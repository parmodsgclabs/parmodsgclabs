<?php
if (!defined('ABSPATH')) exit;

// ============================================================
// SGC LABS® — SMART INTERNAL LINKING ENGINE v6.6.3
// Auto-populates internal backlinks on product pages:
// - Related Products (same category / keyword match)
// - Related Categories (breadcrumb + sibling cats)
// - Smart anchor text from product titles & keywords
// - "Also Viewed" / "Customers Also Buy" blocks
// Google SEO benefit: Passes link equity, improves crawlability
// ============================================================

// -----------------------------------------------------------------
// CORE: Find related products for a given product
// Algorithm: category overlap + keyword match + price range
// -----------------------------------------------------------------
function sgclabs_find_related_products($product_id, $limit = 6) {
    if (!function_exists('wc_get_product')) return [];

    $product_id = intval($product_id);
    $cache_key  = 'sgclabs_related_v2_' . $product_id;
    $cached     = get_transient($cache_key);
    if ($cached !== false) return $cached;

    $product = wc_get_product($product_id);
    if (!$product) return [];

    $cat_ids   = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    $tag_ids   = wp_get_post_terms($product_id, 'product_tag', ['fields' => 'ids']);
    $name      = strtolower($product->get_name());
    $sku       = strtolower($product->get_sku() ?: '');
    $price     = floatval($product->get_price() ?: 0);
    $excl_ids  = array_map('intval', (array) get_option('sgclabs_link_exclude_ids', []));

    // Tax query — category OR tag match (NOT rand orderby — use popularity)
    $tax_query = [['taxonomy'=>'product_cat','field'=>'term_id','terms'=>$cat_ids]];
    if (!empty($tag_ids)) {
        $tax_query = ['relation'=>'OR',
            ['taxonomy'=>'product_cat','field'=>'term_id','terms'=>$cat_ids],
            ['taxonomy'=>'product_tag','field'=>'term_id','terms'=>$tag_ids],
        ];
    }
    $candidate_ids = get_posts([
        'post_type'=>'product','post_status'=>'publish',
        'posts_per_page'=>40,
        'post__not_in'=>array_merge([$product_id], $excl_ids),
        'orderby'=>'meta_value_num','meta_key'=>'total_sales',
        'fields'=>'ids','tax_query'=>$tax_query,
    ]);
    if (count($candidate_ids) < $limit) {
        $fallback = get_posts([
            'post_type'=>'product','post_status'=>'publish',
            'posts_per_page'=>20,
            'post__not_in'=>array_merge([$product_id],$excl_ids,$candidate_ids),
            'orderby'=>'meta_value_num','meta_key'=>'total_sales','fields'=>'ids',
        ]);
        $candidate_ids = array_merge($candidate_ids, $fallback);
    }

    // Pre-compute scoring tokens
    $name_words  = array_unique(array_filter(explode(' ', $name), fn($w) => strlen($w) > 3));
    preg_match_all('/\d+\s*(?:ml|l|mm|cm|m|g|kg)\b/i', $name.' '.$sku, $size_m);
    $size_tokens = $size_m[0] ?? [];

    $scored = [];
    foreach ($candidate_ids as $pid) {
        $pid = intval($pid);
        $p   = wc_get_product($pid);
        if (!$p) continue;

        $score   = 0;
        $p_name  = strtolower($p->get_name());
        $p_sku   = strtolower($p->get_sku() ?: '');
        $p_cats  = wp_get_post_terms($pid,'product_cat',['fields'=>'ids']);
        $p_tags  = wp_get_post_terms($pid,'product_tag',['fields'=>'ids']);
        $p_sales = intval(get_post_meta($pid,'total_sales',true));

        // Category overlap (0-40 pts)
        $score += count(array_intersect($cat_ids,$p_cats)) * 20;
        // Tag overlap (0-20 pts)
        if (!empty($tag_ids)) $score += count(array_intersect($tag_ids,$p_tags)) * 10;
        // Keyword overlap (0-30 pts)
        $p_words = array_unique(array_filter(explode(' ',$p_name),fn($w)=>strlen($w)>3));
        $score  += count(array_intersect($name_words,$p_words)) * 10;
        // SKU prefix match (0-15 pts) — same product family
        if (!empty($sku) && !empty($p_sku)) {
            $pfx = substr($sku,0,max(3,strlen($sku)-3));
            if (strpos($p_sku, $pfx) === 0) $score += 15;
        }
        // Size variant cross-link (0-10 pts) — different size = good link
        preg_match_all('/\d+\s*(?:ml|l|mm|cm|m|g|kg)\b/i',$p_name.' '.$p_sku,$ps_m);
        $p_sizes = $ps_m[0] ?? [];
        if (!empty($size_tokens) && !empty($p_sizes) && empty(array_intersect($size_tokens,$p_sizes))) {
            $score += 10; // different size variant
        }
        // Price proximity (0-15 pts)
        $p_price = floatval($p->get_price() ?: 0);
        if ($price>0 && $p_price>0) {
            $diff  = abs($price-$p_price)/max($price,$p_price);
            $score += max(0,15-intval($diff*15));
        }
        // Popularity (0-10 pts)
        $score += min(10, intval($p_sales/10));
        // In-stock bonus
        if ($p->is_in_stock()) $score += 5;

        $scored[$pid] = [
            'product'  =>$p,'score'=>$score,'name'=>$p->get_name(),
            'permalink'=>get_permalink($pid),'price'=>$p->get_price_html(),
            'image'    =>get_the_post_thumbnail_url($pid,'woocommerce_thumbnail')?:wc_placeholder_img_src(),
            'cats'     =>wp_get_post_terms($pid,'product_cat',['fields'=>'names']),
            'in_stock' =>$p->is_in_stock(),
        ];
    }
    uasort($scored, fn($a,$b) => $b['score']-$a['score']);
    $result = array_slice(array_values($scored),0,$limit);
    set_transient($cache_key,$result,HOUR_IN_SECONDS*6);
    return $result;
}

