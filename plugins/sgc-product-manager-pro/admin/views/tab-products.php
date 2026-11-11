<?php defined( 'ABSPATH' ) || exit; ?>
<div id="pm-products-wrap">
<form id="pm-filter-form" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:10px">
	<input type="text" id="pm-search" class="sgc-pm-input" placeholder="Search name, SKU…" style="width:180px">
	<select id="pm-cat" class="sgc-pm-select"><option value="">All Categories</option><?php foreach(get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'number'=>100]) as $c) echo '<option value="'.esc_attr($c->term_id).'">'.esc_html($c->name).'</option>'; ?></select>
	<select id="pm-status" class="sgc-pm-select"><option value="">All Status</option><option value="publish">Published</option><option value="draft">Draft</option></select>
	<select id="pm-stock" class="sgc-pm-select"><option value="">All Stock</option><option value="instock">In Stock</option><option value="outofstock">Out of Stock</option></select>
	<input type="hidden" id="pm-preset" value="">
	<button type="submit" class="sgc-pm-btn primary">Filter</button>
	<button type="button" class="sgc-pm-btn secondary" onclick="$('#pm-filter-form')[0].reset();$('#pm-preset').val('');$('#pm-filter-form').trigger('submit')">Reset</button>
	<a href="<?php echo esc_url( admin_url('admin.php?page=sgc-product-manager&tab=import') ); ?>" class="sgc-pm-btn secondary" style="margin-left:auto">📥 Import</a>
</form>

<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px">
	<span style="font-size:11px;color:#888;line-height:28px">Quick:</span>
	<button class="sgc-pm-btn secondary pm-preset-btn" data-preset="no_sku" style="font-size:11px">⚠ No SKU</button>
	<button class="sgc-pm-btn secondary pm-preset-btn" data-preset="no_image" style="font-size:11px">🖼 No Image</button>
	<button class="sgc-pm-btn secondary pm-preset-btn" data-preset="low_stock" style="font-size:11px">📦 Low Stock</button>
	<button class="sgc-pm-btn secondary pm-preset-btn" data-preset="" style="font-size:11px">All</button>
</div>

<div id="pm-bulk-bar" style="display:none;background:#1a1a2e;color:#fff;border-radius:6px;padding:8px 14px;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px;font-size:12px">
	<span id="pm-sel-count" style="font-weight:600">0 selected</span>
	<button class="pm-bulk-btn-sm" id="pm-bulk-price">Price %</button>
	<button class="pm-bulk-btn-sm" id="pm-bulk-stock">Stock</button>
	<button class="pm-bulk-btn-sm" id="pm-bulk-status">Status</button>
	<button class="pm-bulk-btn-sm" id="pm-bulk-sku">Gen SKU</button>
	<button class="pm-bulk-btn-sm" id="pm-bulk-export">Export CSV</button>
	<button class="pm-bulk-btn-sm" id="pm-bulk-category">📂 Category</button>
	<button class="pm-bulk-btn-sm" style="background:#0e7c3a;color:#fff;font-weight:700;border:1.5px solid #27ae60" id="pm-bulk-edit-btn">✏️ Bulk Edit</button>
	<button class="pm-bulk-btn-sm" style="background:#7c3aed;color:#fff;font-weight:700;border:1.5px solid #a78bfa" id="pm-bulk-ai-gen">🤖 AI Generate</button>
	<button class="pm-bulk-btn-sm pm-bulk-btn-red" id="pm-bulk-delete">Delete</button>
	<button class="pm-bulk-btn-sm" id="pm-clear-sel" style="margin-left:auto">Clear</button>
</div>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
	<span style="font-size:12px;font-weight:600" id="pm-total-count">Loading…</span>
	<div style="display:flex;align-items:center;gap:6px">
		<button class="sgc-pm-btn-sm" id="pm-prev">‹</button>
		<span style="font-size:11px;color:#888" id="pm-page-info">Page 1</span>
		<button class="sgc-pm-btn-sm" id="pm-next">›</button>
	</div>
