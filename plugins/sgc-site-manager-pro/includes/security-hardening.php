<?php
/**
 * SGC LABS® Security Hardening v1.0.0
 *
 * Plugin-level security layer:
 *   - Public form rate limiting (quote, inquiry, contact)
 *   - Input sanitization enforcer
 *   - SQL injection detection
 *   - XSS output audit hooks
 *   - File upload validation
 *   - API key encryption verify
 *   - Security event logger
 *
 * INSTALL: sgc-site-manager-pro/includes/security-hardening.php
 * REGISTER: sgclabs_include_file( SGCLABS_PATH . 'includes/security-hardening.php' );
 *
 * @package SGCLabsManagerPro
 * @since   7.4.7
 * @version 1.0.0 | Build: 20260516-0630
 */

defined( 'ABSPATH' ) || exit;

// ── Constants ──────────────────────────────────────────────────────────────────
define( 'SGC_SEC_VERSION',   '1.0.0' );
define( 'SGC_SEC_LOG',       'sgc_security_log' );
define( 'SGC_SEC_RATE_PFX',  'sgc_rate_' );        // transient prefix for rate limits
define( 'SGC_SEC_MAX_EVENTS', 100 );                // max events stored in log


// ╔══════════════════════════════════════════════════════════════════════════════╗
// ║  SECTION 1 — RATE LIMITING                                                 ║
// ╚══════════════════════════════════════════════════════════════════════════════╝

/**
 * Check if an IP is rate-limited for a given action.
 * Returns true if blocked, false if allowed.
 */
function sgc_sec_is_rate_limited( string $action, int $max = 5, int $window = 300 ): bool {
    $ip  = sgc_sec_get_ip();
    $key = SGC_SEC_RATE_PFX . md5( $action . $ip );
    $count = (int) get_transient( $key );

    if ( $count >= $max ) {
        sgc_sec_log_event( 'rate_limit', "Blocked: {$action} from IP {$ip} (count: {$count})" );
        return true;
    }

    // Increment counter
    if ( $count === 0 ) {
        set_transient( $key, 1, $window );
    } else {
        set_transient( $key, $count + 1, $window );
    }

    return false;
}

/**
 * Get real visitor IP — handles proxies/CDN safely.
 */
function sgc_sec_get_ip(): string {
    $keys = [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ];
    foreach ( $keys as $k ) {
        if ( ! empty( $_SERVER[ $k ] ) ) {
            $ip = trim( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $k ] ) ) )[0] );
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) return $ip;
        }
    }
    return '0.0.0.0';
}

// ── Hook quote form AJAX — rate limit 5 per 5 minutes per IP ─────────────────
add_action( 'wp_ajax_nopriv_sgclabs_submit_b2b_quote', 'sgc_sec_gate_quote_form', 1 );
add_action( 'wp_ajax_sgclabs_submit_b2b_quote',        'sgc_sec_gate_quote_form', 1 );
function sgc_sec_gate_quote_form(): void {
    if ( sgc_sec_is_rate_limited( 'quote_form', 5, 300 ) ) {
        wp_send_json_error( 'Too many requests. Please wait 5 minutes before trying again.' );
    }
}

// ── Hook inquiry form — rate limit 10 per 10 minutes ─────────────────────────
add_action( 'wp_ajax_nopriv_sgclabs_submit_inquiry', 'sgc_sec_gate_inquiry', 1 );
add_action( 'wp_ajax_sgclabs_submit_inquiry',        'sgc_sec_gate_inquiry', 1 );
function sgc_sec_gate_inquiry(): void {
    if ( sgc_sec_is_rate_limited( 'inquiry_form', 10, 600 ) ) {
        wp_send_json_error( 'Too many requests. Please wait before submitting again.' );
    }
}


// ╔══════════════════════════════════════════════════════════════════════════════╗
// ║  SECTION 2 — INPUT SANITIZATION HELPERS                                    ║
// ╚══════════════════════════════════════════════════════════════════════════════╝

/**
 * Sanitize and validate a POST field.
 * Returns sanitized value or empty string.
 */
function sgc_sec_post( string $key, string $type = 'text' ): string {
    if ( ! isset( $_POST[ $key ] ) ) return '';

    $raw = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

    switch ( $type ) {
        case 'email':
            return sanitize_email( $raw );
        case 'int':
            return (string) absint( $raw );
        case 'float':
            return (string) floatval( $raw );
        case 'textarea':
            return sanitize_textarea_field( $raw );
        case 'url':
            return esc_url_raw( $raw );
        case 'key':
            return sanitize_key( $raw );
        case 'slug':
            return sanitize_title( $raw );
        case 'filename':
            return sanitize_file_name( $raw );
        default:
            return sanitize_text_field( $raw );
    }
}

