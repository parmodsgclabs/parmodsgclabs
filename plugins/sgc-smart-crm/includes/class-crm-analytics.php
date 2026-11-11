<?php
/**
 * SGC Smart CRM Pro — Analytics
 *
 * Campaign reporting and dashboard statistics.
 *
 * @package SGC_Smart_CRM
 * @since   1.0.0
 * @version   1.0.4 | Build: 20260516-0630
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SGC_CRM_Analytics
 *
 * @since 1.0.0
 * @version   1.0.4 | Build: 20260516-0630
 */
class SGC_CRM_Analytics {

	/**
	 * Register AJAX handlers.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function register_ajax() {
		add_action( 'wp_ajax_sgc_crm_get_stats',           array( __CLASS__, 'ajax_stats' ) );
		add_action( 'wp_ajax_sgc_crm_get_campaign_report', array( __CLASS__, 'ajax_campaign_report' ) );
	}

	/**
	 * Verify nonce and capability.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private static function verify() {
		check_ajax_referer( 'sgc_crm_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions.', 'sgc-smart-crm' ), 403 );
		}
	}

	/**
	 * AJAX — return dashboard statistics.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_stats() {
		self::verify();
		wp_send_json_success( SGC_CRM_DB::get_stats() );
	}

	/**
	 * AJAX — return detailed campaign report.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_campaign_report() {
		self::verify();
		global $wpdb;

		$id = (int) ( $_POST['id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( esc_html__( 'Invalid campaign ID.', 'sgc-smart-crm' ) );
		}

		$campaign = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT * FROM `' . SGC_CRM_DB::campaigns() . '` WHERE id = %d', $id )
		);
		if ( ! $campaign ) {
			wp_send_json_error( esc_html__( 'Campaign not found.', 'sgc-smart-crm' ) );
		}

		$q_table = SGC_CRM_DB::queue();

		$stats = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT
				  COUNT(*) AS total,
				  SUM(status = 'sent')    AS sent,
				  SUM(status = 'failed')  AS failed,
				  SUM(status = 'skipped') AS skipped,
				  SUM(opened_at IS NOT NULL)  AS opens,
				  SUM(clicked_at IS NOT NULL) AS clicks
				FROM `{$q_table}` WHERE campaign_id = %d",
				$id
			)
		);

		$open_rate  = ( $stats && $stats->sent > 0 ) ? round( ( $stats->opens  / $stats->sent ) * 100, 1 ) : 0;
		$click_rate = ( $stats && $stats->sent > 0 ) ? round( ( $stats->clicks / $stats->sent ) * 100, 1 ) : 0;

		$openers = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT q.email, q.opened_at, c.first_name, c.company, c.country
				 FROM `{$q_table}` q
				 LEFT JOIN `" . SGC_CRM_DB::contacts() . "` c ON c.email = q.email
				 WHERE q.campaign_id = %d AND q.opened_at IS NOT NULL
				 ORDER BY q.opened_at DESC LIMIT 20",
				$id
			)
		);

		wp_send_json_success(
			array(
				'campaign'   => $campaign,
				'stats'      => $stats,
				'open_rate'  => $open_rate,
				'click_rate' => $click_rate,
				'openers'    => $openers,
			)
		);
	}
}

SGC_CRM_Analytics::register_ajax();
