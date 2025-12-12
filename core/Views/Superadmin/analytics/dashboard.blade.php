@extends('superadmin.layouts.app')

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
                        <div class="bg-primary bg-opacity-10 p-3 rounded">
                            <i class="bi bi-eye text-primary fs-4"></i>
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
                        <div class="bg-success bg-opacity-10 p-3 rounded">
                            <i class="bi bi-people text-success fs-4"></i>
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
                        <div class="bg-warning bg-opacity-10 p-3 rounded">
                            <i class="bi bi-arrow-return-left text-warning fs-4"></i>
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
                        <div class="bg-info bg-opacity-10 p-3 rounded">
                            <i class="bi bi-clock text-info fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted">
                        Tiempo promedio por sesión
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráfico de Visitas -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">
                <i class="bi bi-graph-up me-2"></i>Visitas por Día
            </h5>
        </div>
        <div class="card-body">
            <canvas id="visitsChart" height="80"></canvas>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Top Páginas -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">
                        <i class="bi bi-file-text me-2"></i>Páginas Más Visitadas
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Página</th>
                                    <th class="text-end">Visitas</th>
                                    <th class="text-end">Únicos</th>
                                    <th class="text-end">Tiempo Prom.</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($topPages)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        No hay datos disponibles
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($topPages as $page): ?>
                                    <tr>
                                        <td>
                                            <div class="text-truncate" style="max-width: 300px;" title="<?= htmlspecialchars($page['page_url']) ?>">
                                                <strong><?= htmlspecialchars($page['page_title'] ?: 'Sin título') ?></strong>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($page['page_url']) ?></small>
                                            </div>
                                        </td>
                                        <td class="text-end"><?= number_format($page['visits']) ?></td>
                                        <td class="text-end"><?= number_format($page['unique_visitors']) ?></td>
                                        <td class="text-end"><?= gmdate("i:s", $page['avg_time'] ?? 0) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Países -->
        <div class="col-lg-6">
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
        <!-- Fuentes de Tráfico -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">
                        <i class="bi bi-signpost me-2"></i>Fuentes de Tráfico
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="trafficSourcesChart" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Dispositivos -->
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">
                        <i class="bi bi-phone me-2"></i>Dispositivos
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="devicesChart"></canvas>
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
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// Configuración de Chart.js
Chart.defaults.font.family = "'Inter', system-ui, -apple-system, sans-serif";
Chart.defaults.color = '#6c757d';

// Datos del gráfico de visitas
const visitsData = <?= json_encode($visitsChart) ?>;
const labels = visitsData.map(d => new Date(d.date).toLocaleDateString('es-ES', { day: '2-digit', month: 'short' }));
const visits = visitsData.map(d => d.visits);
const uniqueVisitors = visitsData.map(d => d.unique_visitors);

// Gráfico de Visitas por Día
const visitsCtx = document.getElementById('visitsChart').getContext('2d');
new Chart(visitsCtx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [
            {
                label: 'Total Visitas',
                data: visits,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.4,
                fill: true
            },
            {
                label: 'Visitantes Únicos',
                data: uniqueVisitors,
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                tension: 0.4,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            },
            tooltip: {
                mode: 'index',
                intersect: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        },
        interaction: {
            mode: 'nearest',
            axis: 'x',
            intersect: false
        }
    }
});

// Procesar datos de fuentes de tráfico
const trafficSources = <?= json_encode($trafficSources) ?>;
const trafficTypes = {};

trafficSources.forEach(source => {
    const type = source.referrer_type || 'direct';
    if (!trafficTypes[type]) {
        trafficTypes[type] = 0;
    }
    trafficTypes[type] += parseInt(source.visits);
});

const trafficLabels = {
    'search': 'Buscadores',
    'social': 'Redes Sociales',
    'direct': 'Directo',
    'referral': 'Referencias'
};

const trafficColors = {
    'search': '#0d6efd',
    'social': '#6f42c1',
    'direct': '#198754',
    'referral': '#ffc107'
};

// Gráfico de Fuentes de Tráfico
const trafficCtx = document.getElementById('trafficSourcesChart').getContext('2d');
new Chart(trafficCtx, {
    type: 'doughnut',
    data: {
        labels: Object.keys(trafficTypes).map(k => trafficLabels[k] || k),
        datasets: [{
            data: Object.values(trafficTypes),
            backgroundColor: Object.keys(trafficTypes).map(k => trafficColors[k] || '#6c757d'),
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Procesar datos de dispositivos
const devices = <?= json_encode($devices) ?>;
const deviceData = {};

devices.forEach(device => {
    const type = device.device_type || 'unknown';
    deviceData[type] = parseInt(device.visits);
});

const deviceLabels = {
    'desktop': 'Escritorio',
    'mobile': 'Móvil',
    'tablet': 'Tablet',
    'unknown': 'Desconocido'
};

const deviceColors = {
    'desktop': '#0d6efd',
    'mobile': '#198754',
    'tablet': '#ffc107',
    'unknown': '#6c757d'
};

// Gráfico de Dispositivos
const devicesCtx = document.getElementById('devicesChart').getContext('2d');
new Chart(devicesCtx, {
    type: 'doughnut',
    data: {
        labels: Object.keys(deviceData).map(k => deviceLabels[k] || k),
        datasets: [{
            data: Object.values(deviceData),
            backgroundColor: Object.keys(deviceData).map(k => deviceColors[k] || '#6c757d'),
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Actualizar estadísticas en tiempo real cada 30 segundos
setInterval(function() {
    const tenantId = document.getElementById('tenantSelector')?.value || '';
    const url = '/musedock/analytics/realtime-api' + (tenantId ? '?tenant_id=' + tenantId : '');

    fetch(url)
        .then(response => response.json())
        .then(data => {
            document.getElementById('realtimeVisitors').textContent = data.active_visitors || 0;
        })
        .catch(error => console.error('Error fetching realtime data:', error));
}, 30000);

// Manejar cambio de período
document.getElementById('periodSelector').addEventListener('change', function() {
    const tenantId = document.getElementById('tenantSelector')?.value || '';
    const period = this.value;

    let url = '?period=' + period;
    if (tenantId) {
        url += '&tenant_id=' + tenantId;
    }

    window.location.href = url;
});

// Manejar cambio de tenant
const tenantSelector = document.getElementById('tenantSelector');
if (tenantSelector) {
    tenantSelector.addEventListener('change', function() {
        const period = document.getElementById('periodSelector').value;
        const tenantId = this.value;

        let url = '?period=' + period;
        if (tenantId) {
            url += '&tenant_id=' + tenantId;
        }

        window.location.href = url;
    });
}
</script>
@endpush

@endsection
