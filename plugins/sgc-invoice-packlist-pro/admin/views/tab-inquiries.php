<?php defined('ABSPATH')||exit; ?>
<div id="iip-inquiries-wrap">

<!-- ── TOP ACTION BAR ── -->
<div class="iip-filter-bar" style="background:#1a1a2e;border-color:#1a1a2e;gap:10px;flex-wrap:wrap">
  <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
    <span style="color:#9090aa;font-size:11px;font-weight:600">MANAGER PRO:</span>
    <button class="iip-btn" id="btn-preview-mp" style="background:#2a2a4e;border-color:#3a3a6e;color:#fff;font-size:12px">👁 Preview Inquiries</button>
    <button class="iip-btn success" id="btn-sync-all" style="font-size:12px">🔄 Sync ALL to IIP</button>
    <span style="color:#5050aa;margin:0 4px">|</span>
    <span style="color:#9090aa;font-size:11px;font-weight:600">WOOCOMMERCE:</span>
    <button class="iip-btn" id="btn-preview-wc" style="background:#7c3aed;border-color:#7c3aed;color:#fff;font-size:12px">🛒 WC Orders</button>
    <button class="iip-btn" id="btn-sync-wc-all" style="background:#059669;border-color:#059669;color:#fff;font-size:12px">🔄 Sync ALL WC</button>
    <span id="sync-result" style="color:#7dffc4;font-size:11px"></span>
  </div>
  <div style="margin-left:auto">
    <a href="<?php echo esc_url(admin_url('admin.php?page=sgc-iip&tab=inquiries&action=add')); ?>" class="iip-btn primary" style="font-size:12px">+ Add Manual</a>
  </div>
</div>

<!-- ── MANAGER PRO PREVIEW PANEL ── -->
<div id="mp-preview-panel" style="display:none">
<div class="iip-box" style="padding:0;margin-bottom:14px">
  <div style="padding:12px 16px;background:#f8f9fa;border-bottom:1px solid #e0e0e0;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
    <div>
      <strong style="font-size:13px">📋 Manager Pro — wp_sgclabs_inquiries</strong>
      <span id="mp-total-badge" style="background:#1a1a2e;color:#fff;padding:1px 8px;border-radius:8px;font-size:11px;margin-left:8px"></span>
    </div>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <button class="iip-btn sm success" id="btn-sync-selected">✅ Sync Selected</button>
      <button class="iip-btn sm primary" id="btn-sync-all-mp">Sync ALL</button>
      <button class="iip-btn sm" onclick="jQuery('#mp-preview-panel').slideUp()">✕ Close</button>
    </div>
  </div>
  <div style="overflow-x:auto">
  <table style="width:100%;border-collapse:collapse;font-size:12px">
    <thead style="background:#f5f7fa">
      <tr>
        <th style="padding:8px;border:1px solid #e0e0e0;width:30px"><input type="checkbox" id="mp-check-all"></th>
        <th style="padding:8px;border:1px solid #e0e0e0">#</th>
        <th style="padding:8px;border:1px solid #e0e0e0">Name</th>
        <th style="padding:8px;border:1px solid #e0e0e0">Email</th>
        <th style="padding:8px;border:1px solid #e0e0e0">Phone</th>
        <th style="padding:8px;border:1px solid #e0e0e0">Country</th>
        <th style="padding:8px;border:1px solid #e0e0e0">Product</th>
        <th style="padding:8px;border:1px solid #e0e0e0">Qty</th>
        <th style="padding:8px;border:1px solid #e0e0e0">Date</th>
        <th style="padding:8px;border:1px solid #e0e0e0">MP Status</th>
        <th style="padding:8px;border:1px solid #e0e0e0">Action</th>
      </tr>
    </thead>
    <tbody id="mp-inquiry-rows">
      <tr><td colspan="11" style="padding:20px;text-align:center;color:#aaa">Click "Preview Inquiries" to load…</td></tr>
    </tbody>
  </table>
  </div>
</div>
</div>

