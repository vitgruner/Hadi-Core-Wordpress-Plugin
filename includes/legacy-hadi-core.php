<?php
/**
 * Plugin Name: Hadi – jádro (shortcode, menu, archiv)
 * Description: Shortcode [nabidka_hadu], archiv taxonomie přes shortcode, dynamické menu kategorií „Hadi (auto)“, ACF admin UI pro CPT „had“. Lightbox pro obrázky
 * Version: 1.4.0
 * Author: gruner.digital
 * Requires at least: 6.0
 * Requires PHP: 7.4
 

if (!defined('ABSPATH')) exit;

/** ==== NASTAVENÍ (změň podle potřeby) ==== 
define('HADI_PT',        'had');         // post type
define('HADI_TAX',       'genotyp-hada');   // taxonomie
define('HADI_MENU_TEXT', 'Dostupní hadi'); // přesný text placeholderu v menu
define('HADI_ALL_PAGE',  'hadi');        // slug stránky s přehledem všech hadů ([nabidka_hadu])
define('HADI_POPTV_SLUG','poptavka');    // slug stránky Poptávka (pro tlačítko)
/** ======================================= */

/** Cesta pluginu 
define('HADI_CORE_PATH', plugin_dir_path(__FILE__));
define('HADI_CORE_URL',  plugin_dir_url(__FILE__));

/* ========== ACF kontrola ========== 
add_action('admin_init', function () {
  if (!class_exists('ACF')) {
    add_action('admin_notices', function () {
      echo '<div class="notice notice-error"><p><strong>Hadi – jádro:</strong> Chybí plugin Advanced Custom Fields. Aktivuj ACF, jinak některé funkce nebudou fungovat.</p></div>';
    });
  }
});

/* ========== Helper: cena ========== 
if (!function_exists('had_format_price')) {
  function had_format_price($value, $suffix = ' Kč') {
    if ($value === '' || $value === null) return '';
    return number_format((float)$value, 0, ',', ' ') . $suffix;
  }
}


/* ========== ADMIN UI (jen pro CPT „had“) ========== 
add_action('admin_head', function() {
  global $post;
  if ($post && $post->post_type === HADI_PT) {
    echo '<style>
      .acf-postbox .acf-fields { max-width: 800px; margin: 0 auto; background: #fff; padding: 20px 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
      .acf-field { border: none !important; }
      .acf-postbox h2.hndle { text-align: center; font-size: 20px; font-weight: 600; padding: 15px 0; }
      .acf-field input, .acf-field select, .acf-field textarea { max-width: 100%; }
    </style>';
  }
});
add_action('admin_head', function() {
  echo '<style>
    #editor .postbox:last-child > .inside { max-width: 800px; margin: 0 auto !important; background: #fff; padding: 20px 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
  </style>';
});


add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style(
        'hadi-core',
        plugin_dir_url(__FILE__) . 'assets/hadi-core.css',
        [],
        '1.0.0'
    );
});
*/

