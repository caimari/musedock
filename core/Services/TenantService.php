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
            // Debug: mostrar info del tenant para diagnosticar
            error_log("TenantService DEBUG - Tenant encontrado para $domain:");
            error_log("TenantService DEBUG - status value: '" . ($tenant->status ?? 'NULL') . "'");
            error_log("TenantService DEBUG - status type: " . gettype($tenant->status));
            error_log("TenantService DEBUG - isActive(): " . ($tenant->isActive() ? 'true' : 'false'));
            error_log("TenantService DEBUG - id: " . ($tenant->id ?? 'NULL'));
            error_log("TenantService DEBUG - name: " . ($tenant->name ?? 'NULL'));

            http_response_code(403);
            // Debug temporal - mostrar info en la página de error
            echo "<h1>Acceso denegado. Tenant inactivo para el dominio: $domain</h1>";
            echo "<h3>Debug info:</h3>";
            echo "<pre>";
            echo "status value: '" . ($tenant->status ?? 'NULL') . "'\n";
            echo "status type: " . gettype($tenant->status) . "\n";
            echo "isActive(): " . ($tenant->isActive() ? 'true' : 'false') . "\n";
            echo "id: " . ($tenant->id ?? 'NULL') . "\n";
            echo "name: " . ($tenant->name ?? 'NULL') . "\n";
            echo "</pre>";
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
