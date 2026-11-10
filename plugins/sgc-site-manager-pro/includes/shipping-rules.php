<?php
/**
 * Shipping Rules — Bridge File
 *
 * @package SGCLabs_Manager
 * @since   6.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/class-sgclabs-shipping-rules.php';

$sgclabs_shipping_rules = new SGCLabs_Shipping_Rules();
if ( $sgclabs_shipping_rules->is_active() ) {
    $sgclabs_shipping_rules->boot();
}

/** Admin menu callback. */
function sgclabs_shipping_rules_page() {
    global $sgclabs_shipping_rules;
    if ( $sgclabs_shipping_rules ) {
        $sgclabs_shipping_rules->admin_page();
    }
}
