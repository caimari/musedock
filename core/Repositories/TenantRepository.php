<?php

namespace Screenart\Musedock\Repositories;

use Screenart\Musedock\Models\Tenant;

class TenantRepository
{
    /**
     * Buscar un Tenant por su dominio y devolverlo como objeto Tenant.
     * Soporta variaciones www/sin-www automáticamente.
     * También busca en domain_aliases para dominios secundarios.
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

        // Si no encuentra en tenants.domain, buscar en domain_aliases
        if (!$data) {
            $data = $this->findByAlias($domain, $normalizedDomain);
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
     * Buscar un Tenant por alias de dominio.
     * Consulta domain_aliases y devuelve el Tenant vinculado.
     */
    private function findByAlias(string $domain, string $normalizedDomain): ?Tenant
    {
        try {
            $pdo = \Screenart\Musedock\Database::connect();
            $stmt = $pdo->prepare("
                SELECT tenant_id
                FROM domain_aliases
                WHERE status = 'active'
                  AND (domain = ? OR domain = ?)
                LIMIT 1
            ");
            $stmt->execute([$normalizedDomain, $domain]);
            $row = $stmt->fetch(\PDO::FETCH_OBJ);

            if (!$row) {
                return null;
            }

            return Tenant::where('id', $row->tenant_id)->first() ?: null;
        } catch (\Exception $e) {
            // Table may not exist yet
            return null;
        }
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
