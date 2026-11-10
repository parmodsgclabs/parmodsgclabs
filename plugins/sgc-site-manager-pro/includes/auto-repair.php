<?php
/**
 * SGC LABS® Auto-Repair System v1.0.0
 *
 * Comprehensive daily scanner + auto-fixer for:
 *   - All SGC plugin DB tables, crons, options, files
 *   - Third-party plugin conflicts
 *   - WordPress core health
 *   - AI-powered error log analysis
 *
 * INSTALL: sgc-site-manager-pro/includes/auto-repair.php
 * REGISTER: Add in sgclabs-manager.php:
 *   sgclabs_include_file( SGCLABS_PATH . 'includes/auto-repair.php' );
 *
 * @package SGCLabsManagerPro
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

// ── Constants ──────────────────────────────────────────────────────────────────
define( 'SGC_REPAIR_VERSION',   '1.0.0' );
define( 'SGC_REPAIR_OPTION',    'sgc_auto_repair_last_report' );
define( 'SGC_REPAIR_LOG',       'sgc_auto_repair_log' );
define( 'SGC_REPAIR_CRON',      'sgc_auto_repair_daily' );
define( 'SGC_REPAIR_AI_OPTION', 'sgc_auto_repair_ai_analysis' );


// ╔══════════════════════════════════════════════════════════════════════════════╗
// ║  SECTION 1 — REPAIR DEFINITIONS                                            ║
// ╚══════════════════════════════════════════════════════════════════════════════╝

/**
 * All SGC DB tables — with their creation callbacks
 */
function sgc_repair_get_tables(): array {
    global $wpdb;
    return [

        // ── Manager Pro ───────────────────────────────────────────────────────
        $wpdb->prefix . 'sgclabs_inquiries' => [
            'plugin'   => 'Manager Pro',
            'callback' => 'sgc_repair_create_sgclabs_inquiries',
        ],
        $wpdb->prefix . 'sgclabs_kw_rankings' => [
            'plugin'   => 'Manager Pro',
            'callback' => 'sgc_repair_create_sgclabs_kw_rankings',
        ],
        $wpdb->prefix . 'sgclabs_broken_links' => [
            'plugin'   => 'Manager Pro',
            'callback' => 'sgc_repair_create_sgclabs_broken_links',
        ],
        $wpdb->prefix . 'sgclabs_kw_global' => [
            'plugin'   => 'Manager Pro',
            'callback' => 'sgc_repair_create_sgclabs_kw_global',
        ],
        $wpdb->prefix . 'sgclabs_kw_gsc' => [
            'plugin'   => 'Manager Pro',
            'callback' => 'sgc_repair_create_sgclabs_kw_gsc',
        ],
        $wpdb->prefix . 'sgclabs_crawl_pages' => [
            'plugin'   => 'Manager Pro',
            'callback' => 'sgc_repair_create_sgclabs_crawl_pages',
        ],
        $wpdb->prefix . 'sgclabs_sitemap_pings' => [
            'plugin'   => 'Manager Pro',
            'callback' => 'sgc_repair_create_sgclabs_sitemap_pings',
        ],

        // ── Invoice Pro ───────────────────────────────────────────────────────
        $wpdb->prefix . 'sgc_iip_inquiries' => [
            'plugin'   => 'Invoice Pro',
            'callback' => 'sgc_repair_create_iip_tables',
        ],
        $wpdb->prefix . 'sgc_iip_proformas' => [
            'plugin'   => 'Invoice Pro',
            'callback' => 'sgc_repair_create_iip_tables',
        ],
        $wpdb->prefix . 'sgc_iip_packlists' => [
            'plugin'   => 'Invoice Pro',
            'callback' => 'sgc_repair_create_iip_tables',
        ],

        // ── Smart CRM ─────────────────────────────────────────────────────────
        $wpdb->prefix . 'sgc_crm_contacts' => [
            'plugin'   => 'Smart CRM',
            'callback' => 'sgc_repair_create_crm_tables',
        ],
        $wpdb->prefix . 'sgc_crm_campaigns' => [
            'plugin'   => 'Smart CRM',
            'callback' => 'sgc_repair_create_crm_tables',
        ],
    ];
}

/**
 * All SGC WP-Cron schedules
 */
function sgc_repair_get_crons(): array {
    return [
        'sgclabs_gsc_daily_sync' => [
            'plugin'    => 'Manager Pro',
            'recurrence'=> 'daily',
            'active_if' => 'sgclabs_gsc_is_connected',
        ],
        'sgclabs_daily_rank_sync' => [
            'plugin'    => 'Manager Pro',
            'recurrence'=> 'daily',
            'active_if' => null,
        ],
        'sgclabs_reorder_daily_check' => [
            'plugin'    => 'Manager Pro',
            'recurrence'=> 'daily',
            'active_if' => null,
        ],
        'sgclabs_doctor_daily_scan' => [
            'plugin'    => 'Manager Pro',
            'recurrence'=> 'daily',
            'active_if' => null,
        ],
        SGC_REPAIR_CRON => [
            'plugin'    => 'Auto-Repair',
            'recurrence'=> 'daily',
            'active_if' => null,
        ],
        'sgc_health_weekly_scan' => [
            'plugin'    => 'Health Monitor',
            'recurrence'=> 'weekly',
            'active_if' => null,
        ],
        'sgc_iip_followup_cron' => [
            'plugin'    => 'Invoice Pro',
            'recurrence'=> 'hourly',
            'active_if' => 'SGC_IIP_VERSION',
        ],
        'sgc_iip_rate_refresh' => [
            'plugin'    => 'Invoice Pro',
            'recurrence'=> 'daily',
            'active_if' => 'SGC_IIP_VERSION',
        ],
        'sgc_crm_process_queue' => [
            'plugin'    => 'Smart CRM',
            'recurrence'=> 'sgc_crm_every_five_minutes',
            'active_if' => 'SGC_CRM_VERSION',
        ],
        'sgc_crm_run_automations' => [
            'plugin'    => 'Smart CRM',
            'recurrence'=> 'hourly',
            'active_if' => 'SGC_CRM_VERSION',
        ],
    ];
}

