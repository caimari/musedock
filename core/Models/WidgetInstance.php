<?php
namespace Screenart\Musedock\Models;

use Screenart\Musedock\Database\Model;
use Screenart\Musedock\Database;

class WidgetInstance extends Model
{
    protected static string $table = 'widget_instances';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;
    protected array $fillable = [
        'tenant_id',
        'theme_slug',
        'area_slug',
        'widget_slug',
        'position',
        'config', // Guardará el JSON como string, lo castearemos
    ];
    
    // Casts para tipos de datos
    protected array $casts = [
        'tenant_id' => 'nullable|integer',
        'position'  => 'integer',
        'config'    => 'json', // <--- Castea automáticamente a/desde JSON string
    ];
    
    /**
     * Obtiene las instancias de widgets para un área específica
     * 
     * @param string $themeSlug Slug del tema
     * @param string $areaSlug Slug del área
     * @param int|null $tenantId ID del tenant (NULL para global)
     * @return array Arreglo de instancias de WidgetInstance
     */
    public static function getInstancesForArea(string $themeSlug, string $areaSlug, ?int $tenantId = null): array
    {
        try {
            // Log para depuración
            error_log("WidgetInstance::getInstancesForArea - Consultando instancias para Tema: {$themeSlug}, Área: {$areaSlug}, Tenant: " . ($tenantId ?? 'NULL'));
            
            // Construir consulta SQL directa para mejor control y depuración
            $sql = "SELECT * FROM widget_instances 
                    WHERE theme_slug = :theme_slug 
                    AND area_slug = :area_slug";
            
            $params = [
                ':theme_slug' => $themeSlug,
                ':area_slug' => $areaSlug
            ];
            
            // Filtrar por tenant_id (específico o global)
            if ($tenantId !== null) {
                $sql .= " AND tenant_id = :tenant_id";
                $params[':tenant_id'] = $tenantId;
            } else {
                $sql .= " AND tenant_id IS NULL";
            }
            
            // Ordenar por posición
            $sql .= " ORDER BY position ASC";
            
            // Ejecutar consulta
            $pdo = Database::connect();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // Obtener resultados
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Log para depuración
            error_log("WidgetInstance::getInstancesForArea - Encontradas " . count($results) . " instancias");
            
            // Convertir a objetos WidgetInstance
            $instances = [];
            foreach ($results as $data) {
                $instance = new self();
                // Usamos el método fillData personalizado para evitar conflictos con el método fill() de la clase base
                $instance->fillData($data);
                $instances[] = $instance;
            }
            
            return $instances;
        } catch (\Exception $e) {
            error_log("Error al obtener instancias de widgets: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Método auxiliar para llenar el modelo con datos sin entrar en conflicto con el método fill() original
     * 
     * @param array $data Datos para llenar el modelo
     * @return void
     */
    protected function fillData(array $data): void
    {
        foreach ($this->fillable as $field) {
            if (isset($data[$field])) {
                $this->$field = $this->castCustomField($field, $data[$field]);
            }
        }
    }
    
    /**
     * Castea un campo según su definición en $casts
     * 
     * @param string $field Nombre del campo
     * @param mixed $value Valor a castear
     * @return mixed Valor casteado
     */
    protected function castCustomField($field, $value)
    {
        if (!isset($this->casts[$field])) {
            return $value;
        }
        
        $castType = $this->casts[$field];
        
        if (strpos($castType, 'nullable') !== false) {
            if ($value === null) {
                return null;
            }
            // Quitar 'nullable|' del tipo
            $castType = str_replace('nullable|', '', $castType);
        }
        
        switch ($castType) {
            case 'integer':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'boolean':
                return (bool) $value;
            case 'json':
                if (is_string($value)) {
                    return json_decode($value, true) ?? [];
                }
                if (is_array($value)) {
                    return $value;
                }
                return [];
            default:
                return $value;
        }
    }
}