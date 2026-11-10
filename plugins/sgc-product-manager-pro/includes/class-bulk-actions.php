<?php
defined( 'ABSPATH' ) || exit;

class SGC_PM_Bulk_Actions {

	public static function register_ajax() {
		$actions = [
			'sgc_pm_bulk_delete'          => 'ajax_bulk_delete',
			'sgc_pm_bulk_price'           => 'ajax_bulk_price',
			'sgc_pm_bulk_stock'           => 'ajax_bulk_stock',
			'sgc_pm_bulk_status'          => 'ajax_bulk_status',
			'sgc_pm_bulk_category'        => 'ajax_bulk_category',
			'sgc_pm_bulk_export'          => 'ajax_bulk_export',
			'sgc_pm_bulk_sku'             => 'ajax_bulk_sku',
			'sgc_pm_delete_all_products'  => 'ajax_delete_all',
			// Quick Edit (v3.0.4)
			'sgc_pm_quick_edit_load'      => 'ajax_quick_edit_load',
			'sgc_pm_quick_edit_save'      => 'ajax_quick_edit_save',
			// Bulk Edit (v3.0.4)
			'sgc_pm_bulk_edit'            => 'ajax_bulk_edit',
		];
		foreach ( $actions as $action => $method ) {
			add_action( 'wp_ajax_' . $action, [ __CLASS__, $method ] );
		}
	}

	private static function verify() {
		check_ajax_referer( 'sgc_pm_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) )
			wp_send_json_error( 'Unauthorized', 403 );
	}

	private static function get_ids() {
		$ids = isset( $_POST['ids'] ) ? array_filter( array_map( 'absint', (array) $_POST['ids'] ) ) : [];
		if ( empty( $ids ) ) wp_send_json_error( 'No products selected.' );
		return array_values( $ids );
	}

	// ── Bulk Delete ──────────────────────────────────────────────────────
	public static function ajax_bulk_delete() {
		self::verify();
		$token = strtoupper( sanitize_text_field( $_POST['confirm_token'] ?? '' ) );
		if ( 'DELETE' !== $token ) wp_send_json_error( 'Type DELETE to confirm.' );

		$ids     = self::get_ids();
		$deleted = 0;
		foreach ( $ids as $id ) {
			if ( wp_delete_post( $id, true ) ) $deleted++;
		}
		wp_send_json_success( [ 'deleted' => $deleted, 'message' => "$deleted product(s) permanently deleted." ] );
	}

	// ── Delete ALL products ──────────────────────────────────────────────
	public static function ajax_delete_all() {
		self::verify();
		$token = strtoupper( sanitize_text_field( $_POST['confirm_token'] ?? '' ) );
		if ( 'DELETE' !== $token ) wp_send_json_error( 'Type DELETE to confirm.' );

		$ids = get_posts( [ 'post_type' => 'product', 'posts_per_page' => -1, 'fields' => 'ids', 'post_status' => 'any' ] );
		$deleted = 0;
		foreach ( $ids as $id ) {
			if ( wp_delete_post( $id, true ) ) $deleted++;
		}
		wp_send_json_success( [ 'deleted' => $deleted, 'message' => "All $deleted product(s) deleted." ] );
	}

	// ── Bulk Price Update ────────────────────────────────────────────────
	public static function ajax_bulk_price() {
		self::verify();
		$ids        = self::get_ids();
		$mode       = sanitize_key( $_POST['mode'] ?? 'fixed' ); // fixed | increase_pct | decrease_pct | set_sale
		$value      = floatval( $_POST['value'] ?? 0 );
		$field      = sanitize_key( $_POST['price_field'] ?? 'regular' ); // regular | sale
		$updated    = 0;

		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) continue;

			if ( 'regular' === $field ) {
				$current = floatval( $product->get_regular_price() ?: 0 );
				$new_price = match( $mode ) {
					'increase_pct' => $current * ( 1 + $value / 100 ),
					'decrease_pct' => $current * ( 1 - $value / 100 ),
					default        => $value,
				};
				$product->set_regular_price( wc_format_decimal( max( 0, $new_price ) ) );
			} else {
				$current = floatval( $product->get_sale_price() ?: $product->get_regular_price() ?: 0 );
				$new_price = match( $mode ) {
					'increase_pct' => $current * ( 1 + $value / 100 ),
					'decrease_pct' => $current * ( 1 - $value / 100 ),
					default        => $value,
				};
				if ( '' === (string) $value || 0.0 === $value ) {
					$product->set_sale_price( '' );
				} else {
					$product->set_sale_price( wc_format_decimal( max( 0, $new_price ) ) );
				}
			}

			$product->save();
			wc_delete_product_transients( $id );
			$updated++;
		}

