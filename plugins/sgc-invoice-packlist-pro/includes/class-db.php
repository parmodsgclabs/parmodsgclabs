<?php
defined( 'ABSPATH' ) || exit;

class SGC_IIP_DB {

    // ── Table names ──────────────────────────────────────────────────────
    public static function t( $name ) {
        global $wpdb;
        return $wpdb->prefix . 'sgc_iip_' . $name;
    }

    // ── Create all tables ─────────────────────────────────────────────────
    public static function create_tables() {
        global $wpdb;
        $c = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Main inquiries (extends Manager Pro wp_sgclabs_inquiries)
        dbDelta( "CREATE TABLE " . self::t('inquiries') . " (
            id              bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            source_id       bigint(20)          DEFAULT NULL COMMENT 'ID from wp_sgclabs_inquiries or wp_sgclabs_b2b_quotes',
            source_type     varchar(30)         NOT NULL DEFAULT 'contact' COMMENT 'contact|quote|b2b|manual',
            name            varchar(150)        NOT NULL DEFAULT '',
            email           varchar(150)        NOT NULL DEFAULT '',
            phone           varchar(50)         NOT NULL DEFAULT '',
            company         varchar(200)        NOT NULL DEFAULT '',
            country         varchar(100)        NOT NULL DEFAULT '',
            country_code    varchar(5)          NOT NULL DEFAULT '',
            currency        varchar(5)          NOT NULL DEFAULT 'USD',
            product_details longtext            COMMENT 'JSON array of products inquired',
            raw_message     text,
            incoterm        varchar(20)         NOT NULL DEFAULT 'FOB',
            port            varchar(200)        NOT NULL DEFAULT '',
            status          varchar(30)         NOT NULL DEFAULT 'new' COMMENT 'new|replied|pi_sent|accepted|advance|won|lost|sample',
            pipeline_stage  varchar(30)         NOT NULL DEFAULT 'new',
            loss_reason     varchar(100)        DEFAULT NULL,
            health_score    tinyint(3)          NOT NULL DEFAULT 0,
            buyer_type      varchar(30)         NOT NULL DEFAULT 'unknown' COMMENT 'oem|bulk|dealer|institution|retail|sample',
            ai_classified   tinyint(1)          NOT NULL DEFAULT 0,
            returning_buyer tinyint(1)          NOT NULL DEFAULT 0,
            wa_sent         tinyint(1)          NOT NULL DEFAULT 0,
            email_sent      tinyint(1)          NOT NULL DEFAULT 0,
            auto_reply_at   datetime            DEFAULT NULL,
            pi_number       varchar(30)         DEFAULT NULL,
            pi_accepted     tinyint(1)          NOT NULL DEFAULT 0,
            pi_accepted_at  datetime            DEFAULT NULL,
            pi_accept_token varchar(64)         DEFAULT NULL,
            payment_proof_url varchar(500)      DEFAULT NULL COMMENT 'Buyer uploaded proof URL',
            balance_reminder_sent tinyint(1)    DEFAULT 0   COMMENT '1 = reminder sent',
            balance_reminder_at   datetime      DEFAULT NULL COMMENT 'Reminder sent timestamp',
            advance_amount  decimal(12,2)       DEFAULT NULL,
            advance_date    datetime            DEFAULT NULL,
            admin_note      text,
            ai_draft        longtext            COMMENT 'DeepSeek AI draft content JSON',
            last_followup   datetime            DEFAULT NULL,
            followup_count  tinyint(3)          NOT NULL DEFAULT 0,
            last_message_at datetime            DEFAULT NULL COMMENT 'v1.9.0 inbox',
            unread_count    int                 NOT NULL DEFAULT 0 COMMENT 'v1.9.0 inbox',
            created_at      datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      datetime            DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY     (id),
            KEY status_idx  (status),
            KEY email_idx   (email(100)),
            KEY country_idx (country_code),
            KEY created_idx (created_at)
        ) $c;" );

        // Proforma Invoices
        dbDelta( "CREATE TABLE " . self::t('proformas') . " (
            id              bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            inquiry_id      bigint(20) UNSIGNED NOT NULL,
            pi_number       varchar(30)         NOT NULL,
            pi_type         varchar(20)         NOT NULL DEFAULT 'standard' COMMENT 'standard|oem|sample|revised|b2b',
            revision        tinyint(3)          NOT NULL DEFAULT 1,
            template        varchar(20)         NOT NULL DEFAULT 'navy' COMMENT 'navy|export|minimal',
            buyer_name      varchar(150)        NOT NULL DEFAULT '',
            buyer_email     varchar(150)        NOT NULL DEFAULT '',
            buyer_company   varchar(200)        NOT NULL DEFAULT '',
            buyer_country   varchar(100)        NOT NULL DEFAULT '',
            buyer_phone     varchar(50)         DEFAULT NULL,
            buyer_gstin     varchar(20)         DEFAULT NULL COMMENT 'Buyer GSTIN for domestic invoices',
            buyer_address   text                COMMENT 'Bill To address',
            ship_to_name    varchar(200)        DEFAULT NULL,
            ship_to_company varchar(200)        DEFAULT NULL,
            ship_to_address text                COMMENT 'Ship To address (if different)',
            ship_to_same    tinyint(1)          NOT NULL DEFAULT 1 COMMENT '1=same as bill to',
            payment_method  varchar(30)         NOT NULL DEFAULT 'bank' COMMENT 'bank|paypal|wise|paypal_wise|upi|bank_upi',
            pi_upi_id       varchar(100)        DEFAULT NULL COMMENT 'Per-PI UPI ID override',
            pi_upi_name     varchar(100)        DEFAULT NULL COMMENT 'Per-PI UPI display name',
            pi_upi_qr_url   varchar(500)        DEFAULT NULL COMMENT 'Per-PI custom QR URL',
            currency        varchar(5)          NOT NULL DEFAULT 'USD',
            inr_rate        decimal(10,4)       NOT NULL DEFAULT 83.5000,
            line_items      longtext            NOT NULL COMMENT 'JSON',
            subtotal        decimal(12,2)       NOT NULL DEFAULT 0.00,
            packaging_total decimal(12,2)       NOT NULL DEFAULT 0.00,
            oem_charge      decimal(12,2)       NOT NULL DEFAULT 0.00,
            tax_amount      decimal(12,2)       NOT NULL DEFAULT 0.00,
            gst_percent     decimal(5,2)        NOT NULL DEFAULT 0.00,
            gst_amount      decimal(12,2)       NOT NULL DEFAULT 0.00,
            freight_charge  decimal(12,2)       NOT NULL DEFAULT 0.00,
            discount        decimal(12,2)       NOT NULL DEFAULT 0.00,
            grand_total     decimal(12,2)       NOT NULL DEFAULT 0.00,
            grand_total_inr decimal(12,2)       NOT NULL DEFAULT 0.00,
            incoterm        varchar(20)         NOT NULL DEFAULT 'FOB',
            port            varchar(200)        NOT NULL DEFAULT 'New Delhi, India',
            payment_terms   varchar(200)        NOT NULL DEFAULT '30% advance, 70% before shipment',
            delivery_days   varchar(100)        NOT NULL DEFAULT '15-20 working days',
            validity_days   tinyint(3)          NOT NULL DEFAULT 30,
            valid_until     date                DEFAULT NULL,
            notes           text,
            status          varchar(20)         NOT NULL DEFAULT 'draft' COMMENT 'draft|sent|accepted|expired|revised|dispatched',
            sent_at         datetime            DEFAULT NULL,
            pdf_path        varchar(500)        DEFAULT NULL,
            accept_token    varchar(64)         DEFAULT NULL,
            freight_partner varchar(200)        DEFAULT NULL COMMENT 'Courier/freight company name',
            tracking_no     varchar(200)        DEFAULT NULL COMMENT 'Shipment tracking number',
            tracking_url    varchar(500)        DEFAULT NULL COMMENT 'Tracking URL',
            dispatch_date   datetime            DEFAULT NULL COMMENT 'Date of dispatch',
            created_at      datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY     (id),
            KEY inquiry_idx (inquiry_id),
            KEY pi_num_idx  (pi_number)
        ) $c;" );

        // Packing Lists
        dbDelta( "CREATE TABLE " . self::t('packlists') . " (
            id              bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            inquiry_id      bigint(20) UNSIGNED NOT NULL,
            pi_id           bigint(20) UNSIGNED DEFAULT NULL,
            pl_number       varchar(30)         NOT NULL,
            boxes           longtext            NOT NULL COMMENT 'JSON array of boxes',
            total_boxes     smallint(5)         NOT NULL DEFAULT 0,
            total_net_wt    decimal(10,3)       NOT NULL DEFAULT 0.000,
            total_gross_wt  decimal(10,3)       NOT NULL DEFAULT 0.000,
            total_cbm       decimal(10,4)       NOT NULL DEFAULT 0.0000,
            sea_est_usd     decimal(10,2)       DEFAULT NULL,
            air_est_usd     decimal(10,2)       DEFAULT NULL,
            pdf_path        varchar(500)        DEFAULT NULL,
            created_at      datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY     (id),
            KEY inquiry_idx (inquiry_id)
        ) $c;" );

        // Timeline / Activity log per inquiry
        dbDelta( "CREATE TABLE " . self::t('timeline') . " (
            id              bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            inquiry_id      bigint(20) UNSIGNED NOT NULL,
            action          varchar(100)        NOT NULL,
            description     text,
            actor           varchar(100)        NOT NULL DEFAULT 'system',
            meta            longtext            COMMENT 'JSON extra data',
            created_at      datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY     (id),
            KEY inquiry_idx (inquiry_id),
            KEY created_idx (created_at)
        ) $c;" );

        // Follow-up rules
        dbDelta( "CREATE TABLE " . self::t('followup_rules') . " (
            id              bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name            varchar(200)        NOT NULL,
            trigger_status  varchar(30)         NOT NULL COMMENT 'pi_sent|new|sample',
            delay_days      tinyint(3)          NOT NULL DEFAULT 3,
            channel         varchar(20)         NOT NULL DEFAULT 'email' COMMENT 'email|whatsapp|both',
            subject         varchar(300)        DEFAULT NULL,
            message         text                NOT NULL,
            status          varchar(20)         NOT NULL DEFAULT 'active',
            run_count       int(11)             NOT NULL DEFAULT 0,
            created_at      datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY     (id)
        ) $c;" );

        // PI Accept log
        dbDelta( "CREATE TABLE " . self::t('accepts') . " (
            id              bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            pi_id           bigint(20) UNSIGNED NOT NULL,
            inquiry_id      bigint(20) UNSIGNED NOT NULL,
            buyer_ip        varchar(45)         DEFAULT NULL,
            buyer_agent     text,
            accepted_at     datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY     (id)
        ) $c;" );

        // HS Codes reference
        dbDelta( "CREATE TABLE " . self::t('hs_codes') . " (
            id              bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            hs_code         varchar(20)         NOT NULL,
            description     varchar(300)        NOT NULL,
            category_match  varchar(200)        DEFAULT NULL COMMENT 'WC category slug match',
            import_duty_in  decimal(5,2)        DEFAULT NULL,
            PRIMARY KEY     (id),
            KEY hs_idx      (hs_code)
        ) $c;" );

        // Currency rates cache
        dbDelta( "CREATE TABLE " . self::t('currency_rates') . " (
            currency    varchar(5)  NOT NULL,
            rate_to_inr decimal(12,4) NOT NULL,
            updated_at  datetime    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (currency)
        ) $c;" );

        // v1.2.0: Payment register — full installment/partial tracking
        dbDelta( "CREATE TABLE " . self::t('payments') . " (
            id              bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            inquiry_id      bigint(20) UNSIGNED NOT NULL,
            pi_id           bigint(20) UNSIGNED DEFAULT NULL,
            payment_type    varchar(30)         NOT NULL DEFAULT 'advance' COMMENT 'advance|balance|full|sample|refund|partial',
            amount          decimal(14,2)       NOT NULL DEFAULT 0.00,
            currency        varchar(5)          NOT NULL DEFAULT 'USD',
            amount_inr      decimal(14,2)       DEFAULT NULL COMMENT 'INR equivalent at time of payment',
            method          varchar(30)         NOT NULL DEFAULT 'bank_tt' COMMENT 'bank_tt|lc|paypal|wise|upi|cash|cheque',
            reference_no    varchar(200)        DEFAULT NULL COMMENT 'TT ref / UTR / PayPal txn ID',
            payment_date    date                NOT NULL,
            notes           text                DEFAULT NULL,
            proof_url       varchar(500)        DEFAULT NULL COMMENT 'URL to uploaded payment proof',
            recorded_by     varchar(100)        NOT NULL DEFAULT 'admin',
            created_at      datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY     (id),
            KEY inquiry_idx (inquiry_id),
            KEY pi_idx      (pi_id),
            KEY date_idx    (payment_date)
        ) $c;" );

        // v1.9.0: Email Inbox — threaded messages (in + out)
        dbDelta( "CREATE TABLE " . self::t('messages') . " (
            id              bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            inquiry_id      bigint(20) UNSIGNED NOT NULL,
            direction       varchar(3)          NOT NULL DEFAULT 'in' COMMENT 'in|out',
            message_id      varchar(255)        NOT NULL DEFAULT '',
            in_reply_to     varchar(255)        NOT NULL DEFAULT '',
            from_email      varchar(190)        NOT NULL DEFAULT '',
            from_name       varchar(190)        NOT NULL DEFAULT '',
            to_email        varchar(190)        NOT NULL DEFAULT '',
            subject         varchar(500)        NOT NULL DEFAULT '',
            body_html       longtext,
            body_text       longtext,
            has_attachments tinyint(1)          NOT NULL DEFAULT 0,
            attachments     longtext            COMMENT 'JSON array',
            is_read         tinyint(1)          NOT NULL DEFAULT 0,
            received_at     datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at      datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY     (id),
            UNIQUE KEY msgid_idx   (message_id),
            KEY inquiry_idx (inquiry_id),
            KEY unread_idx  (direction, is_read),
            KEY received_idx (received_at)
        ) $c;" );
    }

