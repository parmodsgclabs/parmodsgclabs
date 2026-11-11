<?php
/**
 * SGC LABS® Safe Delete Module v1.0.0
 *
 * Agar kabhi plugin delete karna pade to pehle sab data export karo.
 * Yeh page ek "Pre-Delete Checklist" provide karta hai:
 *
 * 1. SEO Meta Data      → JSON export (titles, descriptions, focus KW)
 * 2. Redirections       → CSV export (sare 301/302 rules)
 * 3. B2B Quotes         → CSV export
 * 4. Keywords Database  → CSV export
 * 5. Plugin Settings    → JSON export
 * 6. Content Backups    → JSON export
 *
 * @package SGC LABS Manager Pro
 * @since   6.6.6
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================
// AJAX: Export SEO Meta (titles, desc, focus KW per product)
// ============================================================
add_action( 'wp_ajax_sgclabs_safe_export_seo', function() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

    global $wpdb;

    $rows = $wpdb->get_results(
        "SELECT p.ID, p.post_title, p.post_type,
                MAX(CASE WHEN pm.meta_key='_sgclabs_seo_title'       THEN pm.meta_value END) as seo_title,
                MAX(CASE WHEN pm.meta_key='_sgclabs_seo_description' THEN pm.meta_value END) as seo_desc,
                MAX(CASE WHEN pm.meta_key='_sgclabs_focus_keyword'   THEN pm.meta_value END) as focus_kw,
                MAX(CASE WHEN pm.meta_key='_sgclabs_canonical_url'   THEN pm.meta_value END) as canonical,
                MAX(CASE WHEN pm.meta_key='_sgclabs_seo_score'       THEN pm.meta_value END) as seo_score,
                MAX(CASE WHEN pm.meta_key='_sgclabs_robots_noindex'  THEN pm.meta_value END) as noindex
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
         WHERE pm.meta_key LIKE '_sgclabs_%'
           AND p.post_status = 'publish'
         GROUP BY p.ID
         ORDER BY p.ID ASC",
        ARRAY_A
    );

    header( 'Content-Type: text/csv; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="sgclabs-seo-data-' . date( 'Y-m-d' ) . '.csv"' );
    header( 'Pragma: no-cache' );

    $out = fopen( 'php://output', 'w' );
    fprintf( $out, chr(0xEF).chr(0xBB).chr(0xBF) ); // UTF-8 BOM for Excel
    fputcsv( $out, [ 'Post ID', 'Post Title', 'Post Type', 'SEO Title', 'Meta Description', 'Focus Keyword', 'Canonical URL', 'SEO Score', 'No Index' ] );

    foreach ( $rows as $r ) {
        fputcsv( $out, [
            $r['ID'],
            $r['post_title'],
            $r['post_type'],
            $r['seo_title']   ?? '',
            $r['seo_desc']    ?? '',
            $r['focus_kw']    ?? '',
            $r['canonical']   ?? '',
            $r['seo_score']   ?? '',
            $r['noindex']     ?? '0',
        ] );
    }
    fclose( $out );
    exit;
} );

// ============================================================
// AJAX: Export Keywords Database
// ============================================================
add_action( 'wp_ajax_sgclabs_safe_export_keywords', function() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

    global $wpdb;

    header( 'Content-Type: text/csv; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="sgclabs-keywords-' . date( 'Y-m-d' ) . '.csv"' );

    $out = fopen( 'php://output', 'w' );
    fprintf( $out, chr(0xEF).chr(0xBB).chr(0xBF) );
    fputcsv( $out, [ 'Type', 'Keyword', 'Language', 'Category', 'Impressions', 'Clicks', 'Position', 'Active' ] );

    // Global keywords
    $globals = $wpdb->get_results( "SELECT keyword, language, impressions, clicks, position, active FROM {$wpdb->prefix}sgclabs_kw_global ORDER BY impressions DESC", ARRAY_A );
    foreach ( $globals as $r ) {
        fputcsv( $out, [ 'Global', $r['keyword'], $r['language'], '', $r['impressions'], $r['clicks'], $r['position'], $r['active'] ] );
    }

    // Category keywords
    $cats = $wpdb->get_results( "SELECT keyword, language, category_name, active FROM {$wpdb->prefix}sgclabs_kw_category ORDER BY category_name", ARRAY_A );
    foreach ( $cats as $r ) {
        fputcsv( $out, [ 'Category', $r['keyword'], $r['language'], $r['category_name'], '', '', '', $r['active'] ] );
    }

    // GSC keywords
    $gsc = $wpdb->get_results( "SELECT keyword, impressions, clicks, position FROM {$wpdb->prefix}sgclabs_kw_gsc ORDER BY impressions DESC", ARRAY_A );
    foreach ( $gsc as $r ) {
        fputcsv( $out, [ 'GSC', $r['keyword'], 'en', '', $r['impressions'], $r['clicks'], $r['position'], '1' ] );
    }

    fclose( $out );
    exit;
} );

// ============================================================
// AJAX: Export B2B Quotes
// ============================================================
add_action( 'wp_ajax_sgclabs_safe_export_quotes', function() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

    global $wpdb;

    $rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sgclabs_b2b_quotes ORDER BY created_at DESC", ARRAY_A );

    header( 'Content-Type: text/csv; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="sgclabs-b2b-quotes-' . date( 'Y-m-d' ) . '.csv"' );

    $out = fopen( 'php://output', 'w' );
    fprintf( $out, chr(0xEF).chr(0xBB).chr(0xBF) );

    if ( ! empty( $rows ) ) {
        fputcsv( $out, array_keys( $rows[0] ) );
        foreach ( $rows as $r ) fputcsv( $out, array_values( $r ) );
    } else {
        fputcsv( $out, [ 'No quotes found' ] );
    }

    fclose( $out );
    exit;
} );

// ============================================================
// AJAX: Export Plugin Settings (JSON)
// ============================================================
add_action( 'wp_ajax_sgclabs_safe_export_settings', function() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

    global $wpdb;

    $options = $wpdb->get_results(
        "SELECT option_name, option_value FROM {$wpdb->options}
         WHERE option_name LIKE 'sgclabs_%'
           AND option_name NOT LIKE '%api_key%'
           AND option_name NOT LIKE '%secret%'
           AND option_name NOT LIKE '%token%'
           AND option_name NOT LIKE '%password%'
         ORDER BY option_name ASC",
        ARRAY_A
    );

    $export = [
        'exported_at' => current_time( 'mysql' ),
        'version'     => defined( 'SGCLABS_VERSION' ) ? SGCLABS_VERSION : 'unknown',
        'site'        => home_url(),
        'settings'    => $options,
    ];

    header( 'Content-Type: application/json; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="sgclabs-settings-' . date( 'Y-m-d' ) . '.json"' );
    echo wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
    exit;
} );

// ============================================================
// AJAX: Export Content Backups
// ============================================================
add_action( 'wp_ajax_sgclabs_safe_export_backups', function() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

    global $wpdb;

    $rows = $wpdb->get_results(
        "SELECT post_id, meta_value FROM {$wpdb->postmeta}
         WHERE meta_key = '_sgclabs_content_backup'
         ORDER BY post_id ASC",
        ARRAY_A
    );

    $backups = [];
    foreach ( $rows as $r ) {
        $data = maybe_unserialize( $r['meta_value'] );
        if ( is_array( $data ) ) {
            $backups[] = array_merge( [ 'post_id' => $r['post_id'] ], $data );
        }
    }

    header( 'Content-Type: application/json; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="sgclabs-content-backups-' . date( 'Y-m-d' ) . '.json"' );
    echo wp_json_encode( [ 'count' => count( $backups ), 'backups' => $backups ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
    exit;
} );

// ============================================================
// AJAX: Get export stats (counts)
// ============================================================
add_action( 'wp_ajax_sgclabs_safe_delete_stats', function() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

    global $wpdb;

    wp_send_json_success( [
        'seo_products' => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key LIKE '_sgclabs_%'" ),
        'redirects'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_redirects" ),
        'quotes'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_b2b_quotes" ),
        'kw_global'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_kw_global" ),
        'kw_category'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_kw_category" ),
        'settings'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'sgclabs_%'" ),
        'backups'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_sgclabs_content_backup'" ),
    ] );
} );

// ============================================================
// ADMIN PAGE
// ============================================================
function sgclabs_safe_delete_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $nonce = wp_create_nonce( 'sgclabs_nonce' );
    ?>
    <div class="wrap sgclabs-wrap" style="max-width:800px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">

        <h1 style="font-size:22px;font-weight:800;color:#1a1a2e;margin-bottom:6px;">🛡️ Safe Delete — Pre-Delete Export</h1>
        <p style="color:#6b7280;margin-bottom:6px;">Plugin delete karne se pehle sab data export karo. Iske baad safely delete kar sakte ho.</p>

        <!-- Warning Box -->
        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:16px;margin-bottom:24px;display:flex;gap:12px;align-items:flex-start;">
            <span style="font-size:24px;flex-shrink:0;">⚠️</span>
            <div>
                <div style="font-size:14px;font-weight:700;color:#991b1b;margin-bottom:4px;">Plugin delete hone par sab data permanently delete ho jayega!</div>
                <div style="font-size:12px;color:#7f1d1d;line-height:1.6;">
                    Neeche diye gaye sare exports download karo → phir WordPress → Plugins → Delete karo.<br>
                    Agar baad mein plugin reinstall karo to import karke sab data wapas aa jayega.
                </div>
            </div>
        </div>

        <!-- Stats Loading -->
        <div id="sgc-sd-stats" style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:24px;">
            <div style="background:#f8f9fa;border:1px solid #e5e7eb;border-radius:8px;padding:12px;text-align:center;color:#9ca3af;font-size:12px;">Loading...</div>
        </div>

        <!-- Export Checklist -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;margin-bottom:20px;">

            <?php
            $exports = [
                [
                    'id'    => 'seo',
                    'icon'  => '🔍',
                    'title' => 'SEO Meta Data',
                    'desc'  => 'Sare products ke SEO titles, meta descriptions, focus keywords, SEO scores',
                    'action'=> 'sgclabs_safe_export_seo',
                    'file'  => 'CSV',
                    'color' => '#2563eb',
                    'bg'    => '#eff6ff',
                ],
                [
                    'id'    => 'redirects',
                    'icon'  => '↩️',
                    'title' => 'Redirections (301/302)',
                    'desc'  => 'Sare redirect rules — bahut zaroori, broken links prevent karti hain',
                    'action'=> 'sgclabs_redirect_export',
                    'file'  => 'CSV',
                    'color' => '#dc2626',
                    'bg'    => '#fef2f2',
                    'direct'=> true,
                ],
                [
                    'id'    => 'keywords',
                    'icon'  => '🔑',
                    'title' => 'Keywords Database',
                    'desc'  => 'Global keywords, category keywords, GSC imported keywords',
                    'action'=> 'sgclabs_safe_export_keywords',
                    'file'  => 'CSV',
                    'color' => '#7c3aed',
                    'bg'    => '#f5f3ff',
                ],
                [
                    'id'    => 'quotes',
                    'icon'  => '📋',
                    'title' => 'B2B Quotes',
                    'desc'  => 'Sare customer quote requests with contact details',
                    'action'=> 'sgclabs_safe_export_quotes',
                    'file'  => 'CSV',
                    'color' => '#d97706',
                    'bg'    => '#fffbeb',
                ],
                [
                    'id'    => 'settings',
                    'icon'  => '⚙️',
                    'title' => 'Plugin Settings',
                    'desc'  => 'Sari plugin settings — toggle states, preferences, configurations',
                    'action'=> 'sgclabs_safe_export_settings',
                    'file'  => 'JSON',
                    'color' => '#059669',
                    'bg'    => '#f0fdf4',
                ],
                [
                    'id'    => 'backups',
                    'icon'  => '💾',
                    'title' => 'Content Backups',
                    'desc'  => 'AI se pehle save kiye gaye original product content backups',
                    'action'=> 'sgclabs_safe_export_backups',
                    'file'  => 'JSON',
                    'color' => '#0284c7',
                    'bg'    => '#f0f9ff',
                ],
            ];
            foreach ( $exports as $i => $e ) :
                $border = $i > 0 ? 'border-top:1px solid #f3f4f6;' : '';
            ?>
            <div style="display:flex;align-items:center;gap:14px;padding:16px 20px;<?php echo $border; ?>">
                <!-- Checkbox -->
                <div id="sgc-check-<?php echo esc_attr($e['id']); ?>"
                     style="width:22px;height:22px;border-radius:50%;border:2px solid #d1d5db;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:12px;background:#f9fafb;">
                </div>

                <!-- Icon + Info -->
                <div style="width:40px;height:40px;border-radius:10px;background:<?php echo esc_attr($e['bg']); ?>;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;">
                    <?php echo $e['icon']; ?>
                </div>
                <div style="flex:1;">
                    <div style="font-size:13px;font-weight:700;color:#1a1a2e;"><?php echo esc_html($e['title']); ?>
                        <span style="font-size:10px;background:<?php echo esc_attr($e['bg']); ?>;color:<?php echo esc_attr($e['color']); ?>;padding:1px 7px;border-radius:10px;margin-left:6px;font-weight:600;"><?php echo esc_html($e['file']); ?></span>
                    </div>
                    <div style="font-size:11px;color:#6b7280;margin-top:2px;"><?php echo esc_html($e['desc']); ?></div>
                </div>

                <!-- Download Button -->
                <?php if ( ! empty( $e['direct'] ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=' . $e['action'] . '&nonce=' . $nonce ) ); ?>"
                   onclick="sgcMarkDone('<?php echo esc_js($e['id']); ?>')"
                   style="padding:8px 16px;background:<?php echo esc_attr($e['color']); ?>;color:#fff;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;flex-shrink:0;">
                    📥 Download
                </a>
                <?php else : ?>
                <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=' . $e['action'] . '&nonce=' . $nonce ) ); ?>"
                   onclick="sgcMarkDone('<?php echo esc_js($e['id']); ?>')"
                   style="padding:8px 16px;background:<?php echo esc_attr($e['color']); ?>;color:#fff;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;flex-shrink:0;">
                    📥 Download
                </a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Download All Button -->
        <div style="text-align:center;margin-bottom:20px;">
            <button onclick="sgcDownloadAll()" id="sgc-download-all"
                style="padding:12px 32px;background:#1a1a2e;color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;">
                📦 Download All Exports at Once
            </button>
        </div>

        <!-- Progress Bar -->
        <div id="sgc-sd-progress" style="background:#f8f9fa;border:1px solid #e5e7eb;border-radius:10px;padding:14px;margin-bottom:20px;display:none;">
            <div style="font-size:12px;font-weight:600;color:#374151;margin-bottom:8px;" id="sgc-sd-prog-label">Downloading...</div>
            <div style="height:6px;background:#e5e7eb;border-radius:3px;overflow:hidden;">
                <div id="sgc-sd-prog-fill" style="height:100%;background:#1a1a2e;border-radius:3px;transition:width .3s;width:0%;"></div>
            </div>
        </div>

        <!-- Checklist Status -->
        <div id="sgc-sd-checklist-status" style="display:none;background:#f0fdf4;border:1px solid #86efac;border-radius:10px;padding:14px;margin-bottom:20px;text-align:center;">
            <div style="font-size:14px;font-weight:700;color:#15803d;margin-bottom:4px;">✅ Sab exports complete!</div>
            <div style="font-size:12px;color:#166534;">Ab safely WordPress → Plugins → SGC LABS® Manager Pro → Delete kar sakte ho.</div>
            <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"
               style="display:inline-block;margin-top:10px;padding:8px 20px;background:#dc2626;color:#fff;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;">
               🗑️ Plugins Page pe Jao → Delete Karo
            </a>
        </div>

    </div>

    <script>
    var sgcExportIds = ['seo','redirects','keywords','quotes','settings','backups'];
    var sgcDone = {};
    var sgcNonce = '<?php echo esc_js( $nonce ); ?>';

    // Load stats on page load
    jQuery(function($) {
        $.post(ajaxurl, {
            action: 'sgclabs_safe_delete_stats',
            nonce: sgcNonce
        }, function(res) {
            if (!res.success) return;
            var d = res.data;
            var statsHtml = [
                ['🔍', 'SEO Products', d.seo_products],
                ['↩️', 'Redirects', d.redirects],
                ['📋', 'B2B Quotes', d.quotes],
                ['🔑', 'Keywords', d.kw_global + d.kw_category],
                ['⚙️', 'Settings', d.settings],
                ['💾', 'Backups', d.backups],
            ].map(function(s) {
                return '<div style="background:#f8f9fa;border:1px solid #e5e7eb;border-radius:8px;padding:12px;text-align:center;">' +
                    '<div style="font-size:20px;">' + s[0] + '</div>' +
                    '<div style="font-size:20px;font-weight:700;color:#1a1a2e;">' + s[2].toLocaleString() + '</div>' +
                    '<div style="font-size:10px;color:#6b7280;">' + s[1] + '</div>' +
                '</div>';
            }).join('');
            document.getElementById('sgc-sd-stats').innerHTML = statsHtml;
            document.getElementById('sgc-sd-stats').style.gridTemplateColumns = 'repeat(6,1fr)';
        });
    });

    function sgcMarkDone(id) {
        sgcDone[id] = true;
        var el = document.getElementById('sgc-check-' + id);
        if (el) {
            el.style.background = '#16a34a';
            el.style.borderColor = '#16a34a';
            el.style.color = '#fff';
            el.textContent = '✓';
        }
        // Check if all done
        var allDone = sgcExportIds.every(function(i){ return sgcDone[i]; });
        if (allDone) {
            document.getElementById('sgc-sd-checklist-status').style.display = 'block';
            document.getElementById('sgc-sd-checklist-status').scrollIntoView({behavior:'smooth'});
        }
    }

    function sgcDownloadAll() {
        var btn = document.getElementById('sgc-download-all');
        btn.disabled = true;
        btn.textContent = '⏳ Downloading...';

        var progWrap = document.getElementById('sgc-sd-progress');
        var progFill = document.getElementById('sgc-sd-prog-fill');
        var progLabel = document.getElementById('sgc-sd-prog-label');
        progWrap.style.display = 'block';

        var links = [
            {id:'seo',       url: ajaxurl + '?action=sgclabs_safe_export_seo&nonce=' + sgcNonce},
            {id:'redirects', url: ajaxurl + '?action=sgclabs_redirect_export&nonce=' + sgcNonce},
            {id:'keywords',  url: ajaxurl + '?action=sgclabs_safe_export_keywords&nonce=' + sgcNonce},
            {id:'quotes',    url: ajaxurl + '?action=sgclabs_safe_export_quotes&nonce=' + sgcNonce},
            {id:'settings',  url: ajaxurl + '?action=sgclabs_safe_export_settings&nonce=' + sgcNonce},
            {id:'backups',   url: ajaxurl + '?action=sgclabs_safe_export_backups&nonce=' + sgcNonce},
        ];

        var idx = 0;
        var titles = ['SEO Data', 'Redirections', 'Keywords', 'B2B Quotes', 'Settings', 'Content Backups'];

        function downloadNext() {
            if (idx >= links.length) {
                btn.textContent = '✅ All Downloaded!';
                btn.style.background = '#16a34a';
                progFill.style.width = '100%';
                progLabel.textContent = 'All 6 exports complete!';
                return;
            }
            var item = links[idx];
            progLabel.textContent = 'Downloading: ' + titles[idx] + ' (' + (idx+1) + '/' + links.length + ')';
            progFill.style.width = Math.round(((idx+1)/links.length)*100) + '%';

            // Trigger download via hidden link
            var a = document.createElement('a');
            a.href = item.url;
            a.style.display = 'none';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);

            sgcMarkDone(item.id);
            idx++;

            // Slight delay between downloads
            setTimeout(downloadNext, 1200);
        }

        downloadNext();
    }
    </script>
    <?php
}

// Register in admin menu
add_action( 'admin_menu', function() {
    add_submenu_page(
        'sgclabs-manager',
        'Safe Delete',
        '🛡️ Safe Delete',
        'manage_options',
        'sgclabs-safe-delete',
        'sgclabs_safe_delete_page'
    );
}, 99 ); // Last in menu
