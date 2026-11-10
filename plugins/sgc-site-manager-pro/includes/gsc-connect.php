<?php
if (!defined('ABSPATH')) exit;

if (get_option('sgclabs_gsc_connect_active', '1') !== '1') return;

// ============================================================
// TOKEN ENCRYPTION HELPERS — wp_options mein plain text nahi
// ============================================================
function sgclabs_gsc_encrypt($value) {
    if (empty($value)) return '';
    $key  = substr(hash('sha256', AUTH_KEY . SECURE_AUTH_KEY), 0, 32);
    $iv   = substr(hash('sha256', LOGGED_IN_KEY), 0, 16);
    // Use base64 encoding with simple XOR if openssl unavailable
    if (function_exists('openssl_encrypt')) {
        $enc = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
        return $enc ? 'enc:' . $enc : $value;
    }
    // openssl not available — store as base64 (obfuscated, not encrypted)
    return 'b64:' . base64_encode($value);
}

function sgclabs_gsc_decrypt($value) {
    if (empty($value)) return '';
    if (strpos((string)$value, 'enc:') === 0 && function_exists('openssl_decrypt')) {
        $key = substr(hash('sha256', AUTH_KEY . SECURE_AUTH_KEY), 0, 32);
        $iv  = substr(hash('sha256', LOGGED_IN_KEY), 0, 16);
        $dec = openssl_decrypt(substr($value, 4), 'AES-256-CBC', $key, 0, $iv);
        return $dec !== false ? $dec : '';
    }
    // Fix 4: Legacy base64 fallback — only for old keys stored before AES encryption (pre-v5).
    // Validated: decoded value must look like a real key (non-empty, printable ASCII).
    // This fallback will be removed in v7.0 once all sites have re-saved their keys.
    if (strpos((string)$value, 'b64:') === 0) {
        $decoded = base64_decode(substr($value, 4), true); // strict=true rejects invalid base64
        if ($decoded !== false && mb_detect_encoding($decoded, 'ASCII', true) !== false) {
            return $decoded;
        }
        return ''; // Invalid base64 — return empty rather than raw garbage
    }
    return $value;
}

// ============================================================
// SGC LABS® — GOOGLE SEARCH CONSOLE CONNECT v6.6.3
// Real OAuth2 connection to GSC API
// Fetches: Queries, Pages, CTR, Impressions, Position
// ============================================================

// -----------------------------------------------------------------
// CONSTANTS & HELPERS
// -----------------------------------------------------------------
if (!defined('SGCLABS_GSC_API'))     define('SGCLABS_GSC_API',     'https://searchconsole.googleapis.com/webmasters/v3');
if (!defined('SGCLABS_OAUTH_TOKEN')) define('SGCLABS_OAUTH_TOKEN', 'https://oauth2.googleapis.com/token');
if (!defined('SGCLABS_OAUTH_AUTH'))  define('SGCLABS_OAUTH_AUTH',  'https://accounts.google.com/o/oauth2/v2/auth');
if (!defined('SGCLABS_OAUTH_SCOPE')) define('SGCLABS_OAUTH_SCOPE', 'https://www.googleapis.com/auth/webmasters.readonly');

function sgclabs_gsc_get_redirect_uri() {
    // FIX: Use admin_url with force HTTPS to match Google Console exactly
    // admin_url() respects WP_SITEURL but may return http:// — Google requires exact match
    $uri = admin_url('admin.php?page=sgclabs-site-tools-hub&tab=gsc&sgclabs_gsc_callback=1');
    // Force HTTPS — Google Console only accepts https:// redirect URIs
    $uri = set_url_scheme($uri, 'https');
    return $uri;
}
function sgclabs_gsc_is_connected() {
    return !empty(get_option('sgclabs_gsc_access_token')) && !empty(get_option('sgclabs_gsc_site_url'));
}
function sgclabs_gsc_get_credentials() {
    return [
        'client_id'     => get_option('sgclabs_gsc_client_id', ''),
        'client_secret' => sgclabs_gsc_decrypt(get_option('sgclabs_gsc_client_secret', '')), // FIX: decrypt
    ];
}

