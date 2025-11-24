<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin: LIVE doplnění symbolu pohlaví (♂ / ♀) do názvu hada
 * – bere hodnotu z ACF pole "pohlavi" (samec / samice / neznamo / oboji)
 */

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
            if (val === 'oboji')  return '⚤';
            // "neznamo" = bez symbolu
            return '';
        }

        function updateTitle(){
            var $title = $('#title');
            if (!$title.length) return;

            // zapamatujeme si "čistý" název bez symbolu
            var base = $title.data('base-title');
            if (!base) {
                var t = $title.val() || '';
                t = t.replace(/[♂♀⚤]\s*$/u, '').trim();
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
            $('#title').data('base-title', t.replace(/[♂♀⚤]\s*$/u, '').trim());

            // ACF hooky (pokud je ACF načten)
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

// Zavěšení do admin footeru jen na editaci HADI_PT
add_action('admin_footer-post.php',     'had_live_gender_symbol_title_js');
add_action('admin_footer-post-new.php', 'had_live_gender_symbol_title_js');
