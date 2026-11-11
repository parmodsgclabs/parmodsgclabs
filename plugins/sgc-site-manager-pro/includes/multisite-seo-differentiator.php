<?php
/**
 * SGC LABS® — 5-Domain SEO Differentiator v7.2.1
 *
 * Distributed SEO Hub — install on ALL 5 sites.
 * Hub site (labsglass.com) fetches, AI differentiates, pushes to all 5
 * via REST API. Each site is both hub and receiver.
 *
 * AI Providers: Claude Haiku · Gemini Flash · DeepSeek Chat · DeepSeek Reasoner
 * RankMath: reads rank_math_* keys, writes back to same keys — zero conflict.
 * Cross-site: token-based REST push — no FTP, no manual copy-paste.
 *
 * @package   SGCLabs
 * @since     7.2.1
 */

defined( 'ABSPATH' ) || exit;

define( 'SGCLABS_MSD_VERSION', '7.2.1' );

// ── Site token — generated once, used for cross-site REST auth ────────────────

function sgclabs_msd_get_token() {
    $token = get_option( 'sgclabs_msd_site_token' );
    if ( ! $token ) {
        $token = wp_generate_password( 32, false );
        update_option( 'sgclabs_msd_site_token', $token );
    }
    return $token;
}

// ── Domain profiles ───────────────────────────────────────────────────────────

function sgclabs_msd_get_profiles() {
    // Saved profiles override defaults (allows admin to add/edit)
    $saved = get_option( 'sgclabs_msd_profiles', [] );
    $defaults = [
        'labsglass.com' => [
            'url'    => 'https://labsglass.com',
            'token'  => '',
            'market' => 'International B2B export, USD, 50+ countries, ISO 9001:2015, FOB',
            'angle'  => 'Global laboratory glassware exporter — B2B bulk, FOB New Delhi',
            'color'  => '#185FA5',
            'bg'     => '#E6F1FB',
        ],
        'labglass.in' => [
            'url'    => 'https://labglass.in',
            'token'  => '',
            'market' => 'India domestic — colleges, universities, govt labs, research institutes, INR',
            'angle'  => 'Affordable lab glassware for Indian educational & research institutions',
            'color'  => '#27500A',
            'bg'     => '#EAF3DE',
        ],
        'sgclabglass.com' => [
            'url'    => 'https://sgclabglass.com',
            'token'  => '',
            'market' => 'OEM private label, bulk wholesale distributors, custom branding, NDAs',
            'angle'  => 'OEM glassware manufacturing partner — private label & bulk production',
            'color'  => '#633806',
            'bg'     => '#FAEEDA',
        ],
        'astmglass.com' => [
            'url'    => 'https://astmglass.com',
            'token'  => '',
            'market' => 'Glass blowers worldwide, semi-finished raw materials, ASTM/DIN/ISO, machines',
            'angle'  => 'Glass blowing components & semi-finished materials supplier to glass blowers',
            'color'  => '#3C3489',
            'bg'     => '#EEEDFE',
        ],
        'sgclabs.in' => [
            'url'    => 'https://sgclabs.in',
            'token'  => '',
            'market' => 'DIN/ISO precision lab glassware, European-grade, ASTM glassware, India',
            'angle'  => 'DIN/ISO certified precision laboratory glassware — European standard quality',
            'color'  => '#085041',
            'bg'     => '#E1F5EE',
        ],
    ];
    foreach ( $defaults as $k => $v ) {
        if ( ! isset( $saved[ $k ] ) ) $saved[ $k ] = $v;
        else $saved[ $k ] = array_merge( $v, $saved[ $k ] );
    }
    return $saved;
}

// ── REST API — Receiver endpoint (runs on all 5 sites) ───────────────────────

add_action( 'rest_api_init', function() {
    // Endpoint: POST /wp-json/sgclabs/v1/apply-seo
    register_rest_route( 'sgclabs/v1', '/apply-seo', [
        'methods'             => 'POST',
        'callback'            => 'sgclabs_msd_rest_apply_seo',
        'permission_callback' => '__return_true', // token checked inside
    ] );

    // Endpoint: GET /wp-json/sgclabs/v1/site-seo (fetch this site's current SEO)
    register_rest_route( 'sgclabs/v1', '/site-seo', [
        'methods'             => 'GET',
        'callback'            => 'sgclabs_msd_rest_get_seo',
        'permission_callback' => '__return_true',
    ] );
} );

function sgclabs_msd_rest_apply_seo( WP_REST_Request $request ) {
    $token = sanitize_text_field( $request->get_param( 'token' ) );
    if ( $token !== sgclabs_msd_get_token() ) {
        return new WP_Error( 'unauthorized', 'Invalid site token', [ 'status' => 403 ] );
    }

    $title       = sanitize_text_field( $request->get_param( 'title' ) );
    $description = sanitize_textarea_field( $request->get_param( 'description' ) );
    $og_title    = sanitize_text_field( $request->get_param( 'og_title' ) ?: $title );
    $focus_kw    = sanitize_text_field( $request->get_param( 'focus_keyword' ) );

    if ( ! $title && ! $description ) {
        return new WP_Error( 'empty', 'Nothing to apply', [ 'status' => 400 ] );
    }

    $source = sgclabs_save_homepage_seo( $title, $description );

    // Also update focus keyword in RankMath if active
    if ( function_exists( 'sgclabs_rm_is_active' ) && sgclabs_rm_is_active() && $focus_kw ) {
        $front = (int) get_option( 'page_on_front' );
        if ( $front ) update_post_meta( $front, 'rank_math_focus_keyword', $focus_kw );
    }

    // Clear page caches
    if ( function_exists( 'rocket_clean_home' ) ) rocket_clean_home();
    delete_transient( 'sgclabs_seo_homepage_cache' );

    return rest_ensure_response( [
        'applied'  => true,
        'source'   => $source,
        'domain'   => wp_parse_url( home_url(), PHP_URL_HOST ),
        'title'    => $title,
        'desc_len' => strlen( $description ),
    ] );
}

function sgclabs_msd_rest_get_seo( WP_REST_Request $request ) {
    // Public — returns current live SEO data for this site
    $seo = sgclabs_get_homepage_seo();
    return rest_ensure_response( [
        'domain'      => wp_parse_url( home_url(), PHP_URL_HOST ),
        'title'       => $seo['title'],
        'description' => $seo['description'],
        'source'      => $seo['source'],
        'rm_active'   => function_exists( 'sgclabs_rm_is_active' ) && sgclabs_rm_is_active(),
        'version'     => SGCLABS_MSD_VERSION,
    ] );
}

