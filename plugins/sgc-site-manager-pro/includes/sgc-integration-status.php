<?php
/**
 * SGC Integration Status — Manager Pro
 * Shows connected SGC plugins and their status in admin dashboard.
 */
defined('ABSPATH') || exit;

// Add integration status to Manager Pro dashboard
add_action('admin_init', function(){
    // Register integration info via option for other plugins to read
    update_option('sgclabs_manager_pro_version', defined('SGCLABS_BASE_VERSION') ? SGCLABS_BASE_VERSION : '7.4.2', false);
    update_option('sgclabs_manager_pro_active',    '1',     false);
});

// Add "SGC Ecosystem" section to Manager Pro admin page
add_action('sgclabs_dashboard_after_cards', 'sgclabs_ecosystem_status_card');
function sgclabs_ecosystem_status_card(){
    $plugins = [
        'SGC Invoice · Packlist Pro Manager' => defined('SGC_IIP_VERSION') ? SGC_IIP_VERSION : null,
        'SGC Product Manager Pro'            => defined('SGC_PM_VERSION')  ? SGC_PM_VERSION  : null,
        'SGC Smart CRM Pro'                  => class_exists('SGC_CRM_DB') ? get_option('sgc_crm_version','1.0.6') : null,
    ];
    echo '<div class="sgclabs-section" style="margin-top:20px">';
    echo '<h3>🔗 SGC Plugin Ecosystem</h3>';
    echo '<table class="widefat" style="max-width:500px"><tbody>';
    foreach($plugins as $name => $ver){
        $status = $ver
            ? '<span style="color:#27ae60;font-weight:600">✅ Active v'.$ver.'</span>'
            : '<span style="color:#999">⭕ Not installed</span>';
        echo '<tr><td>'.esc_html($name).'</td><td>'.$status.'</td></tr>';
    }
    $usd = get_option('sgc_pm_usd_rate', get_option('sgc_iip_usd_rate','—'));
    echo '<tr><td>Shared USD Rate</td><td><strong>Rs.'.esc_html($usd).'</strong></td></tr>';
    $ai_provider = get_option('sgclabs_ai_seo_provider','—');
    $ai_key = get_option('sgclabs_aichat_deepseek_key') ? '✅ Set' : '⚠️ Not set';
    echo '<tr><td>AI Provider (shared)</td><td>'.esc_html($ai_provider).' — '.$ai_key.'</td></tr>';
    echo '</tbody></table>';
    echo '</div>';
}

// ── Changelog Admin Page ──────────────────────────────────────────
add_action('admin_menu', function(){
    add_submenu_page(
        'sgclabs-manager',
        'SGC Changelog & Integration',
        '📋 Changelog',
        'manage_options',
        'sgclabs-changelog',
        'sgclabs_changelog_page'
    );
}, 99);

