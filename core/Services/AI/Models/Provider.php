<?php
namespace Screenart\Musedock\Services\AI\Models;

use Screenart\Musedock\Database;

/**
 * Modelo para gestionar proveedores de IA
 */
class Provider
{
    /**
     * Obtiene un proveedor por su ID
     * 
     * @param int $id ID del proveedor
     * @param int|null $tenantId ID del tenant o null para global
     * @return array|null Datos del proveedor o null si no existe
     */
    public static function getById($id, $tenantId = null)
    {
        $tenantCondition = "";
        $params = ['id' => $id];
        
        if ($tenantId !== null) {
            $tenantCondition = "AND (tenant_id = :tenant_id OR tenant_id IS NULL)";
            $params['tenant_id'] = $tenantId;
        } else {
            $tenantCondition = "AND tenant_id IS NULL";
        }
        
        return Database::query("
            SELECT * FROM ai_providers
            WHERE id = :id {$tenantCondition}
            LIMIT 1
        ", $params)->fetch();
    }
    
    /**
     * Obtiene todos los proveedores disponibles
     * 
     * @param int|null $tenantId ID del tenant o null para globales
     * @param bool $activeOnly Solo proveedores activos
     * @return array Lista de proveedores
     */
    public static function getAll($tenantId = null, $activeOnly = false)
    {
        $tenantCondition = "";
        $activeCondition = $activeOnly ? "AND active = 1" : "";
        $params = [];
        
        if ($tenantId !== null) {
            $tenantCondition = "WHERE (tenant_id = :tenant_id OR (tenant_id IS NULL AND system_wide = 1))";
            $params['tenant_id'] = $tenantId;
        } else {
            $tenantCondition = "WHERE tenant_id IS NULL";
        }
        
        return Database::query("
            SELECT * FROM ai_providers
            {$tenantCondition} {$activeCondition}
            ORDER BY name ASC
        ", $params)->fetchAll();
    }
    
    /**
     * Obtiene un proveedor por defecto
     * 
     * @param int|null $tenantId ID del tenant o null para global
     * @return array|null Datos del proveedor o null si no hay ninguno activo
     */
    public static function getDefault($tenantId = null)
    {
        // Primero, obtener el ID del proveedor por defecto de la configuración
        $settingKey = 'ai_default_provider';
        $params = ['key' => $settingKey];
        $tenantCondition = "";
        
        if ($tenantId !== null) {
            $tenantCondition = "AND tenant_id = :tenant_id";
            $params['tenant_id'] = $tenantId;
        } else {
            $tenantCondition = "AND tenant_id IS NULL";
        }
        
        $defaultProviderId = Database::query("
            SELECT setting_value FROM ai_settings
            WHERE setting_key = :key {$tenantCondition}
        ", $params)->fetchColumn();
        
        // Si no hay configuración, obtener cualquier proveedor activo
        if (!$defaultProviderId) {
            $tenantCondition = "";
            $params = [];
            
            if ($tenantId !== null) {
                $tenantCondition = "WHERE (tenant_id = :tenant_id OR (tenant_id IS NULL AND system_wide = 1)) AND active = 1";
                $params['tenant_id'] = $tenantId;
            } else {
                $tenantCondition = "WHERE tenant_id IS NULL AND active = 1";
            }
            
            return Database::query("
                SELECT * FROM ai_providers
                {$tenantCondition}
                LIMIT 1
            ", $params)->fetch();
        }
        
        // Obtener el proveedor por ID
        return self::getById($defaultProviderId, $tenantId);
    }
    
    /**
     * Crea un nuevo proveedor
     * 
     * @param array $data Datos del proveedor
     * @return int ID del proveedor creado
     */
    public static function create($data)
    {
        $id = Database::table('ai_providers')->insertGetId([
            'name' => $data['name'],
            'api_key' => $data['api_key'] ?? null,
            'endpoint' => $data['endpoint'] ?? null,
            'provider_type' => $data['provider_type'],
            'model' => $data['model'] ?? null,
            'temperature' => $data['temperature'] ?? 0.7,
            'max_tokens' => $data['max_tokens'] ?? 1000,
            'active' => $data['active'] ?? 0,
            'system_wide' => $data['system_wide'] ?? 0,
            'tenant_id' => $data['tenant_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        return $id;
    }
    
    /**
     * Actualiza un proveedor existente
     * 
     * @param int $id ID del proveedor
     * @param array $data Datos a actualizar
     * @return bool Éxito
     */
    public static function update($id, $data)
    {
        return Database::table('ai_providers')
            ->where('id', $id)
            ->update(array_merge($data, [
                'updated_at' => date('Y-m-d H:i:s')
            ]));
    }
    
    /**
     * Elimina un proveedor
     * 
     * @param int $id ID del proveedor
     * @return bool Éxito
     */
    public static function delete($id)
    {
        return Database::table('ai_providers')
            ->where('id', $id)
            ->delete();
    }
}