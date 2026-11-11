<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ============================================================
 * SGC LABS® SMART SIZE VARIANT LINKER — v6.6.3
 * ============================================================
 * Reads product title, extracts base name + size unit,
 * finds all sibling products in same category with same base name,
 * injects pill buttons (short desc) + text links (main desc)
 * — all hyperlinked to their product pages.
 *
 * Zero DB changes — pure frontend filter injection.
 * Cache: 12 hours per product (transient).
 * Works for ALL product types: Beaker, Flask, Burette, etc.
 * ============================================================
 */

// ── Admin menu ──
// Menu registered centrally in admin-menu.php

// ============================================================
// HELPER: Parse product title to extract base name + size
// ============================================================
function sgclabs_svl_parse( string $title ): array {
    // Match patterns like "Beaker 100ml", "Flask 250 ml", "Burette 50ML"
    $patterns = [
        '/^(.+?)\s+(\d+(?:\.\d+)?\s*(?:ml|l|litre|liter|cc|mm|cm|m|inch|in|oz|g|kg|µl|ul))\b/i',
        '/^(.+?)\s+(\d+(?:\.\d+)?\s*x\s*\d+(?:\.\d+)?\s*(?:mm|cm|m|inch|in))\b/i',
        '/^(.+?)\s+#(\d+)\b/i',
    ];
    foreach ( $patterns as $p ) {
        if ( preg_match( $p, trim( $title ), $m ) ) {
            return [
                'base'      => trim( $m[1] ),
                'full_size' => trim( $m[2] ),
                'original'  => $title,
            ];
        }
    }
    return [];
}

// ============================================================
// HELPER: Find sibling products with same base name in same category
// ============================================================
function sgclabs_svl_get_siblings( int $pid ): array {
    $title  = get_the_title( $pid );
    $parsed = sgclabs_svl_parse( $title );
    if ( empty( $parsed ) ) return [];

    $base = $parsed['base'];
    $cats = wp_get_post_terms( $pid, 'product_cat', [ 'fields' => 'ids' ] );
    if ( is_wp_error( $cats ) || empty( $cats ) ) return [];

    $args = [
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 50,
        'tax_query'      => [[ 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $cats ]],
        'fields'         => 'ids',
    ];
    $ids = get_posts( $args );

    $siblings = [];
    foreach ( $ids as $sid ) {
        $s_title  = get_the_title( $sid );
        $s_parsed = sgclabs_svl_parse( $s_title );
        if ( empty( $s_parsed ) ) continue;
        if ( strcasecmp( trim( $s_parsed['base'] ), trim( $base ) ) !== 0 ) continue;

        $siblings[] = [
            'pid'        => (int) $sid,
            'title'      => $s_title,
            'full_size'  => $s_parsed['full_size'],
            'is_current' => ( (int) $sid === $pid ),
            'url'        => get_permalink( $sid ),
        ];
    }

    // Sort by numeric size
    usort( $siblings, function( $a, $b ) {
        $na = (float) preg_replace( '/[^0-9.]/', '', $a['full_size'] );
        $nb = (float) preg_replace( '/[^0-9.]/', '', $b['full_size'] );
        return $na <=> $nb;
    });

    return count( $siblings ) > 1 ? $siblings : [];
}

// ============================================================
// HELPER: Build pill-style buttons HTML for size variants
// ============================================================
function sgclabs_svl_build_pills( array $siblings, int $current_pid ): string {
    if ( empty( $siblings ) ) return '';
    $html = '<div class="sgc-svl-pills" style="display:flex;flex-wrap:wrap;gap:6px;margin:8px 0;">';
    foreach ( $siblings as $s ) {
        if ( $s['is_current'] ) {
            $html .= '<span style="display:inline-block;padding:4px 12px;border-radius:4px;background:#1a1a2e;color:#fff;font-size:12px;font-weight:600;">' . esc_html( $s['full_size'] ) . '</span>';
        } else {
            $html .= '<a href="' . esc_url( $s['url'] ) . '" style="display:inline-block;padding:4px 12px;border-radius:4px;background:#f1f5f9;color:#334155;font-size:12px;text-decoration:none;border:1px solid #e2e8f0;" title="' . esc_attr( $s['title'] ) . '">' . esc_html( $s['full_size'] ) . '</a>';
        }
    }
    $html .= '</div>';
    return $html;
}

