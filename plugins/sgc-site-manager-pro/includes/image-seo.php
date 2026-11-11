<?php
if (!defined('ABSPATH')) exit;

// ============================================================
// IMAGE SEO ENGINE — Auto Alt Text, Title, Caption, Filename
// WooCommerce Product Image SEO + Media Library SEO
// SGC LABS® Manager Pro v6.6.3
// ============================================================

// ────────────────────────────────────────────────────────────
// SAVE HANDLER
// ────────────────────────────────────────────────────────────
add_action('admin_post_sgclabs_save_image_seo', 'sgclabs_save_image_seo_settings');
function sgclabs_save_image_seo_settings() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'sgclabs_image_seo_nonce')) wp_die('Unauthorized');

    update_option('sgclabs_img_auto_alt',        isset($_POST['img_auto_alt'])        ? '1' : '0');
    update_option('sgclabs_img_auto_title',       isset($_POST['img_auto_title'])       ? '1' : '0');
    update_option('sgclabs_img_alt_pattern',      sanitize_text_field($_POST['img_alt_pattern']      ?? '{product_name} | SGC LABS®'));
    update_option('sgclabs_img_title_pattern',    sanitize_text_field($_POST['img_title_pattern']    ?? '{product_name} Image'));
    update_option('sgclabs_img_og_active',        isset($_POST['img_og_active'])        ? '1' : '0');
    update_option('sgclabs_img_lazy_load',        isset($_POST['img_lazy_load'])        ? '1' : '0');
    update_option('sgclabs_img_webp_hint',        isset($_POST['img_webp_hint'])        ? '1' : '0');
    update_option('sgclabs_img_schema_active',    isset($_POST['img_schema_active'])    ? '1' : '0');
    update_option('sgclabs_img_filename_clean',   isset($_POST['img_filename_clean'])   ? '1' : '0');
    update_option('sgclabs_img_caption_auto',     isset($_POST['img_caption_auto'])     ? '1' : '0');
    update_option('sgclabs_img_caption_pattern',  sanitize_text_field($_POST['img_caption_pattern']  ?? '{product_name} — SGC LABS® India'));
    update_option('sgclabs_img_noindex_orphan',   isset($_POST['img_noindex_orphan'])   ? '1' : '0');
    update_option('sgclabs_img_sitemap_include',  isset($_POST['img_sitemap_include'])  ? '1' : '0');

    wp_redirect(admin_url('admin.php?page=sgclabs-seo-suite&tab=image-seo&saved=1'));
    exit;
}

// ────────────────────────────────────────────────────────────
// AJAX: Bulk Apply Alt Text to WooCommerce Products
// ────────────────────────────────────────────────────────────
add_action('wp_ajax_sgclabs_bulk_image_alt', 'sgclabs_bulk_image_alt_handler');
function sgclabs_bulk_image_alt_handler() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $offset  = intval($_POST['offset'] ?? 0);
    $batch   = 20;
    $pattern = get_option('sgclabs_img_alt_pattern', '{product_name} | SGC LABS®');

    $products = get_posts([
        'post_type'      => 'product',
        'posts_per_page' => $batch,
        'offset'         => $offset,
        'post_status'    => 'publish',
        'fields'         => 'ids',
    ]);

    if (empty($products)) {
        wp_send_json_success(['done' => true, 'processed' => 0]);
    }

    $processed = 0;
    foreach ($products as $pid) {
        $product   = wc_get_product($pid);
        if (!$product) continue;
        $name      = $product->get_name();
        $cats      = wp_get_post_terms($pid, 'product_cat', ['fields' => 'names']);
        $cat_str   = !empty($cats) ? $cats[0] : 'Lab Equipment';
        $sku       = $product->get_sku() ?: '';

        // Build alt text from pattern
        $alt = sgclabs_build_img_alt($pattern, $name, $cat_str, $sku);

        // Apply to featured image
        $thumb_id = get_post_thumbnail_id($pid);
        if ($thumb_id) {
            update_post_meta($thumb_id, '_wp_attachment_image_alt', $alt);
            // Also update title
            if (get_option('sgclabs_img_auto_title', '1') === '1') {
                $title_pattern = get_option('sgclabs_img_title_pattern', '{product_name} Image');
                wp_update_post([
                    'ID'         => $thumb_id,
                    'post_title' => sgclabs_build_img_alt($title_pattern, $name, $cat_str, $sku),
                ]);
            }
        }

        // Apply to gallery images
        $gallery_ids = $product->get_gallery_image_ids();
        foreach ($gallery_ids as $gid) {
            $gallery_alt = sgclabs_build_img_alt($pattern, $name . ' - Gallery', $cat_str, $sku);
            update_post_meta($gid, '_wp_attachment_image_alt', $gallery_alt);
        }

        $processed++;
    }

    wp_send_json_success([
        'done'      => count($products) < $batch,
        'processed' => $processed,
        'next'      => $offset + $batch,
    ]);
}

