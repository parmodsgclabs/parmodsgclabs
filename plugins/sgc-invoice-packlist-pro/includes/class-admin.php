<?php
defined( 'ABSPATH' ) || exit;

class SGC_IIP_Admin {

    const TABS = [
        'inquiries' => '📋 Inquiries',
        'proforma'  => '📄 Proforma Invoice',
        'packlist'  => '📦 Packing List',
        'documents' => '🗂 All Documents',
        'pipeline'  => '🔄 Pipeline',
        'followup'  => '⏰ Follow-up',
        'compose'   => '✉ Compose Email',
        'inbox'     => '📥 Inbox',
        'settings'  => '⚙️ Settings',
        'changelog' => 'ℹ️ About & Changelog',
    ];

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 99 );
        add_action( 'admin_init', [ __CLASS__, 'handle_form_posts' ] );
    }

    public static function register_menu() {
        $cap    = 'manage_woocommerce';
        $slug   = 'sgc-iip';
        $cb     = [ __CLASS__, 'render' ];

        // Attach under Manager Pro if active
        $parent = function_exists( 'sgclabs_manager_page' ) || menu_page_url( 'sgclabs-manager', false )
            ? 'sgclabs-manager'
            : null;

        if ( $parent ) {
            add_submenu_page( $parent, 'Invoice · Packlist Pro Manager', '📋 Invoice Pro Manager', $cap, $slug, $cb );
        } else {
            add_menu_page( 'SGC Invoice Pro Manager', 'SGC Invoice Pro', $cap, $slug, $cb, 'dashicons-email-alt', 56 );
        }
    }

    public static function render() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Access denied.' );

        $tab     = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'inquiries';
        $allowed = array_keys( self::TABS );
        if ( ! in_array( $tab, $allowed, true ) ) $tab = 'inquiries';

        $view = SGC_IIP_PATH . 'admin/views/tab-' . $tab . '.php';
        ?>
        <div class="wrap sgc-iip-wrap">

            <!-- Header -->
            <div class="sgc-iip-header">
                <div class="sgc-iip-header-left">
                    <span style="font-size:22px">📋</span>
                    <div>
                        <h1>SGC Invoice · Packlist Pro Manager</h1>
                        <span>AI-Powered B2B Export Pipeline &nbsp;|&nbsp; <span id="iip-wa-status"><?php echo get_option('sgc_iip_wa_enabled','0')==='1' ? '💬 WhatsApp Active' : '💬 WhatsApp — Configure in Settings'; ?></span></span>
                    </div>
                </div>
                <div class="sgc-iip-header-right">
                    <div class="sgc-iip-header-stats" id="iip-header-stats">
                        <div class="iip-stat"><span id="hstat-new">—</span><small>New</small></div>
                        <div class="iip-stat"><span id="hstat-today">—</span><small>Today</small></div>
                        <div class="iip-stat"><span id="hstat-pi">—</span><small>PI Sent</small></div>
                        <div class="iip-stat"><span id="hstat-won">—</span><small>Won</small></div>
                    </div>
                    <a href="<?php echo esc_url( admin_url('admin.php?page=sgc-iip&tab=inquiries&action=add') ); ?>" class="iip-btn primary">+ Add Inquiry</a>
                </div>
            </div>

            <!-- Tabs -->
            <nav class="sgc-iip-tabs">
                <?php foreach ( self::TABS as $slug => $label ) : ?>
                    <a href="<?php echo esc_url( admin_url('admin.php?page=sgc-iip&tab=' . $slug) ); ?>"
                       class="iip-tab<?php echo $tab === $slug ? ' active' : ''; ?>">
                        <?php echo esc_html( $label ); ?>
                        <?php if ( 'inquiries' === $slug ) : ?><span class="iip-badge" id="tab-new-count"></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <!-- Global notice -->
            <div id="sgc-iip-notice" class="iip-notice" style="display:none"></div>

            <!-- Content -->
            <div class="sgc-iip-content">
                <?php if ( file_exists( $view ) ) include $view; else echo '<p style="padding:20px;color:#888">View not found: ' . esc_html($view) . '</p>'; ?>
            </div>

        </div>
        <?php
    }

    public static function handle_form_posts() {
        // Handles any non-AJAX form submissions if needed
    }
}

SGC_IIP_Admin::init();
