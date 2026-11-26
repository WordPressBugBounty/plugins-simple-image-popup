<?php

declare(strict_types=1);

namespace SimpleImagePopup\Frontend;

use SimpleImagePopup\Domain\PopupRepository;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Evaluates whether a popup should be shown for the current request.
 */
final class TargetingService
{
    /**
     * Popup repository dependency.
     *
     * @var PopupRepository
     */
    private $popup_repository;

    /**
     * Create the service instance.
     *
     * @param PopupRepository $popup_repository Popup data source.
     */
    public function __construct(PopupRepository $popup_repository)
    {
        $this->popup_repository = $popup_repository;
    }

    /**
     * Filter active popups to the ones that match the current request.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getEligiblePopups(): array
    {
        $popups = $this->popup_repository->getActivePopups();

        return array_values(
            array_filter(
                $popups,
                array($this, 'matchesPopup')
            )
        );
    }

    /**
     * Decide whether a popup matches the current page.
     *
     * @param array<string, mixed> $popup Popup record.
     */
    public function matchesPopup(array $popup): bool
    {
        if (empty($popup['enabled']) || empty($popup['imageUrl'])) {
            return false;
        }

        if (($popup['targetMode'] ?? 'all') !== 'selected') {
            return true;
        }

        if (! is_singular()) {
            return false;
        }

        $target_post_ids = isset($popup['targetPostIds']) && is_array($popup['targetPostIds'])
            ? array_map('intval', $popup['targetPostIds'])
            : array();

        return in_array(get_queried_object_id(), $target_post_ids, true);
    }
}
