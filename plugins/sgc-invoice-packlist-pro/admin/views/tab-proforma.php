<?php defined('ABSPATH')||exit;
$inquiry_id       = (int)($_GET['inquiry_id']??0);
$usd_rate         = floatval(get_option('sgc_iip_usd_rate', 83.50));
$export_freight   = floatval(get_option('sgc_iip_export_freight',  0));
$domestic_freight = floatval(get_option('sgc_iip_domestic_freight', 0));
$default_incoterm = get_option('sgc_iip_default_incoterm','FOB');
$default_port     = get_option('sgc_iip_default_port','New Delhi, India');
$default_payment  = get_option('sgc_iip_payment_terms','30% advance, 70% before dispatch');
$default_delivery = get_option('sgc_iip_delivery_days','15-30 working days');
?>
<div id="iip-proforma-wrap">

<!-- ── Top Bar ── -->
<div class="iip-box" style="padding:12px 18px;margin-bottom:14px">
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <!-- Inquiry search dropdown -->
    <div style="position:relative;flex:1;min-width:200px">
      <input type="text" id="pi-inquiry-search" class="iip-input" style="width:100%" placeholder="🔍 Search inquiry — name, email, product…" autocomplete="off">
      <div id="pi-inquiry-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:999;background:#fff;border:1px solid #d0d0d0;border-radius:6px;box-shadow:0 4px 16px rgba(0,0,0,.12);max-height:260px;overflow-y:auto"></div>
    </div>
    <!-- Hidden ID field (kept for compatibility) -->
    <input type="hidden" id="pi-inquiry-id" value="<?php echo esc_attr($inquiry_id); ?>">
    <button class="iip-btn primary" id="btn-create-pi" style="display:none">+ New PI</button>
    <button class="iip-btn" id="btn-ai-draft-pi" style="display:none">🤖 AI Draft</button>
    <span id="pi-inquiry-info" style="font-size:12px;color:#27ae60;font-weight:600;font-style:normal"></span>
    <div style="margin-left:auto;display:flex;gap:6px;align-items:center">
      <label style="font-size:11px;color:#888">USD:</label>
      <input type="number" id="pi-usd-rate" class="iip-input" value="<?php echo $usd_rate; ?>" style="width:72px;font-size:12px" step="0.01">
    </div>
  </div>
</div>

<!-- ── ALL PIs TABLE (shows when no inquiry selected) ── -->
<div id="iip-all-pi-table" class="iip-box" style="padding:0;margin-bottom:14px">
  <div style="padding:12px 18px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
    <strong style="font-size:14px">📋 All Proforma Invoices</strong>
    <div style="display:flex;gap:8px;align-items:center">
      <input type="text" id="all-pi-search" class="iip-input" style="width:160px;font-size:12px" placeholder="Search PI no, buyer…">
      <select id="all-pi-status" class="iip-select" style="font-size:12px">
        <option value="">All Status</option>
        <option value="draft">Draft</option>
        <option value="sent">Sent</option>
        <option value="accepted">Accepted</option>
        <option value="won">Won</option>
      </select>
      <button class="iip-btn sm" id="btn-reload-all-pi">🔄</button>
    </div>
  </div>
  <div id="all-pi-tbody" style="min-height:60px">
    <div style="padding:20px;text-align:center;color:#aaa;font-size:13px">⏳ Loading…</div>
  </div>
  <div id="all-pi-pagination" style="padding:10px 18px;font-size:12px;color:#6b7280;border-top:1px solid #f0f0f0"></div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:16px;align-items:start">

