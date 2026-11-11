<?php
defined( 'ABSPATH' ) || exit;

class SGC_PM_Importer {

	public static function register_ajax() {
		add_action( 'wp_ajax_sgc_pm_import_preview', [ __CLASS__, 'ajax_preview'  ] );
		add_action( 'wp_ajax_sgc_pm_import_run',     [ __CLASS__, 'ajax_run'      ] );
		add_action( 'wp_ajax_sgc_pm_import_status',  [ __CLASS__, 'ajax_status'   ] );
	}

	private static function verify() {
		check_ajax_referer( 'sgc_pm_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) )
			wp_send_json_error( 'Unauthorized', 403 );
	}

	public static function ajax_preview() {
		self::verify();
		if ( empty( $_FILES['import_file']['tmp_name'] ) ) wp_send_json_error( 'No file uploaded.' );

		$tmp  = $_FILES['import_file']['tmp_name']; // phpcs:ignore
		$name = sanitize_file_name( $_FILES['import_file']['name'] ); // phpcs:ignore
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, [ 'csv', 'xlsx', 'xls' ], true ) ) wp_send_json_error( 'Only CSV/Excel allowed.' );
		if ( ! is_uploaded_file( $tmp ) ) wp_send_json_error( 'Invalid upload.' );

		$rows = ( 'csv' === $ext ) ? self::parse_csv( $tmp ) : self::parse_excel( $tmp );
		if ( empty( $rows ) ) wp_send_json_error( 'No data found.' );

		// Store for import
		$job_id = 'import_' . time();
		set_transient( 'sgc_pm_' . $job_id, $rows, HOUR_IN_SECONDS );

