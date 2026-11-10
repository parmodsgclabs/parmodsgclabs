<?php
/**
 * SGC Smart CRM Pro — Settings Tab
 *
 * @package SGC_Smart_CRM
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$providers       = SGC_CRM_AI::providers();
$current_provider = get_option( 'sgc_crm_ai_provider', 'deepseek' );
$from_name       = get_option( 'sgc_crm_from_name',  'SGC LABS® Glassware' );
$from_email      = get_option( 'sgc_crm_from_email', 'sales@labsglass.com' );
$reply_to        = get_option( 'sgc_crm_reply_to',   '' );
$footer_text     = get_option( 'sgc_crm_footer_text','SGC LABS® | Scientific Glassware | Ambala, India | labsglass.com' );
$batch_size      = (int) get_option( 'sgc_crm_batch_size', 50 );
$weekly_segment  = get_option( 'sgc_crm_weekly_segment', 'all' );
?>
<div class="sgc-crm-settings-page">

	<div id="settings-notice" class="sgc-crm-notice" style="display:none"></div>

	<!-- ================================================================
	     AI PROVIDER SETTINGS
	     ================================================================ -->
	<div class="sgc-crm-box">
		<h3>🤖 <?php esc_html_e( 'AI Provider Settings', 'sgc-smart-crm' ); ?></h3>
		<p style="color:#666;margin-top:0"><?php esc_html_e( 'Configure which AI provider generates campaigns and automation emails. Fallback: if no CRM key is set, SGC Manager Pro\'s AI is used.', 'sgc-smart-crm' ); ?></p>

		<!-- Provider Tabs -->
		<div class="sgc-crm-provider-tabs">
			<?php foreach ( $providers as $slug => $provider ) : ?>
			<button class="sgc-provider-tab <?php echo $current_provider === $slug ? 'active' : ''; ?>"
			        data-provider="<?php echo esc_attr( $slug ); ?>">
				<?php echo esc_html( $provider['label'] ); ?>
				<?php if ( $current_provider === $slug ) : ?><span class="sgc-active-dot">●</span><?php endif; ?>
			</button>
			<?php endforeach; ?>
		</div>

		<!-- Provider Panels -->
		<?php foreach ( $providers as $slug => $provider ) :
			$current_model = get_option( 'sgc_crm_ai_model_' . $slug, $provider['default_model'] );
			$has_key       = ! empty( SGC_CRM_AI::get_key( $slug ) );
		?>
		<div class="sgc-provider-panel <?php echo $current_provider === $slug ? 'active' : ''; ?>"
		     id="panel-<?php echo esc_attr( $slug ); ?>">

			<div class="sgc-provider-panel-inner">
				<div class="sgc-crm-form-row">
					<label><?php esc_html_e( 'API Key', 'sgc-smart-crm' ); ?>
						<a href="<?php echo esc_url( $provider['docs_url'] ); ?>" target="_blank" rel="noopener noreferrer"
						   style="font-size:11px;margin-left:6px;color:#1a1a2e">
							<?php esc_html_e( 'Get Key ↗', 'sgc-smart-crm' ); ?>
						</a>
					</label>
					<div style="display:flex;gap:8px;align-items:center">
						<input type="password" id="ai-key-<?php echo esc_attr( $slug ); ?>"
						       class="sgc-crm-input"
						       placeholder="<?php echo esc_attr( $provider['placeholder'] ); ?>"
						       autocomplete="new-password"
						       style="flex:1">
						<?php if ( $has_key ) : ?>
							<span class="sgc-badge green"><?php esc_html_e( 'Key saved', 'sgc-smart-crm' ); ?> ✓</span>
						<?php else : ?>
							<span class="sgc-badge grey"><?php esc_html_e( 'No key', 'sgc-smart-crm' ); ?></span>
						<?php endif; ?>
					</div>
					<small style="color:#888"><?php esc_html_e( 'Keys are encrypted with AES-256-CBC before storage.', 'sgc-smart-crm' ); ?></small>
				</div>

				<div class="sgc-crm-form-row">
					<label><?php esc_html_e( 'Model', 'sgc-smart-crm' ); ?></label>
					<select id="ai-model-<?php echo esc_attr( $slug ); ?>" class="sgc-crm-select" style="width:300px">
						<?php foreach ( $provider['models'] as $model_id => $model_name ) : ?>
							<option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $current_model, $model_id ); ?>>
								<?php echo esc_html( $model_name ); ?> (<?php echo esc_html( $model_id ); ?>)
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div style="display:flex;gap:10px;align-items:center;margin-top:8px">
					<?php if ( $current_provider !== $slug ) : ?>
					<button class="sgc-crm-btn secondary set-active-provider" data-provider="<?php echo esc_attr( $slug ); ?>">
						<?php esc_html_e( '⚡ Set as Active Provider', 'sgc-smart-crm' ); ?>
					</button>
					<?php else : ?>
					<span class="sgc-badge green"><?php esc_html_e( '✓ Currently Active', 'sgc-smart-crm' ); ?></span>
					<?php endif; ?>

					<button class="sgc-crm-btn secondary save-provider-settings" data-provider="<?php echo esc_attr( $slug ); ?>">
						<?php esc_html_e( '💾 Save Settings', 'sgc-smart-crm' ); ?>
					</button>

					<button class="sgc-crm-btn secondary test-connection" data-provider="<?php echo esc_attr( $slug ); ?>">
						<?php esc_html_e( '🔌 Test Connection', 'sgc-smart-crm' ); ?>
					</button>

					<span class="test-result-<?php echo esc_attr( $slug ); ?>" style="font-size:13px"></span>
				</div>
			</div>
		</div>
		<?php endforeach; ?>

		<!-- Fallback note -->
		<div class="sgc-crm-info-box" style="margin-top:16px">
			<strong><?php esc_html_e( 'Fallback Chain:', 'sgc-smart-crm' ); ?></strong>
			<?php esc_html_e( 'CRM API key → SGC Manager Pro sgclabs_ai_call() → WP_Error', 'sgc-smart-crm' ); ?>
		</div>
	</div>

	<!-- ================================================================
	     EMAIL SETTINGS
	     ================================================================ -->
	<div class="sgc-crm-box">
		<h3>📧 <?php esc_html_e( 'Email Settings', 'sgc-smart-crm' ); ?></h3>

		<div class="sgc-crm-form-row sgc-crm-form-2col">
			<div>
				<label><?php esc_html_e( 'From Name', 'sgc-smart-crm' ); ?></label>
				<input type="text" id="setting-from-name" class="sgc-crm-input" value="<?php echo esc_attr( $from_name ); ?>">
			</div>
			<div>
				<label><?php esc_html_e( 'From Email', 'sgc-smart-crm' ); ?></label>
				<input type="email" id="setting-from-email" class="sgc-crm-input" value="<?php echo esc_attr( $from_email ); ?>">
			</div>
		</div>

		<div class="sgc-crm-form-row">
			<label><?php esc_html_e( 'Reply-To Email', 'sgc-smart-crm' ); ?></label>
			<input type="email" id="setting-reply-to" class="sgc-crm-input" value="<?php echo esc_attr( $reply_to ); ?>" style="width:300px">
		</div>

		<div class="sgc-crm-form-row">
			<label><?php esc_html_e( 'Email Footer Text', 'sgc-smart-crm' ); ?></label>
			<input type="text" id="setting-footer-text" class="sgc-crm-input" value="<?php echo esc_attr( $footer_text ); ?>">
		</div>

		<div class="sgc-crm-form-row sgc-crm-form-2col">
			<div>
				<label><?php esc_html_e( 'Send Batch Size', 'sgc-smart-crm' ); ?></label>
				<input type="number" id="setting-batch-size" class="sgc-crm-input" value="<?php echo esc_attr( $batch_size ); ?>" min="5" max="200" style="width:100px">
				<small style="color:#888;display:block"><?php esc_html_e( 'Emails per 5-min cron batch (5–200)', 'sgc-smart-crm' ); ?></small>
			</div>
			<div>
				<label><?php esc_html_e( 'Weekly Promo Segment', 'sgc-smart-crm' ); ?></label>
				<select id="setting-weekly-segment" class="sgc-crm-select">
					<option value="all"       <?php selected( $weekly_segment, 'all' ); ?>><?php esc_html_e( 'All Subscribed', 'sgc-smart-crm' ); ?></option>
					<option value="active_buyer" <?php selected( $weekly_segment, 'active_buyer' ); ?>><?php esc_html_e( 'Active Buyers', 'sgc-smart-crm' ); ?></option>
					<option value="repeat_buyer" <?php selected( $weekly_segment, 'repeat_buyer' ); ?>><?php esc_html_e( 'Repeat Buyers', 'sgc-smart-crm' ); ?></option>
					<option value="international" <?php selected( $weekly_segment, 'international' ); ?>><?php esc_html_e( 'International', 'sgc-smart-crm' ); ?></option>
				</select>
			</div>
		</div>

		<div class="sgc-crm-form-row">
			<label><?php esc_html_e( 'Tracking', 'sgc-smart-crm' ); ?></label>
			<div style="display:flex;gap:24px">
				<label>
					<input type="checkbox" id="setting-track-opens" <?php checked( get_option( 'sgc_crm_track_opens', '1' ), '1' ); ?>>
					<?php esc_html_e( 'Track email opens (1×1 pixel)', 'sgc-smart-crm' ); ?>
				</label>
				<label>
					<input type="checkbox" id="setting-track-clicks" <?php checked( get_option( 'sgc_crm_track_clicks', '1' ), '1' ); ?>>
					<?php esc_html_e( 'Track link clicks (redirect)', 'sgc-smart-crm' ); ?>
				</label>
			</div>
		</div>

		<button class="sgc-crm-btn primary" id="btn-save-email-settings">
			💾 <?php esc_html_e( 'Save Email Settings', 'sgc-smart-crm' ); ?>
		</button>
	</div>

	<!-- ================================================================
	     SYSTEM INFO
	     ================================================================ -->
	<div class="sgc-crm-box">
		<h3>ℹ️ <?php esc_html_e( 'System Info', 'sgc-smart-crm' ); ?></h3>
		<table class="sgc-crm-status-table">
			<tr><td><?php esc_html_e( 'Plugin Version', 'sgc-smart-crm' ); ?></td><td><?php echo esc_html( SGC_CRM_VERSION ); ?></td></tr>
			<tr><td><?php esc_html_e( 'WordPress', 'sgc-smart-crm' ); ?></td><td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
			<tr><td><?php esc_html_e( 'PHP', 'sgc-smart-crm' ); ?></td><td><?php echo esc_html( PHP_VERSION ); ?></td></tr>
			<tr><td><?php esc_html_e( 'WooCommerce', 'sgc-smart-crm' ); ?></td><td><?php echo class_exists( 'WooCommerce' ) ? '<span class="sgc-badge green">Active</span>' : '<span class="sgc-badge grey">Not Active</span>'; ?></td></tr>
			<tr><td><?php esc_html_e( 'SGC Manager Pro', 'sgc-smart-crm' ); ?></td><td><?php echo function_exists( 'sgclabs_ai_call' ) ? '<span class="sgc-badge green">Active (AI fallback available)</span>' : '<span class="sgc-badge grey">Not Active</span>'; ?></td></tr>
			<tr><td><?php esc_html_e( 'OpenSSL (encryption)', 'sgc-smart-crm' ); ?></td><td><?php echo function_exists( 'openssl_encrypt' ) ? '<span class="sgc-badge green">Available</span>' : '<span class="sgc-badge red">Not Available</span>'; ?></td></tr>
			<tr><td><?php esc_html_e( 'Queue Next Run', 'sgc-smart-crm' ); ?></td><td><?php $ts = wp_next_scheduled( 'sgc_crm_process_queue' ); echo $ts ? esc_html( date_i18n( 'M j, Y H:i:s', $ts ) ) : '<em>Not scheduled</em>'; ?></td></tr>
			<tr><td><?php esc_html_e( 'Weekly Promo Next Run', 'sgc-smart-crm' ); ?></td><td><?php $ts2 = wp_next_scheduled( 'sgc_crm_weekly_broadcast' ); echo $ts2 ? esc_html( date_i18n( 'M j, Y H:i:s', $ts2 ) ) : '<em>Not scheduled</em>'; ?></td></tr>
		</table>
	</div>

	<!-- BACKUP STATUS -->
	<div class="sgc-crm-box" style="margin-top:20px">
		<h3 style="margin:0 0 14px">&#x1F4BE; <?php esc_html_e( 'Auto-Backup Status', 'sgc-smart-crm' ); ?></h3>
		<?php
		$last_backup       = get_option( 'sgc_crm_last_backup', '' );
		$last_backup_count = get_option( 'sgc_crm_last_backup_count', 0 );
		$last_backup_file  = get_option( 'sgc_crm_last_backup_file', '' );
		$next_backup       = wp_next_scheduled( 'sgc_crm_weekly_backup' );
		$upload_dir        = wp_upload_dir();
		$backup_dir_path   = $upload_dir['basedir'] . '/sgc-crm-backups/';
		$backup_files      = glob( $backup_dir_path . 'backup-*.csv' ) ?: array();
		?>
		<table class="sgc-crm-status-table">
			<tr>
				<td><?php esc_html_e( 'Last Backup', 'sgc-smart-crm' ); ?></td>
				<td>
					<?php if ( $last_backup ) : ?>
						<span class="sgc-badge green"><?php echo esc_html( date_i18n( 'M j, Y H:i', strtotime( $last_backup ) ) ); ?></span>
						&nbsp;<small style="color:#888"><?php echo esc_html( number_format( (int) $last_backup_count ) ); ?> contacts &middot; <?php echo esc_html( $last_backup_file ); ?></small>
					<?php else : ?>
						<em style="color:#aaa"><?php esc_html_e( 'Not run yet — runs every Monday 2:00 AM', 'sgc-smart-crm' ); ?></em>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Next Backup', 'sgc-smart-crm' ); ?></td>
				<td><?php echo $next_backup ? esc_html( date_i18n( 'M j, Y H:i:s', $next_backup ) ) : '<em>' . esc_html__( 'Not scheduled', 'sgc-smart-crm' ) . '</em>'; ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Stored Backups', 'sgc-smart-crm' ); ?></td>
				<td><?php echo count( $backup_files ); ?> / 8 &nbsp;<small style="color:#aaa">(<?php esc_html_e( 'auto-rotated', 'sgc-smart-crm' ); ?>)</small></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Backup Location', 'sgc-smart-crm' ); ?></td>
				<td><code style="font-size:11px">/wp-content/uploads/sgc-crm-backups/</code></td>
			</tr>
		</table>
		<div style="margin-top:14px">
			<button class="sgc-crm-btn secondary" id="btn-run-backup-now">&#x25B6; <?php esc_html_e( 'Run Backup Now', 'sgc-smart-crm' ); ?></button>
		</div>
		<div id="backup-notice" class="sgc-crm-notice" style="display:none;margin-top:10px"></div>
	</div>

	<!-- DANGER ZONE -->
	<div class="sgc-crm-box" style="margin-top:20px;border:1px solid #f5c6cb">
		<h3 style="margin:0 0 6px;color:#721c24">&#x26A0;&#xFE0F; <?php esc_html_e( 'Danger Zone', 'sgc-smart-crm' ); ?></h3>
		<p style="font-size:13px;color:#856404;margin:0 0 16px"><?php esc_html_e( 'These settings affect data permanently. Read carefully before enabling.', 'sgc-smart-crm' ); ?></p>
		<div style="background:#fff5f5;border:1px solid #f5c6cb;border-radius:6px;padding:16px">
			<div style="display:flex;align-items:flex-start;gap:14px">
				<div style="flex:1">
					<strong style="font-size:14px"><?php esc_html_e( 'Delete all data when plugin is uninstalled', 'sgc-smart-crm' ); ?></strong>
					<p style="font-size:12px;color:#666;margin:4px 0 0"><?php esc_html_e( 'If ON: deleting this plugin from WP Admin will permanently drop all CRM tables (contacts, campaigns, queue). A CSV backup is auto-saved before deletion. Default: OFF — data stays safe even after plugin removal.', 'sgc-smart-crm' ); ?></p>
				</div>
				<label class="sgc-crm-toggle" style="flex-shrink:0;margin-top:2px">
					<input type="checkbox" id="toggle-delete-on-uninstall"
						<?php checked( get_option( 'sgc_crm_delete_data_on_uninstall', '0' ), '1' ); ?>
						data-option="sgc_crm_delete_data_on_uninstall">
					<span class="sgc-crm-toggle-slider"></span>
				</label>
			</div>
			<div id="uninstall-warning" style="display:<?php echo get_option( 'sgc_crm_delete_data_on_uninstall', '0' ) === '1' ? 'block' : 'none'; ?>;margin-top:10px;padding:8px 12px;background:#f8d7da;border-radius:4px;font-size:12px;color:#721c24">
				&#x26A0; <?php esc_html_e( 'WARNING: Plugin delete will permanently erase all contacts & campaigns. Ensure backup is current before proceeding.', 'sgc-smart-crm' ); ?>
			</div>
		</div>
	</div>

</div>

<script>
jQuery(function($){
	function showNotice(m,t){ var $n=$('#settings-notice'); $n.text(m).removeClass('success error').addClass(t||'success').show(); setTimeout(function(){ $n.fadeOut(); },5000); }
	function showBackupNotice(m,t){ var $n=$('#backup-notice'); $n.text(m).removeClass('success error').addClass(t||'success').show(); setTimeout(function(){ $n.fadeOut(); },6000); }

	// Provider tab switching
	$('.sgc-provider-tab').on('click',function(){
		$('.sgc-provider-tab,.sgc-provider-panel').removeClass('active');
		var p=$(this).data('provider');
		$(this).addClass('active');
		$('#panel-'+p).addClass('active');
	});

	// Set active provider — save key+model then set active
	$('.set-active-provider').on('click',function(){
		var provider=$(this).data('provider');
		var key=$('#ai-key-'+provider).val();
		var model=$('#ai-model-'+provider).val();
		var $btn=$(this).text('Saving…').prop('disabled',true);
		$.post(ajaxurl,{
			action:'sgc_crm_save_ai_settings', nonce:SGC_CRM.nonce,
			provider:provider, api_key:key, model:model, set_active:'1'
		},function(r){
			$btn.prop('disabled',false);
			if(r.success){ showNotice(provider+' set as active provider.'); setTimeout(function(){ location.reload(); },1000); }
			else showNotice(r.data,'error');
		});
	});

	// Save provider settings
	$('.save-provider-settings').on('click',function(){
		var provider=$(this).data('provider');
		var key=$('#ai-key-'+provider).val();
		var model=$('#ai-model-'+provider).val();
		var $btn=$(this).text('Saving…').prop('disabled',true);
		$.post(ajaxurl,{
			action:'sgc_crm_save_ai_settings', nonce:SGC_CRM.nonce,
			provider:provider, api_key:key, model:model, set_active:'0'
		},function(r){
			$btn.text('<?php esc_html_e("💾 Save Settings","sgc-smart-crm"); ?>').prop('disabled',false);
			var msg = (r.success && r.data && r.data.message) ? r.data.message : (r.data||'Error');
			showNotice(msg, r.success?'success':'error');
			if(r.success){
				if(key){ $('#ai-key-'+provider).val(''); }
				if(r.data && r.data.has_key){
					$('#panel-'+provider+' .sgc-badge.grey').replaceWith('<span class="sgc-badge green"><?php esc_html_e("Key saved","sgc-smart-crm"); ?> ✓</span>');
				}
			}
		}).fail(function(){
			$btn.text('<?php esc_html_e("💾 Save Settings","sgc-smart-crm"); ?>').prop('disabled',false);
			showNotice('AJAX failed — check browser console.','error');
		});
	});

	// Test connection
	$('.test-connection').on('click',function(){
		var provider=$(this).data('provider');
		var $res=$('.test-result-'+provider).text('⏳ <?php esc_html_e("Testing…","sgc-smart-crm"); ?>');
		$.post(ajaxurl,{action:'sgc_crm_test_ai_connection',nonce:SGC_CRM.nonce,provider:provider},function(r){
			$res.text(r.success?'✅ Connected!':'❌ '+r.data).css('color',r.success?'green':'red');
		});
	});

	// Save email settings — uses dedicated AJAX handler
	$('#btn-save-email-settings').on('click',function(){
		var $btn=$(this).text('Saving…').prop('disabled',true);
		$.post(ajaxurl,{
			action:'sgc_crm_save_email_settings',
			nonce:SGC_CRM.nonce,
			sgc_crm_from_name:     $('#setting-from-name').val(),
			sgc_crm_from_email:    $('#setting-from-email').val(),
			sgc_crm_reply_to:      $('#setting-reply-to').val(),
			sgc_crm_footer_text:   $('#setting-footer-text').val(),
			sgc_crm_batch_size:    $('#setting-batch-size').val(),
			sgc_crm_weekly_segment:$('#setting-weekly-segment').val(),
			sgc_crm_track_opens:   $('#setting-track-opens').is(':checked')?'1':'0',
			sgc_crm_track_clicks:  $('#setting-track-clicks').is(':checked')?'1':'0',
		},function(r){
			$btn.text('💾 Save Email Settings').prop('disabled',false);
			showNotice(r.success ? r.data.message : r.data, r.success ? 'success' : 'error');
		}).fail(function(){
			$btn.text('💾 Save Email Settings').prop('disabled',false);
			showNotice('Save failed — check console.','error');
		});
	});

	// ── Run Backup Now ───────────────────────────────────────────────────
	$('#btn-run-backup-now').on('click', function(){
		var $b = $(this).prop('disabled', true).text('⏳ Running backup…');
		$.post(ajaxurl, {action: 'sgc_crm_run_backup_now', nonce: SGC_CRM.nonce}, function(r){
			$b.prop('disabled', false).html('&#x25B6; <?php esc_html_e("Run Backup Now","sgc-smart-crm"); ?>');
			if(r.success){ showBackupNotice(r.data.message, 'success'); setTimeout(function(){ location.reload(); }, 2500); }
			else showBackupNotice(r.data || 'Backup failed.', 'error');
		}).fail(function(){ $b.prop('disabled', false); showBackupNotice('Server error.', 'error'); });
	});

	// ── Danger Zone toggle ───────────────────────────────────────────────
	$('#toggle-delete-on-uninstall').on('change', function(){
		var checked = this.checked;
		if(checked){
			if(!confirm('<?php esc_html_e("WARNING: If you enable this, deleting the plugin will permanently erase ALL contacts and campaigns. A CSV backup will be auto-saved first. Are you sure?","sgc-smart-crm"); ?>')){
				$(this).prop('checked', false); return;
			}
		}
		var val = checked ? '1' : '0';
		$.post(ajaxurl, {action:'sgc_crm_save_option', nonce:SGC_CRM.nonce, option_name:'sgc_crm_delete_data_on_uninstall', option_value:val}, function(r){
			if(r.success){
				$('#uninstall-warning').toggle(checked);
				showNotice(
					checked ? '<?php esc_html_e("Enabled — data will be deleted on uninstall.","sgc-smart-crm"); ?>'
					        : '<?php esc_html_e("Disabled — data is safe on uninstall.","sgc-smart-crm"); ?>',
					checked ? 'error' : 'success'
				);
			}
		});
	});
});
</script>
