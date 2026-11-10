<?php
defined( 'ABSPATH' ) || exit;

class SGC_PM_Competitor_Intel {

	public static function register_ajax() {
		$actions = [
			'sgc_pm_comp_get_list'      => 'ajax_get_list',
			'sgc_pm_comp_save'          => 'ajax_save',
			'sgc_pm_comp_delete'        => 'ajax_delete',
			'sgc_pm_comp_toggle'        => 'ajax_toggle',
			'sgc_pm_comp_scan_product'  => 'ajax_scan_product',
			'sgc_pm_comp_scan_all'      => 'ajax_scan_all',
			'sgc_pm_comp_get_report'    => 'ajax_get_report',
			'sgc_pm_comp_apply_ai_desc' => 'ajax_apply_ai_desc',
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

	// ── Get competitor list ────────────────────────────────────────────────
	public static function ajax_get_list() {
		self::verify();
		global $wpdb;
		$comps = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sgcpm_competitors ORDER BY name ASC" ); // phpcs:ignore
		wp_send_json_success( $comps );
	}

	public static function ajax_save() {
		self::verify();
		global $wpdb;
		$id      = (int) ( $_POST['comp_id'] ?? 0 );
		$name    = sanitize_text_field( $_POST['name'] ?? '' );
		$url     = sanitize_url( $_POST['url'] ?? '' );
		$cat     = sanitize_text_field( $_POST['category'] ?? '' );
		$country = sanitize_key( $_POST['country'] ?? 'US' );

		if ( ! $name || ! $url ) wp_send_json_error( 'Name and URL required.' );
		// Strip protocol for storage
		$url = preg_replace( '#^https?://#', '', rtrim( $url, '/' ) );

		$table = $wpdb->prefix . 'sgcpm_competitors';
		if ( $id ) {
			$wpdb->update( $table, compact( 'name', 'url', 'cat', 'country' ) + [ 'category' => $cat ], [ 'id' => $id ] ); // phpcs:ignore
		} else {
			$wpdb->insert( $table, [ 'name' => $name, 'url' => $url, 'category' => $cat, 'country' => $country, 'status' => 'active' ] ); // phpcs:ignore
			$id = $wpdb->insert_id;
		}
		wp_send_json_success( [ 'id' => $id ] );
	}

	public static function ajax_delete() {
		self::verify();
		global $wpdb;
		$id = (int) ( $_POST['comp_id'] ?? 0 );
		$wpdb->delete( $wpdb->prefix . 'sgcpm_competitors', [ 'id' => $id ] ); // phpcs:ignore
		wp_send_json_success();
	}

	public static function ajax_toggle() {
		self::verify();
		global $wpdb;
		$id     = (int) ( $_POST['comp_id'] ?? 0 );
		$status = sanitize_key( $_POST['status'] ?? 'active' );
		$wpdb->update( $wpdb->prefix . 'sgcpm_competitors', [ 'status' => $status ], [ 'id' => $id ] ); // phpcs:ignore
		wp_send_json_success();
	}

	// ── Scan one product vs all active competitors ─────────────────────────
	public static function ajax_scan_product() {
		self::verify();
		$product_id = (int) ( $_POST['product_id'] ?? 0 );
		$product    = wc_get_product( $product_id );
		if ( ! $product ) wp_send_json_error( 'Product not found.' );

		global $wpdb;
		$competitors = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sgcpm_competitors WHERE status='active' LIMIT 25" ); // phpcs:ignore
		if ( empty( $competitors ) ) wp_send_json_error( 'No active competitors.' );

		$results = [];
		foreach ( $competitors as $comp ) {
			$data = self::fetch_competitor_data( $product, $comp );
			if ( $data ) {
				$results[] = $data;
				self::save_competitor_data( $comp->id, $product_id, $data );
				usleep( 800000 ); // 0.8s between requests
			}
		}

		// AI gap analysis on all fetched data
		if ( ! empty( $results ) ) {
			$gap = self::ai_gap_analysis( $product, $results );
			if ( $gap && ! is_wp_error( $gap ) ) {
				// Save gap keywords to DB
				$all_gaps = [];
				foreach ( $results as $r ) {
					$all_gaps = array_merge( $all_gaps, $r['keywords'] ?? [] );
				}
				// Update all records for this product with gap analysis
				$wpdb->query( $wpdb->prepare( // phpcs:ignore
					"UPDATE {$wpdb->prefix}sgcpm_competitor_data SET gap_keywords=%s, ai_suggestion=%s WHERE product_id=%d",
					wp_json_encode( $gap['gap_keywords'] ?? [] ),
					$gap['suggested_description'] ?? '',
					$product_id
				) );
			}
		}

		SGC_PM_Logger::log( 'competitor_scan', 'product', $product_id, 'Scanned vs ' . count( $results ) . ' competitors' );
		wp_send_json_success( [ 'scanned' => count( $results ), 'results' => $results ] );
	}

	// ── Cron: scan all products vs all competitors ────────────────────────
	// ── Scan all (AJAX) — v3.0.9: was registered but never implemented → fatal ──
	public static function ajax_scan_all() {
		self::verify();
		if ( get_option( 'sgc_pm_competitor_enabled', '1' ) !== '1' ) {
			wp_send_json_error( 'Competitor Intelligence is disabled in Settings.' );
		}
		// Run in the background so the request does not time out on large catalogs.
		if ( ! wp_next_scheduled( 'sgc_pm_competitor_scan_now' ) ) {
			wp_schedule_single_event( time() + 5, 'sgc_pm_competitor_scan_now' );
		}
		spawn_cron();
		wp_send_json_success( 'Full competitor scan queued — it runs in the background (WP-Cron). Check the Logs tab for progress.' );
	}

	public static function run_scan_all() {
		if ( ! get_option( 'sgc_pm_competitor_enabled', '1' ) ) return;

		// Only scan top 50 products (by sales) to save time
		$product_ids = get_posts( [
			'post_type'      => 'product',
			'posts_per_page' => 50,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		global $wpdb;
		$competitors = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sgcpm_competitors WHERE status='active' LIMIT 25" ); // phpcs:ignore

		foreach ( $product_ids as $pid ) {
			$product = wc_get_product( $pid );
			if ( ! $product ) continue;
			foreach ( $competitors as $comp ) {
				$data = self::fetch_competitor_data( $product, $comp );
				if ( $data ) self::save_competitor_data( $comp->id, $pid, $data );
				usleep( 1000000 ); // 1s between requests
			}
		}

		SGC_PM_Logger::log( 'competitor_scan_all', 'bulk', null, 'Weekly competitor scan complete' );
	}

	// ── Fetch data from one competitor for one product ────────────────────
	private static function fetch_competitor_data( $product, $comp ) {
		$product_name = $product->get_name();
		$search_query = urlencode( $product_name . ' site:' . $comp->url );

		// Google Custom Search (free tier — 100/day)
		// Falls back to direct fetch if no API key
		$google_api_key = get_option( 'sgc_pm_google_search_key', '' );
		$google_cx      = get_option( 'sgc_pm_google_cx', '' );

		$their_title = '';
		$their_meta  = '';
		$their_kws   = [];
		$their_rank  = null;

		if ( $google_api_key && $google_cx ) {
			$url  = "https://www.googleapis.com/customsearch/v1?key={$google_api_key}&cx={$google_cx}&q=" . $search_query . '&num=5';
			$resp = wp_remote_get( $url, [ 'timeout' => 10 ] );
			if ( ! is_wp_error( $resp ) ) {
				$data = json_decode( wp_remote_retrieve_body( $resp ), true );
				if ( ! empty( $data['items'] ) ) {
					$first        = $data['items'][0];
					$their_title  = $first['title']       ?? '';
					$their_meta   = $first['snippet']     ?? '';
					$their_rank   = 1;
				}
			}
		} else {
			// Fallback: fetch competitor's home page meta tags
			$url  = 'https://' . $comp->url;
			$resp = wp_remote_get( $url, [
				'timeout'    => 12,
				'user-agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)',
				'headers'    => [ 'Accept-Language' => 'en-US,en;q=0.9' ],
			] );
			if ( ! is_wp_error( $resp ) ) {
				$html = wp_remote_retrieve_body( $resp );
				// Extract meta title
				if ( preg_match( '/<title[^>]*>([^<]+)<\/title>/i', $html, $m ) ) {
					$their_title = strip_tags( $m[1] );
				}
				// Extract meta description
				if ( preg_match( '/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/is', $html, $m ) ) {
					$their_meta = $m[1];
				}
				// Extract h1 keywords
				preg_match_all( '/<h[12][^>]*>([^<]+)<\/h[12]>/i', $html, $h_matches );
				$their_kws = array_map( 'strip_tags', array_slice( $h_matches[1] ?? [], 0, 5 ) );
			}
		}

		// Also get Google Suggest keywords for the product on this competitor's domain
		$suggest_kws = SGC_PM_AI_Engine::google_suggest( $product_name . ' ' . str_replace( '.com', '', explode( '.', $comp->url )[0] ) );
		$their_kws   = array_unique( array_merge( $their_kws, array_slice( $suggest_kws, 0, 5 ) ) );

		return [
			'competitor_id'   => $comp->id,
			'competitor_name' => $comp->name,
			'competitor_url'  => $comp->url,
			'their_title'     => sanitize_text_field( $their_title ),
			'their_meta_desc' => sanitize_text_field( $their_meta ),
			'keywords'        => $their_kws,
			'their_rank'      => $their_rank,
		];
	}

	// ── AI: find keyword gaps ────────────────────────────────────────────
	private static function ai_gap_analysis( $product, $competitor_results ) {
		$cats     = wp_get_post_terms( $product->get_id(), 'product_cat', [ 'fields' => 'names' ] );
		$our_desc = get_post_field( 'post_content', $product->get_id() );

		$comp_summary = '';
		foreach ( $competitor_results as $cr ) {
			$comp_summary .= "\n- {$cr['competitor_name']}: Title: {$cr['their_title']}. Keywords: " . implode( ', ', $cr['keywords'] );
		}

		$system = 'Expert SEO analyst for B2B laboratory glassware. Identify keyword gaps and write converting descriptions.';
		$prompt = "Our Product: {$product->get_name()}\nCategory: " . implode( ', ', is_array( $cats ) ? $cats : [] ) . "\nOur Current Description: " . substr( strip_tags( $our_desc ), 0, 300 ) . "\n\nCompetitor data:{$comp_summary}\n\nReturn ONLY JSON:\n{\"gap_keywords\":[\"keywords competitors use that we are missing\"],\"standard_codes\":[\"ASTM/ISO/DIN standards mentioned by competitors\"],\"missing_features\":[\"product features competitors highlight that we don't\"],\"suggested_description\":\"150 word description using gap keywords naturally\",\"focus_keyword\":\"best focus keyword\"}";

		$result = SGC_PM_AI_Engine::call( $prompt, $system, 800 );
		if ( is_wp_error( $result ) ) return null;

		$clean  = preg_replace( '/```(?:json)?|```/', '', $result );
		return json_decode( trim( $clean ), true );
	}

	// ── Save competitor data to DB ────────────────────────────────────────
	private static function save_competitor_data( $comp_id, $product_id, $data ) {
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'sgcpm_competitor_data', [ // phpcs:ignore
			'competitor_id'   => $comp_id,
			'product_id'      => $product_id,
			'their_title'     => $data['their_title'] ?? '',
			'their_meta_desc' => $data['their_meta_desc'] ?? '',
			'their_keywords'  => wp_json_encode( $data['keywords'] ?? [] ),
			'their_rank'      => $data['their_rank'],
			'fetched_at'      => current_time( 'mysql' ),
		] );
		// Update last_scan
		$wpdb->update( $wpdb->prefix . 'sgcpm_competitors', [ 'last_scan' => current_time( 'mysql' ) ], [ 'id' => $comp_id ] ); // phpcs:ignore
	}

	// ── Get report for a product ─────────────────────────────────────────
	public static function ajax_get_report() {
		self::verify();
		global $wpdb;
		$pid  = (int) ( $_POST['product_id'] ?? 0 );

		$data = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
			"SELECT cd.*, c.name as comp_name, c.url as comp_url, c.country
			 FROM {$wpdb->prefix}sgcpm_competitor_data cd
			 JOIN {$wpdb->prefix}sgcpm_competitors c ON c.id = cd.competitor_id
			 WHERE cd.product_id = %d
			 ORDER BY cd.fetched_at DESC",
			$pid
		) );

		// Get latest gap keywords
		$gap_kw = null;
		$ai_sug = null;
		foreach ( $data as $row ) {
			if ( $row->gap_keywords ) { $gap_kw = json_decode( $row->gap_keywords, true ); break; }
			if ( $row->ai_suggestion ) { $ai_sug = $row->ai_suggestion; break; }
		}

		wp_send_json_success( [ 'data' => $data, 'gap_keywords' => $gap_kw, 'ai_suggestion' => $ai_sug ] );
	}

	// ── Apply AI suggestion to product ───────────────────────────────────
	public static function ajax_apply_ai_desc() {
		self::verify();
		global $wpdb;
		$pid  = (int) ( $_POST['product_id'] ?? 0 );
		$desc = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
		$kw   = sanitize_text_field( $_POST['focus_keyword'] ?? '' );

		wp_update_post( [ 'ID' => $pid, 'post_excerpt' => $desc ] );
		if ( $kw ) {
			update_post_meta( $pid, 'rank_math_focus_keyword', $kw );
			update_post_meta( $pid, '_yoast_wpseo_focuskw', $kw );
		}
		SGC_PM_Logger::log( 'competitor_apply', 'product', $pid, 'Applied AI competitor-optimized description' );
		wp_send_json_success( 'Description applied.' );
	}
}

SGC_PM_Competitor_Intel::register_ajax();