</div>

<div style="overflow-x:auto;border:1px solid #e8e8e8;border-radius:6px;overflow:hidden">
<table class="sgc-pm-table" style="min-width:900px">
	<thead><tr>
		<th style="width:28px"><input type="checkbox" id="pm-chk-all" style="width:14px;height:14px"></th>
		<th>Product Name</th>
		<th>SKU <small style="font-weight:400;color:#bbb">(dbl-click)</small></th>
		<th>Regular Price</th>
		<th>Sale Price</th>
		<th>Stock</th>
		<th>Status</th>
		<th title="SKU+Price+Image+Desc">Health</th>
		<th></th>
	</tr></thead>
	<tbody id="pm-products-tbody">
		<tr><td colspan="9" style="text-align:center;padding:40px;color:#aaa">Loading…</td></tr>
	</tbody>
</table>
</div>
<div id="pm-bulk-result" class="sgc-pm-notice" style="display:none;margin-top:10px"></div>
</div>

<!-- Category Reassign Modal (from Products Tab) -->
<div class="sgc-pm-modal" id="modal-cat-reassign" style="display:none">
	<div class="sgc-pm-modal-inner" style="max-width:460px">
		<div class="sgc-pm-modal-hdr">
			<h3>📂 Move to Category</h3>
			<button class="pm-modal-close" data-modal="modal-cat-reassign">✕</button>
		</div>
		<div class="sgc-pm-modal-body">
			<div style="background:#FFF3CD;border-radius:5px;padding:10px;font-size:12px;margin-bottom:14px">
				Moving <strong id="cat-modal-count">0</strong> selected product(s) to:
			</div>
			<div class="sgc-pm-fg">
				<label>Select Target Category</label>
				<select id="cat-reassign-target" class="sgc-pm-select" style="width:100%">
					<option value="">— Choose category —</option>
					<?php
					$all_cats = get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'orderby'=>'name','order'=>'ASC','number'=>200]);
					foreach($all_cats as $c) echo '<option value="'.esc_attr($c->term_id).'">'.esc_html($c->name).' ('.$c->count.')</option>';
					?>
				</select>
			</div>
			<div class="sgc-pm-fg">
				<label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:12px">
					<input type="checkbox" id="cat-reassign-append" style="width:14px;height:14px">
					<span>Append (keep existing categories too)</span>
				</label>
				<div style="font-size:11px;color:#888;margin-top:4px">Default: Replace existing category completely</div>
			</div>
		</div>
		<div class="sgc-pm-modal-ftr">
			<button class="sgc-pm-btn secondary pm-modal-close" data-modal="modal-cat-reassign">Cancel</button>
			<button class="sgc-pm-btn primary" id="btn-do-cat-reassign">✅ Reassign Now</button>
		</div>
	</div>
</div>

