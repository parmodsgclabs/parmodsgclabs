<?php
/**
 * Plugin Name:       SGC Site Manager Pro
 * Plugin URI:        https://labsglass.com/plugins/sgc-site-manager-pro/
 * Description:       Complete SEO, AI & WooCommerce suite for international export businesses — Schema.org, IndexNow, hreflang, GSC, AI content engine, B2B pricing, FAQ generator, country landing pages, rank tracker, performance optimizer. GPL v2.
 * Version:           7.5.3
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Tested up to:      7.0
 * WC requires at least: 6.0
 * WC tested up to:   10.8
 * Author:            SGC LABS®
 * Author URI:        https://labsglass.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sgclabs-manager
 * Domain Path:       /languages
 * Network:           false
 *
 * @package    SGCLabs_Manager
 * @copyright  2024-2026 SGC LABS®
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

defined( 'ABSPATH' ) || exit( 'Direct access not permitted.' );

// ── Version & Build constants ─────────────────────────────────────────────────
if ( ! defined( 'SGCLABS_BASE_VERSION' ) ) {
    define( 'SGCLABS_BASE_VERSION', '7.5.3' );
    define( 'SGCLABS_BUILD_DATE',   '2026-11-10' );
    define( 'SGCLABS_BUILD_TIME',   '18:30' );
    define( 'SGCLABS_BUILD_ID',     '20261111-0445' );
    define( 'SGCLABS_BUILD',        '2026-11-11 04:45 IST' );
    define( 'SGCLABS_APP_BUILD_NO', '753-20261111-0445' );  // APP BUILD NO: {version}-{date}-{time}
    define( 'SGCLABS_MIN_WP',       '6.0' );
    define( 'SGCLABS_MIN_PHP',      '8.0' );
    define( 'SGCLABS_SLUG',         'sgclabs-manager' );
    define( 'SGCLABS_TEXTDOMAIN',   'sgclabs-manager' );
}

// ── PHP version guard ─────────────────────────────────────────────────────────
if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
    add_action( 'admin_notices', function () {
        printf(
            '<div class="notice notice-error"><p><strong>SGC Site Manager Pro</strong> requires PHP 8.0 or higher. You are running PHP %s. Please upgrade PHP in your hosting control panel.</p></div>',
            esc_html( PHP_VERSION )
        );
    } );
    return;
}
// Use timestamp-based unique build version for every activation or runtime.
if (!defined('SGCLABS_UNIQUE_BUILD')) {
    define('SGCLABS_UNIQUE_BUILD', SGCLABS_BUILD_ID); // fixed per release — was gmdate('YmdHi'), which re-ran activation every minute
}
if (!defined('SGCLABS_VERSION')) {
    define('SGCLABS_VERSION', SGCLABS_BASE_VERSION . '.' . SGCLABS_UNIQUE_BUILD);
}
if (!defined('SGCLABS_PATH')) {
    define('SGCLABS_PATH', plugin_dir_path(__FILE__));
}
if (!defined('SGCLABS_URL')) {
    define('SGCLABS_URL', plugin_dir_url(__FILE__));
}

// ============================================================
// GLOBAL ENCRYPTION HELPERS
// ============================================================
// ENCRYPTION HELPERS v2 — AES-256-CBC with random IV
// sgce2: prefix = new format (random IV prepended to ciphertext)
// sgce:  prefix = legacy format (fixed IV — decrypted for backward compat)
// ============================================================

/**
 * Derive a consistent 32-byte key from WordPress secret keys using HKDF.
 * Falls back to SHA-256 substr on PHP < 7.1.2 where hash_hkdf is unavailable.
 *
 * @return string 32 raw bytes
 */
function sgclabs_derive_key(): string {
    $auth_key   = defined( 'AUTH_KEY' )        ? AUTH_KEY        : '';
    $secure_key = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '';
    $ikm        = $auth_key . $secure_key;
    if ( function_exists( 'hash_hkdf' ) ) {
        return hash_hkdf( 'sha256', $ikm, 32, 'sgclabs_v3_enc', '' );
    }
    // Fallback: SHA-256 derived key (PHP 7.0 compat)
    return substr( hash( 'sha256', $ikm . 'sgclabs_v3_enc', true ), 0, 32 );
}

/**
 * Encrypt an API key for storage.
 * Uses AES-256-CBC with a fresh random 16-byte IV on every call.
 *
 * @param  string $value  Plain-text value to encrypt.
 * @return string         Encrypted string with 'sgce2:' prefix, or base64 fallback.
 */
