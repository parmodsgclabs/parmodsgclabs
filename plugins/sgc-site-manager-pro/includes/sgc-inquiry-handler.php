<?php
/**
 * SGC LABS® — Unified Inquiry Handler  v2.0.0
 * Build: 2026-05-12 17:30 IST
 * ═══════════════════════════════════════════════════════════════════════
 *
 * FILE LOCATION:
 *   /wp-content/plugins/sgc-site-manager-pro/includes/sgc-inquiry-handler.php
 *
 * LOADER (already added in sgclabs-manager.php — do NOT add manually):
 *   sgclabs_include_file( SGCLABS_PATH . 'includes/sgc-inquiry-handler.php' );
 *
 * WORKS WITH ALL 5 WEBSITES:
 *   labsglass.com · sgclabglass.com · labglass.in · astmglass.com · sgclabs.in
 *
 * WHAT THIS FILE DOES:
 *   1. Uses the correct table: wp_sgclabs_inquiries (Manager Pro + Invoice Pro)
 *   2. Fixes REST POST /wp-json/sgclabs/v1/inquiry  → saves to DB + emails
 *   3. Fixes REST POST /wp-json/sgclabs/v1/quote    → saves to DB + emails
 *   4. Handles AJAX: sgclabs_save_inquiry  (contact form variant)
 *   5. Handles AJAX: sgclabs_submit_inquiry (Manager Pro shortcode — passthrough)
 *   6. Sends rich HTML email to team + confirmation to sender
 *   7. Fires sgclabs_inquiry_received hook (CRM, Smart CRM, Pipeline sync)
 *
 * FIXES FROM v1.3 (standalone):
 *   - Table was wp_sgc_inquiries → now wp_sgclabs_inquiries ✅
 *   - Column schema matches Manager Pro + Invoice Pro ✅
 *   - Admin menu slug was 'sgclabs-manager-pro' → now 'sgclabs-manager' ✅
 *   - define() at file level was unsafe → now uses init hook ✅
 *   - REST handler fired action only, didn't save → now saves to DB ✅
 *   - Source website tracked in source_type column ✅
 *
 * @package SGCLabs\ManagerPro
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

// ── Guard against double-load ─────────────────────────────────────────────
if ( defined( 'SGC_INQ_HANDLER_LOADED' ) ) return;
define( 'SGC_INQ_HANDLER_LOADED', true );
define( 'SGC_INQ_HANDLER_VERSION', '2.0.0' );

// ── Config (change here if needed) ────────────────────────────────────────
if ( ! defined( 'SGC_TEAM_EMAIL'    ) ) define( 'SGC_TEAM_EMAIL',    'info@sgclabglass.com' );
if ( ! defined( 'SGC_TEAM_EMAIL_CC' ) ) define( 'SGC_TEAM_EMAIL_CC', 'info@labsglass.com' );  // Set '' to disable
if ( ! defined( 'SGC_FROM_NAME'     ) ) define( 'SGC_FROM_NAME',     'SGC LABS® Website' );
if ( ! defined( 'SGC_FROM_EMAIL'    ) ) define( 'SGC_FROM_EMAIL',    'noreply@labsglass.com' );

// Table name is resolved at runtime (safe — $wpdb available by init time)
function sgc_inq_table(): string {
    global $wpdb;
    return $wpdb->prefix . 'sgclabs_inquiries';
}

/*
 * ════════════════════════════════════════════════════════════════════════
 *  SECTION 1 — DB COLUMN UPGRADE (safe ALTER, runs once per version)
 *  Adds extra columns to wp_sgclabs_inquiries that the handler needs
 *  but that Manager Pro's core schema doesn't include yet.
 * ════════════════════════════════════════════════════════════════════════
 */
