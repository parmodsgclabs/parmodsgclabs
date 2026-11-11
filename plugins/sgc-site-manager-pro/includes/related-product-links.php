<?php
/**
 * SGC LABS® Related Product Links Module
 *
 * @package     sgclabs-manager-pro
 * @subpackage  includes
 * @version     6.6.3
 * @author      SGC LABS®
 * @link        https://labsglass.com
 *
 * @description
 * Auto-inserts related product links inside product descriptions.
 * - Scans product name + category to find related products
 * - Inserts clickable links at end of short + main description
 * - Size/variant mention → direct link to that product's page
 * - Toggle to enable/disable per description type
 * - Batch processor: 5 products, 2s pause, Start/Stop
 * - Preview before run, confirm dialog
 * - Non-destructive: stored in meta, injected on display via filter
 *
 * @since       6.5.2
 * @since       6.6.2  Improved security, documentation, and WordPress standards
 */
if ( ! defined( 'ABSPATH' ) ) exit;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ============================================================================
// CORE FUNCTION: Build Related Product Links
// ============================================================================

/**
 * Build related product links for a given product
 *
 * Searches for products in the same category and returns top matches
 * based on keyword overlap with the product name.
 *
 * @since  6.5.2
 * @since  6.6.2  Added return type, improved documentation, better error handling
 *
 * @param  int $product_id   The WooCommerce product ID to find related products for.
 * @param  int $max          Maximum number of related products to return. Default 6.
 *
 * @return array             Array of related products with 'name' and 'url' keys.
 *                          Returns empty array if no related products found.
 */
function sgclabs_build_related_links( $product_id, $max = 6 ) {
	// Validate inputs
	$product_id = absint( $product_id );
	$max        = max( 1, min( 10, absint( $max ) ) );

	// Get the product
	if ( ! function_exists( 'wc_get_product' ) ) {
		return array();
	}

	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		return array();
	}

	// Get product categories
	$categories = wp_get_post_terms(
		$product_id,
		'product_cat',
		array( 'fields' => 'ids' )
	);

	// Return early if no categories
	if ( is_wp_error( $categories ) || empty( $categories ) ) {
		return array();
	}

	// Get product name and split into words for matching
	$product_name = $product->get_name();
	$name_words   = array_map(
		'strtolower',
		preg_split( '/[\s\-\/]+/', $product_name )
	);

	// Query for products in same categories
	$query_args = array(
		'post_type'      => 'product',
		'posts_per_page' => 50,
		'post__not_in'   => array( $product_id ),
		'tax_query'      => array(
			array(
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => $categories,
				'operator' => 'IN',
			),
		),
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
	);

	$related_query = new WP_Query( $query_args );
	$related_ids   = wp_list_pluck( $related_query->posts, 'ID' );
	wp_reset_postdata();

	// Score products based on name word overlap
	$scored = array();

	foreach ( $related_ids as $related_id ) {
		$related_product = wc_get_product( $related_id );

		// Skip if product is not available for purchase
		if ( ! $related_product || ! $related_product->is_purchasable() ) {
			continue;
		}

		$related_name   = $related_product->get_name();
		$related_words  = array_map(
			'strtolower',
			preg_split( '/[\s\-\/]+/', $related_name )
		);

		// Calculate overlap score (number of matching words)
		$score = count( array_intersect( $name_words, $related_words ) );

		// Only include products with at least one matching word
		if ( $score > 0 ) {
			$scored[] = array(
				'name'  => $related_name,
				'url'   => get_permalink( $related_id ),
				'score' => $score,
			);
		}
	}

	// Sort by score (highest first), then by name
	usort(
		$scored,
		function( $a, $b ) {
			if ( $a['score'] === $b['score'] ) {
				return strcmp( $a['name'], $b['name'] );
			}
			return $b['score'] - $a['score'];
		}
	);

	// Build final result array with top matches
	$result = array();
	foreach ( array_slice( $scored, 0, $max ) as $item ) {
		$result[] = array(
			'name' => $item['name'],
			'url'  => $item['url'],
		);
	}

	return $result;
}

// ============================================================================
// AJAX HANDLERS
// ============================================================================

/**
 * AJAX Handler: Preview related links for sample products
 *
 * Returns preview data for 5 random products without requiring processing.
 *
 * @since  6.5.2
 * @since  6.6.2  Improved security (capability check first), better error handling
 */
