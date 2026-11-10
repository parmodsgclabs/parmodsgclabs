<?php
defined( 'ABSPATH' ) || exit;

/**
 * SGC_IIP_Hooks
 * Listens to SGC LABS® Manager Pro form submissions.
 * Zero changes needed in Manager Pro.
 */
class SGC_IIP_Hooks {

    public static function init() {
        // ── v1.8.4: Listen to Site Manager unified inquiry hook ────────────────
        // Fires from Get-a-Quote, Inquiry form, B2B Quote, REST API — all SM sources.
        // Duplicate guard: email+product match within 30 min is skipped.
        add_action( 'sgclabs_inquiry_received', [ __CLASS__, 'on_sgclabs_inquiry' ], 20, 1 );
        add_action( 'sgc_iip_classify_inquiry',  [ __CLASS__, 'classify_inquiry_listener' ], 10, 2 );

        // ── Listen to Manager Pro AJAX actions ───────────────────────────
        // Priority 1 = BEFORE Manager Pro calls wp_send_json_success() which dies() execution
        // v1.9.3: the priority-1 intercepts of Site Manager's form AJAX actions were REMOVED.
        // They ran before Site Manager verified the nonce / rate limit / required fields, so
        // any anonymous POST created an Invoice Pro inquiry, and valid submits were stored
        // twice (intercept + sgclabs_inquiry_received). All Site Manager forms now arrive
        // through on_sgclabs_inquiry() only. catch_inquiry()/catch_quote()/catch_b2b() are
        // kept as callable helpers for backward compatibility but are no longer hooked.

        // ── PI Accept link (public) ────────────────────────────────────
        add_action( 'init',           [ __CLASS__, 'register_accept_endpoint' ] );
        add_action( 'template_redirect', [ __CLASS__, 'handle_accept_link'   ] );

        // ── AJAX handlers (admin) ─────────────────────────────────────
        $ajax_map = [
            'sgc_iip_get_inquiries'         => 'ajax_get_inquiries',
            'sgc_iip_get_inquiry'           => 'ajax_get_inquiry',
            'sgc_iip_update_status'         => 'ajax_update_status',
            'sgc_iip_save_note'             => 'ajax_save_note',
            'sgc_iip_delete_inquiry'        => 'ajax_delete_inquiry',
            'sgc_iip_delete_pi'             => 'ajax_delete_pi',
            'sgc_iip_resend_pi'             => 'ajax_resend_pi',
            'sgc_iip_smart_extract'           => 'ajax_smart_extract',
            'sgc_iip_save_extracted_products' => 'ajax_save_extracted_products',
            'sgc_iip_mark_advance'          => 'ajax_mark_advance',
            'sgc_iip_dashboard_stats'       => 'ajax_dashboard_stats',
            'sgc_iip_resend_autoreply'      => 'ajax_resend_autoreply',
            'sgc_iip_add_manual_inquiry'    => 'ajax_add_manual',
            // ── Manager Pro Sync & One-Click PI ──────────────────────────
            'sgc_iip_sync_from_manager'     => 'ajax_sync_from_manager',
            'sgc_iip_one_click_pi_send'     => 'ajax_one_click_pi_send',
            'sgc_iip_get_manager_inquiries' => 'ajax_get_manager_inquiries',
            'sgc_iip_sync_from_wc'          => 'ajax_sync_from_wc',
            'sgc_iip_get_wc_orders'         => 'ajax_get_wc_orders',
            'sgc_iip_wc_product_search'     => 'ajax_wc_product_search',
            // v1.2.0: Payment tracking
            'sgc_iip_add_payment'           => 'ajax_add_payment',
            'sgc_iip_get_payments'          => 'ajax_get_payments',
            'sgc_iip_delete_payment'        => 'ajax_delete_payment',
            // v1.2.1: Pipeline stage move (Kanban drag-drop)
            'sgc_iip_move_stage'            => 'ajax_move_stage',
            'sgc_iip_quick_action'          => 'ajax_quick_action',
            // v1.2.1: All Documents list — external class callbacks
            'sgc_iip_list_all_pi'           => [ 'SGC_IIP_Proforma',     'ajax_list_all' ],
            'sgc_iip_list_all_pl'           => [ 'SGC_IIP_Packlist',     'ajax_list_all' ],
            // v1.3.0: Buyer portal
            'sgc_iip_send_portal_link'      => [ 'SGC_IIP_Buyer_Portal', 'ajax_send_portal_link' ],
            // v1.3.2: Get bank details for PI (by type: export/domestic)
            'sgc_iip_get_bank_for_pi'       => 'ajax_get_bank_for_pi',
            // v1.8.0: Inquiry field edit (name, email, phone, company, country)
            'sgc_iip_update_inquiry'        => 'ajax_update_inquiry',
            // v1.8.0: Fetch single PI by ID (for delete flow status check)
            'sgc_iip_pi_get'                => 'ajax_pi_get',
            // v1.7.0: Conveyor panel — generate PI draft + save follow-up
            'sgc_iip_generate_pi_draft'     => 'ajax_generate_pi_draft',
            'sgc_iip_save_followup'         => 'ajax_save_followup',
        ];
        foreach ( $ajax_map as $action => $method ) {
            // $method can be a string (method on this class) or array [Class, method]
            $callback = is_array( $method ) ? $method : [ __CLASS__, $method ];
            add_action( 'wp_ajax_' . $action, $callback );
        }

        // WP-Cron: follow-up reminder
        add_action( 'sgc_iip_followup_reminder', [ __CLASS__, 'send_followup_reminder' ], 10, 3 );
    }

    // ── Verify nonce helper ───────────────────────────────────────────────
    private static function verify() {
        check_ajax_referer( 'sgc_iip_nonce', 'nonce' );
        // Use manage_woocommerce to match admin page capability (not manage_options)
        if ( ! current_user_can( 'manage_woocommerce' ) )
            wp_send_json_error( 'Unauthorized', 403 );
    }

    // ── Catch: Contact Us / Inquiry form ─────────────────────────────────
    // ── v1.8.5: IP-based rate limiter — shared by all public catch methods ──────
    private static function check_rate_limit( string $action = 'inquiry' ): bool {
        $ip  = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
        $key = 'sgc_iip_rl_' . md5( $ip . $action );
        $hits = (int) get_transient( $key );
        if ( $hits >= 3 ) return false; // block — 3 submissions per 10 min per IP
        set_transient( $key, $hits + 1, 10 * MINUTE_IN_SECONDS );
        return true;
    }

    public static function catch_inquiry() {
        if ( ! self::check_rate_limit( 'inquiry' ) ) return; // silent drop — rate limited
        // Manager Pro already saved — we just catch at priority 20
        // Data is still in $_POST
        $data = [
            'source_type'    => 'contact',
            'name'           => sanitize_text_field( $_POST['name']    ?? '' ),
            'email'          => sanitize_email( $_POST['email']        ?? '' ),
            'phone'          => sanitize_text_field( $_POST['phone']   ?? '' ),
            'country'        => sanitize_text_field( $_POST['country'] ?? '' ),
            'company'        => sanitize_text_field( $_POST['company'] ?? '' ),
            'incoterm'       => sanitize_text_field( $_POST['incoterm']?? 'FOB' ),
            'port'           => sanitize_text_field( $_POST['port']    ?? '' ),
            'raw_message'    => sanitize_textarea_field( $_POST['message'] ?? '' ),
            'product_details'=> wp_json_encode( [[
                'name' => sanitize_text_field( $_POST['product'] ?? '' ),
                'qty'  => sanitize_text_field( $_POST['qty']     ?? '' ),
            ]] ),
        ];

        // source_id: at priority 1, Manager Pro hasn't saved yet — set 0, hook will update later
        $data['source_id'] = 0;

        self::process_new_inquiry( $data );
        // DO NOT send JSON response — Manager Pro handles that
    }

    // ── Catch: Get a Quote form ───────────────────────────────────────────
    public static function catch_quote() {
        if ( ! self::check_rate_limit( 'quote' ) ) return;
        $qtype = sanitize_text_field( $_POST['qtype'] ?? 'Quote Request' );
        $data  = [
            'source_type'    => 'quote',
            'name'           => sanitize_text_field( $_POST['name']    ?? '' ),
            'email'          => sanitize_email( $_POST['email']        ?? '' ),
            'phone'          => sanitize_text_field( $_POST['phone']   ?? '' ),
            'country'        => sanitize_text_field( $_POST['country'] ?? '' ),
            'raw_message'    => sanitize_textarea_field( $_POST['message'] ?? '' ),
            'product_details'=> wp_json_encode( [[
                'name'        => sanitize_text_field( $_POST['product'] ?? '' ),
                'qty'         => sanitize_text_field( $_POST['qty']     ?? '' ),
                'product_url' => esc_url_raw( $_POST['product_url']    ?? '' ),
                'type'        => $qtype,
            ]] ),
        ];

        self::process_new_inquiry( $data );
    }

    // ── Catch: B2B Quote Cart ─────────────────────────────────────────────
    public static function catch_b2b() {
        if ( ! self::check_rate_limit( 'b2b' ) ) return;
        $products_raw = $_POST['products'] ?? [];
        $products     = [];
        if ( is_array( $products_raw ) ) {
            foreach ( $products_raw as $p ) {
                $products[] = [
                    'name'  => sanitize_text_field( $p['name']  ?? '' ),
                    'sku'   => sanitize_text_field( $p['sku']   ?? '' ),
                    'qty'   => sanitize_text_field( $p['qty']   ?? '' ),
                    'price' => floatval( $p['price'] ?? 0 ),
                ];
            }
        }

        $data = [
            'source_type'    => 'b2b',
            'name'           => sanitize_text_field( $_POST['name']     ?? '' ),
            'email'          => sanitize_email( $_POST['email']         ?? '' ),
            'phone'          => sanitize_text_field( $_POST['phone']    ?? '' ),
            'country'        => sanitize_text_field( $_POST['country']  ?? '' ),
            'company'        => sanitize_text_field( $_POST['company']  ?? '' ),
            'incoterm'       => sanitize_text_field( $_POST['incoterm'] ?? 'FOB' ),
            'port'           => sanitize_text_field( $_POST['port']     ?? '' ),
            'raw_message'    => sanitize_textarea_field( $_POST['message'] ?? '' ),
            'product_details'=> wp_json_encode( $products ),
        ];

        self::process_new_inquiry( $data );
    }

