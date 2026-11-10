<?php
/**
 * SGC Smart CRM Pro — Database Helper
 *
 * Centralized table name helpers, table creation, and common queries.
 *
 * @package SGC_Smart_CRM
 * @since   1.0.0
 * @version   1.0.4 | Build: 20260516-0630
 */

// Block direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SGC_CRM_DB
 *
 * @since 1.0.0
 * @version   1.0.4 | Build: 20260516-0630
 */
class SGC_CRM_DB {

	// =========================================================================
	// TABLE NAMES
	// =========================================================================

	/**
	 * Contacts table name.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public static function contacts() {
		global $wpdb;
		return $wpdb->prefix . 'sgc_crm_contacts';
	}

	/**
	 * Campaigns table name.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public static function campaigns() {
		global $wpdb;
		return $wpdb->prefix . 'sgc_crm_campaigns';
	}

	/**
	 * Email queue table name.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public static function queue() {
		global $wpdb;
		return $wpdb->prefix . 'sgc_crm_queue';
	}

	/**
	 * Automation sequences table name.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public static function sequences() {
		global $wpdb;
		return $wpdb->prefix . 'sgc_crm_sequences';
	}

	/**
	 * Automation log table name.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public static function auto_log() {
		global $wpdb;
		return $wpdb->prefix . 'sgc_crm_auto_log';
	}

	// =========================================================================
	// TABLE CREATION
	// =========================================================================

	/**
	 * Create all plugin database tables.
	 * Uses dbDelta() — safe to run on updates.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Contacts.
		dbDelta(
			"CREATE TABLE " . self::contacts() . " (
				id            bigint(20)    UNSIGNED NOT NULL AUTO_INCREMENT,
				email         varchar(200)  NOT NULL DEFAULT '',
				first_name    varchar(100)  NOT NULL DEFAULT '',
				last_name     varchar(100)  NOT NULL DEFAULT '',
				company       varchar(200)  NOT NULL DEFAULT '',
				phone         varchar(50)   NOT NULL DEFAULT '',
				country       varchar(100)  NOT NULL DEFAULT '',
				city          varchar(100)  NOT NULL DEFAULT '',
				tags          text          NOT NULL,
				segment       varchar(100)  NOT NULL DEFAULT 'end_user',
				source        varchar(100)  NOT NULL DEFAULT 'manual',
				status        varchar(20)   NOT NULL DEFAULT 'subscribed',
				ai_score      tinyint(3)    UNSIGNED NOT NULL DEFAULT 0,
				last_order_at datetime      DEFAULT NULL,
				total_orders  mediumint(6)  UNSIGNED NOT NULL DEFAULT 0,
				total_spent   decimal(10,2) NOT NULL DEFAULT 0.00,
				notes         text          NOT NULL,
				created_at    datetime      NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at    datetime      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY   email (email),
				KEY          segment (segment),
				KEY          status (status),
				KEY          source (source),
				KEY          created_at (created_at)
			) $charset"
		);

		// Campaigns.
		dbDelta(
			"CREATE TABLE " . self::campaigns() . " (
				id             bigint(20)   UNSIGNED NOT NULL AUTO_INCREMENT,
				name           varchar(200) NOT NULL DEFAULT '',
				type           varchar(50)  NOT NULL DEFAULT 'newsletter',
				audience       varchar(100) NOT NULL DEFAULT 'all',
				subject        varchar(300) NOT NULL DEFAULT '',
				preheader      varchar(200) NOT NULL DEFAULT '',
				body_html      longtext     NOT NULL,
				from_name      varchar(100) NOT NULL DEFAULT '',
				from_email     varchar(200) NOT NULL DEFAULT '',
				reply_to       varchar(200) NOT NULL DEFAULT '',
				segment_filter varchar(100) NOT NULL DEFAULT 'all',
				tags_filter    varchar(200) NOT NULL DEFAULT '',
				status         varchar(30)  NOT NULL DEFAULT 'draft',
				scheduled_at   datetime     DEFAULT NULL,
				sent_at        datetime     DEFAULT NULL,
				total_sent     int(10)      UNSIGNED NOT NULL DEFAULT 0,
				total_opened   int(10)      UNSIGNED NOT NULL DEFAULT 0,
				total_clicked  int(10)      UNSIGNED NOT NULL DEFAULT 0,
				ai_generated   tinyint(1)   UNSIGNED NOT NULL DEFAULT 0,
				created_at     datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY          type (type),
				KEY          status (status),
				KEY          created_at (created_at)
			) $charset"
		);

		// Email queue.
		dbDelta(
			"CREATE TABLE " . self::queue() . " (
				id            bigint(20)   UNSIGNED NOT NULL AUTO_INCREMENT,
				campaign_id   bigint(20)   UNSIGNED NOT NULL DEFAULT 0,
				contact_id    bigint(20)   UNSIGNED NOT NULL DEFAULT 0,
				email         varchar(200) NOT NULL DEFAULT '',
				status        varchar(20)  NOT NULL DEFAULT 'pending',
				track_token   varchar(64)  NOT NULL DEFAULT '',
				sent_at       datetime     DEFAULT NULL,
				opened_at     datetime     DEFAULT NULL,
				clicked_at    datetime     DEFAULT NULL,
				bounce_reason varchar(200) NOT NULL DEFAULT '',
				created_at    datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY          campaign_id (campaign_id),
				KEY          contact_id (contact_id),
				KEY          status (status),
				UNIQUE KEY   track_token (track_token),
				KEY          created_at (created_at)
			) $charset"
		);

		// Sequences.
		dbDelta(
			"CREATE TABLE " . self::sequences() . " (
				id            bigint(20)   UNSIGNED NOT NULL AUTO_INCREMENT,
				name          varchar(200) NOT NULL DEFAULT '',
				trigger_event varchar(100) NOT NULL DEFAULT '',
				delay_hours   mediumint(5) UNSIGNED NOT NULL DEFAULT 24,
				subject       varchar(300) NOT NULL DEFAULT '',
				body_html     longtext     NOT NULL,
				conditions    text         NOT NULL,
				active        tinyint(1)   UNSIGNED NOT NULL DEFAULT 1,
				sent_count    int(10)      UNSIGNED NOT NULL DEFAULT 0,
				created_at    datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY          trigger_event (trigger_event),
				KEY          active (active)
			) $charset"
		);

		// Automation log.
		dbDelta(
			"CREATE TABLE " . self::auto_log() . " (
				id            bigint(20)   UNSIGNED NOT NULL AUTO_INCREMENT,
				sequence_id   bigint(20)   UNSIGNED NOT NULL DEFAULT 0,
				contact_id    bigint(20)   UNSIGNED NOT NULL DEFAULT 0,
				email         varchar(200) NOT NULL DEFAULT '',
				trigger_event varchar(100) NOT NULL DEFAULT '',
				status        varchar(20)  NOT NULL DEFAULT 'pending',
				scheduled_at  datetime     DEFAULT NULL,
				sent_at       datetime     DEFAULT NULL,
				created_at    datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY          sequence_id (sequence_id),
				KEY          contact_id (contact_id),
				KEY          status (status),
				KEY          scheduled_at (scheduled_at)
			) $charset"
		);
	}

	// =========================================================================
	// TABLE REMOVAL
	// =========================================================================

	/**
	 * Drop all plugin tables.
	 * Called on plugin uninstall.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function drop_tables() {
		global $wpdb;

		// Drop in reverse dependency order.
		$tables = array(
			self::auto_log(),
			self::queue(),
			self::sequences(),
			self::campaigns(),
			self::contacts(),
		);

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		}
	}

	// =========================================================================
	// STATS
	// =========================================================================

	/**
	 * Get dashboard statistics.
	 *
	 * @since  1.0.0
	 * @return array<string, mixed>
	 */
	public static function get_stats() {
		global $wpdb;

		$c_table  = self::contacts();
		$ca_table = self::campaigns();
		$q_table  = self::queue();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array(
			'total_contacts'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$c_table}`" ),
			'subscribed'        => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$c_table}` WHERE status = %s", 'subscribed' ) ),
			'unsubscribed'      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$c_table}` WHERE status = %s", 'unsubscribed' ) ),
			'total_campaigns'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$ca_table}`" ),
			'sent_campaigns'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$ca_table}` WHERE status = %s", 'sent' ) ),
			'total_sent_emails' => (int) $wpdb->get_var( "SELECT COALESCE(SUM(total_sent), 0) FROM `{$ca_table}`" ),
			'total_opens'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$q_table}` WHERE opened_at IS NOT NULL" ),
			'total_clicks'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$q_table}` WHERE clicked_at IS NOT NULL" ),
			'segments'          => $wpdb->get_results(
				"SELECT segment, COUNT(*) AS cnt
				 FROM `{$c_table}`
				 WHERE status = 'subscribed'
				 GROUP BY segment
				 ORDER BY cnt DESC
				 LIMIT 10",
				ARRAY_A
			),
		);
		// phpcs:enable
	}
}

// v1.0.8: Safe ALTER — add reorder_reminded_at column for reorder prediction
add_action( 'init', function() {
    if ( get_option('sgc_crm_db_v108') === '1' ) return;
    global $wpdb;
    $ct = $wpdb->prefix . 'sgc_crm_contacts';
    if ( $wpdb->get_var("SHOW TABLES LIKE '$ct'") !== $ct ) return;
    $cols = $wpdb->get_col("DESCRIBE `$ct`");
    if ( ! in_array('reorder_reminded_at', $cols, true) ) {
        $wpdb->query("ALTER TABLE `$ct` ADD COLUMN `reorder_reminded_at` datetime DEFAULT NULL COMMENT 'Reorder reminder last sent'");
    }
    update_option('sgc_crm_db_v108', '1');
}, 25 );

// v1.1.0: Safe ALTER — Lead Pipeline columns (lead_status, followup_at, followup_note)
add_action( 'init', function() {
    if ( get_option('sgc_crm_db_v110') === '1' ) return;
    global $wpdb;
    $ct = $wpdb->prefix . 'sgc_crm_contacts';
    if ( $wpdb->get_var("SHOW TABLES LIKE '$ct'") !== $ct ) return;
    $cols = $wpdb->get_col("DESCRIBE `$ct`");
    if ( ! in_array('lead_status', $cols, true) ) {
        $wpdb->query("ALTER TABLE `$ct` ADD COLUMN `lead_status` varchar(30) NOT NULL DEFAULT 'none' COMMENT 'Pipeline: none/new/contacted/quoted/follow_up/won/lost' AFTER `status`");
    }
    if ( ! in_array('followup_at', $cols, true) ) {
        $wpdb->query("ALTER TABLE `$ct` ADD COLUMN `followup_at` datetime DEFAULT NULL COMMENT 'Scheduled follow-up datetime' AFTER `lead_status`");
    }
    if ( ! in_array('followup_note', $cols, true) ) {
        $wpdb->query("ALTER TABLE `$ct` ADD COLUMN `followup_note` text NOT NULL DEFAULT '' COMMENT 'Internal follow-up note / email context' AFTER `followup_at`");
    }
    // Index for cron query performance.
    $indexes = $wpdb->get_col("SHOW INDEX FROM `$ct`", 2);
    if ( ! in_array('lead_status', $indexes, true) ) {
        $wpdb->query("ALTER TABLE `$ct` ADD INDEX `lead_status` (`lead_status`)");
    }
    if ( ! in_array('followup_at', $indexes, true) ) {
        $wpdb->query("ALTER TABLE `$ct` ADD INDEX `followup_at` (`followup_at`)");
    }
    update_option('sgc_crm_db_v110', '1');
}, 26 );

