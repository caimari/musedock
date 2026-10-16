<?php
/**
 * Registro único de cuentas DNS (core): cifrado, lectura sin secretos, varias cuentas/proveedores,
 * importación de lo antiguo y localización de zonas. Datos ficticios, SQLite en memoria, sin red.
 *
 *   php tests/dns-accounts/registry_test.php
 */
define('APP_ROOT', dirname(__DIR__, 2));
foreach (['DnsAccounts', 'Dns/DnsZoneClient', 'Dns/CloudflareClient', 'Dns/DnsZones'] as $f) {
    require_once APP_ROOT . "/core/Services/{$f}.php";
}

use Screenart\Musedock\Services\DnsAccounts;
use Screenart\Musedock\Services\Dns\{CloudflareClient, DnsZones, DnsZoneClient};

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
function throws(callable $f): bool
{
    try {
        $f();
    } catch (Throwable $e) {
        return true;
    }

    return false;
}
function pdo(): PDO
{
    $p = new PDO('sqlite::memory:');
    $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $p;
}

$SECRET = 'cf_FICTICIO_token_0123456789abcdef';
$KEY = 'clave-de-prueba';

echo "== 1. Cifrado\n";
$reg = new DnsAccounts(pdo(), $KEY);
$a = $reg->encrypt('hola');
ok(str_starts_with($a, 'dnsa1:') && $reg->decrypt($a) === 'hola', 'ida y vuelta GCM');
ok($reg->encrypt('hola') !== $a, 'cada cifrado usa un IV distinto');
$raw = base64_decode(substr($a, 6));
$tampered = 'dnsa1:' . base64_encode(substr($raw, 0, -1) . chr(ord($raw[-1]) ^ 1));
ok(throws(fn () => $reg->decrypt($tampered)), 'un valor manipulado no se acepta (autenticado)');
ok(throws(fn () => (new DnsAccounts(pdo(), 'otra-clave'))->decrypt($a)), 'otra clave no descifra');
$legacyKey = hash('sha256', $KEY, true);
$iv = random_bytes(16);
$legacy = base64_encode($iv . openssl_encrypt('antiguo', 'aes-256-cbc', $legacyKey, OPENSSL_RAW_DATA, $iv));
ok($reg->decrypt($legacy) === 'antiguo', 'lee el formato antiguo (CBC de Domain Manager)');

echo "== 2. Guardar y listar\n";
$pdo = pdo();
$reg = new DnsAccounts($pdo, $KEY);
$id = $reg->save(null, ['provider' => 'cloudflare', 'name' => 'Principal', 'account_ref' => 'acc123', 'is_default' => 1,
    'credentials' => ['api_token' => $SECRET], 'metadata' => ['role' => 'primary']]);
$stored = $pdo->query('SELECT credentials FROM dns_provider_accounts')->fetchColumn();
ok(strpos($stored, $SECRET) === false && str_starts_with($stored, 'dnsa1:'), 'en la BBDD el token va cifrado, nunca en claro');
ok(strpos(json_encode($reg->all()), $SECRET) === false, 'los listados no contienen el secreto');
ok(strpos(json_encode($reg->find($id)), $SECRET) === false, 'find() no contiene el secreto');
ok($reg->all()[0]['credentials_masked']['api_token'] === 'cf_F••••••cdef', 'secreto enmascarado');
ok($reg->credentials($id)['api_token'] === $SECRET, 'credentials() lo devuelve descifrado de forma explícita');

echo "== 3. Sobrescribir y varias cuentas\n";
$reg->save($id, ['credentials' => ['api_token' => '']]);
ok($reg->credentials($id)['api_token'] === $SECRET, 'secreto vacío conserva el anterior');
$reg->save($id, ['credentials' => ['api_token' => 'cf_NUEVO_0123456789abcdef0123']]);
ok($reg->credentials($id)['api_token'] === 'cf_NUEVO_0123456789abcdef0123', 'se puede sobrescribir la clave en un solo sitio');
$id2 = $reg->save(null, ['provider' => 'cloudflare', 'name' => 'Segunda', 'credentials' => ['api_token' => 'cf_SEGUNDA_000000000000000000']]);
$id3 = $reg->save(null, ['provider' => 'porkbun', 'name' => 'Pork', 'credentials' => ['api_key' => 'pk_1234567890', 'secret_api_key' => 'sk_1234567890']]);
ok(count($reg->all()) === 3 && count($reg->all('cloudflare')) === 2, 'varias cuentas y varios proveedores');
$reg->save($id2, ['is_default' => 1]);
ok($reg->default('cloudflare')['id'] === $id2 && $reg->find($id)['is_default'] === 0, 'solo una cuenta por defecto por proveedor');
ok($reg->default('porkbun')['id'] === $id3, 'default de otro proveedor independiente');
ok(throws(fn () => $reg->save(null, ['provider' => 'inventado', 'name' => 'x', 'credentials' => []])), 'proveedor desconocido rechazado');
ok(throws(fn () => $reg->save(null, ['provider' => 'porkbun', 'name' => 'x', 'credentials' => ['api_key' => 'solo-una']])), 'faltan campos obligatorios');
ok(throws(fn () => $reg->save(null, ['provider' => 'cloudflare', 'name' => '', 'credentials' => ['api_token' => 'abcdefghij']])), 'nombre obligatorio');
$reg->save($id3, ['enabled' => 0]);
ok(count($reg->all(null, true)) === 2, 'las cuentas deshabilitadas no se ofrecen a los consumidores');

