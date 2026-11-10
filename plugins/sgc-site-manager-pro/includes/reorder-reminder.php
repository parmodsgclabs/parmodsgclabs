<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================
// SGC LABS® — PREDICTIVE REORDER REMINDER SYSTEM v6.7.5
// Features:
//  1. Order complete hone pe products + date save
//  2. AI se reorder interval predict karo per product
//  3. Daily WP-Cron check — email send karo
//  4. Professional HTML email template
//  5. Unsubscribe + "Remind later" support
//  6. Admin dashboard — stats + manual trigger
// ============================================================

define( 'SGCLABS_REORDER_META',     '_sgclabs_reorder_interval' );
define( 'SGCLABS_REORDER_LOG',      'sgclabs_reorder_log' );
define( 'SGCLABS_REORDER_UNSUB',    'sgclabs_reorder_unsub' );

// ── 1. TRACK ORDERS — save on completion ─────────────────────
add_action( 'woocommerce_order_status_completed', 'sgclabs_reorder_track_order' );
add_action( 'woocommerce_order_status_processing', 'sgclabs_reorder_track_order' );
function sgclabs_reorder_track_order( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;

    $customer_email = $order->get_billing_email();
    $customer_name  = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
    if ( empty( $customer_email ) ) return;

    // Check unsubscribed
    $unsub_list = (array) get_option( SGCLABS_REORDER_UNSUB, [] );
    if ( in_array( $customer_email, $unsub_list, true ) ) return;

    $order_date = current_time( 'mysql' );
    $products   = [];

    foreach ( $order->get_items() as $item ) {
        $pid     = $item->get_product_id();
        $product = wc_get_product( $pid );
        if ( ! $product ) continue;

        // Get or predict reorder interval
        $interval = (int) get_post_meta( $pid, SGCLABS_REORDER_META, true );
        if ( ! $interval ) {
            $interval = 90; // Default 90 days — will be updated by AI
            wp_schedule_single_event( time() + 30, 'sgclabs_predict_reorder_interval', [ $pid ] );
        }

        $products[] = [
            'id'       => $pid,
            'name'     => $product->get_name(),
            'qty'      => $item->get_quantity(),
            'price'    => (float) $item->get_total() / $item->get_quantity(),
            'sku'      => $product->get_sku(),
            'interval' => $interval,
            'image'    => get_the_post_thumbnail_url( $pid, 'thumbnail' ) ?: '',
        ];
    }

    if ( empty( $products ) ) return;

    // Save customer reorder record
    $existing = get_option( 'sgclabs_reorder_customer_' . md5( $customer_email ), [] );
    $existing[] = [
        'order_id'    => $order_id,
        'order_date'  => $order_date,
        'name'        => trim( $customer_name ),
        'email'       => $customer_email,
        'products'    => $products,
        'reminded'    => false,
        'remind_date' => '',
        'order_value' => (float) $order->get_total(),
        'currency'    => $order->get_currency(),
    ];

    // Keep last 5 orders per customer
    $existing = array_slice( $existing, -5 );
    update_option( 'sgclabs_reorder_customer_' . md5( $customer_email ), $existing, false );

    // Add to global customer index
    $index = (array) get_option( 'sgclabs_reorder_index', [] );
    $index[ md5( $customer_email ) ] = [
        'email'      => $customer_email,
        'name'       => trim( $customer_name ),
        'last_order' => $order_date,
        'order_id'   => $order_id,
    ];
    update_option( 'sgclabs_reorder_index', $index, false );
}

// ── 2. AI INTERVAL PREDICTION ─────────────────────────────────
add_action( 'sgclabs_predict_reorder_interval', 'sgclabs_ai_predict_interval' );
function sgclabs_ai_predict_interval( $pid ) {
    $product = wc_get_product( $pid );
    if ( ! $product ) return;

    // Already predicted
    if ( get_post_meta( $pid, SGCLABS_REORDER_META, true ) ) return;

    $name  = $product->get_name();
    $cats  = wp_get_post_terms( $pid, 'product_cat', [ 'fields' => 'names' ] );
    $cat   = ! empty( $cats ) ? $cats[0] : 'Laboratory Glassware';

    $prompt = "Laboratory glassware product reorder frequency expert.

Product: {$name}
Category: {$cat}
Manufacturer: SGC LABS India (B2B export, research labs, pharma, universities)

Based on typical laboratory usage patterns, what is the average reorder interval in DAYS for this product?

Consider:
- Beakers, flasks, test tubes: high breakage = 60-90 days
- Burettes, pipettes: medium usage = 90-120 days  
- Stopcocks, special components: low usage = 120-180 days
- Consumables: 30-60 days
- Expensive equipment: 180-365 days

Return ONLY a number (days). Example: 90";

    $result = sgclabs_ai_call_for_task( 'seo_title', $prompt, 20, '' );

    if ( ! is_wp_error( $result ) ) {
        preg_match( '/\d+/', trim( $result ), $m );
        $days = isset( $m[0] ) ? (int) $m[0] : 90;
        $days = max( 30, min( 365, $days ) ); // Clamp 30-365
        update_post_meta( $pid, SGCLABS_REORDER_META, $days );
    }
}

