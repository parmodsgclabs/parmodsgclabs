<?php
/**
 * SGC Smart CRM Pro — AI Provider
 *
 * Standalone AI integration supporting Claude, OpenAI, Gemini, and DeepSeek.
 * Falls back to SGC LABS® Manager Pro's sgclabs_ai_call() if no CRM key set.
 * All API keys stored AES-256-CBC encrypted in wp_options.
 *
 * @package SGC_Smart_CRM
 * @since   1.0.0
 * @version   1.1.1 | Build: 20260516-0630
 */

// Block direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SGC_CRM_AI
 *
 * Handles all AI provider calls for the CRM.
 *
 * @since 1.0.0
 * @version   1.1.1 | Build: 20260516-0630
 */
class SGC_CRM_AI {

	/**
	 * Supported AI providers and their configuration.
	 *
	 * @since 1.0.0
	 * @return array<string, array<string, mixed>>
	 */
	public static function providers() {
		return array(
			'claude'   => array(
				'label'       => 'Claude (Anthropic)',
				'models'      => array(
					'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5',
				),
				'default_model' => '',
				'api_url'     => 'https://api.anthropic.com/v1/messages',
				'key_option'  => 'sgc_crm_ai_key_claude',
				'placeholder' => 'sk-ant-api03-...',
				'docs_url'    => 'https://console.anthropic.com/settings/keys',
			),
			'openai'   => array(
				'label'       => 'OpenAI',
				'models'      => array(
					'gpt-4o' => 'GPT-4o',
				),
				'default_model' => '',
				'api_url'     => 'https://api.openai.com/v1/chat/completions',
				'key_option'  => 'sgc_crm_ai_key_openai',
				'placeholder' => 'sk-proj-...',
				'docs_url'    => 'https://platform.openai.com/api-keys',
			),
			'gemini'   => array(
				'label'       => 'Google Gemini',
				'models'      => array(
					'gemini-2.5-flash' => 'Gemini 2.5 Flash',
				),
				'default_model' => '',
				'api_url'     => 'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent',
				'key_option'  => 'sgc_crm_ai_key_gemini',
				'placeholder' => 'AIzaSy...',
				'docs_url'    => 'https://aistudio.google.com/app/apikey',
			),
			'deepseek' => array(
				'label'       => 'DeepSeek',
				'models'      => array(
					'deepseek-chat' => 'DeepSeek Chat (default)',
					'deepseek-v3'   => 'DeepSeek V3',
				),
				'default_model' => 'deepseek-chat',
				'api_url'     => 'https://api.deepseek.com/v1/chat/completions',
				'key_option'  => 'sgc_crm_ai_key_deepseek',
				'placeholder' => 'sk-...',
				'docs_url'    => 'https://platform.deepseek.com/api-keys',
			),
		);
	}

	// =========================================================================
	// ENCRYPTION — AES-256-CBC (same algorithm as SGC Manager Pro)
	// =========================================================================

	/**
	 * Derive a 32-byte encryption key from WordPress secret keys.
	 *
	 * @since  1.0.0
	 * @return string 32 raw bytes.
	 */
	private static function derive_key() {
		$auth_key   = defined( 'AUTH_KEY' )        ? AUTH_KEY        : '';
		$secure_key = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '';
		$ikm        = $auth_key . $secure_key;

		if ( function_exists( 'hash_hkdf' ) ) {
			return hash_hkdf( 'sha256', $ikm, 32, 'sgc_crm_v1_enc', '' );
		}

		// PHP 7.0 fallback.
		return substr( hash( 'sha256', $ikm . 'sgc_crm_v1_enc', true ), 0, 32 );
	}