// ────────────────────────────────────────────────────────────
// AJAX: Bulk Apply Alt Text to ALL Media Library images
// ────────────────────────────────────────────────────────────
add_action('wp_ajax_sgclabs_bulk_media_alt', 'sgclabs_bulk_media_alt_handler');
function sgclabs_bulk_media_alt_handler() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $offset = intval($_POST['offset'] ?? 0);
    $batch  = 30;

    // Get images with no alt text
    $images = get_posts([
        'post_type'      => 'attachment',
        'post_mime_type' => 'image',
        'posts_per_page' => $batch,
        'offset'         => $offset,
        'post_status'    => 'inherit',
        'meta_query'     => [
            'relation' => 'OR',
            ['key' => '_wp_attachment_image_alt', 'compare' => 'NOT EXISTS'],
            ['key' => '_wp_attachment_image_alt', 'value' => '', 'compare' => '='],
        ],
    ]);

    if (empty($images)) {
        wp_send_json_success(['done' => true, 'processed' => 0]);
    }

    $processed = 0;
    foreach ($images as $img) {
        // Build alt from filename
        $filename = pathinfo(get_attached_file($img->ID), PATHINFO_FILENAME);
        $clean    = ucwords(str_replace(['-', '_'], ' ', $filename));
        $alt      = $clean . ' | SGC LABS®';
        update_post_meta($img->ID, '_wp_attachment_image_alt', $alt);
        $processed++;
    }

    wp_send_json_success([
        'done'      => count($images) < $batch,
        'processed' => $processed,
        'next'      => $offset + $batch,
    ]);
}

// ────────────────────────────────────────────────────────────
// HELPER: Build alt/title from pattern tokens
// Tokens: {product_name}, {category}, {sku}, {site_name}
// ────────────────────────────────────────────────────────────
function sgclabs_build_img_alt(string $pattern, string $name, string $cat = '', string $sku = ''): string {
    $site_name = get_bloginfo('name') ?: 'SGC LABS®';
    $result = str_replace(
        ['{product_name}', '{category}', '{sku}', '{site_name}'],
        [$name,            $cat,         $sku,    $site_name],
        $pattern
    );
    return sanitize_text_field($result);
}

// ────────────────────────────────────────────────────────────
// FRONTEND: Auto-inject alt on WooCommerce product images (if missing)
// ────────────────────────────────────────────────────────────
add_filter('woocommerce_product_get_image', 'sgclabs_auto_fill_product_img_alt', 10, 5);
function sgclabs_auto_fill_product_img_alt($html, $product, $size, $attr, $placeholder) {
    if (get_option('sgclabs_img_auto_alt', '1') !== '1') return $html;

    $thumb_id = get_post_thumbnail_id($product->get_id());
    if (!$thumb_id) return $html;

    $existing_alt = get_post_meta($thumb_id, '_wp_attachment_image_alt', true);
    if (!empty($existing_alt)) return $html; // already has alt — don't override

    $name    = $product->get_name();
    $cats    = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);
    $cat_str = !empty($cats) ? $cats[0] : '';
    $sku     = $product->get_sku() ?: '';
    $pattern = get_option('sgclabs_img_alt_pattern', '{product_name} | SGC LABS®');
    $alt     = esc_attr(sgclabs_build_img_alt($pattern, $name, $cat_str, $sku));

    // Inject alt into existing HTML
    $html = preg_replace('/alt="[^"]*"/', 'alt="' . $alt . '"', $html, 1);
    return $html;
}

