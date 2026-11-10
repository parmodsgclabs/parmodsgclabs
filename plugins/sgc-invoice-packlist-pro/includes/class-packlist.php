<?php
defined( 'ABSPATH' ) || exit;

class SGC_IIP_Packlist {

    public static function register_ajax() {
        $map = [
            'sgc_iip_pl_create'       => 'ajax_create',
            'sgc_iip_pl_save'         => 'ajax_save',
            'sgc_iip_pl_get'          => 'ajax_get',
            'sgc_iip_pl_generate_pdf' => 'ajax_generate_pdf',
            'sgc_iip_pl_delete'       => 'ajax_delete',
            'sgc_iip_pl_calc_cbm'     => 'ajax_calc_cbm',
            'sgc_iip_pl_from_pi'      => 'ajax_from_pi',
        ];
        foreach ( $map as $action => $method ) {
            add_action( 'wp_ajax_' . $action, [ __CLASS__, $method ] );
        }
    }

    private static function verify() {
        check_ajax_referer( 'sgc_iip_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );
    }

    // ── Packaging presets (common lab glassware packaging) ────────────────
    const PACKAGING_PRESETS = [
        'beaker_500'    => [ 'pcs_per_carton' => 24, 'net_wt_each' => 0.350, 'carton_wt' => 2.0,  'dims' => [60,40,30] ],
        'beaker_1000'   => [ 'pcs_per_carton' => 12, 'net_wt_each' => 0.550, 'carton_wt' => 2.5,  'dims' => [60,40,30] ],
        'flask_250'     => [ 'pcs_per_carton' => 24, 'net_wt_each' => 0.200, 'carton_wt' => 1.5,  'dims' => [50,40,30] ],
        'flask_1000'    => [ 'pcs_per_carton' => 12, 'net_wt_each' => 0.500, 'carton_wt' => 2.0,  'dims' => [60,50,40] ],
        'burette_50'    => [ 'pcs_per_carton' => 24, 'net_wt_each' => 0.180, 'carton_wt' => 1.5,  'dims' => [70,30,20] ],
        'test_tube'     => [ 'pcs_per_carton' => 144,'net_wt_each' => 0.020, 'carton_wt' => 1.5,  'dims' => [40,30,20] ],
        'bottle_1000'   => [ 'pcs_per_carton' => 12, 'net_wt_each' => 0.600, 'carton_wt' => 2.5,  'dims' => [50,40,30] ],
        'default'       => [ 'pcs_per_carton' => 24, 'net_wt_each' => 0.300, 'carton_wt' => 2.0,  'dims' => [60,40,30] ],
    ];

    // ── AJAX: Create Packlist from PI ─────────────────────────────────────
    public static function ajax_from_pi() {
        self::verify();
        global $wpdb;

        $pi_id = (int) ( $_POST['pi_id'] ?? 0 );
        $pi    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . SGC_IIP_DB::t('proformas') . " WHERE id=%d", $pi_id ), ARRAY_A ); // phpcs:ignore
        if ( ! $pi ) { wp_send_json_error( 'PI not found.' ); return; }

        $line_items = json_decode( $pi['line_items'] ?? '[]', true );
        $boxes      = [];
        $box_num    = 0;

        foreach ( (array) $line_items as $item ) {
            $product_name = $item['product_name'] ?? '';
            $qty          = (int) ( $item['qty'] ?? 0 );
            if ( ! $qty ) continue;

            // Auto-detect packaging preset
            $preset_key = 'default';
            $name_lower = strtolower( $product_name );
            foreach ( self::PACKAGING_PRESETS as $key => $p ) {
                if ( strpos( $key, '_' ) !== false ) {
                    $parts = explode( '_', $key );
                    if ( strpos( $name_lower, $parts[0] ) !== false ) {
                        $preset_key = $key; break;
                    }
                }
            }
            $preset         = self::PACKAGING_PRESETS[ $preset_key ];
            $pcs_per_carton = $preset['pcs_per_carton'];
            $num_cartons    = (int) ceil( $qty / $pcs_per_carton );
            $net_wt_per_carton   = round( min( $qty, $pcs_per_carton ) * $preset['net_wt_each'], 3 );
            $gross_wt_per_carton = round( $net_wt_per_carton + $preset['carton_wt'], 3 );
            $cbm_per_carton = round( ( $preset['dims'][0] * $preset['dims'][1] * $preset['dims'][2] ) / 1000000, 4 );

            for ( $i = 0; $i < $num_cartons; $i++ ) {
                $box_num++;
                $pcs_this = ( $i === $num_cartons - 1 && $qty % $pcs_per_carton !== 0 )
                    ? $qty % $pcs_per_carton
                    : $pcs_per_carton;
                $boxes[] = [
                    'box_no'    => $box_num,
                    'product'   => $product_name,
                    'sku'       => $item['sku'] ?? '',
                    'qty'       => $pcs_this,
                    'net_wt'    => round( $pcs_this * $preset['net_wt_each'], 3 ),
                    'gross_wt'  => round( $pcs_this * $preset['net_wt_each'] + $preset['carton_wt'], 3 ),
                    'length_cm' => $preset['dims'][0],
                    'width_cm'  => $preset['dims'][1],
                    'height_cm' => $preset['dims'][2],
                    'cbm'       => round( $cbm_per_carton, 4 ),
                ];
            }
        }

