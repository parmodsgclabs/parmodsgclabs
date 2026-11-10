<?php defined('ABSPATH')||exit; ?>
<div id="iip-settings-wrap">
<div style="max-width:720px">

<!-- Company Settings -->
<div class="iip-box">
<h3>🏢 Company & Export Details</h3>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
  <?php $fields=['sgc_iip_company_name'=>'Company Name','sgc_iip_company_address'=>'Address','sgc_iip_company_gst'=>'GST Number','sgc_iip_company_pan'=>'PAN','sgc_iip_company_iec'=>'IEC Code (Export)','sgc_iip_company_phone'=>'Phone','sgc_iip_company_email'=>'Email','sgc_iip_company_website'=>'Website']; foreach($fields as $k=>$l): ?>
  <div class="iip-fg"><label><?php echo esc_html($l); ?></label><input type="text" class="iip-input iip-company-field" name="<?php echo esc_attr($k); ?>" value="<?php echo esc_attr(get_option($k,'')); ?>" style="width:100%"></div>
  <?php endforeach; ?>
</div>
<div style="margin-top:10px;display:flex;gap:10px;align-items:center">
<label class="iip-fg" style="margin:0"><span>Signature Image URL</span><input type="text" name="sgc_iip_signature_img" class="iip-input iip-company-field" value="<?php echo esc_attr(get_option('sgc_iip_signature_img','')); ?>" style="width:300px" placeholder="https://... or upload below"></label>
<label class="iip-fg" style="margin:0"><span>Seal/Stamp URL</span><input type="text" name="sgc_iip_seal_img" class="iip-input iip-company-field" value="<?php echo esc_attr(get_option('sgc_iip_seal_img','')); ?>" style="width:300px"></label>
</div>
<button class="iip-btn primary" id="btn-save-company" style="margin-top:12px">💾 Save Company Settings</button>
<div id="company-notice" style="display:none;margin-top:8px;font-size:12px;color:#2e7d32"></div>
</div>

<!-- Bank Details — Export + Domestic -->
<div class="iip-box">
<h3>🏦 Bank Details</h3>
<p style="font-size:12px;color:#666;margin:-8px 0 16px">Export (International) aur Domestic ke liye alag details add karo. PI banate waqt buyer ka country dekh ke auto-select hoga.</p>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

<!-- Export Bank — SWIFT -->
<div style="background:#eaf3ff;border:1.5px solid #bfdbfe;border-radius:10px;padding:16px">
  <div style="font-size:13px;font-weight:700;color:#1d4ed8;margin-bottom:12px;display:flex;align-items:center;gap:6px">
    🌍 Export / International Bank <span style="font-size:10px;font-weight:400;background:#1d4ed8;color:#fff;padding:1px 7px;border-radius:8px">SWIFT / Wire Transfer</span>
  </div>
  <?php $ef=['sgc_iip_export_bank_beneficiary'=>'Beneficiary Name','sgc_iip_export_bank_name'=>'Bank Name','sgc_iip_export_bank_branch'=>'Branch / City','sgc_iip_export_bank_account'=>'Account Number','sgc_iip_export_bank_ifsc'=>'IFSC Code (optional)','sgc_iip_export_bank_swift'=>'SWIFT / BIC Code ★','sgc_iip_export_bank_address'=>'Bank Address (for LC)']; foreach($ef as $k=>$l): ?>
  <div class="iip-fg" style="margin-bottom:8px"><label style="font-size:11px"><?php echo esc_html($l); ?></label><input type="text" class="iip-input iip-export-bank-field" name="<?php echo esc_attr($k); ?>" value="<?php echo esc_attr(get_option($k,get_option(str_replace('export_','',str_replace('sgc_iip_export_','sgc_iip_',$k)),''))) ; ?>" style="width:100%;font-size:12px"></div>
  <?php endforeach; ?>

  <!-- Export Bank QR — account link for international -->
  <div style="margin-top:12px;padding-top:10px;border-top:1px solid #bfdbfe">
    <div style="font-size:11px;font-weight:600;color:#1d4ed8;margin-bottom:6px">🔗 Wire Transfer Reference QR (optional)</div>
    <div class="iip-fg" style="margin-bottom:6px"><label style="font-size:11px">SWIFT Reference URL / Payment link</label>
    <input type="text" class="iip-input iip-export-bank-field" name="sgc_iip_export_bank_qr_url" id="export-bank-qr-url" value="<?php echo esc_attr(get_option('sgc_iip_export_bank_qr_url','')); ?>" style="width:100%;font-size:12px" placeholder="https://yourbank.com/payment/ACCOUNTNO or SWIFT reference URL"></div>
    <div style="display:flex;align-items:center;gap:10px">
      <div id="export-qr-preview" style="width:80px;height:80px;border:1px solid #bfdbfe;border-radius:6px;overflow:hidden;display:flex;align-items:center;justify-content:center;background:#fff;font-size:10px;color:#aaa">
        <?php
          $eurl = get_option('sgc_iip_export_bank_qr_url','');
          if($eurl): ?><img src="https://quickchart.io/qr?text=<?php echo urlencode($eurl); ?>&width=80&height=80" width="80" height="80" style="display:block"><?php else: ?>No QR<?php endif; ?>
      </div>
      <div style="font-size:10px;color:#666">URL daalne pe QR auto-generate hoga.<br>International buyer scan kare → payment link khule.</div>
    </div>
  </div>
