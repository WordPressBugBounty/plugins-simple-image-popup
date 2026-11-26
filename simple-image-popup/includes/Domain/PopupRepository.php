<?php

declare(strict_types=1);

namespace SimpleImagePopup\Domain;

use SimpleImagePopup\Infrastructure\AnalyticsRepository;
use WP_Error;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Handles popup persistence and data mapping.
 */
final class PopupRepository
{
    /**
     * Analytics repository dependency.
     *
     * @var AnalyticsRepository
     */
    private $analytics_repository;

    /**
     * Create a repository instance.
     *
     * @param AnalyticsRepository $analytics_repository Analytics store.
     */
    public function __construct(AnalyticsRepository $analytics_repository)
    {
        $this->analytics_repository = $analytics_repository;
    }

    /**
     * Return all stored popups for the admin application.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listPopups(): array
    {
        $posts = get_posts(
            array(
                'post_type'      => PopupPostType::POST_TYPE,
                'post_status'    => array('publish', 'draft'),
                'posts_per_page' => -1,
                'orderby'        => 'date',
                'order'          => 'DESC',
            )
        );

        if (empty($posts)) {
            return array();
        }

        $popup_ids    = wp_list_pluck($posts, 'ID');
        $view_counts  = $this->analytics_repository->getViewCounts($popup_ids);
        $popup_records = array();

        foreach ($posts as $post) {
            $popup_records[] = $this->mapPopup($post, (int) ($view_counts[$post->ID] ?? 0));
        }

        return $popup_records;
    }

    /**
     * Fetch a single popup record by ID.
     *
     * @param int $popup_id Popup post ID.
     * @return array<string, mixed>|null
     */
    public function getPopup(int $popup_id): ?array
    {
        $post = $popup_id > 0 ? get_post($popup_id) : null;

        if (! $post instanceof WP_Post || $post->post_type !== PopupPostType::POST_TYPE) {
            return null;
        }

        return $this->mapPopup($post, $this->analytics_repository->getViewCount($popup_id));
    }

    /**
     * Check eligibility for view counting without loading private analytics or targeting data.
     */
    public function isActivePopup(int $popup_id): bool
    {
        $post = $popup_id > 0 ? get_post($popup_id) : null;

        return $post instanceof WP_Post
            && $post->post_type === PopupPostType::POST_TYPE
            && $post->post_status === 'publish'
            && (bool) get_post_meta($popup_id, '_sip_enabled', true)
            && $this->getImageUrl($popup_id, (int) get_post_meta($popup_id, '_sip_image_id', true)) !== '';
    }

    /**
     * Save a popup from the admin application payload.
     *
     * @param array<string, mixed> $payload Admin payload.
     * @param int                  $popup_id Existing popup ID, or zero to create a new popup.
     * @return array<string, mixed>|WP_Error
     */
    public function savePopup(array $payload, int $popup_id = 0)
    {
        $sanitized = $this->sanitizePayload($payload);

        if ($sanitized['title'] === '') {
            return new WP_Error('sip_missing_title', __('Popup title is required.', 'simple-image-popup'), array('status' => 400));
        }

        if ($sanitized['image_id'] === 0 && $sanitized['image_url'] === '') {
            return new WP_Error('sip_missing_image', __('Select an image or provide an image URL.', 'simple-image-popup'), array('status' => 400));
        }

        $post_data = array(
            'post_type'   => PopupPostType::POST_TYPE,
            'post_status' => $sanitized['status'],
            'post_title'  => $sanitized['title'],
        );

        if ($popup_id > 0) {
            $existing_popup = get_post($popup_id);

            if (! $existing_popup instanceof WP_Post || $existing_popup->post_type !== PopupPostType::POST_TYPE) {
                return new WP_Error('sip_popup_not_found', __('Popup not found.', 'simple-image-popup'), array('status' => 404));
            }

            $post_data['ID'] = $popup_id;
            $result = wp_update_post($post_data, true);
        } else {
            $result = wp_insert_post($post_data, true);
        }

        if ($result instanceof WP_Error) {
            return $result;
        }

        $popup_id = (int) $result;

        update_post_meta($popup_id, '_sip_enabled', $sanitized['enabled']);
        update_post_meta($popup_id, '_sip_image_id', $sanitized['image_id']);
        update_post_meta($popup_id, '_sip_image_url', $sanitized['image_url']);
        update_post_meta($popup_id, '_sip_link_url', $sanitized['link_url']);
        update_post_meta($popup_id, '_sip_max_width', $sanitized['max_width']);
        update_post_meta($popup_id, '_sip_delay_seconds', $sanitized['delay_seconds']);
        update_post_meta($popup_id, '_sip_repeat_minutes', $sanitized['repeat_minutes']);
        update_post_meta($popup_id, '_sip_click_to_close', $sanitized['click_to_close']);
        update_post_meta($popup_id, '_sip_display_key', $sanitized['display_key']);
        update_post_meta($popup_id, '_sip_target_mode', $sanitized['target_mode']);
        update_post_meta($popup_id, '_sip_target_post_ids', $sanitized['target_post_ids']);

        return $this->getPopup($popup_id);
    }

