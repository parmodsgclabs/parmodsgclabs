<?php
defined( 'ABSPATH' ) || exit;

class SGC_PM_Logger {

	public static function log( $action, $object_type = 'product', $object_id = null, $details = '' ) {
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'sgcpm_logs', [ // phpcs:ignore
			'action'      => sanitize_text_field( $action ),
			'object_type' => sanitize_text_field( $object_type ),
			'object_id'   => $object_id ? (int) $object_id : null,
			'details'     => sanitize_textarea_field( $details ),
			'user_id'     => get_current_user_id() ?: null,
			'ip'          => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
			'created_at'  => current_time( 'mysql' ),
		] );
	}

	public static function register_ajax() {
		add_action( 'wp_ajax_sgc_pm_get_logs',   [ __CLASS__, 'ajax_get_logs' ] );
		add_action( 'wp_ajax_sgc_pm_clear_logs', [ __CLASS__, 'ajax_clear_logs' ] );
	}

	private static function verify() {
		check_ajax_referer( 'sgc_pm_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) )
			wp_send_json_error( 'Unauthorized', 403 );
	}

	public static function ajax_get_logs() {
		self::verify();
		global $wpdb;
		$page   = max( 1, (int) ( $_POST['page'] ?? 1 ) );
		$per    = 50;
		$offset = ( $page - 1 ) * $per;
		$filter = sanitize_key( $_POST['filter'] ?? '' );

		$where = $filter ? $wpdb->prepare( 'WHERE action LIKE %s', '%' . $wpdb->esc_like( $filter ) . '%' ) : '';
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sgcpm_logs $where" ); // phpcs:ignore
		$logs  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sgcpm_logs $where ORDER BY created_at DESC LIMIT %d OFFSET %d", $per, $offset ) ); // phpcs:ignore

		wp_send_json_success( [ 'logs' => $logs, 'total' => $total, 'pages' => ceil( $total / $per ) ] );
	}

	public static function ajax_clear_logs() {
		self::verify();
		global $wpdb;
		$days = max( 1, (int) ( $_POST['days'] ?? 30 ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sgcpm_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)", $days ) ); // phpcs:ignore
		wp_send_json_success( 'Logs older than ' . $days . ' days cleared.' );
	}
}

SGC_PM_Logger::register_ajax();
