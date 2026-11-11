<?php
if (!defined('ABSPATH')) exit;

// ============================================================
// SGC LABS® — ADVANCED SEO TOOLS v6.6.3
// All remaining missing Rank Math Pro features:
// 1. Instant Indexing (IndexNow API)
// 2. WooCommerce SEO fields (Brand/GTIN/MPN/Condition)
// 3. Role Manager + Client Mode
// 4. External Link Attributes (nofollow/sponsored/ugc)
// 5. Auto Redirect on Slug Change
// 6. Regex Redirects
// 7. Bulk SEO Editor (meta title/desc)
// 8. htaccess Editor
// 9. Google Analytics Integration
// 10. Orphan Pages Detection
// 11. SEO Import/Export Wizard
// 12. Link Count in Post List
// 13. Sitemap Enhancements (video/news/exclude)
// ============================================================

// ─────────────────────────────────────────────────────────────
// 1. INSTANT INDEXING — IndexNow API
// ─────────────────────────────────────────────────────────────
add_action('save_post', 'sgclabs_indexnow_on_publish', 99, 2);
function sgclabs_indexnow_on_publish($post_id, $post) {
    if (get_option('sgclabs_indexnow_active', '0') !== '1') return;
    if ($post->post_status !== 'publish') return;
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
    $allowed = get_option('sgclabs_indexnow_post_types', ['post','page','product']);
    if (!in_array($post->post_type, (array)$allowed)) return;

    $url = get_permalink($post_id);
    sgclabs_submit_indexnow($url);
}

function sgclabs_submit_indexnow($url) {
    $api_key = get_option('sgclabs_indexnow_key', '');
    if (!$api_key) {
        $api_key = wp_generate_password(32, false);
        update_option('sgclabs_indexnow_key', $api_key);
    }

    $host = parse_url(home_url(), PHP_URL_HOST);
    $engines = [
        'https://api.indexnow.org/indexnow',
        'https://www.bing.com/indexnow',
        'https://yandex.com/indexnow',
    ];

    foreach ($engines as $endpoint) {
        wp_remote_get(add_query_arg([
            'url'     => $url,
            'key'     => $api_key,
            'keyLocation' => home_url("/{$api_key}.txt"),
        ], $endpoint), ['timeout' => 10, 'blocking' => false]);
    }

    // Log
    $log = get_option('sgclabs_indexnow_log', []);
    array_unshift($log, ['url' => $url, 'time' => current_time('mysql')]);
    $log = array_slice($log, 0, 100);
    update_option('sgclabs_indexnow_log', $log);
}

// Serve IndexNow key file
add_action('init', function() {
    $key = get_option('sgclabs_indexnow_key', '');
    if (!$key) return;
    add_rewrite_rule("^{$key}\\.txt$", 'index.php?sgclabs_indexnow_verify=1', 'top');
    add_filter('query_vars', function($v) { $v[] = 'sgclabs_indexnow_verify'; return $v; });
});
add_action('template_redirect', function() {
    if (!get_query_var('sgclabs_indexnow_verify')) return;
    header('Content-Type: text/plain');
    // Fix 1: esc_attr prevents XSS if key value is ever tampered in DB
    echo esc_attr( get_option('sgclabs_indexnow_key', '') );
    exit;
});

// AJAX: Manual submit
add_action('wp_ajax_sgclabs_indexnow_submit', function() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die();
    $urls = array_filter(array_map('trim', explode("\n", sanitize_textarea_field($_POST['urls'] ?? ''))));
    foreach ($urls as $url) {
        if (filter_var($url, FILTER_VALIDATE_URL)) sgclabs_submit_indexnow($url);
    }
    wp_send_json_success(['submitted' => count($urls)]);
});

// AJAX: Bulk submit all published
add_action('wp_ajax_sgclabs_indexnow_bulk', function() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die();
    $offset = intval($_POST['offset'] ?? 0);
    $batch = 20;
    global $wpdb;
    $urls = $wpdb->get_col($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('post','page','product') ORDER BY ID ASC LIMIT %d OFFSET %d",
        $batch, $offset
    ));
    $total = (int)$wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('post','page','product')");
    foreach ($urls as $pid) sgclabs_submit_indexnow(get_permalink($pid));
    wp_send_json_success(['processed' => $offset + count($urls), 'total' => $total, 'done' => count($urls) < $batch]);
});

