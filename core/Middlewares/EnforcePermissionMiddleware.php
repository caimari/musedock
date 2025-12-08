<?php

namespace Screenart\Musedock\Middlewares;

use Screenart\Musedock\Security\SessionSecurity;

/**
 * Middleware de Seguridad: Deny by Default
 *
 * Este middleware garantiza que TODAS las rutas del panel de administración
 * tengan un checkPermission() definido. Si una función de controlador no
 * tiene verificación de permisos, el acceso es DENEGADO por defecto.
 *
 * Esto previene que los desarrolladores olviden agregar checkPermission()
 * y dejen rutas expuestas accidentalmente.
 *
 * Rutas excluidas (whitelist):
 * - Login/Logout
 * - Password reset
 * - Dashboard (público para usuarios autenticados)
 */
class EnforcePermissionMiddleware
{
    /**
     * Rutas que NO requieren checkPermission() en el controlador
     * (pero sí requieren autenticación via middleware 'superadmin')
     */
    private static array $whitelist = [
        // Autenticación
        'AuthController@login',
        'AuthController@authenticate',
        'AuthController@logout',

        // Recuperación de contraseña
        'PasswordResetController@showForgotForm',
        'PasswordResetController@sendResetLinkEmail',
        'PasswordResetController@showResetForm',
        'PasswordResetController@resetPassword',

        // Dashboard (accesible para cualquier superadmin autenticado)
        'DashboardController@index',
        'MusedockController@index',

        // Perfil personal (el usuario edita su propio perfil)
        'ProfileController@index',
        'ProfileController@updateName',
        'ProfileController@updateEmail',
        'ProfileController@updatePassword',
        'ProfileController@uploadAvatar',
        'ProfileController@deleteAvatar',
        'ProfileController@serveAvatar',

        // 2FA (el usuario configura su propia 2FA)
        'ProfileController@enable2fa',
        'ProfileController@verify2fa',
        'ProfileController@disable2fa',
        'ProfileController@showRecoveryCodes',
        'ProfileController@regenerateRecoveryCodes',
        'TwoFactorController@show',
        'TwoFactorController@verify',
        'TwoFactorController@index',
        'TwoFactorController@setup',
        'TwoFactorController@enable',
        'TwoFactorController@disable',
        'TwoFactorController@regenerateCodes',
        'TwoFactorController@verifyCode',

        // Cambio de idioma (preferencia personal)
        'LanguageSwitcherController@switch',

        // Notificaciones personales
        'NotificationsController@getUnread',
        'NotificationsController@getUnreadCount',
        'NotificationsController@markAsRead',
        'NotificationsController@markAllAsRead',
    ];

    /**
     * Cache de métodos ya verificados
     */
    private static array $verifiedMethods = [];

    /**
     * Ejecutar el middleware
     *
     * @param string $controllerClass Clase del controlador (ej: 'superadmin.MenuController')
     * @param string $method Método a ejecutar (ej: 'index')
     * @return bool True si puede continuar, false si debe denegar
     */
    public static function handle(string $controllerClass, string $method): bool
    {
        // Construir identificador del método
        $shortName = self::getShortControllerName($controllerClass);
        $methodId = "{$shortName}@{$method}";

        // Si está en whitelist, permitir
        if (in_array($methodId, self::$whitelist)) {
            return true;
        }

        // Cache: si ya verificamos este método, usar resultado cacheado
        if (isset(self::$verifiedMethods[$methodId])) {
            return self::$verifiedMethods[$methodId];
        }

        // Verificar si el método tiene checkPermission()
        $hasPermission = self::methodHasPermissionCheck($controllerClass, $method);

        // Guardar en cache
        self::$verifiedMethods[$methodId] = $hasPermission;

        if (!$hasPermission) {
            self::denyAccess($methodId);
            return false;
        }

        return true;
    }

    /**
     * Obtener nombre corto del controlador
     */
    private static function getShortControllerName(string $controllerClass): string
    {
        // 'superadmin.MenuController' → 'MenuController'
        // 'Screenart\Musedock\Controllers\Superadmin\MenuController' → 'MenuController'
        $parts = explode('\\', $controllerClass);
        $name = end($parts);

        // Si tiene formato 'superadmin.Controller'
        if (strpos($name, '.') !== false) {
            $parts = explode('.', $name);
            $name = end($parts);
        }

        return $name;
    }

