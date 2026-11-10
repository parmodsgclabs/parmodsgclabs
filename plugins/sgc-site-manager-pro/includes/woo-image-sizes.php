<?php
if (!defined('ABSPATH')) exit;

// ============================================================
// WOOCOMMERCE IMAGE SIZES MANAGER
// Control Thumbnail, Catalog, Single Product, Gallery sizes
// Appearance > Image Settings equivalent — inside SGC LABS®
// SGC LABS® Manager Pro v6.6.3
// ============================================================

// ────────────────────────────────────────────────────────────
// SAVE HANDLER
// ────────────────────────────────────────────────────────────
add_action('admin_post_sgclabs_save_woo_image_sizes', 'sgclabs_save_woo_image_sizes');
function sgclabs_save_woo_image_sizes() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'sgclabs_woo_img_nonce')) wp_die('Unauthorized');

    // Thumbnail size (WooCommerce shop/archive loop)
    update_option('woocommerce_thumbnail_image_width',       intval($_POST['woo_thumb_width']       ?? 300));
    update_option('woocommerce_thumbnail_cropping',          sanitize_text_field($_POST['woo_thumb_crop']          ?? 'uncropped'));
    update_option('woocommerce_thumbnail_cropping_custom_width',  intval($_POST['woo_thumb_crop_w']  ?? 4));
    update_option('woocommerce_thumbnail_cropping_custom_height', intval($_POST['woo_thumb_crop_h']  ?? 3));

    // Single product main image
    update_option('woocommerce_single_image_width',          intval($_POST['woo_single_width']      ?? 600));

    // v7.3.1: Product image display frame control
    update_option('sgclabs_product_img_frame_w',  intval($_POST['product_img_frame_w']  ?? 250));
    update_option('sgclabs_product_img_frame_h',  intval($_POST['product_img_frame_h']  ?? 250));
    update_option('sgclabs_product_img_zoom',     isset($_POST['product_img_zoom'])     ? '1' : '0');
    update_option('sgclabs_product_img_lightbox', isset($_POST['product_img_lightbox']) ? '1' : '0');

    // WordPress registered image sizes (via options — WC reads these)
    update_option('sgclabs_wp_thumb_w',   intval($_POST['wp_thumb_w']   ?? 150));
    update_option('sgclabs_wp_thumb_h',   intval($_POST['wp_thumb_h']   ?? 150));
    update_option('sgclabs_wp_thumb_crop',isset($_POST['wp_thumb_crop']) ? '1' : '0');
    update_option('medium_size_w',        intval($_POST['wp_medium_w']  ?? 300));
    update_option('medium_size_h',        intval($_POST['wp_medium_h']  ?? 300));
    update_option('large_size_w',         intval($_POST['wp_large_w']   ?? 1024));
    update_option('large_size_h',         intval($_POST['wp_large_h']   ?? 1024));

    update_option('sgclabs_woo_gallery_zoom',  isset($_POST['woo_gallery_zoom'])  ? '1' : '0');
    update_option('sgclabs_woo_lightbox',      isset($_POST['woo_lightbox'])      ? '1' : '0');
    update_option('sgclabs_woo_placeholder',   esc_url_raw($_POST['woo_placeholder'] ?? ''));
    update_option('sgclabs_regen_needed', '1');

    wp_redirect(admin_url('admin.php?page=sgclabs-woo-hub&tab=image-sizes&saved=1'));
    exit;
}

// ────────────────────────────────────────────────────────────
// FILTER: Override WooCommerce thumbnail sizes dynamically
// (so changes take effect even before regeneration for NEW uploads)
// ────────────────────────────────────────────────────────────
add_filter('woocommerce_get_image_size_thumbnail', 'sgclabs_override_thumb_size');
function sgclabs_override_thumb_size($size) {
    $w    = (int)get_option('woocommerce_thumbnail_image_width', 300);
    $crop = get_option('woocommerce_thumbnail_cropping', 'uncropped');

    $size['width']  = $w;
    $size['height'] = $crop === 'uncropped' ? '' : $w;
    $size['crop']   = $crop !== 'uncropped' ? 1 : 0;
    return $size;
}