function sgclabs_encrypt_key( string $value ): string {
    if ( empty( $value ) ) return '';
    if ( ! function_exists( 'openssl_encrypt' ) ) return base64_encode( $value );

    $key = sgclabs_derive_key();
    $iv  = random_bytes( 16 );  // cryptographically random IV every time
    $enc = openssl_encrypt( $value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
    if ( $enc === false ) return base64_encode( $value );

    // Store IV + ciphertext together, base64-encoded
    return 'sgce2:' . base64_encode( $iv . $enc );
}

/**
 * Decrypt an API key from storage.
 * Handles: sgce2: (new random IV), sgce: (legacy fixed IV), raw base64 fallback.
 *
 * @param  string $value  Stored encrypted value.
 * @return string         Decrypted plain-text, or empty string on failure.
 */
function sgclabs_decrypt_key( string $value ): string {
    if ( empty( $value ) ) return '';

    // New format: random IV
    if ( strpos( $value, 'sgce2:' ) === 0 && function_exists( 'openssl_decrypt' ) ) {
        $raw = base64_decode( substr( $value, 6 ), true );
        if ( $raw === false || strlen( $raw ) <= 16 ) return '';
        $iv  = substr( $raw, 0, 16 );
        $enc = substr( $raw, 16 );
        $key = sgclabs_derive_key();
        $dec = openssl_decrypt( $enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        return ( $dec !== false ) ? $dec : '';
    }

    // Legacy format: fixed IV (backward compat — will re-encrypt on next save)
    if ( strpos( $value, 'sgce:' ) === 0 && function_exists( 'openssl_decrypt' ) ) {
        $auth_key   = defined( 'AUTH_KEY' )        ? AUTH_KEY        : '';
        $secure_key = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '';
        $logged_key = defined( 'LOGGED_IN_KEY' )   ? LOGGED_IN_KEY   : '';
        $key_legacy = substr( hash( 'sha256', $auth_key . $secure_key . 'sgclabs_v2' ), 0, 32 );
        $iv_legacy  = substr( hash( 'sha256', $logged_key . 'sgclabs_iv' ), 0, 16 );
        $dec = openssl_decrypt( substr( $value, 5 ), 'AES-256-CBC', $key_legacy, 0, $iv_legacy );
        return ( $dec !== false ) ? $dec : '';
    }

    // Plain base64 fallback (very old installs)
    if ( strpos( $value, 'sgce' ) === false ) {
        $try = base64_decode( $value, true );
        if ( $try !== false && strlen( $try ) > 4 ) return $try;
    }

    return $value;
}
function sgclabs_get_api_key(string $option): string {
    return sgclabs_decrypt_key(get_option($option, ''));
}
function sgclabs_save_api_key(string $option, string $raw_value): void {
    update_option($option, sgclabs_encrypt_key(sanitize_text_field($raw_value)));
}

// Module versions are updated automatically each activation run.
function sgclabs_get_modules_versions(): array {
    $build = SGCLABS_UNIQUE_BUILD;
    return array(
        'google-compliance' => '1.1.' . $build,
        'site-fixes' => '1.2.' . $build,
        'header-footer' => '1.0.' . $build,
        // announcement-bar removed in v6.6.5
        'image-seo' => '1.3.' . $build,
        'seo-manager' => '2.0.' . $build,
        'schema-engine' => '1.8.' . $build,
        'seo-metabox' => '1.7.' . $build,
        // auto-seo-engine consolidated into ai-content-engine v6.6.5
        'woo-local-seo' => '1.4.' . $build,
        'multisite-profiles' => '1.0.' . $build,
        'multilingual-seo' => '1.1.' . $build,
        'global-keywords-engine' => '1.5.' . $build,
        // ai-claude-seo consolidated into ai-content-engine v6.6.5
        'related-product-links' => '1.2.' . $build,
        'size-variant-linker' => '1.0.' . $build,
        'rankmath-parity' => '1.0.' . $build,
        'keywords' => '1.0.' . $build,
        'inquiry-form' => '1.0.' . $build,
        'extras' => '2.0.' . $build,
        'sitemap' => '1.0.' . $build,
        'extended-sitemaps' => '1.0.' . $build,
        'ai-control-center' => '1.0.' . $build,
        // ai-content-generator, focus-keyword-generator, seo-injector, product-content-rewriter, ai-focus-keywords consolidated into ai-content-engine v6.6.5
        'ai-seo-features' => '1.0.' . $build,
        'advanced-seo-tools' => '1.0.' . $build,
        'advanced-admin-pages' => '1.0.' . $build,
        'broken-links' => '1.0.' . $build,
        'internal-links' => '1.0.' . $build,
        'keyword-manager' => '1.0.' . $build,
        'rank-tracker' => '1.0.' . $build,
        'redirections' => '1.0.' . $build,
        'site-tools' => '1.0.' . $build,
        'woo-image-sizes' => '1.0.' . $build,
        // (removed — consolidated v6.6.5)
        'gsc-connect' => '1.0.' . $build,
        'seo-hub' => '1.0.' . $build,
        'shipping-rules' => '1.0.' . $build,
        'pdf-brochure'                  => '1.0.' . $build,
        // (removed — consolidated v6.6.5)
        // ── v7.4.7 updated modules ──────────────────────────────────
        'get-a-quote'                  => '1.1.' . $build,   // literal-n bug fix, mobile CSS, div balance
        'b2b-quote-shortcodes'         => '1.1.' . $build,   // nonce security added
        'site-doctor'                  => '1.1.' . $build,   // JS log() renamed sdLog()
        'site-crawler'                 => '1.1.' . $build,   // extra </div> removed
        'multisite-seo-differentiator' => '1.1.' . $build,   // JS log() renamed msdLog()
        // ── v7.4.7 new modules ──────────────────────────────────────
        'plugin-health-monitor'        => '1.0.' . $build,   // Auto Scan System
        'class-sgc-auto-updater'       => '1.0.' . $build,   // Auto Update Client
    );
}

function sgclabs_store_module_versions() {
    $versions = sgclabs_get_modules_versions();
    update_option('sgclabs_base_version', SGCLABS_BASE_VERSION);
    update_option('sgclabs_unique_build', SGCLABS_UNIQUE_BUILD);
    update_option('sgclabs_version', SGCLABS_VERSION);
    foreach ($versions as $module => $ver) {
        update_option('sgclabs_module_version_' . $module, $ver);
    }
}

add_action('admin_init', function() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-warning"><p><strong>SGC LABS® Manager:</strong> WooCommerce active hona chahiye.</p></div>';
        });
    }
});

// ============================================================
// LOAD MODULES — v6.6.3 Conditional Loading
// Group A: Always load (frontend hooks + admin both need these)
// Group B: Admin only (heavy pages, no frontend hooks)
// Group C: Frontend only (product page filters, public forms)
// ============================================================

// Safe include helper to avoid fatal failures if files are missing.
// v7.5.2: honours SGC LABS → Module Control (a module switched OFF is never included).
function sgclabs_include_file(string $file) {
    if ( class_exists( 'SGC_Module_Guard' ) && ! SGC_Module_Guard::should_load( 'sgc-site-manager-pro', SGC_Module_Guard::key_from_path( 'sgc-site-manager-pro', $file ) ) ) {
        return; // switched off in Module Control (manually or by automatic safe mode)
    }
    if (file_exists($file)) {
        require_once $file;
    } else {
        error_log('SGC LABS Manager Pro missing module: ' . $file);
    }
}

// ── GLOBAL HELP BOX HELPER ──────────────────────────────────
/**
 * Renders a collapsible SOP / Tutorial help box.
 *
 * @param string $id      Unique ID for this box (used for localStorage)
 * @param string $title   Box title e.g. "How to use AI Content Center"
 * @param array  $steps   [ ['icon'=>'1️⃣','heading'=>'...','body'=>'...','tip'=>'...'], ... ]
 * @param array  $opts    Optional: ['note'=>'...', 'video_url'=>'...', 'docs_url'=>'...']
 */
