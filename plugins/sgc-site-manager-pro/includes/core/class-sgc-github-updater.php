<?php
/**
 * SGC GitHub Auto-Updater
 *
 * Connects all 3 SGC plugins to their GitHub repositories.
 * Each plugin registers itself — WordPress plugin update screen
 * shows available updates automatically.
 *
 * Usage (in each plugin's main file):
 *   SGC_GitHub_Updater::register([
 *       'slug'    => 'sgclabs-manager-pro/sgclabs-manager.php',
 *       'repo'    => 'sgclabs/manager-pro',       // GitHub {owner}/{repo}
 *       'version' => '6.9.9',
 *       'token_option' => 'sgc_github_token',     // encrypted wp_option key
 *   ]);
 *
 * Release workflow (GitHub side):
 *   1. Bump version constant in main plugin file
 *   2. git tag v6.9.9 && git push origin v6.9.9
 *   3. GitHub Action creates ZIP and attaches to release
 *   4. WordPress shows update in Plugins screen automatically
 *
 * Powered by Claude (Anthropic)
 *
 * @package SGC\Core
 * @since   6.9.9
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'SGC_GitHub_Updater' ) ) :

class SGC_GitHub_Updater {

    const API_BASE    = 'https://api.github.com/repos/';
    const CACHE_TTL   = 43200; // 12 hours in seconds
    const TOKEN_OPTION = 'sgc_github_token'; // shared encrypted token

    /** @var array[] Registered plugins */
    private static array $plugins = [];

    private static bool $hooked = false;

    // ── Register a plugin ─────────────────────────────────────────────────

    /**
     * @param array $config {
     *   slug:         WordPress plugin slug  e.g. 'sgclabs-manager-pro/sgclabs-manager.php'
     *   repo:         GitHub repo            e.g. 'sgclabs/manager-pro'
     *   version:      Current version        e.g. '6.9.9'
     *   token_option: wp_option for token    (optional, defaults to shared token)
     * }
     */
    public static function register( array $config ): void {
        if ( empty( $config['slug'] ) || empty( $config['repo'] ) ) return;

        $config['token_option'] = $config['token_option'] ?? self::TOKEN_OPTION;
        self::$plugins[ $config['slug'] ] = $config;

        if ( ! self::$hooked ) {
            self::$hooked = true;
            add_filter( 'pre_set_site_transient_update_plugins', [ __CLASS__, 'check_updates'   ] );
            add_filter( 'plugins_api',                           [ __CLASS__, 'plugin_info'     ], 10, 3 );
            add_filter( 'upgrader_source_selection',             [ __CLASS__, 'fix_folder_name' ], 10, 4 );
            // Settings page for GitHub token
            add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        }
    }

    // ── Check for updates (hooked into WP transient) ──────────────────────

    public static function check_updates( $transient ) {
        if ( empty( $transient->checked ) ) return $transient;

        foreach ( self::$plugins as $slug => $cfg ) {
            $release = self::get_latest_release( $cfg['repo'], $cfg['token_option'] );
            if ( ! $release ) continue;

            $latest = ltrim( $release['tag_name'] ?? '', 'v' );
            if ( ! $latest ) continue;

            if ( version_compare( $latest, $cfg['version'], '>' ) ) {
                // Find the ZIP asset in release assets
                $zip_url = self::find_zip_url( $release, $cfg['token_option'] );
                if ( ! $zip_url ) continue;

                $transient->response[ $slug ] = (object) [
                    'slug'        => dirname( $slug ),
                    'plugin'      => $slug,
                    'new_version' => $latest,
                    'url'         => 'https://github.com/' . $cfg['repo'],
                    'package'     => $zip_url,
                    'icons'       => [],
                    'banners'     => [],
                    'requires'    => '5.8',
                    'tested'      => '6.9',
                    'requires_php'=> '7.4',
                ];
            }
        }

        return $transient;
    }

    // ── Plugin info popup (shown when "View details" clicked) ─────────────

    public static function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) return $result;

        foreach ( self::$plugins as $slug => $cfg ) {
            if ( dirname( $slug ) !== $args->slug ) continue;

            $release = self::get_latest_release( $cfg['repo'], $cfg['token_option'] );
            if ( ! $release ) return $result;

            $latest  = ltrim( $release['tag_name'] ?? '', 'v' );
            $body    = $release['body'] ?? 'See GitHub for changelog.';
            $zip_url = self::find_zip_url( $release, $cfg['token_option'] );

            return (object) [
                'name'          => $release['name'] ?? $slug,
                'slug'          => dirname( $slug ),
                'version'       => $latest,
                'author'        => '<a href="https://labsglass.com">SGC LABS®</a>',
                'homepage'      => 'https://github.com/' . $cfg['repo'],
                'requires'      => '5.8',
                'tested'        => '6.9',
                'requires_php'  => '7.4',
                'last_updated'  => $release['published_at'] ?? '',
                'download_link' => $zip_url,
                'sections'      => [
                    'changelog' => nl2br( esc_html( $body ) ),
                    'description' => 'SGC LABS® plugin. <a href="https://github.com/'
                        . esc_attr($cfg['repo']) . '">View on GitHub</a>.',
                ],
            ];
        }

        return $result;
    }

    // ── Fix folder name after install (GitHub ZIPs have repo-branch naming) ──

    public static function fix_folder_name( $source, $remote_source, $upgrader, $hook_extra ) {
        global $wp_filesystem;
        if ( empty( $hook_extra['plugin'] ) ) return $source;

        $slug_dir = dirname( $hook_extra['plugin'] ); // e.g. 'sgclabs-manager-pro'
        $new_source = trailingslashit( $remote_source ) . $slug_dir . '/';

        if ( $source !== $new_source ) {
            $wp_filesystem->move( $source, $new_source );
            return $new_source;
        }

        return $source;
    }

    // ── GitHub API: fetch latest release (cached 12h) ─────────────────────

    private static function get_latest_release( string $repo, string $token_option ): ?array {
        $cache_key = 'sgc_gh_release_' . md5( $repo );
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return $cached ?: null;

        $url  = self::API_BASE . $repo . '/releases/latest';
        $args = [
            'timeout'    => 10,
            'user-agent' => 'SGC-Plugin-Updater/1.0 (WordPress/' . get_bloginfo('version') . ')',
            'headers'    => [ 'Accept' => 'application/vnd.github.v3+json' ],
        ];

        $token = self::get_token( $token_option );
        if ( $token ) {
            $args['headers']['Authorization'] = 'Bearer ' . $token;
        }

        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            // Cache a null result for 1 hour to avoid hammering API on repeated failures
            set_transient( $cache_key, [], HOUR_IN_SECONDS );
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
            set_transient( $cache_key, [], HOUR_IN_SECONDS );
            return null;
        }

        set_transient( $cache_key, $data, self::CACHE_TTL );
        return $data;
    }

    // ── Find ZIP URL in release assets ────────────────────────────────────

    private static function find_zip_url( array $release, string $token_option ): string {
        // Prefer a named asset (plugin ZIP attached by GitHub Action)
        if ( ! empty( $release['assets'] ) ) {
            foreach ( $release['assets'] as $asset ) {
                if ( isset( $asset['content_type'] ) &&
                     strpos( $asset['content_type'], 'zip' ) !== false &&
                     ! empty( $asset['browser_download_url'] ) ) {
                    return self::maybe_authenticated_url(
                        $asset['browser_download_url'],
                        $token_option
                    );
                }
            }
        }

        // Fallback: GitHub source ZIP (works for public repos, no auth needed)
        return $release['zipball_url'] ?? '';
    }

    // ── For private repo assets, WP Upgrader needs a URL it can download ──
    // We store token in DB and use it as a query param workaround, since
    // WP HTTP API doesn't support Authorization headers during upgrades.

    private static function maybe_authenticated_url( string $url, string $token_option ): string {
        $token = self::get_token( $token_option );
        if ( ! $token ) return $url;
        // Append token as query param for private GitHub asset downloads
        return add_query_arg( 'access_token', $token, $url );
    }

    // ── Token storage (AES-256 encrypted) ────────────────────────────────

    public static function get_token( string $option = self::TOKEN_OPTION ): string {
        $encrypted = get_option( $option, '' );
        if ( ! $encrypted ) return '';
        return self::decrypt( $encrypted );
    }

    public static function save_token( string $token, string $option = self::TOKEN_OPTION ): bool {
        if ( empty( $token ) ) {
            delete_option( $option );
            return true;
        }
        return update_option( $option, self::encrypt( $token ), false );
    }

    private static function cipher_key(): string {
        $salt = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'sgc-fallback-key-please-set-auth-key';
        return substr( hash( 'sha256', $salt . 'sgc_github_token_v1', true ), 0, 32 );
    }

    private static function encrypt( string $plaintext ): string {
        $iv  = random_bytes( 16 );
        $enc = openssl_encrypt( $plaintext, 'aes-256-cbc', self::cipher_key(), OPENSSL_RAW_DATA, $iv );
        return base64_encode( $iv . $enc );
    }

    private static function decrypt( string $ciphertext ): string {
        $raw = base64_decode( $ciphertext );
        $iv  = substr( $raw, 0, 16 );
        $enc = substr( $raw, 16 );
        $dec = openssl_decrypt( $enc, 'aes-256-cbc', self::cipher_key(), OPENSSL_RAW_DATA, $iv );
        return $dec ?: '';
    }

    // ── Settings: GitHub token field in Manager Pro settings ─────────────

    public static function register_settings(): void {
        register_setting( 'sgclabs_github_settings', self::TOKEN_OPTION, [
            'sanitize_callback' => function( $v ) {
                $v = sanitize_text_field( wp_unslash( $v ?? '' ) );
                if ( $v ) {
                    self::save_token( $v );
                    return ''; // Never store plaintext — encrypt + return empty
                }
                return get_option( self::TOKEN_OPTION, '' ); // Keep existing encrypted
            },
        ] );
    }

    /**
     * Render the GitHub token settings field.
     * Call this from Manager Pro's Settings page.
     */
    public static function render_token_field(): void {
        $has_token = ! empty( get_option( self::TOKEN_OPTION, '' ) );
        ?>
        <tr>
          <th scope="row">GitHub Token</th>
          <td>
            <input type="password" name="<?php echo esc_attr( self::TOKEN_OPTION ); ?>"
                   value="" class="regular-text"
                   placeholder="<?php echo $has_token ? '••••••••••••••••••••' : 'ghp_xxxxxxxxxxxx'; ?>"
                   autocomplete="new-password">
            <p class="description">
                <?php if ( $has_token ): ?>
                    <span style="color:#3a7d44">✅ Token saved (encrypted).</span>
                    Required for private repo updates. Leave blank to keep existing.
                <?php else: ?>
                    GitHub Personal Access Token — needed for private repo auto-updates.
                    <a href="https://github.com/settings/tokens/new?scopes=repo&description=SGC+Plugin+Updater" target="_blank">Generate token</a>
                    (needs <code>repo</code> scope for private repos, or no scope for public).
                <?php endif; ?>
            </p>
          </td>
        </tr>
        <?php
    }

    // ── Force re-check (clears transient cache) ───────────────────────────

    public static function clear_cache(): void {
        foreach ( self::$plugins as $cfg ) {
            delete_transient( 'sgc_gh_release_' . md5( $cfg['repo'] ) );
        }
        delete_site_transient( 'update_plugins' );
    }
}

endif; // class_exists SGC_GitHub_Updater
