<?php

namespace Screenart\Musedock;

use Screenart\Musedock\BladeExtended as BladeOne;
use Screenart\Musedock\Logger;

class View
{
    protected static $sections = [];
    protected static $currentSection = null;
    protected static $pushStacks = [];
    protected static $pushCurrent = [];
    protected static $currentPush = null;
    protected static $namespaces = [];

    protected static $globalData = [];

    public static function addGlobalData(array $data)
    {
        self::$globalData = array_merge(self::$globalData, $data);
    }

    protected static function loadGlobals()
    {
        if (!function_exists('csrf_field')) {
            require_once __DIR__ . '/helpers.php';
        }

        // Hacer que $GLOBALS['ADMIN_MENU'] esté disponible en las vistas
        // De esta manera los templates pueden acceder directamente a $ADMIN_MENU
        if (isset($GLOBALS['ADMIN_MENU']) && !isset(self::$globalData['ADMIN_MENU'])) {
            self::$globalData['ADMIN_MENU'] = $GLOBALS['ADMIN_MENU'];
        }
    }

    public static function addNamespace($namespace, $path)
    {
        self::$namespaces[$namespace] = rtrim($path, '/');
    }

    public static function startSection(string $name)
    {
        self::$currentSection = $name;
        ob_start();
    }

    public static function stopSection()
    {
        $name = self::$currentSection;
        self::$currentSection = null;

        if ($name) {
            self::$sections[$name] = (self::$sections[$name] ?? '') . ob_get_clean();
        } else {
            ob_end_clean();
        }
    }

    public static function yieldSection(string $name)
    {
        return self::$sections[$name] ?? '';
    }

    public static function startPush(string $name)
    {
        self::$pushCurrent[] = $name;
        ob_start();
    }

    public static function stopPush()
    {
        $name = array_pop(self::$pushCurrent);

        if ($name !== null) {
            $content = ob_get_clean();
            if (!isset(self::$pushStacks[$name])) {
                self::$pushStacks[$name] = [];
            }
            self::$pushStacks[$name][] = $content;
        } else {
            ob_end_clean();
        }
    }

    public static function yieldPush(string $name)
    {
        return isset(self::$pushStacks[$name]) ? implode("\n", self::$pushStacks[$name]) : '';
    }

    protected static function clearCache(string $cacheDir)
    {
        foreach (glob("{$cacheDir}/*.blade.php") as $file) {
            @unlink($file);
        }
    }

    protected static function registerDirectives(BladeOne $blade)
    {
        self::$sections = [];
        self::$currentSection = null;
        self::$pushStacks = [];
        self::$currentPush = null;

        $blade->directive('section', fn($arg) => "<?php \\Screenart\\Musedock\\View::startSection({$arg}); ?>");
        $blade->directive('endsection', fn() => "<?php \\Screenart\\Musedock\\View::stopSection(); ?>");
        $blade->directive('yield', fn($arg) => "<?php echo \\Screenart\\Musedock\\View::yieldSection({$arg}); ?>");
        $blade->directive('push', fn($arg) => "<?php \\Screenart\\Musedock\\View::startPush({$arg}); ?>");
        $blade->directive('endpush', fn() => "<?php \\Screenart\\Musedock\\View::stopPush(); ?>");
        $blade->directive('stack', fn($arg) => "<?php echo \\Screenart\\Musedock\\View::yieldPush({$arg}); ?>");

        $blade->directive('menu', fn($arg) => "<?php echo \\Screenart\\Musedock\\Helpers\\MenuHelper::renderMenu({$arg}); ?>");
        $blade->directive('custommenu', fn($arg) => "<?php echo \\Screenart\\Musedock\\Helpers\\MenuHelper::renderCustomMenu({$arg}); ?>");
        $blade->directive('seometa', fn($arg) => "<?php echo seo_meta({$arg}); ?>");
        $blade->directive('social', fn($arg = null) => $arg ? "<?php echo social_icons({$arg}); ?>" : "<?php echo social_icons(); ?>");
        $blade->directive('setting', fn($arg) => "<?php echo get_setting({$arg}); ?>");
        $blade->directive('cookies', fn() => "<?php echo cookie_banner(); ?>");

        // Directiva CSRF para generar el campo hidden con el token
        $blade->directive('csrf', fn() => "<?php echo '<input type=\"hidden\" name=\"_token\" value=\"' . htmlspecialchars(csrf_token()) . '\">'; ?>");
    }

    protected static function getBladeMode(): int
    {
        return config('debug', false) ? BladeOne::MODE_DEBUG : BladeOne::MODE_AUTO;
    }

