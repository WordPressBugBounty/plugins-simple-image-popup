<?php

declare(strict_types=1);

namespace SimpleImagePopup\Domain;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers the internal popup custom post type.
 */
final class PopupPostType
{
    public const POST_TYPE = 'sip_popup';

    /**
     * Register the popup post type.
     */
    public function register(): void
    {
        register_post_type(
            self::POST_TYPE,
            array(
                'labels' => array(
                    'name'               => __('Popups', 'simple-image-popup'),
                    'singular_name'      => __('Popup', 'simple-image-popup'),
                    'add_new'            => __('Add Popup', 'simple-image-popup'),
                    'add_new_item'       => __('Add Popup', 'simple-image-popup'),
                    'edit_item'          => __('Edit Popup', 'simple-image-popup'),
                    'new_item'           => __('New Popup', 'simple-image-popup'),
                    'view_item'          => __('View Popup', 'simple-image-popup'),
                    'search_items'       => __('Search Popups', 'simple-image-popup'),
                    'not_found'          => __('No popups found.', 'simple-image-popup'),
                    'not_found_in_trash' => __('No popups found in Trash.', 'simple-image-popup'),
                ),
                'public'              => false,
                'show_ui'             => false,
                'show_in_menu'        => false,
                'show_in_rest'        => false,
                'menu_position'       => 58,
                'menu_icon'           => 'dashicons-format-image',
                'supports'            => array('title'),
                'capability_type'     => 'post',
                'capabilities'        => array_fill_keys(
                    array(
                        'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts',
                        'publish_posts', 'read_private_posts', 'delete_posts', 'delete_private_posts',
                        'delete_published_posts', 'delete_others_posts', 'edit_private_posts',
                        'edit_published_posts', 'create_posts', 'read',
                    ),
                    'manage_options'
                ),
                'map_meta_cap'        => false,
                'hierarchical'        => false,
                'rewrite'             => false,
                'query_var'           => false,
                'exclude_from_search' => true,
                'publicly_queryable'  => false,
            )
        );
    }
}