<!-- ── WC PREVIEW PANEL ── -->
<div id="wc-preview-panel" style="display:none">
<div class="iip-box" style="padding:0;margin-bottom:14px;border-color:#7c3aed">
  <div style="padding:12px 16px;background:#f5f3ff;border-bottom:1px solid #e0e0e0;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
    <div><strong style="font-size:13px">🛒 WooCommerce Orders</strong>
    <span id="wc-total-badge" style="background:#7c3aed;color:#fff;padding:1px 8px;border-radius:8px;font-size:11px;margin-left:8px"></span></div>
    <div style="display:flex;gap:6px">
      <button class="iip-btn sm success" id="btn-wc-sync-selected">✅ Sync Selected</button>
      <button class="iip-btn sm" style="background:#7c3aed;color:#fff;border-color:#7c3aed" id="btn-wc-sync-all-2">Sync ALL</button>
      <button class="iip-btn sm" onclick="jQuery('#wc-preview-panel').slideUp()">✕</button>
    </div>
  </div>
  <div style="overflow-x:auto">
  <table style="width:100%;border-collapse:collapse;font-size:12px">
    <thead style="background:#f5f3ff">
      <tr>
        <th style="padding:8px;border:1px solid #e0e0e0;width:30px"><input type="checkbox" id="wc-check-all"></th>
        <th style="padding:8px;border:1px solid #e0e0e0">Order#</th>
        <th style="padding:8px;border:1px solid #e0e0e0">Name</th>
        <th style="padding:8px;border:1px solid #e0e0e0">Email</th>
        <th style="padding:8px;border:1px solid #e0e0e0">Country</th>
        <th style="padding:8px;border:1px solid #e0e0e0">Products</th>
        <th style="padding:8px;border:1px solid #e0e0e0">Total</th>
        <th style="padding:8px;border:1px solid #e0e0e0">Status</th>
        <th style="padding:8px;border:1px solid #e0e0e0">Date</th>
        <th style="padding:8px;border:1px solid #e0e0e0">Action</th>
      </tr>
    </thead>
    <tbody id="wc-order-rows">
      <tr><td colspan="10" style="padding:20px;text-align:center;color:#aaa">Click "WC Orders" to load…</td></tr>
    </tbody>
  </table>
  </div>
</div>
</div>

<!-- ── FILTERS ── -->
<div class="iip-filter-bar">
  <input type="text" id="iip-search" class="iip-input" placeholder="Search name, email, company…" style="width:200px">
  <select id="iip-filter-status" class="iip-select">
    <option value="">All Status</option>
    <?php foreach(SGC_IIP_Pipeline::STAGES as $k=>$s) echo '<option value="'.esc_attr($k).'">'.esc_html($s['icon'].' '.$s['label']).'</option>'; ?>
  </select>
  <select id="iip-filter-source" class="iip-select">
    <option value="">All Sources</option>
    <option value="manager_pro">Manager Pro</option>
    <option value="contact">Contact Form</option>
    <option value="quote">Get a Quote</option>
    <option value="b2b">B2B Cart</option>
    <option value="manual">Manual</option>
  </select>
  <button class="iip-btn primary" id="iip-filter-btn">Filter</button>
  <button class="iip-btn" id="iip-reset-btn">Reset</button>
</div>

<!-- ── KPI ── -->
<div class="iip-kpi-row">
  <?php foreach(['new'=>'🔵 New','pi_sent'=>'📄 PI Sent','accepted'=>'✅ Accepted','won'=>'🏆 Won','lost'=>'❌ Lost'] as $k=>$l): ?>
  <div class="iip-kpi" onclick="filterByStatus('<?php echo esc_attr($k); ?>')">
    <div class="iip-kpi-num" id="kpi-<?php echo esc_attr($k); ?>">—</div>
    <div class="iip-kpi-label"><?php echo esc_html($l); ?></div>
  </div>
  <?php endforeach; ?>
  <div class="iip-kpi"><div class="iip-kpi-num" id="kpi-conversion">—%</div><div class="iip-kpi-label">Conversion</div></div>
</div>

<!-- ── List ── -->
<div class="iip-box" style="padding:0">
  <div id="iip-inquiry-list"><div style="padding:40px;text-align:center;color:#aaa">Loading…</div></div>
  <div class="iip-pagination" id="iip-pagination"></div>
</div>

<!-- Side Panel -->
<div id="iip-detail-panel" class="iip-side-panel" style="display:none"></div>
<div id="iip-panel-overlay" class="iip-overlay" style="display:none" onclick="closeSidePanel()"></div>

