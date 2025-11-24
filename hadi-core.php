    <?php
    <?php
/**
 * Plugin Name: Hadi – jádro (shortcode, menu, archiv)
 * Description: Shortcode [nabidka_hadu], archiv taxonomie přes shortcode, dynamické menu kategorií „Hadi (auto)“, ACF admin UI pro CPT „had“. Lightbox pro obrázky
 * Version: 1.4.0
 * Author: gruner.digital
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

    if (!defined('ABSPATH')) exit;

    // Modular loader wrapper – původní logika pluginu je v includes/legacy-hadi-core.php
    if (!defined('HADI_CORE_PATH')) {
        define('HADI_CORE_PATH', plugin_dir_path(__FILE__));
    }
    if (!defined('HADI_CORE_URL')) {
        define('HADI_CORE_URL', plugin_dir_url(__FILE__));
    }

    require_once HADI_CORE_PATH . 'includes/legacy-hadi-core.php';
	require_once HADI_CORE_PATH . 'includes/constants.php';
	require_once HADI_CORE_PATH . 'includes/helpers.php';
	require_once HADI_CORE_PATH . 'includes/shortcode-hadi.php';
	require_once HADI_CORE_PATH . 'includes/admin-ui.php';
	require_once HADI_CORE_PATH . 'includes/taxonomy-gender.php';
	require_once HADI_CORE_PATH . 'includes/archive-hadi.php'; 
	require_once HADI_CORE_PATH . 'includes/menu-hadi.php';
    require_once HADI_CORE_PATH . 'includes/admin-id-hadi.php';
	require_once HADI_CORE_PATH . 'includes/admin-gender-title-hadi.php';

