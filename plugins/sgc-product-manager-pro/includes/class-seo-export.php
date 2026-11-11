<?php
/**
 * SGC Product Manager Pro — Export SEO Engine
 *
 * Organic search growth module for international export:
 *  1. Product schema.org (JSON-LD) — auto-generated per product
 *  2. FAQ schema — from AI-generated FAQ meta
 *  3. Breadcrumb schema — for all product pages
 *  4. Open Graph + Twitter Card — for social sharing
 *  5. Google Business / Exporter schema on homepage
 *  6. Sitemap priority boost — products with B2B pricing get priority 0.9
 *  7. Auto canonical — prevent duplicate content from WC filters
 *  8. hreflang x-default for en (no multi-lang needed, just x-default)
 *  9. Image SEO — AI alt text auto-applied to all gallery images
 * 10. Export-focused meta robots — product pages indexable, cart/checkout noindex
 *
 * @package SGC_PM
 * @since   3.0.3
 */
defined( 'ABSPATH' ) || exit;

class SGC_PM_SEO_Export {

    public static function init() {
        // Frontend hooks — only on public pages.
        // v3.0.9: when SGC Site Manager Pro is active it owns schema / OG / hreflang / canonical
        // output (it printed duplicates of every tag otherwise) — PM only adds image ALT + sitemap boosts.
        if ( ! is_admin() && defined( 'SGCLABS_BASE_VERSION' ) && ! defined( 'RANK_MATH_VERSION' ) && ! defined( 'WPSEO_VERSION' ) ) {
            add_action( 'wp_head', [ __CLASS__, 'inject_breadcrumb_only' ], 5 ); // Site Manager has no JSON-LD breadcrumbs
        }
        if ( ! is_admin() && ! defined( 'SGCLABS_BASE_VERSION' ) && ! defined( 'RANK_MATH_VERSION' ) && ! defined( 'WPSEO_VERSION' ) ) {
            add_action( 'wp_head', [ __CLASS__, 'inject_schema' ], 5 );
            add_action( 'wp_head', [ __CLASS__, 'inject_og_tags' ], 6 );
            add_action( 'wp_head', [ __CLASS__, 'inject_hreflang' ], 7 );
            add_action( 'wp_head', [ __CLASS__, 'inject_canonical' ], 8 );
            add_action( 'wp_head', [ __CLASS__, 'inject_meta_robots' ], 9 );
        }
        // Image SEO
        add_filter( 'woocommerce_product_get_image', [ __CLASS__, 'add_ai_alt' ], 10, 5 );
        // Sitemap priority
        add_filter( 'wpseo_sitemap_url',    [ __CLASS__, 'boost_sitemap_priority' ], 10, 2 );
        add_filter( 'rank_math/sitemap/url', [ __CLASS__, 'boost_sitemap_priority_rm' ], 10, 2 );
        // Admin AJAX
        add_action( 'wp_ajax_sgc_pm_generate_all_schema', [ __CLASS__, 'ajax_generate_all' ] );
        add_action( 'wp_ajax_sgc_pm_seo_audit',           [ __CLASS__, 'ajax_seo_audit' ] );
    }

    // ── Breadcrumb-only output (used when Site Manager Pro owns the rest of the head) ──
    public static function inject_breadcrumb_only() {
        if ( ! function_exists( 'is_product' ) || ! is_product() ) return;
        $product = wc_get_product( get_the_ID() );
        if ( ! $product ) return;
        $bc = self::breadcrumb_schema( $product ); // returns a ready <script type="application/ld+json"> block
        if ( is_string( $bc ) && $bc !== '' ) {
            echo $bc; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON-LD built from wp_json_encode()
        }
    }

