<?php

use Screenart\Musedock\Database;
use Screenart\Musedock\Models\Language;
use Screenart\Musedock\Helpers\SliderHelper;

if (!function_exists('debug_log')) {
    /**
     * Log de debug que solo escribe cuando APP_DEBUG=true
     * Usar en lugar de error_log() para mensajes informativos
     *
     * @param string $message
     * @return void
     */
    function debug_log(string $message): void {
        static $debug = null;
        if ($debug === null) {
            $debug = \Screenart\Musedock\Env::get('APP_DEBUG', false);
        }
        if ($debug) {
            error_log($message);
        }
    }
}

if (!function_exists('env')) {
    /**
     * Get environment variable value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function env($key, $default = null) {
        return \Screenart\Musedock\Env::get($key, $default);
    }
}

if (!function_exists('dd')) {
    function dd($var) {
        echo "<pre>";
        var_dump($var);
        echo "</pre>";
        die;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token() {
        try {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                error_log("csrf_token(): Sesión no activa, iniciando sesión...");
                session_start();
            }

            if (empty($_SESSION['_csrf_token'])) {
                $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
                error_log("csrf_token(): Token CSRF generado: " . substr($_SESSION['_csrf_token'], 0, 10) . "...");
            }

            $token = $_SESSION['_csrf_token'] ?? '';

            if (empty($token)) {
                error_log("csrf_token(): WARNING - Token está vacío después de generación!");
            }

            return $token;
        } catch (\Exception $e) {
            error_log("csrf_token(): ERROR - " . $e->getMessage());
            return '';
        }
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token()) . '">';
    }
}

if (!function_exists('validate_csrf')) {
    function validate_csrf($token) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return isset($_SESSION['_csrf_token']) && hash_equals($_SESSION['_csrf_token'], $token);
    }
}

if (!function_exists('validate')) {
    function validate(array $rules, array $data = null) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $data = $data ?? $_POST;
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            $rulesList = explode('|', $ruleString);

            foreach ($rulesList as $rule) {
                if ($rule === 'required' && (!$value || trim($value) === '')) {
                    $errors[$field][] = 'Este campo es obligatorio.';
                }

                if ($rule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = 'Debe ser un correo electrónico válido.';
                }

                if (str_starts_with($rule, 'min:')) {
                    $min = (int) explode(':', $rule)[1];
                    if (strlen($value) < $min) {
                        $errors[$field][] = "Debe tener al menos $min caracteres.";
                    }
                }

                if (str_starts_with($rule, 'max:')) {
                    $max = (int) explode(':', $rule)[1];
                    if (strlen($value) > $max) {
                        $errors[$field][] = "No puede superar los $max caracteres.";
                    }
                }
            }
        }

        $_SESSION['errors'] = $errors;
        $_SESSION['old'] = $data;

        return empty($errors);
    }
}

if (!function_exists('errors')) {
    function errors($field = null) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $all = $_SESSION['errors'] ?? [];

        return $field ? ($all[$field] ?? []) : $all;
    }
}

if (!function_exists('form_error')) {
    function form_error($field) {
        $messages = errors($field);
        if (!empty($messages)) {
            $html = '<div class="text-danger small mt-1">';
            foreach ($messages as $msg) {
                $html .= htmlspecialchars($msg) . '<br>';
            }
            $html .= '</div>';
            return $html;
        }
        return '';
    }
}

if (!function_exists('validate_password_strength')) {
    /**
     * Valida la robustez de una contraseña según los requisitos de seguridad
     *
     * @param string $password Contraseña a validar
     * @return bool True si la contraseña es válida
     */
    function validate_password_strength($password) {
        $config = require __DIR__ . '/../config/config.php';
        $security = $config['security'] ?? [];

        $minLength = $security['password_min_length'] ?? 12;
        $requireUppercase = $security['password_require_uppercase'] ?? true;
        $requireLowercase = $security['password_require_lowercase'] ?? true;
        $requireNumbers = $security['password_require_numbers'] ?? true;
        $requireSpecial = $security['password_require_special'] ?? true;

        // Verificar longitud mínima
        if (strlen($password) < $minLength) {
            return false;
        }

        // Verificar mayúsculas
        if ($requireUppercase && !preg_match('/[A-Z]/', $password)) {
            return false;
        }

        // Verificar minúsculas
        if ($requireLowercase && !preg_match('/[a-z]/', $password)) {
            return false;
        }

        // Verificar números
        if ($requireNumbers && !preg_match('/[0-9]/', $password)) {
            return false;
        }

        // Verificar caracteres especiales
        if ($requireSpecial && !preg_match('/[^A-Za-z0-9]/', $password)) {
            return false;
        }

        // Verificar contra contraseñas comunes
        $commonPasswords = [
            'password', '123456', '12345678', 'qwerty', 'abc123',
            'monkey', '1234567', 'letmein', 'trustno1', 'dragon',
            'baseball', 'iloveyou', 'master', 'sunshine', 'ashley',
            'bailey', 'passw0rd', 'shadow', '123123', '654321'
        ];

        if (in_array(strtolower($password), $commonPasswords)) {
            return false;
        }

        return true;
    }
}

if (!function_exists('password_requirements_text')) {
    /**
     * Devuelve el texto de requisitos de contraseña
     *
     * @return string
     */
    function password_requirements_text() {
        $config = require __DIR__ . '/../config/config.php';
        $security = $config['security'] ?? [];

        $minLength = $security['password_min_length'] ?? 12;

        $requirements = [];
        $requirements[] = "Al menos {$minLength} caracteres";

        if ($security['password_require_uppercase'] ?? true) {
            $requirements[] = "una letra mayúscula";
        }

        if ($security['password_require_lowercase'] ?? true) {
            $requirements[] = "una letra minúscula";
        }

        if ($security['password_require_numbers'] ?? true) {
            $requirements[] = "un número";
        }

        if ($security['password_require_special'] ?? true) {
            $requirements[] = "un carácter especial";
        }

        return implode(', ', $requirements);
    }
}