<!-- ══ AI Generate Modal ══════════════════════════════════════════════════ -->
<div class="sgc-pm-modal" id="modal-ai-gen" style="display:none">
	<div class="sgc-pm-modal-inner" style="max-width:500px">
		<div class="sgc-pm-modal-hdr">
			<h3>🤖 AI Bulk Generate — SEO Content</h3>
			<button class="pm-modal-close" data-modal="modal-ai-gen">✕</button>
		</div>
		<div class="sgc-pm-modal-body">

			<!-- Selected count info -->
			<div style="background:#ede9fe;border-radius:6px;padding:10px 14px;margin-bottom:16px;font-size:13px;display:flex;align-items:center;gap:8px">
				<span style="font-size:20px">🤖</span>
				<span><strong id="ai-gen-count">0</strong> products selected for AI content generation</span>
			</div>

			<!-- Tone -->
			<div class="sgc-pm-fg" style="margin-bottom:12px">
				<label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px">Content Tone</label>
				<select id="ai-gen-tone" class="sgc-pm-select" style="width:100%">
					<option value="b2b_formal">💼 B2B Formal — Technical specs, quality certs, bulk pricing</option>
					<option value="export">🌍 Export — International buyers, FOB, compliance standards</option>
					<option value="educational">🎓 Educational — Schools, colleges, research labs</option>
					<option value="dealer">🤝 Dealer — Margin benefits, bulk discounts, partnership</option>
					<option value="concise">⚡ Concise — Bullet points, quick specs, no fluff</option>
				</select>
			</div>

			<!-- What to generate -->
			<div class="sgc-pm-fg" style="margin-bottom:12px">
				<label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px">Generate</label>
				<select id="ai-gen-type" class="sgc-pm-select" style="width:100%">
					<option value="both">Both — Main + Short description</option>
					<option value="main">Main description only</option>
					<option value="short">Short description only</option>
					<option value="seo">SEO Title + Meta only</option>
				</select>
			</div>

			<!-- Progress bar (hidden until running) -->
			<div id="ai-gen-prog" style="display:none;margin-top:12px">
				<div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
					<span id="ai-gen-prog-txt">Starting AI generation…</span>
					<span id="ai-gen-prog-pct" style="font-weight:600">0%</span>
				</div>
				<div style="height:8px;background:#e0e0e0;border-radius:4px;overflow:hidden">
					<div id="ai-gen-prog-bar" style="height:100%;background:#7c3aed;border-radius:4px;transition:width .4s;width:0%"></div>
				</div>
				<div id="ai-gen-log" style="margin-top:8px;max-height:120px;overflow-y:auto;font-size:11px;color:#666;line-height:1.7;background:#f8f8f8;padding:8px;border-radius:4px"></div>
			</div>

			<!-- Result summary -->
			<div id="ai-gen-result" style="display:none;margin-top:12px;padding:10px 14px;border-radius:6px;font-size:13px"></div>
		</div>
		<div class="sgc-pm-modal-ftr">
			<button class="sgc-pm-btn secondary pm-modal-close" data-modal="modal-ai-gen" id="ai-gen-cancel">Cancel</button>
			<button class="sgc-pm-btn primary" id="btn-do-ai-gen" style="background:#7c3aed;min-width:140px">🚀 Start AI Generate</button>
		</div>
	</div>
</div>

