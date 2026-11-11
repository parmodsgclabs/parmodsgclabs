<?php
defined( 'ABSPATH' ) || exit;

class SGC_IIP_Proforma {

    /**
     * Smart payment terms based on order value
     * < $500  → 100% Advance
     * >= $500 → 50% advance, 50% before delivery
     */
    public static function get_payment_terms( float $amount_usd = 0 ): string {
        $threshold = floatval( get_option('sgc_iip_payment_threshold_usd', 500) );
        if ( $amount_usd > 0 && $amount_usd < $threshold ) {
            return '100% Advance payment required (order value below $' . number_format($threshold,0) . ')';
        } elseif ( $amount_usd >= $threshold ) {
            return '50% advance with order, 50% before delivery';
        }
        // Default if no amount known yet
        return get_option('sgc_iip_default_payment', '50% advance with order, 50% before delivery');
    }

    public static function register_ajax() {
        $map = [
            'sgc_iip_pi_create'        => 'ajax_create',
            'sgc_iip_pi_save'          => 'ajax_save',
            'sgc_iip_pi_get'           => 'ajax_get',
            'sgc_iip_pi_list'          => 'ajax_list',
            'sgc_iip_pi_delete'        => 'ajax_delete',
            'sgc_iip_pi_generate_pdf'  => 'ajax_generate_pdf',
            'sgc_iip_pi_preview_html'  => 'ajax_preview_html',
            'sgc_iip_pi_revise'        => 'ajax_revise',
            'sgc_iip_get_hs_codes'     => 'ajax_get_hs_codes',
        ];
        foreach ( $map as $action => $method ) {
            add_action( 'wp_ajax_' . $action, [ __CLASS__, $method ] );
        }
    }

    private static function verify() {
        check_ajax_referer( 'sgc_iip_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );
    }

    // ── AJAX: Create PI ───────────────────────────────────────────────────
    public static function ajax_create() {
        self::verify();
        global $wpdb;

        $inquiry_id = (int) ( $_POST['inquiry_id'] ?? 0 );
        $pi_type    = sanitize_key( $_POST['pi_type'] ?? 'standard' );
        $template   = sanitize_key( $_POST['template'] ?? get_option( 'sgc_iip_default_template', 'navy' ) );

        $inq = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . SGC_IIP_DB::t('inquiries') . " WHERE id=%d", $inquiry_id ), ARRAY_A ); // phpcs:ignore
        if ( ! $inq ) { wp_send_json_error( 'Inquiry not found.' ); return; }

        $pi_number   = SGC_IIP_DB::generate_pi_number( $pi_type );
        $valid_until = date( 'Y-m-d', strtotime( '+' . (int) get_option( 'sgc_iip_pi_validity', 30 ) . ' days' ) );
        $token       = wp_generate_password( 32, false );
        $currency    = $inq['currency'] ?? 'USD';
        $usd_rate    = floatval( get_option( 'sgc_iip_usd_rate', 83.50 ) );

        // Pre-fill from AI draft if available
        $ai_draft  = $inq['ai_draft'] ? json_decode( $inq['ai_draft'], true ) : null;
        $line_items = $ai_draft['line_items'] ?? [
            [
                'product_name'       => '',
                'sku'                => '',
                'hs_code'            => '7017.20',
                'qty'                => 0,
                'unit_price'         => 0.00,
                'packaging_per_unit' => 0.10,
                'oem_charge'         => 0.00,
                'total'              => 0.00,
            ]
        ];

