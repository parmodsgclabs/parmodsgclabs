<?php
/**
 * SGC LABS® Manager Pro — B2B Quote Shortcodes
 *
 * Registers:
 *   [sgclabs_quote_form]   — Full B2B quote form page (replaces old commented shortcode)
 *   [sgclabs_quote_button] — Button → redirects to /request-quote/ (overrides get-a-quote.php version)
 *   [sgclabs_inquiry_form] — Inquiry form with buyer type selector (overrides inquiry-form.php version)
 *
 * AJAX handler: sgc_quote_form_submit
 *   → Saves to DB (sgc_quote_submissions_log)
 *   → Fires sgclabs_inquiry_submitted → Smart CRM instant reply
 *   → Sends admin notification email
 *
 * @package SGCLabs_Manager_Pro
 * @since   7.4.3 (Built: 2026-05-10 11:46 IST)
 */

defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════════════════
// 1.  CSS + JS — enqueue once per page (not per shortcode call)
// ═══════════════════════════════════════════════════════════════════

// CSS is now output directly inside shortcode HTML (see sgclabs_b2b_quote_form_html)
// This guarantees it loads regardless of which stylesheet handle WPBakery enqueues

// ── Localise AJAX vars ──────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', function () {
    wp_localize_script( 'jquery', 'sgc_ajax', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'sgc_quote_nonce' ),
    ] );
} );

// ═══════════════════════════════════════════════════════════════════
// 2.  [sgclabs_quote_form]  — Full B2B Form
// ═══════════════════════════════════════════════════════════════════

// Remove old commented-out shortcode if already registered
remove_shortcode( 'sgclabs_quote_form' );
add_shortcode( 'sgclabs_quote_form', 'sgclabs_b2b_quote_form_shortcode' );

function sgclabs_b2b_quote_form_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'title'  => '📋 Request a B2B Quote',
        'show_hero'  => 'yes',
        'show_steps' => 'yes',
    ], $atts, 'sgclabs_quote_form' );

    ob_start();
    sgclabs_b2b_quote_form_html( $atts );
    // Inline JS once
    sgclabs_b2b_quote_js();
    return ob_get_clean();
}

// ═══════════════════════════════════════════════════════════════════
// 3.  [sgclabs_quote_button]  — Button → /request-quote/
//     Overrides get-a-quote.php version (loaded after it)
// ═══════════════════════════════════════════════════════════════════

remove_shortcode( 'sgclabs_quote_button' );
add_shortcode( 'sgclabs_quote_button', 'sgclabs_b2b_quote_button_shortcode' );

function sgclabs_b2b_quote_button_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'text'    => get_option( 'sgclabs_quote_btn_text', '💬 Get a Quote' ),
        'color'   => get_option( 'sgclabs_quote_btn_color', '#004a99' ),
        'tcolor'  => get_option( 'sgclabs_quote_btn_text_color', '#ffffff' ),
        'product' => '',
        'sku'     => '',
        'url'     => home_url( '/request-quote/' ),
    ], $atts, 'sgclabs_quote_button' );

    $url = esc_url( $atts['url'] );
    // Append product as query param so page can pre-fill
    if ( ! empty( $atts['product'] ) ) {
        $url = add_query_arg( 'product', urlencode( $atts['product'] ), $url );
    }
    if ( ! empty( $atts['sku'] ) ) {
        $url = add_query_arg( 'sku', urlencode( $atts['sku'] ), $url );
    }

    return sprintf(
        '<a href="%s" class="sgclabs-quote-btn" style="display:inline-block;background:%s;color:%s;padding:10px 22px;border-radius:6px;font-weight:700;text-decoration:none;font-size:0.95em;transition:opacity 0.2s;" onmouseover="this.style.opacity=\'0.85\'" onmouseout="this.style.opacity=\'1\'">%s</a>',
        $url,
        esc_attr( $atts['color'] ),
        esc_attr( $atts['tcolor'] ),
        esc_html( $atts['text'] )
    );
}

// ═══════════════════════════════════════════════════════════════════
// 4.  [sgclabs_inquiry_form]  — Inquiry form with buyer type
//     Overrides inquiry-form.php version
// ═══════════════════════════════════════════════════════════════════

remove_shortcode( 'sgclabs_inquiry_form' );
add_shortcode( 'sgclabs_inquiry_form', 'sgclabs_b2b_inquiry_shortcode' );

function sgclabs_b2b_inquiry_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'product' => '',
        'title'   => '📩 Product Inquiry & Quote',
        'show_hero'  => 'no',
        'show_steps' => 'no',
    ], $atts, 'sgclabs_inquiry_form' );

    // Pre-fill product from URL param (set by quote button)
    if ( empty( $atts['product'] ) && ! empty( $_GET['product'] ) ) {
        $atts['product'] = sanitize_text_field( wp_unslash( $_GET['product'] ) );
    }

    ob_start();
    sgclabs_b2b_quote_form_html( $atts );
    sgclabs_b2b_quote_js();
    return ob_get_clean();
}

// ═══════════════════════════════════════════════════════════════════
// 5.  Shared HTML renderer
// ═══════════════════════════════════════════════════════════════════

