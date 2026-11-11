<?php defined('ABSPATH')||exit; ?>
<style>
#iip-kanban-wrap{overflow-x:auto;padding-bottom:8px}
.iip-kanban-v2{display:flex;gap:10px;min-width:940px;align-items:flex-start}
.iip-kb-col{flex:1;min-width:140px;background:#f8f9fa;border-radius:10px;overflow:hidden;border:1px solid #e5e7eb}
.iip-kb-hdr{padding:9px 11px;font-size:11px;font-weight:700;letter-spacing:.04em;display:flex;justify-content:space-between;align-items:center}
.iip-kb-count{font-size:10px;font-weight:700;padding:1px 7px;border-radius:10px;background:rgba(0,0,0,.12)}
.iip-kb-body{padding:7px;min-height:90px;display:flex;flex-direction:column;gap:6px;transition:background .15s}
.iip-kb-body.drag-over{background:rgba(24,95,165,.08);border-radius:6px;outline:2px dashed #185fa5}
.iip-kcard{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:9px 10px;cursor:grab;transition:box-shadow .15s;user-select:none;position:relative}
.iip-kcard:active{cursor:grabbing;box-shadow:0 6px 18px rgba(0,0,0,.13)}
.iip-kcard.dragging{opacity:.35}
.iip-kcard-name{font-size:11px;font-weight:600;color:#1a1a2e;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.iip-kcard-co{font-size:10px;color:#888;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.iip-kcard-meta{display:flex;gap:5px;margin-top:5px;align-items:center;flex-wrap:wrap}
.iip-kcard-flag{font-size:9px;background:#f1f3f5;padding:1px 5px;border-radius:4px;color:#666}
.iip-kcard-pi{font-size:9px;color:#185fa5;font-weight:600;font-family:monospace}
.iip-kcard-health{font-size:10px;font-weight:700;padding:1px 5px;border-radius:4px}
.iip-kcard-actions{display:none;gap:4px;margin-top:7px;border-top:1px solid #f0f0f0;padding-top:6px}
.iip-kcard:hover .iip-kcard-actions{display:flex;flex-wrap:wrap}
.iip-qa{border:none;border-radius:5px;padding:3px 7px;font-size:10px;cursor:pointer;font-weight:600;line-height:1.5}
.iip-pipe-summary{display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap}
.iip-pipe-kpi{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:8px 12px;text-align:center;flex:1;min-width:90px}
.iip-pipe-kpi-n{font-size:18px;font-weight:600;color:#1a1a2e}
.iip-pipe-kpi-l{font-size:10px;color:#888;margin-top:1px}
.iip-pipe-toolbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:8px}
/* Payment modal */
.iip-modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.52);z-index:99990;align-items:center;justify-content:center}
.iip-modal-bg.open{display:flex}
.iip-modal{background:#fff;border-radius:14px;width:530px;max-width:96vw;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.25)}
.iip-modal-hdr{padding:15px 20px;border-bottom:1px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center}
.iip-modal-hdr h3{margin:0;font-size:14px;font-weight:600;color:#1a1a2e}
.iip-modal-close{font-size:22px;cursor:pointer;color:#aaa;line-height:1;border:none;background:none;padding:0 4px}
.iip-modal-body{padding:18px 20px}
.iip-pay-form{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.iip-pay-form label{font-size:11px;color:#555;font-weight:500;display:block;margin-bottom:3px}
.iip-pay-form input,.iip-pay-form select,.iip-pay-form textarea{width:100%;padding:6px 9px;border:1px solid #d9d9d9;border-radius:6px;font-size:12px;background:#fafafa;color:#222}
.iip-pay-form .full{grid-column:1/-1}
.iip-pay-table{width:100%;border-collapse:collapse;font-size:11px;margin-top:10px}
.iip-pay-table th{background:#f5f7fa;padding:6px 8px;text-align:left;font-weight:600;color:#555;border-bottom:2px solid #e5e7eb}
.iip-pay-table td{padding:6px 8px;border-bottom:1px solid #f0f0f0;vertical-align:middle}
.pay-total-row{display:flex;justify-content:space-between;align-items:center;background:#eaf3ff;border-radius:8px;padding:9px 13px;margin-top:8px;font-size:12px;font-weight:600;color:#185fa5}
</style>

<div id="iip-pipeline-wrap">
  <div class="iip-pipe-toolbar">
    <div style="font-size:12px;color:#888">Pipeline · drag cards between stages · hover for actions</div>
    <div style="display:flex;gap:8px;align-items:center">
      <select id="iip-pipe-filter-type" style="font-size:12px;padding:4px 8px;border:1px solid #ddd;border-radius:6px">
        <option value="">All types</option>
        <option value="oem">OEM</option>
        <option value="bulk">Bulk</option>
        <option value="dealer">Dealer</option>
        <option value="institution">Institution</option>
        <option value="retail">Retail</option>
        <option value="sample">Sample</option>
      </select>
      <button class="iip-btn sm" id="btn-reload-pipeline">🔄 Refresh</button>
    </div>
  </div>

  <div class="iip-pipe-summary">
    <div class="iip-pipe-kpi"><div class="iip-pipe-kpi-n" id="kpi-total">—</div><div class="iip-pipe-kpi-l">Total</div></div>
    <div class="iip-pipe-kpi"><div class="iip-pipe-kpi-n" id="kpi-open" style="color:#185fa5">—</div><div class="iip-pipe-kpi-l">Active</div></div>
    <div class="iip-pipe-kpi"><div class="iip-pipe-kpi-n" id="kpi-pi" style="color:#8e44ad">—</div><div class="iip-pipe-kpi-l">PI Sent</div></div>
    <div class="iip-pipe-kpi"><div class="iip-pipe-kpi-n" id="kpi-adv" style="color:#e67e22">—</div><div class="iip-pipe-kpi-l">Advance Rcvd</div></div>
    <div class="iip-pipe-kpi"><div class="iip-pipe-kpi-n" id="kpi-won" style="color:#27ae60">—</div><div class="iip-pipe-kpi-l">Won</div></div>
  </div>

  <div id="iip-kanban-wrap">
    <div class="iip-kanban-v2" id="pipeline-kanban">
      <div style="padding:30px;color:#aaa;text-align:center;width:100%">⏳ Loading pipeline…</div>
    </div>
  </div>
</div>

<!-- Payment Modal -->
<div class="iip-modal-bg" id="pay-modal">
  <div class="iip-modal">
    <div class="iip-modal-hdr">
      <h3>💰 Payments — <span id="pay-modal-name" style="color:#185fa5"></span></h3>
      <button class="iip-modal-close" id="pay-modal-close">&times;</button>
    </div>
    <div class="iip-modal-body">
      <div style="font-size:12px;font-weight:600;margin-bottom:10px">Add New Payment</div>
      <input type="hidden" id="pay-inquiry-id">
      <div class="iip-pay-form">
        <div><label>Payment Type</label>
          <select id="pay-type">
            <option value="advance">Advance (30%)</option>
            <option value="balance">Balance (70%)</option>
            <option value="full">Full Payment</option>
            <option value="partial">Partial</option>
            <option value="sample">Sample Payment</option>
            <option value="refund">Refund</option>
          </select></div>
        <div><label>Method</label>
          <select id="pay-method">
            <option value="bank_tt">Bank TT / Wire</option>
            <option value="lc">Letter of Credit (LC)</option>
            <option value="paypal">PayPal</option>
            <option value="wise">Wise</option>
            <option value="upi">UPI / GPay</option>
            <option value="cash">Cash</option>
            <option value="cheque">Cheque / DD</option>
          </select></div>
        <div><label>Amount</label>
          <input type="number" id="pay-amount" placeholder="e.g. 1500.00" min="0" step="0.01"></div>
        <div><label>Currency</label>
          <select id="pay-currency">
            <option value="USD" selected>USD $</option>
            <option value="INR">INR ₹</option>
            <option value="EUR">EUR €</option>
            <option value="GBP">GBP £</option>
            <option value="AED">AED</option>
          </select></div>
        <div><label>Payment Date</label>
          <input type="date" id="pay-date"></div>
        <div><label>Reference No.</label>
          <input type="text" id="pay-ref" placeholder="TT ref / UTR / Txn ID"></div>
        <div class="full"><label>Notes (optional)</label>
          <textarea id="pay-notes" rows="2" placeholder="e.g. 30% advance received via wire transfer"></textarea></div>
      </div>
      <button class="iip-btn primary" id="btn-save-payment" style="margin-top:12px;width:100%">✅ Save Payment</button>

      <div style="font-size:12px;font-weight:600;margin:18px 0 8px">Payment History</div>
      <table class="iip-pay-table">
        <thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Method</th><th>Ref</th><th></th></tr></thead>
        <tbody id="pay-history-body"><tr><td colspan="6" style="color:#aaa;text-align:center;padding:10px">Loading…</td></tr></tbody>
      </table>
      <div class="pay-total-row" id="pay-total-row" style="display:none">
        <span>Total received (USD equiv.)</span><span id="pay-total-val">$0</span>
      </div>
    </div>
  </div>
</div>

<script>
(function($){
'use strict';
var STAGES={
  new:      {label:'New',         color:'#EBF5FB',tc:'#1A5276',icon:'📥'},
  replied:  {label:'Replied',     color:'#EAF0FB',tc:'#1A3A6E',icon:'💬'},
  pi_sent:  {label:'PI Sent',     color:'#F3E5F5',tc:'#6A1B9A',icon:'📄'},
  accepted: {label:'Accepted',    color:'#E8F5E9',tc:'#1B5E20',icon:'✅'},
  advance:  {label:'Advance Rcvd',color:'#FFF8E1',tc:'#E65100',icon:'💰'},
  won:      {label:'Won ✓',       color:'#E8F5E9',tc:'#1B5E20',icon:'🏆'},
  lost:     {label:'Lost',        color:'#FFEBEE',tc:'#B71C1C',icon:'❌'},
  sample:   {label:'Sample Sent', color:'#FFFDE7',tc:'#F57F17',icon:'📦'}
};
var filterType='', currentPayId=0;

function escH(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

function showToast(msg,type){
  var bg=type==='error'?'#e24b4a':'#27ae60';
  var t=$('<div style="position:fixed;bottom:22px;right:22px;z-index:999999;background:'+bg+';color:#fff;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,.2)">'+msg+'</div>');
  $('body').append(t);
  setTimeout(function(){t.fadeOut(300,function(){t.remove();});},2800);
}

function loadPipeline(){
  $('#pipeline-kanban').html('<div style="padding:30px;color:#aaa;text-align:center;width:100%">⏳ Loading…</div>');
  $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_get_pipeline',nonce:SGCIIP.nonce},function(r){
    if(!r.success)return;
    var data=r.data.data, html='';
    var tot=0,open=0,pi=0,adv=0,won=0;
    Object.keys(STAGES).forEach(function(key){
      var st=STAGES[key], cards=data[key]||[];
      if(filterType) cards=cards.filter(function(c){return c.buyer_type===filterType;});
      tot+=cards.length;
      if(['new','replied','pi_sent','accepted'].indexOf(key)>=0) open+=cards.length;
      if(key==='pi_sent') pi+=cards.length;
      if(key==='advance') adv+=cards.length;
      if(key==='won') won+=cards.length;

      html+='<div class="iip-kb-col" data-stage="'+key+'">'
        +'<div class="iip-kb-hdr" style="background:'+st.color+';color:'+st.tc+'">'+st.icon+' '+st.label
        +'<span class="iip-kb-count">'+cards.length+'</span></div>'
        +'<div class="iip-kb-body" data-stage="'+key+'">';

      if(!cards.length) html+='<div style="font-size:10px;color:#ccc;text-align:center;padding:14px 4px">Empty</div>';

      cards.forEach(function(c){
        var h=parseInt(c.health_score)||0;
        var hc=h>=70?'#27ae60':h>=40?'#f39c12':'#e24b4a';
        var hbg=h>=70?'#eafaf1':h>=40?'#fef9e7':'#fdedec';
        html+='<div class="iip-kcard" draggable="true" data-id="'+c.id+'" data-stage="'+key+'">'
          +'<div class="iip-kcard-name" title="'+escH(c.name)+'">'+escH(c.name)+'</div>'
          +(c.company?'<div class="iip-kcard-co">'+escH(c.company)+'</div>':'')
          +'<div class="iip-kcard-meta">'
          +(c.country?'<span class="iip-kcard-flag">'+escH(c.country)+'</span>':'')
          +(c.pi_number?'<span class="iip-kcard-pi">'+escH(c.pi_number)+'</span>':'')
          +(h?'<span class="iip-kcard-health" style="background:'+hbg+';color:'+hc+'">'+h+'</span>':'')
          +'</div>'
          +'<div class="iip-kcard-actions">'
          +'<button class="iip-qa" style="background:#e8f4fd;color:#185fa5" onclick="iipQA('+c.id+',\'send_autoreply\')">✉ Reply</button>'
          +'<button class="iip-qa" style="background:#e6f9ee;color:#1a7a3d" onclick="iipQA('+c.id+',\'send_wa\')">💬 WA</button>'
          +'<button class="iip-qa" style="background:#e6f7ed;color:#1b5e20" onclick="iipQA('+c.id+',\'mark_won\')">🏆 Won</button>'
          +'<button class="iip-qa" style="background:#fdecea;color:#b71c1c" onclick="iipQA('+c.id+',\'mark_lost\')">✕ Lost</button>'
          +'<button class="iip-qa" style="background:#fff8e1;color:#e65100" onclick="openPay('+c.id+',\''+escH(c.name)+'\')">💰 Pay</button>'
          +'</div>'
          +'</div>';
      });
      html+='</div></div>';
    });
    $('#pipeline-kanban').html(html);
    $('#kpi-total').text(tot);$('#kpi-open').text(open);$('#kpi-pi').text(pi);$('#kpi-adv').text(adv);$('#kpi-won').text(won);
    initDnD();
  });
}

function initDnD(){
  var dragging=null;
  $(document).off('dragstart.iip dragend.iip dragover.iip dragleave.iip drop.iip');
  $(document).on('dragstart.iip','.iip-kcard',function(e){
    dragging=this; $(this).addClass('dragging');
    e.originalEvent.dataTransfer.effectAllowed='move';
    e.originalEvent.dataTransfer.setData('text/plain',$(this).data('id'));
  });
  $(document).on('dragend.iip','.iip-kcard',function(){$(this).removeClass('dragging');$('.iip-kb-body').removeClass('drag-over');dragging=null;});
  $(document).on('dragover.iip','.iip-kb-body',function(e){e.preventDefault();$('.iip-kb-body').removeClass('drag-over');$(this).addClass('drag-over');});
  $(document).on('dragleave.iip','.iip-kb-body',function(){$(this).removeClass('drag-over');});
  $(document).on('drop.iip','.iip-kb-body',function(e){
    e.preventDefault();$(this).removeClass('drag-over');
    if(!dragging)return;
    var newStage=$(this).data('stage'), id=$(dragging).data('id'), oldStage=$(dragging).data('stage');
    if(newStage===oldStage)return;
    $(dragging).data('stage',newStage);$(this).append(dragging);
    var oc=$('.iip-kb-col[data-stage="'+oldStage+'"] .iip-kb-count');
    var nc=$('.iip-kb-col[data-stage="'+newStage+'"] .iip-kb-count');
    oc.text(Math.max(0,parseInt(oc.text())-1));nc.text(parseInt(nc.text())+1);
    $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_move_stage',nonce:SGCIIP.nonce,id:id,stage:newStage},function(r){
      if(!r.success){alert('Stage update failed');loadPipeline();}
      else showToast('Moved to '+STAGES[newStage].label,'success');
    });
  });
}

window.iipQA=function(id,action){
  $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_quick_action',nonce:SGCIIP.nonce,id:id,quick_action:action},function(r){
    if(r.success){showToast(r.data||'Done','success');if(action==='mark_won'||action==='mark_lost')loadPipeline();}
    else showToast(r.data||'Error','error');
  });
};

window.openPay=function(inqId,name){
  currentPayId=inqId;
  $('#pay-inquiry-id').val(inqId);
  $('#pay-modal-name').text(name);
  $('#pay-date').val(new Date().toISOString().slice(0,10));
  $('#pay-modal').addClass('open');
  loadPayHistory();
};

function loadPayHistory(){
  $('#pay-history-body').html('<tr><td colspan="6" style="color:#aaa;text-align:center">Loading…</td></tr>');
  $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_get_payments',nonce:SGCIIP.nonce,inquiry_id:currentPayId},function(r){
    if(!r.success)return;
    var pays=r.data.payments, total=parseFloat(r.data.total_usd)||0;
    var tb='';
    if(!pays.length){tb='<tr><td colspan="6" style="color:#ccc;text-align:center;padding:12px">No payments recorded</td></tr>';}
    else{pays.forEach(function(p){
      var tl={advance:'Advance',balance:'Balance',full:'Full',partial:'Partial',sample:'Sample',refund:'Refund'}[p.payment_type]||p.payment_type;
      var ml={bank_tt:'Bank TT',lc:'LC',paypal:'PayPal',wise:'Wise',upi:'UPI',cash:'Cash',cheque:'Cheque'}[p.method]||p.method;
      tb+='<tr><td>'+escH(p.payment_date)+'</td><td style="font-weight:600;color:#185fa5">'+tl+'</td>'
        +'<td style="font-weight:600">'+p.currency+' '+parseFloat(p.amount).toFixed(2)+'</td>'
        +'<td>'+ml+'</td>'
        +'<td style="font-size:10px;color:#999;max-width:70px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+escH(p.reference_no||'—')+'</td>'
        +'<td><button data-pid="'+p.id+'" class="btn-del-pay" style="background:none;border:none;color:#e24b4a;cursor:pointer;font-size:12px">✕</button></td></tr>';
    });}
    $('#pay-history-body').html(tb);
    if(total>0){$('#pay-total-val').text('$'+total.toFixed(2)+' USD');$('#pay-total-row').show();}
    else{$('#pay-total-row').hide();}
  });
}

$(document).on('click','.btn-del-pay',function(){
  if(!confirm('Delete this payment record?'))return;
  var pid=$(this).data('pid');
  $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_delete_payment',nonce:SGCIIP.nonce,pay_id:pid,inquiry_id:currentPayId},function(r){
    if(r.success)loadPayHistory();
  });
});

$('#btn-save-payment').on('click',function(){
  var amt=parseFloat($('#pay-amount').val());
  if(!amt||amt<=0){alert('Enter a valid amount');return;}
  $(this).text('Saving…').prop('disabled',true);
  $.post(SGCIIP.ajaxUrl,{
    action:'sgc_iip_add_payment',nonce:SGCIIP.nonce,inquiry_id:currentPayId,
    payment_type:$('#pay-type').val(),amount:amt,currency:$('#pay-currency').val(),
    method:$('#pay-method').val(),reference_no:$('#pay-ref').val(),
    payment_date:$('#pay-date').val(),notes:$('#pay-notes').val()
  },function(r){
    $('#btn-save-payment').text('✅ Save Payment').prop('disabled',false);
    if(r.success){
      showToast(r.data.message||'Saved','success');
      $('#pay-amount,#pay-ref,#pay-notes').val('');
      loadPayHistory();loadPipeline();
    }else showToast(r.data||'Error','error');
  });
});

$('#pay-modal-close').on('click',function(){$('#pay-modal').removeClass('open');});
$('#pay-modal').on('click',function(e){if($(e.target).is('#pay-modal'))$(this).removeClass('open');});
$(document).on('keydown',function(e){if(e.key==='Escape')$('#pay-modal').removeClass('open');});

$('#iip-pipe-filter-type').on('change',function(){filterType=$(this).val();loadPipeline();});
$('#btn-reload-pipeline').on('click',loadPipeline);

$(function(){loadPipeline();});
})(jQuery);
</script>
