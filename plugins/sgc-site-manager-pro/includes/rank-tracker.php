<?php
if (!defined('ABSPATH')) exit;

if (get_option('sgclabs_rank_tracker_active', '1') !== '1') return;

// ============================================================
// SGC LABS® — Keyword Analytics & Rank Tracker v6.6.3
// 1. Keyword Rank Tracker (position history)
// 2. GSC Dashboard Widget
// 3. Winning/Losing Keywords
// 4. CTR/Position graphs
// 5. Keyword Suggestions from GSC
// 6. Index Status checker
// ============================================================

// ── DB TABLE FOR RANK HISTORY ─────────────────────────────────
add_action('init', 'sgclabs_rank_tracker_tables');
function sgclabs_rank_tracker_tables() {
    if (get_option('sgclabs_rank_tracker_db', '0') === SGCLABS_VERSION) return;
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sgclabs_rank_history (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        keyword varchar(500) NOT NULL,
        position float DEFAULT 0,
        impressions int DEFAULT 0,
        clicks int DEFAULT 0,
        ctr float DEFAULT 0,
        url varchar(500) DEFAULT '',
        recorded_date date NOT NULL,
        PRIMARY KEY (id),
        KEY idx_keyword_date (keyword(191), recorded_date),
        KEY idx_date (recorded_date)
    ) {$charset};");
    update_option('sgclabs_rank_tracker_db', SGCLABS_VERSION);
}

// ── DAILY CRON: SYNC GSC DATA → RANK HISTORY ─────────────────
add_action('sgclabs_daily_rank_sync', 'sgclabs_sync_rank_data');
if (!wp_next_scheduled('sgclabs_daily_rank_sync')) {
    wp_schedule_event(time(), 'daily', 'sgclabs_daily_rank_sync');
}

function sgclabs_sync_rank_data() {
    $token = get_option('sgclabs_gsc_access_token', '');
    $site_url = get_option('sgclabs_gsc_site_url', '');
    if (!$token || !$site_url) return;

    // Fetch last 7 days data from GSC API
    $end = date('Y-m-d', strtotime('-1 day'));
    $start = date('Y-m-d', strtotime('-7 days'));

    $resp = wp_remote_post('https://www.googleapis.com/webmasters/v3/sites/' . urlencode($site_url) . '/searchAnalytics/query', [
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ],
        'body' => wp_json_encode([
            'startDate' => $start,
            'endDate' => $end,
            'dimensions' => ['query', 'date'],
            'rowLimit' => 500,
        ]),
    ]);

    if (is_wp_error($resp)) return;
    $data = json_decode(wp_remote_retrieve_body($resp), true);
    if (empty($data['rows'])) return;

    global $wpdb;
    $table = $wpdb->prefix . 'sgclabs_rank_history';

    foreach ($data['rows'] as $row) {
        $keyword = sanitize_text_field($row['keys'][0]);
        $date = sanitize_text_field($row['keys'][1]);
        $pos = floatval($row['position']);
        $imp = intval($row['impressions']);
        $clk = intval($row['clicks']);
        $ctr = floatval($row['ctr']) * 100;

        // Upsert
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE keyword = %s AND recorded_date = %s LIMIT 1",
            $keyword, $date
        ));
        if ($exists) {
            $wpdb->update($table, ['position' => $pos, 'impressions' => $imp, 'clicks' => $clk, 'ctr' => $ctr],
                ['id' => $exists]);
        } else {
            $wpdb->insert($table, [
                'keyword' => $keyword, 'position' => $pos, 'impressions' => $imp,
                'clicks' => $clk, 'ctr' => $ctr, 'recorded_date' => $date,
            ]);
        }
    }
    update_option('sgclabs_rank_last_sync', current_time('mysql'));
}

// ── AJAX: GET RANK HISTORY FOR CHARTS ─────────────────────────
add_action('wp_ajax_sgclabs_rank_history', function() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }
    global $wpdb;
    $table = $wpdb->prefix . 'sgclabs_rank_history';
    $days = intval($_POST['days'] ?? 30);
    $keyword = sanitize_text_field($_POST['keyword'] ?? '');
    $since = date('Y-m-d', strtotime("-{$days} days"));

    if ($keyword) {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT recorded_date, position, impressions, clicks, ctr FROM {$table} WHERE keyword = %s AND recorded_date >= %s ORDER BY recorded_date ASC",
            $keyword, $since
        ));
    } else {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT recorded_date, AVG(position) as position, SUM(impressions) as impressions, SUM(clicks) as clicks, AVG(ctr) as ctr FROM {$table} WHERE recorded_date >= %s GROUP BY recorded_date ORDER BY recorded_date ASC",
            $since
        ));
    }
    wp_send_json_success($rows);
});

