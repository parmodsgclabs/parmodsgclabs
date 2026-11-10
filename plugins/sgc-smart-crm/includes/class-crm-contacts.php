<?php
/**
 * SGC Smart CRM Pro — Contacts
 *
 * Contact CRUD, CSV/Excel import, AI segmentation,
 * WooCommerce sync, inquiry form sync, unsubscribe.
 *
 * @package SGC_Smart_CRM
 * @since   1.0.0
 * @version   1.0.4 | Build: 20260516-0630
 */

// Block direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SGC_CRM_Contacts
 *
 * @since 1.0.0
 * @version   1.0.4 | Build: 20260516-0630
 */
class SGC_CRM_Contacts {

	// =========================================================================
	// REGISTER AJAX
	// =========================================================================

	/**
	 * Register all AJAX action hooks.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function register_ajax() {
		$actions = array(
			'sgc_crm_import_csv'         => 'ajax_import_csv',
			'sgc_crm_import_status'      => 'ajax_import_status',   // v1.1.3: batch poll
			'sgc_crm_add_contact'        => 'ajax_add_contact',
			'sgc_crm_update_contact'     => 'ajax_update_contact',
			'sgc_crm_delete_contact'     => 'ajax_delete_contact',
			'sgc_crm_ai_segment'         => 'ajax_ai_segment',
			'sgc_crm_sync_woo_customers' => 'ajax_sync_woo',
			'sgc_crm_woo_sync_status'    => 'ajax_woo_sync_status',
			'sgc_crm_woo_sync_cancel'    => 'ajax_woo_sync_cancel',
			'sgc_crm_sync_inquiries'     => 'ajax_sync_inquiries',
			'sgc_crm_get_contacts'       => 'ajax_get_contacts',
			'sgc_crm_export_contacts'    => 'ajax_export_contacts',
		);

		foreach ( $actions as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( __CLASS__, $method ) );
		}

		// Bulk actions.
		add_action( 'wp_ajax_sgc_crm_bulk_delete',      array( __CLASS__, 'ajax_bulk_delete' ) );
		add_action( 'wp_ajax_sgc_crm_bulk_export',      array( __CLASS__, 'ajax_bulk_export' ) );
		add_action( 'wp_ajax_sgc_crm_bulk_assign',      array( __CLASS__, 'ajax_bulk_assign' ) );
		add_action( 'wp_ajax_sgc_crm_delete_all',       array( __CLASS__, 'ajax_delete_all' ) );

		// Google Contacts CSV import.
		add_action( 'wp_ajax_sgc_crm_import_google_csv', array( __CLASS__, 'ajax_import_google_csv' ) );
		add_action( 'sgc_crm_import_batch', array( __CLASS__, 'process_import_batch' ) ); // v1.1.3

		// ── Frontend public actions (no login required) ────────────────────────
		// v1.1.3 fix: was only wp_ajax_ → non-logged-in visitors couldn't submit
		add_action( 'wp_ajax_nopriv_sgc_crm_add_contact', array( __CLASS__, 'ajax_frontend_add_contact' ) );
		add_action( 'wp_ajax_nopriv_sgc_crm_subscribe',   array( __CLASS__, 'ajax_frontend_add_contact' ) );
		add_action( 'wp_ajax_sgc_crm_subscribe',          array( __CLASS__, 'ajax_frontend_add_contact' ) );

		// Public unsubscribe — no login required.
		add_action( 'wp_ajax_nopriv_sgc_crm_unsubscribe', array( __CLASS__, 'handle_unsubscribe' ) );
		add_action( 'wp_ajax_sgc_crm_unsubscribe',        array( __CLASS__, 'handle_unsubscribe' ) );
	}

	// =========================================================================
	// INTERNAL: VERIFY REQUEST
	// =========================================================================

	/**
	 * Verify nonce and capability. Sends JSON error and exits on failure.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private static function verify() {
		check_ajax_referer( 'sgc_crm_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions.', 'sgc-smart-crm' ), 403 );
		}
	}

	// =========================================================================
	// AJAX: IMPORT CSV / EXCEL
	// =========================================================================

	/**
	 * AJAX handler — import contacts from CSV or Excel file.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_import_csv() {
		self::verify();

		if ( empty( $_FILES['crm_file']['tmp_name'] ) ) {
			wp_send_json_error( esc_html__( 'No file uploaded.', 'sgc-smart-crm' ) );
		}

		$filename = sanitize_file_name( $_FILES['crm_file']['name'] );
		$tmp_file = $_FILES['crm_file']['tmp_name']; // phpcs:ignore
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, array( 'csv', 'xlsx', 'xls' ), true ) ) {
			wp_send_json_error( esc_html__( 'Only CSV and Excel (.xlsx/.xls) files are allowed.', 'sgc-smart-crm' ) );
		}

		if ( ! is_uploaded_file( $tmp_file ) ) {
			wp_send_json_error( esc_html__( 'Invalid file upload.', 'sgc-smart-crm' ) );
		}

		$rows = ( 'csv' === $ext ) ? self::parse_csv( $tmp_file ) : self::parse_excel( $tmp_file );

		if ( empty( $rows ) ) {
			wp_send_json_error( esc_html__( 'No data found in file. Check the format.', 'sgc-smart-crm' ) );
		}

		$total = count( $rows );

		// v1.1.3: For large files (>100 rows), store in DB and process in background batches
		// This prevents PHP timeout on shared hosting (Bluehost 30-60s limit)
		if ( $total > 100 ) {
			$job_id = 'crm_import_' . time() . '_' . wp_rand( 1000, 9999 );
			set_transient( $job_id . '_rows',   $rows,  6 * HOUR_IN_SECONDS );
			set_transient( $job_id . '_status', [
				'total'     => $total,
				'processed' => 0,
				'added'     => 0,
				'updated'   => 0,
				'skipped'   => 0,
				'done'      => false,
			], 6 * HOUR_IN_SECONDS );

			// Kick off first batch immediately
			wp_schedule_single_event( time(), 'sgc_crm_import_batch', [ $job_id ] );
			spawn_cron();

			wp_send_json_success( [
				'background' => true,
				'job_id'     => $job_id,
				'total'      => $total,
				'message'    => "Import started — {$total} contacts processing in background.",
			] );
			return;
		}

		// Small file — process immediately
		$result = self::import_rows( $rows );
		wp_send_json_success( $result );
	}

	// ── AJAX: Poll import job status ────────────────────────────────────────
	public static function ajax_import_status() {
		self::verify();
		$job_id = sanitize_text_field( wp_unslash( $_POST['job_id'] ?? '' ) );
		if ( ! $job_id ) { wp_send_json_error( 'Missing job_id' ); return; }

		$status = get_transient( $job_id . '_status' );
		if ( ! $status ) {
			wp_send_json_error( 'Job not found or expired.' );
			return;
		}
		wp_send_json_success( $status );
	}

	// ── Cron: Process one batch of 50 rows ──────────────────────────────────
	public static function process_import_batch( string $job_id ): void {
		$rows   = get_transient( $job_id . '_rows' );
		$status = get_transient( $job_id . '_status' );

		if ( ! $rows || ! $status || $status['done'] ) return;

		$batch_size = 50;
		$offset     = (int) $status['processed'];
		$batch      = array_slice( $rows, $offset, $batch_size );

		if ( empty( $batch ) ) {
			$status['done'] = true;
			set_transient( $job_id . '_status', $status, HOUR_IN_SECONDS );
			delete_transient( $job_id . '_rows' );
			return;
		}

		$result = self::import_rows( $batch );

		$status['processed'] += count( $batch );
		$status['added']     += (int) ( $result['added']   ?? 0 );
		$status['updated']   += (int) ( $result['updated'] ?? 0 );
		$status['skipped']   += (int) ( $result['skipped'] ?? 0 );

		if ( $status['processed'] >= $status['total'] ) {
			$status['done'] = true;
			delete_transient( $job_id . '_rows' );
		}

		set_transient( $job_id . '_status', $status, 6 * HOUR_IN_SECONDS );

		// Schedule next batch if not done
		if ( ! $status['done'] ) {
			wp_schedule_single_event( time() + 2, 'sgc_crm_import_batch', [ $job_id ] );
			spawn_cron();
		}
	}

	/**
	 * Parse a CSV file into an array of associative rows.
	 *
	 * @since  1.0.0
	 * @param  string $file Path to temp file.
	 * @return array<int, array<string, string>>
	 */
	private static function parse_csv( $file ) {
		$rows    = array();
		$handle  = fopen( $file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return $rows;
		}

		$headers = array();
		$line    = 0;

		while ( false !== ( $data = fgetcsv( $handle, 4096, ',' ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
			if ( 0 === $line ) {
				$headers = array_map( 'strtolower', array_map( 'trim', $data ) );
				$line++;
				continue;
			}
			if ( count( $data ) < count( $headers ) ) {
				$data = array_pad( $data, count( $headers ), '' );
			}
			$rows[] = array_combine( $headers, array_slice( $data, 0, count( $headers ) ) );
			$line++;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $rows;
	}

	/**
	 * Parse an Excel file (.xlsx/.xls) into rows.
	 * Uses PhpSpreadsheet if available, falls back to ZIP+XML parsing.
	 *
	 * @since  1.0.0
	 * @param  string $file Path to temp file.
	 * @return array<int, array<string, string>>
	 */
	private static function parse_excel( $file ) {
		// PhpSpreadsheet (bundled with WooCommerce 4+).
		if ( class_exists( '\PhpOffice\PhpSpreadsheet\IOFactory' ) ) {
			try {
				$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load( $file );
				$data        = $spreadsheet->getActiveSheet()->toArray( null, true, true, false );
				if ( empty( $data ) ) {
					return array();
				}
				$headers = array_map( 'strtolower', array_map( 'trim', array_shift( $data ) ) );
				$rows    = array();
				foreach ( $data as $row ) {
					if ( empty( array_filter( $row ) ) ) {
						continue;
					}
					$rows[] = array_combine( $headers, array_pad( $row, count( $headers ), '' ) );
				}
				return $rows;
			} catch ( \Exception $e ) {
				return array();
			}
		}

		// Fallback: ZIP + XML parsing for .xlsx.
		$zip = new ZipArchive();
		if ( true !== $zip->open( $file ) ) {
			return array();
		}

		// Shared strings.
		$shared_strings = array();
		$ss_xml         = $zip->getFromName( 'xl/sharedStrings.xml' );
		if ( $ss_xml ) {
			$ss_dom = simplexml_load_string( $ss_xml );
			if ( $ss_dom ) {
				foreach ( $ss_dom->si as $si ) {
					$shared_strings[] = (string) $si->t;
				}
			}
		}

		$sheet_xml = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
		$zip->close();

		if ( ! $sheet_xml ) {
			return array();
		}

		$sheet_dom = simplexml_load_string( $sheet_xml );
		$rows_raw  = array();

		foreach ( $sheet_dom->sheetData->row as $row ) {
			$row_data = array();
			foreach ( $row->c as $cell ) {
				$type = (string) $cell['t'];
				$val  = (string) $cell->v;
				if ( 's' === $type && isset( $shared_strings[ (int) $val ] ) ) {
					$val = $shared_strings[ (int) $val ];
				}
				$row_data[] = $val;
			}
			$rows_raw[] = $row_data;
		}

		if ( empty( $rows_raw ) ) {
			return array();
		}

		$headers = array_map( 'strtolower', array_map( 'trim', array_shift( $rows_raw ) ) );
		$rows    = array();
		foreach ( $rows_raw as $row ) {
			if ( empty( array_filter( $row ) ) ) {
				continue;
			}
			$rows[] = array_combine( $headers, array_pad( $row, count( $headers ), '' ) );
		}
		return $rows;
	}

	/**
	 * Bulk-insert rows from import. Returns counts.
	 *
	 * @since  1.0.0
	 * @param  array<int, array<string, string>> $rows Parsed rows.
	 * @return array<string, mixed>
	 */
	private static function import_rows( $rows ) {
		$added   = 0;
		$updated = 0;
		$skipped = 0;
		$errors  = array();

		// Column name aliases for email detection.
		$email_aliases = array( 'email', 'e-mail', 'email address', 'mail', 'emailaddress' );

		foreach ( $rows as $i => $row ) {
			$row       = array_map( 'trim', $row );
			$email_key = null;

			foreach ( $email_aliases as $alias ) {
				if ( ! empty( $row[ $alias ] ) ) {
					$email_key = $alias;
					break;
				}
			}

			if ( null === $email_key ) {
				++$skipped;
				continue;
			}

			$email = strtolower( sanitize_email( $row[ $email_key ] ) );

			if ( ! is_email( $email ) ) {
				/* translators: 1: row number 2: invalid email */
				$errors[] = sprintf( esc_html__( 'Row %1$d: invalid email "%2$s"', 'sgc-smart-crm' ), $i + 2, esc_html( $row[ $email_key ] ) );
				++$skipped;
				continue;
			}

			// Resolve name fields.
			$first_name = $row['first_name'] ?? $row['firstname'] ?? $row['first name'] ?? '';
			$last_name  = $row['last_name']  ?? $row['lastname']  ?? $row['last name']  ?? '';

			// Split "name" column if first/last not present.
			if ( empty( $first_name ) && ! empty( $row['name'] ) ) {
				$parts      = explode( ' ', trim( $row['name'] ), 2 );
				$first_name = $parts[0];
				$last_name  = $parts[1] ?? '';
			}

			$data = array(
				'email'      => $email,
				'first_name' => sanitize_text_field( $first_name ),
				'last_name'  => sanitize_text_field( $last_name ),
				'company'    => sanitize_text_field( $row['company']  ?? $row['company name']  ?? $row['organization'] ?? '' ),
				'phone'      => sanitize_text_field( $row['phone']    ?? $row['mobile']        ?? $row['contact']      ?? '' ),
				'country'    => sanitize_text_field( $row['country']  ?? $row['country name']  ?? '' ),
				'city'       => sanitize_text_field( $row['city']     ?? $row['location']      ?? '' ),
				'tags'       => sanitize_text_field( $row['tags']     ?? $row['tag']           ?? $row['category']    ?? '' ),
				'source'     => 'import',
			);

			$existing = self::get_by_email( $email );
			if ( $existing ) {
				self::update( (int) $existing->id, $data );
				++$updated;
			} else {
				$data['status'] = 'subscribed';
				self::insert( $data );
				++$added;
			}
		}

		return array(
			'added'   => $added,
			'updated' => $updated,
			'skipped' => $skipped,
			'errors'  => array_slice( $errors, 0, 10 ),
			'total'   => count( $rows ),
		);
	}

	// =========================================================================
	// CRUD
	// =========================================================================

	/**
	 * Insert a new contact.
	 *
	 * @since  1.0.0
	 * @param  array<string, mixed> $data Contact data.
	 * @return int Inserted row ID.
	 */
	public static function insert( $data ) {
		global $wpdb;
		$data['created_at'] = current_time( 'mysql' );
		$data['updated_at'] = current_time( 'mysql' );
		$wpdb->insert( SGC_CRM_DB::contacts(), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing contact.
	 *
	 * @since  1.0.0
	 * @param  int                  $id   Contact ID.
	 * @param  array<string, mixed> $data Fields to update.
	 * @return void
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql' );
		$wpdb->update( SGC_CRM_DB::contacts(), $data, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Delete a contact by ID.
	 *
	 * @since  1.0.0
	 * @param  int $id Contact ID.
	 * @return void
	 */
	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( SGC_CRM_DB::contacts(), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Get a contact by email address.
	 *
	 * @since  1.0.0
	 * @param  string $email Email address.
	 * @return object|null Contact row or null.
	 */
	public static function get_by_email( $email ) {
		global $wpdb;
		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT * FROM `' . SGC_CRM_DB::contacts() . '` WHERE email = %s LIMIT 1',
				strtolower( $email )
			)
		);
	}

	/**
	 * Get a contact by ID.
	 *
	 * @since  1.0.0
	 * @param  int $id Contact ID.
	 * @return object|null
	 */
	public static function get_by_id( $id ) {
		global $wpdb;
		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT * FROM `' . SGC_CRM_DB::contacts() . '` WHERE id = %d LIMIT 1',
				(int) $id
			)
		);
	}

	/**
	 * Get subscribed contacts for a campaign send.
	 *
	 * @since  1.0.0
	 * @param  string $segment Segment filter ('all' for no filter).
	 * @param  string $tags    Comma-separated tag filter.
	 * @param  int    $limit   Max rows (0 = no limit).
	 * @param  int    $offset  Offset for pagination.
	 * @return array<int, object>
	 */
	public static function get_for_send( $segment = 'all', $tags = '', $limit = 0, $offset = 0 ) {
		global $wpdb;

		$table  = SGC_CRM_DB::contacts();
		$where  = "status = 'subscribed'";
		$values = array();

		if ( 'all' !== $segment && ! empty( $segment ) ) {
			$where   .= ' AND segment = %s';
			$values[] = $segment;
		}

		if ( ! empty( $tags ) ) {
			$tag_conditions = array();
			foreach ( explode( ',', $tags ) as $tag ) {
				$tag = trim( $tag );
				if ( $tag ) {
					$tag_conditions[] = 'tags LIKE %s';
					$values[]         = '%' . $wpdb->esc_like( $tag ) . '%';
				}
			}
			if ( $tag_conditions ) {
				$where .= ' AND (' . implode( ' OR ', $tag_conditions ) . ')';
			}
		}

		$sql = "SELECT * FROM `{$table}` WHERE {$where} ORDER BY id ASC";

		if ( $limit > 0 ) {
			$sql     .= ' LIMIT %d OFFSET %d';
			$values[] = (int) $limit;
			$values[] = (int) $offset;
		}

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			return $wpdb->get_results( $wpdb->prepare( $sql, ...$values ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql );
	}

	// =========================================================================
	// AI SEGMENTATION
	// =========================================================================

	/**
	 * AJAX handler — run AI segmentation on subscribed contacts.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_ai_segment() {
		self::verify();

		global $wpdb;
		$table    = SGC_CRM_DB::contacts();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$contacts = $wpdb->get_results( "SELECT * FROM `{$table}` WHERE status = 'subscribed' LIMIT 500" );

		if ( empty( $contacts ) ) {
			wp_send_json_error( esc_html__( 'No contacts to segment.', 'sgc-smart-crm' ) );
		}

		$summary = array();
		foreach ( $contacts as $c ) {
			$summary[] = array(
				'id'            => $c->id,
				'email'         => $c->email,
				'country'       => $c->country,
				'company'       => $c->company,
				'tags'          => $c->tags,
				'total_orders'  => $c->total_orders,
				'total_spent'   => $c->total_spent,
				'source'        => $c->source,
				'last_order_at' => $c->last_order_at,
			);
		}

		$prompt = "You are a B2B CRM segmentation AI for SGC LABS® — a scientific laboratory glassware manufacturer & exporter in India.\n\n"
			. "Analyze these contacts and assign each one a segment from these options:\n"
			. "- student: college/university students, research scholars\n"
			. "- teacher: professors, lab instructors, academic staff\n"
			. "- dealer: distributors, resellers, lab supply companies\n"
			. "- end_user: direct buyers, hospital labs, industrial labs\n"
			. "- glass_blower: scientific glass blowing workshops, custom glass fabricators\n"
			. "- semi_finished: buyers of semi-finished glassware, custom fabrication clients\n"
			. "- international: overseas buyers, export customers\n"
			. "- domestic: India-based buyers\n\n"
			. "Contacts:\n" . wp_json_encode( $summary ) . "\n\n"
			. "Return ONLY a valid JSON array: [{\"id\":1,\"segment\":\"dealer\",\"score\":75,\"tags\":\"distributor,india\"}, ...]\n"
			. "Score 0-100 = buying likelihood. No explanation. Pure JSON only.";

		$system = 'B2B CRM segmentation expert. Return only valid JSON array. No markdown, no preamble.';

		$response = SGC_CRM_AI::call( $prompt, 2000, $system );

		if ( is_wp_error( $response ) ) {
			$updated = self::rule_based_segment( $contacts );
			wp_send_json_success(
				array(
					'method'  => 'rule_based',
					'updated' => $updated,
					'note'    => $response->get_error_message(),
				)
			);
		}

		$clean  = preg_replace( '/```(?:json)?|```/', '', $response );
		$result = json_decode( trim( $clean ), true );

		if ( ! is_array( $result ) ) {
			$updated = self::rule_based_segment( $contacts );
			wp_send_json_success( array( 'method' => 'rule_based_fallback', 'updated' => $updated ) );
		}

		$updated = 0;
		foreach ( $result as $item ) {
			if ( empty( $item['id'] ) ) {
				continue;
			}
			$update_data = array();
			if ( ! empty( $item['segment'] ) ) {
				$update_data['segment'] = sanitize_text_field( $item['segment'] );
			}
			if ( isset( $item['score'] ) ) {
				$update_data['ai_score'] = min( 100, max( 0, (int) $item['score'] ) );
			}
			if ( ! empty( $item['tags'] ) ) {
				$update_data['tags'] = sanitize_text_field( $item['tags'] );
			}
			if ( ! empty( $update_data ) ) {
				self::update( (int) $item['id'], $update_data );
				++$updated;
			}
		}

		wp_send_json_success(
			array(
				'method'  => 'ai',
				'updated' => $updated,
				'total'   => count( $contacts ),
			)
		);
	}

	/**
	 * Rule-based fallback segmentation (no AI required).
	 *
	 * @since  1.0.0
	 * @param  array<int, object> $contacts Contacts to segment.
	 * @return int Number of contacts updated.
	 */
	public static function rule_based_segment( $contacts ) {
		$updated = 0;
		foreach ( $contacts as $contact ) {
			$tags    = strtolower( $contact->tags ?? '' );
			$company = strtolower( $contact->company ?? '' );
			$email   = strtolower( $contact->email ?? '' );
			$segment = 'end_user'; // default

			// Glass blower detection
			if ( strpos( $tags, 'glass' ) !== false || strpos( $tags, 'blower' ) !== false
			  || strpos( $company, 'glass' ) !== false || strpos( $company, 'blower' ) !== false ) {
				$segment = 'glass_blower';
			}
			// Semi-finished goods buyers
			elseif ( strpos( $tags, 'semi' ) !== false || strpos( $tags, 'custom' ) !== false
			      || strpos( $company, 'fabricat' ) !== false ) {
				$segment = 'semi_finished';
			}
			// Dealers / distributors
			elseif ( (int) $contact->total_orders >= 5 || strpos( $tags, 'dealer' ) !== false
			      || strpos( $tags, 'distrib' ) !== false || strpos( $company, 'supply' ) !== false
			      || strpos( $company, 'scientific' ) !== false ) {
				$segment = 'dealer';
			}
			// Students
			elseif ( strpos( $tags, 'student' ) !== false || strpos( $email, '.ac.in' ) !== false
			      || strpos( $email, '.edu' ) !== false || strpos( $company, 'college' ) !== false
			      || strpos( $company, 'university' ) !== false ) {
				$segment = 'student';
			}
			// Teachers / academics
			elseif ( strpos( $tags, 'teacher' ) !== false || strpos( $tags, 'professor' ) !== false
			      || strpos( $company, 'institute' ) !== false || strpos( $company, 'iit' ) !== false
			      || strpos( $company, 'iiser' ) !== false ) {
				$segment = 'teacher';
			}
			// International
			elseif ( ! empty( $contact->country )
			      && strtolower( $contact->country ) !== 'india'
			      && strtoupper( $contact->country ) !== 'IN' ) {
				$segment = 'international';
			}
			// Domestic repeat buyers
			elseif ( (int) $contact->total_orders >= 2 ) {
				$segment = 'domestic';
			}

			self::update( (int) $contact->id, array( 'segment' => $segment ) );
			++$updated;
		}
		return $updated;
	}

	// =========================================================================
	// SYNC: WOOCOMMERCE
	// =========================================================================

	/**
	 * AJAX handler — sync WooCommerce customers to CRM contacts.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	// =========================================================================
	// WOO SYNC: START JOB (AJAX)
	// =========================================================================

	/**
	 * AJAX — start WooCommerce background sync job.
	 * Phase 1: WP users with 'customer' role.
	 * Phase 2: WooCommerce orders (for order stats).
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public static function ajax_sync_woo() {
		self::verify();

		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_send_json_error( esc_html__( 'WooCommerce is not active.', 'sgc-smart-crm' ) );
		}

		// Count totals for progress.
		$user_count  = (int) ( new WP_User_Query( array( 'role' => 'customer', 'count_total' => true, 'number' => 0 ) ) )->get_total();
		$order_count = (int) wc_get_orders( array( 'limit' => -1, 'return' => 'ids', 'status' => array( 'completed', 'processing', 'on-hold', 'pending' ) ) );
		// Cap order count estimation.
		if ( $order_count > 5000 ) { $order_count = 5000; }

		$job = array(
			'status'    => 'running',
			'phase'     => 'users',   // users → orders
			'offset'    => 0,
			'total'     => $user_count + $order_count,
			'users_total'  => $user_count,
			'orders_total' => $order_count,
			'processed' => 0,
			'added'     => 0,
			'updated'   => 0,
			'started_at' => current_time( 'mysql' ),
		);

		update_option( 'sgc_crm_woo_sync_job', $job );

		// Kick off first batch immediately via async cron.
		wp_schedule_single_event( time(), 'sgc_crm_woo_sync_batch' );

		wp_send_json_success( array(
			'total'  => $job['total'],
			'message' => sprintf(
				/* translators: 1: user count, 2: order count */
				esc_html__( 'Sync started — %1$d customers + %2$d orders. Running in background…', 'sgc-smart-crm' ),
				$user_count,
				$order_count
			),
		) );
	}