// ── Live fetch — parse meta from external URL ─────────────────────────────────

function sgclabs_msd_fetch_live_seo( $url ) {
    $resp = wp_remote_get( esc_url_raw( $url ), [
        'timeout'    => 14,
        'user-agent' => 'SGCLabs-SEO-Hub/7.1',
        'sslverify'  => false,
    ] );
    if ( is_wp_error( $resp ) ) return [ 'error' => $resp->get_error_message() ];

    $html = wp_remote_retrieve_body( $resp );
    if ( empty( $html ) ) return [ 'error' => 'Empty response from ' . $url ];

    $data = [
        'title' => '', 'description' => '', 'h1' => [], 'h2' => [],
        'og_title' => '', 'og_desc' => '', 'canonical' => '',
        'url' => $url, 'fetched_at' => current_time( 'mysql' ),
    ];

    if ( preg_match( '/<title[^>]*>(.*?)<\/title>/si', $html, $m ) )
        $data['title'] = wp_strip_all_tags( html_entity_decode( trim( $m[1] ) ) );

    foreach ( [
        '/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/si',
        '/<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']description["\'][^>]*>/si',
    ] as $re ) {
        if ( ! $data['description'] && preg_match( $re, $html, $m ) )
            $data['description'] = wp_strip_all_tags( html_entity_decode( trim( $m[1] ) ) );
    }

    if ( preg_match( '/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/si', $html, $m ) )
        $data['og_title'] = wp_strip_all_tags( html_entity_decode( trim( $m[1] ) ) );

    if ( preg_match( '/<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']*)["\'][^>]*>/si', $html, $m ) )
        $data['canonical'] = trim( $m[1] );

    preg_match_all( '/<h1[^>]*>(.*?)<\/h1>/si', $html, $m );
    foreach ( array_slice( $m[1], 0, 3 ) as $h ) {
        $t = wp_strip_all_tags( html_entity_decode( trim( $h ) ) );
        if ( $t ) $data['h1'][] = $t;
    }

    preg_match_all( '/<h2[^>]*>(.*?)<\/h2>/si', $html, $m );
    foreach ( array_slice( $m[1], 0, 4 ) as $h ) {
        $t = wp_strip_all_tags( html_entity_decode( trim( $h ) ) );
        if ( $t ) $data['h2'][] = $t;
    }

    $issues = [];
    if ( count( $data['h1'] ) > 1 )                 $issues[] = count( $data['h1'] ) . ' H1 tags';
    if ( empty( $data['description'] ) )              $issues[] = 'Meta description missing';
    if ( strlen( $data['description'] ) > 160 )       $issues[] = 'Description too long (' . strlen( $data['description'] ) . ' chars)';
    if ( strlen( $data['title'] ) > 65 )              $issues[] = 'Title too long (' . strlen( $data['title'] ) . ' chars)';
    if ( $data['og_title'] && $data['og_title'] !== $data['title'] ) $issues[] = 'OG title mismatch';
    $data['issues'] = $issues;

    return $data;
}

// ── AI Differentiation ────────────────────────────────────────────────────────

function sgclabs_msd_build_prompt( $domain, $profile, $live_data, $all_profiles ) {
    $others = '';
    foreach ( $all_profiles as $d => $p ) {
        if ( $d !== $domain ) $others .= "- {$d}: {$p['angle']}\n";
    }
    $cur_title = $live_data['title']       ?? 'Not set';
    $cur_desc  = $live_data['description'] ?? 'Not set';
    $cur_h1    = implode( ' | ', $live_data['h1'] ?? [] ) ?: 'Not found';
    $cur_h2    = implode( ' | ', array_slice( $live_data['h2'] ?? [], 0, 2 ) ) ?: 'Not found';

    return "You are an expert SEO strategist for SGC LABS® (laboratory glassware manufacturer, Ambala India, ISO 9001:2015, since 2003).

TARGET DOMAIN: {$domain}
Market: {$profile['market']}
Unique angle: {$profile['angle']}

CURRENT SEO (live data — needs improvement):
Title: {$cur_title}
Description: {$cur_desc}
H1: {$cur_h1}
H2: {$cur_h2}

SISTER SITES (must NOT overlap keywords or angles with these):
{$others}

RULES:
- Title: 50-60 chars
- Description: 140-155 chars, include primary keyword naturally, end with CTA
- H1: Clear, specific, contains focus keyword
- H2: Supporting keyword phrase
- All 5 sites belong to the SAME firm but must appear as distinct niche authorities to Google
- Do NOT repeat words/phrases across sister site angles

Respond ONLY with valid JSON (no markdown, no backticks, no explanation):
{\"title\":\"...\",\"description\":\"...\",\"h1\":\"...\",\"h2\":\"...\",\"focus_keyword\":\"...\",\"rationale\":\"1 sentence why this angle is unique\"}";
}

/**
 * Extract first valid JSON object from any string.
 * Handles: pure JSON, markdown fences, JSON inside text, reasoning tokens.
 */
function sgclabs_msd_extract_json( $raw ) {
    if ( empty( $raw ) ) return null;

    // Strip markdown fences
    $clean = preg_replace( '/^```(?:json)?\s*|\s*```$/m', '', trim( $raw ) );

    // Try direct parse first
    $parsed = json_decode( $clean, true );
    if ( $parsed && isset( $parsed['title'] ) ) return $parsed;

    // Find first { ... } block (handles reasoning tokens before JSON)
    if ( preg_match( '/\{[^{}]*"title"[^{}]*\}/s', $raw, $m ) ) {
        $parsed = json_decode( $m[0], true );
        if ( $parsed && isset( $parsed['title'] ) ) return $parsed;
    }

    // Broader: find outermost { }
    $start = strpos( $raw, '{' );
    $end   = strrpos( $raw, '}' );
    if ( $start !== false && $end !== false && $end > $start ) {
        $parsed = json_decode( substr( $raw, $start, $end - $start + 1 ), true );
        if ( $parsed && isset( $parsed['title'] ) ) return $parsed;
    }

    return null;
}

function sgclabs_msd_ai_call( $prompt, $provider = 'auto' ) {
    // System prompt — "json" word must appear for response_format mode
    $system = 'You are an SEO expert. You must respond with ONLY a valid JSON object. No markdown code fences. No explanation. No text before or after the JSON object. Start your response with { and end with }.';

    // Auto chain: DeepSeek → Gemini → Claude
    if ( $provider === 'auto' ) {
        $has_ds  = (bool) get_option( 'sgclabs_aichat_deepseek_key' );
        $has_gm  = (bool) get_option( 'sgclabs_gemini_api_key' );
        $has_cl  = (bool) get_option( 'sgclabs_claude_api_key' );
        $chain   = [];
        if ( $has_ds ) $chain[] = 'deepseek_chat';
        if ( $has_gm ) $chain[] = 'gemini';
        if ( $has_cl ) $chain[] = 'claude';
        if ( empty( $chain ) ) return [ 'error' => 'No AI provider keys configured in Settings.' ];
        foreach ( $chain as $p ) {
            $r = sgclabs_msd_ai_call( $prompt, $p );
            if ( ! empty( $r['text'] ) ) return $r;
        }
        return [ 'error' => 'All configured AI providers failed. Check API keys.' ];
    }

    switch ( $provider ) {

        case 'deepseek_chat':
        case 'deepseek_reasoner':
            $key = sgclabs_get_api_key( 'sgclabs_aichat_deepseek_key' );
            if ( ! $key ) return [ 'error' => 'DeepSeek API key not set in Settings.' ];

            $model = ( $provider === 'deepseek_reasoner' ) ? 'deepseek-reasoner' : 'deepseek-chat';

            // NOTE: NO response_format here — deepseek-reasoner does not support it,
            // and deepseek-chat sometimes returns empty content with json_object mode.
            // We extract JSON manually from the response instead.
            $resp = wp_remote_post( 'https://api.deepseek.com/chat/completions', [
                'timeout' => 40,
                'headers' => [
                    'Authorization' => 'Bearer ' . $key,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode( [
                    'model'      => $model,
                    'messages'   => [
                        [ 'role' => 'system', 'content' => $system ],
                        [ 'role' => 'user',   'content' => $prompt ],
                    ],
                    'max_tokens' => 700,
                ] ),
            ] );

            if ( is_wp_error( $resp ) ) return [ 'error' => 'DeepSeek connection error: ' . $resp->get_error_message() ];

            $http_code = wp_remote_retrieve_response_code( $resp );
            $body      = json_decode( wp_remote_retrieve_body( $resp ), true );

            // Catch API-level errors (401, 429, 500 etc.)
            if ( $http_code !== 200 ) {
                $api_err = $body['error']['message'] ?? ( 'HTTP ' . $http_code );
                return [ 'error' => 'DeepSeek API error: ' . $api_err ];
            }

            // Reasoner models return content in choices[0].message.content
            // but may also have reasoning_content — we ignore that
            $raw = $body['choices'][0]['message']['content'] ?? '';
            if ( empty( $raw ) ) {
                return [ 'error' => 'DeepSeek returned empty response. Try again.' ];
            }
            return [ 'text' => $raw, 'provider_used' => $provider ];

        case 'gemini':
        case 'gemini_flash':
            $key = sgclabs_get_api_key( 'sgclabs_gemini_api_key' );
            if ( ! $key ) return [ 'error' => 'Gemini API key not set in Settings.' ];

            $resp = wp_remote_post(
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $key,
                [
                    'timeout' => 30,
                    'headers' => [ 'Content-Type' => 'application/json' ],
                    'body'    => wp_json_encode( [
                        'contents'         => [ [ 'parts' => [ [ 'text' => $system . "\n\n" . $prompt ] ] ] ],
                        'generationConfig' => [
                            'maxOutputTokens'  => 700,
                            'responseMimeType' => 'application/json',
                        ],
                    ] ),
                ]
            );

            if ( is_wp_error( $resp ) ) return [ 'error' => 'Gemini connection error: ' . $resp->get_error_message() ];

            $http_code = wp_remote_retrieve_response_code( $resp );
            $body      = json_decode( wp_remote_retrieve_body( $resp ), true );

            if ( $http_code !== 200 ) {
                $api_err = $body['error']['message'] ?? ( 'HTTP ' . $http_code );
                return [ 'error' => 'Gemini API error: ' . $api_err ];
            }

            $raw = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
            if ( empty( $raw ) ) return [ 'error' => 'Gemini returned empty response.' ];
            return [ 'text' => $raw, 'provider_used' => 'gemini' ];

        case 'claude':
        case 'claude_haiku':
            $key = sgclabs_get_api_key( 'sgclabs_claude_api_key' );
            if ( ! $key ) return [ 'error' => 'Claude API key not set in Settings.' ];

            $resp = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
                'timeout' => 35,
                'headers' => [
                    'x-api-key'         => $key,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ],
                'body' => wp_json_encode( [
                    'model'      => 'claude-haiku-4-5-20251001',
                    'max_tokens' => 700,
                    'system'     => $system,
                    'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
                ] ),
            ] );

            if ( is_wp_error( $resp ) ) return [ 'error' => 'Claude connection error: ' . $resp->get_error_message() ];

            $http_code = wp_remote_retrieve_response_code( $resp );
            $body      = json_decode( wp_remote_retrieve_body( $resp ), true );

            if ( $http_code !== 200 ) {
                $api_err = $body['error']['message'] ?? ( 'HTTP ' . $http_code );
                return [ 'error' => 'Claude API error: ' . $api_err ];
            }

            $raw = $body['content'][0]['text'] ?? '';
            if ( empty( $raw ) ) return [ 'error' => 'Claude returned empty response.' ];
            return [ 'text' => $raw, 'provider_used' => 'claude' ];

        default:
            return [ 'error' => 'Unknown AI provider: ' . $provider ];
    }
}

// ── Cross-site REST push ──────────────────────────────────────────────────────

function sgclabs_msd_push_to_site( $site_url, $token, $seo_data ) {
    if ( empty( $token ) ) {
        return [ 'error' => 'No token set for ' . $site_url . '. Add it in Settings → Site Tokens.' ];
    }

    $endpoint = trailingslashit( $site_url ) . 'wp-json/sgclabs/v1/apply-seo';
    $resp = wp_remote_post( $endpoint, [
        'timeout' => 15,
        'headers' => [ 'Content-Type' => 'application/json' ],
        'body'    => wp_json_encode( array_merge( $seo_data, [ 'token' => $token ] ) ),
        'sslverify' => false,
    ] );

    if ( is_wp_error( $resp ) ) return [ 'error' => $resp->get_error_message() ];

    $code = wp_remote_retrieve_response_code( $resp );
    $body = json_decode( wp_remote_retrieve_body( $resp ), true );

    if ( $code === 200 && ! empty( $body['applied'] ) ) return [ 'success' => true, 'data' => $body ];
    if ( $code === 403 ) return [ 'error' => 'Token mismatch — check token for ' . $site_url ];
    return [ 'error' => 'HTTP ' . $code . ': ' . ( $body['message'] ?? 'Unknown error' ) ];
}

// ── AJAX: Fetch one domain ────────────────────────────────────────────────────

add_action( 'wp_ajax_sgclabs_msd_fetch_domain', function() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $domain   = sanitize_text_field( wp_unslash( $_POST['domain'] ?? '' ) );
    $profiles = sgclabs_msd_get_profiles();

    if ( ! isset( $profiles[ $domain ] ) ) wp_send_json_error( 'Unknown domain: ' . $domain );

    $data = sgclabs_msd_fetch_live_seo( $profiles[ $domain ]['url'] );
    if ( isset( $data['error'] ) ) wp_send_json_error( $data['error'] );

    wp_send_json_success( $data );
} );

