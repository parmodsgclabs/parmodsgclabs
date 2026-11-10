<?php
if (!defined('ABSPATH')) exit;

// ============================================================
// WHATSAPP SHARE & LEAD GEN MODULE
// SGC LABS® Manager Pro v6.7.0
// Product page pe WhatsApp share button + Catalog share
// ============================================================

add_action('wp', 'sgclabs_wa_share_init');
function sgclabs_wa_share_init() {
    if (get_option('sgclabs_wa_share_active', '1') !== '1') return;
    add_action('wp_footer', 'sgclabs_wa_share_frontend_assets');

    // Product page pe share button
    if (is_product()) {
        $position = get_option('sgclabs_wa_share_position', 'after_meta');
        add_action($position === 'after_meta' ? 'woocommerce_product_meta_end' : 'woocommerce_share', 'sgclabs_wa_share_button', 10);
    }

    // Shop/archive page pe share button
    if (get_option('sgclabs_wa_share_archive', '0') === '1') {
        add_action('woocommerce_after_shop_loop_item', 'sgclabs_wa_share_button_archive', 15);
    }
}

// ── Single Product Share Button ──────────────────────────────
function sgclabs_wa_share_button() {
    global $product;
    if (!$product) return;

    $wa_number = get_option('sgclabs_wa_number', '919996416935');
    $wa_number = preg_replace('/[^0-9]/', '', $wa_number);

    $product_name  = $product->get_name();
    $product_url   = get_permalink($product->get_id());
    $product_sku   = $product->get_sku();
    $product_price = wp_strip_all_tags($product->get_price_html());
    $cat_names     = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);
    $cat_str       = !empty($cat_names) ? $cat_names[0] : 'Lab Equipment';

    $custom_msg = get_option('sgclabs_wa_share_msg',
        "Hi SGC LABS! I'm interested in:\n\n*{product_name}*\nSKU: {sku}\nCategory: {category}\nPrice: {price}\nLink: {url}\n\nPlease send more details."
    );

    $msg = str_replace(
        ['{product_name}', '{sku}', '{category}', '{price}', '{url}'],
        [$product_name, $product_sku, $cat_str, $product_price, $product_url],
        $custom_msg
    );

    $wa_url     = 'https://api.whatsapp.com/send/?phone=' . $wa_number . '&text=' . rawurlencode($msg);
    $share_url  = 'https://api.whatsapp.com/send/?text=' . rawurlencode("Check out: *{$product_name}*\n{$product_url}");

    $btn_color  = get_option('sgclabs_wa_share_btn_color', '#25d366');
    $btn_text   = get_option('sgclabs_wa_share_btn_text', '💬 Enquire on WhatsApp');
    $share_text = get_option('sgclabs_wa_share_share_text', '📤 Share on WhatsApp');

    echo '<div class="sgclabs-wa-share-wrap">';
    echo '<a href="' . esc_url($wa_url) . '" target="_blank" rel="noopener" class="sgclabs-wa-enquire-btn"
        style="background:' . esc_attr($btn_color) . ';">'
        . esc_html($btn_text) . '</a>';

    if (get_option('sgclabs_wa_share_show_share', '1') === '1') {
        echo '<a href="' . esc_url($share_url) . '" target="_blank" rel="noopener" class="sgclabs-wa-share-btn">'
            . esc_html($share_text) . '</a>';
    }
    echo '</div>';
}

// ── Archive Page Share Button ────────────────────────────────
function sgclabs_wa_share_button_archive() {
    global $product;
    if (!$product) return;

    $wa_number = preg_replace('/[^0-9]/', '', get_option('sgclabs_wa_number', '919996416935'));
    $msg       = "Hi! Interested in: *{$product->get_name()}*\nSKU: {$product->get_sku()}\n" . get_permalink($product->get_id());
    $wa_url    = 'https://api.whatsapp.com/send/?phone=' . $wa_number . '&text=' . rawurlencode($msg);

    echo '<a href="' . esc_url($wa_url) . '" target="_blank" class="sgclabs-wa-archive-btn">💬 WhatsApp</a>';
}

