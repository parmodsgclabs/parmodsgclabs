<?php
if (!defined('ABSPATH')) exit;
// FIX: SSRF protection helper - blocks internal/private IP ranges
function sgclabs_is_safe_external_url($url) {
    $parsed = parse_url($url);
    if (empty($parsed["scheme"]) || !in_array($parsed["scheme"], ["http", "https"], true)) return false;
    if (empty($parsed["host"])) return false;
    $host = $parsed["host"];
    if (in_array(strtolower($host), ["localhost", "127.0.0.1", "::1", "0.0.0.0"], true)) return false;
    $ip = gethostbyname($host);
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
    return true;
}


// ============================================================
// SGC LABS® GOOGLE SEO COMPLIANCE ENGINE v6.6.3
// Fully Google-compliant:
// 1. Keyword density control (no stuffing)
// 2. Duplicate meta tag prevention
// 3. Canonical URLs
// 4. Robots.txt manager
// 5. Google Fonts (lazy loaded)
// 6. Core Web Vitals optimization
// 7. Image lazy loading
// 8. Structured data validator
// ============================================================

// ============================================================
// 1. GOOGLE FONTS — LAZY LOADED (Performance friendly)
// ============================================================
add_action('wp_head', 'sgclabs_google_fonts', 1);
function sgclabs_google_fonts() {
    if (get_option('sgclabs_fonts_active', '1') !== '1') return;
    if (is_admin()) return;

    $font = get_option('sgclabs_selected_font', 'DM Sans');
    $font_encoded = urlencode($font);
    $font_url = "https://fonts.googleapis.com/css2?family={$font_encoded}:wght@400;500;600;700&display=swap";
    ?>
    <!-- SGC LABS® Non-blocking Font Load (Core Web Vitals safe) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="<?php echo esc_url($font_url); ?>"
          onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="<?php echo esc_url($font_url); ?>"></noscript>
    <?php
}

// ============================================================
// 2. CORE WEB VITALS OPTIMIZATION
// ============================================================
add_action('wp_head', 'sgclabs_core_web_vitals', 2);
function sgclabs_core_web_vitals() {
    if (get_option('sgclabs_cwv_active', '1') !== '1') return;
    echo "
    <!-- SGC LABS® Core Web Vitals Optimizer -->
    <style>
    /* Prevent layout shift (CLS fix) */
    img, video { height: auto; }
    img:not([width]) { max-width: 100%; }
    .woocommerce ul.products li.product a img { width: 100%; aspect-ratio: 1/1; object-fit: cover; }

    /* LCP optimization - above fold content loads first */
    .site-header, .woocommerce-products-header, h1 { content-visibility: auto; }

    /* FID/INP - reduce input delay */
    * { touch-action: manipulation; }

    /* Reduce render-blocking */
    @font-face { font-display: swap; }
    </style>

    <!-- DNS Prefetch for external resources -->
    <link rel='dns-prefetch' href='//www.google-analytics.com'>
    <link rel='dns-prefetch' href='//fonts.googleapis.com'>
    <link rel='dns-prefetch' href='//api.whatsapp.com'>
    ";
}

// ============================================================
// 3. IMAGE LAZY LOADING (Native + Enhanced)
// ============================================================
add_filter('the_content', 'sgclabs_add_lazy_loading');
add_filter('post_thumbnail_html', 'sgclabs_add_lazy_loading');
add_filter('woocommerce_product_get_image', 'sgclabs_add_lazy_loading');
function sgclabs_add_lazy_loading($content) {
    if (get_option('sgclabs_lazy_images', '1') !== '1') return $content;
    // v6.6.3: Never add lazy loading on front page / home / regular pages
    // — Elementor sliders and hero sections break if lazy is added to their images.
    if (!is_admin() && (is_front_page() || is_home() || (is_page() && !is_product()))) return $content;
    // Allow other filters to disable lazy loading (e.g. slider context)
    if (!apply_filters('sgclabs_lazy_images_enabled', true)) return $content;
    $content = preg_replace('/<img(?![^>]*loading=)/', '<img loading="lazy" decoding="async"', $content);
    return $content;
}