<!-- ── LEFT: PI Editor ── -->
<div>
<div class="iip-box" id="pi-editor" style="display:none;padding:0">

  <!-- PI Header bar -->
  <div style="background:#1a1a2e;padding:14px 18px;border-radius:8px 8px 0 0;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
    <div>
      <span style="color:#fff;font-weight:700;font-size:15px" id="pi-number-display">New PI</span>
      <span id="pi-status-badge" style="font-size:10px;color:#9090aa;margin-left:8px;background:rgba(255,255,255,.1);padding:2px 8px;border-radius:8px"></span>
    </div>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <select id="pi-template-sel" class="iip-select" style="font-size:11px;background:#2a2a4e;color:#fff;border-color:#3a3a6e">
        <option value="navy">Navy Template</option>
        <option value="export">Export Pro</option>
        <option value="minimal">Minimal</option>
      </select>
      <button class="iip-btn sm" id="btn-save-pi-draft" style="background:#2a2a4e;color:#fff;border-color:#3a3a6e" title="Step 1 — Save draft">💾 1. Save</button>
      <button class="iip-btn sm primary" id="btn-preview-review" title="Step 2 — Preview PDF and mark as reviewed">👁 2. Preview &amp; Review</button>
      <span id="pi-reviewed-badge" style="display:none;font-size:10px;background:#2e7d32;color:#fff;padding:2px 8px;border-radius:8px;align-self:center">✅ Reviewed</span>
      <button class="iip-btn sm success" id="btn-proceed-send" disabled title="Preview PI first (Step 2)" style="opacity:0.45;cursor:not-allowed">✉ 3. Send to Buyer</button>
    </div>
  </div>

  <div style="padding:16px 18px">

  <!-- ═══ BUYER INFORMATION ═══ -->
  <div style="margin-bottom:16px;padding-bottom:16px;border-bottom:2px solid #e0e0e0">
    <div style="font-size:11px;font-weight:700;color:#555;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px">👤 Buyer / Contact Information</div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:10px">
      <div class="iip-fg"><label>Buyer Name *</label><input type="text" id="pi-buyer-name" class="iip-input" style="width:100%" placeholder="Full name"></div>
      <div class="iip-fg"><label>Email *</label><input type="email" id="pi-buyer-email" class="iip-input" style="width:100%" placeholder="buyer@company.com"></div>
      <div class="iip-fg"><label>Phone / WhatsApp</label><input type="tel" id="pi-buyer-phone" class="iip-input" style="width:100%" placeholder="+1 555 000 0000"></div>
      <div class="iip-fg"><label>Company / Organization</label><input type="text" id="pi-buyer-company" class="iip-input" style="width:100%"></div>
      <div class="iip-fg"><label>Country *</label>
        <select id="pi-buyer-country" class="iip-select" style="width:100%" onchange="detectDomestic(this.value)">
          <option value="">-- Select Country --</option>
          <optgroup label="🇮🇳 Domestic">
            <option value="India">India (Domestic)</option>
          </optgroup>
          <optgroup label="🌍 Export — Asia">
            <option value="UAE">UAE</option>
            <option value="Saudi Arabia">Saudi Arabia</option>
            <option value="Malaysia">Malaysia</option>
            <option value="Singapore">Singapore</option>
            <option value="Bangladesh">Bangladesh</option>
            <option value="Sri Lanka">Sri Lanka</option>
            <option value="Nepal">Nepal</option>
            <option value="Myanmar">Myanmar</option>
            <option value="Thailand">Thailand</option>
            <option value="Vietnam">Vietnam</option>
            <option value="Indonesia">Indonesia</option>
            <option value="Philippines">Philippines</option>
            <option value="Japan">Japan</option>
            <option value="South Korea">South Korea</option>
            <option value="China">China</option>
          </optgroup>
          <optgroup label="🌍 Export — Africa">
            <option value="Nigeria">Nigeria</option>
            <option value="Kenya">Kenya</option>
            <option value="South Africa">South Africa</option>
            <option value="Ethiopia">Ethiopia</option>
            <option value="Ghana">Ghana</option>
            <option value="Tanzania">Tanzania</option>
            <option value="Uganda">Uganda</option>
            <option value="Egypt">Egypt</option>
            <option value="Morocco">Morocco</option>
          </optgroup>
          <optgroup label="🌍 Export — Europe">
            <option value="Germany">Germany</option>
            <option value="UK">UK</option>
            <option value="France">France</option>
            <option value="Netherlands">Netherlands</option>
            <option value="Italy">Italy</option>
            <option value="Spain">Spain</option>
            <option value="Poland">Poland</option>
            <option value="Sweden">Sweden</option>
            <option value="Switzerland">Switzerland</option>
          </optgroup>
          <optgroup label="🌍 Export — Americas">
            <option value="USA">USA</option>
            <option value="Canada">Canada</option>
            <option value="Brazil">Brazil</option>
            <option value="Mexico">Mexico</option>
            <option value="Argentina">Argentina</option>
            <option value="Colombia">Colombia</option>
          </optgroup>
          <optgroup label="🌍 Export — Oceania">
            <option value="Australia">Australia</option>
            <option value="New Zealand">New Zealand</option>
          </optgroup>
          <optgroup label="🌍 Export — Other">
            <option value="Russia">Russia</option>
            <option value="Turkey">Turkey</option>
            <option value="Israel">Israel</option>
            <option value="Pakistan">Pakistan</option>
          </optgroup>
        </select>
      </div>
    </div>

    <!-- Bill To / Ship To in 2 columns -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:10px">
      <!-- Bill To -->
      <div style="background:#f0f6ff;border:1px solid #bfdbfe;border-radius:7px;padding:12px">
        <div style="font-size:11px;font-weight:700;color:#1e40af;margin-bottom:8px;text-transform:uppercase;letter-spacing:.3px">🧾 Bill To (Invoice Address)</div>
        <div class="iip-fg" style="margin-bottom:8px"><label style="font-size:11px">Name</label><input type="text" id="pi-bill-name" class="iip-input" style="width:100%;font-size:11px" placeholder="Billing contact name"></div>
        <div class="iip-fg" style="margin-bottom:8px"><label style="font-size:11px">Company</label><input type="text" id="pi-bill-company" class="iip-input" style="width:100%;font-size:11px"></div>
        <div class="iip-fg"><label style="font-size:11px">Full Address</label><textarea id="pi-bill-address" class="iip-input" rows="3" style="width:100%;font-size:11px" placeholder="Street, City, State, ZIP, Country"></textarea></div>
      </div>

      <!-- Ship To -->
      <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:7px;padding:12px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
          <div style="font-size:11px;font-weight:700;color:#065f46;text-transform:uppercase;letter-spacing:.3px">📦 Ship To (Delivery Address)</div>
          <label style="font-size:10px;display:flex;align-items:center;gap:5px;cursor:pointer;color:#555">
            <input type="checkbox" id="ship-same-as-bill" checked onchange="toggleShipAddress(this.checked)"> Same as Bill To
          </label>
        </div>
        <div id="ship-address-fields" style="display:none">
          <div class="iip-fg" style="margin-bottom:8px"><label style="font-size:11px">Name</label><input type="text" id="pi-ship-name" class="iip-input" style="width:100%;font-size:11px"></div>
          <div class="iip-fg" style="margin-bottom:8px"><label style="font-size:11px">Company</label><input type="text" id="pi-ship-company" class="iip-input" style="width:100%;font-size:11px"></div>
          <div class="iip-fg"><label style="font-size:11px">Shipping Address</label><textarea id="pi-ship-address" class="iip-input" rows="3" style="width:100%;font-size:11px" placeholder="Different delivery address"></textarea></div>
        </div>
        <div id="ship-same-notice" style="font-size:12px;color:#065f46;padding:20px 10px;text-align:center">✓ Same as billing address</div>
      </div>
    </div>

    <!-- Shipment Details -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
      <div class="iip-fg"><label>Incoterm</label>
        <select id="pi-incoterm" class="iip-select" style="width:100%">
          <option value="FOB">FOB – Free on Board</option>
          <option value="CIF">CIF – Cost Ins. Freight</option>
          <option value="EXW">EXW – Ex Works</option>
          <option value="DDP">DDP – Delivered Duty Paid</option>
          <option value="CFR">CFR – Cost & Freight</option>
          <option value="DAP">DAP – Delivered at Place</option>
        </select>
      </div>
      <div class="iip-fg"><label>Port / Delivery Location</label><input type="text" id="pi-port" class="iip-input" style="width:100%" value="New Delhi, India"></div>
      <div class="iip-fg"><label>Payment Terms</label><input type="text" id="pi-payment" class="iip-input" style="width:100%"></div>
      <div class="iip-fg"><label>Delivery Days</label><input type="text" id="pi-delivery" class="iip-input" style="width:100%"></div>
      <div class="iip-fg"><label>Valid Until</label><input type="date" id="pi-valid-until" class="iip-input" style="width:100%"></div>

      <!-- Payment Method Toggle -->
      <div class="iip-fg">
        <label>Payment Method on PI</label>
        <select id="pi-payment-method" class="iip-select" style="width:100%" onchange="updatePaymentDisplay(this.value)">
          <option value="bank">🏦 Bank / SWIFT (Default)</option>
          <option value="upi">📱 UPI / GPay (Domestic)</option>
          <option value="paypal">🅿️ PayPal</option>
          <option value="wise">💸 Wise Transfer</option>
          <option value="paypal_wise">🅿️+💸 PayPal + Wise</option>
          <option value="bank_upi">🏦+📱 Bank + UPI</option>
        </select>
        <!-- v1.3.2: Auto bank info pill -->
        <div id="pi-bank-info-pill" style="display:none;margin-top:5px;font-size:10px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:5px;padding:4px 8px;color:#065f46;font-family:monospace"></div>
      </div>

      <!-- UPI Override for this PI -->
      <div id="pi-upi-override-wrap" style="display:none;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:7px;padding:12px;margin-top:-4px">
        <div style="font-size:11px;font-weight:700;color:#065f46;margin-bottom:8px">📱 UPI Details for this PI</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
          <div class="iip-fg" style="margin:0">
            <label style="font-size:11px">UPI ID <small style="color:#888">(overrides Settings value)</small></label>
            <input type="text" id="pi-upi-id" class="iip-input" style="width:100%;font-family:monospace" placeholder="e.g. 9996416935@ybl">
          </div>
          <div class="iip-fg" style="margin:0">
            <label style="font-size:11px">Display Name on QR</label>
            <input type="text" id="pi-upi-name" class="iip-input" style="width:100%" placeholder="SGC Labs">
          </div>
          <div class="iip-fg" style="margin:0;grid-column:span 2">
            <label style="font-size:11px">Custom QR Image URL <small style="color:#888">(optional — blank = auto-generate)</small></label>
            <div style="display:flex;gap:6px">
              <input type="url" id="pi-upi-qr-url" class="iip-input" style="flex:1;font-size:11px" placeholder="https://... paste QR image URL">
              <button type="button" class="iip-btn sm" id="btn-pi-upload-qr" style="white-space:nowrap">📁 Media</button>
            </div>
          </div>
          <div style="grid-column:span 2">
            <div id="pi-upi-qr-preview" style="display:none;margin-top:6px">
              <img id="pi-upi-qr-img" src="" width="80" style="border:1px solid #e0e0e0;border-radius:5px;display:block">
              <div style="font-size:10px;color:#888;margin-top:3px">QR Preview — will print on PDF</div>
            </div>
            <button type="button" class="iip-btn sm" id="btn-pi-gen-qr" style="margin-top:6px;font-size:11px">🔄 Preview Auto QR</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Payment Preview -->
    <div id="payment-method-preview" style="margin-top:10px;padding:10px 14px;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;font-size:12px;display:none">
      <div id="payment-preview-content" style="color:#92400e"></div>
    </div>
  </div>

  <!-- ── LINE ITEMS ── -->
  <div style="margin-bottom:10px;display:flex;align-items:center;justify-content:space-between">
    <strong style="font-size:12px;color:#1a1a2e">📦 Line Items</strong>
    <div style="display:flex;gap:6px">
      <button class="iip-btn sm" id="btn-add-line">+ Add Row</button>
      <button class="iip-btn sm" id="btn-add-from-wc">🛒 Add from WC</button>
    </div>
  </div>

  <div style="overflow-x:auto;margin-bottom:14px">
  <table class="iip-li-table" id="pi-line-table" style="font-size:11px">
    <thead><tr>
      <th style="width:22%">Product Name</th>
      <th style="width:9%">HS Code</th>
      <th style="width:7%">SKU</th>
      <th style="width:6%">Qty</th>
      <th style="width:10%">Unit Price</th>
      <!-- Pkg/Unit column removed v1.3.2 -->
      <th style="width:9%">OEM Charge</th>
      <th style="width:10%">Line Total</th>
      <th style="width:12%">Specs/Note</th>
      <th style="width:4%"></th>
    </tr></thead>
    <tbody id="pi-line-body"></tbody>
    <tfoot>
      <tr style="background:#f0f2f5">
        <td colspan="7" style="padding:6px 8px;font-weight:600;font-size:11px;color:#888">Products Subtotal:</td>
        <td style="padding:6px 8px;font-weight:700;color:#1a1a2e;font-size:12px" id="li-grand-display">₹0.00</td>
        <td></td>
      </tr>
    </tfoot>
  </table>
  </div>

  <!-- ── CHARGES & TAXES ── -->
  <div style="background:#f8f9fa;border-radius:6px;padding:14px;margin-bottom:14px">
    <div style="font-size:11px;font-weight:700;color:#555;margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px">Additional Charges & Taxes</div>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px">

      <div class="iip-fg">
        <label>Packaging & Handling <small style="color:#888">(total)</small></label>
        <div style="display:flex;align-items:center;gap:4px">
          <span id="cur-sym-display" style="font-size:12px;color:#555">Rs.</span>
          <input type="number" id="pi-packaging" class="iip-input" value="0" step="0.01" style="flex:1" oninput="piPkgManual=true;recalcTotals()">
        </div>
      </div>

      <div class="iip-fg">
        <label>OEM / Custom Total <small style="color:#888">(if flat)</small></label>
        <div style="display:flex;align-items:center;gap:4px">
          <span style="font-size:12px;color:#555" id="cur-sym2">Rs.</span>
          <input type="number" id="pi-oem-total" class="iip-input" value="0" step="0.01" style="flex:1" oninput="recalcTotals()">
        </div>
      </div>

      <div class="iip-fg">
        <label>Freight Charges</label>
        <div style="display:flex;align-items:center;gap:4px">
          <span style="font-size:12px;color:#555" id="cur-sym3">Rs.</span>
          <input type="number" id="pi-freight" class="iip-input" value="0" step="0.01" style="flex:1" oninput="recalcTotals()">
        </div>
      </div>

      <div class="iip-fg">
        <label>Discount</label>
        <div style="display:flex;align-items:center;gap:4px">
          <span style="font-size:12px;color:#555">-</span>
          <input type="number" id="pi-discount" class="iip-input" value="0" step="0.01" style="flex:1" oninput="recalcTotals()">
        </div>
      </div>

      <div class="iip-fg" id="gst-section">
        <label>GST % <small id="gst-note" style="color:#2e7d32">(Indian buyer)</small></label>
        <div style="display:flex;align-items:center;gap:4px">
          <input type="number" id="pi-gst-pct" class="iip-input" value="0" step="0.5" min="0" max="28" style="width:70px" oninput="recalcTotals()">
          <span style="font-size:12px;color:#888">%</span>
          <span style="font-size:11px;color:#2e7d32;margin-left:4px" id="gst-calc-display"></span>
        </div>
      </div>

    </div>
  </div>

  <!-- ── GRAND TOTAL DISPLAY ── -->
  <div style="background:#1a1a2e;border-radius:6px;padding:14px 18px;margin-bottom:14px">
    <table width="100%" style="font-size:12px;color:#ccc">
      <tr><td>Subtotal (products)</td><td align="right" id="tot-sub">0.00</td></tr>
      <tr><td>Packaging & Handling</td><td align="right" id="tot-pkg">0.00</td></tr>
      <tr id="tot-oem-row" style="display:none"><td>OEM Charges</td><td align="right" id="tot-oem">0.00</td></tr>
      <tr id="tot-gst-row" style="display:none"><td id="tot-gst-label">GST (0%)</td><td align="right" id="tot-gst">0.00</td></tr>
      <tr id="tot-freight-row" style="display:none"><td>Freight</td><td align="right" id="tot-freight">0.00</td></tr>
      <tr id="tot-disc-row" style="display:none"><td>Discount</td><td align="right" id="tot-disc" style="color:#f87171">-0.00</td></tr>
    </table>
    <div style="border-top:1px solid #3a3a6e;margin-top:8px;padding-top:8px;display:flex;justify-content:space-between;align-items:center">
      <span style="color:#9090aa;font-size:12px;font-weight:600">GRAND TOTAL</span>
      <span id="tot-grand" style="color:#4ade80;font-size:22px;font-weight:900">Rs. 0.00</span>
    </div>
    <div id="tot-equiv" style="text-align:right;font-size:11px;color:#6b7280;margin-top:4px"></div>
  </div>

  <!-- Notes -->
  <div class="iip-fg"><label>Notes (printed on PI)</label><textarea id="pi-notes" class="iip-input" rows="2" style="width:100%"></textarea></div>

  <!-- ── DISPATCH / SHIPMENT TRACKING ── -->
  <div style="background:linear-gradient(135deg,#0f4c35,#1a6b4a);border-radius:8px;padding:16px 18px;margin-top:14px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px">
      <div>
        <div style="color:#fff;font-weight:700;font-size:13px">🚚 Dispatch &amp; Shipment</div>
        <div style="color:#86efac;font-size:11px;margin-top:2px">Fill freight details → One click buyer notify</div>
      </div>
      <button id="btn-send-dispatch" class="iip-btn" style="background:#4ade80;color:#14532d;border:none;font-weight:700;padding:8px 16px;font-size:12px;border-radius:6px">
        📦 Dispatch &amp; Notify Buyer
      </button>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <div class="iip-fg" style="margin:0">
        <label style="color:#86efac;font-size:11px;font-weight:600">Freight Partner / Courier</label>
        <input type="text" id="pi-freight-partner" class="iip-input" style="width:100%;background:rgba(255,255,255,.1);color:#fff;border-color:rgba(255,255,255,.2)" placeholder="e.g. DHL, FedEx, DTDC, BlueDart">
      </div>
      <div class="iip-fg" style="margin:0">
        <label style="color:#86efac;font-size:11px;font-weight:600">Tracking Number</label>
        <input type="text" id="pi-tracking-no" class="iip-input" style="width:100%;background:rgba(255,255,255,.1);color:#fff;border-color:rgba(255,255,255,.2)" placeholder="e.g. 1Z999AA10123456784">
      </div>
      <div class="iip-fg" style="margin:0">
        <label style="color:#86efac;font-size:11px;font-weight:600">Dispatch Date</label>
        <input type="date" id="pi-dispatch-date" class="iip-input" style="width:100%;background:rgba(255,255,255,.1);color:#fff;border-color:rgba(255,255,255,.2)" value="<?php echo date('Y-m-d'); ?>">
      </div>
      <div class="iip-fg" style="margin:0">
        <label style="color:#86efac;font-size:11px;font-weight:600">Tracking URL (optional)</label>
        <input type="url" id="pi-tracking-url" class="iip-input" style="width:100%;background:rgba(255,255,255,.1);color:#fff;border-color:rgba(255,255,255,.2)" placeholder="https://track.dhl.com/...">
      </div>
    </div>
    <div id="dispatch-notice" style="margin-top:10px;font-size:12px;color:#86efac;display:none"></div>
  </div>

  </div><!-- /padding div -->
