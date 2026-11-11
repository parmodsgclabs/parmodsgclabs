<?php
/**
 * SGC LABS® — Data Migration & Health Check
 *
 * On every plugin upgrade:
 *  1. Auto-scans wp_options for all sgclabs_* keys → confirms inherited
 *  2. Checks all custom DB tables exist + row counts
 *  3. Checks API keys are set and decryptable
 *  4. One-click JSON export (full backup of all settings)
 *  5. One-click JSON import (restore settings on any site)
 *  6. Runs automatically on activation — shows banner if migration needed
 *
 * @package SGCLabs
 * @since   7.2.1
 */

defined( 'ABSPATH' ) || exit;

// ── Auto-run on activation / upgrade ─────────────────────────────────────────

add_action( 'sgclabs_after_upgrade', 'sgclabs_dm_run_auto_check' );
add_action( 'admin_init',            'sgclabs_dm_maybe_show_banner', 15 );

function sgclabs_dm_run_auto_check() {
    $result = sgclabs_dm_full_scan();
    update_option( 'sgclabs_dm_last_scan', $result, false );
    update_option( 'sgclabs_dm_scan_time', current_time( 'mysql' ), false );
    // Show banner only if something is missing
    if ( $result['missing_keys'] > 0 || $result['missing_tables'] > 0 ) {
        update_option( 'sgclabs_dm_show_banner', '1' );
    }
}

function sgclabs_dm_maybe_show_banner() {
    if ( ! get_option( 'sgclabs_dm_show_banner' ) ) return;
    if ( ! current_user_can( 'manage_options' ) ) return;
    add_action( 'admin_notices', function() {
        $scan = get_option( 'sgclabs_dm_last_scan', [] );
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>SGC LABS® Manager Pro:</strong> Data migration check — ';
        echo $scan['missing_keys'] . ' settings missing, ' . $scan['missing_tables'] . ' tables missing. ';
        echo '<a href="' . admin_url( 'admin.php?page=sgclabs-data-migrator' ) . '">View & Fix →</a></p>';
        echo '</div>';
    } );
}

// ── Core scan function ─────────────────────────────────────────────────────────

function sgclabs_dm_full_scan() {
    global $wpdb;

    $result = [
        'scanned_at'     => current_time( 'mysql' ),
        'plugin_version' => defined( 'SGCLABS_VERSION' ) ? SGCLABS_VERSION : '?',
        'total_keys'     => 0,
        'found_keys'     => 0,
        'missing_keys'   => 0,
        'tables'         => [],
        'missing_tables' => 0,
        'api_keys'       => [],
        'settings'       => [],
    ];

    // Expected settings keys
    $expected_keys = [
        'sgclabs_header_phone', 'sgclabs_header_email', 'sgclabs_header_active',
        'sgclabs_wa_active', 'sgclabs_wa_number',
        'sgclabs_aichat_active', 'sgclabs_aichat_provider',
        'sgclabs_ai_seo_provider', 'sgclabs_canonical_active',
        'sgclabs_indexnow_active', 'sgclabs_ext_link_nofollow',
        'sgclabs_lazy_images', 'sgclabs_fonts_active',
        'sgclabs_currency_default', 'sgclabs_wc_currency_override',
    ];

    foreach ( $expected_keys as $key ) {
        $val = get_option( $key, null );
        $result['total_keys']++;
        if ( $val !== null ) {
            $result['found_keys']++;
            $result['settings'][ $key ] = strlen( (string) $val ) > 40 ? substr( $val, 0, 40 ) . '...' : $val;
        } else {
            $result['missing_keys']++;
            $result['settings'][ $key ] = '__MISSING__';
        }
    }

    // API keys check
    $api_providers = [
        'claude'   => 'sgclabs_claude_api_key',
        'deepseek' => 'sgclabs_aichat_deepseek_key',
        'gemini'   => 'sgclabs_gemini_api_key',
        'openai'   => 'sgclabs_aichat_openai_key',
    ];

    foreach ( $api_providers as $name => $option ) {
        $raw = get_option( $option, '' );
        $dec = $raw;
        if ( $raw && function_exists( 'sgclabs_decrypt' ) ) {
            $dec = sgclabs_decrypt( $raw );
        }
        $result['api_keys'][ $name ] = [
            'in_db'     => ! empty( $raw ),
            'decrypted' => ! empty( $dec ),
            'length'    => strlen( $dec ),
            'prefix'    => $dec ? substr( $dec, 0, 6 ) . '***' : '',
        ];
    }

    // DB tables check
    $expected_tables = [
        $wpdb->prefix . 'sgclabs_inquiries',
        $wpdb->prefix . 'sgclabs_redirects',
        $wpdb->prefix . 'sgclabs_broken_links',
        $wpdb->prefix . 'sgc_doc_sequences',
        $wpdb->prefix . 'sgclabs_crawl_pages',
        $wpdb->prefix . 'sgclabs_kw_rankings',
    ];

    foreach ( $expected_tables as $table ) {
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        $count  = 0;
        if ( $exists ) {
            $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
        }
        $result['tables'][ $table ] = [
            'exists' => (bool) $exists,
            'rows'   => $count,
        ];
        if ( ! $exists ) $result['missing_tables']++;
    }

    return $result;
}

