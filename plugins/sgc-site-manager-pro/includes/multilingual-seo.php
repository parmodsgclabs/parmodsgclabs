<?php
if (!defined('ABSPATH')) exit;

// ============================================================
// SGC LABS® MULTILINGUAL SEO ENGINE
// Auto-generates in 11 languages:
// 1. Product Title
// 2. Meta Description
// 3. URL Slug (language prefix)
// 4. Alt Tags on Images
// 5. Schema Keywords
// 6. hreflang tags
// 7. Multilingual Sitemap
// ============================================================

// --- LANGUAGE CONFIG ---
function sgclabs_get_languages() {
    return [
        'en' => ['name'=>'English',    'flag'=>'🇬🇧', 'prefix'=>'en', 'dir'=>'ltr', 'locale'=>'en_US', 'hreflang'=>'en'],
        'hi' => ['name'=>'Hindi',      'flag'=>'🇮🇳', 'prefix'=>'hi', 'dir'=>'ltr', 'locale'=>'hi_IN', 'hreflang'=>'hi-IN'],
        'ar' => ['name'=>'Arabic',     'flag'=>'🇸🇦', 'prefix'=>'ar', 'dir'=>'rtl', 'locale'=>'ar_SA', 'hreflang'=>'ar-SA'],
        'de' => ['name'=>'German',     'flag'=>'🇩🇪', 'prefix'=>'de', 'dir'=>'ltr', 'locale'=>'de_DE', 'hreflang'=>'de-DE'],
        'fr' => ['name'=>'French',     'flag'=>'🇫🇷', 'prefix'=>'fr', 'dir'=>'ltr', 'locale'=>'fr_FR', 'hreflang'=>'fr-FR'],
        'es' => ['name'=>'Spanish',    'flag'=>'🇪🇸', 'prefix'=>'es', 'dir'=>'ltr', 'locale'=>'es_ES', 'hreflang'=>'es-ES'],
        'zh' => ['name'=>'Chinese',    'flag'=>'🇨🇳', 'prefix'=>'zh', 'dir'=>'ltr', 'locale'=>'zh_CN', 'hreflang'=>'zh-CN'],
        'ja' => ['name'=>'Japanese',   'flag'=>'🇯🇵', 'prefix'=>'ja', 'dir'=>'ltr', 'locale'=>'ja_JP', 'hreflang'=>'ja'],
        'ko' => ['name'=>'Korean',     'flag'=>'🇰🇷', 'prefix'=>'ko', 'dir'=>'ltr', 'locale'=>'ko_KR', 'hreflang'=>'ko'],
        'pt' => ['name'=>'Portuguese', 'flag'=>'🇧🇷', 'prefix'=>'pt', 'dir'=>'ltr', 'locale'=>'pt_BR', 'hreflang'=>'pt-BR'],
        'ru' => ['name'=>'Russian',    'flag'=>'🇷🇺', 'prefix'=>'ru', 'dir'=>'ltr', 'locale'=>'ru_RU', 'hreflang'=>'ru'],
    ];
}

