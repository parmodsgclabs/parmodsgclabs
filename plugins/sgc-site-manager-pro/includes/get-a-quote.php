<?php
if (!defined('ABSPATH')) exit;

// ============================================================
// SGC LABS® — GET A QUOTE MODULE
// WooCommerce product pages pe "Get a Quote" button inject karta hai
// CF7 ya built-in modal form dono support karta hai
// Supports: Single product, Cart quote, Floating button, Archive page
// ============================================================

// ============================================================
// SETTINGS SAVE
// ============================================================
add_action('admin_post_sgclabs_save_quote', 'sgclabs_save_quote_settings');
function sgclabs_save_quote_settings() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'sgclabs_quote_nonce')) wp_die('Unauthorized');

    $fields = [
        'sgclabs_quote_active'          => 'checkbox',
        'sgclabs_quote_hide_cart'       => 'checkbox',
        'sgclabs_quote_hide_price'      => 'checkbox',
        'sgclabs_quote_show_archive'    => 'checkbox',
        'sgclabs_quote_floating_btn'    => 'checkbox',
        'sgclabs_quote_use_cf7'         => 'checkbox',
        'sgclabs_quote_btn_text'        => 'text',
        'sgclabs_quote_btn_color'       => 'color',
        'sgclabs_quote_btn_text_color'  => 'color',
        'sgclabs_quote_modal_title'     => 'text',
        'sgclabs_quote_success_msg'     => 'text',
        'sgclabs_quote_notify_email'    => 'email',
        'sgclabs_quote_cf7_id'          => 'int',
        'sgclabs_quote_btn_position'    => 'text',
        'sgclabs_quote_exclude_cats'    => 'text',
    ];

    foreach ($fields as $key => $type) {
        if ($type === 'checkbox') {
            update_option($key, isset($_POST[$key]) ? '1' : '0');
        } elseif ($type === 'color') {
            update_option($key, sanitize_hex_color($_POST[$key] ?? ''));
        } elseif ($type === 'int') {
            update_option($key, intval($_POST[$key] ?? 0));
        } elseif ($type === 'email') {
            update_option($key, sanitize_email($_POST[$key] ?? ''));
        } else {
            update_option($key, sanitize_text_field($_POST[$key] ?? ''));
        }
    }

    wp_redirect(admin_url('admin.php?page=sgclabs-quotes-inquiries&tab=quote-settings&saved=1'));
    exit;
}

// ============================================================
// FRONTEND: INJECT QUOTE BUTTON ON PRODUCT PAGES
// ============================================================
add_action('wp', 'sgclabs_quote_frontend_hooks');
function sgclabs_quote_frontend_hooks() {
    if (get_option('sgclabs_quote_active', '1') !== '1') return;
    if (!class_exists('WooCommerce')) return;

    // Single product page — button after Add to Cart
    $position = get_option('sgclabs_quote_btn_position', 'after_cart');
    if ($position === 'after_cart') {
        add_action('woocommerce_after_add_to_cart_button', 'sgclabs_render_quote_button', 10);
    } else {
        add_action('woocommerce_before_add_to_cart_button', 'sgclabs_render_quote_button', 10);
    }

    // Archive / shop page
    if (get_option('sgclabs_quote_show_archive', '1') === '1') {
        add_action('woocommerce_after_shop_loop_item', 'sgclabs_render_quote_button_archive', 12);
    }

    // Hide Add to Cart button if enabled
    if (get_option('sgclabs_quote_hide_cart', '0') === '1') {
        add_filter('woocommerce_is_purchasable', '__return_false');
        add_filter('woocommerce_sale_flash', '__return_false');
    }

    // Hide price if enabled
    if (get_option('sgclabs_quote_hide_price', '0') === '1') {
        add_filter('woocommerce_get_price_html', function($price, $product) {
            return '<span class="sgclabs-price-hidden">Price on Request</span>';
        }, 10, 2);
    }

    // Floating quote button (all pages)
    if (get_option('sgclabs_quote_floating_btn', '0') === '1') {
        add_action('wp_footer', 'sgclabs_render_floating_quote_button');
    }

    // Enqueue styles + scripts
    add_action('wp_footer', 'sgclabs_quote_frontend_assets', 5);
}

// ============================================================
// RENDER: SINGLE PRODUCT PAGE BUTTON
// ============================================================
function sgclabs_render_quote_button() {
    global $product;
    if (!$product) return;

    // Check excluded categories
    $exclude_cats = get_option('sgclabs_quote_exclude_cats', '');
    if ($exclude_cats) {
        $excluded = array_map('trim', explode(',', $exclude_cats));
        if (has_term($excluded, 'product_cat', $product->get_id())) return;
    }

    $btn_text  = get_option('sgclabs_quote_btn_text', '💬 Get a Quote');
    $btn_color = get_option('sgclabs_quote_btn_color', '#1565c0');
    $txt_color = get_option('sgclabs_quote_btn_text_color', '#ffffff');
    $use_cf7   = get_option('sgclabs_quote_use_cf7', '0');
    $cf7_id    = intval(get_option('sgclabs_quote_cf7_id', 0));

    $product_name = $product->get_name();
    $product_id   = $product->get_id();
    $product_sku  = $product->get_sku();
    $product_url  = get_permalink($product_id);

    // Render PDF brochure button if active — side by side with quote
    $pdf_active  = get_option( 'sgclabs_pdf_active', '1' ) === '1';
    $pdf_text    = esc_html( get_option( 'sgclabs_pdf_btn_text', '📄 Download Brochure' ) );
    $pdf_bg      = esc_attr( get_option( 'sgclabs_pdf_btn_color', '#c62828' ) );
    $pdf_fg      = esc_attr( get_option( 'sgclabs_pdf_btn_text_color', '#ffffff' ) );

    echo '<div class="sgclabs-btn-row" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:16px;margin-bottom:16px;width:100%;">';

    if ($use_cf7 === '1' && $cf7_id > 0 && function_exists('wpcf7_contact_form')) {
        // CF7 modal mode
        echo '<button type="button" class="sgclabs-quote-btn"
                style="background:' . esc_attr($btn_color) . ';color:' . esc_attr($txt_color) . ';"
                data-product="' . esc_attr($product_name) . '"
                data-sku="' . esc_attr($product_sku) . '"
                data-url="' . esc_url($product_url) . '"
                data-mode="cf7"
                data-cf7id="' . esc_attr($cf7_id) . '"
                onclick="sgclabsOpenQuoteModal(this)">' . esc_html($btn_text) . '</button>';
    } else {
        // Built-in modal mode
        echo '<button type="button" class="sgclabs-quote-btn"
                style="background:' . esc_attr($btn_color) . ';color:' . esc_attr($txt_color) . ';"
                data-product="' . esc_attr($product_name) . '"
                data-sku="' . esc_attr($product_sku) . '"
                data-url="' . esc_url($product_url) . '"
                data-mode="builtin"
                onclick="sgclabsOpenQuoteModal(this)">' . esc_html($btn_text) . '</button>';
    }

    // PDF Brochure button — same row
    if ( $pdf_active ) {
        echo '<button type="button"
            onclick="sgcPdfOpen(' . intval($product_id) . ')"
            class="sgclabs-pdf-inline-btn"
            style="background:' . $pdf_bg . ';color:' . $pdf_fg . ';
            border:none;cursor:pointer;font-family:inherit;
            transition:opacity .2s,transform .1s;"
            onmouseover="this.style.opacity=\'0.88\';this.style.transform=\'translateY(-1px)\'"
            onmouseout="this.style.opacity=\'1\';this.style.transform=\'translateY(0)\'">'
            . $pdf_text . '</button>';
    }

    echo '</div>';
}

// ============================================================
// RENDER: ARCHIVE / SHOP PAGE BUTTON
// ============================================================
function sgclabs_render_quote_button_archive() {
    global $product;
    if (!$product) return;

    $btn_text  = get_option('sgclabs_quote_btn_text', '💬 Get a Quote');
    $btn_color = get_option('sgclabs_quote_btn_color', '#1565c0');
    $txt_color = get_option('sgclabs_quote_btn_text_color', '#ffffff');

    $product_name = $product->get_name();
    $product_sku  = $product->get_sku();
    $product_url  = get_permalink($product->get_id());

    echo '<button type="button" class="sgclabs-quote-btn sgclabs-quote-btn-archive"
            style="background:' . esc_attr($btn_color) . ';color:' . esc_attr($txt_color) . ';margin-top:8px;width:100%;"
            data-product="' . esc_attr($product_name) . '"
            data-sku="' . esc_attr($product_sku) . '"
            data-url="' . esc_url($product_url) . '"
            data-mode="' . (get_option('sgclabs_quote_use_cf7','0')==='1' && intval(get_option('sgclabs_quote_cf7_id',0)) > 0 ? 'cf7' : 'builtin') . '"
            data-cf7id="' . esc_attr(get_option('sgclabs_quote_cf7_id','0')) . '"
            onclick="sgclabsOpenQuoteModal(this)">' . esc_html($btn_text) . '</button>';
}

// ============================================================
// RENDER: FLOATING BUTTON
// ============================================================
function sgclabs_render_floating_quote_button() {
    $btn_text  = get_option('sgclabs_quote_btn_text', '💬 Get a Quote');
    $btn_color = get_option('sgclabs_quote_btn_color', '#1565c0');
    $txt_color = get_option('sgclabs_quote_btn_text_color', '#ffffff');
    echo '
    <div id="sgclabs-floating-quote" style="position:fixed;bottom:90px;right:20px;z-index:9998;">
        <button type="button" class="sgclabs-quote-btn"
            style="background:' . esc_attr($btn_color) . ';color:' . esc_attr($txt_color) . ';
                   border-radius:50px;padding:12px 20px;box-shadow:0 4px 15px rgba(0,0,0,0.25);
                   font-size:14px;font-weight:600;"
            data-product="" data-sku="" data-url="" data-mode="builtin"
            onclick="sgclabsOpenQuoteModal(this)">' . esc_html($btn_text) . '</button>
    </div>';
}

