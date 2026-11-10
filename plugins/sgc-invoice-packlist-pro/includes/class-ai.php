<?php
defined( 'ABSPATH' ) || exit;

class SGC_IIP_AI {

    const PROVIDERS = [
        'deepseek' => [
            'url'   => 'https://api.deepseek.com/v1/chat/completions',
            'model' => 'deepseek-chat',
        ],
        'openai' => [
            'url'   => 'https://api.openai.com/v1/chat/completions',
            'model' => 'gpt-4o-mini',
        ],
        'claude' => [
            'url'   => 'https://api.anthropic.com/v1/messages',
            'model' => 'claude-haiku-4-5-20251001',
        ],
    ];

    public static function register_ajax() {
        $map = [
            'sgc_iip_ai_draft_pi'        => 'ajax_draft_pi',
            'sgc_iip_ai_draft_reply'     => 'ajax_draft_reply',
            'sgc_iip_ai_classify'        => 'ajax_classify',
            'sgc_iip_ai_oem_analyzer'    => 'ajax_oem_analyzer',
            'sgc_iip_ai_price_suggest'   => 'ajax_price_suggest',
            'sgc_iip_ai_test'            => 'ajax_test',
            'sgc_iip_ai_save_settings'   => 'ajax_save_settings',
        ];
        foreach ( $map as $action => $method ) {
            add_action( 'wp_ajax_' . $action, [ __CLASS__, $method ] );
        }
    }

    private static function verify() {
        check_ajax_referer( 'sgc_iip_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) )
            wp_send_json_error( 'Unauthorized', 403 );
    }

    // ── Core call ─────────────────────────────────────────────────────────
    public static function call( $prompt, $system = '', $max_tokens = 1500, $provider = null ) {
        if ( ! $provider ) $provider = get_option( 'sgc_iip_ai_provider', 'deepseek' );
        if ( ! isset( self::PROVIDERS[ $provider ] ) ) $provider = 'deepseek';

        $key = get_option( 'sgc_iip_ai_key_' . $provider, '' );

        // ── Fallback 1: Invoice Pro own keys (other providers) ────
        if ( empty( $key ) ) {
            foreach ( self::PROVIDERS as $pid => $p ) {
                $k = get_option( 'sgc_iip_ai_key_' . $pid, '' );
                if ( $k ) { $provider = $pid; $key = $k; break; }
            }
        }

        // ── Fallback 2: Manager Pro AI keys (shared keys) ─────────
        // Map: deepseek→sgclabs_aichat_deepseek_key, claude→sgclabs_claude_api_key
        // openai→sgclabs_aichat_openai_key, gemini→sgclabs_aichat_gemini_key
        if ( empty( $key ) && function_exists( 'sgclabs_ai_providers' ) ) {
            // Manager Pro is active — use its key system
            $mp_key_map = [
                'deepseek' => 'sgclabs_aichat_deepseek_key',
                'claude'   => 'sgclabs_claude_api_key',
                'openai'   => 'sgclabs_aichat_openai_key',
                'gemini'   => 'sgclabs_aichat_gemini_key',
            ];
            foreach ( $mp_key_map as $pid => $opt ) {
                $k = get_option( $opt, '' );
                if ( $k ) { $provider = $pid; $key = $k; break; }
            }
        }

        if ( empty( $key ) ) return new WP_Error( 'no_key',
            'No AI API key found. Set in Invoice Pro Settings → AI, or Manager Pro → AI Settings.' );

        $config = self::PROVIDERS[ $provider ];

        if ( 'claude' === $provider ) {
            $body = wp_json_encode( [
                'model'      => $config['model'],
                'max_tokens' => $max_tokens,
                'system'     => $system ?: 'You are a B2B export assistant for SGC LABS®, a laboratory glassware manufacturer in India.',
                'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
            ] );
            $headers = [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $key,
                'anthropic-version' => '2023-06-01',
            ];
        } else {
            $messages = [];
            if ( $system ) $messages[] = [ 'role' => 'system', 'content' => $system ];
            $messages[] = [ 'role' => 'user', 'content' => $prompt ];
            $body = wp_json_encode( [
                'model'       => $config['model'],
                'messages'    => $messages,
                'max_tokens'  => $max_tokens,
                'temperature' => 0.3,
            ] );
            $headers = [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $key,
            ];
        }

        $response = wp_remote_post( $config['url'], [
            'headers' => $headers,
            'body'    => $body,
            'timeout' => 60,
        ] );

        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $data['error']['message'] ?? ( 'API error ' . $code );
            return new WP_Error( 'ai_error', $msg );
        }

        if ( 'claude' === $provider ) return $data['content'][0]['text'] ?? '';
        return $data['choices'][0]['message']['content'] ?? '';
    }

