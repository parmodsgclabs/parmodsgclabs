<?php
/**
 * SGC Smart CRM Pro — Lead Pipeline Tab v1.1.0
 *
 * Per-contact lead tracking with status, scheduled follow-up,
 * internal notes, and AI-generated auto follow-up emails.
 *
 * @package SGC_Smart_CRM
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Lead status definitions.
$lead_statuses = array(
	'none'      => array( 'label' => '—',           'color' => '#aaa',    'bg' => '#f5f5f5' ),
	'new'       => array( 'label' => '🆕 New',       'color' => '#1a73e8', 'bg' => '#e8f0fe' ),
	'contacted' => array( 'label' => '📞 Contacted', 'color' => '#f9a825', 'bg' => '#fff8e1' ),
	'quoted'    => array( 'label' => '📋 Quoted',    'color' => '#7b1fa2', 'bg' => '#f3e5f5' ),
	'follow_up' => array( 'label' => '🔔 Follow-Up', 'color' => '#e65100', 'bg' => '#fff3e0' ),
	'won'       => array( 'label' => '✅ Won',        'color' => '#2e7d32', 'bg' => '#e8f5e9' ),
	'lost'      => array( 'label' => '❌ Lost',       'color' => '#c62828', 'bg' => '#ffebee' ),
);

// Fetch contacts that have a lead_status set (not 'none') OR followup_at set.
global $wpdb;
$ct    = $wpdb->prefix . 'sgc_crm_contacts';
$flt   = isset( $_GET['ls'] ) ? sanitize_key( $_GET['ls'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$srch  = isset( $_GET['ps'] ) ? sanitize_text_field( wp_unslash( $_GET['ps'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$where = "WHERE (lead_status != 'none' OR followup_at IS NOT NULL)";
$args  = array();

if ( $flt && array_key_exists( $flt, $lead_statuses ) ) {
	$where .= ' AND lead_status = %s';
	$args[] = $flt;
}
if ( $srch ) {
	$where .= ' AND (first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR company LIKE %s)';
	$like   = '%' . $wpdb->esc_like( $srch ) . '%';
	$args   = array_merge( $args, array( $like, $like, $like, $like ) );
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
$sql   = "SELECT * FROM `{$ct}` {$where} ORDER BY CASE WHEN followup_at IS NOT NULL AND followup_at <= NOW() THEN 0 ELSE 1 END, followup_at ASC, updated_at DESC LIMIT 200";
$leads = empty( $args ) ? $wpdb->get_results( $sql ) : $wpdb->get_results( $wpdb->prepare( $sql, $args ) );

// Counts per status.
$counts_raw = $wpdb->get_results( "SELECT lead_status, COUNT(*) as cnt FROM `{$ct}` WHERE lead_status != 'none' GROUP BY lead_status" );
$counts     = array();
foreach ( $counts_raw as $r ) {
	$counts[ $r->lead_status ] = (int) $r->cnt;
}
$due_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$ct}` WHERE lead_status = 'follow_up' AND followup_at IS NOT NULL AND followup_at <= NOW()" );
// phpcs:enable
?>

<div class="sgc-pipeline-wrap">

<!-- ── HEADER ── -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px">
	<div>
		<h2 style="margin:0;font-size:20px;color:#1a1a2e">🎯 Lead Pipeline</h2>
		<p style="margin:4px 0 0;color:#888;font-size:13px">Track every B2B inquiry from first contact to won order. Auto follow-up emails included.</p>
	</div>
	<button class="sgc-crm-btn primary" id="btn-add-lead" style="padding:9px 20px">
		＋ Add Lead
	</button>
</div>

<?php if ( $due_count > 0 ) : ?>
<div style="background:#fff3e0;border-left:4px solid #e65100;border-radius:6px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px">
	<span style="font-size:20px">🔔</span>
	<div>
		<strong style="color:#e65100"><?php echo (int) $due_count; ?> follow-up<?php echo $due_count > 1 ? 's' : ''; ?> overdue!</strong>
		<span style="color:#888;font-size:13px;margin-left:8px">Auto emails will send on next hourly cron. Or <a href="#" id="trigger-followups-now" style="color:#e65100">send now</a>.</span>
	</div>
</div>
<?php endif; ?>

<!-- ── STATUS FILTER PILLS ── -->
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:center">
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=sgc-smart-crm&tab=pipeline' ) ); ?>"
	   style="text-decoration:none;padding:5px 14px;border-radius:20px;font-size:13px;font-weight:600;border:2px solid <?php echo $flt === '' ? '#1a1a2e' : '#ddd'; ?>;background:<?php echo $flt === '' ? '#1a1a2e' : '#fff'; ?>;color:<?php echo $flt === '' ? '#fff' : '#555'; ?>">
		All (<?php echo array_sum( $counts ); ?>)
	</a>
	<?php foreach ( $lead_statuses as $slug => $info ) :
		if ( $slug === 'none' ) continue;
		$cnt = $counts[ $slug ] ?? 0;
		$active = $flt === $slug;
	?>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=sgc-smart-crm&tab=pipeline&ls=' . $slug ) ); ?>"
	   style="text-decoration:none;padding:5px 14px;border-radius:20px;font-size:13px;font-weight:600;border:2px solid <?php echo $active ? $info['color'] : '#ddd'; ?>;background:<?php echo $active ? $info['bg'] : '#fff'; ?>;color:<?php echo $active ? $info['color'] : '#888'; ?>">
		<?php echo esc_html( $info['label'] ); ?> (<?php echo (int) $cnt; ?>)
	</a>
	<?php endforeach; ?>

	<!-- Search -->
	<form method="get" style="margin-left:auto;display:flex;gap:6px">
		<input type="hidden" name="page" value="sgc-smart-crm">
		<input type="hidden" name="tab" value="pipeline">
		<?php if ( $flt ) : ?><input type="hidden" name="ls" value="<?php echo esc_attr( $flt ); ?>"><?php endif; ?>
		<input type="text" name="ps" value="<?php echo esc_attr( $srch ); ?>" placeholder="Search leads…"
			style="border:1px solid #ddd;border-radius:6px;padding:6px 12px;font-size:13px;width:180px">
		<button type="submit" class="sgc-crm-btn secondary" style="padding:6px 14px;font-size:13px">🔍</button>
	</form>
</div>

<!-- ── LEADS TABLE ── -->
<div class="sgc-crm-box" style="padding:0;overflow:hidden">
<?php if ( empty( $leads ) ) : ?>
	<div style="padding:40px;text-align:center;color:#888">
		<div style="font-size:40px;margin-bottom:10px">🎯</div>
		<p style="margin:0;font-size:15px">No leads found. Add a lead to start tracking!</p>
		<p style="font-size:13px;color:#aaa;margin-top:6px">Tip: Open any contact from the Contacts tab and click "Move to Pipeline"</p>
	</div>
<?php else : ?>
	<table class="wp-list-table widefat fixed" style="border:none">
		<thead>
			<tr style="background:#f8f8f8">
				<th style="width:220px;padding:12px 16px;font-size:12px;color:#666;text-transform:uppercase;letter-spacing:.5px">Contact</th>
				<th style="width:120px;padding:12px 8px;font-size:12px;color:#666;text-transform:uppercase;letter-spacing:.5px">Status</th>
				<th style="width:150px;padding:12px 8px;font-size:12px;color:#666;text-transform:uppercase;letter-spacing:.5px">Follow-Up Date</th>
				<th style="padding:12px 8px;font-size:12px;color:#666;text-transform:uppercase;letter-spacing:.5px">Note / Product</th>
				<th style="width:170px;padding:12px 8px;font-size:12px;color:#666;text-transform:uppercase;letter-spacing:.5px;text-align:right">Actions</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $leads as $lead ) :
			$info       = $lead_statuses[ $lead->lead_status ] ?? $lead_statuses['none'];
			$overdue    = $lead->followup_at && strtotime( $lead->followup_at ) <= time() && $lead->lead_status === 'follow_up';
			$today      = $lead->followup_at && gmdate( 'Y-m-d', strtotime( $lead->followup_at ) ) === gmdate( 'Y-m-d' );
			$full_name  = trim( $lead->first_name . ' ' . $lead->last_name ) ?: 'Unknown';
		?>
		<tr style="border-top:1px solid #f0f0f0;<?php echo $overdue ? 'background:#fffbf0;' : ''; ?>" data-id="<?php echo (int) $lead->id; ?>">

			<!-- Contact info -->
			<td style="padding:12px 16px;vertical-align:middle">
				<div style="font-weight:700;font-size:13px;color:#1a1a2e"><?php echo esc_html( $full_name ); ?></div>
				<div style="font-size:12px;color:#888;margin-top:2px"><?php echo esc_html( $lead->email ); ?></div>
				<?php if ( $lead->company ) : ?>
				<div style="font-size:11px;color:#aaa;margin-top:1px">🏢 <?php echo esc_html( $lead->company ); ?></div>
				<?php endif; ?>
				<?php if ( $lead->country ) : ?>
				<div style="font-size:11px;color:#aaa">🌍 <?php echo esc_html( $lead->country ); ?></div>
				<?php endif; ?>
			</td>

			<!-- Status badge -->
			<td style="padding:12px 8px;vertical-align:middle">
				<select class="pipeline-status-select" data-id="<?php echo (int) $lead->id; ?>"
						style="border:1px solid <?php echo esc_attr( $info['color'] ); ?>;border-radius:12px;padding:4px 8px;font-size:12px;font-weight:600;color:<?php echo esc_attr( $info['color'] ); ?>;background:<?php echo esc_attr( $info['bg'] ); ?>;cursor:pointer;max-width:120px">
					<?php foreach ( $lead_statuses as $slug => $si ) :
						if ( $slug === 'none' ) continue; ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $lead->lead_status, $slug ); ?>><?php echo esc_html( $si['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>

			<!-- Follow-up date -->
			<td style="padding:12px 8px;vertical-align:middle">
				<?php if ( $overdue ) : ?>
					<span style="color:#e65100;font-weight:700;font-size:12px">⚠️ Overdue!</span><br>
					<span style="font-size:11px;color:#aaa"><?php echo esc_html( gmdate( 'M d, Y', strtotime( $lead->followup_at ) ) ); ?></span>
				<?php elseif ( $today ) : ?>
					<span style="color:#2e7d32;font-weight:700;font-size:12px">📅 Today</span><br>
					<span style="font-size:11px;color:#aaa"><?php echo esc_html( gmdate( 'M d, Y', strtotime( $lead->followup_at ) ) ); ?></span>
				<?php elseif ( $lead->followup_at ) : ?>
					<span style="font-size:13px;color:#444"><?php echo esc_html( gmdate( 'M d, Y', strtotime( $lead->followup_at ) ) ); ?></span>
				<?php else : ?>
					<span style="color:#ccc;font-size:12px">—</span>
				<?php endif; ?>
			</td>

			<!-- Note -->
			<td style="padding:12px 8px;vertical-align:middle;max-width:250px">
				<div style="font-size:13px;color:#444;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:240px"
					 title="<?php echo esc_attr( $lead->followup_note ); ?>">
					<?php echo $lead->followup_note ? esc_html( $lead->followup_note ) : '<span style="color:#ccc">No note</span>'; ?>
				</div>
			</td>

			<!-- Actions -->
			<td style="padding:12px 8px;vertical-align:middle;text-align:right;white-space:nowrap">
				<button class="sgc-crm-btn secondary btn-edit-lead" data-id="<?php echo (int) $lead->id; ?>"
						data-name="<?php echo esc_attr( $full_name ); ?>"
						data-email="<?php echo esc_attr( $lead->email ); ?>"
						data-status="<?php echo esc_attr( $lead->lead_status ); ?>"
						data-followup="<?php echo esc_attr( $lead->followup_at ? gmdate( 'Y-m-d', strtotime( $lead->followup_at ) ) : '' ); ?>"
						data-note="<?php echo esc_attr( $lead->followup_note ); ?>"
						style="font-size:12px;padding:5px 10px;margin-right:4px">
					✏️ Edit
				</button>
				<button class="sgc-crm-btn secondary btn-send-manual" data-id="<?php echo (int) $lead->id; ?>" data-email="<?php echo esc_attr( $lead->email ); ?>"
						style="font-size:12px;padding:5px 10px" title="Send follow-up email now">
					📧 Send
				</button>
			</td>
		</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
</div>

<!-- ── ADD / EDIT LEAD MODAL ── -->
<div id="pipeline-modal" class="sgc-crm-modal" style="display:none">
	<div class="sgc-crm-modal-box" style="max-width:520px">
		<div class="sgc-crm-modal-header">
			<h3 id="pipeline-modal-title" style="margin:0;font-size:16px">Add Lead</h3>
			<button class="sgc-crm-modal-close">✕</button>
		</div>
		<div class="sgc-crm-modal-body" style="padding:20px">

			<input type="hidden" id="pipeline-contact-id" value="0">

			<div style="margin-bottom:14px">
				<label style="font-size:13px;font-weight:600;color:#444;display:block;margin-bottom:5px">Contact Email *</label>
				<input type="email" id="pipeline-email" placeholder="marc@amkglass.com" style="width:100%;border:1px solid #ddd;border-radius:6px;padding:8px 12px;font-size:14px;box-sizing:border-box">
				<div id="pipeline-contact-preview" style="margin-top:6px;font-size:12px;color:#888"></div>
			</div>

			<div style="margin-bottom:14px">
				<label style="font-size:13px;font-weight:600;color:#444;display:block;margin-bottom:5px">Lead Status</label>
				<select id="pipeline-status" style="width:100%;border:1px solid #ddd;border-radius:6px;padding:8px 12px;font-size:14px">
					<?php foreach ( $lead_statuses as $slug => $si ) :
						if ( $slug === 'none' ) continue; ?>
					<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $si['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div style="margin-bottom:14px">
				<label style="font-size:13px;font-weight:600;color:#444;display:block;margin-bottom:5px">Schedule Follow-Up Date</label>
				<input type="date" id="pipeline-followup-date" style="width:100%;border:1px solid #ddd;border-radius:6px;padding:8px 12px;font-size:14px;box-sizing:border-box"
					   min="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>">
				<div style="font-size:11px;color:#aaa;margin-top:4px">Leave blank = no auto email scheduled</div>
			</div>

			<div style="margin-bottom:6px">
				<label style="font-size:13px;font-weight:600;color:#444;display:block;margin-bottom:5px">Note / Product / Context
					<span style="font-weight:400;color:#aaa">(used in auto email)</span>
				</label>
				<textarea id="pipeline-note" rows="4" placeholder="e.g. PTFE Key Stopcock 1000 pcs, Ref: SGCQ-538737. Quote sent Mar 27. Waiting for PI confirmation."
						  style="width:100%;border:1px solid #ddd;border-radius:6px;padding:8px 12px;font-size:13px;box-sizing:border-box;resize:vertical"></textarea>
			</div>

			<div id="pipeline-modal-notice" style="display:none;padding:8px 12px;border-radius:6px;font-size:13px;margin-bottom:12px"></div>

			<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:18px">
				<button class="sgc-crm-btn secondary sgc-crm-modal-close">Cancel</button>
				<button class="sgc-crm-btn primary" id="btn-save-lead" style="min-width:100px">Save Lead</button>
			</div>
		</div>
	</div>
</div>

<!-- ── SEND MANUAL FOLLOWUP MODAL ── -->
<div id="send-followup-modal" class="sgc-crm-modal" style="display:none">
	<div class="sgc-crm-modal-box" style="max-width:460px">
		<div class="sgc-crm-modal-header">
			<h3 style="margin:0;font-size:16px">📧 Send Follow-Up Email</h3>
			<button class="sgc-crm-modal-close">✕</button>
		</div>
		<div class="sgc-crm-modal-body" style="padding:20px">
			<p style="margin-top:0;color:#444;font-size:14px">
				Send an AI-generated follow-up email to <strong id="sfm-email"></strong> now?
			</p>
			<div style="background:#f0f7ff;border-radius:6px;padding:12px 16px;font-size:13px;color:#2271b1">
				🤖 Email content will be generated by AI based on the lead note / context you saved. Check your AI provider in Settings.
			</div>
			<input type="hidden" id="sfm-contact-id" value="0">
			<div id="sfm-notice" style="display:none;padding:8px 12px;border-radius:6px;font-size:13px;margin-top:12px"></div>
			<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:18px">
				<button class="sgc-crm-btn secondary sgc-crm-modal-close">Cancel</button>
				<button class="sgc-crm-btn primary" id="btn-confirm-send-followup" style="min-width:120px">Send Now</button>
			</div>
		</div>
	</div>
</div>

</div><!-- .sgc-pipeline-wrap -->

<script>
(function($){

	// ── Open Add Lead modal
	$('#btn-add-lead').on('click', function(){
		$('#pipeline-modal-title').text('Add Lead');
		$('#pipeline-contact-id').val(0);
		$('#pipeline-email').val('').prop('disabled', false);
		$('#pipeline-status').val('new');
		$('#pipeline-followup-date').val('');
		$('#pipeline-note').val('');
		$('#pipeline-contact-preview').text('');
		$('#pipeline-modal-notice').hide();
		$('#pipeline-modal').show();
	});

	// ── Open Edit Lead modal
	$(document).on('click', '.btn-edit-lead', function(){
		var b = $(this);
		$('#pipeline-modal-title').text('Edit Lead — ' + b.data('name'));
		$('#pipeline-contact-id').val(b.data('id'));
		$('#pipeline-email').val(b.data('email')).prop('disabled', true);
		$('#pipeline-status').val(b.data('status'));
		$('#pipeline-followup-date').val(b.data('followup'));
		$('#pipeline-note').val(b.data('note'));
		$('#pipeline-contact-preview').text('');
		$('#pipeline-modal-notice').hide();
		$('#pipeline-modal').show();
	});

	// ── Email lookup for new leads
	var lookupTimer;
	$('#pipeline-email').on('input', function(){
		clearTimeout(lookupTimer);
		var email = $(this).val().trim();
		if ( email.length < 5 ) { $('#pipeline-contact-preview').text(''); return; }
		lookupTimer = setTimeout(function(){
			$.post(SGC_CRM.ajax_url, {
				action: 'sgc_crm_pipeline_lookup',
				nonce: SGC_CRM.nonce,
				email: email
			}, function(res){
				if ( res.success && res.data ) {
					$('#pipeline-contact-preview').html(
						'<span style="color:#2e7d32">✓ Found: <strong>' + res.data.first_name + ' ' + res.data.last_name + '</strong> — ' + res.data.company + '</span>'
					);
					$('#pipeline-contact-id').val(res.data.id);
				} else {
					$('#pipeline-contact-preview').html(
						'<span style="color:#e65100">⚠️ Not in CRM. Save will add as new contact.</span>'
					);
					$('#pipeline-contact-id').val(0);
				}
			});
		}, 500);
	});

	// ── Save lead
	$('#btn-save-lead').on('click', function(){
		var btn = $(this).text('Saving…').prop('disabled', true);
		$.post(SGC_CRM.ajax_url, {
			action: 'sgc_crm_pipeline_save',
			nonce: SGC_CRM.nonce,
			contact_id: $('#pipeline-contact-id').val(),
			email: $('#pipeline-email').val().trim(),
			lead_status: $('#pipeline-status').val(),
			followup_at: $('#pipeline-followup-date').val(),
			followup_note: $('#pipeline-note').val().trim()
		}, function(res){
			btn.text('Save Lead').prop('disabled', false);
			var $n = $('#pipeline-modal-notice');
			if ( res.success ) {
				$n.text('✓ Saved!').css({background:'#e8f5e9',color:'#2e7d32'}).show();
				setTimeout(function(){ location.reload(); }, 900);
			} else {
				$n.text('❌ ' + (res.data || 'Error')).css({background:'#ffebee',color:'#c62828'}).show();
			}
		}).fail(function(){
			btn.text('Save Lead').prop('disabled', false);
			$('#pipeline-modal-notice').text('❌ Network error').css({background:'#ffebee',color:'#c62828'}).show();
		});
	});

	// ── Inline status change (dropdown)
	$(document).on('change', '.pipeline-status-select', function(){
		var $sel = $(this), id = $sel.data('id'), status = $sel.val();
		$.post(SGC_CRM.ajax_url, {
			action: 'sgc_crm_pipeline_update_status',
			nonce: SGC_CRM.nonce,
			contact_id: id,
			lead_status: status
		}, function(res){
			if ( ! res.success ) {
				alert('Could not save status. Please try again.');
			}
		});
	});

	// ── Open send followup modal
	$(document).on('click', '.btn-send-manual', function(){
		$('#sfm-contact-id').val($(this).data('id'));
		$('#sfm-email').text($(this).data('email'));
		$('#sfm-notice').hide();
		$('#send-followup-modal').show();
	});

	// ── Confirm send followup
	$('#btn-confirm-send-followup').on('click', function(){
		var btn = $(this).text('Sending…').prop('disabled', true);
		$.post(SGC_CRM.ajax_url, {
			action: 'sgc_crm_pipeline_send_followup',
			nonce: SGC_CRM.nonce,
			contact_id: $('#sfm-contact-id').val()
		}, function(res){
			btn.text('Send Now').prop('disabled', false);
			var $n = $('#sfm-notice');
			if ( res.success ) {
				$n.text('✓ Email sent!').css({background:'#e8f5e9',color:'#2e7d32'}).show();
				setTimeout(function(){ $('#send-followup-modal').hide(); location.reload(); }, 1200);
			} else {
				$n.text('❌ ' + (res.data || 'Error sending email')).css({background:'#ffebee',color:'#c62828'}).show();
			}
		});
	});

	// ── Trigger overdue followups now
	$('#trigger-followups-now').on('click', function(e){
		e.preventDefault();
		$(this).text('Running…');
		$.post(SGC_CRM.ajax_url, {
			action: 'sgc_crm_pipeline_run_overdue',
			nonce: SGC_CRM.nonce
		}, function(res){
			if ( res.success ) {
				alert('✓ ' + res.data);
				location.reload();
			} else {
				alert('Error: ' + res.data);
			}
		});
	});

	// ── Modal close
	$(document).on('click', '.sgc-crm-modal-close', function(){
		$(this).closest('.sgc-crm-modal').hide();
	});
	$(document).on('click', '.sgc-crm-modal', function(e){
		if ( $(e.target).hasClass('sgc-crm-modal') ) $(this).hide();
	});

})(jQuery);
</script>
