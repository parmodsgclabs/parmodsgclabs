<?php
/**
 * SGC LABS® — <head> SEO tag de-duplicator (v7.5.1)
 *
 * Several Site Manager modules (SEO metabox, Woo Local SEO, Export SEO Engine,
 * Multilingual SEO, Multisite Profiles, Schema Engine, Lab Tech Specs …) each
 * print their own canonical / Open Graph / hreflang / Product JSON-LD. On a
 * product page that produced 2× canonical, 4× og:title, 14 hreflang and 3×
 * Product schema blocks — Google ignores conflicting canonicals and flags
 * duplicate structured data. This module buffers wp_head output and keeps
 * exactly one of each, with these rules:
 *
 *   - <link rel="canonical">            → last one wins (per-product custom canonical from the SEO metabox)
 *   - <meta name="description">,
 *     og:* / twitter:* / article:* meta → first one wins (SEO metabox prints earliest)
 *   - <link rel="alternate" hreflang>   → one per language code
 *   - JSON-LD Product blocks            → merged into ONE block (first value wins per key,
 *                                         so ratings/specs from other modules are kept, not lost)
 *   - WooCommerce's own Product JSON-LD (wp_footer) is suppressed once we have emitted one.
 */
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'sgclabs_head_dedupe_start' ) ) :

/**
 * Current WC_Product, safe inside wp_head on classic themes.
 * Before the loop WordPress registers the `product` query var as a GLOBAL STRING (the slug),
 * so `global $product; $product->get_id()` is a fatal error there. (v7.5.4)
 */
function sgclabs_current_product() {
	global $product;
	if ( $product instanceof WC_Product ) return $product;
	if ( ! function_exists( 'wc_get_product' ) ) return null;
	$id = get_queried_object_id();
	$p  = $id ? wc_get_product( $id ) : null;
	return $p instanceof WC_Product ? $p : null;
}

add_action( 'wp_head', 'sgclabs_head_dedupe_start', -10000 );
add_action( 'wp_head', 'sgclabs_head_dedupe_end', PHP_INT_MAX );

function sgclabs_head_dedupe_start(): void {
	if ( is_admin() || is_feed() || get_option( 'sgclabs_head_dedupe_active', '1' ) !== '1' ) return;
	$GLOBALS['sgclabs_head_dedupe_on'] = ob_start();
}

function sgclabs_head_dedupe_end(): void {
	if ( empty( $GLOBALS['sgclabs_head_dedupe_on'] ) ) return;
	$GLOBALS['sgclabs_head_dedupe_on'] = false;
	$html = ob_get_clean();
	if ( ! is_string( $html ) || $html === '' ) return;
	echo sgclabs_head_dedupe_html( $html ); // phpcs:ignore WordPress.Security.EscapeOutput -- already-rendered head markup
}

/**
 * De-duplicate SEO tags in rendered <head> markup. Pure function (also used by tests).
 */
function sgclabs_head_dedupe_html( string $html ): string {
	$seen_meta = [];
	$seen_lang = [];
	$canonicals = [];

	// 1. Canonical — collect, keep last.
	$html = preg_replace_callback( '#<link\s+[^>]*rel=["\']canonical["\'][^>]*>\s*#i', function ( $m ) use ( &$canonicals ) {
		$canonicals[] = $m[0];
		return "\x00CANON\x00";
	}, $html );
	if ( $canonicals ) {
		$seo_plugin = defined( 'RANK_MATH_VERSION' ) || defined( 'WPSEO_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' );
		$last = trim( $seo_plugin ? reset( $canonicals ) : end( $canonicals ) ) . "\n"; // SEO plugin's canonical (printed first) wins
		$first = true;
		$html = preg_replace_callback( '#\x00CANON\x00#', function () use ( &$first, $last ) {
			if ( $first ) { $first = false; return $last; }
			return '';
		}, $html );
	}

	// 2. description / og: / twitter: / article: / product: meta — keep first per key.
	$html = preg_replace_callback( '#<meta\s+[^>]*>\s*#i', function ( $m ) use ( &$seen_meta ) {
		$tag = $m[0];
		if ( ! preg_match( '#\b(?:property|name)=["\']([^"\']+)["\']#i', $tag, $k ) ) return $tag;
		$key = strtolower( $k[1] );
		if ( $key !== 'description' && ! preg_match( '#^(og|twitter|article|product|fb):#', $key ) ) return $tag;
		if ( in_array( $key, [ 'og:image', 'og:image:alt', 'og:image:width', 'og:image:height', 'og:locale:alternate', 'product:retailer_item_id' ], true ) ) return $tag; // repeatable
		if ( isset( $seen_meta[ $key ] ) ) return '';
		$seen_meta[ $key ] = true;
		return $tag;
	}, $html );

	// 3. hreflang — one per language code.
	$html = preg_replace_callback( '#<link\s+[^>]*hreflang=["\']([^"\']+)["\'][^>]*>\s*#i', function ( $m ) use ( &$seen_lang ) {
		$code = strtolower( $m[1] );
		if ( isset( $seen_lang[ $code ] ) ) return '';
		$seen_lang[ $code ] = true;
		return $m[0];
	}, $html );

	// 4. JSON-LD Product blocks — merge into one.
	$products = [];
	$html = preg_replace_callback( '#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>\s*#is', function ( $m ) use ( &$products ) {
		$data = json_decode( trim( $m[1] ), true );
		if ( ! is_array( $data ) ) return $m[0];
		$type = $data['@type'] ?? null;
		if ( is_array( $type ) ) $type = reset( $type );
		if ( 'Product' !== $type ) return $m[0];
		$products[] = $data;
		return "\x00PRODLD\x00";
	}, $html );
	if ( $products ) {
		$merged = [];
		foreach ( $products as $p ) {
			foreach ( $p as $k => $v ) {
				if ( ! array_key_exists( $k, $merged ) || $merged[ $k ] === '' || $merged[ $k ] === [] || $merged[ $k ] === null ) {
					$merged[ $k ] = $v;
				} elseif ( is_array( $merged[ $k ] ) && is_array( $v ) && ! isset( $v['@type'] ) && ! isset( $merged[ $k ]['@type'] ) && array_keys( $v ) === range( 0, count( $v ) - 1 ) ) {
					// plain lists (e.g. image[], additionalProperty[]) — union, de-duplicated
					$merged[ $k ] = array_values( array_unique( array_merge( $merged[ $k ], $v ), SORT_REGULAR ) );
				}
			}
		}
		$GLOBALS['sgclabs_head_product_schema_emitted'] = true;
		$block = '<script type="application/ld+json">' . wp_json_encode( $merged, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
		$first = true;
		$html = preg_replace_callback( '#\x00PRODLD\x00#', function () use ( &$first, $block ) {
			if ( $first ) { $first = false; return $block; }
			return '';
		}, $html );
	}

	return $html;
}

// WooCommerce prints its own Product JSON-LD in wp_footer — suppress it when we already emitted one.
add_filter( 'woocommerce_structured_data_product', function ( $markup ) {
	return ! empty( $GLOBALS['sgclabs_head_product_schema_emitted'] ) ? [] : $markup;
}, 99 );

endif;
