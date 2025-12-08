<?php

/**
 * 🔒 SECURITY: Helpers para protección CSRF
 * Previene: Cross-Site Request Forgery attacks
 */

if (!function_exists('generate_csrf_token')) {
    /**
     * Generar token CSRF y guardarlo en sesión
     *
     * @return string Token CSRF
     */
    function generate_csrf_token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }
}

if (!function_exists('verify_csrf_token')) {
    /**
     * Verificar que el token CSRF proporcionado es válido
     *
     * @param string $token Token a verificar
     * @return bool True si es válido, false si no
     */
    function verify_csrf_token(string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION['_csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['_csrf_token'], $token);
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generar campo oculto de formulario con token CSRF
     *
     * @return string HTML del campo oculto
     */
    function csrf_field(): string
    {
        $token = generate_csrf_token();
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Obtener el token CSRF actual
     *
     * @return string Token CSRF
     */
    function csrf_token(): string
    {
        return generate_csrf_token();
    }
}