function sgclabs_help_box( string $id, string $title, array $steps, array $opts = [] ): void {
    $stored_key = 'sgc_help_' . $id;
    ?>
    <div id="<?php echo esc_attr($id); ?>-wrap"
         style="background:#f0f7ff;border:1.5px solid #bfdbfe;border-radius:12px;margin-bottom:20px;overflow:hidden;">

        <!-- Header — click to toggle -->
        <div onclick="sgcHelpToggle('<?php echo esc_js($id); ?>')"
             style="display:flex;align-items:center;gap:10px;padding:12px 16px;cursor:pointer;user-select:none;">
            <span style="font-size:18px;">📘</span>
            <span style="flex:1;font-size:13px;font-weight:700;color:#1e40af;"><?php echo esc_html($title); ?></span>
            <span style="font-size:11px;color:#3b82f6;font-weight:600;background:#dbeafe;padding:3px 10px;border-radius:20px;">
                SOP Guide
            </span>
            <span id="<?php echo esc_attr($id); ?>-chev" style="font-size:12px;color:#3b82f6;font-weight:600;margin-left:4px;">▼ Show</span>
        </div>

        <!-- Body — collapsible -->
        <div id="<?php echo esc_attr($id); ?>-body" style="display:none;padding:0 16px 16px;">

            <!-- Steps -->
            <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:14px;">
            <?php foreach ( $steps as $i => $step ) :
                $num  = $i + 1;
                $icon = $step['icon'] ?? "Step {$num}";
            ?>
            <div style="display:flex;gap:12px;background:#fff;border-radius:10px;padding:12px 14px;border:1px solid #dbeafe;">
                <!-- Step number circle -->
                <div style="width:32px;height:32px;border-radius:50%;background:#1e40af;color:#fff;
                    display:flex;align-items:center;justify-content:center;font-size:13px;
                    font-weight:700;flex-shrink:0;"><?php echo $num; ?></div>
                <div style="flex:1;">
                    <div style="font-size:13px;font-weight:700;color:#1e3a8a;margin-bottom:3px;">
                        <?php echo $icon; ?> <?php echo esc_html( $step['heading'] ?? '' ); ?>
                    </div>
                    <div style="font-size:12px;color:#374151;line-height:1.6;">
                        <?php echo wp_kses_post( $step['body'] ?? '' ); ?>
                    </div>
                    <?php if ( ! empty( $step['tip'] ) ) : ?>
                    <div style="margin-top:6px;background:#fefce8;border-left:3px solid #f59e0b;
                        padding:6px 10px;border-radius:0 6px 6px 0;font-size:11px;color:#854d0e;">
                        💡 <strong>Tip:</strong> <?php echo esc_html( $step['tip'] ); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ( ! empty( $step['screenshot'] ) ) : ?>
                    <div style="margin-top:8px;background:#f1f5f9;border:1px dashed #cbd5e1;
                        border-radius:6px;padding:10px;text-align:center;font-size:11px;color:#94a3b8;">
                        🖼️ Screenshot: <em><?php echo esc_html( $step['screenshot'] ); ?></em>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            </div>

            <?php if ( ! empty( $opts['note'] ) ) : ?>
            <div style="background:#fef9c3;border:1px solid #fde047;border-radius:8px;
                padding:10px 14px;font-size:12px;color:#854d0e;margin-bottom:12px;">
                ⚠️ <?php echo esc_html( $opts['note'] ); ?>
            </div>
            <?php endif; ?>

            <!-- Footer links -->
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <?php if ( ! empty( $opts['docs_url'] ) ) : ?>
                <a href="<?php echo esc_url($opts['docs_url']); ?>" target="_blank"
                   style="font-size:11px;color:#2563eb;text-decoration:none;background:#eff6ff;
                   padding:4px 12px;border-radius:20px;border:1px solid #bfdbfe;">
                   📄 Full Documentation
                </a>
                <?php endif; ?>
                <button onclick="sgcHelpDismiss('<?php echo esc_js($id); ?>')"
                    style="font-size:11px;color:#6b7280;background:none;border:none;cursor:pointer;margin-left:auto;">
                    ✕ Don't show again
                </button>
            </div>
        </div>
    </div>

    <script>
    (function() {
        var id   = '<?php echo esc_js($id); ?>';
        var key  = 'sgc_help_' + id;

        // Auto-hide if dismissed before
        if (localStorage.getItem(key) === 'dismissed') {
            var wrap = document.getElementById(id + '-wrap');
            if (wrap) wrap.style.display = 'none';
        }

        window.sgcHelpToggle = window.sgcHelpToggle || function(boxId) {
            var body = document.getElementById(boxId + '-body');
            var chev = document.getElementById(boxId + '-chev');
            if (!body) return;
            var open = body.style.display !== 'none';
            body.style.display = open ? 'none' : 'block';
            chev.textContent   = open ? '▼ Show' : '▲ Hide';
        };

        window.sgcHelpDismiss = window.sgcHelpDismiss || function(boxId) {
            localStorage.setItem('sgc_help_' + boxId, 'dismissed');
            var wrap = document.getElementById(boxId + '-wrap');
            if (wrap) wrap.style.display = 'none';
        };
    })();
    </script>
    <?php
}

// ── ★ v7.5.2 MODULE GUARD — per-module on/off switches + automatic safe mode ──────
require_once SGCLABS_PATH . 'includes/core/sgc-module-guard.php';
function sgclabs_module_guard_register(): void {
    $modules = [];
    foreach ( array_merge( glob( SGCLABS_PATH . 'includes/*.php' ) ?: [], glob( SGCLABS_PATH . 'includes/international/*.php' ) ?: [] ) as $f ) {
        if ( in_array( basename( $f ), [ 'index.php', 'loader.php' ], true ) ) continue;
        $key   = preg_replace( '#\.php$#', '', substr( wp_normalize_path( $f ), strlen( wp_normalize_path( SGCLABS_PATH . 'includes/' ) ) ) ); // relative key (register() not called yet)
        $label = ucwords( str_replace( [ 'class-sgclabs-', 'class-sgc-', 'class-', 'international/', '-' ], [ '', '', '', '🌍 ', ' ' ], $key ) );
        $modules[ $key ] = $label;
    }
    ksort( $modules );
    SGC_Module_Guard::register( 'sgc-site-manager-pro', 'SGC Site Manager Pro', SGCLABS_PATH, $modules, [
        'class-sgclabs-module', 'feature-control', 'module-control-bar', 'general-settings', 'ai-provider',
        'sgc-integration-status', 'plugin-health-monitor', 'auto-repair', 'class-sgc-auto-updater', 'debug-log-protect',
    ] );
}
sgclabs_module_guard_register();

// ── OOP BASE CLASS (must load before any OOP module) ───────
sgclabs_include_file(SGCLABS_PATH . 'includes/class-sgclabs-module.php');

