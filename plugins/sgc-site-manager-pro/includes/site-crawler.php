<?php
if (!defined('ABSPATH')) exit;

if (get_option('sgclabs_site_crawler_active', '1') !== '1') return;

// ============================================================
// SGC LABS® SITE CRAWLER v6.6.3
// 4-in-1: Site Crawler + GSC Errors + Sitemap Submit + SEO Audit
// ============================================================

// ---- DB TABLE CREATION ----
function sgclabs_crawler_create_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // Crawl results table
    dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sgclabs_crawl_pages (
        id          bigint(20) NOT NULL AUTO_INCREMENT,
        url         varchar(500) NOT NULL,
        post_id     bigint(20) DEFAULT 0,
        post_type   varchar(50) DEFAULT '',
        page_title  varchar(500) DEFAULT '',
        h1          varchar(500) DEFAULT '',
        meta_title  varchar(500) DEFAULT '',
        meta_desc   text DEFAULT '',
        word_count  int DEFAULT 0,
        img_count   int DEFAULT 0,
        img_no_alt  int DEFAULT 0,
        status_code int DEFAULT 0,
        load_time   float DEFAULT 0,
        issues      text DEFAULT '',
        seo_score   int DEFAULT 0,
        crawled_at  datetime DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY url_key (url(255))
    ) $charset;");

    // Sitemap submit log
    dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sgclabs_sitemap_pings (
        id          bigint(20) NOT NULL AUTO_INCREMENT,
        engine      varchar(50) NOT NULL,
        sitemap_url varchar(500) NOT NULL,
        response    int DEFAULT 0,
        pinged_at   datetime DEFAULT NULL,
        PRIMARY KEY (id)
    ) $charset;");
}
add_action('init', function() {
    static $done = false;
    if ($done) return;
    $done = true;
    if (get_option('sgclabs_crawler_db_ver', '0') !== '1.0') {
        sgclabs_crawler_create_tables();
        update_option('sgclabs_crawler_db_ver', '1.0');
    }
});

// ============================================================
// AJAX 1: Collect all page URLs from site
// ============================================================
add_action('wp_ajax_sgclabs_crawler_collect', function() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }
    global $wpdb;

    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sgclabs_crawl_pages");

    $urls = [];

    // Homepage
    $urls[] = ['url' => home_url('/'), 'post_id' => 0, 'post_type' => 'home'];

    // All published posts/pages/products
    $post_types = ['post', 'page', 'product'];
    foreach ($post_types as $pt) {
        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_type FROM {$wpdb->posts}
             WHERE post_type = %s AND post_status = 'publish'
             ORDER BY ID ASC",
            $pt
        ));
        foreach ($posts as $p) {
            $permalink = get_permalink($p->ID);
            if ($permalink) $urls[] = ['url' => $permalink, 'post_id' => $p->ID, 'post_type' => $p->post_type];
        }
    }

    // Insert into DB
    $count = 0;
    foreach ($urls as $item) {
        $wpdb->insert($wpdb->prefix . 'sgclabs_crawl_pages', [
            'url'       => esc_url_raw($item['url']),
            'post_id'   => intval($item['post_id']),
            'post_type' => sanitize_key($item['post_type']),
        ]);
        $count++;
    }

    wp_send_json_success(['total' => $count, 'message' => "{$count} URLs collected"]);
});

// ============================================================
// AJAX 2: Audit batch — check each URL (SEO + status)
// ============================================================
add_action('wp_ajax_sgclabs_crawler_audit_batch', function() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }
    global $wpdb;

    $batch  = 5;
    $offset = intval($_POST['offset'] ?? 0);

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sgclabs_crawl_pages
         WHERE crawled_at IS NULL ORDER BY id ASC LIMIT %d OFFSET %d",
        $batch, $offset
    ));

    $total   = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_crawl_pages");
    $audited = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_crawl_pages WHERE crawled_at IS NOT NULL");

    $processed = 0;
    foreach ($rows as $row) {
        $result = sgclabs_audit_single_url($row->url, $row->post_id);
        $wpdb->update(
            $wpdb->prefix . 'sgclabs_crawl_pages',
            array_merge($result, ['crawled_at' => current_time('mysql')]),
            ['id' => $row->id]
        );
        $processed++;
    }

    $new_audited = $audited + $processed;
    wp_send_json_success([
        'processed'   => $processed,
        'total'       => $total,
        'audited'     => $new_audited,
        'percent'     => $total > 0 ? min(100, round($new_audited / $total * 100)) : 0,
        'finished'    => count($rows) < $batch || $new_audited >= $total,
        'next_offset' => $offset + $batch,
    ]);
});

