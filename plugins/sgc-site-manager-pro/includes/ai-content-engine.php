<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ============================================================
 * SGC LABS® — Central AI Content Engine v6.6.3
 * ============================================================
 * Single unified AI-powered content generation engine.
 * Replaces: auto-seo-engine.php, product-content-rewriter.php,
 *           focus-keyword-generator.php, seo-hub.php,
 *           seo-injector.php, multilingual-seo.php (AI parts)
 *
 * All functions use sgclabs_ai_call() — provider-agnostic.
 * Offline fallback included for when AI API not configured.
 * ============================================================
 */

// ============================================================
// SECTION 1 — CORE AI GENERATORS
// ============================================================

/**
 * Generate SEO Title via AI (Google 50-60 char optimized)
 */
function sgclabs_ai_gen_title( int $pid ): string {
    if ( ! function_exists( 'wc_get_product' ) ) return '';
    $p    = wc_get_product( $pid );
    if ( ! $p ) return '';

    $name = $p->get_name();
    $cats = wp_get_post_terms( $pid, 'product_cat', [ 'fields' => 'names' ] );
    $cat  = ! empty( $cats ) ? $cats[0] : 'Laboratory Equipment';
    $sku  = $p->get_sku();

    // Check cache
    $cached = get_post_meta( $pid, '_sgclabs_ai_seo_title', true );
    if ( $cached ) return $cached;

    if ( function_exists( 'sgclabs_ai_any_configured' ) && sgclabs_ai_any_configured() ) {
        $prompt = "You are an SEO expert for a B2B laboratory glassware manufacturer (SGC LABS® India).
Write ONE Google-optimized SEO title for this product:
Product: {$name}
Category: {$cat}
SKU: {$sku}

Rules:
- Exactly 50-60 characters (count carefully)
- START the title with the primary keyword or product name
- Include 'SGC LABS' or 'SGC LABS®'
- Commercial intent (buy/supplier/manufacturer)
- NO quotes, NO explanation, just the title

Output ONLY the title, nothing else.";

        $result = sgclabs_ai_call_for_task( 'seo_title',  $prompt, 80 );
        if ( $result && strlen( trim( $result ) ) >= 30 ) {
            $title = trim( preg_replace( '/^["\']|["\']$/', '', trim( $result ) ) );
            $title = substr( $title, 0, 60 );
            update_post_meta( $pid, '_sgclabs_ai_seo_title',  $title );
            update_post_meta( $pid, '_sgclabs_seo_title',     $title );
            update_post_meta( $pid, 'rank_math_title',        $title );
            update_post_meta( $pid, '_yoast_wpseo_title',     $title );
            update_post_meta( $pid, '_sgclabs_ai_gen_at',     current_time('mysql') );
            return $title;
        }
    }

    // Offline fallback
    $title = sgclabs_ace_offline_title( $name, $cat );
    update_post_meta( $pid, '_sgclabs_seo_title', $title );
    return $title;
}

/**
 * Generate Meta Page Title via AI (HTML <title> tag — CTR-optimized, 50-60 chars)
 * Separate from SEO title — targets click-through rate on Google SERP.
 */
function sgclabs_ai_gen_meta_title( int $pid ): string {
    if ( ! function_exists( 'wc_get_product' ) ) return '';
    $p = wc_get_product( $pid );
    if ( ! $p ) return '';

    $name  = $p->get_name();
    $cats  = wp_get_post_terms( $pid, 'product_cat', [ 'fields' => 'names' ] );
    $cat   = ! empty( $cats ) ? $cats[0] : 'Laboratory Equipment';
    $sku   = $p->get_sku();
    $price = $p->get_price();
    $stock = $p->is_in_stock() ? 'In Stock' : 'Made to Order';

    $cached = get_post_meta( $pid, '_sgclabs_meta_page_title', true );
    if ( $cached ) return $cached;

    if ( function_exists( 'sgclabs_ai_any_configured' ) && sgclabs_ai_any_configured() ) {
        $prompt = "You are a Google SERP CTR specialist for B2B laboratory equipment (SGC LABS® India).
Write ONE HTML <title> tag value for this product page:
Product: {$name}
Category: {$cat}
SKU: {$sku}
Price: " . sgclabs_currency_symbol() . "{$price}
Stock: {$stock}
Manufacturer: SGC LABS® — ISO 9001:2015, Ambala India, worldwide export

STRICT Rules:
- EXACTLY 50-60 characters (count every single character including spaces and pipes)
- Format: [Primary Keyword] | [USP or Action] | SGC LABS®
- Primary keyword = what buyers actually search on Google for this product
- USP = one clear benefit (Price/Manufacturer/ISO Certified/Fast Dispatch)
- Must make a buyer WANT to click from Google search results
- Do NOT use generic words like 'laboratory' unless part of the product name
- Do NOT use quotes, markdown, explanations — just the title text

Output ONLY the plain title text, nothing else.";

        $result = sgclabs_ai_call_for_task( 'meta_desc', $prompt, 80 );
        if ( $result && strlen( trim( $result ) ) >= 30 ) {
            $title = trim( preg_replace( '/^["\']|["\']$/', '', trim( $result ) ) );
            $title = substr( $title, 0, 60 );
            update_post_meta( $pid, '_sgclabs_meta_page_title', $title );
            update_post_meta( $pid, 'rank_math_title',          $title );
            update_post_meta( $pid, '_yoast_wpseo_title',       $title );
            return $title;
        }
    }

    // Offline fallback — safe static title
    $t = 'Buy ' . $name . ' | SGC LABS® India';
    $t = strlen($t) > 60 ? substr($t, 0, 57) . '...' : $t;
    update_post_meta( $pid, '_sgclabs_meta_page_title', $t );
    update_post_meta( $pid, 'rank_math_title',          $t );
    return $t;
}

/**
 * Generate Meta Description via AI (120-160 char)
 */
function sgclabs_ai_gen_meta_desc( int $pid ): string {
    if ( ! function_exists( 'wc_get_product' ) ) return '';
    $p = wc_get_product( $pid );
    if ( ! $p ) return '';

    $name  = $p->get_name();
    $cats  = wp_get_post_terms( $pid, 'product_cat', [ 'fields' => 'names' ] );
    $cat   = ! empty( $cats ) ? $cats[0] : 'Laboratory Equipment';
    $short = substr( wp_strip_all_tags( $p->get_short_description() ), 0, 100 );

    $cached = get_post_meta( $pid, '_sgclabs_ai_meta_desc', true );
    if ( $cached ) return $cached;

    if ( function_exists( 'sgclabs_ai_any_configured' ) && sgclabs_ai_any_configured() ) {
        $prompt = "Write ONE Google meta description for this lab equipment product:
Product: {$name}
Category: {$cat}
Existing short desc: {$short}
Company: SGC LABS® — ISO certified manufacturer, Ambala India, worldwide export

Rules:
- Exactly 130-155 characters
- Include product name + key benefit + call to action
- Include 'SGC LABS' 
- NO quotes, NO explanation

Output ONLY the description.";

        $result = sgclabs_ai_call_for_task( 'meta_desc',  $prompt, 100 );
        if ( $result && strlen( trim( $result ) ) >= 100 ) {
            $desc = trim( $result );
            $desc = substr( $desc, 0, 160 );
            update_post_meta( $pid, '_sgclabs_ai_meta_desc',   $desc );
            update_post_meta( $pid, '_sgclabs_seo_description',$desc );
            update_post_meta( $pid, 'rank_math_description',   $desc );
            update_post_meta( $pid, '_yoast_wpseo_metadesc',   $desc );
            return $desc;
        }
    }

    $desc = sgclabs_ace_offline_desc( $name, $cat, $short );
    update_post_meta( $pid, '_sgclabs_ai_meta_desc',    $desc );
    update_post_meta( $pid, '_sgclabs_seo_description', $desc );
    return $desc;
}

/**
 * Generate Focus Keyword via AI (search volume aware)
 */
function sgclabs_ai_gen_focus_kw( int $pid ): string {
    if ( ! function_exists( 'wc_get_product' ) ) return '';
    $p = wc_get_product( $pid );
    if ( ! $p ) return '';

    $name  = $p->get_name();
    $cats  = wp_get_post_terms( $pid, 'product_cat', [ 'fields' => 'names' ] );
    $cat   = ! empty( $cats ) ? $cats[0] : 'Laboratory Equipment';

    $cached = get_post_meta( $pid, '_sgclabs_focus_kw', true );
    if ( $cached ) return $cached;

    if ( function_exists( 'sgclabs_ai_any_configured' ) && sgclabs_ai_any_configured() ) {
        $prompt = "You are a Google SEO keyword researcher.
Product: {$name} (Category: {$cat})
Seller: SGC LABS® — B2B lab glassware manufacturer, India

Give the SINGLE best focus keyword for this product that:
- Has real Google search volume
- Is specific enough to rank for
- Has commercial/transactional intent
- Is 2-5 words

Output ONLY the keyword phrase, nothing else.";

        $result = sgclabs_ai_call_for_task( 'focus_keyword',  $prompt, 30 );
        if ( $result && strlen( trim( $result ) ) >= 5 ) {
            $kw = strtolower( trim( $result ) );
            update_post_meta( $pid, '_sgclabs_focus_kw',        $kw );
            update_post_meta( $pid, 'rank_math_focus_keyword',  $kw );
            update_post_meta( $pid, '_yoast_wpseo_focuskw',     $kw );
            return $kw;
        }
    }

    return strtolower( $name );
}

/**
 * Generate AI Keyword Suggestions (for Keyword Manager)
 */
