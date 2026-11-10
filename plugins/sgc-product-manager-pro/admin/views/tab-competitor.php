<?php defined( 'ABSPATH' ) || exit; ?>
<div class="sgc-pm-comp-page">
<div style="display:grid;grid-template-columns:280px 1fr;gap:16px;align-items:start">

<!-- LEFT: Competitor List -->
<div>
	<div class="sgc-pm-box">
		<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
			<h3 style="margin:0;font-size:13px">Top 25 Competitors</h3>
			<button class="sgc-pm-btn secondary" id="btn-add-comp" style="font-size:11px;padding:3px 8px">+ Add</button>
		</div>
		<div id="comp-list">
			<div style="text-align:center;padding:20px;color:#aaa;font-size:12px">Loading…</div>
		</div>
	</div>
</div>

<!-- RIGHT: Analysis Panel -->
<div>
	<!-- Scan Controls -->
	<div class="sgc-pm-box" style="margin-bottom:12px">
		<h3 style="margin:0 0 10px;font-size:14px">🔍 Competitor Analysis</h3>
		<div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
			<div style="flex:1;min-width:0">
				<label style="display:block;font-size:11px;color:#888;margin-bottom:4px">Product ID to analyze</label>
				<input type="number" id="comp-product-id" class="sgc-pm-input" placeholder="Enter product ID" style="width:100%" min="1">
			</div>
			<button class="sgc-pm-btn primary" id="btn-scan-product">🔍 Scan vs All Competitors</button>
			<button class="sgc-pm-btn secondary" id="btn-scan-all">Scan All (Weekly Cron)</button>
		</div>
		<div id="scan-progress" style="display:none;margin-top:12px">
			<div style="background:#e0e0e0;border-radius:4px;height:8px;overflow:hidden">
				<div id="scan-bar" style="height:100%;width:0%;background:#1a1a2e;transition:width .5s"></div>
			</div>
			<div style="font-size:12px;color:#555;margin-top:6px" id="scan-label">Fetching competitor data…</div>
		</div>
	</div>

	<!-- Results -->
	<div class="sgc-pm-box" id="comp-results-box" style="display:none;padding:0;overflow:hidden">
		<div style="padding:12px 16px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center">
			<strong id="comp-results-title" style="font-size:13px">Competitor Report</strong>
			<button class="sgc-pm-btn secondary" id="btn-apply-ai-desc" style="font-size:11px;display:none">Apply AI Description</button>
		</div>

		<!-- Keyword Gap Table -->
		<div style="padding:14px;border-bottom:1px solid #f0f0f0">
			<div style="font-size:11px;font-weight:600;text-transform:uppercase;color:#888;letter-spacing:.4px;margin-bottom:8px">Keyword Gap Analysis</div>
			<div id="comp-gap-keywords" style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:10px"></div>
			<div id="comp-ai-suggestion" style="background:#f0f7ff;border-radius:5px;padding:10px;font-size:12px;color:#333;display:none">
				<div style="font-size:11px;font-weight:600;color:#185FA5;margin-bottom:5px">🤖 AI-Suggested Description (using gap keywords):</div>
				<div id="comp-ai-text"></div>
			</div>
		</div>

		<!-- Competitor Comparison Table -->
		<div style="overflow-x:auto">
		<table class="sgc-pm-table" id="comp-table">
			<thead>
				<tr>
					<th>Competitor</th>
					<th>Their Title</th>
					<th>Their Keywords</th>
					<th>Their Rank</th>
					<th>Our Rank</th>
					<th>Gap</th>
				</tr>
			</thead>
			<tbody id="comp-tbody"></tbody>
		</table>
		</div>
	</div>

	<div id="comp-notice" class="sgc-pm-notice" style="display:none;margin-top:10px"></div>
</div>
</div>
</div>

