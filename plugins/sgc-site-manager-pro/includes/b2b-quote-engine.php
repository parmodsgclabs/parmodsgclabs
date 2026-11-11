<?php
if (!defined('ABSPATH')) exit;

/**
 * ============================================================
 * SGC LABS® B2B QUOTE ENGINE — v6.6.3
 * ============================================================
 * Features:
 *  1. Product page "Request Quote" button
 *  2. Multi-product quote cart (localStorage based)
 *  3. Quote form: contact, country, Incoterms, MOQ
 *  4. Printable PDF quote with SGC branding
 *  5. Admin quotes dashboard with pipeline (New/Quoted/Won/Lost)
 *  6. Email notification: admin + buyer confirmation
 * ============================================================
 */

// ── DB table on activation (called from main plugin) ─────────────────────────
add_action('sgclabs_on_activate', 'sgclabs_quote_create_table');
function sgclabs_quote_create_table() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sgclabs_b2b_quotes (
        id          bigint(20)   NOT NULL AUTO_INCREMENT,
        ref_no      varchar(20)  NOT NULL DEFAULT '',
        name        varchar(150) NOT NULL DEFAULT '',
        email       varchar(150) NOT NULL DEFAULT '',
        phone       varchar(50)  NOT NULL DEFAULT '',
        company     varchar(200) NOT NULL DEFAULT '',
        country     varchar(100) NOT NULL DEFAULT '',
        incoterm    varchar(20)  NOT NULL DEFAULT 'EXW',
        port        varchar(200) NOT NULL DEFAULT '',
        products    longtext     NOT NULL,
        message     text         NOT NULL DEFAULT '',
        status      varchar(20)  NOT NULL DEFAULT 'new',
        total_usd   decimal(12,2)         DEFAULT NULL,
        admin_note  text                  DEFAULT NULL,
        created_at  datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  datetime              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY status_idx (status),
        KEY email_idx (email(100))
    ) $charset";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
// Also run at init so table exists even if activation hook missed
add_action('init', function() {
    if (get_option('sgclabs_quote_table_v1') !== '1') {
        sgclabs_quote_create_table();
        update_option('sgclabs_quote_table_v1', '1');
    }
});

// ── Admin menu ────────────────────────────────────────────────────────────────
add_action('admin_menu', function() {
    add_submenu_page(
        'sgclabs-manager',
        'B2B Quotes',
        '📋 B2B Quotes',
        'manage_options',
        'sgclabs-b2b-quotes',
        'sgclabs_b2b_quotes_page'
    );
}, 18);

// ── Frontend: enqueue quote cart JS + CSS ─────────────────────────────────────
// B2B frontend assets removed — inquiry form handles quotes
// add_action('wp_footer', 'sgclabs_b2b_quote_frontend_assets');
function sgclabs_b2b_quote_frontend_assets() {
    if (get_option('sgclabs_b2b_quote_active', '1') !== '1') return;
    if (!is_product() && !is_product_category() && !is_shop()) return;
    // Ensure jQuery available for $.post calls
    wp_enqueue_script('jquery');
    ?>
    <style id="sgclabs-quote-css">
    .sgclabs-quote-btn {
        display:inline-flex; align-items:center; gap:6px;
        background:#1a1a2e; color:#fff; border:none;
        padding:11px 20px; border-radius:6px; font-size:14px;
        font-weight:600; cursor:pointer; width:100%; justify-content:center;
        margin-top:8px; transition:background .2s;
    }
    .sgclabs-quote-btn:hover { background:#27ae60; }
    .sgclabs-quote-btn.added { background:#27ae60; }
    #sgclabs-quote-bubble {
        position:fixed; bottom:148px; right:20px; z-index:9997;
        background:#1a1a2e; color:#fff; padding:10px 16px;
        border-radius:30px; font-size:13px; font-weight:600;
        cursor:pointer; box-shadow:0 4px 16px rgba(0,0,0,.3);
        display:none; align-items:center; gap:8px;
    }
    #sgclabs-quote-bubble .sgc-qb-count {
        background:#27ae60; color:#fff; border-radius:50%;
        width:20px; height:20px; display:flex;
        align-items:center; justify-content:center; font-size:11px;
    }
    </style>
    <?php
}

// ── "Request Quote" button on single product page ─────────────────────────────
// B2B frontend button removed — using inquiry form instead
// add_action('woocommerce_after_add_to_cart_button', 'sgclabs_b2b_quote_product_button');
function sgclabs_b2b_quote_product_button() {
    if (get_option('sgclabs_b2b_quote_active', '1') !== '1') return;
    global $product;
    if (!$product) return;
    $pid   = $product->get_id();
    $name  = esc_js($product->get_name());
    $sku   = esc_js($product->get_sku() ?: '—');
    $price = $product->get_price() ? number_format((float)$product->get_price(), 2) : '0.00';
    $img   = wp_get_attachment_image_url(get_post_thumbnail_id($pid), 'thumbnail') ?: '';
    $quote_page = get_option('sgclabs_quote_page_url', home_url('/request-quote/'));
    echo "<button class='sgclabs-quote-btn' id='sgclabs-qbtn-{$pid}'
        onclick=\"sgclabsAddToQuote({$pid},'{$name}','{$sku}','{$price}','{$img}')\">
        📋 Request Bulk Quote
    </button>
    <div id='sgclabs-quote-bubble' onclick=\"window.location='" . esc_url($quote_page) . "'\">
        📋 Quote Cart <span class='sgc-qb-count' id='sgcqbc'>0</span>
    </div>";
    ?>
    <script>
    (function(){
    function sgclabsGetCart(){try{return JSON.parse(localStorage.getItem('sgclabs_quote_cart')||'[]');}catch(e){return[];}}
    function sgclabsSetCart(c){localStorage.setItem('sgclabs_quote_cart',JSON.stringify(c));}
    function sgclabsUpdateBubble(){
        var c=sgclabsGetCart();
        var b=document.getElementById('sgclabs-quote-bubble');
        var cnt=document.getElementById('sgcqbc');
        if(b){b.style.display=c.length>0?'flex':'none';}
        if(cnt){cnt.textContent=c.length;}
    }
    window.sgclabsAddToQuote=function(id,name,sku,price,img){
        var c=sgclabsGetCart();
        var exists=c.find(function(x){return x.id===id;});
        if(!exists){c.push({id:id,name:name,sku:sku,price:price,img:img,qty:1});}
        sgclabsSetCart(c);
        sgclabsUpdateBubble();
        var btn=document.getElementById('sgclabs-qbtn-'+id);
        if(btn){btn.classList.add('added');btn.textContent='✅ Added to Quote Cart';}
    };
    sgclabsUpdateBubble();
    // Mark already added products
    var c=sgclabsGetCart();
    c.forEach(function(item){
        var btn=document.getElementById('sgclabs-qbtn-'+item.id);
        if(btn){btn.classList.add('added');btn.textContent='✅ Added to Quote Cart';}
    });
    })();
    </script>
    <?php
}

