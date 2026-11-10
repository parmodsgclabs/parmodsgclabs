<?php
if (!defined('ABSPATH')) exit;

// ============================================================
// EXTRA FEATURES MODULE
// 1. WhatsApp Floating Button
// 2. Popup Lead Capture Form
// 3. Currency Switcher (₹/$)
// 4. Live Chat Widget (removed)
// ============================================================

// ============================================================
// WOOCOMMERCE DEFAULT CURRENCY — SET TO USD
// ============================================================
add_filter('woocommerce_currency', 'sgclabs_set_default_currency');
function sgclabs_set_default_currency($currency) {
    $override = get_option('sgclabs_wc_currency_override', 'USD');
    if ($override) return $override;
    return $currency;
}

add_filter('woocommerce_currency_symbol', 'sgclabs_set_currency_symbol', 10, 2);
function sgclabs_set_currency_symbol($symbol, $currency) {
    $symbols = [
        'USD' => '$', 'INR' => '₹', 'EUR' => '€',
        'GBP' => '£', 'AED' => 'د.إ', 'SAR' => '﷼',
        'JPY' => '¥', 'CNY' => '¥', 'KRW' => '₩',
        'BRL' => 'R$', 'RUB' => '₽', 'CAD' => 'CA$',
        'AUD' => 'A$', 'SGD' => 'S$', 'CHF' => 'CHF',
    ];
    $override = get_option('sgclabs_wc_currency_override', 'USD');
    return $symbols[$override] ?? $symbol;
}

// ============================================================
// SAVE SETTINGS ---
add_action('admin_post_sgclabs_save_extras', 'sgclabs_save_extras');
add_action('wp_ajax_sgclabs_save_extras',   'sgclabs_save_extras_ajax');

/**
 * AJAX handler for saving extras settings (called from inline JS in admin pages).
 * Handles partial saves (e.g. only indexnow_active) from quick-save buttons.
 */
function sgclabs_save_extras_ajax() {
    check_ajax_referer( 'sgclabs_extras_nonce', '_wpnonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $allowed_bool = [
        'sgclabs_indexnow_active', 'sgclabs_wa_active', 'sgclabs_popup_active',
        'sgclabs_currency_active', 'sgclabs_chat_active', 'sgclabs_aichat_active',
        'sgclabs_hide_woo_related',
    ];
    $allowed_text = [
        'sgclabs_wa_number', 'sgclabs_wa_message', 'sgclabs_wa_position',
        'sgclabs_popup_title', 'sgclabs_popup_delay', 'sgclabs_popup_offer',
        'sgclabs_currency_default', 'sgclabs_wc_currency_override',
        'sgclabs_aichat_mode', 'sgclabs_tawkto_id',
    ];

    foreach ( $allowed_bool as $key ) {
        if ( array_key_exists( $key, $_POST ) ) {
            update_option( $key, $_POST[ $key ] === '1' ? '1' : '0' );
        }
    }
    foreach ( $allowed_text as $key ) {
        if ( isset( $_POST[ $key ] ) ) {
            update_option( $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
        }
    }

    wp_send_json_success( [ 'message' => 'Saved.' ] );
}

function sgclabs_save_extras() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'sgclabs_extras_nonce')) wp_die('Unauthorized');
    $fields = [
        'sgclabs_wa_active','sgclabs_wa_number','sgclabs_wa_message','sgclabs_wa_position',
        'sgclabs_popup_active','sgclabs_popup_title','sgclabs_popup_delay','sgclabs_popup_offer',
        'sgclabs_currency_active','sgclabs_currency_default','sgclabs_wc_currency_override',
        'sgclabs_chat_active', 'sgclabs_aichat_active', 'sgclabs_aichat_mode', 'sgclabs_tawkto_id',
        'sgclabs_hide_woo_related',
        'sgclabs_ga4_id', 'sgclabs_ga4_exclude_admin',  // GA4
    ];
    $bool_fields = ['sgclabs_wa_active','sgclabs_popup_active','sgclabs_currency_active','sgclabs_chat_active','sgclabs_aichat_active','sgclabs_hide_woo_related','sgclabs_ga4_exclude_admin'];
    $api_key_fields = []; // no secret keys are saved through this form (AI keys use the AI Center)

    foreach ($fields as $f) {
        if (!isset($_POST[$f])) {
            if (in_array($f, $bool_fields)) {
                update_option($f, '0'); // unchecked checkbox = '0'
            }
            continue;
        }
        // Boolean fields: whitelist to '1' only (checkbox value can't be trusted)
        if (in_array($f, $bool_fields)) {
            update_option($f, '1');
            continue;
        }
        if (in_array($f, $api_key_fields)) {
            $raw = sanitize_text_field($_POST[$f]);
            $is_masked = strpos((string)$raw, '...') !== false;
            if (!empty($raw) && !$is_masked) {
                sgclabs_save_api_key($f, $raw);
            }
            continue;
        }
        update_option($f, sanitize_text_field($_POST[$f]));
    }
    wp_redirect(admin_url('admin.php?page=sgclabs-site-tools-hub&tab=extras&saved=1'));
    exit;
}