// ── ★ v6.9.0 CORE INFRASTRUCTURE ────────────────────────────
// Module Registry, Security Audit, Updater, Module Browser admin, Security admin
sgclabs_include_file(SGCLABS_PATH . 'includes/core/loader.php');
sgclabs_include_file(SGCLABS_PATH . 'includes/core/head-dedupe.php');   // ★ v7.5.1 one canonical / og / hreflang / Product schema per page

// ── ★ v6.9.0 INTERNATIONAL HUB ───────────────────────────────
// GDPR Consent, Multi-Currency, Wishlist, Recently Viewed, Compare, Quick View, REST, Login Hardening
sgclabs_include_file(SGCLABS_PATH . 'includes/international/loader.php');

sgclabs_include_file(SGCLABS_PATH . 'includes/feature-control.php');             // ★ Feature Control Center — master toggles (v6.8.5)
sgclabs_include_file(SGCLABS_PATH . 'includes/module-control-bar.php');          // ★ Reusable toggle/radio control bar (v6.6.5)

// ── GROUP A: Always — frontend + admin both ────────────────
sgclabs_include_file(SGCLABS_PATH . 'includes/google-compliance.php');       // CWV, lazy load, canonical
sgclabs_include_file(SGCLABS_PATH . 'includes/site-fixes.php');              // CSS fixes, frontend hooks
sgclabs_include_file(SGCLABS_PATH . 'includes/header-footer.php');           // Header bar, nav fix
// announcement-bar.php removed v6.6.5
sgclabs_include_file(SGCLABS_PATH . 'includes/image-seo.php');               // Image schema, alt inject
sgclabs_include_file(SGCLABS_PATH . 'includes/seo-manager.php');             // Meta inject, wp_head
sgclabs_include_file(SGCLABS_PATH . 'includes/schema-engine.php');           // JSON-LD schema output
sgclabs_include_file(SGCLABS_PATH . 'includes/seo-metabox.php');             // Product meta frontend
// auto-seo-engine.php removed v6.6.5 (consolidated into ai-content-engine)
sgclabs_include_file(SGCLABS_PATH . 'includes/woo-local-seo.php');           // WooCommerce SEO hooks
sgclabs_include_file(SGCLABS_PATH . 'includes/multisite-profiles.php');      // Profile SEO injection
sgclabs_include_file(SGCLABS_PATH . 'includes/multilingual-seo.php');        // Hreflang + multilang
sgclabs_include_file(SGCLABS_PATH . 'includes/global-keywords-engine.php');  // Keyword meta tags
sgclabs_include_file(SGCLABS_PATH . 'includes/ai-provider.php');             // ★ Unified AI Provider (Claude/OpenAI/Gemini/Mistral/DeepSeek)
sgclabs_include_file(SGCLABS_PATH . 'includes/ai-content-engine.php');       // ★ Central AI Content Engine v6.6.3 (One-Click Fix, Bulk AI, Keywords)
// ai-claude-seo.php removed v6.6.5 (consolidated into ai-content-engine)
sgclabs_include_file(SGCLABS_PATH . 'includes/related-product-links.php');   // Frontend content filter
sgclabs_include_file(SGCLABS_PATH . 'includes/size-variant-linker.php');     // Size pill filter (v6.6.3)
sgclabs_include_file(SGCLABS_PATH . 'includes/rankmath-parity.php');         // RankMath compatibility (v7.2.1)
sgclabs_include_file(SGCLABS_PATH . 'includes/multisite-seo-differentiator.php'); // 5-Domain AI SEO (v7.2.1)
sgclabs_include_file(SGCLABS_PATH . 'includes/data-migrator.php');             // Data Health & Migration (v7.2.1)
sgclabs_include_file(SGCLABS_PATH . 'includes/site-doctor.php');               // Site Doctor Auto-Heal (v7.3.0)
sgclabs_include_file( SGCLABS_PATH . 'includes/plugin-health-monitor.php' );     // ★ Plugin Health Monitor v1.0.0 (auto-scan on WP/WC update)
sgclabs_include_file( SGCLABS_PATH . 'includes/auto-repair.php' );               // ★ Auto-Repair System v1.0.0 (daily scan + AI analysis)
sgclabs_include_file( SGCLABS_PATH . 'includes/security-hardening.php' );        // ★ Security Hardening v1.0.0 (rate limit, SQLi/XSS detect, upload validate)
sgclabs_include_file( SGCLABS_PATH . 'includes/wp-security.php' );               // ★ WP Security v1.0.0 (headers, malware scan, core integrity)
sgclabs_include_file( SGCLABS_PATH . 'includes/debug-log-protect.php' );         // ★ Debug Log Protection v7.4.8 (blocks public debug.log access)
sgclabs_include_file( SGCLABS_PATH . 'includes/class-sgc-auto-updater.php' );     // ★ SGC Auto Updater v1.0.0 (one-click update via labsglass.com)
if ( class_exists( 'SGC_Auto_Updater' ) ) {
    SGC_Auto_Updater::register([
        'plugin_id'       => 'sgc-site-manager-pro',
        'plugin_slug'     => 'sgc-site-manager-pro/sgclabs-manager.php',
        'current_version' => SGCLABS_BASE_VERSION,
        'plugin_name'     => 'SGC LABS® Site Manager Pro',
    ]);
}
sgclabs_include_file(SGCLABS_PATH . 'includes/keywords.php');                // Keyword DB helpers
sgclabs_include_file(SGCLABS_PATH . 'includes/inquiry-form.php');            // Public form handler
sgclabs_include_file(SGCLABS_PATH . 'includes/sgc-inquiry-handler.php');     // ★ Unified inquiry handler v2.0 (REST+AJAX+multi-site fix)
sgclabs_include_file(SGCLABS_PATH . 'includes/get-a-quote.php');             // Public quote form
// sgclabs_include_file(SGCLABS_PATH . 'includes/b2b-quote-shortcodes.php');   // DISABLED v7.4.9: superseded by sgc-unified-quote.php (it saved leads into a wp_option, bypassing the DB). Re-enable this line to revert.
sgclabs_include_file(SGCLABS_PATH . 'includes/extras.php');                  // Chatbot, WhatsApp (public)
sgclabs_include_file(SGCLABS_PATH . 'includes/sitemap.php');                 // Sitemap hooks
sgclabs_include_file(SGCLABS_PATH . 'includes/extended-sitemaps.php');       // Extended sitemap

