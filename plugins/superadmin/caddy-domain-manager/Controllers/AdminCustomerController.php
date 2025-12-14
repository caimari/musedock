<?php
/**
 * AdminCustomerController - Listado de customers (superadmin)
 */

namespace CaddyDomainManager\Controllers;

use CaddyDomainManager\Models\Customer;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\View;

class AdminCustomerController
{
    use RequiresPermission;

    private function checkMultitenancyEnabled(): void
    {
        $envValue = \Screenart\Musedock\Env::get('MULTI_TENANT_ENABLED', null);
        if ($envValue !== null) {
            $enabled = filter_var($envValue, FILTER_VALIDATE_BOOLEAN);
        } else {
            $enabled = setting('multi_tenant_enabled', config('multi_tenant_enabled', false));
        }

        if (!$enabled) {
            flash('error', 'La funcionalidad de multitenancy no está habilitada.');
            header('Location: /musedock/dashboard');
            exit;
        }
    }

    public function index()
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        $search = (string) ($_GET['search'] ?? '');
        $page = (int) ($_GET['page'] ?? 1);
        $perPage = (int) ($_GET['per_page'] ?? 50);

        $result = Customer::listWithTenantStats($search, $page, $perPage);

        return View::renderSuperadmin('plugins.caddy-domain-manager.customers', [
            'title' => 'Clientes',
            'customers' => $result['items'],
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'pages' => $result['pages'],
            ],
            'filters' => [
                'search' => $search,
            ],
        ]);
    }
}

