<?php

namespace Screenart\Musedock\Repositories;

use Screenart\Musedock\Models\Tenant;

class TenantRepository
{
    /**
     * Buscar un Tenant por su dominio y devolverlo como objeto Tenant.
     * Soporta variaciones www/sin-www automáticamente.
     */
    public function findByDomain(string $domain): ?Tenant
    {
        // Normalizar dominio: eliminar www. si existe
        $normalizedDomain = preg_replace('/^www\./i', '', $domain);

        // Intentar búsqueda exacta primero
        $data = Tenant::where('domain', $domain)->first();

        // Si no encuentra, intentar con/sin www
        if (!$data) {
            // Si el dominio original tenía www, buscar sin www
            if (stripos($domain, 'www.') === 0) {
                $data = Tenant::where('domain', $normalizedDomain)->first();
            } else {
                // Si no tenía www, buscar con www
                $data = Tenant::where('domain', 'www.' . $domain)->first();
            }
        }

        if (!$data) {
            return null;
        }

        // Si ya es un Tenant, devolverlo directamente
        if ($data instanceof Tenant) {
            return $data;
        }

        // Si es stdClass u otro tipo, convertir a Tenant
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

        // Si ya es un Tenant, devolverlo directamente
        if ($data instanceof Tenant) {
            return $data;
        }

        // Si es stdClass u otro tipo, convertir a Tenant
        return new Tenant((array) $data);
    }
}
