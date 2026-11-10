<?php
/**
 * Template: Sitemap Admin Section (inline widget)
 *
 * @var string $active
 * @var bool   $rm_on
 * @var bool   $yoast_on
 * @var string $index_url
 *
 * @package SGCLabs_Manager
 * @since   6.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;margin-bottom:20px;">
    <h3 style="margin:0 0 6px;font-size:16px;font-weight:700;">🗺️ SGC Sitemap Engine</h3>
    <p style="color:#6b7280;font-size:13px;margin:0 0 14px;">
        RankMath-compatible sitemap — same URLs (<code>/sitemap_index.xml</code>, <code>/product-sitemap.xml</code> etc.)<br>
        Products ke liye image sitemap bhi include hai. Automatically replaces RankMath sitemaps.
    </p>

    <?php if ( $rm_on ) : ?>
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:13px;">
            ⚠️ <strong>RankMath active hai</strong> — SGC Sitemap tab tak kaam nahi karega jab tak RankMath deactivate na karo.
        </div>
    <?php elseif ( $yoast_on ) : ?>
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:13px;">
            ⚠️ <strong>Yoast SEO active hai</strong> — pehle Yoast deactivate karo.
        </div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field( 'sgclabs_sitemap_nonce' ); ?>
        <label style="display:flex;align-items:center;gap:10px;font-size:14px;font-weight:600;cursor:pointer;">
            <input type="checkbox" name="sgclabs_sitemap_active" value="1"
                   <?php checked( $active, '1' ); ?>
                   <?php if ( $rm_on || $yoast_on ) echo 'disabled'; ?>>
            Enable SGC Sitemap Engine
        </label>
        <p style="font-size:12px;color:#6b7280;margin:6px 0 14px 26px;">
            Ye enable karne se <code>/sitemap_index.xml</code> aur sab sub-sitemaps handle hone lagenge.<br>
            RankMath / Yoast ke sitemaps automatically replace ho jayenge.
        </p>

        <?php if ( $active === '1' && ! $rm_on && ! $yoast_on ) : ?>
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px 16px;margin-bottom:14px;">
                <strong style="font-size:13px;color:#166534;">✅ Active Sitemaps:</strong>
                <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;">
                    <?php
                    $types = [ 'index', 'product', 'page', 'post', 'product_cat', 'product_tag' ];
                    foreach ( $types as $t ) :
                        $url = $t === 'index' ? home_url( '/sitemap_index.xml' ) : home_url( '/' . $t . '-sitemap.xml' );
                    ?>
                        <a href="<?php echo esc_url( $url ); ?>" target="_blank"
                           style="background:#fff;border:1px solid #d1fae5;color:#059669;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:600;text-decoration:none;">
                            <?php echo esc_html( $t === 'index' ? 'sitemap_index.xml' : $t . '-sitemap.xml' ); ?> ↗
                        </a>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:10px;font-size:12px;color:#374151;">
                    📋 <strong>GSC mein yeh URL submit karo:</strong>
                    <code style="background:#fff;padding:2px 8px;border-radius:4px;border:1px solid #d1fae5;">
                        <?php echo esc_html( $index_url ); ?>
                    </code>
                </div>
            </div>
        <?php endif; ?>

        <div id="sgc-pnav-sitemap" style="margin-bottom:12px;"></div>
    </form>
</div>