// ============================================================
// CORE: Audit a single URL — SEO checks
// ============================================================
function sgclabs_audit_single_url(string $url, int $post_id): array {
    $start    = microtime(true);
    $response = wp_remote_get($url, [
        'timeout'    => 15,
        'user-agent' => 'SGCLabsCrawler/1.0 (WordPress SEO Audit)',
        'sslverify'  => false,
    ]);
    $load_time   = round(microtime(true) - $start, 3);
    $status_code = is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response);
    $body        = is_wp_error($response) ? '' : wp_remote_retrieve_body($response);

    $issues    = [];
    $seo_score = 100;

    // — Parse HTML —
    $page_title = '';
    $h1         = '';
    $meta_title = '';
    $meta_desc  = '';
    $word_count = 0;
    $img_count  = 0;
    $img_no_alt = 0;

    if ($body) {
        // Title tag
        preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $m);
        $page_title = isset($m[1]) ? trim(strip_tags($m[1])) : '';

        // H1
        preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $body, $m);
        $h1 = isset($m[1]) ? trim(strip_tags($m[1])) : '';

        // Meta title (og:title or title)
        preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/i', $body, $m);
        $meta_title = $m[1] ?? $page_title;

        // Meta description
        preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/i', $body, $m);
        $meta_desc = $m[1] ?? '';

        // Word count (strip tags, count words)
        $clean_body = strip_tags(preg_replace('/<(script|style)[^>]*>.*?<\/(script|style)>/is', '', $body));
        $word_count = str_word_count(html_entity_decode($clean_body, ENT_QUOTES, 'UTF-8'));

        // Images
        preg_match_all('/<img[^>]+>/i', $body, $imgs);
        $img_count = count($imgs[0]);
        foreach ($imgs[0] as $img) {
            if (!preg_match('/alt=["\'][^"\']+["\']/i', $img)) $img_no_alt++;
        }
    }

    // — SEO Issue Detection —
    if ($status_code === 0)   { $issues[] = 'Connection failed'; $seo_score -= 50; }
    if ($status_code === 404) { $issues[] = '404 Not Found'; $seo_score -= 50; }
    if ($status_code >= 500)  { $issues[] = 'Server Error ' . $status_code; $seo_score -= 40; }
    if ($status_code === 301 || $status_code === 302) { $issues[] = 'Redirect ' . $status_code; $seo_score -= 5; }

    if (empty($page_title))        { $issues[] = 'Missing title tag'; $seo_score -= 15; }
    elseif (strlen($page_title) < 30) { $issues[] = 'Title too short (' . strlen($page_title) . ' chars)'; $seo_score -= 8; }
    elseif (strlen($page_title) > 65) { $issues[] = 'Title too long (' . strlen($page_title) . ' chars)'; $seo_score -= 5; }

    if (empty($h1))               { $issues[] = 'Missing H1'; $seo_score -= 15; }
    if (empty($meta_desc))        { $issues[] = 'Missing meta description'; $seo_score -= 12; }
    elseif (strlen($meta_desc) < 100) { $issues[] = 'Meta desc too short'; $seo_score -= 5; }
    elseif (strlen($meta_desc) > 165) { $issues[] = 'Meta desc too long'; $seo_score -= 3; }

    if ($word_count < 100 && $status_code === 200) { $issues[] = 'Thin content (' . $word_count . ' words)'; $seo_score -= 15; }
    if ($img_no_alt > 0)          { $issues[] = $img_no_alt . ' images missing alt text'; $seo_score -= min(10, $img_no_alt * 2); }
    if ($load_time > 3.0)         { $issues[] = 'Slow page (' . $load_time . 's)'; $seo_score -= 10; }
    if ($load_time > 5.0)         { $seo_score -= 10; } // extra penalty

    $seo_score = max(0, min(100, $seo_score));

    return [
        'page_title'  => sanitize_text_field(substr($page_title, 0, 490)),
        'h1'          => sanitize_text_field(substr($h1, 0, 490)),
        'meta_title'  => sanitize_text_field(substr($meta_title, 0, 490)),
        'meta_desc'   => sanitize_textarea_field(substr($meta_desc, 0, 900)),
        'word_count'  => $word_count,
        'img_count'   => $img_count,
        'img_no_alt'  => $img_no_alt,
        'status_code' => $status_code,
        'load_time'   => $load_time,
        'issues'      => implode(' | ', $issues),
        'seo_score'   => $seo_score,
    ];
}

// ============================================================
// AJAX 3: Get audit results for table
// ============================================================
add_action('wp_ajax_sgclabs_crawler_results', function() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }
    global $wpdb;

    $filter     = sanitize_key($_POST['filter'] ?? 'all');
    $page       = max(1, intval($_POST['page'] ?? 1));
    $per_page   = 25;
    $offset     = ($page - 1) * $per_page;

    $where = 'WHERE crawled_at IS NOT NULL';
    if ($filter === 'issues')   $where .= " AND issues != ''";
    if ($filter === 'broken')   $where .= " AND (status_code = 0 OR status_code = 404 OR status_code >= 500)";
    if ($filter === 'thin')     $where .= " AND word_count < 100 AND word_count > 0";
    if ($filter === 'no_meta')  $where .= " AND (meta_desc = '' OR meta_desc IS NULL)";
    if ($filter === 'no_h1')    $where .= " AND (h1 = '' OR h1 IS NULL)";
    if ($filter === 'slow')     $where .= " AND load_time > 3.0";
    if ($filter === 'products') $where .= " AND post_type = 'product'";

    $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_crawl_pages $where");
    $rows  = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sgclabs_crawl_pages $where ORDER BY seo_score ASC LIMIT %d OFFSET %d",
        $per_page, $offset
    ));

    // Summary stats
    $stats = $wpdb->get_row("SELECT
        COUNT(*) as total_crawled,
        SUM(CASE WHEN status_code=0 OR status_code=404 OR status_code>=500 THEN 1 ELSE 0 END) as broken,
        SUM(CASE WHEN issues != '' THEN 1 ELSE 0 END) as with_issues,
        SUM(CASE WHEN word_count < 100 AND word_count > 0 THEN 1 ELSE 0 END) as thin_content,
        SUM(CASE WHEN meta_desc = '' OR meta_desc IS NULL THEN 1 ELSE 0 END) as no_meta,
        SUM(CASE WHEN h1 = '' OR h1 IS NULL THEN 1 ELSE 0 END) as no_h1,
        SUM(CASE WHEN img_no_alt > 0 THEN 1 ELSE 0 END) as missing_alt,
        SUM(CASE WHEN load_time > 3 THEN 1 ELSE 0 END) as slow_pages,
        AVG(seo_score) as avg_score
    FROM {$wpdb->prefix}sgclabs_crawl_pages WHERE crawled_at IS NOT NULL");

    wp_send_json_success(['rows' => $rows, 'total' => $total, 'stats' => $stats, 'pages' => ceil($total / $per_page)]);
});

