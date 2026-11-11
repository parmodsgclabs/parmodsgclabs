<?php
if (!defined('ABSPATH')) exit;

/**
 * ============================================================
 * SGC LABS® PERFORMANCE MODULE — v6.6.3
 * ============================================================
 * No CDN or cache plugin needed — pure WordPress/PHP level.
 *
 * Features:
 *  1. Browser caching via .htaccess (Apache)
 *  2. Gzip / Deflate compression via .htaccess
 *  3. WordPress Transient Object Cache (DB-level)
 *  4. JS defer / async (non-blocking scripts)
 *  5. Remove unused WordPress bloat (emoji, embeds, etc.)
 *  6. DNS prefetch / preconnect hints
 *  7. WooCommerce cart fragments disable on non-cart pages
 *  8. Heartbeat API throttle (reduces admin AJAX load)
 *  9. Self-hosted Google Fonts cache (serve from WP)
 * 10. Admin UI with toggle per feature
 * ============================================================
 */

// ── Menu registration ──────────────────────────────────────
add_action('admin_menu', function () {
    add_submenu_page(
        'sgclabs-manager',
        'Performance',
        '⚡ Performance',
        'manage_options',
        'sgclabs-performance',
        'sgclabs_performance_page'
    );
}, 25);

// ============================================================
// FEATURE 1 — Remove WordPress Bloat
// emoji scripts, oembed, wlwmanifest, generator tag, etc.
// ============================================================
add_action('init', 'sgclabs_perf_remove_bloat');
function sgclabs_perf_remove_bloat() {
    if (get_option('sgclabs_perf_remove_bloat', '1') !== '1') return;

    // Emoji — unnecessary JS/CSS for most sites
    remove_action('wp_head',             'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles',     'print_emoji_styles');
    remove_action('admin_print_styles',  'print_emoji_styles');
    remove_filter('the_content_feed',    'wp_staticize_emoji');
    remove_filter('comment_text_rss',    'wp_staticize_emoji');
    remove_filter('wp_mail',             'wp_staticize_emoji_for_email');
    add_filter('tiny_mce_plugins', function($p) { return array_diff($p, ['wpemoji']); });

    // oEmbed — loads extra JS for embeds, disable if not needed
    remove_action('wp_head',      'wp_oembed_add_discovery_links');
    remove_action('wp_head',      'wp_oembed_add_host_js');
    remove_action('rest_api_init','wp_oembed_register_route');
    remove_filter('oembed_dataparse', 'wp_filter_oembed_result');

    // Other bloat in <head>
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'wp_shortlink_wp_head');
    remove_action('wp_head', 'feed_links_extra', 3);

    // Remove REST API link from header (security + performance)
    remove_action('wp_head', 'rest_output_link_wp_head');
    remove_action('template_redirect', 'rest_output_link_header', 11);
}

// ============================================================
// FEATURE 2 — JS Defer / Async
// Adds defer to non-critical scripts so they don't block render
// ============================================================
add_filter('script_loader_tag', 'sgclabs_perf_defer_scripts', 10, 3);
function sgclabs_perf_defer_scripts($tag, $handle, $src) {
    if (get_option('sgclabs_perf_js_defer', '1') !== '1') return $tag;
    if (is_admin()) return $tag;

    // Never defer these — deferring breaks them
    $never_defer = [
        'jquery',
        'jquery-core',
        'jquery-migrate',
        'elementor-frontend',
        'elementor-pro-frontend',
        'elementor-swiper',        // v6.6.3: Elementor hero/carousel slider
        'swiper',                  // v6.6.3: Swiper.js used by Elementor
        'e-swiper',                // v6.6.3: Elementor Pro swiper alias
        'wp-embed',
        'wc-cart-fragments',
        'woocommerce',
    ];

    if (in_array($handle, $never_defer, true)) return $tag;

    // Already has defer or async — skip
    if (strpos($tag, ' defer') !== false || strpos($tag, ' async') !== false) return $tag;

    // Add defer
    return str_replace(' src=', ' defer src=', $tag);
}

