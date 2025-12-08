<?php

namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\Database;
use Screenart\Musedock\View;
use Screenart\Musedock\Logger;

use Screenart\Musedock\Traits\RequiresPermission;
class ThemeController
{
    use RequiresPermission;
use Screenart\Musedock\Security\SessionSecurity;

    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        $themes = getAvailableThemes();
        $activeTheme = tenant()['theme'] ?? 'default';

        Logger::log("Vista themes.index cargada con: " . json_encode([
            'temas' => $themes,
            'activo' => $activeTheme
        ]));

        return View::renderTenantAdmin('themes.index', [
            'themes' => $themes,
            'activeTheme' => $activeTheme,
            'title' => 'Temas disponibles'
        ]);
    }

    public function update()
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo "Método no permitido";
            exit;
        }

        // 🔒 SECURITY: Verificar CSRF token
        if (!isset($_POST['_csrf']) || !verify_csrf_token($_POST['_csrf'])) {
            http_response_code(403);
            flash('error', 'Token CSRF inválido');
            header('Location: /' . admin_path() . '/dashboard');
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
