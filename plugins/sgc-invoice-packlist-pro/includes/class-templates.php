<?php
/**
 * SGC IIP — Smart Quote Templates v1.0.0
 *
 * Default templates for common quote scenarios.
 * Payment terms auto-calculated by order value.
 *
 * @package SGC_IIP
 * @version 1.0.0 | Build: 20260517-0600
 */

defined('ABSPATH') || exit;

class SGC_IIP_Templates {

    // ── All Templates ─────────────────────────────────────────────────────
    public static function get_all(): array {
        $company = get_option('sgc_iip_company_name', 'SGC LABS® Scientific Glassware');
        $email   = get_option('sgc_iip_company_email', 'info@labsglass.com');
        $phone   = get_option('sgc_iip_company_phone', '+91 999 641 6935');
        $web     = 'labsglass.com';

        $sign = "\n\nBest regards,\n{$company}\n{$email} | {$phone}\n{$web}";

        return [

            // ── 1. Export FOB Delhi ───────────────────────────────────────
            'export-fob' => [
                'name'     => '🌍 Export — FOB Delhi',
                'icon'     => '🌍',
                'channels' => ['email','whatsapp'],
                'incoterm' => 'FOB',
                'currency' => 'USD',
                'type'     => 'export',
                'fields'   => ['buyer_name','buyer_country','items','currency'],
                'subject'  => 'Proforma Invoice / Price List — {items_summary} | SGC LABS® Scientific Glassware',
                'body'     => "Dear {buyer_name},

Thank you for your inquiry regarding laboratory glassware.

Please find our competitive pricing below:

{items_table}

TERMS & CONDITIONS
──────────────────
Incoterm      : FOB New Delhi, India
Currency      : {currency}
Payment Terms : {payment_terms}
Validity      : 30 days from date of quotation
Packing       : Standard export packing (double box, foam padded)
Quality       : Borosilicate 3.3 glass | ISO 3585 | ASTM E438 certified
Lead Time     : 15–20 working days after order confirmation

NOTE: Freight charges are NOT included. We will provide freight quote once packing list is ready (after order confirmation). Buyer may arrange own freight or we can assist with shipping coordination.

BANK DETAILS FOR ADVANCE PAYMENT
{bank_details}

We look forward to your valuable order.{$sign}",
            ],

            // ── 2. Export EXW ─────────────────────────────────────────────
            'export-exw' => [
                'name'     => '🏭 Export — EXW Ambala',
                'icon'     => '🏭',
                'channels' => ['email','whatsapp'],
                'incoterm' => 'EXW',
                'currency' => 'USD',
                'type'     => 'export',
                'fields'   => ['buyer_name','buyer_country','items','currency'],
                'subject'  => 'Price Quotation (Ex-Works) — {items_summary} | SGC LABS®',
                'body'     => "Dear {buyer_name},

Thank you for your interest in our Borosilicate 3.3 laboratory glassware.

Please find our Ex-Works pricing below:

{items_table}

TERMS & CONDITIONS
──────────────────
Incoterm      : EXW Ambala, Haryana, India
Currency      : {currency}
Payment Terms : {payment_terms}
Validity      : 30 days
Packing       : Standard export packing included
Quality       : Borosilicate 3.3 | ISO 3585 certified
Lead Time     : 15–20 working days

Note: All freight, insurance, customs clearance and delivery charges are buyer's responsibility under EXW terms.

BANK DETAILS
{bank_details}

Thank you for considering SGC LABS®.{$sign}",
            ],

            // ── 3. Domestic INR ───────────────────────────────────────────
            'domestic' => [
                'name'     => '🇮🇳 Domestic — INR',
                'icon'     => '🇮🇳',
                'channels' => ['email','whatsapp'],
                'incoterm' => 'EXW',
                'currency' => 'INR',
                'type'     => 'domestic',
                'fields'   => ['buyer_name','buyer_city','items'],
                'subject'  => 'Quotation — {items_summary} | SGC LABS® Scientific Glassware',
                'body'     => "Dear {buyer_name},

Thank you for your inquiry. Please find our quotation below:

{items_table}

TERMS & CONDITIONS
──────────────────
Basis         : Ex-Works, Ambala, Haryana
GST           : 18% applicable (as per HSN code)
Payment Terms : {payment_terms}
Validity      : 30 days
Packing       : Standard packing
Quality       : Borosilicate 3.3 | ISO 3585 certified
Delivery      : 10–15 working days after order confirmation

Note: Transport charges extra. We can arrange transport — charges will be added in final invoice.

BANK DETAILS FOR ADVANCE
{bank_details}

We look forward to your order.{$sign}",
            ],

            // ── 4. Sample Order ───────────────────────────────────────────
            'sample' => [
                'name'     => '📦 Sample Order',
                'icon'     => '📦',
                'channels' => ['email','whatsapp'],
                'incoterm' => 'DDP',
                'currency' => 'USD',
                'type'     => 'export',
                'fields'   => ['buyer_name','buyer_country','items'],
                'subject'  => 'Sample Order Quotation — SGC LABS® Scientific Glassware',
                'body'     => "Dear {buyer_name},

Thank you for your interest in SGC LABS® laboratory glassware.

We are pleased to offer you a sample order for your evaluation:

{items_table}

SAMPLE ORDER TERMS
──────────────────
Payment       : 100% advance (sample orders)
Shipping      : DDP (Delivered Duty Paid) — we handle all freight & customs
Packing       : Individual protective packing for safe delivery
Quality Check : 100% visual inspection before dispatch
Lead Time     : 7–10 working days

We are confident you will be satisfied with our quality. Upon sample approval, we offer competitive bulk pricing.

PAYMENT DETAILS
{bank_details}

Looking forward to a long-term business relationship.{$sign}",
            ],

            // ── 5. Follow-up ──────────────────────────────────────────────
            'followup' => [
                'name'     => '🔄 Follow-up (PI sent)',
                'icon'     => '🔄',
                'channels' => ['email','whatsapp'],
                'incoterm' => '',
                'currency' => '',
                'type'     => 'followup',
                'fields'   => ['buyer_name','pi_number','pi_amount'],
                'subject'  => 'Follow-up: Proforma Invoice {pi_number} — SGC LABS®',
                'body'     => "Dear {buyer_name},

I hope this message finds you well.

I am writing to follow up on our Proforma Invoice *{pi_number}* sent earlier for *{pi_amount}*.

We would appreciate your feedback:
• Have you had a chance to review the quotation?
• Do you require any modifications to specifications or pricing?
• Is there anything else we can assist with?

We remain committed to providing you with the best quality Borosilicate 3.3 glassware at competitive prices. Our team is ready to process your order immediately upon confirmation.

Please feel free to reach out with any questions.{$sign}",
            ],

            // ── 6. Payment Reminder ───────────────────────────────────────
            'payment-reminder' => [
                'name'     => '💰 Payment Reminder',
                'icon'     => '💰',
                'channels' => ['email','whatsapp'],
                'incoterm' => '',
                'currency' => '',
                'type'     => 'reminder',
                'fields'   => ['buyer_name','pi_number','advance_due'],
                'subject'  => 'Payment Reminder — Advance Due | {pi_number} | SGC LABS®',
                'body'     => "Dear {buyer_name},

This is a friendly reminder regarding the advance payment for your order.

Order Reference : {pi_number}
Advance Due     : {advance_due}
Payment Terms   : {payment_terms}

Kindly arrange the advance payment at your earliest convenience so we can begin production and ensure timely delivery.

PAYMENT DETAILS
{bank_details}

Please share the payment confirmation/UTR number after transfer.

We appreciate your prompt response.{$sign}",
            ],
        ]; // end return array
    } // end get_all()

