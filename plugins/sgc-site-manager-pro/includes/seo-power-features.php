<?php
/**
 * SGC LABS® SEO Power Features v1.0.0
 * Additive module — no existing files modified.
 *
 * Features:
 * 1. Image Filename Optimizer  — auto-rename on upload to product-name.jpg
 * 2. Content Backup & Rollback — save before AI overwrites, restore anytime
 * 3. Competitor Intelligence   — AI-powered competitor URL analysis
 *
 * @package SGC LABS Manager Pro
 * @since   6.6.6
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================
// FEATURE 1 — IMAGE FILENAME OPTIMIZER
// Auto-rename uploaded images to SEO-friendly filenames
// e.g. "IMG_1234.jpg" → "borosilicate-flask-250ml-sgclabs.jpg"
// ============================================================

add_filter( 'wp_handle_upload_prefilter', 'sgclabs_optimize_upload_filename' );
function sgclabs_optimize_upload_filename( array $file ): array {
    if ( get_option( 'sgclabs_filename_optimizer_active', '1' ) !== '1' ) return $file;

    $original = $file['name'];
    $ext      = strtolower( pathinfo( $original, PATHINFO_EXTENSION ) );

    // Only process images
    $image_exts = [ 'jpg', 'jpeg', 'png', 'webp', 'gif' ];
    if ( ! in_array( $ext, $image_exts, true ) ) return $file;

    // Try to get product context from referer
    $product_name = '';
    $referer      = wp_get_referer();
    if ( $referer ) {
        $post_id = url_to_postid( $referer );
        if ( $post_id && get_post_type( $post_id ) === 'product' ) {
            $product = wc_get_product( $post_id );
            if ( $product ) {
                $product_name = $product->get_name();
            }
        }
    }

    // Build SEO filename
    if ( $product_name ) {
        $base = sanitize_title( $product_name ) . '-sgclabs';
    } else {
        // Strip common garbage patterns from original filename
        $base = pathinfo( $original, PATHINFO_FILENAME );
        $base = preg_replace( '/^(img|image|photo|pic|dsc|screenshot|untitled)[_\-]?\d*/i', '', $base );
        $base = sanitize_title( $base );
        if ( strlen( $base ) < 4 ) {
            $base = 'lab-product-sgclabs-' . date( 'His' );
        }
    }

    // Ensure filename is unique
    $new_name       = $base . '.' . $ext;
    $upload_dir     = wp_upload_dir();
    $target         = $upload_dir['path'] . '/' . $new_name;
    $counter        = 1;
    while ( file_exists( $target ) ) {
        $new_name = $base . '-' . $counter . '.' . $ext;
        $target   = $upload_dir['path'] . '/' . $new_name;
        $counter++;
    }

    $file['name'] = $new_name;
    return $file;
}

// ============================================================
// FEATURE 2 — CONTENT BACKUP & ROLLBACK
// Save original content BEFORE AI overwrites it
// Restore with one click from product edit page
// ============================================================

/**
 * Save backup of current content before AI generation.
 * Called automatically before any AI bulk/one-click operation.
 */
function sgclabs_backup_product_content( int $pid ): bool {
    $post = get_post( $pid );
    if ( ! $post ) return false;

    $backup = [
        'post_title'   => $post->post_title,
        'post_content' => $post->post_content,
        'post_excerpt' => $post->post_excerpt,
        'seo_title'    => get_post_meta( $pid, '_sgclabs_seo_title', true ),
        'seo_desc'     => get_post_meta( $pid, '_sgclabs_seo_description', true ),
        'focus_kw'     => get_post_meta( $pid, '_sgclabs_focus_keyword', true ),
        'backed_up_at' => current_time( 'mysql' ),
    ];

    update_post_meta( $pid, '_sgclabs_content_backup', $backup );
    return true;
}

// PRE-HOOK: Backup content BEFORE AI modifies it (priority 1).
// The actual AI handler lives in ai-content-engine.php at priority 10.
// WordPress calls both in sequence — this is intentional, NOT a duplicate.
add_action( 'wp_ajax_sgclabs_ai_one_click_fix', function() {
    // Nonce + capability checked here too — never trust call order alone.
    if ( ! check_ajax_referer( 'sgclabs_nonce', 'nonce', false ) ) return;
    if ( ! current_user_can( 'edit_products' ) ) return;
    $pid = intval( $_POST['post_id'] ?? 0 );
    if ( $pid ) sgclabs_backup_product_content( $pid );
}, 1 );