// ── AJAX: AI differentiate one domain ────────────────────────────────────────

add_action( 'wp_ajax_sgclabs_msd_ai_differentiate', function() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $domain    = sanitize_text_field( wp_unslash( $_POST['domain']   ?? '' ) );
    $provider  = sanitize_text_field( wp_unslash( $_POST['provider'] ?? 'auto' ) );
    $live_data = json_decode( wp_unslash( $_POST['live_data'] ?? '{}' ), true ) ?: [];

    $profiles = sgclabs_msd_get_profiles();
    if ( ! isset( $profiles[ $domain ] ) ) wp_send_json_error( 'Unknown domain: ' . $domain );

    $prompt = sgclabs_msd_build_prompt( $domain, $profiles[ $domain ], $live_data, $profiles );
    $result = sgclabs_msd_ai_call( $prompt, $provider );

    // API-level error — return clear message
    if ( ! empty( $result['error'] ) ) {
        wp_send_json_error( $result['error'] );
    }

    $raw    = $result['text'] ?? '';
    $parsed = sgclabs_msd_extract_json( $raw );

    if ( ! $parsed ) {
        // Return first 300 chars of raw for debugging
        $preview = substr( $raw, 0, 300 );
        wp_send_json_error( 'AI JSON parse failed. Raw response: ' . $preview );
    }

    // Ensure required keys exist with fallbacks
    $ai = [
        'title'         => sanitize_text_field( $parsed['title']         ?? '' ),
        'description'   => sanitize_textarea_field( $parsed['description'] ?? '' ),
        'h1'            => sanitize_text_field( $parsed['h1']            ?? '' ),
        'h2'            => sanitize_text_field( $parsed['h2']            ?? '' ),
        'focus_keyword' => sanitize_text_field( $parsed['focus_keyword'] ?? '' ),
        'rationale'     => sanitize_text_field( $parsed['rationale']     ?? '' ),
    ];

    if ( empty( $ai['title'] ) ) {
        wp_send_json_error( 'AI returned JSON but title field is empty. Raw: ' . substr( $raw, 0, 200 ) );
    }

    wp_send_json_success( [
        'domain'        => $domain,
        'ai'            => $ai,
        'provider'      => $result['provider_used'] ?? $provider,
        'raw_length'    => strlen( $raw ),
    ] );
} );

