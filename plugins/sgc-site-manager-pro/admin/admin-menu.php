<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', 'sgclabs_admin_menu' );
function sgclabs_admin_menu() {
	add_menu_page( 'SGC LABS® Manager Pro', 'SGC LABS® Pro', 'manage_options', 'sgclabs-manager', 'sgclabs_dashboard_page', 'dashicons-flask', 30 );
	add_submenu_page( 'sgclabs-manager', 'Dashboard',         '🏠 Dashboard',          'manage_options', 'sgclabs-manager',          'sgclabs_dashboard_page' );
	add_submenu_page( 'sgclabs-manager', 'Module Browser',    '📚 Module Browser',     'manage_options', 'sgclabs-module-browser',   'sgclabs_module_browser_page' );
	add_submenu_page( 'sgclabs-manager', 'Feature Controls',  '🎛️ Feature Controls',   'manage_options', 'sgclabs-feature-control',  'sgclabs_feature_control_page' );
	add_submenu_page( 'sgclabs-manager', 'AI Center',         '🧠 AI Center',          'manage_options', 'sgclabs-ai-center',        'sgclabs_ai_center_router' );
	add_submenu_page( 'sgclabs-manager', 'SEO Suite',         '🔍 SEO Suite',          'manage_options', 'sgclabs-seo-suite',        'sgclabs_seo_suite_router' );
	add_submenu_page( 'sgclabs-manager', 'Quotes & Inquiries','💬 Quotes & Inquiries', 'manage_options', 'sgclabs-quotes-inquiries', 'sgclabs_quotes_inquiries_router' );
	add_submenu_page( 'sgclabs-manager', 'Site Tools',        '🛠️ Site Tools',         'manage_options', 'sgclabs-site-tools-hub',   'sgclabs_site_tools_router' );
	add_submenu_page( 'sgclabs-manager', 'WooCommerce',       '🛒 WooCommerce',        'manage_options', 'sgclabs-woo-hub',          'sgclabs_woo_hub_router' );
	add_submenu_page( 'sgclabs-manager', 'International',     '🌍 International',      'manage_options', 'sgclabs-international',    'sgclabs_international_router' );
	add_submenu_page( 'sgclabs-manager', 'Security Audit',    '🛡️ Security Audit',     'manage_options', 'sgclabs-security-audit',   'sgclabs_security_audit_page' );
	add_submenu_page( 'sgclabs-manager', 'Background Processor','🖥️ Background Processor','manage_options', 'sgclabs-bg-processor',    'sgclabs_bg_processor_page' );
	add_submenu_page( 'sgclabs-manager', 'SEO Migrator',      '🔄 SEO Migrator',       'manage_options', 'sgclabs-seo-migrator',     'sgclabs_seo_migrator_page' );
	add_submenu_page( 'sgclabs-manager', 'Redirections',      '↩️ Redirections',       'manage_options', 'sgclabs-redirections',     'sgclabs_redirections_page' );
	add_submenu_page( 'sgclabs-manager', 'Performance',       '⚡ Performance',         'manage_options', 'sgclabs-performance',      'sgclabs_performance_page' );
	add_submenu_page( 'sgclabs-manager', 'Broken Links',      '🔗 Broken Links',       'manage_options', 'sgclabs-broken-links',     'sgclabs_broken_links_page' );
	add_submenu_page( 'sgclabs-manager', 'General Settings',  '⚙️ General Settings',   'manage_options', 'sgclabs-general',          'sgclabs_general_settings_page' );
}

function sgclabs_international_router() {
	$tab = sanitize_key( $_GET['tab'] ?? 'overview' );
	$tabs = [
		'overview' => [ 'label' => '🌍 Overview',     'callback' => 'sgclabs_international_overview_page' ],
		'currency' => [ 'label' => '💱 Currencies',   'callback' => 'sgclabs_international_currency_page' ],
		'gdpr'     => [ 'label' => '🍪 GDPR Log',     'callback' => 'sgclabs_international_gdpr_page'    ],
		'rest'     => [ 'label' => '🔌 REST API',     'callback' => 'sgclabs_international_rest_page'    ],
	];
	sgclabs_render_tabbed_page( 'International', $tabs, $tab, 'sgclabs-international' );
}

function sgclabs_international_overview_page() {
	$sig = class_exists( 'SGCLabs_Updater' ) ? SGCLabs_Updater::label() : '';
	echo '<div class="wrap"><h1>🌍 International Edition</h1>';
	echo '<p style="font-size:13px;color:#475569;">v6.9.0 international features — har ek module ko Module Browser se ON/OFF kar sakte ho.</p>';
	echo '<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px;margin:14px 0;">';
	echo '<strong>Build signature:</strong> <code>' . esc_html( $sig ) . '</code>';
	echo '</div>';
	echo '<ul style="font-size:13px;line-height:1.9;">';
	echo '<li>🍪 <strong>GDPR Cookie Consent</strong> — granular categories, consent log, anonymous IP storage</li>';
	echo '<li>💱 <strong>Multi-Currency Switcher</strong> — INR / USD / EUR / GBP / AED / SAR / JPY / AUD with auto-detect</li>';
	echo '<li>❤️ <strong>B2B Wishlist</strong> — Save for Quote, syncs to user account when logged in</li>';
	echo '<li>👁️ <strong>Recently Viewed</strong> — last 12 products, cookie-based</li>';
	echo '<li>⚖️ <strong>Product Compare</strong> — up to 4 products side-by-side</li>';
	echo '<li>⚡ <strong>AJAX Quick View</strong> — modal preview from listings</li>';
	echo '<li>🔌 <strong>REST API</strong> — <code>/wp-json/sgclabs/v1/products</code>, <code>/health</code>, <code>/quote</code>, <code>/search</code></li>';
	echo '<li>🔒 <strong>Login Hardening</strong> — failed-login throttle, generic errors, bot blocker</li>';
	echo '</ul></div>';
}