// ── GROUP B: Always load (hooks are context-aware internally) ─
sgclabs_include_file(SGCLABS_PATH . 'includes/ai-control-center.php');      // AI Control Center (v6.6.3)
// ai-content-generator.php removed v6.6.5 (consolidated into ai-content-engine)
// ai-seo-features.php removed v6.8.7 (consolidated into ai-content-engine)
sgclabs_include_file(SGCLABS_PATH . 'includes/advanced-seo-tools.php');     // htaccess, GA4, import
sgclabs_include_file(SGCLABS_PATH . 'includes/advanced-admin-pages.php');   // IndexNow, bulk editor
sgclabs_include_file(SGCLABS_PATH . 'includes/broken-links.php');           // Broken link scanner
sgclabs_include_file(SGCLABS_PATH . 'includes/internal-links.php');         // Internal link builder
sgclabs_include_file(SGCLABS_PATH . 'includes/keyword-manager.php');        // Keyword DB manager
sgclabs_include_file(SGCLABS_PATH . 'includes/rank-tracker.php');           // Rank tracking
sgclabs_include_file(SGCLABS_PATH . 'includes/redirections.php');           // 301 redirect manager (needs template_redirect)
sgclabs_include_file(SGCLABS_PATH . 'includes/site-crawler.php');           // SEO crawler/audit
sgclabs_include_file(SGCLABS_PATH . 'includes/site-tools.php');             // Robots, RSS tools
sgclabs_include_file(SGCLABS_PATH . 'includes/woo-image-sizes.php');        // WooCommerce image sizes
// focus-keyword-generator.php, seo-injector.php, product-content-rewriter.php removed v6.6.5
sgclabs_include_file(SGCLABS_PATH . 'includes/gsc-connect.php');            // Google Search Console
sgclabs_include_file(SGCLABS_PATH . 'includes/seo-hub.php');                // SEO Hub unified page
sgclabs_include_file(SGCLABS_PATH . 'includes/performance.php');             // Performance module (v6.6.3)
sgclabs_include_file(SGCLABS_PATH . 'includes/b2b-quote-engine.php');           // B2B Quote Engine (v1.0)
sgclabs_include_file(SGCLABS_PATH . 'includes/lab-tech-specs.php');             // Lab Tech Specs + Schema (v1.0)
sgclabs_include_file(SGCLABS_PATH . 'includes/ai-inquiry-reply.php');           // AI Inquiry Reply Center (v1.0)
sgclabs_include_file(SGCLABS_PATH . 'includes/inquiry-automation.php');         // ★ Inquiry Automation: follow-up cron, CRM sync, pipeline (v7.3.1)
sgclabs_include_file(SGCLABS_PATH . 'includes/sgc-unified-quote.php');           // UNIFIED Quote & Lead System v7.4.9 - one table (sgc_leads), one form, one cart, hybrid Buy-Now. Mirrors to sgclabs_inquiries for Invoice/CRM.
sgclabs_include_file(SGCLABS_PATH . 'includes/general-settings.php');           // ★ General Settings page (v7.3.1 — fixes fatal error)

// ── NEW v6.6.3 OOP Modules ────────────────────────────────
sgclabs_include_file(SGCLABS_PATH . 'includes/shipping-rules.php');             // Automatic Shipping Rules (v6.6.0)
// ai-focus-keywords.php removed v6.6.5 (consolidated into ai-content-engine)

// ── NEW v6.6.5 Modules ──────────────────────────────────
sgclabs_include_file(SGCLABS_PATH . 'includes/pdf-brochure.php');               // PDF Brochure Generator (v6.6.5)
sgclabs_include_file(SGCLABS_PATH . 'includes/ai-progress-dashboard.php');     // ★ AI Progress Dashboard (v6.6.6)
sgclabs_include_file(SGCLABS_PATH . 'includes/seo-power-features.php');         // ★ SEO Power Features: Image Rename + Backup/Rollback + Competitor Intel (v6.6.6)
sgclabs_include_file(SGCLABS_PATH . 'includes/safe-delete.php');                // ★ Safe Delete: Pre-delete export wizard (v6.6.6)
// announcement-bar.php removed v6.8.7 — not needed
// background-processor.php removed v6.8.7 — not working
sgclabs_include_file(SGCLABS_PATH . 'includes/ai-image-alt.php');               // ★ AI Image ALT Text Bulk Generator (v6.7.0)
sgclabs_include_file(SGCLABS_PATH . 'includes/wa-share.php');                   // ★ WhatsApp Share & Lead Gen (v6.7.0)
sgclabs_include_file(SGCLABS_PATH . 'includes/social-share.php');               // ★ Universal Social Share Bar (v6.9.6)
sgclabs_include_file(SGCLABS_PATH . 'includes/b2b-pricing.php');                // ★ B2B Pricing, MOQ & HSN Code (v6.7.1)
// price-compare.php removed v6.8.7 — old widget not needed
sgclabs_include_file(SGCLABS_PATH . 'includes/reorder-reminder.php');           // ★ Predictive Reorder Reminder System (v6.7.5)
sgclabs_include_file(SGCLABS_PATH . 'includes/urgency-widget.php');
sgclabs_include_file(SGCLABS_PATH . 'includes/bulk-order-table.php');           // ★ Bulk Order Table — VWR Style (v6.8.0)
sgclabs_include_file(SGCLABS_PATH . 'includes/amazon-arabic.php');              // ★ Amazon Business + Arabic SEO (v6.8.0)
sgclabs_include_file(SGCLABS_PATH . 'includes/market-compare.php');             // ★ Marketplace Compare Widget (v6.8.0)
sgclabs_include_file(SGCLABS_PATH . 'includes/bulk-quick-editor.php');          // ★ Quick Bulk Editor — SEO panel + Image Assigner (v6.8.7)
sgclabs_include_file(SGCLABS_PATH . 'includes/export-seo-engine.php');          // ★ Export SEO Engine v7.4.0 — AggregateRating, FAQ schema, Country pages, GMC feed, Webmaster verify, Bulk SEO health
sgclabs_include_file(SGCLABS_PATH . 'includes/seo-health-dashboard.php');       // ★ Bulk SEO Health Dashboard v7.4.0 — Score all 7000+ products

// ============================================================
// v6.6.3: HERO GALLERY / SLIDER IMAGE FIX — SAFE VERSION
// Root cause of broken hero: wp_get_attachment_image_attributes
// filter at priority 99 was interfering with Elementor's gallery
// JS data rendering, causing raw JSON to appear instead of images.
//
// SAFE FIX: Pure CSS approach — no PHP filters touching images.
// Elementor/Swiper/Slick sliders use data-src for lazy loading;
// WordPress native loading="lazy" attribute is output ONLY via
// google-compliance.php which we now exclude on front/home/pages.
// ============================================================