    // ── Classify inquiry type ─────────────────────────────────────────────
    // ── v1.8.5: Enhanced AI classifier — full structured JSON output ─────────
    public static function classify_inquiry( array $inquiry ): ?array {
        $products = is_string( $inquiry['product_details'] ?? '' )
            ? json_decode( $inquiry['product_details'], true )
            : ( $inquiry['product_details'] ?? [] );
        $product_str = '';
        foreach ( (array) $products as $p ) {
            $product_str .= ( $p['name'] ?? '' ) . ' qty:' . ( $p['qty'] ?? '' ) . '. ';
        }

        $country     = sanitize_text_field( $inquiry['country'] ?? '' );

        $system = 'You are a B2B classifier for SGC LABS® — Indian lab glassware exporter.\nReturn ONLY a valid JSON object with these exact keys:\n{\n  "buyer_type": one of [oem,bulk,dealer,institution,retail,sample],\n  "market": one of [domestic,export],\n  "urgency": integer 1-5 (5=urgent),\n  "spam_score": integer 0-100 (100=definitely spam),\n  "suggested_status": one of [new,quoted,hold],\n  "reason": short string under 15 words\n}\nReturn ONLY the JSON. No markdown.';

        $prompt = "Classify this inquiry:\nName: {$inquiry['name']}\nCompany: {$inquiry['company']}\nCountry: {$country}\nProducts: {$product_str}\nMessage: " . substr( $inquiry['message'] ?? $inquiry['raw_message'] ?? '', 0, 400 );

        $result = self::call( $prompt, $system, 150 );
        if ( is_wp_error( $result ) ) return null;

        $clean = trim( preg_replace( '/```(?:json)?|```/', '', $result ) );
        $data  = json_decode( $clean, true );
        if ( ! is_array( $data ) ) return null;

        $valid_types    = [ 'oem', 'bulk', 'dealer', 'institution', 'retail', 'sample' ];
        $valid_statuses = [ 'new', 'quoted', 'hold' ];

        return [
            'buyer_type'       => in_array( $data['buyer_type'] ?? '', $valid_types, true )       ? $data['buyer_type']       : 'bulk',
            'market'           => ( $data['market'] ?? '' ) === 'domestic'                        ? 'domestic'               : 'export',
            'urgency'          => min( 5, max( 1, (int) ( $data['urgency']     ?? 3 ) ) ),
            'spam_score'       => min( 100, max( 0, (int) ( $data['spam_score'] ?? 0 ) ) ),
            'suggested_status' => in_array( $data['suggested_status'] ?? '', $valid_statuses, true ) ? $data['suggested_status'] : 'new',
            'reason'           => substr( sanitize_text_field( $data['reason'] ?? '' ), 0, 100 ),
        ];
    }

