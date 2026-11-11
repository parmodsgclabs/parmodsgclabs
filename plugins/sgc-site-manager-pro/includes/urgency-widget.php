<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================
// SGC LABS® — URGENCY WIDGET v6.8.0 [2026-04-08]
// Social proof + scarcity = +30% conversion
// Features:
//  1. Live viewers count (realistic random)
//  2. Real stock quantity from WooCommerce
//  3. Recent orders count (last 7 days — real data)
//  4. Ships in X days message
//  5. Per-product ON/OFF
//  6. Admin settings
// ============================================================

// ── FRONTEND: Render on product page ─────────────────────────
add_action( 'woocommerce_single_product_summary', 'sgclabs_urgency_render', 15 );
function sgclabs_urgency_render() {
    if ( get_option( 'sgclabs_urgency_active', '1' ) !== '1' ) return;

    global $product;
    if ( ! $product ) return;

    $pid = $product->get_id();

    // Per-product override
    $disabled = get_post_meta( $pid, '_sgclabs_urgency_disabled', true );
    if ( $disabled === '1' ) return;

    // ── Real stock ──────────────────────────────────────────
    $stock_qty   = null;
    $show_stock  = get_option( 'sgclabs_urgency_show_stock', '1' ) === '1';
    $stock_limit = (int) get_option( 'sgclabs_urgency_stock_limit', 50 );

    if ( $show_stock && $product->managing_stock() ) {
        $real_stock = $product->get_stock_quantity();
        if ( $real_stock !== null && $real_stock <= $stock_limit && $real_stock > 0 ) {
            $stock_qty = $real_stock;
        }
    }

    // ── Real orders last 7 days ─────────────────────────────
    $show_orders  = get_option( 'sgclabs_urgency_show_orders', '1' ) === '1';
    $orders_count = 0;
    if ( $show_orders ) {
        $cache_key    = 'sgclabs_urgency_orders_' . $pid;
        $orders_count = get_transient( $cache_key );
        if ( $orders_count === false ) {
            global $wpdb;
            $orders_count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(DISTINCT oi.order_id)
                 FROM {$wpdb->prefix}woocommerce_order_items oi
                 INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
                   ON oi.order_item_id = oim.order_item_id
                 INNER JOIN {$wpdb->posts} p
                   ON oi.order_id = p.ID
                 WHERE oim.meta_key = '_product_id'
                   AND oim.meta_value = %d
                   AND p.post_status IN ('wc-completed','wc-processing')
                   AND p.post_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                $pid
            ) );
            set_transient( $cache_key, $orders_count, HOUR_IN_SECONDS );
        }
    }

    // ── Viewers count — realistic seeded random ─────────────
    $show_viewers = get_option( 'sgclabs_urgency_show_viewers', '1' ) === '1';
    $viewers_min  = (int) get_option( 'sgclabs_urgency_viewers_min', 8 );
    $viewers_max  = (int) get_option( 'sgclabs_urgency_viewers_max', 28 );

    // ── Ships in ───────────────────────────────────────────
    $show_ships   = get_option( 'sgclabs_urgency_show_ships', '1' ) === '1';
    $ships_days   = (int) get_option( 'sgclabs_urgency_ships_days', 2 );

    // Check if anything to show
    $has_content = $show_viewers || ( $show_stock && $stock_qty ) || ( $show_orders && $orders_count > 0 ) || $show_ships;
    if ( ! $has_content ) return;

    $accent = get_option( 'sgclabs_urgency_accent', '#c62828' );
    ?>
    <div class="sgclabs-urgency-widget" id="sgc-urgency-<?php echo $pid; ?>">
        <?php if ( $show_viewers ): ?>
        <div class="sgc-urg-item sgc-urg-viewers">
            <span class="sgc-urg-dot sgc-urg-dot-pulse"></span>
            <span class="sgc-urg-text">
                <strong id="sgc-urg-viewers-<?php echo $pid; ?>"><?php echo rand( $viewers_min, $viewers_max ); ?></strong>
                people viewing this right now
            </span>
        </div>
        <?php endif; ?>

        <?php if ( $show_stock && $stock_qty ): ?>
        <div class="sgc-urg-item sgc-urg-stock">
            <span class="sgc-urg-icon">⚡</span>
            <span class="sgc-urg-text">
                Only <strong style="color:<?php echo esc_attr( $accent ); ?>"><?php echo $stock_qty; ?> pcs</strong> left in stock
            </span>
        </div>
        <?php endif; ?>

        <?php if ( $show_orders && $orders_count > 0 ): ?>
        <div class="sgc-urg-item sgc-urg-orders">
            <span class="sgc-urg-icon">✅</span>
            <span class="sgc-urg-text">
                <strong><?php echo $orders_count; ?> orders</strong> placed in the last 7 days
            </span>
        </div>
        <?php endif; ?>

        <?php if ( $show_ships ): ?>
        <div class="sgc-urg-item sgc-urg-ships">
            <span class="sgc-urg-icon">🚢</span>
            <span class="sgc-urg-text">
                Order now — ships within <strong><?php echo $ships_days; ?> business day<?php echo $ships_days > 1 ? 's' : ''; ?></strong>
            </span>
        </div>
        <?php endif; ?>
    </div>

    <style>
    .sgclabs-urgency-widget {
        background: #fff8f8;
        border: 1.5px solid #fecaca;
        border-radius: 10px;
        padding: 12px 16px;
        margin: 12px 0;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .sgc-urg-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: #374151;
    }
    .sgc-urg-icon { font-size: 15px; flex-shrink: 0; }
    .sgc-urg-text { line-height: 1.4; }
    .sgc-urg-text strong { color: #1a1a2e; }

    /* Live pulse dot */
    .sgc-urg-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: #22c55e;
        flex-shrink: 0;
        display: inline-block;
    }
    .sgc-urg-dot-pulse {
        animation: sgc-urg-pulse 1.8s ease-in-out infinite;
        box-shadow: 0 0 0 0 rgba(34,197,94,.7);
    }
    @keyframes sgc-urg-pulse {
        0%   { box-shadow: 0 0 0 0 rgba(34,197,94,.7); }
        70%  { box-shadow: 0 0 0 7px rgba(34,197,94,0); }
        100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
    }
    </style>

    <script>
    (function() {
        // Viewers count — update every 30s with slight variation
        var el = document.getElementById('sgc-urg-viewers-<?php echo $pid; ?>');
        if ( ! el ) return;

        var min = <?php echo $viewers_min; ?>;
        var max = <?php echo $viewers_max; ?>;
        var cur = parseInt( el.textContent );

        setInterval( function() {
            // Small random change +/- 2
            var change = Math.floor( Math.random() * 5 ) - 2;
            cur = Math.max( min, Math.min( max, cur + change ) );
            el.textContent = cur;
        }, 30000 );
    })();
    </script>
    <?php
}

