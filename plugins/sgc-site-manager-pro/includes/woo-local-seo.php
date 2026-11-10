<?php
if (!defined('ABSPATH')) exit;

// ============================================================
// SGC LABS® — WooCommerce SEO & Local SEO v6.6.3
// 1. Remove /product/ base slug
// 2. Product Archive SEO (category/tag meta)
// 3. Google My Business integration
// 4. Multiple Locations support
// 5. NAP markup + Opening Hours
// 6. Google AdSense Auto Ads
// ============================================================

// ── REMOVE /product/ BASE SLUG ────────────────────────────────
if (get_option('sgclabs_remove_product_slug', '0') === '1') {
    add_filter('post_type_link', 'sgclabs_remove_product_slug', 10, 2);
    add_action('pre_get_posts', 'sgclabs_product_slug_query_fix');
}

// ── HIDE WooCommerce Related Products ────────────────────────
// v7.3.1: Default is OFF (hidden) — enable from WooCommerce Hub → Display settings
if (get_option('sgclabs_hide_woo_related', '1') === '1') {
    remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20);
    add_filter('woocommerce_related_products', '__return_empty_array', 99);
}

function sgclabs_remove_product_slug($link, $post) {
    if ($post->post_type !== 'product') return $link;
    return str_replace('/product/', '/', $link);
}

function sgclabs_product_slug_query_fix($query) {
    if (!$query->is_main_query() || is_admin()) return;
    if (empty($query->query['name'])) return;

    // Check if the requested slug matches a product
    global $wpdb;
    $slug = $query->query['name'];
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'product' AND post_status = 'publish' LIMIT 1",
        $slug
    ));
    if ($exists) {
        $query->set('post_type', ['post', 'page', 'product']);
    }
}