// --- TRANSLATION DICTIONARY ---
function sgclabs_get_full_dictionary() {
    return [
        // Action words
        'buy'             => ['en'=>'Buy','hi'=>'खरीदें','ar'=>'اشتري','de'=>'Kaufen','fr'=>'Acheter','es'=>'Comprar','zh'=>'购买','ja'=>'購入','ko'=>'구매','pt'=>'Comprar','ru'=>'Купить'],
        'order'           => ['en'=>'Order','hi'=>'ऑर्डर करें','ar'=>'اطلب','de'=>'Bestellen','fr'=>'Commander','es'=>'Pedir','zh'=>'订购','ja'=>'注文','ko'=>'주문','pt'=>'Pedir','ru'=>'Заказать'],
        'wholesale'       => ['en'=>'Wholesale','hi'=>'थोक','ar'=>'بالجملة','de'=>'Großhandel','fr'=>'Gros','es'=>'Mayoreo','zh'=>'批发','ja'=>'卸売り','ko'=>'도매','pt'=>'Atacado','ru'=>'Оптом'],
        'supplier'        => ['en'=>'Supplier','hi'=>'आपूर्तिकर्ता','ar'=>'مورد','de'=>'Lieferant','fr'=>'Fournisseur','es'=>'Proveedor','zh'=>'供应商','ja'=>'サプライヤー','ko'=>'공급업체','pt'=>'Fornecedor','ru'=>'Поставщик'],
        'manufacturer'    => ['en'=>'Manufacturer','hi'=>'निर्माता','ar'=>'مصنّع','de'=>'Hersteller','fr'=>'Fabricant','es'=>'Fabricante','zh'=>'制造商','ja'=>'製造業者','ko'=>'제조업체','pt'=>'Fabricante','ru'=>'Производитель'],
        'price'           => ['en'=>'Price','hi'=>'मूल्य','ar'=>'السعر','de'=>'Preis','fr'=>'Prix','es'=>'Precio','zh'=>'价格','ja'=>'価格','ko'=>'가격','pt'=>'Preço','ru'=>'Цена'],
        'quality'         => ['en'=>'Quality','hi'=>'गुणवत्ता','ar'=>'الجودة','de'=>'Qualität','fr'=>'Qualité','es'=>'Calidad','zh'=>'质量','ja'=>'品質','ko'=>'품질','pt'=>'Qualidade','ru'=>'Качество'],
        'best'            => ['en'=>'Best','hi'=>'सर्वश्रेष्ठ','ar'=>'أفضل','de'=>'Beste','fr'=>'Meilleur','es'=>'Mejor','zh'=>'最好的','ja'=>'最高の','ko'=>'최고의','pt'=>'Melhor','ru'=>'Лучший'],
        'online'          => ['en'=>'Online','hi'=>'ऑनलाइन','ar'=>'عبر الإنترنت','de'=>'Online','fr'=>'En ligne','es'=>'En línea','zh'=>'在线','ja'=>'オンライン','ko'=>'온라인','pt'=>'Online','ru'=>'Онлайн'],
        'export'          => ['en'=>'Export','hi'=>'निर्यात','ar'=>'تصدير','de'=>'Export','fr'=>'Export','es'=>'Exportación','zh'=>'出口','ja'=>'輸出','ko'=>'수출','pt'=>'Exportação','ru'=>'Экспорт'],
        // Lab terms
        'laboratory'      => ['en'=>'Laboratory','hi'=>'प्रयोगशाला','ar'=>'مختبر','de'=>'Labor','fr'=>'Laboratoire','es'=>'Laboratorio','zh'=>'实验室','ja'=>'実験室','ko'=>'실험실','pt'=>'Laboratório','ru'=>'Лаборатория'],
        'glassware'       => ['en'=>'Glassware','hi'=>'कांच के बर्तन','ar'=>'أدوات زجاجية','de'=>'Glaswaren','fr'=>'Verrerie','es'=>'Cristalería','zh'=>'玻璃器皿','ja'=>'ガラス器具','ko'=>'유리 기구','pt'=>'Vidraria','ru'=>'Стеклянная посуда'],
        'scientific'      => ['en'=>'Scientific','hi'=>'वैज्ञानिक','ar'=>'علمي','de'=>'Wissenschaftlich','fr'=>'Scientifique','es'=>'Científico','zh'=>'科学','ja'=>'科学的','ko'=>'과학적','pt'=>'Científico','ru'=>'Научный'],
        'borosilicate'    => ['en'=>'Borosilicate','hi'=>'बोरोसिलिकेट','ar'=>'البورسليكات','de'=>'Borosilikat','fr'=>'Borosilicate','es'=>'Borosilicato','zh'=>'硼硅酸盐','ja'=>'ホウケイ酸','ko'=>'붕규산','pt'=>'Borossilicato','ru'=>'Боросиликат'],
        'equipment'       => ['en'=>'Equipment','hi'=>'उपकरण','ar'=>'معدات','de'=>'Ausrüstung','fr'=>'Équipement','es'=>'Equipo','zh'=>'设备','ja'=>'機器','ko'=>'장비','pt'=>'Equipamento','ru'=>'Оборудование'],
        'india'           => ['en'=>'India','hi'=>'भारत','ar'=>'الهند','de'=>'Indien','fr'=>'Inde','es'=>'India','zh'=>'印度','ja'=>'インド','ko'=>'인도','pt'=>'Índia','ru'=>'Индия'],
        // Description phrases
        'desc_quality'    => ['en'=>'Premium quality','hi'=>'उच्च गुणवत्ता','ar'=>'جودة عالية','de'=>'Premium-Qualität','fr'=>'Qualité premium','es'=>'Calidad premium','zh'=>'优质','ja'=>'プレミアム品質','ko'=>'프리미엄 품질','pt'=>'Qualidade premium','ru'=>'Высокое качество'],
        'desc_since'      => ['en'=>'Trusted since 2003','hi'=>'2003 से विश्वसनीय','ar'=>'موثوق منذ 2003','de'=>'Vertrauenswürdig seit 2003','fr'=>'De confiance depuis 2003','es'=>'De confianza desde 2003','zh'=>'自2003年以来值得信赖','ja'=>'2003年から信頼','ko'=>'2003년부터 신뢰','pt'=>'Confiável desde 2003','ru'=>'Надёжный с 2003 года'],
        'desc_export'     => ['en'=>'Worldwide export from India','hi'=>'भारत से विश्वव्यापी निर्यात','ar'=>'تصدير عالمي من الهند','de'=>'Weltweiter Export aus Indien','fr'=>'Export mondial depuis l\'Inde','es'=>'Exportación mundial desde India','zh'=>'从印度全球出口','ja'=>'インドから世界輸出','ko'=>'인도에서 전 세계 수출','pt'=>'Exportação mundial da Índia','ru'=>'Мировой экспорт из Индии'],
        'desc_order'      => ['en'=>'Order now for fast delivery','hi'=>'तेज़ डिलीवरी के लिए अभी ऑर्डर करें','ar'=>'اطلب الآن للتسليم السريع','de'=>'Jetzt für schnelle Lieferung bestellen','fr'=>'Commandez maintenant pour livraison rapide','es'=>'Pida ahora para entrega rápida','zh'=>'立即订购快速发货','ja'=>'今すぐ注文で迅速配送','ko'=>'빠른 배송을 위해 지금 주문','pt'=>'Peça agora para entrega rápida','ru'=>'Заказывайте сейчас для быстрой доставки'],
        'from'            => ['en'=>'from','hi'=>'से','ar'=>'من','de'=>'von','fr'=>'de','es'=>'de','zh'=>'来自','ja'=>'から','ko'=>'에서','pt'=>'de','ru'=>'из'],
        'in'              => ['en'=>'in','hi'=>'में','ar'=>'في','de'=>'in','fr'=>'à','es'=>'en','zh'=>'在','ja'=>'での','ko'=>'에서','pt'=>'em','ru'=>'в'],
    ];
}

// --- CORE: TRANSLATE A WORD ---
function sgclabs_translate($key, $lang) {
    $dict = sgclabs_get_full_dictionary();
    return $dict[$key][$lang] ?? $dict[$key]['en'] ?? $key;
}