// ============================================================
// 1. WHATSAPP FLOATING BUTTON
// ============================================================
add_action('wp_footer', 'sgclabs_whatsapp_button');
function sgclabs_whatsapp_button() {
    if (get_option('sgclabs_wa_active', '1') !== '1') return;
    $number  = preg_replace('/[^0-9]/', '', get_option('sgclabs_wa_number', '919996416935')); // FIX: only digits allowed
    $message = sanitize_text_field(get_option('sgclabs_wa_message', 'Hello SGC LABS! I need a quote for laboratory glassware.')); // FIX: sanitized
    $pos     = get_option('sgclabs_wa_position', 'right');

    // Auto-add product name to message if on product page
    $msg = $message;
    if (function_exists('is_product') && is_product()) {
        $product = function_exists('wc_get_product') ? wc_get_product(get_the_ID()) : null;
        if ($product) $msg = "Hello SGC LABS! I am interested in: " . $product->get_name() . ". Please send me a quote.";
    }

    $side = $pos === 'left' ? 'left:20px' : 'right:20px';
    $enc  = urlencode($msg);
    echo "
    <style>
    .sgclabs-wa-btn { position:fixed; bottom:20px; {$side}; z-index:9996; background:#25D366; color:white; border-radius:50px; padding:12px 22px; font-size:15px; font-weight:600; text-decoration:none; box-shadow:0 4px 15px rgba(37,211,102,0.4); display:flex; align-items:center; gap:8px; transition:transform 0.2s,box-shadow 0.2s; font-family:sans-serif; }
    .sgclabs-wa-btn:hover { transform:scale(1.05); box-shadow:0 6px 20px rgba(37,211,102,0.6); color:white; }
    .sgclabs-wa-btn svg { width:22px; height:22px; fill:white; flex-shrink:0; }
    @media(max-width:600px){ .sgclabs-wa-btn span { display:none; } .sgclabs-wa-btn { padding:14px; border-radius:50%; } }
    </style>
    <a href='https://api.whatsapp.com/send/?phone={$number}&text={$enc}' target='_blank' class='sgclabs-wa-btn' aria-label='Chat on WhatsApp'>
        <svg viewBox='0 0 24 24' xmlns='http://www.w3.org/2000/svg'><path d='M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z'/></svg>
        <span>Chat on WhatsApp</span>
    </a>";
}

// ============================================================
// 2. POPUP LEAD CAPTURE FORM
// ============================================================
add_action('wp_footer', 'sgclabs_popup_form');
function sgclabs_popup_form() {
    if (get_option('sgclabs_popup_active', '0') !== '1') return;
    $title = get_option('sgclabs_popup_title', '🎁 Get Exclusive Quote from SGC LABS®');
    $delay = get_option('sgclabs_popup_delay', '5');
    $offer = get_option('sgclabs_popup_offer', 'Fill the form below and get a special price quote within 24 hours!');
    echo "
    <style>
    .sgclabs-popup-overlay { display:none; position:fixed; top:0;left:0;width:100%;height:100%; background:rgba(0,0,0,0.6); z-index:99999; align-items:center; justify-content:center; }
    .sgclabs-popup-overlay.active { display:flex; }
    .sgclabs-popup-box { background:white; border-radius:12px; padding:35px 30px; max-width:480px; width:90%; position:relative; box-shadow:0 20px 60px rgba(0,0,0,0.3); animation:popIn 0.3s ease; font-family:sans-serif; }
    @keyframes popIn { from{transform:scale(0.8);opacity:0} to{transform:scale(1);opacity:1} }
    .sgclabs-popup-close { position:absolute; top:12px; right:15px; font-size:22px; cursor:pointer; color:#999; background:none; border:none; }
    .sgclabs-popup-title { font-size:20px; font-weight:700; color:#1a1a2e; margin-bottom:8px; }
    .sgclabs-popup-offer { color:#666; font-size:14px; margin-bottom:20px; }
    .sgclabs-popup-form input, .sgclabs-popup-form select { width:100%; padding:10px 12px; border:1px solid #ddd; border-radius:6px; margin-bottom:12px; font-size:14px; box-sizing:border-box; }
    .sgclabs-popup-submit { background:#1a1a2e; color:white; border:none; padding:12px; width:100%; border-radius:6px; font-size:15px; cursor:pointer; font-weight:600; }
    .sgclabs-popup-submit:hover { background:#2d2d5e; }
    </style>
    <div class='sgclabs-popup-overlay' id='sgclabs-popup'>
        <div class='sgclabs-popup-box'>
            <button class='sgclabs-popup-close' onclick='closePopup()'>✕</button>
            <div class='sgclabs-popup-title'>" . esc_html($title) . "</div>
            <div class='sgclabs-popup-offer'>" . esc_html($offer) . "</div>
            <div class='sgclabs-popup-form' id='popup-form-wrap'>
                <input type='text' id='popup_name' placeholder='Your Name *'>
                <input type='email' id='popup_email' placeholder='Email Address *'>
                <input type='tel' id='popup_phone' placeholder='Phone / WhatsApp'>
                <input type='text' id='popup_product' placeholder='Product of Interest'>
                <select id='popup_country'>
                    <option value=''>Select Country</option>
                    <option>India</option><option>USA</option><option>UK</option><option>Germany</option>
                    <option>UAE</option><option>Saudi Arabia</option><option>Australia</option><option>Canada</option><option>Other</option>
                </select>
                <div id='popup-msg' style='display:none;padding:8px;border-radius:5px;margin-bottom:10px;'></div>
                <button class='sgclabs-popup-submit' onclick='submitPopup()'>📨 Get Free Quote</button>
            </div>
        </div>
    </div>
    <script>
    setTimeout(function() {
        if (!localStorage.getItem('sgclabs_popup_shown')) {
            document.getElementById('sgclabs-popup').classList.add('active');
            localStorage.setItem('sgclabs_popup_shown', '1');
        }
    }, " . intval($delay) * 1000 . ");
    function closePopup() { document.getElementById('sgclabs-popup').classList.remove('active'); }
    document.getElementById('sgclabs-popup').addEventListener('click', function(e){ if(e.target===this) closePopup(); });
    function submitPopup() {
        const name=document.getElementById('popup_name').value.trim();
        const email=document.getElementById('popup_email').value.trim();
        const msg=document.getElementById('popup-msg');
        if(!name||!email){msg.style.display='block';msg.style.background='#fee';msg.style.color='#c00';msg.textContent='Please fill Name and Email.';return;}
        jQuery.post('" . admin_url('admin-ajax.php') . "', {
            action:'sgclabs_submit_inquiry', nonce:'" . wp_create_nonce('sgclabs_inquiry') . "',
            name:name, email:email, phone:document.getElementById('popup_phone').value,
            country:document.getElementById('popup_country').value,
            product:document.getElementById('popup_product').value, message:'Via Popup'
        }, function(res){
            if(res.success){msg.style.display='block';msg.style.background='#efe';msg.style.color='#060';msg.textContent='✅ Thank you! We will contact you soon.';document.getElementById('popup-form-wrap').style.opacity='0.5';}
        });
    }
    </script>";
}

// ============================================================
// 3. CURRENCY SWITCHER
// ============================================================
add_action('wp_footer', 'sgclabs_currency_switcher');
function sgclabs_currency_switcher() {
    if (get_option('sgclabs_currency_active', '0') !== '1') return;
    $default = get_option('sgclabs_currency_default', 'INR');
    echo "
    <style>
    .sgclabs-currency-bar { position:fixed; top:50%; right:0; transform:translateY(-50%); z-index:9998; display:flex; flex-direction:column; gap:3px; }
    .sgclabs-currency-btn { background:#1a1a2e; color:white; border:none; padding:8px 10px; cursor:pointer; font-size:12px; font-weight:700; width:48px; text-align:center; border-radius:4px 0 0 4px; opacity:0.8; }
    .sgclabs-currency-btn:hover, .sgclabs-currency-btn.active { opacity:1; background:#27ae60; }
    </style>
    <div class='sgclabs-currency-bar'>
        <button class='sgclabs-currency-btn " . ($default==='INR'?'active':'') . "' onclick='switchCurrency(\"INR\",\"₹\",1)'>₹ INR</button>
        <button class='sgclabs-currency-btn " . ($default==='USD'?'active':'') . "' onclick='switchCurrency(\"USD\",\"$\",0.012)'>$ USD</button>
        <button class='sgclabs-currency-btn " . ($default==='EUR'?'active':'') . "' onclick='switchCurrency(\"EUR\",\"€\",0.011)'>€ EUR</button>
        <button class='sgclabs-currency-btn " . ($default==='GBP'?'active':'') . "' onclick='switchCurrency(\"GBP\",\"£\",0.0095)'>£ GBP</button>
        <button class='sgclabs-currency-btn " . ($default==='AED'?'active':'') . "' onclick='switchCurrency(\"AED\",\"د.إ\",0.044)'>AED</button>
    </div>
    <script>
    let basePrices = {};
    document.querySelectorAll('.price,.woocommerce-Price-amount').forEach(el => {
        const txt = el.textContent.replace(/[^0-9.]/g,'');
        if(txt) { el.dataset.inr = txt; }
    });
    function switchCurrency(code, symbol, rate) {
        document.querySelectorAll('.sgclabs-currency-btn').forEach(b=>b.classList.remove('active'));
        event.target.classList.add('active');
        document.querySelectorAll('.woocommerce-Price-amount bdi').forEach(el => {
            const inr = parseFloat(el.closest('[data-inr]')?.dataset.inr || el.parentElement?.dataset.inr || 0);
            if(inr) el.innerHTML = symbol + ' ' + (inr*rate).toFixed(2);
        });
        localStorage.setItem('sgclabs_currency', JSON.stringify({code,symbol,rate}));
    }
    // Restore saved currency
    const saved = localStorage.getItem('sgclabs_currency');
    if(saved) { const c=JSON.parse(saved); setTimeout(()=>{ if(c.code!=='INR') switchCurrency(c.code,c.symbol,c.rate); },500); }
    </script>";
}



// ============================================================
// 5. AI CHATBOT WIDGET — SGC LABS®
//    Modes: demo (scripted) | claude (AI) | tawkto (live)
// ============================================================

// ── Frontend widget output ────────────────────────────────
add_action( 'wp_footer', 'sgclabs_aichat_widget' );
function sgclabs_aichat_widget() {
    if ( get_option( 'sgclabs_aichat_active', '0' ) !== '1' ) return;

    $mode      = get_option( 'sgclabs_aichat_mode', 'demo' );
    $tawkto_id = get_option( 'sgclabs_tawkto_id', '' );
    $ajax_url  = admin_url( 'admin-ajax.php' );
    $nonce     = wp_create_nonce( 'sgclabs_chat_nonce' );

    // Tawk.to mode — just inject their script
    if ( $mode === 'tawkto' && ! empty( $tawkto_id ) ) {
        $parts = explode( '/', $tawkto_id );
        $prop  = esc_js( $parts[0] ?? '' );
        $wid   = esc_js( $parts[1] ?? 'default' );
        echo "<script>
var Tawk_API=Tawk_API||{},Tawk_LoadStart=new Date();
(function(){var s1=document.createElement('script'),s0=document.getElementsByTagName('script')[0];
s1.async=true;s1.src='https://embed.tawk.to/{$prop}/{$wid}';
s1.charset='UTF-8';s1.setAttribute('crossorigin','*');
s0.parentNode.insertBefore(s1,s0);})();
</script>";
        return;
    }

    // Demo + Claude modes — our own chat widget
    ?>
<style>
#sgc-chat-btn{position:fixed;bottom:84px;right:20px;z-index:9998;width:52px;height:52px;border-radius:50%;background:#1a1a2e;color:#fff;border:none;font-size:22px;cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.25);transition:transform .2s;}
#sgc-chat-btn:hover{transform:scale(1.1);}
#sgc-chat-box{position:fixed;bottom:148px;right:20px;z-index:99999;width:340px;max-width:calc(100vw - 32px);background:#fff;border-radius:14px;box-shadow:0 8px 32px rgba(0,0,0,.18);display:none;flex-direction:column;overflow:hidden;border:1px solid #e2e8f0;}
#sgc-chat-head{background:#1a1a2e;color:#fff;padding:14px 16px;font-size:14px;font-weight:600;display:flex;align-items:center;justify-content:space-between;}
#sgc-chat-head span{font-size:12px;font-weight:400;opacity:.7;}
#sgc-chat-close{background:none;border:none;color:#fff;font-size:20px;cursor:pointer;line-height:1;padding:0;}
#sgc-chat-msgs{padding:14px;height:280px;overflow-y:auto;display:flex;flex-direction:column;gap:10px;background:#f8f9fa;}
.sgc-msg{max-width:82%;padding:9px 13px;border-radius:10px;font-size:13px;line-height:1.5;}
.sgc-msg-bot{background:#fff;border:1px solid #e2e8f0;align-self:flex-start;border-radius:4px 10px 10px 10px;color:#333;}
.sgc-msg-user{background:#1a1a2e;color:#fff;align-self:flex-end;border-radius:10px 4px 10px 10px;}
.sgc-msg-typing{color:#999;font-size:12px;font-style:italic;align-self:flex-start;}
#sgc-chat-input-row{display:flex;gap:8px;padding:10px 12px;border-top:1px solid #e2e8f0;background:#fff;}
#sgc-chat-input{flex:1;border:1px solid #ddd;border-radius:20px;padding:8px 14px;font-size:13px;outline:none;}
#sgc-chat-input:focus{border-color:#1a1a2e;}
#sgc-chat-send{background:#1a1a2e;color:#fff;border:none;border-radius:50%;width:34px;height:34px;cursor:pointer;font-size:15px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
</style>

<button id="sgc-chat-btn" aria-label="Open Chat">💬</button>
<div id="sgc-chat-box" role="dialog" aria-label="SGC LABS Chat">
    <div id="sgc-chat-head">
        🧪 SGC LABS® Assistant
        <span>Typically replies instantly</span>
        <button id="sgc-chat-close" aria-label="Close">×</button>
    </div>
    <div id="sgc-chat-msgs">
        <div class="sgc-msg sgc-msg-bot">Hello! I'm SGC LABS® Assistant. How can I help you with laboratory glassware today? 🔬</div>
    </div>
    <div id="sgc-chat-input-row">
        <input type="text" id="sgc-chat-input" placeholder="Ask about our products..." maxlength="300" autocomplete="off">
        <button id="sgc-chat-send" aria-label="Send">➤</button>
    </div>
</div>

<script>
(function(){
    var btn=document.getElementById('sgc-chat-btn'),
        box=document.getElementById('sgc-chat-box'),
        close=document.getElementById('sgc-chat-close'),
        msgs=document.getElementById('sgc-chat-msgs'),
        inp=document.getElementById('sgc-chat-input'),
        send=document.getElementById('sgc-chat-send');
    var MODE='<?php echo esc_js($mode); ?>';
    var AJAX='<?php echo esc_js($ajax_url); ?>';
    var NONCE='<?php echo esc_js($nonce); ?>';
    var busy=false;

    btn.addEventListener('click',function(){
        box.style.display=box.style.display==='flex'?'none':'flex';
        if(box.style.display==='flex') inp.focus();
    });
    close.addEventListener('click',function(){ box.style.display='none'; });

    function addMsg(text,cls){
        var d=document.createElement('div');
        d.className='sgc-msg '+cls;
        d.textContent=text;
        msgs.appendChild(d);
        msgs.scrollTop=msgs.scrollHeight;
        return d;
    }

    function sendMsg(){
        var q=inp.value.trim();
        if(!q||busy) return;
        addMsg(q,'sgc-msg-user');
        inp.value=''; busy=true;
        var typing=addMsg('Typing…','sgc-msg sgc-msg-typing');

        if(MODE==='demo'){
            // Scripted demo replies
            var demos=[
                'We manufacture premium borosilicate laboratory glassware. What product are you looking for?',
                'Our products include beakers, flasks, burettes, pipettes, condensers, and custom glassware.',
                'For bulk orders and B2B pricing, please use our Get-a-Quote form or WhatsApp us directly.',
                'We export to 50+ countries. Delivery typically takes 7-14 business days internationally.',
                'All SGC LABS® glassware meets ISO and ASTM standards. Certificates available on request.',
            ];
            setTimeout(function(){
                typing.remove(); busy=false;
                addMsg(demos[Math.floor(Math.random()*demos.length)],'sgc-msg-bot');
            },900);
        } else {
            // Claude/AI mode — AJAX call
            var xhr=new XMLHttpRequest();
            xhr.open('POST',AJAX,true);
            xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
            xhr.onload=function(){
                typing.remove(); busy=false;
                try{
                    var r=JSON.parse(xhr.responseText);
                    addMsg(r.success&&r.data&&r.data.reply ? r.data.reply : 'Sorry, I could not process that right now. Please try WhatsApp.','sgc-msg-bot');
                }catch(e){
                    addMsg('Sorry, something went wrong. Please use our contact form.','sgc-msg-bot');
                }
            };
            xhr.onerror=function(){ typing.remove(); busy=false; addMsg('Network error. Please try again.','sgc-msg-bot'); };
            xhr.send('action=sgclabs_aichat_message&nonce='+encodeURIComponent(NONCE)+'&message='+encodeURIComponent(q));
        }
    }

    send.addEventListener('click',sendMsg);
    inp.addEventListener('keydown',function(e){ if(e.key==='Enter') sendMsg(); });
})();
</script>
    <?php
}

// ── AJAX handler — logged-in users ───────────────────────
add_action( 'wp_ajax_sgclabs_aichat_message', 'sgclabs_aichat_handle_message' );
// ── AJAX handler — logged-out users (public chatbot) ─────
add_action( 'wp_ajax_nopriv_sgclabs_aichat_message', 'sgclabs_aichat_handle_message' );

/**
 * Handle AI chatbot message — routes to active AI provider.
 */
function sgclabs_aichat_handle_message() {
    check_ajax_referer( 'sgclabs_chat_nonce', 'nonce' );

    $message = sanitize_text_field( wp_unslash( $_POST['message'] ?? '' ) );
    if ( empty( $message ) ) {
        wp_send_json_error( 'Empty message.' );
    }

    // Rate limit — max 30 msgs per IP per hour via transient
    $ip_key = 'sgc_chat_rl_' . md5( $_SERVER['REMOTE_ADDR'] ?? 'unknown' );
    $count  = (int) get_transient( $ip_key );
    if ( $count >= 30 ) {
        wp_send_json_error( [ 'reply' => 'Too many messages. Please try again later.' ] );
    }
    set_transient( $ip_key, $count + 1, HOUR_IN_SECONDS );

    // Check AI provider available
    if ( ! function_exists( 'sgclabs_ai_call' ) || ! function_exists( 'sgclabs_ai_any_configured' ) ) {
        wp_send_json_error( [ 'reply' => 'AI service not configured. Please contact us via WhatsApp.' ] );
    }
    if ( ! sgclabs_ai_any_configured() ) {
        wp_send_json_error( [ 'reply' => 'AI not configured. Please use our contact form or WhatsApp.' ] );
    }

    $system = 'You are SGC LABS® Assistant, a helpful support agent for SGC LABS® — a manufacturer of premium laboratory glassware based in Ambala, India (labsglass.com). Answer questions about products (beakers, flasks, burettes, condensers, pipettes), pricing, delivery, export, quality standards (ISO/ASTM), and ordering. Be concise (under 80 words). If unsure, direct users to WhatsApp or the quote form.';

    $reply = sgclabs_ai_call( $message, 150, $system );

    if ( is_wp_error( $reply ) || empty( $reply ) ) {
        wp_send_json_error( [ 'reply' => 'Sorry, I could not process that. Please WhatsApp us at +91-171-2600000.' ] );
    }

    wp_send_json_success( [ 'reply' => wp_kses_post( $reply ) ] );
}

// Tawk.to removed — not needed (SGC LABS v6.8.7)

// ============================================================
// ADMIN PAGE
// ============================================================
function sgclabs_extras_page() {
    $saved         = isset($_GET['saved']);
    $wa_active     = get_option('sgclabs_wa_active', '1');
    $wa_number     = get_option('sgclabs_wa_number', '919996416935');
    $wa_message    = get_option('sgclabs_wa_message', 'Hello SGC LABS! I need a quote for laboratory glassware.');
    $wa_position   = get_option('sgclabs_wa_position', 'right');
    $pop_active    = get_option('sgclabs_popup_active', '0');
    $pop_title     = get_option('sgclabs_popup_title', '🎁 Get Exclusive Quote from SGC LABS®');
    $pop_delay     = get_option('sgclabs_popup_delay', '5');
    $pop_offer     = get_option('sgclabs_popup_offer', 'Fill the form and get a special price quote within 24 hours!');
    $cur_active    = get_option('sgclabs_currency_active', '0');
    $cur_default   = get_option('sgclabs_currency_default', 'INR');
    $chat_active   = get_option('sgclabs_chat_active', '0');
    ?>
    <div class="wrap sgclabs-wrap">
        <h1>✨ Extra Features</h1>
        <?php if ($saved): ?><div class="notice notice-success"><p>✅ Settings saved!</p></div><?php endif; ?>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('sgclabs_extras_nonce'); ?>
            <input type="hidden" name="action" value="sgclabs_save_extras">

            <!-- WhatsApp -->
            <div class="sgclabs-section">
                <h2>💬 WhatsApp Floating Button</h2>
                <table class="form-table">
                    <tr><th>Active</th><td><label><input type="checkbox" name="sgclabs_wa_active" value="1" <?php checked($wa_active,'1'); ?>> Show WhatsApp button on site</label></td></tr>
                    <tr><th>WhatsApp Number</th><td><input type="text" name="sgclabs_wa_number" value="<?php echo esc_attr($wa_number); ?>" class="regular-text" placeholder="919996416935"><p class="description">Country code + number, no + sign. e.g. 919996416935</p></td></tr>
                    <tr><th>Default Message</th><td><input type="text" name="sgclabs_wa_message" value="<?php echo esc_attr($wa_message); ?>" class="large-text"><p class="description">On product pages, product name auto-adds!</p></td></tr>
                    <tr><th>Position</th><td>
                        <select name="sgclabs_wa_position">
                            <option value="right" <?php selected($wa_position,'right'); ?>>Bottom Right</option>
                            <option value="left" <?php selected($wa_position,'left'); ?>>Bottom Left</option>
                        </select>
                    </td></tr>
                </table>
            </div>

            <!-- Popup -->
            <div class="sgclabs-section">
                <h2>🎁 Popup Lead Capture Form</h2>
                <table class="form-table">
                    <tr><th>Active</th><td><label><input type="checkbox" name="sgclabs_popup_active" value="1" <?php checked($pop_active,'1'); ?>> Show popup on site</label></td></tr>
                    <tr><th>Popup Title</th><td><input type="text" name="sgclabs_popup_title" value="<?php echo esc_attr($pop_title); ?>" class="large-text"></td></tr>
                    <tr><th>Offer Text</th><td><input type="text" name="sgclabs_popup_offer" value="<?php echo esc_attr($pop_offer); ?>" class="large-text"></td></tr>
                    <tr><th>Delay (seconds)</th><td><input type="number" name="sgclabs_popup_delay" value="<?php echo esc_attr($pop_delay); ?>" min="1" max="60" style="width:80px;"><p class="description">Show after X seconds. 5 = recommended</p></td></tr>
                </table>
                <p class="description">📌 Popup leads save to <strong>Product Inquiries</strong> page automatically.</p>
            </div>

            <!-- Currency Settings -->
            <div class="sgclabs-section">
                <h2>💱 Currency Settings</h2>

                <!-- WooCommerce Actual Currency -->
                <div style="background:#fff3e0;border:2px solid #e67e22;border-radius:8px;padding:18px;margin-bottom:20px;">
                    <h3 style="margin:0 0 10px;color:#e67e22;">🏦 WooCommerce Default Currency (Actual)</h3>
                    <p style="margin:0 0 12px;font-size:13px;color:#555;">Yeh WooCommerce ki real currency change karega — sab product prices is currency mein show honge.</p>
                    <table class="form-table" style="margin:0;">
                        <tr>
                            <th style="width:180px;">Store Currency</th>
                            <td>
                                <select name="sgclabs_wc_currency_override" style="padding:8px 12px;font-size:14px;min-width:260px;border-radius:6px;">
                                    <?php
                                    $wc_cur = get_option('sgclabs_wc_currency_override','USD');
                                    $all_currencies = [
                                        'USD'=>'$ USD — US Dollar',     'INR'=>'₹ INR — Indian Rupee',
                                        'EUR'=>'€ EUR — Euro',          'GBP'=>'£ GBP — British Pound',
                                        'AED'=>'د.إ AED — UAE Dirham',  'SAR'=>'﷼ SAR — Saudi Riyal',
                                        'JPY'=>'¥ JPY — Japanese Yen',  'CNY'=>'¥ CNY — Chinese Yuan',
                                        'KRW'=>'₩ KRW — Korean Won',    'BRL'=>'R$ BRL — Brazilian Real',
                                        'RUB'=>'₽ RUB — Russian Ruble', 'CAD'=>'CA$ — Canadian Dollar',
                                        'AUD'=>'A$ — Australian Dollar','SGD'=>'S$ — Singapore Dollar',
                                        'CHF'=>'CHF — Swiss Franc',     'MXN'=>'MX$ — Mexican Peso',
                                        'IDR'=>'Rp IDR — Indonesian Rupiah','TRY'=>'₺ TRY — Turkish Lira',
                                    ];
                                    foreach ($all_currencies as $code => $label):
                                    ?>
                                        <option value="<?php echo esc_attr($code); ?>" <?php selected($wc_cur,$code); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description" style="margin-top:8px;">
                                    ✅ Abhi set hai: <strong style="color:#e67e22;"><?php echo esc_html($wc_cur); ?></strong>
                                    — Save karne ke baad WooCommerce automatically is currency mein switch ho jayega.
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Frontend Display Switcher -->
                <h3 style="margin-top:0;">🔀 Frontend Currency Switcher (Sidebar)</h3>
                <table class="form-table">
                    <tr>
                        <th>Active</th>
                        <td><label><input type="checkbox" name="sgclabs_currency_active" value="1" <?php checked($cur_active,'1'); ?>>
                        Site pe currency switcher sidebar show karo (₹/$/ etc.)</label></td>
                    </tr>
                    <tr>
                        <th>Sidebar Default</th>
                        <td>
                            <select name="sgclabs_currency_default">
                                <option value="USD" <?php selected($cur_default,'USD'); ?>>$ USD</option>
                                <option value="INR" <?php selected($cur_default,'INR'); ?>>₹ INR</option>
                                <option value="EUR" <?php selected($cur_default,'EUR'); ?>>€ EUR</option>
                                <option value="GBP" <?php selected($cur_default,'GBP'); ?>>£ GBP</option>
                                <option value="AED" <?php selected($cur_default,'AED'); ?>>AED</option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>




            <!-- ============================================================
                 AI CHATBOT — was referenced in save handler but UI was missing
            ============================================================ -->
            <div class="sgclabs-section" style="border-left:4px solid #03a84e;">
                <h2>🤖 AI Chatbot</h2>
                <p style="font-size:13px;color:#666;margin-bottom:16px;">
                    Site pe AI-powered chatbot lagao. Claude AI (Anthropic) ya Tawk.to live chat — dono supported hain.
                </p>
                <table class="form-table">
                    <tr>
                        <th>AI Chatbot Active</th>
                        <td>
                            <label>
                                <input type="checkbox" name="sgclabs_aichat_active" value="1"
                                    <?php checked( get_option('sgclabs_aichat_active','0'), '1' ); ?>>
                                Enable AI Chatbot on frontend
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Chatbot Mode</th>
                        <td>
                            <select name="sgclabs_aichat_mode" style="min-width:220px;">
                                <?php $mode = get_option('sgclabs_aichat_mode','demo'); ?>
                                <option value="demo"    <?php selected($mode,'demo'); ?>>Demo Mode (no API key needed)</option>
                                <option value="claude"  <?php selected($mode,'claude'); ?>>Claude AI (Anthropic)</option>
                                <option value="tawkto"  <?php selected($mode,'tawkto'); ?>>Tawk.to Live Chat</option>
                            </select>
                            <p class="description">
                                Demo = script-based replies. Claude = real AI replies (API key required in AI Control Center).
                                Tawk.to = live human chat widget.
                            </p>
                        </td>
                    </tr>
                    <tr id="sgc-tawkto-row" style="<?php echo $mode !== 'tawkto' ? 'display:none;' : ''; ?>">
                        <th>Tawk.to Property ID</th>
                        <td>
                            <input type="text" name="sgclabs_tawkto_id"
                                value="<?php echo esc_attr( get_option('sgclabs_tawkto_id','') ); ?>"
                                class="regular-text" placeholder="xxxxxxxxxxxxxxxxxxxxxxxx/default">
                            <p class="description">
                                Tawk.to dashboard → Administration → Chat Widget → Widget Code mein milega.
                                Format: <code>propertyId/widgetId</code>
                            </p>
                        </td>
                    </tr>
                </table>
                <script>
                document.querySelector('[name="sgclabs_aichat_mode"]').addEventListener('change', function(){
                    var row = document.getElementById('sgc-tawkto-row');
                    if (row) row.style.display = this.value === 'tawkto' ? '' : 'none';
                });
                </script>
            </div>

            <!-- Tawk.to standalone (legacy sgclabs_chat_active option) -->
            <div class="sgclabs-section">
                <h2>💬 Tawk.to Chat (Legacy)</h2>
                <p style="font-size:13px;color:#666;margin-bottom:12px;">
                    Pehle se Tawk.to use kar rahe hain? Yahan enable/disable karo. Naye setup ke liye upar AI Chatbot section use karo.
                </p>
                <table class="form-table">
                    <tr>
                        <th>Tawk.to Active</th>
                        <td>
                            <label>
                                <input type="checkbox" name="sgclabs_chat_active" value="1"
                                    <?php checked( get_option('sgclabs_chat_active','0'), '1' ); ?>>
                                Enable Tawk.to widget
                            </label>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- WooCommerce Related Products -->
            <div class="sgclabs-section">
                <h2>🛒 WooCommerce Related Products</h2>
                <table class="form-table">
                    <tr><th>Hide Related Products</th><td>
                        <label>
                            <input type="checkbox" name="sgclabs_hide_woo_related" value="1"
                                <?php checked( get_option('sgclabs_hide_woo_related','0'), '1' ); ?>>
                            Hide default "Related products" section on product pages
                        </label>
                        <p class="description">Removes WooCommerce's built-in related products grid. Your "You May Also Like" section stays unaffected.</p>
                    </td></tr>
                </table>
            </div>

            <!-- GA4 -->
            <div class="sgclabs-section">
                <h2>📊 Google Analytics 4 (GA4)</h2>
                <table class="form-table">
                    <tr>
                        <th>Measurement ID</th>
                        <td>
                            <input type="text" name="sgclabs_ga4_id"
                                value="<?php echo esc_attr( get_option('sgclabs_ga4_id','') ); ?>"
                                class="regular-text" placeholder="G-XXXXXXXXXX">
                            <p class="description">Google Analytics 4 Measurement ID. Find it in GA4 → Admin → Data Streams.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Exclude Admins</th>
                        <td>
                            <label>
                                <input type="checkbox" name="sgclabs_ga4_exclude_admin" value="1"
                                    <?php checked( get_option('sgclabs_ga4_exclude_admin','1'), '1' ); ?>>
                                Don't track logged-in admins (recommended)
                            </label>
                        </td>
                    </tr>
                    <?php if ( get_option('sgclabs_ga4_id','') ) : ?>
                    <tr>
                        <td colspan="2">
                            <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:10px 14px;font-size:12px;color:#166534;">
                                ✅ GA4 active — tracking ID: <strong><?php echo esc_html( get_option('sgclabs_ga4_id') ); ?></strong>
                            </div>
                        </td>
                    </tr>
                    <?php else : ?>
                    <tr>
                        <td colspan="2">
                            <div style="background:#fef9c3;border:1px solid #fde047;border-radius:8px;padding:10px 14px;font-size:12px;color:#854d0e;">
                                ⚠️ GA4 ID not set — analytics not tracking. Add your G-XXXXXXXXXX ID above.
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>

            <?php submit_button('💾 Save All Settings'); ?>
        </form>
    </div>
    <?php
}
