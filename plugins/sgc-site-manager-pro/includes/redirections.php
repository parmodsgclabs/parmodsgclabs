<?php
if (!defined('ABSPATH')) exit;

if (get_option('sgclabs_redirections_active', '1') !== '1') return;

// ============================================================
// SGC LABS® REDIRECTIONS MANAGER v6.6.3
// - 301/302 redirect rules (manual + bulk)
// - Auto 404 catcher → redirect
// - GSC 404 import + bulk fix
// - Hit tracking
// - RankMath redirections CSV import
// ============================================================

// ---- DB version check ----
add_action('init', function() {
    static $done = false;
    if ($done) return; $done = true;
    global $wpdb; $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sgclabs_redirects (
        id         bigint(20) NOT NULL AUTO_INCREMENT,
        source_url varchar(500) NOT NULL,
        target_url varchar(500) NOT NULL,
        type       varchar(10) DEFAULT '301',
        is_regex   tinyint(1) DEFAULT 0,
        hits       bigint(20) DEFAULT 0,
        enabled    tinyint(1) DEFAULT 1,
        note       varchar(255) DEFAULT '',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        last_hit   datetime DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY source_key (source_url(255))
    ) $charset;");
    // Upgrade: add is_regex column to existing table
    $col = $wpdb->get_var("SHOW COLUMNS FROM {$wpdb->prefix}sgclabs_redirects LIKE 'is_regex'");
    if (!$col) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}sgclabs_redirects ADD COLUMN is_regex tinyint(1) DEFAULT 0 AFTER type");
    }
    update_option('sgclabs_redirects_db_ver','1.1');
}, 5);

// ============================================================
// CORE: Execute redirects — runs on every request (very fast)
// ============================================================
add_action('template_redirect', 'sgclabs_execute_redirect', 1);
function sgclabs_execute_redirect() {
    if (is_admin()) return;

    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if (!$request_uri) return;

    // Build the source to match — path only (no domain), strip query string
    $path_only   = strtolower(rtrim(parse_url($request_uri, PHP_URL_PATH) ?? '', '/')) ?: '/';
    $full_path   = strtolower(rtrim($request_uri, '/')) ?: '/'; // with query string

    global $wpdb;

    // Check cache first (transient)
    $cache_key = 'sgclabs_redir_' . md5($path_only);
    $cached    = get_transient($cache_key);

    if ($cached === false) {
        // 1. Exact match first (fast)
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sgclabs_redirects
             WHERE enabled = 1 AND is_regex = 0
             AND (
                 LOWER(TRIM(TRAILING '/' FROM source_url)) = %s
                 OR LOWER(TRIM(TRAILING '/' FROM source_url)) = %s
             )
             LIMIT 1",
            $path_only, $full_path
        ));

        // 2. Regex match fallback
        if (!$row) {
            $regex_rules = $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}sgclabs_redirects WHERE enabled = 1 AND is_regex = 1"
            );
            foreach ($regex_rules as $rule) {
                $pattern = $rule->source_url;
                // Safety: validate pattern before running
                if (@preg_match($pattern, null) === false) continue;
                if (preg_match($pattern, $request_uri, $matches)) {
                    // Support backreferences in target e.g. /new/$1
                    $rule->target_url = preg_replace($pattern, $rule->target_url, $request_uri);
                    $row = $rule;
                    break;
                }
            }
        }

        $cached = $row ? (array)$row : 'none';
        set_transient($cache_key, $cached, 3600);
    }

    if ($cached === 'none' || !is_array($cached)) return;

    $redirect = $cached;

    // Update hit count (async-safe: use raw query)
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}sgclabs_redirects SET hits = hits + 1, last_hit = %s WHERE id = %d",
        current_time('mysql'), $redirect['id']
    ));
    delete_transient($cache_key);

    // Build target URL
    $target = $redirect['target_url'];
    if (!preg_match('/^https?:\/\//', $target)) {
        $target = home_url($target);
    }

    $code = in_array($redirect['type'], ['301','302','307','308']) ? (int)$redirect['type'] : 301;
    wp_redirect(esc_url_raw($target), $code);
    exit;
}

// ============================================================
// AUTO 404 CATCHER — log 404s to separate option
// ============================================================
add_action('wp', 'sgclabs_catch_404s');
function sgclabs_catch_404s() {
    if (!is_404() || is_admin()) return;
    if (!get_option('sgclabs_redirect_catch_404','1')) return;

    $url = strtolower(rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '', '/')) ?: '/';

    // Skip WP internals
    $skip = ['/wp-admin','/wp-login','/wp-json','/feed','/xmlrpc','/.well-known','/favicon'];
    foreach ($skip as $s) { if (strpos((string)$url, $s) === 0) return; }
    if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg|css|js|ico|woff|ttf|map)$/i', $url)) return;

    // Store in 404 log (max 500 entries)
    $log = get_option('sgclabs_404_log', []);
    if (!isset($log[$url])) {
        $log[$url] = ['url' => $url, 'hits' => 0, 'first_seen' => current_time('mysql')];
    }
    $log[$url]['hits']++;
    $log[$url]['last_seen'] = current_time('mysql');

    // Keep only top 500 by hits
    if (count($log) > 500) {
        uasort($log, fn($a,$b) => $b['hits'] - $a['hits']);
        $log = array_slice($log, 0, 500, true);
    }
    update_option('sgclabs_404_log', $log);
}

