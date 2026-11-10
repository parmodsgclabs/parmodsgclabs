<?php
defined( 'ABSPATH' ) || exit;

/**
 * SGC_PDF_Engine v1.1.4
 * Pure PHP PDF generator. Zero Dompdf dependency.
 * Added: JPEG image embedding (signature + stamp), GST export/domestic logic.
 */
class SGC_PDF_Engine {
    private $pages    = [];
    private $cur      = '';
    private $img_data = [];  // [ id => [ jpeg_bytes, w, h ] ]
    public  $W        = 595.28;
    public  $H        = 841.89;
    private $y        = 40;

    public function __construct() { $this->_np(); }
    private function _np() {
        if ($this->cur !== '') $this->pages[] = $this->cur;
        $this->cur = ''; $this->y = 40;
    }

    /* ── Colors ── */
    public function fill($r,$g,$b){
        $this->cur.=sprintf("%.3f %.3f %.3f rg\n",$r/255,$g/255,$b/255);
    }
    public function stroke($r,$g,$b){
        $this->cur.=sprintf("%.3f %.3f %.3f RG\n",$r/255,$g/255,$b/255);
    }
    public function lw($w){$this->cur.=sprintf("%.2f w\n",$w);}

    /* ── Shapes ── */
    public function rect($x,$y,$w,$h,$op='f'){
        $py=$this->H-$y-$h;
        $this->cur.=sprintf("%.2f %.2f %.2f %.2f re %s\n",$x,$py,$w,$h,$op);
    }
    public function line($x1,$y1,$x2,$y2){
        $this->cur.=sprintf("%.2f %.2f m %.2f %.2f l S\n",$x1,$this->H-$y1,$x2,$this->H-$y2);
    }

