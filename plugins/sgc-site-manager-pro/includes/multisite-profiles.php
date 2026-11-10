<?php
if (!defined('ABSPATH')) exit;

// ============================================================
// SGC LABS® MULTI-SITE PROFILE MANAGER
// 4 Domain Profiles:
// 1. labsglass.com
// 2. sgclabs.in
// 3. sgclabglass.com
// 4. astmglass.com
// Each domain has its own: Company info, SEO, Currency, WhatsApp, Header, Announcement
// Auto-detects current domain and loads correct profile
// ============================================================

// --- DEFAULT PROFILES FOR ALL 4 DOMAINS ---
function sgclabs_get_default_profiles() {
    return [
        'labsglass.com' => [
            'domain'            => 'labsglass.com',
            'label'             => '🧪 LabsGlass',
            'color'             => '#1a1a2e',
            // Company Info
            'company_name'      => 'SGC LABS®',
            'tagline'           => 'Laboratory Glassware Manufacturer Since 2003',
            'address'           => '4/12/2 Gandhi Nagar, Ambala Cantt, Haryana 133001, India',
            'phone'             => '+91 999 641 6935',
            'email'             => 'sales@labsglass.com',
            'whatsapp'          => '919996416935',
            'currency'          => 'USD',
            // SEO
            'seo_title'         => 'Laboratory Glassware & Scientific Equipment Manufacturer | SGC LABS® India',
            'seo_description'   => 'SGC LABS® — Premium laboratory glassware manufacturer in Ambala, India since 2003. Borosilicate glassware, scientific equipment. Export worldwide.',
            'seo_keywords'      => 'laboratory glassware, borosilicate glassware, scientific equipment, lab instruments, SGC LABS, Ambala India',
            // Announcement
            'announcement'      => '🚀 FREE SHIPPING on orders over $100 | Worldwide Export Available | Call: +91 999 641 6935',
            'announcement_bg'   => '#1a1a2e',
            'announcement_color'=> '#ffffff',
            // Schema
            'schema_type'       => 'Organization',
            'founded'           => '2003',
            'business_type'     => 'Laboratory Glassware Manufacturer',
        ],

        'sgclabs.in' => [
            'domain'            => 'sgclabs.in',
            'label'             => '🔬 SGC Labs India',
            'color'             => '#c0392b',
            // Company Info
            'company_name'      => 'SGC LABS®',
            'tagline'           => 'Scientific Glass Components — Made in India',
            'address'           => '4/12/2 Gandhi Nagar, Ambala Cantt, Haryana 133001, India',
            'phone'             => '+91 999 641 6935',
            'email'             => 'info@sgclabs.in',
            'whatsapp'          => '919996416935',
            'currency'          => 'INR',
            // SEO
            'seo_title'         => 'SGC Labs® India — Scientific Glass Components Manufacturer Ambala',
            'seo_description'   => 'SGC Labs India — Scientific glass components, borosilicate glassware manufacturer in Ambala Haryana. ISO quality, bulk orders, PAN India delivery.',
            'seo_keywords'      => 'SGC labs India, scientific glass components, borosilicate manufacturer Ambala, laboratory glass India, scientific equipment India',
            // Announcement
            'announcement'      => '🇮🇳 PAN India Delivery | GST Invoice | Bulk Orders Welcome | ☎️ +91 999 641 6935',
            'announcement_bg'   => '#c0392b',
            'announcement_color'=> '#ffffff',
            // Schema
            'schema_type'       => 'Organization',
            'founded'           => '2003',
            'business_type'     => 'Scientific Glass Components Manufacturer',
        ],

        'sgclabglass.com' => [
            'domain'            => 'sgclabglass.com',
            'label'             => '⚗️ SGC Lab Glass',
            'color'             => '#27ae60',
            // Company Info
            'company_name'      => 'SGC Lab Glass',
            'tagline'           => 'Premium Laboratory Glass — Global Supplier',
            'address'           => '4/12/2 Gandhi Nagar, Ambala Cantt, Haryana 133001, India',
            'phone'             => '+91 999 641 6935',
            'email'             => 'sales@sgclabglass.com',
            'whatsapp'          => '919996416935',
            'currency'          => 'USD',
            // SEO
            'seo_title'         => 'SGC Lab Glass — Premium Laboratory Glassware Global Supplier | India',
            'seo_description'   => 'SGC Lab Glass — Premium borosilicate laboratory glassware global supplier from India. Beakers, flasks, burettes, pipettes. OEM available. Worldwide shipping.',
            'seo_keywords'      => 'SGC lab glass, laboratory glassware supplier, borosilicate glass supplier, lab glass India, scientific glassware export',
            // Announcement
            'announcement'      => '🌍 Global Shipping | OEM Available | ISO Certified | Get Quote: sales@sgclabglass.com',
            'announcement_bg'   => '#27ae60',
            'announcement_color'=> '#ffffff',
            // Schema
            'schema_type'       => 'Organization',
            'founded'           => '2003',
            'business_type'     => 'Laboratory Glassware Supplier',
        ],

        'astmglass.com' => [
            'domain'            => 'astmglass.com',
            'label'             => '📐 ASTM Glass',
            'color'             => '#8e44ad',
            // Company Info
            'company_name'      => 'ASTM Glass',
            'tagline'           => 'ASTM Standard Laboratory Glassware — Precision Quality',
            'address'           => '4/12/2 Gandhi Nagar, Ambala Cantt, Haryana 133001, India',
            'phone'             => '+91 999 641 6935',
            'email'             => 'sales@astmglass.com',
            'whatsapp'          => '919996416935',
            'currency'          => 'USD',
            // SEO
            'seo_title'         => 'ASTM Standard Laboratory Glassware Manufacturer | ASTMGlass.com India',
            'seo_description'   => 'ASTM Glass — Manufacturer of ASTM standard laboratory glassware from India. Precision borosilicate glass, certified quality. Export to USA, Europe, Middle East.',
            'seo_keywords'      => 'ASTM glass, ASTM standard glassware, ASTM laboratory glass, borosilicate ASTM, precision lab glass India',
            // Announcement
            'announcement'      => '✅ ASTM Certified Glassware | Export Quality | USA & Europe Approved | Quote: sales@astmglass.com',
            'announcement_bg'   => '#8e44ad',
            'announcement_color'=> '#ffffff',
            // Schema
            'schema_type'       => 'Organization',
            'founded'           => '2003',
            'business_type'     => 'ASTM Standard Glassware Manufacturer',
        ],

        'labglass.in' => [
            'domain'            => 'labglass.in',
            'label'             => '🇮🇳 Lab Glass India',
            'color'             => '#e67e22',
            // Company Info
            'company_name'      => 'SGC LABS® India',
            'tagline'           => 'Laboratory Glassware — India Domestic Store',
            'address'           => '4/12/2 Gandhi Nagar, Ambala Cantt, Haryana 133001, India',
            'phone'             => '+91 999 641 6935',
            'email'             => 'info@labglass.in',
            'whatsapp'          => '919996416935',
            'currency'          => 'INR',
            // SEO
            'seo_title'         => 'SGC LABS® India — Laboratory Glassware Manufacturer | Buy Online',
            'seo_description'   => 'Buy laboratory glassware online from SGC LABS® India. ISO 9001:2015 certified manufacturer. GST invoice, COD, free shipping ₹5,000+. Beakers, flasks, burettes & more.',
            'seo_keywords'      => 'lab glassware India, laboratory equipment online, borosilicate glass India, beaker buy online, flask manufacturer India',
            // Announcement
            'announcement'      => '🇮🇳 India Domestic Store | Free Shipping ₹5,000+ | GST Invoice Available | ISO 9001:2015 Certified',
            'announcement_bg'   => '#e67e22',
            'announcement_color'=> '#ffffff',
            // Schema
            'schema_type'       => 'Organization',
            'founded'           => '2003',
            'business_type'     => 'Laboratory Glassware Manufacturer',
        ],
    ];
}

