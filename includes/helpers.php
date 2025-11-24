<?php
if (!defined('ABSPATH')) exit;

/* Cena formát */
if (!function_exists('had_format_price')) {
    function had_format_price($value, $suffix = ' Kč') {
        if ($value === '' || $value === null) return '';
        return number_format((float)$value, 0, ',', ' ') . $suffix;
    }
}

/* Odstranění starých symbolů */
function had_clean_title($title) {
    return trim(preg_replace('/[♂♀⚤]\s*$/u', '', $title));
}

/* Symbol pohlaví */
function had_get_term_gender_symbol($term_id) {
    $p = get_term_meta($term_id, 'term_pohlavi', true);
    if ($p === 'samec')  return '♂';
    if ($p === 'samice') return '♀';
    if ($p === 'oboji')  return '⚤';
    return '';
}