// --- GENERATE ALL MULTILINGUAL SEO DATA FOR A PRODUCT ---
function sgclabs_generate_multilingual_seo($product_id) {
    if (!function_exists('wc_get_product')) return false;
    $product = wc_get_product($product_id);
    if (!$product) return false;

    $name     = $product->get_name();
    $desc     = wp_strip_all_tags($product->get_short_description() ?: $product->get_description());
    $desc     = substr($desc, 0, 150);
    $cats     = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']);
    $cat      = !empty($cats) ? $cats[0] : 'Laboratory Equipment';
    $price    = $product->get_price();
    $sku      = $product->get_sku();
    $in_stock = $product->is_in_stock() ? 'InStock' : 'OutOfStock';
    $images   = $product->get_gallery_image_ids();
    array_unshift($images, $product->get_image_id());
    $base_url = get_permalink($product_id);
    $langs    = sgclabs_get_languages();

    // Get custom locations from DB
    $locations = function_exists('sgclabs_get_custom_locations')
        ? sgclabs_get_custom_locations()
        : ['Ambala'=>'India','Delhi'=>'India','Dubai'=>'UAE'];

    $result = [];

    foreach ($langs as $lang_code => $lang_info) {
        $buy   = sgclabs_translate('buy', $lang_code);
        $lab   = sgclabs_translate('laboratory', $lang_code);
        $glass = sgclabs_translate('glassware', $lang_code);
        $sci   = sgclabs_translate('scientific', $lang_code);
        $boros = sgclabs_translate('borosilicate', $lang_code);
        $sup   = sgclabs_translate('supplier', $lang_code);
        $mfr   = sgclabs_translate('manufacturer', $lang_code);
        $india = sgclabs_translate('india', $lang_code);
        $qual  = sgclabs_translate('desc_quality', $lang_code);
        $since = sgclabs_translate('desc_since', $lang_code);
        $expo  = sgclabs_translate('desc_export', $lang_code);
        $order = sgclabs_translate('desc_order', $lang_code);
        $from  = sgclabs_translate('from', $lang_code);
        $in    = sgclabs_translate('in', $lang_code);
        $price_word = sgclabs_translate('price', $lang_code);
        $whole = sgclabs_translate('wholesale', $lang_code);
        $best  = sgclabs_translate('best', $lang_code);
        $equip = sgclabs_translate('equipment', $lang_code);

        // --- 1. MULTILINGUAL TITLE ---
        $titles = [
            "{$buy} {$name} | SGC LABS® {$india}",
            "{$name} - {$qual} | SGC LABS®",
            "{$best} {$name} {$sup} | SGC LABS®",
        ];
        $primary_title = substr($titles[0], 0, 60) ?: "{$name} | SGC LABS®";

        // --- 2. MULTILINGUAL META DESCRIPTION ---
        if ($desc) {
            $meta_desc = "{$qual} {$name}. {$expo}. {$since}. {$order}.";
        } else {
            $meta_desc = "{$qual} {$name} - {$lab} {$glass} {$from} SGC LABS® {$india}. {$since}. {$expo}.";
        }
        $meta_desc = substr($meta_desc, 0, 160);

        // --- 3. URL SLUG (language prefix) ---
        $slug_name  = sanitize_title($name);
        $lang_url   = home_url("/{$lang_code}/product/{$slug_name}/");
        // hreflang URL (virtual - for sitemap/tags)
        $hreflang_url = add_query_arg('lang', $lang_code, $base_url);

        // --- 4. ALT TAGS for Images ---
        $alt_tags = [];
        foreach (array_slice($images, 0, 5) as $i => $img_id) {
            if (!$img_id) continue;
            $alt_tags[$img_id] = [
                'alt'   => "{$name} - {$lab} {$glass} | SGC LABS® {$india}",
                'title' => "{$buy} {$name} {$from} SGC LABS®",
            ];
        }

        // --- 5. SCHEMA KEYWORDS (multilingual) ---
        $schema_keywords = [
            $name,
            "{$name} {$sup}",
            "{$lab} {$name}",
            "{$boros} {$name}",
            "{$sci} {$name}",
            "{$buy} {$name} {$india}",
            "{$name} {$mfr} {$india}",
            "{$whole} {$name}",
            "{$best} {$name} {$price_word}",
            "SGC LABS {$name}",
        ];

        // Location-based keywords
        $loc_keywords = [];
        foreach (array_slice($locations, 0, 10, true) as $city => $country) {
            $loc_keywords[] = "{$name} {$sup} {$city}";
            $loc_keywords[] = "{$buy} {$name} {$in} {$country}";
        }

        $all_keywords = array_merge($schema_keywords, array_slice($loc_keywords, 0, 10));

        // --- 6. FULL SCHEMA (multilingual) ---
        $schema = [
            "@context"    => "https://schema.org/",
            "@type"       => "Product",
            "name"        => $name,
            "description" => $meta_desc,
            "sku"         => $sku ?: "SGCLABS-{$product_id}",
            "keywords"    => implode(', ', array_unique($all_keywords)),
            "inLanguage"  => $lang_code,
            "brand"       => ["@type" => "Brand", "name" => "SGC LABS®"],
            "manufacturer"=> [
                "@type"   => "Organization",
                "name"    => "SGC LABS®",
                "url"     => "https://labsglass.com",
                "address" => [
                    "@type"           => "PostalAddress",
                    "addressLocality" => "Ambala Cantt",
                    "addressRegion"   => "Haryana",
                    "addressCountry"  => "IN",
                    "postalCode"      => "133001"
                ],
                "telephone" => "+91 999 641 6935",
                "email"     => "sales@labsglass.com"
            ],
            "offers" => [
                "@type"         => "Offer",
                "url"           => $base_url,
                "priceCurrency" => "INR",
                "price"         => $price ?: "0",
                "availability"  => "https://schema.org/{$in_stock}",
                "seller"        => ["@type"=>"Organization","name"=>"SGC LABS®"],
                "shippingDetails"=> [
                    "@type"           => "OfferShippingDetails",
                    "shippingDestination" => ["@type"=>"DefinedRegion","addressCountry"=>"WORLDWIDE"],
                ]
            ],
            "category" => $cat,
        ];

        // Add image to schema
        if (!empty($images[0])) {
            $img_url = wp_get_attachment_image_url($images[0], 'large');
            if ($img_url) $schema['image'] = $img_url;
        }

        // --- 7. OG TAGS (multilingual) ---
        $og_tags = [
            'og:title'       => $primary_title,
            'og:description' => $meta_desc,
            'og:url'         => $base_url,
            'og:type'        => 'product',
            'og:locale'      => $lang_info['locale'],
            'og:site_name'   => 'SGC LABS®',
            'product:price:amount'   => $price ?: '',
            'product:price:currency' => 'INR',
        ];
        if (!empty($images[0])) {
            $img_url = wp_get_attachment_image_url($images[0], 'large');
            if ($img_url) $og_tags['og:image'] = $img_url;
        }

        $result[$lang_code] = [
            'lang_name'    => $lang_info['name'],
            'flag'         => $lang_info['flag'],
            'dir'          => $lang_info['dir'],
            'locale'       => $lang_info['locale'],
            'titles'       => $titles,
            'primary_title'=> $primary_title,
            'meta_desc'    => $meta_desc,
            'slug'         => $slug_name,
            'lang_url'     => $lang_url,
            'hreflang_url' => $hreflang_url,
            'alt_tags'     => $alt_tags,
            'keywords'     => array_unique($all_keywords),
            'schema'       => $schema,
            'og_tags'      => $og_tags,
        ];
    }

    return $result;
}