// ============================================================
// AJAX: Add single redirect
// ============================================================
add_action('wp_ajax_sgclabs_redirect_add', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    global $wpdb;

    $source   = strtolower(trim(sanitize_text_field($_POST['source'] ?? '')));
    $target   = esc_url_raw(sanitize_text_field($_POST['target'] ?? ''));
    $type     = in_array($_POST['type'] ?? '', ['301','302','307']) ? $_POST['type'] : '301';
    $is_regex = ! empty($_POST['is_regex']) ? 1 : 0;
    $note     = sanitize_text_field($_POST['note'] ?? '');

    if (!$source || !$target) wp_send_json_error('Source and target required');

    // For regex rules — skip URL normalization, keep as-is
    if (!$is_regex) {
        if (!preg_match('/^https?:\/\//', $source) && strpos((string)$source, '/') !== 0) $source = '/' . $source;
        if (preg_match('/^https?:\/\//', $source)) {
            $source = strtolower(rtrim(parse_url($source, PHP_URL_PATH) ?? $source, '/')) ?: '/';
        } else {
            $source = rtrim($source, '/') ?: '/';
        }
    }

    $result = $wpdb->replace($wpdb->prefix . 'sgclabs_redirects', [
        'source_url' => $source,
        'target_url' => $target,
        'type'       => $type,
        'is_regex'   => $is_regex,
        'note'       => $note,
        'enabled'    => 1,
    ]);

    if ($result === false) wp_send_json_error('DB error: ' . $wpdb->last_error);
    wp_send_json_success(['id' => $wpdb->insert_id ?: 'updated']);
});

// ============================================================
// AJAX: Delete redirect
// ============================================================
add_action('wp_ajax_sgclabs_redirect_delete', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    global $wpdb;
    $id = intval($_POST['id'] ?? 0);
    $wpdb->delete($wpdb->prefix . 'sgclabs_redirects', ['id' => $id]);
    wp_send_json_success();
});

// ============================================================
// AJAX: Toggle enable/disable
// ============================================================
add_action('wp_ajax_sgclabs_redirect_toggle', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    global $wpdb;
    $id      = intval($_POST['id'] ?? 0);
    $enabled = intval($_POST['enabled'] ?? 1);
    $wpdb->update($wpdb->prefix . 'sgclabs_redirects', ['enabled' => $enabled], ['id' => $id]);
    wp_send_json_success();
});

// ============================================================
// AJAX: Get redirects list (paginated)
// ============================================================
add_action('wp_ajax_sgclabs_redirect_list', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    global $wpdb;

    $page     = max(1, intval($_POST['page'] ?? 1));
    $per_page = 25;
    $offset   = ($page - 1) * $per_page;
    $search   = sanitize_text_field($_POST['search'] ?? '');
    $filter   = sanitize_key($_POST['filter'] ?? 'all');

    $where = 'WHERE 1=1';
    if ($search) $where .= $wpdb->prepare(' AND (source_url LIKE %s OR target_url LIKE %s OR note LIKE %s)',
        '%'.$search.'%', '%'.$search.'%', '%'.$search.'%');
    if ($filter === 'enabled')  $where .= ' AND enabled = 1';
    if ($filter === 'disabled') $where .= ' AND enabled = 0';

    $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_redirects $where");
    $rows  = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sgclabs_redirects $where ORDER BY hits DESC, created_at DESC LIMIT %d OFFSET %d",
        $per_page, $offset
    ));
    wp_send_json_success(['rows' => $rows, 'total' => $total, 'pages' => ceil($total/$per_page)]);
});

// ============================================================
// AJAX: Bulk — add all 404s to redirects → /shop/
// ============================================================
add_action('wp_ajax_sgclabs_redirect_bulk_404', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    global $wpdb;

    $target  = esc_url_raw(sanitize_text_field($_POST['target'] ?? '/shop/')); // FIX: sanitize as URL
    $min_hits = max(1, intval($_POST['min_hits'] ?? 1));
    $log     = get_option('sgclabs_404_log', []);

    $added = 0;
    foreach ($log as $url => $data) {
        if ($data['hits'] < $min_hits) continue;
        $result = $wpdb->replace($wpdb->prefix . 'sgclabs_redirects', [
            'source_url' => $url,
            'target_url' => $target,
            'type'       => '301',
            'note'       => 'Auto: 404 bulk fix',
            'enabled'    => 1,
        ]);
        if ($result !== false) $added++;
    }

    wp_send_json_success(['added' => $added, 'total_404s' => count($log)]);
});

// ============================================================
// AJAX: Clear 404 log
// ============================================================
add_action('wp_ajax_sgclabs_redirect_clear_404_log', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    update_option('sgclabs_404_log', []);
    wp_send_json_success();
});

