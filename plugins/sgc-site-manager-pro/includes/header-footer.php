<?php
if (!defined('ABSPATH')) exit;

// Save settings
add_action('admin_post_sgclabs_save_header', 'sgclabs_save_header_settings');
function sgclabs_save_header_settings() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'sgclabs_header_nonce')) wp_die('Unauthorized');
    update_option('sgclabs_header_phone', sanitize_text_field($_POST['header_phone']));
    update_option('sgclabs_header_email', sanitize_email($_POST['header_email']));
    update_option('sgclabs_header_whatsapp', sanitize_text_field($_POST['header_whatsapp']));
    update_option('sgclabs_header_bg', sanitize_hex_color($_POST['header_bg']));
    update_option('sgclabs_header_text_color', sanitize_hex_color($_POST['header_text_color']));
    update_option('sgclabs_header_active', isset($_POST['header_active']) ? '1' : '0');
    update_option('sgclabs_footer_remove_demo', isset($_POST['footer_remove_demo']) ? '1' : '0');
    update_option('sgclabs_footer_tagline', sanitize_text_field($_POST['footer_tagline']));
    // Nav fix settings
    update_option('sgclabs_nav_fix_active',   isset($_POST['nav_fix_active']) ? '1' : '0');
    update_option('sgclabs_nav_font_size',     intval($_POST['nav_font_size'] ?? 14));
    update_option('sgclabs_nav_menu_bg',       sanitize_hex_color($_POST['nav_menu_bg'] ?? '#2980b9'));
    update_option('sgclabs_nav_menu_color',    sanitize_hex_color($_POST['nav_menu_color'] ?? '#ffffff'));
    update_option('sgclabs_nav_drop_bg',       sanitize_hex_color($_POST['nav_drop_bg'] ?? '#1a1a2e'));
    update_option('sgclabs_nav_drop_color',    sanitize_hex_color($_POST['nav_drop_color'] ?? '#ffffff'));
    wp_redirect(admin_url('admin.php?page=sgclabs-site-tools-hub&tab=header&saved=1'));
    exit;
}

// Topbar removed — disabled by SGC LABS Kumar

