<?php
if (!defined('ABSPATH')) exit;

// ============================================================
// SGC LABS® BROKEN LINKS CHECKER — FIXED v6.6.3
// Batch scanning — no timeout, auto-retry, resume support
// ============================================================

// ── Helper: Suggest a fix based on broken URL pattern ──────────────────────
function sgclabs_suggest_fix( string $url ): string {
    if ( empty( $url ) ) return 'Remove link';
    $parsed = wp_parse_url( $url );
    $host   = $parsed['host'] ?? '';
    $path   = $parsed['path'] ?? '';

    if ( strpos( $host, home_url() ) !== false || empty( $host ) ) {
        // Internal link
        if ( preg_match( '/\.(jpg|jpeg|png|gif|webp|svg)$/i', $path ) ) return 'Re-upload image or remove reference';
        if ( preg_match( '/\.(pdf|doc|docx|xls)$/i', $path ) )         return 'Re-upload file or remove link';
        return 'Add 301 redirect or update link';
    }
    // External link
    if ( empty( $parsed['scheme'] ) || $parsed['scheme'] === 'http' )  return 'Try updating to https://';
    return 'Update or remove external link';
}

// STEP 1: Collect all URLs (fast, no HTTP requests)
add_action('wp_ajax_sgclabs_collect_links', 'sgclabs_collect_links_ajax');
function sgclabs_collect_links_ajax() {
    if (get_option('sgclabs_broken_links_active', '1') !== '1') { wp_send_json_error('Module disabled'); return; }
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }
    global $wpdb;
    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sgclabs_broken_links");

    $urls_to_scan = [];

    // 1. Menu links
    $menus = wp_get_nav_menus();
    foreach ($menus as $menu) {
        $items = wp_get_nav_menu_items($menu->term_id);
        if (!$items) continue;
        foreach ($items as $item) {
            $url = trim($item->url);
            if (!filter_var($url, FILTER_VALIDATE_URL)) continue;
            if (!sgclabs_is_internal_url($url)) continue;
            $urls_to_scan[] = ['url'=>$url,'source'=>'Navigation Menu: '.$menu->name];
        }
    }

    // 2. Posts/pages/products content
    $posts = $wpdb->get_results(
        "SELECT ID FROM {$wpdb->posts}
         WHERE post_type IN ('post','page','product') AND post_status='publish' LIMIT 200"
    );
    foreach ($posts as $post) {
        $content = $wpdb->get_var($wpdb->prepare("SELECT post_content FROM {$wpdb->posts} WHERE ID=%d",$post->ID));
        if (!$content) continue;
        preg_match_all('/href=["\']([^"\'#?]+)["\']/', $content, $m);
        foreach ($m[1] as $url) {
            $url = trim($url);
            if (!filter_var($url, FILTER_VALIDATE_URL)) continue;
            if (!sgclabs_is_internal_url($url)) continue;
            $urls_to_scan[] = ['url'=>$url,'source'=>get_permalink($post->ID)];
        }
    }

    // Deduplicate
    $seen = []; $unique = [];
    foreach ($urls_to_scan as $item) {
        if (!isset($seen[$item['url']])) { $seen[$item['url']]=true; $unique[]=$item; }
    }

    update_option('sgclabs_scan_queue', $unique, false);
    update_option('sgclabs_scan_total', count($unique), false);
    update_option('sgclabs_scan_offset', 0, false);

    wp_send_json_success(['total'=>count($unique)]);
}

function sgclabs_is_internal_url($url) {
    $domain = parse_url(home_url(), PHP_URL_HOST);
    $url_domain = parse_url($url, PHP_URL_HOST);
    if (!$url_domain) return false;
    return str_replace('www.','',$url_domain) === str_replace('www.','',$domain);
}

// STEP 2: Batch scan (10 URLs at a time)
add_action('wp_ajax_sgclabs_scan_batch', 'sgclabs_scan_batch_ajax');
function sgclabs_scan_batch_ajax() {
    if (get_option('sgclabs_broken_links_active', '1') !== '1') { wp_send_json_error('Module disabled'); return; }
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }
    @ini_set('memory_limit','256M'); @set_time_limit(60);

    global $wpdb;
    $queue  = get_option('sgclabs_scan_queue', []);
    $total  = (int)get_option('sgclabs_scan_total', 0);
    $offset = (int)get_option('sgclabs_scan_offset', 0);
    $batch  = 10;

    if (empty($queue) || $offset >= count($queue)) {
        $broken = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_broken_links");
        delete_option('sgclabs_scan_queue');
        wp_send_json_success(['done'=>true,'checked'=>$total,'broken'=>$broken,'percent'=>100,'total'=>$total]);
        return;
    }

    $batch_items = array_slice($queue, $offset, $batch);
    foreach ($batch_items as $item) {
        $code = sgclabs_check_url($item['url']);
        if (in_array($code, [404,410,0])) {
            $wpdb->insert($wpdb->prefix.'sgclabs_broken_links',[
                'url'=>$item['url'],'source_url'=>$item['source'],
                'status_code'=>$code?:404,'last_checked'=>current_time('mysql')
            ],['%s','%s','%d','%s']);
        }
    }

    $new_offset = $offset + $batch;
    update_option('sgclabs_scan_offset', $new_offset, false);
    $is_done = $new_offset >= count($queue);
    $broken  = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_broken_links");
    if ($is_done) { delete_option('sgclabs_scan_queue'); delete_option('sgclabs_scan_offset'); }

    wp_send_json_success([
        'done'   =>$is_done,'checked'=>$new_offset,'broken'=>$broken,
        'percent'=>min(99,round($new_offset/max($total,1)*100)),'total'=>$total
    ]);
}