// ============================================================
// AJAX: Import CSV (RankMath format or simple source,target)
// ============================================================
add_action('wp_ajax_sgclabs_redirect_import_csv', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    global $wpdb;

    if (empty($_FILES['csv_file']['tmp_name'])) wp_send_json_error('No file uploaded');

    $csv_data = file_get_contents($_FILES['csv_file']['tmp_name']);
    if (!$csv_data) wp_send_json_error('Empty file');

    $lines  = explode("\n", $csv_data);
    $added  = 0; $skipped = 0;

    foreach ($lines as $i => $line) {
        $line = trim($line);
        if (!$line || $i === 0) continue; // skip header

        $cols = str_getcsv($line);
        if (count($cols) < 2) { $skipped++; continue; }

        // Support RankMath format: source, target, type, ...
        // Support simple format: source, target
        $source = strtolower(trim($cols[0]));
        $target = esc_url_raw(trim($cols[1])); // FIX: sanitize target URL from CSV
        $type   = isset($cols[2]) && in_array(trim($cols[2]),['301','302','307']) ? trim($cols[2]) : '301';

        if (!$source || !$target) { $skipped++; continue; }

        // Extract path if full URL
        if (preg_match('/^https?:\/\//', $source)) {
            $source = strtolower(rtrim(parse_url($source, PHP_URL_PATH) ?? $source, '/')) ?: '/';
        } else {
            $source = '/' . ltrim(rtrim($source,'/'), '/');
        }

        $result = $wpdb->replace($wpdb->prefix . 'sgclabs_redirects', [
            'source_url' => $source,
            'target_url' => $target,
            'type'       => $type,
            'note'       => 'Imported from CSV',
            'enabled'    => 1,
        ]);
        $result !== false ? $added++ : $skipped++;
    }

    wp_send_json_success(['added' => $added, 'skipped' => $skipped]);
});

// ============================================================
// AJAX: Export CSV
// ============================================================
add_action('wp_ajax_sgclabs_redirect_export', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    global $wpdb;

    $rows = $wpdb->get_results("SELECT source_url, target_url, type, hits, note, enabled, created_at FROM {$wpdb->prefix}sgclabs_redirects ORDER BY hits DESC");

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="sgclabs-redirects-'.date('Y-m-d').'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out, ['Source URL','Target URL','Type','Hits','Note','Enabled','Created At']);
    foreach ($rows as $r) fputcsv($out, [(string)$r->source_url,(string)$r->target_url,(string)$r->type,(int)$r->hits,(string)$r->note,(int)$r->enabled,(string)$r->created_at]);
    fclose($out); exit;
});