// ── AJAX: GET WINNING / LOSING KEYWORDS ───────────────────────
add_action('wp_ajax_sgclabs_winning_losing', function() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }
    global $wpdb;
    $table = $wpdb->prefix . 'sgclabs_rank_history';

    // Compare last 7 days vs previous 7 days
    $period1_start = date('Y-m-d', strtotime('-14 days'));
    $period1_end = date('Y-m-d', strtotime('-8 days'));
    $period2_start = date('Y-m-d', strtotime('-7 days'));
    $period2_end = date('Y-m-d');

    $sql = "SELECT
        p1.keyword,
        ROUND(p1.avg_pos, 1) as old_pos,
        ROUND(p2.avg_pos, 1) as new_pos,
        ROUND(p1.avg_pos - p2.avg_pos, 1) as change_pos,
        COALESCE(p2.total_clicks, 0) as clicks,
        COALESCE(p2.total_imp, 0) as impressions
    FROM (
        SELECT keyword, AVG(position) as avg_pos FROM {$table}
        WHERE recorded_date BETWEEN %s AND %s GROUP BY keyword
    ) p1
    INNER JOIN (
        SELECT keyword, AVG(position) as avg_pos, SUM(clicks) as total_clicks, SUM(impressions) as total_imp FROM {$table}
        WHERE recorded_date BETWEEN %s AND %s GROUP BY keyword
    ) p2 ON p1.keyword = p2.keyword
    WHERE ABS(p1.avg_pos - p2.avg_pos) > 0.5
    ORDER BY change_pos DESC
    LIMIT 50";

    $results = $wpdb->get_results($wpdb->prepare($sql, $period1_start, $period1_end, $period2_start, $period2_end));

    $winning = [];
    $losing = [];
    foreach ($results as $r) {
        if ($r->change_pos > 0) $winning[] = $r; // positive = improved (lower position number = better)
        else $losing[] = $r;
    }
    wp_send_json_success(['winning' => array_slice($winning, 0, 20), 'losing' => array_slice(array_reverse($losing), 0, 20)]);
});

// ── AJAX: KEYWORD SUGGESTIONS ─────────────────────────────────
add_action('wp_ajax_sgclabs_keyword_suggestions', function() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }
    global $wpdb;
    $table = $wpdb->prefix . 'sgclabs_rank_history';

    // Keywords with high impressions but low CTR = opportunity
    $opportunities = $wpdb->get_results("
        SELECT keyword, AVG(position) as avg_pos, SUM(impressions) as total_imp,
               SUM(clicks) as total_clicks, AVG(ctr) as avg_ctr
        FROM {$table}
        WHERE recorded_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY keyword
        HAVING total_imp > 50 AND avg_ctr < 3 AND avg_pos BETWEEN 4 AND 20
        ORDER BY total_imp DESC
        LIMIT 30
    ");

    // Keywords close to page 1 (position 8-20)
    $almost_page1 = $wpdb->get_results("
        SELECT keyword, AVG(position) as avg_pos, SUM(impressions) as total_imp, SUM(clicks) as total_clicks
        FROM {$table}
        WHERE recorded_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
        GROUP BY keyword
        HAVING avg_pos BETWEEN 8 AND 20
        ORDER BY total_imp DESC
        LIMIT 20
    ");

    wp_send_json_success(['opportunities' => $opportunities, 'almost_page1' => $almost_page1]);
});

// ── AJAX: INDEX STATUS CHECK ──────────────────────────────────
add_action('wp_ajax_sgclabs_check_index_status', function() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }
    $urls = isset($_POST['urls']) ? array_map('esc_url_raw', (array)$_POST['urls']) : [];
    if (empty($urls)) {
        // Default: check last 10 published posts/products
        $recent = get_posts(['numberposts' => 10, 'post_type' => ['post', 'page', 'product'], 'post_status' => 'publish']);
        foreach ($recent as $p) $urls[] = get_permalink($p->ID);
    }
    $results = [];
    foreach ($urls as $url) {
        $check_url = 'https://webcache.googleusercontent.com/search?q=cache:' . urlencode($url);
        $resp = wp_remote_head($check_url, ['timeout' => 10, 'redirection' => 0]);
        $code = is_wp_error($resp) ? 0 : wp_remote_retrieve_response_code($resp);
        $results[] = [
            'url' => $url,
            'indexed' => ($code === 200 || $code === 301 || $code === 302),
            'status' => $code,
        ];
    }
    wp_send_json_success($results);
});

