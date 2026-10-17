<?php
require __DIR__ . '/../../modules/search-console/services/GeoService.php';
use Modules\SearchConsole\Services\GeoService;
$f = 0; $t = function ($ok, $m) use (&$f) { echo ($ok ? 'OK   ' : 'FAIL ') . $m . "\n"; if (!$ok) $f++; };
$tot = ['clicks' => 50, 'impressions' => 3100];
$r = GeoService::concentration([['name' => 'vnm', 'clicks' => 1, 'impressions' => 1158], ['name' => 'esp', 'clicks' => 40, 'impressions' => 900]], $tot);
$t(count($r) === 1 && $r[0]['country'] === 'vnm', 'marca el país con mucho volumen y casi sin clics');
$t(GeoService::concentration([['name' => 'esp', 'clicks' => 40, 'impressions' => 900]], $tot) === [], 'un país sano no se marca');
$t(GeoService::concentration([['name' => 'xx', 'clicks' => 0, 'impressions' => 50]], $tot) === [], 'poco volumen no se marca');
$t(GeoService::concentration([['name' => 'xx', 'clicks' => 0, 'impressions' => 500]], ['clicks' => 0, 'impressions' => 3000]) === [], 'sin clics en ningún sitio no se marca');
$small = [['name' => 'vnm', 'clicks' => 0, 'impressions' => 60]];
$t(GeoService::concentration($small, ['clicks' => 30, 'impressions' => 400]) === [], 'por defecto, un dominio pequeño no salta');
$t(count(GeoService::concentration($small, ['clicks' => 30, 'impressions' => 400], ['min_impressions' => 50, 'min_share' => 0.1])) === 1, 'con umbrales propios sí salta');
exit($f ? 1 : 0);