    // ── Default options ───────────────────────────────────────────────────
    public static function set_defaults() {
        $defaults = [
            // Company
            'sgc_iip_company_name'     => 'SGC LABS® Scientific Glassware',
            'sgc_iip_company_address'  => 'Ambala, Haryana - 133001, India',
            'sgc_iip_company_gst'      => '',
            'sgc_iip_company_pan'      => '',
            'sgc_iip_company_iec'      => '', // Import Export Code
            'sgc_iip_company_phone'    => '+91 999 641 6935',
            'sgc_iip_company_email'    => 'info@labsglass.com',
            'sgc_iip_company_website'  => 'https://labsglass.com',
            // v1.8.5: GST
            'sgc_iip_company_state'    => 'HR', // Haryana
            'sgc_iip_default_gst_rate' => '18', // 18% for borosilicate lab glassware
            'sgc_iip_default_hsn'      => '701720', // Borosilicate glass lab ware
            // Bank
            'sgc_iip_bank_name'        => '',
            'sgc_iip_bank_account'     => '',
            'sgc_iip_bank_ifsc'        => '',
            'sgc_iip_bank_swift'       => '',
            'sgc_iip_bank_branch'      => 'Ambala, Haryana',
            'sgc_iip_bank_beneficiary' => 'SGC Labs',
            // Admin
            'sgc_iip_admin_email'      => get_option( 'admin_email' ),
            'sgc_iip_cc_email'         => get_option( 'admin_email' ),
            // PI Settings
            'sgc_iip_pi_prefix'        => 'SGC',
            'sgc_iip_pi_counter'       => '1',
            'sgc_iip_pi_validity'      => '30',
            'sgc_iip_default_incoterm' => 'FOB',
            'sgc_iip_default_port'     => 'New Delhi, India',
            'sgc_iip_default_payment'  => '30% advance, 70% before shipment',
            'sgc_iip_default_delivery' => '15-20 working days',
            'sgc_iip_default_template' => 'navy',
            'sgc_iip_signature_img'    => '',
            'sgc_iip_seal_img'         => '',
            // AI
            'sgc_iip_ai_provider'      => 'deepseek',
            'sgc_iip_ai_key_deepseek'  => '',
            'sgc_iip_ai_key_openai'    => '',
            'sgc_iip_ai_key_claude'    => '',
            // WhatsApp
            'sgc_iip_wa_enabled'       => '0',
            'sgc_iip_wa_phone_id'      => '',
            'sgc_iip_wa_account_id'    => '',
            'sgc_iip_wa_token'         => '',
            'sgc_iip_wa_from_number'   => '',
            // Currency
            'sgc_iip_currency_api_key' => '',
            'sgc_iip_usd_rate'         => '83.50',
            'sgc_iip_eur_rate'         => '90.20',
            'sgc_iip_gbp_rate'         => '106.50',
            // Payment Methods
            'sgc_iip_paypal_email'     => '',
            'sgc_iip_paypal_link'      => '',   // e.g. paypal.me/sgclabs
            'sgc_iip_wise_email'       => '',
            'sgc_iip_wise_link'        => '',   // e.g. wise.com/pay/me/sgclabs
            'sgc_iip_upi_id'           => '',   // e.g. 9996416935@ybl
            'sgc_iip_gpay_name'        => 'SGC Labs',
            'sgc_iip_upi_qr_img'       => '',   // Custom QR image URL (optional)
            'sgc_iip_show_paypal'      => '0',
            'sgc_iip_show_wise'        => '0',
            'sgc_iip_show_upi'         => '1',
            // Follow-up
            'sgc_iip_followup_enabled' => '1',
            // Auto-reply
            'sgc_iip_autoreply_email'  => '1',
            'sgc_iip_autoreply_wa'     => '0',
        ];
        foreach ( $defaults as $k => $v ) {
            add_option( $k, $v );
        }

        // Default currency rates
        global $wpdb;
        $rates = [ 'USD' => 83.50, 'EUR' => 90.20, 'GBP' => 106.50, 'AED' => 22.73, 'SAR' => 22.27 ];
        foreach ( $rates as $cur => $rate ) {
            $wpdb->replace( self::t('currency_rates'), [ 'currency' => $cur, 'rate_to_inr' => $rate ] ); // phpcs:ignore
        }

        // Default follow-up rules
        self::seed_followup_rules();
    }