    protected static function registerModuleNamespaces(BladeOne $blade)
    {
        $modulesPath = APP_ROOT . '/modules';
        if (!is_dir($modulesPath)) return;

        foreach (scandir($modulesPath) as $module) {
            $modulePath = $modulesPath . '/' . $module;

            if (!is_dir($modulePath) || $module === '.' || $module === '..') continue;

            $viewPath = $modulePath . '/views';

            if (is_dir($viewPath)) {
                // Convertir el slug kebab-case a PascalCase para el namespace
                // Ej: "blog" -> "Blog", "media-manager" -> "MediaManager"
                $moduleNamespace = slug_to_namespace($module);
                $blade->addNamespace($moduleNamespace, $viewPath);
            }
        }
    }

    /**
     * Renderiza una plantilla genérica del sistema (para errores, etc.)
     * Usa el directorio core/Views directamente
     */
    public static function render($template, $data = [])
    {
        self::loadGlobals();
        $data = array_merge(self::$globalData, $data);

        $views = __DIR__ . '/../core/Views';
        $cache = __DIR__ . '/../storage/cache/system';

        if (!is_dir($cache)) mkdir($cache, 0775, true);

        $blade = new BladeExtended($views, $cache, self::getBladeMode());
        self::registerHelpers($blade);

        return $blade->run($template, $data);
    }

    public static function renderSuperadmin($template, $data = [])
    {
        self::loadGlobals();
        $data = array_merge(self::$globalData, $data);

        $views = __DIR__ . '/../core/Views/Superadmin';
        $cache = __DIR__ . '/../storage/cache/superadmin';

        if (!is_dir($cache)) mkdir($cache, 0775, true);

        $blade = new BladeExtended($views, $cache, self::getBladeMode());
        self::registerDirectives($blade);
        self::registerModuleNamespaces($blade);

        try {
            // NUEVO: Intentar detectar si es una vista de módulo y buscarla
            $moduleView = self::tryFindModuleView($template, 'superadmin');
            if ($moduleView !== null) {
                return $blade->run($moduleView, $data);
            }

            // Si no es vista de módulo, buscar en core normalmente
            return $blade->run($template, $data);
        } catch (\Exception $e) {
            Logger::log("Blade Superadmin Error: " . $e->getMessage(), 'ERROR');
            Logger::log("Template: $template", 'ERROR');
            Logger::log("Stack trace: " . $e->getTraceAsString(), 'ERROR');

            // En modo debug, mostrar error detallado
            if (config('debug', false)) {
                return self::renderTemplateError($template, $e, 'superadmin');
            }

            return "Error al renderizar superadmin: " . $e->getMessage();
        }
    }

    public static function renderTenantAdmin($template, $data = [])
    {
        self::loadGlobals();
        $data = array_merge(self::$globalData, $data);

        $tenant = tenant();
        $tenantId = $tenant['id'] ?? null;
        $themeSlug = $tenant['theme'] ?? 'default';
        $basePath = __DIR__ . "/../themes/tenant_{$tenantId}/{$themeSlug}/admin";
        $fallback = __DIR__ . '/../core/Views/Tenant';  // FIX: Mayúscula correcta
        $cache = __DIR__ . '/../storage/cache/tenant';

        if (!is_dir($cache)) mkdir($cache, 0775, true);

        $viewPath = file_exists("{$basePath}/" . str_replace('.', '/', $template) . '.blade.php') ? $basePath : $fallback;

        $blade = new BladeExtended($viewPath, $cache, self::getBladeMode());
        self::registerDirectives($blade);
        self::registerModuleNamespaces($blade);

        try {
            // NUEVO: Intentar detectar si es una vista de módulo
            $moduleView = self::tryFindModuleView($template, 'tenant');
            if ($moduleView !== null) {
                return $blade->run($moduleView, $data);
            }

            return $blade->run($template, $data);
        } catch (\Exception $e) {
            Logger::log("Blade TenantAdmin Error: " . $e->getMessage(), 'ERROR');
            Logger::log("Template: $template", 'ERROR');
            Logger::log("Stack trace: " . $e->getTraceAsString(), 'ERROR');

            // En modo debug, mostrar error detallado
            if (config('debug', false)) {
                return self::renderTemplateError($template, $e, 'tenant');
            }

            return "Error al renderizar tenant admin: " . $e->getMessage();
        }
    }

