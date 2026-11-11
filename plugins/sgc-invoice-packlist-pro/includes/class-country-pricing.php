<?php
/**
 * SGC IIP — Country-wise Export Pricing Engine v1.0.0
 *
 * Maps buyer country → region → region-specific USD price per product.
 * Price fetch priority: region price → generic B2B price → regular WC price ÷ USD rate.
 *
 * Regions and their countries (based on SGC LABS® actual export markets):
 *  - gulf:    UAE, Saudi Arabia, Kuwait, Qatar, Bahrain, Oman, Iraq, Jordan
 *  - africa:  Kenya, Nigeria, Tanzania, Ethiopia, Ghana, Egypt, Uganda, South Africa
 *  - sea:     India (domestic — not export), Bangladesh, Sri Lanka, Nepal,
 *             Malaysia, Singapore, Thailand, Philippines, Vietnam, Indonesia
 *  - europe:  Germany, UK, France, Netherlands, Sweden, Italy, Spain
 *  - other:   Everything else (Canada, USA, Australia…)
 *
 * Meta keys stored per product in Product Manager Pro:
 *  _sgc_export_price_gulf    float USD
 *  _sgc_export_price_africa  float USD
 *  _sgc_export_price_sea     float USD
 *  _sgc_export_price_europe  float USD
 *
 * @package SGC_IIP
 * @since   1.8.5
 */
defined( 'ABSPATH' ) || exit;

class SGC_IIP_Country_Pricing {

    // ── Region → Country map ─────────────────────────────────────────────────
    const REGIONS = [
        'gulf' => [
            'ae','sa','kw','qa','bh','om','iq','jo','ye','lb','sy',
            'united arab emirates','saudi arabia','kuwait','qatar',
            'bahrain','oman','iraq','jordan','yemen','lebanon','syria',
        ],
        'africa' => [
            'ke','ng','tz','et','gh','eg','ug','za','zm','zw','mz',
            'sd','cm','ci','sn','rw','mg',
            'kenya','nigeria','tanzania','ethiopia','ghana','egypt',
            'uganda','south africa','zambia','zimbabwe','mozambique',
            'sudan','cameroon','ivory coast','senegal','rwanda','madagascar',
        ],
        'sea' => [
            'bd','lk','np','my','sg','th','ph','vn','id','mm','kh','la',
            'bangladesh','sri lanka','nepal','malaysia','singapore',
            'thailand','philippines','vietnam','indonesia','myanmar',
            'cambodia','laos',
        ],
        'europe' => [
            'de','gb','fr','nl','se','it','es','ch','at','be','pl','pt',
            'germany','uk','united kingdom','france','netherlands','sweden',
            'italy','spain','switzerland','austria','belgium','poland','portugal',
        ],
    ];

    /**
     * Map a country name/code → region slug.
     * Returns 'other' if no match found.
     */
    public static function country_to_region( string $country ): string {
        $needle = strtolower( trim( $country ) );
        if ( empty( $needle ) ) return 'other';
        foreach ( self::REGIONS as $region => $countries ) {
            if ( in_array( $needle, $countries, true ) ) return $region;
        }
        return 'other';
    }

