<?php
/**
 * Plugin Name:       SGC Smart CRM Pro
 * Plugin URI:        https://labsglass.com
 * Description:       AI-Powered B2B CRM — Contact management, CSV/Excel import, AI segmentation, email campaigns, WooCommerce & inquiry automation, weekly product promos. Standalone AI (Claude/OpenAI/Gemini/DeepSeek) with SGC Manager Pro fallback.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Tested up to:      7.0
 * WC tested up to:   10.8
 * Author:            SGC LABS®
 * Author URI:        https://labsglass.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sgc-smart-crm
 * Domain Path:       /languages
 * WC requires at least: 5.0
 * WC tested up to:   10.7
 *
 * @package SGC_Smart_CRM
 * @since   1.0.0
 */

// Block direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevent duplicate loading.
if ( defined( 'SGC_CRM_VERSION' ) ) {
	return;
}

/**
 * Plugin version.
 *
 * @since 1.0.0
 */
define( 'SGC_CRM_VERSION', '1.2.0' );
define( 'SGC_CRM_BUILD_DATE', '2026-11-10' );
define( 'SGC_CRM_BUILD_TIME', '18:30' );
define( 'SGC_CRM_BUILD_ID',   '20261110-1830' );
define( 'SGC_CRM_BUILD',      '2026-11-10 18:30 UTC' );
if ( ! defined( 'SGC_CRM_MIN_WP' ) ) define( 'SGC_CRM_MIN_WP', '6.0' );
define( 'SGC_CRM_MIN_PHP',    '8.0' );

/**
 * Absolute path to plugin directory (trailing slash).
 *
 * @since 1.0.0
 */
define( 'SGC_CRM_PATH', plugin_dir_path( __FILE__ ) );

/**
 * URL to plugin directory (trailing slash).
 *
 * @since 1.0.0
 */
define( 'SGC_CRM_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename for use in filters.
 *
 * @since 1.0.0
 */
define( 'SGC_CRM_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Minimum PHP version required.
 *
 * @since 1.0.0
 */


/**
 * Minimum WordPress version required.
 *
 * @since 1.0.0
 */

// =========================================================================
// REGISTER UNINSTALL HOOK — must be called before any includes.
// =========================================================================
register_uninstall_hook( __FILE__, 'sgc_crm_uninstall' );

// =========================================================================
// ENVIRONMENT CHECK
// =========================================================================

/**
 * Check minimum PHP & WP version requirements before loading.
 *
 * @since 1.0.0
 * @return bool True if requirements met.
 */
function sgc_crm_meets_requirements() {
	global $wp_version;

	if ( version_compare( PHP_VERSION, SGC_CRM_MIN_PHP, '<' ) ) {
		add_action(
			'admin_notices',
			function () {
				$msg = sprintf(
					/* translators: 1: Required PHP version 2: Current PHP version */
					esc_html__( 'SGC Smart CRM Pro requires PHP %1$s or higher. Your server is running PHP %2$s.', 'sgc-smart-crm' ),
					SGC_CRM_MIN_PHP,
					PHP_VERSION
				);
				printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $msg ) );
			}
		);
		return false;
	}

	if ( version_compare( $wp_version, SGC_CRM_MIN_WP, '<' ) ) {
		add_action(
			'admin_notices',
			function () use ( $wp_version ) {
				$msg = sprintf(
					/* translators: 1: Required WP version 2: Current WP version */
					esc_html__( 'SGC Smart CRM Pro requires WordPress %1$s or higher. You are running %2$s.', 'sgc-smart-crm' ),
					SGC_CRM_MIN_WP,
					$wp_version
				);
				printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $msg ) );
			}
		);
		return false;
	}

	return true;
}

if ( ! sgc_crm_meets_requirements() ) {
	return;
}

// =========================================================================
// LOAD INCLUDES
// =========================================================================

/**
 * Safely require a plugin file.
 *
 * @since  1.0.0
 * @param  string $file Absolute path to file.
 * @return void
 */
function sgc_crm_load( $file ) {
	if ( file_exists( $file ) ) {
		require_once $file;
	}
}

