<?php

namespace Modules\InstagramGallery\Controllers\Superadmin;

use Modules\InstagramGallery\Models\InstagramSetting;
use Screenart\Musedock\View;

class SettingsController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = \Screenart\Musedock\Database::connect();
        InstagramSetting::setPdo($this->pdo);
    }

    /**
     * Verificar si el usuario actual tiene un permiso específico
     * Si no lo tiene, redirige con mensaje de error
     */
    private function checkPermission(string $permission): void
    {
        if (!userCan($permission)) {
            flash('error', __instagram('errors.permission_denied'));
            header('Location: /musedock/dashboard');
            exit;
        }
    }

    /**
     * Show settings page
     */
    public function index()
    {
        $this->checkPermission('instagram.settings');
        // Get all settings
        $settings = InstagramSetting::getAll(null);

        // Get available layouts
        $layouts = InstagramSetting::getAvailableLayouts();

        return View::renderModule('instagram-gallery', 'superadmin.instagram.settings', [
            'settings' => $settings,
            'layouts' => $layouts
        ]);
    }

    /**
     * Update settings
     */
    public function update()
    {
        $this->checkPermission('instagram.settings');
        try {
            $data = $_POST;

            // API Credentials
            if (isset($data['instagram_app_id'])) {
                InstagramSetting::set('instagram_app_id', $data['instagram_app_id'], null, 'string');
            }
            if (isset($data['instagram_app_secret'])) {
                InstagramSetting::set('instagram_app_secret', $data['instagram_app_secret'], null, 'string');
            }
            if (isset($data['instagram_redirect_uri'])) {
                InstagramSetting::set('instagram_redirect_uri', $data['instagram_redirect_uri'], null, 'string');
            }

            // Display settings
            if (isset($data['default_layout'])) {
                InstagramSetting::set('default_layout', $data['default_layout'], null, 'string');
            }
            if (isset($data['default_columns'])) {
                InstagramSetting::set('default_columns', (int) $data['default_columns'], null, 'int');
            }
            if (isset($data['default_gap'])) {
                InstagramSetting::set('default_gap', (int) $data['default_gap'], null, 'int');
            }
            if (isset($data['max_posts_per_gallery'])) {
                InstagramSetting::set('max_posts_per_gallery', (int) $data['max_posts_per_gallery'], null, 'int');
            }

            // Cache settings
            if (isset($data['cache_duration_hours'])) {
                InstagramSetting::set('cache_duration_hours', (int) $data['cache_duration_hours'], null, 'int');
            }
            if (isset($data['auto_refresh_tokens'])) {
                InstagramSetting::set('auto_refresh_tokens', $data['auto_refresh_tokens'] ? 1 : 0, null, 'bool');
            }
            if (isset($data['token_refresh_threshold_days'])) {
                InstagramSetting::set('token_refresh_threshold_days', (int) $data['token_refresh_threshold_days'], null, 'int');
            }

            // Display options
            if (isset($data['show_captions'])) {
                InstagramSetting::set('show_captions', $data['show_captions'] ? 1 : 0, null, 'bool');
            }
            if (isset($data['caption_max_length'])) {
                InstagramSetting::set('caption_max_length', (int) $data['caption_max_length'], null, 'int');
            }
            if (isset($data['enable_lightbox'])) {
                InstagramSetting::set('enable_lightbox', $data['enable_lightbox'] ? 1 : 0, null, 'bool');
            }
            if (isset($data['enable_lazy_loading'])) {
                InstagramSetting::set('enable_lazy_loading', $data['enable_lazy_loading'] ? 1 : 0, null, 'bool');
            }
            if (isset($data['show_video_indicator'])) {
                InstagramSetting::set('show_video_indicator', $data['show_video_indicator'] ? 1 : 0, null, 'bool');
            }
            if (isset($data['show_carousel_indicator'])) {
                InstagramSetting::set('show_carousel_indicator', $data['show_carousel_indicator'] ? 1 : 0, null, 'bool');
            }

            // Layout options
            if (isset($data['hover_effect'])) {
                InstagramSetting::set('hover_effect', $data['hover_effect'], null, 'string');
            }
            if (isset($data['border_radius'])) {
                InstagramSetting::set('border_radius', (int) $data['border_radius'], null, 'int');
            }
            if (isset($data['image_aspect_ratio'])) {
                InstagramSetting::set('image_aspect_ratio', $data['image_aspect_ratio'], null, 'string');
            }

            $_SESSION['success'] = __instagram('settings.settings_saved');

        } catch (\Exception $e) {
            error_log('Instagram settings error: ' . $e->getMessage());
            $_SESSION['error'] = __instagram('settings.settings_error') . ': ' . $e->getMessage();
        }

        redirect('/musedock/instagram/settings');
    }
}
