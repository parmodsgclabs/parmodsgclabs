<?php
/**
 * SGC IIP — GST Engine v1.0.0
 *
 * Handles:
 *  - CGST + SGST (same state: company state = buyer state)
 *  - IGST         (inter-state OR export — always zero-rated for export)
 *  - HSN code validation for laboratory glassware
 *  - GST number (GSTIN) format validation
 *
 * Company state: Haryana (HR) — hardcoded default, overridable via setting.
 *
 * @package SGC_IIP
 * @since   1.8.5
 */
defined( 'ABSPATH' ) || exit;

class SGC_IIP_GST {

    // ── Indian state codes ────────────────────────────────────────────────────
    const STATE_CODES = [
        'AN' => 'Andaman & Nicobar','AP' => 'Andhra Pradesh','AR' => 'Arunachal Pradesh',
        'AS' => 'Assam','BR' => 'Bihar','CH' => 'Chandigarh','CG' => 'Chhattisgarh',
        'DD' => 'Daman & Diu','DL' => 'Delhi','DN' => 'Dadra & Nagar Haveli',
        'GA' => 'Goa','GJ' => 'Gujarat','HP' => 'Himachal Pradesh','HR' => 'Haryana',
        'JH' => 'Jharkhand','JK' => 'Jammu & Kashmir','KA' => 'Karnataka',
        'KL' => 'Kerala','LA' => 'Ladakh','LD' => 'Lakshadweep','MH' => 'Maharashtra',
        'ML' => 'Meghalaya','MN' => 'Manipur','MP' => 'Madhya Pradesh',
        'MZ' => 'Mizoram','NL' => 'Nagaland','OD' => 'Odisha','PB' => 'Punjab',
        'PY' => 'Puducherry','RJ' => 'Rajasthan','SK' => 'Sikkim','TG' => 'Telangana',
        'TN' => 'Tamil Nadu','TR' => 'Tripura','UK' => 'Uttarakhand','UP' => 'Uttar Pradesh',
        'WB' => 'West Bengal',
    ];

    // ── Common HSN codes for laboratory glassware ─────────────────────────────
    const HSN_CODES = [
        '7017'      => 'Laboratory, hygienic or pharmaceutical glassware',
        '701710'    => 'Glassware used in laboratory (fused quartz)',
        '701720'    => 'Glassware used in laboratory (borosilicate glass)',
        '701790'    => 'Other laboratory glassware',
        '7015'      => 'Clock or watch glasses and similar glasses',
        '392390'    => 'Plastic laboratory ware',
        '901890'    => 'Other medical/surgical instruments',
    ];

    // ── Default GST rates for lab glassware ───────────────────────────────────
    const DEFAULT_RATE   = 18.00; // Borosilicate glassware: 18%
    const EXPORT_RATE    = 0.00;  // Zero-rated export

    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get company state code from settings.
     */
    public static function company_state(): string {
        return strtoupper( get_option( 'sgc_iip_company_state', 'HR' ) );
    }

    /**
     * Determine GST type: 'cgst_sgst' (same state) or 'igst' (inter-state/export).
     *
     * @param string $buyer_state  Two-letter state code. Empty = export/inter-state.
     * @param bool   $is_export    True for international orders — always igst (zero-rated).
     */
    public static function gst_type( string $buyer_state, bool $is_export = false ): string {
        if ( $is_export ) return 'igst';
        $buyer = strtoupper( trim( $buyer_state ) );
        if ( empty( $buyer ) ) return 'igst';
        return ( $buyer === self::company_state() ) ? 'cgst_sgst' : 'igst';
    }

    /**
     * Calculate GST breakdown from taxable amount + rate.
     *
     * @return array {
     *   gst_type:    string   'cgst_sgst' | 'igst'
     *   gst_rate:    float    e.g. 18.0
     *   gst_amount:  float    total GST
     *   cgst_amount: float    half if cgst_sgst, else 0
     *   sgst_amount: float    half if cgst_sgst, else 0
     *   igst_amount: float    full if igst, else 0
     * }
     */
    public static function calculate(
        float $taxable_amount,
        float $gst_rate,
        string $buyer_state = '',
        bool $is_export = false
    ): array {
        $type       = self::gst_type( $buyer_state, $is_export );
        $effective  = $is_export ? 0.0 : $gst_rate;
        $gst_total  = round( $taxable_amount * $effective / 100, 2 );

        if ( 'cgst_sgst' === $type ) {
            $half = round( $gst_total / 2, 2 );
            // Ensure CGST + SGST = gst_total (rounding fix)
            return [
                'gst_type'    => 'cgst_sgst',
                'gst_rate'    => $effective,
                'gst_amount'  => $gst_total,
                'cgst_amount' => $half,
                'sgst_amount' => $gst_total - $half,
                'igst_amount' => 0.00,
            ];
        }

        return [
            'gst_type'    => 'igst',
            'gst_rate'    => $effective,
            'gst_amount'  => $gst_total,
            'cgst_amount' => 0.00,
            'sgst_amount' => 0.00,
            'igst_amount' => $gst_total,
        ];
    }

    /**
     * Validate GSTIN format: 15 chars, standard pattern.
     * 2-digit state + 10 PAN + 1 entity + Z + 1 check.
     */
    public static function validate_gstin( string $gstin ): bool {
        $gstin = strtoupper( trim( $gstin ) );
        return (bool) preg_match( '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gstin );
    }

    /**
     * Validate HSN code (4, 6, or 8 digits).
     */
    public static function validate_hsn( string $hsn ): bool {
        return (bool) preg_match( '/^[0-9]{4}([0-9]{2}([0-9]{2})?)?$/', trim( $hsn ) );
    }

    /**
     * Get HSN description if known, else empty string.
     */
    public static function hsn_description( string $hsn ): string {
        $hsn = trim( $hsn );
        return self::HSN_CODES[ $hsn ] ?? ( self::HSN_CODES[ substr( $hsn, 0, 4 ) ] ?? '' );
    }

    /**
     * Return state label from code.
     */
    public static function state_label( string $code ): string {
        return self::STATE_CODES[ strtoupper( trim( $code ) ) ] ?? $code;
    }

    /**
     * Return all state options for <select>.
     */
    public static function state_options( string $selected = '' ): string {
        $html = '<option value="">— Select state —</option>';
        foreach ( self::STATE_CODES as $code => $name ) {
            $sel   = selected( $selected, $code, false );
            $html .= "<option value=\"{$code}\" {$sel}>{$name} ({$code})</option>";
        }
        return $html;
    }
}
