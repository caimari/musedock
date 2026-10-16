<?php
/**
 * Autorización del master y propiedades virtuales por subdominio. Usa la BD real dentro de una transacción
 * que se deshace al final (no deja nada). Google simulado.
 *   php tests/search-console/authorization_test.php
 */
define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';
foreach (['Crypto', 'GoogleApiException', 'GoogleClient', 'PropertyService'] as $c) {
    require_once APP_ROOT . "/modules/search-console/services/{$c}.php";
}

use Modules\SearchConsole\Services\{GoogleClient, PropertyService};

$fails = 0;
$n = 0;
function ok(bool $c, string $m): void
{
    global $fails, $n;
    $n++;
    if (!$c) {
        $fails++;
        echo "  FALLA: $m\n";
    }
}

class FakeGoogle extends GoogleClient
{
    public array $sites = [];
    public function __construct() {}
    public function request(string $method, string $url, ?array $body = null): array { return ['siteEntry' => $this->sites]; }
}

$pdo = \Screenart\Musedock\Database::connect();
$pdo->beginTransaction();
try {
    $tenants = $pdo->query('SELECT id, domain FROM tenants WHERE is_client_domain = 0 ORDER BY id LIMIT 3')->fetchAll(PDO::FETCH_ASSOC);
    [$own, $client, $other] = $tenants;
    $parentDomain = 'testparent-' . bin2hex(random_bytes(3)) . '.example';
    $pdo->prepare('UPDATE tenants SET domain = ? WHERE id = ?')->execute(['own.' . $parentDomain, $own['id']]);
    $pdo->prepare('UPDATE tenants SET domain = ?, is_client_domain = 1 WHERE id = ?')->execute(['cliente.' . $parentDomain, $client['id']]);

    $pdo->prepare("INSERT INTO gsc_credentials (owner_type, tenant_id, client_email, key_encrypted) VALUES ('platform', NULL, 'platform@test', 'gsc1:x') RETURNING id")->execute();
    $credId = (int) $pdo->query("SELECT id FROM gsc_credentials WHERE client_email = 'platform@test' ORDER BY id DESC LIMIT 1")->fetchColumn();
    $cred = ['id' => $credId, 'owner_type' => 'platform', 'tenant_id' => null];
    $pdo->prepare("INSERT INTO gsc_properties (credential_id, tenant_id, domain, site_url, permission_level, status, external) VALUES (?, NULL, ?, ?, 'siteOwner', 'active', 0)")
        ->execute([$credId, $parentDomain, 'sc-domain:' . $parentDomain]);
    $parentId = (int) $pdo->lastInsertId();
    $svc = new PropertyService($pdo);

    echo "== 1. Subdominios de tenants propios: propiedad virtual automática\n";
    ok($svc->hostFilter('Own.' . $parentDomain) === 'https://own.' . $parentDomain . '/', 'el filtro es el prefijo de URL del subdominio');
    ok($svc->parentProperty($credId, 'own.' . $parentDomain)['id'] === $parentId, 'encuentra la propiedad de dominio que cubre al subdominio');
    ok($svc->parentProperty($credId, $parentDomain) === null, 'el propio dominio no es «subdominio» de sí mismo');
    $made = $svc->syncVirtualHosts($credId);
    ok($made === 1, 'solo se crea la del tenant propio (el cliente no, sin autorizar): ' . $made);
    $virtuals = $pdo->query("SELECT * FROM gsc_properties WHERE credential_id = $credId AND page_filter <> ''")->fetchAll(PDO::FETCH_ASSOC);
    ok(count($virtuals) === 1 && $virtuals[0]['domain'] === 'own.' . $parentDomain && (int) $virtuals[0]['authorized'] === 0 && (int) $virtuals[0]['parent_id'] === $parentId, 'fila virtual con su filtro, su madre y sin autorización');
    ok($svc->syncVirtualHosts($credId) === 1 && (int) $pdo->query("SELECT COUNT(*) FROM gsc_properties WHERE credential_id = $credId AND page_filter <> ''")->fetchColumn() === 1, 'repetir no duplica filas');

    echo "== 2. Cada tenant ve solo lo suyo\n";
    ok(array_column($svc->visibleForTenant((int) $own['id']), 'domain') === ['own.' . $parentDomain], 'el tenant propio ve su subdominio, no la propiedad del dominio principal');
    ok($svc->visibleForTenant((int) $client['id']) === [], 'el cliente sin autorizar no ve nada');
    ok($svc->visibleForTenant((int) $other['id']) === [], 'otro tenant no ve nada de ellos');
    $saDomains = array_column($svc->visibleForSuperadmin(), 'domain');
    ok(in_array($parentDomain, $saDomains, true) && in_array('own.' . $parentDomain, $saDomains, true), 'el master ve el dominio y los subdominios de sus tenants propios');

    echo "== 3. El master autoriza a un cliente\n";
    $vid = $svc->ensureVirtual($credId, $svc->parentProperty($credId, 'cliente.' . $parentDomain), 'cliente.' . $parentDomain, (int) $client['id'], true);
    ok($vid === $svc->ensureVirtual($credId, $svc->parentProperty($credId, 'cliente.' . $parentDomain), 'cliente.' . $parentDomain, (int) $client['id'], true), 'autorizar dos veces devuelve la misma fila');
    ok(array_column($svc->visibleForTenant((int) $client['id']), 'domain') === ['cliente.' . $parentDomain], 'el cliente autorizado ve su subdominio, solo el suyo');
    ok(!in_array('own.' . $parentDomain, array_column($svc->visibleForTenant((int) $client['id']), 'domain'), true), 'y no el de otro tenant');
    ok(in_array('cliente.' . $parentDomain, array_column($svc->visibleForSuperadmin(), 'domain'), true), 'el master ve lo que él mismo autorizó');
    ok($svc->syncVirtualHosts($credId) === 2, 'syncVirtualHosts conserva al cliente autorizado');
    $svc->purgePlatformDataOfClients();
    ok((int) $pdo->query("SELECT COUNT(*) FROM gsc_properties WHERE id = $vid")->fetchColumn() === 1, 'la purga de clientes NO borra lo autorizado');
    $state = array_column($svc->clientTenantsForAuthorization($credId), null, 'domain')['cliente.' . $parentDomain] ?? null;
    ok($state && $state['authorized'] && $state['virtual'] && $state['property_id'] === $vid, 'la lista del master marca al cliente como autorizado');

    echo "== 4. Dominios propios de clientes (no subdominios)\n";
    $clientOwnDomain = 'cliente-' . bin2hex(random_bytes(3)) . '.example';
    ok(!$svc->isAuthorized($credId, $clientOwnDomain), 'sin autorizar');
    $svc->markPending($credId, $clientOwnDomain, (int) $other['id'], false, 'esperando', true);
    ok($svc->isAuthorized($credId, $clientOwnDomain), 'markPending(authorized) deja la marca puesta');
    $pdo->prepare('UPDATE tenants SET domain = ?, is_client_domain = 1 WHERE id = ?')->execute([$clientOwnDomain, $other['id']]);
    $fake = new FakeGoogle();
    $fake->sites = [['siteUrl' => 'sc-domain:' . $parentDomain, 'permissionLevel' => 'siteOwner'], ['siteUrl' => 'sc-domain:' . $clientOwnDomain, 'permissionLevel' => 'siteOwner']];
    $summary = $svc->refreshFromGoogle($cred, $fake);
    ok($summary['skipped_clients'] === 0 && array_column($svc->visibleForTenant((int) $other['id']), 'domain') === [$clientOwnDomain], 'un dominio de cliente autorizado SÍ se enlaza al releer Google y su cliente lo ve');
    ok((int) $pdo->query("SELECT COUNT(*) FROM gsc_properties WHERE credential_id = $credId AND page_filter <> '' AND status = 'active'")->fetchColumn() === 2, 'releer Google no desactiva las propiedades virtuales (no son de Google)');
    $fake->sites = [['siteUrl' => 'sc-domain:' . $parentDomain, 'permissionLevel' => 'siteOwner'], ['siteUrl' => 'sc-domain:otro-cliente.example', 'permissionLevel' => 'siteOwner']];

    echo "== 5. Retirar la autorización\n";
    $authRow = (int) $pdo->query("SELECT id FROM gsc_properties WHERE credential_id = $credId AND domain = '$clientOwnDomain'")->fetchColumn();
    $svc->revokeAuthorization($authRow);
    ok((int) $pdo->query("SELECT COUNT(*) FROM gsc_properties WHERE id = $authRow")->fetchColumn() === 0 && $svc->visibleForTenant((int) $other['id']) === [], 'retirar borra los datos y el cliente deja de verlos');
    $svc->revokeAuthorization($vid);
    ok((int) $pdo->query("SELECT COUNT(*) FROM gsc_properties WHERE id = $vid")->fetchColumn() === 0 && $svc->visibleForTenant((int) $client['id']) === [], 'igual con un subdominio');

    echo "== 6. Si un tenant propio pasa a cliente, sus datos se purgan\n";
    $pdo->prepare('UPDATE tenants SET is_client_domain = 1 WHERE id = ?')->execute([$own['id']]);
    $svc->purgePlatformDataOfClients();
    ok((int) $pdo->query("SELECT COUNT(*) FROM gsc_properties WHERE credential_id = $credId AND domain = 'own.$parentDomain'")->fetchColumn() === 0, 'la propiedad virtual de un tenant que pasa a cliente (sin autorizar) se borra');

    echo "== 7. Sin propiedad madre activa\n";
    $pdo->prepare('UPDATE tenants SET is_client_domain = 0 WHERE id = ?')->execute([$own['id']]);
    $svc->syncVirtualHosts($credId);
    $pdo->prepare("UPDATE gsc_properties SET status = 'no_access' WHERE id = ?")->execute([$parentId]);
    $svc->syncVirtualHosts($credId);
    ok($pdo->query("SELECT status FROM gsc_properties WHERE credential_id = $credId AND domain = 'own.$parentDomain'")->fetchColumn() === 'no_access', 'si la propiedad de dominio deja de estar activa, la virtual pasa a «sin acceso»');
} catch (Throwable $e) {
    echo 'EXCEPCIÓN: ', $e->getMessage(), ' (', basename($e->getFile()), ':', $e->getLine(), ")\n";
    $fails++;
} finally {
    $pdo->rollBack();
}

echo "\n" . ($fails ? "RESULTADO: $fails de $n comprobaciones FALLAN\n" : "RESULTADO: las $n comprobaciones pasan\n");
exit($fails ? 1 : 0);
