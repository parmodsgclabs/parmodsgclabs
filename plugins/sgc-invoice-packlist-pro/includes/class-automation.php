<?php
/**
 * SGC IIP — Full Automation System v1.0.0
 *
 * Three automations:
 *
 *  1. AUTO PI DRAFT  — Jab bhi naya inquiry aaye, AI background mein
 *                      PI draft taiyaar kar deta hai. Tum sirf review karo.
 *
 *  2. WHATSAPP ALERT — Tumhare phone pe turant alert:
 *                      "New inquiry: Buyer, Product, Est. Value"
 *
 *  3. SMART FOLLOW-UP — PI send ke 3/7/14 din baad auto follow-up email+WA.
 *                        Default rules auto-install. Fully customizable.
 *
 * INSTALL: sgc-invoice-packlist-pro/includes/class-automation.php
 *
 * @package SGC_IIP
 * @version 1.0.0 | Build: 20260516-1800
 */

defined( 'ABSPATH' ) || exit;

class SGC_IIP_Automation {

    // ── Options ───────────────────────────────────────────────────────────
    const OPT_AUTO_DRAFT   = 'sgc_iip_auto_pi_draft';     // '1' or '0'
    const OPT_WA_ALERT     = 'sgc_iip_wa_admin_alert';    // '1' or '0'
    const OPT_WA_MY_PHONE  = 'sgc_iip_wa_my_phone';       // your WA number
    const OPT_FOLLOWUP_ON  = 'sgc_iip_auto_followup';     // '1' or '0'


    // ╔════════════════════════════════════════════════════════════════════╗
    // ║  SECTION 1 — HOOKS                                                ║
    // ╚════════════════════════════════════════════════════════════════════╝

    public static function init(): void {

        // Hook into inquiry processed event
        add_action( 'sgc_iip_inquiry_processed', [ __CLASS__, 'on_new_inquiry' ], 20, 2 );

        // Follow-up cron (already scheduled hourly by sgc-iip.php)
        add_action( 'sgc_iip_followup_cron', [ __CLASS__, 'run_smart_followup' ], 5 );

        // Install default follow-up rules on first run
        add_action( 'init', [ __CLASS__, 'install_default_rules' ] );

        // AJAX handlers
        add_action( 'wp_ajax_sgc_auto_save_settings', [ __CLASS__, 'ajax_save_settings' ] );
        add_action( 'wp_ajax_sgc_auto_test_wa_alert', [ __CLASS__, 'ajax_test_wa_alert' ] );
        add_action( 'wp_ajax_sgc_auto_manual_draft',  [ __CLASS__, 'ajax_manual_draft' ] );
    }


    // ╔════════════════════════════════════════════════════════════════════╗
    // ║  SECTION 2 — ON NEW INQUIRY                                       ║
    // ╚════════════════════════════════════════════════════════════════════╝

    /**
     * Called every time a new inquiry is processed.
     * Runs: Auto PI Draft + WhatsApp Admin Alert
     */
    public static function on_new_inquiry( int $inquiry_id, array $inquiry ): void {

        // ── v1.8.5: AI Classifier — runs first, routes inquiry automatically ─
        self::run_ai_classifier( $inquiry_id, $inquiry );

        // ── Automation 1: WhatsApp Alert to YOU ──────────────────────────
        if ( get_option( self::OPT_WA_ALERT, '1' ) === '1' ) {
            self::send_admin_wa_alert( $inquiry_id, $inquiry );
        }

        // ── Automation 2: AI PI Draft (background via cron) ──────────────
        if ( get_option( self::OPT_AUTO_DRAFT, '1' ) === '1' ) {
            wp_schedule_single_event(
                time() + 60,
                'sgc_iip_bg_auto_draft',
                [ $inquiry_id ]
            );
        }
    }

