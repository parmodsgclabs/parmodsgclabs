<?php
defined('ABSPATH') || exit;

/**
 * SGC Product Manager Pro — Integration Status + Changelog
 */
class SGC_PM_Integration {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_changelog_page'], 99);
        // Expose version for other plugins
        add_action('admin_init', function(){
            update_option('sgc_pm_version', SGC_PM_VERSION, false);
            update_option('sgc_pm_active', '1', false);
        });
        // v3.0.6: Fire hook when B2B price meta changes — IIP can listen to alert open PIs
        // pre_update_post_meta captures old value BEFORE it's overwritten
        add_filter( 'update_post_metadata', [ __CLASS__, 'capture_b2b_old_price' ], 10, 5 );
        add_action( 'updated_post_meta', [ __CLASS__, 'on_b2b_price_meta_updated' ], 10, 4 );
        add_action( 'added_post_meta',   [ __CLASS__, 'on_b2b_price_meta_updated' ], 10, 4 );
        // v3.0.6: Listen to CRM new contact — push top products to CRM campaign queue
        add_action( 'sgc_crm_new_contact_added', [ __CLASS__, 'on_crm_new_contact' ], 10, 1 );
        // v3.0.6: WooCommerce order completed → fire product-sold hook for automation rules
        add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'on_wc_order_completed' ], 10, 1 );
    }

    public static function add_changelog_page() {
        add_submenu_page(
            'sgc-product-manager',
            'Changelog & Integration',
            '📋 Changelog',
            'manage_woocommerce',
            'sgc-pm-changelog',
            [__CLASS__, 'render']
        );
    }

    public static function render() {
        $usd_rate = get_option('sgc_pm_usd_rate', 83.5);
        ?>
        <div class="wrap">
        <h1>📋 SGC Product Manager Pro — Changelog & Integration</h1>

        <!-- Ecosystem Status -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:860px;margin-bottom:20px">
          <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px">
            <h3 style="margin-top:0">🔗 SGC Ecosystem</h3>
            <table style="width:100%;font-size:13px;border-collapse:collapse">
              <?php
              $eco = [
                'Manager Pro'         => function_exists('sgclabs_ai_call') ? '✅ Active' : '⭕ Not active',
                'Invoice Pro'         => defined('SGC_IIP_VERSION') ? '✅ v'.SGC_IIP_VERSION : '⭕ Not active',
                'Smart CRM Pro'       => class_exists('SGC_CRM_DB') ? '✅ Active' : '⭕ Not active',
                'USD Rate (shared)'   => 'Rs.'.esc_html($usd_rate),
                'AI Provider'         => ucfirst(get_option('sgc_pm_ai_provider','deepseek')),
                'Default MOQ'         => get_option('sgc_pm_default_moq', 10).' pcs',
                'Dealer Discount'     => get_option('sgc_pm_dealer_discount', 20).'%',
              ];
              foreach($eco as $k=>$v):
              ?>
              <tr style="border-bottom:1px solid #f0f0f0">
                <td style="padding:7px 0;color:#555"><?php echo esc_html($k); ?></td>
                <td style="padding:7px 0;font-weight:600"><?php echo $v; ?></td>
              </tr>
              <?php endforeach; ?>
            </table>
          </div>
          <div style="background:#e8f5e9;border:1px solid #a5d6a7;border-radius:8px;padding:16px">
            <h3 style="margin-top:0;color:#1b5e20">ℹ️ Integration Notes</h3>
            <div style="font-size:12px;color:#2e7d32;line-height:1.8">
              <strong>Invoice Pro reads from PM Pro:</strong><br>
              • sgc_pm_usd_rate → shared USD rate<br>
              • sgc_pm_default_moq → minimum order qty<br>
              • sgc_pm_dealer_discount → B2B discount %<br>
              • _sgclabs_b2b_pricing → per-product B2B price<br>
              • _export_moq → per-product MOQ<br><br>
              <strong>No changes needed in PM Pro settings.</strong><br>
              Invoice Pro reads these automatically.
            </div>
          </div>
        </div>

        <!-- DB Migration -->
        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px;max-width:860px;margin-bottom:20px">
          <h3 style="margin-top:0">🔧 Database Status</h3>
          <?php
          global $wpdb;
          $tables = ['sgcpm_competitors','sgcpm_competitor_data','sgcpm_automations','sgcpm_logs','sgcpm_keywords'];
          foreach($tables as $t){
              $tn = $wpdb->prefix.$t;
              $exists = $wpdb->get_var("SHOW TABLES LIKE '$tn'") === $tn;
              echo '<div style="font-size:12px;padding:4px 0">'.($exists?'✅':'❌').' '.$tn.'</div>';
          }
          ?>
          <button class="button button-primary" onclick="location.reload()" style="margin-top:10px">🔄 Refresh Status</button>
        </div>

        <!-- Changelog -->
        <?php
        $changelog = [
            ['v2.0.2','19 Apr 2026','Integration','#7c3aed',
             ['🔗 sgc_pm_usd_rate — now read by Invoice Pro automatically',
              '🔗 sgc_pm_default_moq — MOQ auto-populated in Invoice Pro product search',
              '🔗 sgc_pm_dealer_discount — B2B discount used in Invoice Pro pricing',
              '🔗 _sgclabs_b2b_pricing meta — B2B per-product price shown in Invoice Pro',
              '🔗 _export_moq meta — per-product MOQ shown in Invoice Pro',
             ]],
            ['v2.0.2','18 Apr 2026','Previous','#1a1a2e',
             ['Category Fixer — tab-tools.php IIFE fix',
              'Category Reassign in bulk bar',
              'AI auto-scan for wrong categories (DeepSeek)',
              'Enterprise: 11 tabs, AI Studio, Competitor Intel, SQL Editor',
             ]],
        ];
        foreach($changelog as $e): ?>
        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden;margin-bottom:12px;max-width:860px">
          <div style="background:<?php echo esc_attr($e[3]); ?>;padding:10px 16px;display:flex;align-items:center;gap:10px">
            <span style="background:rgba(255,255,255,.2);color:#fff;font-size:12px;font-weight:700;padding:2px 10px;border-radius:10px"><?php echo esc_html($e[0]); ?></span>
            <span style="color:rgba(255,255,255,.7);font-size:11px"><?php echo esc_html($e[1]); ?></span>
            <span style="margin-left:auto;background:rgba(255,255,255,.15);color:#fff;font-size:10px;padding:1px 8px;border-radius:8px"><?php echo esc_html($e[2]); ?></span>
          </div>
          <div style="padding:12px 16px;background:#fafafa">
            <?php foreach($e[4] as $ch): ?>
            <div style="font-size:12px;color:#333;padding:4px 0;border-bottom:1px solid #f0f0f0"><?php echo esc_html($ch); ?></div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php
    }
    // ── v3.0.6: Capture old B2B price BEFORE update (filter runs before DB write) ─
    private static $b2b_old_prices = [];

    public static function capture_b2b_old_price( $check, int $object_id, string $meta_key, $new_value, $prev_value ) {
        if ( '_sgclabs_b2b_pricing' === $meta_key ) {
            self::$b2b_old_prices[ $object_id ] = $prev_value;
        }
        return $check; // null = proceed normally
    }

    // ── v3.0.6: B2B price meta changed → fire hook so IIP can alert open PIs ──
    public static function on_b2b_price_meta_updated( $meta_id, int $object_id, string $meta_key, $new_value ): void {
        if ( '_sgclabs_b2b_pricing' !== $meta_key ) return;
        if ( 'product' !== get_post_type( $object_id ) ) return;
        $old_value = self::$b2b_old_prices[ $object_id ] ?? null;
        // Skip if no actual change
        if ( $old_value !== null && (string) $old_value === (string) $new_value ) return;
        do_action( 'sgc_pm_b2b_price_changed', $object_id, $old_value, $new_value );
        unset( self::$b2b_old_prices[ $object_id ] );
    }

    // ── v3.0.6: New CRM contact → queue top-product promo in CRM ─────────────
    public static function on_crm_new_contact( int $contact_id ): void {
        if ( ! class_exists( 'SGC_CRM_DB' ) ) return;
        global $wpdb;

        // Get top 3 published products by price (B2B price preferred)
        $products = $wpdb->get_results( // phpcs:ignore
            "SELECT ID, post_title FROM {$wpdb->posts}
             WHERE post_type='product' AND post_status='publish'
             ORDER BY ID DESC LIMIT 3",
            ARRAY_A
        );
        if ( empty( $products ) ) return;

        $prod_list = implode( ', ', array_column( $products, 'post_title' ) );
        $ct        = SGC_CRM_DB::contacts();
        $contact   = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore
            "SELECT email, first_name, segment FROM `{$ct}` WHERE id=%d LIMIT 1", $contact_id
        ), ARRAY_A );
        if ( ! $contact ) return;

        // Only fire for international/new_lead contacts — not domestic/vip who already know us
        if ( in_array( $contact['segment'] ?? '', [ 'domestic', 'vip' ], true ) ) return;

        // Add to CRM notes so next campaign can reference it
        $wpdb->update( $ct, [ // phpcs:ignore
            'notes'      => trim( ( $contact['notes'] ?? '' ) . "\nTop products: " . $prod_list ),
            'updated_at' => current_time( 'mysql' ),
        ], [ 'id' => $contact_id ] );

        // Fire a hook so CRM Automation can trigger a welcome sequence
        do_action( 'sgc_pm_new_contact_products_ready', $contact_id, $products );
    }

    // ── v3.0.6: WooCommerce order completed → fire product-sold action ────────
    public static function on_wc_order_completed( int $order_id ): void {
        if ( ! function_exists( 'wc_get_order' ) ) return;
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;
        foreach ( $order->get_items() as $item ) {
            $product_id = (int) $item->get_product_id();
            $qty        = (int) $item->get_quantity();
            if ( $product_id > 0 ) {
                // Increment sold count meta for automation rules
                $sold = (int) get_post_meta( $product_id, '_sgc_pm_wc_sold_count', true );
                update_post_meta( $product_id, '_sgc_pm_wc_sold_count', $sold + $qty );
                do_action( 'sgc_pm_product_sold', $product_id, $order_id, $qty );
            }
        }
    }

}

SGC_PM_Integration::init();