    // ── 1. Schema.org JSON-LD injection ───────────────────────────────────────
    public static function inject_schema() {
        global $post;

        // Homepage — Organization + ExportService schema
        if ( is_front_page() || is_home() ) {
            echo self::organization_schema();
            return;
        }

        if ( ! is_product() || ! $post ) return;

        $product = wc_get_product( $post->ID );
        if ( ! $product ) return;

        $schemas = [];
        $schemas[] = self::product_schema( $product );

        // FAQ schema if AI-generated FAQs exist
        $faq_raw = get_post_meta( $post->ID, '_sgcpm_ai_faq', true );
        if ( $faq_raw ) {
            $faqs = json_decode( $faq_raw, true );
            if ( is_array( $faqs ) && ! empty( $faqs ) ) {
                $schemas[] = self::faq_schema( $faqs );
            }
        }

        // Breadcrumb schema
        $schemas[] = self::breadcrumb_schema( $product );

        foreach ( $schemas as $schema ) {
            if ( $schema ) echo $schema . "\n";
        }
    }

    private static function product_schema( WC_Product $product ): string {
        $id          = $product->get_id();
        $name        = $product->get_name();
        $sku         = $product->get_sku();
        $desc        = get_post_meta( $id, '_sgcpm_schema_desc', true )
                    ?: wp_strip_all_tags( $product->get_short_description() )
                    ?: wp_strip_all_tags( substr( $product->get_description(), 0, 300 ) );
        $url         = get_permalink( $id );
        $img         = get_the_post_thumbnail_url( $id, 'full' ) ?: '';
        $brand       = get_option( 'sgc_pm_ai_brand', 'SGC LABS®' );
        $origin      = get_option( 'sgclabs_country_of_origin', 'India' );
        $price       = $product->get_regular_price();
        $currency    = get_woocommerce_currency();
        $hs_code     = get_post_meta( $id, '_sgcpm_hs_code', true );
        $secondary   = get_post_meta( $id, '_sgcpm_secondary_kw', true );

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => $name,
            'description' => $desc,
            'url'         => $url,
            'sku'         => $sku,
            'mpn'         => $sku,
            'brand'       => [ '@type' => 'Brand', 'name' => $brand ],
            'manufacturer'=> [
                '@type'   => 'Organization',
                'name'    => $brand,
                'address' => [ '@type' => 'PostalAddress', 'addressCountry' => 'IN', 'addressLocality' => 'Ambala' ],
            ],
            'countryOfOrigin' => [ '@type' => 'Country', 'name' => $origin ],
        ];

        if ( $img ) $schema['image'] = [ '@type' => 'ImageObject', 'url' => $img ];

        if ( $price ) {
            $schema['offers'] = [
                '@type'         => 'Offer',
                'price'         => $price,
                'priceCurrency' => $currency,
                'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'url'           => $url,
                'seller'        => [ '@type' => 'Organization', 'name' => $brand ],
                'businessFunction' => 'https://purl.org/goodrelations/v1#Sell',
                'eligibleRegion'   => [ '@type' => 'Place', 'name' => 'Worldwide' ],
            ];
        }

        if ( $hs_code )   $schema['additionalProperty'][] = [ '@type' => 'PropertyValue', 'name' => 'HS Code', 'value' => $hs_code ];
        if ( $secondary ) $schema['keywords'] = $secondary;