// ── Export all settings to JSON ───────────────────────────────────────────────

function sgclabs_dm_export_all() {
    global $wpdb;

    // Get ALL sgclabs_* options
    $rows = $wpdb->get_results(
        "SELECT option_name, option_value FROM {$wpdb->options}
         WHERE option_name LIKE 'sgclabs\_%'
         ORDER BY option_name",
        ARRAY_A
    );

    $export = [
        'export_version' => defined( 'SGCLABS_VERSION' ) ? SGCLABS_VERSION : '7.x',
        'export_date'    => current_time( 'mysql' ),
        'site_url'       => home_url(),
        'settings'       => [],
    ];

    foreach ( $rows as $row ) {
        $export['settings'][ $row['option_name'] ] = $row['option_value'];
    }

    return $export;
}

// ── Import from JSON ──────────────────────────────────────────────────────────

function sgclabs_dm_import_all( $json_data, $overwrite = false ) {
    if ( empty( $json_data['settings'] ) ) {
        return [ 'error' => 'Invalid export file — no settings found' ];
    }

    $imported = 0;
    $skipped  = 0;

    foreach ( $json_data['settings'] as $key => $value ) {
        // Safety: only import sgclabs_* keys
        if ( strpos( $key, 'sgclabs_' ) !== 0 ) continue;

        $existing = get_option( $key, '__NOT_FOUND__' );

        if ( $existing === '__NOT_FOUND__' ) {
            // Key doesn't exist — always add
            add_option( $key, $value, '', 'no' );
            $imported++;
        } elseif ( $overwrite ) {
            // Key exists but overwrite=true
            update_option( $key, $value );
            $imported++;
        } else {
            $skipped++;
        }
    }

    return [
        'imported' => $imported,
        'skipped'  => $skipped,
        'total'    => count( $json_data['settings'] ),
    ];
}

// ── AJAX: Run scan ────────────────────────────────────────────────────────────

add_action( 'wp_ajax_sgclabs_dm_scan', function() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
    $result = sgclabs_dm_full_scan();
    update_option( 'sgclabs_dm_last_scan', $result, false );
    update_option( 'sgclabs_dm_scan_time', current_time( 'mysql' ), false );
    wp_send_json_success( $result );
} );

// ── AJAX: Export ──────────────────────────────────────────────────────────────

add_action( 'wp_ajax_sgclabs_dm_export', function() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
    $data = sgclabs_dm_export_all();
    wp_send_json_success( $data );
} );

// ── AJAX: Import ──────────────────────────────────────────────────────────────

