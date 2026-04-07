<?php
namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\Database;
use Screenart\Musedock\View;
use Screenart\Musedock\Models\Language;
use Screenart\Musedock\Helpers\SiteHelper;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Traits\RequiresPermission;

class SettingsController
{
    use RequiresPermission;

    public function general()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.view');

        $settings = $this->getSettings();
        $timezones = $this->getTimezones();
        $dateFormats = $this->getDateFormats();
        $timeFormats = $this->getTimeFormats();
        
        return View::renderSuperadmin('settings.general', [
            'title' => 'Ajustes generales',
            'settings' => $settings,
            'timezones' => $timezones,
            'dateFormats' => $dateFormats,
            'timeFormats' => $timeFormats
        ]);
    }
    
    public function seo()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.view');

        $settings = $this->getSettings();

        return View::renderSuperadmin('settings.seo', [
            'title' => 'Ajustes SEO y Social',
            'settings' => $settings,
        ]);
    }

    public function cookies()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.view');

        $settings = $this->getSettings();

        return View::renderSuperadmin('settings.cookies', [
            'title' => 'Configuración de Cookies',
            'settings' => $settings,
        ]);
    }

    public function reading()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.view');

        $settings = $this->getSettings();
        $pages = $this->getAllPages();
        $blog_posts = $this->getAllBlogPosts();

        return View::renderSuperadmin('settings.reading', [
            'title' => 'Ajustes de lectura',
            'settings' => $settings,
            'pages' => $pages,
            'blog_posts' => $blog_posts,
        ]);
    }

    public function updateReading()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();

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

            // Actualizar solo los settings de lectura
            $readingSettings = [
                'show_on_front' => $_POST['show_on_front'] ?? 'posts',
                'page_on_front' => $_POST['page_on_front'] ?? '',
                'post_on_front' => $_POST['post_on_front'] ?? '',
                'posts_per_page' => $_POST['posts_per_page'] ?? '10',
                'posts_per_rss' => $_POST['posts_per_rss'] ?? '10',
                'blog_public' => isset($_POST['blog_public']) ? '0' : '1',
                'blog_url_prefix' => $blogUrlPrefix,
                'page_url_prefix' => $pageUrlPrefix,
            ];

            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            foreach ($readingSettings as $key => $value) {
                if ($driver === 'mysql') {
                    $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
                    $stmt->execute([$key, $value, $value]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO settings (\"key\", value) VALUES (?, ?) ON CONFLICT (\"key\") DO UPDATE SET value = EXCLUDED.value");
                    $stmt->execute([$key, $value]);
                }
            }

            // === SINCRONIZACIÓN: Actualizar is_homepage en la tabla pages ===
            $stmt = $pdo->prepare("UPDATE pages SET is_homepage = 0");
            $stmt->execute();

            if ($readingSettings['show_on_front'] === 'page' && !empty($readingSettings['page_on_front'])) {
                $stmt = $pdo->prepare("UPDATE pages SET is_homepage = 1 WHERE id = ?");
                $stmt->execute([$readingSettings['page_on_front']]);
                error_log("SettingsController: Página ID {$readingSettings['page_on_front']} marcada como homepage");
            }

            // Actualizar slugs de blog
            $newBlogPrefix = $blogUrlPrefix ?: null;
            $stmt = $pdo->prepare("UPDATE slugs SET prefix = ? WHERE tenant_id IS NULL AND module = 'blog'");
            $stmt->execute([$newBlogPrefix]);

            // Verificar conflictos cross-module si ambos prefijos son vacíos
            $newPagePrefix = $pageUrlPrefix ?: null;
            if ($newPagePrefix === null && $newBlogPrefix === null) {
                $stmt = $pdo->prepare("
                    SELECT s1.slug FROM slugs s1
                    INNER JOIN slugs s2 ON s1.slug = s2.slug AND s1.tenant_id IS NULL AND s2.tenant_id IS NULL
                    WHERE s1.module = 'pages' AND s2.module = 'blog'
                    LIMIT 5
                ");
                $stmt->execute();
                $conflicts = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                if (!empty($conflicts)) {
                    $pdo->rollBack();
                    flash('error', 'No se pueden quitar ambos prefijos: hay slugs duplicados entre páginas y posts (' . implode(', ', $conflicts) . '). Cambia los slugs duplicados o mantén al menos un prefijo.');
                    header('Location: /musedock/settings/reading');
                    exit;
                }
            }

            // Actualizar slugs de páginas
            $stmt = $pdo->prepare("UPDATE slugs SET prefix = ? WHERE tenant_id IS NULL AND module = 'pages'");
            $stmt->execute([$newPagePrefix]);

            $pdo->commit();

            // Limpiar caché de settings
            setting(null);

            flash('success', 'Ajustes de lectura guardados correctamente.');
            header('Location: /musedock/settings/reading');
            exit;

        } catch (\Exception $e) {
            if (isset($pdo)) {
                $pdo->rollBack();
            }
            error_log("Error updating reading settings: " . $e->getMessage());
            flash('error', 'Error al actualizar los ajustes de lectura.');
            header('Location: /musedock/settings/reading');
            exit;
        }
    }

    public function advanced()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.view');

        $settings = $this->getSettings();
        $versionInfo = $this->getVersionInfo();

        return View::renderSuperadmin('settings.advanced', [
            'title' => 'Ajustes avanzados',
            'settings' => $settings,
            'versionInfo' => $versionInfo,
        ]);
    }

    /**
     * Obtener información de versión desde composer.json
     */
    protected function getVersionInfo(): array
    {
        $composerPath = dirname(__DIR__, 3) . '/composer.json';
        $info = [
            'current' => '0.0.0',
            'name' => 'musedock',
            'repository' => 'https://github.com/caimari/musedock',
            'packagist' => 'caimari/musedock'
        ];

        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);
            $info['current'] = $composer['version'] ?? '0.0.0';
            $info['name'] = $composer['name'] ?? 'musedock';
            $info['repository'] = $composer['support']['source'] ?? $info['repository'];
        }

        return $info;
    }

    /**
     * Verificar actualizaciones disponibles (AJAX)
     */
    public function checkUpdates()
    {
        // Capturar cualquier output previo que pueda haber
        $previousOutput = '';
        while (ob_get_level() > 0) {
            $previousOutput .= ob_get_clean();
        }

        // Establecer headers JSON INMEDIATAMENTE
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('X-Content-Type-Options: nosniff');
        }

        // Iniciar buffer limpio
        ob_start();

        try {
            SessionSecurity::startSession();

            // Verificar que el usuario tenga permiso
            $this->checkPermission('settings.view');

            $versionInfo = $this->getVersionInfo();
            $currentVersion = $versionInfo['current'];
            $latestVersion = null;
            $source = null;
            $downloadUrl = null;
            $changelog = null;

            // 1. Intentar obtener versión desde Packagist (composer)
            $packagistUrl = "https://repo.packagist.org/p2/{$versionInfo['packagist']}.json";
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'ignore_errors' => true,
                    'header' => "User-Agent: MuseDock-CMS/1.0\r\n"
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true
                ]
            ]);
            $packagistData = @file_get_contents($packagistUrl, false, $context);

            if ($packagistData) {
                $packagist = json_decode($packagistData, true);
                $packages = $packagist['packages'][$versionInfo['packagist']] ?? [];

                if (!empty($packages)) {
                    // Ordenar por versión y obtener la más reciente (excluyendo dev)
                    $stableVersions = array_filter($packages, function($pkg) {
                        $version = $pkg['version'] ?? '';
                        return !str_contains($version, 'dev') && !str_contains($version, 'alpha') && !str_contains($version, 'beta');
                    });

                    if (!empty($stableVersions)) {
                        usort($stableVersions, function($a, $b) {
                            return version_compare($b['version'] ?? '0', $a['version'] ?? '0');
                        });
                        $latestVersion = $stableVersions[0]['version'] ?? null;
                        $source = 'packagist';
                        $downloadUrl = "composer update {$versionInfo['packagist']}";
                    }
                }
            }

            // 2. Si no hay Packagist, intentar GitHub API
            if (!$latestVersion) {
                preg_match('/github\.com\/([^\/]+\/[^\/]+)/', $versionInfo['repository'], $matches);
                if (!empty($matches[1])) {
                    $githubRepo = $matches[1];
                    $githubUrl = "https://api.github.com/repos/{$githubRepo}/releases/latest";

                    $opts = stream_context_create([
                        'http' => [
                            'method' => 'GET',
                            'header' => "User-Agent: MuseDock-CMS/1.0\r\nAccept: application/vnd.github.v3+json\r\n",
                            'timeout' => 5,
                            'ignore_errors' => true
                        ]
                    ]);
                    $githubData = @file_get_contents($githubUrl, false, $opts);

                    if ($githubData) {
                        $release = json_decode($githubData, true);
                        if (isset($release['tag_name'])) {
                            $latestVersion = ltrim($release['tag_name'], 'v');
                            $source = 'github';
                            $downloadUrl = $release['html_url'] ?? null;
                            $changelog = $release['body'] ?? null;
                        }
                    }

                    // Si no hay releases, intentar tags
                    if (!$latestVersion) {
                        $tagsUrl = "https://api.github.com/repos/{$githubRepo}/tags";
                        $tagsData = @file_get_contents($tagsUrl, false, $opts);

                        if ($tagsData) {
                            $tags = json_decode($tagsData, true);
                            if (!empty($tags[0]['name'])) {
                                $latestVersion = ltrim($tags[0]['name'], 'v');
                                $source = 'github-tags';
                                $downloadUrl = "https://github.com/{$githubRepo}";
                            }
                        }
                    }
                }
            }

            // Comparar versiones
            $hasUpdate = false;
            if ($latestVersion) {
                $hasUpdate = version_compare($latestVersion, $currentVersion, '>');
            }

            // Descartar cualquier output accidental capturado
            ob_end_clean();

            echo json_encode([
                'success' => true,
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'has_update' => $hasUpdate,
                'source' => $source,
                'download_url' => $downloadUrl,
                'changelog' => $changelog ? mb_substr($changelog, 0, 500) : null
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            // Capturar cualquier output del buffer
            $bufferOutput = '';
            if (ob_get_level() > 0) {
                $bufferOutput = ob_get_clean();
            }

            // Log del error para debugging
            error_log("checkUpdates ERROR: " . $e->getMessage() . " | File: " . $e->getFile() . ":" . $e->getLine());
            if ($previousOutput) {
                error_log("checkUpdates previousOutput: " . substr($previousOutput, 0, 500));
            }

            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'error_file' => basename($e->getFile()) . ':' . $e->getLine(),
                'current_version' => $versionInfo['current'] ?? '0.0.0',
                'debug' => [
                    'previous_output' => $previousOutput ? substr($previousOutput, 0, 200) : null,
                    'buffer_output' => $bufferOutput ? substr($bufferOutput, 0, 200) : null
                ]
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    /**
     * Run CMS update (AJAX POST)
     */
    public function runCmsUpdate()
    {
        while (ob_get_level() > 0) ob_get_clean();
        header('Content-Type: application/json; charset=utf-8');

        try {
            SessionSecurity::startSession();
            $this->checkPermission('settings.edit');

            $result = \Screenart\Musedock\Services\CmsUpdateService::runUpdate();
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Get CMS update status (AJAX GET, polling)
     */
    public function cmsUpdateStatus()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        while (ob_get_level() > 0) ob_get_clean();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $status = \Screenart\Musedock\Services\CmsUpdateService::getUpdateStatus();
            echo json_encode($status, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode(['in_progress' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

public function update()
{
    SessionSecurity::startSession();
    $this->checkPermission('settings.edit');

    // Subida de LOGO (drive interno: /media/file/branding/global/...)
    if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
        $uploadedLogo = $this->storeBrandingUpload($_FILES['site_logo'], 'global', 'logo');
        if ($uploadedLogo) {
            $this->deleteBrandingFile(setting('site_logo'));
            $_POST['site_logo'] = $uploadedLogo;
        }
    } elseif (!isset($_POST['site_logo']) && setting('site_logo')) {
        $_POST['site_logo'] = setting('site_logo');
    }

    // Subida de FAVICON (drive interno: /media/file/branding/global/...)
    if (isset($_FILES['site_favicon']) && $_FILES['site_favicon']['error'] === UPLOAD_ERR_OK) {
        $uploadedFavicon = $this->storeBrandingUpload($_FILES['site_favicon'], 'global', 'favicon');
        if ($uploadedFavicon) {
            $this->deleteBrandingFile(setting('site_favicon'));
            $_POST['site_favicon'] = $uploadedFavicon;
        }
    } elseif (!isset($_POST['site_favicon']) && setting('site_favicon')) {
        $_POST['site_favicon'] = setting('site_favicon');
    }

    // Guardar ajustes básicos
    $this->saveSettings([
        'site_name', 'site_subtitle', 'site_description', 'admin_email',
        'show_logo', 'show_title', 'show_subtitle', 'site_logo', 'site_favicon',
        'timezone', 'date_format', 'time_format',
        // Contact
        'contact_address', 'contact_email', 'contact_phone', 'contact_whatsapp',
        // Footer
        'footer_short_description', 'footer_copyright',
        // Language
        'default_lang', 'force_lang', 'show_language_switcher',
        // Custom code
        'custom_head_code', 'custom_body_start_code', 'custom_body_end_code',
        // Legal
        'legal_jurisdiction', 'legal_entity_type', 'legal_name', 'legal_nif',
        'legal_email', 'legal_address', 'legal_registry_data',
        'legal_supervisory_authority', 'site_has_economic_activity',
        'legal_targets_eu', 'site_uses_analytics_cookies',
        'site_has_user_registration', 'site_has_paid_services'
    ]);


    // Guardar traducciones de footer_short_description por idioma
    $activeLanguages = Database::table('languages')
        ->where('active', 1)
        ->whereNull('tenant_id')
        ->pluck('code');
    foreach ($activeLanguages as $langCode) {
        $key = 'footer_short_description_' . $langCode;
        if (isset($_POST[$key])) {
            Database::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $_POST[$key]]
            );
        }
    }

    // Limpiar caché de settings
    setting(null);

    flash('success', 'Ajustes generales guardados correctamente.');
    header("Location: /musedock/settings");
    exit;
}




/**
 * Gestiona la carga de archivos
 */
protected function handleFileUpload($file, string $directory = 'assets/uploads/logos')
{
    // Validar tamaño máximo (2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        $this->flashError('El logotipo no puede superar los 2 MB.');
        return null;
    }

    // Validar extensiones permitidas
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'ico', 'gif', 'webp', 'svg'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        $this->flashError('Formato de imagen no permitido.');
        return null;
    }

    $targetDir = public_path($directory);

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0775, true);
    }

    $fileName = uniqid() . '.' . $extension;
    $targetFile = $targetDir . '/' . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return $directory . '/' . $fileName;
    }

    $this->flashError('Error al subir el archivo.');
    return null;
}