    /* ── Text ── */
    private function _esc($s){
        if(function_exists('iconv')) $s=iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE',(string)$s);
        return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],(string)$s);
    }
    public function text($x,$y,$txt,$bold=false,$sz=10,$r=0,$g=0,$b=0){
        $f=$bold?'Helvetica-Bold':'Helvetica';
        $py=$this->H-$y;
        $this->cur.="BT\n/{$f} {$sz} Tf\n"
            .sprintf("%.3f %.3f %.3f rg\n",$r/255,$g/255,$b/255)
            .sprintf("%.2f %.2f Td\n",$x,$py)
            ."({$this->_esc($txt)}) Tj\nET\n0 0 0 rg\n";
    }

    /* ── Cell with auto-shrink font ── */
    public function cell($x,$y,$w,$h,$txt,$align='L',$bold=false,$sz=9,
        $br=255,$bg=255,$bb=255,$tr=0,$tg=0,$tb=0,$pad=2){
        $w=(float)$w;$h=(float)$h;$x=(float)$x;$y=(float)$y;$sz=(float)$sz;
        $this->fill($br,$bg,$bb); $this->rect($x,$y,$w,$h);
        $avail=$w-$pad*2;
        $approx=strlen((string)$txt)*$sz*0.52;
        if($approx>$avail&&$approx>0) $sz=max(6,$sz*($avail/$approx));
        $tw=strlen((string)$txt)*$sz*0.52;
        if($align==='C') $tx=$x+($w-$tw)/2;
        elseif($align==='R') $tx=$x+$w-$tw-$pad;
        else $tx=$x+$pad;
        $ty=$y+$h-($h-$sz)/2-1.5;
        $this->text($tx,$ty,(string)$txt,$bold,$sz,$tr,$tg,$tb);
    }

    /* ── IMAGE EMBEDDING ─────────────────────────────────────────────
     * Embeds a JPEG image into PDF.
     * @param $x,$y  top-left position
     * @param $w,$h  display size in points
     * @param $src   file path (local) or URL
     * Returns image object id or false on failure.
     */
    public function image($x,$y,$w,$h,$src){
        if(empty($src)) return false;

        // Get JPEG bytes
        $jpeg_bytes = $this->_get_jpeg($src);
        if(!$jpeg_bytes) return false;

        // Get actual pixel dims
        $info = getimagesizefromstring($jpeg_bytes);
        if(!$info) return false;
        $pw = $info[0]; $ph = $info[1];

        // Store image data
        $img_id = 'Img'.( count($this->img_data)+1 );
        $this->img_data[$img_id] = [ 'bytes'=>$jpeg_bytes, 'w'=>$pw, 'h'=>$ph ];

        // Draw in page stream
        $py = $this->H - $y - $h;
        $this->cur .= "q\n"
            .sprintf("%.2f 0 0 %.2f %.2f %.2f cm\n",$w,$h,$x,$py)
            ."/{$img_id} Do\nQ\n";

        return $img_id;
    }

    /* ── Fetch + convert image to JPEG bytes ── */
    private function _get_jpeg($src){
        // Local file (WordPress upload path)
        if(file_exists($src)){
            $raw = file_get_contents($src);
        } else {
            // URL fetch — try wp_remote_get if available, else curl, else file_get_contents
            if(function_exists('wp_remote_get')){
                $resp = wp_remote_get($src, ['timeout'=>8,'sslverify'=>false]);
                $raw  = is_wp_error($resp) ? false : wp_remote_retrieve_body($resp);
            } elseif(function_exists('curl_init')){
                $ch=curl_init($src);
                curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>1,CURLOPT_SSL_VERIFYPEER=>0]);
                $raw=curl_exec($ch); curl_close($ch);
            } else {
                $raw = @file_get_contents($src);
            }
        }
        if(!$raw) return false;

        // Convert to JPEG using GD if available
        if(function_exists('imagecreatefromstring')){
            $img = @imagecreatefromstring($raw);
            if(!$img) return false;
            ob_start();
            imagejpeg($img,null,90);
            $jpeg = ob_get_clean();
            imagedestroy($img);
            return $jpeg;
        }
        // No GD — return raw if already JPEG
        if(substr($raw,0,2)==="\xFF\xD8") return $raw;
        return false;
    }

    /* ── Y helpers ── */
    public function y(){return $this->y;}
    public function set_y($v){$this->y=$v;}
    public function advance($v){$this->y+=$v;return $this->y;}
    public function check_page($needed=30){
        if($this->y+$needed>800){$this->_np();return true;}return false;
    }

    /* ── Output ── */
    public function output(){
        $this->pages[]=$this->cur;
        $n=count($this->pages);
        $buf="%PDF-1.4\n";
        $off=[];

        $wr=function($id,$body)use(&$buf,&$off){
            $off[$id]=strlen($buf);
            $buf.="{$id} 0 obj\n{$body}\nendobj\n";
        };

        // Font ids
        $fhid  = 3+$n*2;
        $fhbid = $fhid+1;
        $img_base_id = $fhbid+1;

        // Build resource dict with images
        $img_res='';
        foreach(array_keys($this->img_data) as $i=>$img_id){
            $obj_id=$img_base_id+$i;
            $img_res.="/{$img_id} {$obj_id} 0 R ";
        }
        $res='<</Font<</Helvetica '.$fhid.' 0 R /Helvetica-Bold '.$fhbid.' 0 R>>'
            .($img_res?"/XObject<<{$img_res}>>":"")
            .'>>';

        // Catalog
        $wr(1,'<</Type /Catalog /Pages 2 0 R>>');
        $kids=implode(' ',array_map(fn($i)=>(3+$i).' 0 R',range(0,$n-1)));
        $wr(2,'<</Type /Pages /Kids ['.$kids.'] /Count '.$n.'>>');

        // Pages + content streams
        foreach($this->pages as $i=>$stream){
            $pid=3+$i; $sid=3+$n+$i;
            $wr($pid,'<</Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Contents '.$sid.' 0 R /Resources '.$res.'>>');
            $len=strlen($stream);
            $wr($sid,"<</Length {$len}>>\nstream\n{$stream}\nendstream");
        }

        // Fonts
        $wr($fhid, '<</Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding>>');
        $wr($fhbid,'<</Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding>>');

        // Image XObjects
        foreach(array_values($this->img_data) as $i=>$img){
            $obj_id=$img_base_id+$i;
            $len=strlen($img['bytes']);
            $wr($obj_id,"<</Type /XObject /Subtype /Image /Width {$img['w']} /Height {$img['h']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$len}>>\nstream\n{$img['bytes']}\nendstream");
        }

        // Xref
        $xp=strlen($buf);
        $tot=max(array_keys($off))+1;
        $buf.="xref\n0 {$tot}\n0000000000 65535 f \n";
        for($i=1;$i<$tot;$i++){
            $buf.=isset($off[$i])?sprintf("%010d 00000 n \n",$off[$i]):"0000000000 65535 f \n";
        }
        $buf.="trailer\n<</Size {$tot} /Root 1 0 R>>\nstartxref\n{$xp}\n%%EOF\n";
        return $buf;
    }
}


/**
 * SGC_IIP_PDF v1.0.7
 */
class SGC_IIP_PDF {

    public static function get_output_dir(){
        $u=wp_upload_dir();
        $d=$u['basedir'].'/sgc-iip-pdfs/';
        if(!file_exists($d)){
            wp_mkdir_p($d);
            file_put_contents($d.'.htaccess',"Options -Indexes\n");
            file_put_contents($d.'index.php','<?php // Silence');
        }
        return $d;
    }
    public static function get_output_url($f){
        return wp_upload_dir()['baseurl'].'/sgc-iip-pdfs/'.$f;
    }
    public static function register_download_handler(){
        add_action('wp_ajax_sgc_iip_download_pdf',[__CLASS__,'handle_download']);
    }
    public static function handle_download(){
        check_ajax_referer('sgc_iip_nonce','nonce');
        if(!current_user_can('manage_woocommerce')) wp_die('Unauthorized');
        $f=sanitize_file_name($_GET['sgc_iip_pdf']??'');
        $path=self::get_output_dir().$f;
        if(!$f||!file_exists($path)) wp_die('Not found.');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="'.$f.'"');
        header('Content-Length: '.filesize($path));
        readfile($path); exit;
    }

