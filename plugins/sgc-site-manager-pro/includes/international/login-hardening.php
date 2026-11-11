<?php
/**
 * SGC LABS® Login Hardening
 *
 * - Throttles failed login attempts per IP (5 in 15 min → 30 min lockout)
 * - Hides login error specifics ("Invalid username" / "Invalid password" → generic)
 * - Disables login error to prevent username enumeration via XML-RPC
 * - Blocks suspicious bot user-agents on wp-login.php
 *
 * @package   SGCLabs\International
 * @since     6.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'SGCLabs_Registry' ) ) return;
    if ( ! SGCLabs_Registry::is_active( 'login_hardening' ) ) return;

    // Throttle
    add_action( 'wp_login_failed', 'sgclabs_login_record_failure' );
    add_filter( 'authenticate',     'sgclabs_login_check_lockout', 30, 3 );

    // Generic error
    add_filter( 'login_errors', function( $msg ) {
        return '<strong>Error</strong>: Invalid credentials.';
    } );

    // Disable XML-RPC if disabled in option
    add_filter( 'xmlrpc_enabled', '__return_false' );

    // Block bad bots
    add_action( 'login_init', 'sgclabs_login_block_bots' );
}, 30 );

function sgclabs_login_key( string $ip ): string {
    return 'sgclabs_login_fail_' . md5( $ip );
}

function sgclabs_login_record_failure(): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $k  = sgclabs_login_key( $ip );
    $n  = (int) get_transient( $k );
    set_transient( $k, $n + 1, 15 * MINUTE_IN_SECONDS );

    if ( $n + 1 >= 5 ) {
        set_transient( 'sgclabs_login_lock_' . md5( $ip ), 1, 30 * MINUTE_IN_SECONDS );
    }
}

function sgclabs_login_check_lockout( $user, $username, $password ) {
    if ( empty( $username ) ) return $user;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if ( get_transient( 'sgclabs_login_lock_' . md5( $ip ) ) ) {
        return new WP_Error( 'sgclabs_locked', '<strong>Locked</strong>: Too many failed attempts. Try again in 30 minutes.' );
    }
    return $user;
}

function sgclabs_login_block_bots(): void {
    $ua = strtolower( $_SERVER['HTTP_USER_AGENT'] ?? '' );
    $bad = [ 'curl', 'wget', 'libwww', 'python-requests', 'go-http-client', 'masscan', 'nmap', 'sqlmap' ];
    foreach ( $bad as $b ) {
        if ( $ua && strpos( $ua, $b ) !== false ) {
            status_header( 403 );
            wp_die( 'Forbidden', 'Forbidden', [ 'response' => 403 ] );
        }
    }
}
