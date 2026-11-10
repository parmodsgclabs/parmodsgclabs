<?php
/**
 * SGC LABS® AJAX Quick View
 *
 * Modal preview on listing pages — click the eye icon, see name, gallery,
 * price, short description, and Save-for-Quote button without page reload.
 *
 * @package   SGCLabs\International
 * @since     6.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'SGCLabs_Registry' ) ) return;
    if ( ! SGCLabs_Registry::is_active( 'quick_view' ) ) return;

    add_action( 'wp_ajax_sgclabs_quick_view',        'sgclabs_quick_view_ajax' );
    add_action( 'wp_ajax_nopriv_sgclabs_quick_view', 'sgclabs_quick_view_ajax' );
    add_action( 'woocommerce_after_shop_loop_item', 'sgclabs_quick_view_button', 12 );
    add_action( 'wp_footer', 'sgclabs_quick_view_modal' );
}, 30 );

function sgclabs_quick_view_button(): void {
    global $product;
    if ( ! $product ) return;
    $nonce = wp_create_nonce( 'sgclabs_qv' );
    ?>
    <button type="button"
            onclick="sgcQuickView(<?php echo (int) $product->get_id(); ?>)"
            style="margin-top:6px;background:#fff;color:#0f172a;border:1px solid #cbd5e1;
                   padding:6px 12px;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;">
        👁️ Quick View
    </button>
    <script>
    window.SGC_QV_NONCE = window.SGC_QV_NONCE || <?php echo wp_json_encode( $nonce ); ?>;
    window.SGC_QV_AJAX  = window.SGC_QV_AJAX  || <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
    </script>
    <?php
}

function sgclabs_quick_view_ajax(): void {
    check_ajax_referer( 'sgclabs_qv', '_wpnonce' );
    $id = absint( $_POST['id'] ?? 0 );
    if ( ! function_exists( 'wc_get_product' ) ) wp_send_json_error( 'no-woo' );
    $p = wc_get_product( $id );
    if ( ! $p ) wp_send_json_error( 'not-found' );

    ob_start();
    ?>
    <div class="sgc-qv-content">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
            <div><?php echo $p->get_image( 'large', [ 'style' => 'width:100%;border-radius:10px;' ] ); ?></div>
            <div>
                <h2 style="margin:0 0 12px;font-size:22px;"><?php echo esc_html( $p->get_name() ); ?></h2>
                <p style="font-size:13px;color:#64748b;">SKU: <code><?php echo esc_html( $p->get_sku() ); ?></code></p>
                <div style="font-size:22px;color:#1e40af;font-weight:700;margin:14px 0;">
                    <?php echo $p->get_price_html(); ?>
                </div>
                <div style="font-size:13px;color:#475569;line-height:1.6;margin-bottom:18px;">
                    <?php echo wp_kses_post( $p->get_short_description() ); ?>
                </div>
                <a href="<?php echo esc_url( get_permalink( $id ) ); ?>"
                   style="display:inline-block;background:#1e40af;color:#fff;padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:600;">
                    View full product →
                </a>
            </div>
        </div>
    </div>
    <?php
    wp_send_json_success( [ 'html' => ob_get_clean() ] );
}

function sgclabs_quick_view_modal(): void {
    if ( is_admin() ) return;
    ?>
    <div id="sgc-qv-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.6);z-index:99998;align-items:center;justify-content:center;padding:20px;">
        <div id="sgc-qv-box" style="background:#fff;max-width:880px;width:100%;max-height:88vh;overflow:auto;border-radius:14px;padding:28px;position:relative;">
            <button onclick="document.getElementById('sgc-qv-modal').style.display='none'"
                    style="position:absolute;top:14px;right:14px;background:#f1f5f9;border:none;border-radius:50%;width:32px;height:32px;cursor:pointer;font-size:16px;">✕</button>
            <div id="sgc-qv-body" style="text-align:center;padding:40px 20px;color:#64748b;">Loading…</div>
        </div>
    </div>
    <script>
    window.sgcQuickView = window.sgcQuickView || function(id){
        var modal = document.getElementById('sgc-qv-modal');
        var body  = document.getElementById('sgc-qv-body');
        body.innerHTML = '<p>Loading…</p>';
        modal.style.display = 'flex';
        var fd = new FormData();
        fd.append('action','sgclabs_quick_view');
        fd.append('_wpnonce', window.SGC_QV_NONCE);
        fd.append('id', id);
        fetch(window.SGC_QV_AJAX, {method:'POST',body:fd,credentials:'same-origin'})
            .then(r=>r.json()).then(d=>{
                body.innerHTML = (d.success && d.data && d.data.html) ? d.data.html : '<p>Error loading product.</p>';
            });
    };
    document.getElementById('sgc-qv-modal').addEventListener('click', function(e){
        if (e.target.id === 'sgc-qv-modal') this.style.display='none';
    });
    </script>
    <?php
}