</div>

<!-- Domestic Bank — IFSC -->
<div style="background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:10px;padding:16px">
  <div style="font-size:13px;font-weight:700;color:#15803d;margin-bottom:12px;display:flex;align-items:center;gap:6px">
    🇮🇳 Domestic / India Bank <span style="font-size:10px;font-weight:400;background:#15803d;color:#fff;padding:1px 7px;border-radius:8px">NEFT / RTGS / IMPS</span>
  </div>
  <?php $df=['sgc_iip_dom_bank_beneficiary'=>'Beneficiary Name','sgc_iip_dom_bank_name'=>'Bank Name','sgc_iip_dom_bank_branch'=>'Branch','sgc_iip_dom_bank_account'=>'Account Number','sgc_iip_dom_bank_ifsc'=>'IFSC Code ★','sgc_iip_dom_bank_type'=>'Account Type (Savings/Current)']; foreach($df as $k=>$l): ?>
  <div class="iip-fg" style="margin-bottom:8px"><label style="font-size:11px"><?php echo esc_html($l); ?></label><input type="text" class="iip-input iip-dom-bank-field" name="<?php echo esc_attr($k); ?>" value="<?php echo esc_attr(get_option($k,get_option(str_replace('sgc_iip_dom_','sgc_iip_',$k),''))); ?>" style="width:100%;font-size:12px"></div>
  <?php endforeach; ?>

  <!-- Domestic BHIM / UPI QR -->
  <div style="margin-top:12px;padding-top:10px;border-top:1px solid #bbf7d0">
    <div style="font-size:11px;font-weight:600;color:#15803d;margin-bottom:6px">📱 UPI / BHIM QR (auto-generates)</div>
    <div class="iip-fg" style="margin-bottom:8px"><label style="font-size:11px">UPI ID</label>
    <input type="text" class="iip-input iip-dom-bank-field" name="sgc_iip_upi_id" id="dom-upi-id" value="<?php echo esc_attr(get_option('sgc_iip_upi_id','')); ?>" style="width:100%;font-size:12px;font-family:monospace" placeholder="yourname@bankname" oninput="updateDomQr(this.value)"></div>
    <div class="iip-fg" style="margin-bottom:8px"><label style="font-size:11px">UPI Display Name (on QR)</label>
    <input type="text" class="iip-input iip-dom-bank-field" name="sgc_iip_upi_name" id="dom-upi-name" value="<?php echo esc_attr(get_option('sgc_iip_upi_name',get_option('sgc_iip_company_name','Scientific Glass Creations'))); ?>" style="width:100%;font-size:12px" oninput="updateDomQr(document.getElementById('dom-upi-id').value)"></div>
    <div style="display:flex;align-items:center;gap:10px">
      <div id="dom-qr-preview" style="width:80px;height:80px;border:1px solid #bbf7d0;border-radius:6px;overflow:hidden;display:flex;align-items:center;justify-content:center;background:#fff;font-size:10px;color:#aaa">
        <?php
          $upi=$e=get_option('sgc_iip_upi_id',''); $gpay=get_option('sgc_iip_upi_name',get_option('sgc_iip_company_name','Scientific Glass Creations'));
          if($upi): $qu='https://quickchart.io/qr?text='.urlencode('upi://pay?pa='.$upi.'&pn='.$gpay).'&width=80&height=80'; ?>
          <img id="dom-qr-img" src="<?php echo esc_url($qu); ?>" width="80" height="80"><?php else: ?>
          <span id="dom-qr-img-wrap">UPI ID daalo</span><?php endif; ?>
      </div>
      <div style="font-size:10px;color:#666">UPI ID se QR auto-generate.<br>Domestic buyer scan → GPay/PhonePe payment.</div>
    </div>
  </div>
</div>

</div><!-- end grid -->

<div style="margin-top:14px;display:flex;gap:10px">
  <button class="iip-btn primary" id="btn-save-export-bank">💾 Save Export Bank</button>
  <button class="iip-btn" id="btn-save-dom-bank" style="background:#f0fdf4;border-color:#bbf7d0;color:#15803d">💾 Save Domestic Bank</button>
</div>
<div id="bank-notice" style="display:none;margin-top:8px;font-size:12px;color:#2e7d32"></div>
</div>

