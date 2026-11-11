<?php
/**
 * SGC LABS® — Canonical Currency Helper
 *
 * Single source of truth for currency display across all SGC LABS® modules.
 * Reads the WooCommerce default currency via `get_woocommerce_currency()` /
 * `get_woocommerce_currency_symbol()` and exposes simple, prefixed helpers.
 *
 * After v6.9.1, every plugin module that needs to show a price symbol or format
 * a price MUST call these helpers — no hardcoded `₹`, `$`, `€` literals anywhere.
 *
 * Multi-currency conversion (when the optional `multi_currency` module is active)
 * still runs as a display-layer filter in `includes/international/multi-currency.php`,
 * so this helper is also the right entry point for that filter chain.
 *
 * @package SGCLabs\Core
 * @since   6.9.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'sgclabs_currency_code' ) ) :
	/**
	 * Get the active currency code (e.g. "USD", "INR", "EUR").
	 *
	 * Defers to WooCommerce when available, falls back to the
	 * `sgclabs_default_currency` option, then to USD.
	 *
	 * @return string ISO-4217 currency code.
	 */
	function sgclabs_currency_code(): string {
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			$wc = get_woocommerce_currency();
			if ( $wc ) {
				return strtoupper( $wc );
			}
		}
		$fallback = get_option( 'sgclabs_default_currency', 'USD' );
		return strtoupper( is_string( $fallback ) ? $fallback : 'USD' );
	}
endif;

if ( ! function_exists( 'sgclabs_currency_symbol' ) ) :
	/**
	 * Get the active currency symbol (e.g. "$", "₹", "€").
	 *
	 * @param string|null $code Optional ISO code; defaults to the active currency.
	 * @return string Currency symbol (HTML entities decoded).
	 */
	function sgclabs_currency_symbol( ?string $code = null ): string {
		$code = $code ? strtoupper( $code ) : sgclabs_currency_code();

		if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
			$sym = get_woocommerce_currency_symbol( $code );
			if ( $sym ) {
				// WC sometimes returns HTML entities like "&#36;" — decode for plain output.
				return html_entity_decode( $sym, ENT_QUOTES, 'UTF-8' );
			}
		}

		// Minimal fallback table for environments where WC isn't loaded yet.
		$map = [
			'USD' => '$',
			'INR' => '₹',
			'EUR' => '€',
			'GBP' => '£',
			'AED' => 'د.إ',
			'SAR' => 'ر.س',
			'JPY' => '¥',
			'AUD' => 'A$',
			'CAD' => 'C$',
		];
		return $map[ $code ] ?? '$';
	}
endif;

if ( ! function_exists( 'sgclabs_format_price' ) ) :
	/**
	 * Format a numeric price using the active currency.
	 *
	 * Defers to `wc_price()` when WooCommerce is available so decimal places,
	 * thousands separator, and currency-symbol position match the store's
	 * settings exactly.
	 *
	 * @param float|int|string $amount Numeric value to format.
	 * @param array            $args   Optional WooCommerce wc_price() args.
	 * @return string HTML-safe formatted price.
	 */
	function sgclabs_format_price( $amount, array $args = [] ): string {
		$amount = is_numeric( $amount ) ? (float) $amount : 0.0;

		if ( function_exists( 'wc_price' ) ) {
			return wc_price( $amount, $args );
		}

		$decimals = isset( $args['decimals'] ) ? (int) $args['decimals'] : 2;
		return sgclabs_currency_symbol() . number_format_i18n( $amount, $decimals );
	}
endif;

if ( ! function_exists( 'sgclabs_currency_position' ) ) :
	/**
	 * Where the currency symbol sits relative to the amount.
	 *
	 * Returns one of: 'left', 'right', 'left_space', 'right_space'.
	 *
	 * @return string
	 */
	function sgclabs_currency_position(): string {
		$pos = get_option( 'woocommerce_currency_pos', 'left' );
		return in_array( $pos, [ 'left', 'right', 'left_space', 'right_space' ], true )
			? $pos
			: 'left';
	}
endif;

if ( ! function_exists( 'sgclabs_currency_set_default' ) ) :
	/**
	 * Set the default currency option (A8 — "Set as Default Currency" button).
	 *
	 * Updates BOTH `sgclabs_default_currency` and (when WooCommerce is loaded)
	 * `woocommerce_currency` so the entire site is consistent. Also writes
	 * `sgclabs_wc_currency_override` so the older filter in extras.php (which
	 * forces the WooCommerce currency on every read) picks up the new value
	 * without needing its own admin UI.
	 *
	 * Capability + nonce checks are the caller's responsibility.
	 *
	 * @param string $code ISO-4217 currency code.
	 * @return bool True on success.
	 */
	function sgclabs_currency_set_default( string $code ): bool {
		$code = strtoupper( trim( $code ) );
		if ( ! preg_match( '/^[A-Z]{3}$/', $code ) ) {
			return false;
		}
		update_option( 'sgclabs_default_currency', $code );
		update_option( 'sgclabs_wc_currency_override', $code );
		// Also update WooCommerce so storefront prices follow.
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			update_option( 'woocommerce_currency', $code );
		}
		return true;
	}
endif;
