<?php
/**
 * Template: Announcement Bar Admin Page
 *
 * Variables available (extracted from $args):
 * @var string $text
 * @var string $active
 * @var string $bg
 * @var string $color
 * @var string $link
 * @var string $dismissible
 * @var bool   $saved
 * @var string $nonce
 *
 * @package SGCLabs_Manager
 * @since   6.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap sgclabs-wrap">
    <h1>📢 Announcement Bar</h1>

    <?php if ( $saved ) : ?>
        <div class="notice notice-success is-dismissible"><p>✅ Announcement saved!</p></div>
    <?php endif; ?>

    <!-- LIVE PREVIEW -->
    <div class="sgclabs-preview-box" style="margin-bottom:20px;">
        <h3>👁️ Preview</h3>
        <div id="ann-preview"
             style="background:<?php echo esc_attr( $bg ); ?>;color:<?php echo esc_attr( $color ); ?>;padding:10px 40px;text-align:center;font-weight:bold;border-radius:4px;">
            <?php echo esc_html( $text ); ?>
            <span style="float:right;cursor:pointer;">✕</span>
        </div>
    </div>

    <!-- SETTINGS FORM -->
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php echo $nonce; ?>
        <input type="hidden" name="action" value="sgclabs_save_announcement">

        <div class="sgclabs-section">
            <table class="form-table">
                <tr>
                    <th>Active</th>
                    <td>
                        <label>
                            <input type="checkbox" name="ann_active" value="1" <?php checked( $active, '1' ); ?>>
                            Show on website
                        </label>
                    </td>
                </tr>
                <tr>
                    <th>Announcement Text</th>
                    <td>
                        <input type="text" name="ann_text"
                               value="<?php echo esc_attr( $text ); ?>"
                               class="large-text"
                               placeholder="FREE SHIPPING on orders over ₹5000!">
                    </td>
                </tr>
                <tr>
                    <th>Link (Optional)</th>
                    <td>
                        <input type="url" name="ann_link"
                               value="<?php echo esc_attr( $link ); ?>"
                               class="large-text"
                               placeholder="https://labsglass.com/shop">
                    </td>
                </tr>
                <tr>
                    <th>Background Color</th>
                    <td><input type="color" name="ann_bg" value="<?php echo esc_attr( $bg ); ?>"></td>
                </tr>
                <tr>
                    <th>Text Color</th>
                    <td><input type="color" name="ann_color" value="<?php echo esc_attr( $color ); ?>"></td>
                </tr>
                <tr>
                    <th>Dismissible</th>
                    <td>
                        <label>
                            <input type="checkbox" name="ann_dismissible" value="1" <?php checked( $dismissible, '1' ); ?>>
                            Show close (✕) button
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button( '💾 Save Announcement' ); ?>
    </form>
</div>
