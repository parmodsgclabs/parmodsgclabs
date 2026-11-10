<?php
/**
 * SGC LABS® International Hub Loader (v6.9.0)
 *
 * Loads all new international/WooCommerce modules.
 * Each module checks its own toggle via SGCLabs_Registry::is_active().
 *
 * @package   SGCLabs\International
 * @since     6.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$sgc_intl_dir = __DIR__;

$sgc_intl_modules = [
    'gdpr-consent.php',       // GDPR cookie banner
    'multi-currency.php',     // Currency switcher
    'wishlist.php',           // B2B wishlist
    'recently-viewed.php',    // Recently viewed
    'product-compare.php',    // Side-by-side compare
    'quick-view.php',         // AJAX quick view
    'rest-api.php',           // REST endpoints
    'login-hardening.php',    // Login throttle + bot block
];

foreach ( $sgc_intl_modules as $sgc_intl_mod ) {
    $sgc_intl_path = $sgc_intl_dir . '/' . $sgc_intl_mod;
    if ( class_exists( 'SGC_Module_Guard' ) && ! SGC_Module_Guard::should_load( 'sgc-site-manager-pro', 'international/' . substr( $sgc_intl_mod, 0, -4 ) ) ) continue; // Module Control
    if ( file_exists( $sgc_intl_path ) ) {
        require_once $sgc_intl_path;
    }
}

unset( $sgc_intl_dir, $sgc_intl_modules, $sgc_intl_mod, $sgc_intl_path );
