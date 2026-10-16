<?php
/**
 * Test del módulo Search Console: cifrado por ámbito y reglas de privacidad
 * propios/clientes. Usa la BD real dentro de una transacción que se deshace al final.
 *
 *   php tests/search-console/privacy_test.php
 */
define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';
foreach (['Crypto', 'GoogleApiException', 'GoogleClient', 'PropertyService', 'CredentialStore', 'DomainVerifier'] as $class) {
    require_once APP_ROOT . "/modules/search-console/services/{$class}.php";
}

use Modules\SearchConsole\Services\Crypto;
use Modules\SearchConsole\Services\GoogleClient;
use Modules\SearchConsole\Services\PropertyService;

function check(bool $ok, string $what): void
{
    if (!$ok) {
        throw new RuntimeException('FALLO: ' . $what);
    }
    echo "  ✓ {$what}\n";
}

function throws(callable $fn): bool
{
    try {
        $fn();
    } catch (Throwable $e) {
        return true;
    }

    return false;
}

/** Cliente falso: devuelve una lista fija de propiedades sin llamar a Google. */
class FakeGoogleClient extends GoogleClient
{
    public array $sites = [];

    public function request(string $method, string $url, ?array $body = null): array
    {
        return ['siteEntry' => $this->sites];
    }
}

$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
openssl_pkey_export($key, $pem);
$serviceAccount = ['type' => 'service_account', 'client_email' => 'gsc-test@example.iam.gserviceaccount.com', 'private_key' => $pem];

echo "Cifrado\n";
$secret = Crypto::encrypt('{"private_key":"x"}', Crypto::scope(5));
check(Crypto::decrypt($secret, Crypto::scope(5)) === '{"private_key":"x"}', 'la clave de un tenant se descifra en su ámbito');
check(throws(fn () => Crypto::decrypt($secret, Crypto::scope(null))), 'la plataforma no puede descifrar la clave de un tenant');
check(throws(fn () => Crypto::decrypt($secret, Crypto::scope(6))), 'otro tenant no puede descifrarla');

echo "Clave de cuenta de servicio\n";
check(!throws(fn () => GoogleClient::assertServiceAccount($serviceAccount)), 'acepta una clave de cuenta de servicio válida');
check(throws(fn () => GoogleClient::assertServiceAccount(['type' => 'authorized_user'] + $serviceAccount)), 'rechaza credenciales que no son de cuenta de servicio');