// Fix Navigation Menu — Hover Dropdown + Font Size
add_action('wp_head', 'sgclabs_fix_nav_menu', 99);
function sgclabs_fix_nav_menu() {
    if (get_option('sgclabs_nav_fix_active', '1') !== '1') return;
    $font_size  = get_option('sgclabs_nav_font_size', '14');
    $menu_bg    = get_option('sgclabs_nav_menu_bg', '#2980b9');
    $menu_color = get_option('sgclabs_nav_menu_color', '#ffffff');
    $drop_bg    = get_option('sgclabs_nav_drop_bg', '#1a1a2e');
    $drop_color = get_option('sgclabs_nav_drop_color', '#ffffff');
    echo "
    <style>
    /* ===== SGC LABS® Navigation Fix ===== */

    /* Menu font size fix */
    .main-navigation a,
    .nav-menu a,
    .primary-menu a,
    nav.site-navigation a,
    #site-navigation a,
    .menu-item a,
    header nav a,
    .header-menu a {
        font-size: {$font_size}px !important;
    }

    /*
     * BUG FIX v4.3.2:
     * Problem: Hover dropdown page load pe kaam nahi karta tha.
     * Root Cause: Theme ka sticky header scroll ke baad naya stacking context
     * banata hai (transform/will-change ya position:fixed se), jisse nav ka
     * z-index reset ho jaata tha. Top bar ka z-index:9999 bhi nav ke upar
     * overlap kar raha tha aur pointer-events block kar raha tha.
     * Fix: Nav container ko explicit stacking context diya, dropdown ko
     * translate3d se GPU layer pe daala, aur pointer-events ensure kiye.
     */

    /* FIX: Nav container ko apna stacking context do — theme sticky header se independent */
    .main-navigation,
    .nav-menu,
    .primary-menu,
    #site-navigation,
    nav.site-navigation {
        position: relative !important;
        z-index: 9999 !important;
        isolation: isolate !important;
    }

    /* Dropdown hover fix */
    .main-navigation ul ul,
    .nav-menu ul ul,
    .primary-menu ul ul,
    #site-navigation ul ul,
    nav.site-navigation ul ul {
        display: none !important;
        position: absolute !important;
        top: 100% !important;
        left: 0 !important;
        z-index: 9999 !important;
        min-width: 200px !important;
        background: {$drop_bg} !important;
        box-shadow: 0 4px 15px rgba(0,0,0,0.25) !important;
        border-radius: 0 0 6px 6px !important;
        padding: 5px 0 !important;
        /* FIX: translate3d se apni GPU compositing layer milti hai —
           theme ke stacking context se bahar nikal jaata hai */
        transform: translate3d(0, 0, 0) !important;
        /* FIX: pointer-events ensure karo — kuch themes inhe none karte hain */
        pointer-events: auto !important;
    }

    /* Show dropdown on hover */
    .main-navigation ul li.menu-item-has-children:hover > ul,
    .nav-menu li.menu-item-has-children:hover > ul,
    .primary-menu li.menu-item-has-children:hover > ul,
    #site-navigation ul li.menu-item-has-children:hover > ul,
    nav.site-navigation ul li.menu-item-has-children:hover > ul {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        pointer-events: auto !important;
    }

    /* Dropdown item styling */
    .main-navigation ul ul li a,
    .nav-menu ul ul li a,
    #site-navigation ul ul li a,
    nav.site-navigation ul ul li a {
        display: block !important;
        padding: 10px 18px !important;
        color: {$drop_color} !important;
        background: {$drop_bg} !important;
        font-size: calc({$font_size}px - 1px) !important;
        white-space: nowrap !important;
        border-bottom: 1px solid rgba(255,255,255,0.08) !important;
    }

    .main-navigation ul ul li a:hover,
    .nav-menu ul ul li a:hover,
    #site-navigation ul ul li a:hover {
        background: rgba(255,255,255,0.12) !important;
        padding-left: 24px !important;
    }

    /* FIX: Parent items ko explicitly relative karo */
    .menu-item-has-children,
    .main-navigation li,
    #site-navigation ul li,
    nav.site-navigation ul li {
        position: relative !important;
        /* FIX: overflow visible ensure karo — kuch themes overflow:hidden lagate hain
           jo dropdown ko clip kar deta hai, isliye page top pe kaam nahi karta tha */
        overflow: visible !important;
    }

    /* Dropdown arrow indicator */
    .menu-item-has-children > a::after {
        content: ' ▾' !important;
        font-size: 10px !important;
        opacity: 0.8 !important;
    }

    /* FIX: Mobile — hover se kaam nahi karta touch devices pe, JS fallback below */
    @media (max-width: 768px) {
        .main-navigation ul ul,
        #site-navigation ul ul,
        nav.site-navigation ul ul {
            position: static !important;
            width: 100% !important;
            box-shadow: none !important;
            transform: none !important;
        }
        .main-navigation li.menu-item-has-children:hover > ul,
        #site-navigation li.menu-item-has-children:hover > ul {
            display: block !important;
        }
    }
    </style>
    ";
}

function sgclabs_fix_footer_css() {
    if (get_option('sgclabs_footer_remove_demo', '1') !== '1') return;
    echo "<style>
    img[src*='labtechco-demo'], img[src*='labtechco.themestek'], img[src*='pbminfotech'] { display:none !important; }
    .site-footer .widget-area img[src=''] { display:none !important; }
    </style>";
}