// ── Frontend CSS + JS ────────────────────────────────────────
function sgclabs_wa_share_frontend_assets() {
    if (!is_product() && !is_shop() && !is_product_category()) return;
    ?>
    <style id="sgclabs-wa-share-css">
    .sgclabs-wa-share-wrap {
        display: flex;
        gap: 10px;
        margin: 16px 0;
        flex-wrap: wrap;
    }
    .sgclabs-wa-enquire-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 20px;
        border-radius: 7px;
        color: #fff !important;
        font-size: 14px;
        font-weight: 700;
        text-decoration: none !important;
        transition: opacity .2s, transform .1s;
        flex: 1;
        justify-content: center;
        min-height: 48px;
    }
    .sgclabs-wa-enquire-btn:hover { opacity: 0.88; transform: translateY(-1px); }

    .sgclabs-wa-share-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 20px;
        border-radius: 7px;
        background: transparent;
        border: 2px solid #25d366;
        color: #25d366 !important;
        font-size: 14px;
        font-weight: 700;
        text-decoration: none !important;
        transition: all .2s;
        flex: 1;
        justify-content: center;
        min-height: 48px;
    }
    .sgclabs-wa-share-btn:hover {
        background: #25d366;
        color: #fff !important;
        transform: translateY(-1px);
    }

    .sgclabs-wa-archive-btn {
        display: block;
        text-align: center;
        padding: 8px;
        background: #25d366;
        color: #fff !important;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none !important;
        margin-top: 6px;
        transition: opacity .2s;
    }
    .sgclabs-wa-archive-btn:hover { opacity: 0.85; }

    @media(max-width:480px) {
        .sgclabs-wa-share-wrap { flex-direction: column; }
    }
    </style>
    <?php
}

// ── ADMIN SETTINGS PAGE ──────────────────────────────────────
add_action('admin_post_sgclabs_save_wa_share', 'sgclabs_save_wa_share_settings');
function sgclabs_save_wa_share_settings() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'sgclabs_wa_share_nonce')) wp_die('Unauthorized');

    update_option('sgclabs_wa_share_active',     isset($_POST['wa_share_active'])    ? '1' : '0');
    update_option('sgclabs_wa_share_archive',    isset($_POST['wa_share_archive'])   ? '1' : '0');
    update_option('sgclabs_wa_share_show_share', isset($_POST['wa_show_share'])      ? '1' : '0');
    update_option('sgclabs_wa_share_btn_color',  sanitize_hex_color($_POST['wa_btn_color']   ?? '#25d366'));
    update_option('sgclabs_wa_share_btn_text',   sanitize_text_field($_POST['wa_btn_text']   ?? '💬 Enquire on WhatsApp'));
    update_option('sgclabs_wa_share_share_text', sanitize_text_field($_POST['wa_share_text'] ?? '📤 Share on WhatsApp'));
    update_option('sgclabs_wa_share_msg',        sanitize_textarea_field($_POST['wa_share_msg'] ?? ''));
    update_option('sgclabs_wa_share_position',   sanitize_key($_POST['wa_share_position'] ?? 'after_meta'));

    wp_redirect(admin_url('admin.php?page=sgclabs-site-tools-hub&tab=wa-share&saved=1'));
    exit;
}