add_filter('woocommerce_get_image_size_single', 'sgclabs_override_single_size');
function sgclabs_override_single_size($size) {
    $w = (int)get_option('woocommerce_single_image_width', 600);
    $size['width']  = $w;
    $size['height'] = $w;
    $size['crop']   = 0;
    return $size;
}

// ────────────────────────────────────────────────────────────
// AJAX: Trigger WP-CLI style regeneration info
// (actual regen requires server-side or a redirect to regen plugin)
// ────────────────────────────────────────────────────────────
add_action('wp_ajax_sgclabs_clear_regen_notice', 'sgclabs_clear_regen_notice');
function sgclabs_clear_regen_notice() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    update_option('sgclabs_regen_needed', '0');
    wp_send_json_success();
}

// ────────────────────────────────────────────────────────────
// ADMIN PAGE
// ────────────────────────────────────────────────────────────
function sgclabs_woo_image_sizes_page() {
    $saved = isset($_GET['saved']);

    // Current values
    $thumb_w       = (int)get_option('woocommerce_thumbnail_image_width',  300);
    $thumb_crop    = get_option('woocommerce_thumbnail_cropping',           'uncropped');
    $thumb_crop_w  = (int)get_option('woocommerce_thumbnail_cropping_custom_width',  4);
    $thumb_crop_h  = (int)get_option('woocommerce_thumbnail_cropping_custom_height', 3);
    $single_w      = (int)get_option('woocommerce_single_image_width',      600);

    // v7.3.1: Frame control
    $frame_w       = (int)get_option('sgclabs_product_img_frame_w',  250);
    $frame_h       = (int)get_option('sgclabs_product_img_frame_h',  250);
    $img_zoom      = get_option('sgclabs_product_img_zoom',     '1');
    $img_lightbox  = get_option('sgclabs_product_img_lightbox', '1');

    $wp_thumb_w    = (int)get_option('sgclabs_wp_thumb_w',   150);
    $wp_thumb_h    = (int)get_option('sgclabs_wp_thumb_h',   150);
    $wp_thumb_crop = get_option('sgclabs_wp_thumb_crop',     '1');
    $wp_medium_w   = (int)get_option('medium_size_w',         300);
    $wp_medium_h   = (int)get_option('medium_size_h',         300);
    $wp_large_w    = (int)get_option('large_size_w',          1024);
    $wp_large_h    = (int)get_option('large_size_h',          1024);

    $gallery_zoom  = get_option('sgclabs_woo_gallery_zoom',  '0');
    $lightbox      = get_option('sgclabs_woo_lightbox',      '1');
    $placeholder   = get_option('sgclabs_woo_placeholder',   '');
    $regen_needed  = get_option('sgclabs_regen_needed',      '0');

    // Check if WooCommerce is active
    $woo_active = class_exists('WooCommerce');
    ?>
    <div class="wrap sgclabs-wrap">
<?php sgclabs_module_control_bar(["module_id"=>"woo-image-sizes","module_name"=>"WooCommerce Image Sizes","module_icon"=>"📐","option_key"=>"sgclabs_woo_image_sizes_active","default"=>"1","description"=>"Custom image dimensions, zoom, lightbox for product photos"]); ?>
        <div class="sgclabs-header" style="background:linear-gradient(135deg,#7b2d8b,#4a1a8c);">
            <h1>🖼️ WooCommerce Image Sizes</h1>
            <p>Control Thumbnail • Single Product • Gallery • WordPress Core Sizes — all in one place</p>
        </div>

        <?php if ($saved): ?>
            <div class="notice notice-success is-dismissible"><p>✅ Image size settings saved! <?php if ($regen_needed === '1'): ?><strong>⚠️ Regenerate thumbnails for existing images (see below).</strong><?php endif; ?></p></div>
        <?php endif; ?>

        <?php if (!$woo_active): ?>
            <div class="notice notice-error"><p>❌ <strong>WooCommerce not active!</strong> These settings require WooCommerce to be installed and active.</p></div>
        <?php endif; ?>

        <?php if ($regen_needed === '1'): ?>
            <div class="notice notice-warning" id="sgclabs-regen-notice">
                <p>
                    ⚠️ <strong>Thumbnail regeneration needed!</strong>
                    Image sizes changed — existing images need to be regenerated to apply new sizes.<br>
                    <strong>Options:</strong>
                    Install <a href="<?php echo admin_url('plugin-install.php?s=regenerate+thumbnails&tab=search'); ?>" target="_blank">Regenerate Thumbnails plugin</a>
                    or run: <code>wp media regenerate --yes</code> via WP-CLI.
                    <a href="#" id="sgclabs-dismiss-regen" style="margin-left:10px;color:#999;font-size:12px;">Dismiss</a>
                </p>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('sgclabs_woo_img_nonce'); ?>
            <input type="hidden" name="action" value="sgclabs_save_woo_image_sizes">

            <!-- v7.3.1: Product Image Frame + Zoom Control -->
            <div class="sgclabs-section" style="border-left:4px solid #e74c3c;">
                <h2>🖼️ Product Page — Image Display Frame</h2>
                <p style="color:#666;margin-top:-5px;">Product page pe image frame ka maximum size set karo. Actual image zoom/lightbox se full size dikhai degi.</p>
                <table class="form-table">
                    <tr>
                        <th><strong>Frame Max Width × Height (px)</strong>
                            <p style="font-weight:normal;font-size:12px;color:#888;margin:4px 0 0;">Product gallery container ka max size on product page</p>
                        </th>
                        <td>
                            <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                                <label>W: <input type="number" name="product_img_frame_w" value="<?php echo $frame_w; ?>" min="100" max="1200" style="width:90px;"> px</label>
                                <label>H: <input type="number" name="product_img_frame_h" value="<?php echo $frame_h; ?>" min="100" max="1200" style="width:90px;"> px</label>
                                <div style="background:#f9f9f9;border:2px dashed #e74c3c;border-radius:6px;padding:8px 16px;text-align:center;">
                                    <span style="font-weight:bold;color:#e74c3c;font-size:13px;"><?php echo $frame_w; ?>×<?php echo $frame_h; ?>px</span><br>
                                    <span style="font-size:11px;color:#888;">max frame</span>
                                </div>
                            </div>
                            <p class="description" style="margin-top:8px;">💡 Default: <strong>250×250px</strong>. Image aspect ratio is preserved (object-fit: contain). Click-to-zoom shows full original size.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><strong>Click-to-Zoom Lightbox</strong></th>
                        <td>
                            <label><input type="checkbox" name="product_img_lightbox" value="1" <?php checked($img_lightbox,'1'); ?>>
                            Click product image → open full-size in dark overlay (custom lightbox)</label>
                            <p class="description">Esc key ya overlay click se close hoga. WooCommerce PhotoSwipe se independent hai.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><strong>Hover Zoom Cursor</strong></th>
                        <td>
                            <label><input type="checkbox" name="product_img_zoom" value="1" <?php checked($img_zoom,'1'); ?>>
                            Show zoom-in cursor on hover (CSS only, no JS needed)</label>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- WooCommerce Sizes -->
            <div class="sgclabs-section" style="border-left:4px solid #96588a;">
                <h2>🛒 WooCommerce Image Sizes</h2>
                <p style="color:#666;margin-top:-5px;">These settings control how WooCommerce displays product images on your store.</p>

                <table class="form-table">
                    <!-- Thumbnail / Shop Loop -->
                    <tr>
                        <th style="width:220px;">
                            <strong>🏪 Shop/Archive Thumbnail</strong>
                            <p style="font-weight:normal;font-size:12px;color:#888;margin:4px 0 0;">Shown in: Shop, Category, Search, Related Products</p>
                        </th>
                        <td>
                            <div style="display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap;">
                                <div>
                                    <label style="display:block;font-weight:600;margin-bottom:4px;">Width (px)</label>
                                    <input type="number" name="woo_thumb_width" value="<?php echo $thumb_w; ?>" min="50" max="2000" style="width:100px;">
                                    <p class="description">Height is automatic based on crop setting</p>
                                </div>
                                <div style="flex:1;min-width:200px;">
                                    <label style="display:block;font-weight:600;margin-bottom:4px;">Cropping</label>
                                    <select name="woo_thumb_crop" id="woo_thumb_crop_select" style="min-width:200px;">
                                        <option value="uncropped"  <?php selected($thumb_crop,'uncropped'); ?>>Uncropped — Scale proportionally</option>
                                        <option value="1:1"        <?php selected($thumb_crop,'1:1');       ?>>Square (1:1)</option>
                                        <option value="custom"     <?php selected($thumb_crop,'custom');    ?>>Custom Ratio</option>
                                    </select>
                                    <div id="sgclabs-custom-crop" style="display:<?php echo $thumb_crop==='custom'?'block':'none'; ?>;margin-top:10px;background:#f9f9f9;padding:10px;border-radius:6px;border:1px solid #ddd;">
                                        <label>Width ratio: <input type="number" name="woo_thumb_crop_w" value="<?php echo $thumb_crop_w; ?>" min="1" max="99" style="width:60px;"> :</label>
                                        <label>Height ratio: <input type="number" name="woo_thumb_crop_h" value="<?php echo $thumb_crop_h; ?>" min="1" max="99" style="width:60px;"></label>
                                    </div>
                                    <div style="margin-top:10px;" id="sgclabs-thumb-preview-box">
                                        <?php sgclabs_render_size_preview($thumb_w, $thumb_crop, $thumb_crop_w, $thumb_crop_h, 'Shop Thumbnail'); ?>
                                    </div>
                                </div>
                            </div>
                            <p class="description" style="margin-top:10px;">💡 <strong>Recommended:</strong> 300–500px width. Square (1:1) works best for uniform shop grids.</p>
                        </td>
                    </tr>

                    <!-- Single Product Image -->
                    <tr>
                        <th>
                            <strong>📦 Single Product Image</strong>
                            <p style="font-weight:normal;font-size:12px;color:#888;margin:4px 0 0;">Shown in: Product page main image</p>
                        </th>
                        <td>
                            <div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap;">
                                <div>
                                    <label style="display:block;font-weight:600;margin-bottom:4px;">Width (px)</label>
                                    <input type="number" name="woo_single_width" value="<?php echo $single_w; ?>" min="200" max="2000" style="width:100px;">
                                </div>
                                <div style="flex:1;">
                                    <div style="display:inline-block;background:#f0f0f0;border:2px dashed #96588a;border-radius:6px;padding:8px 16px;text-align:center;min-width:120px;">
                                        <span style="font-weight:bold;color:#96588a;font-size:14px;"><?php echo $single_w; ?>px</span><br>
                                        <span style="font-size:11px;color:#888;">width (height auto)</span>
                                    </div>
                                </div>
                            </div>
                            <p class="description" style="margin-top:8px;">💡 <strong>Recommended:</strong> 600–800px. Images are never stretched beyond their original size.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- WordPress Core Sizes -->
            <div class="sgclabs-section" style="border-left:4px solid #3498db;">
                <h2>🔧 WordPress Core Image Sizes</h2>
                <p style="color:#666;margin-top:-5px;">Same as <strong>Settings → Media</strong> — managed here for convenience.</p>
                <table class="form-table">
                    <tr>
                        <th>
                            <strong>Thumbnail Size</strong>
                            <p style="font-weight:normal;font-size:12px;color:#888;margin:4px 0 0;">WP Media thumbnail</p>
                        </th>
                        <td>
                            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                                <label>W: <input type="number" name="wp_thumb_w" value="<?php echo $wp_thumb_w; ?>" min="50" max="1000" style="width:80px;"> px</label>
                                <label>H: <input type="number" name="wp_thumb_h" value="<?php echo $wp_thumb_h; ?>" min="50" max="1000" style="width:80px;"> px</label>
                                <label><input type="checkbox" name="wp_thumb_crop" value="1" <?php checked($wp_thumb_crop,'1'); ?>> Hard crop</label>
                            </div>
                            <p class="description">Default: 150×150. Used in media library grid view.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <strong>Medium Size</strong>
                            <p style="font-weight:normal;font-size:12px;color:#888;margin:4px 0 0;">Inserted in posts/pages</p>
                        </th>
                        <td>
                            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                                <label>W: <input type="number" name="wp_medium_w" value="<?php echo $wp_medium_w; ?>" min="100" max="2000" style="width:80px;"> px</label>
                                <label>H: <input type="number" name="wp_medium_h" value="<?php echo $wp_medium_h; ?>" min="100" max="2000" style="width:80px;"> px</label>
                            </div>
                            <p class="description">Default: 300×300. Scaled proportionally (no crop).</p>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <strong>Large Size</strong>
                            <p style="font-weight:normal;font-size:12px;color:#888;margin:4px 0 0;">Full-width content images</p>
                        </th>
                        <td>
                            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                                <label>W: <input type="number" name="wp_large_w" value="<?php echo $wp_large_w; ?>" min="300" max="5000" style="width:80px;"> px</label>
                                <label>H: <input type="number" name="wp_large_h" value="<?php echo $wp_large_h; ?>" min="300" max="5000" style="width:80px;"> px</label>
                            </div>
                            <p class="description">Default: 1024×1024. Scaled proportionally.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- WooCommerce Display Options -->
            <div class="sgclabs-section" style="border-left:4px solid #27ae60;">
                <h2>🔍 Product Gallery & Display Options</h2>
                <table class="form-table">
                    <tr>
                        <th>Gallery Zoom</th>
                        <td>
                            <label><input type="checkbox" name="woo_gallery_zoom" value="1" <?php checked($gallery_zoom,'1'); ?>>
                            Enable zoom on hover for single product images</label>
                            <p class="description">Requires WooCommerce gallery scripts (enabled by default in WooCommerce)</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Lightbox</th>
                        <td>
                            <label><input type="checkbox" name="woo_lightbox" value="1" <?php checked($lightbox,'1'); ?>>
                            Enable lightbox (click to expand) on product gallery</label>
                            <p class="description">WooCommerce built-in PhotoSwipe lightbox</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Custom Placeholder</th>
                        <td>
                            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                                <input type="url" name="woo_placeholder" value="<?php echo esc_attr($placeholder); ?>" class="large-text" placeholder="https://yoursite.com/wp-content/uploads/placeholder.png">
                                <?php if ($placeholder): ?>
                                    <img src="<?php echo esc_url($placeholder); ?>" style="height:50px;width:50px;object-fit:contain;border:1px solid #ddd;border-radius:4px;" onerror="this.style.display='none'">
                                <?php endif; ?>
                            </div>
                            <p class="description">Shown when a product has no image. Leave empty for WooCommerce default placeholder.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Size Reference Chart -->
            <div class="sgclabs-section" style="background:linear-gradient(135deg,#fefefe,#f5f0ff);border-left:4px solid #9b59b6;">
                <h2>📐 Recommended Size Reference</h2>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                        <thead>
                            <tr style="background:#9b59b6;color:white;">
                                <th style="padding:10px 15px;text-align:left;">Image Type</th>
                                <th style="padding:10px 15px;text-align:left;">Used In</th>
                                <th style="padding:10px 15px;text-align:left;">Recommended Size</th>
                                <th style="padding:10px 15px;text-align:left;">Aspect Ratio</th>
                                <th style="padding:10px 15px;text-align:left;">Max File Size</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $rows = [
                                ['Shop Thumbnail',       'Shop/Archive loop',           '300–500px',    '1:1 (Square)',  '60–100KB'],
                                ['Single Product',       'Product page main image',     '600–800px',    '1:1 or 4:3',   '100–200KB'],
                                ['Product Gallery',      'Product page gallery thumbs', '150–200px',    '1:1',           '30–60KB'],
                                ['OG / Social Image',    'Facebook, Twitter preview',   '1200×630px',   '1.91:1',        '200–300KB'],
                                ['Category Banner',      'Category header',             '1920×400px',   '4.8:1',         '150–250KB'],
                                ['Blog Featured',        'Blog post thumbnail',         '1200×628px',   '1.91:1',        '100–200KB'],
                                ['WP Thumbnail (core)',  'Media library grid',          '150×150px',    '1:1',           '20–40KB'],
                                ['WP Medium (core)',     'Inline post images',          '300×300px',    'Proportional',  '40–80KB'],
                                ['WP Large (core)',      'Full-width content images',   '1024×1024px',  'Proportional',  '80–200KB'],
                            ];
                            foreach ($rows as $i => $row):
                                $bg = $i % 2 === 0 ? '#fff' : '#f9f5ff';
                            ?>
                            <tr style="background:<?php echo $bg; ?>;">
                                <td style="padding:9px 15px;font-weight:600;color:#1a1a2e;"><?php echo esc_html($row[0]); ?></td>
                                <td style="padding:9px 15px;color:#555;"><?php echo esc_html($row[1]); ?></td>
                                <td style="padding:9px 15px;"><code><?php echo esc_html($row[2]); ?></code></td>
                                <td style="padding:9px 15px;color:#666;"><?php echo esc_html($row[3]); ?></td>
                                <td style="padding:9px 15px;color:#27ae60;font-weight:600;"><?php echo esc_html($row[4]); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Regeneration Guide -->
            <div class="sgclabs-section" style="background:#fff8e6;border-left:4px solid #f39c12;">
                <h2>⚠️ After Saving — Regenerate Thumbnails</h2>
                <p>Changing image sizes only affects <strong>newly uploaded images</strong>. To apply new sizes to existing images:</p>
                <div style="display:flex;gap:15px;flex-wrap:wrap;margin-top:10px;">
                    <div style="background:white;padding:15px;border-radius:8px;border:1px solid #ddd;flex:1;min-width:220px;">
                        <strong>Option 1: Plugin (Easiest)</strong><br>
                        <a href="<?php echo admin_url('plugin-install.php?s=regenerate+thumbnails&tab=search&type=term'); ?>" target="_blank" class="button button-secondary" style="margin-top:8px;">
                            Install Regenerate Thumbnails →
                        </a>
                    </div>
                    <div style="background:white;padding:15px;border-radius:8px;border:1px solid #ddd;flex:1;min-width:220px;">
                        <strong>Option 2: WP-CLI (Fastest)</strong><br>
                        <code style="display:block;margin-top:8px;background:#f5f5f5;padding:8px;border-radius:4px;font-size:12px;">wp media regenerate --yes</code>
                    </div>
                    <div style="background:white;padding:15px;border-radius:8px;border:1px solid #ddd;flex:1;min-width:220px;">
                        <strong>Option 3: New Uploads</strong><br>
                        <p style="font-size:13px;color:#666;margin:8px 0 0;">New images uploaded after saving will automatically use the new sizes. No action needed for future uploads.</p>
                    </div>
                </div>
            </div>

            <?php submit_button('💾 Save Image Size Settings'); ?>
        </form>
    </div>

    <script>
    jQuery(function($){
        // Toggle custom crop ratio inputs
        $('#woo_thumb_crop_select').on('change', function(){
            $('#sgclabs-custom-crop').toggle($(this).val() === 'custom');
        });

        // Dismiss regen notice
        $('#sgclabs-dismiss-regen').on('click', function(e){
            e.preventDefault();
            $.post(sgclabs_ajax.ajax_url, {action:'sgclabs_clear_regen_notice', nonce:sgclabs_ajax.nonce});
            $('#sgclabs-regen-notice').fadeOut();
        });
    });
    </script>
    <?php
}

