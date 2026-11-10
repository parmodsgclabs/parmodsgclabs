<?php
if (!defined('ABSPATH')) exit;

/**
 * ============================================================
 * SGC LABS® LAB TECH SPECS — v6.6.3
 * ============================================================
 * Features:
 *  1. WooCommerce product metabox: HS Code, CAS No., UN No.,
 *     Material, Temp Range, Standards, Certs, COO, Gross Wt.
 *  2. Frontend: styled specs table on product page
 *  3. JSON-LD additionalProperty schema injection
 *  4. Admin: bulk CSV import page
 *  5. Shortcode: [sgclabs_tech_specs product_id="123"]
 * ============================================================
 */

// ── Field definitions ─────────────────────────────────────────────────────────
function sgclabs_specs_fields(): array {
    return [
        'hs_code'       => ['label' => 'HS Code',                'placeholder' => 'e.g. 7017.10',          'type' => 'text',  'schema_name' => 'HS Code'],
        'cas_number'    => ['label' => 'CAS Number',             'placeholder' => 'e.g. 7440-44-0',        'type' => 'text',  'schema_name' => 'CAS Number'],
        'un_number'     => ['label' => 'UN / DG Number',         'placeholder' => 'e.g. UN 3827',          'type' => 'text',  'schema_name' => 'UN Number'],
        'material'      => ['label' => 'Material / Composition', 'placeholder' => 'e.g. Borosilicate 3.3', 'type' => 'text',  'schema_name' => 'Material'],
        'temp_range'    => ['label' => 'Temperature Range',      'placeholder' => 'e.g. -80°C to +500°C',  'type' => 'text',  'schema_name' => 'Temperature Range'],
        'wall_thick'    => ['label' => 'Wall Thickness',         'placeholder' => 'e.g. 2.5 mm',           'type' => 'text',  'schema_name' => 'Wall Thickness'],
        'capacity'      => ['label' => 'Capacity / Volume',      'placeholder' => 'e.g. 500 mL',           'type' => 'text',  'schema_name' => 'Capacity'],
        'graduation'    => ['label' => 'Graduation',             'placeholder' => 'e.g. 10 mL intervals',  'type' => 'text',  'schema_name' => 'Graduation'],
        'neck_joint'    => ['label' => 'Neck / Joint Size',      'placeholder' => 'e.g. 29/32 standard',   'type' => 'text',  'schema_name' => 'Neck/Joint Size'],
        'standards'     => ['label' => 'Standards / Norms',      'placeholder' => 'e.g. ISO 3585, DIN 12220','type'=>'text',  'schema_name' => 'Standards'],
        'certifications'=> ['label' => 'Certifications',         'placeholder' => 'e.g. ISO 9001:2015, CE','type' => 'text',  'schema_name' => 'Certifications'],
        'country_origin'=> ['label' => 'Country of Origin',      'placeholder' => 'e.g. India',            'type' => 'text',  'schema_name' => 'Country of Origin'],
        'gross_weight'  => ['label' => 'Gross Weight',           'placeholder' => 'e.g. 1.2 kg per dozen', 'type' => 'text',  'schema_name' => 'Gross Weight'],
        'packing_unit'  => ['label' => 'Packing Unit (MOQ)',     'placeholder' => 'e.g. 12 pcs/box, min 60','type' => 'text', 'schema_name' => 'Packing/MOQ'],
        'shelf_life'    => ['label' => 'Shelf Life / Storage',   'placeholder' => 'e.g. Unlimited, dry storage','type' => 'text','schema_name' => 'Storage'],
        'custom_note'   => ['label' => 'Technical Note',         'placeholder' => 'Any additional spec...',  'type' => 'textarea','schema_name' => 'Technical Note'],
    ];
}

// ── Metabox registration ──────────────────────────────────────────────────────
add_action('add_meta_boxes', function() {
    add_meta_box(
        'sgclabs_tech_specs',
        '🔬 SGC Labs: Technical Specifications',
        'sgclabs_specs_metabox_cb',
        'product',
        'normal',
        'high'
    );
});

