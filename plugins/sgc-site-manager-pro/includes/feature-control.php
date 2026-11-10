<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================
// SGC LABS® — FEATURE CONTROL CENTER v6.8.5 [2026-04-09]
// Master ON/OFF toggles for every feature
// All features se pehle yeh check hota hai
// ============================================================

// ── FEATURE REGISTRY ─────────────────────────────────────────
function sgclabs_features(): array {
    return [

        // ── PRODUCT PAGE WIDGETS ───────────────────────────
        'compare_prices' => [
            'label'   => '💰 Compare Prices Widget (Old)',
            'desc'    => 'Fisher / VWR / Amazon / DWK exact AI-estimated prices — purana widget',
            'option'  => 'sgclabs_compare_prices_active',
            'default' => '0',          // ← OFF by default (replaced by Market Compare)
            'group'   => 'product',
            'tag'     => 'old',
        ],
        'market_compare' => [
            'label'   => '🌐 Market Price Comparison Widget (New)',
            'desc'    => 'Amazon/eBay/Walmart price ranges + product links — naya better widget',
            'option'  => 'sgclabs_market_active',
            'default' => '1',
            'group'   => 'product',
            'tag'     => 'new',
        ],
        'urgency_widget' => [
            'label'   => '⚡ Urgency Widget',
            'desc'    => 'Live viewers, stock level, recent orders — social proof on product page',
            'option'  => 'sgclabs_urgency_active',
            'default' => '1',
            'group'   => 'product',
            'tag'     => '',
        ],
        'b2b_pricing' => [
            'label'   => '📊 Export B2B Pricing',
            'desc'    => 'Tier pricing table, MOQ notice, HS code, FOB info on product page',
            'option'  => 'sgclabs_b2b_pricing_active',
            'default' => '1',
            'group'   => 'product',
            'tag'     => '',
        ],
        'whatsapp_share' => [
            'label'   => '💬 WhatsApp Share Buttons',
            'desc'    => 'Enquire on WhatsApp + Share on WhatsApp buttons on product page',
            'option'  => 'sgclabs_wa_active',
            'default' => '1',
            'group'   => 'product',
            'tag'     => '',
        ],
        'arabic_seo' => [
            'label'   => '🌍 Arabic SEO Meta Tags',
            'desc'    => 'hreflang + Arabic meta description injection on product pages',
            'option'  => 'sgclabs_arabic_seo_active',
            'default' => '1',
            'group'   => 'product',
            'tag'     => '',
        ],

        // ── STORE FEATURES ─────────────────────────────────
        'bulk_order' => [
            'label'   => '📋 Bulk Order Table',
            'desc'    => 'VWR-style /bulk-order/ page with SKU search and inline qty',
            'option'  => 'sgclabs_bulk_order_active',
            'default' => '1',
            'group'   => 'store',
            'tag'     => 'new',
        ],
        'reorder_reminders' => [
            'label'   => '🔄 Reorder Reminder Emails',
            'desc'    => 'Predictive reorder emails based on customer order history',
            'option'  => 'sgclabs_reorder_active',
            'default' => '1',
            'group'   => 'store',
            'tag'     => '',
        ],
        'cart_freight' => [
            'label'   => '🚢 Cart Freight Notice',
            'desc'    => 'Free shipping bar + FOB New Delhi notice on cart and checkout',
            'option'  => 'sgclabs_b2b_freight_notice',
            'default' => '1',
            'group'   => 'store',
            'tag'     => '',
        ],
        'get_a_quote' => [
            'label'   => '📩 Get a Quote Button',
            'desc'    => 'Quote request button on product page',
            'option'  => 'sgclabs_b2b_quote_active',
            'default' => '1',
            'group'   => 'store',
            'tag'     => '',
        ],

        // ── AI FEATURES ────────────────────────────────────
        'ai_seo_bulk' => [
            'label'   => '🤖 AI SEO Bulk Engine',
            'desc'    => 'Bulk title, meta, keywords, description generation for all products',
            'option'  => 'sgclabs_ai_seo_active',
            'default' => '1',
            'group'   => 'ai',
            'tag'     => 'ai',
        ],
        'ai_image_alt' => [
            'label'   => '🖼️ AI Image ALT Text',
            'desc'    => 'Bulk ALT text generation for product images using AI',
            'option'  => 'sgclabs_ai_alt_active',
            'default' => '1',
            'group'   => 'ai',
            'tag'     => 'ai',
        ],
        'ai_quote_reply' => [
            'label'   => '💬 AI Quote Reply',
            'desc'    => 'Auto-generate professional quote reply emails with AI',
            'option'  => 'sgclabs_b2b_quote_active',
            'default' => '1',
            'group'   => 'ai',
            'tag'     => 'ai',
        ],
        'ai_arabic' => [
            'label'   => '🌍 AI Arabic Content',
            'desc'    => 'Generate Arabic SEO title, meta, description per product',
            'option'  => 'sgclabs_ai_arabic_active',
            'default' => '1',
            'group'   => 'ai',
            'tag'     => 'ai',
        ],
    ];
}

