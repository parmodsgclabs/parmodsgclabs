<?php
/**
 * SGC IIP — All Documents Tab v1.2.4
 * One-Click Full Sync + All Inquiries, PIs, Packlists
 */
defined('ABSPATH')||exit;
$nonce   = wp_create_nonce('sgc_iip_nonce');
$csv_inq = admin_url("admin-post.php?action=sgc_iip_export_inq_csv&nonce=$nonce");
$csv_pi  = admin_url("admin-post.php?action=sgc_iip_export_pi_csv&nonce=$nonce");
$csv_pl  = admin_url("admin-post.php?action=sgc_iip_export_pl_csv&nonce=$nonce");
?>
<style>
.docs-toolbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px}
.docs-search{padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;min-width:180px}
.docs-sel{padding:5px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:12px}
.docs-section{margin-bottom:22px}
.docs-section-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;padding-bottom:6px;border-bottom:2px solid #e5e7eb}
.docs-section-title{font-size:13px;font-weight:600;color:#1a1a2e;display:flex;align-items:center;gap:6px}
.docs-count{font-size:11px;background:#f3f4f6;color:#555;padding:2px 8px;border-radius:10px;font-weight:500}
.docs-tbl{width:100%;border-collapse:collapse;font-size:12px}
.docs-tbl th{background:#f8f9fa;padding:7px 10px;text-align:left;font-weight:600;color:#374151;border-bottom:2px solid #e5e7eb;white-space:nowrap}
.docs-tbl td{padding:7px 10px;border-bottom:1px solid #f0f0f0;vertical-align:middle}
.docs-tbl tr:hover td{background:#fafafa}
.docs-badge{font-size:10px;padding:2px 7px;border-radius:10px;font-weight:600;white-space:nowrap}
.st-draft{background:#f3f4f6;color:#6b7280}.st-sent{background:#dbeafe;color:#1d4ed8}
.st-accepted{background:#d1fae5;color:#065f46}.st-won{background:#bbf7d0;color:#14532d}
.st-lost{background:#fee2e2;color:#991b1b}.st-new{background:#fef9c3;color:#713f12}
.st-advance{background:#fde68a;color:#92400e}.st-replied{background:#e9d5ff;color:#6d28d9}
.docs-act{display:flex;gap:4px;align-items:center;flex-wrap:wrap}
.docs-btn{font-size:10px;padding:3px 8px;border-radius:5px;cursor:pointer;border:1px solid;font-weight:500;text-decoration:none;display:inline-flex;align-items:center;gap:3px;white-space:nowrap}
.db-open{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8}
.db-pdf{background:#f0fdf4;border-color:#bbf7d0;color:#166534}
.docs-pager{display:flex;gap:6px;align-items:center;margin-top:8px;font-size:12px}
.docs-pager button{padding:3px 10px;border:1px solid #d1d5db;border-radius:5px;background:#fff;cursor:pointer;font-size:12px}
.docs-pager button:disabled{opacity:.4;cursor:default}
.docs-empty{text-align:center;padding:22px;color:#9ca3af;font-size:12px}

/* ── Sync Bar ───────────────────────────────── */
.sync-bar{background:#1a1a2e;border-radius:10px;padding:14px 16px;margin-bottom:14px}
.sync-bar-top{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px}
.sync-bar-title{color:#fff;font-size:13px;font-weight:700;margin-right:4px}
.sync-source{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);border-radius:7px;padding:7px 12px;text-align:center;min-width:80px}
.sync-source-label{font-size:9px;color:rgba(255,255,255,.45);margin-bottom:2px}
.sync-source-count{font-size:16px;font-weight:700;color:#fff;line-height:1}
.sync-source-synced{font-size:9px;color:#4ade80;margin-top:1px}
.sync-source-pending{font-size:9px;color:#fbbf24;margin-top:1px}
.btn-full-sync{background:linear-gradient(135deg,#27ae60,#1e9651);color:#fff;border:none;border-radius:7px;padding:10px 20px;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px;white-space:nowrap;box-shadow:0 2px 8px rgba(39,174,96,.4)}
.btn-full-sync:disabled{background:#444;box-shadow:none;cursor:wait}
.btn-full-sync:hover:not(:disabled){background:linear-gradient(135deg,#1e9651,#178a42)}
.sync-prog-wrap{margin-top:0}
.sync-prog-bar{height:5px;background:rgba(255,255,255,.1);border-radius:3px;overflow:hidden;margin-bottom:5px}
.sync-prog-fill{height:100%;background:#27ae60;border-radius:3px;width:0%;transition:width .5s ease}
.sync-prog-text{font-size:11px;color:rgba(255,255,255,.55)}
.sync-log{background:rgba(0,0,0,.4);border-radius:6px;padding:8px 12px;font-size:10px;color:#9ca3af;font-family:monospace;max-height:70px;overflow-y:auto;margin-top:8px;display:none;line-height:1.5}
.sync-log .ok{color:#4ade80}.sync-log .warn{color:#fbbf24}.sync-log .err{color:#f87171}
</style>

<div>

<!-- ══════════════════════════════════════════════════
     ONE-CLICK FULL SYNC BAR
═══════════════════════════════════════════════════ -->
<div class="sync-bar">
  <div class="sync-bar-top">
    <span class="sync-bar-title">🔄 One-Click Full Sync</span>
    <div class="sync-source" id="src-manager">
      <div class="sync-source-label">Manager Pro</div>
      <div class="sync-source-count" id="src-mp-count">…</div>
      <div class="sync-source-pending" id="src-mp-pending"></div>
    </div>
    <div class="sync-source" id="src-wc">
      <div class="sync-source-label">WooCommerce</div>
      <div class="sync-source-count" id="src-wc-count">…</div>
      <div class="sync-source-pending" id="src-wc-pending"></div>
    </div>
    <div class="sync-source" id="src-iip">
      <div class="sync-source-label">Direct IIP</div>
      <div class="sync-source-count" id="src-iip-count">…</div>
      <div class="sync-source-synced">Native ✓</div>
    </div>
    <button class="btn-full-sync" id="btn-full-sync">
      <span id="sync-btn-icon">⚡</span>
      <span id="sync-btn-text">Sync All Data</span>
    </button>
  </div>
  <div class="sync-prog-wrap">
    <div class="sync-prog-bar"><div class="sync-prog-fill" id="sync-prog-fill"></div></div>
    <div class="sync-prog-text" id="sync-prog-text">Ready to sync all sources into Invoice Pro</div>
  </div>
  <div class="sync-log" id="sync-log"></div>
</div>

<!-- Toolbar -->
<div class="docs-toolbar">
  <input type="text" id="docs-global-search" class="docs-search" placeholder="Search name, email, company, PI number…">
  <select id="docs-status-filter" class="docs-sel">
    <option value="">All statuses</option>
    <option value="new">New</option><option value="replied">Replied</option>
    <option value="pi_sent">PI Sent</option><option value="accepted">Accepted</option>
    <option value="advance">Advance Rcvd</option><option value="won">Won</option>
    <option value="lost">Lost</option><option value="draft">Draft</option><option value="sent">Sent</option>
  </select>
  <select id="docs-country-filter" class="docs-sel">
    <option value="">All countries</option>
    <option value="India">India</option><option value="USA">USA</option>
    <option value="Germany">Germany</option><option value="UAE">UAE</option>
    <option value="UK">UK</option><option value="Nigeria">Nigeria</option>
    <option value="Kenya">Kenya</option><option value="Saudi Arabia">Saudi Arabia</option>
  </select>
  <button class="iip-btn sm" id="btn-docs-reload">↻ Refresh</button>
  <div style="margin-left:auto;display:flex;gap:6px;font-size:11px">
    Export:
    <a href="<?php echo esc_url($csv_inq); ?>" class="docs-btn db-open">📥 Inquiries</a>
    <a href="<?php echo esc_url($csv_pi); ?>"  class="docs-btn db-open">📥 PIs</a>
    <a href="<?php echo esc_url($csv_pl); ?>"  class="docs-btn db-open">📥 Packlists</a>
  </div>
</div>

<!-- Inquiries -->
<div class="docs-section">
  <div class="docs-section-hdr">
    <div class="docs-section-title">📋 All Inquiries <span class="docs-count" id="inq-count">—</span></div>
    <span style="font-size:11px;color:#9ca3af" id="inq-pages-info"></span>
  </div>
  <table class="docs-tbl">
    <thead><tr><th>#</th><th>INQ No.</th><th>Buyer</th><th>Country</th><th>Product</th><th>Source</th><th>Status</th><th>PI No.</th><th>Date</th><th>Actions</th></tr></thead>
    <tbody id="inq-body"><tr><td colspan="10" class="docs-empty">Loading…</td></tr></tbody>
  </table>
  <div class="docs-pager" id="inq-pager"></div>
</div>

<!-- PIs -->
<div class="docs-section">
  <div class="docs-section-hdr">
    <div class="docs-section-title">📄 All Proforma Invoices <span class="docs-count" id="pi-count">—</span></div>
    <span style="font-size:11px;color:#9ca3af" id="pi-pages-info"></span>
  </div>
  <table class="docs-tbl">
    <thead><tr><th>PI Number</th><th>Buyer</th><th>Company</th><th>Country</th><th>Amount</th><th>Paid</th><th>Balance</th><th>Status</th><th>Sent At</th><th>Actions</th></tr></thead>
    <tbody id="pi-body"><tr><td colspan="10" class="docs-empty">Loading…</td></tr></tbody>
  </table>
  <div class="docs-pager" id="pi-pager"></div>
</div>

<!-- Packlists -->
<div class="docs-section">
  <div class="docs-section-hdr">
    <div class="docs-section-title">📦 All Packing Lists <span class="docs-count" id="pl-count">—</span></div>
    <span style="font-size:11px;color:#9ca3af" id="pl-pages-info"></span>
  </div>
  <table class="docs-tbl">
    <thead><tr><th>PL Number</th><th>Linked PI</th><th>Buyer</th><th>Company</th><th>Country</th><th>Created</th><th>Actions</th></tr></thead>
    <tbody id="pl-body"><tr><td colspan="7" class="docs-empty">Loading…</td></tr></tbody>
  </table>
  <div class="docs-pager" id="pl-pager"></div>
</div>

</div><!-- end wrap -->

<script>
(function($){
'use strict';
var pages={inq:1,pi:1,pl:1}, search='', status='', country='';
var nonce=SGCIIP.nonce, ajaxUrl=SGCIIP.ajaxUrl;
var piBase='<?php echo admin_url("admin.php?page=sgc-iip&tab=proforma"); ?>';
var plBase='<?php echo admin_url("admin.php?page=sgc-iip&tab=packlist"); ?>';

var stColors={new:'st-new',replied:'st-replied',pi_sent:'st-sent',accepted:'st-accepted',advance:'st-advance',won:'st-won',lost:'st-lost',draft:'st-draft',sent:'st-sent',dispatched:'st-accepted'};
function escH(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function badge(st){return '<span class="docs-badge '+(stColors[st]||'st-draft')+'">'+escH(st)+'</span>';}
function fmtDate(s){return s?s.substring(0,10):'—';}
function fmtAmt(n,cur){return (cur||'USD')+' '+parseFloat(n||0).toFixed(2);}
function log(msg,cls){
  var el=$('#sync-log');
  el.show().append('<div class="'+(cls||'')+'">'+escH(msg)+'</div>');
  el.scrollTop(el[0].scrollHeight);
}
function setProgress(pct,text){
  $('#sync-prog-fill').css('width',pct+'%');
  $('#sync-prog-text').text(text);
}
function renderPager(el,cur,total,key){
  var h='<button '+(cur<=1?'disabled':'')+' data-key="'+key+'" data-page="'+(cur-1)+'">‹ Prev</button>';
  h+='<span style="color:#555">Page '+cur+' / '+total+'</span>';
  h+='<button '+(cur>=total?'disabled':'')+' data-key="'+key+'" data-page="'+(cur+1)+'">Next ›</button>';
  el.html(h);
}

// ── Load data counts for sync bar ───────────────────────────────────────
function loadSyncCounts(){
  // Manager Pro count
  $.post(ajaxUrl,{action:'sgc_iip_get_manager_inquiries',nonce:nonce,page:1,per_page:1},function(r){
    if(r.success){
      var total=r.data.total||0, synced=r.data.synced||0, pending=total-synced;
      $('#src-mp-count').text(total);
      $('#src-mp-pending').text(pending>0?pending+' to sync':'All synced ✓').css('color',pending>0?'#fbbf24':'#4ade80');
    } else {
      $('#src-mp-count').text('N/A');
      $('#src-mp-pending').text('Not connected').css('color','#9ca3af');
    }
    checkSyncReady();
  }).fail(function(){ $('#src-mp-count').text('N/A'); checkSyncReady(); });

  // WooCommerce count
  $.post(ajaxUrl,{action:'sgc_iip_get_wc_orders',nonce:nonce,per_page:1},function(r){
    if(r.success){
      var orders=r.data.orders||[], total=r.data.total||orders.length;
      var synced=orders.filter(function(o){return o.already_synced;}).length;
      // For accurate count do a bigger fetch
      $.post(ajaxUrl,{action:'sgc_iip_get_wc_orders',nonce:nonce,per_page:200},function(r2){
        if(r2.success){
          var all=r2.data.orders||[];
          var tot=all.length, syn=all.filter(function(o){return o.already_synced;}).length, pen=tot-syn;
          $('#src-wc-count').text(tot);
          $('#src-wc-pending').text(pen>0?pen+' to sync':'All synced ✓').css('color',pen>0?'#fbbf24':'#4ade80');
          checkSyncReady();
        }
      });
    } else {
      $('#src-wc-count').text('N/A');
      $('#src-wc-pending').text('WC not active').css('color','#9ca3af');
      checkSyncReady();
    }
  }).fail(function(){ $('#src-wc-count').text('N/A'); checkSyncReady(); });

  // IIP native count
  $.post(ajaxUrl,{action:'sgc_iip_get_inquiries',nonce:nonce,page:1,per_page:1},function(r){
    if(r.success) $('#src-iip-count').text(r.data.total||0);
  });
}

function checkSyncReady(){
  var mpPend=$('#src-mp-pending').text(), wcPend=$('#src-wc-pending').text();
  var hasPending=(mpPend.indexOf('to sync')>=0)||(wcPend.indexOf('to sync')>=0);
  // Always enable button — never block user
  $('#btn-full-sync').prop('disabled',false);
  if(hasPending){
    $('#sync-btn-text').text('⚡ Sync All Data Now');
    setProgress(0,'Pending data found — click to import all into Invoice Pro');
  } else {
    $('#sync-btn-text').text('⚡ Sync All Data');
    setProgress(0,'Ready to sync all sources into Invoice Pro');
  }
}

// ── ONE-CLICK FULL SYNC ─────────────────────────────────────────────────
$('#btn-full-sync').on('click',function(){
  var btn=$(this);
  btn.prop('disabled',true);
  $('#sync-btn-text').text('Syncing…');
  $('#sync-log').show().html('');
  setProgress(0,'Starting sync…');

  // Step 1: Sync Manager Pro
  log('Step 1/2: Syncing Manager Pro inquiries…');
  setProgress(10,'Syncing Manager Pro inquiries…');

  $.post(ajaxUrl,{action:'sgc_iip_sync_from_manager',nonce:nonce,mode:'all'},function(r){
    if(r.success){
      var d=r.data;
      log('✓ Manager Pro: '+d.synced+' imported, '+d.skipped+' already existed','ok');
      setProgress(50,'Manager Pro done. Syncing WooCommerce orders…');
    } else {
      log('⚠ Manager Pro: '+(r.data||'not available'),'warn');
      setProgress(50,'Manager Pro skipped. Syncing WooCommerce…');
    }

    // Step 2: Sync WooCommerce
    log('Step 2/2: Syncing WooCommerce orders…');
    $.post(ajaxUrl,{action:'sgc_iip_sync_from_wc',nonce:nonce,mode:'all'},function(r2){
      if(r2.success){
        var d2=r2.data;
        log('✓ WooCommerce: '+d2.synced+' imported, '+d2.skipped+' already existed','ok');
      } else {
        log('⚠ WooCommerce: '+(r2.data||'not available or no orders'),'warn');
      }
      setProgress(100,'Sync complete! Refreshing documents…');
      log('✓ All done. Refreshing lists…','ok');
      $('#sync-btn-text').text('Re-sync All');
      btn.prop('disabled',false);
      // Refresh counts + lists
      setTimeout(function(){ loadSyncCounts(); loadAll(); }, 600);
    }).fail(function(){ log('✗ WooCommerce request failed','err'); setProgress(100,'Done (with errors)'); btn.prop('disabled',false); $('#sync-btn-text').text('Re-sync All'); });

  }).fail(function(){ log('✗ Manager Pro request failed','err'); setProgress(50,'Continuing with WooCommerce…'); });
});

// ── Load Inquiries ───────────────────────────────────────────────────────
function loadInquiries(){
  $('#inq-body').html('<tr><td colspan="10" class="docs-empty">Loading…</td></tr>');
  $.post(ajaxUrl,{action:'sgc_iip_get_inquiries',nonce:nonce,search:search,status:status,country:country,page:pages.inq,per_page:20},function(r){
    if(!r.success){$('#inq-body').html('<tr><td colspan="10" class="docs-empty">Error loading</td></tr>');return;}
    var rows=r.data.rows||r.data.inquiries||[], total=r.data.total||0, pgs=Math.ceil(total/20)||1;
    $('#inq-count').text(total+' total'); $('#inq-pages-info').text(rows.length+' on this page');
    if(!rows.length){$('#inq-body').html('<tr><td colspan="10" class="docs-empty">No inquiries found — click "Sync All" above to import</td></tr>');$('#inq-pager').html('');return;}
    var html='';
    rows.forEach(function(d){
      html+='<tr><td style="color:#9ca3af;font-size:11px">'+d.id+'</td>'
        +'<td style="font-family:monospace;font-size:10px;color:#1d4ed8">'+escH(d.inq_number||'—')+'</td>'
        +'<td><strong>'+escH(d.name)+'</strong><br><span style="font-size:10px;color:#888">'+escH(d.email)+'</span></td>'
        +'<td>'+escH(d.country||'—')+'</td>'
        +'<td style="max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px" title="'+escH(d.product||'')+'">'+escH((d.product||d.raw_message||'—').substring(0,40))+'</td>'
        +'<td><span style="font-size:9px;background:#f3f4f6;padding:1px 5px;border-radius:3px">'+escH(d.source_type||'quote')+'</span></td>'
        +'<td>'+badge(d.status)+'</td>'
        +'<td style="font-family:monospace;font-size:10px;color:#6d28d9">'+escH(d.pi_number||d.latest_pi||'—')+'</td>'
        +'<td style="font-size:10px;color:#888">'+fmtDate(d.created_at)+'</td>'
        +'<td><div class="docs-act"><a href="'+piBase+'&inquiry_id='+d.id+'" class="docs-btn db-open">📄 PI</a>'
        +'<a href="'+plBase+'&inquiry_id='+d.id+'" class="docs-btn db-open">📦 PL</a></div></td></tr>';
    });
    $('#inq-body').html(html);
    renderPager($('#inq-pager'),pages.inq,pgs,'inq');
  });
}

// ── Load PIs ─────────────────────────────────────────────────────────────
function loadPIs(){
  $('#pi-body').html('<tr><td colspan="10" class="docs-empty">Loading…</td></tr>');
  $.post(ajaxUrl,{action:'sgc_iip_list_all_pi',nonce:nonce,search:search,status:status,country:country,page:pages.pi,per_page:20},function(r){
    if(!r.success){$('#pi-body').html('<tr><td colspan="10" class="docs-empty">Error loading</td></tr>');return;}
    var rows=r.data.rows||[], total=r.data.total||0, pgs=r.data.pages||1;
    $('#pi-count').text(total+' total'); $('#pi-pages-info').text(rows.length+' on this page');
    if(!rows.length){$('#pi-body').html('<tr><td colspan="10" class="docs-empty">No PIs found</td></tr>');$('#pi-pager').html('');return;}
    var html='';
    rows.forEach(function(d){
      var hasPdf=d.has_pdf===true||d.has_pdf===1||d.has_pdf==='1';
      var bal=parseFloat(d.balance||0);
      html+='<tr><td style="font-family:monospace;font-weight:600;color:#1d4ed8">'+escH(d.pi_number)+'</td>'
        +'<td><strong>'+escH(d.buyer_name)+'</strong><br><span style="font-size:10px;color:#888">'+escH(d.buyer_email)+'</span></td>'
        +'<td>'+escH(d.buyer_company||'—')+'</td><td>'+escH(d.buyer_country||'—')+'</td>'
        +'<td style="font-weight:600">'+fmtAmt(d.grand_total,d.currency)+'</td>'
        +'<td style="color:#059669">'+fmtAmt(d.total_paid||0,d.currency)+'</td>'
        +'<td style="color:'+(bal>0?'#dc2626':'#059669')+'">'+fmtAmt(bal,d.currency)+'</td>'
        +'<td>'+badge(d.status)+'</td>'
        +'<td style="font-size:10px;color:#888">'+fmtDate(d.sent_at||d.created_at)+'</td>'
        +'<td><div class="docs-act"><a href="'+piBase+'&pi_id='+d.id+'" class="docs-btn db-open">✏ Open</a>'
        +(hasPdf?'<a href="'+ajaxUrl+'?action=sgc_iip_generate_pdf&pi_id='+d.id+'&nonce='+nonce+'" class="docs-btn db-pdf" target="_blank">📄 PDF</a>':'')
        +'</div></td></tr>';
    });
    $('#pi-body').html(html);
    renderPager($('#pi-pager'),pages.pi,pgs,'pi');
  });
}

// ── Load Packlists ────────────────────────────────────────────────────────
function loadPLs(){
  $('#pl-body').html('<tr><td colspan="7" class="docs-empty">Loading…</td></tr>');
  $.post(ajaxUrl,{action:'sgc_iip_list_all_pl',nonce:nonce,search:search,page:pages.pl,per_page:20},function(r){
    if(!r.success){$('#pl-body').html('<tr><td colspan="7" class="docs-empty">Error loading</td></tr>');return;}
    var rows=r.data.rows||[], total=r.data.total||0, pgs=r.data.pages||1;
    $('#pl-count').text(total+' total');
    if(!rows.length){$('#pl-body').html('<tr><td colspan="7" class="docs-empty">No packing lists found</td></tr>');$('#pl-pager').html('');return;}
    var html='';
    rows.forEach(function(d){
      var hasPdf=d.has_pdf===true||d.has_pdf===1||d.has_pdf==='1';
      html+='<tr><td style="font-family:monospace;font-weight:600;color:#059669">'+escH(d.pl_number||'PL-'+d.id)+'</td>'
        +'<td style="font-family:monospace;font-size:10px;color:#6d28d9">'+escH(d.pi_number||'—')+'</td>'
        +'<td>'+escH(d.buyer_name||'—')+'</td><td>'+escH(d.buyer_company||'—')+'</td>'
        +'<td>'+escH(d.buyer_country||'—')+'</td>'
        +'<td style="font-size:10px;color:#888">'+fmtDate(d.created_at)+'</td>'
        +'<td><div class="docs-act"><a href="'+plBase+'&pl_id='+d.id+'" class="docs-btn db-open">✏ Open</a>'
        +(hasPdf?'<a href="'+ajaxUrl+'?action=sgc_iip_generate_pl_pdf&pl_id='+d.id+'&nonce='+nonce+'" class="docs-btn db-pdf" target="_blank">📄 PDF</a>':'')
        +'</div></td></tr>';
    });
    $('#pl-body').html(html);
    renderPager($('#pl-pager'),pages.pl,pgs,'pl');
  });
}

function loadAll(){ loadInquiries(); loadPIs(); loadPLs(); }

// Filters
var searchTimer;
$('#docs-global-search').on('input',function(){ clearTimeout(searchTimer); var v=$(this).val(); searchTimer=setTimeout(function(){ search=v; pages={inq:1,pi:1,pl:1}; loadAll(); },400); });
$('#docs-status-filter').on('change',function(){ status=$(this).val(); pages={inq:1,pi:1,pl:1}; loadAll(); });
$('#docs-country-filter').on('change',function(){ country=$(this).val(); pages={inq:1,pi:1,pl:1}; loadAll(); });
$('#btn-docs-reload').on('click',function(){ pages={inq:1,pi:1,pl:1}; loadAll(); });

// Pager
$(document).on('click','.docs-pager button:not(:disabled)',function(){
  var key=$(this).data('key'), pg=parseInt($(this).data('page'));
  pages[key]=pg;
  if(key==='inq') loadInquiries(); else if(key==='pi') loadPIs(); else loadPLs();
});

$(function(){
  // Button always enabled immediately
  $('#btn-full-sync').prop('disabled',false);
  // Load counts + data in background
  loadSyncCounts();
  loadAll();
});
})(jQuery);
</script>
