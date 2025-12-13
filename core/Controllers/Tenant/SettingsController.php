<?php
namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\Database;
use Screenart\Musedock\View;
use Screenart\Musedock\Models\Language;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Traits\RequiresPermission;

class SettingsController
{
    use RequiresPermission;
    /**
     * Muestra la página de ajustes del tenant
     */
    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.view');

        $tenantId = tenant_id();
        if (!$tenantId) {
            $_SESSION['error'] = 'No se ha detectado el tenant actual';
            header('Location: /' . admin_path());
            exit;
        }

        $settings = $this->getTenantSettings($tenantId);
        $activeLanguages = $this->getActiveLanguages($tenantId);

        return View::renderTenantAdmin('settings/index', [
            'title' => 'Ajustes del Sitio',
            'settings' => $settings,
            'activeLanguages' => $activeLanguages,
        ]);
    }

    /**
     * Guarda los ajustes del tenant
     */
    public function update()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        $tenantId = tenant_id();
        if (!$tenantId) {
            $_SESSION['error'] = 'No se ha detectado el tenant actual';
            header('Location: /' . admin_path());
            exit;
        }

        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();

            // Campos de ajustes a guardar
            $settingsKeys = [
                // Información del sitio
                'site_name',
                'site_description',
                'admin_email',

                // Contacto
                'contact_phone',
                'contact_email',
                'contact_address',

                // Redes sociales
                'social_facebook',
                'social_twitter',
                'social_instagram',
                'social_linkedin',
                'social_youtube',
                'social_tiktok',

                // Visualización
                'show_logo',
                'show_title',

                // Footer
                'footer_copyright',
            ];

            // Procesar campos traducibles (footer_short_description)
            $activeLanguages = $this->getActiveLanguages($tenantId);
            foreach ($activeLanguages as $lang) {
                $settingsKeys[] = 'footer_short_description_' . $lang->code;
            }

            // Guardar cada setting
            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            foreach ($settingsKeys as $key) {
                $value = $_POST[$key] ?? null;

                // Manejar checkboxes
                if (in_array($key, ['show_logo', 'show_title'])) {
                    $value = isset($_POST[$key]) ? '1' : '0';
                }

                if ($value !== null) {
                    $this->saveTenantSetting($pdo, $tenantId, $key, $value, $driver);
                }
            }

            // Procesar uploads de logo y favicon
            $this->handleLogoUpload($pdo, $tenantId, $driver);
            $this->handleFaviconUpload($pdo, $tenantId, $driver);

            $pdo->commit();

            // Limpiar caché
            clear_tenant_settings_cache();

            $_SESSION['success'] = 'Ajustes guardados correctamente';
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error guardando tenant settings: " . $e->getMessage());
            $_SESSION['error'] = 'Error al guardar los ajustes: ' . $e->getMessage();
        }

        header('Location: /' . admin_path() . '/settings');
        exit;
    }

    /**
     * Elimina el logo del tenant
     */
    public function deleteLogo()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        $tenantId = tenant_id();
        if (!$tenantId) {
            $_SESSION['error'] = 'No se ha detectado el tenant actual';
            header('Location: /' . admin_path());
            exit;
        }

        try {
            $currentLogo = tenant_setting('site_logo');
            if ($currentLogo) {
                $logoPath = $_SERVER['DOCUMENT_ROOT'] . '/public/' . $currentLogo;
                if (file_exists($logoPath)) {
                    unlink($logoPath);
                }
            }

            $pdo = Database::connect();
            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            $this->saveTenantSetting($pdo, $tenantId, 'site_logo', '', $driver);

            clear_tenant_settings_cache();
            $_SESSION['success'] = 'Logo eliminado correctamente';
        } catch (\Exception $e) {
            error_log("Error eliminando logo del tenant: " . $e->getMessage());
            $_SESSION['error'] = 'Error al eliminar el logo';
        }

        header('Location: /' . admin_path() . '/settings');
        exit;
    }

    /**
     * Elimina el favicon del tenant
     */
    public function deleteFavicon()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        $tenantId = tenant_id();
        if (!$tenantId) {
            $_SESSION['error'] = 'No se ha detectado el tenant actual';
            header('Location: /' . admin_path());
            exit;
        }

        try {
            $currentFavicon = tenant_setting('site_favicon');
            if ($currentFavicon) {
                $faviconPath = $_SERVER['DOCUMENT_ROOT'] . '/public/' . $currentFavicon;
                if (file_exists($faviconPath)) {
                    unlink($faviconPath);
                }
            }

            $pdo = Database::connect();
            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            $this->saveTenantSetting($pdo, $tenantId, 'site_favicon', '', $driver);

            clear_tenant_settings_cache();
            $_SESSION['success'] = 'Favicon eliminado correctamente';
        } catch (\Exception $e) {
            error_log("Error eliminando favicon del tenant: " . $e->getMessage());
            $_SESSION['error'] = 'Error al eliminar el favicon';
        }

        header('Location: /' . admin_path() . '/settings');
        exit;
    }

    /**
     * Obtiene todos los settings del tenant
     */
    private function getTenantSettings(int $tenantId): array
    {
        try {
            $pdo = Database::connect();
            $keyCol = Database::qi('key');
            $stmt = $pdo->prepare("SELECT {$keyCol}, value FROM tenant_settings WHERE tenant_id = ?");
            $stmt->execute([$tenantId]);
            return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        } catch (\Exception $e) {
            error_log("Error obteniendo tenant settings: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene los idiomas activos del tenant
     */
    private function getActiveLanguages(int $tenantId): array
    {
        try {
            return Language::getActiveLanguages($tenantId);
        } catch (\Exception $e) {
            error_log("Error obteniendo idiomas activos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Guarda un setting del tenant
     */
    private function saveTenantSetting(\PDO $pdo, int $tenantId, string $key, $value, string $driver): bool
    {
        $keyCol = Database::qi('key');

        if ($driver === 'mysql') {
            $stmt = $pdo->prepare("
                INSERT INTO tenant_settings (tenant_id, {$keyCol}, value)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE value = VALUES(value)
            ");
        } else {
            // PostgreSQL
            $stmt = $pdo->prepare("
                INSERT INTO tenant_settings (tenant_id, {$keyCol}, value)
                VALUES (?, ?, ?)
                ON CONFLICT (tenant_id, {$keyCol}) DO UPDATE SET value = EXCLUDED.value
            ");
        }

        return $stmt->execute([$tenantId, $key, $value]);
    }

    /**
     * Procesa el upload del logo
     */
    private function handleLogoUpload(\PDO $pdo, int $tenantId, string $driver): void
    {
        if (!isset($_FILES['site_logo']) || $_FILES['site_logo']['error'] !== UPLOAD_ERR_OK) {
            return;
        }

        $file = $_FILES['site_logo'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];

        if (!in_array($file['type'], $allowedTypes)) {
            throw new \Exception('Tipo de archivo no permitido para el logo');
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'tenant_' . $tenantId . '_logo_' . time() . '.' . $ext;
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/public/uploads/tenants/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $destination = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // Eliminar logo anterior si existe
            $currentLogo = tenant_setting('site_logo');
            if ($currentLogo && file_exists($_SERVER['DOCUMENT_ROOT'] . '/public/' . $currentLogo)) {
                unlink($_SERVER['DOCUMENT_ROOT'] . '/public/' . $currentLogo);
            }

            $this->saveTenantSetting($pdo, $tenantId, 'site_logo', 'uploads/tenants/' . $filename, $driver);
        } else {
            throw new \Exception('Error al subir el logo');
        }
    }

    /**
     * Procesa el upload del favicon
     */
    private function handleFaviconUpload(\PDO $pdo, int $tenantId, string $driver): void
    {
        if (!isset($_FILES['site_favicon']) || $_FILES['site_favicon']['error'] !== UPLOAD_ERR_OK) {
            return;
        }

        $file = $_FILES['site_favicon'];
        $allowedTypes = ['image/x-icon', 'image/png', 'image/vnd.microsoft.icon'];

        if (!in_array($file['type'], $allowedTypes)) {
            throw new \Exception('Tipo de archivo no permitido para el favicon');
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'tenant_' . $tenantId . '_favicon_' . time() . '.' . $ext;
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/public/uploads/tenants/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $destination = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // Eliminar favicon anterior si existe
            $currentFavicon = tenant_setting('site_favicon');
            if ($currentFavicon && file_exists($_SERVER['DOCUMENT_ROOT'] . '/public/' . $currentFavicon)) {
                unlink($_SERVER['DOCUMENT_ROOT'] . '/public/' . $currentFavicon);
            }

            $this->saveTenantSetting($pdo, $tenantId, 'site_favicon', 'uploads/tenants/' . $filename, $driver);
        } else {
            throw new \Exception('Error al subir el favicon');
        }
    }

    /**
     * Muestra la página de ajustes de lectura
     */
    public function reading()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.view');

        $tenantId = tenant_id();
        if (!$tenantId) {
            $_SESSION['error'] = 'No se ha detectado el tenant actual';
            header('Location: /' . admin_path());
            exit;
        }

        $settings = $this->getTenantSettings($tenantId);
        $pages = $this->getTenantPages($tenantId);
        $blogPosts = $this->getTenantBlogPosts($tenantId);

        return View::renderTenantAdmin('settings/reading', [
            'title' => 'Ajustes de Lectura',
            'settings' => $settings,
            'pages' => $pages,
            'blog_posts' => $blogPosts,
        ]);
    }

    /**
     * Guarda los ajustes de lectura
     */
    public function updateReading()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        $tenantId = tenant_id();
        if (!$tenantId) {
            $_SESSION['error'] = 'No se ha detectado el tenant actual';
            header('Location: /' . admin_path());
            exit;
        }

        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();
            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

            // Settings de lectura
            $readingSettings = [
                'show_on_front' => $_POST['show_on_front'] ?? 'posts',
                'page_on_front' => $_POST['page_on_front'] ?? '',
                'post_on_front' => $_POST['post_on_front'] ?? '',
                'posts_per_page' => $_POST['posts_per_page'] ?? '10',
                'posts_per_rss' => $_POST['posts_per_rss'] ?? '10',
                'blog_public' => isset($_POST['blog_public']) ? '0' : '1',
            ];

            foreach ($readingSettings as $key => $value) {
                $this->saveTenantSetting($pdo, $tenantId, $key, $value, $driver);
            }

            // Sincronizar is_homepage en tenant_pages
            $stmt = $pdo->prepare("UPDATE tenant_pages SET is_homepage = 0 WHERE tenant_id = ?");
            $stmt->execute([$tenantId]);

            if ($readingSettings['show_on_front'] === 'page' && !empty($readingSettings['page_on_front'])) {
                $stmt = $pdo->prepare("UPDATE tenant_pages SET is_homepage = 1 WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$readingSettings['page_on_front'], $tenantId]);
            }

            $pdo->commit();
            clear_tenant_settings_cache();

            $_SESSION['success'] = 'Ajustes de lectura guardados correctamente';
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error updating tenant reading settings: " . $e->getMessage());
            $_SESSION['error'] = 'Error al guardar los ajustes de lectura';
        }

        header('Location: /' . admin_path() . '/settings/reading');
        exit;
    }

    /**
     * Obtiene las páginas del tenant
     */
    private function getTenantPages(int $tenantId): array
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                SELECT id, title, slug
                FROM tenant_pages
                WHERE tenant_id = ? AND status = 'published'
                ORDER BY title ASC
            ");
            $stmt->execute([$tenantId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("Error getting tenant pages: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene los posts del blog del tenant
     */
    private function getTenantBlogPosts(int $tenantId): array
    {
        try {
            $pdo = Database::connect();
            // Verificar si existe la tabla tenant_blog_posts
            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') {
                $checkStmt = $pdo->query("SHOW TABLES LIKE 'tenant_blog_posts'");
            } else {
                $checkStmt = $pdo->query("SELECT to_regclass('public.tenant_blog_posts')");
            }

            $tableExists = $checkStmt->fetch();
            if (!$tableExists || (is_array($tableExists) && empty($tableExists[0]))) {
                return [];
            }

            $stmt = $pdo->prepare("
                SELECT id, title, slug
                FROM tenant_blog_posts
                WHERE tenant_id = ? AND status = 'published'
                ORDER BY title ASC
            ");
            $stmt->execute([$tenantId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("Error getting tenant blog posts: " . $e->getMessage());
            return [];
        }
    }
}