function sgclabs_wa_share_admin_page() {
    $saved = isset($_GET['saved']) ? '1' : '0';
    ?>
    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:12px;padding:24px;max-width:700px;">

        <?php if ($saved === '1'): ?>
            <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:10px 14px;margin-bottom:16px;color:#166534;font-size:13px;">✅ Settings saved!</div>
        <?php endif; ?>

        <h3 style="margin:0 0 6px;color:#1a1a2e;">💬 WhatsApp Share & Enquiry</h3>
        <p style="color:#666;font-size:13px;margin:0 0 20px;">Product pages pe WhatsApp enquiry + share buttons add karo.</p>

        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="sgclabs_save_wa_share">
            <?php wp_nonce_field('sgclabs_wa_share_nonce'); ?>

            <table class="form-table" style="font-size:13px;">
                <tr>
                    <th>Enable Module</th>
                    <td><label><input type="checkbox" name="wa_share_active" value="1" <?php checked(get_option('sgclabs_wa_share_active','1'),'1'); ?>> WhatsApp Share module active karo</label></td>
                </tr>
                <tr>
                    <th>Show on Shop Page</th>
                    <td><label><input type="checkbox" name="wa_share_archive" value="1" <?php checked(get_option('sgclabs_wa_share_archive','0'),'1'); ?>> Product listing pe bhi dikhao</label></td>
                </tr>
                <tr>
                    <th>Show Share Button</th>
                    <td><label><input type="checkbox" name="wa_show_share" value="1" <?php checked(get_option('sgclabs_wa_share_show_share','1'),'1'); ?>> "Share on WhatsApp" button bhi dikhao</label></td>
                </tr>
                <tr>
                    <th>Button Position</th>
                    <td>
                        <select name="wa_share_position" style="padding:6px;border-radius:6px;border:1px solid #ddd;">
                            <option value="after_meta" <?php selected(get_option('sgclabs_wa_share_position','after_meta'),'after_meta'); ?>>After Product Meta (SKU/Category)</option>
                            <option value="woocommerce_share" <?php selected(get_option('sgclabs_wa_share_position','after_meta'),'woocommerce_share'); ?>>After Share section</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Button Color</th>
                    <td>
                        <input type="color" name="wa_btn_color" value="<?php echo esc_attr(get_option('sgclabs_wa_share_btn_color','#25d366')); ?>" style="height:36px;width:60px;border-radius:6px;border:1px solid #ddd;cursor:pointer;">
                        <span style="font-size:12px;color:#666;margin-left:8px;">Default: WhatsApp Green #25d366</span>
                    </td>
                </tr>
                <tr>
                    <th>Enquiry Button Text</th>
                    <td><input type="text" name="wa_btn_text" value="<?php echo esc_attr(get_option('sgclabs_wa_share_btn_text','💬 Enquire on WhatsApp')); ?>" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;"></td>
                </tr>
                <tr>
                    <th>Share Button Text</th>
                    <td><input type="text" name="wa_share_text" value="<?php echo esc_attr(get_option('sgclabs_wa_share_share_text','📤 Share on WhatsApp')); ?>" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;"></td>
                </tr>
                <tr>
                    <th>Enquiry Message</th>
                    <td>
                        <textarea name="wa_share_msg" rows="6" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;font-family:monospace;font-size:12px;"><?php echo esc_textarea(get_option('sgclabs_wa_share_msg', "Hi SGC LABS! I'm interested in:\n\n*{product_name}*\nSKU: {sku}\nCategory: {category}\nPrice: {price}\nLink: {url}\n\nPlease send more details.")); ?></textarea>
                        <p style="font-size:11px;color:#888;margin-top:4px;">Variables: {product_name} {sku} {category} {price} {url}</p>
                    </td>
                </tr>
            </table>

            <!-- Preview -->
            <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:14px;margin:16px 0;">
                <div style="font-size:12px;font-weight:600;color:#166534;margin-bottom:10px;">Preview</div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <div style="flex:1;min-width:180px;padding:12px 20px;background:#25d366;border-radius:7px;color:#fff;font-weight:700;font-size:14px;text-align:center;">
                        💬 Enquire on WhatsApp
                    </div>
                    <div style="flex:1;min-width:180px;padding:12px 20px;border:2px solid #25d366;border-radius:7px;color:#25d366;font-weight:700;font-size:14px;text-align:center;">
                        📤 Share on WhatsApp
                    </div>
                </div>
            </div>

            <button type="submit" class="button button-primary" style="background:#1565c0;border-color:#1565c0;padding:8px 24px;border-radius:7px;font-size:13px;font-weight:600;">
                💾 Save Settings
            </button>
        </form>
    </div>
    <?php
}

// ── Admin Menu ───────────────────────────────────────────────
add_action('admin_menu', function() {
    add_submenu_page(
        'sgclabs-manager',
        'WhatsApp Share',
        '💬 WhatsApp Share',
        'manage_options',
        'sgclabs-wa-share',
        'sgclabs_wa_share_admin_page'
    );
}, 24);
