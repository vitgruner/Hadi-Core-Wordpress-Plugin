<?php
if (!defined('ABSPATH')) exit;

/**
 * Dynamické menu – automatické doplnění kategorií hadů
 * do položky menu směřující na stránku /hadi/.
 */

/* ---------------------------------------------
   Helper – vytvoří syntetickou položku menu
--------------------------------------------- */
function hadi_menu_make_item($title, $url, $parent_id = 0) {
    $o = new stdClass();
    $o->ID               = -abs(crc32($title.$url.mt_rand()));
    $o->db_id            = $o->ID;
    $o->menu_item_parent = $parent_id;
    $o->object           = 'custom';
    $o->type             = 'custom';
    $o->type_label       = __('Custom Link');
    $o->title            = $title;
    $o->url              = $url ?: '#';
    $o->classes          = [];
    $o->post_type        = 'nav_menu_item';
    $o->current          = false;
    $o->current_item_ancestor = false;
    $o->current_item_parent   = false;
    return $o;
}

/* ---------------------------------------------
   Index menu → ID termu (kvůli caching)
--------------------------------------------- */
function hadi_menu_get_menu_term_id($args) {
    if (!empty($args->menu) && is_object($args->menu) && isset($args->menu->term_id))
        return intval($args->menu->term_id);

    if (!empty($args->menu) && is_numeric($args->menu))
        return intval($args->menu);

    if (!empty($args->menu->slug)) {
        $m = wp_get_nav_menu_object($args->menu->slug);
        if ($m && !is_wp_error($m)) return intval($m->term_id);
    }
    return 0;
}

/* ---------------------------------------------
   Build položek menu z taxonomie (cache 1 hod)
--------------------------------------------- */
function hadi_menu_build_term_items_cached($menu_term_id, $parent_id) {

    $cache_key = "hadi_menu_terms_{$menu_term_id}";
    $cached = get_transient($cache_key);
    if (is_array($cached)) return $cached;

    $items = [];
    $terms = get_terms([
        'taxonomy'   => HADI_TAX,
        'hide_empty' => true,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ]);

    if (!is_wp_error($terms)) {
        foreach ($terms as $term) {

            // načteme pohlaví
            $pohlavi = get_term_meta($term->term_id, 'term_pohlavi', true);
            $pohlavi_lc = is_string($pohlavi) ? mb_strtolower($pohlavi, 'UTF-8') : '';

            // symbol pohlaví
            $symbol = '';
            if ($pohlavi_lc === 'samec')  $symbol = ' ♂';
            elseif ($pohlavi_lc === 'samice') $symbol = ' ♀';
            elseif ($pohlavi_lc === 'oboji')  $symbol = ' ♂♀';

            // base name bez starých symbolů
            $base = preg_replace('/[♂♀⚤]\s*$/u', '', $term->name);
            $base = trim($base);

            $title = $base . $symbol;

            $url = get_term_link($term);
            if (is_wp_error($url)) $url = '#';

            $items[] = hadi_menu_make_item($title, $url, $parent_id);
        }
    }

    set_transient($cache_key, $items, HOUR_IN_SECONDS);
    return $items;
}

/* ---------------------------------------------
   Flush cache při změně termů a hadů
--------------------------------------------- */
function hadi_menu_flush_cache_all() {
    $menus = wp_get_nav_menus();
    foreach ($menus as $m) {
        delete_transient("hadi_menu_terms_{$m->term_id}");
    }
}
add_action('created_' . HADI_TAX,  'hadi_menu_flush_cache_all');
add_action('edited_'  . HADI_TAX,  'hadi_menu_flush_cache_all');
add_action('delete_'  . HADI_TAX,  'hadi_menu_flush_cache_all');
add_action('save_post_' . HADI_PT, 'hadi_menu_flush_cache_all');
add_action('trashed_post',         'hadi_menu_flush_cache_all');
add_action('untrashed_post',       'hadi_menu_flush_cache_all');
add_action('wp_update_nav_menu',   'hadi_menu_flush_cache_all');

/* ---------------------------------------------
   Inject položek do wp_nav_menu_objects
--------------------------------------------- */
add_filter('wp_nav_menu_objects', function ($items, $args) {

    // 1) Najdeme rodiče – položku směřující na /hadi/
    $parent = null;
    foreach ($items as $it) {
        if (empty($it->url)) continue;

        $path = parse_url($it->url, PHP_URL_PATH);
        $path = trim($path, '/');

        if ($path === HADI_ALL_PAGE) {
            $parent = $it;
            break;
        }
    }

    if (!$parent) return $items; // není v menu položka s /hadi

    // 2) Generujeme děti
    $menu_term_id = hadi_menu_get_menu_term_id($args);
    $new_items    = hadi_menu_build_term_items_cached($menu_term_id, $parent->ID);

    if (empty($new_items)) return $items;

    // 3) Odstraníme původní děti rodiče
    $parent_id = intval($parent->ID);

    $items = array_values(array_filter($items, function ($i) use ($parent_id) {
        return intval($i->menu_item_parent) !== $parent_id;
    }));

    // 4) Připojíme nové
    $items = array_merge($items, $new_items);

    return $items;

}, 10, 2);
