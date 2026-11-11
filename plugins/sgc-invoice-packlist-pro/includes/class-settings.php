<?php
defined( 'ABSPATH' ) || exit;

/**
 * SGC_IIP_Settings_Ajax
 * All settings save handlers in one place.
 */
class SGC_IIP_Settings_Ajax {

    public static function init() {
        $actions = [
            'sgc_iip_save_company_settings',
            'sgc_iip_save_bank_settings',
            'sgc_iip_save_export_bank_settings',
            'sgc_iip_save_dom_bank_settings',
            'sgc_iip_save_pi_settings',
            'sgc_iip_wa_save_settings',
            'sgc_iip_save_rates',
            'sgc_iip_run_migration',
            'sgc_iip_check_tables',
            'sgc_iip_save_payment_settings',
        ];
        foreach ( $actions as $action ) {
            add_action( 'wp_ajax_' . $action, [ __CLASS__, 'handle_' . str_replace( 'sgc_iip_', '', $action ) ] );
        }
    }

    private static function verify() {
        check_ajax_referer( 'sgc_iip_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'Unauthorized', 403 );
    }

    private static function save_fields( array $fields ) {
        foreach ( $fields as $key => $type ) {
            if ( ! isset( $_POST[ $key ] ) ) continue;
            $raw = wp_unslash( $_POST[ $key ] );
            switch ( $type ) {
                case 'email': $val = sanitize_email( $raw ); break;
                case 'url':   $val = esc_url_raw( $raw ); break;
                case 'bool':  $val = ( '1' === $raw ) ? '1' : '0'; break;
                case 'int':   $val = (string) absint( $raw ); break;
                case 'float':  $val = (string) round( floatval( $raw ), 4 ); break;
                case 'number': $val = (string) round( floatval( $raw ), 2 ); break;
                default:       $val = sanitize_text_field( $raw ); break;
            }
            update_option( $key, $val );
        }
    }

    // ── Company ───────────────────────────────────────────────
    public static function handle_save_company_settings() {
        self::verify();
        self::save_fields( [
            'sgc_iip_company_name'     => 'text',
            'sgc_iip_company_address'  => 'text',
            'sgc_iip_company_gst'      => 'text',
            'sgc_iip_company_pan'      => 'text',
            'sgc_iip_company_iec'      => 'text',
            'sgc_iip_company_phone'    => 'text',
            'sgc_iip_company_email'    => 'email',
            'sgc_iip_company_website'  => 'url',
            // v1.8.5: GST fields
            'sgc_iip_company_state'    => 'text',   // e.g. HR
            'sgc_iip_default_gst_rate' => 'text',   // e.g. 18
            'sgc_iip_default_hsn'      => 'text',   // e.g. 701720
            'sgc_iip_signature_img'    => 'url',
            'sgc_iip_seal_img'         => 'url',
            'sgc_iip_admin_email'      => 'email',
            'sgc_iip_cc_email'         => 'email',
        ] );
        wp_send_json_success( 'Company settings saved.' );
    }

    // ── Bank ──────────────────────────────────────────────────
    public static function handle_save_bank_settings() {
        self::verify();
        self::save_fields( [
            'sgc_iip_bank_beneficiary' => 'text',
            'sgc_iip_bank_name'        => 'text',
            'sgc_iip_bank_branch'      => 'text',
            'sgc_iip_bank_account'     => 'text',
            'sgc_iip_bank_ifsc'        => 'text',
            'sgc_iip_bank_swift'       => 'text',
        ] );
        wp_send_json_success( 'Bank details saved.' );
    }

    // v1.2.5: Export bank (SWIFT) settings ────────────────────
    public static function handle_save_export_bank_settings() {
        self::verify();
        self::save_fields( [
            'sgc_iip_export_bank_beneficiary' => 'text',
            'sgc_iip_export_bank_name'        => 'text',
            'sgc_iip_export_bank_branch'      => 'text',
            'sgc_iip_export_bank_account'     => 'text',
            'sgc_iip_export_bank_ifsc'        => 'text',
            'sgc_iip_export_bank_swift'       => 'text',
            'sgc_iip_export_bank_address'     => 'text',
            'sgc_iip_export_bank_qr_url'      => 'url',
        ] );
        wp_send_json_success( 'Export bank details saved.' );
    }

    // v1.2.5: Domestic bank (IFSC/UPI) settings ───────────────
    public static function handle_save_dom_bank_settings() {
        self::verify();
        self::save_fields( [
            'sgc_iip_dom_bank_beneficiary' => 'text',
            'sgc_iip_dom_bank_name'        => 'text',
            'sgc_iip_dom_bank_branch'      => 'text',
            'sgc_iip_dom_bank_account'     => 'text',
            'sgc_iip_dom_bank_ifsc'        => 'text',
            'sgc_iip_dom_bank_type'        => 'text',
            'sgc_iip_upi_id'               => 'text',
            'sgc_iip_upi_name'             => 'text',
        ] );
        wp_send_json_success( 'Domestic bank details saved.' );
    }

    // ── PI Defaults ───────────────────────────────────────────
    public static function handle_save_pi_settings() {
        self::verify();
        self::save_fields( [
            'sgc_iip_pi_prefix'        => 'text',
            'sgc_iip_pi_validity'      => 'int',
            'sgc_iip_default_incoterm' => 'text',
            'sgc_iip_default_port'     => 'text',
            'sgc_iip_export_freight'   => 'number',
            'sgc_iip_domestic_freight' => 'number',
            'sgc_iip_default_payment'  => 'text',
            'sgc_iip_default_delivery' => 'text',
            'sgc_iip_default_template' => 'text',
            'sgc_iip_cc_email'         => 'email',
        ] );
        wp_send_json_success( 'PI defaults saved.' );
    }

    // ── WhatsApp ──────────────────────────────────────────────
    public static function handle_wa_save_settings() {
        self::verify();
        self::save_fields( [
            'sgc_iip_wa_enabled'     => 'bool',
            'sgc_iip_wa_phone_id'    => 'text',
            'sgc_iip_wa_account_id'  => 'text',
            'sgc_iip_wa_token'       => 'text',
            'sgc_iip_wa_from_number' => 'text',
        ] );
        wp_send_json_success( 'WhatsApp settings saved.' );
    }

    // ── Currency rates ────────────────────────────────────────
    public static function handle_save_rates() {
        self::verify();
        self::save_fields( [
            'sgc_iip_usd_rate'         => 'float',
            'sgc_iip_gbp_rate'         => 'float',
            'sgc_iip_eur_rate'         => 'float',
            'sgc_iip_currency_api_key' => 'text',
        ] );
        // Sync to DB table
        global $wpdb;
        foreach ( [ 'USD' => 'sgc_iip_usd_rate', 'GBP' => 'sgc_iip_gbp_rate', 'EUR' => 'sgc_iip_eur_rate' ] as $cur => $opt ) {
            $rate = floatval( get_option( $opt, 83.5 ) );
            $wpdb->replace( SGC_IIP_DB::t('currency_rates'), [ 'currency' => $cur, 'rate_to_inr' => $rate ] ); // phpcs:ignore
        }
        wp_send_json_success( 'Rates saved.' );
    }


    // ── Payment Methods ───────────────────────────────────────────────
    public static function handle_save_payment_settings() {
        self::verify();
        self::save_fields( [
            'sgc_iip_paypal_email' => 'email',
            'sgc_iip_paypal_link'    => 'text',
            'sgc_iip_paypal_max_usd' => 'float',
            'sgc_iip_paypal_fee_pct' => 'float',
            'sgc_iip_wise_email'   => 'email',
            'sgc_iip_wise_link'    => 'text',
            'sgc_iip_upi_id'       => 'text',
            'sgc_iip_gpay_name'    => 'text',
            'sgc_iip_upi_qr_img'   => 'url',
            'sgc_iip_show_paypal'  => 'bool',
            'sgc_iip_show_wise'    => 'bool',
            'sgc_iip_show_upi'     => 'bool',
        ] );
        wp_send_json_success( 'Payment settings saved.' );
    }
    // ── DB Migration ──────────────────────────────────────────────────
    public static function handle_run_migration() {
        self::verify();
        SGC_IIP_DB::create_tables();
        SGC_IIP_DB::set_defaults();
        // Add new columns if missing (ALTER TABLE safe with IF NOT EXISTS logic via dbDelta)
        global $wpdb;
        $table = SGC_IIP_DB::t('proformas');
        // Check and add gst_percent column
        $cols = $wpdb->get_col("SHOW COLUMNS FROM `{$table}`", 0); // phpcs:ignore
        $added = [];
        $new_cols = [
            'gst_percent'    => "ALTER TABLE `{$table}` ADD COLUMN `gst_percent` decimal(5,2) NOT NULL DEFAULT 0.00 AFTER `tax_amount`",
            'gst_amount'     => "ALTER TABLE `{$table}` ADD COLUMN `gst_amount` decimal(12,2) NOT NULL DEFAULT 0.00 AFTER `gst_percent`",
            'freight_charge' => "ALTER TABLE `{$table}` ADD COLUMN `freight_charge` decimal(12,2) NOT NULL DEFAULT 0.00 AFTER `gst_amount`",
            'buyer_phone'    => "ALTER TABLE `{$table}` ADD COLUMN `buyer_phone` varchar(50) DEFAULT NULL AFTER `buyer_country`",
            'bill_to_name'   => "ALTER TABLE `{$table}` ADD COLUMN `bill_to_name` varchar(200) DEFAULT NULL",
            'bill_to_company'=> "ALTER TABLE `{$table}` ADD COLUMN `bill_to_company` varchar(200) DEFAULT NULL",
            'bill_to_address'=> "ALTER TABLE `{$table}` ADD COLUMN `bill_to_address` text",
            'ship_to_same'   => "ALTER TABLE `{$table}` ADD COLUMN `ship_to_same` tinyint(1) NOT NULL DEFAULT 1",
            'ship_to_name'   => "ALTER TABLE `{$table}` ADD COLUMN `ship_to_name` varchar(200) DEFAULT NULL",
            'ship_to_company'=> "ALTER TABLE `{$table}` ADD COLUMN `ship_to_company` varchar(200) DEFAULT NULL",
            'ship_to_address'=> "ALTER TABLE `{$table}` ADD COLUMN `ship_to_address` text",
            'payment_method' => "ALTER TABLE `{$table}` ADD COLUMN `payment_method` varchar(30) NOT NULL DEFAULT 'bank'",
        ];
        foreach ($new_cols as $col => $sql) {
            if (!in_array($col, $cols)) {
                $wpdb->query($sql); // phpcs:ignore
                $added[] = $col;
            }
        }
        $msg = empty($added) ? 'All columns already up-to-date.' : 'Added columns: '.implode(', ',$added);
        wp_send_json_success($msg.' DB migration complete!');
    }

    public static function handle_check_tables() {
        self::verify();
        global $wpdb;
        $tables = ['inquiries','proformas','packlists','timeline','followup_rules','accepts','hs_codes','currency_rates'];
        $ok = []; $missing = [];
        foreach ($tables as $t) {
            $tn = SGC_IIP_DB::t($t);
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$tn}'"); // phpcs:ignore
            if ($exists) $ok[] = $t; else $missing[] = $t;
        }
        if (empty($missing)) {
            wp_send_json_success('All '.count($ok).' tables present.');
        } else {
            wp_send_json_error('Missing: '.implode(', ',$missing).'. Run DB Migration.');
        }
    }
}

SGC_IIP_Settings_Ajax::init();
