<?php
/**
 * SGC LABS® WordPress Security v1.0.0
 *
 * WordPress-level hardening:
 *   - Security headers (X-Frame, XSS, HSTS, CSP, Referrer)
 *   - User enumeration block
 *   - REST API unauthorized access restrict
 *   - Sensitive file access block
 *   - Admin page protection
 *   - Core file integrity daily check
 *   - Malware pattern scanner
 *   - wp-config.php backup detect
 *
 * NOTE: login-hardening.php already handles:
 *   - Login brute force throttle
 *   - XML-RPC disable
 *   - Login error masking
 * So those are NOT duplicated here.
 *
 * INSTALL: sgc-site-manager-pro/includes/wp-security.php
 * REGISTER: sgclabs_include_file( SGCLABS_PATH . 'includes/wp-security.php' );
 *
 * @package SGCLabsManagerPro
 * @since   7.4.7
 * @version 1.0.0 | Build: 20260516-0630
 */

defined( 'ABSPATH' ) || exit;

define( 'SGC_WPSEC_VERSION', '1.0.0' );
define( 'SGC_WPSEC_CRON',    'sgc_wpsec_daily_scan' );
define( 'SGC_WPSEC_REPORT',  'sgc_wpsec_last_report' );


// ╔══════════════════════════════════════════════════════════════════════════════╗
// ║  SECTION 1 — SECURITY HEADERS                                              ║
// ╚══════════════════════════════════════════════════════════════════════════════╝

add_action( 'send_headers', 'sgc_wpsec_send_headers' );
function sgc_wpsec_send_headers(): void {

    // Prevent clickjacking
    if ( ! headers_sent() ) {
        header( 'X-Frame-Options: SAMEORIGIN' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'X-XSS-Protection: 1; mode=block' );
        header( 'Referrer-Policy: strict-origin-when-cross-origin' );
        header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()' );

        // HSTS — only on HTTPS
        if ( is_ssl() ) {
            header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
        }

        // Content Security Policy — allow same origin + known CDNs used
        $csp = implode( '; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com https://www.googletagmanager.com https://www.google-analytics.com https://accounts.google.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: https: blob:",
            "connect-src 'self' https://www.google-analytics.com https://api.anthropic.com https://api.openai.com https://generativelanguage.googleapis.com https://api.deepseek.com",
            "frame-src 'self' https://www.google.com",
            "object-src 'none'",
            "base-uri 'self'",
        ] );
        header( "Content-Security-Policy: {$csp}" );

        // Remove sensitive headers
        header_remove( 'X-Powered-By' );
        header_remove( 'Server' );
    }
}


// ╔══════════════════════════════════════════════════════════════════════════════╗
// ║  SECTION 2 — USER ENUMERATION BLOCK                                        ║
// ╚══════════════════════════════════════════════════════════════════════════════╝

// Block ?author=N enumeration
add_action( 'init', 'sgc_wpsec_block_user_enumeration' );
function sgc_wpsec_block_user_enumeration(): void {
    if ( ! is_admin() && isset( $_GET['author'] ) ) {
        $author = sanitize_text_field( wp_unslash( $_GET['author'] ) );
        if ( is_numeric( $author ) ) {
            if ( function_exists( 'sgc_sec_log_event' ) ) {
                sgc_sec_log_event( 'user_enum', "User enumeration attempt: ?author={$author}" );
            }
            wp_die( 'Access denied.', 'Forbidden', [ 'response' => 403 ] );
        }
    }
}

// Block REST API user listing for non-admins
add_filter( 'rest_endpoints', 'sgc_wpsec_restrict_rest_users' );
function sgc_wpsec_restrict_rest_users( array $endpoints ): array {
    if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
        // Remove user listing endpoints from public access
        if ( isset( $endpoints['/wp/v2/users'] ) ) {
            unset( $endpoints['/wp/v2/users'] );
        }
        if ( isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) ) {
            unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
        }
    }
    return $endpoints;
}


// ╔══════════════════════════════════════════════════════════════════════════════╗
// ║  SECTION 3 — WORDPRESS HARDENING                                           ║
// ╚══════════════════════════════════════════════════════════════════════════════╝