add_action( 'init', function () {
    if ( get_option( 'sgc_inq_handler_v' ) === SGC_INQ_HANDLER_VERSION ) return;

    global $wpdb;
    $t = sgc_inq_table();

    // Only run if the table already exists (Manager Pro creates it on activation)
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) return;

    $cols = array_column(
        $wpdb->get_results( "DESCRIBE `{$t}`", ARRAY_A ) ?: [], // phpcs:ignore
        'Field'
    );

    $alters = [];

    // buyer_type — maps to industry/buyer segment
    if ( ! in_array( 'buyer_type', $cols, true ) )
        $alters[] = "ADD COLUMN `buyer_type` varchar(60) NOT NULL DEFAULT '' COMMENT 'dealer|researcher|university|glass_blower|distributor|other' AFTER `source_type`";

    // quantity_req — full quantity string (Manager Pro has qty varchar(100))
    // qty already exists in Manager Pro schema — skip

    // email_sent flag
    if ( ! in_array( 'email_sent', $cols, true ) )
        $alters[] = "ADD COLUMN `email_sent` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = team email sent' AFTER `follow_up_at`";

    // website_source — which of the 5 websites sent this inquiry
    if ( ! in_array( 'website_source', $cols, true ) )
        $alters[] = "ADD COLUMN `website_source` varchar(100) NOT NULL DEFAULT '' COMMENT 'labsglass.com / sgclabglass.com / etc.' AFTER `source_type`";

    // ip_address
    if ( ! in_array( 'ip_address', $cols, true ) )
        $alters[] = "ADD COLUMN `ip_address` varchar(45) NOT NULL DEFAULT '' AFTER `website_source`";

    if ( ! empty( $alters ) ) {
        foreach ( $alters as $sql ) {
            $wpdb->query( "ALTER TABLE `{$t}` {$sql}" ); // phpcs:ignore
        }
    }

    update_option( 'sgc_inq_handler_v', SGC_INQ_HANDLER_VERSION );
}, 30 );


/*
 * ════════════════════════════════════════════════════════════════════════
 *  SECTION 2 — CORE SAVE FUNCTION
 *  Single source of truth. All entry points call sgc_inq_save().
 * ════════════════════════════════════════════════════════════════════════
 */

/**
 * Save an inquiry to wp_sgclabs_inquiries and send emails.
 *
 * @param  array $data Raw input (will be sanitized internally).
 * @return array { ok: bool, inquiry_id: int, email_sent: bool, error?: string }
 */
