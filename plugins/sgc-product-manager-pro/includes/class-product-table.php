<?php
defined( 'ABSPATH' ) || exit;

class SGC_PM_Product_Table {

	public static function register_ajax() {
		add_action( 'wp_ajax_sgc_pm_get_products',    [ __CLASS__, 'ajax_get_products' ] );
		add_action( 'wp_ajax_sgc_pm_inline_save',     [ __CLASS__, 'ajax_inline_save' ] );
		add_action( 'wp_ajax_sgc_pm_get_categories',  [ __CLASS__, 'ajax_get_categories' ] );
	}

	private static function verify() {
		check_ajax_referer( 'sgc_pm_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) )
			wp_send_json_error( 'Unauthorized', 403 );
	}

	public static function ajax_get_products() {
		self::verify();

		$page     = max( 1, (int) ( $_POST['page'] ?? 1 ) );
		$per      = 100;
		$search   = sanitize_text_field( $_POST['search']   ?? '' );
		$cat      = (int) ( $_POST['cat'] ?? 0 );
		$status   = sanitize_key( $_POST['status']  ?? '' );
		$stock    = sanitize_key( $_POST['stock']   ?? '' );
		$type     = sanitize_key( $_POST['ptype']   ?? '' );
		$no_image = (bool) ( $_POST['no_image'] ?? false );

		$args = [
			'post_type'      => 'product',
			'post_status'    => $status ? [ $status ] : [ 'publish', 'draft', 'private', 'pending' ],
			'posts_per_page' => $per,
			'paged'          => $page,
			'orderby'        => sanitize_key( $_POST['orderby'] ?? 'date' ),
			'order'          => 'ASC' === strtoupper( $_POST['order'] ?? 'DESC' ) ? 'ASC' : 'DESC',
		];

		if ( $search ) {
			$args['s'] = $search;
		}

		if ( $cat ) {
			$args['tax_query'] = [[
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => $cat,
			]];
		}

		if ( $type ) {
			$args['tax_query'] = $args['tax_query'] ?? [];
			$args['tax_query'][] = [
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => $type,
			];
		}

		if ( $stock ) {
			$args['meta_query'] = [[
				'key'   => '_stock_status',
				'value' => $stock,
			]];
		}

		if ( $no_image ) {
			$args['meta_query'] = $args['meta_query'] ?? [];
			$args['meta_query'][] = [
				'relation' => 'OR',
				[ 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ],
				[ 'key' => '_thumbnail_id', 'value' => '', 'compare' => '=' ],
			];
		}

		$query    = new WP_Query( $args );
		$products = [];

		foreach ( $query->posts as $post ) {
			$product = wc_get_product( $post->ID );
			if ( ! $product ) continue;

			$cats = wp_get_post_terms( $post->ID, 'product_cat', [ 'fields' => 'names' ] );
			$img  = get_post_thumbnail_id( $post->ID );

			$products[] = [
				'id'           => $post->ID,
				'name'         => $post->post_title,
				'sku'          => $product->get_sku(),
				'price'        => $product->get_regular_price(),
				'sale_price'   => $product->get_sale_price(),
				'stock'        => $product->get_stock_quantity(),
				'stock_status' => $product->get_stock_status(),
				'status'       => $post->post_status,
				'type'         => $product->get_type(),
				'categories'   => implode( ', ', is_array( $cats ) ? $cats : [] ),
				'has_image'    => ! empty( $img ),
				'edit_url'     => get_edit_post_link( $post->ID, 'raw' ),
				'view_url'     => get_permalink( $post->ID ),
			];
		}

		wp_send_json_success([
			'products' => $products,
			'total'    => $query->found_posts,
			'pages'    => $query->max_num_pages,
			'page'     => $page,
		]);
	}

	public static function ajax_inline_save() {
		self::verify();

		$id    = (int) ( $_POST['id'] ?? 0 );
		$field = sanitize_key( $_POST['field'] ?? '' );
		$value = sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) );

		if ( ! $id || ! $field ) wp_send_json_error( 'Invalid params.' );

		$product = wc_get_product( $id );
		if ( ! $product ) wp_send_json_error( 'Product not found.' );

		$allowed_fields = [ 'name', 'sku', 'regular_price', 'sale_price', 'stock_quantity', 'stock_status', 'status',
			'hsn_code', 'export_price_gulf', 'export_price_africa', 'export_price_sea', 'export_price_europe' ]; // v3.0.7
		if ( ! in_array( $field, $allowed_fields, true ) ) wp_send_json_error( 'Field not editable.' );

		switch ( $field ) {
			case 'name':
				wp_update_post( [ 'ID' => $id, 'post_title' => sanitize_text_field( $value ) ] );
				break;
			case 'sku':
				$product->set_sku( sanitize_text_field( $value ) );
				$product->save();
				break;
			case 'regular_price':
				$product->set_regular_price( wc_format_decimal( $value ) );
				$product->save();
				break;
			case 'sale_price':
				$product->set_sale_price( '' === $value ? '' : wc_format_decimal( $value ) );
				$product->save();
				break;
			case 'stock_quantity':
				$product->set_manage_stock( true );
				$product->set_stock_quantity( (int) $value );
				$product->save();
				break;
			case 'stock_status':
				$product->set_stock_status( in_array( $value, [ 'instock', 'outofstock', 'onbackorder' ], true ) ? $value : 'instock' );
				$product->save();
				break;
			case 'status':
				wp_update_post( [ 'ID' => $id, 'post_status' => sanitize_key( $value ) ] );
				break;
			// v3.0.7: GST + Country pricing meta fields
			case 'hsn_code':
				$hsn = preg_replace( '/[^0-9]/', '', $value );
				update_post_meta( $id, '_sgc_hsn_code', $hsn );
				break;
			case 'export_price_gulf':
			case 'export_price_africa':
			case 'export_price_sea':
			case 'export_price_europe':
				update_post_meta( $id, '_sgc_' . $field, wc_format_decimal( $value ) );
				break;
		}

		wc_delete_product_transients( $id );
		wp_send_json_success( [ 'id' => $id, 'field' => $field, 'value' => $value ] );
	}

	public static function ajax_get_categories() {
		self::verify();
		$cats = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name' ] );
		$out  = [];
		foreach ( $cats as $cat ) {
			$out[] = [ 'id' => $cat->term_id, 'name' => $cat->name, 'count' => $cat->count ];
		}
		wp_send_json_success( $out );
	}
}

SGC_PM_Product_Table::register_ajax();