if (!function_exists('old')) {
    /**
     * Recupera un valor de entrada "antiguo" flasheado a la sesión.
     *
     * @param string|null $key La clave del valor a recuperar.
     * @param mixed $default El valor por defecto si no se encuentra el antiguo.
     * @return mixed
     */
    function old(?string $key = null, $default = null)
    {
        // Asegurarse que la sesión está iniciada
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // Considera loggear un warning si la sesión no está iniciada aquí,
            // pero iniciarla podría tener efectos secundarios si se llama muy tarde.
            // Idealmente, session_start() debe estar al principio de tu script bootstrap.
             session_start();
        }

        // Usamos la clave '_old_input' para los datos del formulario anterior
        $oldInput = $_SESSION['_old_input'] ?? null;

        // Si no hay datos antiguos en la sesión, devuelve el valor por defecto
        if (!is_array($oldInput)) {
            return $default;
        }

        // Si no se especifica una clave, devuelve todos los datos antiguos
        if (is_null($key)) {
            return $oldInput;
        }

        // Busca la clave específica en los datos antiguos
        // Usamos array_key_exists para manejar correctamente claves con valor null o false
        if (array_key_exists($key, $oldInput)) {
            // Devuelve el valor encontrado en la sesión
            // Blade escapará esto con {{ }}, así que no necesitamos e() aquí generalmente.
            return $oldInput[$key];
        }

        // Si la clave no se encontró, devuelve el valor por defecto
        return $default;
    }
}

if (!function_exists('clear_old_input')) {
    function clear_old_input() {
         if (session_status() !== PHP_SESSION_ACTIVE) {
             session_start();
         }
         unset($_SESSION['_old_input']);
    }
}


// Old __ function removed - now using TranslationService (see line 1667)

function flash($key, $value = null)
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!isset($_SESSION['_flash'])) {
        $_SESSION['_flash'] = [];
    }

    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }

    // Si no hay $value, consumir y devolver
    if (isset($_SESSION['_flash'][$key])) {
        $message = $_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);
        return $message;
    }

    return null;
}


if (!function_exists('consume_flash')) {
    function consume_flash($key) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (isset($_SESSION['_flash'][$key])) {
            $value = $_SESSION['_flash'][$key];
            unset($_SESSION['_flash'][$key]); // se elimina correctamente
            return $value;
        }

        return null;
    }
}

if (!function_exists('clear_all_flashes')) {
    function clear_all_flashes() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        unset($_SESSION['_flash']); // Elimina todos los mensajes flash actuales
    }
}


// Tenant helpers
if (!function_exists('tenant')) {
    function tenant() {
        return $GLOBALS['tenant'] ?? null;
    }
}

if (!function_exists('tenant_id')) {
    function tenant_id() {
        return tenant()['id'] ?? null;
    }
}

// Shortcut de sesión
if (!function_exists('session')) {
    function session(string $key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }
}

if (!function_exists('admin_path')) {
    function admin_path() {
        return $GLOBALS['tenant']['admin_path'] ?? 'admin';
    }
}

if (!function_exists('theme_path')) {
    function theme_path(): string {
        $config = require __DIR__ . '/../config/config.php';

		if (!($config['multi_tenant_enabled'] ?? false)) {
    return __DIR__ . '/../themes/' . ($config['default_theme'] ?? 'default');
}

		// Evita error si no hay tenant cargado (modo CMS clásico con tenant activado)
		$tenant = $GLOBALS['tenant'] ?? null;
		$tenantId = $tenant['id'] ?? null;
		$themeName = $tenant['theme'] ?? get_active_theme_slug(); // fallback si no hay tenant

		if ($tenantId === null) {
			return __DIR__ . '/../themes/' . $themeName;
		}

		return __DIR__ . "/../themes/tenant_{$tenantId}/{$themeName}";
    }
}

