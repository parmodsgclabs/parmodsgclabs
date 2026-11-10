<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SGC LABS® — Unified AI Provider System v6.7.4
 * Task Router: Har function ke liye alag AI provider
 * Retry/backoff, 429 handling, error logging, response validation.
 */

function sgclabs_ai_providers(): array {
    return [
        'claude'   => ['label'=>'🧠 Claude (Anthropic)', 'option_key'=>'sgclabs_claude_api_key',       'model'=>'claude-haiku-4-5-20251001',  'placeholder'=>'sk-ant-api03-...', 'docs'=>'https://console.anthropic.com/settings/keys',    'timeout'=>60],
        'openai'   => ['label'=>'🟢 OpenAI (GPT-4o)',    'option_key'=>'sgclabs_aichat_openai_key',    'model'=>'gpt-4o',                     'placeholder'=>'sk-proj-...',      'docs'=>'https://platform.openai.com/api-keys',            'timeout'=>60],
        'gemini'   => ['label'=>'💎 Google Gemini',      'option_key'=>'sgclabs_aichat_gemini_key',    'model'=>'gemini-2.5-flash',           'placeholder'=>'AIzaSy...',        'docs'=>'https://aistudio.google.com/app/apikey',          'timeout'=>45],
        'mistral'  => ['label'=>'🌀 Mistral AI',         'option_key'=>'sgclabs_mistral_api_key',      'model'=>'mistral-large-latest',       'placeholder'=>'your-mistral-key', 'docs'=>'https://console.mistral.ai/api-keys/',            'timeout'=>45],
        'deepseek' => ['label'=>'🌊 DeepSeek',           'option_key'=>'sgclabs_aichat_deepseek_key', 'model'=>'deepseek-chat',              'placeholder'=>'sk-...',           'docs'=>'https://platform.deepseek.com/api-keys',          'timeout'=>45],
    ];
}

// ── TASK DEFINITIONS ──────────────────────────────────────────
// Har task ke liye: default provider + cost tier + description
function sgclabs_ai_tasks(): array {
    return [
        'seo_title'       => ['label'=>'📝 SEO Title',           'default'=>'gemini',   'tier'=>'free',    'desc'=>'Product SEO title generate karo'],
        'meta_desc'       => ['label'=>'📄 Meta Description',    'default'=>'gemini',   'tier'=>'free',    'desc'=>'Meta description generate karo'],
        'focus_keyword'   => ['label'=>'🔑 Focus Keyword',       'default'=>'gemini',   'tier'=>'free',    'desc'=>'Focus keyword suggest karo'],
        'image_alt'       => ['label'=>'🖼️ Image ALT Text',      'default'=>'gemini',   'tier'=>'free',    'desc'=>'Image ALT text generate karo'],
        'short_desc'      => ['label'=>'📋 Short Description',   'default'=>'deepseek', 'tier'=>'cheap',   'desc'=>'Short product description'],
        'long_desc'       => ['label'=>'📖 Long Description',    'default'=>'claude',   'tier'=>'quality', 'desc'=>'Full product description (best quality)'],
        'price_compare'   => ['label'=>'💰 Price Comparison',    'default'=>'deepseek', 'tier'=>'cheap',   'desc'=>'Competitor price estimation'],
        'quote_reply'     => ['label'=>'💬 Quote Reply Email',   'default'=>'claude',   'tier'=>'quality', 'desc'=>'Professional quote reply email'],
        'schema_json'     => ['label'=>'🏷️ Schema/JSON-LD',      'default'=>'deepseek', 'tier'=>'cheap',   'desc'=>'Structured JSON schema generate karo'],
        'multilingual'    => ['label'=>'🌍 Multilingual SEO',    'default'=>'gemini',   'tier'=>'free',    'desc'=>'Multi-language SEO content'],
        'competitor_ai'   => ['label'=>'🔍 Competitor Analysis', 'default'=>'deepseek', 'tier'=>'cheap',   'desc'=>'Competitor analysis'],
        'ai_chatbot'      => ['label'=>'🤖 AI Chatbot',          'default'=>'gemini',   'tier'=>'free',    'desc'=>'Live chat responses'],
        'bulk_seo'        => ['label'=>'⚡ Bulk SEO Engine',     'default'=>'gemini',   'tier'=>'free',    'desc'=>'Bulk SEO automation (7000 products)'],
    ];
}

