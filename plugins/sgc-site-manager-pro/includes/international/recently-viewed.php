<?php
/**
 * SGC LABS® Recently Viewed Products
 *
 * Tracks last N products visited (cookie-based, no DB writes per page).
 * Renders a horizontal carousel via [sgc_recently_viewed] shortcode
 * and auto-injects under WooCommerce single product pages.
 *
 * @package   SGCLabs\International
 * @since     6.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'SGCLabs_Recently_Viewed' ) ) :

final class SGCLabs_Recently_Viewed {

    const COOKIE = 'sgc_rv';
    const MAX    = 12;

    public static function get(): array {
        if ( empty( $_COOKIE[ self::COOKIE ] ) ) return [];
        $list = json_decode( wp_unslash( $_COOKIE[ self::COOKIE ] ), true );
        return is_array( $list ) ? array_map( 'absint', $list ) : [];
    }

    public static function track( int $id ): void {
        if ( $id <= 0 ) return;
        $list = self::get();
        $list = array_values( array_diff( $list, [ $id ] ) );
        array_unshift( $list, $id );
        $list = array_slice( $list, 0, self::MAX );
        $secure = is_ssl();
        @setcookie( self::COOKIE, wp_json_encode( $list ), time() + 14 * DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, $secure, false );
        $_COOKIE[ self::COOKIE ] = wp_json_encode( $list );
    }
}

endif;

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'SGCLabs_Registry' ) ) return;
    if ( ! SGCLabs_Registry::is_active( 'recently_viewed' ) ) return;

    add_action( 'template_redirect', 'sgclabs_rv_track' );
    add_action( 'woocommerce_after_single_product', 'sgclabs_rv_render', 30 );
    add_shortcode( 'sgc_recently_viewed', 'sgclabs_rv_shortcode' );
}, 30 );

function sgclabs_rv_track(): void {
    if ( function_exists( 'is_product' ) && is_product() ) {
        SGCLabs_Recently_Viewed::track( (int) get_the_ID() );
    }
}

function sgclabs_rv_render(): void {
    echo sgclabs_rv_shortcode();
}

function sgclabs_rv_shortcode(): string {
    $current = function_exists( 'is_product' ) && is_product() ? (int) get_the_ID() : 0;
    $ids     = array_diff( SGCLabs_Recently_Viewed::get(), [ $current ] );
    $ids     = array_slice( $ids, 0, 8 );
    if ( empty( $ids ) ) return '';
    if ( ! function_exists( 'wc_get_product' ) ) return '';

    ob_start();
    ?>
    <section class="sgc-rv" style="margin:36px 0;padding:24px 0;border-top:1px solid #e5e7eb;">
        <h3 style="font-size:18px;margin:0 0 16px;color:#0f172a;">Recently Viewed</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;">
            <?php foreach ( $ids as $pid ):
                $p = wc_get_product( $pid );
                if ( ! $p || ! $p->is_visible() ) continue; ?>
                <a href="<?php echo esc_url( get_permalink( $pid ) ); ?>"
                   style="text-decoration:none;color:inherit;background:#fff;border:1px solid #e5e7eb;border-radius:10px;
                          overflow:hidden;display:block;transition:transform .15s;"
                   onmouseover="this.style.transform='translateY(-2px)'"
                   onmouseout="this.style.transform='translateY(0)'">
                    <div style="aspect-ratio:1/1;background:#f8fafc;display:flex;align-items:center;justify-content:center;">
                        <?php echo $p->get_image( 'woocommerce_thumbnail' ); ?>
                    </div>
                    <div style="padding:10px;">
                        <div style="font-size:13px;color:#0f172a;font-weight:600;line-height:1.3;
                                    overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">
                            <?php echo esc_html( $p->get_name() ); ?>
                        </div>
                        <div style="margin-top:6px;font-size:13px;color:#1e40af;font-weight:700;">
                            <?php echo $p->get_price_html(); ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
    return ob_get_clean();
}