function sgclabs_related_links_ajax_preview() {
	// Security checks first
	check_ajax_referer( 'sgclabs_related_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized user' );
	}

	global $wpdb;

	// Get 5 recent products
	$product_ids = $wpdb->get_col(
		"SELECT ID FROM {$wpdb->posts}
		 WHERE post_type = 'product' AND post_status = 'publish'
		 ORDER BY ID DESC LIMIT 5"
	);

	if ( empty( $product_ids ) ) {
		wp_send_json_success(
			array(
				'rows'  => array(),
				'total' => 0,
				'done'  => 0,
			)
		);
	}

	$preview_data = array();

	foreach ( $product_ids as $product_id ) {
		$product_id = absint( $product_id );
		$links      = sgclabs_build_related_links( $product_id );

		$preview_data[] = array(
			'id'    => $product_id,
			'name'  => get_the_title( $product_id ),
			'count' => count( $links ),
			'links' => array_map(
				function( $link ) {
					return $link['name'];
				},
				$links
			),
		);
	}

	$total_products = absint(
		$wpdb->get_var(
			"SELECT COUNT(ID) FROM {$wpdb->posts}
			 WHERE post_type = 'product' AND post_status = 'publish'"
		)
	);

	$processed_products = absint(
		$wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
			 WHERE meta_key = '_sgclabs_related_links'"
		)
	);

	wp_send_json_success(
		array(
			'rows'  => $preview_data,
			'total' => $total_products,
			'done'  => $processed_products,
		)
	);
}
add_action( 'wp_ajax_sgclabs_related_preview', 'sgclabs_related_links_ajax_preview' );

/**
 * AJAX Handler: Run batch processor for generating related links
 *
 * Processes products in batches of 5 with 2 second pause between batches.
 *
 * @since  6.5.2
 * @since  6.6.2  Improved validation, error handling, security
 */
function sgclabs_related_links_ajax_run() {
	// Security checks
	check_ajax_referer( 'sgclabs_related_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized user' );
	}

	global $wpdb;

	// Validate and sanitize input parameters
	$offset           = max( 0, absint( $_POST['offset'] ?? 0 ) );
	$batch_size       = 5; // Fixed batch size for consistency
	$should_overwrite = ! empty( $_POST['overwrite'] );
	$max_links        = max( 1, min( 10, absint( $_POST['max_links'] ?? 6 ) ) );

	// Build query based on overwrite flag
	if ( ! $should_overwrite ) {
		// Get products without existing related links
		$product_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} pm 
					ON p.ID = pm.post_id AND pm.meta_key = '_sgclabs_related_links'
				 WHERE p.post_type = 'product' AND p.post_status = 'publish'
				 AND pm.meta_value IS NULL
				 ORDER BY p.ID ASC LIMIT %d OFFSET %d",
				$batch_size,
				$offset
			)
		);
	} else {
		// Get all products regardless of existing links
		$product_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				 WHERE post_type = 'product' AND post_status = 'publish'
				 ORDER BY ID ASC LIMIT %d OFFSET %d",
				$batch_size,
				$offset
			)
		);
	}

	// Get total and processed counts
	$total_products = absint(
		$wpdb->get_var(
			"SELECT COUNT(ID) FROM {$wpdb->posts}
			 WHERE post_type = 'product' AND post_status = 'publish'"
		)
	);

	$processed_count = 0;
	$error_messages  = array();

	// Process each product in the batch
	foreach ( $product_ids as $product_id ) {
		try {
			$product_id = absint( $product_id );
			$links      = sgclabs_build_related_links( $product_id, $max_links );

			// Always save (even if empty) to mark as processed
			$encoded_links = wp_json_encode( $links );
			if ( false === $encoded_links ) {
				$error_messages[] = sprintf(
					'Product %d: Failed to encode links data',
					$product_id
				);
				continue;
			}

			update_post_meta( $product_id, '_sgclabs_related_links', $encoded_links );
			$processed_count++;
		} catch ( Exception $e ) {
			$error_messages[] = sprintf(
				'Product %d: %s',
				$product_id,
				esc_html( $e->getMessage() )
			);
		}
	}

	// Get updated processed count
	$processed_total = absint(
		$wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
			 WHERE meta_key = '_sgclabs_related_links'"
		)
	);

	// Calculate progress percentage
	$progress_percent = $total_products > 0
		? min( 100, (int) round( $processed_total / $total_products * 100 ) )
		: 0;

	// Check if batch processing is finished
	$is_finished = count( $product_ids ) < $batch_size;

	wp_send_json_success(
		array(
			'processed'   => $processed_count,
			'total'       => $total_products,
			'done'        => $processed_total,
			'percent'     => $progress_percent,
			'finished'    => $is_finished,
			'next_offset' => $offset + $batch_size,
			'errors'      => $error_messages,
		)
	);
}
add_action( 'wp_ajax_sgclabs_related_run', 'sgclabs_related_links_ajax_run' );