// ─────────────────────────────────────────────────────────────
// 2. WOOCOMMERCE SEO FIELDS — Brand/GTIN/MPN/Condition
// ─────────────────────────────────────────────────────────────
add_action('woocommerce_product_options_general_product_data', 'sgclabs_woo_seo_fields');
function sgclabs_woo_seo_fields() {
    echo '<div class="options_group" style="border-top:1px solid #eee;padding-top:10px;">';
    echo '<h4 style="padding-left:12px;color:#6c5ce7;"><strong>🔍 SGC LABS® SEO Fields</strong></h4>';

    woocommerce_wp_text_input(['id'=>'_sgclabs_brand','label'=>'Brand','desc_tip'=>true,'description'=>'Product brand for Google Shopping']);
    woocommerce_wp_text_input(['id'=>'_sgclabs_gtin','label'=>'GTIN/EAN/UPC','desc_tip'=>true,'description'=>'Global Trade Item Number']);
    woocommerce_wp_text_input(['id'=>'_sgclabs_mpn','label'=>'MPN','desc_tip'=>true,'description'=>'Manufacturer Part Number']);
    woocommerce_wp_text_input(['id'=>'_sgclabs_isbn','label'=>'ISBN','desc_tip'=>true,'description'=>'International Standard Book Number']);
    woocommerce_wp_select(['id'=>'_sgclabs_condition','label'=>'Condition','options'=>['NewCondition'=>'New','UsedCondition'=>'Used','RefurbishedCondition'=>'Refurbished','DamagedCondition'=>'Damaged']]);

    echo '</div>';
}

add_action('woocommerce_process_product_meta', 'sgclabs_woo_seo_fields_save');
function sgclabs_woo_seo_fields_save($post_id) {
    foreach (['_sgclabs_brand','_sgclabs_gtin','_sgclabs_mpn','_sgclabs_isbn','_sgclabs_condition'] as $f) {
        if (isset($_POST[$f])) update_post_meta($post_id, $f, sanitize_text_field($_POST[$f]));
    }
}

// Inject Brand/GTIN/MPN into Product Schema
add_filter('woocommerce_structured_data_product', 'sgclabs_enhance_woo_schema', 10, 2);
function sgclabs_enhance_woo_schema($data, $product) {
    $pid = $product->get_id();
    $brand = get_post_meta($pid, '_sgclabs_brand', true);
    $gtin  = get_post_meta($pid, '_sgclabs_gtin', true);
    $mpn   = get_post_meta($pid, '_sgclabs_mpn', true);
    $isbn  = get_post_meta($pid, '_sgclabs_isbn', true);
    $cond  = get_post_meta($pid, '_sgclabs_condition', true);

    if ($brand) $data['brand'] = ['@type' => 'Brand', 'name' => $brand];
    if ($gtin)  $data['gtin'] = $gtin;
    if ($mpn)   $data['mpn'] = $mpn;
    if ($isbn)  $data['isbn'] = $isbn;
    if ($cond)  $data['offers'][0]['itemCondition'] = 'https://schema.org/' . $cond;

    return $data;
}

// ─────────────────────────────────────────────────────────────
// 3. ROLE MANAGER + CLIENT MODE
// ─────────────────────────────────────────────────────────────
function sgclabs_user_can_seo() {
    $allowed_roles = get_option('sgclabs_seo_roles', ['administrator']);
    $user = wp_get_current_user();
    return !empty(array_intersect($allowed_roles, $user->roles)) || current_user_can('manage_options');
}

function sgclabs_is_client_mode() {
    return get_option('sgclabs_client_mode', '0') === '1' && !current_user_can('manage_options');
}

// Hide advanced settings in client mode
add_action('admin_menu', function() {
    if (sgclabs_is_client_mode()) {
        $hide_pages = get_option('sgclabs_client_hide_pages', ['sgclabs-site-tools','sgclabs-compliance','sgclabs-fixes']);
        foreach ($hide_pages as $slug) {
            remove_submenu_page('sgclabs-manager', $slug);
        }
    }
}, 999);