// ────────────────────────────────────────────────────────────
// HELPER: Render a visual size preview box
// ────────────────────────────────────────────────────────────
function sgclabs_render_size_preview(int $w, string $crop, int $crop_w = 4, int $crop_h = 3, string $label = '') {
    // Calculate display height
    if ($crop === 'uncropped') {
        $h = 'auto';
    } elseif ($crop === '1:1') {
        $h = $w;
    } elseif ($crop === 'custom') {
        $h = $crop_h > 0 ? round($w * $crop_h / max(1, $crop_w)) : $w;
    } else {
        $h = $w;
    }

    // Scale down for display (max 80px wide)
    $scale   = min(1, 80 / $w);
    $disp_w  = round($w * $scale);
    $disp_h  = $h === 'auto' ? round($disp_w * 0.75) : round($h * $scale);
    $crop_lbl = $crop === 'uncropped' ? 'No crop' : ($crop === 'custom' ? "{$crop_w}:{$crop_h}" : '1:1');
    $h_disp  = $h === 'auto' ? 'auto' : $h . 'px';
    ?>
    <div style="display:inline-flex;align-items:center;gap:12px;background:#f9f9f9;padding:10px 14px;border-radius:8px;border:1px solid #ddd;">
        <div style="width:<?php echo (int) $disp_w; ?>px;height:<?php echo (int) $disp_h; ?>px;background:linear-gradient(135deg,#96588a,#4a1a8c);border-radius:4px;display:flex;align-items:center;justify-content:center;">
            <span style="color:white;font-size:9px;font-weight:bold;"><?php echo $w; ?>×<?php echo $h_disp; ?></span>
        </div>
        <div style="font-size:12px;color:#555;">
            <strong><?php echo esc_html($label); ?></strong><br>
            <?php echo $w; ?>px × <?php echo $h_disp; ?><br>
            <span style="color:#888;">Crop: <?php echo esc_html($crop_lbl); ?></span>
        </div>
    </div>
    <?php
}