<!-- PI Defaults -->
<div class="iip-box">
<h3>📄 Proforma Invoice Defaults</h3>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
  <div class="iip-fg"><label>PI Prefix</label><input type="text" name="sgc_iip_pi_prefix" class="iip-input iip-pi-field" value="<?php echo esc_attr(get_option('sgc_iip_pi_prefix','SGC')); ?>" style="width:100%"></div>
  <div class="iip-fg"><label>Default Validity (days)</label><input type="number" name="sgc_iip_pi_validity" class="iip-input iip-pi-field" value="<?php echo esc_attr(get_option('sgc_iip_pi_validity','30')); ?>" style="width:100%"></div>
  <div class="iip-fg"><label>Default Incoterm</label><select name="sgc_iip_default_incoterm" class="iip-select iip-pi-field" style="width:100%"><?php foreach(['FOB','CIF','EXW','DDP','FCA','CFR'] as $inc) echo '<option'.selected(get_option('sgc_iip_default_incoterm','FOB'),$inc,false).'>'.esc_html($inc).'</option>'; ?></select></div>
  <div class="iip-fg"><label>Default Port</label><input type="text" name="sgc_iip_default_port" class="iip-input iip-pi-field" value="<?php echo esc_attr(get_option('sgc_iip_default_port','New Delhi, India')); ?>" style="width:100%"></div>
  <div class="iip-fg"><label>Default Export Freight (USD) <small style="color:#888">Auto-added on non-India PI</small></label><input type="number" name="sgc_iip_export_freight" class="iip-input iip-pi-field" value="<?php echo esc_attr(get_option('sgc_iip_export_freight','0')); ?>" style="width:100%" step="0.01" placeholder="e.g. 150"></div>
  <div class="iip-fg"><label>Default Domestic Freight (INR) <small style="color:#888">Auto-added on India PI</small></label><input type="number" name="sgc_iip_domestic_freight" class="iip-input iip-pi-field" value="<?php echo esc_attr(get_option('sgc_iip_domestic_freight','0')); ?>" style="width:100%" step="0.01" placeholder="e.g. 500"></div>
  <div class="iip-fg"><label>Payment Threshold (USD) <small style="color:#888">Below this = 100% advance</small></label>
    <input type="number" name="sgc_iip_payment_threshold_usd" class="iip-input iip-pi-field" value="<?php echo esc_attr(get_option('sgc_iip_payment_threshold_usd','500')); ?>" style="width:100%" placeholder="500">
  </div>
  <div class="iip-fg"><label>Default Payment Terms</label>
    <input type="text" name="sgc_iip_default_payment" class="iip-input iip-pi-field" value="<?php echo esc_attr(get_option('sgc_iip_default_payment','50% advance with order, 50% before dispatch')); ?>" style="width:100%">
  </div>
  <div style="grid-column:span 2;font-size:11px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:5px;padding:8px 12px;color:#1d4ed8">
    💡 <strong>Auto Payment Terms:</strong> Order &lt; $500 → 100% advance | Order ≥ $500 → 50%+50% | Applied automatically on all PIs and templates
  </div>
  <div class="iip-fg" style="grid-column:span 2"><label>Default Delivery Time</label><input type="text" name="sgc_iip_default_delivery" class="iip-input iip-pi-field" value="<?php echo esc_attr(get_option('sgc_iip_default_delivery','15-20 working days')); ?>" style="width:100%"></div>
  <div class="iip-fg"><label>Default PI Template</label><select name="sgc_iip_default_template" class="iip-select iip-pi-field" style="width:100%"><?php foreach(['navy'=>'Navy (Brand)','export'=>'Export Professional','minimal'=>'Minimal Clean'] as $k=>$l) echo '<option value="'.esc_attr($k).'"'.selected(get_option('sgc_iip_default_template','navy'),$k,false).'>'.esc_html($l).'</option>'; ?></select></div>
  <div class="iip-fg"><label>Admin CC Email</label><input type="email" name="sgc_iip_cc_email" class="iip-input iip-pi-field" value="<?php echo esc_attr(get_option('sgc_iip_cc_email',get_option('admin_email'))); ?>" style="width:100%"></div>
</div>
<button class="iip-btn primary" id="btn-save-pi" style="margin-top:12px">💾 Save PI Defaults</button>
<div id="pi-notice" style="display:none;margin-top:8px;font-size:12px;color:#2e7d32"></div>
</div>

<!-- AI Settings -->
<div class="iip-box">
<h3>🤖 AI Engine (DeepSeek Default)</h3>
<?php foreach(['deepseek'=>'DeepSeek (Default — Free tier available)','openai'=>'OpenAI GPT-4o mini','claude'=>'Claude Haiku'] as $pid=>$plabel): ?>
<div class="iip-fg">
  <label><?php echo esc_html($plabel); ?> API Key</label>
  <div style="display:flex;gap:8px">
    <input type="password" class="iip-input ai-key-input" data-provider="<?php echo esc_attr($pid); ?>" name="sgc_iip_ai_key_<?php echo esc_attr($pid); ?>" value="<?php echo esc_attr(get_option('sgc_iip_ai_key_'.$pid,'')); ?>" style="flex:1" placeholder="<?php echo $pid==='deepseek'?'sk-... (platform.deepseek.com)':'API key'; ?>">
    <button class="iip-btn" data-provider="<?php echo esc_attr($pid); ?>" id="test-<?php echo esc_attr($pid); ?>">Test</button>
    <span class="ai-test-result" id="res-<?php echo esc_attr($pid); ?>" style="font-size:12px;line-height:34px"></span>
  </div>
</div>
<?php endforeach; ?>
<div class="iip-fg"><label>Default Provider</label><select name="sgc_iip_ai_provider" class="iip-select iip-ai-field" style="width:200px"><?php foreach(['deepseek','openai','claude'] as $p) echo '<option value="'.esc_attr($p).'"'.selected(get_option('sgc_iip_ai_provider','deepseek'),$p,false).'>'.ucfirst($p).'</option>'; ?></select></div>
<button class="iip-btn primary" id="btn-save-ai">💾 Save AI Settings</button>
<div id="ai-notice" style="display:none;margin-top:8px;font-size:12px;color:#2e7d32"></div>
</div>

