<?php

namespace Modules\SearchConsole\Services;

use PDO;
use Screenart\Musedock\Database;

/**
 * Propiedades de Search Console y reglas de privacidad.
 *
 * Reglas (se aplican aquí y en todas las consultas de datos):
 *  1. La credencial de la plataforma solo se asocia al sitio principal y a los
 *     tenants propios (tenants.is_client_domain = 0). Nunca a un cliente.
 *  2. La credencial propia de un tenant solo se asocia a ese tenant.
 *  3. El superadmin solo ve propiedades leídas con la credencial de la plataforma
 *     de dominios propios. Lo de los clientes no aparece en ninguna vista suya.
 *  4. Si un tenant pasa a ser cliente, sus propiedades leídas con la credencial de
 *     la plataforma se borran con sus datos (purgePlatformDataOfClients).
 */
class PropertyService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connect();
    }

    public function isClientTenant(?int $tenantId): bool
    {
        if ($tenantId === null) {
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT is_client_domain FROM tenants WHERE id = ?');
        $stmt->execute([$tenantId]);

        return (int) $stmt->fetchColumn() === 1;
    }

    public static function mainDomain(): string
    {
        $main = (string) (\Screenart\Musedock\Env::get('MAIN_DOMAIN') ?: (function_exists('setting') ? setting('main_domain', '') : ''));
        if ($main === '' && function_exists('config')) {
            $main = (string) config('main_domain', '');
        }

        return self::normalizeHost($main);
    }

    public static function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('#^[a-z]+://#', '', $host);
        $host = explode('/', $host)[0];
        $host = explode(':', $host)[0];

        return preg_replace('/^www\./', '', $host);
    }

    /** Dominio de una propiedad: 'sc-domain:x.com' → x.com, 'https://www.x.com/' → x.com */
    public static function domainOfSiteUrl(string $siteUrl): string
    {
        return self::normalizeHost(str_starts_with($siteUrl, 'sc-domain:') ? substr($siteUrl, 10) : $siteUrl);
    }

    /**
     * Mapa host → ['tenant_id' => ?int, 'client' => bool] de todos los dominios del CMS
     * (dominio principal de cada tenant, sus alias y el sitio principal).
     */
    public function hostMap(): array
    {
        $map = [];
        foreach ($this->pdo->query('SELECT id, domain, is_client_domain FROM tenants') as $t) {
            $map[self::normalizeHost($t['domain'])] = ['tenant_id' => (int) $t['id'], 'client' => (int) $t['is_client_domain'] === 1];
        }
        try {
            $aliases = $this->pdo->query("SELECT a.domain, a.tenant_id, t.is_client_domain FROM domain_aliases a JOIN tenants t ON t.id = a.tenant_id WHERE a.status = 'active'");
            foreach ($aliases as $a) {
                $map[self::normalizeHost($a['domain'])] ??= ['tenant_id' => (int) $a['tenant_id'], 'client' => (int) $a['is_client_domain'] === 1];
            }
        } catch (\Throwable $e) {
            // tabla de alias opcional
        }
        $main = self::mainDomain();
        if ($main !== '') {
            $map[$main] = ['tenant_id' => null, 'client' => false];
        }

        return $map;
    }

    /**
     * Lee las propiedades que ve la credencial en Google y las asocia a tenants
     * respetando las reglas. Devuelve un resumen para mostrar al usuario.
     */
    public function refreshFromGoogle(array $credential, GoogleClient $client): array
    {
        $sites = $client->request('GET', 'https://www.googleapis.com/webmasters/v3/sites')['siteEntry'] ?? [];
        $map = $this->hostMap();
        $ownerTenant = $credential['owner_type'] === 'tenant' ? (int) $credential['tenant_id'] : null;

        // Una propiedad por dominio: preferir la de dominio (sc-domain) a las de prefijo de URL
        $chosen = [];
        $summary = ['linked' => 0, 'external' => 0, 'unknown' => [], 'skipped_clients' => 0, 'unverified' => 0];
        foreach ($sites as $site) {
            $siteUrl = (string) ($site['siteUrl'] ?? '');
            $level = (string) ($site['permissionLevel'] ?? '');
            if ($siteUrl === '') {
                continue;
            }
            if ($level === 'siteUnverifiedUser') {
                $summary['unverified']++;
                continue;
            }
            $domain = self::domainOfSiteUrl($siteUrl);
            $owner = $map[$domain] ?? null;
            if ($owner === null) {
                $summary['unknown'][] = $domain;
                if ($ownerTenant !== null) {
                    continue; // un tenant solo enlaza su propio dominio
                }
                // Plataforma: dominio de tu Search Console que no es ningún sitio del CMS
                $owner = ['tenant_id' => null, 'client' => false, 'external' => true];
            }
            if ($ownerTenant === null && $owner['client'] && !$this->isAuthorized((int) $credential['id'], $domain)) {
                $summary['skipped_clients']++; // regla 1: la plataforma no lee clientes, salvo que el master lo haya autorizado
                continue;
            }
            if ($ownerTenant !== null && $owner['tenant_id'] !== $ownerTenant) {
                continue; // regla 2
            }
            $rank = str_starts_with($siteUrl, 'sc-domain:') ? 0 : (str_starts_with($siteUrl, 'https://www.') ? 1 : (str_starts_with($siteUrl, 'https://') ? 2 : 3));
            if (!isset($chosen[$domain]) || $rank < $chosen[$domain]['rank']) {
                $chosen[$domain] = ['rank' => $rank, 'site_url' => $siteUrl, 'level' => $level, 'tenant_id' => $owner['tenant_id'], 'external' => !empty($owner['external'])];
            }
        }

        $upsert = $this->pdo->prepare("SELECT id FROM gsc_properties WHERE credential_id = ? AND domain = ? AND page_filter = ''");
        $update = $this->pdo->prepare("UPDATE gsc_properties SET site_url = ?, permission_level = ?, tenant_id = ?, external = ?, status = 'active', last_error = NULL, updated_at = NOW() WHERE id = ?");
        $insert = $this->pdo->prepare("INSERT INTO gsc_properties (credential_id, tenant_id, domain, site_url, permission_level, external, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())");
        foreach ($chosen as $domain => $c) {
            $upsert->execute([$credential['id'], $domain]);
            $id = $upsert->fetchColumn();
            if ($id) {
                $update->execute([$c['site_url'], $c['level'], $c['tenant_id'], $c['external'] ? 1 : 0, $id]);
            } else {
                $insert->execute([$credential['id'], $c['tenant_id'], $domain, $c['site_url'], $c['level'], $c['external'] ? 1 : 0]);
            }
            if ($c['external']) {
                $summary['external']++;
            } else {
                $summary['linked']++;
            }
        }

        // Propiedades que la credencial ya no ve en Google
        $known = array_keys($chosen);
        $stmt = $this->pdo->prepare("SELECT id, domain FROM gsc_properties WHERE credential_id = ? AND status = 'active' AND page_filter = ''");
        $stmt->execute([$credential['id']]);
        $lost = $this->pdo->prepare("UPDATE gsc_properties SET status = 'no_access', last_error = 'La cuenta de servicio ya no tiene acceso a esta propiedad en Search Console.', updated_at = NOW() WHERE id = ?");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
            if (!in_array($p['domain'], $known, true)) {
                $lost->execute([$p['id']]);
            }
        }

        $summary['unknown'] = array_values(array_unique($summary['unknown']));
        if ($ownerTenant === null) {
            $summary['virtual'] = $this->syncVirtualHosts((int) $credential['id']);
        }
        $this->purgePlatformDataOfClients();

        return $summary;
    }

    /**
     * Regla 4: borra las propiedades (y sus datos) leídas con la credencial de la
     * plataforma que pertenecen a tenants marcados como clientes. Incluye las que
     * se guardaron como «externas» y cuyo dominio es ahora un tenant cliente.
     */
    public function purgePlatformDataOfClients(): int
    {
        $ids = $this->pdo->query("
            SELECT p.id FROM gsc_properties p
            JOIN gsc_credentials c ON c.id = p.credential_id AND c.owner_type = 'platform'
            JOIN tenants t ON t.id = p.tenant_id AND t.is_client_domain = 1
            WHERE p.authorized = 0
        ")->fetchAll(PDO::FETCH_COLUMN);
        $clientHosts = array_keys(array_filter($this->hostMap(), fn ($o) => $o['client']));
        if ($clientHosts) {
            $platformRows = $this->pdo->query("
                SELECT p.id, p.domain FROM gsc_properties p
                JOIN gsc_credentials c ON c.id = p.credential_id AND c.owner_type = 'platform'
                WHERE p.authorized = 0
            ")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($platformRows as $row) {
                if (in_array($row['domain'], $clientHosts, true)) {
                    $ids[] = $row['id'];
                }
            }
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        foreach ($ids as $id) {
            $this->deleteProperty((int) $id);
        }

        return count($ids);
    }

    public function deleteProperty(int $propertyId): void
    {
        foreach (['gsc_site_daily', 'gsc_page_daily', 'gsc_query_daily', 'gsc_country_daily', 'gsc_device_daily', 'gsc_sync_runs'] as $table) {
            $this->pdo->prepare("DELETE FROM {$table} WHERE property_id = ?")->execute([$propertyId]);
        }
        $this->pdo->prepare('DELETE FROM gsc_properties WHERE id = ?')->execute([$propertyId]);
    }

    /** Regla 3: lo que puede ver el superadmin. */
    public function visibleForSuperadmin(): array
    {
        return $this->pdo->query("
            SELECT p.*, t.name AS tenant_name, t.label_color
            FROM gsc_properties p
            JOIN gsc_credentials c ON c.id = p.credential_id AND c.owner_type = 'platform'
            LEFT JOIN tenants t ON t.id = p.tenant_id
            WHERE p.external = 0 AND p.status <> 'pending' AND (p.tenant_id IS NULL OR t.is_client_domain = 0 OR p.authorized = 1)
            ORDER BY p.domain
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** «Otros dominios»: propiedades de la cuenta de la plataforma que no son sitios del CMS. */
    public function externalForSuperadmin(): array
    {
        return $this->pdo->query("
            SELECT p.* FROM gsc_properties p
            JOIN gsc_credentials c ON c.id = p.credential_id AND c.owner_type = 'platform'
            WHERE p.external = 1 AND p.tenant_id IS NULL AND p.status <> 'pending'
            ORDER BY p.domain
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Lo que ve un tenant: lo suyo; si es cliente, solo lo leído con su propia credencial. */
    public function visibleForTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, c.owner_type
            FROM gsc_properties p
            JOIN gsc_credentials c ON c.id = p.credential_id
            JOIN tenants t ON t.id = p.tenant_id
            WHERE p.tenant_id = ? AND p.status <> 'pending'
              AND (c.owner_type = 'tenant' AND c.tenant_id = p.tenant_id
                   OR c.owner_type = 'platform' AND (t.is_client_domain = 0 OR p.authorized = 1))
            ORDER BY c.owner_type DESC, p.domain
        ");
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Dominios propios del CMS que la plataforma aún no tiene en Search Console. */
    public function ownDomainsWithoutProperty(int $platformCredentialId): array
    {
        // Solo cuenta como «ya la tengo» una propiedad activa. Las pendientes de verificar (Google aún no
        // ve el TXT) y las que perdieron el acceso siguen en la lista, con su estado.
        $stmt = $this->pdo->prepare('SELECT domain, status, verification_note, updated_at FROM gsc_properties WHERE credential_id = ?');
        $stmt->execute([$platformCredentialId]);
        $have = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $have[$row['domain']] = $row;
        }
        $entry = static fn (?array $row) => ['state' => $row['status'] ?? null, 'note' => $row['verification_note'] ?? null, 'checked_at' => $row['updated_at'] ?? null];

        $missing = [];
        foreach ($this->pdo->query('SELECT id, name, domain FROM tenants WHERE is_client_domain = 0 ORDER BY domain') as $t) {
            $domain = self::normalizeHost($t['domain']);
            if (($have[$domain]['status'] ?? null) !== 'active') {
                $missing[] = ['tenant_id' => (int) $t['id'], 'name' => $t['name'], 'domain' => $domain] + $entry($have[$domain] ?? null);
            }
        }
        $main = self::mainDomain();
        if ($main !== '' && ($have[$main]['status'] ?? null) !== 'active') {
            array_unshift($missing, ['tenant_id' => null, 'name' => 'Sitio principal', 'domain' => $main] + $entry($have[$main] ?? null));
        }

        return $missing;
    }

    /**
     * Guarda que un dominio está pendiente de verificar (el TXT está puesto o por poner pero Google aún
     * no lo reconoce). Así el estado sobrevive a recargar la página y lo reintenta el cron del servidor.
     */
    public function markPending(int $credentialId, string $domain, ?int $tenantId, bool $external, string $note, bool $authorized = false): void
    {
        $now = date('Y-m-d H:i:s');
        $note = mb_substr($note, 0, 500);
        $stmt = $this->pdo->prepare('SELECT id, status FROM gsc_properties WHERE credential_id = ? AND domain = ?');
        $stmt->execute([$credentialId, $domain]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            if ($row['status'] !== 'active') {
                $this->pdo->prepare("UPDATE gsc_properties SET status = 'pending', verification_note = ?, updated_at = ? WHERE id = ?")->execute([$note, $now, $row['id']]);
            }
            if ($authorized) {
                $this->pdo->prepare('UPDATE gsc_properties SET authorized = 1 WHERE id = ?')->execute([$row['id']]);
            }

            return;
        }
        $this->pdo->prepare("INSERT INTO gsc_properties (credential_id, tenant_id, domain, site_url, status, verification_note, external, authorized, created_at, updated_at) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?)")
            ->execute([$credentialId, $tenantId, $domain, 'sc-domain:' . $domain, $note, $external ? 1 : 0, $authorized ? 1 : 0, $now, $now]);
    }

    /** ¿El master autorizó a propósito esta propiedad de un tenant (cliente)? */
    public function isAuthorized(int $credentialId, string $domain): bool
    {
        $stmt = $this->pdo->prepare("SELECT authorized FROM gsc_properties WHERE credential_id = ? AND domain = ? AND page_filter = ''");
        $stmt->execute([$credentialId, $domain]);

        return (int) $stmt->fetchColumn() === 1;
    }

    /** Propiedad de dominio activa de la plataforma que cubre a un subdominio (musedock.com cubre a x.musedock.com), o null. */
    public function parentProperty(int $credentialId, string $host): ?array
    {
        $labels = explode('.', self::normalizeHost($host));
        for ($i = 1; $i < count($labels) - 1; $i++) {
            $stmt = $this->pdo->prepare("SELECT * FROM gsc_properties WHERE credential_id = ? AND domain = ? AND page_filter = '' AND status = 'active' AND site_url LIKE 'sc-domain:%'");
            $stmt->execute([$credentialId, implode('.', array_slice($labels, $i))]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                return $row;
            }
        }

        return null;
    }

    /** Prefijo de URL que delimita un subdominio dentro de la propiedad de su dominio. */
    public static function hostFilter(string $host): string
    {
        return 'https://' . self::normalizeHost($host) . '/';
    }

    /**
     * Crea (si no existe) la propiedad «virtual» de un subdominio: sus datos son los de la propiedad
     * de dominio que lo cubre, descargados con un filtro de página. Así cada tenant ve solo lo suyo.
     */
    public function ensureVirtual(int $credentialId, array $parent, string $host, ?int $tenantId, bool $authorized): int
    {
        $host = self::normalizeHost($host);
        $filter = self::hostFilter($host);
        $stmt = $this->pdo->prepare('SELECT id FROM gsc_properties WHERE credential_id = ? AND site_url = ? AND page_filter = ?');
        $stmt->execute([$credentialId, $parent['site_url'], $filter]);
        $now = date('Y-m-d H:i:s');
        if ($id = $stmt->fetchColumn()) {
            $this->pdo->prepare("UPDATE gsc_properties SET tenant_id = ?, domain = ?, parent_id = ?, status = 'active', authorized = ?, updated_at = ? WHERE id = ?")
                ->execute([$tenantId, $host, $parent['id'], $authorized ? 1 : 0, $now, $id]);

            return (int) $id;
        }
        $this->pdo->prepare("INSERT INTO gsc_properties (credential_id, tenant_id, domain, site_url, permission_level, status, page_filter, parent_id, authorized, external, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'active', ?, ?, ?, 0, ?, ?)")
            ->execute([$credentialId, $tenantId, $host, $parent['site_url'], $parent['permission_level'], $filter, $parent['id'], $authorized ? 1 : 0, $now, $now]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Garantiza la propiedad virtual de cada tenant PROPIO cuyo dominio es un subdominio de una propiedad activa
     * (los clientes solo la tienen si el master los autorizó). Las filas virtuales que ya no tienen propiedad madre
     * activa, o cuyo tenant dejó de existir, pasan a «sin acceso». Devuelve cuántas filas virtuales hay.
     */
    public function syncVirtualHosts(int $credentialId): int
    {
        $n = 0;
        foreach ($this->pdo->query('SELECT id, domain, is_client_domain FROM tenants') as $t) {
            $host = self::normalizeHost($t['domain']);
            $parent = $this->parentProperty($credentialId, $host);
            if ($parent === null) {
                continue;
            }
            $existing = $this->pdo->prepare('SELECT authorized FROM gsc_properties WHERE credential_id = ? AND site_url = ? AND page_filter = ?');
            $existing->execute([$credentialId, $parent['site_url'], self::hostFilter($host)]);
            $row = $existing->fetch(PDO::FETCH_ASSOC);
            if ((int) $t['is_client_domain'] === 1 && !$row) {
                continue; // un cliente solo tiene su propiedad virtual si el master lo autorizó
            }
            $this->ensureVirtual($credentialId, $parent, $host, (int) $t['id'], $row ? (int) $row['authorized'] === 1 : false);
            $n++;
        }
        $orphans = $this->pdo->prepare("SELECT v.id FROM gsc_properties v LEFT JOIN gsc_properties p ON p.id = v.parent_id AND p.status = 'active' WHERE v.credential_id = ? AND v.page_filter <> '' AND v.status = 'active' AND p.id IS NULL");
        $orphans->execute([$credentialId]);
        foreach ($orphans->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $this->pdo->prepare("UPDATE gsc_properties SET status = 'no_access', last_error = 'La propiedad de dominio que cubría este subdominio ya no está activa.' WHERE id = ?")->execute([$id]);
        }

        return $n;
    }

    /** Tenants cliente y si el master los ha autorizado ya (lista del master). */
    public function clientTenantsForAuthorization(int $platformCredentialId): array
    {
        $out = [];
        $stmt = $this->pdo->prepare("SELECT id, domain, authorized, status, verification_note, page_filter FROM gsc_properties WHERE credential_id = ? AND tenant_id IS NOT NULL AND authorized = 1");
        $stmt->execute([$platformCredentialId]);
        $props = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $props[$p['domain']] = $p;
        }
        foreach ($this->pdo->query('SELECT id, name, domain FROM tenants WHERE is_client_domain = 1 ORDER BY domain') as $t) {
            $domain = self::normalizeHost($t['domain']);
            $p = $props[$domain] ?? null;
            $out[] = [
                'tenant_id' => (int) $t['id'], 'name' => $t['name'], 'domain' => $domain,
                'authorized' => $p !== null, 'property_id' => $p['id'] ?? null, 'state' => $p['status'] ?? null, 'note' => $p['verification_note'] ?? null,
                'virtual' => $p !== null && $p['page_filter'] !== '',
            ];
        }

        return $out;
    }

    /** El master retira la autorización: la propiedad y sus datos se borran de la plataforma. */
    public function revokeAuthorization(int $propertyId): void
    {
        $this->pdo->prepare('UPDATE gsc_properties SET authorized = 0 WHERE id = ?')->execute([$propertyId]);
        $this->purgePlatformDataOfClients();
        // Si no era un cliente (nada que purgar), no queda nada autorizado que mostrar
    }

    /** Dominios externos (fuera del CMS) pendientes de verificar, solo plataforma. */
    public function pendingExternal(int $platformCredentialId): array
    {
        $stmt = $this->pdo->prepare("SELECT domain, verification_note, updated_at FROM gsc_properties WHERE credential_id = ? AND status = 'pending' AND external = 1 AND tenant_id IS NULL ORDER BY domain");
        $stmt->execute([$platformCredentialId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