echo "Privacidad propios / clientes\n";
$pdo = \Screenart\Musedock\Database::connect();
$pdo->beginTransaction();
try {
    // Tenants reales con la pizarra limpia dentro de la transacción (se deshace): no depende de lo ya dado de alta
    [$own, $client] = $pdo->query('SELECT id, domain FROM tenants WHERE is_client_domain = 0 ORDER BY id DESC LIMIT 2')->fetchAll(PDO::FETCH_ASSOC);
    $pdo->exec("DELETE FROM gsc_properties WHERE tenant_id IN ({$own['id']}, {$client['id']})");
    $pdo->exec("DELETE FROM domain_aliases WHERE tenant_id IN ({$own['id']}, {$client['id']})");
    $pdo->exec('DELETE FROM gsc_properties WHERE external = 1'); // dominios fuera del CMS ya dados de alta (p. ej. muserelay.com): dentro de la transacción, se deshace
    $pdo->prepare('UPDATE tenants SET is_client_domain = 1 WHERE id = ?')->execute([$client['id']]);
    $ownDomain = PropertyService::normalizeHost($own['domain']);
    $clientDomain = PropertyService::normalizeHost($client['domain']);

    $insCred = $pdo->prepare("INSERT INTO gsc_credentials (owner_type, tenant_id, client_email, key_encrypted) VALUES (?, ?, ?, 'gsc1:x') RETURNING id");
    $insCred->execute(['platform', null, 'platform@test']);
    $platformCred = ['id' => (int) $insCred->fetchColumn(), 'owner_type' => 'platform', 'tenant_id' => null];
    $insCred->execute(['tenant', $client['id'], 'client@test']);
    $clientCred = ['id' => (int) $insCred->fetchColumn(), 'owner_type' => 'tenant', 'tenant_id' => $client['id']];

    $svc = new PropertyService($pdo);
    $fake = new FakeGoogleClient($serviceAccount);
    $fake->sites = [
        ['siteUrl' => 'sc-domain:' . $ownDomain, 'permissionLevel' => 'siteOwner'],
        ['siteUrl' => 'https://www.' . $ownDomain . '/', 'permissionLevel' => 'siteOwner'],
        ['siteUrl' => 'sc-domain:' . $clientDomain, 'permissionLevel' => 'siteOwner'],
        ['siteUrl' => 'sc-domain:desconocido-' . bin2hex(random_bytes(3)) . '.com', 'permissionLevel' => 'siteOwner'],
    ];

    $summary = $svc->refreshFromGoogle($platformCred, $fake);
    check($summary['linked'] === 1, 'la plataforma enlaza solo el dominio propio (una propiedad por dominio)');
    check($summary['skipped_clients'] === 1, 'la plataforma ignora el dominio del cliente aunque Google se lo muestre');
    check(count($summary['unknown']) === 1, 'informa de propiedades sin sitio en el CMS');
    check($summary['external'] === 1 && count($svc->externalForSuperadmin()) === 1, 'guarda el dominio ajeno al CMS en «Otros dominios»');
    check(!in_array($summary['unknown'][0], array_column($svc->visibleForSuperadmin(), 'domain'), true), '«Otros dominios» no se mezclan con los dominios de tenants');
    check(array_column($svc->visibleForTenant((int) $own['id']), 'domain') === [$ownDomain], 'los tenants no ven «Otros dominios»');

    $ownProp = $pdo->query("SELECT site_url FROM gsc_properties WHERE credential_id = {$platformCred['id']}")->fetchColumn();
    check($ownProp === 'sc-domain:' . $ownDomain, 'prefiere la propiedad de dominio a la de prefijo de URL');

    $svc->refreshFromGoogle($clientCred, $fake);
    $clientProps = $pdo->query("SELECT domain FROM gsc_properties WHERE credential_id = {$clientCred['id']}")->fetchAll(PDO::FETCH_COLUMN);
    check($clientProps === [$clientDomain], 'la cuenta del cliente solo enlaza el dominio del cliente');

    // Simula datos que la plataforma leyó del cliente antes de reclasificarlo
    $pdo->prepare("INSERT INTO gsc_properties (credential_id, tenant_id, domain, site_url, status) VALUES (?, ?, ?, ?, 'active')")
        ->execute([$platformCred['id'], $client['id'], $clientDomain, 'sc-domain:' . $clientDomain]);
    $stalePropertyId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO gsc_site_daily (property_id, date, clicks, impressions) VALUES (?, CURRENT_DATE, 5, 50)")->execute([$stalePropertyId]);

    $saDomains = array_column($svc->visibleForSuperadmin(), 'domain');
    check(in_array($ownDomain, $saDomains, true), 'el superadmin ve su dominio propio');
    check(!in_array($clientDomain, $saDomains, true), 'el superadmin NO ve el dominio del cliente (ni el de su cuenta ni el antiguo)');

    $clientView = $svc->visibleForTenant((int) $client['id']);
    check(count($clientView) === 1 && $clientView[0]['owner_type'] === 'tenant', 'el cliente solo ve lo leído con su propia cuenta');
    check(array_column($svc->visibleForTenant((int) $own['id']), 'domain') === [$ownDomain], 'el tenant propio ve sus datos de la plataforma');

    check($svc->purgePlatformDataOfClients() === 1, 'al pasar a cliente se borra lo que la plataforma leyó de él');
    $left = (int) $pdo->query("SELECT COUNT(*) FROM gsc_site_daily WHERE property_id = {$stalePropertyId}")->fetchColumn();
    check($left === 0, 'sus métricas también se borran');
    check((int) $pdo->query("SELECT COUNT(*) FROM gsc_properties WHERE credential_id = {$clientCred['id']}")->fetchColumn() === 1, 'los datos de la cuenta propia del cliente se conservan');
    // Un dominio externo que luego se da de alta como tenant cliente se borra
    $externalDomain = $summary['unknown'][0];
    $pdo->prepare("INSERT INTO domain_aliases (tenant_id, domain, status) VALUES (?, ?, 'active')")->execute([$client['id'], $externalDomain]);
    $svc->purgePlatformDataOfClients();
    check(count($svc->externalForSuperadmin()) === 0, 'un dominio externo que pasa a ser de un cliente se borra');

    // Alias de dominio: los de tenants propios se ofrecen, los de clientes nunca
    $ins = $pdo->prepare('INSERT INTO domain_aliases (tenant_id, domain, status) VALUES (?, ?, ?)');
    $ins->execute([$own['id'], 'alias-propio-gsc.test', 'active']);
    $ins->execute([$own['id'], 'alias-suspendido-gsc.test', 'suspended']);
    $ins->execute([$own['id'], 'sub.' . $ownDomain, 'active']);
    $ins->execute([$own['id'], 'www.' . $ownDomain, 'active']);
    $ins->execute([$client['id'], 'alias-cliente-gsc.test', 'active']);
    $ins->execute([$client['id'], 'alias-cliente-susp-gsc.test', 'suspended']);
    $missing = [];
    foreach ($svc->ownDomainsWithoutProperty((int) $platformCred['id']) as $m) { $missing[$m['domain']] = $m; }
    check(isset($missing['alias-propio-gsc.test']) && empty($missing['alias-propio-gsc.test']['disabled']) && $missing['alias-propio-gsc.test']['tenant_id'] === (int) $own['id'] && !empty($missing['alias-propio-gsc.test']['alias_of']), 'un alias activo de un tenant propio se ofrece, indicando de qué tenant cuelga');
    check(isset($missing['alias-suspendido-gsc.test']['disabled']) && str_contains($missing['alias-suspendido-gsc.test']['disabled'], 'suspendido'), 'un alias suspendido sale deshabilitado, con el motivo');
    check(!isset($missing['alias-cliente-gsc.test']) && !isset($missing['alias-cliente-susp-gsc.test']), 'los alias de un cliente (activos o suspendidos) NO se ofrecen');
    check(!isset($missing['sub.' . $ownDomain]) && !isset($missing['www.' . $ownDomain]) && !isset($missing[$ownDomain]), 'un subdominio cubierto por una propiedad activa, o el mismo dominio con www, no se duplican');
    $dup = array_count_values(array_column($svc->ownDomainsWithoutProperty((int) $platformCred['id']), 'domain'));
    check(max($dup ?: [0]) === 1, 'ningún dominio sale dos veces en la lista');
    $map = $svc->hostMap();
    check(($map['alias-cliente-susp-gsc.test']['client'] ?? false) === true, 'hostMap conoce también los alias suspendidos de un cliente (no pasan por «externos»)');
    $svc->purgePlatformDataOfClients();
    $pdo->prepare("INSERT INTO gsc_properties (credential_id, tenant_id, domain, site_url, status) VALUES (?, ?, ?, ?, 'active')")->execute([$platformCred['id'], $client['id'], 'alias-cliente-gsc.test', 'sc-domain:alias-cliente-gsc.test']);
    check($svc->purgePlatformDataOfClients() === 1, 'si un tenant pasa a cliente, los datos de sus alias también se purgan');
    check(!in_array('alias-cliente-gsc.test', array_column($svc->visibleForSuperadmin(), 'domain'), true), 'el superadmin no ve datos de un alias de cliente');

    // Dar de baja conservando los datos
    $ownProp = (int) $pdo->query("SELECT id FROM gsc_properties WHERE credential_id = {$platformCred['id']} AND domain = " . $pdo->quote($ownDomain) . " AND page_filter = ''")->fetchColumn();
    $pdo->prepare("INSERT INTO gsc_site_daily (property_id, date, clicks, impressions) VALUES (?, CURRENT_DATE, 3, 30)")->execute([$ownProp]);
    $svc->archive($ownProp);
    check(!in_array($ownDomain, array_column($svc->visibleForSuperadmin(), 'domain'), true), 'una propiedad dada de baja deja de mostrarse al superadmin');
    check(!in_array($ownDomain, array_column($svc->visibleForTenant((int) $own['id']), 'domain'), true), 'y al tenant');
    check(in_array($ownDomain, array_column($svc->archivedForSuperadmin(), 'domain'), true), 'aparece en «dados de baja»');
    check((int) $pdo->query("SELECT COUNT(*) FROM gsc_site_daily WHERE property_id = {$ownProp}")->fetchColumn() === 1, 'sus datos NO se borran');
    check(!in_array($ownDomain, array_column($svc->ownDomainsWithoutProperty((int) $platformCred['id']), 'domain'), true), 'no se vuelve a ofrecer para dar de alta');
    $svc->refreshFromGoogle($platformCred, $fake);
    check($pdo->query("SELECT status FROM gsc_properties WHERE id = {$ownProp}")->fetchColumn() === 'archived', 'leer de Google no la reactiva sola');
    $svc->restore($ownProp);
    check(in_array($ownDomain, array_column($svc->visibleForSuperadmin(), 'domain'), true), 'reactivarla la devuelve a la lista con sus datos');
} finally {
    $pdo->rollBack();
}

echo "PASS: cifrado por ámbito y privacidad propios/clientes\n";
