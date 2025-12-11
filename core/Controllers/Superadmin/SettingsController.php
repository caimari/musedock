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

            // Actualizar solo los settings de lectura
            $readingSettings = [
                'show_on_front' => $_POST['show_on_front'] ?? 'posts',
                'page_on_front' => $_POST['page_on_front'] ?? '',
                'post_on_front' => $_POST['post_on_front'] ?? '',
                'posts_per_page' => $_POST['posts_per_page'] ?? '10',
                'posts_per_rss' => $_POST['posts_per_rss'] ?? '10',
                'blog_public' => isset($_POST['blog_public']) ? '0' : '1', // 0 = no indexar, 1 = indexar (default)
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
            // Primero, desmarcar todas las páginas como homepage
            $stmt = $pdo->prepare("UPDATE pages SET is_homepage = 0");
            $stmt->execute();

            // Si se seleccionó una página estática como homepage, marcarla
            if ($readingSettings['show_on_front'] === 'page' && !empty($readingSettings['page_on_front'])) {
                $stmt = $pdo->prepare("UPDATE pages SET is_homepage = 1 WHERE id = ?");
                $stmt->execute([$readingSettings['page_on_front']]);
                error_log("SettingsController: Página ID {$readingSettings['page_on_front']} marcada como homepage");
            }

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


public function update()
{
    SessionSecurity::startSession();
    $this->checkPermission('settings.edit');

    $uploadedLogo = null;
    $uploadedFavicon = null;

    // Subida de LOGO
    if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
        $uploadedLogo = $this->handleFileUpload($_FILES['site_logo']);
        if ($uploadedLogo) {
            $oldLogo = setting('site_logo');
            if ($oldLogo && file_exists(public_path('assets/' . $oldLogo))) {
                unlink(public_path('assets/' . $oldLogo));
            }
            $_POST['site_logo'] = $uploadedLogo;
        }
    }

    // Si no se subió logo nuevo pero ya existe uno, mantenerlo
    if (!isset($_POST['site_logo']) && setting('site_logo')) {
        $_POST['site_logo'] = setting('site_logo');
    }

    // Subida de FAVICON
    if (isset($_FILES['site_favicon']) && $_FILES['site_favicon']['error'] === UPLOAD_ERR_OK) {
        $uploadedFavicon = $this->handleFileUpload($_FILES['site_favicon']);
        if ($uploadedFavicon) {
            $oldFavicon = setting('site_favicon');
            if ($oldFavicon && file_exists(public_path('assets/' . $oldFavicon))) {
                unlink(public_path('assets/' . $oldFavicon));
            }
            $_POST['site_favicon'] = $uploadedFavicon;
        }
    }

    // Si no se subió favicon nuevo pero ya existe uno, mantenerlo
    if (!isset($_POST['site_favicon']) && setting('site_favicon')) {
        $_POST['site_favicon'] = setting('site_favicon');
    }

    // Guardar ajustes básicos
    $this->saveSettings([
        'site_name', 'site_description', 'admin_email',
        'timezone', 'date_format', 'time_format',
        'show_logo', 'show_title', 'site_logo', 'site_favicon',
        'footer_short_description', 'contact_address', 'contact_email',
        'contact_phone', 'contact_whatsapp', 'footer_copyright'
    ]);

    // Guardar traducciones de footer_short_description por idioma
    $activeLanguages = Database::table('languages')->where('active', 1)->pluck('code');
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
protected function handleFileUpload($file)
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

    // Guardar en ubicación pública persistente (no dentro del tema)
    $directory = 'assets/uploads/logos';
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

	
	public function deleteLogo()
{
    SessionSecurity::startSession();
    $this->checkPermission('settings.edit');

    $logo = setting('site_logo');

    if ($logo && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($logo, '/'))) {
        unlink($_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($logo, '/'));
    }

    // Borrar la referencia en la base de datos
    $pdo = \Screenart\Musedock\Database::connect();
    $stmt = $pdo->prepare("UPDATE settings SET value = '' WHERE \"key\" = 'site_logo'");
    $stmt->execute();

    flash('success', 'Logotipo eliminado correctamente.');
    header("Location: /musedock/settings");
    exit;
}

public function deleteFavicon()
{
    SessionSecurity::startSession();
    $this->checkPermission('settings.edit');


    if ($favicon && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($favicon, '/'))) {
        unlink($_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($favicon, '/'));
    }

    // Borrar la referencia en la base de datos
    $pdo = \Screenart\Musedock\Database::connect();
    $stmt = $pdo->prepare("UPDATE settings SET value = '' WHERE \"key\" = 'site_favicon'");
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
            'social_pinterest', 'social_youtube', 'social_linkedin'
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
            'cookies_terms_text', 'cookies_terms_url'
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
            if ($key === 'show_logo' || $key === 'show_title' || $key === 'cookies_enabled') {
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

        $languages = Language::all();
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
        // Recuperar todos los idiomas
        $allLanguages = \Screenart\Musedock\Database::table('languages')->get();
        foreach ($allLanguages as $lang) {
            $code = is_array($lang) ? $lang['code'] : $lang->code;
            $isActive = in_array($code, $selected) ? 1 : 0;
            \Screenart\Musedock\Database::table('languages')
                ->where('code', $code)
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
        
        // Mensaje de éxito para mostrar en la siguiente carga
        flash('success', 'La caché de las vistas Blade ha sido borrada correctamente.');

        // Redirigir de vuelta al formulario de Ajustes Avanzados
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
     * Obtener todas las páginas publicadas para selector
     */
    protected function getAllPages(): array
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                SELECT p.id, COALESCE(pt.title, p.title, p.slug) as title, p.slug
                FROM pages p
                LEFT JOIN page_translations pt ON p.id = pt.page_id AND pt.locale = ?
                WHERE p.status = 'published'
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
     * Obtener todos los posts de blog publicados para selector
     */
    protected function getAllBlogPosts(): array
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                SELECT id, title, slug
                FROM blog_posts
                WHERE status = 'published'
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
}