// ── GET PROVIDER FOR TASK ─────────────────────────────────────
function sgclabs_ai_task_provider( string $task ): string {
    $tasks    = sgclabs_ai_tasks();
    $default  = $tasks[ $task ]['default'] ?? 'gemini';
    $saved    = get_option( "sgclabs_task_provider_{$task}", '' );
    $provider = $saved ?: $default;
    $providers = sgclabs_ai_providers();

    // Check agar configured hai
    if ( isset( $providers[ $provider ] ) && ! empty( sgclabs_ai_get_provider_key( $provider ) ) ) {
        return $provider;
    }

    // Fallback chain: gemini → deepseek → claude → any configured
    $fallback_chain = [ 'gemini', 'deepseek', 'claude', 'openai', 'mistral' ];
    foreach ( $fallback_chain as $fb ) {
        if ( isset( $providers[ $fb ] ) && ! empty( sgclabs_ai_get_provider_key( $fb ) ) ) {
            return $fb;
        }
    }

    return $provider; // Return anyway, error handle hoga
}

// ── TASK-SPECIFIC AI CALL ─────────────────────────────────────
// Yeh function use karo jab specific task ke liye call karna ho
function sgclabs_ai_call_for_task( string $task, string $prompt, int $max_tokens = 800, string $system = '' ) {
    $provider = sgclabs_ai_task_provider( $task );
    return sgclabs_ai_call_with_provider( $provider, $prompt, $max_tokens, $system );
}

// ── CALL WITH SPECIFIC PROVIDER ───────────────────────────────
function sgclabs_ai_call_with_provider( string $provider, string $prompt, int $max_tokens = 800, string $system = '' ) {
    $providers = sgclabs_ai_providers();
    $api_key   = sgclabs_ai_get_provider_key( $provider );
    $label     = $providers[ $provider ]['label'] ?? $provider;

    if ( empty( $api_key ) ) {
        return new WP_Error( 'sgclabs_no_api_key', "No API key for {$label}. Add in SGC LABS → AI Center." );
    }
    if ( sgclabs_ai_is_rate_limited( $provider ) ) {
        // Try fallback provider
        $fallback = sgclabs_ai_get_fallback_provider( $provider );
        if ( $fallback ) {
            $fb_key = sgclabs_ai_get_provider_key( $fallback );
            if ( ! empty( $fb_key ) && ! sgclabs_ai_is_rate_limited( $fallback ) ) {
                sgclabs_ai_log_error( $provider, "Rate limited — falling back to {$fallback}", 'fallback' );
                return sgclabs_ai_call_with_provider( $fallback, $prompt, $max_tokens, $system );
            }
        }
        return new WP_Error( 'sgclabs_rate_limited', "{$label} rate limited. Wait a moment." );
    }

    $waits      = [ 0, 3, 8 ];
    $last_error = null;

    for ( $attempt = 0; $attempt < 3; $attempt++ ) {
        if ( $attempt > 0 ) sleep( $waits[ $attempt ] );

        $result = sgclabs_ai_dispatch( $provider, $prompt, $max_tokens, $system, $api_key );

        if ( is_string( $result ) && strlen( trim( $result ) ) >= 5 ) {
            return $result;
        }

        if ( is_wp_error( $result ) ) {
            $code = $result->get_error_code();
            $msg  = $result->get_error_message();

            if ( $code === 'sgclabs_rl_429' ) {
                $retry_after = (int)( $result->get_error_data()['retry_after'] ?? 60 );
                sgclabs_ai_set_rate_limit( $provider, max( 30, $retry_after ) );
                sgclabs_ai_log_error( $provider, "429 rate-limited {$retry_after}s", "attempt:{$attempt}" );

                // Auto-fallback on rate limit
                $fallback = sgclabs_ai_get_fallback_provider( $provider );
                if ( $fallback ) {
                    $fb_key = sgclabs_ai_get_provider_key( $fallback );
                    if ( ! empty( $fb_key ) ) {
                        sgclabs_ai_log_error( $provider, "Auto-fallback to {$fallback}", 'rate_limit_fallback' );
                        return sgclabs_ai_call_with_provider( $fallback, $prompt, $max_tokens, $system );
                    }
                }
                return new WP_Error( 'sgclabs_rate_limited', "{$label} rate limit. Retry in {$retry_after}s." );
            }

            if ( $code === 'sgclabs_auth' ) {
                sgclabs_ai_log_error( $provider, $msg, 'auth_error' );
                return $result;
            }

            $last_error = $result;
            sgclabs_ai_log_error( $provider, "Attempt " . ( $attempt + 1 ) . " failed: {$msg}", "will_retry" );
        }
    }

    sgclabs_ai_log_error( $provider, 'All 3 attempts failed', 'final' );
    return $last_error ?: new WP_Error( 'sgclabs_failed', 'AI call failed after 3 attempts.' );
}

