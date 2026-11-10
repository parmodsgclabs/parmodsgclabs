<?php
/**
 * SGC LABS® Plugin Health Monitor
 * Auto-scans all SGC plugins after WP/WooCommerce updates
 *
 * @package    SGCLabsManagerPro
 * @version    1.0.0
 * @author     SGC LABS®
 *
 * INSTALL: Place this file in sgc-site-manager-pro/includes/
 * REGISTER: Add this line in sgclabs-manager.php (with other includes):
 *   sgclabs_include_file( SGCLABS_PATH . 'includes/plugin-health-monitor.php' );
 */

defined( 'ABSPATH' ) || exit;

// ── Constants ─────────────────────────────────────────────────────────────────
define( 'SGC_HEALTH_VERSION',    '1.0.0' );
define( 'SGC_HEALTH_OPTION',     'sgc_health_last_scan' );       // stores last scan results
define( 'SGC_HEALTH_STAMP',      'sgc_health_last_scan_time' );  // stores last scan timestamp
define( 'SGC_HEALTH_CRON_HOOK',  'sgc_health_weekly_scan' );

// ── All 4 SGC plugins being monitored ─────────────────────────────────────────
$GLOBALS['sgc_monitored_plugins'] = [
    'manager-pro' => [
        'name'         => 'SGC LABS® Manager Pro',
        'slug'         => 'sgc-site-manager-pro/sgclabs-manager.php',
        'version_const'=> 'SGCLABS_BASE_VERSION',
        'path_const'   => 'SGCLABS_PATH',
        'key_files'    => [
            'sgclabs-manager.php',
            'includes/get-a-quote.php',
            'includes/b2b-quote-shortcodes.php',
            'includes/ai-provider.php',
            'includes/ai-content-engine.php',
            'includes/core/loader.php',
        ],
        'key_tables'   => [ 'sgclabs_inquiries' ],
        'key_options'  => [ 'sgclabs_version' ],
    ],
    'invoice-pro' => [
        'name'         => 'SGC Invoice · Packlist Pro',
        'slug'         => 'sgc-invoice-packlist-pro/sgc-iip.php',
        'version_const'=> 'SGC_IIP_VERSION',
        'path_const'   => 'SGC_IIP_PATH',
        'key_files'    => [
            'sgc-iip.php',
            'includes/class-hooks.php',
            'includes/class-proforma.php',
            'includes/class-db.php',
            'admin/views/tab-proforma.php',
            'admin/views/tab-settings.php',
        ],
        'key_tables'   => [ 'sgc_iip_inquiries' ],
        'key_options'  => [ 'sgc_iip_company_name', 'sgc_iip_usd_rate' ],
    ],
    'crm-pro' => [
        'name'         => 'SGC Smart CRM Pro',
        'slug'         => 'sgc-smart-crm/sgc-smart-crm.php',
        'version_const'=> 'SGC_CRM_VERSION',
        'path_const'   => 'SGC_CRM_PATH',
        'key_files'    => [
            'sgc-smart-crm.php',
            'includes/class-crm-contacts.php',
            'includes/class-crm-campaigns.php',
            'includes/class-crm-db.php',
        ],
        'key_tables'   => [ 'sgc_crm_contacts', 'sgc_crm_campaigns' ],
        'key_options'  => [ 'sgc_crm_ai_provider' ],
    ],
    'product-manager' => [
        'name'         => 'SGC Product Manager Pro',
        'slug'         => 'sgc-product-manager-pro/sgc-product-manager.php',
        'version_const'=> 'SGC_PM_VERSION',
        'path_const'   => 'SGC_PM_PATH',
        'key_files'    => [
            'sgc-product-manager.php',
        ],
        'key_tables'   => [],
        'key_options'  => [],
    ],
];

// ── WooCommerce hooks used across plugins — checked for existence ──────────────
$GLOBALS['sgc_wc_hooks_used'] = [
    'woocommerce_before_add_to_cart_button',
    'woocommerce_after_add_to_cart_button',
    'woocommerce_product_query',
    'woocommerce_checkout_fields',
    'woocommerce_thankyou',
    'woocommerce_before_cart',
    'woocommerce_cart_calculate_fees',
    'woocommerce_email_order_details',
    'wc_ajax_get_refreshed_fragments',
];