// Core includes — order matters.
sgc_crm_load( SGC_CRM_PATH . 'includes/class-crm-db.php' );
sgc_crm_load( SGC_CRM_PATH . 'includes/class-crm-ai.php' );
sgc_crm_load( SGC_CRM_PATH . 'includes/class-crm-contacts.php' );
sgc_crm_load( SGC_CRM_PATH . 'includes/class-crm-importer.php' );
sgc_crm_load( SGC_CRM_PATH . 'includes/class-crm-campaigns.php' );
sgc_crm_load( SGC_CRM_PATH . 'includes/class-crm-sender.php' );
sgc_crm_load( SGC_CRM_PATH . 'includes/class-crm-automation.php' );
sgc_crm_load( SGC_CRM_PATH . 'includes/class-crm-analytics.php' );
sgc_crm_load( SGC_CRM_PATH . 'includes/class-crm-product-fetch.php' );
sgc_crm_load( SGC_CRM_PATH . 'includes/class-crm-integration.php' ); // v1.2.0: was never loaded — Invoice Pro → CRM contact sync and the Changelog page were dead code

// ── Auto Updater — class loaded by Manager Pro ───────────────────────────
add_action( 'plugins_loaded', function () {
    if ( class_exists( 'SGC_Auto_Updater' ) ) {
        SGC_Auto_Updater::register([
            'plugin_id'       => 'sgc-smart-crm-pro',
            'plugin_slug'     => 'sgc-smart-crm/sgc-smart-crm.php',
            'current_version' => SGC_CRM_VERSION,
            'plugin_name'     => 'SGC Smart CRM Pro',
        ]);
    }
}, 25 );

// Admin only.
if ( is_admin() ) {
	sgc_crm_load( SGC_CRM_PATH . 'admin/class-crm-admin.php' );
}

// =========================================================================
// ACTIVATION
// =========================================================================

/**
 * Plugin activation callback.
 * Creates DB tables, sets default options, registers cron jobs.
 *
 * @since  1.0.0
 * @return void
 */
function sgc_crm_activate() {
	// Create tables.
	SGC_CRM_DB::create_tables();

	// Set default options.
	sgc_crm_set_defaults();

	// Register cron jobs.
	if ( ! wp_next_scheduled( 'sgc_crm_process_queue' ) ) {
		wp_schedule_event( time(), 'sgc_crm_every_five_minutes', 'sgc_crm_process_queue' );
	}
	if ( ! wp_next_scheduled( 'sgc_crm_run_automations' ) ) {
		wp_schedule_event( time(), 'hourly', 'sgc_crm_run_automations' );
	}
	if ( ! wp_next_scheduled( 'sgc_crm_weekly_broadcast' ) ) {
		wp_schedule_event(
			strtotime( 'next monday 10:00 AM' ),
			'sgc_crm_twice_weekly',
			'sgc_crm_weekly_broadcast'
		);
	}

	if ( ! wp_next_scheduled( 'sgc_crm_weekly_backup' ) ) {
		wp_schedule_event(
			strtotime( 'next monday 02:00 AM' ),
			'weekly',
			'sgc_crm_weekly_backup'
		);
	}

	// Flush rewrite rules for any custom endpoints.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'sgc_crm_activate' );

// =========================================================================
// DEACTIVATION
// =========================================================================

/**
 * Plugin deactivation callback.
 * Clears scheduled cron events.
 *
 * @since  1.0.0
 * @return void
 */
function sgc_crm_deactivate() {
	$hooks = array(
		'sgc_crm_process_queue',
		'sgc_crm_run_automations',
		'sgc_crm_weekly_broadcast',
		'sgc_crm_run_import_batch',
		'sgc_crm_weekly_backup',
	);
	foreach ( $hooks as $hook ) {
		$timestamp = wp_next_scheduled( $hook );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
		}
	}
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'sgc_crm_deactivate' );

// =========================================================================
// UNINSTALL — also handled by uninstall.php
// =========================================================================

/**
 * Plugin uninstall callback.
 * Drops all plugin tables and deletes all plugin options.
 *
 * @since  1.0.0
 * @return void
 */
function sgc_crm_uninstall() {
	if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
		return;
	}
	SGC_CRM_DB::drop_tables();
	sgc_crm_delete_options();
}

// =========================================================================
// DEFAULT OPTIONS
// =========================================================================

