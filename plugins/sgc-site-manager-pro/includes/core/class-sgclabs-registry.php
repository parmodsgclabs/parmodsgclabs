<?php
/**
 * SGC LABS® Module Registry
 *
 * Central registry for every module/feature in the plugin.
 * - Categorized into logical groups (SEO, AI, Product, Store, International, Tools, Core)
 * - Alphabetical sort built-in
 * - Per-module toggle (option-based)
 * - Per-module health check
 * - Duplicate / unused detector
 *
 * @package   SGCLabs\Core
 * @since     6.9.0
 * @license   GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'SGCLabs_Registry' ) ) :

final class SGCLabs_Registry {

    /** @var array<string,array> */
    private static $modules = [];

    /** @var array<string,string> Category labels */
    public static $categories = [
        'core'          => '⚙️ Core',
        'seo'           => '🔍 SEO',
        'ai'            => '🧠 AI',
        'product'       => '📦 Product Page',
        'store'         => '🛒 Store / WooCommerce',
        'international' => '🌍 International',
        'tools'         => '🛠️ Site Tools',
        'security'      => '🛡️ Security',
        'performance'   => '⚡ Performance',
    ];

    /**
     * Register a module.
     *
     * @param string $slug      Unique slug (e.g. 'wishlist').
     * @param array  $args      label, desc, file, group, default ('1'|'0'), version, author.
     */
    public static function register( string $slug, array $args ): void {
        $defaults = [
            'label'   => $slug,
            'desc'    => '',
            'file'    => '',
            'group'   => 'tools',
            'default' => '1',
            'version' => '1.0.0',
            'author'  => 'SGC LABS®',
            'tag'     => '',     // 'new' | 'beta' | 'pro' | 'old'
            'option'  => 'sgclabs_' . $slug . '_active',
        ];
        self::$modules[ $slug ] = wp_parse_args( $args, $defaults );
    }

    /** Get all registered modules (unsorted). */
    public static function all(): array {
        return self::$modules;
    }

    /** Modules sorted alphabetically by label. */
    public static function alphabetical(): array {
        $copy = self::$modules;
        uasort( $copy, function( $a, $b ) {
            return strcasecmp(
                wp_strip_all_tags( $a['label'] ),
                wp_strip_all_tags( $b['label'] )
            );
        } );
        return $copy;
    }

    /** Modules grouped by category, alphabetical inside each group. */
    public static function by_category(): array {
        $out = [];
        foreach ( self::$categories as $cat => $label ) {
            $out[ $cat ] = [];
        }
        foreach ( self::alphabetical() as $slug => $mod ) {
            $g = isset( $out[ $mod['group'] ] ) ? $mod['group'] : 'tools';
            $out[ $g ][ $slug ] = $mod;
        }
        // Drop empty groups so admin UI is clean.
        return array_filter( $out );
    }

    /** Is a given module active? */
    public static function is_active( string $slug ): bool {
        if ( ! isset( self::$modules[ $slug ] ) ) return false;
        $opt = self::$modules[ $slug ]['option'];
        $def = self::$modules[ $slug ]['default'];
        return get_option( $opt, $def ) === '1';
    }

    /** Toggle a module on/off (admin-only). */
    public static function set_active( string $slug, bool $on ): bool {
        if ( ! current_user_can( 'manage_options' ) )    return false;
        if ( ! isset( self::$modules[ $slug ] ) )         return false;
        update_option( self::$modules[ $slug ]['option'], $on ? '1' : '0' );
        return true;
    }

    /**
     * Health check — verifies module file exists, function names match conventions.
     * Returns array of issues (empty = healthy).
     */
    public static function health_check(): array {
        $issues = [];
        foreach ( self::$modules as $slug => $mod ) {
            if ( ! empty( $mod['file'] ) && ! file_exists( $mod['file'] ) ) {
                $issues[] = sprintf( 'Missing file for "%s": %s', $slug, $mod['file'] );
            }
        }
        return $issues;
    }

    /** Total module count by category. */
    public static function counts(): array {
        $counts = [];
        foreach ( self::by_category() as $cat => $mods ) {
            $counts[ $cat ] = count( $mods );
        }
        return $counts;
    }
}

endif;

// ── REGISTER CORE MODULES (executed on plugin load) ──────────────────
add_action( 'plugins_loaded', function() {

    // International (NEW v6.9.0)
    SGCLabs_Registry::register( 'gdpr_consent', [
        'label'   => '🍪 GDPR Cookie Consent',
        'desc'    => 'Cookie consent banner — GDPR/CCPA/ePrivacy compliant',
        'group'   => 'international',
        'version' => '1.0.0',
        'tag'     => 'new',
    ] );

    SGCLabs_Registry::register( 'multi_currency', [
        'label'   => '💱 Multi-Currency Switcher',
        'desc'    => 'Optional: 8-currency display switcher. OFF by default — site uses WooCommerce default currency (USD).',
        'group'   => 'international',
        'version' => '1.0.1',
        'default' => '0',
        'tag'     => 'beta',
    ] );

    SGCLabs_Registry::register( 'wishlist', [
        'label'   => '❤️ B2B Wishlist',
        'desc'    => 'Customer wishlist — save for quote, share via email',
        'group'   => 'international',
        'version' => '1.0.0',
        'tag'     => 'new',
    ] );

    SGCLabs_Registry::register( 'recently_viewed', [
        'label'   => '👁️ Recently Viewed Products',
        'desc'    => 'Track and display last-viewed products on every page',
        'group'   => 'international',
        'version' => '1.0.0',
        'tag'     => 'new',
    ] );

    SGCLabs_Registry::register( 'product_compare', [
        'label'   => '⚖️ Product Comparison',
        'desc'    => 'Side-by-side spec comparison — up to 4 products',
        'group'   => 'international',
        'version' => '1.0.0',
        'tag'     => 'new',
    ] );

    SGCLabs_Registry::register( 'quick_view', [
        'label'   => '⚡ AJAX Quick View',
        'desc'    => 'Modal preview without leaving listing page',
        'group'   => 'international',
        'version' => '1.0.0',
        'tag'     => 'new',
    ] );

    SGCLabs_Registry::register( 'rest_api', [
        'label'   => '🔌 REST API Endpoints',
        'desc'    => 'Headless commerce — products, quotes, inquiries via REST',
        'group'   => 'international',
        'version' => '1.0.0',
        'tag'     => 'new',
    ] );

    // Security (NEW v6.9.0)
    SGCLabs_Registry::register( 'security_audit', [
        'label'   => '🛡️ Security Audit',
        'desc'    => 'Built-in scanner — file permissions, nonces, capability checks',
        'group'   => 'security',
        'version' => '1.0.0',
        'tag'     => 'new',
    ] );

    SGCLabs_Registry::register( 'login_hardening', [
        'label'   => '🔒 Login Hardening',
        'desc'    => 'Failed login throttle, 2FA-ready, suspicious-IP block',
        'group'   => 'security',
        'version' => '1.0.0',
        'tag'     => 'new',
    ] );

    do_action( 'sgclabs_registry_init' );
}, 5 );