        $data = [
            'inquiry_id'     => $inquiry_id,
            'pi_number'      => $pi_number,
            'pi_type'        => $pi_type,
            'revision'       => 1,
            'template'       => $template,
            'buyer_name'     => $inq['name'],
            'buyer_email'    => $inq['email'],
            'buyer_company'  => $inq['company'],
            'buyer_country'  => $inq['country'],
            'currency'       => $currency,
            'inr_rate'       => $usd_rate,
            'line_items'     => wp_json_encode( $line_items ),
            'subtotal'       => floatval( $ai_draft['subtotal'] ?? 0 ),
            'packaging_total'=> floatval( $ai_draft['packaging_total'] ?? 0 ),
            'oem_charge'     => floatval( $ai_draft['oem_total'] ?? 0 ),
            'grand_total'    => floatval( $ai_draft['grand_total'] ?? 0 ),
            'grand_total_inr'=> 0,
            'incoterm'       => $inq['incoterm'] ?: get_option( 'sgc_iip_default_incoterm', 'FOB' ),
            'port'           => $inq['port'] ?: get_option( 'sgc_iip_default_port', 'New Delhi, India' ),
            'payment_terms'  => $ai_draft['payment_terms'] ?? SGC_IIP_Proforma::get_payment_terms( (float)($ai_draft['grand_total'] ?? 0) ),
            'delivery_days'  => $ai_draft['delivery_days'] ?? get_option( 'sgc_iip_default_delivery', '15-20 working days' ),
            'validity_days'  => (int) get_option( 'sgc_iip_pi_validity', 30 ),
            'valid_until'    => $valid_until,
            'notes'          => $ai_draft['notes'] ?? '',
            'status'         => 'draft',
            'accept_token'   => $token,
        ];

        // Calculate INR total
        $data['grand_total_inr'] = round( $data['grand_total'] * $usd_rate, 2 );

        $wpdb->insert( SGC_IIP_DB::t('proformas'), $data ); // phpcs:ignore
        $pi_id = $wpdb->insert_id;

        // Update inquiry
        $wpdb->update( SGC_IIP_DB::t('inquiries'), [ 'pi_number' => $pi_number ], [ 'id' => $inquiry_id ] ); // phpcs:ignore
        SGC_IIP_DB::log( $inquiry_id, 'pi_created', 'Proforma Invoice ' . $pi_number . ' created', 'admin' );

