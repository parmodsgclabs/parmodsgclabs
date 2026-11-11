<?php
/**
 * Plugin Name:       SGC Invoice & Packlist Pro
 * Plugin URI:        https://labsglass.com/plugins/sgc-invoice-packlist-pro/
 * Description:       Complete B2B export pipeline — Proforma Invoice, Packing List, Payment Register, Kanban Pipeline, Buyer Self-Service Portal, AI Compose, WhatsApp, and CRM sync. Built for international laboratory glassware exporters.
 * Version:           1.10.2
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Tested up to:      7.0
 * Author:            SGC LABS®
 * Author URI:        https://labsglass.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sgc-iip
 * Domain Path:       /languages
 * Network:           false
 *
 * @package    SGC_IIP
 * @copyright  2024-2026 SGC LABS®
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

defined( 'ABSPATH' ) || exit( 'Direct access not permitted.' );
if ( defined( 'SGC_IIP_VERSION' ) ) return;

// ── Version & Build constants ─────────────────────────────────────────────────
define( 'SGC_IIP_VERSION',    '1.10.2' );
define( 'SGC_IIP_BUILD_DATE', '2026-11-10' );
define( 'SGC_IIP_BUILD_TIME', '18:30' );
define( 'SGC_IIP_BUILD_ID',   '20261110-1830' );
define( 'SGC_IIP_BUILD',      '2026-11-10 18:30 UTC' );
define( 'SGC_IIP_MIN_WP',     '6.0' );
define( 'SGC_IIP_MIN_PHP',    '8.0' );
define( 'SGC_IIP_SLUG',       'sgc-iip' );
define( 'SGC_IIP_TEXTDOMAIN', 'sgc-iip' );

// ── Path / URL constants ──────────────────────────────────────────────────────
define( 'SGC_IIP_FILE',     __FILE__ );
define( 'SGC_IIP_PATH',     plugin_dir_path( __FILE__ ) );
define( 'SGC_IIP_URL',      plugin_dir_url( __FILE__ ) );
define( 'SGC_IIP_BASE',     plugin_basename( __FILE__ ) );
define( 'SGC_IIP_ASSETS',   SGC_IIP_URL . 'assets/' );

// ── PHP version guard ─────────────────────────────────────────────────────────
if ( version_compare( PHP_VERSION, SGC_IIP_MIN_PHP, '<' ) ) {
    add_action( 'admin_notices', function () {
        printf(
            '<div class="notice notice-error"><p><strong>SGC Invoice &amp; Packlist Pro</strong> requires PHP %s or higher. You are running PHP %s. Please upgrade PHP.</p></div>',
            esc_html( SGC_IIP_MIN_PHP ), esc_html( PHP_VERSION )
        );
    } );
    return;
}

// ── i18n ──────────────────────────────────────────────────────────────────────
add_action( 'init', function () {
    load_plugin_textdomain( SGC_IIP_TEXTDOMAIN, false, dirname( SGC_IIP_BASE ) . '/languages/' );
}, 1 );

// ── WooCommerce feature compatibility (v1.9.3: HPOS + Cart/Checkout Blocks) ────────────────
// Without this WooCommerce lists the plugin as "incompatible" when High-Performance Order Storage is on.
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
    }
} );

/**
 * Changelog (newest first)
 * v1.10.2 — 2026-11-11: MySQL fix — table schema COMMENT clauses were on separate lines, which made dbDelta emit invalid
 *           ALTER TABLE statements on every upgrade (errors in debug.log on MySQL/MariaDB; harmless on SQLite)
 * v1.10.1 — 2026-11-11: Quote cart inquiries arrive with one line per product (name, SKU, qty, link) + buyer type;
 *           auto-reply is sent once per inquiry (Smart CRM AI reply first, template reply only as fallback)
 * v1.10.0 — 2026-11-11: MODULE CONTROL + automatic safe mode (SGC LABS → Module Control)
 * v1.9.3 — 2026-11-10: STABILITY RELEASE
 *           - FIX fatal: Smart Quote Templates called get_payment_terms()/get_bank_details() that did not exist
 *           - FIX fatal: Buyer Portal + Pipeline reminder called a private email wrapper (SGC_IIP_Email::wrap)
 *           - FIX fatal: WooCommerce product search in PI editor passed WP_Post objects to the country-pricing engine
 *           - FIX: Compose tab JavaScript was broken by an unescaped apostrophe (Sir/Ma'am) — whole tab script failed to load
 *           - FIX: Site Manager inquiries were inserted with wrong column names (message/source) and silently failed;
 *                  now routed through the full pipeline (health score, currency, auto-reply, CRM hook)
 *           - SECURITY: removed priority-1 intercepts of Site Manager form actions that stored inquiries
 *                  before nonce / rate-limit / validation ran (anonymous spam vector + duplicate rows)
 *           - FIX: AI inquiry classifier listener was registered after the event fired (never ran)
 *           - FIX: PI save returned "array offset on null" when the PI id was invalid
 *           - Removed leftover install-dompdf.sh / empty lib folder (PDF engine is pure PHP since v1.0.3)
 * v1.1.5 — 2026-04-23: COMPOSE EMAIL FEATURE — New "✉ Compose Email" tab:
 *           AI Polish (converts Hinglish/rough text → professional English HTML email),
 *           5 tone modes (Professional/Friendly/Formal/Follow-up/Quotation),
 *           multi-file attachment support up to 100MB (PDF, Excel, images, ZIP),
 *           drag-and-drop file zone, live side preview, full iframe preview modal,
 *           auto BCC to company email (info@labsglass.com), direct editable body,
 *           branded SGC LABS® email template on all compose emails.

/**
 * Changelog (newest first)
 * v1.0.8 — 2026-04-19: INTEGRATION UPDATE — All 4 SGC plugins connected:
 *           1. AI keys fallback: Invoice Pro → Manager Pro keys auto-shared
 *           2. USD rate sync: Product Manager Pro rate auto-used
 *           3. Manager Pro sync: exact field mapping (company/incoterm/port)
 *           4. CRM bridge: correct fields (first_name/last_name/segment/tags/source)
 *           5. Product search: B2B price from Product Manager Pro shown
 * v1.0.7 — 2026-04-19: Signature+Stamp image embed in PDF (GD library),
 *           Bill To/Ship To 3-col PDF layout, GST auto-logic (export=zero-rated,
 *           domestic=IGST/CGST+SGST), HSN column in line items, PayPal+Wise+UPI
 *           in PDF payment section, buyer phone in PI.
 * v1.0.6 — 2026-04-19: PayPal+Wise toggle settings, GPay UPI QR in email,
 *           Bill To/Ship To fields in PI editor, buyer phone, payment method select.
 * v1.0.5 — 2026-04-19: Manual PI editor, GST field, Freight charges, WooCommerce sync,
 *           WC product search, PDF margin fix, number truncation fix, SWIFT/IFSC logic,
 *           domestic vs export email logic, standalone PI accept page.
 * v1.0.4 — 2026-04-18: Email fixes — INR for domestic, IFSC vs SWIFT, Sales/Export team.
 * v1.0.3 — 2026-04-18: Pure PHP PDF engine (no Dompdf), Manager Pro sync, one-click PI send.
 * v1.0.2 — 2026-04-18: Manager Pro compatibility, WC order fetch foundation.
 * v1.0.1 — 2026-04-18: Hook priority fix (1 vs 20) — Manager Pro wp_send_json die() bypass.
 * v1.0.0 — 2026-04-18: Initial release. Full inquiry pipeline, AI PI, PDF, WhatsApp, pipeline.
 */

