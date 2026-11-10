<?php
/**
 * SGC Product Studio — Merged into Product Manager Pro v3.0.0
 * Original: SGC Product Studio v1.0.2
 *
 * Key changes from standalone version:
 *  1. sgcps_verify()    → uses sgc_pm_nonce (unified nonce)
 *  2. sgcps_deepseek()  → uses SGC_PM_AI_Engine::call() (unified AI engine)
 *  3. DB tables         → managed by sgc_pm_create_tables() in main plugin
 *  4. Assets            → loaded by main plugin's admin_enqueue_scripts
 *  5. Admin menu        → rendered as 'studio' tab in PM Pro admin
 */
defined( 'ABSPATH' ) || exit;

// ════════════════════════════════════════════════════════════════════════
// HELPERS
// ════════════════════════════════════════════════════════════════════════

/**
 * Unified security check — uses sgc_pm_nonce (same as rest of PM Pro)
 */
function sgcps_verify() {
    check_ajax_referer( 'sgc_pm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
}

function sgcps_get_product_params( $pid ) {
    global $wpdb;
    $row = $wpdb->get_var( $wpdb->prepare(
        "SELECT params FROM {$wpdb->prefix}sgcps_product_params WHERE product_id = %d", $pid
    ) );
    return $row ? ( json_decode( $row, true ) ?: [] ) : [];
}

function sgcps_save_product_params( $pid, $params ) {
    global $wpdb;
    $table  = $wpdb->prefix . 'sgcps_product_params';
    $json   = wp_json_encode( $params );
    $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE product_id = %d", $pid ) );
    if ( $exists ) {
        $wpdb->update( $table, [ 'params' => $json ], [ 'product_id' => $pid ] ); // phpcs:ignore
    } else {
        $wpdb->insert( $table, [ 'product_id' => $pid, 'params' => $json ] ); // phpcs:ignore
    }
}

function sgcps_get_subcat_defaults( $subcat_id ) {
    global $wpdb;
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sgcps_defaults WHERE subcat_id = %d", $subcat_id
    ), ARRAY_A );
    if ( ! $row ) return [ 'params' => [], 'capacities' => [], 'cap_unit' => 'ml', 'subcat_name' => '' ];
    return [
        'params'      => json_decode( $row['params'], true ) ?: [],
        'capacities'  => array_filter( array_map( 'trim', explode( ',', $row['capacities'] ) ) ),
        'cap_unit'    => $row['cap_unit'],
        'subcat_name' => $row['subcat_name'],
    ];
}

function sgcps_param_val( $params, $keys ) {
    foreach ( $params as $p ) {
        $name = strtolower( is_array( $p ) ? ( $p['name'] ?? '' ) : '' );
        foreach ( $keys as $k ) {
            if ( strtolower( $k ) === $name ) {
                return is_array( $p ) ? ( $p['value'] ?? $p['v'] ?? '' ) : '';
            }
        }
    }
    return '';
}

/**
 * Smart batch size — safe for LiteSpeed / shared hosting
 */
function sgcps_batch_size() {
    $memory = (int) ini_get( 'memory_limit' );
    $is_ls  = isset( $_SERVER['SERVER_SOFTWARE'] ) && stripos( $_SERVER['SERVER_SOFTWARE'], 'LiteSpeed' ) !== false;
    if ( $is_ls && $memory >= 512 ) return 50;
    if ( $memory >= 256 ) return 15;
    return 5;
}

// ════════════════════════════════════════════════════════════════════════
// CATEGORY MANAGER
// ════════════════════════════════════════════════════════════════════════

function sgcps_get_categories() {
    $roots = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => 0, 'orderby' => 'name' ] );
    $tree  = [];
    foreach ( $roots as $t ) {
        $children = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => $t->term_id, 'orderby' => 'name' ] );
        $tree[] = [ 'id' => $t->term_id, 'name' => $t->name, 'count' => $t->count, 'children' => $children ];
    }
    return $tree;
}

add_action( 'wp_ajax_sgcps_get_subcats', function () {
    sgcps_verify();
    $parent = intval( $_POST['parent_id'] ?? 0 );
    $terms  = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => $parent, 'orderby' => 'name' ] );
    wp_send_json_success( array_map( function ( $t ) {
        return [ 'id' => $t->term_id, 'name' => $t->name, 'count' => $t->count ];
    }, $terms ) );
} );

add_action( 'wp_ajax_sgcps_load_defaults', function () {
    sgcps_verify();
    wp_send_json_success( sgcps_get_subcat_defaults( intval( $_POST['subcat_id'] ?? 0 ) ) );
} );