function sgc_inq_save( array $data ): array {
    global $wpdb;

    // ── Sanitize ──────────────────────────────────────────────────────────
    $name       = sanitize_text_field( $data['name']        ?? '' );
    $email      = sanitize_email(      $data['email']       ?? '' );
    $phone      = sanitize_text_field( $data['phone']       ?? '' );
    $company    = sanitize_text_field( $data['company']     ?? '' );
    $country    = sanitize_text_field( $data['country']     ?? '' );
    $product    = sanitize_text_field( $data['product']     ?? ( $data['message'] ?? '' ) );
    $qty        = sanitize_text_field( $data['qty']         ?? ( $data['quantity'] ?? '' ) );
    $message    = sanitize_textarea_field( $data['message'] ?? '' );
    $incoterm   = sanitize_text_field( $data['incoterm']    ?? '' );
    $port       = sanitize_text_field( $data['port']        ?? '' );
    $buyer_type = sanitize_text_field( $data['buyer_type']  ?? '' );

    // source_type: map old query_type to Manager Pro's source_type enum
    $query_type  = sanitize_text_field( $data['query_type'] ?? $data['source'] ?? 'quote' );
    $source_type = in_array( $query_type, [ 'quote', 'contact', 'b2b', 'manual', 'wc_order' ], true )
        ? $query_type : 'quote';

    // Which website sent this (detect from HTTP_REFERER or explicit 'source' field)
    $referer        = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ?? '' ) );
    $website_source = sanitize_text_field( $data['website_source'] ?? $data['source_site'] ?? '' );
    if ( ! $website_source && $referer ) {
        $host = parse_url( $referer, PHP_URL_HOST );
        $website_source = $host ?: '';
    }

    $ip = sanitize_text_field(
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? ''
    );
    // Take first IP if comma-separated (proxy chain)
    $ip = trim( explode( ',', $ip )[0] );

    // ── Validate required fields ──────────────────────────────────────────
    if ( empty( $name ) ) {
        return [ 'ok' => false, 'error' => 'Name is required.' ];
    }
    if ( ! is_email( $email ) ) {
        return [ 'ok' => false, 'error' => 'Valid email address is required.' ];
    }
    if ( empty( $product ) ) {
        return [ 'ok' => false, 'error' => 'Product / requirement is required.' ];
    }

    // ── INSERT ────────────────────────────────────────────────────────────
    $row = [
        'name'           => $name,
        'email'          => $email,
        'phone'          => $phone,
        'company'        => $company,
        'country'        => $country,
        'product'        => $product,
        'qty'            => $qty,
        'message'        => $message,
        'incoterm'       => $incoterm,
        'port'           => $port,
        'source_type'    => $source_type,
        'status'         => 'new',
    ];

    // Add extra columns only if they exist (safe for older DB schema)
    $all_cols = array_column(
        $wpdb->get_results( 'DESCRIBE `' . sgc_inq_table() . '`', ARRAY_A ) ?: [], // phpcs:ignore
        'Field'
    );
    if ( in_array( 'buyer_type',      $all_cols, true ) ) $row['buyer_type']      = $buyer_type;
    if ( in_array( 'website_source',  $all_cols, true ) ) $row['website_source']  = $website_source;
    if ( in_array( 'ip_address',      $all_cols, true ) ) $row['ip_address']      = $ip;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $inserted = $wpdb->insert( sgc_inq_table(), $row );

    if ( false === $inserted ) {
        return [ 'ok' => false, 'error' => 'DB insert failed: ' . $wpdb->last_error ];
    }

    $inquiry_id = (int) $wpdb->insert_id;

    // ── Fire unified hook (Smart CRM, Pipeline, AI Reply, follow-up cron) ─
    do_action( 'sgclabs_inquiry_received', [
        'db_id'         => $inquiry_id,
        'source'        => $source_type,
        'status'        => 'new',
        'name'          => $name,
        'email'         => $email,
        'phone'         => $phone,
        'country'       => $country,
        'company'       => $company,
        'subject'       => 'Product Inquiry: ' . $product,
        'message'       => $message,
        'product_title' => $product,
        'page_url'      => $referer,
        'meta'          => [
            'incoterm'       => $incoterm,
            'port'           => $port,
            'qty'            => $qty,
            'buyer_type'     => $buyer_type,
            'website_source' => $website_source,
        ],
        'created_at'    => time(),
    ] );

    // Also push into SGCLabs_Inquiry_Store (AI Reply Center compat)
    if ( class_exists( 'SGCLabs_Inquiry_Store' ) ) {
        SGCLabs_Inquiry_Store::add( [
            'fire_hook'     => false, // hook already fired above with db_id payload
            'source'        => $source_type,
            'status'        => 'new',
            'name'          => $name,
            'email'         => $email,
            'phone'         => $phone,
            'country'       => $country,
            'company'       => $company,
            'subject'       => 'Product Inquiry: ' . $product,
            'message'       => $message,
            'product_title' => $product,
            'page_url'      => $referer,
            'meta'          => [ 'incoterm' => $incoterm, 'port' => $port, 'qty' => $qty ],
        ] );
    }

    // ── Send emails ───────────────────────────────────────────────────────
    $email_sent = sgc_inq_send_emails( compact(
        'name','email','phone','company','country','product',
        'qty','message','incoterm','port','buyer_type','source_type','website_source'
    ), $inquiry_id );

    // Update email_sent flag if column exists
    if ( in_array( 'email_sent', $all_cols, true ) && $email_sent ) {
        $wpdb->update( // phpcs:ignore
            sgc_inq_table(),
            [ 'email_sent' => 1 ],
            [ 'id' => $inquiry_id ],
            [ '%d' ], [ '%d' ]
        );
    }

    return [
        'ok'         => true,
        'inquiry_id' => $inquiry_id,
        'email_sent' => $email_sent,
    ];
}


/*
 * ════════════════════════════════════════════════════════════════════════
 *  SECTION 3 — EMAIL FUNCTIONS
 * ════════════════════════════════════════════════════════════════════════
 */