function sgclabs_changelog_page(){
    $ver      = defined('SGCLABS_BASE_VERSION') ? SGCLABS_BASE_VERSION : '7.4.2';
    $build_id = defined('SGCLABS_BUILD_ID')     ? SGCLABS_BUILD_ID     : '20260509-1100';
    $build_dt = defined('SGCLABS_BUILD_DATE')   ? SGCLABS_BUILD_DATE   : '2026-05-09';
    $build_tm = defined('SGCLABS_BUILD_TIME')   ? SGCLABS_BUILD_TIME   : '10:00';

    $changelog = [
        ['v7.4.2', '09 May 2026', 'Export SEO Engine', '#059669',
         ['✅ GPL 2.0 compliance — Tested up to 6.8, Requires PHP 8.0, Plugin URI, @package, @copyright',
          '✅ WooCommerce HPOS + Cart Blocks compatibility declared (FeaturesUtil)',
          '✅ ABSPATH guard added to ALL 67 includes',
          '✅ PHP 8.0 guard — admin notice, does not block below 8.0',
          '⭐ AggregateRating schema — WC reviews → Google star ratings in SERP',
          '❓ AI FAQ bulk generator — FAQPage schema → Google People Also Ask',
          '🌍 Country landing pages — [sgclabs_export_page country="Germany"] shortcode (10 countries)',
          '🔍 Webmaster verification — Baidu, Bing, Yandex, Pinterest meta tags',
          '📊 Bulk SEO health dashboard — score /100 per product, priority queue',
          '📢 Export Open Graph — HS code, FOB price, worldwide exporter in OG tags',
          '🛒 Google Merchant Center feed — /sgclabs-gmc-feed.xml',
          '📊 Build ID: ' . $build_id . ' | Date: ' . $build_dt . ' ' . $build_tm . ' UTC',
         ]],
        ['v7.3.1', '04 May 2026', 'Pipeline Fix + Inquiry Automation', '#7c3aed',
         ['🔗 Integration: SGC Invoice Pro, Product Manager Pro, Smart CRM Pro connected',
          '🔗 Shared AI keys auto-shared with Invoice Pro',
          '✅ sgclabs_inquiries + sgclabs_kw_rankings tables added to activate()',
          '✨ Inquiry automation — follow-up cron, CRM sync, pipeline stages',
         ]],
        ['v6.8.6', '18 Apr 2026', 'Previous', '#1a1a2e',
         ['Dashboard restructured — 12 menu items',
          'B2B Export pricing, Price Comparison Widget',
          'AI Task Router — Gemini/DeepSeek/Claude routing',
         ]],
    ];
    ?>
    <div class="wrap">
    <h1>📋 SGC Site Manager Pro — v<?php echo esc_html($ver); ?></h1>

    <!-- Version / Build Info Panel -->
    <div style="background:#1a1a2e;border-radius:10px;padding:16px 20px;margin-bottom:20px;max-width:900px;display:flex;gap:24px;flex-wrap:wrap;align-items:center">
      <div>
        <div style="font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px">Version</div>
        <div style="font-size:22px;font-weight:800;color:#27ae60">v<?php echo esc_html($ver); ?></div>
      </div>
      <div>
        <div style="font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px">Build ID</div>
        <div style="font-size:13px;font-weight:600;color:#fff;font-family:monospace"><?php echo esc_html($build_id); ?></div>
      </div>
      <div>
        <div style="font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px">Build Date</div>
        <div style="font-size:13px;font-weight:600;color:#fff"><?php echo esc_html($build_dt); ?> <?php echo esc_html($build_tm); ?> UTC</div>
      </div>
      <div>
        <div style="font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px">License</div>
        <div style="font-size:13px;font-weight:600;color:#4ade80">GPL v2 or later</div>
      </div>
      <div>
        <div style="font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px">PHP Runtime</div>
        <div style="font-size:13px;font-weight:600;color:#fff"><?php echo esc_html(PHP_VERSION); ?></div>
      </div>
      <div>
        <div style="font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px">WP Version</div>
        <div style="font-size:13px;font-weight:600;color:#fff"><?php echo esc_html(get_bloginfo('version')); ?></div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:900px;margin-bottom:20px">
    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px">
      <h3 style="margin-top:0">🔗 Plugin Ecosystem Status</h3>
      <table style="width:100%;font-size:13px;border-collapse:collapse">
        <?php
        $eco = [
          'SGC Invoice Pro'      => defined('SGC_IIP_VERSION') ? '✅ v'.SGC_IIP_VERSION : '⭕ Not active',
          'SGC Product Manager'  => defined('SGC_PM_VERSION')  ? '✅ v'.SGC_PM_VERSION  : '⭕ Not active',
          'SGC Smart CRM Pro'    => class_exists('SGC_CRM_DB') ? '✅ Active'            : '⭕ Not active',
          'WooCommerce'          => defined('WC_VERSION')       ? '✅ v'.WC_VERSION      : '⭕ Not active',
        ];
        foreach($eco as $n=>$s): ?>
        <tr style="border-bottom:1px solid #f0f0f0">
          <td style="padding:6px 0;color:#555"><?php echo esc_html($n); ?></td>
          <td style="padding:6px 0"><?php echo $s; ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px">
      <h3 style="margin-top:0">🤖 Shared AI Keys Status</h3>
      <table style="width:100%;font-size:13px;border-collapse:collapse">
        <?php
        $keys = [
          'DeepSeek' => get_option('sgclabs_aichat_deepseek_key',''),
          'Claude'   => get_option('sgclabs_claude_api_key',''),
          'OpenAI'   => get_option('sgclabs_aichat_openai_key',''),
          'Gemini'   => get_option('sgclabs_aichat_gemini_key',''),
        ];
        foreach($keys as $k=>$v): ?>
        <tr style="border-bottom:1px solid #f0f0f0">
          <td style="padding:6px 0;color:#555"><?php echo esc_html($k); ?></td>
          <td style="padding:6px 0"><?php echo $v ? '<span style="color:#2e7d32">✅ Set</span>' : '<span style="color:#aaa">Not set</span>'; ?></td>
        </tr>
        <?php endforeach;
        $usd = get_option('sgc_pm_usd_rate', get_option('sgc_iip_usd_rate','—'));
        echo '<tr><td style="padding:6px 0;color:#555">USD Rate (shared)</td><td style="padding:6px 0"><strong>Rs.'.esc_html($usd).'</strong></td></tr>';
        ?>
      </table>
    </div>
    </div>

    <?php foreach($changelog as $entry): ?>
    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden;margin-bottom:12px;max-width:900px">
      <div style="background:<?php echo esc_attr($entry[3]); ?>;padding:10px 16px;display:flex;align-items:center;gap:10px">
        <span style="background:rgba(255,255,255,.2);color:#fff;font-size:12px;font-weight:700;padding:2px 10px;border-radius:10px"><?php echo esc_html($entry[0]); ?></span>
        <span style="color:rgba(255,255,255,.7);font-size:11px"><?php echo esc_html($entry[1]); ?></span>
        <span style="margin-left:auto;background:rgba(255,255,255,.15);color:#fff;font-size:10px;padding:1px 8px;border-radius:8px"><?php echo esc_html($entry[2]); ?></span>
      </div>
      <div style="padding:12px 16px;background:#fafafa">
        <?php foreach($entry[4] as $ch): ?>
        <div style="font-size:12px;color:#333;padding:4px 0;border-bottom:1px solid #f0f0f0"><?php echo esc_html($ch); ?></div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php
}