    // ── Render template with data ─────────────────────────────────────────
    public static function render( string $template_id, array $data ): array {
        $templates = self::get_all();
        if ( ! isset( $templates[ $template_id ] ) ) {
            return [ 'error' => 'Template not found' ];
        }
        $tpl = $templates[ $template_id ];

        // Payment terms
        $amount       = floatval( $data['amount'] ?? 0 );
        $currency     = $data['currency'] ?? $tpl['currency'] ?? 'USD';
        $payment_terms = self::get_payment_terms( $amount, $currency );

        // Bank details
        $bank_details = self::get_bank_details( $tpl['type'] );

        // Items table
        $items_table  = '(Add your items here)';
        $items_summary = 'Laboratory Glassware';
        if ( ! empty( $data['items'] ) ) {
            $items_table   = self::render_items_table( $data['items'], $currency );
            $items_summary = implode(', ', array_slice(
                array_column( $data['items'], 'name' ), 0, 2
            ));
            if ( count($data['items']) > 2 ) $items_summary .= ' & more';
        }

        // Replace placeholders
        $replacements = [
            '{buyer_name}'     => $data['buyer_name']     ?? 'Sir/Ma\'am',
            '{buyer_country}'  => $data['buyer_country']  ?? '',
            '{buyer_city}'     => $data['buyer_city']     ?? '',
            '{currency}'       => $currency,
            '{incoterm}'       => $tpl['incoterm'],
            '{payment_terms}'  => $payment_terms,
            '{bank_details}'   => $bank_details,
            '{items_table}'    => $items_table,
            '{items_summary}'  => $items_summary,
            '{pi_number}'      => $data['pi_number']      ?? '—',
            '{pi_amount}'      => $data['pi_amount']      ?? '—',
            '{advance_due}'    => $data['advance_due']    ?? '—',
        ];

        foreach ( $replacements as $key => $val ) {
            $body = str_replace( $key, $val, $body );
        }

        $subject = $tpl['subject'];
        foreach ( $replacements as $key => $val ) {
            $subject = str_replace( $key, $val, $subject );
        }

        return [
            'subject' => $subject,
            'body'    => $body,
            'type'    => $tpl['type'],
            'incoterm'=> $tpl['incoterm'],
            'currency'=> $currency,
        ];
    }