    // ── AJAX: Draft Proforma Invoice ──────────────────────────────────────
    public static function ajax_draft_pi() {
        self::verify();
        global $wpdb;

        $inquiry_id = (int) ( $_POST['inquiry_id'] ?? 0 );
        $inq = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore
            "SELECT * FROM " . SGC_IIP_DB::t('inquiries') . " WHERE id=%d", $inquiry_id
        ), ARRAY_A );
        if ( ! $inq ) { wp_send_json_error( 'Inquiry not found.' ); return; }

        $products = json_decode( $inq['product_details'] ?? '[]', true ) ?: [];
        $currency = $inq['currency'] ?? 'USD';
        $usd_rate = floatval( get_option( 'sgc_iip_usd_rate', 83.50 ) );

        // Fetch relevant WooCommerce products for pricing
        $wc_products_info = '';
        foreach ( $products as $p ) {
            if ( ! empty( $p['name'] ) ) {
                $wc_q = new WP_Query( [
                    'post_type'      => 'product',
                    's'              => $p['name'],
                    'posts_per_page' => 3,
                    'fields'         => 'ids',
                ] );
                foreach ( $wc_q->posts as $pid ) {
                    $wcp = wc_get_product( $pid );
                    if ( $wcp ) {
                        $wc_products_info .= "\nProduct: " . $wcp->get_name()
                            . " | SKU: " . $wcp->get_sku()
                            . " | Price INR: " . $wcp->get_regular_price()
                            . " | Price USD: $" . round( floatval( $wcp->get_regular_price() ) / $usd_rate, 2 );
                    }
                }
            }
        }

        // Get HS code
        $hs_default = $wpdb->get_var( "SELECT hs_code FROM " . SGC_IIP_DB::t('hs_codes') . " WHERE category_match='borosilicate' LIMIT 1" ); // phpcs:ignore

        $system = 'You are an expert B2B export pricing assistant for SGC LABS®, a laboratory glassware manufacturer in India. Generate accurate Proforma Invoice line items. Return ONLY valid JSON.';

        $product_list = implode( ', ', array_column( $products, 'name' ) );
        $qty_list     = implode( ', ', array_map( function($p){ return ($p['name']??'').'×'.($p['qty']??'?'); }, $products ) );

        $prompt = "Generate Proforma Invoice line items for:\n"
            . "Buyer: {$inq['name']} from {$inq['country']}\n"
            . "Products requested: $qty_list\n"
            . "Currency: $currency\n"
            . "Incoterm: {$inq['incoterm']}\n"
            . "Our products (reference prices): $wc_products_info\n\n"
            . "Return ONLY this JSON structure:\n"
            . '{"line_items":[{"product_name":"...","sku":"...","hs_code":"7017.20","qty":0,"unit_price":0.00,"packaging_per_unit":0.10,"oem_charge":0.00,"total":0.00}],"subtotal":0.00,"packaging_total":0.00,"oem_total":0.00,"grand_total":0.00,"payment_terms":"30% advance, 70% before shipment","delivery_days":"15-20 working days","notes":"...","ai_remarks":"brief pricing rationale"}';

        $result = self::call( $prompt, $system, 1500 );
        if ( is_wp_error( $result ) ) { wp_send_json_error( $result->get_error_message() ); return; }

        $clean  = preg_replace( '/```(?:json)?|```/', '', $result );
        $parsed = json_decode( trim( $clean ), true );
        if ( ! $parsed ) { wp_send_json_error( 'AI response parsing failed.' ); return; }

        // Save AI draft to inquiry
        $wpdb->update( SGC_IIP_DB::t('inquiries'), [ 'ai_draft' => wp_json_encode( $parsed ) ], [ 'id' => $inquiry_id ] ); // phpcs:ignore
        SGC_IIP_DB::log( $inquiry_id, 'ai_pi_drafted', 'AI drafted Proforma Invoice', 'ai' );

        wp_send_json_success( $parsed );
    }

    // ── AJAX: Draft reply email ───────────────────────────────────────────
    public static function ajax_draft_reply() {
        self::verify();
        global $wpdb;

        $id   = (int) ( $_POST['inquiry_id'] ?? 0 );
        $tone = sanitize_key( $_POST['tone'] ?? 'formal' );

        $inq = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore
            "SELECT * FROM " . SGC_IIP_DB::t('inquiries') . " WHERE id=%d", $id
        ), ARRAY_A );
        if ( ! $inq ) { wp_send_json_error( 'Not found.' ); return; }

        $tones = [
            'formal'    => 'Professional B2B formal tone — technical details, certifications',
            'friendly'  => 'Warm friendly tone — relationship building',
            'export'    => 'Export-focused — FOB pricing, HS codes, shipping terms',
            'oem'       => 'OEM specialist tone — customization details, MOQ, lead time',
        ];
        $tone_desc = $tones[ $tone ] ?? $tones['formal'];

        $products = json_decode( $inq['product_details'] ?? '[]', true );
        $prod_str = implode( ', ', array_column( (array) $products, 'name' ) );

        $system = 'You are an expert export sales correspondent for SGC LABS®, a leading laboratory glassware manufacturer in India since 2003. ISO certified. Write compelling B2B email replies.';

        $prompt = "Write 3 different reply email drafts to this inquiry:\n"
            . "From: {$inq['name']} ({$inq['company']}) — {$inq['country']}\n"
            . "Products: $prod_str\n"
            . "Message: " . substr( $inq['raw_message'] ?? '', 0, 400 ) . "\n"
            . "Buyer type: {$inq['buyer_type']}\n"
            . "Tone: $tone_desc\n\n"
            . 'Return ONLY JSON: {"drafts":[{"tone":"formal","subject":"...","body":"..."},{"tone":"friendly","subject":"...","body":"..."},{"tone":"export","subject":"...","body":"..."}]}';

        $result = self::call( $prompt, $system, 2000 );
        if ( is_wp_error( $result ) ) { wp_send_json_error( $result->get_error_message() ); return; }

        $clean  = preg_replace( '/```(?:json)?|```/', '', $result );
        $parsed = json_decode( trim( $clean ), true );
        if ( ! $parsed ) { wp_send_json_error( 'Parse failed.' ); return; }

        wp_send_json_success( $parsed );
    }

    // ── AJAX: OEM Analyzer ────────────────────────────────────────────────
    public static function ajax_oem_analyzer() {
        self::verify();

        $message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
        $product = sanitize_text_field( wp_unslash( $_POST['product']  ?? '' ) );
        if ( ! $message ) { wp_send_json_error( 'No message.' ); return; }

        $system = 'You are an OEM specialist for SGC LABS® laboratory glassware manufacturer. Extract specs and estimate costs accurately. Return ONLY valid JSON.';

        $prompt = "Analyze this OEM request and extract specifications:\n"
            . "Product: $product\n"
            . "Message: $message\n\n"
            . 'Return JSON: {"product_type":"...","material":"Borosilicate 3.3","capacity":"...","graduation":"...","customizations":["..."],"logo_printing":true,"qty":0,"moq":0,"estimated_setup_cost_inr":0,"estimated_per_piece_inr":0,"lead_time_days":0,"hs_code":"7017.20","notes":"...","feasibility":"high|medium|low","ai_confidence":"high|medium|low"}';

        $result = self::call( $prompt, $system, 800 );
        if ( is_wp_error( $result ) ) { wp_send_json_error( $result->get_error_message() ); return; }

        $clean  = preg_replace( '/```(?:json)?|```/', '', $result );
        $parsed = json_decode( trim( $clean ), true );
        if ( ! $parsed ) { wp_send_json_error( 'Parse failed.' ); return; }

        wp_send_json_success( $parsed );
    }

    // ── AJAX: Classify ────────────────────────────────────────────────────
    public static function ajax_classify() {
        self::verify();
        global $wpdb;
        $id  = (int) ( $_POST['id'] ?? 0 );
        $inq = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . SGC_IIP_DB::t('inquiries') . ' WHERE id=%d', $id ), ARRAY_A ); // phpcs:ignore
        if ( ! $inq ) { wp_send_json_error( 'Not found.' ); return; }

        // v1.8.5: classify_inquiry now returns array (was string)
        $result = self::classify_inquiry( $inq );
        if ( is_array( $result ) ) {
            $wpdb->update( SGC_IIP_DB::t('inquiries'), [ // phpcs:ignore
                'buyer_type'    => $result['buyer_type'],
                'ai_classified' => 1,
                'urgency_score' => $result['urgency'],
                'spam_score'    => $result['spam_score'],
                'ai_notes'      => wp_json_encode( $result ),
            ], [ 'id' => $id ] );
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( 'Classification failed.' );
        }
    }

    // ── AJAX: Price Suggest ───────────────────────────────────────────────
    public static function ajax_price_suggest() {
        self::verify();
        $product = sanitize_text_field( wp_unslash( $_POST['product']  ?? '' ) );
        $qty     = sanitize_text_field( wp_unslash( $_POST['qty']      ?? '' ) );
        $country = sanitize_text_field( wp_unslash( $_POST['country']  ?? '' ) );
        $type    = sanitize_key( wp_unslash( $_POST['buyer_type'] ?? 'bulk' ) );

        $usd_rate = floatval( get_option( 'sgc_iip_usd_rate', 83.50 ) );

        // Get WC price
        $wc_price_inr = 0;
        $wc_q = new WP_Query( [ 'post_type' => 'product', 's' => $product, 'posts_per_page' => 1, 'fields' => 'ids' ] );
        if ( $wc_q->posts ) {
            $wcp = wc_get_product( $wc_q->posts[0] );
            if ( $wcp ) $wc_price_inr = floatval( $wcp->get_regular_price() );
        }

        $system = 'Pricing expert for Indian lab glassware exporter. Give data-driven export pricing advice. Return ONLY JSON.';
        $prompt = "Suggest export pricing for:\nProduct: $product\nQty: $qty\nBuyer Country: $country\nBuyer Type: $type\nOur MRP (INR): ₹$wc_price_inr\nCurrent USD rate: $usd_rate\n\n"
            . 'Return: {"suggested_unit_usd":0.00,"suggested_unit_inr":0,"packaging_per_unit_usd":0.10,"reasoning":"...","discount_for_qty":"...","market_position":"competitive|premium|budget","total_usd":0.00}';

        $result = self::call( $prompt, $system, 500 );
        if ( is_wp_error( $result ) ) { wp_send_json_error( $result->get_error_message() ); return; }

        $clean  = preg_replace( '/```(?:json)?|```/', '', $result );
        $parsed = json_decode( trim( $clean ), true );
        wp_send_json_success( $parsed ?: [] );
    }

    // ── AJAX: Test connection ─────────────────────────────────────────────
    public static function ajax_test() {
        self::verify();
        $provider = sanitize_key( $_POST['provider'] ?? 'deepseek' );
        $result   = self::call( 'Reply with exactly: OK', 'Test assistant.', 10, $provider );
        if ( is_wp_error( $result ) ) { wp_send_json_error( $result->get_error_message() ); return; }
        wp_send_json_success( 'Connected: ' . substr( $result, 0, 30 ) );
    }

    // ── AJAX: Save AI settings ────────────────────────────────────────────
    public static function ajax_save_settings() {
        self::verify();
        $fields = [
            'sgc_iip_ai_provider'      => 'key',
            'sgc_iip_ai_key_deepseek'  => 'text',
            'sgc_iip_ai_key_openai'    => 'text',
            'sgc_iip_ai_key_claude'    => 'text',
        ];
        foreach ( $fields as $k => $type ) {
            if ( ! isset( $_POST[ $k ] ) ) continue;
            $v = wp_unslash( $_POST[ $k ] );
            update_option( $k, 'key' === $type ? sanitize_key( $v ) : sanitize_text_field( $v ) );
        }
        wp_send_json_success( 'AI settings saved.' );
    }
}

SGC_IIP_AI::register_ajax();
