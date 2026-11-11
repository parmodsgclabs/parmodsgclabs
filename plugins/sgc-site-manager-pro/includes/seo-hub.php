<?php
// DEPRECATED v6.6.3 — Merged into AI Content Center (ai-content-engine.php)
// All functionality moved to SGC LABS® > AI Content Center
// File kept for backward compatibility only

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ============================================================
 * SGC LABS® SEO HUB — v6.6.3
 * ============================================================
 * 4 modules in 1 unified page — no separate menu items needed.
 *
 * Tab 1 : SEO Analyzer    — URL audit, score, checklist
 * Tab 2 : Site SEO        — Homepage meta title/desc/schema
 * Tab 3 : Auto SEO Engine — 3-layer bulk generator
 * Tab 4 : Claude AI SEO   — AI descriptions bulk generator
 *
 * Every module has:
 *  - Enable / Disable toggle (persisted in localStorage)
 *  - Start / Stop / Pause button where applicable
 *  - Progress bar + status
 * ============================================================
 */

// ── Remove old individual Claude AI SEO submenu (registered inside ai-claude-seo.php) ──
add_action( 'admin_menu', function () {
    remove_submenu_page( 'sgclabs-manager', 'sgclabs-claude-seo' );
}, 999 );

// SEO Hub menu registered centrally in admin-menu.php

// ============================================================
// AJAX: Save module toggle states to DB (sgch-tog checkboxes)
// ============================================================
add_action( 'wp_ajax_sgclabs_seo_hub_save_toggles', function () {
    check_ajax_referer( 'sgclabs_seo_hub_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $allowed = [ 'analyzer', 'siteseo', 'autoseo', 'imgseo', 'claude' ];
    $saved   = [];
    foreach ( $allowed as $mod ) {
        $key = 'sgclabs_hub_tog_' . $mod;
        if ( array_key_exists( $mod, $_POST ) ) {
            update_option( $key, $_POST[ $mod ] === '1' ? '1' : '0' );
            $saved[] = $mod;
        }
    }
    wp_send_json_success( [ 'saved' => $saved ] );
} );

// AJAX version of site-seo settings save (for inline JS calls)
add_action( 'wp_ajax_sgclabs_save_seo_hub', function () {
    check_ajax_referer( 'sgclabs_seo_hub_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    update_option( 'sgclabs_seo_title',         sanitize_text_field( $_POST['seo_title']           ?? '' ) );
    update_option( 'sgclabs_seo_description',   sanitize_textarea_field( $_POST['seo_description'] ?? '' ) );
    update_option( 'sgclabs_seo_keywords',      sanitize_textarea_field( $_POST['seo_keywords']    ?? '' ) );
    update_option( 'sgclabs_seo_og_image',      esc_url_raw( $_POST['seo_og_image']                ?? '' ) );
    update_option( 'sgclabs_seo_schema_active', isset( $_POST['seo_schema_active'] ) ? '1' : '0' );
    update_option( 'sgclabs_seo_business_type', sanitize_text_field( $_POST['seo_business_type']   ?? 'Manufacturer' ) );
    wp_send_json_success( [ 'message' => 'Saved.' ] );
} );

// ============================================================
// SAVE: Site SEO settings (Tab 2) — form POST version
// ============================================================
add_action( 'admin_post_sgclabs_save_seo_hub', function () {
    if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'sgclabs_seo_hub_nonce' ) ) {
        wp_die( 'Unauthorized' );
    }
    update_option( 'sgclabs_seo_title',         sanitize_text_field( $_POST['seo_title']        ?? '' ) );
    update_option( 'sgclabs_seo_description',   sanitize_textarea_field( $_POST['seo_description'] ?? '' ) );
    update_option( 'sgclabs_seo_keywords',      sanitize_textarea_field( $_POST['seo_keywords']   ?? '' ) );
    update_option( 'sgclabs_seo_og_image',      esc_url_raw( $_POST['seo_og_image']              ?? '' ) );
    update_option( 'sgclabs_seo_schema_active', isset( $_POST['seo_schema_active'] ) ? '1' : '0' );
    update_option( 'sgclabs_seo_business_type', sanitize_text_field( $_POST['seo_business_type'] ?? 'Manufacturer' ) );
    wp_redirect( admin_url( 'admin.php?page=sgclabs-seo-suite&tab=site-seo&saved=1' ) );
    exit;
} );

