<?php
/**
 * SGC LABS® — RankMath & RankMath Pro Bridge
 *
 * STRATEGY: RankMath = Single Source of Truth.
 * Manager Pro reads FROM RankMath's DB keys and writes BACK to same keys.
 * No duplicate meta. No filter conflict. No output competition.
 *
 * Coordination rules:
 *  1. If RankMath active → NEVER add our own <title> or <meta description>
 *  2. Our AI tools write to rank_math_title / rank_math_description (same keys RM reads)
 *  3. If RankMath NOT active → our seo-metabox.php outputs meta
 *  4. Schema output: Manager Pro adds only what RankMath is missing
 *
 * @package   SGCLabs
 * @since     7.0.0
 */

defined( 'ABSPATH' ) || exit;

// Detection helpers

function sgclabs_rm_is_active() {
    return defined( 'RANK_MATH_VERSION' );
}

function sgclabs_rm_is_pro() {
    return defined( 'RANK_MATH_PRO_VERSION' );
}

// Disable our own meta output when RankMath is active

add_action( 'init', function() {
    if ( ! sgclabs_rm_is_active() ) return;
    add_filter( 'sgclabs_should_output_meta', '__return_false', 5 );
    remove_filter( 'wp_title',               'sgclabs_custom_title',       10 );
    remove_filter( 'pre_get_document_title', 'sgclabs_pre_document_title', 10 );
    if ( sgclabs_rm_is_pro() ) {
        add_filter( 'sgclabs_should_output_og', '__return_false', 5 );
    }
} );

// Read helpers

function sgclabs_get_seo_title( $post_id = 0 ) {
    if ( ! $post_id ) $post_id = get_the_ID();
    if ( sgclabs_rm_is_active() ) {
        $t = get_post_meta( $post_id, 'rank_math_title', true );
        if ( $t ) return $t;
        return get_the_title( $post_id ) . ' | ' . get_bloginfo( 'name' );
    }
    $t = get_post_meta( $post_id, '_sgclabs_seo_title', true );
    return $t ?: get_the_title( $post_id ) . ' | ' . get_bloginfo( 'name' );
}

function sgclabs_get_seo_description( $post_id = 0 ) {
    if ( ! $post_id ) $post_id = get_the_ID();
    if ( sgclabs_rm_is_active() ) {
        $d = get_post_meta( $post_id, 'rank_math_description', true );
        return $d ?: wp_trim_words( get_the_excerpt( $post_id ), 25, '...' );
    }
    $d = get_post_meta( $post_id, '_sgclabs_seo_desc', true );
    return $d ?: wp_trim_words( get_the_excerpt( $post_id ), 25, '...' );
}

function sgclabs_get_homepage_seo() {
    $result = [ 'title' => '', 'description' => '', 'source' => 'none' ];
    if ( sgclabs_rm_is_active() ) {
        $rm = get_option( 'rank_math_general_settings', [] );
        $result['title']       = $rm['homepage_title']       ?? '';
        $result['description'] = $rm['homepage_description'] ?? '';
        $result['source']      = 'rankmath';
        $front = (int) get_option( 'page_on_front' );
        if ( $front ) {
            $t = get_post_meta( $front, 'rank_math_title', true );
            $d = get_post_meta( $front, 'rank_math_description', true );
            if ( $t ) { $result['title'] = $t; $result['source'] = 'rankmath_postmeta'; }
            if ( $d )   $result['description'] = $d;
        }
        return $result;
    }
    $result['title']       = get_option( 'sgclabs_seo_homepage_title', get_bloginfo( 'name' ) );
    $result['description'] = get_option( 'sgclabs_seo_homepage_desc', '' );
    $result['source']      = 'sgclabs';
    return $result;
}

// Write helpers

function sgclabs_save_post_seo( $post_id, $title, $description, $focus_kw = '' ) {
    if ( ! $post_id ) return false;
    if ( sgclabs_rm_is_active() ) {
        update_post_meta( $post_id, 'rank_math_title',          sanitize_text_field( $title ) );
        update_post_meta( $post_id, 'rank_math_description',    sanitize_textarea_field( $description ) );
        update_post_meta( $post_id, 'rank_math_og_title',       sanitize_text_field( $title ) );
        update_post_meta( $post_id, 'rank_math_og_description', sanitize_textarea_field( $description ) );
        if ( $focus_kw ) update_post_meta( $post_id, 'rank_math_focus_keyword', sanitize_text_field( $focus_kw ) );
        return 'rankmath';
    }
    update_post_meta( $post_id, '_sgclabs_seo_title', sanitize_text_field( $title ) );
    update_post_meta( $post_id, '_sgclabs_seo_desc',  sanitize_textarea_field( $description ) );
    return 'sgclabs';
}