    // ── Payment terms (v1.9.3: was referenced but never implemented → fatal) ──
    public static function get_payment_terms( float $amount = 0, string $currency = 'USD' ): string {
        $currency = strtoupper( $currency );
        if ( 'INR' === $currency ) {
            return get_option( 'sgc_iip_payment_terms', '30% advance, 70% before dispatch' );
        }
        // Normalise to USD for the threshold check when another export currency is used.
        $amount_usd = $amount;
        if ( 'USD' !== $currency && $amount > 0 && class_exists( 'SGC_IIP_Currency' ) && method_exists( 'SGC_IIP_Currency', 'convert' ) ) {
            $conv = SGC_IIP_Currency::convert( $amount, $currency, 'USD' );
            if ( is_numeric( $conv ) && $conv > 0 ) $amount_usd = (float) $conv;
        }
        if ( class_exists( 'SGC_IIP_Proforma' ) ) {
            return SGC_IIP_Proforma::get_payment_terms( $amount_usd );
        }
        $threshold = floatval( get_option( 'sgc_iip_payment_threshold_usd', 500 ) );
        return ( $amount_usd > 0 && $amount_usd < $threshold )
            ? '100% advance payment (order value below $' . number_format( $threshold, 0 ) . ')'
            : '50% advance with order, 50% before delivery';
    }

    // ── Bank details block for quote templates (plain text) ───────────────
    public static function get_bank_details( string $type = 'export' ): string {
        $is_dom = ( 'domestic' === $type );
        $ben    = get_option( $is_dom ? 'sgc_iip_dom_bank_beneficiary' : 'sgc_iip_export_bank_beneficiary', get_option( 'sgc_iip_bank_beneficiary', 'SGC Labs' ) );
        $bank   = get_option( $is_dom ? 'sgc_iip_dom_bank_name'        : 'sgc_iip_export_bank_name',        get_option( 'sgc_iip_bank_name', '' ) );
        $acc    = get_option( $is_dom ? 'sgc_iip_dom_bank_account'     : 'sgc_iip_export_bank_account',     get_option( 'sgc_iip_bank_account', '' ) );
        $branch = get_option( $is_dom ? 'sgc_iip_dom_bank_branch'      : 'sgc_iip_export_bank_branch',      get_option( 'sgc_iip_bank_branch', 'Ambala, India' ) );
        $lines  = [
            'Beneficiary : ' . $ben,
            'Bank        : ' . trim( $bank . ( $branch ? ', ' . $branch : '' ), ', ' ),
            'Account No  : ' . $acc,
        ];
        if ( $is_dom ) {
            $lines[] = 'IFSC Code   : ' . get_option( 'sgc_iip_dom_bank_ifsc', get_option( 'sgc_iip_bank_ifsc', '' ) );
            $upi = get_option( 'sgc_iip_upi_id', '' );
            if ( $upi ) $lines[] = 'UPI / GPay  : ' . $upi;
        } else {
            $lines[] = 'SWIFT Code  : ' . get_option( 'sgc_iip_export_bank_swift', get_option( 'sgc_iip_bank_swift', '' ) );
            $pp = get_option( 'sgc_iip_paypal_email', '' );
            $ws = get_option( 'sgc_iip_wise_email', '' );
            if ( $pp ) $lines[] = 'PayPal      : ' . $pp . ' (small orders)';
            if ( $ws ) $lines[] = 'Wise        : ' . $ws;
        }
        return implode( "\n", $lines );
    }

