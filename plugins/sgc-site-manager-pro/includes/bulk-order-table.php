<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================
// SGC LABS® — BULK ORDER TABLE v6.8.0 [2026-04-08]
// VWR/Fisher Scientific style B2B ordering table
// Features:
//  1. Page: /bulk-order/ — auto-created
//  2. Shortcode: [sgclabs_bulk_order]
//  3. Live search by SKU / product name
//  4. Inline qty input per product
//  5. Bulk add all to cart
//  6. Running total
//  7. AJAX cart add
// ============================================================

// ── AUTO-CREATE PAGE ──────────────────────────────────────────
add_action( 'init', 'sgclabs_bulk_order_create_page' );
function sgclabs_bulk_order_create_page() {
    if ( get_option( 'sgclabs_bulk_order_page_created' ) ) return;

    $page = get_page_by_path( 'bulk-order' );
    if ( ! $page ) {
        $page_id = wp_insert_post( [
            'post_title'   => 'Bulk Order — SGC LABS®',
            'post_name'    => 'bulk-order',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '[sgclabs_bulk_order]',
        ] );
        if ( $page_id && ! is_wp_error( $page_id ) ) {
            update_option( 'sgclabs_bulk_order_page_created', $page_id );
        }
    } else {
        update_option( 'sgclabs_bulk_order_page_created', $page->ID );
    }
}

