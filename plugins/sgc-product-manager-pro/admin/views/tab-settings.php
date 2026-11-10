<?php
defined( 'ABSPATH' ) || exit;
$providers = SGC_PM_AI_Engine::PROVIDERS;
$cur_prov  = get_option( 'sgc_pm_ai_provider', 'deepseek' );
?>
<div style="max-width:700px">

<!-- AI Engine Settings -->
<div class="sgc-pm-box" style="margin-bottom:16px">
	<h3 style="margin:0 0 14px;font-size:14px">🤖 AI Engine Settings</h3>

	<div class="sgc-pm-fg">
		<label>Default AI Provider</label>
		<div style="display:flex;gap:8px;flex-wrap:wrap" id="ai-provider-btns">
			<?php foreach ( $providers as $pid => $p ) : ?>
			<button class="sgc-pm-btn pm-set-provider<?php echo $pid === $cur_prov ? ' active-provider' : ''; ?>" data-provider="<?php echo esc_attr($pid); ?>"
				style="<?php echo $pid === $cur_prov ? 'background:#1a1a2e;color:#fff;border-color:#1a1a2e' : ''; ?>">
				<?php echo esc_html($p['label']); ?><?php echo $p['default'] ? ' (Default)' : ''; ?>
			</button>
			<?php endforeach; ?>
		</div>
	</div>

	<?php foreach ( $providers as $pid => $p ) : ?>
	<div class="sgc-pm-fg">
		<label><?php echo esc_html($p['label']); ?> API Key</label>
		<div style="display:flex;gap:8px">
			<input type="password" name="sgc_pm_ai_key_<?php echo esc_attr($pid); ?>"
				class="sgc-pm-input ai-key-input" data-provider="<?php echo esc_attr($pid); ?>"
				value="<?php echo esc_attr( get_option( 'sgc_pm_ai_key_' . $pid, '' ) ); ?>"
				placeholder="<?php echo $pid === 'deepseek' ? 'sk-... (get free at platform.deepseek.com)' : 'API key'; ?>"
				style="flex:1">
			<button class="sgc-pm-btn secondary test-connection" data-provider="<?php echo esc_attr($pid); ?>">Test</button>
			<span class="test-result test-result-<?php echo esc_attr($pid); ?>" style="font-size:12px;line-height:34px"></span>
		</div>
	</div>
	<?php endforeach; ?>

	<div class="sgc-pm-fg">
		<label>Default AI Tone</label>
		<select name="sgc_pm_ai_tone" class="sgc-pm-select ai-setting" style="width:100%">
			<option value="b2b_formal"   <?php selected(get_option('sgc_pm_ai_tone'),'b2b_formal'); ?>>B2B Formal</option>
			<option value="educational"  <?php selected(get_option('sgc_pm_ai_tone'),'educational'); ?>>Educational</option>
			<option value="export"       <?php selected(get_option('sgc_pm_ai_tone'),'export'); ?>>Export-Oriented</option>
			<option value="dealer"       <?php selected(get_option('sgc_pm_ai_tone'),'dealer'); ?>>Dealer/Distributor</option>
			<option value="concise"      <?php selected(get_option('sgc_pm_ai_tone'),'concise'); ?>>Concise & Direct</option>
		</select>
	</div>

	<div class="sgc-pm-fg">
		<label>Brand Name (used in AI prompts)</label>
		<input type="text" name="sgc_pm_ai_brand" class="sgc-pm-input ai-setting" value="<?php echo esc_attr(get_option('sgc_pm_ai_brand','SGC LABS® Scientific Glassware')); ?>" style="width:100%">
	</div>

	<button class="sgc-pm-btn primary" id="btn-save-ai">💾 Save AI Settings</button>
	<div id="ai-save-notice" style="display:none;font-size:12px;margin-top:8px;color:green"></div>
</div>

