<?php
defined( 'ABSPATH' ) || exit;
$provider = get_option( 'sgc_pm_ai_provider', 'deepseek' );
$providers = SGC_PM_AI_Engine::PROVIDERS;
$tones = [ 'b2b_formal' => 'B2B Formal', 'educational' => 'Educational', 'export' => 'Export-Oriented', 'dealer' => 'Dealer/Distributor', 'concise' => 'Concise & Direct' ];
?>
<div class="sgc-pm-ai-page">
<div style="display:grid;grid-template-columns:260px 1fr;gap:16px;align-items:start">

<!-- LEFT: Controls -->
<div>
	<div class="sgc-pm-box">
		<h3 style="margin:0 0 12px;font-size:14px">🤖 AI Settings</h3>
		<div class="sgc-pm-fg">
			<label>Active Provider</label>
			<div style="display:flex;align-items:center;gap:8px">
				<span class="sgc-pm-badge sgc-pm-badge-green"><?php echo esc_html( $providers[$provider]['label'] ?? $provider ); ?></span>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=sgc-product-manager&tab=settings' ) ); ?>" style="font-size:11px">Change</a>
			</div>
		</div>
		<div class="sgc-pm-fg">
			<label>Tone</label>
			<select id="ai-tone" class="sgc-pm-select" style="width:100%">
				<?php foreach ( $tones as $k => $v ) : ?>
				<option value="<?php echo esc_attr($k); ?>" <?php selected( get_option('sgc_pm_ai_tone','b2b_formal'), $k ); ?>><?php echo esc_html($v); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="sgc-pm-fg">
			<label>Generate</label>
			<select id="ai-desc-type" class="sgc-pm-select" style="width:100%">
				<option value="both">Main + Short Description</option>
				<option value="main">Main Description Only</option>
				<option value="short">Short Description Only</option>
				<option value="seo">SEO Fields Only</option>
			</select>
		</div>
		<div class="sgc-pm-fg">
			<label>Extra Keywords (optional)</label>
			<textarea id="ai-extra-keywords" class="sgc-pm-input" rows="3" placeholder="borosilicate, lab grade, ISO certified..." style="width:100%;resize:vertical"></textarea>
		</div>
	</div>

	<!-- Keyword Research Tool -->
	<div class="sgc-pm-box">
		<h3 style="margin:0 0 12px;font-size:14px">🔎 Keyword Research</h3>
		<div class="sgc-pm-fg">
			<label>Product Name</label>
			<input type="text" id="kw-product-name" class="sgc-pm-input" placeholder="e.g. Borosilicate Beaker" style="width:100%">
		</div>
		<div class="sgc-pm-fg">
			<label>Category</label>
			<input type="text" id="kw-category" class="sgc-pm-input" placeholder="e.g. Laboratory Glassware" style="width:100%">
		</div>
		<button class="sgc-pm-btn primary" id="btn-kw-research" style="width:100%">🔎 Research Keywords</button>
		<div id="kw-results" style="margin-top:12px;display:none"></div>
	</div>
</div>