// ============================================================
// FEATURE 3 — DNS Prefetch + Preconnect hints
// Speeds up third-party resource loading
// ============================================================
add_action('wp_head', 'sgclabs_perf_resource_hints', 1);
function sgclabs_perf_resource_hints() {
    if (get_option('sgclabs_perf_resource_hints', '1') !== '1') return;
    if (is_admin()) return;

    $hints = [
        'preconnect' => [
            'https://fonts.googleapis.com',
            'https://fonts.gstatic.com',
            'https://www.google-analytics.com',
            'https://www.googletagmanager.com',
        ],
        'dns-prefetch' => [
            '//api.whatsapp.com',
            '//wa.me',
            '//pagead2.googlesyndication.com',
        ],
    ];

    foreach ($hints['preconnect'] as $url) {
        echo '<link rel="preconnect" href="' . esc_url($url) . '" crossorigin>' . "\n";
    }
    foreach ($hints['dns-prefetch'] as $url) {
        echo '<link rel="dns-prefetch" href="' . esc_url($url) . '">' . "\n";
    }
}

// ============================================================
// FEATURE 4 — WooCommerce cart fragments disable on non-cart pages
// This is one of the biggest WooCommerce performance killers —
// it fires an AJAX request on EVERY page load to check cart.
// Safe to disable on pages where cart is not shown.
// ============================================================
add_action('wp_enqueue_scripts', 'sgclabs_perf_disable_cart_fragments', 99);
function sgclabs_perf_disable_cart_fragments() {
    if (get_option('sgclabs_perf_cart_fragments', '1') !== '1') return;
    if (!function_exists('WC')) return;
    if (is_cart() || is_checkout() || is_account_page()) return;

    wp_dequeue_script('wc-cart-fragments');
    wp_deregister_script('wc-cart-fragments');
}

// ============================================================
// FEATURE 5 — WordPress Heartbeat throttle
// By default Heartbeat fires every 15s in admin, 60s on front.
// Throttle it to reduce server load without breaking autosave.
// ============================================================
add_filter('heartbeat_settings', 'sgclabs_perf_heartbeat');
function sgclabs_perf_heartbeat($settings) {
    if (get_option('sgclabs_perf_heartbeat', '1') !== '1') return $settings;
    $settings['interval'] = 60; // seconds (default 15s admin / 60s front)
    return $settings;
}
// Disable heartbeat completely on front-end (not needed there)
add_action('init', function () {
    if (get_option('sgclabs_perf_heartbeat', '1') !== '1') return;
    if (!is_admin()) {
        wp_deregister_script('heartbeat');
    }
});

// ============================================================
// FEATURE 6 — PHP Output Buffering + Gzip (no .htaccess needed)
// Falls back to PHP-level gzip when Apache mod_deflate not available
// ============================================================
add_action('init', 'sgclabs_perf_php_gzip', 1);
/**
 * v7.5.1: true when a dedicated cache plugin handles page cache / compression / headers.
 * Our PHP gzip, Cache-Control headers and transient page cache would fight with it
 * (double compression, conflicting headers, stale double-cached HTML).
 */
function sgclabs_perf_cache_plugin_active(): bool {
    return defined('LSCWP_V') || defined('WP_ROCKET_VERSION') || defined('W3TC') || defined('WPCACHEHOME') || defined('AUTOPTIMIZE_PLUGIN_VERSION') || class_exists('SiteGround_Optimizer\\Loader') || function_exists('wpfc_clear_all_cache');
}

// LiteSpeed Cache: pages vary by our currency / wishlist / compare / recently-viewed cookies,
// otherwise one visitor's cached HTML (e.g. EUR prices, wishlist bar) is served to everyone.
function sgclabs_perf_vary_cookie_names(): array {
    return ['sgc_currency', 'sgc_wishlist', 'sgc_compare', 'sgc_rv'];
}
// .htaccess Cache-Vary rule (server level) …
add_filter('litespeed_vary_cookies', function($cookies) {
    $cookies = is_array($cookies) ? $cookies : [];
    return array_values(array_unique(array_merge($cookies, sgclabs_perf_vary_cookie_names())));
});
// … and the per-request vary (only cookies actually present on this visit).
add_filter('litespeed_vary_curr_cookies', function($cookies) {
    $cookies = is_array($cookies) ? $cookies : [];
    foreach (sgclabs_perf_vary_cookie_names() as $name) {
        if (!empty($_COOKIE[$name])) $cookies[] = $name;
    }
    return array_values(array_unique($cookies));
});