// ── METABOX: Per-product disable ─────────────────────────────
add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'sgclabs_urgency_product',
        '⚡ Urgency Widget',
        'sgclabs_urgency_product_metabox',
        'product', 'side', 'low'
    );
} );

function sgclabs_urgency_product_metabox( $post ) {
    wp_nonce_field( 'sgclabs_urgency_product', 'sgclabs_urgency_nonce' );
    $disabled = get_post_meta( $post->ID, '_sgclabs_urgency_disabled', true );
    ?>
    <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
        <input type="checkbox" name="sgclabs_urgency_disabled" value="1" <?php checked( $disabled, '1' ); ?>>
        Disable urgency widget on this product
    </label>
    <p style="font-size:11px;color:#9ca3af;margin-top:6px;">Global settings: SGC LABS → ⚡ Urgency Widget</p>
    <?php
}

add_action( 'save_post_product', function( $post_id ) {
    if ( ! isset( $_POST['sgclabs_urgency_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['sgclabs_urgency_nonce'], 'sgclabs_urgency_product' ) ) return;
    update_post_meta( $post_id, '_sgclabs_urgency_disabled', isset( $_POST['sgclabs_urgency_disabled'] ) ? '1' : '0' );
} );

// ── AJAX: Save settings ───────────────────────────────────────
add_action( 'admin_post_sgclabs_save_urgency', 'sgclabs_save_urgency_settings' );
function sgclabs_save_urgency_settings() {
    if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'sgclabs_urgency_nonce' ) ) wp_die( 'Unauthorized' );

    update_option( 'sgclabs_urgency_active',       isset( $_POST['urgency_active'] )      ? '1' : '0' );
    update_option( 'sgclabs_urgency_show_viewers', isset( $_POST['show_viewers'] )        ? '1' : '0' );
    update_option( 'sgclabs_urgency_show_stock',   isset( $_POST['show_stock'] )          ? '1' : '0' );
    update_option( 'sgclabs_urgency_show_orders',  isset( $_POST['show_orders'] )         ? '1' : '0' );
    update_option( 'sgclabs_urgency_show_ships',   isset( $_POST['show_ships'] )          ? '1' : '0' );
    update_option( 'sgclabs_urgency_viewers_min',  absint( $_POST['viewers_min']  ?? 8 ) );
    update_option( 'sgclabs_urgency_viewers_max',  absint( $_POST['viewers_max']  ?? 28 ) );
    update_option( 'sgclabs_urgency_stock_limit',  absint( $_POST['stock_limit']  ?? 50 ) );
    update_option( 'sgclabs_urgency_ships_days',   absint( $_POST['ships_days']   ?? 2 ) );
    update_option( 'sgclabs_urgency_accent',       sanitize_hex_color( $_POST['urgency_accent'] ?? '#c62828' ) );

    wp_redirect( admin_url( 'admin.php?page=sgclabs-site-tools-hub&tab=urgency&saved=1' ) );
    exit;
}

// ── ADMIN PAGE ────────────────────────────────────────────────
// Menu registered via admin-menu.php → Site Tools → Urgency tab

function sgclabs_urgency_admin_page() { ?>
    <div class="wrap">
    <h1 style="color:#1a1a2e;">⚡ Urgency Widget Settings</h1>
    <?php if ( isset( $_GET['saved'] ) ) echo '<div class="notice notice-success is-dismissible"><p>✅ Settings saved!</p></div>'; ?>

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;max-width:600px;margin-top:16px;">
    <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
        <input type="hidden" name="action" value="sgclabs_save_urgency">
        <?php wp_nonce_field( 'sgclabs_urgency_nonce' ); ?>

        <table class="form-table" style="font-size:13px;">
            <tr><th>Enable</th><td><label><input type="checkbox" name="urgency_active" value="1" <?php checked( get_option('sgclabs_urgency_active','1'),'1'); ?>> Enable urgency widget on all products</label></td></tr>
            <tr><th>Show Viewers</th><td><label><input type="checkbox" name="show_viewers" value="1" <?php checked( get_option('sgclabs_urgency_show_viewers','1'),'1'); ?>> "X people viewing right now"</label></td></tr>
            <tr><th>Viewers Range</th><td>
                Min: <input type="number" name="viewers_min" value="<?php echo get_option('sgclabs_urgency_viewers_min',8); ?>" min="1" max="100" style="width:70px;padding:5px;border:1px solid #ddd;border-radius:4px;">
                &nbsp; Max: <input type="number" name="viewers_max" value="<?php echo get_option('sgclabs_urgency_viewers_max',28); ?>" min="1" max="100" style="width:70px;padding:5px;border:1px solid #ddd;border-radius:4px;">
            </td></tr>
            <tr><th>Show Stock</th><td><label><input type="checkbox" name="show_stock" value="1" <?php checked( get_option('sgclabs_urgency_show_stock','1'),'1'); ?>> "Only X pcs left" (when stock ≤ limit)</label></td></tr>
            <tr><th>Stock Limit</th><td><input type="number" name="stock_limit" value="<?php echo get_option('sgclabs_urgency_stock_limit',50); ?>" min="1" style="width:80px;padding:5px;border:1px solid #ddd;border-radius:4px;"> pcs — show below this qty</td></tr>
            <tr><th>Show Orders</th><td><label><input type="checkbox" name="show_orders" value="1" <?php checked( get_option('sgclabs_urgency_show_orders','1'),'1'); ?>> "X orders in last 7 days" (real data)</label></td></tr>
            <tr><th>Show Ships In</th><td><label><input type="checkbox" name="show_ships" value="1" <?php checked( get_option('sgclabs_urgency_show_ships','1'),'1'); ?>> "Ships within X days"</label></td></tr>
            <tr><th>Ships In Days</th><td><input type="number" name="ships_days" value="<?php echo get_option('sgclabs_urgency_ships_days',2); ?>" min="1" max="30" style="width:70px;padding:5px;border:1px solid #ddd;border-radius:4px;"> business days</td></tr>
            <tr><th>Accent Color</th><td><input type="color" name="urgency_accent" value="<?php echo esc_attr(get_option('sgclabs_urgency_accent','#c62828')); ?>" style="height:36px;width:60px;cursor:pointer;border-radius:6px;"></td></tr>
        </table>

        <button type="submit" class="button button-primary" style="background:#1565c0;border-color:#1565c0;border-radius:6px;padding:8px 20px;margin-top:8px;">💾 Save Settings</button>
    </form>
    </div>
    </div>
<?php }
