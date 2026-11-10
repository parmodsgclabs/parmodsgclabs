<?php
if (!defined('ABSPATH')) exit;

// ============================================================
// SGC LABS® — EXPORT B2B PRICING MODULE v6.7.2
// Focus: International Buyers — USD Pricing
// Features:
//  1. Product metabox — HS Code, Export MOQ, Tier Pricing (USD)
//  2. Frontend — Export price table, FOB notice, free shipping badge
//  3. Cart — MOQ enforcement, $2000 free shipping notice
//  4. Admin — Bulk CSV import, stats dashboard
// ============================================================

define('SGCLABS_B2B_META', '_sgclabs_b2b_pricing');

// ── METABOX REGISTER ─────────────────────────────────────────
add_action('add_meta_boxes', function() {
    add_meta_box(
        'sgclabs_b2b_pricing',
        '🌍 Export Pricing & MOQ',
        'sgclabs_b2b_pricing_metabox',
        'product', 'normal', 'high'
    );
});

// ── METABOX RENDER ───────────────────────────────────────────
function sgclabs_b2b_pricing_metabox($post) {
    wp_nonce_field('sgclabs_b2b_pricing_nonce', 'sgclabs_b2b_nonce');
    $d = get_post_meta($post->ID, SGCLABS_B2B_META, true) ?: [];

    $hs_code     = $d['hs_code']      ?? '';
    $export_moq  = $d['export_moq']   ?? '';
    $t1_max      = $d['tier1_max']    ?? '9';
    $t1_price    = $d['tier1_price']  ?? '';
    $t2_min      = $d['tier2_min']    ?? '10';
    $t2_max      = $d['tier2_max']    ?? '49';
    $t2_price    = $d['tier2_price']  ?? '';
    $t3_min      = $d['tier3_min']    ?? '50';
    $t3_price    = $d['tier3_price']  ?? '';
    $tier_active = $d['tier_active']  ?? '0';
    $moq_active  = $d['moq_active']   ?? '0';
    $hs_active   = $d['hs_active']    ?? '0';
    ?>
    <style>
    .sgc-b2b-wrap{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;}
    .sgc-b2b-sec{background:#f8f9fa;border:1px solid #e5e7eb;border-radius:8px;padding:14px;margin-bottom:12px;}
    .sgc-b2b-sec-title{font-size:11px;font-weight:700;color:#1a1a2e;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;}
    .sgc-b2b-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
    .sgc-b2b-field{display:flex;flex-direction:column;gap:3px;}
    .sgc-b2b-field label{font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;}
    .sgc-b2b-field input,.sgc-b2b-field select{padding:7px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;}
    .sgc-b2b-field input:focus{border-color:#1565c0;outline:none;}
    .sgc-b2b-hint{font-size:11px;color:#9ca3af;}
    .sgc-toggle{display:flex;align-items:center;gap:7px;margin-bottom:10px;}
    .sgc-toggle input{width:15px;height:15px;cursor:pointer;}
    .sgc-toggle label{font-size:13px;font-weight:600;color:#374151;cursor:pointer;}
    .sgc-tier-header{display:grid;grid-template-columns:70px 80px 80px 1fr;gap:8px;margin-bottom:4px;}
    .sgc-tier-header span{font-size:10px;font-weight:600;color:#9ca3af;text-transform:uppercase;}
    .sgc-tier-row{display:grid;grid-template-columns:70px 80px 80px 1fr;gap:8px;margin-bottom:6px;align-items:center;}
    .sgc-tier-badge{background:#eff6ff;color:#1565c0;font-size:11px;font-weight:700;padding:4px 0;border-radius:6px;text-align:center;}
    .sgc-collapsed{display:none;}
    .sgc-info-box{background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:8px 12px;font-size:12px;color:#92400e;margin-top:8px;}
    </style>

    <div class="sgc-b2b-wrap">

        <!-- FOB INFO BAR -->
        <div class="sgc-info-box">
            🚢 <strong>Incoterms: FOB New Delhi</strong> &nbsp;|&nbsp;
            🎁 <strong>Free Shipping on orders $2000+</strong> &nbsp;|&nbsp;
            💵 <strong>Currency: USD</strong>
            <br><small style="opacity:.8;">Yeh settings sab products pe globally apply hoti hain.</small>
        </div>

        <div style="margin-top:12px;">

        <!-- HS CODE -->
        <div class="sgc-b2b-sec">
            <div class="sgc-b2b-sec-title">🏷️ HS Code (International)</div>
            <div class="sgc-toggle">
                <input type="checkbox" id="hs_active" name="b2b[hs_active]" value="1" <?php checked($hs_active,'1'); ?> onchange="sgcToggle('hs-fields',this.checked)">
                <label for="hs_active">HS Code product page pe dikhao</label>
            </div>
            <div id="hs-fields" class="<?php echo $hs_active==='1'?'':'sgc-collapsed'; ?>">
                <div class="sgc-b2b-field">
                    <label>HS Code</label>
                    <input type="text" name="b2b[hs_code]" value="<?php echo esc_attr($hs_code); ?>" placeholder="e.g. 7017.10.00" style="max-width:220px;">
                    <span class="sgc-b2b-hint">International Harmonized System code (same as HSN for export)</span>
                </div>
            </div>
        </div>

        <!-- EXPORT MOQ -->
        <div class="sgc-b2b-sec">
            <div class="sgc-b2b-sec-title">📦 Export MOQ (Minimum Order)</div>
            <div class="sgc-toggle">
                <input type="checkbox" id="moq_active" name="b2b[moq_active]" value="1" <?php checked($moq_active,'1'); ?> onchange="sgcToggle('moq-fields',this.checked)">
                <label for="moq_active">MOQ enforce karo</label>
            </div>
            <div id="moq-fields" class="<?php echo $moq_active==='1'?'':'sgc-collapsed'; ?>">
                <div class="sgc-b2b-row">
                    <div class="sgc-b2b-field">
                        <label>Minimum Quantity (pcs)</label>
                        <input type="number" name="b2b[export_moq]" value="<?php echo esc_attr($export_moq); ?>" placeholder="e.g. 25" min="1" style="max-width:120px;">
                        <span class="sgc-b2b-hint">Isse kam qty cart mein block hogi</span>
                    </div>
                    <div class="sgc-b2b-field">
                        <label>MOQ Notice (optional)</label>
                        <input type="text" name="b2b[moq_notice]" value="<?php echo esc_attr($d['moq_notice']??''); ?>" placeholder="Minimum export order: 25 pcs">
                        <span class="sgc-b2b-hint">Default: "Minimum export order: X pcs"</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- TIER PRICING -->
        <div class="sgc-b2b-sec">
            <div class="sgc-b2b-sec-title">📊 Volume / Tier Pricing (USD)</div>
            <div class="sgc-toggle">
                <input type="checkbox" id="tier_active" name="b2b[tier_active]" value="1" <?php checked($tier_active,'1'); ?> onchange="sgcToggle('tier-fields',this.checked)">
                <label for="tier_active">Volume pricing table dikhao</label>
            </div>
            <div id="tier-fields" class="<?php echo $tier_active==='1'?'':'sgc-collapsed'; ?>">
                <div class="sgc-tier-header">
                    <span>Tier</span><span>From (pcs)</span><span>To (pcs)</span><span>Price ($/pc)</span>
                </div>
                <!-- Tier 1 -->
                <div class="sgc-tier-row">
                    <div class="sgc-tier-badge">T1</div>
                    <input type="text" value="1" readonly style="background:#f3f4f6;color:#aaa;border:1px solid #e5e7eb;border-radius:6px;padding:6px;font-size:12px;">
                    <input type="number" name="b2b[tier1_max]" value="<?php echo esc_attr($t1_max); ?>" placeholder="9" min="1" style="border:1px solid #d1d5db;border-radius:6px;padding:6px;font-size:13px;">
                    <input type="number" name="b2b[tier1_price]" value="<?php echo esc_attr($t1_price); ?>" placeholder="e.g. 5.50" step="0.01" min="0" style="border:1px solid #d1d5db;border-radius:6px;padding:6px;font-size:13px;">
                </div>
                <!-- Tier 2 -->
                <div class="sgc-tier-row">
                    <div class="sgc-tier-badge">T2</div>
                    <input type="number" name="b2b[tier2_min]" value="<?php echo esc_attr($t2_min); ?>" placeholder="10" style="border:1px solid #d1d5db;border-radius:6px;padding:6px;font-size:13px;">
                    <input type="number" name="b2b[tier2_max]" value="<?php echo esc_attr($t2_max); ?>" placeholder="49" style="border:1px solid #d1d5db;border-radius:6px;padding:6px;font-size:13px;">
                    <input type="number" name="b2b[tier2_price]" value="<?php echo esc_attr($t2_price); ?>" placeholder="e.g. 4.80" step="0.01" min="0" style="border:1px solid #d1d5db;border-radius:6px;padding:6px;font-size:13px;">
                </div>
                <!-- Tier 3 -->
                <div class="sgc-tier-row">
                    <div class="sgc-tier-badge">T3</div>
                    <input type="number" name="b2b[tier3_min]" value="<?php echo esc_attr($t3_min); ?>" placeholder="50" style="border:1px solid #d1d5db;border-radius:6px;padding:6px;font-size:13px;">
                    <input type="text" value="& above" readonly style="background:#f3f4f6;color:#aaa;border:1px solid #e5e7eb;border-radius:6px;padding:6px;font-size:12px;">
                    <input type="number" name="b2b[tier3_price]" value="<?php echo esc_attr($t3_price); ?>" placeholder="e.g. 4.20" step="0.01" min="0" style="border:1px solid #d1d5db;border-radius:6px;padding:6px;font-size:13px;">
                </div>
                <p class="sgc-b2b-hint" style="margin-top:6px;">💡 Price auto-apply hoga cart mein qty ke hisaab se.</p>
            </div>
        </div>

        </div>
    </div>

    <script>
    function sgcToggle(id, show) {
        var el = document.getElementById(id);
        if (el) el.classList.toggle('sgc-collapsed', !show);
    }
    </script>
    <?php
}

// ── SAVE ─────────────────────────────────────────────────────
add_action('save_post_product', 'sgclabs_b2b_pricing_save', 10, 2);
function sgclabs_b2b_pricing_save($post_id, $post) {
    if (!isset($_POST['sgclabs_b2b_nonce'])) return;
    if (!wp_verify_nonce($_POST['sgclabs_b2b_nonce'], 'sgclabs_b2b_pricing_nonce')) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    $raw  = $_POST['b2b'] ?? [];
    $data = [
        'hs_active'    => isset($raw['hs_active'])   ? '1' : '0',
        'hs_code'      => sanitize_text_field($raw['hs_code']      ?? ''),
        'moq_active'   => isset($raw['moq_active'])  ? '1' : '0',
        'export_moq'   => absint($raw['export_moq']  ?? 0),
        'moq_notice'   => sanitize_text_field($raw['moq_notice']   ?? ''),
        'tier_active'  => isset($raw['tier_active']) ? '1' : '0',
        'tier1_max'    => absint($raw['tier1_max']   ?? 9),
        'tier1_price'  => (float)($raw['tier1_price'] ?? 0),
        'tier2_min'    => absint($raw['tier2_min']   ?? 10),
        'tier2_max'    => absint($raw['tier2_max']   ?? 49),
        'tier2_price'  => (float)($raw['tier2_price'] ?? 0),
        'tier3_min'    => absint($raw['tier3_min']   ?? 50),
        'tier3_price'  => (float)($raw['tier3_price'] ?? 0),
    ];
    update_post_meta($post_id, SGCLABS_B2B_META, $data);
}

// ── HELPER ───────────────────────────────────────────────────
function sgclabs_get_b2b($pid): array {
    return get_post_meta($pid, SGCLABS_B2B_META, true) ?: [];
}

// ── CART: Tier price auto-apply ──────────────────────────────
add_action('woocommerce_before_calculate_totals', 'sgclabs_apply_tier_price', 20);
function sgclabs_apply_tier_price($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (did_action('woocommerce_before_calculate_totals') >= 2) return;

    foreach ($cart->get_cart() as $item) {
        $pid = $item['product_id'];
        $qty = $item['quantity'];
        $d   = sgclabs_get_b2b($pid);

        if (empty($d['tier_active']) || $d['tier_active'] !== '1') continue;

        $price = null;
        $t1max = (int)($d['tier1_max'] ?? 9);
        $t2min = (int)($d['tier2_min'] ?? 10);
        $t2max = (int)($d['tier2_max'] ?? 49);
        $t3min = (int)($d['tier3_min'] ?? 50);

        if ($qty <= $t1max && !empty($d['tier1_price']))
            $price = (float)$d['tier1_price'];
        elseif ($qty >= $t2min && $qty <= $t2max && !empty($d['tier2_price']))
            $price = (float)$d['tier2_price'];
        elseif ($qty >= $t3min && !empty($d['tier3_price']))
            $price = (float)$d['tier3_price'];

        if ($price > 0) $item['data']->set_price($price);
    }
}

// ── CART: MOQ validation ─────────────────────────────────────
add_filter('woocommerce_add_to_cart_validation', 'sgclabs_moq_validate', 10, 3);
function sgclabs_moq_validate($valid, $product_id, $quantity) {
    $d   = sgclabs_get_b2b($product_id);
    $moq = (int)($d['export_moq'] ?? 0);
    // v7.4.8 fix: use ?? to avoid "Undefined array key moq_active" warning
    if (( $d['moq_active'] ?? '0' ) !== '1' || $moq <= 0) return $valid;
    if ($quantity < $moq) {
        $name = get_the_title($product_id);
        wc_add_notice(sprintf(
            '⚠️ <strong>%s</strong> — Minimum export order is <strong>%d pcs</strong>. Please increase quantity.',
            esc_html($name), $moq
        ), 'error');
        return false;
    }
    return $valid;
}

add_action('woocommerce_check_cart_items', 'sgclabs_moq_cart_check');
function sgclabs_moq_cart_check() {
    foreach (WC()->cart->get_cart() as $item) {
        $d   = sgclabs_get_b2b($item['product_id']);
        $moq = (int)($d['export_moq'] ?? 0);
        if (( $d['moq_active'] ?? '0' ) !== '1' || $moq <= 0) continue;
        if ($item['quantity'] < $moq) {
            wc_add_notice(sprintf(
                '⚠️ <strong>%s</strong> — Minimum export order: <strong>%d pcs</strong>.',
                esc_html(get_the_title($item['product_id'])), $moq
            ), 'error');
        }
    }
}

// ── CART: $2000 free shipping notice ─────────────────────────
add_action('woocommerce_before_cart', 'sgclabs_free_shipping_notice');
add_action('woocommerce_before_checkout_form', 'sgclabs_free_shipping_notice');
function sgclabs_free_shipping_notice() {
    $threshold = (float) get_option('sgclabs_free_ship_threshold', 2000);
    $total     = (float) WC()->cart->get_subtotal();
    $remaining = $threshold - $total;

    if ($remaining > 0) {
        echo '<div style="background:#f0f7ff;border:1.5px solid #bfdbfe;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#1e40af;">
            🚢 <strong>Add $' . number_format($remaining, 2) . ' more</strong> to your order for <strong>FREE Shipping (FOB New Delhi)</strong>!
        </div>';
    } else {
        echo '<div style="background:#f0fdf4;border:1.5px solid #86efac;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#166534;">
            ✅ <strong>FREE Shipping unlocked!</strong> Your order qualifies for Free Shipping (FOB New Delhi).
        </div>';
    }
}

// ── FRONTEND: Product page info block ────────────────────────
add_action('woocommerce_single_product_summary', 'sgclabs_b2b_frontend', 25);
function sgclabs_b2b_frontend() {
    global $product;
    if (!$product) return;

    $pid  = $product->get_id();
    $d    = sgclabs_get_b2b($pid);
    if (empty($d)) return;

    $shown = false;
    echo '<div class="sgclabs-b2b-block">';

    // ── Tier Pricing Table ──────────────────────────────────
    if (!empty($d['tier_active']) && $d['tier_active'] === '1') {
        $t1p = (float)($d['tier1_price'] ?? 0);
        $t2p = (float)($d['tier2_price'] ?? 0);
        $t3p = (float)($d['tier3_price'] ?? 0);

        if ($t1p > 0 || $t2p > 0 || $t3p > 0) {
            $shown = true;
            $base  = $t1p > 0 ? $t1p : (float)$product->get_price();
            echo '<div class="sgc-tier-wrap">';
            echo '<div class="sgc-tier-heading">📊 Volume Pricing</div>';
            echo '<table class="sgc-tier-tbl">';
            echo '<thead><tr><th>Quantity</th><th>Price/pc</th><th>Discount</th></tr></thead><tbody>';

            if ($t1p > 0) {
                echo '<tr class="sgc-tier-t1">';
                echo '<td>1 – ' . (int)$d['tier1_max'] . ' pcs</td>';
                echo '<td><strong>$' . number_format($t1p, 2) . '</strong></td>';
                echo '<td><span class="sgc-badge-retail">Standard</span></td>';
                echo '</tr>';
            }
            if ($t2p > 0) {
                $sv = $base > 0 ? round((($base-$t2p)/$base)*100) : 0;
                echo '<tr>';
                echo '<td>' . (int)$d['tier2_min'] . ' – ' . (int)$d['tier2_max'] . ' pcs</td>';
                echo '<td><strong>$' . number_format($t2p, 2) . '</strong></td>';
                echo '<td>' . ($sv > 0 ? '<span class="sgc-badge-save">Save '.$sv.'%</span>' : '—') . '</td>';
                echo '</tr>';
            }
            if ($t3p > 0) {
                $sv = $base > 0 ? round((($base-$t3p)/$base)*100) : 0;
                echo '<tr>';
                echo '<td>' . (int)$d['tier3_min'] . '+ pcs</td>';
                echo '<td><strong>$' . number_format($t3p, 2) . '</strong></td>';
                echo '<td>' . ($sv > 0 ? '<span class="sgc-badge-save">Save '.$sv.'%</span>' : '—') . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '<p class="sgc-tier-note">💡 Price auto-updates in cart based on quantity ordered.</p>';
            echo '</div>';
        }
    }

    // ── Export Info Bar (MOQ + HS Code + FOB + Free Ship) ───
    $info_items = [];

    if (!empty($d['moq_active']) && $d['moq_active'] === '1' && !empty($d['export_moq'])) {
        $notice = !empty($d['moq_notice']) ? $d['moq_notice'] : 'Min. Export Order: ' . $d['export_moq'] . ' pcs';
        $info_items[] = '📦 ' . esc_html($notice);
        $shown = true;
    }

    if (!empty($d['hs_active']) && $d['hs_active'] === '1' && !empty($d['hs_code'])) {
        $info_items[] = '🏷️ HS Code: <strong>' . esc_html($d['hs_code']) . '</strong>';
        $shown = true;
    }

    $info_items[] = '🚢 FOB New Delhi';
    $info_items[] = '🎁 Free Shipping on orders <strong>$2000+</strong>';

    if (!empty($info_items)) {
        echo '<div class="sgc-export-bar">';
        foreach ($info_items as $item) {
            echo '<div class="sgc-export-item">' . $item . '</div>';
        }
        echo '</div>';
        $shown = true;
    }

    echo '</div>';

    // ── CSS ─────────────────────────────────────────────────
    if ($shown) { ?>
    <style>
    .sgclabs-b2b-block { margin: 14px 0; }

    /* Tier table */
    .sgc-tier-wrap { background:#f0f7ff; border:1.5px solid #bfdbfe; border-radius:10px; padding:14px; margin-bottom:10px; }
    .sgc-tier-heading { font-size:13px; font-weight:700; color:#1565c0; margin-bottom:10px; }
    .sgc-tier-tbl { width:100%; border-collapse:collapse; font-size:13px; }
    .sgc-tier-tbl thead tr { background:#1565c0; }
    .sgc-tier-tbl thead th { padding:8px 12px; text-align:left; font-weight:600; font-size:11px; color:#fff; text-transform:uppercase; letter-spacing:.3px; }
    .sgc-tier-tbl tbody tr { border-bottom:1px solid #dbeafe; transition:background .15s; }
    .sgc-tier-tbl tbody tr:hover { background:#eff6ff; }
    .sgc-tier-tbl tbody tr:last-child { border-bottom:none; }
    .sgc-tier-tbl td { padding:8px 12px; color:#1a1a2e; }
    .sgc-tier-t1 { background:#fff; }
    .sgc-badge-retail { background:#e0e7ff; color:#3730a3; font-size:10px; padding:2px 8px; border-radius:20px; font-weight:600; }
    .sgc-badge-save { background:#dcfce7; color:#166534; font-size:10px; padding:2px 8px; border-radius:20px; font-weight:700; }
    .sgc-tier-note { font-size:11px; color:#6b7280; margin-top:8px; }

    /* Export info bar */
    .sgc-export-bar { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:10px; }
    .sgc-export-item { background:#f8f9fa; border:1px solid #e5e7eb; border-radius:6px; padding:7px 12px; font-size:12px; color:#374151; }
    .sgc-export-item strong { color:#1a1a2e; }

    @media(max-width:480px) {
        .sgc-tier-tbl { font-size:12px; }
        .sgc-tier-tbl td { padding:6px 8px; }
        .sgc-export-bar { flex-direction:column; }
    }
    </style>
    <?php }
}

// ── ADMIN PAGE ───────────────────────────────────────────────
add_action('admin_menu', function() {
    add_submenu_page(
        'sgclabs-manager',
        'Export B2B Pricing',
        '💰 Export Pricing',
        'manage_options',
        'sgclabs-b2b-pricing',
        'sgclabs_b2b_pricing_admin'
    );
}, 25);

function sgclabs_b2b_pricing_admin() {
    global $wpdb;

    // CSV Import
    if (isset($_POST['sgclabs_b2b_csv_nonce']) && wp_verify_nonce($_POST['sgclabs_b2b_csv_nonce'], 'sgclabs_b2b_csv') && !empty($_FILES['b2b_csv']['tmp_name'])) {
        $count = sgclabs_b2b_csv_import($_FILES['b2b_csv']['tmp_name']);
        echo '<div class="notice notice-success is-dismissible"><p>✅ ' . $count . ' products B2B pricing set ho gaya!</p></div>';
    }

    // Global settings save
    if (isset($_POST['sgclabs_b2b_global_nonce']) && wp_verify_nonce($_POST['sgclabs_b2b_global_nonce'], 'sgclabs_b2b_global')) {
        update_option('sgclabs_free_ship_threshold', (float)($_POST['free_ship_threshold'] ?? 2000));
        update_option('sgclabs_default_incoterms',  sanitize_text_field($_POST['default_incoterms'] ?? 'FOB New Delhi'));
        echo '<div class="notice notice-success is-dismissible"><p>✅ Global settings saved!</p></div>';
    }

    $total   = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'");
    $b2b_ct  = (int)$wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='".SGCLABS_B2B_META."'");
    $free_ship = get_option('sgclabs_free_ship_threshold', 2000);
    $incoterms = get_option('sgclabs_default_incoterms', 'FOB New Delhi');
    ?>
    <div class="wrap">
    <h1 style="color:#1a1a2e;margin-bottom:20px;">🌍 Export B2B Pricing Manager</h1>

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;max-width:650px;margin-bottom:24px;">
        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px;text-align:center;">
            <div style="font-size:30px;font-weight:700;color:#1565c0;"><?php echo $total; ?></div>
            <div style="font-size:12px;color:#6b7280;margin-top:2px;">Total Products</div>
        </div>
        <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:10px;padding:16px;text-align:center;">
            <div style="font-size:30px;font-weight:700;color:#166534;"><?php echo $b2b_ct; ?></div>
            <div style="font-size:12px;color:#6b7280;margin-top:2px;">Pricing Set</div>
        </div>
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:16px;text-align:center;">
            <div style="font-size:30px;font-weight:700;color:#92400e;"><?php echo $total - $b2b_ct; ?></div>
            <div style="font-size:12px;color:#6b7280;margin-top:2px;">Pending</div>
        </div>
    </div>

    <!-- Global Settings -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;max-width:650px;margin-bottom:20px;">
        <h3 style="margin:0 0 14px;color:#1a1a2e;font-size:15px;">⚙️ Global Export Settings</h3>
        <form method="post">
            <?php wp_nonce_field('sgclabs_b2b_global', 'sgclabs_b2b_global_nonce'); ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                <div>
                    <label style="font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:4px;">Free Shipping Threshold (USD)</label>
                    <input type="number" name="free_ship_threshold" value="<?php echo esc_attr($free_ship); ?>" min="0" step="10" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
                </div>
                <div>
                    <label style="font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:4px;">Default Incoterms</label>
                    <input type="text" name="default_incoterms" value="<?php echo esc_attr($incoterms); ?>" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
                </div>
            </div>
            <button type="submit" class="button button-primary" style="background:#1565c0;border-color:#1565c0;border-radius:6px;padding:6px 18px;">💾 Save Settings</button>
        </form>
    </div>

    <!-- CSV Import -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;max-width:650px;margin-bottom:20px;">
        <h3 style="margin:0 0 6px;color:#1a1a2e;font-size:15px;">📥 Bulk CSV Import</h3>
        <p style="color:#6b7280;font-size:13px;margin:0 0 12px;">7000 products ka pricing ek CSV se set karo.</p>
        <div style="background:#f8f9fa;border-radius:6px;padding:10px 12px;font-size:12px;color:#6b7280;margin-bottom:12px;font-family:monospace;">
            product_id, hs_code, export_moq, tier1_max, tier1_price, tier2_min, tier2_max, tier2_price, tier3_min, tier3_price<br>
            <span style="color:#374151;">123, 7017.10, 25, 9, 5.50, 10, 49, 4.80, 50, 4.20</span>
        </div>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('sgclabs_b2b_csv', 'sgclabs_b2b_csv_nonce'); ?>
            <div style="display:flex;gap:10px;align-items:center;margin-bottom:10px;">
                <input type="file" name="b2b_csv" accept=".csv" required style="flex:1;padding:7px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
                <button type="submit" class="button button-primary" style="background:#1565c0;border-color:#1565c0;border-radius:6px;padding:7px 16px;white-space:nowrap;">📥 Import</button>
            </div>
        </form>
        <button onclick="sgcDlTemplate()" class="button" style="font-size:12px;border-radius:6px;">📄 Download Template CSV</button>
    </div>

    <!-- Recent Products -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;max-width:650px;">
        <h3 style="margin:0 0 14px;color:#1a1a2e;font-size:15px;">📋 Recently Configured Products</h3>
        <?php
        $rows = $wpdb->get_results("SELECT p.ID, p.post_title, pm.meta_value FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id AND pm.meta_key='".SGCLABS_B2B_META."' WHERE p.post_type='product' AND p.post_status='publish' ORDER BY p.ID DESC LIMIT 10");
        if ($rows): ?>
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead><tr style="background:#f8f9fa;border-bottom:2px solid #e5e7eb;">
                <th style="padding:8px 10px;text-align:left;font-weight:600;">Product</th>
                <th style="padding:8px 10px;text-align:center;font-weight:600;">HS Code</th>
                <th style="padding:8px 10px;text-align:center;font-weight:600;">MOQ</th>
                <th style="padding:8px 10px;text-align:center;font-weight:600;">Tiers</th>
                <th style="padding:8px 10px;text-align:center;font-weight:600;">Edit</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r):
                $d = maybe_unserialize($r->meta_value); ?>
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:8px 10px;font-weight:500;"><?php echo esc_html(substr($r->post_title,0,40)); ?></td>
                    <td style="padding:8px 10px;text-align:center;color:#1565c0;"><?php echo esc_html($d['hs_code']??'—'); ?></td>
                    <td style="padding:8px 10px;text-align:center;"><?php echo !empty($d['export_moq']) ? '<span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;">'.$d['export_moq'].' pcs</span>' : '—'; ?></td>
                    <td style="padding:8px 10px;text-align:center;"><?php echo ($d['tier_active']??'0')==='1' ? '<span style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;">✓ Active</span>' : '—'; ?></td>
                    <td style="padding:8px 10px;text-align:center;"><a href="<?php echo get_edit_post_link($r->ID); ?>" style="color:#1565c0;font-size:11px;font-weight:600;">Edit →</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p style="color:#9ca3af;font-size:13px;">Koi product configured nahi — product edit page pe set karo ya CSV import karo.</p>
        <?php endif; ?>
    </div>

    </div>
    <script>
    function sgcDlTemplate() {
        var csv = 'product_id,hs_code,export_moq,tier1_max,tier1_price,tier2_min,tier2_max,tier2_price,tier3_min,tier3_price\n';
        csv += '123,7017.10,25,9,5.50,10,49,4.80,50,4.20\n';
        csv += '124,7017.10,10,4,8.00,5,24,7.20,25,6.50\n';
        var a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([csv],{type:'text/csv'}));
        a.download = 'sgclabs_export_pricing_template.csv';
        a.click();
    }
    </script>
    <?php
}

// ── CSV IMPORT ───────────────────────────────────────────────
function sgclabs_b2b_csv_import($file): int {
    $h = fopen($file, 'r');
    if (!$h) return 0;
    $count = 0; $first = true;
    while (($row = fgetcsv($h)) !== false) {
        if ($first) { $first = false; continue; }
        if (count($row) < 5) continue;
        $pid = (int)trim($row[0]);
        if (!$pid || get_post_type($pid) !== 'product') continue;
        $moq = (int)trim($row[2]??0);
        $t1p = (float)trim($row[4]??0);
        $data = [
            'hs_active'   => !empty(trim($row[1])) ? '1' : '0',
            'hs_code'     => sanitize_text_field(trim($row[1]??'')),
            'moq_active'  => $moq > 0 ? '1' : '0',
            'export_moq'  => $moq,
            'moq_notice'  => '',
            'tier_active' => $t1p > 0 ? '1' : '0',
            'tier1_max'   => (int)trim($row[3]??9),
            'tier1_price' => $t1p,
            'tier2_min'   => (int)trim($row[5]??10),
            'tier2_max'   => (int)trim($row[6]??49),
            'tier2_price' => (float)trim($row[7]??0),
            'tier3_min'   => (int)trim($row[8]??50),
            'tier3_price' => (float)trim($row[9]??0),
        ];
        update_post_meta($pid, SGCLABS_B2B_META, $data);
        $count++;
    }
    fclose($h);
    return $count;
}
