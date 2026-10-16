<?php
/**
 * Informe de SEO de un dominio (Search Console). Variables: $property, $report, $periods, $baseUrl.
 * Plantilla compartida por la vista de superadmin y la de tenant.
 */
$e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$n = fn ($v) => number_format((int) $v, 0, ',', '.');
$pct = fn ($v) => number_format($v * 100, 1, ',', '.') . ' %';
$pos = fn ($v) => $v === null ? '—' : number_format($v, 1, ',', '.');
$delta = function ($now, $before, $lowerIsBetter = false) {
    if (!$before) {
        return '';
    }
    $d = ($now - $before) / $before * 100;
    $good = $lowerIsBetter ? $d <= 0 : $d >= 0;

    return '<span class="' . ($good ? 'gsc-up' : 'gsc-down') . '">' . ($d >= 0 ? '+' : '') . number_format($d, 0, ',', '.') . ' %</span> <span class="text-muted">vs. anterior</span>';
};
$empty = $report['empty'];
$csv = fn (string $type) => $baseUrl . '/export?type=' . $type . '&property_id=' . (int) $property['id'] . '&days=' . (int) ($report['period'] ?? 28);
$csvLink = fn (string $type) => '<a class="btn btn-link btn-sm py-0 float-end" style="font-size:.8rem;" href="' . $e($csv($type)) . '" title="Descargar esta tabla en CSV (Excel)"><i class="bi bi-download"></i> CSV</a>';
// Las páginas son URL reales y se abren en otra pestaña; las consultas son palabras y no llevan enlace.
$pageLink = fn ($url, $label) => preg_match('#^https?://[^\s"<>]+$#i', (string) $url)
    ? '<a href="' . $e($url) . '" target="_blank" rel="noopener noreferrer" title="Abrir ' . $e($url) . '">' . $e($label) . ' <i class="bi bi-box-arrow-up-right" style="font-size:.7em;opacity:.6;"></i></a>'
    : $e($label);
