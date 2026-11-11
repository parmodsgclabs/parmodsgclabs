<?php
/**
 * SGC LABS® Security Audit — Admin UI
 *
 * @package   SGCLabs\Core
 * @since     6.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_ajax_sgclabs_security_rescan', function() {
    check_ajax_referer( 'sgclabs_security', '_wpnonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( '', 403 );
    if ( ! class_exists( 'SGCLabs_Security' ) ) wp_send_json_error( 'no-class' );
    wp_send_json_success( SGCLabs_Security::rescan() );
} );

function sgclabs_security_audit_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( ! class_exists( 'SGCLabs_Security' ) ) {
        echo '<div class="wrap"><p>Security module not loaded.</p></div>';
        return;
    }

    $report = SGCLabs_Security::scan();
    $sum    = $report['summary'];
    $env    = $report['environment'];
    $nonce  = wp_create_nonce( 'sgclabs_security' );

    $sev_color = [
        'critical' => '#dc2626',
        'high'     => '#f97316',
        'medium'   => '#f59e0b',
        'low'      => '#3b82f6',
    ];
    ?>
    <div class="wrap">
        <h1>🛡️ Security Audit
            <button type="button" class="button button-primary" style="margin-left:12px;" onclick="sgcSecRescan(this)">🔄 Rescan</button>
        </h1>

        <p style="font-size:13px;color:#475569;max-width:780px;">
            Built-in scanner — finds nonce-less AJAX, unprepared SQL, dangerous functions, and bad WP-config flags.
            Cached for 1 hour. Click Rescan for a fresh result.
        </p>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin:20px 0;">
            <?php foreach ( [ 'critical', 'high', 'medium', 'low' ] as $sev ): ?>
            <div style="background:#fff;border:1.5px solid <?php echo $sum[ $sev ] > 0 ? $sev_color[ $sev ] : '#e5e7eb'; ?>;border-radius:10px;padding:14px;text-align:center;">
                <div style="font-size:28px;font-weight:800;color:<?php echo $sum[ $sev ] > 0 ? $sev_color[ $sev ] : '#94a3b8'; ?>;"><?php echo (int) $sum[ $sev ]; ?></div>
                <div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;"><?php echo esc_html( $sev ); ?></div>
            </div>
            <?php endforeach; ?>
            <div style="background:#0f172a;color:#fff;border-radius:10px;padding:14px;text-align:center;">
                <div style="font-size:28px;font-weight:800;"><?php echo (int) $sum['total']; ?></div>
                <div style="font-size:11px;letter-spacing:.5px;text-transform:uppercase;opacity:.8;">Total</div>
            </div>
        </div>

        <h2 style="margin-top:24px;">Environment</h2>
        <table class="widefat striped" style="max-width:680px;">
            <tbody>
                <tr><td>PHP Version</td><td><strong><?php echo esc_html( $env['php_version'] ); ?></strong> <?php echo $env['php_ok'] ? '✅' : '⚠️ Upgrade to 7.4+'; ?></td></tr>
                <tr><td>WordPress Version</td><td><strong><?php echo esc_html( $env['wp_version'] ); ?></strong></td></tr>
                <tr><td>SSL Active</td><td><?php echo $env['ssl'] ? '✅ Yes' : '⚠️ No — HTTPS recommended'; ?></td></tr>
                <tr><td>WP_DEBUG</td><td><?php echo $env['wp_debug'] ? '⚠️ ON (turn off in production)' : '✅ OFF'; ?></td></tr>
                <tr><td>WP_DEBUG_DISPLAY</td><td><?php echo $env['wp_debug_disp'] ? '🔴 ON (security risk)' : '✅ OFF'; ?></td></tr>
                <tr><td>WP_DEBUG_LOG</td><td><?php echo $env['wp_debug_log'] ? '✅ ON' : '○ OFF'; ?></td></tr>
                <tr><td>DISALLOW_FILE_EDIT</td><td><?php echo $env['disable_edit'] ? '✅ Set (good)' : '⚠️ Not set — recommended in production'; ?></td></tr>
            </tbody>
        </table>

        <h2 style="margin-top:24px;">Findings</h2>
        <?php if ( empty( $report['findings'] ) ): ?>
            <div style="background:#f0fdf4;border:1.5px solid #10b981;border-radius:10px;padding:18px;color:#166534;">
                ✅ <strong>Bilkul clean!</strong> Koi security issue nahi mila.
            </div>
        <?php else: ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th style="width:90px;">Severity</th>
                        <th style="width:170px;">Rule</th>
                        <th>Message</th>
                        <th>File</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $report['findings'] as $f ): ?>
                    <tr>
                        <td><span style="background:<?php echo esc_attr( $sev_color[ $f['severity'] ] ?? '#94a3b8' ); ?>;color:#fff;padding:3px 10px;border-radius:20px;font-size:10px;font-weight:700;text-transform:uppercase;"><?php echo esc_html( $f['severity'] ); ?></span></td>
                        <td><code><?php echo esc_html( $f['rule'] ); ?></code></td>
                        <td><?php echo esc_html( $f['message'] ); ?></td>
                        <td><code style="font-size:11px;"><?php echo esc_html( $f['file'] ); ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <script>
    function sgcSecRescan(btn){
        btn.disabled = true; btn.textContent = '⏳ Scanning…';
        const fd = new FormData();
        fd.append('action','sgclabs_security_rescan');
        fd.append('_wpnonce', <?php echo wp_json_encode( $nonce ); ?>);
        fetch(ajaxurl,{method:'POST',body:fd,credentials:'same-origin'})
            .then(r=>r.json()).finally(()=>location.reload());
    }
    </script>
    <?php
}
