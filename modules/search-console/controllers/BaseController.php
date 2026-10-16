<?php

namespace Modules\SearchConsole\Controllers;

use Modules\SearchConsole\Services\CredentialStore;
use Modules\SearchConsole\Services\DomainVerifier;
use Modules\SearchConsole\Services\GoogleApiException;
use Modules\SearchConsole\Services\GoogleClient;
use Modules\SearchConsole\Services\OpportunityService;
use Modules\SearchConsole\Services\PropertyService;
use Modules\SearchConsole\Services\ReportService;
use Modules\SearchConsole\Services\SitemapService;
use Modules\SearchConsole\Services\SyncService;
use Modules\SearchConsole\Services\UrlInspectionService;
use Screenart\Musedock\Database;
use Screenart\Musedock\View;

/**
 * Lógica común de las pantallas de Search Console. Cada subclase fija el ámbito:
 * superadmin (credencial de la plataforma, tenantId null) o tenant (su credencial).
 */
abstract class BaseController
{
    protected \PDO $pdo;
    protected CredentialStore $credentials;
    protected PropertyService $properties;

    /** Tenant de la credencial gestionada: null = plataforma */
    protected ?int $scopeTenantId = null;
    protected string $basePath = 'musedock';

    public function __construct()
    {
        $this->pdo = Database::connect();
        $this->credentials = new CredentialStore($this->pdo);
        $this->properties = new PropertyService($this->pdo);
    }

    /**
     * Comprobación de acceso de cada acción. Se llama literalmente como
     * $this->checkPermission() porque EnforcePermissionMiddleware busca esa llamada
     * en el código de cada método de controlador.
     */
    abstract protected function checkPermission(string $permission): void;

    abstract protected function actorEmail(): string;

    protected function baseUrl(): string
    {
        return '/' . trim($this->basePath, '/') . '/search-console';
    }

    /** POST: guardar la clave JSON (fichero subido o texto pegado). */
    public function saveCredential()
    {
        $this->checkPermission('search_console.manage');
        $json = '';
        if (!empty($_FILES['key_file']['tmp_name']) && is_uploaded_file($_FILES['key_file']['tmp_name'])) {
            $json = (string) file_get_contents($_FILES['key_file']['tmp_name'], false, null, 0, 20001);
        } elseif (!empty($_POST['key_json'])) {
            $json = (string) $_POST['key_json'];
        }
        if (trim($json) === '') {
            flash('error', 'Sube el fichero JSON de la cuenta de servicio.');
            return redirect($this->baseUrl());
        }

        try {
            $row = $this->credentials->save($this->scopeTenantId, $json, $this->actorEmail());
            $summary = $this->properties->refreshFromGoogle($row, $this->credentials->client($row));
            flash('success', 'Cuenta de servicio conectada: ' . $row['client_email'] . '. ' . $this->summaryText($summary));
        } catch (GoogleApiException $e) {
            flash('error', 'Google rechazó la clave: ' . $e->getMessage() . ($e->status === 403 ? ' — activa «Google Search Console API» en el proyecto.' : ''));
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }

        return redirect($this->baseUrl());
    }

    /** POST: volver a leer de Google qué propiedades ve la cuenta de servicio. */
    public function refresh()
    {
        $this->checkPermission('search_console.manage');
        $row = $this->credentials->find($this->scopeTenantId);
        if (!$row) {
            flash('error', 'Primero conecta la cuenta de servicio.');
            return redirect($this->baseUrl());
        }
        try {
            $summary = $this->properties->refreshFromGoogle($row, $this->credentials->client($row));
            $this->credentials->markTest((int) $row['id'], true);
            flash('success', 'Propiedades actualizadas. ' . $this->summaryText($summary));
        } catch (\Throwable $e) {
            $this->credentials->markTest((int) $row['id'], false, $e->getMessage());
            flash('error', 'No se pudo leer Search Console: ' . $e->getMessage());
        }

        return redirect($this->baseUrl());
    }