// Hook into bulk operations — backup per product
add_action( 'sgclabs_before_bulk_ai_process', 'sgclabs_backup_product_content', 10, 1 );

// AJAX: Restore content from backup
add_action( 'wp_ajax_sgclabs_restore_content_backup', function() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Unauthorized' );

    $pid = intval( $_POST['post_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'Invalid product ID' );

    $backup = get_post_meta( $pid, '_sgclabs_content_backup', true );
    if ( ! is_array( $backup ) || empty( $backup ) ) {
        wp_send_json_error( 'No backup found for this product' );
    }

    // Restore post content
    wp_update_post( [
        'ID'           => $pid,
        'post_title'   => sanitize_text_field( $backup['post_title'] ?? '' ),
        'post_content' => wp_kses_post( $backup['post_content'] ?? '' ),
        'post_excerpt' => wp_kses_post( $backup['post_excerpt'] ?? '' ),
    ] );

    // Restore SEO meta
    if ( ! empty( $backup['seo_title'] ) )
        update_post_meta( $pid, '_sgclabs_seo_title', sanitize_text_field( $backup['seo_title'] ) );
    if ( ! empty( $backup['seo_desc'] ) )
        update_post_meta( $pid, '_sgclabs_seo_description', sanitize_textarea_field( $backup['seo_desc'] ) );
    if ( ! empty( $backup['focus_kw'] ) )
        update_post_meta( $pid, '_sgclabs_focus_keyword', sanitize_text_field( $backup['focus_kw'] ) );

    wp_send_json_success( [
        'message'    => 'Content restored from backup (' . esc_html( $backup['backed_up_at'] ?? '' ) . ')',
        'backed_up_at' => esc_html( $backup['backed_up_at'] ?? '' ),
    ] );
} );

// AJAX: Check if backup exists
add_action( 'wp_ajax_sgclabs_check_backup', function() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Unauthorized' );

    $pid    = intval( $_POST['post_id'] ?? 0 );
    $backup = get_post_meta( $pid, '_sgclabs_content_backup', true );
    $has    = is_array( $backup ) && ! empty( $backup );

    wp_send_json_success( [
        'has_backup'   => $has,
        'backed_up_at' => $has ? esc_html( $backup['backed_up_at'] ?? '' ) : '',
    ] );
} );

// Add Restore button to product edit page (sidebar)
add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'sgclabs_content_rollback',
        '↩️ SGC Content Backup',
        'sgclabs_content_rollback_metabox',
        'product',
        'side',
        'low'
    );
} );

function sgclabs_content_rollback_metabox( WP_Post $post ) {
    $nonce  = wp_create_nonce( 'sgclabs_nonce' );
    $backup = get_post_meta( $post->ID, '_sgclabs_content_backup', true );
    $has    = is_array( $backup ) && ! empty( $backup );
    $pid    = $post->ID;
    ?>
    <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:12px;">
        <?php if ( $has ) : ?>
            <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:10px;margin-bottom:10px;">
                <div style="color:#15803d;font-weight:600;margin-bottom:3px;">✅ Backup available</div>
                <div style="color:#166534;font-size:11px;">Saved: <?php echo esc_html( $backup['backed_up_at'] ?? '' ); ?></div>
                <div style="color:#166534;font-size:11px;margin-top:2px;">Title: <?php echo esc_html( substr( $backup['post_title'] ?? '', 0, 40 ) ); ?>...</div>
            </div>
            <button type="button" id="sgc-restore-btn"
                style="width:100%;padding:8px;background:#dc2626;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">
                ↩️ Restore Original Content
            </button>
            <div id="sgc-restore-status" style="margin-top:6px;font-size:11px;color:#666;"></div>
        <?php else : ?>
            <div style="background:#f8f9fa;border:1px solid #e5e7eb;border-radius:6px;padding:10px;color:#6b7280;text-align:center;">
                <div style="font-size:16px;margin-bottom:4px;">🔒</div>
                No backup yet. Run AI One-Click to auto-backup original content.
            </div>
        <?php endif; ?>
        <div style="margin-top:8px;font-size:10px;color:#9ca3af;">Backup is saved automatically before every AI operation.</div>
    </div>
    <script>
    (function(){
        var btn = document.getElementById('sgc-restore-btn');
        if (!btn) return;
        btn.addEventListener('click', function() {
            if (!confirm('Original content restore ho jayega. AI-generated content replace ho jayega. Confirm?')) return;
            btn.disabled = true;
            btn.textContent = '⏳ Restoring...';
            var status = document.getElementById('sgc-restore-status');
            jQuery.post(ajaxurl, {
                action: 'sgclabs_restore_content_backup',
                nonce: '<?php echo esc_js( $nonce ); ?>',
                post_id: <?php echo intval( $pid ); ?>
            }, function(res) {
                if (res.success) {
                    btn.style.background = '#16a34a';
                    btn.textContent = '✅ Restored! Reloading...';
                    status.style.color = '#16a34a';
                    status.textContent = res.data.message;
                    setTimeout(function(){ location.reload(); }, 2000);
                } else {
                    btn.disabled = false;
                    btn.textContent = '↩️ Restore Original Content';
                    status.style.color = '#dc2626';
                    status.textContent = '❌ ' + (res.data || 'Error');
                }
            }).fail(function() {
                btn.disabled = false;
                btn.textContent = '↩️ Restore Original Content';
                status.style.color = '#dc2626';
                status.textContent = '❌ Network error';
            });
        });
    })();
    </script>
    <?php
}