    /**
     * Get export USD price for a product in a given region.
     * Fallback chain: region meta → generic B2B meta → WC price ÷ USD rate.
     *
     * @param int    $product_id  WooCommerce product ID.
     * @param string $region      'gulf' | 'africa' | 'sea' | 'europe' | 'other'.
     * @param float  $usd_rate    INR per USD (for fallback calculation).
     * @return float              Price in USD.
     */
    public static function get_price( int $product_id, string $region, float $usd_rate = 83.5 ): float {
        // 1. Region-specific price
        if ( in_array( $region, [ 'gulf', 'africa', 'sea', 'europe' ], true ) ) {
            $meta = get_post_meta( $product_id, '_sgc_export_price_' . $region, true );
            if ( ! empty( $meta ) && floatval( $meta ) > 0 ) {
                return round( floatval( $meta ), 2 );
            }
        }

        // 2. Generic B2B price (INR) → convert to USD
        $b2b_inr = get_post_meta( $product_id, '_sgclabs_b2b_pricing', true );
        if ( ! empty( $b2b_inr ) && floatval( $b2b_inr ) > 0 && $usd_rate > 0 ) {
            return round( floatval( $b2b_inr ) / $usd_rate, 2 );
        }

        // 3. WooCommerce regular price → USD
        if ( function_exists( 'wc_get_product' ) ) {
            $product = wc_get_product( $product_id );
            if ( $product ) {
                $price_inr = floatval( $product->get_regular_price() );
                if ( $price_inr > 0 && $usd_rate > 0 ) {
                    return round( $price_inr / $usd_rate, 2 );
                }
            }
        }

        return 0.0;
    }

    /**
     * Get full pricing info for a product + country — used in IIP product search.
     *
     * @return array {
     *   region:         string
     *   price_usd:      float
     *   price_source:   string  'region_rate' | 'b2b_rate' | 'regular_rate' | 'none'
     *   has_region_rate: bool
     * }
     */
    public static function get_price_info( int $product_id, string $country, float $usd_rate = 83.5 ): array {
        $region = self::country_to_region( $country );

        // Check what source we'll use
        $region_meta = '';
        if ( in_array( $region, [ 'gulf', 'africa', 'sea', 'europe' ], true ) ) {
            $region_meta = get_post_meta( $product_id, '_sgc_export_price_' . $region, true );
        }
        $b2b_inr = get_post_meta( $product_id, '_sgclabs_b2b_pricing', true );

        if ( ! empty( $region_meta ) && floatval( $region_meta ) > 0 ) {
            $source = 'region_rate';
        } elseif ( ! empty( $b2b_inr ) && floatval( $b2b_inr ) > 0 ) {
            $source = 'b2b_rate';
        } else {
            $source = 'regular_rate';
        }

        return [
            'region'          => $region,
            'price_usd'       => self::get_price( $product_id, $region, $usd_rate ),
            'price_source'    => $source,
            'has_region_rate' => ( $source === 'region_rate' ),
            'all_region_prices' => [
                'gulf'    => floatval( get_post_meta( $product_id, '_sgc_export_price_gulf',    true ) ),
                'africa'  => floatval( get_post_meta( $product_id, '_sgc_export_price_africa',  true ) ),
                'sea'     => floatval( get_post_meta( $product_id, '_sgc_export_price_sea',     true ) ),
                'europe'  => floatval( get_post_meta( $product_id, '_sgc_export_price_europe',  true ) ),
            ],
        ];
    }

    /**
     * Render region price input fields HTML — for use in PM B2B tab.
     */
    public static function render_region_price_fields( int $product_id ): string {
        $regions = [
            'gulf'    => '🌙 Gulf (UAE, KSA, Kuwait…)',
            'africa'  => '🌍 Africa (Kenya, Nigeria…)',
            'sea'     => '🌏 SE Asia (Bangladesh, Sri Lanka…)',
            'europe'  => '🇪🇺 Europe (Germany, UK…)',
        ];
        $html = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px">';
        foreach ( $regions as $key => $label ) {
            $val  = get_post_meta( $product_id, '_sgc_export_price_' . $key, true );
            $html .= sprintf(
                '<div>
                    <label style="font-size:11px;color:#666;display:block;margin-bottom:3px">%s <span style="color:#999">(USD)</span></label>
                    <input type="number" step="0.01" min="0"
                           name="_sgc_export_price_%s"
                           value="%s"
                           style="width:100%%"
                           placeholder="0.00">
                </div>',
                esc_html( $label ),
                esc_attr( $key ),
                esc_attr( $val ?: '' )
            );
        }
        $html .= '</div>';
        return $html;
    }
}