// ─────────────────────────────────────────────────────────────
// 4. EXTERNAL LINK ATTRIBUTES — auto nofollow/sponsored/ugc
// ─────────────────────────────────────────────────────────────
add_filter('the_content', 'sgclabs_add_external_link_attrs', 99);
function sgclabs_add_external_link_attrs($content) {
    if (get_option('sgclabs_ext_link_nofollow', '0') !== '1') return $content;
    // v6.6.3: Skip on Elementor pages — raw JSON would be corrupted by link regex
    if (!is_admin() && (is_front_page() || is_home() || (is_page() && !is_product()))) return $content;
    $home_host = parse_url(home_url(), PHP_URL_HOST);
    $nofollow  = get_option('sgclabs_ext_link_nofollow', '1') === '1';
    $sponsored = get_option('sgclabs_ext_link_sponsored', '0') === '1';
    $ugc       = get_option('sgclabs_ext_link_ugc', '0') === '1';
    $new_tab   = get_option('sgclabs_ext_link_newtab', '1') === '1';
    $exclude   = array_filter(array_map('trim', explode("\n", get_option('sgclabs_ext_link_exclude', ''))));

    $content = preg_replace_callback('/<a\s([^>]*href=["\']https?:\/\/([^"\']+)["\'][^>]*)>/i', function($m) use ($home_host, $nofollow, $sponsored, $ugc, $new_tab, $exclude) {
        $tag = $m[0];
        $domain = parse_url('https://' . $m[2], PHP_URL_HOST);

        // Skip internal links
        if ($domain && (stripos((string)$domain, $home_host) !== false)) return $tag;

        // Skip excluded domains
        foreach ($exclude as $ex) {
            if ($ex && stripos((string)$domain, $ex) !== false) return $tag;
        }

        $rels = [];
        if ($nofollow) $rels[] = 'nofollow';
        if ($sponsored) $rels[] = 'sponsored';
        if ($ugc) $rels[] = 'ugc';
        $rels[] = 'noopener';

        // Add/update rel attribute
        if (preg_match('/rel=["\']([^"\']*)["\']/', $tag, $rel_m)) {
            $existing = array_filter(explode(' ', $rel_m[1]));
            $rels = array_unique(array_merge($existing, $rels));
            $tag = preg_replace('/rel=["\'][^"\']*["\']/', 'rel="' . implode(' ', $rels) . '"', $tag);
        } else {
            $tag = str_replace('<a ', '<a rel="' . implode(' ', $rels) . '" ', $tag);
        }

        // Open in new tab
        if ($new_tab && strpos((string)$tag, 'target=') === false) {
            $tag = str_replace('<a ', '<a target="_blank" ', $tag);
        }

        return $tag;
    }, $content);

    return $content;
}

// ─────────────────────────────────────────────────────────────
// 5. AUTO REDIRECT ON SLUG CHANGE
// ─────────────────────────────────────────────────────────────
add_action('post_updated', 'sgclabs_auto_redirect_slug_change', 10, 3);
function sgclabs_auto_redirect_slug_change($post_id, $post_after, $post_before) {
    if (get_option('sgclabs_auto_redirect_slug', '1') !== '1') return;
    if ($post_before->post_status !== 'publish' || $post_after->post_status !== 'publish') return;
    if ($post_before->post_name === $post_after->post_name) return;

    $old_url = '/' . ltrim(str_replace(home_url(), '', get_permalink($post_id)), '/');
    // Build old URL from old slug
    $old_permalink = str_replace($post_after->post_name, $post_before->post_name, $old_url);
    if ($old_permalink === $old_url) return;

    global $wpdb;
    $table = $wpdb->prefix . 'sgclabs_redirects';
    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE source_url=%s", $old_permalink));
    if (!$exists) {
        $wpdb->insert($table, [
            'source_url' => $old_permalink,
            'target_url' => get_permalink($post_id),
            'type' => '301',
            'enabled' => 1,
            'note' => 'Auto: slug changed from ' . $post_before->post_name,
        ]);
    }
}