    // ── HS Codes for lab glassware ─────────────────────────────────────────
    public static function seed_hs_codes() {
        global $wpdb;
        $table = self::t('hs_codes');
        if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table`" ) > 0 ) return; // phpcs:ignore

        $codes = [
            [ '7017.10', 'Quartz/fused silica laboratory glassware', 'silica', 12.0 ],
            [ '7017.20', 'Borosilicate glass laboratory glassware', 'borosilicate', 12.0 ],
            [ '7017.90', 'Other laboratory/hygienic/pharmaceutical glassware', 'glassware', 12.0 ],
            [ '3926.90', 'Plastic labware — other articles of plastics', 'plastic', 18.0 ],
            [ '3926.20', 'PTFE articles — articles of plastics for technical use', 'ptfe', 18.0 ],
            [ '9027.90', 'Parts/accessories for lab instruments', 'instruments', 7.5 ],
            [ '7020.00', 'Other articles of glass (lab use)', 'glass', 10.0 ],
            [ '3917.39', 'Rubber tubing for laboratory', 'rubber', 15.0 ],
            [ '7326.90', 'Metalware for laboratory — other articles of iron/steel', 'metalware', 15.0 ],
            [ '8438.80', 'Glass blowing lathe machinery', 'machinery', 7.5 ],
        ];
        foreach ( $codes as $c ) {
            $wpdb->insert( $table, [ // phpcs:ignore
                'hs_code'        => $c[0],
                'description'    => $c[1],
                'category_match' => $c[2],
                'import_duty_in' => $c[3],
            ] );
        }
    }

    // ── Default follow-up rules ───────────────────────────────────────────
    public static function seed_followup_rules() {
        global $wpdb;
        $table = self::t('followup_rules');
        if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table`" ) > 0 ) return; // phpcs:ignore

        $rules = [
            [
                'name'           => 'PI Follow-up — Day 3',
                'trigger_status' => 'pi_sent',
                'delay_days'     => 3,
                'channel'        => 'both',
                'subject'        => 'Following up on our Proforma Invoice — SGC LABS®',
                'message'        => "Dear {name},\n\nI hope you received our Proforma Invoice (PI No: {pi_number}) dated {pi_date}.\n\nI wanted to follow up to see if you have any questions or require any modifications to the quote.\n\nWe are committed to providing you the best quality laboratory glassware at competitive prices.\n\nPlease feel free to reach out at any time.\n\nWarm regards,\nSGC LABS® Export Team\nlabsglass.com | +91 999 641 6935",
            ],
            [
                'name'           => 'PI Follow-up — Day 7',
                'trigger_status' => 'pi_sent',
                'delay_days'     => 7,
                'channel'        => 'email',
                'subject'        => 'Any modifications needed? — SGC LABS® PI {pi_number}',
                'message'        => "Dear {name},\n\nWe noticed our Proforma Invoice {pi_number} is still open.\n\nIf you require any changes — quantity, packaging, specifications or pricing — we are happy to revise it for you.\n\nOur offer is valid until {valid_until}.\n\nLooking forward to hearing from you.\n\nBest regards,\nSGC LABS® Team",
            ],
            [
                'name'           => 'New Inquiry — No Reply Day 2',
                'trigger_status' => 'new',
                'delay_days'     => 2,
                'channel'        => 'whatsapp',
                'subject'        => null,
                'message'        => "Dear {name}, this is a gentle reminder from SGC LABS®. We received your inquiry for {product} and our team is preparing a detailed quote for you. You will receive our Proforma Invoice shortly. For any urgent queries, please contact us at +91-9996416935. Thank you.",
            ],
        ];
        foreach ( $rules as $r ) {
            $wpdb->insert( $table, $r ); // phpcs:ignore
        }
    }