?>
<style>
.gsc-page .card { border:1px solid #e5eaf1;border-radius:12px;box-shadow:0 3px 14px rgba(30,50,80,.035); }
.gsc-page .num { text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap; }
.gsc-page .table td,.gsc-page .table th { padding:9px 12px;vertical-align:middle; }
.gsc-up { color:#1e7e34; } .gsc-down { color:#c62828; }
.gsc-kpi { padding:1rem 1.1rem; } .gsc-kpi .v { font-size:1.55rem;font-weight:700;line-height:1.1; } .gsc-kpi .l { font-size:.8rem;color:#6c757d;text-transform:uppercase;letter-spacing:.04em; }
.gsc-chart svg { width:100%;height:240px;display:block; }
.gsc-trunc { max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
table.gsc-sortable thead th { cursor:pointer;user-select:none;white-space:nowrap; }
table.gsc-sortable thead th:hover { background:rgba(26,115,232,.06); }
table.gsc-sortable thead th.gsc-asc::after { content:" ▲";font-size:.65em;color:#1a73e8; }
table.gsc-sortable thead th.gsc-desc::after { content:" ▼";font-size:.65em;color:#1a73e8; }
.gsc-fit { table-layout:auto;width:100%; }
.gsc-fit td.gsc-trunc { max-width:0;width:100%; }
.gsc-fit th.num,.gsc-fit td.num { width:1%;padding-left:8px;padding-right:12px; }
.gsc-fit thead th { font-size:.72rem;letter-spacing:.04em; }
.gsc-trunc a { color:inherit;text-decoration:none; } .gsc-trunc a:hover { color:#1a73e8;text-decoration:underline; }
</style>

<div class="app-content gsc-page">
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <a href="<?= $e($baseUrl) ?>" class="text-muted" style="font-size:.85rem;"><i class="bi bi-arrow-left"></i> Todos los dominios</a>
            <h3 class="mb-0 mt-1" style="font-size:1.35rem;font-weight:700;"><i class="bi bi-graph-up-arrow me-2"></i><?= $e($property['domain']) ?></h3>
            <div class="text-muted" style="font-size:.82rem;">
                <?= $e($property['site_url']) ?> ·
                <?php if (!empty($property['last_sync_at'])): ?>datos descargados el <?= $e(date('d/m/Y H:i', strtotime($property['last_sync_at']))) ?><?php else: ?>aún sin descargar<?php endif; ?>
                <?php if (!$empty): ?> · hasta el <?= $e(date('d/m/Y', strtotime($report['to']))) ?> (Google publica los datos con ~2 días de retraso)<?php endif; ?>
            </div>
            <?php if (($property['last_sync_status'] ?? '') === 'error'): ?>
                <div class="text-danger" style="font-size:.82rem;"><i class="bi bi-exclamation-triangle"></i> Último intento fallido: <?= $e($property['last_error']) ?></div>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <?php if (!$empty): ?>
            <select class="form-select form-select-sm" style="width:auto;" onchange="location.href='?days='+this.value" aria-label="Periodo">
                <?php foreach ($periods as $d): ?><option value="<?= (int) $d ?>" <?= ($report['period'] ?? $report['days']) === $d ? 'selected' : '' ?>><?= $e(\Modules\SearchConsole\Services\ReportService::periodLabel((int) $d)) ?></option><?php endforeach; ?>
            </select>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="gsc-glossary"><i class="bi bi-question-circle"></i> ¿Qué significan los datos?</button>
            <?php if (($property['status'] ?? '') === 'active'): ?>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="gsc-inspect-open" title="Ver si Google tiene indexada una URL, cuándo la rastreó y qué canonical eligió"><i class="bi bi-search"></i> Inspeccionar URL</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="gsc-sitemap" data-property="<?= (int) $property['id'] ?>" title="Avisa a Google del sitemap de este dominio para que lo rastree antes"><i class="bi bi-diagram-3"></i> Enviar sitemap</button>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-primary btn-sm" id="gsc-sync" data-property="<?= (int) $property['id'] ?>"><i class="bi bi-cloud-arrow-down"></i> Descargar datos ahora</button>
        </div>
    </div>

<?php if ($empty): ?>
    <div class="card"><div class="card-body text-center p-5">
        <i class="bi bi-hourglass-split" style="font-size:2rem;color:#8a97a8;"></i>
        <?php if (($property['last_sync_status'] ?? '') === 'ok'): ?>
        <h5 class="mt-3">Google aún no registra búsquedas para este dominio</h5>
        <p class="text-muted mb-3">La descarga se hizo bien (<?= $e(date('d/m/Y H:i', strtotime((string) $property['last_sync_at']))) ?>), pero Google no ha mostrado este sitio en ninguna búsqueda en el periodo descargado. Es normal en sitios nuevos o con poco contenido indexado. Los datos aparecerán solos en cuanto haya impresiones.</p>
        <button type="button" class="btn btn-outline-primary" id="gsc-sync2"><i class="bi bi-cloud-arrow-down"></i> Volver a descargar</button>
        <?php else: ?>
        <h5 class="mt-3">Todavía no hay datos de este dominio</h5>
        <p class="text-muted mb-3">La descarga automática corre sola en segundo plano (cada pocas horas). También puedes pedirla ahora; la primera vez baja hasta 16 meses de histórico y puede tardar uno o dos minutos.
        Si el dominio es nuevo en Search Console, Google puede tardar unos días en tener datos.</p>
        <button type="button" class="btn btn-primary" id="gsc-sync2"><i class="bi bi-cloud-arrow-down"></i> Descargar ahora</button>
        <?php endif; ?>
    </div></div>
<?php else:
    $t = $report['totals'];
    $p = $report['previous'];
?>
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><div class="card gsc-kpi"><div class="l">Clics</div><div class="v"><?= $n($t['clicks']) ?></div><div style="font-size:.78rem;"><?= $delta($t['clicks'], $p['clicks']) ?></div></div></div>
        <div class="col-6 col-lg-3"><div class="card gsc-kpi"><div class="l">Impresiones</div><div class="v"><?= $n($t['impressions']) ?></div><div style="font-size:.78rem;"><?= $delta($t['impressions'], $p['impressions']) ?></div></div></div>
        <div class="col-6 col-lg-3"><div class="card gsc-kpi"><div class="l">CTR</div><div class="v"><?= $pct($t['ctr']) ?></div><div style="font-size:.78rem;"><?= $delta($t['ctr'], $p['ctr']) ?></div></div></div>
        <div class="col-6 col-lg-3"><div class="card gsc-kpi"><div class="l">Posición media</div><div class="v"><?= $pos($t['position']) ?></div><div style="font-size:.78rem;"><?= $t['position'] !== null && $p['position'] ? $delta($t['position'], $p['position'], true) : '' ?></div></div></div>
    </div>

    <?php if (!empty($brand) && ($brand['brand']['impressions'] + $brand['other']['impressions']) > 0): $bt = $brand['brand']['impressions'] + $brand['other']['impressions']; ?>
    <div class="card mb-3"><div class="card-body py-2 d-flex flex-wrap gap-3 align-items-center" style="font-size:.85rem;">
        <span><i class="bi bi-person-badge"></i> <strong>Búsquedas de marca</strong> (<?= $e(implode(', ', $brand['tokens'])) ?>): <?= $n($brand['brand']['clicks']) ?> clics · <?= $n($brand['brand']['impressions']) ?> impr. (<?= number_format($brand['brand_share'] * 100, 0, ',', '.') ?> %)</span>
        <span><i class="bi bi-search"></i> <strong>Sin marca</strong> (te descubren): <?= $n($brand['other']['clicks']) ?> clics · <?= $n($brand['other']['impressions']) ?> impr. (<?= number_format((1 - $brand['brand_share']) * 100, 0, ',', '.') ?> %)</span>
        <span class="text-muted" style="font-size:.75rem;" title="Google oculta las consultas poco frecuentes por privacidad: cuentan en el total pero no aparecen en la lista, así que no se pueden clasificar. Una búsqueda es «de marca» si contiene el nombre del sitio (no palabras genéricas como «film» o «spain»).">Clasificación basada en <?= $n($brand['classified']) ?> de <?= $n($brand['site_impressions']) ?> impresiones (<?= number_format(($brand['coverage'] ?? 0) * 100, 0, ',', '.') ?> %): el resto no tiene consulta visible y no está clasificado.</span>
    </div></div>
    <?php endif; ?>
    <div class="card mb-3"><div class="card-body gsc-chart">
        <div class="d-flex gap-3 mb-2" style="font-size:.82rem;"><span><span style="display:inline-block;width:10px;height:10px;background:#1a73e8;border-radius:2px;"></span> Clics</span><span><span style="display:inline-block;width:10px;height:10px;background:#8a56c5;border-radius:2px;"></span> Impresiones</span></div>
        <div class="text-end"><?= $csvLink('daily') ?></div>
        <div id="gsc-chart"></div>
    </div></div>

<?php if (isset($opportunities) && empty($opportunities['empty'])):
    $o = $opportunities;
    $oppTypes = \Modules\SearchConsole\Services\OpportunityService::TYPES;
    $oppHelp = [
        'low_ctr' => 'Apareces alto (posición 1–10) pero con menos de la mitad de los clics que es normal en esa posición.',
        'striking' => 'Posición 4–20: estás en las dos primeras páginas de Google pero fuera del top 3.',
        'far' => 'Posición por encima de 20 con impresiones: hay gente que lo busca y estás muy lejos.',
        'drop' => 'Menos clics (o menos de la mitad de impresiones) que en el periodo anterior.',
        'rising' => 'Más del doble de impresiones que antes, o aparece por primera vez entre las 20 primeras posiciones.',
    ];
?>
    <div class="card mb-3" id="gsc-opps">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div><strong><i class="bi bi-lightbulb"></i> Oportunidades SEO</strong>
                <span class="text-muted" style="font-size:.8rem;">detectadas con los datos de los últimos <?= (int) $o['days'] ?> días · se ignora lo que tiene menos de <?= (int) $o['min_impressions'] ?> impresiones</span></div>
            <button type="button" class="btn btn-link btn-sm p-0" id="gsc-opps-help"><i class="bi bi-question-circle"></i> ¿Cómo se calculan?</button>
        </div>
        <div class="card-body">
        <?php if (!($o['cards'] ?? $o['items'])): ?>
            <div class="text-center text-muted p-3"><i class="bi bi-hourglass-split"></i> Con tan pocos datos todavía no hay señales claras. Las oportunidades irán apareciendo a medida que el dominio reciba más búsquedas en Google.</div>
        <?php else: ?>
            <div class="d-flex flex-wrap gap-2 mb-3" id="gsc-opps-chips">
                <button type="button" class="btn btn-sm btn-dark" data-type="">Todas <span class="badge bg-light text-dark"><?= count($o['cards']) ?></span></button>
                <?php foreach ($oppTypes as $key => [$label, $color]): if (empty($o['card_counts'][$key])) { continue; } ?>
                    <button type="button" class="btn btn-sm btn-outline-<?= $e($color) ?>" data-type="<?= $e($key) ?>"><?= $e($label) ?> <span class="badge bg-<?= $e($color) ?>"><?= (int) $o['card_counts'][$key] ?></span></button>
                <?php endforeach; ?>
                <?php if (!empty($o['tiers']['explore']) || !empty($o['tiers']['archive'])): $hidden = (int) ($o['tiers']['explore'] ?? 0) + (int) ($o['tiers']['archive'] ?? 0); ?>
                <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="gsc-opps-explore" title="Exploración: pocas impresiones. Archivo: páginas de ediciones pasadas"><i class="bi bi-eye"></i> Mostrar exploratorias y de archivo (<?= $hidden ?>)</button>
                <?php endif; ?>
            </div>
            <div class="table-responsive"><table class="table table-sm mb-0 gsc-sortable" id="gsc-opps-table"><thead><tr><th>Oportunidad</th><th>URL / palabra clave</th><th class="num">Impr.</th><th class="num">Clics</th><th class="num">Pos.</th><th>Qué puedes ganar y qué hacer</th></tr></thead><tbody>
            <?php foreach ($o['cards'] as $it): [$tl, $tc] = $oppTypes[$it['type']]; ?>
                <?php $tierLabel = ['high' => ['Prioridad alta', 'danger'], 'medium' => ['Prioridad media', 'warning'], 'explore' => ['Exploración', 'secondary'], 'archive' => ['Archivo', 'secondary']][$it['tier']]; ?>
                <tr data-type="<?= $e(implode(' ', array_unique($it['types']))) ?>" data-tier="<?= $e($it['tier']) ?>" <?= in_array($it['tier'], ['explore', 'archive'], true) ? 'style="display:none;"' : '' ?>>
                    <td style="white-space:nowrap;"><span class="badge bg-<?= $e($tc) ?>"><?= $e($tl) ?></span><div style="margin-top:3px;"><span class="badge bg-<?= $e($tierLabel[1]) ?> <?= $tierLabel[1] === 'warning' ? 'text-dark' : '' ?>" style="font-size:.62rem;" title="<?= $it['tier'] === 'archive' ? 'Página de una edición pasada: mantenimiento y archivo, sin urgencia SEO' : ($it['tier'] === 'explore' ? 'Pocas impresiones: mucha incertidumbre, no actúes sin más datos' : ($it['tier'] === 'high' ? 'Mucho peso en el sitio o caída de clics confirmada' : 'Volumen razonable')) ?>"><?= $e($tierLabel[0]) ?></span><?php if (($it['context'] ?? null) === 'current'): ?> <span class="badge bg-success" style="font-size:.62rem;" title="Edición vigente (<?= (int) $it['year'] ?>)">vigente</span><?php endif; ?></div></td>
                    <td class="gsc-trunc" style="max-width:260px;" title="<?= $e($it['name']) ?>">
                        <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;"><?= $it['kind'] === 'page' ? 'URL' : 'Palabra clave' ?></div>
                        <strong><?= $it['kind'] === 'page' ? $pageLink($it['name'], $it['label']) : $e($it['label']) ?></strong><?php if (!empty($it['brand'])): ?> <span class="badge bg-light text-dark border" style="font-size:.62rem;" title="Búsqueda de marca: contiene el nombre del sitio">marca</span><?php endif; ?></td>
                    <td class="num"><?= $n($it['impressions']) ?></td><td class="num"><?= $n($it['clicks']) ?></td><td class="num"><?= $pos($it['position']) ?></td>
                    <td style="font-size:.82rem;min-width:240px;max-width:460px;"><strong><?= $e($it['detail']) ?></strong><div class="text-muted"><?= $e($it['advice']) ?></div>
                        <?php if (!empty($it['reasons'])): ?><div class="mt-1" style="font-size:.74rem;"><span class="text-muted">También:</span> <?php foreach ($it['reasons'] as $rs): [, $rc] = $oppTypes[$rs['type']]; ?><span class="badge bg-<?= $e($rc) ?> bg-opacity-75" style="font-size:.62rem;"><?= $e($rs['label']) ?></span> <span class="text-muted"><?= $e($rs['detail']) ?></span>; <?php endforeach; ?></div><?php endif; ?>
                        <?php if ($it['kind'] === 'page' && ($property['status'] ?? '') === 'active'): ?><div><button type="button" class="btn btn-link btn-sm p-0 gsc-opt-add" style="font-size:.74rem;" data-url="<?= $e($it['name']) ?>"><i class="bi bi-journal-check"></i> Registrar optimización</button></div><?php endif; ?>
                        <?php if (!empty($editorial['suggestions'][$it['name']])): $sg = $editorial['suggestions'][$it['name']]; ?>
                        <details class="mt-1"><summary class="text-primary" style="cursor:pointer;font-size:.78rem;"><i class="bi bi-lightbulb"></i> Sugerencias (<?= count($sg) ?>)</summary>
                            <ul class="mb-1 mt-1 ps-3" style="font-size:.78rem;"><?php foreach ($sg as $line): ?><li><?= $e($line) ?></li><?php endforeach; ?></ul>
                            <div class="text-muted" style="font-size:.7rem;">Sugerencias por reglas, no por IA: revísalas antes de aplicarlas. Los textos de Google se muestran tal cual, como datos.</div></details>
                        <?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
        <?php foreach ($o['notes'] as $note): ?><div class="text-muted mt-2" style="font-size:.78rem;"><i class="bi bi-info-circle"></i> <?= $e($note) ?></div><?php endforeach; ?>
        </div>
    </div>
    <?php if (!empty($editorial['cannibal'])): ?>
    <div class="card mb-3" id="gsc-cannibal">
        <div class="card-header"><strong><i class="bi bi-intersect"></i> Páginas que compiten entre sí</strong>
            <span class="text-muted" style="font-size:.8rem;">varias páginas de este sitio aparecen para la misma búsqueda (últimos 28 días)</span></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>Búsqueda</th><th class="num">Impr.</th><th>Páginas (posición)</th><th>Qué hacer</th></tr></thead><tbody>
            <?php foreach ($editorial['cannibal'] as $cn): ?>
                <tr><td style="max-width:200px;"><strong><?= $e($cn['query']) ?></strong><?php if (!empty($cn['brand'])): ?> <span class="badge bg-light text-dark border" style="font-size:.62rem;">marca</span><?php endif; ?></td><td class="num"><?= $n($cn['impressions']) ?></td>
                    <td style="font-size:.82rem;"><?php foreach ($cn['pages'] as $pg): ?><div><?= $pageLink($pg['url'], $pg['label']) ?> <span class="text-muted">· pos. <?= $pos($pg['position']) ?> · <?= $n($pg['impressions']) ?> impr.</span></div><?php endforeach; ?></td>
                    <td class="text-muted" style="font-size:.78rem;min-width:240px;max-width:380px;"><?= $e($cn['advice']) ?></td></tr>
            <?php endforeach; ?>
        </tbody></table></div></div>
    </div>
    <?php endif; ?>
    <?php if (isset($optimizations) && ($property['status'] ?? '') === 'active'): ?>
    <div class="card mb-3" id="gsc-optimizations">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div><strong><i class="bi bi-journal-check"></i> Seguimiento de optimizaciones</strong>
                <span class="text-muted" style="font-size:.8rem;">qué cambiaste, cuándo, y cómo se comportó la página antes y después</span></div>
            <button type="button" class="btn btn-outline-primary btn-sm gsc-opt-add" data-url=""><i class="bi bi-plus-circle"></i> Registrar optimización</button>
        </div>
        <div class="card-body p-0">
        <?php if (!$optimizations): ?>
            <div class="text-muted p-3" style="font-size:.85rem;">Todavía no has registrado ninguna. Cuando mejores una página (título, contenido, enlaces…), regístrala aquí con su fecha: el módulo comparará 28 días antes con 28 días después, y con lo que hizo el sitio entero, para ver si el cambio coincide con una mejora.</div>
        <?php else: ?>
            <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                <thead><tr><th>Fecha</th><th>Página</th><th>Cambio</th><th>Antes → después</th><th>Valoración</th><th></th></tr></thead><tbody>
                <?php foreach ($optimizations as $o): $ev = $o['evaluation'];
                    $vc = ['better' => 'success', 'worse' => 'danger', 'flat' => 'secondary', 'tracking' => 'info', 'few_data' => 'secondary'][$ev['state']] ?? 'secondary'; ?>
                    <tr>
                        <td style="white-space:nowrap;"><?= $e(date('d/m/Y', strtotime($o['done_on']))) ?></td>
                        <td class="gsc-trunc" style="max-width:240px;" title="<?= $e($o['url']) ?>"><?= $pageLink($o['url'], OpportunityService::shortPage($o['url'], (string) $property['domain'])) ?></td>
                        <td style="font-size:.82rem;"><strong><?= $e($optimizationKinds[$o['kind']] ?? $o['kind']) ?></strong><?php if (!empty($o['note'])): ?><div class="text-muted" style="font-size:.75rem;"><?= $e($o['note']) ?></div><?php endif; ?></td>
                        <td style="font-size:.8rem;white-space:nowrap;">
                        <?php if ($ev['before'] && $ev['after']): ?>
                            <div>pos. <?= $pos($ev['before']['position']) ?> → <strong><?= $pos($ev['after']['position']) ?></strong></div>
                            <div class="text-muted"><?= $n($ev['before']['clicks']) ?> → <?= $n($ev['after']['clicks']) ?> clics · <?= $n($ev['before']['impressions']) ?> → <?= $n($ev['after']['impressions']) ?> impr.</div>
                            <div class="text-muted" style="font-size:.7rem;"><?= (int) $ev['days'] ?> días antes vs. <?= (int) $ev['days'] ?> después</div>
                        <?php else: ?>—<?php endif; ?></td>
                        <td style="font-size:.82rem;min-width:220px;max-width:340px;"><span class="badge bg-<?= $e($vc) ?>"><?= $e($ev['verdict']) ?></span><div class="text-muted" style="font-size:.72rem;"><?= $e($ev['note']) ?></div></td>
                        <td><button type="button" class="btn btn-link btn-sm text-danger p-0 gsc-opt-del" data-id="<?= (int) $o['id'] ?>" title="Quitar del seguimiento"><i class="bi bi-trash"></i></button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
        <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <script>
    (function () {
        var chips = document.querySelectorAll('#gsc-opps-chips [data-type]'), rows = document.querySelectorAll('#gsc-opps-table tbody tr'), showExplore = false, curType = '';
        function applyOpps() {
            rows.forEach(function (r) { r.style.display = ((!curType || (' ' + r.dataset.type + ' ').indexOf(' ' + curType + ' ') !== -1) && (showExplore || (r.dataset.tier !== 'explore' && r.dataset.tier !== 'archive'))) ? '' : 'none'; });
        }
        chips.forEach(function (b) {
            b.addEventListener('click', function () {
                chips.forEach(function (x) { x.classList.toggle('btn-dark', x === b && !x.dataset.type); });
                curType = b.dataset.type || '';
                applyOpps();
            });
        });
        var ex = document.getElementById('gsc-opps-explore');
        if (ex) ex.addEventListener('click', function () {
            showExplore = !showExplore;
            ex.innerHTML = showExplore ? '<i class="bi bi-eye-slash"></i> Ocultar exploratorias y de archivo' : '<i class="bi bi-eye"></i> Mostrar exploratorias y de archivo (' + <?= (int) (($o['tiers']['explore'] ?? 0) + ($o['tiers']['archive'] ?? 0)) ?> + ')';
            applyOpps();
        });
        var help = document.getElementById('gsc-opps-help');
        if (help) help.addEventListener('click', function () {
            var items = <?= json_encode(array_map(fn ($k) => [$oppTypes[$k][0], $oppHelp[$k]], array_keys($oppTypes)), JSON_UNESCAPED_UNICODE) ?>;
            var html = '<div style="text-align:left;font-size:.92rem"><p>Son reglas fijas (sin IA) sobre los datos que Google ya te ha dado. Se aplican a consultas y a páginas, y se ordenan por lo que podrías ganar.</p><ul>'
                + items.map(function (i) { return '<li><strong>' + i[0] + '</strong>: ' + i[1] + '</li>'; }).join('')
                + '</ul><p class="mb-1">Cada aviso lleva una <strong>prioridad</strong> según los datos que lo respaldan: <strong>alta</strong> (mucho peso en el sitio o una caída de clics confirmada), <strong>media</strong> y <strong>exploración</strong> (pocas impresiones: se ocultan por defecto y no llevan consejo concreto) y <strong>archivo</strong> (páginas de ediciones pasadas, con un año de hace 2 o más en su URL: mantenimiento sin urgencia SEO). Las de la edición vigente suben de prioridad. Las variantes de una misma página (con/sin www, #ancla, utm_*) se suman, y las páginas legales y de acceso se excluyen. Las cifras «≈ +N clics» son una <strong>estimación</strong>, no una previsión. El mínimo de impresiones se adapta al tamaño del sitio (entre 3 y 50). El CTR «normal» por posición es una referencia típica del sector (28 % en la 1.ª, 15 % en la 2.ª, 11 % en la 3.ª… 2 % en la 10.ª), no un dato de Google.</p></div>';
            if (window.Swal) { Swal.fire({ title: 'Cómo se detectan las oportunidades', html: html, icon: 'info', width: 680, confirmButtonText: 'Entendido' }); }
        });
    })();
    </script>
<?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-6"><div class="card"><div class="card-header"><strong>Consultas</strong> <span class="text-muted" style="font-size:.8rem;">lo que la gente busca en Google</span> <?= $csvLink('queries') ?></div>
            <div class="table-responsive"><table class="table table-sm mb-0 gsc-sortable gsc-fit"><thead><tr><th>Consulta</th><th class="num">Clics</th><th class="num">Impr.</th><th class="num">CTR</th><th class="num">Pos.</th></tr></thead><tbody>
            <?php foreach ($report['queries'] as $r): ?><tr><td class="gsc-trunc" title="<?= $e($r['name']) ?>"><?= $e($r['name']) ?></td><td class="num"><?= $n($r['clicks']) ?></td><td class="num"><?= $n($r['impressions']) ?></td><td class="num"><?= $pct($r['ctr']) ?></td><td class="num"><?= $pos($r['position']) ?></td></tr><?php endforeach; ?>
            <?php if (!$report['queries']): ?><tr><td colspan="5" class="text-muted text-center p-3">Sin consultas en este periodo.</td></tr><?php endif; ?>
            </tbody></table></div></div></div>
        <div class="col-lg-6"><div class="card"><div class="card-header"><strong>Páginas</strong> <span class="text-muted" style="font-size:.8rem;">las que más tráfico de Google reciben</span> <?= $csvLink('pages') ?></div>
            <div class="table-responsive"><table class="table table-sm mb-0 gsc-sortable gsc-fit"><thead><tr><th>Página</th><th class="num">Clics</th><th class="num">Impr.</th><th class="num">CTR</th><th class="num">Pos.</th></tr></thead><tbody>
            <?php foreach ($report['pages'] as $r): ?><tr><td class="gsc-trunc" title="<?= $e($r['name']) ?>"><?php $short = preg_replace('#^https?://(www\.)?' . preg_quote($property['domain'], '#') . '#i', '', $r['name']) ?: '/'; ?><?= $pageLink($r['name'], $short) ?><?php if (($r['variants'] ?? 1) > 1): ?> <span class="badge bg-light text-dark border" style="font-size:.65rem;" title="Suma de <?= (int) $r['variants'] ?> variantes de esta URL en Google (con/sin www, con #ancla o con parámetros de seguimiento)"><?= (int) $r['variants'] ?> variantes</span><?php endif; ?><?php if (($property['status'] ?? '') === 'active' && preg_match('#^https?://#i', (string) $r['name'])): ?> <button type="button" class="btn btn-link p-0 ms-1 gsc-inspect-row" data-url="<?= $e($r['name']) ?>" title="Ver si Google la tiene indexada"><i class="bi bi-search" style="font-size:.72rem;opacity:.6;"></i></button><?php endif; ?><?php if (!empty($content[$r['name']])): $ct = $content[$r['name']]; ?><div class="text-muted" style="font-size:.72rem;line-height:1.2;overflow:hidden;text-overflow:ellipsis;" title="<?= $e($ct['title']) ?>"><i class="bi bi-file-earmark-text"></i> <?= $e($ct['type']) ?>: <?= $e($ct['title']) ?> · <a href="<?= $e($ct['edit']) ?>" target="_blank" rel="noopener noreferrer">Editar</a></div><?php endif; ?></td><td class="num"><?= $n($r['clicks']) ?></td><td class="num"><?= $n($r['impressions']) ?></td><td class="num"><?= $pct($r['ctr']) ?></td><td class="num"><?= $pos($r['position']) ?></td></tr><?php endforeach; ?>
            <?php if (!$report['pages']): ?><tr><td colspan="5" class="text-muted text-center p-3">Sin páginas en este periodo.</td></tr><?php endif; ?>
            </tbody></table></div></div></div>
        <div class="col-lg-6"><div class="card"><div class="card-header"><strong>Países</strong> <?= $csvLink('countries') ?></div>
            <div class="table-responsive"><table class="table table-sm mb-0 gsc-sortable gsc-fit"><thead><tr><th>País</th><th class="num">Clics</th><th class="num">Impr.</th><th class="num">CTR</th><th class="num">Pos.</th></tr></thead><tbody>
            <?php foreach ($report['countries'] as $r): ?><tr><td><?= $e(strtoupper($r['name'])) ?></td><td class="num"><?= $n($r['clicks']) ?></td><td class="num"><?= $n($r['impressions']) ?></td><td class="num"><?= $pct($r['ctr']) ?></td><td class="num"><?= $pos($r['position']) ?></td></tr><?php endforeach; ?>
            </tbody></table></div></div></div>
        <div class="col-lg-6"><div class="card"><div class="card-header"><strong>Dispositivos</strong> <?= $csvLink('devices') ?></div>
            <div class="table-responsive"><table class="table table-sm mb-0 gsc-sortable gsc-fit"><thead><tr><th>Dispositivo</th><th class="num">Clics</th><th class="num">Impr.</th><th class="num">CTR</th><th class="num">Pos.</th></tr></thead><tbody>
            <?php foreach ($report['devices'] as $r): ?><tr><td><?= $e(['DESKTOP' => 'Ordenador', 'MOBILE' => 'Móvil', 'TABLET' => 'Tableta'][$r['name']] ?? $r['name']) ?></td><td class="num"><?= $n($r['clicks']) ?></td><td class="num"><?= $n($r['impressions']) ?></td><td class="num"><?= $pct($r['ctr']) ?></td><td class="num"><?= $pos($r['position']) ?></td></tr><?php endforeach; ?>
            </tbody></table></div></div></div>
    </div>
<?php endif; ?>

</div>
</div>

<script>
(function () {
    document.addEventListener('click', function (e) {
        var th = e.target.closest('table.gsc-sortable thead th'); if (!th) { return; }
        var table = th.closest('table'), idx = Array.prototype.indexOf.call(th.parentNode.children, th), asc = th.dataset.dir !== 'asc';
        function val(tr) {
            var c = tr.children[idx]; if (!c) { return ''; }
            var t = c.textContent.trim();
            if (th.classList.contains('num')) { if (/^[—–-]$/.test(t) || t === '') { return null; } var n = parseFloat(t.replace(/[^0-9,+\-]/g, '').replace(',', '.')); return isNaN(n) ? null : n; }
            return t.toLowerCase();
        }
        var tbody = table.tBodies[0], rows = Array.prototype.filter.call(tbody.rows, function (r) { return !r.querySelector('td[colspan]'); });
        rows.sort(function (a, b) {
            var x = val(a), y = val(b);
            if (x === null && y === null) { return 0; } if (x === null) { return 1; } if (y === null) { return -1; }
            return (typeof x === 'number' ? x - y : String(x).localeCompare(String(y), 'es')) * (asc ? 1 : -1);
        });
        rows.forEach(function (r) { tbody.appendChild(r); });
        table.querySelectorAll('thead th').forEach(function (h) { delete h.dataset.dir; h.classList.remove('gsc-asc', 'gsc-desc'); });
        th.dataset.dir = asc ? 'asc' : 'desc'; th.classList.add(asc ? 'gsc-asc' : 'gsc-desc');
    });
    var g = document.getElementById('gsc-glossary');
    if (g) g.addEventListener('click', function () {
        var items = [
            ['Clics', 'Veces que alguien vio tu página en los resultados de Google <em>y entró</em>.'],
            ['Impresiones', 'Veces que tu página <em>apareció</em> en los resultados de alguien, aunque no entrara. Es tu visibilidad.'],
            ['CTR', 'Porcentaje de impresiones que acaban en clic (clics ÷ impresiones). Un 3 % significa que 3 de cada 100 personas que te ven, entran. Depende mucho de la posición: en el puesto 1 lo normal es ~28 %, en el 10 ~2 %. Se mejora con un buen título y descripción.'],
            ['Posición media', 'Lugar medio en el que sales en los resultados. 1 es el primero; del 1 al 10 estás en la primera página; del 11 al 20, en la segunda. Es una media ponderada por impresiones: <strong>cuanto más bajo, mejor</strong>.'],
            ['vs. periodo anterior', 'Compara con el mismo número de días justo antes. En verde, mejora; en rojo, empeora. En la posición, bajar el número es mejorar.'],
            ['Consultas', 'Lo que la gente escribe en Google para encontrarte. Son palabras, no enlaces. Google oculta las búsquedas muy raras por privacidad: cuentan en los totales pero no salen en esta lista.'],
            ['Páginas', 'Las URL de tu sitio que aparecen en Google. Si haces clic en una se abre en otra pestaña.'],
            ['Países y dispositivos', 'Desde dónde y con qué aparato te ven: ordenador, móvil o tableta.'],
            ['Los datos van con ~2 días de retraso', 'Es el retraso normal de Google; el último día completo no está disponible todavía.'],
            ['Ordenar', 'Haz clic en el título de cualquier columna para ordenar por ella; otro clic invierte el orden.']
        ];
        var html = '<ul style="text-align:left;font-size:.92rem;padding-left:1.1rem;margin:0;">' + items.map(function (i) { return '<li class="mb-2"><strong>' + i[0] + '</strong>: ' + i[1] + '</li>'; }).join('') + '</ul>';
        if (window.Swal) { Swal.fire({ title: 'Qué significa cada dato', html: html, icon: 'info', width: 720, confirmButtonText: 'Entendido' }); }
    });
})();
</script>
<script>
(function () {
    var token = <?= json_encode(function_exists('csrf_token') ? csrf_token() : '') ?>, base = <?= json_encode($baseUrl) ?>, pid = <?= (int) $property['id'] ?>,
        kinds = <?= json_encode($optimizationKinds ?? [], JSON_UNESCAPED_UNICODE) ?>, def = <?= json_encode('https://' . $property['domain'] . '/') ?>;
    function esc(t) { return String(t == null ? '' : t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function post(path, params) { return fetch(base + path, { method: 'POST', headers: { 'X-CSRF-TOKEN': token }, body: new URLSearchParams(Object.assign({ _csrf: token, property_id: pid }, params)) }).then(function (r) { return r.json(); }); }
    document.addEventListener('click', function (e) {
        var add = e.target.closest('.gsc-opt-add'), del = e.target.closest('.gsc-opt-del');
        if (add) {
            var today = new Date().toISOString().slice(0, 10);
            Swal.fire({ title: 'Registrar una optimización', width: 640, showCancelButton: true, confirmButtonText: 'Registrar', cancelButtonText: 'Cancelar',
                html: '<div style="text-align:left;font-size:.9rem;"><p class="text-muted" style="font-size:.82rem;">Registra qué cambiaste y cuándo. El módulo comparará 28 días antes con 28 días después, y con lo que hizo el sitio entero. Es una coincidencia en el tiempo, no una prueba de causa.</p>' +
                    '<label class="form-label mb-0">Página (URL completa)</label><input id="opt-url" class="swal2-input" style="margin:.25rem 0 .6rem;width:100%;" value="' + esc(add.dataset.url || def) + '">' +
                    '<label class="form-label mb-0">Qué cambiaste</label><select id="opt-kind" class="swal2-select" style="margin:.25rem 0 .6rem;width:100%;">' + Object.keys(kinds).map(function (k) { return '<option value="' + k + '">' + esc(kinds[k]) + '</option>'; }).join('') + '</select>' +
                    '<label class="form-label mb-0">Nota (opcional)</label><input id="opt-note" class="swal2-input" maxlength="500" style="margin:.25rem 0 .6rem;width:100%;" placeholder="Ej.: título con «claude code precio», descripción nueva">' +
                    '<label class="form-label mb-0">Fecha del cambio</label><input id="opt-date" type="date" class="swal2-input" style="margin:.25rem 0 0;width:100%;" value="' + today + '" max="' + today + '"></div>',
                preConfirm: function () { return { url: document.getElementById('opt-url').value.trim(), kind: document.getElementById('opt-kind').value, note: document.getElementById('opt-note').value.trim(), done_on: document.getElementById('opt-date').value }; } })
            .then(function (x) {
                if (!x.isConfirmed) { return; }
                post('/optimization/add', x.value).then(function (r) { if (r.ok) { location.reload(); } else { Swal.fire({ icon: 'error', title: 'No se pudo registrar', text: r.message || 'Error' }); } })
                    .catch(function () { Swal.fire({ icon: 'error', title: 'Error de red' }); });
            });
        }
        if (del) {
            Swal.fire({ title: 'Quitar del seguimiento', text: 'Solo se borra el registro; la página y sus datos no se tocan.', icon: 'question', showCancelButton: true, confirmButtonText: 'Quitar', cancelButtonText: 'Cancelar' })
                .then(function (x) { if (x.isConfirmed) { post('/optimization/delete', { id: del.dataset.id }).then(function () { location.reload(); }); } });
        }
    });
})();
</script>
<script>
(function () {
    var token = <?= json_encode(function_exists('csrf_token') ? csrf_token() : '') ?>, base = <?= json_encode($baseUrl) ?>, pid = <?= (int) $property['id'] ?>,
        def = <?= json_encode(($property['page_filter'] ?? '') !== '' ? $property['page_filter'] : 'https://' . $property['domain'] . '/') ?>;
    function esc(t) { return String(t == null ? '' : t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function fdate(d) { if (!d) { return '—'; } var x = new Date(d); return isNaN(x) ? esc(d) : x.toLocaleString('es-ES', { dateStyle: 'medium', timeStyle: 'short' }); }
    function post(path, params) { params = Object.assign({ _csrf: token, property_id: pid }, params || {}); return fetch(base + path, { method: 'POST', headers: { 'X-CSRF-TOKEN': token }, body: new URLSearchParams(params) }).then(function (r) { return r.json(); }); }
    function inspect(url) {
        Swal.fire({ title: 'Consultando a Google…', text: url, allowOutsideClick: false, showConfirmButton: false, didOpen: function () { Swal.showLoading(); } });
        post('/inspect', { url: url }).then(function (r) {
            if (!r.ok) { Swal.fire({ icon: 'error', title: 'No se pudo inspeccionar', text: r.message || 'Error' }); return; }
            var tone = r.tone || 'secondary';
            var rows = [['Estado', '<span class="badge bg-' + tone + '">' + esc(r.coverage_es) + '</span>'],
                ['¿Indexada?', r.indexed ? '✅ Sí, aparece en Google' : '❌ No (todavía)'],
                ['Último rastreo', fdate(r.last_crawl)],
                ['Canonical que declara la página', esc(r.user_canonical || '—')],
                ['Canonical que eligió Google', esc(r.google_canonical || '—') + (r.canonical_differs ? ' <span class="badge bg-warning text-dark">distinto</span>' : '')]];
            if (r.fetch) { rows.push(['Descarga de la página', esc(r.fetch)]); }
            if (r.robots) { rows.push(['robots.txt', esc(r.robots)]); }
            var tip = '';
            if (/redirec/i.test(r.coverage)) { tip = 'Esta URL <strong>redirige</strong>: Google ya la ha detectado como redirección y traspasa su SEO al destino. Es lo esperado tras «Mover el SEO».'; }
            else if (r.canonical_differs) { tip = 'Google considera <strong>otra URL</strong> como la principal. Si acabas de mover el SEO, es normal mientras traspasa.'; }
            else if (!r.indexed) { tip = 'Google aún no la tiene indexada. Envía el sitemap y espera: puede tardar días.'; }
            Swal.fire({ title: 'Inspección de URL', width: 720, confirmButtonText: 'Cerrar', html: '<div style="text-align:left;font-size:.88rem;"><div class="text-muted mb-2" style="word-break:break-all;">' + esc(url) + '</div><table class="table table-sm mb-2">' +
                rows.map(function (x) { return '<tr><th style="width:38%;font-weight:600;">' + x[0] + '</th><td style="word-break:break-all;">' + x[1] + '</td></tr>'; }).join('') + '</table>' + (tip ? '<div class="alert alert-light border py-2 mb-0">' + tip + '</div>' : '') +
                (r.link ? '<div class="mt-2"><a href="' + esc(r.link) + '" target="_blank" rel="noopener noreferrer">Abrir en Search Console ↗</a></div>' : '') + '</div>' });
        }).catch(function () { Swal.fire({ icon: 'error', title: 'Error de red' }); });
    }
    var io = document.getElementById('gsc-inspect-open');
    if (io) io.addEventListener('click', function () {
        Swal.fire({ title: 'Inspeccionar una URL', input: 'url', inputValue: def, inputLabel: 'URL completa de una página de este dominio', showCancelButton: true, confirmButtonText: 'Inspeccionar', cancelButtonText: 'Cancelar',
            inputValidator: function (v) { return /^https?:\/\//i.test(v) ? undefined : 'Escribe una URL completa (https://…)'; } })
            .then(function (x) { if (x.isConfirmed) { inspect(x.value.trim()); } });
    });
    document.addEventListener('click', function (e) { var b = e.target.closest('.gsc-inspect-row'); if (b) { inspect(b.dataset.url); } });
    var sm = document.getElementById('gsc-sitemap');
    if (sm) sm.addEventListener('click', function () {
        sm.disabled = true;
        Swal.fire({ title: 'Enviando el sitemap a Google…', allowOutsideClick: false, showConfirmButton: false, didOpen: function () { Swal.showLoading(); } });
        post('/sitemap/submit').then(function (r) {
            var st = r.status, extra = st ? '<div class="text-muted mt-2" style="font-size:.85rem;">Estado en Google: ' + (st.pending ? 'pendiente de procesar' : 'recibido') + (st.last_downloaded ? ' · última descarga ' + fdate(st.last_downloaded) : '') + (st.errors ? ' · <span class="text-danger">' + st.errors + ' errores</span>' : '') + '</div>' : '';
            Swal.fire({ icon: r.ok ? 'success' : 'error', title: r.ok ? 'Sitemap enviado' : 'No se pudo', html: '<div style="word-break:break-all;">' + esc(r.message || '') + '</div>' + extra + (r.ok ? '<div class="text-muted mt-2" style="font-size:.8rem;">Google decide cuándo rastrear: no es instantáneo.</div>' : '') });
        }).catch(function () { Swal.fire({ icon: 'error', title: 'Error de red' }); }).then(function () { sm.disabled = false; });
    });
})();
</script>
<script>
(function () {
    var syncUrl = <?= json_encode($baseUrl . '/sync') ?>, token = <?= json_encode(function_exists('csrf_token') ? csrf_token() : '') ?>;
    function sync(btn) {
        var original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Descargando…';
        if (window.Swal) { Swal.fire({ title: 'Descargando datos de Search Console…', text: 'La primera vez baja hasta 16 meses y puede tardar uno o dos minutos.', allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, didOpen: function () { Swal.showLoading(); } }); }
        fetch(syncUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': token }, body: new URLSearchParams({ property_id: btn.dataset.property || <?= (int) $property['id'] ?>, _csrf: token }) })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.ok) { location.reload(); return; }
                if (window.Swal) { Swal.fire({ icon: 'error', title: 'No se pudo descargar', text: res.message || 'Error desconocido' }); }
                btn.disabled = false; btn.innerHTML = original;
            })
            .catch(function () { if (window.Swal) { Swal.fire({ icon: 'error', title: 'Error de red' }); } btn.disabled = false; btn.innerHTML = original; });
    }
    ['gsc-sync', 'gsc-sync2'].forEach(function (id) { var b = document.getElementById(id); if (b) { b.addEventListener('click', function () { sync(b); }); } });

<?php if (!$empty): ?>
    // Gráfico diario: clics (azul) e impresiones (morado), cada serie con su escala
    var data = <?= json_encode(array_map(fn ($d) => [$d['date'], (int) $d['clicks'], (int) $d['impressions']], $report['daily'])) ?>;
    var box = document.getElementById('gsc-chart'), W = 900, H = 240, L = 8, R = 8, T = 10, B = 24, n = data.length;
    function line(idx, color) {
        var max = Math.max.apply(null, data.map(function (d) { return d[idx]; })) || 1, pts = data.map(function (d, i) {
            return (L + (n > 1 ? i * (W - L - R) / (n - 1) : 0)).toFixed(1) + ',' + (H - B - d[idx] / max * (H - B - T)).toFixed(1);
        });
        return '<polyline fill="none" stroke="' + color + '" stroke-width="2" stroke-linejoin="round" points="' + pts.join(' ') + '"/>';
    }
    var ticks = '';
    [0, Math.floor((n - 1) / 2), n - 1].forEach(function (i) { if (data[i]) { ticks += '<text x="' + (L + (n > 1 ? i * (W - L - R) / (n - 1) : 0)) + '" y="' + (H - 6) + '" font-size="11" fill="#6c757d" text-anchor="' + (i === 0 ? 'start' : (i === n - 1 ? 'end' : 'middle')) + '">' + data[i][0].split('-').reverse().join('/') + '</text>'; } });
    box.innerHTML = '<svg viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="none" role="img" aria-label="Clics e impresiones por día"><line x1="' + L + '" x2="' + (W - R) + '" y1="' + (H - B) + '" y2="' + (H - B) + '" stroke="#dee2e6"/>' + line(2, '#8a56c5') + line(1, '#1a73e8') + ticks + '</svg>';
<?php endif; ?>
})();
</script>
