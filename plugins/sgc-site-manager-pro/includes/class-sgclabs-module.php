<?php
/**
 * SGC LABS® Module Base Class
 *
 * Every OOP module extends this class.
 * Provides: option prefix, nonce helpers, activation toggle,
 *           admin-notice helper, sanitisation wrappers.
 *
 * @package SGCLabs_Manager_Pro
 * @since   6.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

abstract class SGCLabs_Module {

    /** @var string  Unique module slug (e.g. 'shipping-rules'). */
    protected $module_id = '';

    /** @var string  Human-readable label shown in admin. */
    protected $module_label = '';

    /** @var string  Option key that stores active/inactive (1/0). */
    protected $active_option = '';

    /** @var string  Nonce action used by this module's AJAX. */
    protected $nonce_action = 'sgclabs_nonce';

    /* ----------------------------------------------------------
     * CONSTRUCTOR
     * --------------------------------------------------------*/
    public function __construct() {
        // Build the active-option key automatically.
        if ( empty( $this->active_option ) && $this->module_id ) {
            $this->active_option = 'sgclabs_' . str_replace( '-', '_', $this->module_id ) . '_active';
        }
    }

    /* ----------------------------------------------------------
     * BOOT — called by the main plugin file after instantiation.
     * Child classes override this to register hooks.
     * --------------------------------------------------------*/
    abstract public function boot(): void;

    /* ----------------------------------------------------------
     * IS ACTIVE? — Checks the toggle option (default ON).
     * --------------------------------------------------------*/
    public function is_active(): bool {
        return get_option( $this->active_option, '1' ) === '1';
    }

    /* ----------------------------------------------------------
     * NONCE HELPERS
     * --------------------------------------------------------*/
    protected function create_nonce(): string {
        return wp_create_nonce( $this->nonce_action );
    }

    protected function verify_nonce( string $field = 'nonce' ): bool {
        return check_ajax_referer( $this->nonce_action, $field, false ) !== false;
    }

    /* ----------------------------------------------------------
     * CAPABILITY CHECK
     * --------------------------------------------------------*/
    protected function current_user_can_manage(): bool {
        return current_user_can( 'manage_options' );
    }

    /* ----------------------------------------------------------
     * SANITISATION SHORTCUTS
     * --------------------------------------------------------*/
    protected function sanitize( string $value ): string {
        return sanitize_text_field( wp_unslash( $value ) );
    }

    protected function sanitize_array( array $arr ): array {
        return array_map( [ $this, 'sanitize' ], $arr );
    }

    /* ----------------------------------------------------------
     * OPTION HELPERS (prefixed by module_id)
     * --------------------------------------------------------*/
    protected function get_option( string $key, $default = '' ) {
        return get_option( 'sgclabs_' . str_replace( '-', '_', $this->module_id ) . '_' . $key, $default );
    }

    protected function update_option( string $key, $value ): bool {
        return update_option( 'sgclabs_' . str_replace( '-', '_', $this->module_id ) . '_' . $key, $value );
    }

    protected function delete_option( string $key ): bool {
        return delete_option( 'sgclabs_' . str_replace( '-', '_', $this->module_id ) . '_' . $key );
    }

    /* ----------------------------------------------------------
     * ADMIN NOTICE HELPER
     * --------------------------------------------------------*/
    protected function admin_notice( string $message, string $type = 'success' ): void {
        add_action( 'admin_notices', function () use ( $message, $type ) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr( $type ),
                esc_html( $message )
            );
        } );
    }

    /* ----------------------------------------------------------
     * JSON RESPONSE HELPERS (for AJAX)
     * --------------------------------------------------------*/
    protected function json_success( $data = null ): void {
        wp_send_json_success( $data );
    }

    protected function json_error( string $message, int $code = 400 ): void {
        wp_send_json_error( $message, $code );
    }

    /* ----------------------------------------------------------
     * TEMPLATE LOADER — loads from templates/admin/{file}.php
     * Passes $args array as extracted variables.
     * --------------------------------------------------------*/
    protected function render_template( string $template_name, array $args = [] ): void {
        $file = SGCLABS_PATH . 'templates/admin/' . $template_name . '.php';
        if ( ! file_exists( $file ) ) {
            echo '<div class="notice notice-error"><p>Template not found: ' . esc_html( $template_name ) . '</p></div>';
            return;
        }
        // Make variables available inside the template.
        // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        extract( $args, EXTR_SKIP );
        include $file;
    }

    /* ----------------------------------------------------------
     * SCRIPT / STYLE ENQUEUE HELPERS
     * --------------------------------------------------------*/
    protected function enqueue_admin_style( string $handle, string $file, array $deps = [] ): void {
        $url = SGCLABS_URL . 'assets/css/' . $file;
        wp_enqueue_style( 'sgclabs-' . $handle, $url, $deps, SGCLABS_VERSION );
    }

    protected function enqueue_admin_script( string $handle, string $file, array $deps = [ 'jquery' ], bool $in_footer = true ): void {
        $url = SGCLABS_URL . 'assets/js/' . $file;
        wp_enqueue_script( 'sgclabs-' . $handle, $url, $deps, SGCLABS_VERSION, $in_footer );
    }

    protected function enqueue_public_style( string $handle, string $file, array $deps = [] ): void {
        $url = SGCLABS_URL . 'public/css/' . $file;
        wp_enqueue_style( 'sgclabs-' . $handle, $url, $deps, SGCLABS_VERSION );
    }

    protected function enqueue_public_script( string $handle, string $file, array $deps = [ 'jquery' ], bool $in_footer = true ): void {
        $url = SGCLABS_URL . 'public/js/' . $file;
        wp_enqueue_script( 'sgclabs-' . $handle, $url, $deps, SGCLABS_VERSION, $in_footer );
    }

    /* ----------------------------------------------------------
     * LOCALIZE SCRIPT (pass PHP data to JS)
     * --------------------------------------------------------*/
    protected function localize_script( string $handle, string $object_name, array $data ): void {
        wp_localize_script( 'sgclabs-' . $handle, $object_name, $data );
    }
}
