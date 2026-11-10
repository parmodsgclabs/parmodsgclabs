<?php
/**
 * Template: SEO Manager Admin Page
 *
 * @var string $title
 * @var string $desc
 * @var string $keywords
 * @var string $og_image
 * @var string $schema_active
 * @var string $business_type
 * @var bool   $saved
 * @var string $nonce
 * @var int    $title_len
 * @var int    $desc_len
 *
 * @package SGCLabs_Manager
 * @since   6.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap sgclabs-wrap">
    <h1>🔍 SEO Manager</h1>

    <?php if ( $saved ) : ?>
        <div class="notice notice-success is-dismissible"><p>✅ SEO settings saved!</p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php echo $nonce; ?>
        <input type="hidden" name="action" value="sgclabs_save_seo">

        <div class="sgclabs-section">
            <h2>🏠 Homepage SEO</h2>
            <table class="form-table">
                <tr>
                    <th>Meta Title</th>
                    <td>
                        <input type="text" name="seo_title" value="<?php echo esc_attr( $title ); ?>" class="large-text">
                        <p class="description <?php echo $title_len > 60 ? 'sgclabs-warn' : 'sgclabs-ok'; ?>">
                            Characters: <strong><?php echo intval( $title_len ); ?>/60</strong>
                            <?php echo $title_len > 60 ? '⚠️ Too long!' : '✅ Good'; ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th>Meta Description</th>
                    <td>
                        <textarea name="seo_description" class="large-text" rows="3"><?php echo esc_textarea( $desc ); ?></textarea>
                        <p class="description <?php echo $desc_len > 160 ? 'sgclabs-warn' : 'sgclabs-ok'; ?>">
                            Characters: <strong><?php echo intval( $desc_len ); ?>/160</strong>
                            <?php echo $desc_len > 160 ? '⚠️ Too long!' : '✅ Good'; ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th>Focus Keywords</th>
                    <td>
                        <textarea name="seo_keywords" class="large-text" rows="3"><?php echo esc_textarea( $keywords ); ?></textarea>
                        <p class="description">Comma separated keywords</p>
                    </td>
                </tr>
                <tr>
                    <th>OG Image URL</th>
                    <td>
                        <input type="url" name="seo_og_image"
                               value="<?php echo esc_attr( $og_image ); ?>"
                               class="large-text"
                               placeholder="https://labsglass.com/wp-content/...">
                    </td>
                </tr>
                <tr>
                    <th>Business Type</th>
                    <td><input type="text" name="seo_business_type" value="<?php echo esc_attr( $business_type ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th>Schema Markup</th>
                    <td>
                        <label>
                            <input type="checkbox" name="seo_schema_active" value="1" <?php checked( $schema_active, '1' ); ?>>
                            Enable Organization Schema (Recommended ✅)
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Google Search Preview -->
        <div class="sgclabs-section">
            <h3>👁️ Google Search Preview</h3>
            <div class="sgclabs-google-preview">
                <div class="gp-url">labsglass.com</div>
                <div class="gp-title"><?php echo esc_html( substr( $title, 0, 60 ) ); ?></div>
                <div class="gp-desc"><?php echo esc_html( substr( $desc, 0, 160 ) ); ?></div>
            </div>
        </div>

        <?php submit_button( '💾 Save SEO Settings' ); ?>
    </form>
</div>
