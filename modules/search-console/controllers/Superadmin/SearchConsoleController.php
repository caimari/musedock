<?php

namespace Modules\SearchConsole\Controllers\Superadmin;

use Modules\SearchConsole\Controllers\BaseController;
use Modules\SearchConsole\Services\CredentialStore;
use Screenart\Musedock\View;

/**
 * Search Console del dueño del CMS: credencial de la plataforma y vista agrupada
 * de los dominios propios. Los dominios de clientes no aparecen nunca aquí.
 */
class SearchConsoleController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->scopeTenantId = null;
        $this->basePath = 'musedock';
    }

    /** En el superadmin todo exige sesión de superadmin (la credencial es de la plataforma). */
    protected function checkPermission(string $permission): void
    {
        if (empty($_SESSION['super_admin']) || ($_SESSION['super_admin']['role'] ?? '') !== 'superadmin') {
            http_response_code(403);
            exit('Acceso denegado');
        }
    }

    protected function actorEmail(): string
    {
        return (string) ($_SESSION['super_admin']['email'] ?? 'superadmin');
    }

    public function index()
    {
        $this->checkPermission('search_console.view');
        $credential = $this->credentials->find(null);
        $properties = $credential ? $this->properties->visibleForSuperadmin() : [];
        $external = $credential ? $this->properties->externalForSuperadmin() : [];
        $totals = $this->totals(array_map(fn ($p) => (int) $p['id'], array_merge($properties, $external)));
        $clientCount = (int) $this->pdo->query('SELECT COUNT(*) FROM tenants WHERE is_client_domain = 1')->fetchColumn();

        return View::renderModule('search-console', 'superadmin.index', [
            'title' => 'Search Console',
            'credential' => CredentialStore::publicView($credential),
            'properties' => $properties,
            'external' => $external,
            'totals' => $totals,
            'missing' => $credential ? $this->properties->ownDomainsWithoutProperty((int) $credential['id']) : [],
            'clientCount' => $clientCount,
            'baseUrl' => $this->baseUrl(),
            'focusTenant' => isset($_GET['tenant']) ? (int) $_GET['tenant'] : null,
        ]);
    }
}
