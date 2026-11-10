<?php
/**
 * SGC Smart CRM Pro — Dashboard Tab
 *
 * @package SGC_Smart_CRM
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$last_promo = get_option( 'sgc_crm_last_weekly_promo', '' );
$ai_provider = get_option( 'sgc_crm_ai_provider', 'gemini' );
$providers   = SGC_CRM_AI::providers();
$ai_label    = $providers[ $ai_provider ]['label'] ?? $ai_provider;
?>
<div class="sgc-crm-dashboard">

	<!-- Stat Cards -->
	<div class="sgc-crm-stat-cards" id="sgc-crm-stat-cards">
		<div class="sgc-crm-stat-card">
			<div class="sgc-crm-stat-icon">👥</div>
			<div class="sgc-crm-stat-body">
				<div class="sgc-crm-stat-num" id="stat-contacts">—</div>
				<div class="sgc-crm-stat-label"><?php esc_html_e( 'Total Contacts', 'sgc-smart-crm' ); ?></div>
			</div>
		</div>
		<div class="sgc-crm-stat-card">
			<div class="sgc-crm-stat-icon">✅</div>
			<div class="sgc-crm-stat-body">
				<div class="sgc-crm-stat-num" id="stat-subscribed">—</div>
				<div class="sgc-crm-stat-label"><?php esc_html_e( 'Subscribed', 'sgc-smart-crm' ); ?></div>
			</div>
		</div>
		<div class="sgc-crm-stat-card">
			<div class="sgc-crm-stat-icon">📨</div>
			<div class="sgc-crm-stat-body">
				<div class="sgc-crm-stat-num" id="stat-campaigns">—</div>
				<div class="sgc-crm-stat-label"><?php esc_html_e( 'Campaigns Sent', 'sgc-smart-crm' ); ?></div>
			</div>
		</div>
		<div class="sgc-crm-stat-card">
			<div class="sgc-crm-stat-icon">📬</div>
			<div class="sgc-crm-stat-body">
				<div class="sgc-crm-stat-num" id="stat-emails">—</div>
				<div class="sgc-crm-stat-label"><?php esc_html_e( 'Emails Sent', 'sgc-smart-crm' ); ?></div>
			</div>
		</div>
		<div class="sgc-crm-stat-card">
			<div class="sgc-crm-stat-icon">👁️</div>
			<div class="sgc-crm-stat-body">
				<div class="sgc-crm-stat-num" id="stat-opens">—</div>
				<div class="sgc-crm-stat-label"><?php esc_html_e( 'Total Opens', 'sgc-smart-crm' ); ?></div>
			</div>
		</div>
		<div class="sgc-crm-stat-card">
			<div class="sgc-crm-stat-icon">🖱️</div>
			<div class="sgc-crm-stat-body">
				<div class="sgc-crm-stat-num" id="stat-clicks">—</div>
				<div class="sgc-crm-stat-label"><?php esc_html_e( 'Total Clicks', 'sgc-smart-crm' ); ?></div>
			</div>
		</div>
	</div>

	<!-- Quick Actions -->
	<div class="sgc-crm-row">
		<div class="sgc-crm-box" style="flex:1">
			<h3><?php esc_html_e( '⚡ Quick Actions', 'sgc-smart-crm' ); ?></h3>
			<div class="sgc-crm-quick-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=sgc-smart-crm&tab=contacts' ) ); ?>" class="sgc-crm-quick-btn">
					📥 <?php esc_html_e( 'Import Contacts', 'sgc-smart-crm' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=sgc-smart-crm&tab=campaigns' ) ); ?>" class="sgc-crm-quick-btn">
					✍️ <?php esc_html_e( 'New Campaign', 'sgc-smart-crm' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=sgc-smart-crm&tab=contacts' ) ); ?>&action=sync_woo" class="sgc-crm-quick-btn" id="btn-sync-woo">
					🔄 <?php esc_html_e( 'Sync WooCommerce', 'sgc-smart-crm' ); ?>
				</a>
				<button class="sgc-crm-quick-btn" id="btn-trigger-promo">
					📢 <?php esc_html_e( 'Send Weekly Promo Now', 'sgc-smart-crm' ); ?>
				</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=sgc-smart-crm&tab=contacts' ) ); ?>&action=ai_segment" class="sgc-crm-quick-btn" id="btn-ai-segment">
					🤖 <?php esc_html_e( 'AI Segment Contacts', 'sgc-smart-crm' ); ?>
				</a>
			</div>
		</div>

		<!-- AI + Automation Status -->
		<div class="sgc-crm-box" style="flex:1">
			<h3><?php esc_html_e( '🤖 AI & Automation Status', 'sgc-smart-crm' ); ?></h3>
			<table class="sgc-crm-status-table">
				<tr>
					<td><?php esc_html_e( 'AI Provider', 'sgc-smart-crm' ); ?></td>
					<td><strong><?php echo esc_html( $ai_label ); ?></strong>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=sgc-smart-crm&tab=settings' ) ); ?>" class="sgc-crm-link"><?php esc_html_e( 'Change', 'sgc-smart-crm' ); ?></a>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( '⚡ Instant Quote Reply', 'sgc-smart-crm' ); ?></td>
					<td><?php echo get_option( 'sgc_crm_auto_instant_reply', '1' ) ? '<span class="sgc-badge green">ON</span>' : '<span class="sgc-badge red">OFF</span>'; ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Order Follow-ups', 'sgc-smart-crm' ); ?></td>
					<td><?php echo get_option( 'sgc_crm_auto_order_followup', '1' ) ? '<span class="sgc-badge green">ON</span>' : '<span class="sgc-badge red">OFF</span>'; ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Inquiry Follow-ups', 'sgc-smart-crm' ); ?></td>
					<td><?php echo get_option( 'sgc_crm_auto_inquiry_followup', '1' ) ? '<span class="sgc-badge green">ON</span>' : '<span class="sgc-badge red">OFF</span>'; ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Welcome Emails', 'sgc-smart-crm' ); ?></td>
					<td><?php echo get_option( 'sgc_crm_auto_welcome', '1' ) ? '<span class="sgc-badge green">ON</span>' : '<span class="sgc-badge red">OFF</span>'; ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Weekly Promo', 'sgc-smart-crm' ); ?></td>
					<td><?php echo get_option( 'sgc_crm_weekly_ai_enabled', '1' ) ? '<span class="sgc-badge green">ON</span>' : '<span class="sgc-badge red">OFF</span>'; ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Last Weekly Promo', 'sgc-smart-crm' ); ?></td>
					<td><?php echo $last_promo ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_promo ) ) ) : '<em>' . esc_html__( 'Not sent yet', 'sgc-smart-crm' ) . '</em>'; ?></td>
				</tr>
			</table>
		</div>
	</div>

	<!-- Segments -->
	<div class="sgc-crm-box">
		<h3><?php esc_html_e( '🎯 Contact Segments', 'sgc-smart-crm' ); ?></h3>
		<div id="sgc-crm-segments"><em><?php esc_html_e( 'Loading…', 'sgc-smart-crm' ); ?></em></div>
	</div>

	<div id="sgc-crm-dashboard-notice" class="sgc-crm-notice" style="display:none"></div>
</div>

<script>
jQuery(function($){
	// Load stats
	$.post(ajaxurl, {action:'sgc_crm_get_stats', nonce:SGC_CRM.nonce}, function(r){
		if(!r.success) return;
		var d = r.data;
		$('#stat-contacts').text(d.total_contacts.toLocaleString());
		$('#stat-subscribed').text(d.subscribed.toLocaleString());
		$('#stat-campaigns').text(d.sent_campaigns.toLocaleString());
		$('#stat-emails').text(d.total_sent_emails.toLocaleString());
		$('#stat-opens').text(d.total_opens.toLocaleString());
		$('#stat-clicks').text(d.total_clicks.toLocaleString());

		if(d.segments && d.segments.length){
			var html = '<div class="sgc-crm-segments-grid">';
			d.segments.forEach(function(s){
				html += '<div class="sgc-segment-badge"><span class="sgc-segment-name">'+s.segment+'</span><span class="sgc-segment-count">'+s.cnt+'</span></div>';
			});
			html += '</div>';
			$('#sgc-crm-segments').html(html);
		} else {
			$('#sgc-crm-segments').html('<em><?php esc_html_e( "No contacts segmented yet. Run AI Segment from contacts tab.", "sgc-smart-crm" ); ?></em>');
		}
	});

	// Weekly promo trigger
	$('#btn-trigger-promo').on('click', function(){
		if(!confirm(SGC_CRM.confirm_send)) return;
		var $btn = $(this).text('⏳ Scheduling…').prop('disabled',true);
		$.post(ajaxurl, {action:'sgc_crm_trigger_weekly_promo_now', nonce:SGC_CRM.nonce}, function(r){
			$btn.prop('disabled',false).text('📢 <?php esc_html_e( "Send Weekly Promo Now", "sgc-smart-crm" ); ?>');
			var $n = $('#sgc-crm-dashboard-notice');
			$n.text(r.data).removeClass('error').addClass(r.success ? 'success' : 'error').show();
			setTimeout(function(){ $n.fadeOut(); }, 4000);
		});
	});
});
</script>
