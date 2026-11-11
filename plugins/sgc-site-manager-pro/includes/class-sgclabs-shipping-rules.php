<?php
/**
 * SGC LABS® Automatic Shipping Rules Module (OOP)
 *
 * WooCommerce shipping rules engine:
 * - Weight-based flat rate
 * - Price threshold free shipping
 * - Category-based rules
 * - Zone-based (India / International)
 * - Admin CRUD with toggle/priority
 * - Bulk apply to all products
 *
 * @package SGCLabs_Manager
 * @since   6.6.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;


if ( ! defined( 'ABSPATH' ) ) exit;

class SGCLabs_Shipping_Rules extends SGCLabs_Module {

    protected $module_id    = 'shipping-rules';
    protected $module_label = 'Shipping Rules';

    /** @var string Custom DB table name (without prefix). */
    private $table = 'sgclabs_shipping_rules';

    /* ──────────────────────────────────────────────────────────
     * BOOT
     * ────────────────────────────────────────────────────────*/
    public function boot(): void {
        // Create DB table on first run.
        add_action( 'admin_init', [ $this, 'maybe_create_table' ] );

        // Admin AJAX handlers.
        add_action( 'wp_ajax_sgclabs_shipping_rules_save',   [ $this, 'ajax_save_rule' ] );
        add_action( 'wp_ajax_sgclabs_shipping_rules_delete', [ $this, 'ajax_delete_rule' ] );
        add_action( 'wp_ajax_sgclabs_shipping_rules_toggle', [ $this, 'ajax_toggle_rule' ] );
        add_action( 'wp_ajax_sgclabs_shipping_rules_list',   [ $this, 'ajax_list_rules' ] );

        // WooCommerce: Apply rules to shipping calculation.
        add_filter( 'woocommerce_package_rates', [ $this, 'apply_shipping_rules' ], 100, 2 );

        // Admin menu (registered from admin-menu.php via callback).
    }

    /* ──────────────────────────────────────────────────────────
     * DATABASE TABLE
     * ────────────────────────────────────────────────────────*/
    public function maybe_create_table(): void {
        global $wpdb;
        $tbl     = $wpdb->prefix . $this->table;
        $charset = $wpdb->get_charset_collate();

        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tbl}'" ) === $tbl ) return;

        $sql = "CREATE TABLE {$tbl} (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            rule_name   VARCHAR(200) NOT NULL DEFAULT '',
            rule_type   VARCHAR(50) NOT NULL DEFAULT 'free_shipping',
            conditions  LONGTEXT NOT NULL,
            rate_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            priority    INT NOT NULL DEFAULT 10,
            is_active   TINYINT(1) NOT NULL DEFAULT 1,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_active_priority (is_active, priority)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    private function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . $this->table;
    }

    /* ──────────────────────────────────────────────────────────
     * ADMIN PAGE
     * ────────────────────────────────────────────────────────*/
    public function admin_page(): void {
        if ( ! $this->current_user_can_manage() ) {
            wp_die( 'Unauthorized access' );
        }

        // Check WooCommerce.
        $woo_active = class_exists( 'WooCommerce' );

        // Get existing rules.
        $rules = $this->get_all_rules();

        // Get product categories for dropdown.
        $categories = [];
        if ( $woo_active && taxonomy_exists( 'product_cat' ) ) {
            $terms = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
            if ( ! is_wp_error( $terms ) ) {
                foreach ( $terms as $t ) {
                    $categories[] = [ 'id' => $t->term_id, 'name' => $t->name ];
                }
            }
        }

        // WooCommerce shipping zones.
        $zones = [];
        if ( $woo_active && class_exists( 'WC_Shipping_Zones' ) ) {
            foreach ( \WC_Shipping_Zones::get_zones() as $z ) {
                $zones[] = [ 'id' => $z['id'], 'name' => $z['zone_name'] ];
            }
        }

        $this->render_template( 'shipping-rules', [
            'rules'      => $rules,
            'categories' => $categories,
            'zones'      => $zones,
            'woo_active' => $woo_active,
            'nonce'      => wp_create_nonce( 'sgclabs_nonce' ),
            'brand'      => get_option( 'sgclabs_claude_brand_color', '#1a1a2e' ),
            'accent'     => get_option( 'sgclabs_claude_accent_color', '#27ae60' ),
        ] );
    }

    /* ──────────────────────────────────────────────────────────
     * GET ALL RULES
     * ────────────────────────────────────────────────────────*/
    public function get_all_rules(): array {
        global $wpdb;
        $tbl = $this->table_name();
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tbl}'" ) !== $tbl ) return [];

        $rows = $wpdb->get_results( "SELECT * FROM {$tbl} ORDER BY priority ASC, id ASC", ARRAY_A );
        foreach ( $rows as &$r ) {
            $r['conditions'] = json_decode( $r['conditions'], true ) ?: [];
        }
        return $rows ?: [];
    }

    /* ──────────────────────────────────────────────────────────
     * AJAX: Save/Create Rule
     * ────────────────────────────────────────────────────────*/
    public function ajax_save_rule(): void {
        check_ajax_referer( 'sgclabs_nonce', 'nonce' );
        if ( ! $this->current_user_can_manage() ) { wp_send_json_error( 'Unauthorized' ); return; }

        global $wpdb;
        $tbl = $this->table_name();

        $id = intval( $_POST['rule_id'] ?? 0 );

        $data = [
            'rule_name'   => sanitize_text_field( wp_unslash( $_POST['rule_name'] ?? '' ) ),
            'rule_type'   => sanitize_text_field( $_POST['rule_type'] ?? 'free_shipping' ),
            'rate_amount' => floatval( $_POST['rate_amount'] ?? 0 ),
            'priority'    => intval( $_POST['priority'] ?? 10 ),
            'is_active'   => intval( $_POST['is_active'] ?? 1 ),
            'conditions'  => wp_json_encode( [
                'min_weight'    => floatval( $_POST['cond_min_weight'] ?? 0 ),
                'max_weight'    => floatval( $_POST['cond_max_weight'] ?? 0 ),
                'min_price'     => floatval( $_POST['cond_min_price'] ?? 0 ),
                'max_price'     => floatval( $_POST['cond_max_price'] ?? 0 ),
                'categories'    => array_map( 'intval', (array) ( $_POST['cond_categories'] ?? [] ) ),
                'zone'          => sanitize_text_field( $_POST['cond_zone'] ?? 'all' ),
                'country_codes' => sanitize_text_field( $_POST['cond_country_codes'] ?? '' ),
            ] ),
        ];

        if ( empty( $data['rule_name'] ) ) {
            wp_send_json_error( 'Rule name is required.' );
            return;
        }

        if ( $id > 0 ) {
            $wpdb->update( $tbl, $data, [ 'id' => $id ], null, [ '%d' ] );
            wp_send_json_success( [ 'msg' => 'Rule updated.', 'id' => $id ] );
        } else {
            $wpdb->insert( $tbl, $data );
            wp_send_json_success( [ 'msg' => 'Rule created.', 'id' => $wpdb->insert_id ] );
        }
    }

    /* ──────────────────────────────────────────────────────────
     * AJAX: Delete Rule
     * ────────────────────────────────────────────────────────*/
    public function ajax_delete_rule(): void {
        check_ajax_referer( 'sgclabs_nonce', 'nonce' );
        if ( ! $this->current_user_can_manage() ) { wp_send_json_error( 'Unauthorized' ); return; }

        $id = intval( $_POST['rule_id'] ?? 0 );
        if ( ! $id ) { wp_send_json_error( 'Invalid ID' ); return; }

        global $wpdb;
        $wpdb->delete( $this->table_name(), [ 'id' => $id ], [ '%d' ] );
        wp_send_json_success( [ 'msg' => 'Rule deleted.' ] );
    }

    /* ──────────────────────────────────────────────────────────
     * AJAX: Toggle Rule Active/Inactive
     * ────────────────────────────────────────────────────────*/
    public function ajax_toggle_rule(): void {
        check_ajax_referer( 'sgclabs_nonce', 'nonce' );
        if ( ! $this->current_user_can_manage() ) { wp_send_json_error( 'Unauthorized' ); return; }

        $id = intval( $_POST['rule_id'] ?? 0 );
        if ( ! $id ) { wp_send_json_error( 'Invalid ID' ); return; }

        global $wpdb;
        $tbl     = $this->table_name();
        $current = $wpdb->get_var( $wpdb->prepare( "SELECT is_active FROM {$tbl} WHERE id = %d", $id ) );
        $new_val = $current === '1' ? 0 : 1;
        $wpdb->update( $tbl, [ 'is_active' => $new_val ], [ 'id' => $id ] );
        wp_send_json_success( [ 'active' => $new_val ] );
    }

    /* ──────────────────────────────────────────────────────────
     * AJAX: List Rules (for refresh)
     * ────────────────────────────────────────────────────────*/
    public function ajax_list_rules(): void {
        check_ajax_referer( 'sgclabs_nonce', 'nonce' );
        if ( ! $this->current_user_can_manage() ) { wp_send_json_error( 'Unauthorized' ); return; }

        wp_send_json_success( [ 'rules' => $this->get_all_rules() ] );
    }

    /* ──────────────────────────────────────────────────────────
     * WOOCOMMERCE: Apply Shipping Rules
     * ────────────────────────────────────────────────────────*/
    public function apply_shipping_rules( $rates, $package ): array {
        if ( empty( $rates ) ) return $rates;

        $rules = $this->get_all_rules();
        if ( empty( $rules ) ) return $rates;

        // Calculate cart totals.
        $cart_total  = 0;
        $cart_weight = 0;
        $cart_cats   = [];

        foreach ( $package['contents'] as $item ) {
            $cart_total += $item['line_total'];
            $product     = $item['data'];
            if ( $product ) {
                $cart_weight += floatval( $product->get_weight() ) * $item['quantity'];
                $terms = wp_get_post_terms( $item['product_id'], 'product_cat', [ 'fields' => 'ids' ] );
                if ( ! is_wp_error( $terms ) ) {
                    $cart_cats = array_merge( $cart_cats, $terms );
                }
            }
        }
        $cart_cats = array_unique( $cart_cats );

        // Destination country.
        $dest_country = $package['destination']['country'] ?? '';

        // Check rules.
        foreach ( $rules as $rule ) {
            if ( ! $rule['is_active'] ) continue;

            $cond    = $rule['conditions'];
            $matches = true;

            // Weight check.
            if ( ! empty( $cond['min_weight'] ) && $cart_weight < $cond['min_weight'] ) $matches = false;
            if ( ! empty( $cond['max_weight'] ) && $cart_weight > $cond['max_weight'] ) $matches = false;

            // Price check.
            if ( ! empty( $cond['min_price'] ) && $cart_total < $cond['min_price'] ) $matches = false;
            if ( ! empty( $cond['max_price'] ) && $cart_total > $cond['max_price'] ) $matches = false;

            // Category check.
            if ( ! empty( $cond['categories'] ) ) {
                $overlap = array_intersect( $cart_cats, $cond['categories'] );
                if ( empty( $overlap ) ) $matches = false;
            }

            // Zone/country check.
            if ( ! empty( $cond['zone'] ) && $cond['zone'] !== 'all' ) {
                if ( $cond['zone'] === 'india' && $dest_country !== 'IN' ) $matches = false;
                if ( $cond['zone'] === 'international' && $dest_country === 'IN' ) $matches = false;
            }
            if ( ! empty( $cond['country_codes'] ) ) {
                $allowed = array_map( 'trim', explode( ',', strtoupper( $cond['country_codes'] ) ) );
                if ( ! in_array( strtoupper( $dest_country ), $allowed, true ) ) $matches = false;
            }

            if ( ! $matches ) continue;

            // Apply rule.
            switch ( $rule['rule_type'] ) {
                case 'free_shipping':
                    // Set all rates to 0.
                    foreach ( $rates as $rate_key => $rate ) {
                        $rates[ $rate_key ]->cost = 0;
                        $rates[ $rate_key ]->label = $rule['rule_name'] ?: 'Free Shipping';
                    }
                    break;

                case 'flat_rate':
                    // Override with flat rate.
                    foreach ( $rates as $rate_key => $rate ) {
                        $rates[ $rate_key ]->cost  = $rule['rate_amount'];
                        $rates[ $rate_key ]->label = $rule['rule_name'] ?: 'Flat Rate';
                    }
                    break;

                case 'per_kg':
                    // Rate per kg.
                    $total_shipping = $cart_weight * $rule['rate_amount'];
                    foreach ( $rates as $rate_key => $rate ) {
                        $rates[ $rate_key ]->cost  = $total_shipping;
                        $rates[ $rate_key ]->label = $rule['rule_name'] ?: 'Weight-Based Shipping';
                    }
                    break;

                case 'percentage':
                    // Percentage of cart total.
                    $total_shipping = $cart_total * ( $rule['rate_amount'] / 100 );
                    foreach ( $rates as $rate_key => $rate ) {
                        $rates[ $rate_key ]->cost  = round( $total_shipping, 2 );
                        $rates[ $rate_key ]->label = $rule['rule_name'] ?: 'Percentage Shipping';
                    }
                    break;
            }

            // First matching rule wins (priority-based).
            break;
        }

        return $rates;
    }
}