function sgclabs_b2b_quote_form_html( $atts ) {
    // CSS injected directly — works with WPBakery, Elementor, any builder
    static $css_printed = false;
    if ( ! $css_printed ) {
        $css_printed = true;
        echo '<style id="sgc-b2b-quote-css">' . sgclabs_b2b_quote_css() . '</style>';
    }
    $show_hero  = ( isset( $atts['show_hero'] )  && $atts['show_hero']  === 'yes' );
    $show_steps = ( isset( $atts['show_steps'] ) && $atts['show_steps'] === 'yes' );
    $prefill    = isset( $atts['product'] ) ? esc_attr( $atts['product'] ) : '';
    ?>
<div class="sgc-quote-wrap">

<?php if ( $show_hero ) : ?>
    <!-- Hero -->
    <div class="quote-hero">
        <div class="hero-left">
            <div class="hero-title"><?php echo esc_html( $atts['title'] ); ?></div>
            <p>Fill the form and our export team will respond with a detailed quotation within <strong>24–48 working hours</strong>.<br>
            <span style="font-size:0.88em;opacity:0.8;">⏱ Working days: Mon–Fri &nbsp;|&nbsp; Excluding weekends, national holidays &amp; gazetted leaves.</span></p>
        </div>
        <div class="hero-badges">
            <div class="hero-badge">✅ ISO 9001:2015</div>
            <div class="hero-badge">🌍 20+ Countries</div>
            <div class="hero-badge">🏭 Since 2003</div>
            <div class="hero-badge">📦 7000+ Products</div>
        </div>
    </div>
<?php endif; ?>

<?php if ( $show_steps ) : ?>
    <!-- Steps -->
    <div class="steps-bar">
        <div class="step-item"><div class="step-num">1</div><div class="step-label">Submit Requirements</div></div>
        <div class="step-item"><div class="step-num">2</div><div class="step-label">Instant Acknowledgement</div></div>
        <div class="step-item"><div class="step-num">3</div><div class="step-label">Quote in 24–48 Working Hours</div></div>
        <div class="step-item"><div class="step-num">4</div><div class="step-label">Confirm &amp; Place Order</div></div>
    </div>
<?php endif; ?>

    <!-- Buyer Type Selector -->
    <div class="buyer-section">
        <div class="bs-title">👤 Who are you? <span class="bs-sub-inline">— Select your buyer type</span></div>
        <div class="bs-sub">Help us personalize your quote. Select the option that best describes you.</div>
        <div class="buyer-grid">
            <?php
            $buyer_types = [
                [ 'type' => 'Student',               'icon' => '🎓', 'label' => 'Student',              'desc' => 'Academic research &amp; lab assignments',   'notice' => '🎓 Students get special pricing! Please mention your institution name and department.',  'company' => 'University / College Name' ],
                [ 'type' => 'Teacher / Professor',   'icon' => '👨‍🏫', 'label' => 'Teacher / Professor', 'desc' => 'Educational institution procurement',       'notice' => '👨‍🏫 Teachers get institutional pricing. Mention your subject and institution.',          'company' => 'School / College Name' ],
                [ 'type' => 'College / University',  'icon' => '🏫', 'label' => 'College / University', 'desc' => 'Bulk institutional procurement',            'notice' => '🏫 Institutional bulk orders welcome! Share your purchase order or indent.',             'company' => 'Institution / University Name' ],
                [ 'type' => 'Dealer / Distributor',  'icon' => '🏪', 'label' => 'Dealer / Distributor', 'desc' => 'Reseller &amp; regional distribution',      'notice' => '🏪 Dealers get exclusive trade pricing &amp; MOQ benefits. Mention your region.',      'company' => 'Business / Firm Name' ],
                [ 'type' => 'Manufacturer',          'icon' => '🏭', 'label' => 'Manufacturer',         'desc' => 'Industrial &amp; production use',           'notice' => '🏭 Industrial buyers get factory-direct pricing with OEM branding options.',            'company' => 'Company / Factory Name' ],
                [ 'type' => 'Research Lab',          'icon' => '🔬', 'label' => 'Research Lab',         'desc' => 'Professional scientific research',          'notice' => '🔬 Research labs get priority support &amp; certified documentation (CoA, MSDS).',     'company' => 'Lab / Institute Name' ],
                [ 'type' => 'Hospital / Pharma',     'icon' => '🏥', 'label' => 'Hospital / Pharma',    'desc' => 'Clinical &amp; pharmaceutical use',         'notice' => '🏥 We supply hospitals &amp; pharma with full quality documentation.',                'company' => 'Hospital / Company Name' ],
                [ 'type' => 'Glass Blower',          'icon' => '💨', 'label' => 'Glass Blower',         'desc' => 'Semi-finished &amp; OEM components',        'notice' => '💨 Glass blowers get semi-finished components &amp; OEM parts at best prices.',       'company' => 'Studio / Workshop Name' ],
            ];
            foreach ( $buyer_types as $bt ) : ?>
            <div class="bt-card"
                 data-type="<?php echo esc_attr( $bt['type'] ); ?>"
                 data-notice="<?php echo esc_attr( $bt['notice'] ); ?>"
                 data-company="<?php echo esc_attr( $bt['company'] ); ?>">
                <span class="bt-check">✓</span>
                <span class="bt-radio-dot"><span class="bt-radio-inner"></span></span>
                <span class="bt-icon"><?php echo $bt['icon']; ?></span>
                <div class="bt-label"><?php echo esc_html( $bt['label'] ); ?></div>
                <div class="bt-desc"><?php echo $bt['desc']; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="buyer-notice" id="sgc-buyer-notice"></div>
        <input type="hidden" id="sgc-buyer-type" value="">
    </div>

    <!-- Main Layout -->
    <div class="quote-layout">

        <!-- Form -->
        <div class="form-box" id="sgc-quote-form-box">
            <div id="sgc-form-inner">
                <h3>📝 Your Quote / Inquiry Details</h3>
                <form id="sgc-b2b-quote-form" novalidate>
                    <input type="hidden" name="buyer_type" id="sgc-buyer-type-field" value="Not Specified">

                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name <span class="req">*</span></label>
                            <input type="text" name="name" placeholder="Your full name" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address <span class="req">*</span></label>
                            <input type="email" name="email" placeholder="your@email.com" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Phone / WhatsApp</label>
                            <input type="tel" name="phone" placeholder="+1 234 567 8900">
                        </div>
                        <div class="form-group">
                            <label>Company / Institute</label>
                            <input type="text" name="company" placeholder="Your company name">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Country <span class="req">*</span></label>
                            <select name="country" required>
                                <option value="">-- Select Country --</option>
                                <?php
                                $countries = [ 'United States','United Kingdom','Germany','France','Australia','Canada','UAE','Saudi Arabia','Singapore','Malaysia','South Africa','Nigeria','Kenya','Brazil','Mexico','Japan','South Korea','China','India','Other' ];
                                foreach ( $countries as $c ) {
                                    echo '<option value="' . esc_attr( $c ) . '">' . esc_html( $c ) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Quantity Required</label>
                            <input type="text" name="qty" placeholder="e.g. 50 pcs, 1 lot, 500 units">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Product / Item Required <span class="req">*</span></label>
                        <input type="text" name="product" id="sgc-product-field"
                               value="<?php echo $prefill; ?>"
                               placeholder="e.g. Borosilicate Beaker 500ml, Amber Reagent Bottle..." required>
                    </div>

                    <div class="form-group">
                        <label>Query Type <span class="req">*</span></label>
                        <div class="query-type-grid">
                            <label><input type="radio" name="query_type" value="Quote Request" checked> 📋 Quote Request</label>
                            <label><input type="radio" name="query_type" value="Bulk / Export Order"> 🌍 Bulk / Export</label>
                            <label><input type="radio" name="query_type" value="Custom / OEM Order"> 🔧 Custom / OEM</label>
                            <label><input type="radio" name="query_type" value="Pre-Sale Question"> ❓ Pre-Sale Question</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Message / Requirements</label>
                        <textarea name="message" placeholder="Describe your requirements — specs, standards (ISO/DIN/ASTM), packaging, delivery terms..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Attach Drawing / Specs (optional)</label>
                        <div class="file-upload-area">
                            <input type="file" id="sgc-file-input" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.dwg">
                            <p>📎 Click to attach file (PDF, Image, DOC — max 5MB)</p>
                            <div class="file-name-display" id="sgc-file-name"></div>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn" id="sgc-submit-btn">📨 Send Quote Request</button>
                    <p class="form-security">🔒 Your information is secure and will only be used to process your quote.</p>

                    <div class="alert success" id="sgc-alert-success"></div>
                    <div class="alert error"   id="sgc-alert-error"></div>
                </form>
            </div>

            <!-- Success State -->
            <div id="sgc-success-state" style="display:none;text-align:center;padding:40px 30px;">
                <div style="font-size:3.5em;margin-bottom:16px;">✅</div>
                <h3 style="color:#1a6636;margin:0 0 12px;">Quote Request Received!</h3>
                <p style="color:#555;font-size:0.95em;line-height:1.7;">
                    Thank you! Our exports team will send you a detailed quotation <strong>within 24–48 working hours</strong>
                    (Mon–Fri, excluding weekends &amp; public holidays).<br><br>
                    For urgent queries, WhatsApp: <strong>+91 99964 16935</strong>
                </p>
                <a href="<?php echo esc_url( home_url( '/products/' ) ); ?>"
                   style="display:inline-block;margin-top:20px;background:#004a99;color:#fff;padding:12px 26px;border-radius:7px;text-decoration:none;font-weight:700;">
                    Browse Products →
                </a>
            </div>
        </div><!-- /.form-box -->

        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-card green">
                <h4>📞 Contact Us Directly</h4>
                <div class="contact-item"><span>📧</span><span><a href="mailto:info@labsglass.com">info@labsglass.com</a></span></div>
                <div class="contact-item"><span>📍</span><span>4/12/2 Gandhi Nagar, Ambala Cantt, Haryana — 133001, India</span></div>
                <div class="contact-item"><span>🕐</span><span>Mon–Fri: 9:00 AM – 6:00 PM IST</span></div>
                <a class="wa-btn" href="https://api.whatsapp.com/send/?phone=919996416935&text=Hello+SGC+LABS!+I+need+a+quote." target="_blank" rel="noopener">💬 WhatsApp: +91 99964 16935</a>
            </div>

            <div class="sidebar-card">
                <h4>🏆 Why SGC LABS®?</h4>
                <ul class="trust-list">
                    <li>✅ ISO 9001:2015 Certified</li>
                    <li>🌍 Exported to 20+ Countries</li>
                    <li>🏭 Factory Direct — No Middlemen</li>
                    <li>📦 7000+ Products in Stock</li>
                    <li>🔬 ASTM / ISO / DIN / USP Standards</li>
                    <li>🎨 OEM Branding &amp; Custom Packaging</li>
                    <li>💰 Competitive Factory Pricing</li>
                    <li>📅 Since 2003 — 20+ Years</li>
                </ul>
            </div>

            <div class="sidebar-card">
                <h4>📖 Download Catalogue</h4>
                <p class="cat-desc">Browse our full product catalogue 2025–26 before submitting your request.</p>
                <a href="<?php echo esc_url( home_url( '/wp-content/uploads/2025/11/SGC_LABS_2025-26.pdf' ) ); ?>"
                   target="_blank" rel="noopener" class="cat-btn">📥 Download PDF Catalogue</a>
            </div>
        </div><!-- /.sidebar -->

    </div><!-- /.quote-layout -->
</div><!-- /.sgc-quote-wrap -->
    <?php
}

// ═══════════════════════════════════════════════════════════════════
// 6.  Inline JS (printed once per page via flag)
// ═══════════════════════════════════════════════════════════════════

function sgclabs_b2b_quote_js() {
    static $printed = false;
    if ( $printed ) return;
    $printed = true;
    ?>
<script>
(function(){
function init(){
    console.log('🔧 SGC B2B Quote form initializing...');
    // Buyer Cards: Event Delegation (WP strips onclick attrs)
    var grid=document.querySelector('.sgc-quote-wrap .buyer-grid');
    console.log('Grid found:', !!grid);
    if(grid){
        grid.addEventListener('click',function(e){
            var card=e.target.closest('.bt-card');
            if(!card) return;
            grid.querySelectorAll('.bt-card').forEach(function(c){ c.classList.remove('active'); });
            card.classList.add('active');
            var type=card.getAttribute('data-type')||'';
            var notice=card.getAttribute('data-notice')||'';
            var company=card.getAttribute('data-company')||'';
            var h1=document.getElementById('sgc-buyer-type');
            var h2=document.getElementById('sgc-buyer-type-field');
            if(h1) h1.value=type;
            if(h2) h2.value=type;
            var nb=document.getElementById('sgc-buyer-notice');
            if(nb){ nb.textContent=notice; nb.classList.add('show'); }
            var ci=document.querySelector('.sgc-quote-wrap [name="company"]');
            if(ci){
                ci.placeholder=company;
                var grp=ci.closest('.form-group');
                if(grp){ var lbl=grp.querySelector('label'); if(lbl&&lbl.childNodes[0]) lbl.childNodes[0].nodeValue=company.replace(' Name','')+' '; }
            }
            var fb=document.getElementById('sgc-quote-form-box');
            if(fb) setTimeout(function(){ fb.scrollIntoView({behavior:'smooth',block:'start'}); },250);
        });
    }
    // File Upload
    var ua=document.querySelector('.sgc-quote-wrap .file-upload-area');
    var fi=document.getElementById('sgc-file-input');
    if(ua&&fi){
        ua.addEventListener('click',function(){ fi.click(); });
        fi.addEventListener('change',function(){
            var fn=document.getElementById('sgc-file-name');
            if(fn) fn.textContent=this.files[0]?'📎 '+this.files[0].name:'';
        });
    }
    // Pre-fill from URL param
    var params=new URLSearchParams(window.location.search);
    var pf=params.get('product');
    if(pf){
        var pfield=document.getElementById('sgc-product-field');
        if(pfield&&!pfield.value) pfield.value=decodeURIComponent(pf);
    }
    // Query Type: visual highlight
    var qls=document.querySelectorAll('.sgc-quote-wrap .query-type-grid label');
    function hlSelected(){
        qls.forEach(function(l){
            var r=l.querySelector('input[type="radio"]');
            if(!r) return;
            l.style.borderColor=r.checked?'#004a99':'';
            l.style.background=r.checked?'#e8f0ff':'';
            l.style.fontWeight=r.checked?'700':'';
        });
    }
    hlSelected();
    qls.forEach(function(l){ l.addEventListener('click',function(){ setTimeout(hlSelected,10); }); });
    // Form Submit
    var form=document.getElementById('sgc-b2b-quote-form');
    if(!form) return;
    form.addEventListener('submit',function(e){
        e.preventDefault();
        var btn=document.getElementById('sgc-submit-btn');
        var errEl=document.getElementById('sgc-alert-error');
        var name=form.querySelector('[name="name"]').value.trim();
        var email=form.querySelector('[name="email"]').value.trim();
        var country=form.querySelector('[name="country"]').value;
        var product=form.querySelector('[name="product"]').value.trim();
        errEl.style.display='none';
        if(!name||!email||!country||!product){
            errEl.style.cssText='display:block!important';
            errEl.textContent='⚠️ Please fill all required fields (Name, Email, Country, Product).';
            return;
        }
        if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){
            errEl.style.cssText='display:block!important';
            errEl.textContent='⚠️ Please enter a valid email address.';
            return;
        }
        btn.disabled=true; btn.textContent='⏳ Sending...';
        var btf=document.getElementById('sgc-buyer-type-field');
        var qc=form.querySelector('[name="query_type"]:checked');
        var fd=new FormData();
        fd.append('action','sgc_quote_form_submit');
        fd.append('nonce',(typeof sgc_ajax!=='undefined')?sgc_ajax.nonce:'');
        fd.append('name',name);
        fd.append('email',email);
        fd.append('phone',form.querySelector('[name="phone"]').value.trim());
        fd.append('company',form.querySelector('[name="company"]').value.trim());
        fd.append('country',country);
        fd.append('qty',form.querySelector('[name="qty"]').value.trim());
        fd.append('product',product);
        fd.append('message',form.querySelector('[name="message"]').value.trim());
        fd.append('query_type',qc?qc.value:'Quote Request');
        fd.append('buyer_type',btf?btf.value:'Not Specified');
        if(fi&&fi.files[0]) fd.append('attachment',fi.files[0]);
        var au=(typeof sgc_ajax!=='undefined')?sgc_ajax.ajax_url:'/wp-admin/admin-ajax.php';
        fetch(au,{method:'POST',body:fd})
        .then(function(r){ return r.json(); })
        .then(function(res){
            if(res&&res.success){
                document.getElementById('sgc-form-inner').style.display='none';
                var ss=document.getElementById('sgc-success-state');
                if(ss) ss.style.cssText='display:block!important;text-align:center;padding:40px 30px';
            } else {
                errEl.style.cssText='display:block!important';
                errEl.textContent=(res&&res.data)?res.data:'⚠️ Something went wrong. Please WhatsApp or email us.';
                btn.disabled=false; btn.textContent='📨 Send Quote Request';
            }
        })
        .catch(function(){
            errEl.style.cssText='display:block!important';
            errEl.textContent='⚠️ Network error. WhatsApp: +91 99964 16935 or info@labsglass.com';
            btn.disabled=false; btn.textContent='📨 Send Quote Request';
        });
    });
}
if(document.readyState==='loading'){ document.addEventListener('DOMContentLoaded',init); } else { init(); }
})();
</script>
    <?php
}

