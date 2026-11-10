<?php
/**
 * SGC LABS® — Unified SEO API
 *
 * After v6.9.3, every plugin module that touches an SEO field MUST call
 * `sgclabs_seo_get()` / `sgclabs_seo_set()` instead of `update_post_meta()`
 * with a hand-crafted key. This:
 *
 *   1. Routes through the canonical key registry (no more divergent storage)
 *   2. Records `source` (manual / ai / imported / migrated) for hybrid migrations
 *   3. Fires `sgclabs_seo_field_updated` so other modules (cache, schema,
 *      sitemap, frontend) can react in real time
 *   4. Mirrors `short_desc` / `main_desc` to native WP fields when set in English,
 *      so WooCommerce / themes that read `post_excerpt` and `post_content`
 *      stay in sync without any extra glue code
 *
 * @package SGCLabs\Core
 * @since   6.9.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'sgclabs_seo_get' ) ) :
	/**
	 * Read a canonical SEO field for a post.
	 *
	 * If the canonical key has no value yet but a legacy key holds one,
	 * this returns the legacy value. Pair with `sgclabs_seo_set()` to
	 * lazily migrate values forward.
	 *
	 * @param int    $post_id Target post.
	 * @param string $field   Logical field id (e.g. 'seo_title').
	 * @param string $lang    ISO code or '' for default.
	 * @return string         Empty string if not set.
	 */
	function sgclabs_seo_get( int $post_id, string $field, string $lang = '' ): string {
		if ( $post_id <= 0 || ! class_exists( 'SGCLabs_SEO_Fields' ) ) {
			return '';
		}
		$key = SGCLabs_SEO_Fields::key( $field, $lang );
		if ( ! $key ) {
			return '';
		}

		// 1. Canonical read.
		$value = get_post_meta( $post_id, $key, true );
		if ( $value !== '' && $value !== false && $value !== null ) {
			return is_string( $value ) ? $value : (string) $value;
		}

		// 2. Legacy fallback — only for the default language (legacy keys
		//    didn't carry a language suffix).
		if ( $lang === '' || $lang === 'en' ) {
			$map = SGCLabs_SEO_Fields::map();
			foreach ( $map[ $field ]['legacy'] ?? [] as $legacy_key ) {
				$legacy_value = get_post_meta( $post_id, $legacy_key, true );
				if ( $legacy_value !== '' && $legacy_value !== false && $legacy_value !== null ) {
					return is_string( $legacy_value ) ? $legacy_value : (string) $legacy_value;
				}
			}

			// 3. Native WP field mirror for short_desc / main_desc.
			$mirror = $map[ $field ]['mirrors'] ?? '';
			if ( $mirror === 'post_excerpt' ) {
				$post = get_post( $post_id );
				if ( $post ) {
					return (string) $post->post_excerpt;
				}
			} elseif ( $mirror === 'post_content' ) {
				$post = get_post( $post_id );
				if ( $post ) {
					return (string) $post->post_content;
				}
			}
		}

		return '';
	}
endif;

