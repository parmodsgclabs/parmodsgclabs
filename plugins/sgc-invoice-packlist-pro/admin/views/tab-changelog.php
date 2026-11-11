<?php defined('ABSPATH')||exit; ?>
<div id="iip-changelog-wrap" style="max-width:780px">

<!-- Plugin Info Card -->
<div class="iip-box" style="background:linear-gradient(135deg,#1a1a2e,#2a2a4e);color:#fff;padding:28px">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap">
    <div>
      <div style="font-size:22px;font-weight:800;margin-bottom:6px">
        SGC Invoice · Packlist Pro Manager
        <span style="font-size:12px;background:#4ade80;color:#1a1a2e;padding:2px 10px;border-radius:10px;font-weight:700;margin-left:8px;vertical-align:middle">
          v<?php echo esc_html(SGC_IIP_VERSION); ?>
        </span>
      </div>
      <div style="color:#9090aa;font-size:13px;margin-bottom:4px">Complete B2B Invoice & Packlist Suite for SGC LABS®</div>
      <div style="color:#6b7280;font-size:11px">
        Build Date: <?php echo defined('SGC_IIP_BUILD') ? esc_html(SGC_IIP_BUILD) : date('Y-m-d'); ?> &nbsp;·&nbsp;
        PHP: <?php echo phpversion(); ?> &nbsp;·&nbsp;
        WordPress: <?php echo get_bloginfo('version'); ?> &nbsp;·&nbsp;
        WooCommerce: <?php echo defined('WC_VERSION') ? WC_VERSION : '(not active)'; ?>
      </div>
    </div>
    <div style="text-align:right">
      <div style="background:rgba(255,255,255,.08);border-radius:8px;padding:12px 18px;font-size:11px;color:#9090aa">
        <div style="color:#4ade80;font-weight:700;font-size:14px">SGC LABS®</div>
        <div>labsglass.com</div>
        <div>Ambala, Haryana, India</div>
      </div>
    </div>
  </div>
</div>

