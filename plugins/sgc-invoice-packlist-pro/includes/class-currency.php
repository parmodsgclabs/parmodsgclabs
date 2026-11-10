<?php
defined( 'ABSPATH' ) || exit;

class SGC_IIP_Currency {

    // Country code → Currency
    const COUNTRY_CURRENCY = [
        'IN'  => 'INR', 'US'  => 'USD', 'GB'  => 'GBP',
        'DE'  => 'EUR', 'FR'  => 'EUR', 'IT'  => 'EUR', 'ES'  => 'EUR',
        'NL'  => 'EUR', 'BE'  => 'EUR', 'AT'  => 'EUR', 'CH'  => 'EUR',
        'AE'  => 'USD', 'SA'  => 'USD', 'QA'  => 'USD', 'KW'  => 'USD',
        'BH'  => 'USD', 'OM'  => 'USD', 'EG'  => 'USD',
        'AU'  => 'USD', 'NZ'  => 'USD', 'JP'  => 'USD', 'CN'  => 'USD',
        'SG'  => 'USD', 'MY'  => 'USD', 'TH'  => 'USD', 'ID'  => 'USD',
        'PH'  => 'USD', 'VN'  => 'USD', 'KR'  => 'USD',
        'ZA'  => 'USD', 'NG'  => 'USD', 'KE'  => 'USD', 'ET'  => 'USD',
        'BR'  => 'USD', 'MX'  => 'USD', 'AR'  => 'USD', 'CL'  => 'USD',
        'CA'  => 'USD', 'PK'  => 'USD', 'BD'  => 'USD', 'LK'  => 'USD',
    ];

    // Country name → ISO code (common ones buyers type)
    const COUNTRY_NAMES = [
        'india'                => 'IN',
        'united states'        => 'US', 'usa'      => 'US', 'u.s.a'    => 'US',
        'united kingdom'       => 'GB', 'uk'       => 'GB', 'england'  => 'GB',
        'germany'              => 'DE', 'france'   => 'FR',
        'uae'                  => 'AE', 'united arab emirates' => 'AE', 'dubai' => 'AE',
        'saudi arabia'         => 'SA', 'ksa'      => 'SA',
        'australia'            => 'AU', 'canada'   => 'CA',
        'singapore'            => 'SG', 'malaysia' => 'MY',
        'china'                => 'CN', 'japan'    => 'JP', 'korea'    => 'KR',
        'pakistan'             => 'PK', 'bangladesh' => 'BD',
        'south africa'         => 'ZA', 'nigeria'  => 'NG', 'kenya'    => 'KE',
        'brazil'               => 'BR', 'mexico'   => 'MX', 'chile'    => 'CL',
        'qatar'                => 'QA', 'kuwait'   => 'KW', 'oman'     => 'OM',
        'ethiopia'             => 'ET', 'egypt'    => 'EG',
        'philippines'          => 'PH', 'vietnam'  => 'VN', 'thailand' => 'TH',
        'indonesia'            => 'ID', 'sri lanka' => 'LK',
    ];

    // ── Get currency for country code ─────────────────────────────────────
    public static function get_for_country( $country_code ) {
        return self::COUNTRY_CURRENCY[ strtoupper( $country_code ) ] ?? 'USD';
    }

    // ── Country name → ISO code ───────────────────────────────────────────
    public static function country_to_code( $country_name ) {
        if ( ! $country_name ) return '';
        $lower = strtolower( trim( $country_name ) );
        return strtoupper( self::COUNTRY_NAMES[ $lower ] ?? substr( $country_name, 0, 2 ) );
    }

    // ── IP → Country code ─────────────────────────────────────────────────
    public static function ip_to_country( $ip = null ) {
        if ( ! $ip ) $ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1' );

        // Use ipapi.co — free 1000/day, no key needed
        $url      = 'https://ipapi.co/' . $ip . '/json/';
        $cached   = get_transient( 'sgc_iip_ip_' . md5( $ip ) );
        if ( $cached ) return $cached;

        $response = wp_remote_get( $url, [ 'timeout' => 5, 'user-agent' => 'SGC-IIP/1.0' ] );
        if ( is_wp_error( $response ) ) return 'US';

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        $code = sanitize_key( $data['country_code'] ?? 'US' );

        set_transient( 'sgc_iip_ip_' . md5( $ip ), $code, DAY_IN_SECONDS );
        return $code;
    }