/**
 * AJAX Handler: Reset all related links data
 *
 * Deletes all stored related links metadata for all products.
 * Warning: This action cannot be undone without re-running the batch processor.
 *
 * @since  6.5.2
 * @since  6.6.2  Improved security, better error handling
 */
function sgclabs_related_links_ajax_reset() {
	// Security checks
	check_ajax_referer( 'sgclabs_related_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized user' );
	}

	global $wpdb;

	// Delete all related links metadata
	$deleted = $wpdb->delete(
		$wpdb->postmeta,
		array( 'meta_key' => '_sgclabs_related_links' ),
		array( '%s' )
	);

	if ( false === $deleted ) {
		wp_send_json_error( 'Database error: could not reset data' );
	}

	wp_send_json_success( 'All related links data has been reset.' );
}
add_action( 'wp_ajax_sgclabs_related_reset', 'sgclabs_related_links_ajax_reset' );

/**
 * AJAX Handler: Save display settings
 *
 * Saves user preferences for related links display (toggles, label, color).
 *
 * @since  6.5.2
 * @since  6.6.2  Improved input validation, sanitization
 */
function sgclabs_related_links_ajax_save_settings() {
	// Security checks
	check_ajax_referer( 'sgclabs_related_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized user' );
	}

	// Validate and sanitize inputs
	$enable_short = ! empty( $_POST['enable_short'] ) ? '1' : '0';
	$enable_main  = ! empty( $_POST['enable_main'] ) ? '1' : '0';
	$label        = sanitize_text_field( $_POST['label'] ?? 'Also available:' );
	$color        = sanitize_hex_color( $_POST['color'] ?? '#1a1a2e' );

	// Validate color
	if ( empty( $color ) ) {
		$color = '#1a1a2e';
	}

	// Update options
	update_option( 'sgclabs_related_links_short', $enable_short );
	update_option( 'sgclabs_related_links_main', $enable_main );
	update_option( 'sgclabs_related_links_label', $label );
	update_option( 'sgclabs_related_links_color', $color );

	wp_send_json_success( 'Settings have been saved successfully.' );
}
add_action( 'wp_ajax_sgclabs_related_save_settings', 'sgclabs_related_links_ajax_save_settings' );

// ============================================================================
// ADMIN PAGE: Related Product Links Manager
// ============================================================================

/**
 * Render the Related Product Links admin page
 *
 * Displays UI for managing related links generation with batch processor,
 * preview functionality, and configuration options.
 *
 * @since  6.5.2
 * @since  6.6.2  Improved escaping, security, documentation
 */