// ── Module Guard (v1.10.0): per-module on/off switches + automatic safe mode ─────────
require_once SGC_IIP_PATH . 'includes/sgc-module-guard.php';
SGC_Module_Guard::register( 'sgc-invoice-packlist-pro', 'SGC Invoice & Packlist Pro', SGC_IIP_PATH, [
    'class-db'              => 'Database layer',
    'class-hooks'           => 'Inquiries core (forms, AJAX, Site Manager sync)',
    'class-ai'              => 'AI (PI drafts, polish, classify)',
    'class-currency'        => 'Currency & exchange rates',
    'class-email'           => 'Email (PI send, auto-reply, compose)',
    'class-whatsapp'        => 'WhatsApp',
    'class-gst'             => 'GST engine',
    'class-country-pricing' => 'Country-wise export pricing',
    'class-proforma'        => 'Proforma Invoice',
    'class-packlist'        => 'Packing list',
    'class-pdf'             => 'PDF engine',
    'class-pipeline'        => 'Pipeline (Kanban, payments)',
    'class-followup'        => 'Follow-up & balance reminders',
    'class-buyer-portal'    => 'Buyer self-service portal',
    'class-crm-bridge'      => 'CRM bridge',
    'class-settings'        => 'Settings',
    'class-automation'      => 'Full automation',
    'class-templates'       => 'Smart quote templates',
    'class-inbox'           => 'Email inbox (IMAP)',
    'class-admin'           => 'Admin UI',
], [ 'class-db', 'class-hooks', 'class-settings', 'class-admin' ] );