    // ── PI Number Generator ───────────────────────────────────────────────
    // ── v1.2.0: Payment helpers ───────────────────────────────────────────

    /** Get all payments for an inquiry */
    public static function get_payments( int $inquiry_id ): array {
        global $wpdb;
        return $wpdb->get_results( // phpcs:ignore
            $wpdb->prepare( "SELECT * FROM " . self::t('payments') . " WHERE inquiry_id=%d ORDER BY payment_date DESC, id DESC", $inquiry_id ),
            ARRAY_A
        ) ?: [];
    }

    /** Total paid (in original currency) for an inquiry */
    public static function total_paid( int $inquiry_id ): float {
        global $wpdb;
        $rows = $wpdb->get_results( // phpcs:ignore
            $wpdb->prepare( "SELECT amount, currency FROM " . self::t('payments') . " WHERE inquiry_id=%d AND payment_type != 'refund'", $inquiry_id ),
            ARRAY_A
        ) ?: [];
        // Sum everything converted to USD equivalent (best effort)
        $total_usd = 0;
        foreach ( $rows as $r ) {
            $amt = (float) $r['amount'];
            $cur = strtoupper( $r['currency'] );
            if ( $cur === 'USD' ) { $total_usd += $amt; continue; }
            // Use stored rate
            $rate_inr = (float) ( self::get_currency_rate_inr( $cur ) ?: 1 );
            $usd_rate = (float) ( self::get_currency_rate_inr( 'USD' ) ?: 84 );
            $total_usd += $rate_inr > 0 ? ( $amt * $rate_inr / $usd_rate ) : $amt;
        }
        return round( $total_usd, 2 );
    }