// ╔══════════════════════════════════════════════════════════════════════════════╗
// ║  SECTION 1 — SCAN ENGINE                                                   ║
// ╚══════════════════════════════════════════════════════════════════════════════╝

/**
 * Master scan function — runs all checks, stores results.
 */
function sgc_health_run_scan() {
    $results = [
        'scan_time'  => current_time( 'mysql' ),
        'wp_version' => get_bloginfo( 'version' ),
        'wc_version' => defined( 'WC_VERSION' ) ? WC_VERSION : 'not-active',
        'php_version'=> PHP_VERSION,
        'issues'     => [],
        'passed'     => [],
        'plugins'    => [],
    ];

    // 1. PHP Version check
    $results = sgc_health_check_php( $results );

    // 2. WordPress version check
    $results = sgc_health_check_wp( $results );

    // 3. WooCommerce version + hooks check
    $results = sgc_health_check_woocommerce( $results );

    // 4. Per-plugin checks (files, tables, options)
    foreach ( $GLOBALS['sgc_monitored_plugins'] as $key => $plugin ) {
        $results = sgc_health_check_plugin( $results, $key, $plugin );
    }

    // 5. Function conflict check
    $results = sgc_health_check_function_conflicts( $results );

    // Save results
    update_option( SGC_HEALTH_OPTION, $results, false );
    update_option( SGC_HEALTH_STAMP,  time(),   false );

    return $results;
}

// ── Check 1: PHP Version ──────────────────────────────────────────────────────
function sgc_health_check_php( $results ) {
    $php = PHP_VERSION;

    if ( version_compare( $php, '8.0', '<' ) ) {
        $results['issues'][] = [
            'level'   => 'warning',
            'category'=> 'PHP',
            'message' => "PHP {$php} detected. Upgrade to PHP 8.1+ recommended for security & performance.",
            'action'  => 'Contact Bluehost support to upgrade PHP version.',
        ];
    } elseif ( version_compare( $php, '8.3', '>=' ) ) {
        $results['issues'][] = [
            'level'   => 'info',
            'category'=> 'PHP',
            'message' => "PHP {$php} is very new. Some dependencies may not yet be fully tested on this version.",
            'action'  => 'Monitor error logs after any plugin updates.',
        ];
    } else {
        $results['passed'][] = "PHP {$php} — Compatible ✓";
    }

    // Check deprecated functions in use
    if ( version_compare( $php, '8.0', '>=' ) ) {
        $sgc_paths = sgc_health_get_plugin_paths();
        foreach ( $sgc_paths as $path ) {
            if ( ! is_dir( $path ) ) continue;
            $files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path ) );
            foreach ( $files as $file ) {
                if ( $file->getExtension() !== 'php' ) continue;
                // Skip this file itself (scanner mentions create_function in comments)
                if ( basename( $file->getPathname() ) === 'plugin-health-monitor.php' ) continue;
                $code = @file_get_contents( $file->getPathname() );
                // Match actual function call: create_function( or create_function(
                if ( $code && preg_match( '/\bcreate_function\s*\(/', $code ) ) {
                    $results['issues'][] = [
                        'level'   => 'critical',
                        'category'=> 'PHP',
                        'message' => "Deprecated create_function() found in: " . basename( $file->getPathname() ),
                        'action'  => 'Replace with anonymous function. Fatal error on PHP 8.0+.',
                    ];
                }
            }
        }
    }

    return $results;
}

// ── Check 2: WordPress Version ───────────────────────────────────────────────
function sgc_health_check_wp( $results ) {
    $wp = get_bloginfo( 'version' );

    if ( version_compare( $wp, '6.0', '<' ) ) {
        $results['issues'][] = [
            'level'   => 'critical',
            'category'=> 'WordPress',
            'message' => "WordPress {$wp} is outdated. SGC plugins require WP 6.0+.",
            'action'  => 'Update WordPress immediately from Dashboard → Updates.',
        ];
    } elseif ( version_compare( $wp, '6.4', '<' ) ) {
        $results['issues'][] = [
            'level'   => 'warning',
            'category'=> 'WordPress',
            'message' => "WordPress {$wp} detected. Recommend updating to WP 6.4+ for best compatibility.",
            'action'  => 'Update WordPress from Dashboard → Updates.',
        ];
    } else {
        $results['passed'][] = "WordPress {$wp} — Compatible ✓";
    }

    return $results;
}

