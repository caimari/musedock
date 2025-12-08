<?php

// ✅ Evitar múltiples ejecuciones si el módulo ya ha sido cargado
if (defined('BOOTSTRAP_HELLO_WORLD')) return;
define('BOOTSTRAP_HELLO_WORLD', true);

use Screenart\Musedock\Services\SlugService;
use Screenart\Musedock\Services\TenantManager;

/**
 * Datos del módulo
 */
$moduleSlug  = 'hello';
$moduleName  = 'hello-world';
$referenceId = 1;

/**
 * Obtener tenant actual
 */
$tenantId = TenantManager::currentTenantId();

/**
 * Registrar slug para tenant actual
 */
if ($tenantId !== null) {
    SlugService::registerOnce(
        module: $moduleName,
        referenceId: $referenceId,
        slug: $moduleSlug,
        tenantId: $tenantId,
        prefix: null
    );
}

/**
 * Registrar slug para modo CMS clásico si multitenencia está desactivada
 */
if (!setting('multi_tenant_enabled', false)) {
    SlugService::registerOnce(
        module: $moduleName,
        referenceId: $referenceId,
        slug: $moduleSlug,
        tenantId: null,
        prefix: null
    );
}
