<?php
/**
 * Guardia de rutas sensibles de public/index.php: los sondeos de escáneres reciben 404 inmediato y las rutas
 * legítimas (páginas, sitemap, robots, .well-known, claves públicas de IndexNow…) NO se ven afectadas.
 *   php tests/core/sensitive_paths_test.php
 */
$src = file_get_contents(dirname(__DIR__, 2) . '/public/index.php');
if (!preg_match("/\\\$probe = (.*?);\n    if \\(preg_match/s", $src, $m)) { exit("no se encontró la guardia en public/index.php\n"); }
eval('$probe = ' . $m[1] . ';');

$fails = 0;
function ok(bool $c, string $m): void { global $fails; if (!$c) { $fails++; echo "  FALLA: $m\n"; } }
$hit = fn (string $p) => (bool) preg_match($probe, strtolower(rawurldecode($p)));

foreach (['/.env', '/.env.production', '/.git/config', '/.git/HEAD', '/.aws/credentials', '/.ssh/id_rsa', '/.htpasswd', '/config/secrets/prod/sendgrid_keys.json', '/config/secrets/dev/sendgrid_keys%2ejson',
    '/prod.tfvars', '/terraform.tfstate', '/backup.sql', '/db.sqlite3', '/site.bak', '/server.pem', '/wp-login.php', '/xmlrpc.php', '/wp-admin/setup-config.php', '/wp-content/plugins/x',
    '/phpinfo.php', '/phpmyadmin/index.php', '/adminer.php', '/cgi-bin/test', '/composer.json', '/composer.lock', '/vendor/phpunit/phpunit/src/Util/PHP/eval-stdin.php', '/credentials.json', '/aws_secrets.yml',
    '/%2eenv', '/app/.env', '/api/.git/config'] as $p) {
    ok($hit($p), "sondeo bloqueado: $p");
}
foreach (['/', '/blog', '/blog/mi-articulo-sobre-env', '/docs/search-console-musedock-cms', '/en/docs/search-console-guide', '/sitemap.xml', '/robots.txt', '/favicon.ico', '/media/p/foto-abc/png',
    '/assets/css/app.css', '/assets/js/app.min.js', '/63aa08b4691d3bee2fdfef452dea521a.txt', '/p/privacy', '/musedock/login', '/admin/login', '/api/webhook/stripe', '/themes/default/img/logo.webp',
    '/contacto', '/politica-de-cookies', '/mejor-ia-para-programar-en-2026', '/secretos-del-sql-para-principiantes', '/wordpress-vs-cms', '/.well-known/acme-challenge/abc'] as $p) {
    ok(!$hit($p), "ruta legítima NO bloqueada: $p");
}
echo $fails ? "RESULTADO: $fails comprobaciones FALLAN\n" : "RESULTADO: las comprobaciones pasan\n";
exit($fails ? 1 : 0);
