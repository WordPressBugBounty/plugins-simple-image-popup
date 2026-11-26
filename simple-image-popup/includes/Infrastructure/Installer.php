<?php

declare(strict_types=1);

namespace SimpleImagePopup\Infrastructure;

use SimpleImagePopup\Domain\PopupPostType;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Handles activation-time and upgrade-time setup.
 */
final class Installer
{
    private const SCHEMA_VERSION = '2';

    /**
     * Perform activation tasks.
     */
    public static function activate(): void
    {
        $post_type = new PopupPostType();
        $post_type->register();

        (new self())->maybeUpgrade();
        flush_rewrite_rules();
    }

    /**
     * Run upgrade tasks for existing sites.
     */
    public function maybeUpgrade(): void
    {
        $installed_version = (string) get_option('simple_image_popup_db_version', '');

        if ($installed_version === self::SCHEMA_VERSION) {
            return;
        }

        $analytics_repository = new AnalyticsRepository();
        if ($analytics_repository->createTables()) {
            update_option('simple_image_popup_db_version', self::SCHEMA_VERSION);
        }
    }
}
