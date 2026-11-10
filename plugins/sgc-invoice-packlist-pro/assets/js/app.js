/* SGC IIP — app.js v1.7.0 — Conveyor Panel + Live PI Recalc */
(function($){
'use strict';

/* ── Notice ── */
window.iipNotice = function(msg,type,dur){
  type=type||'success'; dur=dur||4000;
  var $n=$('#sgc-iip-notice');
  $n.removeClass('success error info').addClass(type).text(msg).fadeIn(200);
  setTimeout(function(){$n.fadeOut(400);},dur);
};
window.openModal  = function(id){$('#'+id).fadeIn(150);$('body').css('overflow','hidden');};
window.closeModal = function(id){$('#'+id).fadeOut(150);$('body').css('overflow','');};
$(document).on('keydown',function(e){if(e.key==='Escape')$('.iip-modal:visible').fadeOut(150);});

function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function fmt2(n){return parseFloat(n||0).toFixed(2);}
function currSym(c){var m={'USD':'$','INR':'₹','EUR':'€','GBP':'£'};return m[c]||c+' ';}

/* State stores */
window._ep      = window._ep      || {};
window._bt      = window._bt      || {};
window._gt      = window._gt      || {};
window._pid     = window._pid     || {};
window._pn      = window._pn      || {};
window._cur     = window._cur     || {};
window._gstRate = window._gstRate || {};
window._inqData = window._inqData || {};
window._gt  = window._gt  || {};
window._pid = window._pid || {};
window._pn  = window._pn  || {};
window._cur = window._cur || {};

/* ════════════════════════════════════════
   SIDE PANEL — open / close
════════════════════════════════════════ */
window.openDetail = function(id){
  var $p=$('#iip-detail-panel');
  $p.html('<div style="padding:40px;text-align:center;color:#aaa"><div class="iip-spin"></div><br><br>Loading…</div>').show();
  $('#iip-panel-overlay').show();
  $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_get_inquiry',nonce:SGCIIP.nonce,id:id},function(r){
    if(!r.success){$p.html('<div style="padding:24px;color:red">Error loading.</div>');return;}
    drawPanel(r.data);
  });
};
window.closeSidePanel = function(){
  $('#iip-detail-panel').hide();
  $('#iip-panel-overlay').hide();
};

function flowArrow(label){
  return '<div style="display:flex;align-items:center;gap:8px;padding:5px 16px;background:#f8f9fa;border-top:1px solid #eee;border-bottom:1px solid #eee">'
    +'<span style="font-size:13px;color:#bbb">▼</span>'
    +'<span style="font-size:10px;color:#999;font-weight:600;letter-spacing:.03em">'+label+'</span>'
    +'</div>';
}

/* ── Calc totals from _ep ── */
function calcTotals(qid,isIndia){
  var items=(window._ep&&window._ep[qid])||[];
  var subtotal=0;
  items.forEach(function(it){
    var q=parseFloat(it.qty)||0,r=parseFloat(it.rate)||0;
    it._line=Math.round(q*r*100)/100;
    subtotal+=it._line;
  });
  subtotal=Math.round(subtotal*100)/100;
  // GST rate: use per-inquiry stored rate (set when GSTIN is entered or buyer type toggled)
  // Default 18% for domestic, 0% for export — editable via GSTIN field
  var gstRate=isIndia?(window._gstRate&&window._gstRate[qid]!=null?window._gstRate[qid]:18):0;
  var gst=isIndia?Math.round(subtotal*gstRate)/100:0;
  var gstType=(window._gt&&window._gt[qid])||'igst';
  var cgst=0,sgst=0,igst=0;
  if(isIndia){
    if(gstType==='cgst_sgst'){cgst=Math.round(subtotal*gstRate/2)/100;sgst=cgst;}
    else{igst=gst;}
  }
  return{subtotal:subtotal,gst:gst,cgst:cgst,sgst:sgst,igst:igst,grand:Math.round((subtotal+gst)*100)/100,gstRate:gstRate};
}

/* ── Live recalc PI card ── */
window.recalcPI=function(qid){
  var isIndia=window._bt&&window._bt[qid]==='domestic';
  var currency=(window._cur&&window._cur[qid])||(isIndia?'INR':'USD');
  var sym=currSym(currency);
  var tot=calcTotals(qid,isIndia);
  var r=tot.gstRate||18; // actual rate used
  $('#pi-sub-'+qid).text(sym+fmt2(tot.subtotal));
  $('#pi-grand-'+qid).text(sym+fmt2(tot.grand));
  if(isIndia){
    var gstType=(window._gt&&window._gt[qid])||'igst';
    if(gstType==='cgst_sgst'){
      $('#pi-gst-lbl-'+qid).text('CGST '+(r/2)+'% + SGST '+(r/2)+'%');
      $('#pi-gst-'+qid).text(sym+fmt2(tot.cgst)+' + '+sym+fmt2(tot.sgst));
    }else{
      $('#pi-gst-lbl-'+qid).text('IGST '+r+'%');
      $('#pi-gst-'+qid).text(sym+fmt2(tot.igst));
    }
    $('#pi-gst-row-'+qid).show();
  }else{
    $('#pi-gst-row-'+qid).hide();
  }
};