function sgclabs_related_links_page() {
	// Permission check
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'sgclabs-manager' ) );
	}

	global $wpdb;

	// Get statistics
	$total_products    = absint(
		$wpdb->get_var(
			"SELECT COUNT(ID) FROM {$wpdb->posts}
			 WHERE post_type = 'product' AND post_status = 'publish'"
		)
	);
	$processed_products = absint(
		$wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
			 WHERE meta_key = '_sgclabs_related_links'"
		)
	);

	// Get theme colors
	$accent_color = esc_attr( get_option( 'sgclabs_claude_accent_color', '#27ae60' ) );
	$brand_color  = esc_attr( get_option( 'sgclabs_claude_brand_color', '#1a1a2e' ) );

	// Create nonce for security
	$nonce      = wp_create_nonce( 'sgclabs_related_nonce' );
	$ajax_url   = esc_js( admin_url( 'admin-ajax.php' ) );
	$percentage = $total_products > 0 ? round( $processed_products / $total_products * 100 ) : 0;

	// Get current settings
	$enable_short = get_option( 'sgclabs_related_links_short', '1' );
	$enable_main  = get_option( 'sgclabs_related_links_main', '1' );
	$label_text   = get_option( 'sgclabs_related_links_label', 'Also available:' );
	$label_color  = get_option( 'sgclabs_related_links_color', '#1a1a2e' );

	?>
	<style>
	.sgrl-wrap {
		max-width: 960px;
		font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
		padding-bottom: 60px;
	}
	.sgrl-head {
		background: linear-gradient(135deg, <?php echo esc_attr( $brand_color ); ?>, <?php echo esc_attr( $accent_color ); ?>);
		padding: 24px 28px;
		border-radius: 10px;
		margin: 20px 0 22px;
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
	}
	.sgrl-head h1 {
		color: #fff;
		margin: 0;
		font-size: 22px;
		font-weight: 700;
	}
	.sgrl-head p {
		color: rgba(255, 255, 255, 0.82);
		margin: 5px 0 0;
		font-size: 13px;
	}
	.sgrl-card {
		background: #fff;
		border: 1px solid #e2e8f0;
		border-radius: 10px;
		padding: 22px;
		margin-bottom: 18px;
	}
	.sgrl-card h2 {
		font-size: 15px;
		font-weight: 700;
		margin: 0 0 14px;
		color: #1e293b;
	}
	.sgrl-stats {
		display: grid;
		grid-template-columns: repeat(3, 1fr);
		gap: 12px;
		margin-bottom: 20px;
	}
	.sgrl-stat {
		background: #fff;
		border: 1px solid #e2e8f0;
		border-radius: 8px;
		padding: 14px;
		text-align: center;
	}
	.sgrl-stat .n {
		font-size: 26px;
		font-weight: 700;
		color: #1e293b;
	}
	.sgrl-stat .l {
		font-size: 11px;
		color: #64748b;
		margin-top: 3px;
		text-transform: uppercase;
		letter-spacing: 0.5px;
	}
	.sgrl-pb-wrap {
		background: #f1f5f9;
		border-radius: 5px;
		height: 8px;
		margin: 10px 0 4px;
		overflow: hidden;
	}
	.sgrl-pb {
		height: 100%;
		background: linear-gradient(90deg, <?php echo esc_attr( $accent_color ); ?>, <?php echo esc_attr( $brand_color ); ?>);
		border-radius: 5px;
		transition: width 0.4s ease-in-out;
	}
	.sgrl-ptxt {
		font-size: 12px;
		color: #64748b;
		margin-bottom: 12px;
	}
	.sgrl-tgl-row {
		display: flex;
		flex-wrap: wrap;
		gap: 14px;
		margin-bottom: 14px;
	}
	.sgrl-tgl {
		display: flex;
		align-items: center;
		gap: 8px;
		font-size: 13px;
		cursor: pointer;
		color: #374151;
		user-select: none;
	}
	.sgrl-tog {
		-webkit-appearance: none;
		appearance: none;
		width: 36px;
		height: 20px;
		background: #cbd5e1;
		border-radius: 10px;
		position: relative;
		cursor: pointer;
		transition: background 0.2s ease;
		flex-shrink: 0;
		border: none;
		padding: 0;
	}
	.sgrl-tog:checked {
		background: <?php echo esc_attr( $accent_color ); ?>;
	}
	.sgrl-tog::after {
		content: '';
		position: absolute;
		top: 2px;
		left: 2px;
		width: 16px;
		height: 16px;
		background: #fff;
		border-radius: 50%;
		transition: left 0.2s ease;
		box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
	}
	.sgrl-tog:checked::after {
		left: 18px;
	}
	.sgrl-btns {
		display: flex;
		gap: 10px;
		flex-wrap: wrap;
		margin-bottom: 10px;
	}
	.sgrl-btn {
		padding: 8px 20px;
		border-radius: 6px;
		font-size: 13px;
		font-weight: 700;
		cursor: pointer;
		border: none;
		transition: all 0.2s ease;
	}
	.sgrl-btn:disabled {
		opacity: 0.4;
		cursor: not-allowed;
	}
	.sgrl-btn:hover:not(:disabled) {
		transform: translateY(-1px);
		box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
	}
	.sgrl-go {
		background: #16a34a;
		color: #fff;
	}
	.sgrl-off {
		background: #dc2626;
		color: #fff;
	}
	.sgrl-rst {
		background: #64748b;
		color: #fff;
	}
	.sgrl-save {
		background: <?php echo esc_attr( $accent_color ); ?>;
		color: #fff;
	}
	.sgrl-st {
		border-radius: 6px;
		padding: 10px 14px;
		font-size: 13px;
		margin-top: 8px;
		display: none;
	}
	.sgrl-info {
		background: #eff6ff;
		border: 1px solid #93c5fd;
		color: #1d4ed8;
	}
	.sgrl-err {
		background: #fef2f2;
		border: 1px solid #fecaca;
		color: #991b1b;
	}
	.sgrl-done {
		background: #f0fdf4;
		border: 2px solid #86efac;
		border-radius: 8px;
		padding: 14px;
		margin-top: 10px;
		color: #166534;
		font-weight: 700;
		display: none;
	}
	.sgrl-tbl {
		width: 100%;
		border-collapse: collapse;
		font-size: 12px;
	}
	.sgrl-tbl th {
		background: #f8fafc;
		padding: 8px 10px;
		text-align: left;
		border-bottom: 2px solid #e2e8f0;
		font-size: 11px;
		color: #475569;
		text-transform: uppercase;
		letter-spacing: 0.5px;
		font-weight: 700;
	}
	.sgrl-tbl td {
		padding: 8px 10px;
		border-bottom: 1px solid #f1f5f9;
		vertical-align: top;
	}
	.sgrl-tbl tr:hover td {
		background: #f8fafc;
	}
	.pill {
		display: inline-block;
		padding: 2px 9px;
		border: 1px solid #e2e8f0;
		border-radius: 20px;
		font-size: 11px;
		color: #374151;
		margin: 2px;
		background: #f8fafc;
		font-weight: 500;
	}
	.sgrl-preview-box {
		background: #f8fafc;
		border-left: 3px solid <?php echo esc_attr( $label_color ); ?>;
		border-radius: 0 6px 6px 0;
		padding: 12px 16px;
		margin-top: 12px;
	}
	.sgrl-preview-box p {
		margin: 0 0 8px;
		font-size: 13px;
		font-weight: 700;
		color: <?php echo esc_attr( $label_color ); ?>;
	}
	.sgrl-preview-box a {
		display: inline-block;
		padding: 4px 12px;
		background: #fff;
		border: 1px solid <?php echo esc_attr( $label_color ); ?>;
		border-radius: 20px;
		font-size: 12px;
		color: <?php echo esc_attr( $label_color ); ?>;
		text-decoration: none;
		margin: 2px;
		font-weight: 500;
		transition: all 0.2s ease;
	}
	.sgrl-preview-box a:hover {
		background: <?php echo esc_attr( $label_color ); ?>;
		color: #fff;
	}
	@media (max-width: 768px) {
		.sgrl-stats {
			grid-template-columns: 1fr;
		}
		.sgrl-tgl-row {
			flex-direction: column;
			gap: 10px;
		}
	}
	</style>

	<div class="wrap sgrl-wrap">
