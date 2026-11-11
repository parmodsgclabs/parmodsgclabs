<?php
/**
 * Plugin Name:       SGC Product Manager Pro
 * Plugin URI:        https://labsglass.com/plugins/sgc-product-manager-pro/
 * Description:       All-in-one WooCommerce product suite — AI content engine (DeepSeek/Claude/OpenAI/Gemini), Product Studio, Competitor Intelligence, Automation Engine, B2B Export pricing, Bulk SEO, CSV Importer. Built for international laboratory glassware exporters.
 * Version:           3.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Tested up to:      7.0
 * WC requires at least: 6.0
 * WC tested up to:   10.8
 * Author:            SGC LABS®
 * Author URI:        https://labsglass.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sgc-pm
 * Domain Path:       /languages
 * Network:           false
 *
 * @package    SGC_PM
 * @copyright  2024-2026 SGC LABS®
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

defined( 'ABSPATH' ) || exit( 'Direct access not permitted.' );
if ( defined( 'SGC_PM_VERSION' ) ) return;

// ── Version & Build constants ─────────────────────────────────────────────────
define( 'SGC_PM_VERSION',    '3.1.0' );
define( 'SGC_PM_BUILD_DATE', '2026-11-10' );
define( 'SGC_PM_BUILD_TIME', '18:30' );
define( 'SGC_PM_BUILD_ID',   '20261110-1830' );
define( 'SGC_PM_BUILD',      '2026-11-10 18:30 UTC' );
define( 'SGC_PM_MIN_WP',     '6.0' );
define( 'SGC_PM_MIN_PHP',    '8.0' );
define( 'SGC_PM_SLUG',       'sgc-pm' );
define( 'SGC_PM_TEXTDOMAIN', 'sgc-pm' );

// ── Path / URL constants ──────────────────────────────────────────────────────
define( 'SGC_PM_FILE',     __FILE__ );
define( 'SGC_PM_PATH',     plugin_dir_path( __FILE__ ) );
define( 'SGC_PM_URL',      plugin_dir_url( __FILE__ ) );
define( 'SGC_PM_BASE',     plugin_basename( __FILE__ ) );
define( 'SGC_PM_ASSETS',   SGC_PM_URL . 'assets/' );

// ── PHP version guard ─────────────────────────────────────────────────────────
if ( version_compare( PHP_VERSION, SGC_PM_MIN_PHP, '<' ) ) {
    add_action( 'admin_notices', function () {
        printf(
            '<div class="notice notice-error"><p><strong>SGC Product Manager Pro</strong> requires PHP %s or higher. You are running PHP %s.</p></div>',
            esc_html( SGC_PM_MIN_PHP ), esc_html( PHP_VERSION )
        );
    } );
    return;
}

// ── i18n ──────────────────────────────────────────────────────────────────────
add_action( 'init', function () {
    load_plugin_textdomain( SGC_PM_TEXTDOMAIN, false, dirname( SGC_PM_BASE ) . '/languages/' );
}, 1 );

/**
 * CHANGELOG (newest first)
 * ─────────────────────────────────────────────────────────────────────────
 * v3.1.0 — 2026-11-11 — Module Control + automatic safe mode (SGC LABS → Module Control): every module can be switched off; a module that causes a PHP fatal error is switched off automatically
 * v3.0.9 — 2026-11-10 — Stability & ecosystem fixes
 *   ✅ FIX: Competitors tab "Scan All" caused a fatal error (handler was registered but never written) — now queues a background scan
 *   ✅ FIX: Removed a dead duplicate copy of plugin-health-monitor.php (Site Manager owns it; duplicate functions = fatal risk)
 *   ✅ FIX: Export SEO head output (schema/OG/hreflang/canonical) is now disabled when Site Manager Pro is active — it printed duplicate tags; breadcrumb JSON-LD is still added
 *
 * v3.0.0 — 2026-04-23 — MAJOR: SGC Product Studio merged into Product Manager Pro
 *   ✅ Product Studio tab — New Product creator with category defaults + capacity selection
 *   ✅ Image Manager — Upload/URL fetch, auto SKU rename, WooCommerce size regeneration
 *   ✅ Bulk Content Update — SEO content batch generation for all products
 *   ✅ Sub-Category Defaults — Save parameter templates per subcategory
 *   ✅ Studio AI now uses unified AI Engine (DeepSeek/Claude/OpenAI/Gemini) — no separate key
 *   ✅ Single plugin, single nonce, single admin menu — zero conflicts
 *   ✅ Studio DB tables (sgcps_defaults, sgcps_product_params) now managed by PM Pro
 *   ✅ Build timestamp format: ISO-8601 with IST offset
 *
 * v2.0.2 — 2026-04-19 — Integration: Invoice Pro shared USD rate/MOQ/dealer discount
 * v2.0.0 — 2026-04-18 — Enterprise: 11 tabs, AI Studio, Competitor Intel, SQL Editor
 */