// ── FALLBACK CHAIN ────────────────────────────────────────────
function sgclabs_ai_get_fallback_provider( string $current ): string {
    $chain = [
        'gemini'   => 'deepseek',
        'deepseek' => 'gemini',
        'claude'   => 'gemini',
        'openai'   => 'deepseek',
        'mistral'  => 'gemini',
    ];
    $fallback = $chain[ $current ] ?? '';

    // Check configured hai
    if ( $fallback && ! empty( sgclabs_ai_get_provider_key( $fallback ) ) ) {
        return $fallback;
    }

    // Any other configured provider
    foreach ( sgclabs_ai_providers() as $pid => $p ) {
        if ( $pid !== $current && ! empty( sgclabs_ai_get_provider_key( $pid ) ) ) {
            return $pid;
        }
    }
    return '';
}

function sgclabs_ai_active_provider(): string {
    $saved     = get_option( 'sgclabs_ai_seo_provider', '' );
    $providers = sgclabs_ai_providers();
    if ( $saved && isset( $providers[ $saved ] ) ) return $saved;
    foreach ( $providers as $id => $p ) {
        if ( ! empty( sgclabs_get_api_key( $p['option_key'] ) ) ) return $id;
    }
    return 'claude';
}

function sgclabs_ai_get_provider_key( string $provider ): string {
    $providers = sgclabs_ai_providers();
    if ( ! isset( $providers[ $provider ] ) ) return '';
    return sgclabs_get_api_key( $providers[ $provider ]['option_key'] );
}

function sgclabs_ai_any_configured(): bool {
    foreach ( sgclabs_ai_providers() as $p ) {
        if ( ! empty( sgclabs_get_api_key( $p['option_key'] ) ) ) return true;
    }
    return false;
}

// ── Error logger ──────────────────────────────────────────────
function sgclabs_ai_log_error( string $provider, string $error, string $context = '' ): void {
    $log = (array) get_option( 'sgclabs_ai_error_log', [] );
    array_unshift( $log, [
        'time'     => current_time( 'mysql' ),
        'provider' => $provider,
        'error'    => substr( $error, 0, 300 ),
        'context'  => substr( $context, 0, 100 ),
    ] );
    update_option( 'sgclabs_ai_error_log', array_slice( $log, 0, 100 ) );
    error_log( "[SGC LABS AI] [{$provider}] {$error}" );
}

// ── Rate limit tracker ────────────────────────────────────────
function sgclabs_ai_is_rate_limited( string $provider ): bool {
    return (bool) get_transient( "sgclabs_ai_rl_{$provider}" );
}
function sgclabs_ai_set_rate_limit( string $provider, int $secs = 60 ): void {
    set_transient( "sgclabs_ai_rl_{$provider}", time() + $secs, $secs + 10 );
}

// ── MAIN: Default AI call (backward compatible) ───────────────
function sgclabs_ai_call( string $prompt, int $max_tokens = 800, string $system = '' ) {
    $provider = sgclabs_ai_active_provider();
    return sgclabs_ai_call_with_provider( $provider, $prompt, $max_tokens, $system );
}

function sgclabs_ai_dispatch( string $provider, string $prompt, int $max_tokens, string $system, string $api_key ) {
    switch ( $provider ) {
        case 'claude':   return sgclabs_ai_call_claude( $prompt, $max_tokens, $system, $api_key );
        case 'openai':   return sgclabs_ai_call_openai( $prompt, $max_tokens, $system, $api_key );
        case 'gemini':   return sgclabs_ai_call_gemini( $prompt, $max_tokens, $system, $api_key );
        case 'mistral':  return sgclabs_ai_call_mistral( $prompt, $max_tokens, $system, $api_key );
        case 'deepseek': return sgclabs_ai_call_deepseek( $prompt, $max_tokens, $system, $api_key );
        default:         return new WP_Error( 'sgclabs_unknown_provider', "Unknown provider: {$provider}" );
    }
}

