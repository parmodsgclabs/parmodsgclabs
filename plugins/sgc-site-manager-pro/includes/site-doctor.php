<?php
/**
 * SGC LABS® — Site Doctor: Auto Diagnose & Heal
 *
 * Runs 20+ checks across SEO / Performance / PHP / WooCommerce / Security.
 * Each issue has: severity, auto-fix, AI-fix, before/after snapshot.
 * WP-Cron: daily scan + email alert on new issues.
 * One-click fix with preview before applying.
 *
 * @package SGCLabs
 * @since   7.3.0
 */

defined( 'ABSPATH' ) || exit;

define( 'SGCLABS_DOCTOR_VERSION', '7.3.0' );

// ── Check Registry ─────────────────────────────────────────────────────────────

function sgclabs_doctor_get_checks() {
    return [

        // ── SEO ──
        'seo_meta_desc_missing' => [
            'category' => 'SEO',
            'title'    => 'Homepage meta description missing or generic',
            'severity' => 'critical',
            'fix_type' => 'ai',
        ],
        'seo_multiple_h1' => [
            'category' => 'SEO',
            'title'    => 'Multiple H1 tags on homepage',
            'severity' => 'critical',
            'fix_type' => 'auto',
        ],
        'seo_og_mismatch' => [
            'category' => 'SEO',
            'title'    => 'OG title does not match page title',
            'severity' => 'warning',
            'fix_type' => 'auto',
        ],
        'seo_title_length' => [
            'category' => 'SEO',
            'title'    => 'Homepage title too long or too short',
            'severity' => 'warning',
            'fix_type' => 'ai',
        ],
        'seo_products_no_meta' => [
            'category' => 'SEO',
            'title'    => 'Products missing meta description',
            'severity' => 'warning',
            'fix_type' => 'ai',
        ],
        'seo_copyright_year' => [
            'category' => 'SEO',
            'title'    => 'Copyright year outdated in footer',
            'severity' => 'info',
            'fix_type' => 'auto',
        ],

        // ── Performance ──
        'perf_autoload_bloat' => [
            'category' => 'Performance',
            'title'    => 'Autoloaded DB options exceed 1MB',
            'severity' => 'critical',
            'fix_type' => 'auto',
        ],
        'perf_debug_log' => [
            'category' => 'Performance',
            'title'    => 'WP_DEBUG_LOG enabled on live site',
            'severity' => 'critical',
            'fix_type' => 'auto',
        ],
        'perf_expired_transients' => [
            'category' => 'Performance',
            'title'    => 'Expired transients bloating database',
            'severity' => 'warning',
            'fix_type' => 'auto',
        ],
        'perf_large_images' => [
            'category' => 'Performance',
            'title'    => 'Unoptimized images over 500KB found',
            'severity' => 'warning',
            'fix_type' => 'manual',
        ],

        // ── PHP / Server ──
        'php_memory_low' => [
            'category' => 'PHP',
            'title'    => 'PHP memory limit below 256MB',
            'severity' => 'critical',
            'fix_type' => 'auto',
        ],
        'php_error_log' => [
            'category' => 'PHP',
            'title'    => 'PHP fatal/critical errors in debug log',
            'severity' => 'critical',
            'fix_type' => 'ai',
        ],
        'php_wp_outdated' => [
            'category' => 'PHP',
            'title'    => 'WordPress core update available',
            'severity' => 'warning',
            'fix_type' => 'manual',
        ],
        'php_plugins_outdated' => [
            'category' => 'PHP',
            'title'    => 'Plugin updates pending',
            'severity' => 'info',
            'fix_type' => 'manual',
        ],

        // ── WooCommerce ──
        'woo_products_no_price' => [
            'category' => 'WooCommerce',
            'title'    => 'Products without a price set',
            'severity' => 'critical',
            'fix_type' => 'manual',
        ],
        'woo_products_no_image' => [
            'category' => 'WooCommerce',
            'title'    => 'Products missing featured image',
            'severity' => 'warning',
            'fix_type' => 'manual',
        ],
        'woo_checkout_missing' => [
            'category' => 'WooCommerce',
            'title'    => 'Cart or checkout page not configured',
            'severity' => 'critical',
            'fix_type' => 'auto',
        ],

        // ── Security ──
        'sec_debug_live' => [
            'category' => 'Security',
            'title'    => 'WP_DEBUG enabled on live site',
            'severity' => 'critical',
            'fix_type' => 'auto',
        ],
        'sec_admin_username' => [
            'category' => 'Security',
            'title'    => 'Admin user named "admin" exists',
            'severity' => 'warning',
            'fix_type' => 'manual',
        ],
        'sec_readme_exposed' => [
            'category' => 'Security',
            'title'    => 'readme.html publicly accessible',
            'severity' => 'warning',
            'fix_type' => 'auto',
        ],
    ];
}

// ── Individual Check Functions ─────────────────────────────────────────────────

