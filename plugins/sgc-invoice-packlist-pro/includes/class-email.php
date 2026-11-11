<?php
defined( 'ABSPATH' ) || exit;

class SGC_IIP_Email {

    public static function register_ajax() {
        add_action( 'wp_ajax_sgc_iip_send_pi_email',        [ __CLASS__, 'ajax_send_pi'         ] );
        add_action( 'wp_ajax_sgc_iip_send_pi_from_panel',   [ __CLASS__, 'ajax_send_pi_from_panel' ] );
        add_action( 'wp_ajax_sgc_iip_send_custom_email',    [ __CLASS__, 'ajax_send_custom'     ] );
        add_action( 'wp_ajax_sgc_iip_save_email_settings',  [ __CLASS__, 'ajax_save_settings'   ] );
        add_action( 'wp_ajax_sgc_iip_send_dispatch_email',  [ __CLASS__, 'ajax_send_dispatch'   ] );
        // ── Compose Email (new v1.1.5) ──────────────────────────────────
        add_action( 'wp_ajax_sgc_iip_ai_polish_email',      [ __CLASS__, 'ajax_ai_polish_email' ] );
        add_action( 'wp_ajax_sgc_iip_save_compose_draft',   [ __CLASS__, 'ajax_save_compose_draft' ] );
        add_action( 'wp_ajax_sgc_iip_save_to_drive',        [ __CLASS__, 'ajax_save_to_drive' ] );
        add_action( 'wp_ajax_sgc_iip_preview_compose',      [ __CLASS__, 'ajax_preview_compose' ] );
        add_action( 'wp_ajax_sgc_iip_send_compose_email',   [ __CLASS__, 'ajax_send_compose'    ] );
    }

    private static function verify() {
        check_ajax_referer( 'sgc_iip_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );
    }

    // ── Common mailer ─────────────────────────────────────────────────────
    private static function mail( $to, $subject, $html_body, $attachments = [], $cc = null, $bcc = null ) {
        $from_name  = get_option( 'sgc_iip_company_name', 'Scientific Glass Creations' );
        $from_email = get_option( 'sgc_iip_company_email', get_option('admin_email') );
        $cc_email   = $cc ?? get_option( 'sgc_iip_cc_email', get_option('admin_email') );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        ];
        if ( $cc_email )  $headers[] = 'Cc: '  . $cc_email;
        if ( $bcc )       $headers[] = 'Bcc: ' . $bcc;

        return wp_mail( $to, $subject, $html_body, $headers, $attachments );
    }

    // ── Wrap any content in branded email template ─────────────────────────
    public static function wrap( $content, $preheader = '', $division = 'Export Division' ) {
        $company = get_option( 'sgc_iip_company_name', 'Scientific Glass Creations' );
        $website = get_option( 'sgc_iip_company_website', 'https://labsglass.com' );
        $phone   = get_option( 'sgc_iip_company_phone', '+91 999 641 6935' );
        $email   = get_option( 'sgc_iip_company_email', 'info@labsglass.com' );

        return '<!DOCTYPE html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . esc_html( $company ) . '</title></head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:Arial,sans-serif">
' . ( $preheader ? '<div style="display:none;max-height:0;overflow:hidden;">' . esc_html( $preheader ) . '</div>' : '' ) . '
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:24px 0">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08)">

  <!-- Header -->
  <tr><td style="background:#1a1a2e;padding:20px 28px;text-align:left">
    <table width="100%"><tr>
      <td><span style="color:#fff;font-size:20px;font-weight:700;letter-spacing:1px">' . esc_html( $company ) . '</span>
          <br><span style="color:#9090aa;font-size:11px">Laboratory Glassware Manufacturer &amp; Exporter | Since 2003</span></td>
      <td align="right"><span style="color:#2e7d32;font-size:10px;border:1px solid #2e7d32;padding:3px 8px;border-radius:3px">ISO CERTIFIED</span></td>
    </tr></table>
  </td></tr>

  <!-- Body -->
  <tr><td style="padding:28px">' . $content . '</td></tr>

  <!-- Footer -->
  <tr><td style="background:#f8f9fa;padding:16px 28px;border-top:1px solid #e0e0e0">
    <table width="100%"><tr>
      <td style="font-size:11px;color:#888">
        <strong style="color:#333">' . esc_html( $company ) . '</strong><br>
        Ambala, Haryana, India<br>
        📞 ' . esc_html( $phone ) . ' &nbsp;|&nbsp; ✉ ' . esc_html( $email ) . '<br>
        🌐 <a href="' . esc_url( $website ) . '" style="color:#185FA5">' . esc_url( $website ) . '</a>
      </td>
      <td align="right" style="font-size:10px;color:#bbb">
        This email was sent by<br>' . esc_html( $company ) . '<br>' . esc_html( $division ) . '
      </td>
    </tr></table>
  </td></tr>

</table>
</td></tr></table>
</body></html>';
    }

    // ── Inbox reply (branded HTML frame + thread headers) — v1.9.0 ────────
    /**
     * Send a threaded reply to a buyer through the branded SGC frame.
     *
     * @param array  $inquiry      Inquiry row (for personalisation/preheader).
     * @param string $to_email     Buyer email.
     * @param string $subject      Reply subject (usually "Re: …").
     * @param string $body_html    Editor HTML (inner content only — gets wrapped).
     * @param string $in_reply_to  Original Message-ID to thread against.
     * @return bool                wp_mail() result.
     */
    public static function send_reply( array $inquiry, string $to_email, string $subject, string $body_html, string $in_reply_to = '' ): bool {
        $from_name  = get_option( 'sgc_iip_company_name',  'SGC LABS®' );
        $from_email = get_option( 'sgc_iip_company_email', 'info@labsglass.com' );
        $bcc_auto   = $from_email;

        $name      = $inquiry['name'] ?? '';
        $greeting  = $name ? '<p style="margin:0 0 14px;color:#333;font-size:15px">Dear ' . esc_html( $name ) . ',</p>' : '';
        $content   = $greeting . $body_html;
        $preheader = wp_trim_words( wp_strip_all_tags( $body_html ), 18, '…' );

        $full_html = self::wrap( $content, $preheader, 'Export Division' );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Reply-To: ' . $from_email,
        ];
        if ( $bcc_auto && is_email( $bcc_auto ) ) {
            $headers[] = 'Bcc: ' . $bcc_auto;
        }
        // Thread the reply so it lands in the same conversation (and in Sent).
        $in_reply_to = trim( $in_reply_to );
        if ( '' !== $in_reply_to ) {
            $headers[] = 'In-Reply-To: ' . $in_reply_to;
            $headers[] = 'References: ' . $in_reply_to;
        }

