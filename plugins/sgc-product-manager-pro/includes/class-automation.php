<?php
defined( 'ABSPATH' ) || exit;

class SGC_PM_Automation {

	public static function register_ajax() {
		$actions = [
			'sgc_pm_auto_save_rule'    => 'ajax_save_rule',
			'sgc_pm_auto_delete_rule'  => 'ajax_delete_rule',
			'sgc_pm_auto_toggle_rule'  => 'ajax_toggle_rule',
			'sgc_pm_auto_get_rules'    => 'ajax_get_rules',
			'sgc_pm_auto_run_now'      => 'ajax_run_now',
			'sgc_pm_auto_test_rule'    => 'ajax_test_rule',
		];
		foreach ( $actions as $action => $method ) {
			add_action( 'wp_ajax_' . $action, [ __CLASS__, $method ] );
		}

		// WooCommerce hooks for automation triggers
		add_action( 'woocommerce_product_set_stock', [ __CLASS__, 'on_stock_change' ] );
		add_action( 'save_post_product',             [ __CLASS__, 'on_product_save' ], 20, 2 );
		add_action( 'woocommerce_new_product',       [ __CLASS__, 'on_new_product'  ] );
	}

	private static function verify() {
		check_ajax_referer( 'sgc_pm_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) )
			wp_send_json_error( 'Unauthorized', 403 );
	}

	// ── AJAX handlers ─────────────────────────────────────────────────────
	public static function ajax_save_rule() {
		self::verify();
		global $wpdb;

		$id     = (int) ( $_POST['rule_id'] ?? 0 );
		$name   = sanitize_text_field( $_POST['name'] ?? '' );
		$trigger= sanitize_key( $_POST['trigger_type'] ?? '' );
		$cond   = wp_json_encode( (array) ( $_POST['conditions'] ?? [] ) );
		$action = sanitize_key( $_POST['action_type'] ?? '' );
		$adata  = wp_json_encode( (array) ( $_POST['action_data'] ?? [] ) );

		if ( ! $name || ! $trigger || ! $action ) wp_send_json_error( 'Name, trigger, and action required.' );

		$table = $wpdb->prefix . 'sgcpm_automations';
		if ( $id ) {
			$wpdb->update( $table, compact( 'name', 'cond', 'action', 'adata' ) + // phpcs:ignore
				[ 'trigger_type' => $trigger, 'action_type' => $action, 'conditions' => $cond, 'action_data' => $adata ],
				[ 'id' => $id ]
			);
		} else {
			$wpdb->insert( $table, [ // phpcs:ignore
				'name'         => $name,
				'trigger_type' => $trigger,
				'conditions'   => $cond,
				'action_type'  => $action,
				'action_data'  => $adata,
				'status'       => 'active',
			] );
			$id = $wpdb->insert_id;
		}

		SGC_PM_Logger::log( 'automation_save', 'automation', $id, "Rule saved: $name" );
		wp_send_json_success( [ 'id' => $id, 'message' => 'Rule saved.' ] );
	}

	public static function ajax_delete_rule() {
		self::verify();
		global $wpdb;
		$id = (int) ( $_POST['rule_id'] ?? 0 );
		$wpdb->delete( $wpdb->prefix . 'sgcpm_automations', [ 'id' => $id ] ); // phpcs:ignore
		wp_send_json_success();
	}

	public static function ajax_toggle_rule() {
		self::verify();
		global $wpdb;
		$id     = (int) ( $_POST['rule_id'] ?? 0 );
		$status = sanitize_key( $_POST['status'] ?? 'active' );
		$wpdb->update( $wpdb->prefix . 'sgcpm_automations', [ 'status' => $status ], [ 'id' => $id ] ); // phpcs:ignore
		wp_send_json_success();
	}

	public static function ajax_get_rules() {
		self::verify();
		global $wpdb;
		$rules = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sgcpm_automations ORDER BY created_at DESC" ); // phpcs:ignore
		wp_send_json_success( $rules );
	}

	public static function ajax_run_now() {
		self::verify();
		$rule_id = (int) ( $_POST['rule_id'] ?? 0 );
		$result  = self::run_rule( $rule_id );
		wp_send_json_success( [ 'message' => 'Rule executed. Affected: ' . $result ] );
	}

	public static function ajax_test_rule() {
		self::verify();
		global $wpdb;
		$rule_id = (int) ( $_POST['rule_id'] ?? 0 );
		$rule    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sgcpm_automations WHERE id=%d", $rule_id ) ); // phpcs:ignore
		if ( ! $rule ) wp_send_json_error( 'Rule not found.' );

		// Count products that would be affected
		$count = self::count_affected( $rule );
		wp_send_json_success( [ 'would_affect' => $count, 'message' => "This rule would affect $count product(s) right now." ] );
	}

	// ── Cron — run all pending automations ──────────────────────────────
	public static function run_pending() {
		if ( ! get_option( 'sgc_pm_automation_enabled', '1' ) ) return;

		global $wpdb;
		$rules = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sgcpm_automations WHERE status='active' AND trigger_type IN ('cron_daily','cron_weekly','cron_hourly')" ); // phpcs:ignore

		foreach ( $rules as $rule ) {
			self::run_rule( $rule->id );
		}
	}

	// ── Run one rule ─────────────────────────────────────────────────────
	private static function run_rule( $rule_id ) {
		global $wpdb;
		$rule = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sgcpm_automations WHERE id=%d", $rule_id ) ); // phpcs:ignore
		if ( ! $rule || $rule->status !== 'active' ) return 0;

		$conditions   = json_decode( $rule->conditions, true ) ?: [];
		$action_data  = json_decode( $rule->action_data, true ) ?: [];
		$products     = self::get_matching_products( $conditions );
		$affected     = 0;

		foreach ( $products as $product_id ) {
			$did = self::execute_action( $rule->action_type, $action_data, $product_id );
			if ( $did ) $affected++;
		}

		$wpdb->update( $wpdb->prefix . 'sgcpm_automations', // phpcs:ignore
			[ 'run_count' => $rule->run_count + 1, 'last_run' => current_time( 'mysql' ) ],
			[ 'id' => $rule_id ]
		);

		SGC_PM_Logger::log( 'automation_run', 'automation', $rule_id, "Rule '{$rule->name}' ran. Affected: $affected" );
		return $affected;
	}

	private static function get_matching_products( $conditions ) {
		$args = [ 'post_type' => 'product', 'posts_per_page' => -1, 'fields' => 'ids', 'post_status' => 'any' ];

		foreach ( $conditions as $cond ) {
			$type  = $cond['type'] ?? '';
			$op    = $cond['operator'] ?? 'eq';
			$value = $cond['value'] ?? '';

			if ( 'stock_qty' === $type ) {
				$args['meta_query'][] = [
					'key'     => '_stock',
					'value'   => (int) $value,
					'compare' => 'lt' === $op ? '<' : ( 'gt' === $op ? '>' : '=' ),
					'type'    => 'NUMERIC',
				];
			} elseif ( 'stock_status' === $type ) {
				$args['meta_query'][] = [ 'key' => '_stock_status', 'value' => $value ];
			} elseif ( 'category' === $type ) {
				$args['tax_query'][] = [ 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => (int) $value ];
			} elseif ( 'price_lt' === $type ) {
				$args['meta_query'][] = [ 'key' => '_regular_price', 'value' => floatval( $value ), 'compare' => '<', 'type' => 'NUMERIC' ];
			} elseif ( 'status' === $type ) {
				$args['post_status'] = $value;
			} elseif ( 'no_sku' === $type ) {
				$args['meta_query'][] = [ 'relation' => 'OR',
					[ 'key' => '_sku', 'compare' => 'NOT EXISTS' ],
					[ 'key' => '_sku', 'value' => '', 'compare' => '=' ],
				];
			} elseif ( 'no_image' === $type ) {
				$args['meta_query'][] = [ 'relation' => 'OR',
					[ 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ],
					[ 'key' => '_thumbnail_id', 'value' => '', 'compare' => '=' ],
				];
			} elseif ( 'no_description' === $type ) {
				add_filter( 'posts_where', function( $where ) { return $where . " AND post_content = ''"; } );
			}
		}

		return get_posts( $args );
	}

	private static function count_affected( $rule ) {
		$conditions = json_decode( $rule->conditions, true ) ?: [];
		return count( self::get_matching_products( $conditions ) );
	}

	private static function execute_action( $action_type, $action_data, $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) return false;

		switch ( $action_type ) {
			case 'set_draft':
				wp_update_post( [ 'ID' => $product_id, 'post_status' => 'draft' ] );
				return true;

			case 'set_published':
				wp_update_post( [ 'ID' => $product_id, 'post_status' => 'publish' ] );
				return true;

			case 'set_out_of_stock':
				$product->set_stock_status( 'outofstock' );
				$product->save();
				return true;

			case 'set_in_stock':
				$product->set_stock_status( 'instock' );
				$product->save();
				return true;

			case 'adjust_price_pct':
				$pct = floatval( $action_data['pct'] ?? 0 );
				$current = floatval( $product->get_regular_price() ?: 0 );
				$new = max( 0, $current * ( 1 + $pct / 100 ) );
				$product->set_regular_price( wc_format_decimal( $new ) );
				$product->save();
				return true;

			case 'set_sale_price_pct':
				$pct = floatval( $action_data['pct'] ?? 10 );
				$regular = floatval( $product->get_regular_price() ?: 0 );
				$sale = max( 0, $regular * ( 1 - $pct / 100 ) );
				$product->set_sale_price( wc_format_decimal( $sale ) );
				$product->save();
				return true;

			case 'remove_sale_price':
				$product->set_sale_price( '' );
				$product->save();
				return true;

			case 'ai_generate_desc':
				if ( ! class_exists( 'SGC_PM_AI_Engine' ) ) return false;
				$_POST['product_id'] = $product_id;
				$_POST['tone']       = $action_data['tone'] ?? 'b2b_formal';
				$_POST['desc_type']  = $action_data['desc_type'] ?? 'both';
				$_POST['keywords']   = '';

				$cats   = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'names' ] );
				$system = 'Expert SEO copywriter for ' . get_option( 'sgc_pm_ai_brand', 'SGC LABS®' );
				$prompt = "Product: {$product->get_name()}\nCategory: " . implode( ', ', is_array( $cats ) ? $cats : [] ) . "\nReturn JSON: {\"main_description\":\"...\",\"short_description\":\"...\",\"seo_title\":\"...\",\"meta_description\":\"...\",\"focus_keyword\":\"...\"}";
				$result = SGC_PM_AI_Engine::call( $prompt, $system, 800 );
				if ( is_wp_error( $result ) ) return false;
				$parsed = json_decode( preg_replace( '/```(?:json)?|```/', '', $result ), true );
				if ( ! $parsed ) return false;
				if ( ! empty( $parsed['main_description'] ) ) wp_update_post( [ 'ID' => $product_id, 'post_content' => wp_kses_post( $parsed['main_description'] ) ] );
				if ( ! empty( $parsed['short_description'] ) ) wp_update_post( [ 'ID' => $product_id, 'post_excerpt' => sanitize_textarea_field( $parsed['short_description'] ) ] );
				if ( ! empty( $parsed['focus_keyword'] ) ) { update_post_meta( $product_id, 'rank_math_focus_keyword', sanitize_text_field( $parsed['focus_keyword'] ) ); }
				return true;

