<?php

declare(strict_types=1);

namespace SimpleImagePopup\Frontend;

use SimpleImagePopup\Infrastructure\Manifest;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Enqueues built frontend and admin assets.
 */
final class AssetManager
{
    /**
     * Vite manifest reader.
     *
     * @var Manifest
     */
    private $manifest;

    /**
     * Create a new asset manager.
     *
     * @param Manifest $manifest Vite manifest reader.
     */
    public function __construct(Manifest $manifest)
    {
        $this->manifest = $manifest;

        add_filter('script_loader_tag', array($this, 'filterScriptTag'), 10, 2);
    }

    /**
     * Enqueue the frontend runtime bundle.
     *
     * @param array<string, mixed> $config Frontend configuration payload.
     */
    public function enqueueFrontend(array $config): void
    {
        $this->enqueueManifestScript('resources/js/frontend.js', 'simple-image-popup-frontend', array());

        wp_enqueue_style(
            'simple-image-popup-frontend',
            SIMPLE_IMAGE_POPUP_URL . 'css/simple-image-popup.css',
            array(),
            SIMPLE_IMAGE_POPUP_VERSION
        );

        wp_add_inline_script(
            'simple-image-popup-frontend',
            'window.SimpleImagePopupFrontend = ' . wp_json_encode($config, JSON_HEX_TAG) . ';',
            'before'
        );
    }

    /**
     * Enqueue the Vue-powered admin application.
     *
     * @param array<string, mixed> $config Admin configuration payload.
     */
    public function enqueueAdmin(array $config): void
    {
        wp_enqueue_media();
        $this->enqueueManifestScript('resources/js/admin/main.js', 'simple-image-popup-admin', array('wp-element', 'wp-i18n'));

        // Load the admin app's strings from the language packs (JSON generated from the built bundle).
        wp_set_script_translations('simple-image-popup-admin', 'simple-image-popup');

        wp_add_inline_script(
            'simple-image-popup-admin',
            'window.SimpleImagePopupAdmin = ' . wp_json_encode($config, JSON_HEX_TAG) . ';',
            'before'
        );
    }

    /**
     * Determine whether an asset entry exists in the current manifest.
     */
    public function hasEntry(string $entry): bool
    {
        return $this->manifest->getEntry($entry) !== null;
    }

    /**
     * Render the admin bundle as a module and leave the frontend script classic.
     *
     * @param string $tag Existing script tag.
     * @param string $handle Script handle.
     */
    public function filterScriptTag(string $tag, string $handle): string
    {
        if ($handle !== 'simple-image-popup-admin') {
            return $tag;
        }

        // Change only the external script, preserving the inline configuration.
        $id = preg_quote($handle . '-js', '/');

        return preg_replace_callback(
            '/<script\b(?=[^>]*\bid=["\']' . $id . '["\'])[^>]*>/i',
            static function (array $match): string {
                $script = preg_replace('/\s+type=["\'][^"\']*["\']/i', '', $match[0]);

                return substr_replace($script, ' type="module"', strlen('<script'), 0);
            },
            $tag,
            1
        ) ?? $tag;
    }

    /**
     * Enqueue a script and any related CSS from the Vite manifest.
     *
     * @param string   $entry Vite entry path.
     * @param string   $handle WordPress script handle.
     * @param string[] $dependencies Script dependencies.
     */
    private function enqueueManifestScript(string $entry, string $handle, array $dependencies): void
    {
        $manifest_entry = $this->manifest->getEntry($entry);

        if (! is_array($manifest_entry) || empty($manifest_entry['file'])) {
            return;
        }

        wp_enqueue_script(
            $handle,
            SIMPLE_IMAGE_POPUP_URL . 'assets/dist/' . ltrim((string) $manifest_entry['file'], '/'),
            $dependencies,
            SIMPLE_IMAGE_POPUP_VERSION,
            true
        );

        if (! empty($manifest_entry['css']) && is_array($manifest_entry['css'])) {
            foreach ($manifest_entry['css'] as $index => $css_file) {
                wp_enqueue_style(
                    $handle . '-' . (string) $index,
                    SIMPLE_IMAGE_POPUP_URL . 'assets/dist/' . ltrim((string) $css_file, '/'),
                    array(),
                    SIMPLE_IMAGE_POPUP_VERSION
                );
            }
        }
    }
}
