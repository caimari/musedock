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
?>
<style>
.gsc-page .card { border:1px solid #e5eaf1;border-radius:12px;box-shadow:0 3px 14px rgba(30,50,80,.035); }
.gsc-page .num { text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap; }
.gsc-page .table td,.gsc-page .table th { padding:9px 12px;vertical-align:middle; }
.gsc-up { color:#1e7e34; } .gsc-down { color:#c62828; }
.gsc-kpi { padding:1rem 1.1rem; } .gsc-kpi .v { font-size:1.55rem;font-weight:700;line-height:1.1; } .gsc-kpi .l { font-size:.8rem;color:#6c757d;text-transform:uppercase;letter-spacing:.04em; }
.gsc-chart svg { width:100%;height:240px;display:block; }
.gsc-trunc { max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
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
                <?php foreach ($periods as $d): ?><option value="<?= (int) $d ?>" <?= $report['days'] === $d ? 'selected' : '' ?>>Últimos <?= (int) $d ?> días</option><?php endforeach; ?>
            </select>
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

    <div class="card mb-3"><div class="card-body gsc-chart">
        <div class="d-flex gap-3 mb-2" style="font-size:.82rem;"><span><span style="display:inline-block;width:10px;height:10px;background:#1a73e8;border-radius:2px;"></span> Clics</span><span><span style="display:inline-block;width:10px;height:10px;background:#8a56c5;border-radius:2px;"></span> Impresiones</span></div>
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
        <?php if (!$o['items']): ?>
            <div class="text-center text-muted p-3"><i class="bi bi-hourglass-split"></i> Con tan pocos datos todavía no hay señales claras. Las oportunidades irán apareciendo a medida que el dominio reciba más búsquedas en Google.</div>
        <?php else: ?>
            <div class="d-flex flex-wrap gap-2 mb-3" id="gsc-opps-chips">
                <button type="button" class="btn btn-sm btn-dark" data-type="">Todas <span class="badge bg-light text-dark"><?= count($o['items']) ?></span></button>
                <?php foreach ($oppTypes as $key => [$label, $color]): if (empty($o['counts'][$key])) { continue; } ?>
                    <button type="button" class="btn btn-sm btn-outline-<?= $e($color) ?>" data-type="<?= $e($key) ?>"><?= $e($label) ?> <span class="badge bg-<?= $e($color) ?>"><?= (int) $o['counts'][$key] ?></span></button>
                <?php endforeach; ?>
            </div>
            <div class="table-responsive"><table class="table table-sm mb-0" id="gsc-opps-table"><thead><tr><th>Tipo</th><th>Qué</th><th class="num">Impr.</th><th class="num">Clics</th><th class="num">Pos.</th><th>Qué se puede ganar</th><th>Qué hacer</th></tr></thead><tbody>
            <?php foreach ($o['items'] as $it): [$tl, $tc] = $oppTypes[$it['type']]; ?>
                <tr data-type="<?= $e($it['type']) ?>">
                    <td><span class="badge bg-<?= $e($tc) ?>"><?= $e($tl) ?></span><div class="text-muted" style="font-size:.72rem;"><?= $it['kind'] === 'page' ? 'página' : 'consulta' ?></div></td>
                    <td class="gsc-trunc" title="<?= $e($it['name']) ?>"><strong><?= $e($it['label']) ?></strong></td>
                    <td class="num"><?= $n($it['impressions']) ?></td><td class="num"><?= $n($it['clicks']) ?></td><td class="num"><?= $pos($it['position']) ?></td>
                    <td style="font-size:.85rem;white-space:nowrap;"><?= $e($it['detail']) ?></td>
                    <td style="font-size:.82rem;min-width:260px;"><?= $e($it['advice']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
        <?php foreach ($o['notes'] as $note): ?><div class="text-muted mt-2" style="font-size:.78rem;"><i class="bi bi-info-circle"></i> <?= $e($note) ?></div><?php endforeach; ?>
        </div>
    </div>
    <script>
    (function () {
        var chips = document.querySelectorAll('#gsc-opps-chips [data-type]'), rows = document.querySelectorAll('#gsc-opps-table tbody tr');
        chips.forEach(function (b) {
            b.addEventListener('click', function () {
                chips.forEach(function (x) { x.classList.toggle('btn-dark', x === b && !x.dataset.type); });
                rows.forEach(function (r) { r.style.display = (!b.dataset.type || r.dataset.type === b.dataset.type) ? '' : 'none'; });
            });
        });
        var help = document.getElementById('gsc-opps-help');
        if (help) help.addEventListener('click', function () {
            var items = <?= json_encode(array_map(fn ($k) => [$oppTypes[$k][0], $oppHelp[$k]], array_keys($oppTypes)), JSON_UNESCAPED_UNICODE) ?>;
            var html = '<div style="text-align:left;font-size:.92rem"><p>Son reglas fijas (sin IA) sobre los datos que Google ya te ha dado. Se aplican a consultas y a páginas, y se ordenan por lo que podrías ganar.</p><ul>'
                + items.map(function (i) { return '<li><strong>' + i[0] + '</strong>: ' + i[1] + '</li>'; }).join('')
                + '</ul><p class="mb-1">El mínimo de impresiones se adapta al tamaño del sitio (entre 3 y 50). El CTR «normal» por posición es una referencia típica del sector (28 % en la 1.ª, 15 % en la 2.ª, 11 % en la 3.ª… 2 % en la 10.ª), no un dato de Google.</p></div>';
            if (window.Swal) { Swal.fire({ title: 'Cómo se detectan las oportunidades', html: html, icon: 'info', width: 680, confirmButtonText: 'Entendido' }); }
        });
    })();
    </script>
<?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-6"><div class="card"><div class="card-header"><strong>Consultas</strong> <span class="text-muted" style="font-size:.8rem;">lo que la gente busca en Google</span></div>
            <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Consulta</th><th class="num">Clics</th><th class="num">Impr.</th><th class="num">CTR</th><th class="num">Pos.</th></tr></thead><tbody>
            <?php foreach ($report['queries'] as $r): ?><tr><td class="gsc-trunc"><?= $e($r['name']) ?></td><td class="num"><?= $n($r['clicks']) ?></td><td class="num"><?= $n($r['impressions']) ?></td><td class="num"><?= $pct($r['ctr']) ?></td><td class="num"><?= $pos($r['position']) ?></td></tr><?php endforeach; ?>
            <?php if (!$report['queries']): ?><tr><td colspan="5" class="text-muted text-center p-3">Sin consultas en este periodo.</td></tr><?php endif; ?>
            </tbody></table></div></div></div>
        <div class="col-lg-6"><div class="card"><div class="card-header"><strong>Páginas</strong> <span class="text-muted" style="font-size:.8rem;">las que más tráfico de Google reciben</span></div>
            <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Página</th><th class="num">Clics</th><th class="num">Impr.</th><th class="num">CTR</th><th class="num">Pos.</th></tr></thead><tbody>
            <?php foreach ($report['pages'] as $r): ?><tr><td class="gsc-trunc" title="<?= $e($r['name']) ?>"><?= $e(preg_replace('#^https?://(www\.)?' . preg_quote($property['domain'], '#') . '#i', '', $r['name']) ?: '/') ?></td><td class="num"><?= $n($r['clicks']) ?></td><td class="num"><?= $n($r['impressions']) ?></td><td class="num"><?= $pct($r['ctr']) ?></td><td class="num"><?= $pos($r['position']) ?></td></tr><?php endforeach; ?>
            <?php if (!$report['pages']): ?><tr><td colspan="5" class="text-muted text-center p-3">Sin páginas en este periodo.</td></tr><?php endif; ?>
            </tbody></table></div></div></div>
        <div class="col-lg-6"><div class="card"><div class="card-header"><strong>Países</strong></div>
            <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>País</th><th class="num">Clics</th><th class="num">Impr.</th><th class="num">CTR</th><th class="num">Pos.</th></tr></thead><tbody>
            <?php foreach ($report['countries'] as $r): ?><tr><td><?= $e(strtoupper($r['name'])) ?></td><td class="num"><?= $n($r['clicks']) ?></td><td class="num"><?= $n($r['impressions']) ?></td><td class="num"><?= $pct($r['ctr']) ?></td><td class="num"><?= $pos($r['position']) ?></td></tr><?php endforeach; ?>
            </tbody></table></div></div></div>
        <div class="col-lg-6"><div class="card"><div class="card-header"><strong>Dispositivos</strong></div>
            <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Dispositivo</th><th class="num">Clics</th><th class="num">Impr.</th><th class="num">CTR</th><th class="num">Pos.</th></tr></thead><tbody>
            <?php foreach ($report['devices'] as $r): ?><tr><td><?= $e(['DESKTOP' => 'Ordenador', 'MOBILE' => 'Móvil', 'TABLET' => 'Tableta'][$r['name']] ?? $r['name']) ?></td><td class="num"><?= $n($r['clicks']) ?></td><td class="num"><?= $n($r['impressions']) ?></td><td class="num"><?= $pct($r['ctr']) ?></td><td class="num"><?= $pos($r['position']) ?></td></tr><?php endforeach; ?>
            </tbody></table></div></div></div>
    </div>
<?php endif; ?>

</div>
</div>

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
