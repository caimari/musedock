<?php
require __DIR__ . '/../../core/Services/BotClassifier.php';
require __DIR__ . '/../../core/Services/WebAnalytics.php';
use Screenart\Musedock\Services\WebAnalytics as W;
$f = 0;
$t = function ($ok, $m) use (&$f) { echo ($ok ? 'OK   ' : 'FAIL ') . $m . "\n"; if (!$ok) $f++; };
$call = function (string $m, ...$a) { $r = new ReflectionMethod(W::class, $m); $r->setAccessible(true); return $r->invoke(null, ...$a); };

$_COOKIE = [];
$t($call('hasUserConsent') === false, 'sin banner aceptado no hay consentimiento');
$_COOKIE = ['musedock_cookies_accepted' => 'true'];
$t($call('hasUserConsent') === false, 'banner aceptado pero sin analítica: no se registra');
$_COOKIE = ['musedock_cookies_accepted' => 'true', 'musedock_cookie_analytics' => 'false'];
$t($call('hasUserConsent') === false, 'analítica rechazada: no se registra');
$_COOKIE = ['musedock_cookies_accepted' => 'true', 'musedock_cookie_analytics' => 'true'];
$t($call('hasUserConsent') === true, 'con analítica aceptada sí');

$r = $call('analyzeReferrer', 'https://www.agent8ai.com/blog', 'agent8ai.com');
$t($r['type'] === 'internal', 'venir del propio sitio (con o sin www) es navegación interna');
$r = $call('analyzeReferrer', 'https://otro.example/x', 'agent8ai.com');
$t($r['type'] === 'referral', 'otro sitio sigue siendo referral');
$r = $call('analyzeReferrer', 'https://www.google.com/', 'agent8ai.com');
$t($r['type'] === 'search', 'buscador sigue siendo search');
exit($f ? 1 : 0);