/**
 * Set plugin default options on first activation.
 *
 * @since  1.0.0
 * @return void
 */
function sgc_crm_set_defaults() {
	$defaults = array(
		'sgc_crm_from_name'                  => 'SGC LABS® Glassware',
		'sgc_crm_from_email'                 => get_option( 'sgclabs_ai_reply_from_email', get_option( 'admin_email', '' ) ),
		'sgc_crm_reply_to'                   => get_option( 'admin_email', '' ),
		'sgc_crm_footer_text'                => 'SGC LABS® | Scientific Glassware | Ambala, India | labsglass.com',
		'sgc_crm_batch_size'                 => 50,
		'sgc_crm_track_opens'                => '1',
		'sgc_crm_track_clicks'               => '1',
		'sgc_crm_weekly_segment'             => 'all',
		'sgc_crm_auto_order_followup'        => '1',
		'sgc_crm_auto_inquiry_followup'      => '1',
		'sgc_crm_auto_instant_reply'         => '1',
		'sgc_crm_auto_welcome'               => '1',
		'sgc_crm_weekly_ai_enabled'          => '1',
		'sgc_crm_auto_reorder_reminder'      => '1',
		// Data safety — OFF by default (v1.0.5).
		'sgc_crm_delete_data_on_uninstall'   => '0',
		// AI settings — default provider: DeepSeek (user selects model).
		'sgc_crm_ai_provider'                => 'deepseek',
		'sgc_crm_ai_model_claude'            => '',
		'sgc_crm_ai_model_openai'            => '',
		'sgc_crm_ai_model_gemini'            => '',
		'sgc_crm_ai_model_deepseek'          => '',
		// Keys stored blank — user fills in Settings.
		'sgc_crm_ai_key_claude'              => '',
		'sgc_crm_ai_key_openai'              => '',
		'sgc_crm_ai_key_gemini'              => '',
		'sgc_crm_ai_key_deepseek'            => '',
	);

	foreach ( $defaults as $key => $value ) {
		add_option( $key, $value );
	}
}

/**
 * Delete all plugin options on uninstall.
 *
 * @since  1.0.0
 * @return void
 */
function sgc_crm_delete_options() {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE 'sgc\_crm\_%'"
	);
	// Clean up import temp data.
	delete_option( 'sgc_crm_import_job' );
	delete_option( 'sgc_crm_import_rows' );
}

// =========================================================================
// CRON INTERVALS
// =========================================================================

/**
 * Register custom cron schedules.
 *
 * @since  1.0.0
 * @param  array $schedules Existing schedules.
 * @return array Modified schedules.
 */
add_filter( 'cron_schedules', 'sgc_crm_cron_schedules' );
function sgc_crm_cron_schedules( $schedules ) {
	$schedules['sgc_crm_every_five_minutes'] = array(
		'interval' => 300,
		'display'  => esc_html__( 'Every 5 Minutes (SGC CRM)', 'sgc-smart-crm' ),
	);
	$schedules['sgc_crm_twice_weekly'] = array(
		'interval' => 302400,
		'display'  => esc_html__( 'Twice Weekly (SGC CRM)', 'sgc-smart-crm' ),
	);
	if ( ! isset( $schedules['weekly'] ) ) {
		$schedules['weekly'] = array(
			'interval' => 604800,
			'display'  => esc_html__( 'Once Weekly', 'sgc-smart-crm' ),
		);
	}
	return $schedules;
}

// =========================================================================
// CRON ACTION HOOKS
// =========================================================================
add_action( 'sgc_crm_process_queue',       array( 'SGC_CRM_Sender',     'process_queue'             ) );
add_action( 'sgc_crm_run_automations',     array( 'SGC_CRM_Automation', 'run_pending'                ) );
add_action( 'sgc_crm_run_automations',     array( 'SGC_CRM_Automation', 'check_pipeline_followups'  ) );
add_action( 'sgc_crm_weekly_broadcast',    array( 'SGC_CRM_Automation', 'send_weekly_promo'          ) );
add_action( 'sgc_crm_send_auto_email',     array( 'SGC_CRM_Automation', 'send_auto_email'            ), 10, 3 );
add_action( 'sgc_crm_inquiry_followup',    array( 'SGC_CRM_Automation', 'handle_inquiry_followup'    ) );
add_action( 'sgc_crm_pipeline_followup',   array( 'SGC_CRM_Automation', 'send_pipeline_followup_email' ), 10, 1 );
add_action( 'sgc_crm_woo_sync_batch',      array( 'SGC_CRM_Contacts',   'process_woo_sync_batch'    ) );
add_action( 'sgc_crm_weekly_backup',       'sgc_crm_run_weekly_backup' );