// ============================================================
// AJAX 4: Sitemap Submit to Google + Bing
// ============================================================
add_action('wp_ajax_sgclabs_sitemap_submit', function() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }
    global $wpdb;

    // Detect sitemap URLs
    $sitemaps = [];

    // Our plugin sitemap
    $plugin_sitemap = home_url('/?sgclabs_sitemap=1');
    $sitemaps[] = $plugin_sitemap;

    // Rank Math sitemap
    if (function_exists('rank_math')) $sitemaps[] = home_url('/sitemap_index.xml');

    // Yoast sitemap
    if (defined('WPSEO_VERSION')) $sitemaps[] = home_url('/sitemap_index.xml');

    // WordPress default
    $sitemaps[] = home_url('/wp-sitemap.xml');

    // Remove duplicates
    $sitemaps = array_unique($sitemaps);

    $engines = [
        'Google' => 'https://www.google.com/ping?sitemap=',
        'Bing'   => 'https://www.bing.com/ping?sitemap=',
    ];

    $results = [];
    foreach ($sitemaps as $sitemap_url) {
        // Verify sitemap exists first
        $check = wp_remote_head($sitemap_url, ['timeout' => 8, 'sslverify' => false]);
        if (is_wp_error($check) || wp_remote_retrieve_response_code($check) !== 200) continue;

        foreach ($engines as $engine => $ping_base) {
            $ping_url = $ping_base . urlencode($sitemap_url);
            $resp     = wp_remote_get($ping_url, ['timeout' => 10]);
            $code     = is_wp_error($resp) ? 0 : wp_remote_retrieve_response_code($resp);

            $wpdb->insert($wpdb->prefix . 'sgclabs_sitemap_pings', [
                'engine'      => $engine,
                'sitemap_url' => $sitemap_url,
                'response'    => $code,
                'pinged_at'   => current_time('mysql'),
            ]);

            $results[] = [
                'engine'   => $engine,
                'sitemap'  => $sitemap_url,
                'code'     => $code,
                'success'  => ($code >= 200 && $code < 300),
            ];
        }
    }

    // Also ping IndexNow (Bing/Yandex new protocol)
    $indexnow_key = get_option('sgclabs_indexnow_key', '');
    if ($indexnow_key) {
        $indexnow_url = 'https://api.indexnow.org/indexnow?url=' . urlencode(home_url('/')) . '&key=' . $indexnow_key;
        $resp = wp_remote_get($indexnow_url, ['timeout' => 8]);
        $results[] = ['engine' => 'IndexNow', 'sitemap' => home_url('/'), 'code' => is_wp_error($resp) ? 0 : wp_remote_retrieve_response_code($resp), 'success' => true];
    }

    update_option('sgclabs_last_sitemap_ping', current_time('mysql'));
    wp_send_json_success(['results' => $results, 'count' => count($results)]);
});

// ============================================================
// AJAX 5: GSC Crawl Errors (from stored GSC data)
// ============================================================
add_action('wp_ajax_sgclabs_gsc_crawl_errors', function() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }
    global $wpdb;

    // Pull from GSC keywords table — low CTR / zero impressions = crawl issues
    $gsc_data = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}sgclabs_kw_gsc
         WHERE impressions = 0 OR ctr < 0.01
         ORDER BY clicks ASC LIMIT 100"
    );

    // Also check our crawl table for broken pages
    $broken = $wpdb->get_results(
        "SELECT url, status_code, issues, seo_score, crawled_at
         FROM {$wpdb->prefix}sgclabs_crawl_pages
         WHERE status_code IN (0, 301, 302, 404) OR status_code >= 500
         ORDER BY status_code DESC LIMIT 100"
    );

    $gsc_connected = !empty(get_option('sgclabs_gsc_access_token'));
    $last_sync     = get_option('sgclabs_gsc_last_sync', '');

    wp_send_json_success([
        'gsc_connected'   => $gsc_connected,
        'last_sync'       => $last_sync,
        'low_ctr_pages'   => $gsc_data,
        'broken_pages'    => $broken,
        'gsc_count'       => count($gsc_data),
        'broken_count'    => count($broken),
    ]);
});

// ============================================================
// AJAX 6: Export crawl report as CSV
// ============================================================
add_action('wp_ajax_sgclabs_crawler_export', function() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }
    global $wpdb;

    $rows = $wpdb->get_results(
        "SELECT url, post_type, page_title, h1, meta_desc, word_count,
                img_count, img_no_alt, status_code, load_time, seo_score, issues, crawled_at
         FROM {$wpdb->prefix}sgclabs_crawl_pages
         WHERE crawled_at IS NOT NULL
         ORDER BY seo_score ASC"
    );

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="sgclabs-seo-audit-' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['URL', 'Type', 'Page Title', 'H1', 'Meta Desc', 'Words', 'Images', 'Alt Missing', 'Status', 'Load(s)', 'SEO Score', 'Issues', 'Crawled At']);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r->url, $r->post_type, $r->page_title, $r->h1, $r->meta_desc,
            $r->word_count, $r->img_count, $r->img_no_alt,
            $r->status_code, $r->load_time, $r->seo_score, $r->issues, $r->crawled_at
        ]);
    }
    fclose($out);
    exit;
});

// ============================================================
// DASHBOARD WIDGET
// ============================================================
add_action('wp_dashboard_setup', function() {
    wp_add_dashboard_widget(
        'sgclabs_crawler_widget',
        '🕷️ SGC SEO Crawler — Site Health',
        'sgclabs_crawler_dashboard_widget'
    );
});