   public static function renderTheme($template, $data = [])
{
    // Asegurar traducciones del frontend en contexto tenant (antes de renderizar vistas)
    \Screenart\Musedock\Services\TranslationService::setContext('tenant');
    $locale = function_exists('detectLanguage')
        ? detectLanguage()
        : (\Screenart\Musedock\Services\TranslationService::getCurrentLocale() ?? 'es');
    \Screenart\Musedock\Services\TranslationService::load($locale, 'tenant');

    self::loadGlobals();
    $data = array_merge(self::$globalData, $data);

    // Usar get_active_theme_slug() que ya maneja tenant vs master correctamente
    $themeSlug = $data['slug'] ?? get_active_theme_slug();
    $tenant = tenant();
    $tenantId = $tenant['id'] ?? null;

    // Primero intentar con tema personalizado de tenant (si existe)
    if ($tenantId) {
        $themeBase = __DIR__ . "/../themes/tenant_{$tenantId}/" . $themeSlug;
        if (!is_dir($themeBase . '/views')) {
            // Si no existe personalización, usar tema compartido
            $themeBase = __DIR__ . "/../themes/" . $themeSlug;
        }
    } else {
        // Dominio master: usar tema directamente
        $themeBase = __DIR__ . "/../themes/" . $themeSlug;
    }

    $viewPath = $themeBase . '/views';

    // Usar directorio de caché separado por tema para evitar conflictos
    $cache = __DIR__ . '/../storage/cache/themes/' . $themeSlug;

    if (!file_exists("{$viewPath}/" . str_replace('.', '/', $template) . '.blade.php')) {
        $viewPath = __DIR__ . '/../themes/default/views';
        // Si usamos fallback a default, también usar su caché
        $cache = __DIR__ . '/../storage/cache/themes/default';
    }

    if (!is_dir($cache)) mkdir($cache, 0775, true);

    // === PROCESAR SHORTCODES SI EXISTE 'translation->content' ===
    if (isset($data['translation']) && isset($data['translation']->content)) {
        $data['translation']->content = process_shortcodes($data['translation']->content);
    }
    // ==========================================================

    $blade = new BladeExtended($viewPath, $cache, self::getBladeMode());
    self::registerDirectives($blade);

    try {
        return $blade->run($template, $data);
    } catch (\Exception $e) {
        Logger::log("Blade Theme Error: " . $e->getMessage(), 'ERROR');
        return "Error al renderizar tema: " . $e->getMessage();
    }
}


    public static function renderModule(string $slug, string $view, array $data = [])
    {
        self::loadGlobals();
        $data = array_merge(self::$globalData, $data);

        $viewPath = __DIR__ . "/../modules/{$slug}/views";
        $cache = __DIR__ . "/../storage/cache/modules/{$slug}";

        if (!is_dir($cache)) mkdir($cache, 0775, true);

        // Verificar que el directorio de vistas del módulo exista
        if (!is_dir($viewPath)) {
            Logger::log("Module views directory not found: {$viewPath}", 'ERROR');
            return "Error: Directorio de vistas del módulo '{$slug}' no encontrado";
        }

        $blade = new BladeExtended($viewPath, $cache, self::getBladeMode());

        // Detectar si es una vista de tenant (el path contiene 'tenant/')
        $isTenantView = strpos($view, 'tenant/') !== false || strpos($view, 'tenant.') !== false;

        // Registrar namespace del core para acceso a layouts según el contexto
        if ($isTenantView) {
            $blade->addNamespace('layouts', __DIR__ . '/../core/Views/Tenant/layouts');
        } else {
            $blade->addNamespace('layouts', __DIR__ . '/../core/Views/Superadmin/layouts');
        }
        $blade->addNamespace('superadmin', __DIR__ . '/../core/Views/Superadmin');
        $blade->addNamespace('tenant', __DIR__ . '/../core/Views/Tenant');

        self::registerDirectives($blade);
        self::registerModuleNamespaces($blade); // Muy importante: para que @include de otros módulos funcione

        try {
            // Construir ruta completa del archivo para verificar
            $fullViewPath = $viewPath . '/' . str_replace('.', '/', $view) . '.blade.php';

            Logger::log("View::renderModule - Slug: {$slug}, View: {$view}, Path: {$fullViewPath}", 'DEBUG');

            if (!file_exists($fullViewPath)) {
                Logger::log("Template not found: {$fullViewPath}", 'ERROR');

                // En modo debug, mostrar error detallado
                if (config('debug', false)) {
                    return self::renderModuleTemplateError($slug, $view, $fullViewPath);
                }

                return "Error en módulo {$slug}: Template not found: {$fullViewPath}";
            }

            Logger::log("Ejecutando blade->run con view: {$view}", 'DEBUG');
            $result = $blade->run($view, $data);
            Logger::log("Blade->run completado exitosamente", 'DEBUG');

            return $result;
        } catch (\Exception $e) {
            Logger::log("Blade Module Error ({$slug}): " . $e->getMessage(), 'ERROR');
            Logger::log("Template: {$view}", 'ERROR');
            Logger::log("Stack trace: " . $e->getTraceAsString(), 'ERROR');

            // En modo debug, mostrar error detallado
            if (config('debug', false)) {
                return self::renderModuleTemplateError($slug, $view, $viewPath . '/' . str_replace('.', '/', $view) . '.blade.php', $e);
            }

            return "Error en módulo {$slug}: " . $e->getMessage();
        }
    }