<!-- RIGHT: Main Content -->
<div>
	<!-- Bulk AI SEO -->
	<div class="sgc-pm-box" style="margin-bottom:12px">
		<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
			<h3 style="margin:0;font-size:14px">🚀 Bulk AI SEO Generator</h3>
			<span id="ai-bulk-sel-count" class="sgc-pm-badge sgc-pm-badge-blue">0 selected from Products tab</span>
		</div>
		<p style="font-size:12px;color:#888;margin:0 0 12px">Select products from the Products tab first, then come here to generate SEO content in bulk. AI uses competitor gap keywords automatically if available.</p>
		<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
			<button class="sgc-pm-btn primary" id="btn-bulk-ai-seo">🤖 Generate for Selected (max 100)</button>
			<button class="sgc-pm-btn secondary" id="btn-bulk-ai-all">Generate for ALL Published</button>
			<span style="font-size:11px;color:#aaa">⏱ ~3-5 seconds per product</span>
		</div>
		<div id="ai-bulk-progress" style="display:none;margin-top:14px">
			<div style="background:#e0e0e0;border-radius:6px;height:10px;overflow:hidden;margin-bottom:8px">
				<div id="ai-bulk-bar" style="height:100%;width:0%;background:#1a1a2e;border-radius:6px;transition:width .4s"></div>
			</div>
			<div style="font-size:12px;color:#555" id="ai-bulk-label">Processing…</div>
		</div>
		<div id="ai-bulk-result" class="sgc-pm-notice" style="display:none;margin-top:10px"></div>
	</div>

	<!-- Per-Product AI -->
	<div class="sgc-pm-box" style="margin-bottom:12px">
		<h3 style="margin:0 0 12px;font-size:14px">✏️ Single Product AI Generator</h3>
		<div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
			<div class="sgc-pm-fg" style="margin:0;flex:1">
				<label>Product ID</label>
				<input type="number" id="ai-single-id" class="sgc-pm-input" placeholder="Enter product ID" min="1">
			</div>
			<button class="sgc-pm-btn primary" id="btn-ai-single">🤖 Generate</button>
			<button class="sgc-pm-btn secondary" id="btn-ai-price-suggest">💰 Price Suggest</button>
		</div>
		<div id="ai-single-result" style="display:none;margin-top:14px">
			<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
				<div>
					<div style="font-size:11px;font-weight:600;color:#888;text-transform:uppercase;margin-bottom:4px">SEO Title</div>
					<div id="ai-res-title" style="font-size:13px;padding:8px;background:#f8f8f8;border-radius:4px"></div>
				</div>
				<div>
					<div style="font-size:11px;font-weight:600;color:#888;text-transform:uppercase;margin-bottom:4px">Focus Keyword</div>
					<div id="ai-res-keyword" style="font-size:13px;padding:8px;background:#f8f8f8;border-radius:4px"></div>
				</div>
			</div>
			<div style="margin-bottom:10px">
				<div style="font-size:11px;font-weight:600;color:#888;text-transform:uppercase;margin-bottom:4px">Meta Description</div>
				<div id="ai-res-meta" style="font-size:13px;padding:8px;background:#f8f8f8;border-radius:4px"></div>
			</div>
			<div style="margin-bottom:10px">
				<div style="font-size:11px;font-weight:600;color:#888;text-transform:uppercase;margin-bottom:4px">Short Description</div>
				<div id="ai-res-short" style="font-size:13px;padding:8px;background:#f8f8f8;border-radius:4px;max-height:80px;overflow-y:auto"></div>
			</div>
			<div>
				<div style="font-size:11px;font-weight:600;color:#888;text-transform:uppercase;margin-bottom:4px">Main Description (preview)</div>
				<div id="ai-res-main" style="font-size:12px;padding:8px;background:#f8f8f8;border-radius:4px;max-height:120px;overflow-y:auto"></div>
			</div>
			<div style="margin-top:10px">
				<span class="sgc-pm-badge sgc-pm-badge-green">✅ Saved to product automatically</span>
				<span style="font-size:11px;color:#888;margin-left:8px">RankMath + Yoast fields updated</span>
			</div>
		</div>
		<div id="ai-price-result" style="display:none;margin-top:14px;padding:12px;background:#f0f7ff;border-radius:6px;font-size:13px"></div>
	</div>

	<!-- Secondary Keywords display -->
	<div class="sgc-pm-box" id="ai-keywords-box" style="display:none">
		<h3 style="margin:0 0 10px;font-size:14px">🏷️ Suggested Keywords & Tags</h3>
		<div id="ai-keywords-content"></div>
	</div>
</div>
</div>
</div>