/**
 * Key options with default values
 */
function sgc_repair_get_options(): array {
    return [
        'sgclabs_version'           => [ 'plugin' => 'Manager Pro', 'default' => defined('SGCLABS_BASE_VERSION') ? SGCLABS_BASE_VERSION : '7.4.7' ],
        'sgclabs_multilingual_seo_active' => [ 'plugin' => 'Manager Pro', 'default' => '1' ],
        'sgclabs_quote_notify_email' => [ 'plugin' => 'Manager Pro', 'default' => get_option('admin_email') ],
        'sgc_iip_usd_rate'          => [ 'plugin' => 'Invoice Pro',  'default' => '83.50' ],
        'sgc_iip_company_name'      => [ 'plugin' => 'Invoice Pro',  'default' => 'SGC LABS®' ],
    ];
}

/**
 * Key files — must exist for plugins to work
 */
function sgc_repair_get_files(): array {
    $smp = defined('SGCLABS_PATH') ? SGCLABS_PATH : '';
    $iip = defined('SGC_IIP_PATH') ? SGC_IIP_PATH : '';
    $crm = defined('SGC_CRM_PATH') ? SGC_CRM_PATH : '';
    $pm  = defined('SGC_PM_PATH')  ? SGC_PM_PATH  : '';

    $files = [];

    if ( $smp ) {
        $files = array_merge( $files, [
            $smp . 'includes/ai-provider.php'        => 'Manager Pro — AI Provider',
            $smp . 'includes/ai-content-engine.php'  => 'Manager Pro — AI Engine',
            $smp . 'includes/get-a-quote.php'        => 'Manager Pro — Quote Form',
            $smp . 'includes/plugin-health-monitor.php' => 'Manager Pro — Health Monitor',
            $smp . 'includes/auto-repair.php'        => 'Manager Pro — Auto Repair',
            $smp . 'includes/gsc-connect.php'        => 'Manager Pro — GSC Connect',
            $smp . 'includes/site-doctor.php'        => 'Manager Pro — Site Doctor',
        ] );
    }
    if ( $iip ) {
        $files = array_merge( $files, [
            $iip . 'sgc-iip.php'                    => 'Invoice Pro — Main',
            $iip . 'includes/class-db.php'           => 'Invoice Pro — DB Class',
            $iip . 'includes/class-proforma.php'     => 'Invoice Pro — Proforma',
            $iip . 'includes/class-hooks.php'        => 'Invoice Pro — Hooks',
        ] );
    }
    if ( $crm ) {
        $files = array_merge( $files, [
            $crm . 'sgc-smart-crm.php'              => 'Smart CRM — Main',
            $crm . 'includes/class-crm-db.php'       => 'Smart CRM — DB Class',
            $crm . 'includes/class-crm-contacts.php' => 'Smart CRM — Contacts',
        ] );
    }
    if ( $pm ) {
        $files = array_merge( $files, [
            $pm . 'sgc-product-manager.php'         => 'Product Manager — Main',
            $pm . 'includes/class-admin.php'         => 'Product Manager — Admin',
        ] );
    }

    return $files;
}


// ╔══════════════════════════════════════════════════════════════════════════════╗
// ║  SECTION 2 — MAIN REPAIR ENGINE                                            ║
// ╚══════════════════════════════════════════════════════════════════════════════╝

function sgc_auto_repair_run(): array {

    $report = [
        'time'      => current_time( 'mysql' ),
        'fixed'     => [],
        'failed'    => [],
        'skipped'   => [],
        'info'      => [],
        'wp_health' => [],
        'third_party'=> [],
        'ai_analysis'=> '',
    ];

    // ── 1. DB Tables ──────────────────────────────────────────────────────────
    $report = sgc_repair_check_tables( $report );

    // ── 2. WP-Cron Schedules ──────────────────────────────────────────────────
    $report = sgc_repair_check_crons( $report );

    // ── 3. Key Options ────────────────────────────────────────────────────────
    $report = sgc_repair_check_options( $report );

    // ── 4. File Integrity ─────────────────────────────────────────────────────
    $report = sgc_repair_check_files( $report );

    // ── 5. WordPress Core Health ──────────────────────────────────────────────
    $report = sgc_repair_check_wp_health( $report );

    // ── 6. Third-party Plugin Conflicts ───────────────────────────────────────
    $report = sgc_repair_check_third_party( $report );

    // ── 7. AI Error Log Analysis ──────────────────────────────────────────────
    $report = sgc_repair_ai_analysis( $report );

    // ── Save Report ───────────────────────────────────────────────────────────
    update_option( SGC_REPAIR_OPTION, $report, false );
    update_option( 'sgc_auto_repair_last_run', time(), false );

    // ── Email alert if anything failed ────────────────────────────────────────
    if ( ! empty( $report['failed'] ) ) {
        sgc_repair_send_alert( $report );
    }

    return $report;
}


