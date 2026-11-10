<?php
if (!defined('ABSPATH')) exit;

if (get_option('sgclabs_keyword_manager_active', '1') !== '1') return;

// ============================================================
// SGC LABS® KEYWORD MANAGER
// 1. Manually add keywords
// 2. Google Search Console import
// 3. Per-category keywords
// 4. Auto-inject in all products + homepage + sitemap
// ============================================================

// --- CREATE TABLES ---
add_action('init', 'sgclabs_create_keyword_tables');
function sgclabs_create_keyword_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Global keywords (apply everywhere)
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sgclabs_kw_global (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        keyword varchar(500) NOT NULL,
        language varchar(10) DEFAULT 'en',
        source varchar(50) DEFAULT 'manual',
        impressions int DEFAULT 0,
        clicks int DEFAULT 0,
        position float DEFAULT 0,
        active tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;");

    // Category-specific keywords
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sgclabs_kw_category (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        category_id bigint(20) NOT NULL,
        category_name varchar(200),
        keyword varchar(500) NOT NULL,
        language varchar(10) DEFAULT 'en',
        active tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;");

    // GSC imported keywords
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sgclabs_kw_gsc (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        keyword varchar(500) NOT NULL,
        impressions int DEFAULT 0,
        clicks int DEFAULT 0,
        ctr float DEFAULT 0,
        position float DEFAULT 0,
        imported_at datetime DEFAULT CURRENT_TIMESTAMP,
        synced_at datetime DEFAULT NULL,
        active tinyint(1) DEFAULT 1,
        PRIMARY KEY (id)
    ) $charset;");
    // Add synced_at if missing (upgrade existing installs) — MySQL 5.x compatible
    $row = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}sgclabs_kw_gsc LIKE 'synced_at'");
    if (empty($row)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}sgclabs_kw_gsc ADD COLUMN synced_at datetime DEFAULT NULL");
    }
}

// ============================================================
// GET ALL KEYWORDS FOR A PRODUCT (Global + Category)
// ============================================================
function sgclabs_get_all_product_keywords($product_id) {
    global $wpdb;

    // 1. Global keywords
    $global = $wpdb->get_col(
        "SELECT keyword FROM {$wpdb->prefix}sgclabs_kw_global WHERE active=1 ORDER BY clicks DESC, impressions DESC"
    ) ?: [];

    // 2. Category keywords
    $cat_ids = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    $cat_kws = [];
    if (!empty($cat_ids)) {
        $placeholders = implode(',', array_fill(0, count($cat_ids), '%d'));
        $cat_kws = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT keyword FROM {$wpdb->prefix}sgclabs_kw_category 
                 WHERE category_id IN ($placeholders) AND active=1",
                ...$cat_ids
            )
        ) ?: [];
    }

    // 3. GSC top keywords
    $gsc = $wpdb->get_col(
        "SELECT keyword FROM {$wpdb->prefix}sgclabs_kw_gsc WHERE active=1 ORDER BY clicks DESC LIMIT 20"
    ) ?: [];

    return array_unique(array_merge($global, $cat_kws, $gsc));
}

