<?php
if (!defined('ABSPATH')) exit;

// ============================================================
// AI IMAGE ALT TEXT BULK GENERATOR
// SGC LABS® Manager Pro v6.7.0
// AI se product images ka ALT text auto-generate karo
// ============================================================

// ── AJAX: AI Bulk ALT Text Generate ─────────────────────────
add_action('wp_ajax_sgclabs_ai_bulk_alt', 'sgclabs_ai_bulk_alt_handler');
function sgclabs_ai_bulk_alt_handler() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $offset     = intval($_POST['offset'] ?? 0);
    $batch      = intval($_POST['batch'] ?? 5);
    $only_blank = (sanitize_key($_POST['only_blank'] ?? '1') !== '0');
    $batch      = max(1, min($batch, 20));

    global $wpdb;

    if ($only_blank) {
        $products = $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_thumbnail_id'
             LEFT JOIN {$wpdb->postmeta} done ON done.post_id = pm.meta_value AND done.meta_key = '_sgclabs_ai_alt_done'
             WHERE p.post_type = 'product' AND p.post_status = 'publish'
             AND done.meta_value IS NULL
             ORDER BY p.ID ASC LIMIT %d OFFSET %d",
            $batch, $offset
        ));
    } else {
        $products = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type='product' AND post_status='publish'
             ORDER BY ID ASC LIMIT %d OFFSET %d",
            $batch, $offset
        ));
    }

    $total = (int)$wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'"
    );

    if (empty($products)) {
        wp_send_json_success([
            'done'    => true,
            'message' => '✅ Sab products complete!',
            'total'   => $total,
        ]);
    }

    $updated = 0;
    $results = [];

    foreach ($products as $pid) {
        $product = wc_get_product($pid);
        if (!$product) continue;

        $product_name = $product->get_name();
        $sku          = $product->get_sku();
        $cats         = wp_get_post_terms($pid, 'product_cat', ['fields' => 'names']);
        $cat_str      = !empty($cats) ? implode(', ', array_slice($cats, 0, 2)) : 'Laboratory Equipment';
        $thumb_id     = get_post_thumbnail_id($pid);

        if (!$thumb_id) continue;

        // AI se ALT text generate karo
        $prompt = "Generate a concise, SEO-optimized image ALT text for this laboratory glassware product image.
Product: {$product_name}
SKU: {$sku}
Category: {$cat_str}
Brand: SGC LABS India

Rules:
- Max 125 characters
- Include product name + key spec
- End with 'SGC LABS' or 'labsglass.com'
- No quotes, no punctuation at end
- Example: Borosilicate Round Bottom Flask 250ml Laboratory Grade | SGC LABS India

Return ONLY the ALT text, nothing else.";

        $alt = sgclabs_ai_call($prompt, 80, 'You generate concise SEO image ALT texts for laboratory products.');

        if (is_wp_error($alt) || empty($alt)) {
            // Fallback: pattern se banao
            $alt = trim("{$product_name} | SGC LABS India");
            if (strlen($alt) > 125) $alt = substr($alt, 0, 122) . '...';
        } else {
            $alt = trim(strip_tags($alt));
            $alt = preg_replace('/^["\'`]|["\'`]$/', '', $alt);
            if (strlen($alt) > 125) $alt = substr($alt, 0, 122) . '...';
        }

        // Save ALT text to attachment
        update_post_meta($thumb_id, '_wp_attachment_image_alt', $alt);

        // Gallery images bhi update karo
        $gallery_ids = $product->get_gallery_image_ids();
        foreach ($gallery_ids as $gid) {
            $gallery_alt = preg_replace('/SGC LABS India$/', '', $alt);
            $gallery_alt = trim($gallery_alt) . ' — SGC LABS';
            if (strlen($gallery_alt) > 125) $gallery_alt = substr($gallery_alt, 0, 122) . '...';
            update_post_meta($gid, '_wp_attachment_image_alt', $gallery_alt);
        }

        // Mark done
        update_post_meta($thumb_id, '_sgclabs_ai_alt_done', '1');

        $results[] = [
            'id'   => $pid,
            'name' => substr($product_name, 0, 40),
            'alt'  => $alt,
        ];
        $updated++;
    }

    wp_send_json_success([
        'done'    => false,
        'updated' => $updated,
        'results' => $results,
        'next'    => $offset + $batch,
        'total'   => $total,
    ]);
}