// -----------------------------------------------------------------
// OAUTH FLOW: Step 1 — Redirect to Google
// -----------------------------------------------------------------
add_action('admin_init', 'sgclabs_gsc_handle_oauth_init');
function sgclabs_gsc_handle_oauth_init() {
    if (!isset($_GET['sgclabs_gsc_start_oauth'])) return;
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    check_admin_referer('sgclabs_gsc_start_oauth');

    $creds = sgclabs_gsc_get_credentials();
    if (empty($creds['client_id'])) {
        wp_redirect(admin_url('admin.php?page=sgclabs-site-tools-hub&tab=gsc&error=no_client_id'));
        exit;
    }
    $state = wp_create_nonce('sgclabs_gsc_oauth_state');
    update_option('sgclabs_gsc_oauth_state', $state);

    $auth_url = add_query_arg([
        'client_id'     => $creds['client_id'],
        'redirect_uri'  => sgclabs_gsc_get_redirect_uri(),
        'response_type' => 'code',
        'scope'         => SGCLABS_OAUTH_SCOPE,
        'access_type'   => 'offline',
        'prompt'        => 'consent',
        'state'         => $state,
    ], SGCLABS_OAUTH_AUTH);

    wp_redirect($auth_url);
    exit;
}

// -----------------------------------------------------------------
// OAUTH FLOW: Step 2 — Google redirects back with code
// -----------------------------------------------------------------
add_action('admin_init', 'sgclabs_gsc_handle_oauth_callback');
function sgclabs_gsc_handle_oauth_callback() {
    if (!isset($_GET['sgclabs_gsc_callback']) || !isset($_GET['code'])) return;
    if (!current_user_can('manage_options')) wp_die('Unauthorized');

    // Verify state
    $saved_state = get_option('sgclabs_gsc_oauth_state', '');
    if (empty($_GET['state']) || !hash_equals($saved_state, sanitize_text_field($_GET['state']))) {
        wp_redirect(admin_url('admin.php?page=sgclabs-site-tools-hub&tab=gsc&error=state_mismatch'));
        exit;
    }

    $code  = sanitize_text_field($_GET['code']);
    $creds = sgclabs_gsc_get_credentials();

    // Exchange code for tokens
    $response = wp_remote_post(SGCLABS_OAUTH_TOKEN, [
        'timeout' => 20,
        'body'    => [
            'code'          => $code,
            'client_id'     => $creds['client_id'],
            'client_secret' => $creds['client_secret'],
            'redirect_uri'  => sgclabs_gsc_get_redirect_uri(),
            'grant_type'    => 'authorization_code',
        ],
    ]);

    if (is_wp_error($response)) {
        wp_redirect(admin_url('admin.php?page=sgclabs-site-tools-hub&tab=gsc&error=token_request_failed'));
        exit;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($body['access_token'])) {
        $err = urlencode($body['error'] ?? 'unknown');
        wp_redirect(admin_url("admin.php?page=sgclabs-site-tools-hub&tab=gsc&error=no_token&detail={$err}"));
        exit;
    }

    // Save tokens (encrypted with WP auth key)
    update_option('sgclabs_gsc_access_token',  sgclabs_gsc_encrypt($body['access_token'])); // FIX: encrypted
    update_option('sgclabs_gsc_token_expiry',  time() + intval($body['expires_in'] ?? 3600));
    if (!empty($body['refresh_token'])) {
        update_option('sgclabs_gsc_refresh_token', sgclabs_gsc_encrypt($body['refresh_token'])); // FIX: encrypted
    }
    delete_option('sgclabs_gsc_oauth_state');

    // Auto-detect site URL from GSC
    $sites = sgclabs_gsc_api_call('/sites', $body['access_token']);
    if (!empty($sites['siteEntry'])) {
        // Try to match current site
        $home = home_url('/');
        $matched = null;
        foreach ($sites['siteEntry'] as $site) {
            if (rtrim($site['siteUrl'], '/') === rtrim($home, '/') ||
                strpos((string)$home, $site['siteUrl']) !== false) {
                $matched = $site['siteUrl'];
                break;
            }
        }
        // Save first site if no match
        if (!$matched && !empty($sites['siteEntry'][0])) {
            $matched = $sites['siteEntry'][0]['siteUrl'];
        }
        if ($matched) update_option('sgclabs_gsc_site_url', $matched);
    }

    wp_redirect(admin_url('admin.php?page=sgclabs-site-tools-hub&tab=gsc&connected=1'));
    exit;
}

