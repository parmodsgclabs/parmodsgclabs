<?php
/**
 * SGC LABS® — Legacy SEO Meta Bridge
 *
 * For backward compatibility, every legacy SEO meta key listed in
 * `SGCLabs_SEO_Fields::legacy_alias_map()` is transparently redirected to
 * its canonical key when read via `get_post_meta()`. This means every
 * pre-v6.9.3 module that still has hand-written meta lookups keeps working
 * while we migrate piece-by-piece in subsequent passes.
 *
 * Direction:
 *   - READ legacy key  →  if canonical has a value, return that
 *   - READ legacy key  →  else, return the legacy value as stored
 *   - WRITE legacy key →  ALSO write to canonical (so writes don't fork)
 *
 * Note: this filter is intentionally cheap. It only triggers for the small
 * fixed set of legacy SEO keys, never for every single meta read.
 *
 * @package SGCLabs\Core
 * @since   6.9.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SGCLabs_SEO_Legacy_Bridge' ) ) :

final class SGCLabs_SEO_Legacy_Bridge {

	/**
	 * Cached legacy → canonical key map.
	 *
	 * @var array<string,string>|null
	 */
	private static $alias_map = null;

	/** Per-request guard against filter recursion. */
	private static $in_read = [];
	private static $in_write = [];

	public static function boot(): void {
		add_filter( 'get_post_metadata', [ __CLASS__, 'on_read' ], 10, 4 );
		add_filter( 'update_post_metadata', [ __CLASS__, 'on_write' ], 10, 5 );
		add_filter( 'add_post_metadata', [ __CLASS__, 'on_add' ], 10, 5 );
	}

	private static function alias_map(): array {
		if ( self::$alias_map === null ) {
			self::$alias_map = class_exists( 'SGCLabs_SEO_Fields' )
				? SGCLabs_SEO_Fields::legacy_alias_map()
				: [];
		}
		return self::$alias_map;
	}

	/**
	 * Filter: get_post_metadata.
	 *
	 * @param mixed  $value     Default null (means "let WP read normally").
	 * @param int    $object_id Post ID.
	 * @param string $meta_key  Key being read.
	 * @param bool   $single    Return single value or array.
	 * @return mixed Override value, or null to fall through to normal read.
	 */
	public static function on_read( $value, $object_id, $meta_key, $single ) {
		// Only single-value reads of known legacy keys.
		if ( ! $single || ! is_string( $meta_key ) ) {
			return $value;
		}
		$map = self::alias_map();
		if ( ! isset( $map[ $meta_key ] ) ) {
			return $value;
		}
		// Recursion guard — when we read the canonical key below it will
		// re-enter this filter with `meta_key = canonical`, which is NOT in
		// the alias map so it falls through naturally. But we still guard
		// per-key to be safe.
		$guard_id = $object_id . '|' . $meta_key;
		if ( ! empty( self::$in_read[ $guard_id ] ) ) {
			return $value;
		}
		self::$in_read[ $guard_id ] = true;

		$canonical = $map[ $meta_key ];
		// Direct DB read of the canonical key (no caching gymnastics — WP's
		// own meta cache will serve repeats).
		$canonical_val = get_post_meta( $object_id, $canonical, true );

		unset( self::$in_read[ $guard_id ] );

		if ( $canonical_val !== '' && $canonical_val !== false && $canonical_val !== null ) {
			// `get_post_metadata` filter expects array-of-values when overriding.
			return [ $canonical_val ];
		}
		return $value; // fall through; legacy key serves itself
	}

	/**
	 * Filter: update_post_metadata.
	 *
	 * Mirrors writes from a legacy key to the canonical key. Returns null
	 * (== "let WP do the normal write") so the legacy key still gets written
	 * — that keeps any third-party plugin reading the legacy key via raw SQL
	 * happy too. The mirror is fire-and-forget.
	 */
	public static function on_write( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
		if ( $check !== null || ! is_string( $meta_key ) ) {
			return $check;
		}
		$map = self::alias_map();
		if ( ! isset( $map[ $meta_key ] ) ) {
			return $check;
		}
		$canonical = $map[ $meta_key ];
		$guard_id  = $object_id . '|' . $canonical;
		if ( ! empty( self::$in_write[ $guard_id ] ) ) {
			return $check;
		}
		self::$in_write[ $guard_id ] = true;
		update_post_meta( $object_id, $canonical, $meta_value );
		unset( self::$in_write[ $guard_id ] );
		return $check; // null → fall through, legacy key still written
	}

	public static function on_add( $check, $object_id, $meta_key, $meta_value, $unique ) {
		// Same logic as update.
		return self::on_write( $check, $object_id, $meta_key, $meta_value, '' );
	}
}

SGCLabs_SEO_Legacy_Bridge::boot();

endif;