    /**
     * Delete a popup permanently.
     *
     * @param int $popup_id Popup post ID.
     */
    public function deletePopup(int $popup_id): bool
    {
        $post = $popup_id > 0 ? get_post($popup_id) : null;

        if (! $post instanceof WP_Post || $post->post_type !== PopupPostType::POST_TYPE) {
            return false;
        }

        return wp_delete_post($popup_id, true) instanceof WP_Post;
    }

    /**
     * Return currently active popup records for frontend evaluation.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActivePopups(): array
    {
        $posts = get_posts(
            array(
                'post_type'      => PopupPostType::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'date',
                'order'          => 'ASC',
                'meta_key'       => '_sip_enabled',
                'meta_value'     => '1',
            )
        );

        if (empty($posts)) {
            return array();
        }

        return array_values(
            array_filter(
                array_map(
                    function (WP_Post $post): array {
                        return $this->mapPopup($post, 0);
                    },
                    $posts
                ),
                static function (array $popup): bool {
                    return $popup['imageUrl'] !== '';
                }
            )
        );
    }

    /**
     * Search posts and pages for targeting selection.
     *
     * @param string   $search Search term.
     * @param int[]    $include_ids Explicit IDs to include.
     * @return array<int, array<string, mixed>>
     */
    public function searchTargetContent(string $search = '', array $include_ids = array()): array
    {
        $query_args = array(
            'post_type'      => array('post', 'page'),
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        if ($search !== '') {
            $query_args['s'] = $search;
        }

        if (! empty($include_ids)) {
            $query_args['post__in'] = array_values(array_map('intval', $include_ids));
            $query_args['orderby']  = 'post__in';
            $query_args['posts_per_page'] = count($include_ids);
        }

        $posts = get_posts($query_args);

        return $this->mapTargetPosts($posts);
    }

    /**
     * Determine whether a legacy migration popup already exists.
     */
    public function hasMigratedPopup(): bool
    {
        $posts = get_posts(
            array(
                'post_type'      => PopupPostType::POST_TYPE,
                'post_status'    => array('publish', 'draft'),
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_key'       => '_sip_migrated_from_legacy',
                'meta_compare'   => 'EXISTS',
            )
        );

        return ! empty($posts);
    }

    /**
     * Create a popup record from the old option payload.
     *
     * @param array<string, mixed> $legacy_options Legacy option array.
     * @return array<string, mixed>|WP_Error|null
     */
    public function createMigratedPopup(array $legacy_options)
    {
        $image_url = $this->sanitizeImageUrl($legacy_options['sip_image_url'] ?? '');

        if ($image_url === '') {
            return null;
        }

        $image_id = (int) attachment_url_to_postid($image_url);
        $payload  = array(
            'title'          => __('Migrated Legacy Popup', 'simple-image-popup'),
            'status'         => ! empty($legacy_options['sip_plugin_status']) ? 'publish' : 'draft',
            'enabled'        => ! empty($legacy_options['sip_plugin_status']),
            'imageId'        => $image_id,
            'imageUrl'       => $image_url,
            'linkUrl'        => isset($legacy_options['sip_link']) ? (string) $legacy_options['sip_link'] : '',
            'maxWidth'       => isset($legacy_options['sip_max_width']) ? (int) $legacy_options['sip_max_width'] : 700,
            'delaySeconds'   => isset($legacy_options['sip_popup_before_show']) ? (int) $legacy_options['sip_popup_before_show'] : 1,
            'repeatMinutes'  => isset($legacy_options['sip_popup_expiry']) ? (int) $legacy_options['sip_popup_expiry'] : 30,
            'clickToClose'   => ! empty($legacy_options['sip_click_to_close']),
            'displayKey'     => isset($legacy_options['sip_cookie_name']) ? (string) $legacy_options['sip_cookie_name'] : '',
            'targetMode'     => ! empty($legacy_options['sip_conditional_display']) ? 'selected' : 'all',
            'targetPostIds'  => isset($legacy_options['sip_display_posts']) && is_array($legacy_options['sip_display_posts']) ? $legacy_options['sip_display_posts'] : array(),
        );
        $saved = $this->savePopup($payload);

        if ($saved instanceof WP_Error || ! is_array($saved)) {
            return $saved;
        }

        update_post_meta((int) $saved['id'], '_sip_migrated_from_legacy', SIMPLE_IMAGE_POPUP_VERSION);

        return $this->getPopup((int) $saved['id']);
    }

    /**
     * Map a popup for frontend consumption.
     *
     * @param array<string, mixed> $popup Popup record.
     * @return array<string, mixed>
     */
    public function mapPopupConfig(array $popup): array
    {
        return array(
            'id'            => (int) $popup['id'],
            'title'         => (string) $popup['title'],
            'imageUrl'      => (string) $popup['imageUrl'],
            'linkUrl'       => (string) $popup['linkUrl'],
            'maxWidth'      => (int) $popup['maxWidth'],
            'delaySeconds'  => (int) $popup['delaySeconds'],
            'repeatMinutes' => (int) $popup['repeatMinutes'],
            'clickToClose'  => (bool) $popup['clickToClose'],
            'displayKey'    => (string) $popup['displayKey'],
            'viewToken'     => AnalyticsRepository::viewToken((int) $popup['id']),
        );
    }

    /**
     * Sanitize an incoming popup payload.
     *
     * @param array<string, mixed> $payload Raw payload.
     * @return array<string, mixed>
     */
    private function sanitizePayload(array $payload): array
    {
        $image_id  = isset($payload['imageId']) ? max(0, (int) $payload['imageId']) : 0;
        $image_url = $this->sanitizeImageUrl($payload['imageUrl'] ?? '');
        $link_url  = $payload['linkUrl'] ?? '';

        if ($image_id > 0) {
            $attachment_url = $this->sanitizeImageUrl(wp_get_attachment_image_url($image_id, 'full'));

            if ($attachment_url !== '') {
                $image_url = $attachment_url;
            } else {
                $image_id = 0;
            }
        }

        return array(
            'title'          => sanitize_text_field($payload['title'] ?? ''),
            'status'         => $this->sanitizeStatus($payload['status'] ?? 'draft'),
            'enabled'        => ! empty($payload['enabled']),
            'image_id'       => $image_id,
            'image_url'      => $image_url,
            'link_url'       => is_string($link_url) ? esc_url_raw($link_url) : '',
            'max_width'      => isset($payload['maxWidth']) ? max(200, (int) $payload['maxWidth']) : 700,
            'delay_seconds'  => isset($payload['delaySeconds']) ? max(0, (int) $payload['delaySeconds']) : 1,
            'repeat_minutes' => isset($payload['repeatMinutes']) ? max(0, (int) $payload['repeatMinutes']) : 30,
            'click_to_close' => ! empty($payload['clickToClose']),
            'display_key'    => $this->sanitizeDisplayKey($payload['displayKey'] ?? ''),
            'target_mode'    => $this->sanitizeTargetMode($payload['targetMode'] ?? 'all'),
            'target_post_ids' => $this->sanitizeTargetPostIds($payload['targetPostIds'] ?? array()),
        );
    }

    /**
     * Map a WordPress popup post into the app data shape.
     *
     * @param WP_Post $post Popup post.
     * @param int     $view_count Aggregated views.
     * @return array<string, mixed>
     */
    private function mapPopup(WP_Post $post, int $view_count): array
    {
        $image_id       = (int) get_post_meta($post->ID, '_sip_image_id', true);
        $image_url      = $this->getImageUrl((int) $post->ID, $image_id);
        $target_post_ids = get_post_meta($post->ID, '_sip_target_post_ids', true);

        if (! is_array($target_post_ids)) {
            $target_post_ids = array();
        }

        return array(
            'id'             => (int) $post->ID,
            'title'          => $post->post_title,
            'status'         => $post->post_status,
            'enabled'        => (bool) get_post_meta($post->ID, '_sip_enabled', true),
            'imageId'        => $image_id,
            'imageUrl'       => $image_url,
            'linkUrl'        => (string) get_post_meta($post->ID, '_sip_link_url', true),
            'maxWidth'       => max(200, (int) get_post_meta($post->ID, '_sip_max_width', true)),
            'delaySeconds'   => max(0, (int) get_post_meta($post->ID, '_sip_delay_seconds', true)),
            'repeatMinutes'  => max(0, (int) get_post_meta($post->ID, '_sip_repeat_minutes', true)),
            'clickToClose'   => (bool) get_post_meta($post->ID, '_sip_click_to_close', true),
            'displayKey'     => $this->sanitizeDisplayKey((string) get_post_meta($post->ID, '_sip_display_key', true), $post->ID),
            'targetMode'     => $this->sanitizeTargetMode((string) get_post_meta($post->ID, '_sip_target_mode', true)),
            'targetPostIds'  => array_values(array_map('intval', $target_post_ids)),
            'targetPosts'    => empty($target_post_ids) ? array() : $this->searchTargetContent('', $target_post_ids),
            'views'          => $view_count,
            'isMigrated'     => (string) get_post_meta($post->ID, '_sip_migrated_from_legacy', true) !== '',
            'dateCreated'    => mysql2date('c', $post->post_date_gmt),
            'dateModified'   => mysql2date('c', $post->post_modified_gmt),
        );
    }

    private function getImageUrl(int $popup_id, int $image_id): string
    {
        $attachment_url = $image_id > 0
            ? $this->sanitizeImageUrl(wp_get_attachment_image_url($image_id, 'full'))
            : '';

        return $attachment_url !== ''
            ? $attachment_url
            : $this->sanitizeImageUrl(get_post_meta($popup_id, '_sip_image_url', true));
    }

    /**
     * Allow web image URLs, including site-relative paths.
     *
     * @param mixed $value Raw image URL.
     */
    private function sanitizeImageUrl($value): string
    {
        return is_string($value) ? esc_url_raw($value, array('http', 'https')) : '';
    }

    /**
     * Map selected target posts into a serializable shape.
     *
     * @param array<int, WP_Post> $posts Post records.
     * @return array<int, array<string, mixed>>
     */
    private function mapTargetPosts(array $posts): array
    {
        return array_map(
            static function (WP_Post $post): array {
                return array(
                    'id'    => (int) $post->ID,
                    'title' => $post->post_title !== '' ? $post->post_title : __('(no title)', 'simple-image-popup'),
                    'type'  => $post->post_type,
                    'url'   => (string) get_permalink($post),
                );
            },
            $posts
        );
    }

    /**
     * Sanitize the popup publish status.
     *
     * @param mixed $status Raw status value.
     */
    private function sanitizeStatus($status): string
    {
        return in_array($status, array('publish', 'draft'), true) ? (string) $status : 'draft';
    }

    /**
     * Sanitize the popup targeting mode.
     *
     * @param mixed $target_mode Raw target mode value.
     */
    private function sanitizeTargetMode($target_mode): string
    {
        return $target_mode === 'selected' ? 'selected' : 'all';
    }

    /**
     * Sanitize selected target post IDs.
     *
     * @param mixed $target_post_ids Raw post IDs.
     * @return int[]
     */
    private function sanitizeTargetPostIds($target_post_ids): array
    {
        if (! is_array($target_post_ids)) {
            return array();
        }

        return array_values(
            array_filter(
                array_map(
                    static function ($item): int {
                        $post_id = (int) $item;

                        return $post_id > 0 && get_post($post_id) ? $post_id : 0;
                    },
                    $target_post_ids
                )
            )
        );
    }

    /**
     * Sanitize or generate the browser storage key used for repeat frequency.
     *
     * @param mixed    $display_key Raw key value.
     * @param int|null $popup_id Popup ID for fallback generation.
     */
    private function sanitizeDisplayKey($display_key, ?int $popup_id = null): string
    {
        $display_key = is_string($display_key) ? strtolower($display_key) : '';
        $display_key = preg_replace('/[^a-z0-9_-]/', '-', $display_key);
        $display_key = trim((string) $display_key, '-');

        if ($display_key === '') {
            return 'sip-popup-' . (string) ($popup_id ?? wp_rand(1000, 999999));
        }

        return $display_key;
    }
}
