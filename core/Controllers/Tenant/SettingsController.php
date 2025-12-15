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

    private const BRANDING_DIR = 'branding';
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
                'contact_whatsapp',

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
            $this->deleteBrandingFile($currentLogo);

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
            $this->deleteBrandingFile($currentFavicon);

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

        $url = $this->storeBrandingUpload($_FILES['site_logo'], "tenant-{$tenantId}", 'logo');
        if (!$url) {
            throw new \Exception('Error al subir el logo');
        }

        $this->deleteBrandingFile(tenant_setting('site_logo'));
        $this->saveTenantSetting($pdo, $tenantId, 'site_logo', $url, $driver);
    }

    /**
     * Procesa el upload del favicon
     */
    private function handleFaviconUpload(\PDO $pdo, int $tenantId, string $driver): void
    {
        if (!isset($_FILES['site_favicon']) || $_FILES['site_favicon']['error'] !== UPLOAD_ERR_OK) {
            return;
        }

        $url = $this->storeBrandingUpload($_FILES['site_favicon'], "tenant-{$tenantId}", 'favicon');
        if (!$url) {
            throw new \Exception('Error al subir el favicon');
        }

        $this->deleteBrandingFile(tenant_setting('site_favicon'));
        $this->saveTenantSetting($pdo, $tenantId, 'site_favicon', $url, $driver);
    }

    private function storeBrandingUpload(array $file, string $scope, string $kind): ?string
    {
        $allowedTypes = $kind === 'favicon'
            ? ['image/x-icon', 'image/png', 'image/svg+xml', 'image/vnd.microsoft.icon']
            : ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return null;
        }

        if (!in_array(($file['type'] ?? ''), $allowedTypes, true)) {
            return null;
        }

        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = $kind === 'favicon' ? 'ico' : 'png';
        }

        $safeExt = preg_replace('/[^a-z0-9]+/', '', $ext);
        if ($safeExt === '') {
            $safeExt = $kind === 'favicon' ? 'ico' : 'png';
        }

        $filename = "{$kind}-" . time() . '-' . bin2hex(random_bytes(4)) . ".{$safeExt}";
        $relative = self::BRANDING_DIR . '/' . $scope . '/' . $filename;

        $destDir = APP_ROOT . '/storage/app/media/' . self::BRANDING_DIR . '/' . $scope;
        if (!is_dir($destDir)) {
            mkdir($destDir, 0775, true);
        }

        $destPath = APP_ROOT . '/storage/app/media/' . $relative;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return null;
        }

        return '/media/file/' . $relative;
    }

    private function deleteBrandingFile(?string $storedPath): void
    {
        $storedPath = trim((string)$storedPath);
        if ($storedPath === '' || $storedPath === '0') {
            return;
        }

        if (str_starts_with($storedPath, '/media/file/')) {
            $relative = ltrim(substr($storedPath, strlen('/media/file/')), '/');
            $fullPath = APP_ROOT . '/storage/app/media/' . $relative;

            $real = realpath($fullPath);
            $root = realpath(APP_ROOT . '/storage/app/media');
            if ($real && $root && str_starts_with($real, $root) && is_file($real)) {
                @unlink($real);
            }
            return;
        }

        if (str_starts_with($storedPath, 'uploads/') || str_starts_with($storedPath, '/uploads/')) {
            $legacyPath = public_path(ltrim($storedPath, '/'));
            if (is_file($legacyPath)) {
                @unlink($legacyPath);
            }
            return;
        }

        if (str_starts_with($storedPath, 'assets/') || str_starts_with($storedPath, '/assets/')) {
            $legacyPath = public_path(ltrim($storedPath, '/'));
            if (is_file($legacyPath)) {
                @unlink($legacyPath);
            }
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

            // Sincronizar is_homepage en pages
            $stmt = $pdo->prepare("UPDATE pages SET is_homepage = 0 WHERE tenant_id = ?");
            $stmt->execute([$tenantId]);

            if ($readingSettings['show_on_front'] === 'page' && !empty($readingSettings['page_on_front'])) {
                $stmt = $pdo->prepare("UPDATE pages SET is_homepage = 1 WHERE id = ? AND tenant_id = ?");
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
                FROM pages
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

    /**
     * Muestra la página de ajustes SEO y Social
     */
    public function seo()
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

        return View::renderTenantAdmin('settings/seo', [
            'title' => 'Ajustes SEO y Social',
            'settings' => $settings,
        ]);
    }

    /**
     * Muestra la página de ajustes de cookies (por tenant)
     */
    public function cookies()
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

        // Páginas publicadas del tenant para links legales
        $availablePages = [];
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
              SELECT p.id, p.title, s.slug, s.prefix
              FROM pages p
              LEFT JOIN slugs s ON s.reference_id = p.id AND s.module = 'pages' AND s.tenant_id = p.tenant_id
              WHERE p.status = 'published' AND p.tenant_id = ?
              ORDER BY p.title ASC
            ");
            $stmt->execute([$tenantId]);
            $availablePages = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("Error getting tenant pages for cookies settings: " . $e->getMessage());
        }

        return View::renderTenantAdmin('settings/cookies', [
            'title' => 'Configuración de Cookies',
            'settings' => $settings,
            'availablePages' => $availablePages,
        ]);
    }

    /**
     * Guarda los ajustes de cookies (por tenant)
     */
    public function updateCookies()
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

            $keys = [
                'cookies_enabled', 'cookies_text', 'cookies_accept_basic',
                'cookies_accept_all', 'cookies_more_info', 'cookies_policy_url',
                'cookies_terms_text', 'cookies_terms_url'
            ];

            foreach ($keys as $key) {
                $value = $_POST[$key] ?? '';

                if ($key === 'cookies_enabled') {
                    $value = isset($_POST['cookies_enabled']) ? '1' : '0';
                }

                $this->saveTenantSetting($pdo, $tenantId, $key, $value, $driver);
            }

            $pdo->commit();
            clear_tenant_settings_cache();

            $_SESSION['success'] = 'Configuración de cookies guardada correctamente.';
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error updating tenant cookies settings: " . $e->getMessage());
            $_SESSION['error'] = 'Error al guardar la configuración de cookies: ' . $e->getMessage();
        }

        header('Location: /' . admin_path() . '/settings/cookies');
        exit;
    }

    /**
     * Guarda los ajustes SEO y Social
     */
    public function updateSeo()
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

            // Settings de SEO y Social
            $seoSettings = [
                'site_keywords' => $_POST['site_keywords'] ?? '',
                'site_author' => $_POST['site_author'] ?? '',
                'twitter_site' => $_POST['twitter_site'] ?? '',
                'social_facebook' => $_POST['social_facebook'] ?? '',
                'social_twitter' => $_POST['social_twitter'] ?? '',
                'social_instagram' => $_POST['social_instagram'] ?? '',
                'social_linkedin' => $_POST['social_linkedin'] ?? '',
                'social_youtube' => $_POST['social_youtube'] ?? '',
                'social_pinterest' => $_POST['social_pinterest'] ?? '',
            ];

            foreach ($seoSettings as $key => $value) {
                $this->saveTenantSetting($pdo, $tenantId, $key, $value, $driver);
            }

            // Procesar upload de imagen Open Graph
            $this->handleOgImageUpload($pdo, $tenantId, $driver);

            $pdo->commit();
            clear_tenant_settings_cache();

            $_SESSION['success'] = 'Ajustes SEO y Social guardados correctamente';
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error updating tenant SEO settings: " . $e->getMessage());
            $_SESSION['error'] = 'Error al guardar los ajustes SEO: ' . $e->getMessage();
        }

        header('Location: /' . admin_path() . '/settings/seo');
        exit;
    }

    /**
     * Procesa el upload de la imagen Open Graph
     */
    private function handleOgImageUpload(\PDO $pdo, int $tenantId, string $driver): void
    {
        if (!isset($_FILES['og_image']) || $_FILES['og_image']['error'] !== UPLOAD_ERR_OK) {
            return;
        }

        $file = $_FILES['og_image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!in_array($file['type'], $allowedTypes)) {
            throw new \Exception('Tipo de archivo no permitido para la imagen OG');
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'tenant_' . $tenantId . '_og_' . time() . '.' . $ext;
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/public/uploads/tenants/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $destination = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // Eliminar imagen OG anterior si existe
            $currentOg = tenant_setting('og_image');
            if ($currentOg && file_exists($_SERVER['DOCUMENT_ROOT'] . '/public/' . $currentOg)) {
                unlink($_SERVER['DOCUMENT_ROOT'] . '/public/' . $currentOg);
            }

            $this->saveTenantSetting($pdo, $tenantId, 'og_image', 'uploads/tenants/' . $filename, $driver);
        } else {
            throw new \Exception('Error al subir la imagen Open Graph');
        }
    }
}
