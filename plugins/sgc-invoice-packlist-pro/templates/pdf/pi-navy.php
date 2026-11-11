<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Helvetica,Arial,sans-serif;font-size:11px;color:#222;background:#fff}
.page{padding:20px 24px}
/* Header */
.hdr{background:#1a1a2e;color:#fff;padding:16px 20px;display:table;width:100%;margin-bottom:0}
.hdr-left{display:table-cell;vertical-align:top}
.hdr-right{display:table-cell;vertical-align:top;text-align:right}
.co-name{font-size:20px;font-weight:700;letter-spacing:1px;color:#fff}
.co-sub{font-size:9px;color:#9090aa;margin-top:2px}
.pi-title{font-size:22px;font-weight:700;color:#2e7d32;letter-spacing:2px}
.pi-num{font-size:11px;color:#ccc;margin-top:3px}
.pi-num span{color:#fff;font-weight:700}
/* Sub-header: addresses */
.addr-row{display:table;width:100%;border:1px solid #e0e0e0;border-top:3px solid #2e7d32;margin-bottom:14px}
.addr-cell{display:table-cell;width:50%;padding:10px 14px;vertical-align:top;border-right:1px solid #e0e0e0}
.addr-cell:last-child{border-right:none}
.addr-lbl{font-size:8px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.addr-name{font-size:12px;font-weight:700;color:#1a1a2e}
.addr-detail{font-size:10px;color:#555;line-height:1.5;margin-top:2px}
/* PI meta */
.meta-row{display:table;width:100%;margin-bottom:14px}
.meta-cell{display:table-cell;width:25%;background:#f5f7fa;border:1px solid #e0e0e0;padding:8px 10px;text-align:center}
.meta-lbl{font-size:8px;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px}
.meta-val{font-size:11px;font-weight:700;color:#1a1a2e}
/* Line items */
table.items{width:100%;border-collapse:collapse;margin-bottom:14px;font-size:10px}
table.items th{background:#1a1a2e;color:#fff;padding:7px 8px;text-align:left;font-size:9px;font-weight:600;letter-spacing:.3px}
table.items td{padding:7px 8px;border-bottom:1px solid #e8e8e8;vertical-align:top}
table.items tr:nth-child(even) td{background:#f9f9f9}
table.items .hs{font-size:9px;color:#888}
table.items .total{font-weight:700;color:#1a1a2e;text-align:right}
table.items th:last-child,table.items td:last-child{text-align:right}
table.items th:nth-child(3),table.items td:nth-child(3){text-align:center}
/* Totals */
.totals-row{display:table;width:100%;margin-bottom:16px}
.totals-left{display:table-cell;vertical-align:top;width:55%;padding-right:20px}
.totals-right{display:table-cell;vertical-align:top;width:45%}
.totals-tbl{width:100%;border-collapse:collapse;font-size:11px}
.totals-tbl td{padding:5px 8px;border-bottom:1px solid #f0f0f0}
.totals-tbl td:last-child{text-align:right;font-weight:600}
.grand-row td{background:#1a1a2e;color:#fff;font-size:13px;font-weight:700;padding:9px 10px;border:none}
.grand-row td:last-child{text-align:right;color:#2e7d32}
/* Bank / Terms */
.bank-box{background:#fff8e1;border:1px solid #ffe082;border-radius:4px;padding:10px 12px;font-size:9.5px;margin-bottom:14px}
.bank-box strong{color:#e65100;font-size:10px}
.terms-row{display:table;width:100%;margin-bottom:14px}
.terms-cell{display:table-cell;width:50%;padding:0 10px 0 0;vertical-align:top}
.terms-cell:last-child{padding:0 0 0 10px}
.terms-box{border:1px solid #e0e0e0;border-radius:4px;padding:9px 12px;font-size:9.5px;height:100%}
.terms-title{font-weight:700;font-size:10px;color:#1a1a2e;margin-bottom:4px}
/* Signature */
.sig-row{display:table;width:100%;border-top:2px solid #e0e0e0;padding-top:14px;margin-bottom:14px}
.sig-cell{display:table-cell;width:50%;text-align:center;padding:0 20px;vertical-align:bottom}
.sig-line{border-top:1px solid #333;margin-top:40px;margin-bottom:4px}
.sig-name{font-weight:700;font-size:10px;color:#1a1a2e}
.sig-title{font-size:9px;color:#888}
/* Accept link */
.accept-bar{background:#e8f5e9;border:1px solid #a5d6a7;border-radius:4px;padding:10px 14px;
  text-align:center;margin-bottom:14px;font-size:10px;color:#1b5e20}
/* Footer */
.footer{background:#f5f7fa;border-top:2px solid #1a1a2e;padding:10px 14px;font-size:9px;
  color:#888;text-align:center;margin-top:8px}
</style>
</head>
<body>
<?php
// Template variables from $pi array
$pi_number   = $pi['pi_number']     ?? 'PI-2026-0001';
$buyer_name  = $pi['buyer_name']    ?? '';
$buyer_co    = $pi['buyer_company'] ?? '';
$buyer_country= $pi['buyer_country']?? '';
$buyer_addr  = $pi['buyer_address'] ?? '';
$currency    = $pi['currency']      ?? 'USD';
$inr_rate    = floatval($pi['inr_rate'] ?? 83.5);
$incoterm    = $pi['incoterm']      ?? 'FOB';
$port        = $pi['port']          ?? 'New Delhi, India';
$payment     = $pi['payment_terms'] ?? '30% advance, 70% before shipment';
$delivery    = $pi['delivery_days'] ?? '15-20 working days';
$valid       = $pi['valid_until']   ?? date('Y-m-d', strtotime('+30 days'));
$notes       = $pi['notes']         ?? '';

// v1.3.2: Always append EXW/freight disclaimer
$is_dom_pdf  = in_array( strtolower( trim( $buyer_country ?? '' ) ), ['india','in','bharat'], true )
             || ( ($pi['currency']??'USD') === 'INR' );
$exw_note    = $is_dom_pdf
    ? 'Price basis: Ex-Works, Ambala, Haryana. Transportation charges extra at actuals. 7% packaging & handling included.'
    : 'Price basis: Ex-Works, Ambala, India. Freight / shipping charges are NOT included. 7% packaging & handling charges are included in the above prices.';
$notes       = $notes ? $notes . "\n\n" . $exw_note : $exw_note;
$line_items  = $pi['line_items']    ?? [];
$subtotal    = floatval($pi['subtotal']       ?? 0);
$pack_total  = floatval($pi['packaging_total']?? 0);
$oem_charge  = floatval($pi['oem_charge']    ?? 0);
$discount    = floatval($pi['discount']      ?? 0);
$tax         = floatval($pi['tax_amount']    ?? 0);
$grand_total = floatval($pi['grand_total']   ?? 0);
$grand_inr   = floatval($pi['grand_total_inr']?? 0);
$accept_token= $pi['accept_token']  ?? '';
$freight_charge = floatval($pi['freight_charge'] ?? 0);  // v1.3.5: freight row in PDF
$gst_percent    = floatval($pi['gst_percent']    ?? 0);  // v1.3.5: GST label fix

$co_name     = get_option('sgc_iip_company_name','Scientific Glass Creations');
$co_addr     = get_option('sgc_iip_company_address','Ambala, Haryana, India');
$co_gst      = get_option('sgc_iip_company_gst','');
$co_iec      = get_option('sgc_iip_company_iec','');
$co_phone    = get_option('sgc_iip_company_phone','');
$co_email    = get_option('sgc_iip_company_email','info@labsglass.com');
$co_web      = get_option('sgc_iip_company_website','https://labsglass.com');
$is_domestic = ($currency === 'INR');
if ($is_domestic) {
    $bank_ben   = get_option('sgc_iip_dom_bank_beneficiary', get_option('sgc_iip_bank_beneficiary',''));
    $bank_name  = get_option('sgc_iip_dom_bank_name',        get_option('sgc_iip_bank_name',''));
    $bank_acc   = get_option('sgc_iip_dom_bank_account',     get_option('sgc_iip_bank_account',''));
    $bank_swift = '';
    $bank_ifsc  = get_option('sgc_iip_dom_bank_ifsc',        get_option('sgc_iip_bank_ifsc',''));
} else {
    $bank_ben   = get_option('sgc_iip_export_bank_beneficiary', get_option('sgc_iip_bank_beneficiary',''));
    $bank_name  = get_option('sgc_iip_export_bank_name',        get_option('sgc_iip_bank_name',''));
    $bank_acc   = get_option('sgc_iip_export_bank_account',     get_option('sgc_iip_bank_account',''));
    $bank_swift = get_option('sgc_iip_export_bank_swift',       get_option('sgc_iip_bank_swift',''));
    $bank_ifsc  = get_option('sgc_iip_export_bank_ifsc',        '');
}
$bank_branch = get_option('sgc_iip_bank_branch','Ambala, India');

// v1.2.5: Auto-select Export vs Domestic bank based on buyer country
$is_domestic_buyer = in_array( strtolower( trim( $buyer_country ?? '' ) ), ['india','in','bharat'], true )
                  || ( $currency === 'INR' );

if ( $is_domestic_buyer ) {
    // Domestic — IFSC / UPI
    $bd_ben    = get_option('sgc_iip_dom_bank_beneficiary', $bank_ben);
    $bd_name   = get_option('sgc_iip_dom_bank_name',        $bank_name);
    $bd_branch = get_option('sgc_iip_dom_bank_branch',      $bank_branch);
    $bd_acc    = get_option('sgc_iip_dom_bank_account',     $bank_acc);
    $bd_ifsc   = get_option('sgc_iip_dom_bank_ifsc',        $bank_ifsc);
    $bd_type   = get_option('sgc_iip_dom_bank_type',        'Current Account');
    $bd_swift  = '';
    $bd_qr_url = '';
    $upi_id    = get_option('sgc_iip_upi_id', '');
    $upi_name  = get_option('sgc_iip_upi_name', $bd_ben);
    $bd_qr_src = $upi_id
        ? 'https://quickchart.io/qr?text=' . urlencode('upi://pay?pa='.$upi_id.'&pn='.urlencode($upi_name)) . '&width=90&height=90'
        : '';
    $bd_label  = 'Bank Transfer Details (NEFT / RTGS / IMPS)';
    $bd_is_dom = true;
} else {
    // Export — SWIFT / Wire
    $bd_ben    = get_option('sgc_iip_export_bank_beneficiary', $bank_ben);
    $bd_name   = get_option('sgc_iip_export_bank_name',        $bank_name);
    $bd_branch = get_option('sgc_iip_export_bank_branch',      $bank_branch);
    $bd_acc    = get_option('sgc_iip_export_bank_account',     $bank_acc);
    $bd_ifsc   = get_option('sgc_iip_export_bank_ifsc',        $bank_ifsc);
    $bd_swift  = get_option('sgc_iip_export_bank_swift',       $bank_swift);
    $bd_addr   = get_option('sgc_iip_export_bank_address',     '');
    $bd_qr_url = get_option('sgc_iip_export_bank_qr_url',      '');
    $bd_qr_src = $bd_qr_url
        ? 'https://quickchart.io/qr?text=' . urlencode($bd_qr_url) . '&width=90&height=90'
        : '';
    $upi_id    = '';
    $bd_label  = 'Bank Wire / SWIFT Transfer Details';
    $bd_is_dom = false;
}
$sig_img     = get_option('sgc_iip_signature_img','');
$seal_img    = get_option('sgc_iip_seal_img','');
$cur_sym     = ['USD'=>'$','INR'=>'₹','GBP'=>'£','EUR'=>'€'][$currency] ?? $currency.' ';

$accept_url = $accept_token ? home_url('/sgc-pi-accept/'.$pi_number.'/'.$accept_token) : '';
$pi_date    = isset($pi['created_at']) ? date('d M Y', strtotime($pi['created_at'])) : date('d M Y');
?>

<!-- Header -->
<div class="hdr">
<div class="hdr-left">
  <div class="co-name"><?php echo esc_html($co_name); ?></div>
  <div class="co-sub">Laboratory Glassware Manufacturer &amp; Exporter | Since 2003</div>
  <div style="margin-top:6px;font-size:9px;color:#7070aa">
    <?php echo esc_html($co_addr); ?><br>
    <?php if($co_gst) echo 'GST: '.esc_html($co_gst).' | '; ?>
    <?php if($co_iec) echo 'IEC: '.esc_html($co_iec).' | '; ?>
    <?php echo esc_html($co_phone); ?> | <?php echo esc_html($co_email); ?>
  </div>
</div>
<div class="hdr-right">
  <div class="pi-title"><?php echo $is_domestic_buyer ? 'TAX<br>INVOICE' : 'PROFORMA<br>INVOICE'; ?></div>
  <div class="pi-num">PI No: <span><?php echo esc_html($pi_number); ?></span></div>
  <div class="pi-num">Date: <span><?php echo esc_html($pi_date); ?></span></div>
</div>
</div>

<div class="page">

<!-- Addresses -->
<div class="addr-row">
<div class="addr-cell">
  <div class="addr-lbl">Exporter / Seller</div>
  <div class="addr-name"><?php echo esc_html($co_name); ?></div>
  <div class="addr-detail">
    <?php echo esc_html($co_addr); ?><br>
    <?php if($co_gst) echo 'GST No: '.esc_html($co_gst).'<br>'; ?>
    <?php if($co_iec) echo 'IEC: '.esc_html($co_iec).'<br>'; ?>
    <?php echo esc_html($co_email); ?> | <?php echo esc_html($co_web); ?>
  </div>
</div>
<div class="addr-cell">
  <div class="addr-lbl">Buyer / Consignee</div>
  <div class="addr-name"><?php echo esc_html($buyer_name.($buyer_co?' — '.$buyer_co:'')); ?></div>
  <div class="addr-detail">
    <?php echo esc_html($buyer_country); ?><br>
    <?php if($buyer_addr) echo nl2br(esc_html($buyer_addr)).'<br>'; ?>
  </div>
</div>
</div>

<!-- PI Meta -->
<div class="meta-row">
<div class="meta-cell"><div class="meta-lbl">Incoterm</div><div class="meta-val"><?php echo esc_html($incoterm); ?></div></div>
<div class="meta-cell"><div class="meta-lbl">Port</div><div class="meta-val"><?php echo esc_html($port); ?></div></div>
<div class="meta-cell"><div class="meta-lbl">Currency</div><div class="meta-val"><?php echo esc_html($currency); ?></div></div>
<div class="meta-cell"><div class="meta-lbl">Valid Until</div><div class="meta-val" style="color:#c62828"><?php echo esc_html($valid); ?></div></div>
</div>

<!-- Line Items -->
<table class="items">
<thead>
<tr>
  <th style="width:4%">#</th>
  <th style="width:28%">Product Description</th>
  <th style="width:10%">HS Code</th>
  <th style="width:8%">SKU</th>
  <th style="width:7%">Qty</th>
  <th style="width:12%">Unit Price (<?php echo esc_html($currency); ?>)</th>
  <th style="width:8%">OEM</th>
  <th style="width:12%">Total (<?php echo esc_html($currency); ?>)</th>
</tr>
</thead>
<tbody>
<?php foreach($line_items as $i=>$item): ?>
<tr>
  <td><?php echo $i+1; ?></td>
  <td><strong><?php echo esc_html($item['product_name']??''); ?></strong></td>
  <td class="hs"><?php echo esc_html($item['hs_code']??'7017.20'); ?></td>
  <td><?php echo esc_html($item['sku']??''); ?></td>
  <td style="text-align:center"><?php echo esc_html($item['qty']??0); ?></td>
  <td style="text-align:right"><?php echo $cur_sym.number_format(floatval($item['unit_price']??0),2); ?></td>
  <td style="text-align:right"><?php echo $cur_sym.number_format(floatval($item['oem_charge']??0),2); ?></td>
  <td class="total"><?php echo $cur_sym.number_format(floatval($item['total']??0),2); ?></td>
</tr>
<?php endforeach; ?>
<?php if(empty($line_items)): ?><tr><td colspan="9" style="text-align:center;color:#aaa;padding:20px">No line items</td></tr><?php endif; ?>
</tbody>
</table>

<!-- Totals + Bank -->
<div class="totals-row">
<div class="totals-left">
  <!-- Bank Details -->
  <div class="bank-box">
    <strong><?php echo esc_html($bd_label); ?></strong><br><br>
    <div style="display:flex;gap:10px;align-items:flex-start">
      <div style="flex:1">
        Beneficiary: <strong><?php echo esc_html($bd_ben); ?></strong><br>
        Bank: <?php echo esc_html($bd_name); ?>, <?php echo esc_html($bd_branch); ?><br>
        Account No: <strong><?php echo esc_html($bd_acc); ?></strong><br>
        <?php if($bd_is_dom): ?>
        IFSC: <strong><?php echo esc_html($bd_ifsc); ?></strong> &nbsp;|&nbsp; <?php echo esc_html($bd_type); ?><br>
        <?php if($upi_id): ?>UPI: <strong><?php echo esc_html($upi_id); ?></strong><?php endif; ?>
        <?php else: ?>
        IFSC: <?php echo esc_html($bd_ifsc); ?><br>
        <strong>SWIFT: <?php echo esc_html($bd_swift); ?></strong> &nbsp;(International wire)<br>
        <?php if(!empty($bd_addr)): ?>Bank Addr: <?php echo esc_html($bd_addr); ?><?php endif; ?>
        <?php endif; ?>
      </div>
      <?php if(!empty($bd_qr_src)): ?>
      <div style="text-align:center;flex-shrink:0">
        <img src="<?php echo esc_url($bd_qr_src); ?>" width="80" height="80" style="display:block;border:1px solid #ffe082;border-radius:4px"><br>
        <span style="font-size:7.5px;color:#888"><?php echo $bd_is_dom ? 'Scan to pay (UPI)' : 'Payment ref QR'; ?></span>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <!-- Notes -->
  <?php if($notes): ?><div style="border:1px solid #e0e0e0;border-radius:4px;padding:9px 12px;font-size:9.5px;color:#555"><strong>Notes:</strong><br><?php echo nl2br(esc_html($notes)); ?></div><?php endif; ?>
</div>
<div class="totals-right">
  <table class="totals-tbl">
    <tr><td>Subtotal (Products)</td><td><?php echo $cur_sym.number_format($subtotal,2); ?></td></tr>
    <?php if($pack_total>0): ?><tr><td>Packaging &amp; Handling</td><td><?php echo $cur_sym.number_format($pack_total,2); ?></td></tr><?php endif; ?>
    <?php if($oem_charge>0): ?><tr><td>OEM / Custom Charges</td><td><?php echo $cur_sym.number_format($oem_charge,2); ?></td></tr><?php endif; ?>
    <?php if($discount>0): ?><tr><td>Discount</td><td style="color:#c62828">- <?php echo $cur_sym.number_format($discount,2); ?></td></tr><?php endif; ?>
    <?php if($tax>0||$gst_percent>0): ?><tr><td>GST<?php if($gst_percent>0): ?> (<?php echo $gst_percent; ?>%)<?php endif; ?></td><td><?php echo $cur_sym.number_format($tax,2); ?></td></tr><?php endif; ?>  <!-- v1.3.5: GST label fix -->
    <?php if($freight_charge>0): ?><tr><td>Freight Charges</td><td><?php echo $cur_sym.number_format($freight_charge,2); ?></td></tr><?php endif; ?>  <!-- v1.3.5: freight row -->
        <tr class="grand-row">
      <td>GRAND TOTAL (<?php echo esc_html($incoterm.' '.$port); ?>)</td>
      <td><?php echo esc_html($cur_sym).number_format($grand_total,2); ?></td>
    </tr>
    <?php if($currency!=='INR'&&$grand_inr>0): ?>
    <tr><td style="font-size:9px;color:#888">Equivalent (INR @<?php echo $inr_rate; ?>)</td>
    <td style="font-size:9px;color:#888">₹<?php echo number_format($grand_inr,2); ?></td></tr>
    <?php endif; ?>
  </table>
</div>
</div>

<!-- Terms -->
<div class="terms-row">
<div class="terms-cell">
  <div class="terms-box">
    <div class="terms-title">Payment Terms</div>
    <?php echo esc_html($payment); ?>
  </div>
</div>
<div class="terms-cell">
  <div class="terms-box">
    <div class="terms-title">Delivery Time</div>
    <?php echo esc_html($delivery); ?><br>
    <div class="terms-title" style="margin-top:6px">Offer Validity</div>
    This PI is valid until <strong><?php echo esc_html($valid); ?></strong>
  </div>
</div>
</div>

<!-- PI Accept Link -->
<?php if($accept_url): ?>
<div class="accept-bar">
  ✅ <strong>Accept this Proforma Invoice online:</strong> <?php echo esc_url($accept_url); ?>
</div>
<?php endif; ?>

<!-- Signature -->
<div class="sig-row">
<div class="sig-cell">
  <div style="font-size:9px;color:#888;margin-bottom:4px">Prepared by</div>
  <?php if($sig_img): ?><img src="<?php echo esc_url($sig_img); ?>" style="max-height:40px;max-width:140px;object-fit:contain"><?php else: ?><div style="height:40px"></div><?php endif; ?>
  <div class="sig-line"></div>
  <div class="sig-name"><?php echo esc_html($co_name); ?></div>
  <div class="sig-title">Authorised Signatory</div>
</div>
<div class="sig-cell">
  <?php if($seal_img): ?>
  <img src="<?php echo esc_url($seal_img); ?>" style="max-height:60px;max-width:60px;object-fit:contain;margin-bottom:6px"><br>
  <?php endif; ?>
  <div class="sig-line"></div>
  <div class="sig-name">Company Seal</div>
  <div class="sig-title"><?php echo esc_html($co_name); ?></div>
</div>
</div>

<!-- Footer -->
<div class="footer">
  <?php echo esc_html($co_name); ?> &nbsp;|&nbsp; <?php echo esc_html($co_addr); ?> &nbsp;|&nbsp;
  <?php echo esc_html($co_email); ?> &nbsp;|&nbsp; <?php echo esc_html($co_web); ?>
  <br>This is a computer-generated Proforma Invoice. For queries write to <?php echo esc_html($co_email); ?>
</div>

</div>
</body>
</html>
