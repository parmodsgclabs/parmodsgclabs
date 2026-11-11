<?php
/**
 * SGC Site Manager Pro — Export SEO Engine
 *
 * v7.4.0 — New module for international export organic search growth:
 *
 *  1. AggregateRating schema auto-inject (WC reviews → Google star ratings)
 *  2. AI FAQ bulk generator + FAQPage schema (People Also Ask box)
 *  3. Country-specific landing page shortcode [sgclabs_export_page country="Germany"]
 *  4. Baidu + Bing Webmaster verification meta tags
 *  5. Bulk SEO health dashboard — score per product, priority queue
 *  6. Export-optimized Open Graph (HS code, FOB price, worldwide exporter)
 *  7. Product video schema (VideoObject)
 *  8. Google Merchant Center feed (/sgclabs-gmc-feed.xml)
 *
 * @package    SGCLabs_Manager
 * @since      7.4.0
 */
defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════════
// 1. AGGREGATE RATING SCHEMA — WC reviews → Google star ratings
// ═══════════════════════════════════════════════════════════
add_action( 'wp_head', 'sgclabs_aggregate_rating_schema', 4 );
function sgclabs_aggregate_rating_schema() {
    if ( ! function_exists( 'is_product' ) || ! is_product() ) return;
    if ( defined( 'RANK_MATH_VERSION' ) || defined( 'WPSEO_VERSION' ) ) return; // v7.5.1: SEO plugin owns Product schema (ratings included)
    if ( get_option( 'sgclabs_aggregate_rating_active', '1' ) !== '1' ) return;

    global $post;
    $product = wc_get_product( $post->ID );
    if ( ! $product ) return;

    $count  = $product->get_review_count();
    $rating = $product->get_average_rating();

    // Need at least 1 review for schema
    if ( $count < 1 || empty( $rating ) ) return;

    $name      = $product->get_name();
    $url       = get_permalink( $post->ID );
    $img       = get_the_post_thumbnail_url( $post->ID, 'full' ) ?: '';
    $price     = $product->get_regular_price();
    $currency  = get_woocommerce_currency();
    $sku       = $product->get_sku();
    $brand     = get_option( 'sgclabs_company_name', 'SGC LABS®' );
    $hs_code   = get_post_meta( $post->ID, '_sgcpm_hs_code', true )
              ?: get_post_meta( $post->ID, '_sgclabs_b2b_pricing', true )['hs_code'] ?? '';

    // Get tech specs for additionalProperty
    $specs    = get_post_meta( $post->ID, '_sgclabs_tech_specs', true ) ?: [];
    $add_prop = [];
    if ( ! empty( $hs_code ) ) $add_prop[] = [ '@type' => 'PropertyValue', 'name' => 'HS Code', 'value' => $hs_code ];
    foreach ( $specs as $k => $v ) {
        if ( ! empty( $v ) ) $add_prop[] = [ '@type' => 'PropertyValue', 'name' => ucwords( str_replace( '_', ' ', $k ) ), 'value' => $v ];
    }

    $schema = [
        '@context'    => 'https://schema.org',
        '@type'       => 'Product',
        'name'        => $name,
        'url'         => $url,
        'sku'         => $sku,
        'brand'       => [ '@type' => 'Brand', 'name' => $brand ],
        'manufacturer'=> [
            '@type'          => 'Organization',
            'name'           => $brand,
            'address'        => [ '@type' => 'PostalAddress', 'addressLocality' => 'Ambala', 'addressRegion' => 'Haryana', 'addressCountry' => 'IN' ],
            'foundingDate'   => get_option( 'sgclabs_founded_year', '1965' ),
        ],
        'countryOfOrigin' => [ '@type' => 'Country', 'name' => 'India' ],
        'aggregateRating' => [
            '@type'       => 'AggregateRating',
            'ratingValue' => number_format( (float) $rating, 1 ),
            'reviewCount' => intval( $count ),
            'bestRating'  => '5',
            'worstRating' => '1',
        ],
    ];

    if ( $img )           $schema['image']              = [ '@type' => 'ImageObject', 'url' => $img ];
    if ( ! empty( $add_prop ) ) $schema['additionalProperty'] = $add_prop;
    if ( $price ) {
        $schema['offers'] = [
            '@type'         => 'Offer',
            'price'         => $price,
            'priceCurrency' => $currency,
            'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'url'           => $url,
            'seller'        => [ '@type' => 'Organization', 'name' => $brand ],
            'eligibleRegion'=> [ '@type' => 'Place', 'name' => 'Worldwide' ],
        ];
    }

    // Add FAQPage schema if AI-generated FAQs exist
    $faq_raw = get_post_meta( $post->ID, '_sgclabs_ai_faq', true );
    if ( $faq_raw ) {
        $faqs = json_decode( $faq_raw, true );
        if ( is_array( $faqs ) && ! empty( $faqs ) ) {
            $items = [];
            foreach ( $faqs as $fq ) {
                if ( empty( $fq['q'] ) || empty( $fq['a'] ) ) continue;
                $items[] = [
                    '@type'          => 'Question',
                    'name'           => wp_strip_all_tags( $fq['q'] ),
                    'acceptedAnswer' => [ '@type' => 'Answer', 'text' => wp_strip_all_tags( $fq['a'] ) ],
                ];
            }
            if ( $items ) {
                echo '<script type="application/ld+json">' . wp_json_encode( [
                    '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
            }
        }
    }

    // Add VideoObject if product has video
    $video_url = get_post_meta( $post->ID, '_sgclabs_product_video', true );
    if ( $video_url ) {
        echo '<script type="application/ld+json">' . wp_json_encode( [
            '@context'     => 'https://schema.org',
            '@type'        => 'VideoObject',
            'name'         => $name . ' — Product Demo',
            'description'  => wp_strip_all_tags( substr( $product->get_short_description(), 0, 300 ) ),
            'thumbnailUrl' => $img,
            'contentUrl'   => $video_url,
            'uploadDate'   => get_the_date( 'c', $post->ID ),
            'publisher'    => [ '@type' => 'Organization', 'name' => $brand, 'url' => home_url() ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
    }

    echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . '</script>' . "\n";
}

// ═══════════════════════════════════════════════════════════
// 2. AI FAQ BULK GENERATOR
// ═══════════════════════════════════════════════════════════
add_action( 'wp_ajax_sgclabs_generate_product_faq', 'sgclabs_ajax_generate_product_faq' );
function sgclabs_ajax_generate_product_faq() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $product_id = intval( $_POST['product_id'] ?? 0 );
    if ( ! $product_id ) wp_send_json_error( 'Invalid product ID' );

    $product = wc_get_product( $product_id );
    if ( ! $product ) wp_send_json_error( 'Product not found' );

    $name     = $product->get_name();
    $cats     = implode( ', ', wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'names' ] ) ?: [] );
    $specs    = get_post_meta( $product_id, '_sgclabs_tech_specs', true ) ?: [];
    $hs_code  = $specs['hs_code'] ?? get_post_meta( $product_id, '_sgcpm_hs_code', true ) ?? '';
    $brand    = get_option( 'sgclabs_company_name', 'SGC LABS®' );

    $spec_str = '';
    foreach ( $specs as $k => $v ) {
        if ( $v && $k !== 'hs_code' ) $spec_str .= ucwords( str_replace( '_', ' ', $k ) ) . ': ' . $v . '. ';
    }

    $prompt = "Generate 6 buyer-intent FAQ questions and answers for this laboratory glassware product listing.
Product: {$name}
Category: {$cats}
" . ( $spec_str ? "Specs: {$spec_str}" : '' ) . "
" . ( $hs_code ? "HS Code: {$hs_code}" : '' ) . "
Company: {$brand} — ISO 9001:2015 certified manufacturer, Ambala India, worldwide export since 1965.

FAQ topics to cover: MOQ, delivery/shipping, material/glass type, customization, certifications, payment terms, sample availability.

Each Q must be what an international buyer actually types in Google.
Return ONLY valid JSON array: [{\"q\":\"Question\",\"a\":\"Answer 40-80 words\"},...]
No markdown, no preamble.";

    $result = sgclabs_ai_call( $prompt, 1500 );
    if ( is_wp_error( $result ) ) wp_send_json_error( $result->get_error_message() );

    $clean = preg_replace( '/```(?:json)?|```/', '', $result );
    $faqs  = json_decode( trim( $clean ), true );
    if ( ! is_array( $faqs ) || empty( $faqs ) ) wp_send_json_error( 'AI parsing failed. Raw: ' . substr( $result, 0, 200 ) );

    // Sanitize
    $sanitized = [];
    foreach ( $faqs as $fq ) {
        if ( empty( $fq['q'] ) || empty( $fq['a'] ) ) continue;
        $sanitized[] = [ 'q' => sanitize_text_field( $fq['q'] ), 'a' => sanitize_textarea_field( $fq['a'] ) ];
    }

    update_post_meta( $product_id, '_sgclabs_ai_faq', wp_json_encode( $sanitized ) );
    wp_send_json_success( [ 'faqs' => $sanitized, 'count' => count( $sanitized ), 'message' => count( $sanitized ) . ' FAQs generated and saved. FAQPage schema will auto-inject on product page.' ] );
}

// Bulk FAQ generator — batch mode
add_action( 'wp_ajax_sgclabs_bulk_faq_generate', 'sgclabs_ajax_bulk_faq_generate' );
function sgclabs_ajax_bulk_faq_generate() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $offset     = intval( $_POST['offset'] ?? 0 );
    $batch_size = min( 5, intval( $_POST['batch_size'] ?? 3 ) ); // Max 5 per batch (AI rate limits)
    $skip_existing = isset( $_POST['skip_existing'] ) && $_POST['skip_existing'] === '1';

    $args = [
        'post_type'   => 'product',
        'post_status' => 'publish',
        'numberposts' => $batch_size,
        'offset'      => $offset,
        'fields'      => 'ids',
    ];

    if ( $skip_existing ) {
        $args['meta_query'] = [ [
            'key'     => '_sgclabs_ai_faq',
            'compare' => 'NOT EXISTS',
        ] ];
    }

    $ids   = get_posts( $args );
    $done  = 0;
    $errors= [];

    foreach ( $ids as $pid ) {
        // Simulate single product FAQ call
        $_POST['product_id'] = $pid;
        $_POST['nonce']      = wp_create_nonce( 'sgclabs_nonce' );

        $product = wc_get_product( $pid );
        if ( ! $product ) continue;

        $name    = $product->get_name();
        $cats    = implode( ', ', wp_get_post_terms( $pid, 'product_cat', ['fields'=>'names'] ) ?: [] );
        $specs   = get_post_meta( $pid, '_sgclabs_tech_specs', true ) ?: [];
        $brand   = get_option( 'sgclabs_company_name', 'SGC LABS®' );
        $spec_str= '';
        foreach ($specs as $k => $v) { if($v&&$k!=='hs_code') $spec_str .= ucwords(str_replace('_',' ',$k)).': '.$v.'. '; }

        $prompt = "Generate 5 buyer-intent FAQs for: {$name} ({$cats}). Company: {$brand}, India export. Topics: MOQ, delivery, material, certifications, customization. Return JSON array [{\"q\":\"...\",\"a\":\"...\"}] only.";
        $result = sgclabs_ai_call( $prompt, 800 );
        if ( is_wp_error( $result ) ) { $errors[] = $pid; continue; }
        $clean = preg_replace('/```(?:json)?|```/','',$result);
        $faqs  = json_decode(trim($clean),true);
        if ( is_array($faqs) && !empty($faqs) ) {
            $san = array_map(function($f){ return ['q'=>sanitize_text_field($f['q']??''),'a'=>sanitize_textarea_field($f['a']??'')]; }, $faqs);
            update_post_meta($pid,'_sgclabs_ai_faq',wp_json_encode($san));
            $done++;
        } else {
            $errors[] = $pid;
        }
        usleep(500000); // 0.5s between calls — API rate limit respect
    }

    $total = wp_count_posts('product')->publish;
    wp_send_json_success([
        'done'    => $done,
        'errors'  => count($errors),
        'offset'  => $offset + count($ids),
        'total'   => $total,
        'message' => "{$done} products FAQ generated. " . count($errors) . " failed.",
        'has_more'=> count($ids) === $batch_size,
    ]);
}

// ═══════════════════════════════════════════════════════════
// 3. COUNTRY LANDING PAGE SHORTCODE
// [sgclabs_export_page country="Germany"] or [sgclabs_export_page country="UAE"]
// ═══════════════════════════════════════════════════════════
add_shortcode( 'sgclabs_export_page', 'sgclabs_export_country_page' );
function sgclabs_export_country_page( $atts ) {
    $atts = shortcode_atts( [
        'country'  => 'Germany',
        'currency' => '',
        'lang'     => 'en',
        'flag'     => '',
    ], $atts, 'sgclabs_export_page' );

    $country  = sanitize_text_field( $atts['country'] );
    $brand    = get_option( 'sgclabs_company_name', 'SGC LABS®' );
    $phone    = get_option( 'sgclabs_header_phone', '+91 999 641 6935' );
    $email    = get_option( 'sgclabs_header_email', 'sales@labsglass.com' );
    $shop_url = wc_get_page_permalink( 'shop' ) ?: home_url( '/shop/' );
    $rfq_url  = get_option( 'sgclabs_rfq_page_url', home_url( '/get-a-quote/' ) );

    // Country-specific data
    $country_data = [
        'Germany'      => [ 'currency' => 'EUR', 'flag' => '🇩🇪', 'lang' => 'de', 'incoterm' => 'CIF Hamburg', 'port' => 'Hamburg', 'duty_note' => 'EU import duty ~3.7% for lab glassware (HS 7017)', 'buyers' => 'universities, pharma labs, research institutes' ],
        'USA'          => [ 'currency' => 'USD', 'flag' => '🇺🇸', 'lang' => 'en', 'incoterm' => 'CIF New York', 'port' => 'New York / LA', 'duty_note' => 'US import duty 0-6.5% for lab glassware (HS 7017)', 'buyers' => 'universities, biotech firms, pharma companies' ],
        'UAE'          => [ 'currency' => 'AED', 'flag' => '🇦🇪', 'lang' => 'ar', 'incoterm' => 'CIF Dubai', 'port' => 'Jebel Ali', 'duty_note' => 'UAE import duty 5% (GCC tariff)', 'buyers' => 'hospitals, universities, oil & gas labs' ],
        'UK'           => [ 'currency' => 'GBP', 'flag' => '🇬🇧', 'lang' => 'en', 'incoterm' => 'CIF Felixstowe', 'port' => 'Felixstowe', 'duty_note' => 'UK global tariff 0-2.5% for lab glassware', 'buyers' => 'universities, NHS labs, research centres' ],
        'Nigeria'      => [ 'currency' => 'USD', 'flag' => '🇳🇬', 'lang' => 'en', 'incoterm' => 'CIF Lagos', 'port' => 'Apapa, Lagos', 'duty_note' => 'SON inspection required for lab equipment', 'buyers' => 'teaching hospitals, universities, government labs' ],
        'Kenya'        => [ 'currency' => 'USD', 'flag' => '🇰🇪', 'lang' => 'en', 'incoterm' => 'CIF Mombasa', 'port' => 'Mombasa', 'duty_note' => 'EAC import duty 0-10%', 'buyers' => 'hospitals, research institutes, universities' ],
        'Saudi Arabia' => [ 'currency' => 'SAR', 'flag' => '🇸🇦', 'lang' => 'ar', 'incoterm' => 'CIF Jeddah', 'port' => 'Jeddah', 'duty_note' => 'GCC tariff 5% + SASO certification', 'buyers' => 'aramco labs, hospitals, universities' ],
        'Australia'    => [ 'currency' => 'AUD', 'flag' => '🇦🇺', 'lang' => 'en', 'incoterm' => 'CIF Sydney', 'port' => 'Sydney / Melbourne', 'duty_note' => 'FTA benefit — 0% duty for India under ECTA 2022', 'buyers' => 'universities, mining labs, hospitals' ],
        'Canada'       => [ 'currency' => 'CAD', 'flag' => '🇨🇦', 'lang' => 'en', 'incoterm' => 'CIF Vancouver', 'port' => 'Vancouver / Toronto', 'duty_note' => 'Canada import duty 0-6%', 'buyers' => 'universities, pharma, mining labs' ],
        'Brazil'       => [ 'currency' => 'USD', 'flag' => '🇧🇷', 'lang' => 'pt', 'incoterm' => 'CIF Santos', 'port' => 'Santos', 'duty_note' => 'II import tax varies — check NCM code', 'buyers' => 'universities, petrochemical labs, hospitals' ],
    ];

    $d       = $country_data[ $country ] ?? [ 'currency' => 'USD', 'flag' => '🌍', 'lang' => 'en', 'incoterm' => 'FOB New Delhi', 'port' => 'JNPT Mumbai', 'duty_note' => 'Contact us for duty information', 'buyers' => 'laboratories, hospitals, research institutes' ];
    $flag    = $d['flag'];
    $cur     = $atts['currency'] ?: $d['currency'];
    $inco    = $d['incoterm'];
    $port    = $d['port'];
    $duty    = $d['duty_note'];
    $buyers  = $d['buyers'];

    // Add CountryLandingPage schema.org
    add_action('wp_head', function() use ($country, $brand, $cur, $inco, $port, $email, $phone) {
        echo '<script type="application/ld+json">' . wp_json_encode([
            '@context'   => 'https://schema.org',
            '@type'      => 'WebPage',
            'name'       => "Laboratory Glassware Supplier in {$country} — {$brand}",
            'description'=> "Buy certified laboratory glassware from India. Export to {$country} via {$inco}. ISO 9001:2015 certified. 7000+ products. Get quote in 24 hours.",
            'inLanguage' => 'en',
            'provider'   => [
                '@type'   => 'Organization',
                'name'    => $brand,
                'address' => ['@type'=>'PostalAddress','addressLocality'=>'Ambala','addressCountry'=>'IN'],
                'email'   => $email,
                'telephone' => $phone,
                'areaServed'=> $country,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }, 5);

    ob_start(); ?>
<div class="sgclabs-export-page" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:900px;margin:0 auto">

  <!-- Hero -->
  <div style="background:linear-gradient(135deg,#1a1a2e 0%,#16213e 100%);border-radius:14px;padding:36px 32px;margin-bottom:24px;color:#fff">
    <div style="font-size:40px;margin-bottom:8px"><?php echo esc_html($flag); ?></div>
    <h1 style="margin:0 0 10px;font-size:28px;font-weight:800;color:#fff">Laboratory Glassware Supplier for <?php echo esc_html($country); ?></h1>
    <p style="margin:0 0 20px;font-size:15px;color:rgba(255,255,255,.75);max-width:600px">
      <?php echo esc_html($brand); ?> exports 7,000+ lab glassware products to <?php echo esc_html($country); ?>.
      ISO 9001:2015 certified. Factory-direct pricing. Dispatch within 15 days.
    </p>
    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <a href="<?php echo esc_url($rfq_url); ?>" style="background:#27ae60;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:700;display:inline-block">📋 Request Quotation</a>
      <a href="<?php echo esc_url($shop_url); ?>" style="background:rgba(255,255,255,.15);color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;display:inline-block">🔬 Browse Products</a>
    </div>
  </div>

  <!-- Export Info Grid -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:24px">
    <?php
    $info_cards = [
        ['📦','Incoterm', $inco],
        ['🚢','Export Port', $port],
        ['💱','Currency', $cur . ' accepted'],
        ['✅','Certification', 'ISO 9001:2015'],
        ['⏱️','Lead Time', '15-30 working days'],
        ['📊','Min. Order', 'As per product MOQ'],
    ];
    foreach ($info_cards as [$icon,$label,$val]): ?>
    <div style="background:#f8f9fa;border:1px solid #e5e7eb;border-radius:10px;padding:16px">
      <div style="font-size:22px;margin-bottom:6px"><?php echo $icon; ?></div>
      <div style="font-size:11px;color:#888;font-weight:500;text-transform:uppercase;letter-spacing:.04em"><?php echo esc_html($label); ?></div>
      <div style="font-size:14px;font-weight:600;color:#1a1a2e;margin-top:3px"><?php echo esc_html($val); ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Why SGC LABS -->
  <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;margin-bottom:20px">
    <h2 style="margin:0 0 16px;font-size:18px;color:#1a1a2e">Why <?php echo esc_html($country); ?> Buyers Choose <?php echo esc_html($brand); ?></h2>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <?php $points = [
        '🏭 Factory-direct' => 'No middlemen. Direct from our Ambala manufacturing plant — best FOB price guaranteed.',
        '✅ ISO 9001:2015'  => 'International quality certification. Products meet DIN, ASTM, and ISO standards.',
        '📦 7000+ Products' => 'Complete range — beakers, flasks, condensers, burettes, test tubes, culture vessels.',
        '🎨 Custom sizes'   => 'OEM and custom specifications available for bulk orders.',
        '💬 24hr response'  => 'Dedicated export sales team. Quote within 24 business hours.',
        '🌍 50+ countries'  => 'Trusted exporter. Shipped to ' . $country . ' and 50+ countries worldwide.',
      ];
      foreach ($points as $title => $desc): ?>
      <div style="display:flex;gap:10px;align-items:flex-start">
        <div style="font-size:20px;flex-shrink:0"><?php echo substr($title,0,2); ?></div>
        <div>
          <div style="font-size:12px;font-weight:600;color:#1a1a2e"><?php echo esc_html(trim(substr($title,3))); ?></div>
          <div style="font-size:11px;color:#666;line-height:1.5;margin-top:2px"><?php echo esc_html($desc); ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Import info -->
  <div style="background:#fff8e1;border:1px solid #fcd34d;border-radius:10px;padding:16px;margin-bottom:20px;font-size:13px">
    <strong>📋 Import Information for <?php echo esc_html($country); ?>:</strong>
    <?php echo esc_html($duty); ?>.
    Typical buyers in <?php echo esc_html($country); ?>: <?php echo esc_html($buyers); ?>.
    <a href="<?php echo esc_url($rfq_url); ?>" style="color:#d97706;font-weight:600">Contact us for a detailed export quotation →</a>
  </div>

  <!-- CTA -->
  <div style="text-align:center;background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #86efac;border-radius:12px;padding:28px">
    <div style="font-size:28px;margin-bottom:8px">📬</div>
    <h3 style="margin:0 0 8px;color:#065f46;font-size:18px">Get a Quote for <?php echo esc_html($country); ?></h3>
    <p style="margin:0 0 16px;color:#166534;font-size:13px">Send your product list — we respond within 24 business hours with FOB + CIF pricing.</p>
    <a href="<?php echo esc_url($rfq_url); ?>" style="background:#059669;color:#fff;padding:13px 28px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:700;display:inline-block">Request Export Quotation</a>
    <div style="margin-top:14px;font-size:12px;color:#166534">
      📧 <a href="mailto:<?php echo esc_attr($email); ?>" style="color:#059669"><?php echo esc_html($email); ?></a>
      &nbsp;|&nbsp; 📱 <?php echo esc_html($phone); ?>
    </div>
  </div>

</div>
<?php
    return ob_get_clean();
}

// ═══════════════════════════════════════════════════════════
// 4. WEBMASTER VERIFICATION META TAGS
// Baidu, Bing, Yandex, Pinterest, Norton
// ═══════════════════════════════════════════════════════════
add_action( 'wp_head', 'sgclabs_webmaster_verify_tags', 1 );
function sgclabs_webmaster_verify_tags() {
    $tags = [
        'sgclabs_baidu_verify'     => 'baidu-site-verification',
        'sgclabs_bing_verify'      => 'msvalidate.01',
        'sgclabs_yandex_verify'    => 'yandex-verification',
        'sgclabs_pinterest_verify' => 'p:domain_verify',
        'sgclabs_norton_verify'    => 'norton-safeweb-site-verification',
        'sgclabs_alexa_verify'     => 'alexaVerifyID',
    ];
    foreach ( $tags as $option => $meta_name ) {
        $val = get_option( $option, '' );
        if ( ! empty( $val ) ) {
            printf( '<meta name="%s" content="%s">' . "\n", esc_attr( $meta_name ), esc_attr( $val ) );
        }
    }
}

// Admin settings page for verification codes
add_action( 'admin_menu', 'sgclabs_webmaster_verify_menu', 99 );
function sgclabs_webmaster_verify_menu() {
    add_submenu_page(
        'sgclabs-seo-hub',
        'Webmaster Verification',
        '🔍 Webmaster Verify',
        'manage_options',
        'sgclabs-webmaster-verify',
        'sgclabs_webmaster_verify_page'
    );
}

function sgclabs_webmaster_verify_page() {
    if ( isset( $_POST['sgclabs_verify_save'] ) && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'sgclabs_verify_save' ) ) {
        $fields = [ 'sgclabs_baidu_verify', 'sgclabs_bing_verify', 'sgclabs_yandex_verify', 'sgclabs_pinterest_verify', 'sgclabs_norton_verify', 'sgclabs_alexa_verify' ];
        foreach ( $fields as $f ) update_option( $f, sanitize_text_field( wp_unslash( $_POST[$f] ?? '' ) ) );
        echo '<div class="notice notice-success"><p>✅ Verification codes saved.</p></div>';
    }
    ?>
    <div class="wrap">
    <h1>🔍 Webmaster Verification Codes</h1>
    <p style="color:#666;max-width:600px">Paste verification meta tag content values below (NOT the full meta tag — just the content="..." value). These appear in &lt;head&gt; on every page.</p>
    <form method="post">
    <?php wp_nonce_field('sgclabs_verify_save'); ?>
    <table class="form-table">
      <?php
      $verify_fields = [
          'sgclabs_baidu_verify'     => [ '🇨🇳 Baidu Webmaster',     'baidu-site-verification content value', 'baidu.com/site — China\'s #1 search engine. Critical for Chinese buyers.' ],
          'sgclabs_bing_verify'      => [ '🔷 Bing Webmaster Tools',  'msvalidate.01 content value',           'bing.com/webmasters — Also covers DuckDuckGo + Yahoo.' ],
          'sgclabs_yandex_verify'    => [ '🇷🇺 Yandex Webmaster',     'yandex-verification content value',     'webmaster.yandex.com — Russia/CIS buyers.' ],
          'sgclabs_pinterest_verify' => [ '📌 Pinterest',              'p:domain_verify content value',         'Enable rich pins for product images on Pinterest.' ],
          'sgclabs_norton_verify'    => [ '🛡 Norton Safe Web',       'norton-safeweb-site-verification value','Trust badge for enterprise buyers.' ],
          'sgclabs_alexa_verify'     => [ '📊 Alexa / Amazon',        'alexaVerifyID content value',           'Optional — legacy but some B2B buyers check it.' ],
      ];
      foreach ( $verify_fields as $opt => [$label, $placeholder, $help] ): ?>
      <tr>
        <th scope="row"><strong><?php echo esc_html($label); ?></strong></th>
        <td>
          <input type="text" name="<?php echo esc_attr($opt); ?>" value="<?php echo esc_attr(get_option($opt,'')); ?>" class="large-text" placeholder="<?php echo esc_attr($placeholder); ?>">
          <p class="description"><?php echo esc_html($help); ?></p>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php submit_button('💾 Save Verification Codes', 'primary', 'sgclabs_verify_save'); ?>
    </form>

    <h2 style="margin-top:32px">📋 How to get your Baidu verification code</h2>
    <ol style="max-width:600px;line-height:2;color:#555;font-size:13px">
      <li>Go to <a href="https://ziyuan.baidu.com" target="_blank" rel="noopener"><strong>ziyuan.baidu.com</strong></a> (Baidu Search Resources)</li>
      <li>Register/login with a Baidu account (free)</li>
      <li>Add your site → choose "HTML Tag" verification method</li>
      <li>Copy the <code>content="XXXX"</code> value → paste above</li>
      <li>Save → Verify in Baidu panel</li>
      <li>Submit sitemap: <code><?php echo esc_url(home_url('/sitemap.xml')); ?></code></li>
    </ol>
    </div>
    <?php
}

// ═══════════════════════════════════════════════════════════
// 5. BULK SEO HEALTH DASHBOARD
// ═══════════════════════════════════════════════════════════
add_action( 'wp_ajax_sgclabs_seo_health_scan', 'sgclabs_ajax_seo_health_scan' );
function sgclabs_ajax_seo_health_scan() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $offset     = intval( $_POST['offset'] ?? 0 );
    $batch_size = intval( $_POST['batch_size'] ?? 50 );

    $ids = get_posts( [
        'post_type'   => 'product',
        'post_status' => 'publish',
        'numberposts' => $batch_size,
        'offset'      => $offset,
        'fields'      => 'ids',
    ] );

    $results = [];
    foreach ( $ids as $id ) {
        $product = wc_get_product( $id );
        if ( ! $product ) continue;

        $score   = 0;
        $issues  = [];

        // 1. SEO title
        $title = get_post_meta($id,'rank_math_title',true) ?: get_post_meta($id,'_yoast_wpseo_title',true) ?: '';
        $tlen  = strlen($title);
        if ( $tlen >= 30 && $tlen <= 65 ) $score += 15;
        elseif ( empty($title) ) $issues[] = 'Missing SEO title';
        else $issues[] = 'SEO title length: ' . $tlen . ' chars (ideal 30-65)';

        // 2. Meta description
        $meta = get_post_meta($id,'rank_math_description',true) ?: get_post_meta($id,'_yoast_wpseo_metadesc',true) ?: '';
        $mlen = strlen($meta);
        if ( $mlen >= 100 && $mlen <= 160 ) $score += 15;
        elseif ( empty($meta) ) $issues[] = 'Missing meta description';
        else $issues[] = 'Meta desc length: ' . $mlen . ' (ideal 100-160)';

        // 3. Focus keyword
        $kw = get_post_meta($id,'rank_math_focus_keyword',true) ?: get_post_meta($id,'_yoast_wpseo_focuskw',true) ?: '';
        if ( $kw ) $score += 10; else $issues[] = 'Missing focus keyword';

        // 4. Image alt text
        $thumb = get_post_thumbnail_id($id);
        $alt   = $thumb ? get_post_meta($thumb,'_wp_attachment_image_alt',true) : '';
        if ( $alt ) $score += 10; else $issues[] = 'Missing product image alt text';

        // 5. FAQ schema
        $faq = get_post_meta($id,'_sgclabs_ai_faq',true);
        if ( $faq ) $score += 15; else $issues[] = 'No FAQ schema (run AI FAQ generator)';

        // 6. Reviews (AggregateRating)
        $reviews = $product->get_review_count();
        if ( $reviews > 0 ) $score += 10; else $issues[] = 'No product reviews';

        // 7. Content length
        $content = wp_strip_all_tags(get_post_field('post_content',$id));
        $wc = str_word_count($content);
        if ( $wc >= 200 ) $score += 10; else $issues[] = 'Content too short: ' . $wc . ' words (min 200)';

        // 8. HS Code (export signal)
        $hs = get_post_meta($id,'_sgcpm_hs_code',true) ?: (get_post_meta($id,'_sgclabs_b2b_pricing',true)['hs_code'] ?? '');
        if ( $hs ) $score += 10; else $issues[] = 'No HS Code set';

        // 9. Tech specs
        $specs = get_post_meta($id,'_sgclabs_tech_specs',true);
        if ( ! empty($specs) && is_array($specs) && count(array_filter($specs)) > 2 ) $score += 5; else $issues[] = 'Incomplete tech specs';

        // 10. Schema description (for JSON-LD)
        $sdesc = get_post_meta($id,'_sgcpm_schema_desc',true);
        if ( $sdesc ) $score += 5; else $issues[] = 'No schema.org description';

        $priority = $score < 40 ? 'critical' : ( $score < 70 ? 'medium' : 'good' );

        $results[] = [
            'id'       => $id,
            'name'     => $product->get_name(),
            'sku'      => $product->get_sku(),
            'score'    => $score,
            'priority' => $priority,
            'issues'   => $issues,
            'edit_url' => get_edit_post_link($id,''),
        ];
    }

    // Sort by score ascending (worst first)
    usort($results, function($a,$b){ return $a['score'] <=> $b['score']; });

    $total = wp_count_posts('product')->publish;
    wp_send_json_success([
        'results'  => $results,
        'offset'   => $offset + count($ids),
        'total'    => $total,
        'scanned'  => $offset + count($ids),
        'has_more' => count($ids) === $batch_size,
    ]);
}

// ═══════════════════════════════════════════════════════════
// 6. EXPORT OG TAGS (Worldwide exporter, HS code, FOB price)
// ═══════════════════════════════════════════════════════════
add_action( 'wp_head', 'sgclabs_export_og_tags', 3 );
function sgclabs_export_og_tags() {
    if ( ! is_product() ) return;
    if ( defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') ) return; // Let Yoast/RM handle OG
    if ( get_option('sgclabs_export_og_active','1') !== '1' ) return;

    global $post;
    $product = wc_get_product($post->ID);
    if (!$product) return;

    $name   = $product->get_name();
    $desc   = get_post_meta($post->ID,'_yoast_wpseo_metadesc',true) ?: wp_strip_all_tags(substr($product->get_short_description(),0,155));
    $img    = get_the_post_thumbnail_url($post->ID,'large') ?: '';
    $url    = get_permalink($post->ID);
    $price  = $product->get_regular_price();
    $cur    = get_woocommerce_currency();
    $brand  = get_option('sgclabs_company_name','SGC LABS®');
    $hs     = get_post_meta($post->ID,'_sgcpm_hs_code',true) ?: '';
    $b2b    = get_post_meta($post->ID,'_sgclabs_b2b_pricing',true);
    $inco   = $b2b['incoterm'] ?? get_option('sgclabs_default_incoterms','FOB New Delhi');

    $og_desc = $desc . ( $hs ? " HS Code: {$hs}." : '' ) . " {$inco}. Worldwide export. Get quote from {$brand}.";

    echo "\n<!-- SGC Export OG Tags -->\n";
    $tags = [
        'og:type'        => 'product',
        'og:title'       => $name . ' — ' . $brand,
        'og:description' => substr($og_desc,0,200),
        'og:url'         => $url,
        'og:image'       => $img,
        'og:site_name'   => get_bloginfo('name'),
        'og:locale'      => 'en_US',
        'product:price:amount'   => $price,
        'product:price:currency' => $cur,
        'product:availability'   => $product->is_in_stock() ? 'in stock' : 'out of stock',
        'twitter:card'        => 'summary_large_image',
        'twitter:title'       => $name . ' | ' . $brand . ' India',
        'twitter:description' => substr($og_desc,0,200),
        'twitter:image'       => $img,
    ];
    foreach ($tags as $p => $c) {
        if (!$c) continue;
        $attr = strpos($p,'twitter:')===0 ? 'name' : 'property';
        printf('<meta %s="%s" content="%s">%s', esc_attr($attr), esc_attr($p), esc_attr($c), "\n");
    }
}

// ═══════════════════════════════════════════════════════════
// 7. GOOGLE MERCHANT CENTER FEED
// URL: /sgclabs-gmc-feed.xml
// ═══════════════════════════════════════════════════════════
add_action('init', function() {
    add_rewrite_rule('^sgclabs-gmc-feed\.xml$','index.php?sgclabs_gmc_feed=1','top');
    add_filter('query_vars', function($v){ $v[]='sgclabs_gmc_feed'; return $v; });
});

add_action('template_redirect', function() {
    if (!get_query_var('sgclabs_gmc_feed')) return;
    if (get_option('sgclabs_gmc_feed_active','1') !== '1') { status_header(404); exit; }

    $brand    = get_option('sgclabs_company_name','SGC LABS®');
    $currency = get_woocommerce_currency();
    $country  = get_option('sgclabs_gmc_target_country','IN');

    $products = get_posts([
        'post_type'=>'product','post_status'=>'publish',
        'numberposts'=> intval(get_option('sgclabs_gmc_feed_limit',500)),
        'fields'=>'ids',
    ]);

    header('Content-Type: application/xml; charset=UTF-8');
    header('X-Robots-Tag: noindex');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
    echo '<channel>' . "\n";
    echo '<title>' . esc_html($brand) . ' — Google Merchant Feed</title>' . "\n";
    echo '<link>' . esc_url(home_url()) . '</link>' . "\n";
    echo '<description>Laboratory glassware export catalog</description>' . "\n";

    foreach ($products as $pid) {
        $product = wc_get_product($pid);
        if (!$product || !$product->is_in_stock()) continue;
        $name   = $product->get_name();
        $url    = get_permalink($pid);
        $img    = get_the_post_thumbnail_url($pid,'large') ?: '';
        $price  = $product->get_regular_price();
        $sku    = $product->get_sku() ?: 'SGC-'.$pid;
        $desc   = wp_strip_all_tags($product->get_short_description() ?: substr($product->get_description(),0,500));
        $cats   = implode(' > ', wp_get_post_terms($pid,'product_cat',['fields'=>'names']) ?: []);
        $hs     = get_post_meta($pid,'_sgcpm_hs_code',true) ?: '';
        $b2b    = get_post_meta($pid,'_sgclabs_b2b_pricing',true);
        $moq    = $b2b['moq_qty'] ?? 1;
        $alt    = $img ? get_post_meta(get_post_thumbnail_id($pid),'_wp_attachment_image_alt',true) : '';
        if (!$price || !$url) continue;
        echo '<item>' . "\n";
        echo '<g:id>' . esc_html($sku) . '</g:id>' . "\n";
        echo '<g:title>' . esc_html(substr($name,0,150)) . '</g:title>' . "\n";
        echo '<g:description>' . esc_html(substr($desc,0,5000)) . '</g:description>' . "\n";
        echo '<g:link>' . esc_url($url) . '</g:link>' . "\n";
        if ($img) echo '<g:image_link>' . esc_url($img) . '</g:image_link>' . "\n";
        echo '<g:price>' . esc_html(number_format((float)$price,2,'.','')) . ' ' . esc_html($currency) . '</g:price>' . "\n";
        echo '<g:availability>in_stock</g:availability>' . "\n";
        echo '<g:brand>' . esc_html($brand) . '</g:brand>' . "\n";
        echo '<g:mpn>' . esc_html($sku) . '</g:mpn>' . "\n";
        echo '<g:condition>new</g:condition>' . "\n";
        echo '<g:target_country>' . esc_attr($country) . '</g:target_country>' . "\n";
        if ($cats) echo '<g:product_type>' . esc_html($cats) . '</g:product_type>' . "\n";
        if ($hs)   echo '<g:hs_override>' . esc_html($hs) . '</g:hs_override>' . "\n";
        if ($moq>1) echo '<g:min_handling_time>' . intval($moq) . '</g:min_handling_time>' . "\n";
        if ($alt)  echo '<g:image_link_alt>' . esc_html($alt) . '</g:image_link_alt>' . "\n";
        echo '</item>' . "\n";
    }
    echo '</channel></rss>';
    exit;
});

// ═══════════════════════════════════════════════════════════
// 8. ADMIN: FAQ + SEO HEALTH in WooCommerce product editor
// ═══════════════════════════════════════════════════════════
add_action('add_meta_boxes_product', function() {
    add_meta_box('sgclabs_faq_box','🤖 AI FAQ + SEO Score','sgclabs_faq_meta_box_cb','product','side','high');
});

function sgclabs_faq_meta_box_cb($post) {
    $faq_raw = get_post_meta($post->ID,'_sgclabs_ai_faq',true);
    $faqs    = $faq_raw ? json_decode($faq_raw,true) : [];
    $count   = is_array($faqs) ? count($faqs) : 0;
    $nonce   = wp_create_nonce('sgclabs_nonce');
    ?>
    <div id="sgclabs-faq-box" style="font-size:12px">
      <div style="margin-bottom:10px">
        <?php if ($count > 0): ?>
          <span style="color:#059669;font-weight:600">✅ <?php echo $count; ?> FAQs saved</span> — FAQPage schema auto-injects on frontend.
        <?php else: ?>
          <span style="color:#dc2626;font-weight:600">❌ No FAQs</span> — Generate to get Google People Also Ask.
        <?php endif; ?>
      </div>
      <button type="button" class="button button-primary" id="btn-gen-faq-<?php echo $post->ID; ?>"
              onclick="sgclabsGenFAQ(<?php echo $post->ID; ?>, '<?php echo $nonce; ?>')">
        ✨ Generate AI FAQs
      </button>
      <div id="faq-result-<?php echo $post->ID; ?>" style="margin-top:8px;font-size:11px;color:#555"></div>
      <?php if ($count > 0): ?>
      <details style="margin-top:10px"><summary style="cursor:pointer;font-size:11px;color:#888">View <?php echo $count; ?> FAQs</summary>
        <?php foreach ($faqs as $i => $fq): ?>
        <div style="margin-top:8px;padding:6px 8px;background:#f9f9f9;border-radius:4px;border-left:3px solid #10b981">
          <div style="font-weight:600;font-size:11px"><?php echo esc_html($fq['q']??''); ?></div>
          <div style="color:#555;font-size:10px;margin-top:3px"><?php echo esc_html(substr($fq['a']??'',0,100)); ?>…</div>
        </div>
        <?php endforeach; ?>
      </details>
      <?php endif; ?>
    </div>
    <script>
    function sgclabsGenFAQ(pid, nonce) {
        var btn = document.getElementById('btn-gen-faq-'+pid);
        var res = document.getElementById('faq-result-'+pid);
        btn.disabled = true; btn.textContent = '⏳ Generating…';
        res.textContent = 'Calling AI — takes 10-20 seconds…';
        fetch(ajaxurl, {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'action=sgclabs_generate_product_faq&product_id='+pid+'&nonce='+nonce})
        .then(r=>r.json()).then(d=>{
            if(d.success){res.innerHTML='<span style="color:#059669">✅ '+d.data.count+' FAQs generated! Refresh to see them.</span>';}
            else{res.innerHTML='<span style="color:#dc2626">❌ '+d.data+'</span>';}
            btn.disabled=false; btn.textContent='✨ Generate AI FAQs';
        }).catch(e=>{res.textContent='Error: '+e;btn.disabled=false;});
    }
    </script>
    <?php
}
