<?php
/**
 * SGC IIP — Email Inbox (v1.9.0)
 *
 * Forward-to-IIP model: relevant buyer mail is forwarded to iip@labsglass.com.
 * This class polls that mailbox over IMAP, extracts the ORIGINAL sender from
 * the forwarded message, matches/creates an inquiry, and stores the message in
 * the new wp_sgc_iip_messages table. Replies are sent through the branded
 * SGC_IIP_Email::send_reply() frame and logged back as outbound messages.
 *
 * @package SGC_IIP
 * @since   1.9.0
 */
defined( 'ABSPATH' ) || exit;

class SGC_IIP_Inbox {

	const CRON_HOOK = 'sgc_iip_inbox_poll';

	/* ───────────────────────────── Bootstrap ───────────────────────────── */

	public static function init() {
		add_filter( 'cron_schedules', [ __CLASS__, 'cron_schedules' ] );
		add_action( self::CRON_HOOK, [ __CLASS__, 'poll' ] );

		$ajax = [
			'sgc_iip_inbox_list'          => 'ajax_list',
			'sgc_iip_inbox_thread'        => 'ajax_thread',
			'sgc_iip_inbox_mark_read'     => 'ajax_mark_read',
			'sgc_iip_inbox_ai_draft'      => 'ajax_ai_draft',
			'sgc_iip_inbox_send_reply'    => 'ajax_send_reply',
			'sgc_iip_inbox_poll_now'      => 'ajax_poll_now',
			'sgc_iip_inbox_test'          => 'ajax_test',
			'sgc_iip_inbox_save_settings' => 'ajax_save_settings',
		];
		foreach ( $ajax as $action => $method ) {
			add_action( 'wp_ajax_' . $action, [ __CLASS__, $method ] );
		}
	}

	public static function cron_schedules( $schedules ) {
		$mins = max( 5, (int) get_option( 'sgc_iip_inbox_interval', 10 ) );
		$schedules['sgc_iip_inbox_int'] = [
			'interval' => $mins * 60,
			'display'  => sprintf( 'Every %d min (IIP Inbox)', $mins ),
		];
		return $schedules;
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, 'sgc_iip_inbox_int', self::CRON_HOOK );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	private static function verify() {
		check_ajax_referer( 'sgc_iip_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
	}

	/* ─────────────────────── Password encryption (AES-256-CBC) ─────────── */

	private static function ekey() {
		return hash( 'sha256', wp_salt( 'auth' ) . '|sgc_iip_inbox', true );
	}

	public static function enc( $plain ) {
		if ( '' === (string) $plain || ! function_exists( 'openssl_encrypt' ) ) {
			return $plain;
		}
		$iv = openssl_random_pseudo_bytes( 16 );
		$ct = openssl_encrypt( $plain, 'aes-256-cbc', self::ekey(), OPENSSL_RAW_DATA, $iv );
		return 'enc::' . base64_encode( $iv . $ct );
	}

	public static function dec( $stored ) {
		if ( 0 !== strpos( (string) $stored, 'enc::' ) ) {
			return $stored; // plain / legacy
		}
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}
		$raw = base64_decode( substr( $stored, 5 ) );
		$iv  = substr( $raw, 0, 16 );
		$ct  = substr( $raw, 16 );
		return (string) openssl_decrypt( $ct, 'aes-256-cbc', self::ekey(), OPENSSL_RAW_DATA, $iv );
	}

	/* ─────────────────────────── IMAP connection ──────────────────────── */

	public static function imap_available() {
		return function_exists( 'imap_open' );
	}

