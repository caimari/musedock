<?php
namespace Screenart\Musedock;

class Route {
    private static $routes = [];
    private static $middlewares = [];
	private static $namedRoutes = [];
	private static $groupStack = [];
    private static $lastRoute;

	 public static function get($path, $handler) {
		// Verificar si hay grupo activo
		$prefix = '';
		$middlewares = [];

		if (!empty(self::$groupStack)) {
			$group = end(self::$groupStack);
			$prefix = $group['prefix'] ?? '';
			$middlewares = $group['middleware'] ?? [];
		}

		// Concatenar prefijo
		$fullPath = rtrim($prefix . '/' . ltrim($path, '/'), '/');
		$fullPath = $fullPath === '' ? '/' : $fullPath;

		self::$routes['GET'][$fullPath] = $handler;
		self::$middlewares['GET'][$fullPath] = $middlewares;
		self::$lastRoute = ['method' => 'GET', 'path' => $fullPath];

		return new static;
	}

	public static function post($path, $handler) {
		// Verificar si hay grupo activo
		$prefix = '';
		$middlewares = [];

		if (!empty(self::$groupStack)) {
			$group = end(self::$groupStack);
			$prefix = $group['prefix'] ?? '';
			$middlewares = $group['middleware'] ?? [];
		}

		// Concatenar prefijo
		$fullPath = rtrim($prefix . '/' . ltrim($path, '/'), '/');
		$fullPath = $fullPath === '' ? '/' : $fullPath;

		self::$routes['POST'][$fullPath] = $handler;
		self::$middlewares['POST'][$fullPath] = $middlewares;
		self::$lastRoute = ['method' => 'POST', 'path' => $fullPath];

		return new static;
	}

	public static function put($path, $handler) {
    // Verificar si hay grupo activo
    $prefix = '';
    $middlewares = [];

    if (!empty(self::$groupStack)) {
        $group = end(self::$groupStack);
        $prefix = $group['prefix'] ?? '';
        $middlewares = $group['middleware'] ?? [];
    }

    // Concatenar prefijo
    $fullPath = rtrim($prefix . '/' . ltrim($path, '/'), '/');
    $fullPath = $fullPath === '' ? '/' : $fullPath;

    self::$routes['PUT'][$fullPath] = $handler;
    self::$middlewares['PUT'][$fullPath] = $middlewares;
    self::$lastRoute = ['method' => 'PUT', 'path' => $fullPath];

    return new static;
	}

	public static function delete($path, $handler) {
		// Verificar si hay grupo activo
		$prefix = '';
		$middlewares = [];

		if (!empty(self::$groupStack)) {
			$group = end(self::$groupStack);
			$prefix = $group['prefix'] ?? '';
			$middlewares = $group['middleware'] ?? [];
		}

		// Concatenar prefijo
		$fullPath = rtrim($prefix . '/' . ltrim($path, '/'), '/');
		$fullPath = $fullPath === '' ? '/' : $fullPath;

		self::$routes['DELETE'][$fullPath] = $handler;
		self::$middlewares['DELETE'][$fullPath] = $middlewares;
		self::$lastRoute = ['method' => 'DELETE', 'path' => $fullPath];

		return new static;
	}
	
		public static function patch($path, $handler) {
		// Verificar si hay grupo activo
		$prefix = '';
		$middlewares = [];

		if (!empty(self::$groupStack)) {
			$group = end(self::$groupStack);
			$prefix = $group['prefix'] ?? '';
			$middlewares = $group['middleware'] ?? [];
		}

		// Concatenar prefijo
		$fullPath = rtrim($prefix . '/' . ltrim($path, '/'), '/');
		$fullPath = $fullPath === '' ? '/' : $fullPath;

		self::$routes['PATCH'][$fullPath] = $handler;
		self::$middlewares['PATCH'][$fullPath] = $middlewares;
		self::$lastRoute = ['method' => 'PATCH', 'path' => $fullPath];

		return new static;
	}