    /**
     * Intenta encontrar una vista en los módulos
     * Busca automáticamente vistas en formato: blog.posts.index -> modules/Blog/views/superadmin/blog/posts/index.blade.php
     *
     * @param string $template Nombre del template (ej: "blog.posts.index")
     * @param string $context Contexto: "superadmin", "tenant", "frontend"
     * @return string|null Ruta del template con namespace o null si no se encuentra
     */
    protected static function tryFindModuleView($template, $context = 'superadmin')
    {
        // Extraer el primer segmento como posible nombre de módulo
        $parts = explode('.', $template);
        if (count($parts) < 2) {
            return null; // No puede ser vista de módulo
        }

        // Normalizar el slug del módulo a kebab-case
        $moduleSlug = normalize_module_slug($parts[0]); // "blog" -> "blog", "Blog" -> "blog"
        $modulePath = APP_ROOT . "/modules/{$moduleSlug}";

        // Verificar si el módulo existe
        if (!is_dir($modulePath)) {
            return null;
        }

        // Para el namespace de Blade, usar PascalCase
        $moduleNamespace = slug_to_namespace($moduleSlug); // "blog" -> "Blog", "media-manager" -> "MediaManager"

        // Construir ruta a la vista: blog.posts.index -> superadmin/blog/posts/index
        $viewPath = $context . '/' . implode('/', $parts);
        $fullPath = $modulePath . '/views/' . $viewPath . '.blade.php';

        // Verificar si el archivo existe
        if (file_exists($fullPath)) {
            // Retornar con namespace de Blade: Blog::superadmin.blog.posts.index
            return $moduleNamespace . '::' . $viewPath;
        }

        return null;
    }