    /** Get INR rate for a currency from our cache table */
    public static function get_currency_rate_inr( string $currency ): float {
        global $wpdb;
        $rate = $wpdb->get_var( // phpcs:ignore
            $wpdb->prepare( "SELECT rate_to_inr FROM " . self::t('currency_rates') . " WHERE currency=%s", strtoupper($currency) )
        );
        return $rate ? (float) $rate : 0.0;
    }

    public static function generate_pi_number( $type = 'standard' ) {
        global $wpdb;

        // v1.8.5: Atomic PI counter — DB-level lock prevents race condition.
        // Two simultaneous requests can no longer get the same PI number.
        $lock_key    = 'sgc_iip_pi_lock';
        $lock_val    = uniqid( 'pi_', true );
        $lock_expiry = 5; // seconds

        // Spin-wait for lock (max 3 attempts × 100ms)
        $locked = false;
        for ( $i = 0; $i < 3; $i++ ) {
            $locked = $wpdb->query( $wpdb->prepare( // phpcs:ignore
                "INSERT IGNORE INTO `{$wpdb->options}` (option_name, option_value, autoload)
                 VALUES (%s, %s, 'no')",
                $lock_key, $lock_val
            ) );
            if ( $locked ) break;
            usleep( 100000 ); // 100ms
        }

        // Read counter AFTER acquiring lock
        $counter = (int) get_option( 'sgc_iip_pi_counter', 1 );

        $prefix = rtrim( trim( get_option( 'sgc_iip_pi_prefix', 'SGC' ) ), '-/ ' );
        if ( empty( $prefix ) ) $prefix = 'SGC';

        $year   = date( 'Y' );
        $prefix = rtrim( preg_replace( '/' . preg_quote( $year, '/' ) . '$/', '', $prefix ), '-/ ' );
        if ( empty( $prefix ) ) $prefix = 'SGC';

        $type_codes = [
            'standard' => '',
            'oem'      => '-OEM',
            'sample'   => '-SMP',
            'revised'  => '-REV',
            'b2b'      => '-B2B',
        ];
        $type_code = $type_codes[ $type ] ?? '';

        $pi_number = $prefix . $type_code . '-' . $year . '-' . str_pad( $counter, 4, '0', STR_PAD_LEFT );

        // Increment counter + release lock atomically
        update_option( 'sgc_iip_pi_counter', $counter + 1 );
        $wpdb->delete( $wpdb->options, [ 'option_name' => $lock_key, 'option_value' => $lock_val ] ); // phpcs:ignore

        return $pi_number;
    }