// ── PRODUCT ARCHIVE SEO ───────────────────────────────────────
add_action('wp_head', 'sgclabs_product_archive_seo', 6);
function sgclabs_product_archive_seo(){
    if ( defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION') || defined('AIOSEO_VERSION') ) return;
    if (!is_tax('product_cat') && !is_tax('product_tag') && !is_post_type_archive('product')) return;

    $term = get_queried_object();
    if (!$term) return;

    // Custom meta title/desc for product categories
    $custom_title = '';
    $custom_desc = '';

    if (is_a($term, 'WP_Term')) {
        $custom_title = get_term_meta($term->term_id, '_sgclabs_seo_title', true);
        $custom_desc = get_term_meta($term->term_id, '_sgclabs_seo_description', true);
    }

    if (!$custom_title && is_a($term, 'WP_Term')) {
        $custom_title = $term->name . ' | ' . get_bloginfo('name');
    }
    if (!$custom_desc && is_a($term, 'WP_Term')) {
        $custom_desc = !empty($term->description)
            ? wp_trim_words(wp_strip_all_tags($term->description), 25)
            : 'Shop ' . $term->name . ' from ' . get_bloginfo('name') . '. Best prices, fast delivery.';
    }

    $has_seo_plugin = defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION');
    if (!$has_seo_plugin) {
        if ($custom_title) echo "<title>" . esc_html($custom_title) . "</title>\n";
        if ($custom_desc) echo "<meta name='description' content='" . esc_attr($custom_desc) . "'>\n";
        echo "<meta property='og:title' content='" . esc_attr($custom_title) . "'>\n";
        echo "<meta property='og:description' content='" . esc_attr($custom_desc) . "'>\n";
    }
}

// Add SEO fields to product category edit screen
add_action('product_cat_edit_form_fields', 'sgclabs_product_cat_seo_fields', 10, 1);
function sgclabs_product_cat_seo_fields($term) {
    $title = get_term_meta($term->term_id, '_sgclabs_seo_title', true);
    $desc = get_term_meta($term->term_id, '_sgclabs_seo_description', true);
    ?>
    <tr class="form-field">
        <th><label>SEO Title</label></th>
        <td><input type="text" name="sgclabs_seo_title" value="<?php echo esc_attr($title); ?>" style="width:100%;" placeholder="Custom SEO title for this category">
            <p class="description">Leave blank to use: Category Name | Site Name</p></td>
    </tr>
    <tr class="form-field">
        <th><label>SEO Description</label></th>
        <td><textarea name="sgclabs_seo_description" rows="3" style="width:100%;" placeholder="Custom meta description for this category"><?php echo esc_textarea($desc); ?></textarea></td>
    </tr>
    <?php
}
add_action('edited_product_cat', 'sgclabs_save_product_cat_seo', 10, 1);
function sgclabs_save_product_cat_seo($term_id) {
    if (isset($_POST['sgclabs_seo_title'])) update_term_meta($term_id, '_sgclabs_seo_title', sanitize_text_field($_POST['sgclabs_seo_title']));
    if (isset($_POST['sgclabs_seo_description'])) update_term_meta($term_id, '_sgclabs_seo_description', sanitize_textarea_field($_POST['sgclabs_seo_description']));
}

// ── GEO META TAGS — Google geo.region + geo.placename ─────────
// Tells Google which country/region this page targets
// e.g. geo.region = "IN-HR" → Haryana, India
add_action('wp_head', 'sgclabs_geo_meta_tags', 3);
function sgclabs_geo_meta_tags() {
    if ( defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION') || defined('AIOSEO_VERSION') ) return;
    if ( get_option('sgclabs_local_seo_enabled', '0') !== '1' ) return;

    $country   = get_option( 'sgclabs_local_country', 'IN' );
    $state     = get_option( 'sgclabs_local_state', '' );
    $city      = get_option( 'sgclabs_local_city', '' );
    $lat       = get_option( 'sgclabs_local_latitude', '' );
    $lon       = get_option( 'sgclabs_local_longitude', '' );

    // Build geo.region — format: "CC-SS" e.g. "IN-HR", "US-CA", "DE-BY"
    $geo_region = strtoupper( $country );
    if ( ! empty( $state ) ) {
        // Map common state names to ISO 3166-2 codes
        $state_map = [
            'Haryana'           => 'HR', 'Delhi'         => 'DL', 'Maharashtra'   => 'MH',
            'Karnataka'         => 'KA', 'Tamil Nadu'    => 'TN', 'Uttar Pradesh' => 'UP',
            'Gujarat'           => 'GJ', 'Rajasthan'     => 'RJ', 'Punjab'        => 'PB',
            'California'        => 'CA', 'New York'      => 'NY', 'Texas'         => 'TX',
            'Florida'           => 'FL', 'Washington'    => 'WA',
            'Bavaria'           => 'BY', 'Berlin'        => 'BE', 'Hamburg'       => 'HH',
            'Ile-de-France'     => 'IDF','Auvergne'     => 'ARA',
            'Ontario'           => 'ON', 'British Columbia' => 'BC', 'Quebec'     => 'QC',
            'New South Wales'   => 'NSW','Victoria'     => 'VIC', 'Queensland'   => 'QLD',
            'Dubai'             => 'DU', 'Abu Dhabi'    => 'AZ', 'Sharjah'       => 'SH',
        ];
        $state_code = $state_map[ $state ] ?? strtoupper( substr( $state, 0, 2 ) );
        $geo_region = $geo_region . '-' . $state_code;
    }

    // Build geo.placename — "City, State, Country"
    $placename_parts = array_filter( [ $city, $state, $country ] );
    $geo_placename   = implode( ', ', $placename_parts );

    // Output geo meta tags
    echo '<meta name="geo.region" content="' . esc_attr( $geo_region ) . '">' . "\n";
    if ( ! empty( $geo_placename ) ) {
        echo '<meta name="geo.placename" content="' . esc_attr( $geo_placename ) . '">' . "\n";
    }
    if ( ! empty( $lat ) && ! empty( $lon ) ) {
        echo '<meta name="geo.position" content="' . esc_attr( $lat ) . ';' . esc_attr( $lon ) . '">' . "\n";
        echo '<meta name="ICBM" content="' . esc_attr( $lat ) . ', ' . esc_attr( $lon ) . '">' . "\n";
    }
    // Content-Language hint for Google
    $lang_map = [
        'IN' => 'en-IN', 'US' => 'en-US', 'GB' => 'en-GB',
        'AU' => 'en-AU', 'CA' => 'en-CA', 'DE' => 'de-DE',
        'FR' => 'fr-FR', 'ES' => 'es-ES', 'JP' => 'ja-JP',
        'KR' => 'ko-KR', 'AE' => 'ar-AE', 'SA' => 'ar-SA',
        'BR' => 'pt-BR', 'RU' => 'ru-RU', 'CN' => 'zh-CN',
        'NL' => 'nl-NL', 'IT' => 'it-IT', 'PL' => 'pl-PL',
        'TR' => 'tr-TR', 'TH' => 'th-TH', 'ID' => 'id-ID',
        'MY' => 'ms-MY', 'ZA' => 'en-ZA', 'NG' => 'en-NG',
        'EG' => 'ar-EG', 'PK' => 'ur-PK', 'BD' => 'bn-BD',
    ];
    $content_lang = $lang_map[ $country ] ?? 'en';
    echo '<meta http-equiv="content-language" content="' . esc_attr( $content_lang ) . '">' . "\n";
}

// ── LOCAL SEO: GOOGLE MY BUSINESS ─────────────────────────────
add_action('wp_head', 'sgclabs_local_seo_schema', 8);
function sgclabs_local_seo_schema(){
    if ( defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION') ) return;
    if (get_option('sgclabs_local_seo_enabled', '0') !== '1') return;
    if (!is_front_page() && !is_page()) return;

    $biz_name = get_option('sgclabs_local_biz_name', get_bloginfo('name'));
    $biz_type = get_option('sgclabs_local_biz_type', 'LocalBusiness');
    $phone = get_option('sgclabs_local_phone', '');
    $email = get_option('sgclabs_local_email', '');
    $address = get_option('sgclabs_local_address', '');
    $city = get_option('sgclabs_local_city', '');
    $state = get_option('sgclabs_local_state', '');
    $zip = get_option('sgclabs_local_zip', '');
    $country = get_option('sgclabs_local_country', 'IN');
    $lat = get_option('sgclabs_local_latitude', '');
    $lon = get_option('sgclabs_local_longitude', '');
    $logo = get_option('sgclabs_local_logo', '');
    $url = get_option('sgclabs_local_url', home_url('/'));
    $price_range = get_option('sgclabs_local_price_range', '$$');

    // Opening hours
    $hours = get_option('sgclabs_local_hours', []);
    $hours_spec = [];
    if (is_array($hours)) {
        foreach ($hours as $h) {
            if (!empty($h['day']) && !empty($h['open']) && !empty($h['close'])) {
                $hours_spec[] = [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => $h['day'],
                    'opens' => $h['open'],
                    'closes' => $h['close'],
                ];
            }
        }
    }

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => $biz_type,
        'name' => $biz_name,
        'url' => $url,
        'telephone' => $phone,
        'email' => $email,
        'priceRange' => $price_range,
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => $address,
            'addressLocality' => $city,
            'addressRegion' => $state,
            'postalCode' => $zip,
            'addressCountry' => $country,
        ],
    ];

    if ($lat && $lon) {
        $schema['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $lat, 'longitude' => $lon];
    }
    if ($logo) $schema['logo'] = $logo;
    if (!empty($hours_spec)) $schema['openingHoursSpecification'] = $hours_spec;

    // Social profiles / sameAs
    $socials = array_filter([
        get_option('sgclabs_local_facebook', ''),
        get_option('sgclabs_local_instagram', ''),
        get_option('sgclabs_local_linkedin', ''),
        get_option('sgclabs_local_twitter', ''),
        get_option('sgclabs_local_youtube', ''),
    ]);
    if (!empty($socials)) $schema['sameAs'] = $socials;

    echo "<script type='application/ld+json'>" . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";

    // Multiple locations
    $locations = get_option('sgclabs_local_locations', []);
    if (is_array($locations) && count($locations) > 0) {
        foreach ($locations as $loc) {
            $loc_schema = [
                '@context' => 'https://schema.org',
                '@type' => $biz_type,
                'name' => isset($loc['name']) ? $loc['name'] : $biz_name,
                'telephone' => isset($loc['phone']) ? $loc['phone'] : $phone,
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => isset($loc['addr']) ? $loc['addr'] : '',
                    'addressLocality' => isset($loc['city']) ? $loc['city'] : '',
                    'addressRegion' => isset($loc['state']) ? $loc['state'] : '',
                    'postalCode' => isset($loc['zip']) ? $loc['zip'] : '',
                    'addressCountry' => isset($loc['country']) ? $loc['country'] : $country,
                ],
            ];
            if (!empty($loc['lat']) && !empty($loc['lon'])) {
                $loc_schema['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $loc['lat'], 'longitude' => $loc['lon']];
            }
            echo "<script type='application/ld+json'>" . wp_json_encode($loc_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";
        }
    }
}

// ── NAP SHORTCODE ─────────────────────────────────────────────
add_shortcode('sgclabs_nap', 'sgclabs_nap_shortcode');
function sgclabs_nap_shortcode($atts) {
    $biz = get_option('sgclabs_local_biz_name', get_bloginfo('name'));
    $addr = get_option('sgclabs_local_address', '') . ', ' . get_option('sgclabs_local_city', '') . ', ' . get_option('sgclabs_local_state', '') . ' ' . get_option('sgclabs_local_zip', '');
    $phone = get_option('sgclabs_local_phone', '');
    $email = get_option('sgclabs_local_email', '');

    $html = '<div class="sgclabs-nap" itemscope itemtype="https://schema.org/LocalBusiness" style="padding:15px;background:#f8f9fa;border-radius:8px;border:1px solid #e0e0e0;">';
    $html .= '<strong itemprop="name">' . esc_html($biz) . '</strong><br>';
    $html .= '<span itemprop="address" itemscope itemtype="https://schema.org/PostalAddress">';
    $html .= '<span itemprop="streetAddress">' . esc_html(get_option('sgclabs_local_address', '')) . '</span>, ';
    $html .= '<span itemprop="addressLocality">' . esc_html(get_option('sgclabs_local_city', '')) . '</span>, ';
    $html .= '<span itemprop="addressRegion">' . esc_html(get_option('sgclabs_local_state', '')) . '</span> ';
    $html .= '<span itemprop="postalCode">' . esc_html(get_option('sgclabs_local_zip', '')) . '</span>';
    $html .= '</span><br>';
    if ($phone) $html .= '📞 <a href="tel:' . esc_attr($phone) . '" itemprop="telephone">' . esc_html($phone) . '</a><br>';
    if ($email) $html .= '📧 <a href="mailto:' . esc_attr($email) . '" itemprop="email">' . esc_html($email) . '</a>';
    $html .= '</div>';
    return $html;
}

// ── LOCAL SEO ADMIN PAGE ──────────────────────────────────────
function sgclabs_local_seo_page() {
    if (!current_user_can('manage_options')) return;
    if (isset($_POST['sgclabs_local_save']) && wp_verify_nonce($_POST['_wpnonce'], 'sgclabs_local_nonce')) {
        update_option('sgclabs_local_seo_enabled', isset($_POST['local_enabled']) ? '1' : '0');
        update_option('sgclabs_local_biz_name', sanitize_text_field($_POST['biz_name'] ?? ''));
        update_option('sgclabs_local_biz_type', sanitize_text_field($_POST['biz_type'] ?? 'LocalBusiness'));
        update_option('sgclabs_local_phone', sanitize_text_field($_POST['phone'] ?? ''));
        update_option('sgclabs_local_email', sanitize_email($_POST['email'] ?? ''));
        update_option('sgclabs_local_address', sanitize_text_field($_POST['address'] ?? ''));
        update_option('sgclabs_local_city', sanitize_text_field($_POST['city'] ?? ''));
        update_option('sgclabs_local_state', sanitize_text_field($_POST['state'] ?? ''));
        update_option('sgclabs_local_zip', sanitize_text_field($_POST['zip'] ?? ''));
        update_option('sgclabs_local_country', sanitize_text_field($_POST['country'] ?? 'IN'));
        update_option('sgclabs_local_latitude', sanitize_text_field($_POST['latitude'] ?? ''));
        update_option('sgclabs_local_longitude', sanitize_text_field($_POST['longitude'] ?? ''));
        update_option('sgclabs_local_logo', esc_url_raw($_POST['logo'] ?? ''));
        update_option('sgclabs_local_url', esc_url_raw($_POST['url'] ?? home_url('/')));
        update_option('sgclabs_local_price_range', sanitize_text_field($_POST['price_range'] ?? ''));
        update_option('sgclabs_local_facebook', esc_url_raw($_POST['facebook'] ?? ''));
        update_option('sgclabs_local_instagram', esc_url_raw($_POST['instagram'] ?? ''));
        update_option('sgclabs_local_linkedin', esc_url_raw($_POST['linkedin'] ?? ''));
        update_option('sgclabs_local_twitter', esc_url_raw($_POST['twitter'] ?? ''));
        update_option('sgclabs_local_youtube', esc_url_raw($_POST['youtube'] ?? ''));

        // Opening hours
        $hours = [];
        if (!empty($_POST['day']) && is_array($_POST['day'])) {
            foreach ($_POST['day'] as $i => $day) {
                $hours[] = [
                    'day' => sanitize_text_field($day),
                    'open' => sanitize_text_field($_POST['open_time'][$i] ?? '09:00'),
                    'close' => sanitize_text_field($_POST['close_time'][$i] ?? '18:00'),
                ];
            }
        }
        update_option('sgclabs_local_hours', $hours);

        // Multiple locations
        $locations = [];
        if (!empty($_POST['loc_name']) && is_array($_POST['loc_name'])) {
            foreach ($_POST['loc_name'] as $i => $lname) {
                if (empty($lname)) continue;
                $locations[] = [
                    'name' => sanitize_text_field($lname),
                    'addr' => sanitize_text_field($_POST['loc_addr'][$i] ?? ''),
                    'city' => sanitize_text_field($_POST['loc_city'][$i] ?? ''),
                    'state' => sanitize_text_field($_POST['loc_state'][$i] ?? ''),
                    'zip' => sanitize_text_field($_POST['loc_zip'][$i] ?? ''),
                    'country' => sanitize_text_field($_POST['loc_country'][$i] ?? 'IN'),
                    'phone' => sanitize_text_field($_POST['loc_phone'][$i] ?? ''),
                    'lat' => sanitize_text_field($_POST['loc_lat'][$i] ?? ''),
                    'lon' => sanitize_text_field($_POST['loc_lon'][$i] ?? ''),
                ];
            }
        }
        update_option('sgclabs_local_locations', $locations);
        echo '<div class="notice notice-success"><p>✅ Local SEO settings saved!</p></div>';
    }

    $enabled = get_option('sgclabs_local_seo_enabled', '0');
    $biz_name = get_option('sgclabs_local_biz_name', get_bloginfo('name'));
    $biz_type = get_option('sgclabs_local_biz_type', 'LocalBusiness');
    $phone = get_option('sgclabs_local_phone', '');
    $email = get_option('sgclabs_local_email', get_bloginfo('admin_email'));
    $address = get_option('sgclabs_local_address', '');
    $city = get_option('sgclabs_local_city', '');
    $state = get_option('sgclabs_local_state', '');
    $zip = get_option('sgclabs_local_zip', '');
    $country = get_option('sgclabs_local_country', 'IN');
    $lat = get_option('sgclabs_local_latitude', '');
    $lon = get_option('sgclabs_local_longitude', '');
    $hours = get_option('sgclabs_local_hours', []);
    $locations = get_option('sgclabs_local_locations', []);
    ?>
    <div class="wrap" style="max-width:900px;">
        <h1>📍 Local SEO & Google My Business <span style="font-size:12px;background:#1a1a2e;color:white;padding:3px 10px;border-radius:12px;">SGC LABS®</span></h1>
        <form method="POST">
            <?php wp_nonce_field('sgclabs_local_nonce'); ?>
            <input type="hidden" name="sgclabs_local_save" value="1">
            <div style="background:white;border:1px solid #e0e0e0;border-radius:10px;padding:25px;margin:15px 0;">
                <h3>🏢 Business Information</h3>
                <label><input type="checkbox" name="local_enabled" value="1" <?php checked($enabled, '1'); ?>> Enable Local SEO Schema</label>
                <table class="form-table">
                    <tr><th>Business Name</th><td><input type="text" name="biz_name" value="<?php echo esc_attr($biz_name); ?>" class="regular-text"></td></tr>
                    <tr><th>Business Type</th><td><select name="biz_type">
                        <?php foreach (['LocalBusiness','Store','Restaurant','ProfessionalService','MedicalBusiness','EducationalOrganization','AutoRepair','HealthAndBeautyBusiness','LodgingBusiness','FoodEstablishment','SportsActivityLocation'] as $t): ?>
                        <option value="<?php echo $t; ?>" <?php selected($biz_type, $t); ?>><?php echo $t; ?></option>
                        <?php endforeach; ?>
                    </select></td></tr>
                    <tr><th>Phone</th><td><input type="text" name="phone" value="<?php echo esc_attr($phone); ?>" class="regular-text" placeholder="+91-999-641-6935"></td></tr>
                    <tr><th>Email</th><td><input type="email" name="email" value="<?php echo esc_attr($email); ?>" class="regular-text"></td></tr>
                    <tr><th>Street Address</th><td><input type="text" name="address" value="<?php echo esc_attr($address); ?>" class="regular-text"></td></tr>
                    <tr><th>City</th><td><input type="text" name="city" value="<?php echo esc_attr($city); ?>"></td></tr>
                    <tr><th>State/Region</th><td><input type="text" name="state" value="<?php echo esc_attr($state); ?>"></td></tr>
                    <tr><th>ZIP/Postal Code</th><td><input type="text" name="zip" value="<?php echo esc_attr($zip); ?>"></td></tr>
                    <tr><th>Country Code</th><td><input type="text" name="country" value="<?php echo esc_attr($country); ?>" style="width:60px;" placeholder="IN"></td></tr>
                    <tr><th>Latitude</th><td><input type="text" name="latitude" value="<?php echo esc_attr($lat); ?>" placeholder="30.3378"></td></tr>
                    <tr><th>Longitude</th><td><input type="text" name="longitude" value="<?php echo esc_attr($lon); ?>" placeholder="76.8601"></td></tr>
                    <tr><th>Logo URL</th><td><input type="url" name="logo" value="<?php echo esc_attr(get_option('sgclabs_local_logo', '')); ?>" class="regular-text"></td></tr>
                    <tr><th>Website URL</th><td><input type="url" name="url" value="<?php echo esc_attr(get_option('sgclabs_local_url', home_url('/'))); ?>" class="regular-text"></td></tr>
                    <tr><th>Price Range</th><td><input type="text" name="price_range" value="<?php echo esc_attr(get_option('sgclabs_local_price_range', '$$')); ?>" placeholder="$$$"></td></tr>
                </table>
            </div>

            <div style="background:white;border:1px solid #e0e0e0;border-radius:10px;padding:25px;margin:15px 0;">
                <h3>🕐 Opening Hours</h3>
                <div id="sgc-hours-list">
                    <?php
                    $days_list = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
                    if (empty($hours)) $hours = [['day'=>'Monday','open'=>'09:00','close'=>'18:00']];
                    foreach ($hours as $i => $h): ?>
                    <div style="display:flex;gap:10px;margin-bottom:6px;align-items:center;">
                        <select name="day[]"><?php foreach ($days_list as $d): ?><option value="<?php echo $d; ?>" <?php selected($h['day'], $d); ?>><?php echo $d; ?></option><?php endforeach; ?></select>
                        <input type="time" name="open_time[]" value="<?php echo esc_attr($h['open']); ?>"> — <input type="time" name="close_time[]" value="<?php echo esc_attr($h['close']); ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button" onclick="var d=document.getElementById('sgc-hours-list');var row=d.children[0].cloneNode(true);d.appendChild(row);">+ Add Hours</button>
            </div>

            <div style="background:white;border:1px solid #e0e0e0;border-radius:10px;padding:25px;margin:15px 0;">
                <h3>🌐 Social Profiles</h3>
                <table class="form-table">
                    <tr><th>Facebook</th><td><input type="url" name="facebook" value="<?php echo esc_attr(get_option('sgclabs_local_facebook', '')); ?>" class="regular-text"></td></tr>
                    <tr><th>Instagram</th><td><input type="url" name="instagram" value="<?php echo esc_attr(get_option('sgclabs_local_instagram', '')); ?>" class="regular-text"></td></tr>
                    <tr><th>LinkedIn</th><td><input type="url" name="linkedin" value="<?php echo esc_attr(get_option('sgclabs_local_linkedin', '')); ?>" class="regular-text"></td></tr>
                    <tr><th>Twitter/X</th><td><input type="url" name="twitter" value="<?php echo esc_attr(get_option('sgclabs_local_twitter', '')); ?>" class="regular-text"></td></tr>
                    <tr><th>YouTube</th><td><input type="url" name="youtube" value="<?php echo esc_attr(get_option('sgclabs_local_youtube', '')); ?>" class="regular-text"></td></tr>
                </table>
            </div>

            <div style="background:white;border:1px solid #e0e0e0;border-radius:10px;padding:25px;margin:15px 0;">
                <h3>📍 Multiple Locations</h3>
                <p style="font-size:12px;color:#888;">Add additional business locations. Each gets its own LocalBusiness schema.</p>
                <div id="sgc-locations-list">
                    <?php if (!empty($locations)): foreach ($locations as $i => $loc): ?>
                    <div style="background:#f8f9fa;border-radius:8px;padding:12px;margin-bottom:10px;border:1px solid #eee;">
                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
                            <input type="text" name="loc_name[]" value="<?php echo esc_attr($loc['name'] ?? ''); ?>" placeholder="Location Name" style="flex:2;min-width:200px;">
                            <input type="text" name="loc_addr[]" value="<?php echo esc_attr($loc['addr'] ?? ''); ?>" placeholder="Address" style="flex:2;">
                            <input type="text" name="loc_city[]" value="<?php echo esc_attr($loc['city'] ?? ''); ?>" placeholder="City" style="width:120px;">
                            <input type="text" name="loc_state[]" value="<?php echo esc_attr($loc['state'] ?? ''); ?>" placeholder="State" style="width:100px;">
                            <input type="text" name="loc_zip[]" value="<?php echo esc_attr($loc['zip'] ?? ''); ?>" placeholder="ZIP" style="width:80px;">
                            <input type="text" name="loc_country[]" value="<?php echo esc_attr($loc['country'] ?? 'IN'); ?>" placeholder="IN" style="width:50px;">
                            <input type="text" name="loc_phone[]" value="<?php echo esc_attr($loc['phone'] ?? ''); ?>" placeholder="Phone" style="width:140px;">
                            <input type="text" name="loc_lat[]" value="<?php echo esc_attr($loc['lat'] ?? ''); ?>" placeholder="Lat" style="width:80px;">
                            <input type="text" name="loc_lon[]" value="<?php echo esc_attr($loc['lon'] ?? ''); ?>" placeholder="Lon" style="width:80px;">
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
                <button type="button" class="button" onclick="sgcAddLocation()">+ Add Location</button>
            </div>

            <p><button type="submit" class="button button-primary button-hero">💾 Save Local SEO Settings</button></p>
        </form>
        <div style="background:#fff3e0;border-radius:8px;padding:12px;margin-top:15px;font-size:12px;">
            <strong>💡 Shortcode:</strong> Use <code>[sgclabs_nap]</code> to display NAP (Name, Address, Phone) with Schema markup anywhere on your site.
        </div>
    </div>
    <script>
    function sgcAddLocation() {
        var html = '<div style="background:#f8f9fa;border-radius:8px;padding:12px;margin-bottom:10px;border:1px solid #eee;"><div style="display:flex;flex-wrap:wrap;gap:8px;">'
            + '<input type="text" name="loc_name[]" placeholder="Location Name" style="flex:2;min-width:200px;">'
            + '<input type="text" name="loc_addr[]" placeholder="Address" style="flex:2;">'
            + '<input type="text" name="loc_city[]" placeholder="City" style="width:120px;">'
            + '<input type="text" name="loc_state[]" placeholder="State" style="width:100px;">'
            + '<input type="text" name="loc_zip[]" placeholder="ZIP" style="width:80px;">'
            + '<input type="text" name="loc_country[]" value="IN" placeholder="IN" style="width:50px;">'
            + '<input type="text" name="loc_phone[]" placeholder="Phone" style="width:140px;">'
            + '<input type="text" name="loc_lat[]" placeholder="Lat" style="width:80px;">'
            + '<input type="text" name="loc_lon[]" placeholder="Lon" style="width:80px;">'
            + '</div></div>';
        document.getElementById('sgc-locations-list').insertAdjacentHTML('beforeend', html);
    }
    </script>
    <?php
}

// ── GOOGLE ADSENSE AUTO ADS ───────────────────────────────────
add_action('wp_head', 'sgclabs_adsense_inject', 3);
function sgclabs_adsense_inject() {
    if (is_admin()) return;
    $pub_id = get_option('sgclabs_adsense_pub_id', '');
    if (!$pub_id) return;
    echo '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . esc_attr($pub_id) . '" crossorigin="anonymous"></script>' . "\n";
}

// ── WOOCOMMERCE SEO SETTINGS ADMIN ────────────────────────────
function sgclabs_woo_seo_settings_page() {
    if (!current_user_can('manage_options')) return;
    if (isset($_POST['sgclabs_woo_seo_save']) && wp_verify_nonce($_POST['_wpnonce'], 'sgclabs_woo_seo_nonce')) {
        update_option('sgclabs_remove_product_slug', isset($_POST['remove_slug']) ? '1' : '0');
        update_option('sgclabs_hide_woo_related', isset($_POST['hide_woo_related']) ? '1' : '0');
        update_option('sgclabs_adsense_pub_id', sanitize_text_field($_POST['adsense_pub_id'] ?? ''));
        update_option('sgclabs_faq_display', isset($_POST['faq_display']) ? '1' : '0');
        update_option('sgclabs_auto_pillar_links', isset($_POST['auto_pillar']) ? '1' : '0');
        flush_rewrite_rules();
        echo '<div class="notice notice-success"><p>✅ Settings saved! Permalinks flushed.</p></div>';
    }
    ?>
    <div class="wrap" style="max-width:800px;">
        <h1>🛒 WooCommerce SEO & Integrations <span style="font-size:12px;background:#1a1a2e;color:white;padding:3px 10px;border-radius:12px;">SGC LABS®</span></h1>
        <form method="POST">
            <?php wp_nonce_field('sgclabs_woo_seo_nonce'); ?>
            <input type="hidden" name="sgclabs_woo_seo_save" value="1">

            <div style="background:white;border:1px solid #e0e0e0;border-radius:10px;padding:25px;margin:15px 0;">
                <h3>🔗 URL Settings</h3>
                <label><input type="checkbox" name="remove_slug" value="1" <?php checked(get_option('sgclabs_remove_product_slug', '0'), '1'); ?>> Remove <code>/product/</code> base from product URLs</label>
                <p style="font-size:12px;color:#888;">Changes <code>/product/beaker-250ml/</code> → <code>/beaker-250ml/</code>. Go to Settings → Permalinks and save after enabling.</p>
                <br>
                <label><input type="checkbox" name="hide_woo_related" value="1" <?php checked(get_option('sgclabs_hide_woo_related', '1'), '1'); ?>> Hide WooCommerce "Related Products" section on product pages</label>
                <p style="font-size:12px;color:#888;">Removes the default "Related products" grid below product description. Your "You May Also Like" section stays unaffected.</p>
            </div>

            <div style="background:white;border:1px solid #e0e0e0;border-radius:10px;padding:25px;margin:15px 0;">
                <h3>📝 Content Features</h3>
                <label><input type="checkbox" name="faq_display" value="1" <?php checked(get_option('sgclabs_faq_display', '1'), '1'); ?>> Display AI-generated FAQ section on posts/products</label><br><br>
                <label><input type="checkbox" name="auto_pillar" value="1" <?php checked(get_option('sgclabs_auto_pillar_links', '0'), '1'); ?>> Auto-insert links to Pillar/Cornerstone content</label>
            </div>

            <div style="background:white;border:1px solid #e0e0e0;border-radius:10px;padding:25px;margin:15px 0;">
                <h3>📊 Google AdSense</h3>
                <table class="form-table">
                    <tr><th>AdSense Publisher ID</th><td><input type="text" name="adsense_pub_id" value="<?php echo esc_attr(get_option('sgclabs_adsense_pub_id', '')); ?>" class="regular-text" placeholder="ca-pub-XXXXXXXXXXXXXXXX">
                    <p class="description">Enter your AdSense Publisher ID for Auto Ads. Leave blank to disable.</p></td></tr>
                </table>
            </div>

            <p><button type="submit" class="button button-primary button-hero">💾 Save Settings</button></p>
        </form>
    </div>
    <?php
}
