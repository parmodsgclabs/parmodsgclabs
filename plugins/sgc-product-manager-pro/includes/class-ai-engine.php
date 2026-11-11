<?php
defined( 'ABSPATH' ) || exit;

class SGC_PM_AI_Engine {

	const PROVIDERS = [
		'deepseek' => [ 'label' => 'DeepSeek',  'url' => 'https://api.deepseek.com/v1/chat/completions',         'model' => 'deepseek-chat',           'default' => true  ],
		'openai'   => [ 'label' => 'OpenAI',    'url' => 'https://api.openai.com/v1/chat/completions',            'model' => 'gpt-4o-mini',             'default' => false ],
		'gemini'   => [ 'label' => 'Gemini',    'url' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent', 'model' => 'gemini-2.5-flash', 'default' => false ],
		'claude'   => [ 'label' => 'Claude',    'url' => 'https://api.anthropic.com/v1/messages',                 'model' => 'claude-haiku-4-5-20251001','default' => false ],
	];

	public static function register_ajax() {
		$actions = [
			'sgc_pm_ai_generate_desc'    => 'ajax_generate_desc',
			'sgc_pm_ai_bulk_seo'         => 'ajax_bulk_seo',
			'sgc_pm_ai_sql_assist'       => 'ajax_sql_assist',
			'sgc_pm_ai_test_connection'  => 'ajax_test_connection',
			'sgc_pm_ai_save_settings'    => 'ajax_save_settings',
			'sgc_pm_ai_keyword_research' => 'ajax_keyword_research',
			'sgc_pm_ai_price_suggest'    => 'ajax_price_suggest',
		'sgc_pm_ai_suggest_categories' => 'ajax_suggest_categories',
		];
		foreach ( $actions as $action => $method ) {
			add_action( 'wp_ajax_' . $action, [ __CLASS__, $method ] );
		}
	}

	private static function verify() {
		check_ajax_referer( 'sgc_pm_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) )
			wp_send_json_error( 'Unauthorized', 403 );
	}

	// ── Shared key: read from SGC Manager Pro if own key not set (v3.0.2) ─
	private static function get_shared_key( $provider ) {
		// v3.0.6: Corrected option names — must match Site Manager's ai-provider.php 'option_key' values
		$map = [
			'deepseek' => 'sgclabs_aichat_deepseek_key',
			'claude'   => 'sgclabs_claude_api_key',
			'openai'   => 'sgclabs_aichat_openai_key',
			'gemini'   => 'sgclabs_aichat_gemini_key',
		];
		if ( ! isset( $map[ $provider ] ) ) return '';
		$raw = get_option( $map[ $provider ], '' );
		if ( empty( $raw ) ) return '';
		if ( function_exists( 'sgclabs_decrypt_key' ) ) return sgclabs_decrypt_key( $raw );
		$try = base64_decode( $raw, true );
		return ( $try && strlen( $try ) > 10 ) ? $try : $raw;
	}

	// ── Core AI call ──────────────────────────────────────────────────────
	public static function call( $prompt, $system = '', $max_tokens = 1500, $provider = null ) {
		// v3.0.6: Delegate to Site Manager's unified AI engine when available (no provider override).
		// This gives PM full access to SM's task router, rate-limit handling, and retry/backoff.
		if ( ! $provider && function_exists( 'sgclabs_ai_call' ) ) {
			return sgclabs_ai_call( $prompt, $max_tokens, $system );
		}

		// v3.0.2: Default provider deepseek / model deepseek-chat
		if ( ! $provider ) $provider = get_option( 'sgc_pm_ai_provider', 'deepseek' );
		if ( ! isset( self::PROVIDERS[ $provider ] ) ) $provider = 'deepseek';

		$key = get_option( 'sgc_pm_ai_key_' . $provider, '' );

		// v3.0.2: Shared key fallback — read from SGC Manager Pro options
		if ( empty( $key ) ) $key = self::get_shared_key( $provider );

		if ( empty( $key ) ) {
			// Auto-fallback to next provider that has a key (own or shared)
			foreach ( self::PROVIDERS as $pid => $p ) {
				$k = get_option( 'sgc_pm_ai_key_' . $pid, '' );
				if ( empty( $k ) ) $k = self::get_shared_key( $pid );
				if ( $k ) { $provider = $pid; $key = $k; break; }
			}
		}
		if ( empty( $key ) ) return new WP_Error( 'no_key', 'No AI API key configured. Set in PM Pro → AI Engine or SGC Manager Pro → AI Settings.' );

		$config = self::PROVIDERS[ $provider ];

		// Build request per provider
		if ( 'gemini' === $provider ) {
			$body = wp_json_encode( [
				'contents'         => [ [ 'parts' => [ [ 'text' => ( $system ? $system . "\n\n" : '' ) . $prompt ] ] ] ],
				'generationConfig' => [ 'maxOutputTokens' => $max_tokens, 'temperature' => 0.7 ],
			] );
			$url = $config['url'] . '?key=' . $key;
			$headers = [ 'Content-Type' => 'application/json' ];
		} elseif ( 'claude' === $provider ) {
			$body = wp_json_encode( [
				'model'      => $config['model'],
				'max_tokens' => $max_tokens,
				'system'     => $system ?: 'You are an expert SEO and product copywriter for scientific laboratory glassware.',
				'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
			] );
			$url = $config['url'];
			$headers = [ 'Content-Type' => 'application/json', 'x-api-key' => $key, 'anthropic-version' => '2023-06-01' ];
		} else {
			// OpenAI-compatible (DeepSeek, OpenAI)
			$messages = [];
			if ( $system ) $messages[] = [ 'role' => 'system', 'content' => $system ];
			$messages[] = [ 'role' => 'user', 'content' => $prompt ];
			$body = wp_json_encode( [
				'model'       => $config['model'],
				'messages'    => $messages,
				'max_tokens'  => $max_tokens,
				'temperature' => 0.7,
			] );
			$url = $config['url'];
			$headers = [ 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $key ];
		}

		$response = wp_remote_post( $url, [
			'headers' => $headers,
			'body'    => $body,
			'timeout' => 60,
		] );

		if ( is_wp_error( $response ) ) return $response;

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 ) {
			$msg = $data['error']['message'] ?? $data['error'] ?? 'API error ' . $code;
			return new WP_Error( 'ai_error', $msg );
		}

		// Extract text per provider
		if ( 'gemini' === $provider ) {
			return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
		} elseif ( 'claude' === $provider ) {
			return $data['content'][0]['text'] ?? '';
		} else {
			return $data['choices'][0]['message']['content'] ?? '';
		}
	}

	// ── Generate description for one product ─────────────────────────────
	public static function ajax_generate_desc() {
		self::verify();

		$product_id = (int) ( $_POST['product_id'] ?? 0 );
		$keywords   = sanitize_textarea_field( $_POST['keywords'] ?? '' );
		$tone       = sanitize_key( $_POST['tone'] ?? get_option( 'sgc_pm_ai_tone', 'b2b_formal' ) );
		$type       = sanitize_key( $_POST['desc_type'] ?? 'both' ); // main | short | both | seo

		if ( ! $product_id ) wp_send_json_error( 'Invalid product ID.' );
		$product = wc_get_product( $product_id );
		if ( ! $product ) wp_send_json_error( 'Product not found.' );

		$cats      = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'names' ] );
		$brand     = get_option( 'sgc_pm_ai_brand', 'SGC LABS®' );
		$tone_desc = self::tone_desc( $tone );

