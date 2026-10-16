<?php
/**
 * DomainVerifier del módulo Search Console con el registro único de cuentas DNS.
 * Datos ficticios, Google y DNS simulados, SQLite en memoria.
 *
 *   php tests/search-console/verifier_test.php
 */
define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';
foreach (['Crypto', 'GoogleApiException', 'GoogleClient', 'PropertyService', 'DomainVerifier'] as $c) {
    require_once APP_ROOT . "/modules/search-console/services/{$c}.php";
}

use Modules\SearchConsole\Services\{DomainVerifier, GoogleApiException, GoogleClient};
use Screenart\Musedock\Services\DnsAccounts;
use Screenart\Musedock\Services\Dns\{DnsZoneClient, DnsZones};

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

class FakeDns implements DnsZoneClient
{
    public array $txt = [];
    public function __construct(private array $zones) {}
    public function findZone(string $d): ?array { return isset($this->zones[$d]) ? ['id' => $this->zones[$d], 'name' => $d] : null; }
    public function ensureTxt(string $z, string $n, string $c): void { $this->txt[] = [$z, $n, $c]; }
    public function verify(): array { return ['ok' => true, 'message' => '']; }
}

class FakeGoogle extends GoogleClient
{
    public array $calls = [];
    public function __construct(public int $verifyStatus = 200, public int $putStatus = 200) {}
    public function request(string $method, string $url, ?array $body = null): array
    {
        $this->calls[] = "$method $url";
        if (str_contains($url, '/token')) {
            return ['token' => 'google-site-verification=TOKEN123'];
        }
        if (str_contains($url, '/webResource') && $this->verifyStatus !== 200) {
            throw GoogleApiException::fromResponse($this->verifyStatus, '{}');
        }

        return [];
    }
}

function verifier(FakeGoogle $g, ?FakeDns $dns): array
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $reg = new DnsAccounts($pdo, 'clave-de-prueba');
    if ($dns) {
        $reg->save(null, ['provider' => 'cloudflare', 'name' => 'Prueba', 'credentials' => ['api_token' => 'tok_ficticio_000000000000']]);
    }
    $zones = new DnsZones($reg, fn () => $dns);

    return [new DomainVerifier($g, $zones), $dns];
}

echo "== Zona en el registro DNS: TXT automático\n";
[$v, $dns] = verifier($g = new FakeGoogle(), new FakeDns(['ejemplo.com' => 'Z1']));
$r = $v->verify('blog.ejemplo.com');
ok($r['status'] === 'verified', 'verificado: ' . $r['message']);
ok($dns->txt === [['Z1', 'blog.ejemplo.com', 'google-site-verification=TOKEN123']], 'publica el TXT en la zona del dominio padre');
ok(count(array_filter($g->calls, fn ($c) => str_starts_with($c, 'PUT'))) === 1, 'da de alta la propiedad en Search Console');

echo "== Zona en el registro pero Google aún no ve el TXT\n";
[$v, $dns] = verifier(new FakeGoogle(400), new FakeDns(['ejemplo.com' => 'Z1']));
ok($v->verify('ejemplo.com')['status'] === 'pending', 'pendiente de propagación DNS');

echo "== Sin cuenta DNS: TXT a mano\n";
[$v] = verifier(new FakeGoogle(400), null);
$r = $v->verify('ejemplo.com');
ok($r['status'] === 'manual' && str_contains($r['message'], 'google-site-verification=TOKEN123') && str_contains($r['message'], 'Cuentas DNS'), 'devuelve el TXT y apunta a Cuentas DNS');
[$v] = verifier($g = new FakeGoogle(200), null);
$r = $v->verify('ejemplo.com');
ok($r['status'] === 'verified', 'si el TXT ya estaba puesto a mano, verifica igualmente');

echo "== Errores\n";
[$v] = verifier(new FakeGoogle(403), null);
ok($v->verify('ejemplo.com')['status'] === 'error', 'Google rechaza (403) → error');
ok($v->verify('no valido')['status'] === 'error', 'dominio no válido → error');

echo "\n" . ($fails ? "RESULTADO: $fails de $n comprobaciones FALLAN\n" : "RESULTADO: las $n comprobaciones pasan\n");
exit($fails ? 1 : 0);
