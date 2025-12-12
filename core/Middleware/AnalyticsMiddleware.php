<?php

namespace Screenart\Musedock\Middleware;

use Screenart\Musedock\Services\WebAnalytics;

/**
 * Middleware de Analytics
 * Registra automáticamente todas las visitas a páginas públicas
 */
class AnalyticsMiddleware
{
    /**
     * Rutas que NO deben ser rastreadas
     */
    private const EXCLUDED_PATHS = [
        '/musedock/',           // Panel de administración
        '/admin/',              // Panel de tenant
        '/api/',                // Endpoints de API
        '/assets/',             // Archivos estáticos
        '/vendor/',             // Librerías vendor
        '/uploads/',            // Archivos subidos
        '/_health',             // Health check
        '/favicon.ico',         // Favicon
        '/robots.txt',          // Robots
        '/sitemap.xml',         // Sitemap
    ];

    /**
     * Extensiones de archivo que NO deben ser rastreadas
     */
    private const EXCLUDED_EXTENSIONS = [
        '.css', '.js', '.map', '.jpg', '.jpeg', '.png', '.gif', '.svg',
        '.webp', '.ico', '.woff', '.woff2', '.ttf', '.eot', '.otf',
        '.pdf', '.zip', '.tar', '.gz', '.xml', '.json'
    ];

    /**
     * Ejecutar middleware
     */
    public function handle()
    {
        // Verificar si el tracking está habilitado globalmente
        if (!$this->isTrackingEnabled()) {
            return;
        }

        // Obtener la ruta actual
        $currentPath = $_SERVER['REQUEST_URI'] ?? '/';

        // Verificar si la ruta debe ser excluida
        if ($this->shouldExcludePath($currentPath)) {
            return;
        }

        // Solo rastrear peticiones GET
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return;
        }

        // Registrar la visita
        $this->trackPageView();
    }

    /**
     * Verificar si el tracking está habilitado
     */
    private function isTrackingEnabled(): bool
    {
        // Verificar configuración global
        $enabled = getenv('ANALYTICS_ENABLED') !== 'false';

        // En desarrollo, se puede deshabilitar
        if (getenv('APP_ENV') === 'development' && getenv('ANALYTICS_IN_DEV') === 'false') {
            return false;
        }

        return $enabled;
    }

    /**
     * Verificar si una ruta debe ser excluida del tracking
     */
    private function shouldExcludePath(string $path): bool
    {
        // Limpiar query string
        $pathWithoutQuery = strtok($path, '?');

        // Verificar rutas excluidas
        foreach (self::EXCLUDED_PATHS as $excludedPath) {
            if (str_starts_with($pathWithoutQuery, $excludedPath)) {
                return true;
            }
        }

        // Verificar extensiones excluidas
        foreach (self::EXCLUDED_EXTENSIONS as $extension) {
            if (str_ends_with($pathWithoutQuery, $extension)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Registrar vista de página
     */
    private function trackPageView(): void
    {
        try {
            // Obtener tenant_id si existe
            $tenantId = null;

            // Si estamos en modo multi-tenant, detectar el tenant actual
            if (getenv('MULTI_TENANT_ENABLED') === 'true') {
                // Detectar por dominio
                $domain = $_SERVER['HTTP_HOST'] ?? '';

                if (!empty($domain)) {
                    $tenantId = $this->getTenantIdByDomain($domain);
                }
            }

            // Preparar datos de tracking
            $data = [
                'tenant_id' => $tenantId,
                'page_url' => $_SERVER['REQUEST_URI'] ?? '/',
                'page_title' => null, // Se capturará desde JavaScript en el frontend
            ];

            // Registrar la visita de forma asíncrona (no bloquear la respuesta)
            WebAnalytics::track($data);

        } catch (\Exception $e) {
            // No detener la ejecución si falla el tracking
            // Opcionalmente loguear el error
            error_log('Analytics tracking error: ' . $e->getMessage());
        }
    }

    /**
     * Obtener ID del tenant por dominio
     */
    private function getTenantIdByDomain(string $domain): ?int
    {
        try {
            $db = \Screenart\Musedock\Database::connect();

            $stmt = $db->prepare("SELECT id FROM tenants WHERE domain = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$domain]);
            $tenant = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $tenant ? (int)$tenant['id'] : null;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Método estático para invocar el middleware fácilmente
     */
    public static function run(): void
    {
        $middleware = new self();
        $middleware->handle();
    }
}