// ── Check 3: WooCommerce Version + Hooks ─────────────────────────────────────
function sgc_health_check_woocommerce( $results ) {
    if ( ! defined( 'WC_VERSION' ) ) {
        $results['issues'][] = [
            'level'   => 'critical',
            'category'=> 'WooCommerce',
            'message' => 'WooCommerce is NOT active. Several SGC features will not work.',
            'action'  => 'Activate WooCommerce from Plugins page.',
        ];
        return $results;
    }

    $wc = WC_VERSION;
    if ( version_compare( $wc, '7.0', '<' ) ) {
        $results['issues'][] = [
            'level'   => 'warning',
            'category'=> 'WooCommerce',
            'message' => "WooCommerce {$wc} is outdated. SGC plugins tested on WC 7.0+.",
            'action'  => 'Update WooCommerce from Dashboard → Updates.',
        ];
    } else {
        $results['passed'][] = "WooCommerce {$wc} — Compatible ✓";
    }

    // Check hooks still exist in current WC version
    $removed_hooks = [];
    foreach ( $GLOBALS['sgc_wc_hooks_used'] as $hook ) {
        // If hook has no callbacks registered globally, it may be removed
        // We check via has_action (checks if WC itself uses it)
        if ( ! has_action( $hook ) && ! has_filter( $hook ) ) {
            // Only flag as removed if WC is active but hook is completely absent
            // (some hooks only fire conditionally, so this is info-level only)
            $removed_hooks[] = $hook;
        }
    }

    if ( count( $removed_hooks ) > 3 ) {
        // More than 3 missing hooks suggests a WC version break
        $results['issues'][] = [
            'level'   => 'warning',
            'category'=> 'WooCommerce Hooks',
            'message' => count($removed_hooks) . ' WooCommerce hooks appear unused/removed in WC ' . $wc . ': ' . implode( ', ', array_slice($removed_hooks, 0, 3) ) . '…',
            'action'  => 'Review plugin hooks against WooCommerce changelog.',
        ];
    } else {
        $results['passed'][] = 'WooCommerce hooks — All checked ✓';
    }

    return $results;
}

// ── Check 4: Per-Plugin File + Table + Option Checks ─────────────────────────
function sgc_health_check_plugin( $results, $key, $plugin ) {
    global $wpdb;

    $plugin_status = [
        'name'    => $plugin['name'],
        'active'  => is_plugin_active( $plugin['slug'] ),
        'version' => defined( $plugin['version_const'] ) ? constant( $plugin['version_const'] ) : 'unknown',
        'issues'  => [],
        'passed'  => [],
    ];

    // Skip deep checks if plugin not active
    if ( ! $plugin_status['active'] ) {
        $plugin_status['issues'][] = [
            'level'   => 'info',
            'message' => $plugin['name'] . ' is not active — skipping file/table checks.',
        ];
        $results['plugins'][ $key ] = $plugin_status;
        return $results;
    }

    // File integrity check
    $plugin_base = WP_PLUGIN_DIR . '/' . dirname( $plugin['slug'] ) . '/';
    foreach ( $plugin['key_files'] as $rel_file ) {
        $full_path = $plugin_base . $rel_file;
        if ( ! file_exists( $full_path ) ) {
            $plugin_status['issues'][] = [
                'level'   => 'critical',
                'message' => "Missing file: {$rel_file}",
                'action'  => 'Re-upload the plugin ZIP via Plugins → Add New → Upload.',
            ];
        } else {
            $plugin_status['passed'][] = "File OK: {$rel_file}";
        }
    }

    // DB table integrity check
    foreach ( $plugin['key_tables'] as $table_suffix ) {
        $table_name = $wpdb->prefix . $table_suffix;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
        if ( $exists !== $table_name ) {
            $plugin_status['issues'][] = [
                'level'   => 'critical',
                'message' => "Database table missing: {$table_name}",
                'action'  => 'Deactivate and reactivate ' . $plugin['name'] . ' to recreate tables.',
            ];
        } else {
            $plugin_status['passed'][] = "DB table OK: {$table_name}";
        }
    }

    // Option existence check
    foreach ( $plugin['key_options'] as $option_key ) {
        if ( get_option( $option_key ) === false ) {
            $plugin_status['issues'][] = [
                'level'   => 'warning',
                'message' => "Plugin option not set: {$option_key}",
                'action'  => 'Open ' . $plugin['name'] . ' settings and save once.',
            ];
        }
    }

    $results['plugins'][ $key ] = $plugin_status;
    return $results;
}

