<?php
namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\Database;
use Screenart\Musedock\View;
use Screenart\Musedock\Models\Language;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Cache\HtmlCache;
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
                'site_subtitle',
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
                'show_subtitle',

                // Idioma
                'default_lang',
                'force_lang',
                'show_language_switcher',

                // Datos legales
                'legal_jurisdiction',
                'legal_entity_type',
                'legal_name',
                'legal_nif',
                'legal_email',
                'legal_address',
                'legal_registry_data',
                'legal_supervisory_authority',
                'site_has_economic_activity',
                'legal_targets_eu',
                'site_uses_analytics_cookies',
                'site_has_user_registration',
                'site_has_paid_services',

                // Footer
                'footer_copyright',

                // Custom Code (head/body)
                'custom_head_code',
                'custom_body_start_code',
                'custom_body_end_code',
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
                if (in_array($key, ['show_logo', 'show_title', 'show_subtitle', 'show_language_switcher', 'site_has_economic_activity', 'legal_targets_eu', 'site_uses_analytics_cookies', 'site_has_user_registration', 'site_has_paid_services'])) {
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

            // Blog URL prefix
            $blogUrlMode = $_POST['blog_url_mode'] ?? 'prefix';
            if ($blogUrlMode === 'none') {
                $blogUrlPrefix = '';
            } else {
                $blogUrlPrefix = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_POST['blog_url_prefix'] ?? 'blog')));
                if ($blogUrlPrefix === '') {
                    $blogUrlPrefix = 'blog';
                }
            }

            // Page URL prefix
            $pageUrlMode = $_POST['page_url_mode'] ?? 'prefix';
            if ($pageUrlMode === 'none') {
                $pageUrlPrefix = '';
            } else {
                $pageUrlPrefix = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_POST['page_url_prefix'] ?? 'p')));
                if ($pageUrlPrefix === '') {
                    $pageUrlPrefix = 'p';
                }
            }

            // Settings de lectura
            $blogCommentsCaptchaThreshold = (int)($_POST['blog_comments_captcha_spam_threshold'] ?? 5);
            if ($blogCommentsCaptchaThreshold < 1) {
                $blogCommentsCaptchaThreshold = 1;
            }
            if ($blogCommentsCaptchaThreshold > 200) {
                $blogCommentsCaptchaThreshold = 200;
            }

            $blogCommentsApprovalMode = (string)($_POST['blog_comments_approval_mode'] ?? 'trusted_authors');
            $validApprovalModes = ['manual', 'trusted_authors', 'auto_approve'];
            if (!in_array($blogCommentsApprovalMode, $validApprovalModes, true)) {
                $blogCommentsApprovalMode = 'trusted_authors';
            }

            $blogCommentsSpamLinksThreshold = (int)($_POST['blog_comments_spam_links_threshold'] ?? 3);
            if ($blogCommentsSpamLinksThreshold < 1) {
                $blogCommentsSpamLinksThreshold = 1;
            }
            if ($blogCommentsSpamLinksThreshold > 20) {
                $blogCommentsSpamLinksThreshold = 20;
            }

            $readingSettings = [
                'show_on_front' => $_POST['show_on_front'] ?? 'posts',
                'page_on_front' => $_POST['page_on_front'] ?? '',
                'post_on_front' => $_POST['post_on_front'] ?? '',
                'posts_per_page' => $_POST['posts_per_page'] ?? '10',
                'posts_per_rss' => $_POST['posts_per_rss'] ?? '10',
                'blog_public' => isset($_POST['blog_public']) ? '0' : '1',
                'blog_show_views' => isset($_POST['blog_show_views']) ? '1' : '0',
                'blog_comments_approval_mode' => $blogCommentsApprovalMode,
                'blog_comments_spam_links_threshold' => (string)$blogCommentsSpamLinksThreshold,
                'blog_comments_captcha_enabled' => isset($_POST['blog_comments_captcha_enabled']) ? '1' : '0',
                'blog_comments_captcha_spam_threshold' => (string)$blogCommentsCaptchaThreshold,
                'blog_url_prefix' => $blogUrlPrefix,
                'page_url_prefix' => $pageUrlPrefix,
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

            // Actualizar el prefix de todos los slugs de blog existentes
            $newPrefix = $blogUrlPrefix ?: null;
            $stmt = $pdo->prepare("
                UPDATE slugs SET prefix = ?
                WHERE tenant_id = ? AND module = 'blog'
            ");
            $stmt->execute([$newPrefix, $tenantId]);

            // Verificar conflictos cross-module si ambos prefijos son vacíos
            $newPagePrefix = $pageUrlPrefix ?: null;
            $newBlogPrefix = $blogUrlPrefix ?: null;
            if ($newPagePrefix === null && $newBlogPrefix === null) {
                $stmt = $pdo->prepare("
                    SELECT s1.slug FROM slugs s1
                    INNER JOIN slugs s2 ON s1.slug = s2.slug AND s1.tenant_id = s2.tenant_id
                    WHERE s1.module = 'pages' AND s2.module = 'blog'
                    AND s1.tenant_id = ?
                    LIMIT 5
                ");
                $stmt->execute([$tenantId]);
                $conflicts = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                if (!empty($conflicts)) {
                    $pdo->rollBack();
                    $_SESSION['error'] = 'No se pueden quitar ambos prefijos: hay slugs duplicados entre páginas y posts (' . implode(', ', $conflicts) . '). Cambia los slugs duplicados o mantén al menos un prefijo.';
                    header('Location: /' . admin_path() . '/settings/reading');
                    exit;
                }
            }

            // Actualizar el prefix de todos los slugs de páginas existentes
            $stmt = $pdo->prepare("
                UPDATE slugs SET prefix = ?
                WHERE tenant_id = ? AND module = 'pages'
            ");
            $stmt->execute([$newPagePrefix, $tenantId]);

            // Sincronizar links de menú: actualizar site_menu_items que apuntan a páginas/posts
            // para que reflejen los nuevos prefijos automáticamente
            if ($driver === 'pgsql') {
                $stmt = $pdo->prepare("
                    UPDATE site_menu_items
                    SET link = CASE
                        WHEN s.prefix IS NOT NULL AND s.prefix != '' THEN '/' || s.prefix || '/' || s.slug
                        ELSE '/' || s.slug
                    END,
                    updated_at = NOW()
                    FROM slugs s
                    WHERE s.reference_id = site_menu_items.page_id
                      AND s.tenant_id = site_menu_items.tenant_id
                      AND s.module IN ('pages', 'blog')
                      AND site_menu_items.tenant_id = ?
                      AND site_menu_items.page_id IS NOT NULL
                ");
            } else {
                $stmt = $pdo->prepare("
                    UPDATE site_menu_items mi
                    INNER JOIN slugs s ON s.reference_id = mi.page_id AND s.tenant_id = mi.tenant_id
                    SET mi.link = CASE
                        WHEN s.prefix IS NOT NULL AND s.prefix != '' THEN CONCAT('/', s.prefix, '/', s.slug)
                        ELSE CONCAT('/', s.slug)
                    END,
                    mi.updated_at = NOW()
                    WHERE mi.tenant_id = ?
                      AND mi.page_id IS NOT NULL
                      AND s.module IN ('pages', 'blog')
                ");
            }
            $stmt->execute([$tenantId]);

            $pdo->commit();
            clear_tenant_settings_cache();

            // HTML Cache: URLs changed — purge and re-warm
            HtmlCache::onPrefixChanged($tenantId);

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
                'cookies_terms_text', 'cookies_terms_url',
                'cookies_show_icon', 'cookies_banner_layout',
                'cookies_bg_color', 'cookies_text_color',
                'cookies_btn_accept_bg', 'cookies_btn_reject_bg'
            ];

            foreach ($keys as $key) {
                $value = $_POST[$key] ?? '';

                if ($key === 'cookies_enabled' || $key === 'cookies_show_icon') {
                    $value = isset($_POST[$key]) ? '1' : '0';
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
                'social_vimeo' => $_POST['social_vimeo'] ?? '',
                'social_tiktok' => $_POST['social_tiktok'] ?? '',
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

    /**
     * Muestra la página de ajustes de Email SMTP del tenant
     */
    public function email()
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

        return View::renderTenantAdmin('settings/email', [
            'title' => 'Configuración de Email',
            'settings' => $settings,
        ]);
    }

    /**
     * Guarda los ajustes de Email SMTP del tenant
     */
    public function updateEmail()
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

            $emailSettings = [
                'smtp_host'         => trim($_POST['smtp_host'] ?? ''),
                'smtp_port'         => (int)($_POST['smtp_port'] ?? 587),
                'smtp_username'     => trim($_POST['smtp_username'] ?? ''),
                'smtp_encryption'   => in_array($_POST['smtp_encryption'] ?? 'tls', ['tls', 'ssl', '']) ? ($_POST['smtp_encryption'] ?? 'tls') : 'tls',
                'mail_from_address' => trim($_POST['mail_from_address'] ?? ''),
                'mail_from_name'    => trim($_POST['mail_from_name'] ?? ''),
            ];

            // Solo actualizar contraseña si se proporcionó una nueva
            if (!empty($_POST['smtp_password'])) {
                $emailSettings['smtp_password'] = $_POST['smtp_password'];
            }

            foreach ($emailSettings as $key => $value) {
                $this->saveTenantSetting($pdo, $tenantId, $key, (string)$value, $driver);
            }

            $pdo->commit();
            clear_tenant_settings_cache();

            $_SESSION['success'] = 'Configuración de email guardada correctamente';
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error updating tenant email settings: " . $e->getMessage());
            $_SESSION['error'] = 'Error al guardar la configuración de email';
        }

        header('Location: /' . admin_path() . '/settings/email');
        exit;
    }

    /**
     * Muestra la página de ajustes de Storage del tenant
     */
    public function storage()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.view');

        $tenantId = tenant_id();
        if (!$tenantId) {
            $_SESSION['error'] = 'No se ha detectado el tenant actual';
            header('Location: /' . admin_path());
            exit;
        }

        // Cuota y uso de storage del tenant
        $storageQuota = null;
        $storageUsedBytes = null;
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT storage_quota_mb, storage_used_bytes FROM tenants WHERE id = ? LIMIT 1");
            $stmt->execute([$tenantId]);
            $row = $stmt->fetch(\PDO::FETCH_OBJ);
            $storageQuota = $row ? $row->storage_quota_mb : null;
            $storageUsedBytes = $row ? $row->storage_used_bytes : null;
        } catch (\Exception $e) {
            // silencioso
        }

        return View::renderTenantAdmin('settings/storage', [
            'title' => 'Almacenamiento',
            'storageQuota' => $storageQuota,
            'storageUsedBytes' => $storageUsedBytes,
        ]);
    }

    /**
     * Guarda los ajustes de Storage del tenant
     */
    public function updateStorage()
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

            $disk = in_array($_POST['filesystem_disk'] ?? 'local', ['local', 'r2', 's3', 'ionos']) ? ($_POST['filesystem_disk'] ?? 'local') : 'local';
            $this->saveTenantSetting($pdo, $tenantId, 'filesystem_disk', $disk, $driver);

            // Credenciales R2
            if ($disk === 'r2') {
                $r2Settings = [
                    'r2_access_key_id' => trim($_POST['r2_access_key_id'] ?? ''),
                    'r2_bucket'        => trim($_POST['r2_bucket'] ?? ''),
                    'r2_endpoint'      => trim($_POST['r2_endpoint'] ?? ''),
                    'r2_url'           => trim($_POST['r2_url'] ?? ''),
                ];
                if (!empty($_POST['r2_secret_access_key'])) {
                    $r2Settings['r2_secret_access_key'] = $_POST['r2_secret_access_key'];
                }
                foreach ($r2Settings as $k => $v) {
                    $this->saveTenantSetting($pdo, $tenantId, $k, $v, $driver);
                }
            }

            // Credenciales S3
            if ($disk === 's3') {
                $s3Settings = [
                    'aws_access_key_id'    => trim($_POST['aws_access_key_id'] ?? ''),
                    'aws_default_region'   => trim($_POST['aws_default_region'] ?? 'eu-west-1'),
                    'aws_bucket'           => trim($_POST['aws_bucket'] ?? ''),
                    'aws_url'              => trim($_POST['aws_url'] ?? ''),
                ];
                if (!empty($_POST['aws_secret_access_key'])) {
                    $s3Settings['aws_secret_access_key'] = $_POST['aws_secret_access_key'];
                }
                foreach ($s3Settings as $k => $v) {
                    $this->saveTenantSetting($pdo, $tenantId, $k, $v, $driver);
                }
            }

            // Credenciales IONOS
            if ($disk === 'ionos') {
                $ionosSettings = [
                    'ionos_access_key_id' => trim($_POST['ionos_access_key_id'] ?? ''),
                    'ionos_bucket'        => trim($_POST['ionos_bucket'] ?? ''),
                    'ionos_region'        => trim($_POST['ionos_region'] ?? 'de'),
                    'ionos_endpoint'      => trim($_POST['ionos_endpoint'] ?? ''),
                    'ionos_url'           => trim($_POST['ionos_url'] ?? ''),
                ];
                if (!empty($_POST['ionos_secret_access_key'])) {
                    $ionosSettings['ionos_secret_access_key'] = $_POST['ionos_secret_access_key'];
                }
                foreach ($ionosSettings as $k => $v) {
                    $this->saveTenantSetting($pdo, $tenantId, $k, $v, $driver);
                }
            }

            $pdo->commit();
            clear_tenant_settings_cache();

            $_SESSION['success'] = 'Configuración de storage guardada correctamente';
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error updating tenant storage settings: " . $e->getMessage());
            $_SESSION['error'] = 'Error al guardar la configuración de storage';
        }

        header('Location: /' . admin_path() . '/settings/storage');
        exit;
    }

    /**
     * Muestra la página de ajustes de seguridad CSP
     */
    public function security()
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

        return View::renderTenantAdmin('settings/security', [
            'title' => __('security_settings_title'),
            'settings' => $settings,
        ]);
    }

    /**
     * Guarda los ajustes de seguridad CSP
     */
    public function updateSecurity()
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

            $cspKeys = [
                'csp_connect_src',
                'csp_script_src',
                'csp_frame_src',
                'csp_img_src',
            ];

            foreach ($cspKeys as $key) {
                $raw = trim($_POST[$key] ?? '');
                // Validar y limpiar cada línea
                $cleaned = $this->sanitizeCspDomains($raw);
                $this->saveTenantSetting($pdo, $tenantId, $key, $cleaned, $driver);
            }

            $pdo->commit();
            clear_tenant_settings_cache();

            $_SESSION['success'] = __('security_settings_saved');
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error updating tenant security/CSP settings: " . $e->getMessage());
            $_SESSION['error'] = __('security_settings_error') . ': ' . $e->getMessage();
        }

        header('Location: /' . admin_path() . '/settings/security');
        exit;
    }

    /**
     * Sanitiza y valida dominios CSP introducidos por el usuario.
     * Acepta uno por línea. Formatos válidos:
     *   https://dominio.com
     *   wss://dominio.com
     *   https://*.subdominio.com
     * Devuelve string limpio con dominios válidos separados por salto de línea.
     */
    private function sanitizeCspDomains(string $raw): string
    {
        if (empty($raw)) {
            return '';
        }

        $lines = preg_split('/[\r\n]+/', $raw);
        $valid = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Permitir esquemas: https://, wss://, ws:// (dev)
            // Formato: scheme://[*.] domain.tld [:port] [/path]
            if (preg_match('#^(https?|wss?)://(\*\.)?[a-zA-Z0-9\-]+(\.[a-zA-Z0-9\-]+)*(:\d{1,5})?(/[^\s]*)?$#', $line)) {
                $valid[] = $line;
            }
        }

        return implode("\n", array_unique($valid));
    }
}