	public static function options($path, $handler) {
		// Verificar si hay grupo activo
		$prefix = '';
		$middlewares = [];

		if (!empty(self::$groupStack)) {
			$group = end(self::$groupStack);
			$prefix = $group['prefix'] ?? '';
			$middlewares = $group['middleware'] ?? [];
		}

		// Concatenar prefijo
		$fullPath = rtrim($prefix . '/' . ltrim($path, '/'), '/');
		$fullPath = $fullPath === '' ? '/' : $fullPath;

		self::$routes['OPTIONS'][$fullPath] = $handler;
		self::$middlewares['OPTIONS'][$fullPath] = $middlewares;
		self::$lastRoute = ['method' => 'OPTIONS', 'path' => $fullPath];

		return new static;
	}

    public static function head($path, $handler) {
    $prefix = '';
    $middlewares = [];

    if (!empty(self::$groupStack)) {
        $group = end(self::$groupStack);
        $prefix = $group['prefix'] ?? '';
        $middlewares = $group['middleware'] ?? [];
    }

    $fullPath = rtrim($prefix . '/' . ltrim($path, '/'), '/');
    $fullPath = $fullPath === '' ? '/' : $fullPath;

    self::$routes['HEAD'][$fullPath] = $handler;
    self::$middlewares['HEAD'][$fullPath] = $middlewares;
    self::$lastRoute = ['method' => 'HEAD', 'path' => $fullPath];

    return new static;
}  
	
	public static function any($path, $handler) {
    $prefix = '';
    $middlewares = [];

    if (!empty(self::$groupStack)) {
        $group = end(self::$groupStack);
        $prefix = $group['prefix'] ?? '';
        $middlewares = $group['middleware'] ?? [];
    }

    $fullPath = rtrim($prefix . '/' . ltrim($path, '/'), '/');
    $fullPath = $fullPath === '' ? '/' : $fullPath;

    // Registrar para todos los métodos posibles
    $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

    foreach ($methods as $method) {
        self::$routes[$method][$fullPath] = $handler;
        self::$middlewares[$method][$fullPath] = $middlewares;
    }

    self::$lastRoute = ['method' => 'ANY', 'path' => $fullPath];

    return new static;
}
	
    public function middleware($middlewares) {
        $middlewares = is_array($middlewares) ? $middlewares : [$middlewares];
        $method = self::$lastRoute['method'];
        $path = self::$lastRoute['path'];
        self::$middlewares[$method][$path] = $middlewares;
        return $this;
    }
	
	public static function group(array $attributes, callable $callback)
	{
		// Añadir grupo al stack
		self::$groupStack[] = $attributes;

		// Ejecutar las rutas del grupo
		$callback();

		// Quitar grupo del stack después
		array_pop(self::$groupStack);
	}

	
	public function name(string $name) {
    $method = self::$lastRoute['method'];
    $path = self::$lastRoute['path'];
    self::$namedRoutes[$name] = ['method' => $method, 'path' => $path];
    return $this;
	}


public static function resolve() {
    // Usar SessionSecurity para iniciar sesión correctamente
    // Esto maneja expiración, regeneración de ID, y restauración desde "remember me"
    \Screenart\Musedock\Security\SessionSecurity::startSession();

    // DEBUG: Log estado de sesión para diagnóstico de AJAX
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/musedock/run-seeders') !== false) {
        error_log("=== DEBUG run-seeders ===");
        error_log("Session ID: " . session_id());
        error_log("PHPSESSID cookie: " . ($_COOKIE['PHPSESSID'] ?? 'NOT SET'));
        error_log("Session keys: " . json_encode(array_keys($_SESSION)));
        error_log("Has _csrf_token: " . (isset($_SESSION['_csrf_token']) ? 'YES' : 'NO'));
        error_log("Has super_admin: " . (isset($_SESSION['super_admin']) ? 'YES' : 'NO'));
        error_log("POST _token: " . ($_POST['_token'] ?? 'NOT SET'));
        error_log("=========================");
    }

    // Detectar si nos mandan ?lang=xx en la URL
    if (isset($_GET['lang'])) {
        $_SESSION['lang'] = $_GET['lang'];
    }

    // --- PROTECCIÓN CSRF GLOBAL ---
    // Verificar CSRF en todas las peticiones que modifiquen datos
    $method = $_SERVER['REQUEST_METHOD'];

