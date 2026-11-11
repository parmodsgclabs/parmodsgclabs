<?php defined( 'ABSPATH' ) || exit; ?>
<div class="sgc-pm-tools-page">
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

<!-- Duplicate Product -->
<div class="sgc-pm-box">
	<h3 style="margin:0 0 6px;font-size:14px">📋 Duplicate Product</h3>
	<p style="font-size:12px;color:#888;margin:0 0 14px">Clone any product. SKU is cleared on copy — set a new one. Created as Draft.</p>
	<div class="sgc-pm-fg"><label>Product ID</label>
		<input type="number" id="dup-product-id" class="sgc-pm-input" placeholder="Enter product ID" style="width:100%" min="1">
		<small style="color:#888">Find ID in Products → hover Edit → see URL ?post=ID</small>
	</div>
	<button class="sgc-pm-btn primary" id="btn-duplicate">📋 Duplicate Product</button>
	<div id="dup-result" style="display:none;margin-top:12px;padding:10px;background:#e8f5e9;border-radius:6px;font-size:13px"></div>
</div>

<!-- Variant Creator -->
<div class="sgc-pm-box">
	<h3 style="margin:0 0 6px;font-size:14px">🔬 Variant Creator</h3>
	<p style="font-size:12px;color:#888;margin:0 0 14px">Create multiple size/volume variants of one product. All created as Draft.</p>
	<div class="sgc-pm-fg"><label>Base Product ID</label>
		<input type="number" id="var-base-id" class="sgc-pm-input" placeholder="Base product ID" style="width:100%" min="1">
	</div>
	<div id="var-rows">
		<div class="var-row" style="display:grid;grid-template-columns:1fr 80px 100px auto;gap:6px;margin-bottom:6px;align-items:center">
			<input type="text" class="sgc-pm-input var-label" placeholder="Label e.g. 100ml" style="font-size:12px">
			<input type="number" class="sgc-pm-input var-price" placeholder="Price" step="0.01" min="0" style="font-size:12px">
			<input type="text" class="sgc-pm-input var-sku" placeholder="SKU" style="font-size:12px">
			<button class="pm-remove-var sgc-pm-btn secondary" style="font-size:11px;padding:4px 8px">✕</button>
		</div>
	</div>
	<button class="sgc-pm-btn secondary" id="btn-add-var-row" style="font-size:12px;margin-bottom:12px">+ Add Variant</button>
	<br>
	<button class="sgc-pm-btn primary" id="btn-create-variants">🔬 Create Variants</button>
	<div id="var-result" style="display:none;margin-top:12px;padding:10px;background:#e8f5e9;border-radius:6px;font-size:13px"></div>
</div>

</div>
</div>

