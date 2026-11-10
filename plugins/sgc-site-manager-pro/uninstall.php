<?php
/**
 * SGC LABS® Manager Pro — Uninstall
 *
 * Runs when user deletes the plugin from WP Admin.
 * Cleans up ALL plugin options, post meta, custom tables,
 * transients, and cron events from the database.
 *
 * @package SGCLabs_Manager_Pro
 * @since   6.6.5
 */

// Safety: only run via WordPress uninstall process.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// v7.5.1: data safety — inquiries, quotes, keywords and redirects are business data.
// They are only dropped when the admin explicitly enabled "Delete all data on uninstall"
// (option sgclabs_delete_data_on_uninstall = 1, General Settings). Otherwise uninstall only
// clears cron jobs and transients, like Product Manager Pro and Smart CRM already do.
foreach ( [ 'sgclabs_daily_crawl', 'sgclabs_broken_link_check', 'sgclabs_rank_update', 'sgclabs_sitemap_ping', 'sgclabs_send_inquiry_followup', 'sgc_health_weekly_scan', 'sgc_auto_repair_daily' ] as $hook ) {
    wp_clear_scheduled_hook( $hook );
}
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_sgclabs%' OR option_name LIKE '\_transient\_timeout\_sgclabs%'" ); // phpcs:ignore
if ( get_option( 'sgclabs_delete_data_on_uninstall', '0' ) !== '1' ) {
    return;
}

// ── 1. Remove all sgclabs_* options ──
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'sgclabs\_%'" );

// ── 2. Remove ALL sgclabs post meta (wildcard catches everything) ──
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_sgclabs\_%'" );

// ── 3. Remove custom DB tables ──
// Table names MUST match the actual CREATE TABLE statements in the code.
$tables = [
    $wpdb->prefix . 'sgclabs_inquiries',        // get-a-quote.php
    $wpdb->prefix . 'sgclabs_redirects',         // redirections.php
    $wpdb->prefix . 'sgclabs_broken_links',      // broken-links.php
    $wpdb->prefix . 'sgclabs_rank_history',      // rank-tracker.php
    $wpdb->prefix . 'sgclabs_crawl_pages',       // site-crawler.php
    $wpdb->prefix . 'sgclabs_sitemap_pings',     // site-crawler.php
    $wpdb->prefix . 'sgclabs_kw_global',         // keyword-manager.php
    $wpdb->prefix . 'sgclabs_kw_category',       // keyword-manager.php
    $wpdb->prefix . 'sgclabs_kw_gsc',            // keyword-manager.php
    $wpdb->prefix . 'sgclabs_b2b_quotes',        // b2b-quote-engine.php
    $wpdb->prefix . 'sgclabs_shipping_rules',    // class-sgclabs-shipping-rules.php
];

foreach ( $tables as $table ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// ── 4. Clear any transients ──
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sgclabs%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_sgclabs%'" );

// ── 5. Clear scheduled cron events ──
$cron_hooks = [
    'sgclabs_daily_crawl',
    'sgclabs_broken_link_check',
    'sgclabs_rank_update',
    'sgclabs_sitemap_ping',
];

foreach ( $cron_hooks as $hook ) {
    $timestamp = wp_next_scheduled( $hook );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, $hook );
    }
}

// ── 6. Remove any user meta ──
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'sgclabs\_%'" );

// ── 7. Remove term meta (if any) ──
$wpdb->query( "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE '_sgclabs\_%'" );
