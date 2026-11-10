<?php
if (!defined('ABSPATH')) exit;

// ============================================================
// SGC LABS® — ADVANCED ADMIN PAGES v6.6.3
// Admin UI for: Instant Indexing, Bulk Editor, Role Manager,
// htaccess, Analytics, Import/Export, Orphan Pages
// ============================================================

// ── Instant Indexing Page ──
function sgclabs_indexnow_page() {
    $active = get_option('sgclabs_indexnow_active', '0');
    $key = get_option('sgclabs_indexnow_key', '');
    $log = get_option('sgclabs_indexnow_log', []);
    $nonce = wp_create_nonce('sgclabs_nonce');
    ?>
    <div class="wrap sgclabs-wrap">
        <h1>⚡ Instant Indexing (IndexNow)</h1>
        <p>IndexNow API se pages instantly Google, Bing, Yandex ko submit karo. Publish/update pe auto-submit hota hai.</p>

        <div class="sgclabs-section">
            <h2>Settings</h2>
            <table class="form-table">
                <tr><th>Active</th><td><label><input type="checkbox" id="indexnow-active" <?php checked($active, '1'); ?>> Enable IndexNow auto-submit</label></td></tr>
                <tr><th>API Key</th><td><code><?php echo esc_html($key ?: 'Auto-generated on first use'); ?></code></td></tr>
                <tr><th>Verification URL</th><td><code><?php echo esc_url(home_url("/{$key}.txt")); ?></code></td></tr>
            </table>
            <button class="button button-primary" onclick="jQuery.post(ajaxurl,{action:'sgclabs_save_extras',_wpnonce:'<?php echo esc_js(wp_create_nonce('sgclabs_extras_nonce')); ?>',sgclabs_indexnow_active:document.getElementById('indexnow-active').checked?'1':'0'},function(){location.reload()})">💾 Save</button>
        </div>

        <div class="sgclabs-section">
            <h2>📤 Manual Submit URLs</h2>
            <textarea id="indexnow-urls" rows="5" style="width:100%;font-family:monospace;" placeholder="One URL per line..."></textarea>
            <button class="button button-primary" onclick="jQuery.post(ajaxurl,{action:'sgclabs_indexnow_submit',nonce:'<?php echo esc_js( $nonce ); ?>',urls:document.getElementById('indexnow-urls').value},function(r){if(r.success)alert('✅ '+r.data.submitted+' URLs submitted!')})">🚀 Submit Now</button>
        </div>

        <?php if (!empty($log)): ?>
        <div class="sgclabs-section">
            <h2>📋 Recent Submissions (Last 100)</h2>
            <table class="wp-list-table widefat striped" style="font-size:12px;">
                <thead><tr><th>URL</th><th>Time</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($log, 0, 25) as $l): ?>
                    <tr><td><code><?php echo esc_html($l['url']); ?></code></td><td><?php echo esc_html($l['time']); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