/* ═══════════════════════════════════════════════════
   DRAW PANEL — Conveyor Belt UI
═══════════════════════════════════════════════════ */
function drawPanel(q){
  var $p=$('#iip-detail-panel');

  window._inqData[q.id] = { name:q.name||'', email:q.email||'', phone:q.phone||'', company:q.company||'', country:q.country||'' };

  var indiaKeys=['india','in','bharat','ind'];
  var rawCtry=(q.country||'').toLowerCase().trim();
  if(window._bt[q.id]===undefined) window._bt[q.id]=(indiaKeys.indexOf(rawCtry)>=0)?'domestic':'export';
  var isIndia=window._bt[q.id]==='domestic';
  if(window._gt[q.id]===undefined) window._gt[q.id]='igst';
  var currency=isIndia?'INR':(q.currency||'USD');
  window._cur[q.id]=currency;
  var sym=currSym(currency);

  var prods=(q.product_details||[]).filter(function(p){return p.name||p.product;});
  if(prods.length&&!window._ep[q.id]){
    window._ep[q.id]=prods.map(function(p){
      return{name:p.name||p.product||'',qty:p.qty||1,unit:p.unit||'pcs',rate:p.rate||'',sku:p.sku||'',hsn:p.hsn||''};
    });
  }

  var pis=q.proformas||[];
  var lastPI=pis.length?pis[pis.length-1]:null;
  if(lastPI&&!window._pid[q.id]){window._pid[q.id]=lastPI.id;window._pn[q.id]=lastPI.pi_number;}

  var h=parseInt(q.health_score)||0;
  var hc=h>=70?'#2e7d32':h>=40?'#f57f17':'#c62828';
  var stC={new:'#1565C0',replied:'#6A1B9A',pi_sent:'#E65100',accepted:'#2E7D32',advance:'#00695C',won:'#1B5E20',lost:'#B71C1C',sample:'#F57F17'};
  var stL={new:'New',replied:'Replied',pi_sent:'PI Sent',accepted:'Accepted',advance:'Advance Rcvd',won:'Won',lost:'Lost',sample:'Sample'};
  var sc=stC[q.status]||'#555', sl=stL[q.status]||q.status||'New';

  var html='<div style="font-family:Arial,sans-serif;display:flex;flex-direction:column;height:100%">';

  /* ══ HEADER ══ */
  html+='<div style="background:#1a1a2e;color:#fff;padding:14px 16px;flex-shrink:0">'
    +'<div style="display:flex;justify-content:space-between;align-items:flex-start">'
    +'<div><div style="font-size:15px;font-weight:700">'+esc(q.name)
    +(q.returning_buyer=='1'?'<span style="background:#E3F2FD;color:#0D47A1;padding:1px 5px;border-radius:4px;font-size:9px;font-weight:700;margin-left:6px">RETURNING</span>':'')
    +'</div>'
    +'<div style="font-size:11px;color:#9090bb;margin-top:2px">'+esc(q.email||'')+(q.company?' · '+esc(q.company):'')+'</div></div>'
    +'<button onclick="closeSidePanel()" style="background:none;border:none;color:#aaa;font-size:22px;cursor:pointer;line-height:1">×</button>'
    +'</div>'
    +'<div style="display:flex;align-items:center;gap:8px;margin-top:10px;flex-wrap:wrap">'
    +'<span style="background:'+sc+';color:#fff;padding:3px 12px;border-radius:20px;font-size:11px;font-weight:700">'+sl+'</span>'
    +'<select onchange="chStatus('+q.id+',this.value)" style="font-size:11px;padding:3px 6px;border:1px solid #444;background:#2a2a3e;color:#ccc;border-radius:4px">'
    +'<option value="">Change status…</option>'
    +'<option value="replied">Replied</option><option value="pi_sent">PI Sent</option>'
    +'<option value="accepted">Accepted</option><option value="advance">Advance Rcvd</option>'
    +'<option value="won">Won</option><option value="lost">Lost</option>'
    +'</select>'
    +'<div style="margin-left:auto;display:flex;align-items:center;gap:6px">'
    +'<button id="btn-bt-'+q.id+'" onclick="toggleBuyerType('+q.id+')" style="font-size:11px;padding:4px 10px;border:1px solid '+(isIndia?'#2e7d32':'#185FA5')+';background:'+(isIndia?'#e8f5e9':'#e3f2fd')+';color:'+(isIndia?'#1b5e20':'#0c447c')+';border-radius:4px;cursor:pointer">'+(isIndia?'🇮🇳 Domestic':'🌍 Export')+'</button>'
    +'<div style="width:30px;height:30px;border-radius:50%;border:2px solid '+hc+';display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:'+hc+'">'+h+'</div>'
    +'</div></div></div>';

  /* ══ SCROLLABLE BODY ══ */
  html+='<div style="overflow-y:auto;flex:1;padding:0">';

  /* ── STEP 1: BUYER INFO ── */
  html+='<div style="padding:12px 16px;border-bottom:1px solid #f0f0f0;background:#fafafa">'
    +'<div style="font-size:10px;font-weight:700;color:#888;letter-spacing:.05em;margin-bottom:8px">STEP 1 — BUYER INFO</div>'
    +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 14px">'
    +'<div><div style="font-size:10px;color:#888">Email</div><a href="mailto:'+esc(q.email||'')+'" style="font-size:12px;font-weight:600;color:#185FA5">'+esc(q.email||'—')+'</a></div>'
    +'<div><div style="font-size:10px;color:#888">Phone</div><div style="font-size:12px;font-weight:600">'+esc(q.phone||'—')+'</div></div>'
    +'<div><div style="font-size:10px;color:#888">Country</div><div style="font-size:12px;font-weight:600">'+esc(q.country||'—')+'</div></div>'
    +'<div><div style="font-size:10px;color:#888">Currency</div><div id="cur-lbl-'+q.id+'" style="font-size:12px;font-weight:600;color:'+(isIndia?'#1b5e20':'#0c447c')+'">'+currency+'</div></div>'
    +'<div><div style="font-size:10px;color:#888">Incoterm</div><div style="font-size:12px;font-weight:600">'+esc(isIndia?'Ex-Works':(q.incoterm||'FOB'))+'</div></div>'
    +'<div><div style="font-size:10px;color:#888">Source</div><div style="font-size:12px;font-weight:600">'+esc(q.source_type||'manual')+'</div></div>'
    +'</div>'
    +'<div id="gstin-row-'+q.id+'" style="margin-top:8px;'+(isIndia?'':'display:none')+'">'
    +'<div style="font-size:10px;color:#888;margin-bottom:3px">Buyer GSTIN <span style="color:#aaa">(06=Haryana → CGST+SGST, other → IGST)</span></div>'
    +'<input id="gstin-'+q.id+'" type="text" value="'+esc(q.buyer_gstin||'')+'" placeholder="e.g. 06AAAAA0000A1Z5" maxlength="15" style="width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;font-size:11px;box-sizing:border-box" onblur="detectGstType('+q.id+',this.value)">'
    +'</div></div>';

  html+=flowArrow('Step 2 — Paste buyer message · AI extracts products + fills rates from WooCommerce');

  /* ── STEP 2: EXTRACT ── */
  html+='<div style="padding:12px 16px;border-bottom:1px solid #f0f0f0">'
    +'<div style="font-size:10px;font-weight:700;color:#888;letter-spacing:.05em;margin-bottom:6px">STEP 2 — AI EXTRACT</div>'
    +(q.raw_message
      ?'<div style="font-size:11px;color:#555;background:#f9f9f9;border:1px solid #e0e0e0;border-radius:5px;padding:8px;max-height:72px;overflow-y:auto;line-height:1.6;margin-bottom:8px">'+esc(q.raw_message).replace(/\n/g,'<br>')+'</div>'
      :'<textarea id="msg-'+q.id+'" rows="3" placeholder="WhatsApp/Email se buyer message yahan paste karo…" style="width:100%;border:1px solid #ddd;border-radius:5px;padding:8px;font-size:11px;resize:none;box-sizing:border-box;margin-bottom:8px"></textarea>'
    )
    +'<button id="btn-ex-'+q.id+'" onclick="doExtract('+q.id+')" style="width:100%;background:#6d28d9;color:#fff;border:none;border-radius:6px;padding:9px;font-size:12px;font-weight:700;cursor:pointer">⚡ Extract with AI — auto-fill table + rates</button>'
    +'</div>';

  html+=flowArrow('Step 3 — Table always editable · Rate/Qty change → PI card updates instantly');

  /* ── STEP 3: PRODUCT TABLE ── */
  var tot0=calcTotals(q.id,isIndia);
  html+='<div style="padding:12px 16px;border-bottom:1px solid #f0f0f0">'
    +'<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">'
    +'<div style="font-size:10px;font-weight:700;color:#888;letter-spacing:.05em">STEP 3 — PRODUCTS'
    +'<span id="ai-rate-badge-'+q.id+'" style="font-size:9px;background:#e8f5e9;color:#1b5e20;padding:1px 7px;border-radius:8px;margin-left:6px;display:none">AI rates filled</span>'
    +'</div>'
    +'<button onclick="addRow('+q.id+')" style="font-size:11px;padding:3px 10px;border:1px dashed #6d28d9;color:#6d28d9;background:transparent;border-radius:4px;cursor:pointer">+ Add row</button>'
    +'</div>'
    +'<div id="pt-'+q.id+'">'+buildTable(q.id,isIndia,currency)+'</div>'
    +'</div>';

  html+=flowArrow('Step 4 — PI auto-calculates · Verify totals · Save draft → archived on update');

  /* ── STEP 4: PI PREVIEW ── */
  html+='<div style="padding:12px 16px;border-bottom:1px solid #f0f0f0">'
    +'<div style="font-size:10px;font-weight:700;color:#888;letter-spacing:.05em;margin-bottom:8px">STEP 4 — PROFORMA INVOICE</div>'
    +buildPICard(q,isIndia,currency,sym,tot0,lastPI)
    +'<div id="pi-gen-st-'+q.id+'" style="font-size:11px;margin-top:6px;min-height:12px"></div>'
    +'</div>';

  html+=flowArrow('Step 5 — Email auto-composed with PI · To / CC / BCC · HTML template + PDF attached');

  /* ── STEP 5: COMPOSE EMAIL ── */
  var hasDraftPI=!!(window._pid[q.id]||lastPI);
  var piNumForSubj=(window._pn[q.id]||(lastPI?lastPI.pi_number:''));
  var defSubject=piNumForSubj
    ?('Proforma Invoice '+piNumForSubj+' — SGC LABS Scientific Glassware')
    :'Proforma Invoice — SGC LABS Scientific Glassware';

  html+='<div style="padding:12px 16px;border-bottom:1px solid #f0f0f0">'
    +'<div style="font-size:10px;font-weight:700;color:#888;letter-spacing:.05em;margin-bottom:8px">STEP 5 — COMPOSE &amp; SEND</div>'
    +'<div style="background:#f8f9fa;border-radius:6px;padding:10px;margin-bottom:8px">'
    +'<div style="margin-bottom:6px"><div style="font-size:10px;color:#888;margin-bottom:2px">To</div>'
    +'<input type="email" value="'+esc(q.email||'')+'" readonly style="width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;font-size:12px;background:#f0f0f0;box-sizing:border-box"></div>'
    +'<div style="display:flex;gap:8px;margin-bottom:6px">'
    +'<div style="flex:1"><div style="font-size:10px;color:#888;margin-bottom:2px">CC</div>'
    +'<input type="text" id="cc-'+q.id+'" placeholder="cc@example.com" style="width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;font-size:12px;box-sizing:border-box"></div>'
    +'<div style="flex:1"><div style="font-size:10px;color:#888;margin-bottom:2px">BCC</div>'
    +'<input type="text" id="bcc-'+q.id+'" placeholder="bcc@example.com" style="width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;font-size:12px;box-sizing:border-box"></div>'
    +'</div>'
    +'<div style="margin-bottom:6px"><div style="font-size:10px;color:#888;margin-bottom:2px">Subject</div>'
    +'<input type="text" id="sub-'+q.id+'" value="'+esc(defSubject)+'" style="width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;font-size:12px;box-sizing:border-box"></div>'
    +'<div><div style="font-size:10px;color:#888;margin-bottom:2px">Personal note <span style="color:#aaa">(optional — email ke upar aayega)</span></div>'
    +'<textarea id="pnote-'+q.id+'" rows="2" placeholder="e.g. As discussed on call…" style="width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 8px;font-size:11px;resize:none;box-sizing:border-box"></textarea></div>'
    +'</div>'
    +'<div id="attach-badge-'+q.id+'" style="margin-bottom:8px">'
    +(hasDraftPI
      ?'<span style="display:inline-flex;align-items:center;gap:5px;background:#e8f5e9;color:#1b5e20;font-size:11px;padding:3px 10px;border-radius:10px">📎 PI PDF auto-attached on send</span>'
      :'<span style="display:inline-flex;align-items:center;gap:5px;background:#fff3e0;color:#795548;font-size:11px;padding:3px 10px;border-radius:10px">⚠ Step 4 mein PI Draft pehle save karo</span>')
    +'</div>'
    +'<div style="font-size:10px;color:#888;padding:6px 0;border-top:1px dashed #e0e0e0;margin-bottom:8px">📧 HTML template: PI details · Bank/UPI · Accept PI button — sab auto-included</div>'
    +'<div style="display:flex;gap:8px">'
    +'<button id="btn-send-'+q.id+'" onclick="sendPIEmail('+q.id+')" data-piid="'+(window._pid[q.id]||(lastPI?lastPI.id:''))+'" style="flex:2;padding:11px;background:#1a1a2e;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:700;cursor:pointer;'+(hasDraftPI?'':'opacity:.5')+'" '+(hasDraftPI?'':'disabled')+'>📤 Send to Buyer</button>'
    +'<button onclick="savePIFromPanel('+q.id+')" style="flex:1;padding:11px;border:1px solid #1a1a2e;border-radius:6px;background:transparent;font-size:12px;font-weight:700;cursor:pointer;color:#1a1a2e">💾 Save Draft</button>'
    +'</div>'
    +'<div id="send-st-'+q.id+'" style="font-size:11px;margin-top:6px;min-height:12px"></div>'
    +'</div>';

  html+=flowArrow('Step 6 — Schedule follow-up · WP-Cron reminder email · Timeline log auto');

  /* ── STEP 6: FOLLOW-UP ── */
  var today=new Date().toISOString().split('T')[0];
  html+='<div style="padding:12px 16px;border-bottom:1px solid #f0f0f0">'
    +'<div style="font-size:10px;font-weight:700;color:#888;letter-spacing:.05em;margin-bottom:8px">STEP 6 — FOLLOW-UP REMINDER</div>'
    +'<div style="display:flex;gap:6px;margin-bottom:6px">'
    +'<input type="date" id="fu-d-'+q.id+'" min="'+today+'" style="flex:1;border:1px solid #ddd;border-radius:5px;padding:5px 8px;font-size:11px">'
    +'<select id="fu-t-'+q.id+'" style="border:1px solid #ddd;border-radius:5px;padding:5px 8px;font-size:11px">'
    +'<option value="email">Email</option><option value="whatsapp">WhatsApp</option><option value="call">Call</option></select>'
    +'</div>'
    +'<input type="text" id="fu-n-'+q.id+'" placeholder="Reminder note (optional)…" style="width:100%;border:1px solid #ddd;border-radius:5px;padding:5px 8px;font-size:11px;margin-bottom:6px;box-sizing:border-box">'
    +'<button onclick="saveFollowUp('+q.id+')" style="width:100%;padding:8px;background:#0F6E56;color:#fff;border:none;border-radius:5px;font-size:12px;font-weight:700;cursor:pointer">📅 Schedule — us date ko remind karo</button>'
    +'<div id="fu-st-'+q.id+'" style="font-size:11px;margin-top:6px;min-height:12px"></div>'
    +'</div>';

  /* ── NOTES + ACTIONS ── */
  html+='<div style="padding:12px 16px;border-bottom:1px solid #f0f0f0">'
    +'<div style="font-size:11px;font-weight:700;color:#555;margin-bottom:6px">📝 Admin Notes</div>'
    +'<textarea id="nt-'+q.id+'" rows="3" style="width:100%;border:1px solid #ddd;border-radius:5px;padding:6px 8px;font-size:12px;resize:vertical;box-sizing:border-box">'+esc(q.admin_note||'')+'</textarea>'
    +'<div style="display:flex;gap:6px;margin-top:6px;flex-wrap:wrap">'
    +'<button onclick="saveNote('+q.id+')" style="'+ob()+'">Save Note</button>'
    +'<div style="display:flex;gap:4px;align-items:center">'
    +'<input type="number" id="adv-'+q.id+'" placeholder="Amount" style="width:90px;border:1px solid #ddd;border-radius:4px;padding:5px 7px;font-size:11px">'
    +'<button onclick="markAdv('+q.id+')" style="'+ob()+'">💰 Advance</button></div>'
    +'<button onclick="doSendCRM('+q.id+')" style="'+ob()+'">👤 CRM</button>'
    +'<button onclick="doResendReply('+q.id+')" style="'+ob()+'">✉ Auto-reply</button>'
    +'<button onclick="openEditInquiry('+q.id+')" style="'+ob()+'color:#1a1a2e;border-color:#a0a0c0">✏️ Edit</button>'
    +'<button onclick="deleteInquiry('+q.id+')" style="'+ob()+'color:#c62828;border-color:#fca5a5;margin-left:auto">🗑 Delete</button>'
    +'</div></div>';

  /* ── PREVIOUS PIs ── */
  if(pis.length>1){
    html+='<div style="padding:12px 16px;border-bottom:1px solid #f0f0f0">'
      +'<div style="font-size:11px;font-weight:700;color:#555;margin-bottom:6px">📁 Previous PIs</div>';
    var rev=pis.slice().reverse();
    rev.forEach(function(p,i){
      if(i===0)return;
      html+='<div style="display:flex;justify-content:space-between;font-size:11px;padding:4px 0;border-bottom:1px dashed #eee">'
        +'<span style="color:#185FA5;font-weight:600">'+esc(p.pi_number)+'</span>'
        +'<span style="color:#888">'+esc((p.currency||'USD')+' '+fmt2(p.grand_total))+'</span>'
        +'<span style="font-size:10px;padding:1px 6px;border-radius:8px;background:'+(p.status==='sent'?'#e8f5e9':'#fff3e0')+';color:'+(p.status==='sent'?'#1b5e20':'#795548')+'">'+esc(p.status)+'</span>'
        +'<button onclick="viewPDF('+p.id+')" style="border:none;background:none;color:#185FA5;cursor:pointer;font-size:10px">PDF</button>'
        +'</div>';
    });
    html+='</div>';
  }

  /* ── TIMELINE ── */
  if((q.timeline||[]).length){
    html+='<div style="padding:12px 16px;background:#fafafa">'
      +'<div style="font-size:11px;font-weight:700;color:#555;margin-bottom:8px">🕐 Activity Timeline</div>';
    q.timeline.forEach(function(t){
      html+='<div style="display:flex;gap:8px;padding:4px 0;border-bottom:0.5px solid #eee">'
        +'<div style="width:6px;height:6px;border-radius:50%;background:#6d28d9;margin-top:5px;flex-shrink:0"></div>'
        +'<div><div style="font-size:11px;color:#333">'+esc((t.action||'').replace(/_/g,' '))+'</div>'
        +'<div style="font-size:11px;color:#666">'+esc(t.description||'')+'</div>'
        +'<div style="font-size:10px;color:#aaa">'+esc((t.created_at||'').substring(0,16))+'</div>'
        +'</div></div>';
    });
    html+='</div>';
  }

  html+='</div></div>';
  $p.html(html);
}

