<?php
defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════════
// PIPELINE
// ═══════════════════════════════════════════════════════════
class SGC_IIP_Pipeline {

    const STAGES = [
        'new'      => [ 'label' => 'New Inquiry',  'color' => '#E3F2FD', 'text' => '#0D47A1', 'icon' => '🔵' ],
        'replied'  => [ 'label' => 'Replied',       'color' => '#F3E5F5', 'text' => '#4A148C', 'icon' => '💬' ],
        'pi_sent'  => [ 'label' => 'PI Sent',       'color' => '#FFF3E0', 'text' => '#E65100', 'icon' => '📄' ],
        'accepted' => [ 'label' => 'PI Accepted',   'color' => '#E8F5E9', 'text' => '#1B5E20', 'icon' => '✅' ],
        'advance'  => [ 'label' => 'Advance Rcvd',  'color' => '#E0F7FA', 'text' => '#006064', 'icon' => '💰' ],
        'won'      => [ 'label' => 'Won / Closed',  'color' => '#E8F5E9', 'text' => '#1B5E20', 'icon' => '🏆' ],
        'lost'     => [ 'label' => 'Lost',          'color' => '#FFEBEE', 'text' => '#B71C1C', 'icon' => '❌' ],
        'sample'   => [ 'label' => 'Sample Sent',   'color' => '#FFF9C4', 'text' => '#F57F17', 'icon' => '📦' ],
    ];

    public static function register_ajax() {
        add_action( 'wp_ajax_sgc_iip_get_pipeline', [ __CLASS__, 'ajax_get_pipeline' ] );
    }

    public static function ajax_get_pipeline() {
        check_ajax_referer( 'sgc_iip_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        global $wpdb;
        $table = SGC_IIP_DB::t('inquiries');
        $data  = [];
        foreach ( array_keys( self::STAGES ) as $stage ) {
            $rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
                "SELECT id, name, company, country, pi_number, health_score, currency, buyer_type, created_at
                 FROM `$table` WHERE status=%s ORDER BY created_at DESC LIMIT 30",
                $stage
            ), ARRAY_A );
            // Add human-readable "created_ago" for each card
            foreach ( $rows as &$r ) {
                $diff = time() - strtotime( $r['created_at'] );
                if ( $diff < 86400 )       $r['created_ago'] = round($diff/3600).'h ago';
                elseif ( $diff < 2592000 ) $r['created_ago'] = round($diff/86400).'d ago';
                else                       $r['created_ago'] = round($diff/2592000).'mo ago';
            }
            unset($r);
            $data[ $stage ] = $rows;
        }
        wp_send_json_success( [ 'stages' => self::STAGES, 'data' => $data ] );
    }
}

SGC_IIP_Pipeline::register_ajax();


// ═══════════════════════════════════════════════════════════
// FOLLOW-UP AUTOMATION
// ═══════════════════════════════════════════════════════════
class SGC_IIP_Followup {

    public static function register_ajax() {
        $map = [
            'sgc_iip_fu_save_rule'          => 'ajax_save_rule',
            'sgc_iip_fu_delete_rule'        => 'ajax_delete_rule',
            'sgc_iip_fu_toggle_rule'        => 'ajax_toggle_rule',
            'sgc_iip_fu_get_rules'          => 'ajax_get_rules',
            // v1.3.0: Balance reminder
            'sgc_iip_manual_balance_remind' => 'ajax_manual_remind',
            'sgc_iip_save_followup_settings'=> 'ajax_save_settings',
        ];
        foreach ( $map as $action => $method ) {
            add_action( 'wp_ajax_' . $action, [ __CLASS__, $method ] );
        }
    }

