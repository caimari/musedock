<?php
namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\TenantPluginManager;
use Screenart\Musedock\Security\AuditLogger;
use Screenart\Musedock\Security\SessionSecurity;
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

            // Register/unregister plugin menus in tenant_menus
            $this->syncPluginMenus($tenantId, $slug, $newState);

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

            // Remove menus before uninstall
            $this->syncPluginMenus($tenantId, $slug, false);

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

    /**
     * Download plugin as ZIP backup
     */
    public function download($slug)
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        $tenantId = tenant_id();
        if (!$tenantId) {
            flash('error', 'No se pudo determinar el tenant actual.');
            header('Location: ' . admin_url('/plugins'));
            exit;
        }

        $pluginDir = TenantPluginManager::resolvePluginPath($tenantId, $slug);
        if (!$pluginDir || !is_dir($pluginDir)) {
            flash('error', 'Plugin no encontrado en disco.');
            header('Location: ' . admin_url('/plugins'));
            exit;
        }

        $zipName = $slug . '_backup_' . date('Ymd_His') . '.zip';
        $zipPath = sys_get_temp_dir() . '/' . $zipName;

        try {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \Exception('No se pudo crear el archivo ZIP.');
            }

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($pluginDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = $slug . '/' . substr($filePath, strlen($pluginDir) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }

            $zip->close();

            // Send file
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zipName . '"');
            header('Content-Length: ' . filesize($zipPath));
            header('Cache-Control: no-cache, must-revalidate');
            readfile($zipPath);
            @unlink($zipPath);
            exit;

        } catch (\Exception $e) {
            @unlink($zipPath);
            Logger::error("PluginsController: Error al descargar plugin {$slug}", ['error' => $e->getMessage()]);
            flash('error', 'Error al crear backup: ' . $e->getMessage());
            header('Location: ' . admin_url('/plugins'));
            exit;
        }
    }

    /**
     * Sync plugin menus in tenant_menus when plugin is toggled.
     * Reads admin_menu from plugin.json and inserts/removes entries.
     */
    private function syncPluginMenus(int $tenantId, string $slug, bool $activate): void
    {
        try {
            $pluginDir = TenantPluginManager::resolvePluginPath($tenantId, $slug);
            if (!$pluginDir) return;

            $metaFile = $pluginDir . '/plugin.json';
            if (!file_exists($metaFile)) return;

            $meta = json_decode(file_get_contents($metaFile), true);
            if (empty($meta['admin_menu'])) return;

            $pdo = \Screenart\Musedock\Database::connect();
            $menu = $meta['admin_menu'];
            $parentSlug = $menu['slug'] ?? ('plugin-' . $slug);

            if ($activate) {
                // Check if already registered
                $check = $pdo->prepare("SELECT id FROM tenant_menus WHERE tenant_id = ? AND slug = ?");
                $check->execute([$tenantId, $parentSlug]);
                if ($check->fetch()) return; // Already exists

                // Insert parent
                $stmt = $pdo->prepare("
                    INSERT INTO tenant_menus (tenant_id, parent_id, module_id, title, slug, url, icon, icon_type, order_position, is_active, created_at)
                    VALUES (?, NULL, NULL, ?, ?, ?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([
                    $tenantId,
                    $menu['title'] ?? $meta['name'],
                    $parentSlug,
                    $menu['url'] ?? '#',
                    $menu['icon'] ?? 'bi-puzzle',
                    $menu['icon_type'] ?? 'bi',
                    $menu['order'] ?? 50,
                ]);
                $parentId = $pdo->lastInsertId();

                // Insert children
                if (!empty($menu['children'])) {
                    $childStmt = $pdo->prepare("
                        INSERT INTO tenant_menus (tenant_id, parent_id, module_id, title, slug, url, icon, icon_type, order_position, is_active, created_at)
                        VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, 1, NOW())
                    ");
                    foreach ($menu['children'] as $child) {
                        $childStmt->execute([
                            $tenantId,
                            $parentId,
                            $child['title'],
                            $child['slug'] ?? ($parentSlug . '-' . uniqid()),
                            $child['url'] ?? '#',
                            $child['icon'] ?? 'bi-circle',
                            $child['icon_type'] ?? 'bi',
                            $child['order'] ?? 0,
                        ]);
                    }
                }

                Logger::info("PluginsController: Registered menus for plugin {$slug} (tenant {$tenantId})");

            } else {
                // Deactivate: remove all menu entries for this plugin
                // First get parent ID
                $parentStmt = $pdo->prepare("SELECT id FROM tenant_menus WHERE tenant_id = ? AND slug = ?");
                $parentStmt->execute([$tenantId, $parentSlug]);
                $parent = $parentStmt->fetch(\PDO::FETCH_ASSOC);

                if ($parent) {
                    // Delete children first
                    $pdo->prepare("DELETE FROM tenant_menus WHERE tenant_id = ? AND parent_id = ?")
                        ->execute([$tenantId, $parent['id']]);
                    // Delete parent
                    $pdo->prepare("DELETE FROM tenant_menus WHERE tenant_id = ? AND id = ?")
                        ->execute([$tenantId, $parent['id']]);

                    Logger::info("PluginsController: Removed menus for plugin {$slug} (tenant {$tenantId})");
                }
            }
        } catch (\Exception $e) {
            Logger::error("PluginsController: Error syncing menus for plugin {$slug}", ['error' => $e->getMessage()]);
        }
    }
}