/* ── PI Card builder ── */
function buildPICard(q,isIndia,currency,sym,tot,lastPI){
  var h='<div style="background:#f8f9fa;border:1px solid #e0e0e0;border-radius:6px;padding:10px 12px;margin-bottom:8px">';
  if(lastPI){
    h+='<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;padding-bottom:6px;border-bottom:1px dashed #e0e0e0">'
      +'<div style="font-size:13px;font-weight:700;color:#185FA5">'+esc(lastPI.pi_number)+'</div>'
      +'<div style="display:flex;gap:6px;align-items:center">'
      +'<span style="font-size:10px;padding:2px 8px;border-radius:8px;background:'+(lastPI.status==='sent'?'#e8f5e9':'#fff3e0')+';color:'+(lastPI.status==='sent'?'#1b5e20':'#795548')+'">'+(lastPI.status==='sent'?'✅ Sent':'📋 Draft')+'</span>'
      +'<button onclick="viewPDF('+lastPI.id+')" style="border:1px solid #ddd;background:transparent;border-radius:4px;padding:2px 8px;font-size:10px;cursor:pointer">👁 PDF</button>'
      +'</div></div>';
  }
  h+='<div style="font-size:12px;color:#555;display:flex;justify-content:space-between;padding:3px 0">Subtotal<span id="pi-sub-'+q.id+'">'+sym+fmt2(tot.subtotal)+'</span></div>';
  var gstType=(window._gt&&window._gt[q.id])||'igst';
  var gstLbl=gstType==='cgst_sgst'?'CGST 9% + SGST 9%':'IGST 18%';
  var gstVal=gstType==='cgst_sgst'?sym+fmt2(tot.cgst)+' + '+sym+fmt2(tot.sgst):sym+fmt2(tot.igst);
  h+='<div id="pi-gst-row-'+q.id+'" style="font-size:12px;color:#555;display:flex;justify-content:space-between;padding:3px 0;'+(isIndia?'':'display:none')+'">'
    +'<span id="pi-gst-lbl-'+q.id+'">'+gstLbl+'</span><span id="pi-gst-'+q.id+'">'+gstVal+'</span></div>';
  h+='<div style="font-size:14px;font-weight:700;color:#1D9E75;display:flex;justify-content:space-between;border-top:1px solid #e0e0e0;padding-top:6px;margin-top:4px">'
    +'Grand Total<span id="pi-grand-'+q.id+'">'+sym+fmt2(tot.grand)+'</span></div></div>';
  h+='<div style="display:flex;gap:6px">'
    +'<button id="btn-save-pi-'+q.id+'" onclick="savePIFromPanel('+q.id+')" style="flex:2;padding:9px;background:#1a1a2e;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer">'
    +(lastPI?'🔄 Update PI Draft':'💾 Generate PI Draft')+'</button>'
    +(lastPI?'<button onclick="viewPDF('+lastPI.id+')" style="flex:1;padding:9px;border:1px solid #ddd;border-radius:6px;background:transparent;font-size:11px;cursor:pointer">👁 Full PDF</button>':'')
    +'</div>';
  return h;
}