// ============================================================
// ADMIN PAGE
// ============================================================
function sgclabs_redirections_page() {
    global $wpdb;

    $nonce       = wp_create_nonce('sgclabs_nonce');
    $total_redir = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_redirects");
    $total_hits  = (int)$wpdb->get_var("SELECT SUM(hits) FROM {$wpdb->prefix}sgclabs_redirects");
    $log_404     = get_option('sgclabs_404_log', []);
    $total_404   = count($log_404);
    $top_404s    = array_slice(array_values(array_filter(array_map(fn($v)=>$v, $log_404), fn($v)=>$v['hits']>=1)), 0, 10);
    usort($top_404s, fn($a,$b) => $b['hits'] - $a['hits']);

    $catch_enabled = get_option('sgclabs_redirect_catch_404','1');

    // Handle catch 404 toggle save
    if (isset($_POST['sgclabs_save_redirect_settings']) && wp_verify_nonce($_POST['_wpnonce']??'','sgclabs_redir_nonce') && current_user_can('manage_options')) {
        update_option('sgclabs_redirect_catch_404', isset($_POST['sgclabs_redirect_catch_404']) ? '1' : '0');
        $catch_enabled = get_option('sgclabs_redirect_catch_404','1');
    }
    ?>
<div class="wrap sgclabs-wrap" style="font-family:Inter,sans-serif;max-width:1200px;">
<?php sgclabs_module_control_bar(["module_id"=>"redirections","module_name"=>"Redirections Manager","module_icon"=>"↩️","option_key"=>"sgclabs_redirections_active","default"=>"1","description"=>"301/302 redirects, regex support, auto 404 catch","extra_toggles"=>[["key"=>"sgclabs_catch_404_active","label"=>"Auto-catch 404 errors","default"=>"1"]]]); ?>

<h1 style="font-size:22px;font-weight:800;color:#1a1a2e;margin-bottom:4px;">↩️ Redirections Manager</h1>
<p style="color:#6b7280;margin-bottom:20px;">301/302 redirects manage karo · 404 errors auto-catch karo · GSC errors fix karo</p>

<!-- STATS ROW -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-bottom:24px;">
    <?php
    $stats_items = [
        ['↩️','Total Redirects', number_format($total_redir), '#2563eb','#eff6ff'],
        ['🖱️','Total Hits',      number_format($total_hits),  '#16a34a','#f0fdf4'],
        ['🔴','404s Caught',     number_format($total_404),   '#dc2626','#fef2f2'],
        ['⚡','Auto-Catch 404',  $catch_enabled==='1' ? 'ON' : 'OFF', $catch_enabled==='1'?'#16a34a':'#dc2626', $catch_enabled==='1'?'#f0fdf4':'#fef2f2'],
    ];
    foreach ($stats_items as $s): ?>
    <div style="background:<?php echo $s[4];?>;border:1px solid #e5e7eb;border-radius:12px;padding:16px 20px;">
        <div style="font-size:22px;margin-bottom:4px;"><?php echo $s[0];?></div>
        <div style="font-size:11px;color:#6b7280;margin-bottom:2px;"><?php echo esc_html($s[1]);?></div>
        <div style="font-size:24px;font-weight:800;color:<?php echo $s[3];?>;"><?php echo esc_html($s[2]);?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- TABS -->
<div style="display:flex;gap:4px;border-bottom:2px solid #e5e7eb;margin-bottom:20px;flex-wrap:wrap;">
    <?php $tabs = ['redirects'=>'↩️ All Redirects','add'=>'➕ Add New','404s'=>'🔴 404 Log','import'=>'📥 Import/Export']; ?>
    <?php foreach ($tabs as $k=>$label): ?>
    <button data-tab="<?php echo esc_attr($k);?>" id="tab-btn-<?php echo esc_attr($k);?>"
        style="padding:9px 18px;border:none;border-bottom:2px solid transparent;background:none;cursor:pointer;font-size:13px;font-weight:600;color:#6b7280;margin-bottom:-2px;border-radius:6px 6px 0 0;">
        <?php echo $label;?>
    </button>
    <?php endforeach; ?>
</div>

<!-- TAB: ALL REDIRECTS -->
<div id="tab-redirects" class="sgc-tab">
    <div style="display:flex;gap:10px;align-items:center;margin-bottom:14px;flex-wrap:wrap;">
        <input type="text" id="sgc-redir-search" placeholder="Search source/target URL…"
               style="padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;min-width:240px;">
        <select id="sgc-redir-filter" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;">
            <option value="all">All</option>
            <option value="enabled">Enabled</option>
            <option value="disabled">Disabled</option>
        </select>
        <button onclick="sgcLoadRedirects(1)" class="button">🔍 Search</button>
        <div style="margin-left:auto;">
            <a href="<?php echo admin_url('admin-ajax.php?action=sgclabs_redirect_export&nonce='.$nonce); ?>"
               style="background:#1a1a2e;color:#fff;padding:7px 14px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">📥 Export CSV</a>
        </div>
    </div>
    <div id="sgc-redir-table-wrap" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;min-height:100px;">
        <div style="padding:30px;text-align:center;color:#9ca3af;">Loading…</div>
    </div>
    <div id="sgc-redir-pagination" style="padding:12px 0;display:flex;gap:6px;flex-wrap:wrap;align-items:center;"></div>
</div>

<!-- TAB: ADD NEW -->
<div id="tab-add" class="sgc-tab" style="display:none;">
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;max-width:600px;">
        <h3 style="margin:0 0 16px;font-size:16px;font-weight:700;">➕ Add New Redirect</h3>

        <!-- Regex Toggle -->
        <label style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:#f0f4ff;border:1px solid #c7d2fe;border-radius:8px;cursor:pointer;margin-bottom:14px;">
            <input type="checkbox" id="sgc-add-regex" onchange="sgcToggleRegexMode(this.checked)" style="width:15px;height:15px;">
            <div>
                <div style="font-size:13px;font-weight:600;color:#1e40af;">🔧 Regex Mode</div>
                <div style="font-size:11px;color:#3730a3;">Use regular expression pattern — e.g. <code>~^/old-cat/(.*)$~i</code> → <code>/new-cat/$1</code></div>
            </div>
        </label>

        <div style="margin-bottom:14px;">
            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;" id="sgc-source-label">Source URL (path) <span style="color:#dc2626;">*</span></label>
            <input type="text" id="sgc-add-source" placeholder="/old-product-url/ or full URL"
                   style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;box-sizing:border-box;">
            <div style="font-size:11px;color:#9ca3af;margin-top:3px;" id="sgc-source-hint">Example: /product/old-beaker/ or https://labsglass.com/old-page/</div>
        </div>

        <div style="margin-bottom:14px;">
            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Target URL (destination) <span style="color:#dc2626;">*</span></label>
            <input type="text" id="sgc-add-target" placeholder="/shop/ or /product-category/beakers/ or full URL"
                   style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;box-sizing:border-box;">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Redirect Type</label>
                <select id="sgc-add-type" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;">
                    <option value="301">301 — Permanent (recommended)</option>
                    <option value="302">302 — Temporary</option>
                    <option value="307">307 — Temporary (keep method)</option>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Note (optional)</label>
                <input type="text" id="sgc-add-note" placeholder="e.g. Old product renamed"
                       style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;box-sizing:border-box;">
            </div>
        </div>

        <button id="sgc-add-btn" onclick="sgcAddRedirect()"
                style="background:#2563eb;color:#fff;border:none;padding:10px 24px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">
            ➕ Add Redirect
        </button>
        <div id="sgc-add-result" style="margin-top:12px;font-size:13px;"></div>
    </div>

    <!-- Quick Bulk: All 404 → Target -->
    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:20px;margin-top:20px;max-width:600px;">
        <h3 style="margin:0 0 6px;font-size:15px;font-weight:700;color:#991b1b;">🔴 Bulk Fix: All 404s → One Destination</h3>
        <p style="font-size:12px;color:#7f1d1d;margin:0 0 14px;">
            <?php echo $total_404; ?> caught 404 URLs hain. Inhe ek saath redirect karo.
            GSC mein 3,601 "Not Found" errors fix karne ka fastest tarika.
        </p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
            <div style="flex:1;min-width:200px;">
                <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Redirect to:</label>
                <input type="text" id="sgc-bulk-target" value="/shop/"
                       style="width:100%;padding:8px 12px;border:1px solid #fca5a5;border-radius:8px;font-size:13px;box-sizing:border-box;">
            </div>
            <div style="min-width:120px;">
                <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Min hits to include:</label>
                <input type="number" id="sgc-bulk-minhits" value="1" min="1"
                       style="width:100%;padding:8px 12px;border:1px solid #fca5a5;border-radius:8px;font-size:13px;">
            </div>
            <button onclick="sgcBulk404()"
                    style="background:#dc2626;color:#fff;border:none;padding:9px 20px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
                ⚡ Bulk Redirect All 404s
            </button>
        </div>
        <div id="sgc-bulk-result" style="margin-top:10px;font-size:13px;"></div>
    </div>
</div>

<!-- TAB: 404 LOG -->
<div id="tab-404s" class="sgc-tab" style="display:none;">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;flex-wrap:wrap;">
        <h3 style="margin:0;font-size:15px;font-weight:700;">🔴 Live 404 Log — <?php echo $total_404; ?> unique URLs</h3>
        <div style="margin-left:auto;display:flex;gap:8px;align-items:center;">
            <form method="post" style="display:inline;">
                <?php wp_nonce_field('sgclabs_redir_nonce'); ?>
                <label style="font-size:12px;display:flex;align-items:center;gap:6px;">
                    <input type="checkbox" name="sgclabs_redirect_catch_404" value="1" <?php checked($catch_enabled,'1'); ?>
                           onchange="this.form.submit()">
                    <span style="font-size:12px;font-weight:600;">Auto-catch new 404s</span>
                </label>
                <input type="hidden" name="sgclabs_save_redirect_settings" value="1">
            </form>
            <button onclick="sgcClear404Log()" style="background:#dc2626;color:#fff;border:none;padding:6px 12px;border-radius:6px;font-size:12px;cursor:pointer;">
                🗑️ Clear Log
            </button>
        </div>
    </div>

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:#fef2f2;border-bottom:2px solid #fecaca;">
                    <th style="padding:10px 14px;text-align:left;font-weight:600;color:#374151;">404 URL</th>
                    <th style="padding:10px 14px;text-align:center;font-weight:600;color:#374151;">Hits</th>
                    <th style="padding:10px 14px;text-align:center;font-weight:600;color:#374151;">First Seen</th>
                    <th style="padding:10px 14px;text-align:center;font-weight:600;color:#374151;">Last Seen</th>
                    <th style="padding:10px 14px;text-align:center;font-weight:600;color:#374151;">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $log_display = array_values($log_404);
            usort($log_display, fn($a,$b) => ($b['hits']??0) - ($a['hits']??0));
            $log_display = array_slice($log_display, 0, 100);
            if (empty($log_display)): ?>
                <tr><td colspan="5" style="padding:30px;text-align:center;color:#9ca3af;">
                    No 404s caught yet<?php if ($catch_enabled!=='1') echo ' — enable auto-catch above'; ?>.
                </td></tr>
            <?php else:
                foreach ($log_display as $i => $item):
                    $bg = $i%2===0?'#fff':'#fafafa';
                    $hits = (int)($item['hits']??0);
                    $hit_color = $hits>=10?'#dc2626':($hits>=3?'#d97706':'#374151');
            ?>
                <tr style="background:<?php echo $bg;?>;border-bottom:1px solid #f3f4f6;">
                    <td style="padding:9px 14px;font-family:monospace;font-size:12px;color:#2563eb;">
                        <?php echo esc_html($item['url']??''); ?>
                    </td>
                    <td style="padding:9px 14px;text-align:center;font-weight:700;color:<?php echo $hit_color;?>;">
                        <?php echo $hits; ?>
                    </td>
                    <td style="padding:9px 14px;text-align:center;font-size:11px;color:#9ca3af;">
                        <?php echo esc_html(substr($item['first_seen']??'',0,10)); ?>
                    </td>
                    <td style="padding:9px 14px;text-align:center;font-size:11px;color:#9ca3af;">
                        <?php echo esc_html(substr($item['last_seen']??'',0,10)); ?>
                    </td>
                    <td style="padding:9px 14px;text-align:center;">
                        <button onclick="sgcQuickRedirect('<?php echo esc_js($item['url']??''); ?>')"
                                style="background:#2563eb;color:#fff;border:none;padding:4px 10px;border-radius:5px;font-size:11px;cursor:pointer;font-weight:600;">
                            ↩️ Fix
                        </button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php if (count($log_404) > 100): ?>
        <div style="padding:10px 14px;background:#fffbeb;font-size:12px;color:#92400e;">
            ⚠️ <?php echo count($log_404); ?> total 404 URLs — showing top 100 by hits. Use "Bulk Fix" to redirect all.
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- TAB: IMPORT/EXPORT -->
<div id="tab-import" class="sgc-tab" style="display:none;">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:900px;">

        <!-- Import CSV -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;">
            <h3 style="margin:0 0 8px;font-size:15px;font-weight:700;">📥 Import CSV</h3>
            <p style="font-size:12px;color:#6b7280;margin:0 0 14px;">
                RankMath Redirections CSV ya simple format (source,target,type)<br>
                <strong>Column order:</strong> source_url, target_url, type (optional)
            </p>
            <form id="sgc-import-form" onsubmit="return sgcImportCSV(event)">
                <input type="file" id="sgc-import-file" accept=".csv"
                       style="display:block;margin-bottom:12px;font-size:13px;">
                <button type="submit" style="background:#2563eb;color:#fff;border:none;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
                    📥 Import
                </button>
            </form>
            <div id="sgc-import-result" style="margin-top:10px;font-size:13px;"></div>

            <div style="margin-top:16px;padding:12px;background:#f8fafc;border-radius:8px;">
                <div style="font-size:11px;font-weight:600;color:#374151;margin-bottom:6px;">📋 Sample CSV format:</div>
                <code style="font-size:10px;color:#374151;white-space:pre;">/old-url/,/new-url/,301
/product/old-name/,/product/new-name/,301
https://labsglass.com/old/,/shop/,301</code>
            </div>
        </div>

        <!-- Export + RankMath tip -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;">
            <h3 style="margin:0 0 8px;font-size:15px;font-weight:700;">📤 Export & Tools</h3>

            <a href="<?php echo admin_url('admin-ajax.php?action=sgclabs_redirect_export&nonce='.$nonce); ?>"
               style="display:block;background:#1a1a2e;color:#fff;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;text-align:center;margin-bottom:14px;">
                📤 Export All Redirects (CSV)
            </a>

            <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px;margin-bottom:12px;">
                <div style="font-size:12px;font-weight:700;color:#92400e;margin-bottom:4px;">💡 RankMath se migrate kaise karein?</div>
                <div style="font-size:11px;color:#78350f;line-height:1.6;">
                    1. RankMath → Redirections → Export CSV<br>
                    2. Yahan Import karo<br>
                    3. RankMath deactivate karo<br>
                    4. SGC Redirections automatically kaam karega
                </div>
            </div>

            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px;">
                <div style="font-size:12px;font-weight:700;color:#166534;margin-bottom:4px;">⚡ GSC 404s Fix — Fast Way</div>
                <div style="font-size:11px;color:#15803d;line-height:1.6;">
                    1. "404 Log" tab mein top 404s dekho<br>
                    2. "Add New" → Bulk Fix button → <code>/shop/</code><br>
                    3. GSC mein Validate Fix request submit karo<br>
                    4. 1-2 weeks mein 404 errors clear ho jayenge
                </div>
            </div>
        </div>
    </div>
</div>

</div><!-- .wrap -->

<!-- QUICK REDIRECT MODAL -->
<div id="sgc-quick-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:24px;width:480px;max-width:90vw;">
        <h3 style="margin:0 0 14px;font-size:16px;font-weight:700;">↩️ Quick Redirect</h3>
        <div style="margin-bottom:10px;">
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Source (404 URL)</label>
            <input type="text" id="sgc-modal-source" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;box-sizing:border-box;" readonly>
        </div>
        <div style="margin-bottom:14px;">
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Redirect to</label>
            <input type="text" id="sgc-modal-target" value="/shop/" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;box-sizing:border-box;">
        </div>
        <select id="sgc-modal-type" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;margin-bottom:14px;">
            <option value="301">301 Permanent</option>
            <option value="302">302 Temporary</option>
        </select>
        <div style="display:flex;gap:10px;">
            <button onclick="sgcModalSave()" style="flex:1;background:#2563eb;color:#fff;border:none;padding:9px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
                ✅ Save Redirect
            </button>
            <button onclick="document.getElementById('sgc-quick-modal').style.display='none'" style="flex:1;background:#f3f4f6;color:#374151;border:none;padding:9px;border-radius:8px;font-size:13px;cursor:pointer;">
                Cancel
            </button>
        </div>
        <div id="sgc-modal-result" style="margin-top:8px;font-size:12px;"></div>
    </div>
</div>

<div id="sgc-pnav-redirects" style="margin:0 0 12px;display:none;" class="sgc-pnav-404-section"></div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ── Redirections 404 Bulk Fix Progress Nav ──────────────────
    if (typeof SgcProgressNav === 'undefined') return;
    var redirNav = new SgcProgressNav('sgc-pnav-redirects', {
        label: 'Bulk 404 → Redirect Fix',
        icon: '↩️',
        showSpeed: false,
        counters: [{id:'sgc-rd-fixed', label:'Fixed'}, {id:'sgc-rd-skipped', label:'Skipped'}],
        onPlay:  function(nav) { runBulk404Fix(nav); },
        onStop:  function(nav) { },
        onReset: function(nav) { }
    });

    function runBulk404Fix(nav) {
        nav.status('<span class="sgc-pulse"></span> Processing 404 URLs…');
        const nonce = '<?php echo esc_js( wp_create_nonce( "sgclabs_nonce" ) ); ?>';
        jQuery.post(ajaxurl, { action: 'sgclabs_redirect_bulk_404', nonce: nonce }, function(res) {
            if (!res.success) { nav.error(res.data||'Error'); return; }
            const d = res.data;
            nav.setPercent(100);
            nav.setCounter('sgc-rd-fixed',   d.fixed   || 0);
            nav.setCounter('sgc-rd-skipped', d.skipped || 0);
            nav.done((d.fixed||0) + ' redirects created!');
            if (typeof loadRedirects === 'function') setTimeout(loadRedirects, 800);
        }).fail(function(){ nav.error('Network error.'); });
    }
}); // end DOMContentLoaded

