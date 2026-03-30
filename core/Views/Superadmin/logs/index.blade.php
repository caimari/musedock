@extends('layouts.app')

@section('title', $title ?? 'Visor de Logs')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="bi bi-file-text me-2"></i>
                        Visor de Logs
                    </h1>
                    <p class="text-muted mb-0">Monitorea errores y eventos del sistema</p>
                </div>
                <div>
                    <a href="/musedock/logs/download" class="btn btn-outline-primary me-2">
                        <i class="bi bi-download me-1"></i>
                        Descargar
                    </a>
                    <button type="button" class="btn btn-danger" onclick="confirmClearLogs()">
                        <i class="bi bi-trash me-1"></i>
                        Limpiar Logs
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Environment Info -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-info bg-opacity-10 rounded-3 p-3">
                                <i class="bi bi-server text-info fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Entorno</h6>
                            <h4 class="mb-0">{{ $environment['APP_ENV'] ?? 'production' }}</h4>
                            <small class="text-muted">Debug: {{ $environment['APP_DEBUG'] ?? 'false' }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                                <i class="bi bi-file-earmark-text text-primary fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Total Líneas</h6>
                            <h4 class="mb-0">{{ number_format($stats['total_lines'] ?? 0) }}</h4>
                            <small class="text-muted">{{ $stats['file_size_formatted'] ?? '0 B' }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-success bg-opacity-10 rounded-3 p-3">
                                <i class="bi bi-clock-history text-success fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Última Actualización</h6>
                            <h4 class="mb-0">{{ $stats['last_modified'] ? date('H:i:s', strtotime($stats['last_modified'])) : '-' }}</h4>
                            <small class="text-muted">{{ $stats['last_modified'] ? date('d/m/Y', strtotime($stats['last_modified'])) : '-' }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="/musedock/logs" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" name="search" value="{{ $search ?? '' }}" placeholder="Buscar en logs...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Nivel</label>
                    <select class="form-select" name="level">
                        <option value="all" {{ ($level ?? '') === 'all' ? 'selected' : '' }}>Todos</option>
                        <option value="debug" {{ ($level ?? '') === 'debug' ? 'selected' : '' }}>Debug</option>
                        <option value="info" {{ ($level ?? '') === 'info' ? 'selected' : '' }}>Info</option>
                        <option value="warning" {{ ($level ?? '') === 'warning' ? 'selected' : '' }}>Warning</option>
                        <option value="error" {{ ($level ?? '') === 'error' ? 'selected' : '' }}>Error</option>
                        <option value="critical" {{ ($level ?? '') === 'critical' ? 'selected' : '' }}>Critical</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Líneas</label>
                    <select class="form-select" name="lines">
                        <option value="50" {{ ($lines ?? 100) == 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ ($lines ?? 100) == 100 ? 'selected' : '' }}>100</option>
                        <option value="250" {{ ($lines ?? 100) == 250 ? 'selected' : '' }}>250</option>
                        <option value="500" {{ ($lines ?? 100) == 500 ? 'selected' : '' }}>500</option>
                        <option value="1000" {{ ($lines ?? 100) == 1000 ? 'selected' : '' }}>1000</option>
                    </select>
                </div>
                <div class="col-md-5 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-search me-1"></i>
                        Filtrar
                    </button>
                    <a href="/musedock/logs" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>
                        Limpiar Filtros
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Logs -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-dark text-white py-2">
            <div class="d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-terminal me-2"></i>Registro de Eventos</strong>
                <span class="badge bg-secondary">{{ count($logs ?? []) }} eventos</span>
            </div>
        </div>
        <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
            <table class="table table-sm table-hover mb-0 font-monospace" style="font-size: 0.85rem;">
                <thead class="table-light sticky-top">
                    <tr>
                        <th style="width: 160px;">Timestamp</th>
                        <th style="width: 90px;">Nivel</th>
                        <th style="width: 120px;">IP</th>
                        <th style="width: 150px;">Usuario</th>
                        <th>Mensaje</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr class="log-level-{{ $log['level'] ?? 'info' }}">
                        <td class="text-muted">{{ $log['timestamp'] ?? '-' }}</td>
                        <td>
                            @php
                                $level = $log['level'] ?? 'info';
                                $badgeClass = match($level) {
                                    'debug' => 'bg-secondary',
                                    'info' => 'bg-info',
                                    'warning' => 'bg-warning',
                                    'error' => 'bg-danger',
                                    'critical' => 'bg-dark',
                                    default => 'bg-secondary'
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }}">{{ strtoupper($level) }}</span>
                        </td>
                        <td class="text-muted">{{ $log['ip'] ?? '-' }}</td>
                        <td class="text-muted">{{ $log['user'] ?? '-' }}</td>
                        <td>{{ $log['message'] ?? $log['raw'] ?? '-' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-5">
                            <i class="bi bi-inbox fs-1 text-muted"></i>
                            <p class="text-muted mt-2">No hay logs disponibles</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Info sobre niveles de log -->
    <div class="alert alert-info mt-4">
        <h6 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Niveles de Log según Entorno</h6>
        <p class="mb-0"><strong>Actual:</strong> {{ $environment['log_level'] ?? 'N/A' }}</p>
        <hr>
        <small>
            <strong>Desarrollo (APP_DEBUG=true):</strong> Se registran todos los niveles (debug, info, warning, error, critical)<br>
            <strong>Producción (APP_DEBUG=false):</strong> Solo se registran warning, error y critical
        </small>
    </div>
</div>

<!-- Form oculto para limpiar logs -->
<form id="clearLogsForm" method="POST" action="/musedock/logs/clear" style="display: none;">
    @csrf
</form>

@endsection

@push('scripts')
<script>
function confirmClearLogs() {
    Swal.fire({
        title: '¿Limpiar todos los logs?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, limpiar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('clearLogsForm').submit();
        }
    });
}

// Auto-refresh cada 30 segundos si no hay búsqueda activa
@if(empty($search))
setInterval(function() {
    location.reload();
}, 30000);
@endif
</script>

<style>
.log-level-debug { background-color: #f8f9fa; }
.log-level-info { background-color: #e7f3ff; }
.log-level-warning { background-color: #fff3cd; }
.log-level-error { background-color: #f8d7da; }
.log-level-critical { background-color: #f5c2c7; font-weight: bold; }
</style>
@endpush