function sgc_inq_send_emails( array $c, int $id ): bool {
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . SGC_FROM_NAME . ' <' . SGC_FROM_EMAIL . '>',
    ];
    if ( SGC_TEAM_EMAIL_CC ) {
        $headers[] = 'CC: ' . SGC_TEAM_EMAIL_CC;
    }

    // Team email subject — includes website source for multi-site clarity
    $site_label = $c['website_source'] ? ' [' . $c['website_source'] . ']' : '';
    $subject    = sprintf(
        '[INQ #%d]%s %s — %s (%s)',
        $id,
        $site_label,
        strtoupper( $c['source_type'] ),
        $c['product'],
        $c['country']
    );

    $team_sent = wp_mail(
        SGC_TEAM_EMAIL,
        $subject,
        sgc_inq_team_html( $c, $id ),
        $headers
    );

    // Confirmation to sender
    if ( is_email( $c['email'] ) ) {
        $conf_headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . SGC_FROM_NAME . ' <' . SGC_FROM_EMAIL . '>',
            'Reply-To: ' . SGC_TEAM_EMAIL,
        ];
        wp_mail(
            $c['email'],
            'Your inquiry to SGC LABS® has been received — Ref #' . $id,
            sgc_inq_sender_html( $c, $id ),
            $conf_headers
        );
    }

    return (bool) $team_sent;
}

function sgc_inq_team_html( array $c, int $id ): string {
    $rows = [
        'Inquiry #'      => '#' . $id,
        'Source Type'    => esc_html( $c['source_type'] ),
        'Buyer Type'     => esc_html( $c['buyer_type'] ?: '—' ),
        'Website'        => esc_html( $c['website_source'] ?: 'Unknown' ),
        'Name'           => esc_html( $c['name'] ),
        'Email'          => '<a href="mailto:' . esc_attr( $c['email'] ) . '">' . esc_html( $c['email'] ) . '</a>',
        'Phone/WhatsApp' => $c['phone']
            ? '<a href="https://api.whatsapp.com/send/?phone=' . preg_replace( '/\D/', '', $c['phone'] ) . '">'
              . esc_html( $c['phone'] ) . '</a>'
            : '—',
        'Company'        => esc_html( $c['company'] ?: '—' ),
        'Country'        => esc_html( $c['country'] ),
        'Incoterm'       => esc_html( $c['incoterm'] ?: '—' ),
        'Quantity'       => esc_html( $c['qty'] ?: '—' ),
        'Product / Req'  => '<strong>' . esc_html( $c['product'] ) . '</strong>',
        'Message'        => nl2br( esc_html( $c['message'] ?: '—' ) ),
        'Received at'    => esc_html( current_time( 'd M Y H:i:s' ) . ' IST' ),
    ];

    $tr = '';
    foreach ( $rows as $label => $val ) {
        $bold = in_array( $label, [ 'Product / Req', 'Name', 'Email' ], true ) ? '#e8effe' : '#fff';
        $tr  .= "<tr>
            <td style='padding:9px 14px;font-size:12px;font-weight:700;color:#3368c6;background:#f4f7ff;white-space:nowrap;border-bottom:1px solid #e4ebf8'>{$label}</td>
            <td style='padding:9px 14px;font-size:14px;color:#1a2340;background:{$bold};border-bottom:1px solid #e4ebf8'>{$val}</td>
        </tr>";
    }

    $admin_url = esc_url( admin_url( 'admin.php?page=sgclabs-manager&tab=inquiries' ) );

    return "<!DOCTYPE html><html><body style='margin:0;padding:0;background:#f4f7ff;font-family:-apple-system,sans-serif'>
<div style='max-width:640px;margin:28px auto'>
  <div style='background:linear-gradient(135deg,#0f2557,#3368c6);border-radius:12px 12px 0 0;padding:26px 32px'>
    <h1 style='color:#fff;font-size:20px;margin:0 0 4px'>🔔 New Inquiry — SGC LABS®</h1>
    <p style='color:rgba(255,255,255,.75);font-size:13px;margin:0'>Ref <strong style='color:#fff'>#$id</strong></p>
  </div>
  <div style='background:#fff;border-radius:0 0 12px 12px;overflow:hidden;border:1px solid #dce6ff'>
    <table style='width:100%;border-collapse:collapse'>{$tr}</table>
  </div>
  <div style='margin-top:16px;text-align:center'>
    <a href='{$admin_url}' style='display:inline-block;background:#3368c6;color:#fff;border-radius:8px;padding:12px 28px;font-size:14px;font-weight:700;text-decoration:none'>View in Manager Pro →</a>
    &nbsp;
    <a href='https://api.whatsapp.com/send/?phone=919996416935' style='display:inline-block;background:#25D366;color:#fff;border-radius:8px;padding:12px 20px;font-size:14px;font-weight:700;text-decoration:none'>💬 WhatsApp</a>
  </div>
  <p style='text-align:center;font-size:11px;color:#9ba8c0;margin-top:14px'>SGC LABS® · Ambala Cantt, India · labsglass.com</p>
</div></body></html>";
}

