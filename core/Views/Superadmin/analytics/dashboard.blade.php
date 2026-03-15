@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="bi bi-graph-up-arrow me-2"></i>Web Analytics
        </h1>

        <div class="d-flex gap-2">
            <!-- Selector de período -->
            <select id="periodSelector" class="form-select" style="width: auto;">
                <option value="7" <?= $period == 7 ? 'selected' : '' ?>>Últimos 7 días</option>
                <option value="30" <?= $period == 30 ? 'selected' : '' ?>>Últimos 30 días</option>
                <option value="90" <?= $period == 90 ? 'selected' : '' ?>>Últimos 90 días</option>
            </select>

            <?php if (!empty($tenants)): ?>
            <!-- Selector de tenant -->
            <select id="tenantSelector" class="form-select" style="width: auto;">
                <option value="">Todos los sitios</option>
                <?php foreach ($tenants as $tenant): ?>
                    <option value="<?= $tenant['id'] ?>" <?= $tenantId == $tenant['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tenant['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
        </div>
    </div>

    <!-- Estadísticas en Tiempo Real -->
    <div class="alert alert-info mb-4">
        <div class="d-flex align-items-center">
            <i class="bi bi-activity fs-4 me-3"></i>
            <div>
                <strong>Visitantes activos ahora:</strong>
                <span id="realtimeVisitors" class="fs-4 ms-2"><?= $realtime['active_visitors'] ?? 0 ?></span>
                <span class="text-muted ms-3">
                    (<?= $realtime['recent_pageviews'] ?? 0 ?> páginas vistas en los últimos 5 minutos)
                </span>
            </div>
        </div>
    </div>

    <!-- Cards de estadísticas generales -->
    <div class="row g-3 mb-4">
        <!-- Total Visitas -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-1 small">Total Visitas</p>
                            <h3 class="mb-0"><?= number_format($stats['total_visits']) ?></h3>
                        </div>
                        <div class="bg-primary p-3 rounded">
                            <i class="bi bi-eye text-white fs-4"></i>
                        </div>
                    </div>
                    <?php
                    $changeClass = $stats['visit_change'] >= 0 ? 'text-success' : 'text-danger';
                    $changeIcon = $stats['visit_change'] >= 0 ? 'bi-arrow-up' : 'bi-arrow-down';
                    ?>
                    <small class="<?= $changeClass ?>">
                        <i class="bi <?= $changeIcon ?>"></i>
                        <?= abs($stats['visit_change']) ?>%
                        <span class="text-muted">vs período anterior</span>
                    </small>
                </div>
            </div>
        </div>

        <!-- Visitantes Únicos -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-1 small">Visitantes Únicos</p>
                            <h3 class="mb-0"><?= number_format($stats['unique_visitors']) ?></h3>
                        </div>
                        <div class="bg-success p-3 rounded">
                            <i class="bi bi-people text-white fs-4"></i>
                        </div>
                    </div>
                    <?php
                    $changeClass = $stats['visitor_change'] >= 0 ? 'text-success' : 'text-danger';
                    $changeIcon = $stats['visitor_change'] >= 0 ? 'bi-arrow-up' : 'bi-arrow-down';
                    ?>
                    <small class="<?= $changeClass ?>">
                        <i class="bi <?= $changeIcon ?>"></i>
                        <?= abs($stats['visitor_change']) ?>%
                        <span class="text-muted">vs período anterior</span>
                    </small>
                </div>
            </div>
        </div>

        <!-- Tasa de Rebote -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-1 small">Tasa de Rebote</p>
                            <h3 class="mb-0"><?= $stats['bounce_rate'] ?>%</h3>
                        </div>
                        <div class="bg-warning p-3 rounded">
                            <i class="bi bi-arrow-return-left text-dark fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted">
                        Visitantes que solo vieron 1 página
                    </small>
                </div>
            </div>
        </div>

        <!-- Duración Promedio -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-1 small">Duración Promedio</p>
                            <h3 class="mb-0"><?= gmdate("i:s", $stats['avg_duration']) ?></h3>
                        </div>
                        <div class="bg-info p-3 rounded">
                            <i class="bi bi-clock text-white fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted">
                        Tiempo promedio por sesión
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Visitas por Día -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">
                <i class="bi bi-graph-up me-2"></i>Visitas por Día
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th class="text-end">Visitas</th>
                            <th class="text-end">Únicos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $dateFormat = setting('date_format', 'd/m/Y');
                        if (empty($visitsChart)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">No hay datos</td></tr>
                        <?php else:
                            foreach (array_slice($visitsChart, 0, 14) as $day): ?>
                        <tr>
                            <td><?= date($dateFormat, strtotime($day['date'])) ?></td>
                            <td class="text-end"><?= number_format($day['visits']) ?></td>
                            <td class="text-end"><?= number_format($day['unique_visitors']) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Top Páginas -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">
                        <i class="bi bi-file-text me-2"></i>Páginas Más Visitadas
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" id="pagesScrollContainer" style="max-height: 600px; overflow-y: auto;">
                        <table class="table table-hover mb-0">
                            <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                                <tr>
                                    <th>Página</th>
                                    <th class="text-end sortable-col" data-sort="unique_visitors" style="cursor:pointer; user-select:none;">
                                        Únicos <i class="bi bi-arrow-down-up text-muted" style="font-size:0.75em;"></i>
                                    </th>
                                    <th class="text-end sortable-col" data-sort="visits" style="cursor:pointer; user-select:none;">
                                        Visitas <i class="bi bi-arrow-down-up text-muted" style="font-size:0.75em;"></i>
                                    </th>
                                    <th class="text-end">Tiempo Prom.</th>
                                </tr>
                            </thead>
                            <tbody id="pagesTableBody"></tbody>
                        </table>
                        <div id="pagesEnd" class="text-center py-2 text-muted small" style="display: none;">
                            Mostrando todos los resultados
                        </div>
                    </div>
                </div>
                <script>
                var _pagesData = <?= json_encode($topPages ?? []) ?>;
                </script>
            </div>
        </div>

        <!-- Top Países -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">
                        <i class="bi bi-globe me-2"></i>Países
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>País</th>
                                    <th class="text-end">Visitas</th>
                                    <th class="text-end">Únicos</th>
                                    <th class="text-end">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($topCountries)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        No hay datos disponibles
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php
                                    $totalVisits = array_sum(array_column($topCountries, 'visits'));
                                    $countryNames = [
                                        'ES' => 'España', 'US' => 'Estados Unidos', 'MX' => 'México',
                                        'AR' => 'Argentina', 'CO' => 'Colombia', 'CL' => 'Chile',
                                        'PE' => 'Perú', 'VE' => 'Venezuela', 'EC' => 'Ecuador',
                                        'GT' => 'Guatemala', 'CU' => 'Cuba', 'BO' => 'Bolivia',
                                        'DO' => 'Rep. Dominicana', 'HN' => 'Honduras', 'PY' => 'Paraguay',
                                        'SV' => 'El Salvador', 'NI' => 'Nicaragua', 'CR' => 'Costa Rica',
                                        'PA' => 'Panamá', 'UY' => 'Uruguay', 'GB' => 'Reino Unido',
                                        'FR' => 'Francia', 'DE' => 'Alemania', 'IT' => 'Italia',
                                        'BR' => 'Brasil', 'PT' => 'Portugal'
                                    ];
                                    ?>
                                    <?php foreach ($topCountries as $country): ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $countryCode = $country['country'];
                                            $countryName = $countryNames[$countryCode] ?? $countryCode;
                                            ?>
                                            <span class="fi fi-<?= strtolower($countryCode) ?> me-2"></span>
                                            <?= htmlspecialchars($countryName) ?>
                                        </td>
                                        <td class="text-end"><?= number_format($country['visits']) ?></td>
                                        <td class="text-end"><?= number_format($country['unique_visitors']) ?></td>
                                        <td class="text-end">
                                            <?= $totalVisits > 0 ? round(($country['visits'] / $totalVisits) * 100, 1) : 0 ?>%
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Fuentes de Tráfico Externas -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">
                        <i class="bi bi-signpost me-2"></i>Fuentes de Tráfico
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php
                        $trafficLabels = ['search'=>'Buscadores','social'=>'Redes Sociales','direct'=>'Directo','referral'=>'Referencias externas','internal'=>'Navegación interna'];
                        $trafficIcons = ['search'=>'bi-search','social'=>'bi-share','direct'=>'bi-cursor','referral'=>'bi-link-45deg','internal'=>'bi-arrow-repeat'];
                        $trafficColors = ['search'=>'success','social'=>'bi-info','direct'=>'primary','referral'=>'warning','internal'=>'secondary'];

                        // Separar externas e internas
                        $externalSources = [];
                        $internalSources = [];
                        foreach ($trafficSources as $source) {
                            $type = $source['referrer_type'] ?? 'direct';
                            if ($type === 'internal') {
                                $internalSources[] = $source;
                            } else {
                                $externalSources[] = $source;
                            }
                        }

                        // Agrupar externas por tipo
                        $byType = [];
                        foreach ($externalSources as $source) {
                            $type = $source['referrer_type'] ?? 'direct';
                            if (!isset($byType[$type])) $byType[$type] = ['visits' => 0, 'sources' => []];
                            $byType[$type]['visits'] += $source['visits'];
                            if (!empty($source['referrer_domain'])) {
                                $byType[$type]['sources'][] = $source;
                            }
                        }

                        $totalExternal = array_sum(array_column($externalSources, 'visits'));

                        if (empty($externalSources)): ?>
                        <div class="list-group-item text-center text-muted py-4">No hay datos</div>
                        <?php else:
                            foreach ($byType as $type => $data):
                                $pct = $totalExternal > 0 ? ($data['visits'] / $totalExternal) * 100 : 0;
                                $icon = $trafficIcons[$type] ?? 'bi-globe';
                                $color = $trafficColors[$type] ?? 'secondary';
                                $label = $trafficLabels[$type] ?? $type;
                        ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span><i class="bi <?= $icon ?> me-1 text-<?= $color ?>"></i> <?= $label ?></span>
                                <strong><?= number_format($data['visits']) ?></strong>
                            </div>
                            <div class="progress mb-1" style="height: 4px;">
                                <div class="progress-bar bg-<?= $color ?>" style="width: <?= $pct ?>%"></div>
                            </div>
                            <?php if (!empty($data['sources'])): ?>
                            <div class="mt-1">
                                <?php foreach ($data['sources'] as $src): ?>
                                <div class="d-flex justify-content-between align-items-center ps-3" style="font-size: 0.82em;">
                                    <span class="text-truncate" style="max-width: 70%;">
                                        <?php
                                        $srcUrl = 'https://' . ($src['referrer_domain'] ?? '');
                                        $srcLabel = $src['referrer_domain'] ?? '';
                                        if ($type === 'search' && !empty($src['search_engine'])) {
                                            $srcLabel = ucfirst($src['search_engine']);
                                        }
                                        ?>
                                        <a href="<?= htmlspecialchars($srcUrl) ?>" target="_blank" rel="noopener" class="text-decoration-none text-muted" title="<?= htmlspecialchars($src['referrer_domain'] ?? '') ?>">
                                            <i class="bi <?= $type === 'search' ? 'bi-search' : 'bi-box-arrow-up-right' ?> me-1"></i><?= htmlspecialchars($srcLabel) ?>
                                        </a>
                                    </span>
                                    <span class="text-muted"><?= number_format($src['visits']) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navegación Interna (entre dominios propios) -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">
                        <i class="bi bi-arrow-repeat me-2"></i>Navegación Interna
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php
                        $totalInternal = array_sum(array_column($internalSources, 'visits'));
                        if (empty($internalSources)): ?>
                        <div class="list-group-item text-center text-muted py-4">No hay datos</div>
                        <?php else:
                            foreach ($internalSources as $src):
                                $pct = $totalInternal > 0 ? ($src['visits'] / $totalInternal) * 100 : 0;
                        ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-truncate" style="max-width: 70%;">
                                    <a href="https://<?= htmlspecialchars($src['referrer_domain'] ?? '') ?>" target="_blank" rel="noopener" class="text-decoration-none">
                                        <i class="bi bi-globe me-1"></i><?= htmlspecialchars($src['referrer_domain'] ?? 'Desconocido') ?>
                                    </a>
                                </span>
                                <strong><?= number_format($src['visits']) ?></strong>
                            </div>
                            <div class="progress" style="height: 4px;">
                                <div class="progress-bar bg-secondary" style="width: <?= $pct ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Dispositivos -->
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">
                        <i class="bi bi-phone me-2"></i>Dispositivos
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php
                        $deviceLabels = ['desktop'=>'Escritorio','mobile'=>'Móvil','tablet'=>'Tablet'];
                        $totalDevices = array_sum(array_column($devices, 'visits'));
                        if (empty($devices)): ?>
                        <div class="list-group-item text-center text-muted py-4">No hay datos</div>
                        <?php else:
                            foreach ($devices as $device):
                                $pct = $totalDevices > 0 ? ($device['visits'] / $totalDevices) * 100 : 0;
                        ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span><?= $deviceLabels[$device['device_type']] ?? $device['device_type'] ?></span>
                                <strong><?= number_format($device['visits']) ?></strong>
                            </div>
                            <div class="progress" style="height: 4px;">
                                <div class="progress-bar bg-success" style="width: <?= $pct ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navegadores -->
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">
                        <i class="bi bi-browser-chrome me-2"></i>Navegadores
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (empty($browsers)): ?>
                        <div class="list-group-item text-center text-muted py-4">
                            No hay datos disponibles
                        </div>
                        <?php else: ?>
                            <?php
                            $totalBrowserVisits = array_sum(array_column($browsers, 'visits'));
                            foreach ($browsers as $browser):
                                $percentage = $totalBrowserVisits > 0 ? ($browser['visits'] / $totalBrowserVisits) * 100 : 0;
                            ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span><?= htmlspecialchars($browser['browser']) ?></span>
                                    <strong><?= number_format($browser['visits']) ?></strong>
                                </div>
                                <div class="progress" style="height: 4px;">
                                    <div class="progress-bar" style="width: <?= $percentage ?>%"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<!-- Flag Icons CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.0.0/css/flag-icons.min.css">
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var periodSel = document.getElementById('periodSelector');
    var tenantSel = document.getElementById('tenantSelector');

    function updatePage() {
        var p = periodSel ? periodSel.value : '30';
        var t = tenantSel ? tenantSel.value : '';
        var url = '?period=' + p;
        if (t) url += '&tenant_id=' + t;
        window.location.href = url;
    }

    if (periodSel) periodSel.onchange = updatePage;
    if (tenantSel) tenantSel.onchange = updatePage;

    setInterval(function() {
        var t = tenantSel ? tenantSel.value : '';
        fetch('/musedock/analytics/realtime-api' + (t ? '?tenant_id=' + t : ''))
            .then(function(r) { return r.json(); })
            .then(function(d) {
                var el = document.getElementById('realtimeVisitors');
                if (el) el.textContent = d.active_visitors || 0;
            })
            .catch(function() {});
    }, 30000);

    // Tabla de páginas con scroll progresivo y columnas ordenables
    (function() {
        var container = document.getElementById('pagesScrollContainer');
        var tbody = document.getElementById('pagesTableBody');
        var endMsg = document.getElementById('pagesEnd');
        if (!container || !tbody || !window._pagesData) return;

        var allData = _pagesData;
        var displayed = 0;
        var BATCH = 30;
        var currentSort = '';
        var sortDir = 'asc';

        function escHtml(s) {
            var d = document.createElement('div');
            d.textContent = s || '';
            return d.innerHTML;
        }

        function renderRow(page) {
            var domain = page.tenant_domain || '';
            var fullUrl = domain ? 'https://' + domain + page.page_url : page.page_url;
            var title = page.page_title || page.page_url.replace(/^\//, '') || 'Inicio';
            if (title === '/' || !title) title = 'Inicio';
            var avg = parseFloat(page.avg_time) || 0;
            var mins = Math.floor(avg / 60);
            var secs = Math.floor(avg % 60);
            var avgTime = String(mins).padStart(2,'0') + ':' + String(secs).padStart(2,'0');

            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td><div class="text-truncate" style="max-width:550px;" title="' + escHtml(fullUrl) + '">' +
                '<a href="' + escHtml(fullUrl) + '" target="_blank" rel="noopener" class="text-decoration-none"><strong>' + escHtml(title) + '</strong></a>' +
                '<br><small class="text-muted">' + escHtml(domain + page.page_url) + '</small></div></td>' +
                '<td class="text-end">' + Number(page.unique_visitors).toLocaleString() + '</td>' +
                '<td class="text-end">' + Number(page.visits).toLocaleString() + '</td>' +
                '<td class="text-end">' + avgTime + '</td>';
            return tr;
        }

        function renderBatch() {
            var end = Math.min(displayed + BATCH, allData.length);
            for (var i = displayed; i < end; i++) {
                tbody.appendChild(renderRow(allData[i]));
            }
            displayed = end;
            endMsg.style.display = displayed >= allData.length ? 'block' : 'none';
        }

        function renderAll() {
            tbody.innerHTML = '';
            displayed = 0;
            endMsg.style.display = 'none';
            if (allData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No hay datos</td></tr>';
                return;
            }
            renderBatch();
        }

        function sortData(field) {
            if (currentSort === field) {
                sortDir = sortDir === 'desc' ? 'asc' : 'desc';
            } else {
                currentSort = field;
                sortDir = 'desc';
            }
            allData.sort(function(a, b) {
                var va = Number(a[field]) || 0;
                var vb = Number(b[field]) || 0;
                return sortDir === 'desc' ? vb - va : va - vb;
            });

            // Actualizar iconos
            document.querySelectorAll('.sortable-col i').forEach(function(icon) {
                icon.className = 'bi bi-arrow-down-up text-muted';
                icon.style.fontSize = '0.75em';
            });
            var activeCol = document.querySelector('[data-sort="' + field + '"] i');
            if (activeCol) {
                activeCol.className = 'bi ' + (sortDir === 'desc' ? 'bi-sort-down-alt' : 'bi-sort-up-alt') + ' text-primary';
            }

            renderAll();
        }

        // Click en columnas ordenables
        document.querySelectorAll('.sortable-col').forEach(function(th) {
            th.addEventListener('click', function() {
                sortData(th.getAttribute('data-sort'));
            });
        });

        // Scroll progresivo
        container.addEventListener('scroll', function() {
            if (displayed >= allData.length) return;
            if (container.scrollTop + container.clientHeight >= container.scrollHeight - 80) {
                renderBatch();
            }
        });

        // Render inicial ordenado por únicos
        sortData('unique_visitors');
    })();
});
</script>
@endpush

@endsection
