<?php defined('ABSPATH')||exit; ?>
<div id="iip-followup-wrap">

<div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;flex-wrap:wrap">
  <h3 style="margin:0;font-size:14px;font-weight:600">⏰ Follow-up Automation Rules</h3>
  <label style="font-size:12px;display:flex;align-items:center;gap:6px;background:#fff;border:1px solid #e0e0e0;padding:6px 12px;border-radius:5px">
    <input type="checkbox" id="fu-enabled" <?php checked(get_option('sgc_iip_followup_enabled','1'),'1'); ?>> Enable Automation (runs hourly)
  </label>
  <button class="iip-btn primary" id="btn-new-rule" style="margin-left:auto">+ New Rule</button>
</div>

<div id="fu-rules-list"></div>

<!-- Rule Editor Modal -->
<div class="iip-modal" id="modal-fu-rule" style="display:none">
  <div class="iip-modal-inner">
    <div class="iip-modal-hdr"><h3 id="fu-modal-title">New Follow-up Rule</h3><button onclick="closeModal('modal-fu-rule')">✕</button></div>
    <div class="iip-modal-body">
      <input type="hidden" id="fu-rule-id" value="0">
      <div class="iip-fg"><label>Rule Name</label><input type="text" id="fu-name" class="iip-input" style="width:100%" placeholder="e.g. PI Follow-up Day 3"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
        <div class="iip-fg"><label>Trigger Status</label><select id="fu-trigger" class="iip-select" style="width:100%">
          <option value="pi_sent">PI Sent</option><option value="new">New Inquiry</option><option value="sample">Sample Sent</option><option value="replied">Replied</option>
        </select></div>
        <div class="iip-fg"><label>Send After (days)</label><input type="number" id="fu-days" class="iip-input" value="3" min="1" max="30" style="width:100%"></div>
        <div class="iip-fg"><label>Channel</label><select id="fu-channel" class="iip-select" style="width:100%">
          <option value="email">Email</option><option value="whatsapp">WhatsApp</option><option value="both">Both</option>
        </select></div>
      </div>
      <div class="iip-fg"><label>Email Subject</label><input type="text" id="fu-subject" class="iip-input" style="width:100%" placeholder="Variables: {name} {pi_number} {pi_date} {valid_until}"></div>
      <div class="iip-fg"><label>Message Body (Email + WhatsApp)</label>
        <textarea id="fu-message" class="iip-input" rows="6" style="width:100%" placeholder="Dear {name}, ..."></textarea>
        <small style="color:#888;font-size:11px">Variables: {name} {company} {country} {product} {pi_number} {pi_date} {valid_until}</small>
      </div>
    </div>
    <div class="iip-modal-ftr">
      <button class="iip-btn" onclick="closeModal('modal-fu-rule')">Cancel</button>
      <button class="iip-btn primary" id="btn-save-rule">Save Rule</button>
    </div>
  </div>
</div>
</div>

<script>
function loadRules(){
  jQuery.post(SGCIIP.ajaxUrl,{action:'sgc_iip_fu_get_rules',nonce:SGCIIP.nonce},function(r){
    if(!r.success)return;
    var html=r.data.length?r.data.map(function(rule){
      var active=rule.status==='active';
      return '<div class="iip-rule-card'+(active?'':' inactive')+'">'
        +'<div style="flex:1"><strong style="font-size:13px">'+escH(rule.name)+'</strong>'
        +'<div style="font-size:11px;color:#888;margin-top:3px">'
        +'Trigger: <b>'+rule.trigger_status+'</b> · After <b>'+rule.delay_days+' days</b> · Channel: <b>'+rule.channel+'</b>'
        +'<br>Runs: '+rule.run_count+' times</div>'
        +'<div style="font-size:11px;color:#aaa;margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:400px">'+escH(rule.message.substring(0,100))+'…</div></div>'
        +'<div style="display:flex;flex-direction:column;gap:6px;margin-left:12px;flex-shrink:0">'
        +'<button class="iip-btn sm" onclick="editRule('+rule.id+')">Edit</button>'
        +'<button class="iip-btn sm" onclick="toggleRule('+rule.id+',\''+(active?'inactive':'active')+'\')">'+( active?'Pause':'Activate')+'</button>'
        +'<button class="iip-btn sm danger" onclick="deleteRule('+rule.id+')">Delete</button>'
        +'</div></div>';
    }).join('') : '<div style="color:#aaa;padding:20px;text-align:center">No rules. Create your first follow-up rule!</div>';
    jQuery('#fu-rules-list').html(html);
  });
}
function escH(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function editRule(id){
  jQuery.post(SGCIIP.ajaxUrl,{action:'sgc_iip_fu_get_rules',nonce:SGCIIP.nonce},function(r){
    var rule=(r.data||[]).find(function(x){return x.id==id;});
    if(!rule)return;
    jQuery('#fu-modal-title').text('Edit Rule');
    jQuery('#fu-rule-id').val(rule.id);
    jQuery('#fu-name').val(rule.name);
    jQuery('#fu-trigger').val(rule.trigger_status);
    jQuery('#fu-days').val(rule.delay_days);
    jQuery('#fu-channel').val(rule.channel);
    jQuery('#fu-subject').val(rule.subject||'');
    jQuery('#fu-message').val(rule.message);
    openModal('modal-fu-rule');
  });
}
function toggleRule(id,status){ jQuery.post(SGCIIP.ajaxUrl,{action:'sgc_iip_fu_toggle_rule',nonce:SGCIIP.nonce,rule_id:id,status:status},function(){ loadRules(); }); }
function deleteRule(id){ if(!confirm('Delete this rule?'))return; jQuery.post(SGCIIP.ajaxUrl,{action:'sgc_iip_fu_delete_rule',nonce:SGCIIP.nonce,rule_id:id},function(){ loadRules(); }); }
jQuery(function($){
  loadRules();
  $('#fu-enabled').on('change',function(){ jQuery.ajax({url:ajaxurl,method:'POST',data:{action:'wp_ajax_sgc_iip_save_pi_settings',sgc_iip_followup_enabled:$(this).is(':checked')?'1':'0',nonce:SGCIIP.nonce}}); });
  $('#btn-new-rule').on('click',function(){ $('#fu-modal-title').text('New Rule'); $('#fu-rule-id').val(0); $('[id^=fu-]').not('#fu-rule-id,#fu-modal-title').val(''); $('#fu-days').val(3); openModal('modal-fu-rule'); });
  $('#btn-save-rule').on('click',function(){
    $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_fu_save_rule',nonce:SGCIIP.nonce,rule_id:$('#fu-rule-id').val(),
      name:$('#fu-name').val(),trigger_status:$('#fu-trigger').val(),delay_days:$('#fu-days').val(),
      channel:$('#fu-channel').val(),subject:$('#fu-subject').val(),message:$('#fu-message').val()
    },function(r){ closeModal('modal-fu-rule'); if(r.success){iipNotice('Rule saved!');loadRules();} else iipNotice(r.data,'error'); });
  });
});
</script>