<!-- Email Inbox (IMAP) — v1.9.1 -->
<?php
$inbox_imap_ok = class_exists( 'SGC_IIP_Inbox' ) && SGC_IIP_Inbox::imap_available();
$inbox_lastp   = get_option( 'sgc_iip_inbox_last_poll', '' );
$inbox_laste   = get_option( 'sgc_iip_inbox_last_error', '' );
$inbox_haspass = get_option( 'sgc_iip_inbox_pass', '' ) !== '';
?>
<div class="iip-box">
<h3>📥 Email Inbox (IMAP — forward-to-IIP)</h3>
<p style="font-size:12px;color:#666;margin:0 0 12px">
  Buyer mail ko Gmail/Zoho se <code><?php echo esc_attr( get_option('sgc_iip_inbox_user','iip@labsglass.com') ); ?></code> par <strong>forward</strong> karo.
  IIP us mailbox ko IMAP se poll karke Inbox tab me dikhayega.
</p>

<?php if ( ! $inbox_imap_ok ) : ?>
  <div style="background:#fff6e6;border:1px solid #ffe2ad;color:#8a5a00;padding:8px 12px;border-radius:6px;font-size:12px;margin-bottom:12px">
    ⚠️ PHP <code>imap</code> extension server pe nahi hai. Bluehost/CyberPanel se enable karwao, tabhi sync chalega.
  </div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
  <div class="iip-fg"><label>IMAP Host</label><input type="text" class="iip-input iip-inbox-field" data-k="host" value="<?php echo esc_attr( get_option('sgc_iip_inbox_host','imap.zoho.in') ); ?>" style="width:100%" placeholder="imap.zoho.in"></div>
  <div class="iip-fg"><label>Port</label><input type="number" class="iip-input iip-inbox-field" data-k="port" value="<?php echo esc_attr( get_option('sgc_iip_inbox_port',993) ); ?>" style="width:100%" placeholder="993"></div>
  <div class="iip-fg"><label>Security</label>
    <select class="iip-select iip-inbox-field" data-k="security" style="width:100%">
      <?php $sec = get_option('sgc_iip_inbox_security','ssl'); foreach(['ssl'=>'SSL (993)','tls'=>'TLS','none'=>'None'] as $v=>$l) echo '<option value="'.esc_attr($v).'"'.selected($sec,$v,false).'>'.esc_html($l).'</option>'; ?>
    </select>
  </div>
  <div class="iip-fg"><label>Folder</label><input type="text" class="iip-input iip-inbox-field" data-k="folder" value="<?php echo esc_attr( get_option('sgc_iip_inbox_folder','INBOX') ); ?>" style="width:100%" placeholder="INBOX"></div>
  <div class="iip-fg"><label>Username (full email)</label><input type="text" class="iip-input iip-inbox-field" data-k="user" value="<?php echo esc_attr( get_option('sgc_iip_inbox_user','iip@labsglass.com') ); ?>" style="width:100%" placeholder="iip@labsglass.com"></div>
  <div class="iip-fg"><label>App Password <?php echo $inbox_haspass ? '<span style="color:#2e7d32;font-size:11px">(saved ✓)</span>' : ''; ?></label><input type="password" class="iip-input" id="iip-inbox-pass" value="" style="width:100%" placeholder="<?php echo $inbox_haspass ? '•••••• (blank = keep existing)' : 'Zoho app-specific password'; ?>"></div>
  <div class="iip-fg"><label>Poll interval (min)</label><input type="number" class="iip-input iip-inbox-field" data-k="interval" value="<?php echo esc_attr( get_option('sgc_iip_inbox_interval',10) ); ?>" style="width:100%" min="5" placeholder="10"></div>
  <div class="iip-fg"><label>Enable inbox sync</label>
    <select class="iip-select" id="iip-inbox-enabled" style="width:100%">
      <?php $en = get_option('sgc_iip_inbox_enabled','0'); echo '<option value="1"'.selected($en,'1',false).'>ON</option><option value="0"'.selected($en,'0',false).'>OFF</option>'; ?>
    </select>
  </div>
</div>

<div style="margin-top:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
  <button class="iip-btn primary" id="btn-save-inbox">💾 Save Inbox Settings</button>
  <button class="iip-btn" id="btn-test-inbox">🔌 Test Connection</button>
  <span id="inbox-test-result" style="font-size:12px"></span>
</div>
<div id="inbox-notice" style="display:none;margin-top:8px;font-size:12px;color:#2e7d32"></div>
<div style="margin-top:8px;font-size:11px;color:#999">
  <?php echo $inbox_lastp ? 'Last sync: ' . esc_html($inbox_lastp) : 'Not synced yet'; ?>
  <?php echo $inbox_laste ? ' &nbsp;|&nbsp; <span style="color:#c62828">Last error: ' . esc_html($inbox_laste) . '</span>' : ''; ?>
</div>

<script>
jQuery(function($){
  function inboxData(extra){
    var d = $.extend({ nonce: SGCIIP.nonce }, extra||{});
    $('.iip-inbox-field').each(function(){ d[$(this).data('k')] = $(this).val(); });
    d.enabled = $('#iip-inbox-enabled').val();
    var p = $('#iip-inbox-pass').val();
    if (p) { d.pass = p; }
    return d;
  }
  $('#btn-save-inbox').on('click', function(){
    var $b = $(this).addClass('loading').text('Saving…');
    $.post(SGCIIP.ajaxUrl, inboxData({ action:'sgc_iip_inbox_save_settings' }), function(r){
      $('#inbox-notice').text(r.success ? (r.data.message||'Saved!') : ('Error: '+r.data)).css('color', r.success?'#2e7d32':'#c62828').show();
      setTimeout(function(){ $('#inbox-notice').hide(); }, 4000);
    }).always(function(){ $b.removeClass('loading').text('💾 Save Inbox Settings'); });
  });
  $('#btn-test-inbox').on('click', function(){
    // Test uses CURRENTLY SAVED creds — save first if you changed them.
    $('#inbox-test-result').text('Testing…').css('color','#888');
    $.post(SGCIIP.ajaxUrl, { action:'sgc_iip_inbox_test', nonce:SGCIIP.nonce }, function(r){
      $('#inbox-test-result').text(r.success ? ('✅ '+r.data.message) : ('❌ '+r.data)).css('color', r.success?'#2e7d32':'#c62828');
    }).fail(function(){ $('#inbox-test-result').text('❌ Network error').css('color','#c62828'); });
  });
});
</script>