        $to_header = $name ? "{$name} <{$to_email}>" : $to_email;
        return (bool) wp_mail( $to_header, $subject, $full_html, $headers );
    }

    // ── Auto-reply to buyer ───────────────────────────────────────────────
    public static function send_autoreply( array $inquiry ) {
        $name    = esc_html( $inquiry['name'] ?? 'Valued Customer' );
        $products = json_decode( $inquiry['product_details'] ?? '[]', true );
        $product  = esc_html( $products[0]['name'] ?? 'your requested products' );
        $company  = get_option( 'sgc_iip_company_name', 'Scientific Glass Creations' );
        $is_oem   = ( $inquiry['buyer_type'] ?? '' ) === 'oem';

        $oem_section = $is_oem
            ? '<div style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:14px;margin:16px 0">
                <strong style="color:#856404">🔧 OEM / Custom Order Inquiry Received</strong><br>
                <span style="font-size:13px;color:#856404">Our technical team will review your customization requirements and contact you with detailed specifications and pricing.</span>
               </div>'
            : '';

        $content = '
<p style="font-size:16px;color:#333;margin:0 0 8px">Dear <strong>' . $name . '</strong>,</p>
<p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 16px">
Thank you for contacting <strong>' . esc_html( $company ) . '</strong>. We have received your inquiry and our team is reviewing your requirements.
</p>

<div style="background:#e8f5e9;border-left:4px solid #2e7d32;padding:14px 18px;border-radius:0 6px 6px 0;margin:0 0 20px">
  <strong style="color:#1b5e20;font-size:15px">✅ Inquiry Received Successfully</strong><br>
  <span style="color:#2e7d32;font-size:13px">Product: <strong>' . $product . '</strong></span>
</div>

' . $oem_section . '

<table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;margin:0 0 20px;font-size:13px">
  <tr style="background:#f8f9fa">
    <td style="padding:10px 14px;color:#888;border:1px solid #e0e0e0;width:40%">What happens next</td>
    <td style="padding:10px 14px;border:1px solid #e0e0e0"><strong>Proforma Invoice within 24 hours</strong></td>
  </tr>
  <tr>
    <td style="padding:10px 14px;color:#888;border:1px solid #e0e0e0">Standards</td>
    <td style="padding:10px 14px;border:1px solid #e0e0e0">ASTM · ISO · DIN certified</td>
  </tr>
  <tr style="background:#f8f9fa">
    <td style="padding:10px 14px;color:#888;border:1px solid #e0e0e0">Experience</td>
    <td style="padding:10px 14px;border:1px solid #e0e0e0">Manufacturer since 2003 · 50+ countries served</td>
  </tr>
  <tr>
    <td style="padding:10px 14px;color:#888;border:1px solid #e0e0e0">Urgent query?</td>
    <td style="padding:10px 14px;border:1px solid #e0e0e0">📞 +91 999 641 6935 &nbsp;|&nbsp; 💬 WhatsApp</td>
  </tr>
</table>

<p style="color:#777;font-size:13px;line-height:1.6">
If you have any OEM specifications, custom requirements, or modifications needed — simply reply to this email with your details and our technical team will assist you promptly.
</p>

<p style="margin:20px 0 0;color:#333;font-size:14px">Warm regards,<br>
<strong>' . esc_html( $company ) . ' Team</strong></p>';

        $subject = 'Inquiry Received — ' . $company . ' | Proforma Invoice Coming Soon';
        $html    = self::wrap( $content, 'Your inquiry has been received. Proforma Invoice coming within 24 hours.' );

        return self::mail( $inquiry['email'], $subject, $html );
    }

    // ── PI Delivery email ─────────────────────────────────────────────────
    public static function send_pi( array $inquiry, array $pi, string $pdf_path, string $accept_url = '' ) {
        $name    = esc_html( $inquiry['name'] ?? 'Valued Customer' );
        $pi_num  = esc_html( $pi['pi_number'] ?? '' );
        $valid   = esc_html( $pi['valid_until'] ?? '' );
        $company = get_option( 'sgc_iip_company_name', 'Scientific Glass Creations' );

        // ── Domestic vs Export detection ─────────────────────────────
        $buyer_country  = strtolower( trim( $inquiry['country'] ?? $pi['buyer_country'] ?? '' ) );
        $is_domestic    = in_array( $buyer_country, [ 'india', 'in', 'bharat' ], true );

        // ── FIX 1: Grand Total — correct currency for domestic buyers ─
        $pi_currency    = $pi['currency'] ?? 'USD';
        $grand_total_raw= floatval( $pi['grand_total'] ?? 0 );
        $grand_inr      = floatval( $pi['grand_total_inr'] ?? 0 );
        $inr_rate       = floatval( $pi['inr_rate'] ?? get_option( 'sgc_iip_usd_rate', 83.5 ) );

        if ( $is_domestic ) {
            // Indian buyer → grand_total is already INR, show directly
            $display_total = '₹' . number_format( $grand_total_raw, 2 );
            $total_label   = 'Grand Total (INR)';
        } else {
            // Foreign buyer → show in their currency (USD/GBP/EUR etc.)
            $display_total = SGC_IIP_Currency::format( $grand_total_raw, $pi_currency );
            // Also show INR equivalent for reference
            $total_label   = 'Grand Total (' . $pi_currency . ')';
        }

        // ── FIX 2: Bank section — IFSC for domestic, SWIFT for export ─
        // v1.3.9: Use export bank for international, domestic bank for India
        if ( $is_domestic ) {
            $bank_ben  = esc_html( get_option( 'sgc_iip_dom_bank_beneficiary', get_option('sgc_iip_bank_beneficiary','SGC Labs') ) );
            $bank_nm   = esc_html( get_option( 'sgc_iip_dom_bank_name',        get_option('sgc_iip_bank_name','') ) );
            $bank_br   = esc_html( get_option( 'sgc_iip_dom_bank_branch',      'Ambala, India' ) );
            $bank_acc  = esc_html( get_option( 'sgc_iip_dom_bank_account',     get_option('sgc_iip_bank_account','') ) );
            $bank_ifsc = esc_html( get_option( 'sgc_iip_dom_bank_ifsc',        get_option('sgc_iip_bank_ifsc','') ) );
            $bank_swift= '';
            $bank_code_row = 'IFSC Code: <strong>' . $bank_ifsc . '</strong>';
        } else {
            $bank_ben  = esc_html( get_option( 'sgc_iip_export_bank_beneficiary', get_option('sgc_iip_bank_beneficiary','SGC Labs') ) );
            $bank_nm   = esc_html( get_option( 'sgc_iip_export_bank_name',        get_option('sgc_iip_bank_name','') ) );
            $bank_br   = esc_html( get_option( 'sgc_iip_export_bank_branch',      'Ambala, India' ) );
            $bank_acc  = esc_html( get_option( 'sgc_iip_export_bank_account',     get_option('sgc_iip_bank_account','') ) );
            $bank_ifsc = '';
            $bank_swift= esc_html( get_option( 'sgc_iip_export_bank_swift',       get_option('sgc_iip_bank_swift','') ) );
            $bank_addr = esc_html( get_option( 'sgc_iip_export_bank_address',     '' ) );
            $bank_code_row = 'SWIFT Code: <strong>' . $bank_swift . '</strong> &nbsp;<span style="font-size:11px;color:#888">(for international wire transfer)</span>';
        }

        // ── FIX 3: Team name — Sales for domestic, Export for international
        $team_name    = $is_domestic ? 'Sales Team' : 'Export Team';
        $team_division= $is_domestic ? 'Sales Division' : 'Export Division';

        // Incoterm row — hide for domestic if not relevant
        $incoterm_val = $pi['incoterm'] ?? '';
        $port_val     = $pi['port']     ?? '';
        $incoterm_row = '';
        if ( ! $is_domestic && $incoterm_val ) {
            $incoterm_row = '<tr><td style="color:#666;font-size:13px">Incoterm</td><td>' . esc_html( $incoterm_val ) . ' ' . esc_html( $port_val ) . '</td></tr>';
        }

        $accept_btn = $accept_url
            ? '<a href="' . esc_url( $accept_url ) . '" style="display:inline-block;background:#2e7d32;color:#fff;padding:11px 24px;border-radius:5px;text-decoration:none;font-weight:600;font-size:14px;margin:0 8px 8px 0">✅ Accept This PI Online</a>'
            : '';

        $content = '
<p style="font-size:16px;color:#333;margin:0 0 8px">Dear <strong>' . $name . '</strong>,</p>
<p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 16px">
Please find attached our <strong>Proforma Invoice ' . $pi_num . '</strong> as per your inquiry. We appreciate your interest in <strong>' . esc_html( $company ) . '</strong>.
</p>

<div style="background:#e3f2fd;border:1px solid #1565c0;border-radius:8px;padding:18px 20px;margin:0 0 20px">
  <table width="100%" cellpadding="4"><tbody>
    <tr><td style="color:#666;font-size:13px;width:40%">PI Number</td><td style="font-weight:700;color:#1a1a2e">' . $pi_num . '</td></tr>
    <tr><td style="color:#666;font-size:13px">' . $total_label . '</td><td style="font-weight:700;color:#2e7d32;font-size:16px">' . $display_total . '</td></tr>
    ' . $incoterm_row . '
    <tr><td style="color:#666;font-size:13px">Payment Terms</td><td>' . esc_html( $pi['payment_terms'] ?? '' ) . '</td></tr>
    <tr><td style="color:#666;font-size:13px">Delivery</td><td>' . esc_html( $pi['delivery_days'] ?? '' ) . '</td></tr>
    <tr><td style="color:#666;font-size:13px">Valid Until</td><td style="color:#c62828"><strong>' . $valid . '</strong></td></tr>
  </tbody></table>
</div>

<p style="margin:0 0 16px">
' . $accept_btn . '
<a href="mailto:' . esc_attr( get_option( 'sgc_iip_company_email', '' ) ) . '" style="display:inline-block;background:#1a1a2e;color:#fff;padding:11px 24px;border-radius:5px;text-decoration:none;font-weight:600;font-size:14px;margin:0 8px 8px 0">✉ Request Modification</a>
</p>

<p style="color:#555;font-size:13px;line-height:1.7">
The Proforma Invoice is attached as a PDF to this email. Please review all details carefully. If any modifications are required — quantity, specifications, packaging, or pricing — please reply to this email and we will revise the PI promptly.
</p>

' . self::build_payment_section( $inquiry, $pi ) . '

<p style="margin:16px 0 0;color:#333;font-size:14px">Thank you for considering ' . esc_html( $company ) . '.<br>
<strong>' . esc_html( $company ) . ' ' . $team_name . '</strong></p>';

        $subject     = 'Proforma Invoice ' . $pi_num . ' — ' . $company;
        $html        = self::wrap( $content, 'Proforma Invoice ' . $pi_num . ' — Total: ' . $display_total, $team_division );
        $attachments = file_exists( $pdf_path ) ? [ $pdf_path ] : [];

        return self::mail( $inquiry['email'], $subject, $html, $attachments );
    }


    // ── Payment Section Builder (for email) ──────────────────────────
    private static function build_payment_section( array $inquiry, array $pi ) {
        $is_dom      = in_array( strtolower(trim($inquiry['country'] ?? $pi['buyer_country'] ?? '')), ['india','in','bharat'], true );
        $pay_method  = $pi['payment_method'] ?? 'bank';

        // v1.3.9: Export bank for international, domestic for India
        if ( $is_dom ) {
            $bank_ben   = esc_html( get_option('sgc_iip_dom_bank_beneficiary', get_option('sgc_iip_bank_beneficiary','SGC Labs')) );
            $bank_nm    = esc_html( get_option('sgc_iip_dom_bank_name',        get_option('sgc_iip_bank_name','')) );
            $bank_acc   = esc_html( get_option('sgc_iip_dom_bank_account',     get_option('sgc_iip_bank_account','')) );
            $bank_brnch = esc_html( get_option('sgc_iip_dom_bank_branch',      'Ambala, India') );
            $bank_ifsc  = esc_html( get_option('sgc_iip_dom_bank_ifsc',        get_option('sgc_iip_bank_ifsc','')) );
            $bank_swift = '';
        } else {
            $bank_ben   = esc_html( get_option('sgc_iip_export_bank_beneficiary', get_option('sgc_iip_bank_beneficiary','SGC Labs')) );
            $bank_nm    = esc_html( get_option('sgc_iip_export_bank_name',        get_option('sgc_iip_bank_name','')) );
            $bank_acc   = esc_html( get_option('sgc_iip_export_bank_account',     get_option('sgc_iip_bank_account','')) );
            $bank_brnch = esc_html( get_option('sgc_iip_export_bank_branch',      'Ambala, India') );
            $bank_ifsc  = '';
            $bank_swift = esc_html( get_option('sgc_iip_export_bank_swift',       get_option('sgc_iip_bank_swift','')) );
        }

        $paypal_email= esc_html( get_option('sgc_iip_paypal_email','') );
        $paypal_link = esc_html( get_option('sgc_iip_paypal_link','') );
        $wise_email  = esc_html( get_option('sgc_iip_wise_email','') );
        $wise_link   = esc_html( get_option('sgc_iip_wise_link','') );
        $upi_id      = get_option('sgc_iip_upi_id','');
        $gpay_name   = get_option('sgc_iip_gpay_name','SGC Labs');
        $upi_qr_img  = get_option('sgc_iip_upi_qr_img','');

        $show_bank   = in_array($pay_method, ['bank','bank_upi'], true) || !$pay_method;
        $show_paypal = in_array($pay_method, ['paypal','paypal_wise'], true) && $paypal_email;
        $show_wise   = in_array($pay_method, ['wise','paypal_wise'], true)   && $wise_email;
        $show_upi    = in_array($pay_method, ['upi','bank_upi'], true)       && $upi_id;

        $html = '<div style="margin:16px 0">';

        // Bank section
        if ( $show_bank ) {
            $bank_code = $is_dom
                ? 'IFSC Code: <strong style="font-family:monospace">' . $bank_ifsc . '</strong>'
                : 'SWIFT Code: <strong style="font-family:monospace">' . $bank_swift . '</strong>';
            $html .= '<div style="background:#fff8e1;border-left:4px solid #f9a825;padding:14px 16px;border-radius:0 6px 6px 0;margin-bottom:10px;font-size:13px">
              <strong style="color:#92400e">🏦 Bank Transfer Details for Payment</strong><br><br>
              Beneficiary: <strong>' . $bank_ben . '</strong><br>
              ' . $bank_code . '<br>
              Account No: ' . $bank_acc . '<br>
              Bank: ' . $bank_nm . ', ' . $bank_brnch . '
            </div>';
        }

        // UPI / GPay section with QR image
        if ( $show_upi && $upi_id ) {
            // Auto-generate QR URL via quickchart.io (free, no API key)
            $upi_link = 'upi://pay?pa=' . rawurlencode($upi_id) . '&pn=' . rawurlencode($gpay_name);
            $qr_url   = $upi_qr_img ?: 'https://quickchart.io/qr?text=' . rawurlencode($upi_link) . '&size=160&margin=2';

            $html .= '<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:14px;margin-bottom:10px;display:table;width:100%">
              <div style="display:table-cell;vertical-align:top;width:180px">
                <img src="' . esc_url($qr_url) . '" width="150" height="150" style="border:1px solid #e0e0e0;border-radius:6px;display:block" alt="GPay QR">
              </div>
              <div style="display:table-cell;vertical-align:middle;padding-left:16px">
                <strong style="color:#065f46;font-size:14px">📱 Pay via GPay / UPI</strong><br>
                <div style="margin-top:8px;font-size:13px">
                  <strong>UPI ID:</strong> <code style="background:#e0fce0;padding:2px 8px;border-radius:4px;font-size:13px">' . esc_html($upi_id) . '</code><br>
                  <strong>Name:</strong> ' . esc_html($gpay_name) . '<br>
                  <span style="font-size:12px;color:#16a34a;margin-top:6px;display:block">✓ Scan with GPay · PhonePe · Paytm · Any UPI app</span>
                </div>
              </div>
            </div>';
        }

        // PayPal section
        if ( $show_paypal ) {
            $pp_btn = $paypal_link
                ? '<a href="https://' . esc_attr($paypal_link) . '" style="display:inline-block;background:#0070ba;color:#fff;padding:9px 20px;border-radius:5px;text-decoration:none;font-weight:600;font-size:13px;margin-top:8px">🅿️ Pay via PayPal</a>'
                : '';
            $html .= '<div style="background:#e8f4fd;border:1px solid #90cdf4;border-radius:8px;padding:14px;margin-bottom:10px;font-size:13px">
              <strong style="color:#1e40af">🅿️ PayPal Payment</strong><br>
              <div style="margin-top:6px">
                Email: <strong>' . $paypal_email . '</strong><br>
                ' . ( $paypal_link ? 'Pay Link: <a href="https://' . esc_attr($paypal_link) . '" style="color:#0070ba">' . esc_html($paypal_link) . '</a>' : '' ) . '
                ' . $pp_btn . '
              </div>
              <div style="font-size:11px;color:#4a90d9;margin-top:6px">Suitable for amounts up to $500. For larger orders, prefer bank wire.</div>
            </div>';
        }

        // Wise section
        if ( $show_wise ) {
            $ws_btn = $wise_link
                ? '<a href="https://' . esc_attr($wise_link) . '" style="display:inline-block;background:#37517e;color:#fff;padding:9px 20px;border-radius:5px;text-decoration:none;font-weight:600;font-size:13px;margin-top:8px">💸 Pay via Wise</a>'
                : '';
            $html .= '<div style="background:#fdf4ff;border:1px solid #e9d5ff;border-radius:8px;padding:14px;margin-bottom:10px;font-size:13px">
              <strong style="color:#6b21a8">💸 Wise Transfer (Low Fee International)</strong><br>
              <div style="margin-top:6px">
                Email: <strong>' . $wise_email . '</strong><br>
                ' . ( $wise_link ? 'Pay Link: <a href="https://' . esc_attr($wise_link) . '" style="color:#7c3aed">' . esc_html($wise_link) . '</a>' : '' ) . '
                ' . $ws_btn . '
              </div>
              <div style="font-size:11px;color:#7c3aed;margin-top:6px">⭐ Recommended: 0.3-0.7% fee only. 40+ currencies supported.</div>
            </div>';
        }

        $html .= '</div>';
        return $html;
    }

    // ── Advance payment receipt ───────────────────────────────────────────
    public static function send_advance_receipt( array $inquiry, float $amount ) {
        $name    = esc_html( $inquiry['name'] ?? 'Valued Customer' );
        $company = get_option( 'sgc_iip_company_name', 'Scientific Glass Creations' );
        $pi_num  = esc_html( $inquiry['pi_number'] ?? '' );
        $cur     = $inquiry['currency'] ?? 'USD';
        $formatted = SGC_IIP_Currency::format( $amount, $cur );
        $date    = date_i18n( 'F j, Y' );

        $content = '
<p style="font-size:16px;color:#333;margin:0 0 8px">Dear <strong>' . $name . '</strong>,</p>
<p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 16px">
We are pleased to confirm receipt of your advance payment against <strong>' . esc_html( $company ) . '</strong>.
</p>

<div style="background:#e8f5e9;border:2px solid #2e7d32;border-radius:8px;padding:20px;margin:0 0 20px;text-align:center">
  <div style="font-size:13px;color:#666;margin-bottom:6px">Amount Received</div>
  <div style="font-size:28px;font-weight:700;color:#1b5e20">' . $formatted . '</div>
  <div style="font-size:12px;color:#888;margin-top:6px">' . $date . '</div>
</div>

<table width="100%" cellpadding="8" style="border-collapse:collapse;font-size:13px;margin:0 0 20px">
  <tr style="background:#f8f9fa"><td style="border:1px solid #e0e0e0;color:#666">Receipt against PI</td><td style="border:1px solid #e0e0e0"><strong>' . $pi_num . '</strong></td></tr>
  <tr><td style="border:1px solid #e0e0e0;color:#666">Received Amount</td><td style="border:1px solid #e0e0e0;color:#2e7d32;font-weight:700">' . $formatted . '</td></tr>
  <tr style="background:#f8f9fa"><td style="border:1px solid #e0e0e0;color:#666">Date</td><td style="border:1px solid #e0e0e0">' . $date . '</td></tr>
  <tr><td style="border:1px solid #e0e0e0;color:#666">Status</td><td style="border:1px solid #e0e0e0"><span style="background:#2e7d32;color:#fff;padding:2px 10px;border-radius:10px;font-size:11px">RECEIVED ✓</span></td></tr>
</table>

<p style="color:#555;font-size:13px;line-height:1.7">
Your order is now confirmed. We will begin production/preparation immediately and keep you updated on the shipment timeline as mentioned in the Proforma Invoice.
</p>
<p style="color:#333;font-size:14px">Thank you for your trust in ' . esc_html( $company ) . '!<br>
<strong>' . esc_html( $company ) . ' Export Team</strong></p>';

        $subject = 'Payment Receipt — ' . $formatted . ' — ' . $company;
        $html    = self::wrap( $content, 'Advance payment of ' . $formatted . ' received. Thank you!' );
        return self::mail( $inquiry['email'], $subject, $html );
    }

    // ── AJAX: Send PI email ───────────────────────────────────────────────
    public static function ajax_send_pi() {
        self::verify();
        global $wpdb;

        $pi_id = (int) ( $_POST['pi_id'] ?? 0 );
        $pi    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . SGC_IIP_DB::t('proformas') . " WHERE id=%d", $pi_id ), ARRAY_A ); // phpcs:ignore
        if ( ! $pi ) { wp_send_json_error( 'PI not found.' ); return; }

        $inq = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . SGC_IIP_DB::t('inquiries') . " WHERE id=%d", $pi['inquiry_id'] ), ARRAY_A ); // phpcs:ignore
        if ( ! $inq ) { wp_send_json_error( 'Inquiry not found.' ); return; }

        $pdf_path   = $pi['pdf_path'] ?? '';
        $accept_url = home_url( '/sgc-pi-accept/' . $pi['pi_number'] . '/' . $pi['accept_token'] );

        $sent = self::send_pi( $inq, $pi, $pdf_path, $accept_url );

        if ( $sent ) {
            $wpdb->update( SGC_IIP_DB::t('proformas'), [ 'status' => 'sent', 'sent_at' => current_time('mysql') ], [ 'id' => $pi_id ] ); // phpcs:ignore
            $wpdb->update( SGC_IIP_DB::t('inquiries'), [ 'status' => 'pi_sent', 'pi_number' => $pi['pi_number'] ], [ 'id' => $pi['inquiry_id'] ] ); // phpcs:ignore
            SGC_IIP_DB::log( $pi['inquiry_id'], 'pi_emailed', 'PI ' . $pi['pi_number'] . ' emailed to ' . $inq['email'], 'admin' );

            // WhatsApp notification
            if ( get_option( 'sgc_iip_wa_enabled', '0' ) === '1' ) {
                SGC_IIP_WhatsApp::send_pi_notification( $inq, $pi['pi_number'], $accept_url );
            }
        }

        wp_send_json_success( $sent ? 'PI sent successfully!' : 'Email failed. Check SMTP settings.' );
    }

    // ── AJAX: Send PI from Conveyor Panel (v1.7.0) ────────────────────────
    // Accepts pi_id, cc, bcc, custom subject, personal note.
    // Generates fresh PDF → sends HTML email with template + attachment.
    public static function ajax_send_pi_from_panel() {
        self::verify();
        global $wpdb;

        $pi_id         = (int) ( $_POST['pi_id']        ?? 0 );
        $cc            = sanitize_text_field( wp_unslash( $_POST['cc']            ?? '' ) );
        $bcc           = sanitize_text_field( wp_unslash( $_POST['bcc']           ?? '' ) );
        $custom_subject= sanitize_text_field( wp_unslash( $_POST['subject']       ?? '' ) );
        $personal_note = sanitize_textarea_field( wp_unslash( $_POST['personal_note'] ?? '' ) );

        if ( ! $pi_id ) { wp_send_json_error( 'PI ID required.' ); return; }

        $pi = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore
            'SELECT * FROM ' . SGC_IIP_DB::t( 'proformas' ) . ' WHERE id=%d', $pi_id
        ), ARRAY_A );
        if ( ! $pi ) { wp_send_json_error( 'PI not found.' ); return; }

        $inq = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore
            'SELECT * FROM ' . SGC_IIP_DB::t( 'inquiries' ) . ' WHERE id=%d', $pi['inquiry_id']
        ), ARRAY_A );
        if ( ! $inq ) { wp_send_json_error( 'Inquiry not found.' ); return; }

        // Decode line items for PDF
        $pi['line_items'] = json_decode( $pi['line_items'] ?? '[]', true ) ?: [];

        // ── Generate fresh PDF ────────────────────────────────────────────
        $pdf_path = SGC_IIP_PDF::generate_pi( $pi );
        if ( is_wp_error( $pdf_path ) ) {
            wp_send_json_error( 'PDF generation failed: ' . $pdf_path->get_error_message() );
            return;
        }
        $wpdb->update( SGC_IIP_DB::t( 'proformas' ), [ 'pdf_path' => $pdf_path ], [ 'id' => $pi_id ] ); // phpcs:ignore

        // ── Build & send HTML email ───────────────────────────────────────
        $accept_url = home_url( '/sgc-pi-accept/' . $pi['pi_number'] . '/' . ( $pi['accept_token'] ?? '' ) );
        $company    = get_option( 'sgc_iip_company_name', 'Scientific Glass Creations' );
        $pi_num     = $pi['pi_number'] ?? '';

        $buyer_country = strtolower( trim( $inq['country'] ?? $pi['buyer_country'] ?? '' ) );
        $is_domestic   = in_array( $buyer_country, [ 'india', 'in', 'bharat' ], true );
        $team_div      = $is_domestic ? 'Sales Division' : 'Export Division';

        // Personal note block (prepended inside email body)
        $note_html = '';
        if ( $personal_note ) {
            $note_html = '<div style="background:#fff8e1;border-left:4px solid #f9a825;padding:12px 16px;border-radius:0 6px 6px 0;margin:0 0 18px;font-size:14px;color:#555;line-height:1.7">'
                . nl2br( esc_html( $personal_note ) ) . '</div>';
        }

        // Reuse core PI email content builder (same HTML as existing send_pi)
        $pi_content = self::build_pi_body_content( $inq, $pi, $accept_url, $is_domestic );
        $full_content = $note_html . $pi_content;

        $subject  = $custom_subject ?: ( 'Proforma Invoice ' . $pi_num . ' — ' . $company );
        $html     = self::wrap( $full_content, 'PI ' . $pi_num . ' — ' . $company, $team_div );
        $attach   = ( $pdf_path && file_exists( $pdf_path ) ) ? [ $pdf_path ] : [];

        // CC defaults to settings value if left blank by user
        $cc_final = $cc ?: null;  // pass null = use settings CC
        $bcc_final= $bcc ?: null;

        $sent = self::mail( $inq['email'], $subject, $html, $attach, $cc_final, $bcc_final );

        if ( $sent ) {
            $wpdb->update( SGC_IIP_DB::t( 'proformas' ), // phpcs:ignore
                [ 'status' => 'sent', 'sent_at' => current_time( 'mysql' ) ],
                [ 'id'     => $pi_id ]
            );
            $wpdb->update( SGC_IIP_DB::t( 'inquiries' ), // phpcs:ignore
                [ 'status' => 'pi_sent', 'pi_number' => $pi_num ],
                [ 'id'     => $inq['id'] ]
            );
            SGC_IIP_DB::log( $inq['id'], 'pi_sent_panel',
                'PI ' . $pi_num . ' emailed to ' . $inq['email']
                . ( $cc ? ' | CC: ' . $cc : '' )
                . ( $bcc ? ' | BCC: ' . $bcc : '' ),
                'admin'
            );
            // WhatsApp (if enabled)
            if ( get_option( 'sgc_iip_wa_enabled', '0' ) === '1' && ! empty( $inq['phone'] ) ) {
                SGC_IIP_WhatsApp::send_pi_notification( $inq, $pi_num, $accept_url );
            }
            wp_send_json_success( [
                'to'        => $inq['email'],
                'cc'        => $cc,
                'pi_number' => $pi_num,
            ] );
        } else {
            wp_send_json_error( 'Email send failed — SMTP settings check karo.' );
        }
    }

    // ── Internal: build PI email body HTML (shared by send_pi + panel) ────
    private static function build_pi_body_content( array $inq, array $pi, string $accept_url, bool $is_domestic ): string {
        $name    = esc_html( $inq['name'] ?? 'Valued Customer' );
        $pi_num  = esc_html( $pi['pi_number'] ?? '' );
        $valid   = esc_html( $pi['valid_until'] ?? '' );
        $company = get_option( 'sgc_iip_company_name', 'Scientific Glass Creations' );

        $pi_currency     = $pi['currency'] ?? 'USD';
        $grand_total_raw = floatval( $pi['grand_total'] ?? 0 );
        $inr_rate        = floatval( $pi['inr_rate'] ?? get_option( 'sgc_iip_usd_rate', 83.5 ) );

        $display_total = $is_domestic
            ? '₹' . number_format( $grand_total_raw, 2 )
            : SGC_IIP_Currency::format( $grand_total_raw, $pi_currency );
        $total_label   = $is_domestic ? 'Grand Total (INR)' : 'Grand Total (' . $pi_currency . ')';

        $incoterm_val = $pi['incoterm'] ?? '';
        $port_val     = $pi['port']     ?? '';
        $incoterm_row = ( ! $is_domestic && $incoterm_val )
            ? '<tr><td style="color:#666;font-size:13px">Incoterm</td><td>' . esc_html( $incoterm_val . ' ' . $port_val ) . '</td></tr>'
            : '';

        $accept_btn = $accept_url
            ? '<a href="' . esc_url( $accept_url ) . '" style="display:inline-block;background:#2e7d32;color:#fff;padding:11px 24px;border-radius:5px;text-decoration:none;font-weight:600;font-size:14px;margin:0 8px 8px 0">✅ Accept This PI Online</a>'
            : '';

        $gst_row = '';
        if ( $is_domestic && floatval( $pi['gst_amount'] ?? 0 ) > 0 ) {
            $gst_label = ( sanitize_key( $pi['gst_type'] ?? '' ) === 'cgst_sgst' )
                ? 'CGST 9% + SGST 9%' : 'IGST 18%';
            $gst_row = '<tr><td style="color:#666;font-size:13px">' . $gst_label . '</td><td>₹' . number_format( floatval( $pi['gst_amount'] ), 2 ) . '</td></tr>';
        }

        $team_name = $is_domestic ? 'Sales Team' : 'Export Team';

        $content = '
<p style="font-size:16px;color:#333;margin:0 0 8px">Dear <strong>' . $name . '</strong>,</p>
<p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 16px">
Please find attached our <strong>Proforma Invoice ' . $pi_num . '</strong> as per your inquiry.
We appreciate your interest in <strong>' . esc_html( $company ) . '</strong>.
</p>
<div style="background:#e3f2fd;border:1px solid #1565c0;border-radius:8px;padding:18px 20px;margin:0 0 20px">
  <table width="100%" cellpadding="4"><tbody>
    <tr><td style="color:#666;font-size:13px;width:40%">PI Number</td><td style="font-weight:700;color:#1a1a2e">' . $pi_num . '</td></tr>
    <tr><td style="color:#666;font-size:13px">' . $total_label . '</td><td style="font-weight:700;color:#2e7d32;font-size:16px">' . $display_total . '</td></tr>
    ' . $gst_row . '
    ' . $incoterm_row . '
    <tr><td style="color:#666;font-size:13px">Payment Terms</td><td>' . esc_html( $pi['payment_terms'] ?? '' ) . '</td></tr>
    <tr><td style="color:#666;font-size:13px">Delivery</td><td>' . esc_html( $pi['delivery_days'] ?? '' ) . '</td></tr>
    <tr><td style="color:#666;font-size:13px">Valid Until</td><td style="color:#c62828"><strong>' . $valid . '</strong></td></tr>
  </tbody></table>
</div>
<p style="margin:0 0 16px">
' . $accept_btn . '
<a href="mailto:' . esc_attr( get_option( 'sgc_iip_company_email', '' ) ) . '" style="display:inline-block;background:#1a1a2e;color:#fff;padding:11px 24px;border-radius:5px;text-decoration:none;font-weight:600;font-size:14px">✉ Request Modification</a>
</p>
<p style="color:#555;font-size:13px;line-height:1.7">
The Proforma Invoice is attached as PDF. Please review carefully. If any changes are needed — quantity, specifications, pricing — please reply and we will revise promptly.
</p>
' . self::build_payment_section( $inq, $pi ) . '
<p style="margin:16px 0 0;color:#333;font-size:14px">Thank you for considering ' . esc_html( $company ) . '.<br>
<strong>' . esc_html( $company ) . ' ' . $team_name . '</strong></p>';

        return $content;
    }

    // ── AJAX: Custom email ────────────────────────────────────────────────
    public static function ajax_send_custom() {
        self::verify();
        global $wpdb;

        $id      = (int) ( $_POST['inquiry_id'] ?? 0 );
        $subject = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
        $body    = wp_kses_post( wp_unslash( $_POST['body'] ?? '' ) );

        $inq = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . SGC_IIP_DB::t('inquiries') . " WHERE id=%d", $id ), ARRAY_A ); // phpcs:ignore
        if ( ! $inq ) { wp_send_json_error( 'Not found.' ); return; }

        $html = self::wrap( '<p>' . nl2br( $body ) . '</p>' );
        $sent = self::mail( $inq['email'], $subject, $html );

        if ( $sent ) SGC_IIP_DB::log( $id, 'custom_email_sent', 'Custom email: ' . $subject, 'admin' );
        wp_send_json_success( $sent ? 'Email sent!' : 'Failed.' );
    }

    // ── AJAX: Save settings ───────────────────────────────────────────────
    public static function ajax_save_settings() {
        self::verify();
        $fields = [
            'sgc_iip_autoreply_email', 'sgc_iip_autoreply_wa',
            'sgc_iip_cc_email', 'sgc_iip_admin_email',
            'sgc_iip_company_email', 'sgc_iip_company_phone',
        ];
        foreach ( $fields as $f ) {
            if ( isset( $_POST[ $f ] ) )
                update_option( $f, sanitize_text_field( wp_unslash( $_POST[ $f ] ) ) );
        }
        wp_send_json_success( 'Settings saved.' );
    }

    // ── Send Dispatch Notification ────────────────────────────────────────
    public static function send_dispatch( array $inq, array $pi, string $freight_partner, string $tracking_no, string $dispatch_date, string $tracking_url = '' ) {
        $name    = esc_html( $inq['name'] ?? 'Valued Customer' );
        $company = get_option( 'sgc_iip_company_name', 'Scientific Glass Creations' );
        $pi_num  = esc_html( $pi['pi_number'] ?? '' );
        $date    = $dispatch_date ? date_i18n( 'F j, Y', strtotime( $dispatch_date ) ) : date_i18n( 'F j, Y' );

        $track_link = '';
        if ( $tracking_url ) {
            $track_link = '<div style="margin-top:12px;text-align:center">
                <a href="' . esc_url( $tracking_url ) . '" target="_blank" style="background:#1a6b4a;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;font-weight:700;font-size:14px;display:inline-block">
                    🔍 Track Your Shipment
                </a>
            </div>';
        }

        $content = '
<p style="font-size:16px;color:#333;margin:0 0 8px">Dear <strong>' . $name . '</strong>,</p>
<p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 16px">
Great news! Your order against Proforma Invoice <strong>' . $pi_num . '</strong> has been <strong style="color:#1a6b4a">dispatched</strong>. 
Please find the shipment details below.
</p>

<div style="background:linear-gradient(135deg,#0f4c35,#1a6b4a);border-radius:10px;padding:24px;margin:0 0 20px;text-align:center">
  <div style="font-size:13px;color:#86efac;margin-bottom:8px;text-transform:uppercase;letter-spacing:1px">Shipment Status</div>
  <div style="font-size:26px;font-weight:800;color:#fff">📦 DISPATCHED</div>
  <div style="font-size:13px;color:#86efac;margin-top:8px">' . $date . '</div>
</div>

<table width="100%" cellpadding="10" style="border-collapse:collapse;font-size:13px;margin:0 0 20px;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden">
  <tr style="background:#f0fdf4">
    <td style="border:1px solid #d1fae5;color:#065f46;font-weight:700;width:40%">PI Number</td>
    <td style="border:1px solid #d1fae5"><strong>' . $pi_num . '</strong></td>
  </tr>
  <tr>
    <td style="border:1px solid #e0e0e0;color:#666;font-weight:600">Freight Partner</td>
    <td style="border:1px solid #e0e0e0"><strong>' . esc_html( $freight_partner ) . '</strong></td>
  </tr>
  <tr style="background:#f0fdf4">
    <td style="border:1px solid #d1fae5;color:#065f46;font-weight:700">Tracking Number</td>
    <td style="border:1px solid #d1fae5">
      <strong style="font-size:16px;color:#1a6b4a;font-family:monospace">' . esc_html( $tracking_no ) . '</strong>
    </td>
  </tr>
  <tr>
    <td style="border:1px solid #e0e0e0;color:#666;font-weight:600">Dispatch Date</td>
    <td style="border:1px solid #e0e0e0">' . $date . '</td>
  </tr>
  <tr style="background:#f0fdf4">
    <td style="border:1px solid #d1fae5;color:#065f46;font-weight:700">Dispatched By</td>
    <td style="border:1px solid #d1fae5">' . esc_html( $company ) . '</td>
  </tr>
</table>

' . $track_link . '

<p style="color:#555;font-size:13px;line-height:1.7;margin:16px 0">
To track your shipment, visit the <strong>' . esc_html( $freight_partner ) . '</strong> website and enter tracking number: 
<strong style="color:#1a6b4a;font-family:monospace">' . esc_html( $tracking_no ) . '</strong>
</p>

<p style="color:#555;font-size:13px;line-height:1.7">
If you have any questions about your shipment, please reply to this email or contact us at <strong>' . esc_html( get_option( 'sgc_iip_company_phone', '+91 999 641 6935' ) ) . '</strong>.
</p>
<p style="color:#333;font-size:14px">Thank you for your business!<br>
<strong>' . esc_html( $company ) . ' Dispatch Team</strong></p>';

        $subject = '📦 Order Dispatched — ' . $pi_num . ' — Tracking: ' . $tracking_no;
        $html    = self::wrap( $content, 'Your order ' . $pi_num . ' has been dispatched via ' . $freight_partner );
        return self::mail( $inq['email'], $subject, $html );
    }

    // ── AJAX: Send Dispatch Email ─────────────────────────────────────────
    public static function ajax_send_dispatch() {
        self::verify();
        global $wpdb;

        $pi_id          = (int) ( $_POST['pi_id']          ?? 0 );
        $freight_partner = sanitize_text_field( wp_unslash( $_POST['freight_partner'] ?? '' ) );
        $tracking_no     = sanitize_text_field( wp_unslash( $_POST['tracking_no']     ?? '' ) );
        $dispatch_date   = sanitize_text_field( wp_unslash( $_POST['dispatch_date']   ?? '' ) );
        $tracking_url    = esc_url_raw( wp_unslash( $_POST['tracking_url'] ?? '' ) );

        if ( ! $pi_id || ! $freight_partner || ! $tracking_no ) {
            wp_send_json_error( 'PI ID, freight partner aur tracking number required hain.' );
            return;
        }

        $pi = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . SGC_IIP_DB::t('proformas') . " WHERE id=%d", $pi_id ), ARRAY_A ); // phpcs:ignore
        if ( ! $pi ) { wp_send_json_error( 'PI not found.' ); return; }

        $inq = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . SGC_IIP_DB::t('inquiries') . " WHERE id=%d", $pi['inquiry_id'] ), ARRAY_A ); // phpcs:ignore
        if ( ! $inq ) { wp_send_json_error( 'Buyer not found.' ); return; }

        // Save dispatch info to PI
        $wpdb->update( SGC_IIP_DB::t('proformas'), [ // phpcs:ignore
            'status'          => 'dispatched',
            'freight_partner' => $freight_partner,
            'tracking_no'     => $tracking_no,
            'dispatch_date'   => $dispatch_date ?: current_time('mysql'),
            'tracking_url'    => $tracking_url,
        ], [ 'id' => $pi_id ] );

        $sent = self::send_dispatch( $inq, $pi, $freight_partner, $tracking_no, $dispatch_date, $tracking_url );

        if ( $sent ) {
            SGC_IIP_DB::log( $pi['inquiry_id'], 'dispatched', 'Order dispatched via ' . $freight_partner . ' | Tracking: ' . $tracking_no, 'admin' );
            wp_send_json_success( 'Dispatch email sent to ' . $inq['email'] . '! Tracking: ' . $tracking_no );
        } else {
            wp_send_json_error( 'Dispatch info saved but email failed. Check SMTP settings.' );
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // COMPOSE EMAIL FEATURE  (v1.1.5)
    // ══════════════════════════════════════════════════════════════════════

    // ── AI: Polish raw / Hinglish message into professional HTML body ──────
    public static function ajax_ai_polish_email() {
        self::verify();

        $raw     = sanitize_textarea_field( wp_unslash( $_POST['raw_text'] ?? '' ) );
        $tone    = sanitize_text_field( wp_unslash( $_POST['tone']     ?? 'professional' ) );
        $to_name = sanitize_text_field( wp_unslash( $_POST['to_name']  ?? 'Valued Customer' ) );
        $subject = sanitize_text_field( wp_unslash( $_POST['subject']  ?? '' ) );

        if ( empty( $raw ) ) {
            wp_send_json_error( 'No message provided.' );
            return;
        }

        $company  = get_option( 'sgc_iip_company_name', 'Scientific Glass Creations' );
        $tone_map = [
            'professional' => 'professional and polished',
            'friendly'     => 'friendly yet professional',
            'formal'       => 'highly formal and corporate',
            'followup'     => 'warm follow-up reminding the client professionally',
            'quotation'    => 'professional quotation / price list response',
        ];
        $tone_desc = $tone_map[ $tone ] ?? 'professional and polished';

        $mode = sanitize_key( $_POST['mode'] ?? 'general' );

        // Mode-specific system prompt
        if ( $mode === 'template' ) {
            $system = "You are an expert B2B email writer for {$company}. You are given a pre-formatted email template with product details, terms and bank information. Your job is to:
1. Keep ALL the facts, numbers, prices, terms exactly as given
2. Make the language more natural, professional and warm
3. Ensure proper HTML formatting with <p>, <strong>, <table>, <tr>, <th>, <td> tags
4. The table styling: TH: background:#1a1a2e;color:#fff;padding:8px 12px | TD: border:1px solid #e0e0e0;padding:7px 12px | Alternate rows: background:#f8f9fa
5. Convert plain text tables (using | characters) to proper HTML tables
6. Start with: <p>Dear <strong>{$to_name}</strong>,</p>
7. End with the signature as-is from the template
8. Do NOT change any numbers, prices, bank details, or terms
9. Do NOT wrap in markdown fences";
        } elseif ( $mode === 'tubing' ) {
            $system = "You are an expert B2B email writer for {$company}, a laboratory glassware manufacturer from India.

The user has pasted RAW TUBING DATA (sizes, wall thickness, prices). Your job:
1. Parse all tubing specifications from the raw text
2. Create a professional quotation email with an HTML TABLE showing the specifications
3. The table MUST have these columns: Outside Diameter | Wall Thickness | Length Available | Unit Price | Notes

STRICT RULES:
- Return ONLY email body HTML using <p>, <table>, <thead>, <tbody>, <tr>, <th>, <td>, <strong> tags
- Table styling: border-collapse:collapse, width:100%
- TH style: background:#1a1a2e;color:#fff;padding:8px 12px;text-align:left
- TD style: border:1px solid #e0e0e0;padding:7px 12px;font-size:13px
- Alternate row: background:#f8f9fa
- Start with: <p>Dear <strong>{$to_name}</strong>,</p>
- End with: <p>Warm regards,<br><strong>{$company} Export Team</strong><br>info@labsglass.com | +91 999 641 6935</p>
- Add a note: All tubes are Borosilicate 3.3 | ISO 3585 | FOB Ambala, India
- Do NOT wrap in markdown fences";
        } else {
            $system = "You are an expert B2B email writer for {$company}, a leading laboratory glassware manufacturer and exporter from India (since 2003, ISO certified, ships to 50+ countries).

Your job: Convert the raw/Hinglish/broken English message into a complete, {$tone_desc} email body in perfect business English.

STRICT RULES:
- Return ONLY the email body HTML using <p>, <strong>, <ul>, <li>, <br> tags
- Start with: <p>Dear <strong>{$to_name}</strong>,</p>
- End with: <p>Warm regards,<br><strong>{$company} Team</strong></p>
- Do NOT include DOCTYPE, html, head, body, or wrapper tags
- Do NOT include the subject line inside the body
- Keep it 150-300 words unless more detail is genuinely needed
- If product details/specs/prices are mentioned, present them clearly
- Mention {$company} strengths naturally if relevant: ISO certified, Borosilicate 3.3, 50+ countries, since 2003
- Perfect business English — zero grammar errors
- Do NOT wrap output in markdown code fences";
        }

        $prompt = "Convert this raw message into a {$tone_desc} HTML email body:\n\n---\n{$raw}\n---"
                . ( $subject ? "\n\nEmail subject context: {$subject}" : '' );

        $result = SGC_IIP_AI::call( $prompt, $system, 1200 );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
            return;
        }

        $html = trim( $result );
        $html = preg_replace( '/^```html?\s*/i', '', $html );
        $html = preg_replace( '/\s*```$/',       '', $html );

        wp_send_json_success( [ 'html' => $html ] );
    }

    // ── Preview: Return full branded HTML for iframe ──────────────────────
    public static function ajax_preview_compose() {
        self::verify();

        $html_body = wp_kses_post( wp_unslash( $_POST['html_body'] ?? '' ) );
        $subject   = sanitize_text_field( wp_unslash( $_POST['subject']  ?? '' ) );

        if ( empty( $html_body ) ) {
            wp_send_json_error( 'Empty body.' );
            return;
        }

        wp_send_json_success( [ 'html' => self::wrap( $html_body, $subject ) ] );
    }

    // ── Send: Compose email with optional multi-file attachments ──────────
    public static function ajax_send_compose() {
        self::verify();

        $to_email  = sanitize_email(     wp_unslash( $_POST['to_email']  ?? '' ) );
        $to_name   = sanitize_text_field( wp_unslash( $_POST['to_name']   ?? '' ) );
        $subject   = sanitize_text_field( wp_unslash( $_POST['subject']   ?? '' ) );
        $html_body = wp_kses_post(        wp_unslash( $_POST['html_body'] ?? '' ) );

        if ( ! is_email( $to_email ) ) { wp_send_json_error( 'Invalid recipient email address.' ); return; }
        if ( empty( $subject ) )       { wp_send_json_error( 'Subject is required.' );             return; }
        if ( empty( $html_body ) )     { wp_send_json_error( 'Email body is empty.' );             return; }

        // ── Handle uploaded file attachments ──────────────────────────────
        $attachment_paths = [];
        $temp_files       = [];

        if ( ! empty( $_FILES['attachments'] ) ) {
            $upload_dir = wp_upload_dir();
            $temp_dir   = trailingslashit( $upload_dir['basedir'] ) . 'sgc-iip-temp/';

            if ( ! file_exists( $temp_dir ) ) {
                wp_mkdir_p( $temp_dir );
                @file_put_contents( $temp_dir . '.htaccess', 'Deny from all' . PHP_EOL );
                @file_put_contents( $temp_dir . 'index.php', '<?php // Silence is golden' );
            }

            $files = $_FILES['attachments'];
            $count = is_array( $files['name'] ) ? count( $files['name'] ) : 1;

            for ( $i = 0; $i < $count; $i++ ) {
                $f_name = is_array( $files['name'] )     ? $files['name'][$i]     : $files['name'];
                $f_tmp  = is_array( $files['tmp_name'] ) ? $files['tmp_name'][$i] : $files['tmp_name'];
                $f_err  = is_array( $files['error'] )    ? $files['error'][$i]    : $files['error'];
                $f_size = is_array( $files['size'] )     ? $files['size'][$i]     : $files['size'];

                if ( $f_err !== UPLOAD_ERR_OK || ! $f_tmp || ! is_uploaded_file( $f_tmp ) ) continue;
                if ( $f_size > 100 * 1024 * 1024 ) continue; // 100 MB hard limit

                $safe = sanitize_file_name( $f_name );
                $dest = $temp_dir . wp_unique_filename( $temp_dir, $safe );

                if ( move_uploaded_file( $f_tmp, $dest ) ) {
                    $attachment_paths[] = $dest;
                    $temp_files[]       = $dest;
                }
            }
        }

        // ── Wrap in branded template ──────────────────────────────────────
        $full_html  = self::wrap( $html_body, $subject );

        $from_name  = get_option( 'sgc_iip_company_name',  'SGC LABS®' );
        $from_email = get_option( 'sgc_iip_company_email', 'info@labsglass.com' );
        $bcc_auto   = get_option( 'sgc_iip_company_email', 'info@labsglass.com' );

        // v1.2.5: CC / BCC from compose form
        $cc_input  = sanitize_text_field( wp_unslash( $_POST['cc']  ?? '' ) );
        $bcc_input = sanitize_text_field( wp_unslash( $_POST['bcc'] ?? '' ) );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        ];

        // Auto-BCC to company always
        $bcc_list = array_filter( array_map( 'sanitize_email', [ $bcc_auto ] ) );

        // Add user-entered CC addresses
        if ( $cc_input ) {
            foreach ( explode( ',', $cc_input ) as $addr ) {
                $addr = sanitize_email( trim( $addr ) );
                if ( is_email( $addr ) ) $headers[] = 'Cc: ' . $addr;
            }
        }
        // Add user-entered BCC addresses
        if ( $bcc_input ) {
            foreach ( explode( ',', $bcc_input ) as $addr ) {
                $addr = sanitize_email( trim( $addr ) );
                if ( is_email( $addr ) ) $bcc_list[] = $addr;
            }
        }
        foreach ( array_unique( $bcc_list ) as $bcc ) $headers[] = 'Bcc: ' . $bcc;

        $to_header = $to_name ? "{$to_name} <{$to_email}>" : $to_email;
        $sent      = wp_mail( $to_header, $subject, $full_html, $headers, $attachment_paths );

        // ── Cleanup temp files ────────────────────────────────────────────
        foreach ( $temp_files as $f ) {
            if ( file_exists( $f ) ) @unlink( $f );
        }

        // ── Log & respond ─────────────────────────────────────────────────
        $attach_count = count( $attachment_paths );
        if ( $sent ) {
            $note = 'Compose email → ' . $to_email
                  . ' | ' . $subject
                  . ( $attach_count ? ' | ' . $attach_count . ' file(s)' : '' )
                  . ' | BCC: ' . $bcc_auto;
            try {
                SGC_IIP_DB::log( 0, 'compose_email_sent', $note, 'admin' );
            } catch ( \Throwable $e ) {
                error_log( '[SGC-IIP] Compose log: ' . $e->getMessage() );
            }

            // v1.8.2: Notify CRM to update last_contacted for this buyer
            // Fires silently — CRM listens if active, no-op if not
            do_action( 'sgc_iip_compose_email_sent', $to_email, $subject );

            wp_send_json_success( [
                'message' => 'Email sent to ' . $to_email
                           . ( $attach_count ? ' with ' . $attach_count . ' attachment(s)' : '' )
                           . '. BCC copy → ' . $bcc_auto,
            ] );
        } else {
            wp_send_json_error( 'Email failed. Check SMTP/Mailer settings.' );
        }
    }

    // ── Save Compose Draft ────────────────────────────────────────────────
    public static function ajax_save_compose_draft(): void {
        self::verify();
        global $wpdb;

        $to      = sanitize_email( $_POST['to'] ?? '' );
        $cc      = sanitize_text_field( wp_unslash( $_POST['cc']      ?? '' ) );
        $bcc     = sanitize_text_field( wp_unslash( $_POST['bcc']     ?? '' ) );
        $subject = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
        $body    = wp_kses_post( wp_unslash( $_POST['body']    ?? '' ) );
        $raw     = sanitize_textarea_field( wp_unslash( $_POST['raw']  ?? '' ) );
        $mode    = sanitize_key( $_POST['mode'] ?? 'general' );

        $t = $wpdb->prefix . 'sgc_iip_compose_drafts';
        $wpdb->query("CREATE TABLE IF NOT EXISTS `{$t}` (id INT AUTO_INCREMENT PRIMARY KEY,to_email VARCHAR(255),cc TEXT,bcc TEXT,subject VARCHAR(500),body_html LONGTEXT,raw_text TEXT,mode VARCHAR(50) DEFAULT 'general',drive_url VARCHAR(500),created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"); // phpcs:ignore

        $wpdb->insert($t,['to_email'=>$to,'cc'=>$cc,'bcc'=>$bcc,'subject'=>$subject,'body_html'=>$body,'raw_text'=>$raw,'mode'=>$mode,'created_at'=>current_time('mysql')]); // phpcs:ignore
        wp_send_json_success(['id'=>$wpdb->insert_id]);
    }

    // ── Save to Google Drive ──────────────────────────────────────────────
    public static function ajax_save_to_drive(): void {
        self::verify();

        $subject = sanitize_text_field( wp_unslash( $_POST['subject'] ?? 'SGC Email Draft' ) );
        $to      = sanitize_email( $_POST['to'] ?? '' );
        $body    = sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) );
        $date    = sanitize_text_field( wp_unslash( $_POST['date'] ?? current_time('Y-m-d') ) );

        if ( empty($body) ) { wp_send_json_error('Email body empty.'); return; }

        $doc_content = "SGC LABS(r) Email Draft\n";
        $doc_content .= str_repeat('=',42)."\n";
        $doc_content .= "Date: {$date}\nTo: {$to}\nSubject: {$subject}\n";
        $doc_content .= str_repeat('=',42)."\n\n{$body}\n\n-- SGC LABS(r) | labsglass.com";

        $filename = 'SGC-Email-'.$date.'-'.sanitize_file_name(substr($subject,0,30)).'.txt';

        // Try Google Drive OAuth token
        $token = get_option('sgclabs_google_access_token','');
        if ( empty($token) ) {
            // Fallback: save locally
            $upload_dir = wp_upload_dir();
            file_put_contents($upload_dir['path'].'/'.$filename, $doc_content);
            wp_send_json_success(['name'=>$filename,'url'=>$upload_dir['url'].'/'.$filename,'method'=>'local']);
            return;
        }

        $boundary = wp_generate_password(16, false);
        $metadata = json_encode(['name'=>$filename,'mimeType'=>'text/plain']);
        $body_mp  = "--{$boundary}\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n{$metadata}\r\n--{$boundary}\r\nContent-Type: text/plain\r\n\r\n{$doc_content}\r\n--{$boundary}--";

        $r = wp_remote_post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart',[
            'timeout'=>20,
            'headers'=>['Authorization'=>'Bearer '.$token,'Content-Type'=>"multipart/related; boundary={$boundary}"],
            'body'=>$body_mp,
        ]);

        if ( is_wp_error($r) ) { wp_send_json_error('Drive error: '.$r->get_error_message()); return; }

        $data = json_decode(wp_remote_retrieve_body($r), true);
        if (!empty($data['id'])) {
            wp_send_json_success(['name'=>$filename,'url'=>'https://drive.google.com/file/d/'.$data['id'].'/view','method'=>'drive']);
        } else {
            wp_send_json_error('Drive API failed. Check Google OAuth.');
        }
    }

    // ── v1.8.0: PI Cancellation email to buyer ────────────────────────────
    /**
     * Send a branded PI cancellation notification to the buyer.
     *
     * @param array  $pi     Proforma row from DB (must include pi_number, buyer_name, buyer_email).
     * @param array  $inq    Inquiry row from DB (must include name, email).
     * @param string $reason Admin-provided cancellation reason (sanitized before passing).
     * @return bool          True if wp_mail accepted the message.
     */
    public static function send_pi_cancellation( array $pi, array $inq, string $reason = '' ): bool {

        // Prefer PI buyer fields; fall back to inquiry fields
        $buyer_email = sanitize_email( $pi['buyer_email'] ?? $inq['email'] ?? '' );
        if ( ! is_email( $buyer_email ) ) {
            error_log( '[SGC-IIP] send_pi_cancellation: invalid buyer email for PI ' . ( $pi['pi_number'] ?? '?' ) );
            return false;
        }

        $buyer_name  = esc_html( $pi['buyer_name']  ?? $inq['name']  ?? 'Valued Customer' );
        $pi_num      = esc_html( $pi['pi_number']   ?? '' );
        $company     = get_option( 'sgc_iip_company_name',  'Scientific Glass Creations' );
        $from_name   = $company;
        $from_email  = sanitize_email( get_option( 'sgc_iip_company_email', 'info@labsglass.com' ) );
        $bcc_addr    = $from_email; // Always BCC company

        // Buyer country for domestic/export detection
        $buyer_country = strtolower( trim( $pi['buyer_country'] ?? $inq['country'] ?? '' ) );
        $is_domestic   = in_array( $buyer_country, [ 'india', 'in', 'bharat' ], true );
        $division      = $is_domestic ? 'Sales Division' : 'Export Division';
        $team          = $is_domestic ? 'Sales Team'     : 'Export Team';

        // Reason block — only shown if provided
        $reason_block = '';
        if ( $reason !== '' ) {
            $reason_block = '
<div style="background:#fff8e1;border:1px solid #fbbf24;border-radius:6px;padding:14px 18px;margin:0 0 18px;font-size:13px;color:#92400e">
  <strong>Reason:</strong> ' . esc_html( $reason ) . '
</div>';
        }

        $contact_email = esc_html( $from_email );
        $contact_phone = esc_html( get_option( 'sgc_iip_company_phone', '+91 999 641 6935' ) );

        $content = '
<p style="font-size:15px;color:#333;margin:0 0 6px">Dear <strong>' . $buyer_name . '</strong>,</p>
<p style="color:#555;font-size:13px;line-height:1.7;margin:0 0 18px">
  We regret to inform you that <strong>Proforma Invoice ' . $pi_num . '</strong> has been
  <strong style="color:#b91c1c">cancelled</strong>. We sincerely apologise for any inconvenience caused.
</p>

' . $reason_block . '

<div style="background:#f1f5f9;border-radius:8px;padding:16px 18px;margin:0 0 20px;font-size:13px;color:#334155">
  <strong>Cancelled PI:</strong> ' . $pi_num . '<br>
  <strong>Status:</strong> <span style="color:#b91c1c;font-weight:700">Cancelled</span>
</div>

<p style="color:#555;font-size:13px;line-height:1.7;margin:0 0 18px">
  If you would like us to issue a revised quotation or have any questions, please do not hesitate
  to contact our ' . esc_html( $team ) . ' — we will be happy to assist you promptly.
</p>

<div style="border-top:1px solid #e5e7eb;padding-top:16px;font-size:12px;color:#6b7280">
  📞 ' . $contact_phone . ' &nbsp;|&nbsp; ✉ <a href="mailto:' . $contact_email . '" style="color:#185FA5">' . $contact_email . '</a>
</div>';

        $subject = 'Regarding PI ' . $pi_num . ' — Update from ' . $company;
        $wrapped  = self::wrap( $content, 'Important update regarding your Proforma Invoice.', $division );

        if ( ! $wrapped ) {
            error_log( '[SGC-IIP] send_pi_cancellation: wrap() returned empty for PI ' . $pi_num );
            return false;
        }

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Bcc: ' . $bcc_addr,
        ];

        $sent = wp_mail( $buyer_email, $subject, $wrapped, $headers );

        if ( $sent ) {
            error_log( '[SGC-IIP] Cancellation email sent for ' . $pi_num . ' → ' . $buyer_email );
        } else {
            error_log( '[SGC-IIP] Cancellation email FAILED for ' . $pi_num . ' → ' . $buyer_email . ' — check SMTP' );
        }

        return (bool) $sent;
    }

}

SGC_IIP_Email::register_ajax();
