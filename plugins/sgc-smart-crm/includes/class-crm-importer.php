<?php
/**
 * SGC Smart CRM Pro — Bulk Contact Importer v1.0.3
 *
 * Handles large-scale contact imports (13,000+ contacts) from:
 *  - Google Contacts CSV (exact format from contacts.google.com)
 *  - Any generic CSV / Excel
 *
 * Architecture:
 *  - File uploaded → stored temp → background WP-Cron batches (500/batch)
 *  - DB-persisted progress → resume-safe if page closed
 *  - AJAX polling → live real-time progress bar
 *  - Smart duplicate handling → update existing, insert new
 *  - Auto AI segmentation after import completes
 *  - Phone number normalization (scientific notation fix)
 *  - Multi-email support (up to 4 email columns)
 *  - Labels → CRM segments auto-mapping
 *
 * @package SGC_Smart_CRM
 * @since   1.0.3
 * @version   1.0.4 | Build: 20260516-0630
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SGC_CRM_Importer
 *
 * @since 1.0.3
 * @version   1.0.4 | Build: 20260516-0630
 */
class SGC_CRM_Importer {

	/**
	 * Batch size per cron run.
	 *
	 * @since 1.0.3
	 * @var   int
	 */
	const BATCH_SIZE = 500;

	/**
	 * Option key for active import job.
	 *
	 * @since 1.0.3
	 * @var   string
	 */
	const JOB_OPTION = 'sgc_crm_import_job';

	/**
	 * Cron hook name.
	 *
	 * @since 1.0.3
	 * @var   string
	 */
	const CRON_HOOK = 'sgc_crm_run_import_batch';

	// =========================================================================
	// REGISTER
	// =========================================================================

	/**
	 * Register AJAX handlers and cron hook.
	 *
	 * @since  1.0.3
	 * @return void
	 */
	public static function register() {
		// AJAX — admin only.
		add_action( 'wp_ajax_sgc_crm_upload_import',    array( __CLASS__, 'ajax_upload' ) );
		add_action( 'wp_ajax_sgc_crm_import_progress',  array( __CLASS__, 'ajax_progress' ) );
		add_action( 'wp_ajax_sgc_crm_cancel_import',    array( __CLASS__, 'ajax_cancel' ) );
		add_action( 'wp_ajax_sgc_crm_import_now',       array( __CLASS__, 'ajax_import_now' ) );

		// Cron batch processor.
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_batch' ) );
	}

	// =========================================================================
	// AJAX: UPLOAD FILE → START JOB
	// =========================================================================