// ────────────────────────────────────────────────────────────
// FRONTEND: Add ImageObject schema to product pages
// ────────────────────────────────────────────────────────────
add_action('wp_head', 'sgclabs_inject_image_schema', 12);
function sgclabs_inject_image_schema() {
    if (get_option('sgclabs_img_schema_active', '1') !== '1') return;
    // v6.6.3: Skip if RankMath/Yoast active — they handle schema output
    if (defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION') || defined('AIOSEO_VERSION')) return;
    if (!is_singular('product')) return;

    global $post;
    $product  = wc_get_product($post->ID);
    if (!$product) return;

    $thumb_id = get_post_thumbnail_id($post->ID);
    if (!$thumb_id) return;

    $img_url  = wp_get_attachment_url($thumb_id);
    $img_alt  = get_post_meta($thumb_id, '_wp_attachment_image_alt', true) ?: $product->get_name();
    $img_data = wp_get_attachment_image_src($thumb_id, 'full');

    $schema = [
        '@context' => 'https://schema.org',
        '@type'    => 'ImageObject',
        'url'      => $img_url,
        'name'     => $img_alt,
        'caption'  => $product->get_name() . ' — SGC LABS® India',
    ];
    if ($img_data) {
        $schema['width']  = $img_data[1];
        $schema['height'] = $img_data[2];
    }

    echo "<script type='application/ld+json'>" . wp_json_encode($schema, JSON_UNESCAPED_SLASHES) . "</script>\n";
}

// ────────────────────────────────────────────────────────────
// FRONTEND: Add OG image tags for products (if not handled by SEO plugin)
// ────────────────────────────────────────────────────────────
add_action('wp_head', 'sgclabs_inject_product_og_image', 8);
function sgclabs_inject_product_og_image() {
    if (get_option('sgclabs_img_og_active', '1') !== '1') return;
    // Skip if major SEO plugin active — they handle OG
    if (defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION') || defined('AIOSEO_VERSION') || defined('SEOPRESS_VERSION')) return;
    if (!is_singular('product')) return;

    global $post;
    $product  = wc_get_product($post->ID);
    if (!$product) return;

    $thumb_id = get_post_thumbnail_id($post->ID);
    if (!$thumb_id) return;

    $img_url  = wp_get_attachment_url($thumb_id);
    $img_data = wp_get_attachment_image_src($thumb_id, 'full');
    $alt      = get_post_meta($thumb_id, '_wp_attachment_image_alt', true) ?: $product->get_name();

    echo "<meta property='og:image' content='" . esc_url($img_url) . "'>\n";
    echo "<meta property='og:image:alt' content='" . esc_attr($alt) . "'>\n";
    if ($img_data) {
        echo "<meta property='og:image:width' content='" . intval($img_data[1]) . "'>\n";
        echo "<meta property='og:image:height' content='" . intval($img_data[2]) . "'>\n";
    }
    echo "<meta name='twitter:image' content='" . esc_url($img_url) . "'>\n";
    echo "<meta name='twitter:image:alt' content='" . esc_attr($alt) . "'>\n";
}

// ────────────────────────────────────────────────────────────
// FRONTEND: Global lazy loading on all images
// ────────────────────────────────────────────────────────────
// NOTE: Basic lazy loading handled by google-compliance.php (sgclabs_add_lazy_loading)
// This module adds enhanced lazy loading controlled by Image SEO settings
add_filter('wp_get_attachment_image_attributes', 'sgclabs_imgseo_lazy_loading', 10, 3);
function sgclabs_imgseo_lazy_loading($attr, $attachment, $size) {
    if (get_option('sgclabs_img_lazy_load', '1') !== '1') return $attr;
    // v6.6.3: Skip on front/home/pages — Elementor gallery/slider images must load eagerly
    if (!is_admin() && function_exists('is_front_page') && (is_front_page() || is_home() || (is_page() && !is_product()))) return $attr;
    if (!isset($attr['loading'])) {
        $attr['loading'] = 'lazy';
    }
    return $attr;
}

// ────────────────────────────────────────────────────────────
// NOINDEX: Orphan attachment pages (no parent post)
// ────────────────────────────────────────────────────────────
add_action('wp_head', 'sgclabs_noindex_orphan_attachments', 1);
function sgclabs_noindex_orphan_attachments() {
    if (get_option('sgclabs_img_noindex_orphan', '1') !== '1') return;
    // v6.6.3: Skip if RankMath/Yoast active — they handle robots meta
    if (defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION')) return;
    if (!is_attachment()) return;
    global $post;
    if (empty($post->post_parent)) {
        echo "<meta name='robots' content='noindex, nofollow'>\n";
    }
}

// ────────────────────────────────────────────────────────────
// ADMIN PAGE
// ────────────────────────────────────────────────────────────
function sgclabs_image_seo_page() {
    $saved               = isset($_GET['saved']);
    $auto_alt            = get_option('sgclabs_img_auto_alt',        '1');
    $auto_title          = get_option('sgclabs_img_auto_title',       '1');
    $alt_pattern         = get_option('sgclabs_img_alt_pattern',      '{product_name} | SGC LABS®');
    $title_pattern       = get_option('sgclabs_img_title_pattern',    '{product_name} Image');
    $og_active           = get_option('sgclabs_img_og_active',        '1');
    $lazy_load           = get_option('sgclabs_img_lazy_load',        '1');
    $webp_hint           = get_option('sgclabs_img_webp_hint',        '0');
    $schema_active       = get_option('sgclabs_img_schema_active',    '1');
    $filename_clean      = get_option('sgclabs_img_filename_clean',   '1');
    $caption_auto        = get_option('sgclabs_img_caption_auto',     '0');
    $caption_pattern     = get_option('sgclabs_img_caption_pattern',  '{product_name} — SGC LABS® India');
    $noindex_orphan      = get_option('sgclabs_img_noindex_orphan',   '1');
    $sitemap_include     = get_option('sgclabs_img_sitemap_include',  '1');

    // Stats
    global $wpdb;
    $total_images      = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_mime_type LIKE 'image/%'");
    $images_with_alt   = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_wp_attachment_image_alt' AND meta_value != ''");
    $images_without_alt= max(0, $total_images - $images_with_alt);
    $alt_pct           = $total_images > 0 ? round($images_with_alt / $total_images * 100) : 0;

    $total_products    = (int)wp_count_posts('product')->publish;
    $products_with_img = (int)$wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_thumbnail_id'");
    ?>
    <div class="wrap sgclabs-wrap">
        <div class="sgclabs-header" style="background:linear-gradient(135deg,#1a1a2e,#0f3460);">
            <h1>🖼️ Image SEO Manager</h1>
            <p>Auto Alt Text • OG Image Tags • Image Schema • Lazy Load • Orphan Noindex • Bulk Optimizer</p>
        </div>

        <?php if ($saved): ?>
            <div class="notice notice-success is-dismissible"><p>✅ Image SEO settings saved!</p></div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="sgclabs-cards" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));margin-bottom:25px;">
            <div class="sgclabs-card" style="border-top:4px solid #27ae60;">
                <span class="dashicons dashicons-format-image" style="color:#27ae60;font-size:32px;width:32px;height:32px;"></span>
                <h3>Total Images</h3>
                <p style="font-size:1.8em;font-weight:bold;color:#1a1a2e;"><?php echo number_format($total_images); ?></p>
            </div>
            <div class="sgclabs-card" style="border-top:4px solid #3498db;">
                <span class="dashicons dashicons-yes-alt" style="color:#3498db;font-size:32px;width:32px;height:32px;"></span>
                <h3>With Alt Text</h3>
                <p style="font-size:1.8em;font-weight:bold;color:#27ae60;"><?php echo number_format($images_with_alt); ?></p>
                <p style="font-size:11px;color:#888;"><?php echo $alt_pct; ?>% coverage</p>
            </div>
            <div class="sgclabs-card" style="border-top:4px solid #e74c3c;">
                <span class="dashicons dashicons-warning" style="color:#e74c3c;font-size:32px;width:32px;height:32px;"></span>
                <h3>Missing Alt</h3>
                <p style="font-size:1.8em;font-weight:bold;color:#e74c3c;"><?php echo number_format($images_without_alt); ?></p>
            </div>
            <div class="sgclabs-card" style="border-top:4px solid #e67e22;">
                <span class="dashicons dashicons-cart" style="color:#e67e22;font-size:32px;width:32px;height:32px;"></span>
                <h3>Products</h3>
                <p style="font-size:1.8em;font-weight:bold;color:#1a1a2e;"><?php echo number_format($total_products); ?></p>
                <p style="font-size:11px;color:#888;"><?php echo number_format($products_with_img); ?> with image</p>
            </div>
            <div class="sgclabs-card" style="border-top:4px solid #9b59b6;">
                <span class="dashicons dashicons-chart-bar" style="color:#9b59b6;font-size:32px;width:32px;height:32px;"></span>
                <h3>Alt Coverage</h3>
                <div style="background:#e0e0e0;border-radius:10px;height:10px;overflow:hidden;margin:8px 0;">
                    <div style="background:linear-gradient(90deg,#27ae60,#2ecc71);width:<?php echo $alt_pct; ?>%;height:100%;border-radius:10px;"></div>
                </div>
                <p style="font-size:1.4em;font-weight:bold;color:#9b59b6;"><?php echo $alt_pct; ?>%</p>
            </div>
        </div>

        <!-- BULK TOOLS -->
        <div class="sgclabs-section" style="border-left:4px solid #e91e63;">
            <h2>⚡ Bulk Image Optimizer</h2>
            <p style="color:#666;">Apply alt text & titles to all products or media library images at once.</p>
            <div style="display:flex;gap:15px;flex-wrap:wrap;align-items:center;">
                <div id="sgc-pnav-imagealt" style="margin-bottom:8px;"></div>
                <div id="sgc-pnav-mediaalt" style="margin-bottom:8px;"></div>
            </div>
            <div id="sgclabs-img-bulk-log" style="display:none;margin-top:15px;padding:15px;background:#f9f9f9;border-radius:8px;border:1px solid #ddd;">
                <div id="sgclabs-img-bulk-progress-wrap" style="margin-bottom:10px;">
                    <div style="background:#e0e0e0;border-radius:10px;height:14px;overflow:hidden;">
                        <div id="sgclabs-img-bulk-bar" style="background:linear-gradient(90deg,#1a1a2e,#27ae60);height:100%;width:0%;border-radius:10px;transition:width 0.3s;"></div>
                    </div>
                </div>
                <p id="sgclabs-img-bulk-status" style="margin:0;font-size:13px;color:#333;"></p>
            </div>
        </div>

        <!-- SETTINGS FORM -->
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('sgclabs_image_seo_nonce'); ?>
            <input type="hidden" name="action" value="sgclabs_save_image_seo">

            <!-- Auto Alt & Title -->
            <div class="sgclabs-section">
                <h2>🏷️ Auto Alt Text & Title</h2>
                <table class="form-table">
                    <tr>
                        <th>Auto Alt Text</th>
                        <td>
                            <label><input type="checkbox" name="img_auto_alt" value="1" <?php checked($auto_alt,'1'); ?>>
                            Enable — Auto-fill alt text on WooCommerce product images (frontend, if alt is empty)</label>
                        </td>
                    </tr>
                    <tr>
                        <th>Alt Text Pattern</th>
                        <td>
                            <input type="text" name="img_alt_pattern" value="<?php echo esc_attr($alt_pattern); ?>" class="large-text">
                            <p class="description">
                                Available tokens: <code>{product_name}</code> <code>{category}</code> <code>{sku}</code> <code>{site_name}</code><br>
                                Example: <em>{product_name} — {category} | {site_name}</em>
                            </p>
                            <div style="background:#f0f8ff;padding:10px 15px;border-radius:6px;margin-top:8px;border-left:3px solid #3498db;">
                                <strong>Preview:</strong> <span id="sgclabs-alt-preview" style="color:#1a1a2e;"><?php echo esc_html(str_replace(['{product_name}','{category}','{sku}','{site_name}'],['Borosilicate Flask 250ml','Lab Glassware','SGC-001',get_bloginfo('name') ?: 'SGC LABS®'], $alt_pattern)); ?></span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th>Auto Image Title</th>
                        <td>
                            <label><input type="checkbox" name="img_auto_title" value="1" <?php checked($auto_title,'1'); ?>>
                            Enable — Update attachment title when bulk processing</label>
                        </td>
                    </tr>
                    <tr>
                        <th>Title Pattern</th>
                        <td>
                            <input type="text" name="img_title_pattern" value="<?php echo esc_attr($title_pattern); ?>" class="large-text">
                            <p class="description">Same tokens as alt text. Example: <em>{product_name} Product Image</em></p>
                        </td>
                    </tr>
                    <tr>
                        <th>Auto Caption</th>
                        <td>
                            <label><input type="checkbox" name="img_caption_auto" value="1" <?php checked($caption_auto,'1'); ?>>
                            Enable — Set caption on bulk process</label>
                            <br><br>
                            <input type="text" name="img_caption_pattern" value="<?php echo esc_attr($caption_pattern); ?>" class="large-text">
                            <p class="description">Caption pattern (same tokens)</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Clean Filenames</th>
                        <td>
                            <label><input type="checkbox" name="img_filename_clean" value="1" <?php checked($filename_clean,'1'); ?>>
                            Show notice if images have non-SEO filenames (e.g. <code>IMG_1234.jpg</code>)</label>
                            <p class="description">⚠️ Filenames cannot be renamed after upload without breaking URLs. This feature shows warnings only.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- OG + Schema -->
            <div class="sgclabs-section">
                <h2>📤 Open Graph & Image Schema</h2>
                <table class="form-table">
                    <tr>
                        <th>OG Image Tags</th>
                        <td>
                            <label><input type="checkbox" name="img_og_active" value="1" <?php checked($og_active,'1'); ?>>
                            Inject <code>og:image</code>, <code>og:image:width/height</code>, <code>twitter:image</code> on product pages</label>
                            <p class="description">⚠️ Auto-disabled if RankMath / Yoast / AIOSEO / SEOPress detected (prevents duplicates)</p>
                        </td>
                    </tr>
                    <tr>
                        <th>ImageObject Schema</th>
                        <td>
                            <label><input type="checkbox" name="img_schema_active" value="1" <?php checked($schema_active,'1'); ?>>
                            Inject <code>schema.org/ImageObject</code> JSON-LD on product pages</label>
                            <p class="description">Helps Google understand product images — improves Google Images & Shopping appearance</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Performance -->
            <div class="sgclabs-section">
                <h2>⚡ Performance & Indexing</h2>
                <table class="form-table">
                    <tr>
                        <th>Lazy Loading</th>
                        <td>
                            <label><input type="checkbox" name="img_lazy_load" value="1" <?php checked($lazy_load,'1'); ?>>
                            Add <code>loading="lazy"</code> to all WordPress attachment images</label>
                            <p class="description">Improves Core Web Vitals (LCP/CLS). WordPress 5.5+ already does this by default — enable only if your theme overrides it.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>WebP Hint</th>
                        <td>
                            <label><input type="checkbox" name="img_webp_hint" value="1" <?php checked($webp_hint,'0'); ?>>
                            Show admin notice for images not in WebP format</label>
                            <p class="description">Informational only. Convert to WebP via your hosting or a plugin like ShortPixel/Imagify.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Noindex Orphan Attachments</th>
                        <td>
                            <label><input type="checkbox" name="img_noindex_orphan" value="1" <?php checked($noindex_orphan,'1'); ?>>
                            Add <code>noindex</code> to attachment pages that have no parent post</label>
                            <p class="description">✅ Recommended — Orphan attachment pages are thin content that wastes crawl budget</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Image Sitemap</th>
                        <td>
                            <label><input type="checkbox" name="img_sitemap_include" value="1" <?php checked($sitemap_include,'1'); ?>>
                            Include product featured images in SGC LABS® sitemap</label>
                            <p class="description">Adds <code>&lt;image:image&gt;</code> tags to sitemap entries for WooCommerce products</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- SEO Best Practices Info Box -->
            <div class="sgclabs-section" style="background:linear-gradient(135deg,#f0fff4,#e8f8f0);border-left:4px solid #27ae60;">
                <h2>📚 Image SEO Best Practices</h2>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:15px;">
                    <div style="background:white;padding:15px;border-radius:8px;border:1px solid #ddd;">
                        <strong>✅ Alt Text Rules</strong>
                        <ul style="margin:8px 0 0;padding-left:18px;font-size:13px;color:#555;">
                            <li>Describe the image naturally</li>
                            <li>Include target keyword once</li>
                            <li>Keep under 125 characters</li>
                            <li>Don't start with "image of"</li>
                        </ul>
                    </div>
                    <div style="background:white;padding:15px;border-radius:8px;border:1px solid #ddd;">
                        <strong>✅ File Naming</strong>
                        <ul style="margin:8px 0 0;padding-left:18px;font-size:13px;color:#555;">
                            <li>Use descriptive names before upload</li>
                            <li>Lowercase, hyphens only: <code>borosilicate-flask-250ml.jpg</code></li>
                            <li>Include main keyword</li>
                            <li>Avoid <code>IMG_1234.jpg</code></li>
                        </ul>
                    </div>
                    <div style="background:white;padding:15px;border-radius:8px;border:1px solid #ddd;">
                        <strong>✅ Recommended Sizes</strong>
                        <ul style="margin:8px 0 0;padding-left:18px;font-size:13px;color:#555;">
                            <li>Product main: 800×800px minimum</li>
                            <li>OG image: 1200×630px</li>
                            <li>Compress to &lt;200KB per image</li>
                            <li>Use WebP when possible</li>
                        </ul>
                    </div>
                    <div style="background:white;padding:15px;border-radius:8px;border:1px solid #ddd;">
                        <strong>✅ Schema Benefits</strong>
                        <ul style="margin:8px 0 0;padding-left:18px;font-size:13px;color:#555;">
                            <li>Rich results in Google Images</li>
                            <li>Better Google Shopping visibility</li>
                            <li>Improved click-through rates</li>
                            <li>Enhanced product cards</li>
                        </ul>
                    </div>
                </div>
            </div>

            <?php submit_button('💾 Save Image SEO Settings'); ?>
        </form>
    </div>

    <script>
    jQuery(function($){
        // Live alt preview
        $('[name="img_alt_pattern"]').on('input', function(){
            var val = $(this).val()
                .replace('{product_name}', 'Borosilicate Flask 250ml')
                .replace('{category}',     'Lab Glassware')
                .replace('{sku}',          'SGC-001')
                .replace('{site_name}',    '<?php echo esc_js(get_bloginfo('name') ?: 'SGC LABS®'); ?>');
            $('#sgclabs-alt-preview').text(val);
        });

        // Bulk WooCommerce product images
        var bulkTotal = 0, bulkDone = 0;
        $('#sgclabs-bulk-product-alt').on('click', function(){
            $(this).prop('disabled', true).text('⏳ Processing...');
            $('#sgclabs-img-bulk-log').show();
            bulkDone = 0;
            runBulkAlt(0, 'product');
        });

        // Bulk media library
        $('#sgclabs-bulk-media-alt').on('click', function(){
            $(this).prop('disabled', true).text('⏳ Processing...');
            $('#sgclabs-img-bulk-log').show();
            bulkDone = 0;
            runBulkAlt(0, 'media');
        });

        function runBulkAlt(offset, type) {
            var action = type === 'product' ? 'sgclabs_bulk_image_alt' : 'sgclabs_bulk_media_alt';
            $.post(sgclabs_ajax.ajax_url, {
                action: action,
                nonce:  sgclabs_ajax.nonce,
                offset: offset
            }, function(res) {
                if (!res.success) {
                    $('#sgclabs-img-bulk-status').html('❌ Error: ' + (res.data || 'Unknown error'));
                    return;
                }
                bulkDone += res.data.processed;
                var pct = Math.min(100, bulkDone > 0 ? Math.round(bulkDone / (bulkDone + 1) * 100) : 0);
                $('#sgclabs-img-bulk-bar').css('width', res.data.done ? '100%' : (pct + '%'));
                $('#sgclabs-img-bulk-status').html('✅ Processed <strong>' + bulkDone + '</strong> images so far...');

                if (res.data.done) {
                    $('#sgclabs-img-bulk-bar').css('width','100%');
                    $('#sgclabs-img-bulk-status').html('🎉 Done! <strong>' + bulkDone + '</strong> images updated.');
                    $('#sgclabs-bulk-product-alt, #sgclabs-bulk-media-alt').prop('disabled', false).text(function(){
                        return $(this).attr('id') === 'sgclabs-bulk-product-alt'
                            ? '🛒 Bulk Fix WooCommerce Product Images'
                            : '🖼️ Bulk Fix Media Library (Missing Alt Only)';
                    });
                } else {
                    runBulkAlt(res.data.next, type);
                }
            });
        }
    });
    </script>
    <?php
}
