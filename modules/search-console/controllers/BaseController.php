<?php

namespace Modules\SearchConsole\Controllers;

use Modules\SearchConsole\Services\CredentialStore;
use Modules\SearchConsole\Services\DomainVerifier;
use Modules\SearchConsole\Services\GoogleApiException;
use Modules\SearchConsole\Services\PropertyService;
use Screenart\Musedock\Database;

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

    abstract protected function authorizeManage(): void;

    abstract protected function actorEmail(): string;

    protected function baseUrl(): string
    {
        return '/' . trim($this->basePath, '/') . '/search-console';
    }

    /** POST: guardar la clave JSON (fichero subido o texto pegado). */
    public function saveCredential()
    {
        $this->authorizeManage();
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
        $this->authorizeManage();
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
        $this->authorizeManage();
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
        if ($result['status'] === 'verified') {
            try {
                $this->properties->refreshFromGoogle($row, $client);
            } catch (\Throwable $e) {
                // la propiedad ya existe en Google; se enlazará en la próxima actualización
            }
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** POST: desconectar la cuenta de servicio y borrar todos sus datos. */
    public function deleteCredential()
    {
        $this->authorizeManage();
        $row = $this->credentials->find($this->scopeTenantId);
        if ($row) {
            $this->credentials->delete((int) $row['id']);
            flash('success', 'Cuenta de servicio desconectada y datos borrados.');
        }

        return redirect($this->baseUrl());
    }

    /** ¿Puede este ámbito dar de alta ese dominio? */
    protected function domainAllowed(string $domain): bool
    {
        $owner = $this->properties->hostMap()[$domain] ?? null;
        if ($owner === null) {
            return false;
        }
        if ($this->scopeTenantId === null) {
            return !$owner['client'];
        }

        return $owner['tenant_id'] === $this->scopeTenantId;
    }

    /** Totales de los últimos $days días y del periodo anterior, por propiedad. */
    protected function totals(array $propertyIds, int $days = 28): array
    {
        if (empty($propertyIds)) {
            return [];
        }
        $in = implode(',', array_map('intval', $propertyIds));
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
        if (!empty($s['unknown'])) {
            $parts[] = count($s['unknown']) . ' sin sitio en el CMS';
        }

        return implode(' · ', $parts) . '.';
    }
}