// ============================================================
// FRONTEND ASSETS: MODAL HTML + CSS + JS
// ============================================================
function sgclabs_quote_frontend_assets() {
    $modal_title = get_option('sgclabs_quote_modal_title', 'Request a Quote / Pre-Sale Query');
    $success_msg = get_option('sgclabs_quote_success_msg', '✅ Your quote request has been sent! SGC LABS® team will get back to you within 24 hours.');
    $btn_color   = get_option('sgclabs_quote_btn_color', '#1565c0');
    $use_cf7     = get_option('sgclabs_quote_use_cf7', '0');
    $cf7_id      = intval(get_option('sgclabs_quote_cf7_id', 0));

    $countries = [
        // Top export markets first
        'India','USA','UK','Germany','UAE','Saudi Arabia','Australia','Canada',
        // Europe
        'France','Italy','Spain','Netherlands','Belgium','Switzerland','Sweden',
        'Denmark','Norway','Finland','Poland','Czech Republic','Austria','Ireland',
        'Portugal','Greece','Romania','Hungary','Croatia',
        // Middle East & Africa
        'Qatar','Kuwait','Oman','Bahrain','Jordan','Lebanon','Iraq','Iran',
        'Egypt','South Africa','Nigeria','Kenya','Ghana','Tanzania','Morocco',
        'Tunisia','Ethiopia','Uganda',
        // Asia Pacific
        'China','Japan','South Korea','Singapore','Malaysia','Indonesia',
        'Thailand','Vietnam','Philippines','Taiwan','Hong Kong','Bangladesh',
        'Pakistan','Sri Lanka','Nepal','Myanmar',
        // Americas
        'Brazil','Mexico','Argentina','Colombia','Chile','Peru','Ecuador',
        'Venezuela','Costa Rica','Guatemala','Dominican Republic','Cuba',
        // CIS
        'Russia','Kazakhstan','Uzbekistan','Ukraine',
        // Oceania
        'New Zealand','Fiji',
        // Other
        'Turkey','Israel','Other',
    ];
    $country_options = '';
    foreach ($countries as $c) {
        $country_options .= '<option value="' . esc_attr($c) . '">' . esc_html($c) . '</option>';
    }
    ?>
    <!-- SGC LABS® Quote Modal -->
    <div id="sgclabs-quote-overlay" style="display:none;" onclick="sgclabsCloseQuoteModal(event)">
        <div id="sgclabs-quote-modal">
            <button id="sgclabs-quote-close" onclick="sgclabsCloseQuoteModal(null, true)" title="Close">&times;</button>

            <!-- Loading overlay — shown during form submission -->
            <div id="sgcq-loading-overlay">
                <div class="sgcq-spinner"></div>
                <div class="sgcq-loading-text">Submitting your request...</div>
                <div class="sgcq-loading-sub">Please wait, do not close this window</div>
                <div class="sgcq-progress-wrap">
                    <div class="sgcq-progress-bar" id="sgcq-progress-bar"></div>
                </div>
            </div>

            <div id="sgclabs-quote-header">
                <div id="sgclabs-quote-icon">💬</div>
                <div>
                    <h2 id="sgclabs-quote-title"><?php echo esc_html($modal_title); ?></h2>
                    <p id="sgclabs-quote-subtitle">Fill the form below — we respond within 24 hours</p>
                </div>
            </div>

            <!-- Product badge (auto-filled) -->
            <div id="sgclabs-quote-product-badge" style="display:none;">
                <span id="sgclabs-quote-product-name"></span>
                <span id="sgclabs-quote-product-sku" style="opacity:0.7;font-size:12px;"></span>
            </div>

            <!-- CF7 container (shown when CF7 mode) -->
            <div id="sgclabs-quote-cf7-wrap" style="display:none;">
                <?php
                if ($use_cf7 === '1' && $cf7_id > 0 && function_exists('wpcf7_contact_form')) {
                    echo do_shortcode('[contact-form-7 id="' . $cf7_id . '"]');
                }
                ?>
            </div>

            <!-- Built-in form (shown when builtin mode) -->
            <div id="sgclabs-quote-builtin-form">
                <div id="sgclabs-quote-msg" style="display:none;"></div>

                <div class="sgcq-row">
                    <div class="sgcq-col">
                        <label>Your Name <span class="sgcq-req">*</span></label>
                        <input type="text" id="sgcq_name" placeholder="Full name" autocomplete="name">
                    </div>
                    <div class="sgcq-col">
                        <label>Email Address <span class="sgcq-req">*</span></label>
                        <input type="email" id="sgcq_email" placeholder="your@email.com" autocomplete="email">
                    </div>
                </div>

                <div class="sgcq-row">
                    <div class="sgcq-col">
                        <label>Phone / WhatsApp</label>
                        <input type="tel" id="sgcq_phone" placeholder="+91 999 000 0000" autocomplete="tel">
                    </div>
                    <div class="sgcq-col">
                        <label>Country <span class="sgcq-req">*</span></label>
                        <!-- Custom dropdown — native <select> clips inside overflow:auto modal -->
                        <input type="hidden" id="sgcq_country" value="">
                        <div class="sgcq-custom-select" id="sgcq-country-wrap">
                            <div class="sgcq-cs-display" id="sgcq-country-display" onclick="sgcqToggleCountry(event)" tabindex="0" onkeydown="if(event.key==='Enter'||event.key===' ')sgcqToggleCountry(event)">
                                <span id="sgcq-country-label">-- Select Country --</span>
                                <span class="sgcq-cs-arrow">▾</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sgcq-row">
                    <div class="sgcq-col" style="flex:2">
                        <label>Product / Item <span class="sgcq-req">*</span></label>
                        <input type="text" id="sgcq_product" placeholder="Product name or description" readonly>
                    </div>
                    <div class="sgcq-col" style="flex:1">
                        <label>Quantity Required</label>
                        <input type="text" id="sgcq_qty" placeholder="e.g. 100 pcs, bulk">
                    </div>
                </div>

                <!-- Query type selector -->
                <div class="sgcq-row">
                    <div class="sgcq-col" style="flex:1">
                        <label>Query Type</label>
                        <div class="sgcq-type-wrap">
                            <label class="sgcq-type-chip">
                                <input type="radio" name="sgcq_type" value="Quote Request" checked>
                                <span>&#x1F4CB; Quote Request</span>
                            </label>
                            <label class="sgcq-type-chip">
                                <input type="radio" name="sgcq_type" value="Pre-Sale Question">
                                <span>&#x2753; Pre-Sale Question</span>
                            </label>
                            <label class="sgcq-type-chip">
                                <input type="radio" name="sgcq_type" value="Custom / OEM Order">
                                <span>&#x1F527; Custom / OEM Order</span>
                            </label>
                            <label class="sgcq-type-chip">
                                <input type="radio" name="sgcq_type" value="Bulk / Export Order">
                                <span>&#x1F30D; Bulk / Export</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Buyer type selector (v7.4.4) -->
                <div class="sgcq-row">
                    <div class="sgcq-col" style="flex:1">
                        <label>Who are you? <span style="font-weight:400;color:#aaa;font-size:12px">(optional)</span></label>
                        <div class="sgcq-type-wrap">
                            <label class="sgcq-type-chip">
                                <input type="radio" name="sgcq_buyer_type" value="dealer">
                                <span>&#x1F3EA; Dealer</span>
                            </label>
                            <label class="sgcq-type-chip">
                                <input type="radio" name="sgcq_buyer_type" value="researcher">
                                <span>&#x1F52C; Research Lab</span>
                            </label>
                            <label class="sgcq-type-chip">
                                <input type="radio" name="sgcq_buyer_type" value="university">
                                <span>&#x1F393; University</span>
                            </label>
                            <label class="sgcq-type-chip">
                                <input type="radio" name="sgcq_buyer_type" value="glass_blower">
                                <span>&#x1F4A8; Glass Blower</span>
                            </label>
                            <label class="sgcq-type-chip">
                                <input type="radio" name="sgcq_buyer_type" value="distributor">
                                <span>&#x1F30D; Distributor</span>
                            </label>
                            <label class="sgcq-type-chip">
                                <input type="radio" name="sgcq_buyer_type" value="pharma">
                                <span>&#x1F3E5; Pharma</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="sgcq-row">
                    <div class="sgcq-col" style="flex:1">
                        <label>Message / Requirements</label>
                        <textarea id="sgcq_message" rows="4" placeholder="Describe your requirements — specifications, OEM branding, custom sizes, delivery timeline, destination country..."></textarea>
                    </div>
                </div>

                <!-- Attachment (optional) -->
                <div class="sgcq-row">
                    <div class="sgcq-col" style="flex:1">
                        <label>Attach Drawing / Specs (optional)</label>
                        <div id="sgcq-file-zone">
                            <input type="file" id="sgcq_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="display:none" onchange="sgcqFileChange(this)">
                            <div id="sgcq-file-label" onclick="document.getElementById('sgcq_file').click()">
                                📎 Click to attach file (PDF, Image, DOC — max 5MB)
                            </div>
                            <div id="sgcq-file-name" style="display:none;"></div>
                        </div>
                    </div>
                </div>

                <div class="sgcq-footer">
                    <div class="sgcq-security">
                        🔒 Your information is secure and will only be used to process your quote
                    </div>
                    <button type="button" id="sgcq-submit-btn" onclick="sgclabsSubmitQuote()"
                        style="background:<?php echo esc_attr($btn_color); ?>">
                        📨 Send Quote Request
                    </button>
                </div>
            </div>

        </div>
    </div>

    <style>
    /* ===== SGC LABS® Quote Modal ===== */
    #sgclabs-quote-overlay {
        position: fixed; inset: 0;
        background: rgba(0,0,0,0.65);
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 15px;
        backdrop-filter: blur(3px);
        animation: sgcqFadeIn 0.2s ease;
    }
    @keyframes sgcqFadeIn { from{opacity:0} to{opacity:1} }

    #sgclabs-quote-modal {
        background: #fff;
        border-radius: 16px;
        width: 100%;
        max-width: 620px;
        max-height: 92vh;
        overflow-y: auto;
        padding: 30px;
        position: relative;
        box-shadow: 0 25px 60px rgba(0,0,0,0.35);
        animation: sgcqSlideUp 0.25s ease;
    }
    @keyframes sgcqSlideUp { from{transform:translateY(30px);opacity:0} to{transform:translateY(0);opacity:1} }

    #sgclabs-quote-close {
        position: absolute; top: 14px; right: 16px;
        background: #f0f0f0; border: none; border-radius: 50%;
        width: 32px; height: 32px; font-size: 20px; line-height: 1;
        cursor: pointer; color: #555; transition: all 0.2s;
        display: flex; align-items: center; justify-content: center;
    }
    #sgclabs-quote-close:hover { background: #e0e0e0; color: #000; }

    #sgclabs-quote-header {
        display: flex; gap: 14px; align-items: flex-start;
        margin-bottom: 20px; padding-bottom: 18px;
        border-bottom: 2px solid #f0f0f0;
    }
    #sgclabs-quote-icon {
        font-size: 32px; width: 52px; height: 52px;
        background: #f0f4ff; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    #sgclabs-quote-title {
        font-size: 18px; font-weight: 700;
        color: #1a1a2e; margin: 0 0 4px;
    }
    #sgclabs-quote-subtitle { font-size: 13px; color: #666; margin: 0; }

    #sgclabs-quote-product-badge {
        background: linear-gradient(135deg, #f0f4ff, #e8ecff);
        border: 1px solid #c5d0f5;
        border-radius: 8px; padding: 10px 14px;
        margin-bottom: 16px;
        display: flex; gap: 10px; align-items: center;
        font-size: 14px; font-weight: 600; color: #1a1a2e;
    }

    .sgcq-row { display: flex; gap: 14px; margin-bottom: 14px; flex-wrap: wrap; }
    .sgcq-col { flex: 1; min-width: 180px; display: flex; flex-direction: column; gap: 5px; }
    .sgcq-col label { font-size: 13px; font-weight: 600; color: #333; }
    .sgcq-req { color: #e74c3c; }
    .sgcq-col input, .sgcq-col select, .sgcq-col textarea {
        border: 1.5px solid #e0e0e0; border-radius: 8px;
        padding: 9px 12px; font-size: 14px; width: 100%;
        box-sizing: border-box; transition: border-color 0.2s;
        font-family: inherit;
    }
    .sgcq-custom-select { position: relative; }
    .sgcq-cs-display {
        border: 1.5px solid #e0e0e0; border-radius: 8px;
        padding: 9px 12px; font-size: 14px; cursor: pointer;
        background: #fff; display: flex; justify-content: space-between;
        align-items: center; min-height: 40px; user-select: none;
        transition: border-color .2s;
    }
    .sgcq-cs-display:hover, .sgcq-cs-display:focus { border-color: #1565c0; outline: none; }
    .sgcq-cs-display.open { border-color: #1565c0; border-bottom-color: transparent; border-radius: 8px 8px 0 0; }
    .sgcq-cs-arrow { font-size: 12px; color: #888; transition: transform .2s; }
    .sgcq-cs-display.open .sgcq-cs-arrow { transform: rotate(180deg); }
    /* Dropdown panel — position:fixed so it escapes all overflow:hidden parents */
    #sgcq-country-panel {
        position: fixed; /* KEY FIX — escapes modal overflow */
        z-index: 999999; /* above modal z-index:99999 */
        background: #fff;
        border: 1.5px solid #1565c0;
        border-radius: 0 0 10px 10px;
        box-shadow: 0 8px 30px rgba(0,0,0,.15);
        max-height: 220px; overflow-y: auto;
        display: none;
    }
    #sgcq-country-panel.open { display: block; }
    #sgcq-country-search {
        width: 100%; padding: 9px 12px; border: none;
        border-bottom: 1px solid #eee; font-size: 13px;
        outline: none; box-sizing: border-box;
        position: sticky; top: 0; background: #fff; z-index: 1;
    }
    .sgcq-cs-opt {
        padding: 9px 14px; font-size: 13px; cursor: pointer;
        transition: background .1s;
    }
    .sgcq-cs-opt:hover, .sgcq-cs-opt.highlighted { background: #e8f0fe; color: #1565c0; font-weight: 600; }
    .sgcq-cs-noresult { padding: 10px 14px; color: #888; font-size: 13px; }
    .sgcq-col input:focus, .sgcq-col select:focus, .sgcq-col textarea:focus {
        border-color: #1a1a2e; outline: none;
        box-shadow: 0 0 0 3px rgba(26,26,46,0.08);
    }
    .sgcq-col input[readonly] { background: #f8f8ff; cursor: default; }

    /* Query type chips */
    .sgcq-type-wrap { display: flex; gap: 8px; flex-wrap: wrap; }
    .sgcq-type-chip { cursor: pointer; }
    .sgcq-type-chip input { display: none; }
    .sgcq-type-chip span {
        display: block; padding: 7px 12px;
        background: #f5f5f5; border: 1.5px solid #e0e0e0;
        border-radius: 20px; font-size: 12.5px; font-weight: 500;
        color: #444; transition: all 0.15s; white-space: nowrap;
    }
    .sgcq-type-chip input:checked + span {
        background: #1a1a2e; color: #fff; border-color: #1a1a2e;
    }

    /* File zone */
    #sgcq-file-zone {
        border: 2px dashed #d0d0d0; border-radius: 8px;
        padding: 12px 16px; text-align: center;
        cursor: pointer; font-size: 13px; color: #666;
        transition: border-color 0.2s;
    }
    #sgcq-file-zone:hover { border-color: #1a1a2e; color: #1a1a2e; }
    #sgcq-file-label { cursor: pointer; }
    #sgcq-file-name { font-size: 13px; color: #27ae60; font-weight: 600; margin-top: 4px; }

    /* Footer */
    .sgcq-footer {
        display: flex; gap: 12px; align-items: center;
        margin-top: 16px; flex-wrap: wrap;
    }
    .sgcq-security { font-size: 12px; color: #888; flex: 1; }
    #sgcq-submit-btn {
        color: #fff; border: none; padding: 13px 28px;
        border-radius: 8px; font-size: 15px; font-weight: 600;
        cursor: pointer; transition: all 0.2s; white-space: nowrap;
        position: relative; min-width: 200px;
    }
    #sgcq-submit-btn:hover:not(:disabled) { opacity: 0.88; transform: translateY(-1px); }
    #sgcq-submit-btn:disabled { opacity: 1; cursor: not-allowed; }

    /* Loading overlay on modal */
    #sgcq-loading-overlay {
        display: none;
        position: absolute; inset: 0;
        background: rgba(255,255,255,0.85);
        border-radius: 16px;
        z-index: 10;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 16px;
    }
    #sgcq-loading-overlay.active { display: flex; }

    /* Spinner */
    .sgcq-spinner {
        width: 48px; height: 48px;
        border: 4px solid #e0e0e0;
        border-top-color: #1a1a2e;
        border-radius: 50%;
        animation: sgcqSpin 0.8s linear infinite;
    }
    @keyframes sgcqSpin { to { transform: rotate(360deg); } }

    .sgcq-loading-text {
        font-size: 15px; font-weight: 600; color: #1a1a2e;
    }
    .sgcq-loading-sub {
        font-size: 12px; color: #888; margin-top: -10px;
    }

    /* Progress bar */
    .sgcq-progress-wrap {
        width: 220px; height: 4px;
        background: #e0e0e0; border-radius: 4px; overflow: hidden;
    }
    .sgcq-progress-bar {
        height: 100%; width: 0%;
        background: linear-gradient(90deg, #1a1a2e, #27ae60);
        border-radius: 4px;
        transition: width 0.3s ease;
    }

    /* Prevent close while submitting */
    #sgclabs-quote-modal.submitting #sgclabs-quote-close { 
        pointer-events: none; opacity: 0.3; 
    }

    /* Message box */
    #sgclabs-quote-msg {
        border-radius: 8px; padding: 12px 16px;
        font-size: 14px; margin-bottom: 14px;
        border: 1px solid transparent;
    }
    #sgclabs-quote-msg.success { background: #f0fff4; color: #155724; border-color: #b7e1c0; }
    #sgclabs-quote-msg.error   { background: #fff0f0; color: #721c24; border-color: #f5c6cb; }

    /* Button base styles */
    .sgclabs-quote-btn {
        border: none; padding: 13px 20px; border-radius: 7px;
        font-size: 14px; font-weight: 600; cursor: pointer;
        transition: opacity 0.2s, transform 0.1s;
        display: inline-flex; align-items: center; justify-content: center;
        gap: 7px; letter-spacing: 0.2px; width: 100%;
        min-height: 50px; box-sizing: border-box;
    }
    .sgclabs-quote-btn:hover { opacity: 0.88; transform: translateY(-1px); }
    .sgclabs-quote-btn:active { transform: translateY(0); }

    /* Side-by-side layout for Quote + Brochure */
    /* ── Equal spacing between all button sections ────── */

    /* ADD TO CART button ke neeche gap */
    .woocommerce div.product form.cart .single_add_to_cart_button {
        margin-bottom: 0 !important;
    }

    /* GET A QUOTE + BROCHURE row — upar 16px, neeche 16px */
    .sgclabs-btn-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin: 16px 0 16px 0;
        width: 100%;
    }

    /* Quote + Brochure buttons equal size */
    .sgclabs-btn-row .sgclabs-quote-btn,
    .sgclabs-btn-row .sgclabs-pdf-inline-btn {
        width: 100% !important;
        min-height: 52px !important;
        padding: 14px 12px !important;
        font-size: 14px !important;
        font-weight: 600 !important;
        border-radius: 7px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 6px !important;
        box-sizing: border-box !important;
        margin: 0 !important;
        letter-spacing: 0.2px;
        white-space: nowrap;
    }

    /* ADD TO CART button ke neeche gap */
    .woocommerce div.product form.cart .single_add_to_cart_button {
        margin-bottom: 0 !important;
    }
    /* Hamara btn-row — Add to Cart ke turant baad */
    .woocommerce div.product form.cart .sgclabs-btn-row {
        margin-top: 16px !important;
        margin-bottom: 0 !important;
    }
    /* form.cart ke baad pehla element — PayPal */
    .woocommerce div.product form.cart {
        margin-bottom: 0 !important;
        padding-bottom: 0 !important;
    }

    /* PayPal + koi bhi payment button — upar 16px gap */
    .woocommerce div.product form.cart + *,
    .woocommerce div.product form.cart ~ div,
    .woocommerce div.product form.cart ~ p,
    .woocommerce div.product form.cart ~ span,
    .woocommerce div.product .woocommerce-variation-add-to-cart + *,
    .woocommerce-PaymentBox,
    .wc-block-components-payment-methods,
    .woocommerce div.product .wc-paypal,
    .woocommerce div.product .paypal,
    .woocommerce div.product [class*="paypal"],
    .woocommerce div.product [id*="paypal"],
    .woocommerce div.product [class*="PayPal"],
    .woocommerce div.product [id*="PayPal"],
    .woocommerce div.product [class*="ppcp"],
    .woocommerce div.product [class*="woo_pp"],
    .paypal-button,
    .paypal-button-number-0,
    [class*="paypal-button-layout"],
    [class*="paypal-button-color"] {
        margin-top: 16px !important;
    }

    /* Price hidden text */
    .sgclabs-price-hidden {
        font-style: italic; color: #888; font-size: 14px;
    }

    /* Success animation */
    #sgclabs-quote-success-screen {
        text-align: center; padding: 30px 20px;
    }
    #sgclabs-quote-success-screen .sgcq-success-icon {
        font-size: 60px; margin-bottom: 16px;
    }
    #sgclabs-quote-success-screen h3 {
        font-size: 20px; color: #155724; margin-bottom: 8px;
    }
    #sgclabs-quote-success-screen p { color: #666; font-size: 14px; }

    /* Scrollbar inside modal */
    #sgclabs-quote-modal::-webkit-scrollbar { width: 5px; }
    #sgclabs-quote-modal::-webkit-scrollbar-thumb { background: #d0d0d0; border-radius: 5px; }

    @media (max-width: 600px) {
        #sgclabs-quote-modal { padding: 16px; }
        .sgcq-row { flex-direction: column; gap: 10px; }
        .sgcq-col { min-width: 100%; }
        .sgcq-type-wrap { gap: 6px; }
        .sgcq-footer { flex-direction: column; gap: 10px; }
        #sgcq-submit-btn { width: 100%; }
        #sgcq-file-zone { padding: 10px; font-size: 12px; }
        .sgcq-cs-display { font-size: 13px; }
    }
    </style>

    <script>
    // Store current product info
    var sgcqCurrentProduct = { name: '', sku: '', url: '', mode: 'builtin', cf7id: 0 };

    // ── Inject modal HTML on-demand (homepage / page builder fix) ──────
    // When button is added via shortcode/Elementor/widget, the modal HTML
    // (which normally lives in wp_footer) may not yet be in the DOM.
    // This function creates a minimal functional modal at click time.
    function sgclabsInjectModal() {
        if (document.getElementById('sgclabs-quote-overlay')) return; // already there

        var countries = <?php
            $cl = ['India','USA','UK','Germany','UAE','Saudi Arabia','Australia','Canada',
                   'France','Italy','Spain','Netherlands','Belgium','Switzerland','Sweden',
                   'Denmark','Norway','Finland','Poland','Czech Republic','Austria','Ireland',
                   'Portugal','Greece','Romania','Hungary','Croatia',
                   'Qatar','Kuwait','Oman','Bahrain','Jordan','Lebanon','Iraq','Iran',
                   'Egypt','South Africa','Nigeria','Kenya','Ghana','Tanzania','Morocco',
                   'Tunisia','Ethiopia','Uganda','China','Japan','South Korea','Singapore',
                   'Malaysia','Indonesia','Thailand','Vietnam','Philippines','Taiwan',
                   'Hong Kong','Bangladesh','Pakistan','Sri Lanka','Nepal','Myanmar',
                   'Brazil','Mexico','Argentina','Colombia','Chile','Peru','Ecuador',
                   'Venezuela','Costa Rica','Dominican Republic','Russia','Kazakhstan',
                   'Uzbekistan','Ukraine','New Zealand','Fiji','Turkey','Israel','Other'];
            echo wp_json_encode($cl);
        ?>;

        var opts = countries.map(function(c){ return '<option value="'+c+'">'+c+'</option>'; }).join('');

        var html = '<div id="sgclabs-quote-overlay" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.65);display:none;align-items:center;justify-content:center;" onclick="sgclabsCloseQuoteModal(event)">'
            + '<div style="background:#fff;border-radius:16px;width:700px;max-width:95vw;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">'
            + '<div style="background:#1a1a2e;color:#fff;padding:18px 22px;border-radius:16px 16px 0 0;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:1;">'
            + '<div><h2 style="margin:0;font-size:20px;font-weight:700;"><?php echo esc_js(get_option('sgclabs_quote_modal_title','Request a Quote / Pre-Sale Query')); ?></h2>'
            + '<p style="margin:4px 0 0;font-size:12px;opacity:.7;">Fill in the form below — our team responds within 24 hours</p></div>'
            + '<button id="sgclabs-quote-close" onclick="sgclabsCloseQuoteModal(null,true)" style="background:none;border:none;color:#fff;font-size:28px;cursor:pointer;line-height:1;padding:0 4px;">&times;</button>'
            + '</div>'
            + '<div id="sgclabs-quote-builtin-form" style="padding:22px;">'
            + '<div id="sgclabs-quote-product-badge" style="display:none;background:#f0f4ff;border:1px solid #c7d7ff;border-radius:8px;padding:10px 14px;margin-bottom:16px;gap:10px;align-items:center;">'
            + '<span style="font-size:20px;">📦</span>'
            + '<div><div id="sgclabs-quote-product-name" style="font-weight:700;color:#1a1a2e;font-size:14px;"></div><div id="sgclabs-quote-product-sku" style="font-size:12px;color:#666;"></div></div>'
            + '</div>'
            + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">'
            + '<div class="sgcq-col"><label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">Your Name *</label><input type="text" id="sgcq_name" required placeholder="Full name" style="width:100%;padding:9px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:14px;box-sizing:border-box;"></div>'
            + '<div class="sgcq-col"><label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">Email Address *</label><input type="email" id="sgcq_email" required placeholder="you@company.com" style="width:100%;padding:9px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:14px;box-sizing:border-box;"></div>'
            + '<div class="sgcq-col"><label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">Phone / WhatsApp</label><input type="tel" id="sgcq_phone" placeholder="+1 555 000 0000" style="width:100%;padding:9px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:14px;box-sizing:border-box;"></div>'
            + '<div class="sgcq-col"><label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">Company Name</label><input type="text" id="sgcq_company" placeholder="Your company" style="width:100%;padding:9px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:14px;box-sizing:border-box;"></div>'
            + '<div class="sgcq-col"><label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">Country *</label>'
            + '<input type="hidden" id="sgcq_country" value="">'
            + '<div class="sgcq-custom-select" id="sgcq-country-wrap">'
            + '<div class="sgcq-cs-display" id="sgcq-country-display" onclick="sgcqToggleCountry(event)" tabindex="0" style="border:1.5px solid #e0e0e0;border-radius:8px;padding:9px 12px;font-size:14px;cursor:pointer;background:#fff;display:flex;justify-content:space-between;align-items:center;min-height:40px;">'
            + '<span id="sgcq-country-label">-- Select Country --</span>'
            + '<span style="font-size:12px;color:#888;">&#9660;</span>'
            + '</div></div></div>'
            + '<div class="sgcq-col"><label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">Product / SKU</label><input type="text" id="sgcq_product" placeholder="Product name or SKU" style="width:100%;padding:9px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:14px;box-sizing:border-box;"></div>'
            + '<div class="sgcq-col" style="grid-column:1/-1;"><label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">Your Message / Requirements *</label>'
            + '<textarea id="sgcq_message" required rows="4" placeholder="Quantity needed, specifications, destination port, preferred Incoterms..." style="width:100%;padding:9px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:14px;box-sizing:border-box;resize:vertical;"></textarea></div>'
            + '</div>'
            + '<div style="margin-top:18px;display:flex;gap:10px;align-items:center;">'
            + '<button id="sgcq-submit-btn" onclick="sgclabsSubmitQuote()" style="flex:1;padding:13px 28px;background:<?php echo esc_js(get_option("sgclabs_quote_btn_color","#1565c0")); ?>;color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;">📨 Send Quote Request</button>'
            + '</div>'
            + '<div id="sgclabs-quote-msg" style="display:none;margin-top:12px;padding:12px;border-radius:8px;font-size:14px;"></div>'
            + '</div></div></div>';

        var wrap = document.createElement('div');
        wrap.innerHTML = html;
        document.body.appendChild(wrap.firstChild);
    }

    function sgclabsOpenQuoteModal(btn) {
        // ── Ensure modal overlay exists in DOM ─────────────────────────
        // Homepage: button can be from shortcode/widget rendered BEFORE
        // footer, so overlay may not exist yet. Inject it on-demand.
        var overlay = document.getElementById('sgclabs-quote-overlay');
        if (!overlay) {
            // Modal HTML not in DOM (e.g. homepage / page builder) — inject now
            sgclabsInjectModal();
            overlay = document.getElementById('sgclabs-quote-overlay');
            if (!overlay) return; // Still null = fatal, bail
        }
        var productName = btn ? btn.getAttribute('data-product') : '';
        var productSku  = btn ? btn.getAttribute('data-sku') : '';
        var productUrl  = btn ? btn.getAttribute('data-url') : '';
        var mode        = btn ? (btn.getAttribute('data-mode') || 'builtin') : 'builtin';
        var cf7id       = btn ? parseInt(btn.getAttribute('data-cf7id') || '0') : 0;

        sgcqCurrentProduct = { name: productName, sku: productSku, url: productUrl, mode: mode, cf7id: cf7id };

        // Update product badge
        var badge = document.getElementById('sgclabs-quote-product-badge');
        if (productName) {
            document.getElementById('sgclabs-quote-product-name').textContent = '📦 ' + productName;
            document.getElementById('sgclabs-quote-product-sku').textContent  = productSku ? '(SKU: ' + productSku + ')' : '';
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }

        // Auto-fill product field
        var productField = document.getElementById('sgcq_product');
        if (productField) {
            productField.value = productName || '';
            productField.readOnly = !!productName;
            if (!productName) productField.readOnly = false;
        }

        // Show correct form
        var cf7wrap   = document.getElementById('sgclabs-quote-cf7-wrap');
        var builtinWrap = document.getElementById('sgclabs-quote-builtin-form');
        if (mode === 'cf7' && cf7wrap) {
            cf7wrap.style.display = 'block';
            if (builtinWrap) builtinWrap.style.display = 'none';

            // Auto-fill CF7 hidden product field if it exists
            setTimeout(function() {
                var cf7prod = cf7wrap.querySelector('input[name="product-name"], input[name="your-product"]');
                if (cf7prod) { cf7prod.value = productName; }
            }, 100);
        } else {
            if (cf7wrap) cf7wrap.style.display = 'none';
            if (builtinWrap) builtinWrap.style.display = 'block';
        }

        // Reset form state
        var msg = document.getElementById('sgclabs-quote-msg');
        if (msg) { msg.style.display = 'none'; msg.className = ''; }
        var submitBtn = document.getElementById('sgcq-submit-btn');
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = '📨 Send Quote Request'; }

        // Remove success screen if exists
        var ss = document.getElementById('sgclabs-quote-success-screen');
        if (ss) ss.remove();

        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        // Focus first input
        setTimeout(function() {
            var first = document.getElementById('sgcq_name');
            if (first && !first.readOnly) first.focus();
        }, 300);
    }

    // ── Custom Country Dropdown (position:fixed — overflow proof) ────────
    var sgcqCountries = <?php
        $cl = ['India','USA','UK','Germany','UAE','Saudi Arabia','Australia','Canada',
               'France','Italy','Spain','Netherlands','Belgium','Switzerland','Sweden',
               'Denmark','Norway','Finland','Poland','Czech Republic','Austria','Ireland',
               'Portugal','Greece','Romania','Hungary','Croatia',
               'Qatar','Kuwait','Oman','Bahrain','Jordan','Lebanon','Iraq','Iran',
               'Egypt','South Africa','Nigeria','Kenya','Ghana','Tanzania','Morocco',
               'Tunisia','Ethiopia','Uganda','China','Japan','South Korea','Singapore',
               'Malaysia','Indonesia','Thailand','Vietnam','Philippines','Taiwan',
               'Hong Kong','Bangladesh','Pakistan','Sri Lanka','Nepal','Myanmar',
               'Brazil','Mexico','Argentina','Colombia','Chile','Peru','Ecuador',
               'Venezuela','Costa Rica','Dominican Republic','Russia','Kazakhstan',
               'Uzbekistan','Ukraine','New Zealand','Fiji','Turkey','Israel','Other'];
        echo wp_json_encode($cl);
    ?>;

    // Create the panel once, attach to body
    function sgcqInitCountryPanel() {
        if (document.getElementById('sgcq-country-panel')) return;
        var panel = document.createElement('div');
        panel.id = 'sgcq-country-panel';
        panel.innerHTML =
            '<input type="text" id="sgcq-country-search" placeholder="🔍 Search country..." autocomplete="off" oninput="sgcqFilterCountries(this.value)">'
            + '<div id="sgcq-country-opts"></div>';
        document.body.appendChild(panel);
        // Populate options
        sgcqFilterCountries('');
        // Close on outside click
        document.addEventListener('click', function(e) {
            var panel = document.getElementById('sgcq-country-panel');
            var wrap  = document.getElementById('sgcq-country-wrap');
            if (panel && wrap && !panel.contains(e.target) && !wrap.contains(e.target)) {
                sgcqCloseCountry();
            }
        });
    }

    window.sgcqFilterCountries = function(q) {
        var opts = document.getElementById('sgcq-country-opts');
        if (!opts) return;
        var filtered = q ? sgcqCountries.filter(function(c){ return c.toLowerCase().indexOf(q.toLowerCase()) > -1; }) : sgcqCountries;
        if (!filtered.length) {
            opts.innerHTML = '<div class="sgcq-cs-noresult">No results for "'+q+'"</div>';
            return;
        }
        opts.innerHTML = filtered.map(function(c) {
            return '<div class="sgcq-cs-opt" onclick="sgcqSelectCountry(\''+c.replace(/'/g,"\\'")+'\')" onmouseover="this.classList.add(\'highlighted\')" onmouseout="this.classList.remove(\'highlighted\')">'+c+'</div>';
        }).join('');
    };

    window.sgcqSelectCountry = function(val) {
        document.getElementById('sgcq_country').value = val;
        document.getElementById('sgcq-country-label').textContent = val;
        document.getElementById('sgcq-country-display').classList.remove('open');
        var panel = document.getElementById('sgcq-country-panel');
        if (panel) { panel.classList.remove('open'); panel.style.display = 'none'; }
        // Reset search
        var search = document.getElementById('sgcq-country-search');
        if (search) search.value = '';
        sgcqFilterCountries('');
    };

    window.sgcqToggleCountry = function(e) {
        e.stopPropagation();
        sgcqInitCountryPanel();
        var display = document.getElementById('sgcq-country-display');
        var panel   = document.getElementById('sgcq-country-panel');
        if (!panel) return;

        var isOpen = panel.classList.contains('open');
        if (isOpen) {
            sgcqCloseCountry();
            return;
        }

        // Position panel using getBoundingClientRect — works with fixed positioning
        var rect = document.getElementById('sgcq-country-wrap').getBoundingClientRect();
        panel.style.left   = rect.left + 'px';
        panel.style.top    = (rect.bottom) + 'px';
        panel.style.width  = rect.width + 'px';
        panel.style.display = 'block';
        panel.classList.add('open');
        display.classList.add('open');

        // Adjust if panel goes below viewport
        setTimeout(function() {
            var pr = panel.getBoundingClientRect();
            if (pr.bottom > window.innerHeight) {
                panel.style.top = (rect.top - pr.height) + 'px';
                panel.style.borderRadius = '10px 10px 0 0';
            }
        }, 0);

        // Focus search
        var search = document.getElementById('sgcq-country-search');
        if (search) setTimeout(function(){ search.focus(); }, 50);
    };

    window.sgcqCloseCountry = function() {
        var display = document.getElementById('sgcq-country-display');
        var panel   = document.getElementById('sgcq-country-panel');
        if (display) display.classList.remove('open');
        if (panel)   { panel.classList.remove('open'); panel.style.display = 'none'; }
    };

    // Reposition on scroll/resize (modal can scroll)
    document.addEventListener('scroll', function() {
        var panel = document.getElementById('sgcq-country-panel');
        if (panel && panel.classList.contains('open')) {
            var wrap = document.getElementById('sgcq-country-wrap');
            if (wrap) {
                var rect = wrap.getBoundingClientRect();
                panel.style.left = rect.left + 'px';
                panel.style.top  = rect.bottom + 'px';
                panel.style.width = rect.width + 'px';
            }
        }
    }, true);

    function sgclabsCloseQuoteModal(event, force) {
        sgcqCloseCountry(); // Close country dropdown if open
        if (!force && event && event.target !== document.getElementById('sgclabs-quote-overlay')) return;
        document.getElementById('sgclabs-quote-overlay').style.display = 'none';
        document.body.style.overflow = '';
    }

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') sgclabsCloseQuoteModal(null, true);
    });

    function sgcqFileChange(input) {
        var label = document.getElementById('sgcq-file-label');
        var nameEl = document.getElementById('sgcq-file-name');
        if (input.files && input.files[0]) {
            var f = input.files[0];
            if (f.size > 5 * 1024 * 1024) {
                alert('File size must be under 5MB'); input.value = ''; return;
            }
            nameEl.textContent = '✅ ' + f.name + ' (' + (f.size/1024).toFixed(1) + ' KB)';
            nameEl.style.display = 'block';
            label.style.display = 'none';
        }
    }

    function sgclabsSubmitQuote() {
        var name    = (document.getElementById('sgcq_name').value || '').trim();
        var email   = (document.getElementById('sgcq_email').value || '').trim();
        var country = (document.getElementById('sgcq_country').value || '').trim();
        var product = (document.getElementById('sgcq_product').value || '').trim();
        var phone   = (document.getElementById('sgcq_phone').value || '').trim();
        var qty     = (document.getElementById('sgcq_qty').value || '').trim();
        var message = (document.getElementById('sgcq_message').value || '').trim();
        var typeEl  = document.querySelector('input[name="sgcq_type"]:checked');
        var qtype   = typeEl ? typeEl.value : 'Quote Request';
        var buyerEl = document.querySelector('input[name="sgcq_buyer_type"]:checked');
        var buyer_type = buyerEl ? buyerEl.value : '';

        var msgEl = document.getElementById('sgclabs-quote-msg');

        if (!name || !email || !country || !product) {
            msgEl.style.display = 'block';
            msgEl.className = 'error';
            msgEl.innerHTML = '❌ Please fill Name, Email, Country and Product fields.';
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            msgEl.style.display = 'block';
            msgEl.className = 'error';
            msgEl.innerHTML = '❌ Please enter a valid email address.';
            return;
        }

        var btn = document.getElementById('sgcq-submit-btn');
        var modal = document.getElementById('sgclabs-quote-modal');
        var loadingOverlay = document.getElementById('sgcq-loading-overlay');
        var progressBar = document.getElementById('sgcq-progress-bar');

        // Show loading overlay
        btn.disabled = true;
        btn.innerHTML = '⏳ Sending...';
        msgEl.style.display = 'none';
        loadingOverlay.classList.add('active');
        modal.classList.add('submitting');

        // Animate progress bar — fake progress to show activity
        var progress = 0;
        var progressInterval = setInterval(function() {
            if (progress < 85) {
                progress += Math.random() * 12;
                if (progress > 85) progress = 85;
                progressBar.style.width = progress + '%';
            }
        }, 300);

        // Prevent page/tab close while submitting
        var beforeUnloadHandler = function(e) {
            e.preventDefault();
            e.returnValue = 'Form is being submitted. Are you sure you want to leave?';
            return e.returnValue;
        };
        window.addEventListener('beforeunload', beforeUnloadHandler);

        // File handling
        var formData = new FormData();
        formData.append('action', 'sgclabs_submit_quote');
        formData.append('nonce', '<?php echo esc_js( wp_create_nonce( 'sgclabs_quote' ) ); ?>');
        formData.append('name', name);
        formData.append('email', email);
        formData.append('phone', phone);
        formData.append('country', country);
        formData.append('product', product);
        formData.append('qty', qty);
        formData.append('message', message);
        formData.append('qtype', qtype);
        formData.append('buyer_type', buyer_type);
        formData.append('product_url', sgcqCurrentProduct.url || window.location.href);

        var fileInput = document.getElementById('sgcq_file');
        if (fileInput && fileInput.files[0]) {
            formData.append('attachment', fileInput.files[0]);
        }

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            clearInterval(progressInterval);
            window.removeEventListener('beforeunload', beforeUnloadHandler);

            if (res.success) {
                // Complete the progress bar smoothly
                progressBar.style.width = '100%';
                setTimeout(function() {
                    // Hide loading overlay
                    loadingOverlay.classList.remove('active');
                    modal.classList.remove('submitting');
                    // Show success screen
                    var formArea = document.getElementById('sgclabs-quote-builtin-form');
                    formArea.innerHTML = '<div id="sgclabs-quote-success-screen">' +
                        '<div class="sgcq-success-icon">✅</div>' +
                        '<h3>Quote Request Sent!</h3>' +
                        '<p><?php echo esc_js(get_option('sgclabs_quote_success_msg', 'SGC LABS® team will get back to you within 24 hours.')); ?></p>' +
                        '<p style="margin-top:12px;"><strong>Reference:</strong> #' + res.data.ref + '</p>' +
                        '<button onclick="sgclabsCloseQuoteModal(null,true)" style="margin-top:20px;padding:10px 24px;background:#1a1a2e;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:14px;">Close</button>' +
                        '</div>';
                    document.getElementById('sgclabs-quote-product-badge').style.display = 'none';
                }, 500);
            } else {
                // Hide loading, show error
                loadingOverlay.classList.remove('active');
                modal.classList.remove('submitting');
                progressBar.style.width = '0%';
                btn.disabled = false;
                btn.innerHTML = '📨 Send Quote Request';
                msgEl.style.display = 'block';
                msgEl.className = 'error';
                msgEl.innerHTML = '❌ ' + (res.data || 'Error. Please email: sales@labsglass.com');
            }
        })
        .catch(function() {
            clearInterval(progressInterval);
            window.removeEventListener('beforeunload', beforeUnloadHandler);
            loadingOverlay.classList.remove('active');
            modal.classList.remove('submitting');
            progressBar.style.width = '0%';
            btn.disabled = false;
            btn.innerHTML = '📨 Send Quote Request';
            msgEl.style.display = 'block';
            msgEl.className = 'error';
            msgEl.innerHTML = '❌ Network error. Please try again or email: <a href="mailto:sales@labsglass.com">sales@labsglass.com</a>';
        });
    }
    </script>
    <?php
}