// --- INJECT MULTILINGUAL SEO ON PRODUCT PAGES ---
add_action('wp_head', 'sgclabs_inject_multilingual_seo', 4);
function sgclabs_inject_multilingual_seo(){
    // Hreflang — safe to run alongside RankMath (RM doesn't output hreflang by default)
    // Only skip if RM hreflang module is explicitly enabled
    if ( defined('RANK_MATH_VERSION') && get_option('rank_math_modules_multilingual') ) return;
    if (get_option('sgclabs_multilingual_seo_active', '1') !== '1') return;

    // v6.6.5: Fire on products, product categories, shop, AND posts/pages (expanded scope)
    $is_target = is_singular() || is_front_page();
    if ( function_exists('is_product_category') ) {
        $is_target = $is_target || is_product_category();
    }
    if ( function_exists('is_shop') ) {
        $is_target = $is_target || is_shop();
    }
    if ( ! $is_target ) return;

    $post_id = get_the_ID();

    // Fix: front page pe get_the_ID() = 0 hota hai agar static page set hai
    if ( ! $post_id && is_front_page() ) {
        $post_id = intval( get_option( 'page_on_front' ) );
    }

    // hreflang base URL — permalink ya home_url fallback
    $hreflang_base = $post_id ? get_permalink( $post_id ) : home_url( '/' );

    $lang  = isset($_GET['lang']) ? sanitize_text_field($_GET['lang']) : 'en';
    $langs = sgclabs_get_languages();
    if (!isset($langs[$lang])) $lang = 'en';

    // For non-product pages, only output hreflang (no AI translation)
    $is_product = function_exists('is_product') && is_product();
    // v7.5.1: with Rank Math / Yoast active only hreflang is ours — description, OG and Product schema come from the SEO plugin
    $seo_plugin = defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION') || defined('AIOSEO_VERSION') || defined('SEOPRESS_VERSION');

    if ($is_product && ! $seo_plugin) {
        // Check cache
        $cache_key  = "_sgclabs_multilang_seo_{$lang}";
        $cached     = get_post_meta($post_id, $cache_key, true);
        $cache_time = get_post_meta($post_id, "_sgclabs_multilang_time", true);

        if ($cached && $cache_time && (time() - $cache_time < 86400 * 7)) {
            $all_seo = $cached;
        } else {
            $all_seo = sgclabs_generate_multilingual_seo($post_id);
            if ($all_seo) {
                foreach ($all_seo as $lc => $data) {
                    update_post_meta($post_id, "_sgclabs_multilang_seo_{$lc}", $all_seo);
                }
                update_post_meta($post_id, "_sgclabs_multilang_time", time());
            }
        }

        if ($all_seo && isset($all_seo[$lang])) {
            $seo = $all_seo[$lang];

            // Inject primary title + meta
            echo "<!-- SGC LABS® Multilingual SEO Engine | Lang: {$lang} -->\n";
            echo "<meta name='description' content='" . esc_attr($seo['meta_desc']) . "'>\n";
            echo "<meta name='keywords' content='" . esc_attr(implode(', ', array_slice($seo['keywords'], 0, 20))) . "'>\n";

            // RTL support for Arabic
            if ($seo['dir'] === 'rtl') {
                echo "<style>body{direction:rtl;}</style>\n";
            }

            // OG Tags
            foreach ($seo['og_tags'] as $prop => $val) {
                if ($val) echo "<meta property='" . esc_attr($prop) . "' content='" . esc_attr($val) . "'>\n";
            }

            // Schema (multilingual)
            echo "<script type='application/ld+json'>" . wp_json_encode($seo['schema'], JSON_UNESCAPED_UNICODE) . "</script>\n";
        }
    }

    // v6.6.5: hreflang tags for ALL languages — now uses proper BCP 47 codes
    // Fires on products, posts, pages — not just WooCommerce
    // v7.4.7: uses $hreflang_base (handles front page $post_id = 0 case)
    echo "<!-- SGC LABS® hreflang tags (BCP 47) -->\n";
    foreach ($langs as $lc => $linfo) {
        $hreflang_code = $linfo['hreflang'] ?? $lc;
        $url = add_query_arg('lang', $lc, $hreflang_base);
        echo "<link rel='alternate' hreflang='" . esc_attr($hreflang_code) . "' href='" . esc_url($url) . "'>\n";
    }
    echo "<link rel='alternate' hreflang='x-default' href='" . esc_url($hreflang_base) . "'>\n";
}