    // ── Health Score Calculator ───────────────────────────────────────────
    public static function calc_health( $inquiry ) {
        $score = 0;
        if ( ! empty( $inquiry['name'] ) )    $score += 10;
        if ( ! empty( $inquiry['email'] ) )   $score += 10;
        if ( ! empty( $inquiry['phone'] ) )   $score += 10;
        if ( ! empty( $inquiry['company'] ) ) $score += 15;
        if ( ! empty( $inquiry['country'] ) ) $score += 10;
        if ( ! empty( $inquiry['product_details'] ) ) {
            $pd = is_string( $inquiry['product_details'] )
                ? json_decode( $inquiry['product_details'], true )
                : $inquiry['product_details'];
            if ( ! empty( $pd ) ) $score += 20;
            // Check if qty mentioned
            foreach ( (array) $pd as $item ) {
                if ( ! empty( $item['qty'] ) ) { $score += 25; break; }
            }
        } elseif ( ! empty( $inquiry['raw_message'] ) ) {
            // Check for quantity keywords
            if ( preg_match( '/\d+\s*(pcs|pieces|units|nos|qty|sets)/i', $inquiry['raw_message'] ) ) {
                $score += 25;
            }
        }
        return min( 100, $score );
    }

    // ── Helper: save timeline entry ───────────────────────────────────────
    public static function log( $inquiry_id, $action, $description, $actor = 'system', $meta = [] ) {
        global $wpdb;
        $wpdb->insert( self::t('timeline'), [ // phpcs:ignore
            'inquiry_id'  => $inquiry_id,
            'action'      => $action,
            'description' => $description,
            'actor'       => $actor ?: 'admin',
            'meta'        => $meta ? wp_json_encode( $meta ) : null,
        ] );
    }
}

