<?php
/**
 * SGC LABS® Multi-Currency Switcher
 *
 * Adds a currency selector that displays prices in:
 *   INR, USD, EUR, GBP, AED, SAR, JPY, AUD
 *
 * - Stores user preference in cookie
 * - Auto-detects from browser language / Accept-Language as fallback
 * - Filters WooCommerce price displays via woocommerce_get_price_html
 * - Exchange rates: configurable in admin (no auto-fetch in v1; planned)
 * - All prices shown are display-only — checkout still in store currency
 *
 * @package   SGCLabs\International
 * @since     6.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'SGCLabs_Multi_Currency' ) ) :

final class SGCLabs_Multi_Currency {

    const COOKIE       = 'sgc_currency';
    const OPTION_RATES = 'sgclabs_currency_rates';

    /** Default rates RELATIVE TO STORE BASE CURRENCY (admin-editable). */
    public static function default_rates(): array {
        return [
            'INR' => [ 'symbol' => '₹',  'rate' => 1.00,    'label' => 'Indian Rupee'   ],
            'USD' => [ 'symbol' => '$',  'rate' => 0.012,   'label' => 'US Dollar'      ],
            'EUR' => [ 'symbol' => '€',  'rate' => 0.011,   'label' => 'Euro'           ],
            'GBP' => [ 'symbol' => '£',  'rate' => 0.0094,  'label' => 'British Pound'  ],
            'AED' => [ 'symbol' => 'د.إ','rate' => 0.044,   'label' => 'UAE Dirham'     ],
            'SAR' => [ 'symbol' => '﷼',  'rate' => 0.045,   'label' => 'Saudi Riyal'    ],
            'JPY' => [ 'symbol' => '¥',  'rate' => 1.85,    'label' => 'Japanese Yen'   ],
            'AUD' => [ 'symbol' => 'A$', 'rate' => 0.018,   'label' => 'Australian Dollar' ],
        ];
    }

    public static function rates(): array {
        $r = get_option( self::OPTION_RATES );
        return is_array( $r ) && ! empty( $r ) ? $r : self::default_rates();
    }

    public static function active_currency(): string {
        if ( ! empty( $_COOKIE[ self::COOKIE ] ) ) {
            $c = strtoupper( sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) );
            if ( isset( self::rates()[ $c ] ) ) return $c;
        }
        return self::detect_from_locale();
    }

    private static function detect_from_locale(): string {
        $lang = strtolower( substr( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 0, 5 ) );
        if ( strpos( $lang, 'ar' ) === 0 )    return 'AED';
        if ( strpos( $lang, 'en-gb' ) === 0 ) return 'GBP';
        if ( strpos( $lang, 'ja' ) === 0 )    return 'JPY';
        if ( strpos( $lang, 'en-us' ) === 0 ) return 'USD';
        if ( in_array( substr( $lang, 0, 2 ), [ 'fr','de','es','it','nl','pt' ], true ) ) return 'EUR';
        if ( strpos( $lang, 'en-au' ) === 0 ) return 'AUD';
        return 'INR';
    }

    public static function convert( float $amount_base ): array {
        $cur   = self::active_currency();
        $rates = self::rates();
        $r     = $rates[ $cur ] ?? $rates['INR'];
        return [
            'amount'   => round( $amount_base * (float) $r['rate'], 2 ),
            'currency' => $cur,
            'symbol'   => $r['symbol'],
        ];
    }

    public static function format( float $amount_base ): string {
        $c = self::convert( $amount_base );
        return $c['symbol'] . number_format_i18n( $c['amount'], 2 );
    }
}

endif;

// Bail if disabled
add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'SGCLabs_Registry' ) ) return;
    if ( ! SGCLabs_Registry::is_active( 'multi_currency' ) ) return;

    add_action( 'wp_footer', 'sgclabs_currency_render_switcher', 50 );
    add_action( 'init', 'sgclabs_currency_handle_switch' );

    // Filter WooCommerce price display (display-only conversion)
    add_filter( 'woocommerce_get_price_html', 'sgclabs_currency_filter_price', 99, 2 );
}, 30 );

function sgclabs_currency_handle_switch(): void {
    if ( empty( $_GET['sgc_set_currency'] ) ) return;
    $c = strtoupper( sanitize_key( wp_unslash( $_GET['sgc_set_currency'] ) ) );
    if ( isset( SGCLabs_Multi_Currency::rates()[ $c ] ) ) {
        $secure = is_ssl();
        setcookie( SGCLabs_Multi_Currency::COOKIE, $c, time() + 30 * DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, $secure, false );
    }
}

function sgclabs_currency_render_switcher(): void {
    if ( is_admin() ) return;
    $rates  = SGCLabs_Multi_Currency::rates();
    $active = SGCLabs_Multi_Currency::active_currency();
    ?>
    <div id="sgc-currency-switcher" style="position:fixed;bottom:20px;right:20px;z-index:9999;
         background:#fff;border:1px solid #e5e7eb;border-radius:50px;padding:6px 8px;
         box-shadow:0 4px 12px rgba(0,0,0,.08);font-family:-apple-system,sans-serif;display:flex;align-items:center;gap:6px;">
        <span style="font-size:13px;color:#475569;padding-left:8px;">💱</span>
        <select onchange="window.location.href = window.location.pathname + '?sgc_set_currency=' + this.value"
                style="border:none;background:transparent;font-size:13px;padding:4px 4px;cursor:pointer;outline:none;">
            <?php foreach ( $rates as $code => $r ): ?>
                <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $code, $active ); ?>>
                    <?php echo esc_html( $r['symbol'] ); ?> <?php echo esc_html( $code ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php
}

function sgclabs_currency_filter_price( $html, $product ) {
    // Only on frontend, only if non-INR active
    if ( is_admin() ) return $html;
    if ( ! function_exists( 'wc_get_price_to_display' ) ) return $html;
    $cur = SGCLabs_Multi_Currency::active_currency();
    $store = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'INR';
    if ( $cur === $store ) return $html;

    $price = (float) $product->get_price();
    if ( $price <= 0 ) return $html;
    $converted = SGCLabs_Multi_Currency::format( $price );
    return $html . ' <span style="font-size:.85em;color:#64748b;">≈ ' . esc_html( $converted ) . '</span>';
}
