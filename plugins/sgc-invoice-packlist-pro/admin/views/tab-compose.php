<?php
defined( 'ABSPATH' ) || exit;

// v1.8.0: Pre-fill from Proforma tab "Proceed to Send" (Option B conveyor)
$pre_pi_id   = isset( $_GET['pre_pi_id'] )   ? absint( $_GET['pre_pi_id'] )                                   : 0;
$pre_email   = isset( $_GET['pre_email'] )   ? sanitize_email( wp_unslash( $_GET['pre_email'] ) )              : '';
$pre_name    = isset( $_GET['pre_name'] )    ? sanitize_text_field( wp_unslash( $_GET['pre_name'] ) )          : '';
$pre_subject = isset( $_GET['pre_subject'] ) ? sanitize_text_field( wp_unslash( $_GET['pre_subject'] ) )       : '';
$pre_revised = isset( $_GET['pre_revised'] ) && $_GET['pre_revised'] === '1';
$pre_pi_num  = isset( $_GET['pre_pi_num'] )  ? sanitize_text_field( wp_unslash( $_GET['pre_pi_num'] ) )        : '';
$pre_revision= isset( $_GET['pre_revision'] )? absint( $_GET['pre_revision'] )                                 : 1;
$has_prefill = $pre_pi_id > 0 && is_email( $pre_email );
?>

<style>
/* ── Compose Layout ─────────────────────────────────────── */
.sgc-compose-wrap{display:flex;gap:20px;align-items:flex-start;min-height:600px}
.sgc-compose-left{flex:1;min-width:0;display:flex;flex-direction:column;gap:16px}
.sgc-compose-right{width:360px;flex-shrink:0;position:sticky;top:32px}

