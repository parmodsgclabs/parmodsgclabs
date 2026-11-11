<?php
/**
 * SGC LABS® Core Loader (v6.9.0)
 *
 * Single entry-point that loads all new core infrastructure.
 * Hooked early so registry/security/updater are ready before
 * any module or admin page calls them.
 *
 * @package   SGCLabs\Core
 * @since     6.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// 0. SGC Core Bridge — MUST load first (shared doc numbers, PDF paths, inter-plugin bus)
require_once __DIR__ . '/class-sgc-core-bridge.php';

// 1. Core helpers (procedural, used everywhere — load before classes)
require_once __DIR__ . '/currency-helper.php';
require_once __DIR__ . '/seo-api.php';

// 2. Core classes
require_once __DIR__ . '/class-sgclabs-registry.php';
require_once __DIR__ . '/class-sgclabs-security.php';
require_once __DIR__ . '/class-sgclabs-updater.php';

// GitHub auto-updater — loads and registers Manager Pro
require_once __DIR__ . '/class-sgc-github-updater.php';
add_action( 'plugins_loaded', function () {
    SGC_GitHub_Updater::register( [
        'slug'    => 'sgclabs-manager-pro/sgclabs-manager.php',
        'repo'    => 'parmodsgclabs/sgclabs-manager-pro',
        'version' => defined('SGCLABS_BASE_VERSION') ? SGCLABS_BASE_VERSION : '6.9.9',
    ] );
}, 15 );
require_once __DIR__ . '/class-sgclabs-bg-processor.php';
require_once __DIR__ . '/class-sgclabs-seo-fields.php';
require_once __DIR__ . '/class-sgclabs-seo-migrator.php';
require_once __DIR__ . '/class-sgclabs-inquiry-store.php';
require_once __DIR__ . '/class-sgclabs-legacy-inquiry-importer.php';

// 2.5 Backward-compat bridges (must load AFTER schema, BEFORE any module reads SEO meta)
require_once __DIR__ . '/seo-legacy-bridge.php';

// 3. Admin pages
if ( is_admin() ) {
    require_once __DIR__ . '/admin-module-browser.php';
    require_once __DIR__ . '/admin-security-audit.php';
    require_once __DIR__ . '/admin-bg-processor.php';
    require_once __DIR__ . '/admin-seo-migrator.php';
    require_once __DIR__ . '/admin-inquiry-import-export.php';
    require_once __DIR__ . '/admin-multilang-bulk.php';
}

// 3. Inject footer signature into admin pages owned by us
add_action( 'admin_footer_text', function( $text ) {
    if ( ! function_exists( 'get_current_screen' ) ) return $text;
    $screen = get_current_screen();
    if ( $screen && strpos( (string) $screen->id, 'sgclabs' ) !== false ) {
        $sig = class_exists( 'SGCLabs_Updater' ) ? SGCLabs_Updater::label() : 'v6.9.9';
        return 'SGC LABS® Site Manager Pro &nbsp;·&nbsp; '
            . '<code style="font-size:10px;">' . esc_html( $sig ) . '</code>'
            . ' &nbsp;<span style="color:#888;font-size:11px;font-style:italic;">'
            . '&#9889; Powered by Claude &middot; Anthropic</span>';
    }
    return $text;
} );