	/**
	 * AJAX — receive uploaded file, parse it, store job in DB, kick off cron.
	 *
	 * @since  1.0.3
	 * @return void
	 */
	public static function ajax_upload() {
		check_ajax_referer( 'sgc_crm_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions.', 'sgc-smart-crm' ), 403 );
		}

		if ( empty( $_FILES['crm_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['crm_file']['tmp_name'] ) ) {
			wp_send_json_error( esc_html__( 'No file received.', 'sgc-smart-crm' ) );
		}

		$filename = sanitize_file_name( $_FILES['crm_file']['name'] ?? 'import.csv' );
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, array( 'csv', 'xlsx', 'xls' ), true ) ) {
			wp_send_json_error( esc_html__( 'Only CSV and Excel (.xlsx/.xls) files supported.', 'sgc-smart-crm' ) );
		}

		$tmp = $_FILES['crm_file']['tmp_name'];

		// v1.1.5: For large files (22,000+ contacts), store file on disk instead of
		// all rows in wp_options. 22k rows = ~19MB in DB → crash on shared hosting.
		// File on disk = tiny path in wp_options → batch reads offset by offset.
		$upload_dir = wp_upload_dir();
		$store_dir  = trailingslashit( $upload_dir['basedir'] ) . 'sgc-crm-imports/';

		if ( ! file_exists( $store_dir ) ) {
			wp_mkdir_p( $store_dir );
			// Block direct access
			file_put_contents( $store_dir . '.htaccess', 'Require all denied' );
			file_put_contents( $store_dir . 'index.php', '<?php // silence' );
		}

		// Convert xlsx/xls to CSV for uniform offset-reading
		if ( 'csv' !== $ext ) {
			$rows = self::parse_excel( $tmp );
			if ( empty( $rows ) ) {
				wp_send_json_error( esc_html__( 'No data found in Excel file.', 'sgc-smart-crm' ) );
			}
			// Write as CSV to disk
			$stored_file = $store_dir . 'import_' . time() . '.csv';
			$fh = fopen( $stored_file, 'w' ); // phpcs:ignore
			fputcsv( $fh, array_keys( $rows[0] ) );
			foreach ( $rows as $r ) { fputcsv( $fh, $r ); }
			fclose( $fh ); // phpcs:ignore
			$total = count( $rows );
			$source = 'generic_csv';
			unset( $rows ); // free memory immediately
		} else {
			// CSV — count rows first (cheap), then store directly
			$stored_file = $store_dir . 'import_' . time() . '.csv';
			if ( ! move_uploaded_file( $tmp, $stored_file ) ) {
				wp_send_json_error( esc_html__( 'Could not store file. Check uploads folder permissions.', 'sgc-smart-crm' ) );
			}
			// Count rows without loading all into memory
			$total  = max( 0, self::count_csv_rows( $stored_file ) - 1 ); // subtract header
			$source = self::detect_source_from_file( $stored_file );
		}

		if ( $total < 1 ) {
			@unlink( $stored_file ); // phpcs:ignore
			wp_send_json_error( esc_html__( 'No data rows found in file.', 'sgc-smart-crm' ) );
		}

		// Store only job metadata + file path — no rows in DB
		$job = array(
			'filename'    => $filename,
			'stored_file' => $stored_file,
			'total'       => $total,
			'processed'   => 0,
			'added'       => 0,
			'updated'     => 0,
			'skipped'     => 0,
			'errors'      => array(),
			'status'      => 'pending',
			'started_at'  => current_time( 'mysql' ),
			'source'      => $source,
		);

		delete_option( 'sgc_crm_import_rows' ); // clean up old format if any
		update_option( self::JOB_OPTION, $job, false );

		// Schedule first batch immediately + trigger cron
		wp_schedule_single_event( time(), self::CRON_HOOK );
		spawn_cron();

		wp_send_json_success( array(
			'total'   => $total,
			'source'  => $source,
			'message' => sprintf(
				/* translators: %d: total contacts */
				esc_html__( '%d contacts queued. Import started in background.', 'sgc-smart-crm' ),
				$total
			),
		) );
	}

	// =========================================================================
	// AJAX: IMPORT NOW (sync, for small files < 500)
	// =========================================================================

	/**
	 * AJAX — synchronous import for small files.
	 * Runs entire import in one request (max 500 rows).
	 *
	 * @since  1.0.3
	 * @return void
	 */
	public static function ajax_import_now() {
		check_ajax_referer( 'sgc_crm_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions.', 'sgc-smart-crm' ), 403 );
		}

		if ( empty( $_FILES['crm_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['crm_file']['tmp_name'] ) ) {
			wp_send_json_error( esc_html__( 'No file received.', 'sgc-smart-crm' ) );
		}

		$filename = sanitize_file_name( $_FILES['crm_file']['name'] ?? 'import.csv' );
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$tmp      = $_FILES['crm_file']['tmp_name'];

		$rows = ( 'csv' === $ext ) ? self::parse_csv( $tmp ) : self::parse_excel( $tmp );

		if ( empty( $rows ) ) {
			wp_send_json_error( esc_html__( 'No data found.', 'sgc-smart-crm' ) );
		}

		// Cap at 500 for sync.
		$sync_rows = array_slice( $rows, 0, 500 );
		$result    = self::process_rows( $sync_rows );

		wp_send_json_success( array(
			'added'   => $result['added'],
			'updated' => $result['updated'],
			'skipped' => $result['skipped'],
			'total'   => count( $rows ),
			'errors'  => array_slice( $result['errors'], 0, 5 ),
		) );
	}

	// =========================================================================
	// AJAX: PROGRESS POLLING
	// =========================================================================

	/**
	 * AJAX — return current import job progress.
	 * Called by frontend every 2 seconds while import runs.
	 *
	 * @since  1.0.3
	 * @return void
	 */
	public static function ajax_progress() {
		check_ajax_referer( 'sgc_crm_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( '', 403 );
		}

		$job = get_option( self::JOB_OPTION, null );

		if ( ! $job ) {
			wp_send_json_success( array( 'status' => 'idle' ) );
		}

		$pct = $job['total'] > 0 ? round( $job['processed'] / $job['total'] * 100 ) : 0;

		wp_send_json_success( array(
			'status'    => $job['status'],
			'total'     => (int) $job['total'],
			'processed' => (int) $job['processed'],
			'added'     => (int) $job['added'],
			'updated'   => (int) $job['updated'],
			'skipped'   => (int) $job['skipped'],
			'pct'       => $pct,
			'errors'    => array_slice( $job['errors'] ?? array(), 0, 5 ),
			'filename'  => $job['filename'] ?? '',
			'source'    => $job['source'] ?? 'csv',
		) );
	}

	// =========================================================================
	// AJAX: CANCEL
	// =========================================================================

	/**
	 * AJAX — cancel ongoing import.
	 *
	 * @since  1.0.3
	 * @return void
	 */
	public static function ajax_cancel() {
		check_ajax_referer( 'sgc_crm_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( '', 403 );
		}

		$job = get_option( self::JOB_OPTION );
		if ( $job ) {
			$job['status'] = 'cancelled';
			update_option( self::JOB_OPTION, $job, false );
		}

		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_send_json_success( esc_html__( 'Import cancelled.', 'sgc-smart-crm' ) );
	}

	// =========================================================================
	// CRON: BATCH RUNNER
	// =========================================================================

	/**
	 * Process one batch of contacts (500 rows).
	 * Reschedules itself if more batches remain.
	 *
	 * @since  1.0.3
	 * @return void
	 */
	public static function run_batch() {
		$job = get_option( self::JOB_OPTION );

		if ( ! $job || in_array( $job['status'] ?? '', array( 'done', 'cancelled' ), true ) ) {
			return;
		}

		$job['status'] = 'running';
		update_option( self::JOB_OPTION, $job, false );

		$stored_file = $job['stored_file'] ?? '';

		// Legacy fallback: old format stored chunks in wp_options
		if ( empty( $stored_file ) || ! file_exists( $stored_file ) ) {
			$chunks      = get_option( 'sgc_crm_import_rows', array() );
			$chunk_index = (int) floor( $job['processed'] / self::BATCH_SIZE );
			if ( ! isset( $chunks[ $chunk_index ] ) ) {
				$job['status'] = 'done';
				update_option( self::JOB_OPTION, $job, false );
				delete_option( 'sgc_crm_import_rows' );
				return;
			}
			$batch = $chunks[ $chunk_index ];
		} else {
			// v1.1.5: Read batch from file using row offset — no memory bloat
			$offset = (int) ( $job['processed'] );
			$batch  = self::read_csv_batch( $stored_file, $offset, self::BATCH_SIZE );

			if ( empty( $batch ) ) {
				$job['status']      = 'done';
				$job['finished_at'] = current_time( 'mysql' );
				update_option( self::JOB_OPTION, $job, false );
				@unlink( $stored_file ); // phpcs:ignore
				return;
			}
		}

		$result = self::process_rows( $batch );

		$job['processed'] += count( $batch );
		$job['added']     += $result['added'];
		$job['updated']   += $result['updated'];
		$job['skipped']   += $result['skipped'];
		$job['errors']     = array_merge( $job['errors'] ?? array(), $result['errors'] );

		// Cap errors stored to last 20
		if ( count( $job['errors'] ) > 20 ) {
			$job['errors'] = array_slice( $job['errors'], -20 );
		}

		if ( $job['processed'] >= $job['total'] ) {
			$job['status']      = 'done';
			$job['finished_at'] = current_time( 'mysql' );
			update_option( self::JOB_OPTION, $job, false );
			delete_option( 'sgc_crm_import_rows' );
			if ( ! empty( $stored_file ) && file_exists( $stored_file ) ) {
				@unlink( $stored_file ); // phpcs:ignore — clean up temp file
			}
		} else {
			$job['status'] = 'running';
			update_option( self::JOB_OPTION, $job, false );
			// Next batch in 3 seconds (faster than before)
			wp_schedule_single_event( time() + 3, self::CRON_HOOK );
			spawn_cron();
		}
	}

	// ── Read exactly $limit rows from CSV starting at $offset (skipping header) ──
	private static function read_csv_batch( string $file, int $offset, int $limit ): array {
		$rows   = array();
		$handle = fopen( $file, 'r' ); // phpcs:ignore
		if ( ! $handle ) return $rows;

		// Read header
		$bom = fread( $handle, 3 ); // phpcs:ignore
		if ( $bom !== "\xEF\xBB\xBF" ) rewind( $handle );

		$headers = fgetcsv( $handle, 8192, ',' ); // phpcs:ignore
		if ( ! $headers ) { fclose( $handle ); return $rows; } // phpcs:ignore
		$headers = array_map( 'trim', $headers );

		// Skip rows before offset
		$skipped = 0;
		while ( $skipped < $offset ) {
			$line = fgetcsv( $handle, 8192, ',' ); // phpcs:ignore
			if ( false === $line ) { fclose( $handle ); return $rows; } // phpcs:ignore
			$skipped++;
		}

		// Read $limit rows
		$read = 0;
		while ( $read < $limit && false !== ( $data = fgetcsv( $handle, 8192, ',' ) ) ) { // phpcs:ignore
			if ( empty( array_filter( $data ) ) ) continue;
			$data   = array_slice( array_pad( $data, count( $headers ), '' ), 0, count( $headers ) );
			$rows[] = array_combine( $headers, $data );
			$read++;
		}

		fclose( $handle ); // phpcs:ignore
		return $rows;
	}

	// ── Count CSV rows without loading into memory ──────────────────────────
	private static function count_csv_rows( string $file ): int {
		$count  = 0;
		$handle = fopen( $file, 'r' ); // phpcs:ignore
		if ( ! $handle ) return 0;
		while ( false !== fgetcsv( $handle, 8192 ) ) $count++; // phpcs:ignore
		fclose( $handle ); // phpcs:ignore
		return $count;
	}

	// ── Detect source from first data row of file ───────────────────────────
	private static function detect_source_from_file( string $file ): string {
		$handle = fopen( $file, 'r' ); // phpcs:ignore
		if ( ! $handle ) return 'generic_csv';
		$bom = fread( $handle, 3 ); // phpcs:ignore
		if ( $bom !== "\xEF\xBB\xBF" ) rewind( $handle );
		$headers = fgetcsv( $handle, 8192 ) ?: []; // phpcs:ignore
		fclose( $handle ); // phpcs:ignore
		$google_markers = array( 'E-mail 1 - Value', 'Phone 1 - Value', 'Labels', 'Organization Name' );
		$matches = count( array_intersect( $google_markers, $headers ) );
		return $matches >= 3 ? 'google_contacts' : 'generic_csv';
	}

	// =========================================================================
	// CORE: PROCESS ROWS
	// =========================================================================

	/**
	 * Process an array of parsed rows — insert or update contacts.
	 *
	 * @since  1.0.3
	 * @param  array<int, array<string, string>> $rows Parsed contact rows.
	 * @return array{added:int, updated:int, skipped:int, errors:array<string>}
	 */
	private static function process_rows( array $rows ) {
		$added   = 0;
		$updated = 0;
		$skipped = 0;
		$errors  = array();

		foreach ( $rows as $i => $row ) {
			// Find valid email — check up to 4 email columns.
			$email = '';
			for ( $n = 1; $n <= 4; $n++ ) {
				$candidate = strtolower( trim(
					$row[ "E-mail {$n} - Value" ] ?? $row[ "Email {$n} - Value" ] ?? $row['email'] ?? $row['Email'] ?? ''
				) );
				if ( $candidate && filter_var( $candidate, FILTER_VALIDATE_EMAIL ) ) {
					$email = $candidate;
					break;
				}
			}

			// Also try plain "email" columns for generic CSV.
			if ( empty( $email ) ) {
				foreach ( array( 'email', 'Email', 'EMAIL', 'e-mail', 'E-mail', 'Email Address' ) as $col ) {
					$candidate = strtolower( trim( $row[ $col ] ?? '' ) );
					if ( $candidate && filter_var( $candidate, FILTER_VALIDATE_EMAIL ) ) {
						$email = $candidate;
						break;
					}
				}
			}

			if ( empty( $email ) ) {
				++$skipped;
				continue;
			}

			// Build contact data.
			$data = self::map_row( $row, $email );

			$existing = SGC_CRM_Contacts::get_by_email( $email );
			if ( $existing ) {
				// Update — don't overwrite segment if already set.
				if ( ! empty( $existing->segment ) && 'end_user' !== $existing->segment ) {
					unset( $data['segment'] );
				}
				SGC_CRM_Contacts::update( (int) $existing->id, $data );
				++$updated;
			} else {
				$data['status'] = 'subscribed';
				SGC_CRM_Contacts::insert( $data );
				++$added;
			}
		}

		return compact( 'added', 'updated', 'skipped', 'errors' );
	}

	// =========================================================================
	// FIELD MAPPING
	// =========================================================================

	/**
	 * Map a raw CSV row to CRM contact fields.
	 * Handles both Google CSV format and generic CSV formats.
	 *
	 * @since  1.0.3
	 * @param  array<string, string> $row   Raw CSV row.
	 * @param  string                $email Validated email.
	 * @return array<string, mixed>  CRM contact data array.
	 */
	private static function map_row( array $row, string $email ) {
		// ── Name ──────────────────────────────────────────────────────────────
		$first = trim( $row['First Name'] ?? $row['first_name'] ?? $row['firstname'] ?? '' );
		$last  = trim( $row['Last Name']  ?? $row['last_name']  ?? $row['lastname']  ?? '' );

		// Fallback: split "Name" column.
		if ( empty( $first ) ) {
			$full_name = trim(
				$row['Name'] ?? $row['Full Name'] ?? $row['Contact Name'] ?? ''
			);
			if ( $full_name ) {
				$parts = explode( ' ', $full_name, 2 );
				$first = $parts[0];
				$last  = $parts[1] ?? '';
			}
		}

		// ── Phone ─────────────────────────────────────────────────────────────
		$phone = self::normalize_phone(
			$row['Phone 1 - Value'] ?? $row['phone'] ?? $row['Phone'] ?? $row['Mobile'] ?? ''
		);

		// ── Organization ──────────────────────────────────────────────────────
		$company = trim(
			$row['Organization Name'] ?? $row['company'] ?? $row['Company'] ?? $row['Organization'] ?? ''
		);

		// ── Address ───────────────────────────────────────────────────────────
		$city    = trim( $row['Address 1 - City']    ?? $row['city']    ?? $row['City']    ?? '' );
		$country = trim( $row['Address 1 - Country'] ?? $row['country'] ?? $row['Country'] ?? '' );
		$region  = trim( $row['Address 1 - Region']  ?? $row['state']   ?? $row['State']   ?? '' );

		// ── Notes / Website ───────────────────────────────────────────────────
		$notes   = trim( $row['Notes'] ?? $row['notes'] ?? $row['Description'] ?? '' );
		$website = trim( $row['Website 1 - Value'] ?? $row['website'] ?? $row['Website'] ?? '' );

		// ── Labels → Tags + Segment ───────────────────────────────────────────
		$labels_raw = $row['Labels'] ?? $row['Group Membership'] ?? $row['tags'] ?? $row['Tags'] ?? '';
		$parsed     = self::parse_labels( $labels_raw, $email, $company );

		return array(
			'email'      => $email,
			'first_name' => sanitize_text_field( $first ),
			'last_name'  => sanitize_text_field( $last ),
			'company'    => sanitize_text_field( $company ),
			'phone'      => sanitize_text_field( $phone ),
			'city'       => sanitize_text_field( $city ),
			'country'    => sanitize_text_field( $country ),
			'tags'       => sanitize_text_field( $parsed['tags'] ),
			'segment'    => sanitize_key( $parsed['segment'] ),
			'notes'      => sanitize_textarea_field( $notes . ( $website ? "\nWebsite: {$website}" : '' ) ),
			'source'     => 'google_contacts',
		);
	}

	// =========================================================================
	// SMART LABEL → SEGMENT MAPPING
	// =========================================================================

	/**
	 * Parse Google Labels / Group Membership into CRM tags and segment.
	 *
	 * Google labels look like:
	 *   "* myContacts ::: dealer ::: Imported on 5/27 ::: * starred"
	 *
	 * We extract meaningful labels, ignore Google system labels, and map to segments.
	 *
	 * @since  1.0.3
	 * @param  string $labels_raw Raw labels string from CSV.
	 * @param  string $email      Contact email (used for domain-based segment hints).
	 * @param  string $company    Company name (used for segment hints).
	 * @return array{segment:string, tags:string}
	 */
	private static function parse_labels( string $labels_raw, string $email, string $company ) {
		// Split on :::
		$all = array_map( 'trim', explode( ':::', $labels_raw ) );

		// Filter out Google system labels.
		$system_patterns = array( '* myContacts', '* starred', '* other', 'Imported on', 'myContacts' );
		$clean_labels    = array();
		foreach ( $all as $label ) {
			$is_system = false;
			foreach ( $system_patterns as $pat ) {
				if ( stripos( $label, $pat ) !== false ) {
					$is_system = true;
					break;
				}
			}
			if ( ! $is_system && ! empty( $label ) && '*' !== substr( trim( $label ), 0, 1 ) ) {
				$clean_labels[] = strtolower( $label );
			}
		}

		// Detect segment from labels first.
		$segment = self::detect_segment( $clean_labels, $email, $company );

		// Build tags string.
		$tags = implode( ', ', array_unique( $clean_labels ) );

		return array( 'segment' => $segment, 'tags' => $tags );
	}

	/**
	 * Detect best CRM segment from available signals.
	 *
	 * Priority: explicit label match > email domain > company name > default
	 *
	 * @since  1.0.3
	 * @param  array<string> $labels  Clean labels.
	 * @param  string        $email   Email address.
	 * @param  string        $company Company name.
	 * @return string Segment key.
	 */
	private static function detect_segment( array $labels, string $email, string $company ) {
		$label_str   = implode( ' ', $labels );
		$company_low = strtolower( $company );
		$domain      = '';
		if ( strpos( $email, '@' ) !== false ) {
			$domain = strtolower( explode( '@', $email )[1] );
		}

		// Explicit label match — highest priority.
		$label_map = array(
			'glass_blower'  => array( 'glass blower', 'glass-blower', 'glassblower', 'glass blowing', 'glass workshop', 'glass fab' ),
			'semi_finished'  => array( 'semi finished', 'semi-finished', 'semifinished', 'custom fab', 'blanks' ),
			'dealer'         => array( 'dealer', 'distributor', 'reseller', 'wholesaler', 'stockist', 'supplier', 'vendor', 'agent' ),
			'student'        => array( 'student', 'scholar', 'intern', 'undergraduate', 'postgraduate', 'phd' ),
			'teacher'        => array( 'teacher', 'professor', 'lecturer', 'faculty', 'instructor', 'principal', 'hod', 'director' ),
			'international'  => array( 'international', 'export', 'overseas', 'foreign', 'import', 'global' ),
			'domestic'       => array( 'domestic', 'india', 'local', 'pan india' ),
			'end_user'       => array( 'lab', 'laboratory', 'hospital', 'pharma', 'industry', 'industrial', 'client', 'customer' ),
		);

		foreach ( $label_map as $seg => $keywords ) {
			foreach ( $keywords as $kw ) {
				if ( stripos( $label_str, $kw ) !== false ) {
					return $seg;
				}
			}
		}

		// Email domain hints.
		$academic_domains = array( '.edu', '.ac.in', '.edu.in', 'university', 'college', 'iit', 'nit', 'iiser', 'iisc' );
		foreach ( $academic_domains as $ad ) {
			if ( strpos( $domain, $ad ) !== false ) {
				return 'student';
			}
		}

		if ( strpos( $domain, 'school' ) !== false || strpos( $domain, 'vidyalay' ) !== false ) {
			return 'teacher';
		}

		// Company name hints.
		$company_map = array(
			'glass_blower'  => array( 'glass', 'quartz', 'borosilicate' ),
			'dealer'         => array( 'supply', 'supplies', 'scientific', 'lab equip', 'trading', 'traders', 'enterprise', 'corporation', 'pvt', 'ltd', 'llp', 'inc', 'co.' ),
			'teacher'        => array( 'college', 'university', 'school', 'institute', 'iit', 'nit', 'academy' ),
			'end_user'       => array( 'hospital', 'pharma', 'research', 'pathology', 'diagnostic', 'clinic' ),
			'international'  => array( 'export', 'import', 'international', 'global', 'overseas' ),
		);

		foreach ( $company_map as $seg => $keywords ) {
			foreach ( $keywords as $kw ) {
				if ( stripos( $company_low, $kw ) !== false ) {
					return $seg;
				}
			}
		}

		// Country hint from email TLD.
		$non_india_tlds = array( '.com.au', '.co.uk', '.co.za', '.ca', '.de', '.fr', '.cn', '.jp', '.us' );
		foreach ( $non_india_tlds as $tld ) {
			if ( substr( $domain, -strlen( $tld ) ) === $tld ) {
				return 'international';
			}
		}

		// Default.
		return 'end_user';
	}

	// =========================================================================
	// DETECT SOURCE FORMAT
	// =========================================================================

	/**
	 * Detect whether CSV is Google Contacts format or generic.
	 *
	 * @since  1.0.3
	 * @param  array<int, array<string, string>> $rows Parsed rows.
	 * @return string 'google_contacts' | 'generic_csv'
	 */
	private static function detect_source( array $rows ) {
		if ( empty( $rows ) ) {
			return 'generic_csv';
		}
		$keys = array_keys( $rows[0] );
		// Google Contacts always has these specific columns.
		$google_markers = array( 'E-mail 1 - Value', 'Phone 1 - Value', 'Labels', 'Organization Name' );
		$matches        = 0;
		foreach ( $google_markers as $marker ) {
			if ( in_array( $marker, $keys, true ) ) {
				++$matches;
			}
		}
		return $matches >= 3 ? 'google_contacts' : 'generic_csv';
	}

	// =========================================================================
	// PHONE NORMALIZATION
	// =========================================================================

	/**
	 * Normalize phone numbers — fixes scientific notation (9.19729E+11 → 919729...).
	 *
	 * @since  1.0.3
	 * @param  string $raw Raw phone value.
	 * @return string Cleaned phone string.
	 */
	private static function normalize_phone( string $raw ) {
		$raw = trim( $raw );
		if ( empty( $raw ) ) {
			return '';
		}

		// Fix Excel scientific notation: 9.19729E+11 → 919729000000.
		if ( preg_match( '/^[\d.]+E\+\d+$/i', $raw ) ) {
			try {
				$raw = number_format( (float) $raw, 0, '.', '' );
			} catch ( \Throwable $e ) {
				return '';
			}
		}

		// Keep only digits, +, -, (, ), space.
		$raw = preg_replace( '/[^\d+\-() ]/', '', $raw );

		// Trim to reasonable length.
		return substr( trim( $raw ), 0, 20 );
	}

	// =========================================================================
	// CSV PARSER
	// =========================================================================

	/**
	 * Parse a CSV file into associative rows.
	 * Handles UTF-8 BOM, CRLF line endings, quoted fields.
	 *
	 * @since  1.0.3
	 * @param  string $filepath Path to CSV file.
	 * @return array<int, array<string, string>>
	 */
	public static function parse_csv( string $filepath ) {
		$rows   = array();
		$handle = fopen( $filepath, 'r' ); // phpcs:ignore
		if ( ! $handle ) {
			return $rows;
		}

		// Strip UTF-8 BOM if present.
		$bom = fread( $handle, 3 ); // phpcs:ignore
		if ( $bom !== "\xEF\xBB\xBF" ) {
			rewind( $handle );
		}

		$headers = null;
		while ( false !== ( $data = fgetcsv( $handle, 8192, ',' ) ) ) { // phpcs:ignore
			if ( null === $headers ) {
				// Clean headers — strip BOM, trim, normalize CRLF.
				$headers = array_map( function ( $h ) {
					return trim( str_replace( array( "\r", "\n", "\xEF\xBB\xBF" ), '', $h ) );
				}, $data );
				continue;
			}

			// Skip completely empty rows.
			if ( empty( array_filter( $data ) ) ) {
				continue;
			}

			// Pad/trim to header count.
			$data = array_slice( array_pad( $data, count( $headers ), '' ), 0, count( $headers ) );
			$rows[] = array_combine( $headers, $data );
		}

		fclose( $handle ); // phpcs:ignore
		return $rows;
	}

	// =========================================================================
	// EXCEL PARSER
	// =========================================================================

	/**
	 * Parse an Excel file into associative rows.
	 * Uses PhpSpreadsheet if available, else ZIP+XML fallback.
	 *
	 * @since  1.0.3
	 * @param  string $filepath Path to .xlsx / .xls file.
	 * @return array<int, array<string, string>>
	 */
	public static function parse_excel( string $filepath ) {
		// Try PhpSpreadsheet (bundled with WooCommerce 4+).
		if ( class_exists( '\PhpOffice\PhpSpreadsheet\IOFactory' ) ) {
			try {
				$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load( $filepath );
				$raw         = $spreadsheet->getActiveSheet()->toArray( null, true, true, false );
				if ( empty( $raw ) ) {
					return array();
				}
				$headers = array_map( 'trim', array_shift( $raw ) );
				$rows    = array();
				foreach ( $raw as $row ) {
					if ( empty( array_filter( $row ) ) ) {
						continue;
					}
					$padded = array_pad( $row, count( $headers ), '' );
					$rows[] = array_combine( $headers, array_slice( $padded, 0, count( $headers ) ) );
				}
				return $rows;
			} catch ( \Throwable $e ) {
				// Fall through to ZIP parser.
			}
		}

		// ZIP+XML fallback for .xlsx.
		return self::parse_xlsx_zip( $filepath );
	}

	/**
	 * Parse .xlsx using ZipArchive + SimpleXML (no external library needed).
	 *
	 * @since  1.0.3
	 * @param  string $filepath Path to .xlsx file.
	 * @return array<int, array<string, string>>
	 */
	private static function parse_xlsx_zip( string $filepath ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $filepath ) ) {
			return array();
		}

		// Shared strings.
		$shared = array();
		$ss     = $zip->getFromName( 'xl/sharedStrings.xml' );
		if ( $ss ) {
			$dom = simplexml_load_string( $ss );
			if ( $dom ) {
				foreach ( $dom->si as $si ) {
					$shared[] = (string) $si->t;
				}
			}
		}

		$sheet = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
		$zip->close();

		if ( ! $sheet ) {
			return array();
		}

		$dom     = simplexml_load_string( $sheet );
		$raw     = array();

		foreach ( $dom->sheetData->row as $row ) {
			$r = array();
			foreach ( $row->c as $cell ) {
				$type = (string) $cell['t'];
				$val  = (string) $cell->v;
				if ( 's' === $type ) {
					$val = $shared[ (int) $val ] ?? '';
				}
				$r[] = $val;
			}
			$raw[] = $r;
		}

		if ( empty( $raw ) ) {
			return array();
		}

		$headers = array_map( 'trim', array_shift( $raw ) );
		$rows    = array();
		foreach ( $raw as $row ) {
			if ( empty( array_filter( $row ) ) ) {
				continue;
			}
			$rows[] = array_combine( $headers, array_pad( $row, count( $headers ), '' ) );
		}
		return $rows;
	}
}

// Register all hooks.
SGC_CRM_Importer::register();
