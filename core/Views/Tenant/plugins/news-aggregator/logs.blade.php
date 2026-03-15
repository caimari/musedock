@extends('layouts.app')

@section('title', 'Logs - News Aggregator')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        @include('plugins.news-aggregator._nav', ['activeTab' => 'logs'])

        {{-- Flash Messages --}}
        @if(session('flash_success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('flash_success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            @php unset($_SESSION['flash_success']); @endphp
        @endif

        {{-- Filters --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Acción</label>
                        <select name="action" class="form-select" onchange="this.form.submit()">
                            <option value="">Todas</option>
                            <option value="fetch" {{ ($filters['action'] ?? '') === 'fetch' ? 'selected' : '' }}>Fetch</option>
                            <option value="rewrite" {{ ($filters['action'] ?? '') === 'rewrite' ? 'selected' : '' }}>Reescribir</option>
                            <option value="approve" {{ ($filters['action'] ?? '') === 'approve' ? 'selected' : '' }}>Aprobar</option>
                            <option value="reject" {{ ($filters['action'] ?? '') === 'reject' ? 'selected' : '' }}>Rechazar</option>
                            <option value="publish" {{ ($filters['action'] ?? '') === 'publish' ? 'selected' : '' }}>Publicar</option>
                            <option value="pipeline" {{ ($filters['action'] ?? '') === 'pipeline' ? 'selected' : '' }}>Pipeline</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Estado</label>
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">Todos</option>
                            <option value="success" {{ ($filters['status'] ?? '') === 'success' ? 'selected' : '' }}>Éxito</option>
                            <option value="failed" {{ ($filters['status'] ?? '') === 'failed' ? 'selected' : '' }}>Error</option>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        @if(empty($logs))
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-clock-history fs-1 text-muted"></i>
                    <p class="text-muted mt-3">No hay registros.</p>
                </div>
            </div>
        @else
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="text-muted">{{ count($logs) }} registros</span>
                    <form action="{{ admin_url('/plugins/news-aggregator/logs/clear') }}" method="POST" class="d-inline"
                          onsubmit="return confirm('¿Eliminar todos los logs?')">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash"></i> Limpiar logs
                        </button>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Acción</th>
                                <th>Estado</th>
                                <th>Items</th>
                                <th>Tokens</th>
                                <th>Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($logs as $log)
                                <tr>
                                    <td><small>{{ date('d/m/Y H:i:s', strtotime($log->created_at)) }}</small></td>
                                    <td>
                                        @php
                                            $actionClass = match($log->action) {
                                                'fetch' => 'info',
                                                'rewrite' => 'primary',
                                                'approve' => 'success',
                                                'reject' => 'warning',
                                                'publish' => 'success',
                                                'pipeline' => 'dark',
                                                default => 'secondary'
                                            };
                                            $actionLabel = match($log->action) {
                                                'fetch' => 'Fetch',
                                                'rewrite' => 'Reescribir',
                                                'approve' => 'Aprobar',
                                                'reject' => 'Rechazar',
                                                'publish' => 'Publicar',
                                                'pipeline' => 'Pipeline',
                                                default => $log->action
                                            };
                                            $logMeta = !empty($log->metadata) ? json_decode($log->metadata, true) : [];
                                        @endphp
                                        <span class="badge bg-{{ $actionClass }}">{{ $actionLabel }}</span>
                                        @if($log->action === 'pipeline' && !empty($logMeta['source']))
                                            <br><small class="text-muted">
                                                <i class="bi bi-{{ $logMeta['source'] === 'cron' ? 'clock' : 'hand-index' }}"></i>
                                                {{ $logMeta['source'] === 'cron' ? 'Cron' : 'Manual' }}
                                            </small>
                                        @endif
                                    </td>
                                    <td>
                                        @if($log->status === 'success')
                                            <span class="badge bg-success">OK</span>
                                        @else
                                            <span class="badge bg-danger">Error</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($log->action === 'pipeline' && !empty($logMeta))
                                            <small>
                                                {{ $logMeta['fetched'] ?? 0 }} cap,
                                                {{ $logMeta['rewritten'] ?? 0 }} reesc,
                                                {{ $logMeta['approved'] ?? 0 }} apr,
                                                {{ $logMeta['published'] ?? 0 }} pub
                                            </small>
                                        @else
                                            {{ $log->items_count ?? '-' }}
                                        @endif
                                    </td>
                                    <td>{{ $log->tokens_used ? number_format($log->tokens_used) : '-' }}</td>
                                    <td>
                                        @if(!empty($log->error_message))
                                            <small class="text-danger" title="{{ $log->error_message }}">
                                                {{ mb_strimwidth($log->error_message ?? '', 0, 50, '...') }}
                                            </small>
                                        @elseif($log->action === 'pipeline' && !empty($logMeta['log']))
                                            <small class="text-muted" title="{{ implode(' | ', $logMeta['log']) }}">
                                                {{ mb_strimwidth(end($logMeta['log']) ?: '', 0, 50, '...') }}
                                            </small>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