<!-- WhatsApp -->
<div class="iip-box">
<h3>💬 WhatsApp Business API (Meta Cloud)</h3>
<div style="background:#E3F2FD;border-radius:5px;padding:12px;margin-bottom:14px;font-size:12px;color:#0D47A1">
  <strong>Setup:</strong> developers.facebook.com → Your App → WhatsApp → API Setup → Copy Phone Number ID &amp; Token
</div>
<div class="iip-fg"><label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="sgc_iip_wa_enabled" <?php checked(get_option('sgc_iip_wa_enabled','0'),'1'); ?> style="width:14px;height:14px"> Enable WhatsApp Auto-Reply</label></div>
<?php foreach(['sgc_iip_wa_phone_id'=>'Phone Number ID','sgc_iip_wa_account_id'=>'WhatsApp Business Account ID','sgc_iip_wa_token'=>'Permanent Access Token','sgc_iip_wa_from_number'=>'From Phone Number (with country code)'] as $k=>$l): ?>
<div class="iip-fg"><label><?php echo esc_html($l); ?></label><input type="text" name="<?php echo esc_attr($k); ?>" class="iip-input iip-wa-field" value="<?php echo esc_attr(get_option($k,'')); ?>" style="width:100%"></div>
<?php endforeach; ?>
<div style="display:flex;gap:8px;align-items:center;margin-top:6px">
<input type="text" id="wa-test-phone" class="iip-input" placeholder="Test phone: 919996416935" style="width:220px">
<button class="iip-btn" id="btn-wa-test">Send Test Message</button>
<span id="wa-test-result" style="font-size:12px"></span>
</div>
<button class="iip-btn primary" id="btn-save-wa" style="margin-top:12px">💾 Save WhatsApp Settings</button>
<div id="wa-notice" style="display:none;margin-top:8px;font-size:12px;color:#2e7d32"></div>
</div>

<!-- Currency -->
<div class="iip-box">
<h3>💱 Currency Settings</h3>
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
  <div class="iip-fg"><label>USD Rate (1 USD = ? INR)</label><input type="number" name="sgc_iip_usd_rate" class="iip-input iip-cur-field" value="<?php echo esc_attr(get_option('sgc_iip_usd_rate','83.50')); ?>" step="0.01" style="width:100%"></div>
  <div class="iip-fg"><label>GBP Rate</label><input type="number" name="sgc_iip_gbp_rate" class="iip-input iip-cur-field" value="<?php echo esc_attr(get_option('sgc_iip_gbp_rate','106.50')); ?>" step="0.01" style="width:100%"></div>
  <div class="iip-fg"><label>EUR Rate</label><input type="number" name="sgc_iip_eur_rate" class="iip-input iip-cur-field" value="<?php echo esc_attr(get_option('sgc_iip_eur_rate','90.20')); ?>" step="0.01" style="width:100%"></div>
</div>
<div class="iip-fg"><label>ExchangeRate-API Key (free 1500/month)</label><input type="text" name="sgc_iip_currency_api_key" class="iip-input iip-cur-field" value="<?php echo esc_attr(get_option('sgc_iip_currency_api_key','')); ?>" style="width:100%" placeholder="From exchangerate-api.com — free tier"></div>
<div style="display:flex;gap:8px;margin-top:6px">
<button class="iip-btn primary" id="btn-save-currency">💾 Save Rates</button>
<button class="iip-btn" id="btn-refresh-rates">🔄 Fetch Live Rates</button>
<span id="cur-notice" style="font-size:12px;line-height:34px"></span>
</div>
<div id="rates-updated" style="font-size:11px;color:#aaa;margin-top:6px">Last updated: <?php echo esc_html(get_option('sgc_iip_rates_updated','Never')); ?></div>
</div>

<!-- ── Payment Methods ── -->
<div class="iip-box">
<h3>💳 Payment Methods (shown on PI)</h3>

