<?php
if (!defined('ABSPATH')) exit;

/**
 * Archiv taxonomie – /genotyp-hada/slug/
 * Přesměruje na šablonu templates/hadi-archive.php,
 * aby se použil shortcode [nabidka_hadu] se stejným vzhledem.
 */

function hadi_archive_template_include($template) {
    if (is_tax(HADI_TAX)) {
        $tpl = HADI_CORE_PATH . 'templates/hadi-archive.php';
        if (file_exists($tpl)) {
            return $tpl;
        }
    }
    return $template;
}
add_filter('template_include', 'hadi_archive_template_include');
