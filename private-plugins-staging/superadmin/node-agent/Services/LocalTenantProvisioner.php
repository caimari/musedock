<?php

namespace NodeAgent\Services;

use Screenart\Musedock\Services\TenantCreationService;

class LocalTenantProvisioner
{
    private TenantCreationService $tenantCreationService;

    public function __construct()
    {
        $this->tenantCreationService = new TenantCreationService();
    }

    public function createTenant(array $tenantData, array $adminData): array
    {
        return $this->tenantCreationService->createTenant($tenantData, $adminData);
    }
}
