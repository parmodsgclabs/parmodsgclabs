<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================
// SGC LABS® — AMAZON BUSINESS + ARABIC SEO MODULE v6.8.0
// [2026-04-08]
// Features:
//  1. Amazon Business US listing guide + checklist
//  2. Arabic SEO meta support (RTL)
//  3. Arabic product title/desc/meta per product
//  4. hreflang tags for Arabic pages
//  5. Admin dashboard
// ============================================================

// ── ARABIC SEO METABOX ────────────────────────────────────────
add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'sgclabs_arabic_seo',
        '🌍 Arabic SEO (العربية) + Middle East',
        'sgclabs_arabic_seo_metabox',
        'product', 'normal', 'default'
    );
} );

function sgclabs_arabic_seo_metabox( $post ) {
    wp_nonce_field( 'sgclabs_arabic_seo_nonce', 'sgclabs_arabic_nonce' );
    $ar_title    = get_post_meta( $post->ID, '_sgclabs_ar_title',    true );
    $ar_desc     = get_post_meta( $post->ID, '_sgclabs_ar_desc',     true );
    $ar_meta     = get_post_meta( $post->ID, '_sgclabs_ar_meta',     true );
    $ar_keywords = get_post_meta( $post->ID, '_sgclabs_ar_keywords', true );
    $ar_active   = get_post_meta( $post->ID, '_sgclabs_ar_active',   true );
    $nonce       = wp_create_nonce( 'sgclabs_nonce' );
    ?>
    <style>
    .sgc-ar-wrap { font-family: -apple-system, sans-serif; }
    .sgc-ar-field { margin-bottom: 14px; }
    .sgc-ar-field label { display: block; font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 4px; }
    .sgc-ar-field input, .sgc-ar-field textarea {
        width: 100%; padding: 8px 12px;
        border: 1px solid #d1d5db; border-radius: 6px;
        font-size: 14px; direction: rtl;
        font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
    }
    .sgc-ar-field textarea { min-height: 80px; resize: vertical; }
    .sgc-ar-field input:focus, .sgc-ar-field textarea:focus { border-color: #1565c0; outline: none; }
    .sgc-ar-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .sgc-ar-ai-btn { background: #1a1a2e; color: #fff; border: none; border-radius: 7px; padding: 9px 18px; font-size: 12px; font-weight: 600; cursor: pointer; margin-bottom: 14px; }
    .sgc-ar-ai-btn:hover { background: #2d3561; }
    .sgc-ar-status { font-size: 12px; margin-top: 6px; display: none; padding: 8px 12px; border-radius: 6px; }
    .sgc-ar-preview { background: #fdfaf6; border: 1px solid #fde68a; border-radius: 8px; padding: 14px; margin-top: 10px; direction: rtl; font-family: 'Segoe UI', Tahoma, Arial, sans-serif; }
    .sgc-ar-preview h4 { font-size: 16px; color: #1a1a2e; margin: 0 0 6px; font-weight: 700; }
    .sgc-ar-preview p { font-size: 13px; color: #374151; margin: 0; line-height: 1.7; }
    </style>

    <div class="sgc-ar-wrap">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;background:#f0f7ff;border-radius:8px;padding:12px;">
            <span style="font-size:24px;">🇦🇪</span>
            <div>
                <div style="font-size:13px;font-weight:700;color:#1565c0;">Middle East Market — Arabic SEO</div>
                <div style="font-size:11px;color:#6b7280;">UAE, Saudi Arabia, Kuwait, Qatar, Bahrain, Oman — Premium lab glassware buyers</div>
            </div>
            <label style="margin-left:auto;display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                <input type="checkbox" name="sgclabs_ar_active" value="1" <?php checked( $ar_active, '1' ); ?>>
                Enable Arabic SEO
            </label>
        </div>

        <button type="button" class="sgc-ar-ai-btn" onclick="sgcArAiGenerate(<?php echo $post->ID; ?>)">
            🤖 AI Generate Arabic SEO Content
        </button>
        <div id="sgc-ar-status" class="sgc-ar-status"></div>

        <div class="sgc-ar-row">
            <div class="sgc-ar-field">
                <label>عنوان المنتج (Arabic Product Title)</label>
                <input type="text" name="sgclabs_ar_title" id="sgc-ar-title" value="<?php echo esc_attr( $ar_title ); ?>" placeholder="زجاجيات المختبر — بيكر زجاجي 250 مل">
            </div>
            <div class="sgc-ar-field">
                <label>الكلمات المفتاحية (Keywords)</label>
                <input type="text" name="sgclabs_ar_keywords" id="sgc-ar-keywords" value="<?php echo esc_attr( $ar_keywords ); ?>" placeholder="أدوات مختبر, زجاجيات, بيكر">
            </div>
        </div>

        <div class="sgc-ar-field">
            <label>Meta Description (Arabic)</label>
            <textarea name="sgclabs_ar_meta" id="sgc-ar-meta" placeholder="وصف مختصر للمنتج بالعربية لمحركات البحث..."><?php echo esc_textarea( $ar_meta ); ?></textarea>
        </div>

        <div class="sgc-ar-field">
            <label>Product Description (Arabic)</label>
            <textarea name="sgclabs_ar_desc" id="sgc-ar-desc" rows="5" placeholder="وصف تفصيلي للمنتج بالعربية..."><?php echo esc_textarea( $ar_desc ); ?></textarea>
        </div>

        <?php if ( $ar_title || $ar_meta ): ?>
        <div class="sgc-ar-preview">
            <div style="font-size:10px;color:#9ca3af;margin-bottom:8px;direction:ltr;">📱 Google SERP Preview (Arabic)</div>
            <h4><?php echo esc_html( $ar_title ); ?></h4>
            <p style="color:#1565c0;font-size:11px;margin-bottom:4px;direction:ltr;"><?php echo esc_url( get_permalink( $post->ID ) ); ?></p>
            <p><?php echo esc_html( $ar_meta ); ?></p>
        </div>
        <?php endif; ?>
    </div>

    <script>
    function sgcArAiGenerate(pid) {
        var btn    = document.querySelector('.sgc-ar-ai-btn');
        var status = document.getElementById('sgc-ar-status');
        btn.textContent = '⏳ AI generating Arabic content...';
        btn.disabled    = true;
        status.style.display = 'block';
        status.style.background = '#f0f7ff';
        status.style.color = '#1565c0';
        status.textContent = 'AI analyzing product and generating Arabic SEO...';

        jQuery.post(ajaxurl, {
            action:  'sgclabs_ai_generate_arabic',
            nonce:   '<?php echo $nonce; ?>',
            post_id: pid,
        }, function(res) {
            btn.textContent = '🤖 AI Generate Arabic SEO Content';
            btn.disabled    = false;
            if (res.success) {
                var d = res.data;
                if (d.title)    document.getElementById('sgc-ar-title').value    = d.title;
                if (d.meta)     document.getElementById('sgc-ar-meta').value     = d.meta;
                if (d.desc)     document.getElementById('sgc-ar-desc').value     = d.desc;
                if (d.keywords) document.getElementById('sgc-ar-keywords').value = d.keywords;
                status.style.background = '#f0fdf4';
                status.style.color      = '#166534';
                status.textContent      = '✅ Arabic content generated! Review and save.';
            } else {
                status.style.background = '#fef2f2';
                status.style.color      = '#c0392b';
                status.textContent      = '❌ ' + (res.data || 'AI error — check API key');
            }
        }).fail(function() {
            btn.textContent = '🤖 AI Generate Arabic SEO Content';
            btn.disabled    = false;
            status.style.color = '#c0392b';
            status.textContent = '❌ Request failed';
        });
    }
    </script>
    <?php
}

// ── SAVE ARABIC META ──────────────────────────────────────────
add_action( 'save_post_product', 'sgclabs_save_arabic_meta', 10, 2 );
function sgclabs_save_arabic_meta( $post_id, $post ) {
    if ( ! isset( $_POST['sgclabs_arabic_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['sgclabs_arabic_nonce'], 'sgclabs_arabic_seo_nonce' ) ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

    update_post_meta( $post_id, '_sgclabs_ar_active',   isset( $_POST['sgclabs_ar_active'] ) ? '1' : '0' );
    update_post_meta( $post_id, '_sgclabs_ar_title',    sanitize_text_field( wp_unslash( $_POST['sgclabs_ar_title']    ?? '' ) ) );
    update_post_meta( $post_id, '_sgclabs_ar_desc',     sanitize_textarea_field( wp_unslash( $_POST['sgclabs_ar_desc'] ?? '' ) ) );
    update_post_meta( $post_id, '_sgclabs_ar_meta',     sanitize_textarea_field( wp_unslash( $_POST['sgclabs_ar_meta'] ?? '' ) ) );
    update_post_meta( $post_id, '_sgclabs_ar_keywords', sanitize_text_field( wp_unslash( $_POST['sgclabs_ar_keywords'] ?? '' ) ) );
}

// ── AJAX: AI Generate Arabic Content ─────────────────────────
add_action( 'wp_ajax_sgclabs_ai_generate_arabic', 'sgclabs_ai_generate_arabic_handler' );
function sgclabs_ai_generate_arabic_handler() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_products' ) ) wp_send_json_error( 'Unauthorized' );

    $pid = intval( $_POST['post_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'No product ID' );

    $product = wc_get_product( $pid );
    if ( ! $product ) wp_send_json_error( 'Product not found' );

    $name  = $product->get_name();
    $sku   = $product->get_sku();
    $cats  = wp_get_post_terms( $pid, 'product_cat', [ 'fields' => 'names' ] );
    $cat   = ! empty( $cats ) ? $cats[0] : 'Laboratory Glassware';
    $price = (float) $product->get_price();

    $prompt = "You are a professional Arabic SEO copywriter for laboratory glassware products.

Product (English): {$name}
SKU: {$sku}
Category: {$cat}
Price: \${$price} USD
Manufacturer: SGC LABS India — factory direct, ISO certified, B2B export

Generate Arabic SEO content for this product targeting Middle East buyers (UAE, Saudi Arabia, Kuwait).

Return ONLY valid JSON (no markdown, no explanation):
{
  \"title\": \"Arabic product title — 50-60 chars, include product name + key spec\",
  \"meta\": \"Arabic meta description — 130-155 chars, include SGC LABS brand\",
  \"keywords\": \"5-7 Arabic keywords comma separated\",
  \"desc\": \"Arabic product description — 3-4 sentences, professional B2B tone, mention quality + ISO + factory direct\"
}

Use formal Arabic (الفصحى). Include relevant technical lab terms in Arabic.";

    $result = sgclabs_ai_call_for_task( 'multilingual', $prompt, 600, 'Return only valid JSON with Arabic content.' );

    if ( is_wp_error( $result ) ) wp_send_json_error( $result->get_error_message() );

    $clean = trim( preg_replace( '/```json|```/', '', $result ) );
    $data  = json_decode( $clean, true );

    if ( ! $data || ! isset( $data['title'] ) ) wp_send_json_error( 'Could not parse Arabic content. Try again.' );

    wp_send_json_success( [
        'title'    => $data['title']    ?? '',
        'meta'     => $data['meta']     ?? '',
        'keywords' => $data['keywords'] ?? '',
        'desc'     => $data['desc']     ?? '',
    ] );
}

// ── HREFLANG TAGS ─────────────────────────────────────────────
add_action( 'wp_head', 'sgclabs_arabic_hreflang' );
function sgclabs_arabic_hreflang() {
    if ( ! is_product() ) return;
    $product = sgclabs_current_product();
    if ( ! $product ) return;

    $ar_active = get_post_meta( $product->get_id(), '_sgclabs_ar_active', true );
    if ( $ar_active !== '1' ) return;

    $url = get_permalink( $product->get_id() );
    ?>
    <link rel="alternate" hreflang="en" href="<?php echo esc_url( $url ); ?>">
    <link rel="alternate" hreflang="ar" href="<?php echo esc_url( $url ); ?>">
    <link rel="alternate" hreflang="ar-AE" href="<?php echo esc_url( $url ); ?>">
    <link rel="alternate" hreflang="ar-SA" href="<?php echo esc_url( $url ); ?>">
    <link rel="alternate" hreflang="x-default" href="<?php echo esc_url( $url ); ?>">
    <?php
}

// ── INJECT ARABIC META TAGS ───────────────────────────────────
add_action( 'wp_head', 'sgclabs_arabic_meta_tags', 5 );
function sgclabs_arabic_meta_tags() {
    if ( ! is_product() ) return;
    $product = sgclabs_current_product();
    if ( ! $product ) return;

    $pid       = $product->get_id();
    $ar_active = get_post_meta( $pid, '_sgclabs_ar_active',   true );
    if ( $ar_active !== '1' ) return;

    $ar_title    = get_post_meta( $pid, '_sgclabs_ar_title',    true );
    $ar_meta     = get_post_meta( $pid, '_sgclabs_ar_meta',     true );
    $ar_keywords = get_post_meta( $pid, '_sgclabs_ar_keywords', true );

    if ( $ar_title )    echo '<meta name="title:ar" content="' . esc_attr( $ar_title ) . '">' . "\n";
    if ( $ar_meta )     echo '<meta name="description:ar" content="' . esc_attr( $ar_meta ) . '">' . "\n";
    if ( $ar_keywords ) echo '<meta name="keywords:ar" content="' . esc_attr( $ar_keywords ) . '">' . "\n";
    // OpenGraph Arabic
    if ( $ar_title )    echo '<meta property="og:title:ar" content="' . esc_attr( $ar_title ) . '">' . "\n";
    if ( $ar_meta )     echo '<meta property="og:description:ar" content="' . esc_attr( $ar_meta ) . '">' . "\n";
}

// ── ADMIN PAGE ────────────────────────────────────────────────
// Menu registered via admin-menu.php → Site Tools → Amazon tab

function sgclabs_amazon_arabic_admin_page() {
    global $wpdb;
    $ar_count = (int) $wpdb->get_var(
        "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
         WHERE meta_key='_sgclabs_ar_active' AND meta_value='1'"
    );
    ?>
    <div class="wrap" style="font-family:-apple-system,sans-serif;">
    <h1 style="color:#1a1a2e;margin-bottom:20px;">🌍 Amazon Business + Arabic SEO</h1>

    <!-- Tabs -->
    <div style="display:flex;gap:4px;margin-bottom:20px;border-bottom:2px solid #e5e7eb;">
        <button onclick="sgcTab('amazon')" id="tab-amazon" class="sgc-tab active-tab">🏪 Amazon Business Guide</button>
        <button onclick="sgcTab('arabic')" id="tab-arabic" class="sgc-tab">🇦🇪 Arabic SEO Status</button>
    </div>

    <style>
    .sgc-tab{padding:10px 20px;border:none;background:none;cursor:pointer;font-size:13px;font-weight:600;color:#6b7280;border-bottom:2px solid transparent;margin-bottom:-2px;}
    .active-tab{color:#1565c0;border-bottom-color:#1565c0;}
    .sgc-tab-content{display:none;max-width:800px;}
    .sgc-tab-content.active{display:block;}
    .sgc-step{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px 20px;margin-bottom:12px;display:flex;gap:14px;}
    .sgc-step-num{width:32px;height:32px;background:#1565c0;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0;}
    .sgc-step-title{font-size:14px;font-weight:700;color:#1a1a2e;margin-bottom:4px;}
    .sgc-step-desc{font-size:12px;color:#6b7280;line-height:1.6;}
    .sgc-checklist li{font-size:13px;color:#374151;line-height:2;list-style:none;}
    .sgc-checklist li:before{content:"☐ ";color:#1565c0;font-weight:700;}
    .sgc-highlight{background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:14px;margin-bottom:16px;}
    </style>

    <!-- AMAZON TAB -->
    <div id="tab-content-amazon" class="sgc-tab-content active">

        <div class="sgc-highlight">
            <div style="font-size:14px;font-weight:700;color:#166534;margin-bottom:6px;">💰 Amazon Business US — Why it matters for SGC LABS</div>
            <div style="font-size:13px;color:#374151;line-height:1.7;">
                Amazon Business US = <strong>$83 Billion market</strong>. US university labs, pharma companies, hospitals sab Amazon Business se order karte hain.
                SGC LABS listing = direct access to thousands of procurement managers who search "borosilicate beaker", "glass flask", "lab glassware" daily.
            </div>
        </div>

        <h3 style="font-size:15px;color:#1a1a2e;margin:0 0 14px;">📋 Step-by-Step Setup Guide</h3>

        <div class="sgc-step">
            <div class="sgc-step-num">1</div>
            <div>
                <div class="sgc-step-title">Amazon Seller Account — Professional Plan</div>
                <div class="sgc-step-desc">
                    <strong>URL:</strong> <a href="https://sellercentral.amazon.com" target="_blank">sellercentral.amazon.com</a><br>
                    <strong>Cost:</strong> $39.99/month (Professional plan — required for B2B features)<br>
                    <strong>Need:</strong> Business name, tax info (PAN/GSTIN), bank account, credit card<br>
                    <strong>Category:</strong> Industrial & Scientific → Laboratory supplies
                </div>
            </div>
        </div>

        <div class="sgc-step">
            <div class="sgc-step-num">2</div>
            <div>
                <div class="sgc-step-title">Enable Amazon Business (B2B Features)</div>
                <div class="sgc-step-desc">
                    Seller Central → Settings → Account Info → <strong>Amazon Business</strong><br>
                    Yeh enable karo: Business pricing, quantity discounts, enhanced content, bulk ordering<br>
                    <strong>Key benefit:</strong> Business buyers ko tax-exempt purchases allow hoti hain
                </div>
            </div>
        </div>

        <div class="sgc-step">
            <div class="sgc-step-num">3</div>
            <div>
                <div class="sgc-step-title">Product Listings — Lab Glassware Specifics</div>
                <div class="sgc-step-desc">
                    <strong>Title format:</strong> [Brand] [Product] [Size] [Material] [Quantity] [Standard]<br>
                    Example: <em>SGC LABS Borosilicate Glass Beaker 250ml Griffin Low Form ASTM E960 Pack of 12</em><br>
                    <strong>Required fields:</strong> ASIN/UPC, Material (Borosilicate 3.3), Capacity, Compliance standard<br>
                    <strong>Images:</strong> Min 1000×1000px, white background, 6+ images
                </div>
            </div>
        </div>

        <div class="sgc-step">
            <div class="sgc-step-num">4</div>
            <div>
                <div class="sgc-step-title">B2B Pricing Setup</div>
                <div class="sgc-step-desc">
                    Seller Central → Pricing → <strong>Business Pricing</strong><br>
                    Set quantity tiers exactly like labsglass.com:<br>
                    1-9 pcs → Standard price | 10-49 → 10% off | 50+ → 20% off<br>
                    <strong>Pro tip:</strong> Amazon Business buyers filter by "quantity discounts available"
                </div>
            </div>
        </div>

        <div class="sgc-step">
            <div class="sgc-step-num">5</div>
            <div>
                <div class="sgc-step-title">FBA vs FBM Decision</div>
                <div class="sgc-step-desc">
                    <strong>FBM (Fulfilled by Merchant) — Recommended for SGC LABS:</strong><br>
                    ✅ Ship directly from Ambala — no India→USA inventory needed<br>
                    ✅ No Amazon warehouse fees<br>
                    ✅ Control over packaging + customs docs<br>
                    ⚠️ Set realistic shipping time: 15-25 days (sea freight) or 5-7 days (air)<br>
                    Note: "Seller Fulfilled Prime" available after 90 days + performance metrics
                </div>
            </div>
        </div>

        <div class="sgc-step">
            <div class="sgc-step-num">6</div>
            <div>
                <div class="sgc-step-title">Compliance + Certifications</div>
                <div class="sgc-step-desc">
                    Lab glassware ke liye Amazon strict hai:<br>
                    ✅ ISO 9001 certificate upload karo<br>
                    ✅ ASTM/DIN/ISO standard mention karo (E960, ISO 3819)<br>
                    ✅ Material safety: Borosilicate 3.3 clearly mention karo<br>
                    ✅ Country of Origin: India (Made in India badge — buyers like it)<br>
                    📄 Test reports ready rakho — Amazon kisi bhi time maang sakta hai
                </div>
            </div>
        </div>

        <div class="sgc-step">
            <div class="sgc-step-num">7</div>
            <div>
                <div class="sgc-step-title">Keywords Strategy for Lab Glassware</div>
                <div class="sgc-step-desc">
                    <strong>High volume keywords:</strong><br>
                    borosilicate beaker, glass flask laboratory, lab glassware set,
                    erlenmeyer flask glass, boiling flask, graduated cylinder glass,
                    volumetric flask, condenser lab glass, buchner funnel<br><br>
                    <strong>Long tail (less competition):</strong><br>
                    "borosilicate glass beaker 250ml pack", "griffin beaker low form",
                    "lab glassware wholesale", "scientific glass manufacturer"
                </div>
            </div>
        </div>

        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:16px;margin-top:8px;">
            <div style="font-size:13px;font-weight:700;color:#92400e;margin-bottom:8px;">⚠️ Common Mistakes to Avoid</div>
            <ul class="sgc-checklist">
                <li>UPC/barcode nahi hona — GS1 se buy karo (~$250/year for 10 barcodes)</li>
                <li>Shipping time underestimate karna — 25 din safe estimate rakho</li>
                <li>Ek hi listing mein sab sizes mix karna — alag ASIN per size</li>
                <li>Returns policy clear nahi — B2B buyers returns rarely karte hain but policy chahiye</li>
            </ul>
        </div>

        <div style="background:#f0f7ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px;margin-top:12px;">
            <div style="font-size:13px;font-weight:700;color:#1565c0;margin-bottom:8px;">🎯 Also Consider:</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:12px;color:#374151;">
                <div>
                    <div style="font-weight:600;margin-bottom:4px;">🌐 Alibaba.com (International)</div>
                    Gold Supplier membership ~$3000/year<br>
                    Best for Middle East + Asia Pacific buyers
                </div>
                <div>
                    <div style="font-weight:600;margin-bottom:4px;">🇬🇧 Amazon UK</div>
                    Same account, separate marketplace<br>
                    European universities + pharma labs
                </div>
                <div>
                    <div style="font-weight:600;margin-bottom:4px;">🇩🇪 Amazon Germany</div>
                    Largest EU market, German labs<br>
                    Requires German translation
                </div>
                <div>
                    <div style="font-weight:600;margin-bottom:4px;">🇦🇺 Amazon Australia</div>
                    Growing research market<br>
                    Lower competition for lab glassware
                </div>
            </div>
        </div>
    </div>

    <!-- ARABIC TAB -->
    <div id="tab-content-arabic" class="sgc-tab-content">

        <div class="sgc-highlight">
            <div style="font-size:14px;font-weight:700;color:#166534;margin-bottom:6px;">🇦🇪 Arabic SEO — Products with Arabic content: <strong><?php echo $ar_count; ?></strong></div>
            <div style="font-size:13px;color:#374151;">
                Product edit page pe jao → "🌍 Arabic SEO" metabox → "🤖 AI Generate" click karo<br>
                AI automatically Arabic title, meta description, keywords aur description generate karega.
            </div>
        </div>

        <h3 style="font-size:15px;color:#1a1a2e;margin:0 0 14px;">🎯 Middle East Market Facts</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px;">
            <?php
            $facts = [
                [ '🇦🇪', 'UAE', 'Vision 2030 pharma investment. Dubai = logistics hub. Premium buyer.' ],
                [ '🇸🇦', 'Saudi Arabia', 'NEOM + Vision 2030 = massive lab infrastructure spending.' ],
                [ '🇰🇼', 'Kuwait', 'Oil industry + university labs. Bulk buyers.' ],
                [ '🇶🇦', 'Qatar', 'QU + QBRI research. High budget labs.' ],
            ];
            foreach ( $facts as $f ): ?>
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:14px;display:flex;gap:10px;">
                <span style="font-size:24px;"><?php echo $f[0]; ?></span>
                <div>
                    <div style="font-weight:700;font-size:13px;color:#1a1a2e;"><?php echo $f[1]; ?></div>
                    <div style="font-size:11px;color:#6b7280;margin-top:2px;"><?php echo $f[2]; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php
        global $wpdb;
        $ar_products = $wpdb->get_results(
            "SELECT p.ID, p.post_title, pm2.meta_value as ar_title
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sgclabs_ar_active' AND pm.meta_value = '1'
             LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_sgclabs_ar_title'
             WHERE p.post_type = 'product' AND p.post_status = 'publish'
             ORDER BY p.ID DESC LIMIT 10"
        );
        if ( $ar_products ): ?>
        <h3 style="font-size:14px;color:#1a1a2e;margin:0 0 12px;">📋 Products with Arabic SEO (Latest 10)</h3>
        <table style="width:100%;border-collapse:collapse;font-size:12px;background:#fff;border-radius:10px;overflow:hidden;border:1px solid #e5e7eb;">
            <thead><tr style="background:#1a1a2e;">
                <th style="padding:10px 14px;text-align:left;color:#fff;font-weight:600;">Product (English)</th>
                <th style="padding:10px 14px;text-align:right;color:#fff;font-weight:600;">Arabic Title</th>
                <th style="padding:10px 14px;text-align:center;color:#fff;font-weight:600;">Edit</th>
            </tr></thead>
            <tbody>
            <?php foreach ( $ar_products as $p ): ?>
            <tr style="border-bottom:1px solid #f3f4f6;">
                <td style="padding:10px 14px;font-weight:500;color:#1a1a2e;"><?php echo esc_html( substr( $p->post_title, 0, 40 ) ); ?></td>
                <td style="padding:10px 14px;text-align:right;direction:rtl;font-family:Tahoma,Arial,sans-serif;color:#374151;"><?php echo esc_html( $p->ar_title ?: '—' ); ?></td>
                <td style="padding:10px 14px;text-align:center;"><a href="<?php echo get_edit_post_link( $p->ID ); ?>" style="color:#1565c0;font-size:11px;font-weight:600;">Edit →</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="background:#f8f9fa;border-radius:8px;padding:20px;text-align:center;color:#9ca3af;font-size:13px;">
            Koi product Arabic SEO set nahi hai abhi.<br>
            Product edit page pe "🌍 Arabic SEO" section mein enable karo.
        </div>
        <?php endif; ?>
    </div>

    </div>

    <script>
    function sgcTab(tab) {
        document.querySelectorAll('.sgc-tab').forEach(function(t) { t.classList.remove('active-tab'); });
        document.querySelectorAll('.sgc-tab-content').forEach(function(c) { c.classList.remove('active'); });
        document.getElementById('tab-' + tab).classList.add('active-tab');
        document.getElementById('tab-content-' + tab).classList.add('active');
    }
    </script>
    <?php
}