// ============================================================
// FEATURE 3 — COMPETITOR INTELLIGENCE
// Input competitor URL → AI analyzes → Beat their ranking
// ============================================================

// AJAX: Analyze competitor URL
add_action( 'wp_ajax_sgclabs_competitor_analyze', function() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $url = esc_url_raw( wp_unslash( $_POST['competitor_url'] ?? '' ) );
    if ( ! $url || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
        wp_send_json_error( 'Valid URL required' );
    }

    // Fetch competitor page
    $response = wp_remote_get( $url, [
        'timeout'    => 20,
        'user-agent' => 'Mozilla/5.0 (compatible; SGCLabsBot/1.0)',
        'sslverify'  => false,
    ] );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'Could not fetch URL: ' . $response->get_error_message() );
    }

    $html = wp_remote_retrieve_body( $response );
    if ( empty( $html ) ) wp_send_json_error( 'Empty response from URL' );

    // Extract key SEO elements
    $title       = '';
    $description = '';
    $h1          = '';
    $h2s         = [];
    $word_count  = 0;

    // Title
    if ( preg_match( '/<title[^>]*>(.*?)<\/title>/si', $html, $m ) ) {
        $title = wp_strip_all_tags( $m[1] );
    }
    // Meta description
    if ( preg_match( '/<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\']/si', $html, $m ) ) {
        $description = $m[1];
    }
    // H1
    if ( preg_match( '/<h1[^>]*>(.*?)<\/h1>/si', $html, $m ) ) {
        $h1 = wp_strip_all_tags( $m[1] );
    }
    // H2s
    if ( preg_match_all( '/<h2[^>]*>(.*?)<\/h2>/si', $html, $ms ) ) {
        $h2s = array_slice( array_map( 'wp_strip_all_tags', $ms[1] ), 0, 6 );
    }
    // Word count
    $body_text  = preg_replace( '/<script[^>]*>.*?<\/script>/si', '', $html );
    $body_text  = preg_replace( '/<style[^>]*>.*?<\/style>/si', '', $body_text );
    $body_text  = wp_strip_all_tags( $body_text );
    $word_count = str_word_count( $body_text );

    // Our product context
    $our_product = sanitize_text_field( $_POST['our_product'] ?? 'Laboratory Glassware' );
    $our_keyword = sanitize_text_field( $_POST['our_keyword'] ?? '' );

    // Build AI prompt
    $h2_list = ! empty( $h2s ) ? implode( "\n- ", $h2s ) : 'Not detected';
    $prompt  = "You are an expert SEO strategist for B2B laboratory equipment.

Analyze this competitor page and suggest how to BEAT their ranking:

COMPETITOR URL: {$url}
COMPETITOR TITLE: {$title}
COMPETITOR META DESC: {$description}
COMPETITOR H1: {$h1}
COMPETITOR H2 HEADINGS:
- {$h2_list}
COMPETITOR WORD COUNT: {$word_count}

OUR PRODUCT: {$our_product}
OUR TARGET KEYWORD: {$our_keyword}
OUR BRAND: SGC LABS® — ISO 9001:2015 certified manufacturer, Ambala India, exporting to 50+ countries since 2003.

