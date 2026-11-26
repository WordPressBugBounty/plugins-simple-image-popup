<?php

declare(strict_types=1);

namespace SimpleImagePopup\Admin;

use SimpleImagePopup\Domain\PopupRepository;
use SimpleImagePopup\Infrastructure\AnalyticsRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Exposes admin CRUD endpoints and public view tracking.
 */
final class RestController
{
    /**
     * Popup repository dependency.
     *
     * @var PopupRepository
     */
    private $popup_repository;

    /**
     * Analytics repository dependency.
     *
     * @var AnalyticsRepository
     */
    private $analytics_repository;

    /**
     * Create the controller instance.
     *
     * @param PopupRepository      $popup_repository Popup persistence layer.
     * @param AnalyticsRepository  $analytics_repository Analytics store.
     */
    public function __construct(PopupRepository $popup_repository, AnalyticsRepository $analytics_repository)
    {
        $this->popup_repository     = $popup_repository;
        $this->analytics_repository = $analytics_repository;
    }

    /**
     * Register the plugin REST API routes.
     */
    public function registerRoutes(): void
    {
        register_rest_route(
            'simple-image-popup/v1',
            '/popups',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'listPopups'),
                    'permission_callback' => array($this, 'canManagePopups'),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array($this, 'createPopup'),
                    'permission_callback' => array($this, 'canManagePopups'),
                ),
            )
        );

        register_rest_route(
            'simple-image-popup/v1',
            '/popups/(?P<id>[1-9]\d*)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'getPopup'),
                    'permission_callback' => array($this, 'canManagePopups'),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array($this, 'updatePopup'),
                    'permission_callback' => array($this, 'canManagePopups'),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array($this, 'deletePopup'),
                    'permission_callback' => array($this, 'canManagePopups'),
                ),
            )
        );

        register_rest_route(
            'simple-image-popup/v1',
            '/content-search',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'searchContent'),
                    'permission_callback' => array($this, 'canManagePopups'),
                ),
            )
        );

        register_rest_route(
            'simple-image-popup/v1',
            '/track-view',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array($this, 'trackView'),
                    'permission_callback' => array($this, 'canTrackView'),
                    'args'                => array(
                        'popupId' => array('type' => 'integer', 'minimum' => 1, 'required' => true),
                        'token'   => array('type' => 'string', 'pattern' => '^[a-f0-9]{64}$', 'required' => true),
                    ),
                ),
            )
        );
    }

    /**
     * Determine whether the current user can manage popup data.
     */
    public function canManagePopups(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Return all popup records.
     */
    public function listPopups(): WP_REST_Response
    {
        return rest_ensure_response(
            array(
                'items' => $this->popup_repository->listPopups(),
            )
        );
    }

    /**
     * Return a single popup record.
     *
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response|WP_Error
     */
    public function getPopup(WP_REST_Request $request)
    {
        $popup = $this->popup_repository->getPopup((int) $request->get_url_params()['id']);

        if (! is_array($popup)) {
            return new WP_Error('sip_popup_not_found', __('Popup not found.', 'simple-image-popup'), array('status' => 404));
        }

        return rest_ensure_response($popup);
    }

    /**
     * Create a new popup.
     *
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response|WP_Error
     */
    public function createPopup(WP_REST_Request $request)
    {
        $result = $this->popup_repository->savePopup((array) $request->get_json_params());

        if ($result instanceof WP_Error) {
            return $result;
        }

        return new WP_REST_Response($result, 201);
    }

    /**
     * Update an existing popup.
     *
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response|WP_Error
     */
    public function updatePopup(WP_REST_Request $request)
    {
        $result = $this->popup_repository->savePopup((array) $request->get_json_params(), (int) $request->get_url_params()['id']);

        if ($result instanceof WP_Error) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    /**
     * Delete a popup permanently.
     *
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response|WP_Error
     */
    public function deletePopup(WP_REST_Request $request)
    {
        $deleted = $this->popup_repository->deletePopup((int) $request->get_url_params()['id']);

        if (! $deleted) {
            return new WP_Error('sip_popup_delete_failed', __('The popup could not be deleted.', 'simple-image-popup'), array('status' => 404));
        }

        return rest_ensure_response(array('deleted' => true));
    }

    /**
     * Require a signed token from the rendered popup before accessing the database.
     */
    public function canTrackView(WP_REST_Request $request): bool
    {
        $token    = $request->get_param('token');
        $popup_id = filter_var($request->get_param('popupId'), FILTER_VALIDATE_INT);

        return is_string($token) && strlen($token) === 64
            && $popup_id > 0
            && hash_equals(AnalyticsRepository::viewToken($popup_id), $token);
    }

    /**
     * Search posts and pages for popup targeting.
     *
     * @param WP_REST_Request $request REST request.
     */
    public function searchContent(WP_REST_Request $request): WP_REST_Response
    {
        $search      = sanitize_text_field($request->get_param('search'));
        $include_ids = $request->get_param('include');
        $include_ids = is_array($include_ids) ? array_map('intval', $include_ids) : array();

        return rest_ensure_response(
            array(
                'items' => $this->popup_repository->searchTargetContent($search, $include_ids),
            )
        );
    }

    /**
     * Record a popup view when it becomes visible in the browser.
     *
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response|WP_Error
     */
    public function trackView(WP_REST_Request $request)
    {
        $popup_id = (int) $request->get_param('popupId');

        if (! $this->popup_repository->isActivePopup($popup_id)) {
            return new WP_Error('sip_popup_not_found', __('Popup not found.', 'simple-image-popup'), array('status' => 404));
        }

        if (! $this->analytics_repository->recordView($popup_id)) {
            return new WP_Error('sip_view_unavailable', __('The popup view could not be recorded.', 'simple-image-popup'), array('status' => 503));
        }

        return new WP_REST_Response(null, 204);
    }
}