// ── Repair 1: DB Tables ───────────────────────────────────────────────────────

function sgc_repair_check_tables( array $report ): array {
    global $wpdb;
    $tables  = sgc_repair_get_tables();
    $called  = []; // track which callbacks already ran

    foreach ( $tables as $table => $info ) {
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

        if ( $exists === $table ) {
            $report['info'][] = "DB ✓ {$table}";
            continue;
        }

        // Table missing — try to create
        $cb = $info['callback'];
        if ( ! in_array( $cb, $called, true ) && function_exists( $cb ) ) {
            call_user_func( $cb );
            $called[] = $cb;
        } elseif ( ! in_array( $cb, $called, true ) ) {
            // Callback not available — plugin might not be active
            $report['skipped'][] = "DB skip: {$table} ({$info['plugin']} not active)";
            continue;
        }

        // Verify it was created
        $now_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $now_exists === $table ) {
            $report['fixed'][] = "DB ✅ Created missing table: {$table} ({$info['plugin']})";
        } else {
            $report['failed'][] = "DB ❌ Could not create: {$table} ({$info['plugin']})";
        }
    }

    return $report;
}


// ── Repair 2: WP-Cron ────────────────────────────────────────────────────────

function sgc_repair_check_crons( array $report ): array {

    foreach ( sgc_repair_get_crons() as $hook => $info ) {

        // Only repair cron if relevant plugin is active
        $active_if = $info['active_if'];
        if ( $active_if ) {
            if ( function_exists( $active_if ) ) {
                if ( ! call_user_func( $active_if ) ) {
                    $report['skipped'][] = "Cron skip: {$hook} (condition not met)";
                    continue;
                }
            } elseif ( defined( $active_if ) ) {
                // constant exists = plugin is active, ok
            } else {
                $report['skipped'][] = "Cron skip: {$hook} ({$info['plugin']} not active)";
                continue;
            }
        }

        if ( wp_next_scheduled( $hook ) ) {
            $report['info'][] = "Cron ✓ {$hook}";
            continue;
        }

        // Missing — re-register
        $recurrence = $info['recurrence'];
        $schedules  = wp_get_schedules();

        if ( ! isset( $schedules[ $recurrence ] ) && ! in_array( $recurrence, [ 'hourly', 'twicedaily', 'daily', 'weekly' ], true ) ) {
            $report['failed'][] = "Cron ❌ {$hook} — unknown recurrence '{$recurrence}'";
            continue;
        }

        $scheduled = wp_schedule_event( time() + 60, $recurrence, $hook );
        if ( $scheduled !== false ) {
            $report['fixed'][] = "Cron ✅ Re-registered: {$hook} ({$info['plugin']})";
        } else {
            $report['failed'][] = "Cron ❌ Could not register: {$hook}";
        }
    }

    return $report;
}


// ── Repair 3: Options ─────────────────────────────────────────────────────────

function sgc_repair_check_options( array $report ): array {

    foreach ( sgc_repair_get_options() as $option => $info ) {
        $value = get_option( $option );

        if ( $value !== false && $value !== '' ) {
            $report['info'][] = "Option ✓ {$option}";
            continue;
        }

        // Missing — restore default
        update_option( $option, $info['default'], false );
        $report['fixed'][] = "Option ✅ Restored default: {$option} ({$info['plugin']})";
    }

    return $report;
}


// ── Repair 4: File Integrity ──────────────────────────────────────────────────

function sgc_repair_check_files( array $report ): array {

    foreach ( sgc_repair_get_files() as $path => $label ) {
        if ( file_exists( $path ) ) {
            $report['info'][] = "File ✓ {$label}";
            continue;
        }

        // File missing — try to restore from update server
        $restored = sgc_repair_restore_file( $path, $label );

        if ( $restored ) {
            $report['fixed'][] = "File ✅ Restored from server: {$label}";
        } else {
            $report['failed'][] = "File ❌ Missing (could not restore): {$label} — {$path}";
        }
    }

    return $report;
}

function sgc_repair_restore_file( string $path, string $label ): bool {
    // Try to restore via update server download
    // This is best-effort — server must have the ZIP available
    $server_url = 'https://labsglass.com/wp-content/plugins/sgc-update-server/sgc-update-server.php';
    $key        = 'sgclabs_secure_2026_xyz';

    // Determine which plugin this file belongs to
    $plugin_id = null;
    if ( defined('SGCLABS_PATH') && strpos( $path, SGCLABS_PATH ) === 0 ) {
        $plugin_id = 'sgc-site-manager-pro';
    } elseif ( defined('SGC_IIP_PATH') && strpos( $path, SGC_IIP_PATH ) === 0 ) {
        $plugin_id = 'sgc-invoice-packlist-pro';
    } elseif ( defined('SGC_CRM_PATH') && strpos( $path, SGC_CRM_PATH ) === 0 ) {
        $plugin_id = 'sgc-smart-crm-pro';
    } elseif ( defined('SGC_PM_PATH') && strpos( $path, SGC_PM_PATH ) === 0 ) {
        $plugin_id = 'sgc-product-manager-pro';
    }

    if ( ! $plugin_id ) return false;

    // Get ZIP download URL from server
    $check_url = add_query_arg( [
        'action' => 'check',
        'plugin' => $plugin_id,
        'key'    => $key,
    ], $server_url );

    $response = wp_remote_get( $check_url, [ 'timeout' => 10 ] );
    if ( is_wp_error( $response ) ) return false;

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( empty( $data['download_url'] ) ) return false;

    // Log the attempt — actual file restoration from ZIP requires unzip
    // which is environment-dependent. Log for admin awareness.
    error_log( "SGC Auto-Repair: File missing '{$label}' — ZIP available at: {$data['download_url']}" );

    // Can't safely unzip and restore individual files here
    // — flag for manual restore instead
    return false;
}


