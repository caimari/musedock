<?php
// EdgeGuard: un tenant, alias o redirección recién creados se detectan como «nombre nuevo» para leer Cloudflare enseguida.
// Usa una base SQLite en memoria: no toca la base de datos real ni llama a Cloudflare.
require __DIR__ . '/../../core/bootstrap.php';

use Screenart\Musedock\Services\EdgeGuard\EdgeGuard;

$f = 0;
$check = function (string $name, bool $ok) use (&$f) {
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $f++;
    }
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach (['tenants', 'domain_aliases', 'domain_redirects'] as $t) {
    $pdo->exec("CREATE TABLE $t (id INTEGER PRIMARY KEY AUTOINCREMENT, domain TEXT, include_www INTEGER DEFAULT 0)");
}
$guard = new EdgeGuard($pdo);
$seen = fn () => $guard->setSetting('hosts_seen', json_encode($guard->cmsHostnames()));

$check('sin lectura previa no hay «nombres nuevos» (la periódica lo deja anotado)', $guard->newHostnames() === []);

$pdo->exec("INSERT INTO tenants (domain, include_www) VALUES ('viejo.example.org', 1)");
$seen();
$check('tras una lectura no queda nada nuevo', $guard->newHostnames() === []);

$pdo->exec("INSERT INTO tenants (domain, include_www) VALUES ('aca.example.org', 0)");
$check('un tenant nuevo aparece', $guard->newHostnames() === ['aca.example.org']);

$pdo->exec("INSERT INTO domain_aliases (domain, include_www) VALUES ('alias.example.net', 1)");
$new = $guard->newHostnames();
sort($new);
$check('un alias nuevo aparece con su www', $new === ['aca.example.org', 'alias.example.net', 'www.alias.example.net']);

$pdo->exec("INSERT INTO domain_redirects (domain, include_www) VALUES ('antiguo.example.com', 0)");
$check('una redirección nueva aparece', in_array('antiguo.example.com', $guard->newHostnames(), true));

$seen();
$check('después de leer Cloudflare vuelve a no haber nada nuevo', $guard->newHostnames() === []);

$pdo->exec("DELETE FROM tenants WHERE domain = 'viejo.example.org'");
$check('quitar un dominio no pide lectura', $guard->newHostnames() === []);

echo $f === 0 ? "\nTodo bien\n" : "\n$f fallo(s)\n";
exit($f === 0 ? 0 : 1);
