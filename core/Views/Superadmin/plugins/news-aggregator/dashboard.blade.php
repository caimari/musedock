@extends('layouts.app')

@section('title', 'News Aggregator')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        @include('plugins.news-aggregator._nav', ['activeTab' => 'dashboard', 'tenantId' => $tenantId ?? 0, 'tenants' => $tenants ?? []])

        @include('partials.alerts-sweetalert2')

        @if($isGlobal ?? true)
            {{-- Vista global: listado de tenants --}}
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Tenants con News Aggregator activo</h5>
                </div>
                @if(empty($tenantStats))
                    <div class="card-body text-center py-5">
                        <i class="bi bi-inbox fs-1 text-muted"></i>
                        <p class="text-muted mt-3">No hay tenants con el plugin News Aggregator activo.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Dominio</th>
                                    <th>Fuentes activas</th>
                                    <th>Total noticias</th>
                                    <th>Pendientes</th>
                                    <th>Publicadas</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tenantStats as $ts)
                                    <tr>
                                        <td>
                                            <strong>{{ $ts->domain }}</strong>
                                            @if(!empty($ts->name) && $ts->name !== $ts->domain)
                                                <br><small class="text-muted">{{ $ts->name }}</small>
                                            @endif
                                        </td>
                                        <td><span class="badge bg-secondary">{{ $ts->sources_count }}</span></td>
                                        <td>{{ $ts->total_items }}</td>
                                        <td>
                                            @if($ts->pending_total > 0)
                                                <span class="badge bg-warning text-dark">{{ $ts->pending_total }}</span>
                                            @else
                                                <span class="text-muted">0</span>
                                            @endif
                                        </td>
                                        <td><span class="badge bg-success">{{ $ts->published_count }}</span></td>
                                        <td>
                                            <a href="/musedock/news-aggregator?tenant={{ $ts->id }}" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-box-arrow-in-right"></i> Gestionar
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @else
            {{-- Vista de tenant específico --}}

            {{-- Pipeline Status Bar --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <span class="fw-bold me-2"><i class="bi bi-gear-wide-connected"></i> Pipeline:</span>
                            <span class="badge bg-primary">Captura (cron)</span>
                            <i class="bi bi-arrow-right text-muted"></i>
                            <span class="badge {{ ($settings['auto_rewrite'] ?? false) ? 'bg-success' : 'bg-warning text-dark' }}">
                                Reescritura {{ ($settings['auto_rewrite'] ?? false) ? '(auto)' : '(manual)' }}
                            </span>
                            <i class="bi bi-arrow-right text-muted"></i>
                            <span class="badge {{ ($settings['auto_approve'] ?? false) ? 'bg-success' : 'bg-warning text-dark' }}">
                                Aprobación {{ ($settings['auto_approve'] ?? false) ? '(auto)' : '(manual)' }}
                            </span>
                            <i class="bi bi-arrow-right text-muted"></i>
                            <span class="badge {{ ($settings['auto_publish'] ?? false) ? 'bg-success' : 'bg-warning text-dark' }}">
                                Publicación {{ ($settings['auto_publish'] ?? false) ? '(auto)' : '(manual)' }}
                            </span>
                            <i class="bi bi-arrow-right text-muted"></i>
                            <span class="badge bg-info">Blog</span>
                        </div>
                        <form action="/musedock/news-aggregator/run-pipeline" method="POST" class="ms-3">
                            @csrf
                            <input type="hidden" name="tenant_id" value="{{ $tenantId }}">
                            <button type="submit" class="btn btn-sm btn-primary" id="btn-run-pipeline">
                                <i class="bi bi-play-fill"></i> Ejecutar pipeline
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Stats Cards --}}
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0 me-3">
                                    <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                                        <i class="bi bi-rss fs-4 text-primary"></i>
                                    </div>
                                </div>
                                <div>
                                    <h3 class="mb-0">{{ $stats['sources_count'] ?? 0 }}</h3>
                                    <small class="text-muted">Fuentes activas</small>
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
                                        <i class="bi bi-newspaper fs-4 text-success"></i>
                                    </div>
                                </div>
                                <div>
                                    <h3 class="mb-0">{{ $stats['total_items'] ?? 0 }}</h3>
                                    <small class="text-muted">Noticias capturadas</small>
                                    @if(($stats['items_today'] ?? 0) > 0)
                                        <small class="text-success d-block">+{{ $stats['items_today'] }} hoy</small>
                                    @endif
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
                                    <div class="bg-warning bg-opacity-10 rounded-3 p-3">
                                        <i class="bi bi-hourglass-split fs-4 text-warning"></i>
                                    </div>
                                </div>
                                <div>
                                    <h3 class="mb-0">{{ $stats['pending_total'] ?? 0 }}</h3>
                                    <small class="text-muted">Pendientes</small>
                                    <small class="d-block text-muted" style="font-size: 0.7rem;">
                                        @if(($stats['pending_rewrite'] ?? 0) > 0)
                                            <span class="text-secondary">{{ $stats['pending_rewrite'] }} reescribir</span>
                                        @endif
                                        @if(($stats['pending_review'] ?? 0) > 0)
                                            {{ ($stats['pending_rewrite'] ?? 0) > 0 ? ' · ' : '' }}<span class="text-warning">{{ $stats['pending_review'] }} revisar</span>
                                        @endif
                                        @if(($stats['pending_publish'] ?? 0) > 0)
                                            {{ (($stats['pending_rewrite'] ?? 0) + ($stats['pending_review'] ?? 0)) > 0 ? ' · ' : '' }}<span class="text-success">{{ $stats['pending_publish'] }} publicar</span>
                                        @endif
                                    </small>
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
                                        <i class="bi bi-cpu fs-4 text-info"></i>
                                    </div>
                                </div>
                                <div>
                                    <h3 class="mb-0">{{ number_format($stats['tokens_today'] ?? 0) }}</h3>
                                    <small class="text-muted">Tokens hoy</small>
                                    @if(($stats['published_count'] ?? 0) > 0)
                                        <small class="text-primary d-block">{{ $stats['published_count'] }} publicadas</small>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                {{-- Recent Items --}}
                <div class="col-md-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Últimas noticias capturadas</h5>
                            <a href="/musedock/news-aggregator/items?tenant={{ $tenantId }}" class="btn btn-sm btn-outline-primary">Ver todo</a>
                        </div>
                        <div class="card-body p-0">
                            @if(empty($recentItems))
                                <div class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1"></i>
                                    <p class="mt-2">No hay noticias capturadas</p>
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Título</th>
                                                <th>Fuente</th>
                                                <th>Estado</th>
                                                <th>Fecha</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($recentItems as $item)
                                            <tr>
                                                <td>
                                                    <a href="/musedock/news-aggregator/items/{{ $item->id }}?tenant={{ $tenantId }}">
                                                        {{ mb_substr($item->original_title ?? '', 0, 60) }}{{ mb_strlen($item->original_title ?? '') > 60 ? '...' : '' }}
                                                    </a>
                                                </td>
                                                <td><small>{{ $item->source_name ?? '-' }}</small></td>
                                                <td>
                                                    @php
                                                        $sc = match($item->status) {
                                                            'pending' => 'secondary', 'processing' => 'info', 'ready' => 'warning',
                                                            'approved' => 'success', 'rejected' => 'danger', 'published' => 'primary',
                                                            default => 'secondary'
                                                        };
                                                        $sl = match($item->status) {
                                                            'pending' => 'Pendiente', 'processing' => 'Procesando', 'ready' => 'Listo',
                                                            'approved' => 'Aprobado', 'rejected' => 'Rechazado', 'published' => 'Publicado',
                                                            default => $item->status
                                                        };
                                                    @endphp
                                                    <span class="badge bg-{{ $sc }}">{{ $sl }}</span>
                                                </td>
                                                <td><small>{{ date('d/m H:i', strtotime($item->created_at)) }}</small></td>
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
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Errores recientes</h5>
                        </div>
                        <div class="card-body">
                            @if(empty($recentErrors))
                                <p class="text-muted mb-0">Sin errores</p>
                            @else
                                @foreach($recentErrors as $error)
                                    <div class="border-bottom pb-2 mb-2">
                                        <small class="text-danger fw-bold">{{ $error->source_name ?? $error->action }}</small><br>
                                        <small class="text-muted">{{ mb_substr($error->error_message ?? '', 0, 80) }}{{ mb_strlen($error->error_message ?? '') > 80 ? '...' : '' }}</small><br>
                                        <small class="text-muted">{{ date('d/m H:i', strtotime($error->created_at)) }}</small>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Acciones rápidas</h5>
                        </div>
                        <div class="card-body d-grid gap-2">
                            <a href="/musedock/news-aggregator/sources/create?tenant={{ $tenantId }}" class="btn btn-outline-primary">
                                <i class="bi bi-plus-circle"></i> Añadir fuente
                            </a>
                            <a href="/musedock/news-aggregator/items?tenant={{ $tenantId }}&status=ready" class="btn btn-outline-warning">
                                <i class="bi bi-eye"></i> Revisar pendientes
                            </a>
                            <a href="/musedock/news-aggregator/settings?tenant={{ $tenantId }}" class="btn btn-outline-secondary">
                                <i class="bi bi-gear"></i> Configuración
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

@if(!($isGlobal ?? true))
<script>
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('btn-run-pipeline');
    if (btn) {
        btn.closest('form').addEventListener('submit', function() {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Ejecutando...';
        });
    }
});
</script>
@endif
@endsection