// ── CLAUDE ────────────────────────────────────────────────────
function sgclabs_ai_call_claude( string $prompt, int $max_tokens, string $system, string $api_key ) {
    $body = [ 'model' => 'claude-haiku-4-5-20251001', 'max_tokens' => min( $max_tokens, 8192 ), 'messages' => [ [ 'role' => 'user', 'content' => $prompt ] ] ];
    if ( $system ) $body['system'] = $system;
    $r = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
        'timeout' => 60,
        'headers' => [ 'Content-Type' => 'application/json', 'x-api-key' => $api_key, 'anthropic-version' => '2023-06-01' ],
        'body'    => wp_json_encode( $body ),
    ] );
    if ( is_wp_error( $r ) ) return $r;
    $code = (int) wp_remote_retrieve_response_code( $r );
    $b    = json_decode( wp_remote_retrieve_body( $r ), true );
    if ( $code === 429 ) { $e = new WP_Error( 'sgclabs_rl_429', 'RL' ); $e->add_data( [ 'retry_after' => (int)( wp_remote_retrieve_header( $r, 'retry-after' ) ?: 60 ) ] ); return $e; }
    if ( $code === 401 || $code === 403 ) return new WP_Error( 'sgclabs_auth', 'Claude: Invalid API key' );
    if ( isset( $b['error'] ) ) return new WP_Error( 'claude_error', 'Claude: ' . ( $b['error']['message'] ?? 'error' ) );
    $text = trim( $b['content'][0]['text'] ?? '' );
    return $text !== '' ? $text : new WP_Error( 'claude_empty', 'Claude empty response' );
}

// ── OPENAI ────────────────────────────────────────────────────
function sgclabs_ai_call_openai( string $prompt, int $max_tokens, string $system, string $api_key ) {
    $msgs = $system ? [ [ 'role' => 'system', 'content' => $system ] ] : [];
    $msgs[] = [ 'role' => 'user', 'content' => $prompt ];
    $r = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
        'timeout' => 60,
        'headers' => [ 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $api_key ],
        'body'    => wp_json_encode( [ 'model' => 'gpt-4o', 'max_tokens' => $max_tokens, 'messages' => $msgs ] ),
    ] );
    if ( is_wp_error( $r ) ) return $r;
    $code = (int) wp_remote_retrieve_response_code( $r );
    $b    = json_decode( wp_remote_retrieve_body( $r ), true );
    if ( $code === 429 ) { $e = new WP_Error( 'sgclabs_rl_429', 'RL' ); $e->add_data( [ 'retry_after' => (int)( wp_remote_retrieve_header( $r, 'retry-after' ) ?: 60 ) ] ); return $e; }
    if ( $code === 401 ) return new WP_Error( 'sgclabs_auth', 'OpenAI: Invalid API key' );
    if ( isset( $b['error'] ) ) return new WP_Error( 'openai_error', 'OpenAI: ' . ( $b['error']['message'] ?? 'error' ) );
    $text = trim( $b['choices'][0]['message']['content'] ?? '' );
    return $text !== '' ? $text : new WP_Error( 'openai_empty', 'OpenAI empty response' );
}

// ── GEMINI ────────────────────────────────────────────────────
function sgclabs_ai_call_gemini( string $prompt, int $max_tokens, string $system, string $api_key ) {
    $url  = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . urlencode( $api_key );
    $body = [ 'contents' => [ [ 'role' => 'user', 'parts' => [ [ 'text' => $prompt ] ] ] ], 'generationConfig' => [ 'maxOutputTokens' => $max_tokens, 'temperature' => 0.7 ] ];
    if ( $system ) $body['system_instruction'] = [ 'parts' => [ [ 'text' => $system ] ] ];
    $r = wp_remote_post( $url, [ 'timeout' => 45, 'headers' => [ 'Content-Type' => 'application/json' ], 'body' => wp_json_encode( $body ) ] );
    if ( is_wp_error( $r ) ) return $r;
    $code = (int) wp_remote_retrieve_response_code( $r );
    $b    = json_decode( wp_remote_retrieve_body( $r ), true );
    if ( $code === 429 ) { $e = new WP_Error( 'sgclabs_rl_429', 'RL' ); $e->add_data( [ 'retry_after' => 60 ] ); return $e; }
    if ( $code === 401 || $code === 403 ) return new WP_Error( 'sgclabs_auth', 'Gemini: Invalid API key' );
    if ( isset( $b['error'] ) ) return new WP_Error( 'gemini_error', 'Gemini: ' . ( $b['error']['message'] ?? 'error' ) );
    $text = trim( $b['candidates'][0]['content']['parts'][0]['text'] ?? '' );
    return $text !== '' ? $text : new WP_Error( 'gemini_empty', 'Gemini empty response' );
}