// --- GET CURRENT DOMAIN ---
function sgclabs_get_current_domain() {
    // Use WordPress home_url for reliable domain detection (works behind proxy/Cloudflare)
    $parsed = wp_parse_url(home_url());
    $host = isset($parsed['host']) ? $parsed['host'] : ($_SERVER['HTTP_HOST'] ?? '');
    $host = preg_replace('/^www\./', '', strtolower($host));
    return $host;
}

// --- GET ACTIVE PROFILE (auto-detect or manual) ---
function sgclabs_get_active_profile() {
    $domain   = sgclabs_get_current_domain();
    $profiles = sgclabs_get_default_profiles();
    $saved    = get_option('sgclabs_site_profiles', []);

    // Auto-detect domain
    if (isset($profiles[$domain])) {
        // Merge with saved customizations
        $profile = $profiles[$domain];
        if (isset($saved[$domain])) {
            $profile = array_merge($profile, $saved[$domain]);
        }
        return $profile;
    }

    // Manual override
    $manual = get_option('sgclabs_active_profile_domain', '');
    if ($manual && isset($profiles[$manual])) {
        $profile = $profiles[$manual];
        if (isset($saved[$manual])) {
            $profile = array_merge($profile, $saved[$manual]);
        }
        return $profile;
    }

    // Default to labsglass.com
    return $profiles['labsglass.com'];
}

