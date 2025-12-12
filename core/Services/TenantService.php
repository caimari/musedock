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

            // Dominio secundario no registrado: denegar acceso
            http_response_code(403);
            echo "Dominio no autorizado: $domain";
            exit;
        }

        // Si el tenant existe pero no está activo
        if (!$tenant->isActive() && !$allowInactiveOnMainDomain) {
            http_response_code(403);
            echo "Acceso denegado. Tenant inactivo para el dominio: $domain";
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
}