// ── HELPER: Is feature active? ────────────────────────────────
function sgclabs_feature_active( string $key ): bool {
    $features = sgclabs_features();
    if ( ! isset( $features[ $key ] ) ) return true; // Unknown = allow
    $f = $features[ $key ];
    return get_option( $f['option'], $f['default'] ) === '1';
}

// ── GATE: Market compare + urgency (price-compare removed v6.8.7) ─
add_action( 'init', function() {
    // Remove market compare if disabled
    if ( ! sgclabs_feature_active( 'market_compare' ) ) {
        remove_action( 'woocommerce_single_product_summary', 'sgclabs_market_compare_frontend', 29 );
    }
    // Remove urgency widget if disabled
    if ( ! sgclabs_feature_active( 'urgency_widget' ) ) {
        remove_action( 'woocommerce_single_product_summary', 'sgclabs_urgency_render', 15 );
    }
    // Remove B2B pricing frontend if disabled
    if ( ! sgclabs_feature_active( 'b2b_pricing' ) ) {
        remove_action( 'woocommerce_single_product_summary', 'sgclabs_b2b_frontend', 25 );
    }
    // Remove WhatsApp if disabled
    if ( ! sgclabs_feature_active( 'whatsapp_share' ) ) {
        remove_action( 'woocommerce_single_product_summary', 'sgclabs_wa_buttons', 35 );
    }
    // Remove Arabic meta if disabled
    if ( ! sgclabs_feature_active( 'arabic_seo' ) ) {
        remove_action( 'wp_head', 'sgclabs_arabic_hreflang' );
        remove_action( 'wp_head', 'sgclabs_arabic_meta_tags', 5 );
    }
}, 99 ); // priority 99 — after all includes register their hooks

// ── AJAX: Save all feature toggles ───────────────────────────
add_action( 'wp_ajax_sgclabs_save_feature_toggles', 'sgclabs_save_feature_toggles_handler' );
function sgclabs_save_feature_toggles_handler() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $features = sgclabs_features();
    $saved    = 0;

    foreach ( $features as $key => $f ) {
        $posted = sanitize_key( $_POST[ 'feat_' . $key ] ?? '0' );
        $val    = ( $posted === '1' ) ? '1' : '0';
        update_option( $f['option'], $val );
        $saved++;
    }

    wp_send_json_success( "✅ {$saved} features saved!" );
}

// Menu registered via admin-menu.php