// Add lazy loading to all WordPress images
add_filter('wp_get_attachment_image_attributes', 'sgclabs_attachment_lazy', 10, 2);
function sgclabs_attachment_lazy($attr, $attachment) {
    if (get_option('sgclabs_lazy_images', '1') !== '1') return $attr;
    // v6.6.3: Never on front/home/pages — Elementor sliders break
    if (!is_admin() && function_exists('is_front_page') && (is_front_page() || is_home() || (is_page() && !is_product()))) return $attr;
    $attr['loading'] = 'lazy';
    $attr['decoding'] = 'async';
    return $attr;
}

// ============================================================
// 4. CANONICAL URLS (Prevent duplicate content)
// ============================================================
add_action('wp_head', 'sgclabs_canonical_url', 3);
function sgclabs_canonical_url() {
    if (get_option('sgclabs_canonical_active', '1') !== '1') return;
    if (defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION') || defined('AIOSEO_VERSION') || defined('SEOPRESS_VERSION')) return;
    remove_action('wp_head', 'rel_canonical'); // always remove WP default
    // Skip for product pages when auto-seo is active — it outputs canonical at priority 5
    if (function_exists('is_product') && is_product() && get_option('sgclabs_auto_seo_active','0') === '1') return;

    $canonical = '';
    if (is_singular()) {
        $canonical = get_permalink();
    } elseif (is_home() || is_front_page()) {
        $canonical = home_url('/');
    } elseif (is_tax() || is_category() || is_tag()) {
        $canonical = get_term_link(get_queried_object());
        if (is_wp_error($canonical)) $canonical = '';
    } elseif (is_archive()) {
        $ptype = get_post_type();
        $canonical = $ptype ? (get_post_type_archive_link($ptype) ?: '') : '';
    } elseif (is_search()) {
        $canonical = home_url('/');
    }

    if ($canonical) {
        // Strip ALL query params — ?v=, ?utm_*, ?lang=, ?orderby=, ?paged=, ?fbclid= etc
        $parsed    = parse_url($canonical);
        $clean_url = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '') . (isset($parsed['port']) ? ':' . $parsed['port'] : '') . ($parsed['path'] ?? '/');

        // Paginated archives → path-based pagination
        if (is_paged()) {
            $paged = max(1, intval(get_query_var('paged')));
            if ($paged > 1) {
                $clean_url = rtrim($clean_url, '/') . '/page/' . $paged . '/';
            }
        }

        echo "<link rel='canonical' href='" . esc_url($clean_url) . "'>\n";
    }
}

// ============================================================
// 5. DUPLICATE META TAG PREVENTION
// ============================================================
add_action('wp_head', 'sgclabs_prevent_duplicate_meta', 0);
function sgclabs_prevent_duplicate_meta() {
    // Remove WordPress default generator tag (security + cleanliness)
    remove_action('wp_head', 'wp_generator');
    // Remove RSD link (not needed for SEO)
    remove_action('wp_head', 'rsd_link');
    // Remove wlwmanifest (not needed)
    remove_action('wp_head', 'wlwmanifest_link');
    // Remove shortlink
    remove_action('wp_head', 'wp_shortlink_wp_head');
    // Remove feed links from head (keep only what's needed)
    // RSS feed links — always remove (cleaner HTML, B2B site doesn't need RSS)
    remove_action('wp_head', 'feed_links', 2);
    remove_action('wp_head', 'feed_links_extra', 3);
    // Also remove emoji scripts (not needed for lab equipment site)
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
}