// ── WooCommerce HPOS compatibility ────────────────────────────────────────
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

// ── WooCommerce check ────────────────────────────────────────────────────
function sgc_pm_woo_check() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>SGC Product Manager Pro v' . SGC_PM_VERSION . '</strong> requires WooCommerce to be active.</p></div>';
        } );
        return false;
    }
    return true;
}

// ── Boot ─────────────────────────────────────────────────────────────────
// v3.1.0: Module Guard — per-module on/off switches + automatic safe mode (shared with Site Manager)
require_once SGC_PM_PATH . 'includes/sgc-module-guard.php';
SGC_Module_Guard::register( 'sgc-product-manager-pro', 'SGC Product Manager Pro', SGC_PM_PATH, [
    'class-integration'      => 'Ecosystem integration (Invoice Pro / CRM / Site Manager)',
    'class-seo-export'       => 'Export SEO (schema, breadcrumbs, image ALT)',
    'class-logger'           => 'Activity logger',
    'class-ai-engine'        => 'AI engine (DeepSeek / Claude / OpenAI / Gemini)',
    'class-automation'       => 'Automation rules',
    'class-competitor-intel' => 'Competitor intelligence',
    'class-product-table'    => 'Products table',
    'class-bulk-actions'     => 'Bulk actions',
    'class-sql-editor'       => 'SQL editor',
    'class-b2b-export'       => 'B2B export pricing',
    'class-product-tools'    => 'Product tools',
    'class-importer'         => 'CSV importer',
    'class-studio'           => 'Product Studio',
    'class-admin'            => 'Admin UI',
], [ 'class-logger', 'class-admin', 'class-integration' ] );
require_once SGC_PM_PATH . 'includes/class-integration.php';
if ( SGC_Module_Guard::should_load( 'sgc-product-manager-pro', 'class-seo-export' ) ) require_once SGC_PM_PATH . 'includes/class-seo-export.php'; // v3.0.3: Export SEO — Schema, FAQ, OG, hreflang, sitemap

// ── Auto Updater — class loaded by Manager Pro ───────────────────────────
add_action( 'plugins_loaded', function () {
    if ( class_exists( 'SGC_Auto_Updater' ) ) {
        SGC_Auto_Updater::register([
            'plugin_id'       => 'sgc-product-manager-pro',
            'plugin_slug'     => 'sgc-product-manager-pro/sgc-product-manager.php',
            'current_version' => SGC_PM_VERSION,
            'plugin_name'     => 'SGC Product Manager Pro',
        ]);
    }
}, 25 );

add_action( 'plugins_loaded', function () {
    if ( ! sgc_pm_woo_check() ) return;

    $files = [
        'includes/class-logger.php',
        'includes/class-ai-engine.php',
        'includes/class-automation.php',
        'includes/class-competitor-intel.php',
        'includes/class-product-table.php',
        'includes/class-bulk-actions.php',
        'includes/class-sql-editor.php',
        'includes/class-b2b-export.php',
        'includes/class-product-tools.php',
        'includes/class-importer.php',
        'includes/class-studio.php',   // ← Product Studio (merged v3.0.0)
    ];
    foreach ( $files as $f ) {
        $path = SGC_PM_PATH . $f;
        if ( ! SGC_Module_Guard::should_load( 'sgc-product-manager-pro', SGC_Module_Guard::key_from_path( 'sgc-product-manager-pro', $path ) ) ) continue; // Module Control
        if ( file_exists( $path ) ) require_once $path;
    }

    if ( is_admin() ) {
        require_once SGC_PM_PATH . 'includes/class-admin.php';
    }
}, 20 );

