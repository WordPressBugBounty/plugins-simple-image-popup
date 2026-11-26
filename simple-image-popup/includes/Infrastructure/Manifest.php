<?php

declare(strict_types=1);

namespace SimpleImagePopup\Infrastructure;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Reads the Vite build manifest generated for plugin assets.
 */
final class Manifest
{
    /**
     * Cached manifest payload.
     *
     * @var array<string, mixed>|null
     */
    private $manifest;

    /**
     * Return the parsed Vite manifest.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        if (is_array($this->manifest)) {
            return $this->manifest;
        }

        $manifest_path = SIMPLE_IMAGE_POPUP_PATH . 'assets/dist/manifest.json';

        if (! file_exists($manifest_path)) {
            $this->manifest = array();

            return $this->manifest;
        }

        $manifest = json_decode((string) file_get_contents($manifest_path), true);
        $this->manifest = is_array($manifest) ? $manifest : array();

        return $this->manifest;
    }

    /**
     * Look up a single asset entry by source path.
     *
     * @param string $entry Entry key from the Vite manifest.
     * @return array<string, mixed>|null
     */
    public function getEntry(string $entry): ?array
    {
        $manifest = $this->get();

        return isset($manifest[$entry]) && is_array($manifest[$entry]) ? $manifest[$entry] : null;
    }
}
