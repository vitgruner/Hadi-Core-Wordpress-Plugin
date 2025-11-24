<?php
if (!defined('ABSPATH')) exit;

/**
 * Taxonomie – pohlaví u genotypu hada
 * Přidává term meta "term_pohlavi" + symboly ♂ / ♀ / ⚤ do názvu termu.
 */

/**
 * Helper: slug taxonomie hadů
 * (pokud už ji definuješ jinde, nevadí – funkce se nevytvoří podruhé)
 */
if (!function_exists('had_get_tax_slug')) {
    function had_get_tax_slug() {
        return defined('HADI_TAX') ? HADI_TAX : 'genotyp-hada';
    }
}

/**
 * 1) Pole "Pohlaví" na obrazovce "Přidat nový termín"
 */
function had_tax_gender_add_field() {
    ?>
    <div class="form-field term-pohlavi-wrap">
        <label for="term_pohlavi"><?php _e('Pohlaví', 'hadi-core'); ?></label>
        <select name="term_pohlavi" id="term_pohlavi">
            <option value=""><?php _e('Neznámo', 'hadi-core'); ?></option>
            <option value="samec"><?php _e('Samec', 'hadi-core'); ?></option>
            <option value="samice"><?php _e('Samice', 'hadi-core'); ?></option>
            <option value="oboji"><?php _e('Obě pohlaví', 'hadi-core'); ?></option>
        </select>
        <p class="description">
            Vyber pohlaví, pokud je tento genotyp přiřazený konkrétnímu pohlaví. ♂ ♀ ⚤
        </p>
    </div>
    <?php
}

/**
 * 2) Pole "Pohlaví" na obrazovce "Upravit termín"
 */
function had_tax_gender_edit_field($term) {
    $value = get_term_meta($term->term_id, 'term_pohlavi', true);
    ?>
    <tr class="form-field term-pohlavi-wrap">
        <th scope="row">
            <label for="term_pohlavi"><?php _e('Pohlaví', 'hadi-core'); ?></label>
        </th>
        <td>
            <select name="term_pohlavi" id="term_pohlavi">
                <option value="" <?php selected($value, ''); ?>>
                    <?php _e('Neznámo', 'hadi-core'); ?>
                </option>
                <option value="samec" <?php selected($value, 'samec'); ?>>
                    <?php _e('Samec', 'hadi-core'); ?>
                </option>
                <option value="samice" <?php selected($value, 'samice'); ?>>
                    <?php _e('Samice', 'hadi-core'); ?>
                </option>
                <option value="oboji" <?php selected($value, 'oboji'); ?>>
                    <?php _e('Obě pohlaví', 'hadi-core'); ?>
                </option>
            </select>
            <p class="description">Vyber pohlaví tohoto genotypu.</p>
        </td>
    </tr>
    <?php
}

/**
 * 3) Uložení hodnoty při vytvoření / úpravě termu
 */
function had_tax_gender_save($term_id) {
    if (isset($_POST['term_pohlavi'])) {
        $v = sanitize_text_field(wp_unslash($_POST['term_pohlavi']));
        update_term_meta($term_id, 'term_pohlavi', $v);
    }
}

/**
 * 4) Úprava zobrazovaného názvu termu – přidání symbolu
 *    používá helpery: had_get_term_gender_symbol() a had_clean_title()
 */
function had_tax_gender_filter_name($name, $term) {

    if (!$term instanceof WP_Term) {
        return $name;
    }

    if ($term->taxonomy !== had_get_tax_slug()) {
        return $name;
    }

    // symbol z helperu (definován v helpers.php)
    if (function_exists('had_get_term_gender_symbol')) {
        $symbol = had_get_term_gender_symbol($term->term_id);
    } else {
        // fallback – kdyby helper nebyl dostupný
        $p = get_term_meta($term->term_id, 'term_pohlavi', true);
        if ($p === 'samec')      $symbol = '♂';
        elseif ($p === 'samice') $symbol = '♀';
        elseif ($p === 'oboji')  $symbol = '⚤';
        else                     $symbol = '';
    }

    // očistit případné staré symboly
    if (function_exists('had_clean_title')) {
        $base = had_clean_title($name);
    } else {
        $base = trim(preg_replace('/[♂♀⚤]\s*$/u', '', $name));
    }

    return $symbol ? ($base . ' ' . $symbol) : $base;
}

/* ------------------------------------------------------------
 * Registrace hooků pro konkrétní taxonomii
 * ------------------------------------------------------------ */
$__hadi_tax = had_get_tax_slug();

add_action($__hadi_tax . '_add_form_fields',  'had_tax_gender_add_field');
add_action($__hadi_tax . '_edit_form_fields', 'had_tax_gender_edit_field', 10, 1);

add_action('created_' . $__hadi_tax, 'had_tax_gender_save', 10, 1);
add_action('edited_'  . $__hadi_tax, 'had_tax_gender_save', 10, 1);

add_filter('term_name', 'had_tax_gender_filter_name', 10, 2);