// --- AUTO-APPLY PROFILE SETTINGS ---
add_action('init', 'sgclabs_apply_profile_settings', 5);
function sgclabs_apply_profile_settings() {
    if (!get_option('sgclabs_multisite_active', '1')) return;
    $profile = sgclabs_get_active_profile();
    if (!$profile) return;

    // Override header settings
    update_option('sgclabs_header_phone', $profile['phone']);
    update_option('sgclabs_header_email', $profile['email']);
    update_option('sgclabs_wa_number',    $profile['whatsapp']);

    // Override announcement
    update_option('sgclabs_announcement_text',  $profile['announcement']);
    update_option('sgclabs_announcement_bg',    $profile['announcement_bg']);
    update_option('sgclabs_announcement_color', $profile['announcement_color'] ?? '#ffffff');

    // Override WooCommerce currency
    update_option('sgclabs_wc_currency_override', $profile['currency']);
    update_option('sgclabs_currency_default',     $profile['currency']);

    // Auto-disable Market Price Comparison on .in (Indian domestic) domains
    // Indian customers don't need Amazon.com / eBay.com / VWR.com comparison
    $domain = $profile['domain'] ?? '';
    if ( strpos( $domain, '.in', strlen($domain) - 3 ) !== false ) {
        update_option( 'sgclabs_market_active', '0' );
    } else {
        // Ensure it's ON for .com export sites (unless admin manually disabled it)
        if ( get_option( 'sgclabs_market_active' ) === false ) {
            update_option( 'sgclabs_market_active', '1' );
        }
    }
}

// ── WooCommerce currency override from profile ──────────────────────
// This actually switches WooCommerce's active currency based on the profile.
// Without this, WooCommerce ignores the profile currency setting.
add_filter( 'woocommerce_currency', 'sgclabs_profile_wc_currency', 99 );
function sgclabs_profile_wc_currency( $currency ) {
    $override = get_option( 'sgclabs_wc_currency_override', '' );
    if ( $override && in_array( $override, [ 'INR', 'USD', 'EUR', 'GBP', 'AED', 'SAR', 'JPY', 'AUD', 'CAD' ], true ) ) {
        return $override;
    }
    return $currency;
}

