@extends('layouts.app')

@section('title', 'Cola - Cross-Publisher')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        @include('plugins.cross-publisher._nav', ['activeTab' => 'queue'])

        {{-- Flash Messages --}}
        @if(session('flash_success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('flash_success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('flash_error'))
            <div class="alert alert-danger alert-dismissible fade show">
                {{ session('flash_error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Cola de publicaciones</h4>
            <div>
                <a href="{{ admin_url('/plugins/cross-publisher/queue/process-all') }}" class="btn btn-success me-2">
                    <i class="bi bi-play-circle"></i> Procesar todo
                </a>
                <a href="{{ admin_url('/plugins/cross-publisher/queue/create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Nuevo
                </a>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Estado</label>
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">Todos</option>
                            <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pendiente</option>
                            <option value="processing" {{ ($filters['status'] ?? '') === 'processing' ? 'selected' : '' }}>Procesando</option>
                            <option value="completed" {{ ($filters['status'] ?? '') === 'completed' ? 'selected' : '' }}>Completado</option>
                            <option value="failed" {{ ($filters['status'] ?? '') === 'failed' ? 'selected' : '' }}>Fallido</option>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        @if(empty($queue))
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-inbox fs-1 text-muted"></i>
                    <p class="text-muted mt-3">No hay items en la cola.</p>
                    <a href="{{ admin_url('/plugins/cross-publisher/queue/create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Publicar en red
                    </a>
                </div>
            </div>
        @else
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Post</th>
                                <th>Destino</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($queue as $item)
                                <tr>
                                    <td>
                                        <strong>{{ \Illuminate\Support\Str::limit($item->post_title ?? '', 50) }}</strong>
                                        @if($item->translate)
                                            <br><small class="text-info">
                                                <i class="bi bi-translate"></i> Traducir a {{ strtoupper($item->target_language) }}
                                            </small>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ $item->target_tenant_name ?? '' }}</strong>
                                        <br><small class="text-muted">{{ $item->target_domain ?? '' }}</small>
                                    </td>
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
                                        @if($item->status === 'failed' && !empty($item->error_message))
                                            <br><small class="text-danger" title="{{ $item->error_message }}">
                                                {{ \Illuminate\Support\Str::limit($item->error_message, 30) }}
                                            </small>
                                        @endif
                                    </td>
                                    <td>
                                        <small>{{ date('d/m/Y H:i', strtotime($item->created_at)) }}</small>
                                        @if($item->completed_at)
                                            <br><small class="text-success">{{ date('d/m H:i', strtotime($item->completed_at)) }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            @if($item->status === 'pending')
                                                <a href="{{ admin_url('/plugins/cross-publisher/queue/' . $item->id . '/process') }}"
                                                   class="btn btn-outline-success" title="Procesar">
                                                    <i class="bi bi-play"></i>
                                                </a>
                                            @endif
                                            @if($item->status === 'completed' && $item->target_post_id)
                                                <a href="https://{{ $item->target_domain }}/admin/blog/posts/{{ $item->target_post_id }}/edit"
                                                   class="btn btn-outline-primary" target="_blank" title="Ver post">
                                                    <i class="bi bi-box-arrow-up-right"></i>
                                                </a>
                                            @endif
                                            @if($item->status !== 'processing')
                                                <a href="{{ admin_url('/plugins/cross-publisher/queue/' . $item->id . '/delete') }}"
                                                   class="btn btn-outline-danger"
                                                   onclick="return confirm('¿Estás seguro?')" title="Eliminar">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            @endif
                                        </div>
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
