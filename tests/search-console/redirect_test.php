<?php
/**
 * Mover el SEO entre dominio y alias (301 en Caddy): solo tenants propios, ruta al PRINCIPIO, verificación y deshacer.
 * Caddy y la red están simulados; la BD real va dentro de una transacción que se deshace.
 *   php tests/search-console/redirect_test.php
 */
define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/core/Env.php';
\Screenart\Musedock\Env::load();
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/core/helpers.php';
foreach (['PropertyService', 'RedirectService'] as $c) {
    require_once APP_ROOT . "/modules/search-console/services/{$c}.php";
}

use Modules\SearchConsole\Services\RedirectService as R;

$fails = 0;
function ok(bool $c, string $m): void { global $fails; if (!$c) { $fails++; echo "  FALLA: $m\n"; } }

/** Caddy simulado: lista de rutas con PUT (inserta), DELETE y GET como la API real. */
class FakeCaddy
{
    public array $routes = [['@id' => 'wildcard', 'match' => [['host' => ['*.musedock.com']]]], ['@id' => 'tenant', 'match' => [['host' => ['sub.test-redir.example']]]]];
    public int $puts = 0;
    public function __invoke(string $m, string $path, ?array $body): array
    {
        if ($m === 'GET') { return ['code' => 200, 'body' => json_encode($this->routes)]; }
        if (!preg_match('#routes/(\d+)$#', $path, $x)) { return ['code' => 400, 'body' => '']; }
        $i = (int) $x[1];
        if ($m === 'PUT') { array_splice($this->routes, $i, 0, [$body]); $this->puts++; return ['code' => 200, 'body' => '']; }
        if ($m === 'DELETE') { array_splice($this->routes, $i, 1); return ['code' => 200, 'body' => '']; }
        return ['code' => 405, 'body' => ''];
    }
}