function sgclabs_international_currency_page() {
	$current_default = sgclabs_currency_code();

	// ---- A8: Handle "Set as Default Currency" submission --------------
	if ( ! empty( $_POST['sgc_set_default_currency'] ) && check_admin_referer( 'sgc_set_default_currency' ) ) {
		$new_default = isset( $_POST['default_currency'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_POST['default_currency'] ) ) )
			: '';
		if ( sgclabs_currency_set_default( $new_default ) ) {
			$current_default = $new_default;
			echo '<div class="notice notice-success is-dismissible"><p>✅ Default currency set to <strong>'
				. esc_html( $new_default )
				. '</strong>. WooCommerce store currency updated to match.</p></div>';
		} else {
			echo '<div class="notice notice-error is-dismissible"><p>❌ Invalid currency code.</p></div>';
		}
	}

	// ---- Existing rate save (only relevant if multi-currency module is active) ----
	$multi_currency_loaded = class_exists( 'SGCLabs_Multi_Currency' );
	if ( $multi_currency_loaded && ! empty( $_POST['sgc_currency_save'] ) && check_admin_referer( 'sgc_currency_save' ) ) {
		$rates_in = wp_unslash( $_POST['rates'] ?? [] );
		$out      = [];
		$defaults = SGCLabs_Multi_Currency::default_rates();
		foreach ( $defaults as $code => $def ) {
			$rate         = isset( $rates_in[ $code ]['rate'] ) ? (float) $rates_in[ $code ]['rate'] : $def['rate'];
			$out[ $code ] = [
				'symbol' => $def['symbol'],
				'rate'   => $rate,
				'label'  => $def['label'],
			];
		}
		update_option( SGCLabs_Multi_Currency::OPTION_RATES, $out );
		echo '<div class="notice notice-success is-dismissible"><p>✅ Conversion rates saved.</p></div>';
	}

	$mc_active = class_exists( 'SGCLabs_Registry' )
		&& SGCLabs_Registry::is_active( 'multi_currency' );

	?>
	<div class="wrap" style="max-width:900px;">
		<h1>💱 <?php esc_html_e( 'Currency Settings', 'sgclabs-manager' ); ?></h1>

		<!-- Section 1: Default currency (always visible — A8) -->
		<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;margin-top:18px;">
			<h2 style="margin-top:0;">🏁 <?php esc_html_e( 'Default Store Currency', 'sgclabs-manager' ); ?></h2>
			<p style="color:#6b7280;">
				<?php esc_html_e( 'Every product price across the site is shown in this currency. The setting is shared with WooCommerce, so prices in the catalog, cart, checkout, and emails all match.', 'sgclabs-manager' ); ?>
			</p>
			<p style="margin-top:6px;font-size:13px;">
				<?php esc_html_e( 'Currently active:', 'sgclabs-manager' ); ?>
				<code style="background:#1a1a2e;color:#fff;padding:3px 10px;border-radius:4px;font-weight:600;">
					<?php echo esc_html( sgclabs_currency_symbol() . ' ' . $current_default ); ?>
				</code>
			</p>

			<form method="post" style="margin-top:14px;">
				<?php wp_nonce_field( 'sgc_set_default_currency' ); ?>
				<select name="default_currency" style="min-width:280px;padding:6px 10px;font-size:13px;">
					<?php
					$options = [
						'USD' => '$ USD — US Dollar',
						'EUR' => '€ EUR — Euro',
						'GBP' => '£ GBP — British Pound',
						'INR' => '₹ INR — Indian Rupee',
						'AED' => 'د.إ AED — UAE Dirham',
						'SAR' => 'ر.س SAR — Saudi Riyal',
						'JPY' => '¥ JPY — Japanese Yen',
						'AUD' => 'A$ AUD — Australian Dollar',
						'CAD' => 'C$ CAD — Canadian Dollar',
					];
					foreach ( $options as $code => $label ) {
						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $code ),
							selected( $current_default, $code, false ),
							esc_html( $label )
						);
					}
					?>
				</select>
				<button type="submit" name="sgc_set_default_currency" value="1" class="button button-primary">
					✅ <?php esc_html_e( 'Set as Default Currency', 'sgclabs-manager' ); ?>
				</button>
			</form>
		</div>

		<!-- Section 2: Multi-currency conversion (only if module enabled) -->
		<?php if ( $mc_active && $multi_currency_loaded ) : ?>
			<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;margin-top:18px;">
				<h2 style="margin-top:0;">🔄 <?php esc_html_e( 'Multi-Currency Display Switcher', 'sgclabs-manager' ); ?></h2>
				<p style="color:#6b7280;">
					<?php esc_html_e( 'Optional. When enabled, visitors see a floating switcher and prices convert client-side using these rates (relative to the default currency above). Conversion is display-only — orders are still placed in the default currency.', 'sgclabs-manager' ); ?>
				</p>
				<form method="post">
					<?php wp_nonce_field( 'sgc_currency_save' ); ?>
					<table class="form-table" style="max-width:600px;">
						<tbody>
							<?php
							$rates = SGCLabs_Multi_Currency::rates();
							foreach ( $rates as $code => $r ) :
								?>
								<tr>
									<th><?php echo esc_html( $r['symbol'] . ' ' . $code . ' — ' . $r['label'] ); ?></th>
									<td>
										<input type="number" step="0.000001"
											name="rates[<?php echo esc_attr( $code ); ?>][rate]"
											value="<?php echo esc_attr( $r['rate'] ); ?>"
											style="width:160px;">
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p>
						<button type="submit" name="sgc_currency_save" value="1" class="button button-primary">
							💾 <?php esc_html_e( 'Save Rates', 'sgclabs-manager' ); ?>
						</button>
					</p>
				</form>
			</div>
		<?php else : ?>
			<div style="background:#f9fafb;border:1px dashed #d1d5db;border-radius:8px;padding:20px;margin-top:18px;color:#6b7280;">
				<strong>ℹ️ <?php esc_html_e( 'Multi-currency switcher is disabled.', 'sgclabs-manager' ); ?></strong>
				<p style="margin:6px 0 0;"><?php esc_html_e( 'The site uses a single store currency (set above). To enable an optional 8-currency display switcher for international visitors, turn on the "Multi-Currency Switcher" module from SGC LABS → Module Browser.', 'sgclabs-manager' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

function sgclabs_international_gdpr_page() {
	$log = get_option( 'sgclabs_consent_log', [] );
	echo '<div class="wrap"><h1>🍪 GDPR Consent Log</h1>';
	echo '<p>Last <strong>' . count( $log ) . '</strong> consent decisions recorded (max 1,000, anonymized IP).</p>';
	echo '<table class="widefat striped"><thead><tr><th>Time</th><th>IP</th><th>Functional</th><th>Analytics</th><th>Marketing</th></tr></thead><tbody>';
	foreach ( array_reverse( $log ) as $row ) {
		echo '<tr>';
		echo '<td>' . esc_html( $row['time'] ?? '' ) . '</td>';
		echo '<td><code>' . esc_html( $row['ip'] ?? '' ) . '</code></td>';
		foreach ( [ 'functional', 'analytics', 'marketing' ] as $k ) {
			$v = ! empty( $row['pref'][ $k ] );
			echo '<td>' . ( $v ? '✅' : '○' ) . '</td>';
		}
		echo '</tr>';
	}
	echo '</tbody></table></div>';
}

function sgclabs_international_rest_page() {
	$base = rest_url( 'sgclabs/v1/' );
	echo '<div class="wrap"><h1>🔌 REST API</h1>';
	echo '<p>Public endpoints under namespace <code>sgclabs/v1</code>:</p>';
	echo '<table class="widefat striped"><thead><tr><th>Method</th><th>Endpoint</th><th>Description</th></tr></thead><tbody>';
	$rows = [
		[ 'GET',  $base . 'health',           'Plugin version + signature' ],
		[ 'GET',  $base . 'products',         'List products (search/category/page)' ],
		[ 'GET',  $base . 'products/{id}',    'Single product' ],
		[ 'GET',  $base . 'search?q=...',     'Sitewide search' ],
		[ 'POST', $base . 'quote',            'Submit quote request (rate-limited)' ],
		[ 'POST', $base . 'inquiry',          'Submit inquiry (rate-limited)' ],
	];
	foreach ( $rows as $r ) {
		echo '<tr><td><strong>' . esc_html( $r[0] ) . '</strong></td><td><code style="word-break:break-all;">' . esc_html( $r[1] ) . '</code></td><td>' . esc_html( $r[2] ) . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<p style="margin-top:20px;"><a href="' . esc_url( $base . 'health' ) . '" class="button" target="_blank">▶ Test /health</a></p>';
	echo '</div>';
}

function sgclabs_ai_center_router() {
	$tab = sanitize_key( $_GET['tab'] ?? 'content' );
	$tabs = [
		'content' => [ 'label' => '🧠 Content Center', 'callback' => 'sgclabs_ace_admin_page' ],
		'control' => [ 'label' => '🤖 Control Center',  'callback' => 'sgclabs_ai_control_page' ],
		'reply'   => [ 'label' => '💬 Reply Center',    'callback' => 'sgclabs_ai_reply_admin_page' ],
	];
	sgclabs_render_tabbed_page( 'AI Center', $tabs, $tab, 'sgclabs-ai-center' );
}

function sgclabs_seo_suite_router() {
	$tab = sanitize_key( $_GET['tab'] ?? 'bulk-editor' );
	$tabs = [
		'bulk-editor'  => [ 'label' => '✏️ Bulk Editor',    'callback' => 'sgclabs_bulk_editor_page' ],
		'quick-editor' => [ 'label' => '⚡ Quick Editor',   'callback' => 'sgclabs_bulk_quick_editor_page' ],
		'keywords'     => [ 'label' => '🔑 Keywords',       'callback' => 'sgclabs_keyword_manager_page' ],
		'keywords-db'  => [ 'label' => '🌍 Keywords DB',    'callback' => 'sgclabs_keywords_page' ],
		'global-kw'    => [ 'label' => '🌍 Global KW',      'callback' => 'sgclabs_global_keywords_page' ],
		'image-seo'    => [ 'label' => '🖼️ Image SEO',      'callback' => 'sgclabs_image_seo_page' ],
		'local-seo'    => [ 'label' => '📍 Local SEO',      'callback' => 'sgclabs_local_seo_page' ],
		'multilingual' => [ 'label' => '🌐 Multilingual',   'callback' => 'sgclabs_multilang_seo_page' ],
		'ml-bulk'      => [ 'label' => '🚀 Multilang Bulk', 'callback' => 'sgclabs_multilang_bulk_page' ],
		'rank-tracker' => [ 'label' => '📊 Rank Tracker',   'callback' => 'sgclabs_rank_tracker_page' ],
		'indexing'     => [ 'label' => '⚡ Indexing',        'callback' => 'sgclabs_indexnow_page' ],
		'internal'     => [ 'label' => '🔀 Internal Links', 'callback' => 'sgclabs_internal_links_page' ],
	];
	sgclabs_render_tabbed_page( 'SEO Suite', $tabs, $tab, 'sgclabs-seo-suite' );
}

function sgclabs_quotes_inquiries_router() {
	$tab = sanitize_key( $_GET['tab'] ?? 'quote-settings' );
	$tabs = [
		'quote-settings' => [ 'label' => '💬 Quote Settings', 'callback' => 'sgclabs_quote_page' ],
		'b2b-quotes'     => [ 'label' => '📋 B2B Quotes',     'callback' => 'sgclabs_b2b_quotes_page' ],
		'inquiries'      => [ 'label' => '📩 Inquiries',      'callback' => 'sgclabs_inquiries_page' ],
		'import-export'  => [ 'label' => '📥 Import / Export','callback' => 'sgclabs_inquiry_import_export_page' ],
	];
	sgclabs_render_tabbed_page( 'Quotes & Inquiries', $tabs, $tab, 'sgclabs-quotes-inquiries' );
}

function sgclabs_site_tools_router() {
	$tab = sanitize_key( $_GET['tab'] ?? 'tools' );
	// Tabs sorted alphabetically by label (after the leading emoji) so the
	// list scans cleanly. The default 'tools' tab stays first.
	$tabs = [
		'tools'        => [ 'label' => '🛠️ Site Tools',       'callback' => 'sgclabs_site_tools_page' ],
		'amazon'       => [ 'label' => '🌍 Amazon + Arabic',  'callback' => 'sgclabs_amazon_arabic_admin_page' ],
		'auto-fix'     => [ 'label' => '🔧 Auto-Fix',          'callback' => 'sgclabs_fixes_page' ],
		'compliance'   => [ 'label' => '✅ Compliance',         'callback' => 'sgclabs_compliance_page' ],
		'crawler'      => [ 'label' => '🕷️ Crawler',           'callback' => 'sgclabs_crawler_page' ],
		'extras'       => [ 'label' => '✨ Extras',             'callback' => 'sgclabs_extras_page' ],
		'gsc'          => [ 'label' => '🔍 GSC Connect',        'callback' => 'sgclabs_gsc_connect_page' ],
		'header'       => [ 'label' => '📞 Header & Footer',    'callback' => 'sgclabs_header_footer_page' ],
		'htaccess'     => [ 'label' => '📝 .htaccess',          'callback' => 'sgclabs_htaccess_page' ],
		'import'       => [ 'label' => '🔄 Import/Export',      'callback' => 'sgclabs_import_export_page' ],
		'multisite'    => [ 'label' => '🌐 Multi-Site',         'callback' => 'sgclabs_multisite_page' ],
		'pdf'          => [ 'label' => '📄 PDF Brochure',       'callback' => 'sgclabs_pdf_brochure_page' ],
		'roles'        => [ 'label' => '👥 Roles',              'callback' => 'sgclabs_role_manager_page' ],
		'shipping'     => [ 'label' => '🚚 Shipping Rules',     'callback' => 'sgclabs_shipping_rules_page' ],
		'social-share' => [ 'label' => '🔗 Social Share',       'callback' => 'sgclabs_social_share_admin_page' ],
		'status'       => [ 'label' => '📊 Status',             'callback' => 'sgclabs_status_tools_page' ],
		'tech-specs'   => [ 'label' => '🔬 Tech Specs',         'callback' => 'sgclabs_tech_specs_admin_page' ],
		'urgency'      => [ 'label' => '⚡ Urgency Widget',     'callback' => 'sgclabs_urgency_admin_page' ],
	];
	sgclabs_render_tabbed_page( 'Site Tools', $tabs, $tab, 'sgclabs-site-tools-hub' );
}

function sgclabs_woo_hub_router() {
	$tab = sanitize_key( $_GET['tab'] ?? 'woo-seo' );
	$tabs = [
		'woo-seo'       => [ 'label' => '🛒 Woo SEO',        'callback' => 'sgclabs_woo_seo_settings_page' ],
		'image-sizes'   => [ 'label' => '📐 Image Sizes',    'callback' => 'sgclabs_woo_image_sizes_page' ],
		'related-links' => [ 'label' => '🔗 Related Links',  'callback' => 'sgclabs_related_links_page' ],
		'size-variants' => [ 'label' => '📐 Size Variants',  'callback' => 'sgclabs_size_variant_page' ],
		'b2b-pricing'   => [ 'label' => '💰 Export Pricing', 'callback' => 'sgclabs_b2b_pricing_admin' ],
		'market-cmp'    => [ 'label' => '🌐 Market Compare', 'callback' => 'sgclabs_market_compare_admin' ],
		'bulk-order'    => [ 'label' => '📋 Bulk Order',     'callback' => 'sgclabs_bulk_order_admin_page' ],
		'reorder'       => [ 'label' => '🔄 Reorder Emails', 'callback' => 'sgclabs_reorder_admin_page' ],
	];
	sgclabs_render_tabbed_page( 'WooCommerce', $tabs, $tab, 'sgclabs-woo-hub' );
}

function sgclabs_render_tabbed_page( string $title, array $tabs, string $active_tab, string $page_slug ) {
	if ( ! isset( $tabs[ $active_tab ] ) ) $active_tab = array_key_first( $tabs );
	?>
	<div class="wrap">
		<h1 style="margin-bottom:0"><?php echo esc_html( $title ); ?></h1>
		<nav class="nav-tab-wrapper" style="margin-bottom:16px">
			<?php foreach ( $tabs as $slug => $tab ) : ?>
				<a href="<?php echo esc_url( admin_url( "admin.php?page={$page_slug}&tab={$slug}" ) ); ?>"
				   class="nav-tab <?php echo $active_tab === $slug ? 'nav-tab-active' : ''; ?>">
					<?php echo esc_html( $tab['label'] ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
		if ( function_exists( $tabs[ $active_tab ]['callback'] ) ) {
			call_user_func( $tabs[ $active_tab ]['callback'] );
		} else {
			echo '<div class="notice notice-warning"><p>Module not loaded: <code>' . esc_html( $tabs[ $active_tab ]['callback'] ) . '</code></p></div>';
		}
		?>
	</div>
	<?php
}

add_action( 'admin_enqueue_scripts', 'sgclabs_admin_styles' );
function sgclabs_admin_styles( $hook ) {
	$is_sgclabs_page = $hook && ( strpos( (string) $hook, 'sgclabs' ) !== false || strpos( (string) $hook, 'sgc-labs' ) !== false );
	$is_product_edit = in_array( $hook, [ 'post.php', 'post-new.php' ] ) && isset( $_GET['post'] )
						? get_post_type( intval( $_GET['post'] ) ) === 'product'
						: $hook === 'post-new.php' && ( isset( $_GET['post_type'] ) && $_GET['post_type'] === 'product' );
	if ( ! $is_sgclabs_page && ! $is_product_edit ) return;
	wp_enqueue_style( 'sgclabs-admin', SGCLABS_URL . 'assets/css/admin.css', [], SGCLABS_VERSION );
	wp_enqueue_script( 'sgclabs-admin', SGCLABS_URL . 'assets/js/admin.js', [ 'jquery' ], SGCLABS_VERSION, true );
	wp_localize_script( 'sgclabs-admin', 'sgclabs_ajax', [ 'ajax_url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'sgclabs_nonce' ) ] );
	wp_enqueue_style( 'sgc-control', SGCLABS_URL . 'assets/css/sgc-control.css', [ 'sgclabs-admin' ], SGCLABS_VERSION );
	wp_enqueue_script( 'sgc-brain', SGCLABS_URL . 'assets/js/sgc-brain.js', [ 'jquery', 'sgclabs-admin' ], SGCLABS_VERSION, true );
	wp_localize_script( 'sgc-brain', 'sgc_brain', [
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'sgclabs_nonce' ),
		'version'  => SGCLABS_VERSION,
		'brand'    => get_option( 'sgclabs_claude_brand_color',  '#1a1a2e' ),
		'accent'   => get_option( 'sgclabs_claude_accent_color', '#27ae60' ),
	] );
}

if ( ! function_exists( 'sgclabs_dashboard_page' ) ) {
function sgclabs_dashboard_page() {
	global $wpdb;
	$total_products  = (int)( wp_count_posts( 'product' )->publish ?? 0 );
	$seo_cached      = (int)( $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_sgclabs_seo_cache'") ?? 0 );
	$content_done    = (int)( $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_sgclabs_gen_content'") ?? 0 );
	$new_inquiries   = (int)( $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}sgclabs_inquiries'") ? $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_inquiries WHERE status='new'") : 0 );
	$total_inquiries = (int)( $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}sgclabs_inquiries'") ? $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_inquiries") : 0 );
	$broken_links    = (int)( $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}sgclabs_broken_links'") ? $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_broken_links") : 0 );
	$redirections    = (int)( $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}sgclabs_redirects'") ? $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_redirects WHERE enabled=1") : 0 );
	$total_kw        = (int)( $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_kw_global") ?? 0 )
	                 + (int)( $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_kw_gsc") ?? 0 );
	$total_imgs      = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_mime_type LIKE 'image/%'");
	$imgs_with_alt   = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_wp_attachment_image_alt' AND meta_value != ''");
	$seo_pct         = $total_products > 0 ? round( $seo_cached  / $total_products * 100 ) : 0;
	$content_pct     = $total_products > 0 ? round( $content_done / $total_products * 100 ) : 0;
	$img_alt_pct     = $total_imgs > 0     ? round( $imgs_with_alt / $total_imgs * 100 )    : 0;

	$conflicts = []; $cache_plugins = [];
	if ( defined('RANK_MATH_VERSION') ) $conflicts[]     = 'RankMath ' . RANK_MATH_VERSION;
	if ( defined('WPSEO_VERSION') )     $conflicts[]     = 'Yoast SEO ' . WPSEO_VERSION;
	if ( defined('WP_ROCKET_VERSION') ) $cache_plugins[] = 'WP Rocket';
	if ( defined('LSCWP_V') )           $cache_plugins[] = 'LiteSpeed Cache';
	if ( defined('W3TC') )              $cache_plugins[] = 'W3 Total Cache';

	$current_domain = function_exists('sgclabs_get_current_domain') ? sgclabs_get_current_domain() : ($_SERVER['HTTP_HOST'] ?? '');
	$profile        = function_exists('sgclabs_get_active_profile') ? sgclabs_get_active_profile() : [];
	$cwv_active     = get_option('sgclabs_cwv_active','1');
	$canonical      = get_option('sgclabs_canonical_active','1');
	$lazy_imgs      = get_option('sgclabs_lazy_images','1');
	$font           = get_option('sgclabs_selected_font','Inter');
	$quote_on       = get_option('sgclabs_quote_active','1');
	$header_on      = get_option('sgclabs_header_active','1');
	$perf_cache     = get_option('sgclabs_cache_enabled','0');
	$chatbot_on     = get_option('sgclabs_aichat_active','0');
	?>
	<div class="wrap sgclabs-wrap">

	<?php if(!empty($conflicts)): ?>
	<div class="notice notice-success sgclabs-notice-success"><p>
		&#x2705; <strong>Running alongside: <?php echo esc_html(implode(', ',$conflicts)); ?></strong> &#8212; Compatible mode active.<br>
		SGC LABS&#174; defers title, meta, OG, canonical and schema to <?php echo esc_html($conflicts[0]); ?>. No duplicates.<br>
		<em>&#x1F7E2; Fully active: AI SEO, Content Gen, Image SEO, AI Reply, Inquiries, Bulk Editor, Redirections.</em>
	</p></div>
	<?php endif; ?>

	<?php if(!empty($cache_plugins)): ?>
	<div class="notice notice-info"><p>&#x1F4BE; <strong>Cache: <?php echo esc_html(implode(', ',$cache_plugins)); ?></strong> &#8212; SGC cache auto-syncs on flush. &#x2705;</p></div>
	<?php endif; ?>

	<div style="background:#1a1a2e;color:#fff;padding:14px 20px;border-radius:8px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between">
		<div>
			<div style="font-size:18px;font-weight:600;line-height:1.2">&#x1F9EA; SGC LABS&#174; Manager Pro</div>
			<div style="font-size:12px;color:#a0a0c0;margin-top:2px">
				<?php echo esc_html($current_domain); ?>
				<?php if($profile && !empty($profile['company_name'])) echo ' &middot; ' . esc_html($profile['company_name']); ?>
				<?php if($profile && !empty($profile['phone'])) echo ' &middot; &#x1F4DE; ' . esc_html($profile['phone']); ?>
			</div>
		</div>
		<div style="text-align:right">
					<div style="background:rgba(228,176,74,.2);color:#e4b04a;padding:3px 12px;border-radius:12px;font-size:11px;font-weight:600">v<?php echo esc_html(SGCLABS_BASE_VERSION); ?> &middot; <?php echo esc_html( defined('SGCLABS_APP_BUILD_NO') ? SGCLABS_APP_BUILD_NO : SGCLABS_BUILD_ID ); ?></div>
			<div style="font-size:10px;color:#666;margin-top:4px">19 &#8594; 12 menu items</div>
		</div>
	</div>

	<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px">
		<?php
		$pills=[
			[$cwv_active==='1','Core Web Vitals'],[$canonical==='1','Canonical URLs'],
			[$lazy_imgs==='1','Lazy Load'],[true,'Font: '.$font],
			[$perf_cache==='1','Cache ON'],[!empty($conflicts), empty($conflicts)?'No SEO conflict':$conflicts[0].' compat'],
		];
		foreach($pills as $p){
			$bg=$p[0]?'#e6f9ee':'#FCEBEB'; $col=$p[0]?'#27500A':'#791F1F'; $ic=$p[0]?'&#x2705;':'&#x274C;';
			echo '<span style="background:'.$bg.';color:'.$col.';padding:4px 10px;border-radius:20px;font-size:11px;font-weight:500">'.$ic.' '.esc_html($p[1]).'</span>';
		}
		?>
	</div>

	<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px">
		<?php
		$kpis=[
			['AI SEO Progress',$seo_pct.'%',number_format($seo_cached).'/'.number_format($total_products).' products',$seo_pct,$seo_pct>=80?'#27ae60':($seo_pct>=40?'#e4b04a':'#e74c3c'),'sgclabs-ai-center&tab=content','Run AI SEO &rarr;'],
			['AI Content Gen',$content_pct.'%',number_format($content_done).'/'.number_format($total_products).' products',$content_pct,$content_pct>=80?'#27ae60':($content_pct>=40?'#e4b04a':'#e74c3c'),'sgclabs-ai-center&tab=content','Generate &rarr;'],
			['Image Alt Coverage',$img_alt_pct.'%',number_format($imgs_with_alt).'/'.number_format($total_imgs).' images',$img_alt_pct,$img_alt_pct>=80?'#27ae60':($img_alt_pct>=50?'#e4b04a':'#e74c3c'),'sgclabs-seo-suite&tab=image-seo','Optimize &rarr;'],
			['Keywords Tracked',number_format($total_kw),'Global + GSC combined',min(100,$total_kw>0?70:0),'#1a1a2e','sgclabs-seo-suite&tab=keywords','Manage &rarr;'],
		];
		foreach($kpis as $k): ?>
		<div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:12px 14px">
			<div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:.3px;margin-bottom:4px"><?php echo esc_html($k[0]); ?></div>
			<div style="font-size:22px;font-weight:700;color:#1a1a2e;line-height:1"><?php echo esc_html($k[1]); ?></div>
			<div style="font-size:10px;color:#aaa;margin-top:2px"><?php echo esc_html($k[2]); ?></div>
			<div style="height:4px;background:#e0e0e0;border-radius:2px;margin:8px 0 10px;overflow:hidden">
				<div style="height:100%;width:<?php echo intval($k[3]); ?>%;background:<?php echo esc_attr($k[4]); ?>;border-radius:2px"></div>
			</div>
			<a href="<?php echo esc_url(admin_url('admin.php?page='.$k[5])); ?>" class="button button-primary" style="font-size:11px;padding:3px 10px;height:auto"><?php echo wp_kses_post($k[6]); ?></a>
		</div>
		<?php endforeach; ?>
	</div>

	<div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:12px 16px;margin-bottom:16px">
		<div style="font-size:12px;font-weight:600;color:#1a1a2e;margin-bottom:10px">&#x26A1; Quick Actions</div>
		<div style="display:flex;gap:8px;flex-wrap:wrap">
			<?php
			$qa=[
				['&#x1F9E0; Bulk AI SEO','sgclabs-ai-center&tab=content'],
				['&#x270D;&#xFE0F; Content Bulk Gen','sgclabs-ai-center&tab=content'],
				['&#x1F5BC;&#xFE0F; Alt Text Batch','sgclabs-seo-suite&tab=image-seo'],
				['&#x1F4AC; AI Reply Center','sgclabs-ai-center&tab=reply'],
				['&#x1F4E9; View Inquiries','sgclabs-quotes-inquiries&tab=inquiries'],
				['&#x21A9;&#xFE0F; Redirections','sgclabs-redirections'],
				['&#x1F517; Broken Links','sgclabs-broken-links'],
				['&#x1F4DD; Bulk Editor','sgclabs-seo-suite&tab=bulk-editor'],
			];
			foreach($qa as $a):
			?>
			<a href="<?php echo esc_url(admin_url('admin.php?page='.$a[1])); ?>" class="button" style="font-size:12px;padding:5px 12px;height:auto"><?php echo wp_kses_post($a[0]); ?></a>
			<?php endforeach; ?>
		</div>
	</div>

	<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:16px">

		<div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:14px 16px">
			<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
				<div style="font-size:13px;font-weight:600;color:#1a1a2e">&#x1F4AC; Inquiries &amp; Quotes</div>
				<?php if($new_inquiries>0): ?><span style="background:#FCEBEB;color:#791F1F;font-size:10px;padding:2px 8px;border-radius:10px;font-weight:700"><?php echo intval($new_inquiries); ?> new</span><?php endif; ?>
			</div>
			<?php
			$rows=[['New inquiries',$new_inquiries>0?'<span style="color:#e74c3c;font-weight:700">'.intval($new_inquiries).'</span>':'0'],['Total inquiries',intval($total_inquiries)],['Quote form',$quote_on==='1'?'<span style="color:#27ae60">&#x2705; Active</span>':'&#x274C; Off'],['AI Reply','<span style="color:#27ae60">&#x2705; Active</span>']];
			foreach($rows as $r): ?>
			<div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:0.5px solid #f0f0f0;font-size:12px"><span style="color:#666"><?php echo esc_html($r[0]); ?></span><span><?php echo wp_kses_post($r[1]); ?></span></div>
			<?php endforeach; ?>
			<a href="<?php echo esc_url(admin_url('admin.php?page=sgclabs-quotes-inquiries&tab=inquiries')); ?>" class="button button-primary" style="margin-top:10px;font-size:11px;padding:4px 12px;height:auto">View Inquiries &rarr;</a>
		</div>

		<div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:14px 16px">
			<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
				<div style="font-size:13px;font-weight:600;color:#1a1a2e">&#x1F6E1;&#xFE0F; Site Health</div>
				<?php if($broken_links>0): ?><span style="background:#FAEEDA;color:#412402;font-size:10px;padding:2px 8px;border-radius:10px;font-weight:700"><?php echo intval($broken_links); ?> broken</span>
				<?php else: ?><span style="background:#e6f9ee;color:#27500A;font-size:10px;padding:2px 8px;border-radius:10px;font-weight:700">All clear</span><?php endif; ?>
			</div>
			<?php
			$rows=[['Broken links',$broken_links>0?'<span style="color:#e4b04a;font-weight:700">'.intval($broken_links).'</span>':'<span style="color:#27ae60">0 &#x2705;</span>'],['Active redirections',intval($redirections).' active'],['Performance cache',$perf_cache==='1'?'<span style="color:#27ae60">&#x2705; ON</span>':'<span style="color:#888">OFF</span>'],['Compliance',$cwv_active==='1'?'<span style="color:#27ae60">&#x2705; Pass</span>':'<span style="color:#e74c3c">Check</span>']];
			foreach($rows as $r): ?>
			<div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:0.5px solid #f0f0f0;font-size:12px"><span style="color:#666"><?php echo esc_html($r[0]); ?></span><span><?php echo wp_kses_post($r[1]); ?></span></div>
			<?php endforeach; ?>
			<div style="display:flex;gap:6px;margin-top:10px">
				<a href="<?php echo esc_url(admin_url('admin.php?page=sgclabs-broken-links')); ?>" class="button" style="font-size:11px;padding:4px 10px;height:auto">Scan Links</a>
				<a href="<?php echo esc_url(admin_url('admin.php?page=sgclabs-site-tools-hub&tab=compliance')); ?>" class="button" style="font-size:11px;padding:4px 10px;height:auto">Compliance</a>
			</div>
		</div>

		<div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:14px 16px">
			<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
				<div style="font-size:13px;font-weight:600;color:#1a1a2e">&#x1F6D2; WooCommerce</div>
				<span style="background:#e6f9ee;color:#27500A;font-size:10px;padding:2px 8px;border-radius:10px;font-weight:700">Active</span>
			</div>
			<?php
			$tw=(int)get_option('woocommerce_thumbnail_image_width',300); $sw=(int)get_option('woocommerce_single_image_width',600);
			$rows=[['Total products',number_format($total_products)],['Image sizes',$tw.'px / '.$sw.'px'],['B2B pricing',get_option('sgclabs_b2b_pricing_active','0')==='1'?'<span style="color:#27ae60">&#x2705; ON</span>':'<span style="color:#888">OFF</span>'],['Bulk order table',function_exists('sgclabs_bulk_order_admin_page')?'<span style="color:#27ae60">&#x2705; Active</span>':'<span style="color:#888">&#8212;</span>'],['Reorder emails',function_exists('sgclabs_reorder_admin_page')?'<span style="color:#27ae60">&#x2705; Active</span>':'<span style="color:#888">&#8212;</span>']];
			foreach($rows as $r): ?>
			<div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:0.5px solid #f0f0f0;font-size:12px"><span style="color:#666"><?php echo esc_html($r[0]); ?></span><span><?php echo wp_kses_post($r[1]); ?></span></div>
			<?php endforeach; ?>
			<a href="<?php echo esc_url(admin_url('admin.php?page=sgclabs-woo-hub')); ?>" class="button button-primary" style="margin-top:10px;font-size:11px;padding:4px 12px;height:auto">WooCommerce Hub &rarr;</a>
		</div>

		<div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:14px 16px">
			<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
				<div style="font-size:13px;font-weight:600;color:#1a1a2e">&#x1F310; Site &amp; Presence</div>
				<span style="background:#E6F1FB;color:#0C447C;font-size:10px;padding:2px 8px;border-radius:10px;font-weight:700">4 domains</span>
			</div>
			<?php
			$rows=[['Active domain',esc_html($current_domain)],['Header & WhatsApp',$header_on==='1'?'<span style="color:#27ae60">&#x2705; Active</span>':'&#x274C; Off'],['AI Chatbot',$chatbot_on==='1'?'<span style="color:#27ae60">&#x2705; Active</span>':'<span style="color:#888">Off</span>'],['PDF Brochure',function_exists('sgclabs_pdf_brochure_page')?'<span style="color:#27ae60">&#x2705; Ready</span>':'<span style="color:#888">&#8212;</span>'],['Shipping rules',function_exists('sgclabs_shipping_rules_page')?'<span style="color:#27ae60">&#x2705; Active</span>':'<span style="color:#888">&#8212;</span>']];
			foreach($rows as $r): ?>
			<div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:0.5px solid #f0f0f0;font-size:12px"><span style="color:#666"><?php echo esc_html($r[0]); ?></span><span><?php echo wp_kses_post($r[1]); ?></span></div>
			<?php endforeach; ?>
			<div style="display:flex;gap:6px;margin-top:10px">
				<a href="<?php echo esc_url(admin_url('admin.php?page=sgclabs-site-tools-hub&tab=multisite')); ?>" class="button" style="font-size:11px;padding:4px 10px;height:auto">Multi-Site</a>
				<a href="<?php echo esc_url(admin_url('admin.php?page=sgclabs-site-tools-hub&tab=header')); ?>" class="button" style="font-size:11px;padding:4px 10px;height:auto">Header</a>
				<a href="<?php echo esc_url(admin_url('admin.php?page=sgclabs-site-tools-hub&tab=pdf')); ?>" class="button" style="font-size:11px;padding:4px 10px;height:auto">PDF</a>
			</div>
		</div>

	</div>

	<?php if($profile): ?>
	<div style="background:#f8f8f8;border:1px solid #e0e0e0;border-radius:8px;padding:10px 16px;margin-bottom:12px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
		<code style="background:#1a1a2e;color:#e4b04a;padding:2px 10px;border-radius:4px;font-size:12px"><?php echo esc_html($current_domain); ?></code>
		<?php if(!empty($profile['company_name'])) echo '<span style="font-size:12px">&#x1F3E2; '.esc_html($profile['company_name']).'</span>'; ?>
		<?php if(!empty($profile['phone']))         echo '<span style="font-size:12px">&#x1F4DE; '.esc_html($profile['phone']).'</span>'; ?>
		<?php if(!empty($profile['currency']))      echo '<span style="font-size:12px">&#x1F4B1; '.esc_html($profile['currency']).'</span>'; ?>
		<?php if(!empty($profile['email']))         echo '<span style="font-size:12px">&#x1F4E7; '.esc_html($profile['email']).'</span>'; ?>
		<a href="<?php echo esc_url(admin_url('admin.php?page=sgclabs-site-tools-hub&tab=multisite')); ?>" class="button" style="font-size:11px;padding:3px 10px;height:auto;margin-left:auto">Edit Profiles &rarr;</a>
	</div>
	<?php endif; ?>

	<div style="text-align:center;color:#aaa;font-size:11px;padding:8px 0">
		SGC LABS&#174; Manager Pro
		<strong style="color:#e4b04a">v<?php echo esc_html(SGCLABS_BASE_VERSION); ?></strong>
		&nbsp;&middot;&nbsp;
		App Build: <code style="background:#1a1a2e;color:#7dffc4;padding:1px 7px;border-radius:4px;font-size:11px"><?php echo esc_html( defined('SGCLABS_APP_BUILD_NO') ? SGCLABS_APP_BUILD_NO : SGCLABS_BUILD_ID ); ?></code>
		&nbsp;&middot;&nbsp;
		<?php echo esc_html( defined('SGCLABS_BUILD_DATE') ? SGCLABS_BUILD_DATE . ' ' . SGCLABS_BUILD_TIME . ' IST' : SGCLABS_BUILD ); ?>
		&nbsp;&middot;&nbsp;
		<a href="<?php echo esc_url(admin_url('admin.php?page=sgclabs-feature-control')); ?>" style="color:#aaa">Feature Controls</a>
		&nbsp;&middot;&nbsp;
		<a href="<?php echo esc_url(admin_url('admin.php?page=sgclabs-general')); ?>" style="color:#aaa">Settings</a>
	</div>

	</div>
	<?php
}
}