    public static function generate_pi(array $pi){
        $p=new SGC_PDF_Engine();
        self::_pi($p,$pi);
        $out=$p->output();
        $dir=self::get_output_dir();
        // v1.8.5: Random suffix prevents URL guessing of PI PDFs
        $rand  = substr( md5( $pi['pi_number'] . get_option( 'auth_key', wp_generate_password(32) ) ), 0, 8 );
        $fname = 'PI-' . sanitize_file_name( $pi['pi_number'] ?? 'draft' ) . '-' . $rand . '.pdf';
        file_put_contents($dir.$fname,$out);
        return $dir.$fname;
    }
    public static function generate_packlist(array $pl){
        $p=new SGC_PDF_Engine();
        self::_pl($p,$pl);
        $dir=self::get_output_dir();
        $fname='PL-'.sanitize_file_name($pl['pl_number']??'pl').'.pdf';
        file_put_contents($dir.$fname,$p->output());
        return $dir.$fname;
    }

    /* ════════════════════════════════════════════════════════════════
       PROFORMA INVOICE  v1.0.7
    ════════════════════════════════════════════════════════════════ */
    private static function _pi(SGC_PDF_Engine $p, array $pi){
        $ML=43; $MR=43; $W=$p->W-$ML-$MR;  // 15mm each side (1mm = 2.835pt)

        /* ── Data ── */
        $pin    = $pi['pi_number']      ?? 'DRAFT';
        $buyer  = $pi['buyer_name']     ?? '';
        $bco    = $pi['buyer_company']  ?? '';
        $bct    = $pi['buyer_country']  ?? '';
        $bph    = $pi['buyer_phone']    ?? '';
        $cur    = $pi['currency']       ?? 'USD';
        $inr    = floatval($pi['inr_rate']           ?? 83.5);
        $inc    = $pi['incoterm']       ?? 'FOB';
        $prt    = $pi['port']           ?? 'New Delhi, India';
        $pay    = $pi['payment_terms']  ?? '30% advance, 70% before shipment';
        $del    = $pi['delivery_days']  ?? '15-20 working days';
        $val    = $pi['valid_until']    ?? '';
        $notes  = $pi['notes']          ?? '';
        $items  = $pi['line_items']     ?? [];
        $sub    = floatval($pi['subtotal']            ?? 0);
        $pkg    = floatval($pi['packaging_total']     ?? 0);
        $oemv   = floatval($pi['oem_charge']          ?? 0);
        $disc   = floatval($pi['discount']            ?? 0);
        $freight= floatval($pi['freight_charge']      ?? 0);
        $pdate  = isset($pi['created_at'])?date('d M Y',strtotime($pi['created_at'])):date('d M Y');

        // Bill To / Ship To
        $bill_name = $pi['bill_to_name']    ?? $buyer;
        $bill_co   = $pi['bill_to_company'] ?? $bco;
        $bill_addr = $pi['bill_to_address'] ?? ($pi['buyer_address'] ?? '');
        $ship_same = (int)($pi['ship_to_same'] ?? 1);
        $ship_name = $pi['ship_to_name']    ?? '';
        $ship_co   = $pi['ship_to_company'] ?? '';
        $ship_addr = $pi['ship_to_address'] ?? '';
        $pay_method= $pi['payment_method']  ?? 'bank';

        // ── DOMESTIC vs EXPORT DETECTION ──────────────────────────
        $is_dom = in_array(strtolower(trim($bct)), ['india','in','bharat'], true)
               || strtolower(trim($cur)) === 'inr';

        // ── GST LOGIC ─────────────────────────────────────────────
        // Export = Zero-rated (GST Act S.16), Domestic = 18% (or configured %)
        $gst_pct    = $is_dom ? floatval($pi['gst_percent'] ?? 18) : 0;
        $taxable    = $sub + $pkg + $oemv - $disc;
        $gst_amount = $is_dom && $gst_pct > 0 ? round($taxable * $gst_pct / 100, 2) : 0;
        $grand      = $taxable + $gst_amount + $freight;
        $grand_inr  = $is_dom ? $grand : $grand * $inr;

        // GST type (IGST vs CGST+SGST) — Haryana seller
        $buyer_state_lower = strtolower($pi['buyer_state'] ?? '');
        $same_state = (strpos($buyer_state_lower,'haryana')!==false);
        $cgst = $same_state ? round($gst_amount/2,2) : 0;
        $sgst = $same_state ? round($gst_amount/2,2) : 0;
        $igst = $same_state ? 0 : $gst_amount;

        // Currency display
        $csym = $is_dom ? 'Rs.' : (['USD'=>'$','GBP'=>'GBP ','EUR'=>'EUR '][$cur] ?? $cur.' ');

        // Company info
        $co   = get_option('sgc_iip_company_name','SGC LABS(r) Scientific Glassware');
        $ca   = get_option('sgc_iip_company_address','Ambala, Haryana - 133001, India');
        $cph  = get_option('sgc_iip_company_phone','+91 999 641 6935');
        $cem  = get_option('sgc_iip_company_email','info@labsglass.com');
        $cwb  = get_option('sgc_iip_company_website','https://labsglass.com');
        $cgs  = get_option('sgc_iip_company_gst','');
        $cie  = get_option('sgc_iip_company_iec','');
        $bbn  = get_option('sgc_iip_bank_beneficiary','SGC Labs');
        $bnm  = get_option('sgc_iip_bank_name','');
        $bac  = get_option('sgc_iip_bank_account','');
        $bsw  = get_option('sgc_iip_bank_swift','');
        $bif  = get_option('sgc_iip_bank_ifsc','');
        $bbr  = get_option('sgc_iip_bank_branch','Ambala, India');
        $sig_url  = get_option('sgc_iip_signature_img','');
        $seal_url = get_option('sgc_iip_seal_img','');

        // Payment method settings
        $pm_paypal_email = get_option('sgc_iip_paypal_email','');
        $pm_paypal_link  = get_option('sgc_iip_paypal_link','');
        $pm_wise_email   = get_option('sgc_iip_wise_email','');
        $pm_wise_link    = get_option('sgc_iip_wise_link','');
        $pm_upi_id       = $pi['pi_upi_id']    ?: get_option('sgc_iip_upi_id','');
        $pm_gpay_name    = $pi['pi_upi_name']  ?: get_option('sgc_iip_gpay_name','SGC Labs');

        $show_bank   = in_array($pay_method,['bank','bank_upi'],true)||!$pay_method;
        $show_paypal = in_array($pay_method,['paypal','paypal_wise'],true)&&$pm_paypal_email;
        $show_wise   = in_array($pay_method,['wise','paypal_wise'],true)&&$pm_wise_email;
        $show_upi    = in_array($pay_method,['upi','bank_upi'],true)&&$pm_upi_id;

        /* ── HEADER ── */
        $p->fill(26,26,46); $p->rect(0,0,$p->W,58);
        $p->text($ML,18,$co,true,15,255,255,255);
        $p->text($ML,31,'Laboratory Glassware Manufacturer & Exporter  |  ISO Certified  |  Est. 2003',false,8,144,144,170);
        $p->text($ML,42,$ca.'  |  '.$cph.'  |  '.$cem,false,7.5,110,110,140);
        if($is_dom&&$cgs) $p->text($ML,51,'GSTIN: '.$cgs.($cie?' | IEC: '.$cie:''),false,7,130,130,150);
        $p->text($p->W-205,16,'PROFORMA INVOICE',true,14,46,160,67);
        $p->text($p->W-205,32,'PI No: '.$pin,false,9,190,190,210);
        $p->text($p->W-205,43,'Date: '.$pdate,false,9,190,190,210);
        if(!$is_dom) $p->text($p->W-205,52,'Export under LUT/Bond — Zero Rated',false,7.5,100,200,100);

        $p->fill(46,125,50); $p->rect(0,58,$p->W,3);

        /* ── ADDRESS ROW: 3 columns ── */
        $col=$W/3; $y=68; $rh=62;
        $p->fill(248,249,250); $p->rect($ML,$y,$W,$rh);
        $p->stroke(46,125,50); $p->lw(0.5);
        $p->line($ML,$y,$ML+$W,$y);

        // Exporter
        $p->text($ML+5,$y+9,'EXPORTER / SELLER',true,7,100,100,110);
        $p->text($ML+5,$y+20,$co,true,9,26,26,46);
        $p->text($ML+5,$y+31,$ca,false,8,70,70,70);
        if($cgs) $p->text($ML+5,$y+40,'GSTIN: '.$cgs.($cie?' | IEC: '.$cie:''),false,7,80,80,80);
        $p->text($ML+5,$y+50,$cem,false,7.5,24,95,165);

        $p->stroke(200,200,200); $p->lw(0.3);
        $p->line($ML+$col,$y,$ML+$col,$y+$rh);

        // Bill To
        $bx=$ML+$col+5;
        $bem = $pi['buyer_email'] ?? '';
        $p->text($bx,$y+9,'BILL TO (INVOICE)',true,7,30,80,210);
        $p->text($bx,$y+20,($bill_name?:$buyer).($bill_co?' — '.$bill_co:''),true,9,26,26,46);
        $p->text($bx,$y+31,$bct,false,8,70,70,70);
        if($bph) $p->text($bx,$y+40,'Ph: '.$bph,false,7.5,70,70,70);
        if($bem) $p->text($bx,$y+49,$bem,false,7,70,100,180);
        if($bill_addr){$al=explode("\n",$bill_addr);$p->text($bx,$y+57,substr($al[0]??'',0,38),false,7,80,80,80);}

        $p->line($ML+$col*2,$y,$ML+$col*2,$y+$rh);

        // Ship To
        $sx=$ML+$col*2+5;
        $p->text($sx,$y+9,'SHIP TO (DELIVERY)',true,7,30,130,50);
        if($ship_same||!$ship_name){
            $p->text($sx,$y+20,'Same as Bill To',false,9,70,70,70);
            $p->text($sx,$y+31,'(See billing address)',false,7.5,120,120,120);
        } else {
            $p->text($sx,$y+20,$ship_name.($ship_co?' — '.$ship_co:''),true,9,26,26,46);
            $p->text($sx,$y+31,$bct,false,8,70,70,70);
            if($ship_addr){$sl=explode("\n",$ship_addr);
                $p->text($sx,$y+40,substr($sl[0]??'',0,36),false,7,80,80,80);
                if(!empty($sl[1]))$p->text($sx,$y+50,substr($sl[1],0,36),false,7,80,80,80);
            }
        }

        /* ── META ROW ── */
        $y=136; $mw=$W/4;
        $meta=[['Incoterm',$inc.(!$is_dom?' (Export)':'')],['Port / Delivery',$prt],['Currency',$cur],['Valid Until',$val]];
        foreach($meta as $mi=>$m){
            $mx=$ML+$mi*$mw;
            $p->fill($mi%2?255:245,$mi%2?255:245,$mi%2?255:250);
            $p->rect($mx,$y,$mw,24);
            $p->text($mx+4,$y+9,$m[0],false,7.5,100,100,110);
            $p->text($mx+4,$y+20,$m[1],true,9,26,26,46);
        }

        /* ── LINE ITEMS TABLE ── */
        $y=165;
        // Column definitions: [label, width, align]
        $cols = [
            ['#',          20, 'C'],
            ['Product Description', $is_dom?146:154,'L'],
            ['HSN/HS Code', 52, 'C'],
            ['Qty',         28, 'C'],
            ['Unit '.$csym, 60,'R'],
            ['Pkg',         38,'R'],
            ['Total '.$csym,65,'R'],
        ];
        $cx=$ML;
        foreach($cols as $c){
            $p->cell($cx,$y,$c[1],14,$c[0],$c[2],true,8,26,26,46,255,255,255);
            $cx+=$c[1];
        }
        $y+=14;

        foreach($items as $ri=>$item){
            $p->check_page(18);
            $bg=$ri%2?[255,255,255]:[249,249,249];
            $cx=$ML;
            $unit_v = floatval($item['unit_price']??0);
            $pkg_v  = floatval($item['packaging_per_unit']??0);
            $oem_v  = floatval($item['oem_charge']??0);
            $qty_v  = floatval($item['qty']??0);
            $tot_v  = floatval($item['total']??($qty_v*($unit_v+$pkg_v+$oem_v)));
            // HSN code from line item or HS code
            $hsn = $item['hs_code'] ?? '7017.20';
            $row=[
                [(string)($ri+1),'C'],
                [($item['product_name']??'').(!empty($item['specs'])?' ('.substr($item['specs'],0,20).')':''),'L'],
                [$hsn,'C'],
                [number_format($qty_v,0),'C'],
                [$csym.number_format($unit_v,2),'R'],
                [$csym.number_format($pkg_v,2),'R'],
                [$csym.number_format($tot_v,2),'R'],
            ];
            foreach($cols as $ci=>$c){
                $p->cell($cx,$y,$c[1],15,$row[$ci][0],$row[$ci][1],$ci===6,9,$bg[0],$bg[1],$bg[2],33,33,33);
                $cx+=$c[1];
            }
            $p->stroke(220,220,220); $p->lw(0.2);
            $p->line($ML,$y+15,$ML+$W,$y+15);
            $y+=15;
        }

        /* ── TOTALS ── */
        $y+=8; $p->check_page(130);
        $tw = min(210, (int)($W * 0.48)); // Max 48% of content width, never exceeds right margin
        $tx = $ML + $W - $tw;             // Aligned to right margin exactly
        $rh2=13;

        // Build totals rows
        $tot_rows = [['Subtotal (Products)',$csym.number_format($sub,2)]];
        if($pkg>0) $tot_rows[]=['Packaging & Handling',$csym.number_format($pkg,2)];
        if($oemv>0) $tot_rows[]=['OEM / Custom Charges',$csym.number_format($oemv,2)];
        if($disc>0) $tot_rows[]=['Discount','-'.$csym.number_format($disc,2)];

        if($is_dom && $gst_pct>0){
            $tot_rows[]=['Taxable Value',$csym.number_format($taxable,2)];
            if($same_state){
                $tot_rows[]=['CGST ('.($gst_pct/2).'%)',$csym.number_format($cgst,2)];
                $tot_rows[]=['SGST ('.($gst_pct/2).'%)',$csym.number_format($sgst,2)];
            } else {
                $tot_rows[]=['IGST ('.$gst_pct.'%)',$csym.number_format($igst,2)];
            }
        } elseif(!$is_dom){
            $tot_rows[]=['GST','Zero Rated (Export LUT)'];
        }
        if($freight>0) $tot_rows[]=['Freight Charges',$csym.number_format($freight,2)];

        foreach($tot_rows as $tr){
            $p->fill(250,250,250); $p->rect($tx,$y,$tw,$rh2);
            $p->stroke(230,230,230); $p->lw(0.2);
            $p->line($tx,$y+$rh2,$tx+$tw,$y+$rh2);
            $p->text($tx+5,$y+$rh2-4,$tr[0],false,8.5,80,80,80);
            // Right-aligned amount: estimate width and subtract from right edge
            $amt_str = (string)$tr[1];
            $amt_w   = strlen($amt_str) * 9 * 0.52;
            $p->text($tx+$tw-$amt_w-4,$y+$rh2-4,$amt_str,true,9,33,33,33);
            $y+=$rh2;
        }

        // Grand total bar
        $p->fill(26,26,46); $p->rect($tx,$y,$tw,22);
        $p->text($tx+5,$y+14,'GRAND TOTAL',true,9.5,255,255,255);
        $grand_str = $csym.number_format($grand,2);
        $grand_w   = strlen($grand_str) * 13 * 0.52;
        $p->text($tx+$tw-$grand_w-4,$y+14,$grand_str,true,13,46,210,80);
        $y+=22;

        // Equivalent
        if(!$is_dom&&$grand>0){
            $p->text($tx+5,$y+9,'Equiv. INR (@Rs.'.$inr.')',false,7.5,130,130,130);
            $eq1_str = 'Rs.'.number_format($grand*$inr,2);
            $eq1_w   = strlen($eq1_str)*7.5*0.52;
            $p->text($tx+$tw-$eq1_w-4,$y+9,$eq1_str,false,7.5,130,130,130);
            $y+=12;
        }
        if($is_dom&&$grand>0&&$inr>0){
            $p->text($tx+5,$y+9,'USD equiv. (@Rs.'.$inr.')',false,7.5,130,130,130);
            $eq2_str = '$'.number_format($grand/$inr,2);
            $eq2_w   = strlen($eq2_str)*7.5*0.52;
            $p->text($tx+$tw-$eq2_w-4,$y+9,$eq2_str,false,7.5,130,130,130);
            $y+=12;
        }

        /* ── PAYMENT SECTION ── */
        $y+=12; $p->check_page(100);
        $half=($W/2)-4;
        $box_h=95;
        if($show_paypal&&$show_wise) $box_h=115;
        if($show_upi) $box_h=max($box_h,105);

        // Left: Bank / Payment
        $p->fill(255,252,230); $p->rect($ML,$y,$half,$box_h);
        $p->stroke(255,193,7); $p->lw(2.5);
        $p->line($ML,$y,$ML,$y+$box_h);
        $bx=$ML+7; $by=$y+11;

        if($show_bank){
            $p->text($bx,$by,'BANK TRANSFER DETAILS',true,8,170,90,0); $by+=11;
            $p->text($bx,$by,'Beneficiary: ',true,7,90,60,0);
            $p->text($bx+52,$by,$bbn,false,7,33,33,33); $by+=9;
            if($is_dom){
                $p->text($bx,$by,'IFSC: ',true,7,90,60,0);
                $p->text($bx+52,$by,$bif,false,7,33,33,33);
            } else {
                $p->text($bx,$by,'SWIFT: ',true,7,90,60,0);
                $p->text($bx+52,$by,$bsw,false,7,33,33,33);
            }
            $by+=9;
            $p->text($bx,$by,'A/C No: ',true,7,90,60,0);
            $p->text($bx+52,$by,$bac,false,7,33,33,33); $by+=9;
            $p->text($bx,$by,'Bank: ',true,7,90,60,0);
            $p->text($bx+52,$by,$bnm.',  '.$bbr,false,7,33,33,33); $by+=9;
            if($is_dom&&$cgs){
                $p->text($bx,$by,'GSTIN: ',true,7,90,60,0);
                $p->text($bx+52,$by,$cgs,false,7,33,33,33); $by+=9;
            }
        }
        if($show_upi&&$pm_upi_id){
            $p->stroke(255,193,7); $p->lw(0.4);
            if($show_bank){$p->line($bx,$by,$bx+$half-14,$by);$by+=6;}
            $p->text($bx,$by,'GPay / UPI PAYMENT',true,7.5,0,100,50); $by+=10;
            $p->text($bx,$by,'UPI ID: ',true,7.5,0,80,40);
            $p->text($bx+34,$by,$pm_upi_id,true,8,26,26,46); $by+=9;
            $p->text($bx,$by,'Name: ',true,7,0,80,40);
            $p->text($bx+34,$by,$pm_gpay_name,false,7,33,33,33); $by+=8;
            $p->text($bx,$by,'Scan via GPay / PhonePe / Paytm',false,7,80,80,80); $by+=4;

            // ── Embed QR image — per-PI > settings custom > auto-generate ──
            $qr_img_opt = $pi['pi_upi_qr_url'] ?: get_option('sgc_iip_upi_qr_img','');
            $qr_src     = '';
            if($qr_img_opt){
                $qr_src = $qr_img_opt;
            } elseif($pm_upi_id){
                $qr_src = 'https://quickchart.io/qr?text='.urlencode('upi://pay?pa='.$pm_upi_id.'&pn='.urlencode($pm_gpay_name)).'&width=80&height=80&format=png';
            }
            if($qr_src){
                $qr_placed = $p->image($bx, $by, 44, 44, $qr_src);
                if($qr_placed){
                    $p->text($bx+4,$by+46,'Scan to Pay',false,6,100,100,100);
                    $by += 54; // QR height (44) + label (6) + gap (4)
                }
            }
        }
        if($show_paypal&&$pm_paypal_email){
            if($show_bank||$show_upi){$p->stroke(255,193,7);$p->lw(0.3);$p->line($bx,$by,$bx+$half-14,$by);$by+=5;}
            $p->text($bx,$by,'PAYPAL',true,7.5,0,50,150); $by+=9;
            $p->text($bx,$by,'Email: ',true,7,40,70,130);
            $p->text($bx+28,$by,$pm_paypal_email,false,7,33,33,33); $by+=8;
            if($pm_paypal_link){$p->text($bx,$by,'Link: ',true,7,40,70,130);$p->text($bx+28,$by,$pm_paypal_link,false,7,33,33,33);$by+=8;}
        }
        if($show_wise&&$pm_wise_email){
            if($show_bank||$show_paypal){$p->stroke(255,193,7);$p->lw(0.3);$p->line($bx,$by,$bx+$half-14,$by);$by+=5;}
            $p->text($bx,$by,'WISE TRANSFER',true,7.5,80,20,150); $by+=9;
            $p->text($bx,$by,'Email: ',true,7,80,30,130);
            $p->text($bx+28,$by,$pm_wise_email,false,7,33,33,33); $by+=8;
            if($pm_wise_link){$p->text($bx,$by,'Link: ',true,7,80,30,130);$p->text($bx+28,$by,$pm_wise_link,false,7,33,33,33);}
        }

        // Right: Terms
        $px=$ML+$half+8;
        $p->fill(232,245,233); $p->rect($px,$y,$half,$box_h);
        $py2=$y+11;
        $p->text($px+5,$py2,'PAYMENT TERMS',true,8,26,80,30); $py2+=11;
        $p->text($px+5,$py2,$pay,false,8,33,33,33); $py2+=15;
        $p->text($px+5,$py2,'DELIVERY TIME',true,8,26,80,30); $py2+=11;
        $p->text($px+5,$py2,$del,false,8,33,33,33); $py2+=15;
        $p->text($px+5,$py2,'VALIDITY',true,8,26,80,30); $py2+=11;
        $p->text($px+5,$py2,'Valid until: '.$val,false,8,170,30,30); $py2+=13;
        if(!$is_dom){
            $p->fill(230,245,230); $p->rect($px,$py2-2,$half-1,16);
            $p->text($px+5,$py2+10,'Export under GST LUT/Bond | Zero Rated Supply',true,7.5,30,100,30);
        }

        // ── Dynamic box height: use actual content bottom if taller than fixed box ──
        $left_bottom  = $by + 8;                    // actual bottom of left column content
        $box_bottom   = $y + $box_h;                // fixed box bottom
        $actual_bottom = max($left_bottom, $box_bottom);

        // Redraw left box background tall enough to cover QR if needed
        if($left_bottom > $box_bottom){
            $p->fill(255,252,230);
            $p->rect($ML,$box_bottom,$half,$left_bottom-$box_bottom+8);
        }

        /* ── NOTES ── */
        $y = $actual_bottom + 10;
        if($notes){
            $p->check_page(20);
            $p->fill(244,247,252); $p->rect($ML,$y,$W,18);
            $p->text($ML+5,$y+12,'Note: '.substr($notes,0,145),false,8,70,70,100);
            $y+=22;
        }

        /* ── SIGNATURE + STAMP ── */
        $y+=10; $p->check_page(55);
        $sig_y  = $y;
        $sig_h  = 40;
        $sig_w  = 140;

        // Left signature block
        $p->stroke(180,180,180); $p->lw(0.4);
        $p->line($ML+10,$sig_y+$sig_h,$ML+10+$sig_w,$sig_y+$sig_h);

        // Try to embed signature image
        $sig_shown = false;
        if($sig_url){
            // Convert URL to local file path if WordPress upload
            $upload_dir = wp_upload_dir();
            $local_path = str_replace($upload_dir['baseurl'],$upload_dir['basedir'],$sig_url);
            $src = file_exists($local_path) ? $local_path : $sig_url;
            $ok = $p->image($ML+12, $sig_y+2, $sig_w-4, $sig_h-6, $src);
            if($ok) $sig_shown = true;
        }
        if(!$sig_shown){
            // Placeholder dashes
            $p->text($ML+20,$sig_y+20,'(signature)',false,8,200,200,200);
        }

        $p->text($ML+10,$sig_y+$sig_h+8,'Authorised Signatory',false,8.5,33,33,33);
        $p->text($ML+10,$sig_y+$sig_h+18,$co,false,7.5,80,80,80);

        // Right stamp block
        $stamp_x = $ML+$W-$sig_w-20;
        $stamp_shown = false;
        if($seal_url){
            $upload_dir = wp_upload_dir();
            $local_path = str_replace($upload_dir['baseurl'],$upload_dir['basedir'],$seal_url);
            $src = file_exists($local_path) ? $local_path : $seal_url;
            $ok = $p->image($stamp_x, $sig_y, $sig_w, $sig_h, $src);
            if($ok) $stamp_shown = true;
        }
        if(!$stamp_shown){
            $p->stroke(180,180,180); $p->lw(1);
            // Draw stamp circle placeholder
            $cx=$stamp_x+$sig_w/2; $cy=$sig_y+$sig_h/2;
            $p->text($cx-30,$cy+4,'COMPANY SEAL',false,7,200,200,200);
        }

        $p->stroke(180,180,180); $p->lw(0.4);
        $p->line($stamp_x,$sig_y+$sig_h,$stamp_x+$sig_w,$sig_y+$sig_h);
        $p->text($stamp_x,$sig_y+$sig_h+8,'Company Seal / Stamp',false,8.5,33,33,33);
        $p->text($stamp_x,$sig_y+$sig_h+18,"Buyer's Signature & Acceptance",false,7.5,80,80,80);

        /* ── FOOTER ── */
        $p->fill(245,247,250); $p->rect(0,802,$p->W,40);
        $p->stroke(26,26,46); $p->lw(1.5);
        $p->line(0,802,$p->W,802);
        $team = $is_dom ? 'Sales Division' : 'Export Division';
        $p->text($ML,816,$co.' | '.$ca.' | '.$cem,false,7.5,90,90,100);
        $p->text($ML,828,$cwb.' | '.$cph.' | '.$team,false,7.5,90,90,100);
        $p->fill(26,26,46); $p->rect($p->W-135,804,135,36);
        $p->text($p->W-131,814,'PI No: '.$pin,true,8,255,255,255);
        $p->text($p->W-131,825,'Total: '.$csym.number_format($grand,2),true,8.5,46,210,80);
        $p->text($p->W-131,836,'v'.SGC_IIP_VERSION.' | '.date('d M Y'),false,6.5,150,150,160);
    }

