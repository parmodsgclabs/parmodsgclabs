<?php
/**
 * SGC Smart CRM Pro — Email Sender
 *
 * Queue-based batch email sending via wp_mail().
 * Compatible with any SMTP plugin (WP Mail SMTP, FluentSMTP, etc.).
 * Includes open-pixel and click-redirect tracking.
 *
 * @package SGC_Smart_CRM
 * @since   1.0.0
 * @version   1.0.4 | Build: 20260516-0630
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SGC_CRM_Sender
 *
 * @since 1.0.0
 * @version   1.0.4 | Build: 20260516-0630
 */
class SGC_CRM_Sender {

	/**
	 * Transient key for concurrency lock.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const LOCK_TRANSIENT = 'sgc_crm_queue_lock';

	// =========================================================================
	// QUEUE CAMPAIGN
	// =========================================================================

	/**
	 * Add all matching contacts to the send queue for a campaign.
	 *
	 * @since  1.0.0
	 * @param  int $campaign_id Campaign ID.
	 * @return int Number of emails queued.
	 */
	public static function queue_campaign( $campaign_id ) {
		global $wpdb;

		$campaign = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT * FROM `' . SGC_CRM_DB::campaigns() . '` WHERE id = %d', (int) $campaign_id )
		);

		if ( ! $campaign ) {
			return 0;
		}

		$contacts = SGC_CRM_Contacts::get_for_send(
			$campaign->segment_filter ?? 'all',
			$campaign->tags_filter    ?? ''
		);

		if ( empty( $contacts ) ) {
			return 0;
		}

		$q_table = SGC_CRM_DB::queue();
		$queued  = 0;

