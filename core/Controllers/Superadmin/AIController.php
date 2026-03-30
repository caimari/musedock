<?php
namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Services\AI\Models\Provider;
use Screenart\Musedock\Services\AI\Models\Usage;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\Security\SessionSecurity;

class AIController
{
    use RequiresPermission;

    public function __construct()
    {
        // Migrate stale direct-session messages to flash system
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        foreach (['error', 'success', 'warning'] as $key) {
            if (isset($_SESSION[$key]) && !isset($_SESSION['_flash'][$key])) {
                flash($key, $_SESSION[$key]);
            }
            unset($_SESSION[$key]);
        }
    }

    /**
     * Página principal de IA
     */
    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('advanced.ai');

        // Obtener estadísticas de uso si existen las clases necesarias
        try {
            $stats = Usage::getStats();
        } catch (\Exception $e) {
            $stats = [];
        }

        // Obtener uso por tenant
        try {
            $tenantStats = Database::query("
                SELECT t.domain as tenant_domain,
                       COUNT(*) as requests,
                       COALESCE(SUM(l.tokens_used), 0) as tokens
                FROM ai_usage_logs l
                JOIN tenants t ON l.tenant_id = t.id
                WHERE l.tenant_id IS NOT NULL
                GROUP BY t.id, t.domain
                ORDER BY tokens DESC
            ")->fetchAll();
        } catch (\Exception $e) {
            $tenantStats = [];
        }

        // Usando renderSuperadmin en lugar de render
        return View::renderSuperadmin('ai.index', [
            'title' => 'Inteligencia Artificial',
            'stats' => $stats,
            'tenantStats' => $tenantStats
        ]);
    }
    
    /**
     * Listado de proveedores
     */
    public function providers()
    {
        SessionSecurity::startSession();
        $this->checkPermission('advanced.ai');

        try {
            $providers = Database::query("SELECT * FROM ai_providers WHERE tenant_id IS NULL ORDER BY name ASC")->fetchAll();
        } catch (\Exception $e) {
            $providers = [];
        }
        
        return View::renderSuperadmin('ai.providers.index', [
            'title' => 'Proveedores de IA',
            'providers' => $providers
        ]);
    }
    
    /**
     * Formulario para crear proveedor
     */
    public function createProvider()
    {
        SessionSecurity::startSession();
        $this->checkPermission('advanced.ai');

        return View::renderSuperadmin('ai.providers.create', [
            'title' => 'Nuevo Proveedor de IA'
        ]);
    }
    
    /**
     * Guardar nuevo proveedor
     */
    public function storeProvider()
    {
        SessionSecurity::startSession();
        $this->checkPermission('advanced.ai');

        // Validar datos
        if (empty($_POST['name']) || empty($_POST['provider_type'])) {
            flash('error', 'Nombre y tipo de proveedor son obligatorios');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/musedock/ai/providers'));
            exit;
        }

        // Crear proveedor
        $sql = "INSERT INTO ai_providers (name, api_key, endpoint, provider_type, model, temperature, max_tokens, active, system_wide, tenant_id, created_at, updated_at)
                VALUES (:name, :api_key, :endpoint, :provider_type, :model, :temperature, :max_tokens, :active, :system_wide, :tenant_id, NOW(), NOW())";

        $params = [
            'name' => $_POST['name'],
            'api_key' => $_POST['api_key'] ?? null,
            'endpoint' => $_POST['endpoint'] ?? null,
            'provider_type' => $_POST['provider_type'],
            'model' => $_POST['model'] ?? null,
            'temperature' => $_POST['temperature'] ?? 0.7,
            'max_tokens' => $_POST['max_tokens'] ?? 1000,
            'active' => isset($_POST['active']) ? 1 : 0,
            'system_wide' => isset($_POST['system_wide']) ? 1 : 0,
            'tenant_id' => null
        ];

        try {
            Database::query($sql, $params);
            flash('success', 'Proveedor creado correctamente');
        } catch (\Exception $e) {
            flash('error', 'Error al crear el proveedor: ' . $e->getMessage());
        }
        
        header('Location: /musedock/ai/providers');
        exit;
    }
    