// ─────────────────────────────────────────────────────────────
// 6. REGEX REDIRECTS
// ─────────────────────────────────────────────────────────────
add_action('template_redirect', 'sgclabs_regex_redirect_check', 2);
function sgclabs_regex_redirect_check() {
    if (is_admin()) return;
    $regex_rules = get_option('sgclabs_regex_redirects', []);
    if (empty($regex_rules)) return;

    $request_path = strtolower(rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/')) ?: '/';

    foreach ($regex_rules as $rule) {
        if (empty($rule['pattern']) || empty($rule['target']) || empty($rule['enabled'])) continue;
        $pattern = '/' . str_replace('/', '\/', $rule['pattern']) . '/i';
        if (@preg_match($pattern, $request_path, $matches)) {
            $target = $rule['target'];
            // Replace capture groups
            for ($i = 1; $i <= 9; $i++) {
                if (isset($matches[$i])) $target = str_replace('$' . $i, $matches[$i], $target);
            }
            if (strpos((string)$target, 'http') !== 0) $target = home_url($target);
            $code = in_array($rule['type'] ?? '', ['301','302','307']) ? (int)$rule['type'] : 301;
            wp_redirect(esc_url_raw($target), $code);
            exit;
        }
    }
}

// ─────────────────────────────────────────────────────────────
// 7. BULK SEO EDITOR — AJAX list + inline edit
// ─────────────────────────────────────────────────────────────
add_action('wp_ajax_sgclabs_bulk_seo_list', function() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die();

    $post_type = sanitize_key($_POST['post_type'] ?? 'post');
    $page = max(1, intval($_POST['page'] ?? 1));
    $per_page = 25;
    $search = sanitize_text_field($_POST['search'] ?? '');
    $category = isset( $_POST['category'] ) ? (int) $_POST['category'] : 0;

    $args = ['post_type'=>$post_type, 'post_status'=>'publish', 'posts_per_page'=>$per_page, 'paged'=>$page, 'orderby'=>'ID', 'order'=>'DESC'];
    if ($search) $args['s'] = $search;
    // FR5: category filter for products.
    if ( $post_type === 'product' && $category > 0 ) {
        $args['tax_query'] = [
            [
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $category,
            ],
        ];
    }

    $query = new WP_Query($args);
    $posts = [];
    $use_canonical = function_exists( 'sgclabs_seo_get' );
    foreach ($query->posts as $p) {
        $posts[] = [
            'id' => $p->ID,
            'title' => $p->post_title,
            // FR6: read via canonical API; legacy bridge auto-redirects old keys.
            'seo_title' => $use_canonical
                ? sgclabs_seo_get( $p->ID, 'seo_title' )
                : ( get_post_meta($p->ID, '_sgclabs_seo_title', true) ?: '' ),
            'seo_desc'  => $use_canonical
                ? sgclabs_seo_get( $p->ID, 'meta_desc' )
                : ( get_post_meta($p->ID, '_sgclabs_seo_description', true) ?: '' ),
            'focus_kw'  => $use_canonical
                ? sgclabs_seo_get( $p->ID, 'focus_kw' )
                : ( get_post_meta($p->ID, '_sgclabs_focus_kw', true) ?: '' ),
            'score'     => get_post_meta($p->ID, '_sgclabs_seo_score', true) ?: 0,
            'noindex'   => get_post_meta($p->ID, '_sgclabs_robots_noindex', true) ?: '',
        ];
    }

    wp_send_json_success(['posts'=>$posts, 'total'=>$query->found_posts, 'pages'=>$query->max_num_pages, 'page'=>$page]);
});

