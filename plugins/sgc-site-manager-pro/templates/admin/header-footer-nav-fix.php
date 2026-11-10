<?php
/**
 * Template: Navigation Menu Fix — Frontend CSS
 *
 * @var string $nav_font_size
 * @var string $nav_menu_bg
 * @var string $nav_menu_color
 * @var string $nav_drop_bg
 * @var string $nav_drop_color
 *
 * @package SGCLabs_Manager
 * @since   6.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<style>
/* ===== SGC LABS® Navigation Fix ===== */
.main-navigation a, .nav-menu a, .primary-menu a,
nav.site-navigation a, #site-navigation a, .menu-item a,
header nav a, .header-menu a {
    font-size: <?php echo intval( $nav_font_size ); ?>px !important;
}

.main-navigation, .nav-menu, .primary-menu,
#site-navigation, nav.site-navigation {
    position: relative !important;
    z-index: 9999 !important;
    isolation: isolate !important;
}

.main-navigation ul ul, .nav-menu ul ul, .primary-menu ul ul,
#site-navigation ul ul, nav.site-navigation ul ul {
    display: none !important;
    position: absolute !important;
    top: 100% !important;
    left: 0 !important;
    z-index: 9999 !important;
    min-width: 200px !important;
    background: <?php echo esc_attr( $nav_drop_bg ); ?> !important;
    box-shadow: 0 4px 15px rgba(0,0,0,0.25) !important;
    border-radius: 0 0 6px 6px !important;
    padding: 5px 0 !important;
    transform: translate3d(0, 0, 0) !important;
    pointer-events: auto !important;
}

.main-navigation ul li.menu-item-has-children:hover > ul,
.nav-menu li.menu-item-has-children:hover > ul,
.primary-menu li.menu-item-has-children:hover > ul,
#site-navigation ul li.menu-item-has-children:hover > ul,
nav.site-navigation ul li.menu-item-has-children:hover > ul {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    pointer-events: auto !important;
}

.main-navigation ul ul li a, .nav-menu ul ul li a,
#site-navigation ul ul li a, nav.site-navigation ul ul li a {
    display: block !important;
    padding: 10px 18px !important;
    color: <?php echo esc_attr( $nav_drop_color ); ?> !important;
    background: <?php echo esc_attr( $nav_drop_bg ); ?> !important;
    font-size: calc(<?php echo intval( $nav_font_size ); ?>px - 1px) !important;
    white-space: nowrap !important;
    border-bottom: 1px solid rgba(255,255,255,0.08) !important;
}

.main-navigation ul ul li a:hover, .nav-menu ul ul li a:hover,
#site-navigation ul ul li a:hover {
    background: rgba(255,255,255,0.12) !important;
    padding-left: 24px !important;
}

.menu-item-has-children, .main-navigation li,
#site-navigation ul li, nav.site-navigation ul li {
    position: relative !important;
    overflow: visible !important;
}

.menu-item-has-children > a::after {
    content: ' ▾' !important;
    font-size: 10px !important;
    opacity: 0.8 !important;
}

@media (max-width: 768px) {
    .main-navigation ul ul, #site-navigation ul ul,
    nav.site-navigation ul ul {
        position: static !important;
        width: 100% !important;
        box-shadow: none !important;
        transform: none !important;
    }
    .main-navigation li.menu-item-has-children:hover > ul,
    #site-navigation li.menu-item-has-children:hover > ul {
        display: block !important;
    }
}
</style>