if (!function_exists('getAvailableThemes')) {
    function getAvailableThemes() {
        $themes = [];
        $basePath = realpath(__DIR__ . '/../themes/shared');

        if (!$basePath || !is_dir($basePath)) {
            return [];
        }

        foreach (scandir($basePath) as $dir) {
            if ($dir === '.' || $dir === '..') continue;

            $themePath = $basePath . '/' . $dir;
            if (!is_dir($themePath)) continue;

            $metaFile = $themePath . '/theme.json';
            $meta = [
                'slug' => $dir,
                'name' => ucfirst($dir),
                'description' => 'Tema disponible',
                'author' => 'Desconocido',
                'version' => null,
                'type' => 'unknown',
            ];

            if (file_exists($metaFile)) {
                $content = json_decode(file_get_contents($metaFile), true);
                $meta = array_merge($meta, $content ?? []);
            }

            $themes[] = $meta;
        }

        return $themes;
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string {
        $admin = $GLOBALS['tenant']['admin_path'] ?? 'admin';

        $prefix = '/' . trim($admin, '/');
        $suffix = trim($path, '/');

        return $suffix ? "$prefix/$suffix" : $prefix;
    }
}

if (!function_exists('config')) {
    function config($key, $default = null) {
        static $config;

        if (!$config) {
            $config = require __DIR__ . '/../config/config.php';
        }

        return $config[$key] ?? $default;
    }
}

// ----------------------------------------------------------------
// Nuevas funciones para el sistema de roles y permisos
// ----------------------------------------------------------------

/**
 * Verifica si el usuario actual tiene un rol específico
 */
if (!function_exists('has_role')) {
    function has_role(string $roleName): bool {
        // Necesitamos una sesión activa
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        // Verificar si hay una sesión de admin o usuario
        $isAdmin = isset($_SESSION['admin']);
        $isUser = isset($_SESSION['user']);
        
        if (!$isAdmin && !$isUser) {
            return false;
        }
        
        // Los administradores tienen todos los roles
        if ($isAdmin && $_SESSION['admin']['role'] === 'admin') {
            return true;
        }
        
        // Obtener datos del usuario
        $userId = $isAdmin ? $_SESSION['admin']['id'] : $_SESSION['user']['id'];
        $tenantId = $isAdmin ? $_SESSION['admin']['tenant_id'] : $_SESSION['user']['tenant_id'];
        
        // Usar PermissionManager para verificar el rol
        return \Screenart\Musedock\Security\PermissionManager::userHasRole($userId, $roleName, $tenantId);
    }
}

/**
 * Verifica si el usuario actual tiene un permiso específico
 */
if (!function_exists('has_permission')) {
    /**
     * Verifica si el usuario actual tiene un permiso específico.
     * El Superadmin siempre tiene todos los permisos.
     */
    function has_permission(string $permissionName): bool {
        // Necesitamos una sesión activa
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // --- Identificar usuario actual ---
        $currentUser = null;
        $userType = null;
        if (isset($_SESSION['super_admin'])) {
            $currentUser = $_SESSION['super_admin'];
            $userType = 'super_admin';
        } elseif (isset($_SESSION['admin'])) {
            $currentUser = $_SESSION['admin'];
            $userType = 'admin';
        } elseif (isset($_SESSION['user'])) {
            $currentUser = $_SESSION['user'];
            $userType = 'user';
        }

        // --- Si no hay usuario logueado ---
        if ($currentUser === null) {
            return false;
        }

        // --- Caso especial: Superadmin siempre tiene permiso ---
        if ($userType === 'super_admin') {
            return true;
        }

        // --- Caso especial (si lo mantienes): Admin normal tiene todos los permisos ---
        // Ojo: Considera si esto es deseable o si el admin debería tener permisos explícitos
        if ($userType === 'admin' && ($currentUser['role'] ?? null) === 'admin') {
             // Puedes decidir quitar esta línea si quieres que los admins normales
             // dependan de los roles/permisos asignados en la BD.
             // return true;
        }

        // --- Obtener datos para usuarios 'admin' o 'user' ---
        $userId = $currentUser['id'] ?? null;
        // El Superadmin NO tiene tenant_id, los otros sí (o debería ser null si es admin global)
        $tenantId = ($userType === 'admin' || $userType === 'user') ? ($currentUser['tenant_id'] ?? null) : null;

        // Validar que tenemos un ID de usuario para continuar
        if ($userId === null) {
             // Loguear esto podría ser útil
             // Logger::warning("Intento de verificar permiso sin ID de usuario válido.", ['userType' => $userType]);
             return false;
        }

        // --- Delegar al PermissionManager ---
        // Asegúrate que PermissionManager exista y el namespace sea correcto
        try {
             // Pasar el userType también podría ser útil para PermissionManager
             return \Screenart\Musedock\Security\PermissionManager::userHasPermission($userId, $permissionName, $tenantId /*, $userType */);
        } catch (\Throwable $e) {
             // Loguear error si PermissionManager falla
             Logger::exception($e, 'ERROR', ['helper' => 'has_permission', 'userId' => $userId, 'permission' => $permissionName]);
             return false; // Asumir no tiene permiso si hay error
        }
    }
}

/**
 * Obtiene todos los permisos del usuario actual
 */
if (!function_exists('user_permissions')) {
    function user_permissions(): array {
        // Necesitamos una sesión activa
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        // Verificar si hay una sesión de admin o usuario
        $isAdmin = isset($_SESSION['admin']);
        $isUser = isset($_SESSION['user']);
        
        if (!$isAdmin && !$isUser) {
            return [];
        }
        
        // Obtener datos del usuario
        $userId = $isAdmin ? $_SESSION['admin']['id'] : $_SESSION['user']['id'];
        $tenantId = $isAdmin ? $_SESSION['admin']['tenant_id'] : $_SESSION['user']['tenant_id'];
        
        // Si es admin, devolver todos los permisos
        if ($isAdmin && $_SESSION['admin']['role'] === 'admin') {
            // Obtener todos los permisos del sistema
            $db = \Screenart\Musedock\Database::connect();
            $stmt = $db->query("SELECT name FROM permissions");
            return $stmt->fetchAll(\PDO::FETCH_COLUMN);
        }
        
        // Obtener permisos del usuario
        return \Screenart\Musedock\Security\PermissionManager::getUserPermissions($userId, $tenantId);
    }
}

/**
 * Obtiene todos los roles del usuario actual
 */
if (!function_exists('user_roles')) {
    function user_roles(): array {
        // Necesitamos una sesión activa
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        // Verificar si hay una sesión de admin o usuario
        $isAdmin = isset($_SESSION['admin']);
        $isUser = isset($_SESSION['user']);
        
        if (!$isAdmin && !$isUser) {
            return [];
        }
        
        // Obtener datos del usuario
        $userId = $isAdmin ? $_SESSION['admin']['id'] : $_SESSION['user']['id'];
        $tenantId = $isAdmin ? $_SESSION['admin']['tenant_id'] : $_SESSION['user']['tenant_id'];
        
        // Si es admin, incluir el rol de admin
        if ($isAdmin && $_SESSION['admin']['role'] === 'admin') {
            return [['id' => 0, 'name' => 'admin', 'description' => 'Administrador del sistema']];
        }
        
        // Obtener roles del usuario
        return \Screenart\Musedock\Security\PermissionManager::getUserRoles($userId, $tenantId);
    }
}

/**
 * Verifica si un elemento debe ser mostrado según los permisos del usuario
 */
if (!function_exists('should_show')) {
    function should_show(string $permission): bool {
        return has_permission($permission);
    }
}


if (!function_exists('route')) {
    function route(string $name, array|string|int $params = []): string
    {
        // Si es escalar, lo convertimos a array con clave 'id' por defecto
        if (!is_array($params)) {
            $params = ['id' => $params];
        }

        $path = \Screenart\Musedock\Route::getPathByName($name, $params);

        return $path ?: '#ruta-no-encontrada';
    }
}



if (!function_exists('get_active_theme_slug')) {
    function get_active_theme_slug(): string
    {
        // Si multi-tenant está habilitado Y hay un tenant configurado
        if (config('multi_tenant_enabled')) {
            $tenantData = tenant();

            // Si hay tenant activo, usar su tema
            if (!empty($tenantData) && isset($tenantData['theme'])) {
                return $tenantData['theme'];
            }

            // Si no hay tenant (dominio master), usar la tabla themes
        }

        // Buscar tema activo en la tabla themes
        $row = \Screenart\Musedock\Database::table('themes')
            ->where('active', 1)
            ->first();

        // El método first() puede devolver un objeto o un array
        if (is_object($row)) {
            return $row->slug ?? config('default_theme', 'default');
        }

        return $row['slug'] ?? config('default_theme', 'default');
    }
}

// Utilizamos la versión mejorada de setting() que unifica todo
if (!function_exists('setting')) {
    function setting($key, $default = null) {
        static $settings = null;
        
        // Cargar todos los settings una sola vez
        if ($settings === null) {
            try {
                $pdo = \Screenart\Musedock\Database::connect();
                $stmt = $pdo->query("SELECT `key`, `value` FROM settings");
                $settings = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
            } catch (\Exception $e) {
                \Screenart\Musedock\Logger::log("Error cargando settings: " . $e->getMessage(), 'ERROR');
                $settings = [];
            }
        }
        
        return $settings[$key] ?? $default;
    }
}

if (!function_exists('hasComponentPermission')) {
    function hasComponentPermission($componentId, $userId = null, $userType = null, $tenantId = null)
    {
        $userId = $userId ?? $_SESSION['user_id'] ?? null;
        $userType = $userType ?? $_SESSION['user_type'] ?? null;
        $tenantId = $tenantId ?? $_SESSION['tenant_id'] ?? null;
        
        // Si es superadmin, siempre permitir
        if ($userType === 'superadmin') {
            return true;
        }
        
        // Buscar el permiso requerido para este componente
        $query = \Screenart\Musedock\Database::table('resource_permissions')
            ->where('resource_type', 'component')
            ->where('resource_identifier', $componentId);

        // QueryBuilder no soporta when() ni closures - usar if + whereRaw
        if ($tenantId) {
            $query->whereRaw("(tenant_id IS NULL OR tenant_id = ?)", [$tenantId]);
        }

        $permissionSlug = $query->value('permission_slug');
        
        // Si no hay permiso definido, permitir por defecto
        if (!$permissionSlug) {
            return true;
        }
        
        // Verificar si el usuario tiene el permiso requerido
        return \Screenart\Musedock\Security\PermissionManager::has($userId, $permissionSlug, $tenantId, $userType);
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

        // Elimina /public si está incluido por error
        $base = preg_replace('#/public$#', '', $base);

        return $scheme . '://' . $host . $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $assetPath): string
    {
        return url('assets/' . ltrim($assetPath, '/'));
    }
}

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('themeConfig')) {
    function themeConfig($key = null, $default = null)
    {
        $slug = setting('default_theme', 'default');
        $path = __DIR__ . "/../themes/{$slug}/theme.json";

        if (!file_exists($path)) {
            return $default;
        }

        static $config = null;

        if ($config === null) {
            $json = file_get_contents($path);
            $config = json_decode($json, true) ?? [];
        }

        if ($key === null) {
            return $config;
        }

        return $config[$key] ?? $default;
    }
}