// -----------------------------------------------------------------
// CORE: Find related categories for breadcrumb + sidebar linking
// -----------------------------------------------------------------
function sgclabs_find_related_categories($product_id, $limit = 8) {
    $cat_ids = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    if (empty($cat_ids)) return [];

    $related_cats = [];

    foreach ($cat_ids as $cat_id) {
        // Current categories
        $cat = get_term($cat_id, 'product_cat');
        if (!$cat || is_wp_error($cat)) continue;
        $related_cats[$cat_id] = $cat;

        // Parent category
        if ($cat->parent) {
            $parent = get_term($cat->parent, 'product_cat');
            if ($parent && !is_wp_error($parent)) {
                $related_cats[$cat->parent] = $parent;
            }
        }

        // Sibling categories (same parent)
        if ($cat->parent) {
            $siblings = get_terms([
                'taxonomy'   => 'product_cat',
                'parent'     => $cat->parent,
                'hide_empty' => true,
                'exclude'    => [$cat_id],
                'number'     => 5,
            ]);
            foreach ($siblings as $sib) $related_cats[$sib->term_id] = $sib;
        }

        // Child categories
        $children = get_terms([
            'taxonomy'   => 'product_cat',
            'parent'     => $cat_id,
            'hide_empty' => true,
            'number'     => 4,
        ]);
        foreach ($children as $child) $related_cats[$child->term_id] = $child;
    }

    // Add product count + link
    $result = [];
    foreach (array_slice($related_cats, 0, $limit, true) as $cat) {
        $result[] = [
            'id'    => $cat->term_id,
            'name'  => $cat->name,
            'link'  => get_term_link($cat),
            'count' => $cat->count,
            'desc'  => $cat->description,
        ];
    }
    return $result;
}

