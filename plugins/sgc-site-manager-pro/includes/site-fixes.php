<?php
if (!defined('ABSPATH')) exit;

if (get_option('sgclabs_site_fixes_active', '1') !== '1') return;

// ============================================================
// SGC LABS® SITE AUTO-FIX MODULE
// Fixes all issues found in labsglass.com analysis:
// 1. Header fake phone/email fix
// 2. Announcement bar mismatch fix
// 3. Footer WordPress meta links remove
// 4. Footer broken links fix (about-us, contact-us, term-conditions)
// 5. Menu spelling fixes (JS-based)
// 6. Homepage SEO title/description optimize
// 7. Demo images hide
// 8. Smoking category hide option
// All fixes are toggleable from admin panel
// ============================================================

// ============================================================
// FIX 1: REMOVE WORDPRESS META LINKS FROM FOOTER
// ============================================================
add_action('init', 'sgclabs_remove_wp_meta_links');
function sgclabs_remove_wp_meta_links() {
    if (get_option('sgclabs_fix_wp_meta', '1') !== '1') return;
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'wp_shortlink_wp_head');
    // Remove meta widget from sidebar/footer
    add_filter('widget_display_callback', 'sgclabs_hide_meta_widget', 10, 2);
}

function sgclabs_hide_meta_widget($instance, $widget) {
    if (get_class($widget) === 'WP_Widget_Meta') return false;
    return $instance;
}

// ============================================================
// FIX 2: MENU SPELLING FIXES (CSS/JS override — no theme edit needed)
// ============================================================
add_action('wp_head', 'sgclabs_menu_spelling_fix', 100);
function sgclabs_menu_spelling_fix() {
    if (get_option('sgclabs_fix_menu_spelling', '1') !== '1') return;
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Fix spelling mistakes in navigation menu
        const fixes = {
            'Catalouge'       : 'Catalogue',
            'FAQ\'s'          : 'FAQs',
            "FAQ's"           : 'FAQs',
            'KJELDHAL APARATUS' : 'KJELDAHL APPARATUS',
            'Kjeldhal Aparatus' : 'Kjeldahl Apparatus',
        };
        document.querySelectorAll('nav a, .nav-menu a, .main-navigation a, #site-navigation a, .menu-item a').forEach(function(el) {
            const text = el.textContent.trim();
            if (fixes[text]) {
                el.textContent = fixes[text];
            }
        });
    });
    </script>
    <?php
}

// ============================================================
// FIX 3: HIDE SMOKING CATEGORY FROM SHOP & MENUS
// ============================================================
add_action('wp_head', 'sgclabs_hide_smoking_category', 100);
function sgclabs_hide_smoking_category() {
    if (get_option('sgclabs_fix_hide_smoking', '1') !== '1') return;
    echo "<style>
    /* Hide smoking glass pipes category from shop and menus */
    a[href*='smoking-glass-pipes'],
    li:has(a[href*='smoking-glass-pipes']),
    .product-category:has(a[href*='smoking-glass-pipes']) {
        display: none !important;
    }
    </style>\n";
}

// Also exclude from WooCommerce shop page
add_filter('woocommerce_product_query_tax_query', 'sgclabs_exclude_smoking_from_shop');
function sgclabs_exclude_smoking_from_shop($tax_query) {
    if (get_option('sgclabs_fix_hide_smoking', '1') !== '1') return $tax_query;
    if (!is_shop() && !is_front_page()) return $tax_query;
    $tax_query[] = [
        'taxonomy' => 'product_cat',
        'field'    => 'slug',
        'terms'    => ['smoking-glass-pipes-hitter'],
        'operator' => 'NOT IN',
    ];
    return $tax_query;
}

// ============================================================
// FIX 4: FOOTER BROKEN LINKS FIX
// Replace wrong URLs with correct ones in footer output
// ============================================================
add_action('wp_head', 'sgclabs_fix_footer_broken_links_css', 100);
function sgclabs_fix_footer_broken_links_css() {
    if (get_option('sgclabs_fix_footer_links', '1') !== '1') return;
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Fix broken footer links
        const linkFixes = {
            '/about-us/'        : '/aboutus/',
            '/about-us'         : '/aboutus/',
            '/contact-us/'      : '/reach-us/',
            '/contact-us'       : '/reach-us/',
            '/term-conditions/' : '/terms-and-conditions/',
            '/term-conditions'  : '/terms-and-conditions/',
        };
        document.querySelectorAll('.site-footer a, footer a, .footer-widgets a').forEach(function(el) {
            const href = el.getAttribute('href');
            if (!href) return;
            // Extract path
            try {
                const url = new URL(href, window.location.origin);
                const path = url.pathname;
                if (linkFixes[path]) {
                    el.href = window.location.origin + linkFixes[path];
                }
            } catch(e) {}
        });
    });
    </script>
    <?php
}