add_action( 'wp_ajax_sgcps_save_defaults', function () {
    sgcps_verify();
    global $wpdb;
    $subcat_id   = intval( $_POST['subcat_id'] ?? 0 );
    $subcat_name = sanitize_text_field( $_POST['subcat_name'] ?? '' );
    $params      = wp_json_encode( json_decode( stripslashes( $_POST['params'] ?? '[]' ), true ) );
    $capacities  = sanitize_text_field( $_POST['capacities'] ?? '' );
    $cap_unit    = sanitize_text_field( $_POST['cap_unit'] ?? 'ml' );

    if ( ! $subcat_id ) wp_send_json_error( 'Invalid sub-category' );

    $table  = $wpdb->prefix . 'sgcps_defaults';
    $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE subcat_id = %d", $subcat_id ) );
    if ( $exists ) {
        $wpdb->update( $table, compact( 'subcat_name', 'params', 'capacities', 'cap_unit' ), [ 'subcat_id' => $subcat_id ] ); // phpcs:ignore
    } else {
        $wpdb->insert( $table, compact( 'subcat_id', 'subcat_name', 'params', 'capacities', 'cap_unit' ) ); // phpcs:ignore
    }
    wp_send_json_success( 'Defaults saved.' );
} );

// ════════════════════════════════════════════════════════════════════════
// CONTENT GENERATOR
// ════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_sgcps_gen_content', function () {
    sgcps_verify();
    $subcat   = sanitize_text_field( $_POST['subcat_name'] ?? '' );
    $capacity = sanitize_text_field( $_POST['capacity']    ?? '' );
    $cap_unit = sanitize_text_field( $_POST['cap_unit']    ?? 'ml' );
    $sku      = sanitize_text_field( $_POST['sku']         ?? '' );
    $price    = sanitize_text_field( $_POST['price']       ?? '' );
    $params   = json_decode( stripslashes( $_POST['params'] ?? '[]' ), true );
    $use_ai   = get_option( 'sgc_pm_ai_enabled', '1' ) === '1';

    if ( ! $subcat || ! $capacity ) wp_send_json_error( 'Missing: subcat or capacity' );
    wp_send_json_success( sgcps_generate_all( $subcat, $capacity, $cap_unit, $sku, $price, $params, $use_ai ) );
} );

function sgcps_generate_all( $subcat, $cap, $unit, $sku, $price, $params, $ai = false ) {
    $name  = trim( $subcat . ' ' . $cap . $unit );
    $std   = sgcps_param_val( $params, [ 'Standard', 'standard' ] );
    $glass = sgcps_param_val( $params, [ 'Glass Type', 'glass_type' ] ) ?: 'Clear';
    $grad  = sgcps_param_val( $params, [ 'Graduation', 'graduation' ] );
    $cls   = sgcps_param_val( $params, [ 'Class', 'class' ] );
    $acc   = sgcps_param_val( $params, [ 'Accuracy', 'accuracy' ] );
    $cert  = sgcps_param_val( $params, [ 'Certificate', 'certificate' ] );
    $loc   = sgcps_param_val( $params, [ 'Location', 'location' ] ) ?: 'Ambala, India';

    $seo_title  = sgcps_gen_seo_title( $name, $std, $cls );
    $meta_title = sgcps_gen_meta_title( $name, $std );
    $meta_desc  = sgcps_gen_meta_desc( $name, $glass, $grad, $std, $loc );
    $short_desc = sgcps_gen_short_desc( $name, $sku, $price, $acc, $cert, $loc );
    $main_desc  = sgcps_gen_main_desc( $name, $cap, $unit, $params, $loc );
    $alt        = sgcps_gen_alt( $name, $std, $sku );
    $caption    = sgcps_gen_caption( $sku, $name );
    $img_desc   = sgcps_gen_img_desc( $glass, $name, $loc );
    $score      = sgcps_seo_score( $seo_title, $meta_desc, $sku, $std, $params );

    if ( $ai && class_exists( 'SGC_PM_AI_Engine' ) ) {
        $ai_main = sgcps_ai_generate_desc( $name, $params );
        if ( $ai_main ) $main_desc = $ai_main;
    }

    return compact( 'seo_title', 'meta_title', 'meta_desc', 'short_desc', 'main_desc', 'alt', 'caption', 'img_desc', 'score' );
}

function sgcps_gen_seo_title( $name, $std, $cls ) {
    $p = [ $name ];
    if ( $cls ) $p[] = $cls;
    if ( $std ) $p[] = $std;
    $p[] = 'SGC LABS® India';
    return mb_substr( implode( ' | ', $p ), 0, 70 );
}

function sgcps_gen_meta_title( $name, $std ) {
    return mb_substr( $name . ( $std ? ' ' . $std : '' ) . ' Manufacturer | SGC LABS®', 0, 70 );
}

function sgcps_gen_meta_desc( $name, $glass, $grad, $std, $loc ) {
    $d  = 'SGC LABS® ' . $name . ', ' . $glass . ' borosilicate glass';
    if ( $grad ) $d .= ' with ' . $grad . ' graduation';
    if ( $std  ) $d .= ', ' . $std . ' standard';
    $d .= '. Manufactured in ' . $loc . '. Available for B2B export worldwide.';
    return mb_substr( $d, 0, 160 );
}

