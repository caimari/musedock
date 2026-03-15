@extends('layouts.app')

@section('title', 'Cross-Publisher - Dashboard')

@section('content')
<div class="container-fluid p-4">
    @include('partials.alerts-sweetalert2')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-share me-2"></i>Cross-Publisher</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item active">Dashboard</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="/musedock/cross-publisher/posts" class="btn btn-primary">
                <i class="bi bi-search me-1"></i>Explorar Posts
            </a>
            <a href="/musedock/cross-publisher/groups" class="btn btn-outline-primary">
                <i class="bi bi-collection me-1"></i>Grupos
            </a>
            <a href="/musedock/cross-publisher/settings" class="btn btn-outline-secondary">
                <i class="bi bi-gear me-1"></i>Configuración
            </a>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-clock-history text-warning" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h2 class="mb-0">{{ $pendingCount }}</h2>
                            <p class="text-muted mb-0 small">En cola</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-check-circle text-success" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h2 class="mb-0">{{ $publishedToday }}</h2>
                            <p class="text-muted mb-0 small">Publicados hoy</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-info bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-link-45deg text-info" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h2 class="mb-0">{{ $totalRelations }}</h2>
                            <p class="text-muted mb-0 small">Relaciones activas</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-cpu text-primary" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h2 class="mb-0">{{ number_format($tokensToday) }}</h2>
                            <p class="text-muted mb-0 small">Tokens IA hoy</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Quick Actions --}}
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title mb-0">Acceso Rápido</h5></div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="/musedock/cross-publisher/posts" class="btn btn-outline-primary text-start">
                            <i class="bi bi-newspaper me-2"></i>Explorar y publicar posts
                        </a>
                        <a href="/musedock/cross-publisher/queue" class="btn btn-outline-warning text-start">
                            <i class="bi bi-list-task me-2"></i>Cola de publicación
                            @if($pendingCount > 0)
                            <span class="badge bg-warning text-dark float-end">{{ $pendingCount }}</span>
                            @endif
                        </a>
                        <a href="/musedock/cross-publisher/relations" class="btn btn-outline-info text-start">
                            <i class="bi bi-diagram-3 me-2"></i>Relaciones y Sync
                        </a>
                        <a href="/musedock/cross-publisher/groups" class="btn btn-outline-secondary text-start">
                            <i class="bi bi-collection me-2"></i>Grupos editoriales
                            <span class="badge bg-secondary float-end">{{ count($groups) }}</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent Activity --}}
        <div class="col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title mb-0">Actividad Reciente</h5></div>
                <div class="card-body p-0">
                    @if(empty($recentLogs))
                        <div class="text-center py-5">
                            <p class="text-muted">Sin actividad reciente.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Acción</th>
                                        <th>Post</th>
                                        <th>Origen</th>
                                        <th>Destino</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentLogs as $log)
                                    <tr>
                                        <td>
                                            @switch($log->action)
                                                @case('publish')
                                                    <span class="badge bg-primary">Publicar</span>
                                                    @break
                                                @case('sync')
                                                    <span class="badge bg-info">Sync</span>
                                                    @break
                                                @case('translate')
                                                    <span class="badge bg-secondary">Traducir</span>
                                                    @break
                                                @default
                                                    <span class="badge bg-light text-dark">{{ $log->action }}</span>
                                            @endswitch
                                        </td>
                                        <td class="text-truncate" style="max-width: 200px;">{{ $log->post_title ?? '-' }}</td>
                                        <td><small>{{ $log->source_domain ?? '-' }}</small></td>
                                        <td><small>{{ $log->target_domain ?? '-' }}</small></td>
                                        <td>
                                            @if($log->status === 'success')
                                                <i class="bi bi-check-circle text-success"></i>
                                            @else
                                                <i class="bi bi-x-circle text-danger" title="{{ $log->error_message }}"></i>
                                            @endif
                                        </td>
                                        <td><small class="text-muted">{{ date('d/m H:i', strtotime($log->created_at)) }}</small></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
