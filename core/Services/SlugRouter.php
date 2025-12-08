<?php

namespace Screenart\Musedock\Services;

use Screenart\Musedock\Models\Slug;
use Screenart\Musedock\View;
use Screenart\Musedock\Controllers\frontend\PageController;
use Screenart\Musedock\Logger;

class SlugRouter
{
    /**
     * Resuelve un slug dinámico y redirige al módulo correspondiente.
     * 
     * @param string|null $prefix Prefijo de la URL (p, b, etc.)
     * @param string|null $slug Slug específico o null/vacío para listar
     * @return mixed Respuesta del controlador correspondiente
     */
    public static function resolve(?string $prefix, ?string $slug = null)
    {
        // Log inicial para debugging detallado
        $logPath = __DIR__ . '/../../storage/logs/debug.log';
        file_put_contents($logPath, date('Y-m-d H:i:s') . " - ENTRADA RESOLVE - prefix: " . 
            json_encode($prefix) . ", slug: " . json_encode($slug) . "\n", FILE_APPEND);
        
        // Si solo tenemos prefijo sin slug (o slug vacío)
        // Nota: empty() considera null, '', 0, false como vacíos
        if ($prefix && (empty($slug) || $slug === '')) {
            file_put_contents($logPath, date('Y-m-d H:i:s') . " - DETECTADO SOLO PREFIJO: " . $prefix . "\n", FILE_APPEND);
            return self::showListByPrefix($prefix);
        }

        $tenantId = TenantManager::currentTenantId();
        $multiTenant = setting('multi_tenant_enabled', false);
        $currentLang = detectLanguage(); // Detectar el idioma activo
        
        // Log de información básica
        file_put_contents($logPath, date('Y-m-d H:i:s') . " - RESOLVIENDO SLUG:\n", FILE_APPEND);
        file_put_contents($logPath, "- slug: $slug\n- prefix: " . json_encode($prefix) . "\n- tenant_id: " . json_encode($tenantId) . "\n- locale: " . $currentLang . "\n", FILE_APPEND);
        
        // Query Eloquent - Primera versión
        $query = Slug::where('slug', '=', $slug)
            ->where('module', '=', 'pages');
        
        if ($multiTenant) {
            if ($tenantId !== null) {
                $query->where('tenant_id', '=', $tenantId);
            } else {
                $query->whereRaw('tenant_id IS NULL');
            }
        }
        
        if ($prefix !== null) {
            $query->where('prefix', '=', $prefix);
        } else {
            $query->whereRaw('prefix IS NULL');
        }
        
        // Query Eloquent - Con idioma
        $query = Slug::where('slug', '=', $slug)
            ->where('module', '=', 'pages')
            ->whereRaw('(locale = :locale OR locale IS NULL)', [':locale' => $currentLang]);
        
        $entry = $query->first();
        
        // FallBack: Si el ORM no encuentra nada, intenta vía SQL directa
        if (!$entry) {
            $sql = "SELECT * FROM slugs WHERE slug = :slug AND module = 'pages'";
            $params = [':slug' => $slug];
            
            if ($multiTenant) {
                if ($tenantId !== null) {
                    $sql .= " AND tenant_id = :tenant_id";
                    $params[':tenant_id'] = $tenantId;
                } else {
                    $sql .= " AND tenant_id IS NULL";
                }
            }
            
            if ($prefix !== null) {
                $sql .= " AND prefix = :prefix";
                $params[':prefix'] = $prefix;
            } else {
                $sql .= " AND prefix IS NULL";
            }
            
            // También aplicamos locale en la consulta directa
            $sql .= " AND (locale = :locale OR locale IS NULL)";
            $params[':locale'] = $currentLang;
            
            $sql .= " LIMIT 1";
            
            // Log de la consulta
            file_put_contents($logPath, date('Y-m-d H:i:s') . " - SQL DIRECTO: " . $sql . "\n", FILE_APPEND);
            file_put_contents($logPath, date('Y-m-d H:i:s') . " - PARÁMETROS: " . json_encode($params) . "\n", FILE_APPEND);
            
            try {
                $entry = \Screenart\Musedock\Database::query($sql, $params)->fetch(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                file_put_contents($logPath, date('Y-m-d H:i:s') . " - ERROR SQL: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }
        
        if (is_array($entry)) {
            $entry = (object) $entry;
        }
        
        file_put_contents($logPath, "- Resultado encontrado: " . json_encode($entry) . "\n", FILE_APPEND);
        
        if (!$entry) {
            http_response_code(404);
            file_put_contents($logPath, date('Y-m-d H:i:s') . " - 404 NOT FOUND: Renderizando página 404\n", FILE_APPEND);

            // Renderizar la vista 404 bonita
            $blade = new \Screenart\Musedock\BladeExtended(
                __DIR__ . '/../Views/errors',
                __DIR__ . '/../../storage/cache/errors',
                \Screenart\Musedock\BladeExtended::MODE_AUTO
            );
            echo $blade->run('404');
            return;
        }
        
        $method = 'resolve_' . strtolower($entry->module);
        if (method_exists(__CLASS__, $method)) {
            return self::$method($entry->reference_id);
        }

        // Módulo no soportado, mostrar 404
        http_response_code(404);
        file_put_contents($logPath, date('Y-m-d H:i:s') . " - 404 NOT FOUND: Módulo '{$entry->module}' no soportado - Renderizando página 404\n", FILE_APPEND);

        // Renderizar la vista 404 bonita
        $blade = new \Screenart\Musedock\BladeExtended(
            __DIR__ . '/../Views/errors',
            __DIR__ . '/../../storage/cache/errors',
            \Screenart\Musedock\BladeExtended::MODE_AUTO
        );
        echo $blade->run('404');
        return;
    }

    /**
     * Muestra un listado de elementos basado en el prefijo
     * 
     * @param string $prefix Prefijo para determinar qué listar
     * @return mixed Respuesta del controlador correspondiente
     */
    private static function showListByPrefix(string $prefix)
    {
        // Log para debugging
        $logPath = __DIR__ . '/../../storage/logs/debug.log';
        file_put_contents($logPath, date('Y-m-d H:i:s') . " - MOSTRANDO LISTADO POR PREFIJO: $prefix\n", FILE_APPEND);
        
        // Determinar qué controlador y método llamar según el prefijo
        switch ($prefix) {
            case 'p':
                $controller = new \Screenart\Musedock\Controllers\Frontend\PageController();
                return $controller->listPages();
                break;
            // Puedes añadir más casos para otros prefijos
            // case 'b': // Para blog por ejemplo
            //     $controller = new BlogController();
            //     return $controller->listPosts();
            //     break;
            default:
                http_response_code(404);
                file_put_contents($logPath, date('Y-m-d H:i:s') . " - 404 NOT FOUND: Prefijo no reconocido - Renderizando página 404\n", FILE_APPEND);

                // Renderizar la vista 404 bonita
                $blade = new \Screenart\Musedock\BladeExtended(
                    __DIR__ . '/../Views/errors',
                    __DIR__ . '/../../storage/cache/errors',
                    \Screenart\Musedock\BladeExtended::MODE_AUTO
                );
                echo $blade->run('404');
                return;
        }
    }

    /**
     * Resolución de slugs para páginas.
     * 
     * @param int $id ID de la página a mostrar
     * @return mixed Respuesta del controlador
     */
    public static function resolve_pages($id)
    {
        $controller = new \Screenart\Musedock\Controllers\Frontend\PageController();
        return $controller->showById($id);
    }
    
    // Puedes añadir más resolvers aquí si necesitas soporte para otros módulos:
    // public static function resolve_blog($id) { ... }
    // public static function resolve_product($id) { ... }
}