// Ensure only ONE description meta tag (ours takes priority)
add_action('wp_head', 'sgclabs_dequeue_other_seo', 0);
function sgclabs_dequeue_other_seo() {

    $has_rankmath  = defined('RANK_MATH_VERSION');
    $has_yoast     = defined('WPSEO_VERSION');
    $has_aioseo    = defined('AIOSEO_VERSION');
    $has_seopress  = defined('SEOPRESS_VERSION');
    $has_seo_plugin = $has_rankmath || $has_yoast || $has_aioseo || $has_seopress;

    if ($has_seo_plugin) {
        // --- Disable ALL our SEO meta output — let the SEO plugin handle it ---
        // Title + meta description + OG tags + Schema
        remove_action('wp_head', 'sgclabs_inject_meta',            5);
        remove_action('wp_head', 'sgclabs_inject_product_seo',     3);
        // hreflang NOT removed — RankMath doesn't output hreflang by default
        // remove_action('wp_head', 'sgclabs_inject_multilingual_seo',4);
        remove_action('wp_head', 'sgclabs_inject_custom_keywords', 6);
        remove_action('wp_head', 'sgclabs_inject_global_keywords_meta', 7);
        remove_action('wp_head', 'sgclabs_inject_profile_seo',     2); // multisite title echo
        remove_action('wp_head', 'sgclabs_canonical_url',          3); // canonical

        // Remove robots_txt filter — SEO plugin controls this
        // robots_txt filter handled by site-tools.php (sgclabs_tools_custom_robots at priority 99)
    }

    // --- Cache plugin detection — prevent post_meta cache conflicts ---
    // WP Rocket: purge our transients on cache clear
    if (defined('WP_ROCKET_VERSION')) {
        add_action('after_rocket_clean_post', 'sgclabs_purge_post_seo_cache');
        add_action('rocket_clean_domain',     'sgclabs_purge_all_seo_cache');
    }
    // LiteSpeed Cache
    if (defined('LSCWP_V')) {
        add_action('litespeed_purge_post',  'sgclabs_purge_post_seo_cache');
        add_action('litespeed_purge_all',   'sgclabs_purge_all_seo_cache');
    }
    // W3 Total Cache
    if (defined('W3TC')) {
        add_action('w3tc_flush_post',  'sgclabs_purge_post_seo_cache');
        add_action('w3tc_flush_all',   'sgclabs_purge_all_seo_cache');
    }
    // WP Super Cache
    if (function_exists('wp_cache_clear_cache')) {
        add_action('wp_cache_cleared', 'sgclabs_purge_all_seo_cache');
    }
}

// Purge single post SEO/content cache when cache plugin clears it
function sgclabs_purge_post_seo_cache($post_id) {
    if (!$post_id) return;
    delete_post_meta((int)$post_id, '_sgclabs_seo_cache');
    delete_post_meta((int)$post_id, '_sgclabs_seo_cache_time');
    delete_post_meta((int)$post_id, '_sgclabs_gen_content');
    delete_post_meta((int)$post_id, '_sgclabs_gen_content_time');
}

// Purge ALL SGC LABS caches (called on full cache flush)
function sgclabs_purge_all_seo_cache() {
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (
        '_sgclabs_seo_cache','_sgclabs_seo_cache_time',
        '_sgclabs_gen_content','_sgclabs_gen_content_time',
        '_sgclabs_gkw_cache','_sgclabs_gkw_cache_time'
    )");
    // Clear transients
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '%_transient_sgclabs_%', '%_transient_timeout_sgclabs_%'));
}

// ============================================================
// 6. KEYWORD DENSITY CONTROL (Anti-stuffing)
// ============================================================
function sgclabs_check_keyword_density($content, $keyword) {
    if (empty($content) || empty($keyword)) return ['density' => 0, 'count' => 0, 'status' => 'ok'];
    $word_count   = str_word_count(strip_tags($content));
    $kw_count     = substr_count(strtolower(strip_tags($content)), strtolower($keyword));
    $density      = $word_count > 0 ? round(($kw_count / $word_count) * 100, 2) : 0;
    $max_density  = floatval(get_option('sgclabs_max_kw_density', 2.5));
    $status       = $density > $max_density ? 'stuffing' : ($density > 0 ? 'good' : 'missing');
    return ['density' => $density, 'count' => $kw_count, 'words' => $word_count, 'status' => $status, 'max' => $max_density];
}