// ── Repair 5: WordPress Core Health ──────────────────────────────────────────

function sgc_repair_check_wp_health( array $report ): array {

    // Check WP version
    $wp_version = get_bloginfo( 'version' );
    if ( version_compare( $wp_version, '6.0', '<' ) ) {
        $report['wp_health'][] = [
            'level'   => 'critical',
            'message' => "WordPress {$wp_version} outdated. Update immediately.",
            'action'  => 'Dashboard → Updates',
        ];
    } else {
        $report['info'][] = "WP ✓ Version {$wp_version}";
    }

    // Check WooCommerce
    if ( ! defined( 'WC_VERSION' ) ) {
        $report['wp_health'][] = [
            'level'   => 'critical',
            'message' => 'WooCommerce not active — SGC features broken.',
            'action'  => 'Activate WooCommerce',
        ];
    } else {
        $report['info'][] = "WC ✓ Version " . WC_VERSION;
    }

    // Check autoloaded options bloat
    global $wpdb;
    $autoload_size = $wpdb->get_var(
        "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload='yes'"
    );
    $autoload_mb = round( intval( $autoload_size ) / 1024 / 1024, 2 );
    if ( $autoload_mb > 2 ) {
        $report['wp_health'][] = [
            'level'   => 'warning',
            'message' => "Autoloaded options: {$autoload_mb} MB (should be < 2 MB). Causes slow DB queries.",
            'action'  => 'Run transient cleanup from Tools → SGC Site Tools',
        ];
    } else {
        $report['info'][] = "Autoload ✓ {$autoload_mb} MB";
    }

    // Check PHP memory limit
    $memory_limit = ini_get( 'memory_limit' );
    $memory_mb    = intval( $memory_limit );
    if ( $memory_mb < 256 && strpos( $memory_limit, 'G' ) === false ) {
        $report['wp_health'][] = [
            'level'   => 'warning',
            'message' => "PHP memory_limit: {$memory_limit} — recommend 256M+ for 7,000 products.",
            'action'  => 'Contact Bluehost to increase memory limit',
        ];
    } else {
        $report['info'][] = "Memory ✓ {$memory_limit}";
    }

    // Check max_execution_time
    $max_exec = ini_get( 'max_execution_time' );
    if ( intval( $max_exec ) > 0 && intval( $max_exec ) < 120 ) {
        $report['wp_health'][] = [
            'level'   => 'info',
            'message' => "max_execution_time: {$max_exec}s — bulk operations may timeout.",
            'action'  => 'Increase via .htaccess: php_value max_execution_time 300',
        ];
    }

    // Check WP_DEBUG_LOG
    if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
        $log_file = WP_CONTENT_DIR . '/debug.log';
        if ( file_exists( $log_file ) ) {
            $log_size_mb = round( filesize( $log_file ) / 1024 / 1024, 2 );
            if ( $log_size_mb > 10 ) {
                $report['wp_health'][] = [
                    'level'   => 'warning',
                    'message' => "debug.log is {$log_size_mb} MB — very large. Consider clearing.",
                    'action'  => 'Delete /wp-content/debug.log via FTP',
                ];
            } else {
                $report['info'][] = "Debug log ✓ {$log_size_mb} MB";
            }
        }
    }

    // Check active plugin count
    $active_plugins = get_option( 'active_plugins', [] );
    $plugin_count   = count( $active_plugins );
    if ( $plugin_count > 40 ) {
        $report['wp_health'][] = [
            'level'   => 'warning',
            'message' => "{$plugin_count} plugins active — high count can slow site.",
            'action'  => 'Deactivate unused plugins',
        ];
    } else {
        $report['info'][] = "Plugins ✓ {$plugin_count} active";
    }

    // Check for updates available
    $update_plugins = get_site_transient( 'update_plugins' );
    if ( isset( $update_plugins->response ) && count( $update_plugins->response ) > 0 ) {
        $count = count( $update_plugins->response );
        $report['wp_health'][] = [
            'level'   => 'info',
            'message' => "{$count} plugin update(s) available.",
            'action'  => 'Dashboard → Updates',
        ];
    }

    return $report;
}


// ── Repair 6: Third-party Plugin Conflicts ────────────────────────────────────