    /**
     * Renderiza un error de template de módulo bonito en modo debug
     *
     * @param string $slug Slug del módulo
     * @param string $view Vista solicitada
     * @param string $fullPath Ruta completa esperada
     * @param \Exception|null $e Excepción capturada (opcional)
     * @return string HTML del error
     */
    protected static function renderModuleTemplateError($slug, $view, $fullPath, $e = null)
    {
        $message = $e ? htmlspecialchars($e->getMessage()) : 'Template no encontrado';
        $trace = $e ? htmlspecialchars($e->getTraceAsString()) : '';
        $slugHtml = htmlspecialchars($slug);
        $viewHtml = htmlspecialchars($view);
        $pathHtml = htmlspecialchars($fullPath);

        $exists = file_exists($fullPath) ? '✓ EXISTE' : '✗ NO EXISTE';
        $color = file_exists($fullPath) ? '#28a745' : '#dc3545';

        $traceSection = $trace ? <<<HTML
            <div class="error-section">
                <h2>Stack Trace:</h2>
                <div class="error-trace">$trace</div>
            </div>
HTML : '';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Module Template Error - MuseDock</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            margin: 0;
        }
        .error-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .error-header {
            background: #dc3545;
            color: white;
            padding: 20px 30px;
        }
        .error-header h1 {
            margin: 0;
            font-size: 24px;
        }
        .error-body {
            padding: 30px;
        }
        .error-section {
            margin-bottom: 25px;
        }
        .error-section h2 {
            font-size: 18px;
            margin-bottom: 10px;
            color: #333;
        }
        .error-code {
            background: #f8f9fa;
            border-left: 4px solid #dc3545;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        .error-trace {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 400px;
            overflow-y: auto;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            background: #17a2b8;
            color: white;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-header">
            <h1>🚨 Module Template Error - Debug Mode</h1>
        </div>
        <div class="error-body">
            <div class="error-section">
                <h2>Template Solicitado:</h2>
                <div class="error-code">
                    <strong>Módulo:</strong> <span class="badge">$slugHtml</span><br>
                    <strong>Vista:</strong> $viewHtml<br>
                    <strong>Ruta esperada:</strong> $pathHtml<br>
                    <strong>Estado:</strong> <span style="color: $color; font-weight: bold;">$exists</span>
                </div>
            </div>

            <div class="error-section">
                <h2>Mensaje de Error:</h2>
                <div class="error-code">$message</div>
            </div>

            $traceSection

            <div class="error-section">
                <h2>💡 Posibles Soluciones:</h2>
                <ul>
                    <li>Verifica que el archivo de vista exista en: <code>/modules/$slugHtml/views/$viewHtml.blade.php</code></li>
                    <li>Asegúrate de que el nombre del archivo termine en <code>.blade.php</code></li>
                    <li>Verifica los permisos de lectura del archivo y directorio</li>
                    <li>Si acabas de crear el archivo, intenta limpiar la caché: <code>rm -rf storage/cache/modules/$slugHtml/*</code></li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Renderiza un error de template bonito en modo debug
     *
     * @param string $template Nombre del template que falló
     * @param \Exception $e Excepción capturada
     * @param string $context Contexto: "superadmin", "tenant", "theme"
     * @return string HTML del error
     */
    protected static function renderTemplateError($template, $e, $context = 'superadmin')
    {
        $message = htmlspecialchars($e->getMessage());
        $trace = htmlspecialchars($e->getTraceAsString());
        $templateHtml = htmlspecialchars($template);

        // Intentar sugerir ubicaciones
        $suggestions = [];
        $parts = explode('.', $template);

        if (count($parts) > 0) {
            // Usar kebab-case para la carpeta del módulo
            $moduleSlug = normalize_module_slug($parts[0]);
            $modulePath = APP_ROOT . "/modules/{$moduleSlug}";

            if (is_dir($modulePath)) {
                $suggestions[] = $modulePath . '/views/' . $context . '/' . implode('/', $parts) . '.blade.php';
            }
        }

        $suggestions[] = APP_ROOT . '/core/Views/' . ucfirst($context) . '/' . str_replace('.', '/', $template) . '.blade.php';

        $suggestionsHtml = '';
        foreach ($suggestions as $suggestion) {
            $exists = file_exists($suggestion) ? '✓ EXISTE' : '✗ NO EXISTE';
            $color = file_exists($suggestion) ? '#28a745' : '#dc3545';
            $suggestionsHtml .= "<li style='margin: 5px 0; font-family: monospace;'><span style='color: $color; font-weight: bold;'>$exists</span> $suggestion</li>";
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Template Error - MuseDock</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            margin: 0;
        }
        .error-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .error-header {
            background: #dc3545;
            color: white;
            padding: 20px 30px;
        }
        .error-header h1 {
            margin: 0;
            font-size: 24px;
        }
        .error-body {
            padding: 30px;
        }
        .error-section {
            margin-bottom: 25px;
        }
        .error-section h2 {
            font-size: 18px;
            margin-bottom: 10px;
            color: #333;
        }
        .error-code {
            background: #f8f9fa;
            border-left: 4px solid #dc3545;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        .error-trace {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 400px;
            overflow-y: auto;
        }
        .suggestions {
            list-style: none;
            padding: 0;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            background: #17a2b8;
            color: white;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-header">
            <h1>🚨 Template Not Found - Debug Mode</h1>
        </div>
        <div class="error-body">
            <div class="error-section">
                <h2>Template Solicitado:</h2>
                <div class="error-code">
                    <strong>Template:</strong> $templateHtml<br>
                    <strong>Context:</strong> <span class="badge">$context</span>
                </div>
            </div>

            <div class="error-section">
                <h2>Mensaje de Error:</h2>
                <div class="error-code">$message</div>
            </div>

            <div class="error-section">
                <h2>Ubicaciones Buscadas:</h2>
                <ul class="suggestions">$suggestionsHtml</ul>
            </div>

            <div class="error-section">
                <h2>Stack Trace:</h2>
                <div class="error-trace">$trace</div>
            </div>

            <div class="error-section">
                <h2>💡 Posibles Soluciones:</h2>
                <ul>
                    <li>Verifica que el archivo de vista exista en la ubicación correcta</li>
                    <li>Asegúrate de que el nombre del módulo tenga la primera letra mayúscula en la carpeta (ej: "Blog" no "blog")</li>
                    <li>Verifica que la estructura de directorios sea: modules/NombreModulo/views/$context/...</li>
                    <li>Si es una vista de core, debe estar en: core/Views/Superadmin/...</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