// -----------------------------------------------------------------
// TOKEN REFRESH — Auto-refresh when expired
// -----------------------------------------------------------------
function sgclabs_gsc_get_valid_token() {
    $expiry = intval(get_option('sgclabs_gsc_token_expiry', 0));
    $token  = sgclabs_gsc_decrypt(get_option('sgclabs_gsc_access_token', '')); // FIX: decrypt

    // Valid token — use it
    if ($token && time() < ($expiry - 120)) return $token;

    // Try refresh
    $refresh_token = sgclabs_gsc_decrypt(get_option('sgclabs_gsc_refresh_token', '')); // FIX: decrypt
    if (empty($refresh_token)) return false;

    $creds    = sgclabs_gsc_get_credentials();
    $response = wp_remote_post(SGCLABS_OAUTH_TOKEN, [
        'timeout' => 15,
        'body'    => [
            'client_id'     => $creds['client_id'],
            'client_secret' => $creds['client_secret'],
            'refresh_token' => $refresh_token,
            'grant_type'    => 'refresh_token',
        ],
    ]);

    if (is_wp_error($response)) return false;
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($body['access_token'])) return false;

    update_option('sgclabs_gsc_access_token', sgclabs_gsc_encrypt($body['access_token'])); // FIX: encrypted
    update_option('sgclabs_gsc_token_expiry',  time() + intval($body['expires_in'] ?? 3600));
    return $body['access_token'];
}

// -----------------------------------------------------------------
// API HELPER — Make GSC API requests
// -----------------------------------------------------------------
function sgclabs_gsc_api_call($endpoint, $token = null, $method = 'GET', $body = null) {
    if (!$token) $token = sgclabs_gsc_get_valid_token();
    if (!$token) return false;

    $args = [
        'timeout' => 20,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ],
        'method'  => $method,
    ];
    if ($body && $method === 'POST') {
        $args['body'] = wp_json_encode($body);
    }

    $url = strpos((string)$endpoint, 'http') === 0 ? $endpoint : SGCLABS_GSC_API . $endpoint;
    // FIX: SSRF guard — only allow requests to Google APIs
    $allowed_hosts = ['searchconsole.googleapis.com', 'oauth2.googleapis.com', 'accounts.google.com'];
    $parsed_host   = parse_url($url, PHP_URL_HOST);
    if (!in_array($parsed_host, $allowed_hosts, true)) {
        return false; // Reject non-Google endpoints
    }
    $response = wp_remote_request($url, $args);

    if (is_wp_error($response)) return false;
    return json_decode(wp_remote_retrieve_body($response), true);
}

// -----------------------------------------------------------------
// FETCH TOP QUERIES — 90 days, top 500 queries
// -----------------------------------------------------------------
function sgclabs_gsc_fetch_queries($days = 90, $limit = 500) {
    $site_url = get_option('sgclabs_gsc_site_url', '');
    if (!$site_url) return false;

    $end_date   = date('Y-m-d');
    $start_date = date('Y-m-d', strtotime("-{$days} days"));

    $encoded_site = urlencode($site_url);
    $data = sgclabs_gsc_api_call(
        "/sites/{$encoded_site}/searchAnalytics/query",
        null,
        'POST',
        [
            'startDate'  => $start_date,
            'endDate'    => $end_date,
            'dimensions' => ['query'],
            'rowLimit'   => $limit,
            'orderBy'    => [['fieldName' => 'clicks', 'sortOrder' => 'DESCENDING']],
        ]
    );

    if (empty($data['rows'])) return false;
    return $data['rows'];
}

