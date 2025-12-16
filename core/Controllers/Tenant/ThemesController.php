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

        return View::renderTenantAdmin('themes.index', [
            'globalThemes' => $globalThemes,
            'customThemes' => $customThemes,
            'tenant' => $tenant,
            'tenantId' => $tenantId,
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
