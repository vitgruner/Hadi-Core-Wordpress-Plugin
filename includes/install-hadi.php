<?php
if (!defined('ABSPATH')) exit;

/**
 * Aktivace a deaktivace pluginu
 * - vytvoření šablony /templates/hadi-archive.php
 * - flush rewrite rules
 */

function hadi_plugin_activate() {

    // vytvořit templates/ pokud neexistuje
    $tpl_dir = HADI_CORE_PATH . 'templates';
    if (!is_dir($tpl_dir)) {
        wp_mkdir_p($tpl_dir);
    }

    // vytvořit archiv šablonu, pokud chybí
    $tpl = $tpl_dir . '/hadi-archive.php';

    if (!file_exists($tpl)) {
        file_put_contents(
            $tpl,
"<?php
get_header();
\$term = get_queried_object(); ?>

<div class=\"section\"><div class=\"container\">
<h1><?php echo esc_html(single_term_title('', false)); ?></h1>

<?php echo do_shortcode('[nabidka_hadu dostupnost=\"all\" druh=\"' . esc_attr(\$term->slug) . '\"]'); ?>

</div></div>

<?php get_footer(); ?>"
        );
    }

    flush_rewrite_rules();
}

register_activation_hook(HADI_CORE_FILE, 'hadi_plugin_activate');


function hadi_plugin_deactivate() {
    flush_rewrite_rules();
}

register_deactivation_hook(HADI_CORE_FILE, 'hadi_plugin_deactivate');