		$hs_code    = get_post_meta( $product_id, '_sgcpm_hs_code', true ) ?: '';
		$export_mkt = 'Germany, USA, UAE, UK, Nigeria, Kenya, Saudi Arabia, Australia';

		// v3.0.3: Export-focused system prompt
		$system = "You are an expert SEO copywriter and B2B export product specialist for {$brand} — India's leading scientific laboratory glassware manufacturer and exporter (Est. 1965), Ambala, India. Write content that ranks on Google for international B2B buyers searching for lab glassware suppliers from India.";

		// v3.0.3: Enhanced prompt with export SEO + FAQ + alt text + schema
		$prompt = "Write comprehensive SEO-optimized product content for international export:\n"
			. "Product: {$product->get_name()}\n"
			. "SKU: {$product->get_sku()}\n"
			. "Category: " . implode( ', ', is_array( $cats ) ? $cats : [] ) . "\n"
			. "Price: {$product->get_regular_price()} INR\n"
			. ( $hs_code ? "HS Code: {$hs_code}\n" : '' )
			. "Target export markets: {$export_mkt}\n"
			. ( $keywords ? "Must include keywords: {$keywords}\n" : '' )
			. "Tone: {$tone_desc}\n\n"
			. "Return ONLY valid JSON (no markdown, no preamble):\n"
			. '{"main_description":"250-350 word HTML — include export keywords like laboratory glassware manufacturer India, borosilicate glass supplier, ISO certified lab glassware. Natural 1.5-2.5% keyword density. Include specs, compliance (ISO/DIN/ASTM), MOQ.","short_description":"70-90 word export-focused excerpt","seo_title":"55-60 char with brand and target keyword","meta_description":"140-155 char with CTA like Get Quote or Request Sample","focus_keyword":"primary long-tail export keyword","secondary_keywords":["kw1","kw2","kw3","kw4","kw5"],"suggested_tags":["tag1","tag2","tag3"],"alt_text":"descriptive image alt for Google Images and screen readers","faq":[{"q":"Buyer question","a":"Answer with keyword"},{"q":"Another question","a":"Answer"}],"schema_description":"1-2 sentence schema.org description"}';