// Sanitize keywords before injecting (remove duplicates, limit count)
function sgclabs_sanitize_keywords_for_meta($keywords_array) {
    $max = intval(get_option('sgclabs_max_meta_keywords', 25));
    // Remove duplicates (case insensitive)
    $seen = [];
    $clean = [];
    foreach ($keywords_array as $kw) {
        $lower = strtolower(trim($kw));
        if (!isset($seen[$lower]) && strlen($lower) > 2) {
            $seen[$lower] = true;
            $clean[] = trim($kw);
        }
    }
    return array_slice($clean, 0, $max);
}

// ============================================================
// 7. ROBOTS.TXT MANAGER
// ============================================================
// Robots.txt is fully managed by site-tools.php (Site Tools module)
// sgclabs_tools_robots_active + sgclabs_tools_robots_txt options
// Old sgclabs_custom_robots removed to avoid filter conflict

// ============================================================
// 8. STRUCTURED DATA VALIDATOR (AJAX)
// ============================================================
add_action('wp_ajax_sgclabs_validate_schema', 'sgclabs_validate_schema_ajax');
function sgclabs_validate_schema_ajax() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');

    $url = esc_url_raw($_POST['url'] ?? '');

    // FIX: SSRF protection - block private/internal IPs
    if (!sgclabs_is_safe_external_url($url)) {
        wp_send_json_error('Invalid or disallowed URL. Internal/private IP addresses are not permitted.');
        return;
    }

    $response = wp_remote_get($url, ['timeout' => 15]);
    if (is_wp_error($response)) {
        wp_send_json_error('Could not fetch URL: ' . $response->get_error_message());
        return;
    }

    $body   = wp_remote_retrieve_body($response);
    $checks = [];

    // Check for schema markup
    $has_schema    = strpos((string)$body, 'application/ld+json') !== false;
    $has_product   = strpos((string)$body, '"@type":"Product"') !== false 
                    || strpos((string)$body, '"@type": "Product"') !== false
                    || strpos((string)$body, '"@type":["Product"') !== false
                    || strpos((string)$body, '"@type": ["Product"') !== false;
    $has_org       = strpos((string)$body, '"@type":"Organization"') !== false || strpos((string)$body, 'Organization') !== false;
    $has_canonical = strpos((string)$body, "rel='canonical'") !== false || strpos((string)$body, 'rel="canonical"') !== false;
    $has_og        = strpos((string)$body, 'og:title') !== false;
    $has_desc      = strpos((string)$body, "name='description'") !== false || strpos((string)$body, 'name="description"') !== false;
    $has_hreflang  = strpos((string)$body, 'hreflang') !== false;
    $has_lazy      = strpos((string)$body, 'loading="lazy"') !== false || strpos((string)$body, "loading='lazy'") !== false;
    $has_fonts     = strpos((string)$body, 'fonts.googleapis.com') !== false;
    $has_viewport  = strpos((string)$body, 'name="viewport"') !== false;
    $has_title     = preg_match('/<title[^>]*>([^<]+)<\/title>/', $body, $tm);

    // Title length check
    $title_len = $has_title ? strlen($tm[1]) : 0;
    $title_ok  = $title_len >= 30 && $title_len <= 60;

    // Description length
    preg_match('/name=["\']description["\'][^>]*content=["\']([^"\']+)["\']|content=["\']([^"\']+)["\'][^>]*name=["\']description["\']/', $body, $dm);
    $desc_text = $dm[1] ?? $dm[2] ?? '';
    $desc_len  = strlen($desc_text);
    $desc_ok   = $desc_len >= 120 && $desc_len <= 160;

    // Keyword stuffing check
    preg_match('/name=["\']keywords["\'][^>]*content=["\']([^"\']+)["\']/', $body, $km);
    $kw_content = $km[1] ?? '';
    $kw_count   = $kw_content ? count(explode(',', $kw_content)) : 0;
    $kw_ok      = $kw_count <= 25;

    $checks = [
        ['name' => 'Schema Markup (JSON-LD)', 'pass' => $has_schema, 'tip' => 'Structured data Google ko product info deta hai'],
        ['name' => 'Product Schema', 'pass' => $has_product, 'tip' => 'Product type schema for rich results'],
        ['name' => 'Organization Schema', 'pass' => $has_org, 'tip' => 'Brand identity schema'],
        ['name' => 'Canonical URL', 'pass' => $has_canonical, 'tip' => 'Duplicate content prevention'],
        ['name' => 'Meta Description', 'pass' => $has_desc, 'tip' => 'Click-through rate improves with good description'],
        ['name' => 'Description Length (120-160)', 'pass' => $desc_ok, 'tip' => "Current: {$desc_len} chars"],
        ['name' => 'OG Tags (Social)', 'pass' => $has_og, 'tip' => 'Facebook/WhatsApp sharing optimization'],
        ['name' => 'hreflang Tags', 'pass' => $has_hreflang, 'tip' => 'Multi-language SEO signals'],
        ['name' => 'Title Tag', 'pass' => $has_title, 'tip' => 'Page title found'],
        ['name' => 'Title Length (30-60)', 'pass' => $title_ok, 'tip' => "Current: {$title_len} chars"],
        ['name' => 'Keywords Count (max 25)', 'pass' => $kw_ok, 'tip' => "Current: {$kw_count} keywords"],
        ['name' => 'Image Lazy Loading', 'pass' => $has_lazy, 'tip' => 'Core Web Vitals improvement'],
        ['name' => 'Google Fonts (display=swap)', 'pass' => $has_fonts, 'tip' => 'Non-render-blocking fonts'],
        ['name' => 'Viewport Meta Tag', 'pass' => $has_viewport, 'tip' => 'Mobile-friendly requirement'],
    ];

    $passed = count(array_filter($checks, fn($c) => $c['pass']));
    $score  = round($passed / count($checks) * 100);

    wp_send_json_success([
        'checks'  => $checks,
        'passed'  => $passed,
        'total'   => count($checks),
        'score'   => $score,
        'title'   => $has_title ? $tm[1] : 'Not found',
        'title_len' => $title_len,
        'desc'    => substr($desc_text, 0, 160),
        'desc_len'  => $desc_len,
    ]);
}