// ────────────────────────────────────────────────────────────
// APPLY gallery/lightbox settings to WooCommerce
// ────────────────────────────────────────────────────────────
add_filter('woocommerce_single_product_zoom_enabled', 'sgclabs_woo_zoom_setting');
function sgclabs_woo_zoom_setting($enabled) {
    return get_option('sgclabs_woo_gallery_zoom', '0') === '1';
}

add_filter('woocommerce_single_product_photoswipe_enabled', 'sgclabs_woo_lightbox_setting');
function sgclabs_woo_lightbox_setting($enabled) {
    return get_option('sgclabs_woo_lightbox', '1') === '1';
}

add_filter('woocommerce_placeholder_img_src', 'sgclabs_custom_placeholder');
function sgclabs_custom_placeholder($src) {
    $custom = get_option('sgclabs_woo_placeholder', '');
    return !empty($custom) ? esc_url($custom) : $src;
}

// ── v7.3.1: Product image frame size + click-to-zoom ─────────────────────────
add_action('wp_head', 'sgclabs_product_image_frame_css', 99);
function sgclabs_product_image_frame_css() {
    if ( ! is_product() ) return;

    $fw      = intval( get_option( 'sgclabs_product_img_frame_w',  250 ) );
    $fh      = intval( get_option( 'sgclabs_product_img_frame_h',  250 ) );
    $zoom    = get_option( 'sgclabs_product_img_zoom',     '1' ) === '1';
    $lb      = get_option( 'sgclabs_product_img_lightbox', '1' ) === '1';

    echo "<style id='sgclabs-img-frame'>
/* SGC LABS® — Product image frame control v7.3.1 */
.woocommerce-product-gallery,
.woocommerce-product-gallery__wrapper {
    max-width: {$fw}px !important;
    max-height: {$fh}px !important;
}
.woocommerce-product-gallery__image,
.woocommerce-product-gallery__image a,
.woocommerce-product-gallery__image img {
    max-width: {$fw}px !important;
    max-height: {$fh}px !important;
    width: 100% !important;
    height: auto !important;
    object-fit: contain !important;
    display: block;
}
/* Thumbnail strip stays proportional */
.flex-control-thumbs li img {
    max-width: 60px;
    max-height: 60px;
    object-fit: contain;
    cursor: pointer;
}
" . ( $zoom ? "
/* Hover zoom cursor */
.woocommerce-product-gallery__image img {
    cursor: zoom-in !important;
    transition: transform .15s ease;
}
" : "" ) . "
</style>\n";

    // Custom lightbox — click product image → open full-size in overlay
    if ( $lb ) {
        echo "<style id='sgclabs-lightbox-css'>
#sgclabs-lightbox-overlay {
    display:none;position:fixed;inset:0;z-index:99999;
    background:rgba(0,0,0,.88);align-items:center;justify-content:center;
    cursor:zoom-out;
}
#sgclabs-lightbox-overlay.open { display:flex; }
#sgclabs-lightbox-overlay img {
    max-width:92vw;max-height:92vh;object-fit:contain;
    border-radius:6px;box-shadow:0 8px 40px rgba(0,0,0,.6);
    cursor:default;
}
#sgclabs-lightbox-close {
    position:fixed;top:18px;right:24px;color:#fff;font-size:32px;
    cursor:pointer;line-height:1;user-select:none;z-index:100000;
    font-weight:300;opacity:.8;
}
#sgclabs-lightbox-close:hover { opacity:1; }
</style>
<div id='sgclabs-lightbox-overlay' role='dialog' aria-modal='true' aria-label='Product image fullscreen'>
  <span id='sgclabs-lightbox-close' title='Close'>&times;</span>
  <img id='sgclabs-lightbox-img' src='' alt='Product image'>