// -----------------------------------------------------------------
// FRONTEND: Inject Related Products block on product page
// -----------------------------------------------------------------
add_action(get_option('sgclabs_related_position','woocommerce_after_single_product_summary'), 'sgclabs_render_related_products_block', 20);
function sgclabs_render_related_products_block() {
    if (get_option('sgclabs_internal_links_active', '1') !== '1') return;
    if (get_option('sgclabs_show_related_products', '1') !== '1') return;
    if (!is_product()) return;

    $product_id = get_the_ID();
    $related    = sgclabs_find_related_products($product_id, intval(get_option('sgclabs_related_count', 6)));
    if (empty($related)) return;
    $title = get_option('sgclabs_related_title', 'You May Also Like');
    ?>
    <section class="sgclabs-related-products" aria-label="<?php echo esc_attr($title); ?>">
        <h2 class="sgclabs-section-heading"><?php echo esc_html($title); ?></h2>
        <div class="sgclabs-related-grid">
            <?php foreach ($related as $item): ?>
            <a href="<?php echo esc_url($item['permalink']); ?>" class="sgclabs-related-card">
                <div class="sgclabs-related-img">
                    <img src="<?php echo esc_url($item['image']); ?>"
                         alt="<?php echo esc_attr($item['name']); ?>"
                         loading="lazy" width="200" height="200">
                    <?php if ($item['in_stock']): ?>
                        <span class="sgclabs-stock-badge">In Stock</span>
                    <?php endif; ?>
                </div>
                <div class="sgclabs-related-info">
                    <h3><?php echo esc_html($item['name']); ?></h3>
                    <?php if (!empty($item['cats'])): ?>
                        <small><?php echo esc_html($item['cats'][0]); ?></small>
                    <?php endif; ?>
                    <div class="sgclabs-related-price"><?php echo wp_kses_post($item['price']); // WooCommerce price HTML — sanitized ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}

// -----------------------------------------------------------------
// FRONTEND: Related Categories block
// -----------------------------------------------------------------
add_action(get_option('sgclabs_related_position','woocommerce_after_single_product_summary'), 'sgclabs_render_related_categories_block', 25);
function sgclabs_render_related_categories_block() {
    if (get_option('sgclabs_internal_links_active', '1') !== '1') return;
    if (get_option('sgclabs_show_related_cats', '1') !== '1') return;
    if (!is_product()) return;

    $product_id = get_the_ID();
    $cats       = sgclabs_find_related_categories($product_id, 8);
    if (empty($cats)) return;
    $title = get_option('sgclabs_cats_title', 'Browse Related Categories');
    ?>
    <section class="sgclabs-related-cats" aria-label="<?php echo esc_attr($title); ?>">
        <h2 class="sgclabs-section-heading"><?php echo esc_html($title); ?></h2>
        <div class="sgclabs-cats-grid">
            <?php foreach ($cats as $cat): ?>
            <a href="<?php echo esc_url($cat['link']); ?>" class="sgclabs-cat-chip">
                🏷️ <?php echo esc_html($cat['name']); ?>
                <span class="sgclabs-cat-count"><?php echo intval($cat['count']); ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}

// -----------------------------------------------------------------
// FRONTEND: Smart Anchor Text Injection in product description
// Finds product names mentioned in content → adds internal links
// -----------------------------------------------------------------
add_filter('the_content', 'sgclabs_auto_link_content', 20);
add_filter('woocommerce_short_description', 'sgclabs_auto_link_content', 20);
function sgclabs_auto_link_content($content) {
    if (get_option('sgclabs_internal_links_active', '1') !== '1') return $content;
    if (get_option('sgclabs_auto_link_content', '0') !== '1') return $content;
    if (!is_product() && !is_singular('product')) return $content;
    if (is_admin() || doing_filter('the_excerpt')) return $content;

    static $link_map = null;

    if ($link_map === null) {
        $cache_key = 'sgclabs_link_map';
        $link_map  = get_transient($cache_key);

        if (!$link_map) {
            // Build product name → permalink map
            $products = get_posts([
                'post_type'=>'product','post_status'=>'publish',
                'posts_per_page'=>-1,'fields'=>'ids',
            ]);
            $link_map = [];
            foreach ($products as $pid) {
                $p = wc_get_product($pid);
                if (!$p) continue;
                $name = $p->get_name();
                if (strlen($name) < 8 || strlen($name) > 60) continue;
                $link_map[$name] = get_permalink($pid);
            }
            // Merge manual keyword → URL map (admin-defined)
            $manual = (array) get_option('sgclabs_manual_link_map', []);
            foreach ($manual as $kw => $url) {
                if (!empty($kw) && !empty($url)) $link_map[$kw] = esc_url_raw($url);
            }
            // Sort longest first — match "Borosilicate Beaker 250ml" before "Beaker"
            uksort($link_map, fn($a,$b) => strlen($b)-strlen($a));
            set_transient($cache_key, $link_map, 6 * HOUR_IN_SECONDS);
        }
    }

    if (empty($link_map)) return $content;

    $current_id  = get_the_ID();
    $current_url = get_permalink($current_id);
    $linked      = 0;
    $max_links   = intval(get_option('sgclabs_max_auto_links', 3));

    foreach ($link_map as $name => $url) {
        if ($linked >= $max_links) break;
        if ($url === $current_url) continue; // Don't link to self

        $escaped_name = preg_quote($name, '/');
        // FIX: PHP 7.x compatible pattern — no variable-width lookbehind
        // Split content on <a> tags to avoid linking inside existing anchors
        $parts   = preg_split('/(<a[^>]*>.*?<\/a>|<h[1-6][^>]*>.*?<\/h[1-6]>)/si', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        $changed = false;
        foreach ($parts as $idx => $part) {
            if ($idx % 2 === 1) continue; // skip matched delimiters (existing links/headings)
            if (!$changed) {
                $new_part = preg_replace(
                    '/\b(' . $escaped_name . ')\b/u',
                    '<a href="' . esc_url($url) . '" class="sgclabs-auto-link" title="' . esc_attr($name) . '">' . esc_html($name) . '</a>',
                    $part, 1
                );
                if ($new_part !== null && $new_part !== $part) {
                    $parts[$idx] = $new_part;
                    $changed = true;
                }
            }
        }
        if ($changed) {
            $content = implode('', $parts);
            $linked++;
        }
    }

    return $content;
}


// ── JSON-LD isRelatedTo Schema (Google product relationship signal) ──
add_action('wp_head', 'sgclabs_related_products_schema', 20); // v7.5.1: moved from wp_footer so head-dedupe merges it into the single Product schema
function sgclabs_related_products_schema() {
    if (get_option('sgclabs_internal_links_active','1') !== '1') return;
    if (!is_product()) return;
    if (defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION')) return;

    $pid     = get_the_ID();
    $product = wc_get_product($pid);
    if (!$product) return;
    $related = sgclabs_find_related_products($pid, 4);
    if (empty($related)) return;

    $schema = [
        '@context'    => 'https://schema.org',
        '@type'       => 'Product',
        '@id'         => get_permalink($pid) . '#product',
        'name'        => $product->get_name(),
        'isRelatedTo' => array_map(fn($r) => [
            '@type' => 'Product',
            'name'  => $r['name'],
            'url'   => $r['permalink'],
        ], $related),
    ];
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "
";
}

// -----------------------------------------------------------------
// FRONTEND CSS for related blocks
// -----------------------------------------------------------------
add_action('wp_footer', 'sgclabs_internal_links_css');
function sgclabs_internal_links_css() {
    if (get_option('sgclabs_internal_links_active', '1') !== '1') return;
    if (!is_product()) return;
    ?>
    <style id="sgclabs-internal-links-css">

    /* ── Section headings ────────────────────────────── */
    .sgclabs-related-products,
    .sgclabs-related-cats { margin: 40px 0; }

    .sgclabs-section-heading {
        font-size: 1.25em;
        font-weight: 700;
        color: #1a1a2e;
        border-left: 4px solid #1565c0;
        padding-left: 14px;
        margin-bottom: 20px;
        letter-spacing: 0.2px;
    }

    /* ── Related products grid ───────────────────────── */
    .sgclabs-related-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(<?php echo intval(get_option('sgclabs_related_card_width', 160)); ?>px, 1fr));
        gap: 15px;
    }

    .sgclabs-related-card {
        background: #fff;
        border: 1.5px solid #e0e8f8;
        border-radius: 10px;
        overflow: hidden;
        text-decoration: none;
        color: inherit;
        transition: transform .2s, box-shadow .2s, border-color .2s;
        display: block;
    }
    .sgclabs-related-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(21,101,192,0.13);
        border-color: #1565c0;
    }

    .sgclabs-related-img {
        position: relative;
        aspect-ratio: 1;
        overflow: hidden;
        background: #f0f5ff;
    }
    .sgclabs-related-img img {
        width: 100%; height: 100%;
        object-fit: cover; display: block;
        transition: transform .3s;
    }
    .sgclabs-related-card:hover .sgclabs-related-img img {
        transform: scale(1.04);
    }

    /* In Stock badge — SGC Blue */
    .sgclabs-stock-badge {
        position: absolute; top: 8px; right: 8px;
        background: #1565c0;
        color: #fff;
        font-size: 10px;
        padding: 3px 8px;
        border-radius: 20px;
        font-weight: 600;
        letter-spacing: 0.3px;
    }

    .sgclabs-related-info { padding: 12px; }
    .sgclabs-related-info h3 {
        font-size: 13px; font-weight: 600;
        color: #1a1a2e; margin: 0 0 4px;
        line-height: 1.3;
    }
    .sgclabs-related-info small { font-size: 11px; color: #888; }
    .sgclabs-related-price {
        font-size: 13px; font-weight: 700;
        color: #1565c0; margin-top: 6px;
    }

    /* ── View Product Button on card ─────────────────── */
    .sgclabs-related-card::after {
        content: 'View Product →';
        display: block;
        margin: 0 12px 12px;
        padding: 8px 12px;
        background: #1565c0;
        color: #fff;
        text-align: center;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        transition: background .2s;
        text-decoration: none;
    }
    .sgclabs-related-card:hover::after {
        background: #1a1a2e;
    }

    /* ── Related Categories chips ────────────────────── */
    .sgclabs-cats-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .sgclabs-cat-chip {
        background: #fff;
        border: 2px solid #1565c0;
        border-radius: 7px;
        padding: 10px 20px;
        text-decoration: none;
        color: #1565c0;
        font-size: 13px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all .2s;
        letter-spacing: 0.2px;
    }
    .sgclabs-cat-chip:hover {
        background: #1565c0;
        color: #fff;
        border-color: #1565c0;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(21,101,192,0.2);
    }

    .sgclabs-cat-count {
        background: #1565c0;
        color: #fff;
        border-radius: 20px;
        padding: 2px 8px;
        font-size: 11px;
        font-weight: 700;
    }
    .sgclabs-cat-chip:hover .sgclabs-cat-count {
        background: #fff;
        color: #1565c0;
    }

    /* ── Auto link style ─────────────────────────────── */
    .sgclabs-auto-link {
        color: #1565c0;
        border-bottom: 1px dashed #1565c0;
        text-decoration: none;
        transition: color .2s;
    }
    .sgclabs-auto-link:hover {
        color: #c62828;
        border-bottom-color: #c62828;
    }

    /* ── WooCommerce Tabs override ───────────────────── */
    .woocommerce-tabs ul.tabs li a {
        color: #1a1a2e !important;
        font-weight: 600 !important;
    }
    .woocommerce-tabs ul.tabs li.active a,
    .woocommerce-tabs ul.tabs li a:hover {
        color: #1565c0 !important;
    }
    .woocommerce-tabs ul.tabs li.active {
        border-bottom-color: #1565c0 !important;
    }
    .woocommerce-tabs ul.tabs::before {
        border-bottom-color: #1565c0 !important;
    }

    /* ── Mobile ──────────────────────────────────────── */
    @media(max-width:600px) {
        .sgclabs-related-grid { grid-template-columns: repeat(2, 1fr); }
        .sgclabs-cat-chip { padding: 8px 14px; font-size: 12px; }
    }

    </style>
    <?php
}

// -----------------------------------------------------------------
// AJAX: Preview related products for admin panel
// -----------------------------------------------------------------
add_action('wp_ajax_sgclabs_preview_related', 'sgclabs_preview_related_ajax');
function sgclabs_preview_related_ajax() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }

    $pid  = intval($_POST['product_id'] ?? 0);
    $type = sanitize_text_field($_POST['type'] ?? 'products');

    if ($type === 'products') {
        $related = sgclabs_find_related_products($pid, 6);
        $result  = array_map(fn($r) => [
            'name'      => $r['name'],
            'permalink' => $r['permalink'],
            'price'     => wp_strip_all_tags($r['price']),
            'cats'      => $r['cats'],
            'score'     => $r['score'],
            'in_stock'  => $r['in_stock'],
        ], $related);
    } else {
        $result = sgclabs_find_related_categories($pid, 8);
    }
    wp_send_json_success($result);
}

