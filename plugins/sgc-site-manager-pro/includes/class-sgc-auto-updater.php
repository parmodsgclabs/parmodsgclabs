<?php
/**
 * SGC LABS® Auto Updater — Client
 *
 * Charo SGC plugins mein yeh ek file daalni hai.
 * Har plugin apna config deke register karta hai.
 *
 * INSTALL:
 *   1. Yeh file copy karo: plugin-folder/includes/class-sgc-auto-updater.php
 *   2. Plugin ke main PHP file mein include + register karo (neeche examples hain)
 *
 * ── MANAGER PRO (sgclabs-manager.php) mein add karo: ──────────────────────────
 *   sgclabs_include_file( SGCLABS_PATH . 'includes/class-sgc-auto-updater.php' );
 *   SGC_Auto_Updater::register([
 *       'plugin_id'      => 'sgc-site-manager-pro',
 *       'plugin_slug'    => 'sgc-site-manager-pro/sgclabs-manager.php',
 *       'current_version'=> SGCLABS_BASE_VERSION,
 *       'plugin_name'    => 'SGC LABS® Site Manager Pro',
 *   ]);
 *
 * ── INVOICE PRO (sgc-iip.php) mein add karo: ─────────────────────────────────
 *   require_once plugin_dir_path(__FILE__) . 'includes/class-sgc-auto-updater.php';
 *   SGC_Auto_Updater::register([
 *       'plugin_id'      => 'sgc-invoice-packlist-pro',
 *       'plugin_slug'    => 'sgc-invoice-packlist-pro/sgc-iip.php',
 *       'current_version'=> SGC_IIP_VERSION,
 *       'plugin_name'    => 'SGC Invoice & Packlist Pro',
 *   ]);
 *
 * ── SMART CRM PRO (sgc-crm.php) mein add karo: ───────────────────────────────
 *   require_once plugin_dir_path(__FILE__) . 'includes/class-sgc-auto-updater.php';
 *   SGC_Auto_Updater::register([
 *       'plugin_id'      => 'sgc-smart-crm-pro',
 *       'plugin_slug'    => 'sgc-smart-crm-pro/sgc-crm.php',
 *       'current_version'=> SGC_CRM_VERSION,
 *       'plugin_name'    => 'SGC Smart CRM Pro',
 *   ]);
 *
 * ── PRODUCT MANAGER (sgc-product-manager.php) mein add karo: ─────────────────
 *   require_once plugin_dir_path(__FILE__) . 'includes/class-sgc-auto-updater.php';
 *   SGC_Auto_Updater::register([
 *       'plugin_id'      => 'sgc-product-manager-pro',
 *       'plugin_slug'    => 'sgc-product-manager-pro/sgc-product-manager.php',
 *       'current_version'=> SGC_PM_VERSION,
 *       'plugin_name'    => 'SGC Product Manager Pro',
 *   ]);
 *
 * @package SGCLabs
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'SGC_Auto_Updater' ) ) return;

class SGC_Auto_Updater {

    // ── Update server config ──────────────────────────────────────────────────
    const SERVER_URL   = 'https://labsglass.com/wp-content/plugins/sgc-update-server/sgc-update-server.php';
    const SECRET_KEY   = 'sgclabs_secure_2026_xyz'; // server ke SGC_UPDATE_SECRET se match karna chahiye
    const CACHE_HOURS  = 12;  // kitne ghante baad dobara server se check kare

    /** @var array[] registered plugins list */
    private static array $registered = [];

    /**
     * Plugin register karo — main file mein ek baar call karo.
     *
     * @param array $config {
     *   plugin_id       string  e.g. 'sgc-site-manager-pro'
     *   plugin_slug     string  e.g. 'sgc-site-manager-pro/sgclabs-manager.php'
     *   current_version string  e.g. '7.4.7'
     *   plugin_name     string  e.g. 'SGC LABS® Site Manager Pro'
     * }
     */
    public static function register( array $config ): void {

        // Required fields check
        $required = [ 'plugin_id', 'plugin_slug', 'current_version', 'plugin_name' ];
        foreach ( $required as $field ) {
            if ( empty( $config[ $field ] ) ) {
                error_log( 'SGC_Auto_Updater: Missing required field: ' . $field );
                return;
            }
        }

        // Already registered? Skip.
        if ( isset( self::$registered[ $config['plugin_id'] ] ) ) return;

        self::$registered[ $config['plugin_id'] ] = $config;

        // Hook karo — sirf ek baar per plugin
        add_filter( 'pre_set_site_transient_update_plugins', [ __CLASS__, 'check_for_updates' ] );
        add_filter( 'plugins_api', [ __CLASS__, 'plugin_info' ], 10, 3 );
        add_filter( 'upgrader_process_complete', [ __CLASS__, 'after_update' ], 10, 2 );
    }


    // ── HOOK 1: WordPress Plugin Update Check ─────────────────────────────────
    // WP hafte mein ek baar yeh filter chalata hai — hum apni info inject karte hain

    public static function check_for_updates( $transient ) {

        if ( empty( $transient->checked ) ) return $transient;

        foreach ( self::$registered as $plugin_id => $config ) {

            $slug    = $config['plugin_slug'];
            $current = $config['current_version'];

            // Cache check — agar fresh data hai toh server hit mat karo
            $cache_key  = 'sgc_upd_' . md5( $plugin_id );
            $cached     = get_transient( $cache_key );

            if ( false === $cached ) {
                // Server se fresh data lo
                $remote = self::fetch_update_info( $plugin_id );
                if ( ! $remote ) continue;

                // Cache karo
                set_transient( $cache_key, $remote, self::CACHE_HOURS * HOUR_IN_SECONDS );
                $cached = $remote;
            }

            // Version compare — nayi version available hai?
            if ( isset( $cached['version'] ) && version_compare( $cached['version'], $current, '>' ) ) {

                $transient->response[ $slug ] = (object) [
                    'id'          => 'sgclabs/' . $plugin_id,
                    'slug'        => dirname( $slug ),
                    'plugin'      => $slug,
                    'new_version' => $cached['version'],
                    'url'         => $cached['homepage'] ?? self::SERVER_URL,
                    'package'     => $cached['download_url'] ?? '',
                    'icons'       => [],
                    'banners'     => [],
                    'tested'      => $cached['tested'] ?? '',
                    'requires_php'=> $cached['requires_php'] ?? '7.4',
                    'upgrade_notice' => $config['plugin_name'] . ' v' . $cached['version'] . ' available.',
                ];
            }
        }

        return $transient;
    }


    // ── HOOK 2: Plugin Info Popup (View Details link) ─────────────────────────
    // Jab user "View Details" click kare tab changelogs/description dikhao

    public static function plugin_info( $result, $action, $args ) {

        if ( $action !== 'plugin_information' ) return $result;
        if ( ! isset( $args->slug ) ) return $result;

        // Check karo koi registered plugin match karta hai
        foreach ( self::$registered as $plugin_id => $config ) {
            $folder = dirname( $config['plugin_slug'] );
            if ( $args->slug !== $folder ) continue;

            // Server se info lo
            $cache_key = 'sgc_upd_' . md5( $plugin_id );
            $remote    = get_transient( $cache_key );
            if ( ! $remote ) {
                $remote = self::fetch_update_info( $plugin_id );
            }

            if ( ! $remote ) return $result;

            return (object) [
                'name'          => $remote['name']    ?? $config['plugin_name'],
                'slug'          => $folder,
                'version'       => $remote['version'] ?? $config['current_version'],
                'author'        => $remote['author']  ?? 'SGC LABS®',
                'homepage'      => $remote['homepage'] ?? '',
                'requires'      => $remote['requires'] ?? '6.0',
                'tested'        => $remote['tested']   ?? '',
                'requires_php'  => $remote['requires_php'] ?? '7.4',
                'last_updated'  => $remote['last_updated'] ?? '',
                'download_link' => $remote['download_url'] ?? '',
                'sections'      => $remote['sections'] ?? [
                    'description' => $config['plugin_name'],
                    'changelog'   => 'See plugin changelog.',
                ],
            ];
        }

        return $result;
    }


    // ── HOOK 3: Update complete pe cache clear karo ───────────────────────────

    public static function after_update( $upgrader, $options ): void {
        if ( ! isset( $options['type'], $options['plugins'] ) ) return;
        if ( $options['type'] !== 'plugin' ) return;

        foreach ( self::$registered as $plugin_id => $config ) {
            if ( in_array( $config['plugin_slug'], (array) $options['plugins'], true ) ) {
                delete_transient( 'sgc_upd_' . md5( $plugin_id ) );
            }
        }
    }


    // ── Server se update info fetch karo ─────────────────────────────────────

    private static function fetch_update_info( string $plugin_id ): ?array {

        $url = add_query_arg( [
            'action' => 'check',
            'plugin' => $plugin_id,
            'key'    => self::SECRET_KEY,
        ], self::SERVER_URL );

        $response = wp_remote_get( $url, [
            'timeout'    => 10,
            'user-agent' => 'SGC-AutoUpdater/1.0 WordPress/' . get_bloginfo( 'version' ),
        ] );

        if ( is_wp_error( $response ) ) {
            error_log( 'SGC_Auto_Updater: Server error for ' . $plugin_id . ': ' . $response->get_error_message() );
            return null;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            error_log( 'SGC_Auto_Updater: HTTP ' . $code . ' for plugin: ' . $plugin_id );
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! is_array( $data ) || isset( $data['error'] ) ) {
            error_log( 'SGC_Auto_Updater: Invalid response for ' . $plugin_id );
            return null;
        }

        return $data;
    }


    // ── Manual cache clear karo (Health Monitor se call hota hai) ────────────

    public static function clear_cache( string $plugin_id = '' ): void {
        if ( $plugin_id ) {
            delete_transient( 'sgc_upd_' . md5( $plugin_id ) );
            return;
        }
        // Sabka cache clear karo
        foreach ( self::$registered as $id => $config ) {
            delete_transient( 'sgc_upd_' . md5( $id ) );
        }
    }


    // ── Admin mein "Check for Updates" button (optional) ─────────────────────
    // Manager Pro dashboard pe button dikhao

    public static function render_update_status_widget(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $rows = '';
        foreach ( self::$registered as $plugin_id => $config ) {
            $cache_key = 'sgc_upd_' . md5( $plugin_id );
            $remote    = get_transient( $cache_key );
            $current   = $config['current_version'];

            if ( $remote && isset( $remote['version'] ) ) {
                $newer   = version_compare( $remote['version'], $current, '>' );
                $status  = $newer
                    ? '<span style="color:#dc2626;font-weight:700">⬆ v' . esc_html( $remote['version'] ) . ' available</span>'
                    : '<span style="color:#16a34a">✓ Up to date</span>';
                $version = 'v' . esc_html( $current );
            } else {
                $status  = '<span style="color:#6b7280">—</span>';
                $version = 'v' . esc_html( $current );
            }

            $rows .= '<tr>
                <td style="padding:7px 10px;font-size:12px;color:var(--color-text-primary,#111)">' . esc_html( $config['plugin_name'] ) . '</td>
                <td style="padding:7px 10px;font-size:12px;color:#6b7280">' . $version . '</td>
                <td style="padding:7px 10px;font-size:12px">' . $status . '</td>
            </tr>';
        }

        $nonce = wp_create_nonce( 'sgc_updater_check' );
        ?>
        <div style="margin-top:12px">
            <table style="width:100%;border-collapse:collapse;font-size:12px">
                <thead>
                    <tr style="background:var(--color-background-secondary,#f8fafc)">
                        <th style="padding:6px 10px;text-align:left;font-size:11px;color:#6b7280;font-weight:600">Plugin</th>
                        <th style="padding:6px 10px;text-align:left;font-size:11px;color:#6b7280;font-weight:600">Current</th>
                        <th style="padding:6px 10px;text-align:left;font-size:11px;color:#6b7280;font-weight:600">Status</th>
                    </tr>
                </thead>
                <tbody><?php echo $rows; ?></tbody>
            </table>
            <button id="sgc-check-updates-btn"
                onclick="sgcCheckUpdates('<?php echo esc_js( $nonce ); ?>')"
                style="margin-top:10px;background:#1a1a2e;color:#fff;border:none;padding:7px 16px;border-radius:6px;font-size:12px;cursor:pointer">
                🔄 Check for Updates Now
            </button>
            <span id="sgc-update-check-msg" style="margin-left:10px;font-size:12px;color:#6b7280"></span>
        </div>

        <script>
        function sgcCheckUpdates(nonce){
            var btn = document.getElementById('sgc-check-updates-btn');
            var msg = document.getElementById('sgc-update-check-msg');
            btn.disabled = true;
            btn.textContent = '⏳ Checking…';
            msg.textContent = '';
            fetch(ajaxurl, {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'action=sgc_force_update_check&nonce='+nonce
            })
            .then(r=>r.json())
            .then(function(res){
                btn.disabled = false;
                btn.textContent = '🔄 Check for Updates Now';
                if(res.success){
                    msg.textContent = res.data.message;
                    setTimeout(function(){ location.reload(); }, 1500);
                } else {
                    msg.textContent = 'Error: '+(res.data||'unknown');
                }
            })
            .catch(function(){
                btn.disabled=false;
                btn.textContent='🔄 Check for Updates Now';
                msg.textContent='Network error.';
            });
        }
        </script>
        <?php
    }
}

// ── AJAX: Force update check (cache clear + reload) ──────────────────────────
add_action( 'wp_ajax_sgc_force_update_check', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied.' );
        return;
    }
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'sgc_updater_check' ) ) {
        wp_send_json_error( 'Security check failed.' );
        return;
    }

    // Sabka cache clear karo
    SGC_Auto_Updater::clear_cache();

    // WordPress ka update transient bhi delete karo (force fresh check)
    delete_site_transient( 'update_plugins' );

    wp_send_json_success( [ 'message' => 'Cache cleared. Refreshing…' ] );
} );