<!-- Domestic: UPI/GPay -->
<div style="background:#f0fdf4;border:2px solid #bbf7d0;border-radius:10px;padding:18px;margin-bottom:16px">
  <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px">
    <span style="font-size:20px">📱</span>
    <div>
      <strong style="font-size:13px;color:#1a1a2e;display:block">GPay / UPI — Domestic Indian Buyers</strong>
      <span style="font-size:11px;color:#16a34a">QR code automatically prints on PI PDF</span>
    </div>
    <label style="margin-left:auto;display:flex;align-items:center;gap:6px;cursor:pointer;background:#dcfce7;padding:5px 10px;border-radius:6px;font-size:12px;font-weight:600;color:#14532d">
      <input type="checkbox" name="sgc_iip_show_upi" class="iip-pay-field" <?php checked(get_option('sgc_iip_show_upi','1'),'1'); ?>> Show on PI
    </label>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
    <div class="iip-fg">
      <label>UPI ID <small style="color:#888">(e.g. 9996416935@ybl)</small></label>
      <input type="text" name="sgc_iip_upi_id" id="settings-upi-id" class="iip-input iip-pay-field" value="<?php echo esc_attr(get_option('sgc_iip_upi_id','')); ?>" style="width:100%;font-family:monospace" placeholder="yourname@bankname" oninput="updateUpiPreview()">
    </div>
    <div class="iip-fg">
      <label>Display Name (on QR &amp; PDF)</label>
      <input type="text" name="sgc_iip_gpay_name" id="settings-gpay-name" class="iip-input iip-pay-field" value="<?php echo esc_attr(get_option('sgc_iip_gpay_name','SGC Labs')); ?>" style="width:100%" oninput="updateUpiPreview()">
    </div>
  </div>

  <!-- QR Upload Section -->
  <div style="background:#fff;border:1.5px dashed #86efac;border-radius:8px;padding:16px;display:grid;grid-template-columns:auto 1fr;gap:16px;align-items:start">

    <!-- QR Preview Box -->
    <div style="text-align:center;min-width:120px">
      <?php
      $upi     = get_option('sgc_iip_upi_id','');
      $gpay    = get_option('sgc_iip_gpay_name','SGC Labs');
      $qr_img  = get_option('sgc_iip_upi_qr_img','');
      $auto_qr = $upi ? 'https://quickchart.io/qr?text='.urlencode('upi://pay?pa='.$upi.'&pn='.$gpay).'&width=130&height=130' : '';
      $show_qr = $qr_img ?: $auto_qr;
      ?>
      <div style="width:120px;height:120px;border:2px solid #e0e0e0;border-radius:8px;overflow:hidden;background:#f9f9f9;display:flex;align-items:center;justify-content:center;margin:0 auto">
        <?php if($show_qr): ?>
          <img id="qr-preview-img" src="<?php echo esc_url($show_qr); ?>" width="116" height="116" alt="QR Code" style="display:block">
        <?php else: ?>
          <div id="qr-preview-img" style="text-align:center;color:#aaa;font-size:11px;padding:10px">Enter UPI ID to preview QR</div>
        <?php endif; ?>
      </div>
      <div style="font-size:10px;color:#888;margin-top:5px">
        <?php echo $qr_img ? '✅ Custom QR' : ($upi ? '🔄 Auto-generated' : 'No QR'); ?>
      </div>
    </div>

    <!-- Upload Options -->
    <div>
      <div style="font-size:12px;font-weight:700;color:#1a1a2e;margin-bottom:10px">QR Code Image</div>

      <!-- Option 1: Upload -->
      <div style="margin-bottom:10px">
        <label style="font-size:11px;font-weight:600;color:#555;display:block;margin-bottom:4px">Option 1 — Upload custom QR image</label>
        <div style="display:flex;gap:6px;align-items:center">
          <input type="text" name="sgc_iip_upi_qr_img" id="upi-qr-img-url" class="iip-input iip-pay-field" value="<?php echo esc_attr($qr_img); ?>" style="flex:1;font-size:11px" placeholder="https://... (URL of QR image)">
          <button type="button" class="iip-btn sm" id="btn-upload-qr" style="white-space:nowrap;flex-shrink:0">📁 Media</button>
          <?php if($qr_img): ?><button type="button" class="iip-btn sm" onclick="clearQr()" style="color:#e53935;border-color:#e53935;flex-shrink:0">✕ Clear</button><?php endif; ?>
        </div>
        <div style="font-size:10px;color:#888;margin-top:3px">Media Library mein QR image upload karo → URL copy karo → paste karo</div>
      </div>

      <!-- Option 2: Auto from UPI -->
      <div style="background:#f0fdf4;border-radius:6px;padding:10px;font-size:11px;color:#555">
        <strong style="color:#1a1a2e;display:block;margin-bottom:4px">Option 2 — Auto-generate from UPI ID</strong>
        UPI ID field mein ID daalo → QR automatically generate hoga<br>
        Custom QR upload karo toh wo prefer hoga auto-generated pe.<br>
        <a id="qr-download-link" href="<?php echo esc_url($auto_qr); ?>" target="_blank" style="color:#16a34a;font-size:10px;font-weight:600;<?php echo $upi ? '' : 'display:none'; ?>">⬇ Download Auto QR PNG</a>
      </div>
    </div>
  </div>
</div>

