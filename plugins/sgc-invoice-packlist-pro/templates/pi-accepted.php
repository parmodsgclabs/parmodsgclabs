<?php
/**
 * SGC Invoice Pro — PI Accepted Page
 * Standalone page — NO WordPress theme loaded.
 * Variables available: $co_name, $co_addr, $co_email, $co_phone, $co_web, $pi_number, $pi_data
 */
if ( ! defined('ABSPATH') ) exit;

// Safe defaults
$pi_num     = $pi_number ?? ( $pi_data['pi_number'] ?? 'PI' );
$buyer_name = $pi_data['buyer_name']    ?? 'Valued Customer';
$buyer_co   = $pi_data['buyer_company'] ?? '';
$grand_tot  = floatval( $pi_data['grand_total'] ?? 0 );
$currency   = $pi_data['currency']      ?? 'USD';
$pay_terms  = $pi_data['payment_terms'] ?? '';
$delivery   = $pi_data['delivery_days'] ?? '';
$valid      = $pi_data['valid_until']   ?? '';
$accepted_at = current_time('d M Y, H:i');

// Currency symbol
$csym = ['INR'=>'₹','USD'=>'$','GBP'=>'£','EUR'=>'€'][$currency] ?? $currency.' ';
$total_display = $csym . number_format($grand_tot, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PI Accepted — <?php echo esc_html($co_name); ?></title>
<meta name="robots" content="noindex,nofollow">
<style>
/* ── Reset ── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;background:#f0f2f5;min-height:100vh;display:flex;flex-direction:column}

/* ── Topbar ── */
.topbar{background:#1a1a2e;padding:14px 24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.topbar-brand{color:#fff;font-size:18px;font-weight:800;letter-spacing:.5px}
.topbar-brand span{color:#4ade80}
.topbar-sub{color:#9090aa;font-size:11px;margin-top:2px}
.iso-badge{background:transparent;border:1px solid #4ade80;color:#4ade80;font-size:9px;font-weight:700;padding:3px 8px;border-radius:3px;letter-spacing:.5px}

/* ── Main ── */
main{flex:1;display:flex;align-items:center;justify-content:center;padding:32px 16px}

.card{background:#fff;border-radius:16px;box-shadow:0 8px 40px rgba(0,0,0,.12);max-width:580px;width:100%;overflow:hidden}

.card-success{background:linear-gradient(135deg,#065f46,#047857);padding:36px 32px;text-align:center}
.checkmark{width:72px;height:72px;background:rgba(255,255,255,.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:36px}
.card-success h1{color:#fff;font-size:24px;font-weight:700;margin-bottom:8px}
.card-success p{color:rgba(255,255,255,.8);font-size:14px;line-height:1.6}

.pi-badge{display:inline-block;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:#fff;font-size:20px;font-weight:800;letter-spacing:2px;padding:10px 24px;border-radius:8px;margin:16px 0;font-family:monospace}

.card-body{padding:28px 32px}

/* ── PI Summary table ── */
.pi-summary{width:100%;border-collapse:collapse;margin-bottom:24px;font-size:13px}
.pi-summary tr{border-bottom:1px solid #f0f0f0}
.pi-summary tr:last-child{border-bottom:none}
.pi-summary td{padding:10px 4px;vertical-align:middle}
.pi-summary td:first-child{color:#888;width:46%;font-weight:500}
.pi-summary td:last-child{font-weight:600;color:#1a1a2e;text-align:right}
.pi-summary .total-row td{padding-top:14px;padding-bottom:4px}
.pi-summary .total-row td:first-child{color:#1a1a2e;font-weight:700;font-size:14px}
.pi-summary .total-row td:last-child{color:#047857;font-size:20px;font-weight:900}

/* ── Next steps ── */
.steps{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:18px 20px;margin-bottom:24px}
.steps-title{font-size:12px;font-weight:700;color:#065f46;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px}
.step-item{display:flex;align-items:flex-start;gap:10px;margin-bottom:10px;font-size:13px;color:#1a1a2e}
.step-item:last-child{margin-bottom:0}
.step-icon{width:22px;height:22px;border-radius:50%;background:#047857;color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;margin-top:1px}

/* ── Contact row ── */
.contact-row{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px}
.contact-btn{flex:1;min-width:120px;padding:11px 14px;border-radius:8px;text-decoration:none;font-weight:600;font-size:13px;text-align:center;display:block;transition:.15s}
.contact-btn:hover{opacity:.85;transform:translateY(-1px)}
.btn-phone{background:#1a1a2e;color:#fff}
.btn-wa{background:#25d366;color:#fff}
.btn-email{background:#4f46e5;color:#fff}

/* ── Timestamp ── */
.timestamp{background:#f8f9fa;border-radius:8px;padding:12px 16px;font-size:12px;color:#888;text-align:center;margin-bottom:20px}
.timestamp strong{color:#1a1a2e}

/* ── Footer ── */
footer{background:#1a1a2e;padding:20px 24px;text-align:center}
footer p{color:#9090aa;font-size:11px;line-height:1.8}
footer a{color:#4ade80;text-decoration:none}
footer a:hover{text-decoration:underline}
.footer-brand{color:#fff;font-size:13px;font-weight:700;margin-bottom:6px}

/* ── Print ── */
@media print{
  .topbar,.contact-row,.footer{display:none}
  body{background:#fff}
  .card{box-shadow:none;border:1px solid #e0e0e0}
}

/* ── Mobile ── */
@media(max-width:480px){
  .card-success{padding:24px 20px}
  .card-body{padding:20px}
  .topbar{padding:12px 16px}
}
</style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
  <div>
    <div class="topbar-brand"><?php echo esc_html($co_name); ?> <span>®</span></div>
    <div class="topbar-sub">Laboratory Glassware Manufacturer &amp; Exporter · Since 2003</div>
  </div>
  <div class="iso-badge">ISO CERTIFIED</div>
</div>

<!-- Main Content -->
<main>
<div class="card">

  <!-- Success Header -->
  <div class="card-success">
    <div class="checkmark">✅</div>
    <h1>Proforma Invoice Accepted!</h1>
    <p>Thank you<?php if($buyer_name && $buyer_name!=='Valued Customer') echo ', <strong>'.esc_html($buyer_name).'</strong>'; ?><?php if($buyer_co) echo ' ('.esc_html($buyer_co).')'; ?>.<br>
    Your acceptance has been recorded with date &amp; time stamp.</p>
    <div class="pi-badge"><?php echo esc_html($pi_num); ?></div>
  </div>

  <!-- Body -->
  <div class="card-body">

    <!-- PI Summary -->
    <table class="pi-summary">
      <tr><td>PI Number</td><td><?php echo esc_html($pi_num); ?></td></tr>
      <tr><td>Accepted On</td><td><?php echo esc_html($accepted_at); ?></td></tr>
      <?php if($pay_terms): ?><tr><td>Payment Terms</td><td><?php echo esc_html($pay_terms); ?></td></tr><?php endif; ?>
      <?php if($delivery):  ?><tr><td>Delivery</td><td><?php echo esc_html($delivery); ?></td></tr><?php endif; ?>
      <?php if($valid):     ?><tr><td>PI Validity</td><td><?php echo esc_html($valid); ?></td></tr><?php endif; ?>
      <?php if($grand_tot > 0): ?>
      <tr class="total-row">
        <td>Grand Total</td>
        <td><?php echo esc_html($total_display); ?></td>
      </tr>
      <?php endif; ?>
    </table>

    <!-- Next Steps -->
    <div class="steps">
      <div class="steps-title">What happens next?</div>
      <div class="step-item"><div class="step-icon">1</div><div>Your acceptance is recorded. Our team has been notified immediately.</div></div>
      <div class="step-item"><div class="step-icon">2</div><div>Please arrange <strong><?php echo esc_html($pay_terms ?: 'advance payment'); ?></strong> to confirm the order.</div></div>
      <div class="step-item"><div class="step-icon">3</div><div>Share payment confirmation to <strong><?php echo esc_html($co_email); ?></strong></div></div>
      <div class="step-item"><div class="step-icon">4</div><div>Production / dispatch within <strong><?php echo esc_html($delivery ?: '15-20 working days'); ?></strong> after payment.</div></div>
    </div>

    <!-- Timestamp -->
    <div class="timestamp">
      🕐 Accepted on <strong><?php echo esc_html($accepted_at); ?></strong> &nbsp;·&nbsp;
      PI: <strong><?php echo esc_html($pi_num); ?></strong> &nbsp;·&nbsp;
      IP: <?php echo esc_html(sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '—')); ?>
    </div>

    <!-- Contact Buttons -->
    <div class="contact-row">
      <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/','',$co_phone)); ?>" class="contact-btn btn-phone">📞 Call Us</a>
      <a href="https://wa.me/<?php echo esc_attr(preg_replace('/[^0-9]/','',$co_phone)); ?>?text=Hi%2C+I+accepted+PI+<?php echo esc_attr(urlencode($pi_num)); ?>" class="contact-btn btn-wa" target="_blank">💬 WhatsApp</a>
      <a href="mailto:<?php echo esc_attr($co_email); ?>?subject=PI+Accepted+-+<?php echo esc_attr(urlencode($pi_num)); ?>" class="contact-btn btn-email">✉ Email Us</a>
    </div>

    <p style="font-size:12px;color:#aaa;text-align:center;line-height:1.7">
      For any queries, contact us at <a href="mailto:<?php echo esc_attr($co_email); ?>" style="color:#4f46e5"><?php echo esc_html($co_email); ?></a><br>
      Reference your PI number <strong><?php echo esc_html($pi_num); ?></strong> in all communications.
    </p>

  </div><!-- /card-body -->
</div><!-- /card -->
</main>

<!-- Footer -->
<footer>
  <div class="footer-brand"><?php echo esc_html($co_name); ?></div>
  <p>
    <?php echo esc_html($co_addr); ?><br>
    <a href="mailto:<?php echo esc_attr($co_email); ?>"><?php echo esc_html($co_email); ?></a> &nbsp;·&nbsp;
    <?php echo esc_html($co_phone); ?> &nbsp;·&nbsp;
    <a href="<?php echo esc_url($co_web); ?>" target="_blank"><?php echo esc_html($co_web); ?></a>
  </p>
  <p style="margin-top:8px;color:#606070;font-size:10px">
    This page confirms acceptance of Proforma Invoice <?php echo esc_html($pi_num); ?> issued by <?php echo esc_html($co_name); ?>.
    &copy; <?php echo date('Y'); ?> <?php echo esc_html($co_name); ?>. All rights reserved.
  </p>
</footer>

</body>
</html>