// ============================================================
// ADMIN PAGE
// ============================================================
function sgclabs_seo_hub_page() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

    global $wpdb;

    $brand  = esc_attr( get_option( 'sgclabs_claude_brand_color', '#1a1a2e' ) );
    $accent = esc_attr( get_option( 'sgclabs_claude_accent_color', '#27ae60' ) );
    $active_tab = sanitize_key( $_GET['tab'] ?? 'analyzer' );
    $saved      = isset( $_GET['saved'] );
    $nonce      = wp_create_nonce( 'sgclabs_nonce' );
    $ajax_url   = esc_js( admin_url( 'admin-ajax.php' ) );

    // Stats for badges
    $total_products = (int) ( wp_count_posts( 'product' )->publish ?? 0 );
    $auto_seo_done  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_sgclabs_seo_cache'" );
    $claude_done    = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_sgclabs_claude_generated_at'" );

    $tabs = [
        'analyzer' => [ 'icon' => '🔍', 'label' => 'SEO Analyzer' ],
        'site-seo' => [ 'icon' => '🏠', 'label' => 'Site SEO' ],
        'auto-seo' => [ 'icon' => '⚡', 'label' => 'Auto SEO Engine',
            'badge' => $total_products > 0 ? round( $auto_seo_done / $total_products * 100 ) . '%' : '0%' ],
        'claude'   => [ 'icon' => '🧠', 'label' => 'Claude AI SEO',
            'badge' => $total_products > 0 ? round( $claude_done / $total_products * 100 ) . '%' : '0%' ],
    ];
    ?>
    <style>
    .sgch-wrap { max-width:1100px; font-family:'Segoe UI',Arial,sans-serif; padding-bottom:60px; }
    .sgch-head { background:linear-gradient(135deg,<?php echo $brand ?>,<?php echo $accent ?>); padding:22px 28px; border-radius:10px; margin:18px 0 20px; display:flex; align-items:center; justify-content:space-between; }
    .sgch-head h1 { color:#fff; margin:0; font-size:22px; font-weight:700; }
    .sgch-head p  { color:rgba(255,255,255,.8); margin:4px 0 0; font-size:13px; }
    /* Tabs */
    .sgch-tabs { display:flex; gap:0; border-bottom:2px solid #e2e8f0; margin-bottom:22px; flex-wrap:wrap; }
    .sgch-tab { padding:11px 22px; font-size:13px; font-weight:600; cursor:pointer; border:none; background:none; color:#64748b; border-bottom:3px solid transparent; margin-bottom:-2px; display:flex; align-items:center; gap:6px; transition:color .2s; }
    .sgch-tab:hover { color:#1e293b; }
    .sgch-tab.active { color:<?php echo $brand ?>; border-bottom-color:<?php echo $accent ?>; }
    .sgch-badge { background:<?php echo $accent ?>; color:#fff; font-size:10px; padding:2px 7px; border-radius:10px; font-weight:700; }
    /* Cards */
    .sgch-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:22px; margin-bottom:18px; }
    .sgch-card h2 { font-size:15px; font-weight:700; margin:0 0 14px; display:flex; align-items:center; gap:8px; }
    .sgch-card .sub { font-size:12px; color:#64748b; margin-bottom:14px; }
    /* Toggle */
    .sgch-toggle-row { display:flex; align-items:center; gap:10px; margin-bottom:14px; padding:10px 14px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0; }
    .sgch-toggle-lbl { display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; color:#374151; cursor:pointer; }
    .sgch-tog { -webkit-appearance:none; appearance:none; width:40px; height:22px; background:#cbd5e1; border-radius:11px; position:relative; cursor:pointer; transition:background .2s; flex-shrink:0; }
    .sgch-tog:checked { background:<?php echo $accent ?>; }
    .sgch-tog::after { content:''; position:absolute; top:3px; left:3px; width:16px; height:16px; background:#fff; border-radius:50%; transition:left .2s; box-shadow:0 1px 3px rgba(0,0,0,.2); }
    .sgch-tog:checked::after { left:21px; }
    .sgch-toggle-desc { font-size:12px; color:#64748b; margin-left:auto; }
    /* Buttons */
    .sgch-btns { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:10px; }
    .sgch-btn { padding:9px 22px; border-radius:6px; font-size:13px; font-weight:700; cursor:pointer; border:none; transition:opacity .15s; }
    .sgch-btn:hover { opacity:.87; }
    .sgch-btn:disabled { opacity:.4; cursor:not-allowed; }
    .sgch-go  { background:#16a34a; color:#fff; }
    .sgch-pau { background:#f59e0b; color:#fff; }
    .sgch-off { background:#dc2626; color:#fff; }
    .sgch-res { background:#2563eb; color:#fff; }
    .sgch-sec { background:#64748b; color:#fff; }
    /* Progress */
    .sgch-pb-wrap { background:#f1f5f9; border-radius:5px; height:10px; margin:10px 0 5px; overflow:hidden; }
    .sgch-pb { height:100%; background:linear-gradient(90deg,<?php echo $accent ?>,<?php echo $brand ?>); border-radius:5px; transition:width .4s; }
    .sgch-ptxt { font-size:12px; color:#64748b; }
    /* Status */
    .sgch-st { border-radius:6px; padding:10px 14px; font-size:13px; margin-top:8px; display:none; }
    .sgch-info { background:#eff6ff; border:1px solid #93c5fd; color:#1d4ed8; }
    .sgch-err  { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
    .sgch-ok   { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .sgch-warn { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    /* Panels */
    .sgch-panel { display:none; }
    .sgch-panel.active { display:block; }
    /* Form */
    .sgch-form-table { width:100%; }
    .sgch-form-table th { width:200px; font-weight:600; font-size:13px; padding:10px 14px 10px 0; vertical-align:top; color:#374151; }
    .sgch-form-table td { padding:8px 0; }
    .sgch-form-table input[type=text],
    .sgch-form-table input[type=url],
    .sgch-form-table textarea { width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:6px; font-size:13px; }
    .sgch-form-table textarea { min-height:80px; resize:vertical; }
    .sgch-char-counter { font-size:11px; margin-top:4px; }
    .sgch-ok-c  { color:#16a34a; }
    .sgch-warn-c{ color:#dc2626; }
    /* SERP preview */
    .sgch-serp { background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:16px 18px; margin-top:12px; }
    .sgch-serp-url   { font-size:12px; color:#1a6b3c; margin-bottom:4px; }
    .sgch-serp-title { font-size:18px; color:#1a0dab; margin-bottom:4px; font-weight:400; cursor:pointer; }
    .sgch-serp-title:hover { text-decoration:underline; }
    .sgch-serp-desc  { font-size:13px; color:#4d5156; line-height:1.5; }
    /* Audit results */
    .sgch-check { display:flex; gap:10px; align-items:flex-start; padding:10px; margin-bottom:6px; border-radius:6px; }
    .sgch-check.pass { background:#f0fdf4; }
    .sgch-check.fail { background:#fef2f2; }
    .sgch-check.warn { background:#fffbeb; }
    .sgch-check-icon { font-size:18px; flex-shrink:0; }
    .sgch-check-body strong { font-size:13px; }
    .sgch-check-body span   { font-size:12px; color:#64748b; display:block; margin-top:2px; }
    /* Score circle */
    .sgch-score { display:inline-flex; align-items:center; justify-content:center; width:100px; height:100px; border-radius:50%; border:8px solid; font-size:32px; font-weight:800; }
    /* Stats grid */
    .sgch-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:18px; }
    .sgch-stat  { background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:14px; text-align:center; }
    .sgch-stat .n { font-size:26px; font-weight:700; }
    .sgch-stat .l { font-size:11px; color:#64748b; margin-top:3px; }
    /* Tab content disabled overlay */
    .sgch-disabled-overlay { position:relative; }
    .sgch-disabled-overlay::after { content:'Module Disabled'; position:absolute; inset:0; background:rgba(248,250,252,.85); display:flex; align-items:center; justify-content:center; font-size:16px; color:#94a3b8; font-weight:600; border-radius:8px; pointer-events:none; display:none; }
    .sgch-disabled-overlay.disabled::after { display:flex; }
    </style>

    <div class="wrap sgch-wrap">

        <div class="sgch-head">
            <div>
                <h1>🎯 SEO Hub <span style="font-size:13px;opacity:.7;font-weight:400">v6.6.3</span></h1>
                <p>SEO Analyzer · Site SEO · Auto SEO Engine · Claude AI SEO — sab ek jagah</p>
            </div>
            <div style="text-align:right;color:rgba(255,255,255,.8);font-size:12px;">
                <?php echo number_format($total_products); ?> products<br>
                Auto SEO: <?php echo $auto_seo_done ?>/<?php echo $total_products ?> &nbsp;|&nbsp; Claude: <?php echo $claude_done ?>/<?php echo $total_products ?>
            </div>
        </div>

        <?php if ($saved): ?>
        <div class="notice notice-success is-dismissible"><p>✅ Settings saved!</p></div>
        <?php endif; ?>

        <!-- TABS -->
        <div class="sgch-tabs">
            <?php foreach ($tabs as $key => $t): ?>
            <button class="sgch-tab <?php echo $active_tab === $key ? 'active' : ''; ?>"
                    onclick="sgchSwitchTab('<?php echo esc_js($key); ?>', this)">
                <?php echo $t['icon']; ?> <?php echo esc_html($t['label']); ?>
                <?php if (!empty($t['badge'])): ?>
                <span class="sgch-badge"><?php echo esc_html($t['badge']); ?></span>
                <?php endif; ?>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 1: SEO ANALYZER                                           -->
        <!-- ============================================================ -->
        <div class="sgch-panel <?php echo $active_tab === 'analyzer' ? 'active' : ''; ?>" id="tab-analyzer">

            <div class="sgch-card">
                <h2>🔍 SEO Analyzer</h2>
                <div class="sub">Kisi bhi URL ka SEO audit karo — title, meta, headings, images, speed sab check hoga.</div>

                <div class="sgch-toggle-row">
                    <label class="sgch-toggle-lbl">
                        <input type="checkbox" class="sgch-tog" id="tog-analyzer" checked>
                        <span>Enable Analyzer</span>
                    </label>
                    <span class="sgch-toggle-desc">Toggle OFF to disable this module</span>
                </div>

                <div class="sgch-disabled-overlay" id="analyzer-body">
                    <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center;">
                        <input type="url" id="sgch-audit-url"
                               placeholder="URL enter karo (blank = homepage)"
                               style="flex:1;min-width:260px;padding:10px 14px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;">
                        <div class="sgch-btns" style="margin:0">
                            <button class="sgch-btn sgch-go" id="sgch-audit-start" onclick="sgchRunAudit()">▶ Run Audit</button>
                            <button class="sgch-btn sgch-off" id="sgch-audit-stop" style="display:none" onclick="sgchStopAudit()">⏹ Stop</button>
                        </div>
                    </div>

                    <div class="sgch-st sgch-info" id="sgch-audit-status">⏳ Analyzing URL... please wait</div>

                    <div id="sgch-audit-result" style="display:none;margin-top:16px;">
                        <div style="text-align:center;margin-bottom:18px;">
                            <div class="sgch-score" id="sgch-score-circle">—</div>
                            <div style="font-size:13px;color:#64748b;margin-top:8px;">SEO Score</div>
                        </div>
                        <div id="sgch-checks-list"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 2: SITE SEO                                               -->
        <!-- ============================================================ -->
        <div class="sgch-panel <?php echo $active_tab === 'site-seo' ? 'active' : ''; ?>" id="tab-site-seo">
            <?php
            $s_title   = get_option('sgclabs_seo_title', 'Laboratory Glassware & Scientific Equipment Manufacturer | SGC LABS® Ambala India');
            $s_desc    = get_option('sgclabs_seo_description', 'SGC LABS® is a leading manufacturer of laboratory glassware, borosilicate glass & scientific glass blowing components in Ambala, India since 2003.');
            $s_kw      = get_option('sgclabs_seo_keywords', 'laboratory glassware, borosilicate glass, scientific equipment');
            $s_og      = get_option('sgclabs_seo_og_image', '');
            $s_schema  = get_option('sgclabs_seo_schema_active', '1');
            $s_biz     = get_option('sgclabs_seo_business_type', 'Manufacturer');
            $tlen = strlen($s_title); $dlen = strlen($s_desc);
            ?>
            <div class="sgch-card">
                <h2>🏠 Site SEO Settings</h2>
                <div class="sub">Homepage meta title, description, OG image aur schema settings.</div>

                <div class="sgch-toggle-row">
                    <label class="sgch-toggle-lbl">
                        <input type="checkbox" class="sgch-tog" id="tog-siteseo" checked>
                        <span>Enable Site SEO</span>
                    </label>
                    <span class="sgch-toggle-desc">Toggle OFF to skip saving changes</span>
                </div>

                <div class="sgch-disabled-overlay" id="siteseo-body">
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="sgch-siteseo-form">
                        <?php wp_nonce_field('sgclabs_seo_hub_nonce'); ?>
                        <input type="hidden" name="action" value="sgclabs_save_seo_hub">

                        <table class="sgch-form-table">
                            <tr>
                                <th>Meta Title</th>
                                <td>
                                    <input type="text" name="seo_title" id="sgch-title-inp"
                                           value="<?php echo esc_attr($s_title); ?>"
                                           oninput="sgchCountChars('sgch-title-inp','sgch-title-cnt',60)">
                                    <div class="sgch-char-counter <?php echo $tlen>60?'sgch-warn-c':'sgch-ok-c'; ?>" id="sgch-title-cnt">
                                        <?php echo $tlen; ?>/60 chars <?php echo $tlen>60?'⚠️ Too long':'✅ Good'; ?>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th>Meta Description</th>
                                <td>
                                    <textarea name="seo_description" id="sgch-desc-inp"
                                              oninput="sgchCountChars('sgch-desc-inp','sgch-desc-cnt',160)"><?php echo esc_textarea($s_desc); ?></textarea>
                                    <div class="sgch-char-counter <?php echo $dlen>160?'sgch-warn-c':'sgch-ok-c'; ?>" id="sgch-desc-cnt">
                                        <?php echo $dlen; ?>/160 chars <?php echo $dlen>160?'⚠️ Too long':'✅ Good'; ?>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th>Focus Keywords</th>
                                <td>
                                    <textarea name="seo_keywords"><?php echo esc_textarea($s_kw); ?></textarea>
                                    <div style="font-size:11px;color:#64748b;margin-top:4px;">Comma separated</div>
                                </td>
                            </tr>
                            <tr>
                                <th>OG Image URL</th>
                                <td><input type="url" name="seo_og_image" value="<?php echo esc_attr($s_og); ?>" placeholder="https://labsglass.com/wp-content/..."></td>
                            </tr>
                            <tr>
                                <th>Business Type</th>
                                <td><input type="text" name="seo_business_type" value="<?php echo esc_attr($s_biz); ?>" style="width:220px"></td>
                            </tr>
                            <tr>
                                <th>Schema Markup</th>
                                <td>
                                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
                                        <input type="checkbox" name="seo_schema_active" value="1" <?php checked($s_schema,'1'); ?>>
                                        Enable Organization Schema (Recommended ✅)
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <!-- Live SERP Preview -->
                        <div style="margin-top:16px;">
                            <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">Google Preview</div>
                            <div class="sgch-serp">
                                <div class="sgch-serp-url">labsglass.com</div>
                                <div class="sgch-serp-title" id="sgch-serp-title"><?php echo esc_html(substr($s_title,0,60)); ?></div>
                                <div class="sgch-serp-desc" id="sgch-serp-desc"><?php echo esc_html(substr($s_desc,0,160)); ?></div>
                            </div>
                        </div>

                        <div style="margin-top:18px;">
                            <button type="submit" class="sgch-btn sgch-go" id="sgch-siteseo-save">💾 Save Settings</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 3: AUTO SEO ENGINE                                        -->
        <!-- ============================================================ -->
        <div class="sgch-panel <?php echo $active_tab === 'auto-seo' ? 'active' : ''; ?>" id="tab-auto-seo">
            <?php
            $auto_pct = $total_products > 0 ? round($auto_seo_done/$total_products*100) : 0;
            $recent_products = function_exists('wc_get_products')
                ? wc_get_products(['limit'=>8,'status'=>'publish','return'=>'ids'])
                : [];
            ?>
            <div class="sgch-stats">
                <div class="sgch-stat"><div class="n" style="color:<?php echo $brand ?>"><?php echo number_format($total_products); ?></div><div class="l">Total Products</div></div>
                <div class="sgch-stat"><div class="n" style="color:#16a34a"><?php echo number_format($auto_seo_done); ?></div><div class="l">SEO Generated</div></div>
                <div class="sgch-stat"><div class="n" style="color:#f59e0b"><?php echo number_format($total_products-$auto_seo_done); ?></div><div class="l">Pending</div></div>
                <div class="sgch-stat"><div class="n" style="color:#2563eb"><?php echo $auto_pct; ?>%</div><div class="l">Complete</div></div>
            </div>

            <!-- Bulk SEO Generator -->
            <div class="sgch-card">
                <h2>⚡ Bulk SEO Generator — 3 Layers</h2>
                <div class="sub">Saare <?php echo number_format($total_products); ?> products ka SEO auto-generate karo — title, long-tail keywords, multilingual, schema, OG tags.</div>

                <div class="sgch-toggle-row">
                    <label class="sgch-toggle-lbl">
                        <input type="checkbox" class="sgch-tog" id="tog-autoseo" checked>
                        <span>Enable Auto SEO</span>
                    </label>
                    <span class="sgch-toggle-desc">OFF karne pe Start button disable ho jaayega</span>
                </div>

                <div class="sgch-disabled-overlay" id="autoseo-body">
                    <div class="sgch-pb-wrap"><div class="sgch-pb" id="sgch-auto-bar" style="width:<?php echo $auto_pct ?>%"></div></div>
                    <div class="sgch-ptxt" id="sgch-auto-ptxt"><?php echo $auto_seo_done ?>/<?php echo $total_products ?> (<?php echo $auto_pct ?>%)</div>

                    <div class="sgch-btns" style="margin-top:12px">
                        <button class="sgch-btn sgch-go"  id="sgch-auto-start">▶ Start</button>
                        <button class="sgch-btn sgch-pau" id="sgch-auto-pause" style="display:none">⏸ Pause</button>
                        <button class="sgch-btn sgch-res" id="sgch-auto-resume" style="display:none">▶ Resume</button>
                        <button class="sgch-btn sgch-off" id="sgch-auto-stop"  style="display:none">⏹ Stop</button>
                        <button class="sgch-btn sgch-sec" onclick="sgchClearAutoSeoCache()">🗑 Clear Cache</button>
                    </div>
                    <div class="sgch-st" id="sgch-auto-status"></div>

                    <!-- 3 Layers info -->
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:16px;">
                        <?php
                        $layers = [
                            ['1','Basic SEO','Title (60 chars), Meta Desc (160), Focus Keywords, product-specific content'],
                            ['2','Long-tail + Location','35+ cities, 25+ countries, city-specific + buy/price/supplier combos'],
                            ['3','Multi-lang + Schema','11 languages auto, Product Schema, OG/Twitter tags, hreflang'],
                        ];
                        foreach ($layers as [$num,$lbl,$desc]): ?>
                        <div style="background:#f8f9ff;border:1px solid #e0e0f0;border-radius:8px;padding:14px;">
                            <div style="background:<?php echo $brand ?>;color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;margin-bottom:8px;"><?php echo $num ?></div>
                            <div style="font-weight:700;font-size:13px;margin-bottom:4px;"><?php echo esc_html($lbl) ?></div>
                            <div style="font-size:11px;color:#64748b;"><?php echo esc_html($desc) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Image SEO -->
            <div class="sgch-card">
                <h2>📸 Bulk Image SEO</h2>
                <div class="sub">Product images resize + WebP + Alt text auto-generate. 10 images/batch.</div>

                <div class="sgch-toggle-row">
                    <label class="sgch-toggle-lbl">
                        <input type="checkbox" class="sgch-tog" id="tog-imgseo" checked>
                        <span>Enable Image SEO</span>
                    </label>
                </div>

                <div class="sgch-disabled-overlay" id="imgseo-body">
                    <div class="sgch-pb-wrap"><div class="sgch-pb" id="sgch-img-bar" style="width:0%"></div></div>
                    <div class="sgch-ptxt" id="sgch-img-ptxt">Ready</div>

                    <div class="sgch-btns" style="margin-top:12px">
                        <button class="sgch-btn sgch-go"  id="sgch-img-start">▶ Start</button>
                        <button class="sgch-btn sgch-pau" id="sgch-img-pause" style="display:none">⏸ Pause</button>
                        <button class="sgch-btn sgch-res" id="sgch-img-resume" style="display:none">▶ Resume</button>
                        <button class="sgch-btn sgch-off" id="sgch-img-stop"  style="display:none">⏹ Stop</button>
                        <button class="sgch-btn sgch-sec" onclick="sgchResetImgSeo()">🔄 Reset & Redo</button>
                    </div>
                    <div class="sgch-st" id="sgch-img-status"></div>
                </div>
            </div>

            <!-- SEO Preview -->
            <div class="sgch-card">
                <h2>👁️ Product SEO Preview</h2>
                <div style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;align-items:center;">
                    <select id="sgch-preview-pid" style="flex:1;min-width:260px;padding:8px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;">
                        <option value="">-- Product select karo --</option>
                        <?php foreach ($recent_products as $pid):
                            $p = wc_get_product($pid); if (!$p) continue; ?>
                        <option value="<?php echo $pid ?>"><?php echo esc_html($p->get_name()); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="sgch-btn sgch-go" onclick="sgchPreviewSeo()">🔍 Preview</button>
                </div>
                <div id="sgch-preview-result" style="display:none;"></div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 4: CLAUDE AI SEO                                          -->
        <!-- ============================================================ -->
        <div class="sgch-panel <?php echo $active_tab === 'claude' ? 'active' : ''; ?>" id="tab-claude">
            <?php
            // Multi-provider: use whichever AI is active
            $c_api_set = function_exists('sgclabs_ai_any_configured') && sgclabs_ai_any_configured();
            $c_active_provider = function_exists('sgclabs_ai_active_provider') ? sgclabs_ai_active_provider() : 'claude';
            $c_providers = function_exists('sgclabs_ai_providers') ? sgclabs_ai_providers() : [];
            $c_active  = get_option('sgclabs_claude_seo_active', '1');
            $c_ui      = get_option('sgclabs_claude_ui_active', '1');
            $c_desc    = get_option('sgclabs_claude_update_desc', '1');
            $c_short   = get_option('sgclabs_claude_update_short', '1');
            $c_brand   = get_option('sgclabs_claude_brand_color', '#1a1a2e');
            $c_accent  = get_option('sgclabs_claude_accent_color', '#27ae60');
            $c_tone    = get_option('sgclabs_claude_tone', 'professional');
            $c_words   = get_option('sgclabs_claude_words_target', 500);
            $c_pct     = $total_products > 0 ? round($claude_done/$total_products*100) : 0;
            $claude_recent = function_exists('wc_get_products')
                ? wc_get_products(['limit'=>50,'status'=>'publish','return'=>'ids'])
                : [];
            ?>

            <?php if (!$c_api_set): ?>
            <div class="notice notice-warning" style="margin-bottom:16px;">
                <p>⚠️ <strong>No AI provider configured.</strong>
                <a href="<?php echo admin_url('admin.php?page=sgclabs-ai-center&tab=control'); ?>" style="font-weight:700;">
                → Add an API key in AI Control Center</a> (Claude, OpenAI, Gemini, Mistral, or DeepSeek)</p>
            </div>
            <?php else: ?>
            <div class="notice notice-info is-dismissible" style="margin-bottom:16px;border-left-color:#3b82f6;">
                <p>🟢 Active AI: <strong><?php echo esc_html($c_providers[$c_active_provider]['label'] ?? $c_active_provider); ?></strong>
                &nbsp;·&nbsp; <code><?php echo esc_html($c_providers[$c_active_provider]['model'] ?? ''); ?></code>
                &nbsp;·&nbsp; <a href="<?php echo admin_url('admin.php?page=sgclabs-ai-center&tab=control'); ?>">Change provider / manage keys →</a></p>
            </div>
            <?php endif; ?>

            <div class="sgch-stats">
                <div class="sgch-stat"><div class="n" style="color:<?php echo $brand ?>"><?php echo number_format($total_products) ?></div><div class="l">Total</div></div>
                <div class="sgch-stat"><div class="n" style="color:#16a34a"><?php echo number_format($claude_done) ?></div><div class="l">AI Generated</div></div>
                <div class="sgch-stat"><div class="n" style="color:#f59e0b"><?php echo number_format($total_products-$claude_done) ?></div><div class="l">Pending</div></div>
                <div class="sgch-stat"><div class="n" style="color:#7c3aed"><?php echo $c_pct ?>%</div><div class="l">Coverage</div></div>
            </div>

            <!-- Bulk Generate -->
            <div class="sgch-card">
                <h2>🧠 Claude AI Bulk Generator</h2>
                <div class="sub">Claude AI se SEO descriptions + product UI har product ke liye auto-generate karo.</div>

                <div class="sgch-toggle-row">
                    <label class="sgch-toggle-lbl">
                        <input type="checkbox" class="sgch-tog" id="tog-claude" <?php checked($c_active,'1') ?>>
                        <span>Enable Claude AI SEO</span>
                    </label>
                    <span class="sgch-toggle-desc"><?php echo $c_api_set ? '✅ API Connected' : '❌ API Key Missing'; ?></span>
                </div>

                <div class="sgch-disabled-overlay" id="claude-body">
                    <div class="sgch-pb-wrap"><div class="sgch-pb" id="sgch-claude-bar" style="width:<?php echo $c_pct ?>%"></div></div>
                    <div class="sgch-ptxt" id="sgch-claude-ptxt"><?php echo $claude_done ?>/<?php echo $total_products ?> (<?php echo $c_pct ?>%)</div>

                    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:10px 14px;font-size:12px;color:#92400e;margin:12px 0;">
                        ⚠️ Yeh original descriptions overwrite karega. Pehle backup lo: WooCommerce → Products → Export
                    </div>

                    <div class="sgch-btns">
                        <button class="sgch-btn sgch-go"  id="sgch-claude-start"  <?php echo $c_api_set?'':'disabled'; ?>>▶ Start</button>
                        <button class="sgch-btn sgch-pau" id="sgch-claude-pause"  style="display:none" <?php echo $c_api_set?'':'disabled'; ?>>⏸ Pause</button>
                        <button class="sgch-btn sgch-res" id="sgch-claude-resume" style="display:none" <?php echo $c_api_set?'':'disabled'; ?>>▶ Resume</button>
                        <button class="sgch-btn sgch-off" id="sgch-claude-stop"   style="display:none" <?php echo $c_api_set?'':'disabled'; ?>>⏹ Stop</button>
                        <button class="sgch-btn sgch-sec" id="sgch-claude-force"  <?php echo $c_api_set?'':'disabled'; ?>>🔄 Force Regen All</button>
                    </div>
                    <div class="sgch-st" id="sgch-claude-status"></div>
                    <div class="sgch-st sgch-ok" id="sgch-claude-done" style="display:none">✅ Bulk generation complete! All products updated.</div>
                </div>
            </div>

            <!-- Single Test -->
            <div class="sgch-card">
                <h2>🔬 Test Single Product</h2>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:14px;">
                    <select id="sgch-test-pid" style="flex:1;min-width:280px;padding:8px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;">
                        <option value="">-- Product select karo --</option>
                        <?php foreach ($claude_recent as $pid):
                            $p = wc_get_product($pid); if (!$p) continue;
                            $gen = get_post_meta($pid,'_sgclabs_claude_generated_at',true) ? ' ✅' : ' ⭕';
                        ?>
                        <option value="<?php echo $pid ?>"><?php echo esc_html($p->get_name().$gen) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="sgch-btn sgch-go" id="sgch-test-btn" <?php echo $c_api_set?'':'disabled'; ?> onclick="sgchTestGenerate()">🧠 Generate Preview</button>
                    <button class="sgch-btn sgch-res" id="sgch-test-save" style="display:none" onclick="sgchTestSave()">💾 Save to Product</button>
                </div>
                <div class="sgch-st sgch-info" id="sgch-test-loader" style="display:none">⏳ Claude AI se generate ho rahi hai... (~15-30 sec)</div>
                <div id="sgch-test-preview" style="display:none;border:1px solid #e2e8f0;border-radius:8px;padding:20px;background:#fff;"></div>
            </div>

            <!-- Settings -->
            <div class="sgch-card">
                <h2>⚙️ AI SEO Settings</h2>
                <!-- API keys are now managed centrally in AI Control Center -->
                <div style="background:#f0f7ff;border:2px solid #3b82f6;border-radius:8px;padding:14px 16px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                    <div>
                        <strong style="font-size:13px;">🔑 API Keys are managed in AI Control Center</strong><br>
                        <span style="font-size:12px;color:#475569;">Claude, OpenAI, Gemini, Mistral, DeepSeek — switch any time.</span>
                    </div>
                    <a href="<?php echo admin_url('admin.php?page=sgclabs-ai-center&tab=control'); ?>#sgc-api-keys-section"
                       class="button button-primary" style="background:#3b82f6;border-color:#3b82f6;font-size:13px;">
                        🔑 Manage API Keys →
                    </a>
                </div>
                <form method="post" id="sgch-claude-settings-form">
                    <?php wp_nonce_field('sgclabs_claude_seo_nonce'); ?>
                    <input type="hidden" name="sgclabs_save_claude_seo" value="1">
                    <table class="sgch-form-table">
                        <tr>
                            <th>Beautiful Product UI</th>
                            <td><label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
                                <input type="checkbox" name="claude_ui_active" value="1" <?php checked($c_ui,'1') ?>>
                                Inject professional CSS on product pages
                            </label></td>
                        </tr>
                        <tr>
                            <th>Update Long Description</th>
                            <td><label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
                                <input type="checkbox" name="claude_update_desc" value="1" <?php checked($c_desc,'1') ?>>
                                Overwrite product long description with AI content
                            </label></td>
                        </tr>
                        <tr>
                            <th>Update Short Description</th>
                            <td><label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
                                <input type="checkbox" name="claude_update_short" value="1" <?php checked($c_short,'1') ?>>
                                Auto-generate short description
                            </label></td>
                        </tr>
                        <tr>
                            <th>Brand Color</th>
                            <td><input type="color" name="claude_brand_color" value="<?php echo esc_attr($c_brand) ?>"></td>
                        </tr>
                        <tr>
                            <th>Accent Color</th>
                            <td><input type="color" name="claude_accent_color" value="<?php echo esc_attr($c_accent) ?>"></td>
                        </tr>
                        <tr>
                            <th>Writing Tone</th>
                            <td>
                                <select name="claude_tone" style="padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;">
                                    <?php foreach (['professional'=>'Professional','technical'=>'Technical & Detailed','conversational'=>'Conversational','formal'=>'Formal B2B'] as $v=>$l): ?>
                                    <option value="<?php echo $v ?>" <?php selected($c_tone,$v) ?>><?php echo $l ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Target Word Count</th>
                            <td><input type="number" name="claude_words_target" value="<?php echo intval($c_words) ?>" min="300" max="1200" style="width:90px"> words</td>
                        </tr>
                    </table>
                    <div style="margin-top:14px;">
                        <button type="submit" class="sgch-btn sgch-go">💾 Save Settings</button>
                    </div>
                </form>
            </div>
        </div>

    </div><!-- .sgch-wrap -->

    <script>
    (function($){
        var NONCE    = '<?php echo esc_js($nonce); ?>';
        var AJAX     = '<?php echo $ajax_url; ?>';
        var TOTAL    = <?php echo (int)$total_products; ?>;
        var LS       = window.localStorage;

        // ── Tab Switching ──────────────────────────────────────
        window.sgchSwitchTab = function(key, btn) {
            $('.sgch-panel').removeClass('active');
            $('.sgch-tab').removeClass('active');
            $('#tab-' + key).addClass('active');
            $(btn).addClass('active');
            LS.setItem('sgch_active_tab', key);
        };

        // ── Toggle persistence ─────────────────────────────────
        // saveTog: localStorage (instant) + DB (persistent)
        var _hubTogTimer=null, _hubTogPending={};
        function saveTog(id, v) {
            try{ LS.setItem('sgch_tog_'+id, v?'1':'0'); }catch(e){}
            var modName = id.replace('tog-','');
            _hubTogPending[modName] = v ? '1' : '0';
            clearTimeout(_hubTogTimer);
            _hubTogTimer = setTimeout(function(){
                var payload = { action:'sgclabs_seo_hub_save_toggles', nonce:'<?php echo esc_js(wp_create_nonce('sgclabs_seo_hub_nonce')); ?>' };
                $.each(_hubTogPending, function(k,val){ payload[k]=val; });
                _hubTogPending = {};
                $.post(ajaxurl, payload);
            }, 600);
        }
        function loadTog(id, def) { try{ var v=LS.getItem('sgch_tog_'+id); return v===null?def:v==='1'; }catch(e){ return def; } }

        $('.sgch-tog').each(function(){
            var id=$(this).attr('id'), def=$(this).is(':checked');
            if(id) $(this).prop('checked', loadTog(id, def));
        });
        $(document).on('change', '.sgch-tog', function(){
            var id=$(this).attr('id');
            if(id) saveTog(id, $(this).is(':checked'));
            // Sync disabled state on body
            var bodyId = id.replace('tog-','') + '-body';
            var $body = $('#' + bodyId.replace('autoseo','autoseo').replace('siteseo','siteseo'));
            if($(this).is(':checked')) { $body.removeClass('disabled'); }
            else { $body.addClass('disabled'); }
        });

        // Apply disabled state on load
        ['analyzer','siteseo','autoseo','imgseo','claude'].forEach(function(k){
            var $tog = $('#tog-'+k);
            if($tog.length && !$tog.is(':checked')) {
                $('#'+k+'-body').addClass('disabled');
            }
        });

        // Disable start buttons when toggle is off
        $('#tog-autoseo').on('change', function(){ $('#sgch-auto-start').prop('disabled', !$(this).is(':checked')); });
        $('#tog-imgseo').on('change',  function(){ $('#sgch-img-start').prop('disabled', !$(this).is(':checked')); });
        $('#tog-claude').on('change',  function(){ $('#sgch-claude-start,#sgch-claude-force').prop('disabled', !$(this).is(':checked')); });
        $('#tog-siteseo').on('change', function(){ $('#sgch-siteseo-save').prop('disabled', !$(this).is(':checked')); });
        $('#tog-analyzer').on('change',function(){ $('#sgch-audit-start').prop('disabled', !$(this).is(':checked')); });

        // ── Char counters ──────────────────────────────────────
        window.sgchCountChars = function(inpId, cntId, max) {
            var len = $('#'+inpId).val().length;
            var ok  = len <= max;
            $('#'+cntId).removeClass('sgch-ok-c sgch-warn-c').addClass(ok?'sgch-ok-c':'sgch-warn-c')
                .text(len+'/'+max+' chars '+(ok?'✅ Good':'⚠️ Too long'));
            // Live SERP preview
            if(inpId==='sgch-title-inp') $('#sgch-serp-title').text($('#'+inpId).val().substring(0,60));
            if(inpId==='sgch-desc-inp')  $('#sgch-serp-desc').text($('#'+inpId).val().substring(0,160));
        };

        // ── SEO Analyzer ───────────────────────────────────────
        var auditRunning = false;
        window.sgchRunAudit = function() {
            if(!$('#tog-analyzer').is(':checked')){ alert('Analyzer disabled. Enable the toggle first.'); return; }
            if(auditRunning) return;
            var url = $('#sgch-audit-url').val() || '<?php echo esc_js(home_url("/")); ?>';
            auditRunning = true;
            $('#sgch-audit-start').hide(); $('#sgch-audit-stop').show();
            $('#sgch-audit-status').removeClass('sgch-err').addClass('sgch-info').show().text('⏳ Analyzing '+url+' ...');
            $('#sgch-audit-result').hide();
            $.post(AJAX, {action:'sgclabs_run_seo_audit', nonce:NONCE, url:url}, function(res){
                auditRunning = false;
                $('#sgch-audit-start').show(); $('#sgch-audit-stop').hide();
                $('#sgch-audit-status').hide();
                if(!res||!res.success){ $('#sgch-audit-status').removeClass('sgch-info').addClass('sgch-err').show().text('❌ Error: '+(res&&res.data?res.data:'Unknown')); return; }
                var d=res.data, score=d.score||0;
                var col = score>=80?'#16a34a':(score>=50?'#f59e0b':'#dc2626');
                $('#sgch-score-circle').text(score).css({'border-color':col,'color':col});
                var html='';
                (d.checks||[]).forEach(function(c){
                    var cls=c.pass?'pass':(c.warn?'warn':'fail');
                    var icon=c.pass?'✅':(c.warn?'⚠️':'❌');
                    html+='<div class="sgch-check '+cls+'"><div class="sgch-check-icon">'+icon+'</div><div class="sgch-check-body"><strong>'+escH(c.label)+'</strong><span>'+escH(c.detail)+'</span></div></div>';
                });
                $('#sgch-checks-list').html(html);
                $('#sgch-audit-result').show();
            }).fail(function(){ auditRunning=false; $('#sgch-audit-start').show(); $('#sgch-audit-stop').hide(); $('#sgch-audit-status').removeClass('sgch-info').addClass('sgch-err').show().text('❌ AJAX error. Try again.'); });
        };
        window.sgchStopAudit = function() { auditRunning=false; $('#sgch-audit-start').show(); $('#sgch-audit-stop').hide(); $('#sgch-audit-status').hide(); };

        // ── Auto SEO Engine ────────────────────────────────────
        var autoRunning=false, autoPaused=false, autoOffset=0, autoErrors=0;
        var imgRunning=false,  imgPaused=false,  imgOffset=0,  imgErrors=0;

        function makeRunner(cfg) {
            var running=false, paused=false, offset=0, errors=0;
            var $start=$(cfg.start), $pause=$(cfg.pause), $resume=$(cfg.resume), $stop=$(cfg.stop);
            var $bar=$(cfg.bar), $ptxt=$(cfg.ptxt), $st=$(cfg.status);

            $start.on('click', function(){
                if(!$(cfg.toggle).is(':checked')){ alert('Module disabled. Enable the toggle first.'); return; }
                if(running){ alert('Already running!'); return; }
                if(cfg.confirm && !confirm(cfg.confirm)) return;
                running=true; paused=false; offset=0; errors=0;
                $start.hide(); $pause.show(); $stop.show();
                $st.removeClass('sgch-err sgch-ok').addClass('sgch-info').show();
                run();
            });
            $pause.on('click', function(){
                paused=true; $pause.hide(); $resume.show();
                $st.text('⏸ Paused. Click Resume to continue.');
            });
            $resume.on('click', function(){
                paused=false; $pause.show(); $resume.hide();
                run();
            });
            $stop.on('click', function(){
                running=false; paused=false; offset=0;
                $start.show(); $pause.hide(); $resume.hide(); $stop.hide();
                $st.text('⏹ Stopped.').removeClass('sgch-info').addClass('sgch-warn');
            });

            function run(){
                if(!running||paused) return;
                $st.text('⚡ Processing batch at offset '+offset+'...');
                $.ajax({url:AJAX, type:'POST', timeout:90000, data:$.extend({nonce:NONCE, offset:offset, batch:cfg.batch||5}, cfg.data||{}), success:function(res){
                    errors=0;
                    if(!res||!res.success){ retry('Error: '+(res&&res.data?res.data:'Unknown')); return; }
                    var d=res.data;
                    offset=d.next_offset||offset+5;
                    $bar.css('width',(d.percent||0)+'%');
                    $ptxt.text((d.processed||d.done||0)+'/'+d.total+' ('+(d.percent||0)+'%)');
                    $st.text('✅ '+(d.processed||d.done||0)+'/'+d.total+' done ('+(d.percent||0)+'%)');
                    if(d.done||d.finished||d.percent>=100){ running=false; $start.show(); $pause.hide(); $resume.hide(); $stop.hide(); $st.removeClass('sgch-info').addClass('sgch-ok').text('✅ All done! '+d.total+' processed.'); if(cfg.onDone) cfg.onDone(d); return; }
                    setTimeout(run, cfg.gap||500);
                }, error:function(xhr,status){ retry('Network: '+status); }});
            }
            function retry(msg){
                errors++;
                if(errors<=5){ var w=errors*3; $st.text('⚠️ Retry '+errors+'/5: '+msg+' — '+w+'s…'); setTimeout(run, w*1000); }
                else { running=false; $start.show(); $pause.hide(); $resume.hide(); $stop.hide(); $st.removeClass('sgch-info').addClass('sgch-err').text('❌ Failed after 5 retries. '+msg); }
            }
        }

        makeRunner({
            toggle:'#tog-autoseo', start:'#sgch-auto-start', pause:'#sgch-auto-pause',
            resume:'#sgch-auto-resume', stop:'#sgch-auto-stop',
            bar:'#sgch-auto-bar', ptxt:'#sgch-auto-ptxt', status:'#sgch-auto-status',
            data:{action:'sgclabs_bulk_seo'}, batch:10, gap:80,
            confirm:'Saare '+TOTAL+' products ka Auto SEO generate karein?\n\nStart hoga — Pause/Stop se rok sakte ho.',
        });

        makeRunner({
            toggle:'#tog-imgseo', start:'#sgch-img-start', pause:'#sgch-img-pause',
            resume:'#sgch-img-resume', stop:'#sgch-img-stop',
            bar:'#sgch-img-bar', ptxt:'#sgch-img-ptxt', status:'#sgch-img-status',
            data:{action:'sgclabs_bulk_image_seo'}, batch:10, gap:200,
            confirm:'Sab product images ka SEO process karein? (Resize + WebP + Alt text)',
        });

        // ── Claude Bulk ────────────────────────────────────────
        var claudeRunning=false, claudePaused=false, claudeOffset=0, claudeErrors=0, claudeForce=false;

        function claudeRun(force) {
            if(!$('#tog-claude').is(':checked')){ alert('Claude AI disabled. Enable the toggle first.'); return; }
            if(claudeRunning){ alert('Already running!'); return; }
            if(!confirm('Claude AI se '+(force?'ALL':'pending')+' products ke descriptions generate karein?\n\n'+(force?'⚠️ Existing descriptions overwrite honge.':'')+'\n\nProceed?')) return;
            claudeRunning=true; claudePaused=false; claudeOffset=0; claudeErrors=0; claudeForce=force;
            $('#sgch-claude-start,#sgch-claude-force').hide();
            $('#sgch-claude-pause,#sgch-claude-stop').show();
            $('#sgch-claude-status').removeClass('sgch-err sgch-ok').addClass('sgch-info').show();
            $('#sgch-claude-done').hide();
            claudeNext();
        }

        function claudeNext(){
            if(!claudeRunning||claudePaused) return;
            $('#sgch-claude-status').text('⚡ Processing batch at offset '+claudeOffset+'...');
            $.ajax({url:AJAX, type:'POST', timeout:120000, data:{action:'sgclabs_claude_bulk', nonce:NONCE, offset:claudeOffset, batch:10, force:claudeForce?1:0},
                success:function(res){
                    claudeErrors=0;
                    if(!res||!res.success){ claudeRetry('Error: '+(res&&res.data?res.data:'Unknown')); return; }
                    var d=res.data;
                    claudeOffset=d.next_offset||claudeOffset+10;
                    $('#sgch-claude-bar').css('width',(d.percent||0)+'%');
                    $('#sgch-claude-ptxt').text((d.processed||0)+'/'+d.total+' ('+(d.percent||0)+'%)');
                    $('#sgch-claude-status').text('✅ '+(d.processed||0)+'/'+d.total+' done ('+(d.percent||0)+'%)');
                    if(d.done||d.finished||d.percent>=100){ claudeRunning=false; $('#sgch-claude-start,#sgch-claude-force').show(); $('#sgch-claude-pause,#sgch-claude-stop,#sgch-claude-resume').hide(); $('#sgch-claude-status').hide(); $('#sgch-claude-done').show(); return; }
                    setTimeout(claudeNext, 500);
                }, error:function(xhr,s){ claudeRetry('Network: '+s); }
            });
        }
        function claudeRetry(msg){ claudeErrors++; if(claudeErrors<=5){ var w=claudeErrors*3; $('#sgch-claude-status').text('⚠️ Retry '+claudeErrors+'/5 — '+w+'s…'); setTimeout(claudeNext,w*1000); } else { claudeRunning=false; $('#sgch-claude-start,#sgch-claude-force').show(); $('#sgch-claude-pause,#sgch-claude-stop,#sgch-claude-resume').hide(); $('#sgch-claude-status').removeClass('sgch-info').addClass('sgch-err').text('❌ Failed: '+msg); } }

        $('#sgch-claude-start').on('click', function(){ claudeRun(false); });
        $('#sgch-claude-force').on('click', function(){ claudeRun(true); });
        $('#sgch-claude-pause').on('click', function(){ claudePaused=true; $(this).hide(); $('#sgch-claude-resume').show(); $('#sgch-claude-status').text('⏸ Paused.'); });
        $('#sgch-claude-resume').on('click', function(){ claudePaused=false; $(this).hide(); $('#sgch-claude-pause').show(); claudeNext(); });
        $('#sgch-claude-stop').on('click', function(){ claudeRunning=false; claudePaused=false; claudeOffset=0; $('#sgch-claude-start,#sgch-claude-force').show(); $('#sgch-claude-pause,#sgch-claude-stop,#sgch-claude-resume').hide(); $('#sgch-claude-status').removeClass('sgch-info').addClass('sgch-warn').text('⏹ Stopped.'); });

        // ── Single Test ────────────────────────────────────────
        var testPid=null, testData=null;
        window.sgchTestGenerate = function(){
            testPid=$('#sgch-test-pid').val(); if(!testPid){ alert('Product select karo.'); return; }
            $('#sgch-test-btn').prop('disabled',true);
            $('#sgch-test-loader').addClass('sgch-info').show();
            $('#sgch-test-preview').hide(); $('#sgch-test-save').hide();
            $.ajax({url:AJAX,type:'POST',timeout:60000,data:{action:'sgclabs_claude_generate_single',nonce:NONCE,product_id:testPid},success:function(res){
                $('#sgch-test-btn').prop('disabled',false); $('#sgch-test-loader').hide();
                if(!res||!res.success){ alert('Error: '+(res&&res.data?res.data:'Unknown')); return; }
                testData=res.data;
                $('#sgch-test-preview').html(res.data.html||'<p>No preview available.</p>').show();
                $('#sgch-test-save').show();
            },error:function(){ $('#sgch-test-btn').prop('disabled',false); $('#sgch-test-loader').hide(); alert('AJAX error.'); }});
        };
        window.sgchTestSave = function(){
            if(!testPid||!testData) return;
            $.post(AJAX,{action:'sgclabs_claude_save_single',nonce:NONCE,product_id:testPid,data:JSON.stringify(testData)},function(res){
                if(res&&res.success){ alert('✅ Saved to product!'); }
                else alert('Error: '+(res&&res.data?res.data:'Unknown'));
            });
        };

        // ── Auto SEO Preview ───────────────────────────────────
        window.sgchPreviewSeo = function(){
            var pid=$('#sgch-preview-pid').val(); if(!pid){ alert('Product select karo.'); return; }
            $.post(AJAX,{action:'sgclabs_preview_seo',nonce:NONCE,product_id:pid},function(res){
                if(!res||!res.success) return;
                var d=res.data;
                var html='<div style="background:#f8f9ff;border:1px solid #e0e0f0;border-radius:8px;padding:14px;margin-bottom:12px;font-size:13px;">'
                    +'<strong>Title:</strong> '+escH(d.title)+'<br>'
                    +'<strong>Desc:</strong> '+escH(d.meta_desc)+'<br>'
                    +'<strong>Keywords (sample):</strong> '+escH(d.keywords.substring(0,120))+'...</div>';
                $('#sgch-preview-result').html(html).show();
            });
        };

        // ── Cache / Reset helpers ──────────────────────────────
        window.sgchClearAutoSeoCache = function(){
            if(!confirm('Clear all SEO cache? Products will regenerate on next run.')) return;
            $.post(AJAX,{action:'sgclabs_clear_seo_cache',nonce:NONCE},function(res){ if(res&&res.success){ alert('✅ Cache cleared!'); location.reload(); } });
        };
        window.sgchResetImgSeo = function(){
            if(!confirm('Reset image SEO flags? All images will be re-processed on next run.')) return;
            $.post(AJAX,{action:'sgclabs_reset_image_seo',nonce:NONCE},function(res){ if(res&&res.success){ imgOffset=0; alert('✅ Reset done.'); } });
        };

        // ── Utility ────────────────────────────────────────────
        function escH(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    })(jQuery);
    </script>
    <?php
}