<!-- Export: PayPal -->
<div style="background:#e8f4fd;border:1px solid #90cdf4;border-radius:8px;padding:16px;margin-bottom:14px">
  <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
    <span style="font-size:18px">🅿️</span>
    <strong style="font-size:13px;color:#1a1a2e">PayPal (Export / International Buyers)</strong>
    <label style="margin-left:auto;display:flex;align-items:center;gap:6px;cursor:pointer">
      <input type="checkbox" name="sgc_iip_show_paypal" class="iip-pay-field" <?php checked(get_option('sgc_iip_show_paypal','0'),'1'); ?>> Show on PI
    </label>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
    <div class="iip-fg">
      <label>PayPal Email</label>
      <input type="email" name="sgc_iip_paypal_email" class="iip-input iip-pay-field" value="<?php echo esc_attr(get_option('sgc_iip_paypal_email','')); ?>" style="width:100%" placeholder="paypal@yourdomain.com">
    </div>
    <div class="iip-fg">
      <label>PayPal.me Link <small style="color:#888">(e.g. paypal.me/sgclabs)</small></label>
      <input type="text" name="sgc_iip_paypal_link" class="iip-input iip-pay-field" value="<?php echo esc_attr(get_option('sgc_iip_paypal_link','')); ?>" style="width:100%" placeholder="paypal.me/yourname">
    </div>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:8px">
    <div class="iip-fg">
      <label>Max Amount for PayPal (USD) <small style="color:#888">Default: $1000</small></label>
      <input type="number" name="sgc_iip_paypal_max_usd" class="iip-input iip-pay-field" value="<?php echo esc_attr(get_option('sgc_iip_paypal_max_usd','1000')); ?>" style="width:100%" placeholder="1000">
    </div>
    <div class="iip-fg">
      <label>PayPal Fee % <small style="color:#888">Default: 4.4%</small></label>
      <input type="number" name="sgc_iip_paypal_fee_pct" class="iip-input iip-pay-field" value="<?php echo esc_attr(get_option('sgc_iip_paypal_fee_pct','4.4')); ?>" style="width:100%" placeholder="4.4" step="0.1">
    </div>
  </div>
  <div style="margin-top:8px;font-size:11px;color:#4a90d9;background:#dbeafe;padding:8px 10px;border-radius:5px">
    💡 PayPal shown on PI automatically when order is under max amount. Fee row also shows on PI.
  </div>
</div>

<!-- Export: Wise -->
<div style="background:#fdf4ff;border:1px solid #e9d5ff;border-radius:8px;padding:16px;margin-bottom:14px">
  <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
    <span style="font-size:18px">💸</span>
    <strong style="font-size:13px;color:#1a1a2e">Wise (Formerly TransferWise) — Recommended for Export</strong>
    <label style="margin-left:auto;display:flex;align-items:center;gap:6px;cursor:pointer">
      <input type="checkbox" name="sgc_iip_show_wise" class="iip-pay-field" <?php checked(get_option('sgc_iip_show_wise','0'),'1'); ?>> Show on PI
    </label>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
    <div class="iip-fg">
      <label>Wise Email / Account</label>
      <input type="email" name="sgc_iip_wise_email" class="iip-input iip-pay-field" value="<?php echo esc_attr(get_option('sgc_iip_wise_email','')); ?>" style="width:100%" placeholder="wise@yourdomain.com">
    </div>
    <div class="iip-fg">
      <label>Wise Pay Link</label>
      <input type="text" name="sgc_iip_wise_link" class="iip-input iip-pay-field" value="<?php echo esc_attr(get_option('sgc_iip_wise_link','')); ?>" style="width:100%" placeholder="wise.com/pay/me/yourname">
    </div>
  </div>
  <div style="margin-top:8px;font-size:11px;color:#7c3aed;background:#f3e8ff;padding:8px 10px;border-radius:5px">
    ⭐ Wise recommended: low fees (0.3-0.7%), fast transfer, supports 40+ currencies. 
    Better than PayPal for amounts >$200.
  </div>
</div>

<button class="iip-btn primary" id="btn-save-payment">💾 Save Payment Settings</button>
<div id="pay-notice" style="display:none;margin-top:8px;font-size:12px;color:#2e7d32"></div>
</div>

</div>