// ── AJAX: Apply (cross-site or local) ────────────────────────────────────────

add_action( 'wp_ajax_sgclabs_msd_apply', function() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $domain      = sanitize_text_field( wp_unslash( $_POST['domain']        ?? '' ) );
    $title       = sanitize_text_field( wp_unslash( $_POST['title']         ?? '' ) );
    $description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
    $focus_kw    = sanitize_text_field( wp_unslash( $_POST['focus_keyword'] ?? '' ) );

    $profiles     = sgclabs_msd_get_profiles();
    $profile      = $profiles[ $domain ] ?? null;
    if ( ! $profile ) wp_send_json_error( 'Unknown domain' );

    // Detect if this is local or remote
    $current_host = ltrim( wp_parse_url( home_url(), PHP_URL_HOST ), 'www.' );
    $target_host  = ltrim( $domain, 'www.' );

    if ( $current_host === $target_host ) {
        // Local apply
        $source = sgclabs_save_homepage_seo( $title, $description );
        if ( $focus_kw && function_exists( 'sgclabs_rm_is_active' ) && sgclabs_rm_is_active() ) {
            $front = (int) get_option( 'page_on_front' );
            if ( $front ) update_post_meta( $front, 'rank_math_focus_keyword', $focus_kw );
        }
        if ( function_exists( 'rocket_clean_home' ) ) rocket_clean_home();
        delete_transient( 'sgclabs_seo_homepage_cache' );
        wp_send_json_success( [ 'applied' => true, 'method' => 'local', 'source' => $source ] );
    }

    // Remote apply via REST API
    $token = $profile['token'] ?? '';
    if ( ! $token ) {
        wp_send_json_error( 'Token not set for ' . $domain . '. Go to Settings → Site Tokens and add it.' );
    }

    $push_result = sgclabs_msd_push_to_site( $profile['url'], $token, [
        'title'         => $title,
        'description'   => $description,
        'focus_keyword' => $focus_kw,
    ] );

    if ( ! empty( $push_result['error'] ) ) wp_send_json_error( $push_result['error'] );

    wp_send_json_success( [ 'applied' => true, 'method' => 'rest_api', 'domain' => $domain ] );
} );

// ── AJAX: Save token for a domain ─────────────────────────────────────────────

add_action( 'wp_ajax_sgclabs_msd_save_token', function() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $domain  = sanitize_text_field( wp_unslash( $_POST['domain'] ?? '' ) );
    $token   = sanitize_text_field( wp_unslash( $_POST['token']  ?? '' ) );

    $profiles = sgclabs_msd_get_profiles();
    if ( ! isset( $profiles[ $domain ] ) ) wp_send_json_error( 'Unknown domain' );

    $profiles[ $domain ]['token'] = $token;
    update_option( 'sgclabs_msd_profiles', $profiles );

    wp_send_json_success( [ 'domain' => $domain, 'token_set' => true ] );
} );

// ── Admin Page ────────────────────────────────────────────────────────────────

// ── AJAX: Debug — test AI connection directly ─────────────────────────────────