function sgclabs_ai_gen_keywords( int $pid ): array {
    if ( ! function_exists( 'wc_get_product' ) ) return [];
    $p = wc_get_product( $pid );
    if ( ! $p ) return [];

    $name = $p->get_name();
    $cats = wp_get_post_terms( $pid, 'product_cat', [ 'fields' => 'names' ] );
    $cat  = ! empty( $cats ) ? $cats[0] : 'Laboratory Equipment';

    if ( ! function_exists( 'sgclabs_ai_any_configured' ) || ! sgclabs_ai_any_configured() ) {
        return [];
    }

    $prompt = "You are a Google Keyword Planner expert for B2B lab equipment.
Product: {$name}
Category: {$cat}
Target markets: India, USA, UK, Germany, UAE, Australia

Generate 15 SEO keywords that:
- Have real search volume on Google
- Mix of short-tail and long-tail
- Include country/region variations
- Include buying intent keywords
- Include technical/spec keywords

Return ONLY a JSON array of strings. Example: [\"keyword one\",\"keyword two\"]
No explanation, no markdown, just the JSON array.";

    $result = sgclabs_ai_call_for_task( 'long_desc', $prompt, 400 );
    if ( ! $result ) return [];

    $clean = trim( preg_replace( '/^```json|```$/m', '', trim( $result ) ) );
    $keywords = json_decode( $clean, true );
    return is_array( $keywords ) ? array_map( 'sanitize_text_field', $keywords ) : [];
}

/**
 * Generate Short Description via AI (unique, keyword-rich)
 */
function sgclabs_ai_gen_short_desc( int $pid ): string {
    if ( ! function_exists( 'wc_get_product' ) ) return '';
    $p = wc_get_product( $pid );
    if ( ! $p ) return '';

    $name  = $p->get_name();
    $cats  = wp_get_post_terms( $pid, 'product_cat', [ 'fields' => 'names' ] );
    $cat   = ! empty( $cats ) ? $cats[0] : 'Laboratory Equipment';
    $sku   = $p->get_sku();
    $price = $p->get_price();
    $stock = $p->is_in_stock() ? 'In Stock' : 'Made to Order';

    // Return cached if exists and not empty
    $cached = get_post_meta( $pid, '_sgclabs_ai_short_desc', true );
    if ( $cached && strlen( $cached ) >= 50 ) return $cached;

    if ( function_exists( 'sgclabs_ai_any_configured' ) && sgclabs_ai_any_configured() ) {
        $prompt = "Write a WooCommerce product short description for Google SEO:
Product: {$name}
Category: {$cat}
SKU: {$sku}
Price: " . sgclabs_currency_symbol() . "{$price}
Availability: {$stock}
Manufacturer: SGC LABS® — ISO 9001:2015 certified, Ambala India, worldwide export

Rules:
- 100-160 characters (not words — characters)
- Include the primary product keyword naturally in first 50 chars
- Mention ONE key quality claim (ISO certified / manufacturer-direct / borosilicate / etc.)
- Include a soft CTA (Request quote / Order online / Contact for bulk)
- Plain text only — NO HTML tags, NO bullet points
- Unique to THIS specific product — not a generic template

Output ONLY the short description text, nothing else.";

        $result = sgclabs_ai_call_for_task( 'short_desc',  $prompt, 120 );
        if ( $result && strlen( trim( $result ) ) >= 50 ) {
            $desc = wp_strip_all_tags( trim( $result ) );
            $desc = substr( $desc, 0, 180 );
            update_post_meta( $pid, '_sgclabs_ai_short_desc', $desc );
            wp_update_post( [ 'ID' => $pid, 'post_excerpt' => $desc ] );
            return $desc;
        }
    }

    $desc = sgclabs_ace_offline_desc( $name, $cat, '' );
    update_post_meta( $pid, '_sgclabs_ai_short_desc', $desc );
    wp_update_post( [ 'ID' => $pid, 'post_excerpt' => $desc ] );
    return $desc;
}

/**
 * Generate Main Description via AI (unique HTML, no boilerplate)
 */
function sgclabs_ai_gen_main_desc( int $pid ): string {
    if ( ! function_exists( 'wc_get_product' ) ) return '';
    $p = wc_get_product( $pid );
    if ( ! $p ) return '';

    $name   = $p->get_name();
    $cats   = wp_get_post_terms( $pid, 'product_cat', [ 'fields' => 'names' ] );
    $cat    = ! empty( $cats ) ? $cats[0] : 'Laboratory Equipment';
    $sku    = $p->get_sku();
    $price  = $p->get_price();
    $stock  = $p->is_in_stock() ? 'In Stock' : 'Made to Order';
    $focus  = get_post_meta( $pid, '_sgclabs_focus_kw', true ) ?: strtolower( $name );
    $attrs  = [];
    foreach ( $p->get_attributes() as $attr ) {
        if ( method_exists( $attr, 'get_name' ) ) {
            $terms = wc_get_product_terms( $pid, $attr->get_name(), [ 'fields' => 'names' ] );
            if ( ! empty( $terms ) ) $attrs[] = wc_attribute_label( $attr->get_name() ) . ': ' . implode( ', ', $terms );
        }
    }
    $attr_str = ! empty( $attrs ) ? implode( ' | ', $attrs ) : '';

    // Return cached if long enough
    $cached = get_post_meta( $pid, '_sgclabs_ai_main_desc', true );
    if ( $cached && strlen( wp_strip_all_tags( $cached ) ) >= 300 ) return $cached;

    if ( function_exists( 'sgclabs_ai_any_configured' ) && sgclabs_ai_any_configured() ) {
        $prompt = "You are an expert SEO content writer for B2B laboratory equipment.
Write a COMPLETE WooCommerce product description (HTML) for this product:

Product Name: {$name}
Category: {$cat}
Focus Keyword: {$focus}
SKU: {$sku}
Price: " . sgclabs_currency_symbol() . "{$price}
Availability: {$stock}
Product Attributes: {$attr_str}
Manufacturer: SGC LABS® — ISO 9001:2015, Ambala, Haryana, India. Exporting worldwide since 2003.

CONTENT REQUIREMENTS (Google E-E-A-T + WooCommerce standards):
1. Total 400-600 words — unique, specific to THIS product
2. Use these HTML tags: <h2>, <h3>, <ul>, <li>, <strong>, <p>, <table>
3. Include focus keyword \"{$focus}\" naturally 3-5 times (not forced)
4. Include 3-5 LSI related terms naturally (synonyms, related lab terms, applications — e.g. if product is \"beaker\", use: \"glass vessel\", \"laboratory container\", \"volumetric beaker\")
5. Do NOT repeat sentences — every paragraph must add value

REQUIRED SECTIONS (in this order):
<h2>About [Product Name]</h2>
- 2-3 unique sentences about this specific product, its construction material, grade, use-case
- Mention SGC LABS® and ISO certification naturally

<h3>Key Features & Specifications</h3>
- <ul> bullet list: 6-8 points — material, capacity/size range, graduation accuracy, temperature resistance, compliance standards (ISO/ASTM/BIS if applicable), SKU: {$sku}

<h3>Applications</h3>
- <ul> where this product is used: 4-6 specific lab applications (research labs, pharmaceutical QC, university teaching, chemical analysis, etc.)

<h3>Why Choose SGC LABS®?</h3>
- 3-4 points: Factory-direct price, ISO certified, worldwide export (50+ countries), custom sizes available

<h3>Technical Specifications</h3>
- HTML <table> with 2 columns: Specification | Value
- Include: Brand, SKU, Category, Origin, Compliance, MOQ

<p>Contact for bulk orders, custom requirements, or export queries: <strong>sales@labsglass.com</strong> | <strong>+91 999 641 6935</strong> | <a href=\"https://www.labsglass.com\">www.labsglass.com</a></p>

OUTPUT: Only the HTML content. No markdown, no explanation, no code fences.";

        $result = sgclabs_ai_call_for_task( 'long_desc', $prompt, 1400 );
        if ( $result && strlen( trim( $result ) ) >= 300 ) {
            $content = wp_kses_post( trim( $result ) );
            update_post_meta( $pid, '_sgclabs_ai_main_desc', $content );
            wp_update_post( [ 'ID' => $pid, 'post_content' => $content ] );
            return $content;
        }
    }

    // Offline fallback — save it too
    $content = sgclabs_ace_offline_main_desc( $name, $cat, $sku, $focus );
    update_post_meta( $pid, '_sgclabs_ai_main_desc', $content );
    wp_update_post( [ 'ID' => $pid, 'post_content' => $content ] );
    return $content;
}

/**
 * Generate Image Alt Text via AI
 */
function sgclabs_ai_gen_image_alt( int $pid ): array {
    if ( ! function_exists( 'wc_get_product' ) ) return [];
    $p = wc_get_product( $pid );
    if ( ! $p ) return [];

    $name  = $p->get_name();
    $cats  = wp_get_post_terms( $pid, 'product_cat', [ 'fields' => 'names' ] );
    $cat   = ! empty( $cats ) ? $cats[0] : 'Laboratory Equipment';
    $focus = get_post_meta( $pid, '_sgclabs_focus_kw', true ) ?: strtolower( $name );

    $image_ids = array_merge( [ get_post_thumbnail_id( $pid ) ], $p->get_gallery_image_ids() );
    $image_ids = array_filter( $image_ids );
    $result    = [];

    foreach ( $image_ids as $i => $img_id ) {
        $suffix = $i === 0 ? '' : ' - Image ' . ( $i + 1 );
        $alt    = "{$name} {$suffix} | {$cat} | SGC LABS® India";

        if ( function_exists( 'sgclabs_ai_any_configured' ) && sgclabs_ai_any_configured() && $i < 3 ) {
            $prompt = "Write SEO alt text for a product image:
Product: {$name}
Category: {$cat}
Image position: " . ( $i === 0 ? 'Main product image' : 'Gallery image ' . $i ) . "
Brand: SGC LABS® India

Rules: 5-12 words, descriptive, include product name and category. No 'image of' prefix.
Output ONLY the alt text.";

            $ai_alt = sgclabs_ai_call_for_task( 'image_alt',  $prompt, 40 );
            if ( $ai_alt && strlen( trim( $ai_alt ) ) >= 10 ) {
                $alt = trim( $ai_alt );
            }
        }

        $result[ $img_id ] = sanitize_text_field( $alt );
    }

    return $result;
}

/**
 * Generate Multilingual SEO data via AI (real translations)
 */
function sgclabs_ai_gen_multilingual( int $pid, string $lang_code ): array {
    if ( ! function_exists( 'wc_get_product' ) ) return [];
    $p = wc_get_product( $pid );
    if ( ! $p ) return [];

    $name  = $p->get_name();
    $cats  = wp_get_post_terms( $pid, 'product_cat', [ 'fields' => 'names' ] );
    $cat   = ! empty( $cats ) ? $cats[0] : 'Laboratory Equipment';
    $langs     = function_exists('sgclabs_get_languages') ? sgclabs_get_languages() : [];
    $lang_name = $langs[ $lang_code ]['name'] ?? $lang_code;

    if ( function_exists( 'sgclabs_ai_any_configured' ) && sgclabs_ai_any_configured() ) {
        $prompt = "Translate and localize these SEO elements into {$lang_name} for Google search:
Product: {$name}
Category: {$cat}
Brand: SGC LABS® (keep as-is)
Target market: {$lang_name} speaking countries

Generate:
1. SEO Title (50-60 chars in {$lang_name}) — commercial intent, include translated product name
2. Meta Description (130-155 chars in {$lang_name}) — unique, keyword-rich
3. Focus Keyword (2-4 words in {$lang_name}) — what people search on Google

Return ONLY JSON:
{\"title\":\"...\",\"desc\":\"...\",\"keyword\":\"...\"}";

        $result = sgclabs_ai_call( $prompt, 300 );
        if ( $result ) {
            $clean = trim( preg_replace( '/^```json|```$/m', '', trim( $result ) ) );
            $data  = json_decode( $clean, true );
            if ( is_array( $data ) && isset( $data['title'] ) ) {
                return [
                    'title'   => sanitize_text_field( $data['title'] ),
                    'desc'    => sanitize_text_field( $data['desc'] ?? '' ),
                    'keyword' => sanitize_text_field( $data['keyword'] ?? '' ),
                ];
            }
        }
    }

    // Offline fallback using dictionary
    if ( function_exists('sgclabs_generate_multilingual_seo') ) {
        $ml = sgclabs_generate_multilingual_seo( $pid );
        return is_array($ml) ? ($ml[ $lang_code ] ?? []) : [];
    }
    return [];
}

// ============================================================
// SECTION 2 — ONE-CLICK AI SEO FIX (Product Edit Page)
// ============================================================

/**
 * Main: Generate ALL SEO data for a product in one call
 */
function sgclabs_ai_gen_all( int $pid ): array {
    $focus_kw = sgclabs_ai_gen_focus_kw( $pid );
    $title    = sgclabs_ai_gen_title( $pid );
    $meta     = sgclabs_ai_gen_meta_desc( $pid );
    $short    = sgclabs_ai_gen_short_desc( $pid );
    $main     = sgclabs_ai_gen_main_desc( $pid );
    $alts     = sgclabs_ai_gen_image_alt( $pid );

    // Save all to post meta
    update_post_meta( $pid, '_sgclabs_seo_title',       $title );
    update_post_meta( $pid, '_sgclabs_seo_description', $meta );
    update_post_meta( $pid, '_sgclabs_focus_kw',        $focus_kw );
    update_post_meta( $pid, 'rank_math_title',          $title );
    update_post_meta( $pid, 'rank_math_description',    $meta );
    update_post_meta( $pid, 'rank_math_focus_keyword',  $focus_kw );
    update_post_meta( $pid, '_sgclabs_ai_gen_at',       current_time( 'mysql' ) );

    // Save image alts
    foreach ( $alts as $img_id => $alt ) {
        update_post_meta( $img_id, '_wp_attachment_image_alt', $alt );
    }

    // Update WooCommerce content if empty or short
    $p = wc_get_product( $pid );
    if ( $p ) {
        if ( strlen( wp_strip_all_tags( $p->get_short_description() ) ) < 50 ) {
            wp_update_post( [ 'ID' => $pid, 'post_excerpt' => $short ] );
        }
        if ( strlen( wp_strip_all_tags( $p->get_description() ) ) < 200 ) {
            wp_update_post( [ 'ID' => $pid, 'post_content' => $main ] );
        }
    }

    // Calculate new score
    $score = function_exists( 'sgclabs_calculate_seo_score' ) ? sgclabs_calculate_seo_score( $pid ) : 0;
    update_post_meta( $pid, '_sgclabs_seo_score', $score );

    return [
        'title'    => $title,
        'desc'     => $meta,
        'focus_kw' => $focus_kw,
        'short'    => $short,
        'main'     => $main,        // v6.6.6: for Classic Editor write-back
        'main_len' => strlen( $main ),
        'alts'     => count( $alts ),
        'score'    => $score,
    ];
}

// ── AJAX: One-Click AI Fix ────────────────────────────────────
add_action( 'wp_ajax_sgclabs_ai_one_click_fix', function () {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_products' ) ) wp_send_json_error( 'Unauthorized' );

    $pid = intval( $_POST['post_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'No product ID' );

    // Clear cached values so AI regenerates fresh
    $clear_keys = [ '_sgclabs_ai_seo_title', '_sgclabs_ai_meta_desc', '_sgclabs_focus_kw' ];
    foreach ( $clear_keys as $k ) delete_post_meta( $pid, $k );

    $result = sgclabs_ai_gen_all( $pid );
    wp_send_json_success( $result );
} );

// ── AJAX: Preview (before save) ──────────────────────────────
add_action( 'wp_ajax_sgclabs_ai_preview_fix', function () {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_products' ) ) wp_send_json_error( 'Unauthorized' );

    $pid = intval( $_POST['post_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'No product ID' );
    if ( ! function_exists( 'wc_get_product' ) ) wp_send_json_error( 'WooCommerce required' );

    $p = wc_get_product( $pid );
    if ( ! $p ) wp_send_json_error( 'Product not found' );

    $title = sgclabs_ai_gen_title( $pid );
    $meta  = sgclabs_ai_gen_meta_desc( $pid );
    $kw    = sgclabs_ai_gen_focus_kw( $pid );
    $short = sgclabs_ai_gen_short_desc( $pid );

    wp_send_json_success( [
        'title'      => $title,
        'title_len'  => strlen( $title ),
        'desc'       => $meta,
        'desc_len'   => strlen( $meta ),
        'focus_kw'   => $kw,
        'short_desc' => $short,
    ] );
} );

// ── AJAX: Bulk SEO Title Fill ─────────────────────────────────
add_action( 'wp_ajax_sgclabs_ace_bulk_titles', function () {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    global $wpdb;
    $offset     = intval( $_POST['offset'] ?? 0 );
    $batch      = 5;
    $only_blank = ( sanitize_key( $_POST['only_blank'] ?? '1' ) !== '0' );

    if ( $only_blank ) {
        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sgclabs_seo_title'
             WHERE p.post_type = 'product' AND p.post_status = 'publish' AND pm.meta_value IS NULL
             ORDER BY p.ID ASC LIMIT %d OFFSET %d",
            $batch, $offset
        ) );
    } else {
        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'
             ORDER BY ID ASC LIMIT %d OFFSET %d",
            $batch, $offset
        ) );
    }

    $total = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'" );
    $done  = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_sgclabs_seo_title'" );

    $processed = 0;
    foreach ( $ids as $pid ) {
        delete_post_meta( intval($pid), '_sgclabs_ai_seo_title' );
        $title = sgclabs_ai_gen_title( intval( $pid ) );
        if ( $title ) {
            update_post_meta( intval($pid), '_sgclabs_seo_title', $title );
            $processed++;
        }
    }

    wp_send_json_success( [
        'processed'   => $processed,
        'total'       => $total,
        'done'        => $done + $processed,
        'percent'     => min( 100, round( ( $done + $processed ) / max( $total, 1 ) * 100 ) ),
        'finished'    => count( $ids ) < $batch,
        'next_offset' => $offset + $batch,
    ] );
} );
add_action( 'wp_ajax_sgclabs_ace_bulk_content', function () {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    global $wpdb;
    $offset = intval( $_POST['offset'] ?? 0 );
    $batch  = 3; // AI calls per batch (slower but quality)
    $type   = sanitize_key( $_POST['type'] ?? 'all' ); // all|title|desc|kw|content

    $ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'
         ORDER BY ID ASC LIMIT %d OFFSET %d",
        $batch, $offset
    ) );
    $total = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'" );

    $processed = 0;
    foreach ( $ids as $pid ) {
        $pid = intval( $pid );
        // Clear cache for fresh generation
        delete_post_meta( $pid, '_sgclabs_ai_seo_title' );
        delete_post_meta( $pid, '_sgclabs_ai_meta_desc' );
        delete_post_meta( $pid, '_sgclabs_focus_kw' );

        if ( $type === 'all' ) {
            sgclabs_ai_gen_all( $pid );
        } elseif ( $type === 'title' ) {
            sgclabs_ai_gen_title( $pid );
        } elseif ( $type === 'desc' ) {
            sgclabs_ai_gen_meta_desc( $pid );
        } elseif ( $type === 'kw' ) {
            sgclabs_ai_gen_focus_kw( $pid );
        } elseif ( $type === 'content' ) {
            $short = sgclabs_ai_gen_short_desc( $pid );
            $main  = sgclabs_ai_gen_main_desc( $pid );
            wp_update_post( [ 'ID' => $pid, 'post_excerpt' => $short ] );
            wp_update_post( [ 'ID' => $pid, 'post_content' => $main ] );
        }
        $processed++;
    }

    $done = intval( $offset ) + $processed;
    wp_send_json_success( [
        'processed'   => $processed,
        'total'       => $total,
        'done'        => $done,
        'percent'     => min( 100, round( $done / max( $total, 1 ) * 100 ) ),
        'finished'    => count( $ids ) < $batch,
        'next_offset' => $offset + $batch,
    ] );
} );

// ── AJAX: AI Keyword Suggestions ─────────────────────────────
add_action( 'wp_ajax_sgclabs_ace_keyword_suggest', function () {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $pid = intval( $_POST['post_id'] ?? 0 );
    if ( ! $pid ) {
        // Manual product name entered
        $name = sanitize_text_field( $_POST['product_name'] ?? '' );
        $cat  = sanitize_text_field( $_POST['category'] ?? '' );
        if ( ! $name ) wp_send_json_error( 'Product name required' );
        // Create temp product data for prompt
        $keywords = sgclabs_ai_gen_keywords_manual( $name, $cat );
    } else {
        $keywords = sgclabs_ai_gen_keywords( $pid );
    }

    if ( empty( $keywords ) ) {
        wp_send_json_error( 'AI not configured or no results' );
    }
    wp_send_json_success( [ 'keywords' => $keywords ] );
} );

/**
 * Generate keywords by manual name/category (no product ID)
 */
function sgclabs_ai_gen_keywords_manual( string $name, string $cat ): array {
    if ( ! function_exists( 'sgclabs_ai_any_configured' ) || ! sgclabs_ai_any_configured() ) {
        return [];
    }
    $prompt = "You are a Google Keyword Planner expert for B2B lab equipment.
Product: {$name}
Category: {$cat}
Target: India, USA, UK, UAE, Germany, Australia

Generate 15 SEO keywords (mix of short-tail and long-tail, buying intent, country variations).
Return ONLY a JSON array: [\"keyword one\",\"keyword two\"]";

    $result = sgclabs_ai_call( $prompt, 400 );
    if ( ! $result ) return [];
    $clean = trim( preg_replace( '/^```json|```$/m', '', trim( $result ) ) );
    $kws = json_decode( $clean, true );
    return is_array( $kws ) ? array_map( 'sanitize_text_field', $kws ) : [];
}

// ============================================================
// SECTION 3 — ONE-CLICK FIX METABOX (Product Edit Page)
// ============================================================

add_action( 'add_meta_boxes', function () {
    add_meta_box(
        'sgclabs-ai-oneclick',
        '✨ AI One-Click SEO Fix — SGC LABS®',
        'sgclabs_ace_oneclick_metabox',
        'product',
        'side',
        'high'
    );
} );

function sgclabs_ace_oneclick_metabox( WP_Post $post ) {
    $pid     = $post->ID;
    $score   = intval( get_post_meta( $pid, '_sgclabs_seo_score', true ) ?: 0 );
    $gen_at  = get_post_meta( $pid, '_sgclabs_ai_gen_at', true );
    $nonce   = wp_create_nonce( 'sgclabs_nonce' );
    $ai_ok   = function_exists( 'sgclabs_ai_any_configured' ) && sgclabs_ai_any_configured();
    $color   = $score >= 80 ? '#27ae60' : ( $score >= 50 ? '#f39c12' : '#e74c3c' );
    $label   = $score >= 80 ? 'Excellent' : ( $score >= 50 ? 'Good' : 'Needs Fix' );
    ?>
    <div id="sgc-oneclick-wrap" style="font-family:sans-serif;">

    <?php sgclabs_help_box(
        'oneclick-meta',
        '✨ One-Click SEO Fix — How it Works',
        [
            [
                'icon'       => '🤖',
                'heading'    => 'Kya Hota Hai Ek Click Mein',
                'body'       => 'Yeh button ek saath <strong>6 cheezein generate karta hai</strong>: SEO Title (50-60 chars), Meta Description (130-155 chars), Focus Keyword, Short Description, Main Description (HTML), aur Image Alt Texts. Sab RankMath + Yoast mein bhi save hota hai.',
                'tip'        => 'Pehle AI API key zaroor configure karo: SGC LABS → AI Center.',
                'screenshot' => 'One-Click button → "AI analyzing product..." → Score ring update',
            ],
            [
                'icon'       => '📊',
                'heading'    => 'SEO Score Ring Samjho',
                'body'       => 'Score ring <strong>0–100</strong> range mein hai: <span style="color:#27ae60">●</span> <strong>80–100 = Excellent</strong> (Google ready), <span style="color:#f39c12">●</span> <strong>50–79 = Good</strong> (minor improvements), <span style="color:#e74c3c">●</span> <strong>0–49 = Needs Fix</strong>. 16 SEO checks run hote hain.',
                'tip'        => 'Score badhane ke liye: Focus keyword content mein naturally use karo, images ko alt text do.',
                'screenshot' => 'Score ring → color changes: red → orange → green',
            ],
            [
                'icon'       => '↩️',
                'heading'    => 'Undo Karna Ho To — Rollback',
                'body'       => 'AI run hone se pehle original content auto-save hota hai. Sidebar mein <strong>"↩️ SGC Content Backup"</strong> metabox dekho — agar AI ka output pasand na aaye to <strong>"Restore Original Content"</strong> button dabao.',
                'tip'        => 'Rollback sirf ek click mein — page reload hoga aur original content wapas aa jayega.',
                'screenshot' => 'Sidebar → SGC Content Backup metabox → Restore button',
            ],
            [
                'icon'       => '✏️',
                'heading'    => 'AI Output Edit Karna',
                'body'       => 'One-Click ke baad Classic Editor mein content <strong>instantly update</strong> ho jaata hai — page reload ka wait nahi. Title field, TinyMCE content, aur excerpt — sab live update hote hain. Edit karo aur <strong>Update Product</strong> karo.',
                'tip'        => 'AI sirf base banata hai — aap chahe to title ya meta desc manually refine kar sakte ho.',
                'screenshot' => 'After click → editor fields auto-fill → Edit manually → Update',
            ],
        ],
        [
            'note' => 'AI generate hone mein 10-30 seconds lagte hain — button disabled rahega is dauran. Page band mat karo.',
        ]
    ); ?>

        <!-- Score ring -->
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
            <div style="width:60px;height:60px;border-radius:50%;border:4px solid <?php echo esc_attr($color); ?>;display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0;" id="sgc-oc-ring">
                <span style="font-size:18px;font-weight:700;color:<?php echo esc_attr($color); ?>;" id="sgc-oc-score"><?php echo $score; ?></span>
                <span style="font-size:9px;color:#999;">/100</span>
            </div>
            <div>
                <div style="font-size:13px;font-weight:600;color:#1a1a2e;" id="sgc-oc-label"><?php echo esc_html($label); ?></div>
                <?php if ( $gen_at ): ?>
                    <div style="font-size:10px;color:#999;">AI: <?php echo esc_html( date('d M Y', strtotime($gen_at)) ); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ( ! $ai_ok ): ?>
        <div style="background:#fff9e6;border:1px solid #f0c040;border-radius:6px;padding:10px;font-size:12px;color:#856404;margin-bottom:10px;">
            ⚠️ AI provider not configured.<br>
            <a href="<?php echo admin_url('admin.php?page=sgclabs-ai-center&tab=control'); ?>">→ Add API key</a>
        </div>
        <?php endif; ?>

        <!-- Main button -->
        <button id="sgc-oc-btn"
            onclick="sgcOneClickFix(<?php echo intval($pid); ?>, '<?php echo esc_js($nonce); ?>')"
            <?php echo ! $ai_ok ? 'disabled' : ''; ?>
            style="width:100%;padding:11px;background:#1a1a2e;color:#fff;border:none;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;margin-bottom:8px;">
            ✨ AI One-Click SEO Fix
        </button>

        <!-- Status -->
        <div id="sgc-oc-status" style="font-size:12px;color:#666;text-align:center;min-height:18px;"></div>

        <!-- Preview result -->
        <div id="sgc-oc-preview" style="display:none;margin-top:12px;border-top:1px solid #eee;padding-top:12px;">
            <div style="font-size:11px;font-weight:600;color:#999;text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px;">AI Generated Preview</div>
            <div style="margin-bottom:8px;">
                <div style="font-size:10px;color:#999;margin-bottom:2px;">SEO Title</div>
                <div id="sgc-oc-title" style="font-size:12px;color:#1a1a2e;background:#f8f9fa;padding:6px 8px;border-radius:4px;line-height:1.4;"></div>
                <div id="sgc-oc-title-len" style="font-size:10px;color:#999;margin-top:2px;"></div>
            </div>
            <div style="margin-bottom:8px;">
                <div style="font-size:10px;color:#999;margin-bottom:2px;">Focus Keyword</div>
                <div id="sgc-oc-kw" style="font-size:12px;color:#27ae60;background:#f0fdf4;padding:5px 8px;border-radius:4px;"></div>
            </div>
            <div style="margin-bottom:12px;">
                <div style="font-size:10px;color:#999;margin-bottom:2px;">Meta Description</div>
                <div id="sgc-oc-desc" style="font-size:11px;color:#555;background:#f8f9fa;padding:6px 8px;border-radius:4px;line-height:1.5;"></div>
                <div id="sgc-oc-desc-len" style="font-size:10px;color:#999;margin-top:2px;"></div>
            </div>
            <button onclick="sgcApplyFix(<?php echo intval($pid); ?>, '<?php echo esc_js($nonce); ?>')"
                id="sgc-oc-apply"
                style="width:100%;padding:9px;background:#27ae60;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;">
                ✅ Apply & Save All
            </button>
        </div>
    </div>

    <script>
    function sgcOneClickFix(pid, nonce) {
        var btn = document.getElementById('sgc-oc-btn');
        var status = document.getElementById('sgc-oc-status');
        btn.disabled = true;
        btn.innerHTML = '⏳ AI analyzing product...';
        status.textContent = 'Generating SEO title, keywords, description...';

        jQuery.post(ajaxurl, {
            action: 'sgclabs_ai_one_click_fix',
            nonce: nonce,
            post_id: pid
        }, function(res) {
            if (!res.success) {
                status.textContent = '❌ Error: ' + (res.data || 'Try again');
                btn.disabled = false;
                btn.innerHTML = '✨ AI One-Click SEO Fix';
                return;
            }
            var d = res.data;
            var scoreColor = d.score >= 80 ? '#27ae60' : d.score >= 50 ? '#f39c12' : '#e74c3c';

            // Update score ring
            document.getElementById('sgc-oc-score').textContent = d.score;
            document.getElementById('sgc-oc-score').style.color = scoreColor;
            document.getElementById('sgc-oc-ring').style.borderColor = scoreColor;
            document.getElementById('sgc-oc-label').textContent = d.score >= 80 ? 'Excellent! 🎉' : d.score >= 50 ? 'Good' : 'Improved';

            // Show preview
            document.getElementById('sgc-oc-title').textContent = d.title;
            document.getElementById('sgc-oc-title-len').textContent = d.title.length + ' chars';
            document.getElementById('sgc-oc-kw').textContent = d.focus_kw;
            document.getElementById('sgc-oc-desc').textContent = d.desc;
            document.getElementById('sgc-oc-desc-len').textContent = d.desc.length + ' chars | ' + d.alts + ' image alts updated';
            document.getElementById('sgc-oc-preview').style.display = 'block';

            btn.innerHTML = '✅ Done! Score: ' + d.score + '/100';
            btn.style.background = scoreColor;
            status.textContent = 'All fields saved. Page will reload in 3s...';

            // v6.6.6 — Classic Editor write-back (live update without waiting for reload)
            try {
                // Title field
                var titleField = document.getElementById('title');
                if (titleField && d.title) {
                    titleField.value = d.title;
                    // Trigger WP slug preview update
                    jQuery('#title').trigger('keyup');
                }
                // TinyMCE main content (Classic Editor)
                if (typeof tinymce !== 'undefined' && tinymce.get('content') && d.main) {
                    tinymce.get('content').setContent(d.main);
                    tinymce.get('content').fire('change');
                }
                // Excerpt / Short description
                if (typeof tinymce !== 'undefined' && tinymce.get('excerpt') && d.short) {
                    tinymce.get('excerpt').setContent(d.short);
                } else {
                    var excerptEl = document.getElementById('excerpt');
                    if (excerptEl && d.short) excerptEl.value = d.short;
                }
                // Focus keyword field (plugin metabox)
                var kwField = document.getElementById('sgc-focus-kw');
                if (kwField && d.focus_kw) kwField.value = d.focus_kw;
                // SEO Title field (plugin metabox)
                var seoTitleField = document.getElementById('sgc-seo-title');
                if (seoTitleField && d.title) {
                    seoTitleField.value = d.title;
                    seoTitleField.dispatchEvent(new Event('input'));
                }
                // SEO Description field (plugin metabox)
                var seoDescField = document.getElementById('sgc-seo-desc');
                if (seoDescField && d.desc) {
                    seoDescField.value = d.desc;
                    seoDescField.dispatchEvent(new Event('input'));
                }
            } catch(e) {
                console.warn('SGC write-back:', e);
            }

            setTimeout(() => location.reload(), 3000);
        }).fail(function() {
            status.textContent = '❌ Network error. Please try again.';
            btn.disabled = false;
            btn.innerHTML = '✨ AI One-Click SEO Fix';
        });
    }

    function sgcApplyFix(pid, nonce) {
        document.getElementById('sgc-oc-apply').textContent = '✅ Already saved!';
        document.getElementById('sgc-oc-apply').disabled = true;
    }
    </script>
    <?php
}

// ============================================================
// SECTION 4 — AI CONTENT CENTER ADMIN PAGE
// ============================================================

add_action( 'admin_menu', function () {
    add_submenu_page(
        'sgclabs-manager',
        'AI Content Center',
        '🧠 AI Content Center',
        'manage_options',
        'sgclabs-ai-content-center',
        'sgclabs_ace_admin_page'
    );
}, 12 );


// ── MODULE BUTTON + PROGRESS HELPERS ─────────────────────────
function sgclabs_ace_module_btns( string $mod, bool $ai_ok ): string {
    $dis = ! $ai_ok ? 'disabled style="opacity:.5"' : '';
    return <<<HTML
<div class="ace-btns">
    <button class="ace-btn btn-start"  id="mbtn-start-{$mod}"  onclick="aceModPlay('{$mod}')"  {$dis}>▶ Start</button>
    <button class="ace-btn btn-pause"  id="mbtn-pause-{$mod}"  onclick="aceModPause('{$mod}')" style="display:none">⏸ Pause</button>
    <button class="ace-btn btn-pause"  id="mbtn-resume-{$mod}" onclick="aceModPlay('{$mod}')"  style="display:none">▶ Resume</button>
    <button class="ace-btn btn-stop"   id="mbtn-stop-{$mod}"   onclick="aceModStop('{$mod}')"  style="display:none">⏹ Stop</button>
    <button class="ace-btn btn-reset"  onclick="aceModReset('{$mod}')">↺ Reset</button>
</div>
HTML;
}

function sgclabs_ace_module_prog( string $mod ): string {
    return <<<HTML
<div class="ace-prog-wrap" id="mprog-{$mod}" style="display:none">
    <div class="ace-prog-top">
        <span id="mstat-{$mod}">Processing...</span>
        <span id="mpct-{$mod}" style="font-weight:700;color:#1a1a2e;">0%</span>
    </div>
    <div class="ace-prog-bar"><div class="ace-prog-fill" id="mfill-{$mod}"></div></div>
    <div class="ace-counters">
        <div class="ace-ctr"><b id="mdone-{$mod}">0</b> done</div>
    </div>
</div>
HTML;
}

function sgclabs_ace_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    global $wpdb;

    $ai_ok    = function_exists('sgclabs_ai_any_configured') && sgclabs_ai_any_configured();
    $provider = function_exists('sgclabs_ai_active_provider') ? sgclabs_ai_active_provider() : 'none';
    $providers= function_exists('sgclabs_ai_providers') ? sgclabs_ai_providers() : [];
    $total    = (int) wp_count_posts('product')->publish;
    $nonce    = wp_create_nonce('sgclabs_nonce');

    $with_title      = (int)$wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_sgclabs_seo_title'");
    $with_meta_title = (int)$wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_sgclabs_meta_page_title'");
    $with_kw         = (int)$wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_sgclabs_focus_kw'");
    $with_meta_desc  = (int)$wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_sgclabs_ai_meta_desc'");
    $with_short_desc = (int)$wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_sgclabs_ai_short_desc'");
    $with_main_desc  = (int)$wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_sgclabs_ai_main_desc'");
    $score_90        = (int)$wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_sgclabs_seo_score' AND CAST(meta_value AS UNSIGNED) >= 90");

    $tog_title   = get_option('sgclabs_ace_tog_title',   '1');
    $tog_kw      = get_option('sgclabs_ace_tog_kw',      '1');
    $tog_desc    = get_option('sgclabs_ace_tog_desc',     '1');
    $tog_content = get_option('sgclabs_ace_tog_content',  '1');
    $tog_image   = get_option('sgclabs_ace_tog_image',    '1');
    $tog_gsc     = get_option('sgclabs_ace_tog_gsc_kw',  '1');
    $tog_schema  = get_option('sgclabs_ace_tog_schema',   '1');
    $tog_rewrite = get_option('sgclabs_ace_tog_rewrite',  '0');
    $batch_size  = intval(get_option('sgclabs_ace_batch',  3));

    $prov_label  = esc_html($providers[$provider]['label'] ?? $provider);
    $prov_model  = esc_html($providers[$provider]['model'] ?? '');

    // Load all saved progress for resume system
    $all_mods = ['master','titles','meta_title','keywords','meta','short_desc','main_desc','images','multilingual'];
    $saved_progress = [];
    foreach ($all_mods as $m) {
        $p = get_option("sgclabs_ace_prog_{$m}", null);
        if ($p) $saved_progress[$m] = $p;
    }
    ?>
    <div class="wrap sgclabs-wrap" id="sgc-ace-wrap">
    <style>
    #sgc-ace-wrap *{box-sizing:border-box}
    #sgc-ace-wrap .ace-hdr{display:flex;align-items:center;gap:10px;margin-bottom:24px;flex-wrap:wrap}
    #sgc-ace-wrap .ace-hdr h1{font-size:20px;font-weight:600;margin:0;color:#1a1a2e}
    #sgc-ace-wrap .ace-badge{font-size:11px;padding:2px 10px;border-radius:20px;font-weight:600}
    #sgc-ace-wrap .ace-stats{display:grid;grid-template-columns:repeat(7,1fr);gap:8px;margin-bottom:20px}
    #sgc-ace-wrap .ace-stat{background:#f8f9fa;border:1px solid #e8eaed;border-radius:10px;padding:12px 8px;text-align:center}
    #sgc-ace-wrap .ace-stat-n{font-size:22px;font-weight:700;line-height:1.1}
    #sgc-ace-wrap .ace-stat-l{font-size:10px;color:#888;margin-top:3px;line-height:1.3}
    #sgc-ace-wrap .ace-card{background:#fff;border:1px solid #e0e0e0;border-radius:12px;padding:20px;margin-bottom:16px}
    #sgc-ace-wrap .ace-card-title{font-size:15px;font-weight:700;color:#1a1a2e;margin-bottom:16px;display:flex;align-items:center;gap:8px}
    #sgc-ace-wrap .ace-tog-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:14px}
    #sgc-ace-wrap .ace-tog{border:1px solid #ddd;border-radius:8px;padding:10px 12px;cursor:pointer;transition:all .15s;user-select:none}
    #sgc-ace-wrap .ace-tog.tog-on{background:#f0fdf4;border-color:#86efac}
    #sgc-ace-wrap .ace-tog.tog-danger{background:#fef2f2;border-color:#fca5a5}
    #sgc-ace-wrap .ace-tog.tog-off{background:#f9f9f9;border-color:#e0e0e0}
    #sgc-ace-wrap .ace-tog-row{display:flex;align-items:center;gap:8px;margin-bottom:3px}
    #sgc-ace-wrap .ace-tog-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;transition:.15s}
    #sgc-ace-wrap .tog-on .ace-tog-dot{background:#16a34a}
    #sgc-ace-wrap .tog-danger .ace-tog-dot{background:#dc2626;animation:pulse .8s infinite}
    #sgc-ace-wrap .tog-off .ace-tog-dot{background:#ccc}
    #sgc-ace-wrap .ace-tog-name{font-size:12px;font-weight:600;color:#1a1a2e}
    #sgc-ace-wrap .ace-tog-desc{font-size:11px;color:#888;line-height:1.4}
    #sgc-ace-wrap .ace-tog.tog-on .ace-tog-desc{color:#15803d}
    #sgc-ace-wrap .ace-tog.tog-danger .ace-tog-desc{color:#b91c1c}
    #sgc-ace-wrap .ace-batch-row{display:flex;align-items:center;gap:8px;margin-bottom:14px;flex-wrap:wrap}
    #sgc-ace-wrap .ace-batch-row label{font-size:12px;color:#666;font-weight:600}
    #sgc-ace-wrap .ace-bopt{padding:5px 14px;border-radius:6px;border:1px solid #ddd;font-size:12px;cursor:pointer;transition:.15s;font-weight:500}
    #sgc-ace-wrap .ace-bopt:hover{border-color:#1a1a2e;color:#1a1a2e}
    #sgc-ace-wrap .ace-bopt.bsel{background:#1a1a2e;color:#fff;border-color:#1a1a2e}
    #sgc-ace-wrap .ace-btns{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
    #sgc-ace-wrap .ace-btn{padding:8px 18px;border-radius:8px;border:1px solid #ddd;background:#fff;font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;display:inline-flex;align-items:center;gap:6px}
    #sgc-ace-wrap .ace-btn:hover{transform:translateY(-1px)}
    #sgc-ace-wrap .ace-btn:active{transform:translateY(0) scale(.98)}
    #sgc-ace-wrap .btn-start{background:#1a1a2e;color:#fff;border-color:#1a1a2e}
    #sgc-ace-wrap .btn-start:hover{background:#2d2d4e;color:#fff}
    #sgc-ace-wrap .btn-pause{background:#fef3c7;color:#92400e;border-color:#fcd34d}
    #sgc-ace-wrap .btn-stop{background:#fef2f2;color:#991b1b;border-color:#fca5a5}
    #sgc-ace-wrap .btn-reset{border-style:dashed;color:#666}
    #sgc-ace-wrap .ace-prog-wrap{margin-top:6px}
    #sgc-ace-wrap .ace-prog-top{display:flex;justify-content:space-between;font-size:12px;color:#666;margin-bottom:6px}
    #sgc-ace-wrap .ace-prog-bar{height:8px;background:#f0f0f0;border-radius:4px;overflow:hidden}
    #sgc-ace-wrap .ace-prog-fill{height:100%;background:linear-gradient(90deg,#1a1a2e,#27ae60);border-radius:4px;transition:width .4s ease;width:0%}
    #sgc-ace-wrap .ace-counters{display:flex;gap:16px;margin-top:8px;flex-wrap:wrap}
    #sgc-ace-wrap .ace-ctr{font-size:11px;color:#999}
    #sgc-ace-wrap .ace-ctr b{font-size:14px;font-weight:700;color:#1a1a2e;margin-right:2px}
    #sgc-ace-wrap .ace-tabs{display:flex;border-bottom:2px solid #f0f0f0;gap:0;margin-bottom:16px;overflow-x:auto;flex-wrap:nowrap}
    #sgc-ace-wrap .ace-tab{padding:9px 16px;font-size:12px;font-weight:600;border:none;background:none;color:#888;cursor:pointer;border-bottom:3px solid transparent;white-space:nowrap;transition:.15s;margin-bottom:-2px}
    #sgc-ace-wrap .ace-tab.tab-on{color:#1a1a2e;border-bottom-color:#1a1a2e}
    #sgc-ace-wrap .ace-panel{display:none}
    #sgc-ace-wrap .ace-panel.panel-on{display:block}
    #sgc-ace-wrap .ace-panel-desc{font-size:12px;color:#666;margin-bottom:14px;line-height:1.6}
    #sgc-ace-wrap .ace-warn{background:#fef9e7;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;font-size:12px;color:#92400e;margin-bottom:14px}
    #sgc-ace-wrap .ace-chip{display:inline-flex;align-items:center;padding:4px 12px;background:#f0f4ff;border:1px solid #c7d2fe;border-radius:20px;font-size:11px;cursor:pointer;transition:.15s;margin:2px}
    #sgc-ace-wrap .ace-chip:hover{background:#e0e7ff;border-color:#818cf8}
    #sgc-ace-wrap .ace-history-row{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f5f5f5;font-size:12px}
    #sgc-ace-wrap .ace-score{padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700}
    #sgc-ace-wrap .score-hi{background:#f0fdf4;color:#15803d}
    #sgc-ace-wrap .score-md{background:#fef9e7;color:#92400e}
    #sgc-ace-wrap .score-lo{background:#fef2f2;color:#b91c1c}
    #sgc-ace-wrap .ace-radio-row{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:14px;font-size:12px}
    #sgc-ace-wrap .ace-radio-row label{display:flex;align-items:center;gap:5px;cursor:pointer}
    #sgc-ace-wrap .ace-resume-banner{background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #86efac;border-radius:10px;padding:12px 16px;margin-bottom:14px;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
    #sgc-ace-wrap .ace-resume-text{flex:1;font-size:12px;color:#15803d;font-weight:600}
    #sgc-ace-wrap .ace-resume-sub{font-size:11px;font-weight:400;color:#166534;margin-top:2px}
    #sgc-ace-wrap .btn-resume{background:#15803d;color:#fff;border-color:#15803d;font-size:12px;padding:7px 16px}
    #sgc-ace-wrap .btn-fresh{font-size:12px;padding:7px 14px;color:#666;border-color:#ddd}
    #sgc-ace-wrap .ace-panel-controls{background:#f8f9fa;border:1px solid #eee;border-radius:8px;padding:10px 14px;margin-bottom:14px;display:flex;align-items:center;gap:16px;flex-wrap:wrap}
    #sgc-ace-wrap .ace-panel-ctrl-label{font-size:11px;color:#666;font-weight:600;white-space:nowrap}
    #sgc-ace-wrap .ace-mini-tog{display:inline-flex;align-items:center;gap:6px;cursor:pointer;user-select:none;padding:4px 10px;border-radius:6px;border:1px solid #ddd;font-size:11px;font-weight:600;transition:.15s;background:#fff}
    #sgc-ace-wrap .ace-mini-tog.tog-on{background:#f0fdf4;border-color:#86efac;color:#15803d}
    #sgc-ace-wrap .ace-mini-tog.tog-danger{background:#fef2f2;border-color:#fca5a5;color:#b91c1c}
    #sgc-ace-wrap .ace-mini-tog .dot{width:7px;height:7px;border-radius:50%;background:#ccc;transition:.15s}
    #sgc-ace-wrap .ace-mini-tog.tog-on .dot{background:#16a34a}
    #sgc-ace-wrap .ace-mini-tog.tog-danger .dot{background:#dc2626}
    #sgc-ace-wrap .ace-mbopt{padding:3px 10px;border-radius:5px;border:1px solid #ddd;font-size:11px;cursor:pointer;transition:.15s;font-weight:500;background:#fff}
    #sgc-ace-wrap .ace-mbopt.bsel{background:#1a1a2e;color:#fff;border-color:#1a1a2e}
    #sgc-ace-wrap .ace-radio-inline{display:flex;gap:10px;flex-wrap:wrap;font-size:11px}
    #sgc-ace-wrap .ace-radio-inline label{display:flex;align-items:center;gap:4px;cursor:pointer}
    #sgc-ace-wrap .ace-done-list{max-height:200px;overflow-y:auto;margin-top:10px;border:1px solid #f0f0f0;border-radius:8px;background:#fafafa}
    #sgc-ace-wrap .ace-done-row{display:flex;align-items:center;gap:8px;padding:5px 10px;border-bottom:1px solid #f5f5f5;font-size:11px;animation:fadeSlide .3s ease}
    #sgc-ace-wrap .ace-done-row:last-child{border-bottom:none}
    #sgc-ace-wrap .ace-done-id{color:#aaa;min-width:36px}
    #sgc-ace-wrap .ace-done-name{flex:1;font-weight:600;color:#1a1a2e;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    #sgc-ace-wrap .ace-done-val{color:#15803d;font-size:10px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    #sgc-ace-wrap .ace-mod-block-stat{font-size:11px;color:#888;margin-bottom:10px;padding:6px 10px;background:#f0f4ff;border-radius:6px}
    #sgc-ace-wrap .ace-mod-block-stat b{color:#1a1a2e;font-size:14px;font-weight:700}
    @keyframes fadeSlide{from{opacity:0;transform:translateX(-6px)}to{opacity:1;transform:translateX(0)}}
    @keyframes pulse{0%,100%{opacity:1}50%{opacity:.5}}
    </style>

    <!-- Header -->
    <div class="ace-hdr">
        <h1>🧠 AI Content Center</h1>
        <span class="ace-badge" style="background:#1a1a2e;color:#fff;">v6.6.7</span>
        <span class="ace-badge" style="background:<?php echo $ai_ok?'#f0fdf4':'#fef2f2';?>;color:<?php echo $ai_ok?'#15803d':'#b91c1c';?>;">
            <?php echo $ai_ok ? "🟢 {$prov_label}" : '❌ No AI configured'; ?>
        </span>
        <?php if(!$ai_ok): ?>
        <a href="<?php echo admin_url('admin.php?page=sgclabs-ai-center&tab=control');?>" style="font-size:12px;color:#3451b2;">→ Add API key</a>
        <?php endif; ?>
        <a href="<?php echo admin_url('admin.php?page=sgclabs-bg-processor'); ?>"
           style="margin-left:auto;font-size:12px;background:#1a1a2e;color:#fff;padding:6px 14px;border-radius:20px;text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:5px;">
           🖥️ Background Processor
        </a>
    </div>

    <?php sgclabs_help_box(
        'ace-main',
        'How to use AI Content Center — Step by Step SOP',
        [
            [
                'icon'       => '🔑',
                'heading'    => 'API Key Configure Karo (First Time Only)',
                'body'       => 'Pehle AI provider set karo: <strong>SGC LABS → AI Center → Control Center</strong> mein jao. Gemini 2.5 Flash recommend hai — free tier mein 7000+ products handle hote hain. API key paste karo aur Save karo.',
                'tip'        => 'Gemini key milti hai: aistudio.google.com → Get API Key → Free plan kaafi hai.',
                'screenshot' => 'AI Center → Control Center → Gemini Key field',
            ],
            [
                'icon'       => '📊',
                'heading'    => 'Stats Check Karo — Kitna Kaam Bacha Hai',
                'body'       => 'Page ke top mein stats bar hai: <strong>Total Products, SEO Titles, Meta Titles, Keywords, Meta Desc, Short Desc, Main Desc</strong>. Jo field 0 hai, usse generate karna hai.',
                'tip'        => '0 wale fields pehle target karo — sabse zyada impact milega.',
                'screenshot' => 'Top stats bar — green numbers = done, orange = pending',
            ],
            [
                'icon'       => '⚡',
                'heading'    => 'Individual Module Tabs — Ek Field at a Time',
                'body'       => 'Neeche tabs hain: <code>SEO Titles | Meta Title | Keywords | Meta Desc | Short Desc | Main Desc | Images | Multilingual</code>. Ek tab select karo → Batch size choose karo (3 default) → <strong>▶ Start</strong> dabao. Auto-resume karta hai agar beech mein band karo.',
                'tip'        => 'Sirf SEO Titles se shuru karo — 1 ghante mein 7000 products ke titles generate ho jayenge.',
                'screenshot' => 'Tab row → SEO Titles tab → Start button',
            ],
            [
                'icon'       => '🔄',
                'heading'    => 'Batch Size aur Delay Set Karo',
                'body'       => 'Har tab mein <strong>Batch</strong> selector hai (1/2/3/5/10). Master Engine mein <strong>Delay slider</strong> bhi hai (0.5s–5s). Free Gemini tier ke liye: Batch=3, Delay=2s. Paid API ke liye: Batch=10, Delay=0.5s.',
                'tip'        => '429 Rate Limit error aaye to Batch=1 aur Delay=3s karo.',
                'screenshot' => 'Batch selector + Delay slider in Master Engine card',
            ],
            [
                'icon'       => '⚡',
                'heading'    => 'Master Engine — Sab Ek Saath (Advanced)',
                'body'       => 'Top card <strong>"⚡ Master AI Engine"</strong> click karke expand karo. Toggles se choose karo konsa field generate karna hai. DB Backup lena zaroori hai pehle — checkbox check karo → Start All.',
                'tip'        => 'Master Engine sirf experienced users ke liye — individual tabs zyada safe hain.',
                'screenshot' => 'Master Engine card → expand → toggles → backup checkbox → Start All',
            ],
            [
                'icon'       => '⏸️',
                'heading'    => 'Pause / Resume — Kabhi Bhi Band Karo',
                'body'       => 'Beech mein <strong>⏸ Pause</strong> dabao — progress DB mein save ho jaata hai. Baad mein page reload karo — <strong>"Resume available"</strong> banner dikhega. Resume karo ya fresh start karo.',
                'tip'        => 'Browser band karne se pehle Pause karo — warna progress kho sakta hai.',
                'screenshot' => 'Running state → Pause button → Resume banner on reload',
            ],
            [
                'icon'       => '✅',
                'heading'    => 'Verify Karo — Product Page Check',
                'body'       => 'Koi bhi product edit karo → right sidebar mein <strong>"✨ AI One-Click SEO Fix"</strong> metabox dikhega. SEO Score ring dikhega (0–100). Title, meta desc, focus keyword — sab auto-fill ho chuke honge.',
                'tip'        => 'Score 80+ aaye to excellent. 50-79 = good. 50 se kam = individual tabs se improvement karo.',
                'screenshot' => 'Product edit page → sidebar → AI One-Click SEO Fix metabox',
            ],
        ],
        [
            'note' => '7000+ products ke liye estimated time: SEO Titles ~1-2 hrs, Meta Desc ~1-2 hrs, Full content ~4-6 hrs (Batch=3, Delay=2s).',
        ]
    ); ?>

    <!-- Stats -->
    <div class="ace-stats">
        <?php foreach([
            [$total,          'Total Products',   '#1a1a2e'],
            [$with_title,     'SEO Titles',       '#15803d'],
            [$with_meta_title,'Meta Titles',      '#0284c7'],
            [$with_kw,        'Focus Keywords',   '#2563eb'],
            [$with_meta_desc, 'Meta Desc',        '#7c3aed'],
            [$with_short_desc,'Short Desc',       '#c2410c'],
            [$with_main_desc, 'Main Desc',        '#b45309'],
        ] as [$n,$l,$c]): ?>
        <div class="ace-stat">
            <div class="ace-stat-n" style="color:<?php echo $c;?>"><?php echo number_format($n);?></div>
            <div class="ace-stat-l"><?php echo esc_html($l);?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- MASTER ENGINE CARD — collapsible -->
    <div class="ace-card" style="border-color:#1a1a2e;">
        <div class="ace-card-title" style="cursor:pointer;display:flex;align-items:center;justify-content:space-between;"
            onclick="sgcToggleMaster()">
            <span>⚡ Master AI engine
                <span style="font-size:11px;background:#f0f4ff;color:#3451b2;padding:2px 10px;border-radius:10px;font-weight:500;margin-left:6px;">All modules — one click</span>
            </span>
            <span id="master-chevron" style="font-size:14px;color:#888;">▼ expand</span>
        </div>

        <div id="master-body" style="display:none;">

        <!-- Toggles -->
        <div class="ace-tog-grid" style="margin-top:14px;">
        <?php
        $togs = [
            ['title',   '📝 SEO title',        $tog_title,   'Google 50-60 char', false],
            ['kw',      '🔑 Focus keyword',    $tog_kw,      'Real search intent', false],
            ['desc',    '📄 Meta description', $tog_desc,    '130-155 chars', false],
            ['content', '✍️ Product content',  $tog_content, 'Short + main desc', false],
            ['image',   '🖼️ Image alt text',   $tog_image,   'Per-image AI alt', false],
            ['gsc_kw',  '🌐 GSC keyword inject',$tog_gsc,    'Auto-inject in desc', false],
            ['schema',  '📋 Schema desc',      $tog_schema,  'Unique JSON-LD', false],
            ['rewrite', '🔄 Force rewrite',    $tog_rewrite, 'Flush + regenerate!', true],
        ];
        foreach($togs as [$id,$name,$val,$desc,$danger]):
            if($danger) $cls = $val==='1' ? 'tog-danger' : 'tog-off';
            else        $cls = $val==='1' ? 'tog-on'     : 'tog-off';
        ?>
        <div class="ace-tog <?php echo $cls;?>" id="tog-<?php echo esc_attr($id);?>"
            onclick="aceToggle('<?php echo esc_js($id);?>','<?php echo esc_js($danger?'danger':'normal');?>')"
            data-key="sgclabs_ace_tog_<?php echo esc_attr($id);?>" data-val="<?php echo $val==='1'?'1':'0';?>">
            <div class="ace-tog-row">
                <div class="ace-tog-dot"></div>
                <div class="ace-tog-name"><?php echo esc_html($name);?></div>
            </div>
            <div class="ace-tog-desc"><?php echo esc_html($desc);?></div>
        </div>
        <?php endforeach;?>
        </div>

        <!-- Batch size -->
        <div class="ace-batch-row">
            <label>Batch per run:</label>
            <?php foreach([1=>'1',2=>'2',3=>'3 default',5=>'5',10=>'10 fast'] as $v=>$l):?>
            <div class="ace-bopt <?php echo $batch_size==$v?'bsel':'';?>" onclick="aceBatch(this,<?php echo $v;?>)"><?php echo esc_html($l);?></div>
            <?php endforeach;?>
        </div>

        <!-- Delay slider -->
        <div class="ace-batch-row" style="margin-top:6px;gap:10px;align-items:center;">
            <label style="font-size:12px;color:#666;font-weight:600;">Batch delay:</label>
            <input type="range" id="master-delay-slider" min="500" max="5000" step="500" value="2000"
                style="width:130px;cursor:pointer;"
                oninput="document.getElementById('master-delay-val').textContent=(this.value/1000).toFixed(1)+'s';aceBatchDelay=parseInt(this.value);">
            <span id="master-delay-val" style="font-size:13px;font-weight:700;color:#1a1a2e;min-width:32px;">2.0s</span>
            <span style="font-size:11px;color:#aaa;">between batches — prevents 429 rate limit</span>
        </div>

        <!-- DB Backup Warning -->
        <div style="background:#fef9e7;border:1px solid #fcd34d;border-radius:10px;padding:12px 16px;margin-bottom:14px;display:flex;align-items:flex-start;gap:12px;margin-top:14px;">
            <span style="font-size:20px;flex-shrink:0;">⚠️</span>
            <div style="flex:1;">
                <div style="font-size:13px;font-weight:700;color:#92400e;margin-bottom:4px;">Bulk run se pehle database backup lo!</div>
                <div style="font-size:12px;color:#78350f;margin-bottom:8px;">7000+ products overwrite honge. Rollback ke liye backup zaroori hai.</div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sgclabs-site-tools-hub&tab=tools')); ?>"
                   style="font-size:11px;background:#92400e;color:#fff;padding:5px 12px;border-radius:6px;text-decoration:none;font-weight:600;margin-right:8px;">
                   📥 Backup Karo Pehle
                </a>
                <label style="font-size:11px;color:#78350f;cursor:pointer;display:inline-flex;align-items:center;gap:4px;">
                    <input type="checkbox" id="sgc-backup-confirm" onchange="document.getElementById('master-start-btn').disabled=!this.checked;" style="margin:0;">
                    Backup le liya hai, proceed karo
                </label>
            </div>
        </div>

        <!-- Buttons -->
        <div class="ace-btns">
            <button class="ace-btn btn-start" id="master-start-btn" onclick="aceMasterPlay()" <?php echo !$ai_ok?'disabled style="opacity:.5"':'disabled style="opacity:.5" title="Pehle backup confirm karo"'; ?>>▶ Start all</button>
            <button class="ace-btn btn-pause" id="master-pause-btn" onclick="aceMasterPause()" style="display:none">⏸ Pause</button>
            <button class="ace-btn btn-pause" id="master-resume-btn" onclick="aceMasterPlay()" style="display:none">▶ Resume</button>
            <button class="ace-btn btn-stop"  id="master-stop-btn"  onclick="aceMasterStop()"  style="display:none">⏹ Stop</button>
            <button class="ace-btn btn-reset" onclick="aceMasterReset()">↺ Reset</button>
        </div>

        <!-- Progress -->
        <div class="ace-prog-wrap">
            <div class="ace-prog-top">
                <span id="master-status">Ready — configure toggles then press Start</span>
                <span id="master-pct" style="font-weight:700;color:#1a1a2e;">0%</span>
            </div>
            <div class="ace-prog-bar"><div class="ace-prog-fill" id="master-fill"></div></div>
            <div class="ace-counters">
                <div class="ace-ctr"><b id="m-done">0</b>done</div>
                <div class="ace-ctr"><b id="m-total"><?php echo $total;?></b>total</div>
                <div class="ace-ctr"><b id="m-errors">0</b>errors</div>
                <div class="ace-ctr">avg score <b id="m-score">—</b></div>
            </div>
            <div id="m-done-list" class="ace-done-list" style="display:none"></div>
        </div>

        </div><!-- /master-body -->
    </div>

    <!-- MODULE TABS CARD -->
    <div class="ace-card">
        <div class="ace-tabs">
        <?php foreach([
            ['titles',      '📝 SEO Titles'],
            ['meta_title',  '🏷️ Meta Title'],
            ['keywords',    '🔑 Keywords'],
            ['meta',        '📄 Meta Desc'],
            ['short_desc',  '✍️ Short Desc'],
            ['main_desc',   '📖 Main Desc'],
            ['images',      '🖼️ Images'],
            ['multilingual','🌍 Multilingual'],
            ['oneclick',    '✨ One-click'],
            ['history',     '📊 History'],
        ] as $i=>[$tid,$tlabel]):?>
        <button class="ace-tab <?php echo $i===0?'tab-on':'';?>"
            id="tab-btn-<?php echo $tid;?>"
            onclick="aceTab('<?php echo $tid;?>',this)"><?php echo esc_html($tlabel);?></button>
        <?php endforeach;?>
        </div>

        <?php
        // Helper: render resume banner for a module
        function sgclabs_ace_resume_banner(string $mod, array $saved): string {
            if (empty($saved[$mod]) || !is_array($saved[$mod])) return '';
            $p = $saved[$mod];
            if (!empty($p['finished'])) return '';
            $done  = intval($p['done'] ?? 0);
            $total = intval($p['total'] ?? 0);
            $ts    = esc_html($p['last_batch'] ?? '');
            if (!$done || !$total) return '';
            $pct   = $total ? round($done/$total*100) : 0;
            $mod_js = esc_js($mod);
            return "<div class='ace-resume-banner' id='resume-banner-{$mod}'>
                <div class='ace-resume-text'>⚡ Resume available
                    <div class='ace-resume-sub'>{$done} / {$total} done ({$pct}%) — last run: {$ts}</div>
                </div>
                <button class='ace-btn btn-resume' onclick=\"aceModResume('{$mod_js}')\">▶ Resume from #{$done}</button>
                <button class='ace-btn btn-fresh' onclick=\"aceModFresh('{$mod_js}')\">↺ Start fresh</button>
            </div>";
        }
        // Helper: per-panel controls bar
        function sgclabs_ace_panel_controls(string $mod, int $batch_size, bool $ai_ok, string $extra_radio = ''): string {
            $dis = !$ai_ok ? 'disabled style="opacity:.5"' : '';
            $b_html = '';
            foreach ([1=>'1',2=>'2',3=>'3',5=>'5',10=>'10'] as $v=>$l) {
                $sel = $batch_size==$v ? 'bsel' : '';
                $b_html .= "<span class='ace-mbopt {$sel}' onclick=\"aceModBatch('{$mod}',{$v},this)\">{$l}</span>";
            }
            $rw_val = get_option("sgclabs_ace_rw_{$mod}", '0');
            $rw_cls = $rw_val==='1' ? 'tog-danger' : 'tog-off';
            $rw_dot = $rw_val==='1' ? '●' : '○';
            return "<div class='ace-panel-controls'>
                <span class='ace-panel-ctrl-label'>Batch:</span>{$b_html}
                <span style='width:1px;height:20px;background:#ddd;margin:0 4px'></span>
                <span class='ace-mini-tog {$rw_cls}' id='rw-tog-{$mod}'
                    onclick=\"aceMiniToggle('rw_{$mod}','danger',this)\"
                    data-val='{$rw_val}'>
                    <span class='dot'></span> Force Rewrite
                </span>
                {$extra_radio}
            </div>";
        }
        ?>

        <!-- SEO Titles -->
        <div class="ace-panel panel-on" id="panel-titles">
            <div class="ace-panel-desc">Google-optimized 50-60 char SEO title — commercial intent. Saved to RankMath, Yoast + DB. Unique per product.</div>
            <div class="ace-mod-block-stat">✅ DB mein saved: <b><?php echo $with_title;?></b> / <b><?php echo $total;?></b> products</div>
            <?php echo sgclabs_ace_resume_banner('titles', $saved_progress); ?>
            <?php echo sgclabs_ace_panel_controls('titles', $batch_size, $ai_ok,
                "<span style='width:1px;height:20px;background:#ddd;margin:0 4px'></span>
                 <label class='ace-radio-inline' style='font-size:11px;display:flex;align-items:center;gap:4px;cursor:pointer;'>
                 <input type='checkbox' id='titles-blank-only' checked style='margin:0;'> Skip existing (blank only)</label>"); ?>
            <?php echo sgclabs_ace_module_btns('titles', $ai_ok); ?>
            <?php echo sgclabs_ace_module_prog('titles'); ?>
            <div id="mdone-list-titles" class="ace-done-list" style="display:none"></div>
        </div>

        <!-- Meta Title -->
        <div class="ace-panel" id="panel-meta_title">
            <div class="ace-panel-desc">HTML <code>&lt;title&gt;</code> tag — CTR-optimized 50-60 chars. Saved to RankMath + Yoast. Unique buyer-intent title per product.</div>
            <div class="ace-mod-block-stat">✅ DB mein saved: <b><?php echo $with_meta_title;?></b> / <b><?php echo $total;?></b> products</div>
            <?php echo sgclabs_ace_resume_banner('meta_title', $saved_progress); ?>
            <?php echo sgclabs_ace_panel_controls('meta_title', $batch_size, $ai_ok,
                "<span style='width:1px;height:20px;background:#ddd;margin:0 4px'></span>
                 <label class='ace-radio-inline' style='font-size:11px;display:flex;align-items:center;gap:4px;cursor:pointer;'>
                 <input type='radio' name='mta-mode' value='blank' checked style='margin:0;'> Skip existing</label>
                 <label class='ace-radio-inline' style='font-size:11px;display:flex;align-items:center;gap:4px;cursor:pointer;'>
                 <input type='radio' name='mta-mode' value='all' style='margin:0;'> All products</label>"); ?>
            <?php echo sgclabs_ace_module_btns('meta_title', $ai_ok); ?>
            <?php echo sgclabs_ace_module_prog('meta_title'); ?>
            <div id="mdone-list-meta_title" class="ace-done-list" style="display:none"></div>
        </div>

        <!-- Keywords -->
        <div class="ace-panel" id="panel-keywords">
            <div class="ace-panel-desc">Real search-intent focus keywords — what buyers actually type on Google. Saved to RankMath + Yoast + DB.</div>
            <div class="ace-mod-block-stat">✅ DB mein saved: <b><?php echo $with_kw;?></b> / <b><?php echo $total;?></b> products</div>
            <?php echo sgclabs_ace_resume_banner('keywords', $saved_progress); ?>
            <?php echo sgclabs_ace_panel_controls('keywords', $batch_size, $ai_ok); ?>
            <?php echo sgclabs_ace_module_btns('keywords', $ai_ok); ?>
            <?php echo sgclabs_ace_module_prog('keywords'); ?>
            <div id="mdone-list-keywords" class="ace-done-list" style="display:none"></div>
            <div style="background:#f8f9ff;border:1px solid #e0e4ff;border-radius:8px;padding:14px;margin-top:16px;">
                <div style="font-size:12px;font-weight:700;margin-bottom:10px;color:#1a1a2e;">🔍 Manual keyword suggest (single product)</div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                    <input type="text" id="kw-product" placeholder="Product name" style="flex:1;min-width:160px;font-size:12px;padding:6px 10px;border:1px solid #ddd;border-radius:6px;">
                    <input type="text" id="kw-cat" placeholder="Category" style="width:150px;font-size:12px;padding:6px 10px;border:1px solid #ddd;border-radius:6px;">
                    <button class="ace-btn btn-start" style="font-size:12px;padding:6px 14px;" onclick="aceKeywords()" <?php echo !$ai_ok?'disabled':'';?>>🤖 Generate</button>
                </div>
                <div id="kw-result"></div>
            </div>
        </div>

        <!-- Meta Desc -->
        <div class="ace-panel" id="panel-meta">
            <div class="ace-panel-desc">Unique 130-155 char meta description — AI-written per product. Saved to RankMath, Yoast, _sgclabs_seo_description.</div>
            <div class="ace-mod-block-stat">✅ DB mein saved: <b><?php echo $with_meta_desc;?></b> / <b><?php echo $total;?></b> products</div>
            <?php echo sgclabs_ace_resume_banner('meta', $saved_progress); ?>
            <?php echo sgclabs_ace_panel_controls('meta', $batch_size, $ai_ok,
                "<span style='width:1px;height:20px;background:#ddd;margin:0 4px'></span>
                 <label class='ace-radio-inline' style='font-size:11px;display:flex;align-items:center;gap:4px;cursor:pointer;'>
                 <input type='radio' name='meta-mode' value='blank' checked style='margin:0;'> Skip existing</label>
                 <label class='ace-radio-inline' style='font-size:11px;display:flex;align-items:center;gap:4px;cursor:pointer;'>
                 <input type='radio' name='meta-mode' value='all' style='margin:0;'> All products</label>"); ?>
            <?php echo sgclabs_ace_module_btns('meta', $ai_ok); ?>
            <?php echo sgclabs_ace_module_prog('meta'); ?>
            <div id="mdone-list-meta" class="ace-done-list" style="display:none"></div>
        </div>

        <!-- Short Desc -->
        <div class="ace-panel" id="panel-short_desc">
            <div class="ace-panel-desc">WooCommerce short description (post_excerpt) — 100-160 chars, keyword-rich. Auto-saved to DB + WooCommerce post_excerpt.</div>
            <div class="ace-mod-block-stat">✅ DB mein saved: <b><?php echo $with_short_desc;?></b> / <b><?php echo $total;?></b> products &nbsp;|&nbsp; auto-targets blank (&lt;50 chars)</div>
            <?php echo sgclabs_ace_resume_banner('short_desc', $saved_progress); ?>
            <?php echo sgclabs_ace_panel_controls('short_desc', $batch_size, $ai_ok,
                "<span style='width:1px;height:20px;background:#ddd;margin:0 4px'></span>
                 <label class='ace-radio-inline' style='font-size:11px;display:flex;align-items:center;gap:4px;cursor:pointer;'>
                 <input type='radio' name='sdesc-mode' value='blank' checked style='margin:0;'> Blank only (&lt;50 chars)</label>
                 <label class='ace-radio-inline' style='font-size:11px;display:flex;align-items:center;gap:4px;cursor:pointer;'>
                 <input type='radio' name='sdesc-mode' value='all' style='margin:0;'> All products</label>"); ?>
            <?php echo sgclabs_ace_module_btns('short_desc', $ai_ok); ?>
            <?php echo sgclabs_ace_module_prog('short_desc'); ?>
            <div id="mdone-list-short_desc" class="ace-done-list" style="display:none"></div>
        </div>

        <!-- Main Desc -->
        <div class="ace-panel" id="panel-main_desc">
            <div class="ace-panel-desc">Full WooCommerce product description — 400-600 words, HTML with H2/H3/tables, Google E-E-A-T. Auto-saved to post_content + DB.</div>
            <div class="ace-warn">⚠️ Ye module directly <strong>post_content</strong> overwrite karta hai. Force Rewrite ON = existing content replace hoga.</div>
            <div class="ace-mod-block-stat">✅ DB mein saved: <b><?php echo $with_main_desc;?></b> / <b><?php echo $total;?></b> products &nbsp;|&nbsp; auto-targets thin (&lt;300 chars)</div>
            <?php echo sgclabs_ace_resume_banner('main_desc', $saved_progress); ?>
            <?php echo sgclabs_ace_panel_controls('main_desc', $batch_size, $ai_ok,
                "<span style='width:1px;height:20px;background:#ddd;margin:0 4px'></span>
                 <label class='ace-radio-inline' style='font-size:11px;display:flex;align-items:center;gap:4px;cursor:pointer;'>
                 <input type='radio' name='mdesc-mode' value='thin' checked style='margin:0;'> Thin content only (&lt;300)</label>
                 <label class='ace-radio-inline' style='font-size:11px;display:flex;align-items:center;gap:4px;cursor:pointer;'>
                 <input type='radio' name='mdesc-mode' value='all' style='margin:0;'> All products</label>"); ?>
            <?php echo sgclabs_ace_module_btns('main_desc', $ai_ok); ?>
            <?php echo sgclabs_ace_module_prog('main_desc'); ?>
            <div id="mdone-list-main_desc" class="ace-done-list" style="display:none"></div>
        </div>

        <!-- Images -->
        <div class="ace-panel" id="panel-images">
            <div class="ace-panel-desc">Context-aware image alt text — AI reads product name + category per image. Saved to _wp_attachment_image_alt.</div>
            <?php echo sgclabs_ace_resume_banner('images', $saved_progress); ?>
            <?php echo sgclabs_ace_panel_controls('images', $batch_size, $ai_ok); ?>
            <?php echo sgclabs_ace_module_btns('images', $ai_ok); ?>
            <?php echo sgclabs_ace_module_prog('images'); ?>
            <div id="mdone-list-images" class="ace-done-list" style="display:none"></div>
        </div>

        <!-- Multilingual -->
        <div class="ace-panel" id="panel-multilingual">
            <div class="ace-panel-desc">Real AI translations — not dictionary word-swap. Hreflang + localized SEO for each market.</div>
            <?php echo sgclabs_ace_resume_banner('multilingual', $saved_progress); ?>
            <?php echo sgclabs_ace_panel_controls('multilingual', min($batch_size,3), $ai_ok); ?>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;">
            <?php $langs = function_exists('sgclabs_get_languages') ? sgclabs_get_languages() : [];
            foreach($langs as $code=>$info): ?>
            <label class="ace-chip">
                <input type="checkbox" name="ace-langs[]" value="<?php echo esc_attr($code);?>" checked style="width:12px;height:12px;">
                <?php echo esc_html($info['flag'].' '.$info['name']);?>
            </label>
            <?php endforeach;?>
            </div>
            <?php echo sgclabs_ace_module_btns('multilingual', $ai_ok); ?>
            <?php echo sgclabs_ace_module_prog('multilingual'); ?>
            <div id="mdone-list-multilingual" class="ace-done-list" style="display:none"></div>
        </div>

        <!-- One-Click -->
        <div class="ace-panel" id="panel-oneclick">
            <div class="ace-panel-desc">Fix a single product — all SEO fields in one click. Same button also appears on the product edit page sidebar.</div>
            <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;">
                <select id="oc-product" style="flex:1;font-size:12px;padding:7px 10px;border:1px solid #ddd;border-radius:6px;">
                    <option value="">— Select product —</option>
                    <?php
                    $prods = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' ORDER BY ID DESC LIMIT 100");
                    foreach($prods as $p): ?>
                    <option value="<?php echo intval($p->ID);?>">#<?php echo intval($p->ID);?> — <?php echo esc_html(substr($p->post_title,0,55));?></option>
                    <?php endforeach;?>
                </select>
                <button class="ace-btn btn-start" onclick="aceOneClick()" <?php echo !$ai_ok?'disabled':'';?>>✨ AI Fix this product</button>
            </div>
            <div id="oc-result" style="background:#f8f9fa;border-radius:8px;padding:12px;font-size:12px;color:#888;min-height:60px;">
                Select a product and click AI Fix — all SEO fields will be generated and saved.
            </div>
        </div>

        <!-- History -->
        <div class="ace-panel" id="panel-history">
            <div class="ace-panel-desc">Products AI has processed — SEO score, date generated, regenerate option.</div>
            <?php
            $history = $wpdb->get_results("
                SELECT p.ID, p.post_title,
                    MAX(CASE WHEN pm.meta_key='_sgclabs_ai_gen_at'  THEN pm.meta_value END) as gen_at,
                    MAX(CASE WHEN pm.meta_key='_sgclabs_seo_score'  THEN pm.meta_value END) as score
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id
                WHERE p.post_type='product' AND p.post_status='publish'
                AND pm.meta_key IN('_sgclabs_ai_gen_at','_sgclabs_seo_score')
                GROUP BY p.ID HAVING gen_at IS NOT NULL
                ORDER BY gen_at DESC LIMIT 50
            ");
            if($history): foreach($history as $h):
                $sc = intval($h->score);
                $scls = $sc>=90?'score-hi':($sc>=70?'score-md':'score-lo');
            ?>
            <div class="ace-history-row">
                <div style="flex:1;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <a href="<?php echo get_edit_post_link($h->ID);?>" style="color:#1a1a2e;text-decoration:none;"><?php echo esc_html($h->post_title);?></a>
                </div>
                <div style="color:#aaa;white-space:nowrap;font-size:11px;"><?php echo $h->gen_at ? esc_html(date('d M Y H:i', strtotime($h->gen_at))) : '—';?></div>
                <div class="ace-score <?php echo $scls;?>"><?php echo $sc?$sc.'/100':'—';?></div>
                <button class="ace-btn btn-reset" style="font-size:11px;padding:4px 10px;" onclick="aceOneClickPid(<?php echo intval($h->ID);?>)">↺ Regen</button>
            </div>
            <?php endforeach; else: ?>
            <div style="text-align:center;padding:24px;color:#aaa;font-size:13px;">No AI-processed products yet. Run a module above!</div>
            <?php endif;?>
        </div>

    </div><!-- end module tabs card -->
    </div><!-- end wrap -->

    <script>
    var aceNonce   = '<?php echo esc_js($nonce);?>';
    var aceTotal   = <?php echo intval($total);?>;
    var acePaused  = false;
    var aceRunning = false;
    var aceOffset  = 0;
    var aceTimer   = null;
    var aceMDone   = 0, aceMErrors = 0, aceMScoreSum = 0;
    var modOffsets = {};
    var modPaused  = {};
    var modTimers  = {};
    var modBatch   = {};  // per-module batch size
    var aceBatchDelay = 2000; // ms between batches

    // Master card toggle
    window.sgcToggleMaster = function() {
        var body = document.getElementById('master-body');
        var chev = document.getElementById('master-chevron');
        if (body.style.display === 'none') { body.style.display = 'block'; chev.textContent = '▲ collapse'; }
        else { body.style.display = 'none'; chev.textContent = '▼ expand'; }
    };

    // Saved progress from server (for resume)
    var aceSavedProgress = <?php echo wp_json_encode($saved_progress); ?>;

    // ── Save progress to server ───────────────────────────────
    function aceSaveProgress(mod, offset, done, total, finished) {
        jQuery.post(ajaxurl,{
            action:'sgclabs_ace_save_progress', nonce:aceNonce,
            mod:mod, offset:offset, done:done, total:total, finished:finished?1:0,
            last_batch: new Date().toLocaleString('en-IN',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'})
        });
    }

    // ── Mini toggle (per-panel rewrite toggle) ────────────────
    function aceMiniToggle(key, type, el) {
        var cur = el.getAttribute('data-val');
        var nv  = cur==='1' ? '0' : '1';
        el.setAttribute('data-val', nv);
        if(type==='danger') el.className = 'ace-mini-tog ' + (nv==='1' ? 'tog-danger' : 'tog-off');
        else el.className = 'ace-mini-tog ' + (nv==='1' ? 'tog-on' : 'tog-off');
        jQuery.post(ajaxurl,{action:'sgclabs_ace_save_toggle',nonce:aceNonce,key:'sgclabs_ace_'+key,val:nv});
    }

    // ── Per-module batch selector ─────────────────────────────
    function aceModBatch(mod, v, el) {
        el.closest('.ace-panel-controls').querySelectorAll('.ace-mbopt').forEach(function(b){b.classList.remove('bsel');});
        el.classList.add('bsel');
        modBatch[mod] = v;
    }

    // ── Global toggles ────────────────────────────────────────
    function aceToggle(id, type) {
        var el  = document.getElementById('tog-'+id);
        var cur = el.getAttribute('data-val');
        var nv  = cur==='1' ? '0' : '1';
        el.setAttribute('data-val', nv);
        if(type==='danger') el.className = 'ace-tog ' + (nv==='1' ? 'tog-danger' : 'tog-off');
        else el.className = 'ace-tog ' + (nv==='1' ? 'tog-on' : 'tog-off');
        jQuery.post(ajaxurl,{action:'sgclabs_ace_save_toggle',nonce:aceNonce,key:'sgclabs_ace_tog_'+id,val:nv});
    }
    function aceBatch(el, v) {
        document.querySelectorAll('.ace-bopt').forEach(function(b){b.classList.remove('bsel');});
        el.classList.add('bsel');
        jQuery.post(ajaxurl,{action:'sgclabs_ace_save_toggle',nonce:aceNonce,key:'sgclabs_ace_batch',val:v});
    }
    function getToggles() {
        var t={};
        ['title','kw','desc','content','image','gsc_kw','schema','rewrite'].forEach(function(id){
            var el=document.getElementById('tog-'+id);
            t[id] = el ? el.getAttribute('data-val') : '0';
        });
        return t;
    }
    function getBatch() {
        var s=document.querySelector('.ace-bopt.bsel');
        return s ? parseInt(s.textContent) : 3;
    }
    function getModBatch(mod) {
        if(modBatch[mod]) return modBatch[mod];
        var panel = document.getElementById('panel-'+mod);
        if(panel) {
            var sel = panel.querySelector('.ace-mbopt.bsel');
            if(sel) return parseInt(sel.textContent)||3;
        }
        return getBatch();
    }
    function getModRewrite(mod) {
        var el = document.getElementById('rw-tog-'+mod);
        return el ? el.getAttribute('data-val') : '0';
    }

    // ── Master engine ─────────────────────────────────────────
    function setMasterUI(running, paused) {
        document.getElementById('master-start-btn').style.display  = (!running&&!paused)?'flex':'none';
        document.getElementById('master-pause-btn').style.display  = (running&&!paused)?'flex':'none';
        document.getElementById('master-resume-btn').style.display = paused?'flex':'none';
        document.getElementById('master-stop-btn').style.display   = (running||paused)?'flex':'none';
    }
    function setMasterProg(pct, done, status) {
        document.getElementById('master-fill').style.width = pct+'%';
        document.getElementById('master-pct').textContent  = Math.round(pct)+'%';
        document.getElementById('master-status').textContent = status;
        document.getElementById('m-done').textContent   = done;
        document.getElementById('m-total').textContent  = aceTotal;
        document.getElementById('m-errors').textContent = aceMErrors;
        if(aceMDone>0) document.getElementById('m-score').textContent = Math.round(aceMScoreSum/aceMDone)+'%';
    }
    function aceMasterPlay() {
        if(acePaused){acePaused=false;setMasterUI(true,false);runMaster();return;}
        // Check if master has saved progress
        if(aceSavedProgress['master'] && !aceSavedProgress['master'].finished && aceSavedProgress['master'].done > 0) {
            if(!confirm('Pichli session mein '+aceSavedProgress['master'].done+' products done hue the.\n\nOK = Resume from where stopped\nCancel = Start fresh from beginning')) {
                aceOffset=0; aceMDone=0;
            } else {
                aceOffset = aceSavedProgress['master'].offset || 0;
                aceMDone  = aceSavedProgress['master'].done || 0;
            }
        } else {
            aceOffset=0; aceMDone=0;
        }
        if(aceRunning)return;
        aceRunning=true;acePaused=false;aceMErrors=0;aceMScoreSum=0;
        setMasterUI(true,false);
        setMasterProg(aceMDone/aceTotal*100, aceMDone, 'Connecting to AI...');
        runMaster();
    }
    function aceMasterPause() {
        acePaused=true; aceRunning=false;
        clearTimeout(aceTimer);
        aceSaveProgress('master', aceOffset, aceMDone, aceTotal, false);
        setMasterUI(false,true);
        setMasterProg((aceMDone/aceTotal*100), aceMDone, '⏸ Paused at '+aceMDone+'/'+aceTotal+' — progress saved. Resume anytime.');
    }
    function aceMasterStop() {
        aceRunning=false; acePaused=false;
        clearTimeout(aceTimer);
        aceSaveProgress('master', aceOffset, aceMDone, aceTotal, false);
        setMasterUI(false,false);
        setMasterProg((aceMDone/aceTotal*100), aceMDone, '⏹ Stopped. '+aceMDone+' done — progress saved in DB.');
    }
    function aceMasterReset() {
        aceRunning=false; acePaused=false; aceOffset=0; aceMDone=0; aceMErrors=0; aceMScoreSum=0;
        clearTimeout(aceTimer);
        aceSaveProgress('master', 0, 0, aceTotal, false);
        setMasterUI(false,false);
        setMasterProg(0,0,'Ready');
        document.getElementById('m-score').textContent='—';
        var list=document.getElementById('m-done-list'); if(list){list.innerHTML='';list.style.display='none';}
    }
    function runMaster() {
        if(!aceRunning||acePaused)return;
        var t=getToggles(); var b=getBatch();
        jQuery.post(ajaxurl,{
            action:'sgclabs_ace_master_batch', nonce:aceNonce,
            offset:aceOffset, batch:b,
            tog_title:t.title, tog_kw:t.kw, tog_desc:t.desc,
            tog_content:t.content, tog_image:t.image, tog_gsc_kw:t.gsc_kw,
            tog_schema:t.schema, rewrite:t.rewrite
        },function(res){
            if(!res.success){
                aceMErrors++;
                document.getElementById('m-errors').textContent=aceMErrors;
                if(aceMErrors<=5){aceTimer=setTimeout(runMaster,4000);}
                else{aceRunning=false;setMasterUI(false,false);setMasterProg(aceMDone/aceTotal*100,aceMDone,'❌ Too many errors — stopped. Check AI API key.');}
                return;
            }
            var d=res.data;
            aceOffset=d.next_offset||aceOffset+b;
            aceMDone+=d.processed||0;
            aceMErrors+=(d.errors||[]).length;
            aceMScoreSum+=d.score_sum||0;
            var pct=Math.min(99,Math.round(aceMDone/aceTotal*100));
            setMasterProg(pct,aceMDone,'Processing '+aceMDone+' / '+aceTotal+'...');
            // Save progress after every batch
            aceSaveProgress('master', aceOffset, aceMDone, aceTotal, !!d.finished);
            // Append names to live done-list
            if((d.names||[]).length>0){
                var list=document.getElementById('m-done-list');
                list.style.display='block';
                d.names.forEach(function(item){
                    var row=document.createElement('div');
                    row.className='ace-done-row';
                    var sc=parseInt(item.score||0);
                    var scColor=sc>=80?'#15803d':sc>=50?'#92400e':'#b91c1c';
                    row.innerHTML='<span class="ace-done-id">#'+item.id+'</span>'
                        +'<span class="ace-done-name">'+item.name+'</span>'
                        +'<span style="font-size:10px;font-weight:700;color:'+scColor+'">'+sc+'/100</span>';
                    list.insertBefore(row,list.firstChild);
                    if(list.children.length>50)list.removeChild(list.lastChild);
                });
            }
            if(d.finished){
                aceRunning=false;
                setMasterUI(false,false);
                setMasterProg(100,aceMDone,'✅ Complete! '+aceMDone+' products processed.');
                aceSaveProgress('master', 0, aceMDone, aceTotal, true);
            } else if(aceRunning&&!acePaused){
                aceTimer=setTimeout(runMaster, aceBatchDelay);
            }
        }).fail(function(){
            aceMErrors++;
            document.getElementById('m-errors').textContent=aceMErrors;
            aceSaveProgress('master', aceOffset, aceMDone, aceTotal, false);
            if(aceMErrors<=5){aceTimer=setTimeout(runMaster,4000);}
            else{aceRunning=false;setMasterUI(false,false);}
        });
    }

    // ── Tab switcher ──────────────────────────────────────────
    function aceTab(id,el) {
        document.querySelectorAll('.ace-panel').forEach(function(p){p.classList.remove('panel-on');});
        document.querySelectorAll('.ace-tab').forEach(function(t){t.classList.remove('tab-on');});
        document.getElementById('panel-'+id).classList.add('panel-on');
        el.classList.add('tab-on');
    }

    // ── Module runner (per-tab) ───────────────────────────────
    function aceModPlay(mod) {
        if(modPaused[mod]){modPaused[mod]=false;aceSetModBtn(mod,'running');aceRunMod(mod);return;}
        // v6.6.6 fix: resume from saved offset if interrupted, else fresh start
        var sp = aceSavedProgress[mod];
        if(sp && !sp.finished && sp.done > 0 && (sp.offset||0) > 0) {
            modOffsets[mod] = sp.offset;
            var banner = document.getElementById('resume-banner-'+mod);
            if(banner) banner.style.display='none';
            aceModSetProg(mod, sp.done/Math.max(sp.total||aceTotal,1)*100, sp.done, sp.total||aceTotal, 'Resuming from #'+sp.done+'...');
        } else {
            modOffsets[mod] = 0;
        }
        aceSetModBtn(mod,'running');
        var progEl = document.getElementById('mprog-'+mod);
        if(progEl) progEl.style.display='block';
        aceRunMod(mod);
    }
    // Resume from saved offset
    function aceModResume(mod) {
        var sp = aceSavedProgress[mod];
        if(!sp){aceModPlay(mod);return;}
        modOffsets[mod] = sp.offset || 0;
        var banner = document.getElementById('resume-banner-'+mod);
        if(banner) banner.style.display='none';
        aceSetModBtn(mod,'running');
        document.getElementById('mprog-'+mod).style.display='block';
        aceModSetProg(mod, sp.done/Math.max(sp.total,1)*100, sp.done, sp.total, 'Resuming from product #'+sp.done+'...');
        aceRunMod(mod);
    }
    // Start fresh — reset saved progress
    function aceModFresh(mod) {
        aceSavedProgress[mod]=null;
        aceSaveProgress(mod, 0, 0, aceTotal, false);
        var banner = document.getElementById('resume-banner-'+mod);
        if(banner) banner.style.display='none';
        aceModPlay(mod);
    }
    function aceModPause(mod){
        modPaused[mod]=true;
        clearTimeout(modTimers[mod]);
        aceSaveProgress(mod, modOffsets[mod]||0, parseInt(document.getElementById('mdone-'+mod)?.textContent||'0'), aceTotal, false);
        aceSetModBtn(mod,'paused');
        aceModSetProg(mod,
            parseInt(document.getElementById('mfill-'+mod)?.style.width||'0'),
            parseInt(document.getElementById('mdone-'+mod)?.textContent||'0'),
            aceTotal, '⏸ Paused — progress saved in DB. Resume anytime.');
    }
    function aceModStop(mod) {
        modPaused[mod]=false;
        clearTimeout(modTimers[mod]);
        aceSaveProgress(mod, modOffsets[mod]||0, parseInt(document.getElementById('mdone-'+mod)?.textContent||'0'), aceTotal, false);
        aceSetModBtn(mod,'idle');
        modOffsets[mod]=0;
    }
    function aceModReset(mod){
        aceModStop(mod);
        aceSaveProgress(mod, 0, 0, aceTotal, false);
        aceSavedProgress[mod]=null;
        aceModSetProg(mod,0,0,aceTotal,'Ready.');
        var list=document.getElementById('mdone-list-'+mod);
        if(list){list.innerHTML='';list.style.display='none';}
        var banner=document.getElementById('resume-banner-'+mod);
        if(banner)banner.style.display='none';
    }
    function aceSetModBtn(mod,state){
        var s=document.getElementById('mbtn-start-'+mod);
        var p=document.getElementById('mbtn-pause-'+mod);
        var r=document.getElementById('mbtn-resume-'+mod);
        var st=document.getElementById('mbtn-stop-'+mod);
        if(s) s.style.display=state==='idle'?'flex':'none';
        if(p) p.style.display=state==='running'?'flex':'none';
        if(r) r.style.display=state==='paused'?'flex':'none';
        if(st)st.style.display=state!=='idle'?'flex':'none';
    }
    function aceModSetProg(mod,pct,done,total,status){
        var fill=document.getElementById('mfill-'+mod);
        var pctEl=document.getElementById('mpct-'+mod);
        var stat=document.getElementById('mstat-'+mod);
        var doneEl=document.getElementById('mdone-'+mod);
        if(fill) fill.style.width=pct+'%';
        if(pctEl)pctEl.textContent=Math.round(pct)+'%';
        if(stat) stat.textContent=status;
        if(doneEl)doneEl.textContent=done;
    }
    function aceRunMod(mod){
        if(modPaused[mod])return;
        var actionMap={
            titles:'sgclabs_ace_bulk_titles',
            keywords:'sgclabs_ace_bulk_kw',
            meta:'sgclabs_ace_bulk_meta',
            meta_title:'sgclabs_ace_bulk_meta_title',
            short_desc:'sgclabs_ace_bulk_short_desc',
            main_desc:'sgclabs_ace_bulk_main_desc',
            images:'sgclabs_ace_bulk_images',
            multilingual:'sgclabs_ace_bulk_multilingual'
        };
        var action=actionMap[mod];
        if(!action)return;
        var rw = getModRewrite(mod);
        var b  = getModBatch(mod);
        // Blank-only / mode radio for each module
        var blankOnly=0, mode='all';
        if(mod==='titles'){
            var el=document.getElementById('titles-blank-only');
            blankOnly = (el&&el.checked)?1:0;
        }
        // ── Radio button reads for remaining modules (v6.6.6 fix) ──
        if(mod==='meta_title'){
            var elMta=document.querySelector('input[name="mta-mode"]:checked');
            if(elMta && elMta.value==='all') rw='1';
        }
        if(mod==='meta'){
            var elMeta=document.querySelector('input[name="meta-mode"]:checked');
            if(elMeta && elMeta.value==='all') rw='1';
        }
        if(mod==='short_desc'){
            var elSd=document.querySelector('input[name="sdesc-mode"]:checked');
            if(elSd && elSd.value==='all') rw='1';
        }
        if(mod==='main_desc'){
            var elMd=document.querySelector('input[name="mdesc-mode"]:checked');
            if(elMd && elMd.value==='all') rw='1';
        }
        jQuery.post(ajaxurl,{
            action:action, nonce:aceNonce,
            offset:modOffsets[mod]||0,
            batch:b,
            rewrite:rw,
            only_blank:blankOnly
        },function(res){
            if(!res.success){aceSetModBtn(mod,'idle');return;}
            var d=res.data;
            modOffsets[mod]=d.next_offset||0;
            var pct=Math.min(100,Math.round((d.done||0)/Math.max(d.total||aceTotal,1)*100));
            aceModSetProg(mod,pct,d.done||0,d.total||aceTotal,'Processing '+(d.done||0)+' / '+(d.total||aceTotal)+'...');
            // Save progress every batch
            aceSaveProgress(mod, modOffsets[mod], d.done||0, d.total||aceTotal, !!d.finished);
            // Live done-list
            if((d.names||[]).length>0){
                var list=document.getElementById('mdone-list-'+mod);
                if(list){
                    list.style.display='block';
                    d.names.forEach(function(item){
                        var row=document.createElement('div');
                        row.className='ace-done-row';
                        row.innerHTML='<span class="ace-done-id">#'+item.id+'</span>'
                            +'<span class="ace-done-name">'+item.name+'</span>'
                            +'<span class="ace-done-val">'+item.value+'</span>';
                        list.insertBefore(row,list.firstChild);
                        if(list.children.length>30)list.removeChild(list.lastChild);
                    });
                }
            }
            if(d.finished){
                aceSetModBtn(mod,'idle');
                aceModSetProg(mod,100,d.done||0,d.total||aceTotal,'✅ Done! '+(d.done||0)+' products processed.');
                aceSaveProgress(mod, d.done||0, d.done||0, d.total||aceTotal, true);
            } else if(!modPaused[mod]){
                modTimers[mod]=setTimeout(function(){aceRunMod(mod);}, aceBatchDelay);
            }
        }).fail(function(){
            aceSaveProgress(mod, modOffsets[mod]||0, parseInt(document.getElementById('mdone-'+mod)?.textContent||'0'), aceTotal, false);
            aceSetModBtn(mod,'idle');
        });
    }

    // ── Keyword suggest ───────────────────────────────────────
    function aceKeywords(){
        var name=document.getElementById('kw-product').value.trim();
        var cat=document.getElementById('kw-cat').value.trim();
        if(!name){alert('Product name required');return;}
        var r=document.getElementById('kw-result');
        r.innerHTML='<span style="color:#999;font-size:12px;">🤖 Generating keywords...</span>';
        jQuery.post(ajaxurl,{action:'sgclabs_ace_keyword_suggest',nonce:aceNonce,product_name:name,category:cat},function(res){
            if(!res.success){r.innerHTML='<span style="color:#e74c3c;font-size:12px;">❌ '+(res.data||'Error')+'</span>';return;}
            var html='<div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;">';
            (res.data.keywords||[]).forEach(function(kw){
                html+='<span class="ace-chip" onclick="navigator.clipboard.writeText(\''+kw.replace(/'/g,"\\'")+'\')" title="Click to copy">'+kw+' 📋</span>';
            });
            html+='</div><div style="font-size:10px;color:#aaa;margin-top:6px;">Click keyword to copy → add to Keyword Manager for auto-injection</div>';
            r.innerHTML=html;
        }).fail(function(){r.innerHTML='<span style="color:#e74c3c;font-size:12px;">❌ Network error</span>';});
    }

    // ── One-click ─────────────────────────────────────────────
    function aceOneClick(){
        var pid=document.getElementById('oc-product').value;
        if(!pid){alert('Please select a product');return;}
        aceOneClickPid(parseInt(pid));
    }
    function aceOneClickPid(pid){
        var r=document.getElementById('oc-result')||{innerHTML:''};
        r.innerHTML='<span style="color:#666;">⏳ AI generating all SEO fields for product #'+pid+'... (takes ~15-30 seconds)</span>';
        jQuery.post(ajaxurl,{action:'sgclabs_ai_one_click_fix',nonce:aceNonce,post_id:pid},function(res){
            if(!res.success){r.innerHTML='<span style="color:#e74c3c;">❌ '+(res.data||'Error')+'</span>';return;}
            var d=res.data;
            var c=d.score>=90?'#15803d':d.score>=70?'#92400e':'#b91c1c';
            r.innerHTML='<div style="border:1px solid #e0e0e0;border-radius:8px;padding:14px;">'
                +'<div style="font-weight:700;color:'+c+';font-size:14px;margin-bottom:10px;">SEO Score: '+d.score+'/100</div>'
                +'<div style="font-size:12px;margin-bottom:6px;"><strong>SEO Title:</strong> '+d.title+'</div>'
                +'<div style="font-size:12px;margin-bottom:6px;"><strong>Focus Keyword:</strong> '+d.focus_kw+'</div>'
                +'<div style="font-size:12px;margin-bottom:6px;"><strong>Meta Desc:</strong> '+d.desc+'</div>'
                +'<div style="font-size:11px;color:#888;margin-top:8px;">✅ All saved to DB — RankMath, Yoast, WooCommerce. '+d.alts+' image alts updated. <a href="/wp-admin/post.php?post='+pid+'&action=edit" target="_blank">View product →</a></div>'
                +'</div>';
        }).fail(function(){r.innerHTML='<span style="color:#e74c3c;">❌ Network error — check AI API key config</span>';});
    }
    </script>
    <?php
}

// ============================================================
// SECTION 5 — OFFLINE FALLBACK FUNCTIONS
// ============================================================

function sgclabs_ace_offline_title( string $name, string $cat ): string {
    $suffixes = [
        " | SGC LABS® — Lab Supplier India",
        " | Buy Online — SGC LABS®",
        " | Laboratory Equipment — SGC LABS®",
    ];
    foreach ( $suffixes as $sfx ) {
        $candidate = $name . $sfx;
        if ( strlen($candidate) >= 30 && strlen($candidate) <= 60 ) return $candidate;
    }
    $t = $name . " | SGC LABS®";
    return strlen($t) > 60 ? substr($t, 0, 57) . '...' : $t;
}

function sgclabs_ace_offline_desc( string $name, string $cat, string $existing ): string {
    if ( strlen($existing) >= 80 ) {
        $c = $name . ' — ' . substr($existing, 0, 120);
        if ( strlen($c) >= 80 && strlen($c) <= 160 ) return $c;
    }
    $t = "Buy {$name} from SGC LABS® India — ISO certified {$cat} manufacturer. Bulk orders, worldwide export, fast dispatch.";
    return strlen($t) > 160 ? substr($t, 0, 157) . '...' : $t;
}

function sgclabs_ace_offline_main_desc( string $name, string $cat, string $sku, string $focus ): string {
    return "<h2>Premium {$name} by SGC LABS® India</h2>
<p>The <strong>{$name}</strong> is a high-quality {$cat} manufactured by SGC LABS® at our Ambala, Haryana facility. Trusted by 5,000+ institutions across India and worldwide.</p>
<h3>Key Features</h3>
<ul>
<li><strong>Premium Grade:</strong> Manufactured to international quality standards</li>
<li><strong>ISO 9001:2015 Certified:</strong> Rigorous quality inspection every batch</li>
<li><strong>Factory-Direct:</strong> No middlemen — best price guaranteed</li>
<li><strong>Fast Dispatch:</strong> Same-day for in-stock items</li>
<li><strong>Worldwide Export:</strong> DHL/FedEx to 50+ countries</li>
</ul>
<h3>Specifications</h3>
<ul>
<li><strong>Product:</strong> {$name}</li>
<li><strong>Category:</strong> {$cat}</li>
<li><strong>SKU:</strong> {$sku}</li>
<li><strong>Brand:</strong> SGC LABS®</li>
<li><strong>Origin:</strong> Made in India (Ambala, Haryana)</li>
</ul>
<p>Contact us: <strong>sales@labsglass.com</strong> | <strong>+91 999 641 6935</strong></p>";
}

// ============================================================
// SECTION 6 — BACKWARD COMPAT (existing hooks still work)
// ============================================================

// Backward compat stubs → see SECTION 7 below

// ============================================================
// SECTION 7 — BACKWARD COMPATIBILITY STUBS
// All these functions were in separate files — now consolidated
// ============================================================
if ( ! function_exists('sgclabs_build_smart_title') ) {
    function sgclabs_build_smart_title( $name, $cat_str = '' ) {
        return sgclabs_ace_offline_title( (string)$name, (string)$cat_str );
    }
}
if ( ! function_exists('sgclabs_build_smart_desc') ) {
    function sgclabs_build_smart_desc( $name, $cat_str = '', $existing = '' ) {
        return sgclabs_ace_offline_desc( (string)$name, (string)$cat_str, (string)$existing );
    }
}
if ( ! function_exists('sgclabs_generate_focus_keyword') ) {
    function sgclabs_generate_focus_keyword( $product_name, $category = '' ) {
        return [ 'primary' => strtolower( trim($product_name) ), 'lsi' => [] ];
    }
}
if ( ! function_exists('sgclabs_generate_focus_keyword_improved') ) {
    function sgclabs_generate_focus_keyword_improved( $product_name, $full = '', $short = '' ) {
        $words = array_filter( explode(' ', strtolower($product_name)), fn($w) => strlen($w) > 3 );
        return [ 'primary' => implode(' ', array_slice(array_values($words), 0, 4)) ?: strtolower($product_name), 'lsi' => [] ];
    }
}
if ( ! function_exists('sgclabs_auto_generate_seo') ) {
    function sgclabs_auto_generate_seo( $product_id ) {
        return function_exists('sgclabs_ai_gen_all') ? sgclabs_ai_gen_all( intval($product_id) ) : [];
    }
}
if ( ! function_exists('sgclabs_get_translations') ) {
    function sgclabs_get_translations() { return []; }
}
if ( ! function_exists('sgclabs_get_target_locations') ) {
    function sgclabs_get_target_locations() {
        return [ 'Ambala' => 'India', 'Delhi' => 'India', 'Dubai' => 'UAE', 'London' => 'UK', 'New York' => 'USA' ];
    }
}
if ( ! function_exists('sgclabs_generate_h1_h2') ) {
    function sgclabs_generate_h1_h2( $product_name, $category, $brand = 'SGC LABS®' ) {
        return [ 'h1' => esc_html($product_name), 'h2' => "Premium {$category} by {$brand}" ];
    }
}
if ( ! function_exists('sgclabs_build_enhanced_schema') ) {
    function sgclabs_build_enhanced_schema( $product_id, $name, $meta_desc, $sku, $cat_str, $in_stock, $image_url, $product ) {
        return []; // Schema handled by schema-engine.php
    }
}
if ( ! function_exists('sgcr_build_title') ) {
    function sgcr_build_title( $pid ) {
        return function_exists('sgclabs_ai_gen_title') ? sgclabs_ai_gen_title( intval($pid) ) : get_the_title($pid);
    }
}
if ( ! function_exists('sgcr_build_short_desc') ) {
    function sgcr_build_short_desc( $pid ) {
        return function_exists('sgclabs_ai_gen_short_desc') ? sgclabs_ai_gen_short_desc( intval($pid) ) : '';
    }
}
if ( ! function_exists('sgcr_build_main_desc') ) {
    function sgcr_build_main_desc( $pid ) {
        return function_exists('sgclabs_ai_gen_main_desc') ? sgclabs_ai_gen_main_desc( intval($pid) ) : '';
    }
}
if ( ! function_exists('sgcr_build_focus_kw') ) {
    function sgcr_build_focus_kw( $pid ) {
        $kw = function_exists('sgclabs_ai_gen_focus_kw') ? sgclabs_ai_gen_focus_kw( intval($pid) ) : '';
        return [ 'primary' => $kw, 'lsi' => [] ];
    }
}
if ( ! function_exists('sgclabs_injector_build_title') ) {
    function sgclabs_injector_build_title( int $product_id ): string {
        return function_exists('sgclabs_ai_gen_title') ? sgclabs_ai_gen_title( $product_id ) : get_the_title($product_id);
    }
}
if ( ! function_exists('sgclabs_injector_build_desc') ) {
    function sgclabs_injector_build_desc( int $product_id ): string {
        return function_exists('sgclabs_ai_gen_meta_desc') ? sgclabs_ai_gen_meta_desc( $product_id ) : '';
    }
}
if ( ! function_exists('sgclabs_injector_build_focus_kw') ) {
    function sgclabs_injector_build_focus_kw( int $product_id ): string {
        return function_exists('sgclabs_ai_gen_focus_kw') ? sgclabs_ai_gen_focus_kw( $product_id ) : '';
    }
}

// Page stubs — admin pages from disabled files
if ( ! function_exists('sgclabs_auto_seo_page') ) {
    function sgclabs_auto_seo_page() { sgclabs_ace_admin_page(); }
}
if ( ! function_exists('sgclabs_content_generator_page') ) {
    function sgclabs_content_generator_page() { sgclabs_ace_admin_page(); }
}
if ( ! function_exists('sgclabs_content_rewriter_page') ) {
    function sgclabs_content_rewriter_page() { sgclabs_ace_admin_page(); }
}
if ( ! function_exists('sgclabs_focus_keywords_page') ) {
    function sgclabs_focus_keywords_page() { sgclabs_ace_admin_page(); }
}
if ( ! function_exists('sgclabs_ai_focus_keywords_page') ) {
    function sgclabs_ai_focus_keywords_page() { sgclabs_ace_admin_page(); }
}

// ============================================================
// SECTION 8 — ALL AJAX HANDLERS (consolidated)
// ============================================================

// Toggle save
add_action('wp_ajax_sgclabs_ace_save_toggle', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    $key = sanitize_key($_POST['key'] ?? '');
    $val = sanitize_text_field($_POST['val'] ?? '0');
    $allowed = ['sgclabs_ace_tog_title','sgclabs_ace_tog_kw','sgclabs_ace_tog_desc',
                'sgclabs_ace_tog_content','sgclabs_ace_tog_image','sgclabs_ace_tog_gsc_kw',
                'sgclabs_ace_tog_rewrite','sgclabs_ace_tog_schema',
                'sgclabs_ace_tog_meta_title','sgclabs_ace_tog_short_desc','sgclabs_ace_tog_main_desc',
                'sgclabs_ace_rw_titles','sgclabs_ace_rw_meta_title','sgclabs_ace_rw_keywords',
                'sgclabs_ace_rw_meta','sgclabs_ace_rw_short_desc','sgclabs_ace_rw_main_desc',
                'sgclabs_ace_rw_images','sgclabs_ace_rw_multilingual',
                'sgclabs_ace_batch'];
    if (!in_array($key, $allowed, true)) wp_send_json_error('Invalid key');
    update_option($key, $val);
    wp_send_json_success();
});

// Progress persistence — save module progress to DB
add_action('wp_ajax_sgclabs_ace_save_progress', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    $allowed_mods = ['master','titles','meta_title','keywords','meta','short_desc','main_desc','images','multilingual'];
    $mod      = sanitize_key($_POST['mod'] ?? '');
    if (!in_array($mod, $allowed_mods, true)) wp_send_json_error('Invalid module');
    $offset   = intval($_POST['offset'] ?? 0);
    $done     = intval($_POST['done'] ?? 0);
    $total    = intval($_POST['total'] ?? 0);
    $finished = ( sanitize_key( $_POST['finished'] ?? '0' ) ) === '1';
    $last     = sanitize_text_field($_POST['last_batch'] ?? current_time('d M Y H:i'));
    update_option("sgclabs_ace_prog_{$mod}", [
        'offset'     => $offset,
        'done'       => $done,
        'total'      => $total,
        'finished'   => $finished,
        'last_batch' => $last,
    ]);
    wp_send_json_success();
});

// Master batch — runs ALL enabled modules per product
add_action('wp_ajax_sgclabs_ace_master_batch', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    @set_time_limit(120);

    global $wpdb;
    $offset   = intval($_POST['offset'] ?? 0);
    $batch    = min(10, max(1, intval($_POST['batch'] ?? 3)));
    $rewrite  = ( sanitize_key( $_POST['rewrite']    ?? '0' ) ) === '1';
    $tog_title= ( sanitize_key( $_POST['tog_title']  ?? '1' ) ) === '1';
    $tog_kw   = ( sanitize_key( $_POST['tog_kw']     ?? '1' ) ) === '1';
    $tog_desc = ( sanitize_key( $_POST['tog_desc']   ?? '1' ) ) === '1';
    $tog_cont = ( sanitize_key( $_POST['tog_content']?? '1' ) ) === '1';
    $tog_img  = ( sanitize_key( $_POST['tog_image']  ?? '1' ) ) === '1';
    $tog_gsc  = ( sanitize_key( $_POST['tog_gsc_kw'] ?? '1' ) ) === '1';

    $total = (int)$wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'");
    $ids   = $wpdb->get_col($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' ORDER BY ID ASC LIMIT %d OFFSET %d",
        $batch, $offset
    ));

    $processed = 0; $errors = []; $score_sum = 0; $processed_names = [];

    foreach ($ids as $pid) {
        $pid = intval($pid);
        try {
            if ($rewrite) {
                delete_post_meta($pid, '_sgclabs_ai_seo_title');
                delete_post_meta($pid, '_sgclabs_meta_page_title');
                delete_post_meta($pid, '_sgclabs_ai_meta_desc');
                delete_post_meta($pid, '_sgclabs_focus_kw');
                delete_post_meta($pid, '_sgclabs_ai_short_desc');
                delete_post_meta($pid, '_sgclabs_ai_main_desc');
                delete_post_meta($pid, '_sgclabs_ai_gen_at');
            }

            if ($tog_kw && function_exists('sgclabs_ai_gen_focus_kw')) {
                sgclabs_ai_gen_focus_kw($pid);
            }
            if ($tog_title && function_exists('sgclabs_ai_gen_title')) {
                sgclabs_ai_gen_title($pid);
                if (function_exists('sgclabs_ai_gen_meta_title')) sgclabs_ai_gen_meta_title($pid);
            }
            if ($tog_desc && function_exists('sgclabs_ai_gen_meta_desc')) {
                sgclabs_ai_gen_meta_desc($pid);
            }
            if ($tog_cont && function_exists('sgclabs_ai_gen_short_desc')) {
                sgclabs_ai_gen_short_desc($pid);
                if (function_exists('sgclabs_ai_gen_main_desc')) {
                    $main = sgclabs_ai_gen_main_desc($pid);
                    if ($tog_gsc && !empty($main)) {
                        $main = sgclabs_ace_inject_gsc_keywords($pid, $main);
                        update_post_meta($pid, '_sgclabs_ai_main_desc', $main);
                        wp_update_post(['ID'=>$pid,'post_content'=>$main]);
                    }
                }
            }
            if ($tog_img && function_exists('sgclabs_ai_gen_image_alt')) {
                $alts = sgclabs_ai_gen_image_alt($pid);
                foreach ($alts as $img_id => $alt) {
                    update_post_meta($img_id, '_wp_attachment_image_alt', $alt);
                }
            }
            update_post_meta($pid, '_sgclabs_ai_gen_at', current_time('mysql'));
            if (function_exists('sgclabs_calculate_seo_score')) {
                $score = sgclabs_calculate_seo_score($pid);
                update_post_meta($pid, '_sgclabs_seo_score', $score);
                $score_sum += $score;
            }
            $p_obj = function_exists('wc_get_product') ? wc_get_product($pid) : null;
            $processed_names[] = [
                'id'    => $pid,
                'name'  => $p_obj ? esc_html(substr($p_obj->get_name(), 0, 50)) : "#$pid",
                'score' => get_post_meta($pid, '_sgclabs_seo_score', true) ?: 0,
            ];
            $processed++;
        } catch (Exception $e) {
            $errors[] = "Product #{$pid}: " . $e->getMessage();
        }
    }

    $done = $offset + $processed;
    wp_send_json_success([
        'processed'   => $processed,
        'total'       => $total,
        'done'        => $done,
        'percent'     => min(100, round($done / max($total,1) * 100)),
        'finished'    => count($ids) < $batch,
        'next_offset' => $offset + $batch,
        'errors'      => $errors,
        'score_sum'   => $score_sum,
        'names'       => $processed_names,
    ]);
});

// Bulk Keywords only
add_action('wp_ajax_sgclabs_ace_bulk_kw', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    @set_time_limit(90);
    global $wpdb;
    $offset  = intval($_POST['offset'] ?? 0);
    $batch   = min(5, max(1, intval($_POST['batch'] ?? 3)));
    $rewrite = ( sanitize_key( $_POST['rewrite'] ?? '0' ) ) === '1';
    $total   = (int)$wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'");
    if ($rewrite) {
        $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' ORDER BY ID ASC LIMIT %d OFFSET %d", $batch, $offset));
    } else {
        $ids = $wpdb->get_col($wpdb->prepare("SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id AND pm.meta_key='_sgclabs_focus_kw' WHERE p.post_type='product' AND p.post_status='publish' AND pm.meta_value IS NULL ORDER BY p.ID ASC LIMIT %d OFFSET %d", $batch, $offset));
    }
    $done_total = (int)$wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_sgclabs_focus_kw'");
    $processed = 0;
    foreach ($ids as $pid) {
        if ($rewrite) delete_post_meta(intval($pid),'_sgclabs_focus_kw');
        sgclabs_ai_gen_focus_kw(intval($pid));
        $processed++;
    }
    $done = $done_total + $processed;
    wp_send_json_success(['processed'=>$processed,'total'=>$total,'done'=>$done,'percent'=>min(100,round($done/max($total,1)*100)),'finished'=>count($ids)<$batch,'next_offset'=>$offset+$batch]);
});

// Bulk Meta Desc only
add_action('wp_ajax_sgclabs_ace_bulk_meta', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    @set_time_limit(90);
    global $wpdb;
    $offset  = intval($_POST['offset'] ?? 0);
    $batch   = min(5, max(1, intval($_POST['batch'] ?? 3)));
    $rewrite = ( sanitize_key( $_POST['rewrite'] ?? '0' ) ) === '1';
    $total   = (int)$wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'");
    $ids     = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' ORDER BY ID ASC LIMIT %d OFFSET %d", $batch, $offset));
    $done_count = (int)$wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_sgclabs_seo_description'");
    $processed = 0;
    foreach ($ids as $pid) {
        if ($rewrite) delete_post_meta(intval($pid),'_sgclabs_ai_meta_desc');
        sgclabs_ai_gen_meta_desc(intval($pid));
        $processed++;
    }
    $done = $done_count + $processed;
    wp_send_json_success(['processed'=>$processed,'total'=>$total,'done'=>$done,'percent'=>min(100,round($done/max($total,1)*100)),'finished'=>count($ids)<$batch,'next_offset'=>$offset+$batch]);
});

// Bulk Images
add_action('wp_ajax_sgclabs_ace_bulk_images', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    @set_time_limit(90);
    global $wpdb;
    $offset = intval($_POST['offset'] ?? 0);
    $batch  = min(5, max(1, intval($_POST['batch'] ?? 3)));
    $total  = (int)$wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'");
    $ids    = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' ORDER BY ID ASC LIMIT %d OFFSET %d", $batch, $offset));
    $processed = 0;
    foreach ($ids as $pid) {
        $alts = sgclabs_ai_gen_image_alt(intval($pid));
        foreach ($alts as $img_id => $alt) {
            update_post_meta($img_id, '_wp_attachment_image_alt', $alt);
        }
        $processed++;
    }
    $done = $offset + $processed;
    wp_send_json_success(['processed'=>$processed,'total'=>$total,'done'=>$done,'percent'=>min(100,round($done/max($total,1)*100)),'finished'=>count($ids)<$batch,'next_offset'=>$offset+$batch]);
});

// Bulk Multilingual
add_action('wp_ajax_sgclabs_ace_bulk_multilingual', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    @set_time_limit(120);
    global $wpdb;
    $offset = intval($_POST['offset'] ?? 0);
    $batch  = min(3, max(1, intval($_POST['batch'] ?? 2)));
    $langs  = array_map('sanitize_key', (array)($_POST['langs'] ?? array_keys(function_exists('sgclabs_get_languages') ? sgclabs_get_languages() : [])));
    $total  = (int)$wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'");
    $ids    = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' ORDER BY ID ASC LIMIT %d OFFSET %d", $batch, $offset));
    $processed = 0;
    foreach ($ids as $pid) {
        foreach ($langs as $lang) {
            $data = sgclabs_ai_gen_multilingual(intval($pid), $lang);
            if (!empty($data['title'])) {
                update_post_meta(intval($pid), "_sgclabs_ml_title_{$lang}", $data['title']);
                update_post_meta(intval($pid), "_sgclabs_ml_desc_{$lang}",  $data['desc'] ?? '');
                update_post_meta(intval($pid), "_sgclabs_ml_kw_{$lang}",    $data['keyword'] ?? '');
            }
        }
        $processed++;
    }
    $done = $offset + $processed;
    wp_send_json_success(['processed'=>$processed,'total'=>$total,'done'=>$done,'percent'=>min(100,round($done/max($total,1)*100)),'finished'=>count($ids)<$batch,'next_offset'=>$offset+$batch]);
});

// ── Bulk Meta Title ───────────────────────────────────────────
add_action('wp_ajax_sgclabs_ace_bulk_meta_title', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    @set_time_limit(90);
    global $wpdb;
    $offset  = intval($_POST['offset'] ?? 0);
    $batch   = min(5, max(1, intval($_POST['batch'] ?? 3)));
    $rewrite = ( sanitize_key( $_POST['rewrite'] ?? '0' ) ) === '1';
    $total   = (int)$wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'");
    if ($rewrite) {
        $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' ORDER BY ID ASC LIMIT %d OFFSET %d", $batch, $offset));
    } else {
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id AND pm.meta_key='_sgclabs_meta_page_title'
             WHERE p.post_type='product' AND p.post_status='publish' AND pm.meta_value IS NULL ORDER BY p.ID ASC LIMIT %d OFFSET %d",
            $batch, $offset
        ));
    }
    $done_count = (int)$wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_sgclabs_meta_page_title'");
    $processed = 0; $names = [];
    foreach ($ids as $pid) {
        $pid = intval($pid);
        if ($rewrite) delete_post_meta($pid, '_sgclabs_meta_page_title');
        $title = sgclabs_ai_gen_meta_title($pid);
        if ($title) {
            $processed++;
            $p_obj = function_exists('wc_get_product') ? wc_get_product($pid) : null;
            $names[] = ['id' => $pid, 'name' => $p_obj ? esc_html(substr($p_obj->get_name(),0,50)) : "#$pid", 'value' => esc_html(substr($title,0,60))];
        }
    }
    $done = $done_count + $processed;
    wp_send_json_success(['processed'=>$processed,'total'=>$total,'done'=>$done,'percent'=>min(100,round($done/max($total,1)*100)),'finished'=>count($ids)<$batch,'next_offset'=>$offset+$batch,'names'=>$names]);
});

