<?php
/**
 * SGC Site Manager Pro — Bulk SEO Health Dashboard
 * Admin page: SGC Manager → SEO Hub → Bulk SEO Health
 *
 * @package SGCLabs_Manager
 * @since   7.4.0
 */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'sgclabs_seo_health_menu', 98 );
function sgclabs_seo_health_menu() {
    add_submenu_page(
        'sgclabs-seo-hub',
        'Bulk SEO Health',
        '📊 SEO Health',
        'manage_options',
        'sgclabs-seo-health',
        'sgclabs_seo_health_page'
    );
}

function sgclabs_seo_health_page() {
    $total = wp_count_posts('product')->publish ?? 0;
    $nonce = wp_create_nonce('sgclabs_nonce');
    ?>
    <div class="wrap">
    <h1>📊 Bulk SEO Health Dashboard</h1>
    <p style="color:#666;max-width:700px;font-size:13px">
        Scan all <?php echo intval($total); ?> published products for SEO completeness.
        Score /100 per product — identifies missing titles, meta, FAQs, image alt, HS codes.
        Priority queue: worst-scoring products appear first for AI fix.
    </p>

    <!-- Summary cards -->
    <div id="seo-health-summary" style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:16px 0;max-width:800px">
      <div class="sgclabs-kpi-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px;text-align:center">
        <div id="sh-total" style="font-size:24px;font-weight:700;color:#1a1a2e">—</div>
        <div style="font-size:12px;color:#888;margin-top:4px">Total Scanned</div>
      </div>
      <div class="sgclabs-kpi-card" style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:16px;text-align:center">
        <div id="sh-critical" style="font-size:24px;font-weight:700;color:#dc2626">—</div>
        <div style="font-size:12px;color:#ef4444;margin-top:4px">Critical (&lt;40)</div>
      </div>
      <div class="sgclabs-kpi-card" style="background:#fffbeb;border:1px solid #fcd34d;border-radius:10px;padding:16px;text-align:center">
        <div id="sh-medium" style="font-size:24px;font-weight:700;color:#d97706">—</div>
        <div style="font-size:12px;color:#f59e0b;margin-top:4px">Needs Work (40-69)</div>
      </div>
      <div class="sgclabs-kpi-card" style="background:#f0fdf4;border:1px solid #86efac;border-radius:10px;padding:16px;text-align:center">
        <div id="sh-good" style="font-size:24px;font-weight:700;color:#16a34a">—</div>
        <div style="font-size:12px;color:#22c55e;margin-top:4px">Good (70+)</div>
      </div>
    </div>

    <!-- Controls -->
    <div style="display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap">
      <button class="button button-primary" id="btn-start-scan" onclick="sgcStartScan()">🔍 Start Full Scan</button>
      <button class="button" id="btn-bulk-faq" onclick="sgcBulkFAQ()" disabled>✨ AI FAQ — Fix All Critical</button>
      <select id="sh-filter" onchange="sgcFilterResults(this.value)" style="padding:6px 10px;border-radius:6px;border:1px solid #d1d5db;font-size:12px">
        <option value="all">Show all</option>
        <option value="critical">Critical only</option>
        <option value="medium">Needs work</option>
        <option value="good">Good</option>
      </select>
      <div id="sh-progress-wrap" style="display:none;flex:1;max-width:300px">
        <div style="height:6px;background:#e5e7eb;border-radius:3px;overflow:hidden">
          <div id="sh-progress-bar" style="height:100%;background:#6366f1;border-radius:3px;width:0%;transition:width .4s"></div>
        </div>
        <div id="sh-progress-text" style="font-size:11px;color:#888;margin-top:3px">Scanning…</div>
      </div>
    </div>

    <!-- Results table -->
    <table class="wp-list-table widefat fixed striped" id="sh-table" style="display:none">
      <thead>
        <tr>
          <th style="width:5%">Score</th>
          <th style="width:30%">Product</th>
          <th style="width:8%">SKU</th>
          <th style="width:37%">Issues to fix</th>
          <th style="width:10%">FAQ</th>
          <th style="width:10%">Actions</th>
        </tr>
      </thead>
      <tbody id="sh-tbody"></tbody>
    </table>

    <div id="sh-empty" style="display:none;padding:30px;text-align:center;color:#888;background:#f9fafb;border-radius:8px">
      No products match this filter.
    </div>
    </div>

    <script>
    var shAllResults = [];
    var shNonce = '<?php echo esc_js($nonce); ?>';
    var shTotal  = <?php echo intval($total); ?>;
    var shOffset = 0;
    var shScanning = false;

    function sgcStartScan() {
        shAllResults = []; shOffset = 0; shScanning = true;
        document.getElementById('sh-table').style.display = 'none';
        document.getElementById('sh-empty').style.display = 'none';
        document.getElementById('sh-tbody').innerHTML = '';
        document.getElementById('sh-progress-wrap').style.display = 'flex';
        document.getElementById('btn-start-scan').disabled = true;
        document.getElementById('btn-bulk-faq').disabled = true;
        updateSummary(0,0,0,0);
        sgcScanBatch();
    }

    function sgcScanBatch() {
        fetch(ajaxurl, {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'action=sgclabs_seo_health_scan&nonce='+shNonce+'&offset='+shOffset+'&batch_size=50'
        })
        .then(r=>r.json())
        .then(d=>{
            if (!d.success) { alert('Scan error: '+d.data); return; }
            var data = d.data;
            shAllResults = shAllResults.concat(data.results);
            shOffset = data.offset;
            var pct = Math.min(100, Math.round(shOffset/shTotal*100));
            document.getElementById('sh-progress-bar').style.width = pct+'%';
            document.getElementById('sh-progress-text').textContent = 'Scanned '+shOffset+' of '+shTotal+' products…';
            renderRows(data.results, true);
            calcSummary();
            if (data.has_more && shScanning) {
                setTimeout(sgcScanBatch, 300);
            } else {
                shScanning = false;
                document.getElementById('btn-start-scan').disabled = false;
                document.getElementById('btn-bulk-faq').disabled = false;
                document.getElementById('sh-progress-text').textContent = '✅ Scan complete — '+shAllResults.length+' products';
                document.getElementById('sh-table').style.display = '';
            }
        }).catch(e=>{console.error(e); document.getElementById('btn-start-scan').disabled=false;});
    }

    function renderRows(results, append) {
        var tbody = document.getElementById('sh-tbody');
        if (!append) tbody.innerHTML = '';
        document.getElementById('sh-table').style.display = '';
        results.forEach(function(p){
            var scoreColor = p.score<40?'#dc2626':p.score<70?'#d97706':'#16a34a';
            var scoreBg    = p.score<40?'#fef2f2':p.score<70?'#fffbeb':'#f0fdf4';
            var issues     = p.issues.slice(0,4).map(i=>'• '+i).join('<br>');
            var hasFaq     = p.issues.filter(x=>x.includes('FAQ')).length===0;
            var tr = document.createElement('tr');
            tr.dataset.priority = p.priority;
            tr.innerHTML =
                '<td><span style="display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:50%;background:'+scoreBg+';color:'+scoreColor+';font-weight:700;font-size:14px">'+p.score+'</span></td>'
                +'<td><a href="'+p.edit_url+'" target="_blank" style="font-weight:600;color:#1a1a2e;text-decoration:none">'+escH(p.name.substring(0,50))+'</a></td>'
                +'<td style="font-size:11px;font-family:monospace;color:#888">'+escH(p.sku)+'</td>'
                +'<td style="font-size:11px;color:#555;line-height:1.7">'+issues+'</td>'
                +'<td>'+(hasFaq?'<span style="color:#059669;font-size:11px;font-weight:600">✅ Has FAQs</span>':'<button class="button button-small" onclick="sgcGenOneFAQ('+p.id+', this)">✨ Gen FAQ</button>')+'</td>'
                +'<td><a href="'+p.edit_url+'" target="_blank" class="button button-small">✏ Edit</a></td>';
            tbody.appendChild(tr);
        });
    }

    function calcSummary() {
        var crit=0,med=0,good=0;
        shAllResults.forEach(function(p){ if(p.score<40)crit++; else if(p.score<70)med++; else good++; });
        updateSummary(shAllResults.length, crit, med, good);
    }
    function updateSummary(t,c,m,g) {
        document.getElementById('sh-total').textContent=t;
        document.getElementById('sh-critical').textContent=c;
        document.getElementById('sh-medium').textContent=m;
        document.getElementById('sh-good').textContent=g;
    }
    function sgcFilterResults(val) {
        var rows = document.querySelectorAll('#sh-tbody tr');
        var visible = 0;
        rows.forEach(function(r){ var show=val==='all'||r.dataset.priority===val; r.style.display=show?'':'none'; if(show)visible++; });
        document.getElementById('sh-empty').style.display = visible===0?'':'none';
    }
    function sgcGenOneFAQ(pid, btn) {
        btn.disabled=true; btn.textContent='⏳';
        fetch(ajaxurl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'action=sgclabs_generate_product_faq&product_id='+pid+'&nonce='+shNonce})
        .then(r=>r.json()).then(d=>{
            btn.parentNode.innerHTML=d.success?'<span style="color:#059669;font-size:11px">✅ Done</span>':'<span style="color:#dc2626;font-size:10px">Failed</span>';
        });
    }
    function sgcBulkFAQ() {
        var critical = shAllResults.filter(p=>p.score<40&&p.issues.some(i=>i.includes('FAQ')));
        if (!critical.length){alert('No critical products without FAQs found. Run scan first.');return;}
        if (!confirm('Generate AI FAQs for '+critical.length+' critical products? This may take a few minutes.')) return;
        var idx=0;
        function next() {
            if(idx>=critical.length){alert('✅ Done! '+critical.length+' products updated.');return;}
            var p=critical[idx++];
            fetch(ajaxurl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'action=sgclabs_generate_product_faq&product_id='+p.id+'&nonce='+shNonce})
            .then(r=>r.json()).then(d=>{
                document.getElementById('sh-progress-text').textContent='FAQ: '+idx+'/'+critical.length+' — '+escH(p.name.substring(0,30));
                setTimeout(next, 800);
            });
        }
        next();
    }
    function escH(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    </script>
    <?php
}