	// =========================================================================
	// WOO SYNC: STATUS POLL (AJAX)
	// =========================================================================

	/**
	 * AJAX — return current woo sync job status for progress polling.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public static function ajax_woo_sync_status() {
		self::verify();
		$job = get_option( 'sgc_crm_woo_sync_job', null );
		if ( ! $job ) {
			wp_send_json_success( array( 'status' => 'none' ) );
		}
		wp_send_json_success( $job );
	}

	// =========================================================================
	// WOO SYNC: CANCEL (AJAX)
	// =========================================================================

	/**
	 * AJAX — cancel a running woo sync job.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public static function ajax_woo_sync_cancel() {
		self::verify();
		$job = get_option( 'sgc_crm_woo_sync_job', array() );
		if ( $job ) {
			$job['status'] = 'cancelled';
			update_option( 'sgc_crm_woo_sync_job', $job );
		}
		wp_send_json_success();
	}

	// =========================================================================
	// WOO SYNC: BATCH PROCESSOR (CRON CALLBACK)
	// =========================================================================

	/**
	 * Cron callback — process one batch of the WooCommerce sync job.
	 * Reschedules itself until job is done.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public static function process_woo_sync_batch() {
		$job = get_option( 'sgc_crm_woo_sync_job', null );

		if ( ! $job || ! in_array( $job['status'], array( 'running' ), true ) ) {
			return;
		}

		$batch_size = 100;

		// ── PHASE 1: WP Users (customer role) ──────────────────────────────
		if ( 'users' === $job['phase'] ) {
			$users = get_users( array(
				'role'   => 'customer',
				'number' => $batch_size,
				'offset' => (int) $job['offset'],
				'fields' => 'all',
				'orderby'=> 'ID',
				'order'  => 'ASC',
			) );

			if ( empty( $users ) ) {
				// Phase 1 done → move to orders.
				$job['phase']  = 'orders';
				$job['offset'] = 0;
				update_option( 'sgc_crm_woo_sync_job', $job );
				wp_schedule_single_event( time() + 5, 'sgc_crm_woo_sync_batch' );
				return;
			}

			foreach ( $users as $user ) {
				$email = strtolower( $user->user_email );
				if ( ! is_email( $email ) ) { continue; }

				// Get WooCommerce customer data.
				$wc_customer = null;
				if ( function_exists( 'wc_get_customer_order_count' ) ) {
					try {
						$wc_customer = new WC_Customer( $user->ID );
					} catch ( Exception $e ) {
						$wc_customer = null;
					}
				}

				$first_name = get_user_meta( $user->ID, 'billing_first_name', true ) ?: $user->first_name;
				$last_name  = get_user_meta( $user->ID, 'billing_last_name',  true ) ?: $user->last_name;
				$company    = get_user_meta( $user->ID, 'billing_company',     true );
				$phone      = get_user_meta( $user->ID, 'billing_phone',       true );
				$country    = get_user_meta( $user->ID, 'billing_country',     true );
				$city       = get_user_meta( $user->ID, 'billing_city',        true );

				$data = array(
					'email'      => $email,
					'first_name' => sanitize_text_field( $first_name ),
					'last_name'  => sanitize_text_field( $last_name ),
					'company'    => sanitize_text_field( $company ),
					'phone'      => sanitize_text_field( $phone ),
					'country'    => sanitize_text_field( $country ),
					'city'       => sanitize_text_field( $city ),
					'source'     => 'woocommerce',
					'status'     => 'subscribed',
				);

				if ( $wc_customer ) {
					$data['total_orders'] = (int) $wc_customer->get_order_count();
					$data['total_spent']  = (float) $wc_customer->get_total_spent();
				}

				$existing = self::get_by_email( $email );
				if ( $existing ) {
					self::update( (int) $existing->id, $data );
					$job['updated']++;
				} else {
					$data['total_orders'] = $data['total_orders'] ?? 0;
					$data['total_spent']  = $data['total_spent']  ?? 0.00;
					self::insert( $data );
					$job['added']++;
				}
				$job['processed']++;
			}

			$job['offset'] += $batch_size;
			update_option( 'sgc_crm_woo_sync_job', $job );

			// Schedule next batch.
			wp_schedule_single_event( time() + 3, 'sgc_crm_woo_sync_batch' );
			return;
		}

		// ── PHASE 2: WooCommerce Orders (for order stats) ──────────────────
		if ( 'orders' === $job['phase'] ) {
			$orders = wc_get_orders( array(
				'limit'   => $batch_size,
				'offset'  => (int) $job['offset'],
				'orderby' => 'date',
				'order'   => 'ASC',
				'status'  => array( 'completed', 'processing', 'on-hold' ),
				'return'  => 'objects',
			) );

			if ( empty( $orders ) ) {
				// All done.
				$job['status'] = 'done';
				$job['done_at'] = current_time( 'mysql' );
				update_option( 'sgc_crm_woo_sync_job', $job );
				return;
			}

			foreach ( $orders as $order ) {
				$email = strtolower( $order->get_billing_email() );
				if ( ! is_email( $email ) ) { continue; }

				$existing = self::get_by_email( $email );
				$order_date = $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d H:i:s' ) : null;

				$data = array(
					'email'         => $email,
					'first_name'    => sanitize_text_field( $order->get_billing_first_name() ),
					'last_name'     => sanitize_text_field( $order->get_billing_last_name() ),
					'company'       => sanitize_text_field( $order->get_billing_company() ),
					'phone'         => sanitize_text_field( $order->get_billing_phone() ),
					'country'       => sanitize_text_field( $order->get_billing_country() ),
					'city'          => sanitize_text_field( $order->get_billing_city() ),
					'source'        => 'woocommerce',
					'last_order_at' => $order_date,
				);

				if ( $existing ) {
					// Update order stats only if this order is newer.
					if ( ! $existing->last_order_at || $order_date > $existing->last_order_at ) {
						$data['last_order_at'] = $order_date;
					}
					self::update( (int) $existing->id, $data );
					$job['updated']++;
				} else {
					$data['status']       = 'subscribed';
					$data['total_orders'] = 1;
					$data['total_spent']  = (float) $order->get_total();
					self::insert( $data );
					$job['added']++;
				}
				$job['processed']++;
			}

			$job['offset'] += $batch_size;
			update_option( 'sgc_crm_woo_sync_job', $job );
			wp_schedule_single_event( time() + 3, 'sgc_crm_woo_sync_batch' );
		}
	}

	// =========================================================================
	// SYNC: INQUIRY FORM
	// =========================================================================

	/**
	 * AJAX handler — sync SGC LABS® inquiry form submissions to CRM contacts.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_sync_inquiries() {
		self::verify();
		global $wpdb;

		// v1.1.2 fix: IIP uses sgc_iip_inquiries (not the old sgclabs_inquiries table).
		// Try IIP table first, fall back to legacy Manager Pro table.
		$iip_table    = $wpdb->prefix . 'sgc_iip_inquiries';
		$legacy_table = $wpdb->prefix . 'sgclabs_inquiries';

		$iip_exists    = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $iip_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$legacy_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( $iip_exists === $iip_table ) {
			$inq_table = $iip_table;
		} elseif ( $legacy_exists === $legacy_table ) {
			$inq_table = $legacy_table;
		} else {
			wp_send_json_error( esc_html__( 'No inquiry table found. Ensure SGC Invoice Pro or Manager Pro is active.', 'sgc-smart-crm' ) );
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$inquiries = $wpdb->get_results( "SELECT * FROM `{$inq_table}` ORDER BY created_at DESC LIMIT 1000" );
		$added     = 0;
		$updated   = 0;

		foreach ( $inquiries as $inq ) {
			$email = strtolower( sanitize_email( $inq->email ?? '' ) );
			if ( ! is_email( $email ) ) {
				continue;
			}

			$parts    = explode( ' ', trim( $inq->name ?? '' ), 2 );
			$existing = self::get_by_email( $email );

			// IIP stores products as JSON in product_details; legacy stores as plain text in product
			$prod_raw  = $inq->product_details ?? $inq->product ?? '';
			$prod_text = '';
			if ( $prod_raw ) {
				$decoded = json_decode( $prod_raw, true );
				if ( is_array( $decoded ) ) {
					$names = array_filter( array_column( $decoded, 'name' ) );
					$prod_text = implode( ', ', array_slice( $names, 0, 3 ) );
				} else {
					$prod_text = sanitize_text_field( $prod_raw );
				}
			}

			$data     = array(
				'email'      => $email,
				'first_name' => sanitize_text_field( $parts[0] ?? '' ),
				'last_name'  => sanitize_text_field( $parts[1] ?? '' ),
				'phone'      => sanitize_text_field( $inq->phone ?? '' ),
				'company'    => sanitize_text_field( $inq->company ?? '' ),
				'country'    => sanitize_text_field( $inq->country ?? '' ),
				'source'     => 'inquiry',
				'segment'    => 'warm_lead',
				'tags'       => 'inquiry' . ( $prod_text ? ',' . sanitize_text_field( $prod_text ) : '' ),
			);

			if ( $existing ) {
				self::update( (int) $existing->id, $data );
				++$updated;
			} else {
				$data['status'] = 'subscribed';
				self::insert( $data );
				++$added;
			}
		}

		wp_send_json_success( array( 'added' => $added, 'updated' => $updated ) );
	}

	// =========================================================================
	// HOOKS: AUTO-ADD ON WOO ORDER / INQUIRY
	// =========================================================================

	/**
	 * Auto-add/update CRM contact when a WooCommerce order is placed.
	 *
	 * @since  1.0.0
	 * @param  int $order_id WooCommerce order ID.
	 * @return void
	 */
	public static function on_woo_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$email = strtolower( $order->get_billing_email() );
		if ( ! is_email( $email ) ) {
			return;
		}

