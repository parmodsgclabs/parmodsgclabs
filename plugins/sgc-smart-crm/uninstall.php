<?php
/**
 * SGC Smart CRM Pro — Uninstall
 *
 * DATA SAFETY (v1.0.5):
 * Tables are only dropped if admin has explicitly enabled
 * "Delete all data on uninstall" in Settings → Danger Zone.
 * Default = OFF → contacts & campaigns are always safe.
 *
 * @package SGC_Smart_CRM
 * @since   1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// =========================================================================
// ALWAYS: Clear cron events (safe — no data loss)
// =========================================================================
$cron_hooks = array(
	'sgc_crm_process_queue',
	'sgc_crm_run_automations',
	'sgc_crm_weekly_broadcast',
	'sgc_crm_send_auto_email',
	'sgc_crm_inquiry_followup',
	'sgc_crm_weekly_backup',
);
foreach ( $cron_hooks as $hook ) {
	wp_clear_scheduled_hook( $hook );
}

// =========================================================================
// ALWAYS: Clear transients only (cache — not real data)
// =========================================================================
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '\\_transient\\_sgc\\_crm\\_%'
	    OR option_name LIKE '\\_transient\\_timeout\\_sgc\\_crm\\_%'"
);

// =========================================================================
// CONDITIONAL: Drop tables + delete options
// Only if admin explicitly turned ON "Delete all data on uninstall"
// =========================================================================
$delete_data = get_option( 'sgc_crm_delete_data_on_uninstall', '0' );

if ( '1' !== $delete_data ) {
	// Data is safe. Plugin files gone but DB tables remain intact.
	// Reinstall plugin anytime — all contacts & campaigns restored.
	return;
}

// Admin chose to wipe everything — save backup first.
sgc_crm_uninstall_backup();

$tables = array(
	$wpdb->prefix . 'sgc_crm_auto_log',
	$wpdb->prefix . 'sgc_crm_queue',
	$wpdb->prefix . 'sgc_crm_sequences',
	$wpdb->prefix . 'sgc_crm_campaigns',
	$wpdb->prefix . 'sgc_crm_contacts',
);
foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE 'sgc\\_crm\\_%'"
);

/**
 * Auto-export contacts CSV before wiping DB on uninstall.
 * Saved to: /wp-content/uploads/sgc-crm-backups/uninstall-backup-{date}.csv
 *
 * @since  1.0.5
 * @return void
 */
function sgc_crm_uninstall_backup() {
	global $wpdb;
	$table  = $wpdb->prefix . 'sgc_crm_contacts';
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore
	if ( $exists !== $table ) return;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY id ASC", ARRAY_A );
	if ( empty( $rows ) ) return;

	$upload_dir = wp_upload_dir();
	$dir        = trailingslashit( $upload_dir['basedir'] ) . 'sgc-crm-backups';
	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
		file_put_contents( $dir . '/index.php', '<?php // Silence.' ); // phpcs:ignore
	}

	$file   = $dir . '/uninstall-backup-' . gmdate( 'Y-m-d-His' ) . '.csv';
	$handle = fopen( $file, 'w' ); // phpcs:ignore
	if ( ! $handle ) return;

	fputcsv( $handle, array_keys( $rows[0] ) );
	foreach ( $rows as $row ) {
		fputcsv( $handle, $row );
	}
	fclose( $handle ); // phpcs:ignore
}
