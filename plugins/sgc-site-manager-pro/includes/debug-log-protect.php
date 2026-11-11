<?php
/**
 * SGC LABS® — Debug Log Protection
 * Blocks public access to debug.log via .htaccess rule injection
 * v7.4.8: Added on 2026-05-22 after public exposure found
 */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_init', 'sgclabs_protect_debug_log' );

function sgclabs_protect_debug_log() {
    // Only run once — check if already protected
    if ( get_option( 'sgclabs_debug_log_protected' ) === '1' ) return;

    // v7.5.1: re-check at most once a day — previously logged a line on EVERY admin request
    if ( get_transient( 'sgclabs_debug_log_protect_retry' ) ) return;
    set_transient( 'sgclabs_debug_log_protect_retry', 1, DAY_IN_SECONDS );

    $htaccess = ABSPATH . '.htaccess';
    if ( ! file_exists( $htaccess ) || ! is_writable( $htaccess ) ) {
        error_log( '[SGC-SM] .htaccess not writable — debug.log protection skipped (will retry in 24h)' );
        return;
    }

    $current = file_get_contents( $htaccess );

    // Already has our rule
    if ( strpos( $current, 'sgclabs-debug-protect' ) !== false ) {
        update_option( 'sgclabs_debug_log_protected', '1' );
        return;
    }

    $rule = "\n# BEGIN sgclabs-debug-protect\n"
          . "<Files \"debug.log\">\n"
          . "    Require all denied\n"
          . "</Files>\n"
          . "<FilesMatch \"\\.(log|txt|sql|bak|tar|gz|zip)$\">\n"
          . "    <If \"%{REQUEST_URI} =~ m#wp-content/(debug|error|php_error)#\">\n"
          . "        Require all denied\n"
          . "    </If>\n"
          . "</FilesMatch>\n"
          . "# END sgclabs-debug-protect\n";

    file_put_contents( $htaccess, $current . $rule );
    update_option( 'sgclabs_debug_log_protected', '1' );
    error_log( '[SGC-SM] debug.log protection added to .htaccess' );
}