<script>
(function($){
  'use strict';

  // ── Open AI Generate Modal ─────────────────────────────────────────────
  $(document).on('click', '#pm-bulk-ai-gen', function(){
    var ids = getSelectedIds();
    if (!ids.length) { pmNotice('Pehle products select karo.', 'error'); return; }

    // Reset modal state
    $('#ai-gen-count').text(ids.length);
    $('#ai-gen-prog').hide();
    $('#ai-gen-result').hide().html('');
    $('#ai-gen-log').html('');
    $('#ai-gen-prog-bar').css('width','0%');
    $('#ai-gen-prog-pct').text('0%');
    $('#ai-gen-prog-txt').text('Starting AI generation…');
    $('#btn-do-ai-gen').prop('disabled', false).text('🚀 Start AI Generate');
    $('#ai-gen-cancel').text('Cancel');
    openModal('modal-ai-gen');
  });

  // ── Run AI Generation ──────────────────────────────────────────────────
  $(document).on('click', '#btn-do-ai-gen', function(){
    var ids   = getSelectedIds();
    var tone  = $('#ai-gen-tone').val();
    var dtype = $('#ai-gen-type').val();

    if (!ids.length) { pmNotice('Koi product selected nahi.', 'error'); return; }

    $('#btn-do-ai-gen').prop('disabled', true).text('⏳ Generating…');
    $('#ai-gen-cancel').text('Close when done');
    $('#ai-gen-prog').show();
    $('#ai-gen-result').hide();

    var BATCH = 5;   // 5 at a time — safe for shared hosting
    var total = ids.length;
    var done  = 0;
    var errors = 0;

    function runBatch(offset) {
      var batch = ids.slice(offset, offset + BATCH);
      if (!batch.length) {
        // All done
        var pct = 100;
        $('#ai-gen-prog-bar').css('width', pct + '%');
        $('#ai-gen-prog-pct').text(pct + '%');
        $('#ai-gen-prog-txt').text('✅ Complete!');
        $('#ai-gen-result').show().html(
          '<div style="background:#d1fae5;color:#065f46;padding:10px 14px;border-radius:6px">' +
          '✅ <strong>' + done + ' products</strong> updated successfully.' +
          (errors ? ' <span style="color:#b91c1c">❌ ' + errors + ' failed.</span>' : '') +
          '</div>'
        );
        $('#btn-do-ai-gen').prop('disabled', false).text('🚀 Run Again');
        pmNotice('✅ AI content generated for ' + done + ' products.', 'success');
        return;
      }

      $.post(SGCPM.ajaxUrl, {
        action   : 'sgc_pm_ai_bulk_seo',
        nonce    : SGCPM.nonce,
        ids      : batch,
        tone     : tone,
        desc_type: dtype
      }, function(r) {
        if (r.success) {
          done   += r.data.done   || 0;
          errors += r.data.errors || 0;
          $('#ai-gen-log').append('<div>Batch ' + Math.ceil((offset + BATCH) / BATCH) + ': ' + (r.data.done||0) + ' done, ' + (r.data.errors||0) + ' errors</div>');
          $('#ai-gen-log').scrollTop(9999);
        } else {
          errors += batch.length;
          $('#ai-gen-log').append('<div style="color:#b91c1c">Batch error: ' + (r.data||'Unknown') + '</div>');
        }

        // Update progress
        var processed = Math.min(offset + BATCH, total);
        var pct = Math.round((processed / total) * 100);
        $('#ai-gen-prog-bar').css('width', pct + '%');
        $('#ai-gen-prog-pct').text(pct + '%');
        $('#ai-gen-prog-txt').text('Processing ' + processed + ' / ' + total + ' products…');

        // Next batch after 800ms (rate limit safety)
        setTimeout(function(){ runBatch(offset + BATCH); }, 800);

      }).fail(function() {
        errors += batch.length;
        $('#ai-gen-log').append('<div style="color:#b91c1c">Network error on batch ' + Math.ceil((offset+BATCH)/BATCH) + '</div>');
        setTimeout(function(){ runBatch(offset + BATCH); }, 1000);
      });
    }

    runBatch(0);
  });

  // ── Helper: get selected product IDs ─────────────────────────────────
  function getSelectedIds() {
    var ids = [];
    $('.pm-row-chk:checked').each(function(){ ids.push($(this).val()); });
    return ids;
  }

  // ── Modal helpers (if not already defined globally) ───────────────────
  if (typeof openModal !== 'function') {
    window.openModal = function(id) { $('#'+id).fadeIn(180); };
  }
  if (typeof pmNotice !== 'function') {
    window.pmNotice = function(msg, type) {
      var $n = $('#sgc-pm-global-notice');
      $n.text(msg).removeClass('error success').addClass(type||'success').show();
      setTimeout(function(){ $n.fadeOut(); }, 4000);
    };
  }

})(jQuery);
</script>

<!-- ═══════════════════════════════════════════════════════════════════
     QUICK EDIT DRAWER (v3.0.4) — slides in from right