echo "== 4. Forma compatible con los servicios antiguos\n";
$cf = $reg->cloudflareAccounts();
ok(count($cf) === 2 && isset($cf[0]['api_token_decrypted'], $cf[0]['account_id'], $cf[0]['role'], $cf[0]['ssl_mode']), 'cloudflareAccounts() con la forma antigua');
ok($reg->cloudflareToken('primary') === 'cf_SEGUNDA_000000000000000000', 'cloudflareToken(role) devuelve primero la cuenta predeterminada');
ok($reg->cloudflareToken('inexistente') === '', 'rol sin cuenta → vacío');

echo "== 5. Importación de lo antiguo (idempotente)\n";
$pdo = pdo();
$reg = new DnsAccounts($pdo, $KEY);
$pdo->exec('CREATE TABLE cloudflare_accounts (id INTEGER PRIMARY KEY, name TEXT, api_token TEXT, account_id TEXT, role TEXT, is_default_for_new_domains INT, ssl_mode TEXT, enabled INT)');
$enc = function (string $v) use ($legacyKey) {
    $iv = random_bytes(16);

    return base64_encode($iv . openssl_encrypt($v, 'aes-256-cbc', $legacyKey, OPENSSL_RAW_DATA, $iv));
};
$ins = $pdo->prepare('INSERT INTO cloudflare_accounts VALUES (?,?,?,?,?,?,?,?)');
$ins->execute([1, 'Primary', $enc('tok_antiguo_primary_0000000000'), 'acc1', 'primary', 0, 'full', 1]);
$ins->execute([2, 'Custom Domains', $enc('tok_antiguo_domains_0000000000'), 'acc2', 'domains', 1, 'strict', 1]);
putenv('CLOUDFLARE_API_TOKEN=tok_antiguo_primary_0000000000'); // mismo que el de la tabla: no se duplica
putenv('CLOUDFLARE_CUSTOM_DOMAINS_API_TOKEN=tok_env_distinto_00000000000');
putenv('CLOUDFLARE_CUSTOM_DOMAINS_ACCOUNT_ID=accenv');
$r = $reg->importLegacy();
ok($r['legacy'] === 2 && $r['env'] === 1, 'importa 2 de la tabla antigua y 1 del .env (sin duplicar): ' . json_encode($r));
$cf = $reg->cloudflareAccounts();
$byName = array_column($cf, null, 'name');
ok($byName['Custom Domains']['ssl_mode'] === 'strict' && $byName['Custom Domains']['is_default_for_new_domains'] === 1, 'conserva rol, ssl y valor por defecto');
ok($byName['Primary']['api_token_decrypted'] === 'tok_antiguo_primary_0000000000', 'token antiguo legible tras importar');
$r2 = $reg->importLegacy();
ok($r2['legacy'] === 0 && $r2['env'] === 0 && count($reg->all('cloudflare')) === 3, 'segunda importación no duplica nada');
ok((int) $pdo->query('SELECT COUNT(*) FROM cloudflare_accounts')->fetchColumn() === 2, 'la tabla antigua no se toca');
putenv('CLOUDFLARE_API_TOKEN');
putenv('CLOUDFLARE_CUSTOM_DOMAINS_API_TOKEN');
putenv('CLOUDFLARE_CUSTOM_DOMAINS_ACCOUNT_ID');

echo "== 5b. Importar sin copiar tokens que Cloudflare rechaza\n";
$pdo = pdo();
$reg = new DnsAccounts($pdo, $KEY);
$pdo->exec('CREATE TABLE cloudflare_accounts (id INTEGER PRIMARY KEY, name TEXT, api_token TEXT, account_id TEXT, role TEXT, is_default_for_new_domains INT, ssl_mode TEXT, enabled INT)');
$ins = $pdo->prepare('INSERT INTO cloudflare_accounts VALUES (?,?,?,?,?,?,?,?)');
$ins->execute([1, 'Caducado', $enc('tok_caducado_000000000000'), 'a1', 'primary', 0, 'full', 1]);
$ins->execute([2, 'Bueno', $enc('tok_bueno_00000000000000'), 'a2', 'domains', 1, 'full', 1]);
$ins->execute([3, 'Sin red', $enc('tok_sinred_0000000000000'), 'a3', 'domains', 0, 'full', 1]);
$r = $reg->importLegacy(fn ($t) => $t === 'tok_caducado_000000000000' ? false : ($t === 'tok_sinred_0000000000000' ? null : true));
ok($r['legacy'] === 2 && $r['invalid'] === ['Caducado'], 'el token rechazado no se importa y se informa; el dudoso (sin red) sí: ' . json_encode($r));

