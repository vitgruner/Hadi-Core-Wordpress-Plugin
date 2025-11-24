<?php
if (!defined('ABSPATH')) exit;

/**
 * Enqueue CSS a JS pro frontend
 */

function hadi_enqueue_assets() {
    wp_enqueue_style(
        'hadi-core',
        plugin_dir_url(__FILE__) . '../assets/hadi-core.css',
        [],
        '1.0.0'
    );

    // pokud budeš někdy chtít JS:
    // wp_enqueue_script(
    //     'hadi-core-js',
    //     plugin_dir_url(__FILE__) . '../assets/hadi-core.js',
    //     ['jquery'],
    //     '1.0.0',
    //     true
    // );
}
add_action('wp_enqueue_scripts', 'hadi_enqueue_assets');
