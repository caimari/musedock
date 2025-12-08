<?php

namespace Screenart\Musedock\Repositories;

use Screenart\Musedock\Models\Tenant;

class TenantRepository
{
    /**
     * Buscar un Tenant por su dominio y devolverlo como objeto Tenant.
     */
    public function findByDomain(string $domain): ?Tenant
    {
        $data = Tenant::where('domain', $domain)->first();

        if (!$data) {
            return null;
        }

        return new Tenant((array) $data);
    }

    /**
     * Buscar un Tenant por su ID.
     */
    public function findById(int $id): ?Tenant
    {
        $data = Tenant::where('id', $id)->first();

        if (!$data) {
            return null;
        }

        return new Tenant((array) $data);
    }
}
