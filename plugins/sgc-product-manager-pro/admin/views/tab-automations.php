<?php defined( 'ABSPATH' ) || exit; ?>
<div class="sgc-pm-auto-page">

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
	<div>
		<h3 style="margin:0;font-size:15px">⚡ Automation Rules</h3>
		<p style="margin:4px 0 0;font-size:12px;color:#888">Rules run every hour. Each rule watches for a trigger and takes an action automatically.</p>
	</div>
	<button class="sgc-pm-btn primary" id="btn-new-rule">+ New Rule</button>
</div>

<!-- Pre-built rule templates -->
<div class="sgc-pm-box" style="margin-bottom:14px">
	<div style="font-size:12px;font-weight:600;color:#888;text-transform:uppercase;letter-spacing:.4px;margin-bottom:10px">Quick Templates — One Click Add</div>
	<div style="display:flex;gap:8px;flex-wrap:wrap">
		<?php
		$templates = [
			[ 'name' => 'Auto Draft when Out of Stock',         'trigger' => 'stock_below',  'conds' => [ ['type'=>'stock_qty','operator'=>'lt','value'=>'1'] ],  'action' => 'set_draft',         'data' => [] ],
			[ 'name' => 'Email Alert: Low Stock (< 5)',         'trigger' => 'stock_below',  'conds' => [ ['type'=>'stock_qty','operator'=>'lt','value'=>'5'] ],  'action' => 'send_email',        'data' => ['subject'=>'Low Stock Alert: {product}','body'=>'Product {product} has only {stock} units left.'] ],
			[ 'name' => 'AI SEO for New Products',              'trigger' => 'new_product',  'conds' => [],                                                       'action' => 'ai_generate_desc',  'data' => ['tone'=>'b2b_formal','desc_type'=>'both'] ],
			[ 'name' => 'Remove Sale Price every Sunday',       'trigger' => 'cron_weekly',  'conds' => [],                                                       'action' => 'remove_sale_price', 'data' => [] ],
			[ 'name' => 'Increase All Prices by 5% Monthly',   'trigger' => 'cron_weekly',  'conds' => [],                                                       'action' => 'adjust_price_pct',  'data' => ['pct'=>'5'] ],
			[ 'name' => 'Publish All Drafts with Stock',        'trigger' => 'cron_daily',   'conds' => [ ['type'=>'stock_qty','operator'=>'gt','value'=>'0'] ],  'action' => 'set_published',     'data' => [] ],
		];
		foreach ( $templates as $tpl ) :
		?>
		<button class="sgc-pm-btn secondary pm-tpl-rule" style="font-size:11px"
			data-name="<?php echo esc_attr( $tpl['name'] ); ?>"
			data-trigger="<?php echo esc_attr( $tpl['trigger'] ); ?>"
			data-conds="<?php echo esc_attr( wp_json_encode( $tpl['conds'] ) ); ?>"
			data-action="<?php echo esc_attr( $tpl['action'] ); ?>"
			data-data="<?php echo esc_attr( wp_json_encode( $tpl['data'] ) ); ?>">
			+ <?php echo esc_html( $tpl['name'] ); ?>
		</button>
		<?php endforeach; ?>
	</div>
</div>

<!-- Rules List -->
<div class="sgc-pm-box" style="padding:0;overflow:hidden">
	<table class="sgc-pm-table">
		<thead>
			<tr>
				<th>Rule Name</th>
				<th>Trigger</th>
				<th>Action</th>
				<th>Run Count</th>
				<th>Last Run</th>
				<th>Status</th>
				<th>Actions</th>
			</tr>
		</thead>
		<tbody id="pm-rules-tbody">
			<tr><td colspan="7" style="text-align:center;padding:32px;color:#888">Loading rules…</td></tr>
		</tbody>
	</table>
</div>

<div id="auto-notice" class="sgc-pm-notice" style="display:none;margin-top:10px"></div>
</div>

