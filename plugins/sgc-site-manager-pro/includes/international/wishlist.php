<?php
/**
 * SGC LABS® B2B Wishlist
 *
 * "Save for Quote" — international buyers add products to wishlist,
 * then send the entire list as a quote request in one click.
 *
 * - Cookie-based for guests (anonymous list)
 * - WP user_meta for logged-in customers (persistent across devices)
 * - REST endpoint exposed for headless apps
 * - Email-to-quote conversion via existing get-a-quote module
 *
 * @package   SGCLabs\International
 * @since     6.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'SGCLabs_Wishlist' ) ) :

final class SGCLabs_Wishlist {

    const COOKIE   = 'sgc_wishlist';
    const META_KEY = 'sgclabs_wishlist';

    public static function get(): array {
        if ( is_user_logged_in() ) {
            $list = get_user_meta( get_current_user_id(), self::META_KEY, true );
            return is_array( $list ) ? array_map( 'absint', $list ) : [];
        }
        if ( ! empty( $_COOKIE[ self::COOKIE ] ) ) {
            $list = json_decode( wp_unslash( $_COOKIE[ self::COOKIE ] ), true );
            return is_array( $list ) ? array_map( 'absint', $list ) : [];
        }
        return [];
    }

    public static function set( array $ids ): void {
        $ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
        $ids = array_slice( $ids, 0, 100 ); // hard cap

        if ( is_user_logged_in() ) {
            update_user_meta( get_current_user_id(), self::META_KEY, $ids );
        } else {
            $secure = is_ssl();
            setcookie( self::COOKIE, wp_json_encode( $ids ), time() + 30 * DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, $secure, false );
            $_COOKIE[ self::COOKIE ] = wp_json_encode( $ids );
        }
    }

    public static function add( int $id ): void {
        $list = self::get();
        if ( ! in_array( $id, $list, true ) ) $list[] = $id;
        self::set( $list );
    }

    public static function remove( int $id ): void {
        $list = self::get();
        self::set( array_diff( $list, [ $id ] ) );
    }

    public static function count(): int {
        return count( self::get() );
    }

    public static function clear(): void {
        self::set( [] );
    }
}

endif;

// Disabled?
add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'SGCLabs_Registry' ) ) return;
    if ( ! SGCLabs_Registry::is_active( 'wishlist' ) ) return;

    add_action( 'wp_ajax_sgclabs_wishlist',        'sgclabs_wishlist_ajax' );
    add_action( 'wp_ajax_nopriv_sgclabs_wishlist', 'sgclabs_wishlist_ajax' );
    add_action( 'woocommerce_after_add_to_cart_button', 'sgclabs_wishlist_button' );
    add_shortcode( 'sgc_wishlist', 'sgclabs_wishlist_shortcode' );
}, 30 );

function sgclabs_wishlist_ajax(): void {
    check_ajax_referer( 'sgclabs_wishlist', '_wpnonce' );
    $op  = sanitize_key( wp_unslash( $_POST['op']  ?? '' ) );
    $id  = absint( $_POST['id'] ?? 0 );

    switch ( $op ) {
        case 'add':    SGCLabs_Wishlist::add( $id );    break;
        case 'remove': SGCLabs_Wishlist::remove( $id ); break;
        case 'clear':  SGCLabs_Wishlist::clear();        break;
    }
    wp_send_json_success( [ 'count' => SGCLabs_Wishlist::count(), 'list' => SGCLabs_Wishlist::get() ] );
}

function sgclabs_wishlist_button(): void {
    global $product;
    if ( ! $product ) return;
    $id    = $product->get_id();
    $list  = SGCLabs_Wishlist::get();
    $on    = in_array( $id, $list, true );
    $nonce = wp_create_nonce( 'sgclabs_wishlist' );
    ?>
    <button type="button"
            class="sgc-wishlist-btn"
            data-id="<?php echo (int) $id; ?>"
            data-on="<?php echo $on ? 1 : 0; ?>"
            onclick="sgcWishlistToggle(this)"
            style="margin-top:10px;background:<?php echo $on ? '#dc2626' : 'transparent'; ?>;
                   color:<?php echo $on ? '#fff' : '#dc2626'; ?>;
                   border:1.5px solid #dc2626;padding:10px 16px;border-radius:8px;cursor:pointer;font-weight:600;">
        <span class="sgc-wl-icon"><?php echo $on ? '❤️' : '🤍'; ?></span>
        <span class="sgc-wl-txt"><?php echo $on ? 'Saved for Quote' : 'Save for Quote'; ?></span>
    </button>
    <script>
    window.SGC_WL_NONCE = window.SGC_WL_NONCE || <?php echo wp_json_encode( $nonce ); ?>;
    window.SGC_WL_AJAX  = window.SGC_WL_AJAX  || <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
    window.sgcWishlistToggle = window.sgcWishlistToggle || function(btn){
        var id  = btn.dataset.id;
        var on  = btn.dataset.on === '1';
        var op  = on ? 'remove' : 'add';
        var fd  = new FormData();
        fd.append('action','sgclabs_wishlist');
        fd.append('_wpnonce', window.SGC_WL_NONCE);
        fd.append('op', op); fd.append('id', id);
        fetch(window.SGC_WL_AJAX, {method:'POST',body:fd,credentials:'same-origin'})
            .then(r=>r.json()).then(d=>{
                btn.dataset.on = on ? '0' : '1';
                btn.style.background = on ? 'transparent' : '#dc2626';
                btn.style.color      = on ? '#dc2626' : '#fff';
                btn.querySelector('.sgc-wl-icon').textContent = on ? '🤍' : '❤️';
                btn.querySelector('.sgc-wl-txt').textContent  = on ? 'Save for Quote' : 'Saved for Quote';
            });
    };
    </script>
    <?php
}

function sgclabs_wishlist_shortcode(): string {
    $list = SGCLabs_Wishlist::get();
    if ( empty( $list ) ) {
        return '<div style="padding:30px;text-align:center;color:#64748b;background:#f8fafc;border-radius:10px;">'
             . '<p style="font-size:14px;">Aapki wishlist abhi khali hai.</p>'
             . '<p style="font-size:13px;">Products pe "Save for Quote" button click karke add karein.</p>'
             . '</div>';
    }
    ob_start();
    ?>
    <div class="sgc-wishlist-page">
        <h2>❤️ My Wishlist (<?php echo count( $list ); ?>)</h2>
        <table class="widefat" style="margin:20px 0;">
            <thead><tr><th>Product</th><th style="width:120px;">SKU</th><th style="width:100px;">Action</th></tr></thead>
            <tbody>
            <?php foreach ( $list as $pid ):
                $p = wc_get_product( $pid ); if ( ! $p ) continue; ?>
                <tr>
                    <td><a href="<?php echo esc_url( get_permalink( $pid ) ); ?>"><?php echo esc_html( $p->get_name() ); ?></a></td>
                    <td><code><?php echo esc_html( $p->get_sku() ); ?></code></td>
                    <td><a href="?wl_remove=<?php echo (int) $pid; ?>" style="color:#dc2626;">Remove</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p>
            <a href="<?php echo esc_url( home_url( '/request-quote/' ) ); ?>"
               style="display:inline-block;background:#1e40af;color:#fff;padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:600;">
                📩 Send entire wishlist as quote request
            </a>
        </p>
    </div>
    <?php
    return ob_get_clean();
}

// Handle inline remove
add_action( 'template_redirect', function() {
    if ( ! empty( $_GET['wl_remove'] ) ) {
        SGCLabs_Wishlist::remove( absint( $_GET['wl_remove'] ) );
        wp_safe_redirect( remove_query_arg( 'wl_remove' ) );
        exit;
    }
} );

// ─────────────────────────────────────────────────────────────────────
// FLOATING WISHLIST + COMPARE BAR (bottom of every page)
// Shows count badges — click opens slide-out panel with saved items
// ─────────────────────────────────────────────────────────────────────
add_action( 'wp_footer', 'sgclabs_wishlist_compare_floating_bar', 50 );
function sgclabs_wishlist_compare_floating_bar() {
    if ( ! function_exists( 'is_woocommerce' ) ) return;
    if ( is_admin() ) return;

    $wl_count  = SGCLabs_Wishlist::count();
    $cmp_count = 0;
    if ( class_exists( 'SGCLabs_Compare' ) ) {
        $cmp_list  = isset( $_COOKIE['sgc_compare'] ) ? json_decode( wp_unslash( $_COOKIE['sgc_compare'] ), true ) : [];
        $cmp_count = is_array( $cmp_list ) ? count( $cmp_list ) : 0;
    }
    $total = $wl_count + $cmp_count;

    // Find the wishlist + compare pages
    $wl_page  = home_url( '/my-wishlist/' );  // shortcode: [sgc_wishlist]
    $cmp_page = home_url( '/compare/' );       // shortcode: [sgc_compare]
    $nonce    = wp_create_nonce( 'sgclabs_wishlist' );
    ?>
    <!-- SGC LABS® Floating Wishlist + Compare Bar -->
    <style>
    #sgc-wc-bar{position:fixed;bottom:0;left:0;right:0;z-index:9990;background:#1a1a2e;color:#fff;display:flex;align-items:center;justify-content:center;gap:16px;padding:10px 20px;font-size:13px;box-shadow:0 -4px 20px rgba(0,0,0,.2);transform:translateY(100%);transition:transform .3s ease;}
    #sgc-wc-bar.visible{transform:translateY(0);}
    .sgc-bar-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:20px;border:none;cursor:pointer;font-size:12px;font-weight:600;text-decoration:none;transition:all .15s;}
    .sgc-bar-btn:hover{transform:scale(1.05);}
    .sgc-bar-wl{background:#e11d48;color:#fff;}
    .sgc-bar-cmp{background:#0ea5e9;color:#fff;}
    .sgc-bar-quote{background:#16a34a;color:#fff;}
    .sgc-bar-badge{background:rgba(255,255,255,.3);padding:1px 7px;border-radius:10px;font-size:11px;min-width:18px;text-align:center;}
    .sgc-bar-close{background:none;border:none;color:#94a3b8;cursor:pointer;font-size:18px;padding:4px 8px;}
    .sgc-bar-close:hover{color:#fff;}

    /* Slide-out panel */
    #sgc-wc-panel{position:fixed;top:0;right:-420px;width:400px;max-width:90vw;height:100vh;z-index:99991;background:#fff;box-shadow:-8px 0 30px rgba(0,0,0,.2);transition:right .3s ease;overflow-y:auto;}
    #sgc-wc-panel.open{right:0;}
    #sgc-wc-panel-overlay{display:none;position:fixed;inset:0;z-index:99990;background:rgba(0,0,0,.4);}
    #sgc-wc-panel-overlay.open{display:block;}
    .sgc-panel-hdr{background:#1a1a2e;color:#fff;padding:16px 20px;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:1;}
    .sgc-panel-body{padding:16px;}
    .sgc-panel-item{display:flex;gap:10px;align-items:center;padding:10px 0;border-bottom:1px solid #f3f4f6;font-size:13px;}
    .sgc-panel-item img{width:50px;height:50px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb;}
    .sgc-panel-item-info{flex:1;}
    .sgc-panel-item-name{font-weight:600;color:#1a1a2e;text-decoration:none;}
    .sgc-panel-item-name:hover{color:#0ea5e9;}
    .sgc-panel-item-sku{font-size:11px;color:#888;}
    .sgc-panel-item-remove{background:none;border:none;color:#dc2626;cursor:pointer;font-size:14px;}
    .sgc-panel-empty{text-align:center;padding:40px 20px;color:#888;}
    .sgc-panel-actions{padding:16px;border-top:1px solid #e5e7eb;position:sticky;bottom:0;background:#fff;}
    </style>

    <div id="sgc-wc-bar" class="<?php echo $total > 0 ? 'visible' : ''; ?>">
        <button class="sgc-bar-btn sgc-bar-wl" onclick="sgcPanelOpen('wishlist')" title="View saved items">
            ❤️ Wishlist <span class="sgc-bar-badge" id="sgc-wl-count"><?php echo esc_html( (string) $wl_count ); ?></span>
        </button>
        <button class="sgc-bar-btn sgc-bar-cmp" onclick="sgcPanelOpen('compare')" title="Compare products">
            ⚖️ Compare <span class="sgc-bar-badge" id="sgc-cmp-count"><?php echo esc_html( (string) $cmp_count ); ?></span>
        </button>
        <a href="<?php echo esc_url( home_url( '/?page_id=quote' ) ); ?>" class="sgc-bar-btn sgc-bar-quote" onclick="event.preventDefault();sgcPanelOpen('wishlist');">
            📩 Request Quote
        </a>
        <button class="sgc-bar-close" onclick="document.getElementById('sgc-wc-bar').classList.remove('visible')" title="Hide bar">✕</button>
    </div>

    <!-- Slide-out panel -->
    <div id="sgc-wc-panel-overlay" onclick="sgcPanelClose()"></div>
    <div id="sgc-wc-panel">
        <div class="sgc-panel-hdr">
            <span id="sgc-panel-title">❤️ My Wishlist</span>
            <button onclick="sgcPanelClose()" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;">✕</button>
        </div>
        <div class="sgc-panel-body" id="sgc-panel-body">
            <div class="sgc-panel-empty">Loading...</div>
        </div>
        <div class="sgc-panel-actions" id="sgc-panel-actions"></div>
    </div>

    <script>
    (function(){
    var ajaxUrl='<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
    var nonce='<?php echo esc_js( $nonce ); ?>';

    window.sgcPanelOpen=function(type){
        document.getElementById('sgc-wc-panel').classList.add('open');
        document.getElementById('sgc-wc-panel-overlay').classList.add('open');
        document.body.style.overflow='hidden';
        var title=type==='compare'?'⚖️ Compare Products':'❤️ My Wishlist';
        document.getElementById('sgc-panel-title').textContent=title;
        document.getElementById('sgc-panel-body').innerHTML='<div class="sgc-panel-empty">Loading...</div>';

        var action=type==='compare'?'sgclabs_compare':'sgclabs_wishlist';
        var fd=new FormData();
        fd.append('action',action);
        fd.append('cmd','get');
        fd.append('_wpnonce',nonce);
        fetch(ajaxUrl,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(r){
            if(!r.success){document.getElementById('sgc-panel-body').innerHTML='<div class="sgc-panel-empty">Could not load items.</div>';return;}
            var list=r.data.list||r.data.ids||[];
            if(!list.length){
                document.getElementById('sgc-panel-body').innerHTML='<div class="sgc-panel-empty">'+(type==='compare'?'⚖️ No products added to compare yet.':'❤️ Your wishlist is empty.')+'<br><br><a href="<?php echo esc_url( get_permalink( wc_get_page_id('shop') ) ); ?>" style="color:#0ea5e9;">Browse products →</a></div>';
                document.getElementById('sgc-panel-actions').innerHTML='';
                return;
            }
            // Fetch product details via separate AJAX
            var fd2=new FormData();
            fd2.append('action','sgclabs_wishlist_panel_data');
            fd2.append('_wpnonce',nonce);
            fd2.append('ids',JSON.stringify(list));
            fd2.append('type',type);
            fetch(ajaxUrl,{method:'POST',body:fd2}).then(function(r){return r.json();}).then(function(r){
                if(!r.success)return;
                var items=r.data.items||[];
                var html='';
                items.forEach(function(item){
                    html+='<div class="sgc-panel-item">';
                    if(item.img) html+='<img src="'+item.img+'" alt="">';
                    html+='<div class="sgc-panel-item-info">';
                    html+='<a href="'+item.url+'" class="sgc-panel-item-name">'+item.name+'</a>';
                    html+='<div class="sgc-panel-item-sku">SKU: '+item.sku+(item.price?' · '+item.price:'')+'</div>';
                    html+='</div>';
                    html+='<button class="sgc-panel-item-remove" onclick="sgcPanelRemove('+item.id+',\''+type+'\')" title="Remove">✕</button>';
                    html+='</div>';
                });
                document.getElementById('sgc-panel-body').innerHTML=html;
                // Actions
                var actHtml='<div style="display:flex;gap:8px;">';
                if(type==='wishlist'){
                    actHtml+='<a href="#" onclick="sgcSendWishlistAsQuote();return false;" style="flex:1;display:block;text-align:center;background:#16a34a;color:#fff;padding:10px;border-radius:8px;font-weight:600;text-decoration:none;">📩 Send as Quote Request</a>';
                } else {
                    actHtml+='<a href="<?php echo esc_url( $cmp_page ); ?>" style="flex:1;display:block;text-align:center;background:#0ea5e9;color:#fff;padding:10px;border-radius:8px;font-weight:600;text-decoration:none;">⚖️ Compare Side-by-Side</a>';
                }
                actHtml+='<button onclick="sgcPanelClearAll(\''+type+'\')" style="background:#f3f4f6;border:1px solid #ddd;padding:10px 16px;border-radius:8px;cursor:pointer;font-size:12px;">🗑 Clear All</button>';
                actHtml+='</div>';
                document.getElementById('sgc-panel-actions').innerHTML=actHtml;
            });
        });
    };

    window.sgcPanelClose=function(){
        document.getElementById('sgc-wc-panel').classList.remove('open');
        document.getElementById('sgc-wc-panel-overlay').classList.remove('open');
        document.body.style.overflow='';
    };

    window.sgcPanelRemove=function(id,type){
        var fd=new FormData();
        fd.append('action',type==='compare'?'sgclabs_compare':'sgclabs_wishlist');
        fd.append('cmd','remove');
        fd.append('product_id',id);
        fd.append('_wpnonce',nonce);
        fetch(ajaxUrl,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(){
            sgcPanelOpen(type);
            sgcUpdateBarCounts();
        });
    };

    window.sgcPanelClearAll=function(type){
        if(!confirm('Clear all '+(type==='compare'?'compare':'wishlist')+' items?'))return;
        var fd=new FormData();
        fd.append('action',type==='compare'?'sgclabs_compare':'sgclabs_wishlist');
        fd.append('cmd','clear');
        fd.append('_wpnonce',nonce);
        fetch(ajaxUrl,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(){
            sgcPanelOpen(type);
            sgcUpdateBarCounts();
        });
    };

    window.sgcSendWishlistAsQuote=function(){
        // Open the quote modal with wishlist products pre-filled
        sgcPanelClose();
        if(typeof sgclabsOpenQuoteModal==='function'){
            sgclabsOpenQuoteModal(document.querySelector('.sgclabs-quote-btn')||document.body);
            // Pre-fill product field with wishlist items
            setTimeout(function(){
                var items=document.querySelectorAll('.sgc-panel-item-name');
                var names=[];items.forEach(function(el){names.push(el.textContent);});
                var field=document.getElementById('sgcq_product');
                if(field&&names.length) field.value='Wishlist: '+names.join(', ');
            },500);
        } else {
            window.location='<?php echo esc_url( home_url( '/contact/' ) ); ?>';
        }
    };

    window.sgcUpdateBarCounts=function(){
        var fd=new FormData();
        fd.append('action','sgclabs_wishlist');
        fd.append('cmd','get');
        fd.append('_wpnonce',nonce);
        fetch(ajaxUrl,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(r){
            var wlc=r.data&&r.data.list?r.data.list.length:0;
            document.getElementById('sgc-wl-count').textContent=wlc;
            // Update compare count from cookie
            var cc=0;try{var c=JSON.parse(document.cookie.replace(/(?:(?:^|.*;\s*)sgc_compare\s*=\s*([^;]*).*$)|^.*$/,"$1")||'[]');cc=c.length;}catch(e){}
            document.getElementById('sgc-cmp-count').textContent=cc;
            // Show/hide bar
            document.getElementById('sgc-wc-bar').classList.toggle('visible',(wlc+cc)>0);
        });
    };

    // Show bar when items are added (listen for custom events)
    document.addEventListener('sgc_wishlist_updated',sgcUpdateBarCounts);
    document.addEventListener('sgc_compare_updated',sgcUpdateBarCounts);
    })();
    </script>
    <?php
}

// AJAX handler for panel data (product details for the slide-out)
add_action( 'wp_ajax_sgclabs_wishlist_panel_data',        'sgclabs_wishlist_panel_data' );
add_action( 'wp_ajax_nopriv_sgclabs_wishlist_panel_data', 'sgclabs_wishlist_panel_data' );
function sgclabs_wishlist_panel_data() {
    check_ajax_referer( 'sgclabs_wishlist', '_wpnonce' );
    $raw = wp_unslash( $_POST['ids'] ?? '[]' );
    $ids = is_array( $raw ) ? $raw : json_decode( (string) $raw, true ); // accepts ids[] or JSON
    if ( ! is_array( $ids ) ) $ids = [];
    $items = [];
    foreach ( $ids as $pid ) {
        $pid = absint( $pid );
        $product = wc_get_product( $pid );
        if ( ! $product ) continue;
        $img_id = $product->get_image_id();
        $items[] = [
            'id'    => $pid,
            'name'  => $product->get_name(),
            'sku'   => $product->get_sku(),
            'price' => wp_strip_all_tags( $product->get_price_html() ),
            'url'   => get_permalink( $pid ),
            'img'   => $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '',
        ];
    }
    wp_send_json_success( [ 'items' => $items ] );
}