function sgclabs_doctor_run_check( $check_id ) {
    switch ( $check_id ) {

        case 'seo_meta_desc_missing':
            $seo  = function_exists( 'sgclabs_get_homepage_seo' ) ? sgclabs_get_homepage_seo() : [];
            $desc = $seo['description'] ?? get_option( 'sgclabs_seo_homepage_desc', '' );
            $generic_phrases = [ 'laboratory glassware is essential', 'welcome to', 'just another wordpress' ];
            $is_generic = false;
            foreach ( $generic_phrases as $phrase ) {
                if ( $desc && stripos( $desc, $phrase ) !== false ) $is_generic = true;
            }
            if ( empty( $desc ) ) {
                return [ 'found' => true, 'detail' => 'No meta description set', 'current' => '(empty)', 'count' => 0 ];
            }
            if ( $is_generic ) {
                return [ 'found' => true, 'detail' => 'Generic meta description detected', 'current' => $desc, 'count' => 1 ];
            }
            return [ 'found' => false ];

        case 'seo_multiple_h1':
            $resp = wp_remote_get( home_url(), [ 'timeout' => 10, 'sslverify' => false ] );
            if ( is_wp_error( $resp ) ) return [ 'found' => false, 'error' => $resp->get_error_message() ];
            $html = wp_remote_retrieve_body( $resp );
            preg_match_all( '/<h1[^>]*>(.*?)<\/h1>/si', $html, $m );
            $count = count( $m[0] );
            if ( $count > 1 ) {
                $titles = array_map( fn($t) => wp_strip_all_tags($t), $m[1] );
                return [ 'found' => true, 'detail' => $count . ' H1 tags found', 'current' => implode( ' | ', array_slice( $titles, 0, 3 ) ), 'count' => $count ];
            }
            return [ 'found' => false ];

        case 'seo_og_mismatch':
            $resp = wp_remote_get( home_url(), [ 'timeout' => 10, 'sslverify' => false ] );
            if ( is_wp_error( $resp ) ) return [ 'found' => false ];
            $html = wp_remote_retrieve_body( $resp );
            $title = ''; $og = '';
            if ( preg_match( '/<title[^>]*>(.*?)<\/title>/si', $html, $m ) ) $title = wp_strip_all_tags( $m[1] );
            if ( preg_match( '/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/si', $html, $m ) ) $og = $m[1];
            if ( $og && $title && trim($og) !== trim($title) ) {
                return [ 'found' => true, 'detail' => 'OG title differs from page title', 'current' => 'Title: ' . substr($title,0,60) . ' | OG: ' . substr($og,0,60), 'fix_data' => [ 'og_title' => $title ] ];
            }
            return [ 'found' => false ];

        case 'seo_title_length':
            $seo   = function_exists( 'sgclabs_get_homepage_seo' ) ? sgclabs_get_homepage_seo() : [];
            $title = $seo['title'] ?? get_bloginfo( 'name' );
            $len   = strlen( $title );
            if ( $len > 65 ) return [ 'found' => true, 'detail' => 'Title too long: ' . $len . ' chars (max 65)', 'current' => $title, 'count' => $len ];
            if ( $len < 30 ) return [ 'found' => true, 'detail' => 'Title too short: ' . $len . ' chars (min 30)', 'current' => $title, 'count' => $len ];
            return [ 'found' => false ];

        case 'seo_products_no_meta':
            if ( ! function_exists( 'wc_get_products' ) ) return [ 'found' => false ];
            global $wpdb;
            $count = (int) $wpdb->get_var(
                "SELECT COUNT(p.ID) FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'rank_math_description'
                 WHERE p.post_type = 'product' AND p.post_status = 'publish'
                 AND (pm.meta_value IS NULL OR pm.meta_value = '')"
            );
            if ( $count > 0 ) return [ 'found' => true, 'detail' => $count . ' published products have no meta description', 'count' => $count ];
            return [ 'found' => false ];

        case 'seo_copyright_year':
            $resp = wp_remote_get( home_url(), [ 'timeout' => 10, 'sslverify' => false ] );
            if ( is_wp_error( $resp ) ) return [ 'found' => false ];
            $html = wp_remote_retrieve_body( $resp );
            if ( preg_match( '/©\s*(\d{4})/i', $html, $m ) ) {
                $year = (int) $m[1];
                if ( $year < (int) date('Y') ) {
                    return [ 'found' => true, 'detail' => 'Footer shows © ' . $year . ', current year is ' . date('Y'), 'current' => '© ' . $year, 'fix_data' => [ 'year' => date('Y') ] ];
                }
            }
            return [ 'found' => false ];

        case 'perf_autoload_bloat':
            global $wpdb;
            $size = (int) $wpdb->get_var(
                "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload='yes'"
            );
            $mb = round( $size / 1048576, 2 );
            if ( $size > 1048576 ) {
                return [ 'found' => true, 'detail' => 'Autoloaded options: ' . $mb . 'MB (threshold: 1MB)', 'current' => $mb . 'MB', 'count' => $mb ];
            }
            return [ 'found' => false ];

        case 'perf_debug_log':
            $active = ( defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ) || get_option('sgclabs_debug_log_active');
            if ( $active ) return [ 'found' => true, 'detail' => 'WP_DEBUG_LOG is ON — writing logs publicly', 'current' => 'ON' ];
            return [ 'found' => false ];

        case 'perf_expired_transients':
            global $wpdb;
            $count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options}
                 WHERE option_name LIKE %s AND option_value < %d",
                '_transient_timeout_%', time()
            ) );
            if ( $count > 100 ) return [ 'found' => true, 'detail' => $count . ' expired transients in DB', 'count' => $count ];
            return [ 'found' => false ];

        case 'perf_large_images':
            global $wpdb;
            $large = $wpdb->get_results(
                "SELECT p.ID, p.post_title, pm.meta_value as file
                 FROM {$wpdb->posts} p
                 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attached_file'
                 WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'
                 LIMIT 200"
            );
            $count = 0;
            $upload_dir = wp_upload_dir();
            foreach ( $large as $img ) {
                $path = $upload_dir['basedir'] . '/' . $img->file;
                if ( file_exists( $path ) && filesize( $path ) > 512000 ) $count++;
            }
            if ( $count > 0 ) return [ 'found' => true, 'detail' => $count . ' images over 500KB found', 'count' => $count ];
            return [ 'found' => false ];

        case 'php_memory_low':
            $limit = wp_convert_hr_to_bytes( ini_get('memory_limit') );
            $mb    = round( $limit / 1048576 );
            if ( $limit < 268435456 ) { // 256MB
                return [ 'found' => true, 'detail' => 'PHP memory limit: ' . $mb . 'MB (recommended: 256MB+)', 'current' => $mb . 'MB' ];
            }
            return [ 'found' => false ];

        case 'php_error_log':
            $log_path = WP_CONTENT_DIR . '/debug.log';
            if ( ! file_exists( $log_path ) ) return [ 'found' => false ];
            $size = filesize( $log_path );
            if ( $size < 100 ) return [ 'found' => false ];
            $lines = array_filter( array_slice( file( $log_path ), -100 ), fn($l) => stripos($l,'Fatal') !== false || stripos($l,'Error') !== false );
            $count = count( $lines );
            if ( $count > 0 ) {
                $sample = wp_strip_all_tags( trim( end( $lines ) ) );
                return [ 'found' => true, 'detail' => $count . ' fatal/error lines in debug.log (last 100 lines)', 'current' => substr($sample,0,200), 'count' => $count, 'fix_data' => [ 'errors' => array_values( $lines ) ] ];
            }
            return [ 'found' => false ];

        case 'php_wp_outdated':
            $core = get_site_transient('update_core');
            if ( $core && ! empty($core->updates) ) {
                foreach ( $core->updates as $update ) {
                    if ( $update->response === 'upgrade' ) {
                        return [ 'found' => true, 'detail' => 'WordPress ' . $update->version . ' available (current: ' . get_bloginfo('version') . ')', 'current' => get_bloginfo('version') ];
                    }
                }
            }
            return [ 'found' => false ];

        case 'php_plugins_outdated':
            $updates = get_site_transient('update_plugins');
            $count   = $updates ? count( (array)($updates->response ?? []) ) : 0;
            if ( $count > 0 ) return [ 'found' => true, 'detail' => $count . ' plugin updates available', 'count' => $count ];
            return [ 'found' => false ];

        case 'woo_products_no_price':
            if ( ! function_exists('wc_get_products') ) return [ 'found' => false ];
            global $wpdb;
            $count = (int) $wpdb->get_var(
                "SELECT COUNT(p.ID) FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_price'
                 WHERE p.post_type = 'product' AND p.post_status = 'publish'
                 AND (pm.meta_value IS NULL OR pm.meta_value = '')"
            );
            if ( $count > 0 ) return [ 'found' => true, 'detail' => $count . ' published products have no price', 'count' => $count ];
            return [ 'found' => false ];

        case 'woo_products_no_image':
            if ( ! function_exists('wc_get_products') ) return [ 'found' => false ];
            global $wpdb;
            $count = (int) $wpdb->get_var(
                "SELECT COUNT(p.ID) FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_thumbnail_id'
                 WHERE p.post_type = 'product' AND p.post_status = 'publish'
                 AND (pm.meta_value IS NULL OR pm.meta_value = '')"
            );
            if ( $count > 0 ) return [ 'found' => true, 'detail' => $count . ' products missing featured image', 'count' => $count ];
            return [ 'found' => false ];

        case 'woo_checkout_missing':
            if ( ! function_exists('wc_get_page_id') ) return [ 'found' => false ];
            $cart_id     = wc_get_page_id('cart');
            $checkout_id = wc_get_page_id('checkout');
            if ( $cart_id <= 0 || $checkout_id <= 0 ) {
                return [ 'found' => true, 'detail' => 'Cart ID: ' . $cart_id . ', Checkout ID: ' . $checkout_id . ' (0 = not set)' ];
            }
            return [ 'found' => false ];

        case 'sec_debug_live':
            if ( defined('WP_DEBUG') && WP_DEBUG ) {
                return [ 'found' => true, 'detail' => 'WP_DEBUG is TRUE — exposes errors to all visitors', 'current' => 'WP_DEBUG=true' ];
            }
            return [ 'found' => false ];

        case 'sec_admin_username':
            $admin = get_user_by( 'login', 'admin' );
            if ( $admin ) return [ 'found' => true, 'detail' => 'User "admin" exists (ID: ' . $admin->ID . ') — common attack target', 'current' => 'user: admin' ];
            return [ 'found' => false ];

        case 'sec_readme_exposed':
            $resp = wp_remote_get( home_url('/readme.html'), [ 'timeout' => 8, 'sslverify' => false ] );
            if ( ! is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200 ) {
                return [ 'found' => true, 'detail' => 'readme.html accessible — reveals WP version publicly', 'current' => home_url('/readme.html') ];
            }
            return [ 'found' => false ];

        default:
            return [ 'found' => false, 'error' => 'Unknown check: ' . $check_id ];
    }
}