// ── Check 5: Function Conflict Detection ─────────────────────────────────────
function sgc_health_check_function_conflicts( $results ) {
    // Known functions that were duplicated — check if other plugins define them
    $sgc_functions = [
        'sgclabs_ai_call',
        'sgclabs_get_inquiry_count',
        'sgclabs_render_quote_form',
        'sgc_iip_get_settings',
    ];

    $conflicts = [];
    foreach ( $sgc_functions as $fn ) {
        if ( function_exists( $fn ) ) {
            // Function exists — check if it's from a different source
            $ref = new ReflectionFunction( $fn );
            $file = realpath( $ref->getFileName() ) ?: $ref->getFileName(); // v7.5.1: resolve symlinked plugin dirs
            // If function file is not inside our plugin dirs — it's a conflict
            $in_sgc = false;
            foreach ( sgc_health_get_plugin_paths() as $path ) {
                $path = $path ? ( realpath( $path ) ?: $path ) : $path;
                if ( $path && strpos( $file, $path ) === 0 ) {
                    $in_sgc = true;
                    break;
                }
            }
            if ( ! $in_sgc ) {
                $conflicts[] = "{$fn}() defined by: " . basename( $file );
            }
        }
    }

    if ( $conflicts ) {
        $results['issues'][] = [
            'level'   => 'critical',
            'category'=> 'Function Conflicts',
            'message' => 'SGC function names conflict with another plugin: ' . implode( ', ', $conflicts ),
            'action'  => 'Deactivate plugins one by one to identify the conflict.',
        ];
    } else {
        $results['passed'][] = 'Function conflicts — None detected ✓';
    }

    return $results;
}

// ── Helper: Get plugin root paths ─────────────────────────────────────────────
function sgc_health_get_plugin_paths() {
    $paths = [];
    foreach ( $GLOBALS['sgc_monitored_plugins'] as $plugin ) {
        $paths[] = WP_PLUGIN_DIR . '/' . dirname( $plugin['slug'] ) . '/';
    }
    return $paths;
}


// ╔══════════════════════════════════════════════════════════════════════════════╗
// ║  SECTION 2 — TRIGGERS                                                      ║
// ╚══════════════════════════════════════════════════════════════════════════════╝

/**
 * Trigger 1: WP or WooCommerce update complete
 */
add_action( 'upgrader_process_complete', 'sgc_health_on_upgrade', 10, 2 );
function sgc_health_on_upgrade( $upgrader, $options ) {
    // Only trigger on core or plugin updates
    if ( ! isset( $options['type'] ) ) return;
    if ( ! in_array( $options['type'], [ 'core', 'plugin' ], true ) ) return;

    // Check if WooCommerce was updated
    $wc_updated = false;
    if ( isset( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
        foreach ( $options['plugins'] as $plugin_slug ) {
            if ( strpos( $plugin_slug, 'woocommerce' ) !== false ) {
                $wc_updated = true;
                break;
            }
        }
    }

    // Run scan on WP core update or WooCommerce update
    if ( $options['type'] === 'core' || $wc_updated ) {
        // Schedule scan 30 seconds later (after all updates settle)
        if ( ! wp_next_scheduled( 'sgc_health_post_update_scan' ) ) {
            wp_schedule_single_event( time() + 30, 'sgc_health_post_update_scan' );
        }
        // Also set a flag so admin notice shows
        update_option( 'sgc_health_pending_scan', 1, false );
    }
}
add_action( 'sgc_health_post_update_scan', 'sgc_health_run_scan' );

/**
 * Trigger 2: Weekly background scan (WP-Cron)
 */
add_action( 'init', 'sgc_health_schedule_weekly' );
function sgc_health_schedule_weekly() {
    if ( ! wp_next_scheduled( SGC_HEALTH_CRON_HOOK ) ) {
        wp_schedule_event( time(), 'weekly', SGC_HEALTH_CRON_HOOK );
    }
}
add_action( SGC_HEALTH_CRON_HOOK, 'sgc_health_run_scan' );

/**
 * Trigger 3: AJAX — Manual scan from dashboard
 */
add_action( 'wp_ajax_sgc_health_manual_scan', 'sgc_health_ajax_manual_scan' );
function sgc_health_ajax_manual_scan() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied.' );
        return;
    }
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'sgc_health_nonce' ) ) {
        wp_send_json_error( 'Security check failed.' );
        return;
    }
    $results = sgc_health_run_scan();
    wp_send_json_success( [
        'issue_count'  => count( $results['issues'] ),
        'passed_count' => count( $results['passed'] ),
        'scan_time'    => $results['scan_time'],
    ] );
}