add_action( 'wp_ajax_sgclabs_msd_debug_ai', function() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $provider = sanitize_text_field( wp_unslash( $_POST['provider'] ?? 'deepseek_chat' ) );
    $debug    = [];

    // Step 1: Key check
    switch ( $provider ) {
        case 'deepseek_chat':
        case 'deepseek_reasoner':
            $raw_key = get_option( 'sgclabs_aichat_deepseek_key', '' );
            $key     = sgclabs_get_api_key( 'sgclabs_aichat_deepseek_key' );
            $debug['key_in_db']    = ! empty( $raw_key ) ? 'YES (encrypted)' : 'NO — not set';
            $debug['key_decrypted'] = ! empty( $key ) ? 'YES (len=' . strlen( $key ) . ')' : 'NO — decrypt failed';
            $debug['key_prefix']   = ! empty( $key ) ? substr( $key, 0, 6 ) . '...' : 'n/a';
            $endpoint = 'https://api.deepseek.com/chat/completions';
            $payload  = wp_json_encode( [
                'model'      => 'deepseek-chat',
                'messages'   => [ [ 'role' => 'user', 'content' => 'Reply with exactly: {"test":"ok"}' ] ],
                'max_tokens' => 30,
            ] );
            $headers  = [ 'Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json' ];
            break;
        case 'gemini':
            $raw_key = get_option( 'sgclabs_gemini_api_key', '' );
            $key     = sgclabs_get_api_key( 'sgclabs_gemini_api_key' );
            $debug['key_in_db']    = ! empty( $raw_key ) ? 'YES' : 'NO';
            $debug['key_decrypted'] = ! empty( $key ) ? 'YES (len=' . strlen( $key ) . ')' : 'NO';
            $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $key;
            $payload  = wp_json_encode( [ 'contents' => [ [ 'parts' => [ [ 'text' => 'Reply with exactly: {"test":"ok"}' ] ] ] ] ] );
            $headers  = [ 'Content-Type' => 'application/json' ];
            break;
        case 'claude':
            $raw_key = get_option( 'sgclabs_claude_api_key', '' );
            $key     = sgclabs_get_api_key( 'sgclabs_claude_api_key' );
            $debug['key_in_db']    = ! empty( $raw_key ) ? 'YES' : 'NO';
            $debug['key_decrypted'] = ! empty( $key ) ? 'YES (len=' . strlen( $key ) . ')' : 'NO';
            $endpoint = 'https://api.anthropic.com/v1/messages';
            $payload  = wp_json_encode( [ 'model' => 'claude-haiku-4-5-20251001', 'max_tokens' => 30, 'messages' => [ [ 'role' => 'user', 'content' => 'Reply with exactly: {"test":"ok"}' ] ] ] );
            $headers  = [ 'x-api-key' => $key, 'anthropic-version' => '2023-06-01', 'content-type' => 'application/json' ];
            break;
        default:
            wp_send_json_error( 'Unknown provider' );
            return;
    }

    // Step 2: HTTP connectivity check
    $debug['endpoint'] = $endpoint;

    if ( empty( $key ) ) {
        $debug['result'] = 'FAIL — API key empty or decrypt failed';
        wp_send_json_success( $debug );
        return;
    }

    // Step 3: Actual API call
    $resp = wp_remote_post( $endpoint, [
        'timeout' => 20,
        'headers' => $headers,
        'body'    => $payload,
        'sslverify' => false,
    ] );

    if ( is_wp_error( $resp ) ) {
        $debug['result']      = 'FAIL — wp_remote_post error';
        $debug['wp_error']    = $resp->get_error_message();
        $debug['suggestion']  = 'Bluehost may be blocking outgoing HTTPS to this API. Contact hosting support or use a proxy.';
        wp_send_json_success( $debug );
        return;
    }

    $http_code           = wp_remote_retrieve_response_code( $resp );
    $raw_body            = wp_remote_retrieve_body( $resp );
    $debug['http_code']  = $http_code;
    $debug['raw_body']   = substr( $raw_body, 0, 500 );

    if ( $http_code === 200 ) {
        $debug['result'] = 'SUCCESS — API connection working';
    } elseif ( $http_code === 401 || $http_code === 403 ) {
        $debug['result']     = 'FAIL — Invalid API key (HTTP ' . $http_code . ')';
        $debug['suggestion'] = 'Go to SGC Manager Pro → Settings → AI Providers → re-enter ' . $provider . ' API key';
    } elseif ( $http_code === 429 ) {
        $debug['result']     = 'FAIL — Rate limit exceeded (HTTP 429)';
        $debug['suggestion'] = 'Wait 1 minute and try again';
    } elseif ( $http_code === 0 || $http_code === '' ) {
        $debug['result']     = 'FAIL — No response (HTTP 0)';
        $debug['suggestion'] = 'Bluehost firewall blocking outgoing request to ' . $endpoint;
    } else {
        $debug['result']     = 'FAIL — HTTP ' . $http_code;
    }

    wp_send_json_success( $debug );
} );

// ── Admin Page ────────────────────────────────────────────────────────────────

