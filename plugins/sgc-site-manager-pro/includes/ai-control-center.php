<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ============================================================
 * SGC LABS® AI CONTROL CENTER — v6.6.3
 * ============================================================
 * One page to control EVERY plugin module via AI APIs.
 * Priority: Claude → DeepSeek → Gemini → OpenAI
 *
 * Features:
 * - Real-time AJAX activity monitor (JS interceptor)
 * - Live stats (5s polling)
 * - AI Command Bar (natural language → module execution)
 * - Module grid with toggles + Start/Stop per module
 * - All security: nonce + capability on every endpoint
 * ============================================================
 */

// ── Menu registration ──


// ============================================================
// HELPER: Call active AI API (Claude priority)
// ============================================================
function sgclabs_acc_call_ai( string $prompt, int $max_tokens = 800 ): array {
    // Delegates to the unified AI provider system (ai-provider.php)
    // Respects whichever provider the user selected in AI Control Center
    $result = sgclabs_ai_call( $prompt, $max_tokens );
    if ( is_wp_error( $result ) ) {
        return [ 'ok' => false, 'text' => $result->get_error_message(), 'provider' => 'none' ];
    }
    return [ 'ok' => true, 'text' => $result, 'provider' => sgclabs_ai_active_provider() ];
}

// ============================================================
// AJAX: Test API connection
// ============================================================
add_action( 'wp_ajax_sgcacc_api_test', function () {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $result = sgclabs_acc_call_ai( 'Reply with only: {"status":"ok","message":"API connection successful"}', 100 );

    if ( ! $result['ok'] ) {
        wp_send_json_error( $result['text'] );
    }
    wp_send_json_success( [
        'provider' => $result['provider'],
        'message'  => 'Connected to ' . ucfirst( $result['provider'] ) . ' API ✅',
    ] );
} );

// ============================================================
// AJAX: AI Command — natural language → module list
// ============================================================
add_action( 'wp_ajax_sgcacc_ai_command', function () {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $command = sanitize_text_field( $_POST['command'] ?? '' );
    if ( empty( $command ) ) wp_send_json_error( 'Empty command.' );

    $modules_list = implode( ', ', [
        'title_rewriter', 'short_desc', 'main_desc', 'image_alt',
        'focus_keyword', 'auto_seo', 'claude_seo', 'seo_injector',
        'internal_links', 'related_links', 'size_variants',
        'broken_links', 'site_crawler', 'sitemap', 'indexnow',
        'bulk_image_seo', 'content_generator',
    ] );

    $prompt = <<<PROMPT
You are the AI brain of an e-commerce SEO plugin for SGC Labs® (laboratory glassware).

Available modules: {$modules_list}

User command: "{$command}"

Analyze the command and return ONLY valid JSON (no markdown, no explanation):
{
  "understood": "brief 1-line summary of what you understood",
  "modules": ["module1", "module2"],
  "message": "I will run: [modules] to [goal]",
  "confirm_needed": true
}

Rules:
- Only include modules that are directly relevant to the command
- If command is about descriptions/content → title_rewriter, short_desc, main_desc, claude_seo
- If command is about SEO → auto_seo, seo_injector, focus_keyword
- If command is about images → image_alt, bulk_image_seo
- If command is about links → internal_links, related_links, size_variants
- If command is vague → ask for clarification via message field, modules = []
PROMPT;

    $result = sgclabs_acc_call_ai( $prompt, 400 );

    if ( ! $result['ok'] ) {
        wp_send_json_error( $result['text'] );
    }

    $clean = preg_replace( '/```json|```/i', '', $result['text'] );
    $data  = json_decode( trim( $clean ), true );

    if ( ! $data ) {
        wp_send_json_error( 'AI response could not be parsed. Try again.' );
    }

    wp_send_json_success( [
        'ai_response' => $data,
        'provider'    => $result['provider'],
        'raw'         => $result['text'],
    ] );
} );

