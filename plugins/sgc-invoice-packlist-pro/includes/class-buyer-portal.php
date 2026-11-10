<?php
/**
 * SGC IIP — Buyer Self-Service Portal
 *
 * Token-based portal for international buyers (no login needed):
 *  — View Proforma Invoice summary
 *  — Accept / Reject PI with one click
 *  — Upload advance payment proof (image/PDF)
 *  — View payment progress bar + history
 *  — Track dispatch (AWB, courier, EDD)
 *  — Send message to seller
 *
 * URL: yoursite.com/sgc-pi-portal/?sgc_token=XXXXXXXX
 *
 * @package SGC_IIP
 * @since   1.3.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class SGC_IIP_Buyer_Portal {

    // ── Rewrite rules ─────────────────────────────────────────────────────────
    public static function register_rewrite() {
        add_rewrite_rule('^sgc-pi-portal/?$','index.php?sgc_iip_portal=1','top');
    }
    public static function add_query_vars( array $vars ): array {
        $vars[] = 'sgc_iip_portal';
        $vars[] = 'sgc_token';
        return $vars;
    }

    // ── Main entry ────────────────────────────────────────────────────────────
    public static function maybe_render_portal() {
        if ( ! get_query_var('sgc_iip_portal') ) return;

        $token = sanitize_text_field( $_GET['sgc_token'] ?? $_GET['token'] ?? '' );
        if ( empty($token) ) { self::render_error(__('Invalid portal link.','sgc-iip')); exit; }

        global $wpdb;
        $it  = SGC_IIP_DB::t('inquiries');
        $inq = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$it` WHERE pi_accept_token=%s LIMIT 1",$token),ARRAY_A);
        if ( ! $inq ) { self::render_error(__('This link has expired or is invalid.','sgc-iip')); exit; }

        $pt       = SGC_IIP_DB::t('proformas');
        $pi       = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$pt` WHERE inquiry_id=%d ORDER BY id DESC LIMIT 1",$inq['id']),ARRAY_A);
        $dt       = $wpdb->prefix.'sgc_iip_dispatches';
        $dispatch = $wpdb->get_var("SHOW TABLES LIKE '$dt'")===$dt
                  ? $wpdb->get_row($wpdb->prepare("SELECT * FROM `$dt` WHERE inquiry_id=%d ORDER BY id DESC LIMIT 1",$inq['id']),ARRAY_A)
                  : null;
        $payments = SGC_IIP_DB::get_payments(intval($inq['id']));

        if ($_SERVER['REQUEST_METHOD']==='POST') self::handle_action($inq,$pi,$token);
        self::render($inq,$pi,$dispatch,$payments,$token);
        exit;
    }

    // ── Handle buyer POST actions ─────────────────────────────────────────────
    private static function handle_action(array $inq, ?array $pi, string $token) {
        if (!isset($_POST['sgc_portal_nonce']) || !wp_verify_nonce($_POST['sgc_portal_nonce'],'sgc_portal_'.$token)) return;

        global $wpdb;
        $action  = sanitize_key($_POST['portal_action']??'');
        $id      = intval($inq['id']);
        $it      = SGC_IIP_DB::t('inquiries');
        $pt      = SGC_IIP_DB::t('proformas');
        $pi_id   = $pi ? intval($pi['id']) : 0;
        $admin_e = get_option('sgc_iip_company_email',get_option('admin_email'));

        switch($action) {
            case 'accept_pi':
                $wpdb->update($it,['status'=>'accepted','pipeline_stage'=>'accepted'],['id'=>$id]);
                if($pi_id) $wpdb->update($pt,['status'=>'accepted','accepted_at'=>current_time('mysql')],['id'=>$pi_id]);
                SGC_IIP_DB::log($id,'buyer_accepted','Buyer accepted PI via portal.','buyer');
                wp_mail($admin_e, '✅ PI Accepted — '.$inq['name'],
                    "Buyer {$inq['name']} ({$inq['email']}) accepted PI {$pi['pi_number']}.\nPortal: ".home_url('/sgc-pi-portal/?sgc_token='.$token));
                break;

            case 'reject_pi':
                $reason = sanitize_textarea_field(wp_unslash($_POST['reject_reason']??''));
                $wpdb->update($it,['status'=>'lost','pipeline_stage'=>'lost'],['id'=>$id]);
                if($pi_id) $wpdb->update($pt,['status'=>'rejected','buyer_notes'=>$reason],['id'=>$pi_id]);
                SGC_IIP_DB::log($id,'buyer_rejected','Buyer rejected PI. Reason: '.$reason,'buyer');
                wp_mail($admin_e,'❌ PI Rejected — '.$inq['name'],"Buyer {$inq['name']} rejected {$pi['pi_number']}.\nReason: $reason");
                break;

            case 'upload_proof':
                if (!empty($_FILES['payment_proof']['tmp_name']) && $_FILES['payment_proof']['error']===UPLOAD_ERR_OK) {
                    $finfo    = $_FILES['payment_proof'];
                    $allowed  = ['image/jpeg','image/png','image/webp','application/pdf'];
                    $ftype    = wp_check_filetype($finfo['name'])['type']??'';
                    if (in_array($ftype,$allowed,true) && $finfo['size']<=10*1024*1024) {
                        $up   = wp_upload_dir();
                        $dir  = trailingslashit($up['basedir']).'sgc-iip-proofs/'.date('Y/m').'/';
                        wp_mkdir_p($dir);
                        @file_put_contents($dir.'.htaccess','Options -Indexes');
                        @file_put_contents($dir.'index.php','<?php // Silence is golden');
                        $fname = sanitize_file_name(date('Ymd-His').'-proof-'.$id.'-'.$finfo['name']);
                        $dest  = $dir.$fname;
                        $durl  = trailingslashit($up['baseurl']).'sgc-iip-proofs/'.date('Y/m').'/'.$fname;
                        if (move_uploaded_file($finfo['tmp_name'],$dest)) {
                            $wpdb->update($it,['payment_proof_url'=>$durl,'status'=>'advance'],['id'=>$id]);
                            SGC_IIP_DB::log($id,'proof_uploaded','Payment proof uploaded by buyer: '.$fname,'buyer');
                            wp_mail($admin_e,'💰 Payment Proof Uploaded — '.$inq['name'],
                                "Buyer {$inq['name']} uploaded proof.\nPI: {$pi['pi_number']}\nProof: $durl");
                        }
                    }
                }
                break;

            case 'send_message':
                $msg = sanitize_textarea_field(wp_unslash($_POST['buyer_message']??''));
                if($msg) {
                    SGC_IIP_DB::log($id,'buyer_message','Buyer message: '.$msg,'buyer');
                    wp_mail($admin_e,'✉ Buyer Message — '.$inq['name'],
                        "From {$inq['name']} ({$inq['email']}):\n\n$msg\nPI: ".($pi['pi_number']??'—'));
                }
                break;
        }
        wp_safe_redirect(home_url('/sgc-pi-portal/?sgc_token='.$token.'&done='.$action));
        exit;
    }

    // ── Render full portal page ───────────────────────────────────────────────
    private static function render(array $inq,?array $pi,?array $dispatch,array $payments,string $token) {
        $company  = get_option('sgc_iip_company_name','SGC LABS®');
        $logo     = get_option('sgc_iip_company_logo','');
        $status   = $inq['status']??'new';
        $done     = sanitize_key($_GET['done']??'');
        $currency = $pi['currency']??'USD';
        $csym     = ['USD'=>'$','EUR'=>'€','GBP'=>'£','INR'=>'₹','AED'=>'AED '][$currency]??$currency.' ';
        $grand    = floatval($pi['grand_total']??0);
        $paid     = array_sum(array_column($payments,'amount'));
        $balance  = round($grand-$paid,2);
        $pct      = $grand>0 ? min(100,round($paid/$grand*100)) : 0;
        $nonce    = wp_create_nonce('sgc_portal_'.$token);
        $proof_url= $inq['payment_proof_url']??'';

        $stages = [
            'new'=>['Inquiry Received','#3b82f6','📥'],'replied'=>['Replied','#8b5cf6','💬'],
            'pi_sent'=>['PI Sent','#f59e0b','📄'],'accepted'=>['PI Accepted','#10b981','✅'],
            'advance'=>['Advance Received','#f97316','💰'],'won'=>['Order Confirmed','#059669','🏆'],
            'lost'=>['Closed','#6b7280','❌'],'sample'=>['Sample Sent','#f59e0b','📦'],
        ];
        [$slabel,$scolor,$sicon] = $stages[$status]??[ucfirst($status),'#6b7280','•'];

        $done_msgs = [
            'accept_pi'    =>'✅ PI accepted. Our team has been notified.',
            'reject_pi'    =>'Your rejection has been recorded. We will get in touch.',
            'upload_proof' =>'💰 Payment proof received. We will verify within 24 hours.',
            'send_message' =>'✉ Your message has been sent.',
        ];

        ob_start(); ?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php printf('%s — %s', esc_html__('Order Portal','sgc-iip'), esc_html($company)); ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f0f2f5;color:#1a1a1a;min-height:100vh}
.pw{max-width:680px;margin:0 auto;padding:20px 14px 60px}
.hdr{background:#1a1a2e;border-radius:14px;padding:22px 26px;display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;gap:14px;flex-wrap:wrap}
.hdr-logo{height:40px;object-fit:contain}.hdr-co{font-size:22px;font-weight:800;color:#27ae60}
.hdr-name{color:#fff;font-size:13px;font-weight:600}.hdr-sub{color:rgba(255,255,255,.5);font-size:11px;margin-top:2px}
.stage-pill{display:inline-flex;align-items:center;gap:6px;padding:5px 14px;border-radius:20px;font-size:12px;font-weight:600;margin-bottom:14px}
.card{background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.07);margin-bottom:12px;overflow:hidden}
.card-hdr{padding:13px 18px;border-bottom:1px solid #f0f0f0;font-size:13px;font-weight:600;color:#1a1a2e;display:flex;align-items:center;gap:8px}
.card-body{padding:16px 18px}
.row{display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid #f5f5f5;font-size:13px}
.row:last-child{border-bottom:none}.row .l{color:#666}.row .v{font-weight:500}
.grand{background:#f0fdf4;border-radius:8px;padding:11px 14px;display:flex;justify-content:space-between;align-items:center;margin-top:10px}
.grand .l{font-size:13px;font-weight:600;color:#065f46}.grand .v{font-size:20px;font-weight:700;color:#065f46}
.bar-wrap{margin:12px 0 6px}.bar{height:8px;background:#e5e7eb;border-radius:4px;overflow:hidden}
.bar-fill{height:100%;background:#10b981;border-radius:4px;transition:width .6s}.bar-lbls{display:flex;justify-content:space-between;font-size:11px;color:#666;margin-top:4px}
.ptbl{width:100%;border-collapse:collapse;font-size:12px;margin-top:10px}
.ptbl th{background:#f8f9fa;padding:7px 10px;text-align:left;font-weight:600;color:#555;border-bottom:2px solid #eee}
.ptbl td{padding:7px 10px;border-bottom:1px solid #f5f5f5}
.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;line-height:1}
.btn-ok{background:#10b981;color:#fff}.btn-danger{background:#fff;color:#ef4444;border:1.5px solid #ef4444}
.btn-dark{background:#1a1a2e;color:#fff}.btn-out{background:#fff;color:#374151;border:1.5px solid #d1d5db}
.btn-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}
.uzone{border:2px dashed #d1d5db;border-radius:8px;padding:18px;text-align:center;cursor:pointer;transition:.2s;background:#fafafa}
.uzone:hover{border-color:#10b981;background:#f0fdf4}.uzone input{display:none}
.dstep{display:flex;gap:12px;align-items:flex-start;padding:9px 0;border-bottom:1px solid #f5f5f5}
.dstep:last-child{border-bottom:none}.d-ic{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;background:#f0f0f0}
.done-ok{background:#d1fae5;border:1px solid #6ee7b7;border-radius:8px;padding:11px 14px;font-size:13px;color:#065f46;margin-bottom:12px}
.track{background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px 14px;font-size:13px}
.track-n{font-size:18px;font-weight:700;color:#1d4ed8;font-family:monospace}
textarea{width:100%;padding:9px 11px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;font-family:inherit;resize:vertical}
.bdg{font-size:10px;font-weight:600;padding:2px 7px;border-radius:8px;background:#d1fae5;color:#065f46}
.footer{text-align:center;font-size:11px;color:#9ca3af;margin-top:22px;line-height:1.8}
@media(max-width:460px){.btn-row{flex-direction:column}.grand .v{font-size:16px}}
</style>
</head>
<body>
<div class="pw">

<div class="hdr">
  <div>
    <?php if($logo): ?><img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($company); ?>" class="hdr-logo"><?php else: ?>
    <div class="hdr-co"><?php echo esc_html($company); ?></div><?php endif; ?>
    <div class="hdr-sub"><?php esc_html_e('Buyer Order Portal','sgc-iip'); ?></div>
  </div>
  <div style="text-align:right">
    <div class="hdr-name"><?php echo esc_html($inq['name']??''); ?></div>
    <div class="hdr-sub"><?php echo esc_html($inq['company']??''); ?></div>
    <div class="hdr-sub" style="margin-top:4px;font-family:monospace"><?php echo esc_html($pi['pi_number']??($inq['inq_number']??'')); ?></div>
  </div>
</div>

<?php if($done && isset($done_msgs[$done])): ?>
<div class="done-ok"><?php echo esc_html($done_msgs[$done]); ?></div>
<?php endif; ?>

<div style="margin-bottom:12px">
  <span class="stage-pill" style="background:<?php echo esc_attr($scolor); ?>20;color:<?php echo esc_attr($scolor); ?>;border:1px solid <?php echo esc_attr($scolor); ?>40">
    <?php echo esc_html($sicon.' '.$slabel); ?>
  </span>
</div>

<?php if($pi): ?>
<div class="card">
  <div class="card-hdr">📄 <?php esc_html_e('Proforma Invoice','sgc-iip'); ?> — <span style="font-family:monospace;color:#6d28d9;font-size:12px"><?php echo esc_html($pi['pi_number']); ?></span></div>
  <div class="card-body">
    <?php if(!empty($pi['incoterm'])): ?><div class="row"><span class="l"><?php esc_html_e('Incoterm / Port','sgc-iip'); ?></span><span class="v"><?php echo esc_html($pi['incoterm'].' '.($pi['port']??'')); ?></span></div><?php endif; ?>
    <?php if(!empty($pi['payment_terms'])): ?><div class="row"><span class="l"><?php esc_html_e('Payment Terms','sgc-iip'); ?></span><span class="v"><?php echo esc_html($pi['payment_terms']); ?></span></div><?php endif; ?>
    <?php if(!empty($pi['created_at'])): ?><div class="row"><span class="l"><?php esc_html_e('Issued','sgc-iip'); ?></span><span class="v"><?php echo esc_html(date('d M Y',strtotime($pi['created_at']))); ?></span></div><?php endif; ?>
    <div class="grand"><span class="l"><?php esc_html_e('Grand Total','sgc-iip'); ?></span><span class="v"><?php echo esc_html($csym.number_format($grand,2)); ?></span></div>
    <?php if(!empty($pi['pdf_path']) && file_exists($pi['pdf_path'])): ?>
    <div style="margin-top:10px">
      <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=sgc_iip_generate_pdf&pi_id='.$pi['id'].'&token='.urlencode($token))); ?>"
         class="btn btn-out" target="_blank" rel="noopener">📥 <?php esc_html_e('Download PI PDF','sgc-iip'); ?></a>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-hdr">💰 <?php esc_html_e('Payment Status','sgc-iip'); ?></div>
  <div class="card-body">
    <div class="bar-wrap">
      <div class="bar"><div class="bar-fill" style="width:<?php echo intval($pct); ?>%"></div></div>
      <div class="bar-lbls">
        <span><?php printf(esc_html__('Paid: %s%s','sgc-iip'),esc_html($csym),esc_html(number_format($paid,2))); ?></span>
        <span><?php echo intval($pct); ?>%</span>
        <span><?php printf(esc_html__('Balance: %s%s','sgc-iip'),esc_html($csym),esc_html(number_format(max(0,$balance),2))); ?></span>
      </div>
    </div>
    <?php if(!empty($payments)): ?>
    <table class="ptbl">
      <thead><tr><th><?php esc_html_e('Date','sgc-iip'); ?></th><th><?php esc_html_e('Type','sgc-iip'); ?></th><th><?php esc_html_e('Amount','sgc-iip'); ?></th><th><?php esc_html_e('Method','sgc-iip'); ?></th></tr></thead>
      <tbody>
      <?php foreach($payments as $p): ?>
        <tr>
          <td><?php echo esc_html($p['payment_date']); ?></td>
          <td><span class="bdg"><?php echo esc_html(ucfirst($p['payment_type'])); ?></span></td>
          <td style="font-weight:600"><?php echo esc_html($csym.number_format(floatval($p['amount']),2)); ?></td>
          <td><?php echo esc_html(str_replace('_',' ',ucfirst($p['method']))); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?><p style="font-size:12px;color:#9ca3af;text-align:center;padding:10px"><?php esc_html_e('No payments recorded yet.','sgc-iip'); ?></p><?php endif; ?>

    <?php if(!in_array($status,['won','lost'],true)): ?>
    <div style="margin-top:16px;padding-top:13px;border-top:1px solid #f0f0f0">
      <div style="font-size:13px;font-weight:600;margin-bottom:9px">📤 <?php esc_html_e('Upload Payment Proof','sgc-iip'); ?></div>
      <?php if($proof_url): ?><div class="done-ok" style="margin-bottom:8px">✅ <?php esc_html_e('Proof received.','sgc-iip'); ?> <a href="<?php echo esc_url($proof_url); ?>" target="_blank"><?php esc_html_e('View','sgc-iip'); ?></a></div><?php endif; ?>
      <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('sgc_portal_'.$token,'sgc_portal_nonce'); ?>
        <input type="hidden" name="portal_action" value="upload_proof">
        <div class="uzone" onclick="document.getElementById('pf').click()">
          <input type="file" id="pf" name="payment_proof" accept="image/*,.pdf" onchange="document.getElementById('pfn').textContent=this.files[0]?.name||''">
          <div style="font-size:22px;margin-bottom:5px">📎</div>
          <div style="font-size:13px;color:#555;font-weight:500"><?php esc_html_e('Click to upload bank TT screenshot or PDF','sgc-iip'); ?></div>
          <div style="font-size:11px;color:#aaa;margin-top:3px"><?php esc_html_e('JPG, PNG, PDF — max 10MB','sgc-iip'); ?></div>
          <div id="pfn" style="margin-top:6px;font-size:12px;color:#10b981;font-weight:500"></div>
        </div>
        <div class="btn-row"><button type="submit" class="btn btn-dark">💰 <?php esc_html_e('Send Payment Proof','sgc-iip'); ?></button></div>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if($pi && $status==='pi_sent'): ?>
<div class="card">
  <div class="card-hdr">🖊 <?php esc_html_e('Review & Accept PI','sgc-iip'); ?></div>
  <div class="card-body">
    <p style="font-size:13px;color:#555;margin-bottom:13px"><?php esc_html_e('Please review and accept the PI. Once accepted, production begins upon advance payment.','sgc-iip'); ?></p>
    <form method="post">
      <?php wp_nonce_field('sgc_portal_'.$token,'sgc_portal_nonce'); ?>
      <input type="hidden" name="portal_action" value="accept_pi">
      <button type="submit" class="btn btn-ok">✅ <?php esc_html_e('Accept Proforma Invoice','sgc-iip'); ?></button>
    </form>
    <details style="margin-top:11px">
      <summary style="font-size:12px;color:#9ca3af;cursor:pointer"><?php esc_html_e('Reject or request changes','sgc-iip'); ?></summary>
      <form method="post" style="margin-top:9px">
        <?php wp_nonce_field('sgc_portal_'.$token,'sgc_portal_nonce'); ?>
        <input type="hidden" name="portal_action" value="reject_pi">
        <textarea name="reject_reason" rows="3" placeholder="<?php esc_attr_e('Please mention what changes are needed…','sgc-iip'); ?>"></textarea>
        <div class="btn-row"><button type="submit" class="btn btn-danger">✕ <?php esc_html_e('Reject / Request Changes','sgc-iip'); ?></button></div>
      </form>
    </details>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-hdr">🚢 <?php esc_html_e('Shipment & Dispatch','sgc-iip'); ?></div>
  <div class="card-body">
    <?php if($dispatch && !empty($dispatch['awb_number'])): ?>
      <div class="track" style="margin-bottom:12px">
        <div style="font-size:11px;color:#1d4ed8;margin-bottom:3px"><?php esc_html_e('AWB / Tracking Number','sgc-iip'); ?></div>
        <div class="track-n"><?php echo esc_html($dispatch['awb_number']); ?></div>
        <div style="font-size:12px;color:#555;margin-top:5px"><?php echo esc_html($dispatch['courier']??''); ?></div>
        <?php if(!empty($dispatch['tracking_url'])): ?>
          <a href="<?php echo esc_url($dispatch['tracking_url']); ?>" target="_blank" rel="noopener" class="btn btn-out" style="margin-top:9px;padding:6px 13px;font-size:12px">🔗 <?php esc_html_e('Track Shipment','sgc-iip'); ?></a>
        <?php endif; ?>
        <?php if(!empty($dispatch['expected_date'])): ?>
          <div style="font-size:12px;color:#555;margin-top:7px">📅 <?php printf(esc_html__('Expected Delivery: %s','sgc-iip'),esc_html(date('d M Y',strtotime($dispatch['expected_date'])))); ?></div>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <?php
      $stage_order = ['new','replied','pi_sent','accepted','advance','won'];
      $curr_idx    = array_search($status,$stage_order,true)?:0;
      $steps = [
        ['📄',__('PI Sent to buyer','sgc-iip'),   ['pi_sent','accepted','advance','won']],
        ['✅',__('PI Accepted','sgc-iip'),         ['accepted','advance','won']],
        ['💰',__('Advance payment received','sgc-iip'),['advance','won']],
        ['🏆',__('Order dispatched','sgc-iip'),    ['won']],
      ];
      foreach($steps as [$ic,$lbl,$done_stages]): $is_done=in_array($status,$done_stages,true); ?>
      <div class="dstep">
        <div class="d-ic" style="<?php echo $is_done?'background:#d1fae5':''; ?>"><?php echo $ic; ?></div>
        <div style="font-size:13px;font-weight:<?php echo $is_done?'600':'400'; ?>;color:<?php echo $is_done?'#065f46':'#9ca3af'; ?>;padding-top:6px"><?php echo esc_html($lbl); ?></div>
      </div>
      <?php endforeach; ?>
      <p style="font-size:12px;color:#9ca3af;margin-top:10px"><?php esc_html_e('Tracking number will appear here once dispatched.','sgc-iip'); ?></p>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-hdr">✉ <?php esc_html_e('Send a Message','sgc-iip'); ?></div>
  <div class="card-body">
    <form method="post">
      <?php wp_nonce_field('sgc_portal_'.$token,'sgc_portal_nonce'); ?>
      <input type="hidden" name="portal_action" value="send_message">
      <textarea name="buyer_message" rows="3" placeholder="<?php esc_attr_e('Ask about delivery, packing, samples, or any query…','sgc-iip'); ?>"></textarea>
      <div class="btn-row"><button type="submit" class="btn btn-dark">✉ <?php esc_html_e('Send Message','sgc-iip'); ?></button></div>
    </form>
  </div>
</div>

<div class="footer">
  <div><?php echo esc_html($company); ?> &nbsp;|&nbsp; <?php echo esc_html(get_option('sgc_iip_company_email','')); ?></div>
  <div><?php esc_html_e('Secure private link for your order only.','sgc-iip'); ?></div>
  <div style="margin-top:5px">SGC Invoice &amp; Packlist Pro v<?php echo esc_html(SGC_IIP_VERSION); ?> (<?php echo esc_html(SGC_IIP_BUILD_DATE); ?>)</div>
</div>

</div>
</body>
</html>
<?php echo ob_get_clean();
    }

    // ── Error page ────────────────────────────────────────────────────────────
    private static function render_error(string $msg) {
        $co = get_option('sgc_iip_company_name','SGC LABS®');
        status_header(403);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>'.esc_html($co).'</title>'
            .'<style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f0f2f5}.b{background:#fff;border-radius:12px;padding:40px;text-align:center;max-width:380px;box-shadow:0 4px 20px rgba(0,0,0,.08)}</style>'
            .'</head><body><div class="b"><div style="font-size:40px;margin-bottom:12px">🔒</div><h2>'.esc_html($co).'</h2><p style="margin-top:12px;color:#666">'.esc_html($msg).'</p></div></body></html>';
    }

    // ── Send portal link to buyer via email ───────────────────────────────────
    public static function send_portal_link(int $inq_id): bool {
        global $wpdb;
        $it  = SGC_IIP_DB::t('inquiries');
        $inq = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$it` WHERE id=%d",$inq_id),ARRAY_A);
        if (!$inq || empty($inq['email'])) return false;

        $token = $inq['pi_accept_token'] ?: wp_generate_password(32,false);
        if (empty($inq['pi_accept_token'])) $wpdb->update($it,['pi_accept_token'=>$token],['id'=>$inq_id]);

        $url     = home_url('/sgc-pi-portal/?sgc_token='.urlencode($token));
        $co      = get_option('sgc_iip_company_name','SGC LABS®');
        $from_em = get_option('sgc_iip_company_email','sales@labsglass.com');

        $body = "
            <p>Dear <strong>{$inq['name']}</strong>,</p>
            <p>Your Proforma Invoice is ready. Click below to view it, accept it, and upload your payment — all in one secure page.</p>
            <div style='text-align:center;margin:24px 0'>
              <a href='".esc_url($url)."' style='background:#27ae60;color:#fff;padding:13px 28px;border-radius:8px;text-decoration:none;font-size:15px;font-weight:700;display:inline-block'>📄 View My PI &amp; Order Portal</a>
            </div>
            <p style='font-size:12px;color:#888'>This is a private link for your order only. Do not share it.</p>";

        $html = SGC_IIP_Email::wrap($body, 'Your PI is Ready — '.$co);
        return wp_mail(
            sanitize_email($inq['email']),
            sprintf(__('Your Proforma Invoice is Ready — %s','sgc-iip'),$co),
            $html,
            ['Content-Type: text/html; charset=UTF-8','From: '.$co.' <'.$from_em.'>','Bcc: '.$from_em]
        );
    }

    // ── AJAX: Admin sends portal link ─────────────────────────────────────────
    public static function ajax_send_portal_link() {
        check_ajax_referer('sgc_iip_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        $id = intval($_POST['inq_id']??0);
        if (!$id) wp_send_json_error('Invalid ID');
        self::send_portal_link($id)
            ? wp_send_json_success('✅ Portal link sent to buyer.')
            : wp_send_json_error('Email failed. Check SMTP settings.');
    }
}
