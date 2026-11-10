<?php defined('ABSPATH')||exit; ?>
<div id="iip-packlist-wrap">
<div class="iip-box" style="padding:14px 18px;margin-bottom:14px">
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <label style="font-size:12px;font-weight:600">Inquiry ID:</label>
    <input type="number" id="pl-inquiry-id" class="iip-input" style="width:100px" placeholder="e.g. 5">
    <label style="font-size:12px;font-weight:600">PI ID:</label>
    <input type="number" id="pl-pi-id" class="iip-input" style="width:100px" placeholder="optional">
    <button class="iip-btn primary" id="btn-pl-from-pi">📦 Auto-Generate from PI</button>
    <button class="iip-btn" id="btn-pl-manual">+ Manual Packlist</button>
  </div>
</div>

<div id="pl-editor" style="display:none">
<div class="iip-box" style="padding:0">
  <div style="padding:14px 18px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #e0e0e0">
    <h3 style="margin:0;border:none;padding:0">📦 <span id="pl-number-display">Packing List</span></h3>
    <div style="display:flex;gap:6px">
      <button class="iip-btn sm" id="btn-save-pl">💾 Save</button>
      <button class="iip-btn sm primary" id="btn-gen-pl-pdf">📄 Generate PDF</button>
    </div>
  </div>

  <!-- Totals summary -->
  <div style="display:flex;gap:0;background:#f8f9fa;border-bottom:1px solid #e0e0e0">
    <div style="flex:1;padding:14px;text-align:center;border-right:1px solid #e0e0e0">
      <div style="font-size:22px;font-weight:700;color:#1a1a2e" id="pl-tot-boxes">0</div>
      <div style="font-size:11px;color:#888">Cartons</div>
    </div>
    <div style="flex:1;padding:14px;text-align:center;border-right:1px solid #e0e0e0">
      <div style="font-size:22px;font-weight:700;color:#1a1a2e" id="pl-tot-net">0 kg</div>
      <div style="font-size:11px;color:#888">Net Weight</div>
    </div>
    <div style="flex:1;padding:14px;text-align:center;border-right:1px solid #e0e0e0">
      <div style="font-size:22px;font-weight:700;color:#1a1a2e" id="pl-tot-gross">0 kg</div>
      <div style="font-size:11px;color:#888">Gross Weight</div>
    </div>
    <div style="flex:1;padding:14px;text-align:center;border-right:1px solid #e0e0e0">
      <div style="font-size:22px;font-weight:700;color:#2e7d32" id="pl-tot-cbm">0 m³</div>
      <div style="font-size:11px;color:#888">Total CBM</div>
    </div>
    <div style="flex:1;padding:14px;text-align:center;border-right:1px solid #e0e0e0">
      <div style="font-size:16px;font-weight:700;color:#1565c0" id="pl-sea-est">—</div>
      <div style="font-size:11px;color:#888">🚢 Sea Est. (USD)</div>
    </div>
    <div style="flex:1;padding:14px;text-align:center">
      <div style="font-size:16px;font-weight:700;color:#6a1b9a" id="pl-air-est">—</div>
      <div style="font-size:11px;color:#888">✈ Air Est. (USD)</div>
    </div>
  </div>

  <!-- Boxes table -->
  <div style="padding:16px;overflow-x:auto">
  <table class="iip-li-table" id="pl-boxes-table">
    <thead><tr>
      <th>Box#</th><th style="width:25%">Product</th><th>SKU</th><th>Qty</th>
      <th>Net Wt (kg)</th><th>Gross Wt (kg)</th><th>L (cm)</th><th>W (cm)</th><th>H (cm)</th>
      <th>CBM</th><th></th>
    </tr></thead>
    <tbody id="pl-boxes-body"></tbody>
  </table>
  <button class="iip-btn sm" id="btn-add-box" style="margin-top:8px">+ Add Box</button>
  </div>
</div>
</div>