// ── Shortcode: [sgclabs_quote_form] ──────────────────────────────────────────
// B2B shortcode deprecated — use [sgclabs_inquiry_form] instead
// add_shortcode('sgclabs_quote_form', 'sgclabs_quote_form_shortcode');
function sgclabs_quote_form_shortcode() {
    ob_start();
    $countries = ['India','USA','United Kingdom','Germany','France','Italy','Spain','Netherlands','Belgium','Australia','Canada','China','Japan','South Korea','UAE','Saudi Arabia','Kuwait','Qatar','Bahrain','Oman','Egypt','Turkey','Brazil','Mexico','South Africa','Nigeria','Pakistan','Bangladesh','Sri Lanka','Russia','Ukraine','Poland','Sweden','Denmark','Switzerland','Singapore','Malaysia','Thailand','Indonesia','Philippines','Vietnam','New Zealand','Other'];
    $incoterms = ['EXW'=>'EXW – Ex Works','FOB'=>'FOB – Free on Board','CIF'=>'CIF – Cost Insurance Freight','CFR'=>'CFR – Cost & Freight','DAP'=>'DAP – Delivered at Place','DDP'=>'DDP – Delivered Duty Paid','FCA'=>'FCA – Free Carrier'];
    ?>
    <div class="sgclabs-qform-wrap" id="sgclabs-qform-main">
        <div id="sgc-qform-msg" style="display:none;padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:14px;"></div>

        <!-- Cart Items -->
        <div id="sgclabs-qcart-section">
            <h3 style="margin:0 0 12px;font-size:16px;color:#1a1a2e;">📦 Products in Your Quote Request</h3>
            <div id="sgclabs-qcart-items" style="margin-bottom:20px;"></div>
            <div id="sgclabs-qcart-empty" style="display:none;background:#fff9e6;border:1px solid #f0c040;border-radius:6px;padding:14px;font-size:14px;color:#856404;">
                Your quote cart is empty. Browse products and click <strong>"Request Bulk Quote"</strong> to add items.
                <br><a href="<?php echo esc_url(get_permalink(wc_get_page_id('shop'))); ?>" style="color:#1a1a2e;font-weight:700;">← Browse Products</a>
            </div>
            <div id="sgclabs-add-product-row" style="margin-bottom:20px;">
                <button onclick="sgclabsShowAddProduct()" style="background:none;border:1px dashed #ccc;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:13px;color:#666;">+ Add product manually</button>
                <div id="sgclabs-add-product-form" style="display:none;margin-top:10px;background:#f9f9f9;border:1px solid #ddd;border-radius:6px;padding:14px;">
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <input type="text" id="sgc-manual-name" placeholder="Product name / SKU" style="flex:2;min-width:180px;padding:8px;border:1px solid #ccc;border-radius:5px;font-size:13px;">
                        <input type="number" id="sgc-manual-qty" placeholder="Qty" min="1" value="1" style="width:80px;padding:8px;border:1px solid #ccc;border-radius:5px;font-size:13px;">
                        <button onclick="sgclabsAddManual()" style="background:#1a1a2e;color:#fff;border:none;padding:8px 16px;border-radius:5px;cursor:pointer;font-size:13px;">Add</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact & Shipping Details -->
        <div class="sgc-qf-section">
            <h3 style="margin:0 0 16px;font-size:16px;color:#1a1a2e;">👤 Contact & Shipping Details</h3>
            <div class="sgc-qf-grid">
                <div class="sgc-qf-field">
                    <label>Full Name *</label>
                    <input type="text" id="sgcq_name" placeholder="Your name">
                </div>
                <div class="sgc-qf-field">
                    <label>Company / Organization</label>
                    <input type="text" id="sgcq_company" placeholder="Company name (optional)">
                </div>
                <div class="sgc-qf-field">
                    <label>Email Address *</label>
                    <input type="email" id="sgcq_email" placeholder="your@email.com">
                </div>
                <div class="sgc-qf-field">
                    <label>Phone / WhatsApp *</label>
                    <input type="tel" id="sgcq_phone" placeholder="+1 555 000 0000">
                </div>
                <div class="sgc-qf-field">
                    <label>Destination Country *</label>
                    <select id="sgcq_country">
                        <option value="">-- Select Country --</option>
                        <?php foreach ($countries as $c) echo "<option value='" . esc_attr($c) . "'>" . esc_html($c) . "</option>"; ?>
                    </select>
                </div>
                <div class="sgc-qf-field">
                    <label>Preferred Incoterm</label>
                    <select id="sgcq_incoterm">
                        <?php foreach ($incoterms as $code => $label) echo "<option value='" . esc_attr($code) . "'>" . esc_html($label) . "</option>"; ?>
                    </select>
                </div>
                <div class="sgc-qf-field" style="grid-column:1/-1;">
                    <label>Nearest Port / Airport (for shipping)</label>
                    <input type="text" id="sgcq_port" placeholder="e.g. Port of Los Angeles, Heathrow Airport">
                </div>
                <div class="sgc-qf-field" style="grid-column:1/-1;">
                    <label>Special Requirements / Message</label>
                    <textarea id="sgcq_message" rows="3" placeholder="Custom sizes, branding, certifications required, timeline..."></textarea>
                </div>
            </div>
            <button class="sgc-qf-submit" onclick="sgclabsSubmitQuote()">📋 Submit Quote Request to SGC LABS®</button>
        </div>
    </div>

    <style>
    .sgclabs-qform-wrap { max-width:750px; font-family:sans-serif; }
    .sgc-qf-section { background:#f9f9f9; border:1px solid #e0e0e0; border-radius:8px; padding:22px; margin-top:16px; }
    .sgc-qf-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    @media(max-width:600px){.sgc-qf-grid{grid-template-columns:1fr;}}
    .sgc-qf-field { display:flex; flex-direction:column; gap:5px; }
    .sgc-qf-field label { font-weight:600; font-size:13px; color:#333; }
    .sgc-qf-field input,.sgc-qf-field select,.sgc-qf-field textarea { border:1px solid #ccc; border-radius:5px; padding:8px 12px; font-size:14px; box-sizing:border-box; width:100%; }
    .sgc-qf-field input:focus,.sgc-qf-field select:focus,.sgc-qf-field textarea:focus { border-color:#1a1a2e; outline:none; }
    .sgc-qf-submit { margin-top:18px; background:#1a1a2e; color:#fff; border:none; padding:13px 28px; border-radius:6px; font-size:15px; font-weight:700; cursor:pointer; width:100%; }
    .sgc-qf-submit:hover { background:#27ae60; }
    .sgcq-item-row { display:flex; align-items:center; gap:10px; border:1px solid #e8e8e8; border-radius:6px; padding:10px 14px; margin-bottom:8px; background:#fff; }
    .sgcq-item-img { width:48px; height:48px; object-fit:cover; border-radius:4px; background:#f0f0f0; }
    .sgcq-item-info { flex:1; }
    .sgcq-item-name { font-size:14px; font-weight:600; color:#1a1a2e; }
    .sgcq-item-sku { font-size:12px; color:#888; }
    .sgcq-qty-input { width:70px; padding:5px 8px; border:1px solid #ccc; border-radius:4px; text-align:center; font-size:14px; }
    .sgcq-remove-btn { background:none; border:none; color:#e74c3c; font-size:18px; cursor:pointer; padding:0 4px; }
    </style>

    <script>
    (function(){
    function getCart(){try{return JSON.parse(localStorage.getItem('sgclabs_quote_cart')||'[]');}catch(e){return[];}}
    function setCart(c){localStorage.setItem('sgclabs_quote_cart',JSON.stringify(c));}

    function renderCart(){
        var c=getCart();
        var wrap=document.getElementById('sgclabs-qcart-items');
        var empty=document.getElementById('sgclabs-qcart-empty');
        if(!wrap) return;
        if(c.length===0){wrap.innerHTML='';if(empty)empty.style.display='block';return;}
        if(empty)empty.style.display='none';
        wrap.innerHTML=c.map(function(item,i){
            var img=item.img?'<img class="sgcq-item-img" src="'+item.img+'" alt="">'
                            :'<div class="sgcq-item-img" style="display:flex;align-items:center;justify-content:center;color:#ccc;font-size:20px;">📦</div>';
            return '<div class="sgcq-item-row" id="sqrow-'+i+'">'
                +img
                +'<div class="sgcq-item-info">'
                +'<div class="sgcq-item-name">'+item.name+'</div>'
                +'<div class="sgcq-item-sku">SKU: '+item.sku+'</div>'
                +'</div>'
                +'<input type="number" class="sgcq-qty-input" value="'+item.qty+'" min="1" onchange="sgclabsUpdateQty('+i+',this.value)" title="Qty">'
                +'<button class="sgcq-remove-btn" onclick="sgclabsRemoveItem('+i+')" title="Remove">✕</button>'
                +'</div>';
        }).join('');
    }

    window.sgclabsUpdateQty=function(i,v){
        var c=getCart(); if(c[i])c[i].qty=parseInt(v)||1; setCart(c);
    };
    window.sgclabsRemoveItem=function(i){
        var c=getCart(); c.splice(i,1); setCart(c); renderCart();
    };
    window.sgclabsShowAddProduct=function(){
        var f=document.getElementById('sgclabs-add-product-form');
        if(f)f.style.display=f.style.display==='none'?'block':'none';
    };
    window.sgclabsAddManual=function(){
        var name=document.getElementById('sgc-manual-name').value.trim();
        var qty=parseInt(document.getElementById('sgc-manual-qty').value)||1;
        if(!name)return;
        var c=getCart();
        c.push({id:'m_'+Date.now(),name:name,sku:'—',price:'0.00',img:'',qty:qty});
        setCart(c); renderCart();
        document.getElementById('sgc-manual-name').value='';
        document.getElementById('sgc-manual-qty').value='1';
    };

    window.sgclabsSubmitQuote=function(){
        var c=getCart();
        var name=document.getElementById('sgcq_name').value.trim();
        var email=document.getElementById('sgcq_email').value.trim();
        var phone=document.getElementById('sgcq_phone').value.trim();
        var country=document.getElementById('sgcq_country').value;
        var msg=document.getElementById('sgc-qform-msg');
        function showMsg(txt,ok){
            msg.style.display='block';
            msg.style.background=ok?'#efe':'#fee';
            msg.style.color=ok?'#155724':'#721c24';
            msg.textContent=txt;
        }
        if(!name||!email||!phone||!country){return showMsg('❌ Please fill Name, Email, Phone and Country.',false);}
        if(c.length===0){return showMsg('❌ Your quote cart is empty. Add products first.',false);}
        var btn=document.querySelector('.sgc-qf-submit');
        btn.disabled=true; btn.textContent='Sending...';
        jQuery.post('<?php echo esc_url(admin_url('admin-ajax.php')); ?>',{
            action:'sgclabs_submit_b2b_quote',
            nonce:'<?php echo esc_js( wp_create_nonce( 'sgclabs_b2b_quote' ) ); ?>',
            name:name, email:email, phone:phone,
            company:document.getElementById('sgcq_company').value,
            country:country,
            incoterm:document.getElementById('sgcq_incoterm').value,
            port:document.getElementById('sgcq_port').value,
            message:document.getElementById('sgcq_message').value,
            products:JSON.stringify(c)
        },function(res){
            if(res.success){
                localStorage.removeItem('sgclabs_quote_cart');
                showMsg('✅ Quote request submitted! Ref: '+res.data.ref+' — We will email you within 24 hours.',true);
                document.getElementById('sgclabs-qform-main').style.opacity='0.6';
                document.getElementById('sgclabs-qform-main').style.pointerEvents='none';
            } else {
                showMsg('❌ Error: '+(res.data||'Please try again or email sales@labsglass.com'),false);
                btn.disabled=false; btn.textContent='📋 Submit Quote Request to SGC LABS®';
            }
        });
    };

    renderCart();
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ── AJAX: Submit quote ────────────────────────────────────────────────────────
add_action('wp_ajax_sgclabs_submit_b2b_quote',       'sgclabs_handle_b2b_quote');
add_action('wp_ajax_nopriv_sgclabs_submit_b2b_quote', 'sgclabs_handle_b2b_quote');
function sgclabs_handle_b2b_quote() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sgclabs_b2b_quote'))
        wp_send_json_error('Invalid nonce');

    $ip        = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $rate_key  = 'sgclabs_qrate_' . md5($ip);
    if ((int)get_transient($rate_key) >= 3)
        wp_send_json_error('Too many requests. Please wait 5 minutes.');
    set_transient($rate_key, (int)get_transient($rate_key) + 1, 5 * MINUTE_IN_SECONDS);

    global $wpdb;
    $name      = sanitize_text_field($_POST['name']    ?? '');
    $email     = sanitize_email($_POST['email']        ?? '');
    $phone     = sanitize_text_field($_POST['phone']   ?? '');
    $company   = sanitize_text_field($_POST['company'] ?? '');
    $country   = sanitize_text_field($_POST['country'] ?? '');
    $incoterm  = sanitize_text_field($_POST['incoterm']?? 'EXW');
    $port      = sanitize_text_field($_POST['port']    ?? '');
    $message   = sanitize_textarea_field($_POST['message'] ?? '');
    $products_raw = stripslashes($_POST['products'] ?? '[]');
    $products  = json_decode($products_raw, true);
    if (!is_array($products)) $products = [];

    $ref_no = 'SGCQ-' . strtoupper(substr(md5(time() . $email), 0, 6));

    $inserted = $wpdb->insert(
        $wpdb->prefix . 'sgclabs_b2b_quotes',
        [
            'ref_no'   => $ref_no,
            'name'     => $name,
            'email'    => $email,
            'phone'    => $phone,
            'company'  => $company,
            'country'  => $country,
            'incoterm' => $incoterm,
            'port'     => $port,
            'products' => wp_json_encode($products),
            'message'  => $message,
            'status'   => 'new',
        ]
    );

    if (!$inserted) wp_send_json_error('DB error — please contact sales@labsglass.com');

    $quote_id = $wpdb->insert_id;

    // v7.3.1: Mirror write to sgclabs_inquiries so unified pipeline, AI Reply
    // Center, follow-up automation and CRM sync all see B2B quotes.
    $product_summary = implode(', ', array_map(function($p){ return ($p['name'] ?? '—') . ' ×' . ($p['qty'] ?? 1); }, $products));
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $wpdb->insert(
        $wpdb->prefix . 'sgclabs_inquiries',
        [
            'trade_type'  => 'export',
            'name'        => $name,
            'email'       => $email,
            'phone'       => $phone,
            'company'     => $company,
            'country'     => $country,
            'incoterm'    => $incoterm,
            'port'        => $port,
            'product'     => substr( '[B2B] ' . $product_summary, 0, 200 ),
            'qty'         => '',
            'message'     => $message . "\n\n[B2B Ref: {$ref_no}]",
            'status'      => 'new',
            'source_type' => 'b2b',
        ]
    );
    $inq_id = $wpdb->insert_id;

    // Fire unified hook so automation, CRM sync and follow-up scheduler all trigger
    do_action( 'sgclabs_inquiry_received', [
        'id'            => 'b2b_' . $quote_id,
        'db_id'         => $inq_id,
        'source'        => 'b2b',
        'status'        => 'new',
        'name'          => $name,
        'email'         => $email,
        'phone'         => $phone,
        'country'       => $country,
        'company'       => $company,
        'subject'       => 'B2B Quote: ' . $product_summary,
        'message'       => $message,
        'meta'          => [ 'ref_no' => $ref_no, 'incoterm' => $incoterm, 'port' => $port, 'products' => $products ],
        'created_at'    => time(),
    ] );

    // Build product rows for email
    $product_rows_html = '';
    $product_rows_text = '';
    foreach ($products as $p) {
        $pname = esc_html($p['name'] ?? '—');
        $psku  = esc_html($p['sku']  ?? '—');
        $pqty  = intval($p['qty']    ?? 1);
        $product_rows_html .= "<tr><td style='border:1px solid #e2e8f0;padding:8px;'>{$pname}</td><td style='border:1px solid #e2e8f0;padding:8px;'>{$psku}</td><td style='border:1px solid #e2e8f0;padding:8px;text-align:center;'>{$pqty}</td></tr>";
        $product_rows_text .= "  - {$pname} (SKU: {$psku}) × {$pqty}\n";
    }

    // Admin notification email
    $admin_email = get_option('sgclabs_quote_notify_email', get_option('admin_email'));
    $admin_url   = admin_url("admin.php?page=sgclabs-quotes-inquiries&tab=b2b-quotes&view={$quote_id}");
    $admin_body  = "<html><body style='font-family:Arial,sans-serif;font-size:14px;color:#333;'>"
        . "<h2 style='color:#1a1a2e;'>New B2B Quote Request — {$ref_no}</h2>"
        . "<p><strong>From:</strong> " . esc_html($name) . " (" . esc_html($company) . ") &lt;" . esc_html($email) . "&gt;<br>"
        . "<strong>Phone:</strong> " . esc_html($phone) . " | <strong>Country:</strong> " . esc_html($country) . "<br>"
        . "<strong>Incoterm:</strong> {$incoterm} | <strong>Port:</strong> " . esc_html($port) . "</p>"
        . "<h3>Products Requested:</h3>"
        . "<table cellpadding='0' cellspacing='0' style='border-collapse:collapse;width:100%;'>"
        . "<thead><tr style='background:#f8fafc;'><th style='border:1px solid #e2e8f0;padding:8px;text-align:left;'>Product</th><th style='border:1px solid #e2e8f0;padding:8px;'>SKU</th><th style='border:1px solid #e2e8f0;padding:8px;'>Qty</th></tr></thead>"
        . "<tbody>{$product_rows_html}</tbody></table>"
        . ($message ? "<p><strong>Message:</strong><br>" . nl2br(esc_html($message)) . "</p>" : '')
        . "<p><a href='{$admin_url}' style='background:#1a1a2e;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;font-weight:700;display:inline-block;'>View Quote in Admin →</a></p>"
        . "</body></html>";

    wp_mail(
        $admin_email,
        "New Quote Request: {$ref_no} — " . esc_html($name) . " (" . esc_html($country) . ")",
        $admin_body,
        ['Content-Type: text/html; charset=UTF-8', 'From: SGC Labs <info@labsglass.com>']
    );

    // Buyer confirmation email
    $buyer_subject = "Quote Request Received — SGC LABS® [{$ref_no}]";
    $buyer_body    = "<html><body style='font-family:Arial,sans-serif;font-size:14px;color:#333;background:#f0f2f5;padding:20px;'>"
        . "<div style='max-width:580px;margin:0 auto;background:#fff;border-radius:10px;overflow:hidden;'>"
        . "<div style='background:#1a1a2e;padding:24px 30px;'>"
        . "<p style='margin:0;font-size:22px;font-weight:900;color:#27ae60;'>SGC LABS®</p>"
        . "<p style='margin:4px 0 0;font-size:12px;color:rgba(255,255,255,.6);'>Scientific Glassware Manufacturer</p></div>"
        . "<div style='padding:28px 30px;'>"
        . "<p style='font-size:18px;font-weight:700;color:#1a1a2e;'>Hi " . esc_html($name) . ", your quote request is received!</p>"
        . "<p>Ref: <strong style='font-size:18px;color:#27ae60;font-family:monospace;'>{$ref_no}</strong></p>"
        . "<p>We will prepare a detailed quotation and send it to <strong>" . esc_html($email) . "</strong> within <strong>24–48 hours</strong>.</p>"
        . "<h3>Products you requested:</h3>"
        . "<table cellpadding='0' cellspacing='0' style='border-collapse:collapse;width:100%;'>"
        . "<thead><tr style='background:#f0fdf4;'><th style='border:1px solid #86efac;padding:8px;text-align:left;'>Product</th><th style='border:1px solid #86efac;padding:8px;'>SKU</th><th style='border:1px solid #86efac;padding:8px;'>Qty</th></tr></thead>"
        . "<tbody>{$product_rows_html}</tbody></table>"
        . "<p><strong>Incoterm:</strong> {$incoterm}" . ($port ? " | <strong>Port:</strong> " . esc_html($port) : '') . "</p>"
        . "<p style='margin-top:20px;'>Need faster response? "
        . "<a href='https://wa.me/919996416935?text=Hi+ref+" . rawurlencode($ref_no) . "' style='color:#25d366;font-weight:700;'>WhatsApp us</a> or call +91 999 641 6935</p>"
        . "</div>"
        . "<div style='background:#f8fafc;padding:16px 30px;font-size:12px;color:#64748b;text-align:center;'>"
        . "SGC LABS® · Ambala, India · <a href='https://labsglass.com' style='color:#27ae60;'>labsglass.com</a></div>"
        . "</div></body></html>";

    wp_mail(
        $email,
        $buyer_subject,
        $buyer_body,
        ['Content-Type: text/html; charset=UTF-8', 'From: SGC Labs <info@labsglass.com>']
    );

    wp_send_json_success(['ref' => $ref_no, 'id' => $quote_id]);
}

// ── Admin: Update quote status ────────────────────────────────────────────────
add_action('wp_ajax_sgclabs_update_quote_status', 'sgclabs_update_quote_status');
function sgclabs_update_quote_status() {
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sgclabs_admin_quotes')) wp_send_json_error('Invalid nonce');
    global $wpdb;
    $id     = intval($_POST['id']     ?? 0);
    $status = sanitize_text_field($_POST['status'] ?? '');
    $note   = sanitize_textarea_field($_POST['note'] ?? '');
    $total  = isset($_POST['total']) ? (float)$_POST['total'] : null;
    if (!$id || !in_array($status, ['new','quoted','negotiating','won','lost'])) wp_send_json_error('Bad data');
    $data = ['status' => $status];
    if ($note)  $data['admin_note']  = $note;
    if ($total) $data['total_usd']   = $total;
    $wpdb->update($wpdb->prefix . 'sgclabs_b2b_quotes', $data, ['id' => $id]);
    wp_send_json_success();
}

// ── Admin: Delete quote ───────────────────────────────────────────────────────
add_action('wp_ajax_sgclabs_delete_quote', 'sgclabs_delete_quote');
function sgclabs_delete_quote() {
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sgclabs_admin_quotes')) { wp_send_json_error('Invalid nonce'); return; }
    global $wpdb;
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { wp_send_json_error('Bad ID'); return; }
    $wpdb->delete($wpdb->prefix . 'sgclabs_b2b_quotes', ['id' => $id]);
    wp_send_json_success();
}

// ── Admin page ────────────────────────────────────────────────────────────────
function sgclabs_b2b_quotes_page() {
    global $wpdb;
    $table   = $wpdb->prefix . 'sgclabs_b2b_quotes';
    $nonce   = wp_create_nonce('sgclabs_admin_quotes');

    // Handle settings save
    if (isset($_POST['sgclabs_quote_settings']) && current_user_can('manage_options')) {
        check_admin_referer('sgclabs_quote_settings_nonce');
        update_option('sgclabs_quote_notify_email', sanitize_email($_POST['notify_email'] ?? ''));
        update_option('sgclabs_quote_page_url',     esc_url_raw($_POST['quote_page_url'] ?? ''));
        echo "<div class='notice notice-success is-dismissible'><p>Settings saved!</p></div>";
    }

    $filter = sanitize_text_field($_GET['status'] ?? 'all');
    $where  = ($filter !== 'all') ? $wpdb->prepare("WHERE status = %s", $filter) : '';
    $quotes = $wpdb->get_results("SELECT * FROM $table $where ORDER BY created_at DESC");

    $counts = [];
    foreach (['new','quoted','negotiating','won','lost'] as $s) {
        $counts[$s] = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status = %s", $s));
    }
    $counts['all'] = array_sum($counts);

    $status_colors = [
        'new'         => '#e74c3c',
        'quoted'      => '#f39c12',
        'negotiating' => '#3498db',
        'won'         => '#27ae60',
        'lost'        => '#95a5a6',
    ];
    $status_labels = [
        'new'=>'New','quoted'=>'Quoted','negotiating'=>'Negotiating','won'=>'Won','lost'=>'Lost'
    ];

    $notify_email  = get_option('sgclabs_quote_notify_email', get_option('admin_email'));
    $quote_page_url= get_option('sgclabs_quote_page_url', home_url('/request-quote/'));
    ?>
    <div class="wrap sgclabs-wrap">
<?php sgclabs_module_control_bar(["module_id"=>"b2b-quotes","module_name"=>"B2B Quote Engine","module_icon"=>"📋","option_key"=>"sgclabs_b2b_quotes_active","default"=>"1","description"=>"Multi-product quote requests with status tracking & PDF export"]); ?>
        <h1>📋 B2B Quote Requests</h1>

        <!-- Stats Bar -->
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin:12px 0 20px;">
            <?php foreach (array_merge(['all'=>'All'], $status_labels) as $s => $label): ?>
            <a href="?page=sgclabs-quotes-inquiries&tab=b2b-quotes&status=<?php echo esc_attr($s); ?>"
               style="padding:8px 16px;border-radius:20px;font-size:13px;font-weight:600;text-decoration:none;
                      background:<?php echo ($filter===$s)?($s==='all'?'#1a1a2e':($status_colors[$s]??'#1a1a2e')):'#f0f0f0'; ?>;
                      color:<?php echo ($filter===$s)?'#fff':'#333'; ?>;">
                <?php echo esc_html($label); ?> (<?php echo intval($counts[$s] ?? 0); ?>)
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Shortcode info -->
        <div class="sgclabs-section">
            <strong>Quote Form Shortcode:</strong>
            <code style="background:#f0f0f0;padding:4px 10px;border-radius:4px;margin-left:8px;">[sgclabs_quote_form]</code>
            <span style="margin-left:12px;font-size:13px;color:#666;">Add to any page (e.g. /request-quote/)</span>
        </div>

        <!-- Quotes Table -->
        <?php if (empty($quotes)): ?>
            <div class="sgclabs-section"><p>No quote requests yet for this filter.</p></div>
        <?php else: ?>
        <div class="sgclabs-section" style="overflow-x:auto;">
            <table class="wp-list-table widefat fixed striped" style="min-width:900px;">
                <thead>
                    <tr>
                        <th style="width:90px;">Ref</th>
                        <th style="width:140px;">Buyer</th>
                        <th style="width:90px;">Country</th>
                        <th style="width:60px;">Term</th>
                        <th>Products</th>
                        <th style="width:80px;">Value $</th>
                        <th style="width:100px;">Status</th>
                        <th style="width:80px;">Date</th>
                        <th style="width:110px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($quotes as $q):
                    $products = json_decode($q->products, true) ?: [];
                    $prod_list = implode(', ', array_map(fn($p) => esc_html(($p['name'] ?? '?') . ' ×' . ($p['qty'] ?? 1)), $products));
                    $color     = $status_colors[$q->status] ?? '#888';
                ?>
                    <tr id="sqrow-<?php echo intval($q->id); ?>">
                        <td><strong style="color:#1a1a2e;font-family:monospace;"><?php echo esc_html($q->ref_no); ?></strong></td>
                        <td>
                            <strong><?php echo esc_html($q->name); ?></strong><br>
                            <small style="color:#666;"><?php echo esc_html($q->company); ?></small><br>
                            <a href="mailto:<?php echo esc_attr($q->email); ?>" style="font-size:12px;"><?php echo esc_html($q->email); ?></a>
                        </td>
                        <td><?php echo esc_html($q->country); ?></td>
                        <td><span style="background:#e8f0fe;color:#1a56db;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600;"><?php echo esc_html($q->incoterm); ?></span></td>
                        <td style="font-size:12px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr($prod_list); ?>">
                            <?php echo esc_html(substr($prod_list, 0, 120)); ?>
                        </td>
                        <td>
                            <input type="number" step="0.01" placeholder="0.00"
                                   value="<?php echo $q->total_usd ? esc_attr(number_format((float)$q->total_usd, 2, '.', '')) : ''; ?>"
                                   style="width:75px;padding:4px;border:1px solid #ccc;border-radius:4px;font-size:12px;"
                                   id="sqval-<?php echo intval($q->id); ?>">
                        </td>
                        <td>
                            <select id="sqstat-<?php echo intval($q->id); ?>"
                                    style="padding:4px 6px;border:1px solid;border-radius:4px;font-size:12px;font-weight:600;color:<?php echo esc_attr($color); ?>;border-color:<?php echo esc_attr($color); ?>;">
                                <?php foreach ($status_labels as $s => $l): ?>
                                <option value="<?php echo esc_attr($s); ?>" <?php selected($q->status, $s); ?>><?php echo esc_html($l); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td style="font-size:12px;color:#666;"><?php echo date('d M Y', strtotime($q->created_at)); ?></td>
                        <td>
                            <button class="button button-small" style="margin:2px;" onclick="sgcViewQuote(<?php echo intval($q->id); ?>)">👁 View</button>
                            <button class="button button-small button-primary" style="margin:2px;" onclick="sgcSaveQuote(<?php echo intval($q->id); ?>)">💾 Save</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Quote detail modal -->
        <div id="sgcq-modal" style="display:none;position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.5);align-items:center;justify-content:center;">
            <div style="background:#fff;border-radius:10px;width:90%;max-width:680px;max-height:88vh;overflow-y:auto;padding:28px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <h2 style="margin:0;">Quote Details</h2>
                    <button onclick="document.getElementById('sgcq-modal').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;">✕</button>
                </div>
                <div id="sgcq-modal-body"></div>
            </div>
        </div>

        <!-- Settings -->
        <div class="sgclabs-section" style="margin-top:24px;">
            <h3>⚙️ Quote Engine Settings</h3>
            <form method="post">
                <?php wp_nonce_field('sgclabs_quote_settings_nonce'); ?>
                <input type="hidden" name="sgclabs_quote_settings" value="1">
                <table class="form-table">
                    <tr>
                        <th>Quote Notification Email</th>
                        <td><input type="email" name="notify_email" value="<?php echo esc_attr($notify_email); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Quote Form Page URL</th>
                        <td><input type="url" name="quote_page_url" value="<?php echo esc_url($quote_page_url); ?>" class="regular-text">
                        <p class="description">Create a page, add shortcode <code>[sgclabs_quote_form]</code>, paste its URL here.</p></td>
                    </tr>
                </table>
                <p><button type="submit" class="button button-primary">Save Settings</button></p>
            </form>
        </div>
    </div>

    <?php
    // Output quote data as JSON for modal
    $quote_data = [];
    foreach ($wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC") as $q) {
        $quote_data[$q->id] = [
            'ref'      => $q->ref_no,
            'name'     => $q->name,
            'company'  => $q->company,
            'email'    => $q->email,
            'phone'    => $q->phone,
            'country'  => $q->country,
            'incoterm' => $q->incoterm,
            'port'     => $q->port,
            'products' => json_decode($q->products, true) ?: [],
            'message'  => $q->message,
            'note'     => $q->admin_note,
            'total'    => $q->total_usd,
            'date'     => date('d M Y, h:i A', strtotime($q->created_at)),
        ];
    }
    ?>
    <script>
    var sgcQuotes=<?php echo wp_json_encode($quote_data); ?>;
    var sgcNonce='<?php echo esc_js($nonce); ?>';

    function sgcViewQuote(id){
        var q=sgcQuotes[id]; if(!q) return;
        var rows=q.products.map(function(p){
            return '<tr><td style="border:1px solid #e2e8f0;padding:7px;">'+p.name+'</td><td style="border:1px solid #e2e8f0;padding:7px;">'+p.sku+'</td><td style="border:1px solid #e2e8f0;padding:7px;text-align:center;">'+p.qty+'</td></tr>';
        }).join('');
        var html='<table style="width:100%;border-collapse:collapse;margin-bottom:14px;">'
            +'<tr><td style="padding:6px 0;color:#666;width:120px;">Ref</td><td style="font-family:monospace;font-weight:700;font-size:16px;color:#27ae60;">'+q.ref+'</td></tr>'
            +'<tr><td style="padding:6px 0;color:#666;">Name</td><td>'+q.name+(q.company?' <span style="color:#888;">('+q.company+')</span>':'')+'</td></tr>'
            +'<tr><td style="padding:6px 0;color:#666;">Email</td><td><a href="mailto:'+q.email+'">'+q.email+'</a></td></tr>'
            +'<tr><td style="padding:6px 0;color:#666;">Phone</td><td>'+q.phone+'</td></tr>'
            +'<tr><td style="padding:6px 0;color:#666;">Country</td><td>'+q.country+'</td></tr>'
            +'<tr><td style="padding:6px 0;color:#666;">Incoterm</td><td><strong>'+q.incoterm+'</strong>'+(q.port?' — Port: '+q.port:'')+'</td></tr>'
            +'<tr><td style="padding:6px 0;color:#666;">Date</td><td>'+q.date+'</td></tr>'
            +'</table>'
            +'<h3 style="margin:0 0 8px;">Products Requested</h3>'
            +'<table style="width:100%;border-collapse:collapse;margin-bottom:14px;">'
            +'<thead><tr style="background:#f8fafc;"><th style="border:1px solid #e2e8f0;padding:7px;text-align:left;">Product</th><th style="border:1px solid #e2e8f0;padding:7px;">SKU</th><th style="border:1px solid #e2e8f0;padding:7px;">Qty</th></tr></thead>'
            +'<tbody>'+rows+'</tbody></table>'
            +(q.message?'<p><strong>Message:</strong><br>'+q.message+'</p>':'')
            +'<div style="background:#f8fafc;border-radius:6px;padding:14px;margin-top:14px;">'
            +'<p style="margin:0 0 8px;font-weight:600;">Admin Note</p>'
            +'<textarea id="sgcq-note-'+id+'" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;font-size:13px;box-sizing:border-box;" rows="3" placeholder="Internal notes...">'+(q.note||'')+'</textarea>'
            +'</div>'
            +'<div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;">'
            +'<a href="mailto:'+q.email+'?subject=Re: Quote '+q.ref+'" class="button button-primary">✉️ Email Buyer</a>'
            +'<a href="https://wa.me/'+q.phone.replace(/[^0-9]/g,'')+'" target="_blank" class="button">💬 WhatsApp</a>'
            +'<button class="button" onclick="sgcPrintQuote('+id+')">🖨️ Print / PDF</button>'
            +'</div>';
        document.getElementById('sgcq-modal-body').innerHTML=html;
        document.getElementById('sgcq-modal').style.display='flex';
    }

    function sgcSaveQuote(id){
        var status=document.getElementById('sqstat-'+id).value;
        var val=document.getElementById('sqval-'+id).value;
        var noteEl=document.getElementById('sgcq-note-'+id);
        var note=noteEl?noteEl.value:'';
        jQuery.post(ajaxurl,{action:'sgclabs_update_quote_status',nonce:sgcNonce,id:id,status:status,total:val,note:note},function(r){
            if(r.success){var row=document.getElementById('sqrow-'+id);if(row)row.style.background='#f0fff4';}
        });
    }

    function sgcPrintQuote(id){
        var q=sgcQuotes[id]; if(!q) return;
        var rows=q.products.map(function(p,i){
            var bg=i%2===0?'#fff':'#f9f9f9';
            return '<tr style="background:'+bg+'"><td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;">'+(i+1)+'</td>'
                +'<td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;">'+p.name+'</td>'
                +'<td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;">'+p.sku+'</td>'
                +'<td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;text-align:center;">'+p.qty+'</td>'
                +'<td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;text-align:right;">—</td></tr>';
        }).join('');
        var html='<!DOCTYPE html><html><head><title>Quote '+q.ref+'</title>'
            +'<style>*{font-family:Arial,sans-serif;}@media print{body{margin:0;}}</style></head>'
            +'<body style="max-width:780px;margin:0 auto;padding:30px;font-size:13px;color:#333;">'
            +'<table width="100%" style="margin-bottom:24px;">'
            +'<tr><td>'
            +'<div style="font-size:26px;font-weight:900;color:#27ae60;letter-spacing:2px;">SGC LABS®</div>'
            +'<div style="font-size:12px;color:#666;">Scientific Glassware Manufacturer · ISO 9001:2015</div>'
            +'<div style="font-size:12px;color:#666;">Ambala Cantt, Haryana 133001, India</div>'
            +'<div style="font-size:12px;color:#666;">+91 999 641 6935 · sales@labsglass.com · labsglass.com</div>'
            +'</td><td style="text-align:right;">'
            +'<div style="font-size:22px;font-weight:700;color:#1a1a2e;">QUOTATION</div>'
            +'<div style="font-size:16px;font-family:monospace;color:#27ae60;font-weight:700;">'+q.ref+'</div>'
            +'<div style="font-size:12px;color:#666;">Date: '+q.date+'</div>'
            +'</td></tr></table>'
            +'<table width="100%" style="border:1px solid #e0e0e0;border-radius:6px;border-collapse:collapse;margin-bottom:20px;">'
            +'<tr style="background:#f8fafc;"><td style="padding:10px 14px;font-weight:700;">Bill To</td><td style="padding:10px 14px;font-weight:700;">Shipping Info</td></tr>'
            +'<tr><td style="padding:10px 14px;vertical-align:top;border-top:1px solid #f0f0f0;">'
            +'<strong>'+q.name+'</strong><br>'+(q.company?q.company+'<br>':'')+''+q.email+'<br>'+q.phone+'<br>'+q.country
            +'</td><td style="padding:10px 14px;vertical-align:top;border-top:1px solid #f0f0f0;">'
            +'Incoterm: <strong>'+q.incoterm+'</strong><br>'+(q.port?'Port: '+q.port+'<br>':'')
            +'Country: '+q.country
            +'</td></tr></table>'
            +'<table width="100%" style="border-collapse:collapse;">'
            +'<thead><tr style="background:#1a1a2e;color:#fff;">'
            +'<th style="padding:10px 12px;text-align:left;width:35px;">#</th>'
            +'<th style="padding:10px 12px;text-align:left;">Product Description</th>'
            +'<th style="padding:10px 12px;text-align:left;">SKU</th>'
            +'<th style="padding:10px 12px;text-align:center;">Qty</th>'
            +'<th style="padding:10px 12px;text-align:right;">Unit Price (USD)</th>'
            +'</tr></thead><tbody>'+rows+'</tbody>'
            +'<tfoot><tr style="background:#f0fdf4;font-weight:700;">'
            +'<td colspan="4" style="padding:10px 12px;text-align:right;border-top:2px solid #27ae60;">Total (USD)</td>'
            +'<td style="padding:10px 12px;text-align:right;border-top:2px solid #27ae60;font-size:16px;color:#27ae60;">'
            +(q.total?'$'+parseFloat(q.total).toFixed(2):'To be confirmed')+'</td></tr></tfoot></table>'
            +(q.message?'<p style="margin-top:18px;"><strong>Buyer Note:</strong><br>'+q.message+'</p>':'')
            +'<div style="margin-top:24px;padding:14px;background:#f8fafc;border-left:3px solid #27ae60;font-size:12px;color:#555;">'
            +'<strong>Terms & Conditions:</strong> Prices valid for 30 days. Payment: 100% advance or 30% advance, 70% before shipment (for established clients). Lead time: 2–4 weeks. '
            +'Incoterm: '+q.incoterm+'.'+(q.port?' Port: '+q.port+'.':'')+' All prices in USD.</div>'
            +'<div style="margin-top:20px;text-align:center;font-size:12px;color:#94a3b8;">'
            +'SGC LABS® · sales@labsglass.com · +91 999 641 6935 · labsglass.com</div>'
            +'<script>window.onload=function(){window.print();}<\/script>'
            +'</body></html>';
        var w=window.open('','_blank','width=820,height=900');
        w.document.write(html);
        w.document.close();
    }
    </script>
    <?php
}