// -----------------------------------------------------------------
// FETCH TOP PAGES — what pages get most traffic
// -----------------------------------------------------------------
function sgclabs_gsc_fetch_pages($days = 90, $limit = 100) {
    $site_url = get_option('sgclabs_gsc_site_url', '');
    if (!$site_url) return false;

    $encoded_site = urlencode($site_url);
    $data = sgclabs_gsc_api_call(
        "/sites/{$encoded_site}/searchAnalytics/query",
        null,
        'POST',
        [
            'startDate'  => date('Y-m-d', strtotime("-{$days} days")),
            'endDate'    => date('Y-m-d'),
            'dimensions' => ['page'],
            'rowLimit'   => $limit,
            'orderBy'    => [['fieldName' => 'impressions', 'sortOrder' => 'DESCENDING']],
        ]
    );

    if (empty($data['rows'])) return false;
    return $data['rows'];
}

// -----------------------------------------------------------------
// FETCH QUERY+PAGE combined — keyword to page mapping
// -----------------------------------------------------------------
function sgclabs_gsc_fetch_query_page_map($days = 90, $limit = 500) {
    $site_url = get_option('sgclabs_gsc_site_url', '');
    if (!$site_url) return false;

    $encoded_site = urlencode($site_url);
    $data = sgclabs_gsc_api_call(
        "/sites/{$encoded_site}/searchAnalytics/query",
        null,
        'POST',
        [
            'startDate'  => date('Y-m-d', strtotime("-{$days} days")),
            'endDate'    => date('Y-m-d'),
            'dimensions' => ['query', 'page'],
            'rowLimit'   => $limit,
            'orderBy'    => [['fieldName' => 'clicks', 'sortOrder' => 'DESCENDING']],
        ]
    );

    return $data['rows'] ?? [];
}

// -----------------------------------------------------------------
// AJAX: Sync GSC data to DB
// -----------------------------------------------------------------
add_action('wp_ajax_sgclabs_gsc_sync', 'sgclabs_gsc_sync_ajax');
function sgclabs_gsc_sync_ajax() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');

    global $wpdb;
    $rows = sgclabs_gsc_fetch_queries(90, 500);
    if (!$rows) { wp_send_json_error('GSC API error — check connection.'); return; }

    $count = 0;
    foreach ($rows as $row) {
        $kw = sanitize_text_field($row['keys'][0] ?? '');
        if (!$kw || strlen($kw) < 3) continue;
        $clicks      = intval($row['clicks'] ?? 0);
        $impressions = intval($row['impressions'] ?? 0);
        $ctr         = round(floatval($row['ctr'] ?? 0) * 100, 2);
        $position    = round(floatval($row['position'] ?? 0), 1);

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}sgclabs_kw_gsc WHERE keyword=%s", $kw
        ));
        if ($existing) {
            $wpdb->update("{$wpdb->prefix}sgclabs_kw_gsc",
                ['clicks'=>$clicks,'impressions'=>$impressions,'ctr'=>$ctr,'position'=>$position,'synced_at'=>current_time('mysql')],
                ['id'=>intval($existing)]
            );
        } else {
            $wpdb->insert("{$wpdb->prefix}sgclabs_kw_gsc", [
                'keyword'     => $kw,
                'clicks'      => $clicks,
                'impressions' => $impressions,
                'ctr'         => $ctr,
                'position'    => $position,
                'active'      => 1,
                'synced_at'   => current_time('mysql'),
            ]);
            $count++;
        }
    }

    // Clear SEO cache so new keywords apply
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_sgclabs_seo_cache','_sgclabs_seo_cache_time')");
    update_option('sgclabs_gsc_last_sync', current_time('mysql'));
    update_option('sgclabs_gsc_total_kw',  $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgclabs_kw_gsc"));

    wp_send_json_success([
        'new'     => $count,
        'total'   => count($rows),
        'message' => count($rows) . " queries synced! {$count} new keywords added.",
        'synced'  => current_time('mysql'),
    ]);
}