// ── 3. DAILY CRON — Check & Send Reminders ───────────────────
add_action( 'wp', 'sgclabs_reorder_schedule_cron' );
function sgclabs_reorder_schedule_cron() {
    if ( ! wp_next_scheduled( 'sgclabs_reorder_daily_check' ) ) {
        wp_schedule_event( strtotime( 'tomorrow 9:00am' ), 'daily', 'sgclabs_reorder_daily_check' );
    }
}

add_action( 'sgclabs_reorder_daily_check', 'sgclabs_reorder_process_reminders' );
function sgclabs_reorder_process_reminders() {
    if ( get_option( 'sgclabs_reorder_active', '1' ) !== '1' ) return;

    $index   = (array) get_option( 'sgclabs_reorder_index', [] );
    $sent    = 0;
    $skipped = 0;

    foreach ( $index as $hash => $customer ) {
        $email   = $customer['email'];
        $unsub   = (array) get_option( SGCLABS_REORDER_UNSUB, [] );
        if ( in_array( $email, $unsub, true ) ) { $skipped++; continue; }

        $records = (array) get_option( 'sgclabs_reorder_customer_' . $hash, [] );
        if ( empty( $records ) ) continue;

        // Get latest order
        $latest = end( $records );
        if ( $latest['reminded'] ) { $skipped++; continue; }

        // Calculate next remind date
        $order_date    = strtotime( $latest['order_date'] );
        $min_interval  = PHP_INT_MAX;
        foreach ( $latest['products'] as $p ) {
            $interval = (int)( $p['interval'] ?? 90 );
            if ( $interval < $min_interval ) $min_interval = $interval;
        }

        // Check custom snooze
        $snooze_key  = 'sgclabs_reorder_snooze_' . md5( $email );
        $snoozed_until = (int) get_option( $snooze_key, 0 );
        if ( $snoozed_until > time() ) { $skipped++; continue; }

        $remind_at = $order_date + ( $min_interval * DAY_IN_SECONDS );

        // Send if time has come (within 3 day window)
        if ( time() >= $remind_at && time() <= ( $remind_at + 3 * DAY_IN_SECONDS ) ) {
            $result = sgclabs_reorder_send_email( $latest, $hash );
            if ( $result ) {
                // Mark as reminded
                $records[ array_key_last( $records ) ]['reminded']    = true;
                $records[ array_key_last( $records ) ]['remind_date'] = current_time( 'mysql' );
                update_option( 'sgclabs_reorder_customer_' . $hash, $records, false );
                $sent++;

                // Log it
                $log   = (array) get_option( SGCLABS_REORDER_LOG, [] );
                $log[] = [
                    'time'     => current_time( 'mysql' ),
                    'email'    => $email,
                    'name'     => $latest['name'],
                    'products' => count( $latest['products'] ),
                    'status'   => 'sent',
                ];
                update_option( SGCLABS_REORDER_LOG, array_slice( $log, -200 ), false );
            }
        } else {
            $skipped++;
        }
    }

    update_option( 'sgclabs_reorder_last_run', [
        'time'    => current_time( 'mysql' ),
        'sent'    => $sent,
        'skipped' => $skipped,
    ] );
}