/* ════════════════════════════════════════
   PRODUCT TABLE
════════════════════════════════════════ */
function buildTable(qid,isIndia,currency){
  var items=(window._ep&&window._ep[qid])||[];
  var sym=currSym(currency||'USD');
  if(!items.length) return '<div style="font-size:11px;color:#aaa;text-align:center;padding:10px">Product nahi — extract karo ya row add karo</div>';
  var inp='style="width:100%;border:0.5px solid #ddd;border-radius:3px;padding:3px 4px;font-size:10px;box-sizing:border-box"';
  var t='<table style="width:100%;border-collapse:collapse;font-size:10px">'
    +'<thead><tr style="background:#f5f5f5">'
    +'<th style="padding:4px 3px;text-align:left;width:36%">Product</th>'
    +'<th style="padding:4px 3px;text-align:center;width:8%">Qty</th>'
    +'<th style="padding:4px 3px;text-align:center;width:10%">Unit</th>'
    +'<th id="rate-hdr-'+qid+'" style="padding:4px 3px;text-align:right;width:12%">Rate '+sym+'</th>'
    +'<th style="padding:4px 3px;text-align:right;width:12%">Total</th>'
    +'<th style="padding:4px 3px;text-align:center;width:11%">SKU</th>'
    +'<th style="padding:4px 3px;text-align:center;width:9%">HSN</th>'
    +'<th style="width:2%"></th></tr></thead>'
    +'<tbody id="ptb-'+qid+'">';
  items.forEach(function(it,i){t+=prow(qid,i,it,inp,sym);});
  return t+'</tbody></table>';
}

