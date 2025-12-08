<?php
namespace Screenart\Musedock\Services\AI\Models;

use Screenart\Musedock\Database;

/**
 * Modelo para gestionar logs de uso de IA
 */
class Usage
{
    /**
     * Registra un uso de IA
     * 
     * @param array $data Datos del uso
     * @return int ID del registro
     */
    public static function log($data)
    {
        // Verificar si debemos guardar el prompt completo
        $saveFullPrompt = self::getSetting('ai_log_all_prompts', true, $data['tenant_id'] ?? null);
        
        // Si no guardamos el prompt completo, truncarlo
        if (!$saveFullPrompt && isset($data['prompt'])) {
            $data['prompt'] = substr($data['prompt'], 0, 100) . '...';
        }
        
        $id = Database::table('ai_usage_logs')->insertGetId([
            'provider_id' => $data['provider_id'] ?? null,
            'prompt' => $data['prompt'] ?? null,
            'tokens_used' => $data['tokens_used'] ?? 0,
            'status' => $data['status'] ?? 'success',
            'user_id' => $data['user_id'] ?? null,
            'user_type' => $data['user_type'] ?? null,
            'module' => $data['module'] ?? null,
            'action' => $data['action'] ?? null,
            'tenant_id' => $data['tenant_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return $id;
    }
    
    /**
     * Obtiene los logs de uso
     * 
     * @param array $filters Filtros a aplicar
     * @param int $limit Límite de resultados
     * @param int $offset Desplazamiento
     * @return array Resultados
     */
    public static function getLogs($filters = [], $limit = 50, $offset = 0)
    {
        $where = [];
        $params = [];
        
// Aplicar filtros
       if (!empty($filters['tenant_id'])) {
           $where[] = "tenant_id = :tenant_id";
           $params['tenant_id'] = $filters['tenant_id'];
       }
       
       if (!empty($filters['provider_id'])) {
           $where[] = "provider_id = :provider_id";
           $params['provider_id'] = $filters['provider_id'];
       }
       
       if (!empty($filters['user_id'])) {
           $where[] = "user_id = :user_id";
           $params['user_id'] = $filters['user_id'];
       }
       
       if (!empty($filters['user_type'])) {
           $where[] = "user_type = :user_type";
           $params['user_type'] = $filters['user_type'];
       }
       
       if (!empty($filters['module'])) {
           $where[] = "module = :module";
           $params['module'] = $filters['module'];
       }
       
       if (!empty($filters['action'])) {
           $where[] = "action = :action";
           $params['action'] = $filters['action'];
       }
       
       if (!empty($filters['status'])) {
           $where[] = "status LIKE :status";
           $params['status'] = "%{$filters['status']}%";
       }
       
       if (!empty($filters['date_from'])) {
           $where[] = "created_at >= :date_from";
           $params['date_from'] = $filters['date_from'];
       }
       
       if (!empty($filters['date_to'])) {
           $where[] = "created_at <= :date_to";
           $params['date_to'] = $filters['date_to'];
       }
       
       // Construir condición WHERE
       $whereClause = empty($where) ? "" : "WHERE " . implode(" AND ", $where);
       
       // Obtener registros
       $params['limit'] = $limit;
       $params['offset'] = $offset;
       
       return Database::query("
           SELECT l.*, p.name as provider_name, p.provider_type 
           FROM ai_usage_logs l
           LEFT JOIN ai_providers p ON l.provider_id = p.id
           {$whereClause}
           ORDER BY l.created_at DESC
           LIMIT :offset, :limit
       ", $params)->fetchAll();
   }
   
   /**
    * Obtiene estadísticas de uso
    * 
    * @param array $filters Filtros a aplicar
    * @return array Estadísticas
    */
   public static function getStats($filters = [])
   {
       $where = [];
       $params = [];
       
       // Aplicar filtros
       if (!empty($filters['tenant_id'])) {
           $where[] = "tenant_id = :tenant_id";
           $params['tenant_id'] = $filters['tenant_id'];
       }
       
       if (!empty($filters['provider_id'])) {
           $where[] = "provider_id = :provider_id";
           $params['provider_id'] = $filters['provider_id'];
       }
       
       if (!empty($filters['user_id'])) {
           $where[] = "user_id = :user_id";
           $params['user_id'] = $filters['user_id'];
       }
       
       if (!empty($filters['date_from'])) {
           $where[] = "created_at >= :date_from";
           $params['date_from'] = $filters['date_from'];
       }
       
       if (!empty($filters['date_to'])) {
           $where[] = "created_at <= :date_to";
           $params['date_to'] = $filters['date_to'];
       }
       
       // Construir condición WHERE
       $whereClause = empty($where) ? "" : "WHERE " . implode(" AND ", $where);
       
       // Obtener estadísticas básicas
       $stats = Database::query("
           SELECT 
               COUNT(*) as total_requests,
               SUM(tokens_used) as total_tokens,
               AVG(tokens_used) as avg_tokens,
               COUNT(DISTINCT provider_id) as providers_count,
               COUNT(DISTINCT user_id) as users_count,
               COUNT(DISTINCT module) as modules_count
           FROM ai_usage_logs
           {$whereClause}
       ", $params)->fetch();
       
       // Obtener uso por proveedor
       $providerStats = Database::query("
           SELECT 
               p.name as provider_name,
               p.provider_type,
               COUNT(*) as requests,
               SUM(l.tokens_used) as tokens
           FROM ai_usage_logs l
           JOIN ai_providers p ON l.provider_id = p.id
           {$whereClause}
           GROUP BY p.id
           ORDER BY tokens DESC
       ", $params)->fetchAll();
       
       // Obtener uso por módulo
       $moduleStats = Database::query("
           SELECT 
               module,
               COUNT(*) as requests,
               SUM(tokens_used) as tokens
           FROM ai_usage_logs
           {$whereClause}
           GROUP BY module
           ORDER BY tokens DESC
       ", $params)->fetchAll();
       
       return [
           'summary' => $stats,
           'by_provider' => $providerStats,
           'by_module' => $moduleStats
       ];
   }
   
   /**
    * Verifica si un usuario ha excedido su límite diario de tokens
    * 
    * @param int $userId ID del usuario
    * @param string $userType Tipo de usuario (admin, super_admin, etc.)
    * @param int|null $tenantId ID del tenant o null para global
    * @return bool True si ha excedido el límite
    */
   public static function hasExceededDailyLimit($userId, $userType, $tenantId = null)
   {
       // Obtener el límite diario de tokens
       $dailyLimit = self::getSetting('ai_daily_token_limit', 0, $tenantId);
       
       // Si no hay límite (0), devolver false
       if ($dailyLimit <= 0) {
           return false;
       }
       
       // Calcular el uso de tokens para hoy
       $todayStart = date('Y-m-d 00:00:00');
       $todayEnd = date('Y-m-d 23:59:59');
       
       $params = [
           'user_id' => $userId,
           'user_type' => $userType,
           'date_from' => $todayStart,
           'date_to' => $todayEnd
       ];
       
       if ($tenantId !== null) {
           $params['tenant_id'] = $tenantId;
           $tenantCondition = "AND tenant_id = :tenant_id";
       } else {
           $tenantCondition = "AND tenant_id IS NULL";
       }
       
       $tokensUsed = Database::query("
           SELECT SUM(tokens_used) 
           FROM ai_usage_logs 
           WHERE user_id = :user_id 
           AND user_type = :user_type 
           AND created_at BETWEEN :date_from AND :date_to
           {$tenantCondition}
       ", $params)->fetchColumn();
       
       return $tokensUsed >= $dailyLimit;
   }
   
   /**
    * Obtiene un valor de configuración
    * 
    * @param string $key Clave de configuración
    * @param mixed $default Valor por defecto
    * @param int|null $tenantId ID del tenant o null para global
    * @return mixed Valor de configuración
    */
   public static function getSetting($key, $default = null, $tenantId = null)
   {
       $params = ['key' => $key];
       
       if ($tenantId !== null) {
           // Intentar obtener configuración específica del tenant
           $params['tenant_id'] = $tenantId;
           $setting = Database::query("
               SELECT setting_value FROM ai_settings
               WHERE setting_key = :key AND tenant_id = :tenant_id
           ", $params)->fetchColumn();
           
           // Si no existe, intentar obtener configuración global
           if ($setting === false) {
               unset($params['tenant_id']);
               $setting = Database::query("
                   SELECT setting_value FROM ai_settings
                   WHERE setting_key = :key AND tenant_id IS NULL
               ", $params)->fetchColumn();
           }
       } else {
           // Obtener configuración global
           $setting = Database::query("
               SELECT setting_value FROM ai_settings
               WHERE setting_key = :key AND tenant_id IS NULL
           ", $params)->fetchColumn();
       }
       
       return $setting !== false ? $setting : $default;
   }
   
   /**
    * Establece un valor de configuración
    * 
    * @param string $key Clave de configuración
    * @param mixed $value Valor
    * @param int|null $tenantId ID del tenant o null para global
    * @return bool Éxito
    */
   public static function setSetting($key, $value, $tenantId = null)
   {
       $params = [
           'key' => $key,
           'tenant_id' => $tenantId
       ];
       
       $tenantCondition = $tenantId !== null ? "AND tenant_id = :tenant_id" : "AND tenant_id IS NULL";
       
       // Verificar si la configuración ya existe
       $exists = Database::query("
           SELECT COUNT(*) FROM ai_settings
           WHERE setting_key = :key {$tenantCondition}
       ", $params)->fetchColumn();
       
       if ($exists) {
           // Actualizar configuración existente
           return Database::query("
               UPDATE ai_settings
               SET setting_value = :value, updated_at = NOW()
               WHERE setting_key = :key {$tenantCondition}
           ", array_merge($params, ['value' => $value]));
       } else {
           // Crear nueva configuración
           return Database::table('ai_settings')->insert([
               'setting_key' => $key,
               'setting_value' => $value,
               'tenant_id' => $tenantId,
               'created_at' => date('Y-m-d H:i:s'),
               'updated_at' => date('Y-m-d H:i:s')
           ]);
       }
   }
}