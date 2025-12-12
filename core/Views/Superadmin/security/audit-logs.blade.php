@extends('layouts.app')

@section('title', $title ?? 'Security Dashboard')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="bi bi-shield-exclamation me-2"></i>
                        Security Dashboard
                    </h1>
                    <p class="text-muted mb-0">Monitor de intentos fallidos y rate limiting</p>
                </div>
                <div>
                    <span class="badge bg-info">
                        <i class="bi bi-pc-display me-1"></i>
                        Tu IP: {{ $currentIP }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Flash messages --}}
    @php
        $success = flash('success');
        $error = flash('error');
    @endphp

    @if($success)
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            {{ $success }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($error)
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            {{ $error }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Estadísticas Resumen -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 2rem;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">Total Registros Activos</div>
                            <h3 class="mb-0">{{ $totalRecords }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bi bi-envelope-at-fill text-warning" style="font-size: 2rem;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">Emails Únicos Afectados</div>
                            <h3 class="mb-0">{{ count($emailStats) }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bi bi-hdd-network-fill text-info" style="font-size: 2rem;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">IPs Únicas Detectadas</div>
                            <h3 class="mb-0">{{ count($ipStats) }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Whitelist de IPs -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-shield-check me-2"></i>
                        IPs de Confianza (Whitelist)
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Las IPs en esta lista no están sujetas a rate limiting.</p>

                    <!-- Formulario para añadir IP -->
                    <form method="POST" action="{{ route('security.add-trusted-ip') }}" class="mb-4">
                        {!! csrf_field() !!}
                        <div class="row g-3 align-items-end">
                            <div class="col-md-5">
                                <label for="ip" class="form-label">Dirección IP</label>
                                <input type="text" name="ip" id="ip" class="form-control" placeholder="192.168.1.100" required>
                            </div>
                            <div class="col-md-5">
                                <label for="description" class="form-label">Descripción (opcional)</label>
                                <input type="text" name="description" id="description" class="form-control" placeholder="Ej: Oficina central">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-plus-circle me-1"></i>
                                    Añadir
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Lista de IPs confiables -->
                    @if(count($trustedIPs) > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>IP</th>
                                        <th>Descripción</th>
                                        <th>Añadida</th>
                                        <th style="width: 100px;">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($trustedIPs as $trustedIP)
                                        <tr>
                                            <td>
                                                <code>{{ $trustedIP['ip_address'] }}</code>
                                                @if($trustedIP['ip_address'] === $currentIP)
                                                    <span class="badge bg-success ms-2">Tu IP actual</span>
                                                @endif
                                            </td>
                                            <td>{{ $trustedIP['description'] ?? '-' }}</td>
                                            <td class="text-muted small">{{ date('Y-m-d H:i', strtotime($trustedIP['created_at'])) }}</td>
                                            <td>
                                                <form method="POST" action="{{ route('security.remove-trusted-ip') }}" class="d-inline" onsubmit="return confirm('¿Eliminar esta IP de la whitelist?')">
                                                    {!! csrf_field() !!}
                                                    <input type="hidden" name="trusted_ip_id" value="{{ $trustedIP['id'] }}">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            No hay IPs de confianza configuradas. Añade una para evitar rate limiting.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Top Emails Atacados -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="bi bi-envelope-exclamation me-2"></i>
                        Top Emails Atacados
                    </h5>
                </div>
                <div class="card-body">
                    @if(count($emailStats) > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Email</th>
                                        <th class="text-center">Intentos</th>
                                        <th class="text-center">IPs Únicas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($emailStats as $email => $stats)
                                        <tr>
                                            <td><code>{{ $email }}</code></td>
                                            <td class="text-center">
                                                <span class="badge bg-danger">{{ $stats['total_attempts'] }}</span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-warning text-dark">{{ $stats['unique_ips'] }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-shield-check" style="font-size: 3rem;"></i>
                            <p class="mb-0 mt-2">No hay intentos fallidos registrados</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Top IPs Atacantes -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-hdd-network me-2"></i>
                        Top IPs Atacantes
                    </h5>
                </div>
                <div class="card-body">
                    @if(count($ipStats) > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>IP</th>
                                        <th class="text-center">Intentos</th>
                                        <th class="text-center">Emails Únicos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ipStats as $ip => $stats)
                                        <tr>
                                            <td><code>{{ $ip }}</code></td>
                                            <td class="text-center">
                                                <span class="badge bg-danger">{{ $stats['total_attempts'] }}</span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-warning text-dark">{{ $stats['unique_emails'] }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-shield-check" style="font-size: 3rem;"></i>
                            <p class="mb-0 mt-2">No hay IPs atacantes registradas</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de Rate Limits Activos -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-clock-history me-2"></i>
                        Rate Limits Activos
                    </h5>
                </div>
                <div class="card-body">
                    @if(count($rateLimits) > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Email</th>
                                        <th>IP</th>
                                        <th class="text-center">Intentos</th>
                                        <th>Estado</th>
                                        <th>Expira</th>
                                        <th>Registrado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rateLimits as $limit)
                                        <tr>
                                            <td>
                                                @if($limit['type'] === 'global')
                                                    <span class="badge bg-danger">Global</span>
                                                @else
                                                    <span class="badge bg-warning text-dark">Específico</span>
                                                @endif
                                            </td>
                                            <td><code>{{ $limit['email'] }}</code></td>
                                            <td>
                                                @if($limit['ip'] !== 'N/A')
                                                    <code>{{ $limit['ip'] }}</code>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-danger">{{ $limit['attempts'] }}</span>
                                            </td>
                                            <td>
                                                @if($limit['status'] === 'active')
                                                    <span class="badge bg-danger">
                                                        <i class="bi bi-lock-fill me-1"></i>
                                                        Bloqueado
                                                    </span>
                                                @else
                                                    <span class="badge bg-secondary">Expirado</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($limit['minutes_left'] > 0)
                                                    <span class="text-danger">
                                                        <i class="bi bi-clock me-1"></i>
                                                        {{ $limit['minutes_left'] }} min
                                                    </span>
                                                @else
                                                    <span class="text-muted">Expirado</span>
                                                @endif
                                            </td>
                                            <td class="text-muted small">
                                                {{ date('Y-m-d H:i', strtotime($limit['created_at'])) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-shield-check" style="font-size: 4rem;"></i>
                            <h5 class="mt-3">¡Todo en orden!</h5>
                            <p class="mb-0">No hay rate limits activos en este momento</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh cada 30 segundos
    setTimeout(function() {
        location.reload();
    }, 30000);

    // Auto-dismiss alerts
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});
</script>
@endpush