// ╔══════════════════════════════════════════════════════════════════════════════╗
// ║  SECTION 3 — ADMIN NOTICE                                                  ║
// ╚══════════════════════════════════════════════════════════════════════════════╝

add_action( 'admin_notices', 'sgc_health_admin_notice' );
function sgc_health_admin_notice() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $results = get_option( SGC_HEALTH_OPTION, null );
    if ( ! $results ) return;

    $issue_count = count( $results['issues'] ?? [] );
    $critical    = array_filter( $results['issues'] ?? [], fn($i) => $i['level'] === 'critical' );
    $report_url  = admin_url( 'admin.php?page=sgclabs-health-monitor' );

    // If pending scan flag is set — show "scan scheduled" notice
    if ( get_option( 'sgc_health_pending_scan' ) ) {
        delete_option( 'sgc_health_pending_scan' );
        echo '<div class="notice notice-info is-dismissible"><p>
            <strong>SGC LABS®:</strong> WP/WooCommerce update detected — running health scan in background.
            <a href="' . esc_url( $report_url ) . '">View Health Monitor →</a>
        </p></div>';
        return;
    }

    if ( $issue_count === 0 ) return; // No issues — no notice

    $level_class = count( $critical ) > 0 ? 'notice-error' : 'notice-warning';
    $icon        = count( $critical ) > 0 ? '🔴' : '⚠️';

    echo '<div class="notice ' . esc_attr( $level_class ) . ' is-dismissible"><p>
        ' . esc_html( $icon ) . ' <strong>SGC Health Monitor:</strong> '
        . esc_html( $issue_count ) . ' issue' . ( $issue_count > 1 ? 's' : '' ) . ' found '
        . ( isset( $results['scan_time'] ) ? '(scan: ' . esc_html( $results['scan_time'] ) . ')' : '' )
        . ' — <a href="' . esc_url( $report_url ) . '"><strong>View Full Report →</strong></a>'
        . '</p></div>';
}


// ╔══════════════════════════════════════════════════════════════════════════════╗
// ║  SECTION 4 — ADMIN PAGE (Health Report)                                    ║
// ╚══════════════════════════════════════════════════════════════════════════════╝

add_action( 'admin_menu', 'sgc_health_register_menu', 25 );
function sgc_health_register_menu() {
    add_submenu_page(
        'sgclabs-manager',
        'Health Monitor',
        '❤️ Health Monitor',
        'manage_options',
        'sgclabs-health-monitor',
        'sgc_health_render_page'
    );
}