		$result = self::call( $prompt, $system, 2000 );
		if ( is_wp_error( $result ) ) wp_send_json_error( $result->get_error_message() );

		$clean  = preg_replace( '/```(?:json)?|```/', '', $result );
		$parsed = json_decode( trim( $clean ), true );

		if ( ! $parsed ) wp_send_json_error( 'AI response parsing failed. Raw: ' . substr( $result, 0, 200 ) );

		// Save to product
		if ( in_array( $type, [ 'main', 'both' ], true ) && ! empty( $parsed['main_description'] ) ) {
			wp_update_post( [ 'ID' => $product_id, 'post_content' => wp_kses_post( $parsed['main_description'] ) ] );
		}
		if ( in_array( $type, [ 'short', 'both' ], true ) && ! empty( $parsed['short_description'] ) ) {
			wp_update_post( [ 'ID' => $product_id, 'post_excerpt' => sanitize_textarea_field( $parsed['short_description'] ) ] );
		}
		if ( ! empty( $parsed['seo_title'] ) ) {
			update_post_meta( $product_id, '_yoast_wpseo_title', sanitize_text_field( $parsed['seo_title'] ) );
			update_post_meta( $product_id, 'rank_math_title', sanitize_text_field( $parsed['seo_title'] ) );
		}
		if ( ! empty( $parsed['meta_description'] ) ) {
			update_post_meta( $product_id, '_yoast_wpseo_metadesc', sanitize_text_field( $parsed['meta_description'] ) );
			update_post_meta( $product_id, 'rank_math_description', sanitize_text_field( $parsed['meta_description'] ) );
		}
		if ( ! empty( $parsed['focus_keyword'] ) ) {
			update_post_meta( $product_id, '_yoast_wpseo_focuskw', sanitize_text_field( $parsed['focus_keyword'] ) );
			update_post_meta( $product_id, 'rank_math_focus_keyword', sanitize_text_field( $parsed['focus_keyword'] ) );
		}