			case 'send_email':
				$to      = sanitize_email( $action_data['email'] ?? get_option( 'sgc_pm_admin_email', get_option( 'admin_email' ) ) );
				$subject = str_replace( '{product}', $product->get_name(), $action_data['subject'] ?? 'Product Alert: {product}' );
				$body    = str_replace(
					[ '{product}', '{stock}', '{price}' ],
					[ $product->get_name(), $product->get_stock_quantity(), $product->get_regular_price() ],
					$action_data['body'] ?? 'Product {product} needs attention. Stock: {stock}'
				);
				wp_mail( $to, $subject, $body );
				return true;

			case 'assign_category':
				$cat_id = (int) ( $action_data['cat_id'] ?? 0 );
				if ( $cat_id ) wp_set_post_terms( $product_id, [ $cat_id ], 'product_cat', true );
				return true;
		}
		return false;
	}

	// ── Event triggers ────────────────────────────────────────────────────
	public static function on_stock_change( $product ) {
		if ( ! get_option( 'sgc_pm_automation_enabled', '1' ) ) return;
		$stock = $product->get_stock_quantity();
		$alert = (int) get_option( 'sgc_pm_stock_alert_qty', 5 );

		global $wpdb;
		$rules = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sgcpm_automations WHERE status='active' AND trigger_type='stock_below'" ); // phpcs:ignore
		foreach ( $rules as $rule ) {
			$conds = json_decode( $rule->conditions, true ) ?: [];
			foreach ( $conds as $c ) {
				if ( 'stock_qty' === ( $c['type'] ?? '' ) && $stock !== null && $stock < (int) ( $c['value'] ?? $alert ) ) {
					self::execute_action( $rule->action_type, json_decode( $rule->action_data, true ) ?: [], $product->get_id() );
				}
			}
		}
	}

	public static function on_new_product( $product_id ) {
		if ( ! get_option( 'sgc_pm_automation_enabled', '1' ) ) return;

		global $wpdb;
		$rules = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sgcpm_automations WHERE status='active' AND trigger_type='new_product'" ); // phpcs:ignore
		foreach ( $rules as $rule ) {
			// Small delay for product to be fully saved
			wp_schedule_single_event( time() + 10, 'sgc_pm_run_single_rule', [ $rule->id, $product_id ] );
		}
	}

	public static function on_product_save( $product_id, $post ) {
		if ( 'product' !== $post->post_type || wp_is_post_autosave( $product_id ) ) return;
		// Handled by on_new_product for new products
	}
}

add_action( 'sgc_pm_run_single_rule', function( $rule_id, $product_id ) {
	// Run automation for single product
}, 10, 2 );

SGC_PM_Automation::register_ajax();
