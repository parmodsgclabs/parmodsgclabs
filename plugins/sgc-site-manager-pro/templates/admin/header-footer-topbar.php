<?php
/**
 * Template: Header Top Bar — Frontend
 *
 * @var string $phone
 * @var string $email
 * @var string $whatsapp
 * @var string $bg
 * @var string $color
 *
 * @package SGCLabs_Manager
 * @since   6.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<style>
.sgclabs-top-bar { background:<?php echo esc_attr( $bg ); ?>; color:<?php echo esc_attr( $color ); ?>; padding:8px 20px; text-align:center; font-size:13px; font-family:sans-serif; z-index:100; position:relative; }
.sgclabs-top-bar a { color:<?php echo esc_attr( $color ); ?>; text-decoration:none; margin:0 12px; }
.sgclabs-top-bar a:hover { text-decoration:underline; }
@media(max-width:600px){ .sgclabs-top-bar { font-size:11px; padding:6px 10px; } }
</style>
<div class="sgclabs-top-bar">
    <?php if ( $phone ) : ?>
        <a href="tel:<?php echo esc_attr( $phone ); ?>">📞 <?php echo esc_html( $phone ); ?></a>
    <?php endif; ?>
    <?php if ( $email ) : ?>
        <a href="mailto:<?php echo esc_attr( $email ); ?>">✉️ <?php echo esc_html( $email ); ?></a>
    <?php endif; ?>
    <?php if ( $whatsapp ) : ?>
        <a href="https://api.whatsapp.com/send/?phone=<?php echo esc_attr( $whatsapp ); ?>" target="_blank">💬 WhatsApp</a>
    <?php endif; ?>
</div>
