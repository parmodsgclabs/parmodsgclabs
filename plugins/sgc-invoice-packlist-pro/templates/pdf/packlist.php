<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Helvetica,Arial,sans-serif;font-size:10px;color:#222}
.hdr{background:#1a1a2e;color:#fff;padding:12px 18px;display:table;width:100%}
.hdr-l{display:table-cell;vertical-align:top}
.hdr-r{display:table-cell;text-align:right;vertical-align:top}
.page{padding:16px 18px}
table{width:100%;border-collapse:collapse;margin-bottom:14px;font-size:9.5px}
th{background:#1a1a2e;color:#fff;padding:6px 8px;text-align:left;font-size:8.5px}
td{padding:5px 8px;border-bottom:1px solid #eee;vertical-align:middle}
tr:nth-child(even) td{background:#f9f9f9}
.totals-bar{background:#f5f7fa;border:1px solid #e0e0e0;border-radius:4px;padding:10px 14px;display:table;width:100%;margin-bottom:14px}
.t-cell{display:table-cell;text-align:center;padding:4px 10px;border-right:1px solid #e0e0e0}
.t-cell:last-child{border-right:none}
.t-num{font-size:16px;font-weight:700;color:#1a1a2e}
.t-lbl{font-size:8px;color:#888;margin-top:2px}
.freight-bar{background:#fff8e1;border:1px solid #ffe082;border-radius:4px;padding:9px 14px;font-size:9.5px;margin-bottom:14px}
.sig-row{display:table;width:100%;border-top:1px solid #e0e0e0;padding-top:12px;margin-top:12px}
.sig-cell{display:table-cell;width:50%;text-align:center}
.sig-line{border-top:1px solid #333;margin:30px 20px 4px}
</style>
</head>
<body>
<?php
$co_name = get_option('sgc_iip_company_name','SGC LABS®');
$pl_num  = $pl['pl_number'] ?? 'PL-2026-001';
$boxes   = $pl['boxes']     ?? [];
$date    = date('d M Y');
$sig_img = get_option('sgc_iip_signature_img','');
$co_addr = get_option('sgc_iip_company_address','Ambala, India');
?>
<div class="hdr">
<div class="hdr-l">
  <div style="font-size:17px;font-weight:700"><?php echo esc_html($co_name); ?></div>
  <div style="font-size:8px;color:#9090aa">Laboratory Glassware Manufacturer &amp; Exporter</div>
  <div style="font-size:8px;color:#7070aa;margin-top:4px"><?php echo esc_html($co_addr); ?></div>
</div>
<div class="hdr-r">
  <div style="font-size:18px;font-weight:700;color:#2e7d32;letter-spacing:1px">PACKING LIST</div>
  <div style="font-size:9px;color:#ccc;margin-top:4px">PL No: <strong style="color:#fff"><?php echo esc_html($pl_num); ?></strong></div>
  <div style="font-size:9px;color:#ccc">Date: <?php echo esc_html($date); ?></div>
</div>
</div>
<div class="page">

<!-- Totals summary -->
<div class="totals-bar">
<div class="t-cell"><div class="t-num"><?php echo count($boxes); ?></div><div class="t-lbl">Total Cartons</div></div>
<div class="t-cell"><div class="t-num"><?php echo number_format(array_sum(array_column($boxes,'net_wt')),2); ?> kg</div><div class="t-lbl">Net Weight</div></div>
<div class="t-cell"><div class="t-num"><?php echo number_format(array_sum(array_column($boxes,'gross_wt')),2); ?> kg</div><div class="t-lbl">Gross Weight</div></div>
<div class="t-cell"><div class="t-num"><?php echo number_format($pl['total_cbm']??0,4); ?> m³</div><div class="t-lbl">Total CBM</div></div>
</div>

<!-- Box details -->
<table>
<thead>
<tr>
  <th>Box #</th><th>Product Description</th><th>SKU</th><th>Qty</th>
  <th>Net Wt (kg)</th><th>Gross Wt (kg)</th><th>L×W×H (cm)</th><th>CBM (m³)</th>
</tr>
</thead>
<tbody>
<?php foreach($boxes as $box): ?>
<tr>
  <td><strong><?php echo esc_html($box['box_no']??''); ?></strong></td>
  <td><?php echo esc_html($box['product']??''); ?></td>
  <td><?php echo esc_html($box['sku']??''); ?></td>
  <td style="text-align:center"><?php echo esc_html($box['qty']??0); ?></td>
  <td style="text-align:center"><?php echo number_format(floatval($box['net_wt']??0),3); ?></td>
  <td style="text-align:center"><?php echo number_format(floatval($box['gross_wt']??0),3); ?></td>
  <td style="text-align:center"><?php echo esc_html(($box['length_cm']??0).'×'.($box['width_cm']??0).'×'.($box['height_cm']??0)); ?></td>
  <td style="text-align:center"><?php echo number_format(floatval($box['cbm']??0),4); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<!-- Freight estimates -->
<?php if(!empty($pl['sea_est_usd'])||!empty($pl['air_est_usd'])): ?>
<div class="freight-bar">
  <strong>📦 Estimated Freight (Indicative Only)</strong><br>
  Sea Freight (FCL/LCL): ~$<?php echo number_format(floatval($pl['sea_est_usd']??0),2); ?> USD<br>
  Air Freight: ~$<?php echo number_format(floatval($pl['air_est_usd']??0),2); ?> USD<br>
  <em style="font-size:8.5px;color:#888">Actual freight subject to carrier rates at time of shipment.</em>
</div>
<?php endif; ?>

<!-- Signature -->
<div class="sig-row">
<div class="sig-cell">
  <?php if($sig_img): ?><img src="<?php echo esc_url($sig_img); ?>" style="max-height:36px;object-fit:contain"><br><?php else: ?><div style="height:36px"></div><?php endif; ?>
  <div class="sig-line"></div>
  <div style="font-weight:700;font-size:10px"><?php echo esc_html($co_name); ?></div>
  <div style="font-size:9px;color:#888">Authorised Signatory</div>
</div>
<div class="sig-cell">
  <div style="height:36px"></div>
  <div class="sig-line"></div>
  <div style="font-weight:700;font-size:10px">Buyer's Signature</div>
  <div style="font-size:9px;color:#888">Name &amp; Date</div>
</div>
</div>
</div>
</body>
</html>
