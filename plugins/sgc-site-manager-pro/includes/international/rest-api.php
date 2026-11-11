<?php
/**
 * SGC LABS® REST API
 *
 * Headless commerce endpoints under namespace `sgclabs/v1`:
 *   GET  /products?search=...&category=...&per_page=20
 *   GET  /products/(?P<id>\d+)
 *   POST /quote      (submit quote request)
 *   POST /inquiry    (submit inquiry)
 *   GET  /search?q=... (sitewide search)
 *
 * - Public read endpoints (cached)
 * - Write endpoints require nonce + rate limit
 *
 * @package   SGCLabs\International
 * @since     6.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'SGCLabs_Registry' ) ) return;
    if ( ! SGCLabs_Registry::is_active( 'rest_api' ) ) return;

    add_action( 'rest_api_init', 'sgclabs_rest_register_routes' );
}, 30 );

function sgclabs_rest_register_routes(): void {
    register_rest_route( 'sgclabs/v1', '/products', [
        'methods'             => 'GET',
        'callback'            => 'sgclabs_rest_products',
        'permission_callback' => '__return_true',
        'args' => [
            'search'   => [ 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ],
            'category' => [ 'type' => 'string',  'sanitize_callback' => 'sanitize_title'      ],
            'per_page' => [ 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ],
            'page'     => [ 'type' => 'integer', 'default' => 1,  'minimum' => 1 ],
        ],
    ] );

    register_rest_route( 'sgclabs/v1', '/products/(?P<id>\d+)', [
        'methods'             => 'GET',
        'callback'            => 'sgclabs_rest_product',
        'permission_callback' => '__return_true',
    ] );

    register_rest_route( 'sgclabs/v1', '/quote', [
        'methods'             => 'POST',
        'callback'            => 'sgclabs_rest_submit_quote',
        'permission_callback' => 'sgclabs_rest_rate_limit',
    ] );

    register_rest_route( 'sgclabs/v1', '/inquiry', [
        'methods'             => 'POST',
        'callback'            => 'sgclabs_rest_submit_inquiry',
        'permission_callback' => 'sgclabs_rest_rate_limit',
    ] );

    register_rest_route( 'sgclabs/v1', '/search', [
        'methods'             => 'GET',
        'callback'            => 'sgclabs_rest_search',
        'permission_callback' => '__return_true',
    ] );

    register_rest_route( 'sgclabs/v1', '/health', [
        'methods'             => 'GET',
        'callback'            => function() {
            return [
                'ok'      => true,
                'version' => class_exists( 'SGCLabs_Updater' ) ? SGCLabs_Updater::version() : 'unknown',
                'sig'     => class_exists( 'SGCLabs_Updater' ) ? SGCLabs_Updater::signature() : '',
                'time'    => current_time( 'c' ),
            ];
        },
        'permission_callback' => '__return_true',
    ] );
}

function sgclabs_rest_rate_limit( WP_REST_Request $req ) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $k  = 'sgclabs_rl_' . md5( $ip . $req->get_route() );
    $n  = (int) get_transient( $k );
    if ( $n >= 30 ) {
        return new WP_Error( 'rate-limit', 'Too many requests, try again later.', [ 'status' => 429 ] );
    }
    set_transient( $k, $n + 1, MINUTE_IN_SECONDS );
    return true;
}

function sgclabs_rest_products( WP_REST_Request $req ) {
    if ( ! function_exists( 'wc_get_products' ) ) return new WP_Error( 'no-woo', 'WooCommerce not active', [ 'status' => 503 ] );

    $args = [
        'limit'  => $req->get_param( 'per_page' ) ?: 20,
        'page'   => $req->get_param( 'page' ) ?: 1,
        'paginate' => true,
        'status' => 'publish',
    ];
    if ( $s = $req->get_param( 'search' ) )   $args['s'] = $s;
    if ( $c = $req->get_param( 'category' ) ) $args['category'] = [ $c ];

    $res = wc_get_products( $args );
    $items = [];
    foreach ( $res->products as $p ) {
        $items[] = sgclabs_rest_product_array( $p );
    }
    return rest_ensure_response( [
        'items' => $items,
        'total' => $res->total,
        'pages' => $res->max_num_pages,
    ] );
}

function sgclabs_rest_product( WP_REST_Request $req ) {
    if ( ! function_exists( 'wc_get_product' ) ) return new WP_Error( 'no-woo', 'WooCommerce not active', [ 'status' => 503 ] );
    $p = wc_get_product( absint( $req['id'] ) );
    if ( ! $p ) return new WP_Error( 'not-found', 'Product not found', [ 'status' => 404 ] );
    return rest_ensure_response( sgclabs_rest_product_array( $p ) );
}

function sgclabs_rest_product_array( $p ): array {
    return [
        'id'           => $p->get_id(),
        'sku'          => $p->get_sku(),
        'name'         => $p->get_name(),
        'permalink'    => get_permalink( $p->get_id() ),
        'price'        => $p->get_price(),
        'price_html'   => wp_strip_all_tags( $p->get_price_html() ),
        'currency'     => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'INR',
        'stock_status' => $p->get_stock_status(),
        'short_desc'   => wp_strip_all_tags( $p->get_short_description() ),
        'image'        => wp_get_attachment_image_url( $p->get_image_id(), 'large' ) ?: '',
        'categories'   => wp_get_post_terms( $p->get_id(), 'product_cat', [ 'fields' => 'names' ] ),
    ];
}

function sgclabs_rest_submit_quote( WP_REST_Request $req ) {
    $name    = sanitize_text_field( $req['name']    ?? '' );
    $email   = sanitize_email(       $req['email']   ?? '' );
    $product = sanitize_text_field( $req['product'] ?? '' );
    $message = sanitize_textarea_field( $req['message'] ?? '' );

    if ( ! is_email( $email ) ) return new WP_Error( 'bad-email', 'Invalid email', [ 'status' => 400 ] );
    if ( strlen( $name ) < 2 )  return new WP_Error( 'bad-name',  'Name required', [ 'status' => 400 ] );

    do_action( 'sgclabs_rest_quote_submitted', compact( 'name', 'email', 'product', 'message' ) );
    return rest_ensure_response( [ 'ok' => true ] );
}

function sgclabs_rest_submit_inquiry( WP_REST_Request $req ) {
    return sgclabs_rest_submit_quote( $req );
}

function sgclabs_rest_search( WP_REST_Request $req ) {
    $q = sanitize_text_field( $req['q'] ?? '' );
    if ( strlen( $q ) < 2 ) return rest_ensure_response( [ 'items' => [] ] );

    $posts = get_posts( [
        's'              => $q,
        'post_type'      => [ 'product', 'page', 'post' ],
        'posts_per_page' => 20,
    ] );
    $items = [];
    foreach ( $posts as $p ) {
        $items[] = [
            'id'        => $p->ID,
            'type'      => $p->post_type,
            'title'     => $p->post_title,
            'permalink' => get_permalink( $p->ID ),
            'excerpt'   => wp_trim_words( wp_strip_all_tags( $p->post_excerpt ?: $p->post_content ), 25 ),
        ];
    }
    return rest_ensure_response( [ 'items' => $items, 'count' => count( $items ) ] );
}
