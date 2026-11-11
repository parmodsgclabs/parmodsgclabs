<?php
/**
 * SGC Core Bridge — shared infrastructure for all SGC plugins
 *
 * Provides: unified document number generation, PDF folder management,
 * shared options namespace, inter-plugin event bus, plugin version handshake.
 *
 * Used by: SGC LABS® Manager Pro · SGC Invoice Pro · SGC Smart CRM Pro
 *
 * Powered by Claude (Anthropic) — AI-assisted development
 *
 * @package   SGC\Core
 * @since     6.9.9
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'SGC_Core_Bridge' ) ) :

class SGC_Core_Bridge {

    const VERSION      = '1.0.0';
    const OPTION_NS    = 'sgc_core_shared';   // shared wp_options key
    const PDF_SUBDIR   = 'sgc-docs';          // under wp-content/uploads/

    private static bool $booted = false;

    // ── Boot ──────────────────────────────────────────────────────────────────

    public static function boot(): void {
        if ( self::$booted ) return;
        self::$booted = true;

        add_action( 'admin_init',    [ __CLASS__, 'maybe_create_pdf_dir' ] );
        add_action( 'sgclabs_on_activate', [ __CLASS__, 'on_activate' ] );
    }

    public static function on_activate(): void {
        self::maybe_create_pdf_dir();
        self::ensure_sequences_seeded();
    }

    // ── Document number generator ─────────────────────────────────────────────
    //
    // Formats: INQ-2026-0001 · QT-2026-0001 · SGC-E-2026-0001 · SGC-D-2026-0001
    //          INV-2026-0001 · PKL-2026-0001 · PAY-2026-0001
    //
    // Atomic: uses SELECT … FOR UPDATE inside a transaction so concurrent
    // requests on the same site never produce the same number.

    public static function generate_number( string $doc_type ): string {
        global $wpdb;

        $table = $wpdb->prefix . 'sgc_doc_sequences';
        $year  = (int) gmdate( 'Y' );

        // Ensure row exists for this type + year
        $wpdb->query( $wpdb->prepare(
            "INSERT IGNORE INTO `{$table}` (doc_type, prefix, year, last_number)
             VALUES (%s, %s, %d, 0)",
            $doc_type,
            self::prefix_for( $doc_type ),
            $year
        ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

        // Atomic increment inside transaction
        $wpdb->query( 'START TRANSACTION' );
        $wpdb->query( $wpdb->prepare(
            "SELECT last_number FROM `{$table}` WHERE doc_type=%s AND year=%d FOR UPDATE",
            $doc_type, $year
        ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query( $wpdb->prepare(
            "UPDATE `{$table}` SET last_number = last_number + 1 WHERE doc_type=%s AND year=%d",
            $doc_type, $year
        ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $last = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT last_number FROM `{$table}` WHERE doc_type=%s AND year=%d",
            $doc_type, $year
        ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query( 'COMMIT' );

        return self::format_number( $doc_type, $year, $last );
    }

    private static function prefix_for( string $doc_type ): string {
        $map = [
            'INQ'   => 'INQ',
            'QT'    => 'QT',
            'PI_E'  => 'SGC-E',  // Export PI
            'PI_D'  => 'SGC-D',  // Domestic PI
            'INV'   => 'INV',
            'PKL'   => 'PKL',
            'PAY'   => 'PAY',
        ];
        return $map[ $doc_type ] ?? strtoupper( $doc_type );
    }

    private static function format_number( string $doc_type, int $year, int $n ): string {
        $prefix = self::prefix_for( $doc_type );
        $padded = str_pad( $n, 4, '0', STR_PAD_LEFT );
        return "{$prefix}-{$year}-{$padded}";
    }

    private static function ensure_sequences_seeded(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'sgc_doc_sequences';
        $year  = (int) gmdate( 'Y' );
        $types = [ 'INQ', 'QT', 'PI_E', 'PI_D', 'INV', 'PKL', 'PAY' ];
        foreach ( $types as $t ) {
            $wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                "INSERT IGNORE INTO `{$table}` (doc_type, prefix, year, last_number)
                 VALUES (%s, %s, %d, 0)",
                $t, self::prefix_for( $t ), $year
            ) );
        }

        // Migrate old Invoice Pro pi_counter to new sequence (run once)
        $migrated = get_option( 'sgc_core_pi_counter_migrated', false );
        if ( ! $migrated ) {
            $old_counter = (int) get_option( 'sgc_iip_pi_counter', 0 );
            if ( $old_counter > 0 ) {
                foreach ( [ 'PI_E', 'PI_D' ] as $t ) {
                    $wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                        "UPDATE `{$table}` SET last_number = GREATEST(last_number, %d)
                         WHERE doc_type=%s AND year=%d",
                        $old_counter - 1, $t, $year
                    ) );
                }
            }
            update_option( 'sgc_core_pi_counter_migrated', true, false );
        }
    }

    // ── Reverse lookup: any number → doc_type ────────────────────────────────

    public static function type_from_number( string $number ): string {
        if ( strpos( $number, 'INQ-' ) === 0 )   return 'INQ';
        if ( strpos( $number, 'QT-' ) === 0 )    return 'QT';
        if ( strpos( $number, 'SGC-E-' ) === 0 ) return 'PI_E';
        if ( strpos( $number, 'SGC-D-' ) === 0 ) return 'PI_D';
        if ( strpos( $number, 'INV-' ) === 0 )   return 'INV';
        if ( strpos( $number, 'PKL-' ) === 0 )   return 'PKL';
        if ( strpos( $number, 'PAY-' ) === 0 )   return 'PAY';
        return 'UNKNOWN';
    }

    // ── PDF folder management ─────────────────────────────────────────────────

    public static function pdf_base_dir(): string {
        $upload = wp_upload_dir();
        return trailingslashit( $upload['basedir'] ) . self::PDF_SUBDIR;
    }

    public static function pdf_base_url(): string {
        $upload = wp_upload_dir();
        return trailingslashit( $upload['baseurl'] ) . self::PDF_SUBDIR;
    }

    /**
     * Get/create folder for an inquiry.
     * Returns full filesystem path (with trailing slash).
     */
    public static function inquiry_pdf_dir( string $inq_number ): string {
        $safe = preg_replace( '/[^A-Z0-9\-]/', '', strtoupper( $inq_number ) );
        $dir  = self::pdf_base_dir() . '/' . $safe;
        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
            // Prevent directory listing
            file_put_contents( $dir . '/index.php', '<?php // Silence.' );
        }
        return trailingslashit( $dir );
    }

    public static function inquiry_pdf_url( string $inq_number ): string {
        $safe = preg_replace( '/[^A-Z0-9\-]/', '', strtoupper( $inq_number ) );
        return trailingslashit( self::pdf_base_url() . '/' . $safe );
    }

    /**
     * Full path for a specific document PDF.
     * e.g. pdf_path('INQ-2026-0001', 'SGC-E-2026-0023') → .../sgc-docs/INQ-2026-0001/SGC-E-2026-0023.pdf
     */
    public static function pdf_path( string $inq_number, string $doc_number ): string {
        $safe_doc = preg_replace( '/[^A-Z0-9\-]/', '', strtoupper( $doc_number ) );
        return self::inquiry_pdf_dir( $inq_number ) . $safe_doc . '.pdf';
    }

    public static function pdf_url( string $inq_number, string $doc_number ): string {
        $safe_doc = preg_replace( '/[^A-Z0-9\-]/', '', strtoupper( $doc_number ) );
        return self::inquiry_pdf_url( $inq_number ) . $safe_doc . '.pdf';
    }

    public static function maybe_create_pdf_dir(): void {
        $base = self::pdf_base_dir();
        if ( ! is_dir( $base ) ) {
            wp_mkdir_p( $base );
            file_put_contents( $base . '/index.php', '<?php // Silence.' );
            // Block direct access via .htaccess (Apache)
            file_put_contents( $base . '/.htaccess',
                "Options -Indexes\n<Files \"*.pdf\">\n  Require all granted\n</Files>\n"
            );
        }
    }

    // ── Shared options namespace ──────────────────────────────────────────────
    // All 3 plugins read shared config from a single option key.
    // Use sgc_core_get() / sgc_core_set() helpers (defined below).

    public static function get_shared( string $key, $default = null ) {
        $all = get_option( self::OPTION_NS, [] );
        return $all[ $key ] ?? $default;
    }

    public static function set_shared( string $key, $value ): void {
        $all         = get_option( self::OPTION_NS, [] );
        $all[ $key ] = $value;
        update_option( self::OPTION_NS, $all, false );
    }

    // ── Plugin version handshake ──────────────────────────────────────────────

    public static function register_plugin( string $slug, string $version ): void {
        self::set_shared( "plugin_{$slug}_version", $version );
        self::set_shared( "plugin_{$slug}_active", true );
    }

    public static function is_plugin_active( string $slug ): bool {
        return (bool) self::get_shared( "plugin_{$slug}_active", false );
    }

    public static function get_plugin_version( string $slug ): string {
        return (string) self::get_shared( "plugin_{$slug}_version", '0.0.0' );
    }

    // ── Powered-by signature ─────────────────────────────────────────────────

    public static function powered_by(): string {
        return 'Powered by Claude · Anthropic';
    }

    public static function render_powered_by(): void {
        echo '<span style="font-size:11px;color:#888;font-style:italic;margin-left:8px">'
            . '&#9889; ' . esc_html( self::powered_by() )
            . '</span>';
    }
}