<!-- Competitor Intel Settings -->
<div class="sgc-pm-box" style="margin-bottom:16px">
	<h3 style="margin:0 0 14px;font-size:14px">🔍 Competitor Intel Settings</h3>
	<div class="sgc-pm-fg">
		<label>Google Custom Search API Key <small style="color:#888">(optional — 100 free searches/day)</small></label>
		<input type="text" class="sgc-pm-input" id="google-search-key" value="<?php echo esc_attr(get_option('sgc_pm_google_search_key','')); ?>" style="width:100%" placeholder="AIza...">
	</div>
	<div class="sgc-pm-fg">
		<label>Google Custom Search CX ID</label>
		<input type="text" class="sgc-pm-input" id="google-cx" value="<?php echo esc_attr(get_option('sgc_pm_google_cx','')); ?>" style="width:100%" placeholder="012345678901234567890:abc...">
		<small style="color:#888">Create free at <a href="https://programmablesearchengine.google.com" target="_blank">programmablesearchengine.google.com</a></small>
	</div>
	<button class="sgc-pm-btn secondary" id="btn-save-google">Save Google Settings</button>
	<div id="google-save-notice" style="display:none;font-size:12px;margin-top:8px;color:green"></div>
</div>

<!-- B2B + Company Settings -->
<div class="sgc-pm-box" style="margin-bottom:16px">
	<h3 style="margin:0 0 14px;font-size:14px">📄 Company & B2B Settings</h3>
	<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
		<div class="sgc-pm-fg"><label>Company Name</label><input type="text" class="sgc-pm-input sgc-b2b-opt" name="sgc_pm_company_name" value="<?php echo esc_attr(get_option('sgc_pm_company_name','SGC LABS® Scientific Glassware')); ?>" style="width:100%"></div>
		<div class="sgc-pm-fg"><label>GST Number</label><input type="text" class="sgc-pm-input sgc-b2b-opt" name="sgc_pm_company_gst" value="<?php echo esc_attr(get_option('sgc_pm_company_gst','')); ?>" style="width:100%" placeholder="07AABCA1234A1Z5"></div>
		<div class="sgc-pm-fg"><label>USD Rate (1 USD = ? INR)</label><input type="number" class="sgc-pm-input sgc-b2b-opt" name="sgc_pm_usd_rate" value="<?php echo esc_attr(get_option('sgc_pm_usd_rate','83.5')); ?>" step="0.1" style="width:100%"></div>
		<div class="sgc-pm-fg"><label>Default MOQ</label><input type="number" class="sgc-pm-input sgc-b2b-opt" name="sgc_pm_default_moq" value="<?php echo esc_attr(get_option('sgc_pm_default_moq','10')); ?>" style="width:100%"></div>
		<div class="sgc-pm-fg"><label>Dealer Discount (%)</label><input type="number" class="sgc-pm-input sgc-b2b-opt" name="sgc_pm_dealer_discount" value="<?php echo esc_attr(get_option('sgc_pm_dealer_discount','20')); ?>" style="width:100%"></div>
		<div class="sgc-pm-fg"><label>Distributor Discount (%)</label><input type="number" class="sgc-pm-input sgc-b2b-opt" name="sgc_pm_distributor_disc" value="<?php echo esc_attr(get_option('sgc_pm_distributor_disc','30')); ?>" style="width:100%"></div>
		<div class="sgc-pm-fg"><label>OEM Discount (%)</label><input type="number" class="sgc-pm-input sgc-b2b-opt" name="sgc_pm_oem_disc" value="<?php echo esc_attr(get_option('sgc_pm_oem_disc','40')); ?>" style="width:100%"></div>
		<div class="sgc-pm-fg"><label>Stock Alert Qty</label><input type="number" class="sgc-pm-input sgc-b2b-opt" name="sgc_pm_stock_alert_qty" value="<?php echo esc_attr(get_option('sgc_pm_stock_alert_qty','5')); ?>" style="width:100%"></div>
		<div class="sgc-pm-fg"><label>Admin Email (alerts)</label><input type="email" class="sgc-pm-input sgc-b2b-opt" name="sgc_pm_admin_email" value="<?php echo esc_attr(get_option('sgc_pm_admin_email',get_option('admin_email'))); ?>" style="width:100%"></div>
	</div>
	<button class="sgc-pm-btn primary" id="btn-save-b2b">💾 Save Company Settings</button>
	<div id="b2b-save-notice" style="display:none;font-size:12px;margin-top:8px;color:green"></div>
</div>