function sgcps_gen_short_desc( $name, $sku, $price, $acc, $cert, $loc ) {
    $lines   = [ 'Premium quality ' . $name . ' manufactured by SGC LABS® ' . $loc . '.' ];
    $lines[] = 'Material: Borosilicate Glass 3.3.';
    if ( $acc   ) $lines[] = 'Accuracy: ' . $acc . '.';
    if ( $cert  ) $lines[] = $cert . ' available.';
    if ( $sku   ) $lines[] = 'SKU: ' . $sku . '.';
    if ( $price ) $lines[] = 'Price: ₹' . $price . ' (Ex-works Ambala).';
    $lines[] = 'Available for B2B export worldwide from Ambala, India.';
    return implode( ' ', $lines );
}

function sgcps_gen_main_desc( $name, $cap, $unit, $params, $loc ) {
    $specs = '';
    foreach ( $params as $p ) {
        $pn = is_array( $p ) ? ( $p['name']  ?? '' ) : '';
        $pv = is_array( $p ) ? ( $p['value'] ?? $p['v'] ?? '' ) : '';
        if ( $pn && $pv && strtolower( $pn ) !== 'location' ) {
            $specs .= '<li><strong>' . esc_html( $pn ) . ':</strong> ' . esc_html( $pv ) . '</li>';
        }
    }
    $d  = '<p><strong>' . esc_html( $name ) . '</strong> is a premium quality laboratory glassware product ';
    $d .= 'manufactured by <strong>SGC LABS®</strong>, ' . esc_html( $loc ) . '. ';
    $d .= 'Crafted from high-quality borosilicate glass 3.3, offering excellent chemical resistance and thermal stability.</p>';
    if ( $specs ) {
        $d .= '<h4>Technical Specifications</h4><ul>' . $specs;
        $d .= '<li><strong>Capacity:</strong> ' . esc_html( $cap . $unit ) . '</li></ul>';
    }
    $d .= '<h4>Applications</h4><p>Suitable for general laboratory use, chemical analysis, sample preparation, educational institutions, research labs, and industrial quality control.</p>';
    $d .= '<h4>Export &amp; Supply</h4><p>Available for worldwide B2B export from ' . esc_html( $loc ) . '. We supply to dealers, distributors, universities, hospitals across 50+ countries.</p>';
    return $d;
}

function sgcps_gen_alt( $name, $std, $sku ) {
    return mb_substr( trim( $name . ( $std ? ' ' . $std : '' ) . ( $sku ? ' ' . $sku : '' ) . ' SGC LABS India' ), 0, 125 );
}

function sgcps_gen_caption( $sku, $name ) {
    return trim( ( $sku ? $sku . ' — ' : '' ) . $name );
}

function sgcps_gen_img_desc( $glass, $name, $loc ) {
    return $glass . ' borosilicate ' . $name . ' manufactured by SGC LABS® ' . $loc . '. Available for B2B export worldwide.';
}

function sgcps_seo_score( $title, $desc, $sku, $std, $params ) {
    $s = 30;
    if ( $title && strlen( $title ) > 20 ) $s += 15;
    if ( $desc  && strlen( $desc )  > 50 ) $s += 15;
    if ( $sku ) $s += 10;
    if ( $std ) $s += 10;
    if ( count( $params ) >= 3 ) $s += 10;
    if ( count( $params ) >= 6 ) $s += 10;
    return min( 98, $s );
}

/**
 * AI description via unified SGC_PM_AI_Engine (DeepSeek/Claude/OpenAI/Gemini)
 * Replaces the old direct DeepSeek-only sgcps_deepseek() function
 */
function sgcps_ai_generate_desc( $name, $params ) {
    if ( ! class_exists( 'SGC_PM_AI_Engine' ) ) return '';

    $specs = '';
    foreach ( $params as $p ) {
        $pn = is_array( $p ) ? ( $p['name'] ?? '' ) : '';
        $pv = is_array( $p ) ? ( $p['value'] ?? $p['v'] ?? '' ) : '';
        if ( $pn && $pv ) $specs .= $pn . ': ' . $pv . "\n";
    }

    $system = 'You write professional HTML product descriptions for SGC LABS® lab glassware (Ambala, India). Always use <p>, <h4>, <ul> tags. Professional B2B tone.';
    $prompt = "Write a professional HTML product description (200-300 words) for:\nProduct: {$name}\nSpecs:\n{$specs}\nCompany: SGC LABS® Ambala India — lab glassware manufacturer & exporter.";

    $result = SGC_PM_AI_Engine::call( $prompt, $system, 600 );
    if ( is_wp_error( $result ) ) return '';

    $html = trim( $result );
    $html = preg_replace( '/^```html?\s*/i', '', $html );
    $html = preg_replace( '/\s*```$/', '', $html );
    return $html;
}

// ════════════════════════════════════════════════════════════════════════
// IMAGE HANDLER
// ════════════════════════════════════════════════════════════════════════