function prow(qid,i,it,inp,sym){
  inp=inp||'style="width:100%;border:0.5px solid #ddd;border-radius:3px;padding:3px 4px;font-size:10px;box-sizing:border-box"';
  sym=sym||'$';
  var lt=Math.round((parseFloat(it.qty||0)*parseFloat(it.rate||0))*100)/100;
  var units=['pcs','pack','dozen','kg','set'];
  var uSel=units.map(function(u){return '<option'+(u===it.unit?' selected':'')+'>'+u+'</option>';}).join('');
  return '<tr id="pr-'+qid+'-'+i+'" style="border-bottom:0.5px solid #f0f0f0">'
    +'<td style="padding:2px;position:relative">'
    +'<input '+inp+' value="'+esc(it.name)+'" data-f="name" data-q="'+qid+'" data-i="'+i+'" oninput="upf(this);wcLiveSearch(this)" placeholder="Product name…">'
    +'<div id="wcs-'+qid+'-'+i+'" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #6d28d9;border-radius:0 0 6px 6px;z-index:9999;max-height:160px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div>'
    +'</td>'
    +'<td style="padding:2px"><input '+inp+' type="number" value="'+esc(it.qty)+'" min="1" data-f="qty" data-q="'+qid+'" data-i="'+i+'" oninput="upf(this)" style="width:100%;border:0.5px solid #ddd;border-radius:3px;padding:3px 2px;font-size:10px;text-align:center;box-sizing:border-box"></td>'
    +'<td style="padding:2px"><select data-f="unit" data-q="'+qid+'" data-i="'+i+'" onchange="upf(this)" style="width:100%;border:0.5px solid #ddd;border-radius:3px;padding:3px 1px;font-size:10px">'+uSel+'</select></td>'
    +'<td style="padding:2px"><input '+inp+' type="number" step="0.01" value="'+esc(it.rate||'')+'" placeholder="0.00" data-f="rate" data-q="'+qid+'" data-i="'+i+'" oninput="upf(this)" style="width:100%;border:0.5px solid #ddd;border-radius:3px;padding:3px 2px;font-size:10px;text-align:right;box-sizing:border-box"></td>'
    +'<td style="padding:2px;text-align:right;font-size:10px;color:#1D9E75;font-weight:600" id="lt-'+qid+'-'+i+'">'+(lt>0?sym+fmt2(lt):'—')+'</td>'
    +'<td style="padding:2px"><input '+inp+' value="'+esc(it.sku||'')+'" placeholder="SKU" data-f="sku" data-q="'+qid+'" data-i="'+i+'" oninput="upf(this)"></td>'
    +'<td style="padding:2px"><input '+inp+' value="'+esc(it.hsn||'')+'" placeholder="HSN" data-f="hsn" data-q="'+qid+'" data-i="'+i+'" oninput="upf(this)"></td>'
    +'<td style="padding:2px;text-align:center"><button onclick="rmRow('+qid+','+i+')" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:13px;line-height:1">×</button></td>'
    +'</tr>';
}

window.upf=function(el){
  var f=$(el).data('f'),i=$(el).data('i'),q=$(el).data('q');
  if(window._ep&&window._ep[q]&&window._ep[q][i]!==undefined){
    window._ep[q][i][f]=$(el).val();
    if(f==='rate'||f==='qty'){
      var r=parseFloat(window._ep[q][i].rate||0),qty=parseFloat(window._ep[q][i].qty||0);
      var lt=Math.round(r*qty*100)/100;
      var isIndia=window._bt&&window._bt[q]==='domestic';
      var sym=currSym((window._cur&&window._cur[q])||(isIndia?'INR':'USD'));
      $('#lt-'+q+'-'+i).text(lt>0?sym+fmt2(lt):'—');
      recalcPI(q);
    }
  }
};
window.rmRow=function(qid,i){
  if(window._ep&&window._ep[qid]) window._ep[qid].splice(i,1);
  var isIndia=window._bt&&window._bt[qid]==='domestic';
  var currency=(window._cur&&window._cur[qid])||(isIndia?'INR':'USD');
  $('#pt-'+qid).html(buildTable(qid,isIndia,currency));
  recalcPI(qid);
};
window.addRow=function(qid){
  if(!window._ep) window._ep={};
  if(!window._ep[qid]) window._ep[qid]=[];
  var ni=window._ep[qid].length;
  window._ep[qid].push({name:'',qty:1,unit:'pcs',rate:'',sku:'',hsn:''});
  var isIndia=window._bt&&window._bt[qid]==='domestic';
  var currency=(window._cur&&window._cur[qid])||(isIndia?'INR':'USD');
  var sym=currSym(currency);
  var inp='style="width:100%;border:0.5px solid #ddd;border-radius:3px;padding:3px 4px;font-size:10px;box-sizing:border-box"';
  var $tb=$('#ptb-'+qid);
  if($tb.length) $tb.append(prow(qid,ni,window._ep[qid][ni],inp,sym));
  else $('#pt-'+qid).html(buildTable(qid,isIndia,currency));
};

/* ════════════════════════════════════════
   TOGGLE BUYER TYPE
════════════════════════════════════════ */
window.toggleBuyerType=function(qid){
  if(!window._bt) window._bt={};
  window._bt[qid]=(window._bt[qid]==='domestic')?'export':'domestic';
  var isIndia=window._bt[qid]==='domestic';
  var currency=isIndia?'INR':'USD';
  window._cur=window._cur||{};
  window._cur[qid]=currency;
  var sym=currSym(currency);

  $('#btn-bt-'+qid).text(isIndia?'🇮🇳 Domestic':'🌍 Export')
    .css({'border-color':isIndia?'#2e7d32':'#185FA5','background':isIndia?'#e8f5e9':'#e3f2fd','color':isIndia?'#1b5e20':'#0c447c'});
  $('#cur-lbl-'+qid).text(currency).css('color',isIndia?'#1b5e20':'#0c447c');
  $('#gstin-row-'+qid).toggle(isIndia);
  $('#rate-hdr-'+qid).text('Rate '+sym);

  if(window._ep&&window._ep[qid]) $('#pt-'+qid).html(buildTable(qid,isIndia,currency));
  recalcPI(qid);
};

window.detectGstType=function(qid,gstin){
  if(!window._gt) window._gt={};
  gstin=String(gstin||'').trim().toUpperCase();
  if(gstin.length<2) return;
  window._gt[qid]=(gstin.substring(0,2)==='06')?'cgst_sgst':'igst';
  recalcPI(qid);
};

