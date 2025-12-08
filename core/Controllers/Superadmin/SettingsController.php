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
        // IMPORTANTE: Limpiar TODOS los buffers antes de cualquier cosa
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Establecer headers JSON INMEDIATAMENTE
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');

        // Iniciar buffer para capturar cualquier output accidental
        ob_start();

        try {
            SessionSecurity::startSession();

            // Verificar permiso - sin redirección, solo devolver error JSON
            if (!userCan('settings.view')) {
                ob_end_clean();
                echo json_encode([
                    'success' => false,
                    'error' => 'No tienes permiso para verificar actualizaciones'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

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
            // Descartar cualquier output capturado
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'current_version' => $versionInfo['current'] ?? '0.0.0'
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

    // Guardar ajustes
    $this->saveSettings([
        'site_name', 'site_description', 'admin_email',
        'timezone', 'date_format', 'time_format',
        'show_logo', 'show_title', 'site_logo', 'site_favicon',
        'footer_short_description', 'contact_address', 'contact_email',
        'contact_phone', 'contact_whatsapp', 'footer_copyright'
    ]);

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
    $stmt = $pdo->prepare("UPDATE settings SET value = '' WHERE `key` = 'site_logo'");
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
    $stmt = $pdo->prepare("UPDATE settings SET value = '' WHERE `key` = 'site_favicon'");
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
            'cookies_accept_all', 'cookies_more_info', 'cookies_policy_url'
        ]);
        
        // Limpiar caché de helper
        if (class_exists('\\Screenart\\Musedock\\Helpers\\SiteHelper')) {
            \Screenart\Musedock\Helpers\SiteHelper::clearCache();
        }
        
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
}