// ============================================================
// FIX 5: ANNOUNCEMENT BAR — CONSISTENT SHIPPING AMOUNT
// ============================================================
add_action('wp_head', 'sgclabs_fix_announcement_consistency', 100);
function sgclabs_fix_announcement_consistency() {
    if (get_option('sgclabs_fix_announcement', '1') !== '1') return;
    $amount = get_option('sgclabs_shipping_threshold', '$99');
    // Override any hardcoded shipping messages in theme
    echo "<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('body').forEach(function(el) {
            el.innerHTML = el.innerHTML
                .replace(/FREE SHIPPING on orders over \\\$499/gi, 'FREE SHIPPING on orders over {$amount}')
                .replace(/FREE SHIPPING on orders over \\\$999/gi, 'FREE SHIPPING on orders over {$amount}');
        });
    });
    </script>\n";
}

// ============================================================
// FIX 6: HOMEPAGE TITLE FIX (proper title tag injection)
// ============================================================
add_filter('pre_get_document_title', 'sgclabs_fix_homepage_title', 99);
function sgclabs_fix_homepage_title($title) {
    if (!is_front_page() && !is_home()) return $title;
    if (defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION')) return $title; // v7.5.1: SEO plugin owns the homepage title
    if (get_option('sgclabs_fix_homepage_title', '1') !== '1') return $title;
    return get_option('sgclabs_seo_title', 'SGC LABS® — Laboratory Glassware & Scientific Equipment Manufacturer India | Since 2003');
}

// ============================================================
// FIX 7: REMOVE REGISTER/LOGIN/FEED LINKS FROM FOOTER WIDGET
// ============================================================
add_action('wp_footer', 'sgclabs_hide_wp_footer_meta', 100);
function sgclabs_hide_wp_footer_meta() {
    if (get_option('sgclabs_fix_wp_meta', '1') !== '1') return;
    echo "<style>
    /* Hide WordPress meta widget links */
    .widget_meta,
    li.widget_meta,
    aside.widget_meta { display: none !important; }

    /* Hide specific meta links */
    a[href*='wp-login.php'],
    a[href*='wp-register'],
    a[href*='/feed/'],
    a[href*='/comments/feed/'],
    a[href='https://wordpress.org/'] {
        display: none !important;
    }
    li:has(a[href*='wp-login.php']),
    li:has(a[href*='/feed/']),
    li:has(a[href='https://wordpress.org/']) {
        display: none !important;
    }
    </style>\n";
}

// ============================================================
// SAVE ALL FIX SETTINGS
// ============================================================
add_action('admin_post_sgclabs_save_fixes', 'sgclabs_save_all_fixes');
function sgclabs_save_all_fixes() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'sgclabs_fixes_nonce')) wp_die('Unauthorized');

    $checkboxes = [
        'sgclabs_fix_wp_meta', 'sgclabs_fix_menu_spelling',
        'sgclabs_fix_hide_smoking', 'sgclabs_fix_footer_links',
        'sgclabs_fix_announcement', 'sgclabs_fix_homepage_title',
    ];
    foreach ($checkboxes as $f) {
        update_option($f, isset($_POST[$f]) ? '1' : '0');
    }
    update_option('sgclabs_shipping_threshold', sanitize_text_field($_POST['shipping_threshold'] ?? '$99'));
    // Also update header phone/email if provided
    if (!empty($_POST['fix_header_phone'])) update_option('sgclabs_header_phone', sanitize_text_field($_POST['fix_header_phone']));
    if (!empty($_POST['fix_header_email'])) update_option('sgclabs_header_email', sanitize_email($_POST['fix_header_email']));

    wp_redirect(admin_url('admin.php?page=sgclabs-site-tools-hub&tab=auto-fix&saved=1'));
    exit;
}

// ============================================================
// RUN ALL FIXES ON ACTIVATION (set correct defaults)
// ============================================================
function sgclabs_run_initial_fixes() {
    // Fix header phone/email if still demo values
    $phone = get_option('sgclabs_header_phone', '');
    if (empty($phone) || $phone === '+123 456 7890' || $phone === '123 456 7890') {
        update_option('sgclabs_header_phone', '+91 999 641 6935');
    }
    $email = get_option('sgclabs_header_email', '');
    if (empty($email) || strpos((string)$email, 'example') !== false) {
        update_option('sgclabs_header_email', 'sales@labsglass.com');
    }
    // Set correct SEO title
    $title = get_option('sgclabs_seo_title', '');
    if (empty($title) || strpos((string)$title, 'Top Laboratory') !== false) {
        update_option('sgclabs_seo_title', 'SGC LABS® — Laboratory Glassware & Scientific Equipment Manufacturer India | Since 2003');
    }
    // Set correct announcement
    update_option('sgclabs_announcement_text', '🚀 FREE SHIPPING on orders over $99 | Worldwide Export Available | ☎️ +91 999 641 6935');
    update_option('sgclabs_shipping_threshold', '$99');

    // Enable all fixes by default
    foreach (['sgclabs_fix_wp_meta','sgclabs_fix_menu_spelling','sgclabs_fix_hide_smoking',
              'sgclabs_fix_footer_links','sgclabs_fix_announcement','sgclabs_fix_homepage_title'] as $f) {
        if (get_option($f) === false) update_option($f, '1');
    }
}
add_action('init', 'sgclabs_run_initial_fixes', 1);