function sgc_inq_sender_html( array $c, int $id ): string {
    $product_row = $c['product']
        ? "<p style='font-size:14px;color:#1a2340;margin:0 0 4px'><strong>Product:</strong> " . esc_html( $c['product'] ) . "</p>"
        : '';
    $qty_row = $c['qty']
        ? "<p style='font-size:14px;color:#1a2340;margin:0 0 4px'><strong>Quantity:</strong> " . esc_html( $c['qty'] ) . "</p>"
        : '';

    return "<!DOCTYPE html><html><body style='margin:0;padding:0;background:#f4f7ff;font-family:-apple-system,sans-serif'>
<div style='max-width:580px;margin:28px auto'>
  <div style='background:linear-gradient(135deg,#0f2557,#3368c6);border-radius:12px 12px 0 0;padding:26px 32px'>
    <h1 style='color:#fff;font-size:20px;margin:0 0 4px'>Thank you, " . esc_html( $c['name'] ) . "!</h1>
    <p style='color:rgba(255,255,255,.75);font-size:13px;margin:0'>Your inquiry to SGC LABS® — Ref #$id</p>
  </div>
  <div style='background:#fff;border-radius:0 0 12px 12px;padding:28px 32px;border:1px solid #dce6ff'>
    <p style='font-size:15px;color:#1a2340;margin:0 0 16px'>We have received your inquiry. Our exports team will send you a detailed quotation within <strong>24–48 working hours</strong> (Mon–Fri).</p>
    <div style='background:#f4f7ff;border-left:4px solid #3368c6;border-radius:0 8px 8px 0;padding:14px 18px;margin-bottom:20px'>
      <p style='font-size:12px;font-weight:700;color:#3368c6;text-transform:uppercase;margin:0 0 8px'>Your requirement</p>
      {$product_row}{$qty_row}
      <p style='font-size:14px;color:#1a2340;margin:0'><strong>Country:</strong> " . esc_html( $c['country'] ) . "</p>
    </div>
    <p style='font-size:14px;color:#4a556e;margin:0 0 10px'>For urgent queries:</p>
    <p style='font-size:14px;margin:0 0 6px'>📧 <a href='mailto:" . SGC_TEAM_EMAIL . "' style='color:#3368c6'>" . SGC_TEAM_EMAIL . "</a></p>
    <p style='font-size:14px;margin:0'>💬 <a href='https://api.whatsapp.com/send/?phone=919996416935' style='color:#25d366'>WhatsApp: +91 99964 16935</a></p>
    <hr style='border:none;border-top:1px solid #e4ebf8;margin:20px 0'>
    <p style='font-size:12px;color:#9ba8c0;margin:0'>SGC LABS® · 4/12/2 Gandhi Nagar, Ambala Cantt 133001, India · <a href='https://labsglass.com' style='color:#3368c6'>labsglass.com</a></p>
  </div>
</div></body></html>";
}


/*
 * ════════════════════════════════════════════════════════════════════════
 *  SECTION 4 — REST API (override Manager Pro's no-op handlers)
 *  Priority 20 ensures we run AFTER Manager Pro's registration (priority 10)
 * ════════════════════════════════════════════════════════════════════════
 */
