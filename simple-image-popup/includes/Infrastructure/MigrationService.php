<?php

declare(strict_types=1);

namespace SimpleImagePopup\Infrastructure;

use SimpleImagePopup\Domain\PopupRepository;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Migrates legacy option-based settings into popup records.
 */
final class MigrationService
{
    /**
     * Migration flag option name.
     */
    private const MIGRATION_OPTION = 'simple_image_popup_migration_version';
    private const MIGRATION_LOCK = 'simple_image_popup_migration_lock';

    /**
     * Popup repository dependency.
     *
     * @var PopupRepository
     */
    private $popup_repository;

    /**
     * Create the migration service.
     *
     * @param PopupRepository $popup_repository Popup persistence layer.
     */
    public function __construct(PopupRepository $popup_repository)
    {
        $this->popup_repository = $popup_repository;
    }

    /**
     * Perform a one-time migration from the legacy option payload.
     */
    public function maybeMigrateLegacyOptions(): void
    {
        if ((string) get_option(self::MIGRATION_OPTION, '') !== '') {
            return;
        }

        $legacy_options = get_option('sip_plugin_options', array());

        if (! is_array($legacy_options) || empty($legacy_options)) {
            update_option(self::MIGRATION_OPTION, SIMPLE_IMAGE_POPUP_VERSION);

            return;
        }

        if (! add_option(self::MIGRATION_LOCK, '1', '', false)) {
            return;
        }

        try {
            if (! $this->popup_repository->hasMigratedPopup()) {
                $result = $this->popup_repository->createMigratedPopup($legacy_options);

                if ($result instanceof WP_Error) {
                    return;
                }
            }

            update_option(self::MIGRATION_OPTION, SIMPLE_IMAGE_POPUP_VERSION);
        } finally {
            delete_option(self::MIGRATION_LOCK);
        }
    }
}