function sgclabs_save_homepage_seo( $title, $description ) {
    $title       = sanitize_text_field( $title );
    $description = sanitize_textarea_field( $description );
    if ( sgclabs_rm_is_active() ) {
        $rm = get_option( 'rank_math_general_settings', [] );
        $rm['homepage_title']       = $title;
        $rm['homepage_description'] = $description;
        update_option( 'rank_math_general_settings', $rm );
        $front = (int) get_option( 'page_on_front' );
        if ( $front ) {
            update_post_meta( $front, 'rank_math_title',          $title );
            update_post_meta( $front, 'rank_math_description',    $description );
            update_post_meta( $front, 'rank_math_og_title',       $title );
            update_post_meta( $front, 'rank_math_og_description', $description );
        }
        delete_transient( 'rank_math_sitemap_home_url' );
        return 'rankmath';
    }
    update_option( 'sgclabs_seo_homepage_title', $title );
    update_option( 'sgclabs_seo_homepage_desc',  $description );
    return 'sgclabs';
}

// Schema parity — add only what RankMath is missing

add_action( 'wp_head', function() {
    if ( ! sgclabs_rm_is_active() ) return;
    $schema = [];
    if ( is_front_page() && get_option( 'sgclabs_export_schema_active' ) ) {
        $schema[] = [
            '@type'        => 'ExportAction',
            'actionStatus' => 'https://schema.org/ActiveActionStatus',
            'object'       => [ '@type' => 'Product', 'name' => 'Laboratory Glassware Export' ],
            'agent'        => [ '@type' => 'Organization', 'name' => get_bloginfo( 'name' ), 'url' => home_url() ],
        ];
    }
    if ( ! empty( $schema ) ) {
        echo '<script type="application/ld+json">' .
            wp_json_encode( [ '@context' => 'https://schema.org', '@graph' => $schema ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) .
            "</script>\n";
    }
}, 99 );

// Dashboard status card

add_action( 'sgclabs_dashboard_after_cards', function() {
    $rm_active   = sgclabs_rm_is_active();
    $rm_pro      = sgclabs_rm_is_pro();
    $rm_ver      = defined( 'RANK_MATH_VERSION' ) ? RANK_MATH_VERSION : '';
    $rmp_ver     = defined( 'RANK_MATH_PRO_VERSION' ) ? RANK_MATH_PRO_VERSION : '';
    $homepage    = sgclabs_get_homepage_seo();
    ?>
    <div class="sgclabs-section" style="margin-top:16px">
    <h3>RankMath Integration</h3>
    <table class="widefat" style="max-width:600px"><tbody>
        <tr><td>RankMath Free</td><td><?php echo $rm_active ? '<span style="color:#27ae60">&#10003; v' . esc_html($rm_ver) . '</span>' : '<span style="color:#999">Not active</span>'; ?></td></tr>
        <tr><td>RankMath Pro</td><td><?php echo $rm_pro ? '<span style="color:#27ae60">&#10003; v' . esc_html($rmp_ver) . '</span>' : '<span style="color:#999">Not active</span>'; ?></td></tr>
        <tr><td>Meta output</td><td><?php echo $rm_active ? '<span style="color:#27ae60">RankMath (no conflict)</span>' : '<span style="color:#0073aa">SGC Manager Pro</span>'; ?></td></tr>
        <tr><td>Homepage title source</td><td><code><?php echo esc_html($homepage['source']); ?></code> &mdash; <em><?php echo esc_html($homepage['title'] ?: '(not set)'); ?></em></td></tr>
        <tr><td>AI writes to</td><td><code><?php echo $rm_active ? 'rank_math_title, rank_math_description' : 'sgclabs_seo_homepage_*'; ?></code></td></tr>
    </tbody></table>
    </div>
    <?php
} );