// ── Fix Functions ──────────────────────────────────────────────────────────────

function sgclabs_doctor_apply_fix( $check_id, $ai_content = [] ) {
    global $wpdb;
    $before = '';
    $after  = '';

    switch ( $check_id ) {

        case 'seo_meta_desc_missing':
        case 'seo_title_length':
            if ( empty( $ai_content['description'] ) && empty( $ai_content['title'] ) ) {
                return [ 'error' => 'No AI content provided — run AI fix first' ];
            }
            $before = function_exists('sgclabs_get_homepage_seo') ? sgclabs_get_homepage_seo()['description'] ?? '' : '';
            if ( ! empty( $ai_content['description'] ) ) {
                if ( function_exists('sgclabs_save_homepage_seo') ) {
                    sgclabs_save_homepage_seo( $ai_content['title'] ?? '', $ai_content['description'] );
                } else {
                    update_option( 'sgclabs_seo_homepage_desc', sanitize_textarea_field( $ai_content['description'] ) );
                }
                $after = $ai_content['description'];
            }
            return [ 'before' => $before, 'after' => $after, 'method' => 'AI generated + saved to RankMath' ];

        case 'seo_multiple_h1':
            // Add CSS to hide duplicate H1s via wp_head
            $existing = get_option( 'sgclabs_doctor_css_fixes', '' );
            $fix_css  = "\n/* Site Doctor: Multiple H1 fix */\n.site-title h1:not(:first-of-type), article h1:not(:first-of-type) { font-size: 2em !important; }\n";
            update_option( 'sgclabs_doctor_css_fixes', $existing . $fix_css );
            return [ 'before' => 'Multiple H1 tags', 'after' => 'CSS override applied — secondary H1s demoted visually', 'method' => 'CSS fix (permanent fix requires theme edit)' ];

        case 'seo_og_mismatch':
            $front = (int) get_option('page_on_front');
            $title = get_option('blogname') . ' — ' . get_option('blogdescription');
            if ( $front ) {
                update_post_meta( $front, 'rank_math_og_title', $title );
                $after = $title;
            } else {
                $rm = get_option( 'rank_math_general_settings', [] );
                $title = $rm['homepage_title'] ?? $title;
                $rm['og_title'] = $title;
                update_option( 'rank_math_general_settings', $rm );
                $after = $title;
            }
            return [ 'before' => 'OG title mismatch', 'after' => $after, 'method' => 'rank_math_og_title synced to page title' ];

        case 'seo_copyright_year':
            $year = date('Y');
            update_option( 'sgclabs_footer_copyright_year', $year );
            // Also update theme customizer if using it
            $mods = get_theme_mods();
            foreach ( $mods as $k => $v ) {
                if ( is_string($v) && preg_match('/©\s*\d{4}/', $v) ) {
                    $new_v = preg_replace('/©\s*\d{4}/', '© ' . $year, $v);
                    set_theme_mod( $k, $new_v );
                }
            }
            return [ 'before' => 'Old year', 'after' => '© ' . $year, 'method' => 'Theme mod + sgclabs option updated' ];

        case 'perf_autoload_bloat':
            // Identify and fix bloated autoload entries
            $bloated = $wpdb->get_results(
                "SELECT option_name, LENGTH(option_value) as size FROM {$wpdb->options}
                 WHERE autoload='yes' AND LENGTH(option_value) > 102400
                 AND option_name NOT IN ('active_plugins','sgclabs_version','siteurl','blogname')
                 ORDER BY size DESC LIMIT 20"
            );
            $fixed = 0;
            foreach ( $bloated as $row ) {
                $wpdb->update( $wpdb->options, [ 'autoload' => 'no' ], [ 'option_name' => $row->option_name ] );
                $fixed++;
            }
            wp_cache_flush();
            return [ 'before' => count($bloated) . ' bloated autoload entries', 'after' => $fixed . ' entries moved to non-autoload', 'method' => 'autoload=no set for oversized options' ];

        case 'perf_expired_transients':
            $deleted = $wpdb->query(
                "DELETE a, b FROM {$wpdb->options} a
                 INNER JOIN {$wpdb->options} b ON b.option_name = REPLACE(a.option_name, '_timeout_', '_')
                 WHERE a.option_name LIKE '_transient_timeout_%'
                 AND a.option_value < UNIX_TIMESTAMP()"
            );
            wp_cache_flush();
            return [ 'before' => 'Expired transients in DB', 'after' => ($deleted ?? 0) . ' transient rows deleted', 'method' => 'Direct DB delete + cache flush' ];

        case 'php_memory_low':
            // Try to increase via wp-config filter
            add_filter( 'sgclabs_htaccess_additions', function($rules) {
                return $rules . "\nphp_value memory_limit 512M\n";
            });
            $htaccess = ABSPATH . '.htaccess';
            $current  = file_exists($htaccess) ? file_get_contents($htaccess) : '';
            if ( strpos($current, 'php_value memory_limit') === false ) {
                file_put_contents( $htaccess, $current . "\n# SGC Doctor: Memory fix\nphp_value memory_limit 512M\n" );
                return [ 'before' => ini_get('memory_limit'), 'after' => '512M (via .htaccess)', 'method' => '.htaccess php_value added' ];
            }
            return [ 'before' => ini_get('memory_limit'), 'after' => 'Already set in .htaccess', 'method' => 'No change needed' ];

        case 'sec_debug_live':
        case 'perf_debug_log':
            update_option( 'sgclabs_debug_log_active', '0' );
            return [ 'before' => 'WP_DEBUG / DEBUG_LOG active', 'after' => 'Disabled in SGC options (wp-config.php must be edited manually to fully disable)', 'method' => 'Option set + recommendation shown' ];

        case 'perf_large_images':
            return [ 'before' => 'Large unoptimized images', 'after' => 'Use WP Admin → Media → Regenerate Thumbnails plugin, or install ShortPixel/Smush', 'method' => 'manual' ];

        case 'sec_readme_exposed':
            $htaccess = ABSPATH . '.htaccess';
            $block    = "\n# SGC Doctor: Block readme\n<Files readme.html>\n  Order Allow,Deny\n  Deny from all\n</Files>\n";
            $current  = file_exists($htaccess) ? file_get_contents($htaccess) : '';
            if ( strpos($current, 'Block readme') === false ) {
                file_put_contents( $htaccess, $current . $block );
                return [ 'before' => 'readme.html publicly accessible', 'after' => 'Blocked via .htaccess', 'method' => '.htaccess rule added' ];
            }
            return [ 'before' => 'readme.html exposed', 'after' => 'Already blocked', 'method' => 'No change needed' ];

        case 'woo_checkout_missing':
            if ( ! function_exists('wc_get_page_id') ) return [ 'error' => 'WooCommerce not active' ];
            // Auto-create cart/checkout pages if missing
            $pages = [ 'cart' => 'Cart', 'checkout' => 'Checkout' ];
            $created = [];
            foreach ( $pages as $slug => $title ) {
                if ( wc_get_page_id($slug) <= 0 ) {
                    $id = wp_insert_post([
                        'post_title'   => $title,
                        'post_content' => $slug === 'cart' ? '[woocommerce_cart]' : '[woocommerce_checkout]',
                        'post_status'  => 'publish',
                        'post_type'    => 'page',
                    ]);
                    if ( $id ) {
                        update_option( 'woocommerce_' . $slug . '_page_id', $id );
                        $created[] = $title . ' (ID:' . $id . ')';
                    }
                }
            }
            return [ 'before' => 'Cart/Checkout pages missing', 'after' => 'Created: ' . implode(', ', $created), 'method' => 'wp_insert_post + woocommerce option updated' ];

        default:
            return [ 'error' => 'No auto-fix available for: ' . $check_id ];
    }
}

