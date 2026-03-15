<?php
namespace AIImage;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;

class AdminController
{
    private $isSuperAdmin = false;
    private $tenantId = null;

    public function __construct()
    {
        $this->isSuperAdmin = isset($_SESSION['super_admin']);
        $this->tenantId = function_exists('tenant_id') ? tenant_id() : null;
    }

    public function settings()
    {
        $providers = [];

        try {
            $pdo = Database::connect();

            if ($this->tenantId !== null) {
                $stmt = $pdo->prepare("SELECT * FROM ai_image_providers WHERE active = 1 AND (tenant_id = ? OR (tenant_id IS NULL AND system_wide = 1)) ORDER BY name ASC");
                $stmt->execute([$this->tenantId]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM ai_image_providers WHERE tenant_id IS NULL ORDER BY name ASC");
                $stmt->execute();
            }

            $providers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            Logger::error("Error en AIImage settings: " . $e->getMessage());
            if (function_exists('flash')) {
                flash('error', 'Error al cargar la configuracion: ' . $e->getMessage());
            }
        }

        $viewName = 'aiimage.settings';
        $renderMethod = $this->isSuperAdmin ? 'renderSuperadmin' : 'renderTenantAdmin';

        if (method_exists(View::class, $renderMethod)) {
            return View::$renderMethod($viewName, [
                'title' => 'Configuracion de AI Image',
                'providers' => $providers,
                'tenant_id' => $this->tenantId
            ]);
        } else {
            return "Error: Vista no disponible.";
        }
    }

    public function updateSettings()
    {
        try {
            $action = $_POST['action'] ?? '';
            $pdo = Database::connect();

            if ($action === 'create_provider') {
                $name = trim($_POST['provider_name'] ?? '');
                $type = $_POST['provider_type'] ?? 'openai';
                $apiKey = trim($_POST['api_key'] ?? '');
                $model = trim($_POST['model'] ?? '');
                $endpoint = trim($_POST['endpoint'] ?? '');
                $active = isset($_POST['active']) ? 1 : 0;
                $systemWide = isset($_POST['system_wide']) ? 1 : 0;

                if (empty($name)) {
                    throw new \Exception('El nombre del proveedor es obligatorio');
                }

                $stmt = $pdo->prepare("INSERT INTO ai_image_providers (name, provider_type, api_key, model, endpoint, active, system_wide, tenant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $name, $type, $apiKey ?: null, $model ?: null, $endpoint ?: null,
                    $active, $systemWide, $this->tenantId
                ]);

                if (function_exists('flash')) {
                    flash('success', 'Proveedor de imagen creado correctamente');
                }

            } elseif ($action === 'update_provider') {
                $id = (int)($_POST['provider_id'] ?? 0);
                $name = trim($_POST['provider_name'] ?? '');
                $type = $_POST['provider_type'] ?? 'openai';
                $apiKey = trim($_POST['api_key'] ?? '');
                $model = trim($_POST['model'] ?? '');
                $endpoint = trim($_POST['endpoint'] ?? '');
                $active = isset($_POST['active']) ? 1 : 0;
                $systemWide = isset($_POST['system_wide']) ? 1 : 0;

                $stmt = $pdo->prepare("UPDATE ai_image_providers SET name = ?, provider_type = ?, api_key = ?, model = ?, endpoint = ?, active = ?, system_wide = ? WHERE id = ?");
                $stmt->execute([
                    $name, $type, $apiKey ?: null, $model ?: null, $endpoint ?: null,
                    $active, $systemWide, $id
                ]);

                if (function_exists('flash')) {
                    flash('success', 'Proveedor de imagen actualizado correctamente');
                }

            } elseif ($action === 'delete_provider') {
                $id = (int)($_POST['provider_id'] ?? 0);
                $stmt = $pdo->prepare("DELETE FROM ai_image_providers WHERE id = ?");
                $stmt->execute([$id]);

                if (function_exists('flash')) {
                    flash('success', 'Proveedor de imagen eliminado');
                }
            }

        } catch (\Throwable $e) {
            Logger::error("Error en AIImage updateSettings: " . $e->getMessage());
            if (function_exists('flash')) {
                flash('error', 'Error: ' . $e->getMessage());
            }
        }

        $adminBaseUrl = $this->isSuperAdmin ? '/musedock' : (function_exists('admin_url') ? admin_url() : '/admin');
        $redirectUrl = rtrim($adminBaseUrl, '/') . '/ai-image/settings';

        header('Location: ' . $redirectUrl);
        exit;
    }
}
