<?php
require __DIR__ . '/../../core/Middleware/AnalyticsMiddleware.php';
// El constructor no hace nada; probamos solo la lista de exclusiones
$m = new Screenart\Musedock\Middleware\AnalyticsMiddleware();
$r = new ReflectionMethod($m, 'shouldExcludePath'); $r->setAccessible(true);
$f = 0;
foreach (['/%2f%2eenv' => true, '/%2eenv' => true, '/%2eaws%2fcredentials' => true, '/aws/buckets' => true, '/.env' => true, '/wp-login.php' => true, '/' => false, '/blog/mi-articulo' => false, '/festival/aws-awards' => false] as $path => $expect) {
    $got = $r->invoke($m, $path);
    echo ($got === $expect ? 'OK   ' : 'FAIL ') . "$path → " . var_export($got, true) . "\n";
    if ($got !== $expect) $f++;
}
exit($f ? 1 : 0);
