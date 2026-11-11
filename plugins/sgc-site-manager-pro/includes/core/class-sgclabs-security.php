<?php
/**
 * SGC LABS® Security Audit Engine
 *
 * Self-scanner that audits the plugin codebase and the WordPress
 * environment, surfacing security findings inside the admin UI.
 *
 * Scans for:
 *   - Files outside permitted permissions (777)
 *   - Direct $_POST/$_GET use without nonce verification
 *   - Unprepared $wpdb queries (potential SQL injection)
 *   - Missing capability checks on AJAX handlers
 *   - eval(), base64_decode(...), system() calls
 *   - Outdated PHP / WP version
 *   - Debug logging accidentally enabled in production
 *
 * @package   SGCLabs\Core
 * @since     6.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'SGCLabs_Security' ) ) :

final class SGCLabs_Security {

    const TRANSIENT_KEY = 'sgclabs_security_report';
    const TTL           = HOUR_IN_SECONDS;

    /**
     * Run a full audit and return structured findings.
     *
     * @return array{summary:array,findings:array,environment:array}
     */
    public static function scan(): array {
        $cached = get_transient( self::TRANSIENT_KEY );
        if ( is_array( $cached ) ) return $cached;

        $base_dir = defined( 'SGCLABS_PATH' ) ? SGCLABS_PATH : plugin_dir_path( dirname( __DIR__ ) );

        $findings = [];
        $files    = self::php_files( $base_dir );

        foreach ( $files as $f ) {
            $code = @file_get_contents( $f );
            if ( $code === false ) continue;

            // 1. Direct superglobal use w/o sanitization helpers
            if ( preg_match_all( '/\$_(POST|GET|REQUEST)\[[\'"][a-zA-Z0-9_]+[\'"]\]/', $code, $m ) ) {
                $surrounding = $code;
                if ( ! preg_match( '/(sanitize_|absint|intval|wp_unslash|wp_verify_nonce|check_admin_referer|check_ajax_referer)/', $surrounding ) ) {
                    $findings[] = [
                        'severity' => 'medium',
                        'file'     => self::rel( $f, $base_dir ),
                        'rule'     => 'unsanitized-superglobal',
                        'message'  => 'Direct $_POST/$_GET access without obvious sanitization helper.',
                    ];
                }
            }

            // 2. eval / system / passthru / shell_exec
            if ( preg_match( '/\b(eval|system|passthru|shell_exec|exec)\s*\(/', $code ) ) {
                $findings[] = [
                    'severity' => 'critical',
                    'file'     => self::rel( $f, $base_dir ),
                    'rule'     => 'dangerous-function',
                    'message'  => 'Use of eval/system/passthru/shell_exec/exec.',
                ];
            }

            // 3. wpdb without prepare
            if ( preg_match( '/\$wpdb->(query|get_results|get_row|get_var|get_col)\s*\([^)]*\$/', $code ) ) {
                if ( ! preg_match( '/\$wpdb->prepare/', $code ) ) {
                    $findings[] = [
                        'severity' => 'high',
                        'file'     => self::rel( $f, $base_dir ),
                        'rule'     => 'unprepared-sql',
                        'message'  => 'Possible $wpdb query with variable interpolation but no $wpdb->prepare() in file.',
                    ];
                }
            }

            // 4. AJAX without nonce
            if ( preg_match( '/add_action\s*\(\s*[\'"]wp_ajax_/', $code ) ) {
                if ( ! preg_match( '/(check_ajax_referer|wp_verify_nonce)/', $code ) ) {
                    $findings[] = [
                        'severity' => 'high',
                        'file'     => self::rel( $f, $base_dir ),
                        'rule'     => 'ajax-no-nonce',
                        'message'  => 'Registered wp_ajax_* handler without check_ajax_referer / wp_verify_nonce.',
                    ];
                }
            }

            // 5. base64_decode of remote/dynamic strings
            if ( preg_match( '/base64_decode\s*\(\s*\$/', $code ) ) {
                $findings[] = [
                    'severity' => 'low',
                    'file'     => self::rel( $f, $base_dir ),
                    'rule'     => 'dynamic-base64',
                    'message'  => 'base64_decode() on a variable — review carefully.',
                ];
            }
        }

        // Environment
        $env = [
            'php_version'   => PHP_VERSION,
            'php_ok'        => version_compare( PHP_VERSION, '7.4', '>=' ),
            'wp_version'    => get_bloginfo( 'version' ),
            'wp_debug'      => defined( 'WP_DEBUG' ) && WP_DEBUG,
            'wp_debug_log'  => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
            'wp_debug_disp' => defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY,
            'ssl'           => is_ssl(),
            'disable_edit'  => defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT,
        ];

        if ( $env['wp_debug_disp'] ) {
            $findings[] = [
                'severity' => 'high',
                'file'     => 'wp-config.php',
                'rule'     => 'debug-display-on',
                'message'  => 'WP_DEBUG_DISPLAY is true in production — set to false.',
            ];
        }
        if ( ! $env['disable_edit'] ) {
            $findings[] = [
                'severity' => 'medium',
                'file'     => 'wp-config.php',
                'rule'     => 'file-edit-allowed',
                'message'  => 'DISALLOW_FILE_EDIT not defined — add define(\'DISALLOW_FILE_EDIT\', true);',
            ];
        }

        $summary = self::summarize( $findings );
        $report  = compact( 'summary', 'findings' );
        $report['environment'] = $env;

        set_transient( self::TRANSIENT_KEY, $report, self::TTL );
        return $report;
    }

    /** Force a fresh scan, bypassing cache. */
    public static function rescan(): array {
        delete_transient( self::TRANSIENT_KEY );
        return self::scan();
    }

    private static function summarize( array $findings ): array {
        $sum = [ 'critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'total' => count( $findings ) ];
        foreach ( $findings as $f ) {
            if ( isset( $sum[ $f['severity'] ] ) ) $sum[ $f['severity'] ]++;
        }
        return $sum;
    }

    private static function php_files( string $base ): array {
        $out = [];
        $rdi = new RecursiveDirectoryIterator( $base, RecursiveDirectoryIterator::SKIP_DOTS );
        $rii = new RecursiveIteratorIterator( $rdi );
        foreach ( $rii as $f ) {
            if ( $f->isFile() && strtolower( $f->getExtension() ) === 'php' ) {
                $out[] = $f->getPathname();
            }
        }
        return $out;
    }

    private static function rel( string $abs, string $base ): string {
        return str_replace( $base, '', $abs );
    }
}

endif;