<!-- Add Manual Modal -->
<div class="iip-modal" id="modal-add-manual" style="display:none">
  <div class="iip-modal-inner">
    <div class="iip-modal-hdr"><h3>Add Manual Inquiry</h3><button onclick="closeModal('modal-add-manual')">✕</button></div>
    <div class="iip-modal-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="iip-fg"><label>Name *</label><input type="text" id="m-name" class="iip-input" style="width:100%"></div>
        <div class="iip-fg"><label>Email *</label><input type="email" id="m-email" class="iip-input" style="width:100%"></div>
        <div class="iip-fg"><label>Phone</label><input type="text" id="m-phone" class="iip-input" style="width:100%"></div>
        <div class="iip-fg"><label>Company</label><input type="text" id="m-company" class="iip-input" style="width:100%"></div>
        <div class="iip-fg">
          <label>Country</label>
          <input type="text" id="m-country" class="iip-input" style="width:100%"
                 list="sgc-iip-country-list" autocomplete="off"
                 placeholder="Type to search country…">
          <datalist id="sgc-iip-country-list">
            <option value="Afghanistan"><option value="Albania"><option value="Algeria">
            <option value="Andorra"><option value="Angola"><option value="Antigua and Barbuda">
            <option value="Argentina"><option value="Armenia"><option value="Australia">
            <option value="Austria"><option value="Azerbaijan"><option value="Bahamas">
            <option value="Bahrain"><option value="Bangladesh"><option value="Barbados">
            <option value="Belarus"><option value="Belgium"><option value="Belize">
            <option value="Benin"><option value="Bhutan"><option value="Bolivia">
            <option value="Bosnia and Herzegovina"><option value="Botswana"><option value="Brazil">
            <option value="Brunei"><option value="Bulgaria"><option value="Burkina Faso">
            <option value="Burundi"><option value="Cabo Verde"><option value="Cambodia">
            <option value="Cameroon"><option value="Canada"><option value="Central African Republic">
            <option value="Chad"><option value="Chile"><option value="China">
            <option value="Colombia"><option value="Comoros"><option value="Congo">
            <option value="Costa Rica"><option value="Croatia"><option value="Cuba">
            <option value="Cyprus"><option value="Czech Republic"><option value="Denmark">
            <option value="Djibouti"><option value="Dominica"><option value="Dominican Republic">
            <option value="Ecuador"><option value="Egypt"><option value="El Salvador">
            <option value="Equatorial Guinea"><option value="Eritrea"><option value="Estonia">
            <option value="Eswatini"><option value="Ethiopia"><option value="Fiji">
            <option value="Finland"><option value="France"><option value="Gabon">
            <option value="Gambia"><option value="Georgia"><option value="Germany">
            <option value="Ghana"><option value="Greece"><option value="Grenada">
            <option value="Guatemala"><option value="Guinea"><option value="Guinea-Bissau">
            <option value="Guyana"><option value="Haiti"><option value="Honduras">
            <option value="Hungary"><option value="Iceland"><option value="India">
            <option value="Indonesia"><option value="Iran"><option value="Iraq">
            <option value="Ireland"><option value="Israel"><option value="Italy">
            <option value="Jamaica"><option value="Japan"><option value="Jordan">
            <option value="Kazakhstan"><option value="Kenya"><option value="Kiribati">
            <option value="Kuwait"><option value="Kyrgyzstan"><option value="Laos">
            <option value="Latvia"><option value="Lebanon"><option value="Lesotho">
            <option value="Liberia"><option value="Libya"><option value="Liechtenstein">
            <option value="Lithuania"><option value="Luxembourg"><option value="Madagascar">
            <option value="Malawi"><option value="Malaysia"><option value="Maldives">
            <option value="Mali"><option value="Malta"><option value="Marshall Islands">
            <option value="Mauritania"><option value="Mauritius"><option value="Mexico">
            <option value="Micronesia"><option value="Moldova"><option value="Monaco">
            <option value="Mongolia"><option value="Montenegro"><option value="Morocco">
            <option value="Mozambique"><option value="Myanmar"><option value="Namibia">
            <option value="Nauru"><option value="Nepal"><option value="Netherlands">
            <option value="New Zealand"><option value="Nicaragua"><option value="Niger">
            <option value="Nigeria"><option value="North Korea"><option value="North Macedonia">
            <option value="Norway"><option value="Oman"><option value="Pakistan">
            <option value="Palau"><option value="Palestine"><option value="Panama">
            <option value="Papua New Guinea"><option value="Paraguay"><option value="Peru">
            <option value="Philippines"><option value="Poland"><option value="Portugal">
            <option value="Qatar"><option value="Romania"><option value="Russia">
            <option value="Rwanda"><option value="Saint Kitts and Nevis"><option value="Saint Lucia">
            <option value="Saint Vincent and the Grenadines"><option value="Samoa">
            <option value="San Marino"><option value="Sao Tome and Principe">
            <option value="Saudi Arabia"><option value="Senegal"><option value="Serbia">
            <option value="Seychelles"><option value="Sierra Leone"><option value="Singapore">
            <option value="Slovakia"><option value="Slovenia"><option value="Solomon Islands">
            <option value="Somalia"><option value="South Africa"><option value="South Korea">
            <option value="South Sudan"><option value="Spain"><option value="Sri Lanka">
            <option value="Sudan"><option value="Suriname"><option value="Sweden">
            <option value="Switzerland"><option value="Syria"><option value="Taiwan">
            <option value="Tajikistan"><option value="Tanzania"><option value="Thailand">
            <option value="Timor-Leste"><option value="Togo"><option value="Tonga">
            <option value="Trinidad and Tobago"><option value="Tunisia"><option value="Turkey">
            <option value="Turkmenistan"><option value="Tuvalu"><option value="Uganda">
            <option value="Ukraine"><option value="United Arab Emirates">
            <option value="United Kingdom"><option value="United States">
            <option value="Uruguay"><option value="Uzbekistan"><option value="Vanuatu">
            <option value="Vatican City"><option value="Venezuela"><option value="Vietnam">
            <option value="Yemen"><option value="Zambia"><option value="Zimbabwe">
          </datalist>
        </div>
        <div class="iip-fg"><label>Incoterm</label><select id="m-incoterm" class="iip-select" style="width:100%"><option>FOB</option><option>CIF</option><option>EXW</option><option>DDP</option></select></div>
        <div class="iip-fg" style="grid-column:span 2"><label>Product</label><input type="text" id="m-product" class="iip-input" style="width:100%"></div>
        <div class="iip-fg"><label>Quantity</label><input type="text" id="m-qty" class="iip-input" style="width:100%"></div>
        <div class="iip-fg" style="grid-column:span 2"><label>Message</label><textarea id="m-message" class="iip-input" rows="3" style="width:100%"></textarea></div>
      </div>
    </div>
    <div class="iip-modal-ftr">
      <button class="iip-btn" onclick="closeModal('modal-add-manual')">Cancel</button>
      <button class="iip-btn primary" id="btn-save-manual">Save</button>
    </div>
  </div>