function sgclabs_feature_control_page() {
    $features = sgclabs_features();
    $nonce    = wp_create_nonce( 'sgclabs_nonce' );

    // Group features
    $groups = [
        'product' => [ 'label' => '📦 Product Page Widgets', 'icon' => '📦' ],
        'store'   => [ 'label' => '🏪 Store Features',        'icon' => '🏪' ],
        'ai'      => [ 'label' => '🤖 AI Features',           'icon' => '🤖' ],
    ];

    // Count active per group
    $group_counts = [];
    foreach ( $features as $key => $f ) {
        $g = $f['group'];
        if ( ! isset( $group_counts[ $g ] ) ) $group_counts[ $g ] = [ 'total' => 0, 'active' => 0 ];
        $group_counts[ $g ]['total']++;
        if ( get_option( $f['option'], $f['default'] ) === '1' ) $group_counts[ $g ]['active']++;
    }
    ?>
    <div class="wrap" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:750px;">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 style="color:#1a1a2e;margin:0 0 4px;">🎛️ Feature Control Center</h1>
            <p style="color:#6b7280;font-size:13px;margin:0;">Har feature individually ON/OFF karo — ek jagah se sab control</p>
        </div>
        <div style="display:flex;gap:10px;">
            <button id="sgc-fc-all-on" onclick="sgcFcAll(1)"
                style="background:#f0fdf4;color:#166534;border:1px solid #86efac;border-radius:7px;padding:8px 14px;font-size:12px;font-weight:600;cursor:pointer;">
                ✅ Enable All
            </button>
            <button id="sgc-fc-all-off" onclick="sgcFcAll(0)"
                style="background:#fef2f2;color:#c0392b;border:1px solid #fca5a5;border-radius:7px;padding:8px 14px;font-size:12px;font-weight:600;cursor:pointer;">
                ⛔ Disable All
            </button>
            <button id="sgc-fc-save" onclick="sgcFcSave()"
                style="background:#1565c0;color:#fff;border:none;border-radius:7px;padding:8px 20px;font-size:13px;font-weight:600;cursor:pointer;">
                💾 Save All
            </button>
        </div>
    </div>

    <div id="sgc-fc-status" style="display:none;padding:10px 16px;border-radius:8px;font-size:13px;margin-bottom:16px;"></div>

    <?php foreach ( $groups as $gkey => $group ):
        $gc = $group_counts[ $gkey ] ?? [ 'total' => 0, 'active' => 0 ];
    ?>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;margin-bottom:14px;overflow:hidden;">

        <!-- Group Header -->
        <div style="background:#f8f9fa;padding:12px 18px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;">
            <div style="font-size:14px;font-weight:700;color:#1a1a2e;"><?php echo $group['label']; ?></div>
            <div style="font-size:12px;color:#6b7280;">
                <span style="color:<?php echo $gc['active'] > 0 ? '#166534' : '#c0392b'; ?>;font-weight:600;">
                    <?php echo $gc['active']; ?>
                </span>/<?php echo $gc['total']; ?> active
            </div>
        </div>

        <!-- Features in group -->
        <?php foreach ( $features as $key => $f ):
            if ( $f['group'] !== $gkey ) continue;
            $is_active = get_option( $f['option'], $f['default'] ) === '1';
            $tag_html  = '';
            if ( $f['tag'] === 'new' )  $tag_html = '<span style="background:#dbeafe;color:#1e40af;font-size:9px;font-weight:700;padding:1px 6px;border-radius:20px;margin-left:6px;text-transform:uppercase;">NEW</span>';
            if ( $f['tag'] === 'old' )  $tag_html = '<span style="background:#fef3c7;color:#92400e;font-size:9px;font-weight:700;padding:1px 6px;border-radius:20px;margin-left:6px;text-transform:uppercase;">OLD</span>';
            if ( $f['tag'] === 'ai' )   $tag_html = '<span style="background:#ede9fe;color:#5b21b6;font-size:9px;font-weight:700;padding:1px 6px;border-radius:20px;margin-left:6px;text-transform:uppercase;">AI</span>';
        ?>
        <div class="sgc-fc-row" style="display:flex;align-items:center;justify-content:space-between;padding:13px 18px;border-bottom:1px solid #f3f4f6;gap:16px;" id="sgc-fc-row-<?php echo $key; ?>">
            <div style="flex:1;">
                <div style="font-size:13px;font-weight:600;color:#1a1a2e;margin-bottom:3px;display:flex;align-items:center;flex-wrap:wrap;gap:4px;">
                    <span class="sgc-fc-dot" id="sgc-dot-<?php echo $key; ?>"
                        style="width:7px;height:7px;border-radius:50%;display:inline-block;margin-right:2px;background:<?php echo $is_active ? '#27ae60' : '#d1d5db'; ?>;">
                    </span>
                    <?php echo esc_html( $f['label'] ); ?>
                    <?php echo $tag_html; ?>
                </div>
                <div style="font-size:11px;color:#9ca3af;"><?php echo esc_html( $f['desc'] ); ?></div>
            </div>
            <div style="flex-shrink:0;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <span style="font-size:11px;color:#9ca3af;" id="sgc-lbl-<?php echo $key; ?>"><?php echo $is_active ? 'ON' : 'OFF'; ?></span>
                    <div style="position:relative;width:44px;height:24px;" onclick="sgcFcToggle('<?php echo $key; ?>')">
                        <div id="sgc-track-<?php echo $key; ?>"
                            style="position:absolute;top:0;left:0;right:0;bottom:0;border-radius:12px;background:<?php echo $is_active ? '#1565c0' : '#d1d5db'; ?>;transition:background .2s;cursor:pointer;"></div>
                        <div id="sgc-thumb-<?php echo $key; ?>"
                            style="position:absolute;top:3px;left:<?php echo $is_active ? '23px' : '3px'; ?>;width:18px;height:18px;border-radius:50%;background:#fff;transition:left .2s;box-shadow:0 1px 3px rgba(0,0,0,.2);"></div>
                        <input type="hidden" name="feat_<?php echo $key; ?>" id="sgc-inp-<?php echo $key; ?>" value="<?php echo $is_active ? '1' : '0'; ?>">
                    </div>
                </label>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Remove bottom border from last row -->
        <style>.sgc-fc-row:last-child{border-bottom:none!important;}</style>

    </div>
    <?php endforeach; ?>

    <!-- Info Box -->
    <div style="background:#f0f7ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px 18px;margin-top:4px;">
        <div style="font-size:12px;color:#1e40af;line-height:1.8;">
            💡 <strong>Note:</strong> Changes take effect immediately after saving.<br>
            "Compare Prices (Old)" is disabled by default — "Market Price Comparison (New)" is the better replacement.<br>
            Dono ek saath ON karne se <strong>double widget</strong> dikhega product page pe.
        </div>
    </div>

    </div>

    <script>
    var sgcFcNonce = '<?php echo $nonce; ?>';
    var sgcFcChanged = false;

    function sgcFcToggle(key) {
        var inp   = document.getElementById('sgc-inp-' + key);
        var track = document.getElementById('sgc-track-' + key);
        var thumb = document.getElementById('sgc-thumb-' + key);
        var dot   = document.getElementById('sgc-dot-' + key);
        var lbl   = document.getElementById('sgc-lbl-' + key);

        var isOn = inp.value === '1';
        var newVal = isOn ? '0' : '1';
        inp.value = newVal;

        if ( newVal === '1' ) {
            track.style.background = '#1565c0';
            thumb.style.left       = '23px';
            dot.style.background   = '#27ae60';
            lbl.textContent        = 'ON';
        } else {
            track.style.background = '#d1d5db';
            thumb.style.left       = '3px';
            dot.style.background   = '#d1d5db';
            lbl.textContent        = 'OFF';
        }
        sgcFcChanged = true;
    }

    function sgcFcAll(state) {
        var keys = <?php echo wp_json_encode( array_keys( $features ) ); ?>;
        keys.forEach(function(key) {
            var inp = document.getElementById('sgc-inp-' + key);
            if ( !inp ) return;
            var isOn = inp.value === '1';
            if ( (state === 1 && !isOn) || (state === 0 && isOn) ) {
                sgcFcToggle(key);
            }
        });
    }

    function sgcFcSave() {
        var btn    = document.getElementById('sgc-fc-save');
        var status = document.getElementById('sgc-fc-status');
        var data   = { action: 'sgclabs_save_feature_toggles', nonce: sgcFcNonce };

        document.querySelectorAll('[id^="sgc-inp-"]').forEach(function(inp) {
            var key = inp.id.replace('sgc-inp-', '');
            data['feat_' + key] = inp.value;
        });

        btn.textContent = '⏳ Saving...';
        btn.disabled    = true;
        status.style.display = 'none';

        jQuery.post(ajaxurl, data, function(res) {
            btn.textContent = '💾 Save All';
            btn.disabled    = false;
            status.style.display = 'block';
            if ( res.success ) {
                status.style.background = '#f0fdf4';
                status.style.border     = '1px solid #86efac';
                status.style.color      = '#166534';
                status.textContent      = res.data;
                sgcFcChanged = false;
            } else {
                status.style.background = '#fef2f2';
                status.style.border     = '1px solid #fca5a5';
                status.style.color      = '#c0392b';
                status.textContent      = '❌ ' + (res.data || 'Error');
            }
        }).fail(function() {
            btn.textContent = '💾 Save All';
            btn.disabled    = false;
            status.style.display  = 'block';
            status.style.color    = '#c0392b';
            status.textContent    = '❌ Request failed';
        });
    }

    // Warn if leaving with unsaved changes
    window.onbeforeunload = function() {
        if ( sgcFcChanged ) return 'Unsaved changes hain — sure ho?';
    };
    </script>
    <?php
}

// ── ALSO: Disable old compare prices on init (default OFF) ───
add_action( 'wp_loaded', function() {
    // First time install — set default OFF for old widget
    if ( get_option( 'sgclabs_compare_prices_default_set' ) !== '1' ) {
        update_option( 'sgclabs_compare_prices_active', '0' );
        update_option( 'sgclabs_compare_prices_default_set', '1' );
    }
}, 1 );