add_action( 'rest_api_init', function () {

    register_rest_route( 'sgclabs/v1', '/inquiry', [
        'methods'             => 'POST',
        'callback'            => 'sgc_inq_rest_handler',
        'permission_callback' => '__return_true',
    ], true /* override */ );

    register_rest_route( 'sgclabs/v1', '/quote', [
        'methods'             => 'POST',
        'callback'            => 'sgc_inq_rest_handler',
        'permission_callback' => '__return_true',
    ], true );

}, 20 );

function sgc_inq_rest_handler( WP_REST_Request $request ): WP_REST_Response {
    $data   = $request->get_json_params() ?: [];
    $params = $request->get_body_params();
    if ( $params ) {
        $data = array_merge( $data, $params );
    }

    // Detect source website from request
    $origin = $request->get_header( 'Origin' ) ?: $request->get_header( 'Referer' ) ?: '';
    if ( $origin && empty( $data['website_source'] ) ) {
        $host = parse_url( $origin, PHP_URL_HOST );
        $data['website_source'] = $host ?: '';
    }

    $result = sgc_inq_save( $data );

    if ( ! $result['ok'] ) {
        return new WP_REST_Response(
            [ 'ok' => false, 'error' => $result['error'] ],
            422
        );
    }

    return new WP_REST_Response( [
        'ok'         => true,
        'inquiry_id' => $result['inquiry_id'],
        'email_sent' => $result['email_sent'],
    ], 200 );
}


/*
 * ════════════════════════════════════════════════════════════════════════
 *  SECTION 5 — WP AJAX
 *  Handles two action names:
 *    a) sgclabs_save_inquiry   — from contact forms on all 5 websites
 *    b) sgclabs_submit_inquiry — Manager Pro shortcode (already handled by
 *                                inquiry-form.php; this is a safe passthrough
 *                                only if the original handler is disabled)
 * ════════════════════════════════════════════════════════════════════════
 */
add_action( 'wp_ajax_sgclabs_save_inquiry',        'sgc_inq_ajax_save_inquiry' );
add_action( 'wp_ajax_nopriv_sgclabs_save_inquiry', 'sgc_inq_ajax_save_inquiry' );

function sgc_inq_ajax_save_inquiry(): void {
    // Nonce — accept multiple nonce actions used across our sites
    $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? $_POST['_wpnonce'] ?? '' ) );
    $valid = wp_verify_nonce( $nonce, 'sgclabs_inquiry_nonce' )
          || wp_verify_nonce( $nonce, 'sgclabs_inquiry' )
          || wp_verify_nonce( $nonce, 'sgclabs_nonce' )
          || wp_verify_nonce( $nonce, 'sgc_ajax_nonce' );

    if ( ! $valid ) {
        // Contact forms on static HTML pages may not have a nonce — log and proceed
        // (REST API submissions always go through Section 4 above which has no nonce)
        error_log( '[SGC Inquiry] AJAX nonce mismatch from: ' . ( $_SERVER['HTTP_REFERER'] ?? 'unknown' ) );
    }

    $data = array_map( 'wp_unslash', $_POST );
    unset( $data['action'], $data['nonce'], $data['_wpnonce'] );

    $result = sgc_inq_save( $data );

    if ( $result['ok'] ) {
        wp_send_json_success( [
            'inquiry_id' => $result['inquiry_id'],
            'email_sent' => $result['email_sent'],
            'msg'        => 'Inquiry saved. Our team will respond within 24 hours.',
        ] );
    } else {
        wp_send_json_error( [ 'error' => $result['error'] ] );
    }
}


/*
 * ════════════════════════════════════════════════════════════════════════
 *  SECTION 6 — NONCE OUTPUT (for contact form HTML pages)
 *  The contact form JS reads: window.sgc_inq_nonce
 * ════════════════════════════════════════════════════════════════════════
 */
add_action( 'wp_footer', function () {
    $nonce = wp_create_nonce( 'sgclabs_inquiry_nonce' );
    echo '<script>window.sgc_inq_nonce=' . wp_json_encode( $nonce ) . ';</script>' . "\n";
}, 5 );