    /**
     * Formulario para editar proveedor
     */
    public function editProvider($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('advanced.ai');

        $provider = Database::query("SELECT * FROM ai_providers WHERE id = :id", ['id' => $id])->fetch();
        
        if (!$provider) {
            flash('error', 'Proveedor no encontrado');
            header('Location: /musedock/ai/providers');
            exit;
        }

        return View::renderSuperadmin('ai.providers.edit', [
            'title' => 'Editar Proveedor de IA',
            'provider' => $provider
        ]);
    }
    
    /**
     * Actualizar proveedor
     */
    public function updateProvider($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('advanced.ai');

        // Validar datos
        if (empty($_POST['name']) || empty($_POST['provider_type'])) {
            flash('error', 'Nombre y tipo de proveedor son obligatorios');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/musedock/ai/providers'));
            exit;
        }

        // Actualizar proveedor (solo actualizar api_key si se proporciona una nueva)
        $newApiKey = trim($_POST['api_key'] ?? '');
        $apiKeyClause = !empty($newApiKey) ? 'api_key = :api_key,' : '';

        $sql = "UPDATE ai_providers SET
                name = :name,
                {$apiKeyClause}
                endpoint = :endpoint,
                provider_type = :provider_type,
                model = :model,
                temperature = :temperature,
                max_tokens = :max_tokens,
                active = :active,
                system_wide = :system_wide,
                updated_at = NOW()
                WHERE id = :id";

        $params = [
            'id' => $id,
            'name' => $_POST['name'],
            'endpoint' => $_POST['endpoint'] ?? null,
            'provider_type' => $_POST['provider_type'],
            'model' => $_POST['model'] ?? null,
            'temperature' => $_POST['temperature'] ?? 0.7,
            'max_tokens' => $_POST['max_tokens'] ?? 1000,
            'active' => isset($_POST['active']) ? 1 : 0,
            'system_wide' => isset($_POST['system_wide']) ? 1 : 0
        ];

        if (!empty($newApiKey)) {
            $params['api_key'] = $newApiKey;
        }
        
        try {
            Database::query($sql, $params);
            flash('success', 'Proveedor actualizado correctamente');
        } catch (\Exception $e) {
            flash('error', 'Error al actualizar el proveedor: ' . $e->getMessage());
        }
        
        header('Location: /musedock/ai/providers');
        exit;
    }
    
    /**
     * Activar/desactivar proveedor
     */
    public function toggleProvider($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('advanced.ai');

        try {
            $provider = Database::query("SELECT * FROM ai_providers WHERE id = :id", ['id' => $id])->fetch();
            
            if (!$provider) {
                flash('error', 'Proveedor no encontrado');
                header('Location: /musedock/ai/providers');
                exit;
            }

            $newState = $provider['active'] ? 0 : 1;

            Database::query(
                "UPDATE ai_providers SET active = :active, updated_at = NOW() WHERE id = :id",
                ['id' => $id, 'active' => $newState]
            );

            flash('success', 'Estado del proveedor actualizado');
        } catch (\Exception $e) {
            flash('error', 'Error al cambiar el estado: ' . $e->getMessage());
        }
        
        header('Location: /musedock/ai/providers');
        exit;
    }
    
    /**
     * Eliminar proveedor
     */
    public function deleteProvider($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('advanced.ai');

        try {
            $provider = Database::query("SELECT * FROM ai_providers WHERE id = :id", ['id' => $id])->fetch();
            
            if (!$provider) {
                flash('error', 'Proveedor no encontrado');
                header('Location: /musedock/ai/providers');
                exit;
            }

            // Comprobar si hay logs que hacen referencia a este proveedor
            $usageCount = Database::query(
                "SELECT COUNT(*) FROM ai_usage_logs WHERE provider_id = :id",
                ['id' => $id]
            )->fetchColumn();

            if ($usageCount > 0) {
                flash('error', 'No se puede eliminar el proveedor porque tiene registros de uso asociados');
                header('Location: /musedock/ai/providers');
                exit;
            }

            Database::query("DELETE FROM ai_providers WHERE id = :id", ['id' => $id]);

            flash('success', 'Proveedor eliminado correctamente');
        } catch (\Exception $e) {
            flash('error', 'Error al eliminar: ' . $e->getMessage());
        }
        
        header('Location: /musedock/ai/providers');
        exit;
    }
    