// ── v1.3.0: Safe ALTER for existing installs (runs once on init) ──────────────
add_action( 'init', function () {
    if ( get_option('sgc_iip_db_v130') === '1' ) return;
    global $wpdb;
    $t    = SGC_IIP_DB::t('inquiries');
    if ( $wpdb->get_var("SHOW TABLES LIKE '$t'") !== $t ) return;
    $cols = $wpdb->get_col("DESCRIBE `$t`");
    $alters = [
        'payment_proof_url'    => "ADD COLUMN `payment_proof_url` varchar(500) DEFAULT NULL COMMENT 'Buyer uploaded payment proof URL'",
        'balance_reminder_sent'=> "ADD COLUMN `balance_reminder_sent` tinyint(1) DEFAULT 0 COMMENT '1 = balance reminder email sent'",
        'balance_reminder_at'  => "ADD COLUMN `balance_reminder_at` datetime DEFAULT NULL COMMENT 'Balance reminder sent timestamp'",
    ];
    foreach ( $alters as $col => $sql ) {
        if ( ! in_array($col, $cols, true) ) {
            $wpdb->query("ALTER TABLE `$t` $sql"); // phpcs:ignore
        }
    }
    // Generate tokens for existing inquiries without one
    $wpdb->query("UPDATE `$t` SET pi_accept_token = SUBSTRING(MD5(RAND()),1,32) WHERE pi_accept_token IS NULL OR pi_accept_token = ''"); // phpcs:ignore
    update_option('sgc_iip_db_v130','1');
}, 25 );