/* ========== Shortcode [nabidka_hadu] ========== 
add_shortcode('nabidka_hadu', function($atts){
  $a = shortcode_atts([
    'per_page'   => 12,             // kolik karet
    'orderby'    => 'menu_order',   // menu_order|date|cena|title
    'order'      => 'ASC',          // ASC|DESC
    'dostupnost' => 'all',          // available|reserved|sold|all
    'druh'       => '',             // slug/slugy taxonomie HADI_TAX (oddělit čárkou)
  ], $atts, 'nabidka_hadu');

  // --- WP_Query ---
  $args = [
    'post_type'      => HADI_PT,
    'posts_per_page' => (int)$a['per_page'],
    'order'          => in_array(strtoupper($a['order']), ['ASC','DESC']) ? strtoupper($a['order']) : 'ASC',
    'post_status'    => 'publish',
  ];
  if ($a['orderby'] === 'cena') {
    $args['orderby'] = 'meta_value_num';
    $args['meta_key'] = 'cena';
  } elseif (in_array($a['orderby'], ['menu_order','date','title'])) {
    $args['orderby'] = $a['orderby'];
  } else {
    $args['orderby'] = 'menu_order';
  }

  if ($a['dostupnost'] !== 'all') {
    $args['meta_query'][] = [
      'key'     => 'dostupnost',
      'value'   => $a['dostupnost'],
      'compare' => '='
    ];
  }

  if (!empty($a['druh'])) {
    $args['tax_query'] = [[
      'taxonomy' => HADI_TAX,
      'field'    => 'slug',
      'terms'    => array_map('trim', explode(',', $a['druh'])),
    ]];
  }

  $q = new WP_Query($args);


  // --- Helper na ACF/meta --- //
  $get = function($key, $id){
    if (function_exists('get_field')) {
      $v = get_field($key, $id);
      if ($v !== null && $v !== '') return $v;
    }
    return get_post_meta($id, $key, true);
  };

  ob_start();
 
  echo '<div class="had-grid">';

  // skupiny pro řazení
  $groups = [
    'available' => [],
    'reserved'  => [],
    'sold'      => [],
    'other'     => [],
  ];

  if ($q->have_posts()) {
    while ($q->have_posts()) {
      $q->the_post();
      $id = get_the_ID();

     // Obrázek: Featured / ACF single image / URL / placeholder
$img_html = '';
$img_id   = 0;
$img_url  = '';

/* 1) Featured image 
if (has_post_thumbnail($id)) {
    $img_id   = get_post_thumbnail_id($id);
    $img_html = get_the_post_thumbnail($id, 'large', ['class' => 'had-card__img']);
    $img_url  = wp_get_attachment_url($img_id);
}

/* 2) ACF / META, pokud není thumbnail 
if (!$img_html) {
    $acf_image_fields = ['obrazek','foto','image','fotka','hlavni_obrazek'];
    foreach ($acf_image_fields as $field_key) {
        $img_val = function_exists('get_field')
            ? get_field($field_key, $id)
            : get_post_meta($id, $field_key, true);

        if (!$img_val) continue;

        if (is_numeric($img_val)) {
            $img_id = (int) $img_val;
            break;

        } elseif (is_string($img_val) && filter_var($img_val, FILTER_VALIDATE_URL)) {
            $img_url = $img_val;
            break;

        } elseif (is_array($img_val)) {
            if (!empty($img_val['ID'])) {
                $img_id = (int) $img_val['ID'];
                break;
            }
            if (!empty($img_val['url'])) {
                $img_url = $img_val['url'];
                break;
            }
        }
    }

    if ($img_id) {
        $img_html = wp_get_attachment_image($img_id, 'large', false, ['class' => 'had-card__img']);
        if (!$img_url) {
            $img_url = wp_get_attachment_url($img_id); // full-size
        }

    } elseif ($img_url) {
        $img_html = '<img class="had-card__img" src="' . esc_url($img_url) . '" alt="' . esc_attr(get_the_title($id)) . '">';
    }
}

/* 3) BETHEME LIGHTBOX WRAP 
if ($img_html && $img_url) {
    $img_html =
        '<a href="' . esc_url($img_url) . '#' . intval($id) . '" 
            rel="lightbox" 
            data-lightbox-type="image">
            <div class="mask"></div>' .
            $img_html .
        '</a>';
}

/* 4) Placeholder 
if (!$img_html) {
    $img_html = '<div class="had-card__img had-card__img--ph"></div>';
}


     // Data
$ozn       = $get('oznaceni',       $id);
$morf      = $get('morf',           $id);
$pohl      = $get('pohlavi',        $id);
$dnar      = $get('datum_narozeni', $id);
$vaha      = $get('vaha',           $id);
$rod       = $get('rodice',         $id);
$stav      = $get('dostupnost',     $id);

$cena      = $get('cena',           $id);
$cena_eur  = $get('cena_eur',       $id);

$popis     = $get('kratky_popis',   $id);
$odber     = $get('odber',          $id);

// Převod odběru na text (jen když je had dostupný)
$odber_txt = '';
if ($stav === 'available' && $odber) {
    if ($odber === 'ihned') {
        $odber_txt = 'Ihned k odběru';
    } elseif ($odber === 'po_zakrmeni') {
        $odber_txt = 'K odběru po rozkrmení';
    }
}

// Klíč pro skupinu
$stav_key = $stav ?: 'available';
if (!isset($groups[$stav_key])) $stav_key = 'other';

// Textové zobrazení stavu
$stav_txt    = ($stav === 'reserved') ? 'Rezervováno'
              : (($stav === 'sold') ? 'Prodáno' : 'Dostupné');

$badge_class = ($stav === 'reserved') ? 'is-reserved'
              : (($stav === 'sold') ? 'is-sold' : 'is-available');

// Datum narození – formát
$dnar_out = '';
if (!empty($dnar)) {
    $t = strtotime($dnar);
    $dnar_out = $t ? date_i18n('j. n. Y', $t) : $dnar;
}

      // Druhy (taxonomie)
      $druhy_out = '';
      $terms = get_the_terms($id, HADI_TAX);
      if ($terms && !is_wp_error($terms)) {
        $names = array_map(function($t){ return $t->name; }, $terms);
        $druhy_out = implode(', ', $names);
      }

     // Poptávka – odkaz na stránku se slugem HADI_POPTV_SLUG (pokud existuje)
$poptavka_link = '#';
$poptavka_p = get_page_by_path(HADI_POPTV_SLUG);
if ($poptavka_p) {
  $hash = sprintf(
    '#had=%s&oznaceni=%s&morf=%s&cena=%s&cena_eur=%s',
    rawurlencode($id),
    rawurlencode($ozn),
    rawurlencode($morf),
    rawurlencode($cena),
    rawurlencode($cena_eur)
  );
  $poptavka_link = get_permalink($poptavka_p->ID) . $hash;
}

      ob_start(); ?>
      <article class="had-card <?php echo 'is-' . esc_attr($stav ? $stav : 'available'); ?>">
        <div class="had-card__media">
          <?php echo $img_html; ?>
          <span class="had-card__badge <?php echo esc_attr($badge_class); ?>"><?php echo esc_html($stav_txt); ?></span>
        </div>

        <div class="had-card__body">
          <h3 class="had-card__title"><?php echo esc_html(get_the_title()); ?></h3>
          <ul class="had-card__meta">
		    <?php if ($odber_txt !== '') echo '<li><strong>Odběr:</strong> '.esc_html($odber_txt).'</li>'; ?>
            <?php if ($ozn       !== '') echo '<li><strong>Označení:</strong> '.esc_html($ozn).'</li>'; ?>
            <?php if ($morf      !== '') echo '<li><strong>Gen:</strong> '.esc_html($morf).'</li>'; ?>
            <?php if ($pohl      !== '') echo '<li><strong>Pohlaví:</strong> '.esc_html($pohl).'</li>'; ?>
            <?php if ($dnar_out  !== '') echo '<li><strong>Datum narození:</strong> '.esc_html($dnar_out).'</li>'; ?>
            <?php if ($vaha      !== '') echo '<li><strong>Váha:</strong> '.esc_html($vaha).' g</li>'; ?>
            <?php if ($rod       !== '') echo '<li><strong>Rodiče:</strong> '.esc_html($rod).'</li>'; ?>
            <?php // if ($druhy_out !== '') echo '<li><strong>Druh:</strong> '.esc_html($druhy_out).'</li>'; ?>
<?php if ($popis !== ''): ?>
  <hr><p class="had-card__desc">
    <?php echo esc_html($popis); ?>
  </p>
<?php endif; ?>
          </ul>
        </div>

      <div class="had-card__price">
  <?php
    $has_czk = ($cena !== '' && $cena !== null);
    $has_eur = ($cena_eur !== '' && $cena_eur !== null);

    if ($has_czk || $has_eur):
  ?>

    <?php if ($has_czk): ?>
      <span class="had-card__price--czk">
        <?php echo had_format_price($cena, ' Kč'); ?>
      </span>
    <?php endif; ?>

    <?php if ($has_czk && $has_eur): ?>
      <span class="had-card__price--sep"> / </span>
    <?php endif; ?>

    <?php if ($has_eur): ?>
      <span class="had-card__price--eur">
        <?php echo had_format_price($cena_eur, ' €'); ?>
      </span>
    <?php endif; ?>

  <?php else: ?>
    <span class="had-card__ask">Cena na dotaz</span>
  <?php endif; ?>
</div>



        <div class="had-card__actions">
  <?php if ($stav === 'sold') { ?>
    <span class="had-btn had-btn--sold">Prodáno</span>
  <?php } elseif ($stav === 'reserved') { ?>
    <span class="had-btn had-btn--reserved">Rezervováno</span>
  <?php } else { ?>
    <?php if ($poptavka_p) { ?>
      <a class="had-btn had-btn--primary" href="<?php echo esc_url($poptavka_link); ?>">Rezervovat</a>
    <?php } else { ?>
      <span class="had-btn had-btn--disabled">Poptávka není nastavena</span>
    <?php } ?>
  <?php } ?>
</div>

      </article>
      <?php
      $groups[$stav_key][] = ob_get_clean();
    }
    wp_reset_postdata();
  } else {
    echo '<p><em>Aktuálně zde není žádný had k&nbsp;zobrazení.</em></p>';
  }

  foreach (['available','reserved','sold','other'] as $key) {
    if (!empty($groups[$key])) echo implode('', $groups[$key]);
  }

  echo '</div>'; // .had-grid
  return ob_get_clean();
});

*/
	