// --- AUTO ALT TAGS ON PRODUCT IMAGES ---
add_filter('woocommerce_product_get_image', 'sgclabs_inject_alt_tags', 10, 3);
function sgclabs_inject_alt_tags($image, $product, $size) {
    if (!$product) return $image;
    $name  = $product->get_name();
    $india = 'India';
    $alt   = esc_attr("Buy {$name} - Laboratory Glassware | SGC LABS® {$india}");
    // Replace existing alt with our optimized alt
    $image = preg_replace('/alt="[^"]*"/', "alt=\"{$alt}\"", $image);
    return $image;
}

// --- MULTILINGUAL SITEMAP GENERATOR ---
add_action('wp_ajax_sgclabs_generate_sitemap', 'sgclabs_generate_sitemap_ajax');
add_action('wp_ajax_nopriv_sgclabs_generate_sitemap', 'sgclabs_generate_sitemap_nopriv');
function sgclabs_generate_sitemap_nopriv() { wp_die('Unauthorized'); }

function sgclabs_generate_sitemap_ajax() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');

    $langs    = sgclabs_get_languages();
    $products = get_posts(['post_type'=>'product','numberposts'=>-1,'post_status'=>'publish','fields'=>'ids']);
    $pages    = get_posts(['post_type'=>'page','numberposts'=>-1,'post_status'=>'publish','fields'=>'ids']);

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
    $xml .= '        xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

    // Homepage
    $xml .= "<url>\n<loc>" . esc_url(home_url('/')) . "</loc>\n";
    foreach ($langs as $lc => $linfo) {
        $url = add_query_arg('lang', $lc, home_url('/'));
        $xml .= "<xhtml:link rel='alternate' hreflang='" . esc_attr($linfo['hreflang'] ?? $lc) . "' href='" . esc_url($url) . "'/>\n";
    }
    $xml .= "<changefreq>daily</changefreq><priority>1.0</priority></url>\n";

    // Products
    foreach ($products as $pid) {
        $permalink = get_permalink($pid);
        $modified  = get_post_modified_time('c', false, $pid);
        $xml .= "<url>\n<loc>" . esc_url($permalink) . "</loc>\n";
        foreach ($langs as $lc => $linfo) {
            $url = add_query_arg('lang', $lc, $permalink);
            $xml .= "<xhtml:link rel='alternate' hreflang='" . esc_attr($linfo['hreflang'] ?? $lc) . "' href='" . esc_url($url) . "'/>\n";
        }
        $xml .= "<xhtml:link rel='alternate' hreflang='x-default' href='" . esc_url($permalink) . "'/>\n";
        $xml .= "<lastmod>{$modified}</lastmod>\n";
        $xml .= "<changefreq>weekly</changefreq><priority>0.8</priority></url>\n";
    }

    // Pages
    foreach ($pages as $pid) {
        $permalink = get_permalink($pid);
        $xml .= "<url>\n<loc>" . esc_url($permalink) . "</loc>\n";
        $xml .= "<changefreq>monthly</changefreq><priority>0.6</priority></url>\n";
    }

    $xml .= '</urlset>';

    // Save sitemap file
    $upload_dir  = wp_upload_dir();
    $sitemap_path = ABSPATH . 'sgclabs-multilang-sitemap.xml';
    file_put_contents($sitemap_path, $xml);

    wp_send_json_success([
        'url'      => home_url('/sgclabs-multilang-sitemap.xml'),
        'products' => count($products),
        'langs'    => count($langs),
        'urls'     => count($products) * count($langs),
    ]);
}

// Serve sitemap
add_action('init', 'sgclabs_register_sitemap_rewrite');
function sgclabs_register_sitemap_rewrite() {
    add_rewrite_rule('^sgclabs-multilang-sitemap\.xml$', 'index.php?sgclabs_sitemap=1', 'top');
    add_filter('query_vars', function($vars) { $vars[] = 'sgclabs_sitemap'; return $vars; });
}
add_action('template_redirect', 'sgclabs_serve_sitemap');
function sgclabs_serve_sitemap() {
    if (!get_query_var('sgclabs_sitemap')) return;
    $file = ABSPATH . 'sgclabs-multilang-sitemap.xml';
    // FIX: Validate file is exactly the expected file — no path traversal possible
    if (realpath($file) === realpath(ABSPATH . 'sgclabs-multilang-sitemap.xml') && file_exists($file)) {
        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        readfile($file); // readfile is safer — no string manipulation
        exit;
    }
}