        wp_send_json_success( array_merge( $data, [ 'id' => $pi_id, 'line_items' => $line_items ] ) );
    }

    // ── AJAX: Save / Update PI ────────────────────────────────────────────
    public static function ajax_save() {
        self::verify();
        global $wpdb;

        $pi_id      = (int) ( $_POST['pi_id'] ?? 0 );
        $line_items = wp_unslash( $_POST['line_items'] ?? '[]' );
        if ( is_string( $line_items ) ) $line_items = json_decode( $line_items, true );

        // v1.3.2: Clean calculation — price × qty per line, packaging/freight flat
        $subtotal   = 0;
        foreach ( (array) $line_items as &$item ) {
            $qty           = floatval( $item['qty']        ?? 0 );
            $unit          = floatval( $item['unit_price'] ?? 0 );
            $item['total'] = round( $qty * $unit, 2 ); // price × qty only
            $subtotal     += $item['total'];
        }
        unset( $item );

        // Flat charges (from form fields — not per-unit)
        $pack_total  = floatval( $_POST['packaging_total'] ?? 0 );
        $oem_total   = floatval( $_POST['oem_charge']      ?? 0 );
        $discount    = floatval( $_POST['discount']        ?? 0 );
        $tax         = floatval( $_POST['tax_amount']      ?? 0 );
        $gst_percent = floatval( $_POST['gst_percent']     ?? 0 );
        $freight     = floatval( $_POST['freight_charge']  ?? 0 );

        $currency    = sanitize_text_field( wp_unslash( $_POST['currency'] ?? 'USD' ) );
        $is_domestic = ( $currency === 'INR' );

        // Formula: subtotal + packaging + OEM − discount
        $taxable     = $subtotal + $pack_total + $oem_total - $discount;
        $buyer_state  = sanitize_text_field( wp_unslash( $_POST['buyer_state'] ?? '' ) );
        $gst_data     = SGC_IIP_GST::calculate( $taxable, $gst_percent, $buyer_state, ! $is_domestic );
        $gst_amount   = $is_domestic ? $gst_data['gst_amount'] : 0;
        $grand_total  = $taxable + $gst_amount + $freight;

        $pi       = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . SGC_IIP_DB::t('proformas') . " WHERE id=%d", $pi_id ), ARRAY_A ); // phpcs:ignore
        if ( ! $pi ) { wp_send_json_error( 'Proforma Invoice not found.' ); return; } // v1.9.3: guard — was "array offset on null"
        $usd_rate = floatval( $pi['inr_rate'] ?? get_option( 'sgc_iip_usd_rate', 83.50 ) );
        // Domestic = INR (no conversion), Export = USD × rate for INR equivalent
        $grand_total_inr = $is_domestic ? round( $grand_total, 2 ) : round( $grand_total * $usd_rate, 2 );

        $update = [
            'template'       => sanitize_key( $_POST['template'] ?? 'navy' ),
            'line_items'     => wp_json_encode( $line_items ),
            'subtotal'       => round( $subtotal, 2 ),
            'packaging_total'=> round( $pack_total, 2 ),
            'oem_charge'     => round( $oem_total, 2 ),
            'tax_amount'     => round( $tax, 2 ),
            'gst_percent'    => round( $gst_percent, 2 ),
            'gst_amount'     => round( $gst_amount, 2 ),
            // v1.8.5: GST split
            'gst_type'       => $is_domestic ? $gst_data['gst_type'] : 'igst',
            'cgst_amount'    => $is_domestic ? $gst_data['cgst_amount'] : 0.00,
            'sgst_amount'    => $is_domestic ? $gst_data['sgst_amount'] : 0.00,
            'igst_amount'    => $is_domestic ? $gst_data['igst_amount'] : 0.00,
            'company_state'  => SGC_IIP_GST::company_state(),
            'buyer_state'    => $is_domestic ? strtoupper( sanitize_text_field( $buyer_state ) ) : null,
            'freight_charge' => round( $freight, 2 ),
            'discount'       => round( $discount, 2 ),
            'grand_total'    => round( $grand_total, 2 ),
            'grand_total_inr'=> $grand_total_inr,
            'currency'       => $currency,
            'incoterm'       => sanitize_text_field( wp_unslash( $_POST['incoterm']       ?? 'FOB' ) ),
            'port'           => sanitize_text_field( wp_unslash( $_POST['port']           ?? '' ) ),
            'payment_terms'  => sanitize_text_field( wp_unslash( $_POST['payment_terms']  ?? '' ) ),
            'delivery_days'  => sanitize_text_field( wp_unslash( $_POST['delivery_days']  ?? '' ) ),
            'valid_until'    => sanitize_text_field( wp_unslash( $_POST['valid_until']    ?? '' ) ),
            'notes'          => sanitize_textarea_field( wp_unslash( $_POST['notes']         ?? '' ) ),
            'buyer_name'     => sanitize_text_field(     wp_unslash( $_POST['buyer_name']     ?? '' ) ),
            'buyer_email'    => sanitize_email(           wp_unslash( $_POST['buyer_email']    ?? '' ) ),
            'buyer_phone'    => sanitize_text_field(     wp_unslash( $_POST['buyer_phone']    ?? '' ) ),
            'buyer_company'  => sanitize_text_field(     wp_unslash( $_POST['buyer_company']  ?? '' ) ),
            'buyer_country'  => sanitize_text_field(     wp_unslash( $_POST['buyer_country']  ?? '' ) ),
            'buyer_address'  => sanitize_textarea_field( wp_unslash( $_POST['buyer_address']  ?? '' ) ),
            'bill_to_name'   => sanitize_text_field(     wp_unslash( $_POST['bill_to_name']   ?? '' ) ),
            'bill_to_company'=> sanitize_text_field(     wp_unslash( $_POST['bill_to_company']?? '' ) ),
            'bill_to_address'=> sanitize_textarea_field( wp_unslash( $_POST['bill_to_address']?? '' ) ),
            'ship_to_same'   => (int) ( $_POST['ship_to_same']    ?? 1 ),
            'ship_to_name'   => sanitize_text_field(     wp_unslash( $_POST['ship_to_name']   ?? '' ) ),
            'ship_to_company'=> sanitize_text_field(     wp_unslash( $_POST['ship_to_company']?? '' ) ),
            'ship_to_address'=> sanitize_textarea_field( wp_unslash( $_POST['ship_to_address']?? '' ) ),
            'payment_method' => sanitize_key(             wp_unslash( $_POST['payment_method'] ?? 'bank' ) ),
            'pi_upi_id'      => sanitize_text_field(      wp_unslash( $_POST['pi_upi_id']      ?? '' ) ),
            'pi_upi_name'    => sanitize_text_field(      wp_unslash( $_POST['pi_upi_name']    ?? '' ) ),
            'pi_upi_qr_url'  => esc_url_raw(              wp_unslash( $_POST['pi_upi_qr_url']  ?? '' ) ),
        ];

        $wpdb->update( SGC_IIP_DB::t('proformas'), $update, [ 'id' => $pi_id ] ); // phpcs:ignore

        wp_send_json_success( array_merge( $update, [
            'id'          => $pi_id,
            'pi_number'   => $pi['pi_number'],
            'grand_total' => round( $grand_total, 2 ),
            'line_items'  => $line_items,
        ] ) );
    }

    // ── AJAX: Get single PI ───────────────────────────────────────────────
    public static function ajax_get() {
        self::verify();
        global $wpdb;
        $pi_id = (int) ( $_POST['pi_id'] ?? 0 );
        $pi    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . SGC_IIP_DB::t('proformas') . " WHERE id=%d", $pi_id ), ARRAY_A ); // phpcs:ignore
        if ( ! $pi ) { wp_send_json_error( 'Not found.' ); return; }
        $pi['line_items'] = json_decode( $pi['line_items'] ?? '[]', true );
        wp_send_json_success( $pi );
    }

    // ── AJAX: PI list for inquiry ─────────────────────────────────────────
    public static function ajax_list() {
        self::verify();
        global $wpdb;
        $inq_id = (int) ( $_POST['inquiry_id'] ?? 0 );
        $list   = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
            "SELECT * FROM " . SGC_IIP_DB::t('proformas') . " WHERE inquiry_id=%d ORDER BY id DESC",
            $inq_id
        ), ARRAY_A );
        foreach ( $list as &$pi ) $pi['line_items'] = json_decode( $pi['line_items'] ?? '[]', true );
        unset( $pi );
        wp_send_json_success( $list );
    }

    // ── AJAX: List ALL PIs (global, paginated, searchable) — v1.2.1 ──────
    public static function ajax_list_all() {
        self::verify();
        global $wpdb;
        $search     = sanitize_text_field( $_POST['search']     ?? '' );
        $status     = sanitize_key(        $_POST['status']     ?? '' );
        $country    = sanitize_text_field( $_POST['country']    ?? '' );
        $inquiry_id = intval(              $_POST['inquiry_id'] ?? 0  );
        $per_page   = min( 50, max( 10, intval( $_POST['per_page'] ?? 20 ) ) );
        $page       = max( 1, intval( $_POST['page'] ?? 1 ) );
        $offset     = ( $page - 1 ) * $per_page;

        $where = '1=1';
        $vals  = [];

        if ( $search ) {
            $like   = '%' . $wpdb->esc_like( $search ) . '%';
            $where .= " AND (buyer_name LIKE %s OR buyer_email LIKE %s OR buyer_company LIKE %s OR pi_number LIKE %s)";
            $vals   = array_merge( $vals, [ $like, $like, $like, $like ] );
        }
        if ( $status )      { $where .= ' AND status=%s';        $vals[] = $status; }
        if ( $country )     { $where .= ' AND buyer_country=%s'; $vals[] = $country; }
        if ( $inquiry_id )  { $where .= ' AND inquiry_id=%d';    $vals[] = $inquiry_id; }

        $t     = SGC_IIP_DB::t('proformas');
        $total = (int) $wpdb->get_var( $vals ? $wpdb->prepare( "SELECT COUNT(*) FROM `$t` WHERE $where", ...$vals ) : "SELECT COUNT(*) FROM `$t` WHERE $where" ); // phpcs:ignore

        $query = "SELECT id, inquiry_id, pi_number, pi_type, revision, buyer_name, buyer_email, buyer_company, buyer_country,
                         currency, grand_total, grand_total_inr, status, sent_at, pdf_path, incoterm, payment_terms, created_at
                  FROM `$t` WHERE $where ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $vals_p = array_merge( $vals, [ $per_page, $offset ] );
        $rows   = $wpdb->get_results( $wpdb->prepare( $query, ...$vals_p ), ARRAY_A ); // phpcs:ignore

        // Attach payment total
        $pay_t = SGC_IIP_DB::t('payments');
        foreach ( $rows as &$r ) {
            $inq_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT inquiry_id FROM `$t` WHERE id=%d", $r['id'] ) ); // phpcs:ignore
            $r['total_paid'] = $inq_id ? (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM `$pay_t` WHERE inquiry_id=%d AND payment_type!='refund'", $inq_id ) ) : 0; // phpcs:ignore
            $r['balance']    = round( (float)$r['grand_total'] - $r['total_paid'], 2 );
            $r['has_pdf']    = ! empty( $r['pdf_path'] ) && file_exists( $r['pdf_path'] );
        }
        unset( $r );

        wp_send_json_success( [ 'rows' => $rows, 'total' => $total, 'pages' => ceil( $total / $per_page ), 'page' => $page ] );
    }

    // ── AJAX: Export all PIs as CSV — v1.2.1 ─────────────────────────────
    public static function ajax_export_csv() {
        if ( ! current_user_can('manage_options') || ! wp_verify_nonce( $_GET['nonce'] ?? '', 'sgc_iip_nonce' ) ) wp_die('Unauthorized');
        global $wpdb;
        $t    = SGC_IIP_DB::t('proformas');
        $rows = $wpdb->get_results( "SELECT * FROM `$t` ORDER BY created_at DESC", ARRAY_A ); // phpcs:ignore

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="sgc-proforma-invoices-' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel

        $out = fopen('php://output', 'w');
        fputcsv( $out, ['PI Number','Type','Buyer Name','Email','Company','Country','Currency','Amount','INR Amount','Status','Incoterm','Payment Terms','Sent At','Created At'] );
        foreach ( $rows as $r ) {
            fputcsv( $out, [
                $r['pi_number'], $r['pi_type'], $r['buyer_name'], $r['buyer_email'],
                $r['buyer_company'], $r['buyer_country'], $r['currency'],
                $r['grand_total'], $r['grand_total_inr'], $r['status'],
                $r['incoterm'], $r['payment_terms'], $r['sent_at'], $r['created_at'],
            ] );
        }
        fclose( $out );
        exit;
    }

    // ── AJAX: Delete PI ───────────────────────────────────────────────────
    public static function ajax_delete() {
        self::verify();
        global $wpdb;
        $pi_id = (int) ( $_POST['pi_id'] ?? 0 );
        $pi    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . SGC_IIP_DB::t('proformas') . " WHERE id=%d", $pi_id ), ARRAY_A ); // phpcs:ignore
        if ( $pi && $pi['pdf_path'] && file_exists( $pi['pdf_path'] ) ) @unlink( $pi['pdf_path'] );
        $wpdb->delete( SGC_IIP_DB::t('proformas'), [ 'id' => $pi_id ] ); // phpcs:ignore
        wp_send_json_success();
    }

    // ── AJAX: Revise PI (increment revision) ──────────────────────────────
    public static function ajax_revise() {
        self::verify();
        global $wpdb;
        $pi_id = (int) ( $_POST['pi_id'] ?? 0 );
        $pi    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . SGC_IIP_DB::t('proformas') . " WHERE id=%d", $pi_id ), ARRAY_A ); // phpcs:ignore
        if ( ! $pi ) { wp_send_json_error( 'Not found.' ); return; }

        $new_pi_num = $pi['pi_number'] . '-R' . ( (int) $pi['revision'] + 1 );
        $new_token  = wp_generate_password( 32, false );
        unset( $pi['id'] );
        $pi['pi_number']  = $new_pi_num;
        $pi['revision']   = (int) $pi['revision'] + 1;
        $pi['status']     = 'draft';
        $pi['accept_token'] = $new_token;
        $pi['pdf_path']   = null;
        $pi['sent_at']    = null;
        $pi['valid_until'] = date( 'Y-m-d', strtotime( '+' . (int) get_option( 'sgc_iip_pi_validity', 30 ) . ' days' ) );

        $wpdb->insert( SGC_IIP_DB::t('proformas'), $pi ); // phpcs:ignore
        $new_id = $wpdb->insert_id;

        SGC_IIP_DB::log( $pi['inquiry_id'], 'pi_revised', 'PI revised → ' . $new_pi_num, 'admin' );
        wp_send_json_success( [ 'new_pi_id' => $new_id, 'new_pi_number' => $new_pi_num ] );
    }

    // ── AJAX: Generate PDF ────────────────────────────────────────────────
    public static function ajax_generate_pdf() {
        self::verify();
        global $wpdb;

        $pi_id = (int) ( $_POST['pi_id'] ?? 0 );
        $pi    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . SGC_IIP_DB::t('proformas') . " WHERE id=%d", $pi_id ), ARRAY_A ); // phpcs:ignore
        if ( ! $pi ) { wp_send_json_error( 'PI not found.' ); return; }

        $pi['line_items'] = json_decode( $pi['line_items'] ?? '[]', true );
        $pdf_path = SGC_IIP_PDF::generate_pi( $pi );

        if ( is_wp_error( $pdf_path ) ) { wp_send_json_error( $pdf_path->get_error_message() ); return; }

        $wpdb->update( SGC_IIP_DB::t('proformas'), [ 'pdf_path' => $pdf_path ], [ 'id' => $pi_id ] ); // phpcs:ignore
        wp_send_json_success( [
            'pdf_path' => $pdf_path,
            'pdf_url'  => str_replace( ABSPATH, site_url('/'), $pdf_path ),
        ] );
    }

    // ── AJAX: HTML Preview ────────────────────────────────────────────────
    public static function ajax_preview_html() {
        self::verify();
        global $wpdb;
        $pi_id = (int) ( $_POST['pi_id'] ?? 0 );
        $pi    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . SGC_IIP_DB::t('proformas') . " WHERE id=%d", $pi_id ), ARRAY_A ); // phpcs:ignore
        if ( ! $pi ) { wp_send_json_error( 'Not found.' ); return; }
        $pi['line_items'] = json_decode( $pi['line_items'] ?? '[]', true );

        ob_start();
        include SGC_IIP_PATH . 'templates/pdf/pi-' . sanitize_key( $pi['template'] ) . '.php';
        $html = ob_get_clean();
        wp_send_json_success( [ 'html' => $html ] );
    }

    // ── AJAX: HS Codes ────────────────────────────────────────────────────
    public static function ajax_get_hs_codes() {
        self::verify();
        global $wpdb;
        $codes = $wpdb->get_results( "SELECT * FROM " . SGC_IIP_DB::t('hs_codes') . " ORDER BY hs_code", ARRAY_A ); // phpcs:ignore
        wp_send_json_success( $codes );
    }
}

SGC_IIP_Proforma::register_ajax();