	private static function settings() {
		return [
			'enabled'  => get_option( 'sgc_iip_inbox_enabled', '0' ) === '1',
			'host'     => trim( get_option( 'sgc_iip_inbox_host', 'imap.zoho.in' ) ),
			'port'     => (int) get_option( 'sgc_iip_inbox_port', 993 ),
			'enc'      => get_option( 'sgc_iip_inbox_security', 'ssl' ), // ssl|tls|none
			'folder'   => get_option( 'sgc_iip_inbox_folder', 'INBOX' ),
			'user'     => trim( get_option( 'sgc_iip_inbox_user', 'iip@labsglass.com' ) ),
			'pass'     => self::dec( get_option( 'sgc_iip_inbox_pass', '' ) ),
		];
	}

	private static function mailbox_string( $s, $with_folder = true ) {
		$flags = '/imap';
		if ( 'ssl' === $s['enc'] ) {
			$flags .= '/ssl';
		} elseif ( 'tls' === $s['enc'] ) {
			$flags .= '/tls';
		} else {
			$flags .= '/notls';
		}
		// novalidate-cert helps with shared hosts that have imperfect certs.
		$flags .= '/novalidate-cert';
		$mb = '{' . $s['host'] . ':' . $s['port'] . $flags . '}';
		return $with_folder ? $mb . $s['folder'] : $mb;
	}

	/**
	 * Opens an IMAP stream. Returns resource or WP_Error.
	 */
	private static function connect() {
		if ( ! self::imap_available() ) {
			return new WP_Error( 'no_imap', 'PHP IMAP extension is not installed on this server. Ask your host to enable php-imap.' );
		}
		$s = self::settings();
		if ( '' === $s['host'] || '' === $s['user'] || '' === $s['pass'] ) {
			return new WP_Error( 'no_creds', 'Inbox credentials are incomplete. Fill host, username and password in Settings → Email Inbox.' );
		}
		imap_errors(); // clear stale errors
		$conn = @imap_open( self::mailbox_string( $s ), $s['user'], $s['pass'], 0, 1 );
		if ( ! $conn ) {
			$err = imap_last_error();
			return new WP_Error( 'imap_fail', 'IMAP connection failed: ' . ( $err ?: 'unknown error' ) );
		}
		return $conn;
	}

	/* ───────────────────────────── Poll (cron) ────────────────────────── */

	public static function poll() {
		$s = self::settings();
		if ( ! $s['enabled'] ) {
			return [ 'ok' => false, 'reason' => 'disabled' ];
		}
		$conn = self::connect();
		if ( is_wp_error( $conn ) ) {
			update_option( 'sgc_iip_inbox_last_error', $conn->get_error_message() );
			return [ 'ok' => false, 'reason' => $conn->get_error_message() ];
		}

		$imported = 0;
		$ids      = @imap_search( $conn, 'UNSEEN', SE_UID );
		if ( is_array( $ids ) ) {
			// Oldest first for natural ordering.
			sort( $ids );
			foreach ( $ids as $uid ) {
				$msgno = imap_msgno( $conn, $uid );
				if ( ! $msgno ) {
					continue;
				}
				$saved = self::import_message( $conn, $msgno, $uid );
				if ( $saved ) {
					$imported++;
				}
				// Mark as read on the server so it is not re-imported.
				@imap_setflag_full( $conn, (string) $uid, '\\Seen', ST_UID );
			}
		}
		imap_close( $conn );

		update_option( 'sgc_iip_inbox_last_poll', current_time( 'mysql' ) );
		update_option( 'sgc_iip_inbox_last_error', '' );
		return [ 'ok' => true, 'imported' => $imported ];
	}

	/* ───────────────────────── Single-message import ──────────────────── */

