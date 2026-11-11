<?php
if (!defined('ABSPATH')) exit;

// ============================================================
// SGC LABS® SITE TOOLS v6.6.3
// 3-in-1: Robots.txt Manager + Sitemap Manager + RSS Feed Manager
// Full dedicated admin pages with complete functionality
// ============================================================


// ===========================================================
// ███████╗ ROBOTS.TXT MANAGER
// ===========================================================

// Hook: serve custom robots.txt
add_filter('robots_txt', 'sgclabs_tools_custom_robots', 99, 2);
function sgclabs_tools_custom_robots($output, $public) {
    if (get_option('sgclabs_tools_robots_active', '0') !== '1') return $output;
    $custom = get_option('sgclabs_tools_robots_txt', '');
    if (!$custom) return $output;
    // Always add sitemap reference at end
    $sitemap_line = 'Sitemap: ' . home_url('/sitemap_index.xml');
    if (strpos((string)$custom, 'Sitemap:') === false) {
        $custom .= "\n" . $sitemap_line;
    }
    return $custom;
}

// Also serve via virtual URL fallback
add_action('init', function() {
    add_filter('query_vars', function($v){ $v[] = 'sgclabs_robots_serve'; return $v; });
    // v7.5.1: only hijack /robots.txt when our custom robots is ON and no SEO plugin manages it —
    // the unconditional rule broke WordPress core / Rank Math robots.txt (redirected to /robots.txt/).
    if (get_option('sgclabs_tools_robots_active','0') !== '1') return;
    if (defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION')) return;
    add_rewrite_rule('^robots\.txt$', 'index.php?sgclabs_robots_serve=1', 'top');
}, 1);
add_action('template_redirect', function() {
    if (!get_query_var('sgclabs_robots_serve')) return;
    if (get_option('sgclabs_tools_robots_active','0') !== '1') return;
    if (defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION')) return; // v7.5.1: SEO plugin manages robots.txt — fall through to WP core + robots_txt filter
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Robots-Tag: noindex');
    $txt = get_option('sgclabs_tools_robots_txt', sgclabs_tools_default_robots());
    $sitemap_line = 'Sitemap: ' . home_url('/sitemap_index.xml');
    if (strpos((string)$txt, 'Sitemap:') === false) $txt .= "\n$sitemap_line";
    echo $txt;
    exit;
}, 1);

// AJAX: Save robots.txt
add_action('wp_ajax_sgclabs_save_robots', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }
    $txt    = sanitize_textarea_field(wp_unslash($_POST['robots_txt'] ?? ''));
    $active = sanitize_key($_POST['active'] ?? '0');
    update_option('sgclabs_tools_robots_txt', $txt);
    update_option('sgclabs_tools_robots_active', $active === '1' ? '1' : '0');
    flush_rewrite_rules();
    wp_send_json_success(['url' => home_url('/robots.txt')]);
});

// AJAX: Test robots.txt — fetch and return live content
add_action('wp_ajax_sgclabs_test_robots', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }
    $url  = home_url('/robots.txt');
    $resp = wp_remote_get($url, ['timeout'=>8,'sslverify'=>false]);
    $body = is_wp_error($resp) ? ('Error: '.$resp->get_error_message()) : wp_remote_retrieve_body($resp);
    $code = is_wp_error($resp) ? 0 : wp_remote_retrieve_response_code($resp);
    wp_send_json_success(['url'=>$url,'body'=>$body,'code'=>$code]);
});