// --- INJECT PROFILE SEO ON HOMEPAGE ---
add_action('wp_head', 'sgclabs_inject_profile_seo', 2);
function sgclabs_inject_profile_seo(){
    if ( defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION') || defined('AIOSEO_VERSION') ) return;
    if (!is_front_page() && !is_home()) return;
    if (!get_option('sgclabs_multisite_active', '1')) return;

    $profile = sgclabs_get_active_profile();
    if (!$profile) return;

    echo "<!-- SGC LABS® Profile: " . esc_html($profile['domain']) . " -->\n";
    // FIX v4.3.1: Disable ALL SEO meta output when any SEO plugin is active
    // RankMath / Yoast handle: title, meta desc, OG tags, schema — we skip all
    $has_seo_plugin = defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION') || defined('AIOSEO_VERSION') || defined('SEOPRESS_VERSION');
    if (!$has_seo_plugin) {
        echo "<title>" . esc_html($profile['seo_title']) . "</title>\n";
        echo "<meta name='description' content='" . esc_attr($profile['seo_description']) . "'>\n";
        echo "<meta name='keywords' content='" . esc_attr($profile['seo_keywords']) . "'>\n";
        // OG Tags — only when no SEO plugin (RankMath Pro outputs its own OG tags)
        echo "<meta property='og:title' content='" . esc_attr($profile['seo_title']) . "'>\n";
        echo "<meta property='og:description' content='" . esc_attr($profile['seo_description']) . "'>\n";
        echo "<meta property='og:url' content='" . esc_url(home_url('/')) . "'>\n";
        echo "<meta property='og:type' content='website'>\n";
        echo "<meta property='og:site_name' content='" . esc_attr($profile['company_name']) . "'>\n";
    }

    // Organization Schema
    $schema = [
        "@context"    => "https://schema.org",
        "@type"       => "Organization",
        "name"        => $profile['company_name'],
        "description" => $profile['tagline'],
        "url"         => home_url('/'),
        "foundingDate"=> $profile['founded'],
        "address"     => [
            "@type"           => "PostalAddress",
            "streetAddress"   => "4/12/2 Gandhi Nagar",
            "addressLocality" => "Ambala Cantt",
            "addressRegion"   => "Haryana",
            "postalCode"      => "133001",
            "addressCountry"  => "IN"
        ],
        "contactPoint" => [
            "@type"       => "ContactPoint",
            "telephone"   => $profile['phone'],
            "email"       => $profile['email'],
            "contactType" => "sales",
            "areaServed"  => "Worldwide",
        ],
        "sameAs" => [
            "https://labsglass.com",
            "https://sgclabs.in",
            "https://sgclabglass.com",
            "https://astmglass.com",
        ]
    ];
    echo "<script type='application/ld+json'>" . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";
}

// --- SAVE PROFILE CUSTOMIZATIONS ---
add_action('admin_post_sgclabs_save_profile', 'sgclabs_save_profile');
function sgclabs_save_profile() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'sgclabs_profile_nonce')) wp_die('Unauthorized');

    $domain  = sanitize_text_field($_POST['profile_domain']);
    $saved   = get_option('sgclabs_site_profiles', []);

    $fields = ['company_name','tagline','address','phone','email','whatsapp','currency',
               'seo_title','seo_description','seo_keywords',
               'announcement','announcement_bg','announcement_color',
               'business_type','founded'];

    foreach ($fields as $f) {
        if (isset($_POST[$f])) {
            $saved[$domain][$f] = sanitize_textarea_field($_POST[$f]);
        }
    }

    update_option('sgclabs_site_profiles', $saved);

    // Save manual override
    if (isset($_POST['sgclabs_multisite_active'])) {
        update_option('sgclabs_multisite_active', '1');
    } else {
        update_option('sgclabs_multisite_active', '0');
    }
    update_option('sgclabs_active_profile_domain', sanitize_text_field($_POST['manual_domain'] ?? ''));

    wp_redirect(admin_url('admin.php?page=sgclabs-site-tools-hub&tab=multisite&saved=1&domain=' . urlencode($domain)));
    exit;
}

