<?php
if ( ! defined( 'ABSPATH' ) ) {
    die;
}

function theint_load_template( $template_file, $args = array() ) {
    if ( is_array( $args ) ) {
        extract( $args );
    }

    $theme_template  = '/the-intelligent/' . $template_file . '.php';
    $plugin_template = theInt_TEMPLATE_PATH . $template_file . '.php';

    if ( file_exists( get_stylesheet_directory() . $theme_template ) ) {
        $file = get_stylesheet_directory() . $theme_template;
    } elseif ( file_exists( get_template_directory() . $theme_template ) ) {
        $file = get_template_directory() . $theme_template;
    } else {
        $file = $plugin_template;
    }

    if ( file_exists( $file ) ) {
        include $file;
    }
}