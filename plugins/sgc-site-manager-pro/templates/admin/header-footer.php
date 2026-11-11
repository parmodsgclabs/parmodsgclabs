<?php
/**
 * Template: Header & Footer Admin Page
 *
 * @var string $phone
 * @var string $email
 * @var string $whatsapp
 * @var string $bg
 * @var string $color
 * @var string $active
 * @var string $remove_demo
 * @var string $tagline
 * @var string $nav_fix_active
 * @var string $nav_font_size
 * @var string $nav_drop_bg
 * @var string $nav_drop_color
 * @var bool   $saved
 * @var string $nonce
 *
 * @package SGCLabs_Manager
 * @since   6.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap sgclabs-wrap">
    <h1>📞 Header & Footer Manager</h1>

    <?php if ( $saved ) : ?>
        <div class="notice notice-success is-dismissible"><p>✅ Settings saved successfully!</p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php echo $nonce; ?>
        <input type="hidden" name="action" value="sgclabs_save_header">

        <!-- Header Top Bar -->
        <div class="sgclabs-section">
            <h2>🔝 Header Top Bar</h2>
            <table class="form-table">
                <tr>
                    <th>Top Bar Active</th>
                    <td><label><input type="checkbox" name="header_active" value="1" <?php checked( $active, '1' ); ?>> Enable top bar on website</label></td>
                </tr>
                <tr>
                    <th>Phone Number</th>
                    <td><input type="text" name="header_phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text" placeholder="+91 999 641 6935"></td>
                </tr>
                <tr>
                    <th>Email Address</th>
                    <td><input type="email" name="header_email" value="<?php echo esc_attr( $email ); ?>" class="regular-text" placeholder="sales@labsglass.com"></td>
                </tr>
                <tr>
                    <th>WhatsApp Number</th>
                    <td><input type="text" name="header_whatsapp" value="<?php echo esc_attr( $whatsapp ); ?>" class="regular-text" placeholder="919996416935 (country code included)"></td>
                </tr>
                <tr>
                    <th>Background Color</th>
                    <td><input type="color" name="header_bg" value="<?php echo esc_attr( $bg ); ?>"></td>
                </tr>
                <tr>
                    <th>Text Color</th>
                    <td><input type="color" name="header_text_color" value="<?php echo esc_attr( $color ); ?>"></td>
                </tr>
            </table>
        </div>

        <!-- Footer Cleanup -->
        <div class="sgclabs-section">
            <h2>🦶 Footer Cleanup</h2>
            <table class="form-table">
                <tr>
                    <th>Remove Demo Images</th>
                    <td><label><input type="checkbox" name="footer_remove_demo" value="1" <?php checked( $remove_demo, '1' ); ?>> Auto-hide broken demo images (labtechco, pbminfotech)</label></td>
                </tr>
                <tr>
                    <th>Footer Tagline Text</th>
                    <td><input type="text" name="footer_tagline" value="<?php echo esc_attr( $tagline ); ?>" class="large-text"></td>
                </tr>
            </table>
        </div>

        <!-- Navigation Menu Fix -->
        <div class="sgclabs-section" style="border-left:4px solid #e67e22;">
            <h2>🍔 Navigation Menu Fix</h2>
            <div style="background:#fff3e0;border-radius:6px;padding:12px;margin-bottom:15px;font-size:13px;">
                ⚠️ <strong>Hover dropdown kaam nahi kar raha?</strong> Yeh option enable karo — theme ke upar CSS override inject karega.
            </div>
            <table class="form-table">
                <tr>
                    <th>Enable Nav Fix</th>
                    <td>
                        <label>
                            <input type="checkbox" name="nav_fix_active" value="1" <?php checked( $nav_fix_active, '1' ); ?>>
                            <strong>Fix hover dropdowns + font size</strong>
                        </label>
                        <p class="description">Ye enable karne se hover menu aur font size dono theek ho jayenge</p>
                    </td>
                </tr>
                <tr>
                    <th>Menu Font Size</th>
                    <td>
                        <input type="number" name="nav_font_size" value="<?php echo intval( $nav_font_size ); ?>" min="10" max="22" style="width:70px;"> px
                        <p class="description">Recommended: 13-15px</p>
                    </td>
                </tr>
                <tr>
                    <th>Dropdown Background</th>
                    <td>
                        <input type="color" name="nav_drop_bg" value="<?php echo esc_attr( $nav_drop_bg ); ?>">
                        <span style="margin-left:10px;font-size:12px;color:#666;">Dropdown menu ka background color</span>
                    </td>
                </tr>
                <tr>
                    <th>Dropdown Text Color</th>
                    <td><input type="color" name="nav_drop_color" value="<?php echo esc_attr( $nav_drop_color ); ?>"></td>
                </tr>
            </table>

            <!-- Live Preview -->
            <div style="margin-top:15px;">
                <h4>👁️ Dropdown Preview</h4>
                <div style="display:inline-block;background:<?php echo esc_attr( $nav_drop_bg ); ?>;border-radius:6px;overflow:hidden;min-width:200px;">
                    <?php foreach ( [ 'Products', 'Beakers', 'Flasks', 'Laboratory Glass', 'Burettes' ] as $item ) : ?>
                        <div style="padding:10px 18px;color:<?php echo esc_attr( $nav_drop_color ); ?>;font-size:<?php echo intval( $nav_font_size ); ?>px;border-bottom:1px solid rgba(255,255,255,0.1);">
                            <?php echo esc_html( $item ); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php submit_button( '💾 Save All Settings' ); ?>
    </form>

    <!-- Header Bar Preview -->
    <div class="sgclabs-preview-box">
        <h3>👁️ Live Preview (Header Bar)</h3>
        <div style="background:<?php echo esc_attr( $bg ); ?>;color:<?php echo esc_attr( $color ); ?>;padding:10px 20px;border-radius:5px;font-size:13px;">
            📞 <?php echo esc_html( $phone ); ?> &nbsp;|&nbsp; ✉️ <?php echo esc_html( $email ); ?> &nbsp;|&nbsp; 💬 WhatsApp
        </div>
    </div>
</div>
