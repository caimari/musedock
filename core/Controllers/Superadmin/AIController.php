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
        
        // Usando renderSuperadmin en lugar de render
        return View::renderSuperadmin('ai.index', [
            'title' => 'Inteligencia Artificial',
            'stats' => $stats
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
            return back();
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
        
        return redirect('/musedock/ai/providers');
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
            return redirect('/musedock/ai/providers');
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
            return back();
        }
        
        // Actualizar proveedor
        $sql = "UPDATE ai_providers SET 
                name = :name,
                api_key = :api_key,
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
            'api_key' => $_POST['api_key'] ?? null,
            'endpoint' => $_POST['endpoint'] ?? null,
            'provider_type' => $_POST['provider_type'],
            'model' => $_POST['model'] ?? null,
            'temperature' => $_POST['temperature'] ?? 0.7,
            'max_tokens' => $_POST['max_tokens'] ?? 1000,
            'active' => isset($_POST['active']) ? 1 : 0,
            'system_wide' => isset($_POST['system_wide']) ? 1 : 0
        ];
        
        try {
            Database::query($sql, $params);
            flash('success', 'Proveedor actualizado correctamente');
        } catch (\Exception $e) {
            flash('error', 'Error al actualizar el proveedor: ' . $e->getMessage());
        }
        
        return redirect('/musedock/ai/providers');
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
                return redirect('/musedock/ai/providers');
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
        
        return redirect('/musedock/ai/providers');
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
                return redirect('/musedock/ai/providers');
            }
            
            // Comprobar si hay logs que hacen referencia a este proveedor
            $usageCount = Database::query(
                "SELECT COUNT(*) FROM ai_usage_logs WHERE provider_id = :id", 
                ['id' => $id]
            )->fetchColumn();
            
            if ($usageCount > 0) {
                flash('error', 'No se puede eliminar el proveedor porque tiene registros de uso asociados');
                return redirect('/musedock/ai/providers');
            }
            
            Database::query("DELETE FROM ai_providers WHERE id = :id", ['id' => $id]);
            
            flash('success', 'Proveedor eliminado correctamente');
        } catch (\Exception $e) {
            flash('error', 'Error al eliminar: ' . $e->getMessage());
        }
        
        return redirect('/musedock/ai/providers');
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
            $sql = "SELECT l.*, p.name as provider_name, p.provider_type 
                    FROM ai_usage_logs l
                    LEFT JOIN ai_providers p ON l.provider_id = p.id
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
            
            $sql .= " ORDER BY l.created_at DESC LIMIT :offset, :limit";
            $params['offset'] = $offset;
            $params['limit'] = $limit;
            
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
        } catch (\Exception $e) {
            $settings = [
                'ai_daily_token_limit' => '0',
                'ai_log_all_prompts' => '1',
                'ai_default_provider' => '1'
            ];
            $providers = [];
        }
        
        return View::renderSuperadmin('ai.settings', [
            'title' => 'Configuración de IA',
            'settings' => $settings,
            'providers' => $providers
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
        
        return redirect('/musedock/ai/settings');
    }
}