add_action('wp_ajax_sgclabs_bulk_seo_save', function() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die();

    $post_id = intval($_POST['post_id'] ?? 0);
    if (!$post_id) wp_send_json_error('No post ID');

    // FR6: write via canonical API so every consumer sees the new value
    // (and source is recorded as 'manual' for hybrid migrator).
    if ( function_exists( 'sgclabs_seo_set' ) ) {
        if (isset($_POST['seo_title'])) sgclabs_seo_set( $post_id, 'seo_title', sanitize_text_field($_POST['seo_title']),  'manual' );
        if (isset($_POST['seo_desc']))  sgclabs_seo_set( $post_id, 'meta_desc', sanitize_textarea_field($_POST['seo_desc']), 'manual' );
        if (isset($_POST['focus_kw']))  sgclabs_seo_set( $post_id, 'focus_kw', sanitize_text_field($_POST['focus_kw']),    'manual' );
    } else {
        if (isset($_POST['seo_title'])) update_post_meta($post_id, '_sgclabs_seo_title', sanitize_text_field($_POST['seo_title']));
        if (isset($_POST['seo_desc']))  update_post_meta($post_id, '_sgclabs_seo_description', sanitize_textarea_field($_POST['seo_desc']));
        if (isset($_POST['focus_kw']))  update_post_meta($post_id, '_sgclabs_focus_keyword', sanitize_text_field($_POST['focus_kw']));
    }

    $score = sgclabs_calculate_seo_score($post_id);
    update_post_meta($post_id, '_sgclabs_seo_score', $score);

    wp_send_json_success(['score' => $score]);
});

// ─────────────────────────────────────────────────────────────
// 8. HTACCESS EDITOR
// ─────────────────────────────────────────────────────────────
add_action('wp_ajax_sgclabs_save_htaccess', function() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die();

    $content = wp_unslash($_POST['htaccess'] ?? '');
    $htaccess_file = ABSPATH . '.htaccess';

    // Fix 2: Block dangerous directives that could execute PHP or compromise server
    $dangerous_patterns = [
        '/php_value\s+auto_prepend_file/i',
        '/php_value\s+auto_append_file/i',
        '/php_admin_value\s+auto_prepend_file/i',
        '/AddHandler\s+.*php/i',
        '/SetHandler\s+.*php/i',
        '/php_flag\s+engine\s+on/i',
        '/php_value\s+open_basedir/i',
    ];
    foreach ( $dangerous_patterns as $pattern ) {
        if ( preg_match( $pattern, $content ) ) {
            wp_send_json_error( 'Security: Dangerous directive detected. Save blocked.' );
            return;
        }
    }

    if (!is_writable($htaccess_file) && file_exists($htaccess_file)) {
        wp_send_json_error('File not writable');
    }

    // Backup
    $backup = get_option('sgclabs_htaccess_backup', '');
    if (!$backup && file_exists($htaccess_file)) {
        update_option('sgclabs_htaccess_backup', file_get_contents($htaccess_file));
    }

    file_put_contents($htaccess_file, $content);
    wp_send_json_success();
});

add_action('wp_ajax_sgclabs_restore_htaccess', function() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die();
    $backup = get_option('sgclabs_htaccess_backup', '');
    if ($backup) {
        file_put_contents(ABSPATH . '.htaccess', $backup);
        wp_send_json_success();
    }
    wp_send_json_error('No backup found');
});

// ─────────────────────────────────────────────────────────────
// 9. GOOGLE ANALYTICS INTEGRATION (GA4)
// ─────────────────────────────────────────────────────────────
add_action('wp_head', 'sgclabs_ga4_tracking', 1);
function sgclabs_ga4_tracking() {
    if (is_admin()) return;
    $ga_id = get_option('sgclabs_ga4_id', '');
    if (!$ga_id) return;
    if (get_option('sgclabs_ga4_exclude_admin', '1') === '1' && current_user_can('manage_options')) return;
    ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($ga_id); ?>"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?php echo esc_js($ga_id); ?>');</script>
    <?php
}