function sgclabs_crawler_dashboard_widget() {
    global $wpdb;
    $stats = $wpdb->get_row("SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status_code=0 OR status_code=404 OR status_code>=500 THEN 1 ELSE 0 END) as broken,
        SUM(CASE WHEN issues != '' THEN 1 ELSE 0 END) as issues,
        SUM(CASE WHEN word_count < 100 AND word_count > 0 THEN 1 ELSE 0 END) as thin,
        SUM(CASE WHEN meta_desc = '' OR meta_desc IS NULL THEN 1 ELSE 0 END) as no_meta,
        AVG(seo_score) as avg_score,
        MAX(crawled_at) as last_crawl
    FROM {$wpdb->prefix}sgclabs_crawl_pages WHERE crawled_at IS NOT NULL");

    $last_ping = get_option('sgclabs_last_sitemap_ping', '');
    $score     = $stats ? round($stats->avg_score ?? 0) : 0;
    $color     = $score >= 80 ? '#16a34a' : ($score >= 60 ? '#d97706' : '#dc2626');
    $admin_url = admin_url('admin.php?page=sgclabs-site-tools-hub&tab=crawler');

    echo '<div style="font-family:Inter,sans-serif;">';

    if (!$stats || !$stats->total) {
        echo '<p style="color:#6b7280;">No crawl data yet. <a href="' . esc_url($admin_url) . '">Run your first crawl →</a></p>';
    } else {
        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">';
        $items = [
            ['🔴 Broken Pages', $stats->broken, '#fef2f2', '#dc2626'],
            ['⚠️ Pages with Issues', $stats->issues, '#fffbeb', '#d97706'],
            ['📄 Thin Content', $stats->thin, '#eff6ff', '#2563eb'],
            ['🏷️ Missing Meta', $stats->no_meta, '#f0fdf4', '#16a34a'],
        ];
        foreach ($items as $item) {
            echo '<div style="background:' . $item[2] . ';border-radius:8px;padding:10px 14px;">';
            echo '<div style="font-size:11px;color:#6b7280;">' . $item[0] . '</div>';
            echo '<div style="font-size:22px;font-weight:700;color:' . $item[3] . ';">' . intval($item[1]) . '</div>';
            echo '</div>';
        }
        echo '</div>';

        // Score bar
        echo '<div style="background:#f3f4f6;border-radius:8px;padding:10px 14px;margin-bottom:10px;">';
        echo '<div style="font-size:11px;color:#6b7280;margin-bottom:4px;">Average SEO Score — ' . $stats->total . ' pages crawled</div>';
        echo '<div style="background:#e5e7eb;border-radius:99px;height:10px;">';
        echo '<div style="background:' . $color . ';width:' . $score . '%;height:10px;border-radius:99px;transition:width .5s;"></div>';
        echo '</div>';
        echo '<div style="font-size:18px;font-weight:700;color:' . $color . ';margin-top:4px;">' . $score . '/100</div>';
        echo '</div>';

        if ($stats->last_crawl) echo '<div style="font-size:11px;color:#9ca3af;">Last crawl: ' . human_time_diff(strtotime($stats->last_crawl)) . ' ago</div>';
        if ($last_ping) echo '<div style="font-size:11px;color:#9ca3af;">Last sitemap ping: ' . human_time_diff(strtotime($last_ping)) . ' ago</div>';
    }

    echo '<a href="' . esc_url($admin_url) . '" style="display:inline-block;margin-top:10px;background:#1a1a2e;color:#fff;padding:7px 16px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">Open Crawler →</a>';
    echo '</div>';
}

// ============================================================
// ADMIN PAGE
// ============================================================
function sgclabs_crawler_page() {
    global $wpdb;

    $total_collected = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_crawl_pages");
    $total_audited   = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_crawl_pages WHERE crawled_at IS NOT NULL");
    $last_ping       = get_option('sgclabs_last_sitemap_ping', 'Never');
    $gsc_connected   = !empty(get_option('sgclabs_gsc_access_token'));

    $stats = $wpdb->get_row("SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status_code=0 OR status_code=404 OR status_code>=500 THEN 1 ELSE 0 END) as broken,
        SUM(CASE WHEN issues != '' THEN 1 ELSE 0 END) as with_issues,
        SUM(CASE WHEN word_count < 100 AND word_count > 0 THEN 1 ELSE 0 END) as thin,
        SUM(CASE WHEN meta_desc='' OR meta_desc IS NULL THEN 1 ELSE 0 END) as no_meta,
        SUM(CASE WHEN h1='' OR h1 IS NULL THEN 1 ELSE 0 END) as no_h1,
        SUM(CASE WHEN img_no_alt > 0 THEN 1 ELSE 0 END) as missing_alt,
        SUM(CASE WHEN load_time > 3 THEN 1 ELSE 0 END) as slow,
        ROUND(AVG(seo_score)) as avg_score,
        MAX(crawled_at) as last_crawl
    FROM {$wpdb->prefix}sgclabs_crawl_pages WHERE crawled_at IS NOT NULL");

    $avg  = $stats ? (int)$stats->avg_score : 0;
    $sc   = $avg >= 80 ? '#16a34a' : ($avg >= 60 ? '#d97706' : '#dc2626');
    $nonce = wp_create_nonce('sgclabs_nonce');
    ?>
<div class="wrap sgclabs-wrap" style="font-family:Inter,sans-serif;max-width:1200px;">
<?php sgclabs_module_control_bar(["module_id"=>"site-crawler","module_name"=>"Site Crawler","module_icon"=>"🕷️","option_key"=>"sgclabs_site_crawler_active","default"=>"1","description"=>"Crawl & audit all pages for SEO issues","modes"=>[["value"=>"full","label"=>"Full Crawl","desc"=>"Crawl all pages including images"],["value"=>"quick","label"=>"Quick Scan","desc"=>"Text content only, faster"]],"mode_key"=>"sgclabs_crawler_mode","mode_default"=>"full"]); ?>

<h1 style="font-size:22px;font-weight:800;color:#1a1a2e;margin-bottom:4px;">🕷️ SGC Site Crawler &amp; SEO Audit</h1>
<p style="color:#6b7280;margin-bottom:20px;">Site crawl · GSC errors · Sitemap submit · Full SEO audit — sab ek jagah</p>

<!-- ====== 4 ACTION CARDS ====== -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;margin-bottom:24px;">

    <!-- Card 1: Site Crawler -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;border-top:4px solid #2563eb;">
        <div style="font-size:28px;margin-bottom:8px;">🕷️</div>
        <h3 style="font-size:15px;font-weight:700;color:#1a1a2e;margin:0 0 6px;">Site Crawler</h3>
        <p style="font-size:12px;color:#6b7280;margin:0 0 14px;">Saare pages collect karke SEO audit karo — broken links, thin content, missing meta, slow pages</p>
        <div style="font-size:12px;color:#374151;margin-bottom:12px;">
            <?php if ($total_audited > 0): ?>
            ✅ Last crawl: <strong><?php echo intval($total_audited); ?></strong> pages | Score: <strong style="color:<?php echo $sc; ?>"><?php echo $avg; ?>/100</strong><br>
            🕐 <?php echo $stats && $stats->last_crawl ? human_time_diff(strtotime($stats->last_crawl)) . ' ago' : 'Never'; ?>
            <?php else: ?>
            No crawl done yet
            <?php endif; ?>
        </div>
        <div id="sgc-pnav-crawler" style="margin-bottom:12px;"></div>
        <div id="sgc-crawl-progress" style="display:none;margin-top:10px;">
            <div style="background:#e5e7eb;border-radius:99px;height:8px;overflow:hidden;">
                <div id="sgc-crawl-bar" style="background:#2563eb;height:8px;width:0%;transition:width .4s;border-radius:99px;"></div>
            </div>
            <div id="sgc-crawl-status" style="font-size:12px;color:#374151;margin-top:5px;"></div>
        </div>
    </div>

    <!-- Card 2: GSC Crawl Errors -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;border-top:4px solid #dc2626;">
        <div style="font-size:28px;margin-bottom:8px;">🔍</div>
        <h3 style="font-size:15px;font-weight:700;color:#1a1a2e;margin:0 0 6px;">GSC Crawl Errors</h3>
        <p style="font-size:12px;color:#6b7280;margin:0 0 14px;">Google Search Console se crawl errors, low CTR pages, zero impression URLs dekho</p>
        <div style="font-size:12px;margin-bottom:12px;">
            <?php if ($gsc_connected): ?>
            <span style="color:#16a34a;">✅ GSC Connected</span>
            <?php else: ?>
            <span style="color:#dc2626;">❌ GSC Not Connected</span> — <a href="<?php echo admin_url('admin.php?page=sgclabs-gsc-connect'); ?>">Connect →</a>
            <?php endif; ?>
        </div>
        <button id="sgc-gsc-errors-btn" class="button" style="width:100%;background:#dc2626;border-color:#dc2626;color:#fff;">
            🔍 Fetch GSC Errors
        </button>
        <div id="sgc-gsc-result" style="margin-top:10px;font-size:12px;"></div>
    </div>

    <!-- Card 3: Sitemap Submit -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;border-top:4px solid #16a34a;">
        <div style="font-size:28px;margin-bottom:8px;">🗺️</div>
        <h3 style="font-size:15px;font-weight:700;color:#1a1a2e;margin:0 0 6px;">Sitemap Submit</h3>
        <p style="font-size:12px;color:#6b7280;margin:0 0 14px;">Google &amp; Bing ko sitemap ping karo — fast indexing trigger karo</p>
        <div style="font-size:12px;color:#374151;margin-bottom:12px;">
            Last ping: <strong><?php echo $last_ping !== 'Never' ? human_time_diff(strtotime($last_ping)) . ' ago' : 'Never'; ?></strong>
        </div>
        <div style="margin-bottom:10px;">
            <label style="font-size:11px;color:#6b7280;">IndexNow Key (optional)</label>
            <input type="text" id="sgc-indexnow-key" value="<?php echo esc_attr(get_option('sgclabs_indexnow_key','')); ?>"
                   style="width:100%;margin-top:4px;padding:6px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;" placeholder="Your IndexNow key">
        </div>
        <button id="sgc-sitemap-submit" class="button" style="width:100%;background:#16a34a;border-color:#16a34a;color:#fff;">
            🗺️ Submit to Google &amp; Bing
        </button>
        <div id="sgc-sitemap-result" style="margin-top:10px;font-size:12px;"></div>
    </div>

    <!-- Card 4: Quick Stats -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;border-top:4px solid #d97706;">
        <div style="font-size:28px;margin-bottom:8px;">📊</div>
        <h3 style="font-size:15px;font-weight:700;color:#1a1a2e;margin:0 0 10px;">Site Health Score</h3>
        <?php if ($stats && $stats->total > 0): ?>
        <div style="font-size:36px;font-weight:800;color:<?php echo $sc; ?>;line-height:1;"><?php echo $avg; ?>/100</div>
        <div style="background:#e5e7eb;border-radius:99px;height:8px;margin:8px 0 12px;overflow:hidden;">
            <div style="background:<?php echo $sc; ?>;width:<?php echo $avg; ?>%;height:8px;border-radius:99px;"></div>
        </div>
        <div style="font-size:11px;color:#6b7280;line-height:1.8;">
            🔴 Broken: <strong><?php echo intval($stats->broken); ?></strong><br>
            ⚠️ Issues: <strong><?php echo intval($stats->with_issues); ?></strong><br>
            📄 Thin: <strong><?php echo intval($stats->thin); ?></strong><br>
            🏷️ No Meta: <strong><?php echo intval($stats->no_meta); ?></strong><br>
            🔤 No H1: <strong><?php echo intval($stats->no_h1); ?></strong><br>
            🖼️ Alt Missing: <strong><?php echo intval($stats->missing_alt); ?></strong><br>
            🐌 Slow (&gt;3s): <strong><?php echo intval($stats->slow); ?></strong>
        </div>
        <?php else: ?>
        <p style="color:#9ca3af;font-size:13px;">Crawl run karo score dekhne ke liye</p>
        <?php endif; ?>
    </div>
</div>

<!-- ====== EXPORT + FILTER BAR ====== -->
<?php if ($total_audited > 0): ?>
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <strong style="font-size:13px;color:#374151;">Filter:</strong>
    <?php
    $filters = [
        'all'      => ['All Pages', '#6b7280'],
        'issues'   => ['Has Issues', '#dc2626'],
        'broken'   => ['Broken (4xx/5xx)', '#dc2626'],
        'thin'     => ['Thin Content', '#2563eb'],
        'no_meta'  => ['Missing Meta', '#d97706'],
        'no_h1'    => ['Missing H1', '#7c3aed'],
        'slow'     => ['Slow (&gt;3s)', '#d97706'],
        'products' => ['Products Only', '#16a34a'],
    ];
    foreach ($filters as $key => $label): ?>
    <button onclick="sgcLoadResults('<?php echo $key; ?>',1)"
            style="padding:5px 12px;border-radius:99px;border:1.5px solid <?php echo $label[1]; ?>;
                   color:<?php echo $label[1]; ?>;background:#fff;cursor:pointer;font-size:12px;font-weight:600;"
            class="sgc-filter-btn" data-filter="<?php echo $key; ?>">
        <?php echo $label[0]; ?>
    </button>
    <?php endforeach; ?>
    <div style="margin-left:auto;">
        <a href="<?php echo admin_url('admin-ajax.php?action=sgclabs_crawler_export&nonce=' . $nonce); ?>"
           style="background:#1a1a2e;color:#fff;padding:7px 14px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">
            📥 Export CSV
        </a>
    </div>
</div>

<!-- ====== RESULTS TABLE ====== -->
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
    <div id="sgc-crawler-table-wrap">
        <div style="padding:30px;text-align:center;color:#6b7280;">Loading results…</div>
    </div>
    <div id="sgc-crawler-pagination" style="padding:12px 20px;border-top:1px solid #f3f4f6;display:flex;gap:8px;flex-wrap:wrap;align-items:center;"></div>
</div>
<?php endif; ?>

<!-- ====== GSC ERRORS TABLE ====== -->
<div id="sgc-gsc-table" style="display:none;background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;margin-top:16px;"></div>

</div><!-- .wrap -->

<script>
(function($){
'use strict';
var nonce = '<?php echo esc_js( $nonce ); ?>';
var crawlRunning = false, crawlStop = false;
var currentFilter = 'all', currentPage = 1;

// ---- SITE CRAWLER ----
$('#sgc-crawl-start').on('click', function() {
    if (crawlRunning) { crawlStop = true; $(this).text('⏹ Stopping…'); return; }
    crawlRunning = true; crawlStop = false;
    $(this).text('⏹ Stop Crawl').css({background:'#dc2626','border-color':'#dc2626'});
    $('#sgc-crawl-progress').show();
    sgcCrawlCollect();
});

function sgcCrawlCollect() {
    $('#sgc-crawl-status').text('🔍 Collecting all page URLs…');
    $.post(ajaxurl, {action:'sgclabs_crawler_collect', nonce:nonce}, function(res) {
        if (!res.success) { sgcCrawlDone('Error: ' + res.data); return; }
        $('#sgc-crawl-status').text('✅ ' + res.data.total + ' URLs found — starting audit…');
        setTimeout(() => sgcCrawlBatch(0, res.data.total), 500);
    });
}

function sgcCrawlBatch(offset, total) {
    if (crawlStop) { sgcCrawlDone('Stopped'); return; }
    $.post(ajaxurl, {action:'sgclabs_crawler_audit_batch', nonce:nonce, offset:offset}, function(res) {
        if (!res.success) { sgcCrawlDone('Error'); return; }
        var d = res.data;
        var pct = d.percent;
        $('#sgc-crawl-bar').css('width', pct + '%');
        $('#sgc-crawl-status').text('⚡ ' + d.audited + ' / ' + d.total + ' pages audited (' + pct + '%)');
        if (d.finished) {
            sgcCrawlDone('✅ Crawl complete! ' + d.audited + ' pages audited.');
            sgcLoadResults('all', 1);
            location.reload(); // refresh score cards
        } else {
            setTimeout(() => sgcCrawlBatch(d.next_offset, d.total), 800);
        }
    }).fail(() => sgcCrawlDone('AJAX failed'));
}

function sgcCrawlDone(msg) {
    crawlRunning = false;
    $('#sgc-crawl-start').text('🕷️ Start Full Crawl').css({background:'#2563eb','border-color':'#2563eb'});
    $('#sgc-crawl-status').text(msg);
}

// ---- RESULTS TABLE ----
function sgcLoadResults(filter, page) {
    currentFilter = filter; currentPage = page;
    $('#sgc-crawler-table-wrap').html('<div style="padding:30px;text-align:center;color:#6b7280;">Loading…</div>');
    $.post(ajaxurl, {action:'sgclabs_crawler_results', nonce:nonce, filter:filter, page:page}, function(res) {
        if (!res.success) return;
        var d = res.data, rows = d.rows, stats = d.stats;
        if (!rows || rows.length === 0) {
            $('#sgc-crawler-table-wrap').html('<div style="padding:30px;text-align:center;color:#9ca3af;">No pages found for this filter.</div>');
            return;
        }
        var scoreColor = s => s >= 80 ? '#16a34a' : s >= 60 ? '#d97706' : '#dc2626';
        var statusBadge = code => {
            if (code === 200) return '<span style="background:#f0fdf4;color:#16a34a;padding:2px 7px;border-radius:99px;font-size:11px;font-weight:600;">200 OK</span>';
            if (code === 0)   return '<span style="background:#fef2f2;color:#dc2626;padding:2px 7px;border-radius:99px;font-size:11px;font-weight:600;">Failed</span>';
            if (code === 404) return '<span style="background:#fef2f2;color:#dc2626;padding:2px 7px;border-radius:99px;font-size:11px;font-weight:600;">404</span>';
            if (code >= 500)  return '<span style="background:#fef2f2;color:#dc2626;padding:2px 7px;border-radius:99px;font-size:11px;font-weight:600;">' + code + '</span>';
            if (code === 301 || code === 302) return '<span style="background:#fffbeb;color:#d97706;padding:2px 7px;border-radius:99px;font-size:11px;font-weight:600;">' + code + '</span>';
            return '<span style="background:#f3f4f6;color:#374151;padding:2px 7px;border-radius:99px;font-size:11px;font-weight:600;">' + code + '</span>';
        };
        var html = '<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;font-size:12px;">';
        html += '<thead><tr style="background:#f8fafc;border-bottom:2px solid #e5e7eb;">';
        ['URL', 'Title / H1', 'Status', 'Words', 'Score', 'Issues', 'Load'].forEach(h => {
            html += '<th style="padding:10px 12px;text-align:left;font-weight:600;color:#374151;white-space:nowrap;">' + h + '</th>';
        });
        html += '</tr></thead><tbody>';
        rows.forEach((r, i) => {
            var sc = parseInt(r.seo_score);
            var bg = i % 2 === 0 ? '#fff' : '#f9fafb';
            var urlShort = r.url.replace(/^https?:\/\/[^\/]+/, '').substring(0, 50) || '/';
            var issues = r.issues ? r.issues.split(' | ').map(x => '<span style="background:#fef2f2;color:#dc2626;padding:1px 6px;border-radius:4px;font-size:10px;margin:1px;display:inline-block;">' + x + '</span>').join('') : '<span style="color:#16a34a;">✅ OK</span>';
            html += '<tr style="background:' + bg + ';border-bottom:1px solid #f3f4f6;">';
            html += '<td style="padding:9px 12px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><a href="' + r.url + '" target="_blank" style="color:#2563eb;text-decoration:none;" title="' + r.url + '">' + urlShort + '</a></td>';
            html += '<td style="padding:9px 12px;max-width:200px;"><div style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="' + (r.page_title||'') + '">' + (r.page_title ? r.page_title.substring(0,45) : '<span style="color:#dc2626;">No Title</span>') + '</div><div style="color:#9ca3af;font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + (r.h1 ? r.h1.substring(0,40) : '<span style="color:#d97706;">No H1</span>') + '</div></td>';
            html += '<td style="padding:9px 12px;">' + statusBadge(parseInt(r.status_code)) + '</td>';
            html += '<td style="padding:9px 12px;text-align:center;">' + (parseInt(r.word_count) < 100 && r.word_count > 0 ? '<span style="color:#dc2626;font-weight:600;">' + r.word_count + '</span>' : r.word_count) + '</td>';
            html += '<td style="padding:9px 12px;text-align:center;"><span style="font-size:15px;font-weight:700;color:' + scoreColor(sc) + ';">' + sc + '</span></td>';
            html += '<td style="padding:9px 12px;max-width:250px;">' + issues + '</td>';
            html += '<td style="padding:9px 12px;white-space:nowrap;">' + (parseFloat(r.load_time) > 3 ? '<span style="color:#dc2626;font-weight:600;">' + r.load_time + 's</span>' : r.load_time + 's') + '</td>';
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        $('#sgc-crawler-table-wrap').html(html);

        // Pagination
        var pHtml = '<span style="font-size:12px;color:#6b7280;margin-right:8px;">Page ' + page + ' of ' + d.pages + ' (' + d.total + ' pages)</span>';
        if (page > 1) pHtml += '<button onclick="sgcLoadResults(\'' + filter + '\',' + (page-1) + ')" style="padding:4px 10px;border:1px solid #d1d5db;border-radius:6px;cursor:pointer;font-size:12px;">← Prev</button>';
        for (var p = Math.max(1, page-2); p <= Math.min(d.pages, page+2); p++) {
            var active = p === page ? 'background:#1a1a2e;color:#fff;' : '';
            pHtml += '<button onclick="sgcLoadResults(\'' + filter + '\',' + p + ')" style="padding:4px 10px;border:1px solid #d1d5db;border-radius:6px;cursor:pointer;font-size:12px;margin:0 2px;' + active + '">' + p + '</button>';
        }
        if (page < d.pages) pHtml += '<button onclick="sgcLoadResults(\'' + filter + '\',' + (page+1) + ')" style="padding:4px 10px;border:1px solid #d1d5db;border-radius:6px;cursor:pointer;font-size:12px;">Next →</button>';
        $('#sgc-crawler-pagination').html(pHtml);
    });
}

// ---- GSC ERRORS ----
$('#sgc-gsc-errors-btn').on('click', function() {
    $(this).text('Loading…').prop('disabled', true);
    $.post(ajaxurl, {action:'sgclabs_gsc_crawl_errors', nonce:nonce}, function(res) {
        $('#sgc-gsc-errors-btn').text('🔍 Fetch GSC Errors').prop('disabled', false);
        if (!res.success) return;
        var d = res.data;
        var html = '<div style="padding:16px 20px;border-bottom:1px solid #f3f4f6;">';
        html += '<strong style="font-size:14px;">GSC Crawl Issues</strong> ';
        html += '<span style="font-size:12px;color:#6b7280;">— ' + d.broken_count + ' broken pages · ' + d.gsc_count + ' low-CTR GSC URLs</span></div>';

        if (d.broken_pages && d.broken_pages.length) {
            html += '<div style="padding:12px 20px;background:#fef2f2;border-bottom:1px solid #fecaca;font-size:12px;font-weight:600;color:#dc2626;">🔴 Broken Pages (' + d.broken_count + ')</div>';
            html += '<table style="width:100%;border-collapse:collapse;font-size:12px;"><thead><tr style="background:#f8fafc;"><th style="padding:8px 14px;text-align:left;">URL</th><th>Status</th><th>Issues</th></tr></thead><tbody>';
            d.broken_pages.forEach(r => {
                html += '<tr style="border-bottom:1px solid #f3f4f6;"><td style="padding:8px 14px;"><a href="' + r.url + '" target="_blank" style="color:#2563eb;">' + r.url.replace(/^https?:\/\/[^\/]+/,'').substring(0,60) + '</a></td>';
                html += '<td style="padding:8px 14px;text-align:center;"><span style="background:#fef2f2;color:#dc2626;padding:2px 7px;border-radius:99px;font-size:11px;font-weight:600;">' + (r.status_code || 'Failed') + '</span></td>';
                html += '<td style="padding:8px 14px;font-size:11px;color:#6b7280;">' + (r.issues || '—') + '</td></tr>';
            });
            html += '</tbody></table>';
        }

        if (d.gsc_count > 0 && d.low_ctr_pages) {
            html += '<div style="padding:12px 20px;background:#fffbeb;border-top:1px solid #fde68a;border-bottom:1px solid #fde68a;font-size:12px;font-weight:600;color:#d97706;">⚠️ Low CTR / Zero Impression GSC URLs (' + d.gsc_count + ')</div>';
            html += '<table style="width:100%;border-collapse:collapse;font-size:12px;"><thead><tr style="background:#f8fafc;"><th style="padding:8px 14px;text-align:left;">Query / Page</th><th>Clicks</th><th>Impressions</th><th>CTR</th></tr></thead><tbody>';
            d.low_ctr_pages.forEach(r => {
                html += '<tr style="border-bottom:1px solid #f3f4f6;"><td style="padding:8px 14px;">' + (r.query || r.page || '—').substring(0,60) + '</td>';
                html += '<td style="padding:8px 14px;text-align:center;">' + (r.clicks||0) + '</td>';
                html += '<td style="padding:8px 14px;text-align:center;">' + (r.impressions||0) + '</td>';
                html += '<td style="padding:8px 14px;text-align:center;">' + (r.ctr ? (r.ctr*100).toFixed(1)+'%' : '0%') + '</td></tr>';
            });
            html += '</tbody></table>';
        }

        if (!d.gsc_connected) html += '<div style="padding:14px 20px;font-size:12px;color:#6b7280;">💡 GSC connect karo zyada detailed errors ke liye → <a href="' + '<?php echo admin_url("admin.php?page=sgclabs-gsc-connect"); ?>">GSC Connect</a></div>';

        $('#sgc-gsc-table').html(html).show();
    });
});

// ---- SITEMAP SUBMIT ----
$('#sgc-sitemap-submit').on('click', function() {
    var key = $('#sgc-indexnow-key').val();
    if (key) {
        $.post(ajaxurl, {action:'wp_ajax_save_indexnow_key', key:key}); // save key
    }
    $(this).text('⏳ Submitting…').prop('disabled', true);
    $.post(ajaxurl, {action:'sgclabs_sitemap_submit', nonce:nonce, indexnow_key:key}, function(res) {
        $('#sgc-sitemap-submit').text('🗺️ Submit to Google & Bing').prop('disabled', false);
        if (!res.success) { $('#sgc-sitemap-result').html('<span style="color:#dc2626;">Error</span>'); return; }
        var html = '';
        res.data.results.forEach(r => {
            var ok = r.success && r.code >= 200 && r.code < 300;
            html += '<div style="display:flex;align-items:center;gap:6px;margin-top:5px;">';
            html += '<span>' + (ok ? '✅' : '❌') + '</span>';
            html += '<strong style="font-size:11px;">' + r.engine + '</strong>';
            html += '<span style="font-size:11px;color:#6b7280;">' + r.sitemap.replace(/^https?:\/\/[^\/]+/,'').substring(0,40) + '</span>';
            html += '<span style="font-size:11px;color:' + (ok ? '#16a34a' : '#dc2626') + ';">' + r.code + '</span>';
            html += '</div>';
        });
        if (res.data.count === 0) html = '<span style="color:#d97706;">⚠️ No valid sitemaps found. Sitemap plugin install karo ya plugin sitemap enable karo.</span>';
        $('#sgc-sitemap-result').html(html);
    }).fail(() => {
        $('#sgc-sitemap-submit').text('🗺️ Submit to Google & Bing').prop('disabled', false);
        $('#sgc-sitemap-result').html('<span style="color:#dc2626;">AJAX failed</span>');
    });
});

// Save IndexNow key
add_action_wp = function(key){ $.post(ajaxurl, {action:'sgclabs_save_indexnow', nonce:nonce, key:key}); };
$('#sgc-indexnow-key').on('blur', function(){ add_action_wp($(this).val()); });

// Init — load results if available
<?php if ($total_audited > 0): ?>
sgcLoadResults('all', 1);
<?php endif; ?>

})(jQuery);
</script>
    <?php
}

// Save IndexNow key
add_action('wp_ajax_sgclabs_save_indexnow', function() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }
    update_option('sgclabs_indexnow_key', sanitize_text_field($_POST['key'] ?? ''));
    wp_send_json_success();
});