</div>
<script>
(function(){
    function openLB(src,alt){
        var el=document.getElementById('sgclabs-lightbox-img');
        el.src=src; el.alt=alt||'';
        document.getElementById('sgclabs-lightbox-overlay').classList.add('open');
        document.body.style.overflow='hidden';
    }
    function closeLB(){
        document.getElementById('sgclabs-lightbox-overlay').classList.remove('open');
        document.body.style.overflow='';
    }
    document.addEventListener('DOMContentLoaded',function(){
        // Intercept clicks on product gallery images
        document.querySelector('.woocommerce-product-gallery')?.addEventListener('click',function(e){
            var img=e.target.closest('.woocommerce-product-gallery__image img');
            if(!img) return;
            e.preventDefault(); e.stopPropagation();
            // Get full-size src from data-large_image or closest <a> href
            var a=img.closest('a');
            var full=img.getAttribute('data-large_image')||
                     img.getAttribute('data-src')||
                     (a?a.getAttribute('href'):null)||
                     img.src;
            openLB(full, img.alt);
        });
        document.getElementById('sgclabs-lightbox-overlay')?.addEventListener('click',function(e){
            if(e.target.id==='sgclabs-lightbox-overlay'||e.target.id==='sgclabs-lightbox-close') closeLB();
        });
        document.addEventListener('keydown',function(e){if(e.key==='Escape') closeLB();});
    });
})();
</script>\n";
    }
}