/**
 * Detect potential SQL injection attempts in a string.
 */
function sgc_sec_detect_sqli( string $value ): bool {
    $patterns = [
        '/(\bUNION\b.*\bSELECT\b)/i',
        '/(\bSELECT\b.*\bFROM\b)/i',
        '/(\bDROP\b.*\bTABLE\b)/i',
        '/(\bINSERT\b.*\bINTO\b)/i',
        '/(\bDELETE\b.*\bFROM\b)/i',
        '/(--|\#|\/\*|\*\/)/i',
        '/(\bOR\b\s+["\']?\d+["\']?\s*=\s*["\']?\d+)/i',
        '/(\bAND\b\s+["\']?\d+["\']?\s*=\s*["\']?\d+)/i',
        '/\b(EXEC|EXECUTE|CAST|CONVERT|CHAR|NCHAR|VARCHAR|NVARCHAR|DECLARE|CURSOR|FETCH)\b/i',
    ];

    foreach ( $patterns as $pattern ) {
        if ( preg_match( $pattern, $value ) ) {
            return true;
        }
    }
    return false;
}

/**
 * Detect potential XSS attempts in a string.
 */
function sgc_sec_detect_xss( string $value ): bool {
    $patterns = [
        '/<script\b[^>]*>/i',
        '/javascript\s*:/i',
        '/on\w+\s*=\s*["\'][^"\']*["\']/i',
        '/<iframe\b/i',
        '/<object\b/i',
        '/<embed\b/i',
        '/eval\s*\(/i',
        '/document\s*\.\s*cookie/i',
        '/document\s*\.\s*write/i',
    ];

    foreach ( $patterns as $pattern ) {
        if ( preg_match( $pattern, $value ) ) {
            return true;
        }
    }
    return false;
}

/**
 * Scan all POST data for SQLi/XSS — call at start of sensitive AJAX handlers.
 * Returns true if clean, false + logs if suspicious.
 */
function sgc_sec_scan_input(): bool {
    foreach ( $_POST as $key => $value ) {
        $str = is_array( $value ) ? implode( ' ', $value ) : (string) $value;

        if ( sgc_sec_detect_sqli( $str ) ) {
            sgc_sec_log_event( 'sqli_attempt', "Key: {$key} | IP: " . sgc_sec_get_ip() . " | Value: " . substr( $str, 0, 100 ) );
            return false;
        }
        if ( sgc_sec_detect_xss( $str ) ) {
            sgc_sec_log_event( 'xss_attempt', "Key: {$key} | IP: " . sgc_sec_get_ip() . " | Value: " . substr( $str, 0, 100 ) );
            return false;
        }
    }
    return true;
}


// ╔══════════════════════════════════════════════════════════════════════════════╗
// ║  SECTION 3 — FILE UPLOAD VALIDATION                                        ║
// ╚══════════════════════════════════════════════════════════════════════════════╝

/**
 * Validate an uploaded file for quote/inquiry forms.
 * Returns true if safe, WP_Error if not.
 */