	/**
	 * Encrypt an API key for storage.
	 *
	 * @since  1.0.0
	 * @param  string $value Plain-text key.
	 * @return string Encrypted string with 'sgccrm2:' prefix, or base64 fallback.
	 */
	public static function encrypt_key( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return base64_encode( $value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		$key = self::derive_key();
		$iv  = random_bytes( 16 );
		$enc = openssl_encrypt( $value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $enc ) {
			return base64_encode( $value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		return 'sgccrm2:' . base64_encode( $iv . $enc ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a stored API key.
	 *
	 * @since  1.0.0
	 * @param  string $value Stored encrypted value.
	 * @return string Decrypted plain-text, or empty string on failure.
	 */
	public static function decrypt_key( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		if ( 0 === strpos( $value, 'sgccrm2:' ) && function_exists( 'openssl_decrypt' ) ) {
			$raw = base64_decode( substr( $value, 8 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			if ( false === $raw || strlen( $raw ) <= 16 ) {
				return '';
			}
			$iv  = substr( $raw, 0, 16 );
			$enc = substr( $raw, 16 );
			$key = self::derive_key();
			$dec = openssl_decrypt( $enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
			return ( false !== $dec ) ? $dec : '';
		}

		// Plain base64 fallback (no openssl).
		$try = base64_decode( $value, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false !== $try && strlen( $try ) > 4 ) {
			return $try;
		}

		return '';
	}

	/**
	 * Get decrypted API key for a provider.
	 * v1.0.7: Falls back to SGC Manager Pro shared keys if own CRM key not set.
	 *
	 * @since  1.0.0
	 * @param  string $provider Provider slug.
	 * @return string Plain-text API key, or empty string.
	 */
	public static function get_key( $provider ) {
		$providers = self::providers();
		if ( ! isset( $providers[ $provider ] ) ) {
			return '';
		}
		// Try own CRM key first
		$encrypted = get_option( $providers[ $provider ]['key_option'], '' );
		$key = self::decrypt_key( $encrypted );
		if ( ! empty( $key ) ) return $key;

		// v1.1.7: Corrected option names — must match Site Manager ai-provider.php 'option_key' values
		$shared_map = [
			'deepseek' => 'sgclabs_aichat_deepseek_key',
			'claude'   => 'sgclabs_claude_api_key',
			'openai'   => 'sgclabs_aichat_openai_key',
			'gemini'   => 'sgclabs_aichat_gemini_key',
		];
		if ( isset( $shared_map[ $provider ] ) ) {
			$raw = get_option( $shared_map[ $provider ], '' );
			if ( ! empty( $raw ) ) {
				// Manager Pro uses sgclabs_decrypt_key() — use if available
				if ( function_exists( 'sgclabs_decrypt_key' ) ) return sgclabs_decrypt_key( $raw );
				$try = base64_decode( $raw, true );
				return ( $try && strlen( $try ) > 10 ) ? $try : $raw;
			}
		}
		return '';
	}

	/**
	 * Save an API key (encrypts before storing).
	 *
	 * @since  1.0.0
	 * @param  string $provider Provider slug.
	 * @param  string $raw_key  Plain-text API key.
	 * @return void
	 */
	public static function save_key( $provider, $raw_key ) {
		$providers = self::providers();
		if ( ! isset( $providers[ $provider ] ) ) {
			return;
		}
		$encrypted = self::encrypt_key( sanitize_text_field( $raw_key ) );
		update_option( $providers[ $provider ]['key_option'], $encrypted );
	}

	// =========================================================================
	// AI CALL — main entry point
	// =========================================================================

	/**
	 * Make an AI call using the configured CRM provider.
	 * Falls back to SGC Manager Pro's sgclabs_ai_call() if no CRM key set.
	 *
	 * @since  1.0.0
	 * @param  string $prompt     User prompt.
	 * @param  int    $max_tokens Maximum tokens in response.
	 * @param  string $system     System/context prompt.
	 * @return string|WP_Error    AI response string, or WP_Error on failure.
	 */
	public static function call( $prompt, $max_tokens = 800, $system = '' ) {
		// v1.0.7: Default = deepseek / deepseek-chat
		$provider = get_option( 'sgc_crm_ai_provider', 'deepseek' );
		$api_key  = self::get_key( $provider ); // get_key() now checks Manager Pro shared keys automatically

		// Still no key → try sgclabs_ai_call() as last resort fallback.
		if ( empty( $api_key ) ) {
			if ( function_exists( 'sgclabs_ai_call' ) ) {
				return sgclabs_ai_call( $prompt, $max_tokens, $system );
			}
			return new WP_Error(
				'sgc_crm_no_api_key',
				esc_html__( 'No AI API key configured. Add one in SGC Smart CRM → Settings → AI Provider.', 'sgc-smart-crm' )
			);
		}

		// Rate limit check (60s cooldown per provider).
		if ( get_transient( 'sgc_crm_rl_' . $provider ) ) {
			// Try fallback if main provider is rate-limited.
			if ( function_exists( 'sgclabs_ai_call' ) ) {
				return sgclabs_ai_call( $prompt, $max_tokens, $system );
			}
			return new WP_Error(
				'sgc_crm_rate_limited',
				sprintf(
					/* translators: %s: provider name */
					esc_html__( '%s is rate-limited. Please wait a moment.', 'sgc-smart-crm' ),
					$provider
				)
			);
		}

		// Attempt up to 3 times with backoff.
		$wait_seconds = array( 0, 3, 8 );
		$last_error   = null;

		for ( $attempt = 0; $attempt < 3; $attempt++ ) {
			if ( $attempt > 0 ) {
				sleep( $wait_seconds[ $attempt ] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}

			$result = self::dispatch( $provider, $api_key, $prompt, $max_tokens, $system );

			if ( is_string( $result ) && strlen( trim( $result ) ) >= 5 ) {
				return $result;
			}

			if ( is_wp_error( $result ) ) {
				$code = $result->get_error_code();

				if ( 'sgc_crm_rate_limited' === $code ) {
					set_transient( 'sgc_crm_rl_' . $provider, 1, 60 );
					// Fallback immediately.
					if ( function_exists( 'sgclabs_ai_call' ) ) {
						return sgclabs_ai_call( $prompt, $max_tokens, $system );
					}
					return $result;
				}

				// Auth errors — don't retry.
				if ( 'sgc_crm_auth_error' === $code ) {
					return $result;
				}

				$last_error = $result;
			}
		}

		return $last_error instanceof WP_Error
			? $last_error
			: new WP_Error( 'sgc_crm_ai_failed', esc_html__( 'AI call failed after 3 attempts.', 'sgc-smart-crm' ) );
	}

	// =========================================================================
	// DISPATCH TO PROVIDER
	// =========================================================================

	/**
	 * Dispatch API call to the correct provider endpoint.
	 *
	 * @since  1.0.0
	 * @param  string $provider   Provider slug.
	 * @param  string $api_key    Plain-text API key.
	 * @param  string $prompt     User prompt.
	 * @param  int    $max_tokens Max tokens.
	 * @param  string $system     System prompt.
	 * @return string|WP_Error
	 */
	private static function dispatch( $provider, $api_key, $prompt, $max_tokens, $system ) {
		switch ( $provider ) {
			case 'claude':
				return self::call_claude( $api_key, $prompt, $max_tokens, $system );
			case 'openai':
				return self::call_openai( $api_key, $prompt, $max_tokens, $system );
			case 'gemini':
				return self::call_gemini( $api_key, $prompt, $max_tokens, $system );
			case 'deepseek':
				return self::call_deepseek( $api_key, $prompt, $max_tokens, $system );
			default:
				return new WP_Error(
					'sgc_crm_unknown_provider',
					/* translators: %s: provider name */
					sprintf( esc_html__( 'Unknown AI provider: %s', 'sgc-smart-crm' ), $provider )
				);
		}
	}

	// =========================================================================
	// CLAUDE
	// =========================================================================

	/**
	 * Call Anthropic Claude API.
	 *
	 * @since  1.0.0
	 * @param  string $api_key    API key.
	 * @param  string $prompt     Prompt.
	 * @param  int    $max_tokens Max tokens.
	 * @param  string $system     System prompt.
	 * @return string|WP_Error
	 */
	private static function call_claude( $api_key, $prompt, $max_tokens, $system ) {
		$model = get_option( 'sgc_crm_ai_model_claude', '' );
		if ( empty( $model ) ) {
			return new WP_Error( 'sgc_crm_no_model', esc_html__( 'No model selected. Go to Settings → AI Provider and select a model.', 'sgc-smart-crm' ) );
		}

		$body = array(
			'model'      => $model,
			'max_tokens' => (int) $max_tokens,
			'messages'   => array(
				array( 'role' => 'user', 'content' => $prompt ),
			),
		);

		if ( ! empty( $system ) ) {
			$body['system'] = $system;
		}

		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'timeout' => 60,
				'headers' => array(
					'Content-Type'      => 'application/json',
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		return self::parse_response( $response, 'claude' );
	}

	// =========================================================================
	// OPENAI
	// =========================================================================

	/**
	 * Call OpenAI Chat Completions API.
	 *
	 * @since  1.0.0
	 * @param  string $api_key    API key.
	 * @param  string $prompt     Prompt.
	 * @param  int    $max_tokens Max tokens.
	 * @param  string $system     System prompt.
	 * @return string|WP_Error
	 */
	private static function call_openai( $api_key, $prompt, $max_tokens, $system ) {
		$model = get_option( 'sgc_crm_ai_model_openai', '' );
		if ( empty( $model ) ) {
			return new WP_Error( 'sgc_crm_no_model', esc_html__( 'No model selected. Go to Settings → AI Provider and select a model.', 'sgc-smart-crm' ) );
		}
		$messages = array();

		if ( ! empty( $system ) ) {
			$messages[] = array( 'role' => 'system', 'content' => $system );
		}
		$messages[] = array( 'role' => 'user', 'content' => $prompt );

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 60,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode(
					array(
						'model'      => $model,
						'max_tokens' => (int) $max_tokens,
						'messages'   => $messages,
					)
				),
			)
		);

		return self::parse_response( $response, 'openai' );
	}

	// =========================================================================
	// GEMINI
	// =========================================================================

	/**
	 * Call Google Gemini API.
	 *
	 * @since  1.0.0
	 * @param  string $api_key    API key.
	 * @param  string $prompt     Prompt.
	 * @param  int    $max_tokens Max tokens.
	 * @param  string $system     System prompt.
	 * @return string|WP_Error
	 */
	private static function call_gemini( $api_key, $prompt, $max_tokens, $system ) {
		$model = get_option( 'sgc_crm_ai_model_gemini', '' );
		if ( empty( $model ) ) {
			return new WP_Error( 'sgc_crm_no_model', esc_html__( 'No model selected. Go to Settings → AI Provider and select a model.', 'sgc-smart-crm' ) );
		}
		$url   = 'https://generativelanguage.googleapis.com/v1beta/models/'
			. rawurlencode( $model )
			. ':generateContent?key=' . rawurlencode( $api_key );

		$body = array(
			'contents'         => array(
				array(
					'role'  => 'user',
					'parts' => array( array( 'text' => $prompt ) ),
				),
			),
			'generationConfig' => array(
				'maxOutputTokens' => (int) $max_tokens,
				'temperature'     => 0.7,
			),
		);

		if ( ! empty( $system ) ) {
			$body['systemInstruction'] = array(
				'parts' => array( array( 'text' => $system ) ),
			);
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 60,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);

		return self::parse_response( $response, 'gemini' );
	}

	// =========================================================================
	// DEEPSEEK
	// =========================================================================

	/**
	 * Call DeepSeek Chat API (OpenAI-compatible endpoint).
	 *
	 * @since  1.0.0
	 * @param  string $api_key    API key.
	 * @param  string $prompt     Prompt.
	 * @param  int    $max_tokens Max tokens.
	 * @param  string $system     System prompt.
	 * @return string|WP_Error
	 */
	private static function call_deepseek( $api_key, $prompt, $max_tokens, $system ) {
		$model = get_option( 'sgc_crm_ai_model_deepseek', '' );
		if ( empty( $model ) ) {
			return new WP_Error( 'sgc_crm_no_model', esc_html__( 'No model selected. Go to Settings → AI Provider and select a model.', 'sgc-smart-crm' ) );
		}
		$messages = array();

		if ( ! empty( $system ) ) {
			$messages[] = array( 'role' => 'system', 'content' => $system );
		}
		$messages[] = array( 'role' => 'user', 'content' => $prompt );

		$response = wp_remote_post(
			'https://api.deepseek.com/v1/chat/completions',
			array(
				'timeout' => 60,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode(
					array(
						'model'      => $model,
						'max_tokens' => (int) $max_tokens,
						'messages'   => $messages,
					)
				),
			)
		);

		return self::parse_response( $response, 'deepseek' );
	}

	// =========================================================================
	// RESPONSE PARSER
	// =========================================================================

	/**
	 * Parse API response from any provider into a plain string.
	 *
	 * @since  1.0.0
	 * @param  array|WP_Error $response WP HTTP response.
	 * @param  string         $provider Provider slug (for error messages).
	 * @return string|WP_Error
	 */
	private static function parse_response( $response, $provider ) {
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'sgc_crm_http_error',
				sprintf(
					/* translators: 1: provider 2: error message */
					esc_html__( '%1$s HTTP error: %2$s', 'sgc-smart-crm' ),
					$provider,
					$response->get_error_message()
				)
			);
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		$body_raw  = wp_remote_retrieve_body( $response );
		$body      = json_decode( $body_raw, true );

		// Rate limit.
		if ( 429 === $http_code ) {
			return new WP_Error( 'sgc_crm_rate_limited', esc_html__( 'Rate limited.', 'sgc-smart-crm' ) );
		}

		// Auth error.
		if ( 401 === $http_code || 403 === $http_code ) {
			return new WP_Error(
				'sgc_crm_auth_error',
				sprintf(
					/* translators: %s: provider */
					esc_html__( '%s: Invalid API key or unauthorized.', 'sgc-smart-crm' ),
					$provider
				)
			);
		}

		// Other HTTP errors.
		if ( $http_code < 200 || $http_code >= 300 ) {
			$err_msg = isset( $body['error']['message'] ) ? $body['error']['message'] : $body_raw;
			return new WP_Error(
				'sgc_crm_api_error',
				sprintf(
					/* translators: 1: provider 2: HTTP code 3: error message */
					esc_html__( '%1$s API error %2$d: %3$s', 'sgc-smart-crm' ),
					$provider,
					$http_code,
					wp_strip_all_tags( $err_msg )
				)
			);
		}

		// Extract text from provider-specific structure.
		switch ( $provider ) {
			case 'claude':
				$text = $body['content'][0]['text'] ?? '';
				break;

			case 'openai':
			case 'deepseek':
				$text = $body['choices'][0]['message']['content'] ?? '';
				break;

			case 'gemini':
				$text = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
				break;

			default:
				$text = '';
		}

		if ( empty( trim( $text ) ) ) {
			return new WP_Error(
				'sgc_crm_empty_response',
				sprintf(
					/* translators: %s: provider */
					esc_html__( '%s returned an empty response.', 'sgc-smart-crm' ),
					$provider
				)
			);
		}

		return trim( $text );
	}

	// =========================================================================
	// TEST CONNECTION
	// =========================================================================

	/**
	 * Test connection to a provider with a simple ping prompt.
	 *
	 * @since  1.0.0
	 * @param  string $provider Provider slug.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public static function test_connection( $provider ) {
		$api_key = self::get_key( $provider );

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'sgc_crm_no_key',
				esc_html__( 'No API key saved for this provider.', 'sgc-smart-crm' )
			);
		}

		$result = self::dispatch(
			$provider,
			$api_key,
			'Reply with exactly: OK',
			20,
			'You are a connection test. Reply with exactly the word OK and nothing else.'
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	// =========================================================================
	// AJAX HANDLERS
	// =========================================================================

	/**
	 * Register AJAX handlers.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function register_ajax() {
		add_action( 'wp_ajax_sgc_crm_save_ai_settings',  array( __CLASS__, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_sgc_crm_test_ai_connection', array( __CLASS__, 'ajax_test_connection' ) );
	}

	/**
	 * AJAX: Save AI provider settings.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_save_settings() {
		check_ajax_referer( 'sgc_crm_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions.', 'sgc-smart-crm' ), 403 );
		}

		$provider  = sanitize_key( wp_unslash( $_POST['provider'] ?? '' ) );
		$providers = self::providers();

		if ( ! isset( $providers[ $provider ] ) ) {
			wp_send_json_error( esc_html__( 'Invalid provider.', 'sgc-smart-crm' ) );
		}

		// Set as active provider only when explicitly requested.
		$set_active = ( '1' === sanitize_text_field( wp_unslash( $_POST['set_active'] ?? '0' ) ) );
		if ( $set_active ) {
			update_option( 'sgc_crm_ai_provider', $provider );
		}

		// Save model — only if a valid model is submitted.
		$model          = sanitize_text_field( wp_unslash( $_POST['model'] ?? '' ) );
		$allowed_models = array_keys( $providers[ $provider ]['models'] );
		if ( ! empty( $model ) && in_array( $model, $allowed_models, true ) ) {
			update_option( 'sgc_crm_ai_model_' . $provider, $model );
		}

		// Save API key — only if non-empty (blank = keep existing key).
		$raw_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
		if ( ! empty( $raw_key ) ) {
			self::save_key( $provider, $raw_key );
		}

		// Check key status after save.
		$has_key = ! empty( self::get_key( $provider ) );

		wp_send_json_success(
			array(
				'message'    => esc_html__( 'Settings saved.', 'sgc-smart-crm' ),
				'has_key'    => $has_key,
				'provider'   => $provider,
				'set_active' => $set_active,
			)
		);
	}

	/**
	 * AJAX: Test AI provider connection.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_test_connection() {
		check_ajax_referer( 'sgc_crm_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions.', 'sgc-smart-crm' ), 403 );
		}

		$provider = sanitize_key( wp_unslash( $_POST['provider'] ?? '' ) );
		$result   = self::test_connection( $provider );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success(
			sprintf(
				/* translators: %s: provider name */
				esc_html__( '%s connected successfully!', 'sgc-smart-crm' ),
				$provider
			)
		);
	}
}

// Register AJAX hooks.
SGC_CRM_AI::register_ajax();