function sgclabs_specs_metabox_cb($post) {
    wp_nonce_field('sgclabs_specs_save', 'sgclabs_specs_nonce');
    $fields = sgclabs_specs_fields();
    $saved  = get_post_meta($post->ID, '_sgclabs_tech_specs', true) ?: [];
    $active = get_post_meta($post->ID, '_sgclabs_specs_active', true);
    $active = ($active === '') ? '1' : $active;
    ?>
    <div style="padding:4px 0;">
        <label style="display:flex;align-items:center;gap:8px;font-weight:600;margin-bottom:14px;color:#1a1a2e;">
            <input type="checkbox" name="sgclabs_specs_active" value="1" <?php checked($active, '1'); ?>>
            Show specs table on product page
        </label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px 20px;">
        <?php foreach ($fields as $key => $field):
            $val = $saved[$key] ?? '';
            ?>
            <div style="display:flex;flex-direction:column;gap:4px;<?php echo $field['type']==='textarea'?'grid-column:1/-1;':'' ?>">
                <label style="font-size:12px;font-weight:600;color:#444;"><?php echo esc_html($field['label']); ?></label>
                <?php if ($field['type'] === 'textarea'): ?>
                    <textarea name="sgclabs_specs[<?php echo esc_attr($key); ?>]"
                        placeholder="<?php echo esc_attr($field['placeholder']); ?>"
                        style="border:1px solid #ddd;border-radius:4px;padding:7px 10px;font-size:13px;resize:vertical;" rows="2"><?php echo esc_textarea($val); ?></textarea>
                <?php else: ?>
                    <input type="text" name="sgclabs_specs[<?php echo esc_attr($key); ?>]"
                        value="<?php echo esc_attr($val); ?>"
                        placeholder="<?php echo esc_attr($field['placeholder']); ?>"
                        style="border:1px solid #ddd;border-radius:4px;padding:7px 10px;font-size:13px;">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php
}