echo "== 6. Localizar zonas (cuentas rotas no tumban a las demás)\n";
class FakeClient implements DnsZoneClient
{
    public array $txt = [];
    public function __construct(private array $zones, private bool $broken = false) {}
    public function findZone(string $d): ?array
    {
        if ($this->broken) {
            throw new RuntimeException('token inválido');
        }

        return isset($this->zones[$d]) ? ['id' => $this->zones[$d], 'name' => $d] : null;
    }
    public function ensureTxt(string $z, string $n, string $c): void { $this->txt[] = [$z, $n, $c]; }
    public function verify(): array { return ['ok' => !$this->broken, 'message' => 'x']; }
}
$pdo = pdo();
$reg = new DnsAccounts($pdo, $KEY);
$bad = $reg->save(null, ['provider' => 'cloudflare', 'name' => 'Rota', 'credentials' => ['api_token' => 'tok_rota_000000000000000000']]);
$good = $reg->save(null, ['provider' => 'cloudflare', 'name' => 'Buena', 'credentials' => ['api_token' => 'tok_buena_00000000000000000']]);
$clients = [];
$zones = new DnsZones($reg, function (array $acc, array $creds) use (&$clients, $bad) {
    return $clients[$acc['id']] ??= new FakeClient($acc['id'] === $bad ? [] : ['ejemplo.com' => 'Z1'], $acc['id'] === $bad);
});
$loc = $zones->locate('www.blog.ejemplo.com');
ok($loc && $loc['zone']['id'] === 'Z1' && $loc['account']['name'] === 'Buena', 'encuentra la zona del dominio padre aunque otra cuenta tenga el token roto');
ok($zones->locate('no-existe.org') === null, 'dominio sin zona → null');
$disabled = $reg->save($good, ['enabled' => 0]);
ok($zones->locate('ejemplo.com') === null, 'cuenta deshabilitada no se usa');

echo "== 7. Cliente Cloudflare (API simulada)\n";
$calls = [];
$tr = function (string $m, string $u, array $h, ?string $b) use (&$calls) {
    $calls[] = [$m, $u, $b];
    if (str_contains($u, '/user/tokens/verify')) {
        return ['status' => 200, 'body' => '{"success":true,"result":{"status":"active"}}'];
    }
    if ($m === 'GET' && str_contains($u, '/zones?')) {
        return ['status' => 200, 'body' => '{"success":true,"result":[{"id":"Z9","name":"ejemplo.com"}],"result_info":{"total_count":7}}'];
    }
    if ($m === 'GET' && str_contains($u, '/dns_records')) {
        return ['status' => 200, 'body' => '{"success":true,"result":[{"content":"\"google-site-verification=ABC\""}]}'];
    }
    if ($m === 'POST') {
        return ['status' => 200, 'body' => '{"success":true,"result":{}}'];
    }

    return ['status' => 403, 'body' => '{"success":false,"errors":[{"message":"Invalid access token"}]}'];
};
$cl = new CloudflareClient('tok_secreto_0000000000000000', 'acc9', $tr);
ok($cl->verify()['ok'] === true && $cl->findZone('ejemplo.com')['id'] === 'Z9' && $cl->zoneCount() === 7, 'verify, findZone y zoneCount');
ok(str_contains($calls[1][1], 'account.id=acc9'), 'filtra por cuenta si se indica');
$calls = [];
$cl->ensureTxt('Z9', 'ejemplo.com', 'google-site-verification=ABC');
ok(count(array_filter($calls, fn ($c) => $c[0] === 'POST')) === 0, 'no duplica un TXT que ya existe');
$cl->ensureTxt('Z9', 'ejemplo.com', 'google-site-verification=NUEVO');
ok(count(array_filter($calls, fn ($c) => $c[0] === 'POST')) === 1, 'crea el TXT si no existe');
$bad = new CloudflareClient('tok_secreto_0000000000000000', null, fn () => ['status' => 403, 'body' => '{"success":false,"errors":[{"message":"Invalid access token"}]}']);
$v = $bad->verify();
ok($v['ok'] === false && strpos($v['message'], 'tok_secreto') === false, 'un error no filtra el token');

echo "\n" . ($fails ? "RESULTADO: $fails de $n comprobaciones FALLAN\n" : "RESULTADO: las $n comprobaciones pasan\n");
exit($fails ? 1 : 0);