if (!function_exists('pagination_links')) {
    function pagination_links(array $pagination, string $extraQuery = '', string $size = ''): string {
        if ($pagination['last_page'] <= 1) return '';

        // Determinar la clase de tamaño si se especifica (lg o sm)
        $sizeClass = '';
        if ($size === 'lg') {
            $sizeClass = ' pagination-lg';
        } elseif ($size === 'sm') {
            $sizeClass = ' pagination-sm';
        }

        $html = '<nav aria-label="Page navigation">';
        $html .= '<ul class="pagination' . $sizeClass . ' justify-content-center">';

        // Verificar la base_url, usar la URL actual si no está definida
        $baseUrl = $pagination['base_url'] ?? $_SERVER['REQUEST_URI'];
        
        // Limpiar la URL base de parámetros de página existentes
        $baseUrl = preg_replace('/[\?&]page=\d+/', '', $baseUrl);
        
        // Determinar si debemos usar ? o & para los parámetros
        $separator = (strpos($baseUrl, '?') !== false) ? '&' : '?';
        
        $current = $pagination['current'];
        $last = $pagination['last_page'];

        // Extra query string, si hay
        $extra = $extraQuery ? $separator . ltrim($extraQuery, '&?') : '';
        if ($extra && !empty($extraQuery)) {
            // Si ya tenemos un separador, asegurarnos de que el extra inicie con &
            $extra = (strpos($baseUrl, '?') !== false) ? '&' . ltrim($extraQuery, '&?') : '?' . ltrim($extraQuery, '&?');
            // Actualizar el separador para la paginación
            $separator = '&';
        }

        // Botón "<<"
        $html .= '<li class="page-item' . ($current == 1 ? ' disabled' : '') . '">';
        $html .= '<a class="page-link" href="' . $baseUrl . $extra . $separator . 'page=1" aria-label="First">';
        $html .= '<span aria-hidden="true">&laquo;</span></a></li>';

        // Botón "<"
        $html .= '<li class="page-item' . ($current == 1 ? ' disabled' : '') . '">';
        $html .= '<a class="page-link" href="' . $baseUrl . $extra . $separator . 'page=' . max(1, $current - 1) . '" aria-label="Previous">';
        $html .= '<span aria-hidden="true">&lsaquo;</span></a></li>';

        // Números (limitamos a mostrar máximo 5 páginas alrededor de la actual)
        $startPage = max(1, $current - 2);
        $endPage = min($last, $current + 2);
        
        // Ajustar si estamos cerca del inicio o final
        if ($startPage <= 3) {
            $endPage = min($last, 5);
            $startPage = 1;
        }
        
        if ($endPage >= $last - 2) {
            $startPage = max(1, $last - 4);
            $endPage = $last;
        }
        
        // Primera página si no es visible
        if ($startPage > 1) {
            $html .= "<li class='page-item'>";
            $html .= "<a class='page-link' href='{$baseUrl}{$extra}{$separator}page=1'>1</a></li>";
            
            if ($startPage > 2) {
                $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
            }
        }
        
        // Páginas numeradas
        for ($i = $startPage; $i <= $endPage; $i++) {
            if ($current == $i) {
                // Página actual - solo mostrar el número sin "(current)"
                $html .= "<li class='page-item active' aria-current='page'>";
                $html .= "<span class='page-link'>{$i}</span></li>";
            } else {
                // Otras páginas
                $html .= "<li class='page-item'>";
                $html .= "<a class='page-link' href='{$baseUrl}{$extra}{$separator}page={$i}'>{$i}</a></li>";
            }
        }
        
        // Última página si no es visible
        if ($endPage < $last) {
            if ($endPage < $last - 1) {
                $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
            }
            
            $html .= "<li class='page-item'>";
            $html .= "<a class='page-link' href='{$baseUrl}{$extra}{$separator}page={$last}'>{$last}</a></li>";
        }

        // Botón ">"
        $html .= '<li class="page-item' . ($current == $last ? ' disabled' : '') . '">';
        $html .= '<a class="page-link" href="' . $baseUrl . $extra . $separator . 'page=' . min($last, $current + 1) . '" aria-label="Next">';
        $html .= '<span aria-hidden="true">&rsaquo;</span></a></li>';

        // Botón ">>"
        $html .= '<li class="page-item' . ($current == $last ? ' disabled' : '') . '">';
        $html .= '<a class="page-link" href="' . $baseUrl . $extra . $separator . 'page=' . $last . '" aria-label="Last">';
        $html .= '<span aria-hidden="true">&raquo;</span></a></li>';

        $html .= '</ul></nav>';
        return $html;
    }
}
if (!function_exists('getActiveModuleSlugsForContext')) {
    function getActiveModuleSlugsForContext($multiTenant, $tenantId) {
        if ($multiTenant) {
            if (!$tenantId) {
                return \Screenart\Musedock\Database::query("
                    SELECT slug FROM modules 
                    WHERE active = 1 AND cms_enabled = 1
                ")->fetchAll();
            } else {
                return \Screenart\Musedock\Database::query("
                    SELECT m.slug
                    FROM modules m
                    JOIN tenant_modules tm ON tm.module_id = m.id
                    WHERE tm.tenant_id = :tenant AND tm.enabled = 1 AND m.active = 1
                ", ['tenant' => $tenantId])->fetchAll();
            }
        } else {
            return \Screenart\Musedock\Database::query("
                SELECT slug FROM modules 
                WHERE active = 1 AND cms_enabled = 1
            ")->fetchAll();
        }
    }
}

if (!function_exists('getAvailableLocales')) {
    function getAvailableLocales(): array {
        $tenantId = tenant_id();
        $locales = \Screenart\Musedock\Models\Language::getActiveLanguages($tenantId);

        $output = [];
        foreach ($locales as $lang) {
            $output[$lang->code] = $lang->name;
        }

        return $output;
    }
}

if (!function_exists('detectLanguage')) {
    function detectLanguage(): string
    {
        static $lang = null;
        if ($lang !== null) return $lang;

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Cargamos settings desde base de datos
        $settings = \Screenart\Musedock\Database::table('settings')->pluck('value', 'key');

        // Si existe 'force_lang', forzamos idioma
        if (!empty($settings['force_lang'])) {
            return $lang = $settings['force_lang'];
        }

        // Cargamos todos los idiomas activos
        $available = \Screenart\Musedock\Database::table('languages')
            ->where('active', 1)
            ->pluck('code');

        // Si hay ?lang= en la URL
        if (!empty($_GET['lang'])) {
            if (in_array($_GET['lang'], $available)) {
                $_SESSION['lang'] = $_GET['lang'];
                return $lang = $_GET['lang'];
            }
        }

        // Si ya tenemos idioma en sesión
        if (!empty($_SESSION['lang'])) {
            if (in_array($_SESSION['lang'], $available)) {
                return $lang = $_SESSION['lang'];
            }
        }

        // Detectar idioma del navegador
        $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 0, 2);
        if (in_array($browserLang, $available)) {
            return $lang = $browserLang;
        }

        // Idioma por defecto configurado en settings
        return $lang = $settings['language'] ?? 'en';
    }
}

if (!function_exists('format_date')) {
    function format_date($dateTime)
    {
        try {
            $format = setting('date_format', 'd/m/Y');
            return (new DateTime($dateTime))->format($format);
        } catch (Exception $e) {
            return $dateTime;
        }
    }
}

if (!function_exists('format_time')) {
    function format_time($dateTime)
    {
        try {
            $format = setting('time_format', 'H:i');
            return (new DateTime($dateTime))->format($format);
        } catch (Exception $e) {
            return $dateTime;
        }
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime($dateTime)
    {
        try {
            $dateFormat = setting('date_format', 'd/m/Y');
            $timeFormat = setting('time_format', 'H:i');
            return (new DateTime($dateTime))->format("{$dateFormat} {$timeFormat}");
        } catch (Exception $e) {
            return $dateTime;
        }
    }
}

if (!function_exists('date_format_setting')) {
    /**
     * Devuelve la fecha formateada usando la configuración del sistema o tenant.
     *
     * @param int|null $timestamp Si se pasa, se usa ese timestamp. Si no, se usa la hora actual.
     * @return string Fecha formateada según 'date_format' de settings.
     */
    function date_format_setting(?int $timestamp = null): string
    {
        $format = setting('date_format', 'd/m/Y'); // formato por defecto día/mes/año
        $timestamp = $timestamp ?? time();
        return date($format, $timestamp);
    }
}

if (!function_exists('time_format_setting')) {
    /**
     * Devuelve la hora formateada usando la configuración del sistema o tenant.
     *
     * @param int|null $timestamp Si se pasa, se usa ese timestamp. Si no, se usa la hora actual.
     * @return string Hora formateada según 'time_format' de settings.
     */
    function time_format_setting(?int $timestamp = null): string
    {
        $format = setting('time_format', 'H:i'); // formato por defecto 24h
        $timestamp = $timestamp ?? time();
        return date($format, $timestamp);
    }
}

/**
 * Renderiza un menú por su ubicación
 *
 * @param string $location Ubicación del menú (nav, footer, sidebar)
 * @param string|null $locale Idioma (es, en, fr, etc.)
 * @return string HTML del menú
 */
function render_menu($location, $locale = null) {
    require_once __DIR__ . '/Helpers/MenuHelper.php';
    return \Screenart\Musedock\Helpers\MenuHelper::renderMenu($location, $locale);
}

/**
 * Renderiza un menú con clases personalizadas
 *
 * @param string $location Ubicación del menú (nav, footer, sidebar)
 * @param string|null $locale Idioma (es, en, fr, etc.)
 * @param array $classes Clases CSS personalizadas
 * @return string HTML del menú
 */
function render_custom_menu($location, $locale = null, $classes = []) {
    require_once __DIR__ . '/Helpers/MenuHelper.php';
    return \Screenart\Musedock\Helpers\MenuHelper::renderCustomMenu($location, $locale, $classes);
}

/**
 * Renderiza metadatos SEO
 *
 * @param string|null $pageTitle Título de la página
 * @param string|null $pageDescription Descripción de la página
 * @return string HTML con metadatos SEO
 */
function seo_meta($pageTitle = null, $pageDescription = null) {
    require_once __DIR__ . '/Helpers/SiteHelper.php';
    return \Screenart\Musedock\Helpers\SiteHelper::renderSeoMeta($pageTitle, $pageDescription);
}

/**
 * Renderiza iconos de redes sociales
 *
 * @param string $class Clase CSS para la lista de iconos
 * @return string HTML con iconos de redes sociales
 */
function social_icons($class = 'header__social') {
    require_once __DIR__ . '/Helpers/SiteHelper.php';
    return \Screenart\Musedock\Helpers\SiteHelper::renderSocialIcons($class);
}

/**
 * Obtiene un valor de configuración del sistema
 *
 * @param string $key Clave del setting
 * @param mixed $default Valor por defecto si no existe
 * @return mixed Valor del setting
 */
function get_setting($key, $default = null) {
    require_once __DIR__ . '/Helpers/SiteHelper.php';
    return \Screenart\Musedock\Helpers\SiteHelper::getSetting($key, $default);
}

/**
 * Renderiza banner de cookies
 *
 * @return string HTML con el banner de cookies
 */
function cookie_banner() {
    require_once __DIR__ . '/Helpers/SiteHelper.php';
    return \Screenart\Musedock\Helpers\SiteHelper::renderCookieBanner();
}

if (!function_exists('public_path')) {
    function public_path(string $path = ''): string {
        $documentRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');

        // Si el document root no termina en /public, lo añadimos manualmente
        if (basename($documentRoot) !== 'public') {
            $documentRoot .= '/public';
        }

        return $path ? $documentRoot . '/' . ltrim($path, '/') : $documentRoot;
    }
}

if (!function_exists('get_page_templates')) {
    /**
     * Obtiene las plantillas de página disponibles en el tema activo.
     * Busca archivos .blade.php en el directorio de vistas del tema.
     * Prioriza tenant > tema base.
     *
     * @return array ['nombre_archivo.blade.php' => 'Nombre Descriptivo']
     */
    function get_page_templates(): array
    {
        $templates = [];
        $themeSlug = setting('default_theme', 'default'); // Tema por defecto global
        $viewDir = 'views'; // Subdirectorio estándar para vistas dentro del tema
        
        // Lógica Multi-Tenant (adaptada de tu View::renderTheme)
        $multiTenant = setting('multi_tenant_enabled', false);
        $tenant = tenant(); // Obtiene el tenant actual (si existe)
        $themeBasePath = null;
        
        if ($multiTenant && $tenant && isset($tenant['id']) && isset($tenant['theme'])) {
            $tenantId = $tenant['id'];
            $themeSlug = $tenant['theme']; // Usa el tema del tenant
            // Ruta específica del tenant
            $themeBasePath = realpath(__DIR__ . "/../themes/tenant_{$tenantId}/{$themeSlug}/{$viewDir}");
        }
        
        // Si no hay ruta de tenant o no existe, usar la ruta base del tema
        if (!$themeBasePath || !is_dir($themeBasePath)) {
            $themeBasePath = realpath(__DIR__ . "/../themes/{$themeSlug}/{$viewDir}");
        }
        
        // Si ni siquiera la ruta base existe, retornar al menos las plantillas básicas
        if (!$themeBasePath || !is_dir($themeBasePath)) {
            error_log("Directorio de vistas no encontrado para el tema: {$themeSlug}");
            return [
                'page.blade.php' => 'Plantilla Predeterminada',
                'home.blade.php' => 'Plantilla de Inicio'
            ];
        }
        
        // Añadir siempre la plantilla predeterminada
        $templates['page.blade.php'] = 'Plantilla Predeterminada';
        
        // Buscar tanto los archivos template-*.blade.php como los *.blade.php generales
        $templateFiles = glob("{$themeBasePath}/template-*.blade.php");
        $regularFiles = glob("{$themeBasePath}/*.blade.php");
        
        // Combinar ambos resultados
        $files = array_merge($templateFiles ?: [], $regularFiles ?: []);
        
        if ($files) {
            foreach ($files as $file) {
                $filename = basename($file);
                
                // Saltarse las plantillas ya añadidas (como page.blade.php)
                if (isset($templates[$filename])) {
                    continue;
                }
                
                // Intentar obtener un nombre "amigable" de un comentario (opcional)
                $fileContent = @file_get_contents($file, false, null, 0, 200); // Leer solo inicio
                $friendlyName = $filename; // Nombre por defecto es el filename
                
                if ($fileContent !== false && preg_match('/\{\{--\s*Template Name:\s*(.*?)\s*--\}\}/', $fileContent, $matches)) {
                    $friendlyName = trim($matches[1]);
                } else {
                    // Generar nombre desde el filename si no hay comentario
                    $friendlyName = ucwords(str_replace(
                        ['template-', '.blade.php', '-', '_'], 
                        ['', '', ' ', ' '], 
                        $filename
                    ));
                }
                
                $templates[$filename] = $friendlyName;
            }
        }
        
        // Si por alguna razón no se encontraron plantillas, asegurar que al menos devolvemos la predeterminada
        if (empty($templates)) {
            $templates['page.blade.php'] = 'Plantilla Predeterminada';
        }
        
        return $templates;
    }
}
if (!function_exists('process_shortcodes')) {
    /**
     * Procesa shortcodes conocidos dentro de un contenido de texto.
     * Esta versión delega a SliderHelper para que se encargue.
     *
     * @param string|null $content
     * @return string
     */
    function process_shortcodes(?string $content): string
    {
        // Procesar shortcodes de sliders originales
        $content = \Screenart\Musedock\Helpers\SliderHelper::processShortcodes($content);

        // Procesar shortcodes de React Sliders si la función existe
        if (function_exists('process_react_slider_shortcodes')) {
            $content = process_react_slider_shortcodes($content);
        }

        // Procesar shortcodes de Image Gallery si la función existe
        if (function_exists('process_gallery_shortcodes')) {
            $content = process_gallery_shortcodes($content);
        }

        return $content;
    }
}

if (!function_exists('array_get_nested')) {
    function array_get_nested($array, $keys, $default = null)
    {
        if (!is_array($keys)) {
            $keys = explode('.', $keys);
        }

        $current = $array;
        foreach ($keys as $key) {
            if (!is_array($current) || !isset($current[$key])) {
                return $default;
            }
            $current = $current[$key];
        }

        return $current;
    }
}
if (!function_exists('array_check_dependency')) {
    /**
     * Verifica si una opción depende de otra opción y si está activa/visible.
     *
     * @param array $options Opciones actuales guardadas
     * @param string|null $dependsOn Ruta de la opción de la que depende en formato "seccion.opcion"
     * @return bool
     */
    function array_check_dependency(array $options, ?string $dependsOn): bool
    {
        if (!$dependsOn) {
            return true; // No depende de nada => visible
        }

        // Obtener el valor de la opción de la que depende
        $value = array_get_nested($options, $dependsOn);

        // Si el valor es "verdadero", se muestra
        return !empty($value) && $value !== "0" && $value !== "false";
    }
}
if (!function_exists('request')) {
    function request()
    {
        return new class {
            protected $query;

            public function __construct()
            {
                $this->query = $_GET ?? [];
            }

            public function all(): array
            {
                return $this->query;
            }

            public function input(string $key, $default = null)
            {
                return $this->query[$key] ?? $default;
            }

            public function has(string $key): bool
            {
                return isset($this->query[$key]);
            }

            public function except(string|array $keys): array
            {
                $data = $this->query;

                foreach ((array)$keys as $key) {
                    unset($data[$key]);
                }

                return $data;
            }
        };
    }
}
/**
 * Comprueba si existe un menú para una ubicación específica
 * 
 * @param string $location Ubicación del menú
 * @return bool
 */
function menu_exists($location)
{
    $pdo = \Screenart\Musedock\Database::connect();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM site_menus WHERE location = ?");
    $stmt->execute([$location]);
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Obtiene el título de un menú por su ubicación
 * 
 * @param string $location Ubicación del menú
 * @param string|null $locale Idioma (si es null, usa el idioma actual)
 * @return string|null
 */
function get_menu_title($location, $locale = null)
{
    if (!$locale) {
        $locale = setting('language', 'es');
    }
    
    $pdo = \Screenart\Musedock\Database::connect();
    $stmt = $pdo->prepare("
        SELECT mt.title
        FROM site_menus m
        LEFT JOIN site_menu_translations mt ON m.id = mt.menu_id AND mt.locale = ?
        WHERE m.location = ?
        ORDER BY m.id DESC
        LIMIT 1
    ");
    $stmt->execute([$locale, $location]);
    return $stmt->fetchColumn();
}
// -------------------------------------------------
// Sistema de stacks para simular @push y @stack
// -------------------------------------------------

if (!function_exists('push_to_stack')) {
    /**
     * Añade contenido a una pila (stack).
     *
     * @param string $stack Nombre de la pila.
     * @param string $content Contenido a añadir.
     * @return void
     */
    function push_to_stack(string $stack, string $content): void
    {
        if (!isset($GLOBALS['_stacks'])) {
            $GLOBALS['_stacks'] = [];
        }

        if (!isset($GLOBALS['_stacks'][$stack])) {
            $GLOBALS['_stacks'][$stack] = [];
        }

        $GLOBALS['_stacks'][$stack][] = $content;
    }
}

if (!function_exists('render_stack')) {
    /**
     * Renderiza (imprime) todo el contenido de una pila (stack).
     *
     * @param string $stack Nombre de la pila.
     * @param string $glue Opcional. Si quieres unir con salto de línea u otro separador.
     * @return string
     */
    function render_stack(string $stack, string $glue = "\n"): string
    {
        if (!isset($GLOBALS['_stacks'][$stack])) {
            return '';
        }

        return implode($glue, $GLOBALS['_stacks'][$stack]);
    }
}
if (!function_exists('slugToPascalCase')) {
    /**
     * Convierte un slug estilo 'mi-modulo', 'modulo.controlador' o 'modulo_controlador' en 'MiModuloControlador'.
     * Útil para construir namespaces o nombres de clases.
     *
     * Ejemplos:
     * - super-admin → SuperAdmin
     * - blog → Blog
     * - user.profile → UserProfile
     *
     * @param string $slug El slug que quieres convertir.
     * @return string El texto convertido a PascalCase.
     */
    function slugToPascalCase(string $slug): string
    {
        // Reemplazar guiones, puntos y guiones bajos por espacios
        $slug = str_replace(['-', '.', '_'], ' ', $slug);

        // Capitalizar cada palabra y quitar los espacios
        return str_replace(' ', '', ucwords($slug));
    }
}
function media_manager_available() {
    return class_exists(\MediaManager\Controllers\MediaController::class);
}

/**
 * Obtiene una opción del tema activo
 *
 * @param string $key Clave de la opción
 * @param mixed $default Valor por defecto si no existe
 * @return mixed Valor de la opción o el default
 */
if (!function_exists('themeOption')) {
    function themeOption(string $key, $default = null)
    {
        try {
            $themeSlug = get_active_theme_slug();
            $pdo = \Screenart\Musedock\Database::connect();

            // theme_options stores all options as JSON in 'value' column
            $stmt = $pdo->prepare('
                SELECT value
                FROM theme_options
                WHERE theme_slug = :theme_slug
                LIMIT 1
            ');

            $stmt->execute([':theme_slug' => $themeSlug]);

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result && isset($result['value'])) {
                $allOptions = json_decode($result['value'], true);

                // Soportar notación de puntos (ej: "topbar.topbar_enabled")
                if (is_array($allOptions)) {
                    $keys = explode('.', $key);
                    $current = $allOptions;

                    foreach ($keys as $k) {
                        if (!is_array($current) || !isset($current[$k])) {
                            return $default;
                        }
                        $current = $current[$k];
                    }

                    // Convertir valores de toggle ("0", "1") a booleanos
                    if ($current === "0" || $current === 0) {
                        return false;
                    } elseif ($current === "1" || $current === 1) {
                        return true;
                    }

                    return $current;
                }
            }

            return $default;

        } catch (\Exception $e) {
            error_log("Error obteniendo themeOption '{$key}': " . $e->getMessage());
            return $default;
        }
    }
}

// ----------------------------------------------------------------
// Sistema de normalización de slugs de módulos (kebab-case)
// ----------------------------------------------------------------

if (!function_exists('normalize_module_slug')) {
    /**
     * Normaliza un slug de módulo a formato kebab-case estricto
     *
     * Conversiones:
     * - PascalCase/camelCase → kebab-case: "MediaManager" → "media-manager"
     * - snake_case → kebab-case: "ai_writer" → "ai-writer"
     * - Minúsculas forzadas: "Blog" → "blog"
     * - Caracteres no válidos eliminados
     * - Guiones múltiples colapsados
     *
     * @param string $input Slug original (puede estar en cualquier formato)
     * @return string Slug normalizado en kebab-case
     */
    function normalize_module_slug(string $input): string
    {
        // PascalCase/camelCase → kebab-case
        // Inserta un guion antes de cada letra mayúscula precedida por una minúscula
        $slug = preg_replace('/([a-z])([A-Z])/', '$1-$2', $input);

        // snake_case → kebab-case
        $slug = str_replace('_', '-', $slug);

        // Todo a minúsculas
        $slug = strtolower($slug);

        // Limpiar caracteres no válidos (solo permitir a-z, 0-9, -)
        $slug = preg_replace('/[^a-z0-9-]/', '', $slug);

        // Eliminar guiones múltiples
        $slug = preg_replace('/-+/', '-', $slug);

        // Eliminar guiones al inicio y final
        return trim($slug, '-');
    }
}

if (!function_exists('slug_to_namespace')) {
    /**
     * Convierte un slug kebab-case a PascalCase para namespaces PSR-4
     *
     * Ejemplos:
     * - "media-manager" → "MediaManager"
     * - "ai-writer" → "AiWriter"
     * - "blog" → "Blog"
     *
     * @param string $slug Slug en formato kebab-case
     * @return string Nombre en PascalCase para namespace
     */
    function slug_to_namespace(string $slug): string
    {
        // Reemplazar guiones por espacios, capitalizar cada palabra, eliminar espacios
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $slug)));
    }
}

if (!function_exists('validate_module_slug_format')) {
    /**
     * Valida si un slug cumple con el formato kebab-case estricto
     *
     * Reglas:
     * - Solo letras minúsculas (a-z)
     * - Números permitidos (0-9)
     * - Solo guiones como separadores (-)
     * - No puede empezar ni terminar con guion
     * - No puede tener guiones consecutivos
     *
     * @param string $slug Slug a validar
     * @return bool True si es válido, false si no
     */
    function validate_module_slug_format(string $slug): bool
    {
        // Debe coincidir con el patrón: letras minúsculas, números y guiones simples
        // No puede empezar ni terminar con guion
        return preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) === 1;
    }
}

// ============================================================================
// SISTEMA DE PERMISOS SIMPLIFICADO
// ============================================================================

if (!function_exists('userCan')) {
    /**
     * Verificar si el usuario actual tiene un permiso específico
     *
     * Uso en controladores:
     *   if (!userCan('pages.edit')) {
     *       flash('error', 'No tienes permiso');
     *       redirect('/dashboard');
     *   }
     *
     * Uso en vistas Blade:
     *   @if(userCan('pages.create'))
     *       <a href="/pages/create">Crear página</a>
     *   @endif
     *
     * @param string $permissionSlug Slug del permiso (ej: 'pages.edit', 'blog.delete')
     * @return bool True si tiene permiso, false si no
     */
    function userCan(string $permissionSlug): bool
    {
        return \Screenart\Musedock\Helpers\PermissionHelper::currentUserCan($permissionSlug);
    }
}

if (!function_exists('userHasAnyPermission')) {
    /**
     * Verificar si el usuario actual tiene al menos uno de los permisos listados
     *
     * @param array $permissionSlugs Array de slugs de permisos
     * @return bool
     */
    function userHasAnyPermission(array $permissionSlugs): bool
    {
        foreach ($permissionSlugs as $slug) {
            if (userCan($slug)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('userHasAllPermissions')) {
    /**
     * Verificar si el usuario actual tiene todos los permisos listados
     *
     * @param array $permissionSlugs Array de slugs de permisos
     * @return bool
     */
    function userHasAllPermissions(array $permissionSlugs): bool
    {
        foreach ($permissionSlugs as $slug) {
            if (!userCan($slug)) {
                return false;
            }
        }
        return true;
    }
}
// ============================================
// TRANSLATION HELPERS
// ============================================

if (!function_exists('__')) {
    /**
     * Traducir una clave
     * 
     * @param string $key Clave de traducción (ej: 'dashboard.welcome')
     * @param array $replace Valores para reemplazar (ej: ['name' => 'John'])
     * @param string|null $locale Idioma específico (null = actual)
     * @return string
     */
    function __(string $key, array $replace = [], ?string $locale = null): string
    {
        return \Screenart\Musedock\Services\TranslationService::get($key, $replace, $locale);
    }
}

if (!function_exists('trans')) {
    /**
     * Alias de __ para traducción
     */
    function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        return __($key, $replace, $locale);
    }
}

if (!function_exists('trans_choice')) {
    /**
     * Traducción con pluralización
     * 
     * @param string $key Clave de traducción
     * @param int $count Cantidad para pluralización
     * @param array $replace Valores para reemplazar
     * @param string|null $locale Idioma específico
     * @return string
     */
    function trans_choice(string $key, int $count, array $replace = [], ?string $locale = null): string
    {
        return \Screenart\Musedock\Services\TranslationService::choice($key, $count, $replace, $locale);
    }
}

if (!function_exists('app_locale')) {
    /**
     * Obtener idioma actual de la aplicación
     * 
     * @return string
     */
    function app_locale(): string
    {
        return \Screenart\Musedock\Services\TranslationService::getCurrentLocale();
    }
}

if (!function_exists('set_locale')) {
    /**
     * Establecer idioma de la aplicación
     * 
     * @param string $locale Código de idioma (es, en)
     * @return void
     */
    function set_locale(string $locale): void
    {
        \Screenart\Musedock\Services\TranslationService::setLocale($locale);
    }
}

if (!function_exists('available_locales')) {
    /**
     * Obtener idiomas disponibles
     *
     * @return array ['es' => 'Español', 'en' => 'English']
     */
    function available_locales(): array
    {
        return \Screenart\Musedock\Services\TranslationService::getAvailableLocales();
    }
}

if (!function_exists('is_module_active')) {
    /**
     * Verificar si un módulo está activo
     *
     * @param string $slug Slug del módulo (ej: 'media-manager')
     * @return bool
     */
    function is_module_active(string $slug): bool
    {
        static $cache = [];

        if (isset($cache[$slug])) {
            return $cache[$slug];
        }

        try {
            $result = Database::query(
                "SELECT active, cms_enabled FROM modules WHERE slug = :slug LIMIT 1",
                ['slug' => $slug]
            )->fetch();

            $cache[$slug] = $result && $result['active'] == 1 && $result['cms_enabled'] == 1;
        } catch (\Exception $e) {
            $cache[$slug] = false;
        }

        return $cache[$slug];
    }
}

/**
 * Get CMS version from composer.json
 */
if (!function_exists('cms_version')) {
    function cms_version($key = 'version') {
        static $versionData = null;

        if ($versionData === null) {
            $composerFile = __DIR__ . '/../composer.json';
            if (file_exists($composerFile)) {
                $composer = json_decode(file_get_contents($composerFile), true);
                $versionData = [
                    'version' => $composer['version'] ?? '1.0.0',
                    'name' => 'MuseDock CMS',
                    'package' => $composer['name'] ?? 'caimari/musedock',
                    'description' => $composer['description'] ?? '',
                    'created_year' => 2024,
                    'homepage' => $composer['homepage'] ?? 'https://musedock.org'
                ];
            } else {
                $versionData = [
                    'version' => '1.0.0',
                    'name' => 'MuseDock CMS',
                    'package' => 'caimari/musedock',
                    'created_year' => 2024,
                    'homepage' => 'https://musedock.org'
                ];
            }
        }

        return $versionData[$key] ?? null;
    }
}

/**
 * Get CMS copyright string
 */
if (!function_exists('cms_copyright')) {
    function cms_copyright() {
        $createdYear = cms_version('created_year');
        $currentYear = date('Y');
        $yearRange = $createdYear == $currentYear ? $currentYear : "{$createdYear}-{$currentYear}";

        return "© {$yearRange}";
    }
}
