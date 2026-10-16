<?php
/**
 * «Esta semana merece tu tiempo»: solo prioridad alta, impacto comparable, tope por dominio. Detector simulado.
 *   php tests/search-console/priority_test.php
 */
define('APP_ROOT', dirname(__DIR__, 2));
foreach (['PropertyService', 'UrlNormalizer', 'ReportService', 'OpportunityService', 'DropAlertService', 'PriorityService'] as $c) {
    require_once APP_ROOT . "/modules/search-console/services/{$c}.php";
}

use Modules\SearchConsole\Services\{DropAlertService, OpportunityService, PriorityService};

$fails = 0;
function ok(bool $c, string $m): void { global $fails; if (!$c) { $fails++; echo "  FALLA: $m\n"; } }

$pdo = new PDO('sqlite::memory:');
$mk = fn (string $type, string $tier, float $score, int $imp = 100, string $kind = 'page', string $label = '/x') => ['type' => $type, 'tier' => $tier, 'score' => $score, 'impressions' => $imp, 'kind' => $kind, 'label' => $label, 'position' => 8.0, 'detail' => '5 → 1 clics'];
$items = [
    1 => [$mk('low_ctr', 'high', 40.0, 900, 'page', '/a'), $mk('striking', 'high', 30.0, 500, 'page', '/b'), $mk('striking', 'high', 20.0, 400, 'page', '/c'), $mk('low_ctr', 'medium', 90.0), $mk('rising', 'high', 99.0), $mk('far', 'explore', 80.0)],
    2 => [$mk('far', 'high', 668.0, 668, 'page', '/lejos')],
    3 => [$mk('drop', 'high', 35.0, 300, 'query', 'marca'), array_merge($mk('far', 'high', 500.0, 900, 'query', 'tres'), ['brand' => true])],
];
$opp = new class($pdo, $items) extends OpportunityService {
    public function __construct(PDO $pdo, private array $items) { parent::__construct($pdo); }
    public function detect(array $property, int $days): array { return ['empty' => false, 'items' => $this->items[$property['id']] ?? []]; }
};
$drops = new class($pdo) extends DropAlertService {
    public function __construct(PDO $pdo) { parent::__construct($pdo, sys_get_temp_dir() . '/x.json'); }
    public function dropsFor(array $property): array
    {
        return $property['id'] === 4 ? [['impact' => 100.0, 'domain' => $property['domain'], 'key' => 'k', 'text' => 'Los clics bajan de 300 a 100.']] : [];
    }
};
$svc = new PriorityService($opp, $drops);
$props = [
    ['id' => 1, 'domain' => 'uno.test', 'status' => 'active', 'page_filter' => ''],
    ['id' => 2, 'domain' => 'dos.test', 'status' => 'active', 'page_filter' => ''],
    ['id' => 3, 'domain' => 'tres.test', 'status' => 'active', 'page_filter' => ''],
    ['id' => 4, 'domain' => 'cuatro.test', 'status' => 'active', 'page_filter' => ''],
    ['id' => 5, 'domain' => 'virtual.test', 'status' => 'active', 'page_filter' => 'https://v.test/'],
    ['id' => 1, 'domain' => 'archivada.test', 'status' => 'archived', 'page_filter' => ''],
];
$top = $svc->top($props, 10);
$domains = array_column($top, 'domain');
ok($domains[0] === 'cuatro.test', 'la caída de visibilidad del sitio (100 clics) encabeza la lista');
ok(!in_array('virtual.test', $domains, true) && !in_array('archivada.test', $domains, true), 'ni los subdominios virtuales ni los dominios dados de baja entran');
ok(count(array_filter($top, fn ($t) => $t['domain'] === 'uno.test')) === PriorityService::PER_DOMAIN, 'un dominio no ocupa más de ' . PriorityService::PER_DOMAIN . ' puestos');
ok(!array_filter($top, fn ($t) => $t['kind'] === 'rising'), 'las subidas no son «lo que merece tu tiempo»');
$uno = array_values(array_filter($top, fn ($t) => $t['domain'] === 'uno.test'));
ok(abs($uno[0]['impact'] - 40.0) < 0.01 && abs($uno[1]['impact'] - 30.0) < 0.01, 'solo prioridad alta: las de prioridad media y exploración no cuentan');
$dos = array_values(array_filter($top, fn ($t) => $t['domain'] === 'dos.test'))[0];
ok(abs($dos['impact'] - 6.68) < 0.01, 'una oportunidad «lejos» (668 impresiones) se convierte a clics estimados (≈ 6,7), no compite con 668');
ok($top === array_values($top) && array_column($top, 'impact') === (function ($a) { rsort($a); return $a; })(array_column($top, 'impact')), 'ordenadas de más a menos impacto');
ok(!array_filter($top, fn ($t) => $t['domain'] === 'tres.test' && $t['kind'] === 'far'), 'una búsqueda de marca no es «oportunidad» (salir arriba es lo normal), salvo que caiga');
ok(count($svc->top($props, 2)) === 2, 'respeta el límite pedido');
ok($svc->top([], 5) === [], 'sin dominios, lista vacía');

echo $fails ? "RESULTADO: $fails comprobaciones FALLAN\n" : "RESULTADO: las comprobaciones pasan\n";
exit($fails ? 1 : 0);