// Disable our plugin's lazy loading on front page / home / regular pages
// so Elementor gallery and hero sliders are never broken.
add_filter( 'sgclabs_lazy_images_enabled', function( $enabled ) {
    if ( ! is_admin() && ( is_front_page() || is_home() || ( is_page() && ! is_product() ) ) ) {
        return false;
    }
    return $enabled;
} );

// Override google-compliance lazy loading checks using the filter above
add_action( 'wp_head', function() {
    if ( is_admin() ) return;
    if ( is_front_page() || is_home() || ( is_page() && ! is_product() ) ) {
        // Inject inline CSS to ensure Elementor slider images always display
        echo '<style id="sgclabs-slider-fix">'
           . '.elementor-widget-image img,'
           . '.elementor-widget-image-carousel img,'
           . '.elementor-widget-media-carousel img,'
           . '.elementor-slides img,'
           . '.elementor-background-slideshow img,'
           . '.swiper-slide img,'
           . '.slick-slide img,'
           . '[data-widget_type="image.default"] img,'
           . '[data-widget_type="image-carousel.default"] img'
           . '{ visibility:visible !important; opacity:1 !important; display:block !important; }'
           . '</style>' . "\n";
    }
}, 5 );

// --- Admin Menu ---
if (file_exists(SGCLABS_PATH . 'admin/admin-menu.php')) {
    require_once SGCLABS_PATH . 'admin/admin-menu.php';
} else {
    error_log('SGC LABS Manager Pro missing admin-menu.php; plugin partially loaded.');
}