// ============================================================
// HELPER: Build text links HTML for size variants
// ============================================================
function sgclabs_svl_build_textlinks( array $siblings, int $current_pid ): string {
    if ( empty( $siblings ) ) return '';
    $links = [];
    foreach ( $siblings as $s ) {
        if ( $s['is_current'] ) {
            $links[] = '<strong>' . esc_html( $s['full_size'] ) . '</strong>';
        } else {
            $links[] = '<a href="' . esc_url( $s['url'] ) . '">' . esc_html( $s['full_size'] ) . '</a>';
        }
    }
    $base = esc_html( $siblings[0]['title'] ?? '' );
    $parsed = sgclabs_svl_parse( $base );
    $base_name = ! empty( $parsed['base'] ) ? esc_html( $parsed['base'] ) : 'This product';
    return '<p class="sgc-svl-text" style="font-size:13px;color:#475569;">' . $base_name . ' is also available in: ' . implode( ', ', $links ) . '.</p>';
}

// ============================================================
// AJAX: Preview for a specific product
// ============================================================
add_action( 'wp_ajax_sgclabs_svl_preview', function () {
    check_ajax_referer( 'sgclabs_svl_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $pid = (int) ( $_POST['pid'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'No product ID.' );

    // Clear cache so fresh result shown
    delete_transient( 'sgclabs_svl_' . $pid );

    $siblings = sgclabs_svl_get_siblings( $pid );
    $title    = get_the_title( $pid );
    $parsed   = sgclabs_svl_parse( $title );

    if ( empty( $siblings ) ) {
        wp_send_json_success( [
            'found'    => false,
            'title'    => $title,
            'parsed'   => $parsed,
            'message'  => $parsed
                ? 'Base: "' . $parsed['base'] . '" — No other products found with same base name in same category.'
                : 'Could not parse size from product title.',
        ] );
        return;
    }

    $pills = sgclabs_svl_build_pills( $siblings, $pid );
    $text  = sgclabs_svl_build_textlinks( $siblings, $pid );

    wp_send_json_success( [
        'found'    => true,
        'title'    => $title,
        'base'     => $parsed['base'] ?? '',
        'count'    => count( $siblings ),
        'siblings' => array_map( fn( $s ) => [
            'pid'        => $s['pid'],
            'title'      => $s['title'],
            'full_size'  => $s['full_size'],
            'is_current' => $s['is_current'],
            'url'        => $s['url'],
        ], $siblings ),
        'pills_html' => $pills,
        'text_html'  => $text,
    ] );
} );

// ============================================================
// AJAX: Save Settings
// ============================================================
add_action( 'wp_ajax_sgclabs_svl_save', function () {
    check_ajax_referer( 'sgclabs_svl_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $bool_fields = [ 'active', 'in_short', 'in_main' ];
    $text_fields = [ 'label', 'label2' ];
    $color_fields = [ 'color', 'accent' ];

    foreach ( $bool_fields as $field ) {
        $val = isset( $_POST[ $field ] ) ? '1' : '0';
        update_option( 'sgclabs_svl_' . $field, $val );
    }
    foreach ( $text_fields as $field ) {
        if ( isset( $_POST[ $field ] ) ) {
            update_option( 'sgclabs_svl_' . $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
        }
    }
    foreach ( $color_fields as $field ) {
        if ( isset( $_POST[ $field ] ) ) {
            $color = sanitize_hex_color( wp_unslash( $_POST[ $field ] ) );
            if ( $color ) {
                update_option( 'sgclabs_svl_' . $field, $color );
            }
        }
    }

    wp_send_json_success( [ 'message' => 'Settings saved.' ] );
} );

// ============================================================
// AJAX: Bulk scan — how many products have variants
// ============================================================
add_action( 'wp_ajax_sgclabs_svl_scan', function () {
    check_ajax_referer( 'sgclabs_svl_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    global $wpdb;
    $offset = max( 0, (int) ( $_POST['offset'] ?? 0 ) );
    $batch  = 20;

    $ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts}
         WHERE post_type='product' AND post_status='publish'
         ORDER BY ID ASC LIMIT %d OFFSET %d",
        $batch, $offset
    ) );

    $total  = (int) $wpdb->get_var(
        "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'"
    );

    $with_variants = 0;
    $no_size       = 0;
    $no_siblings   = 0;
    $sample        = [];

    foreach ( $ids as $pid ) {
        $pid    = (int) $pid;
        $title  = get_the_title( $pid );
        $parsed = sgclabs_svl_parse( $title );

        if ( ! $parsed ) { $no_size++; continue; }

        $siblings = sgclabs_svl_get_siblings( $pid );
        if ( empty( $siblings ) ) { $no_siblings++; continue; }

        $with_variants++;
        if ( count( $sample ) < 3 ) {
            $sample[] = [
                'title'    => $title,
                'base'     => $parsed['base'],
                'count'    => count( $siblings ),
                'sizes'    => array_column( $siblings, 'full_size' ),
            ];
        }
    }

    $done     = $offset + count( $ids );
    $percent  = $total > 0 ? min( 100, round( $done / $total * 100 ) ) : 0;
    $finished = count( $ids ) < $batch;

    wp_send_json_success( [
        'total'          => $total,
        'done'           => $done,
        'percent'        => $percent,
        'finished'       => $finished,
        'next_offset'    => $offset + $batch,
        'with_variants'  => $with_variants,
        'no_size'        => $no_size,
        'no_siblings'    => $no_siblings,
        'sample'         => $sample,
    ] );
} );

// ============================================================
// ADMIN PAGE
// ============================================================
function sgclabs_size_variant_page() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

    $brand  = esc_attr( get_option( 'sgclabs_claude_brand_color', '#1a1a2e' ) );
    $accent = esc_attr( get_option( 'sgclabs_claude_accent_color', '#27ae60' ) );
    $nonce  = wp_create_nonce( 'sgclabs_svl_nonce' );
    $ajax   = esc_js( admin_url( 'admin-ajax.php' ) );

    $opt_active   = get_option( 'sgclabs_svl_active',   '1' );
    $opt_in_short = get_option( 'sgclabs_svl_in_short', '1' );
    $opt_in_main  = get_option( 'sgclabs_svl_in_main',  '1' );
    $opt_color    = get_option( 'sgclabs_svl_color',    '#1a1a2e' );
    $opt_accent   = get_option( 'sgclabs_svl_accent',   '#27ae60' );
    $opt_label    = get_option( 'sgclabs_svl_label',    'Available Sizes:' );
    $opt_label2   = get_option( 'sgclabs_svl_label2',   'Also available in other sizes:' );

    // Get sample products for preview dropdown
    $sample_products = function_exists( 'wc_get_products' )
        ? wc_get_products( [ 'limit' => 100, 'status' => 'publish', 'return' => 'ids' ] )
        : [];
    ?>
    <style>
    .svl-wrap { max-width:1000px;font-family:'Segoe UI',Arial,sans-serif;padding-bottom:60px; }
    .svl-head { background:linear-gradient(135deg,<?php echo $brand ?>,<?php echo $accent ?>);padding:24px 28px;border-radius:10px;margin:18px 0 22px; }
    .svl-head h1 { color:#fff;margin:0;font-size:22px;font-weight:700; }
    .svl-head p  { color:rgba(255,255,255,.82);margin:5px 0 0;font-size:13px; }
    .svl-card { background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:22px;margin-bottom:18px; }
    .svl-card h2 { font-size:15px;font-weight:700;margin:0 0 14px; }
    .svl-tog-row { display:flex;align-items:center;gap:10px;margin-bottom:12px;padding:10px 14px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0; }
    .svl-tgl-lbl { display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:#374151;cursor:pointer; }
    .svl-tog { -webkit-appearance:none;appearance:none;width:38px;height:21px;background:#cbd5e1;border-radius:11px;position:relative;cursor:pointer;transition:background .2s;flex-shrink:0; }
    .svl-tog:checked { background:<?php echo $accent ?>; }
    .svl-tog::after { content:'';position:absolute;top:2.5px;left:2.5px;width:16px;height:16px;background:#fff;border-radius:50%;transition:left .2s;box-shadow:0 1px 3px rgba(0,0,0,.2); }
    .svl-tog:checked::after { left:19.5px; }
    .svl-row { display:flex;align-items:center;gap:12px;margin-bottom:12px;flex-wrap:wrap; }
    .svl-label { font-size:13px;font-weight:600;color:#374151;min-width:160px; }
    .svl-inp { padding:7px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;width:300px; }
    .svl-inp-sm { width:120px; }
    .svl-btn { padding:9px 22px;border-radius:6px;font-size:13px;font-weight:700;cursor:pointer;border:none; }
    .svl-btn-go  { background:#16a34a;color:#fff; }
    .svl-btn-blu { background:#2563eb;color:#fff; }
    .svl-btn-off { background:#dc2626;color:#fff; }
    .svl-btn-sec { background:#64748b;color:#fff; }
    .svl-btn:hover { opacity:.87; }
    .svl-btn:disabled { opacity:.4;cursor:not-allowed; }
    .svl-st { border-radius:6px;padding:10px 14px;font-size:13px;margin-top:8px;display:none; }
    .svl-ok   { background:#f0fdf4;border:1px solid #86efac;color:#166534; }
    .svl-info { background:#eff6ff;border:1px solid #93c5fd;color:#1d4ed8; }
    .svl-err  { background:#fef2f2;border:1px solid #fecaca;color:#991b1b; }
    .svl-pb-wrap { background:#f1f5f9;border-radius:5px;height:8px;margin:10px 0 5px;overflow:hidden; }
    .svl-pb { height:100%;background:linear-gradient(90deg,<?php echo $accent ?>,<?php echo $brand ?>);border-radius:5px;transition:width .4s; }
    /* Preview box */
    .svl-preview-box { border:1px solid #e2e8f0;border-radius:8px;padding:18px;margin-top:14px;background:#fafafa; }
    .svl-preview-box h3 { font-size:13px;font-weight:700;margin:0 0 10px;color:#374151; }
    .svl-sibling-row { display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #f1f5f9;font-size:13px; }
    .svl-sibling-row:last-child { border-bottom:none; }
    .svl-current-badge { background:#16a34a;color:#fff;font-size:10px;padding:2px 7px;border-radius:4px;font-weight:700; }
    /* Live preview of pills */
    .sgclabs-svl-pills a:hover { background:var(--svl-accent,#1a1a2e) !important;color:#fff !important; }
    </style>

    <div class="wrap svl-wrap">
        <div class="svl-head">
            <h1>🔗 Smart Size Variant Linker <span style="font-size:13px;opacity:.7;font-weight:400">v6.6.3</span></h1>
            <p>Product title se size auto-detect karo — saari sibling sizes hyperlink ke saath short + main description mein inject karo</p>
        </div>

        <!-- SETTINGS CARD -->
        <div class="svl-card">
            <h2>⚙️ Settings</h2>

            <div class="svl-tog-row">
                <label class="svl-tgl-lbl">
                    <input type="checkbox" class="svl-tog" id="svl-active" <?php checked($opt_active,'1') ?>>
                    <span>Enable Size Variant Linker (frontend injection)</span>
                </label>
            </div>

            <div class="svl-tog-row">
                <label class="svl-tgl-lbl">
                    <input type="checkbox" class="svl-tog" id="svl-in-short" <?php checked($opt_in_short,'1') ?>>
                    <span>Inject pill buttons in Short Description</span>
                </label>
            </div>

            <div class="svl-tog-row">
                <label class="svl-tgl-lbl">
                    <input type="checkbox" class="svl-tog" id="svl-in-main" <?php checked($opt_in_main,'1') ?>>
                    <span>Inject text links in Main Description</span>
                </label>
            </div>

            <div style="height:1px;background:#e2e8f0;margin:14px 0"></div>

            <div class="svl-row">
                <span class="svl-label">Brand Color</span>
                <input type="color" id="svl-color" value="<?php echo esc_attr($opt_color) ?>" style="width:48px;height:36px;border:1px solid #e2e8f0;border-radius:6px;cursor:pointer">
                <span style="font-size:12px;color:#64748b">Used for border, text links, label</span>
            </div>
            <div class="svl-row">
                <span class="svl-label">Accent Color</span>
                <input type="color" id="svl-accent" value="<?php echo esc_attr($opt_accent) ?>" style="width:48px;height:36px;border:1px solid #e2e8f0;border-radius:6px;cursor:pointer">
                <span style="font-size:12px;color:#64748b">Current size pill color</span>
            </div>
            <div class="svl-row">
                <span class="svl-label">Short Desc Label</span>
                <input type="text" class="svl-inp" id="svl-label" value="<?php echo esc_attr($opt_label) ?>" placeholder="Available Sizes:">
            </div>
            <div class="svl-row">
                <span class="svl-label">Main Desc Label</span>
                <input type="text" class="svl-inp" id="svl-label2" value="<?php echo esc_attr($opt_label2) ?>" placeholder="Also available in other sizes:">
            </div>

            <div style="margin-top:14px">
                <button class="svl-btn svl-btn-go" id="svl-save">💾 Save Settings</button>
                <span id="svl-save-msg" style="display:none;margin-left:10px;color:#16a34a;font-size:13px">✅ Saved!</span>
            </div>
        </div>

        <!-- PREVIEW CARD -->
        <div class="svl-card">
            <h2>👁️ Live Preview — Test Any Product</h2>
            <p style="font-size:13px;color:#64748b;margin-bottom:14px">Product select karo — dekho kaise size variants auto-detect honge aur kaise dikhenge.</p>

            <div class="svl-row">
                <select id="svl-preview-pid" style="flex:1;padding:9px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;min-width:280px;max-width:500px;">
                    <option value="">-- Product select karo --</option>
                    <?php foreach ( $sample_products as $pid ):
                        $p = wc_get_product( $pid );
                        if ( ! $p ) continue;
                    ?>
                    <option value="<?php echo $pid ?>"><?php echo esc_html( $p->get_name() ) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="svl-btn svl-btn-blu" id="svl-preview-btn">🔍 Preview</button>
            </div>

            <div class="svl-st svl-info" id="svl-preview-loading">⏳ Analyzing product...</div>
            <div id="svl-preview-result"></div>
        </div>

        <!-- SCAN CARD -->
        <div class="svl-card">
            <h2>📊 Bulk Scan — How Many Products Have Variants</h2>
            <p style="font-size:13px;color:#64748b;margin-bottom:14px">Poore catalog ko scan karo — janno kitne products mein size variants auto-link honge.</p>

            <div class="svl-pb-wrap"><div class="svl-pb" id="svl-scan-bar" style="width:0%"></div></div>
            <div style="font-size:12px;color:#64748b;margin-bottom:12px" id="svl-scan-ptxt">Ready to scan</div>

            <div style="display:flex;gap:10px;margin-bottom:10px">
                <button class="svl-btn svl-btn-go"  id="svl-scan-start">▶ Start Scan</button>
                <button class="svl-btn svl-btn-off" id="svl-scan-stop" style="display:none">⏹ Stop</button>
            </div>
            <div class="svl-st" id="svl-scan-status"></div>

            <!-- Scan results -->
            <div id="svl-scan-result" style="display:none;margin-top:14px;">
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:14px" id="svl-scan-stats"></div>
                <div id="svl-scan-samples"></div>
            </div>
        </div>

        <!-- HOW IT WORKS -->
        <div class="svl-card" style="background:#f8fafc">
            <h2>💡 How It Works</h2>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px">
                <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px">
                    <div style="font-size:20px;margin-bottom:8px">1️⃣</div>
                    <div style="font-weight:700;font-size:13px;margin-bottom:4px">Title Parse</div>
                    <div style="font-size:12px;color:#64748b">"Low Form Beaker 5ml"<br>→ Base: "Low Form Beaker"<br>→ Size: "5ml"</div>
                </div>
                <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px">
                    <div style="font-size:20px;margin-bottom:8px">2️⃣</div>
                    <div style="font-weight:700;font-size:13px;margin-bottom:4px">Sibling Search</div>
                    <div style="font-size:12px;color:#64748b">Same category mein sab products dhundo jinka base name same ho → sort by size</div>
                </div>
                <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px">
                    <div style="font-size:20px;margin-bottom:8px">3️⃣</div>
                    <div style="font-weight:700;font-size:13px;margin-bottom:4px">Auto Inject</div>
                    <div style="font-size:12px;color:#64748b">Short desc: pill buttons<br>Main desc: text links<br>All hyperlinked ✅</div>
                </div>
            </div>
            <div style="margin-top:14px;padding:12px;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;font-size:12px;color:#92400e">
                ⚡ <strong>Performance:</strong> Results 12 ghante cache honge — koi bhi DB query production page load pe nahi chalegi. Cache automatically clear hoti hai jab product update hota hai.
            </div>
        </div>
    </div>

    <script>
    (function($){
        var NONCE='<?php echo esc_js($nonce) ?>', AJAX='<?php echo $ajax ?>';

        // ── Save Settings ──
        $('#svl-save').on('click',function(e){
            e.preventDefault();
            $.post(AJAX,{
                action:'sgclabs_svl_save', nonce:NONCE,
                active:   $('#svl-active').is(':checked')?1:0,
                in_short: $('#svl-in-short').is(':checked')?1:0,
                in_main:  $('#svl-in-main').is(':checked')?1:0,
                color:    $('#svl-color').val(),
                accent:   $('#svl-accent').val(),
                label:    $('#svl-label').val(),
                label2:   $('#svl-label2').val(),
            },function(res){
                if(res&&res.success){
                    $('#svl-save-msg').show();
                    setTimeout(function(){$('#svl-save-msg').hide();},2500);
                } else {
                    alert('Save failed: '+(res&&res.data?res.data:'Unknown'));
                }
            });
        });

        // ── Preview ──
        $('#svl-preview-btn').on('click',function(e){
            e.preventDefault();
            var pid=$('#svl-preview-pid').val();
            if(!pid){alert('Product select karo.');return;}
            $('#svl-preview-loading').show();
            $('#svl-preview-result').html('');
            $.post(AJAX,{action:'sgclabs_svl_preview',nonce:NONCE,pid:pid},function(res){
                $('#svl-preview-loading').hide();
                if(!res||!res.success){
                    $('#svl-preview-result').html('<div class="svl-st svl-err" style="display:block">❌ Error: '+(res&&res.data?escH(res.data):'Unknown')+'</div>');
                    return;
                }
                var d=res.data;
                if(!d.found){
                    $('#svl-preview-result').html(
                        '<div class="svl-st svl-info" style="display:block">'
                        +'<strong>Product:</strong> '+escH(d.title)+'<br>'
                        +'<strong>Result:</strong> '+escH(d.message)+'</div>'
                    );
                    return;
                }

                var html='<div class="svl-preview-box">';
                html+='<h3>✅ '+d.count+' size variants found — Base: "'+escH(d.base)+'"</h3>';

                // Siblings table
                html+='<div style="margin-bottom:14px">';
                d.siblings.forEach(function(s){
                    html+='<div class="svl-sibling-row">';
                    html+='<span style="font-size:11px;color:#94a3b8;width:40px">#'+s.pid+'</span>';
                    html+='<strong style="min-width:80px">'+escH(s.full_size)+'</strong>';
                    html+='<span style="color:#64748b;flex:1">'+escH(s.title)+'</span>';
                    if(s.is_current){
                        html+='<span class="svl-current-badge">CURRENT</span>';
                    } else {
                        html+='<a href="'+escH(s.url)+'" target="_blank" style="font-size:11px;color:#2563eb">View →</a>';
                    }
                    html+='</div>';
                });
                html+='</div>';

                // Pills preview
                html+='<div style="margin-bottom:12px">';
                html+='<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748b;margin-bottom:6px">Short Description Preview (Pill Buttons):</div>';
                html+=d.pills_html;
                html+='</div>';

                // Text links preview
                html+='<div>';
                html+='<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748b;margin-bottom:6px">Main Description Preview (Text Links):</div>';
                html+=d.text_html;
                html+='</div>';

                html+='</div>';
                $('#svl-preview-result').html(html);
            }).fail(function(xhr){
                $('#svl-preview-loading').hide();
                $('#svl-preview-result').html('<div class="svl-st svl-err" style="display:block">❌ AJAX error (HTTP '+xhr.status+')</div>');
            });
        });

        // ── Bulk Scan ──
        var scanRunning=false, scanStopped=false, scanOffset=0;
        var totalWith=0, totalNoSize=0, totalNoSib=0;
        var allSamples=[];

        $('#svl-scan-start').on('click',function(e){
            e.preventDefault();
            if(scanRunning){alert('Already running!');return;}
            scanRunning=true; scanStopped=false;
            scanOffset=0; totalWith=0; totalNoSize=0; totalNoSib=0; allSamples=[];
            $(this).hide(); $('#svl-scan-stop').show();
            $('#svl-scan-status').removeClass('svl-ok svl-err').addClass('svl-info').show();
            $('#svl-scan-result').hide();
            scanBatch();
        });

        $('#svl-scan-stop').on('click',function(e){
            e.preventDefault();
            scanStopped=true; scanRunning=false;
            $(this).hide(); $('#svl-scan-start').show();
            $('#svl-scan-status').text('⏹ Scan stopped at offset '+scanOffset+'.');
        });

        function scanBatch(){
            if(scanStopped||!scanRunning)return;
            $('#svl-scan-status').text('⚡ Scanning products '+scanOffset+' to '+(scanOffset+20)+'...');
            $.post(AJAX,{action:'sgclabs_svl_scan',nonce:NONCE,offset:scanOffset},function(res){
                if(!res||!res.success){
                    scanRunning=false;
                    $('#svl-scan-start').show();$('#svl-scan-stop').hide();
                    $('#svl-scan-status').removeClass('svl-info').addClass('svl-err').text('❌ Error: '+(res&&res.data?res.data:'Unknown'));
                    return;
                }
                var d=res.data;
                totalWith   += d.with_variants;
                totalNoSize += d.no_size;
                totalNoSib  += d.no_siblings;
                if(d.sample) allSamples=allSamples.concat(d.sample).slice(0,5);

                $('#svl-scan-bar').css('width',d.percent+'%');
                $('#svl-scan-ptxt').text(d.done+' / '+d.total+' products scanned ('+d.percent+'%)');
                $('#svl-scan-status').text('Scanned '+d.done+'/'+d.total+' — Found '+totalWith+' products with size variants.');

                if(d.finished){
                    scanRunning=false;
                    $('#svl-scan-start').show();$('#svl-scan-stop').hide();
                    $('#svl-scan-status').removeClass('svl-info').addClass('svl-ok')
                        .text('✅ Scan complete! '+d.total+' products scanned.');
                    showScanResults(d.total);
                    return;
                }
                scanOffset=d.next_offset;
                setTimeout(scanBatch,100);
            }).fail(function(xhr){
                scanRunning=false;
                $('#svl-scan-start').show();$('#svl-scan-stop').hide();
                $('#svl-scan-status').removeClass('svl-info').addClass('svl-err').text('❌ AJAX error (HTTP '+xhr.status+')');
            });
        }

        function showScanResults(total){
            var stats='<div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px;text-align:center">'
                +'<div style="font-size:26px;font-weight:700;color:#16a34a">'+totalWith+'</div>'
                +'<div style="font-size:12px;color:#64748b">With Variants ✅</div></div>'
                +'<div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px;text-align:center">'
                +'<div style="font-size:26px;font-weight:700;color:#f59e0b">'+totalNoSib+'</div>'
                +'<div style="font-size:12px;color:#64748b">No Siblings Found</div></div>'
                +'<div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px;text-align:center">'
                +'<div style="font-size:26px;font-weight:700;color:#94a3b8">'+totalNoSize+'</div>'
                +'<div style="font-size:12px;color:#64748b">No Size in Title</div></div>';
            $('#svl-scan-stats').html(stats);

            if(allSamples.length>0){
                var shtml='<div style="font-size:12px;font-weight:700;color:#64748b;margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em">Sample Products With Variants:</div>';
                allSamples.forEach(function(s){
                    shtml+='<div style="padding:8px 12px;background:#f8fafc;border-radius:6px;margin-bottom:6px;font-size:13px">'
                        +'<strong>'+escH(s.base)+'</strong> — '
                        +s.count+' sizes: <span style="color:#2563eb">'+s.sizes.join(', ')+'</span></div>';
                });
                $('#svl-scan-samples').html(shtml);
            }
            $('#svl-scan-result').show();
        }

        function escH(s){
            return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

    })(jQuery);
    </script>
    <?php
}

// ============================================================
// Clear cache when product is updated
// ============================================================
add_action( 'woocommerce_update_product', function( $product_id ) {
    delete_transient( 'sgclabs_svl_' . (int) $product_id );
} );
add_action( 'save_post_product', function( $post_id ) {
    delete_transient( 'sgclabs_svl_' . (int) $post_id );
} );
