<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Services\SuperadminPluginService;
use Screenart\Musedock\Models\SuperadminPlugin;
use Screenart\Musedock\Traits\RequiresPermission;

class PluginsController
{
    use RequiresPermission;

    /**
     * Listar todos los plugins
     */
    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        // Obtener plugins instalados
        $installedPlugins = SuperadminPlugin::all();

        // Escanear directorio para detectar nuevos plugins
        $discoveredPlugins = SuperadminPluginService::scanPlugins();

        // Filtrar plugins descubiertos que no están en BD
        $installedSlugs = array_column(array_map(fn($p) => $p->toArray(), $installedPlugins), 'slug');
        $newPlugins = array_filter($discoveredPlugins, fn($p) => !in_array($p['slug'], $installedSlugs));

        return View::renderSuperadmin('plugins.index', [
            'installedPlugins' => $installedPlugins,
            'newPlugins' => $newPlugins,
            'stats' => [
                'total' => count($installedPlugins),
                'active' => count(array_filter($installedPlugins, fn($p) => $p->is_active)),
                'inactive' => count(array_filter($installedPlugins, fn($p) => !$p->is_active && $p->is_installed)),
                'available' => count($newPlugins)
            ]
        ]);
    }

    /**
     * Ver detalles de un plugin
     */
    public function show(int $id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        $plugin = SuperadminPlugin::find($id);

        if (!$plugin) {
            flash('error', 'Plugin no encontrado.');
            header('Location: /musedock/plugins');
            exit;
        }

        // Verificar requisitos
        $requirements = $plugin->meetsRequirements();

        return View::renderSuperadmin('plugins.show', [
            'plugin' => $plugin,
            'requirements' => $requirements
        ]);
    }

    /**
     * Instalar un plugin
     */
    public function install()
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        // Validar CSRF
        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
            exit;
        }

        $slug = $_POST['slug'] ?? null;

        if (!$slug) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Slug del plugin requerido']);
            exit;
        }

        $result = SuperadminPluginService::install($slug);

        if (!$result['success']) {
            flash('error', $result['message']);
        } else {
            flash('success', $result['message']);
        }

        header('Location: /musedock/plugins');
        exit;
    }

    /**
     * Desinstalar un plugin
     */
    public function uninstall(int $id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        // Validar CSRF
        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
            exit;
        }

        $result = SuperadminPluginService::uninstall($id);

        if (!$result['success']) {
            flash('error', $result['message']);

            if (isset($result['dependents'])) {
                flash('error', 'Dependientes: ' . implode(', ', $result['dependents']));
            }
        } else {
            flash('success', $result['message']);
        }

        header('Location: /musedock/plugins');
        exit;
    }

    /**
     * Activar un plugin
     */
    public function activate(int $id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        // Validar CSRF
        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
            exit;
        }

        $result = SuperadminPluginService::activate($id);

        if (!$result['success']) {
            flash('error', $result['message']);

            if (isset($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    flash('error', $error);
                }
            }
        } else {
            flash('success', $result['message']);
        }

        header('Location: /musedock/plugins');
        exit;
    }

    /**
     * Desactivar un plugin
     */
    public function deactivate(int $id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        // Validar CSRF
        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
            exit;
        }

        $result = SuperadminPluginService::deactivate($id);

        if (!$result['success']) {
            flash('error', $result['message']);

            if (isset($result['dependents'])) {
                flash('error', 'Dependientes activos: ' . implode(', ', $result['dependents']));
            }
        } else {
            flash('success', $result['message']);
        }

        header('Location: /musedock/plugins');
        exit;
    }

    /**
     * Subir e instalar plugin desde ZIP
     */
    public function upload()
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        // Validar CSRF
        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
            exit;
        }

        if (!isset($_FILES['plugin_file'])) {
            flash('error', 'No se recibió ningún archivo');
            header('Location: /musedock/plugins');
            exit;
        }

        $result = SuperadminPluginService::uploadAndInstall($_FILES['plugin_file']);

        if (!$result['success']) {
            flash('error', $result['message']);

            if (isset($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    flash('error', $error);
                }
            }
        } else {
            flash('success', $result['message']);
        }

        header('Location: /musedock/plugins');
        exit;
    }

    /**
     * Escanear directorio de plugins
     */
    public function scan()
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        // Validar CSRF
        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
            exit;
        }

        $discovered = SuperadminPluginService::scanPlugins();
        $count = count($discovered);

        flash('success', "Se encontraron {$count} plugin(s) en el directorio");

        header('Location: /musedock/plugins');
        exit;
    }
}
