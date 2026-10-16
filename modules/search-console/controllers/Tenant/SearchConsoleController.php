<?php

namespace Modules\SearchConsole\Controllers\Tenant;

use Modules\SearchConsole\Controllers\BaseController;
use Modules\SearchConsole\Services\CredentialStore;
use Screenart\Musedock\View;

/**
 * Search Console de un tenant.
 *  - Tenant propio: ve los datos que lee la plataforma y, si quiere, puede añadir su cuenta.
 *  - Tenant cliente: solo ve lo leído con su propia cuenta de servicio, que solo él gestiona.
 */
class SearchConsoleController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $tenantId = function_exists('tenant_id') ? tenant_id() : null;
        $this->scopeTenantId = $tenantId === null ? null : (int) $tenantId;
        $this->basePath = function_exists('admin_path') ? admin_path() : 'admin';
    }

    private function requireTenant(): int
    {
        // Este controlador nunca gestiona la credencial de la plataforma
        if ($this->scopeTenantId === null || empty($_SESSION['admin']) || (int) ($_SESSION['admin']['tenant_id'] ?? 0) !== $this->scopeTenantId) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        return $this->scopeTenantId;
    }

    private function deny(): void
    {
        flash('error', 'No tienes permiso para Search Console.');
        redirect('/' . trim($this->basePath, '/') . '/dashboard');
    }

    protected function checkPermission(string $permission): void
    {
        $this->requireTenant();
        // Quien puede gestionar también puede ver
        if (!userCan($permission) && !($permission === 'search_console.view' && userCan('search_console.manage'))) {
            $this->deny();
        }
    }

    protected function actorEmail(): string
    {
        return (string) ($_SESSION['admin']['email'] ?? 'admin');
    }

    public function index()
    {
        $this->checkPermission('search_console.view');
        $tenantId = $this->requireTenant();

        $properties = $this->properties->visibleForTenant($tenantId);
        $credential = $this->credentials->find($tenantId);

        return View::renderModule('search-console', 'tenant.index', [
            'title' => 'Search Console',
            'isClient' => $this->properties->isClientTenant($tenantId),
            'canManage' => userCan('search_console.manage'),
            'credential' => CredentialStore::publicView($credential),
            'properties' => $properties,
            'period' => $period = $this->periodFromRequest(),
            'totals' => $this->totals(array_map(fn ($p) => (int) $p['id'], $properties), $period['key']),
            'baseUrl' => $this->baseUrl(),
            'tenantDomain' => \Modules\SearchConsole\Services\PropertyService::normalizeHost((string) (tenant()['domain'] ?? '')),
        ]);
    }
}
