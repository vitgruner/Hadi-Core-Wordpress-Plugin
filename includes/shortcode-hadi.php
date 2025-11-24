<?php
if (!defined('ABSPATH')) exit;

/**
 * Shortcode: [nabidka_hadu]
 * Hlavní výpis karet hada včetně obrázků, cen, ACF dat a poptávkového odkazu.
 */

add_shortcode('nabidka_hadu', function($atts){

    $a = shortcode_atts([
        'per_page'   => 12,
        'orderby'    => 'menu_order',
        'order'      => 'ASC',
        'dostupnost' => 'all',
        'druh'       => '',
    ], $atts, 'nabidka_hadu');

    /* ----------------------------------------------
       WP_Query
    ---------------------------------------------- */

    $args = [
        'post_type'      => HADI_PT,
        'posts_per_page' => (int)$a['per_page'],
        'order'          => in_array(strtoupper($a['order']), ['ASC','DESC']) ? strtoupper($a['order']) : 'ASC',
        'post_status'    => 'publish',
    ];

    if ($a['orderby'] === 'cena') {
        $args['orderby']  = 'meta_value_num';
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

    /* ----------------------------------------------
       Helper pro ACF/meta
    ---------------------------------------------- */
    $get = function($key, $id){
        if (function_exists('get_field')) {
            $v = get_field($key, $id);
            if ($v !== null && $v !== '') return $v;
        }
        return get_post_meta($id, $key, true);
    };

    ob_start();
    echo '<div class="had-grid">';

    /* --- Skupiny výpisu --- */
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

            /* ----------------------------------------------
               Obrázek / thumbnail
            ---------------------------------------------- */

            $img_html = '';
            $img_id   = 0;
            $img_url  = '';

            // Featured image
            if (has_post_thumbnail($id)) {
                $img_id   = get_post_thumbnail_id($id);
                $img_html = get_the_post_thumbnail($id, 'large', ['class' => 'had-card__img']);
                $img_url  = wp_get_attachment_url($img_id);
            }

            // ACF fallback
            if (!$img_html) {
                $acf_image_fields = ['obrazek','foto','image','fotka','hlavni_obrazek'];
                foreach ($acf_image_fields as $field_key) {
                    $img_val = function_exists('get_field')
                        ? get_field($field_key, $id)
                        : get_post_meta($id, $field_key, true);

                    if (!$img_val) continue;

                    if (is_numeric($img_val)) {
                        $img_id = (int)$img_val;
                        break;
                    } elseif (is_string($img_val) && filter_var($img_val, FILTER_VALIDATE_URL)) {
                        $img_url = $img_val;
                        break;
                    } elseif (is_array($img_val)) {
                        if (!empty($img_val['ID'])) {
                            $img_id = (int)$img_val['ID'];
                            break;
                        }
                        if (!empty($img_val['url'])) {
                            $img_url = $img_val['url'];
                            break;
                        }
                    }
                }

                if ($img_id) {
                    $img_html = wp_get_attachment_image($img_id, 'large', false, ['class'=>'had-card__img']);
                    if (!$img_url) $img_url = wp_get_attachment_url($img_id);
                } elseif ($img_url) {
                    $img_html = '<img class="had-card__img" src="'.esc_url($img_url).'" alt="'.esc_attr(get_the_title()).'">';
                }
            }

            // Lightbox wrapper
            if ($img_html && $img_url) {
                $img_html =
                    '<a href="'.esc_url($img_url).'#'.intval($id).'"
                        rel="lightbox"
                        data-lightbox-type="image">
                        <div class="mask"></div>' .
                        $img_html .
                    '</a>';
            }

            if (!$img_html) {
                $img_html = '<div class="had-card__img had-card__img--ph"></div>';
            }

            /* ----------------------------------------------
               Načtení ACF hodnot
            ---------------------------------------------- */

            $ozn      = $get('oznaceni',       $id);
            $morf     = $get('morf',           $id);
            $pohl     = $get('pohlavi',        $id);
            $dnar     = $get('datum_narozeni', $id);
            $vaha     = $get('vaha',           $id);
            $rod      = $get('rodice',         $id);
            $stav     = $get('dostupnost',     $id);
            $cena     = $get('cena',           $id);
            $cena_eur = $get('cena_eur',       $id);
            $popis    = $get('kratky_popis',   $id);
            $odber    = $get('odber',          $id);

            /* ----------------------------------------------
               Odběr text
            ---------------------------------------------- */

            $odber_txt = '';
            if ($stav === 'available' && $odber) {
                if ($odber === 'ihned') {
                    $odber_txt = 'Ihned k odběru';
                } elseif ($odber === 'po_zakrmeni') {
                    $odber_txt = 'K odběru po rozkrmení';
                }
            }

            /* ----------------------------------------------
               Stav / badge
            ---------------------------------------------- */

            $stav_key = $stav ?: 'available';
            if (!isset($groups[$stav_key])) $stav_key = 'other';

            $stav_txt = ($stav === 'reserved') ? 'Rezervováno'
                      : (($stav === 'sold') ? 'Prodáno' : 'Dostupné');

            $badge_class = ($stav === 'reserved') ? 'is-reserved'
                          : (($stav === 'sold') ? 'is-sold' : 'is-available');

            /* ----------------------------------------------
               Datum narození
            ---------------------------------------------- */

            $dnar_out = '';
            if (!empty($dnar)) {
                $t = strtotime($dnar);
                $dnar_out = $t ? date_i18n('j. n. Y', $t) : $dnar;
            }

            /* ----------------------------------------------
               Termy (genotypy)
            ---------------------------------------------- */

            $druhy_out = '';
            $terms = get_the_terms($id, HADI_TAX);
            if ($terms && !is_wp_error($terms)) {
                $names = array_map(fn($t) => $t->name, $terms);
                $druhy_out = implode(', ', $names);
            }

            /* ----------------------------------------------
               Odkaz Poptávky
            ---------------------------------------------- */

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

            /* ----------------------------------------------
               Render jedné karty
            ---------------------------------------------- */

            ob_start();
            ?>
            <article class="had-card <?php echo 'is-' . esc_attr($stav ? $stav : 'available'); ?>">
                <div class="had-card__media">
                    <?php echo $img_html; ?>
                    <span class="had-card__badge <?php echo esc_attr($badge_class); ?>">
                        <?php echo esc_html($stav_txt); ?>
                    </span>
                </div>

                <div class="had-card__body">
                    <h3 class="had-card__title"><?php echo esc_html(get_the_title()); ?></h3>

                    <ul class="had-card__meta">
                        <?php if ($odber_txt !== '') echo '<li><strong>Odběr:</strong> ' . esc_html($odber_txt) . '</li>'; ?>
                        <?php if ($ozn       !== '') echo '<li><strong>Označení:</strong> ' . esc_html($ozn) . '</li>'; ?>
                        <?php if ($morf      !== '') echo '<li><strong>Gen:</strong> ' . esc_html($morf) . '</li>'; ?>
                        <?php if ($pohl      !== '') echo '<li><strong>Pohlaví:</strong> ' . esc_html($pohl) . '</li>'; ?>
                        <?php if ($dnar_out  !== '') echo '<li><strong>Datum narození:</strong> ' . esc_html($dnar_out) . '</li>'; ?>
                        <?php if ($vaha      !== '') echo '<li><strong>Váha:</strong> ' . esc_html($vaha) . ' g</li>'; ?>
                        <?php if ($rod       !== '') echo '<li><strong>Rodiče:</strong> ' . esc_html($rod) . '</li>'; ?>

                        <?php if ($popis !== ''): ?>
                            <hr>
                            <p class="had-card__desc"><?php echo esc_html($popis); ?></p>
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
                    <?php if ($stav === 'sold'): ?>
                        <span class="had-btn had-btn--sold">Prodáno</span>

                    <?php elseif ($stav === 'reserved'): ?>
                        <span class="had-btn had-btn--reserved">Rezervováno</span>

                    <?php else: ?>
                        <?php if ($poptavka_p): ?>
                            <a class="had-btn had-btn--primary" href="<?php echo esc_url($poptavka_link); ?>">Rezervovat</a>
                        <?php else: ?>
                            <span class="had-btn had-btn--disabled">Poptávka není nastavena</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

            </article>
            <?php

            $groups[$stav_key][] = ob_get_clean();
        }

        wp_reset_postdata();
    } else {
        echo '<p><em>Aktuálně zde není žádný had k zobrazení.</em></p>';
    }

    // Render podle skupin
    foreach (['available','reserved','sold','other'] as $key) {
        if (!empty($groups[$key])) {
            echo implode('', $groups[$key]);
        }
    }

    echo '</div>';

    return ob_get_clean();
});