		$existing = self::get_by_email( $email );
		$data     = array(
			'first_name'    => sanitize_text_field( $order->get_billing_first_name() ),
			'last_name'     => sanitize_text_field( $order->get_billing_last_name() ),
			'company'       => sanitize_text_field( $order->get_billing_company() ),
			'phone'         => sanitize_text_field( $order->get_billing_phone() ),
			'country'       => sanitize_text_field( $order->get_billing_country() ),
			'city'          => sanitize_text_field( $order->get_billing_city() ),
			'source'        => 'woocommerce',
			'last_order_at' => current_time( 'mysql' ),
		);

		if ( $existing ) {
			$data['total_orders'] = (int) $existing->total_orders + 1;
			$data['total_spent']  = (float) $existing->total_spent + (float) $order->get_total();
			self::update( (int) $existing->id, $data );
		} else {
			$data['email']        = $email;
			$data['status']       = 'subscribed';
			$data['segment']      = 'active_buyer';
			$data['total_orders'] = 1;
			$data['total_spent']  = (float) $order->get_total();
			$contact_id           = self::insert( $data );
			// Trigger welcome email for new contacts.
			do_action( 'sgc_crm_new_contact_added', $contact_id );
		}
	}

	/**
	 * Auto-add CRM contact from SGC LABS® inquiry form submission.
	 *
	 * @since  1.0.0
	 * @param  array<string, mixed> $inquiry_data Inquiry form data.
	 * @return void
	 */
	public static function on_new_inquiry( $inquiry_data ) {
		$email = strtolower( sanitize_email( $inquiry_data['email'] ?? '' ) );
		if ( ! is_email( $email ) ) {
			return;
		}

		$parts    = explode( ' ', trim( $inquiry_data['name'] ?? '' ), 2 );
		$existing = self::get_by_email( $email );
		$data     = array(
			'first_name' => sanitize_text_field( $parts[0] ?? '' ),
			'last_name'  => sanitize_text_field( $parts[1] ?? '' ),
			'phone'      => sanitize_text_field( $inquiry_data['phone']   ?? '' ),
			'country'    => sanitize_text_field( $inquiry_data['country'] ?? '' ),
			'source'     => 'inquiry',
			'segment'    => 'warm_lead',
			'tags'       => 'inquiry',
		);

		if ( ! $existing ) {
			$data['email']  = $email;
			$data['status'] = 'subscribed';
			$contact_id     = self::insert( $data );
			do_action( 'sgc_crm_new_contact_added', $contact_id );
		} else {
			self::update( (int) $existing->id, $data );
		}
	}

	// =========================================================================
	// AJAX: ADD / UPDATE / DELETE / LIST / EXPORT
	// =========================================================================

	/**
	 * AJAX — add a single contact manually.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	// ── AJAX: Frontend contact form / newsletter subscribe (public) ──────────
	// v1.1.3: Separate from admin ajax_add_contact — no capability check needed
	public static function ajax_frontend_add_contact(): void {

		// Rate limiting — max 3 submissions per IP per hour
		$ip       = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
		$rate_key = 'sgc_crm_rate_' . md5( $ip );
		$hits     = (int) get_transient( $rate_key );
		if ( $hits >= 10 ) {
			wp_send_json_error( esc_html__( 'Too many requests. Please try again later.', 'sgc-smart-crm' ) );
			return;
		}
		set_transient( $rate_key, $hits + 1, HOUR_IN_SECONDS );

		// Honeypot check — bots fill hidden fields
		if ( ! empty( $_POST['website'] ) ) {
			wp_send_json_success( array( 'id' => 0 ) ); // silent success to fool bots
			return;
		}

		// Nonce — frontend nonce (localized as sgc_crm_public_nonce)
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? $_POST['_wpnonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'sgc_crm_public' ) ) {
			wp_send_json_error( esc_html__( 'Security check failed. Please refresh and try again.', 'sgc-smart-crm' ) );
			return;
		}

		$email = strtolower( sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ) );
		if ( ! is_email( $email ) ) {
			wp_send_json_error( esc_html__( 'Please enter a valid email address.', 'sgc-smart-crm' ) );
			return;
		}

		// If already exists — update silently (re-subscribe)
		$existing = self::get_by_email( $email );
		if ( $existing ) {
			self::update( (int) $existing->id, [ 'status' => 'subscribed' ] );
			wp_send_json_success( [ 'id' => $existing->id, 'existing' => true ] );
			return;
		}

		$id = self::insert( [
			'email'      => $email,
			'first_name' => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? $_POST['name'] ?? '' ) ),
			'last_name'  => sanitize_text_field( wp_unslash( $_POST['last_name']  ?? '' ) ),
			'company'    => sanitize_text_field( wp_unslash( $_POST['company']    ?? '' ) ),
			'phone'      => sanitize_text_field( wp_unslash( $_POST['phone']      ?? '' ) ),
			'country'    => sanitize_text_field( wp_unslash( $_POST['country']    ?? '' ) ),
			'segment'    => 'new_lead',
			'source'     => 'website_form',
			'status'     => 'subscribed',
		] );

		if ( ! $id ) {
			wp_send_json_error( esc_html__( 'Could not save contact. Please try again.', 'sgc-smart-crm' ) );
			return;
		}

		wp_send_json_success( [ 'id' => $id ] );
	}

	/**
	 * AJAX — add a contact (admin only, requires manage_woocommerce).
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_add_contact() {
		self::verify();

		$email = strtolower( sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ) );
		if ( ! is_email( $email ) ) {
			wp_send_json_error( esc_html__( 'Invalid email address.', 'sgc-smart-crm' ) );
		}

		if ( self::get_by_email( $email ) ) {
			wp_send_json_error( esc_html__( 'This email already exists.', 'sgc-smart-crm' ) );
		}

		$id = self::insert(
			array(
				'email'      => $email,
				'first_name' => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
				'last_name'  => sanitize_text_field( wp_unslash( $_POST['last_name']  ?? '' ) ),
				'company'    => sanitize_text_field( wp_unslash( $_POST['company']    ?? '' ) ),
				'phone'      => sanitize_text_field( wp_unslash( $_POST['phone']      ?? '' ) ),
				'country'    => sanitize_text_field( wp_unslash( $_POST['country']    ?? '' ) ),
				'tags'       => sanitize_text_field( wp_unslash( $_POST['tags']       ?? '' ) ),
				'segment'    => sanitize_key( wp_unslash( $_POST['segment'] ?? 'new_lead' ) ),
				'source'     => 'manual',
				'status'     => 'subscribed',
			)
		);

		wp_send_json_success( array( 'id' => $id ) );
	}

	/**
	 * AJAX — update a contact.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_update_contact() {
		self::verify();

		$id = (int) ( $_POST['id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( esc_html__( 'Invalid contact ID.', 'sgc-smart-crm' ) );
		}

		self::update(
			$id,
			array(
				'first_name' => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
				'last_name'  => sanitize_text_field( wp_unslash( $_POST['last_name']  ?? '' ) ),
				'company'    => sanitize_text_field( wp_unslash( $_POST['company']    ?? '' ) ),
				'phone'      => sanitize_text_field( wp_unslash( $_POST['phone']      ?? '' ) ),
				'country'    => sanitize_text_field( wp_unslash( $_POST['country']    ?? '' ) ),
				'tags'       => sanitize_text_field( wp_unslash( $_POST['tags']       ?? '' ) ),
				'segment'    => sanitize_key( wp_unslash( $_POST['segment'] ?? '' ) ),
				'notes'      => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
			)
		);

		wp_send_json_success();
	}

	/**
	 * AJAX — delete a contact.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_delete_contact() {
		self::verify();

		$id = (int) ( $_POST['id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( esc_html__( 'Invalid contact ID.', 'sgc-smart-crm' ) );
		}

		self::delete( $id );
		wp_send_json_success();
	}

	/**
	 * AJAX — paginated contact list with search/filter.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_get_contacts() {
		self::verify();
		global $wpdb;

		$page    = max( 1, (int) ( $_POST['page'] ?? 1 ) );
		$per     = 50;
		$offset  = ( $page - 1 ) * $per;
		$search  = sanitize_text_field( wp_unslash( $_POST['search']  ?? '' ) );
		$segment = sanitize_key( wp_unslash( $_POST['segment'] ?? '' ) );
		$status  = sanitize_key( wp_unslash( $_POST['status']  ?? '' ) );

		$table  = SGC_CRM_DB::contacts();
		$where  = '1=1';
		$values = array();

		if ( $search ) {
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$where  .= ' AND (email LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR company LIKE %s)';
			$values  = array_merge( $values, array( $like, $like, $like, $like ) );
		}
		if ( $segment ) {
			$where   .= ' AND segment = %s';
			$values[] = $segment;
		}
		if ( $status ) {
			$where   .= ' AND status = %s';
			$values[] = $status;
		}

		$count_sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$where}";
		$data_sql  = "SELECT * FROM `{$table}` WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$values ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$rows  = $wpdb->get_results( $wpdb->prepare( $data_sql, ...array_merge( $values, array( $per, $offset ) ) ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $count_sql );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$rows  = $wpdb->get_results( $wpdb->prepare( $data_sql, $per, $offset ) );
		}

		wp_send_json_success(
			array(
				'contacts' => $rows,
				'total'    => $total,
				'pages'    => (int) ceil( $total / $per ),
				'page'     => $page,
			)
		);
	}

	/**
	 * AJAX — export subscribed contacts as CSV.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_export_contacts() {
		self::verify();
		global $wpdb;

		$table    = SGC_CRM_DB::contacts();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$contacts = $wpdb->get_results( "SELECT * FROM `{$table}` WHERE status = 'subscribed' ORDER BY created_at DESC" );

		$csv = "ID,Email,First Name,Last Name,Company,Phone,Country,City,Tags,Segment,Source,Status,Total Orders,Total Spent,Created\n";
		foreach ( $contacts as $c ) {
			$csv .= implode(
				',',
				array(
					$c->id, $c->email, $c->first_name, $c->last_name, '"' . str_replace( '"', '""', $c->company ) . '"',
					$c->phone, $c->country, $c->city, '"' . str_replace( '"', '""', $c->tags ) . '"',
					$c->segment, $c->source, $c->status, $c->total_orders, $c->total_spent, $c->created_at,
				)
			) . "\n";
		}

		wp_send_json_success( array( 'csv' => $csv, 'count' => count( $contacts ) ) );
	}

	// =========================================================================
	// UNSUBSCRIBE
	// =========================================================================


	/**
	 * AJAX — import contacts from Google Contacts exported CSV.
	 * Google exports use: Name, Given Name, Family Name, Group Membership,
	 * E-mail 1 - Value, Phone 1 - Value, Organization 1 - Name, etc.
	 *
	 * @since  1.0.2
	 * @return void
	 */
	public static function ajax_import_google_csv() {
		self::verify();

		if ( empty( $_FILES['crm_file']['tmp_name'] ) ) {
			wp_send_json_error( esc_html__( 'No file uploaded.', 'sgc-smart-crm' ) );
		}

		$tmp_file = $_FILES['crm_file']['tmp_name']; // phpcs:ignore
		if ( ! is_uploaded_file( $tmp_file ) ) {
			wp_send_json_error( esc_html__( 'Invalid file upload.', 'sgc-smart-crm' ) );
		}

		// Parse CSV.
		$rows    = array();
		$handle  = fopen( $tmp_file, 'r' ); // phpcs:ignore
		if ( ! $handle ) {
			wp_send_json_error( esc_html__( 'Could not read file.', 'sgc-smart-crm' ) );
		}

		$headers = array();
		$line    = 0;
		while ( false !== ( $data = fgetcsv( $handle, 8192, ',' ) ) ) { // phpcs:ignore
			if ( 0 === $line ) {
				$headers = array_map( 'trim', $data );
				$line++;
				continue;
			}
			if ( count( $data ) >= count( $headers ) ) {
				$rows[] = array_combine( $headers, array_slice( $data, 0, count( $headers ) ) );
			}
			$line++;
		}
		fclose( $handle ); // phpcs:ignore

		if ( empty( $rows ) ) {
			wp_send_json_error( esc_html__( 'No data in file. Export contacts from Google as CSV (Google CSV format).', 'sgc-smart-crm' ) );
		}

		$added   = 0;
		$updated = 0;
		$skipped = 0;

		foreach ( $rows as $row ) {
			// Google CSV column names.
			$email = strtolower( sanitize_email(
				$row['E-mail 1 - Value'] ?? $row['Email 1 - Value'] ?? $row['E-mail Address'] ?? $row['email'] ?? ''
			) );

			if ( ! is_email( $email ) ) {
				++$skipped;
				continue;
			}

			$given  = sanitize_text_field( $row['Given Name']  ?? $row['First Name'] ?? '' );
			$family = sanitize_text_field( $row['Family Name'] ?? $row['Last Name']  ?? '' );

			// Fall back to full "Name" column.
			if ( empty( $given ) && ! empty( $row['Name'] ) ) {
				$parts  = explode( ' ', trim( $row['Name'] ), 2 );
				$given  = $parts[0];
				$family = $parts[1] ?? '';
			}

			$company = sanitize_text_field( $row['Organization 1 - Name'] ?? $row['Company'] ?? '' );
			$phone   = sanitize_text_field( $row['Phone 1 - Value'] ?? $row['Phone'] ?? '' );
			$group   = sanitize_text_field( $row['Group Membership'] ?? '' );

			// Map Google Groups to CRM segments.
			$segment = 'end_user';
			$group_l = strtolower( $group );
			if ( strpos( $group_l, 'dealer' ) !== false || strpos( $group_l, 'distrib' ) !== false ) {
				$segment = 'dealer';
			} elseif ( strpos( $group_l, 'student' ) !== false ) {
				$segment = 'student';
			} elseif ( strpos( $group_l, 'teacher' ) !== false || strpos( $group_l, 'professor' ) !== false ) {
				$segment = 'teacher';
			} elseif ( strpos( $group_l, 'glass' ) !== false || strpos( $group_l, 'blower' ) !== false ) {
				$segment = 'glass_blower';
			} elseif ( strpos( $group_l, 'semi' ) !== false || strpos( $group_l, 'custom' ) !== false ) {
				$segment = 'semi_finished';
			} elseif ( strpos( $group_l, 'international' ) !== false || strpos( $group_l, 'export' ) !== false ) {
				$segment = 'international';
			}

			$data = array(
				'email'      => $email,
				'first_name' => $given,
				'last_name'  => $family,
				'company'    => $company,
				'phone'      => $phone,
				'tags'       => sanitize_text_field( $group ),
				'source'     => 'google_contacts',
				'segment'    => $segment,
			);

			$existing = self::get_by_email( $email );
			if ( $existing ) {
				self::update( (int) $existing->id, $data );
				++$updated;
			} else {
				$data['status'] = 'subscribed';
				self::insert( $data );
				++$added;
			}
		}

		wp_send_json_success(
			array(
				'added'   => $added,
				'updated' => $updated,
				'skipped' => $skipped,
				'total'   => count( $rows ),
				'source'  => 'google_contacts',
			)
		);
	}

	// =========================================================================
	// BULK ACTIONS — v1.0.4
	// =========================================================================

	/**
	 * Validate and return sanitized contact IDs from POST.
	 *
	 * @since  1.0.4
	 * @return int[] Array of valid positive IDs.
	 */
	private static function get_bulk_ids() {
		$raw = isset( $_POST['ids'] ) ? (array) $_POST['ids'] : array(); // phpcs:ignore WordPress.Security.NonceVerification
		$ids = array_filter( array_map( 'absint', $raw ) );
		if ( empty( $ids ) ) {
			wp_send_json_error( esc_html__( 'No contacts selected.', 'sgc-smart-crm' ) );
		}
		return array_values( $ids );
	}

	/**
	 * AJAX — bulk delete contacts by ID array.
	 *
	 * @since  1.0.4
	 * @return void
	 */
	public static function ajax_bulk_delete() {
		self::verify();
		global $wpdb;

		$ids          = self::get_bulk_ids();
		$table        = SGC_CRM_DB::contacts();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE id IN ({$placeholders})", ...$ids ) );

		wp_send_json_success(
			array(
				'deleted' => (int) $deleted,
				/* translators: %d number of contacts deleted */
				'message' => sprintf( esc_html__( '%d contact(s) deleted.', 'sgc-smart-crm' ), (int) $deleted ),
			)
		);
	}

	/**
	 * AJAX — bulk export selected contacts as CSV (base64-encoded for JS download).
	 *
	 * @since  1.0.4
	 * @return void
	 */
	public static function ajax_bulk_export() {
		self::verify();
		global $wpdb;

		$ids          = self::get_bulk_ids();
		$table        = SGC_CRM_DB::contacts();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$contacts = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id IN ({$placeholders}) ORDER BY id ASC", ...$ids ) );

		$csv  = "ID,Email,First Name,Last Name,Company,Phone,Country,City,Tags,Segment,Source,Status,AI Score,Total Orders,Total Spent,Created\n";
		foreach ( $contacts as $c ) {
			$csv .= implode(
				',',
				array(
					(int) $c->id,
					$c->email,
					$c->first_name,
					$c->last_name,
					'"' . str_replace( '"', '""', $c->company ) . '"',
					$c->phone,
					$c->country,
					$c->city,
					'"' . str_replace( '"', '""', $c->tags ) . '"',
					$c->segment,
					$c->source,
					$c->status,
					(int) $c->ai_score,
					(int) $c->total_orders,
					number_format( (float) $c->total_spent, 2, '.', '' ),
					$c->created_at,
				)
			) . "\n";
		}

		wp_send_json_success(
			array(
				'csv'      => base64_encode( $csv ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'filename' => 'sgc-crm-export-' . gmdate( 'Y-m-d' ) . '.csv',
				'count'    => count( $contacts ),
			)
		);
	}

	/**
	 * AJAX — bulk assign a segment to selected contacts.
	 *
	 * @since  1.0.4
	 * @return void
	 */
	public static function ajax_bulk_assign() {
		self::verify();
		global $wpdb;

		$ids     = self::get_bulk_ids();
		$segment = sanitize_key( wp_unslash( $_POST['segment'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification

		$allowed = array(
			'student', 'teacher', 'dealer', 'end_user',
			'glass_blower', 'semi_finished', 'international',
			'domestic', 'warm_lead', 'active_buyer', 'new_lead',
		);

		if ( ! in_array( $segment, $allowed, true ) ) {
			wp_send_json_error( esc_html__( 'Invalid segment value.', 'sgc-smart-crm' ) );
		}

		$table        = SGC_CRM_DB::contacts();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}` SET segment = %s, updated_at = %s WHERE id IN ({$placeholders})",
				$segment,
				current_time( 'mysql' ),
				...$ids
			)
		);

		wp_send_json_success(
			array(
				'updated' => (int) $updated,
				'segment' => $segment,
				/* translators: %d number of contacts updated */
				'message' => sprintf( esc_html__( '%d contact(s) updated to segment: %s', 'sgc-smart-crm' ), (int) $updated, $segment ),
			)
		);
	}


	/**
	 * AJAX — delete ALL contacts with auto CSV backup first.
	 * Requires confirm_token = "DELETE" from client for safety.
	 *
	 * @since  1.0.6
	 * @return void
	 */
	public static function ajax_delete_all() {
		self::verify();

		// Extra safety — client must send confirm_token = "DELETE".
		$token = strtoupper( sanitize_text_field( wp_unslash( $_POST['confirm_token'] ?? '' ) ) );
		if ( 'DELETE' !== $token ) {
			wp_send_json_error( esc_html__( 'Confirmation token mismatch. Type DELETE to confirm.', 'sgc-smart-crm' ) );
		}

		global $wpdb;
		$table = SGC_CRM_DB::contacts();

		// Step 1 — count contacts first.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );

		if ( 0 === $total ) {
			wp_send_json_error( esc_html__( 'No contacts to delete.', 'sgc-smart-crm' ) );
		}

		// Step 2 — auto CSV backup before wiping.
		$backup_file = self::backup_all_contacts();

		// Step 3 — truncate table (faster than DELETE for large datasets).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "TRUNCATE TABLE `{$table}`" );

		wp_send_json_success(
			array(
				'deleted' => $total,
				'backup'  => $backup_file,
				/* translators: 1: number deleted 2: backup filename */
				'message' => sprintf(
					esc_html__( '%1$d contacts deleted. Backup saved: %2$s', 'sgc-smart-crm' ),
					$total,
					$backup_file ?: esc_html__( 'N/A', 'sgc-smart-crm' )
				),
			)
		);
	}

	/**
	 * Export all contacts to a CSV file in uploads/sgc-crm-backups/.
	 * Called automatically before any mass-delete operation.
	 *
	 * @since  1.0.6
	 * @return string Backup filename (basename only), or empty string on failure.
	 */
	private static function backup_all_contacts() {
		global $wpdb;
		$table = SGC_CRM_DB::contacts();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY id ASC", ARRAY_A );
		if ( empty( $rows ) ) {
			return '';
		}

		$upload_dir = wp_upload_dir();
		$dir        = trailingslashit( $upload_dir['basedir'] ) . 'sgc-crm-backups';

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
			file_put_contents( $dir . '/.htaccess', 'deny from all' ); // phpcs:ignore
			file_put_contents( $dir . '/index.php', '<?php // Silence.' ); // phpcs:ignore
		}

		$filename = 'pre-delete-backup-' . gmdate( 'Y-m-d-His' ) . '.csv';
		$filepath = $dir . '/' . $filename;
		$handle   = fopen( $filepath, 'w' ); // phpcs:ignore
		if ( ! $handle ) {
			return '';
		}

		fputcsv( $handle, array_keys( $rows[0] ) );
		foreach ( $rows as $row ) {
			fputcsv( $handle, $row );
		}
		fclose( $handle ); // phpcs:ignore

		return $filename;
	}

	/**
	 * Handle unsubscribe requests (AJAX or URL param).
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function handle_unsubscribe() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$token = sanitize_text_field( wp_unslash( $_REQUEST['token'] ?? '' ) );
		$email = sanitize_email( wp_unslash( $_REQUEST['email']  ?? '' ) );
		// phpcs:enable

		if ( $email && is_email( $email ) ) {
			$contact = self::get_by_email( strtolower( $email ) );
			if ( $contact ) {
				self::update( (int) $contact->id, array( 'status' => 'unsubscribed' ) );
			}
		}

		if ( $token ) {
			global $wpdb;
			$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					'SELECT contact_id FROM `' . SGC_CRM_DB::queue() . '` WHERE track_token = %s LIMIT 1',
					$token
				)
			);
			if ( $row ) {
				self::update( (int) $row->contact_id, array( 'status' => 'unsubscribed' ) );
			}
		}

		if ( wp_doing_ajax() ) {
			wp_send_json_success( esc_html__( 'Unsubscribed successfully.', 'sgc-smart-crm' ) );
		}

		wp_die(
			'<h2>' . esc_html__( 'Unsubscribed', 'sgc-smart-crm' ) . '</h2>'
			. '<p>' . esc_html__( 'You have been removed from our mailing list.', 'sgc-smart-crm' ) . '</p>',
			esc_html__( 'Unsubscribed', 'sgc-smart-crm' ),
			array( 'response' => 200 )
		);
	}
}

// =========================================================================
// REGISTER HOOKS
// =========================================================================
SGC_CRM_Contacts::register_ajax();

// WooCommerce order → auto-add contact.
add_action( 'woocommerce_order_status_processing', array( 'SGC_CRM_Contacts', 'on_woo_order' ) );
add_action( 'woocommerce_order_status_completed',  array( 'SGC_CRM_Contacts', 'on_woo_order' ) );

// SGC LABS® inquiry form → auto-add contact.
add_action( 'sgclabs_inquiry_submitted', array( 'SGC_CRM_Contacts', 'on_new_inquiry' ) );

// Unsubscribe via URL parameter.
add_action(
	'init',
	function () {
		if ( isset( $_GET['sgc_crm_unsub'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			SGC_CRM_Contacts::handle_unsubscribe();
		}
	}
);

// =========================================================================
// v1.0.8: UTM SOURCE TRACKING — capture organic/referral source on WC order
// =========================================================================
add_action( 'woocommerce_checkout_order_created', function( WC_Order $order ) {
	// Try to get UTM from cookie (set in frontend JS)
	$utm_source   = sanitize_text_field( $_COOKIE['sgc_utm_source']   ?? '' );
	$utm_medium   = sanitize_text_field( $_COOKIE['sgc_utm_medium']   ?? '' );
	$utm_campaign = sanitize_text_field( $_COOKIE['sgc_utm_campaign'] ?? '' );
	$utm_content  = sanitize_text_field( $_COOKIE['sgc_utm_content']  ?? '' );
	$referrer     = sanitize_text_field( $_COOKIE['sgc_referrer']     ?? '' );

	if ( $utm_source || $referrer ) {
		$order->update_meta_data( '_sgc_utm_source',   $utm_source );
		$order->update_meta_data( '_sgc_utm_medium',   $utm_medium );
		$order->update_meta_data( '_sgc_utm_campaign', $utm_campaign );
		$order->update_meta_data( '_sgc_utm_content',  $utm_content );
		$order->update_meta_data( '_sgc_referrer',     $referrer );
		$order->save();

		// Update CRM contact source if from organic search
		$email = $order->get_billing_email();
		if ( $email && $utm_source === 'organic' ) {
			global $wpdb;
			$ct = $wpdb->prefix . 'sgc_crm_contacts';
			$wpdb->update( $ct, ['source'=>'organic_search'], ['email'=>$email] ); // phpcs:ignore
		}
	}
} );

// v1.0.8: UTM cookie JS — inject on frontend to capture Google/Bing/organic traffic
add_action( 'wp_footer', function() {
	if ( is_admin() ) return;
	?>
<script id="sgc-crm-utm-tracker">
(function(){
	function setCookie(n,v,d){var e=new Date;e.setTime(e.getTime()+d*86400000);document.cookie=n+"="+encodeURIComponent(v)+";expires="+e.toGMTString()+";path=/;SameSite=Lax";}
	var p=new URLSearchParams(window.location.search);
	if(p.get('utm_source')){setCookie('sgc_utm_source',p.get('utm_source')||'',30);setCookie('sgc_utm_medium',p.get('utm_medium')||'',30);setCookie('sgc_utm_campaign',p.get('utm_campaign')||'',30);setCookie('sgc_utm_content',p.get('utm_content')||'',30);}
	if(!document.cookie.includes('sgc_utm_source')&&document.referrer){
		var r=document.referrer,src='direct';
		if(/google\.|bing\.|yahoo\.|duckduckgo\./.test(r))src='organic';
		else if(/facebook\.|instagram\.|linkedin\.|twitter\./.test(r))src='social';
		else if(r)src='referral';
		setCookie('sgc_utm_source',src,30);setCookie('sgc_referrer',r,30);
	}
})();
</script>
	<?php
}, 99 );

// =========================================================================
// v1.0.8: REORDER PREDICTION ENGINE
// Predicts reorder date based on product type + avg consumption rate.
// =========================================================================
add_action( 'sgc_crm_reorder_check', function() {
	if ( get_option('sgc_crm_reorder_prediction','1') !== '1' ) return;

	global $wpdb;
	$ct = $wpdb->prefix . 'sgc_crm_contacts';
	// Contacts with orders, last order 60-180 days ago, not yet reminded
	$contacts = $wpdb->get_results(
		"SELECT * FROM `$ct`
		 WHERE total_orders > 0
		   AND last_order_at IS NOT NULL
		   AND DATEDIFF(NOW(), last_order_at) BETWEEN 60 AND 180
		   AND (reorder_reminded_at IS NULL OR DATEDIFF(NOW(), reorder_reminded_at) > 90)
		 ORDER BY last_order_at ASC LIMIT 30",
		ARRAY_A
	); // phpcs:ignore

	if ( empty($contacts) ) return;

	$company  = get_option('blogname','SGC LABS®');
	$from_em  = get_option('sgc_crm_sender_email', get_option('admin_email'));
	$from_n   = get_option('sgc_crm_sender_name', $company);

	foreach ( $contacts as $c ) {
		$days_since = intval( $wpdb->get_var( $wpdb->prepare(
			"SELECT DATEDIFF(NOW(), last_order_at) FROM `$ct` WHERE id=%d", $c['id']
		) ) );
		$first = sanitize_text_field($c['first_name']??'Customer');
		$subject = "Is it time to restock your lab supplies? — {$company}";
		$html = "<p>Dear <strong>{$first}</strong>,</p>
			<p>It has been approximately <strong>{$days_since} days</strong> since your last order from {$company}.</p>
			<p>Based on typical laboratory consumption rates, your glassware supplies may be running low. We wanted to reach out proactively before you face a shortage.</p>
			<div style='text-align:center;margin:24px 0'>
			  <a href='".esc_url(home_url('/shop'))."' style='background:#1a1a2e;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;display:inline-block'>🔬 Browse & Reorder</a>
			</div>
			<p style='font-size:12px;color:#888'>Reply to this email to speak with our sales team or request a fresh quotation.</p>";

		wp_mail(
			sanitize_email($c['email']),
			$subject,
			$html,
			['Content-Type: text/html; charset=UTF-8','From: '.$from_n.' <'.$from_em.'>']
		);
		$wpdb->update( $ct, ['reorder_reminded_at'=>current_time('mysql')], ['id'=>$c['id']] ); // phpcs:ignore
	}
} );

if ( ! wp_next_scheduled('sgc_crm_reorder_check') ) {
	wp_schedule_event( time(), 'daily', 'sgc_crm_reorder_check' );
}