// ── Bulk Short Description ────────────────────────────────────
add_action('wp_ajax_sgclabs_ace_bulk_short_desc', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    @set_time_limit(90);
    global $wpdb;
    $offset  = intval($_POST['offset'] ?? 0);
    $batch   = min(5, max(1, intval($_POST['batch'] ?? 3)));
    $rewrite = ( sanitize_key( $_POST['rewrite'] ?? '0' ) ) === '1';
    $total   = (int)$wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'");
    if ($rewrite) {
        $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' ORDER BY ID ASC LIMIT %d OFFSET %d", $batch, $offset));
    } else {
        // Only products with empty or very short post_excerpt
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'
             AND (post_excerpt IS NULL OR LENGTH(TRIM(post_excerpt)) < 50)
             ORDER BY ID ASC LIMIT %d OFFSET %d",
            $batch, $offset
        ));
    }
    $done_count = (int)$wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_sgclabs_ai_short_desc'");
    $processed = 0; $names = [];
    foreach ($ids as $pid) {
        $pid = intval($pid);
        if ($rewrite) delete_post_meta($pid, '_sgclabs_ai_short_desc');
        $desc = sgclabs_ai_gen_short_desc($pid);
        if ($desc) {
            $processed++;
            $p_obj = function_exists('wc_get_product') ? wc_get_product($pid) : null;
            $names[] = ['id' => $pid, 'name' => $p_obj ? esc_html(substr($p_obj->get_name(),0,50)) : "#$pid", 'value' => esc_html(substr($desc,0,80))];
        }
    }
    $done = $done_count + $processed;
    wp_send_json_success(['processed'=>$processed,'total'=>$total,'done'=>$done,'percent'=>min(100,round($done/max($total,1)*100)),'finished'=>count($ids)<$batch,'next_offset'=>$offset+$batch,'names'=>$names]);
});