// AJAX: Rebuild link map cache
add_action('wp_ajax_sgclabs_rebuild_link_map', 'sgclabs_rebuild_link_map_ajax');
function sgclabs_rebuild_link_map_ajax() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }
    delete_transient('sgclabs_link_map');
    // Clear all related product caches (v1 + v2 keys)
    global $wpdb;
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
        '%_transient_sgclabs_related_%', '%_transient_timeout_sgclabs_related_%',
        '%_transient_sgclabs_related_v2_%', '%_transient_timeout_sgclabs_related_v2_%'
    ));
    // Build fresh map and return count
    $products = get_posts(['post_type'=>'product','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids']);
    $link_map = [];
    foreach ($products as $pid) {
        $p = wc_get_product($pid);
        if (!$p) continue;
        $name = $p->get_name();
        if (strlen($name) >= 8 && strlen($name) <= 60) {
            $link_map[$name] = get_permalink($pid);
        }
    }
    // Add manual keyword map
    $manual = (array) get_option('sgclabs_manual_link_map', []);
    foreach ($manual as $kw => $url) {
        if (!empty($kw) && !empty($url)) $link_map[$kw] = esc_url_raw($url);
    }
    uksort($link_map, fn($a,$b) => strlen($b)-strlen($a));
    set_transient('sgclabs_link_map', $link_map, 6 * HOUR_IN_SECONDS);
    wp_send_json_success([
        'message'  => 'Link map rebuilt successfully.',
        'products' => count($products),
        'links'    => count($link_map),
    ]);
}