// ── MISTRAL ───────────────────────────────────────────────────
function sgclabs_ai_call_mistral( string $prompt, int $max_tokens, string $system, string $api_key ) {
    $msgs = $system ? [ [ 'role' => 'system', 'content' => $system ] ] : [];
    $msgs[] = [ 'role' => 'user', 'content' => $prompt ];
    $r = wp_remote_post( 'https://api.mistral.ai/v1/chat/completions', [
        'timeout' => 45,
        'headers' => [ 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $api_key ],
        'body'    => wp_json_encode( [ 'model' => 'mistral-large-latest', 'max_tokens' => $max_tokens, 'messages' => $msgs ] ),
    ] );
    if ( is_wp_error( $r ) ) return $r;
    $code = (int) wp_remote_retrieve_response_code( $r );
    $b    = json_decode( wp_remote_retrieve_body( $r ), true );
    if ( $code === 429 ) { $e = new WP_Error( 'sgclabs_rl_429', 'RL' ); $e->add_data( [ 'retry_after' => 60 ] ); return $e; }
    if ( $code === 401 ) return new WP_Error( 'sgclabs_auth', 'Mistral: Invalid API key' );
    if ( isset( $b['error'] ) ) return new WP_Error( 'mistral_error', 'Mistral: ' . ( $b['error']['message'] ?? 'error' ) );
    $text = trim( $b['choices'][0]['message']['content'] ?? '' );
    return $text !== '' ? $text : new WP_Error( 'mistral_empty', 'Mistral empty response' );
}

// ── DEEPSEEK ──────────────────────────────────────────────────
function sgclabs_ai_call_deepseek( string $prompt, int $max_tokens, string $system, string $api_key ) {
    $msgs = $system ? [ [ 'role' => 'system', 'content' => $system ] ] : [];
    $msgs[] = [ 'role' => 'user', 'content' => $prompt ];
    $r = wp_remote_post( 'https://api.deepseek.com/v1/chat/completions', [
        'timeout' => 60,
        'headers' => [ 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $api_key, 'Accept' => 'application/json' ],
        'body'    => wp_json_encode( [ 'model' => 'deepseek-chat', 'max_tokens' => $max_tokens, 'temperature' => 0.7, 'messages' => $msgs ] ),
    ] );
    if ( is_wp_error( $r ) ) return $r;
    $code = (int) wp_remote_retrieve_response_code( $r );
    $b    = json_decode( wp_remote_retrieve_body( $r ), true );
    if ( $code === 429 ) { $e = new WP_Error( 'sgclabs_rl_429', 'RL' ); $e->add_data( [ 'retry_after' => (int)( wp_remote_retrieve_header( $r, 'retry-after' ) ?: 60 ) ] ); return $e; }
    if ( $code === 401 ) return new WP_Error( 'sgclabs_auth', 'DeepSeek: Invalid API key. Get from platform.deepseek.com' );
    if ( $code === 402 ) return new WP_Error( 'sgclabs_auth', 'DeepSeek: Insufficient balance. Top up at platform.deepseek.com' );
    if ( isset( $b['error'] ) ) return new WP_Error( 'deepseek_error', 'DeepSeek: ' . ( $b['error']['message'] ?? 'Unknown error' ) );
    $text = trim( $b['choices'][0]['message']['content'] ?? '' );
    return $text !== '' ? $text : new WP_Error( 'deepseek_empty', 'DeepSeek returned empty response' );
}

// ── Settings save ─────────────────────────────────────────────
function sgclabs_ai_save_provider_settings(): void {
    if ( isset( $_POST['sgclabs_ai_seo_provider'] ) ) {
        $chosen = sanitize_key( $_POST['sgclabs_ai_seo_provider'] );
        if ( array_key_exists( $chosen, sgclabs_ai_providers() ) ) update_option( 'sgclabs_ai_seo_provider', $chosen );
    }
    foreach ( sgclabs_ai_providers() as $pid => $p ) {
        $field = "sgclabs_ai_key_{$pid}";
        if ( ! empty( $_POST[ $field ] ) ) {
            $raw = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
            if ( strpos( $raw, '...' ) === false ) sgclabs_save_api_key( $p['option_key'], $raw );
        }
    }
    // Save task routing
    foreach ( array_keys( sgclabs_ai_tasks() ) as $task ) {
        $field = "sgclabs_task_provider_{$task}";
        if ( isset( $_POST[ $field ] ) ) {
            $val = sanitize_key( $_POST[ $field ] );
            if ( array_key_exists( $val, sgclabs_ai_providers() ) ) {
                update_option( $field, $val );
            }
        }
    }
}

// ── AJAX: Save task routing ───────────────────────────────────
add_action( 'wp_ajax_sgclabs_save_task_routing', function() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $tasks    = sgclabs_ai_tasks();
    $providers = array_keys( sgclabs_ai_providers() );
    $saved    = 0;

    foreach ( array_keys( $tasks ) as $task ) {
        $val = sanitize_key( $_POST[ "task_{$task}" ] ?? '' );
        if ( in_array( $val, $providers, true ) ) {
            update_option( "sgclabs_task_provider_{$task}", $val );
            $saved++;
        }
    }
    wp_send_json_success( "✅ {$saved} task routes saved!" );
} );