// ── GSC DASHBOARD WIDGET ──────────────────────────────────────
add_action('wp_dashboard_setup', 'sgclabs_gsc_dashboard_widget_setup');
function sgclabs_gsc_dashboard_widget_setup() {
    if (!current_user_can('manage_options')) return;
    wp_add_dashboard_widget(
        'sgclabs_gsc_overview',
        '🔍 SGC LABS® — Search Console Overview',
        'sgclabs_gsc_dashboard_widget_render'
    );
}

function sgclabs_gsc_dashboard_widget_render() {
    global $wpdb;
    $table = $wpdb->prefix . 'sgclabs_rank_history';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");

    if (!$table_exists) {
        echo '<p style="color:#888;">Rank tracking data not available yet. Data syncs daily from GSC.</p>';
        return;
    }

    $last_sync = get_option('sgclabs_rank_last_sync', 'Never');
    $total_kw = $wpdb->get_var("SELECT COUNT(DISTINCT keyword) FROM {$table} WHERE recorded_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $avg_pos = $wpdb->get_var("SELECT ROUND(AVG(position),1) FROM {$table} WHERE recorded_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $total_clicks = $wpdb->get_var("SELECT SUM(clicks) FROM {$table} WHERE recorded_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $total_imp = $wpdb->get_var("SELECT SUM(impressions) FROM {$table} WHERE recorded_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $avg_ctr = $total_imp > 0 ? round($total_clicks / $total_imp * 100, 2) : 0;

    $top5 = $wpdb->get_results("
        SELECT keyword, AVG(position) as pos, SUM(clicks) as clicks
        FROM {$table} WHERE recorded_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY keyword ORDER BY clicks DESC LIMIT 5
    ");
    ?>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:12px;">
        <div style="text-align:center;padding:8px;background:#f0f9ff;border-radius:6px;">
            <div style="font-size:18px;font-weight:bold;color:#1a1a2e;"><?php echo intval($total_kw); ?></div>
            <div style="font-size:10px;color:#888;">Keywords</div>
        </div>
        <div style="text-align:center;padding:8px;background:#f0fdf4;border-radius:6px;">
            <div style="font-size:18px;font-weight:bold;color:#27ae60;"><?php echo number_format(intval($total_clicks)); ?></div>
            <div style="font-size:10px;color:#888;">Clicks (7d)</div>
        </div>
        <div style="text-align:center;padding:8px;background:#fffbeb;border-radius:6px;">
            <div style="font-size:18px;font-weight:bold;color:#e67e22;"><?php echo number_format(intval($total_imp)); ?></div>
            <div style="font-size:10px;color:#888;">Impressions</div>
        </div>
        <div style="text-align:center;padding:8px;background:#fef2f2;border-radius:6px;">
            <div style="font-size:18px;font-weight:bold;color:#e74c3c;"><?php echo $avg_pos ?: '—'; ?></div>
            <div style="font-size:10px;color:#888;">Avg Position</div>
        </div>
    </div>
    <?php if ($top5): ?>
    <table style="width:100%;font-size:12px;border-collapse:collapse;">
        <tr style="background:#f8f9fa;"><th style="text-align:left;padding:4px;">Top Keyword</th><th style="text-align:center;padding:4px;">Pos</th><th style="text-align:center;padding:4px;">Clicks</th></tr>
        <?php foreach ($top5 as $kw): ?>
        <tr><td style="padding:3px 4px;"><?php echo esc_html(wp_trim_words($kw->keyword, 6)); ?></td>
            <td style="text-align:center;padding:3px;"><?php echo round($kw->pos, 1); ?></td>
            <td style="text-align:center;padding:3px;"><?php echo intval($kw->clicks); ?></td></tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
    <p style="font-size:10px;color:#aaa;margin-top:8px;">CTR: <?php echo $avg_ctr; ?>% | Last sync: <?php echo esc_html($last_sync); ?> | <a href="<?php echo admin_url('admin.php?page=sgclabs-seo-suite&tab=rank-tracker'); ?>">Full Report →</a></p>
    <?php
}

// ── ADMIN PAGE: RANK TRACKER ──────────────────────────────────
function sgclabs_rank_tracker_page() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $table = $wpdb->prefix . 'sgclabs_rank_history';
    $keywords = $wpdb->get_col("SELECT DISTINCT keyword FROM {$table} ORDER BY keyword ASC LIMIT 200");
    $last_sync = get_option('sgclabs_rank_last_sync', 'Never');
    ?>
    <div class="wrap" style="max-width:1100px;">
<?php sgclabs_module_control_bar(["module_id"=>"rank-tracker","module_name"=>"Rank Tracker","module_icon"=>"📊","option_key"=>"sgclabs_rank_tracker_active","default"=>"1","description"=>"Track keyword rankings via Google Search Console"]); ?>
        <h1 style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:28px;">📊</span> Keyword Rank Tracker
            <span style="font-size:12px;background:#1a1a2e;color:white;padding:3px 10px;border-radius:12px;">SGC LABS® v6.6.3</span>
        </h1>
        <p style="color:#666;">Track keyword position changes over time. Data syncs daily from Google Search Console.</p>
        <p style="font-size:12px;color:#999;">Last sync: <?php echo esc_html($last_sync); ?>
            <button class="button" onclick="jQuery.post(ajaxurl,{action:'sgclabs_force_rank_sync',nonce:'<?php echo esc_js( wp_create_nonce( "sgclabs_nonce" ) ); ?>'},function(r){alert(r.success?'✅ Synced!':'❌ Error');location.reload();});">🔄 Sync Now</button>
        </p>

        <!-- Chart Area -->
        <div style="background:white;border:1px solid #e0e0e0;border-radius:10px;padding:20px;margin:15px 0;">
            <div style="display:flex;gap:10px;margin-bottom:15px;align-items:center;flex-wrap:wrap;">
                <select id="sgc-rt-keyword" style="min-width:300px;padding:8px;">
                    <option value="">— All Keywords (Average) —</option>
                    <?php foreach ($keywords as $kw): ?>
                    <option value="<?php echo esc_attr($kw); ?>"><?php echo esc_html(wp_trim_words($kw, 8)); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="sgc-rt-days" style="padding:8px;">
                    <option value="7">7 Days</option>
                    <option value="14">14 Days</option>
                    <option value="30" selected>30 Days</option>
                    <option value="90">90 Days</option>
                </select>
                <button class="button button-primary" onclick="sgcLoadChart()">📈 Load Chart</button>
            </div>
            <canvas id="sgc-rank-chart" height="300" style="max-height:300px;"></canvas>
        </div>

        <!-- Winning / Losing -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin:15px 0;">
            <div style="background:white;border:1px solid #e0e0e0;border-radius:10px;padding:20px;">
                <h3 style="color:#27ae60;">📈 Winning Keywords (Position Improved)</h3>
                <div id="sgc-winning" style="font-size:13px;">Loading...</div>
            </div>
            <div style="background:white;border:1px solid #e0e0e0;border-radius:10px;padding:20px;">
                <h3 style="color:#e74c3c;">📉 Losing Keywords (Position Dropped)</h3>
                <div id="sgc-losing" style="font-size:13px;">Loading...</div>
            </div>
        </div>

        <!-- Keyword Suggestions -->
        <div style="background:white;border:1px solid #e0e0e0;border-radius:10px;padding:20px;margin:15px 0;">
            <h3>💡 Keyword Opportunities</h3>
            <p style="font-size:12px;color:#888;">High impressions but low CTR — optimize these for quick wins!</p>
            <div id="sgc-suggestions">Loading...</div>
        </div>

        <!-- Index Status -->
        <div style="background:white;border:1px solid #e0e0e0;border-radius:10px;padding:20px;margin:15px 0;">
            <h3>🔎 Index Status Check</h3>
            <button class="button" onclick="sgcCheckIndex()">Check Last 10 Pages</button>
            <div id="sgc-index-results" style="margin-top:10px;"></div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script>
    const rtNonce = '<?php echo esc_js( wp_create_nonce( "sgclabs_nonce" ) ); ?>';
    let rtChart = null;

    function sgcLoadChart() {
        var kw = document.getElementById('sgc-rt-keyword').value;
        var days = document.getElementById('sgc-rt-days').value;
        jQuery.post(ajaxurl, {action:'sgclabs_rank_history', nonce:rtNonce, keyword:kw, days:days}, function(res) {
            if (!res.success || !res.data.length) return;
            var labels = res.data.map(function(r){ return r.recorded_date; });
            var positions = res.data.map(function(r){ return parseFloat(r.position); });
            var clicks = res.data.map(function(r){ return parseInt(r.clicks); });
            var impressions = res.data.map(function(r){ return parseInt(r.impressions); });
            if (rtChart) rtChart.destroy();
            rtChart = new Chart(document.getElementById('sgc-rank-chart'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {label:'Position', data:positions, borderColor:'#e74c3c', yAxisID:'y', tension:0.3, fill:false},
                        {label:'Clicks', data:clicks, borderColor:'#27ae60', yAxisID:'y1', tension:0.3, fill:false},
                        {label:'Impressions', data:impressions, borderColor:'#3498db', yAxisID:'y1', tension:0.3, fill:false, borderDash:[5,5]}
                    ]
                },
                options: {
                    responsive:true, maintainAspectRatio:false,
                    scales: {
                        y: {reverse:true, title:{display:true, text:'Position'}, position:'left'},
                        y1: {title:{display:true, text:'Clicks / Impressions'}, position:'right', grid:{drawOnChartArea:false}}
                    }
                }
            });
        });
    }

    // Load winning/losing
    jQuery.post(ajaxurl, {action:'sgclabs_winning_losing', nonce:rtNonce}, function(res) {
        if (!res.success) return;
        var whtml = '<table style="width:100%;font-size:12px;"><tr><th>Keyword</th><th>Change</th><th>Clicks</th></tr>';
        (res.data.winning || []).forEach(function(k) {
            whtml += '<tr><td>'+k.keyword.substring(0,40)+'</td><td style="color:#27ae60;">↑'+k.change_pos+'</td><td>'+k.clicks+'</td></tr>';
        });
        whtml += '</table>';
        document.getElementById('sgc-winning').innerHTML = res.data.winning.length ? whtml : '<p style="color:#888;">No data yet</p>';

        var lhtml = '<table style="width:100%;font-size:12px;"><tr><th>Keyword</th><th>Change</th><th>Clicks</th></tr>';
        (res.data.losing || []).forEach(function(k) {
            lhtml += '<tr><td>'+k.keyword.substring(0,40)+'</td><td style="color:#e74c3c;">↓'+Math.abs(k.change_pos)+'</td><td>'+k.clicks+'</td></tr>';
        });
        lhtml += '</table>';
        document.getElementById('sgc-losing').innerHTML = res.data.losing.length ? lhtml : '<p style="color:#888;">No data yet</p>';
    });

    // Load suggestions
    jQuery.post(ajaxurl, {action:'sgclabs_keyword_suggestions', nonce:rtNonce}, function(res) {
        if (!res.success) return;
        var html = '<table style="width:100%;font-size:12px;border-collapse:collapse;"><tr style="background:#f8f9fa;"><th style="text-align:left;padding:6px;">Keyword</th><th>Position</th><th>Impressions</th><th>CTR</th><th>Action</th></tr>';
        (res.data.opportunities || []).forEach(function(k) {
            html += '<tr><td style="padding:4px;">'+k.keyword.substring(0,50)+'</td><td style="text-align:center;">'+parseFloat(k.avg_pos).toFixed(1)+'</td><td style="text-align:center;">'+parseInt(k.total_imp)+'</td><td style="text-align:center;">'+parseFloat(k.avg_ctr).toFixed(1)+'%</td><td style="text-align:center;"><span style="background:#fff3e0;padding:2px 8px;border-radius:4px;font-size:11px;">Optimize ↑</span></td></tr>';
        });
        html += '</table>';
        if (res.data.almost_page1 && res.data.almost_page1.length) {
            html += '<h4 style="margin-top:15px;">🎯 Almost Page 1 (Position 8-20)</h4><div style="display:flex;flex-wrap:wrap;gap:6px;">';
            res.data.almost_page1.forEach(function(k) {
                html += '<span style="background:#e8f5e9;padding:3px 10px;border-radius:12px;font-size:11px;">'+k.keyword.substring(0,30)+' <strong>('+parseFloat(k.avg_pos).toFixed(1)+')</strong></span>';
            });
            html += '</div>';
        }
        document.getElementById('sgc-suggestions').innerHTML = html;
    });

    function sgcCheckIndex() {
        document.getElementById('sgc-index-results').innerHTML = '⏳ Checking...';
        jQuery.post(ajaxurl, {action:'sgclabs_check_index_status', nonce:rtNonce}, function(res) {
            if (!res.success) return;
            var html = '<table style="width:100%;font-size:12px;border-collapse:collapse;margin-top:8px;">';
            res.data.forEach(function(r) {
                var icon = r.indexed ? '✅' : '❌';
                html += '<tr><td style="padding:3px;">'+icon+'</td><td><a href="'+r.url+'" target="_blank">'+r.url.substring(0,70)+'</a></td></tr>';
            });
            html += '</table>';
            document.getElementById('sgc-index-results').innerHTML = html;
        });
    }

    // Auto-load chart on page ready
    setTimeout(sgcLoadChart, 500);
    </script>
    <?php
}

// ── AJAX: MANUAL SYNC ─────────────────────────────────────────
add_action('wp_ajax_sgclabs_force_rank_sync', function() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }
    sgclabs_sync_rank_data();
    wp_send_json_success('Synced');
});
