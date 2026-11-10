<?php
/**
 * SGC LABS® — Universal Social Share Bar
 *
 * Single share bar covering both B2B and B2C audiences. Twelve platforms total,
 * each with brand-correct color and SVG icon. Admin chooses which to enable;
 * sensible defaults for a global export business serving consumers + dealers.
 *
 * Default ENABLED:
 *   Email, WhatsApp, Copy Link        ← universal essentials
 *   LinkedIn, Telegram                 ← B2B
 *   Facebook, X (Twitter), Pinterest   ← B2C
 *   Instagram                          ← (caption + image copy mode — IG has no URL share API)
 *
 * Default DISABLED (region-specific, admin opts in):
 *   WeChat (QR modal), LINE, VK, Facebook Messenger
 *
 * Architecture notes:
 *   - This module COEXISTS with the existing wa-share.php WhatsApp module.
 *     If both are active and both render WhatsApp, you get duplicates — so
 *     this module checks `sgclabs_wa_share_active` and skips the WhatsApp
 *     button when the older module is rendering it. Admin can toggle either
 *     module from the Module Browser.
 *   - All shares are pure URL redirects (no JS API keys, no third-party
 *     trackers). Mobile-first.
 *
 * @package SGCLabs
 * @since   v6.9.6
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ──────────────────────────────────────────────────────────────────────
// Defaults
// ──────────────────────────────────────────────────────────────────────
function sgclabs_social_share_defaults(): array {
    return [
        'sgclabs_social_share_active'   => '1',
        'sgclabs_social_share_position' => 'after_cart',  // after_cart | before_cart | after_meta | floating
        'sgclabs_social_share_label'    => 'Share this product:',
        'sgclabs_social_share_template' => 'Check out this product from SGC LABS®: {title} — {url}',
        'sgclabs_social_share_size'     => 'medium',      // small | medium | large
        'sgclabs_social_share_style'    => 'pill',        // pill | square | minimal

        // Per-platform on/off (defaults match the dual B2B+B2C strategy)
        'sgclabs_share_email'      => '1',
        'sgclabs_share_whatsapp'   => '1',
        'sgclabs_share_copy'       => '1',
        'sgclabs_share_linkedin'   => '1',
        'sgclabs_share_telegram'   => '1',
        'sgclabs_share_facebook'   => '1',
        'sgclabs_share_x'          => '1',
        'sgclabs_share_pinterest'  => '1',
        'sgclabs_share_instagram'  => '1',
        'sgclabs_share_wechat'     => '0',
        'sgclabs_share_line'       => '0',
        'sgclabs_share_vk'         => '0',
        'sgclabs_share_messenger'  => '0',
    ];
}

// ──────────────────────────────────────────────────────────────────────
// Save settings handler
// ──────────────────────────────────────────────────────────────────────
add_action( 'admin_post_sgclabs_save_social_share', 'sgclabs_save_social_share_settings' );
function sgclabs_save_social_share_settings() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
    check_admin_referer( 'sgclabs_social_share_nonce' );
    $d    = sgclabs_social_share_defaults();
    $bool = [
        'sgclabs_social_share_active',
        'sgclabs_share_email','sgclabs_share_whatsapp','sgclabs_share_copy',
        'sgclabs_share_linkedin','sgclabs_share_telegram',
        'sgclabs_share_facebook','sgclabs_share_x','sgclabs_share_pinterest','sgclabs_share_instagram',
        'sgclabs_share_wechat','sgclabs_share_line','sgclabs_share_vk','sgclabs_share_messenger',
    ];
    foreach ( array_keys( $d ) as $k ) {
        if ( in_array( $k, $bool, true ) ) {
            update_option( $k, isset( $_POST[ $k ] ) ? '1' : '0' );
        } elseif ( $k === 'sgclabs_social_share_template' ) {
            update_option( $k, sanitize_textarea_field( wp_unslash( $_POST[ $k ] ?? $d[ $k ] ) ) );
        } else {
            update_option( $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ?? $d[ $k ] ) ) );
        }
    }
    wp_safe_redirect( admin_url( 'admin.php?page=sgclabs-site-tools-hub&tab=social-share&saved=1' ) );
    exit;
}

// ──────────────────────────────────────────────────────────────────────
// Frontend hook registration
// ──────────────────────────────────────────────────────────────────────
add_action( 'wp', 'sgclabs_social_share_init' );
function sgclabs_social_share_init() {
    if ( get_option( 'sgclabs_social_share_active', '1' ) !== '1' ) return;
    if ( ! function_exists( 'is_product' ) ) return;

    $position = get_option( 'sgclabs_social_share_position', 'after_cart' );
    $hook_map = [
        'before_cart' => 'woocommerce_before_add_to_cart_button',
        'after_cart'  => 'woocommerce_after_add_to_cart_button',
        'after_meta'  => 'woocommerce_product_meta_end',
        'after_desc'  => 'woocommerce_after_single_product_summary',
        'floating'    => 'wp_footer',
    ];
    $hook = $hook_map[ $position ] ?? 'woocommerce_after_add_to_cart_button';
    add_action( $hook, 'sgclabs_social_share_render', 20 );
    add_action( 'wp_footer', 'sgclabs_social_share_assets', 6 );
}

// ──────────────────────────────────────────────────────────────────────
// SVG icons — all platform-correct brand colors and shapes
// ──────────────────────────────────────────────────────────────────────
function sgclabs_social_share_platforms(): array {
    return [
        'email' => [
            'label'    => 'Email',
            'color'    => '#5f6368',
            'tier'     => 'essential',
            'icon'     => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>',
        ],
        'whatsapp' => [
            'label'    => 'WhatsApp',
            'color'    => '#25D366',
            'tier'     => 'essential',
            'icon'     => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>',
        ],
        'copy' => [
            'label'    => 'Copy Link',
            'color'    => '#475569',
            'tier'     => 'essential',
            'icon'     => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg>',
        ],
        'linkedin' => [
            'label'    => 'LinkedIn',
            'color'    => '#0A66C2',
            'tier'     => 'b2b',
            'icon'     => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.063 2.063 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',
        ],
        'telegram' => [
            'label'    => 'Telegram',
            'color'    => '#26A5E4',
            'tier'     => 'b2b',
            'icon'     => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>',
        ],
        'facebook' => [
            'label'    => 'Facebook',
            'color'    => '#1877F2',
            'tier'     => 'b2c',
            'icon'     => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
        ],
        'x' => [
            'label'    => 'X (Twitter)',
            'color'    => '#000000',
            'tier'     => 'b2c',
            'icon'     => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
        ],
        'pinterest' => [
            'label'    => 'Pinterest',
            'color'    => '#BD081C',
            'tier'     => 'b2c',
            'icon'     => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.162-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.099.12.112.225.085.345-.09.375-.293 1.199-.334 1.363-.053.225-.172.271-.402.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.357-.629-2.748-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24.009 12.017 24c6.624 0 11.99-5.367 11.99-11.987C24.007 5.367 18.641.001 12.017.001z"/></svg>',
        ],
        'instagram' => [
            'label'    => 'Instagram',
            'color'    => '#E4405F',
            'tier'     => 'b2c',
            'icon'     => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zM5.838 12a6.162 6.162 0 1112.324 0 6.162 6.162 0 01-12.324 0zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>',
        ],
        'wechat' => [
            'label'    => 'WeChat',
            'color'    => '#07C160',
            'tier'     => 'regional',
            'icon'     => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M8.691 2.188C3.891 2.188 0 5.476 0 9.53c0 2.212 1.17 4.203 3.002 5.55a.59.59 0 01.213.665l-.39 1.48c-.019.07-.048.141-.048.213 0 .163.13.295.29.295a.326.326 0 00.167-.054l1.903-1.114a.864.864 0 01.717-.098 10.16 10.16 0 002.837.403c.276 0 .543-.027.811-.05-.857-2.578.157-4.972 1.932-6.446 1.703-1.415 3.882-1.98 5.853-1.838-.576-3.583-4.196-6.348-8.596-6.348zM5.785 5.991c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 01-1.162 1.178A1.17 1.17 0 014.623 7.17c0-.651.52-1.18 1.162-1.18zm5.813 0c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 01-1.162 1.178 1.17 1.17 0 01-1.162-1.178c0-.651.52-1.18 1.162-1.18zm5.34 2.867c-1.797-.052-3.746.512-5.28 1.786-1.72 1.428-2.687 3.72-1.78 6.22.942 2.453 3.666 4.229 6.884 4.229.826 0 1.622-.12 2.361-.336a.722.722 0 01.598.082l1.584.926a.272.272 0 00.14.047c.134 0 .24-.111.24-.247 0-.06-.023-.12-.038-.177l-.327-1.233a.582.582 0 01.182-.555c1.526-1.124 2.499-2.781 2.499-4.622 0-3.354-3.099-6.073-6.954-6.12zm-2.495 2.564c.468 0 .851.388.851.866a.857.857 0 01-.851.866.857.857 0 01-.851-.866c0-.478.383-.866.851-.866zm4.66 0c.468 0 .851.388.851.866a.857.857 0 01-.851.866.857.857 0 01-.851-.866c0-.478.383-.866.851-.866z"/></svg>',
        ],
        'line' => [
            'label'    => 'LINE',
            'color'    => '#06C755',
            'tier'     => 'regional',
            'icon'     => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016a.63.63 0 0 1-.631.629.628.628 0 0 1-.51-.262l-2.443-3.317v2.95a.63.63 0 0 1-1.257 0V8.108a.627.627 0 0 1 .624-.628c.195 0 .375.103.495.27l2.462 3.33V8.108a.629.629 0 0 1 1.26 0v4.771zm-5.741 0a.629.629 0 0 1-.626.629.627.627 0 0 1-.626-.629V8.108a.62.62 0 0 1 .626-.628c.346 0 .626.285.626.628v4.771zm-2.466.629H4.917a.63.63 0 0 1-.63-.629V8.108c0-.345.282-.63.63-.63.346 0 .627.285.627.63v4.141h1.756a.63.63 0 0 1 .627.628c0 .344-.281.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/></svg>',
        ],
        'vk' => [
            'label'    => 'VK',
            'color'    => '#0077FF',
            'tier'     => 'regional',
            'icon'     => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M15.684 0H8.316C1.592 0 0 1.592 0 8.316v7.368C0 22.408 1.592 24 8.316 24h7.368C22.408 24 24 22.408 24 15.684V8.316C24 1.592 22.391 0 15.684 0zm3.692 17.123h-1.744c-.66 0-.864-.525-2.05-1.727-1.033-1-1.49-1.135-1.744-1.135-.356 0-.458.102-.458.593v1.575c0 .424-.135.677-1.253.677-1.846 0-3.896-1.118-5.337-3.202C4.604 10.857 4.012 8.553 4.012 8.079c0-.254.102-.491.593-.491h1.744c.44 0 .61.203.78.677.863 2.49 2.303 4.675 2.896 4.675.22 0 .322-.102.322-.66V9.721c-.068-1.186-.695-1.287-.695-1.71 0-.204.17-.407.44-.407h2.744c.373 0 .508.203.508.643v3.473c0 .372.17.508.27.508.22 0 .407-.136.813-.542 1.254-1.406 2.151-3.574 2.151-3.574.119-.254.322-.491.763-.491h1.744c.525 0 .644.27.525.643-.22 1.017-2.354 4.031-2.354 4.031-.186.305-.254.44 0 .78.186.254.796.78 1.203 1.253.745.847 1.32 1.558 1.473 2.05.17.49-.085.744-.576.744z"/></svg>',
        ],
        'messenger' => [
            'label'    => 'Messenger',
            'color'    => '#0084FF',
            'tier'     => 'regional',
            'icon'     => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M.001 11.639C.001 4.949 5.367 0 12 0s11.999 4.95 11.999 11.639c0 6.689-5.367 11.638-12 11.638-1.19 0-2.336-.16-3.413-.46a.961.961 0 00-.642.046l-2.382 1.05a.96.96 0 01-1.348-.85l-.065-2.134a.957.957 0 00-.322-.684C1.901 18.18.001 15.121.001 11.639zm8.32-2.19l-3.525 5.595c-.337.535.318 1.14.823.76L9.43 13.13a.732.732 0 01.882-.003l2.81 2.107a1.808 1.808 0 002.616-.481l3.525-5.594c.337-.535-.318-1.139-.823-.76l-3.81 2.871a.732.732 0 01-.882.003l-2.81-2.107a1.808 1.808 0 00-2.616.481z"/></svg>',
        ],
    ];
}

// ──────────────────────────────────────────────────────────────────────
// Render share bar on product page
// ──────────────────────────────────────────────────────────────────────
function sgclabs_social_share_render() {
    if ( ! function_exists( 'is_product' ) || ! is_product() ) return;
    global $product;
    if ( ! $product ) return;

    $url       = get_permalink( $product->get_id() );
    $title     = $product->get_name();
    $img       = wp_get_attachment_url( $product->get_image_id() );
    $desc      = wp_strip_all_tags( $product->get_short_description() );
    $template  = get_option( 'sgclabs_social_share_template', 'Check out this product from SGC LABS®: {title} — {url}' );
    $share_msg = strtr( $template, [ '{title}' => $title, '{url}' => $url ] );

    $size  = get_option( 'sgclabs_social_share_size', 'medium' );
    $style = get_option( 'sgclabs_social_share_style', 'pill' );
    $label = get_option( 'sgclabs_social_share_label', 'Share this product:' );

    // Don't double-render WhatsApp if the legacy module is also active.
    $skip_wa = ( get_option( 'sgclabs_wa_share_active', '1' ) === '1' );

    $platforms = sgclabs_social_share_platforms();

    // Build per-platform share URL
    $share_urls = [
        'email'     => 'mailto:?subject=' . rawurlencode( $title ) . '&body=' . rawurlencode( $share_msg ),
        'whatsapp'  => 'https://wa.me/?text=' . rawurlencode( $share_msg ),
        'copy'      => '#',
        'linkedin'  => 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode( $url ),
        'telegram'  => 'https://t.me/share/url?url=' . rawurlencode( $url ) . '&text=' . rawurlencode( $title ),
        'facebook'  => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $url ),
        'x'         => 'https://twitter.com/intent/tweet?url=' . rawurlencode( $url ) . '&text=' . rawurlencode( $title ),
        'pinterest' => 'https://pinterest.com/pin/create/button/?url=' . rawurlencode( $url ) . '&description=' . rawurlencode( $title ) . ( $img ? '&media=' . rawurlencode( $img ) : '' ),
        'instagram' => '#',  // IG has no URL share API — handled via copy-caption modal
        'wechat'    => '#',  // QR modal
        'line'      => 'https://social-plugins.line.me/lineit/share?url=' . rawurlencode( $url ) . '&text=' . rawurlencode( $title ),
        'vk'        => 'https://vk.com/share.php?url=' . rawurlencode( $url ) . '&title=' . rawurlencode( $title ),
        'messenger' => 'fb-messenger://share/?link=' . rawurlencode( $url ),
    ];

    // Determine which buttons to show
    $enabled = [];
    foreach ( $platforms as $key => $p ) {
        if ( $key === 'whatsapp' && $skip_wa ) continue; // legacy module renders it
        if ( get_option( 'sgclabs_share_' . $key, $key === 'wechat' || $key === 'line' || $key === 'vk' || $key === 'messenger' ? '0' : '1' ) === '1' ) {
            $enabled[ $key ] = $p;
        }
    }
    if ( empty( $enabled ) ) return;

    $size_px  = $size === 'small' ? 28 : ( $size === 'large' ? 44 : 36 );
    $icon_pad = $size === 'small' ? 6  : ( $size === 'large' ? 12 : 9 );
    $radius   = $style === 'square' ? 6 : ( $style === 'minimal' ? 0 : 999 );

    ?>
    <div class="sgc-share-bar" data-style="<?php echo esc_attr( $style ); ?>"
         data-product-url="<?php echo esc_attr( $url ); ?>"
         data-product-title="<?php echo esc_attr( $title ); ?>"
         data-product-img="<?php echo esc_attr( $img ); ?>"
         data-share-msg="<?php echo esc_attr( $share_msg ); ?>"
         style="margin:18px 0;padding:14px 16px;background:#f8f9fb;border:1px solid #e5e7eb;border-radius:10px;">
        <?php if ( ! empty( $label ) ) : ?>
            <div style="font-size:12px;color:#475569;font-weight:600;margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px;">
                <?php echo esc_html( $label ); ?>
            </div>
        <?php endif; ?>
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <?php foreach ( $enabled as $key => $p ) :
                $href     = $share_urls[ $key ] ?? '#';
                $is_modal = in_array( $key, [ 'copy', 'instagram', 'wechat' ], true );
                ?>
                <a href="<?php echo esc_url( $href ); ?>"
                   <?php echo $is_modal ? 'data-sgc-share="' . esc_attr( $key ) . '"' : 'target="_blank" rel="noopener noreferrer"'; ?>
                   title="<?php echo esc_attr( 'Share on ' . $p['label'] ); ?>"
                   aria-label="<?php echo esc_attr( 'Share on ' . $p['label'] ); ?>"
                   class="sgc-share-btn sgc-share-<?php echo esc_attr( $key ); ?>"
                   style="display:inline-flex;align-items:center;justify-content:center;
                       width:<?php echo esc_attr( $size_px ); ?>px;height:<?php echo esc_attr( $size_px ); ?>px;
                       padding:<?php echo esc_attr( $icon_pad ); ?>px;
                       background:<?php echo esc_attr( $p['color'] ); ?>;color:#fff;
                       border-radius:<?php echo esc_attr( $radius ); ?>px;
                       text-decoration:none;transition:transform .15s,box-shadow .15s,filter .15s;
                       box-shadow:0 1px 2px rgba(0,0,0,.08);">
                    <span style="display:flex;width:100%;height:100%;align-items:center;justify-content:center;">
                        <?php echo $p['icon']; // SVG, safe ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

// ──────────────────────────────────────────────────────────────────────
// Inline JS — copy to clipboard, Instagram caption modal, WeChat QR modal
// ──────────────────────────────────────────────────────────────────────
function sgclabs_social_share_assets() {
    if ( ! function_exists( 'is_product' ) || ! is_product() ) return;
    ?>
<style>
.sgc-share-btn:hover{transform:translateY(-2px);box-shadow:0 4px 8px rgba(0,0,0,.15);filter:brightness(1.05);}
.sgc-share-btn:active{transform:translateY(0);}
.sgc-share-btn svg{width:60%;height:60%;display:block;}
.sgc-share-modal{position:fixed;inset:0;z-index:999998;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;padding:20px;}
.sgc-share-modal.open{display:flex;}
.sgc-share-modal-card{background:#fff;border-radius:12px;padding:24px;max-width:420px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.3);}
.sgc-share-modal-card h3{margin:0 0 12px;font-size:16px;color:#1a1a2e;}
.sgc-share-modal-card textarea{width:100%;border:1px solid #ddd;border-radius:6px;padding:10px;font-size:13px;resize:vertical;font-family:inherit;}
.sgc-share-modal-close{position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:#94a3b8;}
.sgc-toast{position:fixed;bottom:30px;left:50%;transform:translateX(-50%);background:#16a34a;color:#fff;padding:10px 18px;border-radius:8px;font-size:13px;z-index:999999;box-shadow:0 4px 12px rgba(0,0,0,.2);opacity:0;pointer-events:none;transition:opacity .25s;}
.sgc-toast.show{opacity:1;}
</style>

<div id="sgc-share-modal" class="sgc-share-modal" onclick="if(event.target===this)sgcShareCloseModal()">
    <div class="sgc-share-modal-card" style="position:relative;">
        <button class="sgc-share-modal-close" onclick="sgcShareCloseModal()" aria-label="Close">&times;</button>
        <div id="sgc-share-modal-body"></div>
    </div>
</div>
<div id="sgc-share-toast" class="sgc-toast"></div>

<script>
(function(){
function toast(msg){
    var t=document.getElementById('sgc-share-toast');
    t.textContent=msg;t.classList.add('show');
    setTimeout(function(){t.classList.remove('show');},2200);
}
window.sgcShareOpenModal=function(html){
    document.getElementById('sgc-share-modal-body').innerHTML=html;
    document.getElementById('sgc-share-modal').classList.add('open');
    document.body.style.overflow='hidden';
};
window.sgcShareCloseModal=function(){
    document.getElementById('sgc-share-modal').classList.remove('open');
    document.body.style.overflow='';
};

document.addEventListener('click',function(e){
    var btn=e.target.closest('[data-sgc-share]');
    if(!btn)return;
    e.preventDefault();
    var bar=btn.closest('.sgc-share-bar');
    if(!bar)return;
    var url=bar.getAttribute('data-product-url')||location.href;
    var title=bar.getAttribute('data-product-title')||document.title;
    var img=bar.getAttribute('data-product-img')||'';
    var msg=bar.getAttribute('data-share-msg')||(title+' — '+url);
    var type=btn.getAttribute('data-sgc-share');

    if(type==='copy'){
        if(navigator.clipboard&&window.isSecureContext){
            navigator.clipboard.writeText(url).then(function(){toast('✅ Link copied!');});
        }else{
            // Fallback
            var ta=document.createElement('textarea');
            ta.value=url;ta.style.position='fixed';ta.style.opacity='0';
            document.body.appendChild(ta);ta.select();
            try{document.execCommand('copy');toast('✅ Link copied!');}
            catch(err){toast('Could not copy — please copy manually.');}
            document.body.removeChild(ta);
        }
    }else if(type==='instagram'){
        sgcShareOpenModal(
            '<h3>📸 Share on Instagram</h3>'+
            '<p style="color:#64748b;font-size:13px;line-height:1.5;">Instagram does not allow direct URL sharing from the web. Copy the caption below, save the product image, and paste both into your Instagram post or story:</p>'+
            '<textarea rows="5" readonly onclick="this.select()" style="margin-top:12px;">'+
              msg.replace(/</g,'&lt;')+
            '</textarea>'+
            '<div style="display:flex;gap:8px;margin-top:14px;">'+
              '<button onclick="navigator.clipboard&amp;&amp;navigator.clipboard.writeText(\''+msg.replace(/\\/g,'\\\\').replace(/\'/g,"\\'")+'\').then(function(){var t=document.getElementById(\'sgc-share-toast\');t.textContent=\'✅ Caption copied!\';t.classList.add(\'show\');setTimeout(function(){t.classList.remove(\'show\');},2200);});" '+
                'style="flex:1;background:#E4405F;color:#fff;border:none;padding:10px;border-radius:6px;font-weight:600;cursor:pointer;">📋 Copy caption</button>'+
              (img?'<a href="'+img+'" target="_blank" rel="noopener" download style="flex:1;background:#475569;color:#fff;text-align:center;padding:10px;border-radius:6px;font-weight:600;text-decoration:none;">💾 Save image</a>':'')+
            '</div>'
        );
    }else if(type==='wechat'){
        // Generate QR via free API (no key); fallback to Google Charts.
        var qr1='https://api.qrserver.com/v1/create-qr-code/?size=240x240&data='+encodeURIComponent(url);
        sgcShareOpenModal(
            '<h3>💬 Share on WeChat</h3>'+
            '<p style="color:#64748b;font-size:13px;line-height:1.5;">Open WeChat → Discover → Scan QR — point your camera at this code to open the product:</p>'+
            '<div style="text-align:center;padding:16px;background:#f8f9fb;border-radius:8px;margin-top:12px;">'+
              '<img src="'+qr1+'" alt="WeChat QR" style="max-width:240px;width:100%;height:auto;display:inline-block;" '+
                'onerror="this.onerror=null;this.src=\'https://chart.googleapis.com/chart?cht=qr&chs=240x240&chl='+encodeURIComponent(url)+'\';">'+
            '</div>'+
            '<p style="color:#94a3b8;font-size:11px;margin-top:10px;text-align:center;">'+url+'</p>'
        );
    }
});
})();
</script>
    <?php
}

// ──────────────────────────────────────────────────────────────────────
// Admin settings page
// ──────────────────────────────────────────────────────────────────────
function sgclabs_social_share_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
    $d = sgclabs_social_share_defaults();
    $v = [];
    foreach ( $d as $k => $dv ) $v[ $k ] = get_option( $k, $dv );
    $saved = isset( $_GET['saved'] );
    $platforms = sgclabs_social_share_platforms();
    ?>
    <div class="wrap" style="max-width:880px;">
        <h1>🔗 Social Share Bar
            <span style="font-size:11px;background:#1a1a2e;color:#fff;padding:2px 8px;border-radius:10px;vertical-align:middle;">B2B + B2C</span>
        </h1>
        <?php if ( $saved ) : ?><div class="notice notice-success is-dismissible"><p>✅ Settings saved!</p></div><?php endif; ?>

        <p style="color:#555;max-width:680px;line-height:1.6;">
            Universal share bar on every product page. 12 platforms supported — enable only the ones your audience actually uses.
            Region-specific platforms (WeChat, LINE, VK, Messenger) are off by default; turn them on if those markets matter.
        </p>

        <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
            <?php wp_nonce_field( 'sgclabs_social_share_nonce' ); ?>
            <input type="hidden" name="action" value="sgclabs_save_social_share">

            <!-- General -->
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:16px;">
                <h2 style="margin-top:0;font-size:15px;">⚙️ General</h2>
                <table class="form-table">
                    <tr><th>Enable share bar</th>
                        <td><label><input type="checkbox" name="sgclabs_social_share_active" value="1" <?php checked( $v['sgclabs_social_share_active'], '1' ); ?>> Show on product pages</label></td>
                    </tr>
                    <tr><th>Position</th>
                        <td>
                            <select name="sgclabs_social_share_position">
                                <option value="after_cart"  <?php selected( $v['sgclabs_social_share_position'], 'after_cart' ); ?>>Below Add to Cart</option>
                                <option value="before_cart" <?php selected( $v['sgclabs_social_share_position'], 'before_cart' ); ?>>Above Add to Cart</option>
                                <option value="after_meta"  <?php selected( $v['sgclabs_social_share_position'], 'after_meta' ); ?>>After product meta (SKU/category)</option>
                                <option value="after_desc"  <?php selected( $v['sgclabs_social_share_position'], 'after_desc' ); ?>>After product description</option>
                            </select>
                        </td>
                    </tr>
                    <tr><th>Heading label</th>
                        <td><input type="text" name="sgclabs_social_share_label" value="<?php echo esc_attr( $v['sgclabs_social_share_label'] ); ?>" class="regular-text"></td>
                    </tr>
                    <tr><th>Button size</th>
                        <td>
                            <select name="sgclabs_social_share_size">
                                <option value="small"  <?php selected( $v['sgclabs_social_share_size'], 'small' ); ?>>Small</option>
                                <option value="medium" <?php selected( $v['sgclabs_social_share_size'], 'medium' ); ?>>Medium</option>
                                <option value="large"  <?php selected( $v['sgclabs_social_share_size'], 'large' ); ?>>Large</option>
                            </select>
                        </td>
                    </tr>
                    <tr><th>Button shape</th>
                        <td>
                            <select name="sgclabs_social_share_style">
                                <option value="pill"    <?php selected( $v['sgclabs_social_share_style'], 'pill' ); ?>>Pill (round)</option>
                                <option value="square"  <?php selected( $v['sgclabs_social_share_style'], 'square' ); ?>>Square (rounded corners)</option>
                                <option value="minimal" <?php selected( $v['sgclabs_social_share_style'], 'minimal' ); ?>>Minimal (sharp corners)</option>
                            </select>
                        </td>
                    </tr>
                    <tr><th>Share message template</th>
                        <td>
                            <textarea name="sgclabs_social_share_template" rows="2" class="large-text"><?php echo esc_textarea( $v['sgclabs_social_share_template'] ); ?></textarea>
                            <p class="description">Tokens: <code>{title}</code>, <code>{url}</code></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Universal essentials -->
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:16px;">
                <h2 style="margin-top:0;font-size:15px;">🌐 Universal essentials <span style="font-size:11px;color:#64748b;font-weight:normal;">— work for both B2B + B2C</span></h2>
                <?php sgclabs_social_share_render_tier( $platforms, $v, [ 'email', 'whatsapp', 'copy' ] ); ?>
            </div>

            <!-- B2B platforms -->
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:16px;">
                <h2 style="margin-top:0;font-size:15px;">💼 B2B — professional networks</h2>
                <?php sgclabs_social_share_render_tier( $platforms, $v, [ 'linkedin', 'telegram' ] ); ?>
            </div>

            <!-- B2C platforms -->
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:16px;">
                <h2 style="margin-top:0;font-size:15px;">🛒 B2C — consumer social</h2>
                <?php sgclabs_social_share_render_tier( $platforms, $v, [ 'facebook', 'x', 'pinterest', 'instagram' ] ); ?>
                <p style="color:#94a3b8;font-size:11px;margin:8px 0 0;">
                    💡 Instagram doesn't allow URL sharing from the web — clicking the IG button opens a "copy caption + save image" modal so the user can post manually.
                </p>
            </div>

            <!-- Regional / opt-in -->
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:16px;">
                <h2 style="margin-top:0;font-size:15px;">🌏 Regional <span style="font-size:11px;color:#64748b;font-weight:normal;">— enable only if you ship to these markets</span></h2>
                <?php sgclabs_social_share_render_tier( $platforms, $v, [ 'wechat', 'line', 'vk', 'messenger' ] ); ?>
                <p style="color:#94a3b8;font-size:11px;margin:8px 0 0;">
                    💡 WeChat doesn't support direct URL sharing — the button opens a QR-code modal that customers scan in their WeChat app.
                </p>
            </div>

            <?php submit_button( 'Save Settings' ); ?>
        </form>
    </div>
    <?php
}

/** Helper to render one tier of platform toggles in the admin page. */
function sgclabs_social_share_render_tier( array $platforms, array $values, array $keys ) {
    echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px;">';
    foreach ( $keys as $k ) {
        if ( ! isset( $platforms[ $k ] ) ) continue;
        $p     = $platforms[ $k ];
        $on    = ( $values[ 'sgclabs_share_' . $k ] ?? '0' ) === '1';
        $color = $p['color'];
        echo '<label style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid ' . ( $on ? esc_attr( $color ) : '#e5e7eb' ) . ';border-radius:8px;background:' . ( $on ? esc_attr( $color ) . '0d' : '#fff' ) . ';cursor:pointer;font-size:13px;">';
        echo '<input type="checkbox" name="sgclabs_share_' . esc_attr( $k ) . '" value="1" ' . checked( $on, true, false ) . ' style="margin:0;">';
        echo '<span style="display:inline-flex;width:22px;height:22px;border-radius:6px;background:' . esc_attr( $color ) . ';color:#fff;align-items:center;justify-content:center;">';
        echo '<span style="display:flex;width:14px;height:14px;align-items:center;justify-content:center;">' . $p['icon'] . '</span>';
        echo '</span>';
        echo '<span style="font-weight:600;">' . esc_html( $p['label'] ) . '</span>';
        echo '</label>';
    }
    echo '</div>';
}