.sgc-section{background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:20px}
.sgc-section h3{margin:0 0 14px;font-size:14px;font-weight:700;color:#1a1a2e;display:flex;align-items:center;gap:6px;padding-bottom:10px;border-bottom:1px solid #f0f0f0}

.sgc-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.sgc-fg{display:flex;flex-direction:column;gap:4px}
.sgc-fg label{font-size:12px;font-weight:600;color:#555}

.sgc-raw-textarea{width:100%;min-height:120px;resize:vertical;font-family:inherit;font-size:13px;line-height:1.6;padding:10px 12px;border:1px solid #d0d0d0;border-radius:6px;box-sizing:border-box;color:#333}
.sgc-raw-textarea:focus{outline:none;border-color:#185fa5;box-shadow:0 0 0 2px rgba(24,95,165,.12)}

.sgc-body-editor{min-height:180px;padding:14px;border:1px solid #d0d0d0;border-radius:6px;font-size:13px;line-height:1.7;color:#333;background:#fafafa;cursor:text;outline:none}
.sgc-body-editor:focus{border-color:#185fa5;background:#fff;box-shadow:0 0 0 2px rgba(24,95,165,.1)}
.sgc-body-editor p{margin:0 0 10px}

/* Attach zone */
.sgc-attach-zone{border:2px dashed #d0d0d0;border-radius:8px;padding:20px;text-align:center;cursor:pointer;transition:all .2s;background:#fafafa}
.sgc-attach-zone:hover,.sgc-attach-zone.drag-over{border-color:#185fa5;background:#f0f6ff}
.sgc-attach-item{display:flex;align-items:center;gap:10px;padding:8px 12px;background:#f8f9fa;border:1px solid #e0e0e0;border-radius:6px;margin-top:8px;font-size:12px}
.sgc-attach-item .attach-name{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#333;font-weight:500}
.sgc-attach-item .attach-size{color:#888;flex-shrink:0}
.sgc-attach-item .attach-rm{background:none;border:none;color:#e53935;cursor:pointer;font-size:16px;line-height:1;padding:0 2px;flex-shrink:0}
.sgc-attach-total{font-size:11px;color:#888;margin-top:6px;text-align:right}

/* Tone pills */
.sgc-tone-wrap{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}
.sgc-tone-pill{padding:5px 12px;border:1.5px solid #d0d0d0;border-radius:20px;font-size:12px;cursor:pointer;background:#fff;color:#555;transition:all .15s;user-select:none}
.sgc-tone-pill.active{border-color:#185fa5;background:#185fa5;color:#fff;font-weight:600}

/* AI status */
.sgc-ai-bar{display:flex;align-items:center;gap:10px;margin-top:10px;flex-wrap:wrap}
#cmp-ai-msg{font-size:12px;color:#888;flex:1}
.sgc-spin{display:inline-block;width:14px;height:14px;border:2px solid #e0e0e0;border-top-color:#185fa5;border-radius:50%;animation:spin .7s linear infinite;flex-shrink:0}
@keyframes spin{to{transform:rotate(360deg)}}

/* Preview panel */
.sgc-preview-panel{background:#fff;border:1px solid #e0e0e0;border-radius:10px;overflow:hidden}
.sgc-preview-hdr{background:#1a1a2e;color:#fff;padding:12px 16px;font-size:13px;font-weight:600;display:flex;justify-content:space-between;align-items:center}
.sgc-preview-hdr span.badge{font-size:10px;background:#2e7d32;padding:2px 8px;border-radius:10px;font-weight:400}
#sgc-preview-body{padding:0;overflow-y:auto;max-height:520px;font-size:12px;line-height:1.6}
.sgc-preview-empty{padding:40px 20px;text-align:center;color:#ccc}
.sgc-preview-empty .icon{font-size:40px;margin-bottom:10px}

/* Subject preview */
.sgc-meta-bar{padding:10px 14px;background:#f8f9fa;border-bottom:1px solid #e0e0e0;font-size:11px;color:#666}
.sgc-meta-bar strong{color:#333}

/* Send footer */
.sgc-send-footer{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.sgc-attach-badge{font-size:12px;color:#555;background:#f0f0f0;padding:4px 10px;border-radius:12px}

/* Char counter */
.sgc-char-count{font-size:11px;color:#aaa;text-align:right;margin-top:4px}

/* Status badge in send section */
#cmp-send-status{font-size:13px;font-weight:500}
.status-ok{color:#2e7d32}
.status-err{color:#c62828}

@media(max-width:900px){
  .sgc-compose-wrap{flex-direction:column}
  .sgc-compose-right{width:100%;position:static}
  .sgc-grid-2{grid-template-columns:1fr}
}
</style>

<?php if ( $has_prefill ) : ?>
<div id="sgc-prefill-banner" style="background:#e0f2fe;border:1px solid #0284c7;border-radius:8px;padding:12px 18px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
  <div style="font-size:13px;color:#0c4a6e">
    <strong>📋 Pre-filled from PI <?php echo esc_html( $pre_pi_num ); ?><?php echo $pre_revised ? ' (REVISED — Rev.' . $pre_revision . ')' : ''; ?></strong>
    &nbsp;— To: <strong><?php echo esc_html( $pre_name ?: $pre_email ); ?></strong>
    <?php if ( $pre_revised ) : ?>
    &nbsp;<span style="background:#dc2626;color:#fff;font-size:10px;padding:1px 7px;border-radius:8px">REVISED</span>
    <?php endif; ?>
  </div>
  <a href="<?php echo esc_url( admin_url('admin.php?page=sgc-iip&tab=proforma') ); ?>"
     style="font-size:11px;color:#0369a1;text-decoration:none">← Back to PI</a>
</div>
<?php endif; ?>

<div class="sgc-compose-wrap">

  <!-- ════════════════════════════════ LEFT ══════════════════════════════ -->
  <div class="sgc-compose-left">

    <!-- 1. Recipient -->
    <div class="sgc-section">
      <h3>📬 Recipient Details</h3>
      <div class="sgc-grid-2">
        <div class="sgc-fg">
          <label>Client Name</label>
          <input type="text" id="cmp-name" class="iip-input" placeholder="Mr. John Smith / ABC Chemicals Ltd.">
        </div>
        <div class="sgc-fg">
          <label>To Email *</label>
          <input type="email" id="cmp-email" class="iip-input" placeholder="buyer@company.com">
        </div>
        <div class="sgc-fg">
          <label>CC <span style="font-weight:400;color:#aaa;font-size:11px">(Carbon Copy — visible to all)</span></label>
          <input type="text" id="cmp-cc" class="iip-input" placeholder="cc1@company.com, cc2@company.com">
        </div>
        <div class="sgc-fg">
          <label>BCC <span style="font-weight:400;color:#aaa;font-size:11px">(Blind CC — hidden from others)</span></label>
          <input type="text" id="cmp-bcc" class="iip-input" placeholder="<?php echo esc_attr(get_option('sgc_iip_company_email','info@labsglass.com')); ?>">
        </div>
      </div>
      <div class="sgc-fg" style="margin-top:10px">
        <label>Subject *</label>
        <input type="text" id="cmp-subject" class="iip-input" placeholder="e.g. Laboratory Glassware Price List — SGC LABS® | Catalogue Request">
      </div>
    </div>

    <!-- 1.5 Template Selector -->
    <div class="sgc-section" id="template-section">
      <h3>📋 Quick Templates <span style="font-size:11px;color:#888;font-weight:400">— ek click mein ready-made email</span></h3>

      <div id="template-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:8px;margin-bottom:12px">
        <div style="text-align:center;color:#aaa;font-size:12px;padding:10px">⏳ Loading templates...</div>
      </div>

      <!-- Template fill form (hidden until template selected) -->
      <div id="template-fill-form" style="display:none;background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:14px;margin-top:10px">
        <div style="font-weight:700;font-size:13px;margin-bottom:10px;color:#1a1a2e" id="tpl-selected-name"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div>
            <label style="font-size:11px;font-weight:600;color:#555">Buyer Name *</label>
            <input type="text" id="tpl-buyer-name" class="iip-input" placeholder="e.g. Yamam Alkoshab" style="width:100%">
          </div>
          <div>
            <label style="font-size:11px;font-weight:600;color:#555">Country / City</label>
            <input type="text" id="tpl-buyer-location" class="iip-input" placeholder="e.g. Yemen / Mumbai" style="width:100%">
          </div>
          <div>
            <label style="font-size:11px;font-weight:600;color:#555">Currency</label>
            <select id="tpl-currency" class="iip-select" style="width:100%">
              <option value="USD">USD — US Dollar</option>
              <option value="EUR">EUR — Euro</option>
              <option value="GBP">GBP — British Pound</option>
              <option value="INR">INR — Indian Rupee</option>
            </select>
          </div>
          <div>
            <label style="font-size:11px;font-weight:600;color:#555">Order Value (for payment terms)</label>
            <input type="number" id="tpl-amount" class="iip-input" placeholder="0.00" step="0.01" style="width:100%" oninput="updatePaymentTermsPreview()">
          </div>
        </div>

        <!-- Payment terms preview -->
        <div id="payment-terms-preview" style="margin-top:8px;font-size:11px;background:#fffbeb;border:1px solid #fde68a;border-radius:5px;padding:6px 10px;color:#92400e">
          💰 Payment terms: Enter order value to auto-calculate
        </div>

        <!-- Items table -->
        <div style="margin-top:12px">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
            <label style="font-size:11px;font-weight:600;color:#555">Items / Products</label>
            <button type="button" class="iip-btn sm" onclick="addTemplateItem()" style="font-size:10px">+ Add Item</button>
          </div>
          <table style="width:100%;font-size:11px;border-collapse:collapse">
            <thead>
              <tr style="background:#f3f4f6">
                <th style="padding:5px 8px;text-align:left;font-weight:600;color:#555">Product Name</th>
                <th style="padding:5px 8px;text-align:center;width:70px">Qty</th>
                <th style="padding:5px 8px;text-align:center;width:60px">Unit</th>
                <th style="padding:5px 8px;text-align:center;width:90px">Rate</th>
                <th style="width:28px"></th>
              </tr>
            </thead>
            <tbody id="tpl-items-body">
              <tr id="tpl-item-0">
                <td style="padding:3px"><input type="text" class="iip-input tpl-item-name" placeholder="Product name" style="width:100%;font-size:11px"></td>
                <td style="padding:3px"><input type="number" class="iip-input tpl-item-qty" value="1" min="1" style="width:100%;font-size:11px;text-align:center"></td>
                <td style="padding:3px"><input type="text" class="iip-input tpl-item-unit" value="pcs" style="width:100%;font-size:11px;text-align:center"></td>
                <td style="padding:3px"><input type="number" class="iip-input tpl-item-rate" placeholder="0.00" step="0.01" style="width:100%;font-size:11px;text-align:right" oninput="calcTemplateTotal()"></td>
                <td style="padding:3px;text-align:center"><button onclick="removeTemplateItem(0)" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:14px">&#10005;</button></td>
              </tr>
            </tbody>
          </table>
          <div id="tpl-total-display" style="text-align:right;font-size:12px;color:#059669;font-weight:700;margin-top:4px"></div>
        </div>

        <div style="display:flex;gap:8px;margin-top:12px">
          <button type="button" class="iip-btn primary" onclick="applyTemplate()" style="flex:1">🤖 AI Draft from Template</button>
          <button type="button" class="iip-btn" onclick="document.getElementById('template-fill-form').style.display='none'">Cancel</button>
        </div>
      </div>
    </div>

    <!-- 2. Raw Message + AI -->
    <div class="sgc-section">
      <h3>✍ Your Message <span style="font-size:11px;color:#888;font-weight:400">— paste rough text, AI will polish it</span></h3>

      <!-- Mode selector -->
      <div style="display:flex;gap:6px;margin-bottom:8px;flex-wrap:wrap">
        <button type="button" class="iip-btn sm active" id="mode-general" onclick="setMode('general')" style="font-size:11px">💬 General Message</button>
        <button type="button" class="iip-btn sm" id="mode-tubing" onclick="setMode('tubing')" style="font-size:11px">⊞ Tubing / Price Data</button>
        <button type="button" class="iip-btn sm" id="mode-quotation" onclick="setMode('quotation')" style="font-size:11px">📋 Quotation Reply</button>
        <button type="button" class="iip-btn sm" id="mode-followup" onclick="setMode('followup')" style="font-size:11px">🔄 Follow-up</button>
      </div>
      <div id="mode-hint" style="font-size:11px;color:#888;background:#f8fafc;border:1px solid #e5e7eb;border-radius:5px;padding:6px 10px;margin-bottom:8px">
        💡 Koi bhi text paste karo — Hindi, English, mixed — AI samajh lega
      </div>
      <textarea id="cmp-raw" class="sgc-raw-textarea" rows="7"
        placeholder="e.g. hi john bhai beaker price list manga hai 50ml se 2000ml tak USD FOB price chahiye export ke liye, borosilicate 3.3 quality, good packing, delivery 30 days..."></textarea>
      <div class="sgc-char-count"><span id="raw-chars">0</span> chars</div>

      <div style="margin-top:12px">
        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:6px">Email Tone</label>
        <div class="sgc-tone-wrap">
          <span class="sgc-tone-pill active" data-tone="professional">💼 Professional</span>
          <span class="sgc-tone-pill" data-tone="friendly">😊 Friendly</span>
          <span class="sgc-tone-pill" data-tone="formal">🎩 Formal</span>
          <span class="sgc-tone-pill" data-tone="followup">🔄 Follow-up</span>
          <span class="sgc-tone-pill" data-tone="quotation">📋 Quotation Reply</span>
        </div>
      </div>

      <div class="sgc-ai-bar">
        <button id="btn-ai-polish" class="iip-btn primary" style="min-width:140px">✨ AI Polish</button>
        <div id="cmp-ai-spinner" style="display:none"><span class="sgc-spin"></span></div>
        <span id="cmp-ai-msg"></span>
      </div>
    </div>

    <!-- 3. Polished Body (hidden until AI runs) -->
    <div class="sgc-section" id="cmp-polished-section" style="display:none">
      <h3>✉ Email Body
        <span style="font-size:11px;color:#888;font-weight:400;margin-left:4px">— click to edit directly</span>
        <button id="btn-repolish" class="iip-btn sm" style="margin-left:auto;font-size:11px">↺ Re-Polish</button>
      </h3>
      <!-- Format Toolbar -->
      <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px;padding-bottom:8px;border-bottom:1px solid #f0f0f0">
        <button type="button" class="iip-btn sm" onclick="fmtCmd('bold')" title="Bold"><b>B</b></button>
        <button type="button" class="iip-btn sm" onclick="fmtCmd('italic')" title="Italic"><i>I</i></button>
        <button type="button" class="iip-btn sm" onclick="fmtCmd('underline')" title="Underline"><u>U</u></button>
        <span style="width:1px;background:#e0e0e0;margin:0 2px"></span>
        <button type="button" class="iip-btn sm" onclick="insertBulletList()" title="Bullet List">• List</button>
        <button type="button" class="iip-btn sm" onclick="showTableInsert()" title="Insert Table">⊞ Table</button>
        <button type="button" class="iip-btn sm" onclick="insertDivider()" title="Divider">— Line</button>
        <span style="width:1px;background:#e0e0e0;margin:0 2px"></span>
        <button type="button" class="iip-btn sm" onclick="insertBoilerplate('greeting')" title="Add greeting">👋 Greeting</button>
        <button type="button" class="iip-btn sm" onclick="insertBoilerplate('signature')" title="Add signature">✍ Sign</button>
        <span style="margin-left:auto;display:flex;gap:6px">
          <button type="button" class="iip-btn sm" onclick="clearBody()" style="color:#dc2626">🗑 Clear</button>
          <button type="button" class="iip-btn sm" onclick="showWAPreview()" style="background:#25d366;color:#fff;border-color:#25d366">📱 WA Preview</button>
        </span>
      </div>

      <!-- Table Insert Panel (hidden) -->
      <div id="table-insert-panel" style="display:none;background:#f8fafc;border:1px solid #e0e0e0;border-radius:6px;padding:12px;margin-bottom:10px">
        <div style="font-size:12px;font-weight:600;margin-bottom:8px">⊞ Insert Table</div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <div>
            <label style="font-size:11px;color:#666">Columns:</label>
            <input type="number" id="tbl-cols" value="3" min="1" max="8" style="width:50px;border:1px solid #d0d0d0;border-radius:4px;padding:3px;font-size:12px">
          </div>
          <div>
            <label style="font-size:11px;color:#666">Rows:</label>
            <input type="number" id="tbl-rows" value="4" min="1" max="20" style="width:50px;border:1px solid #d0d0d0;border-radius:4px;padding:3px;font-size:12px">
          </div>
          <div>
            <label style="font-size:11px;color:#666">Headers (comma separated):</label>
            <input type="text" id="tbl-headers" value="Specification,Size,Availability" style="width:220px;border:1px solid #d0d0d0;border-radius:4px;padding:3px;font-size:12px" placeholder="Col1,Col2,Col3">
          </div>
          <button type="button" class="iip-btn sm primary" onclick="insertTable()">Insert</button>
          <button type="button" class="iip-btn sm" onclick="document.getElementById('table-insert-panel').style.display='none'">Cancel</button>
        </div>
        <!-- Quick templates -->
        <div style="margin-top:8px;font-size:11px;color:#888">Quick templates:</div>
        <div style="display:flex;gap:6px;margin-top:4px;flex-wrap:wrap">
          <button type="button" class="iip-btn sm" style="font-size:10px" onclick="quickTable('glass-tube')">Glass Tube Sizes</button>
          <button type="button" class="iip-btn sm" style="font-size:10px" onclick="quickTable('price-list')">Price List</button>
          <button type="button" class="iip-btn sm" style="font-size:10px" onclick="quickTable('product-list')">Product List</button>
          <button type="button" class="iip-btn sm" style="font-size:10px" onclick="quickTable('availability')">Availability</button>
        </div>
      </div>

      <div id="cmp-body" class="sgc-body-editor" contenteditable="true" spellcheck="true"></div>
    </div>

    <!-- 4. Attachments -->
    <div class="sgc-section">
      <h3>📎 Attachments <span style="font-size:11px;color:#888;font-weight:400">— PDF, Excel, Images, ZIP &nbsp;|&nbsp; Max 100 MB total</span></h3>

      <div class="sgc-attach-zone" id="attach-zone">
        <input type="file" id="cmp-files" multiple
          accept=".pdf,.xlsx,.xls,.doc,.docx,.jpg,.jpeg,.png,.gif,.zip,.rar,.csv"
          style="display:none" onchange="handleFileSelect(this.files)">
        <div style="font-size:28px;margin-bottom:6px">📁</div>
        <div style="font-size:13px;color:#555;font-weight:500">Click to browse files</div>
        <div style="font-size:11px;color:#aaa;margin-top:4px">or drag & drop here</div>
        <button type="button" class="iip-btn sm" style="margin-top:10px"
          onclick="document.getElementById('cmp-files').click()">Browse Files</button>
      </div>

      <div id="attach-list"></div>
      <div class="sgc-attach-total" id="attach-total" style="display:none"></div>
    </div>

    <!-- 5. Send Footer -->
    <div class="sgc-section">
      <!-- CC / BCC row -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">
        <div>
          <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px">CC <span style="font-weight:400;color:#aaa">(optional)</span></label>
          <input type="text" id="cmp-cc" class="iip-input" placeholder="cc@email.com, another@email.com" style="width:100%;box-sizing:border-box">
        </div>
        <div>
          <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px">BCC <span style="font-weight:400;color:#aaa">(your copy auto-added)</span></label>
          <input type="text" id="cmp-bcc-extra" class="iip-input" placeholder="extra@bcc.com" style="width:100%;box-sizing:border-box">
          <div style="font-size:10px;color:#888;margin-top:3px">📋 Auto BCC: <strong><?php echo esc_html( get_option('sgc_iip_company_email','info@labsglass.com') ); ?></strong></div>
        </div>
      </div>
      <div class="sgc-send-footer">
        <div>
          <span id="cmp-send-status"></span>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <button id="btn-fullpreview" class="iip-btn" style="min-width:110px">👁 Preview</button>
          <button id="btn-save-draft" class="iip-btn" style="min-width:110px" onclick="saveDraft()">💾 Save Draft</button>
          <button id="btn-drive-save" class="iip-btn" style="min-width:130px;background:#4285f4;color:#fff;border-color:#4285f4" onclick="saveToDrive()">
            📁 Google Drive
          </button>
          <button id="btn-send-compose" class="iip-btn success" style="min-width:140px;font-size:14px;padding:11px 22px">
            📤 Send Now
          </button>
        </div>
      </div>
      <!-- Drive status -->
      <div id="drive-status" style="display:none;margin-top:8px;font-size:12px;padding:8px 12px;background:#e8f5e9;border-radius:6px;color:#2e7d32"></div>
    </div>

  </div><!-- /left -->

  <!-- ════════════════════════════════ RIGHT ═════════════════════════════ -->
  <div class="sgc-compose-right">
    <div class="sgc-preview-panel">
      <div class="sgc-preview-hdr">
        <span>📧 Live Preview</span>
        <span class="badge">Updates after AI Polish</span>
      </div>
      <div id="sgc-preview-body">
        <div class="sgc-preview-empty">
          <div class="icon">✉</div>
          <div style="font-size:13px">Compose your message<br>and click <strong>AI Polish</strong></div>
        </div>
      </div>
    </div>

    <!-- Quick tips -->
    <div style="margin-top:14px;padding:14px;background:#f8f9fa;border-radius:8px;font-size:12px;color:#666;line-height:1.7">
      <strong style="color:#333;display:block;margin-bottom:6px">💡 Tips</strong>
      • Hindi mein paste karo — AI English mein convert karega<br>
      • Tone change karke dubara polish karo<br>
      • PDF catalogue directly attach karo<br>
      • BCC copy automatically aayegi aapke email pe<br>
      • Body directly click karke edit bhi kar sakte ho
    </div>
  </div>

<!-- WhatsApp Preview Modal -->
<div id="wa-preview-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;z-index:99999;background:rgba(0,0,0,.6);align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;width:380px;max-height:85vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 8px 40px rgba(0,0,0,.3)">
    <div style="background:#075e54;color:#fff;padding:14px 18px;display:flex;align-items:center;justify-content:space-between">
      <div style="display:flex;align-items:center;gap:10px">
        <div style="width:36px;height:36px;border-radius:50%;background:#25d366;display:flex;align-items:center;justify-content:center;font-weight:700">S</div>
        <div><div style="font-weight:600;font-size:14px">SGC LABS®</div><div style="font-size:11px;color:#a8d5cd">online</div></div>
      </div>
      <button onclick="document.getElementById('wa-preview-modal').style.display='none'" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;line-height:1">&#10005;</button>
    </div>
    <div style="flex:1;overflow-y:auto;padding:16px;background:#e5ddd5">
      <div id="wa-bubble" style="background:#fff;border-radius:0 10px 10px 10px;padding:10px 14px;max-width:92%;font-size:13px;line-height:1.7;box-shadow:0 1px 3px rgba(0,0,0,.15);white-space:pre-wrap;word-break:break-word"></div>
      <div style="font-size:10px;color:#777;margin-top:4px;text-align:right">now &#10003;&#10003;</div>
    </div>
    <div style="padding:12px 18px;border-top:1px solid #f0f0f0;display:flex;gap:8px">
      <button class="iip-btn sm" onclick="copyWAText()" style="flex:1">&#128203; Copy</button>
      <button onclick="document.getElementById('wa-preview-modal').style.display='none'" class="iip-btn sm" style="flex:1">Close</button>
    </div>
  </div>
</div>

</div><!-- /compose-wrap -->

<!-- ══════════════════════ FULL PREVIEW MODAL ══════════════════════════ -->
<div class="iip-modal" id="modal-fullpreview" style="display:none">
  <div class="iip-modal-inner" style="max-width:700px">
    <div class="iip-modal-hdr">
      <h3>📧 Full Email Preview</h3>
      <button onclick="closeModal('modal-fullpreview')">✕</button>
    </div>
    <div class="iip-modal-body" style="padding:0;background:#f0f2f5">
      <div style="padding:10px 16px;background:#fff;border-bottom:1px solid #e0e0e0;font-size:12px;color:#666">
        <strong>To:</strong> <span id="prev-to">—</span> &nbsp;|&nbsp;
        <strong>Subject:</strong> <span id="prev-subject">—</span>
      </div>
      <iframe id="preview-iframe" style="width:100%;height:560px;border:none;display:block"></iframe>
    </div>
    <div class="iip-modal-ftr" style="gap:10px">
      <button class="iip-btn" onclick="closeModal('modal-fullpreview')">✕ Close</button>
      <button class="iip-btn success" onclick="closeModal('modal-fullpreview');doSendEmail()">📤 Send This Email</button>
    </div>
  </div>
</div>

<script>
(function($){
  'use strict';

  // ── State ─────────────────────────────────────────────────────────────
  var polishedHTML = '';
  var attachedFiles = [];        // Array of File objects

  // ── v1.8.0: Pre-fill from PI conveyor (Option B) ─────────────────────
  var preFillData = {
    hasPrefill : <?php echo $has_prefill ? 'true' : 'false'; ?>,
    piId       : <?php echo (int) $pre_pi_id; ?>,
    email      : <?php echo wp_json_encode( $pre_email ); ?>,
    name       : <?php echo wp_json_encode( $pre_name ); ?>,
    subject    : <?php echo wp_json_encode( $pre_subject ); ?>,
    revised    : <?php echo $pre_revised ? 'true' : 'false'; ?>,
    piNum      : <?php echo wp_json_encode( $pre_pi_num ); ?>,
    revision   : <?php echo (int) $pre_revision; ?>
  };

  if ( preFillData.hasPrefill ) {
    // Pre-fill recipient fields
    if ( preFillData.email )   $( '#cmp-email' ).val( preFillData.email );
    if ( preFillData.name )    $( '#cmp-name' ).val( preFillData.name );
    if ( preFillData.subject ) $( '#cmp-subject' ).val( preFillData.subject );

    // For revised PI — set tone to formal + pre-fill raw message note
    if ( preFillData.revised ) {
      $( '.sgc-tone-pill' ).removeClass( 'active' );
      $( '[data-tone="formal"]' ).addClass( 'active' );
      selectedTone = 'formal';
      var revisedNote = 'Please note this supersedes our previous Proforma Invoice. '
                      + 'Kindly refer to the revised ' + preFillData.piNum
                      + ' Rev.' + preFillData.revision + ' attached herewith.';
      $( '#cmp-raw' ).val( revisedNote );
      $( '#raw-chars' ).text( revisedNote.length );
    }
  }
  var selectedTone = 'professional';

  // ── Tone pills ────────────────────────────────────────────────────────
  $('.sgc-tone-pill').on('click', function(){
    $('.sgc-tone-pill').removeClass('active');
    $(this).addClass('active');
    selectedTone = $(this).data('tone');
  });

  // ── Char counter ──────────────────────────────────────────────────────
  $('#cmp-raw').on('input', function(){
    $('#raw-chars').text($(this).val().length);
  });

  // ── AI Polish ─────────────────────────────────────────────────────────
  $('#btn-ai-polish, #btn-repolish').on('click', doAIPolish);

  function doAIPolish() {
    var raw = $.trim($('#cmp-raw').val());
    if (!raw) { iipNotice('Please paste your message first.', 'error'); return; }

    var name    = $('#cmp-name').val() || 'Valued Customer';
    var subject = $('#cmp-subject').val() || '';

    $('#btn-ai-polish').prop('disabled', true).text('⏳ Polishing…');
    $('#cmp-ai-spinner').show();
    $('#cmp-ai-msg').text('AI is refining your message…');

    $.post(SGCIIP.ajaxUrl, {
      action  : 'sgc_iip_ai_polish_email',
      nonce   : SGCIIP.nonce,
      raw_text: raw,
      tone    : selectedTone,
      to_name : name,
      subject : subject,
      mode    : currentMode
    }, function(r) {
      if (r.success) {
        polishedHTML = r.data.html;
        $('#cmp-body').html(polishedHTML);
        $('#cmp-polished-section').slideDown(200);
        $('#cmp-ai-msg').html('<span style="color:#2e7d32">✅ Polished! Click body to edit.</span>');
        renderSidePreview();
      } else {
        $('#cmp-ai-msg').html('<span style="color:#c62828">❌ ' + (r.data || 'AI failed') + '</span>');
      }
    }).fail(function(){
      $('#cmp-ai-msg').html('<span style="color:#c62828">❌ Network error</span>');
    }).always(function(){
      $('#btn-ai-polish').prop('disabled', false).text('✨ AI Polish');
      $('#cmp-ai-spinner').hide();
    });
  }

  // ── Live preview (side panel) ─────────────────────────────────────────
  function renderSidePreview() {
    var bodyHtml = $('#cmp-body').html() || polishedHTML;
    if (!bodyHtml) return;

    var name    = $('#cmp-name').val() || 'Valued Customer';
    var company = '<?php echo esc_js( get_option("sgc_iip_company_name","SGC LABS®") ); ?>';
    var phone   = '<?php echo esc_js( get_option("sgc_iip_company_phone","+91 999 641 6935") ); ?>';
    var email   = '<?php echo esc_js( get_option("sgc_iip_company_email","info@labsglass.com") ); ?>';
    var website = '<?php echo esc_js( get_option("sgc_iip_company_website","https://labsglass.com") ); ?>';

    var html = '<div style="font-family:Arial,sans-serif;font-size:13px;color:#333;background:#f0f2f5;padding:16px">'
      + '<div style="max-width:540px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)">'
      // header
      + '<div style="background:#1a1a2e;padding:16px 20px">'
      + '<span style="color:#fff;font-size:16px;font-weight:700">'+company+'</span><br>'
      + '<span style="color:#9090aa;font-size:10px">Laboratory Glassware Manufacturer &amp; Exporter | Since 2003</span>'
      + '</div>'
      // body
      + '<div style="padding:20px">' + bodyHtml + '</div>'
      // footer
      + '<div style="background:#f8f9fa;padding:14px 20px;border-top:1px solid #e0e0e0;font-size:11px;color:#888">'
      + '<strong style="color:#333">'+company+'</strong> | Ambala, India<br>'
      + '📞 '+phone+' &nbsp;|&nbsp; ✉ '+email+'<br>'
      + '🌐 '+website
      + '</div></div></div>';

    $('#sgc-preview-body').html(html);
  }

  // Update preview when body is edited directly
  $('#cmp-body').on('input', function(){
    clearTimeout(window._prevTimer);
    window._prevTimer = setTimeout(renderSidePreview, 600);
  });

  // ── Full preview modal ────────────────────────────────────────────────
  $('#btn-fullpreview').on('click', function(){
    var email   = $.trim($('#cmp-email').val());
    var name    = $.trim($('#cmp-name').val());
    var subject = $.trim($('#cmp-subject').val());
    var body    = $('#cmp-body').html();

    if (!email || !subject || !body) {
      iipNotice('Please fill email, subject and polish your message first.', 'error');
      return;
    }

    $('#prev-to').text(name ? name + ' <' + email + '>' : email);
    $('#prev-subject').text(subject);

    // Fetch full wrapped HTML from server
    $.post(SGCIIP.ajaxUrl, {
      action   : 'sgc_iip_preview_compose',
      nonce    : SGCIIP.nonce,
      html_body: body,
      to_name  : name,
      subject  : subject
    }, function(r){
      if (r.success) {
        var blob = new Blob([r.data.html], {type:'text/html'});
        var url  = URL.createObjectURL(blob);
        $('#preview-iframe').attr('src', url);
        openModal('modal-fullpreview');
      } else {
        iipNotice('Preview failed: ' + (r.data||'error'), 'error');
      }
    });
  });

  // ── File attachments ──────────────────────────────────────────────────
  function handleFileSelect(files) {
    for (var i=0; i<files.length; i++) {
      var f = files[i];
      // 100MB limit per file
      if (f.size > 100 * 1024 * 1024) {
        iipNotice('❌ ' + f.name + ' is over 100MB limit.', 'error');
        continue;
      }
      // Deduplicate by name+size
      var exists = attachedFiles.some(function(a){ return a.name===f.name && a.size===f.size; });
      if (!exists) attachedFiles.push(f);
    }
    renderAttachList();
  }
  window.handleFileSelect = handleFileSelect;

  function renderAttachList() {
    var totalBytes = 0;
    var html = '';
    attachedFiles.forEach(function(f, idx){
      totalBytes += f.size;
      var ext   = f.name.split('.').pop().toUpperCase();
      var icon  = ext==='PDF' ? '📄' : (ext==='XLSX'||ext==='XLS') ? '📊' : (ext==='ZIP'||ext==='RAR') ? '🗜' : (ext==='JPG'||ext==='JPEG'||ext==='PNG') ? '🖼' : '📁';
      html += '<div class="sgc-attach-item">'
            + '<span style="font-size:16px">'+icon+'</span>'
            + '<span class="attach-name" title="'+escH(f.name)+'">'+escH(f.name)+'</span>'
            + '<span class="attach-size">'+formatBytes(f.size)+'</span>'
            + '<button class="attach-rm" title="Remove" onclick="removeAttach('+idx+')">×</button>'
            + '</div>';
    });
    $('#attach-list').html(html);
    if (attachedFiles.length > 0) {
      $('#attach-total').show().text('Total: ' + formatBytes(totalBytes) + ' / 100 MB | ' + attachedFiles.length + ' file(s)');
    } else {
      $('#attach-total').hide();
    }
  }

  window.removeAttach = function(idx) {
    attachedFiles.splice(idx, 1);
    renderAttachList();
  };

  // Drag & drop
  var zone = document.getElementById('attach-zone');
  zone.addEventListener('dragover', function(e){ e.preventDefault(); zone.classList.add('drag-over'); });
  zone.addEventListener('dragleave', function(){ zone.classList.remove('drag-over'); });
  zone.addEventListener('drop', function(e){
    e.preventDefault();
    zone.classList.remove('drag-over');
    handleFileSelect(e.dataTransfer.files);
  });

  // ── Send Email ────────────────────────────────────────────────────────
  $('#btn-send-compose').on('click', doSendEmail);
  window.doSendEmail = doSendEmail;

  function doSendEmail() {
    var toEmail = $.trim($('#cmp-email').val());
    var subject = $.trim($('#cmp-subject').val());
    var body    = $.trim($('#cmp-body').html());
    var toName  = $.trim($('#cmp-name').val());
    var ccVal   = $.trim($('#cmp-cc').val());
    var bccVal  = $.trim($('#cmp-bcc').val());

    if (!toEmail) { iipNotice('❌ Recipient email required.', 'error'); $('#cmp-email').focus(); return; }
    if (!subject) { iipNotice('❌ Subject required.', 'error'); $('#cmp-subject').focus(); return; }
    if (!body)    { iipNotice('❌ Email body is empty. Please AI Polish your message first.', 'error'); return; }

    // Check total size
    var totalBytes = attachedFiles.reduce(function(sum,f){ return sum+f.size; }, 0);
    if (totalBytes > 100*1024*1024) { iipNotice('❌ Total attachments exceed 100MB.', 'error'); return; }

    $('#btn-send-compose').prop('disabled', true).text('⏳ Sending…');
    $('#cmp-send-status').html('<span style="color:#888">Sending email…</span>');

    // Use FormData for file upload
    var fd = new FormData();
    fd.append('action',    'sgc_iip_send_compose_email');
    fd.append('nonce',     SGCIIP.nonce);
    fd.append('to_email',  toEmail);
    fd.append('to_name',   toName);
    fd.append('subject',   subject);
    fd.append('html_body', body);
    if (ccVal)  fd.append('cc',  ccVal);
    if (bccVal) fd.append('bcc', bccVal);

    attachedFiles.forEach(function(f, i){
      fd.append('attachments[]', f, f.name);
    });

    $.ajax({
      url        : SGCIIP.ajaxUrl,
      type       : 'POST',
      data       : fd,
      processData: false,
      contentType: false,
      success: function(r) {
        if (r.success) {
          $('#cmp-send-status').html('<span class="status-ok">✅ ' + r.data.message + '</span>');
          iipNotice('✅ Email sent to ' + toEmail, 'success');
          // Reset form optionally
          setTimeout(function(){
            if (confirm('Email sent! Reset the compose form for a new email?')) {
              resetCompose();
            }
          }, 800);
        } else {
          $('#cmp-send-status').html('<span class="status-err">❌ ' + (r.data||'Failed') + '</span>');
          iipNotice('❌ ' + (r.data||'Email failed'), 'error');
        }
      },
      error: function() {
        $('#cmp-send-status').html('<span class="status-err">❌ Network error</span>');
        iipNotice('❌ Network error. Try again.', 'error');
      },
      complete: function() {
        $('#btn-send-compose').prop('disabled', false).text('📤 Send Email');
      }
    });
  }

  // ── Reset ─────────────────────────────────────────────────────────────
  function resetCompose() {
    $('#cmp-name,#cmp-email,#cmp-subject,#cmp-raw').val('');
    $('#cmp-body').html('');
    $('#cmp-polished-section').hide();
    $('#cmp-ai-msg').text('');
    $('#cmp-send-status').html('');
    $('#raw-chars').text('0');
    polishedHTML = '';
    attachedFiles = [];
    renderAttachList();
    $('#sgc-preview-body').html(
      '<div class="sgc-preview-empty"><div class="icon">✉</div><div style="font-size:13px">Compose your message<br>and click <strong>AI Polish</strong></div></div>'
    );
  }

  // ── Helpers ───────────────────────────────────────────────────────────
  function formatBytes(b) {
    if (b < 1024)       return b + ' B';
    if (b < 1048576)    return (b/1024).toFixed(1) + ' KB';
    return (b/1048576).toFixed(1) + ' MB';
  }
  function escH(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  // ── Template System ──────────────────────────────────────────────────
  var selectedTemplateId = null;
  var tplItemCount = 1;

  // Load templates on page ready
  $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_get_templates',nonce:SGCIIP.nonce},function(r){
    if(!r.success) return;
    var html='';
    r.data.forEach(function(tpl){
      html+='<div class="tpl-card" data-id="'+tpl.id+'" onclick="selectTemplate(\''+tpl.id+'\',\''+tpl.name+'\')" '
        +'style="border:1.5px solid #e5e7eb;border-radius:8px;padding:10px;text-align:center;cursor:pointer;transition:all .15s;background:#fff">'
        +'<div style="font-size:20px;margin-bottom:4px">'+tpl.icon+'</div>'
        +'<div style="font-size:11px;font-weight:600;color:#374151;line-height:1.3">'+tpl.name+'</div>'
        +'<div style="font-size:9px;color:#9ca3af;margin-top:3px">'+tpl.incoterm+(tpl.currency?' · '+tpl.currency:'')+'</div>'
        +'</div>';
    });
    $('#template-grid').html(html);
  });

  window.selectTemplate = function(id, name) {
    selectedTemplateId = id;
    // Highlight selected
    $('.tpl-card').css({'border-color':'#e5e7eb','background':'#fff'});
    $('.tpl-card[data-id="'+id+'"]').css({'border-color':'#7c3aed','background':'#faf5ff'});
    $('#tpl-selected-name').text(name);
    $('#template-fill-form').slideDown(150);
    // Scroll to form
    document.getElementById('template-fill-form').scrollIntoView({behavior:'smooth',block:'start'});
  };

  window.addTemplateItem = function() {
    var idx = tplItemCount++;
    var row = '<tr id="tpl-item-'+idx+'">'
      +'<td style="padding:3px"><input type="text" class="iip-input tpl-item-name" placeholder="Product name" style="width:100%;font-size:11px"></td>'
      +'<td style="padding:3px"><input type="number" class="iip-input tpl-item-qty" value="1" min="1" style="width:100%;font-size:11px;text-align:center"></td>'
      +'<td style="padding:3px"><input type="text" class="iip-input tpl-item-unit" value="pcs" style="width:100%;font-size:11px;text-align:center"></td>'
      +'<td style="padding:3px"><input type="number" class="iip-input tpl-item-rate" placeholder="0.00" step="0.01" style="width:100%;font-size:11px;text-align:right" oninput="calcTemplateTotal()"></td>'
      +'<td style="padding:3px;text-align:center"><button onclick="removeTemplateItem('+idx+')" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:14px">&#10005;</button></td>'
      +'</tr>';
    $('#tpl-items-body').append(row);
  };

  window.removeTemplateItem = function(idx) {
    $('#tpl-item-'+idx).remove();
    calcTemplateTotal();
  };

  window.calcTemplateTotal = function() {
    var total=0;
    var cur = $('#tpl-currency').val()||'USD';
    var sym = cur==='INR'?'₹':'$';
    $('.tpl-item-rate').each(function(){
      var qty = parseFloat($(this).closest('tr').find('.tpl-item-qty').val())||1;
      var rate = parseFloat($(this).val())||0;
      total += qty*rate;
    });
    if(total>0) {
      $('#tpl-total-display').text('Total: '+sym+total.toFixed(2));
      updatePaymentTermsPreview(total);
    } else {
      $('#tpl-total-display').text('');
    }
  };

  window.updatePaymentTermsPreview = function(autoAmount) {
    var amount = autoAmount || parseFloat($('#tpl-amount').val())||0;
    var cur    = $('#tpl-currency').val()||'USD';
    if(amount<=0){ $('#payment-terms-preview').html('💰 Payment terms: Enter order value to auto-calculate'); return; }

    $.post(SGCIIP.ajaxUrl,{action:'sgc_iip_get_payment_terms',nonce:SGCIIP.nonce,amount:amount,currency:cur},function(r){
      if(r.success){
        var sym = cur==='INR'?'₹':'$';
        var threshold = r.data.threshold;
        var terms = r.data.terms;
        var color = amount < threshold ? '#dc2626' : '#059669';
        $('#payment-terms-preview').html(
          '💰 <strong>Payment Terms:</strong> <span style="color:'+color+'">'+terms+'</span>'
          +(amount<threshold?'<span style="font-size:10px;color:#9ca3af"> (below $'+threshold+' threshold)</span>':'')
        );
        $('#tpl-amount').val(amount.toFixed(2));
      }
    });
  };

  $('#tpl-currency').on('change', calcTemplateTotal);

  window.applyTemplate = function() {
    if(!selectedTemplateId){ iipNotice('Template select karo pehle','error'); return; }

    // Collect items
    var items=[];
    $('#tpl-items-body tr').each(function(){
      var name = $(this).find('.tpl-item-name').val().trim();
      if(!name) return;
      items.push({
        name: name,
        qty:  $(this).find('.tpl-item-qty').val()||'1',
        unit: $(this).find('.tpl-item-unit').val()||'pcs',
        rate: $(this).find('.tpl-item-rate').val()||'0'
      });
    });

    var buyerName = $('#tpl-buyer-name').val()||'Sir/Ma\'am';
    var location  = $('#tpl-buyer-location').val()||'';
    var currency  = $('#tpl-currency').val()||'USD';
    var amount    = parseFloat($('#tpl-amount').val())||0;

    // Auto-fill compose fields
    $('#cmp-name').val(buyerName);
    $('#cmp-email').val('');  // User fills

    $.post(SGCIIP.ajaxUrl,{
      action:       'sgc_iip_render_template',
      nonce:        SGCIIP.nonce,
      template_id:  selectedTemplateId,
      buyer_name:   buyerName,
      buyer_country:location,
      buyer_city:   location,
      currency:     currency,
      amount:       amount,
      items:        JSON.stringify(items)
    },function(r){
      if(!r.success){ iipNotice('Error: '+(r.data||'Template render failed'),'error'); return; }
      var d = r.data;

      // Fill compose fields
      $('#cmp-subject').val(d.subject);

      // Convert plain text body to HTML for editor
      var htmlBody = '<p>'+d.body.replace(/\n\n/g,'</p><p>').replace(/\n/g,'<br>')+'</p>';
      htmlBody = htmlBody.replace(/──+/g,'<hr style="border:none;border-top:1px solid #e0e0e0;margin:8px 0">');

      $('#cmp-body').html(htmlBody);
      $('#cmp-polished-section').slideDown(200);
      $('#cmp-raw').val('[Template used: '+$('.tpl-card[data-id="'+selectedTemplateId+'"]').find('div:eq(1)').text()+']');
      renderSidePreview && renderSidePreview();

      // Auto-run AI Polish on template output
      iipNotice('⏳ AI template ko polish kar raha hai...','info',5000);
      $('#btn-ai-polish').prop('disabled',true);
      $('#cmp-ai-spinner').show();

      $.post(SGCIIP.ajaxUrl,{
        action:   'sgc_iip_ai_polish_email',
        nonce:    SGCIIP.nonce,
        raw_text: d.body,
        tone:     'professional',
        to_name:  $('#tpl-buyer-name').val()||'Valued Customer',
        subject:  d.subject,
        mode:     'template'
      },function(ar){
        $('#btn-ai-polish').prop('disabled',false);
        $('#cmp-ai-spinner').hide();
        if(ar.success){
          $('#cmp-body').html(ar.data.html);
          iipNotice('✅ AI draft ready! Review karke send karo.');
          renderSidePreview && renderSidePreview();
        } else {
          iipNotice('✅ Template applied (AI polish failed — manually edit karo).');
        }
        document.getElementById('cmp-polished-section').scrollIntoView({behavior:'smooth'});
      }).fail(function(){
        $('#btn-ai-polish').prop('disabled',false);
        $('#cmp-ai-spinner').hide();
        iipNotice('✅ Template applied! Review karke send karo.');
        document.getElementById('cmp-polished-section').scrollIntoView({behavior:'smooth'});
      });
    });
  };

  // ── Mode Selector ─────────────────────────────────────────────────────
  var currentMode = 'general';
  var modeHints = {
    general:   '💡 Koi bhi text paste karo — Hindi, English, mixed — AI samajh lega',
    tubing:    '💡 Tubing data paste karo: "20mm tube - 1.8mm wall - $2.50/meter, 28mm - 2.5mm - $3.20..." — AI table banayega automatically',
    quotation: '💡 Client ki mail paste karo + apna rough reply — AI professional quotation email banayega',
    followup:  '💡 Inquiry ID ya client naam paste karo — AI smart follow-up email banayega'
  };
  var modePlaceholders = {
    general:   'e.g. hi john bhai beaker price list manga hai 50ml se 2000ml tak USD FOB price chahiye...',
    tubing:    'e.g.
20mm OD - 1.8mm wall - $2.50/meter
28mm OD - 2.5mm wall - $3.20/meter
32mm OD - 2.0mm wall - $3.80/meter

Borosilicate 3.3 | ISO standard | FOB Ambala India',
    quotation: 'Client ki email paste karo yahan phir apna rough answer likho...',
    followup:  'Client ka naam ya inquiry no. likho e.g. "Yamam Alkoshab - 1.5 ltr beaker ka follow up"'
  };

  window.setMode = function(mode) {
    currentMode = mode;
    document.querySelectorAll('[id^="mode-"]').forEach(function(b){ b.classList.remove('active'); });
    document.getElementById('mode-'+mode).classList.add('active');
    document.getElementById('mode-hint').innerHTML = modeHints[mode];
    document.getElementById('cmp-raw').placeholder = modePlaceholders[mode];
    // Auto-set tone pill
    if(mode==='quotation') {
      document.querySelectorAll('.sgc-tone-pill').forEach(function(p){ p.classList.remove('active'); });
      var qpill = document.querySelector('[data-tone="quotation"]');
      if(qpill) qpill.classList.add('active');
    } else if(mode==='followup') {
      document.querySelectorAll('.sgc-tone-pill').forEach(function(p){ p.classList.remove('active'); });
      var fpill = document.querySelector('[data-tone="followup"]');
      if(fpill) fpill.classList.add('active');
    }
  };

  // ── Save Draft (server) ───────────────────────────────────────────────
  window.saveDraft = function() {
    var to      = $('#cmp-email').val();
    var subject = $('#cmp-subject').val();
    var body    = document.getElementById('cmp-body').innerHTML;
    var raw     = $('#cmp-raw').val();
    if(!subject && !body){ iipNotice('Subject ya body required','error'); return; }

    $('#btn-save-draft').prop('disabled',true).text('Saving...');
    $.post(SGCIIP.ajaxUrl, {
      action:  'sgc_iip_save_compose_draft',
      nonce:   SGCIIP.nonce,
      to:      to,
      cc:      $('#cmp-cc').val(),
      bcc:     $('#cmp-bcc-extra').val(),
      subject: subject,
      body:    body,
      raw:     raw,
      mode:    currentMode
    }, function(r) {
      $('#btn-save-draft').prop('disabled',false).text('💾 Save Draft');
      if(r.success) iipNotice('✅ Draft saved! ID: #'+r.data.id);
      else iipNotice('Error: '+(r.data||'Save failed'),'error');
    });
  };

  // ── Google Drive Save ─────────────────────────────────────────────────
  window.saveToDrive = function() {
    var subject = $('#cmp-subject').val() || 'SGC Email Draft';
    var body    = document.getElementById('cmp-body').innerText || '';
    var to      = $('#cmp-email').val();
    if(!body){ iipNotice('Email body empty — AI Polish pehle karo','error'); return; }

    $('#btn-drive-save').prop('disabled',true).text('📁 Saving...');
    $('#drive-status').hide();

    $.post(SGCIIP.ajaxUrl, {
      action:  'sgc_iip_save_to_drive',
      nonce:   SGCIIP.nonce,
      subject: subject,
      to:      to,
      body:    body,
      date:    new Date().toISOString().substr(0,10)
    }, function(r) {
      $('#btn-drive-save').prop('disabled',false).text('📁 Google Drive');
      if(r.success) {
        $('#drive-status').html('✅ Saved to Google Drive: <a href="'+r.data.url+'" target="_blank" style="color:#1565c0">'+r.data.name+'</a>').show();
        iipNotice('✅ Google Drive pe save ho gaya!');
      } else {
        iipNotice('Drive error: '+(r.data||'Failed'),'error');
      }
    });
  };

  // ── Format Toolbar Functions ──────────────────────────────────────────

  function fmtCmd(cmd) {
    document.getElementById('cmp-body').focus();
    document.execCommand(cmd, false, null);
  }

  function insertBulletList() {
    var editor = document.getElementById('cmp-body');
    editor.focus();
    document.execCommand('insertUnorderedList', false, null);
  }

  function insertDivider() {
    var editor = document.getElementById('cmp-body');
    editor.focus();
    document.execCommand('insertHTML', false, '<hr style="border:none;border-top:1px solid #e0e0e0;margin:10px 0">');
  }

  function clearBody() {
    if(confirm('Email body clear karo?')) {
      document.getElementById('cmp-body').innerHTML = '';
    }
  }

  function showTableInsert() {
    var p = document.getElementById('table-insert-panel');
    p.style.display = p.style.display === 'none' ? 'block' : 'none';
  }

  function insertTable() {
    var cols    = parseInt(document.getElementById('tbl-cols').value) || 3;
    var rows    = parseInt(document.getElementById('tbl-rows').value) || 4;
    var headers = document.getElementById('tbl-headers').value.split(',').map(function(h){return h.trim();});

    var html = '<table style="border-collapse:collapse;width:100%;margin:10px 0;font-size:12px">';
    // Header row
    html += '<thead><tr style="background:#1a1a2e;color:#fff">';
    for(var c=0; c<cols; c++) {
      html += '<th style="border:1px solid #ccc;padding:7px 10px;text-align:left">'+(headers[c]||'Column '+(c+1))+'</th>';
    }
    html += '</tr></thead><tbody>';
    // Data rows
    for(var r=0; r<rows; r++) {
      var bg = r%2===0?'#ffffff':'#f8f9fa';
      html += '<tr style="background:'+bg+'">';
      for(var c2=0; c2<cols; c2++) {
        html += '<td style="border:1px solid #e0e0e0;padding:6px 10px" contenteditable="true">&nbsp;</td>';
      }
      html += '</tr>';
    }
    html += '</tbody></table><p></p>';

    document.getElementById('cmp-body').focus();
    document.execCommand('insertHTML', false, html);
    document.getElementById('table-insert-panel').style.display = 'none';
  }

  function quickTable(type) {
    var configs = {
      'glass-tube': {
        cols: 3,
        rows: 9,
        headers: ['Outside Diameter','Wall Thickness','Available'],
        data: [
          ['20mm','1.8mm','&#10003; Yes'],
          ['28mm','2.5mm','&#10003; Yes'],
          ['32mm','2.0mm','&#10003; Yes'],
          ['34mm','2.8mm','&#10003; Yes'],
          ['36mm','2.0mm','&#10003; Yes'],
          ['42mm','3.0mm','&#10003; Yes'],
          ['45mm','2.8mm','&#10003; Yes'],
          ['50mm','3.0mm','&#10003; Yes'],
          ['65mm','3.2mm','&#10003; Yes'],
        ]
      },
      'price-list': {
        cols: 4,
        rows: 5,
        headers: ['Product','Qty','Unit Price (USD)','Total'],
        data: []
      },
      'product-list': {
        cols: 3,
        rows: 5,
        headers: ['Product Name','Specification','Quantity'],
        data: []
      },
      'availability': {
        cols: 3,
        rows: 5,
        headers: ['Product','Stock Status','Lead Time'],
        data: []
      }
    };

    var cfg = configs[type];
    if(!cfg) return;

    // Pre-fill headers
    document.getElementById('tbl-cols').value = cfg.cols;
    document.getElementById('tbl-rows').value = cfg.rows;
    document.getElementById('tbl-headers').value = cfg.headers.join(',');

    // Build table with pre-filled data
    var html = '<table style="border-collapse:collapse;width:100%;margin:10px 0;font-size:12px">';
    html += '<thead><tr style="background:#1a1a2e;color:#fff">';
    cfg.headers.forEach(function(h) {
      html += '<th style="border:1px solid #ccc;padding:7px 10px;text-align:left">'+h+'</th>';
    });
    html += '</tr></thead><tbody>';

    for(var r=0; r<cfg.rows; r++) {
      var bg = r%2===0?'#ffffff':'#f8f9fa';
      html += '<tr style="background:'+bg+'">';
      for(var c=0; c<cfg.cols; c++) {
        var val = (cfg.data[r] && cfg.data[r][c]) ? cfg.data[r][c] : '&nbsp;';
        html += '<td style="border:1px solid #e0e0e0;padding:6px 10px" contenteditable="true">'+val+'</td>';
      }
      html += '</tr>';
    }
    html += '</tbody></table><p></p>';

    document.getElementById('cmp-body').focus();
    document.execCommand('insertHTML', false, html);
    document.getElementById('table-insert-panel').style.display='none';
  }

  function insertBoilerplate(type) {
    var editor = document.getElementById('cmp-body');
    var texts = {
      greeting: '<p>Dear Sir/Ma\'am,</p><p>Thank you for your inquiry. Please find the details below:</p>',
      signature: '<p><br>Best regards,<br><strong>SGC LABS® Export Team</strong><br>sales@labsglass.com | +91 999 641 6935<br>labsglass.com</p>'
    };
    if(texts[type]) {
      editor.focus();
      document.execCommand('insertHTML', false, texts[type]);
    }
  }

  // ── WhatsApp Preview ─────────────────────────────────────────────────

  function showWAPreview() {
    var body = document.getElementById('cmp-body');
    // Convert HTML to plain text for WA
    var temp = document.createElement('div');
    temp.innerHTML = body.innerHTML;

    // Convert tables to text format
    temp.querySelectorAll('table').forEach(function(tbl) {
      var rows = tbl.querySelectorAll('tr');
      var textRows = [];
      rows.forEach(function(row, ri) {
        var cells = row.querySelectorAll('th,td');
        var cellTexts = [];
        cells.forEach(function(c){ cellTexts.push(c.innerText.trim()); });
        if(ri===0) {
          // Header - make bold with *
          textRows.push('*'+cellTexts.join('* | *')+'*');
          textRows.push(cellTexts.map(function(){ return '---'; }).join(' | '));
        } else {
          textRows.push(cellTexts.join(' | '));
        }
      });
      var textNode = document.createTextNode(textRows.join('
'));
      tbl.parentNode.replaceChild(textNode, tbl);
    });

    // Convert <br>, <p>, <li> to newlines
    temp.querySelectorAll('p,br,li').forEach(function(el){
      el.insertAdjacentText('afterend', '
');
    });

    // Convert <b>,<strong> to *text*
    temp.querySelectorAll('b,strong').forEach(function(el){
      el.insertAdjacentText('beforebegin','*');
      el.insertAdjacentText('afterend','*');
    });

    var plain = temp.innerText || temp.textContent || '';
    plain = plain.replace(/
{3,}/g,'

').trim();

    document.getElementById('wa-bubble').textContent = plain;
    document.getElementById('wa-preview-modal').style.display = 'flex';
    window._waText = plain;
  }

  function copyWAText() {
    if(window._waText) {
      navigator.clipboard.writeText(window._waText).then(function(){
        iipNotice('&#10003; Copied to clipboard! Paste in WhatsApp.');
      }).catch(function(){
        // Fallback
        var ta = document.createElement('textarea');
        ta.value = window._waText;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        iipNotice('&#10003; Copied!');
      });
    }
  }

})(jQuery);
</script>