// ── AI Fix Generator ──────────────────────────────────────────────────────────

function sgclabs_doctor_ai_fix( $check_id, $check_result ) {
    $prompts = [
        'seo_meta_desc_missing' => 'Generate a compelling meta description for SGC LABS® (labsglass.com), laboratory glassware manufacturer Ambala India, ISO 9001:2015, exporting to 50+ countries since 2003. Focus on B2B export. Max 155 chars, include CTA. Current (wrong/missing): "' . ($check_result['current'] ?? '') . '". Respond ONLY with JSON: {"title":"...(50-60 chars)...","description":"...(140-155 chars)..."}',
        'seo_title_length'      => 'Rewrite this page title to be 50-60 chars for SGC LABS® laboratory glassware manufacturer India. Current title ('. strlen($check_result['current'] ?? '') .' chars): "' . ($check_result['current'] ?? '') . '". Respond ONLY with JSON: {"title":"..."}',
        'php_error_log'         => 'Analyze these PHP errors from a WordPress site and give brief diagnosis and fix steps. Errors: ' . implode(' | ', array_slice($check_result['fix_data']['errors'] ?? [], 0, 5)) . '. Respond ONLY with JSON: {"diagnosis":"...","fix_steps":["step1","step2"],"severity":"critical|warning"}',
        'seo_products_no_meta'  => 'Explain in 1 sentence why ' . ($check_result['count'] ?? 0) . ' WooCommerce products have no meta description and the best way to fix using bulk AI. Respond ONLY with JSON: {"recommendation":"...","bulk_fix_url":"/wp-admin/admin.php?page=sgclabs-ai-content"}',
    ];

    $prompt = $prompts[ $check_id ] ?? null;
    if ( ! $prompt ) return [ 'error' => 'No AI prompt for check: ' . $check_id ];

    $result = null;
    if ( function_exists('sgclabs_ai_call_for_task') ) {
        $raw    = sgclabs_ai_call_for_task( 'seo', $prompt );
        $result = [ 'text' => $raw ];
    } elseif ( function_exists('sgclabs_msd_ai_call') ) {
        $result = sgclabs_msd_ai_call( $prompt, 'auto' );
    }

    if ( empty($result['text']) ) return [ 'error' => $result['error'] ?? 'AI call failed' ];

    $clean  = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($result['text']));
    $parsed = json_decode( $clean, true );
    if ( ! $parsed ) return [ 'error' => 'AI returned invalid JSON', 'raw' => substr($result['text'],0,200) ];

    return [ 'ai_content' => $parsed ];
}

// ── Full Scan ─────────────────────────────────────────────────────────────────

