<?php
if (!defined('ABSPATH')) exit;

add_action('admin_post_sgclabs_save_seo', 'sgclabs_save_seo');
function sgclabs_save_seo() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'sgclabs_seo_nonce')) wp_die('Unauthorized');
    update_option('sgclabs_seo_title', sanitize_text_field($_POST['seo_title']));
    update_option('sgclabs_seo_description', sanitize_textarea_field($_POST['seo_description']));
    update_option('sgclabs_seo_keywords', sanitize_textarea_field($_POST['seo_keywords']));
    update_option('sgclabs_seo_og_image', esc_url_raw($_POST['seo_og_image']));
    update_option('sgclabs_seo_schema_active', isset($_POST['seo_schema_active']) ? '1' : '0');
    update_option('sgclabs_seo_business_type', sanitize_text_field($_POST['seo_business_type']));
    wp_redirect(admin_url('admin.php?page=sgclabs-seo-suite&saved=1'));
    exit;
}

// Inject meta tags
add_action('wp_head', 'sgclabs_inject_meta', 5);
function sgclabs_inject_meta() {
    // FIX: Skip if SEO plugin handles OG/meta — prevent duplicate tags
    if (defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION') || defined('AIOSEO_VERSION') || defined('SEOPRESS_VERSION')) return;
    if (!is_front_page() && !is_home()) return;
    $title = get_option('sgclabs_seo_title', 'Laboratory Glassware & Scientific Equipment Manufacturer | SGC LABS® Ambala India');
    $desc = get_option('sgclabs_seo_description', 'SGC LABS® is a leading manufacturer of laboratory glassware, borosilicate glass & scientific glass blowing components in Ambala, India since 2003. 5000+ products. Export worldwide.');
    $keywords = get_option('sgclabs_seo_keywords', 'laboratory glassware, borosilicate glass, scientific equipment, glass blowing components, Ambala manufacturer');
    $og_image = get_option('sgclabs_seo_og_image', '');
    echo "<meta name='description' content='" . esc_attr($desc) . "'>\n";
    echo "<meta name='keywords' content='" . esc_attr($keywords) . "'>\n";
    echo "<meta property='og:title' content='" . esc_attr($title) . "'>\n";
    echo "<meta property='og:description' content='" . esc_attr($desc) . "'>\n";
    echo "<meta property='og:url' content='" . esc_url(home_url()) . "'>\n";
    echo "<meta property='og:type' content='website'>\n";
    if ($og_image) echo "<meta property='og:image' content='" . esc_url($og_image) . "'>\n";
    echo "<meta name='twitter:card' content='summary_large_image'>\n";
    echo "<meta name='twitter:title' content='" . esc_attr($title) . "'>\n";
    echo "<meta name='twitter:description' content='" . esc_attr($desc) . "'>\n";

    // Schema markup
    if (get_option('sgclabs_seo_schema_active', '1') === '1') {
        $schema = [
            "@context" => "https://schema.org",
            "@type" => "Organization",
            "name" => "SGC LABS®",
            "url" => "https://labsglass.com",
            "logo" => "https://labsglass.com/wp-content/uploads/2019/12/SGC-LABS-Logo.png",
            "telephone" => "+91 999 641 6935",
            "email" => "sales@labsglass.com",
            "address" => [
                "@type" => "PostalAddress",
                "streetAddress" => "4/12/2 Gandhi Nagar",
                "addressLocality" => "Ambala Cantt",
                "addressRegion" => "Haryana",
                "postalCode" => "133001",
                "addressCountry" => "IN"
            ],
            "sameAs" => ["https://labsglass.com"],
            "description" => "Leading manufacturer of laboratory glassware and scientific glass blowing components since 2003."
        ];
        echo "<script type='application/ld+json'>" . wp_json_encode($schema) . "</script>\n";
    }
}

function sgclabs_seo_page() {
    $saved = isset($_GET['saved']);
    $title = get_option('sgclabs_seo_title', 'Laboratory Glassware & Scientific Equipment Manufacturer | SGC LABS® Ambala India');
    $desc = get_option('sgclabs_seo_description', 'SGC LABS® is a leading manufacturer of laboratory glassware, borosilicate glass & scientific glass blowing components in Ambala, India since 2003. 5000+ products. Export worldwide.');
    $keywords = get_option('sgclabs_seo_keywords', 'laboratory glassware, borosilicate glass, scientific equipment');
    $og_image = get_option('sgclabs_seo_og_image', '');
    $schema_active = get_option('sgclabs_seo_schema_active', '1');
    $business_type = get_option('sgclabs_seo_business_type', 'Manufacturer');
    $title_len = strlen($title);
    $desc_len = strlen($desc);
    ?>
    <div class="wrap sgclabs-wrap">
        <h1>🔍 SEO Manager</h1>
        <?php if ($saved): ?><div class="notice notice-success"><p>✅ SEO settings saved!</p></div><?php endif; ?>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('sgclabs_seo_nonce'); ?>
            <input type="hidden" name="action" value="sgclabs_save_seo">
            <div class="sgclabs-section">
                <h2>🏠 Homepage SEO</h2>
                <table class="form-table">
                    <tr>
                        <th>Meta Title</th>
                        <td>
                            <input type="text" name="seo_title" value="<?php echo esc_attr($title); ?>" class="large-text">
                            <p class="description <?php echo intval($title_len) > 60 ? 'sgclabs-warn' : 'sgclabs-ok'; ?>">
                                Characters: <strong><?php echo intval($title_len); ?>/60</strong>
                                <?php echo intval($title_len) > 60 ? '⚠️ Too long!' : '✅ Good'; ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th>Meta Description</th>
                        <td>
                            <textarea name="seo_description" class="large-text" rows="3"><?php echo esc_textarea($desc); ?></textarea>
                            <p class="description <?php echo intval($desc_len) > 160 ? 'sgclabs-warn' : 'sgclabs-ok'; ?>">
                                Characters: <strong><?php echo intval($desc_len); ?>/160</strong>
                                <?php echo intval($desc_len) > 160 ? '⚠️ Too long!' : '✅ Good'; ?>
                            </p>
                        </td>
                    </tr>
                    <tr><th>Focus Keywords</th><td><textarea name="seo_keywords" class="large-text" rows="3"><?php echo esc_textarea($keywords); ?></textarea><p class="description">Comma separated keywords</p></td></tr>
                    <tr><th>OG Image URL</th><td><input type="url" name="seo_og_image" value="<?php echo esc_attr($og_image); ?>" class="large-text" placeholder="https://labsglass.com/wp-content/..."></td></tr>
                    <tr><th>Business Type</th><td><input type="text" name="seo_business_type" value="<?php echo esc_attr($business_type); ?>" class="regular-text"></td></tr>
                    <tr><th>Schema Markup</th><td><label><input type="checkbox" name="seo_schema_active" value="1" <?php checked($schema_active, '1'); ?>> Enable Organization Schema (Recommended ✅)</label></td></tr>
                </table>
            </div>
            <div class="sgclabs-section">
                <h3>👁️ Google Search Preview</h3>
                <div class="sgclabs-google-preview">
                    <div class="gp-url">labsglass.com</div>
                    <div class="gp-title"><?php echo esc_html(substr($title, 0, 60)); ?></div>
                    <div class="gp-desc"><?php echo esc_html(substr($desc, 0, 160)); ?></div>
                </div>
            </div>
            <?php submit_button('💾 Save SEO Settings'); ?>
        </form>
    </div>
    <?php
}