// ── AJAX: Test provider ───────────────────────────────────────
add_action( 'wp_ajax_sgclabs_test_ai_provider', function() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $provider = sanitize_key( $_POST['provider'] ?? '' );
    $start    = microtime( true );
    $result   = sgclabs_ai_call_with_provider( $provider, 'Reply with exactly: "SGC LABS AI test OK"', 30, '' );
    $ms       = round( ( microtime( true ) - $start ) * 1000 );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }
    wp_send_json_success( [ 'response' => $result, 'ms' => $ms ] );
} );

// ── Settings HTML — updated with Task Router ──────────────────
function sgclabs_ai_render_provider_settings_html(): void {
    $providers = sgclabs_ai_providers();
    $active    = sgclabs_ai_active_provider();
    $log       = (array) get_option( 'sgclabs_ai_error_log', [] );
    $tasks     = sgclabs_ai_tasks();
    $nonce     = wp_create_nonce( 'sgclabs_nonce' );

    // Provider cards
    ?>
    <tr><th scope="row" style="vertical-align:top;padding-top:12px">🤖 Default AI Provider</th><td>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;max-width:620px;margin-bottom:14px">
    <?php foreach ( $providers as $pid => $p ):
        $key_set = ! empty( sgclabs_ai_get_provider_key( $pid ) );
        $is_active = $active === $pid;
        $is_rl = (bool) get_transient( "sgclabs_ai_rl_{$pid}" );
    ?>
    <label style="display:flex;align-items:flex-start;gap:8px;padding:10px 12px;cursor:pointer;border-radius:8px;border:2px solid <?php echo $is_active ? '#16a34a' : '#e2e8f0'; ?>;background:<?php echo $is_active ? '#f0fff4' : '#fff'; ?>">
        <input type="radio" name="sgclabs_ai_seo_provider" value="<?php echo esc_attr( $pid ); ?>" <?php checked( $active, $pid ); ?> style="margin-top:2px;flex-shrink:0">
        <div>
            <div style="font-weight:700;font-size:13px"><?php echo esc_html( $p['label'] ); ?></div>
            <code style="font-size:10px;background:#f1f5f9;padding:1px 4px;border-radius:3px;color:#475569"><?php echo esc_html( $p['model'] ); ?></code>
            <div style="margin-top:3px;font-size:10px">
                <?php if ( $is_rl ): ?>
                    <span style="color:#dc2626;font-weight:600">⏳ Rate limited</span>
                <?php elseif ( $key_set ): ?>
                    <span style="color:#16a34a;font-weight:600">✅ Key saved</span>
                    <button type="button" onclick="sgcTestProvider('<?php echo $pid; ?>')"
                        style="margin-left:4px;background:none;border:1px solid #d1d5db;border-radius:4px;padding:1px 6px;font-size:10px;cursor:pointer;color:#6b7280;">
                        Test
                    </button>
                <?php else: ?>
                    <span style="color:#e74c3c">⚠️ No key</span>
                <?php endif; ?>
            </div>
            <div id="sgc-test-<?php echo $pid; ?>" style="font-size:10px;margin-top:2px;display:none;"></div>
        </div>
    </label>
    <?php endforeach; ?>
    </div></td></tr>

    <?php foreach ( $providers as $pid => $p ):
        $stored    = sgclabs_ai_get_provider_key( $pid );
        $is_active = $active === $pid;
    ?>
    <tr style="<?php echo $is_active ? 'background:#f0fff4' : ''; ?>">
        <th scope="row"><?php echo esc_html( $p['label'] ); ?> Key
            <?php if ( $is_active ) echo '<span style="background:#16a34a;color:#fff;font-size:9px;padding:1px 5px;border-radius:3px;font-weight:700;margin-left:4px">DEFAULT</span>'; ?>
        </th>
        <td>
            <input type="password" name="<?php echo esc_attr( "sgclabs_ai_key_{$pid}" ); ?>"
                value="<?php echo $stored ? esc_attr( substr( $stored, 0, 12 ) . '...' ) : ''; ?>"
                placeholder="<?php echo esc_attr( $p['placeholder'] ); ?>"
                class="regular-text" autocomplete="new-password">
            <?php if ( $stored ) echo '<span style="color:#16a34a;font-size:12px;margin-left:6px">✅ Saved & encrypted</span>'; ?>
            <p class="description">Get key: <a href="<?php echo esc_url( $p['docs'] ); ?>" target="_blank"><?php echo esc_html( $p['docs'] ); ?> →</a></p>
        </td>
    </tr>
    <?php endforeach; ?>

    <!-- TASK ROUTER -->
    <tr><th scope="row" style="vertical-align:top;padding-top:12px">
        🎯 Task-wise AI Routing
        <p class="description" style="font-weight:normal;margin-top:4px;">Har function ke liye alag AI use karo</p>
    </th><td>

    <div style="background:#f8f9ff;border:1.5px solid #c7d2fe;border-radius:10px;padding:16px;max-width:620px;">

        <!-- Tier legend -->
        <div style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
            <span style="background:#dcfce7;color:#166534;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;">🆓 Free = Gemini</span>
            <span style="background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;">💰 Cheap = DeepSeek</span>
            <span style="background:#ede9fe;color:#5b21b6;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;">⭐ Quality = Claude</span>
        </div>

        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:#e0e7ff;border-bottom:2px solid #c7d2fe;">
                    <th style="padding:8px 12px;text-align:left;font-weight:600;color:#3730a3;">Task</th>
                    <th style="padding:8px 12px;text-align:left;font-weight:600;color:#3730a3;">AI Provider</th>
                    <th style="padding:8px 12px;text-align:left;font-weight:600;color:#3730a3;">Cost</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $tier_badges = [
                'free'    => '<span style="background:#dcfce7;color:#166534;padding:1px 7px;border-radius:20px;font-size:10px;font-weight:600;">FREE</span>',
                'cheap'   => '<span style="background:#fef3c7;color:#92400e;padding:1px 7px;border-radius:20px;font-size:10px;font-weight:600;">~$0.0001/req</span>',
                'quality' => '<span style="background:#ede9fe;color:#5b21b6;padding:1px 7px;border-radius:20px;font-size:10px;font-weight:600;">~$0.0006/req</span>',
            ];
            foreach ( $tasks as $task_id => $task ):
                $current_provider = sgclabs_ai_task_provider( $task_id );
                $saved_provider   = get_option( "sgclabs_task_provider_{$task_id}", $task['default'] );
                $tier_badge       = $tier_badges[ $task['tier'] ] ?? '';
            ?>
            <tr style="border-bottom:1px solid #e0e7ff;<?php echo $task['tier'] === 'free' ? 'background:#fafffe;' : ( $task['tier'] === 'quality' ? 'background:#faf8ff;' : '' ); ?>">
                <td style="padding:9px 12px;">
                    <div style="font-weight:600;color:#1a1a2e;"><?php echo esc_html( $task['label'] ); ?></div>
                    <div style="font-size:11px;color:#9ca3af;"><?php echo esc_html( $task['desc'] ); ?></div>
                </td>
                <td style="padding:9px 12px;">
                    <select name="sgclabs_task_provider_<?php echo $task_id; ?>"
                        class="sgc-task-route-select"
                        data-task="<?php echo $task_id; ?>"
                        style="padding:5px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;background:#fff;">
                        <?php foreach ( $providers as $pid => $p ):
                            $key_ok = ! empty( sgclabs_ai_get_provider_key( $pid ) );
                        ?>
                        <option value="<?php echo esc_attr( $pid ); ?>"
                            <?php selected( $saved_provider, $pid ); ?>
                            <?php echo ! $key_ok ? 'style="color:#ccc;"' : ''; ?>>
                            <?php echo esc_html( $p['label'] ); ?><?php echo ! $key_ok ? ' (no key)' : ''; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td style="padding:9px 12px;"><?php echo $tier_badge; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top:14px;display:flex;gap:10px;align-items:center;">
            <button type="button" id="sgc-save-routing-btn"
                style="background:#1565c0;color:#fff;border:none;border-radius:7px;padding:9px 20px;font-size:13px;font-weight:600;cursor:pointer;"
                onclick="sgcSaveRouting()">
                💾 Save Task Routing
            </button>
            <button type="button"
                style="background:#f1f5f9;color:#374151;border:1px solid #d1d5db;border-radius:7px;padding:9px 16px;font-size:12px;cursor:pointer;"
                onclick="sgcResetRouting()">
                🔄 Reset to Defaults
            </button>
            <span id="sgc-routing-status" style="font-size:12px;display:none;"></span>
        </div>

        <!-- Cost estimate -->
        <div style="margin-top:12px;background:#fff;border:1px solid #e0e7ff;border-radius:8px;padding:12px;">
            <div style="font-size:12px;font-weight:600;color:#3730a3;margin-bottom:6px;">💡 7000 Products Estimated Cost</div>
            <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:12px;color:#374151;">
                <span>🆓 Gemini tasks: <strong style="color:#166534;">$0 (Free)</strong></span>
                <span>💰 DeepSeek tasks: <strong style="color:#92400e;">~$0.85 total</strong></span>
                <span>⭐ Claude tasks: <strong style="color:#5b21b6;">~$4.20 total</strong></span>
            </div>
        </div>
    </div>
    </td></tr>

    <?php if ( ! empty( $log ) ): ?>
    <tr><th scope="row" style="vertical-align:top">⚠️ Recent Errors</th><td>
    <div style="max-height:180px;overflow-y:auto;border:1px solid #fca5a5;border-radius:6px;padding:8px;background:#fef2f2">
    <?php foreach ( array_slice( $log, 0, 10 ) as $e ): ?>
    <div style="font-size:11px;padding:3px 0;border-bottom:1px solid #fee2e2;display:flex;gap:8px">
        <span style="color:#aaa;white-space:nowrap"><?php echo esc_html( substr( $e['time'] ?? '', 5, 11 ) ); ?></span>
        <span style="color:#b91c1c;font-weight:600"><?php echo esc_html( $e['provider'] ?? '' ); ?></span>
        <span style="color:#666"><?php echo esc_html( $e['error'] ?? '' ); ?></span>
    </div>
    <?php endforeach; ?>
    </div>
    <form method="post" style="margin-top:4px"><?php wp_nonce_field( 'sgclabs_clear_ai_log' ); ?>
    <input type="hidden" name="sgclabs_clear_ai_log" value="1">
    <button type="submit" class="button button-small">Clear log</button></form>
    </td></tr>
    <?php endif; ?>

    <script>
    var sgcNonce = '<?php echo $nonce; ?>';

    function sgcTestProvider(pid) {
        var el = document.getElementById('sgc-test-' + pid);
        el.style.display = 'block';
        el.style.color = '#6b7280';
        el.textContent = '⏳ Testing...';
        jQuery.post(ajaxurl, {
            action: 'sgclabs_test_ai_provider',
            nonce: sgcNonce,
            provider: pid
        }, function(res) {
            if (res.success) {
                el.style.color = '#166534';
                el.textContent = '✅ ' + res.data.ms + 'ms — OK';
            } else {
                el.style.color = '#dc2626';
                el.textContent = '❌ ' + res.data;
            }
        }).fail(function() {
            el.style.color = '#dc2626';
            el.textContent = '❌ Request failed';
        });
    }

    function sgcSaveRouting() {
        var btn    = document.getElementById('sgc-save-routing-btn');
        var status = document.getElementById('sgc-routing-status');
        var data   = { action: 'sgclabs_save_task_routing', nonce: sgcNonce };

        document.querySelectorAll('.sgc-task-route-select').forEach(function(sel) {
            data['task_' + sel.dataset.task] = sel.value;
        });

        btn.textContent = '⏳ Saving...';
        btn.disabled = true;
        status.style.display = 'none';

        jQuery.post(ajaxurl, data, function(res) {
            btn.textContent = '💾 Save Task Routing';
            btn.disabled = false;
            status.style.display = 'inline';
            if (res.success) {
                status.style.color = '#166534';
                status.textContent = res.data;
            } else {
                status.style.color = '#dc2626';
                status.textContent = '❌ ' + res.data;
            }
        }).fail(function() {
            btn.textContent = '💾 Save Task Routing';
            btn.disabled = false;
            status.style.display = 'inline';
            status.style.color = '#dc2626';
            status.textContent = '❌ Request failed';
        });
    }

    function sgcResetRouting() {
        if (!confirm('Sab tasks default providers pe reset ho jayenge. Continue?')) return;
        var defaults = <?php
            $def = [];
            foreach ($tasks as $tid => $t) $def[$tid] = $t['default'];
            echo wp_json_encode($def);
        ?>;
        document.querySelectorAll('.sgc-task-route-select').forEach(function(sel) {
            var task = sel.dataset.task;
            if (defaults[task]) sel.value = defaults[task];
        });
        sgcSaveRouting();
    }
    </script>
    <?php
}

add_action( 'admin_init', function(): void {
    if ( ! isset( $_POST['sgclabs_clear_ai_log'] ) || ! current_user_can( 'manage_options' ) ) return;
    check_admin_referer( 'sgclabs_clear_ai_log' );
    update_option( 'sgclabs_ai_error_log', [] );
} );