Provide a strategic analysis in this EXACT JSON format (no markdown, no explanation):
{
  \"competitor_strengths\": [\"strength 1\", \"strength 2\", \"strength 3\"],
  \"competitor_weaknesses\": [\"weakness 1\", \"weakness 2\", \"weakness 3\"],
  \"our_advantages\": [\"advantage 1\", \"advantage 2\", \"advantage 3\"],
  \"beat_strategy\": [
    {\"action\": \"What to do\", \"reason\": \"Why this beats them\"},
    {\"action\": \"What to do\", \"reason\": \"Why this beats them\"},
    {\"action\": \"What to do\", \"reason\": \"Why this beats them\"},
    {\"action\": \"What to do\", \"reason\": \"Why this beats them\"}
  ],
  \"suggested_title\": \"Our better SEO title (under 60 chars)\",
  \"suggested_meta\": \"Our better meta description (under 160 chars)\",
  \"content_gaps\": [\"topic they missed 1\", \"topic they missed 2\", \"topic they missed 3\"],
  \"quick_wins\": [\"quick win 1\", \"quick win 2\"]
}";

    $ai_result = function_exists( 'sgclabs_ai_call' ) ? sgclabs_ai_call( $prompt, 800 ) : null;

    if ( ! $ai_result || is_wp_error( $ai_result ) ) {
        wp_send_json_error( 'AI analysis failed. Check API key in AI Control Center.' );
    }

    // Parse JSON response
    $clean  = preg_replace( '/^```json\s*|\s*```$/m', '', trim( $ai_result ) );
    $parsed = json_decode( $clean, true );

    if ( ! is_array( $parsed ) ) {
        // Return raw if JSON parse fails
        wp_send_json_success( [
            'raw'           => $ai_result,
            'competitor'    => compact( 'title', 'description', 'h1', 'h2s', 'word_count' ),
            'parse_failed'  => true,
        ] );
    }

    wp_send_json_success( [
        'analysis'   => $parsed,
        'competitor' => compact( 'title', 'description', 'h1', 'h2s', 'word_count' ),
        'url'        => $url,
    ] );
} );

// Admin page for Competitor Intelligence
function sgclabs_competitor_intelligence_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $nonce = wp_create_nonce( 'sgclabs_nonce' );
    $ai_ok = function_exists( 'sgclabs_ai_any_configured' ) && sgclabs_ai_any_configured();
    ?>
    <div class="wrap sgclabs-wrap" style="max-width:900px;">
        <h1 style="font-size:22px;font-weight:800;color:#1a1a2e;margin-bottom:6px;">🎯 Competitor Intelligence</h1>
        <p style="color:#6b7280;margin-bottom:24px;">Competitor URL daalo → AI analyze karega → Unhe beat karne ki strategy milegi</p>

        <?php if ( ! $ai_ok ) : ?>
        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px;margin-bottom:20px;color:#991b1b;">
            ⚠️ AI provider configured nahi hai. <a href="<?php echo admin_url('admin.php?page=sgclabs-ai-center&tab=control'); ?>">→ Add API key</a>
        </div>
        <?php endif; ?>

        <!-- Input Form -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;margin-bottom:20px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px;">🔗 Competitor URL *</label>
                    <input type="url" id="sgc-comp-url" placeholder="https://competitor.com/product/..." style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px;">📦 Our Product Name</label>
                    <input type="text" id="sgc-comp-product" placeholder="e.g. Borosilicate Beaker 250ml" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px;">🔑 Target Keyword</label>
                    <input type="text" id="sgc-comp-keyword" placeholder="e.g. buy laboratory beaker india" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;">
                </div>
                <div style="display:flex;align-items:flex-end;">
                    <button onclick="sgcAnalyzeCompetitor()" id="sgc-comp-btn" <?php echo !$ai_ok?'disabled':'';?>
                        style="width:100%;padding:10px;background:#1a1a2e;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">
                        🎯 Analyze Competitor
                    </button>
                </div>
            </div>
        </div>

        <!-- Results -->
        <div id="sgc-comp-results" style="display:none;">

            <!-- Competitor Profile -->
            <div id="sgc-comp-profile" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;margin-bottom:16px;">
                <h3 style="font-size:14px;font-weight:700;margin-bottom:12px;color:#1a1a2e;">📊 Competitor Profile</h3>
                <table style="width:100%;font-size:12px;border-collapse:collapse;">
                    <tr><td style="padding:5px 0;color:#6b7280;width:120px;">Title</td><td id="sgc-cp-title" style="font-weight:600;color:#1a1a2e;"></td></tr>
                    <tr><td style="padding:5px 0;color:#6b7280;">Meta Desc</td><td id="sgc-cp-desc" style="color:#555;"></td></tr>
                    <tr><td style="padding:5px 0;color:#6b7280;">H1</td><td id="sgc-cp-h1" style="color:#555;"></td></tr>
                    <tr><td style="padding:5px 0;color:#6b7280;">Word Count</td><td id="sgc-cp-words" style="font-weight:600;"></td></tr>
                    <tr><td style="padding:5px 0;color:#6b7280;">H2 Headings</td><td id="sgc-cp-h2s" style="color:#555;font-size:11px;"></td></tr>
                </table>
            </div>

            <!-- Strategy Grid -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
                <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px;">
                    <div style="font-size:12px;font-weight:700;color:#991b1b;margin-bottom:8px;">💪 Their Strengths</div>
                    <ul id="sgc-comp-strengths" style="font-size:11px;color:#7f1d1d;padding-left:16px;"></ul>
                </div>
                <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:10px;padding:14px;">
                    <div style="font-size:12px;font-weight:700;color:#15803d;margin-bottom:8px;">⚡ Their Weaknesses</div>
                    <ul id="sgc-comp-weaknesses" style="font-size:11px;color:#14532d;padding-left:16px;"></ul>
                </div>
                <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px;">
                    <div style="font-size:12px;font-weight:700;color:#1e40af;margin-bottom:8px;">🏆 Our Advantages</div>
                    <ul id="sgc-comp-advantages" style="font-size:11px;color:#1e3a8a;padding-left:16px;"></ul>
                </div>
                <div style="background:#fef9e7;border:1px solid #fcd34d;border-radius:10px;padding:14px;">
                    <div style="font-size:12px;font-weight:700;color:#92400e;margin-bottom:8px;">🎯 Content Gaps They Missed</div>
                    <ul id="sgc-comp-gaps" style="font-size:11px;color:#78350f;padding-left:16px;"></ul>
                </div>
            </div>

            <!-- Beat Strategy -->
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;margin-bottom:16px;">
                <h3 style="font-size:14px;font-weight:700;margin-bottom:12px;color:#1a1a2e;">🚀 Beat Strategy — Action Plan</h3>
                <div id="sgc-comp-strategy" style="display:flex;flex-direction:column;gap:8px;"></div>
            </div>

            <!-- Suggested Title + Meta -->
            <div style="background:linear-gradient(135deg,#f8f9ff,#f0f1ff);border:1px solid #c7d2fe;border-radius:12px;padding:18px;margin-bottom:16px;">
                <h3 style="font-size:14px;font-weight:700;margin-bottom:12px;color:#1a1a2e;">✍️ AI Suggested — Better Title & Meta</h3>
                <div style="margin-bottom:10px;">
                    <div style="font-size:11px;color:#6b7280;margin-bottom:3px;">Suggested SEO Title</div>
                    <div id="sgc-comp-sug-title" style="font-size:13px;font-weight:700;color:#1a1a2e;background:#fff;padding:8px 12px;border-radius:6px;border:1px solid #e5e7eb;"></div>
                </div>
                <div>
                    <div style="font-size:11px;color:#6b7280;margin-bottom:3px;">Suggested Meta Description</div>
                    <div id="sgc-comp-sug-meta" style="font-size:12px;color:#555;background:#fff;padding:8px 12px;border-radius:6px;border:1px solid #e5e7eb;line-height:1.5;"></div>
                </div>
            </div>

            <!-- Quick Wins -->
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;">
                <h3 style="font-size:14px;font-weight:700;margin-bottom:10px;color:#1a1a2e;">⚡ Quick Wins — Karo Aaj</h3>
                <div id="sgc-comp-quickwins" style="display:flex;flex-wrap:wrap;gap:8px;"></div>
            </div>
        </div>

        <!-- Status -->
        <div id="sgc-comp-status" style="margin-top:14px;font-size:13px;color:#6b7280;text-align:center;"></div>
    </div>

    <script>
    function sgcAnalyzeCompetitor() {
        var url     = document.getElementById('sgc-comp-url').value.trim();
        var product = document.getElementById('sgc-comp-product').value.trim();
        var keyword = document.getElementById('sgc-comp-keyword').value.trim();
        var btn     = document.getElementById('sgc-comp-btn');
        var status  = document.getElementById('sgc-comp-status');

        if (!url) { alert('Competitor URL required'); return; }

        btn.disabled = true;
        btn.textContent = '⏳ Fetching & analyzing...';
        status.textContent = 'Competitor page fetch ho rahi hai... (10-20 seconds)';
        document.getElementById('sgc-comp-results').style.display = 'none';

        jQuery.post(ajaxurl, {
            action: 'sgclabs_competitor_analyze',
            nonce: '<?php echo esc_js( $nonce ); ?>',
            competitor_url: url,
            our_product: product,
            our_keyword: keyword
        }, function(res) {
            btn.disabled = false;
            btn.textContent = '🎯 Analyze Competitor';
            status.textContent = '';

            if (!res.success) {
                status.style.color = '#dc2626';
                status.textContent = '❌ ' + (res.data || 'Analysis failed');
                return;
            }

            var d = res.data;
            var c = d.competitor;
            var a = d.analysis;

            // Competitor profile
            document.getElementById('sgc-cp-title').textContent  = c.title  || '—';
            document.getElementById('sgc-cp-desc').textContent   = c.description || '—';
            document.getElementById('sgc-cp-h1').textContent     = c.h1 || '—';
            document.getElementById('sgc-cp-words').textContent  = (c.word_count || 0).toLocaleString() + ' words';
            document.getElementById('sgc-cp-h2s').textContent    = (c.h2s || []).join(' | ') || '—';

            if (a && !d.parse_failed) {
                // Strengths/weaknesses/advantages/gaps
                ['strengths','weaknesses','advantages','content_gaps'].forEach(function(key, i) {
                    var ids = ['sgc-comp-strengths','sgc-comp-weaknesses','sgc-comp-advantages','sgc-comp-gaps'];
                    var el = document.getElementById(ids[i]);
                    var items = a[key] || [];
                    el.innerHTML = items.map(function(s){ return '<li style="margin-bottom:3px;">'+s+'</li>'; }).join('');
                });

                // Strategy
                var stratEl = document.getElementById('sgc-comp-strategy');
                stratEl.innerHTML = (a.beat_strategy || []).map(function(s, i) {
                    return '<div style="display:flex;gap:10px;padding:10px;background:#f8fafc;border-radius:8px;border-left:3px solid #1a1a2e;">' +
                        '<span style="font-size:16px;font-weight:700;color:#1a1a2e;flex-shrink:0;">' + (i+1) + '</span>' +
                        '<div><div style="font-size:12px;font-weight:700;color:#1a1a2e;">' + s.action + '</div>' +
                        '<div style="font-size:11px;color:#6b7280;margin-top:2px;">' + s.reason + '</div></div></div>';
                }).join('');

                // Suggested title/meta
                document.getElementById('sgc-comp-sug-title').textContent = a.suggested_title || '—';
                document.getElementById('sgc-comp-sug-meta').textContent  = a.suggested_meta  || '—';

                // Quick wins
                var qwEl = document.getElementById('sgc-comp-quickwins');
                qwEl.innerHTML = (a.quick_wins || []).map(function(w) {
                    return '<span style="background:#f0fdf4;border:1px solid #86efac;color:#15803d;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600;">⚡ ' + w + '</span>';
                }).join('');
            } else if (d.parse_failed) {
                document.getElementById('sgc-comp-strategy').innerHTML =
                    '<pre style="font-size:11px;background:#f8f9fa;padding:12px;border-radius:6px;overflow:auto;">' +
                    (d.raw || 'No response') + '</pre>';
            }

            document.getElementById('sgc-comp-results').style.display = 'block';
            document.getElementById('sgc-comp-results').scrollIntoView({behavior:'smooth'});

        }).fail(function() {
            btn.disabled = false;
            btn.textContent = '🎯 Analyze Competitor';
            status.style.color = '#dc2626';
            status.textContent = '❌ Network error. Try again.';
        });
    }
    </script>
    <?php
}

// Register admin menu page
add_action( 'admin_menu', function() {
    add_submenu_page(
        'sgclabs-manager',
        'Competitor Intelligence',
        '🎯 Competitor Intel',
        'manage_options',
        'sgclabs-competitor-intel',
        'sgclabs_competitor_intelligence_page'
    );
}, 25 );

// ============================================================
// SETTINGS: Filename optimizer toggle
// ============================================================

add_action( 'admin_init', function() {
    register_setting( 'sgclabs_power_features', 'sgclabs_filename_optimizer_active' );
} );
