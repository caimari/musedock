<?php
/**
 * Comprueba que cada clase usada con `new X` o `X::` en los controladores y servicios del módulo se resuelve
 * (por `use`, por el namespace o por ser global). Detecta un `use` olvidado antes de que dé un 500.
 *   php tests/search-console/imports_test.php
 */
define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';
spl_autoload_register(function ($c) {
    $map = ['Modules\\SearchConsole\\Controllers\\' => 'controllers', 'Modules\\SearchConsole\\Services\\' => 'services'];
    $f = null;
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($c, $prefix)) { $f = APP_ROOT . "/modules/search-console/{$dir}/" . str_replace('\\', '/', substr($c, strlen($prefix))) . '.php'; }
    }
    if ($f === null) { return; }
    if (is_file($f)) { require_once $f; }
});

$files = array_merge(glob(APP_ROOT . '/modules/search-console/controllers/*.php'), glob(APP_ROOT . '/modules/search-console/controllers/*/*.php'), glob(APP_ROOT . '/modules/search-console/services/*.php'));
$bad = [];
$checked = 0;
foreach ($files as $file) {
    $tokens = token_get_all(file_get_contents($file));
    $ns = '';
    $uses = [];
    $n = count($tokens);
    $name = function (int $i) use ($tokens) { // lee un nombre (con o sin namespace) a partir de $i
        $s = '';
        while (isset($tokens[$i]) && is_array($tokens[$i]) && in_array($tokens[$i][0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR], true)) { $s .= $tokens[$i][1]; $i++; }
        return $s;
    };
    $refs = [];
    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];
        if (!is_array($t)) { continue; }
        if ($t[0] === T_NAMESPACE) { $ns = $name($i + 2); }
        if ($t[0] === T_USE && ($tokens[$i + 2][0] ?? null) !== T_FUNCTION && $i < 80) { $u = $name($i + 2); if ($u !== '') { $uses[substr($u, (int) strrpos('\\' . $u, '\\'))] = ltrim($u, '\\'); } }
        if ($t[0] === T_NEW) { $j = $i + 1; while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) { $j++; } $nm = $name($j); if ($nm !== '') { $refs[$nm] = $t[2]; } }
        if ($t[0] === T_DOUBLE_COLON) { $j = $i - 1; if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) { $refs[$tokens[$j][1]] = $t[2]; } }
    }
    foreach ($refs as $r => $line) {
        if (in_array(strtolower($r), ['self', 'static', 'parent', 'class'], true) || $r[0] === '$') { continue; }
        $checked++;
        $first = explode('\\', ltrim($r, '\\'))[0];
        $candidates = $r[0] === '\\' ? [ltrim($r, '\\')] : (isset($uses[$first]) ? [$uses[$first] . substr($r, strlen($first))] : [($ns ? $ns . '\\' : '') . $r]);
        $found = false;
        foreach ($candidates as $c) { if (class_exists($c) || interface_exists($c) || enum_exists($c)) { $found = true; } }
        if (!$found) { $bad[] = basename(dirname($file)) . '/' . basename($file) . ":$line  $r"; }
    }
}
if ($bad) { echo "FALLA: clases que no se resuelven:\n  " . implode("\n  ", $bad) . "\n"; exit(1); }
echo "RESULTADO: las $checked referencias a clases de " . count($files) . " ficheros se resuelven\n";
