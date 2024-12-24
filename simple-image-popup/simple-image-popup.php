<?php

/**
 * Plugin Name: Simple Image Popup
 * Description: Display a simple image in a lightbox on page load with optional conditional display on selected posts/pages.
 * Author: Mr Digital
 * Author URI: https://www.mrdigital.com.au
 * Text Domain: simple-image-popup
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Version: 2.5.8
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SimpleImagePopup')) :

    class SimpleImagePopup
    {

        public function __construct()
        {
            add_action('wp_enqueue_scripts', array($this, 'assets'));
            add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
            add_action('admin_init', array($this, 'plugin_options'));
            add_action('admin_menu', array($this, 'plugin_menu'));
            add_action('wp_footer', array($this, 'popup'), 100);
        }

        public function assets()
        {
            wp_enqueue_script('jquery');
            wp_register_style('simple-image-popup', plugin_dir_url(__FILE__) . '/css/simple-image-popup.css', array(), false, 'all');
            wp_enqueue_style('simple-image-popup');
        }

        public function admin_assets()
        {
            wp_enqueue_media();
            wp_register_script('media-uploader', plugins_url('js/media-uploader.js', __FILE__), array('jquery'));
            wp_enqueue_script('media-uploader');
        }

        public function plugin_options()
        {
            if (false == get_option('sip_plugin_options')) {
                add_option('sip_plugin_options');
            }

            add_settings_section(
                'sip_image_options',
                '',
                '',
                'sip_plugin_options'
            );

            add_settings_field(
                'sip_image_status',
                'Active',
                array($this, 'sip_image_status_callback'),
                'sip_plugin_options',
                'sip_image_options'
            );

            add_settings_field(
                'sip_image_url',
                'Image URL',
                array($this, 'sip_image_url_callback'),
                'sip_plugin_options',
                'sip_image_options'
            );

            add_settings_field(
                'sip_max_width',
                'Image Max Width (px)',
                array($this, 'sip_max_width_callback'),
                'sip_plugin_options',
                'sip_image_options'
            );

            add_settings_field(
                'sip_link',
                'Link URL',
                array($this, 'sip_link_callback'),
                'sip_plugin_options',
                'sip_image_options'
            );

            add_settings_field(
                'sip_click_to_close',
                'Click to close',
                array($this, 'sip_click_to_close_callback'),
                'sip_plugin_options',
                'sip_image_options'
            );

            add_settings_field(
                'sip_cookie_name',
                'Popup ID',
                array($this, 'sip_cookie_callback'),
                'sip_plugin_options',
                'sip_image_options'
            );

            add_settings_field(
                'sip_popup_expiry',
                'Popup expiry (minutes)',
                array($this, 'sip_popup_expiry_callback'),
                'sip_plugin_options',
                'sip_image_options'
            );

            add_settings_field(
                'sip_popup_before_show',
                'Show popup after (seconds)',
                array($this, 'sip_popup_before_show_callback'),
                'sip_plugin_options',
                'sip_image_options'
            );

            // Conditional Display fields
            add_settings_field(
                'sip_conditional_display',
                'Enable Conditional Display',
                array($this, 'sip_conditional_display_callback'),
                'sip_plugin_options',
                'sip_image_options'
            );

            // Add a class to this field's row for easy show/hide
            add_settings_field(
                'sip_display_posts',
                'Select Posts/Pages',
                array($this, 'sip_display_posts_callback'),
                'sip_plugin_options',
                'sip_image_options',
                array('class' => 'sip_display_posts_row')
            );

            register_setting(
                'sip_plugin_options',
                'sip_plugin_options',
                array($this, 'sanitize_options')
            );

            add_action('admin_notices', function () {
                $options = get_option('sip_plugin_options');
                $active = isset($options['sip_plugin_status']) ? true : false;

                if (!$active) {
                    return;
                }

                $current_screen = get_current_screen()->base;

                if ($current_screen == 'dashboard') {
                    echo admin_message('Image popup is enabled', 'success', 'null', 'Edit popup settings', admin_url('options-general.php?page=simple_image_plugin'));
                }
            });

            function admin_message(string $message, string $type = 'success', $classes = null, $link_title = null, $link_url = null): string
            {
                $message = __($message, 'simple-image-popup');

                switch ($type) {
                    case 'success':
                        $class = 'notice notice-success';
                        break;
                    case 'error':
                        $class = 'notice notice-error';
                        break;
                    default:
                        $class = 'notice notice-warning';
                }

                return sprintf('
                  <div class="%1$s" style="display:flex; align-items:center; justify-content:space-between"><p>%2$s</p>
                  <p><a href="%3$s">%4$s</a></p>
                  </div>', esc_attr($class), esc_html($message), esc_url($link_url), esc_html($link_title));
            }
        }

        public function sanitize_options($input)
        {
            $sanitized = array();

            $sanitized['sip_plugin_status']     = !empty($input['sip_plugin_status']) ? 1 : 0;
            $sanitized['sip_image_url']         = !empty($input['sip_image_url']) ? esc_url_raw($input['sip_image_url']) : '';
            $sanitized['sip_link']              = !empty($input['sip_link']) ? esc_url_raw($input['sip_link']) : '';
            $sanitized['sip_cookie_name']       = !empty($input['sip_cookie_name']) ? sanitize_text_field($input['sip_cookie_name']) : uniqid();
            $sanitized['sip_max_width']         = isset($input['sip_max_width']) ? intval($input['sip_max_width']) : 700;
            $sanitized['sip_click_to_close']    = !empty($input['sip_click_to_close']) ? 1 : 0;
            $sanitized['sip_popup_expiry']      = isset($input['sip_popup_expiry']) ? intval($input['sip_popup_expiry']) : 30;
            $sanitized['sip_popup_before_show'] = isset($input['sip_popup_before_show']) ? intval($input['sip_popup_before_show']) : 1;

            $sanitized['sip_conditional_display'] = !empty($input['sip_conditional_display']) ? 1 : 0;
            if (!empty($input['sip_display_posts']) && is_array($input['sip_display_posts'])) {
                $valid_ids = array();
                foreach ($input['sip_display_posts'] as $post_id) {
                    $post_id = intval($post_id);
                    if (get_post($post_id)) {
                        $valid_ids[] = $post_id;
                    }
                }
                $sanitized['sip_display_posts'] = $valid_ids;
            } else {
                $sanitized['sip_display_posts'] = array();
            }

            return $sanitized;
        }

        public function plugin_menu()
        {
            add_options_page(
                'Simple Image Popup Options',
                'Simple Image Popup',
                'administrator',
                'simple_image_plugin',
                array($this, 'sip_plugin_page')
            );
        }

        public function sip_plugin_page()
        {
            ?>
            <div class="wrap">
                <h2>Simple Image Popup Options</h2>
                <?php settings_errors(); ?>
                <form method="post" action="options.php">
                    <?php settings_fields('sip_plugin_options'); ?>
                    <?php do_settings_sections('sip_plugin_options'); ?>
                    <?php submit_button(); ?>
                </form>

                <script>
                    (function($) {
                        $(document).ready(function() {
                            var $checkbox = $('#sip_conditional_display');
                            var $row = $('.sip_display_posts_row');

                            function togglePostsRow() {
                                if ($checkbox.is(':checked')) {
                                    $row.show();
                                } else {
                                    $row.hide();
                                }
                            }

                            // Initial check
                            togglePostsRow();

                            // On change
                            $checkbox.on('change', togglePostsRow);
                        });
                    })(jQuery);
                </script>
            </div>
            <?php
        }

        public function sip_image_url_callback($args)
        {
            $options = get_option('sip_plugin_options');
            $image = isset($options['sip_image_url']) ? $options['sip_image_url'] : '';
            $html = '
                <input type="text" id="sip_image_url" name="sip_plugin_options[sip_image_url]" class="regular-text" value="' . esc_attr($image) . '"/>
                <input id="upload_image_button" type="button" class="button-primary" value="Insert Image" />
            ';
            echo $html;
        }

        public function sip_link_callback($args)
        {
            $options = get_option('sip_plugin_options');
            $link = isset($options['sip_link']) ? $options['sip_link'] : '';
            $html = '<input type="text" id="sip_link" name="sip_plugin_options[sip_link]" class="regular-text" value="' . esc_attr($link) . '" placeholder="e.g. https://www.google.com"/>';
            echo $html;
        }

        public function sip_cookie_callback($args)
        {
            $options = get_option('sip_plugin_options');
            $cookie = isset($options['sip_cookie_name']) ? $options['sip_cookie_name'] : uniqid();
            $html = '
                <input type="text" id="sip_cookie_name" name="sip_plugin_options[sip_cookie_name]" class="regular-text" value="' . esc_attr($cookie) . '" placeholder="Cookie name"/>
                <small style="display:block; margin-top:5px">Changing the Popup ID resets its stored state in browsers.</small>
            ';
            echo $html;
        }

        public function sip_max_width_callback($args)
        {
            $options = get_option('sip_plugin_options');
            $width = isset($options['sip_max_width']) ? intval($options['sip_max_width']) : 700;
            $html = '
                <input type="number" id="sip_max_width" name="sip_plugin_options[sip_max_width]" class="regular-text" value="' . esc_attr($width) . '" placeholder="Max width in px"/>
                <small style="display:block; margin-top:5px">Default is 700px.</small>
            ';
            echo $html;
        }

        public function sip_click_to_close_callback($args)
        {
            $options = get_option('sip_plugin_options');
            $status = isset($options['sip_click_to_close']) ? (bool)$options['sip_click_to_close'] : false;
            $html = '<input type="checkbox" id="sip_click_to_close" name="sip_plugin_options[sip_click_to_close]" value="1" ' . checked(1, $status, false) . '/>
            <small style="display:block; margin-top:5px">Check if you want the popup to close when the image is clicked.</small>';
            echo $html;
        }

        public function sip_image_status_callback($args)
        {
            $options = get_option('sip_plugin_options');
            $status = isset($options['sip_plugin_status']) ? (bool)$options['sip_plugin_status'] : false;
            $html = '<input type="checkbox" id="sip_plugin_status" name="sip_plugin_options[sip_plugin_status]" value="1" ' . checked(1, $status, false) . '/>';
            echo $html;
        }

        public function sip_popup_expiry_callback($args)
        {
            $options = get_option('sip_plugin_options');
            $expiry = isset($options['sip_popup_expiry']) ? intval($options['sip_popup_expiry']) : 30;
            $html = '
                <input type="number" min="0" id="sip_popup_expiry" name="sip_plugin_options[sip_popup_expiry]" value="' . esc_attr($expiry) . '" placeholder="0 to disable"/>
                <small style="display:block; margin-top:5px">Set 0 to show the popup on every page load.</small>
            ';
            echo $html;
        }

        public function sip_popup_before_show_callback($args)
        {
            $options = get_option('sip_plugin_options');
            $seconds = isset($options['sip_popup_before_show']) ? intval($options['sip_popup_before_show']) : 1;
            $html = '
                <input type="number" min="0" id="sip_popup_before_show" name="sip_plugin_options[sip_popup_before_show]" value="' . esc_attr($seconds) . '" placeholder="0 to show immediately"/>
                <small style="display:block; margin-top:5px">Number of seconds to wait before showing the popup.</small>
            ';
            echo $html;
        }

        public function sip_conditional_display_callback($args)
        {
            $options = get_option('sip_plugin_options');
            $enabled = isset($options['sip_conditional_display']) ? (bool)$options['sip_conditional_display'] : false;
            $html = '<input type="checkbox" id="sip_conditional_display" name="sip_plugin_options[sip_conditional_display]" value="1" ' . checked(1, $enabled, false) . '/>
            <small style="display:block; margin-top:5px">Check to only display on selected posts/pages below.</small>';
            echo $html;
        }

        public function sip_display_posts_callback($args)
        {
            $options = get_option('sip_plugin_options');
            $selected_ids = isset($options['sip_display_posts']) ? (array)$options['sip_display_posts'] : array();

            $posts = get_posts(array('numberposts' => -1, 'post_type' => array('post', 'page'), 'orderby' => 'title', 'order' => 'ASC'));

            $html = '<select name="sip_plugin_options[sip_display_posts][]" multiple style="height:200px; width:250px;">';
            foreach ($posts as $p) {
                $selected = in_array($p->ID, $selected_ids) ? 'selected="selected"' : '';
                $html .= '<option value="' . intval($p->ID) . '" ' . $selected . '>' . esc_html($p->post_title) . ' (' . esc_html($p->post_type) . ')</option>';
            }
            $html .= '</select><br><small>Select multiple by holding Ctrl/Cmd and clicking.</small>';

            echo $html;
        }

        public function popup()
        {
            $options = get_option('sip_plugin_options');
            $image_url          = isset($options['sip_image_url']) ? esc_url($options['sip_image_url']) : '';
            $link               = isset($options['sip_link']) ? esc_url($options['sip_link']) : '';
            $active             = isset($options['sip_plugin_status']) && $options['sip_plugin_status'] == 1 ? true : false;
            $expiry             = isset($options['sip_popup_expiry']) ? intval($options['sip_popup_expiry']) : 0;
            $seconds_before_show= isset($options['sip_popup_before_show']) ? intval($options['sip_popup_before_show']) * 1000 : 0;
            $clicktoclose       = isset($options['sip_click_to_close']) && $options['sip_click_to_close'] == 1 ? true : false;
            $cookie_name        = isset($options['sip_cookie_name']) ? esc_js($options['sip_cookie_name']) : '';
            $max_width          = isset($options['sip_max_width']) ? intval($options['sip_max_width']) : 700;
            $conditional        = isset($options['sip_conditional_display']) && $options['sip_conditional_display'] == 1 ? true : false;
            $display_posts      = isset($options['sip_display_posts']) ? (array)$options['sip_display_posts'] : array();

            if ($conditional) {
                if (!is_singular() || !in_array(get_queried_object_id(), $display_posts)) {
                    return;
                }
            }

            if ($active && !empty($image_url)) : ?>

                <div id="simple-image-popup" class="simple-image-popup-plugin" style="display:none;">
                    <div class="simple-image-popup-plugin__inner" role="dialog" aria-modal="true" aria-label="Popup" tabindex="0" style="width:<?php echo esc_attr($max_width); ?>px; max-width:90%; margin:0 auto;">
                        <button id="simple-image-popup-plugin__close" aria-label="Close popup">
                            <svg class="simple-image-popup-plugin__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512">
                                <path d="M315.3 411.3c-6.253 6.253-16.37 6.253-22.63 0L160 278.6l-132.7 132.7c-6.253 6.253-16.37 6.253-22.63 0c-6.253-6.253-6.253-16.37 0-22.63L137.4 256L4.69 123.3c-6.253-6.253-6.253-16.37 0-22.63c6.253-6.253 16.37-6.253 22.63 0L160 233.4l132.7-132.7c6.253-6.253 16.37-6.253 22.63 0c6.253 6.253 6.253 16.37 0 22.63L182.6 256l132.7 132.7C321.6 394.9 321.6 405.1 315.3 411.3z" />
                            </svg>
                        </button>

                        <?php if ($link && !$clicktoclose) : ?>
                            <a href="<?php echo esc_url($link); ?>">
                        <?php endif; ?>

                            <img src="<?php echo esc_url($image_url); ?>" <?php if ($clicktoclose): ?>id="closeimage" style="cursor:pointer"<?php endif; ?> class="simple-image-popup-plugin__image" alt="Popup Image">

                        <?php if ($link && !$clicktoclose) : ?>
                            </a>
                        <?php endif; ?>

                    </div>
                </div>

                <script>
                    (function($) {
                        var openPopup = false;
                        var popupValue = localStorage.getItem('<?php echo $cookie_name; ?>');
                        var expiryMinutes = <?php echo intval($expiry); ?>;
                        var showDelay = <?php echo intval($seconds_before_show); ?>;
                        var lastFocus = null;

                        if (!popupValue) {
                            var time = new Date();
                            if (expiryMinutes > 0) {
                                time.setMinutes(time.getMinutes() + expiryMinutes);
                            }
                            localStorage.setItem('<?php echo $cookie_name; ?>', time);
                            openPopup = true;
                        } else {
                            var timeNow = new Date();
                            var lastOpened = new Date(popupValue);

                            if (timeNow >= lastOpened) {
                                openPopup = true;
                                localStorage.removeItem('<?php echo $cookie_name; ?>');
                                var newTime = new Date();
                                if (expiryMinutes > 0) {
                                    newTime.setMinutes(newTime.getMinutes() + expiryMinutes);
                                }
                                localStorage.setItem('<?php echo $cookie_name; ?>', newTime);
                            } else {
                                openPopup = false;
                            }
                        }

                        function closePopup() {
                            $('#simple-image-popup').fadeOut(300, function() {
                                if (lastFocus && lastFocus.focus) {
                                    lastFocus.focus();
                                }
                                $(document).off('keydown.popupClose');
                            });
                        }

                        if (openPopup) {
                            $(document).ready(function() {
                                setTimeout(function() {
                                    lastFocus = document.activeElement;

                                    $('#simple-image-popup').fadeIn(300, function() {
                                        $('#simple-image-popup-plugin__close').focus();
                                    });

                                    $('#simple-image-popup-plugin__close').on('click', function() {
                                        closePopup();
                                    });

                                    <?php if ($clicktoclose): ?>
                                    $('#simple-image-popup').on('click', function(e) {
                                        if ($(e.target).closest('.simple-image-popup-plugin__inner').length === 0 || e.target.id === 'closeimage') {
                                            closePopup();
                                        }
                                    });
                                    <?php endif; ?>

                                    $(document).on('keydown.popupClose', function(e) {
                                        if (e.key === 'Escape') {
                                            closePopup();
                                        }
                                    });
                                }, showDelay);
                            });
                        }
                    })(jQuery);
                </script>
            <?php endif;
        }
    }

    $simpleImagePopup = new SimpleImagePopup();

endif;