		wp_send_json_success( [ 'updated' => $updated, 'message' => "$updated product(s) price updated." ] );
	}

	// ── Bulk Stock Update ────────────────────────────────────────────────
	public static function ajax_bulk_stock() {
		self::verify();
		$ids        = self::get_ids();
		$status     = sanitize_key( $_POST['stock_status'] ?? 'instock' );
		$quantity   = isset( $_POST['quantity'] ) && '' !== $_POST['quantity'] ? (int) $_POST['quantity'] : null;
		$updated    = 0;

		$allowed_status = [ 'instock', 'outofstock', 'onbackorder' ];
		if ( ! in_array( $status, $allowed_status, true ) ) wp_send_json_error( 'Invalid stock status.' );

		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) continue;
			$product->set_stock_status( $status );
			if ( null !== $quantity ) {
				$product->set_manage_stock( true );
				$product->set_stock_quantity( $quantity );
			}
			$product->save();
			wc_delete_product_transients( $id );
			$updated++;
		}

		wp_send_json_success( [ 'updated' => $updated, 'message' => "$updated product(s) stock updated." ] );
	}

	// ── Bulk Status Change ───────────────────────────────────────────────
	public static function ajax_bulk_status() {
		self::verify();
		$ids     = self::get_ids();
		$status  = sanitize_key( $_POST['new_status'] ?? 'publish' );
		$allowed = [ 'publish', 'draft', 'private', 'pending' ];
		if ( ! in_array( $status, $allowed, true ) ) wp_send_json_error( 'Invalid status.' );

		$updated = 0;
		foreach ( $ids as $id ) {
			if ( wp_update_post( [ 'ID' => $id, 'post_status' => $status ] ) ) $updated++;
		}
		wp_send_json_success( [ 'updated' => $updated, 'message' => "$updated product(s) status → $status." ] );
	}

	// ── Bulk Category Assign ─────────────────────────────────────────────
	public static function ajax_bulk_category() {
		self::verify();
		$ids     = self::get_ids();
		$cat_id  = (int) ( $_POST['cat_id'] ?? 0 );
		$append  = (bool) ( $_POST['append'] ?? false );
		if ( ! $cat_id ) wp_send_json_error( 'No category selected.' );

		$updated = 0;
		foreach ( $ids as $id ) {
			$current = $append ? wp_get_post_terms( $id, 'product_cat', [ 'fields' => 'ids' ] ) : [];
			$current = is_array( $current ) ? $current : [];
			$current[] = $cat_id;
			wp_set_post_terms( $id, array_unique( $current ), 'product_cat' );
			$updated++;
		}
		wp_send_json_success( [ 'updated' => $updated, 'message' => "$updated product(s) category updated." ] );
	}

	// ── Bulk Export CSV ──────────────────────────────────────────────────
	public static function ajax_bulk_export() {
		self::verify();
		$ids     = self::get_ids();
		$usd     = floatval( get_option( 'sgc_pm_usd_rate', '83.5' ) );
		$rows    = [];

		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) continue;
			$price    = floatval( $product->get_regular_price() ?: 0 );
			$cats     = wp_get_post_terms( $id, 'product_cat', [ 'fields' => 'names' ] );
			$rows[]   = [
				$id,
				html_entity_decode( $product->get_name() ),
				$product->get_sku(),
				$price,
				$product->get_sale_price() ?: '',
				$usd > 0 ? round( $price / $usd, 2 ) : '',
				$product->get_stock_quantity(),
				$product->get_stock_status(),
				get_post_status( $id ),
				$product->get_type(),
				implode( '|', is_array( $cats ) ? $cats : [] ),
			];
		}

		$header = [ 'ID','Name','SKU','Price INR','Sale Price INR','Price USD','Stock Qty','Stock Status','Status','Type','Categories' ];
		$csv    = implode( ',', $header ) . "\n";
		foreach ( $rows as $row ) {
			$csv .= implode( ',', array_map( fn($v) => '"' . str_replace( '"', '""', $v ) . '"', $row ) ) . "\n";
		}

		wp_send_json_success( [
			'csv'      => base64_encode( $csv ),
			'filename' => 'sgc-products-' . gmdate( 'Y-m-d' ) . '.csv',
			'count'    => count( $rows ),
		]);
	}

	// ── Bulk SKU Generator ───────────────────────────────────────────────
	public static function ajax_bulk_sku() {
		self::verify();
		$ids     = self::get_ids();
		$prefix  = strtoupper( sanitize_text_field( $_POST['prefix'] ?? 'SGC' ) );
		$start   = max( 1, (int) ( $_POST['start'] ?? 1 ) );
		$pad     = max( 3, min( 6, (int) ( $_POST['pad'] ?? 4 ) ) );
		$updated = 0;

		foreach ( $ids as $i => $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) continue;
			$sku = $prefix . '-' . str_pad( $start + $i, $pad, '0', STR_PAD_LEFT );
			// Ensure unique
			while ( wc_get_product_id_by_sku( $sku ) && wc_get_product_id_by_sku( $sku ) !== $id ) {
				$start++;
				$sku = $prefix . '-' . str_pad( $start + $i, $pad, '0', STR_PAD_LEFT );
			}
			$product->set_sku( $sku );
			$product->save();
			$updated++;
		}

		wp_send_json_success( [ 'updated' => $updated, 'message' => "$updated SKU(s) generated." ] );
	}

	// =========================================================================
	// QUICK EDIT — Single product (v3.0.4)
	// =========================================================================

	/**
	 * AJAX: Load single product data for Quick Edit drawer.
	 */
	public static function ajax_quick_edit_load() {
		self::verify();
		$id      = (int) ( $_POST['product_id'] ?? 0 );
		$product = wc_get_product( $id );
		if ( ! $product ) wp_send_json_error( 'Product not found.' );

		// Gallery image IDs → URLs.
		$gallery_ids  = $product->get_gallery_image_ids();
		$gallery_urls = array_map( function( $gid ) {
			return wp_get_attachment_url( $gid );
		}, $gallery_ids );

		$thumb_id  = $product->get_image_id();
		$thumb_url = $thumb_id ? wp_get_attachment_url( $thumb_id ) : '';

		wp_send_json_success( [
			'id'                => $id,
			'name'              => $product->get_name(),
			'sku'               => $product->get_sku(),
			'regular_price'     => $product->get_regular_price(),
			'sale_price'        => $product->get_sale_price(),
			'short_description' => $product->get_short_description(),
			'description'       => $product->get_description(),
			'featured_image_url'=> $thumb_url,
			'gallery_urls'      => $gallery_urls,
			'edit_url'          => get_edit_post_link( $id, '' ),
		] );
	}

	/**
	 * AJAX: Save single product Quick Edit changes.
	 */
	public static function ajax_quick_edit_save() {
		self::verify();
		$id      = (int) ( $_POST['product_id'] ?? 0 );
		$product = wc_get_product( $id );
		if ( ! $product ) wp_send_json_error( 'Product not found.' );

		$updated = [];

		// Title.
		if ( isset( $_POST['name'] ) && $_POST['name'] !== '' ) {
			$product->set_name( sanitize_text_field( wp_unslash( $_POST['name'] ) ) );
			$updated[] = 'name';
		}
		// SKU.
		if ( isset( $_POST['sku'] ) ) {
			$product->set_sku( sanitize_text_field( wp_unslash( $_POST['sku'] ) ) );
			$updated[] = 'sku';
		}
		// Prices.
		if ( isset( $_POST['regular_price'] ) && $_POST['regular_price'] !== '' ) {
			$product->set_regular_price( wc_format_decimal( wp_unslash( $_POST['regular_price'] ) ) );
			$updated[] = 'regular_price';
		}
		if ( isset( $_POST['sale_price'] ) ) {
			$product->set_sale_price( $_POST['sale_price'] !== '' ? wc_format_decimal( wp_unslash( $_POST['sale_price'] ) ) : '' );
			$updated[] = 'sale_price';
		}
		// Short description.
		if ( isset( $_POST['short_description'] ) ) {
			$product->set_short_description( wp_kses_post( wp_unslash( $_POST['short_description'] ) ) );
			$updated[] = 'short_description';
		}
		// Main description.
		if ( isset( $_POST['description'] ) ) {
			$product->set_description( wp_kses_post( wp_unslash( $_POST['description'] ) ) );
			$updated[] = 'description';
		}

		// Featured image — accept URL, convert to attachment ID.
		$feat_url = sanitize_url( wp_unslash( $_POST['featured_image_url'] ?? '' ) );
		if ( $feat_url ) {
			$att_id = self::url_to_attachment_id( $feat_url );
			if ( $att_id ) {
				$product->set_image_id( $att_id );
				$updated[] = 'featured_image';
			}
		}

		// Gallery URLs — comma separated or JSON array.
		$raw_gallery = sanitize_textarea_field( wp_unslash( $_POST['gallery_urls'] ?? '' ) );
		if ( $raw_gallery ) {
			$urls    = array_filter( array_map( 'trim', explode( "\n", str_replace( ',', "\n", $raw_gallery ) ) ) );
			$gal_ids = [];
			foreach ( $urls as $url ) {
				$att_id = self::url_to_attachment_id( sanitize_url( $url ) );
				if ( $att_id ) $gal_ids[] = $att_id;
			}
			if ( ! empty( $gal_ids ) ) {
				$product->set_gallery_image_ids( $gal_ids );
				$updated[] = 'gallery';
			}
		}

		$product->save();
		wp_send_json_success( [ 'updated' => $updated, 'message' => 'Product saved! (' . implode( ', ', $updated ) . ')' ] );
	}

	// =========================================================================
	// BULK EDIT — Multiple products (v3.0.4)
	// =========================================================================

	/**
	 * AJAX: Apply bulk edit fields to multiple products at once.
	 * Only fields with non-empty values are applied.
	 */
	public static function ajax_bulk_edit() {
		self::verify();
		$ids = self::get_ids();

		// Which fields to update (only non-empty ones).
		$name_prefix     = sanitize_text_field( wp_unslash( $_POST['name_prefix']     ?? '' ) );
		$name_suffix     = sanitize_text_field( wp_unslash( $_POST['name_suffix']     ?? '' ) );
		$short_desc      = wp_kses_post( wp_unslash( $_POST['short_description']      ?? '' ) );
		$feat_url        = sanitize_url( wp_unslash( $_POST['featured_image_url']     ?? '' ) );
		$gallery_raw     = sanitize_textarea_field( wp_unslash( $_POST['gallery_urls']?? '' ) );
		$desc_append     = wp_kses_post( wp_unslash( $_POST['desc_append']            ?? '' ) );

		// Pre-resolve image IDs (same for all products = 1 lookup).
		$feat_id = $feat_url ? self::url_to_attachment_id( $feat_url ) : 0;

		$gal_ids = [];
		if ( $gallery_raw ) {
			$urls = array_filter( array_map( 'trim', explode( "\n", str_replace( ',', "\n", $gallery_raw ) ) ) );
			foreach ( $urls as $url ) {
				$aid = self::url_to_attachment_id( sanitize_url( $url ) );
				if ( $aid ) $gal_ids[] = $aid;
			}
		}

		$updated = 0;
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) continue;
			$changed = false;

			if ( $name_prefix || $name_suffix ) {
				$product->set_name( $name_prefix . $product->get_name() . $name_suffix );
				$changed = true;
			}
			if ( $short_desc ) {
				$product->set_short_description( $short_desc );
				$changed = true;
			}
			if ( $desc_append ) {
				$product->set_description( $product->get_description() . "\n" . $desc_append );
				$changed = true;
			}
			if ( $feat_id ) {
				$product->set_image_id( $feat_id );
				$changed = true;
			}
			if ( ! empty( $gal_ids ) ) {
				$product->set_gallery_image_ids( $gal_ids );
				$changed = true;
			}

			if ( $changed ) {
				$product->save();
				$updated++;
			}
		}

		wp_send_json_success( [ 'updated' => $updated, 'message' => "$updated product(s) updated." ] );
	}

	// =========================================================================
	// HELPER: URL → Attachment ID
	// =========================================================================

	/**
	 * Convert a media URL to a WP attachment ID.
	 * Works for images already in the Media Library.
	 *
	 * @param  string $url Full URL.
	 * @return int    Attachment ID or 0.
	 */
	private static function url_to_attachment_id( $url ) {
		if ( ! $url ) return 0;
		// Strip query string.
		$url = preg_replace( '/\?.*/', '', $url );
		// Try native WP function first (WP 6.4+).
		if ( function_exists( 'attachment_url_to_postid' ) ) {
			$id = attachment_url_to_postid( $url );
			if ( $id ) return $id;
		}
		// Fallback: DB lookup by guid.
		global $wpdb;
		$id = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT ID FROM {$wpdb->posts} WHERE guid = %s AND post_type = 'attachment' LIMIT 1",
			$url
		) );
		return (int) $id;
	}
}

SGC_PM_Bulk_Actions::register_ajax();