		wp_send_json_success( [
			'job_id'  => $job_id,
			'total'   => count( $rows ),
			'columns' => array_keys( $rows[0] ),
			'preview' => array_slice( $rows, 0, 5 ),
		] );
	}

	public static function ajax_run() {
		self::verify();
		$job_id   = sanitize_key( $_POST['job_id'] ?? '' );
		$mapping  = (array) ( $_POST['mapping'] ?? [] );
		$mode     = sanitize_key( $_POST['mode'] ?? 'upsert' ); // insert | upsert | update_only

		$rows = get_transient( 'sgc_pm_' . $job_id );
		if ( ! $rows ) wp_send_json_error( 'Import session expired. Re-upload file.' );

		$done = $skip = $fail = 0;
		foreach ( $rows as $row ) {
			$name     = sanitize_text_field( $row[ $mapping['name']        ?? 'name'          ] ?? '' );
			$sku      = sanitize_text_field( $row[ $mapping['sku']         ?? 'sku'           ] ?? '' );
			$price    = $row[ $mapping['regular_price'] ?? 'regular_price' ] ?? '';
			$sale     = $row[ $mapping['sale_price']    ?? 'sale_price'    ] ?? '';
			$stock    = $row[ $mapping['stock']         ?? 'stock_quantity'] ?? '';
			$status   = sanitize_key( $row[ $mapping['status'] ?? 'status' ] ?? 'draft' );
			$desc     = wp_kses_post( $row[ $mapping['description'] ?? 'description'] ?? '' );
			$short    = sanitize_textarea_field( $row[ $mapping['short_description'] ?? 'short_description'] ?? '' );
			$category = sanitize_text_field( $row[ $mapping['category'] ?? 'category'] ?? '' );

			if ( ! $name ) { $skip++; continue; }

			// Find existing by SKU
			$existing_id = $sku ? wc_get_product_id_by_sku( $sku ) : 0;

			if ( $existing_id && 'insert' === $mode ) { $skip++; continue; }
			if ( ! $existing_id && 'update_only' === $mode ) { $skip++; continue; }

			$post_data = [
				'post_title'   => $name,
				'post_status'  => in_array( $status, [ 'publish', 'draft', 'private' ], true ) ? $status : 'draft',
				'post_type'    => 'product',
				'post_content' => $desc,
				'post_excerpt' => $short,
			];

			if ( $existing_id ) {
				$post_data['ID'] = $existing_id;
				$product_id = wp_update_post( $post_data );
			} else {
				$post_data['post_author'] = get_current_user_id();
				$product_id = wp_insert_post( $post_data );
			}

			if ( is_wp_error( $product_id ) || ! $product_id ) { $fail++; continue; }

			$product = wc_get_product( $product_id );
			if ( $sku )   { try { $product->set_sku( $sku ); } catch ( Exception $e ) {} }
			if ( $price ) $product->set_regular_price( wc_format_decimal( $price ) );
			if ( $sale )  $product->set_sale_price( wc_format_decimal( $sale ) );
			if ( $stock !== '' ) {
				$product->set_manage_stock( true );
				$product->set_stock_quantity( (int) $stock );
			}
			$product->save();

			// Category
			if ( $category ) {
				$term = get_term_by( 'name', $category, 'product_cat' );
				if ( ! $term ) {
					$new  = wp_insert_term( $category, 'product_cat' );
					$term = ! is_wp_error( $new ) ? get_term( $new['term_id'], 'product_cat' ) : null;
				}
				if ( $term ) wp_set_post_terms( $product_id, [ $term->term_id ], 'product_cat' );
			}

			wc_delete_product_transients( $product_id );
			$done++;
		}

		delete_transient( 'sgc_pm_' . $job_id );
		SGC_PM_Logger::log( 'import', 'bulk', null, "Import: $done done, $skip skipped, $fail failed" );
		wp_send_json_success( [ 'done' => $done, 'skipped' => $skip, 'failed' => $fail, 'total' => count( $rows ) ] );
	}

	public static function ajax_status() {
		self::verify();
		wp_send_json_success( [ 'status' => 'idle' ] );
	}

	private static function parse_csv( $file ) {
		$rows = $headers = [];
		$handle = fopen( $file, 'r' ); // phpcs:ignore
		if ( ! $handle ) return [];
		$line = 0;
		while ( false !== ( $data = fgetcsv( $handle, 4096, ',' ) ) ) { // phpcs:ignore
			if ( 0 === $line ) { $headers = array_map( 'strtolower', array_map( 'trim', $data ) ); $line++; continue; }
			if ( count( $data ) >= count( $headers ) )
				$rows[] = array_combine( $headers, array_slice( $data, 0, count( $headers ) ) );
			$line++;
		}
		fclose( $handle ); // phpcs:ignore
		return $rows;
	}

	private static function parse_excel( $file ) {
		if ( class_exists( '\PhpOffice\PhpSpreadsheet\IOFactory' ) ) {
			try {
				$sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load( $file )->getActiveSheet()->toArray( null, true, true, false );
				if ( empty( $sheet ) ) return [];
				$headers = array_map( 'strtolower', array_map( 'trim', array_shift( $sheet ) ) );
				$rows = [];
				foreach ( $sheet as $row ) {
					if ( empty( array_filter( $row ) ) ) continue;
					$rows[] = array_combine( $headers, array_pad( $row, count( $headers ), '' ) );
				}
				return $rows;
			} catch ( \Exception $e ) { return []; }
		}
		// ZIP fallback for xlsx
		$zip = new ZipArchive();
		if ( true !== $zip->open( $file ) ) return [];
		$ss_xml = $zip->getFromName( 'xl/sharedStrings.xml' );
		$shared  = [];
		if ( $ss_xml ) {
			$dom = simplexml_load_string( $ss_xml );
			if ( $dom ) foreach ( $dom->si as $si ) $shared[] = (string) $si->t;
		}
		$sheet_xml = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
		$zip->close();
		if ( ! $sheet_xml ) return [];
		$dom = simplexml_load_string( $sheet_xml );
		$raw = [];
		foreach ( $dom->sheetData->row as $row ) {
			$r = [];
			foreach ( $row->c as $cell ) {
				$v = (string) $cell->v;
				$r[] = ( 's' === (string) $cell['t'] && isset( $shared[ (int) $v ] ) ) ? $shared[ (int) $v ] : $v;
			}
			$raw[] = $r;
		}
		if ( empty( $raw ) ) return [];
		$headers = array_map( 'strtolower', array_map( 'trim', array_shift( $raw ) ) );
		$rows = [];
		foreach ( $raw as $row ) {
			if ( empty( array_filter( $row ) ) ) continue;
			$rows[] = array_combine( $headers, array_pad( $row, count( $headers ), '' ) );
		}
		return $rows;
	}
}

SGC_PM_Importer::register_ajax();
