<?php
/**
 * SGC Product Manager Pro — Uninstall
 *
 * DATA SAFETY: Tables only dropped if admin explicitly enabled
 * "Delete all data on uninstall" in Settings → Danger Zone.
 * Default = OFF → all product data, automation rules, competitor data safe.
 *
 * @package SGC_PM
 * @since   3.0.3
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

global $wpdb;

// Always: clear scheduled crons
$hooks = [ 'sgc_pm_auto_cron', 'sgc_pm_competitor_scan', 'sgc_pm_daily_report' ];
foreach ( $hooks as $hook ) wp_clear_scheduled_hook( $hook );

// Always: clear transients
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_sgc\_pm\_%' OR option_name LIKE '\_transient\_timeout\_sgc\_pm\_%'" ); // phpcs:ignore

// Conditional: only wipe tables if user explicitly requested
if ( get_option( 'sgc_pm_delete_data_on_uninstall', '0' ) !== '1' ) return;

$tables = [
    $wpdb->prefix . 'sgcpm_competitors',
    $wpdb->prefix . 'sgcpm_competitor_data',
    $wpdb->prefix . 'sgcpm_automations',
    $wpdb->prefix . 'sgcpm_logs',
    $wpdb->prefix . 'sgcpm_keywords',
    $wpdb->prefix . 'sgcps_defaults',
    $wpdb->prefix . 'sgcps_product_params',
];
foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore
}
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'sgc\_pm\_%' OR option_name LIKE 'sgcps\_%'" ); // phpcs:ignore