<script>
jQuery(function($){
	// Keyword Research
	$('#btn-kw-research').on('click', function(){
		var pname=$('#kw-product-name').val(), cat=$('#kw-category').val();
		if(!pname){alert('Enter product name');return;}
		var $b=$(this).prop('disabled',true).text('Researching…');
		$.post(SGCPM.ajaxUrl,{action:'sgc_pm_ai_keyword_research',nonce:SGCPM.nonce,product_name:pname,category:cat},function(r){
			$b.prop('disabled',false).text('Research Keywords');
			if(!r.success){alert(r.data);return;}
			var d=r.data, html='';
			if(d.google_suggest&&d.google_suggest.length){
				html+='<div style="font-size:11px;font-weight:600;color:#888;text-transform:uppercase;margin-bottom:6px">Google Suggest</div>';
				html+='<div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:10px">';
				d.google_suggest.forEach(function(kw){ html+='<span class="sgc-pm-badge sgc-pm-badge-blue" style="cursor:pointer" onclick="addKeyword(\''+kw.replace(/'/g,"\\'")+'\')">'+(kw)+'</span>'; });
				html+='</div>';
			}
			if(d.ai_analysis){
				var a=d.ai_analysis;
				if(a.primary_keywords){ html+='<div style="font-size:11px;font-weight:600;color:#888;text-transform:uppercase;margin-bottom:4px">Primary Keywords</div><div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:8px">'; a.primary_keywords.forEach(function(k){html+='<span class="sgc-pm-badge sgc-pm-badge-green" style="cursor:pointer" onclick="addKeyword(\''+k.replace(/'/g,"\\'")+'\')">'+(k)+'</span>';}); html+='</div>'; }
				if(a.b2b_keywords){ html+='<div style="font-size:11px;font-weight:600;color:#888;text-transform:uppercase;margin-bottom:4px">B2B Keywords</div><div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:8px">'; a.b2b_keywords.forEach(function(k){html+='<span class="sgc-pm-badge sgc-pm-badge-amber" style="cursor:pointer" onclick="addKeyword(\''+k.replace(/'/g,"\\'")+'\')">'+(k)+'</span>';}); html+='</div>'; }
				if(a.standard_codes&&a.standard_codes.length){ html+='<div style="font-size:11px;font-weight:600;color:#888;text-transform:uppercase;margin-bottom:4px">Standards/Codes</div><div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:8px">'; a.standard_codes.forEach(function(k){html+='<span class="sgc-pm-badge sgc-pm-badge-gray">'+(k)+'</span>';}); html+='</div>'; }
				if(a.suggested_focus){ html+='<div style="background:#EAF3DE;border-radius:4px;padding:8px;font-size:12px;margin-top:4px">Focus Keyword: <strong>'+a.suggested_focus+'</strong></div>'; }
			}
			$('#kw-results').html(html).show();
		}).fail(function(){$b.prop('disabled',false).text('Research Keywords');alert('Server error.');});
	});

	window.addKeyword = function(kw){
		var cur=$('#ai-extra-keywords').val();
		$('#ai-extra-keywords').val(cur ? cur+', '+kw : kw);
	};

	// Single Product AI
	$('#btn-ai-single').on('click', function(){
		var id=$('#ai-single-id').val();
		if(!id){alert('Enter product ID');return;}
		var $b=$(this).prop('disabled',true).text('Generating…');
		$('#ai-single-result').hide();
		$.post(SGCPM.ajaxUrl,{action:'sgc_pm_ai_generate_desc',nonce:SGCPM.nonce,product_id:id,keywords:$('#ai-extra-keywords').val(),tone:$('#ai-tone').val(),desc_type:$('#ai-desc-type').val()},function(r){
			$b.prop('disabled',false).text('Generate');
			if(!r.success){alert(r.data);return;}
			var d=r.data.data;
			$('#ai-res-title').text(d.seo_title||'—');
			$('#ai-res-keyword').text(d.focus_keyword||'—');
			$('#ai-res-meta').text(d.meta_description||'—');
			$('#ai-res-short').text(d.short_description||'—');
			$('#ai-res-main').html(d.main_description||'—');
			$('#ai-single-result').show();
			if(d.secondary_keywords||d.suggested_tags){
				var khtml='<div style="display:flex;flex-wrap:wrap;gap:5px">';
				(d.secondary_keywords||[]).forEach(function(k){khtml+='<span class="sgc-pm-badge sgc-pm-badge-blue">'+k+'</span>';});
				(d.suggested_tags||[]).forEach(function(k){khtml+='<span class="sgc-pm-badge sgc-pm-badge-gray">'+k+'</span>';});
				khtml+='</div>';
				$('#ai-keywords-content').html(khtml);
				$('#ai-keywords-box').show();
			}
		}).fail(function(){$b.prop('disabled',false).text('Generate');alert('Server error.');});
	});

	// Price Suggest
	$('#btn-ai-price-suggest').on('click', function(){
		var id=$('#ai-single-id').val();
		if(!id){alert('Enter product ID first');return;}
		var $b=$(this).prop('disabled',true).text('Analyzing…');
		$.post(SGCPM.ajaxUrl,{action:'sgc_pm_ai_price_suggest',nonce:SGCPM.nonce,product_id:id},function(r){
			$b.prop('disabled',false).text('Price Suggest');
			if(!r.success){alert(r.data);return;}
			var d=r.data;
			var arrowIcon=d.recommendation==='increase'?'↑ Increase':d.recommendation==='decrease'?'↓ Decrease':'→ Keep';
			$('#ai-price-result').html('<strong>AI Price Analysis:</strong><br>Suggested Retail: ₹'+d.suggested_retail_inr+' &nbsp;|&nbsp; Dealer: ₹'+d.suggested_dealer_inr+' &nbsp;|&nbsp; Export: $'+d.suggested_export_usd+'<br><span class="sgc-pm-badge '+(d.recommendation==='increase'?'sgc-pm-badge-green':'sgc-pm-badge-amber')+'">'+arrowIcon+'</span> &nbsp;'+d.reasoning+'<br><small style="color:#888">Market Position: '+d.market_position+'</small>').show();
		}).fail(function(){$b.prop('disabled',false).text('Price Suggest');});
	});

	// Bulk AI SEO
	$('#btn-bulk-ai-seo,#btn-bulk-ai-all').on('click', function(){
		var all=$(this).is('#btn-bulk-ai-all');
		var ids = all ? [] : (window.sgcPMSelectedIds ? Array.from(window.sgcPMSelectedIds) : []);
		if(!all&&!ids.length){alert('Select products from the Products tab first.');return;}
		if(!confirm('Generate AI SEO for '+(all?'ALL published':''+ids.length+' selected')+' products? This may take several minutes.')){return;}
		var $b=$(this).prop('disabled',true).text('Running…');
		$('#ai-bulk-progress').show(); $('#ai-bulk-bar').css('width','10%'); $('#ai-bulk-label').text('AI processing…');
		$.post(SGCPM.ajaxUrl,{action:'sgc_pm_ai_bulk_seo',nonce:SGCPM.nonce,ids:ids,tone:$('#ai-tone').val(),desc_type:$('#ai-desc-type').val()},function(r){
			$b.prop('disabled',false).text(all?'Generate for ALL Published':'Generate for Selected (max 100)');
			$('#ai-bulk-bar').css('width','100%');
			$('#ai-bulk-label').text(r.success?r.data.message:'Error: '+r.data);
			$('#ai-bulk-result').text(r.success?r.data.message:r.data).removeClass('success error').addClass(r.success?'success':'error').show();
			setTimeout(function(){$('#ai-bulk-progress').hide();$('#ai-bulk-bar').css('width','0%');},3000);
		}).fail(function(){$b.prop('disabled',false);$('#ai-bulk-progress').hide();alert('Server error.');});
	});
});
</script>