function sgc_health_render_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $results    = get_option( SGC_HEALTH_OPTION, null );
    $last_scan  = get_option( SGC_HEALTH_STAMP, null );
    $nonce      = wp_create_nonce( 'sgc_health_nonce' );
    $issue_count = isset( $results['issues'] ) ? count( $results['issues'] ) : 0;
    $pass_count  = isset( $results['passed'] ) ? count( $results['passed'] ) : 0;
    ?>
    <div class="wrap" style="max-width:900px">

        <!-- Header -->
        <div style="background:#1a1a2e;color:#fff;padding:20px 24px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div>
                <h1 style="color:#fff;margin:0;font-size:22px">❤️ SGC Plugin Health Monitor</h1>
                <p style="margin:4px 0 0;color:#9090cc;font-size:13px">
                    <?php if ( $last_scan ) : ?>
                        Last scan: <?php echo esc_html( human_time_diff( $last_scan, time() ) ); ?> ago
                        (<?php echo esc_html( date_i18n( 'D, d M Y H:i', $last_scan ) ); ?>)
                    <?php else : ?>
                        No scan run yet
                    <?php endif; ?>
                </p>
            </div>
            <button id="sgc-health-scan-btn"
                    style="background:#7c3aed;color:#fff;border:none;padding:10px 22px;border-radius:7px;font-size:14px;font-weight:600;cursor:pointer">
                🔍 Run Scan Now
            </button>
        </div>

        <!-- Summary KPI bar -->
        <?php if ( $results ) : ?>
        <div style="display:flex;gap:14px;margin-bottom:20px;flex-wrap:wrap">
            <div style="flex:1;min-width:140px;background:<?php echo $issue_count === 0 ? '#f0fdf4' : '#fef2f2'; ?>;border:1px solid <?php echo $issue_count === 0 ? '#bbf7d0' : '#fecaca'; ?>;border-radius:8px;padding:14px 18px;text-align:center">
                <div style="font-size:28px;font-weight:700;color:<?php echo $issue_count === 0 ? '#16a34a' : '#dc2626'; ?>"><?php echo esc_html( $issue_count ); ?></div>
                <div style="font-size:12px;color:#6b7280;margin-top:2px">Issues Found</div>
            </div>
            <div style="flex:1;min-width:140px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:14px 18px;text-align:center">
                <div style="font-size:28px;font-weight:700;color:#16a34a"><?php echo esc_html( $pass_count ); ?></div>
                <div style="font-size:12px;color:#6b7280;margin-top:2px">Checks Passed</div>
            </div>
            <div style="flex:1;min-width:140px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 18px;text-align:center">
                <div style="font-size:16px;font-weight:700;color:#1a1a2e"><?php echo esc_html( $results['php_version'] ?? '—' ); ?></div>
                <div style="font-size:12px;color:#6b7280;margin-top:2px">PHP Version</div>
            </div>
            <div style="flex:1;min-width:140px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 18px;text-align:center">
                <div style="font-size:16px;font-weight:700;color:#1a1a2e"><?php echo esc_html( $results['wp_version'] ?? '—' ); ?></div>
                <div style="font-size:12px;color:#6b7280;margin-top:2px">WordPress</div>
            </div>
            <div style="flex:1;min-width:140px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 18px;text-align:center">
                <div style="font-size:16px;font-weight:700;color:#1a1a2e"><?php echo esc_html( $results['wc_version'] ?? '—' ); ?></div>
                <div style="font-size:12px;color:#6b7280;margin-top:2px">WooCommerce</div>
            </div>
        </div>

        <!-- Issues List -->
        <?php if ( $issue_count > 0 ) : ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:20px;overflow:hidden">
            <div style="background:#fef2f2;padding:12px 18px;border-bottom:1px solid #fecaca">
                <strong style="color:#991b1b">⚠️ Issues Requiring Attention (<?php echo esc_html( $issue_count ); ?>)</strong>
            </div>
            <?php foreach ( $results['issues'] as $issue ) :
                $colors = [
                    'critical' => [ '#fef2f2', '#dc2626', '🔴' ],
                    'warning'  => [ '#fffbeb', '#d97706', '⚠️' ],
                    'info'     => [ '#eff6ff', '#2563eb', 'ℹ️' ],
                ];
                [$bg, $clr, $ico] = $colors[ $issue['level'] ] ?? $colors['info'];
            ?>
            <div style="background:<?php echo esc_attr($bg); ?>;border-bottom:1px solid #f3f4f6;padding:14px 18px">
                <div style="display:flex;align-items:flex-start;gap:10px">
                    <span style="font-size:16px;flex-shrink:0"><?php echo esc_html($ico); ?></span>
                    <div style="flex:1">
                        <?php if ( isset( $issue['category'] ) ) : ?>
                            <span style="display:inline-block;background:<?php echo esc_attr($clr); ?>;color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px;margin-bottom:4px">
                                <?php echo esc_html( $issue['category'] ); ?>
                            </span>
                        <?php endif; ?>
                        <div style="font-size:13px;color:#1f2937;font-weight:600"><?php echo esc_html( $issue['message'] ); ?></div>
                        <?php if ( isset( $issue['action'] ) ) : ?>
                            <div style="font-size:12px;color:#6b7280;margin-top:4px">👉 <?php echo esc_html( $issue['action'] ); ?></div>
                        <?php endif; ?>
                    </div>
                    <span style="font-size:11px;font-weight:700;color:<?php echo esc_attr($clr); ?>;text-transform:uppercase;flex-shrink:0">
                        <?php echo esc_html( $issue['level'] ); ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Per-Plugin Status -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:20px;overflow:hidden">
            <div style="background:#f8fafc;padding:12px 18px;border-bottom:1px solid #e5e7eb">
                <strong style="color:#1a1a2e">🔌 Plugin Status</strong>
            </div>
            <?php foreach ( $results['plugins'] ?? [] as $key => $pstatus ) :
                $p_issues = count( $pstatus['issues'] ?? [] );
                $p_pass   = count( $pstatus['passed'] ?? [] );
                $p_active = $pstatus['active'] ?? false;
            ?>
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6;display:flex;align-items:flex-start;gap:14px">
                <div style="flex-shrink:0;margin-top:2px">
                    <?php if ( ! $p_active ) : ?>
                        <span style="font-size:20px">⬜</span>
                    <?php elseif ( $p_issues > 0 ) : ?>
                        <span style="font-size:20px">🔴</span>
                    <?php else : ?>
                        <span style="font-size:20px">🟢</span>
                    <?php endif; ?>
                </div>
                <div style="flex:1">
                    <div style="font-size:13px;font-weight:700;color:#1f2937">
                        <?php echo esc_html( $pstatus['name'] ); ?>
                        <span style="font-weight:400;color:#6b7280;font-size:11px">
                            v<?php echo esc_html( $pstatus['version'] ); ?>
                            <?php echo $p_active ? '— Active' : '— Inactive'; ?>
                        </span>
                    </div>
                    <?php if ( $p_issues > 0 ) : ?>
                        <div style="font-size:12px;color:#dc2626;margin-top:3px"><?php echo esc_html($p_issues); ?> issue(s) detected</div>
                        <?php foreach ( $pstatus['issues'] as $pi ) : ?>
                            <div style="font-size:11px;color:#6b7280;margin-top:2px">• <?php echo esc_html($pi['message']); ?></div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <div style="font-size:12px;color:#16a34a;margin-top:3px"><?php echo esc_html($p_pass); ?> checks passed ✓</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Passed Checks -->
        <?php if ( $pass_count > 0 ) : ?>
        <details style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:12px 18px;margin-bottom:20px">
            <summary style="cursor:pointer;font-weight:600;color:#16a34a">✅ Passed Checks (<?php echo esc_html($pass_count); ?>)</summary>
            <ul style="margin:10px 0 0;padding-left:18px">
                <?php foreach ( $results['passed'] as $p ) : ?>
                    <li style="font-size:12px;color:#374151;margin:3px 0"><?php echo esc_html($p); ?></li>
                <?php endforeach; ?>
            </ul>
        </details>
        <?php endif; ?>

        <?php else : ?>
        <!-- No scan yet -->
        <div style="background:#f8fafc;border:2px dashed #d1d5db;border-radius:10px;padding:40px;text-align:center">
            <div style="font-size:40px;margin-bottom:10px">🔍</div>
            <div style="font-size:15px;font-weight:600;color:#374151">No scan has been run yet</div>
            <div style="font-size:13px;color:#6b7280;margin-top:6px">Click "Run Scan Now" to check all SGC plugins.</div>
        </div>
        <?php endif; ?>

    </div><!-- .wrap -->

    <!-- Scan result notice (after AJAX) -->
    <div id="sgc-health-result" style="display:none;position:fixed;bottom:20px;right:20px;background:#1a1a2e;color:#fff;padding:14px 20px;border-radius:10px;z-index:9999;font-size:13px;max-width:320px;box-shadow:0 4px 20px rgba(0,0,0,0.3)"></div>

    <script>
    (function($){
        $('#sgc-health-scan-btn').on('click', function(){
            var btn = $(this);
            btn.text('⏳ Scanning…').prop('disabled', true);
            $.post(ajaxurl, {
                action : 'sgc_health_manual_scan',
                nonce  : '<?php echo esc_js( $nonce ); ?>'
            }, function(res){
                btn.text('🔍 Run Scan Now').prop('disabled', false);
                if(res.success){
                    var d = res.data;
                    var msg = d.issue_count === 0
                        ? '✅ All clear! ' + d.passed_count + ' checks passed.'
                        : '⚠️ ' + d.issue_count + ' issue(s) found. Page reloading…';
                    $('#sgc-health-result').html(msg).fadeIn();
                    setTimeout(function(){ location.reload(); }, 1800);
                } else {
                    btn.text('🔍 Run Scan Now').prop('disabled', false);
                    $('#sgc-health-result').html('❌ Scan error: ' + (res.data||'unknown')).fadeIn();
                    setTimeout(function(){ $('#sgc-health-result').fadeOut(); }, 4000);
                }
            }).fail(function(){
                btn.text('🔍 Run Scan Now').prop('disabled', false);
                $('#sgc-health-result').html('❌ AJAX error. Check browser console.').fadeIn();
                setTimeout(function(){ $('#sgc-health-result').fadeOut(); }, 4000);
            });
        });
    })(jQuery);
    </script>
    <?php
}