    /** POST (AJAX): alta automática de un dominio vía Cloudflare. */
    public function verifyDomain()
    {
        $this->checkPermission('search_console.manage');
        header('Content-Type: application/json; charset=utf-8');
        $row = $this->credentials->find($this->scopeTenantId);
        $domain = PropertyService::normalizeHost((string) ($_POST['domain'] ?? ''));
        if (!$row || $domain === '' || !$this->domainAllowed($domain)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Dominio no permitido en este ámbito.']);
            exit;
        }

        $client = $this->credentials->client($row);
        $result = (new DomainVerifier($client))->verify($domain);
        $this->recordVerification($row, $client, $domain, $result);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** POST (AJAX): alta de un dominio que no es tenant del CMS (WordPress, otro servidor…). Solo la plataforma. */
    public function addExternalDomain()
    {
        $this->checkPermission('search_console.manage');
        header('Content-Type: application/json; charset=utf-8');
        $row = $this->credentials->find($this->scopeTenantId);
        $domain = PropertyService::normalizeHost((string) ($_POST['domain'] ?? ''));
        if (!$row || $this->scopeTenantId !== null || $domain === '' || !$this->externalAllowed($domain)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Dominio no permitido: es de un cliente o no es válido.']);
            exit;
        }

        $client = $this->credentials->client($row);
        $result = (new DomainVerifier($client))->verify($domain);
        $this->recordVerification($row, $client, $domain, $result);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Tras un intento de alta: si Google ya lo reconoce se enlaza la propiedad; si aún no, se guarda como
     * «pendiente» para que el estado sobreviva a recargar la página y lo reintente el cron del servidor.
     */
    protected function recordVerification(array $credential, GoogleClient $client, string $domain, array $result): void
    {
        if ($result['status'] === 'verified') {
            try {
                $this->properties->refreshFromGoogle($credential, $client);
            } catch (\Throwable $e) {
                // la propiedad ya existe en Google; se enlazará en la próxima actualización
            }
            if ($credential['owner_type'] === 'platform') {
                // Recién dado de alta: se avisa a Google de su sitemap para que rastree antes (best-effort)
                try {
                    (new SitemapService())->submitForDomain($this->pdo, $this->credentials, $domain);
                } catch (\Throwable $e) {
                    // no es crítico
                }
            }

            return;
        }
        if (in_array($result['status'], ['pending', 'manual'], true)) {
            $owner = $this->properties->hostMap()[$domain] ?? null;
            $this->properties->markPending((int) $credential['id'], $domain, $owner['tenant_id'] ?? $this->scopeTenantId, $owner === null, (string) $result['message']);
        }
    }

    /** GET: informe de SEO de una propiedad (solo las que este ámbito puede ver). */
    public function show($id)
    {
        $this->checkPermission('search_console.view');
        $property = $this->visibleProperty((int) $id);
        if (!$property) {
            http_response_code(404);
            exit('Propiedad no encontrada');
        }
        $days = (int) ($_GET['days'] ?? 28);
        $report = (new ReportService($this->pdo))->report($property, $days);

        return View::renderModule('search-console', ($this->scopeTenantId === null ? 'superadmin' : 'tenant') . '.domain', [
            'title' => 'SEO · ' . $property['domain'],
            'property' => $property,
            'report' => $report,
            'opportunities' => (new OpportunityService($this->pdo))->detect($property, in_array($days, [7, 28, 90, 180, 365], true) ? $days : 365),
            'periods' => ReportService::PERIODS,
            'baseUrl' => $this->baseUrl(),
        ]);
    }

    /** POST (AJAX): descarga ahora los datos de una propiedad (o de todas las visibles). */
    public function syncNow()
    {
        $this->checkPermission('search_console.manage');
        header('Content-Type: application/json; charset=utf-8');
        @set_time_limit(600);
        $only = (int) ($_POST['property_id'] ?? 0);
        $targets = array_filter($this->visibleProperties(), fn ($p) => $p['status'] === 'active' && ($only === 0 || (int) $p['id'] === $only));
        if (!$targets) {
            echo json_encode(['ok' => false, 'message' => 'No hay propiedades activas que descargar.']);
            exit;
        }
        // Cada propiedad se descarga con la cuenta que la lee: la de la plataforma para los dominios propios o
        // autorizados (también cuando lo pide un tenant), la del propio tenant para lo que él conectó.
        $sync = new SyncService($this->pdo);
        $clients = [];
        $rows = 0;
        $errors = [];
        foreach ($targets as $p) {
            $cid = (int) $p['credential_id'];
            if (!array_key_exists($cid, $clients)) {
                $cred = $this->credentials->findById($cid);
                $clients[$cid] = $cred ? $this->credentials->client($cred) : null;
            }
            if ($clients[$cid] === null) {
                $errors[] = $p['domain'] . ': no hay cuenta de servicio conectada.';
                continue;
            }
            $r = $sync->syncProperty($p, $clients[$cid]);
            $rows += $r['rows'];
            if ($r['status'] !== 'ok') {
                $errors[] = $p['domain'] . ': ' . $r['error'];
            }
        }
        echo json_encode(['ok' => !$errors, 'rows' => $rows, 'message' => $errors ? implode(' · ', $errors) : "Descargadas {$rows} filas de " . count($targets) . ' propiedad(es).'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Credencial que lee una propiedad (la de la plataforma o la del tenant) y su cliente de Google. */
    private function clientFor(array $property): ?GoogleClient
    {
        $cred = $this->credentials->findById((int) $property['credential_id']);

        return $cred ? $this->credentials->client($cred) : null;
    }

    /** Host real de una propiedad: el subdominio de una propiedad virtual, o el dominio. */
    private function hostOf(array $property): string
    {
        return PropertyService::normalizeHost((string) $property['domain']);
    }

    /** POST (AJAX): envía el sitemap del dominio a Google y devuelve su estado. */
    public function submitSitemap()
    {
        $this->checkPermission('search_console.manage');
        header('Content-Type: application/json; charset=utf-8');
        $property = $this->visibleProperty((int) ($_POST['property_id'] ?? 0));
        $client = $property && $property['status'] === 'active' ? $this->clientFor($property) : null;
        if (!$property || !$client) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Propiedad no disponible.']);
            exit;
        }
        $svc = new SitemapService();
        $r = $svc->submit($client, $property['site_url'], $this->hostOf($property));
        $status = $r['ok'] ? $svc->status($client, $property['site_url'], $this->hostOf($property)) : null;
        echo json_encode($r + ['status' => $status], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** POST (AJAX): inspecciona una URL de la propiedad (¿indexada? ¿último rastreo? ¿canonical?). */
    public function inspectUrl()
    {
        $this->checkPermission('search_console.view');
        header('Content-Type: application/json; charset=utf-8');
        $property = $this->visibleProperty((int) ($_POST['property_id'] ?? 0));
        $url = trim((string) ($_POST['url'] ?? ''));
        if (!$property || $property['status'] !== 'active' || !UrlInspectionService::belongsTo($url, (string) $property['domain'], (string) ($property['page_filter'] ?? ''))) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'La URL no pertenece a este dominio.']);
            exit;
        }
        $client = $this->clientFor($property);
        if (!$client) {
            echo json_encode(['ok' => false, 'message' => 'No hay cuenta de servicio conectada.']);
            exit;
        }
        echo json_encode((new UrlInspectionService())->inspect($client, $property['site_url'], $url), JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Propiedades que este ámbito puede ver (misma regla de privacidad que las pantallas). */
    protected function visibleProperties(): array
    {
        return $this->scopeTenantId === null
            ? array_merge($this->properties->visibleForSuperadmin(), $this->properties->externalForSuperadmin())
            : $this->properties->visibleForTenant($this->scopeTenantId);
    }

    protected function visibleProperty(int $id): ?array
    {
        foreach ($this->visibleProperties() as $p) {
            if ((int) $p['id'] === $id) {
                return $p;
            }
        }

        return null;
    }

    /**
     * POST (AJAX): el master autoriza a propósito el dominio (o subdominio) de un cliente. Solo plataforma y con
     * su contraseña. Desde ese momento el cliente ve su SEO sin hacer nada y esos datos no se purgan.
     */
    public function authorizeClient()
    {
        $this->checkPermission('search_console.manage');
        header('Content-Type: application/json; charset=utf-8');
        $row = $this->credentials->find($this->scopeTenantId);
        $domain = PropertyService::normalizeHost((string) ($_POST['domain'] ?? ''));
        $owner = $this->properties->hostMap()[$domain] ?? null;
        if ($this->scopeTenantId !== null || !$row || !$owner || empty($owner['client']) || empty($owner['tenant_id'])) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Solo se pueden autorizar dominios de tenants marcados como cliente.']);
            exit;
        }
        if (!$this->actorPasswordOk((string) ($_POST['confirm_password'] ?? ''))) {
            echo json_encode(['status' => 'error', 'message' => 'Contraseña incorrecta (o demasiados intentos): no se ha autorizado nada.']);
            exit;
        }

        $tenantId = (int) $owner['tenant_id'];
        $parent = $this->properties->parentProperty((int) $row['id'], $domain);
        if ($parent) {
            // Subdominio de un dominio ya dado de alta: no hay nada que verificar, se descarga con filtro de página
            $this->properties->ensureVirtual((int) $row['id'], $parent, $domain, $tenantId, true);
            echo json_encode(['status' => 'verified', 'message' => "{$domain} autorizado: el cliente ya ve su SEO (solo el de su subdominio). Los datos se descargan en el siguiente ciclo o con «Descargar datos ahora»."], JSON_UNESCAPED_UNICODE);
            exit;
        }
        // Dominio propio del cliente: se verifica con TXT (si su DNS está en tus cuentas DNS) y se marca como autorizado
        $this->properties->markPending((int) $row['id'], $domain, $tenantId, false, 'Autorizado por ti; esperando la verificación de Google.', true);
        $client = $this->credentials->client($row);
        $result = (new DomainVerifier($client))->verify($domain);
        $this->recordVerification($row, $client, $domain, $result);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** POST (AJAX): el master retira la autorización; la propiedad y sus datos se borran de la plataforma. */
    public function revokeClient()
    {
        $this->checkPermission('search_console.manage');
        header('Content-Type: application/json; charset=utf-8');
        $id = (int) ($_POST['property_id'] ?? 0);
        $stmt = $this->pdo->prepare('SELECT p.id FROM gsc_properties p JOIN tenants t ON t.id = p.tenant_id WHERE p.id = ? AND p.authorized = 1 AND t.is_client_domain = 1');
        $stmt->execute([$id]);
        if ($this->scopeTenantId !== null || !$stmt->fetchColumn()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No es una autorización de un cliente.']);
            exit;
        }
        if (!$this->actorPasswordOk((string) ($_POST['confirm_password'] ?? ''))) {
            echo json_encode(['status' => 'error', 'message' => 'Contraseña incorrecta (o demasiados intentos): no se ha retirado nada.']);
            exit;
        }
        $this->properties->revokeAuthorization($id);
        echo json_encode(['status' => 'verified', 'message' => 'Autorización retirada: los datos de este cliente se han borrado de la plataforma.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** POST: desconectar la cuenta de servicio y borrar todos sus datos. Exige la contraseña del administrador. */
    public function deleteCredential()
    {
        $this->checkPermission('search_console.manage');
        if (!$this->actorPasswordOk((string) ($_POST['confirm_password'] ?? ''))) {
            flash('error', 'Contraseña incorrecta (o demasiados intentos): no se ha desconectado nada.');

            return redirect($this->baseUrl());
        }
        $row = $this->credentials->find($this->scopeTenantId);
        if ($row) {
            $this->credentials->delete((int) $row['id']);
            flash('success', 'Cuenta de servicio desconectada y datos borrados.');
        }

        return redirect($this->baseUrl());
    }

    /** Comprueba la contraseña del administrador con sesión iniciada (confirmación común del core). */
    protected function actorPasswordOk(string $password): bool
    {
        return \Screenart\Musedock\Security\PasswordConfirmation::verify($password, $this->pdo);
    }

    /** Un dominio externo vale mientras no sea (ni cuelgue de) un dominio de cliente. */
    protected function externalAllowed(string $domain): bool
    {
        if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain)) {
            return false;
        }
        $map = $this->properties->hostMap();
        $labels = explode('.', $domain);
        for ($i = 0; $i < count($labels) - 1; $i++) {
            $candidate = implode('.', array_slice($labels, $i));
            if (!empty($map[$candidate]['client'])) {
                return false;
            }
        }

        return true;
    }

    /** ¿Puede este ámbito dar de alta ese dominio? */
    protected function domainAllowed(string $domain): bool
    {
        $owner = $this->properties->hostMap()[$domain] ?? null;
        if ($owner === null || ($owner['alias_status'] ?? 'active') !== 'active') {
            return false;
        }
        if ($this->scopeTenantId === null) {
            return !$owner['client'];
        }

        return $owner['tenant_id'] === $this->scopeTenantId;
    }

    /** Totales de los últimos $days días y del periodo anterior, por propiedad. */
    /** Periodo elegido para la tabla principal (?days=): días, texto y opciones del selector. */
    protected function periodFromRequest(): array
    {
        $key = (int) ($_GET['days'] ?? 28);
        $key = in_array($key, ReportService::PERIODS, true) ? $key : 28;
        $options = [];
        foreach (ReportService::PERIODS as $d) {
            $options[$d] = ReportService::periodLabel($d);
        }

        return ['key' => $key, 'label' => ReportService::periodLabel($key), 'options' => $options];
    }

    protected function totals(array $propertyIds, int $days = 28): array
    {
        if (empty($propertyIds)) {
            return [];
        }
        $in = implode(',', array_map('intval', $propertyIds));
        if ($days === 0) { // todo el histórico guardado
            $min = $this->pdo->query("SELECT MIN(date) FROM gsc_site_daily WHERE property_id IN ({$in})")->fetchColumn();
            $days = $min ? max(7, (int) floor((strtotime('-2 days') - strtotime((string) $min)) / 86400) + 1) : 28;
        }
        // Google publica con ~2 días de retraso: el periodo termina hace 2 días
        $end = date('Y-m-d', strtotime('-2 days'));
        $start = date('Y-m-d', strtotime($end . ' -' . ($days - 1) . ' days'));
        $prevEnd = date('Y-m-d', strtotime($start . ' -1 day'));
        $prevStart = date('Y-m-d', strtotime($prevEnd . ' -' . ($days - 1) . ' days'));

        $stmt = $this->pdo->prepare("
            SELECT property_id,
                   SUM(CASE WHEN date BETWEEN :s AND :e THEN clicks ELSE 0 END) AS clicks,
                   SUM(CASE WHEN date BETWEEN :s AND :e THEN impressions ELSE 0 END) AS impressions,
                   SUM(CASE WHEN date BETWEEN :s AND :e THEN position * impressions ELSE 0 END) AS pos_weight,
                   SUM(CASE WHEN date BETWEEN :ps AND :pe THEN clicks ELSE 0 END) AS prev_clicks,
                   SUM(CASE WHEN date BETWEEN :ps AND :pe THEN impressions ELSE 0 END) AS prev_impressions
            FROM gsc_site_daily
            WHERE property_id IN ({$in}) AND date BETWEEN :ps AND :e
            GROUP BY property_id
        ");
        $stmt->execute([':s' => $start, ':e' => $end, ':ps' => $prevStart, ':pe' => $prevEnd]);

        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $impr = (int) $r['impressions'];
            $out[(int) $r['property_id']] = [
                'clicks' => (int) $r['clicks'],
                'impressions' => $impr,
                'ctr' => $impr > 0 ? $r['clicks'] / $impr : 0,
                'position' => $impr > 0 ? $r['pos_weight'] / $impr : null,
                'prev_clicks' => (int) $r['prev_clicks'],
                'prev_impressions' => (int) $r['prev_impressions'],
            ];
        }

        return $out;
    }

    protected function summaryText(array $s): string
    {
        $parts = [$s['linked'] . ' propiedad(es) enlazadas'];
        if ($s['skipped_clients'] > 0) {
            $parts[] = $s['skipped_clients'] . ' de clientes ignoradas (privadas)';
        }
        if (!empty($s['external'])) {
            $parts[] = $s['external'] . ' en «Otros dominios»';
        } elseif (!empty($s['unknown'])) {
            $parts[] = count($s['unknown']) . ' sin sitio en el CMS';
        }

        return implode(' · ', $parts) . '.';
    }
}