════════════════════════════════════════════════════════════════════ -->
<div id="pm-qe-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99998"></div>
<div id="pm-qe-drawer" style="display:none;position:fixed;top:0;right:0;width:520px;max-width:96vw;height:100vh;background:#fff;z-index:99999;box-shadow:-4px 0 24px rgba(0,0,0,.18);overflow-y:auto;flex-direction:column">

	<!-- Drawer Header -->
	<div style="background:#1a1a2e;color:#fff;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:10">
		<div>
			<div style="font-size:15px;font-weight:700">✏️ Quick Edit</div>
			<div id="pm-qe-product-name" style="font-size:12px;opacity:.7;margin-top:2px">Loading…</div>
		</div>
		<div style="display:flex;gap:8px;align-items:center">
			<a id="pm-qe-edit-link" href="#" target="_blank" class="sgc-pm-btn secondary" style="font-size:12px;padding:5px 12px;color:#1a1a2e">🔗 Full Edit</a>
			<button id="pm-qe-close" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;padding:0;line-height:1">✕</button>
		</div>
	</div>

	<!-- Drawer Body -->
	<div style="padding:20px;flex:1">
		<input type="hidden" id="pm-qe-id" value="0">

		<!-- Title -->
		<div style="margin-bottom:14px">
			<label style="font-size:12px;font-weight:700;color:#444;display:block;margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px">Product Title</label>
			<input type="text" id="pm-qe-name" style="width:100%;border:1.5px solid #ddd;border-radius:6px;padding:9px 12px;font-size:14px;box-sizing:border-box">
		</div>

		<!-- SKU + Prices row -->
		<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:14px">
			<div>
				<label style="font-size:11px;font-weight:700;color:#666;display:block;margin-bottom:4px">SKU</label>
				<input type="text" id="pm-qe-sku" style="width:100%;border:1.5px solid #ddd;border-radius:6px;padding:8px 10px;font-size:13px;box-sizing:border-box">
			</div>
			<div>
				<label style="font-size:11px;font-weight:700;color:#666;display:block;margin-bottom:4px">Regular Price</label>
				<input type="number" id="pm-qe-reg-price" step="0.01" min="0" style="width:100%;border:1.5px solid #ddd;border-radius:6px;padding:8px 10px;font-size:13px;box-sizing:border-box">
			</div>
			<div>
				<label style="font-size:11px;font-weight:700;color:#666;display:block;margin-bottom:4px">Sale Price</label>
				<input type="number" id="pm-qe-sale-price" step="0.01" min="0" style="width:100%;border:1.5px solid #ddd;border-radius:6px;padding:8px 10px;font-size:13px;box-sizing:border-box">
			</div>
		</div>

		<!-- Featured Image URL -->
		<div style="margin-bottom:14px">
			<label style="font-size:12px;font-weight:700;color:#444;display:block;margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px">Featured Image URL</label>
			<input type="url" id="pm-qe-feat-img" placeholder="https://labsglass.com/wp-content/uploads/…" style="width:100%;border:1.5px solid #ddd;border-radius:6px;padding:9px 12px;font-size:13px;box-sizing:border-box">
			<div id="pm-qe-feat-preview" style="margin-top:8px;display:none">
				<img id="pm-qe-feat-img-preview" src="" alt="" style="max-height:80px;border:1px solid #eee;border-radius:4px;object-fit:contain">
			</div>
			<div style="font-size:11px;color:#aaa;margin-top:4px">Paste image URL from Media Library or external. Must be in WP Media Library to save.</div>
		</div>

		<!-- Gallery Image URLs -->
		<div style="margin-bottom:14px">
			<label style="font-size:12px;font-weight:700;color:#444;display:block;margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px">Gallery Images (one URL per line)</label>
			<textarea id="pm-qe-gallery" rows="4" placeholder="https://labsglass.com/wp-content/uploads/img1.jpg&#10;https://labsglass.com/wp-content/uploads/img2.jpg&#10;https://labsglass.com/wp-content/uploads/img3.jpg" style="width:100%;border:1.5px solid #ddd;border-radius:6px;padding:9px 12px;font-size:12px;box-sizing:border-box;resize:vertical"></textarea>
			<div style="font-size:11px;color:#aaa;margin-top:3px">Current gallery URLs shown below. Edit or replace. Each URL on new line or comma separated.</div>
			<div id="pm-qe-gallery-current" style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap"></div>
		</div>

		<!-- Short Description -->
		<div style="margin-bottom:14px">
			<label style="font-size:12px;font-weight:700;color:#444;display:block;margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px">Short Description</label>
			<textarea id="pm-qe-short-desc" rows="3" style="width:100%;border:1.5px solid #ddd;border-radius:6px;padding:9px 12px;font-size:13px;box-sizing:border-box;resize:vertical"></textarea>
		</div>

		<!-- Main Description -->
		<div style="margin-bottom:20px">
			<label style="font-size:12px;font-weight:700;color:#444;display:block;margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px">Main Description</label>
			<textarea id="pm-qe-desc" rows="6" style="width:100%;border:1.5px solid #ddd;border-radius:6px;padding:9px 12px;font-size:13px;box-sizing:border-box;resize:vertical"></textarea>
		</div>

		<!-- Notice -->
		<div id="pm-qe-notice" style="display:none;padding:10px 14px;border-radius:6px;font-size:13px;margin-bottom:14px"></div>

		<!-- Save button -->
		<div style="display:flex;gap:10px;justify-content:flex-end;position:sticky;bottom:0;background:#fff;padding:14px 0 4px;border-top:1px solid #f0f0f0;margin-top:4px">
			<button id="pm-qe-close-btn" class="sgc-pm-btn secondary">Cancel</button>
			<button id="pm-qe-save" class="sgc-pm-btn primary" style="min-width:120px;font-size:14px">💾 Save Changes</button>
		</div>
	</div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     BULK EDIT MODAL (v3.0.4)
