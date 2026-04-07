<?php

namespace Screenart\Musedock\Services;

use Screenart\Musedock\Models\Slug;
use Screenart\Musedock\View;
use Screenart\Musedock\Controllers\frontend\PageController;
use Screenart\Musedock\Logger;
use Screenart\Musedock\Services\DefaultLegalPagesService;

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
        
        // Query Eloquent - Con todos los filtros (buscar en pages y blog)
        $query = Slug::where('slug', '=', $slug)
            ->whereRaw("module IN ('pages', 'blog')")
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
            // Sin prefijo: buscar tanto NULL como vacío (ambos significan "sin prefijo")
            $query->whereRaw("(prefix IS NULL OR prefix = '')");
        }

        $entry = $query->first();

        // FallBack: Si el ORM no encuentra nada, intenta vía SQL directa
        if (!$entry) {
            $sql = "SELECT * FROM slugs WHERE slug = :slug AND module IN ('pages', 'blog')";
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
                $sql .= " AND (prefix IS NULL OR prefix = '')";
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

        // If not found with given prefix, check if slug exists with a different prefix (cross-prefix redirect)
        if (!$entry && $prefix !== null) {
            $crossSql = "SELECT prefix FROM slugs WHERE slug = :slug AND module = 'blog'";
            $crossParams = [':slug' => $slug];
            if ($multiTenant) {
                if ($tenantId !== null) {
                    $crossSql .= " AND tenant_id = :tid";
                    $crossParams[':tid'] = $tenantId;
                } else {
                    $crossSql .= " AND tenant_id IS NULL";
                }
            }
            $crossSql .= " LIMIT 1";
            try {
                $crossEntry = \Screenart\Musedock\Database::query($crossSql, $crossParams)->fetch(\PDO::FETCH_OBJ);
                if ($crossEntry && $crossEntry->prefix && $crossEntry->prefix !== $prefix) {
                    header('Location: /' . $crossEntry->prefix . '/' . $slug, true, 301);
                    exit;
                }
            } catch (\Exception $e) {}
        }

        // Redirect 301 si el slug resuelve a la página de inicio (evitar contenido duplicado)
        if ($entry && $entry->module === 'pages') {
            $homepageId = self::getHomepagePageId($tenantId);
            if ($homepageId && (int)$entry->reference_id === $homepageId) {
                file_put_contents($logPath, date('Y-m-d H:i:s') . " - 301 REDIRECT: slug de homepage '{$slug}' -> /\n", FILE_APPEND);
                header('Location: /', true, 301);
                exit;
            }
        }

        if (!$entry) {
            // === FALLBACK: Verificar si es una página legal por defecto ===
            $pagePrefix = function_exists('page_prefix') ? page_prefix() : 'p';
            if (($prefix === $pagePrefix || $prefix === null) && DefaultLegalPagesService::isLegalPageSlug($slug)) {
                file_put_contents($logPath, date('Y-m-d H:i:s') . " - FALLBACK: Página legal por defecto - $slug\n", FILE_APPEND);
                $controller = new \Screenart\Musedock\Controllers\Frontend\PageController();
                return $controller->showDefaultLegalPage($slug);
            }

            file_put_contents($logPath, date('Y-m-d H:i:s') . " - 404 NOT FOUND: Renderizando página 404\n", FILE_APPEND);
            self::render404();
        }

        $method = 'resolve_' . strtolower($entry->module);
        if (method_exists(__CLASS__, $method)) {
            return self::$method($entry->reference_id);
        }

        // Módulo no soportado
        file_put_contents($logPath, date('Y-m-d H:i:s') . " - 404 NOT FOUND: Módulo '{$entry->module}' no soportado\n", FILE_APPEND);
        self::render404();
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
        // Obtener prefijo de páginas configurable
        $pagePrefix = function_exists('page_prefix') ? page_prefix() : 'p';

        switch ($prefix) {
            case ($pagePrefix !== '' ? $pagePrefix : '___none___'):
            case 'p': // Fallback para compatibilidad
                $controller = new \Screenart\Musedock\Controllers\Frontend\PageController();
                return $controller->listPages();
                break;
            case 'docs':
                $controller = new \Blog\Controllers\Frontend\BlogController();
                return $controller->docsIndex();
                break;
            default:
                // Prefijo no reconocido: intentar resolver como slug sin prefijo
                // Esto permite que URLs como /slug-del-post funcionen cuando blog_prefix es vacío
                file_put_contents($logPath, date('Y-m-d H:i:s') . " - Prefijo no reconocido, intentando como slug sin prefijo: $prefix\n", FILE_APPEND);
                return self::resolve(null, $prefix);
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

    /**
     * Resolución de slugs para posts del blog.
     */
    public static function resolve_blog($id)
    {
        $controller = new \Blog\Controllers\Frontend\BlogController();
        return $controller->showById($id);
    }

    /**
     * Obtiene el page_id de la página de inicio.
     */
    private static function getHomepagePageId(?int $tenantId): ?int
    {
        try {
            $pdo = \Screenart\Musedock\Database::connect();

            if ($tenantId) {
                $stmt = $pdo->prepare("SELECT value FROM tenant_settings WHERE tenant_id = ? AND \"key\" = 'show_on_front'");
                $stmt->execute([$tenantId]);
                $showOnFront = $stmt->fetchColumn();

                if ($showOnFront === 'page') {
                    $stmt = $pdo->prepare("SELECT value FROM tenant_settings WHERE tenant_id = ? AND \"key\" = 'page_on_front'");
                    $stmt->execute([$tenantId]);
                    $pageOnFront = $stmt->fetchColumn();
                    if ($pageOnFront && is_numeric($pageOnFront)) {
                        return (int)$pageOnFront;
                    }
                }

                $stmt = $pdo->prepare("SELECT id FROM pages WHERE is_homepage = 1 AND status = 'published' AND tenant_id = ? LIMIT 1");
                $stmt->execute([$tenantId]);
            } else {
                $showOnFront = function_exists('setting') ? setting('show_on_front', 'posts') : 'posts';
                if ($showOnFront === 'page') {
                    $pageOnFront = function_exists('setting') ? setting('page_on_front', '') : '';
                    if ($pageOnFront && is_numeric($pageOnFront)) {
                        return (int)$pageOnFront;
                    }
                }
                $stmt = $pdo->prepare("SELECT id FROM pages WHERE is_homepage = 1 AND status = 'published' AND tenant_id IS NULL LIMIT 1");
                $stmt->execute();
            }

            $id = $stmt->fetchColumn();
            return $id ? (int)$id : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Redirect 301 para URLs legacy de WordPress.
     * Patrones soportados:
     *   /index.php/YYYY/MM/DD/slug       -> /[prefix/]slug
     *   /YYYY/MM/DD/slug                 -> /[prefix/]slug
     *   /index.php/slug                  -> /[prefix/]slug
     *   /index.php/tag/slug              -> /[blog_prefix/]tag/slug
     *   /index.php/category/slug         -> /[blog_prefix/]category/slug
     *   /YYYY/MM/slug                    -> /[prefix/]slug
     *
     * Si el slug existe, redirige 301 a la URL actual correcta.
     * Si no existe, devuelve 404.
     *
     * @param string $path Path completo después de /index.php/ o tras la fecha
     */
    public static function redirectLegacy(string $path): void
    {
        $path = trim($path, '/');
        if ($path === '') {
            header('Location: /', true, 301);
            exit;
        }

        $segments = explode('/', $path);
        $tenantId = TenantManager::currentTenantId();
        $pdo = \Screenart\Musedock\Database::connect();

        // Obtener blog_prefix del tenant para construir URLs de tag/category
        $blogPrefix = function_exists('blog_prefix') ? blog_prefix() : 'blog';

        // --- Detectar tag/slug o category/slug ---
        if (count($segments) >= 2 && ($segments[0] === 'tag' || $segments[0] === 'category')) {
            $type = $segments[0]; // 'tag' o 'category'
            $slug = $segments[1];
            $table = ($type === 'tag') ? 'blog_tags' : 'blog_categories';

            $sql = "SELECT slug FROM {$table} WHERE slug = :slug";
            $params = [':slug' => $slug];
            if ($tenantId !== null) {
                $sql .= " AND tenant_id = :tid";
                $params[':tid'] = $tenantId;
            } else {
                $sql .= " AND tenant_id IS NULL";
            }
            $sql .= " LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $entry = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($entry) {
                $newUrl = $blogPrefix !== '' ? "/{$blogPrefix}/{$type}/{$slug}" : "/{$type}/{$slug}";
                header('Location: ' . $newUrl, true, 301);
                exit;
            }

            // Tag/category no encontrado — 404
            self::render404();
            return;
        }

        // --- Filtrar segmentos de fecha (YYYY, MM, DD numéricos) y quedarse con el slug ---
        $slug = end($segments);

        // Buscar el slug en la tabla slugs (posts/pages)
        $sql = "SELECT slug, prefix, module FROM slugs WHERE slug = :slug";
        $params = [':slug' => $slug];

        if ($tenantId !== null) {
            $sql .= " AND tenant_id = :tid";
            $params[':tid'] = $tenantId;
        } else {
            $sql .= " AND tenant_id IS NULL";
        }
        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $entry = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($entry) {
            $newUrl = '/';
            if (!empty($entry['prefix'])) {
                $newUrl .= $entry['prefix'] . '/';
            }
            $newUrl .= $entry['slug'];

            header('Location: ' . $newUrl, true, 301);
            exit;
        }

        // Slug no encontrado — 404
        self::render404();
    }

    /**
     * Renderiza la página 404 y termina la ejecución.
     */
    private static function render404(): void
    {
        http_response_code(404);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        try {
            $blade = new \Screenart\Musedock\BladeExtended(
                __DIR__ . '/../Views/errors',
                __DIR__ . '/../../storage/cache/errors',
                \Screenart\Musedock\BladeExtended::MODE_AUTO
            );
            echo $blade->run('404');
        } catch (\Exception $e) {
            echo self::getGeneric404Html();
        }
        exit;
    }
}