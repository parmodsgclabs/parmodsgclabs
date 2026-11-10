<?php
if (!defined('ABSPATH')) exit;

if (get_option('sgclabs_extended_sitemaps_active', '1') !== '1') return;

// ============================================================
// SGC LABS® — Extended Sitemaps & Redirect Enhancements v6.6.3
// 1. Video Sitemap
// 2. News Sitemap
// 3. KML/GEO Sitemap
// 4. Redirect Categories/Tags
// ============================================================

// ── VIDEO SITEMAP ─────────────────────────────────────────────
add_action('init', 'sgclabs_video_sitemap_init');
function sgclabs_video_sitemap_init() {
    add_rewrite_rule('^sgclabs-video-sitemap\.xml$', 'index.php?sgclabs_video_sitemap=1', 'top');
    add_rewrite_tag('%sgclabs_video_sitemap%', '1');
}
add_action('template_redirect', 'sgclabs_video_sitemap_output');
function sgclabs_video_sitemap_output() {
    if (!get_query_var('sgclabs_video_sitemap')) return;
    header('Content-Type: application/xml; charset=UTF-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";

    global $wpdb;
    $posts = $wpdb->get_results("
        SELECT p.ID, p.post_title, p.post_content, p.post_date, p.post_modified
        FROM {$wpdb->posts} p
        WHERE p.post_status='publish'
          AND p.post_type IN ('post','page','product')
          AND (p.post_content LIKE '%youtube.com%' OR p.post_content LIKE '%youtu.be%'
               OR p.post_content LIKE '%vimeo.com%' OR p.post_content LIKE '%<video%'
               OR p.post_content LIKE '%wp-video%' OR p.post_content LIKE '%iframe%src%video%')
        ORDER BY p.post_modified DESC
        LIMIT 1000
    ");

    foreach ($posts as $post) {
        $url = get_permalink($post->ID);
        $thumb = get_the_post_thumbnail_url($post->ID, 'large') ?: '';
        $videos = sgclabs_extract_video_urls($post->post_content);
        foreach ($videos as $vid) {
            echo "  <url>\n";
            echo "    <loc>" . esc_url($url) . "</loc>\n";
            echo "    <video:video>\n";
            echo "      <video:thumbnail_loc>" . esc_url($thumb) . "</video:thumbnail_loc>\n";
            echo "      <video:title>" . esc_xml($post->post_title) . "</video:title>\n";
            echo "      <video:description>" . esc_xml(wp_trim_words(wp_strip_all_tags($post->post_content), 30)) . "</video:description>\n";
            if (strpos((string)$vid, 'youtube') !== false || strpos((string)$vid, 'youtu.be') !== false || strpos((string)$vid, 'vimeo') !== false) {
                echo "      <video:player_loc>" . esc_url($vid) . "</video:player_loc>\n";
            } else {
                echo "      <video:content_loc>" . esc_url($vid) . "</video:content_loc>\n";
            }
            echo "      <video:publication_date>" . mysql2date('c', $post->post_date) . "</video:publication_date>\n";
            echo "    </video:video>\n";
            echo "  </url>\n";
        }
    }
    echo '</urlset>';
    exit;
}

function sgclabs_extract_video_urls($content) {
    $urls = [];
    // YouTube
    if (preg_match_all('/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([\w-]{11})/i', $content, $m)) {
        foreach ($m[1] as $id) $urls[] = "https://www.youtube.com/embed/{$id}";
    }
    // Vimeo
    if (preg_match_all('/(?:https?:\/\/)?(?:www\.)?vimeo\.com\/(\d+)/i', $content, $m)) {
        foreach ($m[1] as $id) $urls[] = "https://player.vimeo.com/video/{$id}";
    }
    // HTML5 video src
    if (preg_match_all('/<video[^>]*src=["\']([^"\']+)["\']/', $content, $m)) {
        foreach ($m[1] as $src) $urls[] = $src;
    }
    if (preg_match_all('/<source[^>]*src=["\']([^"\']+)["\']/', $content, $m)) {
        foreach ($m[1] as $src) $urls[] = $src;
    }
    return array_unique(array_slice($urls, 0, 5));
}

// ── NEWS SITEMAP ──────────────────────────────────────────────
add_action('init', 'sgclabs_news_sitemap_init');
function sgclabs_news_sitemap_init() {
    add_rewrite_rule('^sgclabs-news-sitemap\.xml$', 'index.php?sgclabs_news_sitemap=1', 'top');
    add_rewrite_tag('%sgclabs_news_sitemap%', '1');
}
add_action('template_redirect', 'sgclabs_news_sitemap_output');
function sgclabs_news_sitemap_output() {
    if (!get_query_var('sgclabs_news_sitemap')) return;
    header('Content-Type: application/xml; charset=UTF-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";

    $site_name = get_bloginfo('name');
    $lang = substr(get_locale(), 0, 2);
    $news_cats = get_option('sgclabs_news_sitemap_categories', '');
    $cat_filter = '';
    if ($news_cats) {
        $cat_ids = array_map('intval', explode(',', $news_cats));
        if (!empty($cat_ids)) {
            global $wpdb;
            $placeholders = implode(',', array_fill(0, count($cat_ids), '%d'));
            $cat_filter = $wpdb->prepare(
                " AND p.ID IN (SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ($placeholders))",
                $cat_ids
            );
        }
    }

    global $wpdb;
    $two_days_ago = date('Y-m-d H:i:s', strtotime('-2 days'));
    $posts = $wpdb->get_results($wpdb->prepare("
        SELECT p.ID, p.post_title, p.post_date
        FROM {$wpdb->posts} p
        WHERE p.post_status='publish' AND p.post_type='post' AND p.post_date >= %s {$cat_filter}
        ORDER BY p.post_date DESC LIMIT 1000
    ", $two_days_ago));

    foreach ($posts as $post) {
        $cats = wp_get_post_categories($post->ID, ['fields' => 'names']);
        $kw = implode(', ', array_slice($cats, 0, 5));
        echo "  <url>\n";
        echo "    <loc>" . esc_url(get_permalink($post->ID)) . "</loc>\n";
        echo "    <news:news>\n";
        echo "      <news:publication>\n";
        echo "        <news:name>" . esc_xml($site_name) . "</news:name>\n";
        echo "        <news:language>{$lang}</news:language>\n";
        echo "      </news:publication>\n";
        echo "      <news:publication_date>" . mysql2date('c', $post->post_date) . "</news:publication_date>\n";
        echo "      <news:title>" . esc_xml($post->post_title) . "</news:title>\n";
        if ($kw) echo "      <news:keywords>" . esc_xml($kw) . "</news:keywords>\n";
        echo "    </news:news>\n";
        echo "  </url>\n";
    }
    echo '</urlset>';
    exit;
}

// ── KML/GEO SITEMAP ──────────────────────────────────────────
add_action('init', 'sgclabs_kml_sitemap_init');
function sgclabs_kml_sitemap_init() {
    add_rewrite_rule('^sgclabs-geo-sitemap\.kml$', 'index.php?sgclabs_kml_sitemap=1', 'top');
    add_rewrite_tag('%sgclabs_kml_sitemap%', '1');
}
add_action('template_redirect', 'sgclabs_kml_sitemap_output');
function sgclabs_kml_sitemap_output() {
    if (!get_query_var('sgclabs_kml_sitemap')) return;
    header('Content-Type: application/vnd.google-earth.kml+xml; charset=UTF-8');

    $biz_name = get_option('sgclabs_local_biz_name', get_bloginfo('name'));
    $lat = get_option('sgclabs_local_latitude', '30.3378');
    $lon = get_option('sgclabs_local_longitude', '76.8601');
    $addr = get_option('sgclabs_local_address', 'Ambala Cantt, Haryana, India');
    $phone = get_option('sgclabs_local_phone', '+91-999-641-6935');
    $desc = get_option('sgclabs_local_description', $biz_name . ' — ' . $addr);

    // Multiple locations support
    $locations = get_option('sgclabs_local_locations', []);
    if (empty($locations)) {
        $locations = [['name' => $biz_name, 'lat' => $lat, 'lon' => $lon, 'addr' => $addr, 'phone' => $phone, 'desc' => $desc]];
    }

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<kml xmlns="http://www.opengis.net/kml/2.2">' . "\n";
    echo "<Document>\n";
    echo "  <name>" . esc_xml($biz_name) . "</name>\n";
    echo "  <description>" . esc_xml($desc) . "</description>\n";

    foreach ($locations as $loc) {
        $lname = isset($loc['name']) ? $loc['name'] : $biz_name;
        $llat = isset($loc['lat']) ? $loc['lat'] : $lat;
        $llon = isset($loc['lon']) ? $loc['lon'] : $lon;
        $laddr = isset($loc['addr']) ? $loc['addr'] : $addr;
        echo "  <Placemark>\n";
        echo "    <name>" . esc_xml($lname) . "</name>\n";
        echo "    <description>" . esc_xml($laddr) . "</description>\n";
        echo "    <Point><coordinates>{$llon},{$llat},0</coordinates></Point>\n";
        echo "  </Placemark>\n";
    }

    echo "</Document>\n</kml>";
    exit;
}

// ── REDIRECT CATEGORIES/TAGS ──────────────────────────────────
add_action('admin_post_sgclabs_save_taxonomy_redirects', function() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'sgclabs_tax_redir_nonce')) wp_die();
    $redirects = [];
    if (!empty($_POST['tax_from']) && is_array($_POST['tax_from'])) {
        foreach ($_POST['tax_from'] as $i => $from) {
            $from = sanitize_text_field( (string) $from );
            $to = sanitize_text_field($_POST['tax_to'][$i] ?? '');
            $type = intval($_POST['tax_type'][$i] ?? 301);
            if ($from && $to) {
                $redirects[] = ['from' => $from, 'to' => $to, 'type' => $type];
            }
        }
    }
    update_option('sgclabs_taxonomy_redirects', $redirects);
    wp_redirect(admin_url('admin.php?page=sgclabs-redirections&tab=taxonomy&saved=1'));
    exit;
});

add_action('template_redirect', 'sgclabs_apply_taxonomy_redirects', 1);
function sgclabs_apply_taxonomy_redirects() {
    if (is_admin()) return;
    $redirects = get_option('sgclabs_taxonomy_redirects', []);
    if (empty($redirects)) return;
    $current = trim(strtok($_SERVER['REQUEST_URI'], '?'), '/');
    foreach ($redirects as $r) {
        $from = trim($r['from'], '/');
        if ($current === $from || strpos((string)$current, $from) === 0) {
            wp_redirect($r['to'], $r['type']);
            exit;
        }
    }
}

// ── REGISTER SITEMAPS IN MAIN INDEX ───────────────────────────
add_filter('sgclabs_sitemap_index_entries', 'sgclabs_add_extended_sitemaps');
function sgclabs_add_extended_sitemaps($entries) {
    $entries[] = ['loc' => home_url('/sgclabs-video-sitemap.xml'), 'lastmod' => date('c')];
    $entries[] = ['loc' => home_url('/sgclabs-news-sitemap.xml'), 'lastmod' => date('c')];
    $entries[] = ['loc' => home_url('/sgclabs-geo-sitemap.kml'), 'lastmod' => date('c')];
    return $entries;
}

// ── FLUSH REWRITE ON ACTIVATION ───────────────────────────────
add_action('sgclabs_activation', function() {
    sgclabs_video_sitemap_init();
    sgclabs_news_sitemap_init();
    sgclabs_kml_sitemap_init();
    flush_rewrite_rules();
});

// helper
if (!function_exists('esc_xml')) {
    function esc_xml($str) {
        return htmlspecialchars($str, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}

// ============================================================
// HREFLANG SITEMAP — for Google multilingual product indexing
// URL: /sgclabs-hreflang-sitemap.xml
// ============================================================
add_action( 'init', 'sgclabs_hreflang_sitemap_init' );
function sgclabs_hreflang_sitemap_init() {
    add_rewrite_rule( '^sgclabs-hreflang-sitemap\.xml$', 'index.php?sgclabs_hreflang_sitemap=1', 'top' );
    add_filter( 'query_vars', function( $vars ) { $vars[] = 'sgclabs_hreflang_sitemap'; return $vars; } );
}

add_action( 'template_redirect', 'sgclabs_hreflang_sitemap_output' );
function sgclabs_hreflang_sitemap_output() {
    if ( ! get_query_var( 'sgclabs_hreflang_sitemap' ) ) return;

    if ( ! function_exists( 'sgclabs_get_languages' ) ) {
        status_header( 404 ); exit;
    }

    header( 'Content-Type: application/xml; charset=UTF-8' );
    $langs = sgclabs_get_languages();

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
    echo '        xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
    echo '<!-- SGC LABS® Hreflang Sitemap — Generated ' . gmdate('Y-m-d') . ' -->' . "\n";

    global $wpdb;
    $products = $wpdb->get_results(
        "SELECT ID, post_modified FROM {$wpdb->posts}
         WHERE post_type='product' AND post_status='publish'
         ORDER BY post_modified DESC LIMIT 2000"
    );

    foreach ( $products as $product ) {
        $base_url = get_permalink( $product->ID );
        $lastmod  = gmdate( 'Y-m-d', strtotime( $product->post_modified ) );

        echo "  <url>\n";
        echo '    <loc>' . esc_url( $base_url ) . "</loc>\n";
        echo '    <lastmod>' . esc_html( $lastmod ) . "</lastmod>\n";

        // x-default (canonical English)
        echo '    <xhtml:link rel="alternate" hreflang="x-default" href="' . esc_url( $base_url ) . '"/>' . "\n";

        // One entry per language — v6.6.5: proper BCP 47 hreflang codes
        foreach ( $langs as $lang_code => $lang_info ) {
            $hreflang_code = $lang_info['hreflang'] ?? $lang_code;
            $lang_url = add_query_arg( 'lang', $lang_code, $base_url );
            echo '    <xhtml:link rel="alternate" hreflang="' . esc_attr( $hreflang_code ) . '" href="' . esc_url( $lang_url ) . '"/>' . "\n";
        }

        echo "  </url>\n";
    }

    echo '</urlset>';
    exit;
}

// Add hreflang sitemap to sitemap index
add_filter( 'sgclabs_sitemap_index_entries', 'sgclabs_add_hreflang_to_index' );
function sgclabs_add_hreflang_to_index( array $entries ): array {
    $entries[] = [
        'loc'     => home_url( '/sgclabs-hreflang-sitemap.xml' ),
        'lastmod' => gmdate( 'Y-m-d' ),
    ];
    return $entries;
}