    /**
     * Verificar si un método tiene checkPermission() en su código
     */
    private static function methodHasPermissionCheck(string $controllerClass, string $method): bool
    {
        try {
            // Resolver clase completa
            $fullClass = self::resolveFullClassName($controllerClass);

            if (!class_exists($fullClass)) {
                // Si no podemos resolver la clase, asumimos que está protegida
                // (mejor denegar acceso que permitir sin verificación)
                return false;
            }

            $reflection = new \ReflectionMethod($fullClass, $method);
            $filename = $reflection->getFileName();
            $startLine = $reflection->getStartLine();
            $endLine = $reflection->getEndLine();

            if (!$filename || !file_exists($filename)) {
                return false;
            }

            // Leer contenido del método
            $lines = file($filename);
            $methodContent = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

            // Buscar llamada a checkPermission, checkAnyPermission, checkAllPermissions o requireSuperAdmin
            $patterns = [
                '/\$this->checkPermission\s*\(/i',
                '/\$this->checkAnyPermission\s*\(/i',
                '/\$this->checkAllPermissions\s*\(/i',
                '/\$this->requireSuperAdmin\s*\(/i',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $methodContent)) {
                    return true;
                }
            }

            return false;

        } catch (\ReflectionException $e) {
            // Si hay error de reflexión, denegar acceso por seguridad
            return false;
        }
    }

    /**
     * Resolver nombre completo de clase
     */
    private static function resolveFullClassName(string $controllerClass): string
    {
        // Si ya es nombre completo
        if (strpos($controllerClass, '\\') !== false) {
            return $controllerClass;
        }

        // Si tiene formato 'superadmin.Controller'
        if (strpos($controllerClass, 'superadmin.') === 0) {
            $name = str_replace('superadmin.', '', $controllerClass);
            return "Screenart\\Musedock\\Controllers\\Superadmin\\{$name}";
        }

        // Asumir namespace de Superadmin
        return "Screenart\\Musedock\\Controllers\\Superadmin\\{$controllerClass}";
    }

    /**
     * Denegar acceso y mostrar error
     */
    private static function denyAccess(string $methodId): void
    {
        SessionSecurity::startSession();

        // Log del intento
        $auth = SessionSecurity::getAuthenticatedUser();
        $userId = $auth['id'] ?? 'unknown';
        $userEmail = $auth['email'] ?? 'unknown';

        error_log("[SECURITY] Acceso denegado a {$methodId} - Usuario: {$userEmail} (ID: {$userId}) - Motivo: Sin checkPermission() definido");

        // Mostrar error
        flash('error', "Acceso denegado: La ruta '{$methodId}' no tiene permisos configurados. Contacte al administrador.");

        // Redirigir al dashboard
        header('Location: /musedock/dashboard');
        exit;
    }

    /**
     * Agregar ruta a la whitelist dinámicamente
     */
    public static function addToWhitelist(string $methodId): void
    {
        if (!in_array($methodId, self::$whitelist)) {
            self::$whitelist[] = $methodId;
        }
    }

    /**
     * Verificar si una ruta está en whitelist
     */
    public static function isWhitelisted(string $methodId): bool
    {
        return in_array($methodId, self::$whitelist);
    }

    /**
     * Obtener lista de métodos sin protección (para auditoría)
     */
    public static function getUnprotectedMethods(): array
    {
        $unprotected = [];
        $controllersPath = __DIR__ . '/../Controllers/Superadmin';

        if (!is_dir($controllersPath)) {
            return [];
        }

        $iterator = new \DirectoryIterator($controllersPath);

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $className = $file->getBasename('.php');
                $fullClass = "Screenart\\Musedock\\Controllers\\Superadmin\\{$className}";

                if (!class_exists($fullClass)) {
                    continue;
                }

                $reflection = new \ReflectionClass($fullClass);
                $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

                foreach ($methods as $method) {
                    // Ignorar métodos heredados y magic methods
                    if ($method->class !== $fullClass || strpos($method->name, '__') === 0) {
                        continue;
                    }

                    $methodId = "{$className}@{$method->name}";

                    // Si está en whitelist, ignorar
                    if (in_array($methodId, self::$whitelist)) {
                        continue;
                    }

                    // Verificar si tiene checkPermission
                    if (!self::methodHasPermissionCheck($fullClass, $method->name)) {
                        $unprotected[] = $methodId;
                    }
                }
            }
        }

        return $unprotected;
    }
}
