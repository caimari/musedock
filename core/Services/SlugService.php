<?php

namespace Screenart\Musedock\Services;

use Screenart\Musedock\Models\Slug;
use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;

class SlugService 
{
    /**
     * Comprueba si un slug ya existe en el sistema (para AJAX y validaciones)
     *
     * @param string $slug El slug a verificar
     * @param string|null $prefix El prefijo del slug
     * @param int|null $excludeId ID a excluir (para edición)
     * @param string|null $module Módulo (pages, blog_posts, etc.)
     * @param int|null $tenantId ID del tenant (null para global)
     * @return bool
     */
    public static function exists(string $slug, ?string $prefix = null, ?int $excludeId = null, ?string $module = 'pages', ?int $tenantId = null): bool
    {
        $query = \Screenart\Musedock\Database::table('slugs')
            ->where('slug', $slug)
            ->where('module', $module);

        if ($prefix !== null) {
            $query->where('prefix', $prefix);
        }

        if ($excludeId !== null) {
            $query->where('reference_id', '!=', $excludeId);
        }

        // Filtrar por tenant_id para que slugs sean independientes por tenant
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }

        return $query->exists();
    }

    /**
     * Registra un slug una sola vez, evitando duplicados
     * 
     * @param string $module Nombre del módulo
     * @param int $referenceId ID de referencia
     * @param string $slug Slug a registrar
     * @param int|null $tenantId ID del tenant o null
     * @param string|null $prefix Prefijo o null
     * @return void
     */
    public static function registerOnce(string $module, int $referenceId, string $slug, ?int $tenantId = null, ?string $prefix = null): void 
    {
        // Ruta para el archivo de log
        $logFile = __DIR__ . '/../../storage/logs/slug-debug.log';
        
        // Log inicial
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - INICIO registerOnce:\n" . json_encode([
            'module' => $module,
            'reference_id' => $referenceId,
            'slug' => $slug,
            'tenant_id' => $tenantId,
            'prefix' => $prefix
        ], JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
        
        try {
            // Verificar si ya existe usando el ORM (approach original)
            $query = Slug::where('module', '=', $module)
                ->where('reference_id', '=', $referenceId)
                ->where('slug', '=', $slug);
            
            if ($tenantId !== null) {
                $query->where('tenant_id', '=', $tenantId);
            } else {
                $query->whereRaw('tenant_id IS NULL');
            }
            
            if ($prefix !== null) {
                $query->where('prefix', '=', $prefix);
            } else {
                $query->whereRaw('prefix IS NULL');
            }
            
            // Ejecutar la consulta y obtener el resultado
            $existingSlug = $query->first();
            
            // Log del resultado
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Slug existente vía ORM: " . ($existingSlug ? "SÍ" : "NO") . "\n", FILE_APPEND);
            
            // Si no encontramos vía ORM, intentemos con una consulta SQL directa como verificación adicional
            if (!$existingSlug) {
                // Consulta SQL directa usando Database::query() que vimos en tu código
                $sql = "SELECT id FROM slugs WHERE module = :module AND reference_id = :ref_id AND slug = :slug";
                $params = [
                    ':module' => $module, 
                    ':ref_id' => $referenceId,
                    ':slug' => $slug
                ];
                
                // Agregar condiciones para tenant_id
                if ($tenantId !== null) {
                    $sql .= " AND tenant_id = :tenant_id";
                    $params[':tenant_id'] = $tenantId;
                } else {
                    $sql .= " AND tenant_id IS NULL";
                }
                
                // Agregar condiciones para prefix
                if ($prefix !== null) {
                    $sql .= " AND prefix = :prefix";
                    $params[':prefix'] = $prefix;
                } else {
                    $sql .= " AND prefix IS NULL";
                }
                
                $sql .= " LIMIT 1";
                
                // Log de la consulta SQL
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - SQL directa: " . $sql . "\n", FILE_APPEND);
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Parámetros: " . json_encode($params) . "\n", FILE_APPEND);
                
                try {
                    // Ejecutar la consulta utilizando Database::query()
                    $result = Database::query($sql, $params);
                    $directResult = $result->fetch();
                    
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Resultado SQL directa: " . json_encode($directResult) . "\n", FILE_APPEND);
                    
                    // Si encontramos con SQL directa pero no con ORM, hay un problema con el ORM
                    if ($directResult) {
                        file_put_contents($logFile, date('Y-m-d H:i:s') . " - ADVERTENCIA: Slug encontrado con SQL directa pero no con ORM\n", FILE_APPEND);
                        // Consideramos que existe
                        $existingSlug = true;
                    }
                } catch (\Exception $e) {
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR en consulta directa: " . $e->getMessage() . "\n", FILE_APPEND);
                }
            }
            
            // Si no existe (ni por ORM ni por SQL directa), intentamos insertar
            if (!$existingSlug) {
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Slug no existe, intentando insertarlo\n", FILE_APPEND);
                
                try {
                    // Usamos el modelo ORM para la inserción
                    $slugEntry = new Slug([
                        'tenant_id' => $tenantId,
                        'module' => $module,
                        'reference_id' => $referenceId,
                        'slug' => $slug,
                        'prefix' => $prefix,
                    ]);
                    
                    $result = $slugEntry->save();
                    
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Resultado inserción ORM: " . ($result ? "éxito" : "fallo") . "\n", FILE_APPEND);
                    
                    // Si tiene ID, lo registramos
                    if (isset($slugEntry->id)) {
                        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Nuevo slug ID: " . $slugEntry->id . "\n", FILE_APPEND);
                    }
                } catch (\Exception $e) {
                    // Si hay un error, podría ser por clave duplicada (índice UNIQUE)
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR de inserción: " . $e->getMessage() . "\n", FILE_APPEND);
                    
                    // Verificamos si se trata de un error de clave duplicada
                    if (strpos($e->getMessage(), 'Duplicate entry') !== false || strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
                        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Slug ya existe (detectado por restricción UNIQUE)\n", FILE_APPEND);
                    } else {
                        // Para otros errores, registramos y relanzamos
                        Logger::log("SlugService::registerOnce - Error al guardar slug: " . $e->getMessage(), 'ERROR');
                        throw $e;
                    }
                }
            } else {
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Slug ya existe, no se crea uno nuevo\n", FILE_APPEND);
            }
        } catch (\Exception $e) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR GENERAL: " . $e->getMessage() . "\n", FILE_APPEND);
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Stack trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);
            Logger::log("SlugService::registerOnce - Error general: " . $e->getMessage(), 'ERROR');
        }
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - FIN registerOnce\n\n", FILE_APPEND);
    }
}