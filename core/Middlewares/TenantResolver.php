<?php

namespace Screenart\Musedock\Middlewares;

use Screenart\Musedock\Services\TenantService;

class TenantResolver
{
    protected TenantService $tenantService;

    public function __construct()
    {
        $this->tenantService = new TenantService();
    }

    public function handle()
    {
        // Prioridad: .env → BD → fallback
        // Leer multi_tenant_enabled desde .env primero
        $multiTenant = \Screenart\Musedock\Env::get('MULTI_TENANT_ENABLED', null);
        if ($multiTenant === null) {
            // Si no está en .env, leer desde BD
            $multiTenant = setting('multi_tenant_enabled', false);
        }

        // Leer main_domain desde .env primero
        $mainDomain = \Screenart\Musedock\Env::get('MAIN_DOMAIN', null);
        if ($mainDomain === null) {
            // Si no está en .env, leer desde BD con fallback a config
            $mainDomain = setting('main_domain', config('main_domain'));
        }

        // Si multitenencia está desactivada, permitir acceso sin restricciones
        if (!$multiTenant) {
            return true;
        }

        if (!isset($_SERVER['HTTP_HOST'])) {
            http_response_code(400);
            echo "Host no detectado.";
            exit;
        }

        $host = $_SERVER['HTTP_HOST'];

        // Usamos TenantService
        return $this->tenantService->resolveTenant($host, $mainDomain);
    }
}