</div><!-- /pi-editor -->
</div><!-- /left -->

<!-- ── RIGHT: PI List + Quick Stats ── -->
<div>
  <!-- AI Tools -->
  <div class="iip-box" style="margin-bottom:14px">
    <h3>🤖 AI Price Suggest</h3>
    <div class="iip-fg"><label>Product</label><input type="text" id="ai-prod" class="iip-input" style="width:100%" placeholder="e.g. Borosilicate Beaker 500ml"></div>
    <div class="iip-fg"><label>Qty</label><input type="text" id="ai-qty" class="iip-input" style="width:100%" placeholder="100 pcs"></div>
    <button class="iip-btn" id="btn-ai-price" style="width:100%;margin-bottom:8px">💡 Get AI Price</button>
    <div id="ai-price-result" style="font-size:12px;background:#f5f7fa;border-radius:5px;padding:10px;display:none"></div>
  </div>

  <!-- PI List -->
  <div class="iip-box">
    <h3>📋 PI History</h3>
    <div id="pi-list-panel" style="font-size:12px;color:#aaa">Load an inquiry to see PIs.</div>
  </div>

  <!-- WC Product Search -->
  <div class="iip-box">
    <h3>🛒 WC Product Lookup</h3>
    <input type="text" id="wc-prod-search" class="iip-input" style="width:100%;margin-bottom:8px" placeholder="Search product name…">
    <button class="iip-btn" id="btn-wc-search" style="width:100%">Search</button>
    <div id="wc-prod-results" style="margin-top:8px;font-size:12px"></div>
  </div>
</div>
</div>

<!-- PI Preview Modal -->
<div class="iip-modal" id="modal-pi-preview" style="display:none">
  <div class="iip-modal-inner" style="max-width:820px">
    <div class="iip-modal-hdr"><h3>👁 PI Preview</h3><button onclick="closeModal('modal-pi-preview')">✕</button></div>
    <div class="iip-modal-body" style="padding:0"><iframe id="pi-preview-frame" style="width:100%;height:620px;border:none"></iframe></div>
    <div class="iip-modal-ftr" style="justify-content:space-between">
      <button class="iip-btn" onclick="closeModal('modal-pi-preview')">← Back to Edit</button>
      <button class="iip-btn success" id="btn-mark-reviewed" style="font-weight:700;padding:9px 22px">✅ Looks Good — Mark as Reviewed</button>
    </div>
  </div>
</div>

<!-- ── Cancel PI Modal ─────────────────────────────────────────────────── -->
<div class="iip-modal" id="modal-cancel-pi" style="display:none">
  <div class="iip-modal-inner" style="max-width:480px">
    <div class="iip-modal-hdr" style="background:#b91c1c"><h3>⚠️ Cancel PI</h3><button onclick="closeModal('modal-cancel-pi')">✕</button></div>
    <div class="iip-modal-body">
      <p style="color:#333;font-size:13px;margin:0 0 4px">This PI was already sent to the buyer.</p>
      <p style="color:#333;font-size:13px;margin:0 0 16px">A <strong>cancellation notification email</strong> will be sent to them.</p>
      <div class="iip-fg">
        <label style="font-size:12px;font-weight:600;color:#555">Reason for Cancellation *</label>
        <textarea id="pi-cancel-reason" class="iip-input" rows="3" style="width:100%;resize:vertical;font-size:13px"
                  placeholder="e.g. Price revision required. We will send you a revised PI shortly."></textarea>
        <span id="pi-cancel-reason-err" style="color:#dc2626;font-size:11px;display:none">Reason is required.</span>
      </div>
      <p style="font-size:11px;color:#b91c1c;margin-top:12px;border-top:1px solid #fecaca;padding-top:10px">
        ⚠️ This cannot be undone. PI status will be set to <strong>Cancelled</strong>.
      </p>
    </div>
    <div class="iip-modal-ftr" style="justify-content:space-between">
      <button class="iip-btn" onclick="closeModal('modal-cancel-pi')">← Back</button>
      <button class="iip-btn" id="btn-confirm-cancel-pi"
              style="background:#b91c1c;color:#fff;border-color:#b91c1c;font-weight:700">
        Cancel PI &amp; Notify Buyer
      </button>
    </div>
  </div>
</div>

<!-- WC Product Add Modal -->
<div class="iip-modal" id="modal-wc-product" style="display:none">
  <div class="iip-modal-inner" style="max-width:500px">
    <div class="iip-modal-hdr"><h3>🛒 Add from WooCommerce</h3><button onclick="closeModal('modal-wc-product')">✕</button></div>
    <div class="iip-modal-body">
      <input type="text" id="wc-search-modal" class="iip-input" style="width:100%;margin-bottom:10px" placeholder="Search product…">
      <div id="wc-modal-results"></div>
    </div>
  </div>
</div>

<script>
var piId=0, currentInquiry=null, isDomestic=false;
var usdRate         = parseFloat('<?php echo $usd_rate; ?>');
var exportFreight   = parseFloat('<?php echo $export_freight; ?>');
var domesticFreight = parseFloat('<?php echo $domestic_freight; ?>');
var defaultIncoterm = '<?php echo esc_js($default_incoterm); ?>';
var defaultPort     = '<?php echo esc_js($default_port); ?>';
var defaultPayment  = '<?php echo esc_js($default_payment); ?>';
var defaultDelivery = '<?php echo esc_js($default_delivery); ?>';

// Country → domestic check map
var domesticCountries = ['india','in','bharat','भारत','ind'];

// Country → incoterm/port/currency suggestions
var countryConfig = {
  'india':        {inco:'EXW', port:'Customer Location', cur:'INR', pay:'upi', dom:true},
  'usa':          {inco:'CIF', port:'New York / Los Angeles', cur:'USD', pay:'bank', dom:false},
  'germany':      {inco:'CIF', port:'Hamburg', cur:'USD', pay:'bank', dom:false},
  'uae':          {inco:'CIF', port:'Jebel Ali, Dubai', cur:'USD', pay:'bank', dom:false},
  'uk':           {inco:'CIF', port:'Felixstowe', cur:'USD', pay:'bank', dom:false},
  'nigeria':      {inco:'CIF', port:'Apapa, Lagos', cur:'USD', pay:'bank', dom:false},
  'kenya':        {inco:'CIF', port:'Mombasa', cur:'USD', pay:'bank', dom:false},
  'saudi arabia': {inco:'CIF', port:'Jeddah', cur:'USD', pay:'bank', dom:false},
  'australia':    {inco:'CIF', port:'Sydney', cur:'USD', pay:'bank', dom:false},
  'canada':       {inco:'CIF', port:'Vancouver', cur:'USD', pay:'bank', dom:false},
  'brazil':       {inco:'CIF', port:'Santos', cur:'USD', pay:'bank', dom:false},
  'south africa': {inco:'CIF', port:'Durban', cur:'USD', pay:'bank', dom:false},
  'malaysia':     {inco:'CIF', port:'Port Klang', cur:'USD', pay:'bank', dom:false},
  'singapore':    {inco:'CIF', port:'Singapore', cur:'USD', pay:'bank', dom:false},
};

/* ── Ship To toggle ── */
function toggleShipAddress(same){
  jQuery('#ship-address-fields').toggle(!same);
  jQuery('#ship-same-notice').toggle(same);
}

