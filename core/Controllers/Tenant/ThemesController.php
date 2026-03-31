<?php

namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\TenantThemeManager;
use Screenart\Musedock\Services\TenantManager;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\AuditLogger;
use Screenart\Musedock\View;
use Screenart\Musedock\Logger;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Models\ThemeSkin;
use Screenart\Musedock\Models\ThemeOption;
use Screenart\Musedock\Cache\HtmlCache;

class ThemesController
{
    use RequiresPermission;

    /**
     * Listar temas (globales + personalizados)
     */
    public function index() {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        $tenantId = tenant_id();

        // Temas globales del sistema
        $globalThemes = $this->getGlobalThemes();

        // Temas personalizados del tenant
        $customThemes = Database::query(
            "SELECT * FROM tenant_themes WHERE tenant_id = :tenant_id ORDER BY name",
            ['tenant_id' => $tenantId]
        )->fetchAll(\PDO::FETCH_ASSOC);

        // Tema activo actual
        $tenant = Database::query(
            "SELECT theme_type, custom_theme_slug, theme FROM tenants WHERE id = :id",
            ['id' => $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        // Skins disponibles para el tema activo
        $activeThemeSlug = ($tenant['theme_type'] === 'custom' && $tenant['custom_theme_slug'])
            ? $tenant['custom_theme_slug']
            : ($tenant['theme'] ?? 'default');
        $skins = ThemeSkin::getAvailableSkins($activeThemeSlug, $tenantId);

        // Detect which skin is currently applied
        $currentOptions = ThemeOption::getOptions($activeThemeSlug, $tenantId);
        $activeSkinSlug = $currentOptions['_active_skin'] ?? null;

        return View::renderTenantAdmin('themes.index', [
            'globalThemes' => $globalThemes,
            'customThemes' => $customThemes,
            'skins' => $skins,
            'tenant' => $tenant,
            'tenantId' => $tenantId,
            'activeThemeSlug' => $activeThemeSlug,
            'activeSkinSlug' => $activeSkinSlug,
            'title' => 'Gestión de Temas'
        ]);
    }

    /**
     * Subir e instalar tema personalizado
     */
    public function upload() {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        $tenantId = tenant_id();

        if (!isset($_FILES['theme_zip'])) {
            flash('error', 'No se seleccionó ningún archivo.');
            return redirect('/' . admin_path() . '/themes');
        }

        $file = $_FILES['theme_zip'];

        // Validaciones básicas
        if (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'zip') {
            flash('error', 'Solo se permiten archivos ZIP.');
            return redirect('/' . admin_path() . '/themes');
        }

        if ($file['size'] > 20 * 1024 * 1024) {
            flash('error', 'El archivo es demasiado grande (máximo 20MB).');
            return redirect('/' . admin_path() . '/themes');
        }

        // Instalar con validación de seguridad
        $result = TenantThemeManager::install($tenantId, $file['tmp_name']);

        if ($result['success']) {
            // Loguear en auditoría si existe
            if (class_exists('Screenart\Musedock\Security\AuditLogger')) {
                AuditLogger::log('theme.installed', 'INFO', [
                    'theme_slug' => $result['slug'],
                    'theme_name' => $result['name'],
                    'validated' => $result['validated'],
                    'security_score' => $result['security_score']
                ]);
            }

            $message = "Tema '{$result['name']}' instalado";

            if ($result['validated']) {
                $message .= " (Score de seguridad: {$result['security_score']}/100)";
                flash('success', $message);
            } else {
                $message .= " pero NO pasó validación de seguridad. No se puede activar.";
                flash('warning', $message);
            }

            if (!empty($result['warnings'])) {
                flash('info', 'Advertencias: ' . implode(', ', $result['warnings']));
            }
        } else {
            flash('error', 'Error al instalar tema: ' . $result['error']);
        }

        return redirect('/' . admin_path() . '/themes');
    }

    /**
     * Activar tema personalizado
     */
    public function activateCustom($slug) {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        $tenantId = tenant_id();

        if (TenantThemeManager::activate($tenantId, $slug)) {
            if (class_exists('Screenart\Musedock\Security\AuditLogger')) {
                AuditLogger::log('theme.activated', 'INFO', ['theme_slug' => $slug, 'theme_type' => 'custom']);
            }
            HtmlCache::onThemeChanged($tenantId);
            flash('success', 'Tema personalizado activado correctamente.');
        } else {
            flash('error', 'Error al activar tema. Verifica que esté validado.');
        }

        return redirect('/' . admin_path() . '/themes');
    }

    /**
     * Activar tema global
     */
    public function activateGlobal($slug) {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        $tenantId = tenant_id();

        if (TenantThemeManager::useGlobalTheme($tenantId, $slug)) {
            if (class_exists('Screenart\Musedock\Security\AuditLogger')) {
                AuditLogger::log('theme.activated', 'INFO', ['theme_slug' => $slug, 'theme_type' => 'global']);
            }
            HtmlCache::onThemeChanged($tenantId);
            flash('success', 'Tema global activado correctamente.');
        } else {
            flash('error', 'Error al activar tema global.');
        }

        return redirect('/' . admin_path() . '/themes');
    }

    /**
     * Desinstalar tema personalizado
     */
    public function uninstall($slug) {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        $tenantId = tenant_id();

        if (TenantThemeManager::uninstall($tenantId, $slug)) {
            if (class_exists('Screenart\Musedock\Security\AuditLogger')) {
                AuditLogger::log('theme.uninstalled', 'WARNING', ['theme_slug' => $slug]);
            }
            flash('success', 'Tema desinstalado correctamente.');
        } else {
            flash('error', 'Error al desinstalar tema.');
        }

        return redirect('/' . admin_path() . '/themes');
    }

    /**
     * Revalidar tema personalizado
     */
    public function revalidate($slug) {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        $tenantId = tenant_id();

        $results = TenantThemeManager::syncTenantThemes($tenantId);

        flash('success', 'Tema revalidado correctamente.');
        return redirect('/' . admin_path() . '/themes');
    }

    /**
     * Obtener temas globales disponibles para tenants
     * Solo muestra temas que tienen available_for_tenants = 1 en la BD
     */
    private function getGlobalThemes(): array {
        $themes = [];
        $themesPath = APP_ROOT . '/themes';

        if (!is_dir($themesPath)) {
            return $themes;
        }

        // Obtener lista de temas habilitados para tenants desde la BD
        $availableThemes = Database::query(
            "SELECT slug FROM themes WHERE available_for_tenants = 1"
        )->fetchAll(\PDO::FETCH_COLUMN);

        foreach (glob($themesPath . '/*', GLOB_ONLYDIR) as $themePath) {
            $slug = basename($themePath);

            // Saltar directorios especiales
            if (in_array($slug, ['tenant_1', 'tenant_16', 'shared'])) {
                continue;
            }

            // Solo mostrar temas habilitados para tenants
            if (!in_array($slug, $availableThemes)) {
                continue;
            }

            $metadataFile = $themePath . '/theme.json';
            if (!file_exists($metadataFile)) {
                continue;
            }

            $metadata = json_decode(file_get_contents($metadataFile), true);

            $themes[] = [
                'slug' => $slug,
                'name' => $metadata['name'] ?? ucfirst($slug),
                'description' => $metadata['description'] ?? '',
                'author' => $metadata['author'] ?? 'MuseDock Team',
                'version' => $metadata['version'] ?? '1.0.0',
                'screenshot' => $metadata['screenshot'] ?? null,
                'type' => 'global'
            ];
        }

        return $themes;
    }

    // ========== SKINS ==========

    /**
     * Apply a skin (loads its options into the active theme).
     */
    public function applySkin($skinSlug) {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            flash('error', 'Token de seguridad inválido.');
            return redirect('/' . admin_path() . '/themes');
        }

        $tenantId = tenant_id();
        $skin = ThemeSkin::getBySlug($skinSlug, $tenantId);

        if (!$skin) {
            flash('error', 'Skin no encontrado.');
            return redirect('/' . admin_path() . '/themes');
        }

        $themeSlug = $skin['theme_slug'];
        $options = is_string($skin['options']) ? json_decode($skin['options'], true) : $skin['options'];

        if (empty($options)) {
            flash('error', 'El skin no contiene opciones válidas.');
            return redirect('/' . admin_path() . '/themes');
        }

        // Apply skin options to the theme (store which skin is active)
        $options['_active_skin'] = $skinSlug;
        $success = ThemeOption::saveOptions($themeSlug, $tenantId, $options);

        if ($success) {
            // Regenerate CSS/JS
            $appearanceController = new ThemeAppearanceController();
            try {
                $reflection = new \ReflectionMethod($appearanceController, 'generateCustomCSS');
                $reflection->setAccessible(true);
                $reflection->invoke($appearanceController, $themeSlug, $options, $tenantId);

                $reflection2 = new \ReflectionMethod($appearanceController, 'generateCustomJS');
                $reflection2->setAccessible(true);
                $reflection2->invoke($appearanceController, $themeSlug, $options, $tenantId);
            } catch (\Exception $e) {
                Logger::log("Error regenerating CSS/JS after skin apply: " . $e->getMessage(), 'WARNING');
            }

            ThemeSkin::incrementInstallCount((int)$skin['id']);

            // Update cookie banner colors to match skin accent
            try {
                $pdo = \Screenart\Musedock\Database::connect();
                $accent = $options['header']['header_link_hover_color']
                       ?? $options['scroll_to_top']['scroll_to_top_bg_color']
                       ?? '#ff5e15';
                $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
                $keyCol = \Screenart\Musedock\Database::qi('key');
                $upsertSetting = function($key, $value) use ($pdo, $tenantId, $driver, $keyCol) {
                    if ($driver === 'pgsql') {
                        $stmt = $pdo->prepare("INSERT INTO tenant_settings (tenant_id, {$keyCol}, value) VALUES (?, ?, ?) ON CONFLICT (tenant_id, {$keyCol}) DO UPDATE SET value = EXCLUDED.value");
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO tenant_settings (tenant_id, {$keyCol}, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
                    }
                    $stmt->execute([$tenantId, $key, $value]);
                };
                $upsertSetting('cookies_btn_accept_bg', $accent);
                $upsertSetting('cookies_btn_reject_bg', '#6b7280');
                $upsertSetting('cookies_bg_color', '#ffffff');
                $upsertSetting('cookies_text_color', '#333333');
            } catch (\Exception $e) {
                // Non-fatal
            }

            if (class_exists('Screenart\Musedock\Security\AuditLogger')) {
                AuditLogger::log('skin.applied', 'INFO', [
                    'skin_slug' => $skinSlug,
                    'skin_name' => $skin['name'],
                    'theme_slug' => $themeSlug
                ]);
            }

            flash('success', "Skin '{$skin['name']}' aplicado correctamente.");
        } else {
            flash('error', 'Error al aplicar el skin.');
        }

        return redirect('/' . admin_path() . '/themes');
    }

    /**
     * Upload a skin (.skin.json file).
     */
    public function uploadSkin() {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        $tenantId = tenant_id();

        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            flash('error', 'Token de seguridad inválido.');
            return redirect('/' . admin_path() . '/themes');
        }

        if (!isset($_FILES['skin_file']) || $_FILES['skin_file']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'No se seleccionó ningún archivo.');
            return redirect('/' . admin_path() . '/themes');
        }

        $file = $_FILES['skin_file'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

        if ($ext !== 'json') {
            flash('error', 'Solo se permiten archivos .json');
            return redirect('/' . admin_path() . '/themes');
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            flash('error', 'El archivo es demasiado grande (máximo 2MB).');
            return redirect('/' . admin_path() . '/themes');
        }

        $content = file_get_contents($file['tmp_name']);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            flash('error', 'El archivo JSON no es válido.');
            return redirect('/' . admin_path() . '/themes');
        }

        // Validate skin structure
        $validation = ThemeSkin::validateSkinData($data);
        if (!$validation['valid']) {
            flash('error', 'Skin no válido: ' . implode(', ', $validation['errors']));
            return redirect('/' . admin_path() . '/themes');
        }

        $slug = ThemeSkin::generateSlug($data['name']);

        $skinData = [
            'slug' => $slug,
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'author' => $data['author'] ?? 'Usuario',
            'version' => $data['version'] ?? '1.0',
            'theme_slug' => $data['theme_slug'] ?? 'default',
            'screenshot' => $data['screenshot'] ?? null,
            'options' => $data['options'],
            'is_global' => 0,
            'tenant_id' => $tenantId,
            'is_active' => 1,
        ];

        if (ThemeSkin::saveSkin($skinData)) {
            if (class_exists('Screenart\Musedock\Security\AuditLogger')) {
                AuditLogger::log('skin.uploaded', 'INFO', [
                    'skin_slug' => $slug,
                    'skin_name' => $data['name']
                ]);
            }
            flash('success', "Skin '{$data['name']}' subido correctamente.");
        } else {
            flash('error', 'Error al guardar el skin.');
        }

        return redirect('/' . admin_path() . '/themes');
    }

    /**
     * Delete a tenant-owned skin.
     */
    public function deleteSkin($slug) {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            flash('error', 'Token de seguridad inválido.');
            return redirect('/' . admin_path() . '/themes');
        }

        $tenantId = tenant_id();

        if (ThemeSkin::deleteSkin($slug, $tenantId)) {
            flash('success', 'Skin eliminado correctamente.');
        } else {
            flash('error', 'Error al eliminar el skin. Solo puedes eliminar skins propios.');
        }

        return redirect('/' . admin_path() . '/themes');
    }

    // ========== MÉTODOS LEGACY (compatibilidad con ThemeController antiguo) ==========

    /**
     * @deprecated Use index() instead
     */
    public function update()
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo "Método no permitido";
            exit;
        }

        $theme = trim($_POST['theme'] ?? 'default');
        $tenantId = tenant_id();

        // Validar que sea un tema válido
        if (!in_array($theme, getAvailableThemes())) {
            flash('error', 'Tema no válido.');
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }

        Database::table('tenants')->where('id', $tenantId)->update([
            'theme' => $theme
        ]);

        flash('success', 'Tema actualizado correctamente.');
        header('Location: /' . admin_path() . '/dashboard');
        exit;
    }
}