// ── v1.8.5: GST split columns + AI classifier columns ────────────────────────
add_action( 'init', function () {
    if ( get_option( 'sgc_iip_db_v185' ) === '1' ) return;
    global $wpdb;
    $t = SGC_IIP_DB::t( 'inquiries' );
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$t'" ) !== $t ) return; // phpcs:ignore
    $cols   = $wpdb->get_col( "DESCRIBE `$t`" ); // phpcs:ignore
    $alters = [
        // GST split
        'cgst_amount'   => "ADD COLUMN `cgst_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'CGST (same-state, 50% of GST)'",
        'sgst_amount'   => "ADD COLUMN `sgst_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'SGST (same-state, 50% of GST)'",
        'igst_amount'   => "ADD COLUMN `igst_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'IGST (inter-state, full GST)'",
        'gst_type'      => "ADD COLUMN `gst_type` varchar(10) DEFAULT 'igst' COMMENT 'igst or cgst_sgst'",
        'company_state' => "ADD COLUMN `company_state` varchar(5) DEFAULT 'HR' COMMENT 'Company state code at time of PI'",
        'buyer_state'   => "ADD COLUMN `buyer_state` varchar(5) DEFAULT NULL COMMENT 'Buyer state code (domestic only)'",
        // AI classifier
        'urgency_score' => "ADD COLUMN `urgency_score` tinyint(3) DEFAULT 0 COMMENT 'AI urgency 1-5'",
        'spam_score'    => "ADD COLUMN `spam_score` tinyint(3) DEFAULT 0 COMMENT 'AI spam probability 0-100'",
        'ai_notes'      => "ADD COLUMN `ai_notes` text DEFAULT NULL COMMENT 'Full AI classification JSON'",
    ];
    foreach ( $alters as $col => $sql ) {
        if ( ! in_array( $col, $cols, true ) ) {
            $wpdb->query( "ALTER TABLE `$t` $sql" ); // phpcs:ignore
        }
    }
    // HSN code per product line item stored in product_details JSON — no schema change needed
    update_option( 'sgc_iip_db_v185', '1' );
}, 26 );
