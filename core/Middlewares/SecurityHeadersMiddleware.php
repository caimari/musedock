<?php

namespace Screenart\Musedock\Middlewares;

/**
 * 🔒 SECURITY: Middleware para agregar headers de seguridad
 * Previene: Clickjacking, MIME sniffing, XSS, información leak
 */
class SecurityHeadersMiddleware
{
    /**
     * Headers de seguridad a aplicar
     */
    private static array $headers = [
        // Previene clickjacking attacks
        'X-Frame-Options' => 'SAMEORIGIN',

        // Previene MIME type sniffing
        'X-Content-Type-Options' => 'nosniff',

        // Habilita XSS protection en navegadores
        'X-XSS-Protection' => '1; mode=block',

        // Fuerza HTTPS (habilitado por defecto para producción)
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',

        // Controla qué información se envía en el Referer header
        'Referrer-Policy' => 'strict-origin-when-cross-origin',

        // Control de permisos del navegador
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',

        // Previene que el navegador cachee información sensible
        'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
        'Pragma' => 'no-cache',
        'Expires' => '0'
    ];

    /**
     * Content Security Policy (CSP)
     * Configuración base - puede ser personalizada según necesidades
     */
    private static string $csp = "default-src 'self'; " .
                                   "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdn.tiny.cloud https://cdn.ckeditor.com https://pagead2.googlesyndication.com https://adservice.google.com https://www.googletagservices.com https://tpc.googlesyndication.com https://partner.googleadservices.com; " .
                                   "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; " .
                                   "img-src 'self' data: https: blob:; " .
                                   "font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net; " .
                                   "connect-src 'self' https://pagead2.googlesyndication.com https://adservice.google.com; " .
                                   "frame-src 'self' https://googleads.g.doubleclick.net https://tpc.googlesyndication.com https://www.google.com; " .
                                   "object-src 'none'; " .
                                   "base-uri 'self'; " .
                                   "form-action 'self'; " .
                                   "frame-ancestors 'self';";

    /**
     * Ejecutar middleware - aplicar headers de seguridad
     */
    public function handle(): void
    {
        // Solo aplicar si headers no han sido enviados
        if (headers_sent()) {
            return;
        }

        // Aplicar headers básicos
        foreach (self::$headers as $header => $value) {
            // Strict-Transport-Security solo si HTTPS está activo
            if ($header === 'Strict-Transport-Security') {
                if ($this->isHttps()) {
                    header("{$header}: {$value}");
                }
            } else {
                header("{$header}: {$value}");
            }
        }

        // Aplicar CSP (Content Security Policy)
        // Habilitado por defecto - ajustar según necesidades en config
        $cspEnabled = defined('CSP_ENABLED') ? CSP_ENABLED : true;
        if ($cspEnabled) {
            header("Content-Security-Policy: " . self::$csp);
        }
    }

    /**
     * Verificar si la conexión es HTTPS
     */
    private function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
            return true;
        }

        return false;
    }

    /**
     * Configurar CSP personalizado
     *
     * @param string $csp Directivas CSP personalizadas
     */
    public static function setCSP(string $csp): void
    {
        self::$csp = $csp;
    }

    /**
     * Agregar o modificar un header de seguridad
     *
     * @param string $header Nombre del header
     * @param string $value Valor del header
     */
    public static function setHeader(string $header, string $value): void
    {
        self::$headers[$header] = $value;
    }

    /**
     * Habilitar HSTS (HTTP Strict Transport Security)
     * Solo llamar si el sitio usa HTTPS
     *
     * @param int $maxAge Duración en segundos (default: 1 año)
     * @param bool $includeSubDomains Incluir subdominios
     * @param bool $preload Habilitar HSTS preload
     */
    public static function enableHSTS(int $maxAge = 31536000, bool $includeSubDomains = true, bool $preload = false): void
    {
        $value = "max-age={$maxAge}";

        if ($includeSubDomains) {
            $value .= '; includeSubDomains';
        }

        if ($preload) {
            $value .= '; preload';
        }

        self::$headers['Strict-Transport-Security'] = $value;
    }

    /**
     * Remover header de cacheo (para páginas que SÍ deben cachearse)
     */
    public static function allowCaching(): void
    {
        unset(self::$headers['Cache-Control']);
        unset(self::$headers['Pragma']);
        unset(self::$headers['Expires']);
    }
}
