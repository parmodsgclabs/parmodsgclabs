<?php
/**
 * Template: Announcement Bar — Frontend Output
 *
 * Variables available (extracted from $args):
 * @var string $text
 * @var string $bg
 * @var string $color
 * @var string $link
 * @var string $dismissible
 *
 * @package SGCLabs_Manager
 * @since   6.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<style>
.sgclabs-announcement {
    background: <?php echo esc_attr( $bg ); ?>;
    color: <?php echo esc_attr( $color ); ?>;
    text-align: center;
    padding: 10px 40px;
    font-size: 14px;
    font-weight: 600;
    position: relative;
    font-family: sans-serif;
}
.sgclabs-announcement a {
    color: <?php echo esc_attr( $color ); ?>;
    text-decoration: underline;
}
.sgclabs-ann-close {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    font-size: 18px;
    opacity: 0.8;
    background: none;
    border: none;
    color: <?php echo esc_attr( $color ); ?>;
}
.sgclabs-ann-close:hover { opacity: 1; }
</style>

<div class="sgclabs-announcement" id="sgclabs-ann">
    <?php if ( $link ) : ?>
        <a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $text ); ?></a>
    <?php else : ?>
        <?php echo esc_html( $text ); ?>
    <?php endif; ?>

    <?php if ( $dismissible === '1' ) : ?>
        <button class="sgclabs-ann-close"
                onclick="document.getElementById('sgclabs-ann').style.display='none'"
                aria-label="Close announcement">✕</button>
    <?php endif; ?>
</div>