/**
 * Helper para mostrar errores de forma bonita
 */
protected function flashError($message)
{
    flash('error', $message);
}

protected function storeBrandingUpload(array $file, string $scope, string $kind): ?string
{
    $allowedTypes = $kind === 'favicon'
        ? ['image/x-icon', 'image/png', 'image/svg+xml', 'image/vnd.microsoft.icon']
        : ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];

    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }

    if (!in_array(($file['type'] ?? ''), $allowedTypes, true)) {
        $this->flashError('Formato de imagen no permitido.');
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
    $relative = "branding/{$scope}/{$filename}";

    $destDir = APP_ROOT . '/storage/app/media/branding/' . $scope;
    if (!is_dir($destDir)) {
        mkdir($destDir, 0775, true);
    }

    $destPath = APP_ROOT . '/storage/app/media/' . $relative;
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        $this->flashError('Error al subir el archivo.');
        return null;
    }

    return '/media/file/' . $relative;
}

protected function deleteBrandingFile(?string $storedPath): void
{
    $storedPath = trim((string)$storedPath);
    if ($storedPath === '' || $storedPath === '0') return;

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

    // Legacy: assets/... o uploads/... en public
    if (str_starts_with($storedPath, 'assets/') || str_starts_with($storedPath, '/assets/') || str_starts_with($storedPath, 'uploads/') || str_starts_with($storedPath, '/uploads/')) {
        $legacyPath = public_path(ltrim($storedPath, '/'));
        if (is_file($legacyPath)) {
            @unlink($legacyPath);
        }
    }
}

	
	public function deleteLogo()
{
    SessionSecurity::startSession();
    $this->checkPermission('settings.edit');

    $logo = setting('site_logo');

    $this->deleteBrandingFile($logo);

    // Borrar la referencia en la base de datos
    $pdo = \Screenart\Musedock\Database::connect();
    $keyCol = \Screenart\Musedock\Database::qi('key');
    $stmt = $pdo->prepare("UPDATE settings SET value = '' WHERE {$keyCol} = 'site_logo'");
    $stmt->execute();

    flash('success', 'Logotipo eliminado correctamente.');
    header("Location: /musedock/settings");
    exit;
}