<!-- Rule Builder Modal -->
<div class="sgc-pm-modal" id="modal-rule" style="display:none">
	<div class="sgc-pm-modal-inner" style="max-width:600px">
		<div class="sgc-pm-modal-hdr">
			<h3 id="rule-modal-title">New Automation Rule</h3>
			<button class="pm-modal-close" data-modal="modal-rule">✕</button>
		</div>
		<div class="sgc-pm-modal-body">
			<input type="hidden" id="rule-id" value="">
			<div class="sgc-pm-fg">
				<label>Rule Name</label>
				<input type="text" id="rule-name" class="sgc-pm-input" placeholder="e.g. Auto Draft when out of stock" style="width:100%">
			</div>
			<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
				<div class="sgc-pm-fg">
					<label>Trigger (When?)</label>
					<select id="rule-trigger" class="sgc-pm-select" style="width:100%">
						<option value="cron_hourly">Every Hour (Cron)</option>
						<option value="cron_daily">Every Day (Cron)</option>
						<option value="cron_weekly">Every Week (Cron)</option>
						<option value="stock_below">Stock Changes</option>
						<option value="new_product">New Product Added</option>
					</select>
				</div>
				<div class="sgc-pm-fg">
					<label>Action (Do what?)</label>
					<select id="rule-action" class="sgc-pm-select" style="width:100%">
						<option value="set_draft">Set as Draft</option>
						<option value="set_published">Set as Published</option>
						<option value="set_out_of_stock">Set Out of Stock</option>
						<option value="set_in_stock">Set In Stock</option>
						<option value="adjust_price_pct">Adjust Price by %</option>
						<option value="set_sale_price_pct">Set Sale Price by %</option>
						<option value="remove_sale_price">Remove Sale Price</option>
						<option value="ai_generate_desc">AI Generate Description</option>
						<option value="send_email">Send Email Alert</option>
						<option value="assign_category">Assign Category</option>
					</select>
				</div>
			</div>

			<!-- Conditions -->
			<div style="margin-bottom:12px">
				<label style="display:block;font-size:12px;font-weight:600;color:#333;margin-bottom:8px">Conditions (optional — filter which products)</label>
				<div id="rule-conditions"></div>
				<button type="button" class="sgc-pm-btn secondary" id="btn-add-condition" style="font-size:11px;margin-top:6px">+ Add Condition</button>
			</div>

			<!-- Action Data (dynamic based on action) -->
			<div id="rule-action-data" style="background:#f8f8f8;border-radius:5px;padding:12px;display:none">
				<label style="display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:8px">Action Settings</label>
				<div id="rule-action-fields"></div>
			</div>
		</div>
		<div class="sgc-pm-modal-ftr">
			<button class="sgc-pm-btn secondary" id="btn-test-rule" style="margin-right:auto">Test (count affected)</button>
			<button class="sgc-pm-btn secondary pm-modal-close" data-modal="modal-rule">Cancel</button>
			<button class="sgc-pm-btn primary" id="btn-save-rule">Save Rule</button>
		</div>
	</div>
</div>

