<?php

declare(strict_types=1);

namespace SimpleImagePopup;

use SimpleImagePopup\Admin\AdminPage;
use SimpleImagePopup\Admin\RestController;
use SimpleImagePopup\Domain\PopupMeta;
use SimpleImagePopup\Domain\PopupPostType;
use SimpleImagePopup\Domain\PopupRepository;
use SimpleImagePopup\Frontend\AssetManager;
use SimpleImagePopup\Frontend\Renderer;
use SimpleImagePopup\Frontend\TargetingService;
use SimpleImagePopup\Infrastructure\AnalyticsRepository;
use SimpleImagePopup\Infrastructure\Installer;
use SimpleImagePopup\Infrastructure\Manifest;
use SimpleImagePopup\Infrastructure\MigrationService;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Coordinates plugin services and WordPress hooks.
 */
final class Plugin
{
    /**
     * The plugin singleton.
     *
     * @var self|null
     */
    private static $instance;

    /**
     * Popup post type registrar.
     *
     * @var PopupPostType
     */
    private $post_type;

    /**
     * Popup meta registrar.
     *
     * @var PopupMeta
     */
    private $popup_meta;

    /**
     * Popup repository.
     *
     * @var PopupRepository
     */
    private $popup_repository;

    /**
     * Admin page controller.
     *
     * @var AdminPage
     */
    private $admin_page;

    /**
     * REST controller.
     *
     * @var RestController
     */
    private $rest_controller;

    /**
     * Frontend renderer.
     *
     * @var Renderer
     */
    private $renderer;

    /**
     * Migration service.
     *
     * @var MigrationService
     */
    private $migration_service;

    /**
     * Installer service.
     *
     * @var Installer
     */
    private $installer;

    private function __construct()
    {
        $manifest             = new Manifest();
        $asset_manager        = new AssetManager($manifest);
        $analytics_repository = new AnalyticsRepository();
        $this->popup_repository = new PopupRepository($analytics_repository);
        $targeting_service    = new TargetingService($this->popup_repository);

        $this->post_type         = new PopupPostType();
        $this->popup_meta        = new PopupMeta();
        $this->admin_page        = new AdminPage($asset_manager, $this->popup_repository);
        $this->rest_controller   = new RestController($this->popup_repository, $analytics_repository);
        $this->renderer          = new Renderer($asset_manager, $this->popup_repository, $targeting_service);
        $this->migration_service = new MigrationService($this->popup_repository);
        $this->installer         = new Installer();
    }

    /**
     * Boot the plugin singleton.
     */
    public static function boot(): self
    {
        if (! self::$instance instanceof self) {
            self::$instance = new self();
            self::$instance->register();
        }

        return self::$instance;
    }

    /**
     * Register all WordPress hooks used by the plugin.
     */
    public function register(): void
    {
        add_action('init', array($this->post_type, 'register'));
        add_action('init', array($this->popup_meta, 'register'));
        add_action('init', array($this->migration_service, 'maybeMigrateLegacyOptions'), 20);
        add_action('init', array($this->installer, 'maybeUpgrade'), 15);
        add_action('admin_menu', array($this->admin_page, 'registerMenu'));
        add_action('admin_enqueue_scripts', array($this->admin_page, 'enqueueAssets'));
        add_action('rest_api_init', array($this->rest_controller, 'registerRoutes'));
        add_action('wp_enqueue_scripts', array($this->renderer, 'enqueueAssets'));
        add_action('wp_footer', array($this->renderer, 'render'), 100);
    }
}