function sgcps_generate_woo_sizes( $attach_id ) {
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $file = get_attached_file( $attach_id );
    if ( ! $file || ! file_exists( $file ) ) return false;

    $metadata = wp_generate_attachment_metadata( $attach_id, $file );
    wp_update_attachment_metadata( $attach_id, $metadata );

    $woo_sizes = [
        'woocommerce_thumbnail'         => wc_get_image_size( 'thumbnail' ),
        'woocommerce_single'            => wc_get_image_size( 'single' ),
        'woocommerce_gallery_thumbnail' => wc_get_image_size( 'gallery_thumbnail' ),
    ];

    $editor = wp_get_image_editor( $file );
    if ( ! is_wp_error( $editor ) ) {
        foreach ( $woo_sizes as $size_name => $size ) {
            $width  = intval( $size['width']  ?? 300 );
            $height = intval( $size['height'] ?? 300 );
            $crop   = (bool) ( $size['crop']  ?? true );
            if ( ! isset( $metadata['sizes'][ $size_name ] ) ) {
                $editor->resize( $width, $height, $crop );
                $resized = $editor->save();
                if ( ! is_wp_error( $resized ) ) {
                    $metadata['sizes'][ $size_name ] = [
                        'file'      => basename( $resized['path'] ),
                        'width'     => $resized['width'],
                        'height'    => $resized['height'],
                        'mime-type' => $resized['mime-type'],
                    ];
                }
                $editor = wp_get_image_editor( $file );
                if ( is_wp_error( $editor ) ) break;
            }
        }
        wp_update_attachment_metadata( $attach_id, $metadata );
    }
    return $metadata;
}

function sgcps_rename_by_sku( $attach_id, $sku, $slot_num = '1' ) {
    $file = get_attached_file( $attach_id );
    if ( ! $file || ! file_exists( $file ) ) return false;
    $ext      = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
    $dir      = dirname( $file );
    $new_name = strtolower( sanitize_file_name( $sku ) ) . '-' . $slot_num . '.' . $ext;
    $new_path = $dir . '/' . $new_name;
    if ( file_exists( $new_path ) && $new_path !== $file ) {
        $new_name = strtolower( sanitize_file_name( $sku ) ) . '-' . $slot_num . '-' . time() . '.' . $ext;
        $new_path = $dir . '/' . $new_name;
    }
    if ( @rename( $file, $new_path ) ) {
        $upload_dir = wp_upload_dir();
        $rel = str_replace( $upload_dir['basedir'] . '/', '', $new_path );
        update_post_meta( $attach_id, '_wp_attached_file', $rel );
        wp_update_post( [ 'ID' => $attach_id, 'post_title' => sanitize_file_name( $sku . '-' . $slot_num ) ] );
        return $new_name;
    }
    return false;
}

add_action( 'wp_ajax_sgcps_regen_sizes', function () {
    sgcps_verify();
    $pid = intval( $_POST['pid'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'Invalid product' );
    $ids  = [];
    $feat = get_post_thumbnail_id( $pid );
    if ( $feat ) $ids[] = $feat;
    $gallery = get_post_meta( $pid, '_product_image_gallery', true );
    foreach ( array_filter( explode( ',', $gallery ) ) as $gid ) $ids[] = (int) $gid;
    if ( ! $ids ) wp_send_json_error( 'No images found' );
    $done = 0;
    foreach ( $ids as $aid ) { if ( sgcps_generate_woo_sizes( $aid ) ) $done++; }
    wp_send_json_success( [ 'msg' => $done . ' image(s) regenerated with all WooCommerce sizes!', 'done' => $done, 'total' => count( $ids ) ] );
} );

add_action( 'wp_ajax_sgcps_bulk_regen_sizes', function () {
    sgcps_verify();
    global $wpdb;
    $offset = intval( $_POST['offset'] ?? 0 );
    $batch  = 5;
    set_time_limit( 55 );
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT DISTINCT pm.meta_value as attach_id FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.meta_value
         WHERE pm.meta_key = '_thumbnail_id' AND p.post_type = 'attachment'
         ORDER BY pm.meta_value ASC LIMIT %d OFFSET %d",
        $batch, $offset
    ) );
    $total = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT meta_value) FROM {$wpdb->postmeta} WHERE meta_key='_thumbnail_id'" ); // phpcs:ignore
    $done = 0;
    foreach ( $rows as $row ) { $aid = (int) $row->attach_id; if ( $aid && sgcps_generate_woo_sizes( $aid ) ) $done++; }
    $next    = $offset + $batch;
    $is_done = $next >= $total || count( $rows ) < $batch;
    wp_send_json_success( [ 'done' => $done, 'offset' => $next, 'total' => $total, 'is_done' => $is_done, 'msg' => 'Processed ' . min( $next, $total ) . ' of ' . $total ] );
} );