// =========================================================================
// WEEKLY AUTO-BACKUP
// =========================================================================

/**
 * Weekly cron callback — exports all contacts to a CSV in uploads.
 * Keeps last 8 backups (2 months), deletes older ones automatically.
 * Saved to: /wp-content/uploads/sgc-crm-backups/backup-YYYY-MM-DD.csv
 *
 * @since  1.0.5
 * @return void
 */
function sgc_crm_run_weekly_backup() {
	global $wpdb;

	$table  = $wpdb->prefix . 'sgc_crm_contacts';
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	if ( $exists !== $table ) {
		return;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY id ASC", ARRAY_A );
	if ( empty( $rows ) ) {
		return;
	}

	$upload_dir = wp_upload_dir();
	$dir        = trailingslashit( $upload_dir['basedir'] ) . 'sgc-crm-backups';

	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
		// Block direct browser access.
		file_put_contents( $dir . '/.htaccess', 'deny from all' ); // phpcs:ignore
		file_put_contents( $dir . '/index.php', '<?php // Silence.' ); // phpcs:ignore
	}

	// Write CSV.
	$filename = $dir . '/backup-' . gmdate( 'Y-m-d' ) . '.csv';
	$handle   = fopen( $filename, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! $handle ) {
		return;
	}
	fputcsv( $handle, array_keys( $rows[0] ) );
	foreach ( $rows as $row ) {
		fputcsv( $handle, $row );
	}
	fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions

	// Rotate — keep only latest 8 backups.
	$files = glob( $dir . '/backup-*.csv' );
	if ( $files && count( $files ) > 8 ) {
		sort( $files ); // Oldest first.
		$old = array_slice( $files, 0, count( $files ) - 8 );
		foreach ( $old as $old_file ) {
			wp_delete_file( $old_file );
		}
	}

	// Record last backup time.
	update_option( 'sgc_crm_last_backup', gmdate( 'Y-m-d H:i:s' ) );
	update_option( 'sgc_crm_last_backup_count', count( $rows ) );
	update_option( 'sgc_crm_last_backup_file', basename( $filename ) );
}

// =========================================================================
// I18N
// =========================================================================

/**
 * Load plugin text domain for translations.
 *
 * @since  1.0.0
 * @return void
 */
add_action( 'init', 'sgc_crm_load_textdomain' );
function sgc_crm_load_textdomain() {
	load_plugin_textdomain(
		'sgc-smart-crm',
		false,
		dirname( SGC_CRM_BASENAME ) . '/languages'
	);
}

// =========================================================================
// ENQUEUE ADMIN ASSETS
// =========================================================================

/**
 * Enqueue admin CSS and JS only on plugin pages.
 *
 * @since  1.0.0
 * @param  string $hook Current admin page hook.
 * @return void
 */
add_action( 'admin_enqueue_scripts', 'sgc_crm_admin_assets' );
function sgc_crm_admin_assets( $hook ) {
	// Only load on our plugin pages.
	if ( false === strpos( $hook, 'sgc-smart-crm' ) ) {
		return;
	}

	$css_file = SGC_CRM_PATH . 'assets/css/admin.css';
	$js_file  = SGC_CRM_PATH . 'assets/js/admin.js';

	wp_enqueue_style(
		'sgc-crm-admin',
		SGC_CRM_URL . 'assets/css/admin.css',
		array(),
		file_exists( $css_file ) ? filemtime( $css_file ) : SGC_CRM_VERSION
	);

	wp_enqueue_script(
		'sgc-crm-admin',
		SGC_CRM_URL . 'assets/js/admin.js',
		array( 'jquery' ),
		file_exists( $js_file ) ? filemtime( $js_file ) : SGC_CRM_VERSION,
		true // Load in footer.
	);

	wp_localize_script(
		'sgc-crm-admin',
		'SGC_CRM',
		array(
			'ajax_url'       => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'sgc_crm_nonce' ),
			'version'        => SGC_CRM_VERSION,
			'confirm_delete' => esc_html__( 'Are you sure you want to delete this?', 'sgc-smart-crm' ),
			'confirm_send'   => esc_html__( 'Send to all matching contacts now?', 'sgc-smart-crm' ),
			'confirm_cancel' => esc_html__( 'Cancel the ongoing import?', 'sgc-smart-crm' ),
			'import_bg_msg'  => esc_html__( 'Import is running in background. You can close this page — it will continue.', 'sgc-smart-crm' ),
		)
	);
}