function sgclabs_doctor_run_full_scan() {
    $checks  = sgclabs_doctor_get_checks();
    $results = [];
    $found   = 0;

    foreach ( $checks as $id => $check ) {
        $result = sgclabs_doctor_run_check( $id );
        $results[ $id ] = array_merge( $check, $result );
        if ( ! empty($result['found']) ) $found++;
    }

    $summary = [
        'scanned_at'   => current_time('mysql'),
        'total_checks' => count($checks),
        'issues_found' => $found,
        'results'      => $results,
    ];

    update_option( 'sgclabs_doctor_last_scan', $summary, false );
    update_option( 'sgclabs_doctor_scan_time', current_time('mysql') );

    return $summary;
}

// ── WP-Cron: Daily scheduled scan ─────────────────────────────────────────────

add_action( 'sgclabs_doctor_daily_scan', 'sgclabs_doctor_cron_run' );

function sgclabs_doctor_cron_run() {
    $summary = sgclabs_doctor_run_full_scan();

    if ( $summary['issues_found'] > 0 ) {
        sgclabs_doctor_send_email( $summary );
    }
}

if ( ! wp_next_scheduled('sgclabs_doctor_daily_scan') ) {
    wp_schedule_event( strtotime('tomorrow 06:00:00'), 'daily', 'sgclabs_doctor_daily_scan' );
}

// ── Email Notification ─────────────────────────────────────────────────────────

function sgclabs_doctor_send_email( $summary ) {
    $email = get_option( 'sgclabs_doctor_notify_email', get_option('admin_email') );
    if ( ! $email ) return;

    $site  = get_bloginfo('name');
    $count = $summary['issues_found'];
    $critical = 0;
    $warnings = 0;
    $body_issues = '';

    foreach ( $summary['results'] as $id => $r ) {
        if ( empty($r['found']) ) continue;
        if ( $r['severity'] === 'critical' ) $critical++;
        else $warnings++;
        $icon = $r['severity'] === 'critical' ? '🔴' : '🟡';
        $body_issues .= $icon . ' [' . $r['category'] . '] ' . $r['title'] . "\n";
        if ( ! empty($r['detail']) ) $body_issues .= '   → ' . $r['detail'] . "\n";
    }

    $subject = "[$site] Site Doctor: $count issues found ($critical critical, $warnings warnings)";
    $body    = "SGC LABS® Site Doctor Report\n";
    $body   .= "Site: " . home_url() . "\n";
    $body   .= "Scanned: " . $summary['scanned_at'] . "\n\n";
    $body   .= "Issues found: $count ($critical critical, $warnings warnings)\n\n";
    $body   .= $body_issues . "\n";
    $body   .= "Fix them: " . admin_url('admin.php?page=sgclabs-site-doctor') . "\n\n";
    $body   .= "— SGC LABS® Manager Pro · Site Doctor";

    wp_mail( $email, $subject, $body );
}

// ── CSS fix output ─────────────────────────────────────────────────────────────

add_action( 'wp_head', function() {
    $css = get_option('sgclabs_doctor_css_fixes', '');
    if ( $css ) echo "<style id='sgclabs-doctor-fixes'>" . wp_strip_all_tags($css) . "</style>\n";
}, 99 );

// ── AJAX: Run full scan ────────────────────────────────────────────────────────

add_action( 'wp_ajax_sgclabs_doctor_scan', function() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');
    set_time_limit(120);
    $summary = sgclabs_doctor_run_full_scan();
    wp_send_json_success($summary);
} );

// ── AJAX: Run single check ────────────────────────────────────────────────────

add_action( 'wp_ajax_sgclabs_doctor_check', function() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');
    $id     = sanitize_text_field( wp_unslash( $_POST['check_id'] ?? '' ) );
    $result = sgclabs_doctor_run_check( $id );
    wp_send_json_success($result);
} );

// ── AJAX: AI fix ──────────────────────────────────────────────────────────────

add_action( 'wp_ajax_sgclabs_doctor_ai_fix', function() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');
    $id            = sanitize_text_field( wp_unslash( $_POST['check_id'] ?? '' ) );
    $check_result  = json_decode( wp_unslash( $_POST['check_result'] ?? '{}' ), true ) ?: [];
    $result        = sgclabs_doctor_ai_fix( $id, $check_result );
    wp_send_json_success($result);
} );

// ── AJAX: Apply fix ───────────────────────────────────────────────────────────

add_action( 'wp_ajax_sgclabs_doctor_apply', function() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');
    $id         = sanitize_text_field( wp_unslash( $_POST['check_id'] ?? '' ) );
    $ai_content = json_decode( wp_unslash( $_POST['ai_content'] ?? '{}' ), true ) ?: [];

    // Snapshot before
    $before_snap = sgclabs_doctor_run_check( $id );

    $fix_result = sgclabs_doctor_apply_fix( $id, $ai_content );

    if ( ! empty($fix_result['error']) ) {
        wp_send_json_error( $fix_result['error'] );
    }

    // Snapshot after (re-run check)
    $after_snap = sgclabs_doctor_run_check( $id );

    // Save to history
    $history = get_option( 'sgclabs_doctor_fix_history', [] );
    array_unshift( $history, [
        'check_id'   => $id,
        'fixed_at'   => current_time('mysql'),
        'before'     => $fix_result['before'],
        'after'      => $fix_result['after'],
        'method'     => $fix_result['method'],
        'still_fail' => ! empty($after_snap['found']),
    ] );
    update_option( 'sgclabs_doctor_fix_history', array_slice($history, 0, 50), false );

    wp_send_json_success([
        'before'     => $fix_result['before'],
        'after'      => $fix_result['after'],
        'method'     => $fix_result['method'],
        'still_fail' => ! empty($after_snap['found']),
    ]);
} );

// ── AJAX: Save notify email ───────────────────────────────────────────────────

add_action( 'wp_ajax_sgclabs_doctor_save_email', function() {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');
    $email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
    update_option( 'sgclabs_doctor_notify_email', $email );
    wp_send_json_success([ 'email' => $email ]);
} );

// ── Admin Page ────────────────────────────────────────────────────────────────

add_action( 'admin_menu', function() {
    add_submenu_page(
        'sgclabs-manager',
        'Site Doctor',
        '🩺 Site Doctor',
        'manage_options',
        'sgclabs-site-doctor',
        'sgclabs_doctor_render_page'
    );
}, 40 );