add_action( 'wp_ajax_sgcps_upload_image', function () {
    sgcps_verify();
    $pid  = intval( $_POST['pid']  ?? 0 );
    $slot = sanitize_text_field( $_POST['slot'] ?? 'featured' );
    $sku  = sanitize_text_field( $_POST['sku']  ?? '' );
    $name = sanitize_text_field( $_POST['name'] ?? '' );
    $std  = sanitize_text_field( $_POST['std']  ?? '' );
    $fkey = sanitize_text_field( $_POST['fkey'] ?? 'sgcps_file' );
    if ( empty( $_FILES[ $fkey ]['tmp_name'] ) ) wp_send_json_error( 'No file received' );

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $overrides = [ 'test_form' => false ];
    $uploaded  = wp_handle_upload( $_FILES[ $fkey ], $overrides ); // phpcs:ignore
    if ( isset( $uploaded['error'] ) ) wp_send_json_error( $uploaded['error'] );

    $attach_id = wp_insert_attachment( [
        'post_mime_type' => $uploaded['type'],
        'post_title'     => sanitize_file_name( pathinfo( $uploaded['file'], PATHINFO_FILENAME ) ),
        'post_content'   => '',
        'post_status'    => 'inherit',
    ], $uploaded['file'], $pid );
    if ( is_wp_error( $attach_id ) ) wp_send_json_error( $attach_id->get_error_message() );

    sgcps_generate_woo_sizes( $attach_id );
    if ( $sku ) {
        $slot_num = ( $slot === 'featured' ) ? '1' : ( preg_replace( '/[^0-9]/', '', $slot ) ?: '2' );
        sgcps_rename_by_sku( $attach_id, $sku, $slot_num );
    }

    $prod_name = $name ?: get_the_title( $pid );
    $alt       = sgcps_gen_alt( $prod_name, $std, $sku );
    $caption   = sgcps_gen_caption( $sku, $prod_name );
    $img_desc  = sgcps_gen_img_desc( 'Borosilicate', $prod_name, 'Ambala, India' );
    update_post_meta( $attach_id, '_wp_attachment_image_alt', $alt );
    wp_update_post( [ 'ID' => $attach_id, 'post_excerpt' => $caption, 'post_content' => $img_desc ] );

    if ( $pid ) {
        if ( $slot === 'featured' ) {
            set_post_thumbnail( $pid, $attach_id );
        } else {
            $gallery = get_post_meta( $pid, '_product_image_gallery', true );
            $ids     = array_filter( explode( ',', $gallery ) );
            if ( ! in_array( (string) $attach_id, $ids ) ) { $ids[] = $attach_id; update_post_meta( $pid, '_product_image_gallery', implode( ',', $ids ) ); }
        }
    }
    wp_send_json_success( [ 'attach_id' => $attach_id, 'thumb' => wp_get_attachment_image_url( $attach_id, 'thumbnail' ), 'alt' => $alt, 'caption' => $caption, 'msg' => 'Uploaded + assigned!' ] );
} );

add_action( 'wp_ajax_sgcps_fetch_image', function () {
    sgcps_verify();
    $url  = esc_url_raw( $_POST['url']  ?? '' );
    $pid  = intval(      $_POST['pid']  ?? 0  );
    $slot = sanitize_text_field( $_POST['slot'] ?? 'featured' );
    $sku  = sanitize_text_field( $_POST['sku']  ?? '' );
    $name = sanitize_text_field( $_POST['name'] ?? '' );
    $std  = sanitize_text_field( $_POST['std']  ?? '' );
    if ( ! $url ) wp_send_json_error( 'URL required' );

    $attach_id = attachment_url_to_postid( $url );
    if ( ! $attach_id ) {
        global $wpdb;
        $fname     = basename( parse_url( $url, PHP_URL_PATH ) );
        $attach_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_wp_attached_file' AND meta_value LIKE %s", '%' . $wpdb->esc_like( $fname ) ) );
    }
    if ( ! $attach_id ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $tmp = download_url( $url, 30 );
        if ( is_wp_error( $tmp ) ) wp_send_json_error( 'Download failed: ' . $tmp->get_error_message() );
        $file_arr  = [ 'name' => basename( parse_url( $url, PHP_URL_PATH ) ), 'tmp_name' => $tmp ];
        $attach_id = media_handle_sideload( $file_arr, $pid );
        if ( is_wp_error( $attach_id ) ) { @unlink( $tmp ); wp_send_json_error( $attach_id->get_error_message() ); } // phpcs:ignore
    }

    sgcps_generate_woo_sizes( $attach_id );
    if ( $sku ) {
        $slot_num = ( $slot === 'featured' ) ? '1' : ( preg_replace( '/[^0-9]/', '', $slot ) ?: '2' );
        sgcps_rename_by_sku( $attach_id, $sku, $slot_num );
    }
    $prod_name = $name ?: get_the_title( $pid );
    $alt       = sgcps_gen_alt( $prod_name, $std, $sku );
    $caption   = sgcps_gen_caption( $sku, $prod_name );
    $img_desc  = sgcps_gen_img_desc( 'Borosilicate', $prod_name, 'Ambala, India' );
    update_post_meta( $attach_id, '_wp_attachment_image_alt', $alt );
    wp_update_post( [ 'ID' => $attach_id, 'post_excerpt' => $caption, 'post_content' => $img_desc ] );

    if ( $pid ) {
        if ( $slot === 'featured' ) {
            set_post_thumbnail( $pid, $attach_id );
        } else {
            $gallery = get_post_meta( $pid, '_product_image_gallery', true );
            $ids     = array_filter( explode( ',', $gallery ) );
            if ( ! in_array( (string) $attach_id, $ids ) ) { $ids[] = $attach_id; update_post_meta( $pid, '_product_image_gallery', implode( ',', $ids ) ); }
        }
    }
    wp_send_json_success( [ 'attach_id' => $attach_id, 'thumb' => wp_get_attachment_image_url( $attach_id, 'thumbnail' ), 'alt' => $alt, 'caption' => $caption, 'msg' => 'Image saved + assigned!' ] );
} );