// ── 4. SEND EMAIL ─────────────────────────────────────────────
function sgclabs_reorder_send_email( array $record, string $hash ): bool {
    $to      = $record['email'];
    $name    = $record['name'] ?: 'Valued Customer';
    $fname   = explode( ' ', $name )[0];
    $subject = get_option( 'sgclabs_reorder_subject', 'Time to Restock Your Lab Supplies — SGC LABS®' );

    $from_name  = get_option( 'sgclabs_reorder_from_name', 'SGC LABS® — Laboratory Glassware' );
    $from_email = get_option( 'sgclabs_reorder_from_email', get_option( 'admin_email' ) );
    $shop_url   = home_url();
    $logo_url   = get_option( 'sgclabs_reorder_logo', home_url( '/wp-content/uploads/2026/03/cropped-sgc-logo-resized.png' ) );
    $accent     = get_option( 'sgclabs_reorder_accent_color', '#1565c0' );

    // Unsubscribe + snooze tokens
    $unsub_token = base64_encode( $to . '|unsub|' . wp_hash( $to . 'unsub' ) );
    $snooze_token = base64_encode( $to . '|snooze30|' . wp_hash( $to . 'snooze30' ) );
    $unsub_url   = add_query_arg( [ 'sgclabs_reorder_action' => $unsub_token ], home_url() );
    $snooze_url  = add_query_arg( [ 'sgclabs_reorder_action' => $snooze_token ], home_url() );

    // Build products HTML
    $products_html = '';
    foreach ( $record['products'] as $p ) {
        $product_url = get_permalink( $p['id'] );
        $img_html    = $p['image']
            ? "<img src='{$p['image']}' width='60' height='60' style='border-radius:6px;object-fit:cover;border:1px solid #e5e7eb;'>"
            : "<div style='width:60px;height:60px;background:#f0f5ff;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:20px;'>🧪</div>";

        $products_html .= "
        <tr>
          <td style='padding:12px 0;border-bottom:1px solid #f3f4f6;vertical-align:top;width:72px;'>{$img_html}</td>
          <td style='padding:12px 0 12px 14px;border-bottom:1px solid #f3f4f6;vertical-align:top;'>
            <div style='font-weight:600;font-size:14px;color:#1a1a2e;margin-bottom:3px;'>{$p['name']}</div>
            <div style='font-size:12px;color:#6b7280;'>SKU: {$p['sku']} &nbsp;|&nbsp; Last qty: {$p['qty']} pcs</div>
            <div style='font-size:13px;font-weight:600;color:{$accent};margin-top:3px;'>\$" . number_format( $p['price'], 2 ) . "/pc</div>
          </td>
          <td style='padding:12px 0 12px 14px;border-bottom:1px solid #f3f4f6;vertical-align:middle;text-align:right;'>
            <a href='{$product_url}' style='background:{$accent};color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none;font-size:12px;font-weight:600;white-space:nowrap;'>Reorder →</a>
          </td>
        </tr>";
    }

    $shop_btn_text = get_option( 'sgclabs_reorder_btn_text', 'View My Products & Reorder' );
    $custom_note   = get_option( 'sgclabs_reorder_note', 'As a valued SGC LABS customer, you receive priority processing on reorders. Same quality, same specifications — guaranteed.' );

    $html = "<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#f4f6f9;font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Arial,sans-serif;'>
<table width='100%' cellpadding='0' cellspacing='0' style='background:#f4f6f9;padding:30px 0;'>
<tr><td align='center'>
<table width='600' cellpadding='0' cellspacing='0' style='background:#fff;border-radius:12px;overflow:hidden;max-width:600px;width:100%;'>

  <!-- Header -->
  <tr><td style='background:{$accent};padding:24px 32px;text-align:center;'>
    <img src='{$logo_url}' height='45' alt='SGC LABS' style='max-height:45px;'>
    <div style='color:rgba(255,255,255,.8);font-size:12px;margin-top:8px;letter-spacing:.5px;text-transform:uppercase;'>Laboratory Glassware Manufacturer — Since 2003</div>
  </td></tr>

  <!-- Hero -->
  <tr><td style='padding:32px 32px 20px;text-align:center;'>
    <div style='font-size:40px;margin-bottom:12px;'>🔬</div>
    <h1 style='margin:0 0 10px;font-size:24px;color:#1a1a2e;font-weight:700;'>Time to Restock, {$fname}!</h1>
    <p style='margin:0;font-size:15px;color:#6b7280;line-height:1.6;'>Based on your previous order, your lab supplies may be running low.<br>Here's what you ordered last time:</p>
  </td></tr>

  <!-- Products -->
  <tr><td style='padding:0 32px 24px;'>
    <div style='background:#f8f9fa;border-radius:10px;padding:20px;'>
      <div style='font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;'>YOUR PREVIOUS ORDER</div>
      <table width='100%' cellpadding='0' cellspacing='0'>
        {$products_html}
      </table>
    </div>
  </td></tr>

  <!-- CTA Button -->
  <tr><td style='padding:0 32px 24px;text-align:center;'>
    <a href='{$shop_url}/products/' style='display:inline-block;background:{$accent};color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-size:15px;font-weight:700;letter-spacing:.3px;'>{$shop_btn_text}</a>
  </td></tr>

  <!-- Custom Note -->
  <tr><td style='padding:0 32px 24px;'>
    <div style='background:#f0f7ff;border-left:4px solid {$accent};border-radius:0 8px 8px 0;padding:14px 16px;'>
      <div style='font-size:13px;color:#1e40af;line-height:1.6;'>{$custom_note}</div>
    </div>
  </td></tr>

  <!-- Why SGC LABS trust bar -->
  <tr><td style='padding:0 32px 28px;'>
    <table width='100%' cellpadding='0' cellspacing='0'>
      <tr>
        <td style='text-align:center;padding:8px;'>
          <div style='font-size:20px;'>✅</div>
          <div style='font-size:11px;font-weight:600;color:#374151;margin-top:4px;'>ISO Certified</div>
        </td>
        <td style='text-align:center;padding:8px;'>
          <div style='font-size:20px;'>🏭</div>
          <div style='font-size:11px;font-weight:600;color:#374151;margin-top:4px;'>Factory Direct</div>
        </td>
        <td style='text-align:center;padding:8px;'>
          <div style='font-size:20px;'>🌍</div>
          <div style='font-size:11px;font-weight:600;color:#374151;margin-top:4px;'>5000+ Clients</div>
        </td>
        <td style='text-align:center;padding:8px;'>
          <div style='font-size:20px;'>🚢</div>
          <div style='font-size:11px;font-weight:600;color:#374151;margin-top:4px;'>FOB New Delhi</div>
        </td>
      </tr>
    </table>
  </td></tr>

  <!-- Footer -->
  <tr><td style='background:#f8f9fa;border-top:1px solid #e5e7eb;padding:20px 32px;text-align:center;'>
    <p style='margin:0 0 10px;font-size:12px;color:#9ca3af;'>SGC LABS® | A-Block, Industrial Area, Ambala, Haryana, India</p>
    <p style='margin:0;font-size:11px;'>
      <a href='{$snooze_url}' style='color:#6b7280;text-decoration:none;'>⏰ Remind me in 30 days</a>
      &nbsp;&nbsp;|&nbsp;&nbsp;
      <a href='{$unsub_url}' style='color:#6b7280;text-decoration:none;'>Unsubscribe from reorder reminders</a>
    </p>
  </td></tr>

</table>
</td></tr>
</table>
</body></html>";

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        "From: {$from_name} <{$from_email}>",
        "Reply-To: {$from_email}",
    ];

    return wp_mail( $to, $subject, $html, $headers );
}