════════════════════════════════════════════════════════════════════ -->
<div class="sgc-pm-modal" id="modal-bulk-edit" style="display:none">
	<div class="sgc-pm-modal-inner" style="max-width:560px">
		<div class="sgc-pm-modal-hdr">
			<h3>✏️ Bulk Edit — <span id="bulk-edit-count">0</span> Products</h3>
			<button class="pm-modal-close" data-modal="modal-bulk-edit">✕</button>
		</div>
		<div class="sgc-pm-modal-body">

			<div style="background:#fff8e1;border-left:3px solid #f9a825;border-radius:4px;padding:10px 14px;font-size:12px;margin-bottom:16px;color:#555">
				⚡ Only filled fields will be applied. Empty fields = no change.
			</div>

			<!-- Title prefix/suffix -->
			<div style="margin-bottom:14px">
				<label style="font-size:12px;font-weight:700;color:#444;display:block;margin-bottom:6px">Title — Add Prefix / Suffix</label>
				<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
					<div>
						<label style="font-size:11px;color:#888">Prefix (add before title)</label>
						<input type="text" id="be-name-prefix" placeholder="e.g. SGC " style="width:100%;border:1.5px solid #ddd;border-radius:6px;padding:7px 10px;font-size:13px;box-sizing:border-box;margin-top:3px">
					</div>
					<div>
						<label style="font-size:11px;color:#888">Suffix (add after title)</label>
						<input type="text" id="be-name-suffix" placeholder="e.g. — India" style="width:100%;border:1.5px solid #ddd;border-radius:6px;padding:7px 10px;font-size:13px;box-sizing:border-box;margin-top:3px">
					</div>
				</div>
			</div>

			<!-- Featured Image URL -->
			<div style="margin-bottom:14px">
				<label style="font-size:12px;font-weight:700;color:#444;display:block;margin-bottom:4px">Featured Image URL <span style="font-weight:400;color:#aaa">(apply same image to all)</span></label>
				<input type="url" id="be-feat-img" placeholder="https://labsglass.com/wp-content/uploads/…" style="width:100%;border:1.5px solid #ddd;border-radius:6px;padding:8px 12px;font-size:13px;box-sizing:border-box">
			</div>

			<!-- Gallery URLs -->
			<div style="margin-bottom:14px">
				<label style="font-size:12px;font-weight:700;color:#444;display:block;margin-bottom:4px">Gallery Image URLs <span style="font-weight:400;color:#aaa">(same gallery → all selected products)</span></label>
				<textarea id="be-gallery" rows="3" placeholder="One URL per line or comma separated" style="width:100%;border:1.5px solid #ddd;border-radius:6px;padding:8px 12px;font-size:12px;box-sizing:border-box;resize:vertical"></textarea>
			</div>

			<!-- Short Description -->
			<div style="margin-bottom:14px">
				<label style="font-size:12px;font-weight:700;color:#444;display:block;margin-bottom:4px">Short Description <span style="font-weight:400;color:#aaa">(replace in all)</span></label>
				<textarea id="be-short-desc" rows="3" placeholder="Leave blank to skip" style="width:100%;border:1.5px solid #ddd;border-radius:6px;padding:8px 12px;font-size:13px;box-sizing:border-box;resize:vertical"></textarea>
			</div>

			<!-- Append to Description -->
			<div style="margin-bottom:6px">
				<label style="font-size:12px;font-weight:700;color:#444;display:block;margin-bottom:4px">Append to Main Description <span style="font-weight:400;color:#aaa">(add at end, don't replace)</span></label>
				<textarea id="be-desc-append" rows="3" placeholder="e.g. Manufactured at our ISO 9001:2015 certified facility in Ambala, India." style="width:100%;border:1.5px solid #ddd;border-radius:6px;padding:8px 12px;font-size:13px;box-sizing:border-box;resize:vertical"></textarea>
			</div>

			<div id="be-notice" style="display:none;padding:10px 14px;border-radius:6px;font-size:13px;margin-top:12px"></div>

			<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:18px">
				<button class="sgc-pm-btn secondary pm-modal-close" data-modal="modal-bulk-edit">Cancel</button>
				<button class="sgc-pm-btn primary" id="btn-do-bulk-edit" style="min-width:130px">✏️ Apply to All</button>
			</div>
		</div>
	</div>
</div>

<script>
(function($){

// ── Quick Edit Drawer ─────────────────────────────────────────────────────
function openQEDrawer(productId) {
	$('#pm-qe-id').val(productId);
	$('#pm-qe-product-name').text('Loading…');
	$('#pm-qe-notice').hide();
	$('#pm-qe-gallery-current').empty();
	$('#pm-qe-feat-preview').hide();

	// Show drawer + overlay.
	$('#pm-qe-overlay,#pm-qe-drawer').show();

	// Load product data.
	$.post(SGCPM.ajaxUrl, {
		action: 'sgc_pm_quick_edit_load',
		nonce: SGCPM.nonce,
		product_id: productId
	}, function(r) {
		if (!r.success) { sgcNotice(r.data || 'Error loading product.', 'error'); return; }
		var d = r.data;
		$('#pm-qe-product-name').text(d.name);
		$('#pm-qe-edit-link').attr('href', d.edit_url);
		$('#pm-qe-name').val(d.name);
		$('#pm-qe-sku').val(d.sku);
		$('#pm-qe-reg-price').val(d.regular_price);
		$('#pm-qe-sale-price').val(d.sale_price);
		$('#pm-qe-short-desc').val(d.short_description.replace(/<[^>]+>/g,''));
		$('#pm-qe-desc').val(d.description.replace(/<[^>]+>/g,''));
		$('#pm-qe-feat-img').val(d.featured_image_url);

		// Featured image preview.
		if (d.featured_image_url) {
			$('#pm-qe-feat-img-preview').attr('src', d.featured_image_url);
			$('#pm-qe-feat-preview').show();
		}

		// Gallery URLs in textarea + thumbnail strip.
		if (d.gallery_urls && d.gallery_urls.length) {
			$('#pm-qe-gallery').val(d.gallery_urls.join('\n'));
			d.gallery_urls.forEach(function(url){
				$('#pm-qe-gallery-current').append(
					'<img src="'+url+'" style="height:48px;width:48px;object-fit:contain;border:1px solid #eee;border-radius:4px" title="'+url+'">'
				);
			});
		} else {
			$('#pm-qe-gallery').val('');
		}
	});
}

function closeQEDrawer() {
	$('#pm-qe-overlay,#pm-qe-drawer').hide();
}

// Open via row button.
$(document).on('click', '.pm-qe-btn', function(e){
	e.stopPropagation();
	openQEDrawer($(this).data('id'));
});

// Close drawer.
$('#pm-qe-close, #pm-qe-close-btn, #pm-qe-overlay').on('click', closeQEDrawer);

// Featured image URL → live preview.
$('#pm-qe-feat-img').on('input', function(){
	var url = $(this).val().trim();
	if (url) {
		$('#pm-qe-feat-img-preview').attr('src', url);
		$('#pm-qe-feat-preview').show();
	} else {
		$('#pm-qe-feat-preview').hide();
	}
});

// Save Quick Edit.
$('#pm-qe-save').on('click', function(){
	var $b = $(this).text('Saving…').prop('disabled', true);
	var $n = $('#pm-qe-notice');

	$.post(SGCPM.ajaxUrl, {
		action              : 'sgc_pm_quick_edit_save',
		nonce               : SGCPM.nonce,
		product_id          : $('#pm-qe-id').val(),
		name                : $('#pm-qe-name').val(),
		sku                 : $('#pm-qe-sku').val(),
		regular_price       : $('#pm-qe-reg-price').val(),
		sale_price          : $('#pm-qe-sale-price').val(),
		short_description   : $('#pm-qe-short-desc').val(),
		description         : $('#pm-qe-desc').val(),
		featured_image_url  : $('#pm-qe-feat-img').val().trim(),
		gallery_urls        : $('#pm-qe-gallery').val().trim()
	}, function(r) {
		$b.text('💾 Save Changes').prop('disabled', false);
		if (r.success) {
			$n.text('✅ ' + r.data.message).css({background:'#e8f5e9',color:'#2e7d32'}).show();
			sgcNotice(r.data.message);
			PM.load();
			setTimeout(closeQEDrawer, 1200);
		} else {
			$n.text('❌ ' + (r.data || 'Save failed')).css({background:'#ffebee',color:'#c62828'}).show();
		}
	}).fail(function(){
		$b.text('💾 Save Changes').prop('disabled', false);
		$n.text('❌ Network error').css({background:'#ffebee',color:'#c62828'}).show();
	});
});

// ── Bulk Edit Modal ───────────────────────────────────────────────────────
$(document).on('click', '#pm-bulk-edit-btn', function(){
	var n = window.sgcPMSelectedIds.size;
	if (!n) { sgcNotice('Select products first.', 'error'); return; }
	$('#bulk-edit-count').text(n);
	// Clear fields.
	$('#be-name-prefix,#be-name-suffix,#be-feat-img,#be-gallery,#be-short-desc,#be-desc-append').val('');
	$('#be-notice').hide();
	openModal('modal-bulk-edit');
});

$(document).on('click', '#btn-do-bulk-edit', function(){
	var ids = Array.from(window.sgcPMSelectedIds);
	if (!ids.length) { sgcNotice('No products selected.', 'error'); return; }

	var $b = $(this).text('Applying…').prop('disabled', true);
	var $n = $('#be-notice');

	$.post(SGCPM.ajaxUrl, {
		action             : 'sgc_pm_bulk_edit',
		nonce              : SGCPM.nonce,
		ids                : ids,
		name_prefix        : $('#be-name-prefix').val(),
		name_suffix        : $('#be-name-suffix').val(),
		featured_image_url : $('#be-feat-img').val().trim(),
		gallery_urls       : $('#be-gallery').val().trim(),
		short_description  : $('#be-short-desc').val(),
		desc_append        : $('#be-desc-append').val()
	}, function(r) {
		$b.text('✏️ Apply to All').prop('disabled', false);
		if (r.success) {
			$n.text('✅ ' + r.data.message).css({background:'#e8f5e9',color:'#2e7d32'}).show();
			sgcNotice(r.data.message);
			setTimeout(function(){
				closeModal('modal-bulk-edit');
				window.sgcPMSelectedIds.clear();
				PM.load();
			}, 1000);
		} else {
			$n.text('❌ ' + (r.data || 'Error')).css({background:'#ffebee',color:'#c62828'}).show();
		}
	});
});

})(jQuery);
</script>