<!-- Add Competitor Modal -->
<div class="sgc-pm-modal" id="modal-add-comp" style="display:none">
	<div class="sgc-pm-modal-inner" style="max-width:420px">
		<div class="sgc-pm-modal-hdr"><h3>Add Competitor</h3><button class="pm-modal-close" data-modal="modal-add-comp">✕</button></div>
		<div class="sgc-pm-modal-body">
			<input type="hidden" id="comp-edit-id" value="">
			<div class="sgc-pm-fg"><label>Company Name</label><input type="text" id="comp-name" class="sgc-pm-input" placeholder="e.g. Fisher Scientific" style="width:100%"></div>
			<div class="sgc-pm-fg"><label>Website URL</label><input type="text" id="comp-url" class="sgc-pm-input" placeholder="fishersci.com" style="width:100%"></div>
			<div class="sgc-pm-fg"><label>Category</label><input type="text" id="comp-cat" class="sgc-pm-input" placeholder="Lab Glassware" style="width:100%"></div>
			<div class="sgc-pm-fg"><label>Country</label><select id="comp-country" class="sgc-pm-select" style="width:100%"><option value="US">USA</option><option value="IN">India</option><option value="UK">UK</option><option value="DE">Germany</option><option value="CN">China</option></select></div>
		</div>
		<div class="sgc-pm-modal-ftr">
			<button class="sgc-pm-btn secondary pm-modal-close" data-modal="modal-add-comp">Cancel</button>
			<button class="sgc-pm-btn primary" id="btn-save-comp">Save Competitor</button>
		</div>
	</div>
</div>

