<?php

declare(strict_types=1);

namespace SimpleImagePopup\Domain;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers popup meta fields and sanitization rules.
 */
final class PopupMeta
{
    /**
     * Register popup meta fields.
     */
    public function register(): void
    {
        $this->registerBooleanMeta('_sip_enabled', false);
        $this->registerIntegerMeta('_sip_image_id', 0);
        $this->registerStringMeta('_sip_image_url', '');
        $this->registerStringMeta('_sip_link_url', '');
        $this->registerIntegerMeta('_sip_max_width', 700);
        $this->registerIntegerMeta('_sip_delay_seconds', 1);
        $this->registerIntegerMeta('_sip_repeat_minutes', 30);
        $this->registerBooleanMeta('_sip_click_to_close', false);
        $this->registerStringMeta('_sip_display_key', '');
        $this->registerStringMeta('_sip_target_mode', 'all');
        $this->registerIntegerArrayMeta('_sip_target_post_ids');
        $this->registerStringMeta('_sip_migrated_from_legacy', '');
    }

    private function registerBooleanMeta(string $key, bool $default): void
    {
        register_post_meta(
            PopupPostType::POST_TYPE,
            $key,
            array(
                'single'            => true,
                'type'              => 'boolean',
                'default'           => $default,
                'sanitize_callback' => static function ($value): bool {
                    return ! empty($value);
                },
                'auth_callback'     => static function (): bool {
                    return current_user_can('manage_options');
                },
            )
        );
    }

    private function registerIntegerMeta(string $key, int $default): void
    {
        register_post_meta(
            PopupPostType::POST_TYPE,
            $key,
            array(
                'single'            => true,
                'type'              => 'integer',
                'default'           => $default,
                'sanitize_callback' => static function ($value): int {
                    return max(0, (int) $value);
                },
                'auth_callback'     => static function (): bool {
                    return current_user_can('manage_options');
                },
            )
        );
    }

    private function registerStringMeta(string $key, string $default): void
    {
        register_post_meta(
            PopupPostType::POST_TYPE,
            $key,
            array(
                'single'            => true,
                'type'              => 'string',
                'default'           => $default,
                'sanitize_callback' => static function ($value): string {
                    return is_string($value) ? sanitize_text_field($value) : '';
                },
                'auth_callback'     => static function (): bool {
                    return current_user_can('manage_options');
                },
            )
        );
    }

    private function registerIntegerArrayMeta(string $key): void
    {
        register_post_meta(
            PopupPostType::POST_TYPE,
            $key,
            array(
                'single'            => true,
                'type'              => 'array',
                'default'           => array(),
                'sanitize_callback' => static function ($value): array {
                    if (! is_array($value)) {
                        return array();
                    }

                    return array_values(
                        array_filter(
                            array_map('intval', $value),
                            static function (int $item): bool {
                                return $item > 0;
                            }
                        )
                    );
                },
                'auth_callback'     => static function (): bool {
                    return current_user_can('manage_options');
                },
            )
        );
    }
}