// ── AJAX: Single product ALT text ───────────────────────────
add_action('wp_ajax_sgclabs_ai_single_alt', 'sgclabs_ai_single_alt_handler');
function sgclabs_ai_single_alt_handler() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('edit_products')) wp_send_json_error('Unauthorized');

    $pid = intval($_POST['post_id'] ?? 0);
    if (!$pid) wp_send_json_error('No product ID');

    $product = wc_get_product($pid);
    if (!$product) wp_send_json_error('Product not found');

    $thumb_id = get_post_thumbnail_id($pid);
    if (!$thumb_id) wp_send_json_error('No featured image');

    $product_name = $product->get_name();
    $sku          = $product->get_sku();
    $cats         = wp_get_post_terms($pid, 'product_cat', ['fields' => 'names']);
    $cat_str      = !empty($cats) ? implode(', ', array_slice($cats, 0, 2)) : 'Laboratory Equipment';

    $prompt = "Generate SEO image ALT text for: {$product_name} (SKU: {$sku}, Category: {$cat_str}) by SGC LABS India. Max 125 chars. Return ONLY the ALT text.";

    $alt = sgclabs_ai_call($prompt, 60, 'You generate concise SEO image ALT texts.');
    if (is_wp_error($alt) || empty($alt)) {
        $alt = "{$product_name} | SGC LABS India";
    }
    $alt = trim(strip_tags($alt));
    if (strlen($alt) > 125) $alt = substr($alt, 0, 122) . '...';

    update_post_meta($thumb_id, '_wp_attachment_image_alt', $alt);
    update_post_meta($thumb_id, '_sgclabs_ai_alt_done', '1');

    wp_send_json_success(['alt' => $alt]);
}

// ── AJAX: Reset done flags ───────────────────────────────────
add_action('wp_ajax_sgclabs_ai_alt_reset', function() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb;
    $wpdb->delete($wpdb->postmeta, ['meta_key' => '_sgclabs_ai_alt_done']);
    wp_send_json_success('Reset done — ab sab products process honge');
});