// ============================================================
// AJAX: Live stats for dashboard
// ============================================================
add_action( 'wp_ajax_sgcacc_live_stats', function () {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    global $wpdb;

    $total    = (int) ( wp_count_posts( 'product' )->publish ?? 0 );
    $seo_done = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_sgclabs_seo_cache'" );
    $claude   = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_sgclabs_claude_generated_at'" );
    $titles   = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_sgclabs_seo_title'" );
    $focus_kw = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_sgclabs_focus_kw'" );
    $img_alt  = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_sgclabs_img_alt_set'" );
    $inquiries= (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_inquiries WHERE status='new'" );
    $broken   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_broken_links" );
    $kw_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_kw_global" );
    $redirects= (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_redirects WHERE enabled=1" );

    $pct = fn( $done ) => $total > 0 ? round( $done / $total * 100 ) : 0;

    wp_send_json_success( [
        'total_products' => $total,
        'seo_done'       => $seo_done,     'seo_pct'    => $pct( $seo_done ),
        'claude_done'    => $claude,        'claude_pct' => $pct( $claude ),
        'titles_done'    => $titles,        'titles_pct' => $pct( $titles ),
        'focus_kw_done'  => $focus_kw,      'focus_pct'  => $pct( $focus_kw ),
        'img_alt_done'   => $img_alt,       'img_pct'    => $pct( $img_alt ),
        'new_inquiries'  => $inquiries,
        'broken_links'   => $broken,
        'keywords'       => $kw_total,
        'redirects'      => $redirects,
        'timestamp'      => current_time( 'H:i:s' ),
    ] );
} );

// ============================================================
// AJAX: Toggle a module on/off
// ============================================================
add_action( 'wp_ajax_sgcacc_toggle', function () {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $option = sanitize_key( $_POST['option'] ?? '' );
    $value  = ( $_POST['value'] ?? '0' ) === '1' ? '1' : '0';

    // Whitelist of toggleable options
    $allowed = [
        // Existing
        'sgclabs_claude_seo_active', 'sgclabs_claude_ui_active',
        'sgclabs_content_gen_active', 'sgclabs_auto_seo_active',
        'sgclabs_internal_links_active', 'sgclabs_svl_active',
        'sgclabs_aichat_active', 'sgclabs_wa_active',
        'sgclabs_announcement_active', 'sgclabs_popup_active',
        'sgclabs_cwv_active', 'sgclabs_lazy_images',
        'sgclabs_canonical_active', 'sgclabs_indexnow_active',
        'sgclabs_sitemap_active', 'sgclabs_local_seo_enabled',
        'sgclabs_img_schema_active', 'sgclabs_img_og_active',
        'sgclabs_product_layout_fix', 'sgclabs_svl_in_short',
        'sgclabs_svl_in_main', 'sgclabs_related_links_short',
        'sgclabs_related_links_main',
        // New module toggles (v6.6.3)
        'sgclabs_auto_seo_engine_active',
        'sgclabs_broken_links_active',
        'sgclabs_extended_sitemaps_active',
        'sgclabs_focus_kw_gen_active',
        'sgclabs_global_keywords_active',
        'sgclabs_gsc_connect_active',
        'sgclabs_inquiry_form_active',
        'sgclabs_keyword_manager_active',
        'sgclabs_multilingual_seo_active',
        'sgclabs_product_rewriter_active',
        'sgclabs_rank_tracker_active',
        'sgclabs_rankmath_parity_active',
        'sgclabs_redirections_active',
        'sgclabs_related_product_links_active',
        'sgclabs_schema_engine_active',
        'sgclabs_seo_injector_active',
        'sgclabs_seo_metabox_active',
        'sgclabs_site_crawler_active',
        'sgclabs_site_fixes_active',
        'sgclabs_ai_seo_features_active',
        'sgclabs_woo_image_sizes_active',
        'sgclabs_header_footer_active',
        // New module toggles (v6.6.3+)
        'sgclabs_auto_seo_engine_active',
        'sgclabs_b2b_quote_active',
        'sgclabs_broken_links_active',
        'sgclabs_extended_sitemaps_active',
        'sgclabs_focus_kw_gen_active',
        'sgclabs_global_keywords_active',
        'sgclabs_gsc_connect_active',
        'sgclabs_inquiry_form_active',
        'sgclabs_keyword_manager_active',
        'sgclabs_lab_tech_specs_active',
        'sgclabs_multilingual_seo_active',
        'sgclabs_ai_inquiry_reply_active',
    ];

    if ( ! in_array( $option, $allowed, true ) ) {
        wp_send_json_error( 'Option not allowed: ' . $option );
    }

    update_option( $option, $value );
    wp_send_json_success( [ 'option' => $option, 'value' => $value ] );
} );

// ============================================================
// ADMIN PAGE
// ============================================================
function sgclabs_ai_control_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'sgclabs' ), 403 );
    }

    global $wpdb;

    $nonce    = wp_create_nonce( 'sgclabs_nonce' );
    $ajax_url = admin_url( 'admin-ajax.php' );
    $brand    = get_option( 'sgclabs_claude_brand_color',  '#1a1a2e' );
    $accent   = get_option( 'sgclabs_claude_accent_color', '#27ae60' );

    // API key status — via unified provider system
    $active_provider = sgclabs_ai_active_provider();
    $api_status = [];
    foreach ( sgclabs_ai_providers() as $pid => $pdata ) {
        $api_status[$pid] = ! empty( sgclabs_ai_get_provider_key( $pid ) );
    }

    // Stats
    $total   = (int) ( wp_count_posts( 'product' )->publish ?? 0 );
    $seo_pct = $total > 0 ? round( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_sgclabs_seo_cache'" ) / $total * 100 ) : 0;

    // Module definitions
    $modules = [
        'content' => [
            'label' => 'Content Modules',
            'icon'  => '📝',
            'items' => [
                [ 'id' => 'title_rewriter',   'label' => 'Title Rewriter',       'action' => 'sgclabs_ace_bulk_titles',     'toggle' => 'sgclabs_auto_seo_engine_active',  'has_batch' => true ],
                [ 'id' => 'short_desc',        'label' => 'Short Description',    'action' => 'sgclabs_ace_bulk_short_desc', 'toggle' => 'sgclabs_auto_seo_engine_active',  'has_batch' => true ],
                [ 'id' => 'main_desc',         'label' => 'Main Description',     'action' => 'sgclabs_ace_bulk_main_desc',  'toggle' => 'sgclabs_product_rewriter_active', 'has_batch' => true ],
                [ 'id' => 'content_generator', 'label' => 'AI Content Gen',       'action' => 'sgclabs_ace_bulk_content',    'toggle' => 'sgclabs_content_gen_active',      'has_batch' => true ],
                [ 'id' => 'claude_seo',        'label' => 'Claude AI SEO',        'action' => 'sgclabs_ace_master_batch',    'toggle' => 'sgclabs_claude_seo_active',       'has_batch' => true ],
            ],
        ],
        'seo' => [
            'label' => 'SEO Modules',
            'icon'  => '🔍',
            'items' => [
                [ 'id' => 'auto_seo',      'label' => 'Auto SEO Engine',    'action' => 'sgclabs_ace_master_batch',    'toggle' => 'sgclabs_auto_seo_active',       'has_batch' => true ],
                [ 'id' => 'focus_keyword', 'label' => 'Focus Keywords',     'action' => 'sgclabs_ace_bulk_kw',         'toggle' => 'sgclabs_focus_kw_gen_active',   'has_batch' => true ],
                [ 'id' => 'seo_injector',  'label' => 'SEO Injector',       'action' => 'sgclabs_ace_bulk_meta_title', 'toggle' => 'sgclabs_seo_injector_active',   'has_batch' => true ],
                [ 'id' => 'seo_metabox',   'label' => 'SEO Metabox',        'action' => null,                          'toggle' => 'sgclabs_seo_metabox_active',    'has_batch' => false ],
                [ 'id' => 'schema_engine', 'label' => 'Schema Engine',      'action' => null,                          'toggle' => 'sgclabs_schema_engine_active',  'has_batch' => false ],
                [ 'id' => 'image_alt',     'label' => 'Image Alt Text',     'action' => 'sgclabs_ace_bulk_images',     'toggle' => 'sgclabs_img_schema_active',     'has_batch' => true ],
                [ 'id' => 'bulk_image_seo','label' => 'Bulk Image SEO',     'action' => 'sgclabs_ace_bulk_images',     'toggle' => 'sgclabs_woo_image_sizes_active','has_batch' => true ],
                [ 'id' => 'rankmath',      'label' => 'RankMath Parity',    'action' => null,                          'toggle' => 'sgclabs_rankmath_parity_active','has_batch' => false ],
            ],
        ],
        'links' => [
            'label' => 'Link Modules',
            'icon'  => '🔗',
            'items' => [
                [ 'id' => 'internal_links', 'label' => 'Internal Links',    'action' => 'sgclabs_rebuild_link_map', 'toggle' => 'sgclabs_internal_links_active',       'has_batch' => false ],
                [ 'id' => 'related_links',  'label' => 'Related Products',  'action' => 'sgclabs_related_run',      'toggle' => 'sgclabs_related_product_links_active','has_batch' => true ],
                [ 'id' => 'size_variants',  'label' => 'Size Variants',     'action' => 'sgclabs_svl_scan',         'toggle' => 'sgclabs_svl_active',                  'has_batch' => true ],
                [ 'id' => 'broken_links',   'label' => 'Broken Links',      'action' => 'sgclabs_scan_batch',       'toggle' => 'sgclabs_broken_links_active',         'has_batch' => true ],
                [ 'id' => 'redirections',   'label' => 'Redirections',      'action' => null,                       'toggle' => 'sgclabs_redirections_active',         'has_batch' => false ],
            ],
        ],
        'technical' => [
            'label' => 'Technical',
            'icon'  => '⚙️',
            'items' => [
                [ 'id' => 'site_crawler',    'label' => 'Site Crawler',       'action' => 'sgclabs_crawler_collect',      'toggle' => 'sgclabs_site_crawler_active',    'has_batch' => true ],
                [ 'id' => 'sitemap',         'label' => 'Sitemap',            'action' => 'sgclabs_sitemap_ping_engines', 'toggle' => 'sgclabs_sitemap_active',         'has_batch' => false ],
                [ 'id' => 'indexnow',        'label' => 'Instant Indexing',   'action' => 'sgclabs_indexnow_bulk',        'toggle' => 'sgclabs_indexnow_active',        'has_batch' => true ],
                [ 'id' => 'rank_tracker',    'label' => 'Rank Tracker',       'action' => null,                           'toggle' => 'sgclabs_rank_tracker_active',    'has_batch' => false ],
                [ 'id' => 'gsc_connect',     'label' => 'Google Search Console','action'=> null,                          'toggle' => 'sgclabs_gsc_connect_active',     'has_batch' => false ],
                [ 'id' => 'ext_sitemaps',    'label' => 'Extended Sitemaps',  'action' => null,                           'toggle' => 'sgclabs_extended_sitemaps_active','has_batch' => false ],
                [ 'id' => 'global_keywords', 'label' => 'Global Keywords',    'action' => null,                           'toggle' => 'sgclabs_global_keywords_active', 'has_batch' => false ],
                [ 'id' => 'keyword_manager', 'label' => 'Keyword Manager',    'action' => null,                           'toggle' => 'sgclabs_keyword_manager_active', 'has_batch' => false ],
                [ 'id' => 'multilingual',    'label' => 'Multilingual SEO',   'action' => null,                           'toggle' => 'sgclabs_multilingual_seo_active','has_batch' => false ],
                [ 'id' => 'ai_seo_features', 'label' => 'AI SEO Features',    'action' => null,                           'toggle' => 'sgclabs_ai_seo_features_active', 'has_batch' => false ],
                [ 'id' => 'site_fixes',      'label' => 'Site Fixes',         'action' => null,                           'toggle' => 'sgclabs_site_fixes_active',      'has_batch' => false ],
            ],
        ],
        'frontend' => [
            'label' => 'Frontend',
            'icon'  => '🌐',
            'items' => [
                [ 'id' => 'chatbot',        'label' => 'AI Chatbot',         'action' => null, 'toggle' => 'sgclabs_aichat_active',         'has_batch' => false ],
                [ 'id' => 'whatsapp',       'label' => 'WhatsApp Button',    'action' => null, 'toggle' => 'sgclabs_wa_active',             'has_batch' => false ],
                [ 'id' => 'announcement',   'label' => 'Announcement Bar',   'action' => null, 'toggle' => 'sgclabs_announcement_active',   'has_batch' => false ],
                [ 'id' => 'header_footer',  'label' => 'Header / Footer Bar','action' => null, 'toggle' => 'sgclabs_header_footer_active',  'has_batch' => false ],
                [ 'id' => 'layout_fix',     'label' => 'Product Layout Fix', 'action' => null, 'toggle' => 'sgclabs_product_layout_fix',    'has_batch' => false ],
                [ 'id' => 'cwv',            'label' => 'Core Web Vitals',    'action' => null, 'toggle' => 'sgclabs_cwv_active',            'has_batch' => false ],
                [ 'id' => 'lazy_images',    'label' => 'Lazy Load Images',   'action' => null, 'toggle' => 'sgclabs_lazy_images',           'has_batch' => false ],
                [ 'id' => 'canonical',      'label' => 'Canonical URLs',     'action' => null, 'toggle' => 'sgclabs_canonical_active',      'has_batch' => false ],
                [ 'id' => 'inquiry_form',   'label' => 'Inquiry Form',       'action' => null, 'toggle' => 'sgclabs_inquiry_form_active',   'has_batch' => false ],
            ],
        ],
    ];
    ?>
<!DOCTYPE html>
<div class="wrap" id="sgc-control-wrap" style="--sgc-brand:<?php echo esc_attr($brand); ?>;--sgc-accent:<?php echo esc_attr($accent); ?>">
<style>
/* ============================================================
   SGC LABS® AI CONTROL CENTER — Dark Theme UI
   ============================================================ */
#sgc-control-wrap { max-width:1400px; font-family:'Segoe UI',system-ui,sans-serif; }

/* Header */
.acc-head { background:linear-gradient(135deg,<?php echo esc_attr($brand) ?>,<?php echo esc_attr($accent) ?>);
  padding:20px 28px; border-radius:12px; margin:16px 0 20px;
  display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
.acc-head h1 { color:#fff; margin:0; font-size:22px; font-weight:700; display:flex; align-items:center; gap:10px; }
.acc-head p  { color:rgba(255,255,255,.75); margin:4px 0 0; font-size:13px; }
.acc-api-bar { display:flex; gap:8px; flex-wrap:wrap; }
.acc-api-chip { display:flex; align-items:center; gap:6px; padding:6px 14px;
  border-radius:20px; font-size:12px; font-weight:700; border:none; cursor:pointer;
  transition:all .2s; }
.acc-api-chip.active { background:#fff; color:<?php echo esc_attr($brand) ?>; box-shadow:0 2px 8px rgba(0,0,0,.2); }
.acc-api-chip.inactive { background:rgba(255,255,255,.15); color:rgba(255,255,255,.7); }
.acc-api-dot { width:8px; height:8px; border-radius:50%; }
.acc-api-dot.on { background:#4ade80; }
.acc-api-dot.off{ background:#ef4444; }

/* Two column layout */
.acc-layout { display:grid; grid-template-columns:360px 1fr; gap:20px; }
@media(max-width:1100px){ .acc-layout { grid-template-columns:1fr; } }

/* Left panel */
.acc-left { display:flex; flex-direction:column; gap:16px; }

/* Cards */
.acc-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px;
  overflow:hidden; }
.acc-card-head { padding:14px 18px; border-bottom:1px solid #f1f5f9;
  display:flex; align-items:center; justify-content:space-between; }
.acc-card-head h3 { margin:0; font-size:14px; font-weight:700;
  color:#1e293b; display:flex; align-items:center; gap:8px; }
.acc-card-body { padding:16px 18px; }

/* Live monitor */
.acc-monitor { background:#0f172a; border-radius:10px; padding:14px 16px;
  max-height:260px; overflow-y:auto; font-family:'JetBrains Mono','Fira Code',monospace;
  font-size:11.5px; scrollbar-width:thin; scrollbar-color:#334155 transparent; }
.acc-monitor::-webkit-scrollbar { width:4px; }
.acc-monitor::-webkit-scrollbar-thumb { background:#334155; border-radius:4px; }
.acc-log-entry { display:flex; align-items:center; gap:8px; padding:4px 0;
  border-bottom:1px solid #1e293b; animation:accFadeIn .3s ease; }
.acc-log-entry:last-child { border-bottom:none; }
@keyframes accFadeIn { from{opacity:0;transform:translateY(-4px)} to{opacity:1;transform:none} }
.acc-log-icon { font-size:12px; width:16px; flex-shrink:0; }
.acc-log-action { color:#94a3b8; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.acc-log-action.running { color:#60a5fa; }
.acc-log-action.done    { color:#4ade80; }
.acc-log-action.error   { color:#f87171; }
.acc-log-time { color:#475569; font-size:10px; white-space:nowrap; }
.acc-log-dur  { color:#64748b; font-size:10px; min-width:40px; text-align:right; }
.acc-monitor-empty { color:#475569; text-align:center; padding:20px 0; font-size:12px; }

/* Stats grid */
.acc-stats { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.acc-stat { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;
  padding:12px; position:relative; overflow:hidden; }
.acc-stat-label { font-size:11px; color:#64748b; font-weight:600;
  text-transform:uppercase; letter-spacing:.04em; margin-bottom:4px; }
.acc-stat-value { font-size:22px; font-weight:800; color:#1e293b; line-height:1; }
.acc-stat-sub   { font-size:11px; color:#94a3b8; margin-top:3px; }
.acc-stat-bar   { position:absolute; bottom:0; left:0; height:3px;
  background:linear-gradient(90deg,<?php echo esc_attr($accent) ?>,<?php echo esc_attr($brand) ?>);
  transition:width .6s ease; border-radius:0 2px 0 0; }
.acc-stat.highlight .acc-stat-value { color:<?php echo esc_attr($accent) ?>; }
.acc-stat.warn .acc-stat-value { color:#f59e0b; }
.acc-stat.danger .acc-stat-value { color:#ef4444; }

/* AI Command Bar */
.acc-cmd-wrap { display:flex; gap:10px; }
.acc-cmd-input { flex:1; padding:11px 16px; border:1.5px solid #e2e8f0;
  border-radius:8px; font-size:13px; font-family:inherit;
  transition:border-color .2s; outline:none; resize:none; min-height:44px; max-height:120px; }
.acc-cmd-input:focus { border-color:<?php echo esc_attr($accent) ?>; }
.acc-cmd-btn { padding:11px 20px; border-radius:8px; border:none;
  background:<?php echo esc_attr($accent) ?>; color:#fff; font-size:13px;
  font-weight:700; cursor:pointer; transition:opacity .2s; white-space:nowrap; }
.acc-cmd-btn:hover { opacity:.87; }
.acc-cmd-btn:disabled { opacity:.4; cursor:not-allowed; }
.acc-ai-response { background:#f0fdf4; border:1px solid #86efac; border-radius:8px;
  padding:12px 16px; margin-top:10px; font-size:13px; color:#166534; display:none; }
.acc-ai-response.thinking { background:#eff6ff; border-color:#93c5fd; color:#1d4ed8; }
.acc-ai-response.error    { background:#fef2f2; border-color:#fecaca; color:#991b1b; }

/* Module grid — right panel */
.acc-right { }
.acc-modules-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px; }
.acc-module-section { background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; }
.acc-mod-header { padding:12px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0;
  display:flex; align-items:center; gap:8px; }
.acc-mod-header h4 { margin:0; font-size:13px; font-weight:700; color:#1e293b; }
.acc-mod-items { padding:8px 0; }

/* Module row */
.acc-mod-row { display:flex; align-items:center; gap:10px; padding:8px 16px;
  border-bottom:1px solid #f8fafc; transition:background .15s; }
.acc-mod-row:last-child { border-bottom:none; }
.acc-mod-row:hover { background:#f8fafc; }
.acc-mod-name { flex:1; font-size:13px; color:#374151; font-weight:500; }

/* Toggle switch */
.acc-tog-wrap { position:relative; flex-shrink:0; }
.acc-tog-inp { opacity:0; width:0; height:0; position:absolute; }
.acc-tog-lbl { display:inline-block; width:36px; height:20px; background:#cbd5e1;
  border-radius:10px; cursor:pointer; position:relative; transition:background .2s; }
.acc-tog-lbl::after { content:''; position:absolute; top:2px; left:2px;
  width:16px; height:16px; background:#fff; border-radius:50%;
  transition:left .2s; box-shadow:0 1px 3px rgba(0,0,0,.2); }
.sgc-toggle:checked + .acc-tog-lbl { background:<?php echo esc_attr($accent) ?>; }
.sgc-toggle:checked + .acc-tog-lbl::after { left:18px; }
.sgc-toggle:disabled + .acc-tog-lbl { opacity:.4; cursor:not-allowed; }

/* Run button */
.acc-run-btn { padding:5px 12px; border-radius:6px; border:none;
  font-size:11px; font-weight:700; cursor:pointer; transition:all .2s;
  display:flex; align-items:center; gap:4px; white-space:nowrap; }
.acc-run-btn.idle  { background:#e2e8f0; color:#374151; }
.acc-run-btn.idle:hover  { background:<?php echo esc_attr($accent) ?>; color:#fff; transform:translateY(-1px); }
.acc-run-btn.running { background:#fef3c7; color:#92400e; cursor:not-allowed; }
.acc-run-btn.done  { background:#dcfce7; color:#166534; cursor:default; }
.acc-run-btn.error { background:#fee2e2; color:#991b1b; cursor:pointer; }
.acc-run-btn.hidden { display:none; }

/* Progress mini bar */
.acc-mini-progress { height:2px; background:#f1f5f9; border-radius:2px; margin-top:4px; overflow:hidden; display:none; }
.acc-mini-fill { height:100%; background:<?php echo esc_attr($accent) ?>; transition:width .4s; border-radius:2px; }

/* Status bar */
.acc-status-bar { background:#f8fafc; border-top:1px solid #e2e8f0;
  padding:8px 16px; font-size:11px; color:#64748b;
  display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.acc-pulse { display:inline-block; width:6px; height:6px; border-radius:50%;
  background:#4ade80; animation:accPulse 1.2s ease infinite; }
@keyframes accPulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }
.acc-timestamp { margin-left:auto; }

/* Scrollable monitor meta */
.acc-monitor-meta { display:flex; gap:16px; padding:8px 0 0; font-size:11px; color:#64748b; flex-wrap:wrap; }
.acc-monitor-meta span strong { color:#1e293b; }
</style>

<div class="acc-head">
    <div>
        <h1>🤖 AI Control Center <span style="font-size:13px;opacity:.7;font-weight:400">v6.6.3</span></h1>
        <p>All AI modules in one place — <?php echo esc_html( sgclabs_ai_providers()[sgclabs_ai_active_provider()]['label'] ?? 'No provider' ); ?> active | <?php echo number_format( $total ); ?> products | <a href="#sgc-api-keys-section" style="color:rgba(255,255,255,.85);text-decoration:underline;" onclick="document.getElementById('sgc-api-keys-section').scrollIntoView({behavior:'smooth'});return false;">🔑 Manage API Keys ↓</a></p>
    </div>
    <div>
        <div class="acc-api-bar">
            <?php foreach ( sgclabs_ai_providers() as $pid => $pdata ):
                $is_set = ! empty( sgclabs_ai_get_provider_key( $pid ) );
                $cls    = ( $pid === $active_provider ) ? 'active' : 'inactive';
            ?>
            <div class="acc-api-chip <?php echo $cls ?>">
                <div class="acc-api-dot <?php echo $is_set ? 'on' : 'off' ?>"></div>
                <?php echo esc_html( $pdata['label'] ) ?>
                <?php if ( $pid === $active_provider ) echo '<span style="font-size:10px;opacity:.7">(active)</span>'; ?>
            </div>
            <?php endforeach; ?>
            <button id="sgc-api-test" style="padding:6px 14px;border-radius:20px;border:2px solid rgba(255,255,255,.5);background:transparent;color:#fff;font-size:12px;font-weight:700;cursor:pointer;">
                Test API
            </button>
        </div>
        <div style="margin-top:8px;font-size:11px;color:rgba(255,255,255,.65);text-align:right">
            Active: <strong style="color:#fff"><?php echo $active_provider !== 'none' ? esc_html( sgclabs_ai_providers()[$active_provider]['label'] ?? ucfirst( $active_provider ) ) : 'No API key set'; ?></strong>
            &nbsp;|&nbsp; <a href="#sgc-api-keys-section" onclick="document.getElementById('sgc-api-keys-section').scrollIntoView({behavior:'smooth'});return false;" style="color:rgba(255,255,255,.8)">🔑 Configure Keys ↓</a>
        </div>
    </div>
</div>

<div class="acc-layout">

    <!-- LEFT PANEL -->
    <div class="acc-left">

        <!-- Live Activity Monitor -->
        <div class="acc-card">
            <div class="acc-card-head">
                <h3><span class="acc-pulse"></span> Live AJAX Monitor</h3>
                <button id="sgc-monitor-clear" style="background:none;border:none;color:#94a3b8;font-size:11px;cursor:pointer;">Clear</button>
            </div>
            <div class="acc-card-body" style="padding:12px">
                <div class="acc-monitor" id="sgc-activity-log">
                    <div class="sgc-log-empty">Waiting for activity...</div>
                </div>
                <div class="acc-monitor-meta">
                    <span>Requests: <strong id="sgc-mon-req">0</strong></span>
                    <span>Errors: <strong id="sgc-mon-err" style="color:#f87171">0</strong></span>
                    <span>Avg: <strong id="sgc-mon-avg">—</strong></span>
                    <span class="acc-timestamp" id="sgc-mon-ts">—</span>
                </div>
            </div>
        </div>

        <!-- AI Command Bar -->
        <div class="acc-card">
            <div class="acc-card-head">
                <h3>🧠 AI Command Bar</h3>
                <span style="font-size:11px;color:#94a3b8">Natural language → modules</span>
            </div>
            <div class="acc-card-body">
                <div class="acc-cmd-wrap">
                    <textarea class="acc-cmd-input" id="sgc-ai-input"
                        placeholder='e.g. "Optimize all product descriptions" or "Fix broken links and update sitemap"'
                        rows="2"></textarea>
                    <button class="acc-cmd-btn" id="sgc-ai-run">
                        ▶ Ask AI
                    </button>
                </div>
                <div class="acc-ai-response" id="sgc-ai-result"></div>
                <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;">
                    <?php
                    $quick = [
                        'Rewrite all product descriptions',
                        'Generate SEO for all products',
                        'Update image alt texts',
                        'Fix all broken links',
                        'Submit sitemap to Google',
                    ];
                    foreach ( $quick as $q ):
                    ?>
                    <button class="sgc-quick-cmd" data-cmd="<?php echo esc_js($q) ?>" style="padding:4px 10px;border-radius:20px;border:1px solid #e2e8f0;background:#f8fafc;font-size:11px;color:#64748b;cursor:pointer;font-family:inherit;">
                        <?php echo esc_html($q) ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Live Stats -->
        <div class="acc-card">
            <div class="acc-card-head">
                <h3>📊 Live Stats <span style="font-size:10px;color:#94a3b8;font-weight:400">auto-refresh 5s</span></h3>
                <span id="sgc-stat-ts" style="font-size:11px;color:#94a3b8">—</span>
            </div>
            <div class="acc-card-body">
                <div class="acc-stats" id="acc-stats-grid">
                    <div class="acc-stat highlight">
                        <div class="acc-stat-label">Total Products</div>
                        <div class="acc-stat-value" id="sgc-stat-total"><?php echo number_format($total) ?></div>
                        <div class="acc-stat-bar" style="width:100%"></div>
                    </div>
                    <div class="acc-stat">
                        <div class="acc-stat-label">Auto SEO</div>
                        <div class="acc-stat-value" id="sgc-stat-seo"><?php echo $seo_pct ?>%</div>
                        <div class="acc-stat-sub" id="sgc-stat-seo-sub">of products</div>
                        <div class="acc-stat-bar" id="sgc-bar-seo" style="width:<?php echo $seo_pct ?>%"></div>
                    </div>
                    <div class="acc-stat">
                        <div class="acc-stat-label">Claude SEO</div>
                        <div class="acc-stat-value" id="sgc-stat-claude">—</div>
                        <div class="acc-stat-sub" id="sgc-stat-claude-sub">products done</div>
                        <div class="acc-stat-bar" id="sgc-bar-claude" style="width:0%"></div>
                    </div>
                    <div class="acc-stat">
                        <div class="acc-stat-label">Focus KW</div>
                        <div class="acc-stat-value" id="sgc-stat-kw">—</div>
                        <div class="acc-stat-bar" id="sgc-bar-kw" style="width:0%"></div>
                    </div>
                    <div class="acc-stat">
                        <div class="acc-stat-label">Image Alt</div>
                        <div class="acc-stat-value" id="sgc-stat-img">—</div>
                        <div class="acc-stat-bar" id="sgc-bar-img" style="width:0%"></div>
                    </div>
                    <div class="acc-stat warn">
                        <div class="acc-stat-label">New Inquiries</div>
                        <div class="acc-stat-value" id="sgc-stat-inquiries">—</div>
                        <div class="acc-stat-sub"><a href="<?php echo admin_url('admin.php?page=sgclabs-quotes-inquiries&tab=inquiries') ?>" style="color:<?php echo esc_attr($accent) ?>">View →</a></div>
                    </div>
                    <div class="acc-stat danger">
                        <div class="acc-stat-label">Broken Links</div>
                        <div class="acc-stat-value" id="sgc-stat-broken">—</div>
                    </div>
                    <div class="acc-stat">
                        <div class="acc-stat-label">Keywords</div>
                        <div class="acc-stat-value" id="sgc-stat-kw">—</div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- .acc-left -->

    <!-- RIGHT PANEL — Module Grid -->
    <div class="acc-right">
        <div style="margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
            <div style="font-size:14px;font-weight:700;color:#1e293b;">Module Control Grid</div>
            <div style="display:flex;gap:8px;">
                <button id="sgc-run-all" style="padding:8px 18px;border-radius:8px;background:<?php echo esc_attr($accent) ?>;color:#fff;border:none;font-size:13px;font-weight:700;cursor:pointer;">
                    ▶ Run All Enabled
                </button>
                <button id="sgc-stop-all" style="padding:8px 18px;border-radius:8px;background:#ef4444;color:#fff;border:none;font-size:13px;font-weight:700;cursor:pointer;">
                    ⏹ Stop All
                </button>
            </div>
        </div>
        <div class="acc-modules-grid">
            <?php foreach ( $modules as $group_id => $group ): ?>
            <div class="acc-module-section" id="acc-group-<?php echo esc_attr($group_id) ?>">
                <div class="acc-mod-header">
                    <span style="font-size:16px"><?php echo $group['icon'] ?></span>
                    <h4><?php echo esc_html($group['label']) ?></h4>
                </div>
                <div class="acc-mod-items">
                    <?php foreach ( $group['items'] as $mod ):
                        $tog_id  = 'sgc-tog-' . $mod['id'];
                        $tog_val = $mod['toggle'] ? get_option( $mod['toggle'], '1' ) : null;
                        $is_on   = $tog_val === null ? true : ( $tog_val === '1' );
                    ?>
                    <div class="acc-mod-row" id="acc-row-<?php echo esc_attr($mod['id']) ?>">
                        <span class="acc-mod-name"><?php echo esc_html($mod['label']) ?></span>

                        <?php if ( $mod['toggle'] ): ?>
                        <div class="acc-tog-wrap" title="Enable/Disable">
                            <input type="checkbox" class="sgc-toggle acc-tog-inp" id="<?php echo esc_attr($tog_id) ?>"
                                data-toggle-id="<?php echo esc_attr($mod['id']) ?>"
                                data-option="<?php echo esc_attr($mod['toggle']) ?>"
                                <?php checked($is_on) ?>>
                            <label class="acc-tog-lbl" for="<?php echo esc_attr($tog_id) ?>"></label>
                        </div>
                        <?php else: ?>
                        <div class="acc-tog-wrap" style="width:36px"></div>
                        <?php endif; ?>

                        <?php if ( $mod['action'] ): ?>
                        <button class="acc-run-btn idle" id="sgc-run-<?php echo esc_attr($mod['id']) ?>"
                            data-action="<?php echo esc_attr($mod['action']) ?>"
                            data-module="<?php echo esc_attr($mod['id']) ?>"
                            data-batch="<?php echo $mod['has_batch'] ? '1' : '0' ?>">
                            ▶ Run
                        </button>
                        <button id="sgc-stop-<?php echo esc_attr($mod['id']) ?>" style="display:none;padding:5px 10px;border-radius:6px;border:none;background:#fee2e2;color:#991b1b;font-size:11px;font-weight:700;cursor:pointer;">⏹</button>
                        <button id="sgc-pause-<?php echo esc_attr($mod['id']) ?>" style="display:none;padding:5px 10px;border-radius:6px;border:none;background:#fef3c7;color:#92400e;font-size:11px;font-weight:700;cursor:pointer;">⏸</button>
                        <?php else: ?>
                        <span style="font-size:11px;color:#94a3b8;min-width:52px;text-align:center">toggle only</span>
                        <?php endif; ?>
                    </div>
                    <div class="acc-mini-progress" id="sgc-prog-<?php echo esc_attr($mod['id']) ?>" style="display:none;position:relative;">
                    <div class="acc-mini-fill" id="sgc-bar-<?php echo esc_attr($mod['id']) ?>" style="width:0%"></div>
                    <span id="sgc-pct-<?php echo esc_attr($mod['id']) ?>" style="position:absolute;right:4px;top:-1px;font-size:9px;color:#64748b;"></span>
                    </div>
                    <div id="sgc-status-<?php echo esc_attr($mod['id']) ?>" style="font-size:11px;color:#64748b;padding:2px 16px 4px;min-height:14px;"></div>
                    <div id="sgc-cnt-wrap-<?php echo esc_attr($mod['id']) ?>" style="display:none;padding:0 16px 6px;font-size:10px;color:#94a3b8;display:flex;gap:8px;">
                      <span>Done: <strong id="sgc-cnt-<?php echo esc_attr($mod['id']) ?>-done">0</strong></span>
                      <span>Skip: <strong id="sgc-cnt-<?php echo esc_attr($mod['id']) ?>-skipped">0</strong></span>
                      <span>Total: <strong id="sgc-cnt-<?php echo esc_attr($mod['id']) ?>-total">0</strong></span>
                      <span style="color:#f87171">Err: <strong id="sgc-cnt-<?php echo esc_attr($mod['id']) ?>-errors">0</strong></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Status bar -->
        <div class="acc-status-bar" style="margin-top:16px;border-radius:8px;border:1px solid #e2e8f0;">
            <span class="acc-pulse"></span>
            <span id="sgc-global-status">Ready — all systems nominal</span>
            <span class="acc-timestamp" id="sgc-global-ts">—</span>
        </div>
    </div><!-- .acc-right -->

</div><!-- .acc-layout -->

<!-- ═══════════════════════════════════════════════════════════════════════
     🔑 AI API KEYS MANAGEMENT — all providers in one place
     ═══════════════════════════════════════════════════════════════════════ -->
<div id="sgc-api-keys-section" style="margin-top:28px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06);">
    <div style="background:linear-gradient(135deg,#1a1a2e 0%,#16213e 100%);padding:20px 28px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <h2 style="margin:0;color:#fff;font-size:18px;font-weight:800;letter-spacing:.3px;">🔑 AI Provider & API Keys</h2>
            <p style="margin:4px 0 0;color:rgba(255,255,255,.65);font-size:13px;">All AI features across the plugin use the selected provider. Switch any time — your data stays intact.</p>
        </div>
        <div style="background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);border-radius:8px;padding:8px 16px;color:#fff;font-size:13px;font-weight:600;">
            Active: <?php
                $ap = sgclabs_ai_active_provider();
                $apd = sgclabs_ai_providers()[$ap] ?? [];
                echo esc_html($apd['label'] ?? $ap);
                if(!empty($apd['model'])) echo ' &nbsp;<code style="background:rgba(255,255,255,.15);padding:1px 6px;border-radius:4px;font-size:11px;">'.esc_html($apd['model']).'</code>';
            ?>
        </div>
    </div>

    <?php
    // Handle API keys form save
    if ( isset($_POST['sgclabs_save_ai_keys']) && current_user_can('manage_options') ) {
        check_admin_referer('sgclabs_ai_keys_nonce');
        sgclabs_ai_save_provider_settings();
        echo '<div class="notice notice-success" style="margin:12px 16px 0;border-radius:6px;"><p>✅ API keys & provider saved!</p></div>';
    }
    ?>

    <div style="padding:24px 28px;">
        <form method="post">
            <?php wp_nonce_field('sgclabs_ai_keys_nonce'); ?>
            <input type="hidden" name="sgclabs_save_ai_keys" value="1">
            <table class="form-table" style="margin:0;">
                <?php sgclabs_ai_render_provider_settings_html(); ?>
            </table>
            <div style="margin-top:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <button type="submit" class="button button-primary" style="font-size:14px;padding:6px 20px;height:auto;background:#27ae60;border-color:#27ae60;">
                    💾 Save API Keys & Provider
                </button>
                <span style="font-size:12px;color:#94a3b8;">Keys are AES-encrypted before storage. Switch provider any time — all keys remain saved.</span>
            </div>
        </form>

        <!-- Where each key is used -->
        <div style="margin-top:22px;padding-top:18px;border-top:1px solid #f1f5f9;">
            <h4 style="margin:0 0 10px;font-size:13px;color:#475569;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">How keys are used across the plugin</h4>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;">
                <?php foreach ([
                    ['🤖 AI Reply Center',     'Drafts & sends inquiry replies'],
                    ['🔍 AI SEO Generator',    'Product descriptions, meta tags'],
                    ['💡 FAQ Generator',        'SEO FAQ blocks on products'],
                    ['🖼️ Image Alt Text',       'Auto-generates from product name'],
                    ['🧠 AI Control Bar',       'Natural language module commands'],
                    ['📝 Content Generator',    'Bulk product content rewriting'],
                ] as [$feat,$desc]): ?>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px;">
                    <div style="font-weight:700;font-size:12px;color:#1e293b;"><?php echo $feat; ?></div>
                    <div style="font-size:11px;color:#64748b;margin-top:2px;"><?php echo $desc; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

</div><!-- #sgc-control-wrap -->


<script>
/* SGC Brain JS is loaded via wp_enqueue_scripts — see admin-menu.php */
/* All module control logic lives in assets/js/sgc-brain.js */
</script>
    <?php
}