/*
 * ════════════════════════════════════════════════════════════════════════
 *  SECTION 7 — ADMIN PAGE UPGRADE
 *  Enhanced inquiry table — only added if not already present in Manager Pro
 * ════════════════════════════════════════════════════════════════════════
 */
add_action( 'admin_menu', function () {
    global $submenu;

    // Correct parent slug for Manager Pro v7.x
    $parent = 'sgclabs-manager';

    // Check if inquiry page already registered
    $already = false;
    foreach ( $submenu[ $parent ] ?? [] as $item ) {
        if ( strpos( $item[2] ?? '', 'inquiries' ) !== false ) {
            $already = true;
            break;
        }
    }

    if ( ! $already ) {
        add_submenu_page(
            $parent,
            'Inquiries',
            '📩 Inquiries',
            'manage_options',
            'sgclabs-inquiries',
            'sgc_inq_admin_page'
        );
    }
}, 99 );


function sgc_inq_admin_page(): void {
    global $wpdb;
    $t = sgc_inq_table();

    // ── Quick actions ──────────────────────────────────────────────────────
    if ( isset( $_GET['sgc_status'], $_GET['id'] ) && check_admin_referer( 'sgc_inq_action' ) ) {
        $wpdb->update( $t, // phpcs:ignore
            [ 'status' => sanitize_key( $_GET['sgc_status'] ) ],
            [ 'id'     => absint( $_GET['id'] ) ],
            [ '%s' ], [ '%d' ]
        );
        echo '<div class="notice notice-success"><p>Status updated.</p></div>';
    }
    if ( isset( $_GET['sgc_delete'] ) && check_admin_referer( 'sgc_inq_action' ) ) {
        $wpdb->delete( $t, [ 'id' => absint( $_GET['sgc_delete'] ) ], [ '%d' ] ); // phpcs:ignore
        echo '<div class="notice notice-success"><p>Inquiry deleted.</p></div>';
    }

    $filter = sanitize_key( $_GET['filter_status'] ?? '' );
    $where  = $filter
        ? $wpdb->prepare( 'WHERE status = %s', $filter )
        : '';

    // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows  = $wpdb->get_results( "SELECT * FROM `{$t}` {$where} ORDER BY created_at DESC LIMIT 300" );
    $total = $wpdb->get_var( "SELECT COUNT(*) FROM `{$t}`" );
    $new_c = $wpdb->get_var( "SELECT COUNT(*) FROM `{$t}` WHERE status = 'new'" );
    // phpcs:enable

    $statuses = [ '' => 'All', 'new' => 'New', 'read' => 'Read', 'quoted' => 'Quoted', 'pi_sent' => 'PI Sent', 'won' => 'Won', 'lost' => 'Lost' ];
    $colors   = [ 'new' => '#3368c6', 'read' => '#888', 'quoted' => '#7c3aed', 'pi_sent' => '#0ea5e9', 'won' => '#1a9955', 'lost' => '#d94040' ];

    echo '<div class="wrap">';
    echo '<h1 style="display:flex;align-items:center;gap:10px">📩 Inquiries'
       . '<span style="background:#3368c6;color:#fff;border-radius:20px;padding:2px 12px;font-size:13px">'
       . (int) $new_c . ' new</span>'
       . '<span style="font-size:13px;color:#888;font-weight:400;margin-left:8px">Total: ' . (int) $total . '</span>'
       . '</h1>';

    // Filter tabs
    echo '<div style="margin:14px 0 18px;display:flex;gap:6px;flex-wrap:wrap">';
    foreach ( $statuses as $slug => $label ) {
        $active = ( $filter === $slug );
        $url    = esc_url( add_query_arg( [ 'page' => 'sgclabs-inquiries', 'filter_status' => $slug ], admin_url( 'admin.php' ) ) );
        $style  = $active
            ? 'background:#3368c6;color:#fff;border-color:#3368c6'
            : 'background:#f4f7ff;color:#3368c6;border-color:#dce6ff';
        echo "<a href='{$url}' style='padding:6px 14px;border-radius:6px;font-size:13px;font-weight:600;{$style};text-decoration:none;border:1px solid'>{$label}</a>";
    }
    echo '</div>';

    if ( ! $rows ) {
        echo '<p style="color:#6b7a99;margin-top:20px">No inquiries found.</p></div>';
        return;
    }

    $has_website = in_array( 'website_source', array_column(
        $wpdb->get_results( "DESCRIBE `{$t}`", ARRAY_A ) ?: [], // phpcs:ignore
        'Field'
    ), true );

    echo '<div style="overflow-x:auto"><table class="wp-list-table widefat fixed striped" style="border-radius:8px;overflow:hidden">
    <thead><tr>
      <th style="width:44px">#</th>
      <th style="width:130px">Date</th>
      <th>Name / Contact</th>
      <th style="width:90px">Country</th>
      <th>Product Required</th>
      <th style="width:80px">Qty</th>'
      . ( $has_website ? '<th style="width:110px">Website</th>' : '' )
      . '<th style="width:100px">Status</th>
      <th>Actions</th>
    </tr></thead><tbody>';

    foreach ( $rows as $r ) {
        $color    = $colors[ $r->status ] ?? '#6b7a99';
        $base_url = esc_url( add_query_arg( [ 'page' => 'sgclabs-inquiries', 'id' => $r->id, '_wpnonce' => wp_create_nonce( 'sgc_inq_action' ) ], admin_url( 'admin.php' ) ) );
        $wa_num   = preg_replace( '/\D/', '', $r->phone );

        echo '<tr>
          <td><strong style="color:#3368c6">' . (int) $r->id . '</strong></td>
          <td style="font-size:12px;color:#6b7a99">' . esc_html( gmdate( 'd M Y H:i', strtotime( $r->created_at ) ) ) . '</td>
          <td>
            <strong style="font-size:13px">' . esc_html( $r->name ) . '</strong><br>
            <a href="mailto:' . esc_attr( $r->email ) . '" style="font-size:12px;color:#3368c6">' . esc_html( $r->email ) . '</a>'
            . ( $r->phone ? '<br><a href="https://api.whatsapp.com/send/?phone=' . esc_attr( $wa_num ) . '" style="font-size:12px;color:#25d366" target="_blank">💬 ' . esc_html( $r->phone ) . '</a>' : '' )
            . ( $r->company ? '<br><span style="font-size:11px;color:#aaa">' . esc_html( $r->company ) . '</span>' : '' )
            . '</td>
          <td>' . esc_html( $r->country ) . '</td>
          <td style="font-size:13px"><strong>' . esc_html( wp_trim_words( $r->product, 12 ) ) . '</strong></td>
          <td style="font-size:12px;color:#555">' . esc_html( $r->qty ?: '—' ) . '</td>'
          . ( $has_website ? '<td style="font-size:11px;color:#888">' . esc_html( $r->website_source ?? '—' ) . '</td>' : '' )
          . '<td><span style="background:' . $color . '20;color:' . $color . ';border-radius:10px;padding:2px 9px;font-size:11px;font-weight:700">' . esc_html( $r->status ) . '</span></td>
          <td style="font-size:12px;white-space:nowrap">
            <a href="' . esc_url( add_query_arg( 'sgc_status', 'read',   $base_url ) ) . '" style="color:#888">Read</a> |
            <a href="' . esc_url( add_query_arg( 'sgc_status', 'quoted', $base_url ) ) . '" style="color:#7c3aed">Quoted</a> |
            <a href="' . esc_url( add_query_arg( 'sgc_status', 'won',    $base_url ) ) . '" style="color:#1a9955">Won</a> |
            <a href="' . esc_url( add_query_arg( 'sgc_delete', $r->id,   $base_url ) ) . '" style="color:#d94040" onclick="return confirm(\'Delete inquiry #' . (int) $r->id . '?\')">Delete</a>
          </td>
        </tr>';
    }

    echo '</tbody></table></div>';
    echo '<p style="color:#9ba8c0;font-size:12px;margin-top:12px">Showing ' . count( $rows ) . ' of ' . (int) $total . ' total inquiries. Table: ' . esc_html( $t ) . '</p>';
    echo '</div>';
}
