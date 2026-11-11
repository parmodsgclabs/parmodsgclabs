<?php
/**
 * ============================================================
 * SGC LABS® — Inquiry Automation v7.3.1
 * ============================================================
 * Features:
 *  1. sgclabs_inquiry_received hook listener — was dead, now active
 *  2. Admin notification email on every new inquiry (all sources)
 *  3. CRM auto-contact create (if SGC Smart CRM Pro active)
 *  4. 3-day follow-up email — scheduled via WP-Cron per inquiry
 *  5. Follow-up tracking — follow_up_sent + follow_up_at columns
 *  6. Manual follow-up trigger from admin
 *  7. Settings: follow-up on/off, delay days, email template
 * ============================================================
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── 1. LISTEN TO sgclabs_inquiry_received ────────────────────────────────────
// This hook was fired by SGCLabs_Inquiry_Store::add() but nobody listened.
// Now it also fires from b2b-quote-engine.php (v7.3.1) and get-a-quote.php.

add_action( 'sgclabs_inquiry_received', 'sgclabs_automation_on_inquiry', 10, 1 );

function sgclabs_automation_on_inquiry( $record ) {
    if ( ! is_array( $record ) ) return;

    $email   = sanitize_email( $record['email'] ?? '' );
    $name    = sanitize_text_field( $record['name'] ?? 'Customer' );
    $source  = sanitize_text_field( $record['source'] ?? 'unknown' );
    $db_id   = intval( $record['db_id'] ?? 0 );

    // ── 1a. CRM auto-contact create ──────────────────────────────────────────
    if ( class_exists( 'SGC_CRM_DB' ) && $email ) {
        sgclabs_automation_crm_sync( $record );
    }

    // ── 1b. Schedule 3-day follow-up (only for export inquiries, not WC orders) ─
    if ( get_option( 'sgclabs_followup_active', '1' ) === '1'
         && $email
         && ! in_array( $source, [ 'woocommerce', 'manual' ], true )
         && $db_id > 0
    ) {
        $delay_days = max( 1, intval( get_option( 'sgclabs_followup_delay_days', 3 ) ) );
        $scheduled_at = time() + ( $delay_days * DAY_IN_SECONDS );

        // Prevent duplicate cron for same db_id
        if ( ! wp_next_scheduled( 'sgclabs_send_inquiry_followup', [ $db_id ] ) ) {
            wp_schedule_single_event( $scheduled_at, 'sgclabs_send_inquiry_followup', [ $db_id ] );
        }
    }
}

// ── 2. FOLLOW-UP CRON HANDLER ─────────────────────────────────────────────────
add_action( 'sgclabs_send_inquiry_followup', 'sgclabs_automation_send_followup', 10, 1 );

function sgclabs_automation_send_followup( $inq_id ) {
    if ( get_option( 'sgclabs_followup_active', '1' ) !== '1' ) return;

    global $wpdb;
    $inq = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sgclabs_inquiries WHERE id = %d",
        intval( $inq_id )
    ) );

    if ( ! $inq ) return;
    if ( ! empty( $inq->follow_up_sent ) ) return; // already sent
    // Don't follow-up on won/lost inquiries
    if ( in_array( $inq->status, [ 'won', 'lost', 'pi_sent' ], true ) ) return;

    $to      = sanitize_email( $inq->email );
    $name    = sanitize_text_field( $inq->name );
    $product = sanitize_text_field( $inq->product ?? '' );
    $inq_no  = sanitize_text_field( $inq->inq_number ?? "INQ#{$inq->id}" );

    if ( ! is_email( $to ) ) return;

    $from_name  = get_option( 'sgclabs_followup_from_name',  'SGC Labs Sales Team' );
    $from_email = get_option( 'sgclabs_followup_from_email', 'sales@labsglass.com' );
    $company    = get_bloginfo( 'name' );

    $subject = get_option(
        'sgclabs_followup_subject',
        "Following up on your inquiry [{$inq_no}] — SGC LABS®"
    );
    $subject = str_replace( ['{inq_no}', '{name}', '{product}'], [ $inq_no, $name, $product ], $subject );

    $custom_body = get_option( 'sgclabs_followup_body', '' );
    if ( $custom_body ) {
        $body_text = str_replace(
            [ '{name}', '{inq_no}', '{product}', '{company}' ],
            [ $name, $inq_no, $product, $company ],
            $custom_body
        );
        $body_html = nl2br( esc_html( $body_text ) );
    } else {
        $body_html = "
            <p>Hi <strong>{$name}</strong>,</p>
            <p>We wanted to follow up on your inquiry <strong style='font-family:monospace;'>{$inq_no}</strong>
               regarding <strong>" . esc_html( $product ) . "</strong>.</p>
            <p>Our team has reviewed your requirement and would love to send you a formal quotation.
               Could you please confirm the following:</p>
            <ul style='margin:8px 0 8px 20px;'>
                <li>Required quantity / lot size</li>
                <li>Preferred delivery port / Incoterm</li>
                <li>Any specific standard (ISO / ASTM / custom grade)?</li>
            </ul>
            <p>Simply reply to this email or reach us on WhatsApp at <strong>+91 999 641 6935</strong>.</p>
            <p>We look forward to serving you!</p>
        ";
    }

    $html = "
    <html><body style='font-family:Arial,sans-serif;font-size:14px;color:#333;background:#f0f2f5;padding:20px;'>
    <div style='max-width:580px;margin:0 auto;background:#fff;border-radius:10px;overflow:hidden;'>
      <div style='background:#1a1a2e;padding:20px 28px;'>
        <p style='margin:0;font-size:20px;font-weight:900;color:#27ae60;'>SGC LABS®</p>
        <p style='margin:4px 0 0;font-size:11px;color:rgba(255,255,255,.6);'>Scientific Glassware Manufacturer · Ambala, India</p>
      </div>
      <div style='padding:24px 28px;'>
        {$body_html}
        <p style='margin-top:20px;font-size:13px;color:#64748b;border-top:1px solid #f0f0f0;padding-top:16px;'>
          {$from_name}<br>
          <a href='mailto:{$from_email}' style='color:#27ae60;'>{$from_email}</a> | +91 999 641 6935<br>
          <a href='https://labsglass.com' style='color:#27ae60;'>labsglass.com</a>
        </p>
      </div>
    </div>
    </body></html>";

    $sent = wp_mail(
        $to,
        $subject,
        $html,
        [
            "Content-Type: text/html; charset=UTF-8",
            "From: {$from_name} <{$from_email}>",
        ]
    );

    if ( $sent ) {
        $wpdb->update(
            $wpdb->prefix . 'sgclabs_inquiries',
            [ 'follow_up_sent' => 1, 'follow_up_at' => current_time( 'mysql' ) ],
            [ 'id' => intval( $inq_id ) ]
        );
        // Also log in admin
        $log = (array) get_option( 'sgclabs_followup_log', [] );
        $log[] = [
            'inq_id'  => $inq_id,
            'email'   => $to,
            'sent_at' => current_time( 'mysql' ),
        ];
        // Keep last 200 log entries
        if ( count( $log ) > 200 ) $log = array_slice( $log, -200 );
        update_option( 'sgclabs_followup_log', $log, false );
    }
}

// ── 3. CRM AUTO-SYNC ──────────────────────────────────────────────────────────
function sgclabs_automation_crm_sync( array $record ) {
    if ( ! class_exists( 'SGC_CRM_DB' ) ) return;

    $email   = sanitize_email( $record['email'] ?? '' );
    if ( ! is_email( $email ) ) return;

    // Check if contact already exists in CRM
    global $wpdb;
    $crm_table = $wpdb->prefix . 'sgc_crm_contacts';

    $exists = $wpdb->get_var( $wpdb->prepare(
        "SHOW TABLES LIKE %s", $crm_table
    ) );
    if ( ! $exists ) return;

    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$crm_table} WHERE email = %s LIMIT 1",
        $email
    ) );

    if ( $existing ) {
        // Update last_inquiry_at if column exists
        $cols = $wpdb->get_col( "DESCRIBE {$crm_table}" );
        if ( in_array( 'last_inquiry_at', $cols, true ) ) {
            $wpdb->update( $crm_table, [ 'last_inquiry_at' => current_time('mysql') ], [ 'email' => $email ] );
        }
        return; // contact already exists, no duplicate
    }

    // Create new CRM contact (segment from the form's "Who are you?" answer when given)
    $name_parts = explode( ' ', sanitize_text_field( $record['name'] ?? '' ), 2 );
    $bt  = sanitize_text_field( $record['buyer_type'] ?? ( $record['meta']['buyer_type'] ?? '' ) );
    $seg = ( $bt && $bt !== 'Not Specified' && class_exists( 'SGC_CRM_Integration' ) && method_exists( 'SGC_CRM_Integration', 'segment_from_buyer_type' ) )
         ? SGC_CRM_Integration::segment_from_buyer_type( $bt, (string) ( $record['country'] ?? '' ) ) : '';
    $wpdb->insert( $crm_table, array_filter( [
        'first_name'  => $name_parts[0] ?? '',
        'last_name'   => $name_parts[1] ?? '',
        'email'       => $email,
        'phone'       => sanitize_text_field( $record['phone'] ?? '' ),
        'company'     => sanitize_text_field( $record['company'] ?? '' ),
        'country'     => sanitize_text_field( $record['country'] ?? '' ),
        'source'      => 'inquiry_auto',
        'segment'     => $seg,
        'tags'        => trim( ( sanitize_text_field( $record['source'] ?? 'inquiry' ) ?: 'inquiry' ) . ( $bt && $bt !== 'Not Specified' ? ',' . sanitize_title( $bt ) : '' ) ),
        'notes'       => 'Inquiry: ' . sanitize_text_field( $record['product_title'] ?? $record['subject'] ?? '' ), // v7.5.1: NOT NULL column — insert silently failed without it
        'created_at'  => current_time( 'mysql' ),
        'updated_at'  => current_time( 'mysql' ),
    ], function($v){ return $v !== ''; } ) );
}

// ── 4. MANUAL FOLLOW-UP TRIGGER (Admin AJAX) ─────────────────────────────────
add_action( 'wp_ajax_sgclabs_manual_followup', function() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $inq_id = intval( $_POST['inq_id'] ?? 0 );
    if ( ! $inq_id ) wp_send_json_error( 'Invalid ID' );

    sgclabs_automation_send_followup( $inq_id );
    wp_send_json_success( 'Follow-up sent' );
} );

// ── 5. ENSURE FOLLOW-UP COLUMNS EXIST (safe alter on init) ───────────────────
add_action( 'init', 'sgclabs_automation_ensure_columns', 20 );
function sgclabs_automation_ensure_columns() {
    if ( get_option( 'sgclabs_automation_cols_v731' ) === '1' ) return;
    global $wpdb;
    $table = $wpdb->prefix . 'sgclabs_inquiries';
    $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    if ( ! $exists ) return;

    $cols = $wpdb->get_col( "DESCRIBE {$table}" );
    if ( ! in_array( 'follow_up_sent', $cols, true ) ) {
        $wpdb->query( "ALTER TABLE {$table} ADD COLUMN follow_up_sent tinyint(1) DEFAULT 0 COMMENT '1 = follow-up sent'" );
    }
    if ( ! in_array( 'follow_up_at', $cols, true ) ) {
        $wpdb->query( "ALTER TABLE {$table} ADD COLUMN follow_up_at datetime DEFAULT NULL COMMENT 'Timestamp follow-up sent'" );
    }
    if ( ! in_array( 'source_type', $cols, true ) ) {
        $wpdb->query( "ALTER TABLE {$table} ADD COLUMN source_type varchar(30) DEFAULT 'quote' COMMENT 'quote|contact|b2b|manual|wc_order'" );
    }
    update_option( 'sgclabs_automation_cols_v731', '1' );
}

// ── 6. ADMIN SETTINGS PAGE ───────────────────────────────────────────────────
add_action( 'admin_menu', function() {
    add_submenu_page(
        'sgclabs-manager',
        'Inquiry Automation',
        '🤖 Inq. Automation',
        'manage_options',
        'sgclabs-inq-automation',
        'sgclabs_automation_settings_page'
    );
}, 19 );

function sgclabs_automation_settings_page() {
    if ( isset( $_POST['sgclabs_auto_save'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'sgclabs_automation_save' ) ) {
        update_option( 'sgclabs_followup_active',      sanitize_text_field( $_POST['followup_active']      ?? '0' ) );
        update_option( 'sgclabs_followup_delay_days',  intval( $_POST['followup_delay_days']               ?? 3 ) );
        update_option( 'sgclabs_followup_from_name',   sanitize_text_field( $_POST['followup_from_name']   ?? '' ) );
        update_option( 'sgclabs_followup_from_email',  sanitize_email(      $_POST['followup_from_email']  ?? '' ) );
        update_option( 'sgclabs_followup_subject',     sanitize_text_field( $_POST['followup_subject']     ?? '' ) );
        update_option( 'sgclabs_followup_body',        sanitize_textarea_field( $_POST['followup_body']    ?? '' ) );
        echo '<div class="notice notice-success"><p>✅ Automation settings saved.</p></div>';
    }

    $log = array_reverse( (array) get_option( 'sgclabs_followup_log', [] ) );
    ?>
    <div class="wrap">
    <h1>🤖 Inquiry Automation — v7.3.1</h1>
    <p style="color:#666;font-size:13px;max-width:700px;">
        Har nai inquiry pe automatically: (1) CRM mein contact ban jata hai, (2) 3-day follow-up email schedule hoti hai.
        <code>sgclabs_inquiry_received</code> hook ab active hai — sab sources (Get-a-Quote, Inquiry form, B2B Quote) trigger karte hain.
    </p>

    <form method="post">
    <?php wp_nonce_field('sgclabs_automation_save'); ?>
    <table class="form-table">
        <tr><th>Follow-up emails</th><td>
            <label><input type="checkbox" name="followup_active" value="1" <?php checked(get_option('sgclabs_followup_active','1'),'1'); ?>>
            Enable automatic follow-up emails</label>
        </td></tr>
        <tr><th>Delay (days)</th><td>
            <input type="number" name="followup_delay_days" value="<?php echo intval(get_option('sgclabs_followup_delay_days',3)); ?>" min="1" max="30" style="width:70px;">
            <p class="description">Inquiry aane ke kitne din baad follow-up email bhejni hai (default: 3 days)</p>
        </td></tr>
        <tr><th>From name</th><td>
            <input type="text" name="followup_from_name" value="<?php echo esc_attr(get_option('sgclabs_followup_from_name','SGC Labs Sales Team')); ?>" class="regular-text">
        </td></tr>
        <tr><th>From email</th><td>
            <input type="email" name="followup_from_email" value="<?php echo esc_attr(get_option('sgclabs_followup_from_email','sales@labsglass.com')); ?>" class="regular-text">
        </td></tr>
        <tr><th>Subject line</th><td>
            <input type="text" name="followup_subject" value="<?php echo esc_attr(get_option('sgclabs_followup_subject','')); ?>" class="large-text" placeholder="Following up on your inquiry [{inq_no}] — SGC LABS®">
            <p class="description">Variables: <code>{inq_no}</code> <code>{name}</code> <code>{product}</code></p>
        </td></tr>
        <tr><th>Email body</th><td>
            <textarea name="followup_body" rows="8" class="large-text" placeholder="Leave empty for default template. Variables: {name} {inq_no} {product} {company}"><?php echo esc_textarea(get_option('sgclabs_followup_body','')); ?></textarea>
            <p class="description">Custom body text. Plain text only — will be auto-wrapped in HTML. Leave blank for default SGC template.</p>
        </td></tr>
    </table>
    <p><input type="submit" name="sgclabs_auto_save" class="button-primary" value="Save Automation Settings"></p>
    </form>

    <!-- CRM status -->
    <h2 style="margin-top:28px;">🔗 CRM Integration Status</h2>
    <table class="widefat" style="max-width:500px;">
        <tr>
            <td>SGC Smart CRM Pro</td>
            <td><?php echo class_exists('SGC_CRM_DB') ? '<span style="color:#27ae60;font-weight:600;">✅ Active — auto-sync ON</span>' : '<span style="color:#999;">⭕ Not active — install SGC CRM Pro to enable auto-sync</span>'; ?></td>
        </tr>
        <tr>
            <td>Follow-up cron</td>
            <td><?php echo get_option('sgclabs_followup_active','1')==='1' ? '<span style="color:#27ae60;">✅ Active</span>' : '<span style="color:#e74c3c;">❌ Disabled</span>'; ?></td>
        </tr>
        <tr>
            <td>Follow-up delay</td>
            <td><?php echo intval(get_option('sgclabs_followup_delay_days',3)); ?> days after inquiry</td>
        </tr>
    </table>

    <!-- Follow-up log -->
    <h2 style="margin-top:28px;">📋 Follow-up Log (last 20)</h2>
    <?php if (empty($log)): ?>
        <p style="color:#888;">No follow-up emails sent yet.</p>
    <?php else: ?>
    <table class="widefat" style="max-width:600px;">
        <thead><tr><th>Inquiry ID</th><th>Email</th><th>Sent At</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($log, 0, 20) as $entry): ?>
            <tr>
                <td>#<?php echo intval($entry['inq_id'] ?? 0); ?></td>
                <td><?php echo esc_html($entry['email'] ?? ''); ?></td>
                <td><?php echo esc_html($entry['sent_at'] ?? ''); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    </div>
    <?php
}

// ── 7. DEFAULT OPTIONS ON ACTIVATE ───────────────────────────────────────────
add_action( 'sgclabs_on_activate', function() {
    add_option( 'sgclabs_followup_active',     '1' );
    add_option( 'sgclabs_followup_delay_days', '3' );
    add_option( 'sgclabs_followup_from_name',  'SGC Labs Sales Team' );
    add_option( 'sgclabs_followup_from_email', 'sales@labsglass.com' );
} );
