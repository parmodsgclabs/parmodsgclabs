<?php
defined( 'ABSPATH' ) || exit;

class SGC_IIP_WhatsApp {

    const API_BASE = 'https://graph.facebook.com/v19.0/';

    public static function register_ajax() {
        add_action( 'wp_ajax_sgc_iip_wa_test',        [ __CLASS__, 'ajax_test'         ] );
        add_action( 'wp_ajax_sgc_iip_wa_send_custom', [ __CLASS__, 'ajax_send_custom'  ] );
        add_action( 'wp_ajax_sgc_iip_wa_save_settings',[ __CLASS__, 'ajax_save_settings'] );
    }

    private static function verify() {
        check_ajax_referer( 'sgc_iip_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'Unauthorized', 403 );
    }

    // ── Core send ─────────────────────────────────────────────────────────
    public static function send( $to_phone, $message_type, $payload ) {
        $phone_id = get_option( 'sgc_iip_wa_phone_id', '' );
        $token    = get_option( 'sgc_iip_wa_token', '' );

        if ( ! $phone_id || ! $token ) return false;

        // Clean phone number
        $phone = preg_replace( '/[^0-9]/', '', $to_phone );
        if ( strlen( $phone ) < 10 ) return false;
        // Add India code if no country code
        if ( strlen( $phone ) === 10 ) $phone = '91' . $phone;

        $url  = self::API_BASE . $phone_id . '/messages';
        $body = array_merge( [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $phone,
            'type'              => $message_type,
        ], $payload );

        $response = wp_remote_post( $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( $body ),
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) return false;

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 === $code || 201 === $code ) return true;

        // Log error
        error_log( 'SGC IIP WhatsApp error: ' . wp_remote_retrieve_body( $response ) );
        return false;
    }

    // ── Send auto-reply (text message) ────────────────────────────────────
    public static function send_autoreply( array $inquiry ) {
        if ( empty( $inquiry['phone'] ) ) return false;

        $name    = $inquiry['name'] ?? 'Valued Customer';
        $products = json_decode( $inquiry['product_details'] ?? '[]', true );
        $product  = $products[0]['name'] ?? 'your inquiry';
        $company  = get_option( 'sgc_iip_company_name', 'SGC LABS®' );
        $phone    = get_option( 'sgc_iip_company_phone', '+91 999 641 6935' );

        $message = "Dear $name,\n\nThank you for contacting $company!\n\n"
            . "We have received your inquiry regarding *$product* and our export team is reviewing your requirements.\n\n"
            . "You will receive our Proforma Invoice within *24 hours*.\n\n"
            . "For urgent queries, please call or WhatsApp us at:\n$phone\n\n"
            . "Warm regards,\n$company\n🌐 labsglass.com";

        return self::send( $inquiry['phone'], 'text', [
            'text' => [ 'body' => $message, 'preview_url' => false ],
        ] );
    }

    // ── Send PI notification ──────────────────────────────────────────────
    public static function send_pi_notification( array $inquiry, string $pi_number, string $accept_url = '' ) {
        if ( empty( $inquiry['phone'] ) ) return false;

        $name    = $inquiry['name'] ?? 'Valued Customer';
        $company = get_option( 'sgc_iip_company_name', 'SGC LABS®' );
        $email   = get_option( 'sgc_iip_company_email', 'info@labsglass.com' );

        $message = "Dear $name,\n\nPlease find attached our *Proforma Invoice $pi_number* from $company.\n\n"
            . "The invoice has been sent to your registered email address.\n\n";

        if ( $accept_url ) {
            $message .= "✅ You can also accept this PI online:\n$accept_url\n\n";
        }

        $message .= "For any modifications or queries, simply reply to this message or email us at $email.\n\n"
            . "Thank you for your business!\n$company";

        return self::send( $inquiry['phone'], 'text', [
            'text' => [ 'body' => $message, 'preview_url' => false ],
        ] );
    }

    // ── Send follow-up message ────────────────────────────────────────────
    public static function send_followup( array $inquiry, string $message ) {
        if ( empty( $inquiry['phone'] ) ) return false;
        return self::send( $inquiry['phone'], 'text', [
            'text' => [ 'body' => $message, 'preview_url' => false ],
        ] );
    }

    // ── AJAX: Test connection ─────────────────────────────────────────────
    public static function ajax_test() {
        self::verify();
        $test_phone = sanitize_text_field( $_POST['phone'] ?? '' );
        if ( ! $test_phone ) { wp_send_json_error( 'Enter a phone number to test.' ); return; }

        $company = get_option( 'sgc_iip_company_name', 'SGC LABS®' );
        $sent    = self::send( $test_phone, 'text', [
            'text' => [ 'body' => "✅ WhatsApp integration test from $company — SGC Invoice Pro Manager. Connection working!", 'preview_url' => false ],
        ] );

        wp_send_json_success( $sent
            ? 'Test message sent! Check your WhatsApp.'
            : 'Failed. Check Phone Number ID and Token.'
        );
    }

    // ── AJAX: Send custom message ─────────────────────────────────────────
    public static function ajax_send_custom() {
        self::verify();
        global $wpdb;

        $id      = (int) ( $_POST['inquiry_id'] ?? 0 );
        $message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );

        $inq = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . SGC_IIP_DB::t('inquiries') . " WHERE id=%d", $id ), ARRAY_A ); // phpcs:ignore
        if ( ! $inq ) { wp_send_json_error( 'Inquiry not found.' ); return; }
        if ( empty( $inq['phone'] ) ) { wp_send_json_error( 'No phone number for this inquiry.' ); return; }

        $sent = self::send_followup( $inq, $message );
        if ( $sent ) SGC_IIP_DB::log( $id, 'wa_custom_sent', 'WhatsApp message sent', 'admin' );
        wp_send_json_success( $sent ? 'WhatsApp message sent!' : 'Failed to send.' );
    }

    // ── AJAX: Save settings ───────────────────────────────────────────────
    public static function ajax_save_settings() {
        self::verify();
        $fields = [
            'sgc_iip_wa_enabled'    => 'bool',
            'sgc_iip_wa_phone_id'   => 'text',
            'sgc_iip_wa_account_id' => 'text',
            'sgc_iip_wa_token'      => 'text',
            'sgc_iip_wa_from_number'=> 'text',
        ];
        foreach ( $fields as $k => $type ) {
            if ( ! isset( $_POST[ $k ] ) ) continue;
            $v = wp_unslash( $_POST[ $k ] );
            update_option( $k, 'bool' === $type ? ( '1' === $v ? '1' : '0' ) : sanitize_text_field( $v ) );
        }
        wp_send_json_success( 'WhatsApp settings saved.' );
    }
}

SGC_IIP_WhatsApp::register_ajax();