<script>
jQuery(function($){
  // Save helpers
  function saveGroup(selector,notice,ajaxData){
    var data=Object.assign({nonce:SGCIIP.nonce},ajaxData);
    $(selector).each(function(){ data[$(this).attr('name')]=$(this).is('[type=checkbox]')?( $(this).is(':checked')?'1':'0' ):$(this).val(); });
    $.post(SGCIIP.ajaxUrl,data,function(r){ $(notice).text(r.success?'Saved!':r.data).show(); setTimeout(function(){$(notice).hide();},3000); });
  }
  $('#btn-save-company').on('click',function(){ saveGroup('.iip-company-field','#company-notice',{action:'sgc_iip_save_company_settings'}); });
  $('#btn-save-export-bank').on('click',function(){ saveGroup('.iip-export-bank-field','#bank-notice',{action:'sgc_iip_save_export_bank_settings'}); });
  $('#btn-save-dom-bank').on('click',function(){ saveGroup('.iip-dom-bank-field','#bank-notice',{action:'sgc_iip_save_dom_bank_settings'}); });

  // Export bank QR preview
  $('#export-bank-qr-url').on('input', function(){
    var url = $(this).val().trim();
    var p = $('#export-qr-preview');
    if(url) p.html('<img src="https://quickchart.io/qr?text='+encodeURIComponent(url)+'&width=80&height=80" width="80" height="80">');
    else p.html('<span style="font-size:10px;color:#aaa">No QR</span>');
  });

  // Domestic UPI QR preview
  window.updateDomQr = function(upiId){
    var name = $('#dom-upi-name').val() || 'SGC LABS';
    var p = $('#dom-qr-preview');
    if(upiId && upiId.includes('@')){
      var upiStr = 'upi://pay?pa='+upiId+'&pn='+encodeURIComponent(name);
      p.html('<img id="dom-qr-img" src="https://quickchart.io/qr?text='+encodeURIComponent(upiStr)+'&width=80&height=80" width="80" height="80">');
    } else {
      p.html('<span style="font-size:10px;color:#aaa">UPI ID daalo</span>');
    }
  };
  $('#btn-save-pi').on('click',function(){ saveGroup('.iip-pi-field','#pi-notice',{action:'sgc_iip_save_pi_settings'}); });
  $('#btn-save-ai').on('click',function(){
    var data={action:'sgc_iip_ai_save_settings',nonce:SGCIIP.nonce};
    $('.ai-key-input').each(function(){ data['sgc_iip_ai_key_'+$(this).data('provider')]=$(this).val(); });
    data.sgc_iip_ai_provider=$('[name=sgc_iip_ai_provider]').val();
    $.post(SGCIIP.ajaxUrl,data,function(r){ $('#ai-notice').text(r.success?'Saved!':r.data).show(); setTimeout(function(){$('#ai-notice').hide();},3000); });
  });
  $('[id^=test-]').on('click',function(){
    var p=$(this).data('provider');
    $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_ai_test',nonce:SGCIIP.nonce,provider:p,['sgc_iip_ai_key_'+p]:$('.ai-key-input[data-provider='+p+']').val()},function(r){ $('#res-'+p).text(r.success?'✅':('❌ '+r.data)).css('color',r.success?'#2e7d32':'#c62828'); });
  });
  $('#btn-save-wa').on('click',function(){ saveGroup('.iip-wa-field, [name=sgc_iip_wa_enabled]','#wa-notice',{action:'sgc_iip_wa_save_settings'}); });
  $('#btn-wa-test').on('click',function(){
    $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_wa_test',nonce:SGCIIP.nonce,phone:$('#wa-test-phone').val()},function(r){ $('#wa-test-result').text(r.success?'✅ '+r.data:'❌ '+r.data).css('color',r.success?'#2e7d32':'#c62828'); });
  });
  $('#btn-save-currency').on('click',function(){ saveGroup('.iip-cur-field','#cur-notice',{action:'sgc_iip_save_rates'}); });
  $('#btn-save-payment').on('click',function(){
    var data={action:'sgc_iip_save_payment_settings',nonce:SGCIIP.nonce};
    $('.iip-pay-field').each(function(){
      var n=$(this).attr('name');
      if(!n) return;
      data[n]=$(this).is('[type=checkbox]')?($(this).is(':checked')?'1':'0'):$(this).val();
    });
    $.post(SGCIIP.ajaxUrl,data,function(r){
      $('#pay-notice').text(r.success?'✅ Saved!':r.data).show();
      setTimeout(function(){$('#pay-notice').hide();},3000);
    });
  });
  $('#btn-refresh-rates').on('click',function(){
    $('#cur-notice').text('Fetching…');
    $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_refresh_rates',nonce:SGCIIP.nonce},function(r){ $('#cur-notice').text(r.success?'✅ Updated!':'❌ '+r.data); });
  });

  // ── QR Media Library Upload ──
  var qrMediaFrame;
  $('#btn-upload-qr').on('click',function(e){
    e.preventDefault();
    if(qrMediaFrame){qrMediaFrame.open();return;}
    qrMediaFrame=wp.media({title:'Select QR Code Image',button:{text:'Use this QR'},multiple:false,library:{type:['image']}});
    qrMediaFrame.on('select',function(){
      var att=qrMediaFrame.state().get('selection').first().toJSON();
      $('#upi-qr-img-url').val(att.url);
      $('#qr-preview-img').attr('src',att.url).css({'display':'block','width':'116px','height':'116px','object-fit':'contain'});
    });
    qrMediaFrame.open();
  });

  // ── Clear custom QR ──
  window.clearQr=function(){
    $('#upi-qr-img-url').val('');
    var upi=$('#settings-upi-id').val().trim();
    var name=$('#settings-gpay-name').val().trim()||'SGC Labs';
    if(upi){
      var url='https://quickchart.io/qr?text='+encodeURIComponent('upi://pay?pa='+upi+'&pn='+name)+'&width=130&height=130';
      $('#qr-preview-img').attr('src',url).css('display','block');
    } else {
      $('#qr-preview-img').attr('src','').css('display','none');
    }
  };

  // ── Live UPI Preview update ──
  window.updateUpiPreview=function(){
    var upi=$('#settings-upi-id').val().trim();
    var name=$('#settings-gpay-name').val().trim()||'SGC Labs';
    var custom=$('#upi-qr-img-url').val().trim();
    if(custom){return;}
    if(upi){
      var url='https://quickchart.io/qr?text='+encodeURIComponent('upi://pay?pa='+upi+'&pn='+name)+'&width=130&height=130';
      $('#qr-preview-img').attr('src',url).css('display','block');
      var dl='https://quickchart.io/qr?text='+encodeURIComponent('upi://pay?pa='+upi+'&pn='+name)+'&width=300&height=300';
      $('#qr-download-link').attr('href',dl).show();
    } else {
      $('#qr-preview-img').css('display','none');
      $('#qr-download-link').hide();
    }
  };

  // Note: AJAX handlers are registered server-side in class-hooks.php
});
</script>
<?php
// ── Automation Settings Section ──────────────────────────────────────────
if ( class_exists('SGC_IIP_Automation') ) {
    SGC_IIP_Automation::render_settings_section(
        wp_create_nonce('sgc_iip_nonce')
    );
}
?>
</div><!-- /#iip-settings-wrap -->