// ============================================================
// ACTIVATION
// ============================================================
register_activation_hook(__FILE__, 'sgclabs_activate');
function sgclabs_activate() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    add_option('sgclabs_header_phone', '+91 999 641 6935');
    add_option('sgclabs_header_email', 'sales@labsglass.com');
    add_option('sgclabs_header_active', '1');
    add_option('sgclabs_announcement_text', 'FREE SHIPPING on orders over 5000! | Worldwide Export Available');
    add_option('sgclabs_announcement_active', '1');
    add_option('sgclabs_wa_active',          '1');
    add_option('sgclabs_wa_number',          '919996416935');
    add_option('sgclabs_aichat_active',      '0');  // OFF by default — enable from Extras settings
    add_option('sgclabs_aichat_provider',    '');   // No default — user chooses in Extras settings
    add_option('sgclabs_ai_seo_provider',    '');   // No default — user chooses in SEO Hub → AI SEO
    add_option('sgclabs_aichat_legacy_mode', '0');
    add_option('sgclabs_aichat_company_name','SGC Labs® Support');
    add_option('sgclabs_aichat_phone',       '+91 999 641 6935');
    add_option('sgclabs_aichat_email',       'info@labsglass.com');
    add_option('sgclabs_aichat_whatsapp',    '919996416935');
    add_option('sgclabs_aichat_header_color','#03a84e');
    add_option('sgclabs_wc_currency_override', 'USD');
    add_option('sgclabs_currency_default', 'USD');
    add_option('sgclabs_fonts_active', '1');
    add_option('sgclabs_selected_font', 'Inter');
    add_option('sgclabs_cwv_active', '1');
    add_option('sgclabs_lazy_images', '1');
    add_option('sgclabs_canonical_active', '1');
    add_option('sgclabs_max_kw_density', '2.5');
    add_option('sgclabs_max_meta_keywords', '25');
    add_option('sgclabs_indexnow_active', '1');
    add_option('sgclabs_ext_link_nofollow', '1');
    add_option('sgclabs_ext_link_newtab', '1');
    add_option('sgclabs_auto_redirect_slug', '1');

    // Module toggle defaults (v6.6.3) — all ON by default, admin can disable
    add_option('sgclabs_auto_seo_engine_active',         '1');
    add_option('sgclabs_broken_links_active',            '1');
    add_option('sgclabs_extended_sitemaps_active',       '1');
    add_option('sgclabs_focus_kw_gen_active',            '1');
    add_option('sgclabs_global_keywords_active',         '1');
    add_option('sgclabs_gsc_connect_active',             '1');
    add_option('sgclabs_inquiry_form_active',            '1');
    add_option('sgclabs_keyword_manager_active',         '1');
    add_option('sgclabs_multilingual_seo_active',        '1');
    add_option('sgclabs_product_rewriter_active',        '1');
    add_option('sgclabs_rank_tracker_active',            '1');
    add_option('sgclabs_rankmath_parity_active',         '1');
    add_option('sgclabs_redirections_active',            '1');
    add_option('sgclabs_related_product_links_active',   '1');
    add_option('sgclabs_schema_engine_active',           '1');
    add_option('sgclabs_seo_injector_active',            '1');
    add_option('sgclabs_seo_metabox_active',             '1');
    add_option('sgclabs_site_crawler_active',            '1');
    add_option('sgclabs_site_fixes_active',              '1');
    add_option('sgclabs_ai_seo_features_active',         '1');
    add_option('sgclabs_woo_image_sizes_active',         '1');
    add_option('sgclabs_header_footer_active',           '1');

    // Performance module defaults (v6.6.3)
    add_option('sgclabs_perf_remove_bloat',   '1');
    add_option('sgclabs_perf_js_defer',       '1');
    add_option('sgclabs_perf_resource_hints', '1');
    add_option('sgclabs_perf_cart_fragments', '1');
    add_option('sgclabs_perf_heartbeat',      '1');
    add_option('sgclabs_perf_cache_headers',  '1');
    add_option('sgclabs_perf_php_gzip',       '0'); // Off by default — enable manually
    add_option('sgclabs_perf_page_cache',     '0'); // Off by default — enable manually
    add_option('sgclabs_perf_cache_ttl',      '3600');

    // B2B Quote Engine defaults (v1.0)
    add_option('sgclabs_quote_notify_email', get_option('admin_email'));
    add_option('sgclabs_quote_page_url',     home_url('/request-quote/'));

    // AI Inquiry Reply defaults (v1.0)
    add_option('sgclabs_ai_reply_tone',       'professional');
    add_option('sgclabs_ai_reply_from_name',  'SGC Labs Team');
    add_option('sgclabs_ai_reply_from_email', 'sales@labsglass.com');
    add_option('sgclabs_ai_reply_signature',  "Best regards,
SGC Labs® Sales Team
+91 999 641 6935 | sales@labsglass.com | labsglass.com");

    // v7.3.1: sgclabs_inquiries is now ALSO created here so Fix-Missing button and
    // plugin activation always guarantee the table exists — even before any form submit.
    // get-a-quote.php still runs its own dbDelta (harmless — adds new columns only).
    $tables = [
        // ── Unified Inquiry table (canonical schema) ─────────────────────────
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sgclabs_inquiries (
            id          mediumint(9)  NOT NULL AUTO_INCREMENT,
            inq_number  varchar(30)   DEFAULT NULL COMMENT 'INQ-YYYY-NNNN from sgc_doc_sequences',
            trade_type  varchar(10)   NOT NULL DEFAULT 'export' COMMENT 'export|domestic',
            name        varchar(100),
            email       varchar(100),
            phone       varchar(20),
            company     varchar(150),
            country     varchar(100),
            incoterm    varchar(20),
            port        varchar(100),
            product     varchar(200),
            qty         varchar(100),
            message     text,
            status      varchar(20)   DEFAULT 'new' COMMENT 'new|read|quoted|pi_sent|won|lost',
            pi_number   varchar(30)   DEFAULT NULL COMMENT 'Filled by Invoice Pro after PI created',
            source_type varchar(30)   DEFAULT 'quote' COMMENT 'quote|contact|b2b|manual|wc_order',
            follow_up_sent tinyint(1) DEFAULT 0 COMMENT '1 = 3-day follow-up email sent',
            follow_up_at   datetime   DEFAULT NULL COMMENT 'Timestamp follow-up was sent',
            created_at  datetime      DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY email_idx (email(80)),
            KEY status_idx (status),
            KEY inq_number_idx (inq_number)
        ) $charset",
        // ── Keyword rankings snapshot (daily/weekly position tracking) ────────
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sgclabs_kw_rankings (
            id          bigint(20)    NOT NULL AUTO_INCREMENT,
            keyword     varchar(500)  NOT NULL,
            position    float         DEFAULT 0,
            previous    float         DEFAULT 0 COMMENT 'Previous position for delta calculation',
            url         varchar(500)  DEFAULT '',
            impressions int           DEFAULT 0,
            clicks      int           DEFAULT 0,
            ctr         float         DEFAULT 0,
            recorded_date date        NOT NULL,
            source      varchar(20)   DEFAULT 'gsc' COMMENT 'gsc|manual',
            PRIMARY KEY (id),
            KEY kw_date_idx (keyword(191), recorded_date),
            KEY date_idx (recorded_date)
        ) $charset",
        // Broken links — only defined here
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sgclabs_broken_links (
            id          mediumint(9)  NOT NULL AUTO_INCREMENT,
            url         varchar(500),
            source_url  varchar(500),
            status_code int,
            last_checked datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset",
        // Keywords — global pool
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sgclabs_kw_global (
            id          mediumint(9)  NOT NULL AUTO_INCREMENT,
            keyword     varchar(500)  NOT NULL,
            language    varchar(10)   DEFAULT 'en',
            source      varchar(50)   DEFAULT 'manual',
            impressions int           DEFAULT 0,
            clicks      int           DEFAULT 0,
            position    float         DEFAULT 0,
            active      tinyint(1)    DEFAULT 1,
            created_at  datetime      DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset",
        // Keywords — per category
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sgclabs_kw_category (
            id           mediumint(9)  NOT NULL AUTO_INCREMENT,
            category_id  bigint(20)    NOT NULL,
            category_name varchar(200),
            keyword      varchar(500)  NOT NULL,
            language     varchar(10)   DEFAULT 'en',
            active       tinyint(1)    DEFAULT 1,
            created_at   datetime      DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset",
        // Site crawler pages
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sgclabs_crawl_pages (
            id          bigint(20)    NOT NULL AUTO_INCREMENT,
            url         varchar(500)  NOT NULL,
            post_id     bigint(20)    DEFAULT 0,
            post_type   varchar(50)   DEFAULT '',
            page_title  varchar(500)  DEFAULT '',
            h1          varchar(500)  DEFAULT '',
            meta_title  varchar(500)  DEFAULT '',
            meta_desc   text          DEFAULT '',
            word_count  int           DEFAULT 0,
            img_count   int           DEFAULT 0,
            img_no_alt  int           DEFAULT 0,
            status_code int           DEFAULT 0,
            load_time   float         DEFAULT 0,
            issues      text          DEFAULT '',
            seo_score   int           DEFAULT 0,
            crawled_at  datetime      DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY url_key (url(255))
        ) $charset",
        // Sitemap pings log
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sgclabs_sitemap_pings (
            id          bigint(20)    NOT NULL AUTO_INCREMENT,
            engine      varchar(50)   NOT NULL,
            sitemap_url varchar(500)  NOT NULL,
            response    int           DEFAULT 0,
            pinged_at   datetime      DEFAULT NULL,
            PRIMARY KEY (id)
        ) $charset",
        // GSC keyword import
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sgclabs_kw_gsc (
            id          mediumint(9)  NOT NULL AUTO_INCREMENT,
            keyword     varchar(500)  NOT NULL,
            impressions int           DEFAULT 0,
            clicks      int           DEFAULT 0,
            ctr         float         DEFAULT 0,
            position    float         DEFAULT 0,
            imported_at datetime      DEFAULT CURRENT_TIMESTAMP,
            active      tinyint(1)    DEFAULT 1,
            PRIMARY KEY (id)
        ) $charset",
        // SGC unified document sequence counter (shared across all SGC plugins)
        // Prevents duplicate/mismatched document numbers across INQ/QT/PI/INV/PKL/PAY
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sgc_doc_sequences (
            id          int(10)       NOT NULL AUTO_INCREMENT,
            doc_type    varchar(20)   NOT NULL COMMENT 'INQ|QT|PI_E|PI_D|INV|PKL|PAY',
            prefix      varchar(20)   NOT NULL,
            year        smallint(4)   NOT NULL,
            last_number int(10)       NOT NULL DEFAULT 0,
            updated_at  datetime      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY type_year (doc_type, year)
        ) $charset",
    ];
    foreach ( $tables as $sql ) {
        $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    }

    // Fire module-level activation hooks (e.g. B2B Quote Engine table creation)
    do_action('sgclabs_on_activate');

    // v6.6.8: Force update button colors + spacing to Option D (SGC Logo colors)
    update_option('sgclabs_quote_btn_color',      '#1565c0'); // SGC Blue
    update_option('sgclabs_quote_btn_text_color', '#ffffff');
    update_option('sgclabs_pdf_btn_color',        '#c62828'); // SGC Red
    update_option('sgclabs_pdf_btn_text_color',   '#ffffff');

    update_option('sgclabs_db_version', SGCLABS_VERSION);

    // Store auto-generated inner module versions every activation.
    sgclabs_store_module_versions();

    flush_rewrite_rules();
}

// Force color update on version upgrade (runs once after plugin update)
add_action('admin_init', function() {
    if ( get_option('sgclabs_btn_color_ver') !== '6.7.3' ) {
        update_option('sgclabs_quote_btn_color',      '#1565c0');
        update_option('sgclabs_quote_btn_text_color', '#ffffff');
        update_option('sgclabs_pdf_btn_color',        '#c62828');
        update_option('sgclabs_pdf_btn_text_color',   '#ffffff');
        update_option('sgclabs_btn_color_ver',        '6.7.3');
    }
});

add_action('admin_init', 'sgclabs_maybe_upgrade', 20);
function sgclabs_maybe_upgrade() {
    if (get_option('sgclabs_db_version', '0') === SGCLABS_VERSION) {
        sgclabs_store_module_versions();
        return;
    }
    sgclabs_activate();
    // Auto-run data health check after upgrade
    do_action( 'sgclabs_after_upgrade' );
}

register_deactivation_hook( __FILE__, 'sgclabs_deactivate' );

/**
 * Plugin deactivation callback.
 * Flushes rewrite rules so redirections are cleaned up.
 *
 * @since 6.6.2
 */
function sgclabs_deactivate(): void {
    flush_rewrite_rules();
}

// ============================================================
// i18n — load text domain for translations
// ============================================================
// SGC Ecosystem Integration
require_once plugin_dir_path(__FILE__) . 'includes/sgc-integration-status.php';
add_action( 'plugins_loaded', 'sgclabs_load_textdomain' );

// ── WooCommerce HPOS + Cart Blocks compatibility ───────────────────────────
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
    }
} );