public function deleteFavicon()
{
    SessionSecurity::startSession();
    $this->checkPermission('settings.edit');

    $favicon = setting('site_favicon');

    $this->deleteBrandingFile($favicon);

    // Borrar la referencia en la base de datos
    $pdo = \Screenart\Musedock\Database::connect();
    $keyCol = \Screenart\Musedock\Database::qi('key');
    $stmt = $pdo->prepare("UPDATE settings SET value = '' WHERE {$keyCol} = 'site_favicon'");
    $stmt->execute();

    flash('success', 'Favicon eliminado correctamente.');
    header("Location: /musedock/settings");
    exit;
}

    
    public function updateSeo()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        // Manejar carga de imagen OG si se proporciona
        if (isset($_FILES['og_image']) && $_FILES['og_image']['error'] === UPLOAD_ERR_OK) {
            $ogImage = $this->handleFileUpload($_FILES['og_image'], 'uploads/seo');
            $_POST['og_image'] = $ogImage;
        }
        
        $this->saveSettings([
            'site_keywords', 'site_author', 'og_image', 'twitter_site',
            'social_facebook', 'social_twitter', 'social_instagram',
            'social_pinterest', 'social_youtube', 'social_linkedin',
            'social_github', 'social_tiktok'
        ]);
        
        // Limpiar caché de helper
        if (class_exists('\\Screenart\\Musedock\\Helpers\\SiteHelper')) {
            \Screenart\Musedock\Helpers\SiteHelper::clearCache();
        }
        
        flash('success', 'Ajustes SEO y redes sociales guardados correctamente.');
        header("Location: /musedock/settings/seo");
        exit;
    }
    
    public function updateCookies()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        $this->saveSettings([
            'cookies_enabled', 'cookies_text', 'cookies_accept_basic',
            'cookies_accept_all', 'cookies_more_info', 'cookies_policy_url',
            'cookies_terms_text', 'cookies_terms_url',
            'cookies_show_icon', 'cookies_banner_layout',
            'cookies_bg_color', 'cookies_text_color',
            'cookies_btn_accept_bg', 'cookies_btn_reject_bg'
        ]);

        // Limpiar caché de helper
        if (class_exists('\\Screenart\\Musedock\\Helpers\\SiteHelper')) {
            \Screenart\Musedock\Helpers\SiteHelper::clearCache();
        }

        // Limpiar caché de settings
        setting(null);

        flash('success', 'Configuración de cookies guardada correctamente.');
        header("Location: /musedock/settings/cookies");
        exit;
    }

    public function updateAdvanced()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        // Verificar si la configuración está en .env (no permitir cambios desde UI)
        $envMultiTenant = \Screenart\Musedock\Env::get('MULTI_TENANT_ENABLED', null);
        $envMainDomain = \Screenart\Musedock\Env::get('MAIN_DOMAIN', null);

        // Si multitenencia está en .env, removerla del POST para no guardarla en BD
        if ($envMultiTenant !== null) {
            unset($_POST['multi_tenant_enabled']);
        }

        // Si main_domain está en .env, removerlo del POST
        if ($envMainDomain !== null) {
            unset($_POST['main_domain']);
        }

        // Validación: si se activa multitenencia, debe haber un main_domain
        $multiTenantEnabled = isset($_POST['multi_tenant_enabled']) && $_POST['multi_tenant_enabled'] == '1';
        $mainDomain = trim($_POST['main_domain'] ?? '');

        if ($multiTenantEnabled && empty($mainDomain) && $envMainDomain === null) {
            flash('error', 'Debes configurar un dominio principal para activar la multitenencia.');
            header("Location: /musedock/settings/advanced");
            exit;
        }

        // Validar que main_domain no esté registrado como tenant
        if ($multiTenantEnabled && !empty($mainDomain)) {
            $db = Database::connect();
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM tenants WHERE domain = ?");
            $stmt->execute([$mainDomain]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                flash('error', 'El dominio principal no puede estar registrado como tenant. Por favor, elige otro dominio.');
                header("Location: /musedock/settings/advanced");
                exit;
            }
        }

        $this->saveSettings([
            'force_lang',
            'multi_tenant_enabled', 'main_domain'
        ]);

        flash('success', 'Ajustes avanzados guardados correctamente.');
        header("Location: /musedock/settings/advanced");
        exit;
    }
    


    protected function saveSettings(array $keys)
    {
        foreach ($keys as $key) {
            $value = $_POST[$key] ?? '';
            
            // Checkbox se envía como "on" o no se envía
            if (in_array($key, ['show_logo', 'show_title', 'show_subtitle', 'show_language_switcher', 'cookies_enabled', 'cookies_show_icon', 'site_has_economic_activity', 'legal_targets_eu', 'site_uses_analytics_cookies', 'site_has_user_registration', 'site_has_paid_services'])) {
                $value = isset($_POST[$key]) ? '1' : '0';
            }
            
            Database::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value]
            );
        }
        
        // Limpiar caché de settings para que estén disponibles inmediatamente
        setting(null); // ← Limpia el static $settings
    }

    protected function getSettings(): array
    {
        $rows = Database::table('settings')->get();
        $assoc = [];
        foreach ($rows as $row) {
            $assoc[$row->key] = $row->value; // ← corregido
        }
        return $assoc;
    }
    
    /**
     * Obtiene una lista de zonas horarias para el select
     */
    protected function getTimezones(): array
    {
        $timezones = [
            'UTC' => 'UTC',
            'Europe/Madrid' => 'Europa/Madrid',
            'Europe/London' => 'Europa/Londres',
            'Europe/Paris' => 'Europa/París',
            'America/New_York' => 'América/Nueva York',
            'America/Los_Angeles' => 'América/Los Ángeles',
            'America/Mexico_City' => 'América/Ciudad de México',
            'America/Bogota' => 'América/Bogotá',
            'America/Argentina/Buenos_Aires' => 'América/Buenos Aires',
            'America/Santiago' => 'América/Santiago',
            'Asia/Tokyo' => 'Asia/Tokio',
            'Asia/Shanghai' => 'Asia/Shanghai',
            'Australia/Sydney' => 'Australia/Sídney'
        ];
        
        return $timezones;
    }
    
    /**
     * Obtiene formatos de fecha para el select
     */
    protected function getDateFormats(): array
    {
        return [
            'd/m/Y' => date('d/m/Y') . ' (día/mes/año)',
            'm/d/Y' => date('m/d/Y') . ' (mes/día/año)',
            'Y-m-d' => date('Y-m-d') . ' (año-mes-día)',
            'd.m.Y' => date('d.m.Y') . ' (día.mes.año)',
            'd F, Y' => date('d F, Y') . ' (día mes, año)',
            'F d, Y' => date('F d, Y') . ' (mes día, año)'
        ];
    }
    
    /**
     * Obtiene formatos de hora para el select
     */
    protected function getTimeFormats(): array
    {
        return [
            'H:i' => date('H:i') . ' (24h)',
            'h:i A' => date('h:i A') . ' (12h con AM/PM)',
            'H:i:s' => date('H:i:s') . ' (24h con segundos)',
            'h:i:s A' => date('h:i:s A') . ' (12h con segundos y AM/PM)'
        ];
    }
    
    public function languages()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.view');

        // En /musedock solo gestionamos idiomas globales (tenant_id IS NULL)
        $languages = Language::whereNull('tenant_id')
            ->orderBy('order_position')
            ->get();
        return View::renderSuperadmin('settings.languages', [
            'title'     => 'Idiomas disponibles',
            'languages' => $languages
        ]);
    }
    
    public function updateLanguages()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        $selected = json_decode($_POST['languages'] ?? '[]', true);
        // Recuperar solo idiomas globales (evita tocar los del tenant)
        $allLanguages = \Screenart\Musedock\Database::table('languages')
            ->whereNull('tenant_id')
            ->get();
        foreach ($allLanguages as $lang) {
            $code = is_array($lang) ? $lang['code'] : $lang->code;
            $isActive = in_array($code, $selected) ? 1 : 0;
            \Screenart\Musedock\Database::table('languages')
                ->where('code', $code)
                ->whereNull('tenant_id')
                ->update(['active' => $isActive]);
        }
        
        flash('success', 'Idiomas actualizados correctamente.');
        header('Location: ' . route('settings.languages'));
        exit;
    }
    
    public function clearBladeCache()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        $cacheDirs = [
            __DIR__ . '/../../../storage/cache/superadmin',
            __DIR__ . '/../../../storage/cache/tenant',
            __DIR__ . '/../../../storage/cache/themes',
            __DIR__ . '/../../../storage/cache/modules',
        ];
        
        foreach ($cacheDirs as $dir) {
            if (is_dir($dir)) {
                foreach (glob($dir . '/*.blade.php') as $file) {
                    @unlink($file);
                }
            }
        }
        
        // Limpiar caché de helper SiteHelper
        if (class_exists('\\Screenart\\Musedock\\Helpers\\SiteHelper')) {
            \Screenart\Musedock\Helpers\SiteHelper::clearCache();
        }

        // Limpiar caché de sitemaps y feeds
        $xmlCacheDirs = [
            __DIR__ . '/../../../storage/cache/sitemaps',
            __DIR__ . '/../../../storage/cache/feeds',
        ];
        foreach ($xmlCacheDirs as $dir) {
            if (is_dir($dir)) {
                foreach (glob($dir . '/*.xml') as $file) {
                    @unlink($file);
                }
            }
        }

        // Mensaje de éxito para mostrar en la siguiente carga
        flash('success', 'La caché de vistas Blade, sitemaps y feeds ha sido borrada correctamente.');

        // Redirigir de vuelta al formulario de Ajustes Avanzados
        header('Location: ' . route('settings.advanced'));
        exit;
    }

    public function clearOpcache()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        if (function_exists('opcache_reset') && opcache_reset()) {
            flash('success', 'OPcache de PHP limpiado correctamente.');
        } else {
            flash('warning', 'OPcache no está activo o no se pudo limpiar.');
        }

        header('Location: ' . route('settings.advanced'));
        exit;
    }

    /**
     * Configuración de Email/SMTP
     */
    public function email()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.view');

        // Leer configuración actual del .env
        $envPath = dirname(__DIR__, 3) . '/.env';
        $envConfig = $this->parseEnvFile($envPath);

        return View::renderSuperadmin('settings.email', [
            'title' => 'Configuración de Email',
            'envConfig' => $envConfig,
        ]);
    }

    /**
     * Guardar configuración de Email
     */
    public function updateEmail()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        $envPath = dirname(__DIR__, 3) . '/.env';

        $emailSettings = [
            'MAIL_DRIVER' => $_POST['mail_driver'] ?? 'smtp',
            'SMTP_HOST' => $_POST['smtp_host'] ?? '',
            'SMTP_PORT' => $_POST['smtp_port'] ?? '587',
            'SMTP_USERNAME' => $_POST['smtp_username'] ?? '',
            'SMTP_PASSWORD' => $_POST['smtp_password'] ?? '',
            'SMTP_ENCRYPTION' => $_POST['smtp_encryption'] ?? 'tls',
            'MAIL_FROM_ADDRESS' => $_POST['mail_from_address'] ?? '',
            'MAIL_FROM_NAME' => $_POST['mail_from_name'] ?? '',
        ];

        // No guardar password si está vacío (mantener el actual)
        if (empty($emailSettings['SMTP_PASSWORD'])) {
            $currentEnv = $this->parseEnvFile($envPath);
            $emailSettings['SMTP_PASSWORD'] = $currentEnv['SMTP_PASSWORD'] ?? '';
        }

        $this->updateEnvFile($envPath, $emailSettings);

        flash('success', 'Configuración de email guardada correctamente.');
        header('Location: ' . route('settings.email'));
        exit;
    }

    /**
     * Configuración de Storage/Disco
     */
    public function storage()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.view');

        // Leer configuración actual del .env
        $envPath = dirname(__DIR__, 3) . '/.env';
        $envConfig = $this->parseEnvFile($envPath);

        return View::renderSuperadmin('settings.storage', [
            'title' => 'Configuración de Storage',
            'envConfig' => $envConfig,
        ]);
    }

    /**
     * Guardar configuración de Storage
     */
    public function updateStorage()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        $envPath = dirname(__DIR__, 3) . '/.env';
        $driver = $_POST['filesystem_disk'] ?? 'local';

        $storageSettings = [
            'FILESYSTEM_DISK' => $driver,
        ];

        // Configuración de Cloudflare R2
        if ($driver === 'r2' || !empty($_POST['r2_access_key_id'])) {
            $storageSettings['R2_ACCESS_KEY_ID'] = $_POST['r2_access_key_id'] ?? '';
            $storageSettings['R2_SECRET_ACCESS_KEY'] = $_POST['r2_secret_access_key'] ?? '';
            $storageSettings['R2_BUCKET'] = $_POST['r2_bucket'] ?? '';
            $storageSettings['R2_ENDPOINT'] = $_POST['r2_endpoint'] ?? '';
            $storageSettings['R2_URL'] = $_POST['r2_url'] ?? '';

            // No guardar secret si está vacío
            if (empty($storageSettings['R2_SECRET_ACCESS_KEY'])) {
                $currentEnv = $this->parseEnvFile($envPath);
                $storageSettings['R2_SECRET_ACCESS_KEY'] = $currentEnv['R2_SECRET_ACCESS_KEY'] ?? '';
            }
        }

        // Configuración de Amazon S3
        if ($driver === 's3' || !empty($_POST['aws_access_key_id'])) {
            $storageSettings['AWS_ACCESS_KEY_ID'] = $_POST['aws_access_key_id'] ?? '';
            $storageSettings['AWS_SECRET_ACCESS_KEY'] = $_POST['aws_secret_access_key'] ?? '';
            $storageSettings['AWS_DEFAULT_REGION'] = $_POST['aws_default_region'] ?? 'eu-west-1';
            $storageSettings['AWS_BUCKET'] = $_POST['aws_bucket'] ?? '';
            $storageSettings['AWS_URL'] = $_POST['aws_url'] ?? '';

            // No guardar secret si está vacío
            if (empty($storageSettings['AWS_SECRET_ACCESS_KEY'])) {
                $currentEnv = $this->parseEnvFile($envPath);
                $storageSettings['AWS_SECRET_ACCESS_KEY'] = $currentEnv['AWS_SECRET_ACCESS_KEY'] ?? '';
            }
        }

        // Configuración de IONOS S3
        if ($driver === 'ionos' || !empty($_POST['ionos_access_key_id'])) {
            $storageSettings['IONOS_ACCESS_KEY_ID'] = $_POST['ionos_access_key_id'] ?? '';
            $storageSettings['IONOS_SECRET_ACCESS_KEY'] = $_POST['ionos_secret_access_key'] ?? '';
            $storageSettings['IONOS_BUCKET'] = $_POST['ionos_bucket'] ?? '';
            $storageSettings['IONOS_REGION'] = $_POST['ionos_region'] ?? 'de';
            $storageSettings['IONOS_ENDPOINT'] = $_POST['ionos_endpoint'] ?? '';
            $storageSettings['IONOS_URL'] = $_POST['ionos_url'] ?? '';

            // No guardar secret si está vacío
            if (empty($storageSettings['IONOS_SECRET_ACCESS_KEY'])) {
                $currentEnv = $this->parseEnvFile($envPath);
                $storageSettings['IONOS_SECRET_ACCESS_KEY'] = $currentEnv['IONOS_SECRET_ACCESS_KEY'] ?? '';
            }
        }

        $this->updateEnvFile($envPath, $storageSettings);

        flash('success', 'Configuración de storage guardada correctamente.');
        header('Location: ' . route('settings.storage'));
        exit;
    }

    /**
     * Guardar configuración de Storage para Tenants
     */
    public function updateStorageTenant()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        $envPath = dirname(__DIR__, 3) . '/.env';

        $tenantStorageSettings = [
            // Discos habilitados para tenants
            'TENANT_DISK_MEDIA_ENABLED' => isset($_POST['tenant_disk_media_enabled']) ? 'true' : 'false',
            'TENANT_DISK_LOCAL_ENABLED' => isset($_POST['tenant_disk_local_enabled']) ? 'true' : 'false',
            'TENANT_DISK_R2_ENABLED' => isset($_POST['tenant_disk_r2_enabled']) ? 'true' : 'false',
            'TENANT_DISK_S3_ENABLED' => isset($_POST['tenant_disk_s3_enabled']) ? 'true' : 'false',

            // Cuota por defecto
            'TENANT_DEFAULT_STORAGE_QUOTA_MB' => max(100, min(102400, (int)($_POST['tenant_default_storage_quota_mb'] ?? 1024))),
        ];

        $this->updateEnvFile($envPath, $tenantStorageSettings);

        flash('success', 'Configuración de almacenamiento para tenants guardada correctamente.');
        header('Location: ' . route('settings.storage'));
        exit;
    }

    /**
     * Parsear archivo .env y devolver array asociativo
     */
    private function parseEnvFile(string $path): array
    {
        $config = [];

        if (!file_exists($path)) {
            return $config;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Ignorar comentarios
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parsear KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remover comillas si existen
                if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                    (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }

                $config[$key] = $value;
            }
        }

        return $config;
    }

    /**
     * Obtener todas las páginas publicadas para selector (solo páginas globales, tenant_id IS NULL)
     */
    protected function getAllPages(): array
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                SELECT p.id, COALESCE(pt.title, p.title, p.slug) as title, p.slug
                FROM pages p
                LEFT JOIN page_translations pt ON p.id = pt.page_id AND pt.locale = ?
                WHERE p.status = 'published' AND p.tenant_id IS NULL
                ORDER BY COALESCE(pt.title, p.title, p.slug) ASC
            ");
            $stmt->execute([config('app.locale', 'es')]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("Error getting pages: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener todos los posts de blog publicados para selector (solo posts globales, tenant_id IS NULL)
     */
    protected function getAllBlogPosts(): array
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                SELECT id, title, slug
                FROM blog_posts
                WHERE status = 'published' AND tenant_id IS NULL
                ORDER BY title ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("Error getting blog posts: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Actualizar variables en el archivo .env
     */
    private function updateEnvFile(string $path, array $settings): bool
    {
        if (!file_exists($path)) {
            return false;
        }

        $content = file_get_contents($path);
        $lines = explode("\n", $content);
        $updatedKeys = [];

        foreach ($lines as $i => $line) {
            // Ignorar líneas vacías y comentarios
            if (empty(trim($line)) || strpos(trim($line), '#') === 0) {
                continue;
            }

            // Buscar KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key) = explode('=', $line, 2);
                $key = trim($key);

                if (array_key_exists($key, $settings)) {
                    $value = $settings[$key];
                    // Escapar valor si contiene espacios o caracteres especiales
                    if (preg_match('/[\s#\'"=]/', $value)) {
                        $value = '"' . addslashes($value) . '"';
                    }
                    $lines[$i] = "{$key}={$value}";
                    $updatedKeys[] = $key;
                }
            }
        }

        // Añadir claves que no existían
        foreach ($settings as $key => $value) {
            if (!in_array($key, $updatedKeys)) {
                // Escapar valor si contiene espacios o caracteres especiales
                if (preg_match('/[\s#\'"=]/', $value)) {
                    $value = '"' . addslashes($value) . '"';
                }
                $lines[] = "{$key}={$value}";
            }
        }

        return file_put_contents($path, implode("\n", $lines)) !== false;
    }

    // ==================== BACKUP SYSTEM ====================

    private function getBackupDir(): string
    {
        $dir = dirname(__DIR__, 3) . '/storage/backups/db';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        return $dir;
    }

    /**
     * Backups settings page
     */
    public function backups()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.view');

        $settings = $this->getSettings();
        $backupDir = $this->getBackupDir();

        $backups = $this->listBackups($backupDir);

        return View::renderSuperadmin('settings.backups', [
            'title' => 'Copias de seguridad',
            'backups' => $backups,
            'retention_days' => (int) ($settings['backup_retention_days'] ?? 15),
            'auto_enabled' => (bool) ($settings['backup_auto_enabled'] ?? 1),
        ]);
    }

    /**
     * Update backup settings
     */
    public function updateBackups()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        $retention = max(1, min(365, (int) ($_POST['backup_retention_days'] ?? 15)));
        $autoEnabled = ($_POST['backup_auto_enabled'] ?? '1') === '1' ? '1' : '0';

        $this->saveSettings([
            'backup_retention_days' => (string) $retention,
            'backup_auto_enabled' => $autoEnabled,
        ]);

        flash('success', 'Configuración de backups actualizada.');
        header('Location: /musedock/settings/backups');
        exit;
    }

    /**
     * Create backup (AJAX)
     */
    public function createBackup()
    {
        // Limpiar cualquier output previo y forzar JSON
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');

        try {
            SessionSecurity::startSession();
            $this->checkPermission('settings.edit');

            ob_start();
            $file = $this->performBackup('manual');
            $stray = ob_get_clean(); // Capturar cualquier output accidental

            echo json_encode([
                'success' => true,
                'message' => 'Backup creado: ' . basename($file),
                'file' => basename($file),
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'debug' => $e->getFile() . ':' . $e->getLine(),
            ]);
        }
        exit;
    }

    /**
     * Download backup
     */
    public function downloadBackup()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.view');

        $filename = basename($_GET['file'] ?? '');
        if (!preg_match('/^musedock_db_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}_(manual|auto|pre-restore)\.sql\.gz$/', $filename)) {
            flash('error', 'Archivo de backup inválido.');
            header('Location: /musedock/settings/backups');
            exit;
        }

        $filepath = $this->getBackupDir() . '/' . $filename;
        if (!file_exists($filepath)) {
            flash('error', 'Archivo de backup no encontrado.');
            header('Location: /musedock/settings/backups');
            exit;
        }

        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        readfile($filepath);
        exit;
    }

    /**
     * Delete backup (AJAX)
     */
    public function deleteBackup()
    {
        header('Content-Type: application/json');

        try {
            SessionSecurity::startSession();
            $this->checkPermission('settings.edit');
        } catch (\Exception $e) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }

        $data = $GLOBALS['_JSON_INPUT'] ?? json_decode(file_get_contents('php://input'), true);
        $filename = basename($data['file'] ?? '');

        if (!preg_match('/^musedock_db_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}_(manual|auto|pre-restore)\.sql\.gz$/', $filename)) {
            echo json_encode(['success' => false, 'message' => 'Nombre de archivo inválido.']);
            exit;
        }

        $filepath = $this->getBackupDir() . '/' . $filename;
        if (file_exists($filepath)) {
            unlink($filepath);
        }

        echo json_encode(['success' => true, 'message' => 'Backup eliminado.']);
        exit;
    }

    /**
     * Restore backup (AJAX, password-protected)
     */
    public function restoreBackup()
    {
        header('Content-Type: application/json');

        try {
            SessionSecurity::startSession();
            $this->checkPermission('settings.edit');
        } catch (\Exception $e) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }

        $data = $GLOBALS['_JSON_INPUT'] ?? json_decode(file_get_contents('php://input'), true);
        $filename = basename($data['file'] ?? '');
        $password = $data['password'] ?? '';

        try {
            // Validate filename
            if (!preg_match('/^musedock_db_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}_(manual|auto|pre-restore)\.sql\.gz$/', $filename)) {
                throw new \Exception('Nombre de archivo inválido.');
            }

            $filepath = $this->getBackupDir() . '/' . $filename;
            if (!file_exists($filepath)) {
                throw new \Exception('Archivo de backup no encontrado.');
            }

            // Verify password
            if (empty($password)) {
                throw new \Exception('La contraseña es obligatoria.');
            }

            $auth = SessionSecurity::getAuthenticatedUser();
            if (!$auth || ($auth['type'] ?? '') !== 'super_admin') {
                throw new \Exception('No autorizado.');
            }

            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT password FROM super_admins WHERE id = ?");
            $stmt->execute([$auth['id']]);
            $storedHash = $stmt->fetchColumn();

            if (!$storedHash || !password_verify($password, $storedHash)) {
                throw new \Exception('Contraseña incorrecta.');
            }

            // Create a pre-restore backup
            $this->performBackup('pre-restore');

            // Restore: leer SQL del .gz y ejecutar vía PDO
            $gz = gzopen($filepath, 'rb');
            if (!$gz) {
                throw new \Exception('No se pudo abrir el archivo de backup.');
            }

            $sql = '';
            while (!gzeof($gz)) {
                $sql .= gzread($gz, 8192);
            }
            gzclose($gz);

            if (empty(trim($sql))) {
                throw new \Exception('El archivo de backup está vacío.');
            }

            $pdo = Database::connect();
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Ejecutar el SQL completo
            // Dividir por sentencias para mejor manejo de errores
            $pdo->beginTransaction();
            try {
                $pdo->exec($sql);
                $pdo->commit();
            } catch (\Exception $e) {
                $pdo->rollBack();
                throw new \Exception('Error al restaurar la base de datos: ' . $e->getMessage());
            }

            echo json_encode([
                'success' => true,
                'message' => 'Base de datos restaurada correctamente desde ' . $filename,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Perform a database backup
     */
    public function performBackup(string $type = 'auto'): string
    {
        // Si exec() está disponible (CLI), usar pg_dump
        if (\function_exists('exec')) {
            return $this->performBackupPgDump($type);
        }

        // Fallback: backup vía PDO (PHP-FPM sin exec)
        return $this->performBackupPDO($type);
    }

    /**
     * Backup usando pg_dump (CLI)
     */
    private function performBackupPgDump(string $type): string
    {
        $config = require dirname(__DIR__, 3) . '/config/config.php';
        $dbHost = $config['db']['host'] ?? 'localhost';
        $dbPort = $config['db']['port'] ?? 5432;
        $dbName = $config['db']['name'] ?? '';
        $dbUser = $config['db']['user'] ?? '';
        $dbPass = $config['db']['pass'] ?? '';

        $backupDir = $this->getBackupDir();
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "musedock_db_{$timestamp}_{$type}.sql.gz";
        $filepath = $backupDir . '/' . $filename;

        $envStr = 'PGPASSWORD=' . \escapeshellarg($dbPass);
        $cmd = \sprintf(
            '%s pg_dump -h %s -p %s -U %s -Fp --no-owner --no-acl %s 2>&1 | gzip > %s',
            $envStr,
            \escapeshellarg($dbHost),
            \escapeshellarg((string) $dbPort),
            \escapeshellarg($dbUser),
            \escapeshellarg($dbName),
            \escapeshellarg($filepath)
        );

        $output = [];
        $returnCode = 0;
        \exec($cmd, $output, $returnCode);

        if (!file_exists($filepath) || filesize($filepath) < 100) {
            $errorMsg = implode("\n", $output);
            if (file_exists($filepath)) unlink($filepath);
            throw new \Exception("Backup falló: {$errorMsg}");
        }

        return $filepath;
    }

    /**
     * Backup usando PDO puro (cuando exec no está disponible)
     */
    private function performBackupPDO(string $type): string
    {
        $pdo = Database::connect();
        $backupDir = $this->getBackupDir();
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "musedock_db_{$timestamp}_{$type}.sql.gz";
        $filepath = $backupDir . '/' . $filename;

        $gz = gzopen($filepath, 'wb9');
        if (!$gz) {
            throw new \Exception('No se pudo crear el archivo de backup.');
        }

        try {
            gzwrite($gz, "-- MuseDock DB Backup\n");
            gzwrite($gz, "-- Date: " . date('Y-m-d H:i:s') . "\n");
            gzwrite($gz, "-- Type: {$type}\n");
            gzwrite($gz, "SET client_encoding = 'UTF8';\n");
            gzwrite($gz, "SET standard_conforming_strings = on;\n\n");

            // Obtener todas las tablas
            $tables = $pdo->query(
                "SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename"
            )->fetchAll(\PDO::FETCH_COLUMN);

            // Obtener todas las secuencias
            $sequences = $pdo->query(
                "SELECT sequencename FROM pg_sequences WHERE schemaname = 'public' ORDER BY sequencename"
            )->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                $qtable = '"' . str_replace('"', '""', $table) . '"';

                // Estructura de la tabla
                // Obtener columnas
                $cols = $pdo->query("
                    SELECT column_name, data_type, column_default, is_nullable,
                           character_maximum_length, numeric_precision, numeric_scale,
                           udt_name
                    FROM information_schema.columns
                    WHERE table_schema = 'public' AND table_name = " . $pdo->quote($table) . "
                    ORDER BY ordinal_position
                ")->fetchAll(\PDO::FETCH_ASSOC);

                if (empty($cols)) continue;

                gzwrite($gz, "\n-- Table: {$table}\n");
                gzwrite($gz, "DROP TABLE IF EXISTS {$qtable} CASCADE;\n");
                gzwrite($gz, "CREATE TABLE {$qtable} (\n");

                $colDefs = [];
                foreach ($cols as $col) {
                    $colName = '"' . str_replace('"', '""', $col['column_name']) . '"';
                    $colType = $this->pgColumnType($col);
                    $nullable = $col['is_nullable'] === 'NO' ? ' NOT NULL' : '';
                    $default = '';
                    if ($col['column_default'] !== null) {
                        $default = ' DEFAULT ' . $col['column_default'];
                    }
                    $colDefs[] = "    {$colName} {$colType}{$nullable}{$default}";
                }
                gzwrite($gz, implode(",\n", $colDefs) . "\n");
                gzwrite($gz, ");\n");

                // Constraints (PK, UNIQUE)
                $constraints = $pdo->query("
                    SELECT con.conname, con.contype,
                           pg_get_constraintdef(con.oid) as definition
                    FROM pg_constraint con
                    JOIN pg_class rel ON rel.oid = con.conrelid
                    JOIN pg_namespace nsp ON nsp.oid = rel.relnamespace
                    WHERE rel.relname = " . $pdo->quote($table) . "
                    AND nsp.nspname = 'public'
                    ORDER BY con.contype
                ")->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($constraints as $con) {
                    $conName = '"' . str_replace('"', '""', $con['conname']) . '"';
                    gzwrite($gz, "ALTER TABLE {$qtable} ADD CONSTRAINT {$conName} {$con['definition']};\n");
                }

                // Datos
                $count = $pdo->query("SELECT COUNT(*) FROM {$qtable}")->fetchColumn();
                if ($count > 0) {
                    gzwrite($gz, "\n-- Data for {$table} ({$count} rows)\n");

                    $colNames = array_map(fn($c) => '"' . str_replace('"', '""', $c['column_name']) . '"', $cols);
                    $colList = implode(', ', $colNames);

                    // Leer por lotes para no saturar memoria
                    $batchSize = 500;
                    $offset = 0;
                    while ($offset < $count) {
                        $rows = $pdo->query("SELECT * FROM {$qtable} LIMIT {$batchSize} OFFSET {$offset}")->fetchAll(\PDO::FETCH_ASSOC);
                        foreach ($rows as $row) {
                            $values = [];
                            foreach ($cols as $col) {
                                $val = $row[$col['column_name']];
                                if ($val === null) {
                                    $values[] = 'NULL';
                                } elseif ($this->pgIsNumeric($col)) {
                                    $values[] = $val;
                                } elseif ($col['data_type'] === 'boolean' || $col['udt_name'] === 'bool') {
                                    $values[] = ($val === true || $val === 't' || $val === '1' || $val === 1) ? 'true' : 'false';
                                } else {
                                    $values[] = $pdo->quote((string) $val);
                                }
                            }
                            gzwrite($gz, "INSERT INTO {$qtable} ({$colList}) VALUES (" . implode(', ', $values) . ");\n");
                        }
                        $offset += $batchSize;
                    }
                }
            }

            // Restaurar valores de secuencias
            gzwrite($gz, "\n-- Sequences\n");
            foreach ($sequences as $seq) {
                $qseq = '"' . str_replace('"', '""', $seq) . '"';
                $val = $pdo->query("SELECT last_value FROM {$qseq}")->fetchColumn();
                if ($val) {
                    gzwrite($gz, "SELECT setval('{$seq}', {$val}, true);\n");
                }
            }

            // Índices (no PK/UNIQUE que ya van en constraints)
            gzwrite($gz, "\n-- Indexes\n");
            $indexes = $pdo->query("
                SELECT indexname, indexdef
                FROM pg_indexes
                WHERE schemaname = 'public'
                AND indexname NOT IN (
                    SELECT conname FROM pg_constraint
                    WHERE connamespace = (SELECT oid FROM pg_namespace WHERE nspname = 'public')
                )
                ORDER BY tablename, indexname
            ")->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($indexes as $idx) {
                gzwrite($gz, $idx['indexdef'] . ";\n");
            }

            gzwrite($gz, "\n-- Backup completed\n");

        } finally {
            gzclose($gz);
        }

        if (!file_exists($filepath) || filesize($filepath) < 50) {
            if (file_exists($filepath)) unlink($filepath);
            throw new \Exception('El backup generado está vacío o es inválido.');
        }

        return $filepath;
    }

    /**
     * Determina el tipo SQL de una columna PostgreSQL
     */
    private function pgColumnType(array $col): string
    {
        $type = $col['udt_name'] ?? $col['data_type'];

        switch ($type) {
            case 'int4': return 'integer';
            case 'int8': return 'bigint';
            case 'int2': return 'smallint';
            case 'float4': return 'real';
            case 'float8': return 'double precision';
            case 'bool': return 'boolean';
            case 'varchar':
                $len = $col['character_maximum_length'];
                return $len ? "character varying({$len})" : 'character varying';
            case 'bpchar':
                $len = $col['character_maximum_length'];
                return $len ? "character({$len})" : 'character';
            case 'numeric':
                $p = $col['numeric_precision'];
                $s = $col['numeric_scale'];
                return ($p && $s !== null) ? "numeric({$p},{$s})" : 'numeric';
            case 'text': return 'text';
            case 'timestamp': return 'timestamp without time zone';
            case 'timestamptz': return 'timestamp with time zone';
            case 'date': return 'date';
            case 'time': return 'time without time zone';
            case 'timetz': return 'time with time zone';
            case 'json': return 'json';
            case 'jsonb': return 'jsonb';
            case 'uuid': return 'uuid';
            case 'bytea': return 'bytea';
            case 'inet': return 'inet';
            case 'cidr': return 'cidr';
            case '_text': return 'text[]';
            case '_int4': return 'integer[]';
            case '_varchar': return 'character varying[]';
            default: return $col['data_type'] ?? $type;
        }
    }

    /**
     * Determina si una columna es numérica
     */
    private function pgIsNumeric(array $col): bool
    {
        return in_array($col['udt_name'] ?? '', ['int2', 'int4', 'int8', 'float4', 'float8', 'numeric', 'oid']);
    }

    /**
     * Cleanup old backups based on retention days
     */
    public function cleanupOldBackups(): int
    {
        $settings = $this->getSettings();
        $retentionDays = (int) ($settings['backup_retention_days'] ?? 15);
        $backupDir = $this->getBackupDir();
        $cutoff = time() - ($retentionDays * 86400);
        $deleted = 0;

        foreach (glob($backupDir . '/musedock_db_*.sql.gz') as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * List available backups
     */
    private function listBackups(string $dir): array
    {
        $backups = [];
        $files = glob($dir . '/musedock_db_*.sql.gz');

        if (!$files) return [];

        // Sort by modification time, newest first
        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));

        foreach ($files as $file) {
            $filename = basename($file);
            $size = filesize($file);
            $mtime = filemtime($file);

            // Format size
            if ($size >= 1073741824) {
                $sizeStr = number_format($size / 1073741824, 2) . ' GB';
            } elseif ($size >= 1048576) {
                $sizeStr = number_format($size / 1048576, 1) . ' MB';
            } elseif ($size >= 1024) {
                $sizeStr = number_format($size / 1024, 0) . ' KB';
            } else {
                $sizeStr = $size . ' B';
            }

            $backups[] = [
                'filename' => $filename,
                'date' => date('d/m/Y H:i:s', $mtime),
                'size' => $sizeStr,
                'is_auto' => strpos($filename, '_auto.') !== false,
                'timestamp' => $mtime,
            ];
        }

        return $backups;
    }
}