function sgc_sec_validate_upload( array $file ): bool|\WP_Error {

    // Allowed MIME types
    $allowed_types = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    // Max size: 5MB
    $max_size = 5 * 1024 * 1024;

    if ( $file['size'] > $max_size ) {
        return new \WP_Error( 'file_too_large', 'File too large. Max 5MB allowed.' );
    }

    // Check MIME type using finfo (not just extension)
    $finfo     = finfo_open( FILEINFO_MIME_TYPE );
    $mime_type = finfo_file( $finfo, $file['tmp_name'] );
    finfo_close( $finfo );

    if ( ! in_array( $mime_type, $allowed_types, true ) ) {
        sgc_sec_log_event( 'bad_upload', "Blocked MIME: {$mime_type} | IP: " . sgc_sec_get_ip() );
        return new \WP_Error( 'invalid_type', 'File type not allowed. Allowed: PDF, JPG, PNG, DOC.' );
    }

    // Check extension matches MIME
    $ext_map = [
        'application/pdf'  => ['pdf'],
        'image/jpeg'       => ['jpg', 'jpeg'],
        'image/png'        => ['png'],
        'image/gif'        => ['gif'],
        'image/webp'       => ['webp'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
    ];

    $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
    $allowed_exts = $ext_map[ $mime_type ] ?? [];

    if ( ! in_array( $ext, $allowed_exts, true ) ) {
        sgc_sec_log_event( 'ext_mismatch', "MIME: {$mime_type} but ext: {$ext} | IP: " . sgc_sec_get_ip() );
        return new \WP_Error( 'ext_mismatch', 'File extension does not match file type.' );
    }

    return true;
}


// ╔══════════════════════════════════════════════════════════════════════════════╗
// ║  SECTION 4 — API KEY ENCRYPTION VERIFY                                     ║
// ╚══════════════════════════════════════════════════════════════════════════════╝

/**
 * Verify all SGC API keys are encrypted (not stored as plain text).
 * Runs weekly — logs warning if plain text key found.
 */
add_action( SGC_REPAIR_CRON, 'sgc_sec_verify_api_key_encryption' );
function sgc_sec_verify_api_key_encryption(): void {

    $api_key_options = [
        'sgclabs_claude_api_key',
        'sgclabs_openai_api_key',
        'sgclabs_gemini_api_key',
        'sgclabs_deepseek_api_key',
        'sgclabs_mistral_api_key',
        'sgc_crm_ai_api_key',
    ];

    foreach ( $api_key_options as $option ) {
        $value = get_option( $option, '' );
        if ( empty( $value ) ) continue;

        // Encrypted values start with base64 chars and are long
        // Plain text API keys from providers usually start with specific prefixes
        $plain_indicators = [ 'sk-', 'AIza', 'gsk_', 'Bearer ' ];
        foreach ( $plain_indicators as $prefix ) {
            if ( str_starts_with( $value, $prefix ) ) {
                sgc_sec_log_event(
                    'unencrypted_key',
                    "Warning: API key in '{$option}' appears unencrypted. Save it again via SGC AI Settings to encrypt."
                );
                break;
            }
        }
    }
}


// ╔══════════════════════════════════════════════════════════════════════════════╗
// ║  SECTION 5 — SECURITY EVENT LOGGER                                         ║
// ╚══════════════════════════════════════════════════════════════════════════════╝

/**
 * Log a security event.
 */
function sgc_sec_log_event( string $type, string $message ): void {
    $log   = get_option( SGC_SEC_LOG, [] );
    $log[] = [
        'time'    => current_time( 'mysql' ),
        'type'    => $type,
        'message' => $message,
        'ip'      => sgc_sec_get_ip(),
        'url'     => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
    ];

    // Keep last N events only
    if ( count( $log ) > SGC_SEC_MAX_EVENTS ) {
        $log = array_slice( $log, -SGC_SEC_MAX_EVENTS );
    }

    update_option( SGC_SEC_LOG, $log, false );

    // Also write to WP debug log
    error_log( "SGC_SECURITY [{$type}]: {$message}" );
}

/**
 * Get security log.
 */
function sgc_sec_get_log(): array {
    return array_reverse( get_option( SGC_SEC_LOG, [] ) );
}

/**
 * Clear security log.
 */
function sgc_sec_clear_log(): void {
    update_option( SGC_SEC_LOG, [], false );
}

// AJAX: clear log
add_action( 'wp_ajax_sgc_sec_clear_log', function() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied.' );
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'sgc_sec_nonce' ) ) {
        wp_send_json_error( 'Security check failed.' );
    }
    sgc_sec_clear_log();
    wp_send_json_success( 'Log cleared.' );
} );


// ╔══════════════════════════════════════════════════════════════════════════════╗
// ║  SECTION 6 — ADMIN SECURITY LOG PAGE                                       ║
// ╚══════════════════════════════════════════════════════════════════════════════╝

add_action( 'admin_menu', 'sgc_sec_register_menu', 27 );
function sgc_sec_register_menu(): void {
    add_submenu_page(
        'sgclabs-manager',
        'Security Log',
        '🔒 Security Log',
        'manage_options',
        'sgclabs-security-log',
        'sgc_sec_render_page'
    );
}

