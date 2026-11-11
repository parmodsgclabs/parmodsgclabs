<?php
/**
 * SGC LABS® PDF Brochure Generator — v3.0 Export-Grade
 *
 * Complete redesign for international export presentation:
 *   - Two-band header with logo, brand, tagline, ISO badge, date
 *   - Generous breathable margins and proper typography hierarchy
 *   - Modern card-style spec table, accent-bar section headers
 *   - 4-column footer: address, email, phone, website + page numbers
 *   - All branding pieces (address / phone / email / website / ISO) are
 *     separate options so the admin can edit them in plain inputs.
 *
 * Architecture is unchanged: server-side base64 image + jsPDF client-side
 * (no jsPDF version bump, no CDN dependency, no CORS surface).
 *
 * @package SGCLabs
 * @since   v6.9.5
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function sgclabs_pdf_defaults(): array {
    return [
        'sgclabs_pdf_active'          => '1',
        'sgclabs_pdf_btn_text'        => '📄 Download Brochure',
        'sgclabs_pdf_btn_color'       => '#c62828',
        'sgclabs_pdf_btn_text_color'  => '#ffffff',
        'sgclabs_pdf_btn_position'    => 'after_cart',
        'sgclabs_pdf_show_price'      => '0',
        // Branding — split fields (used in header + footer)
        'sgclabs_pdf_company_name'    => 'SGC LABS®',
        'sgclabs_pdf_company_tagline' => 'Laboratory Glassware Manufacturer & Exporter — Since 2003',
        'sgclabs_pdf_iso_badge'       => 'ISO 9001:2015 Certified',
        'sgclabs_pdf_address'         => '4/12/2 Gandhi Nagar, Ambala Cantt 133001, Haryana, India',
        'sgclabs_pdf_email'           => 'sales@labsglass.com',
        'sgclabs_pdf_phone'           => '+91 999 641 6935',
        'sgclabs_pdf_website'         => 'www.labsglass.com',
        // Theme
        'sgclabs_pdf_header_color'    => '#1a1a2e',
        'sgclabs_pdf_accent_color'    => '#c9a961',
        'sgclabs_pdf_logo_url'        => '',
        // Legacy single-line footer — kept for backward compatibility.
        'sgclabs_pdf_footer_text'     => '',
    ];
}

add_action( 'admin_post_sgclabs_save_pdf_settings', 'sgclabs_save_pdf_settings' );
function sgclabs_save_pdf_settings() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
    check_admin_referer( 'sgclabs_pdf_settings_nonce' );
    $d      = sgclabs_pdf_defaults();
    $bool   = [ 'sgclabs_pdf_active', 'sgclabs_pdf_show_price' ];
    $cols   = [ 'sgclabs_pdf_btn_color', 'sgclabs_pdf_btn_text_color', 'sgclabs_pdf_header_color', 'sgclabs_pdf_accent_color' ];
    $emails = [ 'sgclabs_pdf_email' ];
    $urls   = [ 'sgclabs_pdf_logo_url' ];
    foreach ( array_keys( $d ) as $k ) {
        if ( in_array( $k, $bool, true ) )       update_option( $k, isset($_POST[$k]) ? '1' : '0' );
        elseif ( in_array( $k, $cols, true ) )   update_option( $k, sanitize_hex_color($_POST[$k] ?? $d[$k]) ?: $d[$k] );
        elseif ( in_array( $k, $emails, true ) ) update_option( $k, sanitize_email(wp_unslash($_POST[$k] ?? $d[$k])) );
        elseif ( in_array( $k, $urls, true ) )   update_option( $k, esc_url_raw(wp_unslash($_POST[$k] ?? '')) );
        else                                     update_option( $k, sanitize_text_field(wp_unslash($_POST[$k] ?? '')) );
    }
    wp_safe_redirect( admin_url( 'admin.php?page=sgclabs-site-tools-hub&tab=pdf&saved=1' ) );
    exit;
}

add_action( 'wp_ajax_sgclabs_pdf_get_product',        'sgclabs_pdf_get_product_data' );
add_action( 'wp_ajax_nopriv_sgclabs_pdf_get_product', 'sgclabs_pdf_get_product_data' );
function sgclabs_pdf_get_product_data() {
    check_ajax_referer( 'sgclabs_pdf_nonce', 'nonce' );
    $pid = intval( $_POST['product_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'Invalid product ID' );
    $product = wc_get_product( $pid );
    if ( ! $product ) wp_send_json_error( 'Product not found' );

    // Base64 image — zero CORS
    $img_b64 = '';
    $tid = $product->get_image_id();
    if ( $tid ) {
        $path = get_attached_file( $tid );
        if ( $path && file_exists( $path ) ) {
            $mime    = mime_content_type( $path ) ?: 'image/jpeg';
            $img_b64 = 'data:' . $mime . ';base64,' . base64_encode( file_get_contents( $path ) );
        }
    }

    // Specs
    $specs  = get_post_meta( $pid, '_sgclabs_tech_specs', true ) ?: [];
    $fields = function_exists('sgclabs_specs_fields') ? sgclabs_specs_fields() : [];
    $specs_out = [];
    foreach ( $fields as $key => $info ) {
        if ( ! empty($specs[$key]) ) $specs_out[] = ['label'=>$info['label'],'value'=>$specs[$key]];
    }

    // Variants
    $packs = [];
    if ( $product->is_type('variable') ) {
        foreach ( $product->get_available_variations() as $v ) {
            $a = array_values(array_filter($v['attributes']));
            if ($a) $packs[] = implode(' / ', $a);
        }
    }

    $show_price = get_option('sgclabs_pdf_show_price','0') === '1';

    // Logo base64
    $logo_b64 = '';
    $logo_url = get_option('sgclabs_pdf_logo_url','');
    if ( $logo_url ) {
        $lid = attachment_url_to_postid($logo_url);
        if ( $lid ) {
            $lpath = get_attached_file($lid);
            if ( $lpath && file_exists($lpath) ) {
                $lmime = mime_content_type($lpath) ?: 'image/png';
                $logo_b64 = 'data:' . $lmime . ';base64,' . base64_encode(file_get_contents($lpath));
            }
        }
    }

    wp_send_json_success([
        'title'      => $product->get_name(),
        'sku'        => $product->get_sku(),
        'short_desc' => wp_strip_all_tags($product->get_short_description()),
        'full_desc'  => wp_strip_all_tags($product->get_description()),
        'categories' => wp_strip_all_tags(wc_get_product_category_list($pid,', ')),
        'price'      => $show_price ? wp_strip_all_tags($product->get_price_html()) : '',
        'show_price' => $show_price,
        'specs'      => $specs_out,
        'pack_sizes' => $packs,
        'img_b64'    => $img_b64,
        'logo_b64'   => $logo_b64,
        // Branding fields
        'company'    => get_option('sgclabs_pdf_company_name','SGC LABS®'),
        'tagline'    => get_option('sgclabs_pdf_company_tagline',''),
        'iso_badge'  => get_option('sgclabs_pdf_iso_badge','ISO 9001:2015 Certified'),
        'address'    => get_option('sgclabs_pdf_address',''),
        'email'      => get_option('sgclabs_pdf_email',''),
        'phone'      => get_option('sgclabs_pdf_phone',''),
        'website'    => get_option('sgclabs_pdf_website',''),
        'hdr_color'  => get_option('sgclabs_pdf_header_color','#1a1a2e'),
        'acc_color'  => get_option('sgclabs_pdf_accent_color','#c9a961'),
    ]);
}

add_action( 'wp', 'sgclabs_pdf_frontend_hooks' );
function sgclabs_pdf_frontend_hooks() {
    if ( get_option('sgclabs_pdf_active','1') !== '1' ) return;
    if ( ! function_exists('is_product') ) return;
    add_action( 'wp_footer', 'sgclabs_pdf_modal_html', 5 );
    add_action( 'wp_footer', 'sgclabs_pdf_assets_html', 6 );
}

function sgclabs_render_pdf_button() {
    // PDF button is rendered side-by-side with the Quote button (get-a-quote.php).
    return;
}

function sgclabs_pdf_modal_html() { ?>
<div id="sgc-pdf-modal" style="display:none;position:fixed;inset:0;z-index:999999;
    background:rgba(0,0,0,.55);align-items:center;justify-content:center;"
    onclick="if(event.target===this)sgcPdfClose()">
    <div style="background:#fff;border-radius:12px;width:420px;max-width:95vw;
        box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;">
        <div id="sgc-pdf-hdr" style="background:#1a1a2e;color:#fff;padding:16px 20px;
            display:flex;align-items:center;justify-content:space-between;">
            <div>
                <div style="font-size:15px;font-weight:700;">📄 Product Brochure</div>
                <div style="font-size:11px;opacity:.7;" id="sgc-pdf-hdr-name">Loading...</div>
            </div>
            <button onclick="sgcPdfClose()" style="background:none;border:none;color:#fff;
                font-size:22px;cursor:pointer;line-height:1;">&times;</button>
        </div>
        <div id="sgc-pdf-loading" style="padding:32px;text-align:center;color:#888;">
            <div style="font-size:28px;margin-bottom:8px;">⏳</div>
            <div style="font-size:13px;">Loading product data...</div>
        </div>
        <div id="sgc-pdf-form" style="display:none;padding:20px;">
            <label style="display:block;margin-bottom:14px;cursor:pointer;">
                <input type="checkbox" id="sgc-pdf-img-chk" checked>
                <span style="margin-left:6px;font-size:13px;">Include product image</span>
            </label>
            <label style="display:block;margin-bottom:14px;cursor:pointer;">
                <input type="checkbox" id="sgc-pdf-price-chk">
                <span style="margin-left:6px;font-size:13px;">Include price</span>
            </label>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">
                Personal note (optional)
            </label>
            <textarea id="sgc-pdf-note" rows="3" placeholder="e.g. Bulk discount available on request"
                style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:6px;
                font-size:12px;resize:vertical;font-family:inherit;"></textarea>
            <div id="sgc-pdf-status" style="font-size:12px;margin-top:10px;min-height:18px;"></div>
            <button id="sgc-pdf-btn" onclick="sgcPdfGenerate()"
                style="margin-top:14px;width:100%;background:#1a1a2e;color:#fff;border:none;
                padding:11px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">
                📥 Download PDF Brochure
            </button>
        </div>
    </div>
</div>
<?php }

function sgclabs_pdf_assets_html() {
    if ( ! is_product() ) return;
    $product = function_exists('sgclabs_current_product') ? sgclabs_current_product() : null;
    $pid = $product ? $product->get_id() : ( get_queried_object_id() ?: 0 );
    if ( ! $pid ) return;

    $jspdf_url = plugins_url( 'assets/js/jspdf.umd.min.js', dirname(__FILE__) );
    wp_enqueue_script( 'sgclabs-jspdf', $jspdf_url, [], '2.5.2', true );
    ?>
<script>
(function(){
    if (typeof window.jspdf === 'undefined') {
        var s=document.createElement('script');
        s.src='<?php echo esc_url($jspdf_url); ?>';
        s.onload=function(){
            if (typeof window.jspdf === 'undefined') {
                console.warn('SGC: jsPDF not loaded');
            }
        };
        document.head.appendChild(s);
    }
})();
window.sgcPdfPID = <?php echo intval($pid); ?>;
window.sgcPdfNonce = '<?php echo wp_create_nonce('sgclabs_pdf_nonce'); ?>';
window.sgcPdfAjax = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';

(function(){
var PD=null;

window.sgcPdfOpen=function(){
    document.getElementById('sgc-pdf-modal').style.display='flex';
    document.body.style.overflow='hidden';
    document.getElementById('sgc-pdf-loading').style.display='block';
    document.getElementById('sgc-pdf-form').style.display='none';
    sgcPdfSt('','');
    fetch(window.sgcPdfAjax,{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'action=sgclabs_pdf_get_product&product_id='+window.sgcPdfPID+'&nonce='+window.sgcPdfNonce
    }).then(function(r){return r.json();}).then(function(j){
        if(!j.success){throw new Error(j.data||'Failed');}
        PD=j.data;
        document.getElementById('sgc-pdf-hdr-name').textContent=PD.title||'';
        document.getElementById('sgc-pdf-price-chk').checked=!!PD.show_price;
        document.getElementById('sgc-pdf-loading').style.display='none';
        document.getElementById('sgc-pdf-form').style.display='block';
    }).catch(function(e){
        document.getElementById('sgc-pdf-loading').innerHTML='<div style="color:#c00;">❌ '+e.message+'</div>';
    });
};
window.sgcPdfClose=function(){
    document.getElementById('sgc-pdf-modal').style.display='none';
    document.body.style.overflow='';
};
function sgcPdfSt(msg,t){
    var c=t==='ok'?'#0a0':t==='err'?'#c00':'#888';
    var el=document.getElementById('sgc-pdf-status');
    if(el){el.style.color=c;el.textContent=msg;}
}

// ─────────────────────────────────────────────────────────────────────
// PDF GENERATION — v3.0 Export-Grade
// ─────────────────────────────────────────────────────────────────────
window.sgcPdfGenerate=function(){
    if(!PD)return;
    if(typeof window.jspdf==='undefined'||typeof window.jspdf.jsPDF==='undefined'){
        sgcPdfSt('❌ PDF library load nahi hui. Page refresh karo aur dobara try karo.','err');
        return;
    }
    var btn=document.getElementById('sgc-pdf-btn');
    btn.disabled=true;
    btn.textContent='⏳ Generating...';

    try{
        var jsPDF=window.jspdf.jsPDF;
        var doc=new jsPDF('p','mm','a4');

        // Page constants
        var W=210,H=297;
        var M=16;                   // outer page margin
        var CW=W-M*2;               // content width
        var HEADER_H=42;            // two-band header height
        var FOOTER_H=22;            // footer band height
        var BODY_TOP=HEADER_H+10;   // first content y on every page
        var BODY_BOT=H-FOOTER_H-6;  // bottom content limit before page break

        // Colors
        function hex2rgb(h){
            h=(h||'#1a1a2e').replace('#','');
            return [parseInt(h.slice(0,2),16),parseInt(h.slice(2,4),16),parseInt(h.slice(4,6),16)];
        }
        var HC=hex2rgb(PD.hdr_color);  // primary navy
        var AC=hex2rgb(PD.acc_color);  // accent gold
        var TX=[33,37,41];             // body text
        var TX2=[100,108,118];         // muted text
        var BG_LIGHT=[248,249,251];    // light card bg
        var BORDER=[225,228,232];

        var showPrice=document.getElementById('sgc-pdf-price-chk').checked;
        var showImg=document.getElementById('sgc-pdf-img-chk').checked;
        var note=(document.getElementById('sgc-pdf-note').value||'').trim();

        // ── Helpers ─────────────────────────────────────────────────
        function setText(rgb){doc.setTextColor(rgb[0],rgb[1],rgb[2]);}
        function setFill(rgb){doc.setFillColor(rgb[0],rgb[1],rgb[2]);}
        function setDraw(rgb){doc.setDrawColor(rgb[0],rgb[1],rgb[2]);}

        // Decode HTML entities before drawing — fixes &amp; &quot; &#39; etc.
        function decodeHtml(str){
            if(!str)return '';
            return str
                .replace(/&amp;/g,'&')
                .replace(/&lt;/g,'<')
                .replace(/&gt;/g,'>')
                .replace(/&quot;/g,'"')
                .replace(/&#39;/g,"'")
                .replace(/&nbsp;/g,' ')
                .replace(/&reg;/g,'(R)')
                .replace(/&#\d+;/g,function(m){
                    try{return String.fromCharCode(parseInt(m.slice(2,-1)));}catch(e){return '';}
                });
        }

        // Currency symbol safe for Helvetica (jsPDF doesn't support ₹ Unicode)
        function safeCurrency(str){
            if(!str)return '';
            return str
                .replace(/₹/g,'Rs.')
                .replace(/€/g,'EUR ')
                .replace(/£/g,'GBP ')
                .replace(/¥/g,'JPY ');
        }

        // Reset to body text state — MUST call after every drawHeader()
        function resetBodyText(){
            doc.setFontSize(9);
            doc.setFont('helvetica','normal');
            setText(TX);
        }

        function sectionHeader(label,y){
            setFill(AC); doc.rect(M,y-3.5,3,5,'F');
            doc.setFontSize(11); doc.setFont('helvetica','bold'); setText(HC);
            doc.text(decodeHtml(label),M+6,y);
            resetBodyText();   // ← reset after drawing colored header
            return y+7;
        }

        function ensureSpace(needed,y){
            if(y+needed>BODY_BOT){
                doc.addPage();
                drawHeader();
                resetBodyText();   // ← FIX: reset color after header on new page
                return BODY_TOP;
            }
            return y;
        }

        // ── HEADER ───────────────────────────────────────────────────
        function drawHeader(){
            // Main navy band
            setFill(HC); doc.rect(0,0,W,HEADER_H-6,'F');
            // Accent gold strip
            setFill(AC); doc.rect(0,HEADER_H-6,W,2,'F');
            // Thin lighter strip below accent
            setFill(BG_LIGHT); doc.rect(0,HEADER_H-4,W,4,'F');

            // Logo
            var lx=M;
            if(PD.logo_b64){
                try{doc.addImage(PD.logo_b64,'PNG',M,8,20,20);lx=M+24;}
                catch(e){try{doc.addImage(PD.logo_b64,'JPEG',M,8,20,20);lx=M+24;}catch(e2){}}
            }

            // Brand name
            setText([255,255,255]);
            doc.setFontSize(16); doc.setFont('helvetica','bold');
            doc.text(decodeHtml(PD.company||'SGC LABS(R)'),lx,15);

            // Tagline
            doc.setFontSize(8); doc.setFont('helvetica','normal');
            setText([200,205,215]);
            var tl=doc.splitTextToSize(decodeHtml(PD.tagline||''),W-lx-M-50);
            doc.text(tl.slice(0,2),lx,21);

            // ISO badge pill
            if(PD.iso_badge){
                var badge=decodeHtml(PD.iso_badge);
                doc.setFontSize(8); doc.setFont('helvetica','bold');
                var bw=doc.getTextWidth(badge)+8;
                setFill(AC);
                doc.roundedRect(W-M-bw,11,bw,6.5,3,3,'F');
                setText(HC);
                doc.text(badge,W-M-bw+4,15.5);
            }

            // Date top right
            doc.setFontSize(7); doc.setFont('helvetica','normal');
            setText([180,185,195]);
            var d=new Date();
            var ds=d.toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'});
            doc.text(ds+'  .  '+d.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit'}),W-M,24,{align:'right'});

            // ── CRITICAL: Reset to body text after header ────────────
            // drawHeader() must leave the doc in a state ready for body
            // content — not in white/grey header text mode.
            resetBodyText();
        }

        // ── FOOTER ───────────────────────────────────────────────────
        function drawFooter(pageNum,totalPages){
            var fy=H-FOOTER_H;
            // Navy band
            setFill(HC); doc.rect(0,fy,W,FOOTER_H,'F');
            // Accent strip on top of footer
            setFill(AC); doc.rect(0,fy,W,1.2,'F');

            // Four-column contact strip
            var colW=(W-M*2)/4;
            var iy=fy+8;
            doc.setFontSize(7); doc.setFont('helvetica','bold');
            setText(AC);
            ['ADDRESS','EMAIL','PHONE','WEBSITE'].forEach(function(lbl,i){
                doc.text(lbl,M+colW*i,iy);
            });

            doc.setFontSize(8); doc.setFont('helvetica','normal');
            setText([235,238,245]);
            var vals=[
                PD.address||'',
                PD.email||'',
                PD.phone||'',
                PD.website||''
            ];
            vals.forEach(function(v,i){
                if(!v)return;
                var lines=doc.splitTextToSize(v,colW-3);
                doc.text(lines.slice(0,2),M+colW*i,iy+4);
            });

            // Page X / Y
            doc.setFontSize(7); setText([180,185,195]);
            doc.text('Page '+pageNum+' / '+totalPages,W-M,H-3,{align:'right'});
            doc.text('Generated by SGC LABS® plugin',M,H-3);
        }

        // ── PAGE 1: HEADER ───────────────────────────────────────────
        drawHeader();
        var y=BODY_TOP;

        // ── TITLE ────────────────────────────────────────────────────
        doc.setFontSize(18); doc.setFont('helvetica','bold'); setText(HC);
        var tlines=doc.splitTextToSize(decodeHtml(PD.title||'Product'),CW);
        tlines.forEach(function(l){doc.text(l,M,y);y+=8;});
        // Title underline accent
        setDraw(AC); doc.setLineWidth(0.8);
        doc.line(M,y-1,M+30,y-1);
        resetBodyText();
        y+=4;

        // ── META BADGES (SKU, Category) ──────────────────────────────
        var badges=[];
        if(PD.sku) badges.push({label:'SKU',value:PD.sku});
        if(PD.categories) badges.push({label:'Category',value:decodeHtml(PD.categories)});
        if(badges.length){
            var bx=M;
            badges.forEach(function(b){
                doc.setFontSize(7); doc.setFont('helvetica','bold');
                var labelW=doc.getTextWidth(b.label)+5;
                doc.setFontSize(8); doc.setFont('helvetica','normal');
                var valueW=doc.getTextWidth(b.value)+5;
                // Label pill (dark)
                setFill(HC);
                doc.roundedRect(bx,y-3.5,labelW,5.5,1.2,1.2,'F');
                setText([255,255,255]);
                doc.setFontSize(7); doc.setFont('helvetica','bold');
                doc.text(b.label,bx+2.5,y);
                // Value pill (light)
                setFill(BG_LIGHT);
                doc.roundedRect(bx+labelW,y-3.5,valueW,5.5,1.2,1.2,'F');
                setText(HC);
                doc.setFontSize(8); doc.setFont('helvetica','normal');
                doc.text(b.value,bx+labelW+2.5,y);
                bx+=labelW+valueW+5;
            });
            resetBodyText();
            y+=8;
        }

        // ── PRICE (optional) ─────────────────────────────────────────
        if(showPrice&&PD.price){
            // Decode + make rupee safe for Helvetica
            var priceClean=safeCurrency(decodeHtml(PD.price));
            // Truncate if too long (WC price with strikethrough HTML)
            priceClean=priceClean.replace(/<[^>]+>/g,'').substring(0,60);
            setFill([232,245,233]);
            doc.roundedRect(M,y-4,CW,9,2,2,'F');
            doc.setFontSize(11); doc.setFont('helvetica','bold');
            setText([22,101,52]);
            doc.text('Price: '+priceClean,M+4,y+1);
            resetBodyText();
            y+=11;
        }

        // ── HERO BLOCK: image card + short description ───────────────
        var imgSize=showImg&&PD.img_b64?60:0;
        if(imgSize>0){
            // Image card with light bg + border
            setFill([252,253,254]);
            doc.roundedRect(M,y,imgSize,imgSize,2,2,'F');
            setDraw(BORDER); doc.setLineWidth(0.3);
            doc.roundedRect(M,y,imgSize,imgSize,2,2,'S');
            try{doc.addImage(PD.img_b64,'JPEG',M+3,y+3,imgSize-6,imgSize-6);}
            catch(e){try{doc.addImage(PD.img_b64,'PNG',M+3,y+3,imgSize-6,imgSize-6);}catch(e2){}}
        }
        var tx=M+(imgSize>0?imgSize+8:0);
        var tw=CW-(imgSize>0?imgSize+8:0);
        var heroStartY=y;

        if(PD.short_desc){
            doc.setFontSize(9); doc.setFont('helvetica','normal'); setText(TX);
            var sd=doc.splitTextToSize(decodeHtml(safeCurrency(PD.short_desc)),tw);
            var ly=y+3;
            sd.slice(0,12).forEach(function(l){
                doc.text(l,tx,ly);ly+=4.6;
            });
        }
        y=Math.max(y+(imgSize>0?imgSize:0),heroStartY+50)+8;

        // ── DESCRIPTION ──────────────────────────────────────────────
        if(PD.full_desc){
            y=ensureSpace(15,y);
            y=sectionHeader('Product Description',y);
            doc.setFontSize(9); doc.setFont('helvetica','normal'); setText(TX);
            var fd=doc.splitTextToSize(decodeHtml(safeCurrency(PD.full_desc)),CW);
            fd.forEach(function(l){
                if(y>BODY_BOT){
                    doc.addPage();drawHeader();
                    y=BODY_TOP;
                }
                doc.text(l,M,y); y+=4.6;
            });
            y+=4;
        }

        // ── TECHNICAL SPECS (modern card-style table) ────────────────
        if(PD.specs&&PD.specs.length>0){
            y=ensureSpace(20,y);
            y=sectionHeader('Technical Specifications',y);

            var tableTop=y;
            var tableY=y;
            var rowH=7.5;
            var labelColW=CW*0.42;

            PD.specs.forEach(function(s,i){
                if(tableY+rowH>BODY_BOT){
                    // Close current table card before page break
                    setDraw(BORDER); doc.setLineWidth(0.3);
                    doc.roundedRect(M,tableTop,CW,tableY-tableTop,2,2,'S');
                    doc.addPage();drawHeader();
                    tableY=BODY_TOP;
                    tableTop=BODY_TOP;   // ← FIX: reset tableTop on new page
                }
                if(i%2===0){
                    setFill(BG_LIGHT);
                    doc.rect(M,tableY,CW,rowH,'F');
                }
                doc.setFontSize(8.5); doc.setFont('helvetica','bold'); setText(HC);
                doc.text(decodeHtml(String(s.label||'')),M+4,tableY+5);
                doc.setFont('helvetica','normal'); setText(TX);
                var valLines=doc.splitTextToSize(
                    decodeHtml(safeCurrency(String(s.value||''))),
                    CW-labelColW-8
                );
                doc.text(valLines.slice(0,1),M+labelColW,tableY+5);
                tableY+=rowH;
            });
            // Close final card border
            setDraw(BORDER); doc.setLineWidth(0.3);
            doc.roundedRect(M,tableTop,CW,tableY-tableTop,2,2,'S');
            resetBodyText();
            y=tableY+6;
        }

        // ── PACK SIZES / VARIANTS ────────────────────────────────────
        if(PD.pack_sizes&&PD.pack_sizes.length>0){
            y=ensureSpace(15,y);
            y=sectionHeader('Available Sizes / Variants',y);
            doc.setFontSize(9); doc.setFont('helvetica','normal'); setText(TX);
            PD.pack_sizes.forEach(function(p){
                if(y>BODY_BOT){doc.addPage();drawHeader();y=BODY_TOP;}
                setFill(AC); doc.circle(M+2,y-1.4,1,'F');
                doc.text(decodeHtml(p),M+6,y); y+=5;
            });
            resetBodyText();
            y+=3;
        }

        // ── PERSONAL NOTE ────────────────────────────────────────────
        if(note){
            var noteDecoded=decodeHtml(note);
            var nl=doc.splitTextToSize(noteDecoded,CW-10);
            var noteH=nl.length*4.6+8;
            y=ensureSpace(noteH+4,y);
            setFill([255,250,225]);
            doc.roundedRect(M,y-2,CW,noteH,2,2,'F');
            setDraw([240,210,90]); doc.setLineWidth(0.3);
            doc.roundedRect(M,y-2,CW,noteH,2,2,'S');
            doc.setFontSize(7); doc.setFont('helvetica','bold');
            setText([146,108,18]);
            doc.text('NOTE',M+4,y+2);
            doc.setFontSize(9); doc.setFont('helvetica','italic');
            setText([86,68,18]);
            var ly=y+7;
            nl.forEach(function(n){doc.text(n,M+4,ly);ly+=4.6;});
            resetBodyText();
            y+=noteH+4;
        }

        // ── FOOTERS ON ALL PAGES ─────────────────────────────────────
        var totalPages=doc.getNumberOfPages();
        for(var p=1;p<=totalPages;p++){
            doc.setPage(p);
            drawFooter(p,totalPages);
        }

        // ── SAVE ─────────────────────────────────────────────────────
        var fn=(PD.title||'brochure')
            .replace(/[^a-zA-Z0-9\s]/g,'')
            .replace(/\s+/g,'-')
            .substring(0,40)+'-SGC-Brochure.pdf';
        doc.save(fn);
        sgcPdfSt('✅ PDF downloaded!','ok');
        btn.textContent='✅ Downloaded!';
        setTimeout(function(){btn.disabled=false;btn.textContent='📥 Download PDF Brochure';},2500);
    }catch(err){
        console.error('PDF error:',err);
        sgcPdfSt('❌ '+err.message,'err');
        btn.disabled=false;
        btn.textContent='📥 Download PDF Brochure';
    }
};
})();
</script>
<?php }

// ─────────────────────────────────────────────────────────────────────
// ADMIN SETTINGS PAGE
// ─────────────────────────────────────────────────────────────────────
function sgclabs_pdf_brochure_page() {
    if(!current_user_can('manage_options')) wp_die('Unauthorized');
    $d=sgclabs_pdf_defaults();
    $v=[];
    foreach($d as $k=>$dv) $v[$k]=get_option($k,$dv);
    $saved=isset($_GET['saved']);
    ?>
    <div class="wrap" style="max-width:780px;">
        <h1>📄 PDF Brochure Generator
            <span style="font-size:11px;background:#1a1a2e;color:#fff;padding:2px 8px;border-radius:10px;vertical-align:middle;">v3.0 export-grade</span>
        </h1>
        <?php if($saved): ?><div class="notice notice-success is-dismissible"><p>✅ Settings saved!</p></div><?php endif; ?>

        <p style="color:#555;max-width:680px;line-height:1.6;">
            Branded A4 brochures for every product. Header has logo + brand + ISO badge + date; footer has split address / email / phone / website columns + page numbers. Edit each piece below.
        </p>

        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('sgclabs_pdf_settings_nonce'); ?>
            <input type="hidden" name="action" value="sgclabs_save_pdf_settings">

            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:16px;">
                <h2 style="margin-top:0;font-size:15px;">⚙️ General</h2>
                <table class="form-table">
                    <tr><th>Enable</th><td><label><input type="checkbox" name="sgclabs_pdf_active" value="1" <?php checked($v['sgclabs_pdf_active'],'1'); ?>> Show button on product pages</label></td></tr>
                    <tr><th>Button Position</th><td>
                        <select name="sgclabs_pdf_btn_position">
                            <option value="after_cart" <?php selected($v['sgclabs_pdf_btn_position'],'after_cart'); ?>>After Add to Cart</option>
                            <option value="before_cart" <?php selected($v['sgclabs_pdf_btn_position'],'before_cart'); ?>>Before Add to Cart</option>
                        </select>
                    </td></tr>
                    <tr><th>Show Price by Default</th><td><label><input type="checkbox" name="sgclabs_pdf_show_price" value="1" <?php checked($v['sgclabs_pdf_show_price'],'1'); ?>> User can toggle in modal</label></td></tr>
                </table>
            </div>

            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:16px;">
                <h2 style="margin-top:0;font-size:15px;">🎨 Front-end Button</h2>
                <table class="form-table">
                    <tr><th>Text</th><td><input type="text" name="sgclabs_pdf_btn_text" value="<?php echo esc_attr($v['sgclabs_pdf_btn_text']); ?>" class="regular-text"></td></tr>
                    <tr><th>Background Color</th><td><input type="color" name="sgclabs_pdf_btn_color" value="<?php echo esc_attr($v['sgclabs_pdf_btn_color']); ?>"></td></tr>
                    <tr><th>Text Color</th><td><input type="color" name="sgclabs_pdf_btn_text_color" value="<?php echo esc_attr($v['sgclabs_pdf_btn_text_color']); ?>"></td></tr>
                </table>
            </div>

            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:16px;">
                <h2 style="margin-top:0;font-size:15px;">🏢 Header — Branding</h2>
                <table class="form-table">
                    <tr><th>Company Name</th><td><input type="text" name="sgclabs_pdf_company_name" value="<?php echo esc_attr($v['sgclabs_pdf_company_name']); ?>" class="regular-text"></td></tr>
                    <tr><th>Tagline</th><td><input type="text" name="sgclabs_pdf_company_tagline" value="<?php echo esc_attr($v['sgclabs_pdf_company_tagline']); ?>" class="large-text"></td></tr>
                    <tr><th>ISO / Certification Badge</th><td>
                        <input type="text" name="sgclabs_pdf_iso_badge" value="<?php echo esc_attr($v['sgclabs_pdf_iso_badge']); ?>" class="regular-text" placeholder="ISO 9001:2015 Certified">
                        <p class="description">Shown as a gold pill in the top-right of the header. Leave empty to hide.</p>
                    </td></tr>
                    <tr><th>Logo URL</th><td>
                        <input type="url" name="sgclabs_pdf_logo_url" value="<?php echo esc_attr($v['sgclabs_pdf_logo_url']); ?>" class="regular-text" placeholder="https://...">
                        <p class="description">Square-ish PNG/JPG works best. Recommended 200×200 px.</p>
                    </td></tr>
                    <tr><th>Header Color</th><td><input type="color" name="sgclabs_pdf_header_color" value="<?php echo esc_attr($v['sgclabs_pdf_header_color']); ?>"></td></tr>
                    <tr><th>Accent Color</th><td>
                        <input type="color" name="sgclabs_pdf_accent_color" value="<?php echo esc_attr($v['sgclabs_pdf_accent_color']); ?>">
                        <p class="description">Used for the gold strip, ISO badge, section bars, bullets.</p>
                    </td></tr>
                </table>
            </div>

            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:16px;">
                <h2 style="margin-top:0;font-size:15px;">📍 Footer — Contact (4-column strip)</h2>
                <table class="form-table">
                    <tr><th>Address</th><td>
                        <input type="text" name="sgclabs_pdf_address" value="<?php echo esc_attr($v['sgclabs_pdf_address']); ?>" class="large-text" placeholder="Street, City, PIN, State, Country">
                    </td></tr>
                    <tr><th>Email</th><td>
                        <input type="email" name="sgclabs_pdf_email" value="<?php echo esc_attr($v['sgclabs_pdf_email']); ?>" class="regular-text" placeholder="sales@yourcompany.com">
                    </td></tr>
                    <tr><th>Phone</th><td>
                        <input type="text" name="sgclabs_pdf_phone" value="<?php echo esc_attr($v['sgclabs_pdf_phone']); ?>" class="regular-text" placeholder="+91 ...">
                    </td></tr>
                    <tr><th>Website</th><td>
                        <input type="text" name="sgclabs_pdf_website" value="<?php echo esc_attr($v['sgclabs_pdf_website']); ?>" class="regular-text" placeholder="www.yourcompany.com">
                    </td></tr>
                </table>
            </div>

            <?php submit_button('Save Settings'); ?>
        </form>
    </div>
    <?php
}