// ============================================================
// ADMIN PAGE
// ============================================================
function sgclabs_multisite_page() {
    $saved_profiles = get_option('sgclabs_site_profiles', []);
    $default_profiles = sgclabs_get_default_profiles();
    $current_domain = sgclabs_get_current_domain();
    $active_tab = sanitize_text_field($_GET['domain'] ?? 'labsglass.com');
    $is_saved = isset($_GET['saved']);
    $multisite_active = get_option('sgclabs_multisite_active', '1');
    $manual_domain = get_option('sgclabs_active_profile_domain', '');
    ?>
    <div class="wrap sgclabs-wrap">
        <h1>🌐 Multi-Site Profile Manager</h1>
        <p>4 domains ke liye alag alag settings — plugin automatically sahi profile load karega!</p>

        <?php if ($is_saved): ?>
            <div class="notice notice-success"><p>✅ Profile saved! Domain: <strong><?php echo esc_html($_GET['domain'] ?? ''); ?></strong></p></div>
        <?php endif; ?>

        <!-- Current Domain Status -->
        <div class="sgclabs-section" style="background:linear-gradient(135deg,#f0fff4,#e8f8f0);border-left:4px solid #27ae60;">
            <div style="display:flex;align-items:center;gap:15px;flex-wrap:wrap;">
                <div>
                    <strong style="font-size:16px;">🌐 Current Domain:</strong>
                    <code style="font-size:15px;background:#1a1a2e;color:#00ff88;padding:4px 12px;border-radius:4px;margin-left:8px;"><?php echo esc_html($current_domain); ?></code>
                </div>
                <?php if (isset($default_profiles[$current_domain])): ?>
                    <span style="background:#27ae60;color:white;padding:5px 15px;border-radius:20px;font-size:13px;">
                        ✅ Profile Found — <?php echo esc_html($default_profiles[$current_domain]['label']); ?>
                    </span>
                <?php else: ?>
                    <span style="background:#e67e22;color:white;padding:5px 15px;border-radius:20px;font-size:13px;">
                        ⚠️ Domain not in list — Manual profile select karo
                    </span>
                <?php endif; ?>
            </div>

            <div style="margin-top:15px;display:flex;align-items:center;gap:15px;flex-wrap:wrap;">
                <label style="font-weight:600;">
                    <input type="checkbox" id="toggle-multisite" <?php checked($multisite_active,'1'); ?> onchange="toggleMultisite(this)">
                    Auto Profile Switching Active
                </label>
                <span style="font-size:13px;color:#555;">Manual override:</span>
                <select id="manual-domain-select" style="padding:6px 10px;border-radius:5px;border:1px solid #ddd;" onchange="setManualDomain(this.value)">
                    <option value="">Auto-detect (recommended)</option>
                    <?php foreach ($default_profiles as $d => $p): ?>
                        <option value="<?php echo $d; ?>" <?php selected($manual_domain,$d); ?>><?php echo esc_html($p['label']); ?> — <?php echo $d; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Domain Tabs -->
        <div class="ms-domain-tabs">
            <?php foreach ($default_profiles as $domain => $profile): ?>
                <button class="ms-domain-tab <?php echo $active_tab===esc_attr($domain)?'active':''; ?>"
                    onclick="location.href='?page=sgclabs-site-tools-hub&tab=multisite&domain=<?php echo urlencode($domain); ?>'"
                    style="border-top:3px solid <?php echo esc_attr($profile['color']); ?>">
                    <?php echo esc_html($profile['label']); ?>
                    <small><?php echo esc_html($domain); ?></small>
                    <?php if ($current_domain === $domain): ?><span class="ms-live-badge">LIVE</span><?php endif; ?>
                    <?php if (isset($saved_profiles[$domain])): ?><span class="ms-custom-badge">✏️</span><?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Active Tab Profile Form -->
        <?php
        $profile = array_merge(
            $default_profiles[$active_tab] ?? $default_profiles['labsglass.com'],
            $saved_profiles[$active_tab] ?? []
        );
        ?>
        <div class="ms-profile-content">
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('sgclabs_profile_nonce'); ?>
                <input type="hidden" name="action" value="sgclabs_save_profile">
                <input type="hidden" name="profile_domain" value="<?php echo esc_attr($active_tab); ?>">

                <!-- Company Info -->
                <div class="sgclabs-section">
                    <h2 style="color:<?php echo esc_attr($profile['color']); ?>">🏢 Company Info — <?php echo esc_html($active_tab); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th>Company Name</th>
                            <td><input type="text" name="company_name" value="<?php echo esc_attr($profile['company_name']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th>Tagline</th>
                            <td><input type="text" name="tagline" value="<?php echo esc_attr($profile['tagline']); ?>" class="large-text"></td>
                        </tr>
                        <tr>
                            <th>Address</th>
                            <td><textarea name="address" class="large-text" rows="2"><?php echo esc_textarea($profile['address']); ?></textarea></td>
                        </tr>
                        <tr>
                            <th>Phone</th>
                            <td><input type="text" name="phone" value="<?php echo esc_attr($profile['phone']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td><input type="email" name="email" value="<?php echo esc_attr($profile['email']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th>WhatsApp Number</th>
                            <td><input type="text" name="whatsapp" value="<?php echo esc_attr($profile['whatsapp']); ?>" class="regular-text">
                            <p class="description">Country code + number, no + sign. e.g. 919996416935</p></td>
                        </tr>
                        <tr>
                            <th>Default Currency</th>
                            <td>
                                <select name="currency">
                                    <?php foreach(['USD'=>'$ USD','INR'=>'₹ INR','EUR'=>'€ EUR','GBP'=>'£ GBP','AED'=>'AED','SAR'=>'SAR'] as $c=>$l): ?>
                                        <option value="<?php echo esc_attr($c); ?>" <?php selected($profile['currency'],$c); ?>><?php echo esc_html($l); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Business Type</th>
                            <td><input type="text" name="business_type" value="<?php echo esc_attr($profile['business_type']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th>Founded Year</th>
                            <td><input type="text" name="founded" value="<?php echo esc_attr($profile['founded']); ?>" style="width:100px;"></td>
                        </tr>
                    </table>
                </div>

                <!-- SEO Settings -->
                <div class="sgclabs-section">
                    <h2>🔍 SEO Settings</h2>
                    <table class="form-table">
                        <tr>
                            <th>Homepage Title</th>
                            <td>
                                <input type="text" name="seo_title" value="<?php echo esc_attr($profile['seo_title']); ?>" class="large-text" id="seo_title_<?php echo $active_tab; ?>" oninput="updateCharCount(this,'title-count-<?php echo md5($active_tab); ?>')">
                                <span id="title-count-<?php echo md5($active_tab); ?>" style="font-size:12px;color:#666;"></span>
                                <div class="sgclabs-google-preview" style="margin-top:10px;">
                                    <div class="gp-url"><?php echo esc_html($active_tab); ?>/</div>
                                    <div class="gp-title" id="preview-title-<?php echo md5($active_tab); ?>"><?php echo esc_html(substr($profile['seo_title'],0,60)); ?></div>
                                    <div class="gp-desc" id="preview-desc-<?php echo md5($active_tab); ?>"><?php echo esc_html(substr($profile['seo_description'],0,160)); ?></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>Meta Description</th>
                            <td>
                                <textarea name="seo_description" class="large-text" rows="3" oninput="updateCharCount(this,'desc-count-<?php echo md5($active_tab); ?>')"><?php echo esc_textarea($profile['seo_description']); ?></textarea>
                                <span id="desc-count-<?php echo md5($active_tab); ?>" style="font-size:12px;color:#666;"></span>
                            </td>
                        </tr>
                        <tr>
                            <th>Focus Keywords</th>
                            <td><textarea name="seo_keywords" class="large-text" rows="2"><?php echo esc_textarea($profile['seo_keywords']); ?></textarea></td>
                        </tr>
                    </table>
                </div>

                <!-- Announcement Bar -->
                <div class="sgclabs-section">
                    <h2>📢 Announcement Bar</h2>
                    <table class="form-table">
                        <tr>
                            <th>Announcement Text</th>
                            <td><input type="text" name="announcement" value="<?php echo esc_attr($profile['announcement']); ?>" class="large-text"></td>
                        </tr>
                        <tr>
                            <th>Background Color</th>
                            <td><input type="color" name="announcement_bg" value="<?php echo esc_attr($profile['announcement_bg']); ?>"></td>
                        </tr>
                        <tr>
                            <th>Text Color</th>
                            <td><input type="color" name="announcement_color" value="<?php echo esc_attr($profile['announcement_color'] ?? '#ffffff'); ?>"></td>
                        </tr>
                    </table>
                    <!-- Preview -->
                    <div style="background:<?php echo esc_attr($profile['announcement_bg']); ?>;color:<?php echo esc_attr($profile['announcement_color'] ?? '#fff'); ?>;padding:10px;text-align:center;border-radius:5px;margin-top:10px;font-size:14px;">
                        <?php echo esc_html($profile['announcement']); ?>
                    </div>
                </div>

                <?php submit_button('💾 Save Profile — ' . esc_html($active_tab), 'primary large'); ?>
            </form>

            <!-- All Profiles Overview -->
            <div class="sgclabs-section" style="margin-top:30px;">
                <h2>📊 All 4 Profiles Overview</h2>
                <div class="ms-overview-grid">
                    <?php foreach ($default_profiles as $d => $p):
                        $merged = array_merge($p, $saved_profiles[$d] ?? []);
                        $is_current = $current_domain === $d;
                    ?>
                    <div class="ms-overview-card" style="border-top:4px solid <?php echo esc_attr($p['color']); ?>">
                        <div class="ms-card-header">
                            <span><?php echo esc_html($p['label']); ?></span>
                            <?php if ($is_current): ?><span class="ms-live-badge">🟢 LIVE</span><?php endif; ?>
                        </div>
                        <div class="ms-card-domain"><?php echo esc_html($d); ?></div>
                        <div class="ms-card-info">
                            <div>📞 <?php echo esc_html($merged['phone']); ?></div>
                            <div>📧 <?php echo esc_html($merged['email']); ?></div>
                            <div>💱 <?php echo esc_html($merged['currency']); ?></div>
                        </div>
                        <a href="?page=sgclabs-site-tools-hub&tab=multisite&domain=<?php echo urlencode($d); ?>" class="button button-small" style="margin-top:10px;">✏️ Edit</a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <style>
    .ms-domain-tabs { display:flex; gap:0; margin-bottom:0; flex-wrap:wrap; }
    .ms-domain-tab { background:#f5f5f5; border:1px solid #ddd; border-bottom:none; padding:12px 20px; cursor:pointer; font-size:13px; font-weight:600; display:flex; flex-direction:column; align-items:center; gap:2px; min-width:150px; transition:all 0.2s; }
    .ms-domain-tab small { font-size:11px; font-weight:normal; color:#888; }
    .ms-domain-tab.active { background:white; color:#1a1a2e; border-bottom-color:white; }
    .ms-domain-tab:hover { background:#e8e8e8; }
    .ms-live-badge { background:#27ae60; color:white; font-size:10px; padding:1px 6px; border-radius:10px; }
    .ms-custom-badge { font-size:12px; }
    .ms-profile-content { background:white; border:1px solid #ddd; border-radius:0 8px 8px 8px; padding:0 20px; }
    .ms-overview-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:15px; }
    .ms-overview-card { background:#f8f9ff; border-radius:10px; padding:15px; }
    .ms-card-header { display:flex; justify-content:space-between; align-items:center; font-weight:700; font-size:14px; margin-bottom:5px; }
    .ms-card-domain { font-size:12px; color:#666; margin-bottom:10px; font-family:monospace; }
    .ms-card-info { font-size:12px; color:#555; display:flex; flex-direction:column; gap:4px; }
    </style>

    <script>
    function updateCharCount(el, countId) {
        const len = el.value.length;
        const max = el.name === 'seo_title' ? 60 : 160;
        const el2 = document.getElementById(countId);
        if (el2) {
            el2.textContent = len + '/' + max + ' chars';
            el2.style.color = len > max ? '#e74c3c' : '#27ae60';
        }
    }

    function toggleMultisite(cb) {
        jQuery.post(ajaxurl, {
            action: 'sgclabs_toggle_multisite',
            nonce: '<?php echo esc_js( wp_create_nonce( 'sgclabs_nonce' ) ); ?>',
            active: cb.checked ? 1 : 0
        }, function(res) {
            if (res.success) {
                alert(cb.checked ? '✅ Auto profile switching ON!' : '❌ Auto profile switching OFF!');
            }
        });
    }

    function setManualDomain(domain) {
        jQuery.post(ajaxurl, {
            action: 'sgclabs_set_manual_domain',
            nonce: '<?php echo esc_js( wp_create_nonce( 'sgclabs_nonce' ) ); ?>',
            domain: domain
        }, function(res) {
            if (res.success) alert('✅ Manual domain set: ' + (domain || 'Auto-detect'));
        });
    }

    // Init char counts
    document.querySelectorAll('input[name="seo_title"], textarea[name="seo_description"]').forEach(el => {
        updateCharCount(el, el.name === 'seo_title' ? 'title-count-<?php echo md5($active_tab); ?>' : 'desc-count-<?php echo md5($active_tab); ?>');
    });
    </script>
    <?php
}

// AJAX handlers
add_action('wp_ajax_sgclabs_toggle_multisite', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; } // FIX: capability check added
    update_option('sgclabs_multisite_active', intval($_POST['active'] ?? 0) ? '1' : '0');
    wp_send_json_success();
});

add_action('wp_ajax_sgclabs_set_manual_domain', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; } // FIX: capability check added
    update_option('sgclabs_active_profile_domain', sanitize_text_field($_POST['domain'] ?? ''));
    wp_send_json_success();
});