        $totals = self::calc_totals( $boxes );
        $pl_num = 'PL-' . $pi['pi_number'];

        $data = [
            'inquiry_id'     => $pi['inquiry_id'],
            'pi_id'          => $pi_id,
            'pl_number'      => $pl_num,
            'boxes'          => wp_json_encode( $boxes ),
            'total_boxes'    => $totals['boxes'],
            'total_net_wt'   => $totals['net_wt'],
            'total_gross_wt' => $totals['gross_wt'],
            'total_cbm'      => $totals['cbm'],
            'sea_est_usd'    => self::estimate_sea( $totals['gross_wt'], $totals['cbm'] ),
            'air_est_usd'    => self::estimate_air( $totals['gross_wt'] ),
        ];

        $wpdb->insert( SGC_IIP_DB::t('packlists'), $data ); // phpcs:ignore
        $pl_id = $wpdb->insert_id;

        SGC_IIP_DB::log( $pi['inquiry_id'], 'packlist_created', 'Packing list ' . $pl_num . ' created from PI', 'admin' );

        wp_send_json_success( array_merge( $data, [
            'id'    => $pl_id,
            'boxes' => $boxes,
        ] ) );
    }

    // ── AJAX: Create manual packlist ──────────────────────────────────────
    public static function ajax_create() {
        self::verify();
        global $wpdb;

        $inq_id = (int) ( $_POST['inquiry_id'] ?? 0 );
        $pl_num = 'PL-' . date('Y') . '-' . str_pad( mt_rand(1,9999), 4, '0', STR_PAD_LEFT );

        $data = [
            'inquiry_id'     => $inq_id,
            'pl_number'      => $pl_num,
            'boxes'          => wp_json_encode( [] ),
            'total_boxes'    => 0,
            'total_net_wt'   => 0,
            'total_gross_wt' => 0,
            'total_cbm'      => 0,
        ];
        $wpdb->insert( SGC_IIP_DB::t('packlists'), $data ); // phpcs:ignore
        wp_send_json_success( array_merge( $data, [ 'id' => $wpdb->insert_id, 'boxes' => [] ] ) );
    }

    // ── AJAX: Save packlist ───────────────────────────────────────────────
    public static function ajax_save() {
        self::verify();
        global $wpdb;

        $pl_id  = (int) ( $_POST['pl_id'] ?? 0 );
        $boxes  = wp_unslash( $_POST['boxes'] ?? '[]' );
        if ( is_string( $boxes ) ) $boxes = json_decode( $boxes, true );
        if ( ! is_array( $boxes ) ) $boxes = [];

        // Recalc CBM per box
        foreach ( $boxes as &$box ) {
            $l = floatval( $box['length_cm'] ?? 60 );
            $w = floatval( $box['width_cm']  ?? 40 );
            $h = floatval( $box['height_cm'] ?? 30 );
            $box['cbm'] = round( ( $l * $w * $h ) / 1000000, 4 );
        }
        unset( $box );

        $totals = self::calc_totals( $boxes );
        $update = [
            'boxes'          => wp_json_encode( $boxes ),
            'total_boxes'    => $totals['boxes'],
            'total_net_wt'   => $totals['net_wt'],
            'total_gross_wt' => $totals['gross_wt'],
            'total_cbm'      => $totals['cbm'],
            'sea_est_usd'    => self::estimate_sea( $totals['gross_wt'], $totals['cbm'] ),
            'air_est_usd'    => self::estimate_air( $totals['gross_wt'] ),
        ];
        $wpdb->update( SGC_IIP_DB::t('packlists'), $update, [ 'id' => $pl_id ] ); // phpcs:ignore
        wp_send_json_success( array_merge( $update, [ 'boxes' => $boxes ] ) );
    }

    // ── AJAX: Get packlist ────────────────────────────────────────────────
    public static function ajax_get() {
        self::verify();
        global $wpdb;
        $id = (int) ( $_POST['pl_id'] ?? 0 );
        $pl = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . SGC_IIP_DB::t('packlists') . " WHERE id=%d", $id ), ARRAY_A ); // phpcs:ignore
        if ( ! $pl ) { wp_send_json_error( 'Not found.' ); return; }
        $pl['boxes'] = json_decode( $pl['boxes'] ?? '[]', true );
        wp_send_json_success( $pl );
    }

    // ── AJAX: Generate PDF ────────────────────────────────────────────────
    public static function ajax_generate_pdf() {
        self::verify();
        global $wpdb;
        $pl_id = (int) ( $_POST['pl_id'] ?? 0 );
        $pl    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . SGC_IIP_DB::t('packlists') . " WHERE id=%d", $pl_id ), ARRAY_A ); // phpcs:ignore
        if ( ! $pl ) { wp_send_json_error( 'Not found.' ); return; }
        $pl['boxes'] = json_decode( $pl['boxes'] ?? '[]', true );

        $pdf_path = SGC_IIP_PDF::generate_packlist( $pl );
        if ( is_wp_error( $pdf_path ) ) { wp_send_json_error( $pdf_path->get_error_message() ); return; }

        $wpdb->update( SGC_IIP_DB::t('packlists'), [ 'pdf_path' => $pdf_path ], [ 'id' => $pl_id ] ); // phpcs:ignore
        wp_send_json_success( [
            'pdf_path' => $pdf_path,
            'pdf_url'  => str_replace( ABSPATH, site_url('/'), $pdf_path ),
        ] );
    }

    // ── AJAX: Delete ──────────────────────────────────────────────────────
    // ── AJAX: List ALL Packlists (global) — v1.2.1 ───────────────────────
    public static function ajax_list_all() {
        self::verify();
        global $wpdb;
        $search  = sanitize_text_field( $_POST['search']   ?? '' );
        $per_page = min( 50, max( 10, intval( $_POST['per_page'] ?? 20 ) ) );
        $page    = max( 1, intval( $_POST['page'] ?? 1 ) );
        $offset  = ( $page - 1 ) * $per_page;

        $t     = SGC_IIP_DB::t('packlists');
        $pt    = SGC_IIP_DB::t('proformas');
        $where = '1=1'; $vals = [];

        if ( $search ) {
            $like   = '%' . $wpdb->esc_like( $search ) . '%';
            $where .= " AND (pl.pl_number LIKE %s OR pr.buyer_name LIKE %s OR pr.buyer_company LIKE %s)";
            $vals   = [ $like, $like, $like ];
        }

        $total = (int) $wpdb->get_var( $vals
            ? $wpdb->prepare( "SELECT COUNT(*) FROM `$t` pl LEFT JOIN `$pt` pr ON pl.pi_id=pr.id WHERE $where", ...$vals )
            : "SELECT COUNT(*) FROM `$t` pl LEFT JOIN `$pt` pr ON pl.pi_id=pr.id WHERE $where"
        ); // phpcs:ignore

        $q   = "SELECT pl.id, pl.pl_number, pl.pi_id, pl.pdf_path, pl.created_at,
                       pr.pi_number, pr.buyer_name, pr.buyer_email, pr.buyer_company, pr.buyer_country,
                       pr.grand_total, pr.currency
                FROM `$t` pl LEFT JOIN `$pt` pr ON pl.pi_id=pr.id
                WHERE $where ORDER BY pl.created_at DESC LIMIT %d OFFSET %d";
        $vp  = array_merge( $vals, [ $per_page, $offset ] );
        $rows = $wpdb->get_results( $wpdb->prepare( $q, ...$vp ), ARRAY_A ); // phpcs:ignore

        foreach ( $rows as &$r ) $r['has_pdf'] = ! empty( $r['pdf_path'] ) && file_exists( $r['pdf_path'] );
        unset( $r );

        wp_send_json_success( [ 'rows' => $rows, 'total' => $total, 'pages' => ceil( $total / $per_page ), 'page' => $page ] );
    }

    // ── GET: Export all packlists as CSV — v1.2.1 ────────────────────────
    public static function ajax_export_csv() {
        if ( ! current_user_can('manage_options') || ! wp_verify_nonce( $_GET['nonce'] ?? '', 'sgc_iip_nonce' ) ) wp_die('Unauthorized');
        global $wpdb;
        $t  = SGC_IIP_DB::t('packlists');
        $pt = SGC_IIP_DB::t('proformas');
        $rows = $wpdb->get_results( "SELECT pl.*, pr.buyer_name, pr.buyer_company, pr.buyer_country, pr.pi_number FROM `$t` pl LEFT JOIN `$pt` pr ON pl.pi_id=pr.id ORDER BY pl.created_at DESC", ARRAY_A ); // phpcs:ignore

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="sgc-packlists-' . date('Y-m-d') . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output','w');
        fputcsv( $out, ['PL Number','PI Number','Buyer','Company','Country','Created At'] );
        foreach ( $rows as $r ) fputcsv( $out, [ $r['pl_number'], $r['pi_number'], $r['buyer_name'], $r['buyer_company'], $r['buyer_country'], $r['created_at'] ] );
        fclose( $out );
        exit;
    }

    public static function ajax_delete() {
        self::verify();
        global $wpdb;
        $id = (int) ( $_POST['pl_id'] ?? 0 );
        $pl = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . SGC_IIP_DB::t('packlists') . " WHERE id=%d", $id ), ARRAY_A ); // phpcs:ignore
        if ( $pl && $pl['pdf_path'] && file_exists( $pl['pdf_path'] ) ) @unlink( $pl['pdf_path'] );
        $wpdb->delete( SGC_IIP_DB::t('packlists'), [ 'id' => $id ] ); // phpcs:ignore
        wp_send_json_success();
    }

    // ── AJAX: Real-time CBM calc ──────────────────────────────────────────
    public static function ajax_calc_cbm() {
        self::verify();
        $boxes = (array) json_decode( wp_unslash( $_POST['boxes'] ?? '[]' ), true );
        $totals = self::calc_totals( $boxes );
        $totals['sea_est_usd'] = self::estimate_sea( $totals['gross_wt'], $totals['cbm'] );
        $totals['air_est_usd'] = self::estimate_air( $totals['gross_wt'] );
        wp_send_json_success( $totals );
    }

    // ── Helpers ───────────────────────────────────────────────────────────
    private static function calc_totals( array $boxes ) {
        $total_net  = 0;
        $total_gross= 0;
        $total_cbm  = 0;
        foreach ( $boxes as $box ) {
            $total_net   += floatval( $box['net_wt']   ?? 0 );
            $total_gross += floatval( $box['gross_wt'] ?? 0 );
            $l = floatval( $box['length_cm'] ?? 0 );
            $w = floatval( $box['width_cm']  ?? 0 );
            $h = floatval( $box['height_cm'] ?? 0 );
            $total_cbm  += round( ( $l * $w * $h ) / 1000000, 4 );
        }
        return [
            'boxes'    => count( $boxes ),
            'net_wt'   => round( $total_net, 3 ),
            'gross_wt' => round( $total_gross, 3 ),
            'cbm'      => round( $total_cbm, 4 ),
        ];
    }

    // Rough sea freight estimate (USD) — indicative only
    private static function estimate_sea( float $gross_kg, float $cbm ) {
        // Chargeable weight = max(actual, volumetric@1:1000 ratio)
        $vol_weight = $cbm * 1000;
        $chargeable = max( $gross_kg, $vol_weight );
        // Approx $35/CBM or $0.035/kg — India to generic port
        return round( max( $chargeable * 0.035, $cbm * 35 ), 2 );
    }

    // Rough air freight estimate (USD)
    private static function estimate_air( float $gross_kg ) {
        // Approx $4.5/kg from India
        return round( $gross_kg * 4.5, 2 );
    }
}

SGC_IIP_Packlist::register_ajax();