<script>
var plId=0;
function addBoxRow(box){
  box=box||{};
  var num=jQuery('#pl-boxes-body tr').length+1;
  var row=jQuery('<tr>'
    +'<td><input class="bx-no" value="'+(box.box_no||num)+'" style="width:40px;border:none;background:transparent"></td>'
    +'<td><input class="bx-prod" value="'+(box.product||'')+'" style="width:100%;border:none;background:transparent;font-size:11px"></td>'
    +'<td><input class="bx-sku" value="'+(box.sku||'')+'" style="width:60px;border:none;background:transparent;font-size:10px"></td>'
    +'<td><input class="bx-qty" type="number" value="'+(box.qty||0)+'" style="width:50px;border:none;background:transparent;font-size:11px" min="0"></td>'
    +'<td><input class="bx-net" type="number" value="'+(box.net_wt||0)+'" style="width:65px;border:none;background:transparent;font-size:11px" step="0.001"></td>'
    +'<td><input class="bx-gross" type="number" value="'+(box.gross_wt||0)+'" style="width:65px;border:none;background:transparent;font-size:11px" step="0.001"></td>'
    +'<td><input class="bx-l" type="number" value="'+(box.length_cm||60)+'" style="width:50px;border:none;background:transparent;font-size:11px"></td>'
    +'<td><input class="bx-w" type="number" value="'+(box.width_cm||40)+'" style="width:50px;border:none;background:transparent;font-size:11px"></td>'
    +'<td><input class="bx-h" type="number" value="'+(box.height_cm||30)+'" style="width:50px;border:none;background:transparent;font-size:11px"></td>'
    +'<td class="bx-cbm" style="font-size:10px;font-weight:600;color:#2e7d32">'+parseFloat(box.cbm||0).toFixed(4)+'</td>'
    +'<td><button onclick="jQuery(this).closest(\'tr\').remove();calcPLTotals();" style="background:none;border:none;color:#e53935;cursor:pointer">✕</button></td>'
    +'</tr>');
  row.find('input').on('input',function(){
    var tr=jQuery(this).closest('tr');
    var l=parseFloat(tr.find('.bx-l').val())||0,w=parseFloat(tr.find('.bx-w').val())||0,h=parseFloat(tr.find('.bx-h').val())||0;
    tr.find('.bx-cbm').text(((l*w*h)/1000000).toFixed(4));
    calcPLTotals();
  });
  jQuery('#pl-boxes-body').append(row);
  calcPLTotals();
}
function calcPLTotals(){
  var net=0,gross=0,cbm=0,boxes=0;
  jQuery('#pl-boxes-body tr').each(function(){
    boxes++;
    net+=parseFloat(jQuery(this).find('.bx-net').val())||0;
    gross+=parseFloat(jQuery(this).find('.bx-gross').val())||0;
    var l=parseFloat(jQuery(this).find('.bx-l').val())||0,w=parseFloat(jQuery(this).find('.bx-w').val())||0,h=parseFloat(jQuery(this).find('.bx-h').val())||0;
    cbm+=(l*w*h)/1000000;
  });
  jQuery('#pl-tot-boxes').text(boxes);
  jQuery('#pl-tot-net').text(net.toFixed(2)+' kg');
  jQuery('#pl-tot-gross').text(gross.toFixed(2)+' kg');
  jQuery('#pl-tot-cbm').text(cbm.toFixed(4)+' m³');
  var sea=Math.max(gross*0.035,cbm*35).toFixed(2);
  var air=(gross*4.5).toFixed(2);
  jQuery('#pl-sea-est').text('~$'+sea);
  jQuery('#pl-air-est').text('~$'+air);
}
function getBoxes(){
  var boxes=[];
  jQuery('#pl-boxes-body tr').each(function(){
    var l=parseFloat(jQuery(this).find('.bx-l').val())||0,w=parseFloat(jQuery(this).find('.bx-w').val())||0,h=parseFloat(jQuery(this).find('.bx-h').val())||0;
    boxes.push({box_no:jQuery(this).find('.bx-no').val(),product:jQuery(this).find('.bx-prod').val(),sku:jQuery(this).find('.bx-sku').val(),
      qty:jQuery(this).find('.bx-qty').val(),net_wt:jQuery(this).find('.bx-net').val(),gross_wt:jQuery(this).find('.bx-gross').val(),
      length_cm:l,width_cm:w,height_cm:h,cbm:(l*w*h/1000000).toFixed(4)});
  });
  return boxes;
}
jQuery(function($){
  $('#btn-pl-from-pi').on('click',function(){
    var piId=parseInt($('#pl-pi-id').val())||0;
    if(!piId){iipNotice('Enter PI ID first','error');return;}
    $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_pl_from_pi',nonce:SGCIIP.nonce,pi_id:piId,inquiry_id:parseInt($('#pl-inquiry-id').val())||0},function(r){
      if(!r.success){iipNotice(r.data,'error');return;}
      plId=r.data.id; $('#pl-number-display').text(r.data.pl_number);
      $('#pl-boxes-body').empty();
      (r.data.boxes||[]).forEach(function(b){ addBoxRow(b); });
      calcPLTotals(); $('#pl-editor').show();
      iipNotice('✅ Packing list auto-generated from PI!');
    });
  });
  $('#btn-add-box').on('click',function(){ addBoxRow(); });
  $('#btn-save-pl').on('click',function(){
    if(!plId){iipNotice('Generate packlist first','error');return;}
    $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_pl_save',nonce:SGCIIP.nonce,pl_id:plId,boxes:JSON.stringify(getBoxes())},function(r){
      iipNotice(r.success?'✅ Saved!':r.data, r.success?'success':'error');
    });
  });
  $('#btn-gen-pl-pdf').on('click',function(){
    if(!plId){iipNotice('Save first','error');return;}
    $(this).text('Generating…').prop('disabled',true);
    $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_pl_generate_pdf',nonce:SGCIIP.nonce,pl_id:plId},function(r){
      $('#btn-gen-pl-pdf').text('📄 Generate PDF').prop('disabled',false);
      if(r.success){iipNotice('✅ PDF ready!');window.open(r.data.pdf_url,'_blank');}
      else iipNotice(r.data,'error');
    });
  });
  $('#btn-pl-manual').on('click',function(){
    var inqId=parseInt($('#pl-inquiry-id').val())||0;
    if(!inqId){iipNotice('Enter inquiry ID','error');return;}
    $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_pl_create',nonce:SGCIIP.nonce,inquiry_id:inqId},function(r){
      if(r.success){ plId=r.data.id; $('#pl-number-display').text(r.data.pl_number); $('#pl-editor').show(); }
    });
  });
});
</script>
</div><!-- /#iip-packlist-wrap -->