// ─────────────────────────────────────────────────────────────
// 10. ORPHAN PAGES DETECTION
// ─────────────────────────────────────────────────────────────
add_action('wp_ajax_sgclabs_find_orphans', function() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die();

    global $wpdb;
    @ini_set('memory_limit', '512M');

    $published = $wpdb->get_results(
        "SELECT ID, post_title, post_type FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('post','page','product') ORDER BY ID DESC LIMIT 500",
        ARRAY_A
    );

    $orphans = [];
    $home = home_url();

    foreach ($published as $p) {
        $permalink = get_permalink($p['ID']);
        $relative = str_replace($home, '', $permalink);
        $slug = basename(parse_url($permalink, PHP_URL_PATH));
        if (!$slug || !$relative) continue;

        // Check if any other published post links to this page (DB-level search, no GROUP_CONCAT)
        $linked = false;
        $esc_relative = '%' . $wpdb->esc_like($relative) . '%';
        $esc_slug = '%' . $wpdb->esc_like($slug) . '%';
        $found = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='publish' AND ID != %d AND (post_content LIKE %s OR post_content LIKE %s) LIMIT 1",
            $p['ID'], $esc_relative, $esc_slug
        ));
        if ($found > 0) $linked = true;

        // Also check menus
        if (!$linked) {
            $menus = wp_get_nav_menus();
            foreach ($menus as $menu) {
                $items = wp_get_nav_menu_items($menu->term_id);
                if ($items) foreach ($items as $item) {
                    if ($item->object_id == $p['ID'] || (isset($item->url) && $relative && stripos($item->url, $relative) !== false)) {
                        $linked = true; break 2;
                    }
                }
            }
        }

        if (!$linked) {
            $orphans[] = [
                'id' => $p['ID'],
                'title' => $p['post_title'],
                'type' => $p['post_type'],
                'url' => $permalink,
            ];
        }
    }

    wp_send_json_success(['orphans' => $orphans, 'total' => count($published), 'orphan_count' => count($orphans)]);
});

// ─────────────────────────────────────────────────────────────
// 11. SEO IMPORT/EXPORT WIZARD
// ─────────────────────────────────────────────────────────────
add_action('wp_ajax_sgclabs_seo_export', function() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die();

    global $wpdb;
    $meta = $wpdb->get_results(
        "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key LIKE '_sgclabs_%' AND meta_key NOT LIKE '%api_key%' AND meta_key NOT LIKE '%secret%'",
        ARRAY_A
    );

    $options = $wpdb->get_results(
        "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'sgclabs_%' AND option_name NOT LIKE '%api_key%' AND option_name NOT LIKE '%secret%' AND option_name NOT LIKE '%token%'",
        ARRAY_A
    );

    wp_send_json_success(['meta' => $meta, 'options' => $options, 'version' => SGCLABS_VERSION]);
});

add_action('wp_ajax_sgclabs_seo_import', function() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die();

    $json = json_decode(wp_unslash($_POST['import_data'] ?? ''), true);
    if (!$json) wp_send_json_error('Invalid JSON');

    $imported = 0;
    if (!empty($json['meta'])) {
        foreach ($json['meta'] as $m) {
            // Fix 3a: sanitize meta_value before saving
            $safe_meta_val = sanitize_textarea_field( wp_unslash( $m['meta_value'] ?? '' ) );
            update_post_meta(intval($m['post_id']), sanitize_key($m['meta_key']), $safe_meta_val);
            $imported++;
        }
    }
    if (!empty($json['options'])) {
        foreach ($json['options'] as $o) {
            // Fix 3b: sanitize option_value before saving — prevents stored XSS via import
            $safe_opt_val = sanitize_text_field( wp_unslash( $o['option_value'] ?? '' ) );
            update_option(sanitize_key($o['option_name']), $safe_opt_val);
            $imported++;
        }
    }

    wp_send_json_success(['imported' => $imported]);
});

// Import from Yoast SEO
add_action('wp_ajax_sgclabs_import_yoast', function() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die();

    global $wpdb;
    $yoast_meta = $wpdb->get_results(
        "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ('_yoast_wpseo_title','_yoast_wpseo_metadesc','_yoast_wpseo_focuskw','_yoast_wpseo_canonical','_yoast_wpseo_meta-robots-noindex','_yoast_wpseo_meta-robots-nofollow')",
        ARRAY_A
    );

    $map = [
        '_yoast_wpseo_title'      => '_sgclabs_seo_title',
        '_yoast_wpseo_metadesc'   => '_sgclabs_seo_description',
        '_yoast_wpseo_focuskw'    => '_sgclabs_focus_keyword',
        '_yoast_wpseo_canonical'  => '_sgclabs_canonical_url',
        '_yoast_wpseo_meta-robots-noindex'  => '_sgclabs_robots_noindex',
        '_yoast_wpseo_meta-robots-nofollow' => '_sgclabs_robots_nofollow',
    ];

    $imported = 0;
    foreach ($yoast_meta as $m) {
        $new_key = $map[$m['meta_key']] ?? null;
        if ($new_key && !empty($m['meta_value'])) {
            update_post_meta(intval($m['post_id']), $new_key, $m['meta_value']);
            $imported++;
        }
    }

    wp_send_json_success(['imported' => $imported]);
});