function sgclabs_perf_php_gzip() {
    if (get_option('sgclabs_perf_php_gzip', '0') !== '1') return;
    if (sgclabs_perf_cache_plugin_active()) return;
    if (is_admin()) return;
    if (!extension_loaded('zlib') || ini_get('zlib.output_compression')) return;
    if (headers_sent()) return;

    ob_start('ob_gzhandler');
}

// ============================================================
// FEATURE 7 — Browser Cache headers via PHP
// Sends proper Cache-Control + Expires headers for static assets
// Works even without Apache .htaccess mod_expires
// ============================================================
add_action('send_headers', 'sgclabs_perf_cache_headers');
function sgclabs_perf_cache_headers() {
    if (get_option('sgclabs_perf_cache_headers', '1') !== '1') return;
    if (sgclabs_perf_cache_plugin_active()) return; // LiteSpeed / WP Rocket send their own
    if (is_admin() || is_user_logged_in()) return;
    if (is_feed() || is_robots()) return;

    // For logged-out users on public pages: cache for 1 hour
    header('Cache-Control: public, max-age=3600, s-maxage=3600');
    header('Vary: Accept-Encoding');
}

// ============================================================
// FEATURE 8 — Transient Object Cache (DB-level page caching)
// Caches full HTML output of non-logged-in users in DB transients.
// Simple, no file system, no Redis needed. Good for shared hosting.
// ============================================================
add_action('template_redirect', 'sgclabs_perf_page_cache_serve', 1);
function sgclabs_perf_page_cache_serve() {
    if (get_option('sgclabs_perf_page_cache', '0') !== '1') return;
    if (sgclabs_perf_cache_plugin_active()) return; // never double-cache under LiteSpeed etc.
    if (is_admin() || is_user_logged_in()) return;
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') return;
    if (!empty($_GET)) return; // Don't cache URLs with query strings
    if (is_cart() || is_checkout() || is_account_page()) return;

    $cache_key = 'sgclabs_page_' . md5($_SERVER['REQUEST_URI']);
    $cached    = get_transient($cache_key);

    if ($cached !== false) {
        echo $cached;
        echo '<!-- SGC LABS® Cache hit: ' . esc_html($_SERVER['REQUEST_URI']) . ' -->';
        exit;
    }

    // Start buffering — save output on shutdown
    ob_start();
    add_action('shutdown', function () use ($cache_key) {
        // Guard: only get_clean if OUR buffer is still open
        if (ob_get_level() > 0) {
            $html = ob_get_clean();
        } else {
            $html = '';
        }
        if ($html && strlen($html) > 500) {
            $ttl = (int) get_option('sgclabs_perf_cache_ttl', 3600);
            set_transient($cache_key, $html, $ttl);
        }
        if ($html) echo $html;
    }, 0);
}

// Clear page cache on post save / update
add_action('save_post',       'sgclabs_perf_flush_page_cache');
add_action('woocommerce_product_set_stock', 'sgclabs_perf_flush_page_cache');
function sgclabs_perf_flush_page_cache($post_id = 0) {
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sgclabs_page_%' OR option_name LIKE '_transient_timeout_sgclabs_page_%'");
}

