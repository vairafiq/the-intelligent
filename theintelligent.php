<?php
/**
 * Plugin Name: The Intelligent
 * Description: Increase you shop sale with a Machine Learning Algorithm.
 * Version: 1.0
 * Author: Exlac
 * Text Domain: theintelligent
 * Domain Path: /languages/
 * WC tested up to: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

// Setup The Consts.
if ( ! defined( 'theInt_PLUGIN_FILE' ) ) {
    define( 'theInt_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'theInt_PLUGIN_PATH' ) ) {
    define( 'theInt_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'theInt_LANG_PATH' ) ) {
    define( 'theInt_LANG_PATH', dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
if ( ! defined( 'theInt_TEMPLATE_PATH' ) ) {
    define( 'theInt_TEMPLATE_PATH', theInt_PLUGIN_PATH . 'templates/' );
}
// Include The App.
$app = theInt_PLUGIN_PATH . 'includes/init.php';
if ( ! class_exists( 'theIntelligent' ) ) {
    include $app;
}