if ( ! function_exists( 'sgclabs_seo_set' ) ) :
	/**
	 * Write a canonical SEO field for a post.
	 *
	 * @param int    $post_id Target post.
	 * @param string $field   Logical field id.
	 * @param string $value   New value (already sanitised — caller's job).
	 * @param string $source  manual | ai | imported | migrated. Default 'manual'.
	 * @param string $lang    ISO code or '' for default.
	 * @return bool           True if anything actually changed.
	 */
	function sgclabs_seo_set( int $post_id, string $field, string $value, string $source = 'manual', string $lang = '' ): bool {
		if ( $post_id <= 0 || ! class_exists( 'SGCLabs_SEO_Fields' ) ) {
			return false;
		}
		$key = SGCLabs_SEO_Fields::key( $field, $lang );
		if ( ! $key ) {
			return false;
		}
		$source = in_array( $source, [ 'manual', 'ai', 'imported', 'migrated' ], true )
			? $source
			: 'manual';

		$old = get_post_meta( $post_id, $key, true );
		if ( $old === $value ) {
			// Touch the source key so audit trail still updates.
			$src_key = SGCLabs_SEO_Fields::source_key( $field, $lang );
			if ( $src_key ) {
				update_post_meta( $post_id, $src_key, $source );
			}
			return false;
		}

		update_post_meta( $post_id, $key, $value );

		$src_key = SGCLabs_SEO_Fields::source_key( $field, $lang );
		if ( $src_key ) {
			update_post_meta( $post_id, $src_key, $source );
		}

		// Mirror to native WP fields when writing English short/main desc.
		if ( $lang === '' || $lang === 'en' ) {
			$map    = SGCLabs_SEO_Fields::map();
			$mirror = $map[ $field ]['mirrors'] ?? '';
			if ( $mirror === 'post_excerpt' ) {
				wp_update_post( [ 'ID' => $post_id, 'post_excerpt' => $value ] );
			} elseif ( $mirror === 'post_content' ) {
				wp_update_post( [ 'ID' => $post_id, 'post_content' => $value ] );
			}
		}

		/**
		 * Fires after an SEO field changes. Listeners include cache busters,
		 * schema regenerators, the sitemap pinger, etc.
		 *
		 * @param int    $post_id Post id.
		 * @param string $field   Logical field id.
		 * @param mixed  $old     Previous value.
		 * @param string $value   New value.
		 * @param string $source  manual | ai | imported | migrated.
		 * @param string $lang    Language code.
		 */
		do_action( 'sgclabs_seo_field_updated', $post_id, $field, $old, $value, $source, $lang );
		return true;
	}
endif;

if ( ! function_exists( 'sgclabs_seo_source' ) ) :
	/**
	 * Get the source ('manual'|'ai'|'imported'|'migrated'|'') of a canonical field.
	 */
	function sgclabs_seo_source( int $post_id, string $field, string $lang = '' ): string {
		if ( ! class_exists( 'SGCLabs_SEO_Fields' ) ) {
			return '';
		}
		$src_key = SGCLabs_SEO_Fields::source_key( $field, $lang );
		if ( ! $src_key ) {
			return '';
		}
		$v = get_post_meta( $post_id, $src_key, true );
		return is_string( $v ) ? $v : '';
	}
endif;

if ( ! function_exists( 'sgclabs_seo_delete' ) ) :
	/**
	 * Delete a canonical field (and its source companion).
	 */
	function sgclabs_seo_delete( int $post_id, string $field, string $lang = '' ): bool {
		if ( ! class_exists( 'SGCLabs_SEO_Fields' ) ) {
			return false;
		}
		$key     = SGCLabs_SEO_Fields::key( $field, $lang );
		$src_key = SGCLabs_SEO_Fields::source_key( $field, $lang );
		if ( ! $key ) {
			return false;
		}
		$ok = delete_post_meta( $post_id, $key );
		if ( $src_key ) {
			delete_post_meta( $post_id, $src_key );
		}
		return (bool) $ok;
	}
endif;

if ( ! function_exists( 'sgclabs_seo_get_all_languages' ) ) :
	/**
	 * Fetch every translation of a multilingual field for a post in one call.
	 *
	 * @return array<string,string> Lang code → value (only languages that have content).
	 */
	function sgclabs_seo_get_all_languages( int $post_id, string $field ): array {
		if ( ! class_exists( 'SGCLabs_SEO_Fields' ) ) {
			return [];
		}
		$out = [];
		foreach ( SGCLabs_SEO_Fields::languages() as $code => $cfg ) {
			$value = sgclabs_seo_get( $post_id, $field, $code );
			if ( $value !== '' ) {
				$out[ $code ] = $value;
			}
		}
		return $out;
	}
endif;