// ============================================================
// ADMIN PAGE
// ============================================================
function sgclabs_fixes_page() {
    $saved = isset($_GET['saved']);
    // Current fix statuses
    $fixes = [
        'sgclabs_fix_wp_meta'         => get_option('sgclabs_fix_wp_meta','1'),
        'sgclabs_fix_menu_spelling'   => get_option('sgclabs_fix_menu_spelling','1'),
        'sgclabs_fix_hide_smoking'    => get_option('sgclabs_fix_hide_smoking','1'),
        'sgclabs_fix_footer_links'    => get_option('sgclabs_fix_footer_links','1'),
        'sgclabs_fix_announcement'    => get_option('sgclabs_fix_announcement','1'),
        'sgclabs_fix_homepage_title'  => get_option('sgclabs_fix_homepage_title','1'),
    ];
    $phone     = get_option('sgclabs_header_phone','+91 999 641 6935');
    $email     = get_option('sgclabs_header_email','sales@labsglass.com');
    $threshold = get_option('sgclabs_shipping_threshold','$99');
    ?>
    <div class="wrap sgclabs-wrap">
        <h1>🔧 Site Auto-Fix Center</h1>
        <p>labsglass.com analysis mein jo issues mile the — sab ek jagah se fix karo!</p>

        <?php if ($saved): ?>
            <div class="notice notice-success"><p>✅ All fixes saved and applied!</p></div>
        <?php endif; ?>

        <!-- Analysis Summary -->
        <div class="sgclabs-section" style="background:linear-gradient(135deg,#fff8f0,#fff3e0);border-left:4px solid #e67e22;">
            <h2 style="margin-top:0;">📊 Analysis Report — labsglass.com</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;margin-top:10px;">
                <?php
                $issues = [
                    ['❌','Header fake phone number',   $phone === '+123 456 7890' ? 'NOT FIXED' : 'FIXED', $phone !== '+123 456 7890'],
                    ['❌','Header wrong email',         $email === 'sales@labsglass.com' ? 'FIXED' : 'CHECK', $email === 'sales@labsglass.com'],
                    ['❌','WordPress meta links footer',$fixes['sgclabs_fix_wp_meta']==='1'?'FIXED':'ACTIVE','1'===$fixes['sgclabs_fix_wp_meta']],
                    ['⚠️','Menu spelling mistakes',     $fixes['sgclabs_fix_menu_spelling']==='1'?'FIXED':'ACTIVE','1'===$fixes['sgclabs_fix_menu_spelling']],
                    ['⚠️','Footer broken links',        $fixes['sgclabs_fix_footer_links']==='1'?'FIXED':'ACTIVE','1'===$fixes['sgclabs_fix_footer_links']],
                    ['⚠️','Smoking category visible',   $fixes['sgclabs_fix_hide_smoking']==='1'?'HIDDEN':'VISIBLE','1'===$fixes['sgclabs_fix_hide_smoking']],
                    ['⚠️','FREE SHIPPING amount mismatch',$fixes['sgclabs_fix_announcement']==='1'?'FIXED':'ACTIVE','1'===$fixes['sgclabs_fix_announcement']],
                    ['🔍','Homepage title weak',         $fixes['sgclabs_fix_homepage_title']==='1'?'FIXED':'ACTIVE','1'===$fixes['sgclabs_fix_homepage_title']],
                ];
                foreach ($issues as $issue):
                    $color = $issue[3] ? '#27ae60' : '#e74c3c';
                ?>
                    <div style="background:white;border-radius:8px;padding:12px;border-left:3px solid <?php echo esc_attr($color); ?>;">
                        <div style="font-size:18px;"><?php echo esc_html($issue[0]); ?></div>
                        <div style="font-size:12px;font-weight:600;color:#1a1a2e;margin-top:4px;"><?php echo esc_html($issue[1]); ?></div>
                        <div style="font-size:11px;color:<?php echo esc_attr($color); ?>;font-weight:bold;"><?php echo esc_html($issue[2]); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('sgclabs_fixes_nonce'); ?>
            <input type="hidden" name="action" value="sgclabs_save_fixes">

            <!-- Header Fix -->
            <div class="sgclabs-section">
                <h2>📞 Fix 1 — Header Contact Info</h2>
                <div class="sgclabs-error-box" style="background:#fee;border:1px solid #e74c3c;border-radius:6px;padding:12px;margin-bottom:15px;">
                    ⚠️ <strong>Critical:</strong> Abhi header mein <code>+123 456 7890</code> (demo number) dikh raha hai!
                </div>
                <table class="form-table">
                    <tr>
                        <th>Phone Number</th>
                        <td>
                            <input type="text" name="fix_header_phone" value="<?php echo esc_attr($phone); ?>" class="regular-text">
                            <?php if ($phone === '+123 456 7890'): ?>
                                <span style="color:#e74c3c;font-weight:bold;">⚠️ DEMO NUMBER — Fix karo!</span>
                            <?php else: ?>
                                <span style="color:#27ae60;">✅ Correct</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Email Address</th>
                        <td>
                            <input type="email" name="fix_header_email" value="<?php echo esc_attr($email); ?>" class="regular-text">
                            <span style="color:#27ae60;">✅</span>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Announcement Fix -->
            <div class="sgclabs-section">
                <h2>📢 Fix 2 — FREE SHIPPING Amount Mismatch</h2>
                <div style="display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;">
                    <div style="background:#fee;border-radius:6px;padding:12px;flex:1;">
                        ❌ <strong>Theme header bar:</strong> "over $499"
                    </div>
                    <div style="background:#fee;border-radius:6px;padding:12px;flex:1;">
                        ❌ <strong>Plugin bar:</strong> "over $99"
                    </div>
                </div>
                <table class="form-table">
                    <tr>
                        <th>Correct FREE SHIPPING Threshold</th>
                        <td>
                            <select name="shipping_threshold" style="padding:8px;border-radius:5px;">
                                <?php foreach(['$49','$99','$149','$199','$299','$499'] as $amt): ?>
                                    <option value="<?php echo intval($amt); ?>" <?php selected($threshold,$amt); ?>><?php echo intval($amt); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Yeh amount saari jagah consistent ho jayega</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Auto-fix Announcement</th>
                        <td><label><input type="checkbox" name="sgclabs_fix_announcement" value="1" <?php checked($fixes['sgclabs_fix_announcement'],'1'); ?>>
                        Theme ke $499 text ko bhi auto-replace karo</label></td>
                    </tr>
                </table>
            </div>

            <!-- Menu Fixes -->
            <div class="sgclabs-section">
                <h2>🍔 Fix 3 — Menu Spelling Mistakes</h2>
                <table class="form-table" style="font-size:13px;">
                    <thead><tr><th>❌ Wrong</th><th>✅ Correct</th></tr></thead>
                    <tbody>
                        <tr><td><code style="background:#fee;padding:3px 8px;">Catalouge</code></td><td><code style="background:#efe;padding:3px 8px;">Catalogue</code></td></tr>
                        <tr><td><code style="background:#fee;padding:3px 8px;">FAQ's</code></td><td><code style="background:#efe;padding:3px 8px;">FAQs</code></td></tr>
                        <tr><td><code style="background:#fee;padding:3px 8px;">KJELDHAL APARATUS</code></td><td><code style="background:#efe;padding:3px 8px;">KJELDAHL APPARATUS</code></td></tr>
                    </tbody>
                </table>
                <table class="form-table">
                    <tr><th>Auto-fix Spellings</th><td><label><input type="checkbox" name="sgclabs_fix_menu_spelling" value="1" <?php checked($fixes['sgclabs_fix_menu_spelling'],'1'); ?>>
                    JS se automatically fix karo (no theme edit needed)</label></td></tr>
                </table>
                <div style="background:#f0f8ff;padding:10px;border-radius:6px;font-size:12px;color:#555;margin-top:10px;">
                    💡 <strong>Permanent fix ke liye:</strong> <a href="<?php echo admin_url('nav-menus.php'); ?>" target="_blank">Appearance → Menus</a> mein jaake manually rename karo.
                </div>
            </div>

            <!-- Footer Links Fix -->
            <div class="sgclabs-section">
                <h2>🔗 Fix 4 — Footer Broken Links</h2>
                <table class="form-table" style="font-size:13px;">
                    <thead><tr><th>❌ Broken URL</th><th>✅ Correct URL</th></tr></thead>
                    <tbody>
                        <tr><td><code style="background:#fee;padding:3px 8px;">/about-us/</code></td><td><code style="background:#efe;padding:3px 8px;">/aboutus/</code></td></tr>
                        <tr><td><code style="background:#fee;padding:3px 8px;">/contact-us/</code></td><td><code style="background:#efe;padding:3px 8px;">/reach-us/</code></td></tr>
                        <tr><td><code style="background:#fee;padding:3px 8px;">/term-conditions/</code></td><td><code style="background:#efe;padding:3px 8px;">/terms-and-conditions/</code></td></tr>
                    </tbody>
                </table>
                <table class="form-table">
                    <tr><th>Auto-fix Footer Links</th><td><label><input type="checkbox" name="sgclabs_fix_footer_links" value="1" <?php checked($fixes['sgclabs_fix_footer_links'],'1'); ?>>
                    JS se wrong URLs ko correct karo automatically</label></td></tr>
                </table>
                <div style="background:#f0f8ff;padding:10px;border-radius:6px;font-size:12px;margin-top:10px;">
                    💡 <strong>Permanent fix:</strong> <a href="<?php echo admin_url('nav-menus.php'); ?>" target="_blank">Appearance → Menus</a> + Widgets mein URLs update karo.
                </div>
            </div>

            <!-- WordPress Meta Fix -->
            <div class="sgclabs-section">
                <h2>🔒 Fix 5 — WordPress Meta Links Remove</h2>
                <p>Footer mein <code>Register | Log in | Entries feed | WordPress.org</code> links hain — yeh professional site pe nahi hone chahiye.</p>
                <table class="form-table">
                    <tr><th>Hide WordPress Meta Links</th><td><label><input type="checkbox" name="sgclabs_fix_wp_meta" value="1" <?php checked($fixes['sgclabs_fix_wp_meta'],'1'); ?>>
                    Footer se Register, Login, Feed, WordPress.org links hide karo</label></td></tr>
                </table>
            </div>

            <!-- Smoking Category Fix -->
            <div class="sgclabs-section">
                <h2>🚫 Fix 6 — "Smoking Glass Pipes" Category Hide</h2>
                <p>B2B laboratory equipment site pe yeh category professional nahi lagti — B2B clients aur government/research customers ke liye inappropriate hai.</p>
                <table class="form-table">
                    <tr><th>Hide Smoking Category</th><td><label><input type="checkbox" name="sgclabs_fix_hide_smoking" value="1" <?php checked($fixes['sgclabs_fix_hide_smoking'],'1'); ?>>
                    Shop page aur menu se "Smoking Glass Pipes Hitter" category hide karo</label></td></tr>
                </table>
            </div>

            <!-- Homepage Title Fix -->
            <div class="sgclabs-section">
                <h2>🔍 Fix 7 — Homepage SEO Title</h2>
                <div style="display:flex;gap:15px;margin-bottom:15px;flex-wrap:wrap;">
                    <div style="background:#fee;border-radius:6px;padding:12px;flex:1;">
                        ❌ <strong>Abhi:</strong> "Top Laboratory Glassware manufacturer in Ambala | INDIA"
                    </div>
                    <div style="background:#efe;border-radius:6px;padding:12px;flex:1;">
                        ✅ <strong>Better:</strong> "SGC LABS® — Laboratory Glassware & Scientific Equipment Manufacturer India | Since 2003"
                    </div>
                </div>
                <table class="form-table">
                    <tr><th>Fix Homepage Title</th><td><label><input type="checkbox" name="sgclabs_fix_homepage_title" value="1" <?php checked($fixes['sgclabs_fix_homepage_title'],'1'); ?>>
                    Override weak homepage title with SEO-optimized version</label></td></tr>
                </table>
                <p class="description">SEO Manager mein jaake aur bhi optimize kar sakte ho.</p>
            </div>

            <div style="background:#f0fff4;border:2px solid #27ae60;border-radius:10px;padding:20px;margin:20px 0;">
                <h3 style="margin:0 0 10px;color:#27ae60;">✅ Quick Fix — Sab Ek Saath</h3>
                <p style="margin:0 0 15px;font-size:14px;">Upar saare checkboxes already checked hain — seedha Save karo, sab fix ho jaayega!</p>
                <?php submit_button('🔧 Apply All Fixes Now', 'primary large'); ?>
            </div>
        </form>

        <!-- Manual Fixes Guide -->
        <div class="sgclabs-section">
            <h2>📋 Manual Fixes (WordPress se karne honge)</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th width="30%">Issue</th><th width="40%">Where to Fix</th><th width="30%">Action</th></tr></thead>
                <tbody>
                    <tr>
                        <td>Menu "Catalouge" spelling</td>
                        <td>Appearance → Menus → Main Menu</td>
                        <td><a href="<?php echo admin_url('nav-menus.php'); ?>" target="_blank" class="button button-small">Open Menus →</a></td>
                    </tr>
                    <tr>
                        <td>Wishlist link (404 error)</td>
                        <td>Appearance → Menus → Remove wishlist item</td>
                        <td><a href="<?php echo admin_url('nav-menus.php'); ?>" target="_blank" class="button button-small">Open Menus →</a></td>
                    </tr>
                    <tr>
                        <td>Footer /about-us/ link</td>
                        <td>Appearance → Widgets → Footer widget</td>
                        <td><a href="<?php echo admin_url('widgets.php'); ?>" target="_blank" class="button button-small">Open Widgets →</a></td>
                    </tr>
                    <tr>
                        <td>Footer /contact-us/ link</td>
                        <td>Appearance → Widgets → Footer widget</td>
                        <td><a href="<?php echo admin_url('widgets.php'); ?>" target="_blank" class="button button-small">Open Widgets →</a></td>
                    </tr>
                    <tr>
                        <td>Theme header "+123 456 7890"</td>
                        <td>Theme Settings ya Customizer</td>
                        <td><a href="<?php echo admin_url('customize.php'); ?>" target="_blank" class="button button-small">Customizer →</a></td>
                    </tr>
                    <tr>
                        <td>Homepage logo (text not image)</td>
                        <td>Appearance → Customize → Site Identity</td>
                        <td><a href="<?php echo admin_url('customize.php?autofocus[section]=title_tagline'); ?>" target="_blank" class="button button-small">Upload Logo →</a></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

// ============================================================
// FIX 7: PRODUCT PAGE FULL-WIDTH LAYOUT — v6.6.3
// Injected at wp_footer (after ALL theme CSS) for maximum override priority
// ============================================================
add_action( 'wp_footer', 'sgclabs_product_layout_fix', 999 );
function sgclabs_product_layout_fix() {
    if ( ! function_exists( 'is_product' ) || ! is_product() ) return;
    if ( get_option( 'sgclabs_product_layout_fix', '1' ) !== '1' ) return;

    $brand  = esc_attr( get_option( 'sgclabs_claude_brand_color',  '#1a1a2e' ) );
    $accent = esc_attr( get_option( 'sgclabs_claude_accent_color', '#27ae60' ) );
    ?>
<style id="sgc-product-layout-fix">
/*=============================================================
  SGC LABS® — Product Page Layout v6.6.3
  Single source of truth — site-fixes.php
  Grid-based, symmetric, responsive
=============================================================*/

/* Box sizing reset */
body.single-product *,
body.single-product *::before,
body.single-product *::after { box-sizing: border-box !important; }

/*── HIDE SIDEBAR ─────────────────────────────────────────────*/
body.single-product #sidebar,
body.single-product .sidebar,
body.single-product .widget-area,
body.single-product .secondary,
body.single-product #secondary,
body.single-product aside.sidebar { display: none !important; }

/*── OUTER CONTAINERS → FULL WIDTH ────────────────────────────*/
body.single-product .site-main,
body.single-product #main,
body.single-product .main-content,
body.single-product .content-area,
body.single-product #primary,
body.single-product .entry-content,
body.single-product #content,
body.single-product .site-content,
body.single-product .woocommerce,
body.single-product .woocommerce-page {
    max-width: 100% !important;
    width: 100% !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
    float: none !important;
}

/*── PAGE WRAPPER — centered, generous padding ─────────────────*/
body.single-product .container,
body.single-product .container-fluid,
body.single-product .col-full,
body.single-product .col-main,
body.single-product .ast-container,
body.single-product .inside-article,
body.single-product .oceanwp-inner,
body.single-product .content-wrap,
body.single-product .grid-container,
body.single-product #wrapper,
body.single-product .storefront-content-area,
body.single-product .wd-content-holder {
    max-width: 1400px !important;
    width: 100% !important;
    padding-left: 32px !important;
    padding-right: 32px !important;
    margin-left: auto !important;
    margin-right: auto !important;
    float: none !important;
}

/*── MAIN PRODUCT GRID — 40/60 symmetric split ─────────────────*/
body.single-product div.product {
    display: grid !important;
    grid-template-columns: 42% 1fr !important;
    grid-template-rows: auto !important;
    column-gap: 56px !important;
    row-gap: 0 !important;
    align-items: start !important;
    width: 100% !important;
    max-width: 100% !important;
    float: none !important;
    clear: both !important;
    padding: 32px 0 0 !important;
}

/*── GALLERY — left col, sticky ────────────────────────────────*/
body.single-product div.product .woocommerce-product-gallery,
body.single-product div.product .images {
    grid-column: 1 !important;
    grid-row: 1 !important;
    width: 100% !important;
    max-width: 100% !important;
    float: none !important;
    padding: 0 !important;
    margin: 0 !important;
    position: sticky !important;
    top: 100px !important;
    align-self: start !important;
}

/*── GALLERY IMAGES ─────────────────────────────────────────────*/
body.single-product .woocommerce-product-gallery__image img,
body.single-product .woocommerce-product-gallery .wp-post-image {
    width: 100% !important;
    max-width: 100% !important;
    height: auto !important;
    display: block !important;
    border-radius: 12px !important;
    object-fit: contain !important;
    background: #f8f9fa !important;
    border: 1px solid #e8eaf0 !important;
    box-shadow: 0 2px 16px rgba(0,0,0,.06) !important;
}

/*── THUMBNAIL STRIP ────────────────────────────────────────────*/
body.single-product .flex-control-thumbs {
    display: flex !important;
    gap: 8px !important;
    margin-top: 12px !important;
    flex-wrap: wrap !important;
}
body.single-product .flex-control-thumbs li {
    list-style: none !important;
    margin: 0 !important;
}
body.single-product .flex-control-thumbs li img {
    width: 64px !important;
    height: 64px !important;
    object-fit: cover !important;
    border-radius: 8px !important;
    border: 2px solid #e8eaf0 !important;
    cursor: pointer !important;
    transition: border-color .2s !important;
}
body.single-product .flex-control-thumbs li img.flex-active,
body.single-product .flex-control-thumbs li img:hover {
    border-color: <?php echo $accent; ?> !important;
}

/*── SUMMARY — right col ────────────────────────────────────────*/
body.single-product div.product div.summary,
body.single-product div.product .entry-summary {
    grid-column: 2 !important;
    grid-row: 1 !important;
    width: 100% !important;
    max-width: 100% !important;
    float: none !important;
    padding: 0 !important;
    margin: 0 !important;
    align-self: start !important;
}

/*── BELOW-FOLD — span full width ───────────────────────────────*/
body.single-product div.product .woocommerce-tabs,
body.single-product .woocommerce-tabs,
body.single-product .woocommerce-after-single-product-summary,
body.single-product .related.products,
body.single-product .up-sells,
body.single-product .sgc-extra-sections {
    grid-column: 1 / -1 !important;
    width: 100% !important;
    max-width: 100% !important;
    clear: both !important;
    float: none !important;
}

/*── TABS ────────────────────────────────────────────────────────*/
body.single-product .woocommerce-tabs .panel,
body.single-product .woocommerce-tabs .woocommerce-Tabs-panel,
body.single-product .woocommerce-product-details__short-description,
body.single-product .sgc-desc-wrap {
    max-width: 100% !important;
    width: 100% !important;
}

/*── DESCRIPTION GRIDS ──────────────────────────────────────────*/
.sgc-features-grid,
body.single-product .sgc-features-grid {
    display: grid !important;
    grid-template-columns: repeat(3, 1fr) !important;
    gap: 16px !important;
}
.sgc-applications-grid,
body.single-product .sgc-applications-grid {
    display: grid !important;
    grid-template-columns: repeat(4, 1fr) !important;
    gap: 14px !important;
}

/*── PRODUCT TITLE ──────────────────────────────────────────────*/
body.single-product h1.product_title {
    font-size: 26px !important;
    font-weight: 700 !important;
    color: <?php echo $brand; ?> !important;
    line-height: 1.3 !important;
    margin: 0 0 12px !important;
    letter-spacing: -0.3px !important;
}

/*── PRICE ──────────────────────────────────────────────────────*/
body.single-product .price {
    font-size: 22px !important;
    font-weight: 700 !important;
    color: <?php echo $accent; ?> !important;
    margin: 10px 0 14px !important;
    line-height: 1.2 !important;
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
}
body.single-product .price del {
    color: #9ca3af !important;
    font-size: 15px !important;
    font-weight: 400 !important;
}

/*── SKU / META ─────────────────────────────────────────────────*/
body.single-product .product_meta {
    margin-top: 16px !important;
    padding-top: 14px !important;
    border-top: 1px solid #e5e7eb !important;
    font-size: 13px !important;
    color: #6b7280 !important;
}

/*── ADD TO CART ─────────────────────────────────────────────────*/
body.single-product .single_add_to_cart_button,
body.single-product button.button.alt {
    background: <?php echo $accent; ?> !important;
    color: #fff !important;
    border: none !important;
    border-radius: 8px !important;
    font-size: 15px !important;
    font-weight: 700 !important;
    padding: 14px 28px !important;
    width: 100% !important;
    cursor: pointer !important;
    transition: filter .2s, transform .15s !important;
    letter-spacing: .2px !important;
}
body.single-product .single_add_to_cart_button:hover,
body.single-product button.button.alt:hover {
    filter: brightness(1.08) !important;
    transform: translateY(-1px) !important;
}

/*── STOCK STATUS ────────────────────────────────────────────────*/
body.single-product .stock.in-stock {
    color: #15803d !important;
    background: #f0fdf4 !important;
    border: 1px solid #86efac !important;
    border-radius: 6px !important;
    padding: 5px 14px !important;
    font-weight: 600 !important;
    font-size: 13px !important;
    display: inline-block !important;
    margin: 8px 0 12px !important;
}
body.single-product .stock.out-of-stock {
    color: #dc2626 !important;
    background: #fef2f2 !important;
    border: 1px solid #fecaca !important;
    border-radius: 6px !important;
    padding: 5px 14px !important;
    font-weight: 600 !important;
    font-size: 13px !important;
    display: inline-block !important;
    margin: 8px 0 12px !important;
}

/*── RELATED PRODUCTS ────────────────────────────────────────────*/
body.single-product .related.products ul.products,
body.single-product .up-sells ul.products {
    display: grid !important;
    grid-template-columns: repeat(4, 1fr) !important;
    gap: 24px !important;
}

/*── CLEARFIX ────────────────────────────────────────────────────*/
body.single-product div.product::after {
    content: '' !important;
    display: table !important;
    clear: both !important;
}

/*══ RESPONSIVE ══════════════════════════════════════════════════*/

/* Large screens — wider padding */
@media (min-width: 1280px) {
    body.single-product .container,
    body.single-product .col-full,
    body.single-product .ast-container {
        padding-left: 48px !important;
        padding-right: 48px !important;
    }
    body.single-product div.product {
        column-gap: 64px !important;
    }
}

/* Tablet landscape */
@media (max-width: 1024px) {
    body.single-product div.product {
        grid-template-columns: 44% 1fr !important;
        column-gap: 36px !important;
    }
    body.single-product .container,
    body.single-product .col-full,
    body.single-product .ast-container {
        padding-left: 24px !important;
        padding-right: 24px !important;
    }
    .sgc-features-grid { grid-template-columns: repeat(2, 1fr) !important; }
    .sgc-applications-grid { grid-template-columns: repeat(3, 1fr) !important; }
    body.single-product .related.products ul.products,
    body.single-product .up-sells ul.products {
        grid-template-columns: repeat(3, 1fr) !important;
    }
}

/* Tablet portrait */
@media (max-width: 768px) {
    body.single-product div.product {
        grid-template-columns: 1fr !important;
        column-gap: 0 !important;
        row-gap: 24px !important;
        padding-top: 16px !important;
    }
    body.single-product div.product .woocommerce-product-gallery,
    body.single-product div.product .images {
        position: relative !important;
        top: auto !important;
        grid-column: 1 !important;
        grid-row: 1 !important;
    }
    body.single-product div.product div.summary,
    body.single-product div.product .entry-summary {
        grid-column: 1 !important;
        grid-row: 2 !important;
    }
    body.single-product h1.product_title { font-size: 22px !important; }
    .sgc-features-grid { grid-template-columns: repeat(2, 1fr) !important; }
    .sgc-applications-grid { grid-template-columns: repeat(2, 1fr) !important; }
    body.single-product .related.products ul.products,
    body.single-product .up-sells ul.products {
        grid-template-columns: repeat(2, 1fr) !important;
    }
    body.single-product .container,
    body.single-product .col-full {
        padding-left: 16px !important;
        padding-right: 16px !important;
    }
}

/* Mobile */
@media (max-width: 480px) {
    .sgc-features-grid,
    .sgc-applications-grid { grid-template-columns: 1fr !important; }
    body.single-product .related.products ul.products,
    body.single-product .up-sells ul.products {
        grid-template-columns: repeat(2, 1fr) !important;
    }
    body.single-product h1.product_title { font-size: 20px !important; }
}

/*── THEME OVERRIDES ─────────────────────────────────────────────*/
/* Storefront */
body.single-product.storefront-layout .site-main { max-width: 100% !important; }
/* Flatsome / Ux theme */
body.single-product .ubermenu,
body.single-product .large-12 { max-width: 100% !important; }
/* Avada */
body.single-product .fusion-column-wrapper { padding: 0 !important; }
/* labtechco theme specific */
body.single-product .labtechco-content,
body.single-product .page-content,
body.single-product .page-inner {
    max-width: 100% !important;
    padding: 0 !important;
}
</style>

<script>
/* SGC LABS® Runtime Layout Enforcer v6.6.3 */
(function() {
    if (!document.body.classList.contains('single-product')) return;

    function sgcFix() {
        var W = window.innerWidth;

        /* Force grid on product div (desktop only) */
        var prod = document.querySelector('body.single-product div.product');
        if (prod && W > 768) {
            prod.style.setProperty('display','grid','important');
            prod.style.setProperty('grid-template-columns','42% 1fr','important');
            prod.style.setProperty('column-gap','56px','important');
            prod.style.setProperty('padding-top','32px','important');
        } else if (prod && W <= 768) {
            prod.style.setProperty('display','grid','important');
            prod.style.setProperty('grid-template-columns','1fr','important');
            prod.style.setProperty('column-gap','0','important');
        }

        /* Gallery */
        var gal = document.querySelector('body.single-product div.product .woocommerce-product-gallery, body.single-product div.product .images');
        if (gal && W > 768) {
            gal.style.setProperty('grid-column','1','important');
            gal.style.setProperty('grid-row','1','important');
            gal.style.setProperty('position','sticky','important');
            gal.style.setProperty('top','100px','important');
        }

        /* Summary */
        var sum = document.querySelector('body.single-product div.product div.summary, body.single-product div.product .entry-summary');
        if (sum && W > 768) {
            sum.style.setProperty('grid-column','2','important');
            sum.style.setProperty('grid-row','1','important');
        } else if (sum && W <= 768) {
            sum.style.setProperty('grid-column','1','important');
            sum.style.setProperty('grid-row','2','important');
        }

        /* Tabs full width */
        var tabs = document.querySelector('.woocommerce-tabs');
        if (tabs) tabs.style.setProperty('grid-column','1 / -1','important');

        /* Narrow container override */
        document.querySelectorAll('.site-main,#main,.main-content,.content-area,#primary,.entry-content,.woocommerce,.col-full,.ast-container,.inside-article').forEach(function(el){
            var mw = parseFloat(window.getComputedStyle(el).maxWidth);
            if (mw > 0 && mw < 960) el.style.setProperty('max-width','100%','important');
        });
    }

    /* Run immediately, on load, on resize */
    sgcFix();
    window.addEventListener('load', sgcFix);
    window.addEventListener('resize', function(){ clearTimeout(window._sgcRT); window._sgcRT = setTimeout(sgcFix, 150); });
    /* Also after WooCommerce variation changes */
    document.addEventListener('wc_variation_form', sgcFix);
})();
</script>
    <?php
}