// AJAX: Flush cache manually
add_action('wp_ajax_sgclabs_flush_cache', function () {
    check_ajax_referer('sgclabs_perf_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    sgclabs_perf_flush_page_cache();
    wp_send_json_success(['message' => 'Page cache flushed successfully.']);
});

// ============================================================
// FEATURE 9 — Write .htaccess performance rules
// Adds browser caching + Gzip rules to .htaccess automatically
// ============================================================
function sgclabs_perf_get_htaccess_rules(): string {
    return '
# BEGIN SGC LABS® Performance
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType image/x-icon "access plus 1 year"
    ExpiresByType video/mp4 "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType text/javascript "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType application/pdf "access plus 1 month"
    ExpiresByType application/x-font-woff "access plus 1 year"
    ExpiresByType font/woff2 "access plus 1 year"
    ExpiresByType text/html "access plus 0 seconds"
</IfModule>

<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/css application/json
    AddOutputFilterByType DEFLATE application/javascript text/javascript
    AddOutputFilterByType DEFLATE text/xml application/xml
    AddOutputFilterByType DEFLATE image/svg+xml application/x-font-ttf
    AddOutputFilterByType DEFLATE font/woff font/woff2 application/font-woff
    BrowserMatch ^Mozilla/4 gzip-only-text/html
    BrowserMatch ^Mozilla/4\.0[678] no-gzip
    BrowserMatch \bMSIE !no-gzip !gzip-only-text/html
</IfModule>

<IfModule mod_headers.c>
    <FilesMatch "\.(ico|jpg|jpeg|png|gif|webp|svg|css|js|woff|woff2)$">
        Header set Cache-Control "max-age=31536000, public"
    </FilesMatch>
    <FilesMatch "\.(html|htm|php)$">
        Header set Cache-Control "max-age=0, private, must-revalidate"
    </FilesMatch>
    Header unset ETag
</IfModule>

FileETag None
# END SGC LABS® Performance
';
}

// AJAX: Write htaccess rules
add_action('wp_ajax_sgclabs_perf_write_htaccess', function () {
    check_ajax_referer('sgclabs_perf_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $htaccess = ABSPATH . '.htaccess';
    if (!is_writable($htaccess) && !is_writable(ABSPATH)) {
        wp_send_json_error('.htaccess is not writable. Please set permissions to 644 via cPanel File Manager.');
    }

    $current = file_exists($htaccess) ? file_get_contents($htaccess) : '';

    // Remove old SGC LABS® Performance block if exists
    $current = preg_replace('/\n?# BEGIN SGC LABS® Performance.*?# END SGC LABS® Performance\n?/s', '', $current);

    $new_content = sgclabs_perf_get_htaccess_rules() . "\n" . $current;
    file_put_contents($htaccess, $new_content);

    wp_send_json_success(['message' => 'Browser caching + Gzip rules written to .htaccess successfully!']);
});

// AJAX: Remove htaccess rules
add_action('wp_ajax_sgclabs_perf_remove_htaccess', function () {
    check_ajax_referer('sgclabs_perf_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $htaccess = ABSPATH . '.htaccess';
    if (!file_exists($htaccess)) wp_send_json_error('.htaccess not found.');

    $current     = file_get_contents($htaccess);
    $new_content = preg_replace('/\n?# BEGIN SGC LABS® Performance.*?# END SGC LABS® Performance\n?/s', '', $current);
    file_put_contents($htaccess, $new_content);

    wp_send_json_success(['message' => 'Performance rules removed from .htaccess.']);
});

// AJAX: Save settings
add_action('wp_ajax_sgclabs_perf_save', function () {
    check_ajax_referer('sgclabs_perf_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $toggles = [
        'sgclabs_perf_remove_bloat',
        'sgclabs_perf_js_defer',
        'sgclabs_perf_resource_hints',
        'sgclabs_perf_cart_fragments',
        'sgclabs_perf_heartbeat',
        'sgclabs_perf_php_gzip',
        'sgclabs_perf_cache_headers',
        'sgclabs_perf_page_cache',
    ];
    foreach ($toggles as $key) {
        update_option($key, isset($_POST[$key]) && $_POST[$key] === '1' ? '1' : '0');
    }
    $ttl = max(60, min(86400, (int) ($_POST['sgclabs_perf_cache_ttl'] ?? 3600)));
    update_option('sgclabs_perf_cache_ttl', $ttl);

    // Flush cache on save
    sgclabs_perf_flush_page_cache();

    wp_send_json_success(['message' => 'Settings saved and cache flushed.']);
});

// ============================================================
// ADMIN PAGE
// ============================================================
function sgclabs_performance_page() {
    if (!current_user_can('manage_options')) wp_die('Unauthorized', 403);

    $nonce    = wp_create_nonce('sgclabs_perf_nonce');
    $htaccess = ABSPATH . '.htaccess';
    $ht_exists   = file_exists($htaccess);
    $ht_writable = $ht_exists ? is_writable($htaccess) : is_writable(ABSPATH);
    $ht_has_rules = $ht_exists && strpos(file_get_contents($htaccess), '# BEGIN SGC LABS® Performance') !== false;

    // Check what's available on server
    $has_zlib    = extension_loaded('zlib');
    $has_opcache = function_exists('opcache_get_status');
    $php_version = PHP_VERSION;
    $server_soft = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
    $is_apache   = stripos($server_soft, 'apache') !== false || stripos($server_soft, 'litespeed') !== false;

    $features = [
        'sgclabs_perf_remove_bloat'    => ['Remove WordPress Bloat',         'Removes emoji scripts, oEmbed JS, wlwmanifest, generator tag, REST API link from &lt;head&gt;. Saves 5-15KB per page.',          '1', '🧹', true],
        'sgclabs_perf_js_defer'        => ['Defer Non-Critical JS',           'Adds <code>defer</code> attribute to non-essential scripts so they load after HTML — improves First Contentful Paint.',          '1', '⚡', true],
        'sgclabs_perf_resource_hints'  => ['DNS Prefetch / Preconnect',       'Adds preconnect hints for Google Fonts, Analytics, GTM. Browser opens connections early — reduces latency.',                      '1', '🔗', true],
        'sgclabs_perf_cart_fragments'  => ['Disable WooCommerce Cart AJAX',   'Stops WooCommerce from firing an AJAX request on every page load just to check cart. Safe on non-cart pages.',                   '1', '🛒', true],
        'sgclabs_perf_heartbeat'       => ['Throttle WordPress Heartbeat',    'Reduces Heartbeat API from 15s → 60s interval and disables it on front-end. Reduces server AJAX load significantly.',            '1', '💓', true],
        'sgclabs_perf_cache_headers'   => ['Browser Cache Headers (PHP)',     'Sends Cache-Control headers via PHP for logged-out users. Works even without .htaccess mod_expires.',                            '1', '📦', true],
        'sgclabs_perf_php_gzip'        => ['PHP-level Gzip Compression',      'Enables ob_gzhandler() to compress HTML output via PHP. Use only if .htaccess mod_deflate is not available on your host.',       '0', '🗜️', $has_zlib],
        'sgclabs_perf_page_cache'      => ['DB Page Cache (Transients)',       'Caches full HTML of public pages in WordPress database. No file system or Redis needed — works on any shared hosting.',           '0', '💾', true],
    ];
    ?>
<div class="wrap" style="max-width:900px;font-family:'Segoe UI',system-ui,sans-serif;">
<?php sgclabs_module_control_bar(["module_id"=>"performance","module_name"=>"Performance Optimizer","module_icon"=>"⚡","option_key"=>"sgclabs_performance_active","default"=>"1","description"=>"Page cache, lazy load, minification, resource hints","extra_toggles"=>[["key"=>"sgclabs_perf_cache_active","label"=>"Page Cache","default"=>"1"],["key"=>"sgclabs_perf_lazy_active","label"=>"Lazy Load","default"=>"1"],["key"=>"sgclabs_perf_minify_active","label"=>"Minify","default"=>"0"]]]); ?>

<div style="background:linear-gradient(135deg,#1a1a2e,#16213e);padding:24px 28px;border-radius:12px;margin:16px 0 24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div>
        <h1 style="color:#27ae60;margin:0;font-size:20px;font-weight:700;">⚡ Performance</h1>
        <p style="color:rgba(255,255,255,.65);margin:4px 0 0;font-size:13px;">No CDN or cache plugin needed — pure WordPress &amp; PHP level optimization</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <span style="background:rgba(255,255,255,.1);color:rgba(255,255,255,.8);font-size:12px;padding:4px 12px;border-radius:20px;">PHP <?php echo esc_html($php_version); ?></span>
        <span style="background:rgba(255,255,255,.1);color:rgba(255,255,255,.8);font-size:12px;padding:4px 12px;border-radius:20px;"><?php echo $is_apache ? '✅ Apache' : '⚠️ Non-Apache'; ?></span>
        <span style="background:rgba(255,255,255,.1);color:rgba(255,255,255,.8);font-size:12px;padding:4px 12px;border-radius:20px;"><?php echo $has_zlib ? '✅ zlib (Gzip)' : '❌ No zlib'; ?></span>
        <span style="background:rgba(255,255,255,.1);color:rgba(255,255,255,.8);font-size:12px;padding:4px 12px;border-radius:20px;"><?php echo $has_opcache ? '✅ OPcache' : '⚠️ No OPcache'; ?></span>
    </div>
</div>

<div id="sgcperf-msg" style="display:none;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:14px;"></div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;">

<div>
    <!-- Feature toggles -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;margin-bottom:16px;">
        <div style="padding:14px 18px;border-bottom:1px solid #f1f5f9;background:#f8fafc;">
            <h3 style="margin:0;font-size:14px;font-weight:700;color:#1e293b;">Performance Features</h3>
            <p style="margin:4px 0 0;font-size:12px;color:#64748b;">Toggle each feature on/off — save at bottom</p>
        </div>
        <?php foreach ($features as $key => [$label, $desc, $default, $icon, $available]): ?>
        <?php $val = get_option($key, $default); ?>
        <div style="display:flex;align-items:flex-start;gap:12px;padding:14px 18px;border-bottom:1px solid #f8fafc;<?php echo !$available ? 'opacity:.5;' : ''; ?>">
            <span style="font-size:20px;margin-top:2px;"><?php echo $icon; ?></span>
            <div style="flex:1;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <span style="font-size:14px;font-weight:600;color:#1e293b;"><?php echo esc_html($label); ?></span>
                    <?php if (!$available): ?><span style="font-size:11px;background:#fef3c7;color:#92400e;padding:2px 7px;border-radius:4px;">Not available on this server</span><?php endif; ?>
                    <?php if ($default === '0'): ?><span style="font-size:11px;background:#eff6ff;color:#1e40af;padding:2px 7px;border-radius:4px;">Off by default</span><?php endif; ?>
                </div>
                <p style="margin:4px 0 0;font-size:12px;color:#64748b;line-height:1.6;"><?php echo $desc; ?></p>
            </div>
            <label style="position:relative;flex-shrink:0;margin-top:2px;">
                <input type="checkbox" class="sgcperf-tog" name="<?php echo esc_attr($key); ?>" value="1"
                    <?php checked($val, '1'); ?> <?php disabled(!$available); ?>
                    style="opacity:0;width:0;height:0;position:absolute;">
                <span class="sgcperf-lbl" style="display:inline-block;width:40px;height:22px;background:<?php echo ($val === '1' && $available) ? '#27ae60' : '#cbd5e1'; ?>;border-radius:11px;cursor:<?php echo $available ? 'pointer' : 'not-allowed'; ?>;position:relative;transition:background .2s;">
                    <span style="position:absolute;top:3px;left:<?php echo ($val === '1') ? '21px' : '3px'; ?>;width:16px;height:16px;background:#fff;border-radius:50%;transition:left .2s;box-shadow:0 1px 3px rgba(0,0,0,.2);"></span>
                </span>
            </label>
        </div>
        <?php endforeach; ?>

        <!-- Cache TTL -->
        <div style="padding:14px 18px;border-bottom:1px solid #f8fafc;">
            <div style="display:flex;align-items:center;gap:12px;">
                <span style="font-size:20px;">⏱️</span>
                <div style="flex:1;">
                    <span style="font-size:14px;font-weight:600;color:#1e293b;">Page Cache Duration</span>
                    <p style="margin:4px 0 0;font-size:12px;color:#64748b;">How long pages are cached in database (only applies when DB Page Cache is enabled)</p>
                </div>
                <select name="sgclabs_perf_cache_ttl" id="sgcperf-ttl" style="padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;">
                    <?php
                    $ttl_options = [300=>'5 minutes',900=>'15 minutes',1800=>'30 minutes',3600=>'1 hour',7200=>'2 hours',21600=>'6 hours',43200=>'12 hours',86400=>'24 hours'];
                    $cur_ttl = (int) get_option('sgclabs_perf_cache_ttl', 3600);
                    foreach ($ttl_options as $v => $l):
                    ?><option value="<?php echo $v; ?>" <?php selected($cur_ttl, $v); ?>><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="padding:16px 18px;">
            <button onclick="sgcPerfSave()" style="padding:10px 24px;background:#27ae60;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;margin-right:8px;">💾 Save Settings</button>
            <button onclick="sgcPerfFlush()" style="padding:10px 18px;background:#f8fafc;color:#374151;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;cursor:pointer;">🗑️ Flush Cache</button>
        </div>
    </div>
</div>

<!-- Right panel -->
<div>

    <!-- .htaccess panel -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;margin-bottom:16px;">
        <div style="padding:14px 18px;border-bottom:1px solid #f1f5f9;background:#f8fafc;">
            <h3 style="margin:0;font-size:14px;font-weight:700;color:#1e293b;">🗂️ .htaccess Rules</h3>
            <p style="margin:4px 0 0;font-size:12px;color:#64748b;">Browser caching + Gzip via Apache</p>
        </div>
        <div style="padding:16px 18px;">
            <?php if (!$is_apache): ?>
            <div style="background:#fef3c7;border:1px solid #fde68a;border-radius:6px;padding:10px 12px;font-size:12px;color:#92400e;margin-bottom:12px;">
                ⚠️ Your server appears to be Nginx or non-Apache. .htaccess rules may not work — use the PHP-level options on the left instead.
            </div>
            <?php endif; ?>

            <?php if (!$ht_writable): ?>
            <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:10px 12px;font-size:12px;color:#991b1b;margin-bottom:12px;">
                ❌ .htaccess is not writable. Set permissions to 644 via cPanel → File Manager.
            </div>
            <?php endif; ?>

            <div style="background:<?php echo $ht_has_rules ? '#f0fdf4' : '#f8fafc'; ?>;border:1px solid <?php echo $ht_has_rules ? '#86efac' : '#e2e8f0'; ?>;border-radius:6px;padding:10px 12px;font-size:12px;margin-bottom:12px;">
                <?php if ($ht_has_rules): ?>
                <span style="color:#166534;">✅ SGC LABS® performance rules are active in .htaccess</span>
                <?php else: ?>
                <span style="color:#64748b;">⬜ No SGC LABS® rules in .htaccess yet</span>
                <?php endif; ?>
            </div>

            <?php if ($ht_has_rules): ?>
            <pre style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:10px;font-size:10px;overflow-x:auto;max-height:120px;color:#374151;"><?php echo esc_html(sgclabs_perf_get_htaccess_rules()); ?></pre>
            <?php endif; ?>

            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;">
                <?php if ($ht_writable): ?>
                <button onclick="sgcPerfHtaccess('write')" style="padding:8px 14px;background:#27ae60;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;">
                    <?php echo $ht_has_rules ? '🔄 Update Rules' : '✍️ Write Rules'; ?>
                </button>
                <?php if ($ht_has_rules): ?>
                <button onclick="sgcPerfHtaccess('remove')" style="padding:8px 14px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;border-radius:6px;font-size:12px;cursor:pointer;">
                    🗑️ Remove Rules
                </button>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Server info -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
        <div style="padding:14px 18px;border-bottom:1px solid #f1f5f9;background:#f8fafc;">
            <h3 style="margin:0;font-size:14px;font-weight:700;color:#1e293b;">🖥️ Server Info</h3>
        </div>
        <div style="padding:16px 18px;">
            <?php
            $info = [
                'PHP Version'   => PHP_VERSION,
                'Server'        => $server_soft,
                'zlib / Gzip'   => $has_zlib ? '✅ Available' : '❌ Not available',
                'OPcache'       => $has_opcache ? '✅ Active' : '⚠️ Not detected',
                '.htaccess'     => $ht_exists ? ($ht_writable ? '✅ Writable' : '⚠️ Read-only') : '❌ Not found',
                'Memory Limit'  => ini_get('memory_limit'),
                'Max Exec Time' => ini_get('max_execution_time') . 's',
            ];
            foreach ($info as $k => $v):
            ?>
            <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid #f8fafc;font-size:13px;">
                <span style="color:#64748b;"><?php echo esc_html($k); ?></span>
                <span style="color:#1e293b;font-weight:500;"><?php echo esc_html($v); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>
</div>
</div>

<script>
var PERF_NONCE = '<?php echo esc_js($nonce); ?>';
var PERF_AJAX  = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';

function sgcPerfMsg(msg, ok) {
    var el = document.getElementById('sgcperf-msg');
    el.style.display = 'block';
    el.style.background = ok ? '#f0fdf4' : '#fef2f2';
    el.style.border = '1px solid ' + (ok ? '#86efac' : '#fecaca');
    el.style.color = ok ? '#166534' : '#991b1b';
    el.innerHTML = msg;
    window.scrollTo({top:0,behavior:'smooth'});
}

function sgcPerfSave() {
    var data = new FormData();
    data.append('action', 'sgclabs_perf_save');
    data.append('nonce', PERF_NONCE);
    document.querySelectorAll('.sgcperf-tog').forEach(function(cb) {
        if (cb.checked) data.append(cb.name, '1');
    });
    var ttl = document.getElementById('sgcperf-ttl');
    if (ttl) data.append('sgclabs_perf_cache_ttl', ttl.value);

    fetch(PERF_AJAX, {method:'POST',body:data})
        .then(function(r){return r.json();})
        .then(function(res){
            sgcPerfMsg(res.success ? '✅ ' + res.data.message : '❌ ' + res.data, res.success);
        }).catch(function(){ sgcPerfMsg('❌ Network error. Please try again.', false); });
}

function sgcPerfFlush() {
    var data = new FormData();
    data.append('action', 'sgclabs_flush_cache');
    data.append('nonce', PERF_NONCE);
    fetch(PERF_AJAX, {method:'POST',body:data})
        .then(function(r){return r.json();})
        .then(function(res){
            sgcPerfMsg(res.success ? '✅ ' + res.data.message : '❌ ' + res.data, res.success);
        }).catch(function(){ sgcPerfMsg('❌ Network error.', false); });
}

function sgcPerfHtaccess(action) {
    var msg = action === 'remove'
        ? 'This will remove SGC LABS® performance rules from .htaccess. Continue?'
        : 'This will write browser caching and Gzip rules to your .htaccess file. Continue?';
    if (!confirm(msg)) return;
    var data = new FormData();
    data.append('action', 'sgclabs_perf_' + action + '_htaccess');
    data.append('nonce', PERF_NONCE);
    fetch(PERF_AJAX, {method:'POST',body:data})
        .then(function(r){return r.json();})
        .then(function(res){
            sgcPerfMsg(res.success ? '✅ ' + res.data.message : '❌ ' + res.data, res.success);
            if (res.success) setTimeout(function(){ location.reload(); }, 1500);
        }).catch(function(){ sgcPerfMsg('❌ Network error.', false); });
}

// Toggle switch UI behavior
document.querySelectorAll('.sgcperf-tog').forEach(function(cb) {
    cb.addEventListener('change', function() {
        var lbl  = this.nextElementSibling;
        var knob = lbl.querySelector('span');
        if (this.checked) {
            lbl.style.background  = '#27ae60';
            knob.style.left       = '21px';
        } else {
            lbl.style.background  = '#cbd5e1';
            knob.style.left       = '3px';
        }
    });
});
</script>
    <?php
}