// =========================================================================
// PLUGIN ACTION LINKS
// =========================================================================

/**
 * Add Settings and Contacts links on Plugins page.
 *
 * @since  1.0.0
 * @param  array $links Existing action links.
 * @return array Modified links.
 */
add_filter( 'plugin_action_links_' . SGC_CRM_BASENAME, 'sgc_crm_action_links' );
function sgc_crm_action_links( $links ) {
	$plugin_links = array(
		'<a href="' . esc_url( admin_url( 'admin.php?page=sgc-smart-crm&tab=settings' ) ) . '">'
			. esc_html__( 'Settings', 'sgc-smart-crm' ) . '</a>',
		'<a href="' . esc_url( admin_url( 'admin.php?page=sgc-smart-crm&tab=contacts' ) ) . '">'
			. esc_html__( 'Contacts', 'sgc-smart-crm' ) . '</a>',
	);
	return array_merge( $plugin_links, $links );
}

// =========================================================================
// WOOCOMMERCE DECLARE COMPATIBILITY
// =========================================================================
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
);

// =========================================================================
// AJAX: Save toggle option (whitelisted options only)
// =========================================================================

/**
 * AJAX handler to save a single plugin option (for toggle switches).
 *
 * @since  1.0.0
 * @return void Sends JSON response.
 */
add_action( 'wp_ajax_sgc_crm_save_option', 'sgc_crm_ajax_save_option' );
function sgc_crm_ajax_save_option() {
	check_ajax_referer( 'sgc_crm_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Insufficient permissions.', 'sgc-smart-crm' ), 403 );
	}

	// Strict whitelist — only boolean toggle options allowed here.
	$allowed_options = array(
		'sgc_crm_auto_order_followup',
		'sgc_crm_auto_inquiry_followup',
		'sgc_crm_auto_instant_reply',
		'sgc_crm_auto_welcome',
		'sgc_crm_auto_reorder_reminder',
		'sgc_crm_weekly_ai_enabled',
		'sgc_crm_delete_data_on_uninstall',
		'sgc_crm_track_opens',
		'sgc_crm_track_clicks',
	);

	$option = sanitize_key( wp_unslash( $_POST['option_name'] ?? '' ) );
	$value  = ( '1' === sanitize_text_field( wp_unslash( $_POST['option_value'] ?? '0' ) ) ) ? '1' : '0';

	if ( ! in_array( $option, $allowed_options, true ) ) {
		wp_send_json_error( esc_html__( 'Option not permitted.', 'sgc-smart-crm' ), 400 );
	}

	update_option( $option, $value );
	wp_send_json_success( array( 'option' => $option, 'value' => $value ) );
}

// =========================================================================
// AJAX: Save email / general settings (non-toggle options)
// =========================================================================

/**
 * AJAX handler to save email and general CRM settings.
 *
 * @since  1.0.0
 * @return void
 */