<?php if(function_exists("sgclabs_module_control_bar")) sgclabs_module_control_bar(["module_id"=>"related-links","module_name"=>"Related Product Links","module_icon"=>"🔗","option_key"=>"sgclabs_related_links_active","default"=>"1","description"=>"Auto-generate internal links between related products","modes"=>[["value"=>"auto","label"=>"Auto Generate","desc"=>"AI finds related products by category/tags"],["value"=>"manual","label"=>"Manual Only","desc"=>"Only manually assigned related links"]],"mode_key"=>"sgclabs_related_mode","mode_default"=>"auto"]); ?>
		<div class="sgrl-head">
			<h1><?php esc_html_e( 'Related Product Links', 'sgclabs-manager' ); ?> <span style="font-size: 13px; opacity: 0.7; font-weight: 400;">v6.6.3</span></h1>
			<p><?php esc_html_e( 'Auto-insert related product links into product descriptions — same-category products, clickable pill links', 'sgclabs-manager' ); ?></p>
		</div>

		<!-- Statistics -->
		<div class="sgrl-stats">
			<div class="sgrl-stat">
				<div class="n" style="color: <?php echo esc_attr( $brand_color ); ?>">
					<?php echo esc_html( number_format( $total_products ) ); ?>
				</div>
				<div class="l"><?php esc_html_e( 'Total Products', 'sgclabs-manager' ); ?></div>
			</div>
			<div class="sgrl-stat">
				<div class="n" style="color: #16a34a;">
					<?php echo esc_html( number_format( $processed_products ) ); ?>
				</div>
				<div class="l"><?php esc_html_e( 'Links Generated', 'sgclabs-manager' ); ?></div>
			</div>
			<div class="sgrl-stat">
				<div class="n" style="color: #dc2626;">
					<?php echo esc_html( number_format( $total_products - $processed_products ) ); ?>
				</div>
				<div class="l"><?php esc_html_e( 'Pending', 'sgclabs-manager' ); ?></div>
			</div>
		</div>

		<!-- Display Settings -->
		<div class="sgrl-card">
			<h2><?php esc_html_e( 'Display Settings', 'sgclabs-manager' ); ?></h2>
			<div class="sgrl-tgl-row">
				<label class="sgrl-tgl">
					<input type="checkbox" class="sgrl-tog" id="sgrl-en-short" <?php checked( $enable_short, '1' ); ?>>
					<span><?php esc_html_e( 'Inject into Short Description', 'sgclabs-manager' ); ?></span>
				</label>
				<label class="sgrl-tgl">
					<input type="checkbox" class="sgrl-tog" id="sgrl-en-main" <?php checked( $enable_main, '1' ); ?>>
					<span><?php esc_html_e( 'Inject into Main Description', 'sgclabs-manager' ); ?></span>
				</label>
			</div>
			<div style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap; margin-bottom: 14px;">
				<label style="font-size: 13px; color: #374151;">
					<?php esc_html_e( 'Section Label:', 'sgclabs-manager' ); ?>
					<input type="text" id="sgrl-label" value="<?php echo esc_attr( $label_text ); ?>" 
						style="margin-left: 8px; padding: 6px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px; width: 200px;">
				</label>
				<label style="font-size: 13px; color: #374151;">
					<?php esc_html_e( 'Link Color:', 'sgclabs-manager' ); ?>
					<input type="color" id="sgrl-color" value="<?php echo esc_attr( $label_color ); ?>" 
						style="margin-left: 8px; width: 40px; height: 32px; border: 1px solid #e2e8f0; border-radius: 4px; cursor: pointer;">
				</label>
			</div>
			<button id="sgrl-save-settings" class="sgrl-btn sgrl-save"><?php esc_html_e( 'Save Settings', 'sgclabs-manager' ); ?></button>
			<span id="sgrl-save-msg" style="display: none; margin-left: 10px; color: #16a34a; font-size: 13px;">
				<?php esc_html_e( 'Saved!', 'sgclabs-manager' ); ?>
			</span>

			<!-- Live Preview -->
			<div class="sgrl-preview-box" id="sgrl-prev-box">
				<p id="sgrl-prev-label"><?php echo esc_html( $label_text ); ?></p>
				<div>
					<a href="#" onclick="return false;">Borosilicate Flask 500ml</a>
					<a href="#" onclick="return false;">Lab Condenser</a>
					<a href="#" onclick="return false;">Measuring Cylinder</a>
					<a href="#" onclick="return false;">Glass Burette</a>
				</div>
			</div>
		</div>

		<!-- Batch Generator -->
		<div class="sgrl-card">
			<h2><?php esc_html_e( 'Generate Related Links', 'sgclabs-manager' ); ?></h2>

			<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px; flex-wrap: wrap;">
				<label style="font-size: 13px; color: #374151;">
					<?php esc_html_e( 'Max links per product:', 'sgclabs-manager' ); ?>
					<select id="sgrl-max" style="margin-left: 8px; padding: 6px 10px; border: 1px solid #e2e8f0; border-radius: 6px;">
						<option value="3">3</option>
						<option value="4">4</option>
						<option value="5">5</option>
						<option value="6" selected>6</option>
						<option value="8">8</option>
					</select>
				</label>
				<label class="sgrl-tgl">
					<input type="checkbox" class="sgrl-tog" id="sgrl-overwrite">
					<span><?php esc_html_e( 'Overwrite existing', 'sgclabs-manager' ); ?></span>
				</label>
			</div>

			<div class="sgrl-pb-wrap">
				<div class="sgrl-pb" id="sgrl-bar" style="width: <?php echo esc_attr( (int) $percentage ); ?>%"></div>
			</div>
			<div class="sgrl-ptxt" id="sgrl-ptxt">
				<?php
				echo esc_html(
					sprintf(
						'%s / %s (%s%%)',
						number_format( $processed_products ),
						number_format( $total_products ),
						$percentage
					)
				);
				?>
			</div>

			<div class="sgrl-btns">
				<button class="sgrl-btn sgrl-go" id="sgrl-start">▶ <?php esc_html_e( 'Generate', 'sgclabs-manager' ); ?></button>
				<button class="sgrl-btn sgrl-off" id="sgrl-stop" style="display: none;">■ <?php esc_html_e( 'Stop', 'sgclabs-manager' ); ?></button>
				<button class="sgrl-btn sgrl-rst" id="sgrl-reset">↻ <?php esc_html_e( 'Reset All', 'sgclabs-manager' ); ?></button>
				<button class="sgrl-btn" style="background: #2563eb; color: #fff;" id="sgrl-preview-btn">
					<?php esc_html_e( 'Preview 5 Products', 'sgclabs-manager' ); ?>
				</button>
			</div>
			<div class="sgrl-st sgrl-info" id="sgrl-status"></div>
			<div class="sgrl-done" id="sgrl-done">
				<?php esc_html_e( 'All products processed! Related links generated.', 'sgclabs-manager' ); ?>
			</div>
		</div>

		<!-- Preview Table -->
		<div class="sgrl-card" id="sgrl-preview-wrap" style="display: none;">
			<h2><?php esc_html_e( 'Preview — Related Links Sample', 'sgclabs-manager' ); ?></h2>
			<div id="sgrl-preview-body"></div>
		</div>
	</div>

	<script>
	(function($) {
		'use strict';

		var NONCE = '<?php echo esc_js( $nonce ); ?>';
		var AJAX = '<?php echo esc_js( $ajax_url ); ?>';
		var TOTAL = <?php echo absint( $total_products ); ?>;
		var PAUSE = 2000; // milliseconds

		var running = false;
		var stopped = false;
		var offset = 0;

		/**
		 * Update live preview with current label and color settings
		 */
		function updatePreview() {
			var lbl = $('#sgrl-label').val() || 'Also available:';
			var col = $('#sgrl-color').val() || '#1a1a2e';
			$('#sgrl-prev-label').text(lbl).css('color', col);
			$('#sgrl-prev-box').css('border-left-color', col);
			$('#sgrl-prev-box a').css({'border-color': col, 'color': col});
		}

		// Update preview on input
		$('#sgrl-label, #sgrl-color').on('input change', updatePreview);

		// Save settings
		$('#sgrl-save-settings').on('click', function(e) {
			e.preventDefault();
			$.post(AJAX, {
				action: 'sgclabs_related_save_settings',
				nonce: NONCE,
				enable_short: $('#sgrl-en-short').is(':checked') ? 1 : 0,
				enable_main: $('#sgrl-en-main').is(':checked') ? 1 : 0,
				label: $('#sgrl-label').val(),
				color: $('#sgrl-color').val(),
			}, function(res) {
				if (res && res.success) {
					$('#sgrl-save-msg').show();
					setTimeout(function() {
						$('#sgrl-save-msg').hide();
					}, 2500);
				} else {
					alert('<?php esc_html_e( 'Save failed.', 'sgclabs-manager' ); ?>');
				}
			});
		});

		// Batch generator
		$('#sgrl-start').on('click', function(e) {
			e.preventDefault();

			if (running) {
				alert('<?php esc_html_e( 'Already running!', 'sgclabs-manager' ); ?>');
				return;
			}

			var overwrite = $('#sgrl-overwrite').is(':checked');
			var maxLinks = parseInt($('#sgrl-max').val()) || 6;

			var msg = overwrite
				? '<?php esc_html_e( 'Regenerate related links for ALL', 'sgclabs-manager' ); ?> ' + TOTAL + ' <?php esc_html_e( 'products?', 'sgclabs-manager' ); ?>\n\n' +
				  '<?php esc_html_e( 'This will overwrite existing data. Proceed?', 'sgclabs-manager' ); ?>'
				: '<?php esc_html_e( 'Generate related links for products without existing data?', 'sgclabs-manager' ); ?>\n\n' +
				  'Batch: 5 | Pause: 2s | Total: ' + TOTAL + '\n\n<?php esc_html_e( 'Proceed?', 'sgclabs-manager' ); ?>';

			if (!confirm(msg)) {
				return;
			}

			running = true;
			stopped = false;
			offset = 0;

			$(this).hide();
			$('#sgrl-stop').show();
			$('#sgrl-status').addClass('sgrl-info').removeClass('sgrl-err').show();
			$('#sgrl-done').hide();

			runBatch();

			function runBatch() {
				if (stopped) {
					$('#sgrl-status').text('<?php esc_html_e( 'Stopped at offset', 'sgclabs-manager' ); ?> ' + offset + '.');
					idle();
					return;
				}

				$('#sgrl-status').text('<?php esc_html_e( 'Processing batch at offset', 'sgclabs-manager' ); ?> ' + offset + '...');

				$.post(AJAX, {
					action: 'sgclabs_related_run',
					nonce: NONCE,
					offset: offset,
					overwrite: overwrite ? 1 : 0,
					max_links: maxLinks,
				}, function(res) {
					if (!res || !res.success) {
						$('#sgrl-status').removeClass('sgrl-info').addClass('sgrl-err').text('<?php esc_html_e( 'Error:', 'sgclabs-manager' ); ?> ' + ((res && res.data) ? res.data : '<?php esc_html_e( 'Unknown', 'sgclabs-manager' ); ?>'));
						idle();
						return;
					}

					var data = res.data;
					$('#sgrl-bar').css('width', data.percent + '%');
					$('#sgrl-ptxt').text(data.done + ' / ' + data.total + ' (' + data.percent + '%)');
					$('#sgrl-status').text(data.done + ' / ' + data.total + ' <?php esc_html_e( 'done', 'sgclabs-manager' ); ?> (' + data.percent + '%)');

					if (data.finished) {
						running = false;
						$('#sgrl-stop').hide();
						$('#sgrl-start').show();
						$('#sgrl-status').hide();
						$('#sgrl-done').show();
						return;
					}

					offset = data.next_offset;
					setTimeout(runBatch, PAUSE);
				}).fail(function(xhr) {
					$('#sgrl-status').removeClass('sgrl-info').addClass('sgrl-err').text('<?php esc_html_e( 'AJAX error (HTTP', 'sgclabs-manager' ); ?> ' + xhr.status + ').');
					idle();
				});
			}
		});

		$('#sgrl-stop').on('click', function(e) {
			e.preventDefault();
			stopped = true;
			running = false;
			$(this).hide();
		});

		$('#sgrl-reset').on('click', function(e) {
			e.preventDefault();

			if (!confirm('<?php esc_html_e( 'Delete ALL generated related links data?', 'sgclabs-manager' ); ?>\n\n<?php esc_html_e( 'This cannot be undone.', 'sgclabs-manager' ); ?>')) {
				return;
			}

			$.post(AJAX, {
				action: 'sgclabs_related_reset',
				nonce: NONCE,
			}, function(res) {
				if (res && res.success) {
					alert('<?php esc_html_e( 'Reset done!', 'sgclabs-manager' ); ?>');
					location.reload();
				} else {
					alert('<?php esc_html_e( 'Error:', 'sgclabs-manager' ); ?> ' + ((res && res.data) ? res.data : '<?php esc_html_e( 'Unknown', 'sgclabs-manager' ); ?>'));
				}
			});
		});

		// Preview
		$('#sgrl-preview-btn').on('click', function(e) {
			e.preventDefault();
			$(this).prop('disabled', true).text('<?php esc_html_e( 'Loading...', 'sgclabs-manager' ); ?>');

			$.post(AJAX, {
				action: 'sgclabs_related_preview',
				nonce: NONCE,
			}, function(res) {
				$('#sgrl-preview-btn').prop('disabled', false).text('<?php esc_html_e( 'Preview 5 Products', 'sgclabs-manager' ); ?>');

				if (!res || !res.success) {
					alert('<?php esc_html_e( 'Preview error.', 'sgclabs-manager' ); ?>');
					return;
				}

				var rows = res.data.rows;
				var html = '<table class="sgrl-tbl"><thead><tr><th>#</th><th><?php esc_html_e( 'Product', 'sgclabs-manager' ); ?></th><th><?php esc_html_e( 'Related Links Found', 'sgclabs-manager' ); ?></th><th><?php esc_html_e( 'Link Names', 'sgclabs-manager' ); ?></th></tr></thead><tbody>';

				rows.forEach(function(row) {
					var pills = row.links.map(function(name) {
						return '<span class="pill">' + escapeHtml(name) + '</span>';
					}).join('');

					html += '<tr><td style="color: #94a3b8;">' + row.id + '</td><td><strong>' + escapeHtml(row.name) + '</strong></td>' +
						'<td style="text-align: center; font-weight: 700; color: ' + (row.count > 0 ? '#16a34a' : '#dc2626') + ';">' + row.count + '</td>' +
						'<td>' + (pills || '<span style="color: #94a3b8;">No related products found in same category</span>') + '</td></tr>';
				});

				html += '</tbody></table>';
				$('#sgrl-preview-body').html(html);
				$('#sgrl-preview-wrap').show();
				$('html, body').animate({scrollTop: $('#sgrl-preview-wrap').offset().top - 60}, 350);
			}).fail(function(xhr) {
				$('#sgrl-preview-btn').prop('disabled', false).text('<?php esc_html_e( 'Preview 5 Products', 'sgclabs-manager' ); ?>');
				alert('<?php esc_html_e( 'AJAX error (HTTP', 'sgclabs-manager' ); ?> ' + xhr.status + ').');
			});
		});

		/**
		 * Utilities
		 */
		function idle() {
			running = false;
			$('#sgrl-start').show();
			$('#sgrl-stop').hide();
		}

		function escapeHtml(text) {
			var map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return String(text || '').replace(/[&<>"']/g, function(m) {
				return map[m];
			});
		}
	})(jQuery);
	</script>
	<?php
}