/* ── Payment Method Preview ── */
function updatePaymentDisplay(method){
  var paypal_email = '<?php echo esc_js(get_option("sgc_iip_paypal_email","")); ?>';
  var paypal_link  = '<?php echo esc_js(get_option("sgc_iip_paypal_link","")); ?>';
  var wise_email   = '<?php echo esc_js(get_option("sgc_iip_wise_email","")); ?>';
  var wise_link    = '<?php echo esc_js(get_option("sgc_iip_wise_link","")); ?>';
  var upi_id       = '<?php echo esc_js(get_option("sgc_iip_upi_id","")); ?>';
  var gpay_name    = '<?php echo esc_js(get_option("sgc_iip_gpay_name","SGC Labs")); ?>';

  // Show/hide UPI override section
  var showUpi = (method==='upi'||method==='bank_upi');
  jQuery('#pi-upi-override-wrap').toggle(showUpi);
  if(showUpi && !jQuery('#pi-upi-id').val() && upi_id){
    jQuery('#pi-upi-id').val(upi_id);
    jQuery('#pi-upi-name').val(gpay_name);
  }

  var html = '';
  var show = false;

  if(method==='paypal'||method==='paypal_wise'){
    if(paypal_email) html+='🅿️ <strong>PayPal:</strong> '+paypal_email+(paypal_link?' | <a href="https://'+paypal_link+'" target="_blank">'+paypal_link+'</a>':'')+'<br>';
    show=true;
  }
  if(method==='wise'||method==='paypal_wise'){
    if(wise_email) html+='💸 <strong>Wise:</strong> '+wise_email+(wise_link?' | <a href="https://'+wise_link+'" target="_blank">'+wise_link+'</a>':'')+'<br>';
    show=true;
  }
  if(method==='upi'||method==='bank_upi'){
    if(upi_id) html+='📱 <strong>GPay/UPI:</strong> '+upi_id+' ('+gpay_name+')<br>';
    show=true;
  }
  if(method==='bank'||method==='bank_upi') html+='🏦 <strong>Bank:</strong> SWIFT/IFSC details from Settings<br>';

  if(!html) html='ℹ️ Configure payment details in Settings → Payment Methods';
  jQuery('#payment-preview-content').html(html);
  jQuery('#payment-method-preview').toggle(show||method!=='bank');
}

/* ── Domestic detection ── */
function detectDomestic(country, skipOverrides){
  var key = (country||'').toLowerCase().trim();
  isDomestic = domesticCountries.indexOf(key) >= 0;
  var cfg = countryConfig[key] || null;

  // Currency symbol
  var sym = isDomestic ? 'Rs.' : '$';
  jQuery('#cur-sym-display,#cur-sym2,#cur-sym3,.iip-currency-sym').text(sym);
  jQuery('#gst-section').toggle(isDomestic);
  jQuery('#gst-note').text(isDomestic ? '(Indian buyer — GST applicable)' : '(N/A for export)');
  if (!isDomestic) jQuery('#pi-gst-pct').val('0');  // v1.3.5: reset GST on export switch

  // Auto payment method
  jQuery('#pi-payment-method').val(
    cfg ? cfg.pay : (isDomestic ? 'upi' : 'bank')
  ).trigger('change');

  // Auto incoterm
  jQuery('#pi-incoterm').val(
    cfg ? cfg.inco : (isDomestic ? 'EXW' : (defaultIncoterm||'FOB'))
  );

  // Auto port
  jQuery('#pi-port').val(
    cfg ? cfg.port : (isDomestic ? 'Customer Location' : (defaultPort||'New Delhi, India'))
  );

  // Auto payment terms (only if empty)
  if(!jQuery('#pi-payment').val()){
    jQuery('#pi-payment').val(isDomestic
      ? 'Advance payment before dispatch'
      : (defaultPayment||'30% advance, 70% before dispatch'));
  }

  // Auto delivery (only if empty)
  if(!jQuery('#pi-delivery').val()){
    jQuery('#pi-delivery').val(isDomestic ? '7-10 working days' : (defaultDelivery||'15-30 working days'));
  }

  // v1.3.5 FIX Bug6: Only reset freight/notes on NEW PI, not when loading saved PI
  if (!skipOverrides) {
    jQuery('#pi-freight').val(0); // Zero freight on new PI
    var notesField = jQuery('#pi-notes');
    if(!isDomestic && !notesField.val()){
      notesField.val('Price basis: Ex-Works, Ambala, India.\nFreight / shipping charges are NOT included and will be charged at actuals or as per agreed Incoterm.\n7% packaging & handling charges are included in the above prices.');
    } else if(isDomestic && !notesField.val()){
      notesField.val('Price basis: Ex-Works, Ambala, Haryana.\nTransportation / courier charges extra at actuals.\n7% packaging & handling charges are included in the above prices.');
    }
    piPkgManual = false; // Reset manual flag on new PI
  }

  // v1.3.2: Auto-load bank details based on country (INR/USD)
  sgcAutoLoadBank(isDomestic);

  recalcTotals();
}

// Load bank details from Settings into PI based on domestic/export
function sgcAutoLoadBank(domestic){
  jQuery.post(SGCIIP.ajaxUrl,{
    action: 'sgc_iip_get_bank_for_pi',
    nonce:  SGCIIP.nonce,
    type:   domestic ? 'domestic' : 'export'
  },function(r){
    if(!r.success) return;
    var b = r.data;
    // Show bank info pill near payment method
    var bankInfo = domestic
      ? (b.beneficiary ? b.beneficiary + ' | IFSC: ' + b.ifsc + ' | UPI: ' + (b.upi_id||'—') : '')
      : (b.beneficiary ? b.beneficiary + ' | SWIFT: ' + b.swift + ' | Acc: ' + b.account : '');
    if(bankInfo) jQuery('#pi-bank-info-pill').text('🏦 ' + bankInfo).show();
    else jQuery('#pi-bank-info-pill').hide();
  });
}

/* ── Recalc all totals ── */
// v1.3.5: piPkgManual flag — true when user manually edits packaging field
var piPkgManual = false;

function recalcTotals(){
  // price × qty per line
  var sub=0;
  jQuery('#pi-line-body tr').each(function(){
    var qty = parseFloat(jQuery(this).find('.li-qty').val())  || 0;
    var u   = parseFloat(jQuery(this).find('.li-unit').val()) || 0;
    var lt  = qty * u;
    jQuery(this).find('.li-total').val(lt.toFixed(2));
    sub += lt;
  });

  // v1.3.5 FIX Bug2: Auto 7% packaging — only when user has NOT manually set it
  var pkg;
  if (piPkgManual) {
    pkg = parseFloat(jQuery('#pi-packaging').val()) || 0;
  } else {
    pkg = sub > 0 ? Math.round(sub * 0.07 * 100) / 100 : 0;
    jQuery('#pi-packaging').val(pkg.toFixed(2));
  }

  var oem     = parseFloat(jQuery('#pi-oem-total').val())  || 0;
  var freight = parseFloat(jQuery('#pi-freight').val())    || 0;
  var disc    = parseFloat(jQuery('#pi-discount').val())   || 0;
  var gstPct  = parseFloat(jQuery('#pi-gst-pct').val())   || 0;

  // Formula: (subtotal + packaging + OEM − discount) + GST + freight = grand total
  var taxable = sub + pkg + oem - disc;
  var gstAmt  = (gstPct > 0 && isDomestic) ? Math.round(taxable * gstPct / 100 * 100) / 100 : 0;
  var grand   = taxable + gstAmt + freight;

  // v1.3.5 FIX Bug1: Use pkg/oem (finalPkg/finalOem were undefined → ReferenceError)
  jQuery('#tot-sub').text(fmtAmt(sub));
  jQuery('#tot-pkg').text(fmtAmt(pkg));
  jQuery('#tot-oem-row').toggle(oem>0); jQuery('#tot-oem').text(fmtAmt(oem));
  jQuery('#tot-freight-row').toggle(freight>0); jQuery('#tot-freight').text(fmtAmt(freight));
  jQuery('#tot-disc-row').toggle(disc>0); jQuery('#tot-disc').text('-'+fmtAmt(disc));
  jQuery('#tot-gst-row').toggle(gstPct>0 && isDomestic);  // v1.3.5: export must not show GST row
  jQuery('#tot-gst-label').text('GST ('+gstPct+'%)');
  jQuery('#tot-gst').text(fmtAmt(gstAmt));
  jQuery('#gst-calc-display').text(gstPct>0?'= '+fmtAmt(gstAmt):'');
  jQuery('#tot-grand').text(fmtAmt(grand));
  // v1.3.5 FIX Bug5: tfoot label says "Total from line items" → show products subtotal
  jQuery('#li-grand-display').text(fmtAmt(sub));

  if(isDomestic && grand>0 && usdRate>0){
    jQuery('#tot-equiv').text('Equiv. USD: $'+( grand/usdRate ).toFixed(2)+' (@Rs.'+usdRate+')');
  } else if(!isDomestic && grand>0 && usdRate>0){
    jQuery('#tot-equiv').text('Equiv. INR: Rs.'+(grand*usdRate).toFixed(2)+' (@'+usdRate+')');
  } else {
    jQuery('#tot-equiv').text('');
  }
}

