<?php
if (!defined('ABSPATH')) exit;

/**
 * Nastavení pluginu Hadi (options stránka v administraci)
 * + kontrola přítomnosti ACF
 */

/* -----------------------------------------
 * ACF kontrola – admin notice
 * ----------------------------------------- */
add_action('admin_init', function () {
    if (!class_exists('ACF')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>Hadi – jádro:</strong> Chybí plugin Advanced Custom Fields. Aktivuj ACF, jinak některé funkce nebudou fungovat.</p></div>';
        });
    }
});

/* -----------------------------------------
 * ULOŽENÍ VOLEB
 * ----------------------------------------- */

function hadi_default_opts() {
    return [
        'pt'            => 'had',
        'tax'           => 'genotyp-hada',
        'menu_text'     => 'Dostupní hadi',
        'all_slug'      => 'hadi',
        'poptavka_slug' => 'poptavka',
        'per_page'      => 20,
        'sorting_ui'    => 1,
    ];
}

function hadi_get_opts() {
    return wp_parse_args(
        get_option('hadi_core_options', []),
        hadi_default_opts()
    );
}

function hadi_sanitize_opts($in) {
    $d = hadi_default_opts();

    return [
        'pt'            => sanitize_title($in['pt']            ?? $d['pt']),
        'tax'           => sanitize_title($in['tax']           ?? $d['tax']),
        'menu_text'     => sanitize_text_field($in['menu_text'] ?? $d['menu_text']),
        'all_slug'      => sanitize_title($in['all_slug']      ?? $d['all_slug']),
        'poptavka_slug' => sanitize_title($in['poptavka_slug'] ?? $d['poptavka_slug']),
        'per_page'      => max(1, intval($in['per_page']       ?? $d['per_page'])),
        'sorting_ui'    => empty($in['sorting_ui']) ? 0 : 1,
    ];
}

/* -----------------------------------------
 * ADMIN MENU – stránka Nastavení
 * ----------------------------------------- */

add_action('admin_menu', function () {
    add_options_page(
        'Hadi – nastavení',
        'Hadi',
        'manage_options',
        'hadi-core-settings',
        'hadi_render_settings_page'
    );
});

add_action('admin_init', function () {
    register_setting(
        'hadi_core_group',
        'hadi_core_options',
        [
            'type'              => 'array',
            'sanitize_callback' => 'hadi_sanitize_opts',
            'default'           => hadi_default_opts(),
        ]
    );

    add_settings_section(
        'hadi_core_sec',
        '',
        '__return_false',
        'hadi_core'
    );

    foreach (['pt','tax','menu_text','all_slug','poptavka_slug','per_page'] as $k) {
        add_settings_field(
            $k,
            ucfirst(str_replace('_',' ',$k)),
            'hadi_settings_field_text',
            'hadi_core',
            'hadi_core_sec',
            ['key' => $k]
        );
    }

    add_settings_field(
        'sorting_ui',
        'Zobrazit ovládání řazení',
        'hadi_settings_field_checkbox',
        'hadi_core',
        'hadi_core_sec',
        ['key' => 'sorting_ui']
    );
});

/* -----------------------------------------
 * Render jednotlivých polí
 * ----------------------------------------- */

function hadi_settings_field_text($args) {
    $o = hadi_get_opts();
    $k = $args['key'];
    $val = $o[$k] ?? '';
    echo "<input type='text' name='hadi_core_options[$k]' value='".esc_attr($val)."' class='regular-text' />";
}

function hadi_settings_field_checkbox($args) {
    $o = hadi_get_opts();
    $k = $args['key'];
    $chk = !empty($o[$k]) ? 'checked' : '';
    echo "<label><input type='checkbox' name='hadi_core_options[$k]' value='1' $chk /> zapnuto</label>";
}

/* -----------------------------------------
 * Render celé stránky Nastavení
 * ----------------------------------------- */

function hadi_render_settings_page() { ?>
    <div class="wrap">
        <h1>Hadi – nastavení</h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('hadi_core_group');
            do_settings_sections('hadi_core');
            submit_button();
            ?>
        </form>
    </div>
<?php }