    // ── Items table renderer ──────────────────────────────────────────────
    private static function render_items_table( array $items, string $currency = 'USD' ): string {
        $sym = $currency === 'INR' ? '₹' : '$';
        $rows = '';
        $subtotal = 0;

        foreach ( $items as $i => $item ) {
            $qty   = floatval( $item['qty']  ?? 1 );
            $rate  = floatval( $item['rate'] ?? 0 );
            $total = $qty * $rate;
            $subtotal += $total;
            $bg = ( $i % 2 === 0 ) ? '#ffffff' : '#f8f9fa';
            $rows .= sprintf(
                "| %-40s | %6s | %10s | %10s |\n",
                substr($item['name'] ?? '', 0, 40),
                $qty . ' ' . ($item['unit'] ?? 'pcs'),
                $rate  > 0 ? $sym . number_format($rate,  2) : '—',
                $total > 0 ? $sym . number_format($total, 2) : '—'
            );
        }

        $header = sprintf("| %-40s | %6s | %10s | %10s |\n", 'Product / Description', 'Qty', 'Unit Price', 'Total');
        $sep    = str_repeat('-', strlen($header)-1) . "\n";
        $table  = $sep . $header . $sep . $rows . $sep;

        if ( $subtotal > 0 ) {
            $table .= sprintf("%56s %10s\n", 'SUBTOTAL :', $sym . number_format($subtotal, 2));
            $table .= "(Freight charges will be advised separately after order confirmation)\n";
        }

        return $table;
    }

    // ── AJAX: Get all templates (for JS) ─────────────────────────────────
    public static function ajax_get_templates(): void {
        check_ajax_referer('sgc_iip_nonce','nonce');
        if ( ! current_user_can('manage_woocommerce') ) { wp_send_json_error('Unauthorized'); return; }

        $templates = self::get_all();
        $list = [];
        foreach ( $templates as $id => $tpl ) {
            $list[] = [
                'id'       => $id,
                'name'     => $tpl['name'],
                'icon'     => $tpl['icon'],
                'type'     => $tpl['type'],
                'currency' => $tpl['currency'],
                'incoterm' => $tpl['incoterm'],
                'fields'   => $tpl['fields'],
            ];
        }
        wp_send_json_success( $list );
    }

    // ── AJAX: Render a template ───────────────────────────────────────────
    public static function ajax_render_template(): void {
        check_ajax_referer('sgc_iip_nonce','nonce');
        if ( ! current_user_can('manage_woocommerce') ) { wp_send_json_error('Unauthorized'); return; }

        $template_id = sanitize_key( $_POST['template_id'] ?? '' );
        $items_json  = sanitize_textarea_field( wp_unslash( $_POST['items'] ?? '[]' ) );
        $items       = json_decode( $items_json, true ) ?: [];

        $data = [
            'buyer_name'    => sanitize_text_field( wp_unslash( $_POST['buyer_name']    ?? 'Sir/Ma\'am' ) ),
            'buyer_country' => sanitize_text_field( wp_unslash( $_POST['buyer_country'] ?? '' ) ),
            'buyer_city'    => sanitize_text_field( wp_unslash( $_POST['buyer_city']    ?? '' ) ),
            'currency'      => sanitize_key( $_POST['currency'] ?? 'USD' ),
            'amount'        => floatval( $_POST['amount'] ?? 0 ),
            'pi_number'     => sanitize_text_field( wp_unslash( $_POST['pi_number']  ?? '' ) ),
            'pi_amount'     => sanitize_text_field( wp_unslash( $_POST['pi_amount']  ?? '' ) ),
            'advance_due'   => sanitize_text_field( wp_unslash( $_POST['advance_due']?? '' ) ),
            'items'         => $items,
        ];

        $result = self::render( $template_id, $data );
        if ( isset($result['error']) ) { wp_send_json_error($result['error']); return; }

        wp_send_json_success( $result );
    }

    // ── AJAX: Get payment terms for amount ────────────────────────────────
    public static function ajax_get_payment_terms(): void {
        check_ajax_referer('sgc_iip_nonce','nonce');
        $amount   = floatval( $_POST['amount']   ?? 0 );
        $currency = sanitize_key( $_POST['currency'] ?? 'USD' );
        wp_send_json_success([
            'terms'     => self::get_payment_terms($amount, $currency),
            'threshold' => get_option('sgc_iip_payment_threshold_usd', 500),
        ]);
    }
}

// Register AJAX
add_action('wp_ajax_sgc_iip_get_templates',     ['SGC_IIP_Templates', 'ajax_get_templates']);
add_action('wp_ajax_sgc_iip_render_template',   ['SGC_IIP_Templates', 'ajax_render_template']);
add_action('wp_ajax_sgc_iip_get_payment_terms', ['SGC_IIP_Templates', 'ajax_get_payment_terms']);