<script>
jQuery(function($){
	var condCount = 0;

	function loadRules(){
		$.post(SGCPM.ajaxUrl,{action:'sgc_pm_auto_get_rules',nonce:SGCPM.nonce},function(r){
			if(!r.success) return;
			var html='';
			if(!r.data.length){ html='<tr><td colspan="7" style="text-align:center;padding:32px;color:#888">No rules yet. Create your first rule.</td></tr>'; }
			else r.data.forEach(function(rule){
				var trigLabels={cron_hourly:'Every Hour',cron_daily:'Every Day',cron_weekly:'Every Week',stock_below:'Stock Change',new_product:'New Product'};
				var actLabels={set_draft:'Draft Product',set_published:'Publish Product',set_out_of_stock:'Out of Stock',remove_sale_price:'Remove Sale',adjust_price_pct:'Adjust Price %',ai_generate_desc:'AI SEO',send_email:'Email Alert',assign_category:'Assign Category'};
				var isActive=rule.status==='active';
				html+='<tr>'
					+'<td><strong>'+rule.name+'</strong></td>'
					+'<td><span class="sgc-pm-badge sgc-pm-badge-blue">'+(trigLabels[rule.trigger_type]||rule.trigger_type)+'</span></td>'
					+'<td><span class="sgc-pm-badge sgc-pm-badge-gray">'+(actLabels[rule.action_type]||rule.action_type)+'</span></td>'
					+'<td>'+rule.run_count+'</td>'
					+'<td style="font-size:11px;color:#aaa">'+(rule.last_run||'Never')+'</td>'
					+'<td><span class="sgc-pm-badge '+(isActive?'sgc-pm-badge-green':'sgc-pm-badge-red')+'">'+(isActive?'Active':'Paused')+'</span></td>'
					+'<td style="white-space:nowrap">'
					+'<button class="sgc-pm-btn secondary pm-rule-toggle" data-id="'+rule.id+'" data-status="'+(isActive?'paused':'active')+'" style="font-size:10px;padding:2px 7px">'+(isActive?'Pause':'Resume')+'</button> '
					+'<button class="sgc-pm-btn secondary pm-rule-run" data-id="'+rule.id+'" style="font-size:10px;padding:2px 7px">Run Now</button> '
					+'<button class="sgc-pm-btn secondary pm-rule-edit" data-rule="'+encodeURIComponent(JSON.stringify(rule))+'" style="font-size:10px;padding:2px 7px">Edit</button> '
					+'<button class="sgc-pm-btn pm-rule-del" data-id="'+rule.id+'" style="font-size:10px;padding:2px 7px;background:#A32D2D;color:#fff;border-color:#A32D2D">Del</button>'
					+'</td></tr>';
			});
			$('#pm-rules-tbody').html(html);
		});
	}

	loadRules();

	// Action data dynamic fields
	$('#rule-action').on('change',function(){
		var a=$(this).val();
		var fields={'adjust_price_pct':'<label style="font-size:12px">Percentage (negative = decrease)</label><input type="number" id="adata-pct" class="sgc-pm-input" placeholder="e.g. 10 = increase 10%" style="width:100%" step="0.1">',
			'set_sale_price_pct':'<label style="font-size:12px">Discount %</label><input type="number" id="adata-pct" class="sgc-pm-input" placeholder="e.g. 20 = 20% off regular price" style="width:100%" step="0.1" min="0" max="90">',
			'send_email':'<label style="font-size:12px">To Email</label><input type="email" id="adata-email" class="sgc-pm-input" value="'+SGCPM.adminEmail+'" style="width:100%;margin-bottom:6px"><label style="font-size:12px">Subject</label><input type="text" id="adata-subject" class="sgc-pm-input" value="Low Stock Alert: {product}" style="width:100%;margin-bottom:6px"><label style="font-size:12px">Body</label><textarea id="adata-body" class="sgc-pm-input" rows="3" style="width:100%">Product {product} needs attention. Stock: {stock}, Price: {price}</textarea>',
			'ai_generate_desc':'<label style="font-size:12px">Tone</label><select id="adata-tone" class="sgc-pm-select" style="width:100%;margin-bottom:6px"><option value="b2b_formal">B2B Formal</option><option value="educational">Educational</option><option value="export">Export</option></select><label style="font-size:12px">Type</label><select id="adata-desc-type" class="sgc-pm-select" style="width:100%"><option value="both">Main + Short</option><option value="main">Main Only</option><option value="short">Short Only</option></select>',
			'assign_category':'<label style="font-size:12px">Category ID</label><input type="number" id="adata-cat" class="sgc-pm-input" placeholder="WordPress term ID" style="width:100%">'};
		if(fields[a]){ $('#rule-action-fields').html(fields[a]); $('#rule-action-data').show(); }
		else $('#rule-action-data').hide();
	});

	// Add condition
	$('#btn-add-condition').on('click',function(){
		condCount++;
		var html='<div class="cond-row" style="display:flex;gap:6px;margin-bottom:6px;align-items:center">'
			+'<select class="sgc-pm-select cond-type" style="flex:1"><option value="stock_qty">Stock Qty</option><option value="stock_status">Stock Status</option><option value="category">Category ID</option><option value="no_sku">No SKU</option><option value="no_image">No Image</option><option value="no_description">No Description</option></select>'
			+'<select class="sgc-pm-select cond-op" style="width:80px"><option value="lt">Less than</option><option value="eq">Equals</option><option value="gt">Greater than</option></select>'
			+'<input type="text" class="sgc-pm-input cond-val" placeholder="value" style="width:80px">'
			+'<button class="sgc-pm-btn secondary cond-remove" style="padding:4px 8px;font-size:11px">✕</button></div>';
		$('#rule-conditions').append(html);
	});
	$(document).on('click','.cond-remove',function(){ $(this).closest('.cond-row').remove(); });

	// Template click
	$(document).on('click','.pm-tpl-rule',function(){
		var $t=$(this);
		var name=$t.data('name'),trigger=$t.data('trigger'),action=$t.data('action');
		$('#rule-id').val('');
		$('#rule-name').val(name);
		$('#rule-trigger').val(trigger);
		$('#rule-action').val(action).trigger('change');
		$('#rule-conditions').empty();
		$('#rule-modal-title').text('Add Rule: '+name);
		$('#modal-rule').addClass('show').css('display','flex');
	});

	// New rule
	$('#btn-new-rule').on('click',function(){
		$('#rule-id').val(''); $('#rule-name').val(''); $('#rule-trigger').val('cron_daily'); $('#rule-action').val('set_draft').trigger('change'); $('#rule-conditions').empty(); $('#rule-modal-title').text('New Automation Rule'); openModal('modal-rule');
	});

	// Edit rule
	$(document).on('click','.pm-rule-edit',function(){
		var rule=JSON.parse(decodeURIComponent($(this).data('rule')));
		$('#rule-id').val(rule.id); $('#rule-name').val(rule.name); $('#rule-trigger').val(rule.trigger_type); $('#rule-action').val(rule.action_type).trigger('change'); $('#rule-modal-title').text('Edit Rule'); openModal('modal-rule');
	});

	// Save rule
	$('#btn-save-rule').on('click',function(){
		var conds=[];
		$('.cond-row').each(function(){ conds.push({type:$(this).find('.cond-type').val(),operator:$(this).find('.cond-op').val(),value:$(this).find('.cond-val').val()}); });
		var adata={};
		if($('#adata-pct').length) adata.pct=$('#adata-pct').val();
		if($('#adata-email').length){ adata.email=$('#adata-email').val(); adata.subject=$('#adata-subject').val(); adata.body=$('#adata-body').val(); }
		if($('#adata-tone').length){ adata.tone=$('#adata-tone').val(); adata.desc_type=$('#adata-desc-type').val(); }
		if($('#adata-cat').length) adata.cat_id=$('#adata-cat').val();

		$.post(SGCPM.ajaxUrl,{action:'sgc_pm_auto_save_rule',nonce:SGCPM.nonce,rule_id:$('#rule-id').val(),name:$('#rule-name').val(),trigger_type:$('#rule-trigger').val(),action_type:$('#rule-action').val(),conditions:conds,action_data:adata},function(r){
			closeModal('modal-rule');
			if(r.success){loadRules();}else alert(r.data);
		});
	});

	// Run now
	$(document).on('click','.pm-rule-run',function(){
		var id=$(this).data('id');
		$(this).prop('disabled',true).text('Running…');
		$.post(SGCPM.ajaxUrl,{action:'sgc_pm_auto_run_now',nonce:SGCPM.nonce,rule_id:id},function(r){
			var n=$('#auto-notice');
			n.text(r.success?r.data.message:r.data).removeClass('success error').addClass(r.success?'success':'error').show();
			setTimeout(function(){n.fadeOut();},4000);
			loadRules();
		});
	});

	// Toggle
	$(document).on('click','.pm-rule-toggle',function(){
		$.post(SGCPM.ajaxUrl,{action:'sgc_pm_auto_toggle_rule',nonce:SGCPM.nonce,rule_id:$(this).data('id'),status:$(this).data('status')},function(){loadRules();});
	});

	// Delete
	$(document).on('click','.pm-rule-del',function(){
		if(!confirm('Delete this rule?')) return;
		$.post(SGCPM.ajaxUrl,{action:'sgc_pm_auto_delete_rule',nonce:SGCPM.nonce,rule_id:$(this).data('id')},function(){loadRules();});
	});

	// Test
	$('#btn-test-rule').on('click',function(){
		var id=$('#rule-id').val();
		if(!id){alert('Save the rule first, then test.');return;}
		$.post(SGCPM.ajaxUrl,{action:'sgc_pm_auto_test_rule',nonce:SGCPM.nonce,rule_id:id},function(r){
			alert(r.success?r.data.message:r.data);
		});
	});
});
</script>