add_action( 'wp_ajax_sgcps_get_product_images', function () {
    sgcps_verify();
    $pid = intval( $_POST['pid'] ?? 0 );
    if ( ! wc_get_product( $pid ) ) wp_send_json_error( 'Product not found' );
    $images  = [];
    $feat_id = get_post_thumbnail_id( $pid );
    if ( $feat_id ) $images[] = [ 'id' => $feat_id, 'slot' => 'featured', 'url' => wp_get_attachment_image_url( $feat_id, 'thumbnail' ), 'alt' => get_post_meta( $feat_id, '_wp_attachment_image_alt', true ) ];
    $gallery = get_post_meta( $pid, '_product_image_gallery', true );
    foreach ( array_filter( explode( ',', $gallery ) ) as $i => $gid ) {
        $images[] = [ 'id' => (int) $gid, 'slot' => 'gallery-' . ( $i + 2 ), 'url' => wp_get_attachment_image_url( (int) $gid, 'thumbnail' ), 'alt' => get_post_meta( (int) $gid, '_wp_attachment_image_alt', true ) ];
    }
    wp_send_json_success( $images );
} );

add_action( 'wp_ajax_sgcps_remove_image', function () {
    sgcps_verify();
    $pid       = intval( $_POST['pid'] ?? 0 );
    $attach_id = intval( $_POST['attach_id'] ?? 0 );
    $slot      = sanitize_text_field( $_POST['slot'] ?? '' );
    if ( $slot === 'featured' ) {
        delete_post_thumbnail( $pid );
    } else {
        $gallery = get_post_meta( $pid, '_product_image_gallery', true );
        $ids     = array_filter( array_diff( explode( ',', $gallery ), [ (string) $attach_id ] ) );
        update_post_meta( $pid, '_product_image_gallery', implode( ',', $ids ) );
    }
    wp_send_json_success( 'Removed' );
} );

// ════════════════════════════════════════════════════════════════════════
// PRODUCT CREATOR + EDITOR
// ════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_sgcps_create_product', function () {
    sgcps_verify();
    $name       = sanitize_text_field(     $_POST['name']       ?? '' );
    $sku        = sanitize_text_field(     $_POST['sku']        ?? '' );
    $price      = sanitize_text_field(     $_POST['price']      ?? '' );
    $stock      = intval(                  $_POST['stock']      ?? 0  );
    $subcat_id  = intval(                  $_POST['subcat_id']  ?? 0  );
    $capacity   = sanitize_text_field(     $_POST['capacity']   ?? '' );
    $cap_unit   = sanitize_text_field(     $_POST['cap_unit']   ?? 'ml' );
    $params     = json_decode( stripslashes( $_POST['params']   ?? '[]' ), true );
    $seo_title  = sanitize_text_field(     $_POST['seo_title']  ?? '' );
    $meta_title = sanitize_text_field(     $_POST['meta_title'] ?? '' );
    $meta_desc  = sanitize_textarea_field( $_POST['meta_desc']  ?? '' );
    $short_desc = wp_kses_post(            $_POST['short_desc'] ?? '' );
    $main_desc  = wp_kses_post(            $_POST['main_desc']  ?? '' );

    if ( ! $name ) wp_send_json_error( 'Product name required' );

    $product = new WC_Product_Simple();
    $product->set_name( $name );
    if ( $sku   ) { try { $product->set_sku( $sku ); } catch ( \Exception $e ) {} }
    if ( $price ) $product->set_regular_price( $price );
    if ( $stock ) { $product->set_manage_stock( true ); $product->set_stock_quantity( $stock ); $product->set_stock_status( 'instock' ); }
    $product->set_short_description( $short_desc );
    $product->set_description( $main_desc );
    $product->set_status( 'publish' );
    $product->set_catalog_visibility( 'visible' );
    $pid = $product->save();
    if ( ! $pid ) wp_send_json_error( 'Product create nahi hua' );

    if ( $subcat_id ) wp_set_object_terms( $pid, $subcat_id, 'product_cat' );
    update_post_meta( $pid, '_sgclabs_seo_title',       $seo_title );
    update_post_meta( $pid, '_sgclabs_meta_title',      $meta_title );
    update_post_meta( $pid, '_sgclabs_seo_description', $meta_desc );
    update_post_meta( $pid, '_sgcps_capacity',          $capacity . $cap_unit );
    sgcps_save_product_params( $pid, $params );

    // Sync to RankMath / Yoast
    if ( $seo_title ) {
        update_post_meta( $pid, 'rank_math_title',        $seo_title );
        update_post_meta( $pid, '_yoast_wpseo_title',     $seo_title );
    }
    if ( $meta_desc ) {
        update_post_meta( $pid, 'rank_math_description',  $meta_desc );
        update_post_meta( $pid, '_yoast_wpseo_metadesc',  $meta_desc );
    }

    SGC_PM_Logger::log( 'studio_create', 'product', $pid, 'Product Studio: created ' . $name );
    wp_send_json_success( [ 'pid' => $pid, 'edit_link' => get_edit_post_link( $pid, 'raw' ), 'msg' => 'Product created!' ] );
} );