		// v3.0.3: Save export SEO extras
		if ( ! empty( $parsed['alt_text'] ) ) {
			update_post_meta( $product_id, '_sgcpm_ai_alt_text', sanitize_text_field( $parsed['alt_text'] ) );
			$thumb_id = get_post_thumbnail_id( $product_id );
			if ( $thumb_id ) update_post_meta( $thumb_id, '_wp_attachment_image_alt', sanitize_text_field( $parsed['alt_text'] ) );
		}
		if ( ! empty( $parsed['faq'] ) )               update_post_meta( $product_id, '_sgcpm_ai_faq',      wp_json_encode( $parsed['faq'] ) );
		if ( ! empty( $parsed['schema_description'] ) ) update_post_meta( $product_id, '_sgcpm_schema_desc', sanitize_text_field( $parsed['schema_description'] ) );
		if ( ! empty( $parsed['secondary_keywords'] ) ) update_post_meta( $product_id, '_sgcpm_secondary_kw', implode( ', ', array_map( 'sanitize_text_field', (array)$parsed['secondary_keywords'] ) ) );
		if ( ! empty( $parsed['suggested_tags'] ) && is_array( $parsed['suggested_tags'] ) ) {
			wp_set_post_terms( $product_id, array_map( 'sanitize_text_field', $parsed['suggested_tags'] ), 'product_tag', true );
		}

		wc_delete_product_transients( $product_id );
		SGC_PM_Logger::log( 'ai_generate', 'product', $product_id, 'AI generated content v3.0.3. Keywords: ' . ( $parsed['focus_keyword'] ?? '' ) );

