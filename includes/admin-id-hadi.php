<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin rozšíření pro CPT HADI:
 *  - sloupec "ID" v přehledu
 *  - ID jako box nad editorem
 */

/**
 * 1) Přidání sloupce ID do seznamu hadů
 */
function hadi_admin_add_id_column($columns) {
    $columns['had_id'] = 'ID';
    return $columns;
}
add_filter('manage_' . HADI_PT . '_posts_columns', 'hadi_admin_add_id_column');

/**
 * 2) Výpis hodnoty ID ve sloupci
 */
function hadi_admin_render_id_column($column, $post_id) {
    if ($column === 'had_id') {
        echo (int) $post_id;
    }
}
add_action('manage_' . HADI_PT . '_posts_custom_column', 'hadi_admin_render_id_column', 10, 2);

/**
 * 3) Nastavení sloupce jako řaditelného
 */
function hadi_admin_sortable_id_column($columns) {
    $columns['had_id'] = 'ID';
    return $columns;
}
add_filter('manage_edit-' . HADI_PT . '_sortable_columns', 'hadi_admin_sortable_id_column');

/**
 * 4) Zobrazení ID přímo v editoru hada
 */
function hadi_admin_show_id_above_editor($post) {
    if ($post->post_type !== HADI_PT) return;

    echo '<div style="
        padding:10px 15px;
        background:#f1f1f1;
        border:1px solid #ccc;
        margin-bottom:15px;
        font-size:14px;
    ">
        <strong>ID tohoto hada:</strong> ' . (int) $post->ID . '
    </div>';
}
add_action('edit_form_after_title', 'hadi_admin_show_id_above_editor');