// ── Bulk Main Description ─────────────────────────────────────
add_action('wp_ajax_sgclabs_ace_bulk_main_desc', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    @set_time_limit(180);
    global $wpdb;
    $offset  = intval($_POST['offset'] ?? 0);
    $batch   = min(3, max(1, intval($_POST['batch'] ?? 2)));
    $rewrite = ( sanitize_key( $_POST['rewrite'] ?? '0' ) ) === '1';
    $total   = (int)$wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'");
    if ($rewrite) {
        $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' ORDER BY ID ASC LIMIT %d OFFSET %d", $batch, $offset));
    } else {
        // Only products with thin or missing main description
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'
             AND (post_content IS NULL OR LENGTH(TRIM(post_content)) < 300)
             ORDER BY ID ASC LIMIT %d OFFSET %d",
            $batch, $offset
        ));
    }
    $done_count = (int)$wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_sgclabs_ai_main_desc'");
    $processed = 0; $names = [];
    foreach ($ids as $pid) {
        $pid = intval($pid);
        if ($rewrite) delete_post_meta($pid, '_sgclabs_ai_main_desc');
        $content = sgclabs_ai_gen_main_desc($pid);
        if ($content) {
            $processed++;
            $p_obj = function_exists('wc_get_product') ? wc_get_product($pid) : null;
            $names[] = ['id' => $pid, 'name' => $p_obj ? esc_html(substr($p_obj->get_name(),0,50)) : "#$pid", 'value' => esc_html(substr(wp_strip_all_tags($content),0,60)).'...'];
        }
    }
    $done = $done_count + $processed;
    wp_send_json_success(['processed'=>$processed,'total'=>$total,'done'=>$done,'percent'=>min(100,round($done/max($total,1)*100)),'finished'=>count($ids)<$batch,'next_offset'=>$offset+$batch,'names'=>$names]);
});