// AJAX: Get GSC sites list
add_action('wp_ajax_sgclabs_gsc_sites', 'sgclabs_gsc_sites_ajax');
function sgclabs_gsc_sites_ajax() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    $sites = sgclabs_gsc_api_call('/sites');
    if (!$sites) { wp_send_json_error('Cannot fetch sites'); return; }
    wp_send_json_success($sites['siteEntry'] ?? []);
}

// AJAX: Set active site
add_action('wp_ajax_sgclabs_gsc_set_site', 'sgclabs_gsc_set_site_ajax');
function sgclabs_gsc_set_site_ajax() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    $site = esc_url_raw($_POST['site_url'] ?? '');
    if (!$site) { wp_send_json_error('Invalid URL'); return; }
    // FIX: Validate scheme (http/https only) and no private IPs
    $scheme = parse_url($site, PHP_URL_SCHEME);
    $host   = parse_url($site, PHP_URL_HOST);
    if (!in_array($scheme, ['http','https'], true)) { wp_send_json_error('Invalid URL scheme'); return; }
    $ip = gethostbyname($host);
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        wp_send_json_error('Private/internal URLs not allowed'); return;
    }
    update_option('sgclabs_gsc_site_url', $site);
    wp_send_json_success();
}

// AJAX: Disconnect GSC
add_action('wp_ajax_sgclabs_gsc_disconnect', 'sgclabs_gsc_disconnect_ajax');
function sgclabs_gsc_disconnect_ajax() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    foreach (['sgclabs_gsc_access_token','sgclabs_gsc_refresh_token','sgclabs_gsc_token_expiry',
              'sgclabs_gsc_site_url','sgclabs_gsc_last_sync','sgclabs_gsc_oauth_state'] as $opt) {
        delete_option($opt);
    }
    wp_send_json_success();
}

// Auto-sync once per day via WP Cron
add_action('sgclabs_gsc_daily_sync', 'sgclabs_gsc_cron_sync');
function sgclabs_gsc_cron_sync() {
    if (!sgclabs_gsc_is_connected()) return;
    global $wpdb;
    // v7.4.7: defensive table check — keyword-manager creates it but guard just in case
    $kw_table = $wpdb->prefix . 'sgclabs_kw_gsc';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$kw_table}'" ) !== $kw_table ) {
        error_log('SGC GSC Cron: sgclabs_kw_gsc table missing — skipping sync');
        return;
    }
    $rows = sgclabs_gsc_fetch_queries(90, 500);
    if (!$rows) return;
    foreach ($rows as $row) {
        $kw = sanitize_text_field($row['keys'][0] ?? '');
        if (!$kw) continue;
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sgclabs_kw_gsc WHERE keyword=%s", $kw));
        $data = ['clicks'=>intval($row['clicks']),'impressions'=>intval($row['impressions']),'ctr'=>round(floatval($row['ctr'])*100,2),'position'=>round(floatval($row['position']),1),'synced_at'=>current_time('mysql')];
        if ($existing) $wpdb->update("{$wpdb->prefix}sgclabs_kw_gsc", $data, ['id'=>intval($existing)]);
        else $wpdb->insert("{$wpdb->prefix}sgclabs_kw_gsc", array_merge(['keyword'=>$kw,'active'=>1],$data));
    }
    update_option('sgclabs_gsc_last_sync', current_time('mysql'));
}
if (!wp_next_scheduled('sgclabs_gsc_daily_sync')) {
    wp_schedule_event(time(), 'daily', 'sgclabs_gsc_daily_sync');
}