// Admin page
function sgclabs_header_footer_page() {
    $saved = isset($_GET['saved']);
    $phone = get_option('sgclabs_header_phone', '+91 999 641 6935');
    $email = get_option('sgclabs_header_email', 'sales@labsglass.com');
    $whatsapp = get_option('sgclabs_header_whatsapp', '919996416935');
    $bg = get_option('sgclabs_header_bg', '#1a1a2e');
    $color = get_option('sgclabs_header_text_color', '#ffffff');
    $active = get_option('sgclabs_header_active', '1');
    $remove_demo = get_option('sgclabs_footer_remove_demo', '1');
    $tagline = get_option('sgclabs_footer_tagline', 'SGC LABS® — Laboratory Glassware & Scientific Equipment Manufacturer since 2003.');
    ?>
    <div class="wrap sgclabs-wrap">
        <h1>📞 Header & Footer Manager</h1>
        <?php if ($saved): ?><div class="notice notice-success"><p>✅ Settings saved successfully!</p></div><?php endif; ?>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('sgclabs_header_nonce'); ?>
            <input type="hidden" name="action" value="sgclabs_save_header">
            <div class="sgclabs-section">
                <h2>🔝 Header Top Bar</h2>
                <table class="form-table">
                    <tr><th>Top Bar Active</th><td><label><input type="checkbox" name="header_active" value="1" <?php checked($active, '1'); ?>> Enable top bar on website</label></td></tr>
                    <tr><th>Phone Number</th><td><input type="text" name="header_phone" value="<?php echo esc_attr($phone); ?>" class="regular-text" placeholder="+91 999 641 6935"></td></tr>
                    <tr><th>Email Address</th><td><input type="email" name="header_email" value="<?php echo esc_attr($email); ?>" class="regular-text" placeholder="sales@labsglass.com"></td></tr>
                    <tr><th>WhatsApp Number</th><td><input type="text" name="header_whatsapp" value="<?php echo esc_attr($whatsapp); ?>" class="regular-text" placeholder="919996416935 (country code included)"></td></tr>
                    <tr><th>Background Color</th><td><input type="color" name="header_bg" value="<?php echo esc_attr($bg); ?>"></td></tr>
                    <tr><th>Text Color</th><td><input type="color" name="header_text_color" value="<?php echo esc_attr($color); ?>"></td></tr>
                </table>
            </div>
            <div class="sgclabs-section">
                <h2>🦶 Footer Cleanup</h2>
                <table class="form-table">
                    <tr><th>Remove Demo Images</th><td><label><input type="checkbox" name="footer_remove_demo" value="1" <?php checked($remove_demo, '1'); ?>> Auto-hide broken demo images (labtechco, pbminfotech)</label></td></tr>
                    <tr><th>Footer Tagline Text</th><td><input type="text" name="footer_tagline" value="<?php echo esc_attr($tagline); ?>" class="large-text"></td></tr>
                </table>
            </div>

            <!-- Navigation Fix Section -->
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
                                <input type="checkbox" name="nav_fix_active" value="1" <?php checked(get_option('sgclabs_nav_fix_active','1'),'1'); ?>>
                                <strong>Fix hover dropdowns + font size</strong>
                            </label>
                            <p class="description">Ye enable karne se hover menu aur font size dono theek ho jayenge</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Menu Font Size</th>
                        <td>
                            <input type="number" name="nav_font_size" value="<?php echo intval(get_option('sgclabs_nav_font_size','14')); ?>" min="10" max="22" style="width:70px;"> px
                            <p class="description">Recommended: 13-15px. Aapke menu mein bahut bada lag raha tha — 14px set karo</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Dropdown Background</th>
                        <td>
                            <input type="color" name="nav_drop_bg" value="<?php echo esc_attr(get_option('sgclabs_nav_drop_bg','#1a1a2e')); ?>">
                            <span style="margin-left:10px;font-size:12px;color:#666;">Dropdown menu ka background color</span>
                        </td>
                    </tr>
                    <tr>
                        <th>Dropdown Text Color</th>
                        <td>
                            <input type="color" name="nav_drop_color" value="<?php echo esc_attr(get_option('sgclabs_nav_drop_color','#ffffff')); ?>">
                        </td>
                    </tr>
                </table>

                <!-- Live Preview -->
                <div style="margin-top:15px;">
                    <h4>👁️ Dropdown Preview</h4>
                    <div style="display:inline-block;background:<?php echo esc_attr(get_option('sgclabs_nav_drop_bg','#1a1a2e')); ?>;border-radius:6px;overflow:hidden;min-width:200px;">
                        <?php foreach(['Products','Beakers','Flasks','Laboratory Glass','Burettes'] as $item): ?>
                            <div style="padding:10px 18px;color:<?php echo esc_attr(get_option('sgclabs_nav_drop_color','#ffffff')); ?>;font-size:<?php echo intval(get_option('sgclabs_nav_font_size','14')); ?>px;border-bottom:1px solid rgba(255,255,255,0.1);"><!-- FIX: intval() added -->                                <?php echo esc_html($item); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php submit_button('💾 Save All Settings'); ?>
        </form>
        <div class="sgclabs-preview-box">
            <h3>👁️ Live Preview (Header Bar)</h3>
            <div id="header-preview" style="background:<?php echo esc_attr($bg); ?>;color:<?php echo esc_attr($color); ?>;padding:10px 20px;border-radius:5px;font-size:13px;">
                📞 <?php echo esc_html($phone); ?> &nbsp;|&nbsp; ✉️ <?php echo esc_html($email); ?> &nbsp;|&nbsp; 💬 WhatsApp
            </div>
        </div>
    </div>
    <?php
}