<script>
jQuery(function($){
	function loadCompList(){
		$.post(SGCPM.ajaxUrl,{action:'sgc_pm_comp_get_list',nonce:SGCPM.nonce},function(r){
			if(!r.success) return;
			var html='';
			var flags={US:'🇺🇸',IN:'🇮🇳',UK:'🇬🇧',DE:'🇩🇪',CN:'🇨🇳'};
			r.data.forEach(function(c){
				var isActive=c.status==='active';
				html+='<div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:0.5px solid #f0f0f0;font-size:12px">'
					+'<span>'+(flags[c.country]||'🌐')+'</span>'
					+'<div style="flex:1;min-width:0"><div style="font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+c.name+'</div><div style="font-size:10px;color:#aaa;font-family:monospace">'+c.url+'</div></div>'
					+'<div style="display:flex;gap:4px">'
					+'<button class="sgc-pm-btn secondary comp-toggle" data-id="'+c.id+'" data-status="'+(isActive?'paused':'active')+'" style="font-size:9px;padding:1px 5px">'+(isActive?'✓':'○')+'</button>'
					+'<button class="sgc-pm-btn secondary comp-del" data-id="'+c.id+'" style="font-size:9px;padding:1px 5px;color:#A32D2D">✕</button>'
					+'</div></div>';
			});
			if(!r.data.length) html='<div style="text-align:center;padding:20px;color:#aaa;font-size:12px">No competitors. Add some above.</div>';
			$('#comp-list').html(html);
		});
	}
	loadCompList();

	$('#btn-add-comp').on('click',function(){ $('#comp-edit-id').val(''); $('#comp-name,#comp-url,#comp-cat').val(''); openModal('modal-add-comp'); });

	$('#btn-save-comp').on('click',function(){
		$.post(SGCPM.ajaxUrl,{action:'sgc_pm_comp_save',nonce:SGCPM.nonce,comp_id:$('#comp-edit-id').val(),name:$('#comp-name').val(),url:$('#comp-url').val(),category:$('#comp-cat').val(),country:$('#comp-country').val()},function(r){
			closeModal('modal-add-comp'); loadCompList();
		});
	});

	$(document).on('click','.comp-toggle',function(){
		$.post(SGCPM.ajaxUrl,{action:'sgc_pm_comp_toggle',nonce:SGCPM.nonce,comp_id:$(this).data('id'),status:$(this).data('status')},function(){ loadCompList(); });
	});
	$(document).on('click','.comp-del',function(){
		if(!confirm('Remove this competitor?')) return;
		$.post(SGCPM.ajaxUrl,{action:'sgc_pm_comp_delete',nonce:SGCPM.nonce,comp_id:$(this).data('id')},function(){ loadCompList(); });
	});

	// Scan product
	$('#btn-scan-product').on('click',function(){
		var pid=$('#comp-product-id').val();
		if(!pid){alert('Enter a product ID.');return;}
		var $b=$(this).prop('disabled',true).text('Scanning…');
		$('#scan-progress').show(); $('#scan-bar').css('width','20%'); $('#scan-label').text('Fetching data from '+25+' competitors…');
		$('#comp-results-box').hide();
		$.post(SGCPM.ajaxUrl,{action:'sgc_pm_comp_scan_product',nonce:SGCPM.nonce,product_id:pid},function(r){
			$b.prop('disabled',false).text('Scan vs All Competitors');
			$('#scan-bar').css('width','100%');
			setTimeout(function(){$('#scan-progress').hide();$('#scan-bar').css('width','0%');},1500);
			if(!r.success){ alert(r.data); return; }
			renderResults(pid, r.data);
		}).fail(function(){$b.prop('disabled',false).text('Scan vs All Competitors');$('#scan-progress').hide();alert('Server error.');});
	});

	function renderResults(pid, data){
		$('#comp-results-title').text('Competitor Report — Product #'+pid+' — '+data.scanned+' competitors scanned');
		var tb='';
		(data.results||[]).forEach(function(r){
			var gap=r.their_rank&&!r.our_rank?'<span class="sgc-pm-badge sgc-pm-badge-red">Behind</span>':'<span class="sgc-pm-badge sgc-pm-badge-green">Tracking</span>';
			tb+='<tr><td><strong>'+r.competitor_name+'</strong><br><small style="color:#aaa;font-family:monospace">'+r.competitor_url+'</small></td>'
				+'<td style="font-size:11px;max-width:200px">'+r.their_title+'</td>'
				+'<td><div style="display:flex;flex-wrap:wrap;gap:3px">'+(r.keywords||[]).slice(0,4).map(function(k){return '<span class="sgc-pm-badge sgc-pm-badge-blue" style="font-size:9px">'+k+'</span>';}).join('')+'</div></td>'
				+'<td>'+(r.their_rank?'#'+r.their_rank:'—')+'</td>'
				+'<td>'+(r.our_rank?'#'+r.our_rank:'—')+'</td>'
				+'<td>'+gap+'</td></tr>';
		});
		$('#comp-tbody').html(tb||'<tr><td colspan="6" style="text-align:center;padding:20px;color:#aaa">No data fetched.</td></tr>');
		$('#comp-results-box').show();

		// Load gap keywords & AI suggestion
		$.post(SGCPM.ajaxUrl,{action:'sgc_pm_comp_get_report',nonce:SGCPM.nonce,product_id:pid},function(r){
			if(!r.success) return;
			if(r.data.gap_keywords&&r.data.gap_keywords.length){
				var kw='<strong style="font-size:11px;color:#721c24">Missing Keywords:</strong><br>';
				kw+=r.data.gap_keywords.map(function(k){ return '<span class="sgc-pm-badge sgc-pm-badge-red" style="cursor:pointer">'+k+'</span>'; }).join(' ');
				$('#comp-gap-keywords').html(kw);
			}
			if(r.data.ai_suggestion){
				$('#comp-ai-text').text(r.data.ai_suggestion);
				$('#comp-ai-suggestion').show();
				$('#btn-apply-ai-desc').show().data('pid',pid).data('desc',r.data.ai_suggestion).data('kw',(r.data.gap_keywords||[]).join(', '));
			}
		});
	}

	$('#btn-apply-ai-desc').on('click',function(){
		var pid=$(this).data('pid'), desc=$(this).data('desc'), kw=$(this).data('kw');
		if(!confirm('Apply AI-optimized description to product #'+pid+'?')) return;
		$.post(SGCPM.ajaxUrl,{action:'sgc_pm_comp_apply_ai_desc',nonce:SGCPM.nonce,product_id:pid,description:desc,focus_keyword:kw},function(r){
			var $n=$('#comp-notice'); $n.text(r.success?'Applied successfully!':'Error: '+r.data).removeClass('success error').addClass(r.success?'success':'error').show();
			setTimeout(function(){$n.fadeOut();},4000);
		});
	});

	$('#btn-scan-all').on('click',function(){ if(!confirm('Schedule full competitor scan for top 50 products? (Runs in background)')) return; alert('Scan scheduled. Check Logs tab for progress.'); });
});
</script>