function sgc_sec_render_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $log   = sgc_sec_get_log();
    $nonce = wp_create_nonce( 'sgc_sec_nonce' );
    $count = count( $log );

    $type_colors = [
        'rate_limit'       => [ '#fef2f2', '#dc2626', '🚫' ],
        'sqli_attempt'     => [ '#fef2f2', '#991b1b', '💉' ],
        'xss_attempt'      => [ '#fef2f2', '#991b1b', '⚡' ],
        'bad_upload'       => [ '#fffbeb', '#d97706', '📁' ],
        'ext_mismatch'     => [ '#fffbeb', '#d97706', '📁' ],
        'unencrypted_key'  => [ '#fffbeb', '#d97706', '🔑' ],
        'malware'          => [ '#fef2f2', '#991b1b', '☣️' ],
        'file_changed'     => [ '#fffbeb', '#d97706', '📝' ],
    ];
    ?>
    <div class="wrap" style="max-width:960px">

        <div style="background:#1a1a2e;color:#fff;padding:20px 24px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div>
                <h1 style="color:#fff;margin:0;font-size:22px">🔒 SGC Security Log</h1>
                <p style="margin:4px 0 0;color:#9090cc;font-size:13px"><?php echo esc_html($count); ?> events recorded</p>
            </div>
            <button id="sgc-sec-clear-btn" onclick="sgcSecClearLog('<?php echo esc_js($nonce); ?>')"
                style="background:#dc2626;color:#fff;border:none;padding:8px 18px;border-radius:6px;font-size:13px;cursor:pointer">
                🗑️ Clear Log
            </button>
        </div>

        <?php if ( empty( $log ) ) : ?>
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:30px;text-align:center">
            <div style="font-size:32px">🛡️</div>
            <div style="font-size:15px;font-weight:700;color:#15803d;margin-top:8px">No security events recorded</div>
            <div style="font-size:13px;color:#6b7280;margin-top:4px">Site is clean — no attacks detected.</div>
        </div>
        <?php else : ?>

        <!-- Stats bar -->
        <?php
        $types_count = [];
        foreach ( $log as $e ) {
            $types_count[ $e['type'] ] = ( $types_count[ $e['type'] ] ?? 0 ) + 1;
        }
        ?>
        <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
            <?php foreach ( $types_count as $type => $cnt ) :
                [$bg, $clr, $icon] = $type_colors[$type] ?? ['#f8fafc','#6b7280','⚠️'];
            ?>
            <div style="background:<?php echo esc_attr($bg); ?>;border:1px solid <?php echo esc_attr($clr); ?>22;border-radius:8px;padding:10px 14px;text-align:center;min-width:100px">
                <div style="font-size:18px;font-weight:700;color:<?php echo esc_attr($clr); ?>"><?php echo esc_html($cnt); ?></div>
                <div style="font-size:11px;color:#6b7280"><?php echo esc_html($icon . ' ' . str_replace('_', ' ', $type)); ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Events table -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden">
            <table style="width:100%;border-collapse:collapse;font-size:12px">
                <thead>
                    <tr style="background:#f8fafc">
                        <th style="padding:10px 14px;text-align:left;color:#6b7280;font-weight:600">Time</th>
                        <th style="padding:10px 14px;text-align:left;color:#6b7280;font-weight:600">Type</th>
                        <th style="padding:10px 14px;text-align:left;color:#6b7280;font-weight:600">IP</th>
                        <th style="padding:10px 14px;text-align:left;color:#6b7280;font-weight:600">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $log as $event ) :
                        [$bg, $clr, $icon] = $type_colors[$event['type']] ?? ['#fff','#374151','⚠️'];
                    ?>
                    <tr style="border-top:1px solid #f3f4f6;background:<?php echo esc_attr($bg); ?>">
                        <td style="padding:8px 14px;color:#6b7280;white-space:nowrap"><?php echo esc_html($event['time']); ?></td>
                        <td style="padding:8px 14px">
                            <span style="background:<?php echo esc_attr($clr); ?>;color:#fff;font-size:10px;padding:2px 7px;border-radius:20px;font-weight:700">
                                <?php echo esc_html($icon . ' ' . $event['type']); ?>
                            </span>
                        </td>
                        <td style="padding:8px 14px;color:#374151;font-family:monospace"><?php echo esc_html($event['ip']); ?></td>
                        <td style="padding:8px 14px;color:#374151"><?php echo esc_html( substr($event['message'], 0, 120) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
    function sgcSecClearLog(nonce) {
        if (!confirm('Clear all security logs?')) return;
        var fd = new FormData();
        fd.append('action', 'sgc_sec_clear_log');
        fd.append('nonce', nonce);
        fetch(ajaxurl, {method:'POST', body:fd})
        .then(r=>r.json())
        .then(function(res){
            if(res.success) location.reload();
        });
    }
    </script>
    <?php
}