    // Soporte para method spoofing (como Laravel)
    // Los formularios HTML solo soportan GET y POST, pero podemos simular PUT/PATCH/DELETE
    if ($method === 'POST' && isset($_POST['_method'])) {
        error_log("ROUTE: Method spoofing detectado - _method=" . $_POST['_method']);
        $spoofedMethod = strtoupper($_POST['_method']);
        if (in_array($spoofedMethod, ['PUT', 'PATCH', 'DELETE'])) {
            $method = $spoofedMethod;
            error_log("ROUTE: Método cambiado de POST a {$method}");
        }
    }

    error_log("ROUTE: Método final para routing: {$method}");

    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
        // Rutas excluidas de CSRF (APIs públicas y rutas internas seguras)
        $csrfExcludedRoutes = [
            '/api/analytics/track',
            '/api/webhooks/',  // Para futuros webhooks
            '/clear-flashes',  // Limpieza de flash messages (ya verifica sesión admin internamente)
        ];

        $currentUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
        $skipCsrf = false;

        foreach ($csrfExcludedRoutes as $excludedRoute) {
            // Verificar si la ruta comienza con el patrón O si termina con el patrón
            // Esto permite excluir /api/analytics/track (prefix) y /admin/clear-flashes (suffix)
            if (strpos($currentUri, $excludedRoute) === 0 ||
                substr($currentUri, -strlen($excludedRoute)) === $excludedRoute) {
                $skipCsrf = true;
                break;
            }
        }

        if (!$skipCsrf) {
            $middlewareRegistry = require __DIR__ . '/MiddlewareRegistry.php';
            if (isset($middlewareRegistry['csrf'])) {
                $csrfMiddleware = new $middlewareRegistry['csrf']();
                if (!$csrfMiddleware->handle()) {
                    return; // CSRF falló, el middleware ya manejó la respuesta
                }
            }
        }
    }
    // --------------------------------------------------------

    // --- Ejecutar siempre el middleware de idioma ---
    $middlewareRegistry = require __DIR__ . '/MiddlewareRegistry.php';
    if (isset($middlewareRegistry['language'])) {
        $languageMiddlewareClass = $middlewareRegistry['language'];
        $languageMiddleware = new $languageMiddlewareClass();
        $languageMiddleware->handle();
    }
    // --------------------------------------------------------

    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
    $uri = rtrim($uri, '/');
    $uri = $uri === '' ? '/' : $uri;

    // Solo loguear en modo debug
    $isDebug = \Screenart\Musedock\Env::get('APP_DEBUG', false);
    if ($isDebug) {
        error_log("ROUTE: Buscando ruta - Método: {$method}, URI: {$uri}");
        error_log("ROUTE: Rutas disponibles para {$method}: " . (isset(self::$routes[$method]) ? implode(', ', array_keys(self::$routes[$method])) : 'ninguna'));
    }

    // Buscar coincidencias exactas primero
    if (isset(self::$routes[$method][$uri])) {
        if ($isDebug) {
            error_log("ROUTE: Coincidencia exacta encontrada para {$method} {$uri}");
        }
        $handler = self::$routes[$method][$uri];
        $middlewares = self::$middlewares[$method][$uri] ?? [];
        if (!self::runMiddlewares($middlewares)) return;
        return self::callHandler($handler);
    }

    // Buscar coincidencias con parámetros dinámicos
    if (isset(self::$routes[$method]) && is_array(self::$routes[$method])) {
        foreach (self::$routes[$method] as $route => $handler) {
            // Soporte para parámetros wildcard {param:.*} que capturan todo (incluyendo /)
            // y parámetros normales {param} que capturan hasta el siguiente /
            $routePattern = preg_replace_callback('#\{([^\}:]+)(:([^\}]+))?\}#', function($matches) {
                $paramName = $matches[1];
                $pattern = $matches[3] ?? null;

                // Si hay un patrón personalizado como {path:.*}, usar ese patrón
                if ($pattern) {
                    return '(' . $pattern . ')';
                }

                // Por defecto, capturar hasta el siguiente /
                return '([^/]+)';
            }, $route);

            $routeRegex = "#^" . $routePattern . "$#";

            if (preg_match($routeRegex, $uri, $matches)) {
                if ($isDebug) {
                    error_log("ROUTE: Coincidencia dinámica encontrada - Ruta: {$route}, URI: {$uri}");
                }
                array_shift($matches); // Quitamos el match completo
                $middlewares = self::$middlewares[$method][$route] ?? [];
                if (!self::runMiddlewares($middlewares)) return;
                return self::callHandler($handler, $matches);
            }
        }
    }

    // Si no se encuentra la ruta -> error 404 bonito
    if ($isDebug) {
        error_log("ROUTE: No se encontró ninguna ruta para {$method} {$uri}");
    }
    http_response_code(404);

    self::render404Page();
}