function fmtAmt(v){
  var sym=isDomestic?'Rs. ':'$ ';
  return sym+parseFloat(v||0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');
}
function escH(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

/* ── Add line row ── */
function addLineRow(item){
  item=item||{};
  var row=jQuery('<tr>'+
    '<td><input class="li-name iip-input" value="'+escH(item.product_name||item.name||'')+'" style="width:100%;font-size:11px;padding:4px 6px"></td>'+
    '<td><input class="li-hs iip-input" value="'+(item.hs_code||'7017.20')+'" style="width:100%;font-size:10px;padding:3px 5px"></td>'+
    '<td><input class="li-sku iip-input" value="'+(item.sku||'')+'" style="width:100%;font-size:10px;padding:3px 5px"></td>'+
    '<td><input class="li-qty iip-input" type="number" value="'+(item.qty||1)+'" style="width:100%;font-size:11px;padding:4px 5px" min="0"></td>'+
    '<td><input class="li-unit iip-input" type="number" value="'+(item.unit_price||0)+'" style="width:100%;font-size:11px;padding:4px 5px" step="0.01" min="0"></td>'+
    /* li-pkg removed v1.3.2 */
    '<td><input class="li-oem iip-input" type="number" value="'+(item.oem_charge||0)+'" style="width:100%;font-size:11px;padding:4px 5px" step="0.01"></td>'+
    '<td><input class="li-total iip-input" type="number" value="'+(item.total||0)+'" style="width:100%;font-size:11px;padding:4px 5px;font-weight:600;background:#f0fdf4" step="0.01" readonly></td>'+
    '<td><input class="li-specs iip-input" value="'+(item.specs||'')+'" style="width:100%;font-size:10px;padding:3px 5px" placeholder="optional"></td>'+
    '<td style="text-align:center"><button onclick="jQuery(this).closest(\'tr\').remove();recalcTotals();" style="background:none;border:none;color:#e53935;cursor:pointer;font-size:15px;padding:0 4px">✕</button></td>'+
    '</tr>');
  row.find('input:not([readonly])').on('input',recalcTotals);
  jQuery('#pi-line-body').append(row);
  recalcTotals();
}

/* ── Get line items array ── */
function getLineItems(){
  var items=[];
  jQuery('#pi-line-body tr').each(function(){
    var qty=parseFloat(jQuery(this).find('.li-qty').val())||0;
    var u=parseFloat(jQuery(this).find('.li-unit').val())||0;
    var om=parseFloat(jQuery(this).find('.li-oem').val())||0;
    items.push({
      product_name:jQuery(this).find('.li-name').val(),
      hs_code:jQuery(this).find('.li-hs').val(),
      sku:jQuery(this).find('.li-sku').val(),
      qty:qty, unit_price:u, oem_charge:om,
      total:(qty*u).toFixed(2),  // v1.3.5: price × qty only
      specs:jQuery(this).find('.li-specs').val()
    });
  });
  return items;
}

/* ── Load PI into editor ── */
function loadPI(pi){
  piId=pi.id;
  piPkgManual = false; // v1.3.5: reset on each PI load
  if(typeof resetConveyorState==='function') resetConveyorState(); // v1.8.0: new PI load = re-review required
  jQuery('#pi-editor').show();
  jQuery('#pi-number-display').text(pi.pi_number);
  jQuery('#pi-status-badge').text('Status: '+pi.status);
  jQuery('#pi-template-sel').val(pi.template||'navy');
  jQuery('#pi-buyer-name').val(pi.buyer_name||'');
  jQuery('#pi-buyer-email').val(pi.buyer_email||'');
  jQuery('#pi-buyer-company').val(pi.buyer_company||'');
  // v1.3.5 FIX Bug6: Set country dropdown (detectDomestic called ONCE at end, skipOverrides=true)
  var cval = pi.buyer_country || '';
  jQuery('#pi-buyer-country').val(cval);
  // If not in dropdown, add as custom option
  if(cval && !jQuery('#pi-buyer-country option[value="'+cval+'"]').length){
    jQuery('#pi-buyer-country').append('<option value="'+cval+'" selected>'+cval+'</option>');
  }
  jQuery('#pi-buyer-address').val(pi.buyer_address||'');
  jQuery('#pi-incoterm').val(pi.incoterm||'FOB');
  jQuery('#pi-port').val(pi.port||'');
  jQuery('#pi-payment').val(pi.payment_terms||'');
  jQuery('#pi-delivery').val(pi.delivery_days||'');
  jQuery('#pi-valid-until').val(pi.valid_until||'');
  jQuery('#pi-notes').val(pi.notes||'');
  // Bill To / Ship To
  jQuery('#pi-buyer-phone').val(pi.buyer_phone||'');
  jQuery('#pi-bill-name').val(pi.bill_to_name||pi.buyer_name||'');
  jQuery('#pi-bill-company').val(pi.bill_to_company||pi.buyer_company||'');
  jQuery('#pi-bill-address').val(pi.bill_to_address||pi.buyer_address||'');
  var shipSame = pi.ship_to_same!=='0' && pi.ship_to_same!==0;
  jQuery('#ship-same-as-bill').prop('checked',shipSame);
  toggleShipAddress(shipSame);
  jQuery('#pi-ship-name').val(pi.ship_to_name||'');
  jQuery('#pi-ship-company').val(pi.ship_to_company||'');
  jQuery('#pi-ship-address').val(pi.ship_to_address||'');
  jQuery('#pi-payment-method').val(pi.payment_method||'bank');
  updatePaymentDisplay(pi.payment_method||'bank');
  // Load per-PI UPI if saved
  if(pi.pi_upi_id) jQuery('#pi-upi-id').val(pi.pi_upi_id);
  if(pi.pi_upi_name) jQuery('#pi-upi-name').val(pi.pi_upi_name);
  if(pi.pi_upi_qr_url){ jQuery('#pi-upi-qr-url').val(pi.pi_upi_qr_url); jQuery('#pi-upi-qr-img').attr('src',pi.pi_upi_qr_url); jQuery('#pi-upi-qr-preview').show(); }
  jQuery('#pi-packaging').val(parseFloat(pi.packaging_total||0).toFixed(2));
  jQuery('#pi-oem-total').val(parseFloat(pi.oem_charge||0).toFixed(2));
  jQuery('#pi-freight').val(parseFloat(pi.freight_charge||0).toFixed(2));  // v1.3.5: set BEFORE detectDomestic
  jQuery('#pi-discount').val(parseFloat(pi.discount||0).toFixed(2));
  jQuery('#pi-gst-pct').val(parseFloat(pi.gst_percent||0).toFixed(1));
  // v1.3.5 FIX Bug6: If saved PI had packaging, treat as manual so auto-calc doesn't overwrite
  if(parseFloat(pi.packaging_total||0) > 0) piPkgManual = true;
  // v1.3.5 FIX Bug6: Call detectDomestic ONCE, AFTER charges set, with skipOverrides=true
  detectDomestic(pi.buyer_country||'', true);
  jQuery('#pi-line-body').empty();
  (pi.line_items||[]).forEach(function(item){ addLineRow(item); });
  recalcTotals();
}

function loadPIById(id){
  jQuery.post(SGCIIP.ajaxUrl,{action:'sgc_iip_pi_get',nonce:SGCIIP.nonce,pi_id:id},function(r){
    if(r.success) loadPI(r.data);
  });
}

/* ── Save PI data ── */
function getPiSaveData(){
  // v1.3.2: Consistent formula — price × qty only, flat packaging + freight separate
  var items   = getLineItems();
  var sub     = items.reduce(function(a,b){ return a + parseFloat(b.qty)*parseFloat(b.unit_price); }, 0);
  var pkg     = parseFloat(jQuery('#pi-packaging').val())  || 0;
  var oem     = parseFloat(jQuery('#pi-oem-total').val())  || 0;
  var freight = parseFloat(jQuery('#pi-freight').val())    || 0;
  var disc    = parseFloat(jQuery('#pi-discount').val())   || 0;
  var gstPct  = parseFloat(jQuery('#pi-gst-pct').val())   || 0;
  var taxable = sub + pkg + oem - disc;
  var gstAmt  = (gstPct > 0 && isDomestic) ? Math.round(taxable*gstPct/100*100)/100 : 0;
  var grand   = taxable + gstAmt + freight;
  // Domestic = INR (no conversion), Export = USD × rate for INR equiv
  var grandInr = isDomestic ? grand : Math.round(grand * usdRate * 100) / 100;
  return {
    action:'sgc_iip_pi_save', nonce:SGCIIP.nonce, pi_id:piId,
    template:jQuery('#pi-template-sel').val(),
    buyer_address:jQuery('#pi-buyer-address').val(),
    incoterm:jQuery('#pi-incoterm').val(),
    port:jQuery('#pi-port').val(),
    payment_terms:jQuery('#pi-payment').val(),
    delivery_days:jQuery('#pi-delivery').val(),
    valid_until:jQuery('#pi-valid-until').val(),
    notes:jQuery('#pi-notes').val(),
    line_items:JSON.stringify(items),
    packaging_total:pkg.toFixed(2),
    oem_charge:oem.toFixed(2),
    freight_charge:freight.toFixed(2),
    discount:disc.toFixed(2),
    gst_percent:gstPct,
    tax_amount:gstAmt.toFixed(2),
    grand_total:grand.toFixed(2),
    grand_total_inr:grandInr.toFixed(2),
    subtotal:sub.toFixed(2),
    currency:isDomestic ? 'INR' : 'USD',
    inr_rate:usdRate,
    // ── Buyer / Contact Info ──
    buyer_name:    jQuery('#pi-buyer-name').val(),
    buyer_email:   jQuery('#pi-buyer-email').val(),
    buyer_phone:   jQuery('#pi-buyer-phone').val(),
    buyer_company: jQuery('#pi-buyer-company').val(),
    buyer_country: jQuery('#pi-buyer-country').val(),
    buyer_address: jQuery('#pi-buyer-address').val(),
    // ── Bill To ──
    bill_to_name:    jQuery('#pi-bill-name').val(),
    bill_to_company: jQuery('#pi-bill-company').val(),
    bill_to_address: jQuery('#pi-bill-address').val(),
    // ── Ship To ──
    ship_to_same:    jQuery('#ship-same-as-bill').is(':checked')?1:0,
    ship_to_name:    jQuery('#pi-ship-name').val(),
    ship_to_company: jQuery('#pi-ship-company').val(),
    ship_to_address: jQuery('#pi-ship-address').val(),
    // ── Payment ──
    payment_method:  jQuery('#pi-payment-method').val(),
    // Per-PI UPI override
    pi_upi_id:    jQuery('#pi-upi-id').val(),
    pi_upi_name:  jQuery('#pi-upi-name').val(),
    pi_upi_qr_url:jQuery('#pi-upi-qr-url').val()
  };
}

jQuery(function($){
  var inqId=parseInt('<?php echo esc_js($inquiry_id); ?>')||0;
  if(inqId){$('#pi-inquiry-id').val(inqId);setTimeout(function(){$('#btn-load-inquiry').click();},300);}

  $('#pi-usd-rate').on('change',function(){ usdRate=parseFloat($(this).val())||83.5; recalcTotals(); });

  $('#btn-load-inquiry').on('click',function(){
    inqId=parseInt($('#pi-inquiry-id').val())||0;
    if(!inqId){iipNotice('Enter inquiry ID','error');return;}
    $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_get_inquiry',nonce:SGCIIP.nonce,id:inqId},function(r){
      if(!r.success){iipNotice('Inquiry not found','error');return;}
      currentInquiry=r.data;
      $('#pi-inquiry-info').text('Loaded: '+r.data.name+(r.data.company?' ('+r.data.company+')':''));
      // Auto-fill buyer country for domestic detection
      if(r.data.country) detectDomestic(r.data.country);
      // Load PI list
      $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_pi_list',nonce:SGCIIP.nonce,inquiry_id:inqId},function(pr){
        var list=pr.data||[];
        var html=list.length?list.map(function(pi){
          return '<div style="border:1px solid #e0e0e0;border-radius:5px;padding:8px 10px;margin-bottom:6px;cursor:pointer;font-size:11px;display:flex;justify-content:space-between;align-items:center" onclick="loadPIById('+pi.id+')">'
            +'<div><strong>'+escH(pi.pi_number)+'</strong> <span style="color:#888">Rev.'+pi.revision+'</span><br>'
            +'<span style="font-size:10px;color:#aaa">'+pi.status+' · '+pi.pi_type+'</span></div>'
            +'<span style="color:#2e7d32;font-weight:700;font-size:12px">'+parseFloat(pi.grand_total||0).toFixed(2)+' '+pi.currency+'</span></div>';
        }).join('') : '<div style="color:#aaa;font-size:12px">No PIs yet.</div>';
        $('#pi-list-panel').html(html);
      });
    });
  });

  $('#btn-create-pi').on('click',function(){
    inqId=parseInt($('#pi-inquiry-id').val())||0;
    if(!inqId){iipNotice('Load inquiry first','error');return;}
    $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_pi_create',nonce:SGCIIP.nonce,inquiry_id:inqId,pi_type:'standard'},function(r){
      if(r.success){iipNotice('PI created: '+r.data.pi_number);loadPI(r.data);loadPIList(inqId);fetchAllPI();}
      else iipNotice(r.data,'error');
    });
  });

  $('#btn-ai-draft-pi').on('click',function(){
    inqId=parseInt($('#pi-inquiry-id').val())||0;
    if(!inqId){iipNotice('Load inquiry first','error');return;}
    $(this).prop('disabled',true).text('🤖 Drafting…');
    $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_ai_draft_pi',nonce:SGCIIP.nonce,inquiry_id:inqId},function(r){
      $('#btn-ai-draft-pi').prop('disabled',false).text('🤖 AI Draft');
      if(r.success){
        if(r.data.line_items&&r.data.line_items.length){
          $('#pi-line-body').empty();
          r.data.line_items.forEach(function(item){ addLineRow(item); });
          if(r.data.notes) $('#pi-notes').val(r.data.notes);
          iipNotice('✅ AI drafted '+r.data.line_items.length+' line items. Review & save!');
        } else {
          iipNotice('✅ AI draft saved. Create PI to view.','info');
        }
      } else iipNotice('AI: '+r.data,'error');
    });
  });

  $('#btn-add-line').on('click',function(){ addLineRow(); });
  $('#btn-add-from-wc').on('click',function(){ openModal('modal-wc-product'); loadWCModal(); });

  // ── 3-Step Conveyor State ──────────────────────────────────────────────
  var piReviewed = false;

  function resetConveyorState() {
    piReviewed = false;
    var btn = document.getElementById('btn-proceed-send');
    if ( btn ) { btn.disabled = true; btn.style.opacity = '0.45'; btn.style.cursor = 'not-allowed'; btn.title = 'Preview PI first (Step 2)'; }
    var badge = document.getElementById('pi-reviewed-badge');
    if ( badge ) badge.style.display = 'none';
  }

  function enableSendButton() {
    var btn = document.getElementById('btn-proceed-send');
    if ( btn ) { btn.disabled = false; btn.style.opacity = '1'; btn.style.cursor = 'pointer'; btn.title = 'Proceed to Compose & Send'; }
    var badge = document.getElementById('pi-reviewed-badge');
    if ( badge ) badge.style.display = 'inline-block';
  }

  // ── Step 1: Save Draft ─────────────────────────────────────────────────
  $('#btn-save-pi-draft').on('click', function () {
    if ( ! piId ) { iipNotice('Create PI first', 'error'); return; }
    $.post( SGCIIP.ajaxUrl, getPiSaveData(), function ( r ) {
      if ( r.success ) {
        iipNotice( '✅ PI saved! Total: ' + parseFloat( r.data.grand_total || 0 ).toFixed(2) );
        resetConveyorState(); // New save = re-review required
        if ( inqId ) loadPIList( inqId );
        fetchAllPI();
      } else {
        iipNotice( r.data, 'error' );
      }
    });
  });

  // ── Step 2: Preview & Review ────────────────────────────────────────────
  $('#btn-preview-review').on('click', function () {
    if ( ! piId ) { iipNotice( 'Save PI first (Step 1)', 'error' ); return; }
    var $btn = $( this );
    $btn.prop( 'disabled', true ).text( '⏳ Loading…' );

    // Auto-save first, then generate PDF, then open preview
    $.post( SGCIIP.ajaxUrl, getPiSaveData(), function () {
      $.post( SGCIIP.ajaxUrl, { action: 'sgc_iip_pi_generate_pdf', nonce: SGCIIP.nonce, pi_id: piId }, function ( r ) {
        $btn.prop( 'disabled', false ).text( '👁 2. Preview & Review' );
        if ( r.success && ( r.data.pdf_url || r.data.url ) ) {
          var pdfUrl = r.data.pdf_url || r.data.url;
          $( '#pi-preview-frame' ).attr( 'src', pdfUrl );
          openModal( 'modal-pi-preview' );
        } else {
          iipNotice( r.data || 'PDF generation failed', 'error' );
        }
      }).fail( function () {
        $btn.prop( 'disabled', false ).text( '👁 2. Preview & Review' );
        iipNotice( 'PDF generation failed — check server logs', 'error' );
      });
    }).fail( function () {
      $btn.prop( 'disabled', false ).text( '👁 2. Preview & Review' );
      iipNotice( 'Save failed — check server', 'error' );
    });
  });

  // Mark as Reviewed — inside preview modal footer
  $( '#btn-mark-reviewed' ).on( 'click', function () {
    piReviewed = true;
    enableSendButton();
    closeModal( 'modal-pi-preview' );
    iipNotice( '✅ PI reviewed! Click "3. Send to Buyer" to compose your email.', 'success' );
  });

  // ── Step 3: Proceed to Send — open Compose tab pre-filled ──────────────
  $( '#btn-proceed-send' ).on( 'click', function () {
    if ( ! piReviewed ) {
      iipNotice( 'Please preview the PI first (Step 2)', 'error' );
      return;
    }
    if ( ! piId ) { iipNotice( 'PI ID missing', 'error' ); return; }

    // Collect data for compose tab pre-fill
    var buyerEmail   = $.trim( $( '#pi-buyer-email' ).val() )  || '';
    var buyerName    = $.trim( $( '#pi-buyer-name' ).val() )   || '';
    var piNumber     = $.trim( $( '#pi-number-display' ).text() ).replace( /\s.*/, '' ) || '';
    var revision     = parseInt( $( '#pi-status-badge' ).text().replace( /[^0-9]/g, '' ) ) || 1;
    var isRevised    = revision > 1;
    var subjectPrefix= isRevised ? 'REVISED ' : '';
    var subject      = subjectPrefix + 'Proforma Invoice ' + piNumber + ( isRevised ? ' Rev.' + revision : '' ) + ' — SGC LABS® Laboratory Glassware';

    if ( ! buyerEmail ) {
      iipNotice( 'Buyer email is missing on this PI', 'error' );
      return;
    }

    // Build compose URL with pre-fill params
    var params = new URLSearchParams({
      page        : 'sgc-iip',
      tab         : 'compose',
      pre_pi_id   : piId,
      pre_email   : buyerEmail,
      pre_name    : buyerName,
      pre_subject : subject,
      pre_revised : isRevised ? '1' : '0',
      pre_pi_num  : piNumber,
      pre_revision: revision
    });
    window.location.href = SGCIIP.ajaxUrl.replace( 'admin-ajax.php', '' ) + 'admin.php?' + params.toString();
  });

  // ── Cancel PI — global deletePI updated ────────────────────────────────
  // (overrides the global deletePI below for sent/accepted PIs)
  var _cancelPiId   = 0;
  var _cancelPiBtn  = null;

  $( '#btn-confirm-cancel-pi' ).on( 'click', function () {
    var reason = $.trim( $( '#pi-cancel-reason' ).val() );
    if ( ! reason ) {
      $( '#pi-cancel-reason-err' ).show();
      $( '#pi-cancel-reason' ).focus();
      return;
    }
    $( '#pi-cancel-reason-err' ).hide();
    var $btn = $( this );
    $btn.prop( 'disabled', true ).text( '⏳ Cancelling…' );

    $.post( SGCIIP.ajaxUrl, {
      action : 'sgc_iip_pi_delete',
      nonce  : SGCIIP.nonce,
      pi_id  : _cancelPiId,
      reason : reason
    }, function ( r ) {
      closeModal( 'modal-cancel-pi' );
      $( '#pi-cancel-reason' ).val( '' );
      $btn.prop( 'disabled', false ).text( 'Cancel PI & Notify Buyer' );
      if ( r.success ) {
        var msg = r.data && r.data.email_sent
            ? '✅ PI cancelled. Buyer notified by email.'
            : '✅ PI cancelled.';
        iipNotice( msg, 'success' );
        if ( typeof fetchAllPI === 'function' ) fetchAllPI();
        if ( typeof inqId !== 'undefined' && inqId && typeof loadPIList === 'function' ) loadPIList( inqId );
        if ( _cancelPiBtn ) jQuery( _cancelPiBtn ).html( '&#128465;' ).prop( 'disabled', false );
      } else {
        iipNotice( r.data || 'Cancel failed', 'error' );
        if ( _cancelPiBtn ) jQuery( _cancelPiBtn ).html( '&#128465;' ).prop( 'disabled', false );
      }
    }).fail( function () {
      closeModal( 'modal-cancel-pi' );
      $btn.prop( 'disabled', false ).text( 'Cancel PI & Notify Buyer' );
      iipNotice( 'Network error — try again', 'error' );
      if ( _cancelPiBtn ) jQuery( _cancelPiBtn ).html( '&#128465;' ).prop( 'disabled', false );
    });
  });

  // ── Per-PI QR preview ──
  jQuery('#btn-pi-gen-qr').on('click',function(){
    var upi=jQuery('#pi-upi-id').val().trim();
    var name=jQuery('#pi-upi-name').val().trim()||'SGC Labs';
    if(!upi){iipNotice('UPI ID daalo pehle','error');return;}
    var url='https://quickchart.io/qr?text='+encodeURIComponent('upi://pay?pa='+upi+'&pn='+name)+'&width=80&height=80&format=png';
    jQuery('#pi-upi-qr-img').attr('src',url);
    jQuery('#pi-upi-qr-preview').show();
  });

  jQuery('#pi-upi-qr-url').on('input',function(){
    var url=jQuery(this).val().trim();
    if(url){ jQuery('#pi-upi-qr-img').attr('src',url); jQuery('#pi-upi-qr-preview').show(); }
    else jQuery('#pi-upi-qr-preview').hide();
  });

  // ── Media Library for per-PI QR ──
  var piQrFrame;
  jQuery('#btn-pi-upload-qr').on('click',function(e){
    e.preventDefault();
    if(piQrFrame){piQrFrame.open();return;}
    piQrFrame=wp.media({title:'Select QR Code Image',button:{text:'Use this QR'},multiple:false,library:{type:['image']}});
    piQrFrame.on('select',function(){
      var att=piQrFrame.state().get('selection').first().toJSON();
      jQuery('#pi-upi-qr-url').val(att.url);
      jQuery('#pi-upi-qr-img').attr('src',att.url);
      jQuery('#pi-upi-qr-preview').show();
    });
    piQrFrame.open();
  });
  $('#btn-send-dispatch').on('click',function(){
    if(!piId){iipNotice('Save PI first','error');return;}
    var partner = $('#pi-freight-partner').val().trim();
    var tracking = $('#pi-tracking-no').val().trim();
    if(!partner){iipNotice('Freight partner name daalo','error');$('#pi-freight-partner').focus();return;}
    if(!tracking){iipNotice('Tracking number daalo','error');$('#pi-tracking-no').focus();return;}
    var btn=$(this);
    btn.prop('disabled',true).text('⏳ Sending...');
    $.post(SGCIIP.ajaxUrl,{
      action:'sgc_iip_send_dispatch_email',
      nonce:SGCIIP.nonce,
      pi_id:piId,
      freight_partner:partner,
      tracking_no:tracking,
      dispatch_date:$('#pi-dispatch-date').val(),
      tracking_url:$('#pi-tracking-url').val().trim(),
    },function(r){
      btn.prop('disabled',false).text('📦 Dispatch & Notify Buyer');
      if(r.success){
        $('#dispatch-notice').html('✅ '+r.data).show();
        iipNotice('✅ Dispatch email sent to buyer!','success');
      } else {
        iipNotice('Error: '+r.data,'error');
      }
    });
  });

  // AI Price
  $('#btn-ai-price').on('click',function(){
    var prod=$('#ai-prod').val(); var qty=$('#ai-qty').val();
    if(!prod){iipNotice('Enter product name','error');return;}
    $(this).text('⏳…').prop('disabled',true);
    var country=currentInquiry?currentInquiry.country:'';
    $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_ai_price_suggest',nonce:SGCIIP.nonce,product:prod,qty:qty,country:country,buyer_type:'bulk'},function(r){
      $('#btn-ai-price').text('💡 Get AI Price').prop('disabled',false);
      if(r.success&&r.data){
        var d=r.data;
        var html='<div style="line-height:1.8">';
        if(d.suggested_unit_usd) html+='<div>Unit Price: <strong>$'+parseFloat(d.suggested_unit_usd).toFixed(2)+'</strong> (Rs.'+parseFloat(d.suggested_unit_inr||d.suggested_unit_usd*usdRate).toFixed(0)+')</div>';
        if(d.packaging_per_unit_usd) html+='<div>Pkg/Unit: <strong>$'+parseFloat(d.packaging_per_unit_usd).toFixed(2)+'</strong></div>';
        if(d.reasoning) html+='<div style="color:#888;font-size:10px;margin-top:4px">'+escH(d.reasoning)+'</div>';
        if(d.suggested_unit_usd) html+='<button class="iip-btn sm primary" style="margin-top:6px" onclick="addLineRow({product_name:\''+escH(prod)+'\',unit_price:'+parseFloat(d.suggested_unit_usd*usdRate).toFixed(0)+',packaging_per_unit:'+parseFloat(d.packaging_per_unit_usd*usdRate).toFixed(0)+',qty:1})">+ Add Row</button>';
        html+='</div>';
        $('#ai-price-result').html(html).show();
      }
    });
  });

  // WC Product Search
  $('#btn-wc-search').on('click',function(){
    var q=$('#wc-prod-search').val();
    if(!q) return;
    $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_wc_product_search',nonce:SGCIIP.nonce,query:q},function(r){
      if(!r.success){$('#wc-prod-results').html('<div style="color:#c62828">'+escH(r.data)+'</div>');return;}
      var html=(r.data||[]).map(function(p){
        return '<div style="border:1px solid #e0e0e0;border-radius:4px;padding:6px 8px;margin-bottom:4px;font-size:11px;display:flex;justify-content:space-between;align-items:center">'
          +'<div><strong>'+escH(p.name)+'</strong><br><span style="color:#888">SKU: '+escH(p.sku||'—')+'</span></div>'
          +'<div><span style="font-weight:700;color:#2e7d32">Rs.'+parseFloat(p.price||0).toFixed(0)+'</span>'
          +'<button class="iip-btn sm" style="margin-left:6px" onclick="addLineRow({product_name:\''+escH(p.name)+'\',sku:\''+escH(p.sku||'')+'\',unit_price:'+parseFloat(p.price||0).toFixed(0)+',qty:1})">+ Add</button></div>'
          +'</div>';
      }).join('');
      $('#wc-prod-results').html(html||'<div style="color:#aaa">No products found.</div>');
    });
  });

  function loadWCModal(){
    $('#wc-modal-results').html('<div class="iip-spin"></div> Loading WC products…');
    $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_wc_product_search',nonce:SGCIIP.nonce,query:''},function(r){
      var html=(r.data||[]).slice(0,20).map(function(p){
        return '<div style="border:1px solid #e0e0e0;border-radius:4px;padding:6px 8px;margin-bottom:4px;font-size:11px;display:flex;justify-content:space-between;align-items:center">'
          +'<div><strong>'+escH(p.name)+'</strong><br><span style="color:#888">SKU: '+escH(p.sku||'—')+'  |  Rs.'+parseFloat(p.price||0).toFixed(0)+'</span></div>'
          +'<button class="iip-btn sm primary" onclick="addLineRow({product_name:\''+escH(p.name)+'\',sku:\''+escH(p.sku||'')+'\',unit_price:'+parseFloat(p.price||0).toFixed(0)+',qty:1});closeModal(\'modal-wc-product\')">+ Add</button>'
          +'</div>';
      }).join('');
      $('#wc-modal-results').html(html||'<div style="color:#aaa;padding:20px;text-align:center">No WC products found.</div>');
    });
  }

  $('#wc-search-modal').on('input',function(){
    var q=$(this).val();
    if(q.length<2) return;
    $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_wc_product_search',nonce:SGCIIP.nonce,query:q},function(r){
      var html=(r.data||[]).map(function(p){
        return '<div style="border:1px solid #e0e0e0;border-radius:4px;padding:6px 8px;margin-bottom:4px;font-size:11px;display:flex;justify-content:space-between;align-items:center">'
          +'<div><strong>'+escH(p.name)+'</strong><br>Rs.'+parseFloat(p.price||0).toFixed(0)+' | SKU: '+escH(p.sku||'—')+'</div>'
          +'<button class="iip-btn sm primary" onclick="addLineRow({product_name:\''+escH(p.name)+'\',sku:\''+escH(p.sku||'')+'\',unit_price:'+parseFloat(p.price||0).toFixed(0)+',qty:1});closeModal(\'modal-wc-product\')">+ Add</button>'
          +'</div>';
      }).join('');
      $('#wc-modal-results').html(html||'No results');
    });
  });

  // ── INQUIRY SEARCH (replaces manual ID input) ─────────────────────────────
  var inqSearchTimer=null;
  $('#pi-inquiry-search').on('input',function(){
    var q=$(this).val().trim();
    clearTimeout(inqSearchTimer);
    if(q.length<2){$('#pi-inquiry-dropdown').hide();return;}
    inqSearchTimer=setTimeout(function(){
      $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_get_inquiries',nonce:SGCIIP.nonce,search:q,per_page:8},function(r){
        var list=r.data&&r.data.rows?r.data.rows:(Array.isArray(r.data)?r.data:[]);
        if(!list.length){$('#pi-inquiry-dropdown').html('<div style="padding:10px 14px;color:#aaa;font-size:12px">No inquiries found</div>').show();return;}
        var html=list.map(function(inq){
          return '<div class="pi-inq-drop-item" data-id="'+inq.id+'" style="padding:8px 14px;border-bottom:1px solid #f3f4f6;cursor:pointer;font-size:12px">'
            +'<div style="font-weight:700;color:#1a1a2e">#'+inq.id+' — '+escH(inq.name||'')+'</div>'
            +'<div style="font-size:11px;color:#6b7280">'+escH(inq.email||'')+' · '+escH(inq.product_name||'')+'</div>'
            +'</div>';
        }).join('');
        $('#pi-inquiry-dropdown').html(html).show();
      });
    },300);
  });

  $(document).on('click','.pi-inq-drop-item',function(){
    var id=$(this).data('id');
    var label=$(this).find('div:first').text();
    $('#pi-inquiry-id').val(id);
    $('#pi-inquiry-search').val(label);
    $('#pi-inquiry-dropdown').hide();
    loadInquiryById(id);
  });
  $(document).on('click',function(e){
    if(!$(e.target).closest('#pi-inquiry-search,#pi-inquiry-dropdown').length)
      $('#pi-inquiry-dropdown').hide();
  });

  function loadInquiryById(id){
    $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_get_inquiry',nonce:SGCIIP.nonce,id:id},function(r){
      if(!r.success){iipNotice('Inquiry not found','error');return;}
      inqId=id; currentInquiry=r.data;
      $('#pi-inquiry-id').val(id);
      $('#pi-inquiry-info').text('\u2713 '+escH(r.data.name));
      if(r.data.country) detectDomestic(r.data.country);
      $('#btn-create-pi,#btn-ai-draft-pi').show();
      loadPIList(id);
      allPiInqFilter=id; allPiPage=1; fetchAllPI();
    });
  }

  function loadPIList(id){
    $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_pi_list',nonce:SGCIIP.nonce,inquiry_id:id},function(pr){
      var list=pr.data||[];
      var html=list.length?list.map(function(pi){
        return '<div style="border:1px solid #e0e0e0;border-radius:6px;padding:8px 10px;margin-bottom:6px;font-size:11px">'
          +'<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">'
          +'<strong style="cursor:pointer;color:#1a1a2e" onclick="loadPIById('+pi.id+')">'+escH(pi.pi_number)+'</strong>'
          +'<span style="font-size:10px;background:#e0e7ff;color:#3730a3;padding:1px 6px;border-radius:8px">'+escH(pi.status)+'</span>'
          +'</div>'
          +'<div style="color:#6b7280;font-size:10px;margin-bottom:6px">'+parseFloat(pi.grand_total||0).toFixed(2)+' '+pi.currency+'</div>'
          +'<div style="display:flex;gap:4px">'
          +'<button class="iip-btn sm" style="font-size:10px" onclick="loadPIById('+pi.id+')">&#9998; Edit</button>'
          +'<button class="iip-btn sm" style="font-size:10px" onclick="genPDF('+pi.id+')">PDF</button>'
          +'<button class="iip-btn sm" style="font-size:10px;background:#fef2f2;border-color:#fecaca;color:#dc2626" onclick="deletePI('+pi.id+',this)">&#128465;</button>'
          +'</div>'
          +'</div>';
      }).join('') : '<div style="color:#aaa;font-size:12px;text-align:center;padding:10px">No PIs yet</div>';
      $('#pi-list-panel').html(html);
    });
  }

  // ── ALL PI TABLE ─────────────────────────────────────────────────────────
  var allPiPage=1, allPiInqFilter=0, allPiSearchTimer=null;

  function fetchAllPI(){
    var search=$('#all-pi-search').val()||'';
    var status=$('#all-pi-status').val()||'';
    $('#all-pi-tbody').html('<div style="padding:20px;text-align:center;color:#aaa;font-size:13px">Loading&#8230;</div>');
    $.post(SGCIIP.ajaxUrl,{
      action:'sgc_iip_list_all_pi',nonce:SGCIIP.nonce,
      page:allPiPage,per_page:15,
      search:search,status:status,
      inquiry_id:allPiInqFilter
    },function(r){
      var list=r.data&&r.data.rows?r.data.rows:(Array.isArray(r.data)?r.data:[]);
      var total=r.data&&r.data.total?r.data.total:list.length;
      if(!list.length){
        $('#all-pi-tbody').html('<div style="padding:20px;text-align:center;color:#aaa;font-size:13px">No proforma invoices found.</div>');
        $('#all-pi-pagination').html(''); return;
      }
      var html='<table style="width:100%;border-collapse:collapse;font-size:12px">'
        +'<thead><tr style="background:#f8fafc;border-bottom:2px solid #e5e7eb">'
        +'<th style="padding:8px 14px;text-align:left;color:#6b7280">Buyer</th>'
        +'<th style="padding:8px 14px;text-align:left;color:#6b7280">PI No</th>'
        +'<th style="padding:8px 14px;text-align:left;color:#6b7280">Amount</th>'
        +'<th style="padding:8px 14px;text-align:left;color:#6b7280">Status</th>'
        +'<th style="padding:8px 14px;text-align:left;color:#6b7280">Date</th>'
        +'<th style="padding:8px 14px;text-align:center;color:#6b7280">Actions</th>'
        +'</tr></thead><tbody>';
      list.forEach(function(pi){
        html+='<tr style="border-bottom:1px solid #f3f4f6">'
          +'<td style="padding:8px 14px"><div style="font-weight:600;color:#1f2937">'+escH(pi.buyer_name||'&#8212;')+'</div><div style="font-size:10px;color:#6b7280">Inq #'+pi.inquiry_id+'</div></td>'
          +'<td style="padding:8px 14px"><span style="font-weight:700;color:#1a1a2e;font-family:monospace">'+escH(pi.pi_number||'&#8212;')+'</span><br><span style="font-size:10px;color:#6b7280">Rev.'+pi.revision+'</span></td>'
          +'<td style="padding:8px 14px;font-weight:700;color:#059669">'+parseFloat(pi.grand_total||0).toFixed(2)+' '+(pi.currency||'USD')+'</td>'
          +'<td style="padding:8px 14px"><span style="background:#e0e7ff;color:#3730a3;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700">'+escH(pi.status||'draft')+'</span></td>'
          +'<td style="padding:8px 14px;color:#6b7280;font-size:11px">'+(pi.created_at||'').substr(0,10)+'</td>'
          +'<td style="padding:8px 14px;text-align:center">'
          +'<div style="display:flex;gap:3px;justify-content:center">'
          +'<button class="iip-btn sm" style="font-size:10px" onclick="loadPIById('+pi.id+')">&#9998; Edit</button>'
          +'<button class="iip-btn sm" style="font-size:10px" onclick="genPDF('+pi.id+')">PDF</button>'
          +'<button class="iip-btn sm" style="font-size:10px" onclick="sendPIEmail('+pi.id+')">&#9993;</button>'
          +'<button class="iip-btn sm" style="font-size:10px;background:#fef2f2;border-color:#fecaca;color:#dc2626" onclick="deletePI('+pi.id+',this)">&#128465;</button>'
          +'</div></td>'
          +'</tr>';
      });
      html+='</tbody></table>';
      $('#all-pi-tbody').html(html);
      var pages=Math.ceil(total/15);
      var pg='Showing '+list.length+' of '+total+' PIs';
      if(pages>1){
        if(allPiPage>1) pg+=' <button class="iip-btn sm" style="font-size:11px" onclick="allPiPage--;fetchAllPI()">← Prev</button>';
        pg+=' | Page '+allPiPage+'/'+pages;
        if(allPiPage<pages) pg+=' <button class="iip-btn sm" style="font-size:11px" onclick="allPiPage++;fetchAllPI()">Next →</button>';
      }
      $('#all-pi-pagination').html(pg);
    });
  }



  $('#all-pi-search').on('input',function(){
    clearTimeout(allPiSearchTimer);
    allPiSearchTimer=setTimeout(function(){allPiPage=1;fetchAllPI();},400);
  });
  $('#all-pi-status').on('change',function(){allPiPage=1;fetchAllPI();});
  $('#btn-reload-all-pi').on('click',fetchAllPI);

  // Load all PIs on page open
  fetchAllPI();

  // Auto-load from URL
  var urlInqId=parseInt('<?php echo esc_js($inquiry_id); ?>')||0;
  if(urlInqId){loadInquiryById(urlInqId);}

});