// ── Autoload includes ─────────────────────────────────────────────────────
add_action( 'plugins_loaded', function () {
    $files = [
        'includes/class-db.php',
        'includes/class-hooks.php',
        'includes/class-ai.php',
        'includes/class-currency.php',
        'includes/class-email.php',
        'includes/class-whatsapp.php',
        'includes/class-gst.php',              // v1.8.5: GST engine
        'includes/class-country-pricing.php',  // v1.8.5: Country-wise export pricing
        'includes/class-proforma.php',
        'includes/class-packlist.php',
        'includes/class-pdf.php',
        'includes/class-pipeline.php',
        'includes/class-followup.php',
        'includes/class-buyer-portal.php',
        'includes/class-crm-bridge.php',
        'includes/class-settings.php',
        'includes/class-automation.php',    // ★ Full Automation v1.0.0
        'includes/class-templates.php',     // ★ Smart Quote Templates v1.0.0
        'includes/class-inbox.php',         // ★ Email Inbox v1.9.0
    ];
    foreach ( $files as $f ) {
        $p = SGC_IIP_PATH . $f;
        if ( ! SGC_Module_Guard::should_load( 'sgc-invoice-packlist-pro', SGC_Module_Guard::key_from_path( 'sgc-invoice-packlist-pro', $p ) ) ) continue; // Module Control
        if ( file_exists( $p ) ) require_once $p;
    }
    if ( is_admin() ) {
        require_once SGC_IIP_PATH . 'includes/class-admin.php';
    }
}, 20 );

// ── Buyer Portal — rewrite + query vars + render ───────────────────────────
add_action( 'init',              [ 'SGC_IIP_Buyer_Portal', 'register_rewrite' ] );
add_filter( 'query_vars',        [ 'SGC_IIP_Buyer_Portal', 'add_query_vars' ] );
add_action( 'template_redirect', [ 'SGC_IIP_Buyer_Portal', 'maybe_render_portal' ] );

// ── Balance Reminder — daily cron ─────────────────────────────────────────
add_action( 'sgc_iip_balance_reminder_check', [ 'SGC_IIP_Followup', 'run_balance_reminder_check' ] );


// ── Assets ───────────────────────────────────────────────────────────────
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( strpos( $hook, 'sgc-iip' ) === false &&
         strpos( $hook, 'sgclabs-manager' ) === false ) return;

    wp_enqueue_media(); // For QR image upload in settings

    wp_enqueue_style(
        'sgc-iip-style',
        SGC_IIP_URL . 'assets/css/style.css',
        [], SGC_IIP_VERSION
    );
    wp_enqueue_script(
        'sgc-iip-app',
        SGC_IIP_URL . 'assets/js/app.js',
        [ 'jquery' ], SGC_IIP_VERSION, true
    );
    // v1.9.0: Email Inbox assets
    wp_enqueue_style(
        'sgc-iip-inbox',
        SGC_IIP_URL . 'assets/css/inbox.css',
        [ 'sgc-iip-style' ], SGC_IIP_VERSION
    );
    wp_enqueue_script(
        'sgc-iip-inbox',
        SGC_IIP_URL . 'assets/js/inbox.js',
        [ 'jquery', 'sgc-iip-app' ], SGC_IIP_VERSION, true
    );
    wp_localize_script( 'sgc-iip-app', 'SGCIIP', array(
        'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
        'nonce'     => wp_create_nonce( 'sgc_iip_nonce' ),
        'pluginUrl' => SGC_IIP_URL,
        'currency'  => get_woocommerce_currency_symbol(),
        'version'   => SGC_IIP_VERSION,
        'adminEmail'=> get_option( 'sgc_iip_admin_email', get_option( 'admin_email' ) ),
    ) );
} );

