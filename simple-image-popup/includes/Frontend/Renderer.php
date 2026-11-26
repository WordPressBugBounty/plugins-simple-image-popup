<?php

declare(strict_types=1);

namespace SimpleImagePopup\Frontend;

use SimpleImagePopup\Domain\PopupRepository;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Renders popup markup and frontend configuration.
 */
final class Renderer
{
    /**
     * Asset manager dependency.
     *
     * @var AssetManager
     */
    private $asset_manager;

    /**
     * Popup repository dependency.
     *
     * @var PopupRepository
     */
    private $popup_repository;

    /**
     * Targeting service dependency.
     *
     * @var TargetingService
     */
    private $targeting_service;

    /**
     * Cached eligible popups for the current request.
     *
     * @var array<int, array<string, mixed>>
     */
    private $eligible_popups = array();

    /**
     * Create the renderer instance.
     *
     * @param AssetManager    $asset_manager Asset enqueue helper.
     * @param PopupRepository $popup_repository Popup data source.
     * @param TargetingService $targeting_service Targeting evaluator.
     */
    public function __construct(
        AssetManager $asset_manager,
        PopupRepository $popup_repository,
        TargetingService $targeting_service
    ) {
        $this->asset_manager    = $asset_manager;
        $this->popup_repository = $popup_repository;
        $this->targeting_service = $targeting_service;
    }

    /**
     * Enqueue frontend assets only when the current request has eligible popups.
     */
    public function enqueueAssets(): void
    {
        $this->eligible_popups = $this->targeting_service->getEligiblePopups();

        if (empty($this->eligible_popups)) {
            return;
        }

        $popup_configs = array_map(array($this->popup_repository, 'mapPopupConfig'), $this->eligible_popups);

        $this->asset_manager->enqueueFrontend(
            array(
                'trackUrl'   => rest_url('simple-image-popup/v1/track-view'),
                'popups'     => array_values($popup_configs),
            )
        );
    }

    /**
     * Render hidden popup markup for the current request.
     */
    public function render(): void
    {
        if (empty($this->eligible_popups)) {
            return;
        }

        echo '<div class="simple-image-popup-plugin__mount">';

        foreach ($this->eligible_popups as $popup) {
            $popup_id      = (int) $popup['id'];
            $title         = (string) $popup['title'];
            $image_url     = (string) $popup['imageUrl'];
            $link_url      = (string) $popup['linkUrl'];
            $click_to_close = ! empty($popup['clickToClose']);
            $max_width     = max(200, (int) $popup['maxWidth']);

            if ($image_url === '') {
                continue;
            }

            ?>
            <div
                id="simple-image-popup-<?php echo esc_attr((string) $popup_id); ?>"
                class="simple-image-popup-plugin"
                data-popup-id="<?php echo esc_attr((string) $popup_id); ?>"
                data-click-to-close="<?php echo $click_to_close ? '1' : '0'; ?>"
                hidden
            >
                <div class="simple-image-popup-plugin__backdrop" data-popup-close="backdrop"></div>
                <div
                    class="simple-image-popup-plugin__inner"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="simple-image-popup-title-<?php echo esc_attr((string) $popup_id); ?>"
                    tabindex="-1"
                    style="max-width: <?php echo esc_attr((string) $max_width); ?>px;"
                >
                    <h2
                        id="simple-image-popup-title-<?php echo esc_attr((string) $popup_id); ?>"
                        class="simple-image-popup-plugin__title screen-reader-text"
                    >
                        <?php echo esc_html($title); ?>
                    </h2>
                    <button
                        type="button"
                        class="simple-image-popup-plugin__close"
                        aria-label="<?php echo esc_attr__('Close popup', 'simple-image-popup'); ?>"
                        data-popup-close="button"
                    >
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <?php if ($link_url !== '' && ! $click_to_close) : ?>
                        <a href="<?php echo esc_url($link_url); ?>" class="simple-image-popup-plugin__link">
                    <?php endif; ?>
                    <img
                        src="<?php echo esc_url($image_url); ?>"
                        alt="<?php echo esc_attr($title); ?>"
                        class="simple-image-popup-plugin__image"
                        <?php if ($click_to_close) : ?>
                            data-popup-close="image"
                        <?php endif; ?>
                    />
                    <?php if ($link_url !== '' && ! $click_to_close) : ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }

        echo '</div>';
    }

}