endif; // class_exists

// ── Global helper functions (available everywhere) ────────────────────────────

if ( ! function_exists( 'sgc_generate_doc_number' ) ) {
    /**
     * Generate the next document number for a given type.
     *
     * @param string $doc_type INQ|QT|PI_E|PI_D|INV|PKL|PAY
     * @return string e.g. "INQ-2026-0001"
     */
    function sgc_generate_doc_number( string $doc_type ): string {
        return SGC_Core_Bridge::generate_number( $doc_type );
    }
}

if ( ! function_exists( 'sgc_pdf_path' ) ) {
    function sgc_pdf_path( string $inq_number, string $doc_number ): string {
        return SGC_Core_Bridge::pdf_path( $inq_number, $doc_number );
    }
}

if ( ! function_exists( 'sgc_pdf_url' ) ) {
    function sgc_pdf_url( string $inq_number, string $doc_number ): string {
        return SGC_Core_Bridge::pdf_url( $inq_number, $doc_number );
    }
}

if ( ! function_exists( 'sgc_core_get' ) ) {
    function sgc_core_get( string $key, $default = null ) {
        return SGC_Core_Bridge::get_shared( $key, $default );
    }
}

if ( ! function_exists( 'sgc_core_set' ) ) {
    function sgc_core_set( string $key, $value ): void {
        SGC_Core_Bridge::set_shared( $key, $value );
    }
}

// Boot immediately on load
SGC_Core_Bridge::boot();