// --- ADMIN PAGE ---
function sgclabs_multilang_seo_page() {
    global $wpdb;
    $cached = $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key LIKE '_sgclabs_multilang_seo_%'") ?? 0;
    $total  = function_exists('wc_get_products') ? (wp_count_posts('product')->publish ?? 0) : 0;
    $pct    = $total > 0 ? round($cached / $total * 100) : 0;
    $langs  = sgclabs_get_languages();
    $sitemap_exists = file_exists(ABSPATH . 'sgclabs-multilang-sitemap.xml');
    ?>
    <div class="wrap sgclabs-wrap">
<?php sgclabs_module_control_bar(["module_id"=>"multilingual-seo","module_name"=>"Multilingual SEO","module_icon"=>"🌐","option_key"=>"sgclabs_multilingual_seo_active","default"=>"1","description"=>"11 languages — auto SEO titles, descriptions, hreflang tags","modes"=>[["value"=>"ai","label"=>"AI Translation","desc"=>"AI generates translations per product"],["value"=>"manual","label"=>"Manual Only","desc"=>"Only use manually entered translations"]],"mode_key"=>"sgclabs_multilang_mode","mode_default"=>"ai"]); ?>
        <h1>🌐 Multilingual SEO Engine</h1>
        <p>Har product ke liye <strong>11 languages</strong> mein automatic SEO — Title, Description, Slug, Alt Tags, Schema, hreflang, Sitemap.</p>

        <!-- Stats -->
        <div class="sgclabs-seo-stats">
            <div class="seo-stat-box"><div class="seo-stat-num"><?php echo number_format($total); ?></div><div class="seo-stat-label">Products</div></div>
            <div class="seo-stat-box green"><div class="seo-stat-num"><?php echo count($langs); ?></div><div class="seo-stat-label">Languages</div></div>
            <div class="seo-stat-box blue"><div class="seo-stat-num"><?php echo number_format($total * count($langs)); ?></div><div class="seo-stat-label">Total SEO Pages</div></div>
            <div class="seo-stat-box orange"><div class="seo-stat-num"><?php echo intval($pct); ?>%</div><div class="seo-stat-label">Generated</div></div>
        </div>

        <!-- What gets generated -->
        <div class="sgclabs-section">
            <h2>✅ Har Product ke liye Kya Generate Hota Hai</h2>
            <div class="multilang-features-grid">
                <div class="ml-feature-card">
                    <div class="ml-icon">📌</div>
                    <h4>Title Tags</h4>
                    <p>3 variations har language mein. "Buy Beaker | SGC LABS® India" → "Kaufen Beaker | SGC LABS® Indien"</p>
                </div>
                <div class="ml-feature-card">
                    <div class="ml-icon">📝</div>
                    <h4>Meta Description</h4>
                    <p>160 char description har language mein. Quality + Export + Trust signals included.</p>
                </div>
                <div class="ml-feature-card">
                    <div class="ml-icon">🔗</div>
                    <h4>URL Slug</h4>
                    <p>?lang=de, ?lang=ar etc. Virtual lang URLs with hreflang tags for Google.</p>
                </div>
                <div class="ml-feature-card">
                    <div class="ml-icon">🖼️</div>
                    <h4>Image Alt Tags</h4>
                    <p>Har product image pe auto SEO alt text. "Buy Beaker - Laboratory Glassware | SGC LABS® India"</p>
                </div>
                <div class="ml-feature-card">
                    <div class="ml-icon">🗂️</div>
                    <h4>Schema Markup</h4>
                    <p>Product schema with multilingual keywords, manufacturer info, shipping worldwide.</p>
                </div>
                <div class="ml-feature-card">
                    <div class="ml-icon">🌍</div>
                    <h4>hreflang Tags</h4>
                    <p>Google ko batata hai ki yeh page 11 languages mein available hai.</p>
                </div>
            </div>
        </div>

        <!-- Languages -->
        <div class="sgclabs-section">
            <h2>🗣️ Languages Auto-Generated</h2>
            <div style="display:flex;flex-wrap:wrap;gap:10px;">
                <?php foreach ($langs as $code => $info): ?>
                    <div class="lang-pill">
                        <?php echo esc_html($info['flag']); ?> <?php echo esc_html($info['name']); ?>
                        <small>(<?php echo esc_html($code); ?>)</small>
                        <?php if ($info['dir']==='rtl'): ?><span style="color:#e74c3c;">RTL</span><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Product Preview -->
        <div class="sgclabs-section">
            <h2>👁️ Live Preview — Kisi Product ka Multilingual SEO Dekho</h2>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:15px;">
                <?php
                $recent = function_exists('wc_get_products') ? wc_get_products(['limit'=>8,'status'=>'publish','return'=>'ids']) : [];
                ?>
                <select id="ml-product-id" style="min-width:280px;padding:8px;">
                    <option value="">-- Select Product --</option>
                    <?php foreach ($recent as $pid):
                        $p = wc_get_product($pid); if (!$p) continue; ?>
                        <option value="<?php echo $pid; ?>"><?php echo esc_html($p->get_name()); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="ml-lang-select" style="padding:8px;">
                    <?php foreach ($langs as $code => $info): ?>
                        <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($info['flag'] . ' ' . $info['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="button button-primary" onclick="previewMultilangSEO()">🔍 Preview</button>
            </div>

            <div id="ml-preview-result" style="display:none;">
                <div class="seo-preview-tabs">
                    <button class="seo-ptab active" onclick="showPTab('ptab-title')">📌 Title</button>
                    <button class="seo-ptab" onclick="showPTab('ptab-desc')">📝 Description</button>
                    <button class="seo-ptab" onclick="showPTab('ptab-keywords')">🔑 Keywords</button>
                    <button class="seo-ptab" onclick="showPTab('ptab-schema')">🗂️ Schema</button>
                    <button class="seo-ptab" onclick="showPTab('ptab-og')">📱 OG Tags</button>
                    <button class="seo-ptab" onclick="showPTab('ptab-hreflang')">🌍 hreflang</button>
                </div>
                <div id="ptab-title" class="seo-ptab-content active">
                    <h4>Title Variations:</h4>
                    <div id="ml-titles"></div>
                    <h4 style="margin-top:15px;">Google Preview:</h4>
                    <div class="sgclabs-google-preview">
                        <div class="gp-url">labsglass.com/product/...</div>
                        <div class="gp-title" id="ml-primary-title"></div>
                        <div class="gp-desc" id="ml-meta-desc-preview"></div>
                    </div>
                </div>
                <div id="ptab-desc" class="seo-ptab-content" style="display:none;">
                    <h4>Meta Description:</h4>
                    <div id="ml-meta-desc" style="background:#f5f5f5;padding:15px;border-radius:8px;font-size:14px;"></div>
                    <p id="ml-desc-len" style="color:#666;font-size:12px;"></p>
                </div>
                <div id="ptab-keywords" class="seo-ptab-content" style="display:none;">
                    <h4>All Keywords:</h4>
                    <div id="ml-keywords" class="kw-tag-cloud"></div>
                </div>
                <div id="ptab-schema" class="seo-ptab-content" style="display:none;">
                    <h4>Schema JSON-LD:</h4>
                    <pre id="ml-schema" style="background:#1a1a2e;color:#00ff88;padding:15px;border-radius:8px;font-size:11px;overflow:auto;max-height:300px;"></pre>
                </div>
                <div id="ptab-og" class="seo-ptab-content" style="display:none;">
                    <h4>Open Graph Tags (Facebook/WhatsApp):</h4>
                    <div id="ml-og"></div>
                </div>
                <div id="ptab-hreflang" class="seo-ptab-content" style="display:none;">
                    <h4>hreflang Tags (for all languages):</h4>
                    <pre id="ml-hreflang" style="background:#f5f5f5;padding:15px;border-radius:8px;font-size:11px;overflow:auto;"></pre>
                </div>
            </div>
        </div>

        <!-- Sitemap Generator -->
        <div class="sgclabs-section">
            <h2>🗺️ Multilingual Sitemap Generator</h2>
            <p>Google Search Console ke liye sitemap — saare products × 11 languages = <strong><?php echo number_format($total * 11); ?> URLs!</strong></p>
            <?php if ($sitemap_exists): ?>
                <div class="sgclabs-ok-box" style="margin-bottom:15px;">
                    ✅ Sitemap exists: <a href="<?php echo home_url('/sgclabs-multilang-sitemap.xml'); ?>" target="_blank"><?php echo home_url('/sgclabs-multilang-sitemap.xml'); ?></a>
                </div>
            <?php endif; ?>
            <div id="sgc-pnav-multilang"></div>
            <div id="sitemap-result" style="margin-top:15px;display:none;"></div>
            <div style="margin-top:15px;" class="description">
                <strong>Baad mein:</strong> Google Search Console → Sitemaps → Is URL ko submit karo:<br>
                <code><?php echo home_url('/sgclabs-multilang-sitemap.xml'); ?></code>
            </div>
        </div>
    </div>

    <style>
    .multilang-features-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:15px; }
    .ml-feature-card { background:#f8f9ff; border:1px solid #e0e0f0; border-radius:10px; padding:18px; text-align:center; }
    .ml-feature-card h4 { margin:8px 0 5px; color:#1a1a2e; }
    .ml-feature-card p { font-size:12px; color:#666; margin:0; }
    .ml-icon { font-size:28px; }
    .lang-pill { background:#f0f4ff; border:1px solid #d0d8ff; border-radius:20px; padding:6px 14px; font-size:13px; display:flex; align-items:center; gap:5px; }
    .seo-preview-tabs { display:flex; gap:5px; margin-bottom:15px; flex-wrap:wrap; }
    .seo-ptab { background:#f0f0f0; border:1px solid #ddd; padding:7px 14px; border-radius:5px; cursor:pointer; font-size:13px; }
    .seo-ptab.active { background:#1a1a2e; color:white; border-color:#1a1a2e; }
    .ml-title-var { background:#f5f5f5; padding:10px 15px; border-radius:6px; margin-bottom:8px; font-size:14px; border-left:3px solid #1a1a2e; }
    .ml-og-row { display:flex; gap:10px; padding:6px 0; border-bottom:1px solid #f0f0f0; font-size:13px; }
    .ml-og-prop { color:#1a1a2e; font-weight:bold; min-width:200px; }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
    if (typeof SgcProgressNav === 'undefined') return;
    // ── Multilingual SEO Progress Nav ───────────────────────────
    const mlNav = new SgcProgressNav('sgc-pnav-multilang', {
        label: 'Multilingual SEO Sitemap',
        icon: '🌐',
        showSpeed: false,
        counters: [{id:'sgc-ml-langs', label:'Languages'}, {id:'sgc-ml-pages', label:'Pages'}],
        onPlay:  function(nav) { runGenerateSitemap(nav); },
        onStop:  function(nav) { },
        onReset: function(nav) { }
    });

    function runGenerateSitemap(nav) {
        nav.status('<span class="sgc-pulse"></span> Generating multilingual sitemap…');
        const nonce = '<?php echo esc_js( wp_create_nonce( "sgclabs_nonce" ) ); ?>';
        jQuery.post(ajaxurl, { action: 'sgclabs_generate_sitemap', nonce: nonce }, function(res) {
            if (!res.success) { nav.error(res.data||'Error'); return; }
            const d = res.data;
            nav.setPercent(100);
            nav.setCounter('sgc-ml-langs', d.langs || '—');
            nav.setCounter('sgc-ml-pages', d.total || '—');
            nav.done('Sitemap generated! ' + (d.total||0) + ' URLs indexed.');
        }).fail(function(){ nav.error('Network error.'); });
    }

    const nonce = '<?php echo esc_js( wp_create_nonce( 'sgclabs_nonce' ) ); ?>';

    function showPTab(id) {
        document.querySelectorAll('.seo-ptab-content').forEach(el => el.style.display='none');
        document.querySelectorAll('.seo-ptab').forEach(el => el.classList.remove('active'));
        document.getElementById(id).style.display='block';
        event.target.classList.add('active');
    }

    function previewMultilangSEO() {
        const pid  = document.getElementById('ml-product-id').value;
        const lang = document.getElementById('ml-lang-select').value;
        if (!pid) { alert('Please select a product'); return; }
        jQuery.post(ajaxurl, {
            action: 'sgclabs_preview_multilang',
            nonce: nonce,
            product_id: pid,
            lang: lang
        }, function(res) {
            if (!res.success) { alert('Error: ' + (res.data || 'Unknown')); return; }
            const d = res.data;
            document.getElementById('ml-preview-result').style.display = 'block';

            // Titles
            let titlesHtml = d.titles.map((t,i) => `<div class="ml-title-var"><strong>Variation ${i+1}:</strong> ${t}</div>`).join('');
            document.getElementById('ml-titles').innerHTML = titlesHtml;
            document.getElementById('ml-primary-title').textContent = d.primary_title;
            document.getElementById('ml-meta-desc-preview').textContent = d.meta_desc;

            // Description
            document.getElementById('ml-meta-desc').textContent = d.meta_desc;
            document.getElementById('ml-desc-len').textContent = `Characters: ${d.meta_desc.length}/160`;

            // Keywords
            const kwHtml = d.keywords.slice(0,25).map(k => `<span class="sgclabs-tag">${k}</span>`).join('');
            document.getElementById('ml-keywords').innerHTML = kwHtml;

            // Schema
            document.getElementById('ml-schema').textContent = JSON.stringify(d.schema, null, 2);

            // OG Tags
            let ogHtml = Object.entries(d.og_tags).map(([k,v]) => v ? `<div class="ml-og-row"><span class="ml-og-prop">${k}</span><span>${v}</span></div>` : '').join('');
            document.getElementById('ml-og').innerHTML = ogHtml;

            // hreflang
            let hreflangCode = '';
            Object.entries(d.hreflang_urls).forEach(([lc, url]) => {
                hreflangCode += `<link rel="alternate" hreflang="${lc}" href="${url}">\n`;
            });
            hreflangCode += `<link rel="alternate" hreflang="x-default" href="${d.base_url}">`;
            document.getElementById('ml-hreflang').textContent = hreflangCode;

            document.getElementById('ml-preview-result').scrollIntoView({behavior:'smooth'});
        });
    }

    function generateSitemap() {
        const btn = event.target;
        btn.disabled = true;
        btn.textContent = '⏳ Generating...';
        jQuery.post(ajaxurl, {action:'sgclabs_generate_sitemap', nonce:nonce}, function(res) {
            btn.disabled = false;
            btn.textContent = '🗺️ Generate Multilingual Sitemap';
            const el = document.getElementById('sitemap-result');
            el.style.display = 'block';
            if (res.success) {
                el.innerHTML = `<div class="sgclabs-ok-box">✅ Sitemap generated!<br>Products: ${res.data.products} × Languages: ${res.data.langs} = <strong>${res.data.urls} URLs</strong><br><a href="${res.data.url}" target="_blank">📥 Download Sitemap</a></div>`;
            } else {
                el.innerHTML = `<div style="color:red">❌ Error generating sitemap</div>`;
            }
        });
    }
    }); // end DOMContentLoaded
    </script>
    <?php
}

// AJAX: Preview multilingual SEO
add_action('wp_ajax_sgclabs_preview_multilang', 'sgclabs_preview_multilang_ajax');
function sgclabs_preview_multilang_ajax() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');

    $pid  = intval($_POST['product_id'] ?? 0);
    $lang = sanitize_text_field($_POST['lang'] ?? 'en');

    $all_seo = sgclabs_generate_multilingual_seo($pid);
    if (!$all_seo || !isset($all_seo[$lang])) {
        wp_send_json_error('Could not generate SEO');
        return;
    }

    $seo = $all_seo[$lang];
    $langs = sgclabs_get_languages();

    // Build hreflang URLs — v6.6.5: use BCP 47 codes as keys
    $hreflang_urls = [];
    foreach ($langs as $lc => $linfo) {
        $hreflang_code = $linfo['hreflang'] ?? $lc;
        $hreflang_urls[$hreflang_code] = add_query_arg('lang', $lc, get_permalink($pid));
    }

    wp_send_json_success([
        'titles'        => $seo['titles'],
        'primary_title' => $seo['primary_title'],
        'meta_desc'     => $seo['meta_desc'],
        'keywords'      => $seo['keywords'],
        'schema'        => $seo['schema'],
        'og_tags'       => $seo['og_tags'],
        'hreflang_urls' => $hreflang_urls,
        'base_url'      => get_permalink($pid),
        'lang'          => $lang,
        'dir'           => $seo['dir'],
    ]);
}
