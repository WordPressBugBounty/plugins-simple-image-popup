<?php

declare(strict_types=1);

namespace SimpleImagePopup\Admin;

use SimpleImagePopup\Domain\PopupRepository;
use SimpleImagePopup\Frontend\AssetManager;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Renders the custom admin application shell.
 */
final class AdminPage
{
    /**
     * Admin page slug.
     */
    public const PAGE_SLUG = 'simple-image-popup';

    /**
     * Hook suffix returned when WordPress registers the Settings submenu.
     *
     * @var string|false
     */
    private $menu_hook = false;

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
     * Create the admin page controller.
     *
     * @param AssetManager   $asset_manager Asset enqueue helper.
     * @param PopupRepository $popup_repository Popup data source.
     */
    public function __construct(AssetManager $asset_manager, PopupRepository $popup_repository)
    {
        $this->asset_manager    = $asset_manager;
        $this->popup_repository = $popup_repository;
    }

    /**
     * Register the plugin admin menu page.
     */
    public function registerMenu(): void
    {
        $this->menu_hook = add_options_page(
            __('Simple Image Popup', 'simple-image-popup'),
            __('Simple Image Popup', 'simple-image-popup'),
            'manage_options',
            self::PAGE_SLUG,
            array($this, 'render')
        );

        if ($this->menu_hook) {
            add_action('load-' . $this->menu_hook, array($this, 'registerHelpTab'));
        }
    }

    /**
     * Add the CSS reference to the Settings screen's contextual help.
     */
    public function registerHelpTab(): void
    {
        $screen = get_current_screen();

        if ($screen) {
            $screen->add_help_tab(
                array(
                    'id'       => 'simple-image-popup-css-overrides',
                    'title'    => __('CSS Overrides', 'simple-image-popup'),
                    'callback' => array($this, 'renderCssHelp'),
                )
            );
        }
    }

    /**
     * Show examples and the exact frontend stylesheet shipped with the plugin.
     */
    public function renderCssHelp(): void
    {
        $file = SIMPLE_IMAGE_POPUP_PATH . 'css/simple-image-popup.css';
        $css  = is_readable($file) ? file_get_contents($file) : false;
        ?>
        <p><?php esc_html_e('Copy the rules you want to change and paste them into your theme’s Additional CSS. Save your changes and preview a page where the popup is enabled.', 'simple-image-popup'); ?></p>
        <ul>
            <li><?php esc_html_e('For block themes, go to Appearance → Editor → Styles → Styles actions (three dots) → Additional CSS.', 'simple-image-popup'); ?></li>
            <li><?php esc_html_e('For classic themes, go to Appearance → Customize → Additional CSS.', 'simple-image-popup'); ?></li>
        </ul>
        <p><?php esc_html_e('The example changes the overlay and corner radius for all popups. To style just one popup, put its ID before the selector. Inspect the popup on the site to find its ID and replace 123 in the example.', 'simple-image-popup'); ?></p>
        <pre><code>.simple-image-popup-plugin__backdrop {
  background: rgba(0, 0, 0, 0.65);
}

.simple-image-popup-plugin__inner,
.simple-image-popup-plugin__image {
  border-radius: 8px;
}

#simple-image-popup-123 .simple-image-popup-plugin__backdrop {
  background: rgba(20, 30, 50, 0.8);
}</code></pre>
        <p><?php esc_html_e('Set the maximum width in the popup editor. Keep the [hidden] and scroll-lock rules so the popup can close and the page can scroll again.', 'simple-image-popup'); ?></p>
        <?php if (is_string($css)) : ?>
            <p><label for="simple-image-popup-css-source"><strong><?php esc_html_e('Complete default popup CSS', 'simple-image-popup'); ?></strong></label></p>
            <textarea id="simple-image-popup-css-source" class="large-text code" rows="18" readonly spellcheck="false"><?php echo esc_textarea($css); ?></textarea>
        <?php else : ?>
            <p><?php esc_html_e('The default popup stylesheet could not be read. Review css/simple-image-popup.css in the plugin folder.', 'simple-image-popup'); ?></p>
        <?php endif;
    }

    /**
     * Enqueue assets for the custom admin application.
     *
     * @param string $hook_suffix Current admin hook.
     */
    public function enqueueAssets(string $hook_suffix): void
    {
        if ($hook_suffix !== $this->menu_hook) {
            return;
        }

        $this->asset_manager->enqueueAdmin(
            array(
                'apiBase'          => rest_url('simple-image-popup/v1'),
                'nonce'            => wp_create_nonce('wp_rest'),
                'fallbackMessage'  => __('Run `npm run build` to generate the admin assets.', 'simple-image-popup'),
                'hasAdminBundle'   => $this->asset_manager->hasEntry('resources/js/admin/main.js'),
                'initialPopups'    => $this->popup_repository->listPopups(),
                'pageTitle'        => __('Simple Image Popup', 'simple-image-popup'),
            )
        );
    }

    /**
     * Render the root element used by the Vue admin application.
     */
    public function render(): void
    {
        if (! $this->asset_manager->hasEntry('resources/js/admin/main.js')) {
            echo '<div class="wrap"><h1>' . esc_html__('Simple Image Popup', 'simple-image-popup') . '</h1><p>' . esc_html__('The admin application has not been built yet. Run `npm run build` from the plugin workspace.', 'simple-image-popup') . '</p></div>';

            return;
        }

        echo '<div class="wrap"><div id="simple-image-popup-admin"></div></div>';
    }
}