add_action( 'wp_ajax_sgclabs_dm_import', function() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $json_raw  = wp_unslash( $_POST['json_data'] ?? '' );
    $overwrite = ! empty( $_POST['overwrite'] );

    $data = json_decode( $json_raw, true );
    if ( ! $data ) {
        wp_send_json_error( 'Invalid JSON file' );
    }

    $result = sgclabs_dm_import_all( $data, $overwrite );
    if ( ! empty( $result['error'] ) ) {
        wp_send_json_error( $result['error'] );
    }

    wp_send_json_success( $result );
} );

// ── AJAX: Fix missing (run dbDelta + add_option defaults) ────────────────────

add_action( 'wp_ajax_sgclabs_dm_fix_missing', function() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    // Re-run activation to restore missing tables + default options
    if ( function_exists( 'sgclabs_activate' ) ) {
        sgclabs_activate();
    }

    $result = sgclabs_dm_full_scan();
    update_option( 'sgclabs_dm_last_scan', $result, false );
    delete_option( 'sgclabs_dm_show_banner' );

    wp_send_json_success( [
        'message' => 'Activation re-run complete. Missing items restored.',
        'scan'    => $result,
    ] );
} );

// ── Admin Page ────────────────────────────────────────────────────────────────

add_action( 'admin_menu', function() {
    add_submenu_page(
        'sgclabs-manager',
        'Data Migration & Health',
        'Data Health',
        'manage_options',
        'sgclabs-data-migrator',
        'sgclabs_dm_render_page'
    );
}, 35 );