</div>

<!-- One-Click Result Modal -->
<div class="iip-modal" id="modal-pi-result" style="display:none">
  <div class="iip-modal-inner" style="max-width:460px">
    <div class="iip-modal-hdr" style="background:#2e7d32"><h3>⚡ PI Sent!</h3><button onclick="closeModal('modal-pi-result')">✕</button></div>
    <div class="iip-modal-body" id="modal-pi-result-body" style="padding:24px"></div>
    <div class="iip-modal-ftr"><button class="iip-btn primary" onclick="closeModal('modal-pi-result');loadInquiries()">✓ Done</button></div>
  </div>
</div>

<script>
var iipPage=1,iipFilters={};
function escH(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

/* ── Load IIP Inquiries ── */
function loadInquiries(page){
  iipPage=page||1;
  jQuery('#iip-inquiry-list').html('<div style="padding:30px;text-align:center"><div class="iip-spin"></div></div>');
  jQuery.post(SGCIIP.ajaxUrl,Object.assign({action:'sgc_iip_get_inquiries',nonce:SGCIIP.nonce,page:iipPage},iipFilters),function(r){
    if(!r.success)return;
    var d=r.data,html='';
    var stages=<?php
      $sd=[];
      foreach(SGC_IIP_Pipeline::STAGES as $k=>$s){$sd[$k]=['color'=>$s['color'],'text'=>$s['text'],'icon'=>$s['icon'],'label'=>$s['label']];}
      echo wp_json_encode($sd);
    ?>;

    if(!d.inquiries.length){
      html='<div style="padding:50px;text-align:center;color:#aaa">'
        +'<div style="font-size:36px;margin-bottom:12px">📭</div>'
        +'<div style="font-size:14px;margin-bottom:12px">No inquiries found in IIP.</div>'
        +'<button class="iip-btn success" onclick="jQuery(\'#btn-sync-all\').click()">🔄 Sync from Manager Pro</button>'
        +'</div>';
    } else {
      d.inquiries.forEach(function(inq){
        var st=stages[inq.status]||{color:'#f5f5f5',text:'#333',icon:'●',label:inq.status};
        var health=parseInt(inq.health_score)||0;
        var hc=health>=70?'#2e7d32':health>=40?'#f57f17':'#c62828';
        var products=(inq.product_details||[]).map(function(p){return p.name||(p.product||'');}).filter(Boolean).join(', ')||'—';
        var returning=inq.returning_buyer=='1'?'<span style="background:#E3F2FD;color:#0D47A1;padding:1px 5px;border-radius:6px;font-size:9px;font-weight:700;margin-left:4px">RETURNING</span>':'';
        var mpBadge=inq.source_type==='manager_pro'?'<span style="background:#7c3aed;color:#fff;padding:1px 5px;border-radius:6px;font-size:9px;font-weight:700;margin-left:4px">MP</span>':'';
        var piTag=inq.latest_pi?'<span style="background:#e8f5e9;color:#1b5e20;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:600">'+escH(inq.latest_pi)+'</span>':'';

        var piBtn='';
        if(['pi_sent','accepted','advance','won'].indexOf(inq.status)>=0){
          piBtn='<span style="font-size:10px;color:#2e7d32;font-weight:700;background:#e8f5e9;padding:3px 8px;border-radius:4px">✅ PI Sent</span>';
        } else {
          piBtn=''; // AI PI+Send removed — use Smart Extract inside inquiry
        }

        html+='<div class="iip-inq-row" onclick="openDetail('+inq.id+')">'
          +'<div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0">'
          +'<div class="iip-avatar">'+escH(inq.name).charAt(0).toUpperCase()+'</div>'
          +'<div style="min-width:0;flex:1">'
          +'<div style="font-weight:600;font-size:13px">'+escH(inq.name)+returning+mpBadge+'</div>'
          +'<div style="font-size:11px;color:#888">'+escH(inq.email)+(inq.company?' · '+escH(inq.company):'')+'</div>'
          +'<div style="font-size:11px;color:#185FA5;margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:260px">'+escH(products)+'</div>'
          +'</div></div>'
          +'<div style="display:flex;align-items:center;gap:8px;flex-shrink:0;flex-wrap:wrap">'
          +'<span style="font-size:11px;color:#888">🌍 '+escH(inq.country||'—')+'</span>'
          +'<span style="background:'+st.color+';color:'+st.text+';padding:2px 8px;border-radius:9px;font-size:10px;font-weight:700">'+st.icon+' '+st.label+'</span>'
          +'<div style="width:34px;height:34px;border-radius:50%;background:#f0f0f0;border:2px solid '+hc+';display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:'+hc+'">'+health+'</div>'
          +piTag
          +piBtn
          +'<span style="font-size:10px;color:#ccc">'+escH((inq.created_at||'').substring(0,10))+'</span>'
          +'</div></div>';
      });
    }
    jQuery('#iip-inquiry-list').html(html);
    // Pagination
    var pg='';
    if(d.pages>1){
      if(iipPage>1) pg+='<button class="iip-btn" onclick="loadInquiries('+(iipPage-1)+')">‹ Prev</button>';
      pg+='<span style="font-size:12px;color:#888">Page '+iipPage+' of '+d.pages+' ('+d.total+' total)</span>';
      if(iipPage<d.pages) pg+='<button class="iip-btn" onclick="loadInquiries('+(iipPage+1)+')">Next ›</button>';
    }
    jQuery('#iip-pagination').html(pg);
  });
}

function filterByStatus(s){iipFilters.status=s;loadInquiries(1);}

/* ── ⚡ ONE-CLICK PI + SEND ── */
/* oneClickPI moved to app.js */

/* ── Manager Pro Sync ── */
function loadMPInquiries(){
  jQuery('#mp-inquiry-rows').html('<tr><td colspan="11" style="padding:20px;text-align:center"><div class="iip-spin"></div> Loading…</td></tr>');
  jQuery('#mp-preview-panel').slideDown(200);
  jQuery.post(SGCIIP.ajaxUrl,{action:'sgc_iip_get_manager_inquiries',nonce:SGCIIP.nonce,limit:100},function(r){
    if(!r.success){
      jQuery('#mp-inquiry-rows').html('<tr><td colspan="11" style="padding:16px;color:#c62828;text-align:center">❌ '+escH(r.data)+'</td></tr>');
      return;
    }
    var rows=r.data.rows;
    jQuery('#mp-total-badge').text(r.data.total+' total');
    if(!rows.length){
      jQuery('#mp-inquiry-rows').html('<tr><td colspan="11" style="padding:20px;text-align:center;color:#aaa">No inquiries in Manager Pro.</td></tr>');
      return;
    }
    var html='';
    rows.forEach(function(row){
      var synced=row.already_synced;
      html+='<tr style="'+(synced?'opacity:.55;background:#f9f9f9;':'row.status==="new"?"background:#fffde7;":"background:#fff;"')+(row.status==='new'&&!synced?'background:#fffde7':'background:#fff')+'">'
        +'<td style="padding:6px 8px;border:1px solid #e0e0e0;text-align:center">'+(synced?'<span title="Already in IIP" style="color:#2e7d32;font-size:14px">✅</span>':'<input type="checkbox" class="mp-row-check" value="'+row.id+'">')+'</td>'
        +'<td style="padding:6px 8px;border:1px solid #e0e0e0;font-weight:600">'+row.id+'</td>'
        +'<td style="padding:6px 8px;border:1px solid #e0e0e0"><strong>'+escH(row.name)+'</strong></td>'
        +'<td style="padding:6px 8px;border:1px solid #e0e0e0;font-size:11px">'+escH(row.email)+'</td>'
        +'<td style="padding:6px 8px;border:1px solid #e0e0e0;font-size:11px">'+escH(row.phone||'—')+'</td>'
        +'<td style="padding:6px 8px;border:1px solid #e0e0e0">'+escH(row.country||'—')+'</td>'
        +'<td style="padding:6px 8px;border:1px solid #e0e0e0;font-weight:700;color:#1a1a2e;max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+escH(row.product||'—')+'</td>'
        +'<td style="padding:6px 8px;border:1px solid #e0e0e0;font-size:11px">'+escH(row.qty||'—')+'</td>'
        +'<td style="padding:6px 8px;border:1px solid #e0e0e0;font-size:10px;white-space:nowrap">'+escH((row.created_at||'').substring(0,10))+'</td>'
        +'<td style="padding:6px 8px;border:1px solid #e0e0e0">'+(row.status==='new'?'<span style="background:#fff3cd;color:#856404;padding:1px 6px;border-radius:8px;font-size:10px;font-weight:600">New</span>':'<span style="background:#e8f5e9;color:#1b5e20;padding:1px 6px;border-radius:8px;font-size:10px">Read</span>')+'</td>'
        +'<td style="padding:6px 8px;border:1px solid #e0e0e0">'+(synced?'<span style="color:#2e7d32;font-size:11px;font-weight:600">IIP #'+escH(row.iip_id)+'</span>':'<button class="iip-btn sm primary" onclick="syncOne('+row.id+',this)">Import →</button>')+'</td>'
        +'</tr>';
    });
    jQuery('#mp-inquiry-rows').html(html);
  });
}

function syncRows(mode,ids){
  var data={action:'sgc_iip_sync_from_manager',nonce:SGCIIP.nonce,mode:mode};
  if(ids&&ids.length) data['ids[]']=ids;
  else if(ids) data.ids=ids;
  jQuery('#sync-result').text('⏳ Syncing…');
  jQuery.post(SGCIIP.ajaxUrl,data,function(r){
    if(r.success){jQuery('#sync-result').text(r.data.message);loadInquiries(1);loadStats();setTimeout(loadMPInquiries,600);}
    else jQuery('#sync-result').text('❌ '+r.data);
  });
}
function syncOne(id,btn){jQuery(btn).prop('disabled',true).text('⏳…');jQuery.post(SGCIIP.ajaxUrl,{action:'sgc_iip_sync_from_manager',nonce:SGCIIP.nonce,mode:'selected','ids[]':id},function(r){if(r.success){jQuery(btn).replaceWith('<span style="color:#2e7d32;font-weight:600">✅ Done</span>');loadInquiries(1);loadStats();}else jQuery(btn).prop('disabled',false).text('Import →');});}

jQuery(function($){
  loadStats(); loadInquiries(1);

  // Auto-check MP count
  $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_get_manager_inquiries',nonce:SGCIIP.nonce,limit:1},function(r){
    if(r.success){
      var t=r.data.total;
      $('#btn-preview-mp').text('👁 Preview ('+t+' inquiries)');
      $('#btn-sync-all').text('🔄 Sync ALL ('+t+') to IIP');
    }
  });

  $('#btn-preview-mp').on('click',loadMPInquiries);
  $('#btn-preview-wc').on('click',loadWCOrders);
  $('#btn-sync-wc-all').on('click',function(){if(confirm('Sync ALL WooCommerce orders?')) wcSyncRows('all');});
  $('#btn-wc-sync-all-2').on('click',function(){if(confirm('Sync ALL WC orders?')) wcSyncRows('all');});
  $('#wc-check-all').on('change',function(){$('.wc-row-check').prop('checked',$(this).is(':checked'));});
  $('#btn-wc-sync-selected').on('click',function(){
    var ids=$('.wc-row-check:checked').map(function(){return $(this).val();}).get();
    if(!ids.length){iipNotice('Select at least one order','error');return;}
    wcSyncRows('selected',ids);
  });
  $('#btn-sync-all').on('click',function(){if(confirm('Sync ALL Manager Pro inquiries into IIP?\n\nAlready synced ones will be skipped.')) syncRows('all');});
  $('#btn-sync-all-mp').on('click',function(){if(confirm('Sync ALL into IIP?')) syncRows('all');});
  $('#mp-check-all').on('change',function(){$('.mp-row-check').prop('checked',$(this).is(':checked'));});
  $('#btn-sync-selected').on('click',function(){
    var ids=$('.mp-row-check:checked').map(function(){return $(this).val();}).get();
    if(!ids.length){iipNotice('Pehle select karo','error');return;}
    syncRows('selected',ids);
  });
  $('#iip-filter-btn').on('click',function(){iipFilters={search:$('#iip-search').val(),status:$('#iip-filter-status').val(),source_type:$('#iip-filter-source').val()};loadInquiries(1);});
  $('#iip-reset-btn').on('click',function(){iipFilters={};$('#iip-search,#iip-filter-status,#iip-filter-source').val('');loadInquiries(1);});
  $('#btn-save-manual').on('click',function(){
    $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_add_manual_inquiry',nonce:SGCIIP.nonce,name:$('#m-name').val(),email:$('#m-email').val(),phone:$('#m-phone').val(),company:$('#m-company').val(),country:$('#m-country').val(),incoterm:$('#m-incoterm').val(),product:$('#m-product').val(),qty:$('#m-qty').val(),message:$('#m-message').val()},function(r){closeModal('modal-add-manual');if(r.success){iipNotice('✅ Added!');loadInquiries(1);}else iipNotice(r.data,'error');});
  });
  if(window.location.search.indexOf('action=add')>-1) openModal('modal-add-manual');
});

