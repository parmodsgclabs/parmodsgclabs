<?php
defined( 'ABSPATH' ) || exit;

class SGC_PM_SQL_Editor {

	// Permanently blocked keywords — no exceptions
	const BLOCKED = [ 'DROP', 'TRUNCATE', 'ALTER', 'CREATE', 'RENAME', 'GRANT', 'REVOKE', 'LOAD_FILE', 'INTO OUTFILE', 'INTO DUMPFILE' ];

	public static function register_ajax() {
		add_action( 'wp_ajax_sgc_pm_sql_run',          [ __CLASS__, 'ajax_run' ] );
		add_action( 'wp_ajax_sgc_pm_sql_save_query',   [ __CLASS__, 'ajax_save_query' ] );
		add_action( 'wp_ajax_sgc_pm_sql_delete_query', [ __CLASS__, 'ajax_delete_query' ] );
		add_action( 'wp_ajax_sgc_pm_sql_get_queries',  [ __CLASS__, 'ajax_get_queries' ] );
		add_action( 'wp_ajax_sgc_pm_sql_inline_edit',  [ __CLASS__, 'ajax_inline_edit' ] );
	}

	private static function verify() {
		check_ajax_referer( 'sgc_pm_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) )
			wp_send_json_error( 'Unauthorized', 403 );
	}

	private static function is_safe( $sql ) {
		$upper = strtoupper( preg_replace( '/\s+/', ' ', trim( $sql ) ) );
		foreach ( self::BLOCKED as $keyword ) {
			// Word boundary check
			if ( preg_match( '/\b' . preg_quote( $keyword, '/' ) . '\b/', $upper ) ) {
				return false;
			}
		}
		return true;
	}

	private static function query_type( $sql ) {
		$first = strtoupper( strtok( trim( $sql ), " \t\n\r" ) );
		return $first; // SELECT, UPDATE, DELETE, INSERT etc.
	}

	public static function ajax_run() {
		self::verify();
		global $wpdb;

		$sql   = trim( wp_unslash( $_POST['sql'] ?? '' ) );
		$token = strtoupper( sanitize_text_field( $_POST['confirm_token'] ?? '' ) );

		if ( empty( $sql ) ) wp_send_json_error( 'Empty query.' );

		// Safety check
		if ( ! self::is_safe( $sql ) ) {
			wp_send_json_error( '🚫 Blocked: DROP, TRUNCATE, ALTER, CREATE, RENAME are not allowed.' );
		}

		$type = self::query_type( $sql );

		// Destructive queries require confirm_token
		if ( in_array( $type, [ 'UPDATE', 'DELETE', 'INSERT', 'REPLACE' ], true ) && 'CONFIRM' !== $token ) {
			// Return preview: how many rows would be affected
			$preview_sql = self::get_preview_sql( $sql, $type );
			$count       = $preview_sql ? (int) $wpdb->get_var( $preview_sql ) : null; // phpcs:ignore

			wp_send_json_success([
				'requires_confirm' => true,
				'type'             => $type,
				'preview_count'    => $count,
				'message'          => "This $type will affect approximately $count row(s). Send confirm_token=CONFIRM to execute.",
			]);
		}

		// Execute
		$start = microtime( true );

		if ( 'SELECT' === $type || 'SHOW' === $type || 'DESCRIBE' === $type || 'EXPLAIN' === $type ) {
			$results = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore
			$elapsed = round( ( microtime( true ) - $start ) * 1000, 2 );

			if ( $wpdb->last_error ) wp_send_json_error( 'SQL Error: ' . $wpdb->last_error );

			self::save_history( $sql, count( $results ), $elapsed );

			wp_send_json_success([
				'type'    => $type,
				'rows'    => $results,
				'count'   => count( $results ),
				'columns' => ! empty( $results ) ? array_keys( $results[0] ) : [],
				'elapsed' => $elapsed,
			]);
		} else {
			$wpdb->query( $sql ); // phpcs:ignore
			$elapsed = round( ( microtime( true ) - $start ) * 1000, 2 );

			if ( $wpdb->last_error ) wp_send_json_error( 'SQL Error: ' . $wpdb->last_error );

			self::save_history( $sql, $wpdb->rows_affected, $elapsed );

			wp_send_json_success([
				'type'     => $type,
				'affected' => $wpdb->rows_affected,
				'elapsed'  => $elapsed,
				'message'  => "$wpdb->rows_affected row(s) affected in {$elapsed}ms",
			]);
		}
	}

	private static function get_preview_sql( $sql, $type ) {
		global $wpdb;
		if ( 'DELETE' === $type ) {
			// Convert DELETE FROM tbl WHERE ... → SELECT COUNT(*) FROM tbl WHERE ...
			$preview = preg_replace( '/^DELETE\s+FROM/i', 'SELECT COUNT(*) FROM', $sql, 1 );
			return $preview !== $sql ? $preview : null;
		}
		if ( 'UPDATE' === $type ) {
			// Extract table and WHERE clause
			if ( preg_match( '/UPDATE\s+(\S+)\s+SET\s+.+?(?:WHERE\s+(.+))?$/is', $sql, $m ) ) {
				$where = ! empty( $m[2] ) ? 'WHERE ' . $m[2] : '';
				return "SELECT COUNT(*) FROM {$m[1]} $where";
			}
		}
		return null;
	}

	private static function save_history( $sql, $rows, $elapsed ) {
		$history   = get_option( 'sgc_pm_sql_history', [] );
		array_unshift( $history, [
			'sql'     => $sql,
			'rows'    => $rows,
			'elapsed' => $elapsed,
			'time'    => current_time( 'mysql' ),
		]);
		update_option( 'sgc_pm_sql_history', array_slice( $history, 0, 20 ) );
	}

	// ── Inline Edit from SQL result ──────────────────────────────────────
	public static function ajax_inline_edit() {
		self::verify();
		global $wpdb;

		$table  = sanitize_text_field( wp_unslash( $_POST['table']  ?? '' ) );
		$col    = sanitize_text_field( wp_unslash( $_POST['col']    ?? '' ) );
		$value  = sanitize_text_field( wp_unslash( $_POST['value']  ?? '' ) );
		$pk_col = sanitize_text_field( wp_unslash( $_POST['pk_col'] ?? 'ID' ) );
		$pk_val = sanitize_text_field( wp_unslash( $_POST['pk_val'] ?? '' ) );

		if ( ! $table || ! $col || ! $pk_val ) wp_send_json_error( 'Invalid params.' );

		// Only allow wp_ prefixed tables
		if ( 0 !== strpos( $table, $wpdb->prefix ) ) {
			wp_send_json_error( 'Only WordPress tables allowed.' );
		}

		$updated = $wpdb->update( $table, [ $col => $value ], [ $pk_col => $pk_val ] ); // phpcs:ignore
		if ( false === $updated ) wp_send_json_error( 'Update failed: ' . $wpdb->last_error );

		wp_send_json_success( [ 'updated' => $updated ] );
	}

	// ── Saved Queries CRUD ───────────────────────────────────────────────
	public static function ajax_save_query() {
		self::verify();
		$name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$sql     = trim( wp_unslash( $_POST['sql'] ?? '' ) );
		if ( ! $name || ! $sql ) wp_send_json_error( 'Name and SQL required.' );

		$queries = get_option( 'sgc_pm_saved_queries', [] );
		$id      = 'q_' . time();
		$queries[ $id ] = [ 'id' => $id, 'name' => $name, 'sql' => $sql, 'created' => current_time( 'mysql' ) ];
		update_option( 'sgc_pm_saved_queries', $queries );

		wp_send_json_success( [ 'id' => $id, 'message' => 'Query saved.' ] );
	}

	public static function ajax_delete_query() {
		self::verify();
		$id      = sanitize_key( $_POST['query_id'] ?? '' );
		$queries = get_option( 'sgc_pm_saved_queries', [] );
		unset( $queries[ $id ] );
		update_option( 'sgc_pm_saved_queries', $queries );
		wp_send_json_success();
	}

	public static function ajax_get_queries() {
		self::verify();
		$queries = get_option( 'sgc_pm_saved_queries', [] );
		$history = get_option( 'sgc_pm_sql_history',   [] );
		wp_send_json_success( [ 'saved' => array_values( $queries ), 'history' => $history ] );
	}
}

SGC_PM_SQL_Editor::register_ajax();