// ── Assets ───────────────────────────────────────────────────────────────
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( strpos( $hook, 'sgc-product-manager' ) === false ) return;

    $tab = sanitize_key( $_GET['tab'] ?? 'dashboard' );

    // Core PM Pro assets
    wp_enqueue_style(  'sgc-pm-style', SGC_PM_URL . 'assets/css/style.css',  [], SGC_PM_VERSION );
    wp_enqueue_script( 'sgc-pm-app',   SGC_PM_URL . 'assets/js/app.js', [ 'jquery' ], SGC_PM_VERSION, true );

    wp_localize_script( 'sgc-pm-app', 'SGCPM', [
        'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
        'nonce'      => wp_create_nonce( 'sgc_pm_nonce' ),
        'currency'   => get_woocommerce_currency_symbol(),
        'usdRate'    => floatval( get_option( 'sgc_pm_usd_rate', '83.5' ) ),
        'aiProvider' => get_option( 'sgc_pm_ai_provider', 'deepseek' ),
        'version'    => SGC_PM_VERSION,
        'build'      => SGC_PM_BUILD,
    ] );

    // Studio assets — only on studio tab
    if ( 'studio' === $tab ) {
        wp_enqueue_media(); // For wp.media file picker
        wp_enqueue_style(  'sgc-pm-studio-css', SGC_PM_URL . 'assets/css/studio.css', [], SGC_PM_VERSION );
        wp_enqueue_script( 'sgc-pm-studio-js',  SGC_PM_URL . 'assets/js/studio.js', [ 'jquery' ], SGC_PM_VERSION, true );

        // Compatibility shim: Studio JS uses SGCPS.ajax / SGCPS.nonce
        // We feed it the unified PM Pro nonce so no JS changes needed
        wp_localize_script( 'sgc-pm-studio-js', 'SGCPS', [
            'ajax'    => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'sgc_pm_nonce' ), // ← unified nonce
            'version' => SGC_PM_VERSION,
            'build'   => SGC_PM_BUILD,
            'aiOn'    => ( get_option( 'sgc_pm_ai_enabled', '1' ) === '1' ) ? '1' : '0',
        ] );
    }
} );

// ── Activation ───────────────────────────────────────────────────────────
register_activation_hook( __FILE__, 'sgc_pm_activate' );
function sgc_pm_activate() {
    sgc_pm_create_tables();
    sgc_pm_set_defaults();

    if ( ! wp_next_scheduled( 'sgc_pm_automation_run' ) )
        wp_schedule_event( time(), 'hourly', 'sgc_pm_automation_run' );

    if ( ! wp_next_scheduled( 'sgc_pm_competitor_scan' ) )
        wp_schedule_event( strtotime( 'next monday 03:00 AM' ), 'weekly', 'sgc_pm_competitor_scan' );
}

// ── Deactivation ─────────────────────────────────────────────────────────
register_deactivation_hook( __FILE__, 'sgc_pm_deactivate' );
function sgc_pm_deactivate() {
    wp_clear_scheduled_hook( 'sgc_pm_automation_run' );
    wp_clear_scheduled_hook( 'sgc_pm_competitor_scan' );
}

// ── Cron hooks ───────────────────────────────────────────────────────────
add_action( 'sgc_pm_automation_run',  [ 'SGC_PM_Automation',      'run_pending'  ] );
add_action( 'sgc_pm_competitor_scan', [ 'SGC_PM_Competitor_Intel', 'run_scan_all' ] );
add_action( 'sgc_pm_competitor_scan_now', [ 'SGC_PM_Competitor_Intel', 'run_scan_all' ] ); // one-off scan queued from the Competitors tab