    // ── Get exchange rate (INR per 1 foreign currency) ────────────────────
    public static function get_rate( $currency ) {
        $currency = strtoupper( $currency );
        if ( 'INR' === $currency ) return 1.0;

        global $wpdb;
        $rate = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore
            "SELECT rate_to_inr FROM " . SGC_IIP_DB::t('currency_rates') . " WHERE currency=%s",
            $currency
        ) );

        if ( $rate ) return floatval( $rate );

        // Fallback: Product Manager Pro rate takes priority (shared config)
        $pm_usd_rate = floatval( get_option( 'sgc_pm_usd_rate', 0 ) );
        $fallbacks = [
            'USD' => floatval( get_option( 'sgc_iip_usd_rate', $pm_usd_rate ?: 83.50 ) ),
            'GBP' => floatval( get_option( 'sgc_iip_gbp_rate', 106.50 ) ),
            'EUR' => floatval( get_option( 'sgc_iip_eur_rate', 90.20 ) ),
        ];
        return $fallbacks[ $currency ] ?? ( $pm_usd_rate ?: 83.50 );
    }

    // ── Convert amount between currencies ─────────────────────────────────
    public static function convert( $amount, $from, $to ) {
        if ( $from === $to ) return $amount;
        $inr_amount = 'INR' === $from ? $amount : $amount * self::get_rate( $from );
        return 'INR' === $to ? $inr_amount : $inr_amount / self::get_rate( $to );
    }

    // ── Format currency display ───────────────────────────────────────────
    public static function format( $amount, $currency ) {
        $symbols = [ 'USD' => '$', 'INR' => '₹', 'GBP' => '£', 'EUR' => '€' ];
        $symbol  = $symbols[ strtoupper( $currency ) ] ?? $currency . ' ';
        return $symbol . number_format( $amount, 2 );
    }

    // ── Refresh rates from ExchangeRate-API (free 1500/month) ─────────────
    public static function refresh_rates() {
        $api_key = get_option( 'sgc_iip_currency_api_key', '' );
        if ( ! $api_key ) {
            // Use free no-key endpoint (limited)
            $url = 'https://api.exchangerate-api.com/v4/latest/INR';
        } else {
            $url = 'https://v6.exchangerate-api.com/v6/' . $api_key . '/latest/INR';
        }

        $response = wp_remote_get( $url, [ 'timeout' => 10 ] );
        if ( is_wp_error( $response ) ) return false;

        $data  = json_decode( wp_remote_retrieve_body( $response ), true );
        $rates = $data['rates'] ?? ( $data['conversion_rates'] ?? [] );
        if ( empty( $rates ) ) return false;

        global $wpdb;
        $currencies = [ 'USD', 'GBP', 'EUR', 'AED', 'SAR', 'SGD', 'AUD', 'CAD' ];
        foreach ( $currencies as $cur ) {
            if ( isset( $rates[ $cur ] ) && $rates[ $cur ] > 0 ) {
                // rates are INR/currency — we want INR per 1 foreign = 1/rate * ... 
                // ExchangeRate-API: INR base, so rates[USD] = how many USD per 1 INR
                // We want: how many INR per 1 USD = 1 / rates[USD]
                $inr_per_foreign = 1 / floatval( $rates[ $cur ] );
                $wpdb->replace( SGC_IIP_DB::t('currency_rates'), [ // phpcs:ignore
                    'currency'    => $cur,
                    'rate_to_inr' => round( $inr_per_foreign, 4 ),
                ] );
                // Also update legacy options
                if ( in_array( $cur, [ 'USD', 'GBP', 'EUR' ], true ) ) {
                    update_option( 'sgc_iip_' . strtolower( $cur ) . '_rate', round( $inr_per_foreign, 2 ) );
                }
            }
        }
        update_option( 'sgc_iip_rates_updated', current_time('mysql') );
        return true;
    }

    // ── AJAX ──────────────────────────────────────────────────────────────
    public static function register_ajax() {
        add_action( 'wp_ajax_sgc_iip_get_rates',    [ __CLASS__, 'ajax_get_rates'    ] );
        add_action( 'wp_ajax_sgc_iip_refresh_rates', [ __CLASS__, 'ajax_refresh_rates'] );
        add_action( 'wp_ajax_sgc_iip_save_rates',   [ __CLASS__, 'ajax_save_rates'   ] );
    }

    public static function ajax_get_rates() {
        check_ajax_referer( 'sgc_iip_nonce', 'nonce' );
        global $wpdb;
        $rates = $wpdb->get_results( "SELECT * FROM " . SGC_IIP_DB::t('currency_rates'), ARRAY_A ); // phpcs:ignore
        wp_send_json_success( [
            'rates'      => $rates,
            'updated_at' => get_option( 'sgc_iip_rates_updated', 'Never' ),
        ] );
    }

    public static function ajax_refresh_rates() {
        check_ajax_referer( 'sgc_iip_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'Unauthorized', 403 );
        $ok = self::refresh_rates();
        wp_send_json_success( $ok ? 'Rates refreshed.' : 'Refresh failed — check API key.' );
    }

    public static function ajax_save_rates() {
        check_ajax_referer( 'sgc_iip_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'Unauthorized', 403 );
        $fields = [ 'sgc_iip_usd_rate', 'sgc_iip_gbp_rate', 'sgc_iip_eur_rate', 'sgc_iip_currency_api_key' ];
        foreach ( $fields as $f ) {
            if ( isset( $_POST[ $f ] ) ) update_option( $f, sanitize_text_field( wp_unslash( $_POST[ $f ] ) ) );
        }
        wp_send_json_success( 'Rates saved.' );
    }
}

SGC_IIP_Currency::register_ajax();
