<?php

/**
 * Plugin Name: Simple Image Popup
 * Description: Manage popup campaigns with a modern admin experience, targeting rules, migration from legacy settings, and popup view tracking.
 * Author: Mr Digital
 * Author URI: https://www.mrdigital.com.au
 * Text Domain: simple-image-popup
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Version: 3.0.2
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('SIMPLE_IMAGE_POPUP_VERSION', '3.0.2');
define('SIMPLE_IMAGE_POPUP_FILE', __FILE__);
define('SIMPLE_IMAGE_POPUP_PATH', plugin_dir_path(__FILE__));
define('SIMPLE_IMAGE_POPUP_URL', plugin_dir_url(__FILE__));

require_once SIMPLE_IMAGE_POPUP_PATH . 'includes/Autoloader.php';

\SimpleImagePopup\Autoloader::register();

register_activation_hook(
    SIMPLE_IMAGE_POPUP_FILE,
    array(\SimpleImagePopup\Infrastructure\Installer::class, 'activate')
);

\SimpleImagePopup\Plugin::boot();