// ── DB Tables ────────────────────────────────────────────────────────────
function sgc_pm_create_tables() {
    global $wpdb;
    $c = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // ── PM Pro core tables ────────────────────────────────────────────────
    dbDelta( "CREATE TABLE {$wpdb->prefix}sgcpm_competitors (
        id          bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name        varchar(200)        NOT NULL,
        url         varchar(500)        NOT NULL,
        category    varchar(200)                 DEFAULT '',
        country     varchar(10)                  DEFAULT 'IN',
        status      varchar(20)                  DEFAULT 'active',
        last_scan   datetime                     DEFAULT NULL,
        created_at  datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY status_idx (status)
    ) $c;" );

    dbDelta( "CREATE TABLE {$wpdb->prefix}sgcpm_competitor_data (
        id              bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        competitor_id   bigint(20) UNSIGNED NOT NULL,
        product_id      bigint(20) UNSIGNED NOT NULL,
        their_title     varchar(500)                 DEFAULT '',
        their_meta_desc text,
        their_keywords  longtext,
        their_rank      int                          DEFAULT NULL,
        gap_keywords    longtext,
        ai_suggestion   longtext,
        fetched_at      datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY product_idx (product_id),
        KEY comp_idx    (competitor_id)
    ) $c;" );

    dbDelta( "CREATE TABLE {$wpdb->prefix}sgcpm_automations (
        id           bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name         varchar(200)        NOT NULL,
        trigger_type varchar(100)        NOT NULL,
        conditions   longtext,
        action_type  varchar(100)        NOT NULL,
        action_data  longtext,
        status       varchar(20)                  DEFAULT 'active',
        run_count    int                          DEFAULT 0,
        last_run     datetime                     DEFAULT NULL,
        created_at   datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $c;" );

    dbDelta( "CREATE TABLE {$wpdb->prefix}sgcpm_logs (
        id          bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        action      varchar(100)        NOT NULL,
        object_type varchar(50)                  DEFAULT 'product',
        object_id   bigint(20)                   DEFAULT NULL,
        details     text,
        user_id     bigint(20)                   DEFAULT NULL,
        ip          varchar(45)                  DEFAULT '',
        created_at  datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY action_idx     (action),
        KEY created_idx    (created_at)
    ) $c;" );

    dbDelta( "CREATE TABLE {$wpdb->prefix}sgcpm_keywords (
        id          bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        product_id  bigint(20) UNSIGNED NOT NULL,
        keyword     varchar(500)        NOT NULL,
        source      varchar(100)                 DEFAULT 'manual',
        volume      int                          DEFAULT 0,
        created_at  datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY product_idx (product_id)
    ) $c;" );

    // ── Product Studio tables (merged v3.0.0) ─────────────────────────────
    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sgcps_defaults (
        id          bigint(20)   NOT NULL AUTO_INCREMENT,
        subcat_id   bigint(20)   NOT NULL,
        subcat_name varchar(200) NOT NULL DEFAULT '',
        params      longtext     NOT NULL,
        capacities  text         NOT NULL DEFAULT '',
        cap_unit    varchar(20)  NOT NULL DEFAULT 'ml',
        updated_at  datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY subcat_id (subcat_id)
    ) $c;" );

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sgcps_product_params (
        id         bigint(20) NOT NULL AUTO_INCREMENT,
        product_id bigint(20) NOT NULL,
        params     longtext   NOT NULL,
        updated_at datetime   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY product_id (product_id)
    ) $c;" );
}

function sgc_pm_set_defaults() {
    $defaults = [
        'sgc_pm_usd_rate'           => '83.5',
        'sgc_pm_default_moq'        => '10',
        'sgc_pm_dealer_discount'    => '20',
        'sgc_pm_company_name'       => 'SGC LABS® Scientific Glassware',
        'sgc_pm_ai_provider'        => 'deepseek',
        'sgc_pm_ai_tone'            => 'b2b_formal',
        'sgc_pm_ai_brand'           => 'SGC LABS®',
        'sgc_pm_ai_enabled'         => '1',
        'sgc_pm_automation_enabled' => '1',
        'sgc_pm_competitor_enabled' => '1',
        'sgc_pm_stock_alert_qty'    => '5',
        'sgc_pm_gallery_enhance'    => '1',
    ];
    foreach ( $defaults as $key => $val ) {
        if ( ! get_option( $key ) ) update_option( $key, $val );
    }
}

// ── DB upgrade on version change ─────────────────────────────────────────
add_action( 'plugins_loaded', function () {
    $installed = get_option( 'sgc_pm_db_version', '0' );
    if ( version_compare( $installed, SGC_PM_VERSION, '<' ) ) {
        sgc_pm_create_tables();
        update_option( 'sgc_pm_db_version', SGC_PM_VERSION );
    }
}, 25 );

// ── Master toggles (header switches) — v3.0.9: the JS posted to sgc_pm_master_toggle but no
//    handler existed, so the AI / Auto / Intel switches in the header always showed "Error".
add_action( 'wp_ajax_sgc_pm_master_toggle', function () {
    check_ajax_referer( 'sgc_pm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'Unauthorized', 403 );
    $allowed = [ 'sgc_pm_ai_enabled', 'sgc_pm_automation_enabled', 'sgc_pm_competitor_enabled', 'sgc_pm_gallery_enhance' ];
    $opt = sanitize_key( $_POST['option'] ?? '' );
    if ( ! in_array( $opt, $allowed, true ) ) wp_send_json_error( 'Option not allowed' );
    $val = ( '1' === sanitize_text_field( wp_unslash( $_POST['value'] ?? '0' ) ) ) ? '1' : '0';
    update_option( $opt, $val );
    wp_send_json_success( [ 'option' => $opt, 'value' => $val ] );
} );

// ── Plugin action links ───────────────────────────────────────────────────
add_filter( 'plugin_action_links_' . SGC_PM_BASE, function ( $links ) {
    array_unshift( $links, '<a href="' . admin_url( 'admin.php?page=sgc-product-manager' ) . '">Open</a>' );
    return $links;
} );

// ── Frontend: Product Gallery — Big main + small thumbnails (v3.0.4) ─────
function sgc_pm_gallery_enhance_on(): bool {
    return get_option( 'sgc_pm_gallery_enhance', '1' ) === '1' && function_exists( 'is_product' ) && is_product();
}
add_action( 'wp_head', function () {
    if ( ! sgc_pm_gallery_enhance_on() ) return;
    ?>
<style id="sgc-pm-gallery-style">
/* SGC PM v3.0.4 — Product Gallery: Big main + thumbnail strip */
.woocommerce-product-gallery {
    display: flex !important;
    flex-direction: column !important;
    gap: 10px !important;
}
/* Main image — full width, tall */
.woocommerce-product-gallery .woocommerce-product-gallery__wrapper {
    order: 1 !important;
    width: 100% !important;
}
.woocommerce-product-gallery .woocommerce-product-gallery__image:not(:first-child) {
    display: none !important;
}
.woocommerce-product-gallery .woocommerce-product-gallery__image:first-child img {
    width: 100% !important;
    height: auto !important;
    max-height: 480px !important;
    object-fit: contain !important;
    border-radius: 8px !important;
    cursor: zoom-in !important;
    transition: opacity .25s ease !important;
    border: 1px solid #f0f0f0 !important;
}
/* Thumbnail strip */
.sgc-pm-thumb-strip {
    order: 2 !important;
    display: flex !important;
    gap: 8px !important;
    flex-wrap: wrap !important;
    padding: 0 !important;
    margin: 0 !important;
}
.sgc-pm-thumb-strip .sgc-pm-thumb {
    width: 72px !important;
    height: 72px !important;
    object-fit: contain !important;
    border: 2px solid #e0e0e0 !important;
    border-radius: 6px !important;
    cursor: pointer !important;
    transition: border-color .2s, opacity .2s !important;
    background: #fafafa !important;
    padding: 3px !important;
    box-sizing: border-box !important;
}
.sgc-pm-thumb-strip .sgc-pm-thumb:hover,
.sgc-pm-thumb-strip .sgc-pm-thumb.sgc-active {
    border-color: #1a1a2e !important;
    opacity: 1 !important;
}
.sgc-pm-thumb-strip .sgc-pm-thumb:not(.sgc-active) {
    opacity: .72 !important;
}
/* Lightbox overlay */
#sgc-pm-lightbox {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.88);
    z-index: 99999;
    align-items: center;
    justify-content: center;
    cursor: zoom-out;
}
#sgc-pm-lightbox.open { display: flex !important; }
#sgc-pm-lightbox img {
    max-width: 92vw;
    max-height: 92vh;
    object-fit: contain;
    border-radius: 8px;
    box-shadow: 0 8px 40px rgba(0,0,0,.5);
}
#sgc-pm-lightbox-close {
    position: absolute;
    top: 18px; right: 24px;
    color: #fff;
    font-size: 32px;
    cursor: pointer;
    line-height: 1;
    opacity: .8;
}
#sgc-pm-lightbox-close:hover { opacity: 1; }
</style>
    <?php
} );