add_action( 'wp_ajax_sgcps_search_products', function () {
    sgcps_verify();
    global $wpdb;
    $q      = sanitize_text_field( $_POST['q']      ?? '' );
    $cat_id = intval(              $_POST['cat_id'] ?? 0  );
    $page   = max( 1, intval(     $_POST['page']   ?? 1  ) );
    $per    = 20;
    $offset = ( $page - 1 ) * $per;

    $join  = "LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_sku'";
    $where = "p.post_type = 'product' AND p.post_status = 'publish'";
    if ( $q ) {
        $like   = '%' . $wpdb->esc_like( $q ) . '%';
        $where .= $wpdb->prepare( ' AND (p.post_title LIKE %s OR pm.meta_value LIKE %s)', $like, $like );
    }
    if ( $cat_id ) {
        $join .= " INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.term_id = {$cat_id}";
    }
    $total = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p {$join} WHERE {$where}" ); // phpcs:ignore
    $rows  = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT p.ID, p.post_title, pm.meta_value as sku FROM {$wpdb->posts} p {$join} WHERE {$where} ORDER BY p.post_title ASC LIMIT %d OFFSET %d", $per, $offset ) ); // phpcs:ignore
    $products = [];
    foreach ( $rows as $r ) {
        $pid = (int) $r->ID;
        $products[] = [ 'id' => $pid, 'name' => $r->post_title, 'sku' => $r->sku ?: '', 'score' => sgcps_seo_score( get_post_meta( $pid, '_sgclabs_seo_title', true ), get_post_meta( $pid, '_sgclabs_seo_description', true ), $r->sku, '', [] ), 'has_img' => (bool) get_post_thumbnail_id( $pid ), 'thumb' => get_the_post_thumbnail_url( $pid, 'thumbnail' ) ?: '' ];
    }
    wp_send_json_success( [ 'products' => $products, 'total' => $total, 'pages' => max( 1, ceil( $total / $per ) ) ] );
} );

add_action( 'wp_ajax_sgcps_load_product', function () {
    sgcps_verify();
    $pid     = intval( $_POST['pid'] ?? 0 );
    $product = wc_get_product( $pid );
    if ( ! $product ) wp_send_json_error( 'Not found' );
    $cats = wp_get_object_terms( $pid, 'product_cat', [ 'fields' => 'ids' ] );
    wp_send_json_success( [ 'id' => $pid, 'name' => $product->get_name(), 'sku' => $product->get_sku(), 'price' => $product->get_regular_price(), 'stock' => $product->get_stock_quantity(), 'short_desc' => $product->get_short_description(), 'main_desc' => $product->get_description(), 'cat_ids' => $cats, 'seo_title' => get_post_meta( $pid, '_sgclabs_seo_title', true ), 'meta_title' => get_post_meta( $pid, '_sgclabs_meta_title', true ), 'meta_desc' => get_post_meta( $pid, '_sgclabs_seo_description', true ), 'params' => sgcps_get_product_params( $pid ), 'capacity' => get_post_meta( $pid, '_sgcps_capacity', true ), 'thumb' => get_the_post_thumbnail_url( $pid, 'thumbnail' ) ?: '' ] );
} );

add_action( 'wp_ajax_sgcps_update_product', function () {
    sgcps_verify();
    $pid        = intval(                  $_POST['pid']        ?? 0  );
    $name       = sanitize_text_field(     $_POST['name']       ?? '' );
    $sku        = sanitize_text_field(     $_POST['sku']        ?? '' );
    $price      = sanitize_text_field(     $_POST['price']      ?? '' );
    $stock      = intval(                  $_POST['stock']      ?? 0  );
    $seo_title  = sanitize_text_field(     $_POST['seo_title']  ?? '' );
    $meta_title = sanitize_text_field(     $_POST['meta_title'] ?? '' );
    $meta_desc  = sanitize_textarea_field( $_POST['meta_desc']  ?? '' );
    $short_desc = wp_kses_post(            $_POST['short_desc'] ?? '' );
    $main_desc  = wp_kses_post(            $_POST['main_desc']  ?? '' );
    $params     = json_decode( stripslashes( $_POST['params']   ?? '[]' ), true );

    if ( ! $pid ) wp_send_json_error( 'Invalid product' );
    $product = wc_get_product( $pid );
    if ( ! $product ) wp_send_json_error( 'Not found' );

    if ( $name  ) $product->set_name( $name );
    if ( $sku   ) { try { $product->set_sku( $sku ); } catch ( \Exception $e ) {} }
    if ( $price ) $product->set_regular_price( $price );
    if ( $stock > 0 ) { $product->set_manage_stock( true ); $product->set_stock_quantity( $stock ); }
    if ( $short_desc ) $product->set_short_description( $short_desc );
    if ( $main_desc  ) $product->set_description( $main_desc );
    $product->save();

    update_post_meta( $pid, '_sgclabs_seo_title',       $seo_title );
    update_post_meta( $pid, '_sgclabs_meta_title',      $meta_title );
    update_post_meta( $pid, '_sgclabs_seo_description', $meta_desc );
    sgcps_save_product_params( $pid, $params );

    // Sync RankMath / Yoast
    if ( $seo_title ) { update_post_meta( $pid, 'rank_math_title', $seo_title ); update_post_meta( $pid, '_yoast_wpseo_title', $seo_title ); }
    if ( $meta_desc ) { update_post_meta( $pid, 'rank_math_description', $meta_desc ); update_post_meta( $pid, '_yoast_wpseo_metadesc', $meta_desc ); }

    SGC_PM_Logger::log( 'studio_update', 'product', $pid, 'Product Studio: updated' );
    wp_send_json_success( [ 'msg' => 'Updated!', 'pid' => $pid ] );
} );