		foreach ( $contacts as $contact ) {
			// Skip if already queued.
			$exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT id FROM `{$q_table}` WHERE campaign_id = %d AND contact_id = %d LIMIT 1",
					(int) $campaign_id,
					(int) $contact->id
				)
			);
			if ( $exists ) {
				continue;
			}

			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$q_table,
				array(
					'campaign_id' => (int) $campaign_id,
					'contact_id'  => (int) $contact->id,
					'email'       => $contact->email,
					'status'      => 'pending',
					'track_token' => wp_generate_password( 32, false ),
					'created_at'  => current_time( 'mysql' ),
				)
			);
			++$queued;
		}

		// Mark campaign as sending.
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			SGC_CRM_DB::campaigns(),
			array( 'status' => 'sending' ),
			array( 'id'     => (int) $campaign_id )
		);

		// Trigger first batch asap.
		wp_schedule_single_event( time(), 'sgc_crm_process_queue' );

		return $queued;
	}

	// =========================================================================
	// PROCESS QUEUE (cron)
	// =========================================================================

	/**
	 * Process a batch of pending queue emails.
	 * Called every 5 minutes via WP-Cron.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function process_queue() {
		global $wpdb;

		// Prevent parallel runs.
		if ( get_transient( self::LOCK_TRANSIENT ) ) {
			return;
		}
		set_transient( self::LOCK_TRANSIENT, 1, 120 );

		$batch_size = max( 1, min( 200, (int) get_option( 'sgc_crm_batch_size', 50 ) ) );
		$q_table    = SGC_CRM_DB::queue();
		$ca_table   = SGC_CRM_DB::campaigns();

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT q.*, ca.subject, ca.body_html, ca.from_name, ca.from_email, ca.reply_to
				 FROM `{$q_table}` q
				 INNER JOIN `{$ca_table}` ca ON ca.id = q.campaign_id
				 WHERE q.status = 'pending'
				 ORDER BY q.id ASC
				 LIMIT %d",
				$batch_size
			)
		);

		if ( empty( $rows ) ) {
			// No pending — mark sending campaigns as sent.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "UPDATE `{$ca_table}` SET status='sent', sent_at=NOW() WHERE status='sending'" );
			delete_transient( self::LOCK_TRANSIENT );
			return;
		}

		$sent_per_campaign = array();

		foreach ( $rows as $row ) {
			$contact = SGC_CRM_Contacts::get_by_id( (int) $row->contact_id );

			if ( ! $contact || 'subscribed' !== $contact->status ) {
				$wpdb->update( $q_table, array( 'status' => 'skipped', 'sent_at' => current_time( 'mysql' ) ), array( 'id' => (int) $row->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				continue;
			}

			$sent = self::send_single( $row, $contact );

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$q_table,
				array(
					'status'  => $sent ? 'sent' : 'failed',
					'sent_at' => current_time( 'mysql' ),
				),
				array( 'id' => (int) $row->id )
			);

			if ( $sent ) {
				$sent_per_campaign[ (int) $row->campaign_id ] = ( $sent_per_campaign[ (int) $row->campaign_id ] ?? 0 ) + 1;
			}
		}

		// Update campaign totals.
		foreach ( $sent_per_campaign as $cid => $count ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"UPDATE `{$ca_table}` SET total_sent = total_sent + %d WHERE id = %d",
					$count,
					$cid
				)
			);
		}

		delete_transient( self::LOCK_TRANSIENT );
	}

	// =========================================================================
	// SEND SINGLE EMAIL
	// =========================================================================

	/**
	 * Send one email from the queue.
	 *
	 * @since  1.0.0
	 * @param  object $row     Queue row.
	 * @param  object $contact Contact row.
	 * @return bool
	 */
	private static function send_single( $row, $contact ) {
		$body_html = self::personalize( $row->body_html, $contact, $row->track_token );

		if ( get_option( 'sgc_crm_track_clicks', '1' ) ) {
			$body_html = self::inject_click_tracking( $body_html, $row->track_token );
		}

		$subject    = self::personalize_subject( $row->subject, $contact );
		// v1.1.7: Sanitize email headers to prevent header injection
		$from_name  = preg_replace( '/[\r\n]/', '', $row->from_name  ?: get_option( 'sgc_crm_from_name',  'SGC LABS® Glassware' ) );
		$from_email = sanitize_email( $row->from_email ?: get_option( 'sgc_crm_from_email', 'sales@labsglass.com' ) );
		$reply_to   = sanitize_email( $row->reply_to   ?: get_option( 'sgc_crm_reply_to',   $from_email ) );

		return (bool) wp_mail(
			$contact->email,
			$subject,
			$body_html,
			array(
				'Content-Type: text/html; charset=UTF-8',
				"From: {$from_name} <{$from_email}>",
				"Reply-To: {$reply_to}",
				'X-Mailer: SGC-Smart-CRM/' . SGC_CRM_VERSION,
			)
		);
	}

	/**
	 * Replace merge tags in email body.
	 *
	 * @since  1.0.0
	 * @param  string $html    Email HTML.
	 * @param  object $contact Contact row.
	 * @param  string $token   Tracking token.
	 * @return string
	 */
	private static function personalize( $html, $contact, $token = '' ) {
		$first   = $contact->first_name ?: esc_html__( 'Valued Customer', 'sgc-smart-crm' );
		$full    = trim( $contact->first_name . ' ' . $contact->last_name ) ?: $first;
		$unsub   = add_query_arg(
			array_filter( array( 'sgc_crm_unsub' => '1', 'token' => $token, 'email' => $contact->email ) ),
			home_url( '/' )
		);

		$tags = array(
			'{{first_name}}'      => esc_html( $first ),
			'{{full_name}}'       => esc_html( $full ),
			'{{company}}'         => esc_html( $contact->company ),
			'{{email}}'           => esc_html( $contact->email ),
			'{{country}}'         => esc_html( $contact->country ),
			'{{unsubscribe_url}}' => esc_url( $unsub ),
			'{{year}}'            => gmdate( 'Y' ),
			// Alternate tag styles.
			'{first_name}' => esc_html( $first ),
			'{company}'    => esc_html( $contact->company ),
		);

		return str_replace( array_keys( $tags ), array_values( $tags ), $html );
	}

	/**
	 * Replace merge tags in subject line.
	 *
	 * @since  1.0.0
	 * @param  string $subject  Subject line.
	 * @param  object $contact  Contact row.
	 * @return string
	 */
	private static function personalize_subject( $subject, $contact ) {
		$first = $contact->first_name ?: esc_html__( 'Valued Customer', 'sgc-smart-crm' );
		return str_replace( array( '{{first_name}}', '{first_name}' ), esc_html( $first ), $subject );
	}

	/**
	 * Rewrite href links in email through the click-tracking redirect.
	 *
	 * @since  1.0.0
	 * @param  string $html   Email HTML.
	 * @param  string $token  Tracking token.
	 * @return string
	 */
	private static function inject_click_tracking( $html, $token ) {
		return preg_replace_callback(
			'/href=["\']([^"\']+)["\']/i',
			function ( $matches ) use ( $token ) {
				$url = $matches[1];
				// Skip: unsubscribe, anchor, mailto, tel.
				if ( false !== strpos( $url, 'sgc_crm_unsub' )
					|| '#' === substr( $url, 0, 1 )
					|| 0 === strpos( $url, 'mailto:' )
					|| 0 === strpos( $url, 'tel:' ) ) {
					return $matches[0];
				}
				$track = admin_url( 'admin-ajax.php' )
					. '?action=sgc_crm_track_click&token=' . rawurlencode( $token )
					. '&url=' . rawurlencode( $url );
				return 'href="' . esc_url( $track ) . '"';
			},
			$html
		);
	}

	// =========================================================================
	// TEST SEND
	// =========================================================================

	/**
	 * Send a test copy of a campaign to a specific email.
	 *
	 * @since  1.0.0
	 * @param  int    $campaign_id Campaign ID.
	 * @param  string $to_email    Recipient email.
	 * @return bool
	 */
	public static function send_test( $campaign_id, $to_email ) {
		global $wpdb;

		$campaign = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT * FROM `' . SGC_CRM_DB::campaigns() . '` WHERE id = %d', (int) $campaign_id )
		);
		if ( ! $campaign ) {
			return false;
		}

		// v1.1.7: Sanitize email headers to prevent header injection
		$from_name  = preg_replace( '/[\r\n]/', '', $campaign->from_name  ?: get_option( 'sgc_crm_from_name',  'SGC LABS® Glassware' ) );
		$from_email = sanitize_email( $campaign->from_email ?: get_option( 'sgc_crm_from_email', 'sales@labsglass.com' ) );

		$body = str_replace(
			array( '{{first_name}}', '{first_name}', '{{company}}' ),
			array( esc_html__( 'Test User', 'sgc-smart-crm' ), esc_html__( 'Test User', 'sgc-smart-crm' ), 'Your Company' ),
			$campaign->body_html
		);

		return (bool) wp_mail(
			$to_email,
			'[TEST] ' . $campaign->subject,
			$body,
			array(
				'Content-Type: text/html; charset=UTF-8',
				"From: {$from_name} <{$from_email}>",
				"Reply-To: {$from_email}",
			)
		);
	}

	// =========================================================================
	// DIRECT SEND (automation)
	// =========================================================================

	/**
	 * Send an email directly without queuing (used by automation sequences).
	 *
	 * @since  1.0.0
	 * @param  string               $to_email   Recipient.
	 * @param  string               $subject    Subject.
	 * @param  string               $body_html  HTML body.
	 * @param  array<string, mixed> $options    from_name, from_email, reply_to.
	 * @return bool
	 */
	public static function send_direct( $to_email, $subject, $body_html, $options = array() ) {
		// v1.1.7: Sanitize email headers to prevent header injection
		$from_name  = preg_replace( '/[\r\n]/', '', $options['from_name']  ?? get_option( 'sgc_crm_from_name',  'SGC LABS® Glassware' ) );
		$from_email = sanitize_email( $options['from_email'] ?? get_option( 'sgc_crm_from_email', 'sales@labsglass.com' ) );
		$reply_to   = sanitize_email( $options['reply_to']   ?? get_option( 'sgc_crm_reply_to',   $from_email ) );

		return (bool) wp_mail(
			$to_email,
			$subject,
			$body_html,
			array(
				'Content-Type: text/html; charset=UTF-8',
				"From: {$from_name} <{$from_email}>",
				"Reply-To: {$reply_to}",
				'X-Mailer: SGC-Smart-CRM-Auto/' . SGC_CRM_VERSION,
			)
		);
	}
}