// ── 5. HANDLE UNSUBSCRIBE + SNOOZE ───────────────────────────
add_action( 'init', 'sgclabs_reorder_handle_actions' );
function sgclabs_reorder_handle_actions() {
    if ( empty( $_GET['sgclabs_reorder_action'] ) ) return;

    $token = sanitize_text_field( $_GET['sgclabs_reorder_action'] );
    $data  = base64_decode( $token );
    $parts = explode( '|', $data );

    if ( count( $parts ) < 3 ) return;

    [ $email, $action, $hash ] = $parts;
    $email = sanitize_email( $email );

    if ( $action === 'unsub' && wp_hash( $email . 'unsub' ) === $hash ) {
        $unsub = (array) get_option( SGCLABS_REORDER_UNSUB, [] );
        if ( ! in_array( $email, $unsub, true ) ) {
            $unsub[] = $email;
            update_option( SGCLABS_REORDER_UNSUB, $unsub );
        }
        wp_die(
            '<div style="font-family:sans-serif;text-align:center;padding:60px 20px;"><h2>✅ Unsubscribed</h2><p>You will no longer receive reorder reminders from SGC LABS.<br><a href="' . esc_url( home_url() ) . '">← Return to store</a></p></div>',
            'Unsubscribed — SGC LABS',
            [ 'response' => 200 ]
        );
    }

    if ( $action === 'snooze30' && wp_hash( $email . 'snooze30' ) === $hash ) {
        update_option( 'sgclabs_reorder_snooze_' . md5( $email ), time() + ( 30 * DAY_IN_SECONDS ) );
        wp_die(
            '<div style="font-family:sans-serif;text-align:center;padding:60px 20px;"><h2>⏰ Reminder Snoozed</h2><p>We will remind you again in 30 days.<br><a href="' . esc_url( home_url() ) . '">← Return to store</a></p></div>',
            'Reminder Snoozed — SGC LABS',
            [ 'response' => 200 ]
        );
    }
}

