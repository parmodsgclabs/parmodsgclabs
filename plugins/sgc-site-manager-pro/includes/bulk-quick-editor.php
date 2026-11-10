<?php
/**
 * SGC LABS® Bulk Quick Editor v1.0.0
 * Module 1: Quick SEO Panel — product list, inline edit, 1-click copy
 * Module 2: Image URL Assigner — category filter, SKU/size auto, image URL paste
 * @package SGCLabs_Manager_Pro
 * @since   6.8.7
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================
// AJAX — Load products for Quick SEO Panel
// ============================================================
add_action( 'wp_ajax_sgclabs_qe_load_products', 'sgclabs_qe_load_products' );
function sgclabs_qe_load_products() {
    check_ajax_referer( 'sgclabs_qe_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    global $wpdb;
    $cat_id  = intval( $_POST['cat_id'] ?? 0 );
    $search  = sanitize_text_field( $_POST['search'] ?? '' );
    $page    = max( 1, intval( $_POST['page'] ?? 1 ) );
    $per     = 20;
    $offset  = ( $page - 1 ) * $per;

    $where = "p.post_type = 'product' AND p.post_status = 'publish'";
    $join  = '';

    if ( $cat_id > 0 ) {
        $join  = " INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
                   INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.term_id = %d";
        $where .= $wpdb->prepare( ' AND tt.taxonomy = %s', 'product_cat' );
    }

    if ( $search ) {
        $like   = '%' . $wpdb->esc_like( $search ) . '%';
        $where .= $wpdb->prepare( ' AND (p.post_title LIKE %s OR pm_sku.meta_value LIKE %s)', $like, $like );
    }

    $sku_join = " LEFT JOIN {$wpdb->postmeta} pm_sku ON pm_sku.post_id = p.ID AND pm_sku.meta_key = '_sku'";

    $base_sql = "FROM {$wpdb->posts} p {$sku_join} {$join} WHERE {$where}";

    if ( $cat_id > 0 ) {
        $total_sql = $wpdb->prepare( "SELECT COUNT(DISTINCT p.ID) {$base_sql}", $cat_id );
        $rows_sql  = $wpdb->prepare( "SELECT DISTINCT p.ID, p.post_title, pm_sku.meta_value as sku {$base_sql} ORDER BY p.post_title ASC LIMIT %d OFFSET %d", $cat_id, $per, $offset );
    } else {
        $total_sql = "SELECT COUNT(DISTINCT p.ID) {$base_sql}";
        $rows_sql  = $wpdb->prepare( "SELECT DISTINCT p.ID, p.post_title, pm_sku.meta_value as sku {$base_sql} ORDER BY p.post_title ASC LIMIT %d OFFSET %d", $per, $offset );
    }

    $total   = (int) $wpdb->get_var( $total_sql );
    $rows    = $wpdb->get_results( $rows_sql );
    $pages   = max( 1, ceil( $total / $per ) );

    $products = [];
    foreach ( $rows as $row ) {
        $pid = (int) $row->ID;
        $products[] = [
            'id'          => $pid,
            'title'       => $row->post_title,
            'sku'         => $row->sku ?: '',
            'seo_title'   => get_post_meta( $pid, '_sgclabs_seo_title',       true ) ?: '',
            'meta_title'  => get_post_meta( $pid, '_sgclabs_meta_title',      true ) ?: '',
            'meta_desc'   => get_post_meta( $pid, '_sgclabs_seo_description', true ) ?: '',
        ];
    }

    wp_send_json_success( [ 'products' => $products, 'total' => $total, 'pages' => $pages, 'page' => $page ] );
}

// ============================================================
// AJAX — Save SEO fields (Quick SEO Panel)
// ============================================================
add_action( 'wp_ajax_sgclabs_qe_save_seo', 'sgclabs_qe_save_seo' );
function sgclabs_qe_save_seo() {
    check_ajax_referer( 'sgclabs_qe_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $pid        = intval( $_POST['pid'] ?? 0 );
    $seo_title  = sanitize_text_field( $_POST['seo_title']  ?? '' );
    $meta_title = sanitize_text_field( $_POST['meta_title'] ?? '' );
    $meta_desc  = sanitize_textarea_field( $_POST['meta_desc']  ?? '' );

    if ( ! $pid ) wp_send_json_error( 'Invalid product ID' );

    update_post_meta( $pid, '_sgclabs_seo_title',       $seo_title );
    update_post_meta( $pid, '_sgclabs_meta_title',      $meta_title );
    update_post_meta( $pid, '_sgclabs_seo_description', $meta_desc );

    wp_send_json_success( 'Saved' );
}

// ============================================================
// AJAX — Load products for Image Assigner
// ============================================================
add_action( 'wp_ajax_sgclabs_ia_load', 'sgclabs_ia_load' );
function sgclabs_ia_load() {
    check_ajax_referer( 'sgclabs_qe_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    global $wpdb;
    $cat_id = intval( $_POST['cat_id'] ?? 0 );
    $search = sanitize_text_field( $_POST['search'] ?? '' );
    $page   = max( 1, intval( $_POST['page'] ?? 1 ) );
    $per    = 25;
    $offset = ( $page - 1 ) * $per;

    $where = "p.post_type = 'product' AND p.post_status = 'publish'";

    if ( $search ) {
        $like   = '%' . $wpdb->esc_like( $search ) . '%';
        $where .= $wpdb->prepare( ' AND (p.post_title LIKE %s OR pm_sku.meta_value LIKE %s)', $like, $like );
    }

    $sku_join  = " LEFT JOIN {$wpdb->postmeta} pm_sku  ON pm_sku.post_id  = p.ID AND pm_sku.meta_key  = '_sku'";
    $attr_join = " LEFT JOIN {$wpdb->postmeta} pm_attr ON pm_attr.post_id = p.ID AND pm_attr.meta_key = '_product_attributes'";
    $img_join  = " LEFT JOIN {$wpdb->postmeta} pm_img  ON pm_img.post_id  = p.ID AND pm_img.meta_key  = '_thumbnail_id'";

    if ( $cat_id > 0 ) {
        $cat_join  = " INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
                       INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.term_id = {$cat_id} AND tt.taxonomy = 'product_cat'";
        $total_sql = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p {$sku_join} {$cat_join} WHERE {$where}";
        $rows_sql  = $wpdb->prepare( "SELECT DISTINCT p.ID, p.post_title, pm_sku.meta_value as sku, pm_attr.meta_value as attrs, pm_img.meta_value as thumb_id FROM {$wpdb->posts} p {$sku_join} {$attr_join} {$img_join} {$cat_join} WHERE {$where} ORDER BY p.post_title ASC LIMIT %d OFFSET %d", $per, $offset );
    } else {
        $total_sql = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p {$sku_join} WHERE {$where}";
        $rows_sql  = $wpdb->prepare( "SELECT DISTINCT p.ID, p.post_title, pm_sku.meta_value as sku, pm_attr.meta_value as attrs, pm_img.meta_value as thumb_id FROM {$wpdb->posts} p {$sku_join} {$attr_join} {$img_join} WHERE {$where} ORDER BY p.post_title ASC LIMIT %d OFFSET %d", $per, $offset );
    }

    $total = (int) $wpdb->get_var( $total_sql );
    $rows  = $wpdb->get_results( $rows_sql );
    $pages = max( 1, ceil( $total / $per ) );

    $products = [];
    foreach ( $rows as $row ) {
        $pid      = (int) $row->ID;
        $capacity = '';
        if ( $row->attrs ) {
            $attrs = maybe_unserialize( $row->attrs );
            if ( is_array( $attrs ) ) {
                foreach ( [ 'capacity', 'size', 'volume', 'pa_capacity', 'pa_size', 'pa_volume' ] as $key ) {
                    if ( isset( $attrs[ $key ] ) ) {
                        $val = $attrs[ $key ];
                        $capacity = is_array( $val ) ? ( $val['value'] ?? '' ) : (string) $val;
                        if ( $capacity ) break;
                    }
                }
            }
        }
        $has_img = ! empty( $row->thumb_id );
        $products[] = [
            'id'       => $pid,
            'title'    => $row->post_title,
            'sku'      => $row->sku ?: '—',
            'capacity' => $capacity ?: '—',
            'has_img'  => $has_img,
        ];
    }

    wp_send_json_success( [ 'products' => $products, 'total' => $total, 'pages' => $pages, 'page' => $page ] );
}

// ============================================================
// AJAX — Save image URL to product (Image Assigner)
// ============================================================
add_action( 'wp_ajax_sgclabs_ia_save_image', 'sgclabs_ia_save_image' );
function sgclabs_ia_save_image() {
    check_ajax_referer( 'sgclabs_qe_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $pid      = intval( $_POST['pid'] ?? 0 );
    $img_url  = esc_url_raw( $_POST['img_url'] ?? '' );

    if ( ! $pid || ! $img_url ) wp_send_json_error( 'Invalid data' );

    // Try to find attachment by URL in media library
    $attach_id = attachment_url_to_postid( $img_url );

    // If not found, try searching by filename
    if ( ! $attach_id ) {
        global $wpdb;
        $filename  = basename( parse_url( $img_url, PHP_URL_PATH ) );
        $attach_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s",
            '%' . $wpdb->esc_like( $filename )
        ) );
    }

    if ( ! $attach_id ) {
        wp_send_json_error( 'Image media library mein nahi mili. Pehle Media > Upload se upload karo.' );
    }

    set_post_thumbnail( $pid, $attach_id );
    $thumb_url = wp_get_attachment_image_url( $attach_id, 'thumbnail' );

    wp_send_json_success( [ 'msg' => 'Image save ho gayi!', 'thumb' => $thumb_url ] );
}

// ============================================================
// ADMIN PAGE
// ============================================================
function sgclabs_bulk_quick_editor_page() {
    $nonce = wp_create_nonce( 'sgclabs_qe_nonce' );

    // Get all product categories
    $cats = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => true, 'orderby' => 'name', 'order' => 'ASC' ] );
    ?>
    <div class="wrap sgclabs-wrap">
        <h1>⚡ Quick Bulk Editor</h1>
        <p style="color:#666;margin-bottom:20px;">Product page khule bina SEO fields edit karo ya images assign karo.</p>

        <!-- Module Tabs -->
        <div style="display:flex;gap:4px;margin-bottom:24px;border-bottom:2px solid #e0e0e0;">
            <button class="sgc-mtab sgc-mtab-active" onclick="sgcTab('seo')">📝 Quick SEO Panel</button>
            <button class="sgc-mtab" onclick="sgcTab('img')">🖼️ Image URL Assigner</button>
        </div>

        <!-- ── MODULE 1: Quick SEO Panel ── -->
        <div id="tab-seo">
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:16px;">
                <select id="seo-cat" style="min-width:180px;padding:6px 10px;">
                    <option value="0">— Sab Categories —</option>
                    <?php foreach ( $cats as $cat ) : ?>
                        <option value="<?php echo esc_attr( $cat->term_id ); ?>"><?php echo esc_html( $cat->name ); ?> (<?php echo $cat->count; ?>)</option>
                    <?php endforeach; ?>
                </select>
                <input type="text" id="seo-search" placeholder="Product name ya SKU search..." style="width:220px;padding:6px 10px;">
                <button class="button button-primary" onclick="sgcSeoLoad(1)">🔍 Load Products</button>
                <span id="seo-count" style="color:#888;font-size:13px;"></span>
            </div>

            <div id="seo-table-wrap">
                <p style="color:#aaa;text-align:center;padding:40px;">Category select karo aur Load karo.</p>
            </div>
            <div id="seo-pag" style="margin-top:12px;"></div>
        </div>

        <!-- ── MODULE 2: Image URL Assigner ── -->
        <div id="tab-img" style="display:none;">
            <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:12px 16px;margin-bottom:16px;">
                <strong>⚠️ Image URL kaise kaam karta hai:</strong> Pehle <strong>Media > Add New</strong> se images upload karo. Phir media library mein image open karo — URL copy karo — yahan paste karo.
            </div>

            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:16px;">
                <select id="img-cat" style="min-width:180px;padding:6px 10px;">
                    <option value="0">— Sab Categories —</option>
                    <?php foreach ( $cats as $cat ) : ?>
                        <option value="<?php echo esc_attr( $cat->term_id ); ?>"><?php echo esc_html( $cat->name ); ?> (<?php echo $cat->count; ?>)</option>
                    <?php endforeach; ?>
                </select>
                <input type="text" id="img-search" placeholder="Product name ya SKU..." style="width:220px;padding:6px 10px;">
                <button class="button button-primary" onclick="sgcImgLoad(1)">🔍 Load Products</button>
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                    <input type="checkbox" id="img-no-img-only"> Sirf bina image wale
                </label>
                <span id="img-count" style="color:#888;font-size:13px;"></span>
            </div>

            <div id="img-table-wrap">
                <p style="color:#aaa;text-align:center;padding:40px;">Category select karo aur Load karo.</p>
            </div>
            <div id="img-pag" style="margin-top:12px;"></div>
        </div>
    </div>

    <style>
    .sgclabs-wrap { max-width:1400px; }
    .sgc-mtab { background:none;border:none;border-bottom:3px solid transparent;padding:10px 20px;font-size:14px;cursor:pointer;color:#555;font-weight:500; }
    .sgc-mtab:hover { color:#2271b1; }
    .sgc-mtab-active { color:#2271b1;border-bottom-color:#2271b1; }
    .sgc-seo-table { width:100%;border-collapse:collapse;font-size:13px; }
    .sgc-seo-table th { background:#f8f9fa;padding:8px 10px;text-align:left;border-bottom:2px solid #e0e0e0;font-weight:600;position:sticky;top:32px;z-index:1; }
    .sgc-seo-table td { padding:8px 10px;border-bottom:1px solid #f0f0f0;vertical-align:top; }
    .sgc-seo-table tr:hover td { background:#f8f9ff; }
    .sgc-seo-table input[type=text] { width:100%;padding:5px 8px;border:1px solid #ddd;border-radius:4px;font-size:12px;box-sizing:border-box; }
    .sgc-seo-table textarea { width:100%;padding:5px 8px;border:1px solid #ddd;border-radius:4px;font-size:12px;resize:vertical;min-height:52px;box-sizing:border-box; }
    .sgc-copy-btn { background:none;border:1px solid #ccc;border-radius:4px;padding:3px 7px;cursor:pointer;font-size:11px;color:#555;white-space:nowrap; }
    .sgc-copy-btn:hover { background:#e8f0fe;border-color:#2271b1;color:#2271b1; }
    .sgc-save-btn { background:#2271b1;color:#fff;border:none;border-radius:4px;padding:5px 12px;cursor:pointer;font-size:12px;white-space:nowrap; }
    .sgc-save-btn:hover { background:#135e96; }
    .sgc-saved { color:#27ae60;font-size:11px;display:none;margin-top:4px; }
    .sgc-pid { color:#aaa;font-size:11px; }
    .sgc-img-table { width:100%;border-collapse:collapse;font-size:13px; }
    .sgc-img-table th { background:#f8f9fa;padding:8px 10px;text-align:left;border-bottom:2px solid #e0e0e0;font-weight:600; }
    .sgc-img-table td { padding:8px 10px;border-bottom:1px solid #f0f0f0;vertical-align:middle; }
    .sgc-img-table tr:hover td { background:#f8f9ff; }
    .sgc-tab-row { display:flex;gap:0; }
    .sgc-tab-btn { background:#f5f5f5;border:1px solid #ddd;padding:4px 12px;font-size:11px;cursor:pointer;border-radius:0; }
    .sgc-tab-btn:first-child { border-radius:4px 0 0 4px; }
    .sgc-tab-btn:last-child { border-radius:0 4px 4px 0; }
    .sgc-tab-btn.act { background:#2271b1;color:#fff;border-color:#2271b1; }
    .sgc-tab-content { display:none;margin-top:6px; }
    .sgc-tab-content.act { display:block; }
    .sgc-img-badge { display:inline-block;padding:2px 7px;border-radius:10px;font-size:11px;font-weight:600; }
    .sgc-has-img { background:#d4edda;color:#155724; }
    .sgc-no-img { background:#f8d7da;color:#721c24; }
    .sgc-pag-btn { display:inline-block;padding:4px 10px;margin:2px;border:1px solid #ddd;border-radius:4px;cursor:pointer;font-size:12px;background:#fff; }
    .sgc-pag-btn:hover,.sgc-pag-btn.act { background:#2271b1;color:#fff;border-color:#2271b1; }
    .sgc-img-preview { width:40px;height:40px;object-fit:cover;border-radius:4px;border:1px solid #ddd; }
    </style>

    <script>
    var SGC_QE = { nonce: '<?php echo esc_js( $nonce ); ?>', seoPage: 1, imgPage: 1 };

    // Tab switcher
    function sgcTab(tab) {
        document.getElementById('tab-seo').style.display = tab === 'seo' ? '' : 'none';
        document.getElementById('tab-img').style.display = tab === 'img' ? '' : 'none';
        document.querySelectorAll('.sgc-mtab').forEach(function(b,i) {
            b.classList.toggle('sgc-mtab-active', (tab==='seo' && i===0)||(tab==='img' && i===1));
        });
    }

    // ── SEO Panel ──
    function sgcSeoLoad(page) {
        SGC_QE.seoPage = page;
        document.getElementById('seo-table-wrap').innerHTML = '<p style="color:#aaa;text-align:center;padding:40px;">Loading...</p>';
        jQuery.post(ajaxurl, {
            action: 'sgclabs_qe_load_products',
            nonce:  SGC_QE.nonce,
            cat_id: document.getElementById('seo-cat').value,
            search: document.getElementById('seo-search').value,
            page:   page
        }, function(res) {
            if (!res.success) { alert(res.data); return; }
            var d = res.data;
            document.getElementById('seo-count').textContent = d.total + ' products';
            if (!d.products.length) {
                document.getElementById('seo-table-wrap').innerHTML = '<p style="color:#aaa;text-align:center;padding:40px;">Koi product nahi mila.</p>';
                return;
            }
            var html = '<table class="sgc-seo-table"><thead><tr><th style="width:30px">#</th><th style="width:180px">Product</th><th>SEO Title</th><th>Meta Title</th><th>Meta Description</th><th style="width:70px">Action</th></tr></thead><tbody>';
            d.products.forEach(function(p) {
                html += '<tr id="seo-row-'+p.id+'">';
                html += '<td><span class="sgc-pid">'+p.id+'</span></td>';
                html += '<td><strong>'+escHtml(p.title)+'</strong><br><span style="color:#888;font-size:11px;">SKU: '+escHtml(p.sku)+'</span></td>';
                html += '<td><input type="text" id="st-'+p.id+'" value="'+escAttr(p.seo_title)+'"><br><button class="sgc-copy-btn" onclick="sgcCopy(\'st-'+p.id+'\')">📋 Copy</button></td>';
                html += '<td><input type="text" id="mt-'+p.id+'" value="'+escAttr(p.meta_title)+'"><br><button class="sgc-copy-btn" onclick="sgcCopy(\'mt-'+p.id+'\')">📋 Copy</button></td>';
                html += '<td><textarea id="md-'+p.id+'">'+escHtml(p.meta_desc)+'</textarea><br><button class="sgc-copy-btn" onclick="sgcCopy(\'md-'+p.id+'\')">📋 Copy</button></td>';
                html += '<td><button class="sgc-save-btn" onclick="sgcSeoSave('+p.id+')">💾 Save</button><div class="sgc-saved" id="saved-'+p.id+'">✓ Saved</div></td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            document.getElementById('seo-table-wrap').innerHTML = html;
            sgcPagination('seo-pag', d.page, d.pages, 'sgcSeoLoad');
        });
    }

    function sgcSeoSave(pid) {
        jQuery.post(ajaxurl, {
            action:     'sgclabs_qe_save_seo',
            nonce:      SGC_QE.nonce,
            pid:        pid,
            seo_title:  document.getElementById('st-'+pid).value,
            meta_title: document.getElementById('mt-'+pid).value,
            meta_desc:  document.getElementById('md-'+pid).value,
        }, function(res) {
            var el = document.getElementById('saved-'+pid);
            if (res.success) { el.style.display='block'; setTimeout(function(){el.style.display='none';},2000); }
            else alert('Error: '+res.data);
        });
    }

    function sgcCopy(id) {
        var el = document.getElementById(id);
        el.select(); el.setSelectionRange(0,99999);
        navigator.clipboard.writeText(el.value).then(function() {
            var btn = el.nextElementSibling;
            var orig = btn.textContent;
            btn.textContent = '✓ Copied!';
            btn.style.color = '#27ae60';
            setTimeout(function(){ btn.textContent = orig; btn.style.color=''; }, 1500);
        });
    }

    // ── Image Assigner ──
    function sgcImgLoad(page) {
        SGC_QE.imgPage = page;
        document.getElementById('img-table-wrap').innerHTML = '<p style="color:#aaa;text-align:center;padding:40px;">Loading...</p>';
        jQuery.post(ajaxurl, {
            action: 'sgclabs_ia_load',
            nonce:  SGC_QE.nonce,
            cat_id: document.getElementById('img-cat').value,
            search: document.getElementById('img-search').value,
            page:   page
        }, function(res) {
            if (!res.success) { alert(res.data); return; }
            var d = res.data;
            var prods = d.products;
            if (document.getElementById('img-no-img-only').checked) {
                prods = prods.filter(function(p){ return !p.has_img; });
            }
            document.getElementById('img-count').textContent = d.total + ' products';
            if (!prods.length) {
                document.getElementById('img-table-wrap').innerHTML = '<p style="color:#aaa;text-align:center;padding:40px;">Koi product nahi mila.</p>';
                return;
            }
            var html = '<table class="sgc-img-table"><thead><tr><th style="width:30px">#</th><th>Product</th><th style="width:80px">Image</th><th style="width:350px">SKU / Size / Image URL</th><th style="width:80px">Action</th></tr></thead><tbody>';
            prods.forEach(function(p) {
                var badge = p.has_img
                    ? '<span class="sgc-img-badge sgc-has-img">✓ Image hai</span>'
                    : '<span class="sgc-img-badge sgc-no-img">✗ No image</span>';
                html += '<tr id="img-row-'+p.id+'">';
                html += '<td><span class="sgc-pid">'+p.id+'</span></td>';
                html += '<td><strong>'+escHtml(p.title)+'</strong><br>'+badge+'</td>';
                html += '<td><img id="thumb-'+p.id+'" src="" style="display:'+(p.has_img?'block':'none')+';width:44px;height:44px;object-fit:cover;border-radius:4px;border:1px solid #ddd;" onerror="this.style.display=\'none\'"></td>';
                html += '<td>';
                // 3 tabs
                html += '<div class="sgc-tab-row">';
                html += '<button class="sgc-tab-btn act" onclick="sgcImgTab('+p.id+',\'sku\',this)">SKU</button>';
                html += '<button class="sgc-tab-btn" onclick="sgcImgTab('+p.id+',\'cap\',this)">Size/Capacity</button>';
                html += '<button class="sgc-tab-btn" onclick="sgcImgTab('+p.id+',\'url\',this)">Image URL</button>';
                html += '</div>';
                html += '<div id="itab-sku-'+p.id+'" class="sgc-tab-content act"><input type="text" value="'+escAttr(p.sku)+'" readonly style="background:#f5f5f5;cursor:pointer;" onclick="navigator.clipboard.writeText(this.value)"><span style="font-size:11px;color:#888;"> (click to copy)</span></div>';
                html += '<div id="itab-cap-'+p.id+'" class="sgc-tab-content"><input type="text" value="'+escAttr(p.capacity)+'" readonly style="background:#f5f5f5;cursor:pointer;" onclick="navigator.clipboard.writeText(this.value)"><span style="font-size:11px;color:#888;"> (click to copy)</span></div>';
                html += '<div id="itab-url-'+p.id+'" class="sgc-tab-content"><input type="url" id="iurl-'+p.id+'" placeholder="https://yoursite.com/wp-content/uploads/...image.jpg" style="width:100%;"></div>';
                html += '</td>';
                html += '<td><button class="sgc-save-btn" onclick="sgcImgSave('+p.id+')">💾 Save</button><div class="sgc-saved" id="isaved-'+p.id+'">✓ Done</div></td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            document.getElementById('img-table-wrap').innerHTML = html;
            sgcPagination('img-pag', d.page, d.pages, 'sgcImgLoad');
        });
    }

    function sgcImgTab(pid, tab, btn) {
        ['sku','cap','url'].forEach(function(t) {
            document.getElementById('itab-'+t+'-'+pid).classList.toggle('act', t===tab);
        });
        btn.closest('.sgc-tab-row').querySelectorAll('.sgc-tab-btn').forEach(function(b){ b.classList.remove('act'); });
        btn.classList.add('act');
        if (tab === 'url') {
            setTimeout(function(){ document.getElementById('iurl-'+pid).focus(); }, 50);
        }
    }

    function sgcImgSave(pid) {
        var urlEl = document.getElementById('iurl-'+pid);
        var url   = urlEl ? urlEl.value.trim() : '';
        if (!url) { alert('Image URL tab mein URL paste karo pehle!'); sgcImgTab(pid,'url', document.querySelector('#img-row-'+pid+' .sgc-tab-btn:last-child')); return; }
        var btn = document.querySelector('#img-row-'+pid+' .sgc-save-btn');
        btn.textContent = '⏳';
        jQuery.post(ajaxurl, {
            action:  'sgclabs_ia_save_image',
            nonce:   SGC_QE.nonce,
            pid:     pid,
            img_url: url
        }, function(res) {
            btn.textContent = '💾 Save';
            var el = document.getElementById('isaved-'+pid);
            if (res.success) {
                el.style.display='block';
                setTimeout(function(){el.style.display='none';},3000);
                if (res.data.thumb) {
                    var img = document.getElementById('thumb-'+pid);
                    img.src = res.data.thumb;
                    img.style.display = 'block';
                }
                // Update badge
                var row = document.getElementById('img-row-'+pid);
                var badge = row.querySelector('.sgc-img-badge');
                if (badge) { badge.className='sgc-img-badge sgc-has-img'; badge.textContent='✓ Image hai'; }
            } else {
                alert('Error: '+res.data);
            }
        });
    }

    // ── Utilities ──
    function sgcPagination(elId, cur, total, fn) {
        if (total <= 1) { document.getElementById(elId).innerHTML=''; return; }
        var html = 'Page: ';
        for (var i=1;i<=total;i++) {
            html += '<span class="sgc-pag-btn'+(i===cur?' act':'')+'" onclick="'+fn+'('+i+')">'+i+'</span>';
        }
        document.getElementById(elId).innerHTML = html;
    }

    function escHtml(s) {
        return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function escAttr(s) {
        return String(s||'').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }
    </script>
    <?php
}