    // ── Core processor: save + score + classify + auto-reply ─────────────
    private static function process_new_inquiry( array $data ): int {
        global $wpdb;

        // Detect duplicate buyer
        $existing = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore
            "SELECT COUNT(*) FROM " . SGC_IIP_DB::t('inquiries') . " WHERE email=%s",
            $data['email']
        ) );
        $data['returning_buyer'] = $existing > 0 ? 1 : 0;

        // Detect country code from name
        $data['country_code'] = SGC_IIP_Currency::country_to_code( $data['country'] );

        // Currency based on country
        $data['currency'] = SGC_IIP_Currency::get_for_country( $data['country_code'] );

        // Health score
        $data['health_score'] = SGC_IIP_DB::calc_health( $data );

        // Insert
        $wpdb->insert( SGC_IIP_DB::t('inquiries'), array_merge( $data, [ // phpcs:ignore
            'status'         => 'new',
            'pipeline_stage' => 'new',
        ] ) );
        $inquiry_id = (int) $wpdb->insert_id;
        if ( ! $inquiry_id ) return 0;

        // Timeline: received
        SGC_IIP_DB::log( $inquiry_id, 'received',
            'Inquiry received via ' . $data['source_type'] . ' form',
            'system',
            [ 'source_type' => $data['source_type'], 'health' => $data['health_score'] ]
        );

        // Update pi_accept_token
        $token = wp_generate_password( 32, false );
        $wpdb->update( SGC_IIP_DB::t('inquiries'), [ 'pi_accept_token' => $token ], [ 'id' => $inquiry_id ] ); // phpcs:ignore

        // Auto-reply (email always, WA if configured)
        $inquiry_data = $wpdb->get_row( // phpcs:ignore
            $wpdb->prepare( "SELECT * FROM " . SGC_IIP_DB::t('inquiries') . " WHERE id=%d", $inquiry_id ),
            ARRAY_A
        );

        // Classify buyer type with AI (async-style — low priority)
        do_action( 'sgc_iip_classify_inquiry', $inquiry_id, $inquiry_data );

        // Send auto-reply
        if ( get_option( 'sgc_iip_autoreply_email', '1' ) === '1' ) {
            $sent = SGC_IIP_Email::send_autoreply( $inquiry_data );
            if ( $sent ) {
                $wpdb->update( SGC_IIP_DB::t('inquiries'), [ 'email_sent' => 1, 'auto_reply_at' => current_time('mysql') ], [ 'id' => $inquiry_id ] ); // phpcs:ignore
                SGC_IIP_DB::log( $inquiry_id, 'autoreply_sent', 'Auto-reply email sent to ' . $data['email'] );
            }
        }

        // WhatsApp auto-reply
        if ( get_option( 'sgc_iip_wa_enabled', '0' ) === '1' && ! empty( $data['phone'] ) ) {
            $wa_sent = SGC_IIP_WhatsApp::send_autoreply( $inquiry_data );
            if ( $wa_sent ) {
                $wpdb->update( SGC_IIP_DB::t('inquiries'), [ 'wa_sent' => 1 ], [ 'id' => $inquiry_id ] ); // phpcs:ignore
                SGC_IIP_DB::log( $inquiry_id, 'wa_autoreply_sent', 'WhatsApp auto-reply sent' );
            }
        }

        do_action( 'sgc_iip_inquiry_processed', $inquiry_id, $inquiry_data );
        return $inquiry_id;
    }

    // ── AI classify listener (v1.9.3: was registered AFTER the action fired, so it never ran) ──
    public static function classify_inquiry_listener( $id, $data ): void {
        if ( ! class_exists( 'SGC_IIP_AI' ) || ! is_array( $data ) ) return;
        $type = SGC_IIP_AI::classify_inquiry( $data );
        if ( $type ) {
            global $wpdb;
            $wpdb->update( SGC_IIP_DB::t('inquiries'), [ 'buyer_type' => $type, 'ai_classified' => 1 ], [ 'id' => (int) $id ] ); // phpcs:ignore
        }
    }

    // ── PI Accept endpoint ────────────────────────────────────────────────
    public static function register_accept_endpoint() {
        // v1.3.1 FIX: PI numbers contain slashes (e.g. SGC/04/-2026-0034)
        // Token is always last 32-char segment — capture everything before it as pi_number
        add_rewrite_rule(
            '^sgc-pi-accept/(.+)/([a-zA-Z0-9]{16,})/?$',
            'index.php?sgc_pi_number=$matches[1]&sgc_pi_token=$matches[2]',
            'top'
        );
        add_rewrite_tag( '%sgc_pi_number%', '(.+)' );
        add_rewrite_tag( '%sgc_pi_token%',  '([a-zA-Z0-9]+)' );
    }

    public static function handle_accept_link() {
        $pi_number = get_query_var( 'sgc_pi_number' );
        $token     = get_query_var( 'sgc_pi_token' );
        if ( ! $pi_number || ! $token ) return;

        global $wpdb;
        $pi = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore
            "SELECT * FROM " . SGC_IIP_DB::t('proformas') . " WHERE pi_number=%s AND accept_token=%s",
            $pi_number, $token
        ) );

        if ( ! $pi ) { wp_die( 'Invalid or expired link.' ); return; }
        if ( $pi->status === 'accepted' ) {
            wp_die( 'This Proforma Invoice has already been accepted. Thank you!' );
            return;
        }

        // Mark accepted
        $wpdb->update( SGC_IIP_DB::t('proformas'), [ 'status' => 'accepted', 'sent_at' => current_time('mysql') ], [ 'id' => $pi->id ] ); // phpcs:ignore
        $wpdb->update( SGC_IIP_DB::t('inquiries'), [ 'pi_accepted' => 1, 'pi_accepted_at' => current_time('mysql'), 'status' => 'accepted' ], [ 'id' => $pi->inquiry_id ] ); // phpcs:ignore
        $wpdb->insert( SGC_IIP_DB::t('accepts'), [ // phpcs:ignore
            'pi_id'       => $pi->id,
            'inquiry_id'  => $pi->inquiry_id,
            'buyer_ip'    => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
            'buyer_agent' => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
        ] );

        SGC_IIP_DB::log( $pi->inquiry_id, 'pi_accepted', 'PI ' . $pi_number . ' accepted by buyer online', 'buyer' );

        // Show standalone acceptance page — NO WordPress theme/header/footer
        $co_name  = get_option( 'sgc_iip_company_name',    'SGC LABS® Scientific Glassware' );
        $co_addr  = get_option( 'sgc_iip_company_address', 'Ambala, Haryana 133001, India' );
        $co_email = get_option( 'sgc_iip_company_email',   'info@labsglass.com' );
        $co_phone = get_option( 'sgc_iip_company_phone',   '+91 999 641 6935' );
        $co_web   = get_option( 'sgc_iip_company_website', 'https://labsglass.com' );

        // Pass data to template via variables
        $pi_data  = (array) $pi;

        // Output completely standalone HTML — bypasses WordPress theme entirely
        nocache_headers();
        header( 'Content-Type: text/html; charset=UTF-8' );

        include SGC_IIP_PATH . 'templates/pi-accepted.php';
        exit;
    }

    // ── AJAX: Get inquiries list ──────────────────────────────────────────
    public static function ajax_get_inquiries() {
        self::verify();
        global $wpdb;

        $page    = max( 1, (int) ( $_POST['page'] ?? 1 ) );
        $per     = 20;
        $offset  = ( $page - 1 ) * $per;
        $status  = sanitize_key( $_POST['status']  ?? '' );
        $search  = sanitize_text_field( $_POST['search'] ?? '' );
        $country = sanitize_text_field( $_POST['country'] ?? '' );
        $type    = sanitize_key( $_POST['source_type'] ?? '' );

        $where = [ '1=1' ];
        $vals  = [];

        if ( $status )  { $where[] = 'status=%s';       $vals[] = $status; }
        if ( $country ) { $where[] = 'country_code=%s'; $vals[] = $country; }
        if ( $type )    { $where[] = 'source_type=%s';  $vals[] = $type; }
        if ( $search )  {
            $where[] = '(name LIKE %s OR email LIKE %s OR company LIKE %s)';
            $like    = '%' . $wpdb->esc_like( $search ) . '%';
            $vals[]  = $like; $vals[] = $like; $vals[] = $like;
        }

        $where_sql = implode( ' AND ', $where );
        $table     = SGC_IIP_DB::t('inquiries');

        $total = (int) $wpdb->get_var( $vals
            ? $wpdb->prepare( "SELECT COUNT(*) FROM `$table` WHERE $where_sql", ...$vals ) // phpcs:ignore
            : "SELECT COUNT(*) FROM `$table` WHERE $where_sql"
        );

        $rows = $vals
            ? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$table` WHERE $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d", ...array_merge( $vals, [ $per, $offset ] ) ), ARRAY_A ) // phpcs:ignore
            : $wpdb->get_results( "SELECT * FROM `$table` WHERE $where_sql ORDER BY created_at DESC LIMIT $per OFFSET $offset", ARRAY_A ); // phpcs:ignore

        // Attach latest PI number + flatten product name for JS
        foreach ( $rows as &$row ) {
            $row['latest_pi'] = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore
                "SELECT pi_number FROM " . SGC_IIP_DB::t('proformas') . " WHERE inquiry_id=%d ORDER BY id DESC LIMIT 1",
                $row['id']
            ) );
            $pd = json_decode( $row['product_details'] ?? '[]', true ) ?: [];
            $row['product_details'] = $pd;
            // Flatten first product name for easy display in JS
            $row['product'] = isset($pd[0]['name']) ? $pd[0]['name'] . ( isset($pd[0]['qty']) ? ' (' . $pd[0]['qty'] . ')' : '' ) : ( $row['raw_message'] ?? '' );
        }
        unset( $row );

        wp_send_json_success( [
            'inquiries' => $rows,
            'total'     => $total,
            'pages'     => max( 1, (int) ceil( $total / $per ) ),
        ] );
    }

    // ── AJAX: Single inquiry detail ───────────────────────────────────────
    public static function ajax_get_inquiry() {
        self::verify();
        global $wpdb;
        $id = (int) ( $_POST['id'] ?? 0 );

        $inq = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore
            "SELECT * FROM " . SGC_IIP_DB::t('inquiries') . " WHERE id=%d", $id
        ), ARRAY_A );
        if ( ! $inq ) { wp_send_json_error( 'Not found.' ); return; }

        $inq['product_details'] = json_decode( $inq['product_details'] ?? '[]', true );
        $inq['timeline'] = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
            "SELECT * FROM " . SGC_IIP_DB::t('timeline') . " WHERE inquiry_id=%d ORDER BY created_at ASC",
            $id
        ), ARRAY_A );
        $inq['proformas'] = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
            "SELECT id, pi_number, pi_type, status, grand_total, currency, sent_at, valid_until FROM " . SGC_IIP_DB::t('proformas') . " WHERE inquiry_id=%d ORDER BY id DESC",
            $id
        ), ARRAY_A );

        wp_send_json_success( $inq );
    }

    // ── AJAX: Update status ───────────────────────────────────────────────
    public static function ajax_update_status() {
        self::verify();
        global $wpdb;

        $id          = (int) ( $_POST['id'] ?? 0 );
        $status      = sanitize_key( $_POST['status'] ?? '' );
        $loss_reason = sanitize_text_field( $_POST['loss_reason'] ?? '' );

        $allowed = [ 'new','replied','pi_sent','accepted','advance','won','lost','sample' ];
        if ( ! in_array( $status, $allowed, true ) ) { wp_send_json_error( 'Invalid status.' ); return; }

        $update = [ 'status' => $status, 'pipeline_stage' => $status ];
        if ( $loss_reason ) $update['loss_reason'] = $loss_reason;

        $wpdb->update( SGC_IIP_DB::t('inquiries'), $update, [ 'id' => $id ] ); // phpcs:ignore
        SGC_IIP_DB::log( $id, 'status_changed', 'Status → ' . $status, 'admin' );

        // v1.2.1: Fire deal_won hook — CRM sync, reorder sequence, analytics
        if ( $status === 'won' ) {
            $inq = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . SGC_IIP_DB::t('inquiries') . " WHERE id=%d", $id ), ARRAY_A ); // phpcs:ignore
            if ( $inq ) do_action( 'sgc_iip_deal_won', $inq );
        }

        wp_send_json_success();
    }

    // ── AJAX: Save note ───────────────────────────────────────────────────
    public static function ajax_save_note() {
        self::verify();
        global $wpdb;
        $id   = (int) ( $_POST['id'] ?? 0 );
        $note = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );
        $wpdb->update( SGC_IIP_DB::t('inquiries'), [ 'admin_note' => $note ], [ 'id' => $id ] ); // phpcs:ignore
        SGC_IIP_DB::log( $id, 'note_saved', 'Admin note updated', 'admin' );
        wp_send_json_success();
    }

    // ── AJAX: Mark advance received ───────────────────────────────────────
    public static function ajax_mark_advance() {
        self::verify();
        global $wpdb;
        $id     = (int) ( $_POST['id']     ?? 0 );
        $amount = floatval( $_POST['amount'] ?? 0 );
        if ( ! $id || ! $amount ) { wp_send_json_error( 'Invalid data.' ); return; }

        $wpdb->update( SGC_IIP_DB::t('inquiries'), [ // phpcs:ignore
            'advance_amount' => $amount,
            'advance_date'   => current_time('mysql'),
            'status'         => 'advance',
        ], [ 'id' => $id ] );

        SGC_IIP_DB::log( $id, 'advance_received', 'Advance received: ' . $amount, 'admin' );

        // Send receipt
        $inq = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . SGC_IIP_DB::t('inquiries') . " WHERE id=%d", $id ), ARRAY_A ); // phpcs:ignore
        if ( $inq ) SGC_IIP_Email::send_advance_receipt( $inq, $amount );

        wp_send_json_success( 'Advance marked and receipt sent.' );
    }

    // ── AJAX: Dashboard stats ─────────────────────────────────────────────
    public static function ajax_dashboard_stats() {
        self::verify();
        global $wpdb;
        $t = SGC_IIP_DB::t('inquiries');

        $stats = [
            'total'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$t`" ), // phpcs:ignore
            'new'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$t` WHERE status='new'" ), // phpcs:ignore
            'pi_sent'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$t` WHERE status='pi_sent'" ), // phpcs:ignore
            'won'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$t` WHERE status='won'" ), // phpcs:ignore
            'lost'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$t` WHERE status='lost'" ), // phpcs:ignore
            'today'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$t` WHERE DATE(created_at)=CURDATE()" ), // phpcs:ignore
            'this_month'=> (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$t` WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())" ), // phpcs:ignore
            'hot_leads' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$t` WHERE health_score>=70 AND status='new'" ), // phpcs:ignore
            'conversion_rate' => 0,
        ];
        if ( $stats['total'] > 0 ) {
            $stats['conversion_rate'] = round( ( $stats['won'] / $stats['total'] ) * 100, 1 );
        }

        // Top countries
        $stats['top_countries'] = $wpdb->get_results( // phpcs:ignore
            "SELECT country, COUNT(*) as cnt FROM `$t` GROUP BY country ORDER BY cnt DESC LIMIT 5",
            ARRAY_A
        );

        // Source breakdown
        $stats['by_source'] = $wpdb->get_results( // phpcs:ignore
            "SELECT source_type, COUNT(*) as cnt FROM `$t` GROUP BY source_type",
            ARRAY_A
        );

        // Loss reasons
        $stats['loss_reasons'] = $wpdb->get_results( // phpcs:ignore
            "SELECT loss_reason, COUNT(*) as cnt FROM `$t` WHERE status='lost' AND loss_reason IS NOT NULL GROUP BY loss_reason ORDER BY cnt DESC",
            ARRAY_A
        );

        wp_send_json_success( $stats );
    }

    // ── AJAX: Resend auto-reply ───────────────────────────────────────────
    public static function ajax_resend_autoreply() {
        self::verify();
        global $wpdb;
        $id  = (int) ( $_POST['id'] ?? 0 );
        $inq = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . SGC_IIP_DB::t('inquiries') . " WHERE id=%d", $id ), ARRAY_A ); // phpcs:ignore
        if ( ! $inq ) { wp_send_json_error( 'Not found.' ); return; }

        $sent = SGC_IIP_Email::send_autoreply( $inq );
        if ( $sent ) SGC_IIP_DB::log( $id, 'autoreply_resent', 'Auto-reply resent by admin', 'admin' );
        wp_send_json_success( $sent ? 'Sent!' : 'Failed to send.' );
    }

    // ── AJAX: Add manual inquiry ──────────────────────────────────────────
    public static function ajax_add_manual() {
        self::verify();

        $country  = sanitize_text_field( wp_unslash( $_POST['country'] ?? '' ) );
        $is_india = in_array( strtolower( trim( $country ) ), ['india','in','bharat','ind'], true );

        $data = [
            'source_type'    => 'manual',
            'name'           => sanitize_text_field( wp_unslash( $_POST['name']    ?? '' ) ),
            'email'          => sanitize_email( wp_unslash( $_POST['email']        ?? '' ) ),
            'phone'          => sanitize_text_field( wp_unslash( $_POST['phone']   ?? '' ) ),
            'company'        => sanitize_text_field( wp_unslash( $_POST['company'] ?? '' ) ),
            'country'        => $country,
            'currency'       => $is_india ? 'INR' : 'USD',
            'incoterm'       => $is_india ? 'Ex-Works' : sanitize_text_field( wp_unslash( $_POST['incoterm'] ?? 'FOB' ) ),
            'port'           => $is_india
                ? get_option( 'sgc_iip_default_dom_port', 'Ambala, India' )
                : sanitize_text_field( wp_unslash( $_POST['port'] ?? '' ) ),
            'buyer_type'     => $is_india ? 'domestic' : 'export',
            'raw_message'    => sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ),
            'product_details'=> wp_json_encode( [[
                'name' => sanitize_text_field( wp_unslash( $_POST['product'] ?? '' ) ),
                'qty'  => sanitize_text_field( wp_unslash( $_POST['qty']     ?? '' ) ),
            ]] ),
        ];

        if ( ! $data['name'] || ! $data['email'] ) { wp_send_json_error( 'Name and email required.' ); return; }

        self::process_new_inquiry( $data );
        wp_send_json_success( 'Inquiry added.' );
    }

    // ═══════════════════════════════════════════════════════════════════
    // MANAGER PRO SYNC — reads wp_sgclabs_inquiries → imports to SGC IIP
    // ═══════════════════════════════════════════════════════════════════

    // ── AJAX: Get Manager Pro inquiries (preview before sync) ─────────
    public static function ajax_get_manager_inquiries() {
        self::verify();
        global $wpdb;

        $mp_table = $wpdb->prefix . 'sgclabs_inquiries';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$mp_table}'" ) !== $mp_table ) {
            wp_send_json_error( 'Manager Pro not found. Table wp_sgclabs_inquiries missing.' );
            return;
        }

        $limit  = (int) ( $_POST['limit'] ?? 50 );
        $offset = (int) ( $_POST['offset'] ?? 0 );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM `{$mp_table}` ORDER BY id DESC LIMIT %d OFFSET %d",
            $limit, $offset
        ), ARRAY_A );

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$mp_table}`" );

        // Check which are already synced
        $iip_table = SGC_IIP_DB::t( 'inquiries' );
        foreach ( $rows as &$row ) {
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM `{$iip_table}` WHERE source_type='manager_pro' AND source_id=%d LIMIT 1",
                $row['id']
            ) );
            $row['already_synced'] = ! empty( $exists );
            $row['iip_id']         = $exists ?: null;
        }
        unset( $row );

        wp_send_json_success( [ 'rows' => $rows, 'total' => $total ] );
    }

    // ── AJAX: Sync selected / all from Manager Pro ────────────────────
    public static function ajax_sync_from_manager() {
        self::verify();
        global $wpdb;

        $mp_table  = $wpdb->prefix . 'sgclabs_inquiries';
        $iip_table = SGC_IIP_DB::t( 'inquiries' );

        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$mp_table}'" ) !== $mp_table ) {
            wp_send_json_error( 'Manager Pro table not found.' );
            return;
        }

        // Which IDs to sync? 'all' or array of IDs
        $mode      = sanitize_key( $_POST['mode'] ?? 'selected' );
        // Accept both ids[] and ids formats
        $ids_raw = $_POST['ids'] ?? ( $_POST['ids[]'] ?? [] );
        if ( is_string( $ids_raw ) ) $ids_raw = explode( ',', $ids_raw );

        if ( 'all' === $mode ) {
            // Sync both regular inquiries AND b2b quotes
            $rows = $wpdb->get_results( "SELECT * FROM `{$mp_table}` ORDER BY id DESC LIMIT 500", ARRAY_A );
        } else {
            if ( ! is_array( $ids_raw ) || empty( $ids_raw ) ) {
                wp_send_json_error( 'No IDs provided.' ); return;
            }
            $ids_safe = implode( ',', array_map( 'intval', $ids_raw ) );
            $rows     = $wpdb->get_results( "SELECT * FROM `{$mp_table}` WHERE id IN ({$ids_safe})", ARRAY_A );
        }

        $synced  = 0;
        $skipped = 0;

        foreach ( $rows as $row ) {
            // Skip already synced
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM `{$iip_table}` WHERE source_type='manager_pro' AND source_id=%d LIMIT 1",
                $row['id']
            ) );
            if ( $exists ) { $skipped++; continue; }

            // Build product_details JSON
            // Build product_details — handle Manager Pro inquiry + B2B quote formats
            $product_details = wp_json_encode( [ [
                'name'        => $row['product'] ?? '',
                'qty'         => $row['qty']     ?? '',
                'product_url' => $row['product_url'] ?? '',
            ] ] );

            // Country code
            $country_code = SGC_IIP_Currency::country_to_code( $row['country'] ?? '' );
            $currency     = SGC_IIP_Currency::get_for_country( $country_code );

            // Proper field mapping: Manager Pro → Invoice Pro
            // wp_sgclabs_inquiries: name,email,phone,country,company,incoterm,port,product,qty,message
            $data = [
                'source_id'      => (int) $row['id'],
                'source_type'    => 'manager_pro',
                'name'           => $row['name']     ?? '',
                'email'          => $row['email']    ?? '',
                'phone'          => $row['phone']    ?? '',
                'company'        => $row['company']  ?? '',
                'country'        => $row['country']  ?? '',
                'country_code'   => $country_code,
                'currency'       => $currency,
                'incoterm'       => $row['incoterm'] ?? 'FOB',
                'port'           => $row['port']     ?? '',
                'raw_message'    => $row['message']  ?? '',
                'product_details'=> $product_details,
                'status'         => ( $row['status'] ?? 'new' ) === 'read' ? 'replied' : 'new',
                'health_score'   => SGC_IIP_DB::calc_health( [
                    'name'           => $row['name'],
                    'email'          => $row['email'],
                    'phone'          => $row['phone'],
                    'company'        => $row['company'],
                    'country'        => $row['country'],
                    'product_details'=> $product_details,
                    'raw_message'    => $row['message'],
                ] ),
                'pi_accept_token' => wp_generate_password( 32, false ),
                'created_at'      => $row['created_at'] ?? current_time( 'mysql' ),
            ];

            $wpdb->insert( $iip_table, $data );
            $iip_id = $wpdb->insert_id;

            if ( $iip_id ) {
                SGC_IIP_DB::log( $iip_id, 'imported', 'Synced from Manager Pro (ID #' . $row['id'] . ')', 'system' );
                $synced++;
            }
        }

        wp_send_json_success( [
            'synced'  => $synced,
            'skipped' => $skipped,
            'total'   => count( $rows ),
            'message' => "✅ {$synced} imported, {$skipped} already existed.",
        ] );
    }

    // ═══════════════════════════════════════════════════════════════════
    // ONE-CLICK PI SEND — AI Draft → Create PI → Generate PDF → Email
    // ═══════════════════════════════════════════════════════════════════
    public static function ajax_one_click_pi_send() {
        self::verify();
        global $wpdb;

        $inquiry_id = (int) ( $_POST['inquiry_id'] ?? 0 );
        if ( ! $inquiry_id ) { wp_send_json_error( 'Inquiry ID required.' ); return; }

        $inq = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . SGC_IIP_DB::t('inquiries') . " WHERE id=%d", $inquiry_id
        ), ARRAY_A );
        if ( ! $inq ) { wp_send_json_error( 'Inquiry not found.' ); return; }

        // ── Step 1: Build line items ──────────────────────────────────
        $products    = json_decode( $inq['product_details'] ?? '[]', true ) ?: [];
        $usd_rate    = floatval( get_option( 'sgc_iip_usd_rate', 83.50 ) );
        // ── Auto-detect domestic vs export based on buyer country ────
        $buyer_country_raw = strtolower( trim( $inq['country'] ?? '' ) );
        $is_india = in_array( $buyer_country_raw, ['india','in','bharat','ind'], true );

        $currency   = $is_india ? 'INR' : ( $inq['currency'] ?? 'USD' );
        $incoterm   = $is_india
            ? ( $inq['incoterm'] ?: 'Ex-Works' )
            : ( $inq['incoterm'] ?: get_option( 'sgc_iip_default_incoterm', 'FOB' ) );
        $port       = $is_india
            ? get_option( 'sgc_iip_default_dom_port', 'Ambala, India' )
            : ( $inq['port'] ?: get_option( 'sgc_iip_default_port', 'New Delhi, India' ) );
        $pi_type_label = $is_india ? 'TAX INVOICE' : 'PROFORMA INVOICE';
        $buyer_type  = $inq['buyer_type'] ?? 'bulk';
        $pay_terms   = get_option( 'sgc_iip_default_payment', '30% advance, 70% before shipment' );
        $delivery    = get_option( 'sgc_iip_default_delivery', '15-20 working days' );
        $notes       = '';
        $line_items  = [];
        $subtotal    = 0.0; $pkg_total = 0.0; $oem_total = 0.0; $grand_total = 0.0;

        // Use saved rates if user set them — skip AI
        $use_saved = ! empty( $_POST['use_saved_rates'] );
        $all_rated = $use_saved && ! empty( $products ) && array_reduce( $products, function( $c, $p ) {
            return $c && isset( $p['rate'] ) && floatval( $p['rate'] ) > 0;
        }, true );

        if ( $all_rated ) {
            foreach ( $products as $p ) {
                $qty   = max( 1, (int) preg_replace( '/[^0-9]/', '', $p['qty'] ?? '1' ) );
                $rate  = floatval( $p['rate'] );
                $pkg   = round( $rate * 0.06, 2 );
                $tot   = round( ( $rate + $pkg ) * $qty, 2 );
                $line_items[] = [
                    'product_name'       => sanitize_text_field( $p['name'] ?? 'Laboratory Glassware' ),
                    'sku'                => sanitize_text_field( $p['sku']  ?? '' ),
                    'hs_code'            => sanitize_text_field( $p['hsn']  ?? '7017.20' ),
                    'qty'                => $qty,
                    'unit'               => sanitize_text_field( $p['unit'] ?? 'pcs' ),
                    'unit_price'         => $rate,
                    'packaging_per_unit' => $pkg,
                    'oem_charge'         => 0.00,
                    'total'              => $tot,
                    'specs'              => 'Borosilicate 3.3',
                ];
                $subtotal    += $rate * $qty;
                $pkg_total   += $pkg  * $qty;
                $grand_total += $tot;
            }
        } else {
        // AI pricing path
        $product_str = implode( ', ', array_map( function($p){
            return ($p['name']??'') . ( !empty($p['qty']) ? ' (Qty: '.$p['qty'].')' : '' );
        }, $products ) );
        $wc_ref = '';
        foreach ( $products as $p ) {
            if ( empty( $p['name'] ) ) continue;
            $q = new WP_Query( [ 'post_type' => 'product', 's' => $p['name'], 'posts_per_page' => 2, 'fields' => 'ids' ] );
            foreach ( $q->posts as $pid ) {
                $wcp = wc_get_product( $pid );
                if ( $wcp ) {
                    $wc_ref .= $wcp->get_name() . ' | MRP: ₹' . $wcp->get_regular_price()
                        . ' | USD: $' . round( floatval( $wcp->get_regular_price() ) / $usd_rate, 2 ) . ' | SKU: ' . $wcp->get_sku() . "
";
                }
            }
        }

        // HS code lookup
        $hs_default = $wpdb->get_var( "SELECT hs_code FROM " . SGC_IIP_DB::t('hs_codes') . " WHERE category_match='borosilicate' LIMIT 1" );

        $system = 'You are a senior B2B export pricing specialist for SGC LABS®, a laboratory glassware manufacturer in India (ISO certified, since 2003). Generate accurate commercial Proforma Invoice line items. Return ONLY valid JSON, no markdown.';

        $prompt = "Generate complete commercial Proforma Invoice for:
"
            . "Buyer: {$inq['name']} | Company: {$inq['company']} | Country: {$inq['country']}
"
            . "Buyer Type: {$buyer_type} | Incoterm: {$incoterm} | Port: {$port}
"
            . "Products Requested: {$product_str}
"
            . "Currency: {$currency} | USD Rate: {$usd_rate}
"
            . ( $wc_ref ? "Our WooCommerce Reference Prices:
{$wc_ref}
" : "" )
            . "Default HS Code: " . ( $hs_default ?: '7017.20' ) . "

"
            . "IMPORTANT: Price competitively for {$buyer_type} buyer. Include packaging charge per unit.

"
            . 'Return JSON:
{
  "line_items":[
    {
      "product_name":"...",
      "sku":"...",
      "hs_code":"7017.20",
      "qty":100,
      "unit_price":2.50,
      "packaging_per_unit":0.15,
      "oem_charge":0.00,
      "total":265.00,
      "specs":"borosilicate 3.3, ISO standard"
    }
  ],
  "subtotal":250.00,
  "packaging_total":15.00,
  "oem_total":0.00,
  "discount":0.00,
  "grand_total":265.00,
  "payment_terms":"30% advance, 70% before shipment",
  "delivery_days":"15-20 working days",
  "notes":"All glassware meets ASTM E438 and ISO 3585 standards.",
  "ai_remarks":"Pricing basis and key notes"
}';

        $ai_result = SGC_IIP_AI::call( $prompt, $system, 1800 );

        if ( ! is_wp_error( $ai_result ) ) {
            $clean  = preg_replace( '/```(?:json)?|```/', '', $ai_result );
            $parsed = json_decode( trim( $clean ), true );
            if ( $parsed && ! empty( $parsed['line_items'] ) ) {
                $line_items  = $parsed['line_items'];
                $subtotal    = floatval( $parsed['subtotal']       ?? 0 );
                $pkg_total   = floatval( $parsed['packaging_total']?? 0 );
                $oem_total   = floatval( $parsed['oem_total']      ?? 0 );
                $grand_total = floatval( $parsed['grand_total']    ?? 0 );
                $pay_terms   = $parsed['payment_terms']  ?? $pay_terms;
                $delivery    = $parsed['delivery_days']  ?? $delivery;
                $notes       = $parsed['notes']          ?? '';
            }
        }

        // Fallback if AI failed — manual line item from inquiry data
        if ( empty( $line_items ) ) {
            foreach ( $products as $p ) {
                $qty  = (int) preg_replace( '/[^0-9]/', '', $p['qty'] ?? '100' ) ?: 100;
                $line_items[] = [
                    'product_name'       => $p['name'] ?? 'Laboratory Glassware',
                    'sku'                => $p['sku']  ?? '',
                    'hs_code'            => $p['hsn']  ?? ( $hs_default ?: '7017.20' ),
                    'qty'                => $qty,
                    'unit_price'         => 2.00,
                    'packaging_per_unit' => 0.15,
                    'oem_charge'         => 0.00,
                    'total'              => $qty * 2.15,
                    'specs'              => 'Borosilicate 3.3',
                ];
                $subtotal    += $qty * 2.00;
                $pkg_total   += $qty * 0.15;
                $grand_total += $qty * 2.15;
            }
        }
        } // end else (AI pricing)

        // ── Step 2: Create PI record ──────────────────────────────────
        $pi_number   = SGC_IIP_DB::generate_pi_number( 'standard' );
        $valid_until = date( 'Y-m-d', strtotime( '+' . (int) get_option( 'sgc_iip_pi_validity', 30 ) . ' days' ) );
        $token       = wp_generate_password( 32, false );

        $pi_data = [
            'inquiry_id'      => $inquiry_id,
            'pi_number'       => $pi_number,
            'pi_type'         => $is_india ? 'domestic' : 'standard',
            'pi_type_label'   => $pi_type_label,
            'revision'        => 1,
            'template'        => get_option( 'sgc_iip_default_template', 'navy' ),
            'buyer_name'      => $inq['name'],
            'buyer_email'     => $inq['email'],
            'buyer_company'   => $inq['company'],
            'buyer_country'   => $inq['country'],
            'currency'        => $currency,
            'inr_rate'        => $usd_rate,
            'line_items'      => wp_json_encode( $line_items ),
            'subtotal'        => round( $subtotal, 2 ),
            'packaging_total' => round( $pkg_total, 2 ),
            'oem_charge'      => round( $oem_total, 2 ),
            'tax_amount'      => 0,
            'discount'        => 0,
            'grand_total'     => round( $grand_total, 2 ),
            'grand_total_inr' => round( $grand_total * $usd_rate, 2 ),
            'incoterm'        => $incoterm,
            'port'            => $port,
            'payment_terms'   => $pay_terms,
            'delivery_days'   => $delivery,
            'validity_days'   => (int) get_option( 'sgc_iip_pi_validity', 30 ),
            'valid_until'     => $valid_until,
            'notes'           => $notes,
            'status'          => 'draft',
            'accept_token'    => $token,
        ];

        $wpdb->insert( SGC_IIP_DB::t('proformas'), $pi_data );
        $pi_id = (int) $wpdb->insert_id;

        if ( ! $pi_id ) {
            wp_send_json_error( 'Failed to create PI record.' ); return;
        }

        // ── Step 3: Generate PDF ──────────────────────────────────────
        $pi_for_pdf              = $pi_data;
        $pi_for_pdf['id']        = $pi_id;
        $pi_for_pdf['line_items']= $line_items;

        $pdf_path = SGC_IIP_PDF::generate_pi( $pi_for_pdf );
        $pdf_ok   = ! is_wp_error( $pdf_path );

        if ( $pdf_ok ) {
            $wpdb->update( SGC_IIP_DB::t('proformas'), [ 'pdf_path' => $pdf_path ], [ 'id' => $pi_id ] );
        }

        // ── Step 4: Send Email to buyer ───────────────────────────────
        $accept_url = home_url( '/sgc-pi-accept/' . $pi_number . '/' . $token );
        $attachments = ( $pdf_ok && file_exists( $pdf_path ) ) ? [ $pdf_path ] : [];
        $email_sent  = SGC_IIP_Email::send_pi( $inq, $pi_data, $pdf_path ?: '', $accept_url );

        // ── Step 5: Update inquiry status ────────────────────────────
        $wpdb->update( SGC_IIP_DB::t('inquiries'), [
            'status'     => 'pi_sent',
            'pi_number'  => $pi_number,
        ], [ 'id' => $inquiry_id ] );

        // ── Step 6: WhatsApp notification ────────────────────────────
        if ( get_option( 'sgc_iip_wa_enabled', '0' ) === '1' && ! empty( $inq['phone'] ) ) {
            SGC_IIP_WhatsApp::send_pi_notification( $inq, $pi_number, $accept_url );
        }

        // ── Log ──────────────────────────────────────────────────────
        SGC_IIP_DB::log( $inquiry_id, 'one_click_pi_sent',
            "One-click PI {$pi_number} — AI drafted, PDF " . ( $pdf_ok ? 'generated' : 'failed' ) . ", Email " . ( $email_sent ? 'sent' : 'failed' ),
            'admin'
        );

        wp_send_json_success( [
            'pi_number'  => $pi_number,
            'pi_id'      => $pi_id,
            'grand_total'=> round( $grand_total, 2 ),
            'currency'   => $currency,
            'pdf_ok'     => $pdf_ok,
            'email_sent' => $email_sent,
            'accept_url' => $accept_url,
            'message'    => "✅ PI {$pi_number} created & sent to {$inq['email']}",
        ] );
    }

    // ═══════════════════════════════════════════════════════════════════
    // WOOCOMMERCE SYNC
    // ═══════════════════════════════════════════════════════════════════

    // ── GET: Export all inquiries as CSV — v1.2.1 ─────────────────────────
    // ── v1.3.2: Return bank details for PI (export or domestic) ─────────────
    public static function ajax_get_bank_for_pi() {
        check_ajax_referer( 'sgc_iip_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'Unauthorized' );

        $type = sanitize_key( $_POST['type'] ?? 'export' );

        if ( $type === 'domestic' ) {
            wp_send_json_success( [
                'beneficiary' => get_option( 'sgc_iip_dom_bank_beneficiary', get_option('sgc_iip_bank_beneficiary','') ),
                'bank'        => get_option( 'sgc_iip_dom_bank_name',        get_option('sgc_iip_bank_name','') ),
                'branch'      => get_option( 'sgc_iip_dom_bank_branch',      '' ),
                'account'     => get_option( 'sgc_iip_dom_bank_account',     get_option('sgc_iip_bank_account','') ),
                'ifsc'        => get_option( 'sgc_iip_dom_bank_ifsc',        get_option('sgc_iip_bank_ifsc','') ),
                'upi_id'      => get_option( 'sgc_iip_upi_id',              '' ),
                'upi_name'    => get_option( 'sgc_iip_upi_name',            '' ),
                'swift'       => '',
                'currency'    => 'INR',
            ] );
        } else {
            wp_send_json_success( [
                'beneficiary' => get_option( 'sgc_iip_export_bank_beneficiary', get_option('sgc_iip_bank_beneficiary','') ),
                'bank'        => get_option( 'sgc_iip_export_bank_name',        get_option('sgc_iip_bank_name','') ),
                'branch'      => get_option( 'sgc_iip_export_bank_branch',      '' ),
                'account'     => get_option( 'sgc_iip_export_bank_account',     get_option('sgc_iip_bank_account','') ),
                'ifsc'        => get_option( 'sgc_iip_export_bank_ifsc',        '' ),
                'swift'       => get_option( 'sgc_iip_export_bank_swift',       get_option('sgc_iip_bank_swift','') ),
                'address'     => get_option( 'sgc_iip_export_bank_address',     '' ),
                'currency'    => 'USD',
            ] );
        }
    }

    public static function ajax_export_inquiries_csv() {
        if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( $_GET['nonce'] ?? '', 'sgc_iip_nonce' ) ) wp_die( 'Unauthorized' );
        global $wpdb;
        $t    = SGC_IIP_DB::t('inquiries');
        $rows = $wpdb->get_results( "SELECT * FROM `$t` ORDER BY created_at DESC", ARRAY_A ); // phpcs:ignore

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="sgc-inquiries-' . date('Y-m-d') . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output','w');
        fputcsv( $out, ['ID','INQ Number','Name','Email','Phone','Company','Country','Product','Status','PI Number','Source','Health','Created At'] );
        foreach ( $rows as $r ) fputcsv( $out, [
            $r['id'], $r['inq_number']??'', $r['name'], $r['email'], $r['phone'],
            $r['company'], $r['country'], $r['product']??'', $r['status'],
            $r['pi_number']??'', $r['source_type']??'', $r['health_score']??0, $r['created_at'],
        ] );
        fclose( $out );
        exit;
    }

    public static function ajax_wc_product_search() {
        self::verify();
        $query        = sanitize_text_field( wp_unslash( $_POST['query']        ?? '' ) );
        $buyer_country = sanitize_text_field( wp_unslash( $_POST['buyer_country'] ?? '' ) ); // v1.8.5

        $args = [
            'post_type'      => 'product',
            'posts_per_page' => 20,
            'post_status'    => 'publish',
            'fields'         => 'ids', // v1.9.3: handlers below expect integer IDs (was WP_Post → TypeError)
        ];
        if ( $query ) $args['s'] = $query;
        $q = new WP_Query( $args );

        // USD rate — prefer Product Manager Pro rate
        $usd_rate = floatval( get_option( 'sgc_pm_usd_rate', 0 ) )
                 ?: floatval( get_option( 'sgc_iip_usd_rate', 83.5 ) );

        $results = [];
        foreach ( $q->posts as $pid ) {
            $prod = wc_get_product( $pid );
            if ( ! $prod ) continue;

            $price_inr  = floatval( $prod->get_price() );
            $export_moq = get_post_meta( $pid, '_export_moq', true )
                       ?: get_option( 'sgc_pm_default_moq', 10 );

            // v1.8.5: Country-wise pricing — region-specific USD price
            $pricing_info  = class_exists( 'SGC_IIP_Country_Pricing' )
                ? SGC_IIP_Country_Pricing::get_price_info( $pid, $buyer_country, $usd_rate )
                : null;

            $region_price_usd = $pricing_info ? $pricing_info['price_usd'] : 0;
            $region           = $pricing_info ? $pricing_info['region']     : 'other';
            $price_source     = $pricing_info ? $pricing_info['price_source'] : 'regular_rate';

            // Fallback B2B calculation (when country pricing engine unavailable)
            $dealer_disc   = floatval( get_option( 'sgc_pm_dealer_discount', 20 ) );
            $b2b_price_inr = $price_inr * ( 1 - $dealer_disc / 100 );
            $b2b_price_usd = $usd_rate > 0 ? round( $b2b_price_inr / $usd_rate, 2 ) : 0;

            // HSN code — v1.8.5 uses dedicated _sgc_hsn_code meta
            $hsn  = get_post_meta( $pid, '_sgc_hsn_code', true )
                 ?: get_post_meta( $pid, '_hsn_code', true )
                 ?: get_option( 'sgc_iip_default_hsn', '701720' );
            $unit = get_post_meta( $pid, '_sgc_unit', true ) ?: 'pcs';

            $results[] = [
                'id'              => $pid,
                'name'            => $prod->get_name(),
                'sku'             => $prod->get_sku(),
                'hsn'             => $hsn,
                'unit'            => $unit,
                'price'           => $price_inr,
                'price_usd'       => $usd_rate > 0 ? round( $price_inr / $usd_rate, 2 ) : 0,
                'b2b_price_inr'   => round( $b2b_price_inr, 2 ),
                'b2b_price_usd'   => $b2b_price_usd,
                // v1.8.5: Region-aware price
                'region_price_usd'=> $region_price_usd ?: $b2b_price_usd,
                'region'          => $region,
                'price_source'    => $price_source,
                'has_region_rate' => $pricing_info ? $pricing_info['has_region_rate'] : false,
                'moq'             => (int) $export_moq,
                'stock'           => $prod->get_stock_status(),
                'categories'      => implode( ', ', wp_get_post_terms( $pid, 'product_cat', [ 'fields' => 'names' ] ) ),
            ];
        }
        wp_send_json_success( $results );
    }

    public static function ajax_get_wc_orders() {
        self::verify();
        if ( ! function_exists('wc_get_orders') ) { wp_send_json_error('WooCommerce not active.'); return; }

        $args = [
            'status'  => [ 'wc-pending','wc-processing','wc-on-hold','wc-completed' ],
            'limit'   => 50,
            'orderby' => 'date',
            'order'   => 'DESC',
        ];
        $orders = wc_get_orders($args);

        // Also get WC inquiries (from WooCommerce Product Inquiries or similar)
        global $wpdb;
        $rows = [];

        // Check if already synced
        $iip_table = SGC_IIP_DB::t('inquiries');

        foreach ($orders as $order) {
            $oid      = $order->get_id();
            $exists   = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM `{$iip_table}` WHERE source_type='woocommerce' AND source_id=%d LIMIT 1",
                $oid
            ) );

            $items_str = implode(', ', array_map(function($item){
                return $item->get_name().' x'.$item->get_quantity();
            }, $order->get_items()));

            $rows[] = [
                'id'             => $oid,
                'order_number'   => $order->get_order_number(),
                'name'           => $order->get_billing_first_name().' '.$order->get_billing_last_name(),
                'email'          => $order->get_billing_email(),
                'phone'          => $order->get_billing_phone(),
                'company'        => $order->get_billing_company(),
                'country'        => $order->get_billing_country(),
                'products'       => $items_str,
                'total'          => $order->get_total(),
                'currency'       => $order->get_currency(),
                'status'         => $order->get_status(),
                'date'           => $order->get_date_created()->format('Y-m-d'),
                'already_synced' => !empty($exists),
                'iip_id'         => $exists ?: null,
            ];
        }

        wp_send_json_success(['orders' => $rows, 'total' => count($rows)]);
    }

    public static function ajax_sync_from_wc() {
        self::verify();
        if ( ! function_exists('wc_get_order') ) { wp_send_json_error('WooCommerce not active.'); return; }

        global $wpdb;
        $mode    = sanitize_key($_POST['mode'] ?? 'selected');
        $ids_raw = $_POST['ids'] ?? ($_POST['ids[]'] ?? []);
        if (is_string($ids_raw)) $ids_raw = explode(',', $ids_raw);

        $iip_table = SGC_IIP_DB::t('inquiries');

        if ('all' === $mode) {
            $orders = wc_get_orders(['limit'=>200,'orderby'=>'date','order'=>'DESC']);
        } else {
            if (empty($ids_raw)) { wp_send_json_error('No IDs provided.'); return; }
            $orders = array_filter(array_map('wc_get_order', array_map('intval', $ids_raw)));
        }

        $synced = 0; $skipped = 0;

        foreach ($orders as $order) {
            $oid   = $order->get_id();
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM `{$iip_table}` WHERE source_type='woocommerce' AND source_id=%d LIMIT 1", $oid
            ));
            if ($exists) { $skipped++; continue; }

            // Build product details from order items
            $products = [];
            foreach ($order->get_items() as $item) {
                $products[] = [
                    'name'     => $item->get_name(),
                    'qty'      => $item->get_quantity(),
                    'price'    => $item->get_total(),
                    'product_id'=> $item->get_product_id(),
                ];
            }

            $bname = $order->get_billing_first_name().' '.$order->get_billing_last_name();
            $country_raw = $order->get_billing_country();
            $country_code = $country_raw;
            // Map WC 2-letter code to name
            if (function_exists('WC')) {
                $countries = WC()->countries->get_countries();
                $country_raw = $countries[$country_raw] ?? $country_raw;
            }
            $currency = $order->get_currency() ?: SGC_IIP_Currency::get_for_country($country_code);

            $data = [
                'source_id'      => $oid,
                'source_type'    => 'woocommerce',
                'name'           => $bname,
                'email'          => $order->get_billing_email(),
                'phone'          => $order->get_billing_phone(),
                'company'        => $order->get_billing_company(),
                'country'        => $country_raw,
                'country_code'   => $country_code,
                'currency'       => $currency,
                'raw_message'    => 'WooCommerce Order #'.$order->get_order_number().' | Status: '.$order->get_status(),
                'product_details'=> wp_json_encode($products),
                'status'         => 'new',
                'health_score'   => 75, // WC orders are high quality leads
                'pi_accept_token'=> wp_generate_password(32, false),
                'created_at'     => $order->get_date_created()->format('Y-m-d H:i:s'),
            ];

            $wpdb->insert($iip_table, $data);
            $iip_id = $wpdb->insert_id;

            if ($iip_id) {
                SGC_IIP_DB::log($iip_id,'wc_imported',
                    'Synced from WooCommerce Order #'.$order->get_order_number(),'system');
                $synced++;
            }
        }

        wp_send_json_success([
            'synced'  => $synced,
            'skipped' => $skipped,
            'message' => "✅ {$synced} WC orders imported, {$skipped} skipped.",
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // v1.2.0: PAYMENT TRACKING HANDLERS
    // ═══════════════════════════════════════════════════════════

    public static function ajax_add_payment() {
        self::verify();
        global $wpdb;

        $inquiry_id  = (int) ( $_POST['inquiry_id']  ?? 0 );
        $pi_id       = (int) ( $_POST['pi_id']       ?? 0 ) ?: null;
        $type        = sanitize_key( $_POST['payment_type'] ?? 'advance' );
        $amount      = round( floatval( $_POST['amount'] ?? 0 ), 2 );
        $currency    = strtoupper( sanitize_text_field( $_POST['currency'] ?? 'USD' ) );
        $method      = sanitize_key( $_POST['method'] ?? 'bank_tt' );
        $reference   = sanitize_text_field( wp_unslash( $_POST['reference_no'] ?? '' ) );
        $pay_date    = sanitize_text_field( $_POST['payment_date'] ?? current_time('Y-m-d') );
        $notes       = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );

        $allowed_types   = [ 'advance','balance','full','sample','refund','partial' ];
        $allowed_methods = [ 'bank_tt','lc','paypal','wise','upi','cash','cheque' ];
        if ( ! $inquiry_id || ! $amount || ! in_array( $type, $allowed_types, true ) ) {
            wp_send_json_error( 'Invalid payment data.' ); return;
        }
        if ( ! in_array( $method, $allowed_methods, true ) ) $method = 'bank_tt';

        // Calculate INR equivalent
        $amount_inr = null;
        if ( $currency === 'INR' ) {
            $amount_inr = $amount;
        } else {
            $rate = SGC_IIP_DB::get_currency_rate_inr( $currency );
            if ( $rate > 0 ) $amount_inr = round( $amount * $rate, 2 );
        }

        $wpdb->insert( SGC_IIP_DB::t('payments'), [ // phpcs:ignore
            'inquiry_id'   => $inquiry_id,
            'pi_id'        => $pi_id,
            'payment_type' => $type,
            'amount'       => $amount,
            'currency'     => $currency,
            'amount_inr'   => $amount_inr,
            'method'       => $method,
            'reference_no' => $reference,
            'payment_date' => $pay_date,
            'notes'        => $notes,
            'recorded_by'  => wp_get_current_user()->user_login ?: 'admin',
        ] );

        $pay_id = $wpdb->insert_id;

        // Auto-update inquiry status based on payment type
        $status_map = [
            'advance' => 'advance',
            'full'    => 'won',
            'balance' => 'won',
        ];
        if ( isset( $status_map[ $type ] ) ) {
            $wpdb->update( SGC_IIP_DB::t('inquiries'), // phpcs:ignore
                [ 'status' => $status_map[$type], 'pipeline_stage' => $status_map[$type] ],
                [ 'id' => $inquiry_id ]
            );
        }

        SGC_IIP_DB::log( $inquiry_id, 'payment_added',
            ucfirst($type) . ' ' . $currency . ' ' . $amount . ' via ' . strtoupper($method) . ( $reference ? ' (Ref: '.$reference.')' : '' ),
            'admin', [ 'pay_id' => $pay_id ]
        );

        wp_send_json_success( [
            'id'      => $pay_id,
            'message' => '✅ Payment recorded successfully.',
            'total'   => SGC_IIP_DB::total_paid( $inquiry_id ),
        ] );
    }

    public static function ajax_get_payments() {
        self::verify();
        $inquiry_id = (int) ( $_POST['inquiry_id'] ?? 0 );
        if ( ! $inquiry_id ) { wp_send_json_error('Invalid ID'); return; }

        $payments = SGC_IIP_DB::get_payments( $inquiry_id );
        $total    = SGC_IIP_DB::total_paid( $inquiry_id );

        wp_send_json_success( [ 'payments' => $payments, 'total_usd' => $total ] );
    }

    public static function ajax_delete_payment() {
        self::verify();
        global $wpdb;
        $pay_id     = (int) ( $_POST['pay_id']     ?? 0 );
        $inquiry_id = (int) ( $_POST['inquiry_id'] ?? 0 );
        if ( ! $pay_id ) { wp_send_json_error('Invalid ID'); return; }

        $wpdb->delete( SGC_IIP_DB::t('payments'), [ 'id' => $pay_id ] ); // phpcs:ignore
        SGC_IIP_DB::log( $inquiry_id, 'payment_deleted', 'Payment #'.$pay_id.' deleted', 'admin' );

        wp_send_json_success( [
            'message' => 'Payment deleted.',
            'total'   => SGC_IIP_DB::total_paid( $inquiry_id ),
        ] );
    }

    // ═══════════════════════════════════════════════════════════
    // v1.2.0: KANBAN PIPELINE — stage move + quick actions
    // ═══════════════════════════════════════════════════════════

    public static function ajax_move_stage() {
        self::verify();
        global $wpdb;
        $id    = (int) sanitize_key( $_POST['id']    ?? 0 );
        $stage = sanitize_key( $_POST['stage'] ?? '' );
        $allowed = [ 'new','replied','pi_sent','accepted','advance','won','lost','sample' ];
        if ( ! $id || ! in_array( $stage, $allowed, true ) ) {
            wp_send_json_error( 'Invalid.' ); return;
        }
        $wpdb->update( SGC_IIP_DB::t('inquiries'), // phpcs:ignore
            [ 'status' => $stage, 'pipeline_stage' => $stage, 'updated_at' => current_time('mysql') ],
            [ 'id' => $id ]
        );
        SGC_IIP_DB::log( $id, 'stage_moved', 'Pipeline → ' . $stage, 'admin' );

        // v1.2.1: Fire deal_won hook on drag-to-won
        if ( $stage === 'won' ) {
            $inq = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . SGC_IIP_DB::t('inquiries') . " WHERE id=%d", $id ), ARRAY_A ); // phpcs:ignore
            if ( $inq ) do_action( 'sgc_iip_deal_won', $inq );
        }

        wp_send_json_success( [ 'stage' => $stage ] );
    }

    public static function ajax_quick_action() {
        self::verify();
        $id     = (int) sanitize_key( $_POST['id']     ?? 0 );
        $action = sanitize_key( $_POST['quick_action'] ?? '' );
        if ( ! $id ) { wp_send_json_error('Invalid ID'); return; }

        global $wpdb;
        $inq = $wpdb->get_row( // phpcs:ignore
            $wpdb->prepare( "SELECT * FROM " . SGC_IIP_DB::t('inquiries') . " WHERE id=%d", $id ),
            ARRAY_A
        );
        if ( ! $inq ) { wp_send_json_error('Inquiry not found'); return; }

        switch ( $action ) {
            case 'send_autoreply':
                SGC_IIP_Email::send_autoreply( $inq );
                SGC_IIP_DB::log( $id, 'autoreply_sent', 'Quick action: autoreply sent', 'admin' );
                wp_send_json_success( '✉ Autoreply sent to ' . $inq['email'] );
                break;

            case 'mark_won':
                $wpdb->update( SGC_IIP_DB::t('inquiries'), // phpcs:ignore
                    [ 'status' => 'won', 'pipeline_stage' => 'won' ], [ 'id' => $id ]
                );
                SGC_IIP_DB::log( $id, 'marked_won', 'Quick action: marked Won', 'admin' );
                // v1.2.1: Fire deal_won hook
                do_action( 'sgc_iip_deal_won', $inq );
                wp_send_json_success( '🏆 Marked as Won' );
                break;

            case 'mark_lost':
                $wpdb->update( SGC_IIP_DB::t('inquiries'), // phpcs:ignore
                    [ 'status' => 'lost', 'pipeline_stage' => 'lost' ], [ 'id' => $id ]
                );
                SGC_IIP_DB::log( $id, 'marked_lost', 'Quick action: marked Lost', 'admin' );
                wp_send_json_success( '❌ Marked as Lost' );
                break;

            case 'send_wa':
                if ( class_exists('SGC_IIP_WhatsApp') ) {
                    SGC_IIP_WhatsApp::send_followup( $inq, 'Hi ' . $inq['name'] . ', following up on your inquiry. Please let us know if you have any questions.' );
                    SGC_IIP_DB::log( $id, 'wa_sent', 'Quick action: WhatsApp followup sent', 'admin' );
                    wp_send_json_success( '💬 WhatsApp sent' );
                } else {
                    wp_send_json_error( 'WhatsApp not configured' );
                }
                break;

            default:
                wp_send_json_error( 'Unknown action' );
        }
    }


    // ── Smart Extract — AI extracts products from text ────────────────────
    public static function ajax_smart_extract(): void {
        self::verify();

        $text = sanitize_textarea_field( wp_unslash( $_POST['text'] ?? '' ) );
        if ( empty( $text ) ) { wp_send_json_error( 'Text required.' ); return; }

        $system = 'You are a lab glassware product extraction AI. Extract product names and quantities. Return ONLY a JSON array, no markdown, no explanation.';

        $prompt = "Extract all products with quantities from this buyer inquiry message:\n\n"
            . $text
            . "\n\nReturn ONLY this JSON array format:\n"
            . '[{"name":"1.5 Ltr Beaker Borosilicate 3.3","qty":100,"unit":"pcs","rate":""},{"name":"Round Bottom Flask 500ml","qty":50,"unit":"pcs","rate":""}]'
            . "\nRules: qty=1 if not mentioned, rate always empty, proper lab glassware names.";

        $result = '';
        if ( function_exists('sgclabs_ai_call') ) {
            $result = sgclabs_ai_call( $prompt, 600, $system );
        } elseif ( method_exists('SGC_IIP_AI','call') ) {
            $result = SGC_IIP_AI::call( $prompt, $system, 600 );
        } else {
            wp_send_json_error( 'AI not configured.' ); return;
        }

        if ( is_wp_error($result) || empty($result) ) { wp_send_json_error('AI error.'); return; }

        $clean = preg_replace('/```(?:json)?|```/', '', $result);
        if ( preg_match('/\[.*\]/s', $clean, $m) ) $clean = $m[0];
        $items = json_decode( trim($clean), true );

        if ( !is_array($items) || empty($items) ) { wp_send_json_error('Parse failed. Try simpler text.'); return; }

        $safe = [];
        foreach ( $items as $item ) {
            if ( empty($item['name']) ) continue;
            $safe[] = [
                'name' => sanitize_text_field($item['name']),
                'qty'  => intval($item['qty'] ?? 1),
                'unit' => sanitize_text_field($item['unit'] ?? 'pcs'),
                'rate' => '',
            ];
        }
        wp_send_json_success( ['items' => $safe] );
    }

    // ── Save Extracted Products ───────────────────────────────────────────
    public static function ajax_save_extracted_products(): void {
        self::verify();
        global $wpdb;

        $inq_id   = intval( $_POST['inq_id'] ?? 0 );
        $products = sanitize_textarea_field( wp_unslash( $_POST['products'] ?? '' ) );
        if ( !$inq_id ) { wp_send_json_error('Invalid ID.'); return; }

        $items = json_decode( $products, true );
        if ( !is_array($items) ) { wp_send_json_error('Invalid data.'); return; }

        $safe = [];
        foreach ( $items as $item ) {
            if ( empty($item['name']) ) continue;
            $safe[] = [
                'name' => sanitize_text_field($item['name']),
                'qty'  => sanitize_text_field($item['qty']  ?? '1'),
                'unit' => sanitize_text_field($item['unit'] ?? 'pcs'),
                'rate' => sanitize_text_field($item['rate'] ?? ''),
                'sku'  => sanitize_text_field($item['sku']  ?? ''),
                'hsn'  => sanitize_text_field($item['hsn']  ?? '7017.20'),
            ];
        }

        $wpdb->update( SGC_IIP_DB::t('inquiries'), ['product_details' => wp_json_encode($safe)], ['id' => $inq_id] ); // phpcs:ignore
        SGC_IIP_DB::log( $inq_id, 'smart_extract_saved', count($safe).' products saved', 'admin' );
        wp_send_json_success( ['saved' => count($safe)] );
    }

    // ── AJAX: Delete inquiry (soft delete → trash) ────────────────────────────
    public static function ajax_delete_inquiry(): void {
        self::verify();
        global $wpdb;
        $id = (int) ( $_POST['id'] ?? 0 );
        if ( ! $id ) { wp_send_json_error( 'Invalid ID.' ); return; }
        $wpdb->update( // phpcs:ignore
            SGC_IIP_DB::t('inquiries'),
            [ 'status' => 'deleted', 'admin_note' => 'Deleted by admin on ' . current_time('mysql') ],
            [ 'id' => $id ]
        );
        SGC_IIP_DB::log( $id, 'inquiry_deleted', 'Inquiry moved to trash', 'admin' );
        wp_send_json_success( 'Inquiry deleted.' );
    }

    // ── AJAX: Update inquiry basic fields ────────────────────────────────────
    // v1.8.0: Allows admin to correct buyer name, email, phone, company, country.
    public static function ajax_update_inquiry(): void {
        self::verify();
        global $wpdb;

        $id = (int) ( $_POST['id'] ?? 0 );
        if ( $id <= 0 ) { wp_send_json_error( 'Invalid inquiry ID.' ); return; }

        // Validate row exists
        $existing = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT id FROM " . SGC_IIP_DB::t('inquiries') . " WHERE id = %d AND status != 'deleted' LIMIT 1",
            $id
        ) );
        if ( ! $existing ) { wp_send_json_error( 'Inquiry not found.' ); return; }

        // Sanitize every field individually — never trust POST
        $name    = sanitize_text_field( wp_unslash( $_POST['name']    ?? '' ) );
        $email   = sanitize_email(      wp_unslash( $_POST['email']   ?? '' ) );
        $phone   = sanitize_text_field( wp_unslash( $_POST['phone']   ?? '' ) );
        $company = sanitize_text_field( wp_unslash( $_POST['company'] ?? '' ) );
        $country = sanitize_text_field( wp_unslash( $_POST['country'] ?? '' ) );

        // Name and email are required
        if ( empty( $name ) )              { wp_send_json_error( 'Name is required.' );           return; }
        if ( ! empty( $email ) && ! is_email( $email ) ) {
            wp_send_json_error( 'Invalid email address.' );
            return;
        }

        $data    = array_filter( [
            'name'    => $name,
            'email'   => $email   ?: null,
            'phone'   => $phone   ?: null,
            'company' => $company ?: null,
            'country' => $country ?: null,
        ], fn( $v ) => $v !== null );

        $formats = [];
        foreach ( $data as $key => $val ) {
            $formats[] = '%s';
        }

        $updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            SGC_IIP_DB::t('inquiries'),
            $data,
            [ 'id' => $id ],
            $formats,
            [ '%d' ]
        );

        if ( $updated === false ) {
            error_log( '[SGC-IIP] ajax_update_inquiry: DB update failed for ID ' . $id );
            wp_send_json_error( 'Database error — please try again.' );
            return;
        }

        SGC_IIP_DB::log( $id, 'inquiry_edited', 'Fields updated by admin: ' . implode( ', ', array_keys( $data ) ), 'admin' );

        wp_send_json_success( [
            'message' => 'Inquiry updated.',
            'data'    => $data,
        ] );
    }

    // ── AJAX: Get single PI by ID (status check for delete/cancel flow) ─────
    // v1.8.0: Used by deletePI() JS to decide whether cancel modal is needed.
    public static function ajax_pi_get(): void {
        self::verify();
        global $wpdb;

        $pi_id = (int) ( $_POST['pi_id'] ?? 0 );
        if ( $pi_id <= 0 ) { wp_send_json_error( 'Invalid PI ID.' ); return; }

        $pi = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT id, pi_number, status, buyer_name, buyer_email, revision
               FROM " . SGC_IIP_DB::t('proformas') . "
              WHERE id = %d LIMIT 1",
            $pi_id
        ), ARRAY_A );

        if ( ! $pi ) { wp_send_json_error( 'PI not found.' ); return; }

        wp_send_json_success( $pi );
    }

    // ── AJAX: Delete / cancel PI ─────────────────────────────────────────────
    // v1.8.0: Sends cancellation email to buyer when PI was already sent/accepted.
    //         Accepts optional `reason` POST param for buyer notification.
    public static function ajax_delete_pi(): void {
        self::verify();
        global $wpdb;

        $pi_id = (int) ( $_POST['pi_id'] ?? 0 );
        if ( $pi_id <= 0 ) { wp_send_json_error( 'Invalid PI ID.' ); return; }

        // Sanitize reason — strip tags, trim whitespace
        $reason = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );

        $pi = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT * FROM " . SGC_IIP_DB::t('proformas') . " WHERE id = %d LIMIT 1",
            $pi_id
        ), ARRAY_A );

        if ( ! $pi ) { wp_send_json_error( 'PI not found.' ); return; }

        // Mark cancelled
        $updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            SGC_IIP_DB::t('proformas'),
            [ 'status' => 'cancelled' ],
            [ 'id'     => $pi_id ],
            [ '%s' ],
            [ '%d' ]
        );

        if ( $updated === false ) {
            error_log( '[SGC-IIP] ajax_delete_pi: DB update failed for PI ID ' . $pi_id );
            wp_send_json_error( 'Database error — please try again.' );
            return;
        }

        $log_note = 'PI ' . $pi['pi_number'] . ' cancelled by admin';
        if ( $reason !== '' ) $log_note .= ' — Reason: ' . $reason;
        SGC_IIP_DB::log( (int) $pi['inquiry_id'], 'pi_cancelled', $log_note, 'admin' );

        // Send cancellation email only if PI reached the buyer
        $email_sent  = false;
        $notify_statuses = [ 'sent', 'accepted', 'advance' ];

        if ( in_array( $pi['status'], $notify_statuses, true ) ) {
            $inq = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                "SELECT * FROM " . SGC_IIP_DB::t('inquiries') . " WHERE id = %d LIMIT 1",
                (int) $pi['inquiry_id']
            ), ARRAY_A );

            if ( $inq ) {
                try {
                    $email_sent = SGC_IIP_Email::send_pi_cancellation( $pi, $inq, $reason );
                } catch ( \Throwable $e ) {
                    error_log( '[SGC-IIP] send_pi_cancellation exception: ' . $e->getMessage() );
                }
            }
        }

        wp_send_json_success( [
            'message'    => 'PI cancelled.',
            'email_sent' => $email_sent,
        ] );
    }

    // ── AJAX: Resend existing PI email ────────────────────────────────────────
    public static function ajax_resend_pi(): void {
        self::verify();
        global $wpdb;
        $pi_id = (int) ( $_POST['pi_id'] ?? 0 );
        if ( ! $pi_id ) { wp_send_json_error( 'Invalid PI ID.' ); return; }
        $pi = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore
            "SELECT * FROM " . SGC_IIP_DB::t('proformas') . " WHERE id=%d", $pi_id
        ), ARRAY_A );
        if ( ! $pi ) { wp_send_json_error( 'PI not found.' ); return; }
        $inq = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore
            "SELECT * FROM " . SGC_IIP_DB::t('inquiries') . " WHERE id=%d", $pi['inquiry_id']
        ), ARRAY_A );
        if ( ! $inq ) { wp_send_json_error( 'Inquiry not found.' ); return; }
        // Regenerate PDF
        $pi_data = $pi;
        $pi_data['line_items'] = json_decode( $pi['line_items'] ?? '[]', true ) ?: [];
        $pdf_path = SGC_IIP_PDF::generate_pi( $pi_data );
        if ( is_wp_error( $pdf_path ) ) { wp_send_json_error( 'PDF generation failed.' ); return; }
        $token      = $pi['accept_token'] ?? wp_generate_password( 32, false );
        $accept_url = home_url( '/sgc-pi-accept/' . $pi['pi_number'] . '/' . $token );
        $sent = SGC_IIP_Email::send_pi( $inq, $pi, $pdf_path, $accept_url );
        SGC_IIP_DB::log( $pi['inquiry_id'], 'pi_resent', 'PI '.$pi['pi_number'].' resent to '.$inq['email'], 'admin' );
        if ( $sent ) {
            $wpdb->update( SGC_IIP_DB::t('proformas'), [ 'status' => 'sent' ], [ 'id' => $pi_id ] ); // phpcs:ignore
            wp_send_json_success( 'PI email resent to ' . $inq['email'] );
        } else {
            wp_send_json_error( 'Email send failed — check SMTP settings.' );
        }
    }


    // ═══════════════════════════════════════════════════════════════════════
    // v1.7.0: GENERATE PI DRAFT — called from conveyor panel Step 4
    // Saves/updates a draft PI from live product table without sending email.
    // ═══════════════════════════════════════════════════════════════════════
    public static function ajax_generate_pi_draft() {
        self::verify();
        global $wpdb;

        $inquiry_id  = (int) ( $_POST['inquiry_id']  ?? 0 );
        $buyer_type  = sanitize_key( $_POST['buyer_type']  ?? 'export' );   // 'domestic' | 'export'
        $gst_type    = sanitize_key( $_POST['gst_type']    ?? 'igst' );     // 'igst' | 'cgst_sgst'
        $buyer_gstin = sanitize_text_field( wp_unslash( $_POST['buyer_gstin'] ?? '' ) );

        $raw_products = wp_unslash( $_POST['products'] ?? '[]' );
        $products     = json_decode( is_string( $raw_products ) ? $raw_products : '[]', true );

        if ( ! $inquiry_id ) { wp_send_json_error( 'Inquiry ID required.' ); return; }
        if ( empty( $products ) ) { wp_send_json_error( 'Products list empty.' ); return; }

        $inq = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore
            'SELECT * FROM ' . SGC_IIP_DB::t( 'inquiries' ) . ' WHERE id=%d', $inquiry_id
        ), ARRAY_A );
        if ( ! $inq ) { wp_send_json_error( 'Inquiry not found.' ); return; }

        $is_domestic = ( $buyer_type === 'domestic' );
        $currency    = $is_domestic ? 'INR' : ( $inq['currency'] ?: 'USD' );
        $usd_rate    = floatval( get_option( 'sgc_iip_usd_rate', 83.50 ) );
        $incoterm    = $is_domestic
            ? 'Ex-Works'
            : ( $inq['incoterm'] ?: get_option( 'sgc_iip_default_incoterm', 'FOB' ) );
        $port        = $is_domestic
            ? get_option( 'sgc_iip_default_dom_port', 'Ambala, India' )
            : ( $inq['port'] ?: get_option( 'sgc_iip_default_port', 'New Delhi, India' ) );

        // ── Build line items + subtotal ───────────────────────────────────
        $line_items = [];
        $subtotal   = 0.0;

        foreach ( (array) $products as $p ) {
            $name  = sanitize_text_field( $p['name']  ?? '' );
            if ( ! $name ) continue;
            $qty   = max( 1, (int) preg_replace( '/[^0-9]/', '', (string) ( $p['qty'] ?? 1 ) ) );
            $rate  = round( floatval( $p['rate'] ?? 0 ), 4 );
            $total = round( $qty * $rate, 2 );

            $line_items[] = [
                'product_name' => $name,
                'sku'          => sanitize_text_field( $p['sku']  ?? '' ),
                'hs_code'      => sanitize_text_field( $p['hsn']  ?? '70179090' ),
                'qty'          => $qty,
                'unit'         => sanitize_text_field( $p['unit'] ?? 'pcs' ),
                'unit_price'   => $rate,
                'total'        => $total,
                'specs'        => 'Borosilicate 3.3',
            ];
            $subtotal += $total;
        }
        $subtotal = round( $subtotal, 2 );

        if ( empty( $line_items ) ) { wp_send_json_error( 'No valid products with names.' ); return; }

        // ── GST (domestic only) — use rate from frontend so "jo dikhaye wahi save ho" ─
        $gst_percent = 0.0;
        $gst_amount  = 0.0;
        $cgst        = 0.0;
        $sgst        = 0.0;
        $igst        = 0.0;
        if ( $is_domestic ) {
            // Read GST % from frontend (what user sees on screen); fallback to 18%
            $gst_percent_raw = floatval( $_POST['gst_percent'] ?? 18 );
            $gst_percent     = ( $gst_percent_raw >= 0 && $gst_percent_raw <= 28 ) ? $gst_percent_raw : 18.0;
            $gst_amount      = round( $subtotal * $gst_percent / 100, 2 );
            if ( $gst_type === 'cgst_sgst' ) {
                $cgst = round( $subtotal * ( $gst_percent / 2 ) / 100, 2 );
                $sgst = $cgst;
            } else {
                $igst = $gst_amount;
            }
        }
        $grand_total     = round( $subtotal + $gst_amount, 2 );
        $grand_total_inr = $is_domestic ? $grand_total : round( $grand_total * $usd_rate, 2 );
        $pay_terms       = SGC_IIP_Proforma::get_payment_terms( $is_domestic ? ( $grand_total / max( 1, $usd_rate ) ) : $grand_total );
        $delivery        = get_option( 'sgc_iip_default_delivery', '15-20 working days' );
        $valid_until     = date( 'Y-m-d', strtotime( '+' . (int) get_option( 'sgc_iip_pi_validity', 30 ) . ' days' ) );

        // ── Find latest PI for this inquiry (by inquiry_id first, then by stored pi_number) ──
        $existing = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            'SELECT * FROM ' . SGC_IIP_DB::t( 'proformas' ) . ' WHERE inquiry_id=%d ORDER BY id DESC LIMIT 1',
            $inquiry_id
        ), ARRAY_A );

        // v1.8.2 fallback: if PI wasn't linked by inquiry_id, find it via inquiry's stored pi_number
        // This fixes "DB insert failed" when PI exists but inquiry_id wasn't set
        if ( ! $existing && ! empty( $inq['pi_number'] ) ) {
            $existing = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                'SELECT * FROM ' . SGC_IIP_DB::t( 'proformas' ) . ' WHERE pi_number=%s ORDER BY id DESC LIMIT 1',
                $inq['pi_number']
            ), ARRAY_A );
            if ( $existing ) {
                // Repair the link so future lookups work
                $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    SGC_IIP_DB::t( 'proformas' ),
                    [ 'inquiry_id' => $inquiry_id ],
                    [ 'id'         => (int) $existing['id'] ],
                    [ '%d' ],
                    [ '%d' ]
                );
                error_log( '[SGC-IIP] Repaired inquiry_id link for PI ' . $inq['pi_number'] . ' (inquiry #' . $inquiry_id . ')' );
            }
        }

        $update_data = [
            'currency'        => $currency,
            'inr_rate'        => $usd_rate,
            'line_items'      => wp_json_encode( $line_items ),
            'subtotal'        => $subtotal,
            'packaging_total' => 0,
            'oem_charge'      => 0,
            'gst_percent'     => $gst_percent,
            'gst_amount'      => $gst_amount,
            'tax_amount'      => 0,
            'discount'        => 0,
            'grand_total'     => $grand_total,
            'grand_total_inr' => $grand_total_inr,
            'incoterm'        => $incoterm,
            'port'            => $port,
            'payment_terms'   => $pay_terms,
            'delivery_days'   => $delivery,
            'valid_until'     => $valid_until,
            'buyer_name'      => $inq['name'],
            'buyer_email'     => $inq['email'],
            'buyer_phone'     => $inq['phone'] ?? '',
            'buyer_company'   => $inq['company'] ?? '',
            'buyer_country'   => $inq['country'] ?? '',
            'buyer_gstin'     => $buyer_gstin,
            'pi_type'         => $is_domestic ? 'domestic' : 'standard',
            'pdf_path'        => null, // clear stale PDF
        ];

        if ( $existing ) {
            // ── UPDATE existing draft ─────────────────────────────────────
            $pi_id     = (int) $existing['id'];
            $pi_number = $existing['pi_number'];
            $result    = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                SGC_IIP_DB::t( 'proformas' ),
                $update_data,
                [ 'id' => $pi_id ],
                null,  // $wpdb infers formats from values
                [ '%d' ]
            );
            if ( $result === false ) {
                $db_err = $wpdb->last_error ? ': ' . $wpdb->last_error : '.';
                error_log( '[SGC-IIP] PI update failed for PI #' . $pi_id . $db_err );
                wp_send_json_error( 'DB update failed' . $db_err . ' Check error log.' );
                return;
            }
            SGC_IIP_DB::log( $inquiry_id, 'pi_draft_updated', 'PI draft ' . $pi_number . ' updated from panel', 'admin' );
        } else {
            // ── CREATE new draft ──────────────────────────────────────────
            $pi_number = SGC_IIP_DB::generate_pi_number( 'standard' );
            $token     = wp_generate_password( 32, false );

            $insert = array_merge( $update_data, [
                'inquiry_id'   => $inquiry_id,
                'pi_number'    => $pi_number,
                'revision'     => 1,
                'template'     => get_option( 'sgc_iip_default_template', 'navy' ),
                'validity_days'=> (int) get_option( 'sgc_iip_pi_validity', 30 ),
                'notes'        => '',
                'status'       => 'draft',
                'accept_token' => $token,
            ] );

            $wpdb->insert( SGC_IIP_DB::t( 'proformas' ), $insert ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $pi_id = (int) $wpdb->insert_id;

            if ( ! $pi_id ) {
                $db_err = $wpdb->last_error ? ' — ' . $wpdb->last_error : '';
                error_log( '[SGC-IIP] PI insert failed for inquiry #' . $inquiry_id . $db_err );
                wp_send_json_error( 'DB insert failed' . ( $db_err ? ': ' . $db_err : '. Check error log.' ) );
                return;
            }

            $wpdb->update( SGC_IIP_DB::t( 'inquiries' ), [ 'pi_number' => $pi_number ], [ 'id' => $inquiry_id ] ); // phpcs:ignore
            SGC_IIP_DB::log( $inquiry_id, 'pi_draft_created', 'PI draft ' . $pi_number . ' created from panel', 'admin' );
        }

        wp_send_json_success( [
            'pi_id'       => $pi_id,
            'pi_number'   => $pi_number,
            'subtotal'    => $subtotal,
            'gst_amount'  => $gst_amount,
            'grand_total' => $grand_total,
            'currency'    => $currency,
            'is_domestic' => $is_domestic,
            'gst_type'    => $gst_type,
            'cgst'        => $cgst,
            'sgst'        => $sgst,
            'igst'        => $igst,
        ] );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // v1.7.0: SAVE FOLLOW-UP — DB save + WP-Cron reminder on due date
    // ═══════════════════════════════════════════════════════════════════════
    public static function ajax_save_followup() {
        self::verify();
        global $wpdb;

        $inquiry_id    = (int) ( $_POST['inquiry_id']    ?? 0 );
        $followup_date = sanitize_text_field( wp_unslash( $_POST['followup_date'] ?? '' ) );
        $channel       = sanitize_key( $_POST['channel'] ?? 'email' );
        $note          = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );

        if ( ! $inquiry_id )    { wp_send_json_error( 'Inquiry ID required.' ); return; }
        if ( ! $followup_date ) { wp_send_json_error( 'Follow-up date required.' ); return; }

        $ts = strtotime( $followup_date );
        if ( ! $ts ) { wp_send_json_error( 'Invalid date format.' ); return; }

        // ── Lazy-create followups table ───────────────────────────────────
        $tbl = $wpdb->prefix . 'sgc_iip_followups';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tbl}'" ) !== $tbl ) { // phpcs:ignore
            $wpdb->query( "CREATE TABLE `{$tbl}` (
                id          bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                inquiry_id  bigint(20) UNSIGNED NOT NULL,
                due_date    date                NOT NULL,
                channel     varchar(20)         NOT NULL DEFAULT 'email',
                note        text,
                status      varchar(20)         NOT NULL DEFAULT 'pending',
                created_at  datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY inq_idx (inquiry_id),
                KEY due_idx (due_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;" ); // phpcs:ignore
        }

        // ── Save to DB ────────────────────────────────────────────────────
        $wpdb->insert( $tbl, [ // phpcs:ignore
            'inquiry_id' => $inquiry_id,
            'due_date'   => $followup_date,
            'channel'    => $channel,
            'note'       => $note,
            'status'     => 'pending',
        ] );

        // ── Schedule WP-Cron at 09:00 AM on due date ─────────────────────
        $remind_at = strtotime( $followup_date . ' 09:00:00' );
        if ( $remind_at > time() ) {
            wp_schedule_single_event( $remind_at, 'sgc_iip_followup_reminder', [ $inquiry_id, $channel, $note ] );
        }

        SGC_IIP_DB::log( $inquiry_id, 'followup_scheduled',
            'Follow-up: ' . $followup_date . ' via ' . $channel . ( $note ? ' — ' . $note : '' ), 'admin' );

        wp_send_json_success( [ 'date' => $followup_date, 'channel' => $channel ] );
    }

    // ── WP-Cron callback: admin ko email reminder ─────────────────────────
    public static function send_followup_reminder( int $inquiry_id, string $channel, string $note ) {
        global $wpdb;

        $inq = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore
            'SELECT * FROM ' . SGC_IIP_DB::t( 'inquiries' ) . ' WHERE id=%d', $inquiry_id
        ), ARRAY_A );

        $admin_email = get_option( 'admin_email' );
        $buyer_name  = $inq['name']  ?? ( 'Inquiry #' . $inquiry_id );
        $buyer_email = $inq['email'] ?? '';
        $pi_num      = $inq['pi_number'] ?? '';

        $subject = '🔔 Follow-up Due: ' . $buyer_name . ( $pi_num ? ' (' . $pi_num . ')' : '' );

        $body = '<div style="font-family:Arial,sans-serif;max-width:500px">'
            . '<h2 style="color:#1a1a2e">Follow-up Reminder</h2>'
            . '<table style="width:100%;font-size:14px;border-collapse:collapse">'
            . '<tr><td style="padding:6px;color:#888;width:120px">Buyer</td><td style="padding:6px;font-weight:700">' . esc_html( $buyer_name ) . '</td></tr>'
            . '<tr><td style="padding:6px;color:#888">Email</td><td style="padding:6px">' . esc_html( $buyer_email ) . '</td></tr>'
            . ( $pi_num ? '<tr><td style="padding:6px;color:#888">PI Number</td><td style="padding:6px;color:#185FA5;font-weight:700">' . esc_html( $pi_num ) . '</td></tr>' : '' )
            . '<tr><td style="padding:6px;color:#888">Channel</td><td style="padding:6px;font-weight:700;color:#0F6E56">' . esc_html( ucfirst( $channel ) ) . '</td></tr>'
            . ( $note ? '<tr><td style="padding:6px;color:#888">Note</td><td style="padding:6px">' . esc_html( $note ) . '</td></tr>' : '' )
            . '</table>'
            . '<p style="margin-top:16px"><a href="' . esc_url( admin_url( 'admin.php?page=sgc-iip&tab=inquiries' ) ) . '" style="background:#1a1a2e;color:#fff;padding:10px 20px;border-radius:5px;text-decoration:none;font-weight:700">Open IIP Dashboard</a></p>'
            . '</div>';

        wp_mail( $admin_email, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );

        // Mark followup as done in DB
        $tbl = $wpdb->prefix . 'sgc_iip_followups';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tbl}'" ) === $tbl ) { // phpcs:ignore
            $wpdb->update( $tbl, [ 'status' => 'done' ], [ 'inquiry_id' => $inquiry_id, 'status' => 'pending' ] ); // phpcs:ignore
        }
    }

    // ── v1.8.4: Site Manager unified inquiry hook handler ─────────────────────
    // Converts sgclabs_inquiry_received payload → IIP inquiry row, fires
    // sgc_iip_inquiry_processed so CRM bridge and automations trigger normally.
    public static function on_sgclabs_inquiry( array $data ): void {
        global $wpdb;
        $tbl = $wpdb->prefix . 'sgc_iip_inquiries';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) ) !== $tbl ) return; // phpcs:ignore

        $email   = sanitize_email( $data['email'] ?? '' );
        $product = sanitize_text_field( $data['product_title'] ?? $data['product'] ?? $data['subject'] ?? '' );
        if ( ! is_email( $email ) ) return;

        // Duplicate guard: same email within 2 minutes (form double-submit / hook fired twice)
        $recent = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore
            "SELECT id FROM `$tbl` WHERE email=%s AND created_at > %s LIMIT 1",
            $email, date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 120 )
        ) );
        if ( $recent ) return;

        $meta   = is_array( $data['meta'] ?? null ) ? $data['meta'] : [];
        $source = sanitize_key( $data['source'] ?? 'site_manager' );
        $map    = [ 'quote' => 'quote', 'contact' => 'contact', 'inquiry' => 'contact', 'b2b' => 'b2b', 'unified_quote' => 'quote' ];
        $source_type = $map[ $source ] ?? 'manager_pro';

        $qty = sanitize_text_field( (string) ( $meta['qty'] ?? $data['qty'] ?? '' ) );
        $row = [
            'source_type'     => $source_type,
            'source_id'       => (int) ( $data['db_id'] ?? 0 ),
            'name'            => sanitize_text_field( $data['name'] ?? '' ),
            'email'           => $email,
            'phone'           => sanitize_text_field( $data['phone'] ?? '' ),
            'company'         => sanitize_text_field( $data['company'] ?? '' ),
            'country'         => sanitize_text_field( $data['country'] ?? '' ),
            'incoterm'        => sanitize_text_field( $meta['incoterm'] ?? $data['incoterm'] ?? 'FOB' ),
            'port'            => sanitize_text_field( $meta['port'] ?? $data['port'] ?? '' ),
            'raw_message'     => sanitize_textarea_field( $data['message'] ?? '' ),
            'product_details' => wp_json_encode( [ [
                'name'        => $product,
                'qty'         => $qty !== '' ? $qty : 1,
                'unit'        => 'pcs',
                'product_url' => esc_url_raw( $data['product_url'] ?? '' ),
                'type'        => sanitize_text_field( $meta['qtype'] ?? '' ),
            ] ] ),
            'buyer_type'      => sanitize_text_field( $meta['buyer_type'] ?? '' ),
            'created_at'      => current_time( 'mysql' ),
            'updated_at'      => current_time( 'mysql' ),
        ];

        // Full pipeline: health score, currency, auto-reply, classification, sgc_iip_inquiry_processed hook
        $inquiry_id = self::process_new_inquiry( $row );
        if ( $inquiry_id ) {
            SGC_IIP_DB::log( $inquiry_id, 'sm_sync', 'Synced from Site Manager (' . $source . ' form, sgclabs_inquiry_received)', 'system' );
        }
    }

}

SGC_IIP_Hooks::init();