(function($){
var nonce = '<?php echo esc_js( $nonce ); ?>';
var currentPage = 1;

// ---- TABS ---- (delegated binding — safer than inline onclick)
$(document).on('click', '[data-tab]', function(){
    var id = $(this).data('tab');
    window.sgcTab(id);
});
window.sgcTab = function(id) {
    document.querySelectorAll('.sgc-tab').forEach(function(t){ t.style.display='none'; });
    document.querySelectorAll('[id^="tab-btn-"]').forEach(function(b){
        b.style.color='#6b7280'; b.style.borderBottomColor='transparent'; b.style.background='none';
    });
    var el = document.getElementById('tab-'+id);
    if(el) el.style.display='block';
    var btn = document.getElementById('tab-btn-'+id);
    if(btn){ btn.style.color='#1a1a2e'; btn.style.borderBottomColor='#1a1a2e'; btn.style.background='#f8fafc'; }
};

// ---- LOAD REDIRECTS TABLE ----
window.sgcLoadRedirects = function(page) {
    currentPage = page || 1;
    var search = document.getElementById('sgc-redir-search').value;
    var filter = document.getElementById('sgc-redir-filter').value;
    $('#sgc-redir-table-wrap').html('<div style="padding:30px;text-align:center;color:#9ca3af;">Loading…</div>');
    $.post(ajaxurl, {action:'sgclabs_redirect_list', nonce:nonce, page:currentPage, search:search, filter:filter}, function(res) {
        if (!res.success) return;
        var d = res.data, rows = d.rows;
        if (!rows || rows.length === 0) {
            $('#sgc-redir-table-wrap').html('<div style="padding:30px;text-align:center;color:#9ca3af;">No redirects found. "Add New" se create karo.</div>');
            $('#sgc-redir-pagination').html('');
            return;
        }
        var html = '<table style="width:100%;border-collapse:collapse;font-size:12px;">';
        html += '<thead><tr style="background:#f8fafc;border-bottom:2px solid #e5e7eb;">';
        ['Source URL','→ Target URL','Type','Hits','Note','Status','Actions'].forEach(h =>
            html += '<th style="padding:9px 12px;text-align:left;font-weight:600;color:#374151;white-space:nowrap;">'+h+'</th>');
        html += '</tr></thead><tbody>';
        rows.forEach((r, i) => {
            var bg = i%2===0?'#fff':'#f9fafb';
            var en = parseInt(r.enabled);
            var typeBg = r.type==='301'?'#eff6ff':'#f0fdf4';
            var typeC  = r.type==='301'?'#2563eb':'#16a34a';
            html += '<tr style="background:'+bg+';border-bottom:1px solid #f3f4f6;opacity:'+(en?1:.5)+';">';
            html += '<td style="padding:9px 12px;font-family:monospace;font-size:11px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="'+r.source_url+'">'+r.source_url+'</td>';
            html += '<td style="padding:9px 12px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="'+r.target_url+'"><span style="color:#2563eb;font-family:monospace;font-size:11px;">'+r.target_url+'</span></td>';
            html += '<td style="padding:9px 12px;"><span style="background:'+typeBg+';color:'+typeC+';padding:2px 8px;border-radius:99px;font-size:11px;font-weight:700;">'+r.type+'</span></td>';
            html += '<td style="padding:9px 12px;text-align:center;font-weight:600;color:'+(parseInt(r.hits)>0?'#16a34a':'#9ca3af')+';">'+r.hits+'</td>';
            html += '<td style="padding:9px 12px;font-size:11px;color:#6b7280;max-width:120px;overflow:hidden;text-overflow:ellipsis;">'+r.note+'</td>';
            html += '<td style="padding:9px 12px;"><label style="position:relative;display:inline-flex;align-items:center;cursor:pointer;"><input type="checkbox" '+(en?'checked':'')+'onchange="sgcToggle('+r.id+',this.checked?1:0)" style="width:16px;height:16px;accent-color:#2563eb;"><span style="margin-left:4px;font-size:11px;">'+(en?'On':'Off')+'</span></label></td>';
            html += '<td style="padding:9px 12px;"><button onclick="sgcDelete('+r.id+')" style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:3px 8px;border-radius:4px;font-size:11px;cursor:pointer;">🗑️</button></td>';
            html += '</tr>';
        });
        html += '</tbody></table>';
        $('#sgc-redir-table-wrap').html(html);

        var pHtml = '<span style="font-size:12px;color:#6b7280;margin-right:8px;">'+d.total+' redirects · Page '+currentPage+' of '+d.pages+'</span>';
        if (currentPage > 1) pHtml += '<button onclick="sgcLoadRedirects('+(currentPage-1)+')" style="padding:4px 10px;border:1px solid #d1d5db;border-radius:6px;cursor:pointer;font-size:12px;">← Prev</button>';
        for (var p=Math.max(1,currentPage-2); p<=Math.min(d.pages,currentPage+2); p++) {
            var active = p===currentPage?'background:#1a1a2e;color:#fff;':'';
            pHtml += '<button onclick="sgcLoadRedirects('+p+')" style="padding:4px 10px;border:1px solid #d1d5db;border-radius:6px;cursor:pointer;font-size:12px;margin:0 2px;'+active+'">'+p+'</button>';
        }
        if (currentPage < d.pages) pHtml += '<button onclick="sgcLoadRedirects('+(currentPage+1)+')" style="padding:4px 10px;border:1px solid #d1d5db;border-radius:6px;cursor:pointer;font-size:12px;">Next →</button>';
        $('#sgc-redir-pagination').html(pHtml);
    });
};

// ---- ADD REDIRECT ----
window.sgcAddRedirect = function() {
    var source   = document.getElementById('sgc-add-source').value.trim();
    var target   = document.getElementById('sgc-add-target').value.trim();
    var type     = document.getElementById('sgc-add-type').value;
    var note     = document.getElementById('sgc-add-note').value.trim();
    var is_regex = document.getElementById('sgc-add-regex').checked ? 1 : 0;
    if (!source || !target) { document.getElementById('sgc-add-result').innerHTML='<span style="color:#dc2626;">Source aur target dono chahiye!</span>'; return; }
    document.getElementById('sgc-add-btn').textContent = '⏳ Saving…';
    $.post(ajaxurl, {action:'sgclabs_redirect_add', nonce:nonce, source:source, target:target, type:type, note:note, is_regex:is_regex}, function(res) {
        document.getElementById('sgc-add-btn').textContent = '➕ Add Redirect';
        if (res.success) {
            document.getElementById('sgc-add-result').innerHTML = '<span style="color:#16a34a;">✅ Redirect added!</span>';
            document.getElementById('sgc-add-source').value = '';
            document.getElementById('sgc-add-note').value = '';
            document.getElementById('sgc-add-regex').checked = false;
            sgcToggleRegexMode(false);
            sgcLoadRedirects(1);
        } else {
            document.getElementById('sgc-add-result').innerHTML = '<span style="color:#dc2626;">❌ '+res.data+'</span>';
        }
    });
};

// ---- REGEX MODE TOGGLE ----
window.sgcToggleRegexMode = function(enabled) {
    var label = document.getElementById('sgc-source-label');
    var hint  = document.getElementById('sgc-source-hint');
    var src   = document.getElementById('sgc-add-source');
    if (enabled) {
        label.textContent = 'Regex Pattern *';
        hint.innerHTML = 'Example: <code>~^/old-cat/(.*)$~i</code> → Target: <code>/new-cat/$1</code>';
        src.placeholder = '~^/old-category/(.*)$~i';
        src.style.fontFamily = 'monospace';
        src.style.background = '#f0f4ff';
    } else {
        label.innerHTML = 'Source URL (path) <span style="color:#dc2626;">*</span>';
        hint.textContent = 'Example: /product/old-beaker/ or https://labsglass.com/old-page/';
        src.placeholder = '/old-product-url/ or full URL';
        src.style.fontFamily = '';
        src.style.background = '';
    }
};

// ---- DELETE ----
window.sgcDelete = function(id) {
    if (!confirm('Delete this redirect?')) return;
    $.post(ajaxurl, {action:'sgclabs_redirect_delete', nonce:nonce, id:id}, function(res) {
        if (res.success) sgcLoadRedirects(currentPage);
    });
};

// ---- TOGGLE ----
window.sgcToggle = function(id, enabled) {
    $.post(ajaxurl, {action:'sgclabs_redirect_toggle', nonce:nonce, id:id, enabled:enabled});
};

// ---- BULK 404 ----
window.sgcBulk404 = function() {
    var target   = document.getElementById('sgc-bulk-target').value;
    var min_hits = document.getElementById('sgc-bulk-minhits').value;
    if (!target) { document.getElementById('sgc-bulk-result').innerHTML='<span style="color:#dc2626;">Target URL required</span>'; return; }
    if (!confirm('Saare caught 404 URLs ko "'+target+'" pe redirect kar dein?\n\nYe action GSC ke 3,601 Not Found errors fix karega.')) return;
    document.getElementById('sgc-bulk-result').innerHTML = '⏳ Processing…';
    $.post(ajaxurl, {action:'sgclabs_redirect_bulk_404', nonce:nonce, target:target, min_hits:min_hits}, function(res) {
        if (res.success) {
            document.getElementById('sgc-bulk-result').innerHTML =
                '<span style="color:#16a34a;">✅ '+res.data.added+' redirects added from '+res.data.total_404s+' 404 URLs!</span>';
            sgcTab('redirects'); sgcLoadRedirects(1);
        } else {
            document.getElementById('sgc-bulk-result').innerHTML = '<span style="color:#dc2626;">Error</span>';
        }
    });
};

// ---- QUICK REDIRECT (from 404 log row) ----
window.sgcQuickRedirect = function(url) {
    document.getElementById('sgc-modal-source').value = url;
    document.getElementById('sgc-modal-target').value = '/shop/';
    document.getElementById('sgc-modal-result').innerHTML = '';
    document.getElementById('sgc-quick-modal').style.display = 'flex';
};
window.sgcModalSave = function() {
    var source = document.getElementById('sgc-modal-source').value;
    var target = document.getElementById('sgc-modal-target').value;
    var type   = document.getElementById('sgc-modal-type').value;
    $.post(ajaxurl, {action:'sgclabs_redirect_add', nonce:nonce, source:source, target:target, type:type, note:'From 404 log'}, function(res) {
        if (res.success) {
            document.getElementById('sgc-modal-result').innerHTML = '<span style="color:#16a34a;">✅ Saved!</span>';
            setTimeout(() => document.getElementById('sgc-quick-modal').style.display='none', 800);
            sgcLoadRedirects(1);
        }
    });
};

// ---- CLEAR 404 LOG ----
window.sgcClear404Log = function() {
    if (!confirm('404 log clear kar dein? (Redirects delete nahi honge)')) return;
    $.post(ajaxurl, {action:'sgclabs_redirect_clear_404_log', nonce:nonce}, function(res) {
        if (res.success) location.reload();
    });
};

// ---- IMPORT CSV ----
window.sgcImportCSV = function(e) {
    e.preventDefault();
    var file = document.getElementById('sgc-import-file').files[0];
    if (!file) { document.getElementById('sgc-import-result').innerHTML='<span style="color:#dc2626;">File select karo</span>'; return false; }
    var fd = new FormData();
    fd.append('action','sgclabs_redirect_import_csv');
    fd.append('nonce', nonce);
    fd.append('csv_file', file);
    document.getElementById('sgc-import-result').innerHTML='⏳ Importing…';
    $.ajax({ url:ajaxurl, type:'POST', data:fd, processData:false, contentType:false, success:function(res) {
        if (res.success) {
            document.getElementById('sgc-import-result').innerHTML =
                '<span style="color:#16a34a;">✅ '+res.data.added+' imported, '+res.data.skipped+' skipped</span>';
            sgcTab('redirects'); sgcLoadRedirects(1);
        } else {
            document.getElementById('sgc-import-result').innerHTML='<span style="color:#dc2626;">'+res.data+'</span>';
        }
    }});
    return false;
};

// Init
sgcTab('redirects');
sgcLoadRedirects(1);

})(jQuery);
</script>
    <?php
}