		wp_send_json_success( [ 'product_id' => $product_id, 'data' => $parsed, 'message' => 'Content generated and saved.' ] );
	}

	// ── Bulk SEO (batch up to 100) ────────────────────────────────────────
	public static function ajax_bulk_seo() {
		self::verify();

		$ids    = array_filter( array_map( 'absint', (array) ( $_POST['ids'] ?? [] ) ) );
		$tone   = sanitize_key( $_POST['tone'] ?? 'b2b_formal' );
		$type   = sanitize_key( $_POST['desc_type'] ?? 'both' );
		$done   = 0;
		$errors = [];
		$limit  = min( 100, count( $ids ) );

		foreach ( array_slice( array_values( $ids ), 0, $limit ) as $id ) {
			// Fetch keywords from competitor data if available
			global $wpdb;
			$gap_kw = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore
				"SELECT gap_keywords FROM {$wpdb->prefix}sgcpm_competitor_data WHERE product_id=%d ORDER BY fetched_at DESC LIMIT 1",
				$id
			) );
			$keywords = '';
			if ( $gap_kw ) {
				$arr = json_decode( $gap_kw, true );
				if ( is_array( $arr ) ) $keywords = implode( ', ', array_slice( $arr, 0, 5 ) );
			}

			$_POST['product_id'] = $id;
			$_POST['keywords']   = $keywords;
			$_POST['tone']       = $tone;
			$_POST['desc_type']  = $type;

			$product = wc_get_product( $id );
			if ( ! $product ) { $errors[] = $id; continue; }

			$cats   = wp_get_post_terms( $id, 'product_cat', [ 'fields' => 'names' ] );
			$brand  = get_option( 'sgc_pm_ai_brand', 'SGC LABS®' );
			$system = "Expert SEO copywriter for {$brand} — Indian lab glassware exporter.";

			$prompt = "Product: {$product->get_name()}\nSKU: {$product->get_sku()}\nCategory: " . implode( ', ', is_array( $cats ) ? $cats : [] ) . "\n"
				. ( $keywords ? "Use keywords: {$keywords}\n" : '' )
				. "Return ONLY JSON: {\"main_description\":\"...\",\"short_description\":\"...\",\"seo_title\":\"...\",\"meta_description\":\"...\",\"focus_keyword\":\"...\"}";

			$result = self::call( $prompt, $system, 800 );
			if ( is_wp_error( $result ) ) { $errors[] = $id; continue; }

			$clean  = preg_replace( '/```(?:json)?|```/', '', $result );
			$parsed = json_decode( trim( $clean ), true );
			if ( ! $parsed ) { $errors[] = $id; continue; }

			if ( ! empty( $parsed['main_description'] ) )
				wp_update_post( [ 'ID' => $id, 'post_content' => wp_kses_post( $parsed['main_description'] ) ] );
			if ( ! empty( $parsed['short_description'] ) )
				wp_update_post( [ 'ID' => $id, 'post_excerpt' => sanitize_textarea_field( $parsed['short_description'] ) ] );
			if ( ! empty( $parsed['seo_title'] ) ) {
				update_post_meta( $id, '_yoast_wpseo_title', sanitize_text_field( $parsed['seo_title'] ) );
				update_post_meta( $id, 'rank_math_title', sanitize_text_field( $parsed['seo_title'] ) );
			}
			if ( ! empty( $parsed['meta_description'] ) ) {
				update_post_meta( $id, '_yoast_wpseo_metadesc', sanitize_text_field( $parsed['meta_description'] ) );
				update_post_meta( $id, 'rank_math_description', sanitize_text_field( $parsed['meta_description'] ) );
			}
			if ( ! empty( $parsed['focus_keyword'] ) ) {
				update_post_meta( $id, '_yoast_wpseo_focuskw', sanitize_text_field( $parsed['focus_keyword'] ) );
				update_post_meta( $id, 'rank_math_focus_keyword', sanitize_text_field( $parsed['focus_keyword'] ) );
			}
			wc_delete_product_transients( $id );
			$done++;

			// Brief pause to avoid rate limits
			if ( $done % 5 === 0 ) usleep( 500000 ); // 0.5s every 5
		}

		SGC_PM_Logger::log( 'ai_bulk_seo', 'bulk', null, "Bulk SEO: $done done, " . count( $errors ) . ' errors' );
		wp_send_json_success( [ 'done' => $done, 'errors' => count( $errors ), 'message' => "$done products updated, " . count( $errors ) . " failed." ] );
	}

	// ── Keyword research via Google Suggest + AI ──────────────────────────
	public static function ajax_keyword_research() {
		self::verify();
		$product_name = sanitize_text_field( $_POST['product_name'] ?? '' );
		$category     = sanitize_text_field( $_POST['category'] ?? '' );
		if ( ! $product_name ) wp_send_json_error( 'Product name required.' );

		// Google Autocomplete — free, no API key
		$keywords = self::google_suggest( $product_name );
		if ( $category ) {
			$cat_kw = self::google_suggest( $category . ' manufacturer india' );
			$keywords = array_unique( array_merge( $keywords, $cat_kw ) );
		}

		// AI analyze + expand
		$system = 'SEO keyword analyst for B2B laboratory glassware manufacturer in India.';
		$prompt = "Product: {$product_name}\nCategory: {$category}\n"
			. "Google Suggest keywords: " . implode( ', ', array_slice( $keywords, 0, 15 ) ) . "\n\n"
			. "Return ONLY JSON:\n"
			. '{"primary_keywords":["top 5 high-volume keywords"],"long_tail":["5 buying-intent long-tail keywords"],"b2b_keywords":["5 B2B specific keywords"],"standard_codes":["ASTM/ISO/DIN standards relevant to product"],"competitor_terms":["terms competitors use"],"suggested_focus":"single best focus keyword","search_intent":"informational|transactional|commercial"}';

		$result = self::call( $prompt, $system, 600 );
		if ( is_wp_error( $result ) ) {
			// Return just the Google suggest results if AI fails
			wp_send_json_success( [ 'google_suggest' => $keywords, 'ai_analysis' => null ] );
		}

		$clean  = preg_replace( '/```(?:json)?|```/', '', $result );
		$parsed = json_decode( trim( $clean ), true );

		wp_send_json_success( [ 'google_suggest' => $keywords, 'ai_analysis' => $parsed ] );
	}

	// ── Google Suggest (free, no API key) ────────────────────────────────
	public static function google_suggest( $query ) {
		$url  = 'https://suggestqueries.google.com/complete/search?client=firefox&q=' . urlencode( $query . ' ' );
		$resp = wp_remote_get( $url, [ 'timeout' => 8, 'user-agent' => 'Mozilla/5.0' ] );
		if ( is_wp_error( $resp ) ) return [];

		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		return is_array( $body[1] ?? null ) ? array_slice( $body[1], 0, 10 ) : [];
	}

	// ── AI SQL Assistant ─────────────────────────────────────────────────
	public static function ajax_sql_assist() {
		self::verify();
		global $wpdb;

		$question = sanitize_textarea_field( $_POST['question'] ?? '' );
		if ( ! $question ) wp_send_json_error( 'Question required.' );

		$prefix = $wpdb->prefix;
		$system = "You are a WordPress MySQL expert. Generate safe SQL queries for WooCommerce. Table prefix: {$prefix}. Never use DROP/TRUNCATE/ALTER. Return ONLY the SQL query, no explanation.";
		$prompt = "Write a MySQL SELECT query for WooCommerce to: {$question}\nCommon tables: {$prefix}posts (products: post_type='product'), {$prefix}postmeta (meta_key like '_regular_price','_sku','_stock','_stock_status'), {$prefix}terms, {$prefix}term_relationships. Return ONLY the SQL.";

		$result = self::call( $prompt, $system, 400 );
		if ( is_wp_error( $result ) ) wp_send_json_error( $result->get_error_message() );

		$sql = preg_replace( '/```(?:sql)?|```/', '', trim( $result ) );
		wp_send_json_success( [ 'sql' => trim( $sql ) ] );
	}

	// ── Price suggestion ──────────────────────────────────────────────────
	public static function ajax_price_suggest() {
		self::verify();
		$product_id = (int) ( $_POST['product_id'] ?? 0 );
		$product    = wc_get_product( $product_id );
		if ( ! $product ) wp_send_json_error( 'Product not found.' );

		$current = $product->get_regular_price();
		$cats    = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'names' ] );

		$system = 'Pricing analyst for Indian lab glassware manufacturer. Give data-driven price recommendations.';
		$prompt = "Product: {$product->get_name()}\nCurrent Price: ₹{$current} INR\nCategory: " . implode( ', ', is_array( $cats ) ? $cats : [] ) . "\nBrand: " . get_option( 'sgc_pm_ai_brand', 'SGC LABS®' ) . "\n\nSuggest pricing strategy. Return ONLY JSON:\n{\"suggested_retail_inr\":0,\"suggested_dealer_inr\":0,\"suggested_export_usd\":0,\"reasoning\":\"brief explanation\",\"market_position\":\"budget|mid|premium\",\"recommendation\":\"increase|decrease|keep\"}";

		$result = self::call( $prompt, $system, 400 );
		if ( is_wp_error( $result ) ) wp_send_json_error( $result->get_error_message() );

		$clean  = preg_replace( '/```(?:json)?|```/', '', $result );
		$parsed = json_decode( trim( $clean ), true );
		if ( ! $parsed ) wp_send_json_error( 'Failed to parse AI response.' );

		wp_send_json_success( $parsed );
	}

	// ── Test connection ───────────────────────────────────────────────────
	public static function ajax_test_connection() {
		self::verify();
		$provider = sanitize_key( $_POST['provider'] ?? 'deepseek' );

		$result = self::call( 'Reply with exactly: CONNECTED', 'You are a test assistant.', 20, $provider );
		if ( is_wp_error( $result ) ) wp_send_json_error( $result->get_error_message() );

		wp_send_json_success( [ 'message' => 'Connected! Response: ' . substr( $result, 0, 50 ) ] );
	}

	// ── Save AI settings ──────────────────────────────────────────────────
	public static function ajax_save_settings() {
		self::verify();

		$fields = [
			'sgc_pm_ai_provider'       => 'key',
			'sgc_pm_ai_key_deepseek'   => 'text',
			'sgc_pm_ai_key_openai'     => 'text',
			'sgc_pm_ai_key_gemini'     => 'text',
			'sgc_pm_ai_key_claude'     => 'text',
			'sgc_pm_ai_tone'           => 'key',
			'sgc_pm_ai_brand'          => 'text',
			'sgc_pm_ai_enabled'        => 'bool',
		];

		foreach ( $fields as $key => $type ) {
			if ( ! isset( $_POST[ $key ] ) ) continue;
			$val = wp_unslash( $_POST[ $key ] );
			switch ( $type ) {
				case 'key':  $val = sanitize_key( $val ); break;
				case 'bool': $val = ( '1' === $val ) ? '1' : '0'; break;
				default:     $val = sanitize_text_field( $val );
			}
			update_option( $key, $val );
		}

		wp_send_json_success( 'AI settings saved.' );
	}

	// ── AI Category Suggester ─────────────────────────────────────────────
	// Batch: send up to 50 product names → AI returns suggested category per product
	public static function ajax_suggest_categories() {
		self::verify();

		$products    = (array) ( $_POST['products'] ?? [] ); // [{id, name, current_cat}]
		$available   = sanitize_textarea_field( wp_unslash( $_POST['available_categories'] ?? '' ) );

		if ( empty( $products ) ) wp_send_json_error( 'No products sent.' );

		// Build product list string
		$lines = [];
		foreach ( array_slice( $products, 0, 50 ) as $p ) {
			$lines[] = $p['id'] . '|' . $p['name'] . '|' . $p['current_cat'];
		}
		$product_list = implode( "\n", $lines );

		$system = 'You are a product categorization expert for a scientific laboratory glassware manufacturer. Analyze product names and assign the most accurate category. Be concise and accurate.';

		$prompt = "Available categories:\n{$available}\n\n"
			. "Products (format: ID|Name|CurrentCategory):\n{$product_list}\n\n"
			. "For each product, decide if its current category is CORRECT or WRONG, and suggest the right one.\n"
			. "Return ONLY valid JSON array (no markdown):\n"
			. '[{"id":123,"suggested_category":"Lab Glassware","is_wrong":true,"confidence":"high"},...]'
			. "\nConfidence: high/medium/low. is_wrong: true only if current category is clearly incorrect.";

		$result = self::call( $prompt, $system, 2000 );
		if ( is_wp_error( $result ) ) wp_send_json_error( $result->get_error_message() );

		$clean  = preg_replace( '/```(?:json)?|```/', '', $result );
		// Extract JSON array
		preg_match( '/\[.*\]/s', $clean, $match );
		$parsed = json_decode( $match[0] ?? $clean, true );

		if ( ! is_array( $parsed ) ) wp_send_json_error( 'AI parsing failed. Try again.' );

		wp_send_json_success( $parsed );
	}

	private static function tone_desc( $tone ) {
		$tones = [
			'b2b_formal'    => 'Professional B2B formal tone — technical specs, quality certifications, bulk pricing focus',
			'educational'   => 'Educational tone — suitable for schools, colleges, research labs',
			'export'        => 'Export-oriented — international buyers, FOB pricing, compliance standards',
			'dealer'        => 'Dealer/distributor tone — margin benefits, bulk discounts, partnership focus',
			'concise'       => 'Concise and direct — bullet points, quick specs, no fluff',
		];
		return $tones[ $tone ] ?? $tones['b2b_formal'];
	}
}

SGC_PM_AI_Engine::register_ajax();