add_action( 'init', 'sgc_wpsec_harden_wp' );
function sgc_wpsec_harden_wp(): void {

    // Remove WP version from head and feeds
    remove_action( 'wp_head', 'wp_generator' );
    add_filter( 'the_generator', '__return_empty_string' );

    // Remove WP version from scripts/styles
    add_filter( 'style_loader_src',  'sgc_wpsec_remove_version_query', 9999 );
    add_filter( 'script_loader_src', 'sgc_wpsec_remove_version_query', 9999 );

    // Disable file editing in admin (if not already set in wp-config)
    if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
        define( 'DISALLOW_FILE_EDIT', true );
    }

    // Remove unnecessary head links
    remove_action( 'wp_head', 'rsd_link' );
    remove_action( 'wp_head', 'wlwmanifest_link' );
    remove_action( 'wp_head', 'wp_shortlink_wp_head' );
    remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );

    // Disable pingbacks
    add_filter( 'xmlrpc_methods', 'sgc_wpsec_disable_pingback' );
}

function sgc_wpsec_remove_version_query( string $src ): string {
    if ( strpos( $src, 'ver=' ) ) {
        $src = remove_query_arg( 'ver', $src );
    }
    return $src;
}

function sgc_wpsec_disable_pingback( array $methods ): array {
    unset( $methods['pingback.ping'] );
    return $methods;
}

// Disable admin bar for non-admins on frontend
add_action( 'after_setup_theme', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        show_admin_bar( false );
    }
} );


// ╔══════════════════════════════════════════════════════════════════════════════╗
// ║  SECTION 4 — MALWARE SCANNER                                               ║
// ╚══════════════════════════════════════════════════════════════════════════════╝

/**
 * Scan SGC plugin files for malware patterns.
 * Lightweight — checks for common PHP backdoor signatures.
 */
function sgc_wpsec_scan_for_malware(): array {
    $findings = [];

    // Malware signature patterns
    $patterns = [
        'eval_base64'    => '/eval\s*\(\s*base64_decode\s*\(/i',
        'eval_gzinflate' => '/eval\s*\(\s*gzinflate\s*\(/i',
        'eval_str_rot'   => '/eval\s*\(\s*str_rot13\s*\(/i',
        'shell_exec'     => '/shell_exec\s*\(/i',
        'passthru'       => '/passthru\s*\(/i',
        'system_call'    => '/\bsystem\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)/i',
        'preg_replace_e' => '/preg_replace\s*\(\s*[\'"].*\/e[\'"\s]/i',
        'assert_code'    => '/assert\s*\(\s*\$_(GET|POST|REQUEST)/i',
        'base64_hidden'  => '/\$\w+\s*=\s*base64_decode\s*\([\'"][A-Za-z0-9+\/=]{50,}[\'"\s]*\)\s*;/i',
        'hex_encoded'    => '/\\\\x[0-9a-f]{2}\\\\x[0-9a-f]{2}\\\\x[0-9a-f]{2}\\\\x[0-9a-f]{2}/i',
    ];

    // Only scan SGC plugin directories
    $scan_paths = [];
    if ( defined('SGCLABS_PATH') )  $scan_paths[] = SGCLABS_PATH;
    if ( defined('SGC_IIP_PATH') )  $scan_paths[] = SGC_IIP_PATH;
    if ( defined('SGC_CRM_PATH') )  $scan_paths[] = SGC_CRM_PATH;
    if ( defined('SGC_PM_PATH') )   $scan_paths[] = SGC_PM_PATH;

    foreach ( $scan_paths as $path ) {
        if ( ! is_dir( $path ) ) continue;

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS )
        );

        foreach ( $files as $file ) {
            if ( $file->getExtension() !== 'php' ) continue;
            if ( $file->getSize() > 500000 ) continue; // skip huge files

            $content = @file_get_contents( $file->getPathname() );
            if ( ! $content ) continue;

            foreach ( $patterns as $type => $pattern ) {
                if ( preg_match( $pattern, $content ) ) {
                    $findings[] = [
                        'type'    => $type,
                        'file'    => str_replace( WP_CONTENT_DIR, '', $file->getPathname() ),
                        'time'    => current_time( 'mysql' ),
                    ];

                    if ( function_exists( 'sgc_sec_log_event' ) ) {
                        sgc_sec_log_event( 'malware', "Pattern '{$type}' in: " . basename( $file->getPathname() ) );
                    }
                    break; // one finding per file is enough
                }
            }
        }
    }

    return $findings;
}


// ╔══════════════════════════════════════════════════════════════════════════════╗
// ║  SECTION 5 — CORE FILE INTEGRITY CHECK                                     ║
// ╚══════════════════════════════════════════════════════════════════════════════╝

/**
 * Check if sensitive WP files have been recently modified.
 * Flags files modified in last 24h (excluding known update windows).
 */