<!-- ══ CATEGORY FIXER ══════════════════════════════════════════════════ -->
<div class="sgc-pm-box" style="margin-top:16px" id="catfix-box">
	<h3 style="margin:0 0 4px;font-size:15px">📂 Category Fixer</h3>
	<p style="margin:0 0 16px;font-size:12px;color:#888">Galat category ke products dhundho → list dekho → manually select karo → ek click mein sahi category mein shift karo.</p>

	<?php
	$all_cats = get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'orderby'=>'name','order'=>'ASC','number'=>200]);
	?>

	<!-- Step 1 + 2 row -->
	<div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:14px">
		<div style="flex:1;min-width:180px">
			<label style="display:block;font-size:11px;font-weight:600;color:#555;margin-bottom:5px">Step 1 — Category choose karo (products dekhne hain jis mein)</label>
			<select id="catfix-from" class="sgc-pm-select" style="width:100%">
				<option value="">— Sab Products (no filter) —</option>
				<?php foreach($all_cats as $c): ?>
				<option value="<?php echo esc_attr($c->term_id); ?>"><?php echo esc_html($c->name); ?> (<?php echo (int)$c->count; ?> products)</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div>
			<button class="sgc-pm-btn primary" id="catfix-load-btn" style="white-space:nowrap;height:36px">Step 2 — Load Products ▶</button>
		</div>
		<div>
			<button class="sgc-pm-btn" id="catfix-ai-btn" style="white-space:nowrap;height:36px;background:#185FA5;color:#fff;border-color:#185FA5" disabled>🤖 AI Scan (optional)</button>
		</div>
	</div>

	<!-- AI Progress bar -->
	<div id="catfix-ai-prog" style="display:none;background:#E6F1FB;border-radius:5px;padding:10px;margin-bottom:12px">
		<div style="display:flex;justify-content:space-between;margin-bottom:6px">
			<span style="font-size:12px;color:#0C447C;font-weight:500">🤖 AI product names analyze kar raha hai…</span>
			<span style="font-size:12px;color:#185FA5;font-weight:600" id="catfix-ai-prog-txt">0%</span>
		</div>
		<div style="background:#B5D4F4;border-radius:4px;height:7px;overflow:hidden">
			<div id="catfix-ai-prog-bar" style="height:100%;width:0%;background:#185FA5;border-radius:4px;transition:width .5s"></div>
		</div>
	</div>

	<!-- Bulk action bar (shown when rows selected) -->
	<div id="catfix-bulk-bar" style="display:none;background:#1a1a2e;color:#fff;border-radius:6px;padding:9px 14px;margin-bottom:10px;align-items:center;gap:10px;flex-wrap:wrap">
		<span style="font-size:13px;font-weight:600" id="catfix-sel-count">0 selected</span>
		<span style="color:#666;font-size:14px">→</span>
		<span style="font-size:12px;color:#aaa">Move to:</span>
		<select id="catfix-to" style="padding:4px 8px;border-radius:4px;font-size:12px;border:none;background:#fff;color:#333;min-width:180px">
			<option value="">— Target category chuniye —</option>
			<?php foreach($all_cats as $c): ?>
			<option value="<?php echo esc_attr($c->term_id); ?>"><?php echo esc_html($c->name); ?></option>
			<?php endforeach; ?>
		</select>
		<button id="catfix-move-btn" style="padding:5px 14px;border-radius:4px;font-size:12px;font-weight:600;background:#3B6D11;color:#fff;border:none;cursor:pointer;white-space:nowrap">✅ Move — One Click</button>
		<button onclick="catfixClearSel()" style="margin-left:auto;padding:3px 9px;font-size:11px;border-radius:3px;border:0.5px solid rgba(255,255,255,.3);background:transparent;color:#fff;cursor:pointer">Clear</button>
	</div>

	<!-- Table toolbar -->
	<div id="catfix-toolbar" style="display:none;align-items:center;gap:8px;margin-bottom:6px;padding:4px 0">
		<input type="checkbox" id="catfix-chk-all" style="width:14px;height:14px" title="Sab select karo">
		<span style="font-size:12px;color:#888" id="catfix-total-lbl"></span>
		<button id="catfix-ai-flagged-btn" style="display:none;padding:3px 10px;font-size:11px;border-radius:3px;border:1px solid #185FA5;background:#E6F1FB;color:#0C447C;cursor:pointer">✓ Sirf AI Flagged Select Karo</button>
		<span style="margin-left:auto;font-size:12px;color:#888">Click row to select / deselect</span>
	</div>

	<!-- Product Table -->
	<div id="catfix-tbl-wrap" style="display:none;border:1px solid #e8e8e8;border-radius:6px;overflow:hidden;max-height:480px;overflow-y:auto">
		<table style="width:100%;border-collapse:collapse;font-size:12px">
			<thead>
				<tr style="background:#f8f8f8;position:sticky;top:0;z-index:2">
					<th style="padding:8px 10px;text-align:left;font-size:10px;color:#666;text-transform:uppercase;letter-spacing:.3px;width:30px">
						<input type="checkbox" id="catfix-chk-all2" style="width:14px;height:14px">
					</th>
					<th style="padding:8px 10px;text-align:left;font-size:10px;color:#666;text-transform:uppercase;letter-spacing:.3px">Product Name</th>
					<th style="padding:8px 10px;text-align:left;font-size:10px;color:#666;text-transform:uppercase;letter-spacing:.3px">SKU</th>
					<th style="padding:8px 10px;text-align:left;font-size:10px;color:#666;text-transform:uppercase;letter-spacing:.3px">Current Category</th>
					<th style="padding:8px 10px;text-align:left;font-size:10px;color:#666;text-transform:uppercase;letter-spacing:.3px">Price</th>
					<th style="padding:8px 10px;text-align:left;font-size:10px;color:#666;text-transform:uppercase;letter-spacing:.3px">🤖 AI Suggest</th>
				</tr>
			</thead>
			<tbody id="catfix-tbody"></tbody>
		</table>
	</div>

	<div id="catfix-notice" style="display:none;padding:10px 14px;border-radius:5px;font-size:12px;margin-top:10px"></div>