<!-- System Info -->
<div class="sgc-pm-box">
	<h3 style="margin:0 0 12px;font-size:14px">⚙️ System Info</h3>
	<table class="sgc-pm-table">
		<tr><td style="color:#888">Plugin Version</td><td><?php echo SGC_PM_VERSION; ?></td></tr>
		<tr><td style="color:#888">WordPress</td><td><?php echo get_bloginfo('version'); ?></td></tr>
		<tr><td style="color:#888">PHP</td><td><?php echo PHP_VERSION; ?></td></tr>
		<tr><td style="color:#888">WooCommerce</td><td><?php echo WC_VERSION ?? 'Active'; ?></td></tr>
		<tr><td style="color:#888">Total Products</td><td><?php echo wp_count_posts('product')->publish ?? 0; ?></td></tr>
		<tr><td style="color:#888">Automation Cron</td><td><?php $t=wp_next_scheduled('sgc_pm_automation_run'); echo $t ? date_i18n('M j H:i:s',$t) : 'Not scheduled'; ?></td></tr>
		<tr><td style="color:#888">Competitor Scan</td><td><?php $t2=wp_next_scheduled('sgc_pm_competitor_scan'); echo $t2 ? date_i18n('M j H:i:s',$t2) : 'Not scheduled'; ?></td></tr>
	</table>
</div>
</div>

<script>
jQuery(function($){
	// Set provider
	$(document).on('click','.pm-set-provider',function(){
		var p=$(this).data('provider');
		$.post(SGCPM.ajaxUrl,{action:'sgc_pm_ai_save_settings',nonce:SGCPM.nonce,sgc_pm_ai_provider:p},function(r){
			$('.pm-set-provider').css({background:'',color:'',borderColor:''});
			$(this).css({background:'#1a1a2e',color:'#fff',borderColor:'#1a1a2e'});
			if(r.success) $('#ai-save-notice').text('Provider set to: '+p).show();
		}.bind(this));
	});

	// Test connection
	$(document).on('click','.test-connection',function(){
		var p=$(this).data('provider'), key=$('.ai-key-input[data-provider="'+p+'"]').val();
		if(key){ $.post(SGCPM.ajaxUrl,{action:'sgc_pm_ai_save_settings',nonce:SGCPM.nonce,['sgc_pm_ai_key_'+p]:key},function(){}); }
		var $r=$('.test-result-'+p).text('Testing…');
		$.post(SGCPM.ajaxUrl,{action:'sgc_pm_ai_test_connection',nonce:SGCPM.nonce,provider:p},function(r){
			$r.text(r.success?'✅ Connected':'❌ '+r.data).css('color',r.success?'green':'red');
		}).fail(function(){$r.text('❌ Error').css('color','red');});
	});

	// Save AI settings
	$('#btn-save-ai').on('click',function(){
		var data={action:'sgc_pm_ai_save_settings',nonce:SGCPM.nonce};
		$('.ai-key-input').each(function(){ data['sgc_pm_ai_key_'+$(this).data('provider')]=$(this).val(); });
		$('.ai-setting').each(function(){ data[$(this).attr('name')]=$(this).val(); });
		$.post(SGCPM.ajaxUrl,data,function(r){ $('#ai-save-notice').text(r.success?'Saved!':r.data).show(); setTimeout(function(){$('#ai-save-notice').hide();},3000); });
	});

	// Save Google settings
	$('#btn-save-google').on('click',function(){
		$.post(SGCPM.ajaxUrl,{action:'sgc_pm_save_google_settings',nonce:SGCPM.nonce,key:$('#google-search-key').val(),cx:$('#google-cx').val()},function(r){
			$('#google-save-notice').text('Saved!').show(); setTimeout(function(){$('#google-save-notice').hide();},2000);
		});
	});

	// Save B2B settings
	$('#btn-save-b2b').on('click',function(){
		var data={action:'sgc_pm_save_b2b_settings',nonce:SGCPM.nonce};
		$('.sgc-b2b-opt').each(function(){ data[$(this).attr('name')]=$(this).val(); });
		$.post(SGCPM.ajaxUrl,data,function(r){ $('#b2b-save-notice').text(r.success?'Saved!':r.data).show(); setTimeout(function(){$('#b2b-save-notice').hide();},2000); });
	});
});
</script>