// -----------------------------------------------------------------
// ADMIN PAGE
// -----------------------------------------------------------------
function sgclabs_gsc_connect_page() {
    $is_connected = sgclabs_gsc_is_connected();
    $creds        = sgclabs_gsc_get_credentials();
    $site_url     = get_option('sgclabs_gsc_site_url', '');
    $last_sync    = get_option('sgclabs_gsc_last_sync', '');
    $total_kw     = get_option('sgclabs_gsc_total_kw', 0);
    $connected    = isset($_GET['connected']);
    $error        = sanitize_text_field($_GET['error'] ?? '');

    // Save credentials
    if (isset($_POST['sgclabs_save_gsc_creds']) && wp_verify_nonce($_POST['_wpnonce'], 'sgclabs_gsc_creds')) {
        update_option('sgclabs_gsc_client_id',     sanitize_text_field($_POST['gsc_client_id']));
        update_option('sgclabs_gsc_client_secret', sgclabs_gsc_encrypt(sanitize_text_field($_POST['gsc_client_secret']))); // FIX: encrypted
        $creds = sgclabs_gsc_get_credentials();
        echo '<div class="notice notice-success"><p>✅ Credentials saved!</p></div>';
    }

    $error_msgs = [
        'no_client_id'       => 'Client ID missing. Please enter your Google OAuth credentials first.',
        'state_mismatch'     => 'Security check failed. Please try again.',
        'token_request_failed' => 'Could not connect to Google. Check your credentials.',
        'no_token'           => 'Google did not return an access token. Check Client ID/Secret and redirect URI.',
    ];
    ?>
    <div class="wrap sgclabs-wrap">
<?php sgclabs_module_control_bar(["module_id"=>"gsc-connect","module_name"=>"Google Search Console","module_icon"=>"🔍","option_key"=>"sgclabs_gsc_connect_active","default"=>"1","description"=>"Connect GSC for keyword sync, rank tracking, index status"]); ?>

        <?php if ($connected): ?>
            <div class="notice notice-success"><p>🎉 <strong>Google Search Console connected successfully!</strong> Click "Sync Now" to import your search data.</p></div>
        <?php endif; ?>
        <?php if ($error && isset($error_msgs[$error])): ?>
            <div class="notice notice-error"><p>❌ <?php echo esc_html($error_msgs[$error]); ?></p></div>
        <?php endif; ?>

        <div class="sgclabs-header">
            <h1>🔗 Google Search Console — Live Connect</h1>
            <p>Real OAuth2 connection — GSC se live search queries, CTR, impressions, positions fetch karo aur automatically plugin mein use karo.</p>
        </div>

        <!-- STATUS CARD -->
        <div class="sgclabs-section" style="background:<?php echo $is_connected ? 'linear-gradient(135deg,#f0fff4,#e8f8f0)' : 'linear-gradient(135deg,#fff8f0,#fff3e0)'; ?>;border-left:4px solid <?php echo $is_connected ? '#27ae60' : '#e67e22'; ?>;">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:15px;">
                <div>
                    <h2 style="margin:0 0 6px;"><?php echo $is_connected ? '✅ Connected' : '⚡ Not Connected'; ?> <!-- safe: boolean ternary --></h2>
                    <?php if ($is_connected): ?>
                        <p style="margin:0;color:#555;">Site: <strong><?php echo esc_html($site_url); ?></strong></p>
                        <?php if ($last_sync): ?>
                            <p style="margin:4px 0 0;color:#888;font-size:13px;">Last sync: <?php echo esc_html(human_time_diff(strtotime($last_sync)) . ' ago'); ?> &nbsp;|&nbsp; <?php echo intval($total_kw); ?> keywords in DB</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p style="margin:0;color:#888;">Follow the steps below to connect your Google Search Console account.</p>
                    <?php endif; ?>
                </div>
                <?php if ($is_connected): ?>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <div id="sgc-pnav-gsc" style="margin-bottom:10px;"></div>
                    <button onclick="disconnectGSC()" class="button button-large" style="color:#e74c3c;border-color:#e74c3c;">
                        🔌 Disconnect
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <div id="sync-status" style="margin-top:12px;display:none;"></div>
        </div>

        <?php if ($is_connected): ?>
        <!-- SITE SELECTOR -->
        <div class="sgclabs-section">
            <h2>🌐 Active Site</h2>
            <p>Agar multiple sites GSC mein hain toh yahan change karo:</p>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <select id="gsc-site-select" style="min-width:350px;padding:8px;">
                    <option value="">⏳ Loading sites...</option>
                </select>
                <button onclick="setSite()" class="button button-primary">✅ Set Active Site</button>
            </div>
        </div>

        <!-- STATS PANEL -->
        <div class="sgclabs-section">
            <h2>📊 What Gets Synced to Plugin</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:15px;">
                <?php foreach([
                    ['🔑','Search Queries','Top 500 queries (90 days) — clicks, impressions, CTR, position','#3498db'],
                    ['📈','Auto Keywords','Queries automatically added to Keyword Manager → injected in meta tags','#27ae60'],
                    ['🎯','Position Tracking','See which keywords rank 1-10 (top page) vs need improvement','#e67e22'],
                    ['🔄','Daily Auto-Sync','WP Cron se har roz automatically sync hota hai','#8e44ad'],
                ] as [$icon,$title,$desc,$color]): ?>
                <div style="background:#fff;border:1px solid #e0e0e0;border-left:4px solid <?php echo esc_attr($color); ?>;border-radius:8px;padding:15px;">
                    <div style="font-size:24px;margin-bottom:8px;"><?php echo esc_html($icon); ?></div>
                    <strong style="color:#1a1a2e;"><?php echo esc_html($title); ?></strong>
                    <p style="margin:6px 0 0;font-size:12px;color:#666;"><?php echo esc_html($desc); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php else: ?>

        <!-- NOTE: Google Cloud Console Setup removed per user request -->

        <!-- STEP 2: Connect -->
        <div class="sgclabs-section" style="<?php echo empty($creds['client_id']) ? 'opacity:0.5;pointer-events:none;' : ''; ?>">
            <h2>🔗 Step 2 — Connect Google Account</h2>
            <p>Credentials save karne ke baad yahan click karo Google account se connect karne ke liye:</p>

            <?php /* FIX: Show exact redirect URI before connect so user can verify Google Console */ ?>
            <?php $current_redirect_uri = sgclabs_gsc_get_redirect_uri(); ?>
            <div style="background:#fff8e1;border:1px solid #ffe082;padding:10px 14px;border-radius:6px;margin-bottom:14px;font-size:12px;">
                <strong>🔗 Google Console mein yahi Redirect URI honi chahiye:</strong><br>
                <code id="sgc-cur-uri" style="word-break:break-all;font-size:12.5px;background:#fffde7;padding:3px 6px;border-radius:3px;"><?php echo esc_html($current_redirect_uri); ?></code>
                <button onclick="navigator.clipboard.writeText(document.getElementById('sgc-cur-uri').textContent.trim()).then(()=>{this.textContent='✅ Copied!';setTimeout(()=>this.textContent='📋 Copy',2000)})"
                        class="button" style="margin-left:8px;padding:1px 8px;font-size:11px;">📋 Copy</button><br>
                <span style="color:#b45309;margin-top:4px;display:block;">⚠️ Error 400 aa raha hai? Google Cloud Console → Credentials → OAuth Client → <strong>Authorized redirect URIs</strong> mein exactly yahi URL add karo.</span>
            </div>

            <?php if (!empty($creds['client_id'])): ?>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=sgclabs-site-tools-hub&tab=gsc&sgclabs_gsc_start_oauth=1'), 'sgclabs_gsc_start_oauth'); ?>"
                   class="button button-primary button-hero"
                   style="font-size:15px;padding:12px 30px;height:auto;display:inline-block;">
                    🚀 Connect with Google
                </a>
                <p style="margin-top:10px;color:#888;font-size:12px;">
                    ℹ️ Google "This app isn't verified" warning aa sakti hai — "Advanced" → "Go to app (unsafe)" click karo. Ye aapka apna Google Cloud project hai, safe hai.
                </p>
            <?php else: ?>
                <p style="color:#e67e22;">⚠️ Pehle Step 1 mein credentials save karo.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div><!-- .wrap -->

    <script>
    document.addEventListener('DOMContentLoaded', function() {
    if (typeof SgcProgressNav === 'undefined') return;
    // ── GSC Connect Progress Nav ─────────────────────────────────
    const gscNav = new SgcProgressNav('sgc-pnav-gsc', {
        label: 'Google Search Console Sync',
        icon: '🔗',
        showSpeed: false,
        counters: [{id:'sgc-gsc-queries', label:'Queries'}, {id:'sgc-gsc-pages', label:'Pages'}],
        onPlay:  function(nav) { runGscSync(nav); },
        onStop:  function(nav) { },
        onReset: function(nav) { }
    });

    function runGscSync(nav) {
        nav.status('<span class="sgc-pulse"></span> Syncing with Google Search Console…');
        const nonce = '<?php echo esc_js( wp_create_nonce( "sgclabs_nonce" ) ); ?>';
        jQuery.post(ajaxurl, { action: 'sgclabs_gsc_sync', nonce: nonce }, function(res) {
            if (!res.success) { nav.error(res.data||'GSC sync failed'); return; }
            const d = res.data;
            nav.setPercent(100);
            nav.setCounter('sgc-gsc-queries', d.queries || '—');
            nav.setCounter('sgc-gsc-pages',   d.pages   || '—');
            nav.done('GSC sync complete! ' + (d.queries||0) + ' queries imported.');
        }).fail(function(){ nav.error('Network error — check GSC connection.'); });
    }
    }); // end DOMContentLoaded

    const nonce = '<?php echo esc_js(wp_create_nonce('sgclabs_nonce')); ?>';

    <?php if ($is_connected): ?>
    // Load sites on page load
    jQuery(function() { loadSites(); });

    function loadSites() {
        jQuery.post(ajaxurl, { action:'sgclabs_gsc_sites', nonce }, function(res) {
            if (!res.success) return;
            const sel = document.getElementById('gsc-site-select');
            const current = '<?php echo esc_js($site_url); ?>';
            sel.innerHTML = res.data.map(s =>
                `<option value="${s.siteUrl}" ${s.siteUrl===current?'selected':''}>${s.siteUrl} (${s.permissionLevel})</option>`
            ).join('');
        });
    }

    function setSite() {
        const url = document.getElementById('gsc-site-select').value;
        if (!url) return;
        jQuery.post(ajaxurl, { action:'sgclabs_gsc_set_site', nonce, site_url:url }, function(res) {
            if (res.success) { alert('✅ Active site updated!'); location.reload(); }
        });
    }

    function syncGSC() {
        const btn = document.getElementById('sync-btn');
        const status = document.getElementById('sync-status');
        btn.disabled = true;
        btn.textContent = '⏳ Syncing...';
        status.style.display = 'block';
        status.innerHTML = '<div style="background:#f0f4ff;border:1px solid #c0d0ff;border-radius:6px;padding:12px;">⏳ Fetching data from Google Search Console API...</div>';

        jQuery.post(ajaxurl, { action:'sgclabs_gsc_sync', nonce }, function(res) {
            btn.disabled = false;
            btn.textContent = '🔄 Sync Now';
            if (res.success) {
                status.innerHTML = `<div style="background:#f0fff4;border:1px solid #a3d9b8;border-radius:6px;padding:12px;">
                    ✅ <strong>${res.data.message}</strong><br>
                    <small>Synced at: ${res.data.synced}</small>
                </div>`;
                setTimeout(() => location.reload(), 2000);
            } else {
                status.innerHTML = `<div style="background:#fff0f0;border:1px solid #ffb3b3;border-radius:6px;padding:12px;">❌ ${res.data || 'Sync failed'}</div>`;
            }
        });
    }

    function disconnectGSC() {
        if (!confirm('Google Search Console se disconnect karna chahte ho? Ye saved tokens delete kar dega.')) return;
        jQuery.post(ajaxurl, { action:'sgclabs_gsc_disconnect', nonce }, function(res) {
            if (res.success) location.reload();
        });
    }
    <?php endif; ?>

    function copyRedirectUri() {
        const uri = '<?php echo esc_js(sgclabs_gsc_get_redirect_uri()); ?>';
        navigator.clipboard.writeText(uri).then(() => alert('✅ Redirect URI copied!'));
    }
    </script>
    <?php
}