// Import from RankMath
add_action('wp_ajax_sgclabs_import_rankmath', function() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die();

    global $wpdb;
    $rm_meta = $wpdb->get_results(
        "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ('rank_math_title','rank_math_description','rank_math_focus_keyword','rank_math_canonical_url','rank_math_robots')",
        ARRAY_A
    );

    $map = [
        'rank_math_title'         => '_sgclabs_seo_title',
        'rank_math_description'   => '_sgclabs_seo_description',
        'rank_math_focus_keyword' => '_sgclabs_focus_keyword',
        'rank_math_canonical_url' => '_sgclabs_canonical_url',
    ];

    $imported = 0;
    foreach ($rm_meta as $m) {
        if ($m['meta_key'] === 'rank_math_robots') {
            $robots = maybe_unserialize($m['meta_value']);
            if (is_array($robots)) {
                if (in_array('noindex', $robots)) update_post_meta(intval($m['post_id']), '_sgclabs_robots_noindex', '1');
                if (in_array('nofollow', $robots)) update_post_meta(intval($m['post_id']), '_sgclabs_robots_nofollow', '1');
            }
            $imported++;
            continue;
        }
        $new_key = $map[$m['meta_key']] ?? null;
        if ($new_key && !empty($m['meta_value'])) {
            update_post_meta(intval($m['post_id']), $new_key, $m['meta_value']);
            $imported++;
        }
    }

    wp_send_json_success(['imported' => $imported]);
});

// ─────────────────────────────────────────────────────────────
// 12. LINK COUNT IN POST LIST COLUMNS
// ─────────────────────────────────────────────────────────────
add_filter('manage_posts_columns', 'sgclabs_add_link_count_column');
add_filter('manage_pages_columns', 'sgclabs_add_link_count_column');
function sgclabs_add_link_count_column($columns) {
    $columns['sgc_links'] = 'Links';
    return $columns;
}

add_action('manage_posts_custom_column', 'sgclabs_link_count_column_data', 10, 2);
add_action('manage_pages_custom_column', 'sgclabs_link_count_column_data', 10, 2);
function sgclabs_link_count_column_data($column, $post_id) {
    if ($column !== 'sgc_links') return;
    $content = get_post_field('post_content', $post_id);
    $home = home_url();
    $domain = parse_url($home, PHP_URL_HOST);
    $internal = 0; $external = 0;

    if (preg_match_all('/href=["\']([^"\'#]+)["\']/', $content, $m)) {
        foreach ($m[1] as $url) {
            $url_host = parse_url($url, PHP_URL_HOST);
            if (!$url_host || stripos((string)$url_host, $domain) !== false || strpos((string)$url, '/') === 0) $internal++;
            else $external++;
        }
    }
    echo "<span title='Internal' style='color:#27ae60;'>🔗{$internal}</span> <span title='External' style='color:#e67e22;'>🌐{$external}</span>";
}

// ─────────────────────────────────────────────────────────────
// 13. SITEMAP ENHANCEMENTS — Post Exclusion & Post-type Control
// ─────────────────────────────────────────────────────────────
// Per-post sitemap exclusion (stored in meta)
add_action('save_post', function($post_id) {
    if (!isset($_POST['sgclabs_seo_metabox_nonce'])) return;
    $exclude = isset($_POST['_sgclabs_sitemap_exclude']) ? '1' : '';
    update_post_meta($post_id, '_sgclabs_sitemap_exclude', $exclude);
}, 12);

// Filter sitemap query to exclude marked posts
add_filter('sgclabs_sitemap_exclude_ids', function($ids) {
    global $wpdb;
    $excluded = $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_sgclabs_sitemap_exclude' AND meta_value='1'");
    return array_merge($ids, array_map('intval', $excluded));
});