add_action( 'wp_footer', function () {
    if ( ! sgc_pm_gallery_enhance_on() ) return;
    ?>
<!-- SGC PM v3.0.4 Gallery JS -->
<div id="sgc-pm-lightbox"><span id="sgc-pm-lightbox-close">✕</span><img id="sgc-pm-lightbox-img" src="" alt=""></div>
<script>
(function(){
    function initGallery(){
        var gallery = document.querySelector('.woocommerce-product-gallery');
        if(!gallery) return;

        var images = gallery.querySelectorAll('.woocommerce-product-gallery__image');
        if(images.length < 2) return; // Only 1 image, no strip needed.

        var mainImg = images[0].querySelector('img');
        if(!mainImg) return;

        // Build thumbnail strip.
        var strip = document.createElement('div');
        strip.className = 'sgc-pm-thumb-strip';

        images.forEach(function(item, i){
            var img = item.querySelector('img');
            if(!img) return;
            var src   = img.getAttribute('data-large_image') || img.src;
            var thumb = document.createElement('img');
            thumb.className = 'sgc-pm-thumb' + (i===0?' sgc-active':'');
            thumb.src   = img.src;
            thumb.alt   = img.alt;
            thumb.setAttribute('data-full', src);

            thumb.addEventListener('click', function(){
                // Swap main image.
                var full = this.getAttribute('data-full');
                mainImg.src = full;
                if(mainImg.hasAttribute('data-large_image')) mainImg.setAttribute('data-large_image', full);
                // Update active state.
                strip.querySelectorAll('.sgc-pm-thumb').forEach(function(t){ t.classList.remove('sgc-active'); });
                this.classList.add('sgc-active');
            });
            strip.appendChild(thumb);
        });

        gallery.appendChild(strip);

        // Lightbox on main image click.
        mainImg.addEventListener('click', function(){
            var full = this.getAttribute('data-large_image') || this.src;
            document.getElementById('sgc-pm-lightbox-img').src = full;
            document.getElementById('sgc-pm-lightbox').classList.add('open');
        });
    }

    // Close lightbox.
    document.getElementById('sgc-pm-lightbox').addEventListener('click', function(){
        this.classList.remove('open');
    });
    document.getElementById('sgc-pm-lightbox-close').addEventListener('click', function(e){
        e.stopPropagation();
        document.getElementById('sgc-pm-lightbox').classList.remove('open');
    });
    document.addEventListener('keydown', function(e){
        if(e.key==='Escape') document.getElementById('sgc-pm-lightbox').classList.remove('open');
    });

    // Init after WooCommerce gallery is ready.
    if(document.readyState==='loading'){
        document.addEventListener('DOMContentLoaded', function(){
            setTimeout(initGallery, 300);
        });
    } else {
        setTimeout(initGallery, 300);
    }
})();
</script>
    <?php
} );