function sgclabs_tools_default_robots(): string {
    $home = home_url('/');
    return "User-agent: *
Allow: /

# Block admin & auth
Disallow: /wp-admin/
Disallow: /wp-login.php
Disallow: /wp-register.php
Disallow: /xmlrpc.php

# Block thin/non-SEO pages
Disallow: /?s=
Disallow: /search/
Disallow: /cart/
Disallow: /checkout/
Disallow: /my-account/
Disallow: /order-received/

# Block WooCommerce parameters
Disallow: /*?add-to-cart=
Disallow: /*?orderby=
Disallow: /*?filter_
Disallow: /*?min_price=
Disallow: /*?max_price=

# Block URL parameters (duplicates)
Disallow: /*?v=
Disallow: /*?lang=
Disallow: /*?utm_

# Allow product images
Allow: /wp-content/uploads/

# AI Crawlers -- explicitly allowed
# Googlebot (Search + AI Overview)
User-agent: Googlebot
Allow: /

# Google-Extended (Gemini AI training)
User-agent: Google-Extended
Allow: /

# GPTBot (OpenAI / ChatGPT training)
User-agent: GPTBot
Allow: /

# ChatGPT-User (ChatGPT live browsing)
User-agent: ChatGPT-User
Allow: /

# ClaudeBot (Anthropic / Claude AI)
User-agent: ClaudeBot
Allow: /

# Claude-User (Claude live browsing)
User-agent: Claude-User
Allow: /

# PerplexityBot
User-agent: PerplexityBot
Allow: /

# Sitemap
Sitemap: {$home}sitemap_index.xml";
}

// ===========================================================
// ███████╗ SITEMAP MANAGER (standalone — not in sitemap.php)
// ===========================================================

// AJAX: Generate and get sitemap stats
add_action('wp_ajax_sgclabs_sitemap_stats', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }
    global $wpdb;

    $types = [
        'product'     => ['Products',          'product'],
        'page'        => ['Pages',             'page'],
        'post'        => ['Blog Posts',        'post'],
        'product_cat' => ['Product Categories','taxonomy'],
        'product_tag' => ['Product Tags',      'taxonomy'],
    ];
    $stats = [];
    foreach ($types as $key => $info) {
        if ($info[1] === 'taxonomy') {
            $count = wp_count_terms(['taxonomy'=>$key,'hide_empty'=>true]);
            $count = is_wp_error($count) ? 0 : (int)$count;
        } else {
            $c = wp_count_posts($key);
            $count = (int)($c->publish ?? 0);
        }
        if ($count > 0) {
            $url = $key === 'home' ? home_url('/sitemap_index.xml') : home_url('/'.$key.'-sitemap.xml');
            $stats[] = ['name'=>$info[0],'key'=>$key,'count'=>$count,'url'=>$url,'pages'=>max(1,ceil($count/200))];
        }
    }

    // Check if our sitemap engine active
    $engine_active = get_option('sgclabs_sitemap_active','0') === '1' && !defined('RANK_MATH_VERSION') && !defined('WPSEO_VERSION');

    // Check sitemap accessibility
    $index_url    = home_url('/sitemap_index.xml');
    $resp         = wp_remote_head($index_url,['timeout'=>6,'sslverify'=>false]);
    $index_status = is_wp_error($resp) ? 0 : wp_remote_retrieve_response_code($resp);

    $last_ping = get_option('sgclabs_last_sitemap_ping','');
    wp_send_json_success(['stats'=>$stats,'engine_active'=>$engine_active,'index_url'=>$index_url,'index_status'=>$index_status,'last_ping'=>$last_ping,'rm_active'=>defined('RANK_MATH_VERSION'),'yoast_active'=>defined('WPSEO_VERSION')]);
});

// AJAX: Enable/disable sitemap engine
add_action('wp_ajax_sgclabs_sitemap_toggle', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }
    $val = (isset($_POST['active']) && $_POST['active'] === '1') ? '1' : '0';
    update_option('sgclabs_sitemap_active', $val);
    flush_rewrite_rules();
    wp_send_json_success(['active'=>$val]);
});

// AJAX: Ping Google + Bing (reuse from site-crawler but standalone)
add_action('wp_ajax_sgclabs_sitemap_ping_engines', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }

    $sitemap = home_url('/sitemap_index.xml');
    $engines = [
        'Google' => 'https://www.google.com/ping?sitemap=' . urlencode($sitemap),
        'Bing'   => 'https://www.bing.com/ping?sitemap='   . urlencode($sitemap),
    ];

    $results = [];
    foreach ($engines as $name => $url) {
        $r    = wp_remote_get($url, ['timeout'=>10]);
        $code = is_wp_error($r) ? 0 : wp_remote_retrieve_response_code($r);
        $results[] = ['engine'=>$name,'code'=>$code,'ok'=>($code>=200&&$code<300)];
    }

    // IndexNow
    $ikey = get_option('sgclabs_indexnow_key','');
    if ($ikey) {
        $iurl = 'https://api.indexnow.org/indexnow?url='.urlencode(home_url('/')).'&key='.$ikey;
        $r    = wp_remote_get($iurl,['timeout'=>8]);
        $results[] = ['engine'=>'IndexNow','code'=>is_wp_error($r)?0:wp_remote_retrieve_response_code($r),'ok'=>true];
    }

    update_option('sgclabs_last_sitemap_ping', current_time('mysql'));
    wp_send_json_success(['results'=>$results,'sitemap'=>$sitemap]);
});


// ===========================================================
// ███████╗ RSS FEED MANAGER
// ===========================================================

add_action('wp', 'sgclabs_rss_manager_hooks', 1);
function sgclabs_rss_manager_hooks() {
    $mode = get_option('sgclabs_rss_mode','keep'); // keep | redirect | disable | custom

    if ($mode === 'disable') {
        // Completely disable all feeds
        add_action('do_feed',        'sgclabs_rss_disable_feed', 1);
        add_action('do_feed_rdf',    'sgclabs_rss_disable_feed', 1);
        add_action('do_feed_rss',    'sgclabs_rss_disable_feed', 1);
        add_action('do_feed_rss2',   'sgclabs_rss_disable_feed', 1);
        add_action('do_feed_atom',   'sgclabs_rss_disable_feed', 1);
        add_action('do_feed_rss2_comments','sgclabs_rss_disable_feed', 1);
        add_action('do_feed_atom_comments','sgclabs_rss_disable_feed', 1);
        // Remove head links
        remove_action('wp_head','feed_links',2);
        remove_action('wp_head','feed_links_extra',3);

    } elseif ($mode === 'redirect') {
        $target = get_option('sgclabs_rss_redirect_url', '');
        if ($target) {
            add_action('do_feed',      function() use ($target){ wp_redirect($target,301); exit; }, 1);
            add_action('do_feed_rss',  function() use ($target){ wp_redirect($target,301); exit; }, 1);
            add_action('do_feed_rss2', function() use ($target){ wp_redirect($target,301); exit; }, 1);
            add_action('do_feed_atom', function() use ($target){ wp_redirect($target,301); exit; }, 1);
        }
    } elseif ($mode === 'custom') {
        // Custom RSS — modify feed content
        add_filter('the_excerpt_rss',  'sgclabs_rss_custom_excerpt');
        add_filter('the_content_feed', 'sgclabs_rss_custom_content');
        add_filter('wp_feed_cache_transient_lifetime', function(){ return 3600; });
        // Add custom branding to feed
        add_filter('wp_title_rss', function($title){ return $title . ' — ' . get_bloginfo('name'); });
    }

    // Remove feed links from <head> if setting enabled
    if (get_option('sgclabs_rss_remove_head_links','0') === '1') {
        remove_action('wp_head','feed_links',2);
        remove_action('wp_head','feed_links_extra',3);
    }
}

function sgclabs_rss_disable_feed() {
    $redirect = get_option('sgclabs_rss_disable_redirect', home_url('/'));
    wp_redirect(esc_url_raw($redirect), 301);
    exit;
}

function sgclabs_rss_custom_excerpt($excerpt) {
    $suffix = get_option('sgclabs_rss_custom_suffix','');
    return $excerpt . ($suffix ? ' ' . $suffix : '');
}
function sgclabs_rss_custom_content($content) {
    $suffix = get_option('sgclabs_rss_custom_suffix','');
    return $content . ($suffix ? '<p>'.$suffix.'</p>' : '');
}

// AJAX: Save RSS settings
add_action('wp_ajax_sgclabs_save_rss', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }
    update_option('sgclabs_rss_mode',              sanitize_key($_POST['mode'] ?? 'keep'));
    update_option('sgclabs_rss_redirect_url',      esc_url_raw($_POST['redirect_url'] ?? ''));
    update_option('sgclabs_rss_disable_redirect',  esc_url_raw($_POST['disable_redirect'] ?? home_url('/')));
    update_option('sgclabs_rss_custom_suffix',     sanitize_textarea_field($_POST['custom_suffix'] ?? ''));
    update_option('sgclabs_rss_remove_head_links', (($_POST['remove_head_links'] ?? '0') === '1') ? '1' : '0');
    wp_send_json_success();
});

// AJAX: Test feed URL
add_action('wp_ajax_sgclabs_test_feed', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }
    $url  = esc_url_raw($_POST['url'] ?? get_bloginfo('rss2_url'));
    $resp = wp_remote_get($url,['timeout'=>8,'sslverify'=>false]);
    $code = is_wp_error($resp) ? 0 : wp_remote_retrieve_response_code($resp);
    $body = is_wp_error($resp) ? $resp->get_error_message() : substr(wp_remote_retrieve_body($resp),0,500);
    wp_send_json_success(['code'=>$code,'url'=>$url,'preview'=>$body]);
});


// ===========================================================
// ███████╗ ADMIN PAGE — SITE TOOLS
// ===========================================================

function sgclabs_site_tools_page() {
    $nonce = wp_create_nonce('sgclabs_nonce');

    // Current settings
    $robots_active   = get_option('sgclabs_tools_robots_active','0');
    $robots_txt      = get_option('sgclabs_tools_robots_txt', sgclabs_tools_default_robots());
    $sitemap_active  = get_option('sgclabs_sitemap_active','0');
    $rss_mode        = get_option('sgclabs_rss_mode','keep');
    $rss_redirect    = get_option('sgclabs_rss_redirect_url','');
    $rss_dis_redir   = get_option('sgclabs_rss_disable_redirect', home_url('/'));
    $rss_suffix      = get_option('sgclabs_rss_custom_suffix','');
    $rss_rm_head     = get_option('sgclabs_rss_remove_head_links','0');
    $rm_active       = defined('RANK_MATH_VERSION');
    $yoast_active    = defined('WPSEO_VERSION');
    $indexnow_key    = get_option('sgclabs_indexnow_key','');
    $last_ping       = get_option('sgclabs_last_sitemap_ping','');

    $feed_url  = get_bloginfo('rss2_url');
    $robots_url = home_url('/robots.txt');
    $sitemap_url = home_url('/sitemap_index.xml');
    ?>
<div class="wrap sgclabs-wrap" style="font-family:Inter,sans-serif;max-width:1100px;">

<h1 style="font-size:22px;font-weight:800;color:#1a1a2e;margin-bottom:4px;">🛠️ Site Tools</h1>
<p style="color:#6b7280;margin-bottom:24px;">Robots.txt · XML Sitemap · RSS Feed — sab ek jagah manage karo</p>

<!-- TABS -->
<div style="display:flex;gap:2px;border-bottom:2px solid #e5e7eb;margin-bottom:24px;">
    <?php foreach(['robots'=>'🤖 Robots.txt','sitemap'=>'🗺️ Sitemap','rss'=>'📡 RSS Feed'] as $k=>$label): ?>
    <button onclick="sgcToolTab('<?php echo $k?>')" id="sgtab-<?php echo $k?>"
        style="padding:10px 22px;border:none;border-bottom:3px solid transparent;background:none;cursor:pointer;font-size:13px;font-weight:600;color:#6b7280;margin-bottom:-2px;border-radius:6px 6px 0 0;transition:all .15s;">
        <?php echo $label?>
    </button>
    <?php endforeach; ?>
</div>


<!-- ══════════════════════════════════════════════════════
     TAB 1: ROBOTS.TXT
══════════════════════════════════════════════════════ -->
<div id="sgtab-robots-content" class="sg-tool-tab">
<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;">

    <!-- Left: Editor -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:22px;">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
            <h2 style="margin:0;font-size:16px;font-weight:700;">🤖 Robots.txt Editor</h2>
            <a href="<?php echo esc_url($robots_url)?>" target="_blank"
               style="font-size:11px;color:#2563eb;text-decoration:none;">↗ <?php echo esc_html($robots_url)?></a>
            <div style="margin-left:auto;display:flex;align-items:center;gap:8px;">
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:600;cursor:pointer;">
                    <div id="sgc-robots-toggle" onclick="sgcToggleRobots()" style="width:44px;height:24px;border-radius:99px;background:<?php echo $robots_active==='1'?'#16a34a':'#d1d5db'?>;cursor:pointer;position:relative;transition:background .2s;">
                        <div style="position:absolute;top:2px;left:<?php echo $robots_active==='1'?'20':'2'?>px;width:20px;height:20px;background:#fff;border-radius:50%;transition:left .2s;box-shadow:0 1px 3px rgba(0,0,0,.2);"></div>
                    </div>
                    <span id="sgc-robots-status"><?php echo $robots_active==='1'?'Active':'Inactive'?></span>
                </label>
            </div>
        </div>

        <textarea id="sgc-robots-textarea" rows="22"
            style="width:100%;font-family:'Fira Code',monospace;font-size:12px;border:1px solid #e5e7eb;border-radius:8px;padding:12px;resize:vertical;line-height:1.6;box-sizing:border-box;"><?php echo esc_textarea($robots_txt)?></textarea>

        <div style="display:flex;gap:10px;margin-top:14px;flex-wrap:wrap;">
            <button onclick="sgcSaveRobots()"
                style="background:#1a1a2e;color:#fff;border:none;padding:9px 20px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
                💾 Save Robots.txt
            </button>
            <button onclick="sgcResetRobots()"
                style="background:#f3f4f6;color:#374151;border:1px solid #d1d5db;padding:9px 16px;border-radius:8px;font-size:13px;cursor:pointer;">
                🔄 Reset to Default
            </button>
            <button onclick="sgcTestRobots()"
                style="background:#2563eb;color:#fff;border:none;padding:9px 16px;border-radius:8px;font-size:13px;cursor:pointer;">
                🔍 Test Live
            </button>
        </div>
        <div id="sgc-robots-msg" style="margin-top:10px;font-size:13px;"></div>
    </div>

    <!-- Right: Info + Test result -->
    <div>
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:16px;margin-bottom:14px;">
            <h4 style="margin:0 0 8px;font-size:13px;font-weight:700;color:#166534;">✅ Kya Karta Hai?</h4>
            <ul style="margin:0;padding-left:16px;font-size:12px;color:#166534;line-height:1.8;">
                <li>Googlebot ko batata hai kaunse pages crawl karne hain</li>
                <li>Cart/checkout/admin pages block karta hai</li>
                <li>Duplicate URLs (?v=, ?lang=) block karta hai</li>
                <li>Sitemap URL automatically add hoti hai</li>
            </ul>
        </div>

        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin-bottom:14px;">
            <h4 style="margin:0 0 10px;font-size:13px;font-weight:700;">📋 Quick Rules</h4>
            <?php
            $rules = [
                ['Allow: /product/', 'Sare products allow', '#16a34a'],
                ['Disallow: /cart/', 'Cart block karo', '#dc2626'],
                ['Disallow: /*?*', 'Sare params block', '#dc2626'],
                ['Disallow: /wp-admin/', 'Admin block', '#dc2626'],
                ['Crawl-delay: 1', 'Crawl speed limit', '#2563eb'],
            ];
            foreach ($rules as $r): ?>
            <div onclick="sgcInsertRule('<?php echo esc_js($r[0])?>')"
                 style="display:flex;justify-content:space-between;align-items:center;padding:6px 8px;border-radius:6px;cursor:pointer;margin-bottom:3px;border:1px solid #f3f4f6;"
                 onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                <code style="font-size:11px;color:<?php echo $r[2]?>;"><?php echo esc_html($r[0])?></code>
                <span style="font-size:10px;color:#9ca3af;"><?php echo esc_html($r[1])?> ↑</span>
            </div>
            <?php endforeach; ?>
        </div>

        <div id="sgc-robots-test-result" style="display:none;background:#1a1a2e;border-radius:12px;padding:14px;">
            <div style="font-size:11px;color:#10b981;font-weight:600;margin-bottom:6px;">Live robots.txt ↗</div>
            <pre id="sgc-robots-live" style="color:#e5e7eb;font-size:10px;margin:0;white-space:pre-wrap;max-height:200px;overflow-y:auto;"></pre>
        </div>
    </div>
</div>
</div><!-- tab robots -->


<!-- ══════════════════════════════════════════════════════
     TAB 2: SITEMAP
══════════════════════════════════════════════════════ -->
<div id="sgtab-sitemap-content" class="sg-tool-tab" style="display:none;">
<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;">

    <!-- Left: Sitemap control panel -->
    <div>
        <!-- Engine Status Card -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;margin-bottom:16px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
                <div>
                    <h2 style="margin:0 0 3px;font-size:16px;font-weight:700;">🗺️ SGC Sitemap Engine</h2>
                    <div style="font-size:12px;color:#6b7280;">
                        <?php if ($rm_active): ?>
                        <span style="color:#dc2626;">⚠️ RankMath active — SGC Sitemap tab tak kaam nahi karega</span>
                        <?php elseif ($yoast_active): ?>
                        <span style="color:#dc2626;">⚠️ Yoast SEO active — pehle deactivate karo</span>
                        <?php else: ?>
                        <a href="<?php echo esc_url($sitemap_url)?>" target="_blank" style="color:#2563eb;">↗ <?php echo esc_html($sitemap_url)?></a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!$rm_active && !$yoast_active): ?>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <div id="sgc-sitemap-toggle-el" onclick="sgcToggleSitemap()"
                         style="width:52px;height:28px;border-radius:99px;background:<?php echo $sitemap_active==='1'?'#16a34a':'#d1d5db'?>;cursor:pointer;position:relative;transition:background .2s;">
                        <div id="sgc-sitemap-knob" style="position:absolute;top:3px;left:<?php echo $sitemap_active==='1'?'24':'3'?>px;width:22px;height:22px;background:#fff;border-radius:50%;transition:left .2s;box-shadow:0 1px 3px rgba(0,0,0,.2);"></div>
                    </div>
                    <span id="sgc-sitemap-status-text" style="font-size:13px;font-weight:600;"><?php echo $sitemap_active==='1'?'✅ Active':'❌ Inactive'?></span>
                </label>
                <?php endif; ?>
            </div>

            <!-- Sub-sitemaps grid (loaded via JS) -->
            <div id="sgc-sitemap-stats-wrap">
                <div style="text-align:center;padding:20px;color:#9ca3af;font-size:13px;">Loading sitemap data…</div>
            </div>
        </div>

        <!-- Ping engines -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;margin-bottom:16px;">
            <h3 style="margin:0 0 6px;font-size:15px;font-weight:700;">⚡ Submit to Search Engines</h3>
            <p style="font-size:12px;color:#6b7280;margin:0 0 14px;">Google + Bing ko ping karo — fast indexing trigger karo</p>

            <div style="margin-bottom:12px;">
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">IndexNow Key (optional — Bing/Yandex fast indexing)</label>
                <div style="display:flex;gap:8px;">
                    <input type="text" id="sgc-indexnow-key2" value="<?php echo esc_attr($indexnow_key)?>"
                           placeholder="Generate from Bing Webmaster Tools"
                           style="flex:1;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;">
                    <button onclick="sgcSaveIndexNow()" style="background:#f3f4f6;border:1px solid #d1d5db;padding:8px 14px;border-radius:8px;font-size:12px;cursor:pointer;">Save</button>
                </div>
            </div>

            <button onclick="sgcPingSitemaps()"
                style="background:#16a34a;color:#fff;border:none;padding:10px 22px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
                📡 Submit Sitemap Now
            </button>
            <?php if ($last_ping): ?>
            <div style="font-size:11px;color:#9ca3af;margin-top:8px;">Last ping: <?php echo human_time_diff(strtotime($last_ping))?> ago</div>
            <?php endif; ?>
            <div id="sgc-sitemap-ping-result" style="margin-top:12px;font-size:13px;"></div>
        </div>

        <!-- GSC Submission tip -->
        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:16px;">
            <h4 style="margin:0 0 6px;font-size:13px;font-weight:700;color:#1d4ed8;">📋 GSC mein Sitemap Submit Kaise Karein</h4>
            <ol style="margin:0;padding-left:16px;font-size:12px;color:#1e40af;line-height:1.9;">
                <li>Google Search Console → <strong>Sitemaps</strong> section</li>
                <li>Enter: <code style="background:#fff;padding:1px 6px;border-radius:3px;"><?php echo esc_html($sitemap_url)?></code></li>
                <li>Submit → Google automatically sub-sitemaps discover karega</li>
            </ol>
        </div>
    </div>

    <!-- Right: Sitemap URL list -->
    <div>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;">
            <h4 style="margin:0 0 12px;font-size:13px;font-weight:700;">🔗 All Sitemap URLs</h4>
            <?php
            $sitemap_urls = [
                '/sitemap_index.xml'      => 'Master Index',
                '/product-sitemap.xml'    => 'Products',
                '/page-sitemap.xml'       => 'Pages',
                '/post-sitemap.xml'       => 'Posts',
                '/product_cat-sitemap.xml'=> 'Product Categories',
                '/product_tag-sitemap.xml'=> 'Product Tags',
            ];
            foreach ($sitemap_urls as $path => $label):
                $full = home_url($path);
            ?>
            <div style="padding:7px 0;border-bottom:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <div style="font-size:12px;font-weight:600;color:#374151;"><?php echo esc_html($label)?></div>
                    <div style="font-size:10px;font-family:monospace;color:#6b7280;"><?php echo esc_html($path)?></div>
                </div>
                <a href="<?php echo esc_url($full)?>" target="_blank"
                   style="background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe;padding:3px 8px;border-radius:5px;font-size:10px;text-decoration:none;font-weight:600;">
                    Open ↗
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
</div><!-- tab sitemap -->


<!-- ══════════════════════════════════════════════════════
     TAB 3: RSS FEED
══════════════════════════════════════════════════════ -->
<div id="sgtab-rss-content" class="sg-tool-tab" style="display:none;">
<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;">

    <!-- Left: RSS settings -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:22px;">
        <h2 style="margin:0 0 16px;font-size:16px;font-weight:700;">📡 RSS Feed Manager</h2>

        <!-- Mode selector -->
        <div style="margin-bottom:20px;">
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:8px;">Feed Mode</label>
            <?php
            $modes = [
                'keep'     => ['✅ Keep Active',   'WordPress default RSS feed chalti rahe (recommended)'],
                'custom'   => ['⚙️ Custom Mode',   'Feed content customize karo — branding add karo'],
                'redirect' => ['↩️ Redirect Feed', 'Feed ko kisi aur URL pe redirect karo (e.g. Feedburner)'],
                'disable'  => ['🚫 Disable Feed',  'Sari RSS feeds disable karo — visitors ko homepage pe bhejo'],
            ];
            foreach ($modes as $val => $info): ?>
            <label style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border:2px solid <?php echo $rss_mode===$val?'#2563eb':'#e5e7eb'?>;border-radius:8px;cursor:pointer;margin-bottom:6px;background:<?php echo $rss_mode===$val?'#eff6ff':'#fff'?>;"
                   onclick="sgcSelectRSSMode('<?php echo $val?>')">
                <input type="radio" name="rss_mode" value="<?php echo esc_attr($val)?>" <?php checked($rss_mode,$val)?> style="margin-top:2px;accent-color:#2563eb;">
                <div>
                    <div style="font-size:13px;font-weight:600;color:#1a1a2e;"><?php echo $info[0]?></div>
                    <div style="font-size:11px;color:#6b7280;"><?php echo esc_html($info[1])?></div>
                </div>
            </label>
            <?php endforeach; ?>
        </div>

        <!-- Conditional fields -->
        <div id="rss-field-redirect" style="display:<?php echo $rss_mode==='redirect'?'block':'none'?>;margin-bottom:14px;">
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Redirect URL (e.g. Feedburner URL)</label>
            <input type="url" id="sgc-rss-redirect-url" value="<?php echo esc_attr($rss_redirect)?>"
                   placeholder="https://feeds.feedburner.com/yourblog"
                   style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;box-sizing:border-box;">
        </div>

        <div id="rss-field-disable" style="display:<?php echo $rss_mode==='disable'?'block':'none'?>;margin-bottom:14px;">
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Redirect feed visitors to:</label>
            <input type="url" id="sgc-rss-disable-redirect" value="<?php echo esc_attr($rss_dis_redir)?>"
                   placeholder="<?php echo esc_attr(home_url('/'))?>"
                   style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;box-sizing:border-box;">
        </div>

        <div id="rss-field-custom" style="display:<?php echo $rss_mode==='custom'?'block':'none'?>;margin-bottom:14px;">
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Custom content appended to each post in feed:</label>
            <textarea id="sgc-rss-suffix" rows="3"
                      style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;box-sizing:border-box;"
                      placeholder="e.g. Visit labsglass.com for more lab equipment"><?php echo esc_textarea($rss_suffix)?></textarea>
        </div>

        <!-- Remove head links option -->
        <div style="padding:12px;background:#f8fafc;border-radius:8px;margin-bottom:16px;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
                <input type="checkbox" id="sgc-rss-rm-head" value="1" <?php checked($rss_rm_head,'1')?> style="width:16px;height:16px;accent-color:#2563eb;">
                <div>
                    <strong>Remove RSS links from &lt;head&gt;</strong>
                    <div style="font-size:11px;color:#6b7280;">wp_head() se feed discovery links hata do — cleaner HTML</div>
                </div>
            </label>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button onclick="sgcSaveRSS()"
                style="background:#1a1a2e;color:#fff;border:none;padding:10px 22px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
                💾 Save RSS Settings
            </button>
            <button onclick="sgcTestFeed()"
                style="background:#2563eb;color:#fff;border:none;padding:10px 16px;border-radius:8px;font-size:13px;cursor:pointer;">
                🔍 Test Feed
            </button>
        </div>
        <div id="sgc-rss-msg" style="margin-top:10px;font-size:13px;"></div>
    </div>

    <!-- Right: Feed URLs + info -->
    <div>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin-bottom:14px;">
            <h4 style="margin:0 0 12px;font-size:13px;font-weight:700;">📡 Your Feed URLs</h4>
            <?php
            $feeds = [
                get_bloginfo('rss2_url')      => 'Main RSS 2.0 Feed',
                get_bloginfo('atom_url')       => 'Atom Feed',
                get_bloginfo('comments_rss2_url') => 'Comments Feed',
            ];
            foreach ($feeds as $url => $label): ?>
            <div style="padding:8px 0;border-bottom:1px solid #f3f4f6;">
                <div style="font-size:12px;font-weight:600;color:#374151;"><?php echo esc_html($label)?></div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:3px;">
                    <code style="font-size:10px;color:#6b7280;"><?php echo esc_html(str_replace(home_url(),'',esc_url($url)))?></code>
                    <a href="<?php echo esc_url($url)?>" target="_blank"
                       style="background:#f3f4f6;color:#374151;padding:2px 8px;border-radius:4px;font-size:10px;text-decoration:none;">↗</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:14px;margin-bottom:14px;">
            <h4 style="margin:0 0 6px;font-size:12px;font-weight:700;color:#92400e;">⚠️ Labsglass.com ke liye</h4>
            <p style="font-size:11px;color:#78350f;margin:0;line-height:1.7;">
                E-commerce site pe RSS feed mostly blog posts ke liye hai.<br>
                <strong>Recommended:</strong> "Keep Active" mode rakho — Google News aur aggregators use karte hain.<br>
                Agar blog nahi hai toh "Disable" kar sakte ho.
            </p>
        </div>

        <div id="sgc-feed-test-result" style="display:none;background:#1a1a2e;border-radius:12px;padding:14px;">
            <div id="sgc-feed-test-status" style="font-size:11px;font-weight:600;margin-bottom:6px;"></div>
            <pre id="sgc-feed-test-body" style="color:#e5e7eb;font-size:9px;margin:0;white-space:pre-wrap;max-height:180px;overflow-y:auto;"></pre>
        </div>
    </div>
</div>
</div><!-- tab rss -->

</div><!-- .wrap -->

<script>
(function($){
var nonce = '<?php echo esc_js( $nonce ); ?>';
var robotsActive = <?php echo $robots_active==='1'?'true':'false'; ?>;
var sitemapActive = <?php echo $sitemap_active==='1'?'true':'false'; ?>;

// ---- TABS ----
window.sgcToolTab = function(id) {
    document.querySelectorAll('.sg-tool-tab').forEach(t => t.style.display = 'none');
    document.querySelectorAll('[id^="sgtab-"]').forEach(b => {
        if (b.tagName === 'BUTTON') { b.style.color='#6b7280'; b.style.borderBottomColor='transparent'; b.style.background='none'; }
    });
    document.getElementById('sgtab-'+id+'-content').style.display = 'block';
    var btn = document.getElementById('sgtab-'+id);
    if (btn) { btn.style.color='#1a1a2e'; btn.style.borderBottomColor='#1a1a2e'; btn.style.background='#f8fafc'; }
};

// =================== ROBOTS.TXT ===================
window.sgcToggleRobots = function() {
    robotsActive = !robotsActive;
    var tog = document.getElementById('sgc-robots-toggle');
    var knob = tog.querySelector('div');
    var stat = document.getElementById('sgc-robots-status');
    tog.style.background = robotsActive ? '#16a34a' : '#d1d5db';
    knob.style.left = robotsActive ? '20px' : '2px';
    stat.textContent = robotsActive ? 'Active' : 'Inactive';
};

window.sgcSaveRobots = function() {
    var txt = document.getElementById('sgc-robots-textarea').value;
    var btn = event.target; btn.textContent = '⏳ Saving…'; btn.disabled = true;
    $.post(ajaxurl, {action:'sgclabs_save_robots', nonce:nonce, robots_txt:txt, active: robotsActive?'1':'0'}, function(res) {
        btn.textContent = '💾 Save Robots.txt'; btn.disabled = false;
        if (res.success) {
            document.getElementById('sgc-robots-msg').innerHTML = '✅ Saved! <a href="'+res.data.url+'" target="_blank" style="color:#2563eb;">View live ↗</a>';
        } else {
            document.getElementById('sgc-robots-msg').innerHTML = '<span style="color:#dc2626;">❌ Error</span>';
        }
    });
};

window.sgcResetRobots = function() {
    if (!confirm('Default robots.txt se replace kar dein?')) return;
    $.post(ajaxurl, {action:'sgclabs_save_robots', nonce:nonce, robots_txt:'', active:'0'}, function() {
        location.reload();
    });
};

window.sgcTestRobots = function() {
    $.post(ajaxurl, {action:'sgclabs_test_robots', nonce:nonce}, function(res) {
        if (!res.success) return;
        document.getElementById('sgc-robots-test-result').style.display = 'block';
        document.getElementById('sgc-robots-live').textContent = res.data.body || 'Empty response';
    });
};

window.sgcInsertRule = function(rule) {
    var ta = document.getElementById('sgc-robots-textarea');
    ta.value += '\n' + rule;
    ta.focus();
};

// =================== SITEMAP ===================
function sgcLoadSitemapStats() {
    $.post(ajaxurl, {action:'sgclabs_sitemap_stats', nonce:nonce}, function(res) {
        if (!res.success) return;
        var d = res.data, html = '';
        if (d.rm_active || d.yoast_active) {
            var plugin = d.rm_active ? 'RankMath' : 'Yoast';
            html = '<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px;font-size:12px;color:#92400e;">⚠️ '+plugin+' active hai — uska sitemap use ho raha hai. '+plugin+' deactivate karne ke baad SGC Sitemap enable karo.</div>';
        } else if (d.stats && d.stats.length) {
            html = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;margin-top:4px;">';
            d.stats.forEach(s => {
                var accessible = d.engine_active;
                html += '<a href="'+s.url+'" target="_blank" style="text-decoration:none;">';
                html += '<div style="background:'+( accessible?'#f0fdf4':'#f9fafb')+';border:1px solid '+(accessible?'#bbf7d0':'#e5e7eb')+';border-radius:8px;padding:10px 12px;">';
                html += '<div style="font-size:11px;color:#6b7280;margin-bottom:2px;">'+s.name+'</div>';
                html += '<div style="font-size:18px;font-weight:700;color:'+(accessible?'#16a34a':'#374151')+';">'+s.count.toLocaleString()+'</div>';
                html += '<div style="font-size:10px;color:#9ca3af;">'+s.pages+' page'+(s.pages>1?'s':'')+'</div>';
                html += '</div></a>';
            });
            html += '</div>';
            if (d.index_status === 200) {
                html += '<div style="margin-top:10px;font-size:12px;color:#16a34a;">✅ sitemap_index.xml accessible (HTTP '+d.index_status+')</div>';
            } else if (d.index_status > 0) {
                html += '<div style="margin-top:10px;font-size:12px;color:#dc2626;">⚠️ sitemap_index.xml returned HTTP '+d.index_status+'</div>';
            } else {
                html += '<div style="margin-top:10px;font-size:12px;color:#d97706;">💡 Sitemap engine enable karo upar se — sab URLs kaam karne lagenge</div>';
            }
        }
        document.getElementById('sgc-sitemap-stats-wrap').innerHTML = html;
    });
}

window.sgcToggleSitemap = function() {
    sitemapActive = !sitemapActive;
    var tog = document.getElementById('sgc-sitemap-toggle-el');
    var knob = document.getElementById('sgc-sitemap-knob');
    var txt  = document.getElementById('sgc-sitemap-status-text');
    if (tog) tog.style.background = sitemapActive ? '#16a34a' : '#d1d5db';
    if (knob) knob.style.left = sitemapActive ? '24px' : '3px';
    if (txt) txt.textContent = sitemapActive ? '✅ Active' : '❌ Inactive';
    $.post(ajaxurl, {action:'sgclabs_sitemap_toggle', nonce:nonce, active: sitemapActive?'1':'0'}, function(res) {
        setTimeout(sgcLoadSitemapStats, 800);
    });
};

window.sgcPingSitemaps = function() {
    var btn = event.target; btn.textContent = '⏳ Pinging…'; btn.disabled = true;
    $.post(ajaxurl, {action:'sgclabs_sitemap_ping_engines', nonce:nonce}, function(res) {
        btn.textContent = '📡 Submit Sitemap Now'; btn.disabled = false;
        if (!res.success) return;
        var html = '<div style="margin-top:6px;">';
        res.data.results.forEach(r => {
            var ok = r.ok && r.code >= 200 && r.code < 300;
            html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">';
            html += '<span>'+(ok?'✅':'❌')+'</span><strong style="font-size:12px;min-width:70px;">'+r.engine+'</strong>';
            html += '<span style="font-size:12px;color:'+(ok?'#16a34a':'#dc2626')+';">HTTP '+r.code+'</span></div>';
        });
        html += '<div style="font-size:11px;color:#6b7280;margin-top:4px;">Sitemap: '+res.data.sitemap+'</div></div>';
        document.getElementById('sgc-sitemap-ping-result').innerHTML = html;
    }).fail(() => { btn.textContent = '📡 Submit Sitemap Now'; btn.disabled = false; });
};

window.sgcSaveIndexNow = function() {
    var key = document.getElementById('sgc-indexnow-key2').value;
    $.post(ajaxurl, {action:'sgclabs_save_indexnow', nonce:nonce, key:key}, function(res) {
        if (res.success) alert('✅ IndexNow key saved!');
    });
};

// =================== RSS ===================
window.sgcSelectRSSMode = function(mode) {
    document.querySelectorAll('input[name="rss_mode"]').forEach(r => {
        r.checked = (r.value === mode);
        var lbl = r.closest('label');
        if (lbl) {
            lbl.style.borderColor = r.checked ? '#2563eb' : '#e5e7eb';
            lbl.style.background  = r.checked ? '#eff6ff' : '#fff';
        }
    });
    ['redirect','disable','custom'].forEach(f => {
        var el = document.getElementById('rss-field-'+f);
        if (el) el.style.display = (mode === f) ? 'block' : 'none';
    });
};

window.sgcSaveRSS = function() {
    var mode = document.querySelector('input[name="rss_mode"]:checked')?.value || 'keep';
    var data = {
        action: 'sgclabs_save_rss', nonce: nonce, mode: mode,
        redirect_url:    document.getElementById('sgc-rss-redirect-url')?.value  || '',
        disable_redirect: document.getElementById('sgc-rss-disable-redirect')?.value || '',
        custom_suffix:   document.getElementById('sgc-rss-suffix')?.value         || '',
        remove_head_links: document.getElementById('sgc-rss-rm-head')?.checked ? '1' : '0',
    };
    var btn = event.target; btn.textContent = '⏳ Saving…'; btn.disabled = true;
    $.post(ajaxurl, data, function(res) {
        btn.textContent = '💾 Save RSS Settings'; btn.disabled = false;
        document.getElementById('sgc-rss-msg').innerHTML = res.success
            ? '<span style="color:#16a34a;">✅ RSS settings saved!</span>'
            : '<span style="color:#dc2626;">❌ Error</span>';
    });
};

window.sgcTestFeed = function() {
    var url = '<?php echo esc_js(get_bloginfo("rss2_url")); ?>';
    $.post(ajaxurl, {action:'sgclabs_test_feed', nonce:nonce, url:url}, function(res) {
        if (!res.success) return;
        var el   = document.getElementById('sgc-feed-test-result');
        var stat = document.getElementById('sgc-feed-test-status');
        var body = document.getElementById('sgc-feed-test-body');
        el.style.display = 'block';
        var ok = res.data.code === 200;
        stat.innerHTML = (ok ? '<span style="color:#10b981;">✅ HTTP '+res.data.code+' — Feed accessible</span>' : '<span style="color:#ef4444;">❌ HTTP '+res.data.code+'</span>');
        body.textContent = res.data.preview;
    });
};

// Init
sgcToolTab('robots');
sgcLoadSitemapStats();

})(jQuery);
</script>
    <?php
}
