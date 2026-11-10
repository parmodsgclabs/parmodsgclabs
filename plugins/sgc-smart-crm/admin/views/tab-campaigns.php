<?php
/**
 * SGC Smart CRM Pro — Campaigns Tab
 *
 * @package SGC_Smart_CRM
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="sgc-crm-campaigns-page">

	<div class="sgc-crm-toolbar">
		<div class="sgc-crm-toolbar-left">
			<select id="campaign-filter-type" class="sgc-crm-select">
				<option value=""><?php esc_html_e( 'All Types', 'sgc-smart-crm' ); ?></option>
				<option value="newsletter">📰 <?php esc_html_e( 'Newsletter', 'sgc-smart-crm' ); ?></option>
				<option value="product_promo">🛍️ <?php esc_html_e( 'Product Promo', 'sgc-smart-crm' ); ?></option>
				<option value="reorder_reminder">🔁 <?php esc_html_e( 'Reorder Reminder', 'sgc-smart-crm' ); ?></option>
				<option value="announcement">📣 <?php esc_html_e( 'Announcement', 'sgc-smart-crm' ); ?></option>
				<option value="student_special">🎓 <?php esc_html_e( 'Student Special', 'sgc-smart-crm' ); ?></option>
				<option value="dealer_special">🏪 <?php esc_html_e( 'Dealer Special', 'sgc-smart-crm' ); ?></option>
				<option value="glass_blower_special">🔥 <?php esc_html_e( 'Glass Blower Special', 'sgc-smart-crm' ); ?></option>
			</select>
			<select id="campaign-filter-status" class="sgc-crm-select">
				<option value=""><?php esc_html_e( 'All Status', 'sgc-smart-crm' ); ?></option>
				<option value="draft"><?php esc_html_e( 'Draft', 'sgc-smart-crm' ); ?></option>
				<option value="scheduled"><?php esc_html_e( 'Scheduled', 'sgc-smart-crm' ); ?></option>
				<option value="sending"><?php esc_html_e( 'Sending', 'sgc-smart-crm' ); ?></option>
				<option value="sent"><?php esc_html_e( 'Sent', 'sgc-smart-crm' ); ?></option>
			</select>
		</div>
		<div class="sgc-crm-toolbar-right">
			<button class="sgc-crm-btn secondary" id="btn-ai-generate">🤖 <?php esc_html_e( 'AI Generate', 'sgc-smart-crm' ); ?></button>
			<button class="sgc-crm-btn primary" id="btn-new-campaign">➕ <?php esc_html_e( 'New Campaign', 'sgc-smart-crm' ); ?></button>
		</div>
	</div>

	<div id="campaigns-notice" class="sgc-crm-notice" style="display:none"></div>

	<div class="sgc-crm-box">
		<div class="sgc-crm-table-wrap">
			<table class="sgc-crm-table" id="campaigns-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'sgc-smart-crm' ); ?></th>
						<th><?php esc_html_e( 'Type', 'sgc-smart-crm' ); ?></th>
						<th><?php esc_html_e( 'Subject', 'sgc-smart-crm' ); ?></th>
						<th><?php esc_html_e( 'Segment', 'sgc-smart-crm' ); ?></th>
						<th><?php esc_html_e( 'Sent', 'sgc-smart-crm' ); ?></th>
						<th><?php esc_html_e( 'Opens', 'sgc-smart-crm' ); ?></th>
						<th><?php esc_html_e( 'Clicks', 'sgc-smart-crm' ); ?></th>
						<th><?php esc_html_e( 'Status', 'sgc-smart-crm' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'sgc-smart-crm' ); ?></th>
					</tr>
				</thead>
				<tbody id="campaigns-tbody">
					<tr><td colspan="9" style="text-align:center;padding:32px"><?php esc_html_e( 'Loading…', 'sgc-smart-crm' ); ?></td></tr>
				</tbody>
			</table>
		</div>
	</div>
</div>

<!-- Campaign Edit Modal -->
<div class="sgc-crm-modal" id="campaign-modal" style="display:none">
	<div class="sgc-crm-modal-inner" style="max-width:800px">
		<div class="sgc-crm-modal-header">
			<h3 id="campaign-modal-title"><?php esc_html_e( 'New Campaign', 'sgc-smart-crm' ); ?></h3>
			<button class="sgc-crm-modal-close" data-modal="campaign-modal">✕</button>
		</div>
		<div class="sgc-crm-modal-body">
			<input type="hidden" id="campaign-id">
			<div class="sgc-crm-form-row sgc-crm-form-2col">
				<div>
					<label><?php esc_html_e( 'Campaign Name *', 'sgc-smart-crm' ); ?></label>
					<input type="text" id="campaign-name" class="sgc-crm-input" required>
				</div>
				<div>
					<label><?php esc_html_e( 'Campaign Type', 'sgc-smart-crm' ); ?></label>
					<select id="campaign-type" class="sgc-crm-select">
						<option value="newsletter">📰 <?php esc_html_e( 'Newsletter', 'sgc-smart-crm' ); ?></option>
						<option value="product_promo">🛍️ <?php esc_html_e( 'Product Promo', 'sgc-smart-crm' ); ?></option>
						<option value="reorder_reminder">🔁 <?php esc_html_e( 'Reorder Reminder', 'sgc-smart-crm' ); ?></option>
						<option value="announcement">📣 <?php esc_html_e( 'Announcement', 'sgc-smart-crm' ); ?></option>
						<option value="student_special">🎓 <?php esc_html_e( 'Student Special', 'sgc-smart-crm' ); ?></option>
						<option value="dealer_special">🏪 <?php esc_html_e( 'Dealer Special', 'sgc-smart-crm' ); ?></option>
						<option value="glass_blower_special">🔥 <?php esc_html_e( 'Glass Blower Special', 'sgc-smart-crm' ); ?></option>
					</select>
				</div>
			</div>
			<div class="sgc-crm-form-row">
				<label><?php esc_html_e( 'Subject Line *', 'sgc-smart-crm' ); ?></label>
				<input type="text" id="campaign-subject" class="sgc-crm-input" placeholder="<?php esc_attr_e( 'e.g. New Lab Glassware Arrivals — SGC LABS®', 'sgc-smart-crm' ); ?>">
			</div>
			<div class="sgc-crm-form-row">
				<label><?php esc_html_e( 'Preheader Text', 'sgc-smart-crm' ); ?></label>
				<input type="text" id="campaign-preheader" class="sgc-crm-input" placeholder="<?php esc_attr_e( 'Preview text shown in inbox…', 'sgc-smart-crm' ); ?>">
			</div>
			<div class="sgc-crm-form-row sgc-crm-form-2col">
				<div>
					<label><?php esc_html_e( 'Send To Audience', 'sgc-smart-crm' ); ?></label>
					<select id="campaign-segment" class="sgc-crm-select">
						<option value="all"><?php esc_html_e( 'All Subscribed', 'sgc-smart-crm' ); ?></option>
						<option value="student">🎓 <?php esc_html_e( 'Students', 'sgc-smart-crm' ); ?></option>
						<option value="teacher">📚 <?php esc_html_e( 'Teachers / Professors', 'sgc-smart-crm' ); ?></option>
						<option value="dealer">🏪 <?php esc_html_e( 'Dealers / Distributors', 'sgc-smart-crm' ); ?></option>
						<option value="end_user">🔬 <?php esc_html_e( 'End Users (Labs)', 'sgc-smart-crm' ); ?></option>
						<option value="glass_blower">🔥 <?php esc_html_e( 'Glass Blowers', 'sgc-smart-crm' ); ?></option>
						<option value="semi_finished">⚗️ <?php esc_html_e( 'Semi-finished Buyers', 'sgc-smart-crm' ); ?></option>
						<option value="international">🌍 <?php esc_html_e( 'International', 'sgc-smart-crm' ); ?></option>
						<option value="domestic">🇮🇳 <?php esc_html_e( 'Domestic (India)', 'sgc-smart-crm' ); ?></option>
					</select>
				</div>
				<div>
					<label><?php esc_html_e( 'Tags Filter (optional)', 'sgc-smart-crm' ); ?></label>
					<input type="text" id="campaign-tags-filter" class="sgc-crm-input" placeholder="lab,india">
				</div>
			</div>
			<div class="sgc-crm-form-row">
				<label><?php esc_html_e( 'Email Body HTML', 'sgc-smart-crm' ); ?></label>
				<textarea id="campaign-body" class="sgc-crm-textarea" rows="10" placeholder="<?php esc_attr_e( 'Paste HTML or use AI Generate to create content…', 'sgc-smart-crm' ); ?>"></textarea>
			</div>
			<div class="sgc-crm-form-row">
				<label><?php esc_html_e( 'Schedule (optional)', 'sgc-smart-crm' ); ?></label>
				<input type="datetime-local" id="campaign-schedule" class="sgc-crm-input" style="width:250px">
			</div>
			<div class="sgc-crm-form-row">
				<label><?php esc_html_e( 'Send Test Email', 'sgc-smart-crm' ); ?></label>
				<div style="display:flex;gap:8px">
					<input type="email" id="test-email" class="sgc-crm-input" placeholder="<?php esc_attr_e( 'test@email.com', 'sgc-smart-crm' ); ?>" style="flex:1">
					<button class="sgc-crm-btn secondary" id="btn-send-test"><?php esc_html_e( 'Send Test', 'sgc-smart-crm' ); ?></button>
				</div>
			</div>
		</div>
		<div class="sgc-crm-modal-footer">
			<button class="sgc-crm-btn secondary sgc-crm-modal-close" data-modal="campaign-modal"><?php esc_html_e( 'Cancel', 'sgc-smart-crm' ); ?></button>
			<button class="sgc-crm-btn secondary" id="btn-schedule-campaign"><?php esc_html_e( '📅 Schedule', 'sgc-smart-crm' ); ?></button>
			<button class="sgc-crm-btn primary" id="btn-save-campaign"><?php esc_html_e( 'Save Draft', 'sgc-smart-crm' ); ?></button>
			<button class="sgc-crm-btn primary" id="btn-send-now" style="background:#e4b04a;color:#000"><?php esc_html_e( '🚀 Send Now', 'sgc-smart-crm' ); ?></button>
		</div>
	</div>
</div>

<!-- AI Generate Modal -->
<div class="sgc-crm-modal" id="ai-modal" style="display:none">
	<div class="sgc-crm-modal-inner">
		<div class="sgc-crm-modal-header">
			<h3>🤖 <?php esc_html_e( 'AI Campaign Generator', 'sgc-smart-crm' ); ?></h3>
			<button class="sgc-crm-modal-close" data-modal="ai-modal">✕</button>
		</div>
		<div class="sgc-crm-modal-body">
			<div class="sgc-crm-form-row sgc-crm-form-2col">
				<div>
					<label><?php esc_html_e( 'Campaign Type', 'sgc-smart-crm' ); ?></label>
					<select id="ai-type" class="sgc-crm-select">
						<option value="newsletter">📰 <?php esc_html_e( 'Newsletter', 'sgc-smart-crm' ); ?></option>
						<option value="product_promo">🛍️ <?php esc_html_e( 'Product Promo', 'sgc-smart-crm' ); ?></option>
						<option value="reorder_reminder">🔁 <?php esc_html_e( 'Reorder Reminder', 'sgc-smart-crm' ); ?></option>
						<option value="announcement">📣 <?php esc_html_e( 'Announcement', 'sgc-smart-crm' ); ?></option>
						<option value="student_special">🎓 <?php esc_html_e( 'Student Special', 'sgc-smart-crm' ); ?></option>
						<option value="dealer_special">🏪 <?php esc_html_e( 'Dealer Special', 'sgc-smart-crm' ); ?></option>
						<option value="glass_blower_special">🔥 <?php esc_html_e( 'Glass Blower Special', 'sgc-smart-crm' ); ?></option>
					</select>
				</div>
				<div>
					<label><?php esc_html_e( 'Target Audience', 'sgc-smart-crm' ); ?></label>
					<select id="ai-audience" class="sgc-crm-select">
						<option value="all"><?php esc_html_e( 'All', 'sgc-smart-crm' ); ?></option>
						<option value="student">🎓 <?php esc_html_e( 'Students', 'sgc-smart-crm' ); ?></option>
						<option value="teacher">📚 <?php esc_html_e( 'Teachers', 'sgc-smart-crm' ); ?></option>
						<option value="dealer">🏪 <?php esc_html_e( 'Dealers', 'sgc-smart-crm' ); ?></option>
						<option value="end_user">🔬 <?php esc_html_e( 'End Users', 'sgc-smart-crm' ); ?></option>
						<option value="glass_blower">🔥 <?php esc_html_e( 'Glass Blowers', 'sgc-smart-crm' ); ?></option>
						<option value="semi_finished">⚗️ <?php esc_html_e( 'Semi-finished', 'sgc-smart-crm' ); ?></option>
						<option value="international">🌍 <?php esc_html_e( 'International', 'sgc-smart-crm' ); ?></option>
					</select>
				</div>
			</div>
			<div class="sgc-crm-form-row">
				<label><?php esc_html_e( 'Topic / Focus', 'sgc-smart-crm' ); ?></label>
				<input type="text" id="ai-topic" class="sgc-crm-input" placeholder="<?php esc_attr_e( 'e.g. Borosilicate flasks, new arrivals, Diwali discount…', 'sgc-smart-crm' ); ?>">
			</div>
			<div class="sgc-crm-form-row">
				<label><?php esc_html_e( 'Tone', 'sgc-smart-crm' ); ?></label>
				<select id="ai-tone" class="sgc-crm-select" style="width:240px">
					<option value="professional"><?php esc_html_e( 'Professional', 'sgc-smart-crm' ); ?></option>
					<option value="friendly"><?php esc_html_e( 'Friendly & Warm', 'sgc-smart-crm' ); ?></option>
					<option value="urgent"><?php esc_html_e( 'Urgent / Promotional', 'sgc-smart-crm' ); ?></option>
					<option value="technical"><?php esc_html_e( 'Technical / Detailed', 'sgc-smart-crm' ); ?></option>
				</select>
			</div>
			<div id="ai-preview" style="display:none">
				<hr>
				<label><?php esc_html_e( 'Generated Subject:', 'sgc-smart-crm' ); ?></label>
				<div id="ai-subject-preview" class="sgc-crm-preview-subject"></div>
				<label style="margin-top:12px;display:block"><?php esc_html_e( 'Preview:', 'sgc-smart-crm' ); ?></label>
				<iframe id="ai-body-preview" style="width:100%;height:350px;border:1px solid #ddd;border-radius:4px"></iframe>
			</div>
		</div>
		<div class="sgc-crm-modal-footer">
			<button class="sgc-crm-btn secondary sgc-crm-modal-close" data-modal="ai-modal"><?php esc_html_e( 'Cancel', 'sgc-smart-crm' ); ?></button>
			<button class="sgc-crm-btn secondary" id="btn-ai-regen" style="display:none">🔄 <?php esc_html_e( 'Regenerate', 'sgc-smart-crm' ); ?></button>
			<button class="sgc-crm-btn primary" id="btn-ai-generate-go">✨ <?php esc_html_e( 'Generate', 'sgc-smart-crm' ); ?></button>
			<button class="sgc-crm-btn primary" id="btn-ai-use" style="display:none;background:#27ae60"><?php esc_html_e( 'Use This →', 'sgc-smart-crm' ); ?></button>
		</div>
	</div>
</div>

<!-- Report Modal -->
<div class="sgc-crm-modal" id="report-modal" style="display:none">
	<div class="sgc-crm-modal-inner" style="max-width:700px">
		<div class="sgc-crm-modal-header">
			<h3>📊 <?php esc_html_e( 'Campaign Report', 'sgc-smart-crm' ); ?></h3>
			<button class="sgc-crm-modal-close" data-modal="report-modal">✕</button>
		</div>
		<div class="sgc-crm-modal-body" id="report-modal-body"></div>
	</div>
</div>

<script>
jQuery(function($){
	var aiData = null;

	function escHtml(s){ return $('<div>').text(s||'').html(); }
	function showNotice(m,t){ var $n=$('#campaigns-notice'); $n.text(m).removeClass('success error').addClass(t||'success').show(); setTimeout(function(){ $n.fadeOut(); },4000); }
	function statusBadge(s){ var c={draft:'grey',scheduled:'blue',sending:'orange',sent:'green'}; return '<span class="sgc-badge '+(c[s]||'grey')+'">'+s+'</span>'; }

	function loadCampaigns(){
		$.post(ajaxurl,{action:'sgc_crm_get_campaigns',nonce:SGC_CRM.nonce,type:$('#campaign-filter-type').val(),status:$('#campaign-filter-status').val()},function(r){
			if(!r.success) return;
			var tbody='';
			if(!r.data||!r.data.length){
				tbody='<tr><td colspan="9" style="text-align:center;padding:24px"><?php esc_html_e("No campaigns yet.","sgc-smart-crm"); ?></td></tr>';
			} else {
				r.data.forEach(function(c){
					var or = c.total_sent>0 ? Math.round(c.total_opened/c.total_sent*100)+'%' : '—';
					var cr = c.total_sent>0 ? Math.round(c.total_clicked/c.total_sent*100)+'%' : '—';
					tbody+='<tr>'
						+'<td><strong>'+escHtml(c.name)+'</strong>'+(c.ai_generated=='1'?' <span class="sgc-badge blue">AI</span>':'')+'</td>'
						+'<td>'+escHtml(c.type)+'</td>'
						+'<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+escHtml(c.subject)+'</td>'
						+'<td>'+escHtml(c.segment_filter)+'</td>'
						+'<td>'+c.total_sent+'</td>'
						+'<td>'+or+'</td>'
						+'<td>'+cr+'</td>'
						+'<td>'+statusBadge(c.status)+'</td>'
						+'<td class="sgc-crm-actions">'
							+(c.status==='draft'||c.status==='scheduled'?'<button class="sgc-btn-icon edit-campaign" data-id="'+c.id+'" title="Edit">✏️</button>':'')
							+(c.status==='sent'?'<button class="sgc-btn-icon view-report" data-id="'+c.id+'" title="Report">📊</button>':'')
							+(c.status==='draft'?'<button class="sgc-btn-icon send-now" data-id="'+c.id+'" title="Send Now">🚀</button>':'')
							+'<button class="sgc-btn-icon delete-campaign" data-id="'+c.id+'" title="Delete">🗑️</button>'
						+'</td>'
					+'</tr>';
				});
			}
			$('#campaigns-tbody').html(tbody);
		});
	}

	loadCampaigns();
	$('#campaign-filter-type,#campaign-filter-status').on('change', loadCampaigns);

	// New campaign modal
	$('#btn-new-campaign').on('click', function(){
		$('#campaign-id').val('');
		$('#campaign-modal-title').text('<?php esc_html_e("New Campaign","sgc-smart-crm"); ?>');
		$('#campaign-name,#campaign-subject,#campaign-preheader,#campaign-body,#campaign-tags-filter').val('');
		$('#campaign-type,#campaign-segment').val($('#campaign-type option:first').val());
		$('#campaign-modal').show();
	});

	// Edit campaign
	$(document).on('click','.edit-campaign',function(){
		var id=$(this).data('id');
		$.post(ajaxurl,{action:'sgc_crm_get_campaign',nonce:SGC_CRM.nonce,id:id},function(r){
			if(!r.success||!r.data) return;
			var c=r.data;
			$('#campaign-id').val(c.id); $('#campaign-modal-title').text('<?php esc_html_e("Edit Campaign","sgc-smart-crm"); ?>');
			$('#campaign-name').val(c.name); $('#campaign-subject').val(c.subject);
			$('#campaign-preheader').val(c.preheader); $('#campaign-body').val(c.body_html);
			$('#campaign-type').val(c.type); $('#campaign-segment').val(c.segment_filter);
			$('#campaign-tags-filter').val(c.tags_filter);
			$('#campaign-modal').show();
		});
	});

	// Save draft
	$('#btn-save-campaign').on('click',function(){
		var id=$('#campaign-id').val();
		var data={nonce:SGC_CRM.nonce,action:id?'sgc_crm_update_campaign':'sgc_crm_create_campaign',
			name:$('#campaign-name').val(), type:$('#campaign-type').val(), subject:$('#campaign-subject').val(),
			preheader:$('#campaign-preheader').val(), body_html:$('#campaign-body').val(),
			segment_filter:$('#campaign-segment').val(), tags_filter:$('#campaign-tags-filter').val()};
		if(id) data.id=id;
		$.post(ajaxurl,data,function(r){
			if(r.success){ $('#campaign-modal').hide(); showNotice('<?php esc_html_e("Campaign saved.","sgc-smart-crm"); ?>'); loadCampaigns(); }
			else showNotice(r.data,'error');
		});
	});

	// Schedule
	$('#btn-schedule-campaign').on('click',function(){
		var id=$('#campaign-id').val(), dt=$('#campaign-schedule').val();
		if(!dt){ showNotice('<?php esc_html_e("Pick a date/time first.","sgc-smart-crm"); ?>','error'); return; }
		if(!id){ $('#btn-save-campaign').click(); return; }
		$.post(ajaxurl,{action:'sgc_crm_schedule_campaign',nonce:SGC_CRM.nonce,id:id,scheduled_at:dt},function(r){
			if(r.success){ $('#campaign-modal').hide(); showNotice('<?php esc_html_e("Scheduled!","sgc-smart-crm"); ?>'); loadCampaigns(); }
			else showNotice(r.data,'error');
		});
	});

	// Send now (from modal)
	$('#btn-send-now').on('click',function(){
		if(!confirm(SGC_CRM.confirm_send)) return;
		var id=$('#campaign-id').val();
		if(!id){ showNotice('<?php esc_html_e("Save campaign first.","sgc-smart-crm"); ?>','error'); return; }
		$.post(ajaxurl,{action:'sgc_crm_send_campaign_now',nonce:SGC_CRM.nonce,id:id},function(r){
			if(r.success){ $('#campaign-modal').hide(); showNotice(r.data.message); loadCampaigns(); }
			else showNotice(r.data,'error');
		});
	});

	// Send now (from table)
	$(document).on('click','.send-now',function(){
		if(!confirm(SGC_CRM.confirm_send)) return;
		var id=$(this).data('id');
		$.post(ajaxurl,{action:'sgc_crm_send_campaign_now',nonce:SGC_CRM.nonce,id:id},function(r){
			if(r.success) showNotice(r.data.message); else showNotice(r.data,'error');
			loadCampaigns();
		});
	});

	// Test email
	$('#btn-send-test').on('click',function(){
		var id=$('#campaign-id').val(), email=$('#test-email').val();
		if(!id||!email){ showNotice('<?php esc_html_e("Save campaign and enter test email.","sgc-smart-crm"); ?>','error'); return; }
		var $b=$(this).text('Sending…').prop('disabled',true);
		$.post(ajaxurl,{action:'sgc_crm_send_test_email',nonce:SGC_CRM.nonce,id:id,test_email:email},function(r){
			$b.text('<?php esc_html_e("Send Test","sgc-smart-crm"); ?>').prop('disabled',false);
			showNotice(r.data, r.success?'success':'error');
		});
	});

	// Delete
	$(document).on('click','.delete-campaign',function(){
		if(!confirm(SGC_CRM.confirm_delete)) return;
		$.post(ajaxurl,{action:'sgc_crm_delete_campaign',nonce:SGC_CRM.nonce,id:$(this).data('id')},function(r){
			if(r.success) loadCampaigns(); else showNotice(r.data,'error');
		});
	});

	// Report
	$(document).on('click','.view-report',function(){
		var id=$(this).data('id');
		$('#report-modal-body').html('<p><?php esc_html_e("Loading…","sgc-smart-crm"); ?></p>');
		$('#report-modal').show();
		$.post(ajaxurl,{action:'sgc_crm_get_campaign_report',nonce:SGC_CRM.nonce,id:id},function(r){
			if(!r.success){ $('#report-modal-body').html('<p>'+r.data+'</p>'); return; }
			var d=r.data, s=d.stats;
			var html='<div class="sgc-crm-stat-cards" style="grid-template-columns:repeat(4,1fr);margin:0 0 16px">'
				+'<div class="sgc-crm-stat-card"><div class="sgc-crm-stat-num">'+s.sent+'</div><div class="sgc-crm-stat-label">Sent</div></div>'
				+'<div class="sgc-crm-stat-card"><div class="sgc-crm-stat-num">'+s.opens+'</div><div class="sgc-crm-stat-label">Opens</div></div>'
				+'<div class="sgc-crm-stat-card"><div class="sgc-crm-stat-num">'+d.open_rate+'%</div><div class="sgc-crm-stat-label">Open Rate</div></div>'
				+'<div class="sgc-crm-stat-card"><div class="sgc-crm-stat-num">'+d.click_rate+'%</div><div class="sgc-crm-stat-label">Click Rate</div></div>'
				+'</div>';
			if(d.openers&&d.openers.length){
				html+='<h4>Recent Openers</h4><table class="sgc-crm-table"><thead><tr><th>Email</th><th>Name</th><th>Country</th><th>Opened</th></tr></thead><tbody>';
				d.openers.forEach(function(o){ html+='<tr><td>'+escHtml(o.email)+'</td><td>'+escHtml(o.first_name)+'</td><td>'+escHtml(o.country)+'</td><td>'+escHtml(o.opened_at)+'</td></tr>'; });
				html+='</tbody></table>';
			}
			$('#report-modal-body').html(html);
		});
	});

	// AI Generate
	$('#btn-ai-generate').on('click',function(){ $('#ai-modal').show(); });
	function doAiGenerate(){
		var $btn=$('#btn-ai-generate-go').text('⏳ Generating…').prop('disabled',true);
		$('#btn-ai-regen,#btn-ai-use').hide(); $('#ai-preview').hide();
		$.post(ajaxurl,{action:'sgc_crm_ai_generate',nonce:SGC_CRM.nonce,
			type:$('#ai-type').val(), topic:$('#ai-topic').val(), audience:$('#ai-audience').val(),
			segment:$('#ai-audience').val(), tone:$('#ai-tone').val()
		},function(r){
			$btn.text('✨ <?php esc_html_e("Generate","sgc-smart-crm"); ?>').prop('disabled',false);
			if(r.success){
				aiData=r.data;
				$('#ai-subject-preview').text(r.data.subject);
				var iframe=$('#ai-body-preview')[0];
				iframe.contentDocument.open(); iframe.contentDocument.write(r.data.body_html||r.data.body||''); iframe.contentDocument.close();
				$('#ai-preview').show(); $('#btn-ai-regen,#btn-ai-use').show();
			} else {
				alert(r.data);
			}
		}).fail(function(){ $btn.text('✨ Generate').prop('disabled',false); });
	}
	$('#btn-ai-generate-go').on('click', doAiGenerate);
	$('#btn-ai-regen').on('click', doAiGenerate);
	$('#btn-ai-use').on('click',function(){
		if(!aiData) return;
		$('#ai-modal').hide();
		$('#campaign-id').val(''); $('#campaign-modal-title').text('<?php esc_html_e("New Campaign (AI Generated)","sgc-smart-crm"); ?>');
		$('#campaign-name').val(aiData.subject||'AI Campaign');
		$('#campaign-subject').val(aiData.subject||'');
		$('#campaign-preheader').val(aiData.preheader||'');
		$('#campaign-body').val(aiData.body_html||aiData.body||'');
		$('#campaign-modal').show();
	});

	$(document).on('click','.sgc-crm-modal-close',function(){ $('#'+$(this).data('modal')).hide(); });
	$(document).on('click','.sgc-crm-modal',function(e){ if($(e.target).hasClass('sgc-crm-modal')) $(this).hide(); });
});
</script>