// ============================================================
// AUTO-INJECT KEYWORDS INTO PRODUCT PAGES
// ============================================================
add_action('wp_head', 'sgclabs_inject_custom_keywords', 6);
function sgclabs_inject_custom_keywords() {
    // Guard: skip if any SEO plugin active — they handle keywords meta
    if ( defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION') || defined('AIOSEO_VERSION') || defined('SEOPRESS_VERSION') ) return;
    global $wpdb;

    if (function_exists('is_product') && is_product()) {
        $pid  = get_the_ID();
        $kws  = sgclabs_get_all_product_keywords($pid);
        if (!empty($kws)) {
            $kw_string = esc_attr(implode(', ', array_slice($kws, 0, 30)));
            echo "<meta name='keywords' content='{$kw_string}'>\n";
            echo "<!-- SGC LABS® Keywords: " . count($kws) . " keywords injected -->\n";
        }

    } elseif (is_front_page() || is_home()) {
        // Homepage — inject all global + GSC keywords
        $global = $wpdb->get_col("SELECT keyword FROM {$wpdb->prefix}sgclabs_kw_global WHERE active=1 LIMIT 30") ?: [];
        $gsc    = $wpdb->get_col("SELECT keyword FROM {$wpdb->prefix}sgclabs_kw_gsc WHERE active=1 ORDER BY clicks DESC LIMIT 20") ?: [];
        $all    = array_unique(array_merge($global, $gsc));
        if (!empty($all)) {
            echo "<meta name='keywords' content='" . esc_attr(implode(', ', $all)) . "'>\n";
        }

    } elseif (is_tax('product_cat')) {
        // Category pages
        $term    = get_queried_object();
        $cat_kws = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT keyword FROM {$wpdb->prefix}sgclabs_kw_category WHERE category_id=%d AND active=1",
                $term->term_id
            )
        ) ?: [];
        $global  = $wpdb->get_col("SELECT keyword FROM {$wpdb->prefix}sgclabs_kw_global WHERE active=1 LIMIT 15") ?: [];
        $all     = array_unique(array_merge($cat_kws, $global));
        if (!empty($all)) {
            echo "<meta name='keywords' content='" . esc_attr(implode(', ', $all)) . "'>\n";
        }
    }
}

// ============================================================
// AJAX HANDLERS
// ============================================================

// Add global keyword manually
add_action('wp_ajax_sgclabs_kw_add_global', 'sgclabs_kw_add_global');
function sgclabs_kw_add_global() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    global $wpdb;
    $keyword  = sanitize_text_field($_POST['keyword'] ?? '');
    $language = sanitize_text_field($_POST['language'] ?? 'en');
    if (!$keyword) { wp_send_json_error('Keyword required'); return; }
    $result = $wpdb->insert("{$wpdb->prefix}sgclabs_kw_global",
        ['keyword'=>$keyword,'language'=>$language,'source'=>'manual']
    );
    if ($result) wp_send_json_success(['id'=>$wpdb->insert_id,'keyword'=>$keyword]);
    else wp_send_json_error('Already exists or DB error');
}

// Delete global keyword
add_action('wp_ajax_sgclabs_kw_delete_global', 'sgclabs_kw_delete_global');
function sgclabs_kw_delete_global() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    global $wpdb;
    $wpdb->delete("{$wpdb->prefix}sgclabs_kw_global", ['id'=>intval($_POST['id'] ?? 0)]);
    wp_send_json_success();
}

// Add category keyword
add_action('wp_ajax_sgclabs_kw_add_category', 'sgclabs_kw_add_category');
function sgclabs_kw_add_category() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    global $wpdb;
    $cat_id   = intval($_POST['category_id'] ?? 0);
    $cat_name = sanitize_text_field($_POST['category_name'] ?? '');
    $keyword  = sanitize_text_field($_POST['keyword'] ?? '');
    $language = sanitize_text_field($_POST['language'] ?? 'en');
    if (!$keyword || !$cat_id) { wp_send_json_error('Category and Keyword required'); return; }
    $result = $wpdb->insert("{$wpdb->prefix}sgclabs_kw_category",
        ['category_id'=>$cat_id,'category_name'=>$cat_name,'keyword'=>$keyword,'language'=>$language]
    );
    if ($result) wp_send_json_success(['id'=>$wpdb->insert_id,'keyword'=>$keyword,'cat'=>$cat_name]);
    else wp_send_json_error('DB error');
}

// Delete category keyword
add_action('wp_ajax_sgclabs_kw_delete_category', 'sgclabs_kw_delete_category');
function sgclabs_kw_delete_category() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    global $wpdb;
    $wpdb->delete("{$wpdb->prefix}sgclabs_kw_category", ['id'=>intval($_POST['id'] ?? 0)]);
    wp_send_json_success();
}