// ── 6. AJAX: Manual send + test ──────────────────────────────
add_action( 'wp_ajax_sgclabs_reorder_manual_send', 'sgclabs_reorder_manual_send_handler' );
function sgclabs_reorder_manual_send_handler() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $email = sanitize_email( $_POST['email'] ?? '' );
    if ( ! $email ) wp_send_json_error( 'Email required' );

    $hash    = md5( $email );
    $records = (array) get_option( 'sgclabs_reorder_customer_' . $hash, [] );
    if ( empty( $records ) ) wp_send_json_error( 'No order history for this email' );

    $latest = end( $records );
    $result = sgclabs_reorder_send_email( $latest, $hash );

    if ( $result ) {
        // Log it
        $log   = (array) get_option( SGCLABS_REORDER_LOG, [] );
        $log[] = [ 'time' => current_time( 'mysql' ), 'email' => $email, 'name' => $latest['name'], 'products' => count( $latest['products'] ), 'status' => 'manual' ];
        update_option( SGCLABS_REORDER_LOG, array_slice( $log, -200 ) );
        wp_send_json_success( '✅ Email sent to ' . $email );
    } else {
        wp_send_json_error( 'Email sending failed — check wp_mail config' );
    }
}

add_action( 'wp_ajax_sgclabs_reorder_test_email', 'sgclabs_reorder_test_email_handler' );
function sgclabs_reorder_test_email_handler() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $to = sanitize_email( $_POST['test_email'] ?? get_option( 'admin_email' ) );

    // Build fake record
    $fake = [
        'email'    => $to,
        'name'     => 'Test Customer',
        'products' => [
            [ 'id'=>0, 'name'=>'Borosilicate Beaker 250ml', 'qty'=>50, 'price'=>3.20, 'sku'=>'BK250ML', 'interval'=>90, 'image'=>'' ],
            [ 'id'=>0, 'name'=>'Round Bottom Flask 500ml',  'qty'=>24, 'price'=>4.80, 'sku'=>'RBF500ML', 'interval'=>90, 'image'=>'' ],
        ],
        'order_id'    => 'TEST-001',
        'order_date'  => current_time( 'mysql' ),
        'order_value' => 256.00,
        'currency'    => 'USD',
    ];

    $result = sgclabs_reorder_send_email( $fake, md5( $to ) );
    if ( $result ) wp_send_json_success( '✅ Test email sent to ' . $to );
    else wp_send_json_error( 'Failed — check SMTP/wp_mail config' );
}

// ── 7. SAVE SETTINGS ──────────────────────────────────────────
add_action( 'admin_post_sgclabs_save_reorder_settings', 'sgclabs_save_reorder_settings' );
function sgclabs_save_reorder_settings() {
    if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'sgclabs_reorder_settings' ) ) wp_die( 'Unauthorized' );

    update_option( 'sgclabs_reorder_active',       isset( $_POST['reorder_active'] ) ? '1' : '0' );
    update_option( 'sgclabs_reorder_subject',      sanitize_text_field( $_POST['reorder_subject'] ?? '' ) );
    update_option( 'sgclabs_reorder_from_name',    sanitize_text_field( $_POST['reorder_from_name'] ?? '' ) );
    update_option( 'sgclabs_reorder_from_email',   sanitize_email( $_POST['reorder_from_email'] ?? '' ) );
    update_option( 'sgclabs_reorder_btn_text',     sanitize_text_field( $_POST['reorder_btn_text'] ?? '' ) );
    update_option( 'sgclabs_reorder_note',         sanitize_textarea_field( $_POST['reorder_note'] ?? '' ) );
    update_option( 'sgclabs_reorder_accent_color', sanitize_hex_color( $_POST['reorder_accent'] ?? '#1565c0' ) );
    update_option( 'sgclabs_reorder_logo',         esc_url_raw( $_POST['reorder_logo'] ?? '' ) );

    wp_redirect( admin_url( 'admin.php?page=sgclabs-woo-hub&tab=reorder&saved=1' ) );
    exit;
}

// ── 8. ADMIN MENU + PAGE ──────────────────────────────────────
add_action( 'admin_menu', function() {
    add_submenu_page(
        'sgclabs-manager',
        'Reorder Reminders',
        '🔄 Reorder Reminders',
        'manage_options',
        'sgclabs-reorder',
        'sgclabs_reorder_admin_page'
    );
}, 27 );