function sgclabs_msd_render_page() {
    $profiles   = sgclabs_msd_get_profiles();
    $rm_active  = function_exists( 'sgclabs_rm_is_active' ) && sgclabs_rm_is_active();
    $rm_ver     = defined( 'RANK_MATH_VERSION' ) ? RANK_MATH_VERSION : '';
    $nonce      = wp_create_nonce( 'sgclabs_nonce' );
    $my_token   = sgclabs_msd_get_token();
    $my_host    = ltrim( wp_parse_url( home_url(), PHP_URL_HOST ), 'www.' );

    $has_claude   = (bool) get_option( 'sgclabs_claude_api_key' );
    $has_deepseek = (bool) get_option( 'sgclabs_aichat_deepseek_key' );
    $has_gemini   = (bool) get_option( 'sgclabs_gemini_api_key' );
    ?>
<div class="wrap" id="sgclabs-msd">
<style>
#sgclabs-msd{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}
.msd-top{display:flex;align-items:center;gap:10px;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid #ddd}
.msd-top h2{margin:0;font-size:20px}
.badge{font-size:11px;padding:2px 8px;border-radius:10px;font-weight:600}
.badge-rm{background:#d4edda;color:#155724}
.badge-no{background:#f8d7da;color:#721c24}
.badge-ver{background:#e8f4fd;color:#0c5460}
.msd-token-box{background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px}
.msd-token-box strong{display:block;margin-bottom:4px}
.msd-token-box code{background:#fff;padding:2px 8px;border-radius:4px;font-size:12px;border:1px solid #ddd;user-select:all;cursor:copy}
.msd-controls{display:flex;gap:10px;align-items:center;padding:10px 14px;background:#f9f9f9;border:1px solid #e0e0e0;border-radius:8px;margin-bottom:14px;flex-wrap:wrap}
.msd-controls select,.msd-controls button{font-size:13px}
.btn-primary{background:#0073aa;color:#fff;border:none;padding:7px 16px;border-radius:5px;cursor:pointer;font-weight:600}
.btn-primary:hover{background:#005177}
.btn-purple{background:#6f42c1;color:#fff;border:none;padding:7px 16px;border-radius:5px;cursor:pointer;font-weight:600}
.btn-purple:hover{background:#5930a8}
.domain-grid{display:flex;flex-direction:column;gap:12px}
.d-card{border:1px solid #ddd;border-radius:10px;overflow:hidden;background:#fff}
.d-card.done{border-color:#28a745}
.d-card.active{border-color:#ffc107}
.card-top{padding:10px 14px;display:flex;align-items:center;gap:8px;background:#fafafa;border-bottom:1px solid #eee}
.d-pill{font-size:12px;font-weight:700;padding:3px 10px;border-radius:12px}
.d-market{font-size:11px;color:#666;margin-left:8px;flex:1}
.this-site{font-size:10px;font-weight:700;background:#0073aa;color:#fff;padding:2px 7px;border-radius:8px;margin-left:auto}
.status-dot{width:9px;height:9px;border-radius:50%;background:#ddd}
.status-dot.scanning{background:#ffc107;animation:blink .7s ease-in-out infinite}
.status-dot.ok{background:#28a745}
.status-dot.err{background:#dc3545}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}
.card-body{padding:13px;display:grid;grid-template-columns:1fr 1fr;gap:10px}
.seo-f label{font-size:10px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:2px}
.cur{font-size:12px;background:#f5f5f5;border:1px solid #e0e0e0;border-radius:5px;padding:5px 8px;min-height:30px;line-height:1.4;word-break:break-word}
.ai-val{font-size:12px;background:#e8f5e9;border:1px solid #a5d6a7;border-radius:5px;padding:5px 8px;min-height:30px;line-height:1.4;word-break:break-word;display:none}
.ai-val.show{display:block}
.arr{font-size:11px;color:#bbb;text-align:center;padding:2px 0;display:none}
.full{grid-column:1/-1}
.issues{grid-column:1/-1;font-size:11px;color:#856404;background:#fff3cd;border:1px solid #ffeeba;border-radius:5px;padding:6px 10px;line-height:1.6;display:none}
.card-foot{padding:10px 14px;border-top:1px solid #eee;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.btn-sm{padding:5px 11px;border-radius:5px;font-size:12px;cursor:pointer;font-weight:500}
.btn-blue{background:#fff;border:1px solid #0073aa;color:#0073aa}
.btn-blue:hover{background:#e7f4ff}
.btn-violet{background:#fff;border:1px solid #6f42c1;color:#6f42c1;display:none}
.btn-violet.show{display:inline-block}
.btn-violet:hover{background:#f0eaff}
.btn-apply{background:#28a745;color:#fff;border:none;display:none;font-weight:700}
.btn-apply.show{display:inline-block}
.btn-apply:hover{background:#1e7e34}
.btn-apply.done{background:#155724;cursor:default}
.btn-apply.remote{background:#9c27b0}
.btn-apply.remote:hover{background:#7b1fa2}
.uniq{font-size:11px;color:#666;margin-left:auto}
.token-input{border:1px solid #ccc;border-radius:5px;padding:4px 8px;font-size:11px;font-family:monospace;width:200px}
.btn-save-tok{background:#fff;border:1px solid #666;color:#444;padding:4px 8px;border-radius:5px;font-size:11px;cursor:pointer}
.spin{display:inline-block;width:11px;height:11px;border:2px solid #ddd;border-top-color:#0073aa;border-radius:50%;animation:msd-spin .6s linear infinite;vertical-align:middle;margin-right:3px}
@keyframes msd-spin{to{transform:rotate(360deg)}}
.msd-log{margin-top:12px;background:#1e1e1e;color:#ccc;font-family:monospace;font-size:11px;padding:10px 13px;border-radius:8px;max-height:150px;overflow-y:auto;line-height:1.7;display:none}
.log-ok{color:#4caf50}.log-ai{color:#64b5f6}.log-warn{color:#ffb74d}.log-err{color:#ef5350}
</style>

<div class="msd-top">
    <h2>5-Domain SEO Differentiator</h2>
    <span class="badge badge-ver">v<?php echo SGCLABS_MSD_VERSION; ?></span>
    <span class="badge <?php echo $rm_active ? 'badge-rm' : 'badge-no'; ?>">
        RankMath <?php echo $rm_active ? 'active v' . esc_html( $rm_ver ) : 'not active'; ?>
    </span>
</div>

<div class="msd-token-box">
    <strong>This site token (<?php echo esc_html( $my_host ); ?>)</strong>
    Copy this token aur baaki 4 sites ke "Site Token" field mein paste karo:
    <code id="my-token"><?php echo esc_html( $my_token ); ?></code>
    &nbsp;<button onclick="navigator.clipboard.writeText('<?php echo esc_js($my_token); ?>').then(()=>this.textContent='Copied!')" style="border:none;background:none;cursor:pointer;font-size:12px;color:#856404">Copy</button>
    <br><small style="color:#666;margin-top:4px;display:block">REST endpoint: <code><?php echo esc_html( home_url( '/wp-json/sgclabs/v1/apply-seo' ) ); ?></code></small>
</div>

<div class="msd-controls">
    <label style="font-size:13px;font-weight:600">AI Provider:</label>
    <select id="msd-provider">
        <option value="auto">Auto (best available)</option>
        <?php if ( $has_deepseek ) : ?>
        <option value="deepseek_chat" selected>DeepSeek Chat (V3)</option>
        <option value="deepseek_reasoner">DeepSeek Reasoner (R1)</option>
        <?php endif; ?>
        <?php if ( $has_gemini ) : ?>
        <option value="gemini">Gemini 2.0 Flash</option>
        <?php endif; ?>
        <?php if ( $has_claude ) : ?>
        <option value="claude">Claude Haiku</option>
        <?php endif; ?>
    </select>
    <button class="btn-primary" onclick="msdFetchAll()">Fetch all 5 sites</button>
    <button class="btn-purple" id="btn-ai-all" style="display:none" onclick="msdAIAll()">Differentiate all with AI</button>
    <button class="btn-primary" id="btn-push-all" style="display:none;background:#9c27b0;border-color:#9c27b0" onclick="msdPushAll()">Push to all 5 sites</button>
    <button style="background:#fff;border:1px solid #666;color:#555;padding:7px 12px;border-radius:5px;font-size:12px;cursor:pointer;margin-left:auto" onclick="msdDebug()">Test AI connection</button>
    <span id="msd-status" style="font-size:12px;color:#666"></span>
</div>

<div class="msd-log" id="msd-log"></div>

<div class="domain-grid" id="msd-grid">
<?php foreach ( $profiles as $domain => $profile ) :
    $did      = preg_replace( '/[^a-z0-9]/', '_', $domain );
    $is_local = ltrim( $domain, 'www.' ) === $my_host;
?>
<div class="d-card" id="card-<?php echo esc_attr($did); ?>">
    <div class="card-top">
        <div class="status-dot" id="dot-<?php echo esc_attr($did); ?>"></div>
        <span class="d-pill" style="background:<?php echo esc_attr($profile['bg']); ?>;color:<?php echo esc_attr($profile['color']); ?>"><?php echo esc_html($domain); ?></span>
        <span class="d-market"><?php echo esc_html($profile['market']); ?></span>
        <?php if ( $is_local ) : ?><span class="this-site">This site</span><?php endif; ?>
    </div>
    <div class="card-body">
        <div class="seo-f">
            <label>Meta title <span id="tlen-<?php echo esc_attr($did); ?>" style="font-weight:400;color:#bbb"></span></label>
            <div class="cur" id="cur-title-<?php echo esc_attr($did); ?>">—</div>
            <div class="arr" id="arr-title-<?php echo esc_attr($did); ?>">&#8595; AI generated</div>
            <div class="ai-val" id="ai-title-<?php echo esc_attr($did); ?>"></div>
        </div>
        <div class="seo-f">
            <label>H1 (primary)</label>
            <div class="cur" id="cur-h1-<?php echo esc_attr($did); ?>">—</div>
            <div class="arr" id="arr-h1-<?php echo esc_attr($did); ?>">&#8595; AI generated</div>
            <div class="ai-val" id="ai-h1-<?php echo esc_attr($did); ?>"></div>
        </div>
        <div class="seo-f full">
            <label>Meta description <span id="dlen-<?php echo esc_attr($did); ?>" style="font-weight:400;color:#bbb"></span></label>
            <div class="cur" id="cur-desc-<?php echo esc_attr($did); ?>">—</div>
            <div class="arr" id="arr-desc-<?php echo esc_attr($did); ?>">&#8595; AI generated</div>
            <div class="ai-val" id="ai-desc-<?php echo esc_attr($did); ?>"></div>
        </div>
        <div class="seo-f">
            <label>H2 (supporting)</label>
            <div class="cur" id="cur-h2-<?php echo esc_attr($did); ?>">—</div>
            <div class="arr" id="arr-h2-<?php echo esc_attr($did); ?>">&#8595;</div>
            <div class="ai-val" id="ai-h2-<?php echo esc_attr($did); ?>"></div>
        </div>
        <div class="seo-f">
            <label>Focus keyword</label>
            <div class="cur" id="cur-kw-<?php echo esc_attr($did); ?>">—</div>
            <div class="ai-val" id="ai-kw-<?php echo esc_attr($did); ?>"></div>
        </div>
        <div class="issues" id="iss-<?php echo esc_attr($did); ?>"></div>
    </div>
    <div class="card-foot">
        <button class="btn-sm btn-blue" onclick="msdFetch('<?php echo esc_js($domain); ?>')">Fetch live</button>
        <button class="btn-sm btn-violet show" id="btn-ai-<?php echo esc_attr($did); ?>" onclick="msdAI('<?php echo esc_js($domain); ?>')">AI Differentiate</button>
        <button class="btn-sm btn-apply <?php echo $is_local ? '' : 'remote'; ?>" id="btn-apply-<?php echo esc_attr($did); ?>" onclick="msdApply('<?php echo esc_js($domain); ?>')">
            <?php echo $is_local ? 'Apply (this site)' : 'Push via REST'; ?>
        </button>
        <?php if ( ! $is_local ) : ?>
        <input type="text" class="token-input" id="tok-<?php echo esc_attr($did); ?>" placeholder="Paste site token" value="<?php echo esc_attr($profile['token'] ?? ''); ?>">
        <button class="btn-save-tok" onclick="msdSaveToken('<?php echo esc_js($domain); ?>')">Save token</button>
        <?php endif; ?>
        <span class="uniq" id="uniq-<?php echo esc_attr($did); ?>"></span>
    </div>
</div>
<?php endforeach; ?>
</div>

<script>
var MSD={nonce:'<?php echo esc_js($nonce); ?>',ajaxUrl:'<?php echo esc_js(admin_url('admin-ajax.php')); ?>',profiles:<?php echo wp_json_encode($profiles); ?>,live:{},ai:{}};

function did(d){return d.replace(/[^a-z0-9]/g,'_');}
function msdLog(msg,cls){var l=document.getElementById('msd-log');l.style.display='block';var el=document.createElement('div');if(cls)el.className=cls;el.textContent='['+new Date().toLocaleTimeString()+'] '+msg;l.appendChild(el);l.scrollTop=l.scrollHeight;}
function dot(d,s){var el=document.getElementById('dot-'+did(d));if(el)el.className='status-dot'+(s?' '+s:'');}
function sleep(ms){return new Promise(r=>setTimeout(r,ms));}

function msdFetch(domain){
    var id=did(domain);
    dot(domain,'scanning');
    document.getElementById('cur-title-'+id).innerHTML='<span class="spin"></span>Fetching...';
    msdLog('Fetching '+domain+'...','log-ai');
    jQuery.post(MSD.ajaxUrl,{action:'sgclabs_msd_fetch_domain',nonce:MSD.nonce,domain:domain},function(r){
        if(!r.success){msdLog('Error: '+r.data,'log-err');dot(domain,'err');return;}
        var d=r.data; MSD.live[domain]=d;
        document.getElementById('cur-title-'+id).textContent=d.title||'(empty)';
        document.getElementById('cur-desc-'+id).textContent=d.description||'(not set)';
        document.getElementById('cur-h1-'+id).textContent=(d.h1&&d.h1.length)?d.h1.join(' | '):'(not found)';
        document.getElementById('cur-h2-'+id).textContent=(d.h2&&d.h2.length)?d.h2.slice(0,2).join(' | '):'(not found)';
        var tl=document.getElementById('tlen-'+id),dl=document.getElementById('dlen-'+id);
        if(tl)tl.textContent='('+((d.title||'').length)+' chars)';
        if(dl)dl.textContent='('+((d.description||'').length)+' chars)';
        if(d.issues&&d.issues.length){var el=document.getElementById('iss-'+id);el.textContent='Issues: '+d.issues.join(' · ');el.style.display='block';}
        dot(domain,'ok');
        msdLog(domain+' fetched — '+(d.issues?d.issues.length:0)+' issue(s).','log-ok');
    }).fail(function(){msdLog('AJAX fail for '+domain,'log-err');dot(domain,'err');});
}

function msdFetchAll(){
    var all=Object.keys(MSD.profiles),i=0;
    function next(){if(i>=all.length){document.getElementById('btn-ai-all').style.display='inline-block';msdLog('All 5 fetched. Ready for AI.','log-ok');return;}msdFetch(all[i++]);setTimeout(next,1300);}
    next();
}

function msdAI(domain){
    var id=did(domain),prov=document.getElementById('msd-provider').value;
    dot(domain,'scanning');
    document.getElementById('btn-ai-'+id).innerHTML='<span class="spin"></span>AI...';
    msdLog('AI ('+prov+') generating for '+domain+'...','log-ai');
    jQuery.post(MSD.ajaxUrl,{action:'sgclabs_msd_ai_differentiate',nonce:MSD.nonce,domain:domain,provider:prov,live_data:JSON.stringify(MSD.live[domain]||{})},function(r){
        document.getElementById('btn-ai-'+id).textContent='AI Differentiate';
        if(!r.success){msdLog('AI error: '+r.data,'log-err');dot(domain,'err');return;}
        var ai=r.data.ai; MSD.ai[domain]=ai;
        var map={title:ai.title,h1:ai.h1,desc:ai.description,h2:ai.h2,kw:ai.focus_keyword};
        Object.keys(map).forEach(function(f){if(!map[f])return;var el=document.getElementById('ai-'+f+'-'+id),arr=document.getElementById('arr-'+f+'-'+id);if(el){el.textContent=map[f];el.classList.add('show');}if(arr)arr.style.display='block';});
        document.getElementById('btn-apply-'+id).classList.add('show');
        document.getElementById('card-'+id).classList.add('done');
        dot(domain,'ok');
        if(ai.rationale)document.getElementById('uniq-'+id).textContent=ai.rationale;
        msdLog(domain+' differentiated. KW: '+ai.focus_keyword,'log-ok');
        // Show push-all if all done
        if(Object.keys(MSD.ai).length===Object.keys(MSD.profiles).length)
            document.getElementById('btn-push-all').style.display='inline-block';
    }).fail(function(){msdLog('AJAX fail AI','log-err');dot(domain,'err');document.getElementById('btn-ai-'+id).textContent='AI Differentiate';});
}

async function msdAIAll(){
    var all=Object.keys(MSD.profiles);
    for(var i=0;i<all.length;i++){msdAI(all[i]);await sleep(3800);}
    msdLog('All 5 AI differentiated.','log-ok');
}

function msdApply(domain){
    var id=did(domain),ai=MSD.ai[domain];
    if(!ai){alert('Run AI first for '+domain);return;}
    var label=document.getElementById('btn-apply-'+id).textContent;
    var isRemote=label.indexOf('REST')>=0;
    if(!confirm('Apply AI SEO to '+domain+'?\n\n'+(isRemote?'Will push via REST API to remote site.':'Will write to RankMath / SEO keys on this site.')))return;
    msdLog('Applying to '+domain+'...','log-ai');
    jQuery.post(MSD.ajaxUrl,{action:'sgclabs_msd_apply',nonce:MSD.nonce,domain:domain,title:ai.title,description:ai.description,focus_keyword:ai.focus_keyword},function(r){
        if(!r.success){msdLog('Apply error: '+r.data,'log-err');return;}
        var btn=document.getElementById('btn-apply-'+id);
        btn.textContent='Applied!';btn.classList.add('done');
        msdLog(domain+' applied via '+(r.data.method||'?')+'.','log-ok');
    }).fail(function(){msdLog('Apply AJAX fail','log-err');});
}

async function msdPushAll(){
    if(!confirm('Push AI SEO to ALL 5 sites via REST API?\n\nMake sure site tokens are set for all remote sites.'))return;
    var all=Object.keys(MSD.profiles);
    for(var i=0;i<all.length;i++){msdApply(all[i]);await sleep(1500);}
}

function msdDebug(){
    var prov=document.getElementById('msd-provider').value||'deepseek_chat';
    msdLog('Testing AI connection: '+prov+'...','log-ai');
    jQuery.post(MSD.ajaxUrl,{action:'sgclabs_msd_debug_ai',nonce:MSD.nonce,provider:prov},function(r){
        if(!r.success){msdLog('Debug error: '+r.data,'log-err');return;}
        var d=r.data;
        msdLog('=== AI CONNECTION REPORT ===','log-ai');
        Object.keys(d).forEach(function(k){
            var cls=(k==='result')?(d[k].indexOf('SUCCESS')>=0?'log-ok':'log-err'):(k==='suggestion'?'log-warn':'');
            msdLog(k+': '+d[k],cls);
        });
        msdLog('=== END REPORT ===','log-ai');
    }).fail(function(){msdLog('Debug AJAX failed — check browser console','log-err');});
}

function msdSaveToken(domain){
    var id=did(domain),tok=document.getElementById('tok-'+id);
    if(!tok)return;
    jQuery.post(MSD.ajaxUrl,{action:'sgclabs_msd_save_token',nonce:MSD.nonce,domain:domain,token:tok.value},function(r){
        if(r.success)msdLog('Token saved for '+domain,'log-ok');
        else msdLog('Token save error: '+r.data,'log-err');
    });
}
</script>
</div>
<?php
}

add_action( 'admin_menu', function() {
    add_submenu_page(
        'sgclabs-manager',
        '5-Domain SEO Differentiator',
        '5-Domain SEO',
        'manage_options',
        'sgclabs-msd',
        'sgclabs_msd_render_page'
    );
}, 30 );
