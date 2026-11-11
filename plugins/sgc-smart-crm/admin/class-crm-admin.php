<?php
/**
 * SGC Smart CRM Pro — Admin
 *
 * Registers the admin menu under SGC LABS® Manager Pro
 * and renders the 6-tab dashboard.
 *
 * @package SGC_Smart_CRM
 * @since   1.0.0
 * @version   1.1.1 | Build: 20260516-0630
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SGC_CRM_Admin
 *
 * @since 1.0.0
 * @version   1.1.1 | Build: 20260516-0630
 */
class SGC_CRM_Admin {

	/**
	 * Initialise admin hooks.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 99 );
	}

	/**
	 * Register submenu under SGC LABS® Manager Pro (or standalone).
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function register_menu() {
		// Use Manager Pro parent if available; fall back to standalone.
		$parent = menu_page_url( 'sgclabs-manager', false ) ? 'sgclabs-manager' : 'tools.php';

		add_submenu_page(
			$parent,
			esc_html__( 'SGC Smart CRM Pro', 'sgc-smart-crm' ),
			esc_html__( '📧 Smart CRM', 'sgc-smart-crm' ),
			'manage_options',
			'sgc-smart-crm',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Get tab definitions.
	 *
	 * @since  1.0.0
	 * @return array<string, string>
	 */
	private static function tabs() {
		return array(
			'dashboard'  => esc_html__( '📊 Dashboard',  'sgc-smart-crm' ),
			'contacts'   => esc_html__( '👥 Contacts',   'sgc-smart-crm' ),
			'pipeline'   => esc_html__( '🎯 Pipeline',   'sgc-smart-crm' ),
			'campaigns'  => esc_html__( '📨 Campaigns',  'sgc-smart-crm' ),
			'automation' => esc_html__( '⚡ Automation', 'sgc-smart-crm' ),
			'products'   => esc_html__( '🛍️ Products',   'sgc-smart-crm' ),
			'settings'   => esc_html__( '⚙️ Settings',   'sgc-smart-crm' ),
		);
	}

	/**
	 * Render the full admin page.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'sgc-smart-crm' ) );
		}

		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$allowed     = array_keys( self::tabs() );
		if ( ! in_array( $current_tab, $allowed, true ) ) {
			$current_tab = 'dashboard';
		}

		$view_file = SGC_CRM_PATH . 'admin/views/tab-' . $current_tab . '.php';

		echo '<div class="wrap sgc-crm-wrap">';
		self::render_header( $current_tab );

		echo '<div class="sgc-crm-content">';
		if ( file_exists( $view_file ) ) {
			include $view_file;
		} else {
			echo '<p>' . esc_html__( 'View not found.', 'sgc-smart-crm' ) . '</p>';
		}
		echo '</div></div>';
	}

	/**
	 * Render header + tab navigation.
	 *
	 * @since  1.0.0
	 * @param  string $current_tab Active tab slug.
	 * @return void
	 */
	private static function render_header( $current_tab ) {
		?>
		<div class="sgc-crm-header">
			<div class="sgc-crm-logo">
				<span class="sgc-crm-logo-icon">📧</span>
				<div>
					<h1><?php esc_html_e( 'SGC Smart CRM Pro', 'sgc-smart-crm' ); ?></h1>
					<span><?php esc_html_e( 'AI-Powered B2B CRM & Email Automation', 'sgc-smart-crm' ); ?></span>
				</div>
			</div>
			<div class="sgc-crm-version">v<?php echo esc_html( SGC_CRM_VERSION ); ?></div>
		</div>

		<nav class="sgc-crm-tabs" aria-label="<?php esc_attr_e( 'CRM Navigation', 'sgc-smart-crm' ); ?>">
			<?php foreach ( self::tabs() as $slug => $label ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=sgc-smart-crm&tab=' . $slug ) ); ?>"
				   class="sgc-crm-tab<?php echo $current_tab === $slug ? ' active' : ''; ?>"
				   <?php echo $current_tab === $slug ? 'aria-current="page"' : ''; ?>>
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}
}

SGC_CRM_Admin::init();
