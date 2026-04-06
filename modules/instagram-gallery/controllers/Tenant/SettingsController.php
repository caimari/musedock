<?php

namespace Modules\InstagramGallery\Controllers\Tenant;

use Modules\InstagramGallery\Models\InstagramSetting;
use Screenart\Musedock\View;

class SettingsController
{
    private $pdo;
    private $tenantId;

    public function __construct()
    {
        $this->pdo = \Screenart\Musedock\Database::connect();
        InstagramSetting::setPdo($this->pdo);

        $this->tenantId = class_exists('TenantManager') ? \TenantManager::currentTenantId() : 1;
    }

    private function checkPermission(string $permission): void
    {
        if (!userCan($permission)) {
            flash('error', __instagram('errors.permission_denied'));
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }
    }

    public function index()
    {
        $this->checkPermission('instagram.settings');

        $settings = InstagramSetting::getAll($this->tenantId);
        $layouts = InstagramSetting::getAvailableLayouts();

        return View::renderModule('instagram-gallery', 'tenant.instagram.settings', [
            'settings' => $settings,
            'layouts' => $layouts,
            'tenantId' => $this->tenantId
        ]);
    }

    public function update()
    {
        $this->checkPermission('instagram.settings');

        try {
            $data = $_POST;

            // Mode
            if (isset($data['instagram_mode'])) {
                InstagramSetting::set('instagram_mode', $data['instagram_mode'], $this->tenantId, 'string');
            }

            // API Credentials
            if (isset($data['instagram_app_id'])) {
                InstagramSetting::set('instagram_app_id', $data['instagram_app_id'], $this->tenantId, 'string');
            }
            if (isset($data['instagram_app_secret'])) {
                InstagramSetting::set('instagram_app_secret', $data['instagram_app_secret'], $this->tenantId, 'string');
            }
            if (isset($data['instagram_redirect_uri'])) {
                InstagramSetting::set('instagram_redirect_uri', $data['instagram_redirect_uri'], $this->tenantId, 'string');
            }

            // Display settings
            if (isset($data['default_layout'])) {
                InstagramSetting::set('default_layout', $data['default_layout'], $this->tenantId, 'string');
            }
            if (isset($data['default_columns'])) {
                InstagramSetting::set('default_columns', (int) $data['default_columns'], $this->tenantId, 'int');
            }
            if (isset($data['default_gap'])) {
                InstagramSetting::set('default_gap', (int) $data['default_gap'], $this->tenantId, 'int');
            }
            if (isset($data['max_posts_per_gallery'])) {
                InstagramSetting::set('max_posts_per_gallery', (int) $data['max_posts_per_gallery'], $this->tenantId, 'int');
            }

            // Cache settings
            if (isset($data['cache_duration_hours'])) {
                InstagramSetting::set('cache_duration_hours', (int) $data['cache_duration_hours'], $this->tenantId, 'int');
            }
            InstagramSetting::set('auto_refresh_tokens', isset($data['auto_refresh_tokens']) ? 1 : 0, $this->tenantId, 'bool');

            if (isset($data['token_refresh_threshold_days'])) {
                InstagramSetting::set('token_refresh_threshold_days', (int) $data['token_refresh_threshold_days'], $this->tenantId, 'int');
            }

            // Display options
            InstagramSetting::set('show_captions', isset($data['show_captions']) ? 1 : 0, $this->tenantId, 'bool');

            if (isset($data['caption_max_length'])) {
                InstagramSetting::set('caption_max_length', (int) $data['caption_max_length'], $this->tenantId, 'int');
            }

            InstagramSetting::set('enable_lightbox', isset($data['enable_lightbox']) ? 1 : 0, $this->tenantId, 'bool');
            InstagramSetting::set('enable_lazy_loading', isset($data['enable_lazy_loading']) ? 1 : 0, $this->tenantId, 'bool');

            // Layout options
            if (isset($data['hover_effect'])) {
                InstagramSetting::set('hover_effect', $data['hover_effect'], $this->tenantId, 'string');
            }
            if (isset($data['border_radius'])) {
                InstagramSetting::set('border_radius', (int) $data['border_radius'], $this->tenantId, 'int');
            }
            if (isset($data['image_aspect_ratio'])) {
                InstagramSetting::set('image_aspect_ratio', $data['image_aspect_ratio'], $this->tenantId, 'string');
            }

            $_SESSION['success'] = __instagram('settings.settings_saved');

        } catch (\Exception $e) {
            error_log('Instagram settings error: ' . $e->getMessage());
            $_SESSION['error'] = __instagram('settings.settings_error') . ': ' . $e->getMessage();
        }

        redirect('/' . admin_path() . '/instagram/settings');
    }
}