<!-- System Status -->
<div class="iip-box">
  <h3>🔍 System Status</h3>
  <table style="width:100%;border-collapse:collapse;font-size:12px">
    <?php
    global $wpdb;
    $checks = [
      ['Plugin Version',     SGC_IIP_VERSION,                                              '✅'],
      ['Build Date',         defined('SGC_IIP_BUILD') ? SGC_IIP_BUILD : 'N/A',           '✅'],
      ['DB Tables',          (int)$wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}sgc_iip_inquiries'")===1?'All present':'Missing tables', (int)$wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}sgc_iip_inquiries'")===1?'✅':'❌'],
      ['Manager Pro Tables', (int)$wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}sgclabs_inquiries'")===1?'Connected':'Not found',      (int)$wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}sgclabs_inquiries'")===1?'✅':'⚠️'],
      ['WooCommerce',        defined('WC_VERSION')?'Active (v'.WC_VERSION.')':'Not active',                                                 defined('WC_VERSION')?'✅':'⚠️'],
      ['PDF Engine',         'Built-in (zero dependencies)',                                 '✅'],
      ['AI Provider',        ucfirst(get_option('sgc_iip_ai_provider','deepseek')),         get_option('sgc_iip_ai_key_'.get_option('sgc_iip_ai_provider','deepseek'))?'✅':'⚠️ No API key'],
      ['WhatsApp',           get_option('sgc_iip_wa_enabled','0')==='1'?'Enabled':'Disabled','ℹ️'],
      ['Manager Pro', function_exists('sgclabs_ai_call')?'✅ Connected':'⚠️ Not active', '🔗'],
      ['Product Manager Pro', defined('SGC_PM_VERSION')?'✅ Connected (v'.SGC_PM_VERSION.')':'⚠️ Not active', '🔗'],
      ['Smart CRM Pro', class_exists('SGC_CRM_DB')?'✅ Connected':'⚠️ Not active', '🔗'],
      ['Shared USD Rate', get_option('sgc_pm_usd_rate')?'Rs.'.get_option('sgc_pm_usd_rate').' (from PM Pro)':'Not set', '💱'],
      ['Shared AI Keys', function_exists('sgclabs_ai_providers')&&!empty(array_filter(array_map(fn($p)=>get_option($p['option_key'],''),sgclabs_ai_providers())))?'✅ Manager Pro keys available':'—', '🤖'],
      ['Total Inquiries',    (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgc_iip_inquiries"),                                  '📊'],
      ['Proformas Created',  (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgc_iip_proformas"),                                  '📊'],
      ['Upload Dir',         file_exists(wp_upload_dir()['basedir'].'/sgc-iip-pdfs/')?'PDF folder exists':'Will be created on first PDF',   '✅'],
    ];
    foreach($checks as $i=>[$label,$val,$icon]):
      $bg = $i%2?'#fff':'#f8f9fa';
    ?>
    <tr style="background:<?php echo $bg; ?>">
      <td style="padding:9px 12px;border:1px solid #e0e0e0;color:#555;font-weight:500;width:40%"><?php echo esc_html($label); ?></td>
      <td style="padding:9px 12px;border:1px solid #e0e0e0;font-weight:600;color:#1a1a2e"><?php echo esc_html((string)$val); ?></td>
      <td style="padding:9px 12px;border:1px solid #e0e0e0;text-align:center;width:40px"><?php echo esc_html($icon); ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<!-- Changelog -->
<div class="iip-box">
  <h3>📋 Changelog</h3>

  <?php
  $changelog = [
    [
      'version' => '1.0.7',
      'date'    => '19 Apr 2026, 19:30 IST',
      'type'    => 'Major',
      'color'   => '#2e7d32',
      'changes' => [
        '✅ Manual PI editor — full control over each line item (product, qty, price, HS code, specs)',
        '✅ GST field — auto-shown for Indian buyers, hidden for export',
        '✅ Freight charges — separate field with auto-calculation',
        '✅ WooCommerce Orders sync — preview, select, import to IIP',
        '✅ WC Product search — add from WC catalog directly into PI line items',
        '✅ PDF engine rewrite — proper A4 balanced margins (36pt each side)',
        '✅ PDF number fix — auto font-shrink prevents truncation (no more "Rs.2,4")',
        '✅ PI Accept page — completely standalone, zero WordPress theme interference',
        '✅ SWIFT/IFSC fix in PDF — India→IFSC only, International→SWIFT only',
        '✅ Grand total calculation — GST + Freight now included in formula',
        '✅ Plugin renamed: "SGC Invoice · Packlist Pro Manager"',
        '✅ Internal changelog with build date added',
      ],
    ],
    [
      'version' => '1.0.4',
      'date'    => '18 Apr 2026, 17:00 IST',
      'type'    => 'Fix',
      'color'   => '#1565c0',
      'changes' => [
        '✅ Email: Indian buyer → Grand Total in INR (was showing USD symbol with INR value)',
        '✅ Email: IFSC shown for domestic, SWIFT for international',
        '✅ Email: Footer "Sales Team" for India, "Export Team" for export',
        '✅ Email: Incoterm row hidden for domestic buyers',
        '✅ Autoreply: "our team" instead of "our export team"',
      ],
    ],
    [
      'version' => '1.0.3',
      'date'    => '18 Apr 2026, 16:30 IST',
      'type'    => 'Major',
      'color'   => '#2e7d32',
      'changes' => [
        '✅ Pure PHP PDF engine — zero Dompdf dependency, no library needed',
        '✅ Manager Pro sync — Preview, select, import inquiries from wp_sgclabs_inquiries',
        '✅ One-click PI send — AI draft → PDF → Email in single click',
        '✅ PDF tested and validated (valid %PDF header, 9.6KB output)',
      ],
    ],
    [
      'version' => '1.0.2',
      'date'    => '18 Apr 2026, 16:00 IST',
      'type'    => 'Feature',
      'color'   => '#7c3aed',
      'changes' => [
        '✅ Manager Pro compatibility layer — reads wp_sgclabs_inquiries',
        '✅ WooCommerce order fetch foundation',
        '✅ Tab-inquiries redesigned with sync buttons',
        '✅ All syntax checks passing (26 files)',
      ],
    ],
    [
      'version' => '1.0.1',
      'date'    => '18 Apr 2026, 15:30 IST',
      'type'    => 'Hotfix',
      'color'   => '#c62828',
      'changes' => [
        '🐛 Critical: Hook priority changed from 20→1 to fire BEFORE Manager Pro',
        '🐛 Manager Pro calls wp_send_json_success() which calls die() — hooks at priority 20+ never fired',
        '🐛 source_id DB query removed (Manager Pro hasn\'t saved yet at priority 1)',
      ],
    ],
    [
      'version' => '1.0.0',
      'date'    => '18 Apr 2026, 14:00 IST',
      'type'    => 'Initial',
      'color'   => '#1a1a2e',
      'changes' => [
        '🚀 Initial release',
        '✅ 8 DB tables, full inquiry pipeline',
        '✅ AI engine (DeepSeek/OpenAI/Claude) with fallback chain',
        '✅ WhatsApp Meta Cloud API integration',
        '✅ Email engine — branded HTML, autoreply, PI delivery, advance receipt',
        '✅ Pipeline kanban (8 stages), Follow-up automation (cron hourly)',
        '✅ CRM bridge — won inquiry → SGC Smart CRM Pro',
        '✅ PDF templates (Navy, Export Pro, Minimal)',
        '✅ Packing List with CBM calculator, sea/air freight estimate',
      ],
    ],
  ];

  foreach($changelog as $entry): ?>
  <div style="border:1px solid #e0e0e0;border-radius:8px;overflow:hidden;margin-bottom:12px">
    <div style="background:<?php echo esc_attr($entry['color']); ?>;padding:10px 16px;display:flex;align-items:center;gap:10px">
      <span style="background:rgba(255,255,255,.2);color:#fff;font-size:12px;font-weight:800;padding:2px 10px;border-radius:10px">v<?php echo esc_html($entry['version']); ?></span>
      <span style="color:#fff;font-size:11px;opacity:.8"><?php echo esc_html($entry['date']); ?></span>
      <span style="margin-left:auto;background:rgba(255,255,255,.15);color:#fff;font-size:10px;padding:1px 8px;border-radius:8px;font-weight:600"><?php echo esc_html($entry['type']); ?></span>
    </div>
    <div style="padding:12px 16px;background:#fafafa">
      <?php foreach($entry['changes'] as $change): ?>
      <div style="font-size:12px;color:#333;padding:4px 0;line-height:1.6;border-bottom:1px solid #f0f0f0"><?php echo esc_html($change); ?></div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Quick Actions -->
<div class="iip-box">
  <h3>🔧 Quick Database Actions</h3>
  <p style="font-size:12px;color:#888;margin-bottom:14px">Use these if you updated the plugin and need to add new DB columns.</p>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <button class="iip-btn primary" id="btn-run-dbdelta">🔄 Run DB Migration (dbDelta)</button>
    <button class="iip-btn" id="btn-check-tables">📊 Check Tables</button>
    <span id="db-action-result" style="font-size:12px;line-height:34px;color:#2e7d32"></span>
  </div>
</div>

</div>

<script>
jQuery(function($){
  $('#btn-run-dbdelta').on('click',function(){
    $(this).prop('disabled',true).text('Running…');
    $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_run_migration',nonce:SGCIIP.nonce},function(r){
      $('#btn-run-dbdelta').prop('disabled',false).text('🔄 Run DB Migration');
      $('#db-action-result').text(r.success?'✅ '+r.data:'❌ '+r.data);
    });
  });
  $('#btn-check-tables').on('click',function(){
    $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_check_tables',nonce:SGCIIP.nonce},function(r){
      $('#db-action-result').text(r.success?'✅ '+r.data:'❌ '+r.data);
    });
  });
});
</script>