// -----------------------------------------------------------------
// ADMIN PAGE
// -----------------------------------------------------------------
function sgclabs_internal_links_page() {
    // Save settings
    if (isset($_POST['sgclabs_save_links']) && wp_verify_nonce($_POST['_wpnonce'], 'sgclabs_links_nonce')) {
        $opts = [
            'sgclabs_internal_links_active' => isset($_POST['links_active']) ? '1' : '0',
            'sgclabs_show_related_products' => isset($_POST['show_related']) ? '1' : '0',
            'sgclabs_show_related_cats'     => isset($_POST['show_cats']) ? '1' : '0',
            'sgclabs_auto_link_content'     => isset($_POST['auto_link']) ? '1' : '0',
            'sgclabs_related_count'         => intval($_POST['related_count'] ?? 6),
            'sgclabs_related_title'         => sanitize_text_field($_POST['related_title'] ?? 'You May Also Like'),
            'sgclabs_cats_title'            => sanitize_text_field($_POST['cats_title'] ?? 'Browse Related Categories'),
            'sgclabs_max_auto_links'        => intval($_POST['max_auto_links'] ?? 3),
            'sgclabs_related_card_width'    => intval($_POST['card_width'] ?? 160),
            'sgclabs_related_position'      => sanitize_key($_POST['related_position'] ?? 'woocommerce_after_single_product_summary'),
        ];
        foreach ($opts as $key => $val) update_option($key, $val);

        // Save exclusion list
        $excl_raw = sanitize_textarea_field($_POST['exclude_ids'] ?? '');
        $excl_ids = array_filter(array_map('intval', explode(',', $excl_raw)));
        update_option('sgclabs_link_exclude_ids', $excl_ids);

        // Save manual keyword→URL map
        $kw_keys = $_POST['manual_kw'] ?? [];
        $kw_urls = $_POST['manual_url'] ?? [];
        $manual_map = [];
        foreach ($kw_keys as $i => $kw) {
            $kw = sanitize_text_field($kw);
            $url = esc_url_raw($kw_urls[$i] ?? '');
            if (!empty($kw) && !empty($url)) $manual_map[$kw] = $url;
        }
        update_option('sgclabs_manual_link_map', $manual_map);
        // Rebuild link map cache with new manual entries
        delete_transient('sgclabs_link_map');

        echo '<div class="notice notice-success"><p>✅ Settings saved!</p></div>';
    }

    $is_active   = get_option('sgclabs_internal_links_active', '1');
    $show_rel    = get_option('sgclabs_show_related_products', '1');
    $show_cats   = get_option('sgclabs_show_related_cats', '1');
    $auto_link   = get_option('sgclabs_auto_link_content', '0');
    $rel_count   = intval(get_option('sgclabs_related_count', 6));
    $rel_title   = get_option('sgclabs_related_title', 'You May Also Like');
    $cats_title  = get_option('sgclabs_cats_title', 'Browse Related Categories');
    $max_links   = intval(get_option('sgclabs_max_auto_links', 3));
    $card_width    = intval(get_option('sgclabs_related_card_width', 160));
    $position      = get_option('sgclabs_related_position', 'woocommerce_after_single_product_summary');
    $excl_ids      = (array) get_option('sgclabs_link_exclude_ids', []);
    $manual_map    = (array) get_option('sgclabs_manual_link_map', []);

    // Recent products for preview
    $recent = function_exists('wc_get_products') ? wc_get_products(['limit'=>10,'status'=>'publish','return'=>'ids']) : [];
    ?>
    <div class="wrap sgclabs-wrap">
        <div class="sgclabs-header">
            <h1>🔗 Smart Internal Linking Engine</h1>
            <p>Product pages pe automatically <strong>related products + categories</strong> show karo — Google crawlability improve hoti hai, bounce rate kam hota hai, aur link equity distribute hoti hai.</p>
        </div>

        <!-- SEO Benefits Info -->
        <div class="sgclabs-section" style="background:linear-gradient(135deg,#f0fff4,#e8f8f0);border-left:4px solid #27ae60;">
            <h2>📈 SEO Benefits — Internal Linking Kyun Important Hai</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;margin-top:10px;">
                <?php foreach([
                    ['🕷️','Google Crawlability','Google spider related pages discover karta hai internal links se — uncrawled products rank nahi karte'],
                    ['💪','Link Equity Distribution','High-traffic pages ka authority related products ko milta hai — "PageRank passing"'],
                    ['⏱️','Bounce Rate Reduction','Visitor ek product se doosra dekhta hai → session time badhta hai → Google positive signal'],
                    ['🎯','Keyword Anchor Text','Relevant anchor text se Google samjhta hai ki linked page kiske baare mein hai'],
                    ['🗺️','Site Architecture','Proper category linking = logical site structure = better crawl budget usage'],
                    ['💰','Higher Revenue','Related products dikhne se upsell/cross-sell opportunities — conversion rate improve'],
                ] as [$i,$t,$d]): ?>
                <div style="background:#fff;border:1px solid #c8e6c9;border-radius:8px;padding:12px;">
                    <strong style="color:#27ae60;"><?php echo $i; ?> <?php echo esc_html($t); ?></strong>
                    <p style="font-size:12px;color:#555;margin:6px 0 0;"><?php echo esc_html($d); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Settings -->
        <div class="sgclabs-section">
            <h2>⚙️ Settings</h2>
            <form method="post">
                <?php wp_nonce_field('sgclabs_links_nonce'); ?>
                <input type="hidden" name="sgclabs_save_links" value="1">
                <table class="form-table">
                    <tr>
                        <th>Enable Internal Linking</th>
                        <td><label><input type="checkbox" name="links_active" value="1" <?php checked($is_active,'1'); ?>> Active rakhna hai</label></td>
                    </tr>
                    <tr>
                        <th>Related Products Block</th>
                        <td>
                            <label><input type="checkbox" name="show_related" value="1" <?php checked($show_rel,'1'); ?>> Product pages pe "Related Products" block dikhao</label><br><br>
                            <label>Block Title: <input type="text" name="related_title" value="<?php echo esc_attr($rel_title); ?>" class="regular-text"></label><br><br>
                            <label>Products count: <input type="number" name="related_count" value="<?php echo intval($rel_count); ?>" min="2" max="12" style="width:60px;"></label><br><br>
                            <label>Card min-width: <input type="number" name="card_width" value="<?php echo intval($card_width); ?>" min="120" max="300" style="width:70px;"> px</label>
                        </td>
                    </tr>
                    <tr>
                        <th>Related Categories Block</th>
                        <td>
                            <label><input type="checkbox" name="show_cats" value="1" <?php checked($show_cats,'1'); ?>> Category chips dikhao (parent, siblings, children)</label><br><br>
                            <label>Block Title: <input type="text" name="cats_title" value="<?php echo esc_attr($cats_title); ?>" class="regular-text"></label>
                        </td>
                    </tr>
                    <tr>
                        <th>Auto-Link Content</th>
                        <td>
                            <label><input type="checkbox" name="auto_link" value="1" <?php checked($auto_link,'1'); ?>> Product description mein product names automatically link karo</label>
                            <p class="description">⚠️ <strong>Carefully use karo</strong> — existing content mein product names pe automatic link add hoga. Max links per page:</p>
                            <input type="number" name="max_auto_links" value="<?php echo intval($max_links); ?>" min="1" max="10" style="width:60px;">
                        </td>
                    </tr>
                    <tr>
                        <th>Display Position</th>
                        <td>
                            <select name="related_position">
                                <?php $positions = [
                                    'woocommerce_after_single_product_summary' => 'After Product Summary (default)',
                                    'woocommerce_before_single_product_summary'=> 'Before Product Summary',
                                    'woocommerce_after_add_to_cart_form'       => 'After Add to Cart Button',
                                    'woocommerce_product_tabs'                 => 'In Product Tabs',
                                ];
                                foreach ($positions as $hook => $label): ?>
                                    <option value="<?php echo esc_attr($hook); ?>" <?php selected($position,$hook); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Exclude Products</th>
                        <td>
                            <input type="text" name="exclude_ids"
                                value="<?php echo esc_attr(implode(',', $excl_ids)); ?>"
                                class="large-text" placeholder="Product IDs, comma separated e.g. 123,456,789">
                            <p class="description">Yeh products related suggestions mein nahi dikhenge.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Manual Keyword → URL Map</th>
                        <td>
                            <p class="description">Specific word pe specific page ka link lagao — auto-link content mein use hoga.</p>
                            <div id="sgc-manual-map-wrap">
                                <?php foreach ($manual_map as $kw => $url): ?>
                                <div class="sgc-manual-row" style="display:flex;gap:8px;margin-bottom:6px;">
                                    <input type="text" name="manual_kw[]" value="<?php echo esc_attr($kw); ?>" placeholder="Keyword" style="width:200px;">
                                    <input type="url" name="manual_url[]" value="<?php echo esc_attr($url); ?>" placeholder="https://..." style="flex:1;">
                                    <button type="button" onclick="this.parentElement.remove()" class="button">✕</button>
                                </div>
                                <?php endforeach; ?>
                                <div class="sgc-manual-row" style="display:flex;gap:8px;margin-bottom:6px;">
                                    <input type="text" name="manual_kw[]" value="" placeholder="Keyword e.g. Borosilicate" style="width:200px;">
                                    <input type="url" name="manual_url[]" value="" placeholder="https://labsglass.com/category/..." style="flex:1;">
                                    <button type="button" onclick="this.parentElement.remove()" class="button">✕</button>
                                </div>
                            </div>
                            <button type="button" class="button" onclick="addManualRow()">+ Add Row</button>
                        </td>
                    </tr>
                </table>
                <p><button type="submit" class="button button-primary button-large">💾 Save Settings</button>
                   <div id="sgc-pnav-intlinks" style="margin-top:10px;"></div></p>
            </form>
        </div>

        <!-- Preview Tool -->
        <div class="sgclabs-section">
            <h2>👁️ Preview — Related Products & Categories</h2>
            <p>Kisi bhi product ke liye dekho kaunse related products aur categories suggest honge:</p>
            <div style="display:flex;gap:10px;margin-bottom:15px;flex-wrap:wrap;align-items:center;">
                <select id="preview-link-product" style="min-width:300px;padding:8px;">
                    <option value="">-- Product Select Karo --</option>
                    <?php foreach ($recent as $pid):
                        $p = function_exists('wc_get_product') ? wc_get_product($pid) : null;
                        if (!$p) continue; ?>
                        <option value="<?php echo intval($pid); ?>"><?php echo esc_html($p->get_name()); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="button button-primary" onclick="previewRelated('products')">🔍 Related Products</button>
                <button class="button" onclick="previewRelated('categories')">🏷️ Related Categories</button>
            </div>
            <div id="link-preview-wrap" style="display:none;background:#f8f9ff;border:1px solid #dde4f8;border-radius:8px;padding:15px;"></div>
        </div>

        <!-- How It Works -->
        <div class="sgclabs-section" style="background:#f8f9ff;border-left:4px solid #3498db;">
            <h2>🧠 Algorithm — Kaise Related Products Choose Hote Hain</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-top:10px;">
                <?php foreach([
                    ['🗂️ Category Match','Same category = +20 pts per overlap. Tag match = +10 pts.','#e3f2fd','#2196f3'],
                    ['🔤 Name Keywords','Title mein common words = +10 pts per word','#e8f5e9','#4caf50'],
                    ['🔧 SKU Prefix Match','Same SKU family (BK-250, BK-500) = +15 pts — detects variants','#e0f7fa','#00bcd4'],
                    ['📐 Size Variants','Different size = +10 pts — e.g. 250ml links to 500ml','#fce4ec','#e91e63'],
                    ['💰 Price Proximity','Similar price range = +15 pts max','#fff3e0','#ff9800'],
                    ['📈 Popularity','WooCommerce sales data = up to +10 pts','#f3e5f5','#9c27b0'],
                    ['✅ Stock Status','In-stock products = +5 pts bonus','#e8f5e9','#4caf50'],
                ] as [$t,$d,$bg,$bc]): ?>
                <div style="background:<?php echo esc_attr($bg); ?>;border:1px solid <?php echo esc_attr($bc); ?>;border-radius:8px;padding:12px;">
                    <strong style="color:<?php echo $bc; ?>"><?php echo esc_html($t); ?></strong>
                    <p style="font-size:12px;color:#555;margin:6px 0 0;"><?php echo esc_html($d); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
    if (typeof SgcProgressNav === 'undefined') return;
    // ── Internal Links Progress Nav ─────────────────────────────
    const ilNav = new SgcProgressNav('sgc-pnav-intlinks', {
        label: 'Internal Link Cache Rebuild',
        icon: '🔀',
        showSpeed: false,
        counters: [{id:'sgc-il-products', label:'Products'}, {id:'sgc-il-links', label:'Links Mapped'}],
        onPlay:  function(nav) { runRebuildCache(nav); },
        onStop:  function(nav) { },
        onReset: function(nav) { }
    });

    function runRebuildCache(nav) {
        nav.status('<span class="sgc-pulse"></span> Rebuilding link map…');
        const nonce = '<?php echo esc_js( wp_create_nonce( "sgclabs_nonce" ) ); ?>';
        jQuery.post(ajaxurl, { action: 'sgclabs_rebuild_link_map', nonce: nonce }, function(res) {
            if (!res.success) { nav.error(res.data||'Error'); return; }
            const d = res.data;
            nav.setPercent(100);
            nav.setCounter('sgc-il-products', d.products || '—');
            nav.setCounter('sgc-il-links',    d.links    || '—');
            nav.done('Link cache rebuilt! ' + (d.products||0) + ' products, ' + (d.links||0) + ' links.');
        }).fail(function(){ nav.error('Network error.'); });
    }

    const nonce = '<?php echo esc_js(wp_create_nonce('sgclabs_nonce')); ?>';

    function previewRelated(type) {
        const pid = document.getElementById('preview-link-product').value;
        if (!pid) { alert('Pehle product select karo.'); return; }
        const wrap = document.getElementById('link-preview-wrap');
        wrap.style.display = 'block';
        wrap.innerHTML = '<p style="color:#999;">⏳ Loading...</p>';

        jQuery.post(ajaxurl, { action:'sgclabs_preview_related', nonce, product_id:pid, type }, function(res) {
            if (!res.success || !res.data.length) {
                wrap.innerHTML = '<p style="color:#e74c3c;">No results found.</p>'; return;
            }
            if (type === 'products') {
                wrap.innerHTML = `
                    <p><strong>📦 ${res.data.length} Related Products (sorted by relevance score):</strong></p>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:10px;">
                    ${res.data.map(r => `
                        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:12px;">
                            <strong style="font-size:13px;">${r.name}</strong><br>
                            <small style="color:#888;">${r.cats ? r.cats[0] : ''}</small><br>
                            <span style="color:#27ae60;font-weight:600;">${r.price}</span>
                            <span style="float:right;background:#e8f0ff;border-radius:20px;padding:2px 8px;font-size:11px;">Score: ${r.score}</span><br>
                            <small>${r.in_stock ? '✅ In Stock' : '⚠️ Out of Stock'}</small>
                        </div>`).join('')}
                    </div>`;
            } else {
                wrap.innerHTML = `
                    <p><strong>🏷️ ${res.data.length} Related Categories:</strong></p>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;">
                    ${res.data.map(c => `
                        <a href="${c.link}" target="_blank" style="background:#f0f4ff;border:1px solid #c0d0ff;border-radius:20px;padding:6px 14px;text-decoration:none;color:#1a3a7a;font-size:13px;">
                            🏷️ ${c.name} <span style="background:#1a1a2e;color:#fff;border-radius:10px;padding:1px 6px;font-size:11px;margin-left:4px;">${c.count}</span>
                        </a>`).join('')}
                    </div>`;
            }
        });
    }

    function addManualRow() {
        const wrap = document.getElementById('sgc-manual-map-wrap');
        const div = document.createElement('div');
        div.className = 'sgc-manual-row';
        div.style.cssText = 'display:flex;gap:8px;margin-bottom:6px;';
        div.innerHTML = '<input type="text" name="manual_kw[]" value="" placeholder="Keyword" style="width:200px;"><input type="url" name="manual_url[]" value="" placeholder="https://..." style="flex:1;"><button type="button" onclick="this.parentElement.remove()" class="button">✕</button>';
        wrap.appendChild(div);
    }

    function rebuildCache() {
        jQuery.post(ajaxurl, { action:'sgclabs_rebuild_link_map', nonce }, function(res) {
            if (res.success) alert('✅ ' + res.data.message);
        });
    }
    }); // end DOMContentLoaded
    </script>
    <?php
}