/* ════════════════════════════════════════
   SAVE PI DRAFT (Step 4)
════════════════════════════════════════ */
window.savePIFromPanel=function(qid){
  var items=(window._ep&&window._ep[qid])||[];
  var valid=items.filter(function(p){return p.name&&String(p.name).trim();});
  if(!valid.length){iipNotice('Product table mein name required','error');return;}

  var isIndia=window._bt&&window._bt[qid]==='domestic';
  var gstType=(window._gt&&window._gt[qid])||'igst';
  var gstin=String($('#gstin-'+qid).val()||'');
  // Send the GST rate exactly as shown on screen — "jo dikhaye wahi save ho"
  var gstRate=isIndia?(window._gstRate&&window._gstRate[qid]!=null?window._gstRate[qid]:18):0;
  var $b=$('#btn-save-pi-'+qid);
  var $st=$('#pi-gen-st-'+qid);
  $b.prop('disabled',true).text('⏳ Saving…');

  $.post(SGCIIP.ajaxUrl,{
    action:'sgc_iip_generate_pi_draft',
    nonce:SGCIIP.nonce,
    inquiry_id:qid,
    products:JSON.stringify(valid),
    buyer_type:isIndia?'domestic':'export',
    gst_type:gstType,
    gst_percent:gstRate,
    buyer_gstin:gstin
  },function(r){
    $b.prop('disabled',false);
    if(r.success){
      var d=r.data;
      window._pid[qid]=d.pi_id;
      window._pn[qid]=d.pi_number;
      var sym=currSym(d.currency);
      $b.text('🔄 Update PI Draft');
      $st.html('<span style="color:#1D9E75">✅ Draft: <strong>'+esc(d.pi_number)+'</strong> · '+sym+fmt2(d.grand_total)+'</span>');
      $('#sub-'+qid).val('Proforma Invoice '+d.pi_number+' — SGC LABS Scientific Glassware');
      $('#attach-badge-'+qid).html('<span style="display:inline-flex;align-items:center;gap:5px;background:#e8f5e9;color:#1b5e20;font-size:11px;padding:3px 10px;border-radius:10px">📎 '+esc(d.pi_number)+'.pdf — auto-attached on send</span>');
      $('#btn-send-'+qid).prop('disabled',false).css('opacity','1').attr('data-piid',d.pi_id);
      iipNotice('✅ PI Draft saved!');
    }else{
      $b.text(window._pid[qid]?'🔄 Update PI Draft':'💾 Generate PI Draft');
      $st.html('<span style="color:#c62828">❌ '+esc(r.data||'Failed')+'</span>');
      iipNotice(r.data||'Failed','error');
    }
  }).fail(function(){
    $b.prop('disabled',false).text(window._pid[qid]?'🔄 Update PI Draft':'💾 Generate PI Draft');
    $st.html('<span style="color:#c62828">❌ Server error</span>');
  });
};

/* ════════════════════════════════════════
   SEND PI EMAIL (Step 5)
════════════════════════════════════════ */
window.sendPIEmail=function(qid){
  var $b=$('#btn-send-'+qid);
  var piId=$b.attr('data-piid')||window._pid[qid];
  if(!piId){iipNotice('Step 4 mein PI Draft pehle save karo','error');return;}
  var subject=$('#sub-'+qid).val()||'';
  if(!subject){iipNotice('Subject required','error');return;}

  $b.prop('disabled',true).text('⏳ PDF ban rahi + Email ja rahi…');
  $('#send-st-'+qid).html('<span style="color:#6d28d9">PDF generate ho rahi hai, 15-30 seconds…</span>');

  $.post(SGCIIP.ajaxUrl,{
    action:'sgc_iip_send_pi_from_panel',
    nonce:SGCIIP.nonce,
    pi_id:piId,
    cc:$('#cc-'+qid).val()||'',
    bcc:$('#bcc-'+qid).val()||'',
    subject:subject,
    personal_note:$('#pnote-'+qid).val()||''
  },function(r){
    $b.prop('disabled',false).text('📤 Send to Buyer');
    if(r.success){
      var d=r.data;
      $('#send-st-'+qid).html('<div style="background:#e8f5e9;border-radius:5px;padding:8px;line-height:1.7">'
        +'<strong style="color:#1b5e20">✅ Email sent!</strong><br>'
        +'<span style="font-size:11px;color:#2e7d32">To: '+esc(d.to)+(d.cc?' | CC: '+esc(d.cc):'')+'</span><br>'
        +'<span style="font-size:11px;color:#2e7d32">PI: '+esc(d.pi_number)+' · PDF attached</span>'
        +'</div>');
      iipNotice('✅ PI buyer ko email ho gayi!');
      if(typeof loadInquiries==='function') setTimeout(loadInquiries,1500);
      if(typeof loadStats==='function') setTimeout(loadStats,1500);
      setTimeout(function(){openDetail(qid);},3000);
    }else{
      $('#send-st-'+qid).html('<span style="color:#c62828">❌ '+esc(r.data||'Email failed')+'</span>');
      iipNotice(r.data||'Email failed','error');
    }
  }).fail(function(){
    $b.prop('disabled',false).text('📤 Send to Buyer');
    $('#send-st-'+qid).html('<span style="color:#c62828">❌ Server error — retry</span>');
  });
};

/* ════════════════════════════════════════
   SAVE FOLLOW-UP (Step 6)
════════════════════════════════════════ */
window.saveFollowUp=function(qid){
  var d=$('#fu-d-'+qid).val();
  var t=$('#fu-t-'+qid).val();
  var n=$('#fu-n-'+qid).val()||'';
  if(!d){iipNotice('Date select karo','error');return;}
  var $btn=$(document).find('button[onclick="saveFollowUp('+qid+')"]');
  $btn.prop('disabled',true).text('⏳ Scheduling…');

  $.post(SGCIIP.ajaxUrl,{
    action:'sgc_iip_save_followup',
    nonce:SGCIIP.nonce,
    inquiry_id:qid,
    followup_date:d,
    channel:t,
    note:n
  },function(r){
    $btn.prop('disabled',false).text('📅 Schedule — us date ko remind karo');
    if(r.success){
      $('#fu-st-'+qid).html('<span style="color:#1D9E75">✅ Scheduled: '+esc(d)+' via '+esc(t)+'</span>');
      iipNotice('✅ Follow-up scheduled!');
    }else{
      $('#fu-st-'+qid).html('<span style="color:#c62828">❌ '+esc(r.data||'Failed')+'</span>');
      iipNotice(r.data||'Failed','error');
    }
  });
};