function sgc_repair_check_third_party( array $report ): array {

    // Check for known conflicting plugins
    $known_conflicts = [
        'wp-rocket/wp-rocket.php' => [
            'name'   => 'WP Rocket',
            'issue'  => 'May cache AJAX responses. Exclude SGC AJAX URLs.',
            'action' => 'WP Rocket → Advanced → Exclude: /wp-admin/admin-ajax.php',
        ],
        'litespeed-cache/litespeed-cache.php' => [
            'name'   => 'LiteSpeed Cache',
            'issue'  => 'May conflict with SGC dynamic content.',
            'action' => 'Exclude SGC pages from cache',
        ],
        'siteground-optimizer/index.php' => [
            'name'   => 'SiteGround Optimizer',
            'issue'  => 'JS/CSS minification may break SGC admin scripts.',
            'action' => 'Disable JS/CSS combine for admin pages',
        ],
        'autoptimize/autoptimize.php' => [
            'name'   => 'Autoptimize',
            'issue'  => 'JS aggregation can break SGC admin UI.',
            'action' => 'Exclude admin AJAX from Autoptimize',
        ],
    ];

    $active_plugins = get_option( 'active_plugins', [] );

    foreach ( $known_conflicts as $plugin_file => $info ) {
        if ( in_array( $plugin_file, $active_plugins, true ) ) {
            $report['third_party'][] = [
                'level'   => 'warning',
                'name'    => $info['name'],
                'message' => $info['issue'],
                'action'  => $info['action'],
            ];
        }
    }

    // Check for duplicate SEO plugins (conflict with SGC SEO)
    $seo_plugins = [
        'wordpress-seo/wp-seo.php'        => 'Yoast SEO',
        'all-in-one-seo-pack/all_in_one_seo_pack.php' => 'All in One SEO',
        'the-seo-framework/the-seo-framework.php' => 'The SEO Framework',
    ];

    $active_seo = [];
    foreach ( $seo_plugins as $file => $name ) {
        if ( in_array( $file, $active_plugins, true ) ) {
            $active_seo[] = $name;
        }
    }

    if ( ! empty( $active_seo ) ) {
        $report['third_party'][] = [
            'level'   => 'warning',
            'name'    => implode( ', ', $active_seo ),
            'message' => 'Third-party SEO plugin active alongside SGC SEO — duplicate meta tags possible.',
            'action'  => 'Disable SGC SEO meta injection or disable third-party SEO plugin',
        ];
    }

    // Codestar Framework deprecated warning
    if ( in_array( 'codestar-framework/codestar-framework.php', $active_plugins, true ) ) {
        $report['third_party'][] = [
            'level'   => 'info',
            'name'    => 'CodeStar Framework',
            'message' => 'PHP 8.x deprecated warnings from codestar-framework/functions/fallback.php line 31. Not critical.',
            'action'  => 'Update CodeStar Framework or ignore (warnings only, not fatal)',
        ];
    }

    if ( empty( $report['third_party'] ) ) {
        $report['info'][] = 'Third-party ✓ No conflicts detected';
    }

    return $report;
}


// ── Repair 7: AI Error Log Analysis ──────────────────────────────────────────

function sgc_repair_ai_analysis( array $report ): array {

    // Only run if AI is available
    if ( ! function_exists( 'sgclabs_ai_call' ) ) {
        $report['ai_analysis'] = 'AI analysis skipped — sgclabs_ai_call() not available.';
        return $report;
    }

    // Read last 100 lines of debug.log
    $log_file = WP_CONTENT_DIR . '/debug.log';
    if ( ! file_exists( $log_file ) || ! is_readable( $log_file ) ) {
        $report['ai_analysis'] = 'No debug.log found — no errors to analyze.';
        return $report;
    }

    // Get last ~5KB of log
    $fp       = fopen( $log_file, 'r' );
    $log_size = filesize( $log_file );
    $read_from = max( 0, $log_size - 5000 );
    fseek( $fp, $read_from );
    $log_tail = fread( $fp, 5000 );
    fclose( $fp );

    // Check if there are any errors worth analyzing
    if ( substr_count( $log_tail, 'PHP Fatal' ) === 0 &&
         substr_count( $log_tail, 'PHP Error' ) === 0 &&
         substr_count( $log_tail, 'PHP Warning' ) < 3 ) {
        $report['ai_analysis'] = 'No significant errors in recent log.';
        return $report;
    }

    // Ask AI to analyze
    $prompt = "You are a WordPress expert. Analyze this PHP error log from a WooCommerce site (labsglass.com). 
Identify the TOP 3 most important issues and suggest specific fixes. Be concise — max 200 words.
Focus on: Fatal errors, recurring warnings, performance issues.
Ignore: ob_end_flush notices (known Bluehost issue), deprecated notices from third-party plugins.

ERROR LOG (last entries):
{$log_tail}

Respond in this format:
ISSUE 1: [problem] → FIX: [solution]
ISSUE 2: [problem] → FIX: [solution]  
ISSUE 3: [problem] → FIX: [solution]
SUMMARY: [one line overall assessment]";

    $ai_response = sgclabs_ai_call( $prompt, 400, 'WordPress error log analyzer. Be specific and actionable.' );

    if ( $ai_response ) {
        $report['ai_analysis'] = $ai_response;
        update_option( SGC_REPAIR_AI_OPTION, [
            'analysis' => $ai_response,
            'time'     => current_time( 'mysql' ),
        ], false );
    } else {
        $report['ai_analysis'] = 'AI analysis failed — check AI provider settings.';
    }

    return $report;
}


// ── Table Creation Callbacks ──────────────────────────────────────────────────

function sgc_repair_create_sgclabs_inquiries(): void {
    // Trigger Manager Pro's own table creation
    if ( function_exists( 'sgclabs_activate' ) ) {
        sgclabs_activate();
    }
}

function sgc_repair_create_sgclabs_kw_rankings(): void {
    if ( function_exists( 'sgclabs_activate' ) ) {
        sgclabs_activate();
    }
}

function sgc_repair_create_sgclabs_broken_links(): void {
    if ( function_exists( 'sgclabs_activate' ) ) {
        sgclabs_activate();
    }
}

function sgc_repair_create_sgclabs_kw_global(): void {
    if ( function_exists( 'sgclabs_activate' ) ) {
        sgclabs_activate();
    }
}