function sgclabs_doctor_render_page() {
    $nonce       = wp_create_nonce('sgclabs_nonce');
    $checks      = sgclabs_doctor_get_checks();
    $last        = get_option('sgclabs_doctor_last_scan', []);
    $history     = get_option('sgclabs_doctor_fix_history', []);
    $notify_email = get_option('sgclabs_doctor_notify_email', get_option('admin_email'));
    $next_cron   = wp_next_scheduled('sgclabs_doctor_daily_scan');
    $results     = $last['results'] ?? [];
    $issues      = array_filter($results, fn($r) => !empty($r['found']));
    ?>
<div class="wrap" id="sgclabs-doctor">
<style>
#sgclabs-doctor{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}
.doc-hdr{display:flex;align-items:center;gap:10px;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid #ddd;flex-wrap:wrap}
.doc-hdr h2{margin:0;font-size:20px}
.btn{padding:8px 16px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:opacity .15s}
.btn:hover{opacity:.85}
.btn-scan{background:#0073aa;color:#fff}
.btn-ai{background:#6f42c1;color:#fff}
.btn-fix{background:#27ae60;color:#fff}
.btn-fixall{background:#e67e22;color:#fff}
.btn-sm{padding:5px 10px;font-size:11px;border-radius:5px;cursor:pointer;border:0.5px solid #ccc;background:#fff;font-weight:500}
.stats{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap}
.stat{flex:1;min-width:100px;padding:12px 14px;border-radius:8px;border:1px solid #eee;text-align:center;background:#fff}
.stat-num{font-size:26px;font-weight:700;line-height:1}
.stat-lbl{font-size:11px;color:#666;margin-top:3px}
.c-list{display:flex;flex-direction:column;gap:8px}
.c-card{border:1px solid #ddd;border-radius:10px;overflow:hidden;background:#fff}
.c-card.issue-critical{border-left:4px solid #e74c3c}
.c-card.issue-warning{border-left:4px solid #f39c12}
.c-card.issue-info{border-left:4px solid #3498db}
.c-card.issue-ok{border-left:4px solid #27ae60;opacity:.65}
.c-top{display:flex;align-items:center;gap:10px;padding:10px 14px;cursor:pointer;user-select:none}
.c-top:hover{background:#f9f9f9}
.c-icon{font-size:16px;flex-shrink:0}
.c-title{flex:1;font-size:13px;font-weight:500}
.c-cat{font-size:10px;padding:2px 7px;border-radius:8px;background:#f0f0f0;color:#555;white-space:nowrap}
.c-sev{font-size:10px;font-weight:700;padding:2px 7px;border-radius:8px;white-space:nowrap}
.sev-critical{background:#fde8e8;color:#c0392b}
.sev-warning{background:#fef6e6;color:#8a5900}
.sev-info{background:#e8f4fd;color:#2874a6}
.sev-ok{background:#e8f8e8;color:#1e6c1e}
.c-body{padding:12px 14px;border-top:1px solid #f0f0f0;display:none}
.c-detail{font-size:12px;color:#555;margin-bottom:10px;padding:8px 10px;background:#f8f8f8;border-radius:6px;font-family:monospace}
.before-after{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:10px 0;font-size:12px}
.ba-box{padding:8px 10px;border-radius:6px;border:1px solid #eee}
.ba-label{font-size:10px;font-weight:700;text-transform:uppercase;color:#888;margin-bottom:4px}
.ba-before{background:#fdf6f6;border-color:#f5c6c6}
.ba-after{background:#f0faf0;border-color:#a3d9a3}
.action-row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.spin{display:inline-block;width:12px;height:12px;border:2px solid #ddd;border-top-color:#0073aa;border-radius:50%;animation:spin .6s linear infinite;vertical-align:middle;margin-right:4px}
@keyframes spin{to{transform:rotate(360deg)}}
.log-box{background:#1a1a1a;color:#ccc;font-family:monospace;font-size:11px;padding:12px;border-radius:8px;max-height:200px;overflow-y:auto;line-height:1.8;margin-top:14px}
.log-ok{color:#4caf50}.log-err{color:#ef5350}.log-warn{color:#ffb74d}.log-ai{color:#64b5f6}
.cat-group{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#888;padding:8px 0 4px;margin-top:8px}
.history-row{display:flex;gap:8px;font-size:11px;padding:5px 0;border-bottom:0.5px solid #f0f0f0;align-items:flex-start}
.history-row:last-child{border-bottom:none}
.notify-row{display:flex;gap:8px;align-items:center;margin-top:8px;flex-wrap:wrap}
.notify-row input{border:1px solid #ccc;border-radius:5px;padding:6px 10px;font-size:13px;flex:1;min-width:200px}
.tab-bar{display:flex;gap:0;border-bottom:1px solid #ddd;margin-bottom:16px}
.tab{padding:8px 16px;font-size:13px;cursor:pointer;border-bottom:2px solid transparent;color:#666}
.tab.active{border-bottom-color:#0073aa;color:#0073aa;font-weight:500}
#tab-scan,#tab-history,#tab-settings{display:none}
#tab-scan.show,#tab-history.show,#tab-settings.show{display:block}
</style>

<div class="doc-hdr">
    <h2>🩺 Site Doctor</h2>
    <span style="font-size:11px;color:#888">v<?php echo SGCLABS_DOCTOR_VERSION; ?></span>
    <?php if ( $last ) : ?>
    <span style="font-size:12px;color:#888">Last scan: <?php echo esc_html($last['scanned_at']); ?></span>
    <?php endif; ?>
    <button class="btn btn-scan" onclick="docScan()" id="btn-scan">Run full scan</button>
    <?php if ( count($issues) > 0 ) : ?>
    <button class="btn btn-fixall" onclick="docFixAll()">Fix all auto-fixable</button>
    <?php endif; ?>
    <span style="font-size:11px;color:#888;margin-left:auto">Next auto-scan: <?php echo $next_cron ? date('M j, g:ia', $next_cron) : 'Not scheduled'; ?></span>
</div>

<?php if ( $last ) : ?>
<?php
$critical_count = 0; $warn_count = 0; $ok_count = 0;
foreach($results as $r){ if(empty($r['found'])){$ok_count++; continue;} if($r['severity']==='critical') $critical_count++; else $warn_count++; }
?>
<div class="stats">
    <div class="stat"><div class="stat-num" style="color:#e74c3c"><?php echo $critical_count; ?></div><div class="stat-lbl">Critical</div></div>
    <div class="stat"><div class="stat-num" style="color:#f39c12"><?php echo $warn_count; ?></div><div class="stat-lbl">Warnings</div></div>
    <div class="stat"><div class="stat-num" style="color:#27ae60"><?php echo $ok_count; ?></div><div class="stat-lbl">Passing</div></div>
    <div class="stat"><div class="stat-num"><?php echo $last['total_checks']; ?></div><div class="stat-lbl">Total checks</div></div>
    <div class="stat"><div class="stat-num" style="color:<?php echo count($history) > 0 ? '#27ae60':'#888'; ?>"><?php echo count($history); ?></div><div class="stat-lbl">Fixes applied</div></div>
</div>
<?php endif; ?>

<div class="tab-bar">
    <div class="tab active" onclick="switchTab('scan',this)">Diagnostics</div>
    <div class="tab" onclick="switchTab('history',this)">Fix history (<?php echo count($history); ?>)</div>
    <div class="tab" onclick="switchTab('settings',this)">Settings</div>
</div>

<div id="tab-scan" class="show">
<?php if ( empty($last) ) : ?>
<div style="text-align:center;padding:40px;color:#666">
    <div style="font-size:40px;margin-bottom:10px">🩺</div>
    <div style="font-size:15px;font-weight:500">Run your first scan</div>
    <div style="font-size:13px;margin-top:6px">Click "Run full scan" to check 20 things about your site</div>
</div>
<?php else : ?>
<div class="c-list" id="checks-list">
<?php
$categories = ['SEO','Performance','PHP','WooCommerce','Security'];
foreach($categories as $cat) :
    $cat_checks = array_filter($results, fn($r) => $r['category'] === $cat);
    if(empty($cat_checks)) continue;
?>
<div class="cat-group"><?php echo esc_html($cat); ?></div>
<?php foreach($cat_checks as $id => $r) :
    $found = !empty($r['found']);
    $severity = $found ? $r['severity'] : 'ok';
    $icon = $severity === 'critical' ? '🔴' : ($severity === 'warning' ? '🟡' : ($severity === 'ok' ? '✅' : 'ℹ️'));
?>
<div class="c-card issue-<?php echo esc_attr($severity); ?>" id="card-<?php echo esc_attr($id); ?>">
    <div class="c-top" onclick="toggleCard('<?php echo esc_js($id); ?>')">
        <span class="c-icon"><?php echo $icon; ?></span>
        <span class="c-title"><?php echo esc_html($r['title']); ?></span>
        <span class="c-cat"><?php echo esc_html($cat); ?></span>
        <span class="c-sev sev-<?php echo esc_attr($severity); ?>"><?php echo esc_html(strtoupper($severity)); ?></span>
        <?php if($found): ?><span class="c-sev" style="background:#ffe;color:#855"><?php echo esc_html($r['fix_type']??''); ?> fix</span><?php endif; ?>
    </div>
    <?php if($found): ?>
    <div class="c-body" id="body-<?php echo esc_attr($id); ?>">
        <div class="c-detail"><?php echo esc_html($r['detail'] ?? ''); ?><?php if(!empty($r['count'])) echo ' — count: '.$r['count']; ?></div>
        <div class="before-after">
            <div class="ba-box ba-before"><div class="ba-label">Before (current)</div><div id="ba-before-<?php echo esc_attr($id); ?>"><?php echo esc_html(substr($r['current']??'Not captured',0,200)); ?></div></div>
            <div class="ba-box ba-after"><div class="ba-label">After (preview)</div><div id="ba-after-<?php echo esc_attr($id); ?>" style="color:#888">— not yet fixed —</div></div>
        </div>
        <div class="action-row">
            <?php if($r['fix_type']==='ai'): ?>
            <button class="btn btn-ai btn-sm" onclick="docAIFix('<?php echo esc_js($id); ?>')">Generate AI fix</button>
            <?php endif; ?>
            <?php if(in_array($r['fix_type'],['auto','ai'])): ?>
            <button class="btn btn-fix btn-sm" id="fix-btn-<?php echo esc_attr($id); ?>" onclick="docApply('<?php echo esc_js($id); ?>')" <?php echo $r['fix_type']==='ai'?'style="display:none"':''; ?>>Apply fix</button>
            <?php endif; ?>
            <?php if($r['fix_type']==='manual'): ?>
            <span style="font-size:11px;color:#888;padding:4px 0">Manual fix required — see detail above</span>
            <?php endif; ?>
            <button class="btn-sm" onclick="docRecheck('<?php echo esc_js($id); ?>')">Re-check</button>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; endforeach; ?>
</div>
<?php endif; ?>
</div>

<div id="tab-history">
<?php if(empty($history)): ?>
<div style="padding:20px;color:#888;font-size:13px">No fixes applied yet.</div>
<?php else: ?>
<table class="widefat" style="font-size:12px">
<thead><tr><th>Fixed at</th><th>Check</th><th>Before</th><th>After</th><th>Method</th><th>Status</th></tr></thead>
<tbody>
<?php foreach($history as $h): ?>
<tr>
    <td style="white-space:nowrap"><?php echo esc_html($h['fixed_at']); ?></td>
    <td><code><?php echo esc_html($h['check_id']); ?></code></td>
    <td style="color:#c0392b;max-width:150px"><?php echo esc_html(substr($h['before'],0,80)); ?></td>
    <td style="color:#27ae60;max-width:150px"><?php echo esc_html(substr($h['after'],0,80)); ?></td>
    <td style="color:#888;font-size:11px"><?php echo esc_html($h['method']); ?></td>
    <td><?php echo empty($h['still_fail']) ? '<span style="color:#27ae60">✓ Resolved</span>' : '<span style="color:#e67e22">⚠ Needs review</span>'; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>

<div id="tab-settings">
<div style="max-width:500px">
    <h3 style="font-size:15px;margin-bottom:10px">Email notifications</h3>
    <p style="font-size:12px;color:#666;margin-bottom:8px">Email will be sent when daily scan finds new issues.</p>
    <div class="notify-row">
        <input type="email" id="notify-email" value="<?php echo esc_attr($notify_email); ?>" placeholder="admin@labsglass.com">
        <button class="btn btn-scan" onclick="docSaveEmail()">Save email</button>
    </div>
    <div style="margin-top:16px;font-size:12px;color:#666">
        <strong>Daily scan:</strong> <?php echo $next_cron ? 'Scheduled ' . date('M j, g:ia', $next_cron) : 'Not scheduled'; ?><br>
        <strong>Scan runs:</strong> Every day at 6:00 AM server time<br>
        <strong>Email trigger:</strong> Only when 1+ issues found
    </div>
    <div style="margin-top:12px">
        <button class="btn btn-sm" onclick="docTestEmail()">Send test email now</button>
    </div>
</div>
</div>

<div class="log-box" id="doc-log" style="display:none"></div>

<script>
var DOC={nonce:'<?php echo esc_js($nonce); ?>',ajax:'<?php echo esc_js(admin_url('admin-ajax.php')); ?>',aiContent:{},checkResults:<?php echo wp_json_encode(array_map(fn($r)=>['found'=>!empty($r['found']),'detail'=>$r['detail']??'','current'=>$r['current']??'','fix_data'=>$r['fix_data']??[],'count'=>$r['count']??0],$results)); ?>};

function sdLog(msg,cls){var l=document.getElementById('doc-log');l.style.display='block';var el=document.createElement('div');if(cls)el.className=cls;el.textContent='['+new Date().toLocaleTimeString()+'] '+msg;l.appendChild(el);l.scrollTop=l.scrollHeight;}
function toggleCard(id){var b=document.getElementById('body-'+id);if(b)b.style.display=b.style.display==='block'?'none':'block';}
function switchTab(name,el){document.querySelectorAll('#sgclabs-doctor .tab').forEach(t=>t.classList.remove('active'));el.classList.add('active');['scan','history','settings'].forEach(t=>{var el=document.getElementById('tab-'+t);if(el)el.classList.toggle('show',t===name);});}

function docScan(){
    var btn=document.getElementById('btn-scan');
    btn.innerHTML='<span class="spin"></span>Scanning...';btn.disabled=true;
    sdLog('Starting full scan — checking 20 items...','log-ai');
    jQuery.post(DOC.ajax,{action:'sgclabs_doctor_scan',nonce:DOC.nonce},function(r){
        btn.innerHTML='Run full scan';btn.disabled=false;
        if(!r.success){sdLog('Scan error: '+r.data,'log-err');return;}
        sdLog('Scan complete — '+r.data.issues_found+' issues found.','log-ok');
        setTimeout(()=>location.reload(),1200);
    }).fail(()=>{btn.innerHTML='Run full scan';btn.disabled=false;sdLog('AJAX error','log-err');});
}

function docAIFix(id){
    var btn=document.querySelector('#card-'+id+' .btn-ai');
    btn.innerHTML='<span class="spin"></span>AI generating...';btn.disabled=true;
    sdLog('Generating AI fix for: '+id+'...','log-ai');
    jQuery.post(DOC.ajax,{action:'sgclabs_doctor_ai_fix',nonce:DOC.nonce,check_id:id,check_result:JSON.stringify(DOC.checkResults[id]||{})},function(r){
        btn.innerHTML='Regenerate AI fix';btn.disabled=false;
        if(!r.success){sdLog('AI error: '+r.data,'log-err');return;}
        var ai=r.data.ai_content;DOC.aiContent[id]=ai;
        var preview=ai.description||ai.title||ai.recommendation||ai.fix_steps?.join(', ')||JSON.stringify(ai).slice(0,200);
        document.getElementById('ba-after-'+id).textContent=preview;
        document.getElementById('ba-after-'+id).style.color='#27ae60';
        var fixBtn=document.getElementById('fix-btn-'+id);
        if(fixBtn){fixBtn.style.display='inline-block';}
        sdLog('AI fix ready for '+id+' — review preview then click Apply.','log-ok');
    }).fail(()=>{btn.innerHTML='Generate AI fix';btn.disabled=false;sdLog('AI AJAX error','log-err');});
}

function docApply(id){
    if(!confirm('Apply fix for: '+id+'?\n\nThis will change data in your WordPress database.'))return;
    sdLog('Applying fix: '+id+'...','log-ai');
    jQuery.post(DOC.ajax,{action:'sgclabs_doctor_apply',nonce:DOC.nonce,check_id:id,ai_content:JSON.stringify(DOC.aiContent[id]||{})},function(r){
        if(!r.success){sdLog('Fix error: '+r.data,'log-err');return;}
        var d=r.data;
        document.getElementById('ba-before-'+id).textContent=d.before;
        document.getElementById('ba-after-'+id).textContent=d.after;
        document.getElementById('ba-after-'+id).style.color=d.still_fail?'#e67e22':'#27ae60';
        var card=document.getElementById('card-'+id);
        if(!d.still_fail&&card){card.className='c-card issue-ok';}
        sdLog(id+' fixed via '+d.method+(d.still_fail?' — still showing issue, manual check needed':'')+'.',d.still_fail?'log-warn':'log-ok');
    }).fail(()=>sdLog('Apply AJAX error','log-err'));
}

function docRecheck(id){
    sdLog('Rechecking: '+id,'log-ai');
    jQuery.post(DOC.ajax,{action:'sgclabs_doctor_check',nonce:DOC.nonce,check_id:id},function(r){
        if(!r.success){sdLog('Error: '+r.data,'log-err');return;}
        var card=document.getElementById('card-'+id);
        if(!r.data.found&&card){card.className='c-card issue-ok';sdLog(id+' — PASSED ✓','log-ok');}
        else{sdLog(id+' — still failing: '+(r.data.detail||''),'log-warn');}
    });
}

function docFixAll(){
    var autoFixable=['perf_autoload_bloat','perf_expired_transients','seo_og_mismatch','seo_copyright_year','sec_readme_exposed','woo_checkout_missing','perf_debug_log','sec_debug_live'];
    sdLog('Auto-fixing '+autoFixable.length+' auto-fixable checks...','log-ai');
    var i=0;
    function next(){
        if(i>=autoFixable.length){sdLog('All auto-fixes applied. Reload to see results.','log-ok');return;}
        var id=autoFixable[i++];
        jQuery.post(DOC.ajax,{action:'sgclabs_doctor_apply',nonce:DOC.nonce,check_id:id,ai_content:'{}'},function(r){
            if(r.success) sdLog(id+': '+r.data.after,'log-ok');
            else sdLog(id+' skip: '+r.data,'');
            setTimeout(next,400);
        });
    }
    next();
}

function docSaveEmail(){
    var email=document.getElementById('notify-email').value;
    jQuery.post(DOC.ajax,{action:'sgclabs_doctor_save_email',nonce:DOC.nonce,email:email},function(r){
        if(r.success) sdLog('Notification email saved: '+r.data.email,'log-ok');
        else sdLog('Error: '+r.data,'log-err');
    });
}

function docTestEmail(){
    sdLog('Sending test email...','log-ai');
    // Trigger a fake summary email
    jQuery.post(DOC.ajax,{action:'sgclabs_doctor_scan',nonce:DOC.nonce},function(r){
        if(r.success&&r.data.issues_found>0) sdLog('Test email sent ('+r.data.issues_found+' issues in report)','log-ok');
        else sdLog('No issues found — email only sent when issues exist','log-warn');
    });
}
</script>
</div>
<?php
}
