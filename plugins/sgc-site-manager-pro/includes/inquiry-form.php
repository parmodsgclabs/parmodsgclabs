<?php
if (!defined('ABSPATH')) exit;

// Shortcode: [sgclabs_inquiry_form]
add_shortcode('sgclabs_inquiry_form', 'sgclabs_inquiry_shortcode');

// ── Aliases — all render the same inquiry form ────────────────────────────
// [sgclabs_quote_form]   — used on /request-quote/ B2B page
// [sgclabs_b2b_inquiry_form] — B2B specific alias
// [sgc_contact_form]     — generic alias for any contact page
add_shortcode('sgclabs_quote_form',       'sgclabs_inquiry_shortcode');
add_shortcode('sgclabs_b2b_inquiry_form', 'sgclabs_inquiry_shortcode');
add_shortcode('sgc_contact_form',         'sgclabs_inquiry_shortcode');
function sgclabs_inquiry_shortcode($atts) {
    if (!is_array($atts)) $atts = [];
    $atts = shortcode_atts(['product' => '', 'title' => 'Product Inquiry'], $atts);
    ob_start();
    ?>
    <div class="sgclabs-inquiry-wrap">
        <h3 class="sgclabs-inquiry-title">📩 <?php echo esc_html($atts['title']); ?></h3>
        <div id="sgclabs-form-msg" style="display:none;padding:10px;border-radius:5px;margin-bottom:15px;"></div>
        <div class="sgclabs-inquiry-form">
            <div class="sgi-row">
                <div class="sgi-col">
                    <label>Your Name *</label>
                    <input type="text" id="sgi_name" placeholder="Full Name" required>
                </div>
                <div class="sgi-col">
                    <label>Email Address *</label>
                    <input type="email" id="sgi_email" placeholder="your@email.com" required>
                </div>
            </div>
            <div class="sgi-row">
                <div class="sgi-col">
                    <label>Phone / WhatsApp</label>
                    <input type="tel" id="sgi_phone" placeholder="+91 999 000 0000">
                </div>
                <div class="sgi-col">
                    <label>Country</label>
                    <select id="sgi_country">
                        <option value="">-- Select Country --</option>
                        <?php
                        $countries = ['India','USA','UK','Germany','France','Italy','Spain','Australia','Canada','China','Japan','South Korea','UAE','Saudi Arabia','Pakistan','Bangladesh','Brazil','Russia','Indonesia','Nigeria','South Africa','Egypt','Turkey','Netherlands','Mexico','Other'];
                        foreach ($countries as $c) echo "<option value='" . esc_attr($c) . "'>" . esc_html($c) . "</option>";
                        ?>
                    </select>
                </div>
            </div>
            <div class="sgi-row">
                <div class="sgi-col">
                    <label>Company / Organization</label>
                    <input type="text" id="sgi_company" placeholder="Company name (optional)">
                </div>
                <div class="sgi-col">
                    <label>Preferred Incoterm</label>
                    <select id="sgi_incoterm">
                        <option value="">-- Select (optional) --</option>
                        <option value="EXW">EXW – Ex Works</option>
                        <option value="FOB">FOB – Free on Board</option>
                        <option value="CIF">CIF – Cost Insurance Freight</option>
                        <option value="CFR">CFR – Cost & Freight</option>
                        <option value="DAP">DAP – Delivered at Place</option>
                        <option value="DDP">DDP – Delivered Duty Paid</option>
                    </select>
                </div>
            </div>
            <div class="sgi-row">
                <div class="sgi-col" style="flex:1">
                    <label>Nearest Port / Airport <small style="color:#999;">(for export shipments)</small></label>
                    <input type="text" id="sgi_port" placeholder="e.g. Port of Los Angeles, Dubai Port, Heathrow">
                </div>
            </div>
            <div class="sgi-row">
                <div class="sgi-col" style="flex:1">
                    <label>Product Name / Category *</label>
                    <input type="text" id="sgi_product" placeholder="e.g. Borosilicate Beaker 500ml, Ground Joints..." value="<?php echo esc_attr($atts['product']); ?>">
                </div>
            </div>
            <div class="sgi-row">
                <div class="sgi-col" style="flex:1">
                    <label>Quantity Required</label>
                    <input type="text" id="sgi_qty" placeholder="e.g. 100 pcs, 50 dozen, bulk">
                </div>
            </div>
            <div class="sgi-row">
                <div class="sgi-col" style="flex:1">
                    <label>Message / Special Requirements</label>
                    <textarea id="sgi_message" rows="4" placeholder="Any specific requirements, customization, OEM printing needs..."></textarea>
                </div>
            </div>
            <button class="sgi-submit-btn" onclick="sgclabsSubmitInquiry()">📨 Send Inquiry to SGC LABS®</button>
        </div>
    </div>
    <style>
    .sgclabs-inquiry-wrap { max-width:700px; background:#f9f9f9; border:1px solid #ddd; border-radius:10px; padding:25px; font-family:sans-serif; }
    .sgclabs-inquiry-title { color:#1a1a2e; margin-bottom:20px; }
    .sgi-row { display:flex; gap:15px; margin-bottom:15px; flex-wrap:wrap; }
    .sgi-col { flex:1; min-width:200px; display:flex; flex-direction:column; gap:5px; }
    .sgi-col label { font-weight:600; font-size:13px; color:#333; }
    .sgi-col input, .sgi-col select, .sgi-col textarea { border:1px solid #ccc; border-radius:5px; padding:8px 12px; font-size:14px; width:100%; box-sizing:border-box; }
    .sgi-col input:focus, .sgi-col select:focus, .sgi-col textarea:focus { border-color:#1a1a2e; outline:none; }
    .sgi-submit-btn { background:#1a1a2e; color:#fff; border:none; padding:12px 30px; border-radius:6px; font-size:15px; cursor:pointer; width:100%; margin-top:5px; }
    .sgi-submit-btn:hover { background:#2d2d5e; }
    </style>
    <script>
    function sgclabsSubmitInquiry() {
        const name = document.getElementById('sgi_name').value.trim();
        const email = document.getElementById('sgi_email').value.trim();
        const product = document.getElementById('sgi_product').value.trim();
        const msgEl = document.getElementById('sgclabs-form-msg');
        if (!name || !email || !product) {
            msgEl.style.display = 'block';
            msgEl.style.background = '#fee';
            msgEl.style.color = '#c00';
            msgEl.textContent = '❌ Please fill Name, Email and Product fields.';
            return;
        }
        const btn = document.querySelector('.sgi-submit-btn');
        btn.disabled = true; btn.textContent = 'Sending...';
        jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
            action: 'sgclabs_submit_inquiry',
            nonce: '<?php echo esc_js( wp_create_nonce( 'sgclabs_inquiry' ) ); ?>',
            name: name, email: email,
            phone: document.getElementById('sgi_phone').value,
            country: document.getElementById('sgi_country').value,
            company: document.getElementById('sgi_company').value,
            incoterm: document.getElementById('sgi_incoterm').value,
            port: document.getElementById('sgi_port').value,
            product: product,
            qty: document.getElementById('sgi_qty').value,
            message: document.getElementById('sgi_message').value
        }, function(res) {
            msgEl.style.display = 'block';
            if (res.success) {
                msgEl.style.background = '#efe'; msgEl.style.color = '#060';
                msgEl.textContent = '✅ Inquiry sent! A confirmation email has been sent to you. Our team will reply within 24–48 hours.';
                document.querySelector('.sgclabs-inquiry-form').style.opacity = '0.5';
            } else {
                msgEl.style.background = '#fee'; msgEl.style.color = '#c00';
                msgEl.textContent = '❌ Error. Please try again or email directly: sales@labsglass.com';
                btn.disabled = false; btn.textContent = '📨 Send Inquiry to SGC LABS®';
            }
        });
    }
    </script>
    <?php
    return ob_get_clean();
}

// Handle form submission
add_action('wp_ajax_sgclabs_submit_inquiry', 'sgclabs_handle_inquiry');
add_action('wp_ajax_nopriv_sgclabs_submit_inquiry', 'sgclabs_handle_inquiry');
function sgclabs_handle_inquiry() {
    if (get_option('sgclabs_inquiry_form_active', '1') !== '1') { wp_send_json_error('Module disabled'); return; }
    if (!wp_verify_nonce(($_POST['nonce'] ?? ''), 'sgclabs_inquiry')) wp_send_json_error('Invalid nonce');

    // FIX: Rate limiting — same IP se 5 min mein max 3 submissions
    $ip         = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $rate_key   = 'sgclabs_inq_rate_' . md5($ip);
    $rate_count = (int) get_transient($rate_key);
    if ($rate_count >= 3) {
        wp_send_json_error('Too many submissions. Please wait a few minutes and try again.');
        return;
    }
    set_transient($rate_key, $rate_count + 1, 5 * MINUTE_IN_SECONDS);

    global $wpdb;
    $name     = sanitize_text_field($_POST['name']);
    $email    = sanitize_email($_POST['email']);
    $phone    = sanitize_text_field($_POST['phone']);
    $country  = sanitize_text_field($_POST['country']);
    $company  = sanitize_text_field($_POST['company'] ?? '');
    $incoterm = sanitize_text_field($_POST['incoterm'] ?? '');
    $port     = sanitize_text_field($_POST['port'] ?? '');
    $product  = sanitize_text_field($_POST['product']);
    $qty      = sanitize_text_field($_POST['qty']);
    $message  = sanitize_textarea_field($_POST['message']);

    // Save to DB — FIX: qty bhi save hoga ab
    $inserted = $wpdb->insert($wpdb->prefix . 'sgclabs_inquiries', compact('name','email','phone','country','company','incoterm','port','product','qty','message'));

    // Bug 4 fix (v6.9.3): also push into unified inquiry store so AI Reply Center,
    // Import/Export, and any other consumer can see this submission. The legacy
    // DB table write above is retained for backward compatibility with reports
    // that still query it directly.
    if ( class_exists( 'SGCLabs_Inquiry_Store' ) ) {
        SGCLabs_Inquiry_Store::add( [
            'fire_hook'     => false, // hook already fired above with db_id payload
            'source'        => 'shortcode',
            'status'        => 'new',
            'name'          => $name,
            'email'         => $email,
            'phone'         => $phone,
            'country'       => $country,
            'company'       => $company,
            'subject'       => 'Product Inquiry: ' . $product,
            'message'       => $message,
            'product_title' => $product,
            'page_url'      => isset( $_SERVER['HTTP_REFERER'] )
                ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
            'meta'          => [
                'incoterm' => $incoterm,
                'port'     => $port,
                'qty'      => $qty,
            ],
        ] );
    }

    // v7.3.1: Fire unified hook — triggers automation, CRM sync, follow-up scheduling
    $inq_db_id = $inserted ? $wpdb->insert_id : 0;
    do_action( 'sgclabs_inquiry_received', [
        'db_id'   => $inq_db_id,
        'source'  => 'shortcode',
        'status'  => 'new',
        'name'    => $name,
        'email'   => $email,
        'phone'   => $phone,
        'country' => $country,
        'company' => $company,
        'subject' => 'Product Inquiry: ' . $product,
        'message' => $message,
        'meta'    => [ 'incoterm' => $incoterm, 'port' => $port, 'qty' => $qty ],
        'created_at' => time(),
    ] );
    $admin_email  = get_option('sgclabs_quote_notify_email', get_option('admin_email'));
    $product_safe = preg_replace("/[\r\n\t]/", " ", $product);
    $subject      = "New Product Inquiry: {$product_safe} — SGC LABS®";
    $name_s       = wp_strip_all_tags($name);
    $email_s      = wp_strip_all_tags($email);
    $phone_s      = wp_strip_all_tags($phone);
    $country_s    = wp_strip_all_tags($country);

    $ref_inq      = 'SGCI-' . strtoupper(substr(md5(time() . $email), 0, 6));
    $date_s       = current_time('d M Y, h:i A');

    $admin_headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: SGC Labs Team <info@labsglass.com>',
        'Reply-To: ' . $name_s . ' <' . sanitize_email($email) . '>',
    ];
    $admin_body = '<html><body style="font-family:Arial,sans-serif;font-size:14px;color:#333;">
<h2 style="color:#1a1a2e;">New Product Inquiry — SGC LABS®</h2>
<p><strong>Ref:</strong> ' . esc_html($ref_inq) . ' &nbsp;|&nbsp; <strong>Date:</strong> ' . esc_html($date_s) . '</p>
<table cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;max-width:500px;">
<tr style="background:#f8fafc;"><td style="border:1px solid #e2e8f0;color:#64748b;">Name</td><td style="border:1px solid #e2e8f0;font-weight:600;">' . esc_html($name_s) . '</td></tr>
<tr><td style="border:1px solid #e2e8f0;color:#64748b;">Email</td><td style="border:1px solid #e2e8f0;"><a href="mailto:' . esc_html($email_s) . '">' . esc_html($email_s) . '</a></td></tr>
<tr style="background:#f8fafc;"><td style="border:1px solid #e2e8f0;color:#64748b;">Phone</td><td style="border:1px solid #e2e8f0;">' . esc_html($phone_s) . '</td></tr>
<tr><td style="border:1px solid #e2e8f0;color:#64748b;">Company</td><td style="border:1px solid #e2e8f0;">' . esc_html($company) . '</td></tr>
<tr style="background:#f8fafc;"><td style="border:1px solid #e2e8f0;color:#64748b;">Incoterm</td><td style="border:1px solid #e2e8f0;">' . esc_html($incoterm) . ' | Port: ' . esc_html($port) . '</td></tr>
<tr><td style="border:1px solid #e2e8f0;color:#64748b;">Country</td><td style="border:1px solid #e2e8f0;">' . esc_html($country_s) . '</td></tr>
<tr style="background:#f8fafc;"><td style="border:1px solid #e2e8f0;color:#64748b;">Product</td><td style="border:1px solid #e2e8f0;font-weight:700;">' . esc_html($product) . '</td></tr>
<tr><td style="border:1px solid #e2e8f0;color:#64748b;">Quantity</td><td style="border:1px solid #e2e8f0;">' . esc_html($qty) . '</td></tr>
</table>
' . ($message ? '<p><strong>Message:</strong><br>' . nl2br(esc_html($message)) . '</p>' : '') . '
<p style="margin-top:20px;"><a href="mailto:' . esc_html($email_s) . '" style="padding:10px 20px;background:#27ae60;color:#fff;text-decoration:none;border-radius:6px;font-weight:700;">Reply to ' . esc_html($name_s) . ' →</a></p>
<p style="font-size:11px;color:#94a3b8;">SGC LABS® Site Manager Pro | <a href="' . admin_url('admin.php?page=sgclabs-quotes-inquiries&tab=inquiries') . '">View All Inquiries</a></p>
</body></html>';

    wp_mail($admin_email, $subject, $admin_body, $admin_headers);

    // ── Auto-reply to buyer ──
    if ($email) {
        $buyer_headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: SGC Labs Team <info@labsglass.com>',
            'Reply-To: Sales Team <sales@labsglass.com>',
            'Cc: info@labsglass.com',
        ];
        $buyer_subject = "We received your inquiry — SGC LABS® [{$ref_inq}]";
        $name_esc      = esc_html($name_s);
        $product_esc   = esc_html($product);
        $ref_esc       = esc_html($ref_inq);
        $email_esc     = esc_html($email_s);

        $buyer_body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:32px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:12px;overflow:hidden;">
  <tr><td style="background:linear-gradient(135deg,#1a1a2e,#16213e);padding:30px 36px;text-align:center;">
    <p style="margin:0 0 4px;font-size:24px;font-weight:900;color:#27ae60;letter-spacing:2px;">SGC LABS®</p>
    <p style="margin:0;font-size:12px;color:rgba(255,255,255,.6);">Scientific Glassware Manufacturer · Since 2003</p>
  </td></tr>
  <tr><td style="background:linear-gradient(90deg,#27ae60,#2ecc71);height:4px;"></td></tr>
  <tr><td style="padding:36px 36px 28px;">
    <p style="font-size:20px;font-weight:700;color:#1a1a2e;margin:0 0 12px;">Thank you, ' . $name_esc . '! 🙏</p>
    <p style="font-size:14px;color:#475569;line-height:1.8;margin:0 0 24px;">
      We have received your product inquiry for <strong style="color:#1a1a2e;">' . $product_esc . '</strong>.<br>
      Our sales team will respond within <strong style="color:#27ae60;font-size:15px;">24–48 hours</strong>.
    </p>
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0fdf4;border:1.5px solid #86efac;border-radius:10px;margin-bottom:24px;">
      <tr><td style="padding:18px 22px;">
        <p style="margin:0 0 4px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.1em;font-weight:700;">Reference Number</p>
        <p style="margin:0;font-size:24px;font-weight:900;color:#166534;letter-spacing:2px;font-family:monospace;">' . $ref_esc . '</p>
        <p style="margin:6px 0 0;font-size:12px;color:#64748b;">📌 Please quote this in any follow-up emails or calls.</p>
      </td></tr>
    </table>
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:10px;margin-bottom:24px;">
      <tr><td style="padding:18px 22px;">
        <p style="margin:0 0 12px;font-size:12px;font-weight:800;color:#1e40af;text-transform:uppercase;letter-spacing:.05em;">What happens next?</p>
        <p style="margin:0 0 6px;font-size:13px;color:#1e3a8a;">✅ &nbsp;Inquiry logged and assigned to our sales team</p>
        <p style="margin:0 0 6px;font-size:13px;color:#1e3a8a;">🔍 &nbsp;Requirements and pricing reviewed</p>
        <p style="margin:0;font-size:13px;color:#1e3a8a;">📧 &nbsp;Detailed quote sent to <strong>' . $email_esc . '</strong> within <strong>24–48 hours</strong></p>
      </td></tr>
    </table>
    <p style="font-size:14px;color:#64748b;margin:0 0 14px;"><strong style="color:#1a1a2e;">Need faster response?</strong></p>
    <table cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:8px;">
      <tr>
        <td><a href="tel:+919996416935" style="display:inline-block;padding:10px 16px;background:#1a1a2e;color:#fff;text-decoration:none;border-radius:6px;font-size:13px;font-weight:700;">📞 Call</a></td>
        <td><a href="https://wa.me/919996416935?text=Hi%2C%20ref%20' . $ref_esc . '" style="display:inline-block;padding:10px 16px;background:#25d366;color:#fff;text-decoration:none;border-radius:6px;font-size:13px;font-weight:700;">💬 WhatsApp</a></td>
        <td><a href="mailto:sales@labsglass.com?subject=Re:%20' . rawurlencode($ref_inq) . '" style="display:inline-block;padding:10px 16px;background:#27ae60;color:#fff;text-decoration:none;border-radius:6px;font-size:13px;font-weight:700;">✉️ Email</a></td>
      </tr>
    </table>
  </td></tr>
  <tr><td style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:20px 36px;text-align:center;">
    <p style="margin:0 0 4px;font-size:13px;color:#1a1a2e;font-weight:800;">SGC LABS®</p>
    <p style="margin:0 0 4px;font-size:12px;color:#64748b;">Ambala Cantt, Haryana 133001, India</p>
    <p style="margin:8px 0;font-size:12px;"><a href="https://labsglass.com" style="color:#27ae60;text-decoration:none;font-weight:700;">labsglass.com</a> &nbsp;·&nbsp; <a href="tel:+919996416935" style="color:#64748b;text-decoration:none;">+91 999 641 6935</a></p>
    <p style="margin:10px 0 0;font-size:11px;color:#cbd5e1;">Automated confirmation — reply to sales@labsglass.com with ref <strong>' . $ref_esc . '</strong></p>
  </td></tr>
</table></td></tr></table>
</body></html>';

        wp_mail($email, $buyer_subject, $buyer_body, $buyer_headers);
    }

    if ($inserted) wp_send_json_success();
    else wp_send_json_error('DB error');
}

// Admin page to view inquiries
function sgclabs_inquiries_page() {
    global $wpdb;

    // v7.3.1: Pipeline status update via AJAX dropdown
    if (isset($_POST['update_inq_status']) && wp_verify_nonce($_POST['_wpnonce'], 'sgclabs_inquiry_action')) {
        $wpdb->update(
            $wpdb->prefix . 'sgclabs_inquiries',
            ['status' => sanitize_text_field($_POST['new_status'] ?? 'read')],
            ['id' => intval($_POST['update_inq_status'])]
        );
    }
    // Legacy: mark read button still works
    if (isset($_POST['mark_read']) && wp_verify_nonce($_POST['_wpnonce'], 'sgclabs_inquiry_action')) {
        $wpdb->update($wpdb->prefix . 'sgclabs_inquiries', ['status' => 'read'], ['id' => intval($_POST['mark_read'] ?? 0)]);
    }

    $pipeline_statuses = [
        'all'      => ['label' => 'All',      'color' => '#64748b'],
        'new'      => ['label' => 'New',      'color' => '#e74c3c'],
        'read'     => ['label' => 'Read',     'color' => '#3498db'],
        'quoted'   => ['label' => 'Quoted',   'color' => '#f39c12'],
        'pi_sent'  => ['label' => 'PI Sent',  'color' => '#8e44ad'],
        'won'      => ['label' => 'Won ✓',    'color' => '#27ae60'],
        'lost'     => ['label' => 'Lost',     'color' => '#95a5a6'],
    ];

    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
    $where = $status_filter !== 'all' ? $wpdb->prepare("WHERE status = %s", $status_filter) : '';
    $inquiries = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sgclabs_inquiries $where ORDER BY created_at DESC");
    $new_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_inquiries WHERE status='new'");

    // Count per status for tabs
    $counts = [];
    foreach (array_keys($pipeline_statuses) as $s) {
        if ($s === 'all') { $counts['all'] = count((array)$wpdb->get_results("SELECT id FROM {$wpdb->prefix}sgclabs_inquiries")); continue; }
        $counts[$s] = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_inquiries WHERE status=%s", $s));
    }
    ?>
    <div class="wrap sgclabs-wrap">
<?php sgclabs_module_control_bar(["module_id"=>"inquiry-form","module_name"=>"Inquiry Form","module_icon"=>"📩","option_key"=>"sgclabs_inquiry_form_active","default"=>"1","description"=>"Product inquiry form with email notifications"]); ?>
        <h1>📩 Product Inquiries <span class="sgclabs-badge-red"><?php echo intval($new_count); ?> New</span></h1>

        <div class="sgclabs-section">
            <h3>📋 Shortcode — Add Inquiry Form to Any Page:</h3>
            <code style="background:#f0f0f0;padding:8px 15px;border-radius:5px;font-size:14px;">[sgclabs_inquiry_form]</code>
            &nbsp;&nbsp;
            <code style="background:#f0f0f0;padding:8px 15px;border-radius:5px;font-size:14px;">[sgclabs_inquiry_form product="Borosilicate Beaker"]</code>
            <p class="description">Kisi bhi page mein paste karo — form appear ho jayega!</p>
        </div>

        <div class="sgclabs-section">
            <!-- Pipeline status tabs -->
            <div style="margin-bottom:15px;display:flex;gap:6px;flex-wrap:wrap;">
                <?php foreach ($pipeline_statuses as $s => $meta): ?>
                <a href="?page=sgclabs-quotes-inquiries&tab=inquiries&status=<?php echo esc_attr($s); ?>"
                   style="padding:5px 12px;border-radius:20px;text-decoration:none;font-size:12px;font-weight:600;
                          background:<?php echo $status_filter===$s ? esc_attr($meta['color']) : '#f1f1f1'; ?>;
                          color:<?php echo $status_filter===$s ? '#fff' : '#555'; ?>;border:1px solid <?php echo esc_attr($meta['color']); ?>;">
                    <?php echo esc_html($meta['label']); ?>
                    <span style="background:rgba(0,0,0,.15);border-radius:10px;padding:1px 6px;font-size:11px;"><?php echo intval($counts[$s] ?? 0); ?></span>
                </a>
                <?php endforeach; ?>
            </div>

            <?php if (empty($inquiries)): ?>
                <p>Koi inquiry nahi mili is status mein.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="4%">#</th>
                            <th width="5%">INQ#</th>
                            <th width="13%">Name / Date</th>
                            <th width="16%">Contact</th>
                            <th width="10%">Country</th>
                            <th width="18%">Product</th>
                            <th width="14%">Message</th>
                            <th width="5%">Source</th>
                            <th width="10%">Status</th>
                            <th width="5%">FU</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inquiries as $inq):
                            $status = sanitize_text_field($inq->status ?? 'new');
                            $status_colors = ['new'=>'#e74c3c','read'=>'#3498db','quoted'=>'#f39c12','pi_sent'=>'#8e44ad','won'=>'#27ae60','lost'=>'#95a5a6'];
                            $sc = $status_colors[$status] ?? '#999';
                            $row_bg = $status === 'new' ? 'background:#fff9e6;font-weight:bold;' : ($status === 'won' ? 'background:#f0fff4;' : '');
                        ?>
                            <tr style="<?php echo esc_attr($row_bg); ?>">
                                <td><?php echo intval($inq->id); ?></td>
                                <td style="font-size:10px;color:#666;font-family:monospace;"><?php echo esc_html($inq->inq_number ?? '—'); ?></td>
                                <td><?php echo esc_html($inq->name); ?><br><small style="color:#888;"><?php echo date('d M Y', strtotime($inq->created_at)); ?></small></td>
                                <td>
                                    <a href="mailto:<?php echo esc_attr($inq->email); ?>"><?php echo esc_html($inq->email); ?></a><br>
                                    <?php if ($inq->phone): ?>
                                        <a href="https://api.whatsapp.com/send/?phone=<?php echo preg_replace('/[^0-9]/','',$inq->phone); ?>" target="_blank">💬 <?php echo esc_html($inq->phone); ?></a>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($inq->country); ?></td>
                                <td style="font-size:12px;"><?php echo esc_html(substr($inq->product ?? '', 0, 60)); ?></td>
                                <td style="font-size:12px;"><?php echo esc_html(substr($inq->message ?? '', 0, 60)); ?><?php echo strlen($inq->message ?? '') > 60 ? '…' : ''; ?></td>
                                <td><span style="font-size:10px;background:#eef;padding:2px 5px;border-radius:3px;"><?php echo esc_html($inq->source_type ?? 'quote'); ?></span></td>
                                <td>
                                    <!-- v7.3.1: Pipeline status dropdown -->
                                    <form method="post" style="margin:0;">
                                        <?php wp_nonce_field('sgclabs_inquiry_action'); ?>
                                        <input type="hidden" name="update_inq_status" value="<?php echo intval($inq->id); ?>">
                                        <select name="new_status" onchange="this.form.submit()"
                                                style="font-size:11px;padding:2px 4px;border-radius:4px;border:1.5px solid <?php echo esc_attr($sc); ?>;color:<?php echo esc_attr($sc); ?>;font-weight:600;background:#fff;max-width:80px;">
                                            <?php foreach ($pipeline_statuses as $s => $m): if ($s === 'all') continue; ?>
                                            <option value="<?php echo esc_attr($s); ?>" <?php selected($status, $s); ?>><?php echo esc_html($m['label']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                                <td style="text-align:center;">
                                    <?php if (!empty($inq->follow_up_sent) && $inq->follow_up_sent): ?>
                                        <span title="Follow-up sent <?php echo esc_attr($inq->follow_up_at ?? ''); ?>" style="color:#27ae60;font-size:14px;">✉️</span>
                                    <?php else: ?>
                                        <span style="color:#ccc;font-size:12px;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
