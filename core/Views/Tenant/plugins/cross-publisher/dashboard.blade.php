@extends('layouts.app')

@section('title', 'Cross-Publisher')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        @include('plugins.cross-publisher._nav', ['activeTab' => 'dashboard'])

        {{-- Network Status --}}
        @if(!$networkKey)
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                Este tenant no está registrado en ninguna red editorial.
                <a href="{{ admin_url('/plugins/cross-publisher/network') }}" class="alert-link">
                    Configurar ahora
                </a>
            </div>
        @else
            <div class="alert alert-info">
                <i class="bi bi-diagram-3"></i>
                Red activa: <strong>{{ $networkKey }}</strong>
                ({{ count($networkTenants) }} miembros)
            </div>
        @endif

        {{-- Stats Cards --}}
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <div class="bg-warning bg-opacity-10 rounded-3 p-3">
                                    <i class="bi bi-hourglass-split fs-4 text-warning"></i>
                                </div>
                            </div>
                            <div>
                                <h3 class="mb-0">{{ $stats['pending_count'] ?? 0 }}</h3>
                                <small class="text-muted">Pendientes</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <div class="bg-info bg-opacity-10 rounded-3 p-3">
                                    <i class="bi bi-arrow-repeat fs-4 text-info"></i>
                                </div>
                            </div>
                            <div>
                                <h3 class="mb-0">{{ $stats['processing_count'] ?? 0 }}</h3>
                                <small class="text-muted">Procesando</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <div class="bg-success bg-opacity-10 rounded-3 p-3">
                                    <i class="bi bi-check-circle fs-4 text-success"></i>
                                </div>
                            </div>
                            <div>
                                <h3 class="mb-0">{{ $stats['completed_count'] ?? 0 }}</h3>
                                <small class="text-muted">Completados</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                                    <i class="bi bi-cpu fs-4 text-primary"></i>
                                </div>
                            </div>
                            <div>
                                <h3 class="mb-0">{{ number_format($stats['tokens_today'] ?? 0) }}</h3>
                                <small class="text-muted">Tokens hoy</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            {{-- Recent Queue --}}
            <div class="col-md-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Cola reciente</h5>
                        <a href="{{ admin_url('/plugins/cross-publisher/queue') }}" class="btn btn-sm btn-outline-primary">
                            Ver todo
                        </a>
                    </div>
                    <div class="card-body p-0">
                        @if(empty($recentQueue))
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1"></i>
                                <p class="mt-2">No hay items en la cola</p>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Post</th>
                                            <th>Destino</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentQueue as $item)
                                            <tr>
                                                <td>{{ \Illuminate\Support\Str::limit($item->post_title ?? '', 40) }}</td>
                                                <td>{{ $item->target_tenant_name ?? '' }}</td>
                                                <td>
                                                    @php
                                                        $statusClass = match($item->status) {
                                                            'pending' => 'warning',
                                                            'processing' => 'info',
                                                            'completed' => 'success',
                                                            'failed' => 'danger',
                                                            default => 'secondary'
                                                        };
                                                        $statusLabel = match($item->status) {
                                                            'pending' => 'Pendiente',
                                                            'processing' => 'Procesando',
                                                            'completed' => 'Completado',
                                                            'failed' => 'Fallido',
                                                            default => $item->status
                                                        };
                                                    @endphp
                                                    <span class="badge bg-{{ $statusClass }}">{{ $statusLabel }}</span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="col-md-4">
                {{-- Quick Actions --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Acciones rápidas</h5>
                    </div>
                    <div class="card-body d-grid gap-2">
                        <a href="{{ admin_url('/plugins/cross-publisher/queue/create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Publicar en red
                        </a>
                        @if(($stats['pending_count'] ?? 0) > 0)
                            <a href="{{ admin_url('/plugins/cross-publisher/queue/process-all') }}" class="btn btn-success">
                                <i class="bi bi-play-circle"></i> Procesar cola
                            </a>
                        @endif
                        <a href="{{ admin_url('/plugins/cross-publisher/settings') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-gear"></i> Configuración
                        </a>
                    </div>
                </div>

                {{-- Network Members --}}
                @if($networkKey && !empty($networkTenants))
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Miembros de la red</h5>
                        </div>
                        <ul class="list-group list-group-flush">
                            @foreach(array_slice($networkTenants, 0, 5) as $tenant)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>{{ $tenant->tenant_name }}</strong>
                                        <br><small class="text-muted">{{ $tenant->domain }}</small>
                                    </div>
                                    <span class="badge bg-{{ $tenant->default_language === 'es' ? 'primary' : 'secondary' }}">
                                        {{ strtoupper($tenant->default_language) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                        @if(count($networkTenants) > 5)
                            <div class="card-footer text-center bg-white">
                                <a href="{{ admin_url('/plugins/cross-publisher/network') }}">
                                    +{{ count($networkTenants) - 5 }} más
                                </a>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