// ── Save metabox ──────────────────────────────────────────────────────────────
add_action('save_post_product', function($post_id) {
    if (!isset($_POST['sgclabs_specs_nonce'])) return;
    if (!wp_verify_nonce($_POST['sgclabs_specs_nonce'], 'sgclabs_specs_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $fields = sgclabs_specs_fields();
    $data   = [];
    $raw    = $_POST['sgclabs_specs'] ?? [];
    foreach ($fields as $key => $field) {
        $val = $raw[$key] ?? '';
        $data[$key] = ($field['type'] === 'textarea')
            ? sanitize_textarea_field($val)
            : sanitize_text_field($val);
    }
    update_post_meta($post_id, '_sgclabs_tech_specs', $data);
    update_post_meta($post_id, '_sgclabs_specs_active', isset($_POST['sgclabs_specs_active']) ? '1' : '0');
});

// ── Frontend: render specs table ──────────────────────────────────────────────
add_action('woocommerce_after_single_product_summary', 'sgclabs_render_specs_table', 12);
function sgclabs_render_specs_table() {
    if (get_option('sgclabs_lab_tech_specs_active', '1') !== '1') return;
    global $product;
    if (!$product) return;
    $pid    = $product->get_id();
    $active = get_post_meta($pid, '_sgclabs_specs_active', true);
    if ($active === '0') return;
    $specs  = get_post_meta($pid, '_sgclabs_tech_specs', true) ?: [];
    $fields = sgclabs_specs_fields();
    $rows   = [];
    foreach ($fields as $key => $field) {
        $v = trim($specs[$key] ?? '');
        if ($v !== '') $rows[] = [$field['label'], $v];
    }
    if (empty($rows)) return;
    ?>
    <div class="sgclabs-specs-wrap">
        <h2 class="sgclabs-specs-title">Technical Specifications</h2>
        <table class="sgclabs-specs-table">
            <tbody>
            <?php foreach ($rows as $i => [$label, $val]): ?>
                <tr class="<?php echo $i % 2 === 0 ? 'sgcsp-even' : 'sgcsp-odd'; ?>">
                    <th class="sgcsp-label"><?php echo esc_html($label); ?></th>
                    <td class="sgcsp-value"><?php echo esc_html($val); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <style>
    .sgclabs-specs-wrap { margin:24px 0 16px; clear:both; }
    .sgclabs-specs-title { font-size:20px; font-weight:700; color:#1a1a2e; margin-bottom:12px; border-left:4px solid #27ae60; padding-left:12px; }
    .sgclabs-specs-table { width:100%; border-collapse:collapse; font-size:14px; }
    .sgclabs-specs-table tr.sgcsp-even { background:#f9f9f9; }
    .sgclabs-specs-table tr.sgcsp-odd  { background:#fff; }
    .sgclabs-specs-table tr:last-child td,.sgclabs-specs-table tr:last-child th { border-bottom:none; }
    .sgclabs-specs-table .sgcsp-label { width:35%; padding:9px 14px; font-weight:600; color:#444; border-bottom:1px solid #eee; vertical-align:top; }
    .sgclabs-specs-table .sgcsp-value { padding:9px 14px; color:#222; border-bottom:1px solid #eee; }
    @media(max-width:480px){ .sgclabs-specs-table .sgcsp-label{width:45%;} }
    </style>
    <?php
}

// ── Shortcode ─────────────────────────────────────────────────────────────────
add_shortcode('sgclabs_tech_specs', function($atts) {
    $atts = shortcode_atts(['product_id' => 0], $atts);
    $pid  = intval($atts['product_id']);
    if (!$pid) return '<p style="color:#c00;">sgclabs_tech_specs: product_id required.</p>';
    $specs  = get_post_meta($pid, '_sgclabs_tech_specs', true) ?: [];
    $fields = sgclabs_specs_fields();
    $rows   = [];
    foreach ($fields as $key => $field) {
        $v = trim($specs[$key] ?? '');
        if ($v !== '') $rows[] = [$field['label'], $v];
    }
    if (empty($rows)) return '';
    ob_start(); ?>
    <table style="width:100%;border-collapse:collapse;font-size:14px;">
        <?php foreach ($rows as $i => [$l, $v]): ?>
        <tr style="background:<?php echo $i%2?'#fff':'#f9f9f9'; ?>">
            <th style="width:35%;padding:9px 14px;font-weight:600;color:#444;border-bottom:1px solid #eee;text-align:left;"><?php echo esc_html($l); ?></th>
            <td style="padding:9px 14px;border-bottom:1px solid #eee;"><?php echo esc_html($v); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php return ob_get_clean();
});

// ── JSON-LD additionalProperty schema injection ───────────────────────────────
function sgclabs_tech_specs_additional_properties(int $pid): array {
    $specs = get_post_meta($pid, '_sgclabs_tech_specs', true) ?: [];
    if (empty($specs) || !is_array($specs)) return [];
    $fields    = sgclabs_specs_fields();
    $add_props = [];
    foreach ($fields as $key => $field) {
        $v = trim($specs[$key] ?? '');
        if ($v === '') continue;
        $add_props[] = [ '@type' => 'PropertyValue', 'name' => $field['schema_name'], 'value' => $v ];
    }
    return $add_props;
}

// v7.5.1: Rank Math active → inject specs INTO its Product entity (no duplicate Product schema)
add_filter('rank_math/json_ld', function($data) {
    if (!function_exists('is_product') || !is_product() || !is_array($data)) return $data;
    $props = sgclabs_tech_specs_additional_properties(get_the_ID());
    if (!$props) return $data;
    foreach ($data as $k => $entity) {
        if (is_array($entity) && (($entity['@type'] ?? '') === 'Product' || (is_array($entity['@type'] ?? null) && in_array('Product', $entity['@type'], true)))) {
            $data[$k]['additionalProperty'] = array_merge($entity['additionalProperty'] ?? [], $props);
        }
    }
    return $data;
}, 99);

add_action('wp_head', function() {
    if (!is_product()) return;
    if (defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION')) return; // merged via rank_math/json_ld above
    global $product;
    if (!$product) return;
    $pid   = $product->get_id();
    $specs = get_post_meta($pid, '_sgclabs_tech_specs', true) ?: [];
    if (empty($specs)) return;
    $fields    = sgclabs_specs_fields();
    $add_props = [];
    foreach ($fields as $key => $field) {
        $v = trim($specs[$key] ?? '');
        if ($v === '') continue;
        $add_props[] = [
            '@type'       => 'PropertyValue',
            'name'        => $field['schema_name'],
            'value'       => $v,
        ];
    }
    if (empty($add_props)) return;
    $schema = [
        '@context'           => 'https://schema.org/',
        '@type'              => 'Product',
        'name'               => $product->get_name(),
        'sku'                => $product->get_sku(),
        'additionalProperty' => $add_props,
    ];
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}, 10);

// ── Admin menu ────────────────────────────────────────────────────────────────
add_action('admin_menu', function() {
    add_submenu_page(
        'sgclabs-manager',
        'Tech Specs',
        '🔬 Tech Specs',
        'manage_options',
        'sgclabs-tech-specs',
        'sgclabs_tech_specs_admin_page'
    );
}, 19);

function sgclabs_tech_specs_admin_page() {
    $csv_msg = '';
    if (isset($_POST['sgclabs_import_specs']) && current_user_can('manage_options')) {
        check_admin_referer('sgclabs_specs_import_nonce');
        if (!empty($_FILES['specs_csv']['tmp_name'])) {
            $result = sgclabs_import_specs_csv($_FILES['specs_csv']['tmp_name']);
            $csv_msg = $result['msg'];
        }
    }
    ?>
    <div class="wrap sgclabs-wrap">
        <h1>🔬 Lab Tech Specs</h1>

        <?php if ($csv_msg): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($csv_msg); ?></p></div>
        <?php endif; ?>

        <div class="sgclabs-section">
            <h3>How to use</h3>
            <p>Open any product in WooCommerce → find <strong>"SGC Labs: Technical Specifications"</strong> metabox → fill in the fields → Update product.</p>
            <p>The specs table automatically appears on the product page and is included in JSON-LD structured data (Google Rich Results).</p>
        </div>

        <div class="sgclabs-section">
            <h3>📥 Bulk Import via CSV</h3>
            <p>CSV format: <code>product_id, hs_code, cas_number, un_number, material, temp_range, wall_thick, capacity, graduation, neck_joint, standards, certifications, country_origin, gross_weight, packing_unit, shelf_life, custom_note</code></p>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('sgclabs_specs_import_nonce'); ?>
                <input type="hidden" name="sgclabs_import_specs" value="1">
                <input type="file" name="specs_csv" accept=".csv" required style="margin-right:10px;">
                <button type="submit" class="button button-primary">Import CSV</button>
            </form>
            <p style="margin-top:10px;">
                <a href="#" onclick="sgclabsDownloadSpecsCsvTemplate();return false;" style="text-decoration:none;">⬇️ Download CSV Template</a>
            </p>
        </div>

        <div class="sgclabs-section">
            <h3>📋 Products with Specs</h3>
            <?php
            global $wpdb;
            $products_with_specs = $wpdb->get_results(
                "SELECT p.ID, p.post_title FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id
                 WHERE pm.meta_key='_sgclabs_tech_specs' AND p.post_type='product' AND p.post_status='publish'
                 ORDER BY p.post_title ASC LIMIT 100"
            );
            if (empty($products_with_specs)): ?>
                <p>No products have specs added yet.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>Product</th><th>ID</th><th>Specs Active</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($products_with_specs as $prod):
                        $active = get_post_meta($prod->ID, '_sgclabs_specs_active', true);
                        $active = ($active === '') ? '1' : $active;
                    ?>
                        <tr>
                            <td><?php echo esc_html($prod->post_title); ?></td>
                            <td><?php echo intval($prod->ID); ?></td>
                            <td><?php echo $active === '1' ? '<span style="color:#27ae60;font-weight:600;">✅ Yes</span>' : '<span style="color:#e74c3c;">✗ Hidden</span>'; ?></td>
                            <td><a href="<?php echo esc_url(get_edit_post_link($prod->ID)); ?>" class="button button-small">Edit Specs</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <script>
    function sgclabsDownloadSpecsCsvTemplate(){
        var header='product_id,hs_code,cas_number,un_number,material,temp_range,wall_thick,capacity,graduation,neck_joint,standards,certifications,country_origin,gross_weight,packing_unit,shelf_life,custom_note\n';
        var row='123,7017.10,7440-44-0,UN 3827,Borosilicate 3.3,-80°C to +500°C,2.5 mm,500 mL,10 mL intervals,29/32,"ISO 3585, DIN 12220",ISO 9001:2015,India,1.2 kg/doz,12 pcs/box - min 60,Unlimited dry storage,Use with heating mantle only\n';
        var blob=new Blob([header+row],{type:'text/csv'});
        var a=document.createElement('a'); a.href=URL.createObjectURL(blob);
        a.download='sgclabs-specs-import-template.csv'; a.click();
    }
    </script>
    <?php
}

// ── CSV Import handler ────────────────────────────────────────────────────────
function sgclabs_import_specs_csv(string $filepath): array {
    $handle = fopen($filepath, 'r');
    if (!$handle) return ['msg' => 'Could not read file.'];

    $headers = fgetcsv($handle); // skip header row
    $field_keys = array_keys(sgclabs_specs_fields());
    $imported = 0; $skipped = 0;

    while (($row = fgetcsv($handle)) !== false) {
        $pid = intval($row[0] ?? 0);
        if (!$pid || get_post_type($pid) !== 'product') { $skipped++; continue; }
        $data = [];
        foreach ($field_keys as $i => $key) {
            $data[$key] = sanitize_text_field($row[$i + 1] ?? '');
        }
        update_post_meta($pid, '_sgclabs_tech_specs', $data);
        update_post_meta($pid, '_sgclabs_specs_active', '1');
        $imported++;
    }
    fclose($handle);
    return ['msg' => "Import complete: {$imported} products updated, {$skipped} skipped."];
}