    /* ════════════════════════════════════════════════════════════════
       PACKING LIST
    ════════════════════════════════════════════════════════════════ */
    private static function _pl(SGC_PDF_Engine $p, array $pl){
        $ML=36;$MR=36;$W=$p->W-$ML-$MR;
        $pln=$pl['pl_number']??'PL-001'; $boxes=$pl['boxes']??[];
        $co=get_option('sgc_iip_company_name','SGC LABS(r)');

        $p->fill(26,26,46);$p->rect(0,0,$p->W,48);
        $p->text($ML,17,$co,true,14,255,255,255);
        $p->text($ML,30,'Laboratory Glassware Manufacturer & Exporter',false,8,144,144,170);
        $p->text($p->W-220,15,'PACKING LIST',true,16,46,160,67);
        $p->text($p->W-220,32,'PL No: '.$pln.'   Date: '.date('d M Y'),false,9,190,190,210);
        $p->fill(46,125,50);$p->rect(0,48,$p->W,3);

        $y=58;$sw=$W/4;
        $net=floatval($pl['total_net_wt']??0);$grs=floatval($pl['total_gross_wt']??0);$cbm=floatval($pl['total_cbm']??0);
        foreach([['Total Cartons',count($boxes)],['Net Wt (kg)',number_format($net,2)],['Gross Wt (kg)',number_format($grs,2)],['CBM (m3)',number_format($cbm,4)]] as $i=>$s){
            $sx=$ML+$i*$sw;
            $p->fill($i%2?240:248,$i%2?244:250,$i%2?240:248);$p->rect($sx,$y,$sw,28);
            $p->text($sx+5,$y+10,$s[0],false,8,100,100,100);
            $p->text($sx+5,$y+22,(string)$s[1],true,12,26,26,46);
        }
        $y+=34;
        $cols=[['Box#',32,'C'],['Product',168,'L'],['SKU',55,'C'],['Qty',36,'C'],['Net kg',50,'R'],['Gross kg',56,'R'],['L×W×H',66,'C'],['CBM',52,'R']];
        $cx=$ML;
        foreach($cols as $c){$p->cell($cx,$y,$c[1],13,$c[0],$c[2],true,8,26,26,46,255,255,255);$cx+=$c[1];}
        $y+=13;
        foreach($boxes as $i=>$box){
            $bg=$i%2?[255,255,255]:[249,249,249];$cx=$ML;
            $dims=($box['length_cm']??0).'x'.($box['width_cm']??0).'x'.($box['height_cm']??0);
            $row=[[(string)($box['box_no']??$i+1),'C'],[$box['product']??'','L'],[$box['sku']??'','C'],[number_format($box['qty']??0,0),'C'],
                  [number_format(floatval($box['net_wt']??0),3),'R'],[number_format(floatval($box['gross_wt']??0),3),'R'],[$dims,'C'],[number_format(floatval($box['cbm']??0),4),'R']];
            foreach($cols as $ci=>$c){$p->cell($cx,$y,$c[1],14,$row[$ci][0],$row[$ci][1],false,8,$bg[0],$bg[1],$bg[2],33,33,33);$cx+=$c[1];}
            $p->stroke(220,220,220);$p->lw(0.2);$p->line($ML,$y+14,$ML+$W,$y+14);
            $y+=14;if($y>780)break;
        }
        // Signature
        $sy=790;
        $sig_url=get_option('sgc_iip_signature_img','');
        if($sig_url){
            $upload_dir=wp_upload_dir();
            $local=str_replace($upload_dir['baseurl'],$upload_dir['basedir'],$sig_url);
            $src=file_exists($local)?$local:$sig_url;
            $p->image($ML+10,$sy-38,130,32,$src);
        }
        $p->stroke(180,180,180);$p->lw(0.4);
        $p->line($ML+10,$sy,$ML+150,$sy);
        $p->text($ML+10,$sy+10,'Authorised Signatory — '.$co,false,8,80,80,80);
        $p->fill(245,247,250);$p->rect(0,802,$p->W,40);
        $p->stroke(26,26,46);$p->lw(1.5);$p->line(0,802,$p->W,802);
        $p->text($ML,818,$co.' — Packing List '.$pln.' — '.date('d M Y'),false,8,90,90,100);
    }
}

SGC_IIP_PDF::register_download_handler();