// ── ADMIN PAGE ───────────────────────────────────────────────
add_action('sgclabs_render_image_seo_tab_ai_alt', 'sgclabs_ai_alt_admin_tab');
function sgclabs_ai_alt_admin_tab() {
    global $wpdb;
    $total    = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'");
    $done_ct  = (int)$wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_sgclabs_ai_alt_done'");
    $pct      = $total > 0 ? round($done_ct / $total * 100) : 0;
    $nonce    = wp_create_nonce('sgclabs_nonce');
    ?>
    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:12px;padding:24px;margin-top:20px;">

        <h3 style="margin:0 0 6px;color:#1a1a2e;font-size:16px;">🤖 AI Image ALT Text Bulk Generator</h3>
        <p style="color:#666;font-size:13px;margin:0 0 20px;">AI se har product ki featured image aur gallery images ka SEO-optimized ALT text generate karo.</p>

        <!-- Progress -->
        <div style="background:#f8f9fa;border-radius:8px;padding:16px;margin-bottom:20px;">
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                <span style="font-size:13px;font-weight:600;color:#1a1a2e;">Progress</span>
                <span style="font-size:13px;color:#666;" id="sgc-alt-progress-text"><?php echo $done_ct; ?>/<?php echo $total; ?> (<?php echo $pct; ?>%)</span>
            </div>
            <div style="background:#e0e0e0;border-radius:20px;height:8px;overflow:hidden;">
                <div id="sgc-alt-progress-bar" style="background:linear-gradient(90deg,#1565c0,#27ae60);height:100%;width:<?php echo $pct; ?>%;transition:width .4s;"></div>
            </div>
        </div>

        <!-- Controls -->
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
            <div>
                <label style="font-size:12px;font-weight:600;color:#333;display:block;margin-bottom:4px;">Batch Size</label>
                <select id="sgc-alt-batch" style="padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px;">
                    <option value="3">3 per minute (Safe)</option>
                    <option value="5" selected>5 per minute</option>
                    <option value="10">10 per minute (Fast)</option>
                </select>
            </div>
            <div style="display:flex;align-items:center;gap:6px;margin-top:18px;">
                <input type="checkbox" id="sgc-alt-only-blank" checked style="width:16px;height:16px;">
                <label for="sgc-alt-only-blank" style="font-size:13px;">Sirf blank ALT wale</label>
            </div>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button id="sgc-alt-start-btn" class="button button-primary"
                style="background:#1565c0;border-color:#1565c0;color:#fff;padding:8px 20px;border-radius:7px;font-size:13px;font-weight:600;"
                onclick="sgcAltStart()">
                🚀 Start Bulk Generate
            </button>
            <button id="sgc-alt-stop-btn" class="button" style="display:none;" onclick="sgcAltStop()">
                ⏹ Stop
            </button>
            <button class="button" onclick="sgcAltReset()" style="color:#c62828;border-color:#c62828;">
                🔄 Reset (Sab dobara process karo)
            </button>
        </div>

        <!-- Status -->
        <div id="sgc-alt-status" style="margin-top:16px;display:none;padding:12px;background:#f0f8ff;border:1px solid #bee3f8;border-radius:8px;font-size:13px;color:#2b6cb0;"></div>

        <!-- Live Results -->
        <div id="sgc-alt-results" style="margin-top:16px;max-height:300px;overflow-y:auto;display:none;">
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead>
                    <tr style="background:#f8f9fa;">
                        <th style="padding:8px;text-align:left;border-bottom:1px solid #e0e0e0;">Product</th>
                        <th style="padding:8px;text-align:left;border-bottom:1px solid #e0e0e0;">Generated ALT Text</th>
                    </tr>
                </thead>
                <tbody id="sgc-alt-results-body"></tbody>
            </table>
        </div>
    </div>

    <script>
    var sgcAltRunning = false;
    var sgcAltOffset  = 0;
    var sgcAltTotal   = 0;
    var sgcAltDone    = <?php echo $done_ct; ?>;

    function sgcAltStart() {
        sgcAltRunning = true;
        sgcAltOffset  = 0;
        document.getElementById('sgc-alt-start-btn').style.display = 'none';
        document.getElementById('sgc-alt-stop-btn').style.display  = 'inline-block';
        document.getElementById('sgc-alt-status').style.display    = 'block';
        document.getElementById('sgc-alt-results').style.display   = 'block';
        sgcAltRun();
    }

    function sgcAltStop() {
        sgcAltRunning = false;
        document.getElementById('sgc-alt-start-btn').style.display = 'inline-block';
        document.getElementById('sgc-alt-stop-btn').style.display  = 'none';
        document.getElementById('sgc-alt-status').textContent = '⏹ Stopped — Resume ke liye dobara Start karo';
    }

    function sgcAltRun() {
        if (!sgcAltRunning) return;

        var batch     = document.getElementById('sgc-alt-batch').value;
        var onlyBlank = document.getElementById('sgc-alt-only-blank').checked ? '1' : '0';

        document.getElementById('sgc-alt-status').textContent = '⏳ Processing offset ' + sgcAltOffset + '...';

        jQuery.post(ajaxurl, {
            action:     'sgclabs_ai_bulk_alt',
            nonce:      '<?php echo $nonce; ?>',
            offset:     sgcAltOffset,
            batch:      batch,
            only_blank: onlyBlank,
        }, function(res) {
            if (!res.success) {
                document.getElementById('sgc-alt-status').textContent = '❌ Error: ' + (res.data || 'Unknown');
                sgcAltStop();
                return;
            }

            var d = res.data;
            sgcAltTotal = d.total;

            if (d.done) {
                sgcAltDone = d.total;
                sgcAltUpdateProgress();
                document.getElementById('sgc-alt-status').textContent = '✅ Sab complete! ' + d.total + ' products done.';
                sgcAltStop();
                return;
            }

            // Results table update
            var tbody = document.getElementById('sgc-alt-results-body');
            (d.results || []).forEach(function(r) {
                var tr = document.createElement('tr');
                tr.innerHTML = '<td style="padding:6px 8px;border-bottom:1px solid #f0f0f0;color:#1a1a2e;font-weight:600;">' + r.name + '</td>'
                             + '<td style="padding:6px 8px;border-bottom:1px solid #f0f0f0;color:#555;">' + r.alt + '</td>';
                tbody.insertBefore(tr, tbody.firstChild);
            });

            sgcAltDone  += (d.updated || 0);
            sgcAltOffset = d.next;
            sgcAltUpdateProgress();

            document.getElementById('sgc-alt-status').textContent =
                '✅ ' + sgcAltDone + '/' + sgcAltTotal + ' done — Next batch in 2s...';

            setTimeout(sgcAltRun, 2000);

        }).fail(function() {
            document.getElementById('sgc-alt-status').textContent = '❌ Request failed — Retry ho raha hai...';
            setTimeout(sgcAltRun, 5000);
        });
    }

    function sgcAltUpdateProgress() {
        var pct = sgcAltTotal > 0 ? Math.round(sgcAltDone / sgcAltTotal * 100) : 0;
        document.getElementById('sgc-alt-progress-bar').style.width = pct + '%';
        document.getElementById('sgc-alt-progress-text').textContent = sgcAltDone + '/' + sgcAltTotal + ' (' + pct + '%)';
    }

    function sgcAltReset() {
        if (!confirm('Reset karega — sab products dobara process honge. Continue?')) return;
        jQuery.post(ajaxurl, {
            action: 'sgclabs_ai_alt_reset',
            nonce:  '<?php echo $nonce; ?>',
        }, function(res) {
            if (res.success) {
                sgcAltDone = 0;
                sgcAltUpdateProgress();
                document.getElementById('sgc-alt-results-body').innerHTML = '';
                alert('✅ Reset complete — ab Start karo');
            }
        });
    }
    </script>
    <?php
}

// ── Admin Menu ───────────────────────────────────────────────
add_action('admin_menu', function() {
    add_submenu_page(
        'sgclabs-manager',
        'AI Image ALT Text',
        '🖼️ AI Image ALT',
        'manage_options',
        'sgclabs-ai-image-alt',
        'sgclabs_ai_alt_admin_page_full'
    );
}, 23);

function sgclabs_ai_alt_admin_page_full() {
    echo '<div class="wrap">';
    echo '<h1 style="color:#1a1a2e;">🖼️ AI Image ALT Text Bulk Generator</h1>';
    sgclabs_ai_alt_admin_tab();
    echo '</div>';
}