function sgclabs_reorder_admin_page() {
    global $wpdb;
    $nonce    = wp_create_nonce( 'sgclabs_nonce' );
    $saved    = isset( $_GET['saved'] );
    $index    = (array) get_option( 'sgclabs_reorder_index', [] );
    $log      = (array) get_option( SGCLABS_REORDER_LOG, [] );
    $last_run = get_option( 'sgclabs_reorder_last_run', [] );
    $unsub    = (array) get_option( SGCLABS_REORDER_UNSUB, [] );

    // Stats
    $total_customers = count( $index );
    $total_sent      = count( array_filter( $log, fn($l) => $l['status'] !== 'skipped' ) );
    $unsub_count     = count( $unsub );

    // Next cron
    $next_cron = wp_next_scheduled( 'sgclabs_reorder_daily_check' );
    ?>
    <div class="wrap" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
    <h1 style="color:#1a1a2e;margin-bottom:20px;">🔄 Predictive Reorder Reminder System</h1>

    <?php if ( $saved ): ?>
    <div class="notice notice-success is-dismissible"><p>✅ Settings saved!</p></div>
    <?php endif; ?>

    <!-- Stats Row -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;max-width:760px;margin-bottom:24px;">
        <?php
        $stats = [
            [ 'num' => $total_customers, 'label' => 'Customers Tracked', 'color' => '#1565c0', 'bg' => '#eff6ff', 'border' => '#bfdbfe' ],
            [ 'num' => $total_sent,      'label' => 'Emails Sent',        'color' => '#166534', 'bg' => '#f0fdf4', 'border' => '#86efac' ],
            [ 'num' => $unsub_count,     'label' => 'Unsubscribed',       'color' => '#92400e', 'bg' => '#fffbeb', 'border' => '#fde68a' ],
            [ 'num' => $next_cron ? human_time_diff( $next_cron ) : 'N/A', 'label' => 'Next Auto-Run', 'color' => '#5b21b6', 'bg' => '#faf5ff', 'border' => '#ddd6fe' ],
        ];
        foreach ( $stats as $s ): ?>
        <div style="background:<?php echo $s['bg']; ?>;border:1px solid <?php echo $s['border']; ?>;border-radius:10px;padding:16px;text-align:center;">
            <div style="font-size:26px;font-weight:700;color:<?php echo $s['color']; ?>;"><?php echo $s['num']; ?></div>
            <div style="font-size:11px;color:#6b7280;margin-top:2px;"><?php echo $s['label']; ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:1000px;">
    <div>

    <!-- Settings -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;margin-bottom:20px;">
        <h3 style="margin:0 0 16px;font-size:15px;color:#1a1a2e;">⚙️ Settings</h3>
        <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
            <input type="hidden" name="action" value="sgclabs_save_reorder_settings">
            <?php wp_nonce_field( 'sgclabs_reorder_settings' ); ?>

            <table style="width:100%;font-size:13px;border-collapse:collapse;">
                <tr>
                    <td style="padding:8px 0;font-weight:600;width:140px;color:#374151;">Active</td>
                    <td style="padding:8px 0;"><label><input type="checkbox" name="reorder_active" value="1" <?php checked( get_option( 'sgclabs_reorder_active', '1' ), '1' ); ?>> Enable reorder reminders</label></td>
                </tr>
                <tr>
                    <td style="padding:8px 0;font-weight:600;color:#374151;">Email Subject</td>
                    <td style="padding:8px 0;"><input type="text" name="reorder_subject" value="<?php echo esc_attr( get_option( 'sgclabs_reorder_subject', 'Time to Restock Your Lab Supplies — SGC LABS®' ) ); ?>" style="width:100%;padding:7px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;"></td>
                </tr>
                <tr>
                    <td style="padding:8px 0;font-weight:600;color:#374151;">From Name</td>
                    <td style="padding:8px 0;"><input type="text" name="reorder_from_name" value="<?php echo esc_attr( get_option( 'sgclabs_reorder_from_name', 'SGC LABS® — Laboratory Glassware' ) ); ?>" style="width:100%;padding:7px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;"></td>
                </tr>
                <tr>
                    <td style="padding:8px 0;font-weight:600;color:#374151;">From Email</td>
                    <td style="padding:8px 0;"><input type="email" name="reorder_from_email" value="<?php echo esc_attr( get_option( 'sgclabs_reorder_from_email', get_option( 'admin_email' ) ) ); ?>" style="width:100%;padding:7px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;"></td>
                </tr>
                <tr>
                    <td style="padding:8px 0;font-weight:600;color:#374151;">Accent Color</td>
                    <td style="padding:8px 0;display:flex;gap:8px;align-items:center;">
                        <input type="color" name="reorder_accent" value="<?php echo esc_attr( get_option( 'sgclabs_reorder_accent_color', '#1565c0' ) ); ?>" style="height:36px;width:60px;cursor:pointer;border-radius:6px;">
                        <span style="font-size:11px;color:#9ca3af;">Email header + button color</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 0;font-weight:600;color:#374151;vertical-align:top;">Note in Email</td>
                    <td style="padding:8px 0;"><textarea name="reorder_note" rows="3" style="width:100%;padding:7px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;"><?php echo esc_textarea( get_option( 'sgclabs_reorder_note', 'As a valued SGC LABS customer, you receive priority processing on reorders.' ) ); ?></textarea></td>
                </tr>
            </table>

            <button type="submit" class="button button-primary" style="background:#1565c0;border-color:#1565c0;border-radius:6px;padding:8px 20px;margin-top:8px;">💾 Save Settings</button>
        </form>
    </div>

    <!-- Test Email -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;margin-bottom:20px;">
        <h3 style="margin:0 0 12px;font-size:15px;color:#1a1a2e;">🧪 Test Email</h3>
        <p style="font-size:13px;color:#6b7280;margin:0 0 12px;">Preview kaisi email jaayegi customer ko:</p>
        <div style="display:flex;gap:8px;">
            <input type="email" id="sgc-test-email-addr" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" placeholder="your@email.com" style="flex:1;padding:8px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
            <button class="button" onclick="sgcSendTestEmail()" style="border-radius:6px;padding:8px 16px;font-size:13px;">📧 Send Test</button>
        </div>
        <div id="sgc-test-result" style="font-size:12px;margin-top:8px;display:none;"></div>
    </div>

    <!-- Manual Send -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;">
        <h3 style="margin:0 0 12px;font-size:15px;color:#1a1a2e;">📤 Manual Send</h3>
        <p style="font-size:13px;color:#6b7280;margin:0 0 12px;">Kisi specific customer ko abhi reminder bhejo:</p>
        <div style="display:flex;gap:8px;">
            <input type="email" id="sgc-manual-email" placeholder="customer@email.com" style="flex:1;padding:8px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
            <button class="button button-primary" onclick="sgcManualSend()" style="background:#27ae60;border-color:#27ae60;border-radius:6px;padding:8px 16px;font-size:13px;color:#fff;">📤 Send Now</button>
        </div>
        <div id="sgc-manual-result" style="font-size:12px;margin-top:8px;display:none;"></div>
    </div>

    </div>
    <div>

    <!-- Customer List -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;margin-bottom:20px;">
        <h3 style="margin:0 0 14px;font-size:15px;color:#1a1a2e;">👥 Tracked Customers</h3>
        <?php if ( empty( $index ) ): ?>
            <p style="color:#9ca3af;font-size:13px;">Koi customers tracked nahi — orders complete hone pe automatically track hone lagega.</p>
        <?php else:
            $sorted = $index;
            usort( $sorted, fn($a,$b) => strcmp( $b['last_order'], $a['last_order'] ) );
            $sorted = array_slice( $sorted, 0, 15 );
        ?>
        <div style="max-height:320px;overflow-y:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
                <tr style="background:#f8f9fa;border-bottom:2px solid #e5e7eb;">
                    <th style="padding:8px 10px;text-align:left;font-weight:600;">Customer</th>
                    <th style="padding:8px 10px;text-align:center;font-weight:600;">Last Order</th>
                    <th style="padding:8px 10px;text-align:center;font-weight:600;">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $sorted as $c ):
                $is_unsub = in_array( $c['email'], $unsub, true );
            ?>
            <tr style="border-bottom:1px solid #f3f4f6;">
                <td style="padding:8px 10px;">
                    <div style="font-weight:600;color:#1a1a2e;"><?php echo esc_html( $c['name'] ); ?></div>
                    <div style="font-size:11px;color:#9ca3af;"><?php echo esc_html( $c['email'] ); ?>
                        <?php if ( $is_unsub ): ?><span style="background:#fef2f2;color:#c0392b;padding:1px 6px;border-radius:10px;font-size:9px;font-weight:600;margin-left:4px;">Unsubbed</span><?php endif; ?>
                    </div>
                </td>
                <td style="padding:8px 10px;text-align:center;font-size:11px;color:#6b7280;"><?php echo esc_html( substr( $c['last_order'], 0, 10 ) ); ?></td>
                <td style="padding:8px 10px;text-align:center;">
                    <?php if ( ! $is_unsub ): ?>
                    <button onclick="document.getElementById('sgc-manual-email').value='<?php echo esc_js( $c['email'] ); ?>';sgcManualSend();"
                        style="background:none;border:1px solid #1565c0;color:#1565c0;border-radius:5px;padding:3px 8px;font-size:10px;cursor:pointer;font-weight:600;">
                        Send →
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recent Log -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;">
        <h3 style="margin:0 0 14px;font-size:15px;color:#1a1a2e;">📋 Recent Activity</h3>
        <?php
        $last_run_info = '';
        if ( ! empty( $last_run ) ) {
            $last_run_info = "Last auto-run: <strong>{$last_run['time']}</strong> — {$last_run['sent']} sent, {$last_run['skipped']} skipped";
        }
        if ( $last_run_info ) echo "<p style='font-size:12px;color:#6b7280;margin:0 0 12px;'>{$last_run_info}</p>";
        ?>
        <?php if ( empty( $log ) ): ?>
            <p style="color:#9ca3af;font-size:13px;">No activity yet.</p>
        <?php else: ?>
        <div style="max-height:240px;overflow-y:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead><tr style="background:#f8f9fa;border-bottom:2px solid #e5e7eb;">
                <th style="padding:7px 10px;text-align:left;font-weight:600;">Time</th>
                <th style="padding:7px 10px;text-align:left;font-weight:600;">Customer</th>
                <th style="padding:7px 10px;text-align:center;font-weight:600;">Status</th>
            </tr></thead>
            <tbody>
            <?php foreach ( array_reverse( array_slice( $log, -20 ) ) as $l ): ?>
            <tr style="border-bottom:1px solid #f3f4f6;">
                <td style="padding:7px 10px;color:#9ca3af;"><?php echo esc_html( substr( $l['time'], 5, 11 ) ); ?></td>
                <td style="padding:7px 10px;">
                    <div style="font-weight:500;"><?php echo esc_html( $l['name'] ); ?></div>
                    <div style="color:#9ca3af;font-size:10px;"><?php echo esc_html( $l['email'] ); ?></div>
                </td>
                <td style="padding:7px 10px;text-align:center;">
                    <?php if ( $l['status'] === 'sent' ): ?>
                        <span style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:600;">✓ Sent</span>
                    <?php elseif ( $l['status'] === 'manual' ): ?>
                        <span style="background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:600;">Manual</span>
                    <?php else: ?>
                        <span style="background:#f1f5f9;color:#6b7280;padding:2px 8px;border-radius:20px;font-size:10px;">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    </div>
    </div>
    </div>

    <script>
    var sgcReorderNonce = '<?php echo $nonce; ?>';

    function sgcSendTestEmail() {
        var email  = document.getElementById('sgc-test-email-addr').value;
        var result = document.getElementById('sgc-test-result');
        result.style.display = 'block';
        result.style.color = '#6b7280';
        result.textContent = '⏳ Sending test email...';
        jQuery.post(ajaxurl, {
            action: 'sgclabs_reorder_test_email',
            nonce: sgcReorderNonce,
            test_email: email,
        }, function(res) {
            result.style.color = res.success ? '#166534' : '#c0392b';
            result.textContent = res.success ? res.data : '❌ ' + res.data;
        }).fail(function() {
            result.style.color = '#c0392b';
            result.textContent = '❌ Request failed';
        });
    }

    function sgcManualSend() {
        var email  = document.getElementById('sgc-manual-email').value;
        var result = document.getElementById('sgc-manual-result');
        if (!email) { alert('Email address daalo'); return; }
        result.style.display = 'block';
        result.style.color = '#6b7280';
        result.textContent = '⏳ Sending...';
        jQuery.post(ajaxurl, {
            action: 'sgclabs_reorder_manual_send',
            nonce: sgcReorderNonce,
            email: email,
        }, function(res) {
            result.style.color = res.success ? '#166534' : '#c0392b';
            result.textContent = res.success ? res.data : '❌ ' + res.data;
        }).fail(function() {
            result.style.color = '#c0392b';
            result.textContent = '❌ Request failed';
        });
    }
    </script>
    <?php
}