function sgc_repair_create_sgclabs_kw_gsc(): void {
    if ( function_exists( 'sgclabs_activate' ) ) {
        sgclabs_activate();
    }
}

function sgc_repair_create_sgclabs_crawl_pages(): void {
    if ( function_exists( 'sgclabs_activate' ) ) {
        sgclabs_activate();
    }
}

function sgc_repair_create_sgclabs_sitemap_pings(): void {
    if ( function_exists( 'sgclabs_activate' ) ) {
        sgclabs_activate();
    }
}

function sgc_repair_create_iip_tables(): void {
    if ( class_exists( 'SGC_IIP_DB' ) ) {
        SGC_IIP_DB::create_tables();
    }
}

function sgc_repair_create_crm_tables(): void {
    if ( class_exists( 'SGC_CRM_DB' ) ) {
        SGC_CRM_DB::create_tables();
    }
}


// ── Email Alert ───────────────────────────────────────────────────────────────

function sgc_repair_send_alert( array $report ): void {
    $to      = get_option( 'sgclabs_quote_notify_email', get_option( 'admin_email' ) );
    $subject = '[SGC LABS®] Auto-Repair Alert — ' . count( $report['failed'] ) . ' issue(s) need attention';

    $body  = "SGC Auto-Repair ran on " . $report['time'] . "\n\n";
    $body .= "❌ COULD NOT AUTO-FIX (" . count( $report['failed'] ) . "):\n";
    foreach ( $report['failed'] as $item ) {
        $body .= "  • {$item}\n";
    }
    if ( ! empty( $report['fixed'] ) ) {
        $body .= "\n✅ AUTO-FIXED (" . count( $report['fixed'] ) . "):\n";
        foreach ( $report['fixed'] as $item ) {
            $body .= "  • {$item}\n";
        }
    }
    if ( $report['ai_analysis'] ) {
        $body .= "\n🤖 AI ANALYSIS:\n" . $report['ai_analysis'] . "\n";
    }
    $body .= "\nView full report: " . admin_url( 'admin.php?page=sgclabs-auto-repair' );

    wp_mail( $to, $subject, $body );
}


// ╔══════════════════════════════════════════════════════════════════════════════╗
// ║  SECTION 3 — TRIGGERS                                                      ║
// ╚══════════════════════════════════════════════════════════════════════════════╝

// Daily cron
add_action( 'init', 'sgc_repair_schedule_cron' );
function sgc_repair_schedule_cron(): void {
    if ( ! wp_next_scheduled( SGC_REPAIR_CRON ) ) {
        // Schedule for 6 AM IST (00:30 UTC)
        $next_6am = strtotime( 'tomorrow 00:30:00' );
        wp_schedule_event( $next_6am, 'daily', SGC_REPAIR_CRON );
    }
}
add_action( SGC_REPAIR_CRON, 'sgc_auto_repair_run' );

// Run on plugin activation
add_action( 'sgclabs_plugin_activated', function() {
    wp_schedule_single_event( time() + 5, 'sgc_repair_post_activate' );
} );
add_action( 'sgc_repair_post_activate', 'sgc_auto_repair_run' );

// AJAX — manual trigger
add_action( 'wp_ajax_sgc_run_auto_repair', 'sgc_repair_ajax_handler' );
function sgc_repair_ajax_handler(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied.' );
        return;
    }
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'sgc_auto_repair_nonce' ) ) {
        wp_send_json_error( 'Security check failed.' );
        return;
    }

    $report = sgc_auto_repair_run();

    wp_send_json_success( [
        'fixed'   => count( $report['fixed'] ),
        'failed'  => count( $report['failed'] ),
        'skipped' => count( $report['skipped'] ),
        'time'    => $report['time'],
    ] );
}

// Admin notice if issues found
add_action( 'admin_notices', 'sgc_repair_admin_notice' );
function sgc_repair_admin_notice(): void {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $report = get_option( SGC_REPAIR_OPTION );
    if ( ! $report ) return;

    $fixed_count  = count( $report['fixed'] ?? [] );
    $failed_count = count( $report['failed'] ?? [] );

    if ( $fixed_count === 0 && $failed_count === 0 ) return;

    $url = admin_url( 'admin.php?page=sgclabs-auto-repair' );

    if ( $fixed_count > 0 && $failed_count === 0 ) {
        echo '<div class="notice notice-success is-dismissible"><p>
            ✅ <strong>SGC Auto-Repair:</strong> ' . esc_html( $fixed_count ) . ' issue(s) auto-fixed today.
            <a href="' . esc_url( $url ) . '">View Report →</a>
        </p></div>';
    } elseif ( $failed_count > 0 ) {
        echo '<div class="notice notice-error is-dismissible"><p>
            ⚠️ <strong>SGC Auto-Repair:</strong> ' . esc_html( $failed_count ) . ' issue(s) need manual attention.
            <a href="' . esc_url( $url ) . '"><strong>View Report →</strong></a>
        </p></div>';
    }
}


// ╔══════════════════════════════════════════════════════════════════════════════╗
// ║  SECTION 4 — ADMIN PAGE                                                    ║
// ╚══════════════════════════════════════════════════════════════════════════════╝

add_action( 'admin_menu', 'sgc_repair_register_menu', 26 );
function sgc_repair_register_menu(): void {
    add_submenu_page(
        'sgclabs-manager',
        'Auto-Repair',
        '🔧 Auto-Repair',
        'manage_options',
        'sgclabs-auto-repair',
        'sgc_repair_render_page'
    );
}

