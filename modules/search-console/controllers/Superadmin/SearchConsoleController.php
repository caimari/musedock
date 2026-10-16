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
        $period = $this->periodFromRequest();
        $totals = $this->totals(array_map(fn ($p) => (int) $p['id'], array_merge($properties, $external)), $period['key']);
        $clientCount = (int) $this->pdo->query('SELECT COUNT(*) FROM tenants WHERE is_client_domain = 1')->fetchColumn();

        return View::renderModule('search-console', 'superadmin.index', [
            'title' => 'Search Console',
            'credential' => CredentialStore::publicView($credential),
            'properties' => $properties,
            'external' => $external,
            'totals' => $totals,
            'period' => $period,
            'expiry' => (new \Modules\SearchConsole\Services\DomainExpiryService($this->pdo))->listing(),
            'redirectPairs' => (new \Modules\SearchConsole\Services\RedirectService($this->pdo))->pairs(),
            'indexNow' => $this->indexNowView(),
            'priorities' => $credential ? $this->priorities($properties) : [],
            'archived' => $credential ? $this->properties->archivedForSuperadmin() : [],
            'missing' => $credential ? $this->properties->ownDomainsWithoutProperty((int) $credential['id']) : [],
            'pendingExternal' => $credential ? $this->properties->pendingExternal((int) $credential['id']) : [],
            'clientTenants' => $credential ? $this->properties->clientTenantsForAuthorization((int) $credential['id']) : [],
            'clientCount' => $clientCount,
            'baseUrl' => $this->baseUrl(),
            'focusTenant' => isset($_GET['tenant']) ? (int) $_GET['tenant'] : null,
        ]);
    }

    private function bulk(): \Modules\SearchConsole\Services\BulkVerifyService
    {
        return new \Modules\SearchConsole\Services\BulkVerifyService($this->pdo, $this->properties);
    }

    /** POST (AJAX): «Dar de alta todos» como cola en el servidor: start | step | stop | resume | status. */
    public function bulkAction($action)
    {
        $this->checkPermission('search_console.manage');
        header('Content-Type: application/json; charset=utf-8');
        $credential = $this->credentials->find(null);
        $bulk = $this->bulk();
        if (!$credential) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No hay cuenta de servicio conectada.']);
            exit;
        }
        $state = match ((string) $action) {
            'start' => $bulk->start((int) $credential['id'], in_array($_POST['scope'] ?? '', ['alias', 'main'], true) ? $_POST['scope'] : null),
            'step' => $bulk->step($this->credentials),
            'stop' => $bulk->stop(),
            'resume' => $bulk->resume(),
            default => $bulk->view($bulk->state()),
        };
        echo json_encode($state, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** POST (AJAX): dar de baja (conservando los datos) o reactivar una propiedad de la plataforma. */
    public function propertyAction($action)
    {
        $this->checkPermission('search_console.manage');
        header('Content-Type: application/json; charset=utf-8');
        $id = (int) ($_POST['property_id'] ?? 0);
        $visible = array_merge($this->properties->visibleForSuperadmin(), $this->properties->externalForSuperadmin(), $this->properties->archivedForSuperadmin());
        $row = null;
        foreach ($visible as $p) {
            if ((int) $p['id'] === $id) {
                $row = $p;
            }
        }
        if ($row === null || ($row['page_filter'] ?? '') !== '' || !in_array($action, ['archive', 'restore'], true)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Propiedad no permitida.']);
            exit;
        }
        if ($action === 'archive' && !$this->actorPasswordOk((string) ($_POST['confirm_password'] ?? ''))) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Contraseña incorrecta.']);
            exit;
        }
        $action === 'archive' ? $this->properties->archive($id) : $this->properties->restore($id);
        echo json_encode(['ok' => true]);
        exit;
    }

    /** POST (AJAX): consulta la caducidad de un dominio propio (solo dominios de tenants propios). */
    public function expiryCheck()
    {
        $this->checkPermission('search_console.manage');
        header('Content-Type: application/json; charset=utf-8');
        $svc = new \Modules\SearchConsole\Services\DomainExpiryService($this->pdo);
        $domain = \Modules\SearchConsole\Services\PropertyService::normalizeHost((string) ($_POST['domain'] ?? ''));
        if ($domain === '' || !isset($svc->ownDomains()[$domain])) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Dominio no permitido.']);
            exit;
        }
        $r = $svc->check($domain);
        echo json_encode(['ok' => true, 'status' => $r['status'], 'expires_at' => $r['expires_at']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** POST (AJAX): mover el SEO entre un dominio de tenant propio y su alias (apply) o quitar la redirección (remove). */
    public function redirectAction($action)
    {
        $this->checkPermission('search_console.manage');
        header('Content-Type: application/json; charset=utf-8');
        if (!$this->actorPasswordOk((string) ($_POST['confirm_password'] ?? ''))) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Contraseña incorrecta.']);
            exit;
        }
        @set_time_limit(180);
        $svc = new \Modules\SearchConsole\Services\RedirectService($this->pdo);
        if ((string) $action === 'bulk') {
            $domains = array_slice(array_filter((array) ($_POST['domains'] ?? []), 'is_string'), 0, 50);
            $res = $svc->bulk((string) ($_POST['mode'] ?? ''), $domains);
            $sm = new \Modules\SearchConsole\Services\SitemapService();
            foreach ($res['results'] as &$one) {
                if ($one['ok'] && !empty($one['to'])) {
                    $r = $sm->submitForDomain($this->pdo, $this->credentials, $one['to']);
                    $one['message'] .= ' ' . ($r['ok'] ? '✓ Sitemap de ' . $one['to'] . ' enviado a Google.' : $r['message']);
                }
            }
            unset($one);
            echo json_encode(['ok' => $res['failed'] === 0 && $res['ok'] > 0] + $res + ['message' => $res['ok'] . ' hechas' . ($res['failed'] ? ', ' . $res['failed'] . ' con problemas' : '')], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $from = (string) ($_POST['from'] ?? '');
        $res = match ((string) $action) {
            'apply' => $this->applyAndSubmitSitemap($svc, $from, (string) ($_POST['to'] ?? '')),
            'remove' => $svc->remove($from),
            default => ['ok' => false, 'message' => 'Acción no válida.'],
        };
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Mueve el SEO y, si fue bien, avisa a Google del sitemap del destino para que lo rastree antes. */
    private function applyAndSubmitSitemap(\Modules\SearchConsole\Services\RedirectService $svc, string $from, string $to): array
    {
        $res = $svc->apply($from, $to);
        if ($res['ok']) {
            $r = (new \Modules\SearchConsole\Services\SitemapService())->submitForDomain($this->pdo, $this->credentials, $to);
            $res['message'] .= ' ' . ($r['ok'] ? "Sitemap de {$to} enviado a Google." : $r['message']);
        }

        return $res;
    }

    private function indexNow(): \Modules\SearchConsole\Services\IndexNowService
    {
        return new \Modules\SearchConsole\Services\IndexNowService($this->pdo);
    }

    private function indexNowView(): array
    {
        $svc = $this->indexNow();
        $key = $svc->ensureKey();

        return ['enabled' => (bool) $svc->config()['enabled'], 'key' => $key, 'hosts' => $svc->listing()];
    }

    /** POST (AJAX): lee el sitemap de un dominio propio y avisa de lo nuevo (o toma nota la primera vez). */
    public function indexNowScan()
    {
        $this->checkPermission('search_console.manage');
        header('Content-Type: application/json; charset=utf-8');
        $svc = $this->indexNow();
        $host = \Modules\SearchConsole\Services\PropertyService::normalizeHost((string) ($_POST['host'] ?? ''));
        if (!in_array($host, $svc->ownHosts(), true)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Dominio no permitido.']);
            exit;
        }
        @set_time_limit(60);
        echo json_encode(['ok' => true] + $svc->scanHost($host), JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** POST (AJAX): activa o desactiva los avisos de IndexNow. */
    public function indexNowToggle()
    {
        $this->checkPermission('search_console.manage');
        header('Content-Type: application/json; charset=utf-8');
        $svc = $this->indexNow();
        $svc->setEnabled(!empty($_POST['enabled']) && $_POST['enabled'] !== '0');
        echo json_encode(['ok' => true, 'enabled' => (bool) $svc->config()['enabled']]);
        exit;
    }

    /** POST (AJAX): «Descargar datos ahora» como cola: start | step | stop | resume | status. */
    public function syncQueue($action)
    {
        $this->checkPermission('search_console.manage');
        header('Content-Type: application/json; charset=utf-8');
        if (!$this->credentials->find(null)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No hay cuenta de servicio conectada.']);
            exit;
        }
        $q = new \Modules\SearchConsole\Services\SyncQueueService($this->pdo);
        $state = match ((string) $action) {
            'start' => $q->start(array_merge($this->properties->visibleForSuperadmin(), $this->properties->externalForSuperadmin())),
            'step' => $q->step($this->credentials, new \Modules\SearchConsole\Services\SyncService($this->pdo)),
            'stop' => $q->stop(),
            'resume' => $q->resume(),
            default => $q->view($q->state()),
        };
        echo json_encode($state, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Lo que más merece tu tiempo: pocas oportunidades y caídas de impacto alto en todos los dominios. */
    private function priorities(array $properties): array
    {
        try {
            $svc = new \Modules\SearchConsole\Services\PriorityService(
                new \Modules\SearchConsole\Services\OpportunityService($this->pdo),
                new \Modules\SearchConsole\Services\DropAlertService($this->pdo)
            );

            return $svc->top($properties, 5);
        } catch (\Throwable $e) {
            return [];
        }
    }
}
