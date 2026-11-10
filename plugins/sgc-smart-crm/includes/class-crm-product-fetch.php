<?php
/**
 * SGC Smart CRM Pro — Product Fetch
 *
 * Fetches WooCommerce products for promo email generation.
 * Supports local WC queries and REST API remote fetch.
 *
 * @package SGC_Smart_CRM
 * @since   1.0.0
 * @version   1.0.4 | Build: 20260516-0630
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SGC_CRM_Product_Fetch
 *
 * @since 1.0.0
 * @version   1.0.4 | Build: 20260516-0630
 */
class SGC_CRM_Product_Fetch {

	/**
	 * Register AJAX handlers.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function register_ajax() {
		add_action( 'wp_ajax_sgc_crm_get_products',       array( __CLASS__, 'ajax_get_products' ) );
		add_action( 'wp_ajax_sgc_crm_build_product_promo', array( __CLASS__, 'ajax_build_promo' ) );
	}

	/**
	 * Verify nonce and capability.
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

	/**
	 * Get products for weekly promo. Rotates weekly between types.
	 *
	 * @since  1.0.0
	 * @param  int $limit Number of products.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_promo_products( $limit = 6 ) {
		if ( class_exists( 'WooCommerce' ) ) {
			return self::get_local_products( $limit );
		}
		return array();
	}

	/**
	 * Fetch from local WooCommerce installation.
	 *
	 * @since  1.0.0
	 * @param  int    $limit    Max products.
	 * @param  string $category Category slug (empty = all).
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_local_products( $limit, $category = '' ) {
		$week  = (int) gmdate( 'W' );
		$types = array( 'featured', 'sale', 'latest', 'random' );
		$type  = $types[ $week % 4 ];

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit * 2,
			'orderby'        => 'rand',
			'fields'         => 'ids',
		);

		if ( $category ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => $category,
				),
			);
		}

		switch ( $type ) {
			case 'featured':
				$args['tax_query'][] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					'taxonomy' => 'product_visibility',
					'field'    => 'name',
					'terms'    => 'featured',
				);
				break;
			case 'sale':
				$sale_ids = array_slice( wc_get_product_ids_on_sale(), 0, 50 );
				if ( ! empty( $sale_ids ) ) {
					$args['post__in'] = $sale_ids;
				}
				break;
			case 'latest':
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
				break;
		}

		$ids     = get_posts( $args );
		$results = array();

		foreach ( $ids as $pid ) {
			$product = wc_get_product( $pid );
			if ( ! $product ) {
				continue;
			}

			$results[] = array(
				'id'         => (int) $pid,
				'name'       => $product->get_name(),
				'price'      => wc_price( $product->get_price() ),
				'price_raw'  => (float) $product->get_price(),
				'url'        => get_permalink( $pid ),
				'sku'        => $product->get_sku(),
				'image'      => get_the_post_thumbnail_url( $pid, 'medium' ) ?: '',
				'category'   => implode( ', ', wp_list_pluck( wp_get_post_terms( $pid, 'product_cat' ), 'name' ) ),
				'short_desc' => wp_strip_all_tags( $product->get_short_description() ),
			);

			if ( count( $results ) >= $limit ) {
				break;
			}
		}

		return $results;
	}

	/**
	 * Get WooCommerce product categories.
	 *
	 * @since  1.0.0
	 * @return array<int, object>|\WP_Term[]
	 */
	public static function get_categories() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}
		return get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'number'     => 50,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		) ?: array();
	}

	/**
	 * AJAX — get products for promo builder UI.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_get_products() {
		self::verify();

		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_send_json_error( esc_html__( 'WooCommerce not active.', 'sgc-smart-crm' ) );
		}

		$limit    = min( 24, max( 1, (int) ( $_POST['limit']    ?? 12 ) ) );
		$category = sanitize_key( wp_unslash( $_POST['category'] ?? '' ) );

		$products = self::get_local_products( $limit, $category );
		wp_send_json_success( $products );
	}

	/**
	 * AJAX — build AI promo from selected product IDs.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_build_promo() {
		self::verify();

		$ids_raw  = sanitize_text_field( wp_unslash( $_POST['product_ids'] ?? '' ) );
		$tone     = sanitize_text_field( wp_unslash( $_POST['tone']        ?? 'professional' ) );
		$segment  = sanitize_key( wp_unslash( $_POST['segment']            ?? 'all' ) );
		$ids      = array_filter( array_map( 'intval', explode( ',', $ids_raw ) ) );

		if ( empty( $ids ) ) {
			wp_send_json_error( esc_html__( 'No products selected.', 'sgc-smart-crm' ) );
		}

		$info = array();
		foreach ( $ids as $pid ) {
			$product = wc_get_product( $pid );
			if ( ! $product ) {
				continue;
			}
			$info[] = array(
				'name'  => $product->get_name(),
				'price' => wp_strip_all_tags( wc_price( $product->get_price() ) ),
				'sku'   => $product->get_sku(),
				'url'   => get_permalink( $pid ),
				'image' => get_the_post_thumbnail_url( $pid, 'medium' ) ?: '',
			);
		}

		if ( empty( $info ) ) {
			wp_send_json_error( esc_html__( 'No valid products found.', 'sgc-smart-crm' ) );
		}

		$prompt = "Create a B2B product promo email for SGC LABS® (scientific lab glassware manufacturer, Ambala, India).\n"
			. "Products:\n" . wp_json_encode( $info ) . "\n"
			. "Segment: {$segment}\nTone: {$tone}\n"
			. "Include: product highlights, pricing, bulk discount mention, CTA to labsglass.com\n"
			. "Return JSON: {\"subject\":\"...\",\"preheader\":\"...\",\"body_html\":\"<full responsive HTML with inline styles>\"}";

		$response = SGC_CRM_AI::call( $prompt, 2000, 'B2B email writer. Return only valid JSON.' );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}

		$clean  = preg_replace( '/```(?:json)?|```/', '', $response );
		$result = json_decode( trim( $clean ), true );

		if ( ! is_array( $result ) || empty( $result['subject'] ) ) {
			wp_send_json_error( esc_html__( 'AI returned unexpected format. Please try again.', 'sgc-smart-crm' ) );
		}

		wp_send_json_success( $result );
	}
}

SGC_CRM_Product_Fetch::register_ajax();