// ╔══════════════════════════════════════════════════════════════════════════════╗
// ║  SECTION 5 — WP DASHBOARD WIDGET                                           ║
// ╚══════════════════════════════════════════════════════════════════════════════╝

add_action( 'wp_dashboard_setup', 'sgc_health_dashboard_widget_setup' );
function sgc_health_dashboard_widget_setup() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    wp_add_dashboard_widget(
        'sgc_health_widget',
        '❤️ SGC Plugin Health',
        'sgc_health_dashboard_widget_render'
    );
}

function sgc_health_dashboard_widget_render() {
    $results   = get_option( SGC_HEALTH_OPTION, null );
    $last_scan = get_option( SGC_HEALTH_STAMP, null );
    $url       = admin_url( 'admin.php?page=sgclabs-health-monitor' );

    if ( ! $results ) {
        echo '<p style="color:#6b7280;font-size:13px">No scan run yet. <a href="' . esc_url($url) . '">Run first scan →</a></p>';
        return;
    }

    $issues  = $results['issues'] ?? [];
    $passed  = $results['passed'] ?? [];
    $plugins = $results['plugins'] ?? [];

    echo '<div style="font-size:12px;color:#6b7280;margin-bottom:10px">Last scan: ' . esc_html( human_time_diff( $last_scan, time() ) ) . ' ago</div>';

    // Plugin rows
    foreach ( $plugins as $pstatus ) {
        $p_active = $pstatus['active'] ?? false;
        $p_issues = count( $pstatus['issues'] ?? [] );
        $icon     = ! $p_active ? '⬜' : ( $p_issues > 0 ? '🔴' : '🟢' );
        echo '<div style="display:flex;align-items:center;gap:8px;padding:5px 0;border-bottom:1px solid #f3f4f6">';
        echo '<span>' . esc_html($icon) . '</span>';
        echo '<span style="flex:1;font-size:12px;color:#374151">' . esc_html($pstatus['name']) . '</span>';
        echo '<span style="font-size:11px;color:#6b7280">v' . esc_html($pstatus['version']) . '</span>';
        echo '</div>';
    }

    // Summary
    $total_issues = count( $issues );
    echo '<div style="margin-top:12px;display:flex;align-items:center;justify-content:space-between">';
    if ( $total_issues === 0 ) {
        echo '<span style="color:#16a34a;font-weight:600;font-size:12px">✅ All ' . esc_html(count($passed)) . ' checks passed</span>';
    } else {
        echo '<span style="color:#dc2626;font-weight:600;font-size:12px">⚠️ ' . esc_html($total_issues) . ' issue(s) found</span>';
    }
    echo '<a href="' . esc_url($url) . '" style="font-size:11px">Full Report →</a>';
    echo '</div>';
}


// ╔══════════════════════════════════════════════════════════════════════════════╗
// ║  SECTION 6 — CLEANUP ON DEACTIVATION                                       ║
// ╚══════════════════════════════════════════════════════════════════════════════╝

register_deactivation_hook(
    defined('SGCLABS_PATH') ? SGCLABS_PATH . 'sgclabs-manager.php' : __FILE__,
    'sgc_health_deactivate'
);
function sgc_health_deactivate() {
    $timestamp = wp_next_scheduled( SGC_HEALTH_CRON_HOOK );
    if ( $timestamp ) wp_unschedule_event( $timestamp, SGC_HEALTH_CRON_HOOK );
    $timestamp2 = wp_next_scheduled( 'sgc_health_post_update_scan' );
    if ( $timestamp2 ) wp_unschedule_event( $timestamp2, 'sgc_health_post_update_scan' );
}