// ═══════════════════════════════════════════════════════════════════
// 7.  CSS string
// ═══════════════════════════════════════════════════════════════════

function sgclabs_b2b_quote_css() {
    return '
/* WPBakery + Theme Reset */
.sgc-quote-wrap,.sgc-quote-wrap *{box-sizing:border-box!important}
.vc_row .sgc-quote-wrap,.wpb_wrapper .sgc-quote-wrap,.vc_column-inner .sgc-quote-wrap{width:100%!important;float:none!important;clear:both!important}
.sgc-quote-wrap{font-family:\'Segoe UI\',Arial,sans-serif!important;max-width:100%!important;margin:0 0 40px!important;color:#333!important;float:none!important;clear:both!important;padding:0!important}
.sgc-quote-wrap div,.sgc-quote-wrap form,.sgc-quote-wrap input,.sgc-quote-wrap select,.sgc-quote-wrap textarea,.sgc-quote-wrap button,.sgc-quote-wrap a{float:none!important}
/* Hero */
.sgc-quote-wrap .quote-hero{background:linear-gradient(135deg,#004a99 0%,#1a2e4a 100%)!important;border-radius:12px!important;padding:28px 30px!important;margin-bottom:18px!important;display:flex!important;flex-wrap:wrap!important;align-items:center!important;gap:14px!important;width:100%!important}
.sgc-quote-wrap .hero-left{flex:1 1 240px!important;min-width:0!important}
.sgc-quote-wrap .hero-title{color:#fff!important;font-size:1.45em!important;font-weight:700!important;margin:0 0 8px!important;line-height:1.3!important;display:block!important;text-transform:none!important;letter-spacing:0!important}
.sgc-quote-wrap .hero-left p{color:rgba(255,255,255,.85)!important;margin:0!important;font-size:.88em!important;line-height:1.65!important}
.sgc-quote-wrap .hero-badges{display:flex!important;flex-direction:column!important;gap:5px!important;min-width:130px!important;flex-shrink:0!important}
.sgc-quote-wrap .hero-badge{background:rgba(255,255,255,.15)!important;border:1px solid rgba(255,255,255,.3)!important;border-radius:5px!important;padding:5px 9px!important;color:#fff!important;font-size:.76em!important;font-weight:600!important;text-align:center!important;display:block!important;list-style:none!important;margin:0!important}
/* Steps */
.sgc-quote-wrap .steps-bar{display:flex!important;flex-wrap:wrap!important;gap:8px!important;margin-bottom:18px!important;width:100%!important;padding:0!important;list-style:none!important}
.sgc-quote-wrap .step-item{background:#fff!important;border:1px solid #dde6f0!important;border-radius:7px!important;padding:11px 8px!important;text-align:center!important;flex:1 1 130px!important;min-width:0!important;list-style:none!important;margin:0!important}
.sgc-quote-wrap .step-num{width:26px!important;height:26px!important;background:#004a99!important;color:#fff!important;border-radius:50%!important;display:flex!important;align-items:center!important;justify-content:center!important;font-weight:700!important;font-size:.85em!important;margin:0 auto 5px!important}
.sgc-quote-wrap .step-label{font-size:.76em!important;color:#555!important;font-weight:600!important;line-height:1.35!important;display:block!important}
/* Buyer Section */
.sgc-quote-wrap .buyer-section{background:#fff!important;border-radius:10px!important;box-shadow:0 3px 10px rgba(0,0,0,.06)!important;border-top:4px solid #7c3aed!important;padding:18px 22px!important;margin-bottom:16px!important;width:100%!important}
.sgc-quote-wrap .bs-title{font-size:.98em!important;font-weight:700!important;color:#1a2e4a!important;margin:0 0 4px!important;display:block!important;text-transform:none!important}
.sgc-quote-wrap .bs-sub-inline{font-weight:400!important;color:#999!important;font-size:.87em!important}
.sgc-quote-wrap .bs-sub{font-size:.83em!important;color:#777!important;margin:0 0 13px!important;display:block!important}
.sgc-quote-wrap .buyer-grid{display:flex!important;flex-wrap:wrap!important;gap:8px!important;padding:0!important;margin:0!important;list-style:none!important}
.sgc-quote-wrap .bt-card{border:2px solid #e0e7f0!important;border-radius:9px!important;padding:11px 7px!important;text-align:center!important;cursor:pointer!important;background:#f8fafc!important;transition:all .2s!important;flex:1 1 88px!important;min-width:88px!important;max-width:125px!important;position:relative!important;user-select:none!important;-webkit-user-select:none!important;list-style:none!important;margin:0!important}
.sgc-quote-wrap .bt-card:hover{border-color:#7c3aed!important;background:#f5f0ff!important;transform:translateY(-2px)!important;box-shadow:0 4px 10px rgba(124,58,237,.14)!important}
.sgc-quote-wrap .bt-card.active{border-color:#7c3aed!important;background:#ede9fe!important;box-shadow:0 4px 12px rgba(124,58,237,.2)!important}
.sgc-quote-wrap .bt-check{display:none!important;position:absolute!important;top:5px!important;right:6px!important;background:#7c3aed!important;color:#fff!important;width:14px!important;height:14px!important;border-radius:50%!important;font-size:.6em!important;font-weight:700!important;line-height:14px!important;text-align:center!important}
.sgc-quote-wrap .bt-card.active .bt-check{display:block!important}
.sgc-quote-wrap .bt-radio-dot{display:block!important;width:15px!important;height:15px!important;border:2px solid #c0c8d8!important;border-radius:50%!important;margin:0 auto 6px!important;position:relative!important;background:#fff!important;transition:border-color .2s!important}
.sgc-quote-wrap .bt-radio-inner{display:block!important;width:6px!important;height:6px!important;border-radius:50%!important;background:transparent!important;position:absolute!important;top:50%!important;left:50%!important;transform:translate(-50%,-50%)!important;transition:background .2s!important}
.sgc-quote-wrap .bt-card.active .bt-radio-dot{border-color:#7c3aed!important;background:#f0ebff!important}
.sgc-quote-wrap .bt-card.active .bt-radio-inner{background:#7c3aed!important}
.sgc-quote-wrap .bt-icon{font-size:1.65em!important;margin-bottom:5px!important;display:block!important;line-height:1!important}
.sgc-quote-wrap .bt-label{font-weight:700!important;font-size:.76em!important;color:#1a2e4a!important;margin-bottom:2px!important;display:block!important;line-height:1.3!important}
.sgc-quote-wrap .bt-card.active .bt-label{color:#7c3aed!important}
.sgc-quote-wrap .bt-desc{font-size:.66em!important;color:#999!important;line-height:1.3!important;display:block!important}
.sgc-quote-wrap .buyer-notice{margin-top:11px!important;padding:9px 13px!important;background:#f0ebff!important;border-left:4px solid #7c3aed!important;border-radius:0 5px 5px 0!important;font-size:.84em!important;color:#4c1d95!important;display:none!important}
.sgc-quote-wrap .buyer-notice.show{display:block!important}
/* Main layout */
.sgc-quote-wrap .quote-layout{display:flex!important;flex-wrap:wrap!important;gap:16px!important;align-items:flex-start!important;width:100%!important}
.sgc-quote-wrap .form-box{background:#fff!important;border-radius:10px!important;box-shadow:0 3px 10px rgba(0,0,0,.06)!important;border-top:4px solid #004a99!important;padding:22px 24px!important;flex:1 1 380px!important;min-width:0!important;width:auto!important}
.sgc-quote-wrap .form-box h3{margin:0 0 16px!important;color:#1a2e4a!important;font-size:.97em!important;padding-bottom:10px!important;border-bottom:2px solid #eef0f3!important;display:block!important;font-weight:700!important;text-transform:none!important;letter-spacing:0!important}
.sgc-quote-wrap .form-row{display:flex!important;flex-wrap:wrap!important;gap:10px!important;margin-bottom:10px!important}
.sgc-quote-wrap .form-row .form-group{flex:1 1 170px!important;margin-bottom:0!important;min-width:0!important}
.sgc-quote-wrap .form-group{margin-bottom:10px!important;padding:0!important}
.sgc-quote-wrap .form-group label{display:block!important;font-size:.81em!important;font-weight:600!important;color:#444!important;margin-bottom:5px!important;line-height:1.4!important}
.sgc-quote-wrap .form-group label span.req{color:#e74c3c!important}
.sgc-quote-wrap .form-group input,.sgc-quote-wrap .form-group select,.sgc-quote-wrap .form-group textarea{width:100%!important;padding:8px 10px!important;border:1.5px solid #dde3ea!important;border-radius:6px!important;font-size:.88em!important;color:#333!important;background:#fafcff!important;box-sizing:border-box!important;font-family:inherit!important;height:auto!important;line-height:1.4!important;box-shadow:none!important;-webkit-appearance:none!important}
.sgc-quote-wrap .form-group select{-webkit-appearance:menulist!important;appearance:menulist!important}
.sgc-quote-wrap .form-group input:focus,.sgc-quote-wrap .form-group select:focus,.sgc-quote-wrap .form-group textarea:focus{outline:none!important;border-color:#004a99!important;background:#fff!important}
.sgc-quote-wrap .form-group textarea{height:85px!important;resize:vertical!important}
.sgc-quote-wrap .query-type-grid{display:flex!important;flex-wrap:wrap!important;gap:6px!important;padding:0!important;list-style:none!important}
.sgc-quote-wrap .query-type-grid label{display:flex!important;align-items:center!important;gap:6px!important;font-weight:500!important;cursor:pointer!important;background:#f8fafc!important;border:1.5px solid #dde3ea!important;border-radius:6px!important;padding:7px 10px!important;font-size:.82em!important;margin:0!important;flex:1 1 120px!important;color:#333!important;line-height:1.3!important;transition:border-color .2s,background .2s!important}
.sgc-quote-wrap .query-type-grid label:hover{border-color:#004a99!important;background:#f0f6ff!important}
.sgc-quote-wrap .query-type-grid input[type="radio"]{width:13px!important;height:13px!important;margin:0!important;flex-shrink:0!important;accent-color:#004a99!important;cursor:pointer!important}
.sgc-quote-wrap .file-upload-area{border:2px dashed #c0d4f0!important;border-radius:7px!important;padding:13px!important;text-align:center!important;cursor:pointer!important;background:#f8fafc!important}
.sgc-quote-wrap .file-upload-area p{margin:0!important;font-size:.8em!important;color:#777!important}
.sgc-quote-wrap .file-name-display{font-size:.78em!important;color:#004a99!important;margin-top:4px!important;display:block!important}
.sgc-quote-wrap .submit-btn{width:100%!important;padding:12px!important;background:#004a99!important;color:#fff!important;border:none!important;border-radius:7px!important;font-size:.97em!important;font-weight:700!important;cursor:pointer!important;margin-top:7px!important;display:block!important;text-align:center!important;text-transform:none!important;letter-spacing:0!important;box-shadow:none!important;line-height:1!important}
.sgc-quote-wrap .submit-btn:hover{background:#003880!important}
.sgc-quote-wrap .submit-btn:disabled{background:#a0b4cc!important;cursor:not-allowed!important}
.sgc-quote-wrap .form-security{text-align:center!important;font-size:.76em!important;color:#999!important;margin-top:7px!important;display:block!important}
.sgc-quote-wrap .alert{padding:10px 13px!important;border-radius:6px!important;font-size:.85em!important;margin-top:10px!important;display:none!important}
.sgc-quote-wrap .alert.success{background:#e8f8ef!important;border:1px solid #a3d9b5!important;color:#1a6636!important}
.sgc-quote-wrap .alert.error{background:#fdf0f0!important;border:1px solid #f0b4b4!important;color:#922!important}
/* Sidebar */
.sgc-quote-wrap .sidebar{display:flex!important;flex-direction:column!important;gap:12px!important;flex:0 0 230px!important;min-width:0!important;width:230px!important}
.sgc-quote-wrap .sidebar-card{background:#fff!important;border-radius:9px!important;box-shadow:0 2px 8px rgba(0,0,0,.05)!important;padding:15px!important;border-top:3px solid #004a99!important;width:100%!important}
.sgc-quote-wrap .sidebar-card.green{border-top-color:#27ae60!important}
.sgc-quote-wrap .sidebar-card h4{margin:0 0 10px!important;color:#1a2e4a!important;font-size:.88em!important;display:block!important;font-weight:700!important;text-transform:none!important;letter-spacing:0!important}
.sgc-quote-wrap .contact-item{display:flex!important;align-items:flex-start!important;gap:6px!important;margin-bottom:7px!important;font-size:.8em!important;color:#444!important;line-height:1.5!important;padding:0!important}
.sgc-quote-wrap .contact-item a{color:#004a99!important;text-decoration:none!important;font-weight:600!important}
.sgc-quote-wrap .wa-btn{display:block!important;text-align:center!important;background:#25D366!important;color:#fff!important;font-weight:700!important;padding:9px!important;border-radius:6px!important;text-decoration:none!important;font-size:.82em!important;margin-top:3px!important;line-height:1.3!important}
.sgc-quote-wrap .trust-list{list-style:none!important;padding:0!important;margin:0!important}
.sgc-quote-wrap .trust-list li{padding:4px 0!important;font-size:.79em!important;color:#444!important;border-bottom:1px solid #f0f0f0!important;display:flex!important;gap:5px!important;list-style:none!important;margin:0!important;background:none!important;line-height:1.45!important}
.sgc-quote-wrap .trust-list li::before{display:none!important;content:""!important}
.sgc-quote-wrap .trust-list li:last-child{border-bottom:none!important}
.sgc-quote-wrap .cat-desc{font-size:.8em!important;color:#555!important;margin:0 0 9px!important;display:block!important}
.sgc-quote-wrap .cat-btn{display:block!important;text-align:center!important;background:#004a99!important;color:#fff!important;padding:9px!important;border-radius:6px!important;text-decoration:none!important;font-weight:700!important;font-size:.82em!important}
@media(max-width:640px){
.sgc-quote-wrap .sidebar{flex:1 1 100%!important;width:100%!important}
.sgc-quote-wrap .form-box{flex:1 1 100%!important}
.sgc-quote-wrap .hero-badges{flex-direction:row!important;flex-wrap:wrap!important;min-width:auto!important}
.sgc-quote-wrap .bt-card{min-width:76px!important;max-width:none!important;flex:1 1 76px!important}
}';
}

// ═══════════════════════════════════════════════════════════════════
// 9.  AJAX Handler — sgc_quote_form_submit
// ═══════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_sgc_quote_form_submit',        'sgclabs_handle_b2b_quote_submit' );
add_action( 'wp_ajax_nopriv_sgc_quote_form_submit', 'sgclabs_handle_b2b_quote_submit' );

function sgclabs_handle_b2b_quote_submit() {

    // Verify nonce (security — CSRF protection)
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'sgc_quote_nonce' ) ) {
        wp_send_json_error( 'Security check failed. Please refresh the page and try again.' );
        return;
    }

    // Rate limiting — 5 submissions per IP per hour
    $ip       = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );
    $rate_key = 'sgc_qrate_' . md5( $ip );
    $count    = (int) get_transient( $rate_key );
    if ( $count >= 5 ) {
        wp_send_json_error( 'Too many requests. Please try again later or WhatsApp us.' );
    }
    set_transient( $rate_key, $count + 1, HOUR_IN_SECONDS );

    // Sanitize all inputs
    $name       = sanitize_text_field( wp_unslash( $_POST['name']       ?? '' ) );
    $email      = sanitize_email( wp_unslash( $_POST['email']           ?? '' ) );
    $phone      = sanitize_text_field( wp_unslash( $_POST['phone']      ?? '' ) );
    $company    = sanitize_text_field( wp_unslash( $_POST['company']    ?? '' ) );
    $country    = sanitize_text_field( wp_unslash( $_POST['country']    ?? '' ) );
    $product    = sanitize_text_field( wp_unslash( $_POST['product']    ?? '' ) );
    $qty        = sanitize_text_field( wp_unslash( $_POST['qty']        ?? '' ) );
    $message    = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
    $query_type = sanitize_text_field( wp_unslash( $_POST['query_type'] ?? 'Quote Request' ) );
    $buyer_type = sanitize_text_field( wp_unslash( $_POST['buyer_type'] ?? 'Not Specified' ) );

    // Validate required fields
    if ( ! $name || ! is_email( $email ) || ! $country || ! $product ) {
        wp_send_json_error( 'Please fill all required fields (Name, Email, Country, Product).' );
    }

    // Build inquiry data
    $inquiry_data = [
        'name'       => $name,
        'email'      => $email,
        'phone'      => $phone,
        'company'    => $company,
        'country'    => $country,
        'product'    => $product,
        'qty'        => $qty,
        'message'    => $message,
        'query_type' => $query_type,
        'buyer_type' => $buyer_type,
        'source'     => 'b2b_quote_form',
        'timestamp'  => current_time( 'mysql' ),
        'ip'         => $ip,
    ];

    // Save to log option
    $log   = get_option( 'sgc_quote_submissions_log', [] );
    $log[] = $inquiry_data;
    if ( count( $log ) > 500 ) $log = array_slice( $log, -500 );
    update_option( 'sgc_quote_submissions_log', $log, false );

    // Fire hook → Smart CRM instant reply + contact create
    do_action( 'sgclabs_inquiry_submitted', $inquiry_data );

    // Admin email notification
    $admin_email = get_option( 'admin_email' );
    $subject     = sprintf( '[SGC LABS B2B Quote] %s — %s from %s (%s)', $query_type, $buyer_type, $name, $country );
    
    $body = "═══════════════════════════════════════════════\n";
    $body .= " NEW B2B QUOTE REQUEST — SGC LABS®\n";
    $body .= "═══════════════════════════════════════════════\n\n";
    $body .= "👤 Buyer Type   : {$buyer_type}\n";
    $body .= "📋 Query Type   : {$query_type}\n\n";
    $body .= "Name            : {$name}\n";
    $body .= "Email           : {$email}\n";
    $body .= "Phone           : {$phone}\n";
    $body .= "Company/Institute: {$company}\n";
    $body .= "Country         : {$country}\n\n";
    $body .= "Product         : {$product}\n";
    $body .= "Quantity        : {$qty}\n\n";
    $body .= "Message/Requirements:\n{$message}\n\n";
    $body .= "─────────────────────────────────────────────\n";
    $body .= "Time     : " . current_time( 'D d M Y, H:i' ) . " IST\n";
    $body .= "Source   : B2B Quote Form (labsglass.com/reach-us/)\n";
    $body .= "IP       : {$ip}\n";
    $body .= "═════════════════════════════════════════════\n\n";

    wp_mail( $admin_email, $subject, $body );

    // Success response
    wp_send_json_success( [ 'message' => 'Quote request submitted successfully!' ] );
}
