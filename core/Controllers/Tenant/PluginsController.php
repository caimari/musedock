<?php
namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\TenantPluginManager;
use Screenart\Musedock\Security\AuditLogger;
use Screenart\Musedock\Logger;
use Screenart\Musedock\View;
use Screenart\Musedock\Helpers\FileUploadValidator;

use Screenart\Musedock\Traits\RequiresPermission;
/**
 * Controlador de Plugins por Tenant
 *
 * Gestiona plugins privados específicos de cada tenant
 */
class PluginsController
{
    use RequiresPermission;
use Screenart\Musedock\Security\SessionSecurity;

    /**
     * Listar todos los plugins del tenant
     */
    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        $tenantId = tenant_id();

        if (!$tenantId) {
            flash('error', 'No se pudo determinar el tenant actual.');
            header('Location: ' . admin_url('/dashboard'));
            exit;
        }

        try {
            $plugins = TenantPluginManager::getAllPlugins($tenantId);

            Logger::debug("PluginsController: Listando " . count($plugins) . " plugins para tenant {$tenantId}");

            return View::renderTenantAdmin('plugins.index', [
                'title' => 'Mis Plugins Privados',
                'plugins' => $plugins
            ]);

        } catch (\Exception $e) {
            Logger::error("PluginsController: Error al listar plugins", ['error' => $e->getMessage()]);
            flash('error', 'Error al cargar la lista de plugins.');
            header('Location: ' . admin_url('/dashboard'));
            exit;
        }
    }

    /**
     * Subir e instalar plugin desde archivo ZIP
     */
    public function upload()
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        $tenantId = tenant_id();

        if (!$tenantId) {
            flash('error', 'No se pudo determinar el tenant actual.');
            header('Location: ' . admin_url('/plugins'));
            exit;
        }

        if (!isset($_FILES['plugin_zip'])) {
            flash('error', 'No se seleccionó ningún archivo.');
            header('Location: ' . admin_url('/plugins'));
            exit;
        }

        $file = $_FILES['plugin_zip'];

        // 🔒 SECURITY: Validación completa del archivo ZIP incluyendo contenido
        // Previene: RCE, Path Traversal, archivos maliciosos
        $validation = FileUploadValidator::validateZip($file, 10 * 1024 * 1024, true);

        if (!$validation['valid']) {
            flash('error', $validation['error']);
            header('Location: ' . admin_url('/plugins'));
            exit;
        }

        // Instalar plugin
        Logger::info("PluginsController: Instalando plugin desde ZIP para tenant {$tenantId}");

        $result = TenantPluginManager::install($tenantId, $file['tmp_name']);

        if ($result['success']) {
            AuditLogger::log('plugin.installed', 'INFO', [
                'plugin_slug' => $result['slug'],
                'plugin_name' => $result['name']
            ]);

            flash('success', "Plugin '{$result['name']}' instalado correctamente. Actívalo para usarlo.");
        } else {
            flash('error', 'Error al instalar plugin: ' . $result['error']);
        }

        header('Location: ' . admin_url('/plugins'));
        exit;
    }

    /**
     * Activar/desactivar plugin
     */
    public function toggle($slug)
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        $tenantId = tenant_id();

        if (!$tenantId) {
            flash('error', 'No se pudo determinar el tenant actual.');
            header('Location: ' . admin_url('/plugins'));
            exit;
        }

        try {
            // Obtener estado actual
            $pdo = \Screenart\Musedock\Database::connect();
            $stmt = $pdo->prepare("SELECT * FROM tenant_plugins WHERE tenant_id = ? AND slug = ?");
            $stmt->execute([$tenantId, $slug]);
            $plugin = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$plugin) {
                flash('error', 'Plugin no encontrado.');
                header('Location: ' . admin_url('/plugins'));
                exit;
            }

            // Cambiar estado
            $newState = !$plugin['active'];
            TenantPluginManager::toggle($tenantId, $slug, $newState);

            $action = $newState ? 'activado' : 'desactivado';

            AuditLogger::log('plugin.' . ($newState ? 'enabled' : 'disabled'), 'INFO', [
                'plugin_slug' => $slug,
                'plugin_name' => $plugin['name']
            ]);

            flash('success', "Plugin '{$plugin['name']}' {$action} correctamente.");

        } catch (\Exception $e) {
            Logger::error("PluginsController: Error al cambiar estado del plugin {$slug}", ['error' => $e->getMessage()]);
            flash('error', 'Error al cambiar el estado del plugin.');
        }

        header('Location: ' . admin_url('/plugins'));
        exit;
    }

    /**
     * Desinstalar plugin
     */
    public function uninstall($slug)
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        $tenantId = tenant_id();

        if (!$tenantId) {
            flash('error', 'No se pudo determinar el tenant actual.');
            header('Location: ' . admin_url('/plugins'));
            exit;
        }

        try {
            // Obtener información del plugin antes de desinstalar
            $pdo = \Screenart\Musedock\Database::connect();
            $stmt = $pdo->prepare("SELECT * FROM tenant_plugins WHERE tenant_id = ? AND slug = ?");
            $stmt->execute([$tenantId, $slug]);
            $plugin = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$plugin) {
                flash('error', 'Plugin no encontrado.');
                header('Location: ' . admin_url('/plugins'));
                exit;
            }

            // Desinstalar
            if (TenantPluginManager::uninstall($tenantId, $slug)) {
                AuditLogger::log('plugin.uninstalled', 'WARNING', [
                    'plugin_slug' => $slug,
                    'plugin_name' => $plugin['name']
                ]);

                flash('success', "Plugin '{$plugin['name']}' desinstalado correctamente.");
            } else {
                flash('error', 'Error al desinstalar plugin.');
            }

        } catch (\Exception $e) {
            Logger::error("PluginsController: Error al desinstalar plugin {$slug}", ['error' => $e->getMessage()]);
            flash('error', 'Error al desinstalar plugin: ' . $e->getMessage());
        }

        header('Location: ' . admin_url('/plugins'));
        exit;
    }

    /**
     * Sincronizar plugins desde disco
     */
    public function sync()
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        $tenantId = tenant_id();

        if (!$tenantId) {
            flash('error', 'No se pudo determinar el tenant actual.');
            header('Location: ' . admin_url('/plugins'));
            exit;
        }

        try {
            Logger::info("PluginsController: Sincronizando plugins para tenant {$tenantId}");

            $results = TenantPluginManager::syncTenantPlugins($tenantId);

            $message = sprintf(
                'Sincronización completada: %d registrados, %d actualizados',
                count($results['registered']),
                count($results['updated'])
            );

            if (!empty($results['errors'])) {
                $message .= ', ' . count($results['errors']) . ' errores';
            }

            flash($results['errors'] ? 'warning' : 'success', $message);

            AuditLogger::log('plugins.synced', 'INFO', [
                'results' => $results
            ]);

        } catch (\Exception $e) {
            Logger::error("PluginsController: Error al sincronizar plugins", ['error' => $e->getMessage()]);
            flash('error', 'Error al sincronizar plugins: ' . $e->getMessage());
        }

        header('Location: ' . admin_url('/plugins'));
        exit;
    }
}