    // ── v1.8.5: AI Classifier — auto-route + spam filter + urgency flag ─────
    public static function run_ai_classifier( int $inquiry_id, array $inquiry ): void {
        if ( ! get_option( 'sgc_iip_ai_classifier_enabled', '1' ) ) return;

        $result = SGC_IIP_AI::classify_inquiry( $inquiry );
        if ( ! is_array( $result ) ) return;

        global $wpdb;
        $tbl = SGC_IIP_DB::t( 'inquiries' );

        $update = [
            'buyer_type'    => $result['buyer_type'],
            'ai_classified' => 1,
            'urgency_score' => $result['urgency'],
            'spam_score'    => $result['spam_score'],
            'ai_notes'      => wp_json_encode( $result ),
        ];

        // Auto-route: spam → mark hold; domestic → set INR currency
        if ( $result['spam_score'] >= 75 ) {
            $update['status'] = 'hold';
            SGC_IIP_DB::log( $inquiry_id, 'ai_spam', 'AI spam filter: score ' . $result['spam_score'] . '. ' . $result['reason'], 'system' );
        } elseif ( $result['market'] === 'domestic' ) {
            // Domestic inquiry: ensure currency is INR
            $update['currency'] = 'INR';
            SGC_IIP_DB::log( $inquiry_id, 'ai_classify', 'AI: Domestic inquiry. ' . $result['reason'], 'system' );
        } else {
            SGC_IIP_DB::log( $inquiry_id, 'ai_classify', 'AI: Export/' . $result['buyer_type'] . '. Urgency ' . $result['urgency'] . '/5. ' . $result['reason'], 'system' );
        }

        // Urgency ≥ 4 — add priority tag to notes
        if ( $result['urgency'] >= 4 && ( $result['spam_score'] ?? 0 ) < 50 ) {
            $existing_notes = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore
                "SELECT notes FROM `{$tbl}` WHERE id=%d", $inquiry_id
            ) );
            $update['notes'] = trim( '🔥 URGENT — ' . ( $existing_notes ?: '' ) );
            SGC_IIP_DB::log( $inquiry_id, 'ai_urgency', 'AI flagged as urgent (score ' . $result['urgency'] . '/5)', 'system' );
        }

        $wpdb->update( $tbl, $update, [ 'id' => $inquiry_id ] ); // phpcs:ignore
    }

    // Register background draft hook
    // (called once during init so it's always registered)
    public static function register_bg_hooks(): void {
        add_action( 'sgc_iip_bg_auto_draft', [ __CLASS__, 'run_auto_draft' ] );
    }


    // ╔════════════════════════════════════════════════════════════════════╗
    // ║  SECTION 3 — AUTOMATION 1: WHATSAPP ADMIN ALERT                  ║
    // ╚════════════════════════════════════════════════════════════════════╝

    public static function send_admin_wa_alert( int $inquiry_id, array $inquiry ): void {

        $my_phone = get_option( self::OPT_WA_MY_PHONE, '' );
        if ( empty( $my_phone ) ) return;

        // Build alert message
        $name     = $inquiry['name']    ?? 'Unknown';
        $country  = $inquiry['country'] ?? '—';
        $email    = $inquiry['email']   ?? '—';
        $product  = '—';
        if ( ! empty( $inquiry['product_details'] ) ) {
            $prods   = json_decode( $inquiry['product_details'], true ) ?: [];
            $product = implode( ', ', array_map(
                fn($p) => trim( ($p['name']??'') . ' ' . ($p['qty']??'') ),
                $prods
            ) );
        }
        $score    = $inquiry['health_score'] ?? 0;
        $pi_link  = admin_url( 'admin.php?page=sgc-iip&tab=proforma&inquiry_id=' . $inquiry_id );

        $message = "🔔 *New Inquiry #" . $inquiry_id . "* — SGC LABS®\n\n"
            . "👤 *Buyer:* " . $name . " (" . $country . ")\n"
            . "📧 " . $email . "\n"
            . "📦 *Product:* " . $product . "\n"
            . "💯 *Health Score:* " . $score . "/100\n\n"
            . "➡️ Create PI: " . $pi_link;

        SGC_IIP_WhatsApp::send(
            $my_phone,
            'text',
            [ 'text' => [ 'body' => $message, 'preview_url' => false ] ]
        );
    }


    // ╔════════════════════════════════════════════════════════════════════╗
    // ║  SECTION 4 — AUTOMATION 2: AUTO AI PI DRAFT                      ║
    // ╚════════════════════════════════════════════════════════════════════╝

    public static function run_auto_draft( int $inquiry_id ): void {
        global $wpdb;

        $inq = $wpdb->get_row( // phpcs:ignore
            $wpdb->prepare( "SELECT * FROM " . SGC_IIP_DB::t('inquiries') . " WHERE id=%d", $inquiry_id ),
            ARRAY_A
        );
        if ( ! $inq ) return;

        // Skip if AI draft already exists
        if ( ! empty( $inq['ai_draft'] ) ) return;

        $products  = json_decode( $inq['product_details'] ?? '[]', true ) ?: [];
        $currency  = $inq['currency'] ?? 'USD';
        $usd_rate  = floatval( get_option( 'sgc_iip_usd_rate', 83.50 ) );

        // Fetch WC product prices for reference
        $wc_info = '';
        foreach ( $products as $p ) {
            if ( empty( $p['name'] ) ) continue;
            $wc_q = new WP_Query( [
                'post_type'      => 'product',
                's'              => $p['name'],
                'posts_per_page' => 3,
                'fields'         => 'ids',
            ] );
            foreach ( $wc_q->posts as $pid ) {
                $wcp = wc_get_product( $pid );
                if ( $wcp ) {
                    $wc_info .= "\nProduct: " . $wcp->get_name()
                        . " | SKU: " . $wcp->get_sku()
                        . " | INR: " . $wcp->get_regular_price()
                        . " | USD: $" . round( floatval( $wcp->get_regular_price() ) / $usd_rate, 2 );
                }
            }
        }

        $qty_list = implode( ', ', array_map(
            fn($p) => ($p['name']??'') . '×' . ($p['qty']??'?'),
            $products
        ) );

        $system = 'You are a B2B export pricing expert for SGC LABS®, a laboratory glassware manufacturer in Ambala, India. Generate accurate Proforma Invoice line items. Return ONLY valid JSON, no markdown.';

        $prompt = "Auto-draft Proforma Invoice for new inquiry:\n"
            . "Buyer: {$inq['name']} from {$inq['country']}\n"
            . "Products: $qty_list\n"
            . "Currency: $currency | Incoterm: {$inq['incoterm']}\n"
            . "Reference prices from WooCommerce: $wc_info\n\n"
            . "Return ONLY this JSON:\n"
            . '{"line_items":[{"product_name":"...","sku":"...","hs_code":"7017.20","qty":0,"unit_price":0.00,"packaging_per_unit":0.10,"oem_charge":0.00,"total":0.00}],"subtotal":0.00,"packaging_total":0.00,"oem_total":0.00,"grand_total":0.00,"payment_terms":"30% advance, 70% before shipment","delivery_days":"15-20 working days","notes":"...","ai_remarks":"brief pricing rationale"}';

        $result = SGC_IIP_AI::call( $prompt, $system, 1500 );
        if ( is_wp_error( $result ) ) {
            error_log( 'SGC Auto Draft failed for inquiry #' . $inquiry_id . ': ' . $result->get_error_message() );
            return;
        }

        $clean  = preg_replace( '/```(?:json)?|```/', '', $result );
        $parsed = json_decode( trim( $clean ), true );
        if ( ! $parsed ) {
            error_log( 'SGC Auto Draft: JSON parse failed for #' . $inquiry_id );
            return;
        }

        // Save draft
        $wpdb->update( // phpcs:ignore
            SGC_IIP_DB::t('inquiries'),
            [ 'ai_draft' => wp_json_encode( $parsed ) ],
            [ 'id' => $inquiry_id ]
        );

        SGC_IIP_DB::log( $inquiry_id, 'auto_draft_ready', 'AI auto-drafted PI — ready for review', 'system' );

        // WA alert: draft ready
        $my_phone = get_option( self::OPT_WA_MY_PHONE, '' );
        if ( $my_phone && get_option( self::OPT_WA_ALERT, '1' ) === '1' ) {
            $grand = $parsed['grand_total'] ?? 0;
            $msg   = "✅ *AI PI Draft Ready* — Inquiry #{$inquiry_id}\n"
                   . "Buyer: " . ($inq['name']??'') . "\n"
                   . "Est. Value: " . number_format( $grand, 2 ) . " {$currency}\n"
                   . "Items: " . count( $parsed['line_items'] ?? [] ) . " line items\n\n"
                   . "👉 Review & Send: " . admin_url( 'admin.php?page=sgc-iip&tab=proforma&inquiry_id=' . $inquiry_id );

            SGC_IIP_WhatsApp::send(
                $my_phone,
                'text',
                [ 'text' => [ 'body' => $msg, 'preview_url' => false ] ]
            );
        }
    }


    // ╔════════════════════════════════════════════════════════════════════╗
    // ║  SECTION 5 — AUTOMATION 3: SMART FOLLOW-UP                       ║
    // ╚════════════════════════════════════════════════════════════════════╝

    /**
     * Smart follow-up — runs hourly via cron.
     * Sends follow-up to inquiries where PI was sent but no response.
     */
    public static function run_smart_followup(): void {
        if ( get_option( self::OPT_FOLLOWUP_ON, '1' ) !== '1' ) return;

        global $wpdb;
        $t = SGC_IIP_DB::t('inquiries');

        // Get inquiries where:
        // - Status is 'pi_sent' (PI was sent)
        // - Not yet accepted/won/lost
        // - followup_count < 3 (max 3 follow-ups)
        $inquiries = $wpdb->get_results( // phpcs:ignore
            "SELECT * FROM `{$t}`
             WHERE status IN ('pi_sent','new')
             AND pipeline_stage NOT IN ('won','lost','accepted')
             AND followup_count < 3
             AND (
                 (status='pi_sent' AND sent_at IS NOT NULL
                  AND DATEDIFF(NOW(), sent_at) IN (3,7,14))
                 OR
                 (status='new' AND created_at IS NOT NULL
                  AND DATEDIFF(NOW(), created_at) IN (7,14))
             )
             AND (last_followup IS NULL OR DATE(last_followup) < CURDATE())",
            ARRAY_A
        );

        if ( empty( $inquiries ) ) return;

        foreach ( $inquiries as $inq ) {
            self::send_smart_followup( $inq );
        }
    }

    private static function send_smart_followup( array $inq ): void {
        global $wpdb;

        $count = (int) ( $inq['followup_count'] ?? 0 );

        // ── Generate AI follow-up message ────────────────────────────────
        $prompt = "Write a short, professional B2B follow-up email for laboratory glassware export.\n"
            . "Buyer: {$inq['name']} from {$inq['country']}\n"
            . "Follow-up number: " . ($count + 1) . " of 3\n"
            . "Product: " . ( json_decode($inq['product_details']??'[]',true)[0]['name'] ?? 'laboratory glassware' ) . "\n"
            . "Tone: " . ( $count === 0 ? 'warm and helpful' : ( $count === 1 ? 'gentle reminder' : 'final check-in, offer alternative' ) ) . "\n"
            . "Keep it under 80 words. Subject line + body. No signature (added separately).\n"
            . "Format: SUBJECT: ...\nBODY: ...";

        $ai_message = SGC_IIP_AI::call( $prompt, 'You are a B2B sales expert for laboratory glassware. Write concise, professional follow-up emails.', 300 );

        // Parse subject + body
        $subject = 'Following up on your inquiry — SGC LABS®';
        $body    = '';

        if ( ! is_wp_error( $ai_message ) ) {
            if ( preg_match( '/SUBJECT:\s*(.+)/i', $ai_message, $sm ) ) {
                $subject = trim( $sm[1] );
            }
            if ( preg_match( '/BODY:\s*(.+)/si', $ai_message, $bm ) ) {
                $body = trim( $bm[1] );
            } else {
                $body = $ai_message;
            }
        } else {
            // Fallback messages
            $fallbacks = [
                "Dear {$inq['name']},\n\nI wanted to follow up on your inquiry for laboratory glassware. We'd love to assist you. Please let us know if you need any clarification or revised pricing.\n\nBest regards,\nSGC LABS® Team",
                "Dear {$inq['name']},\n\nJust a gentle reminder regarding your inquiry. We have your Proforma Invoice ready. Please review and let us know how you'd like to proceed.\n\nBest regards,\nSGC LABS® Team",
                "Dear {$inq['name']},\n\nThis is our final follow-up. We remain happy to assist with any modifications to your order. Please feel free to reach out anytime.\n\nBest regards,\nSGC LABS® Team",
            ];
            $body = $fallbacks[ min( $count, 2 ) ];
        }

        // Send email
        if ( ! empty( $inq['email'] ) ) {
            $sent = wp_mail(
                $inq['email'],
                $subject,
                $body . "\n\n-- \nSGC LABS® | sales@labsglass.com | +91 9996416935",
                [ 'Content-Type: text/plain; charset=UTF-8' ]
            );

            if ( $sent ) {
                SGC_IIP_DB::log( $inq['id'], 'followup_sent',
                    'Auto follow-up #' . ($count+1) . ' sent: ' . $subject, 'system' );
            }
        }

        // WhatsApp follow-up (to buyer)
        if ( ! empty( $inq['phone'] ) && get_option('sgc_iip_wa_enabled','0') === '1' ) {
            SGC_IIP_WhatsApp::send_followup( $inq, $body );
        }

        // Update DB
        $wpdb->update( // phpcs:ignore
            SGC_IIP_DB::t('inquiries'),
            [
                'last_followup'  => current_time('mysql'),
                'followup_count' => $count + 1,
            ],
            [ 'id' => $inq['id'] ]
        );

        // Alert admin if deal going cold (3rd follow-up)
        if ( $count >= 2 ) {
            $my_phone = get_option( self::OPT_WA_MY_PHONE, '' );
            if ( $my_phone ) {
                SGC_IIP_WhatsApp::send(
                    $my_phone,
                    'text',
                    [ 'text' => [ 'body' =>
                        "⚠️ *Deal Going Cold* — Inquiry #{$inq['id']}\n"
                        . "Buyer: {$inq['name']} ({$inq['country']})\n"
                        . "3rd follow-up sent. Consider manual outreach.\n"
                        . admin_url( 'admin.php?page=sgc-iip&tab=inquiries' )
                    ] ]
                );
            }
        }
    }


    // ╔════════════════════════════════════════════════════════════════════╗
    // ║  SECTION 6 — DEFAULT RULES INSTALL                               ║
    // ╚════════════════════════════════════════════════════════════════════╝

    public static function install_default_rules(): void {
        if ( get_option( 'sgc_iip_auto_rules_installed' ) ) return;

        // Default follow-up rules for the existing follow-up system
        global $wpdb;
        $t = SGC_IIP_DB::t('followup_rules');

        // Check if table exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$t'" ) !== $t ) return; // phpcs:ignore

        // Check if rules already exist
        $existing = $wpdb->get_var( "SELECT COUNT(*) FROM `$t`" ); // phpcs:ignore
        if ( $existing > 0 ) {
            update_option( 'sgc_iip_auto_rules_installed', '1' );
            return;
        }

        $rules = [
            [
                'name'       => '3-Day Follow-up (PI Sent)',
                'trigger'    => 'pi_sent',
                'delay_days' => 3,
                'channel'    => 'email',
                'template'   => 'Dear {{name}},\n\nI hope you received our Proforma Invoice. Please review and let us know if you have any questions or need any modifications.\n\nBest regards,\nSGC LABS®',
                'status'     => 'active',
            ],
            [
                'name'       => '7-Day Follow-up (PI Sent)',
                'trigger'    => 'pi_sent',
                'delay_days' => 7,
                'channel'    => 'email_wa',
                'template'   => 'Dear {{name}},\n\nJust checking in — we would love to move forward with your order. Please let us know how you\'d like to proceed.\n\nBest regards,\nSGC LABS®',
                'status'     => 'active',
            ],
            [
                'name'       => '14-Day Final Follow-up',
                'trigger'    => 'pi_sent',
                'delay_days' => 14,
                'channel'    => 'email',
                'template'   => 'Dear {{name}},\n\nThis is our final follow-up. We remain available to assist you. Please don\'t hesitate to reach out if you need a revised quote.\n\nBest regards,\nSGC LABS®',
                'status'     => 'active',
            ],
        ];

        foreach ( $rules as $rule ) {
            $wpdb->insert( $t, array_merge( $rule, [ // phpcs:ignore
                'created_at' => current_time('mysql'),
            ] ) );
        }

        update_option( 'sgc_iip_auto_rules_installed', '1' );
    }


    // ╔════════════════════════════════════════════════════════════════════╗
    // ║  SECTION 7 — AJAX HANDLERS                                        ║
    // ╚════════════════════════════════════════════════════════════════════╝

    public static function ajax_save_settings(): void {
        if ( ! current_user_can('manage_options') ) wp_send_json_error('Permission denied.');
        if ( ! isset($_POST['nonce']) || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['nonce'])), 'sgc_iip_nonce' ) ) {
            wp_send_json_error('Security check failed.');
        }

        $settings = [
            self::OPT_AUTO_DRAFT  => sanitize_text_field( $_POST['auto_draft']  ?? '0' ),
            self::OPT_WA_ALERT    => sanitize_text_field( $_POST['wa_alert']    ?? '0' ),
            self::OPT_WA_MY_PHONE => sanitize_text_field( $_POST['my_phone']    ?? '' ),
            self::OPT_FOLLOWUP_ON => sanitize_text_field( $_POST['followup_on'] ?? '0' ),
        ];

        foreach ( $settings as $key => $val ) {
            update_option( $key, $val, false );
        }

        wp_send_json_success( 'Automation settings saved.' );
    }

    public static function ajax_test_wa_alert(): void {
        if ( ! current_user_can('manage_options') ) wp_send_json_error('Permission denied.');
        if ( ! isset($_POST['nonce']) || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['nonce'])), 'sgc_iip_nonce' ) ) {
            wp_send_json_error('Security check failed.');
        }

        $my_phone = sanitize_text_field( $_POST['phone'] ?? get_option(self::OPT_WA_MY_PHONE,'') );
        if ( empty($my_phone) ) { wp_send_json_error('Enter your phone number first.'); return; }

        $sent = SGC_IIP_WhatsApp::send( $my_phone, 'text', [
            'text' => [ 'body' =>
                "✅ *SGC Auto-Alert Test* — Working!\n"
                . "You will receive alerts like this when new inquiries arrive.\n"
                . "SGC LABS® Automation System v1.0"
            , 'preview_url' => false ]
        ] );

        if ( $sent ) wp_send_json_success('Test alert sent! Check your WhatsApp.');
        else wp_send_json_error('WhatsApp send failed. Check WA settings in Settings tab.');
    }

    public static function ajax_manual_draft(): void {
        if ( ! current_user_can('manage_options') ) wp_send_json_error('Permission denied.');
        if ( ! isset($_POST['nonce']) || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['nonce'])), 'sgc_iip_nonce' ) ) {
            wp_send_json_error('Security check failed.');
        }

        $inquiry_id = intval( $_POST['inquiry_id'] ?? 0 );
        if ( ! $inquiry_id ) { wp_send_json_error('Invalid inquiry ID.'); return; }

        self::run_auto_draft( $inquiry_id );
        wp_send_json_success( 'AI draft started. Refresh in 30 seconds.' );
    }


    // ╔════════════════════════════════════════════════════════════════════╗
    // ║  SECTION 8 — AUTOMATION SETTINGS TAB UI                          ║
    // ╚════════════════════════════════════════════════════════════════════╝

    public static function render_settings_section( string $nonce ): void {

        $auto_draft  = get_option( self::OPT_AUTO_DRAFT,  '1' );
        $wa_alert    = get_option( self::OPT_WA_ALERT,    '1' );
        $my_phone    = get_option( self::OPT_WA_MY_PHONE, '' );
        $followup_on = get_option( self::OPT_FOLLOWUP_ON, '1' );
        ?>
        <div class="iip-box" style="margin-top:16px;padding:0">
            <div style="background:#1a1a2e;color:#fff;padding:12px 18px;border-radius:8px 8px 0 0;display:flex;align-items:center;gap:10px">
                <span style="font-size:18px">🤖</span>
                <strong style="font-size:14px">Full Automation Settings</strong>
                <span style="font-size:11px;color:#9090cc;margin-left:auto">v1.0.0</span>
            </div>
            <div style="padding:18px">

                <!-- Auto PI Draft -->
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:14px 18px;margin-bottom:12px">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                            <input type="checkbox" id="auto-draft-toggle" <?php checked($auto_draft,'1'); ?> style="width:16px;height:16px">
                            <strong style="font-size:13px">🤖 Auto AI PI Draft</strong>
                        </label>
                        <span style="font-size:11px;background:#16a34a;color:#fff;padding:2px 8px;border-radius:10px">ACTIVE</span>
                    </div>
                    <p style="font-size:12px;color:#374151;margin:0">Jab bhi naya inquiry aaye — AI 60 seconds mein PI draft taiyaar kar deta hai. Tum sirf review karke send karo.</p>
                </div>

                <!-- WhatsApp Alert -->
                <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:14px 18px;margin-bottom:12px">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                            <input type="checkbox" id="wa-alert-toggle" <?php checked($wa_alert,'1'); ?> style="width:16px;height:16px">
                            <strong style="font-size:13px">📲 WhatsApp Admin Alert</strong>
                        </label>
                    </div>
                    <p style="font-size:12px;color:#374151;margin:0 0 10px">New inquiry aane pe tumhare phone pe turant WhatsApp alert aayega.</p>
                    <div style="display:flex;gap:8px;align-items:center">
                        <input type="tel" id="my-wa-phone" class="iip-input" value="<?php echo esc_attr($my_phone); ?>"
                               style="flex:1;font-size:12px" placeholder="+91 9996416935 (apna number)">
                        <button class="iip-btn sm" id="btn-test-wa-alert" style="font-size:12px;white-space:nowrap">
                            📤 Test Alert
                        </button>
                    </div>
                </div>

                <!-- Smart Follow-up -->
                <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:14px 18px;margin-bottom:16px">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                            <input type="checkbox" id="followup-toggle" <?php checked($followup_on,'1'); ?> style="width:16px;height:16px">
                            <strong style="font-size:13px">🔄 Smart Auto Follow-up</strong>
                        </label>
                    </div>
                    <p style="font-size:12px;color:#374151;margin:0">PI send ke baad 3, 7, aur 14 din par AI-written follow-up email + WhatsApp automatically bhejega.</p>
                </div>

                <!-- Save button -->
                <button class="iip-btn primary" id="btn-save-automation" style="width:100%">
                    💾 Save Automation Settings
                </button>
                <div id="auto-save-msg" style="margin-top:8px;font-size:12px;text-align:center;color:#16a34a"></div>

            </div>
        </div>

        <script>
        (function($){
            $('#btn-save-automation').on('click', function(){
                var btn=$(this);
                btn.prop('disabled',true).text('Saving…');
                $.post(SGCIIP.ajaxUrl, {
                    action:      'sgc_auto_save_settings',
                    nonce:       SGCIIP.nonce,
                    auto_draft:  $('#auto-draft-toggle').is(':checked') ? '1':'0',
                    wa_alert:    $('#wa-alert-toggle').is(':checked')   ? '1':'0',
                    my_phone:    $('#my-wa-phone').val(),
                    followup_on: $('#followup-toggle').is(':checked')   ? '1':'0',
                }, function(r){
                    btn.prop('disabled',false).text('💾 Save Automation Settings');
                    $('#auto-save-msg').text(r.success ? '✅ Saved!' : '❌ '+(r.data||'Error'));
                    setTimeout(function(){$('#auto-save-msg').text('');},3000);
                });
            });

            $('#btn-test-wa-alert').on('click', function(){
                var btn=$(this);
                btn.prop('disabled',true).text('Sending…');
                $.post(SGCIIP.ajaxUrl, {
                    action:'sgc_auto_test_wa_alert',
                    nonce:SGCIIP.nonce,
                    phone:$('#my-wa-phone').val()
                }, function(r){
                    btn.prop('disabled',false).text('📤 Test Alert');
                    alert(r.success ? r.data : '❌ '+r.data);
                });
            });
        })(jQuery);
        </script>
        <?php
    }
}

// ── Boot ──────────────────────────────────────────────────────────────────────
SGC_IIP_Automation::init();
SGC_IIP_Automation::register_bg_hooks();