function sgclabs_dm_render_page() {
    $nonce    = wp_create_nonce( 'sgclabs_nonce' );
    $last     = get_option( 'sgclabs_dm_last_scan', [] );
    $scantime = get_option( 'sgclabs_dm_scan_time', '' );
    ?>
<div class="wrap" id="sgclabs-dm">
<style>
#sgclabs-dm{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}
.dm-hdr{display:flex;align-items:center;gap:10px;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid #ddd}
.dm-hdr h2{margin:0;font-size:20px}
.dm-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px}
.dm-card{border:1px solid #ddd;border-radius:10px;overflow:hidden;background:#fff}
.dm-card-hdr{padding:10px 14px;background:#f9f9f9;border-bottom:1px solid #eee;font-weight:600;font-size:13px;display:flex;align-items:center;gap:8px}
.dm-card-body{padding:14px}
.row{display:flex;justify-content:space-between;align-items:center;padding:5px 0;border-bottom:0.5px solid #f0f0f0;font-size:12px}
.row:last-child{border-bottom:none}
.val-ok{color:#27ae60;font-weight:500}
.val-miss{color:#e74c3c;font-weight:500}
.val-warn{color:#f39c12;font-weight:500}
.btn{padding:8px 16px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;border:none}
.btn-scan{background:#0073aa;color:#fff}
.btn-export{background:#27ae60;color:#fff}
.btn-fix{background:#e67e22;color:#fff}
.btn-import{background:#8e44ad;color:#fff}
.btn:hover{opacity:.88}
.score-box{text-align:center;padding:20px;font-size:36px;font-weight:700;line-height:1}
.score-label{font-size:12px;color:#666;margin-top:4px}
.log-box{background:#1a1a1a;color:#ccc;font-family:monospace;font-size:11px;padding:12px;border-radius:8px;max-height:200px;overflow-y:auto;line-height:1.7;display:none;margin-top:12px}
.log-ok{color:#4caf50}.log-err{color:#ef5350}.log-warn{color:#ffb74d}.log-ai{color:#64b5f6}
.import-area{width:100%;height:100px;font-family:monospace;font-size:11px;border:1px solid #ddd;border-radius:6px;padding:8px;resize:vertical}
.stat-row{display:flex;gap:10px;margin-bottom:14px}
.stat{flex:1;padding:12px;border:1px solid #eee;border-radius:8px;text-align:center;background:#f9f9f9}
.stat-num{font-size:24px;font-weight:700}
.stat-lbl{font-size:11px;color:#666;margin-top:2px}
.spin{display:inline-block;width:12px;height:12px;border:2px solid #ddd;border-top-color:#0073aa;border-radius:50%;animation:spin .6s linear infinite;vertical-align:middle;margin-right:4px}
@keyframes spin{to{transform:rotate(360deg)}}
.api-row{display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:0.5px solid #f0f0f0;font-size:12px}
.api-row:last-child{border-bottom:none}
.dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.dot-ok{background:#27ae60}
.dot-miss{background:#e74c3c}
.dot-warn{background:#f39c12}
</style>

<div class="dm-hdr">
    <h2>Data Migration & Health Check</h2>
    <?php if ( $scantime ) : ?>
    <span style="font-size:12px;color:#666">Last scan: <?php echo esc_html( $scantime ); ?></span>
    <?php endif; ?>
    <button class="btn btn-scan" style="margin-left:auto" onclick="dmScan()">Run Health Check</button>
</div>

<?php if ( ! empty( $last ) ) : ?>
<div class="stat-row">
    <div class="stat">
        <div class="stat-num <?php echo $last['found_keys'] === $last['total_keys'] ? 'val-ok' : 'val-warn'; ?>">
            <?php echo $last['found_keys']; ?>/<?php echo $last['total_keys']; ?>
        </div>
        <div class="stat-lbl">Settings inherited</div>
    </div>
    <div class="stat">
        <div class="stat-num <?php echo $last['missing_tables'] === 0 ? 'val-ok' : 'val-miss'; ?>">
            <?php echo count( $last['tables'] ) - $last['missing_tables']; ?>/<?php echo count( $last['tables'] ); ?>
        </div>
        <div class="stat-lbl">DB Tables intact</div>
    </div>
    <div class="stat">
        <?php $api_ok = count( array_filter( $last['api_keys'], fn($k) => $k['in_db'] ) ); ?>
        <div class="stat-num <?php echo $api_ok > 0 ? 'val-ok' : 'val-miss'; ?>">
            <?php echo $api_ok; ?>/<?php echo count( $last['api_keys'] ); ?>
        </div>
        <div class="stat-lbl">API keys found</div>
    </div>
    <div class="stat">
        <div class="stat-num <?php echo $last['missing_keys'] === 0 && $last['missing_tables'] === 0 ? 'val-ok' : 'val-warn'; ?>">
            <?php echo ( $last['missing_keys'] === 0 && $last['missing_tables'] === 0 ) ? '✓' : '!'; ?>
        </div>
        <div class="stat-lbl">Overall status</div>
    </div>
</div>
<?php endif; ?>

<div class="dm-grid">

    <!-- API Keys -->
    <div class="dm-card">
        <div class="dm-card-hdr">API Keys Status</div>
        <div class="dm-card-body" id="api-keys-body">
        <?php if ( ! empty( $last['api_keys'] ) ) : ?>
            <?php foreach ( $last['api_keys'] as $name => $info ) : ?>
            <div class="api-row">
                <div class="dot <?php echo $info['in_db'] ? 'dot-ok' : 'dot-miss'; ?>"></div>
                <span style="flex:1;font-weight:500;text-transform:capitalize"><?php echo esc_html( $name ); ?></span>
                <?php if ( $info['in_db'] && $info['decrypted'] ) : ?>
                    <span class="val-ok">Found (<?php echo esc_html( $info['prefix'] ); ?>)</span>
                <?php elseif ( $info['in_db'] ) : ?>
                    <span class="val-warn">In DB — decrypt failed</span>
                <?php else : ?>
                    <span class="val-miss">Not set</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php else : ?>
            <p style="color:#666;font-size:12px">Run health check to see status</p>
        <?php endif; ?>
        </div>
    </div>

    <!-- DB Tables -->
    <div class="dm-card">
        <div class="dm-card-hdr">Database Tables</div>
        <div class="dm-card-body" id="tables-body">
        <?php if ( ! empty( $last['tables'] ) ) : ?>
            <?php foreach ( $last['tables'] as $table => $info ) : ?>
            <div class="row">
                <span style="font-family:monospace;font-size:11px"><?php echo esc_html( str_replace( $GLOBALS['wpdb']->prefix, '', $table ) ); ?></span>
                <?php if ( $info['exists'] ) : ?>
                    <span class="val-ok"><?php echo number_format( $info['rows'] ); ?> rows</span>
                <?php else : ?>
                    <span class="val-miss">Missing</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php else : ?>
            <p style="color:#666;font-size:12px">Run health check to see tables</p>
        <?php endif; ?>
        </div>
    </div>

    <!-- Settings -->
    <div class="dm-card">
        <div class="dm-card-hdr">Core Settings Check</div>
        <div class="dm-card-body" style="max-height:220px;overflow-y:auto" id="settings-body">
        <?php if ( ! empty( $last['settings'] ) ) : ?>
            <?php foreach ( $last['settings'] as $key => $val ) : ?>
            <div class="row">
                <span style="font-size:11px;font-family:monospace"><?php echo esc_html( str_replace( 'sgclabs_', '', $key ) ); ?></span>
                <?php if ( $val === '__MISSING__' ) : ?>
                    <span class="val-miss">Missing</span>
                <?php else : ?>
                    <span class="val-ok" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $val ?: '(empty)' ); ?></span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php else : ?>
            <p style="color:#666;font-size:12px">Run health check to see settings</p>
        <?php endif; ?>
        </div>
    </div>

    <!-- Actions -->
    <div class="dm-card">
        <div class="dm-card-hdr">Backup & Restore</div>
        <div class="dm-card-body">
            <p style="font-size:12px;color:#444;margin-bottom:12px">
                Export saves ALL <code>sgclabs_*</code> settings to a JSON file.<br>
                Import restores them on any site — perfect for moving to new hosting.
            </p>

            <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
                <button class="btn btn-export" onclick="dmExport()">Export Settings JSON</button>
                <?php if ( ! empty( $last['missing_keys'] ) || ! empty( $last['missing_tables'] ) ) : ?>
                <button class="btn btn-fix" onclick="dmFixMissing()">Fix Missing Items</button>
                <?php endif; ?>
            </div>

            <div style="border-top:1px solid #eee;padding-top:12px">
                <p style="font-size:12px;font-weight:600;margin-bottom:6px">Import from JSON</p>
                <textarea class="import-area" id="import-json" placeholder='Paste exported JSON here... {"export_version":"7.x","settings":{...}}'></textarea>
                <div style="display:flex;gap:8px;align-items:center;margin-top:8px">
                    <button class="btn btn-import" onclick="dmImport(false)">Import (keep existing)</button>
                    <button class="btn btn-import" style="background:#c0392b" onclick="dmImport(true)">Import + Overwrite all</button>
                </div>
            </div>
        </div>
    </div>

</div>

<div class="log-box" id="dm-log"></div>

<script>
var DM_NONCE = '<?php echo esc_js( $nonce ); ?>';
var DM_AJAX  = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

function dmLog(msg, cls) {
    var log = document.getElementById('dm-log');
    log.style.display = 'block';
    var el = document.createElement('div');
    if (cls) el.className = cls;
    el.textContent = '[' + new Date().toLocaleTimeString() + '] ' + msg;
    log.appendChild(el);
    log.scrollTop = log.scrollHeight;
}

function dmScan() {
    dmLog('Running full health check...', 'log-ai');
    jQuery.post(DM_AJAX, {action:'sgclabs_dm_scan', nonce:DM_NONCE}, function(r) {
        if (!r.success) { dmLog('Scan error: ' + r.data, 'log-err'); return; }
        var d = r.data;
        dmLog('Settings: ' + d.found_keys + '/' + d.total_keys + ' found', d.missing_keys > 0 ? 'log-warn' : 'log-ok');
        dmLog('DB Tables: ' + (Object.keys(d.tables).length - d.missing_tables) + '/' + Object.keys(d.tables).length + ' exist', d.missing_tables > 0 ? 'log-warn' : 'log-ok');
        var apiOk = Object.values(d.api_keys).filter(function(k){return k.in_db;}).length;
        dmLog('API Keys: ' + apiOk + '/' + Object.keys(d.api_keys).length + ' set', apiOk === 0 ? 'log-warn' : 'log-ok');
        if (d.missing_keys === 0 && d.missing_tables === 0) {
            dmLog('All data intact — migration successful!', 'log-ok');
        } else {
            dmLog('Some items missing — click "Fix Missing Items"', 'log-warn');
        }
        setTimeout(function(){ location.reload(); }, 1500);
    }).fail(function(){ dmLog('AJAX error', 'log-err'); });
}

function dmExport() {
    dmLog('Generating export...', 'log-ai');
    jQuery.post(DM_AJAX, {action:'sgclabs_dm_export', nonce:DM_NONCE}, function(r) {
        if (!r.success) { dmLog('Export error: ' + r.data, 'log-err'); return; }
        var json = JSON.stringify(r.data, null, 2);
        var blob = new Blob([json], {type:'application/json'});
        var url  = URL.createObjectURL(blob);
        var a    = document.createElement('a');
        a.href   = url;
        a.download = 'sgclabs-settings-' + new Date().toISOString().slice(0,10) + '.json';
        a.click();
        URL.revokeObjectURL(url);
        dmLog('Exported ' + Object.keys(r.data.settings).length + ' settings to JSON file.', 'log-ok');
    }).fail(function(){ dmLog('Export AJAX error', 'log-err'); });
}

function dmFixMissing() {
    if (!confirm('Re-run plugin activation to restore missing tables and default settings?')) return;
    dmLog('Running fix...', 'log-ai');
    jQuery.post(DM_AJAX, {action:'sgclabs_dm_fix_missing', nonce:DM_NONCE}, function(r) {
        if (!r.success) { dmLog('Fix error: ' + r.data, 'log-err'); return; }
        dmLog(r.data.message, 'log-ok');
        dmLog('Missing keys after fix: ' + r.data.scan.missing_keys, r.data.scan.missing_keys > 0 ? 'log-warn' : 'log-ok');
        setTimeout(function(){ location.reload(); }, 2000);
    }).fail(function(){ dmLog('Fix AJAX error', 'log-err'); });
}

function dmImport(overwrite) {
    var json_raw = document.getElementById('import-json').value.trim();
    if (!json_raw) { dmLog('Paste JSON first', 'log-warn'); return; }
    try { JSON.parse(json_raw); } catch(e) { dmLog('Invalid JSON: ' + e.message, 'log-err'); return; }
    if (!confirm('Import settings from JSON?' + (overwrite ? '\n\nWARNING: This will overwrite existing settings.' : '\n\nExisting settings will be kept (only missing ones added).'))) return;
    dmLog('Importing settings...', 'log-ai');
    jQuery.post(DM_AJAX, {action:'sgclabs_dm_import', nonce:DM_NONCE, json_data:json_raw, overwrite:overwrite?1:0}, function(r) {
        if (!r.success) { dmLog('Import error: ' + r.data, 'log-err'); return; }
        dmLog('Imported: ' + r.data.imported + ' settings.', 'log-ok');
        dmLog('Skipped (already exist): ' + r.data.skipped, '');
        setTimeout(function(){ dmScan(); }, 1000);
    }).fail(function(){ dmLog('Import AJAX error', 'log-err'); });
}
</script>
</div>
<?php
}