// ============================================================
// AJAX: HANDLE QUOTE SUBMISSION
// ============================================================
add_action('wp_ajax_sgclabs_submit_quote',        'sgclabs_handle_quote_submission');
add_action('wp_ajax_nopriv_sgclabs_submit_quote', 'sgclabs_handle_quote_submission');
function sgclabs_handle_quote_submission() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sgclabs_quote')) {
        wp_send_json_error('Invalid nonce');
    }

    // Rate limiting
    $ip        = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $rate_key  = 'sgclabs_quote_rate_' . md5($ip);
    $rate_cnt  = (int) get_transient($rate_key);
    if ($rate_cnt >= 5) {
        wp_send_json_error('Too many requests. Please wait a few minutes.');
    }
    set_transient($rate_key, $rate_cnt + 1, 5 * MINUTE_IN_SECONDS);

    // Sanitize inputs
    $name        = sanitize_text_field($_POST['name'] ?? '');
    $email       = sanitize_email($_POST['email'] ?? '');
    $phone       = sanitize_text_field($_POST['phone'] ?? '');
    $country     = sanitize_text_field($_POST['country'] ?? '');
    $product     = sanitize_text_field($_POST['product'] ?? '');
    $qty         = sanitize_text_field($_POST['qty'] ?? '');
    $message     = sanitize_textarea_field($_POST['message'] ?? '');
    $qtype       = sanitize_text_field($_POST['qtype'] ?? 'Quote Request');
    $buyer_type  = sanitize_text_field($_POST['buyer_type'] ?? '');
    $product_url = esc_url_raw($_POST['product_url'] ?? '');

    if (!$name || !$email || !$product) {
        wp_send_json_error('Required fields missing');
    }

    global $wpdb;

    // Generate reference number
    $ref = 'SGCQ-' . strtoupper(substr(md5(time() . $email), 0, 6));

    // Handle file attachment — SECURE: uses wp_handle_upload with real MIME check
    $attachment_path = '';
    if (!empty($_FILES['attachment']['name'])) {
        $allowed_types = ['image/jpeg','image/png','application/pdf','application/msword',
                          'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!function_exists('wp_handle_upload')) require_once(ABSPATH . 'wp-admin/includes/file.php');
        $upload = wp_handle_upload($_FILES['attachment'], [
            'test_form' => false,
            'mimes'     => ['jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf',
                            'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        ]);
        if (!isset($upload['error']) && isset($upload['url'])) {
            $real_type = isset($upload['type']) ? $upload['type'] : '';
            if (in_array($real_type, $allowed_types) && $_FILES['attachment']['size'] <= 5 * 1024 * 1024) {
                $attachment_path = $upload['url'];
            } else {
                @unlink($upload['file']); // remove if type mismatch
            }
        }
    }

    // Canonical sgclabs_inquiries table — single source of truth (v6.9.9+)
    // sgclabs-manager.php activation does NOT create this table — only here.
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    $charset = $wpdb->get_charset_collate();
    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sgclabs_inquiries (
        id          mediumint(9)  NOT NULL AUTO_INCREMENT,
        inq_number  varchar(30)   DEFAULT NULL COMMENT 'Unified: INQ-YYYY-NNNN from wp_sgc_doc_sequences',
        trade_type  varchar(10)   NOT NULL DEFAULT 'export' COMMENT 'export|domestic',
        name        varchar(100),
        email       varchar(100),
        phone       varchar(20),
        company     varchar(150),
        country     varchar(100),
        incoterm    varchar(20),
        port        varchar(100),
        product     varchar(200),
        qty         varchar(100),
        message     text,
        status      varchar(20)   DEFAULT 'new',
        pi_number   varchar(30)   DEFAULT NULL COMMENT 'Filled by Invoice Pro after PI created',
        source_type varchar(30)   DEFAULT 'quote' COMMENT 'quote|contact|b2b|manual|wc_order',
        created_at  datetime      DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY email_idx (email(80)),
        KEY status_idx (status),
        KEY inq_number_idx (inq_number)
    ) $charset;" );

    // Ensure any new columns exist on older installs (safe on MySQL 5.7+)
    $columns = $wpdb->get_col( "DESCRIBE {$wpdb->prefix}sgclabs_inquiries" ); // phpcs:ignore
    $add_cols = [
        'inq_number'  => "varchar(30) DEFAULT NULL",
        'trade_type'  => "varchar(10) NOT NULL DEFAULT 'export'",
        'company'     => "varchar(150)",
        'incoterm'    => "varchar(20)",
        'port'        => "varchar(100)",
        'pi_number'   => "varchar(30) DEFAULT NULL",
        'source_type' => "varchar(30) DEFAULT 'quote'",
        'buyer_type'  => "varchar(60) NOT NULL DEFAULT ''",
    ];
    foreach ( $add_cols as $col => $def ) {
        if ( ! in_array( $col, $columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}sgclabs_inquiries ADD COLUMN {$col} {$def}" ); // phpcs:ignore
        }
    }

    // Generate unified inquiry number from sgc_doc_sequences (atomic, no duplicates)
    $inq_number = sgc_generate_doc_number( 'INQ' );

    $result = $wpdb->insert(
        $wpdb->prefix . 'sgclabs_inquiries',
        [
            'inq_number'  => $inq_number,
            'trade_type'  => ( ! empty( $_POST['country'] ) && in_array( strtolower( sanitize_text_field( $_POST['country'] ?? '' ) ), [ 'india', 'in' ], true ) ) ? 'domestic' : 'export',
            'name'        => $name,
            'email'       => $email,
            'phone'       => $phone,
            'company'     => sanitize_text_field( $_POST['company'] ?? '' ),
            'country'     => $country,
            'incoterm'    => sanitize_text_field( $_POST['incoterm'] ?? 'FOB' ),
            'port'        => sanitize_text_field( $_POST['port'] ?? '' ),
            'product'     => "[{$qtype}] {$product}",
            'qty'         => $qty,
            'message'     => $message . ( $attachment_path ? "\n\nAttachment: {$attachment_path}" : '' ) . "\nRef: {$ref}\nProduct URL: {$product_url}",
            'status'      => 'new',
            'source_type' => 'quote',
            'buyer_type'  => $buyer_type,
        ],
        [ '%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' ]
    );

    if ($result === false) {
        wp_send_json_error('DB error: ' . $wpdb->last_error);
    }
    $quote_db_id = (int) $wpdb->insert_id;

    // v7.5.1: fire the unified hook (Invoice Pro, Smart CRM, follow-up automation listen here).
    // Previously only the inquiry/B2B/unified forms fired it, so Get-a-Quote leads never
    // reached Invoice Pro except through an unauthenticated priority-1 intercept (now removed).
    do_action( 'sgclabs_inquiry_received', [
        'id'            => 'quote_' . $quote_db_id,
        'db_id'         => $quote_db_id,
        'source'        => 'quote',
        'status'        => 'new',
        'name'          => $name,
        'email'         => $email,
        'phone'         => $phone,
        'country'       => $country,
        'company'       => sanitize_text_field( $_POST['company'] ?? '' ),
        'subject'       => "{$qtype}: {$product}",
        'message'       => $message,
        'product_title' => $product,
        'product_url'   => $product_url,
        'page_url'      => $product_url,
        'meta'          => [
            'incoterm'   => sanitize_text_field( $_POST['incoterm'] ?? 'FOB' ),
            'port'       => sanitize_text_field( $_POST['port'] ?? '' ),
            'qty'        => $qty,
            'qtype'      => $qtype,
            'buyer_type' => $buyer_type,
            'ref'        => $ref,
        ],
        'created_at'    => time(),
    ] );

    // Email notification
    $notify_email  = get_option('sgclabs_quote_notify_email', get_option('admin_email'));
    $product_safe  = preg_replace('/[\r\n\t]/', ' ', $product);
    $subject       = "New {$qtype}: {$product_safe} — SGC LABS® [{$ref}]";

    $body  = "New {$qtype} received!\n";
    $body .= "Reference: {$ref}\n\n";
    $body .= "Name    : {$name}\n";
    $body .= "Email   : {$email}\n";
    $body .= "Phone   : {$phone}\n";
    $body .= "Country : {$country}\n";
    $body .= "Product : {$product}\n";
    $body .= "Qty     : {$qty}\n";
    $body .= "Type    : {$qtype}\n\n";
    $body .= "Message :\n{$message}\n\n";
    if ($attachment_path) $body .= "Attachment: {$attachment_path}\n\n";
    $body .= "Product URL: {$product_url}\n";
    $body .= "\n-- SGC LABS® Site Manager Pro";

    // ── Shared safe vars ──
    $name_safe      = esc_html($name);
    $email_safe     = esc_html($email);
    $phone_safe     = esc_html($phone ?: 'Not provided');
    $product_safe_h = esc_html($product);
    $ref_safe       = esc_html($ref);
    $qtype_safe     = esc_html($qtype);
    $qty_safe       = esc_html($qty ?: 'Not specified');
    $country_safe   = esc_html($country ?: 'Not specified');
    $message_safe   = nl2br(esc_html($message));
    $date_safe      = esc_html(current_time('d M Y, h:i A'));

    // ── Admin notification — HTML with full details, Reply-To = client ──
    $headers_admin = [
        'Content-Type: text/html; charset=UTF-8',
        'From: SGC Labs Team <info@labsglass.com>',
        'Reply-To: ' . $name_safe . ' <' . sanitize_email($email) . '>',
    ];

    $admin_body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:24px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:10px;overflow:hidden;">
  <tr><td style="background:#1a1a2e;padding:20px 28px;"><h2 style="color:#27ae60;margin:0;font-size:18px;">SGC LABS® — New ' . $qtype_safe . '</h2><p style="color:rgba(255,255,255,.65);margin:4px 0 0;font-size:12px;">Received: ' . $date_safe . ' &nbsp;|&nbsp; Ref: <strong style="color:#fff;">' . $ref_safe . '</strong></p></td></tr>
  <tr><td style="padding:24px 28px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;border-collapse:collapse;">
      <tr style="background:#f8fafc;"><td style="padding:10px 12px;color:#64748b;width:35%;border:1px solid #e2e8f0;">👤 Name</td><td style="padding:10px 12px;color:#1a1a2e;font-weight:600;border:1px solid #e2e8f0;">' . $name_safe . '</td></tr>
      <tr><td style="padding:10px 12px;color:#64748b;border:1px solid #e2e8f0;">✉️ Email</td><td style="padding:10px 12px;border:1px solid #e2e8f0;"><a href="mailto:' . $email_safe . '" style="color:#1a1a2e;">' . $email_safe . '</a></td></tr>
      <tr style="background:#f8fafc;"><td style="padding:10px 12px;color:#64748b;border:1px solid #e2e8f0;">📞 Phone</td><td style="padding:10px 12px;border:1px solid #e2e8f0;"><a href="tel:' . esc_attr($phone) . '" style="color:#1a1a2e;">' . $phone_safe . '</a></td></tr>
      <tr><td style="padding:10px 12px;color:#64748b;border:1px solid #e2e8f0;">🌍 Country</td><td style="padding:10px 12px;border:1px solid #e2e8f0;">' . $country_safe . '</td></tr>
      <tr style="background:#f8fafc;"><td style="padding:10px 12px;color:#64748b;border:1px solid #e2e8f0;">🧪 Product</td><td style="padding:10px 12px;color:#1a1a2e;font-weight:600;border:1px solid #e2e8f0;">' . $product_safe_h . '</td></tr>
      <tr><td style="padding:10px 12px;color:#64748b;border:1px solid #e2e8f0;">📦 Quantity</td><td style="padding:10px 12px;border:1px solid #e2e8f0;">' . $qty_safe . '</td></tr>
      <tr style="background:#f8fafc;"><td style="padding:10px 12px;color:#64748b;border:1px solid #e2e8f0;">🏷️ Type</td><td style="padding:10px 12px;border:1px solid #e2e8f0;">' . $qtype_safe . '</td></tr>
    </table>
    ' . ($message ? '<p style="font-size:13px;color:#64748b;margin:20px 0 6px;font-weight:600;">📝 Message:</p><div style="background:#f8fafc;border-left:3px solid #27ae60;padding:14px 16px;font-size:14px;color:#374151;line-height:1.7;">' . $message_safe . '</div>' : '') . '
    ' . ($attachment_path ? '<p style="font-size:13px;margin:16px 0 0;"><strong>📎 Attachment:</strong> <a href="' . esc_url($attachment_path) . '" style="color:#27ae60;">' . esc_html(basename($attachment_path)) . '</a></p>' : '') . '
    <p style="margin:20px 0 0;"><a href="' . esc_url($product_url) . '" style="font-size:13px;color:#27ae60;">View Product →</a></p>
    <div style="margin:20px 0 0;"><a href="mailto:' . $email_safe . '" style="display:inline-block;padding:10px 24px;background:#27ae60;color:#fff;text-decoration:none;border-radius:6px;font-size:13px;font-weight:700;">Reply to ' . $name_safe . ' →</a></div>
  </td></tr>
  <tr><td style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:12px 28px;text-align:center;font-size:11px;color:#94a3b8;">SGC LABS® Site Manager Pro &nbsp;|&nbsp; <a href="' . admin_url('admin.php?page=sgclabs-quotes-inquiries&tab=inquiries') . '" style="color:#27ae60;">View All Inquiries →</a></td></tr>
</table></td></tr></table></body></html>';

    wp_mail($notify_email, $subject, $admin_body, $headers_admin);

    // ── Auto-reply to customer — HTML, professional branding ──
    $headers_customer = [
        'Content-Type: text/html; charset=UTF-8',
        'From: SGC Labs Team <info@labsglass.com>',
        'Reply-To: Sales Team <sales@labsglass.com>',
    ];

    $customer_subject = "We received your inquiry — SGC LABS® [{$ref}]";

    $customer_body = '<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Inquiry Received — SGC LABS®</title></head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:32px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;">

  <!-- Header -->
  <tr>
    <td style="background:linear-gradient(135deg,#1a1a2e 0%,#16213e 100%);padding:32px 36px;text-align:center;">
      <p style="margin:0 0 6px;font-size:26px;font-weight:900;color:#27ae60;letter-spacing:2px;">SGC LABS®</p>
      <p style="margin:0;font-size:12px;color:rgba(255,255,255,.6);letter-spacing:.5px;">Scientific Glassware Manufacturer · Since 2003 · Ambala, India</p>
    </td>
  </tr>
  <tr><td style="background:linear-gradient(90deg,#27ae60,#2ecc71);height:4px;"></td></tr>

  <!-- Greeting -->
  <tr>
    <td style="padding:36px 36px 0;">
      <p style="font-size:22px;font-weight:700;color:#1a1a2e;margin:0 0 12px;">Thank you, ' . $name_safe . '! 🙏</p>
      <p style="font-size:15px;color:#475569;line-height:1.8;margin:0 0 28px;">
        We have successfully received your <strong style="color:#1a1a2e;">' . $qtype_safe . '</strong> and our sales team will review it shortly.<br>
        You will receive a detailed response within <strong style="color:#27ae60;font-size:16px;">24–48 hours</strong>.
      </p>

      <!-- Reference badge -->
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0fdf4;border:1.5px solid #86efac;border-radius:10px;margin-bottom:28px;">
        <tr>
          <td style="padding:20px 24px;">
            <p style="margin:0 0 4px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.1em;font-weight:700;">Your Reference Number</p>
            <p style="margin:0;font-size:26px;font-weight:900;color:#166534;letter-spacing:2px;font-family:monospace;">' . $ref_safe . '</p>
            <p style="margin:8px 0 0;font-size:12px;color:#64748b;">📌 Please quote this number in any follow-up emails or calls.</p>
          </td>
        </tr>
      </table>

      <!-- Inquiry summary -->
      <p style="font-size:12px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;margin:0 0 10px;padding-bottom:8px;border-bottom:1px solid #f1f5f9;">Inquiry Summary</p>
      <table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;border-collapse:collapse;margin-bottom:28px;">
        <tr>
          <td style="padding:10px 12px;color:#64748b;background:#f8fafc;border:1px solid #e9ecef;width:38%;font-weight:600;">🧪 Product</td>
          <td style="padding:10px 12px;color:#1a1a2e;font-weight:700;background:#f8fafc;border:1px solid #e9ecef;">' . $product_safe_h . '</td>
        </tr>
        <tr>
          <td style="padding:10px 12px;color:#64748b;border:1px solid #e9ecef;font-weight:600;">📦 Quantity</td>
          <td style="padding:10px 12px;color:#374151;border:1px solid #e9ecef;">' . $qty_safe . '</td>
        </tr>
        <tr>
          <td style="padding:10px 12px;color:#64748b;background:#f8fafc;border:1px solid #e9ecef;font-weight:600;">🏷️ Type</td>
          <td style="padding:10px 12px;color:#374151;background:#f8fafc;border:1px solid #e9ecef;">' . $qtype_safe . '</td>
        </tr>
        <tr>
          <td style="padding:10px 12px;color:#64748b;border:1px solid #e9ecef;font-weight:600;">🌍 Country</td>
          <td style="padding:10px 12px;color:#374151;border:1px solid #e9ecef;">' . $country_safe . '</td>
        </tr>
        <tr>
          <td style="padding:10px 12px;color:#64748b;background:#f8fafc;border:1px solid #e9ecef;font-weight:600;">📅 Submitted</td>
          <td style="padding:10px 12px;color:#374151;background:#f8fafc;border:1px solid #e9ecef;">' . $date_safe . '</td>
        </tr>
      </table>

      <!-- What happens next -->
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:10px;margin-bottom:28px;">
        <tr>
          <td style="padding:20px 24px;">
            <p style="margin:0 0 14px;font-size:13px;font-weight:800;color:#1e40af;text-transform:uppercase;letter-spacing:.05em;">What happens next?</p>
            <table cellpadding="0" cellspacing="0">
              <tr><td style="padding:5px 0;font-size:14px;color:#1e3a8a;">✅ &nbsp; Inquiry logged &amp; assigned to our sales team</td></tr>
              <tr><td style="padding:5px 0;font-size:14px;color:#1e3a8a;">🔍 &nbsp; Team reviews your product requirements &amp; quantity</td></tr>
              <tr><td style="padding:5px 0;font-size:14px;color:#1e3a8a;">💰 &nbsp; Pricing, availability &amp; lead time confirmed</td></tr>
              <tr><td style="padding:5px 0;font-size:14px;color:#1e3a8a;">📧 &nbsp; Detailed quote sent to <strong>' . $email_safe . '</strong> within <strong>24–48 hrs</strong></td></tr>
            </table>
          </td>
        </tr>
      </table>

      <!-- Contact CTA -->
      <p style="font-size:14px;color:#64748b;margin:0 0 14px;"><strong style="color:#1a1a2e;">Need an urgent response?</strong> Reach us directly:</p>
      <table cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:8px;">
        <tr>
          <td><a href="tel:+919996416935" style="display:inline-block;padding:10px 18px;background:#1a1a2e;color:#fff;text-decoration:none;border-radius:6px;font-size:13px;font-weight:700;">📞 Call Us</a></td>
          <td><a href="https://wa.me/919996416935?text=Hi%2C%20my%20inquiry%20ref%20is%20' . $ref_safe . '" style="display:inline-block;padding:10px 18px;background:#25d366;color:#fff;text-decoration:none;border-radius:6px;font-size:13px;font-weight:700;">💬 WhatsApp</a></td>
          <td><a href="mailto:sales@labsglass.com?subject=Re:%20' . rawurlencode($ref) . '" style="display:inline-block;padding:10px 18px;background:#27ae60;color:#fff;text-decoration:none;border-radius:6px;font-size:13px;font-weight:700;">✉️ Email Us</a></td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:24px 36px;text-align:center;margin-top:32px;">
      <p style="margin:0 0 4px;font-size:14px;color:#1a1a2e;font-weight:800;letter-spacing:.5px;">SGC LABS®</p>
      <p style="margin:0 0 4px;font-size:12px;color:#64748b;">Scientific Glassware Manufacturer · Ambala Cantt, Haryana 133001, India</p>
      <p style="margin:10px 0 0;font-size:12px;">
        <a href="https://labsglass.com" style="color:#27ae60;text-decoration:none;font-weight:700;">labsglass.com</a>
        &nbsp;·&nbsp;
        <a href="tel:+919996416935" style="color:#64748b;text-decoration:none;">+91 999 641 6935</a>
        &nbsp;·&nbsp;
        <a href="mailto:sales@labsglass.com" style="color:#64748b;text-decoration:none;">sales@labsglass.com</a>
      </p>
      <p style="margin:14px 0 0;font-size:11px;color:#cbd5e1;line-height:1.6;">
        This is an automated confirmation email. Please do not reply directly to this message.<br>
        To follow up on your inquiry, email <a href="mailto:sales@labsglass.com" style="color:#94a3b8;">sales@labsglass.com</a> with your reference number <strong>' . $ref_safe . '</strong>.
      </p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>';

    wp_mail($email, $customer_subject, $customer_body, $headers_customer);

    wp_send_json_success(['ref' => $ref]);
}

// ============================================================
// SHORTCODE: [sgclabs_quote_button]
// Usage: [sgclabs_quote_button product="Borosilicate Flask 500ml" text="Get a Quote"]
// ============================================================
add_shortcode('sgclabs_quote_button', 'sgclabs_quote_button_shortcode');
function sgclabs_quote_button_shortcode($atts) {
    $atts = shortcode_atts([
        'product' => '',
        'sku'     => '',
        'text'    => get_option('sgclabs_quote_btn_text', '💬 Get a Quote'),
        'color'   => get_option('sgclabs_quote_btn_color', '#1565c0'),
        'tcolor'  => get_option('sgclabs_quote_btn_text_color', '#ffffff'),
    ], $atts, 'sgclabs_quote_button');

    return '<button type="button" class="sgclabs-quote-btn"
        style="background:' . esc_attr($atts['color']) . ';color:' . esc_attr($atts['tcolor']) . ';"
        data-product="' . esc_attr($atts['product']) . '"
        data-sku="' . esc_attr($atts['sku']) . '"
        data-url="' . esc_url(get_permalink()) . '"
        data-mode="builtin"
        onclick="sgclabsOpenQuoteModal(this)">' . esc_html($atts['text']) . '</button>';
}

// ============================================================
// ADMIN PAGE
// ============================================================
function sgclabs_quote_page() {
    $saved = isset($_GET['saved']);

    $active       = get_option('sgclabs_quote_active', '1');
    $hide_cart    = get_option('sgclabs_quote_hide_cart', '0');
    $hide_price   = get_option('sgclabs_quote_hide_price', '0');
    $show_archive = get_option('sgclabs_quote_show_archive', '1');
    $floating     = get_option('sgclabs_quote_floating_btn', '0');
    $use_cf7      = get_option('sgclabs_quote_use_cf7', '0');
    $btn_text     = get_option('sgclabs_quote_btn_text', '💬 Get a Quote');
    $btn_color    = get_option('sgclabs_quote_btn_color', '#1565c0');
    $txt_color    = get_option('sgclabs_quote_btn_text_color', '#ffffff');
    $modal_title  = get_option('sgclabs_quote_modal_title', 'Request a Quote / Pre-Sale Query');
    $success_msg  = get_option('sgclabs_quote_success_msg', '✅ Your quote request has been sent! SGC LABS® team will get back to you within 24 hours.');
    $notify_email = get_option('sgclabs_quote_notify_email', get_option('admin_email'));
    $cf7_id       = get_option('sgclabs_quote_cf7_id', 0);
    $btn_pos      = get_option('sgclabs_quote_btn_position', 'after_cart');
    $exclude_cats = get_option('sgclabs_quote_exclude_cats', '');

    // Count quotes from DB
    global $wpdb;
    $total_quotes = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_inquiries WHERE product LIKE '[Quote%' OR product LIKE '[Pre-Sale%' OR product LIKE '[Custom%' OR product LIKE '[Bulk%'") ?? 0;
    $new_quotes   = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_inquiries WHERE status='new' AND (product LIKE '[Quote%' OR product LIKE '[Pre-Sale%' OR product LIKE '[Custom%' OR product LIKE '[Bulk%')") ?? 0;

    // CF7 forms list
    $cf7_forms = [];
    if (function_exists('wpcf7_contact_form')) {
        $cf7_forms = get_posts(['post_type' => 'wpcf7_contact_form', 'posts_per_page' => -1, 'post_status' => 'publish']);
    }
    ?>
    <div class="wrap sgclabs-wrap">
        <h1>💬 Get a Quote — WooCommerce</h1>
        <p style="color:#666;margin-top:-5px;">Product pages pe "Get a Quote" button add karo — CF7 ya built-in form ke saath</p>

        <?php if ($saved): ?>
            <div class="notice notice-success"><p>✅ Quote settings saved!</p></div>
        <?php endif; ?>

        <!-- Stats row -->
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin-bottom:24px;">
            <div class="sgclabs-card" style="border-top:4px solid #27ae60;text-align:left;padding:16px 20px;">
                <div style="font-size:28px;font-weight:700;color:#27ae60;"><?php echo intval($total_quotes); ?></div>
                <div style="font-size:13px;color:#666;">Total Quote Requests</div>
            </div>
            <div class="sgclabs-card" style="border-top:4px solid #e74c3c;text-align:left;padding:16px 20px;">
                <div style="font-size:28px;font-weight:700;color:#e74c3c;"><?php echo intval($new_quotes); ?></div>
                <div style="font-size:13px;color:#666;">New (Unread)</div>
            </div>
            <div class="sgclabs-card" style="border-top:4px solid #3498db;text-align:left;padding:16px 20px;">
                <div style="font-size:28px;font-weight:700;color:#3498db;"><?php echo $active === '1' ? '✅' : '❌'; ?></div>
                <div style="font-size:13px;color:#666;">Button Status</div>
            </div>
            <div class="sgclabs-card" style="border-top:4px solid #8e44ad;text-align:left;padding:16px 20px;">
                <div style="font-size:28px;font-weight:700;color:#8e44ad;"><?php echo function_exists('wpcf7_contact_form') ? '✅' : '❌'; ?></div>
                <div style="font-size:13px;color:#666;">CF7 Installed</div>
            </div>
        </div>

        <!-- Shortcodes reference -->
        <div class="sgclabs-section" style="background:#f0f8ff;border-left:4px solid #3498db;">
            <h3 style="margin-top:0;">📋 Shortcode — Kisi bhi page pe button add karo</h3>
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <div>
                    <code style="background:#1a1a2e;color:#00ff88;padding:8px 14px;border-radius:6px;font-size:13px;">[sgclabs_quote_button]</code>
                    <p style="font-size:12px;color:#666;margin:4px 0 0;">Default button — kisi bhi page pe</p>
                </div>
                <div>
                    <code style="background:#1a1a2e;color:#00ff88;padding:8px 14px;border-radius:6px;font-size:13px;">[sgclabs_quote_button product="Beaker 500ml" text="Request Price"]</code>
                    <p style="font-size:12px;color:#666;margin:4px 0 0;">Custom product + button text ke saath</p>
                </div>
            </div>
            <p style="font-size:12px;color:#555;margin-bottom:0;margin-top:10px;">
                💡 <strong>CF7 mein product auto-fill ke liye:</strong> CF7 form mein ek hidden field add karo — name: <code>product-name</code> — plugin automatically fill kar dega.
            </p>
        </div>

        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('sgclabs_quote_nonce'); ?>
            <input type="hidden" name="action" value="sgclabs_save_quote">

            <!-- SECTION 1: GENERAL -->
            <div class="sgclabs-section">
                <h2>⚙️ General Settings</h2>
                <table class="form-table">
                    <tr>
                        <th>Enable "Get a Quote"</th>
                        <td>
                            <label><input type="checkbox" name="sgclabs_quote_active" value="1" <?php checked($active,'1'); ?>>
                            Product pages pe Quote button dikhao</label>
                        </td>
                    </tr>
                    <tr>
                        <th>Button Position</th>
                        <td>
                            <select name="sgclabs_quote_btn_position" style="padding:6px 10px;border-radius:5px;">
                                <option value="after_cart"  <?php selected($btn_pos,'after_cart'); ?>>After "Add to Cart" button</option>
                                <option value="before_cart" <?php selected($btn_pos,'before_cart'); ?>>Before "Add to Cart" button</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Show on Shop/Archive</th>
                        <td>
                            <label><input type="checkbox" name="sgclabs_quote_show_archive" value="1" <?php checked($show_archive,'1'); ?>>
                            Shop page aur category pages pe bhi dikhao</label>
                        </td>
                    </tr>
                    <tr>
                        <th>Floating Quote Button</th>
                        <td>
                            <label><input type="checkbox" name="sgclabs_quote_floating_btn" value="1" <?php checked($floating,'1'); ?>>
                            Har page ke corner mein floating button dikhao</label>
                        </td>
                    </tr>
                    <tr>
                        <th>Hide "Add to Cart"</th>
                        <td>
                            <label><input type="checkbox" name="sgclabs_quote_hide_cart" value="1" <?php checked($hide_cart,'1'); ?>>
                            "Add to Cart" button remove karo — sirf Quote button raho
                            <span style="color:#e74c3c;font-size:12px;">(Use karo agar B2B-only site hai)</span></label>
                        </td>
                    </tr>
                    <tr>
                        <th>Hide Price</th>
                        <td>
                            <label><input type="checkbox" name="sgclabs_quote_hide_price" value="1" <?php checked($hide_price,'1'); ?>>
                            Price hide karo — "Price on Request" dikhao
                            <span style="color:#e74c3c;font-size:12px;">(B2B/wholesale ke liye)</span></label>
                        </td>
                    </tr>
                    <tr>
                        <th>Exclude Categories</th>
                        <td>
                            <input type="text" name="sgclabs_quote_exclude_cats" value="<?php echo esc_attr($exclude_cats); ?>" class="regular-text" placeholder="e.g. accessories, spare-parts">
                            <p class="description">Comma separated category slugs — in categories pe button nahi aayega</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- SECTION 2: BUTTON APPEARANCE -->
            <div class="sgclabs-section">
                <h2>🎨 Button Appearance</h2>
                <table class="form-table">
                    <tr>
                        <th>Button Text</th>
                        <td>
                            <input type="text" name="sgclabs_quote_btn_text" value="<?php echo esc_attr($btn_text); ?>" class="regular-text" placeholder="💬 Get a Quote">
                            <p class="description">Examples: "💬 Get a Quote" | "📋 Request Price" | "📩 Send Inquiry"</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Button Background Color</th>
                        <td>
                            <input type="color" name="sgclabs_quote_btn_color" value="<?php echo esc_attr($btn_color); ?>">
                            <span style="margin-left:10px;font-size:13px;color:#666;">Button ka background</span>
                        </td>
                    </tr>
                    <tr>
                        <th>Button Text Color</th>
                        <td>
                            <input type="color" name="sgclabs_quote_btn_text_color" value="<?php echo esc_attr($txt_color); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th>Live Preview</th>
                        <td>
                            <button type="button"
                                style="background:<?php echo esc_attr($btn_color); ?>;color:<?php echo esc_attr($txt_color); ?>;border:none;padding:11px 22px;border-radius:7px;font-size:14px;font-weight:600;cursor:pointer;">
                                <?php echo esc_html($btn_text); ?>
                            </button>
                            <p class="description">Actual site pe aise dikhaai dega</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- SECTION 3: FORM TYPE (CF7 vs Built-in) -->
            <div class="sgclabs-section" style="border-left:4px solid #e67e22;">
                <h2>📝 Form Type — CF7 ya Built-in?</h2>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
                    <div style="background:<?php echo $use_cf7==='0'?'#f0fff4':'#f5f5f5'; ?>;border:2px solid <?php echo $use_cf7==='0'?'#27ae60':'#e0e0e0'; ?>;border-radius:10px;padding:16px;">
                        <div style="font-size:20px;margin-bottom:8px;">⚡</div>
                        <strong>Built-in Modal Form</strong>
                        <p style="font-size:12px;color:#666;margin:6px 0 0;">No extra plugin needed. Name, Email, Phone, Country, Product, Qty, Query Type, File Upload — sab included. Auto-reply email bhi.</p>
                        <?php if ($use_cf7==='0'): ?><span style="color:#27ae60;font-size:12px;font-weight:600;">✅ Currently Active</span><?php endif; ?>
                    </div>
                    <div style="background:<?php echo $use_cf7==='1'?'#f0fff4':'#f5f5f5'; ?>;border:2px solid <?php echo $use_cf7==='1'?'#27ae60':'#e0e0e0'; ?>;border-radius:10px;padding:16px;">
                        <div style="font-size:20px;margin-bottom:8px;">🔌</div>
                        <strong>Contact Form 7 (CF7)</strong>
                        <p style="font-size:12px;color:#666;margin:6px 0 0;">Apna existing CF7 form modal mein dikhao. Product name automatically fill ho jaata hai hidden field ke through.</p>
                        <?php if ($use_cf7==='1'): ?><span style="color:#27ae60;font-size:12px;font-weight:600;">✅ Currently Active</span><?php endif; ?>
                        <?php if (!function_exists('wpcf7_contact_form')): ?>
                            <br><span style="color:#e74c3c;font-size:12px;">⚠️ CF7 installed nahi hai — pehle install karo</span>
                        <?php endif; ?>
                    </div>
                </div>

                <table class="form-table">
                    <tr>
                        <th>Use Contact Form 7</th>
                        <td>
                            <label>
                                <input type="checkbox" name="sgclabs_quote_use_cf7" value="1" <?php checked($use_cf7,'1'); ?> <?php echo !function_exists('wpcf7_contact_form')?'disabled':''; ?>>
                                CF7 form use karo (built-in form ki jagah)
                            </label>
                        </td>
                    </tr>
                    <?php if (function_exists('wpcf7_contact_form')): ?>
                    <tr>
                        <th>Select CF7 Form</th>
                        <td>
                            <select name="sgclabs_quote_cf7_id" style="padding:6px 10px;border-radius:5px;min-width:300px;">
                                <option value="0">-- Select a CF7 Form --</option>
                                <?php foreach ($cf7_forms as $form): ?>
                                    <option value="<?php echo intval($form->ID); ?>" <?php selected(intval($cf7_id), $form->ID); ?>>
                                        <?php echo esc_html($form->post_title); ?> (ID: <?php echo intval($form->ID); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Jo CF7 form modal mein dikhana hai usse select karo</p>
                        </td>
                    </tr>
                    <tr>
                        <th>CF7 Product Auto-fill Setup</th>
                        <td>
                            <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:12px;font-size:13px;">
                                <strong>📋 CF7 form mein product name auto-fill ke liye:</strong><br>
                                CF7 form editor mein yeh hidden field add karo:<br>
                                <code style="background:#1a1a2e;color:#00ff88;padding:4px 10px;border-radius:4px;display:inline-block;margin-top:6px;">[hidden product-name "Product Name"]</code><br>
                                <span style="color:#666;font-size:12px;">Plugin automatically is field mein clicked product ka naam fill kar dega.</span>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <th>Install CF7</th>
                        <td>
                            <a href="<?php echo admin_url('plugin-install.php?s=contact+form+7&tab=search&type=term'); ?>" class="button" target="_blank">
                                📦 Install Contact Form 7 →
                            </a>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>

            <!-- SECTION 4: EMAIL & NOTIFICATIONS -->
            <div class="sgclabs-section">
                <h2>📧 Email & Notifications</h2>
                <table class="form-table">
                    <tr>
                        <th>Notification Email</th>
                        <td>
                            <input type="email" name="sgclabs_quote_notify_email" value="<?php echo esc_attr($notify_email); ?>" class="regular-text" placeholder="sales@labsglass.com">
                            <p class="description">Quote request aane par is email pe notification milega.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Modal Title</th>
                        <td>
                            <input type="text" name="sgclabs_quote_modal_title" value="<?php echo esc_attr($modal_title); ?>" class="large-text" placeholder="Request a Quote / Pre-Sale Query">
                        </td>
                    </tr>
                    <tr>
                        <th>Success Message</th>
                        <td>
                            <input type="text" name="sgclabs_quote_success_msg" value="<?php echo esc_attr($success_msg); ?>" class="large-text">
                            <p class="description">Form submit hone ke baad customer ko dikhayi jaane wali message</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div style="background:#f0fff4;border:2px solid #27ae60;border-radius:10px;padding:20px;margin:20px 0;">
                <?php submit_button('💾 Save Quote Settings', 'primary large', 'submit', false); ?>
                <span style="margin-left:15px;font-size:13px;color:#666;">Changes instantly live ho jaayenge</span>
            </div>
        </form>

        <!-- Recent Quotes Preview -->
        <?php
        $recent_quotes = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}sgclabs_inquiries
             WHERE product LIKE '[Quote%' OR product LIKE '[Pre-Sale%' OR product LIKE '[Custom%' OR product LIKE '[Bulk%'
             ORDER BY created_at DESC LIMIT 8"
        );
        if ($recent_quotes):
        ?>
        <div class="sgclabs-section">
            <h2>📋 Recent Quote Requests
                <a href="<?php echo admin_url('admin.php?page=sgclabs-quotes-inquiries&tab=inquiries'); ?>" style="font-size:13px;margin-left:10px;" class="button button-small">View All →</a>
            </h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="6%">Ref</th>
                        <th width="14%">Name</th>
                        <th width="16%">Email</th>
                        <th width="10%">Country</th>
                        <th width="22%">Product / Type</th>
                        <th width="8%">Qty</th>
                        <th width="10%">Date</th>
                        <th width="8%">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_quotes as $q): ?>
                        <tr <?php echo $q->status === 'new' ? 'style="font-weight:bold;background:#fff9e6;"' : ''; ?>>
                            <td style="font-size:11px;color:#888;"><?php
                                // Extract ref from message
                                preg_match('/SGCQ-[A-Z0-9]{6}/', $q->message, $m);
                                echo esc_html($m[0] ?? '#' . $q->id);
                            ?></td>
                            <td><?php echo esc_html($q->name); ?></td>
                            <td><a href="mailto:<?php echo esc_attr($q->email); ?>"><?php echo esc_html($q->email); ?></a></td>
                            <td><?php echo esc_html($q->country); ?></td>
                            <td><?php echo esc_html($q->product); ?></td>
                            <td><?php echo esc_html($q->qty ?: '—'); ?></td>
                            <td><?php echo date('d M Y', strtotime($q->created_at)); ?></td>
                            <td>
                                <?php if ($q->status === 'new'): ?>
                                    <span class="sgclabs-badge-red">New</span>
                                <?php else: ?>
                                    <span class="sgclabs-badge-green">Read</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>
    <?php
}