// ── Bulk load + generate ──────────────────────────────────────────────────

add_action( 'wp_ajax_sgcps_bulk_load', function () {
    sgcps_verify();
    global $wpdb;
    $cat_id = intval( $_POST['cat_id'] ?? 0 );
    $page   = max( 1, intval( $_POST['page'] ?? 1 ) );
    $per    = sgcps_batch_size();
    $offset = ( $page - 1 ) * $per;

    $join  = "LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_sku'";
    $where = "p.post_type = 'product' AND p.post_status = 'publish'";
    if ( $cat_id ) $join .= " INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.term_id = {$cat_id}";

    $total = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p {$join} WHERE {$where}" ); // phpcs:ignore
    $rows  = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT p.ID, p.post_title, pm.meta_value as sku FROM {$wpdb->posts} p {$join} WHERE {$where} ORDER BY p.ID ASC LIMIT %d OFFSET %d", $per, $offset ) ); // phpcs:ignore

    $products = [];
    foreach ( $rows as $r ) {
        $pid = (int) $r->ID;
        $products[] = [ 'id' => $pid, 'name' => $r->post_title, 'sku' => $r->sku ?: '', 'score' => sgcps_seo_score( get_post_meta( $pid, '_sgclabs_seo_title', true ), get_post_meta( $pid, '_sgclabs_seo_description', true ), $r->sku, '', [] ) ];
    }
    wp_send_json_success( [ 'products' => $products, 'total' => $total, 'pages' => max( 1, ceil( $total / $per ) ), 'batch_size' => $per ] );
} );

add_action( 'wp_ajax_sgcps_bulk_generate', function () {
    sgcps_verify();
    $raw = $_POST['ids'] ?? '[]';
    $ids = array_map( 'intval', (array) ( is_array( $raw ) ? $raw : ( json_decode( stripslashes( (string) $raw ), true ) ?: [] ) ) );
    if ( ! $ids ) wp_send_json_error( 'No products selected' );
    set_time_limit( 55 );
    $results = [];
    $use_ai  = get_option( 'sgc_pm_ai_enabled', '1' ) === '1';

    foreach ( $ids as $pid ) {
        $product = wc_get_product( $pid );
        if ( ! $product ) { $results[] = [ 'pid' => $pid, 'status' => 'skip' ]; continue; }

        $sku     = $product->get_sku();
        $params  = sgcps_get_product_params( $pid );
        $cap     = get_post_meta( $pid, '_sgcps_capacity', true ) ?: '';
        preg_match( '/[\d.]+/', $cap, $m );
        $cap_num  = $m[0] ?? '';
        $cap_unit = preg_replace( '/[\d.\s]+/', '', $cap ) ?: 'ml';
        $cats     = wp_get_object_terms( $pid, 'product_cat', [ 'fields' => 'names' ] );
        $subcat   = $cats[0] ?? $product->get_name();

        $content = sgcps_generate_all( $subcat, $cap_num, $cap_unit, $sku, '', $params, $use_ai );

        update_post_meta( $pid, '_sgclabs_seo_title',       $content['seo_title'] );
        update_post_meta( $pid, '_sgclabs_meta_title',      $content['meta_title'] );
        update_post_meta( $pid, '_sgclabs_seo_description', $content['meta_desc'] );
        // Sync RankMath + Yoast
        update_post_meta( $pid, 'rank_math_title',          $content['seo_title'] );
        update_post_meta( $pid, 'rank_math_description',    $content['meta_desc'] );
        update_post_meta( $pid, '_yoast_wpseo_title',       $content['seo_title'] );
        update_post_meta( $pid, '_yoast_wpseo_metadesc',    $content['meta_desc'] );

        $product->set_short_description( $content['short_desc'] );
        $product->set_description( $content['main_desc'] );
        $product->save();

        $results[] = [ 'pid' => $pid, 'name' => $product->get_name(), 'score' => $content['score'], 'status' => 'done' ];
    }
    wp_send_json_success( $results );
} );