</div>

<script>
(function(){
	var cfProducts = [];
	var cfSelected = new Set();
	var cfAiResults = {};

	function cfNotice(msg, type){
		var n = document.getElementById('catfix-notice');
		n.textContent = msg;
		n.style.display = 'block';
		n.style.background = type === 'success' ? '#EAF3DE' : type === 'error' ? '#FCEBEB' : '#E6F1FB';
		n.style.color     = type === 'success' ? '#27500A' : type === 'error' ? '#791F1F' : '#0C447C';
		setTimeout(function(){ n.style.display = 'none'; }, 5000);
	}

	function cfRender(){
		if(!cfProducts.length){
			document.getElementById('catfix-tbody').innerHTML =
				'<tr><td colspan="6" style="padding:32px;text-align:center;color:#aaa">Is category mein koi product nahi mila.</td></tr>';
			return;
		}
		var html = '';
		cfProducts.forEach(function(p){
			var isSel = cfSelected.has(p.id);
			var ai    = cfAiResults[p.id];
			var aiCell = '<span style="color:#ccc;font-size:11px">—</span>';
			if(ai){
				if(ai.is_wrong){
					aiCell = '<span style="background:#FCEBEB;color:#791F1F;padding:1px 7px;border-radius:9px;font-size:10px;font-weight:500">Wrong</span>'
						+ ' <span style="font-size:11px;color:#888">→</span>'
						+ ' <strong style="color:#27500A;font-size:11px">' + (ai.suggested_category||'') + '</strong>';
				} else {
					aiCell = '<span style="background:#EAF3DE;color:#27500A;padding:1px 7px;border-radius:9px;font-size:10px;font-weight:500">Correct</span>';
				}
			}
			var bgColor = isSel ? '#EBF3FF' : (ai && ai.is_wrong ? '#FFFBF0' : 'transparent');
			html += '<tr data-id="' + p.id + '" style="border-bottom:0.5px solid #f2f2f2;cursor:pointer;background:' + bgColor + '">'
				+ '<td style="padding:7px 10px"><input type="checkbox" class="cf-row-chk" data-id="' + p.id + '"'
				+ (isSel ? ' checked' : '') + ' style="width:13px;height:13px"></td>'
				+ '<td style="padding:7px 10px;font-size:12px;font-weight:' + (isSel ? '500' : '400') + '">' + p.name + '</td>'
				+ '<td style="padding:7px 10px;font-family:monospace;font-size:10px;color:#aaa">' + (p.sku || '—') + '</td>'
				+ '<td style="padding:7px 10px"><span style="background:#FCEBEB;color:#791F1F;padding:1px 7px;border-radius:9px;font-size:10px;font-weight:500">'
				+ p.current_cat + '</span></td>'
				+ '<td style="padding:7px 10px;color:#888;font-size:12px">' + (p.price ? '&#8377;' + p.price : '—') + '</td>'
				+ '<td style="padding:7px 10px">' + aiCell + '</td>'
				+ '</tr>';
		});
		document.getElementById('catfix-tbody').innerHTML = html;
		cfUpdateBulkBar();
	}

	function cfUpdateBulkBar(){
		var n   = cfSelected.size;
		var bar = document.getElementById('catfix-bulk-bar');
		bar.style.display = n > 0 ? 'flex' : 'none';
		document.getElementById('catfix-sel-count').textContent = n + ' product' + (n === 1 ? '' : 's') + ' selected';
	}

	window.catfixClearSel = function(){
		cfSelected.clear();
		cfRender();
	};

	// Row click delegation
	document.getElementById('catfix-tbody').addEventListener('click', function(e){
		var tr = e.target.closest('tr[data-id]');
		if(!tr) return;
		var id = parseInt(tr.getAttribute('data-id'));
		var chk = tr.querySelector('.cf-row-chk');
		if(e.target === chk){
			chk.checked ? cfSelected.add(id) : cfSelected.delete(id);
		} else {
			cfSelected.has(id) ? cfSelected.delete(id) : cfSelected.add(id);
			if(chk) chk.checked = cfSelected.has(id);
		}
		tr.style.background = cfSelected.has(id) ? '#EBF3FF' : (cfAiResults[id] && cfAiResults[id].is_wrong ? '#FFFBF0' : 'transparent');
		cfUpdateBulkBar();
	});

	// Select all checkbox (both)
	function bindSelectAll(id){
		var el = document.getElementById(id);
		if(!el) return;
		el.addEventListener('change', function(){
			cfProducts.forEach(function(p){ this.checked ? cfSelected.add(p.id) : cfSelected.delete(p.id); }.bind(el));
			cfRender();
		});
	}
	bindSelectAll('catfix-chk-all');
	bindSelectAll('catfix-chk-all2');

	// AI Flagged only
	document.getElementById('catfix-ai-flagged-btn').addEventListener('click', function(){
		cfSelected.clear();
		cfProducts.forEach(function(p){ if(cfAiResults[p.id] && cfAiResults[p.id].is_wrong) cfSelected.add(p.id); });
		cfRender();
		cfNotice('AI flagged products select ho gaye. Ab "Move — One Click" karo.', 'info');
	});

	// LOAD PRODUCTS
	document.getElementById('catfix-load-btn').addEventListener('click', function(){
		var fromCat = document.getElementById('catfix-from').value;
		var $b = this;
		$b.disabled = true;
		$b.textContent = 'Loading…';

		var data = new FormData();
		data.append('action',   'sgc_pm_catfix_load');
		data.append('nonce',    SGCPM.nonce);
		data.append('from_cat', fromCat);

		jQuery.post(SGCPM.ajaxUrl, {
			action   : 'sgc_pm_catfix_load',
			nonce    : SGCPM.nonce,
			from_cat : fromCat
		}, function(r){
			$b.disabled  = false;
			$b.textContent = 'Step 2 — Load Products ▶';

			if(!r.success){
				cfNotice(r.data || 'Error loading products.', 'error');
				return;
			}

			cfProducts   = r.data.products || [];
			cfSelected   = new Set();
			cfAiResults  = {};

			document.getElementById('catfix-tbl-wrap').style.display = 'block';
			document.getElementById('catfix-toolbar').style.display  = 'flex';
			document.getElementById('catfix-total-lbl').textContent  = cfProducts.length + ' products loaded';
			document.getElementById('catfix-ai-btn').disabled        = false;
			document.getElementById('catfix-ai-flagged-btn').style.display = 'none';

			cfRender();

			if(cfProducts.length === 0){
				cfNotice('Is category mein koi product nahi mila.', 'info');
			} else {
				cfNotice(cfProducts.length + ' products load ho gaye. Rows click karo ya checkboxes use karo select karne ke liye.', 'info');
			}
		}).fail(function(){
			$b.disabled  = false;
			$b.textContent = 'Step 2 — Load Products ▶';
			cfNotice('Server error. Please try again.', 'error');
		});
	});

	// MOVE SELECTED
	document.getElementById('catfix-move-btn').addEventListener('click', function(){
		var ids   = Array.from(cfSelected);
		var toCat = document.getElementById('catfix-to').value;
		var toTxt = document.getElementById('catfix-to').options[document.getElementById('catfix-to').selectedIndex].text;

		if(!ids.length){ cfNotice('Koi product select nahi kiya.', 'error'); return; }
		if(!toCat)     { cfNotice('Target category chuniye pehle.', 'error'); return; }
		if(!confirm(ids.length + ' products ko "' + toTxt + '" mein move karein?')) return;

		var $b = this;
		$b.disabled    = true;
		$b.textContent = 'Moving…';

		jQuery.post(SGCPM.ajaxUrl, {
			action : 'sgc_pm_bulk_reassign_category',
			nonce  : SGCPM.nonce,
			ids    : ids,
			cat_id : toCat,
			append : '0'
		}, function(r){
			$b.disabled    = false;
			$b.textContent = '✅ Move — One Click';

			if(r.success){
				// Remove moved products from list
				var moved = new Set(ids);
				cfProducts  = cfProducts.filter(function(p){ return !moved.has(p.id); });
				cfSelected  = new Set();
				document.getElementById('catfix-total-lbl').textContent = cfProducts.length + ' products';
				cfRender();
				cfNotice(r.data.message || 'Done!', 'success');
			} else {
				cfNotice(r.data || 'Error.', 'error');
			}
		}).fail(function(){
			$b.disabled    = false;
			$b.textContent = '✅ Move — One Click';
			cfNotice('Server error.', 'error');
		});
	});

	// AI AUTO-SCAN
	document.getElementById('catfix-ai-btn').addEventListener('click', function(){
		if(!cfProducts.length){ cfNotice('Pehle products load karo.', 'error'); return; }

		var cats = [];
		document.querySelectorAll('#catfix-to option[value]').forEach(function(o){
			if(o.value) cats.push(o.textContent.replace(/\s*\(\d+\)$/, '').trim());
		});
		var availCats = cats.join(', ');

		var $b = this;
		$b.disabled    = true;
		$b.textContent = 'Scanning…';

		var progBox = document.getElementById('catfix-ai-prog');
		progBox.style.display = 'block';
		document.getElementById('catfix-ai-prog-bar').style.width = '0%';
		document.getElementById('catfix-ai-prog-txt').textContent = '0%';

		var batchSize = 30;
		var batches   = [];
		for(var i = 0; i < cfProducts.length; i += batchSize){
			batches.push(cfProducts.slice(i, i + batchSize));
		}

		var done = 0;
		function runBatch(idx){
			if(idx >= batches.length){
				progBox.style.display = 'none';
				$b.disabled    = false;
				$b.textContent = '🤖 AI Scan (optional)';
				document.getElementById('catfix-ai-flagged-btn').style.display = 'inline-block';
				var wrong = Object.values(cfAiResults).filter(function(r){ return r.is_wrong; }).length;
				cfRender();
				cfNotice('AI scan done! ' + wrong + ' products galat category mein milein. "Sirf AI Flagged Select Karo" button use karo.', wrong > 0 ? 'info' : 'success');
				return;
			}
			var batch = batches[idx].map(function(p){
				return {id: p.id, name: p.name, current_cat: p.current_cat};
			});
			jQuery.post(SGCPM.ajaxUrl, {
				action               : 'sgc_pm_ai_suggest_categories',
				nonce                : SGCPM.nonce,
				products             : batch,
				available_categories : availCats
			}, function(r){
				if(r.success && Array.isArray(r.data)){
					r.data.forEach(function(item){ cfAiResults[item.id] = item; });
				}
				done += batches[idx].length;
				var pct = Math.round(done / cfProducts.length * 100);
				document.getElementById('catfix-ai-prog-bar').style.width = pct + '%';
				document.getElementById('catfix-ai-prog-txt').textContent = pct + '%';
				cfRender();
				setTimeout(function(){ runBatch(idx + 1); }, 600);
			}).fail(function(){
				setTimeout(function(){ runBatch(idx + 1); }, 1000);
			});
		}
		runBatch(0);
	});

})();
</script>