// ── Global functions (called via onclick attributes) ─────────────────────────
function deletePI(id, btn){
  // Fetch PI status first to decide flow
  jQuery(btn).text('...').prop('disabled',true);
  jQuery.post(SGCIIP.ajaxUrl,{action:'sgc_iip_pi_get',nonce:SGCIIP.nonce,pi_id:id},function(r){
    if(!r.success||!r.data){
      iipNotice(r.data||'Failed to load PI','error');
      jQuery(btn).html('&#128465;').prop('disabled',false);
      return;
    }
    var st=r.data.status||'draft';
    if(st==='sent'||st==='accepted'||st==='advance'){
      // PI already reached buyer — show cancel modal with reason field
      jQuery(btn).html('&#128465;').prop('disabled',false);
      window._cancelPiId  = id;
      window._cancelPiBtn = btn;
      jQuery('#pi-cancel-reason').val('');
      jQuery('#pi-cancel-reason-err').hide();
      openModal('modal-cancel-pi');
    } else {
      // Draft/expired/revised — simple delete, no email needed
      if(!confirm('Delete this PI permanently?')){
        jQuery(btn).html('&#128465;').prop('disabled',false);
        return;
      }
      jQuery.post(SGCIIP.ajaxUrl,{action:'sgc_iip_pi_delete',nonce:SGCIIP.nonce,pi_id:id,reason:''},function(r){
        if(r.success){
          iipNotice('PI deleted');
          if(typeof fetchAllPI==='function') fetchAllPI();
          if(typeof inqId!=='undefined'&&inqId&&typeof loadPIList==='function') loadPIList(inqId);
        }else{
          iipNotice(r.data||'Delete failed','error');
          jQuery(btn).html('&#128465;').prop('disabled',false);
        }
      }).fail(function(){
        iipNotice('Network error','error');
        jQuery(btn).html('&#128465;').prop('disabled',false);
      });
    }
  }).fail(function(){
    iipNotice('Network error','error');
    jQuery(btn).html('&#128465;').prop('disabled',false);
  });
}

function genPDF(id){
  iipNotice('⏳ Generating PDF…','info');
  jQuery.post(SGCIIP.ajaxUrl,{action:'sgc_iip_pi_generate_pdf',nonce:SGCIIP.nonce,pi_id:id},function(r){
    if(r.success&&(r.data.pdf_url||r.data.url)) window.open(r.data.pdf_url||r.data.url,'_blank');
    else iipNotice(r.data||'PDF generation failed','error');
  });
}

function sendPIEmail(id){
  if(!confirm('Send PI email to buyer?')) return;
  iipNotice('⏳ Sending email…','info');
  jQuery.post(SGCIIP.ajaxUrl,{action:'sgc_iip_send_pi_email',nonce:SGCIIP.nonce,pi_id:id},function(r){
    if(r.success) iipNotice('✅ Email sent to buyer!');
    else iipNotice(r.data||'Email send failed','error');
  });
}
</script>
</div><!-- /#iip-proforma-wrap -->
