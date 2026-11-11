<?php
defined( 'ABSPATH' ) || exit;
$ai_provider = get_option( 'sgc_pm_ai_provider', 'deepseek' );
$providers   = SGC_PM_AI_Engine::PROVIDERS;
$ai_label    = $providers[ $ai_provider ]['label'] ?? $ai_provider;
$auto_on     = get_option( 'sgc_pm_automation_enabled', '1' );
$comp_on     = get_option( 'sgc_pm_competitor_enabled', '1' );
$ai_on       = get_option( 'sgc_pm_ai_enabled', '1' );
?>
<div class="sgc-pm-dashboard">

<!-- KPI Cards -->
<div class="sgc-pm-kpi-grid" id="pm-kpi-grid">
	<?php
	$kpis = [
		[ 'id' => 'kpi-total',    'label' => 'Total Products',   'icon' => '📦', 'color' => 'blue'   ],
		[ 'id' => 'kpi-pub',      'label' => 'Published',        'icon' => '✅', 'color' => 'green'  ],
		[ 'id' => 'kpi-draft',    'label' => 'Drafts',           'icon' => '📝', 'color' => 'gray'   ],
		[ 'id' => 'kpi-nostock',  'label' => 'Out of Stock',     'icon' => '⚠️', 'color' => 'red'    ],
		[ 'id' => 'kpi-nosku',    'label' => 'Missing SKU',      'icon' => '🔢', 'color' => 'amber'  ],
		[ 'id' => 'kpi-noimg',    'label' => 'No Image',         'icon' => '🖼', 'color' => 'coral'  ],
	];
	foreach ( $kpis as $k ) :
	?>
	<div class="sgc-pm-kpi-card sgc-pm-kpi-<?php echo esc_attr( $k['color'] ); ?>">
		<div class="sgc-pm-kpi-icon"><?php echo $k['icon']; ?></div>
		<div class="sgc-pm-kpi-num" id="<?php echo esc_attr( $k['id'] ); ?>">—</div>
		<div class="sgc-pm-kpi-label"><?php echo esc_html( $k['label'] ); ?></div>
	</div>
	<?php endforeach; ?>
</div>

<!-- Row: Status + AI status -->
<div class="sgc-pm-row" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px">

	<!-- AI Engine Status -->
	<div class="sgc-pm-box">
		<h3>🤖 AI Engine</h3>
		<table class="sgc-pm-status-tbl">
			<tr><td>Provider</td><td><strong><?php echo esc_html( $ai_label ); ?></strong></td></tr>
			<tr><td>Status</td><td><?php echo $ai_on ? '<span class="sgc-pm-badge sgc-pm-badge-green">Active</span>' : '<span class="sgc-pm-badge sgc-pm-badge-red">Paused</span>'; ?></td></tr>
			<tr><td>API Key</td><td><?php $k = get_option( 'sgc_pm_ai_key_' . $ai_provider, '' ); echo $k ? '<span class="sgc-pm-badge sgc-pm-badge-green">Set</span>' : '<span class="sgc-pm-badge sgc-pm-badge-red">Not Set</span>'; ?></td></tr>
		</table>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=sgc-product-manager&tab=settings' ) ); ?>" class="sgc-pm-btn secondary" style="margin-top:10px;display:inline-block;font-size:11px">Configure AI →</a>
	</div>

	<!-- Automation Status -->
	<div class="sgc-pm-box">
		<h3>⚡ Automations</h3>
		<?php
		global $wpdb;
		$rules_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sgcpm_automations WHERE status='active'" ); // phpcs:ignore
		$next_run     = wp_next_scheduled( 'sgc_pm_automation_run' );
		?>
		<table class="sgc-pm-status-tbl">
			<tr><td>Status</td><td><?php echo $auto_on ? '<span class="sgc-pm-badge sgc-pm-badge-green">Running</span>' : '<span class="sgc-pm-badge sgc-pm-badge-red">Paused</span>'; ?></td></tr>
			<tr><td>Active Rules</td><td><strong><?php echo $rules_count; ?></strong></td></tr>
			<tr><td>Next Run</td><td style="font-size:11px"><?php echo $next_run ? esc_html( date_i18n( 'M j H:i', $next_run ) ) : 'Not scheduled'; ?></td></tr>
		</table>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=sgc-product-manager&tab=automations' ) ); ?>" class="sgc-pm-btn secondary" style="margin-top:10px;display:inline-block;font-size:11px">Manage Rules →</a>
	</div>

	<!-- Competitor Intel Status -->
	<div class="sgc-pm-box">
		<h3>🔍 Competitor Intel</h3>
		<?php
		$comp_total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sgcpm_competitors WHERE status='active'" ); // phpcs:ignore
		$last_scan   = $wpdb->get_var( "SELECT MAX(last_scan) FROM {$wpdb->prefix}sgcpm_competitors" ); // phpcs:ignore
		$next_scan   = wp_next_scheduled( 'sgc_pm_competitor_scan' );
		?>
		<table class="sgc-pm-status-tbl">
			<tr><td>Status</td><td><?php echo $comp_on ? '<span class="sgc-pm-badge sgc-pm-badge-green">Active</span>' : '<span class="sgc-pm-badge sgc-pm-badge-red">Paused</span>'; ?></td></tr>
			<tr><td>Competitors</td><td><strong><?php echo $comp_total; ?> / 25</strong></td></tr>
			<tr><td>Last Scan</td><td style="font-size:11px"><?php echo $last_scan ? esc_html( date_i18n( 'M j H:i', strtotime( $last_scan ) ) ) : 'Never'; ?></td></tr>
		</table>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=sgc-product-manager&tab=competitor' ) ); ?>" class="sgc-pm-btn secondary" style="margin-top:10px;display:inline-block;font-size:11px">View Intel →</a>
	</div>