/* ========== Archiv taxonomie ========== 
/* Vrství šablonu pro /druh-hada/slug/ tak, aby použila [nabidka_hadu] se stejným vzhledem. 
add_filter('template_include', function ($template) {
  if (is_tax(HADI_TAX)) {
    $tpl = HADI_CORE_PATH . 'templates/hadi-archive.php';
    if (file_exists($tpl)) return $tpl;
  }
  return $template;
});
*/
/* ========== Dynamické menu: „Hadi (auto)“ → jen kategorie ========== */

/** helper – syntetická položka menu 
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
 


  // doplníme vlastnosti, které běžné menu položky mají
  $o->post_type             = 'nav_menu_item';
  $o->current               = false;
  $o->current_item_ancestor = false;
  $o->current_item_parent   = false;
  return $o;
}
*/
/** zjisti term_id menu pro cache 
function hadi_menu_get_menu_term_id($args) {
  if (!empty($args->menu) && is_object($args->menu) && isset($args->menu->term_id)) return intval($args->menu->term_id);
  if (!empty($args->menu) && is_numeric($args->menu)) return intval($args->menu);
  if (!empty($args->menu->slug)) {
    $m = wp_get_nav_menu_object($args->menu->slug);
    if ($m && !is_wp_error($m)) return intval($m->term_id);
  }
  return 0;
}

/** sestav položky termů s cache 
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

    // načíst pohlaví
    $pohlavi = get_term_meta($term->term_id, 'term_pohlavi', true);
    $pohlavi_lc = is_string($pohlavi) ? mb_strtolower($pohlavi, 'UTF-8') : '';

    // symbol pohlaví
    $symbol = '';
    if ($pohlavi_lc === 'samec') {
        $symbol = ' ♂';
    } elseif ($pohlavi_lc === 'samice') {
        $symbol = ' ♀';
    } elseif ($pohlavi_lc === 'oboji') {
        $symbol = ' ♂♀';
    }

    // základní název BEZ symbolů na konci
    $base = preg_replace('/[♂♀⚪⚤]+$/u', '', $term->name);
    $base = trim($base);

    // finální title
    $title = $base . $symbol;

    // URL
    $link = get_term_link($term);
    if (is_wp_error($link)) $link = '#';

    // vytvořit položku menu
    $items[] = hadi_menu_make_item($title, $link, $parent_id);
}

  }
  set_transient($cache_key, $items, HOUR_IN_SECONDS);
  return $items;
}

/** invalidace cache při změnách 
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
add_filter('wp_nav_menu_objects', function ($items, $args) {

  // 1) Najdi položku menu, která vede na stránku s přehledem všech hadů
  //    Použijeme slug HADI_ALL_PAGE (např. 'hadi') a z URL vytáhneme jen path.
  $parent = null;
  foreach ($items as $it) {
    if (!isset($it->url) || empty($it->url)) {
      continue;
    }

    // vezmeme z URL jen cestu (bez domény, query, hashe)
    $path = parse_url($it->url, PHP_URL_PATH);
    $path = trim($path, '/');

    if ($path === HADI_ALL_PAGE) {
      $parent = $it;
      break;
    }
  }

  // pokud v menu není položka vedoucí na /hadi, plugin nic nedělá
  if (!$parent) {
    return $items;
  }

  // 2) Zachovej URL rodiče – plugin ji nemění (jen generuje děti)

  // 3) Generuj děti = termy taxonomie s hady
  $menu_term_id = hadi_menu_get_menu_term_id($args);
  $new_items    = hadi_menu_build_term_items_cached($menu_term_id, $parent->ID);

  // Pokud nejsou žádné termy, necháme rodiče být a nic negenerujeme
  if (empty($new_items)) {
    return $items;
  }

  // 4) Vyčistit původní děti daného rodiče a připojit nové termy
  $parent_id = intval($parent->ID);

  $items = array_values(array_filter(
    $items,
    function ($i) use ($parent_id) {
      return intval($i->menu_item_parent) !== $parent_id;
    }
  ));

  // připojíme nové položky z taxonomie jako pod-menu rodiče
  $items = array_merge($items, $new_items);

  return $items;
}, 10, 2);


/* ========== Aktivace – vytvoření šablony, flush rewrite (pro jistotu) ========== 
register_activation_hook(__FILE__, function () {
  // vytvoř šablonu pokud chybí
  $tpl_dir = HADI_CORE_PATH . 'templates';
  if (!is_dir($tpl_dir)) wp_mkdir_p($tpl_dir);
  $tpl = $tpl_dir . '/hadi-archive.php';
  if (!file_exists($tpl)) {
    file_put_contents($tpl, "<?php\nget_header();\n\$term = get_queried_object(); ?>\n<div class=\"section\"><div class=\"container\">\n<h1><?php echo esc_html(single_term_title('', false)); ?></h1>\n<?php echo do_shortcode('[nabidka_hadu dostupnost=\"all\" druh=\"' . esc_attr(\$term->slug) . '\"]'); ?>\n</div></div>\n<?php get_footer(); ?>");
  }
  flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, function () {
  flush_rewrite_rules();
});

// === ULOŽENÍ VOLEB ===
function hadi_default_opts(){ return [
  'pt'=>'had','tax'=>'genotyp-hada','menu_text'=>'Dostupní hadi',
  'all_slug'=>'hadi','poptavka_slug'=>'poptavka','per_page'=>20,'sorting_ui'=>1,
];}
function hadi_get_opts(){ return wp_parse_args(get_option('hadi_core_options',[]), hadi_default_opts()); }
function hadi_sanitize_opts($in){ $d=hadi_default_opts(); return [
  'pt'=>sanitize_title($in['pt']??$d['pt']),
  'tax'=>sanitize_title($in['tax']??$d['tax']),
  'menu_text'=>sanitize_text_field($in['menu_text']??$d['menu_text']),
  'all_slug'=>sanitize_title($in['all_slug']??$d['all_slug']),
  'poptavka_slug'=>sanitize_title($in['poptavka_slug']??$d['poptavka_slug']),
  'per_page'=>max(1,intval($in['per_page']??$d['per_page'])),
  'sorting_ui'=>empty($in['sorting_ui'])?0:1,
];}

// === ADMIN MENU ===
add_action('admin_menu', function () {
  add_options_page('Hadi – nastavení','Hadi','manage_options','hadi-core-settings','hadi_render_settings_page');
});
add_action('admin_init', function () {
  register_setting('hadi_core_group','hadi_core_options',['type'=>'array','sanitize_callback'=>'hadi_sanitize_opts','default'=>hadi_default_opts()]);
  add_settings_section('hadi_core_sec','', '__return_false','hadi_core');
  foreach (['pt','tax','menu_text','all_slug','poptavka_slug','per_page'] as $k) {
    add_settings_field($k, ucfirst(str_replace('_',' ',$k)), 'hadi_settings_field_text', 'hadi_core','hadi_core_sec', ['key'=>$k]);
  }
  add_settings_field('sorting_ui','Zobrazit ovládání řazení','hadi_settings_field_checkbox','hadi_core','hadi_core_sec',['key'=>'sorting_ui']);
});
function hadi_settings_field_text($args){ $o=hadi_get_opts(); $k=$args['key']; echo "<input type='text' name='hadi_core_options[$k]' value='".esc_attr($o[$k]??'')."' class='regular-text' />"; }
function hadi_settings_field_checkbox($args){ $o=hadi_get_opts(); $k=$args['key']; $chk=!empty($o[$k])?'checked':''; echo "<label><input type='checkbox' name='hadi_core_options[$k]' value='1' $chk /> zapnuto</label>"; }
function hadi_render_settings_page(){ ?>
  <div class="wrap">
    <h1>Hadi – nastavení</h1>
    <form action="options.php" method="post">
      <?php settings_fields('hadi_core_group'); do_settings_sections('hadi_core'); submit_button(); ?>
    </form>
  </div>
  
  
<?php }

/*************************************************************
 * ADMIN: Sloupec "ID" pro CPT Hadi + zobrazení ID v editoru
 ************************************************************

// 1) Přidání sloupce ID do seznamu hadů
add_filter('manage_' . HADI_PT . '_posts_columns', function($columns) {
    // Vloží sloupec ID na konec tabulky
    $columns['had_id'] = 'ID';
    return $columns;
});

// 2) Výpis dat pro sloupec ID
add_action('manage_' . HADI_PT . '_posts_custom_column', function($column, $post_id) {
    if ($column === 'had_id') {
        echo $post_id;
    }
}, 10, 2);

// 3) Třídění sloupce ID
add_filter('manage_edit-' . HADI_PT . '_sortable_columns', function($columns) {
    $columns['had_id'] = 'ID';
    return $columns;
});

// 4) Zobrazení ID přímo v editoru hada
add_action('edit_form_after_title', function($post) {
    if ($post->post_type === HADI_PT) {
        echo '<div style="
            padding:10px 15px;
            background:#f1f1f1;
            border:1px solid #ccc;
            margin-bottom:15px;
            font-size:14px;
        ">
            <strong>ID tohoto hada:</strong> ' . $post->ID . '
        </div>';
    }
});

/*************************************************************
 * ADMIN: Skrýt Betheme live-edit tlačítko jen u hadů
 ************************************************************
add_action('admin_head-post.php',     'had_hide_betheme_button');
add_action('admin_head-post-new.php', 'had_hide_betheme_button');

function had_hide_betheme_button() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== HADI_PT ) {
        return;
    }

    // Skryje modré tlačítko "Edit with Be / Ball PythonBuilder"
    echo '<style>
        .mfn-live-edit-page-button,
        .mfn-live-edit-page-button.classic {
            display: none !important;
        }
    </style>';
}

/*************************************************************
 * LIVE doplnění symbolu pohlaví (♂ / ♀) do názvu hada
 * – bere hodnotu z ACF pole "pohlavi" (samec / samice / neznamo)
 ************************************************************
add_action('admin_footer-post.php',     'had_live_gender_symbol_title_js');
add_action('admin_footer-post-new.php', 'had_live_gender_symbol_title_js');

function had_live_gender_symbol_title_js() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== HADI_PT ) {
        return;
    }
    ?>
    <script>
    (function($){

        // mapování textové hodnoty pohlaví -> symbol
        function getSymbol(val){
            if (!val) return '';
            val = val.toLowerCase();
            if (val === 'samec')  return '♂';
            if (val === 'samice') return '♀';
            // "neznamo" = bez symbolu, nebo sem můžeš dát třeba '⚪'
            return '';
        }

        function updateTitle(){
            var $title = $('#title');
            if (!$title.length) return;

            // zapamatujeme si "čistý" název bez symbolu
            var base = $title.data('base-title');
            if (!base) {
                var t = $title.val() || '';
                t = t.replace(/[♂♀⚪]\s*$/u, '').trim();
                $title.data('base-title', t);
                base = t;
            }

            // najdeme ACF pole pohlavi
            var val = '';
            var $field = $('.acf-field[data-name="pohlavi"]');
            if ($field.length){
                var $sel = $field.find('select');
                if ($sel.length){
                    val = $sel.val();
                } else {
                    val = $field.find('input[type="radio"]:checked').val() || '';
                }
            }

            var symbol = getSymbol(val);
            $title.val(symbol ? (base + ' ' + symbol) : base);
        }

        $(document).ready(function(){

            // uložíme si výchozí titulek bez symbolu
            var t = $('#title').val() || '';
            $('#title').data('base-title', t.replace(/[♂♀⚪]\s*$/u, '').trim());

            // ACF hooky (když je ACF načtené)
            if (window.acf) {
                acf.addAction('ready', updateTitle);
                acf.addAction('change', function($el){
                    if ($el.closest('.acf-field').data('name') === 'pohlavi') {
                        updateTitle();
                    }
                });
            }

            // fallback – kdyby ACF hooky neběžely
            $(document).on(
                'change',
                '.acf-field[data-name="pohlavi"] select, .acf-field[data-name="pohlavi"] input[type="radio"]',
                updateTitle
            );
        });

    })(jQuery);
    </script>
    <?php
}
*/
/*************************************************************
 * Taxonomie: Pohlaví u genotypu hada (dropdown + symbol v názvu)
 *************************************************************/

