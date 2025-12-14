<?php

namespace Modules\InstagramGallery\Controllers\Tenant;

use Modules\InstagramGallery\Models\InstagramConnection;
use Modules\InstagramGallery\Models\InstagramPost;
use Modules\InstagramGallery\Models\InstagramSetting;

class GalleryController
{
    private $pdo;
    private $tenantId;

    public function __construct()
    {
        $this->pdo = \Screenart\Musedock\Database::connect();
        InstagramConnection::setPdo($this->pdo);
        InstagramPost::setPdo($this->pdo);
        InstagramSetting::setPdo($this->pdo);

        // Get tenant ID
        $this->tenantId = class_exists('TenantManager') ? TenantManager::currentTenantId() : 1;
    }

    /**
     * Show gallery preview with shortcode
     */
    public function show($id)
    {
        $connection = InstagramConnection::find((int) $id);

        if (!$connection) {
            $_SESSION['error'] = __instagram('connection.not_found');
            redirect('/admin/instagram');
            return;
        }

        // Check if tenant can access this connection (own or global)
        if ($connection->tenant_id !== null && $connection->tenant_id !== $this->tenantId) {
            $_SESSION['error'] = __instagram('errors.permission_denied');
            redirect('/admin/instagram');
            return;
        }

        // Get posts for preview
        $posts = $connection->activePosts(12);

        // Get available layouts
        $layouts = InstagramSetting::getAvailableLayouts();

        // Get default settings
        $defaultLayout = InstagramSetting::get('default_layout', $this->tenantId, 'grid');
        $defaultColumns = InstagramSetting::get('default_columns', $this->tenantId, 3);
        $defaultGap = InstagramSetting::get('default_gap', $this->tenantId, 10);

        render('modules/instagram-gallery/views/tenant/instagram/gallery.blade.php', [
            'connection' => $connection,
            'posts' => $posts,
            'layouts' => $layouts,
            'defaultLayout' => $defaultLayout,
            'defaultColumns' => $defaultColumns,
            'defaultGap' => $defaultGap
        ]);
    }
}