// Bulk import keywords (manual paste)
add_action('wp_ajax_sgclabs_kw_bulk_import', 'sgclabs_kw_bulk_import');
function sgclabs_kw_bulk_import() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    global $wpdb;
    $text  = sanitize_textarea_field($_POST['keywords_text'] ?? '');
    $lang  = sanitize_text_field($_POST['language'] ?? 'en');
    $lines = array_filter(array_map('trim', explode("\n", $text)));
    $count = 0;
    foreach ($lines as $line) {
        if (!$line) continue;
        $r = $wpdb->insert("{$wpdb->prefix}sgclabs_kw_global",
            ['keyword'=>substr($line,0,500),'language'=>$lang,'source'=>'bulk']
        );
        if ($r) $count++;
    }
    // Clear SEO cache
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_sgclabs_seo_cache','_sgclabs_seo_cache_time','_sgclabs_multilang_time')");
    wp_send_json_success(['count'=>$count,'message'=>"{$count} keywords imported! SEO cache cleared."]);
}

// GSC Import (paste CSV data from GSC export)
add_action('wp_ajax_sgclabs_kw_gsc_import', 'sgclabs_kw_gsc_import');
function sgclabs_kw_gsc_import() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    global $wpdb;
    $csv  = sanitize_textarea_field($_POST['gsc_csv'] ?? '');
    $lines = array_filter(array_map('trim', explode("\n", $csv)));
    $count = 0;
    foreach ($lines as $i => $line) {
        if ($i === 0 && stripos((string)$line, 'query') !== false) continue; // skip header
        $cols = str_getcsv($line);
        if (empty($cols[0])) continue;
        $keyword     = sanitize_text_field($cols[0]);
        $clicks      = intval($cols[1] ?? 0);
        $impressions = intval($cols[2] ?? 0);
        $ctr         = floatval(str_replace('%','', $cols[3] ?? 0));
        $position    = floatval($cols[4] ?? 0);
        // Upsert
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}sgclabs_kw_gsc WHERE keyword=%s", $keyword
        ));
        if ($existing) {
            $wpdb->update("{$wpdb->prefix}sgclabs_kw_gsc",
                ['clicks'=>$clicks,'impressions'=>$impressions,'ctr'=>$ctr,'position'=>$position],
                ['id'=>$existing]
            );
        } else {
            $r = $wpdb->insert("{$wpdb->prefix}sgclabs_kw_gsc",
                ['keyword'=>$keyword,'clicks'=>$clicks,'impressions'=>$impressions,'ctr'=>$ctr,'position'=>$position]
            );
            if ($r) $count++;
        }
    }
    // Clear SEO cache
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_sgclabs_seo_cache','_sgclabs_seo_cache_time')");
    wp_send_json_success(['count'=>$count,'message'=>"{$count} GSC keywords imported!"]);
}

// Toggle keyword active/inactive
add_action('wp_ajax_sgclabs_kw_toggle', 'sgclabs_kw_toggle');
function sgclabs_kw_toggle() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    global $wpdb;
    $table  = sanitize_text_field($_POST['table'] ?? '');
    $id     = intval($_POST['id'] ?? 0);
    $active = intval($_POST['active'] ?? 0);
    $allowed = ['sgclabs_kw_global','sgclabs_kw_category','sgclabs_kw_gsc'];
    $full_table = $wpdb->prefix . $table;
    if (!in_array($table, array_map(fn($t) => str_replace($wpdb->prefix,'',$t), $allowed))) {
        wp_send_json_error('Invalid table'); return;
    }
    $wpdb->update($full_table, ['active'=>$active], ['id'=>$id]);
    wp_send_json_success();
}