    private static function verify() {
        check_ajax_referer( 'sgc_iip_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );
    }

    // ── Cron: run pending follow-ups ──────────────────────────────────────
    public static function run_pending() {
        if ( get_option( 'sgc_iip_followup_enabled', '1' ) !== '1' ) return;

        global $wpdb;
        $rules = $wpdb->get_results( "SELECT * FROM " . SGC_IIP_DB::t('followup_rules') . " WHERE status='active'", ARRAY_A ); // phpcs:ignore
        if ( ! $rules ) return;

        foreach ( $rules as $rule ) {
            $target_date = date( 'Y-m-d', strtotime( '-' . (int) $rule['delay_days'] . ' days' ) );
            $trigger     = $rule['trigger_status'];

            // Find inquiries that match trigger status and were updated on target_date
            $inquiries = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
                "SELECT * FROM " . SGC_IIP_DB::t('inquiries') . "
                 WHERE status=%s
                   AND DATE(updated_at) = %s
                   AND (last_followup IS NULL OR DATE(last_followup) < %s)
                 LIMIT 50",
                $trigger, $target_date, date('Y-m-d')
            ), ARRAY_A );

            foreach ( $inquiries as $inq ) {
                self::send_followup( $inq, $rule );
            }
        }
    }

    private static function send_followup( array $inq, array $rule ) {
        global $wpdb;

        $message = self::replace_vars( $rule['message'], $inq );
        $sent    = false;

        if ( in_array( $rule['channel'], [ 'email', 'both' ], true ) && $inq['email'] ) {
            $subject = self::replace_vars( $rule['subject'] ?? 'Follow-up from SGC LABS®', $inq );
            $html    = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#333;line-height:1.7">' . nl2br( esc_html( $message ) ) . '</div>';
            $sent    = wp_mail( $inq['email'], $subject, $html, [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . get_option('sgc_iip_company_name','SGC LABS®') . ' <' . get_option('sgc_iip_company_email', get_option('admin_email')) . '>',
            ] );
        }

        if ( in_array( $rule['channel'], [ 'whatsapp', 'both' ], true ) && $inq['phone'] ) {
            SGC_IIP_WhatsApp::send_followup( $inq, $message );
            $sent = true;
        }

        if ( $sent ) {
            $wpdb->update( SGC_IIP_DB::t('inquiries'), [ // phpcs:ignore
                'last_followup'  => current_time('mysql'),
                'followup_count' => (int) $inq['followup_count'] + 1,
            ], [ 'id' => $inq['id'] ] );

            SGC_IIP_DB::log( $inq['id'], 'followup_sent', 'Follow-up: ' . $rule['name'], 'system' );
        }
    }

    private static function replace_vars( string $text, array $inq ) {
        global $wpdb;
        $pi_num  = $inq['pi_number'] ?? '';
        $pi_date = '';
        $valid   = '';
        if ( $pi_num ) {
            $pi = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . SGC_IIP_DB::t('proformas') . " WHERE pi_number=%s LIMIT 1", $pi_num ), ARRAY_A ); // phpcs:ignore
            if ( $pi ) {
                $pi_date = date_i18n( 'F j, Y', strtotime( $pi['created_at'] ) );
                $valid   = date_i18n( 'F j, Y', strtotime( $pi['valid_until'] ) );
            }
        }
        $products = json_decode( $inq['product_details'] ?? '[]', true );
        $product  = $products[0]['name'] ?? 'your inquiry';

        return str_replace(
            [ '{name}', '{company}', '{country}', '{product}', '{pi_number}', '{pi_date}', '{valid_until}' ],
            [ $inq['name'], $inq['company'], $inq['country'], $product, $pi_num, $pi_date, $valid ],
            $text
        );
    }

    public static function ajax_get_rules() {
        self::verify();
        global $wpdb;
        $rules = $wpdb->get_results( "SELECT * FROM " . SGC_IIP_DB::t('followup_rules') . " ORDER BY delay_days ASC", ARRAY_A ); // phpcs:ignore
        wp_send_json_success( $rules );
    }

    public static function ajax_save_rule() {
        self::verify();
        global $wpdb;
        $id    = (int) ( $_POST['rule_id'] ?? 0 );
        $data  = [
            'name'           => sanitize_text_field( wp_unslash( $_POST['name']     ?? '' ) ),
            'trigger_status' => sanitize_key( $_POST['trigger_status']               ?? 'pi_sent' ),
            'delay_days'     => (int) ( $_POST['delay_days']                         ?? 3 ),
            'channel'        => sanitize_key( $_POST['channel']                      ?? 'email' ),
            'subject'        => sanitize_text_field( wp_unslash( $_POST['subject']  ?? '' ) ),
            'message'        => sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ),
        ];
        if ( $id ) {
            $wpdb->update( SGC_IIP_DB::t('followup_rules'), $data, [ 'id' => $id ] ); // phpcs:ignore
        } else {
            $data['status'] = 'active';
            $wpdb->insert( SGC_IIP_DB::t('followup_rules'), $data ); // phpcs:ignore
            $id = $wpdb->insert_id;
        }
        wp_send_json_success( [ 'id' => $id ] );
    }

    public static function ajax_delete_rule() {
        self::verify();
        global $wpdb;
        $wpdb->delete( SGC_IIP_DB::t('followup_rules'), [ 'id' => (int)($_POST['rule_id']??0) ] ); // phpcs:ignore
        wp_send_json_success();
    }

    public static function ajax_toggle_rule() {
        self::verify();
        global $wpdb;
        $id     = (int)($_POST['rule_id']??0);
        $status = sanitize_key( $_POST['status'] ?? 'active' );
        $wpdb->update( SGC_IIP_DB::t('followup_rules'), [ 'status' => $status ], [ 'id' => $id ] ); // phpcs:ignore
        wp_send_json_success();
    }

    // ═══════════════════════════════════════════════════════════
    // v1.3.0: BALANCE PAYMENT AUTO-REMINDER
    // ═══════════════════════════════════════════════════════════

    public static function run_balance_reminder_check() {
        if ( get_option('sgc_iip_balance_reminder_active','1') !== '1' ) return;

        global $wpdb;
        $days = max(1, intval(get_option('sgc_iip_balance_reminder_days',3)));
        $it   = SGC_IIP_DB::t('inquiries');
        $pt   = SGC_IIP_DB::t('proformas');
        $dt   = $wpdb->prefix.'sgc_iip_dispatches';
        $has_dt = $wpdb->get_var("SHOW TABLES LIKE '$dt'") === $dt;

        if ( $has_dt ) {
            $target = date('Y-m-d', strtotime("+{$days} days"));
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT i.*, d.expected_date, p.pi_number, p.grand_total, p.currency
                 FROM `$it` i
                 LEFT JOIN `$dt` d ON d.inquiry_id=i.id
                 LEFT JOIN `$pt` p ON p.inquiry_id=i.id
                 WHERE i.status='advance'
                   AND DATE(d.expected_date)=%s
                   AND (i.balance_reminder_sent IS NULL OR i.balance_reminder_sent=0)
                 ORDER BY i.id ASC LIMIT 100", $target), ARRAY_A);
        } else {
            $rows = $wpdb->get_results(
                "SELECT i.*, p.pi_number, p.grand_total, p.currency
                 FROM `$it` i LEFT JOIN `$pt` p ON p.inquiry_id=i.id
                 WHERE i.status='advance'
                   AND (i.balance_reminder_sent IS NULL OR i.balance_reminder_sent=0)
                 ORDER BY i.id ASC LIMIT 50", ARRAY_A);
        }

        foreach ((array)$rows as $row) self::send_balance_reminder($row);
    }

    public static function send_balance_reminder( array $inq ): bool {
        $email = sanitize_email($inq['email']??'');
        if (!is_email($email)) return false;

        global $wpdb;
        $it       = SGC_IIP_DB::t('inquiries');
        $name     = sanitize_text_field($inq['name']??'Customer');
        $pi_num   = sanitize_text_field($inq['pi_number']??'');
        $co       = get_option('sgc_iip_company_name','SGC LABS®');
        $from_em  = get_option('sgc_iip_company_email','sales@labsglass.com');
        $currency = sanitize_text_field($inq['currency']??'USD');
        $csym     = ['USD'=>'$','EUR'=>'€','GBP'=>'£','INR'=>'₹','AED'=>'AED '][$currency]??$currency.' ';
        $grand    = floatval($inq['grand_total']??0);
        $payments = SGC_IIP_DB::get_payments(intval($inq['id']));
        $paid     = array_sum(array_column($payments,'amount'));
        $balance  = round($grand - $paid, 2);

        if ($balance <= 0) {
            $wpdb->update($it,['balance_reminder_sent'=>1],['id'=>$inq['id']]);
            return false;
        }

        $edd      = !empty($inq['expected_date']) ? date('d M Y',strtotime($inq['expected_date'])) : '';
        $token    = $inq['pi_accept_token']??'';
        $portal   = $token ? home_url('/sgc-pi-portal/?sgc_token='.urlencode($token)) : '';
        $subject  = get_option('sgc_iip_balance_reminder_subject',"Balance Payment Reminder — {$pi_num} | {$co}");
        $subject  = str_replace(['{pi_number}','{name}','{company}'],[$pi_num,$name,$co],$subject);

        $r1 = esc_html($csym.number_format($grand,2).' '.$currency);
        $r2 = esc_html($csym.number_format($paid,2));
        $r3 = esc_html($csym.number_format($balance,2).' '.$currency);
        $edd_html   = $edd ? "<p style='font-size:13px;color:#555'>📅 <strong>Expected dispatch:</strong> {$edd}</p>" : '';
        $portal_html= $portal ? "<div style='text-align:center;margin:24px 0'><a href='".esc_url($portal)."' style='background:#1a1a2e;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;display:inline-block'>💰 Upload Payment Proof</a></div>" : '';

        $body = "<p>Dear <strong>{$name}</strong>,</p>
            <p>This is a friendly reminder that the <strong>balance payment</strong> for PI <strong style='font-family:monospace'>{$pi_num}</strong> is now due.</p>
            <table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:14px'>
              <tr style='background:#f8f9fa'><td style='padding:10px 14px;border:1px solid #e5e7eb;color:#555'>Order Total</td><td style='padding:10px 14px;border:1px solid #e5e7eb;font-weight:600'>{$r1}</td></tr>
              <tr><td style='padding:10px 14px;border:1px solid #e5e7eb;color:#555'>Advance Received</td><td style='padding:10px 14px;border:1px solid #e5e7eb;color:#059669;font-weight:600'>{$r2}</td></tr>
              <tr style='background:#fff8e1'><td style='padding:10px 14px;border:2px solid #fcd34d;font-weight:700'>Balance Due</td><td style='padding:10px 14px;border:2px solid #fcd34d;font-size:18px;font-weight:800;color:#d97706'>{$r3}</td></tr>
            </table>
            {$edd_html}
            <p style='font-size:13px;color:#555'>Kindly arrange the balance at the earliest to ensure on-time dispatch.</p>
            {$portal_html}
            <p style='font-size:13px;color:#888'>If you have already paid, please ignore this or reply with your transaction reference.</p>";

        $html = SGC_IIP_Email::wrap($body, $subject);
        $sent = wp_mail($email,$subject,$html,[
            'Content-Type: text/html; charset=UTF-8',
            'From: '.$co.' <'.$from_em.'>',
            'Bcc: '.$from_em,
        ]);

        if ($sent) {
            $wpdb->update($it,['balance_reminder_sent'=>1,'balance_reminder_at'=>current_time('mysql')],['id'=>$inq['id']]);
            SGC_IIP_DB::log(intval($inq['id']),'balance_reminder_sent',"Balance reminder → {$email}. Balance: {$csym}".number_format($balance,2),'system');
        }
        return $sent;
    }

    public static function ajax_manual_remind() {
        check_ajax_referer('sgc_iip_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        $id = intval($_POST['inq_id']??0);
        if (!$id) wp_send_json_error('Invalid ID');
        global $wpdb;
        $it = SGC_IIP_DB::t('inquiries'); $pt = SGC_IIP_DB::t('proformas');
        $inq = $wpdb->get_row($wpdb->prepare(
            "SELECT i.*, p.pi_number, p.grand_total, p.currency FROM `$it` i LEFT JOIN `$pt` p ON p.inquiry_id=i.id WHERE i.id=%d ORDER BY p.id DESC LIMIT 1",$id),ARRAY_A);
        if (!$inq) { wp_send_json_error('Not found'); return; }
        $inq['balance_reminder_sent'] = 0;
        self::send_balance_reminder($inq)
            ? wp_send_json_success('✅ Balance reminder sent.')
            : wp_send_json_error('Email failed. Check SMTP.');
    }

    public static function ajax_save_settings() {
        check_ajax_referer('sgc_iip_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        update_option('sgc_iip_balance_reminder_active', isset($_POST['active'])?'1':'0');
        update_option('sgc_iip_balance_reminder_days',   max(1,intval($_POST['days']??3)));
        update_option('sgc_iip_balance_reminder_subject',sanitize_text_field(wp_unslash($_POST['subject']??'')));
        update_option('sgc_iip_balance_reminder_body',   sanitize_textarea_field(wp_unslash($_POST['body']??'')));
        wp_send_json_success('Settings saved.');
    }
}

SGC_IIP_Followup::register_ajax();


// ═══════════════════════════════════════════════════════════
// CRM BRIDGE — Won inquiry → SGC Smart CRM Pro
// ═══════════════════════════════════════════════════════════
class SGC_IIP_CRM_Bridge {

    public static function register_ajax() {
        add_action( 'wp_ajax_sgc_iip_send_to_crm', [ __CLASS__, 'ajax_send_to_crm' ] );
    }

    public static function ajax_send_to_crm() {
        check_ajax_referer( 'sgc_iip_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        global $wpdb;
        $id  = (int) ( $_POST['inquiry_id'] ?? 0 );
        $inq = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . SGC_IIP_DB::t('inquiries') . " WHERE id=%d", $id ), ARRAY_A ); // phpcs:ignore
        if ( ! $inq ) { wp_send_json_error( 'Not found.' ); return; }

        // Check CRM Pro table exists
        $crm_table = $wpdb->prefix . 'sgc_crm_contacts';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$crm_table'" ) !== $crm_table ) { // phpcs:ignore
            wp_send_json_error( 'SGC Smart CRM Pro not installed or active.' );
            return;
        }

        // Check if already in CRM
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `$crm_table` WHERE email=%s LIMIT 1", $inq['email'] ) ); // phpcs:ignore
        if ( $existing ) {
            wp_send_json_success( [ 'message' => 'Buyer already in CRM. CRM ID: #' . $existing, 'crm_id' => $existing ] );
            return;
        }

        $products = json_decode( $inq['product_details'] ?? '[]', true );
        $prod_names = implode( ', ', array_filter( array_column( (array)$products, 'name' ) ) );
        $notes    = 'Imported from Invoice Pro. PI: ' . ( $inq['pi_number'] ?? '—' )
                  . ' | Products: ' . ( $prod_names ?: '—' )
                  . ' | Inquiry ID: #' . $inq['id'];

        // ── Map buyer_type → CRM segment (exact CRM Pro values) ──
        $buyer_type_map = [
            'oem'         => 'dealer',          // OEM → treated as dealer
            'bulk'        => 'dealer',
            'dealer'      => 'dealer',
            'institution' => 'end_user',
            'retail'      => 'end_user',
            'sample'      => 'new_lead',
            'unknown'     => 'new_lead',
        ];
        // Domestic vs International segment
        $country_lower = strtolower( trim( $inq['country'] ?? '' ) );
        $is_domestic   = in_array( $country_lower, ['india','in','bharat'], true );
        $base_segment  = $buyer_type_map[ $inq['buyer_type'] ?? 'unknown' ] ?? 'new_lead';
        $segment       = $is_domestic ? 'domestic' : 'international';
        if ( $base_segment === 'dealer' ) $segment = 'dealer'; // dealer overrides

        // ── Split full name into first/last (CRM Pro uses separate fields) ──
        $name_parts = explode( ' ', trim( $inq['name'] ?? '' ), 2 );
        $first_name = $name_parts[0] ?? '';
        $last_name  = $name_parts[1] ?? '';

        // ── Build tags ──
        $tags = implode( ',', array_filter( [
            $inq['country'] ?? '',
            $inq['buyer_type'] ?? '',
            'invoice_pro',
            $is_domestic ? 'domestic' : 'export',
        ] ) );

        $wpdb->insert( $crm_table, [ // phpcs:ignore
            'email'      => $inq['email'],
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'company'    => $inq['company']  ?? '',
            'phone'      => $inq['phone']    ?? '',
            'country'    => $inq['country']  ?? '',
            'city'       => '',
            'tags'       => $tags,
            'segment'    => $segment,
            'source'     => 'invoice_pro',    // CRM Pro source tracking
            'status'     => 'subscribed',
            'ai_score'   => min( 100, (int)($inq['health_score'] ?? 50) ),
            'notes'      => $notes,
        ] );
        $crm_id = $wpdb->insert_id;

        SGC_IIP_DB::log( $id, 'crm_added', 'Buyer added to SGC CRM Pro. CRM ID: #' . $crm_id, 'admin' );
        wp_send_json_success( [ 'message' => 'Added to CRM Pro! Contact ID: #' . $crm_id, 'crm_id' => $crm_id ] );
    }
}

SGC_IIP_CRM_Bridge::register_ajax();
