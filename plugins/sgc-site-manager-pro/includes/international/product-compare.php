<?php
/**
 * SGC LABS® Product Compare
 *
 * Up to 4 products side-by-side — name, image, price, SKU,
 * and any custom lab-tech-spec attributes from the existing module.
 *
 * Cookie-based so guests can compare without an account.
 *
 * @package   SGCLabs\International
 * @since     6.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'SGCLabs_Compare' ) ) :

final class SGCLabs_Compare {

    const COOKIE = 'sgc_compare';
    const MAX    = 4;

    public static function get(): array {
        if ( empty( $_COOKIE[ self::COOKIE ] ) ) return [];
        $list = json_decode( wp_unslash( $_COOKIE[ self::COOKIE ] ), true );
        return is_array( $list ) ? array_map( 'absint', $list ) : [];
    }

    public static function set( array $ids ): void {
        $ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
        $ids = array_slice( $ids, 0, self::MAX );
        $secure = is_ssl();
        @setcookie( self::COOKIE, wp_json_encode( $ids ), time() + 7 * DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, $secure, false );
        $_COOKIE[ self::COOKIE ] = wp_json_encode( $ids );
    }

    public static function add( int $id ): void {
        $list = self::get();
        if ( ! in_array( $id, $list, true ) && count( $list ) < self::MAX ) $list[] = $id;
        self::set( $list );
    }

    public static function remove( int $id ): void {
        self::set( array_diff( self::get(), [ $id ] ) );
    }
}

endif;

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'SGCLabs_Registry' ) ) return;
    if ( ! SGCLabs_Registry::is_active( 'product_compare' ) ) return;

    add_action( 'wp_ajax_sgclabs_compare',        'sgclabs_compare_ajax' );
    add_action( 'wp_ajax_nopriv_sgclabs_compare', 'sgclabs_compare_ajax' );
    add_action( 'woocommerce_after_add_to_cart_button', 'sgclabs_compare_button' );
    add_shortcode( 'sgc_compare', 'sgclabs_compare_shortcode' );
}, 30 );

function sgclabs_compare_ajax(): void {
    check_ajax_referer( 'sgclabs_compare', '_wpnonce' );
    $op = sanitize_key( wp_unslash( $_POST['op'] ?? '' ) );
    $id = absint( $_POST['id'] ?? 0 );
    if ( $op === 'add' )    SGCLabs_Compare::add( $id );
    if ( $op === 'remove' ) SGCLabs_Compare::remove( $id );
    wp_send_json_success( [ 'count' => count( SGCLabs_Compare::get() ) ] );
}

function sgclabs_compare_button(): void {
    global $product;
    if ( ! $product ) return;
    $id    = $product->get_id();
    $list  = SGCLabs_Compare::get();
    $on    = in_array( $id, $list, true );
    $nonce = wp_create_nonce( 'sgclabs_compare' );
    ?>
    <button type="button"
            class="sgc-compare-btn"
            data-id="<?php echo (int) $id; ?>"
            data-on="<?php echo $on ? 1 : 0; ?>"
            onclick="sgcCompareToggle(this)"
            style="margin:8px 0 0 0;background:transparent;color:#1e40af;border:1.5px solid #1e40af;
                   padding:8px 14px;border-radius:8px;cursor:pointer;font-weight:600;font-size:13px;">
        ⚖️ <span class="sgc-cmp-txt"><?php echo $on ? 'Added to compare' : 'Compare'; ?></span>
    </button>
    <script>
    window.SGC_CMP_NONCE = window.SGC_CMP_NONCE || <?php echo wp_json_encode( $nonce ); ?>;
    window.sgcCompareToggle = window.sgcCompareToggle || function(btn){
        var on = btn.dataset.on === '1';
        var fd = new FormData();
        fd.append('action','sgclabs_compare');
        fd.append('_wpnonce', window.SGC_CMP_NONCE);
        fd.append('op', on ? 'remove' : 'add');
        fd.append('id', btn.dataset.id);
        fetch(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, {method:'POST',body:fd,credentials:'same-origin'})
            .then(r=>r.json()).then(d=>{
                btn.dataset.on = on ? '0' : '1';
                btn.querySelector('.sgc-cmp-txt').textContent = on ? 'Compare' : 'Added to compare';
            });
    };
    </script>
    <?php
}

function sgclabs_compare_shortcode(): string {
    $ids = SGCLabs_Compare::get();
    if ( empty( $ids ) ) {
        return '<p style="padding:20px;background:#f8fafc;border-radius:8px;color:#64748b;">No products selected for comparison yet. Browse products and click ⚖️ Compare.</p>';
    }
    if ( ! function_exists( 'wc_get_product' ) ) return '';

    ob_start();
    ?>
    <div class="sgc-compare-table" style="overflow-x:auto;">
        <table style="width:100%;min-width:640px;border-collapse:collapse;background:#fff;">
            <thead>
                <tr>
                    <th style="background:#f8fafc;padding:12px;border:1px solid #e5e7eb;text-align:left;width:160px;">Attribute</th>
                    <?php foreach ( $ids as $pid ):
                        $p = wc_get_product( $pid ); if ( ! $p ) continue; ?>
                        <th style="padding:12px;border:1px solid #e5e7eb;text-align:center;">
                            <a href="<?php echo esc_url( get_permalink( $pid ) ); ?>" style="text-decoration:none;color:inherit;">
                                <?php echo $p->get_image( 'thumbnail' ); ?><br>
                                <strong style="font-size:13px;"><?php echo esc_html( $p->get_name() ); ?></strong>
                            </a>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $rows = [
                    'SKU'   => function( $p ) { return esc_html( $p->get_sku() ); },
                    'Price' => function( $p ) { return wp_kses_post( $p->get_price_html() ); },
                    'Stock' => function( $p ) { return esc_html( $p->get_stock_status() ); },
                    'Categories' => function( $p ) {
                        $terms = wp_get_post_terms( $p->get_id(), 'product_cat', [ 'fields' => 'names' ] );
                        return esc_html( implode( ', ', $terms ?: [] ) );
                    },
                    'Short Description' => function( $p ) { return wp_kses_post( $p->get_short_description() ); },
                ];
                foreach ( $rows as $label => $cb ):
                    ?><tr>
                        <td style="padding:12px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:600;"><?php echo esc_html( $label ); ?></td>
                        <?php foreach ( $ids as $pid ): $p = wc_get_product( $pid ); if ( ! $p ) continue; ?>
                            <td style="padding:12px;border:1px solid #e5e7eb;text-align:center;font-size:13px;"><?php echo call_user_func( $cb, $p ); ?></td>
                        <?php endforeach; ?>
                    </tr><?php
                endforeach;
                ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}