// ============================================================
// ADMIN PAGE
// ============================================================
function sgclabs_keyword_manager_page() {
    global $wpdb;
    $global_kws  = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sgclabs_kw_global ORDER BY clicks DESC, id DESC");
    $cat_kws     = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sgclabs_kw_category ORDER BY category_name, id DESC");
    $gsc_kws     = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sgclabs_kw_gsc ORDER BY clicks DESC LIMIT 200");
    $categories  = get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'number'=>200]);
    $nonce       = wp_create_nonce('sgclabs_nonce');
    ?>
    <div class="wrap sgclabs-wrap">
        <h1>🔑 Keyword Manager</h1>
        <p>Manually add karo, Google Search Console se import karo — sab keywords <strong>automatically inject</strong> honge har product, category aur homepage pe!</p>

        <!-- Stats -->
        <div class="sgclabs-seo-stats">
            <div class="seo-stat-box green">
                <div class="seo-stat-num"><?php echo count($global_kws); ?></div>
                <div class="seo-stat-label">Global Keywords</div>
            </div>
            <div class="seo-stat-box blue">
                <div class="seo-stat-num"><?php echo count($cat_kws); ?></div>
                <div class="seo-stat-label">Category Keywords</div>
            </div>
            <div class="seo-stat-box orange">
                <div class="seo-stat-num"><?php echo count($gsc_kws); ?></div>
                <div class="seo-stat-label">GSC Keywords</div>
            </div>
            <div class="seo-stat-box">
                <div class="seo-stat-num"><?php echo count($global_kws) + count($cat_kws) + count($gsc_kws); ?></div>
                <div class="seo-stat-label">Total Keywords</div>
            </div>
        </div>

        <!-- TABS -->
        <div class="seo-data-tabs">
            <button class="seo-tab-btn active" onclick="showKwTab('tab-global')">🌐 Global Keywords <span class="seo-count"><?php echo count($global_kws); ?></span></button>
            <button class="seo-tab-btn" onclick="showKwTab('tab-category')">📂 Category Keywords <span class="seo-count"><?php echo count($cat_kws); ?></span></button>
            <button class="seo-tab-btn" onclick="showKwTab('tab-gsc')">📊 Google Search Console <span class="seo-count"><?php echo count($gsc_kws); ?></span></button>
            <button class="seo-tab-btn" onclick="showKwTab('tab-bulk')">📥 Bulk Import</button>
        </div>

        <!-- ========== GLOBAL KEYWORDS TAB ========== -->
        <div id="tab-global" class="kw-tab-content active">
            <div class="sgclabs-section">
                <h2>🌐 Add Global Keyword</h2>
                <p class="description">Yeh keywords <strong>saare products + homepage</strong> pe automatically inject honge.</p>
                <div class="seo-add-form">
                    <input type="text" id="gkw_keyword" placeholder="Keyword  e.g. borosilicate beaker India" class="seo-input" style="flex:2">
                    <select id="gkw_lang" class="seo-input">
                        <option value="en">🇬🇧 English</option>
                        <option value="hi">🇮🇳 Hindi</option>
                        <option value="ar">🇸🇦 Arabic</option>
                        <option value="de">🇩🇪 German</option>
                        <option value="fr">🇫🇷 French</option>
                        <option value="es">🇪🇸 Spanish</option>
                        <option value="zh">🇨🇳 Chinese</option>
                        <option value="ja">🇯🇵 Japanese</option>
                        <option value="ko">🇰🇷 Korean</option>
                        <option value="pt">🇧🇷 Portuguese</option>
                        <option value="ru">🇷🇺 Russian</option>
                    </select>
                    <button class="button button-primary" onclick="addGlobalKw()">➕ Add Keyword</button>
                </div>
                <div id="gkw-msg" style="margin-top:10px;display:none;"></div>
            </div>

            <div class="sgclabs-section">
                <h2>📋 All Global Keywords</h2>
                <input type="text" id="gkw-search" placeholder="🔍 Search keywords..." class="seo-input" style="width:300px;margin-bottom:15px;" oninput="filterKw('gkw-table',this.value)">
                <table class="wp-list-table widefat fixed striped" id="gkw-table">
                    <thead><tr><th width="55%">Keyword</th><th width="10%">Lang</th><th width="10%">Source</th><th width="10%">Status</th><th width="15%">Action</th></tr></thead>
                    <tbody id="gkw-tbody">
                    <?php foreach ($global_kws as $kw): ?>
                        <tr id="gkw-row-<?php echo intval($kw->id); ?>">
                            <td><strong><?php echo esc_html($kw->keyword); ?></strong></td>
                            <td><?php echo esc_html(strtoupper($kw->language)); ?></td>
                            <td><span class="sgclabs-tag" style="font-size:11px;"><?php echo esc_html($kw->source); ?></span></td>
                            <td>
                                <label class="kw-toggle">
                                    <input type="checkbox" <?php checked($kw->active,1); ?> onchange="toggleKw('sgclabs_kw_global',<?php echo intval($kw->id); ?>,this.checked?1:0)">
                                    <span><?php echo intval($kw->active) ? '✅' : '❌'; ?></span>
                                </label>
                            </td>
                            <td><button class="button button-small" style="color:red;" onclick="deleteKw('global',<?php echo intval($kw->id); ?>)">🗑️ Delete</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ========== CATEGORY KEYWORDS TAB ========== -->
        <div id="tab-category" class="kw-tab-content" style="display:none;">
            <div class="sgclabs-section">
                <h2>📂 Add Category-Specific Keyword</h2>
                <p class="description">Yeh keywords sirf us category ke products pe inject honge.</p>
                <div class="seo-add-form">
                    <select id="ckw_cat" class="seo-input" style="flex:1.5">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat->term_id; ?>" data-name="<?php echo esc_attr($cat->name); ?>">
                                <?php echo esc_html($cat->name); ?> (<?php echo $cat->count; ?> products)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" id="ckw_keyword" placeholder="Keyword  e.g. buy borosilicate beaker" class="seo-input" style="flex:2">
                    <select id="ckw_lang" class="seo-input">
                        <option value="en">English</option>
                        <option value="hi">Hindi</option>
                        <option value="ar">Arabic</option>
                        <option value="de">German</option>
                        <option value="fr">French</option>
                        <option value="es">Spanish</option>
                    </select>
                    <button class="button button-primary" onclick="addCategoryKw()">➕ Add</button>
                </div>
                <div id="ckw-msg" style="margin-top:10px;display:none;"></div>

                <div style="margin-top:20px;background:#f0f8ff;padding:15px;border-radius:8px;">
                    <h4 style="margin:0 0 10px;">💡 Smart Tip — Bulk add per category:</h4>
                    <p style="margin:0;font-size:13px;">Ek category select karo aur neeche se multiple keywords ek ek karke add karo. Jaise "Laboratory Glassware" category ke liye:</p>
                    <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:5px;">
                        <?php
                        $sample_kws = ['buy laboratory beaker India','borosilicate glassware wholesale','lab glassware manufacturer Ambala','laboratory flask price India','scientific glassware exporter'];
                        foreach ($sample_kws as $sk) echo "<span class='sgclabs-tag' style='cursor:pointer;' onclick=\"document.getElementById('ckw_keyword').value='{$sk}'\">{$sk}</span>";
                        ?>
                    </div>
                </div>
            </div>

            <div class="sgclabs-section">
                <h2>📋 Category Keywords</h2>
                <?php
                // Group by category
                $grouped = [];
                foreach ($cat_kws as $kw) {
                    $grouped[$kw->category_name][] = $kw;
                }
                foreach ($grouped as $cat_name => $kws_list):
                ?>
                    <div class="cat-kw-group">
                        <h4>📂 <?php echo esc_html($cat_name); ?> <span class="seo-count"><?php echo count($kws_list); ?></span></h4>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
                            <?php foreach ($kws_list as $kw): ?>
                                <span class="kw-chip <?php echo intval($kw->active) ? '' : 'inactive'; ?>">
                                    <?php echo esc_html($kw->keyword); ?>
                                    <small>(<?php echo strtoupper($kw->language); ?>)</small>
                                    <button onclick="deleteCatKw(<?php echo intval($kw->id); ?>)" style="background:none;border:none;cursor:pointer;color:#e74c3c;padding:0 3px;">✕</button>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($grouped)): ?>
                    <p>Abhi koi category keyword nahi hai. Upar se add karo!</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- ========== GSC TAB ========== -->
        <div id="tab-gsc" class="kw-tab-content" style="display:none;">
            <div class="sgclabs-section">
                <h2>📊 Google Search Console Import</h2>
                <div class="gsc-steps">
                    <div class="gsc-step"><span class="step-num">1</span> <a href="https://search.google.com/search-console" target="_blank">Google Search Console</a> mein jaao</div>
                    <div class="gsc-step"><span class="step-num">2</span> <strong>Performance → Search Results</strong> pe click karo</div>
                    <div class="gsc-step"><span class="step-num">3</span> Date range set karo (last 3 months recommended)</div>
                    <div class="gsc-step"><span class="step-num">4</span> <strong>Export → Download CSV</strong> karo</div>
                    <div class="gsc-step"><span class="step-num">5</span> CSV ka content copy karke neeche paste karo</div>
                </div>
                <textarea id="gsc_csv" rows="8" style="width:100%;font-family:monospace;font-size:12px;border:1px solid #ddd;border-radius:5px;padding:10px;" placeholder="GSC CSV data paste karo:
query,clicks,impressions,ctr,position
laboratory glassware india,150,5420,2.77%,8.3
buy borosilicate beaker,89,3210,2.77%,6.1
glass blowing components manufacturer,45,1890,2.38%,12.4
scientific equipment ambala,38,1560,2.44%,9.8"></textarea>
                <div style="margin-top:10px;display:flex;gap:10px;">
                    <button class="button button-primary button-large" onclick="importGSC()">📥 Import GSC Keywords</button>
                    <button class="button" onclick="document.getElementById('gsc_csv').value=''">🗑️ Clear</button>
                </div>
                <div id="gsc-msg" style="margin-top:10px;display:none;"></div>
            </div>

            <?php if (!empty($gsc_kws)): ?>
            <div class="sgclabs-section">
                <h2>📈 Imported GSC Keywords <small>(sorted by clicks)</small></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th width="45%">Keyword</th><th width="12%">Clicks</th><th width="15%">Impressions</th><th width="10%">CTR</th><th width="10%">Position</th><th width="8%">Active</th></tr></thead>
                    <tbody>
                    <?php foreach ($gsc_kws as $kw): ?>
                        <tr>
                            <td><?php echo esc_html($kw->keyword); ?></td>
                            <td><strong style="color:#27ae60;"><?php echo number_format($kw->clicks); ?></strong></td>
                            <td><?php echo number_format($kw->impressions); ?></td>
                            <td><?php echo round($kw->ctr, 2); ?>%</td>
                            <td>
                                <span style="color:<?php echo esc_attr(round($kw->position, 1)) <= 10 ? '#27ae60' : ($kw->position <= 20 ? '#e67e22' : '#e74c3c'); ?>">
                                    <?php echo round($kw->position, 1); ?>
                                </span>
                            </td>
                            <td>
                                <input type="checkbox" <?php checked($kw->active,1); ?> 
                                    onchange="toggleKw('sgclabs_kw_gsc',<?php echo intval($kw->id); ?>,this.checked?1:0)">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- ========== BULK IMPORT TAB ========== -->
        <div id="tab-bulk" class="kw-tab-content" style="display:none;">
            <div class="sgclabs-section">
                <h2>📥 Bulk Import Keywords</h2>
                <p>Ek line mein ek keyword paste karo — saare ek saath import ho jayenge!</p>
                <select id="bulk_lang" class="seo-input" style="width:200px;margin-bottom:10px;">
                    <option value="en">🇬🇧 English</option>
                    <option value="hi">🇮🇳 Hindi</option>
                    <option value="ar">🇸🇦 Arabic</option>
                    <option value="de">🇩🇪 German</option>
                    <option value="fr">🇫🇷 French</option>
                    <option value="es">🇪🇸 Spanish</option>
                    <option value="zh">🇨🇳 Chinese</option>
                </select>
                <textarea id="bulk_keywords" rows="12" style="width:100%;font-family:monospace;font-size:13px;border:1px solid #ddd;border-radius:5px;padding:10px;" 
                    placeholder="Ek line mein ek keyword:
laboratory glassware manufacturer India
buy borosilicate beaker online
scientific glass equipment supplier
glass blowing components wholesale
lab instruments Ambala India
borosilicate round bottom flask
laboratory metalware supplier
..."></textarea>
                <div style="margin-top:10px;display:flex;gap:10px;align-items:center;">
                    <button class="button button-primary button-large" onclick="bulkImport()">📥 Import All Keywords</button>
                    <span id="bulk-count" style="color:#666;font-size:13px;"></span>
                </div>
                <div id="bulk-msg" style="margin-top:10px;display:none;"></div>
            </div>
        </div>
    </div>

    <style>
    .seo-data-tabs { display:flex; gap:8px; margin-bottom:0; flex-wrap:wrap; }
    .seo-tab-btn { background:#f0f0f0; border:1px solid #ddd; padding:9px 16px; border-radius:8px 8px 0 0; cursor:pointer; font-size:13px; border-bottom:none; }
    .seo-tab-btn.active { background:white; border-color:#e0e0e0; border-bottom-color:white; font-weight:600; color:#1a1a2e; }
    .kw-tab-content .sgclabs-section { border-radius:0 8px 8px 8px; }
    .seo-count { background:#1a1a2e; color:white; border-radius:10px; padding:1px 7px; font-size:11px; margin-left:5px; }
    .seo-add-form { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
    .seo-input { padding:8px 12px; border:1px solid #ddd; border-radius:6px; font-size:14px; }
    .cat-kw-group { margin-bottom:20px; padding-bottom:15px; border-bottom:1px solid #f0f0f0; }
    .cat-kw-group h4 { margin:0 0 10px; color:#1a1a2e; }
    .kw-chip { background:#e8f4fd; border:1px solid #b8d9f0; border-radius:20px; padding:5px 12px; font-size:12px; display:inline-flex; align-items:center; gap:5px; }
    .kw-chip.inactive { background:#f5f5f5; border-color:#ddd; color:#999; }
    .gsc-steps { background:#f8f9ff; border-radius:8px; padding:15px; margin-bottom:15px; }
    .gsc-step { display:flex; align-items:center; gap:10px; padding:5px 0; font-size:13px; }
    .step-num { background:#1a1a2e; color:white; width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:bold; flex-shrink:0; }
    </style>

    <script>
    const nonce = '<?php echo esc_js($nonce); ?>';

    function showKwTab(id) {
        document.querySelectorAll('.kw-tab-content').forEach(el => el.style.display='none');
        document.querySelectorAll('.seo-tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById(id).style.display='block';
        event.target.classList.add('active');
    }

    function addGlobalKw() {
        const kw = document.getElementById('gkw_keyword').value.trim();
        const lang = document.getElementById('gkw_lang').value;
        if (!kw) { showMsg('gkw-msg','Please enter a keyword','error'); return; }
        jQuery.post(ajaxurl, {action:'sgclabs_kw_add_global',nonce,keyword:kw,language:lang}, function(res) {
            if (res.success) {
                showMsg('gkw-msg','✅ Keyword added!','success');
                document.getElementById('gkw_keyword').value='';
                // Add to table
                const tbody = document.getElementById('gkw-tbody');
                tbody.insertAdjacentHTML('afterbegin',`<tr id="gkw-row-${res.data.id}">
                    <td><strong>${res.data.keyword}</strong></td>
                    <td>${lang.toUpperCase()}</td>
                    <td><span class="sgclabs-tag" style="font-size:11px;">manual</span></td>
                    <td>✅</td>
                    <td><button class="button button-small" style="color:red;" onclick="deleteKw('global',${res.data.id})">🗑️ Delete</button></td>
                </tr>`);
            } else {
                showMsg('gkw-msg','❌ '+res.data,'error');
            }
        });
    }

    function addCategoryKw() {
        const sel = document.getElementById('ckw_cat');
        const cat_id = sel.value;
        const cat_name = sel.options[sel.selectedIndex]?.text?.split(' (')[0] || '';
        const kw = document.getElementById('ckw_keyword').value.trim();
        const lang = document.getElementById('ckw_lang').value;
        if (!cat_id || !kw) { showMsg('ckw-msg','Please select category and enter keyword','error'); return; }
        jQuery.post(ajaxurl, {action:'sgclabs_kw_add_category',nonce,category_id:cat_id,category_name:cat_name,keyword:kw,language:lang}, function(res) {
            if (res.success) {
                showMsg('ckw-msg','✅ Category keyword added!','success');
                document.getElementById('ckw_keyword').value='';
                setTimeout(()=>location.reload(), 1500);
            } else {
                showMsg('ckw-msg','❌ '+res.data,'error');
            }
        });
    }

    function deleteKw(type, id) {
        if (!confirm('Delete this keyword?')) return;
        const action = type==='global' ? 'sgclabs_kw_delete_global' : 'sgclabs_kw_delete_category';
        jQuery.post(ajaxurl, {action,nonce,id}, function(res) {
            if (res.success) {
                const row = document.getElementById('gkw-row-'+id);
                if (row) row.remove();
            }
        });
    }

    function deleteCatKw(id) {
        if (!confirm('Delete?')) return;
        jQuery.post(ajaxurl, {action:'sgclabs_kw_delete_category',nonce,id}, function(res) {
            if (res.success) setTimeout(()=>location.reload(), 500);
        });
    }

    function toggleKw(table, id, active) {
        jQuery.post(ajaxurl, {action:'sgclabs_kw_toggle',nonce,table,id,active});
    }

    function importGSC() {
        const csv = document.getElementById('gsc_csv').value.trim();
        if (!csv) { showMsg('gsc-msg','Please paste GSC CSV data','error'); return; }
        jQuery.post(ajaxurl, {action:'sgclabs_kw_gsc_import',nonce,gsc_csv:csv}, function(res) {
            if (res.success) {
                showMsg('gsc-msg','✅ '+res.data.message,'success');
                setTimeout(()=>location.reload(), 2000);
            } else showMsg('gsc-msg','❌ Error importing','error');
        });
    }

    function bulkImport() {
        const text = document.getElementById('bulk_keywords').value.trim();
        const lang = document.getElementById('bulk_lang').value;
        if (!text) { showMsg('bulk-msg','Please enter keywords','error'); return; }
        jQuery.post(ajaxurl, {action:'sgclabs_kw_bulk_import',nonce,keywords_text:text,language:lang}, function(res) {
            if (res.success) {
                showMsg('bulk-msg','✅ '+res.data.message,'success');
                setTimeout(()=>location.reload(), 2000);
            } else showMsg('bulk-msg','❌ Error','error');
        });
    }

    // Count lines in bulk textarea
    document.getElementById('bulk_keywords')?.addEventListener('input', function() {
        const lines = this.value.split('\n').filter(l=>l.trim()).length;
        document.getElementById('bulk-count').textContent = lines + ' keywords ready to import';
    });

    function filterKw(tableId, query) {
        const rows = document.querySelectorAll('#'+tableId+' tbody tr');
        rows.forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(query.toLowerCase()) ? '' : 'none';
        });
    }

    function showMsg(id, msg, type) {
        const el = document.getElementById(id);
        el.style.display='block';
        el.style.background = type==='success'?'#efe':'#fee';
        el.style.color = type==='success'?'#060':'#c00';
        el.style.padding = '10px';
        el.style.borderRadius = '5px';
        el.textContent = msg;
        setTimeout(()=>el.style.display='none', 4000);
    }
    </script>
    <?php
}