function sgc_repair_render_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $report    = get_option( SGC_REPAIR_OPTION );
    $last_run  = get_option( 'sgc_auto_repair_last_run' );
    $nonce     = wp_create_nonce( 'sgc_auto_repair_nonce' );
    $ai_data   = get_option( SGC_REPAIR_AI_OPTION );

    $fixed_count   = count( $report['fixed']       ?? [] );
    $failed_count  = count( $report['failed']      ?? [] );
    $skipped_count = count( $report['skipped']     ?? [] );
    $wp_issues     = count( $report['wp_health']   ?? [] );
    $tp_issues     = count( $report['third_party'] ?? [] );
    ?>
    <div class="wrap" style="max-width:960px">

        <!-- Header -->
        <div style="background:#1a1a2e;color:#fff;padding:20px 24px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div>
                <h1 style="color:#fff;margin:0;font-size:22px">🔧 SGC Auto-Repair System</h1>
                <p style="margin:4px 0 0;color:#9090cc;font-size:13px">
                    Daily scan at 6:00 AM IST &nbsp;|&nbsp;
                    <?php echo $last_run ? 'Last run: ' . esc_html( human_time_diff( $last_run ) ) . ' ago' : 'Never run yet'; ?>
                </p>
            </div>
            <button id="sgc-repair-btn"
                style="background:#7c3aed;color:#fff;border:none;padding:10px 22px;border-radius:7px;font-size:14px;font-weight:600;cursor:pointer">
                🔧 Run Full Repair Now
            </button>
        </div>

        <?php if ( $report ) : ?>

        <!-- KPI Cards -->
        <div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap">
            <?php
            $cards = [
                [ $fixed_count,   'Auto-Fixed',        $fixed_count  > 0 ? '#f0fdf4' : '#f8fafc', $fixed_count  > 0 ? '#16a34a' : '#6b7280', $fixed_count  > 0 ? '#bbf7d0' : '#e2e8f0' ],
                [ $failed_count,  'Need Attention',    $failed_count > 0 ? '#fef2f2' : '#f8fafc', $failed_count > 0 ? '#dc2626' : '#6b7280', $failed_count > 0 ? '#fecaca' : '#e2e8f0' ],
                [ $wp_issues,     'WP Health Issues',  $wp_issues    > 0 ? '#fffbeb' : '#f8fafc', $wp_issues    > 0 ? '#d97706' : '#6b7280', $wp_issues    > 0 ? '#fde68a' : '#e2e8f0' ],
                [ $tp_issues,     'Plugin Conflicts',  $tp_issues    > 0 ? '#fffbeb' : '#f8fafc', $tp_issues    > 0 ? '#d97706' : '#6b7280', $tp_issues    > 0 ? '#fde68a' : '#e2e8f0' ],
                [ $skipped_count, 'Skipped',           '#f8fafc', '#6b7280', '#e2e8f0' ],
            ];
            foreach ( $cards as [$val, $label, $bg, $clr, $border] ) : ?>
            <div style="flex:1;min-width:120px;background:<?php echo esc_attr($bg); ?>;border:1px solid <?php echo esc_attr($border); ?>;border-radius:8px;padding:14px;text-align:center">
                <div style="font-size:26px;font-weight:700;color:<?php echo esc_attr($clr); ?>"><?php echo esc_html($val); ?></div>
                <div style="font-size:11px;color:#6b7280;margin-top:3px"><?php echo esc_html($label); ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Auto-Fixed -->
        <?php if ( $fixed_count > 0 ) : ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:16px;overflow:hidden">
            <div style="background:#f0fdf4;padding:12px 18px;border-bottom:1px solid #bbf7d0;font-weight:700;color:#15803d">
                ✅ Auto-Fixed (<?php echo esc_html($fixed_count); ?>)
            </div>
            <?php foreach ( $report['fixed'] as $item ) : ?>
            <div style="padding:10px 18px;border-bottom:1px solid #f3f4f6;font-size:13px;color:#374151">
                <?php echo esc_html($item); ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Need Attention -->
        <?php if ( $failed_count > 0 ) : ?>
        <div style="background:#fff;border:1px solid #fecaca;border-radius:10px;margin-bottom:16px;overflow:hidden">
            <div style="background:#fef2f2;padding:12px 18px;border-bottom:1px solid #fecaca;font-weight:700;color:#991b1b">
                ❌ Need Manual Attention (<?php echo esc_html($failed_count); ?>)
            </div>
            <?php foreach ( $report['failed'] as $item ) : ?>
            <div style="padding:10px 18px;border-bottom:1px solid #f3f4f6;font-size:13px;color:#dc2626">
                <?php echo esc_html($item); ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- WordPress Health -->
        <?php if ( $wp_issues > 0 ) : ?>
        <div style="background:#fff;border:1px solid #fde68a;border-radius:10px;margin-bottom:16px;overflow:hidden">
            <div style="background:#fffbeb;padding:12px 18px;border-bottom:1px solid #fde68a;font-weight:700;color:#92400e">
                ⚠️ WordPress Health (<?php echo esc_html($wp_issues); ?>)
            </div>
            <?php foreach ( $report['wp_health'] as $item ) :
                $icons = [ 'critical' => '🔴', 'warning' => '⚠️', 'info' => 'ℹ️' ];
                $icon  = $icons[ $item['level'] ] ?? 'ℹ️';
            ?>
            <div style="padding:12px 18px;border-bottom:1px solid #f3f4f6">
                <div style="font-size:13px;font-weight:600;color:#1f2937"><?php echo esc_html($icon . ' ' . $item['message']); ?></div>
                <div style="font-size:12px;color:#6b7280;margin-top:3px">👉 <?php echo esc_html($item['action']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Third-party Conflicts -->
        <?php if ( $tp_issues > 0 ) : ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:16px;overflow:hidden">
            <div style="background:#f8fafc;padding:12px 18px;border-bottom:1px solid #e5e7eb;font-weight:700;color:#1a1a2e">
                🔌 Plugin Conflicts (<?php echo esc_html($tp_issues); ?>)
            </div>
            <?php foreach ( $report['third_party'] as $item ) : ?>
            <div style="padding:12px 18px;border-bottom:1px solid #f3f4f6">
                <div style="font-size:13px;font-weight:600;color:#1f2937">⚠️ <?php echo esc_html($item['name']); ?></div>
                <div style="font-size:12px;color:#6b7280;margin-top:2px"><?php echo esc_html($item['message']); ?></div>
                <div style="font-size:12px;color:#3b82f6;margin-top:2px">👉 <?php echo esc_html($item['action']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- AI Analysis -->
        <?php if ( ! empty( $report['ai_analysis'] ) && $report['ai_analysis'] !== 'No significant errors in recent log.' ) : ?>
        <div style="background:#faf5ff;border:1px solid #e9d5ff;border-radius:10px;margin-bottom:16px;padding:18px">
            <div style="font-weight:700;color:#6d28d9;margin-bottom:10px">🤖 AI Error Log Analysis</div>
            <pre style="font-size:12px;color:#374151;white-space:pre-wrap;margin:0;font-family:monospace"><?php echo esc_html( $report['ai_analysis'] ); ?></pre>
            <?php if ( $ai_data ) : ?>
            <div style="font-size:11px;color:#9ca3af;margin-top:8px">Analyzed: <?php echo esc_html( $ai_data['time'] ?? '' ); ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- All Checks Passed -->
        <?php if ( $fixed_count === 0 && $failed_count === 0 && $wp_issues === 0 && $tp_issues === 0 ) : ?>
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:30px;text-align:center;margin-bottom:16px">
            <div style="font-size:32px">✅</div>
            <div style="font-size:15px;font-weight:700;color:#15803d;margin-top:8px">All systems healthy!</div>
            <div style="font-size:13px;color:#6b7280;margin-top:4px">No issues found in last scan.</div>
        </div>
        <?php endif; ?>

        <!-- Info Details (collapsed) -->
        <details style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 18px;margin-bottom:16px">
            <summary style="cursor:pointer;font-weight:600;color:#374151;font-size:13px">
                📋 All Checks (<?php echo esc_html(count($report['info'] ?? [])); ?> passed)
            </summary>
            <div style="margin-top:10px;columns:2;column-gap:20px">
                <?php foreach ( $report['info'] as $item ) : ?>
                <div style="font-size:11px;color:#6b7280;margin:2px 0;break-inside:avoid"><?php echo esc_html($item); ?></div>
                <?php endforeach; ?>
            </div>
        </details>

        <?php else : ?>
        <div style="background:#f8fafc;border:2px dashed #d1d5db;border-radius:10px;padding:40px;text-align:center">
            <div style="font-size:40px">🔧</div>
            <div style="font-size:15px;font-weight:600;color:#374151;margin-top:10px">No scan run yet</div>
            <div style="font-size:13px;color:#6b7280;margin-top:6px">Click "Run Full Repair Now" to start.</div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Result toast -->
    <div id="sgc-repair-result" style="display:none;position:fixed;bottom:20px;right:20px;background:#1a1a2e;color:#fff;padding:14px 20px;border-radius:10px;z-index:9999;font-size:13px;max-width:340px;box-shadow:0 4px 20px rgba(0,0,0,0.3)"></div>

    <script>
    document.getElementById('sgc-repair-btn').addEventListener('click', function() {
        var btn = this;
        btn.disabled = true;
        btn.textContent = '⏳ Running scan...';

        var fd = new FormData();
        fd.append('action', 'sgc_run_auto_repair');
        fd.append('nonce', '<?php echo esc_js($nonce); ?>');

        fetch(ajaxurl, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(function(res) {
            btn.disabled = false;
            btn.textContent = '🔧 Run Full Repair Now';
            if (res.success) {
                var d = res.data;
                var msg = '✅ Fixed: ' + d.fixed + ' | ❌ Failed: ' + d.failed + ' | ⏭ Skipped: ' + d.skipped;
                document.getElementById('sgc-repair-result').textContent = msg;
                document.getElementById('sgc-repair-result').style.display = 'block';
                setTimeout(function() { location.reload(); }, 2000);
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = '🔧 Run Full Repair Now';
            document.getElementById('sgc-repair-result').textContent = '❌ Network error. Try again.';
            document.getElementById('sgc-repair-result').style.display = 'block';
        });
    });
    </script>
    <?php
}


// ── Cleanup on deactivation ───────────────────────────────────────────────────
register_deactivation_hook(
    defined('SGCLABS_PATH') ? SGCLABS_PATH . 'sgclabs-manager.php' : __FILE__,
    function() {
        $ts = wp_next_scheduled( SGC_REPAIR_CRON );
        if ( $ts ) wp_unschedule_event( $ts, SGC_REPAIR_CRON );
    }
);