/**
 * Load plugin text domain for translations.
 *
 * @since 6.6.2
 */
function sgclabs_load_textdomain(): void {
    load_plugin_textdomain(
        'sgclabs-manager',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );
}

// ── v7.4.9: Unified SGC Ecosystem Dashboard Widget ───────────────────────────
add_action( 'wp_dashboard_setup', 'sgclabs_register_ecosystem_widget' );

function sgclabs_register_ecosystem_widget(): void {
    wp_add_dashboard_widget(
        'sgclabs_ecosystem_status',
        '🧪 SGC LABS® — Plugin Ecosystem',
        'sgclabs_render_ecosystem_widget'
    );
}

function sgclabs_render_ecosystem_widget(): void {
    global $wpdb;

    $plugins = [
        'Site Manager Pro'     => [ 'const' => 'SGCLABS_MANAGER_VERSION', 'color' => '#185FA5' ],
        'Invoice & Packlist'   => [ 'const' => 'SGC_IIP_VERSION',         'color' => '#0F6E56' ],
        'Product Manager Pro'  => [ 'const' => 'SGC_PM_VERSION',          'color' => '#92400e' ],
        'Smart CRM Pro'        => [ 'const' => 'SGC_CRM_VERSION',         'color' => '#7e22ce' ],
    ];

    // Quick stats
    $open_pis   = 0;
    $crm_count  = 0;
    $inq_count  = 0;
    $iip_tbl    = $wpdb->prefix . 'sgc_iip_inquiries';
    $crm_tbl    = $wpdb->prefix . 'sgc_crm_contacts';

    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$iip_tbl}'" ) === $iip_tbl ) { // phpcs:ignore
        $inq_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$iip_tbl}` WHERE status NOT IN ('won','lost','cancelled')" ); // phpcs:ignore
        $open_pis  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$iip_tbl}` WHERE pi_number IS NOT NULL AND status NOT IN ('won','lost')" ); // phpcs:ignore
    }
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$crm_tbl}'" ) === $crm_tbl ) { // phpcs:ignore
        $crm_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$crm_tbl}` WHERE status='subscribed'" ); // phpcs:ignore
    }

    echo '<div style="font-family:sans-serif">';

    // Plugin status grid
    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:12px">';
    foreach ( $plugins as $name => $info ) {
        $ver    = defined( $info['const'] ) ? constant( $info['const'] ) : null;
        $active = $ver ? '✅' : '⭕';
        $label  = $ver ? 'v' . $ver : 'Inactive';
        printf(
            '<div style="background:#f8f9fa;border-left:3px solid %s;padding:6px 8px;border-radius:4px;font-size:12px">
                <div style="font-weight:600;color:#1a1a2e">%s %s</div>
                <div style="color:#666;margin-top:2px">%s</div>
            </div>',
            esc_attr( $info['color'] ),
            $active,
            esc_html( $name ),
            esc_html( $label )
        );
    }
    echo '</div>';

    // Key metrics
    echo '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;margin-bottom:10px">';
    $metrics = [
        [ 'Open inquiries', $inq_count, '#185FA5' ],
        [ 'Open PIs',       $open_pis,  '#0F6E56' ],
        [ 'CRM contacts',   $crm_count, '#7e22ce' ],
    ];
    foreach ( $metrics as [ $label, $val, $col ] ) {
        printf(
            '<div style="text-align:center;background:#f8f9fa;border-radius:4px;padding:8px">
                <div style="font-size:20px;font-weight:700;color:%s">%d</div>
                <div style="font-size:11px;color:#888">%s</div>
            </div>',
            esc_attr( $col ), (int) $val, esc_html( $label )
        );
    }
    echo '</div>';

    // AI key status
    $providers = function_exists( 'sgclabs_ai_providers' ) ? sgclabs_ai_providers() : [];
    $keys_ok   = 0;
    foreach ( $providers as $id => $p ) {
        $raw = get_option( $p['option_key'], '' );
        if ( ! empty( $raw ) ) $keys_ok++;
    }
    echo '<div style="font-size:11px;color:#888;border-top:1px solid #eee;padding-top:8px;margin-top:4px">';
    echo 'AI providers configured: <strong>' . (int) $keys_ok . '/' . count( $providers ) . '</strong>';
    echo ' &nbsp;|&nbsp; ';
    echo '<a href="' . esc_url( admin_url( 'admin.php?page=sgclabs-manager' ) ) . '" style="color:#185FA5">Open Dashboard →</a>';
    echo '</div>';

    echo '</div>';
}