	private static function import_message( $conn, $msgno, $uid ) {
		global $wpdb;

		$header  = imap_headerinfo( $conn, $msgno );
		$struct  = imap_fetchstructure( $conn, $msgno );
		$msg_id  = isset( $header->message_id ) ? trim( $header->message_id ) : ( 'iipuid-' . $uid . '@local' );

		// Duplicate guard.
		$exists = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
			'SELECT id FROM ' . SGC_IIP_DB::t( 'messages' ) . ' WHERE message_id=%s LIMIT 1',
			$msg_id
		) );
		if ( $exists ) {
			return false;
		}

		$body_html = self::get_body( $conn, $msgno, $struct, true );
		$body_text = self::get_body( $conn, $msgno, $struct, false );

		// Resolve ORIGINAL sender (forwarded mail).
		$orig = self::resolve_original_sender( $conn, $msgno, $struct, $body_html, $body_text, $header );

		$subject = isset( $header->subject ) ? self::mime_decode( $header->subject ) : '(no subject)';
		$subject = preg_replace( '/^(fwd?|re):\s*/i', '', $subject );
		$date    = isset( $header->udate ) ? gmdate( 'Y-m-d H:i:s', $header->udate ) : current_time( 'mysql', true );

		$inquiry_id = self::match_or_create_inquiry( $orig['email'], $orig['name'], $subject, $body_text, $orig['country'] );

		$has_att = self::has_attachments( $struct );

		$wpdb->insert( SGC_IIP_DB::t( 'messages' ), [ // phpcs:ignore WordPress.DB.PreparedSQL
			'inquiry_id'      => $inquiry_id,
			'direction'       => 'in',
			'message_id'      => $msg_id,
			'in_reply_to'     => isset( $header->in_reply_to ) ? trim( $header->in_reply_to ) : '',
			'from_email'      => $orig['email'],
			'from_name'       => $orig['name'],
			'to_email'        => self::settings()['user'],
			'subject'         => $subject,
			'body_html'       => self::sanitize_html( $body_html ?: nl2br( esc_html( $body_text ) ) ),
			'body_text'       => $body_text,
			'has_attachments' => $has_att ? 1 : 0,
			'attachments'     => '[]',
			'is_read'         => 0,
			'received_at'     => $date,
			'created_at'      => current_time( 'mysql' ),
		] );

		self::touch_inquiry( $inquiry_id );
		return true;
	}

	/**
	 * Find the real buyer behind a forwarded message.
	 * 1) message/rfc822 attached part (Gmail/Zoho "Forward as attachment").
	 * 2) "From:" line inside the forwarded body.
	 * 3) Fallback: actual envelope From (the forwarder).
	 */
	private static function resolve_original_sender( $conn, $msgno, $struct, $body_html, $body_text, $header ) {
		// 1) Attached original message.
		if ( ! empty( $struct->parts ) ) {
			foreach ( $struct->parts as $i => $part ) {
				if ( isset( $part->type ) && 2 === (int) $part->type ) { // type 2 = message
					$raw = imap_fetchbody( $conn, $msgno, (string) ( $i + 1 ) );
					if ( preg_match( '/^From:\s*(.+)$/mi', $raw, $m ) ) {
						$parsed = self::parse_address_line( $m[1] );
						if ( $parsed['email'] ) {
							return $parsed + [ 'country' => '' ];
						}
					}
				}
			}
		}
		// 2) Body "From:" line.
		$hay = $body_text ?: wp_strip_all_tags( $body_html );
		if ( preg_match( '/From:\s*(.+?)[\r\n]/i', $hay, $m ) ) {
			$parsed = self::parse_address_line( $m[1] );
			if ( $parsed['email'] ) {
				return $parsed + [ 'country' => '' ];
			}
		}
		// 3) Envelope From.
		$email = '';
		$name  = '';
		if ( ! empty( $header->from[0] ) ) {
			$f     = $header->from[0];
			$email = isset( $f->mailbox, $f->host ) ? $f->mailbox . '@' . $f->host : '';
			$name  = isset( $f->personal ) ? self::mime_decode( $f->personal ) : '';
		}
		return [ 'email' => sanitize_email( $email ), 'name' => $name, 'country' => '' ];
	}

	private static function parse_address_line( $line ) {
		$line = self::mime_decode( trim( $line ) );
		$email = '';
		$name  = '';
		if ( preg_match( '/<([^>]+)>/', $line, $m ) ) {
			$email = $m[1];
			$name  = trim( str_replace( '<' . $m[1] . '>', '', $line ), " \t\"'" );
		} elseif ( preg_match( '/[\w.+-]+@[\w.-]+\.[a-z]{2,}/i', $line, $m ) ) {
			$email = $m[0];
		}
		return [ 'email' => sanitize_email( $email ), 'name' => $name ];
	}

	/* ─────────────────────── Inquiry matching / creation ──────────────── */

	private static function match_or_create_inquiry( $email, $name, $subject, $body_text, $country ) {
		global $wpdb;
		$email = sanitize_email( $email );

		if ( $email ) {
			$existing = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
				'SELECT id FROM ' . SGC_IIP_DB::t( 'inquiries' ) . ' WHERE email=%s ORDER BY id DESC LIMIT 1',
				$email
			) );
			if ( $existing ) {
				return (int) $existing;
			}
		}

		$wpdb->insert( SGC_IIP_DB::t( 'inquiries' ), [ // phpcs:ignore WordPress.DB.PreparedSQL
			'source_type' => 'email_in',
			'name'        => $name ?: ( $email ?: 'Unknown sender' ),
			'email'       => $email,
			'country'     => $country,
			'raw_message' => mb_substr( (string) $body_text, 0, 4000 ),
			'status'      => 'review',
			'pipeline_stage' => 'new',
			'buyer_type'  => 'unknown',
			'created_at'  => current_time( 'mysql' ),
		] );
		return (int) $wpdb->insert_id;
	}

	private static function touch_inquiry( $inquiry_id ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
			'UPDATE ' . SGC_IIP_DB::t( 'inquiries' ) . '
			 SET last_message_at=%s,
			     unread_count=(SELECT COUNT(*) FROM ' . SGC_IIP_DB::t( 'messages' ) . ' WHERE inquiry_id=%d AND direction="in" AND is_read=0)
			 WHERE id=%d',
			current_time( 'mysql' ), $inquiry_id, $inquiry_id
		) );
	}

	/* ───────────────────────────── MIME helpers ───────────────────────── */

	private static function mime_decode( $str ) {
		$out = '';
		foreach ( (array) imap_mime_header_decode( $str ) as $part ) {
			$cs   = ( $part->charset && 'default' !== strtolower( $part->charset ) ) ? $part->charset : 'UTF-8';
			$out .= function_exists( 'mb_convert_encoding' )
				? mb_convert_encoding( $part->text, 'UTF-8', $cs )
				: $part->text;
		}
		return $out;
	}

	private static function decode_part( $data, $encoding ) {
		switch ( (int) $encoding ) {
			case 3: return base64_decode( $data );        // BASE64
			case 4: return quoted_printable_decode( $data ); // QUOTED-PRINTABLE
			default: return $data;
		}
	}

	/**
	 * Walk the MIME tree and return HTML (when $want_html) or plain text body.
	 */
	private static function get_body( $conn, $msgno, $struct, $want_html, $section = '' ) {
		if ( empty( $struct->parts ) ) {
			$data = imap_fetchbody( $conn, $msgno, $section ?: '1' );
			$data = self::decode_part( $data, $struct->encoding ?? 0 );
			return self::to_utf8( $data, $struct );
		}
		$found = '';
		foreach ( $struct->parts as $i => $part ) {
			$idx = $section ? $section . '.' . ( $i + 1 ) : (string) ( $i + 1 );
			if ( ! empty( $part->parts ) ) {
				$nested = self::get_body( $conn, $msgno, $part, $want_html, $idx );
				if ( $nested ) {
					$found = $nested;
					if ( $want_html ) {
						return $found;
					}
				}
				continue;
			}
			$subtype = strtolower( $part->subtype ?? '' );
			$is_text = isset( $part->type ) && 0 === (int) $part->type;
			if ( ! $is_text ) {
				continue;
			}
			if ( $want_html && 'html' === $subtype ) {
				$raw = imap_fetchbody( $conn, $msgno, $idx );
				return self::to_utf8( self::decode_part( $raw, $part->encoding ?? 0 ), $part );
			}
			if ( ! $want_html && 'plain' === $subtype ) {
				$raw = imap_fetchbody( $conn, $msgno, $idx );
				return self::to_utf8( self::decode_part( $raw, $part->encoding ?? 0 ), $part );
			}
		}
		return $found;
	}

	private static function to_utf8( $data, $part ) {
		$charset = 'UTF-8';
		if ( ! empty( $part->parameters ) ) {
			foreach ( $part->parameters as $p ) {
				if ( 0 === strcasecmp( $p->attribute, 'charset' ) ) {
					$charset = $p->value;
				}
			}
		}
		if ( 'UTF-8' !== strtoupper( $charset ) && function_exists( 'mb_convert_encoding' ) ) {
			$data = @mb_convert_encoding( $data, 'UTF-8', $charset );
		}
		return $data;
	}

	private static function has_attachments( $struct ) {
		if ( empty( $struct->parts ) ) {
			return false;
		}
		foreach ( $struct->parts as $part ) {
			if ( ! empty( $part->disposition ) && 0 === strcasecmp( $part->disposition, 'attachment' ) ) {
				return true;
			}
			if ( ! empty( $part->ifdparameters ) ) {
				return true;
			}
		}
		return false;
	}

	private static function sanitize_html( $html ) {
		// Strip dangerous markup but keep formatting/images for display.
		return wp_kses_post( (string) $html );
	}

	/* ───────────────────────────── AJAX: list ─────────────────────────── */

	public static function ajax_list() {
		self::verify();
		global $wpdb;
		$filter = sanitize_key( $_POST['filter'] ?? 'all' ); // all|unread
		$search = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );

		$where = ' WHERE i.id IN ( SELECT inquiry_id FROM ' . SGC_IIP_DB::t( 'messages' ) . ' ) ';
		if ( 'unread' === $filter ) {
			$where .= ' AND i.unread_count > 0 ';
		}
		$params = [];
		if ( '' !== $search ) {
			$where   .= ' AND ( i.name LIKE %s OR i.email LIKE %s ) ';
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		$sql = 'SELECT i.id, i.name, i.email, i.country, i.status, i.unread_count, i.last_message_at,
				(SELECT subject FROM ' . SGC_IIP_DB::t( 'messages' ) . ' m WHERE m.inquiry_id=i.id ORDER BY m.received_at DESC LIMIT 1) AS last_subject,
				(SELECT LEFT(body_text,120) FROM ' . SGC_IIP_DB::t( 'messages' ) . ' m WHERE m.inquiry_id=i.id ORDER BY m.received_at DESC LIMIT 1) AS snippet
				FROM ' . SGC_IIP_DB::t( 'inquiries' ) . ' i ' . $where . '
				ORDER BY i.last_message_at DESC LIMIT 100';

		$rows = $params
			? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) // phpcs:ignore WordPress.DB.PreparedSQL
			: $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL

		wp_send_json_success( [ 'threads' => $rows ?: [] ] );
	}

	public static function ajax_thread() {
		self::verify();
		global $wpdb;
		$id = (int) ( $_POST['inquiry_id'] ?? 0 );

		$inq = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
			'SELECT id,name,email,country,status FROM ' . SGC_IIP_DB::t( 'inquiries' ) . ' WHERE id=%d', $id
		), ARRAY_A );
		if ( ! $inq ) {
			wp_send_json_error( 'Conversation not found.' );
		}

		$msgs = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
			'SELECT id,direction,from_name,from_email,subject,body_html,has_attachments,received_at,is_read,message_id
			 FROM ' . SGC_IIP_DB::t( 'messages' ) . ' WHERE inquiry_id=%d ORDER BY received_at ASC', $id
		), ARRAY_A );

		// Auto mark inbound as read on open.
		$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
			'UPDATE ' . SGC_IIP_DB::t( 'messages' ) . ' SET is_read=1 WHERE inquiry_id=%d AND direction="in"', $id
		) );
		$wpdb->update( SGC_IIP_DB::t( 'inquiries' ), [ 'unread_count' => 0 ], [ 'id' => $id ] ); // phpcs:ignore

		wp_send_json_success( [ 'inquiry' => $inq, 'messages' => $msgs ?: [] ] );
	}

	public static function ajax_mark_read() {
		self::verify();
		global $wpdb;
		$id = (int) ( $_POST['inquiry_id'] ?? 0 );
		$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
			'UPDATE ' . SGC_IIP_DB::t( 'messages' ) . ' SET is_read=1 WHERE inquiry_id=%d AND direction="in"', $id
		) );
		$wpdb->update( SGC_IIP_DB::t( 'inquiries' ), [ 'unread_count' => 0 ], [ 'id' => $id ] ); // phpcs:ignore
		wp_send_json_success();
	}

	/* ─────────────────────── AJAX: AI draft (reply) ───────────────────── */

	public static function ajax_ai_draft() {
		self::verify();
		global $wpdb;
		$mid = (int) ( $_POST['message_id'] ?? 0 );
		$msg = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
			'SELECT * FROM ' . SGC_IIP_DB::t( 'messages' ) . ' WHERE id=%d', $mid
		), ARRAY_A );
		if ( ! $msg ) {
			wp_send_json_error( 'Message not found.' );
		}
		if ( ! class_exists( 'SGC_IIP_AI' ) ) {
			wp_send_json_error( 'AI engine unavailable.' );
		}

		$company = get_option( 'sgc_iip_company_name', 'SGC LABS®' );
		$system  = "You are an expert B2B export sales correspondent for {$company}, a laboratory glassware manufacturer in India since 2003, ISO certified. Reply to the buyer's email professionally and concisely.";

		$incoming = wp_strip_all_tags( $msg['body_text'] ?: $msg['body_html'] );
		$prompt   = "Write ONE polished reply to this buyer email.\n"
			. "Buyer: {$msg['from_name']} <{$msg['from_email']}>\n"
			. "Subject: {$msg['subject']}\n"
			. "Their message:\n" . mb_substr( $incoming, 0, 1200 ) . "\n\n"
			. "Return ONLY clean HTML body content (use <p>, <ul>, <li>, <strong> — no <html>, <head> or <body> tags, no inline CSS). "
			. "Do not add a signature or company footer; that is added automatically.";

		$result = SGC_IIP_AI::call( $prompt, $system, 1200 );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}
		$html = preg_replace( '/```(?:html)?|```/', '', (string) $result );
		wp_send_json_success( [
			'subject' => 'Re: ' . $msg['subject'],
			'body'    => self::sanitize_html( trim( $html ) ),
		] );
	}

	/* ─────────────────────── AJAX: send reply (branded) ───────────────── */

	public static function ajax_send_reply() {
		self::verify();
		global $wpdb;

		$mid     = (int) ( $_POST['message_id'] ?? 0 );
		$subject = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
		$body    = wp_kses_post( wp_unslash( $_POST['body'] ?? '' ) );

		if ( '' === trim( wp_strip_all_tags( $body ) ) ) {
			wp_send_json_error( 'Reply body is empty.' );
		}

		$msg = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
			'SELECT * FROM ' . SGC_IIP_DB::t( 'messages' ) . ' WHERE id=%d', $mid
		), ARRAY_A );
		if ( ! $msg ) {
			wp_send_json_error( 'Original message not found.' );
		}

		$inq = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
			'SELECT * FROM ' . SGC_IIP_DB::t( 'inquiries' ) . ' WHERE id=%d', (int) $msg['inquiry_id']
		), ARRAY_A );

		$to = sanitize_email( $msg['from_email'] );
		if ( ! is_email( $to ) ) {
			wp_send_json_error( 'Buyer email address is invalid.' );
		}
		if ( '' === $subject ) {
			$subject = 'Re: ' . $msg['subject'];
		}

		// Reuse the branded send path. send_reply() wraps body in wrap() frame.
		$result = SGC_IIP_Email::send_reply( (array) $inq, $to, $subject, $body, $msg['message_id'] );

		if ( ! $result ) {
			wp_send_json_error( 'wp_mail() failed to send. Check your SMTP / mail settings.' );
		}

		// Log outbound message in the thread.
		$wpdb->insert( SGC_IIP_DB::t( 'messages' ), [ // phpcs:ignore WordPress.DB.PreparedSQL
			'inquiry_id'  => (int) $msg['inquiry_id'],
			'direction'   => 'out',
			'message_id'  => '<' . wp_generate_uuid4() . '@labsglass.com>',
			'in_reply_to' => $msg['message_id'],
			'from_email'  => self::settings()['user'],
			'from_name'   => get_option( 'sgc_iip_company_name', 'SGC LABS®' ),
			'to_email'    => $to,
			'subject'     => $subject,
			'body_html'   => $body,
			'body_text'   => wp_strip_all_tags( $body ),
			'is_read'     => 1,
			'received_at' => current_time( 'mysql' ),
			'created_at'  => current_time( 'mysql' ),
		] );
		self::touch_inquiry( (int) $msg['inquiry_id'] );

		wp_send_json_success( [ 'sent' => true ] );
	}

	/* ─────────────────────── AJAX: poll now / test / settings ─────────── */

	public static function ajax_poll_now() {
		self::verify();
		$res = self::poll();
		if ( empty( $res['ok'] ) ) {
			wp_send_json_error( $res['reason'] ?? 'Poll failed.' );
		}
		wp_send_json_success( [ 'imported' => $res['imported'] ?? 0 ] );
	}

	public static function ajax_test() {
		self::verify();
		$conn = self::connect();
		if ( is_wp_error( $conn ) ) {
			wp_send_json_error( $conn->get_error_message() );
		}
		$check = imap_check( $conn );
		$count = $check ? (int) $check->Nmsgs : 0;
		imap_close( $conn );
		wp_send_json_success( [ 'message' => "Connected. Mailbox has {$count} message(s)." ] );
	}

	public static function ajax_save_settings() {
		self::verify();
		$map = [
			'sgc_iip_inbox_enabled'  => isset( $_POST['enabled'] ) && '1' === $_POST['enabled'] ? '1' : '0',
			'sgc_iip_inbox_host'     => sanitize_text_field( wp_unslash( $_POST['host'] ?? '' ) ),
			'sgc_iip_inbox_port'     => (int) ( $_POST['port'] ?? 993 ),
			'sgc_iip_inbox_security' => sanitize_key( $_POST['security'] ?? 'ssl' ),
			'sgc_iip_inbox_folder'   => sanitize_text_field( wp_unslash( $_POST['folder'] ?? 'INBOX' ) ),
			'sgc_iip_inbox_user'     => sanitize_email( wp_unslash( $_POST['user'] ?? '' ) ),
			'sgc_iip_inbox_interval' => max( 5, (int) ( $_POST['interval'] ?? 10 ) ),
		];
		foreach ( $map as $k => $v ) {
			update_option( $k, $v );
		}
		// Password: only overwrite if a new one was entered.
		$pass = (string) wp_unslash( $_POST['pass'] ?? '' );
		if ( '' !== $pass ) {
			update_option( 'sgc_iip_inbox_pass', self::enc( $pass ) );
		}
		// Reschedule with possibly-new interval.
		self::unschedule();
		if ( '1' === $map['sgc_iip_inbox_enabled'] ) {
			self::schedule();
		}
		wp_send_json_success( [ 'message' => 'Inbox settings saved.' ] );
	}
}

SGC_IIP_Inbox::init();