/**
 * Renderiza una página 404 atractiva.
 * Intenta usar la plantilla del tema, si falla usa un HTML genérico.
 */
private static function render404Page(): void
{
    // Limpiar cualquier output previo que pueda interferir
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Intento 1: Plantilla del tema activo
    try {
        ob_start();
        $output = \Screenart\Musedock\View::renderTheme('errors.404');
        ob_end_clean();
        echo $output;
        return;
    } catch (\Throwable $e) {
        ob_end_clean();
        error_log("404: Falló renderTheme errors.404 - " . $e->getMessage());
    }

    // Intento 2: Plantilla base del sistema
    try {
        ob_start();
        $output = \Screenart\Musedock\View::render('errors.404');
        ob_end_clean();
        echo $output;
        return;
    } catch (\Throwable $e) {
        ob_end_clean();
        error_log("404: Falló render errors.404 - " . $e->getMessage());
    }

    // Intento 3: HTML genérico atractivo (sin dependencias)
    self::renderGeneric404Html();
}

/**
 * Renderiza un HTML 404 genérico y atractivo sin dependencias externas.
 */
private static function renderGeneric404Html(): void
{
    $requestUri = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/', ENT_QUOTES, 'UTF-8');
    $homeUrl = '/';

    // Detectar si hay un tenant para obtener el nombre del sitio
    $siteName = 'Este sitio';
    if (isset($GLOBALS['tenant']['name'])) {
        $siteName = htmlspecialchars($GLOBALS['tenant']['name'], ENT_QUOTES, 'UTF-8');
    }

    echo <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Página no encontrada</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 20px;
        }

        .container {
            text-align: center;
            max-width: 600px;
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .error-code {
            font-size: clamp(100px, 20vw, 180px);
            font-weight: 800;
            line-height: 1;
            text-shadow: 4px 4px 0 rgba(0,0,0,0.1);
            margin-bottom: 20px;
            background: linear-gradient(180deg, #fff 0%, rgba(255,255,255,0.7) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .error-title {
            font-size: clamp(24px, 5vw, 36px);
            font-weight: 600;
            margin-bottom: 15px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }

        .error-message {
            font-size: 18px;
            opacity: 0.9;
            margin-bottom: 40px;
            line-height: 1.6;
        }

        .buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 28px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: #fff;
            color: #667eea;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }

        .btn-secondary {
            background: rgba(255,255,255,0.15);
            color: #fff;
            border: 2px solid rgba(255,255,255,0.3);
        }

        .btn-secondary:hover {
            background: rgba(255,255,255,0.25);
            border-color: rgba(255,255,255,0.5);
        }

        .illustration {
            margin-bottom: 30px;
        }

        .illustration svg {
            width: 120px;
            height: 120px;
            opacity: 0.9;
        }

        .site-name {
            margin-top: 50px;
            font-size: 14px;
            opacity: 0.7;
        }

        @media (max-width: 480px) {
            .buttons {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="illustration">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <path d="M16 16s-1.5-2-4-2-4 2-4 2"/>
                <line x1="9" y1="9" x2="9.01" y2="9"/>
                <line x1="15" y1="9" x2="15.01" y2="9"/>
            </svg>
        </div>

        <div class="error-code">404</div>

        <h1 class="error-title">Página no encontrada</h1>

        <p class="error-message">
            Lo sentimos, la página que buscas no existe o ha sido movida.<br>
            Puede que el enlace esté roto o la dirección sea incorrecta.
        </p>

        <div class="buttons">
            <a href="{$homeUrl}" class="btn btn-primary">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                Ir al inicio
            </a>
            <button onclick="history.back()" class="btn btn-secondary">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"/>
                    <polyline points="12 19 5 12 12 5"/>
                </svg>
                Volver atrás
            </button>
        </div>

        <p class="site-name">{$siteName}</p>
    </div>
</body>
</html>
HTML;
}



private static function callHandler($handler, $params = [])
{
    if (is_callable($handler)) {
        echo call_user_func_array($handler, $params);
        return;
    }

    if (is_string($handler) && str_contains($handler, '@')) {
        list($controllerPart, $method) = explode('@', $handler, 2);

        $fullClassName = null;
        $controllerInstance = null;

        // === Intento 0: Namespace completo (nuevo) ===
        if (str_contains($controllerPart, '\\')) {
            $fullClassName = $controllerPart;
            
            Logger::debug("Intento cargar controlador con namespace completo: {$fullClassName}");
            
            if (class_exists($fullClassName)) {
                try {
                    $controllerInstance = new $fullClassName();
                    Logger::debug("Se instanció correctamente el controlador con namespace completo: {$fullClassName}");
                } catch (\Throwable $e) {
                    Logger::exception($e, 'ERROR', [
                        'source' => 'Route::callHandler',
                        'handler' => $handler,
                        'message' => 'Error instanciando controlador con namespace completo'
                    ]);
                }
            } else {
                Logger::error("La clase '{$fullClassName}' no existe o no se puede cargar automáticamente.");
                
                // Intento adicional - buscar el archivo manualmente
                $namespaceSegments = explode('\\', $controllerPart);
                $className = array_pop($namespaceSegments);
                
                // Buscar si estamos en un módulo
                if (count($namespaceSegments) >= 2 && $namespaceSegments[0] !== 'Screenart') {
                    $moduleDir = APP_ROOT . '/modules/' . $namespaceSegments[0];
                    $possiblePath = $moduleDir . '/' . strtolower($namespaceSegments[1]) . '/' . $className . '.php';
                    
                    Logger::debug("Intentando cargar manualmente: {$possiblePath}");
                    
                    if (file_exists($possiblePath)) {
                        require_once $possiblePath;
                        if (class_exists($fullClassName)) {
                            try {
                                $controllerInstance = new $fullClassName();
                                Logger::debug("Se cargó e instanció manualmente: {$fullClassName}");
                            } catch (\Throwable $e) {
                                Logger::exception($e, 'ERROR', [
                                    'source' => 'Route::callHandler',
                                    'handler' => $handler,
                                    'message' => 'Error instanciando controlador cargado manualmente'
                                ]);
                            }
                        }
                    }
                }
            }
        }

        // === Intento 1: Módulos ===
        if ($controllerInstance === null && str_contains($controllerPart, '.')) {
            list($moduleSlug, $controllerName) = explode('.', $controllerPart, 2);
            $moduleNamespace = slugToPascalCase($moduleSlug);
            $controllerClass = slugToPascalCase($controllerName);

            $fullClassName = "Screenart\\Musedock\\Modules\\{$moduleNamespace}\\Controllers\\{$controllerClass}";
            
            Logger::debug("Intento cargar controlador de módulo: {$fullClassName}");

            if (class_exists($fullClassName)) {
                try {
                    $controllerInstance = new $fullClassName();
                    Logger::debug("Se instanció correctamente el controlador de módulo: {$fullClassName}");
                } catch (\Throwable $e) {
                    Logger::exception($e, 'ERROR', [
                        'source' => 'Route::callHandler',
                        'handler' => $handler,
                        'message' => 'Error instanciando controlador de módulo'
                    ]);
                }
            }
        }

        // === Intento 2: Core ===
        if ($controllerInstance === null && str_contains($controllerPart, '.')) {
            list($prefix, $controllerName) = explode('.', $controllerPart, 2);
            $prefixNamespace = slugToPascalCase($prefix);
            $controllerClass = slugToPascalCase($controllerName);

            $fullClassName = "Screenart\\Musedock\\Controllers\\{$prefixNamespace}\\{$controllerClass}";
            
            Logger::debug("Intento cargar controlador de core: {$fullClassName}");

            if (class_exists($fullClassName)) {
                try {
                    $controllerInstance = new $fullClassName();
                    Logger::debug("Se instanció correctamente el controlador de core: {$fullClassName}");
                } catch (\Throwable $e) {
                    Logger::exception($e, 'ERROR', [
                        'source' => 'Route::callHandler',
                        'handler' => $handler,
                        'message' => 'Error instanciando controlador de core'
                    ]);
                    http_response_code(500);
                    echo "Error interno: No se pudo instanciar el controlador.";
                    return;
                }
            }
        }

        // === Ejecución del método ===
        if ($controllerInstance !== null) {
            try {
                // === SEGURIDAD: Verificar que el método tiene checkPermission() ===
                // Solo aplicar a controladores de Superadmin
                if (str_contains($fullClassName, 'Controllers\\Superadmin\\')) {
                    if (!Middlewares\EnforcePermissionMiddleware::handle($fullClassName, $method)) {
                        return; // Acceso denegado - el middleware ya redirigió
                    }
                }

                $refMethod = new \ReflectionMethod($controllerInstance, $method);

                if (!$refMethod->isPublic()) {
                    throw new \RuntimeException("Método '{$method}' no es público.");
                }

                // Ejecutar método y capturar el resultado
                $result = $refMethod->invokeArgs($controllerInstance, $params);

                // Si el método devuelve algo → imprimirlo automáticamente
                if ($result !== null) {
                    echo $result;
                }

                return;

            } catch (\ReflectionException $e) {
                Logger::error("Método '{$method}' no encontrado en la clase '" . get_class($controllerInstance) . "'.");
                \Screenart\Musedock\ErrorHandler::http(500, "Método '{$method}' no encontrado.");
                return;

            } catch (\Throwable $e) {
                Logger::exception($e, 'ERROR', [
                    'source' => 'Route::callHandler',
                    'handler' => $handler,
                    'message' => 'Error al ejecutar el método del controlador'
                ]);
                \Screenart\Musedock\ErrorHandler::render($e, 500, 'Error en la Aplicación');
                return;
            }
        }

        Logger::error("Error: Controlador o clase '{$controllerPart}' no encontrado en core ni en módulos.");
        \Screenart\Musedock\ErrorHandler::http(500, "Controlador o clase '{$controllerPart}' no encontrado.");
        return;
    }

    Logger::error("Handler inválido proporcionado a la ruta.", ['handler' => $handler]);
    \Screenart\Musedock\ErrorHandler::http(500, "Handler inválido.");
}
	
public static function getPathByName(string $name, array $params = []): ?string
{
    if (!isset(self::$namedRoutes[$name])) {
        return null;
    }

    $path = self::$namedRoutes[$name]['path'];

    // Reemplazar los {parametros} por valores reales si existen
    foreach ($params as $key => $value) {
        if ($value !== null) {
            $path = preg_replace('/\{' . preg_quote($key, '/') . '\}/', (string)$value, $path);
        }
    }

    // Eliminar cualquier parámetro sin reemplazar (opcional)
    // Aquí está la corrección para evitar el problema con null
    $path = preg_replace('/\{[^\}]+\}/', '', $path);
    
    // También podemos hacer el reemplazo más explícito:
    // $path = preg_replace('/\{[^\}]+\}/', (string)'', $path);
    
    $path = rtrim($path, '/');

    return $path ?: '/';
}

private static function runMiddlewares(array $middlewares): bool
{
    $middlewareRegistry = require __DIR__ . '/MiddlewareRegistry.php';
    $isDebug = \Screenart\Musedock\Env::get('APP_DEBUG', false);

    // Iniciar registro de ejecución de middlewares (solo en debug)
    if ($isDebug) {
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $middlewareChain = [];
        $startTime = microtime(true);

        error_log("==================== INICIO EJECUCIÓN MIDDLEWARES ====================");
        error_log("URL: $requestUri");
        error_log("Middleware chain: " . json_encode($middlewares));

        // Detectar tipo de usuario para diagnóstico
        $userType = 'guest';
        if (isset($_SESSION['super_admin'])) $userType = 'super_admin';
        elseif (isset($_SESSION['admin'])) $userType = 'admin';
        elseif (isset($_SESSION['user'])) $userType = 'user';
        error_log("Tipo de usuario: $userType");

        // Información adicional para admins (detectar bucles)
        if ($userType === 'admin') {
            $referer = $_SERVER['HTTP_REFERER'] ?? 'none';
            error_log("Referrer: $referer");

            // Verificar posibles bucles de redirección
            if (strpos($referer, $requestUri) !== false) {
                error_log("⚠️ POSIBLE BUCLE DE REDIRECCIÓN DETECTADO ($referer -> $requestUri)");
            }
        }
    }

    foreach ($middlewares as $index => $entry) {
        $middlewareName = is_string($entry) ? $entry : get_class($entry);

        if ($isDebug) {
            $middlewareStart = microtime(true);
            error_log("[$index] Ejecutando middleware: $middlewareName");
        }

        // Si la entrada es string, puede llevar parámetro tras ":"
        if (is_string($entry)) {
            // Separa nombre y parámetro (si lo hay)
            list($name, $param) = array_pad(explode(':', $entry, 2), 2, null);

            if (!isset($middlewareRegistry[$name])) {
                if ($isDebug) {
                    error_log("[$index] Error: Middleware '{$name}' no registrado");
                }
                return false;
            }

            $class = $middlewareRegistry[$name];

            if ($isDebug) {
                error_log("[$index] Clase a instanciar: $class" . ($param ? " con parámetro: $param" : ""));
            }

            // Instancia con o sin parámetro
            $instance = $param
                ? new $class($param)
                : new $class();
        }
        // Si hubiera objetos, podrías cogerlos directamente (opcional)
        elseif (is_object($entry)) {
            $instance = $entry;
            if ($isDebug) {
                error_log("[$index] Instancia directa de: " . get_class($instance));
            }
        }
        else {
            if ($isDebug) {
                error_log("[$index] Error: Middleware inválido");
            }
            return false;
        }

        // Comprobación de método handle()
        if (!method_exists($instance, 'handle')) {
            if ($isDebug) {
                error_log("[$index] Error: El middleware '". get_class($instance) ."' no tiene método handle()");
            }
            return false;
        }

        // Ejecución del middleware con registro de tiempo
        $result = null;
        try {
            if ($isDebug) {
                error_log("[$index] Iniciando ejecución del middleware");
            }

            $result = $instance->handle();

            if ($isDebug) {
                $middlewareEnd = microtime(true);
                $executionTime = round(($middlewareEnd - $middlewareStart) * 1000, 2);
            }

            if ($result === false) {
                if ($isDebug) {
                    error_log("[$index] Middleware interrumpido con resultado FALSE ($executionTime ms)");
                    error_log("==================== FIN EJECUCIÓN MIDDLEWARES (INTERRUMPIDO) ====================");
                }
                return false;
            }

            if ($isDebug) {
                error_log("[$index] Middleware completado con éxito ($executionTime ms)");
                $middlewareChain[] = $middlewareName;
            }

        } catch (\Exception $e) {
            // Los errores críticos siempre se loguean
            error_log("[$index] EXCEPCIÓN en middleware: " . $e->getMessage());
            if ($isDebug) {
                error_log("[$index] Traza: " . $e->getTraceAsString());
                error_log("==================== FIN EJECUCIÓN MIDDLEWARES (EXCEPCIÓN) ====================");
            }
            return false;
        }
    }

    if ($isDebug) {
        $totalTime = round((microtime(true) - $startTime) * 1000, 2);
        error_log("Cadena completa de middlewares ejecutados: " . implode(' -> ', $middlewareChain));
        error_log("Tiempo total de ejecución: $totalTime ms");
        error_log("==================== FIN EJECUCIÓN MIDDLEWARES (ÉXITO) ====================");
    }

    return true;
}


}