/* ════════════════════════════════════════
   AI EXTRACT
════════════════════════════════════════ */
window.doExtract=function(qid){
  var $b=$('#btn-ex-'+qid);
  $b.prop('disabled',true).text('⏳ AI extract ho raha hai…');

  $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_get_inquiry',nonce:SGCIIP.nonce,id:qid},function(r){
    var msg=(r.success&&r.data.raw_message)?r.data.raw_message:$('#msg-'+qid).val()||'';
    if(!msg){
      $b.prop('disabled',false).text('⚡ Extract with AI — auto-fill table + rates');
      iipNotice('Message nahi mila — paste karo','error'); return;
    }
    $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_smart_extract',nonce:SGCIIP.nonce,text:msg,inq_id:qid},function(er){
      $b.prop('disabled',false).text('⚡ Re-extract');
      if(!er.success){iipNotice(er.data||'Extract failed','error');return;}
      var items=er.data.items||[];
      if(!items.length){iipNotice('Koi product nahi mila','error');return;}
      if(!window._ep) window._ep={};
      window._ep[qid]=items.map(function(p){
        return{name:p.name||'',qty:p.qty||1,unit:p.unit||'pcs',rate:'',sku:'',hsn:''};
      });
      var done=0,total=items.length;
      items.forEach(function(it,i){
        if(!it.name||it.name.length<3){done++;if(done===total)_redrawAfterExtract(qid);return;}
        $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_wc_product_search',nonce:SGCIIP.nonce,query:it.name},function(wr){
          if(wr.success&&wr.data&&wr.data.length&&window._ep[qid]&&window._ep[qid][i]){
            var m=wr.data[0],p=window._ep[qid][i];
            if(!p.sku)  p.sku =m.sku ||'';
            if(!p.hsn)  p.hsn =m.hsn ||'';
            if(!p.unit&&m.unit) p.unit=m.unit;
            if(!p.rate) p.rate=m.b2b_price_usd||m.price_usd||'';
          }
          done++;if(done===total)_redrawAfterExtract(qid);
        }).fail(function(){done++;if(done===total)_redrawAfterExtract(qid);});
      });
    });
  }).fail(function(){
    $b.prop('disabled',false).text('⚡ Extract with AI');
    iipNotice('Server error','error');
  });
};

function _redrawAfterExtract(qid){
  var isIndia=window._bt&&window._bt[qid]==='domestic';
  var currency=(window._cur&&window._cur[qid])||(isIndia?'INR':'USD');
  $('#pt-'+qid).html(buildTable(qid,isIndia,currency));
  $('#ai-rate-badge-'+qid).show();
  recalcPI(qid);
  iipNotice('✅ Products extracted! Rate verify karo phir Save Draft karo.');
}

/* ════════════════════════════════════════
   PANEL HELPERS
════════════════════════════════════════ */
function ob(){return 'display:inline-flex;align-items:center;gap:4px;padding:6px 10px;border-radius:5px;font-size:11px;font-weight:600;cursor:pointer;background:transparent;color:#333;border:1px solid #ccc;';}

window.chStatus=function(id,st){
  if(!st)return;
  $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_update_status',nonce:SGCIIP.nonce,id:id,status:st},function(r){
    if(r.success){iipNotice('✅ Status updated!');openDetail(id);if(typeof loadInquiries==='function')loadInquiries();}
    else iipNotice('Error','error');
  });
};
window.saveNote=function(id){
  $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_save_note',nonce:SGCIIP.nonce,id:id,note:$('#nt-'+id).val()},function(r){
    iipNotice(r.success?'✅ Note saved!':'Error',r.success?'success':'error');
  });
};
window.markAdv=function(id){
  var a=$('#adv-'+id).val();
  if(!a||parseFloat(a)<=0){iipNotice('Amount daalo','error');return;}
  $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_mark_advance',nonce:SGCIIP.nonce,id:id,amount:a},function(r){
    iipNotice(r.success?'✅ Advance marked!':r.data,r.success?'success':'error');
    if(r.success)openDetail(id);
  });
};
window.doSendCRM=function(id){
  $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_send_to_crm',nonce:SGCIIP.nonce,inquiry_id:id},function(r){
    iipNotice(r.success?'✅ Added to CRM!':'CRM error',r.success?'success':'error');
  });
};
window.doResendReply=function(id){
  $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_resend_autoreply',nonce:SGCIIP.nonce,id:id},function(r){
    iipNotice(r.success?'✅ Auto-reply resent!':'Error',r.success?'success':'error');
  });
};
window.viewPDF=function(piId){
  window.open(SGCIIP.adminUrl+'admin.php?page=sgc-iip&tab=proforma&preview_pi='+piId,'_blank');
};
window.deleteInquiry=function(id){
  if(!confirm('Is inquiry ko delete karna chahte ho?')) return;
  $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_delete_inquiry',nonce:SGCIIP.nonce,id:id},function(r){
    if(r.success){iipNotice('✅ Deleted.');closeSidePanel();if(typeof loadInquiries==='function')setTimeout(loadInquiries,500);}
    else iipNotice('Delete failed','error');
  });
};

/* ── v1.8.0: Edit Inquiry fields ── */
window.openEditInquiry=function(id){
  var data=window._inqData[id];
  if(!data){iipNotice('Inquiry data not loaded — please refresh panel','error');return;}
  // Remove any existing edit panel
  $('#inq-edit-panel-'+id).remove();

  // Build inline edit panel
  var clList='<datalist id="edit-country-list-'+id+'">'
    +['Afghanistan','Albania','Algeria','Argentina','Armenia','Australia','Austria','Azerbaijan','Bahrain','Bangladesh','Belgium','Brazil','Canada','Chile','China','Colombia','Croatia','Cyprus','Czech Republic','Denmark','Egypt','Ethiopia','Finland','France','Georgia','Germany','Ghana','Greece','Hungary','Iceland','India','Indonesia','Iran','Iraq','Ireland','Israel','Italy','Jamaica','Japan','Jordan','Kazakhstan','Kenya','Kuwait','Lebanon','Libya','Malaysia','Maldives','Malta','Mexico','Morocco','Myanmar','Nepal','Netherlands','New Zealand','Nigeria','Norway','Oman','Pakistan','Palestine','Panama','Philippines','Poland','Portugal','Qatar','Romania','Russia','Saudi Arabia','Senegal','Serbia','Singapore','Slovakia','South Africa','South Korea','Spain','Sri Lanka','Sudan','Sweden','Switzerland','Syria','Taiwan','Tanzania','Thailand','Turkey','Uganda','Ukraine','United Arab Emirates','United Kingdom','United States','Uzbekistan','Venezuela','Vietnam','Yemen','Zambia','Zimbabwe']
    .map(function(c){return '<option value="'+c+'">';}).join('')+'</datalist>';

  var panel='<div id="inq-edit-panel-'+id+'" style="margin:12px 16px;background:#f8fafc;border:1px solid #c7d2e0;border-radius:8px;padding:14px">'
    +'<div style="font-size:11px;font-weight:700;color:#1a1a2e;margin-bottom:10px">✏️ Edit Buyer Details</div>'
    +clList
    +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">'
    +'<div><label style="font-size:10px;color:#555;font-weight:600">Name *</label>'
    +'<input id="ei-name-'+id+'" type="text" value="'+esc(data.name||'')+'" class="iip-input" style="width:100%;font-size:12px"></div>'
    +'<div><label style="font-size:10px;color:#555;font-weight:600">Email</label>'
    +'<input id="ei-email-'+id+'" type="email" value="'+esc(data.email||'')+'" class="iip-input" style="width:100%;font-size:12px"></div>'
    +'<div><label style="font-size:10px;color:#555;font-weight:600">Phone</label>'
    +'<input id="ei-phone-'+id+'" type="text" value="'+esc(data.phone||'')+'" class="iip-input" style="width:100%;font-size:12px"></div>'
    +'<div><label style="font-size:10px;color:#555;font-weight:600">Company</label>'
    +'<input id="ei-company-'+id+'" type="text" value="'+esc(data.company||'')+'" class="iip-input" style="width:100%;font-size:12px"></div>'
    +'<div style="grid-column:span 2"><label style="font-size:10px;color:#555;font-weight:600">Country</label>'
    +'<input id="ei-country-'+id+'" type="text" value="'+esc(data.country||'')+'" list="edit-country-list-'+id+'" class="iip-input" autocomplete="off" style="width:100%;font-size:12px"></div>'
    +'</div>'
    +'<div id="ei-err-'+id+'" style="color:#dc2626;font-size:11px;display:none;margin-bottom:6px"></div>'
    +'<div style="display:flex;gap:6px">'
    +'<button onclick="saveEditInquiry('+id+')" style="font-size:12px;padding:6px 14px;background:#1a1a2e;color:#fff;border:none;border-radius:5px;cursor:pointer;font-weight:600">💾 Save Changes</button>'
    +'<button onclick="$(\'#inq-edit-panel-'+id+'\').remove()" style="font-size:12px;padding:6px 12px;background:none;border:1px solid #d0d0d0;border-radius:5px;cursor:pointer;color:#555">Cancel</button>'
    +'</div></div>';

  // Insert below Step 1 buyer info section
  $('#iip-detail-panel .iip-detail-actions').before(panel);
  // Fallback: append to scrollable body if selector not found
  if(!$('#inq-edit-panel-'+id).length){
    $('#iip-detail-panel > div > div').eq(1).prepend(panel);
  }
  $('#ei-name-'+id).focus();
};