        return '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . '</script>';
    }

    private static function faq_schema( array $faqs ): string {
        $items = [];
        foreach ( $faqs as $faq ) {
            if ( empty( $faq['q'] ) || empty( $faq['a'] ) ) continue;
            $items[] = [
                '@type'          => 'Question',
                'name'           => wp_strip_all_tags( $faq['q'] ),
                'acceptedAnswer' => [ '@type' => 'Answer', 'text' => wp_strip_all_tags( $faq['a'] ) ],
            ];
        }
        if ( empty( $items ) ) return '';
        return '<script type="application/ld+json">' . wp_json_encode( [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $items,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
    }

    private static function breadcrumb_schema( WC_Product $product ): string {
        $cats   = wp_get_post_terms( $product->get_id(), 'product_cat' );
        $items  = [ [ '@type' => 'ListItem', 'position' => 1, 'name' => get_bloginfo('name'), 'item' => home_url('/') ] ];
        $pos    = 2;
        if ( ! is_wp_error($cats) && ! empty($cats) ) {
            $cat    = $cats[0];
            $items[]= [ '@type' => 'ListItem', 'position' => $pos++, 'name' => $cat->name, 'item' => get_term_link($cat) ];
        }
        $items[]= [ '@type' => 'ListItem', 'position' => $pos, 'name' => $product->get_name(), 'item' => get_permalink($product->get_id()) ];
        return '<script type="application/ld+json">' . wp_json_encode( [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
    }

    private static function organization_schema(): string {
        $name    = get_option('sgclabs_company_name', get_bloginfo('name'));
        $url     = home_url('/');
        $logo    = get_option('sgclabs_company_logo','') ?: get_site_icon_url(512);
        $phone   = get_option('sgclabs_header_phone', '+91 999 641 6935');
        $email   = get_option('sgclabs_header_email', 'sales@labsglass.com');
        $founded = get_option('sgc_pm_founded_year', '1965');

        $org = [
            '@context'       => 'https://schema.org',
            '@type'          => [ 'Organization', 'ExportAction' ],
            'name'           => $name,
            'url'            => $url,
            'foundingDate'   => $founded,
            'description'    => 'India\'s leading laboratory glassware manufacturer and exporter. 7000+ products, worldwide export, ISO certified.',
            'address'        => [
                '@type'            => 'PostalAddress',
                'streetAddress'    => 'Scientific Instruments Area',
                'addressLocality'  => 'Ambala',
                'addressRegion'    => 'Haryana',
                'postalCode'       => '134003',
                'addressCountry'   => 'IN',
            ],
            'contactPoint'   => [
                '@type'       => 'ContactPoint',
                'telephone'   => $phone,
                'email'       => $email,
                'contactType' => 'sales',
                'areaServed'  => 'Worldwide',
            ],
            'sameAs'         => array_filter( [
                get_option('sgclabs_social_linkedin',''),
                get_option('sgclabs_social_facebook',''),
                get_option('sgclabs_social_twitter',''),
            ] ),
        ];
        if ( $logo ) $org['logo'] = [ '@type' => 'ImageObject', 'url' => $logo ];

        return '<script type="application/ld+json">' . wp_json_encode( $org, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . '</script>';
    }

    // ── 2. Open Graph + Twitter Card ──────────────────────────────────────────
    public static function inject_og_tags() {
        // Skip if Yoast/RankMath already handling
        if ( defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') ) return;
        if ( ! is_product() ) return;

        global $post;
        $product = wc_get_product( $post->ID );
        if ( ! $product ) return;

        $title = get_post_meta($post->ID,'_yoast_wpseo_title',true) ?: $product->get_name() . ' — ' . get_bloginfo('name');
        $desc  = get_post_meta($post->ID,'_yoast_wpseo_metadesc',true) ?: wp_strip_all_tags(substr($product->get_short_description(),0,155));
        $img   = get_the_post_thumbnail_url($post->ID,'large') ?: '';
        $url   = get_permalink($post->ID);

        echo "\n<!-- SGC PM Export SEO: Open Graph -->\n";
        foreach ( [
            'og:type'        => 'product',
            'og:title'       => $title,
            'og:description' => $desc,
            'og:url'         => $url,
            'og:image'       => $img,
            'og:site_name'   => get_bloginfo('name'),
            'og:locale'      => 'en_US',
            'product:price:amount'   => $product->get_regular_price(),
            'product:price:currency' => get_woocommerce_currency(),
            'twitter:card'        => 'summary_large_image',
            'twitter:title'       => $title,
            'twitter:description' => $desc,
            'twitter:image'       => $img,
        ] as $prop => $content ) {
            if ( $content ) {
                $attr = strpos($prop,'twitter:') === 0 ? 'name' : 'property';
                printf( '<meta %s="%s" content="%s">%s', esc_attr($attr), esc_attr($prop), esc_attr($content), "\n" );
            }
        }
    }

    // ── 3. hreflang x-default ────────────────────────────────────────────────
    public static function inject_hreflang() {
        if ( ! is_product() && ! is_product_category() ) return;
        $url = get_permalink();
        printf( '<link rel="alternate" hreflang="en" href="%s">%s', esc_url($url), "\n" );
        printf( '<link rel="alternate" hreflang="x-default" href="%s">%s', esc_url($url), "\n" );
    }

    // ── 4. Canonical ─────────────────────────────────────────────────────────
    public static function inject_canonical() {
        if ( defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') ) return;
        if ( ! is_product() && ! is_product_category() ) return;
        $canonical = get_permalink();
        if ( $canonical ) printf( '<link rel="canonical" href="%s">%s', esc_url($canonical), "\n" );
    }

    // ── 5. Meta robots ───────────────────────────────────────────────────────
    public static function inject_meta_robots() {
        if ( defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') ) return;
        if ( is_cart() || is_checkout() || is_account_page() || is_wc_endpoint_url() ) {
            echo '<meta name="robots" content="noindex,nofollow">' . "\n";
        }
    }

    // ── 6. Sitemap priority boost ─────────────────────────────────────────────
    public static function boost_sitemap_priority( $url_output, $url ) {
        if ( isset( $url['loc'] ) && strpos( $url['loc'], '/product/' ) !== false ) {
            $post_id = url_to_postid( $url['loc'] );
            if ( $post_id ) {
                $has_b2b = get_post_meta( $post_id, '_sgclabs_b2b_pricing', true );
                $has_seo = get_post_meta( $post_id, '_sgcpm_ai_faq', true );
                if ( $has_b2b || $has_seo ) {
                    $url_output = str_replace( '<priority>0.5</priority>', '<priority>0.9</priority>', $url_output );
                    $url_output = str_replace( '<changefreq>weekly</changefreq>', '<changefreq>daily</changefreq>', $url_output );
                }
            }
        }
        return $url_output;
    }

    public static function boost_sitemap_priority_rm( $url, $post_id ) {
        if ( get_post_type($post_id) === 'product' ) {
            $has_b2b = get_post_meta($post_id,'_sgclabs_b2b_pricing',true);
            $has_seo = get_post_meta($post_id,'_sgcpm_ai_faq',true);
            if ( $has_b2b || $has_seo ) $url['priority'] = '0.9';
        }
        return $url;
    }

    // ── 7. AI alt text on WC product images ───────────────────────────────────
    public static function add_ai_alt( $html, $product, $size, $attr, $placeholder ) {
        if ( ! $product || $placeholder ) return $html;
        $ai_alt = get_post_meta( $product->get_id(), '_sgcpm_ai_alt_text', true );
        if ( $ai_alt && strpos($html,'alt="') !== false ) {
            $html = preg_replace( '/alt="[^"]*"/', 'alt="' . esc_attr($ai_alt) . '"', $html, 1 );
        }
        return $html;
    }

    // ── 8. Bulk generate schema for all products ──────────────────────────────
    public static function ajax_generate_all() {
        check_ajax_referer('sgc_pm_nonce','nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('Unauthorized');

        $args = [
            'post_type'   => 'product',
            'post_status' => 'publish',
            'numberposts' => intval($_POST['batch_size'] ?? 20),
            'offset'      => intval($_POST['offset'] ?? 0),
            'fields'      => 'ids',
        ];
        $ids = get_posts($args);
        $done = 0;
        foreach ( $ids as $id ) {
            // Auto-apply AI alt to all gallery images if missing
            $alt = get_post_meta($id,'_sgcpm_ai_alt_text',true);
            if ( $alt ) {
                $thumb = get_post_thumbnail_id($id);
                if ($thumb && !get_post_meta($thumb,'_wp_attachment_image_alt',true)) {
                    update_post_meta($thumb,'_wp_attachment_image_alt',$alt);
                }
                $gallery = get_post_meta($id,'_product_image_gallery',true);
                if ($gallery) {
                    $gids = explode(',', $gallery);
                    foreach ($gids as $gid) {
                        $gid = intval($gid);
                        if ($gid && !get_post_meta($gid,'_wp_attachment_image_alt',true)) {
                            update_post_meta($gid,'_wp_attachment_image_alt', $alt . ' — ' . get_the_title($id));
                        }
                    }
                }
            }
            $done++;
        }
        wp_send_json_success([
            'done'    => $done,
            'offset'  => intval($_POST['offset'] ?? 0) + $done,
            'message' => "{$done} products processed.",
        ]);
    }

    // ── 9. SEO audit for a product ────────────────────────────────────────────
    public static function ajax_seo_audit() {
        check_ajax_referer('sgc_pm_nonce','nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('Unauthorized');
        $id = intval($_POST['product_id'] ?? 0);
        if (!$id) wp_send_json_error('Invalid ID');

        $product = wc_get_product($id);
        if (!$product) wp_send_json_error('Product not found');

        $checks = [];
        $score  = 0;

        // Title
        $title = get_post_meta($id,'rank_math_title',true) ?: get_post_meta($id,'_yoast_wpseo_title',true) ?: '';
        $checks[] = ['label'=>'SEO Title','ok'=>strlen($title)>=30&&strlen($title)<=65,'val'=>$title?strlen($title).' chars':'Missing','weight'=>15];

        // Meta description
        $meta = get_post_meta($id,'rank_math_description',true) ?: get_post_meta($id,'_yoast_wpseo_metadesc',true) ?: '';
        $checks[] = ['label'=>'Meta Description','ok'=>strlen($meta)>=100&&strlen($meta)<=160,'val'=>$meta?strlen($meta).' chars':'Missing','weight'=>15];

        // Focus keyword
        $kw = get_post_meta($id,'rank_math_focus_keyword',true) ?: get_post_meta($id,'_yoast_wpseo_focuskw',true) ?: '';
        $checks[] = ['label'=>'Focus Keyword','ok'=>!empty($kw),'val'=>$kw?:'Missing','weight'=>10];

        // Alt text
        $thumb = get_post_thumbnail_id($id);
        $alt   = $thumb ? get_post_meta($thumb,'_wp_attachment_image_alt',true) : '';
        $checks[] = ['label'=>'Image Alt Text','ok'=>!empty($alt),'val'=>$alt?:'Missing','weight'=>10];

        // FAQ schema
        $faq = get_post_meta($id,'_sgcpm_ai_faq',true);
        $checks[] = ['label'=>'FAQ Schema (Rich Result)','ok'=>!empty($faq),'val'=>$faq?count(json_decode($faq,true)).' FAQs':'Missing — run AI Generate','weight'=>15];

        // Schema description
        $sdesc = get_post_meta($id,'_sgcpm_schema_desc',true);
        $checks[] = ['label'=>'Schema.org Description','ok'=>!empty($sdesc),'val'=>$sdesc?'Set':'Missing','weight'=>10];

        // B2B pricing (export intent signal)
        $b2b = get_post_meta($id,'_sgclabs_b2b_pricing',true);
        $checks[] = ['label'=>'B2B Export Pricing','ok'=>!empty($b2b),'val'=>$b2b?'Set':'Not set','weight'=>10];

        // Content length
        $content = wp_strip_all_tags(get_post_field('post_content',$id));
        $wc = str_word_count($content);
        $checks[] = ['label'=>'Content Length','ok'=>$wc>=200,'val'=>$wc.' words','weight'=>10];

        // HS Code
        $hs = get_post_meta($id,'_sgcpm_hs_code',true);
        $checks[] = ['label'=>'HS Code (Export)','ok'=>!empty($hs),'val'=>$hs?:'Not set','weight'=>5];

        foreach ($checks as $c) { if ($c['ok']) $score += $c['weight']; }

        wp_send_json_success(['checks'=>$checks,'score'=>$score,'product'=>$product->get_name()]);
    }
}

// Auto-register
add_action('plugins_loaded', ['SGC_PM_SEO_Export','init'], 20);
