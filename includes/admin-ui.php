<?php
if (!defined('ABSPATH')) exit;

/* ACF vzhled */
add_action('admin_head', function() {
    global $post;
    if ($post && $post->post_type === HADI_PT) {
        echo '<style>
          .acf-postbox .acf-fields {
            max-width:800px; margin:0 auto; background:#fff;
            padding:20px 30px; border-radius:10px;
            box-shadow:0 0 10px rgba(0,0,0,0.05);
          }
        </style>';
    }
});

/* Skrytí betheme edit button */
add_action('admin_head', function() {
    $screen = get_current_screen();
    if ($screen && $screen->post_type === HADI_PT) {
        echo '<style>
          .mfn-live-edit-page-button { display:none!important; }
        </style>';
    }
});