// ── Bulk SEO Editor Page ──
function sgclabs_bulk_editor_page() {
    $nonce = wp_create_nonce('sgclabs_nonce');
    $post_types = get_post_types(['public' => true], 'objects');
    // FR5: list product categories so the dropdown can filter results.
    $product_cats = function_exists( 'get_terms' )
        ? get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name' ] )
        : [];
    if ( is_wp_error( $product_cats ) ) {
        $product_cats = [];
    }
    ?>
    <div class="wrap sgclabs-wrap">
        <h1>✏️ Bulk SEO Editor</h1>
        <p>Saare posts/pages/products ka SEO Title, Meta Description, Focus Keyword ek jagah se edit karo.</p>

        <div style="display:flex;gap:10px;margin-bottom:15px;align-items:center;flex-wrap:wrap;">
            <select id="bulk-post-type" onchange="sgcBulkOnTypeChange()">
                <?php foreach ($post_types as $pt): ?>
                    <option value="<?php echo esc_attr($pt->name); ?>"><?php echo esc_html($pt->label); ?></option>
                <?php endforeach; ?>
            </select>
            <select id="bulk-category" onchange="sgcBulkLoad(1)" style="min-width:240px;">
                <option value="0">— All categories —</option>
                <?php foreach ( $product_cats as $cat ) : ?>
                    <option value="<?php echo esc_attr( (string) $cat->term_id ); ?>">
                        <?php echo esc_html( $cat->name . ' (' . $cat->count . ')' ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" id="bulk-search" placeholder="Search..." style="width:200px;padding:6px 10px;">
            <button class="button" onclick="sgcBulkLoad(1)">🔍 Search</button>
        </div>

        <div id="bulk-editor-table"></div>
        <div id="bulk-pagination" style="margin-top:10px;"></div>
    </div>

    <style>
    .bulk-row{display:grid;grid-template-columns:50px 1fr 1fr 1fr 120px 60px 60px;gap:8px;padding:8px 10px;border-bottom:1px solid #eee;align-items:center;font-size:12px;}
    .bulk-row:hover{background:#f8f9ff;}
    .bulk-header{font-weight:bold;background:#f0f0f0;border-radius:6px;}
    .bulk-row input,.bulk-row textarea{width:100%;padding:4px 8px;border:1px solid #ddd;border-radius:4px;font-size:12px;}
    .bulk-row textarea{resize:vertical;height:36px;}
    .bulk-score{display:inline-block;width:30px;height:30px;border-radius:50%;text-align:center;line-height:30px;color:#fff;font-weight:bold;font-size:11px;}
    </style>

    <script>
    function sgcBulkOnTypeChange() {
        // Hide category dropdown when not on products.
        var pt  = document.getElementById('bulk-post-type').value;
        var sel = document.getElementById('bulk-category');
        sel.style.display = (pt === 'product') ? '' : 'none';
        sgcBulkLoad(1);
    }
    function sgcBulkLoad(page) {
        jQuery.post(ajaxurl, {
            action: 'sgclabs_bulk_seo_list',
            nonce: '<?php echo esc_js( $nonce ); ?>',
            post_type: document.getElementById('bulk-post-type').value,
            page: page,
            search: document.getElementById('bulk-search').value,
            category: document.getElementById('bulk-category').value
        }, function(res) {
            if (!res.success) return;
            var d = res.data;
            var html = '<div class="bulk-row bulk-header"><span>#</span><span>Post Title</span><span>SEO Title</span><span>Meta Description</span><span>Focus KW</span><span>Score</span><span>Save</span></div>';
            d.posts.forEach(function(p) {
                var sc = p.score >= 70 ? '#27ae60' : (p.score >= 40 ? '#f39c12' : '#e74c3c');
                html += '<div class="bulk-row" id="bulk-' + p.id + '"><span>' + p.id + '</span>';
                html += '<span><strong>' + p.title + '</strong></span>';
                html += '<span><input type="text" data-field="seo_title" value="' + (p.seo_title||'').replace(/"/g,'&quot;') + '"></span>';
                html += '<span><textarea data-field="seo_desc">' + (p.seo_desc||'') + '</textarea></span>';
                html += '<span><input type="text" data-field="focus_kw" value="' + (p.focus_kw||'').replace(/"/g,'&quot;') + '"></span>';
                html += '<span><span class="bulk-score" style="background:' + sc + '">' + p.score + '</span></span>';
                html += '<span><button class="button" onclick="sgcBulkSave(' + p.id + ')">💾</button></span></div>';
            });
            document.getElementById('bulk-editor-table').innerHTML = html;

            var pag = '';
            for (var i = 1; i <= d.pages; i++) {
                pag += '<button class="button' + (i===d.page ? ' button-primary' : '') + '" onclick="sgcBulkLoad(' + i + ')" style="margin:2px;">' + i + '</button>';
            }
            document.getElementById('bulk-pagination').innerHTML = 'Page: ' + pag + ' (' + d.total + ' total)';
        });
    }

    function sgcBulkSave(postId) {
        var row = document.getElementById('bulk-' + postId);
        jQuery.post(ajaxurl, {
            action: 'sgclabs_bulk_seo_save',
            nonce: '<?php echo esc_js( $nonce ); ?>',
            post_id: postId,
            seo_title: row.querySelector('[data-field="seo_title"]').value,
            seo_desc: row.querySelector('[data-field="seo_desc"]').value,
            focus_kw: row.querySelector('[data-field="focus_kw"]').value
        }, function(res) {
            if (res.success) {
                var sc = res.data.score >= 70 ? '#27ae60' : (res.data.score >= 40 ? '#f39c12' : '#e74c3c');
                row.querySelector('.bulk-score').style.background = sc;
                row.querySelector('.bulk-score').textContent = res.data.score;
                row.style.background = '#e8f8e8';
                setTimeout(function(){ row.style.background = ''; }, 1500);
            }
        });
    }

    sgcBulkOnTypeChange();
    </script>
    <?php
}

// ── Role Manager Page ──
function sgclabs_role_manager_page() {
    if (isset($_POST['sgclabs_save_roles']) && wp_verify_nonce($_POST['_wpnonce'], 'sgclabs_roles_nonce')) {
        update_option('sgclabs_seo_roles', array_map('sanitize_text_field', (array)($_POST['seo_roles'] ?? ['administrator'])));
        update_option('sgclabs_client_mode', isset($_POST['client_mode']) ? '1' : '0');
        update_option('sgclabs_client_hide_pages', array_map('sanitize_text_field', (array)($_POST['client_hide'] ?? [])));
        echo '<div class="notice notice-success"><p>✅ Saved!</p></div>';
    }

    $roles = wp_roles()->get_names();
    $allowed = get_option('sgclabs_seo_roles', ['administrator']);
    $client_mode = get_option('sgclabs_client_mode', '0');
    $hidden = get_option('sgclabs_client_hide_pages', []);
    $all_pages = [
        'sgclabs-site-tools'=>'Site Tools','sgclabs-compliance'=>'Google Compliance','sgclabs-fixes'=>'Site Auto-Fix',
        'sgclabs-multisite'=>'Multi-Site Profiles','sgclabs-global-kw'=>'Global Keywords','sgclabs-extras'=>'Extras',
    ];
    ?>
    <div class="wrap sgclabs-wrap">
        <h1>👥 SEO Role Manager</h1>
        <form method="post">
            <?php wp_nonce_field('sgclabs_roles_nonce'); ?>
            <input type="hidden" name="sgclabs_save_roles" value="1">

            <div class="sgclabs-section">
                <h2>User Roles with SEO Access</h2>
                <div style="display:flex;flex-wrap:wrap;gap:12px;">
                    <?php foreach ($roles as $slug => $name): ?>
                        <label style="font-size:13px;"><input type="checkbox" name="seo_roles[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $allowed)); ?>> <?php echo esc_html($name); ?></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="sgclabs-section">
                <h2>🔒 Client Mode</h2>
                <p>Client Mode enable karne se non-admin users ko advanced settings nahi dikhenge.</p>
                <label><input type="checkbox" name="client_mode" value="1" <?php checked($client_mode, '1'); ?>> Enable Client Mode</label>

                <h4 style="margin-top:15px;">Hide these pages from clients:</h4>
                <div style="display:flex;flex-wrap:wrap;gap:10px;">
                    <?php foreach ($all_pages as $slug => $label): ?>
                        <label style="font-size:13px;"><input type="checkbox" name="client_hide[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $hidden)); ?>> <?php echo esc_html($label); ?></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php submit_button('💾 Save Roles'); ?>
        </form>
    </div>
    <?php
}

// ── SEO Import/Export Page ──
function sgclabs_import_export_page() {
    $nonce = wp_create_nonce('sgclabs_nonce');
    ?>
    <div class="wrap sgclabs-wrap">
        <h1>🔄 SEO Import / Export</h1>

        <div class="sgclabs-section">
            <h2>📤 Export SGC LABS® SEO Data</h2>
            <p>Saara SEO data (titles, descriptions, keywords, settings) JSON format mein download karo.</p>
            <button class="button button-primary" onclick="sgcExportSEO()">📥 Export to JSON</button>
        </div>

        <div class="sgclabs-section">
            <h2>📥 Import SGC LABS® SEO Data</h2>
            <textarea id="import-json" rows="6" style="width:100%;font-family:monospace;" placeholder="Paste exported JSON here..."></textarea>
            <button class="button button-primary" onclick="sgcImportSEO()" style="margin-top:8px;">📤 Import</button>
        </div>

        <div class="sgclabs-section" style="border-left:4px solid #6c5ce7;">
            <h2>🔀 Import from Other SEO Plugins</h2>
            <div style="display:flex;gap:15px;flex-wrap:wrap;">
                <button class="button" onclick="if(confirm('Import all RankMath SEO data?'))jQuery.post(ajaxurl,{action:'sgclabs_import_rankmath',nonce:'<?php echo esc_js( $nonce ); ?>'},function(r){if(r.success)alert('✅ '+r.data.imported+' items imported from RankMath!')})">
                    Import from <strong>Rank Math</strong>
                </button>
                <button class="button" onclick="if(confirm('Import all Yoast SEO data?'))jQuery.post(ajaxurl,{action:'sgclabs_import_yoast',nonce:'<?php echo esc_js( $nonce ); ?>'},function(r){if(r.success)alert('✅ '+r.data.imported+' items imported from Yoast!')})">
                    Import from <strong>Yoast SEO</strong>
                </button>
            </div>
            <p style="font-size:12px;color:#888;margin-top:8px;">Titles, descriptions, focus keywords, canonical URLs, robots tags — sab import ho jayega.</p>
        </div>
    </div>

    <script>
    function sgcExportSEO() {
        jQuery.post(ajaxurl, {action:'sgclabs_seo_export',nonce:'<?php echo esc_js( $nonce ); ?>'}, function(res) {
            if (!res.success) return;
            var blob = new Blob([JSON.stringify(res.data, null, 2)], {type:'application/json'});
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'sgclabs-seo-export-' + new Date().toISOString().slice(0,10) + '.json';
            a.click();
        });
    }
    function sgcImportSEO() {
        var json = document.getElementById('import-json').value;
        if (!json) return alert('Paste JSON first!');
        jQuery.post(ajaxurl, {action:'sgclabs_seo_import',nonce:'<?php echo esc_js( $nonce ); ?>',import_data:json}, function(res) {
            if (res.success) alert('✅ ' + res.data.imported + ' items imported!');
            else alert('❌ Error: ' + (res.data || 'Unknown'));
        });
    }
    </script>
    <?php
}

// ── Orphan Pages + Link Builder Page ──
function sgclabs_link_builder_page() {
    $nonce = wp_create_nonce('sgclabs_nonce');
    $nofollow = get_option('sgclabs_ext_link_nofollow', '0');
    $newtab   = get_option('sgclabs_ext_link_newtab', '1');
    ?>
    <div class="wrap sgclabs-wrap">
        <h1>🔗 Link Builder & Analysis</h1>

        <div class="sgclabs-section">
            <h2>⚙️ External Link Settings</h2>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('sgclabs_link_nonce'); ?>
                <input type="hidden" name="action" value="sgclabs_save_link_settings">
                <table class="form-table">
                    <tr><th>Auto Nofollow External Links</th><td><label><input type="checkbox" name="ext_nofollow" value="1" <?php checked($nofollow, '1'); ?>> Add rel="nofollow" to all external links in content</label></td></tr>
                    <tr><th>Open in New Tab</th><td><label><input type="checkbox" name="ext_newtab" value="1" <?php checked($newtab, '1'); ?>> Add target="_blank" to external links</label></td></tr>
                    <tr><th>Exclude Domains</th><td><textarea name="ext_exclude" rows="3" style="width:100%;" placeholder="one domain per line (e.g. google.com)"><?php echo esc_textarea(get_option('sgclabs_ext_link_exclude', '')); ?></textarea></td></tr>
                </table>
                <?php submit_button('💾 Save Link Settings'); ?>
            </form>
        </div>

        <div class="sgclabs-section" style="border-left:4px solid #e74c3c;">
            <h2>🔍 Orphan Pages Detection</h2>
            <p>Find pages that have ZERO internal links pointing to them — these are invisible to Google!</p>
            <button class="button button-primary" id="find-orphans-btn" onclick="sgcFindOrphans()">🔍 Scan for Orphan Pages</button>
            <div id="orphan-results" style="margin-top:15px;"></div>
        </div>
    </div>

    <script>
    function sgcFindOrphans() {
        document.getElementById('find-orphans-btn').textContent = '⏳ Scanning...';
        jQuery.post(ajaxurl, {action:'sgclabs_find_orphans',nonce:'<?php echo esc_js( $nonce ); ?>'}, function(res) {
            document.getElementById('find-orphans-btn').textContent = '🔍 Scan Again';
            if (!res.success) return;
            var d = res.data;
            var html = '<p><strong>' + d.orphan_count + '</strong> orphan pages found out of ' + d.total + ' total published.</p>';
            if (d.orphans.length > 0) {
                html += '<table class="wp-list-table widefat striped" style="font-size:12px;"><thead><tr><th>ID</th><th>Title</th><th>Type</th><th>URL</th><th>Action</th></tr></thead><tbody>';
                d.orphans.forEach(function(o) {
                    html += '<tr><td>' + o.id + '</td><td>' + o.title + '</td><td>' + o.type + '</td><td><a href="' + o.url + '" target="_blank">' + o.url.substring(0,60) + '</a></td><td><a href="post.php?post=' + o.id + '&action=edit" class="button">Edit</a></td></tr>';
                });
                html += '</tbody></table>';
            } else {
                html += '<p style="color:#27ae60;font-weight:bold;">✅ No orphan pages! Good SEO structure.</p>';
            }
            document.getElementById('orphan-results').innerHTML = html;
        });
    }
    </script>
    <?php
}

// Save link settings
add_action('admin_post_sgclabs_save_link_settings', function() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'sgclabs_link_nonce')) wp_die();
    update_option('sgclabs_ext_link_nofollow', isset($_POST['ext_nofollow']) ? '1' : '0');
    update_option('sgclabs_ext_link_newtab', isset($_POST['ext_newtab']) ? '1' : '0');
    update_option('sgclabs_ext_link_exclude', sanitize_textarea_field($_POST['ext_exclude'] ?? ''));
    wp_redirect(admin_url('admin.php?page=sgclabs-seo-suite&tab=internal&saved=1'));
    exit;
});

// ============================================================
// .htaccess Editor Page
// ============================================================
function sgclabs_htaccess_page() {
    if (!current_user_can('manage_options')) return;
    $htaccess_file = ABSPATH . '.htaccess';
    $content = file_exists($htaccess_file) ? file_get_contents($htaccess_file) : '';
    $writable = file_exists($htaccess_file) ? is_writable($htaccess_file) : is_writable(ABSPATH);
    $backup = get_option('sgclabs_htaccess_backup', '');
    ?>
    <div class="wrap" style="max-width:900px;">
        <h1 style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:28px;">📝</span> .htaccess Editor
            <span style="font-size:12px;background:#1a1a2e;color:white;padding:3px 10px;border-radius:12px;">SGC LABS®</span>
        </h1>
        <?php if (!$writable): ?>
            <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:12px 16px;margin:15px 0;border-radius:6px;">
                <strong>⚠️ Warning:</strong> .htaccess file is not writable. Please set permissions to 644 via File Manager or FTP.
            </div>
        <?php endif; ?>
        <div style="background:white;border:1px solid #e0e0e0;border-radius:10px;padding:25px;margin-top:15px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
                <h3 style="margin:0;">Current .htaccess Content</h3>
                <div style="display:flex;gap:8px;">
                    <?php if ($backup): ?>
                    <button id="sgc-ht-restore" class="button" onclick="sgcRestoreHtaccess()" style="background:#e74c3c;color:white;border:none;">
                        🔄 Restore Backup
                    </button>
                    <?php endif; ?>
                    <button id="sgc-ht-save" class="button button-primary" onclick="sgcSaveHtaccess()" <?php echo !$writable ? 'disabled' : ''; ?>>
                        💾 Save Changes
                    </button>
                </div>
            </div>
            <textarea id="sgc-htaccess-content" style="width:100%;height:500px;font-family:'Courier New',monospace;font-size:13px;line-height:1.5;background:#1a1a2e;color:#27ae60;padding:15px;border-radius:8px;border:1px solid #333;resize:vertical;"><?php echo esc_textarea($content); ?></textarea>
            <div id="sgc-ht-status" style="margin-top:10px;"></div>
            <div style="margin-top:15px;padding:12px;background:#f8f9fa;border-radius:8px;font-size:12px;color:#666;">
                <strong>⚡ Quick Tips:</strong>
                Do NOT remove WordPress default rewrite rules.
                Always backup before editing.
                Incorrect rules can break your entire site.
                <?php if ($backup): ?>
                <br><strong>✅ Backup available</strong> — you can restore anytime.
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
    function sgcSaveHtaccess() {
        var btn = document.getElementById('sgc-ht-save');
        btn.disabled = true; btn.textContent = '⏳ Saving...';
        var data = new FormData();
        data.append('action', 'sgclabs_save_htaccess');
        data.append('_wpnonce', '<?php echo esc_js(wp_create_nonce("sgclabs_htaccess_nonce")); ?>');
        data.append('htaccess', document.getElementById('sgc-htaccess-content').value);
        fetch(ajaxurl, {method:'POST', body:data})
        .then(r => r.json()).then(d => {
            document.getElementById('sgc-ht-status').innerHTML = d.success
                ? '<span style="color:#27ae60;font-weight:bold;">✅ .htaccess saved successfully!</span>'
                : '<span style="color:#e74c3c;font-weight:bold;">❌ Error: ' + (d.data||'Could not save') + '</span>';
            btn.disabled = false; btn.textContent = '💾 Save Changes';
        }).catch(() => {
            document.getElementById('sgc-ht-status').innerHTML = '<span style="color:red;">❌ Network error</span>';
            btn.disabled = false; btn.textContent = '💾 Save Changes';
        });
    }
    function sgcRestoreHtaccess() {
        if (!confirm('⚠️ This will restore the backup version of .htaccess. Continue?')) return;
        var data = new FormData();
        data.append('action', 'sgclabs_restore_htaccess');
        data.append('_wpnonce', '<?php echo esc_js(wp_create_nonce("sgclabs_htaccess_nonce")); ?>');
        fetch(ajaxurl, {method:'POST', body:data})
        .then(r => r.json()).then(d => {
            if (d.success) { location.reload(); }
            else { alert('Error restoring: ' + (d.data||'Unknown')); }
        });
    }
    </script>
    <?php
}
