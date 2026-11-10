<?php
defined( 'ABSPATH' ) || exit;

class SGC_PM_Admin {

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 98 );
	}

	public static function register_menu() {
		$parent = menu_page_url( 'sgclabs-manager', false ) ? 'sgclabs-manager' : null;
		$cap    = 'manage_woocommerce';
		$slug   = 'sgc-product-manager';
		$cb     = [ __CLASS__, 'render' ];

		if ( $parent ) {
			add_submenu_page( $parent, 'SGC Product Manager', '🛍 Product Manager', $cap, $slug, $cb );
		} else {
			add_menu_page( 'SGC Product Manager Pro', 'SGC Products', $cap, $slug, $cb, 'dashicons-products', 55 );
		}
	}

	private static function tabs() {
		return [
			'dashboard'   => '📊 Dashboard',
			'products'    => '📦 Products',
			'studio'      => '🎨 Product Studio',
			'ai'          => '🤖 AI Studio',
			'automations' => '⚡ Automations',
			'competitor'  => '🔍 Competitor Intel',
			'sql'         => '⚡ SQL Editor',
			'b2b'         => '📄 B2B Export',
			'tools'       => '🔧 Tools',
			'import'      => '📥 Import',
			'logs'        => '📋 Logs',
			'settings'    => '⚙️ Settings',
		];
	}

	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Access denied.' );
		$tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
		$allowed = array_keys( self::tabs() );
		if ( ! in_array( $tab, $allowed, true ) ) $tab = 'dashboard';
		$view    = SGC_PM_PATH . 'admin/views/tab-' . $tab . '.php';
		?>
		<div class="wrap sgc-pm-wrap">
			<div class="sgc-pm-header">
				<div class="sgc-pm-logo">
					<span class="sgc-pm-logo-icon">🛍️</span>
					<div>
						<h1>SGC Product Manager Pro</h1>
						<span>AI · Product Studio · Automations · Competitor Intel · SQL · B2B Export &nbsp;|&nbsp; v<?php echo esc_html( SGC_PM_VERSION ); ?> · Build <?php echo esc_html( SGC_PM_BUILD ); ?></span>
					</div>
				</div>
				<div class="sgc-pm-header-right">
					<!-- Master Controls -->
					<div class="sgc-pm-master-controls">
						<label class="sgc-pm-toggle-wrap" title="AI Engine">
							<span>AI</span>
							<div class="sgc-pm-toggle">
								<input type="checkbox" id="master-ai" <?php checked( get_option( 'sgc_pm_ai_enabled', '1' ), '1' ); ?> data-option="sgc_pm_ai_enabled">
								<span class="sgc-pm-toggle-slider"></span>
							</div>
						</label>
						<label class="sgc-pm-toggle-wrap" title="Automations">
							<span>Auto</span>
							<div class="sgc-pm-toggle">
								<input type="checkbox" id="master-auto" <?php checked( get_option( 'sgc_pm_automation_enabled', '1' ), '1' ); ?> data-option="sgc_pm_automation_enabled">
								<span class="sgc-pm-toggle-slider"></span>
							</div>
						</label>
						<label class="sgc-pm-toggle-wrap" title="Product gallery enhancement (big image + thumbnail strip + lightbox). Turn OFF if your theme/Elementor has its own gallery.">
							<span>Gallery</span>
							<div class="sgc-pm-toggle">
								<input type="checkbox" id="master-gallery" <?php checked( get_option( 'sgc_pm_gallery_enhance', '1' ), '1' ); ?> data-option="sgc_pm_gallery_enhance">
								<span class="sgc-pm-toggle-slider"></span>
							</div>
						</label>
						<label class="sgc-pm-toggle-wrap" title="Competitor Intel">
							<span>Intel</span>
							<div class="sgc-pm-toggle">
								<input type="checkbox" id="master-comp" <?php checked( get_option( 'sgc_pm_competitor_enabled', '1' ), '1' ); ?> data-option="sgc_pm_competitor_enabled">
								<span class="sgc-pm-toggle-slider"></span>
							</div>
						</label>
					</div>
					<span class="sgc-pm-version">v<?php echo esc_html( SGC_PM_VERSION ); ?></span>
				</div>
			</div>

			<nav class="sgc-pm-tabs" aria-label="Plugin Navigation">
				<?php foreach ( self::tabs() as $slug => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=sgc-product-manager&tab=' . $slug ) ); ?>"
					   class="sgc-pm-tab<?php echo $tab === $slug ? ' active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div id="sgc-pm-global-notice" class="sgc-pm-notice" style="display:none"></div>

			<div class="sgc-pm-content">
				<?php if ( file_exists( $view ) ) include $view; else echo '<p>View not found.</p>'; ?>
			</div>
		</div>
		<?php
	}
}

SGC_PM_Admin::init();