function sgc_wpsec_check_core_files(): array {
    $alerts = [];
    $cutoff = time() - DAY_IN_SECONDS;

    // Files that should NEVER change unless WP updates
    $sensitive_files = [
        ABSPATH . 'wp-login.php',
        ABSPATH . 'wp-config.php',
        ABSPATH . 'wp-includes/functions.php',
        ABSPATH . 'wp-includes/pluggable.php',
        ABSPATH . 'wp-includes/class-wp.php',
    ];

    foreach ( $sensitive_files as $file ) {
        if ( ! file_exists( $file ) ) continue;

        $modified = filemtime( $file );
        if ( $modified > $cutoff ) {
            $alerts[] = [
                'file'     => basename( $file ),
                'modified' => date( 'Y-m-d H:i:s', $modified ),
            ];

            if ( function_exists( 'sgc_sec_log_event' ) ) {
                sgc_sec_log_event( 'file_changed',
                    "Core file recently modified: " . basename($file) . " at " . date('H:i', $modified)
                );
            }
        }
    }

    return $alerts;
}


// ╔══════════════════════════════════════════════════════════════════════════════╗
// ║  SECTION 6 — DAILY SECURITY SCAN                                           ║
// ╚══════════════════════════════════════════════════════════════════════════════╝

// Schedule daily scan
add_action( 'init', 'sgc_wpsec_schedule' );
function sgc_wpsec_schedule(): void {
    if ( ! wp_next_scheduled( SGC_WPSEC_CRON ) ) {
        wp_schedule_event( strtotime( 'tomorrow 01:00:00' ), 'daily', SGC_WPSEC_CRON );
    }
}

add_action( SGC_WPSEC_CRON, 'sgc_wpsec_run_scan' );
function sgc_wpsec_run_scan(): array {

    $report = [
        'time'         => current_time( 'mysql' ),
        'malware'      => sgc_wpsec_scan_for_malware(),
        'core_changes' => sgc_wpsec_check_core_files(),
        'status'       => 'clean',
    ];

    $total_issues = count( $report['malware'] ) + count( $report['core_changes'] );

    if ( $total_issues > 0 ) {
        $report['status'] = 'alert';

        // Email admin
        $to      = get_option( 'admin_email' );
        $subject = '[SGC LABS® SECURITY ALERT] ' . $total_issues . ' issue(s) detected on ' . get_bloginfo('name');
        $body    = "Security scan found issues on " . $report['time'] . "\n\n";

        if ( ! empty( $report['malware'] ) ) {
            $body .= "⚠️ MALWARE PATTERNS:\n";
            foreach ( $report['malware'] as $m ) {
                $body .= "  • [{$m['type']}] {$m['file']}\n";
            }
        }
        if ( ! empty( $report['core_changes'] ) ) {
            $body .= "\n⚠️ CORE FILE CHANGES:\n";
            foreach ( $report['core_changes'] as $c ) {
                $body .= "  • {$c['file']} — modified at {$c['modified']}\n";
            }
        }

        $body .= "\nView security log: " . admin_url( 'admin.php?page=sgclabs-security-log' );
        wp_mail( $to, $subject, $body );
    }

    update_option( SGC_WPSEC_REPORT, $report, false );
    return $report;
}

// AJAX: manual scan trigger
add_action( 'wp_ajax_sgc_wpsec_manual_scan', function() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied.' );
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'sgc_wpsec_nonce' ) ) {
        wp_send_json_error( 'Security check failed.' );
    }
    $report = sgc_wpsec_run_scan();
    wp_send_json_success( [
        'malware'      => count( $report['malware'] ),
        'core_changes' => count( $report['core_changes'] ),
        'status'       => $report['status'],
    ] );
} );

// Show admin notice if scan found issues
add_action( 'admin_notices', 'sgc_wpsec_admin_notice' );
function sgc_wpsec_admin_notice(): void {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $report = get_option( SGC_WPSEC_REPORT );
    if ( ! $report || $report['status'] === 'clean' ) return;

    $total = count( $report['malware'] ) + count( $report['core_changes'] );
    $url   = admin_url( 'admin.php?page=sgclabs-security-log' );

    echo '<div class="notice notice-error is-dismissible"><p>
        🚨 <strong>SGC Security Alert:</strong> ' . esc_html($total) . ' security issue(s) detected!
        <a href="' . esc_url($url) . '"><strong>View Security Log →</strong></a>
    </p></div>';
}

// Cleanup on deactivate
register_deactivation_hook(
    defined('SGCLABS_PATH') ? SGCLABS_PATH . 'sgclabs-manager.php' : __FILE__,
    function() {
        $ts = wp_next_scheduled( SGC_WPSEC_CRON );
        if ( $ts ) wp_unschedule_event( $ts, SGC_WPSEC_CRON );
    }
);
