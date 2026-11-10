<?php
defined( 'ABSPATH' ) || exit;

class SGC_PM_Product_Tools {

	public static function register_ajax() {
		add_action( 'wp_ajax_sgc_pm_duplicate_product', [ __CLASS__, 'ajax_duplicate' ] );
		add_action( 'wp_ajax_sgc_pm_create_variants',   [ __CLASS__, 'ajax_create_variants' ] );
	}

	private static function verify() {
		check_ajax_referer( 'sgc_pm_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) )
			wp_send_json_error( 'Unauthorized', 403 );
	}

	// ── Duplicate Product ────────────────────────────────────────────────
	public static function ajax_duplicate() {
		self::verify();
		$id = (int) ( $_POST['id'] ?? 0 );
		if ( ! $id ) wp_send_json_error( 'Invalid product ID.' );

		$original = wc_get_product( $id );
		if ( ! $original ) wp_send_json_error( 'Product not found.' );

		// Clone post
		$new_post_id = wp_insert_post([
			'post_title'   => $original->get_name() . ' (Copy)',
			'post_content' => get_post_field( 'post_content', $id ),
			'post_excerpt' => get_post_field( 'post_excerpt', $id ),
			'post_status'  => 'draft',
			'post_type'    => 'product',
			'post_author'  => get_current_user_id(),
		]);

		if ( is_wp_error( $new_post_id ) ) wp_send_json_error( $new_post_id->get_error_message() );

		// Copy all meta
		global $wpdb;
		$metas = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
			"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d",
			$id
		));
		foreach ( $metas as $meta ) {
			if ( '_thumbnail_id' !== $meta->meta_key ) {
				add_post_meta( $new_post_id, $meta->meta_key, maybe_unserialize( $meta->meta_value ) );
			}
		}

		// Clear SKU on copy — avoid duplicate
		update_post_meta( $new_post_id, '_sku', '' );

		// Copy taxonomies
		$taxonomies = get_object_taxonomies( 'product' );
		foreach ( $taxonomies as $tax ) {
			$terms = wp_get_object_terms( $id, $tax, [ 'fields' => 'ids' ] );
			if ( ! is_wp_error( $terms ) ) wp_set_object_terms( $new_post_id, $terms, $tax );
		}

		wc_delete_product_transients( $new_post_id );

		wp_send_json_success([
			'new_id'   => $new_post_id,
			'edit_url' => get_edit_post_link( $new_post_id, 'raw' ),
			'message'  => 'Product duplicated as Draft. SKU cleared — set a new one.',
		]);
	}

	// ── Variant Creator ──────────────────────────────────────────────────
	// Creates multiple size/variant copies of a base product
	public static function ajax_create_variants() {
		self::verify();

		$base_id  = (int) ( $_POST['base_id'] ?? 0 );
		$variants = isset( $_POST['variants'] ) ? (array) $_POST['variants'] : [];

		if ( ! $base_id || empty( $variants ) ) wp_send_json_error( 'Base product and variants required.' );

		$original = wc_get_product( $base_id );
		if ( ! $original ) wp_send_json_error( 'Base product not found.' );

		$created  = [];
		$base_name = $original->get_name();
		// Strip existing size pattern if any: "Beaker 500ml" → "Beaker"
		$base_clean = preg_replace( '/\s+\d+\s*(ml|ML|L|g|kg|mm|cm|oz|pcs|pc)\b.*/', '', $base_name );

		global $wpdb;
		$metas = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
			"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d",
			$base_id
		));
		$taxonomies = get_object_taxonomies( 'product' );

		foreach ( $variants as $variant ) {
			$suffix = sanitize_text_field( $variant['label'] ?? '' );
			$price  = sanitize_text_field( $variant['price'] ?? '' );
			$sku    = sanitize_text_field( $variant['sku'] ?? '' );
			if ( ! $suffix ) continue;

			$new_id = wp_insert_post([
				'post_title'   => trim( $base_clean . ' ' . $suffix ),
				'post_content' => get_post_field( 'post_content', $base_id ),
				'post_excerpt' => get_post_field( 'post_excerpt', $base_id ),
				'post_status'  => 'draft',
				'post_type'    => 'product',
				'post_author'  => get_current_user_id(),
			]);
			if ( is_wp_error( $new_id ) ) continue;

			// Copy meta
			foreach ( $metas as $meta ) {
				if ( ! in_array( $meta->meta_key, [ '_thumbnail_id', '_sku', '_regular_price', '_sale_price' ], true ) ) {
					add_post_meta( $new_id, $meta->meta_key, maybe_unserialize( $meta->meta_value ) );
				}
			}

			// Set variant-specific fields
			$product = wc_get_product( $new_id );
			if ( $price ) $product->set_regular_price( wc_format_decimal( $price ) );
			if ( $sku )   $product->set_sku( $sku );
			$product->save();

			// Copy taxonomies
			foreach ( $taxonomies as $tax ) {
				$terms = wp_get_object_terms( $base_id, $tax, [ 'fields' => 'ids' ] );
				if ( ! is_wp_error( $terms ) ) wp_set_object_terms( $new_id, $terms, $tax );
			}

			wc_delete_product_transients( $new_id );
			$created[] = [
				'id'       => $new_id,
				'name'     => trim( $base_clean . ' ' . $suffix ),
				'edit_url' => get_edit_post_link( $new_id, 'raw' ),
			];
		}

		wp_send_json_success([
			'created' => $created,
			'count'   => count( $created ),
			'message' => count( $created ) . ' variant(s) created as Draft.',
		]);
	}
}

SGC_PM_Product_Tools::register_ajax();
