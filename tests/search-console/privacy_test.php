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
    [$own, $client] = $pdo->query('SELECT id, domain FROM tenants WHERE is_client_domain = 0 ORDER BY id LIMIT 2')->fetchAll(PDO::FETCH_ASSOC);
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
} finally {
    $pdo->rollBack();
}

echo "PASS: cifrado por ámbito y privacidad propios/clientes\n";