/* ── WooCommerce Sync JS ── */
function loadWCOrders(){
  jQuery('#wc-order-rows').html('<tr><td colspan="10" style="padding:20px;text-align:center"><div class="iip-spin"></div> Loading WC orders…</td></tr>');
  jQuery('#wc-preview-panel').slideDown(200);
  jQuery.post(SGCIIP.ajaxUrl,{action:'sgc_iip_get_wc_orders',nonce:SGCIIP.nonce},function(r){
    if(!r.success){jQuery('#wc-order-rows').html('<tr><td colspan="10" style="padding:16px;color:#c62828;text-align:center">❌ '+escH(r.data)+'</td></tr>');return;}
    jQuery('#wc-total-badge').text(r.data.total+' orders');
    var rows=r.data.orders,html='';
    if(!rows.length){jQuery('#wc-order-rows').html('<tr><td colspan="10" style="padding:20px;text-align:center;color:#aaa">No WC orders found.</td></tr>');return;}
    rows.forEach(function(o){
      var synced=o.already_synced;
      var statusColors={'pending':'#f59e0b','processing':'#3b82f6','on-hold':'#f97316','completed':'#22c55e'};
      var sc=statusColors[o.status]||'#9ca3af';
      html+='<tr style="'+(synced?'opacity:.55;':'')+'">'
        +'<td style="padding:6px 8px;border:1px solid #e0e0e0;text-align:center">'+(synced?'<span style="color:#2e7d32">✅</span>':'<input type="checkbox" class="wc-row-check" value="'+o.id+'">')+'</td>'
        +'<td style="padding:6px 8px;border:1px solid #e0e0e0;font-weight:700;color:#7c3aed">#'+escH(o.order_number)+'</td>'
        +'<td style="padding:6px 8px;border:1px solid #e0e0e0"><strong>'+escH(o.name)+'</strong><br><span style="font-size:10px;color:#888">'+escH(o.company||'')+'</span></td>'
        +'<td style="padding:6px 8px;border:1px solid #e0e0e0;font-size:11px">'+escH(o.email)+'</td>'
        +'<td style="padding:6px 8px;border:1px solid #e0e0e0">'+escH(o.country)+'</td>'
        +'<td style="padding:6px 8px;border:1px solid #e0e0e0;font-size:11px;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+escH(o.products)+'</td>'
        +'<td style="padding:6px 8px;border:1px solid #e0e0e0;font-weight:700;color:#1a1a2e">'+escH(o.currency)+' '+parseFloat(o.total||0).toFixed(2)+'</td>'
        +'<td style="padding:6px 8px;border:1px solid #e0e0e0"><span style="background:'+sc+';color:#fff;padding:1px 6px;border-radius:8px;font-size:10px">'+escH(o.status)+'</span></td>'
        +'<td style="padding:6px 8px;border:1px solid #e0e0e0;font-size:10px">'+escH(o.date)+'</td>'
        +'<td style="padding:6px 8px;border:1px solid #e0e0e0">'+(synced?'<span style="color:#2e7d32;font-size:11px">In IIP</span>':'<button class="iip-btn sm" style="background:#7c3aed;color:#fff;border:none" onclick="wcSyncOne('+o.id+',this)">Import</button>')+'</td>'
        +'</tr>';
    });
    jQuery('#wc-order-rows').html(html);
  });
}
function wcSyncRows(mode,ids){
  var d={action:'sgc_iip_sync_from_wc',nonce:SGCIIP.nonce,mode:mode};
  if(ids&&ids.length) d['ids[]']=ids;
  jQuery('#sync-result').text('⏳ Syncing WC…');
  jQuery.post(SGCIIP.ajaxUrl,d,function(r){
    jQuery('#sync-result').text(r.success?r.data.message:'❌ '+r.data);
    if(r.success){loadInquiries(1);loadStats();setTimeout(loadWCOrders,600);}
  });
}
function wcSyncOne(id,btn){jQuery(btn).prop('disabled',true).text('⏳…');wcSyncRows('selected',[id]);}

function loadStats(){
  jQuery.post(SGCIIP.ajaxUrl,{action:'sgc_iip_dashboard_stats',nonce:SGCIIP.nonce},function(r){
    if(!r.success)return;var d=r.data;
    jQuery('#kpi-new').text(d.new||0);jQuery('#kpi-pi_sent').text(d.pi_sent||0);
    jQuery('#kpi-accepted').text(d.accepted||0);jQuery('#kpi-won').text(d.won||0);
    jQuery('#kpi-lost').text(d.lost||0);jQuery('#kpi-conversion').text((d.conversion_rate||0)+'%');
    jQuery('#hstat-new,#hstat-today,#hstat-pi,#hstat-won').each(function(){
      var m={'hstat-new':'new','hstat-today':'today','hstat-pi':'pi_sent','hstat-won':'won'};
      jQuery(this).text(d[m[this.id]]||0);
    });
    jQuery('#tab-new-count').text(d.new>0?d.new:'').toggle(d.new>0);
  });
}
</script>
</div><!-- /#iip-inquiries-wrap -->
