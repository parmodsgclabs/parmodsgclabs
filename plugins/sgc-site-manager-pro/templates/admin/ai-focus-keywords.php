<?php
/**
 * Template: AI Focus Keywords Admin Page
 *
 * @var int    $total
 * @var int    $with_kw
 * @var int    $pending
 * @var int    $percent
 * @var array  $products
 * @var bool   $has_ai
 * @var string $nonce
 * @var string $brand
 * @var string $accent
 *
 * @package SGCLabs_Manager
 * @since   6.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap sgclabs-wrap" style="max-width:1200px;margin:20px auto;">
    <!-- HEADER -->
    <div style="background:linear-gradient(135deg,<?php echo esc_attr( $brand ); ?>,<?php echo esc_attr( $accent ); ?>);padding:30px;border-radius:12px;margin-bottom:24px;">
        <h1 style="color:#fff;margin:0;font-size:28px;">🎯 AI Focus Keywords</h1>
        <p style="color:rgba(255,255,255,.85);margin:8px 0 0;font-size:14px;">
            Slug + Canonical URL + Product Title → AI-powered focus keyword generation.
            <?php if ( $has_ai ) : ?>
                <span style="background:rgba(255,255,255,.2);padding:2px 8px;border-radius:4px;">🟢 AI Active</span>
            <?php else : ?>
                <span style="background:rgba(255,100,100,.3);padding:2px 8px;border-radius:4px;">⚠️ No AI Key — Fallback Mode</span>
            <?php endif; ?>
        </p>
    </div>

    <!-- PROGRESS STATS -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px;">
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:20px;text-align:center;">
            <div style="font-size:32px;font-weight:bold;color:<?php echo esc_attr( $accent ); ?>;" id="stat-total"><?php echo number_format( $total ); ?></div>
            <div style="font-size:12px;color:#666;margin-top:4px;">Total Products</div>
        </div>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:20px;text-align:center;">
            <div style="font-size:32px;font-weight:bold;color:#27ae60;" id="stat-done"><?php echo number_format( $with_kw ); ?></div>
            <div style="font-size:12px;color:#666;margin-top:4px;">With Keywords ✅</div>
        </div>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:20px;text-align:center;">
            <div style="font-size:32px;font-weight:bold;color:#f97316;" id="stat-pending"><?php echo number_format( $pending ); ?></div>
            <div style="font-size:12px;color:#666;margin-top:4px;">Pending</div>
        </div>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:20px;text-align:center;">
            <div style="font-size:32px;font-weight:bold;color:#3b82f6;" id="stat-pct"><?php echo $percent; ?>%</div>
            <div style="font-size:12px;color:#666;margin-top:4px;">Complete</div>
        </div>
    </div>

    <!-- PROGRESS BAR -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:20px;margin-bottom:20px;">
        <div style="height:8px;background:#e5e7eb;border-radius:4px;overflow:hidden;">
            <div id="fk-progress" style="height:100%;width:<?php echo $percent; ?>%;background:linear-gradient(90deg,<?php echo esc_attr( $accent ); ?>,<?php echo esc_attr( $brand ); ?>);transition:width 0.3s;"></div>
        </div>
    </div>

    <!-- BULK GENERATION -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;margin-bottom:24px;">
        <h2 style="margin:0 0 16px;">⚡ Bulk Generation</h2>
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <button id="fk-bulk-start" class="button button-primary button-large" style="background:<?php echo esc_attr( $accent ); ?>;border-color:<?php echo esc_attr( $accent ); ?>;font-weight:bold;">
                ▶️ Start Bulk Generation (<?php echo $has_ai ? 'AI' : 'Fallback'; ?> Mode)
            </button>
            <button id="fk-bulk-pause" class="button button-secondary button-large" style="display:none;">⏸️ Pause</button>
            <button id="fk-bulk-resume" class="button button-secondary button-large" style="display:none;">▶️ Resume</button>
            <label style="font-size:13px;">
                <input type="checkbox" id="fk-use-ai" <?php checked( $has_ai ); ?> <?php disabled( ! $has_ai ); ?>>
                Use AI (slower but smarter)
            </label>
        </div>
        <div id="fk-bulk-status" style="margin-top:12px;padding:12px;background:#f0fdf4;border:1px solid #86efac;border-radius:8px;display:none;color:#166534;">
            <span id="fk-bulk-text">Ready...</span>
        </div>
    </div>

    <!-- SINGLE PRODUCT GENERATOR + EDIT/PREVIEW -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;margin-bottom:24px;">
        <h2 style="margin:0 0 16px;">🔍 Single Product — Generate + Edit + Preview</h2>

        <!-- Product Selector -->
        <div style="display:flex;gap:12px;margin-bottom:16px;align-items:end;">
            <div style="flex:1;">
                <label style="font-weight:600;display:block;margin-bottom:4px;">Select Product</label>
                <select id="fk-product-select" class="large-text" style="height:36px;">
                    <option value="">— Select a product —</option>
                    <?php foreach ( $products as $p ) : ?>
                        <option value="<?php echo intval( $p->ID ); ?>"
                                data-slug="<?php echo esc_attr( $p->post_name ); ?>"
                                data-kw="<?php echo esc_attr( $p->focus_kw ); ?>">
                            <?php echo esc_html( $p->post_title ); ?>
                            <?php echo $p->focus_kw ? ' ✅' : ' ⏳'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button id="fk-generate-single" class="button button-primary" style="height:36px;background:<?php echo esc_attr( $accent ); ?>;border-color:<?php echo esc_attr( $accent ); ?>;">
                🎯 Generate Keyword
            </button>
        </div>

        <!-- RESULT + EDITOR (hidden initially) -->
        <div id="fk-editor" style="display:none;">
            <!-- Generated Info -->
            <div id="fk-method-badge" style="margin-bottom:12px;font-size:12px;color:#666;"></div>

            <!-- Editable Primary Keyword -->
            <div style="margin-bottom:16px;">
                <label style="font-weight:600;display:block;margin-bottom:4px;">Primary Focus Keyword</label>
                <input type="text" id="fk-primary" class="large-text" style="font-size:16px;font-weight:bold;padding:10px;">
            </div>

            <!-- Editable LSI Keywords -->
            <div style="margin-bottom:16px;">
                <label style="font-weight:600;display:block;margin-bottom:4px;">LSI Keywords (comma-separated)</label>
                <textarea id="fk-lsi" class="large-text" rows="3" style="font-size:13px;padding:10px;"></textarea>
                <p class="description">Edit, add or remove keywords. Separate with commas.</p>
            </div>

            <!-- LIVE SERP PREVIEW -->
            <div style="margin-bottom:16px;padding:20px;background:#fff;border:2px solid #e2e8f0;border-radius:8px;">
                <h3 style="margin:0 0 12px;font-size:14px;color:#999;">👁️ Google Search Preview</h3>
                <div style="font-family:Arial,sans-serif;max-width:600px;">
                    <div id="serp-url" style="font-size:14px;color:#202124;margin-bottom:2px;"></div>
                    <div id="serp-title" style="font-size:20px;color:#1a0dab;margin-bottom:4px;cursor:pointer;line-height:1.3;"></div>
                    <div id="serp-desc" style="font-size:14px;color:#4d5156;line-height:1.5;"></div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display:flex;gap:12px;">
                <button id="fk-save" class="button button-primary button-large" style="background:<?php echo esc_attr( $accent ); ?>;border-color:<?php echo esc_attr( $accent ); ?>;font-weight:bold;">
                    💾 Save Keyword
                </button>
                <button id="fk-regenerate" class="button button-secondary button-large">🔄 Regenerate</button>
                <button id="fk-skip" class="button button-large">⏭️ Skip</button>
            </div>
            <div id="fk-save-status" style="margin-top:8px;display:none;"></div>
        </div>
    </div>

    <!-- PRODUCTS TABLE -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;">
        <h2 style="margin:0 0 16px;">📋 Products & Keywords (Recent 50)</h2>
        <table class="widefat striped" style="border-radius:8px;overflow:hidden;">
            <thead>
                <tr><th>ID</th><th>Product</th><th>Slug</th><th>Focus Keyword</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ( $products as $p ) : ?>
                <tr>
                    <td><?php echo intval( $p->ID ); ?></td>
                    <td><?php echo esc_html( $p->post_title ); ?></td>
                    <td style="font-size:12px;color:#888;"><?php echo esc_html( $p->post_name ); ?></td>
                    <td>
                        <?php if ( $p->focus_kw ) : ?>
                            <span style="background:#d4edda;color:#155724;padding:2px 8px;border-radius:4px;font-size:12px;">
                                <?php echo esc_html( $p->focus_kw ); ?>
                            </span>
                        <?php else : ?>
                            <span style="color:#999;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="button fk-gen-row" data-id="<?php echo intval( $p->ID ); ?>" style="font-size:11px;">🎯 Generate</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function($) {
    const nonce = '<?php echo esc_js( $nonce ); ?>';
    const ajaxurl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
    let bulkRunning = false, bulkPaused = false;
    let currentProductId = 0;

    // Highlight keyword in text
    function highlightKw(text, kw) {
        if (!kw) return text;
        const re = new RegExp('(' + kw.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
        return text.replace(re, '<strong style="color:#1a1a2e;">$1</strong>');
    }

    // Update SERP preview
    function updateSerp(serp, keyword) {
        if (!serp) return;
        $('#serp-url').html(serp.url || '');
        $('#serp-title').html(highlightKw(serp.title || '', keyword));
        $('#serp-desc').html(highlightKw(serp.desc || '', keyword));
    }

    // Primary keyword field → live update SERP
    $('#fk-primary').on('input', function() {
        const kw = $(this).val();
        const title = $('#serp-title').text();
        const desc = $('#serp-desc').text();
        $('#serp-title').html(highlightKw(title, kw));
        $('#serp-desc').html(highlightKw(desc, kw));
    });

    // Generate single
    function generateSingle(productId) {
        currentProductId = productId;
        const useAI = $('#fk-use-ai').is(':checked') ? '1' : '0';

        $('#fk-editor').hide();
        $('#fk-generate-single').prop('disabled', true).text('⏳ Generating...');

        $.post(ajaxurl, {
            action: 'sgclabs_ai_fk_generate_single',
            nonce: nonce,
            product_id: productId,
            use_ai: useAI
        }, function(res) {
            $('#fk-generate-single').prop('disabled', false).text('🎯 Generate Keyword');

            if (!res.success) {
                alert(res.data || 'Error generating keyword');
                return;
            }

            const d = res.data;
            $('#fk-primary').val(d.primary);
            $('#fk-lsi').val((d.lsi || []).join(', '));
            $('#fk-method-badge').html(
                '📌 Method: <strong>' + d.method + '</strong>' +
                (d.provider ? ' via ' + d.provider : '') +
                ' | Product #' + d.product_id
            );
            updateSerp(d.serp, d.primary);
            $('#fk-editor').slideDown(200);
        }).fail(function() {
            $('#fk-generate-single').prop('disabled', false).text('🎯 Generate Keyword');
            alert('AJAX Error');
        });
    }

    $('#fk-generate-single').on('click', function(e) {
        e.preventDefault();
        const pid = $('#fk-product-select').val();
        if (!pid) { alert('Select a product first'); return; }
        generateSingle(pid);
    });

    // Row generate button
    $(document).on('click', '.fk-gen-row', function(e) {
        e.preventDefault();
        const pid = $(this).data('id');
        $('#fk-product-select').val(pid);
        generateSingle(pid);
        $('html,body').animate({scrollTop: $('#fk-editor').offset().top - 100}, 300);
    });

    // Save
    $('#fk-save').on('click', function(e) {
        e.preventDefault();
        if (!currentProductId) return;
        $.post(ajaxurl, {
            action: 'sgclabs_ai_fk_save',
            nonce: nonce,
            product_id: currentProductId,
            primary: $('#fk-primary').val(),
            lsi: $('#fk-lsi').val()
        }, function(res) {
            if (res.success) {
                $('#fk-save-status').html('<span style="color:#155724;">✅ Saved! ' + res.data.primary + '</span>').show();
                setTimeout(function() { $('#fk-save-status').fadeOut(); }, 3000);
            } else {
                alert(res.data || 'Save failed');
            }
        });
    });

    // Regenerate
    $('#fk-regenerate').on('click', function(e) {
        e.preventDefault();
        if (currentProductId) generateSingle(currentProductId);
    });

    // Skip
    $('#fk-skip').on('click', function(e) {
        e.preventDefault();
        $('#fk-editor').slideUp(200);
        currentProductId = 0;
    });

    // ── BULK GENERATION ──
    function bulkProcess() {
        if (bulkPaused) return;
        $.post(ajaxurl, {
            action: 'sgclabs_ai_fk_generate_batch',
            nonce: nonce,
            batch_size: 5,
            use_ai: $('#fk-use-ai').is(':checked') ? '1' : '0'
        }, function(res) {
            if (!res.success) {
                $('#fk-bulk-text').text('❌ Error: ' + (res.data || 'Unknown'));
                bulkRunning = false;
                return;
            }
            const d = res.data;
            $('#fk-progress').css('width', d.percent + '%');
            $('#stat-done').text(d.done);
            $('#stat-pending').text(d.total - d.done);
            $('#stat-pct').text(d.percent + '%');
            $('#fk-bulk-text').text('⚡ ' + d.done + '/' + d.total + ' done (' + d.percent + '%) — Batch: ' + d.processed + ' processed');

            if (d.finished) {
                bulkRunning = false;
                $('#fk-bulk-text').text('✅ Complete! All products have focus keywords.');
                $('#fk-bulk-start').show();
                $('#fk-bulk-pause').hide();
                return;
            }
            setTimeout(bulkProcess, 2000);
        }).fail(function() {
            $('#fk-bulk-text').text('❌ AJAX Error');
            bulkRunning = false;
        });
    }

    $('#fk-bulk-start').on('click', function(e) {
        e.preventDefault();
        if (bulkRunning) return;
        bulkRunning = true;
        bulkPaused = false;
        $(this).hide();
        $('#fk-bulk-pause').show();
        $('#fk-bulk-status').show();
        $('#fk-bulk-text').text('⏳ Starting...');
        bulkProcess();
    });

    $('#fk-bulk-pause').on('click', function(e) {
        e.preventDefault();
        bulkPaused = true;
        $(this).hide();
        $('#fk-bulk-resume').show();
        $('#fk-bulk-text').text('⏸️ Paused.');
    });

    $('#fk-bulk-resume').on('click', function(e) {
        e.preventDefault();
        bulkPaused = false;
        $(this).hide();
        $('#fk-bulk-pause').show();
        bulkProcess();
    });

})(jQuery);
</script>
