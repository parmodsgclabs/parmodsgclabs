<?php
/**
 * SGC LABS® — Unified Inquiry Store
 *
 * Solves Bug 4 (Reply Center stuck on "Product Inquiries 0 New") and powers
 * FR4 (Import / Export). Before v6.9.3, inquiries lived in fragmented places:
 *   - Shortcode form submissions → one custom postmeta scheme
 *   - WooCommerce-side inquiries → another
 *   - Admin "manual add" → option-based
 * Reply Center read only the first source, so anything else looked like zero.
 *
 * This class persists everything to a single options-backed bucket
 * (`sgclabs_inquiries`) and exposes a tiny API plus a `sgclabs_inquiry_received`
 * action that any module can either fire (to add) or listen to (Reply Center,
 * notification mailer, AI auto-replier). Storage is option-based on purpose —
 * Bluehost shared hosting handles small JSON better than custom tables, and
 * the volume here is realistic (a few thousand inquiries lifetime, not millions).
 *
 * Schema (versioned for cross-plugin import compat):
 *   {
 *     "schema_version": "1.0",
 *     "id":             "inq_<uniqid>",
 *     "source":         "shortcode" | "woocommerce" | "manual" | "imported",
 *     "status":         "new" | "in_progress" | "replied" | "closed",
 *     "name":           "...",
 *     "email":          "...",
 *     "phone":          "...",
 *     "country":        "...",
 *     "company":        "...",
 *     "subject":        "...",
 *     "message":        "...",
 *     "product_id":     0,
 *     "product_title":  "...",
 *     "page_url":       "...",
 *     "ip":             "x.x.x.x",
 *     "ua":             "...",
 *     "created_at":     <unix>,
 *     "updated_at":     <unix>,
 *     "replies":        [ { "by":"admin@...", "at":<unix>, "text":"..." } ],
 *     "tags":           [ "..." ],
 *     "meta":           { ... arbitrary }
 *   }
 *
 * @package SGCLabs\Core
 * @since   6.9.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SGCLabs_Inquiry_Store' ) ) :

final class SGCLabs_Inquiry_Store {

	const OPTION         = 'sgclabs_inquiries';
	const SCHEMA_VERSION = '1.0';
	const MAX_RECORDS    = 5000; // keep most recent

	// ────────────────────────────────────────────────────────────
	// Add / fetch / update
	// ────────────────────────────────────────────────────────────

	/**
	 * Persist a new inquiry.
	 *
	 * @param array $data   Partial fields; missing ones get defaults.
	 * @return string       Inquiry ID.
	 */
	public static function add( array $data ): string {
		$now    = time();
		$record = wp_parse_args( $data, [
			'schema_version' => self::SCHEMA_VERSION,
			'id'             => 'inq_' . uniqid( '', true ),
			'source'         => 'manual',
			'status'         => 'new',
			'name'           => '',
			'email'          => '',
			'phone'          => '',
			'country'        => '',
			'company'        => '',
			'subject'        => '',
			'message'        => '',
			'product_id'     => 0,
			'product_title'  => '',
			'page_url'       => '',
			'ip'             => self::anon_ip( $_SERVER['REMOTE_ADDR'] ?? '' ),
			'ua'             => isset( $_SERVER['HTTP_USER_AGENT'] )
				? mb_substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 200 )
				: '',
			'created_at'     => $now,
			'updated_at'     => $now,
			'replies'        => [],
			'tags'           => [],
			'meta'           => [],
		] );

		// Deduplicate by source+email+timestamp(±60s) to avoid form double-submits.
		$existing = self::all();
		foreach ( $existing as $row ) {
			if (
				$row['source'] === $record['source']
				&& strtolower( (string) $row['email'] ) === strtolower( (string) $record['email'] )
				&& abs( ( (int) $row['created_at'] ) - $now ) < 60
				&& trim( (string) $row['message'] ) === trim( (string) $record['message'] )
			) {
				return $row['id']; // existing
			}
		}

		$existing[] = $record;

		// Cap to most recent N.
		if ( count( $existing ) > self::MAX_RECORDS ) {
			usort( $existing, function ( $a, $b ) {
				return ( (int) ( $b['created_at'] ?? 0 ) ) <=> ( (int) ( $a['created_at'] ?? 0 ) );
			} );
			$existing = array_slice( $existing, 0, self::MAX_RECORDS );
		}

		self::write( $existing );

		/**
		 * Fires whenever an inquiry lands in the store.
		 *
		 * Listeners include:
		 *   - AI Reply Center (Bug 4 — counts now refresh)
		 *   - Notification mailer
		 *   - Auto-tagger / spam filter
		 *
		 * @param array $record Full inquiry record.
		 */
		// v7.5.1: callers that already fired the unified hook with their own (richer) payload
		// pass 'fire_hook' => false — previously every form submit fired the hook TWICE,
		// which created duplicate rows in Invoice Pro / CRM / follow-up automation.
		if ( ! array_key_exists( 'fire_hook', $data ) || false !== $data['fire_hook'] ) {
			do_action( 'sgclabs_inquiry_received', $record );
		}

		return $record['id'];
	}

	/**
	 * Fetch all inquiries, newest first.
	 *
	 * @param array $filters status / source / from / to / search.
	 * @return array<int,array>
	 */
	public static function all( array $filters = [] ): array {
		$raw = get_option( self::OPTION, [] );
		if ( ! is_array( $raw ) ) {
			$raw = [];
		}

		// Filter.
		$status = $filters['status'] ?? '';
		$source = $filters['source'] ?? '';
		$from   = isset( $filters['from'] ) ? (int) $filters['from'] : 0;
		$to     = isset( $filters['to'] ) ? (int) $filters['to'] : 0;
		$search = isset( $filters['search'] ) ? mb_strtolower( trim( (string) $filters['search'] ) ) : '';

		$out = [];
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( $status && ( $row['status'] ?? '' ) !== $status ) {
				continue;
			}
			if ( $source && ( $row['source'] ?? '' ) !== $source ) {
				continue;
			}
			if ( $from && (int) ( $row['created_at'] ?? 0 ) < $from ) {
				continue;
			}
			if ( $to && (int) ( $row['created_at'] ?? 0 ) > $to ) {
				continue;
			}
			if ( $search ) {
				$blob = mb_strtolower(
					(string) ( $row['name'] ?? '' ) . ' '
					. (string) ( $row['email'] ?? '' ) . ' '
					. (string) ( $row['subject'] ?? '' ) . ' '
					. (string) ( $row['message'] ?? '' )
				);
				if ( strpos( $blob, $search ) === false ) {
					continue;
				}
			}
			$out[] = $row;
		}

		usort( $out, function ( $a, $b ) {
			return ( (int) ( $b['created_at'] ?? 0 ) ) <=> ( (int) ( $a['created_at'] ?? 0 ) );
		} );
		return $out;
	}

	public static function get( string $id ): ?array {
		foreach ( self::all() as $row ) {
			if ( ( $row['id'] ?? '' ) === $id ) {
				return $row;
			}
		}
		return null;
	}

	public static function update( string $id, array $patch ): bool {
		$rows    = self::all();
		$changed = false;
		foreach ( $rows as $i => $row ) {
			if ( ( $row['id'] ?? '' ) === $id ) {
				$rows[ $i ]              = array_merge( $row, $patch );
				$rows[ $i ]['updated_at'] = time();
				$changed                 = true;
				break;
			}
		}
		if ( $changed ) {
			self::write( $rows );
		}
		return $changed;
	}

	public static function delete( string $id ): bool {
		$rows = self::all();
		$kept = array_values( array_filter( $rows, function ( $r ) use ( $id ) {
			return ( $r['id'] ?? '' ) !== $id;
		} ) );
		if ( count( $kept ) === count( $rows ) ) {
			return false;
		}
		self::write( $kept );
		return true;
	}

	public static function counts(): array {
		$out = [
			'total'       => 0,
			'new'         => 0,
			'in_progress' => 0,
			'replied'     => 0,
			'closed'      => 0,
		];
		foreach ( self::all() as $row ) {
			$out['total']++;
			$status = $row['status'] ?? 'new';
			if ( isset( $out[ $status ] ) ) {
				$out[ $status ]++;
			}
		}
		return $out;
	}

	// ────────────────────────────────────────────────────────────
	// Import / Export (FR4)
	// ────────────────────────────────────────────────────────────

	/**
	 * Export to JSON string with schema versioning.
	 */
	public static function export_json( array $filters = [] ): string {
		$payload = [
			'plugin'         => 'sgclabs-manager-pro',
			'schema'         => 'sgclabs.inquiries',
			'schema_version' => self::SCHEMA_VERSION,
			'exported_at'    => gmdate( 'c' ),
			'exported_by'    => is_user_logged_in() ? wp_get_current_user()->user_login : '',
			'filters'        => $filters,
			'records'        => self::all( $filters ),
		];
		return wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Export to CSV string (flat — first reply preview only).
	 */
	public static function export_csv( array $filters = [] ): string {
		$rows = self::all( $filters );
		$cols = [
			'id', 'source', 'status', 'name', 'email', 'phone', 'country',
			'company', 'subject', 'message', 'product_id', 'product_title',
			'page_url', 'created_at', 'updated_at',
		];
		$out = fopen( 'php://temp', 'w+' );
		fputcsv( $out, $cols );
		foreach ( $rows as $r ) {
			$line = [];
			foreach ( $cols as $c ) {
				$v = $r[ $c ] ?? '';
				if ( $c === 'created_at' || $c === 'updated_at' ) {
					$v = $v ? gmdate( 'Y-m-d H:i:s', (int) $v ) : '';
				}
				$line[] = is_scalar( $v ) ? (string) $v : wp_json_encode( $v );
			}
			fputcsv( $out, $line );
		}
		rewind( $out );
		$csv = stream_get_contents( $out );
		fclose( $out );
		return (string) $csv;
	}

	/**
	 * Import a JSON payload (string or already-decoded array).
	 *
	 * @param string|array $payload
	 * @param string       $mode  'append' (default) or 'merge' (dedupe by id+email)
	 * @return array stats { imported, skipped, errors }
	 */
	public static function import_json( $payload, string $mode = 'append' ): array {
		$decoded = is_array( $payload ) ? $payload : json_decode( (string) $payload, true );
		if ( ! is_array( $decoded ) || empty( $decoded['records'] ) || ! is_array( $decoded['records'] ) ) {
			return [ 'imported' => 0, 'skipped' => 0, 'errors' => [ 'Invalid payload — no records.' ] ];
		}
		$schema = $decoded['schema_version'] ?? '0';
		if ( version_compare( $schema, self::SCHEMA_VERSION, '>' ) ) {
			return [
				'imported' => 0,
				'skipped'  => 0,
				'errors'   => [
					sprintf(
						'Payload schema_version %s is newer than this plugin supports (%s). Upgrade the plugin first.',
						$schema,
						self::SCHEMA_VERSION
					),
				],
			];
		}

		$existing = self::all();
		$by_id    = [];
		$by_email = [];
		foreach ( $existing as $row ) {
			$by_id[ $row['id'] ?? '' ] = true;
			$key                       = strtolower( ( $row['email'] ?? '' ) . '|' . ( $row['created_at'] ?? '' ) );
			$by_email[ $key ]          = true;
		}

		$imported = 0;
		$skipped  = 0;
		foreach ( $decoded['records'] as $rec ) {
			if ( ! is_array( $rec ) ) {
				$skipped++;
				continue;
			}
			$id  = $rec['id']    ?? '';
			$key = strtolower( ( $rec['email'] ?? '' ) . '|' . ( $rec['created_at'] ?? '' ) );

			if ( $mode === 'merge' && ( ( $id && isset( $by_id[ $id ] ) ) || isset( $by_email[ $key ] ) ) ) {
				$skipped++;
				continue;
			}
			$rec['source']      = $rec['source'] ?? 'imported';
			$rec['id']          = $rec['id']     ?? 'inq_' . uniqid( '', true );
			$rec['created_at']  = $rec['created_at'] ?? time();
			$rec['updated_at']  = $rec['updated_at'] ?? time();
			$rec['status']      = $rec['status']     ?? 'new';

			$existing[] = $rec;
			$imported++;
		}

		// Cap.
		if ( count( $existing ) > self::MAX_RECORDS ) {
			usort( $existing, function ( $a, $b ) {
				return ( (int) ( $b['created_at'] ?? 0 ) ) <=> ( (int) ( $a['created_at'] ?? 0 ) );
			} );
			$existing = array_slice( $existing, 0, self::MAX_RECORDS );
		}
		self::write( $existing );

		return [ 'imported' => $imported, 'skipped' => $skipped, 'errors' => [] ];
	}

	// ────────────────────────────────────────────────────────────
	// Internals
	// ────────────────────────────────────────────────────────────

	private static function write( array $rows ): void {
		// `false` = not autoloaded (this can grow).
		update_option( self::OPTION, $rows, false );
	}

	/**
	 * Anonymize last octet of IPv4 / suffix of IPv6 for GDPR-friendly logging.
	 */
	private static function anon_ip( string $ip ): string {
		$ip = trim( $ip );
		if ( $ip === '' ) {
			return '';
		}
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$parts        = explode( '.', $ip );
			$parts[3]     = '0';
			return implode( '.', $parts );
		}
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$parts        = explode( ':', $ip );
			while ( count( $parts ) < 8 ) {
				$parts[] = '0';
			}
			$parts        = array_slice( $parts, 0, 4 );
			return implode( ':', $parts ) . '::';
		}
		return '';
	}
}

endif;