</div>

<!-- Quick Actions -->
<div class="sgc-pm-box">
	<h3>⚡ Quick Actions</h3>
	<div style="display:flex;gap:10px;flex-wrap:wrap">
		<button class="sgc-pm-btn primary" onclick="location.href='<?php echo esc_url( admin_url( 'admin.php?page=sgc-product-manager&tab=ai' ) ); ?>'">🤖 Bulk AI SEO</button>
		<button class="sgc-pm-btn secondary" onclick="location.href='<?php echo esc_url( admin_url( 'admin.php?page=sgc-product-manager&tab=competitor' ) ); ?>'">🔍 Scan Competitors</button>
		<button class="sgc-pm-btn secondary" onclick="location.href='<?php echo esc_url( admin_url( 'admin.php?page=sgc-product-manager&tab=import' ) ); ?>'">📥 Import Products</button>
		<button class="sgc-pm-btn secondary" onclick="location.href='<?php echo esc_url( admin_url( 'admin.php?page=sgc-product-manager&tab=b2b' ) ); ?>'">📄 Export B2B Price List</button>
		<button class="sgc-pm-btn secondary" onclick="location.href='<?php echo esc_url( admin_url( 'admin.php?page=sgc-product-manager&tab=products' ) ); ?>&filter=no_sku'">🔢 Fix Missing SKUs</button>
		<button class="sgc-pm-btn secondary" onclick="location.href='<?php echo esc_url( admin_url( 'admin.php?page=sgc-product-manager&tab=products' ) ); ?>&filter=outofstock'">📦 Out of Stock</button>
	</div>
</div>

<!-- Recent Activity -->
<div class="sgc-pm-box">
	<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
		<h3 style="margin:0">📋 Recent Activity</h3>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=sgc-product-manager&tab=logs' ) ); ?>" style="font-size:12px;color:#185FA5">View all →</a>
	</div>
	<div id="pm-recent-logs">
		<?php
		$logs = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sgcpm_logs ORDER BY created_at DESC LIMIT 8" ); // phpcs:ignore
		if ( $logs ) :
			foreach ( $logs as $log ) :
				$icons = [ 'ai_generate' => '🤖', 'ai_bulk_seo' => '🤖', 'bulk_delete' => '🗑', 'import' => '📥', 'competitor_scan' => '🔍', 'automation_run' => '⚡' ];
				$icon  = $icons[ $log->action ] ?? '📋';
			?>
			<div style="display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid var(--color-border-tertiary, #eee);font-size:12px">
				<span><?php echo $icon; ?></span>
				<span style="flex:1"><?php echo esc_html( $log->action ); ?><?php echo $log->details ? ' — ' . esc_html( substr( $log->details, 0, 60 ) ) : ''; ?></span>
				<span style="color:#aaa;white-space:nowrap"><?php echo esc_html( date_i18n( 'M j H:i', strtotime( $log->created_at ) ) ); ?></span>
			</div>
			<?php endforeach;
		else : ?>
			<p style="color:#aaa;font-size:13px">No activity yet. Start by scanning competitors or running bulk AI SEO.</p>
		<?php endif; ?>
	</div>
</div>

</div>

<script>
jQuery(function($){
	// Load KPI stats
	$.post(SGCPM.ajaxUrl, {action:'sgc_pm_get_products', nonce:SGCPM.nonce, page:1, posts_per_page:1}, function(r){
		if(!r.success) return;
		$('#kpi-total').text(r.data.total.toLocaleString());
	});

	// Quick stats via direct WP query (separate AJAX)
	$.post(SGCPM.ajaxUrl, {action:'sgc_pm_dashboard_stats', nonce:SGCPM.nonce}, function(r){
		if(!r.success) return;
		var d = r.data;
		$('#kpi-total').text((d.total||0).toLocaleString());
		$('#kpi-pub').text((d.published||0).toLocaleString());
		$('#kpi-draft').text((d.draft||0).toLocaleString());
		$('#kpi-nostock').text((d.out_of_stock||0).toLocaleString());
		$('#kpi-nosku').text((d.no_sku||0).toLocaleString());
		$('#kpi-noimg').text((d.no_image||0).toLocaleString());
	});
});
</script>