$pdo = \Screenart\Musedock\Database::connect();
$pdo->beginTransaction();
try {
    [$own, $client] = $pdo->query('SELECT id, domain FROM tenants WHERE is_client_domain = 0 ORDER BY id DESC LIMIT 2')->fetchAll(PDO::FETCH_ASSOC);
    $pdo->exec("DELETE FROM domain_aliases WHERE tenant_id IN ({$own['id']}, {$client['id']})");
    $pdo->exec("DELETE FROM domain_redirects WHERE domain LIKE '%test-redir.example'");
    $pdo->prepare('UPDATE tenants SET domain = ? WHERE id = ?')->execute(['sub.test-redir.example', $own['id']]);
    $pdo->prepare('UPDATE tenants SET domain = ?, is_client_domain = 1 WHERE id = ?')->execute(['cliente.test-redir.example', $client['id']]);
    $ins = $pdo->prepare('INSERT INTO domain_aliases (tenant_id, domain, status) VALUES (?, ?, ?)');
    $ins->execute([$own['id'], 'alias-redir.example', 'active']);
    $ins->execute([$own['id'], 'alias-susp.example', 'suspended']);
    $ins->execute([$client['id'], 'alias-cliente-redir.example', 'active']);

    $caddy = new FakeCaddy();
    $probeOk = fn (string $host) => ['code' => 301, 'location' => 'https://alias-redir.example/ruta-de-prueba?x=1'];
    $svc = new R($pdo, $caddy, $probeOk, fn ($h) => 200);

    $pairs = [];
    foreach ($svc->pairs() as $p) { $pairs[$p['alias']] = $p; }
    ok(isset($pairs['alias-redir.example']) && isset($pairs['alias-susp.example']) && !isset($pairs['alias-cliente-redir.example']), 'las parejas incluyen alias de tenants propios y NO los de clientes');

    ok($svc->validate('sub.test-redir.example', 'otro.example') !== null, 'solo entre un tenant y su propio alias');
    ok($svc->validate('cliente.test-redir.example', 'alias-cliente-redir.example') !== null, 'un tenant cliente no se puede mover');
    ok($svc->validate('sub.test-redir.example', 'alias-susp.example') !== null, 'un alias suspendido no vale');

    $r = $svc->apply('sub.test-redir.example', 'alias-redir.example');
    ok($r['ok'] === true, 'aplica: ' . $r['message']);
    ok($caddy->routes[0]['@id'] === 'redirect-sub-test-redir-example' && count($caddy->routes) === 3, 'la ruta se inserta al PRINCIPIO (antes del comodín y del tenant)');
    ok($caddy->routes[0]['handle'][0]['headers']['Location'][0] === 'https://alias-redir.example{http.request.uri}' && $caddy->routes[0]['handle'][0]['status_code'] === '301', '301 con la ruta conservada');
    $row = $pdo->query("SELECT redirect_to, status, caddy_configured FROM domain_redirects WHERE domain = 'sub.test-redir.example'")->fetch(PDO::FETCH_ASSOC);
    ok($row && $row['redirect_to'] === 'https://alias-redir.example' && $row['status'] === 'active' && (int) $row['caddy_configured'] === 1, 'queda guardada en domain_redirects (el Domain Manager y repair la ven)');

    $state = [];
    foreach ($svc->pairs() as $p) { $state[$p['alias']] = $p['redirect']; }
    ok($state['alias-redir.example'] === 'tenant', 'el panel muestra que el dominio del tenant redirige');
    ok($svc->apply('alias-redir.example', 'sub.test-redir.example')['ok'] === false, 'no se puede crear la redirección inversa encima (bucle)');
    ok($svc->apply('sub.test-redir.example', 'alias-redir.example')['ok'] === false, 'ni repetirla');

    $r = $svc->remove('sub.test-redir.example');
    ok($r['ok'] && count($caddy->routes) === 2 && !$pdo->query("SELECT 1 FROM domain_redirects WHERE domain = 'sub.test-redir.example'")->fetchColumn(), 'quitar deshace la ruta y la fila');

    // la inversa: alias → dominio del tenant
    $caddy2 = new FakeCaddy();
    $svc2 = new R($pdo, $caddy2, fn ($h) => ['code' => 301, 'location' => 'https://sub.test-redir.example/x'], fn ($h) => 200);
    ok($svc2->apply('alias-redir.example', 'sub.test-redir.example')['ok'] === true && $caddy2->routes[0]['@id'] === 'redirect-alias-redir-example', 'también al revés: alias → dominio del tenant');
    $svc2->remove('alias-redir.example');

    // seguridad: destino caído → no se toca nada
    $caddy3 = new FakeCaddy();
    $svc3 = new R($pdo, $caddy3, $probeOk, fn ($h) => 502);
    $r = $svc3->apply('sub.test-redir.example', 'alias-redir.example');
    ok($r['ok'] === false && $caddy3->puts === 0 && count($caddy3->routes) === 2, 'si el destino no responde, no se cambia nada');

    // seguridad: verificación falla → se deshace
    $caddy4 = new FakeCaddy();
    $svc4 = new R($pdo, $caddy4, fn ($h) => ['code' => 200, 'location' => null], fn ($h) => 200);
    $r = $svc4->apply('sub.test-redir.example', 'alias-redir.example');
    ok($r['ok'] === false && count($caddy4->routes) === 2 && !$pdo->query("SELECT 1 FROM domain_redirects WHERE domain = 'sub.test-redir.example'")->fetchColumn(), 'si la verificación falla, se deshace y no se guarda');

    // acción masiva: varias parejas de una vez, cada una por separado
    $caddy5 = new FakeCaddy();
    $svc5 = new R($pdo, $caddy5, fn ($h) => ['code' => 301, 'location' => 'https://' . (str_starts_with($h, 'sub.') ? 'alias-redir.example' : 'sub.test-redir.example') . '/x'], fn ($h) => 200);
    $res = $svc5->bulk('to_alias', ['sub.test-redir.example|alias-redir.example', 'cliente.test-redir.example|alias-cliente-redir.example', 'no-existe.example|x.example']);
    ok($res['ok'] === 1 && count($res['results']) === 1 && $res['results'][0]['ok'], 'bulk to_alias: aplica la pareja propia e ignora la del cliente y la inexistente');
    $two = $svc5->bulk('remove', ['sub.test-redir.example|alias-susp.example']);
    fwrite(STDERR, json_encode($two) . "\n");
    ok($two['failed'] === 1 && count($two['results']) === 1, 'una pareja se identifica por dominio Y alias (un tenant con dos alias no se mezcla)');
    $svc5->bulk('remove', ['sub.test-redir.example|alias-redir.example']);
    $svc5->bulk('to_alias', ['sub.test-redir.example|alias-redir.example']);
    $res = $svc5->bulk('remove', ['sub.test-redir.example|alias-redir.example']);
    ok($res['ok'] === 1 && count($caddy5->routes) === 2, 'bulk remove quita la redirección');
    $res = $svc5->bulk('remove', ['sub.test-redir.example|alias-redir.example']);
    ok($res['failed'] === 1, 'bulk remove sobre una pareja sin redirección da fallo, no error');
    ok($svc5->bulk('borrar-todo', ['sub.test-redir.example|alias-redir.example'])['failed'] === 1, 'una acción desconocida no hace nada');

    // quitar solo lo que es un movimiento de SEO
    ok($svc->remove('musedock.com')['ok'] === false, 'no se puede quitar una redirección cualquiera');
} finally {
    $pdo->rollBack();
}
echo $fails ? "RESULTADO: $fails comprobaciones FALLAN\n" : "RESULTADO: las comprobaciones pasan\n";
exit($fails ? 1 : 0);