// ── SHORTCODE ─────────────────────────────────────────────────
add_shortcode( 'sgclabs_bulk_order', 'sgclabs_bulk_order_shortcode' );
function sgclabs_bulk_order_shortcode( $atts = [] ) {
    if ( ! class_exists( 'WooCommerce' ) ) return '<p>WooCommerce required.</p>';

    $nonce = wp_create_nonce( 'sgclabs_bulk_order' );
    ob_start();
    ?>
    <div class="sgclabs-bulk-order-wrap" id="sgc-bulk-order">

        <!-- Header -->
        <div class="sgc-bo-header">
            <div>
                <h2 class="sgc-bo-title">📋 Bulk Order</h2>
                <p class="sgc-bo-subtitle">Search by SKU or product name — add multiple items at once</p>
            </div>
            <a href="<?php echo wc_get_cart_url(); ?>" class="sgc-bo-cart-link">
                🛒 View Cart
                <span class="sgc-bo-cart-count" id="sgc-bo-cart-count"><?php echo WC()->cart ? WC()->cart->get_cart_contents_count() : 0; ?></span>
            </a>
        </div>

        <!-- Search Bar -->
        <div class="sgc-bo-search-wrap">
            <div class="sgc-bo-search-inner">
                <span class="sgc-bo-search-icon">🔍</span>
                <input type="text" id="sgc-bo-search"
                    placeholder="Search by SKU (e.g. BK250ML) or product name (e.g. Beaker)..."
                    autocomplete="off">
                <button class="sgc-bo-clear-btn" id="sgc-bo-clear" onclick="sgcBoClearSearch()" style="display:none;">✕</button>
            </div>
            <div id="sgc-bo-search-status" class="sgc-bo-search-status"></div>
        </div>

        <!-- Table -->
        <div class="sgc-bo-table-wrap">
            <table class="sgc-bo-table" id="sgc-bo-table">
                <thead>
                    <tr>
                        <th style="width:50px;">SKU</th>
                        <th>Product Name</th>
                        <th style="width:90px;">Category</th>
                        <th style="width:90px;">Unit Price</th>
                        <th style="width:90px;">Stock</th>
                        <th style="width:100px;">Qty</th>
                        <th style="width:100px;">Subtotal</th>
                    </tr>
                </thead>
                <tbody id="sgc-bo-tbody">
                    <tr class="sgc-bo-empty-row">
                        <td colspan="7" style="text-align:center;padding:40px;color:#9ca3af;">
                            <div style="font-size:36px;margin-bottom:10px;">🔍</div>
                            <div style="font-size:14px;font-weight:600;">Search for products above</div>
                            <div style="font-size:12px;margin-top:4px;">Type SKU like "BK250" or product name like "beaker"</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Footer Bar -->
        <div class="sgc-bo-footer" id="sgc-bo-footer" style="display:none;">
            <div class="sgc-bo-footer-info">
                <span id="sgc-bo-items-count">0 items</span> selected
                &nbsp;|&nbsp;
                Total: <strong id="sgc-bo-total">$0.00</strong>
            </div>
            <div class="sgc-bo-footer-actions">
                <button class="sgc-bo-btn-secondary" onclick="sgcBoClearAll()">Clear All Qty</button>
                <button class="sgc-bo-btn-primary" id="sgc-bo-add-btn" onclick="sgcBoAddToCart()">
                    🛒 Add All to Cart
                </button>
            </div>
        </div>

        <!-- Success message -->
        <div id="sgc-bo-success" class="sgc-bo-success" style="display:none;"></div>

    </div>

    <style>
    .sgclabs-bulk-order-wrap { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; max-width: 1000px; margin: 0 auto; }
    .sgc-bo-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
    .sgc-bo-title { font-size: 22px; font-weight: 700; color: #1a1a2e; margin: 0 0 4px; }
    .sgc-bo-subtitle { font-size: 13px; color: #6b7280; margin: 0; }
    .sgc-bo-cart-link { display: inline-flex; align-items: center; gap: 8px; background: #1565c0; color: #fff; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; }
    .sgc-bo-cart-count { background: #c62828; color: #fff; border-radius: 20px; padding: 1px 7px; font-size: 11px; font-weight: 700; }

    .sgc-bo-search-wrap { margin-bottom: 16px; }
    .sgc-bo-search-inner { display: flex; align-items: center; background: #fff; border: 2px solid #1565c0; border-radius: 10px; padding: 0 14px; gap: 10px; }
    .sgc-bo-search-icon { font-size: 16px; }
    .sgc-bo-search-inner input { flex: 1; border: none; outline: none; padding: 13px 0; font-size: 14px; color: #1a1a2e; background: transparent; }
    .sgc-bo-clear-btn { background: none; border: none; cursor: pointer; color: #9ca3af; font-size: 16px; padding: 4px; }
    .sgc-bo-search-status { font-size: 12px; color: #6b7280; margin-top: 6px; padding-left: 4px; min-height: 18px; }

    .sgc-bo-table-wrap { border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; margin-bottom: 0; }
    .sgc-bo-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .sgc-bo-table thead tr { background: #1a1a2e; }
    .sgc-bo-table thead th { padding: 11px 14px; text-align: left; font-size: 11px; font-weight: 600; color: rgba(255,255,255,.8); text-transform: uppercase; letter-spacing: .4px; }
    .sgc-bo-table tbody tr { border-bottom: 1px solid #f3f4f6; transition: background .15s; }
    .sgc-bo-table tbody tr:hover { background: #f9fafb; }
    .sgc-bo-table tbody tr:last-child { border-bottom: none; }
    .sgc-bo-table td { padding: 11px 14px; vertical-align: middle; }

    .sgc-bo-sku { font-family: monospace; font-size: 12px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; color: #475569; }
    .sgc-bo-name { font-weight: 600; color: #1a1a2e; }
    .sgc-bo-name a { color: #1a1a2e; text-decoration: none; }
    .sgc-bo-name a:hover { color: #1565c0; text-decoration: underline; }
    .sgc-bo-cat { font-size: 11px; color: #6b7280; }
    .sgc-bo-price { font-weight: 700; color: #1565c0; }
    .sgc-bo-stock-ok  { color: #166534; font-size: 12px; font-weight: 600; }
    .sgc-bo-stock-low { color: #c0392b; font-size: 12px; font-weight: 600; }
    .sgc-bo-stock-out { color: #9ca3af; font-size: 12px; }

    .sgc-bo-qty-input { width: 72px; padding: 7px 10px; border: 1.5px solid #d1d5db; border-radius: 6px; font-size: 13px; font-weight: 600; text-align: center; color: #1a1a2e; transition: border-color .2s; }
    .sgc-bo-qty-input:focus { border-color: #1565c0; outline: none; }
    .sgc-bo-qty-input.has-qty { border-color: #27ae60; background: #f0fdf4; }

    .sgc-bo-subtotal { font-weight: 700; color: #1a1a2e; font-size: 13px; }
    .sgc-bo-subtotal.active { color: #166534; }

    .sgc-bo-footer { display: flex; justify-content: space-between; align-items: center; background: #1a1a2e; padding: 16px 20px; border-radius: 0 0 10px 10px; flex-wrap: wrap; gap: 12px; margin-top: -1px; }
    .sgc-bo-footer-info { color: rgba(255,255,255,.8); font-size: 13px; }
    .sgc-bo-footer-info strong { color: #fff; font-size: 15px; }
    .sgc-bo-footer-actions { display: flex; gap: 10px; }
    .sgc-bo-btn-secondary { background: transparent; border: 1px solid rgba(255,255,255,.3); color: rgba(255,255,255,.7); padding: 9px 16px; border-radius: 7px; font-size: 13px; cursor: pointer; }
    .sgc-bo-btn-primary { background: #27ae60; border: none; color: #fff; padding: 9px 20px; border-radius: 7px; font-size: 13px; font-weight: 700; cursor: pointer; transition: background .2s; }
    .sgc-bo-btn-primary:hover { background: #219a52; }
    .sgc-bo-btn-primary:disabled { background: #9ca3af; cursor: not-allowed; }

    .sgc-bo-success { background: #f0fdf4; border: 1.5px solid #86efac; border-radius: 8px; padding: 14px 18px; margin-top: 12px; font-size: 14px; color: #166534; font-weight: 600; text-align: center; }
    .sgc-bo-empty-row td { background: #fafafa; }

    @media(max-width:640px) {
        .sgc-bo-table thead th:nth-child(3),
        .sgc-bo-table td:nth-child(3),
        .sgc-bo-table thead th:nth-child(5),
        .sgc-bo-table td:nth-child(5) { display: none; }
    }
    </style>

    <script>
    var sgcBoNonce     = '<?php echo $nonce; ?>';
    var sgcBoCartUrl   = '<?php echo wc_get_cart_url(); ?>';
    var sgcBoAjaxUrl   = '<?php echo admin_url("admin-ajax.php"); ?>';
    var sgcBoSearchTimer = null;
    var sgcBoProducts  = {};  // pid => {price, name}

    // Search
    document.getElementById('sgc-bo-search').addEventListener('input', function() {
        var q = this.value.trim();
        document.getElementById('sgc-bo-clear').style.display = q ? 'block' : 'none';
        clearTimeout( sgcBoSearchTimer );
        if ( q.length < 2 ) {
            if ( q.length === 0 ) sgcBoResetTable();
            return;
        }
        sgcBoSearchTimer = setTimeout( function() { sgcBoSearch(q); }, 350 );
    });

    function sgcBoSearch(q) {
        var status = document.getElementById('sgc-bo-search-status');
        status.textContent = '🔍 Searching...';

        jQuery.post(sgcBoAjaxUrl, {
            action: 'sgclabs_bulk_order_search',
            nonce:  sgcBoNonce,
            q:      q,
        }, function(res) {
            if ( res.success ) {
                sgcBoRenderResults( res.data );
                status.textContent = res.data.length + ' product' + (res.data.length !== 1 ? 's' : '') + ' found';
            } else {
                status.textContent = 'No products found for "' + q + '"';
                sgcBoResetTable();
            }
        });
    }

    function sgcBoRenderResults(products) {
        var tbody = document.getElementById('sgc-bo-tbody');
        tbody.innerHTML = '';

        if ( ! products.length ) {
            tbody.innerHTML = '<tr class="sgc-bo-empty-row"><td colspan="7" style="text-align:center;padding:30px;color:#9ca3af;">No products found</td></tr>';
            return;
        }

        products.forEach(function(p) {
            sgcBoProducts[p.id] = { price: p.price, name: p.name };

            var stockHtml = p.in_stock
                ? ( p.stock_qty !== null && p.stock_qty <= 50
                    ? '<span class="sgc-bo-stock-low">⚡ Only ' + p.stock_qty + ' left</span>'
                    : '<span class="sgc-bo-stock-ok">✓ In Stock</span>' )
                : '<span class="sgc-bo-stock-out">Out of Stock</span>';

            var tr = document.createElement('tr');
            tr.dataset.pid   = p.id;
            tr.dataset.price = p.price;
            tr.innerHTML =
                '<td><span class="sgc-bo-sku">' + p.sku + '</span></td>' +
                '<td><div class="sgc-bo-name"><a href="' + p.url + '" target="_blank">' + p.name + '</a></div></td>' +
                '<td><span class="sgc-bo-cat">' + p.cat + '</span></td>' +
                '<td><span class="sgc-bo-price">$' + parseFloat(p.price).toFixed(2) + '</span></td>' +
                '<td>' + stockHtml + '</td>' +
                '<td><input type="number" class="sgc-bo-qty-input" data-pid="' + p.id + '" min="0" value="" placeholder="0" ' + (!p.in_stock ? 'disabled' : '') + ' oninput="sgcBoUpdateRow(this)"></td>' +
                '<td><span class="sgc-bo-subtotal" id="sgc-bo-sub-' + p.id + '">—</span></td>';
            tbody.appendChild(tr);
        });

        sgcBoUpdateFooter();
    }

    function sgcBoUpdateRow(input) {
        var pid      = input.dataset.pid;
        var qty      = parseInt(input.value) || 0;
        var price    = parseFloat(input.closest('tr').dataset.price);
        var subEl    = document.getElementById('sgc-bo-sub-' + pid);

        input.classList.toggle('has-qty', qty > 0);

        if ( qty > 0 ) {
            var sub = (price * qty).toFixed(2);
            subEl.textContent = '$' + sub;
            subEl.classList.add('active');
        } else {
            subEl.textContent = '—';
            subEl.classList.remove('active');
        }
        sgcBoUpdateFooter();
    }

    function sgcBoUpdateFooter() {
        var inputs   = document.querySelectorAll('.sgc-bo-qty-input');
        var total    = 0;
        var items    = 0;
        inputs.forEach(function(inp) {
            var qty   = parseInt(inp.value) || 0;
            var price = parseFloat(inp.closest('tr').dataset.price) || 0;
            if ( qty > 0 ) { total += price * qty; items += qty; }
        });
        document.getElementById('sgc-bo-total').textContent        = '$' + total.toFixed(2);
        document.getElementById('sgc-bo-items-count').textContent   = items;
        document.getElementById('sgc-bo-footer').style.display      = items > 0 ? 'flex' : 'none';
    }

    function sgcBoClearSearch() {
        document.getElementById('sgc-bo-search').value = '';
        document.getElementById('sgc-bo-clear').style.display = 'none';
        document.getElementById('sgc-bo-search-status').textContent = '';
        sgcBoResetTable();
    }

    function sgcBoResetTable() {
        document.getElementById('sgc-bo-tbody').innerHTML =
            '<tr class="sgc-bo-empty-row"><td colspan="7" style="text-align:center;padding:40px;color:#9ca3af;"><div style="font-size:36px;margin-bottom:10px;">🔍</div><div style="font-size:14px;font-weight:600;">Search for products above</div><div style="font-size:12px;margin-top:4px;">Type SKU like "BK250" or product name like "beaker"</div></td></tr>';
        document.getElementById('sgc-bo-footer').style.display = 'none';
        sgcBoProducts = {};
    }

    function sgcBoClearAll() {
        document.querySelectorAll('.sgc-bo-qty-input').forEach(function(inp) {
            inp.value = '';
            inp.classList.remove('has-qty');
            var pid = inp.dataset.pid;
            var subEl = document.getElementById('sgc-bo-sub-' + pid);
            if ( subEl ) { subEl.textContent = '—'; subEl.classList.remove('active'); }
        });
        sgcBoUpdateFooter();
    }

    function sgcBoAddToCart() {
        var btn   = document.getElementById('sgc-bo-add-btn');
        var items = [];

        document.querySelectorAll('.sgc-bo-qty-input').forEach(function(inp) {
            var qty = parseInt(inp.value) || 0;
            var pid = inp.dataset.pid;
            if ( qty > 0 && pid ) items.push({ product_id: pid, qty: qty });
        });

        if ( ! items.length ) return;

        btn.disabled    = true;
        btn.textContent = '⏳ Adding...';

        jQuery.post(sgcBoAjaxUrl, {
            action: 'sgclabs_bulk_order_add_cart',
            nonce:  sgcBoNonce,
            items:  JSON.stringify(items),
        }, function(res) {
            btn.disabled = false;
            if ( res.success ) {
                btn.textContent = '🛒 Add All to Cart';
                var success = document.getElementById('sgc-bo-success');
                success.style.display = 'block';
                success.textContent   = '✅ ' + res.data.added + ' item type(s) added to cart! ' + res.data.total + ' pcs total.';
                document.getElementById('sgc-bo-cart-count').textContent = res.data.cart_count;
                setTimeout(function() { success.style.display = 'none'; }, 5000);
                sgcBoClearAll();
            } else {
                btn.textContent = '🛒 Add All to Cart';
                alert('Error: ' + (res.data || 'Could not add to cart'));
            }
        }).fail(function() {
            btn.disabled    = false;
            btn.textContent = '🛒 Add All to Cart';
            alert('Request failed. Please try again.');
        });
    }
    </script>
    <?php
    return ob_get_clean();
}

// ── AJAX: Search products ─────────────────────────────────────
add_action( 'wp_ajax_sgclabs_bulk_order_search',        'sgclabs_bulk_order_search_handler' );
add_action( 'wp_ajax_nopriv_sgclabs_bulk_order_search', 'sgclabs_bulk_order_search_handler' );
function sgclabs_bulk_order_search_handler() {
    check_ajax_referer( 'sgclabs_bulk_order', 'nonce' );

    $q      = sanitize_text_field( $_POST['q'] ?? '' );
    $limit  = 20;

    if ( strlen( $q ) < 2 ) wp_send_json_error( 'Query too short' );

    // Search by SKU first, then name
    $args = [
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        's'              => $q,
    ];

    // Also search by SKU
    add_filter( 'posts_search', function( $search, $wp_query ) use ( $q ) {
        global $wpdb;
        if ( ! $wp_query->is_search ) return $search;
        $like   = '%' . $wpdb->esc_like( $q ) . '%';
        $search .= $wpdb->prepare(
            " OR EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm
                WHERE pm.post_id = {$wpdb->posts}.ID
                  AND pm.meta_key = '_sku'
                  AND pm.meta_value LIKE %s
            )",
            $like
        );
        return $search;
    }, 10, 2 );

    $posts   = get_posts( $args );
    $results = [];

    foreach ( $posts as $post ) {
        $product = wc_get_product( $post->ID );
        if ( ! $product ) continue;

        $cats    = wp_get_post_terms( $post->ID, 'product_cat', [ 'fields' => 'names' ] );
        $cat_str = ! empty( $cats ) ? $cats[0] : '';

        $stock_qty = $product->managing_stock() ? $product->get_stock_quantity() : null;

        $results[] = [
            'id'        => $post->ID,
            'name'      => $product->get_name(),
            'sku'       => $product->get_sku() ?: '—',
            'price'     => (float) $product->get_price(),
            'url'       => get_permalink( $post->ID ),
            'cat'       => $cat_str,
            'in_stock'  => $product->is_in_stock(),
            'stock_qty' => $stock_qty,
        ];
    }

    if ( empty( $results ) ) wp_send_json_error( 'No results' );
    wp_send_json_success( $results );
}

// ── AJAX: Add to cart ─────────────────────────────────────────
add_action( 'wp_ajax_sgclabs_bulk_order_add_cart',        'sgclabs_bulk_order_add_cart_handler' );
add_action( 'wp_ajax_nopriv_sgclabs_bulk_order_add_cart', 'sgclabs_bulk_order_add_cart_handler' );
function sgclabs_bulk_order_add_cart_handler() {
    check_ajax_referer( 'sgclabs_bulk_order', 'nonce' );

    $items_raw = sanitize_text_field( $_POST['items'] ?? '[]' );
    $items     = json_decode( stripslashes( $items_raw ), true );

    if ( empty( $items ) || ! is_array( $items ) ) wp_send_json_error( 'No items' );

    $added      = 0;
    $total_qty  = 0;
    $errors     = [];

    foreach ( $items as $item ) {
        $pid = (int)( $item['product_id'] ?? 0 );
        $qty = (int)( $item['qty'] ?? 0 );
        if ( ! $pid || $qty <= 0 ) continue;

        $product = wc_get_product( $pid );
        if ( ! $product || ! $product->is_in_stock() ) {
            $errors[] = $product ? $product->get_name() . ' is out of stock' : 'Product #' . $pid . ' not found';
            continue;
        }

        $result = WC()->cart->add_to_cart( $pid, $qty );
        if ( $result ) {
            $added++;
            $total_qty += $qty;
        } else {
            $errors[] = $product->get_name() . ' could not be added';
        }
    }

    if ( $added === 0 ) {
        wp_send_json_error( implode( ', ', $errors ) ?: 'Nothing added' );
    }

    wp_send_json_success( [
        'added'      => $added,
        'total'      => $total_qty,
        'cart_count' => WC()->cart->get_cart_contents_count(),
        'cart_url'   => wc_get_cart_url(),
        'errors'     => $errors,
    ] );
}

// ── ADMIN MENU ────────────────────────────────────────────────
// Menu registered via admin-menu.php → WooCommerce → Bulk Order tab

function sgclabs_bulk_order_admin_page() {
    $page_id  = get_option( 'sgclabs_bulk_order_page_created', 0 );
    $page_url = $page_id ? get_permalink( $page_id ) : home_url( '/bulk-order/' );
    $edit_url = $page_id ? get_edit_post_link( $page_id ) : '';
    ?>
    <div class="wrap">
    <h1 style="color:#1a1a2e;">📋 Bulk Order Table</h1>

    <div style="background:#f0fdf4;border:1.5px solid #86efac;border-radius:10px;padding:16px 20px;max-width:650px;margin:16px 0 24px;">
        <div style="font-size:14px;font-weight:700;color:#166534;margin-bottom:6px;">✅ Bulk Order Page is Live!</div>
        <div style="font-size:13px;color:#374151;margin-bottom:10px;">Share this link with your B2B customers:</div>
        <div style="display:flex;gap:10px;align-items:center;">
            <input type="text" value="<?php echo esc_url( $page_url ); ?>" readonly
                style="flex:1;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;background:#fff;cursor:pointer;"
                onclick="this.select();document.execCommand('copy');this.value='<?php echo esc_js( $page_url ); ?>';">
            <a href="<?php echo esc_url( $page_url ); ?>" target="_blank" class="button button-primary" style="background:#1565c0;border-color:#1565c0;border-radius:6px;padding:8px 16px;">
                🔗 View Page →
            </a>
        </div>
    </div>

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;max-width:650px;">
        <h3 style="margin:0 0 14px;font-size:15px;color:#1a1a2e;">📖 How to Use</h3>
        <ol style="font-size:13px;color:#374151;line-height:2.2;padding-left:20px;">
            <li>Share <strong><?php echo esc_html( $page_url ); ?></strong> with your customers</li>
            <li>Customer search karta hai — SKU ya product name</li>
            <li>Multiple products ka qty set karta hai</li>
            <li>"Add All to Cart" click karta hai</li>
            <li>Checkout → Order complete!</li>
        </ol>
        <div style="background:#f8f9fa;border-radius:8px;padding:12px;margin-top:12px;font-size:12px;color:#6b7280;">
            💡 Shortcode: <code style="background:#e5e7eb;padding:2px 6px;border-radius:4px;">[sgclabs_bulk_order]</code> — kisi bhi page/post mein add karo
        </div>
        <?php if ( $edit_url ): ?>
        <a href="<?php echo esc_url( $edit_url ); ?>" class="button" style="margin-top:12px;border-radius:6px;">✏️ Edit Bulk Order Page</a>
        <?php endif; ?>
    </div>
    </div>
    <?php
}