window.saveEditInquiry=function(id){
  var name    = $.trim($('#ei-name-'+id).val());
  var email   = $.trim($('#ei-email-'+id).val());
  var phone   = $.trim($('#ei-phone-'+id).val());
  var company = $.trim($('#ei-company-'+id).val());
  var country = $.trim($('#ei-country-'+id).val());

  if(!name){ $('#ei-err-'+id).text('Name is required.').show(); return; }
  if(email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){ $('#ei-err-'+id).text('Invalid email address.').show(); return; }
  $('#ei-err-'+id).hide();

  var $btn=$('#inq-edit-panel-'+id+' button').first();
  $btn.prop('disabled',true).text('⏳ Saving…');

  $.post(SGCIIP.ajaxUrl,{
    action  : 'sgc_iip_update_inquiry',
    nonce   : SGCIIP.nonce,
    id      : id,
    name    : name,
    email   : email,
    phone   : phone,
    company : company,
    country : country
  },function(r){
    if(r.success){
      iipNotice('✅ Inquiry updated!','success');
      $('#inq-edit-panel-'+id).remove();
      // Reload panel with fresh data
      if(typeof openDetail==='function') setTimeout(function(){openDetail(id);},400);
      if(typeof loadInquiries==='function') setTimeout(loadInquiries,600);
    }else{
      $('#ei-err-'+id).text(r.data||'Save failed').show();
      $btn.prop('disabled',false).text('💾 Save Changes');
    }
  }).fail(function(){
    $('#ei-err-'+id).text('Network error — try again').show();
    $btn.prop('disabled',false).text('💾 Save Changes');
  });
};
window.deletePI=function(piId,qid){
  if(!confirm('Is PI ko cancel karna chahte ho?')) return;
  $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_delete_pi',nonce:SGCIIP.nonce,pi_id:piId},function(r){
    iipNotice(r.success?'✅ PI cancelled.':'Failed',r.success?'success':'error');
    if(r.success&&qid) setTimeout(function(){openDetail(qid);},1000);
  });
};

/* ════════════════════════════════════════
   WC LIVE SEARCH
════════════════════════════════════════ */
var _wcTimer=null;
window.wcLiveSearch=function(el){
  var $el=$(el);
  var val=$el.val().trim();
  var qid=$el.data('q');
  var idx=$el.data('i');
  var $dd=$('#wcs-'+qid+'-'+idx);
  clearTimeout(_wcTimer);
  if(val.length<3){$dd.hide().empty();return;}
  _wcTimer=setTimeout(function(){
    $dd.html('<div style="padding:8px;font-size:11px;color:#888">Searching…</div>').show();
    $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_wc_product_search',nonce:SGCIIP.nonce,query:val},function(r){
      if(!r.success||!r.data||!r.data.length){$dd.html('<div style="padding:8px;font-size:11px;color:#c62828">No WC match</div>');return;}
      var html='';
      r.data.slice(0,8).forEach(function(p){
        var price=p.b2b_price_usd||p.price_usd||'';
        var encoded=encodeURIComponent(JSON.stringify(p));
        html+='<div onclick="wcPick('+qid+','+idx+',\''+encoded+'\')" style="padding:7px 10px;cursor:pointer;border-bottom:0.5px solid #f0f0f0;font-size:11px" onmouseover="this.style.background=\'#f0f4ff\'" onmouseout="this.style.background=\'\'"><div style="font-weight:600;color:#1a1a2e">'+esc(p.name)+'</div><div style="color:#888;font-size:10px">SKU: '+esc(p.sku||'—')+' | HSN: '+esc(p.hsn||'—')+' | '+(price?'$'+price:'No price')+'</div></div>';
      });
      $dd.html(html).show();
    });
  },400);
  $(document).one('click.wcsearch',function(e){
    if(!$(e.target).closest($dd).length&&!$(e.target).is($el)) $dd.hide();
  });
};

window.wcPick=function(qid,idx,encoded){
  try{
    var p=JSON.parse(decodeURIComponent(encoded));
    if(!window._ep||!window._ep[qid]||window._ep[qid][idx]===undefined) return;
    var row=window._ep[qid][idx];
    if(p.name)  row.name=p.name;
    if(p.sku)   row.sku =p.sku;
    if(p.hsn)   row.hsn =p.hsn;
    if(p.unit)  row.unit=p.unit;
    var pr=p.b2b_price_usd||p.price_usd||'';
    if(pr) row.rate=pr;

    var $row=$('#pr-'+qid+'-'+idx);
    $row.find('[data-f="name"]').val(row.name);
    $row.find('[data-f="sku"]').val(row.sku);
    $row.find('[data-f="hsn"]').val(row.hsn);
    $row.find('[data-f="rate"]').val(row.rate);
    $row.find('[data-f="unit"] option').prop('selected',false).filter(function(){
      return $(this).val()===row.unit||$(this).text()===row.unit;
    }).prop('selected',true);

    $('#wcs-'+qid+'-'+idx).hide().empty();

    var qty=parseFloat(row.qty||0),rate=parseFloat(row.rate||0);
    var lt=Math.round(qty*rate*100)/100;
    var isIndia=window._bt&&window._bt[qid]==='domestic';
    var sym=currSym((window._cur&&window._cur[qid])||(isIndia?'INR':'USD'));
    $('#lt-'+qid+'-'+idx).text(lt>0?sym+fmt2(lt):'—');
    recalcPI(qid);
    iipNotice('✅ '+row.name+' selected');
  }catch(e){iipNotice('WC pick error','error');}
};

/* ── Init ── */
window._ep={};window._bt={};window._gt={};window._pid={};window._pn={};window._cur={};
$(function(){
  if(typeof SGCIIP!=='undefined') SGCIIP.adminUrl=SGCIIP.adminUrl||(window.location.origin+'/wp-admin/');
});

})(jQuery);