/**
 * Pomocná funkce – vrátí slug taxonomie pro genotyp hada.
 * Pokud používáš konstantu HADI_TAX, použijeme ji, jinak fallback na 'genotyp-hada'.

function had_get_tax_slug() {
    if (defined('HADI_TAX')) {
        return HADI_TAX;
    }
    return 'genotyp-hada';
}
 */
/**
 * 1) Pole Pohlaví na obrazovce "Přidat nový termín"
 
add_action( had_get_tax_slug() . '_add_form_fields', function() {
    ?>
    <div class="form-field term-pohlavi-wrap">
        <label for="term_pohlavi"><?php _e('Pohlaví', 'hadi-core'); ?></label>
        <select name="term_pohlavi" id="term_pohlavi">
            <option value=""><?php _e('Neznámo', 'hadi-core'); ?></option>
            <option value="samec"><?php _e('Samec', 'hadi-core'); ?></option>
            <option value="samice"><?php _e('Samice', 'hadi-core'); ?></option>
			 <option value="oboji"><?php _e('Obě pohlaví', 'hadi-core'); ?></option>
        </select>
        <p class="description">Vyber pohlaví, pokud má tento genotyp přiřazené. ♂ ♀ ⚤</p>
    </div>
    <?php
});

/**
 * 2) Pole Pohlaví na obrazovce "Upravit termín"
 
add_action( had_get_tax_slug() . '_edit_form_fields', function($term) {
    $value = get_term_meta($term->term_id, 'term_pohlavi', true);
    ?>
    <tr class="form-field term-pohlavi-wrap">
        <th scope="row"><label for="term_pohlavi"><?php _e('Pohlaví', 'hadi-core'); ?></label></th>
        <td>
            <select name="term_pohlavi" id="term_pohlavi">
                <option value="" <?php selected($value, ''); ?>><?php _e('Neznámo', 'hadi-core'); ?></option>
                <option value="samec" <?php selected($value, 'samec'); ?>><?php _e('Samec', 'hadi-core'); ?></option>
                <option value="samice" <?php selected($value, 'samice'); ?>><?php _e('Samice', 'hadi-core'); ?></option>
				 <option value="oboji"  <?php selected($value, 'oboji');  ?>><?php _e('Obě pohlaví', 'hadi-core'); ?></option>
            </select>
            <p class="description">Vyber pohlaví tohoto genotypu.</p>
        </td>
    </tr>
    <?php
}, 10, 1);

/**
 * 3) Uložení hodnoty při vytvoření/upravení termu
 
add_action( 'created_' . ( defined('HADI_TAX') ? HADI_TAX : 'genotyp-hada' ), function($term_id) {
    if (isset($_POST['term_pohlavi'])) {
        $v = sanitize_text_field($_POST['term_pohlavi']);
        update_term_meta($term_id, 'term_pohlavi', $v);
    }
}, 10, 1);

add_action( 'edited_' . ( defined('HADI_TAX') ? HADI_TAX : 'genotyp-hada' ), function($term_id) {
    if (isset($_POST['term_pohlavi'])) {
        $v = sanitize_text_field($_POST['term_pohlavi']);
        update_term_meta($term_id, 'term_pohlavi', $v);
    }
}, 10, 1);

/**
 * 4) Úprava zobrazovaného názvu termu – přidání symbolu
 
add_filter('term_name', function($name, $term) {
    if (!$term || $term->taxonomy !== had_get_tax_slug()) {
        return $name;
    }

    $pohlavi = get_term_meta($term->term_id, 'term_pohlavi', true);
    $pohlavi_lc = is_string($pohlavi) ? mb_strtolower($pohlavi, 'UTF-8') : '';

    $symbol = '';
    if ($pohlavi_lc === 'samec') {
        $symbol = '♂';
    } elseif ($pohlavi_lc === 'samice') {
        $symbol = '♀';
    } elseif ($pohlavi_lc === 'oboji') {
        $symbol = '⚤';     // tady klidně můžeš dát třeba '⚤'
    } else {
        $symbol = '';
    }

    $base = preg_replace('/[♂♀⚪⚤]\s*$/u', '', $name);
    $base = trim($base);

    return $symbol ? ($base . ' ' . $symbol) : $base;
}, 10, 2);