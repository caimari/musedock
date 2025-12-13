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

        // Leer multi_tenant_enabled desde .env primero (consistente con TenantResolver)
        $multiTenant = \Screenart\Musedock\Env::get('MULTI_TENANT_ENABLED', null);
        if ($multiTenant === null) {
            $multiTenant = setting('multi_tenant_enabled', false);
        }
        // Convertir a booleano (puede venir como string "true"/"false" desde .env)
        $multiTenant = filter_var($multiTenant, FILTER_VALIDATE_BOOLEAN);

        $currentLang = detectLanguage(); // Detectar el idioma activo
        
        // Log de información básica
        file_put_contents($logPath, date('Y-m-d H:i:s') . " - RESOLVIENDO SLUG:\n", FILE_APPEND);
        file_put_contents($logPath, "- slug: $slug\n- prefix: " . json_encode($prefix) . "\n- tenant_id: " . json_encode($tenantId) . "\n- multiTenant: " . ($multiTenant ? 'true' : 'false') . "\n- locale: " . $currentLang . "\n", FILE_APPEND);
        
        // Query Eloquent - Con todos los filtros
        $query = Slug::where('slug', '=', $slug)
            ->where('module', '=', 'pages')
            ->whereRaw('(locale = :locale OR locale IS NULL)', [':locale' => $currentLang]);

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

            // Limpiar cualquier buffer de output
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            // Asegurar headers correctos
            if (!headers_sent()) {
                header('Content-Type: text/html; charset=UTF-8');
            }

            // Intentar renderizar con Blade, con fallback a HTML directo
            try {
                $blade = new \Screenart\Musedock\BladeExtended(
                    __DIR__ . '/../Views/errors',
                    __DIR__ . '/../../storage/cache/errors',
                    \Screenart\Musedock\BladeExtended::MODE_AUTO
                );
                echo $blade->run('404');
            } catch (\Exception $e) {
                file_put_contents($logPath, date('Y-m-d H:i:s') . " - ERROR BLADE 404: " . $e->getMessage() . "\n", FILE_APPEND);
                // Fallback a HTML genérico si Blade falla
                echo self::getGeneric404Html();
            }
            exit;
        }
        
        $method = 'resolve_' . strtolower($entry->module);
        if (method_exists(__CLASS__, $method)) {
            return self::$method($entry->reference_id);
        }

        // Módulo no soportado, mostrar 404
        http_response_code(404);
        file_put_contents($logPath, date('Y-m-d H:i:s') . " - 404 NOT FOUND: Módulo '{$entry->module}' no soportado - Renderizando página 404\n", FILE_APPEND);

        // Limpiar cualquier buffer de output
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Asegurar headers correctos
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }

        // Intentar renderizar con Blade, con fallback a HTML directo
        try {
            $blade = new \Screenart\Musedock\BladeExtended(
                __DIR__ . '/../Views/errors',
                __DIR__ . '/../../storage/cache/errors',
                \Screenart\Musedock\BladeExtended::MODE_AUTO
            );
            echo $blade->run('404');
        } catch (\Exception $e) {
            file_put_contents($logPath, date('Y-m-d H:i:s') . " - ERROR BLADE 404: " . $e->getMessage() . "\n", FILE_APPEND);
            echo self::getGeneric404Html();
        }
        exit;
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

                // Limpiar cualquier buffer de output
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }

                // Asegurar headers correctos
                if (!headers_sent()) {
                    header('Content-Type: text/html; charset=UTF-8');
                }

                // Intentar renderizar con Blade, con fallback a HTML directo
                try {
                    $blade = new \Screenart\Musedock\BladeExtended(
                        __DIR__ . '/../Views/errors',
                        __DIR__ . '/../../storage/cache/errors',
                        \Screenart\Musedock\BladeExtended::MODE_AUTO
                    );
                    echo $blade->run('404');
                } catch (\Exception $e) {
                    file_put_contents($logPath, date('Y-m-d H:i:s') . " - ERROR BLADE 404: " . $e->getMessage() . "\n", FILE_APPEND);
                    echo self::getGeneric404Html();
                }
                exit;
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

    /**
     * HTML genérico de 404 como fallback si Blade falla
     */
    private static function getGeneric404Html(): string
    {
        $requestUri = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/', ENT_QUOTES, 'UTF-8');
        $siteName = isset($GLOBALS['tenant']['name'])
            ? htmlspecialchars($GLOBALS['tenant']['name'], ENT_QUOTES, 'UTF-8')
            : 'Este sitio';

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Página no encontrada</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 20px;
        }
        .container { text-align: center; max-width: 600px; }
        .error-code {
            font-size: clamp(100px, 20vw, 180px);
            font-weight: 800;
            line-height: 1;
            text-shadow: 4px 4px 0 rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .error-title { font-size: clamp(24px, 5vw, 36px); font-weight: 600; margin-bottom: 15px; }
        .error-message { font-size: 18px; opacity: 0.9; margin-bottom: 40px; line-height: 1.6; }
        .btn {
            display: inline-block;
            padding: 14px 28px;
            background: #fff;
            color: #667eea;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin: 0 10px;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.3); }
        .btn-secondary { background: rgba(255,255,255,0.15); color: #fff; border: 2px solid rgba(255,255,255,0.3); }
        .site-name { margin-top: 50px; font-size: 14px; opacity: 0.7; }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-code">404</div>
        <h1 class="error-title">Página no encontrada</h1>
        <p class="error-message">
            Lo sentimos, la página que buscas no existe o ha sido movida.<br>
            Puede que el enlace esté roto o la dirección sea incorrecta.
        </p>
        <div>
            <a href="/" class="btn">Ir al inicio</a>
            <a href="javascript:history.back()" class="btn btn-secondary">Volver atrás</a>
        </div>
        <p class="site-name">{$siteName}</p>
    </div>
</body>
</html>
HTML;
    }

    // Puedes añadir más resolvers aquí si necesitas soporte para otros módulos:
    // public static function resolve_blog($id) { ... }
    // public static function resolve_product($id) { ... }
}