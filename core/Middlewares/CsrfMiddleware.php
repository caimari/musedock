<?php

namespace Screenart\Musedock\Middlewares;

use Screenart\Musedock\Logger;

/**
 * Middleware de protección CSRF
 */
class CsrfMiddleware
{
    /**
     * Verifica el token CSRF en peticiones POST, PUT, PATCH, DELETE
     *
     * @return bool
     */
    public function handle()
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Solo verificar en métodos que modifican datos
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return true;
        }

        // Iniciar sesión si no está activa
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Obtener token de diferentes fuentes
        $token = $this->getToken();

        // Verificar que existe un token en la sesión
        if (!isset($_SESSION['_csrf_token'])) {
            Logger::log("CSRF: Token de sesión no encontrado", 'WARNING', [
                'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'session_id' => session_id(),
                'session_keys' => array_keys($_SESSION),
                'cookie_phpsessid' => $_COOKIE['PHPSESSID'] ?? 'NOT SET'
            ]);
            $this->fail();
            return false;
        }

        // Verificar el token usando hash_equals (resistente a timing attacks)
        if (!$token || !hash_equals($_SESSION['_csrf_token'], $token)) {
            Logger::log("CSRF: Token inválido o faltante", 'WARNING', [
                'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'method' => $method
            ]);
            $this->fail();
            return false;
        }

        return true;
    }

    /**
     * Obtiene el token CSRF de múltiples fuentes
     *
     * @return string|null
     */
    private function getToken()
    {
        // 1. Buscar en POST
        if (isset($_POST['_token'])) {
            return $_POST['_token'];
        }

        if (isset($_POST['_csrf'])) {
            return $_POST['_csrf'];
        }

        if (isset($_POST['csrf_token'])) {
            return $_POST['csrf_token'];
        }

        if (isset($_POST['_csrf_token'])) {
            return $_POST['_csrf_token'];
        }

        // 2. Buscar en headers (para APIs)
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return $_SERVER['HTTP_X_CSRF_TOKEN'];
        }

        if (isset($_SERVER['HTTP_X_XSRF_TOKEN'])) {
            return $_SERVER['HTTP_X_XSRF_TOKEN'];
        }

        // 3. Buscar en body JSON (para peticiones AJAX con Content-Type: application/json)
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $input = file_get_contents('php://input');
            if ($input) {
                $json = json_decode($input, true);
                if (is_array($json)) {
                    // Guardar el JSON parseado para que los controladores puedan reutilizarlo
                    // ya que php://input solo puede leerse una vez
                    $GLOBALS['_JSON_INPUT'] = $json;

                    if (isset($json['_csrf'])) {
                        return $json['_csrf'];
                    }
                    if (isset($json['_token'])) {
                        return $json['_token'];
                    }
                    if (isset($json['csrf_token'])) {
                        return $json['csrf_token'];
                    }
                }
            }
        }

        // ELIMINADO: CSRF tokens en GET por razones de seguridad
        // Los tokens CSRF nunca deben enviarse en la URL ya que pueden:
        // - Aparecer en logs del servidor
        // - Filtrarse en headers Referer
        // - Quedar en el historial del navegador
        // Use POST, headers HTTP, o campos ocultos en su lugar

        return null;
    }

    /**
     * Maneja el fallo de validación CSRF
     */
    private function fail()
    {
        http_response_code(419);

        // Generar nuevo token CSRF para que el cliente pueda reintentar
        $newToken = null;
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Generar nuevo token CSRF
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
            $newToken = $_SESSION['_csrf_token'];
        }

        // Detectar si es una petición AJAX
        // Verificar múltiples headers posibles
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
                  (!empty($_SERVER['HTTP_ACCEPT']) &&
                  strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
                  (!empty($_SERVER['CONTENT_TYPE']) &&
                  strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);

        if ($isAjax) {
            header('Content-Type: application/json');

            // Mensaje traducido
            $message = 'Tu sesión ha expirado. Por favor, recarga la página e inicia sesión nuevamente.';

            $response = [
                'success' => false,
                'message' => $message,
                'error' => 'csrf_token_mismatch',
                'error_detail' => 'Token CSRF inválido o expirado',
                'redirect' => $this->getLoginUrl()
            ];
            // Incluir nuevo token para que el frontend pueda actualizar y reintentar
            if ($newToken) {
                $response['new_csrf_token'] = $newToken;
            }
            echo json_encode($response);
        } else {
            // Determinar la URL de login apropiada
            $loginUrl = $this->getLoginUrl();

            // Guardar mensaje flash para mostrar en el login
            // Usamos ambos sistemas de flash para compatibilidad
            if (session_status() === PHP_SESSION_ACTIVE) {
                // Sistema nuevo (función flash)
                if (function_exists('flash')) {
                    flash('error', 'Tu sesión ha expirado. Por favor, inicia sesión nuevamente.');
                }
                // Sistema legacy (sesión directa) para superadmin
                $_SESSION['error'] = 'Tu sesión ha expirado. Por favor, inicia sesión nuevamente.';
            }

            // Redirigir al login
            header("Location: $loginUrl");
        }

        exit;
    }

    /**
     * Determina la URL de login apropiada según el contexto
     *
     * @return string
     */
    private function getLoginUrl()
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Si es superadmin (comienza con /{ADMIN_PATH_MUSEDOCK})
        $musedockBase = '/' . trim((string) \Screenart\Musedock\Env::get('ADMIN_PATH_MUSEDOCK', 'musedock'), '/');
        if (strpos($uri, $musedockBase) === 0) {
            return "{$musedockBase}/login";
        }

        // Si es customer (comienza con /customer)
        if (strpos($uri, '/customer') === 0) {
            return '/customer/login';
        }

        // Para tenants, usar el admin_path si está disponible
        if (function_exists('admin_path')) {
            return '/' . admin_path() . '/login';
        }

        // Por defecto, intentar detectar del URI
        // Si hay un path antes de /dashboard, /profile, etc.
        if (preg_match('#^/([^/]+)/(dashboard|profile|settings)#', $uri, $matches)) {
            return '/' . $matches[1] . '/login';
        }

        // Fallback: asumir panel de admin genérico
        return '/admin/login';
    }
}