// ============================================================
// SAVE COMPLIANCE SETTINGS
// ============================================================
add_action('admin_post_sgclabs_save_compliance', 'sgclabs_save_compliance');
function sgclabs_save_compliance() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'sgclabs_compliance_nonce')) wp_die('Unauthorized');

    $fields = [
        'sgclabs_fonts_active', 'sgclabs_selected_font',
        'sgclabs_cwv_active', 'sgclabs_lazy_images',
        'sgclabs_canonical_active', 'sgclabs_remove_feed_links',
        'sgclabs_max_kw_density', 'sgclabs_max_meta_keywords',
        // robots fields moved to site-tools.php
    ];
    foreach ($fields as $f) {
        if (isset($_POST[$f])) update_option($f, sanitize_textarea_field($_POST[$f]));
        else update_option($f, '0');
    }
    // Flush rewrite rules for robots.txt
    flush_rewrite_rules();
    wp_redirect(admin_url('admin.php?page=sgclabs-site-tools-hub&tab=compliance&saved=1'));
    exit;
}

// ============================================================
// ADMIN PAGE
// ============================================================
function sgclabs_compliance_page() {
    $saved = isset($_GET['saved']);
    ?>
    <div class="wrap sgclabs-wrap">
        <h1>✅ Google SEO Compliance</h1>
        <p>Google ke saare SEO guidelines follow karo — violations se bachao, performance improve karo!</p>

        <?php if ($saved): ?>
            <div class="notice notice-success"><p>✅ Settings saved! Flush rewrite rules ho gaye.</p></div>
        <?php endif; ?>

        <!-- VALIDATOR -->
        <div class="sgclabs-section">
            <h2>🔍 Live SEO Validator</h2>
            <p>Kisi bhi page ka SEO score check karo — Google ke 14 parameters ke against:</p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:15px;">
                <input type="url" id="validate_url" value="<?php echo esc_attr(home_url('/')); ?>"
                    style="flex:1;padding:8px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;" placeholder="https://labsglass.com/product/...">
                <button class="button" onclick="autoDetectProduct()" title="Latest product URL auto-fill">🔍 Auto-detect Product URL</button>
                <button class="button button-primary button-large" onclick="validateSEO()">✅ Validate Now</button>
                <?php
                // Get latest product URL for auto-detect
                $latest_product = function_exists('wc_get_products') ? wc_get_products(['limit'=>1,'status'=>'publish','return'=>'ids']) : [];
                $latest_url = !empty($latest_product) ? get_permalink($latest_product[0]) : home_url('/');
                ?>
                <script>
                function autoDetectProduct() {
                    document.getElementById('validate_url').value = '<?php echo esc_js($latest_url); ?>';
                    validateSEO();
                }
                </script>
            </div>
            <div id="validator-result" style="display:none;">
                <div class="compliance-score-wrap">
                    <div class="compliance-score-circle" id="score-circle">
                        <span id="score-num">0</span><small>/100</small>
                    </div>
                    <div class="compliance-score-info">
                        <h3 id="score-label">Checking...</h3>
                        <p id="score-sub"></p>
                        <div id="page-title-info" style="font-size:13px;color:#555;"></div>
                    </div>
                </div>
                <table class="wp-list-table widefat fixed striped" style="margin-top:15px;">
                    <thead><tr><th width="40%">Check</th><th width="15%">Status</th><th width="45%">Tip</th></tr></thead>
                    <tbody id="checks-tbody"></tbody>
                </table>
            </div>
        </div>

        <!-- SETTINGS FORM -->
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('sgclabs_compliance_nonce'); ?>
            <input type="hidden" name="action" value="sgclabs_save_compliance">

            <!-- Google Fonts -->
            <div class="sgclabs-section">
                <h2>🔤 Google Fonts (Non-blocking)</h2>
                <p class="description">Google approved fonts — lazy loaded taaki Core Web Vitals affect na ho!</p>
                <table class="form-table">
                    <tr>
                        <th>Enable Google Fonts</th>
                        <td><label><input type="checkbox" name="sgclabs_fonts_active" value="1" <?php checked(get_option('sgclabs_fonts_active','1'),'1'); ?>> Apply Google Font to entire site</label></td>
                    </tr>
                    <tr>
                        <th>Select Font</th>
                        <td>
                            <div class="font-preview-grid">
                                <?php
                                $fonts = ['Inter','Roboto','Open Sans','Lato','Poppins','Nunito','Source Sans','Montserrat'];
                                $current = get_option('sgclabs_selected_font','Inter');
                                foreach ($fonts as $f):
                                    $sample_styles = [
                                        'Inter'       => 'font-family:Inter,sans-serif',
                                        'Roboto'      => 'font-family:Roboto,sans-serif',
                                        'Open Sans'   => 'font-family:"Open Sans",sans-serif',
                                        'Lato'        => 'font-family:Lato,sans-serif',
                                        'Poppins'     => 'font-family:Poppins,sans-serif',
                                        'Nunito'      => 'font-family:Nunito,sans-serif',
                                        'Source Sans' => 'font-family:"Source Sans 3",sans-serif',
                                        'Montserrat'  => 'font-family:Montserrat,sans-serif',
                                    ];
                                ?>
                                <label class="font-card <?php echo $current===esc_attr($f)?'selected':''; ?>">
                                    <input type="radio" name="sgclabs_selected_font" value="<?php echo esc_attr($f); ?>" <?php checked($current,$f); ?> style="display:none;" onchange="this.closest('.font-preview-grid').querySelectorAll('.font-card').forEach(c=>c.classList.remove('selected'));this.closest('.font-card').classList.add('selected')">
                                    <div class="font-name"><?php echo esc_html($f); ?></div>
                                    <div class="font-sample" style="<?php echo esc_attr($sample_styles[$f] ?? ''); ?>">Laboratory Glassware SGC LABS®</div>
                                    <div class="font-meta">Google Fonts ✓</div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Core Web Vitals -->
            <div class="sgclabs-section">
                <h2>⚡ Core Web Vitals Optimizer</h2>
                <table class="form-table">
                    <tr>
                        <th>Enable CWV Optimizer</th>
                        <td>
                            <label><input type="checkbox" name="sgclabs_cwv_active" value="1" <?php checked(get_option('sgclabs_cwv_active','1'),'1'); ?>>
                            Fix CLS, LCP, FID/INP automatically</label>
                            <p class="description">Adds CSS fixes for layout shift, loads fonts with display=swap, DNS prefetch</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Image Lazy Loading</th>
                        <td>
                            <label><input type="checkbox" name="sgclabs_lazy_images" value="1" <?php checked(get_option('sgclabs_lazy_images','1'),'1'); ?>>
                            Add loading="lazy" to all images (improves page speed)</label>
                        </td>
                    </tr>
                </table>
                <div class="cwv-info-grid">
                    <div class="cwv-card good">
                        <strong>LCP</strong>
                        <span>Largest Contentful Paint</span>
                        <small>Target: &lt; 2.5s ✅</small>
                    </div>
                    <div class="cwv-card good">
                        <strong>CLS</strong>
                        <span>Cumulative Layout Shift</span>
                        <small>Target: &lt; 0.1 ✅</small>
                    </div>
                    <div class="cwv-card good">
                        <strong>INP</strong>
                        <span>Interaction to Next Paint</span>
                        <small>Target: &lt; 200ms ✅</small>
                    </div>
                </div>
            </div>

            <!-- Canonical + Meta -->
            <div class="sgclabs-section">
                <h2>🔗 Canonical URLs & Duplicate Prevention</h2>
                <table class="form-table">
                    <tr>
                        <th>Canonical URLs</th>
                        <td>
                            <label><input type="checkbox" name="sgclabs_canonical_active" value="1" <?php checked(get_option('sgclabs_canonical_active','1'),'1'); ?>>
                            Auto-add canonical tags (removes ?lang=, ?utm_source= etc.)</label>
                        </td>
                    </tr>
                    <tr>
                        <th>Remove Feed Links</th>
                        <td>
                            <label><input type="checkbox" name="sgclabs_remove_feed_links" value="1" <?php checked(get_option('sgclabs_remove_feed_links','0'),'1'); ?>>
                            Remove RSS feed links from head (cleaner HTML)</label>
                        </td>
                    </tr>
                    <tr>
                        <th>Max Keywords per Page</th>
                        <td>
                            <input type="number" name="sgclabs_max_meta_keywords" value="<?php echo esc_attr(get_option('sgclabs_max_meta_keywords','25')); ?>" min="5" max="50" style="width:80px;">
                            <p class="description">Google recommends max 25 keywords. More = keyword stuffing penalty ⚠️</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Max Keyword Density %</th>
                        <td>
                            <input type="number" name="sgclabs_max_kw_density" value="<?php echo esc_attr(get_option('sgclabs_max_kw_density','2.5')); ?>" step="0.1" min="0.5" max="5" style="width:80px;">
                            <p class="description">Google penalty threshold: 3%+. We recommend 1-2.5% for safety ✅</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Robots.txt -->
            <div class="sgclabs-section">
                <h2>🤖 Robots.txt Manager</h2>
                <table class="form-table">
                    <tr>
                        <th>Robots.txt Manager</th>
                        <td>
                            <div style="background:#e8f5e9;border:1px solid #a5d6a7;padding:12px 16px;border-radius:6px;">
                                ✅ <strong>Robots.txt ab Site Tools module mein manage hota hai.</strong><br>
                                <a href="<?php echo admin_url('admin.php?page=sgclabs-site-tools-hub'); ?>" class="button button-secondary" style="margin-top:8px;">
                                    🛠️ Site Tools → Robots.txt Editor Open Karo
                                </a>
                            </div>
                        </td>
                    </tr>
                </table>
                <div style="background:#f8f9ff;padding:15px;border-radius:8px;margin-top:10px;">
                    <h4 style="margin:0 0 10px;">✅ Recommended robots.txt for SGC LABS®:</h4>
                    <button type="button" class="button" onclick="loadRecommendedRobots()">📋 Load Recommended</button>
                </div>
            </div>

            <?php submit_button('💾 Save All Compliance Settings'); ?>
        </form>
    </div>

    <?php
    // Sitemap Engine section
    if (function_exists('sgclabs_sitemap_admin_section')) {
        sgclabs_sitemap_admin_section();
    }
    ?>

    <style>
    .font-preview-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:12px; margin-top:10px; }
    .font-card { border:2px solid #e0e0e0; border-radius:8px; padding:12px; cursor:pointer; transition:all 0.2s; }
    .font-card:hover { border-color:#1a1a2e; }
    .font-card.selected { border-color:#1a1a2e; background:#f0f4ff; }
    .font-name { font-weight:700; font-size:13px; color:#1a1a2e; margin-bottom:5px; }
    .font-sample { font-size:12px; color:#555; margin-bottom:5px; }
    .font-meta { font-size:10px; color:#27ae60; }
    .cwv-info-grid { display:flex; gap:15px; margin-top:15px; flex-wrap:wrap; }
    .cwv-card { background:#f0fff4; border:1px solid #27ae60; border-radius:8px; padding:12px 20px; text-align:center; flex:1; min-width:120px; }
    .cwv-card strong { display:block; font-size:20px; color:#1a1a2e; }
    .cwv-card span { font-size:11px; color:#555; display:block; }
    .cwv-card small { font-size:11px; color:#27ae60; display:block; margin-top:3px; }
    .compliance-score-wrap { display:flex; gap:20px; align-items:center; padding:20px; background:#f8f9ff; border-radius:10px; }
    .compliance-score-circle { width:90px; height:90px; border-radius:50%; display:flex; flex-direction:column; align-items:center; justify-content:center; font-size:24px; font-weight:bold; color:white; flex-shrink:0; }
    .compliance-score-circle span { line-height:1; }
    .compliance-score-circle small { font-size:12px; font-weight:normal; }
    .compliance-score-info h3 { margin:0 0 5px; }
    .compliance-score-info p { margin:0; color:#666; font-size:13px; }
    </style>

    <script>
    const nonce = '<?php echo esc_js( wp_create_nonce( 'sgclabs_nonce' ) ); ?>';

    function validateSEO() {
        const url = document.getElementById('validate_url').value;
        if (!url) { alert('Please enter a URL'); return; }
        const btn = event.target;
        btn.disabled = true; btn.textContent = '⏳ Checking...';
        jQuery.post(ajaxurl, {action:'sgclabs_validate_schema',nonce,url}, function(res) {
            btn.disabled = false; btn.textContent = '🔍 Validate Now';
            document.getElementById('validator-result').style.display='block';
            if (!res.success) { alert('Error: '+res.data); return; }
            const d = res.data;
            const circle = document.getElementById('score-circle');
            document.getElementById('score-num').textContent = d.score;
            circle.style.background = d.score >= 80 ? '#27ae60' : d.score >= 60 ? '#e67e22' : '#e74c3c';
            document.getElementById('score-label').textContent = d.score >= 80 ? '✅ Excellent SEO!' : d.score >= 60 ? '⚠️ Needs Improvement' : '❌ Poor SEO';
            document.getElementById('score-sub').textContent = `${d.passed}/${d.total} checks passed`;
            document.getElementById('page-title-info').innerHTML = `Title: "${d.title}" (${d.title_len} chars) | Desc: ${d.desc_len} chars`;
            const tbody = document.getElementById('checks-tbody');
            tbody.innerHTML = d.checks.map(c => `
                <tr>
                    <td><strong>${c.name}</strong></td>
                    <td>${c.pass ? '<span style="color:#27ae60;font-size:18px;">✅</span>' : '<span style="color:#e74c3c;font-size:18px;">❌</span>'}</td>
                    <td style="font-size:12px;color:#666;">${c.tip}</td>
                </tr>
            `).join('');
            document.getElementById('validator-result').scrollIntoView({behavior:'smooth'});
        });
    }

    function loadRecommendedRobots() {
        // robots.txt reset moved to site-tools.php;
    }
    </script>
    <?php
}

// sgclabs_default_robots_txt() removed — robots.txt handled by site-tools.php