    /**
     * Ver logs de uso
     */
    public function logs()
    {
        SessionSecurity::startSession();
        $this->checkPermission('advanced.ai');

        $filters = [
            'provider_id' => $_GET['provider_id'] ?? null,
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null,
            'module' => $_GET['module'] ?? null
        ];
        
        $page = (int)($_GET['page'] ?? 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;
        
        try {
            // Construir consulta para los logs
            $sql = "SELECT l.*, p.name as provider_name, p.provider_type,
                           t.domain as tenant_domain,
                           a.name as admin_name, a.email as admin_email
                    FROM ai_usage_logs l
                    LEFT JOIN ai_providers p ON l.provider_id = p.id
                    LEFT JOIN tenants t ON l.tenant_id = t.id
                    LEFT JOIN admins a ON l.user_id = a.id AND l.user_type IN ('admin', 'system')
                    WHERE 1=1";
            
            $params = [];
            
            if (!empty($filters['provider_id'])) {
                $sql .= " AND l.provider_id = :provider_id";
                $params['provider_id'] = $filters['provider_id'];
            }
            
            if (!empty($filters['date_from'])) {
                $sql .= " AND l.created_at >= :date_from";
                $params['date_from'] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $sql .= " AND l.created_at <= :date_to";
                $params['date_to'] = $filters['date_to'];
            }
            
            if (!empty($filters['module'])) {
                $sql .= " AND l.module = :module";
                $params['module'] = $filters['module'];
            }
            
            $sql .= " ORDER BY l.created_at DESC LIMIT :limit OFFSET :offset";
            $params['limit'] = $limit;
            $params['offset'] = $offset;
            
            $logs = Database::query($sql, $params)->fetchAll();
            
            // Obtener proveedores para filtros
            $providers = Database::query("SELECT * FROM ai_providers WHERE active = 1 ORDER BY name ASC")->fetchAll();
        } catch (\Exception $e) {
            $logs = [];
            $providers = [];
        }
        
        return View::renderSuperadmin('ai.logs', [
            'title' => 'Logs de uso de IA',
            'logs' => $logs,
            'providers' => $providers,
            'filters' => $filters,
            'page' => $page
        ]);
    }
    
    /**
     * Configuración del sistema de IA
     */
    public function settings()
    {
        SessionSecurity::startSession();
        $this->checkPermission('advanced.ai');

        try {
            // Obtener configuraciones actuales
            $settingsData = Database::query(
                "SELECT setting_key, setting_value FROM ai_settings WHERE tenant_id IS NULL"
            )->fetchAll();
            
            $settings = [];
            foreach ($settingsData as $setting) {
                $settings[$setting['setting_key']] = $setting['setting_value'];
            }
            
            // Valores por defecto si no existen
            $settings = array_merge([
                'ai_daily_token_limit' => '0',
                'ai_log_all_prompts' => '1',
                'ai_default_provider' => '1'
            ], $settings);
            
            // Obtener proveedores activos
            $providers = Database::query(
                "SELECT * FROM ai_providers WHERE active = 1 ORDER BY name ASC"
            )->fetchAll();

            // Obtener tenants activos con sus cuotas de IA
            $tenants = Database::query(
                "SELECT id, name, domain FROM tenants WHERE status = 'active' ORDER BY name ASC"
            )->fetchAll();

            $tenantQuotas = [];
            foreach ($tenants as $t) {
                $quota = Database::query(
                    "SELECT setting_value FROM ai_settings WHERE setting_key = 'ai_daily_token_limit' AND tenant_id = :tid",
                    ['tid' => $t['id']]
                )->fetchColumn();
                $tenantQuotas[$t['id']] = $quota !== false ? $quota : null;
            }
        } catch (\Exception $e) {
            $settings = [
                'ai_daily_token_limit' => '0',
                'ai_log_all_prompts' => '1',
                'ai_default_provider' => '1'
            ];
            $providers = [];
            $tenants = [];
            $tenantQuotas = [];
        }

        return View::renderSuperadmin('ai.settings', [
            'title' => 'Configuración de IA',
            'settings' => $settings,
            'providers' => $providers,
            'tenants' => $tenants,
            'tenantQuotas' => $tenantQuotas
        ]);
    }
    
    /**
     * Actualizar configuración
     */
    public function updateSettings()
    {
        SessionSecurity::startSession();
        $this->checkPermission('advanced.ai');

        try {
            // Configuraciones a actualizar
            $settings = [
                'ai_daily_token_limit' => $_POST['daily_token_limit'] ?? 0,
                'ai_log_all_prompts' => isset($_POST['log_all_prompts']) ? 1 : 0,
                'ai_default_provider' => $_POST['default_provider'] ?? 1
            ];
            
            // Actualizar cada configuración
            foreach ($settings as $key => $value) {
                // Verificar si ya existe
                $exists = Database::query(
                    "SELECT COUNT(*) FROM ai_settings WHERE setting_key = :key AND tenant_id IS NULL",
                    ['key' => $key]
                )->fetchColumn();
                
                if ($exists) {
                    // Actualizar
                    Database::query(
                        "UPDATE ai_settings SET setting_value = :value, updated_at = NOW() 
                         WHERE setting_key = :key AND tenant_id IS NULL",
                        ['key' => $key, 'value' => $value]
                    );
                } else {
                    // Insertar
                    Database::query(
                        "INSERT INTO ai_settings (setting_key, setting_value, tenant_id, created_at, updated_at)
                         VALUES (:key, :value, NULL, NOW(), NOW())",
                        ['key' => $key, 'value' => $value]
                    );
                }
            }
            
            flash('success', 'Configuración actualizada correctamente');
        } catch (\Exception $e) {
            flash('error', 'Error al actualizar la configuración: ' . $e->getMessage());
        }
        
        header('Location: /musedock/ai/settings');
        exit;
    }

    /**
     * Actualizar cuota diaria de tokens para un tenant específico
     */
    public function updateTenantQuota()
    {
        SessionSecurity::startSession();
        $this->checkPermission('advanced.ai');

        try {
            $tenantId = (int) ($_POST['tenant_id'] ?? 0);
            $limit = (int) ($_POST['token_limit'] ?? 0);

            if ($tenantId <= 0) {
                throw new \Exception('Tenant no válido');
            }

            $key = 'ai_daily_token_limit';
            $exists = Database::query(
                "SELECT COUNT(*) FROM ai_settings WHERE setting_key = :key AND tenant_id = :tid",
                ['key' => $key, 'tid' => $tenantId]
            )->fetchColumn();

            if ($limit <= 0) {
                // Si ponen 0 o vacío, eliminar la config específica (usará la global)
                if ($exists) {
                    Database::query(
                        "DELETE FROM ai_settings WHERE setting_key = :key AND tenant_id = :tid",
                        ['key' => $key, 'tid' => $tenantId]
                    );
                }
            } elseif ($exists) {
                Database::query(
                    "UPDATE ai_settings SET setting_value = :value, updated_at = NOW()
                     WHERE setting_key = :key AND tenant_id = :tid",
                    ['key' => $key, 'value' => $limit, 'tid' => $tenantId]
                );
            } else {
                Database::query(
                    "INSERT INTO ai_settings (setting_key, setting_value, tenant_id, created_at, updated_at)
                     VALUES (:key, :value, :tid, NOW(), NOW())",
                    ['key' => $key, 'value' => $limit, 'tid' => $tenantId]
                );
            }

            flash('success', 'Cuota del tenant actualizada correctamente');
        } catch (\Exception $e) {
            flash('error', 'Error al actualizar cuota: ' . $e->getMessage());
        }

        header('Location: /musedock/ai/settings');
        exit;
    }
}