// ── GSC Keyword Injector ─────────────────────────────────────
function sgclabs_ace_inject_gsc_keywords( int $pid, string $content ): string {
    global $wpdb;
    // Get top GSC keywords for this product
    $product = function_exists('wc_get_product') ? wc_get_product($pid) : null;
    if (!$product) return $content;

    $name = strtolower($product->get_name());
    // Get global GSC keywords ordered by clicks
    $gsc_kws = $wpdb->get_col("SELECT keyword FROM {$wpdb->prefix}sgclabs_kw_gsc WHERE active=1 ORDER BY clicks DESC LIMIT 10");
    // Get global keywords
    $global_kws = $wpdb->get_col("SELECT keyword FROM {$wpdb->prefix}sgclabs_kw_global WHERE active=1 ORDER BY id DESC LIMIT 5");
    $all_kws = array_unique(array_merge($gsc_kws, $global_kws));

    if (empty($all_kws)) return $content;

    // Find keywords relevant to this product
    $relevant = array_filter($all_kws, function($kw) use ($name) {
        $words = explode(' ', strtolower($kw));
        foreach ($words as $w) {
            if (strlen($w) > 3 && strpos($name, $w) !== false) return true;
        }
        return false;
    });

    if (empty($relevant)) $relevant = array_slice($all_kws, 0, 3);

    // Append keywords section to content
    if (!empty($relevant)) {
        $kw_str = implode(', ', array_map('esc_html', $relevant));
        if (strpos($content, 'Search Keywords') === false) {
            $content .= "\n<p><strong>Related Search Keywords:</strong> " . $kw_str . "</p>";
        }
    }
    return $content;
}
