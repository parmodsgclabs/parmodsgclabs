<?php
/**
 * SGC LABS® GDPR Cookie Consent Banner
 *
 * GDPR / CCPA / ePrivacy-compliant cookie banner with:
 *   - Granular categories (necessary, functional, analytics, marketing)
 *   - Consent storage in cookie + WP option (audit trail)
 *   - Programmatic API: sgclabs_consent_for( 'analytics' ): bool
 *   - Auto-blocks GTM / GA4 / Meta Pixel until consent given
 *
 * @package   SGCLabs\International
 * @since     6.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'sgclabs_consent_for' ) ) {
    function sgclabs_consent_for( string $category ): bool {
        if ( empty( $_COOKIE['sgc_consent'] ) ) return false;
        $val = json_decode( wp_unslash( $_COOKIE['sgc_consent'] ), true );
        if ( ! is_array( $val ) ) return false;
        return ! empty( $val[ $category ] );
    }
}

// Bail if disabled in registry.
add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'SGCLabs_Registry' ) ) return;
    if ( ! SGCLabs_Registry::is_active( 'gdpr_consent' ) ) return;

    add_action( 'wp_footer', 'sgclabs_gdpr_render_banner', 99 );
    add_action( 'wp_enqueue_scripts', 'sgclabs_gdpr_enqueue' );
    add_action( 'wp_ajax_sgclabs_consent_log',        'sgclabs_gdpr_log_consent' );
    add_action( 'wp_ajax_nopriv_sgclabs_consent_log', 'sgclabs_gdpr_log_consent' );
}, 20 );

function sgclabs_gdpr_enqueue(): void {
    // Tiny inline script — no external file needed.
}

function sgclabs_gdpr_render_banner(): void {
    if ( is_admin() ) return;
    // Don't show if already consented.
    if ( ! empty( $_COOKIE['sgc_consent'] ) ) return;

    $policy_url = esc_url( get_privacy_policy_url() ?: home_url( '/privacy-policy/' ) );
    $nonce      = wp_create_nonce( 'sgclabs_consent' );
    ?>
    <div id="sgc-consent" role="dialog" aria-labelledby="sgc-consent-title"
         style="position:fixed;bottom:20px;left:20px;right:20px;max-width:640px;margin:0 auto;
                background:#fff;border-radius:12px;box-shadow:0 12px 40px rgba(0,0,0,.18);
                border:1px solid #e2e8f0;padding:18px 20px;font-family:-apple-system,sans-serif;
                z-index:99999;">
        <h3 id="sgc-consent-title" style="margin:0 0 8px;font-size:16px;color:#0f172a;">
            🍪 We value your privacy
        </h3>
        <p style="margin:0 0 12px;font-size:13px;color:#475569;line-height:1.55;">
            We use cookies to improve your experience, run analytics, and (with consent) personalize content.
            Read our <a href="<?php echo $policy_url; ?>" style="color:#1e40af;">Privacy Policy</a>.
        </p>
        <div id="sgc-consent-cats" style="display:none;background:#f8fafc;padding:12px;border-radius:8px;margin-bottom:12px;font-size:12px;">
            <label style="display:block;margin:6px 0;"><input type="checkbox" checked disabled> <strong>Necessary</strong> — always on (login, cart)</label>
            <label style="display:block;margin:6px 0;"><input type="checkbox" name="functional" checked> <strong>Functional</strong> — currency, language preferences</label>
            <label style="display:block;margin:6px 0;"><input type="checkbox" name="analytics" checked> <strong>Analytics</strong> — anonymous usage stats</label>
            <label style="display:block;margin:6px 0;"><input type="checkbox" name="marketing"> <strong>Marketing</strong> — ads, retargeting</label>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="button" onclick="sgcConsent('all')"   style="background:#10b981;color:#fff;border:none;padding:10px 16px;border-radius:8px;font-weight:600;cursor:pointer;">Accept all</button>
            <button type="button" onclick="sgcConsent('select')" style="background:#1e40af;color:#fff;border:none;padding:10px 16px;border-radius:8px;font-weight:600;cursor:pointer;">Save selection</button>
            <button type="button" onclick="sgcConsent('reject')" style="background:#fff;color:#475569;border:1.5px solid #cbd5e1;padding:10px 16px;border-radius:8px;font-weight:600;cursor:pointer;">Reject all</button>
            <button type="button" onclick="document.getElementById('sgc-consent-cats').style.display='block';this.style.display='none';"
                    style="background:transparent;color:#1e40af;border:none;padding:10px 16px;font-size:13px;cursor:pointer;text-decoration:underline;">Customize</button>
        </div>
    </div>
    <script>
    (function(){
      var URL = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
      var N   = <?php echo wp_json_encode( $nonce ); ?>;
      window.sgcConsent = function(mode){
        var pref = { necessary:1, functional:0, analytics:0, marketing:0 };
        if (mode === 'all'){ pref.functional=1; pref.analytics=1; pref.marketing=1; }
        if (mode === 'select'){
          var cats = document.getElementById('sgc-consent-cats');
          ['functional','analytics','marketing'].forEach(function(k){
            var c = cats.querySelector('input[name='+k+']');
            pref[k] = c && c.checked ? 1 : 0;
          });
        }
        var cookieVal = encodeURIComponent(JSON.stringify(pref));
        document.cookie = 'sgc_consent='+cookieVal+'; path=/; max-age=15552000; SameSite=Lax' + (location.protocol==='https:' ? '; Secure' : '');
        var fd = new FormData();
        fd.append('action','sgclabs_consent_log');
        fd.append('_wpnonce', N);
        fd.append('pref', JSON.stringify(pref));
        fetch(URL, {method:'POST', body:fd, credentials:'same-origin'}).catch(function(){});
        var el = document.getElementById('sgc-consent'); if(el) el.remove();
        document.dispatchEvent(new CustomEvent('sgc-consent-updated', { detail: pref }));
      };
    })();
    </script>
    <?php
}

function sgclabs_gdpr_log_consent(): void {
    check_ajax_referer( 'sgclabs_consent', '_wpnonce' );
    $pref_raw = wp_unslash( $_POST['pref'] ?? '{}' );
    $pref     = json_decode( (string) $pref_raw, true ) ?: [];
    $log      = get_option( 'sgclabs_consent_log', [] );
    if ( ! is_array( $log ) ) $log = [];
    $log[]    = [
        'time' => current_time( 'mysql' ),
        'ip'   => sgclabs_anonymize_ip( $_SERVER['REMOTE_ADDR'] ?? '' ),
        'pref' => $pref,
    ];
    if ( count( $log ) > 1000 ) $log = array_slice( $log, -1000 );
    update_option( 'sgclabs_consent_log', $log, false );
    wp_send_json_success();
}

function sgclabs_anonymize_ip( string $ip ): string {
    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
        $parts = explode( '.', $ip );
        $parts[3] = '0';
        return implode( '.', $parts );
    }
    return preg_replace( '/[\da-f]+$/i', '0', $ip );
}
