<?php

declare(strict_types=1);

namespace SimpleImagePopup;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Loads plugin classes from the includes directory.
 */
final class Autoloader
{
    /**
     * Register the autoloader with SPL.
     */
    public static function register(): void
    {
        spl_autoload_register(array(__CLASS__, 'autoload'));
    }

    /**
     * Load a namespaced plugin class file when it is requested.
     *
     * @param string $class Fully qualified class name.
     */
    public static function autoload(string $class): void
    {
        $prefix = __NAMESPACE__ . '\\';

        if (strpos($class, $prefix) !== 0) {
            return;
        }

        $relative_class = substr($class, strlen($prefix));
        if (! preg_match('/\A[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*\z/', $relative_class)) {
            return;
        }

        $relative_path = str_replace('\\', DIRECTORY_SEPARATOR, $relative_class) . '.php';
        $file          = SIMPLE_IMAGE_POPUP_PATH . 'includes/' . $relative_path;

        if (file_exists($file)) {
            require_once $file;
        }
    }
}