add_action( 'wp_ajax_sgc_crm_save_email_settings', 'sgc_crm_ajax_save_email_settings' );
function sgc_crm_ajax_save_email_settings() {
	check_ajax_referer( 'sgc_crm_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Insufficient permissions.', 'sgc-smart-crm' ), 403 );
	}

	$fields = array(
		'sgc_crm_from_name'             => 'text',
		'sgc_crm_from_email'            => 'email',
		'sgc_crm_reply_to'              => 'email',
		'sgc_crm_footer_text'           => 'text',
		'sgc_crm_weekly_segment'        => 'key',
		'sgc_crm_batch_size'            => 'int',
		'sgc_crm_track_opens'           => 'bool',
		'sgc_crm_track_clicks'          => 'bool',
		'sgc_crm_auto_order_followup'   => 'bool',
		'sgc_crm_auto_inquiry_followup' => 'bool',
		'sgc_crm_auto_instant_reply'    => 'bool',
		'sgc_crm_auto_welcome'          => 'bool',
		'sgc_crm_auto_reorder_reminder' => 'bool',
		'sgc_crm_weekly_ai_enabled'     => 'bool',
	);

	$saved = array();

	foreach ( $fields as $key => $type ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			continue;
		}
		$raw = wp_unslash( $_POST[ $key ] );
		switch ( $type ) {
			case 'email':
				$value = sanitize_email( $raw );
				break;
			case 'key':
				$value = sanitize_key( $raw );
				break;
			case 'int':
				$value = max( 5, min( 200, (int) $raw ) );
				break;
			case 'bool':
				$value = ( '1' === sanitize_text_field( $raw ) ) ? '1' : '0';
				break;
			default:
				$value = sanitize_text_field( $raw );
		}
		update_option( $key, $value );
		$saved[] = $key;
	}

	wp_send_json_success(
		array(
			'saved'   => $saved,
			'message' => esc_html__( 'Settings saved.', 'sgc-smart-crm' ),
		)
	);
}

// =========================================================================
// AJAX: Trigger weekly promo manually
// =========================================================================

/**
 * AJAX handler to manually trigger the weekly product promo broadcast.
 *
 * @since  1.0.0
 * @return void Sends JSON response.
 */
add_action( 'wp_ajax_sgc_crm_trigger_weekly_promo_now', 'sgc_crm_ajax_trigger_weekly' );
function sgc_crm_ajax_trigger_weekly() {
	check_ajax_referer( 'sgc_crm_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Insufficient permissions.', 'sgc-smart-crm' ), 403 );
	}

	// Schedule async — avoids PHP timeout on slow AI calls.
	wp_schedule_single_event( time(), 'sgc_crm_weekly_broadcast' );

	wp_send_json_success(
		esc_html__( 'Weekly promo scheduled. It will send momentarily.', 'sgc-smart-crm' )
	);
}

// =========================================================================
// AJAX: Run backup now (manual trigger from Settings)
// =========================================================================

/**
 * AJAX handler — manually trigger a contacts backup from Settings page.
 *
 * @since  1.0.5
 * @return void
 */
add_action( 'wp_ajax_sgc_crm_run_backup_now', 'sgc_crm_ajax_run_backup_now' );
function sgc_crm_ajax_run_backup_now() {
	check_ajax_referer( 'sgc_crm_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Insufficient permissions.', 'sgc-smart-crm' ), 403 );
	}

	sgc_crm_run_weekly_backup();

	$last = get_option( 'sgc_crm_last_backup', '' );
	$cnt  = get_option( 'sgc_crm_last_backup_count', 0 );
	$file = get_option( 'sgc_crm_last_backup_file', '' );

	if ( ! $last ) {
		wp_send_json_error( esc_html__( 'Backup failed — no contacts found or folder not writable.', 'sgc-smart-crm' ) );
	}

	wp_send_json_success(
		array(
			/* translators: 1: contact count 2: filename */
			'message' => sprintf(
				esc_html__( 'Backup complete — %1$d contacts saved to %2$s', 'sgc-smart-crm' ),
				(int) $cnt,
				$file
			),
		)
	);
}

// =========================================================================
// FRONTEND: Public nonce + ajaxurl for contact forms
// v1.1.3: Was missing — frontend forms couldn't authenticate with server
// =========================================================================
add_action( 'wp_enqueue_scripts', 'sgc_crm_frontend_scripts' );
function sgc_crm_frontend_scripts() {
    // Only add inline script — no extra JS file needed
    // Outputs sgcCRM.ajaxUrl and sgcCRM.nonce for any frontend form
    $data = wp_json_encode( [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'sgc_crm_public' ),
        'action'  => 'sgc_crm_add_contact',
    ] );
    wp_add_inline_script(
        'jquery', // attach to jQuery which is always loaded
        "window.sgcCRM = {$data};",
        'after'
    );
}
