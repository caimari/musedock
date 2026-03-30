<?php

namespace Screenart\Musedock\Services;

use Screenart\Musedock\Models\Tenant;
use Screenart\Musedock\Repositories\TenantRepository;
use Screenart\Musedock\Services\TenantManager;

class TenantService
{
    protected TenantRepository $tenantRepository;

    /**
     * Cache interno de tenants.
     * @var array<string, Tenant>
     */
    protected static array $tenantCache = [];

    public function __construct()
    {
        $this->tenantRepository = new TenantRepository();
    }

    /**
     * Buscar un tenant por dominio usando cache.
     */
    public function findTenantByDomain(string $domain): ?Tenant
    {
        if (isset(self::$tenantCache[$domain])) {
            return self::$tenantCache[$domain];
        }

        $tenant = $this->tenantRepository->findByDomain($domain);

        if ($tenant) {
            self::$tenantCache[$domain] = $tenant;
        }

        return $tenant;
    }

    /**
     * Resolver un tenant o permitir modo clásico si es dominio principal.
     */
    public function resolveTenant(string $domain, string $mainDomain, bool $allowInactiveOnMainDomain = false): bool
    {
        $tenant = $this->findTenantByDomain($domain);

        // Si no hay tenant
        if (!$tenant) {
            if ($domain === $mainDomain) {
                // Dominio principal, permitir modo clásico
                return true;
            }

            // Dominio secundario no registrado: mostrar página de error estética
            http_response_code(403);
            $this->renderErrorPage('domain_not_found', $domain);
            exit;
        }

        // Si el tenant existe pero no está activo
        if (!$tenant->isActive() && !$allowInactiveOnMainDomain) {
            http_response_code(403);
            $this->renderErrorPage('tenant_inactive', $domain);
            exit;
        }

        // Establecer tenant global
        $this->setTenantGlobals($tenant);

        return true;
    }

    /**
     * Establece el tenant global en la aplicación.
     */
    private function setTenantGlobals(Tenant $tenant): void
    {
        $GLOBALS['tenant'] = [
            'id'         => $tenant->id,
            'domain'     => $tenant->domain,
            'admin_path' => $tenant->getAdminPath(),
            'name'       => $tenant->name,
            'theme'      => $tenant->getTheme(),
        ];

        TenantManager::setTenantId($tenant->id);
    }

    /**
     * Renderizar página de error estética
     */
    private function renderErrorPage(string $type, string $domain): void
    {
        $titles = [
            'domain_not_found' => 'Dominio no configurado',
            'tenant_inactive' => 'Sitio temporalmente inactivo'
        ];

        $messages = [
            'domain_not_found' => 'Este dominio aún no ha sido configurado en la plataforma.',
            'tenant_inactive' => 'Este sitio se encuentra temporalmente inactivo.'
        ];

        $title = $titles[$type] ?? 'Error';
        $message = $messages[$type] ?? 'Ha ocurrido un error.';

        echo <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} - MuseDock</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            padding: 48px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        .icon svg {
            width: 40px;
            height: 40px;
            fill: white;
        }
        h1 {
            color: #1a202c;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .message {
            color: #718096;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .domain {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 16px;
            font-family: monospace;
            font-size: 14px;
            color: #4a5568;
            margin-bottom: 32px;
            word-break: break-all;
        }
        .footer {
            color: #a0aec0;
            font-size: 13px;
        }
        .footer span {
            color: #667eea;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
            </svg>
        </div>
        <h1>{$title}</h1>
        <p class="message">{$message}</p>
        <div class="domain">{$domain}</div>
        <p class="footer">Powered by <span>MuseDock</span></p>
    </div>
</body>
</html>
HTML;
    }
}