// =========================================================================
// TRACKING: OPEN PIXEL
// =========================================================================
add_action( 'wp_ajax_nopriv_sgc_crm_track_open', 'sgc_crm_handle_open_track' );
add_action( 'wp_ajax_sgc_crm_track_open',        'sgc_crm_handle_open_track' );

/**
 * Track email open via 1×1 GIF pixel request.
 *
 * @since  1.0.0
 * @return void
 */
function sgc_crm_handle_open_track() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$token = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );

	if ( $token ) {
		global $wpdb;
		$q_table  = SGC_CRM_DB::queue();
		$ca_table = SGC_CRM_DB::campaigns();

		$updated = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"UPDATE `{$q_table}` SET opened_at = NOW() WHERE track_token = %s AND opened_at IS NULL",
				$token
			)
		);

		if ( $updated ) {
			$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare( "SELECT campaign_id FROM `{$q_table}` WHERE track_token = %s LIMIT 1", $token )
			);
			if ( $row ) {
				$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare( "UPDATE `{$ca_table}` SET total_opened = total_opened + 1 WHERE id = %d", (int) $row->campaign_id )
				);
			}
		}
	}

	// Return a 1×1 transparent GIF.
	header( 'Content-Type: image/gif' );
	header( 'Cache-Control: no-cache, no-store, must-revalidate' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	echo base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
	exit;
}

// =========================================================================
// TRACKING: CLICK REDIRECT
// =========================================================================
add_action( 'wp_ajax_nopriv_sgc_crm_track_click', 'sgc_crm_handle_click_track' );
add_action( 'wp_ajax_sgc_crm_track_click',        'sgc_crm_handle_click_track' );

/**
 * Track email link click and redirect to destination URL.
 *
 * @since  1.0.0
 * @return void
 */
function sgc_crm_handle_click_track() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$token       = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
	$destination = esc_url_raw( wp_unslash( $_GET['url'] ?? home_url() ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( $token ) {
		global $wpdb;
		$q_table  = SGC_CRM_DB::queue();
		$ca_table = SGC_CRM_DB::campaigns();

		$updated = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"UPDATE `{$q_table}` SET clicked_at = NOW() WHERE track_token = %s AND clicked_at IS NULL",
				$token
			)
		);

		if ( $updated ) {
			$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare( "SELECT campaign_id FROM `{$q_table}` WHERE track_token = %s LIMIT 1", $token )
			);
			if ( $row ) {
				$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare( "UPDATE `{$ca_table}` SET total_clicked = total_clicked + 1 WHERE id = %d", (int) $row->campaign_id )
				);
			}
		}
	}

	wp_redirect( $destination );
	exit;
}
