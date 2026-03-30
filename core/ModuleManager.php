<?php
namespace Screenart\Musedock;

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;

class ModuleManager
{
    public static function syncModulesWithDisk()
    {
        $modulesDir = __DIR__ . '/../modules';  // CORREGIR AQUÍ: __DIR__ con dos guiones bajos
        $db = Database::connect();
        
        Logger::debug("ModuleManager: Sincronizando módulos con disco");
        
        foreach (glob($modulesDir . '/*', GLOB_ONLYDIR) as $modPath) {
            $json = $modPath . '/module.json';
            if (!file_exists($json)) {
                Logger::debug("ModuleManager: No existe module.json en " . basename($modPath));
                continue;
            }
            
            $data = json_decode(file_get_contents($json), true);
            $slug = basename($modPath);
            
            try {
                $exists = $db->prepare("SELECT COUNT(*) FROM modules WHERE slug = :slug");
                $exists->execute(['slug' => $slug]);
                
                if (!$exists->fetchColumn()) {
                    Logger::info("ModuleManager: Registrando nuevo módulo {$slug}");
                    
                    $stmt = $db->prepare("INSERT INTO modules (slug, name, description, version, author, active, public, cms_enabled)
                        VALUES (:slug, :name, :desc, :ver, :author, :active, :public, :cms_enabled)");
                    $stmt->execute([
                        'slug'   => $slug,
                        'name'   => $data['name'] ?? ucfirst($slug),
                        'desc'   => $data['description'] ?? '',
                        'ver'    => $data['version'] ?? '1.0',
                        'author' => $data['author'] ?? 'Desconocido',
                        'active' => (int)($data['active'] ?? 1),
                        'public' => (int)($data['public'] ?? 0),
                        'cms_enabled' => (int)($data['cms_enabled'] ?? 1)  // Añadir cms_enabled por defecto
                    ]);
                    
                    Logger::info("ModuleManager: Módulo {$slug} registrado correctamente");
                } else {
                    Logger::debug("ModuleManager: Módulo {$slug} ya existe en base de datos");
                }
            } catch (\Exception $e) {
                Logger::error("ModuleManager: Error al sincronizar módulo {$slug} - " . $e->getMessage());
            }
        }
    }
    
    // Añadir un método para activar/desactivar explícitamente módulos
    public static function enableModule($slug, $forCms = true)
    {
        try {
            $db = Database::connect();
            
            // Actualizar el módulo
            $stmt = $db->prepare("UPDATE modules SET active = 1, cms_enabled = :cms_enabled WHERE slug = :slug");
            $stmt->execute([
                'slug' => $slug,
                'cms_enabled' => $forCms ? 1 : 0
            ]);
            
            Logger::info("ModuleManager: Módulo {$slug} activado", ['forCms' => $forCms]);
            return true;
        } catch (\Exception $e) {
            Logger::error("ModuleManager: Error al activar módulo {$slug} - " . $e->getMessage());
            return false;
        }
    }
}