// ── Activation ───────────────────────────────────────────────────────────
register_activation_hook( __FILE__, 'sgc_iip_activate' );
function sgc_iip_activate() {
    require_once SGC_IIP_PATH . 'includes/class-db.php';
    SGC_IIP_DB::create_tables();
    SGC_IIP_DB::set_defaults();
    SGC_IIP_DB::seed_hs_codes();

    if ( ! wp_next_scheduled( 'sgc_iip_followup_cron' ) )
        wp_schedule_event( time(), 'hourly', 'sgc_iip_followup_cron' );

    if ( ! wp_next_scheduled( 'sgc_iip_rate_refresh' ) )
        wp_schedule_event( time(), 'daily', 'sgc_iip_rate_refresh' );

    // v1.3.0: Balance payment reminder daily cron
    if ( ! wp_next_scheduled( 'sgc_iip_balance_reminder_check' ) )
        wp_schedule_event( time(), 'daily', 'sgc_iip_balance_reminder_check' );

    // v1.9.0: Email Inbox poll cron
    if ( class_exists( 'SGC_IIP_Inbox' ) ) {
        SGC_IIP_Inbox::schedule();
    }

    flush_rewrite_rules();
    update_option( 'sgc_iip_activated_at', current_time('mysql') );
}

register_deactivation_hook( __FILE__, 'sgc_iip_deactivate' );
function sgc_iip_deactivate() {
    wp_clear_scheduled_hook( 'sgc_iip_balance_reminder_check' );
    wp_clear_scheduled_hook( 'sgc_iip_inbox_poll' ); // v1.9.0
    flush_rewrite_rules();
}

// ── DB upgrade on each version update (adds new columns safely) ───────────
add_action( 'plugins_loaded', function () {
    $installed = get_option( 'sgc_iip_db_version', '0' );
    if ( version_compare( $installed, SGC_IIP_VERSION, '<' ) ) {
        require_once SGC_IIP_PATH . 'includes/class-db.php';
        SGC_IIP_DB::create_tables(); // dbDelta safely adds missing columns
        update_option( 'sgc_iip_db_version', SGC_IIP_VERSION );
    }
} );

// ── v1.2.1: CSV export handlers (GET-based, nonce protected) ─────────────
add_action( 'admin_post_sgc_iip_export_pi_csv',  [ 'SGC_IIP_Proforma', 'ajax_export_csv' ] );
add_action( 'admin_post_sgc_iip_export_pl_csv',  [ 'SGC_IIP_Packlist', 'ajax_export_csv' ] );
add_action( 'admin_post_sgc_iip_export_inq_csv', [ 'SGC_IIP_Hooks',    'ajax_export_inquiries_csv' ] );

// ── Deactivation ─────────────────────────────────────────────────────────
register_deactivation_hook( __FILE__, function () {
    wp_clear_scheduled_hook( 'sgc_iip_followup_cron' );
    wp_clear_scheduled_hook( 'sgc_iip_rate_refresh' );
} );

// ── Cron hooks ───────────────────────────────────────────────────────────
add_action( 'sgc_iip_followup_cron',  [ 'SGC_IIP_Followup',  'run_pending'     ] );
add_action( 'sgc_iip_rate_refresh',   [ 'SGC_IIP_Currency',  'refresh_rates'   ] );

// ── Plugin action links ───────────────────────────────────────────────────
add_filter( 'plugin_action_links_' . SGC_IIP_BASE, function ( $links ) {
    array_unshift(
        $links,
        '<a href="' . admin_url( 'admin.php?page=sgc-iip' ) . '">Open</a>'
    );
    return $links;
} );