function sgclabs_check_url($url) {
    $args = ['timeout'=>8,'redirection'=>3,'user-agent'=>'SGCLabs-LinkChecker/2.0','sslverify'=>false];
    $r = wp_remote_head($url, $args);
    if (is_wp_error($r)) { $r = wp_remote_get($url, $args); }
    if (is_wp_error($r)) return 0;
    return (int)wp_remote_retrieve_response_code($r);
}

add_action('wp_ajax_sgclabs_delete_broken','sgclabs_delete_broken_link');
function sgclabs_delete_broken_link() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }
    global $wpdb;
    $wpdb->delete($wpdb->prefix.'sgclabs_broken_links',['id'=>intval($_POST['id'] ?? 0)]);
    wp_send_json_success();
}

add_action('wp_ajax_sgclabs_clear_broken','sgclabs_clear_broken_links');
function sgclabs_clear_broken_links() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }
    global $wpdb;
    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sgclabs_broken_links");
    wp_send_json_success();
}

function sgclabs_broken_links_page() {
    global $wpdb;
    $links  = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sgclabs_broken_links ORDER BY id DESC");
    $nonce  = wp_create_nonce('sgclabs_nonce');
    $total_q= (int)get_option('sgclabs_scan_total',0);
    $offset = (int)get_option('sgclabs_scan_offset',0);
    $can_resume = $total_q > 0 && $offset > 0 && $offset < $total_q;
    ?>
    <div class="wrap sgclabs-wrap">
    <?php sgclabs_module_control_bar([
        'module_id'   => 'broken-links',
        'module_name' => 'Broken Link Scanner',
        'module_icon' => '🔗',
        'option_key'  => 'sgclabs_broken_links_active',
        'default'     => '1',
        'description' => 'Scan all posts & products for broken internal/external links',
        'modes'       => [
            ['value'=>'all',      'label'=>'All Content',    'desc'=>'Scan posts, pages & products'],
            ['value'=>'products', 'label'=>'Products Only',  'desc'=>'Scan WooCommerce products only'],
        ],
        'mode_key'    => 'sgclabs_broken_links_mode',
        'mode_default'=> 'all',
    ]); ?>
        <h1>🔗 Broken Links Checker v2.0</h1>

        <div class="sgclabs-section">
            <p><strong>Batch scanning</strong> — 10 URLs at a time, no timeout, auto-retry support!</p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:15px;">
                <div id="sgc-pnav-brokenlinks"></div>
                <?php if ($can_resume): ?>
                    <button class="button button-large" onclick="resumeScan()" style="background:#27ae60;color:white;border-color:#27ae60;">
                        ▶️ Resume (<?php echo intval($offset); ?>/<?php echo intval($total_q); ?>)
                    </button>
                <?php endif; ?>
                <?php if (!empty($links)): ?>
                    <button class="button" onclick="clearAll()" style="color:#e74c3c;">🗑️ Clear All</button>
                <?php endif; ?>
            </div>
            <div id="scan-progress" style="display:none;">
                <div class="seo-progress-outer"><div id="scan-fill" class="seo-progress-inner" style="width:0%">0%</div></div>
                <p id="scan-status" style="margin-top:8px;font-weight:600;font-size:14px;"></p>
            </div>
        </div>

        <div class="sgclabs-section">
            <h2><?php echo empty($links)?'✅ No Broken Links':'❌ Broken Links: <span style="color:#e74c3c">'.count($links).'</span>'; ?></h2>
            <?php if (empty($links)): ?>
                <div class="sgclabs-ok-box">✅ Koi broken link nahi mila. Scan karke check karo!</div>
            <?php else: ?>
                <div style="background:#fff3e0;border:1px solid #e67e22;border-radius:8px;padding:12px;margin:12px 0;font-size:13px;">
                    ⚠️ <strong>Fix karo:</strong> <a href="<?php echo admin_url('nav-menus.php'); ?>" target="_blank">Appearance → Menus</a> mein broken items delete karo.
                    Ya <a href="<?php echo admin_url('edit-tags.php?taxonomy=product_cat&post_type=product'); ?>" target="_blank">Product Categories</a> mein missing categories banao.
                </div>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th width="38%">Broken URL</th><th width="28%">Found On</th><th width="12%">Status</th><th width="12%">Fix</th><th width="10%">Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($links as $link): ?>
                        <tr id="row-<?php echo intval($link->id); ?>">
                            <td style="word-break:break-all;color:#e74c3c;font-size:12px;"><?php echo esc_html($link->url); ?></td>
                            <td style="font-size:12px;">
                                <?php if (strpos($link->source_url,'Navigation')!==false): ?>
                                    <a href="<?php echo admin_url('nav-menus.php'); ?>" target="_blank">🍔 <?php echo esc_html($link->source_url); ?></a>
                                <?php else: ?>
                                    <a href="<?php echo esc_url($link->source_url); ?>" target="_blank" style="font-size:11px;"><?php echo esc_html(str_replace(home_url(),'',$link->source_url)?:'/'); ?></a>
                                <?php endif; ?>
                            </td>
                            <td><span style="background:#e74c3c;color:white;padding:2px 8px;border-radius:4px;font-size:12px;"><?php echo intval($link->status_code)==0?'Timeout':$link->status_code; ?></span></td>
                            <td style="font-size:11px;color:#666;"><?php echo esc_html(sgclabs_suggest_fix($link->url)); ?></td>
                            <td><button class="button button-small" onclick="deleteBroken(<?php echo intval($link->id); ?>)" style="color:#e74c3c;">🗑️ Remove</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
    if (typeof SgcProgressNav === 'undefined') return;
    const blNonce = '<?php echo esc_js( wp_create_nonce( "sgclabs_nonce" ) ); ?>';
    let blPaused = false, blErrors = 0;

    // ── SgcProgressNav ─────────────────────────────────────────
    const blNav = new SgcProgressNav('sgc-pnav-brokenlinks', {
        label: 'Broken Links Scanner',
        icon: '🔗',
        showSpeed: false,
        counters: [
            {id:'sgc-bl-scanned', label:'Scanned'},
            {id:'sgc-bl-broken',  label:'Broken'},
            {id:'sgc-bl-total',   label:'Total URLs'},
        ],
        onPlay:  function(nav) { blPaused = false; blErrors = 0; startCollect(nav); },
        onPause: function(nav) { blPaused = true; },
        onStop:  function(nav) { blPaused = true; },
        onReset: function(nav) { blPaused = true; }
    });

    // STEP 1: Collect URLs first, then batch scan
    function startCollect(nav) {
        nav.status('<span class="sgc-pulse"></span> Collecting all URLs from site...');
        jQuery.ajax({
            url: ajaxurl, type: 'POST', timeout: 30000,
            data: { action: 'sgclabs_collect_links', nonce: blNonce },
            success: function(res) {
                if (!res.success) { nav.error(res.data || 'Collection failed'); return; }
                nav.status('<span class="sgc-pulse"></span> ' + res.data.total + ' URLs found — scanning...');
                nav.setCounter('sgc-bl-total', res.data.total);
                if (!blPaused) runBatch(nav, 0);
            },
            error: function() { nav.error('Server error. Try again.'); }
        });
    }

    // STEP 2: Batch scan loop
    function runBatch(nav, offset) {
        if (blPaused) return;
        jQuery.ajax({
            url: ajaxurl, type: 'POST', timeout: 45000,
            data: { action: 'sgclabs_scan_batch', nonce: blNonce, offset: offset },
            success: function(res) {
                blErrors = 0;
                if (!res.success) { nav.error(res.data || 'Batch error'); return; }
                const d = res.data;
                const pct = d.total > 0 ? Math.min(99, Math.round(d.checked / d.total * 100)) : 0;
                nav.setPercent(pct);
                nav.setCounter('sgc-bl-scanned', d.checked);
                nav.setCounter('sgc-bl-broken',  d.broken || 0);
                nav.setCounter('sgc-bl-total',    d.total);
                nav.status('<span class="sgc-pulse"></span> ' + d.checked + ' / ' + d.total + ' URLs scanned' + (d.broken > 0 ? ' | ❌ ' + d.broken + ' broken' : ''));
                if (d.done) {
                    nav.done(d.broken + ' broken link(s) found out of ' + d.total + ' URLs.');
                    setTimeout(() => location.reload(), 2500);
                } else if (!blPaused) {
                    setTimeout(() => runBatch(nav, d.next_offset || (offset + 10)), 200);
                }
            },
            error: function() {
                blErrors++;
                if (blErrors <= 5) {
                    nav.status('⚠️ Retry ' + blErrors + '/5...');
                    setTimeout(() => runBatch(nav, offset), blErrors * 2000);
                } else { nav.error('Network error after 5 retries.'); }
            }
        });
    }

    const nonce = blNonce;
    function deleteBroken(id) {
        if (!confirm('Remove this broken link from list?')) return;
        jQuery.post(ajaxurl, {action:'sgclabs_delete_broken',nonce,id}, function(res) {
            if (res.success) document.getElementById('row-'+id)?.remove();
        });
    }
    function clearAll() {
        if (!confirm('Clear all broken links from list?')) return;
        jQuery.post(ajaxurl, {action:'sgclabs_clear_broken',nonce}, function(res) {
            if (res.success) location.reload();
        });
    }
    }); // end DOMContentLoaded
    </script>
    <?php
}