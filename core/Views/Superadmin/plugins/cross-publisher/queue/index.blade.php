@extends('layouts.app')

@section('title', 'Cola de Publicación - Cross-Publisher')

@section('content')
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-list-task me-2"></i>Cola de Publicación</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="/musedock/cross-publisher">Cross-Publisher</a></li>
                    <li class="breadcrumb-item active">Cola</li>
                </ol>
            </nav>
        </div>
        @if(($counts['pending'] ?? 0) > 0)
        <form method="POST" action="/musedock/cross-publisher/queue/process">
            {!! csrf_field() !!}
            <button type="submit" class="btn btn-success" onclick="return confirm('¿Procesar todos los items pendientes?')">
                <i class="bi bi-play-fill me-1"></i>Procesar Pendientes ({{ $counts['pending'] ?? 0 }})
            </button>
        </form>
        @endif
    </div>

    @include('partials.alerts-sweetalert2')

    {{-- Status Tabs --}}
    <ul class="nav nav-pills mb-3">
        <li class="nav-item"><a class="nav-link {{ $currentStatus === '' ? 'active' : '' }}" href="/musedock/cross-publisher/queue">Todos <span class="badge bg-secondary">{{ array_sum($counts) }}</span></a></li>
        <li class="nav-item"><a class="nav-link {{ $currentStatus === 'pending' ? 'active' : '' }}" href="/musedock/cross-publisher/queue?status=pending">Pendientes <span class="badge bg-warning text-dark">{{ $counts['pending'] ?? 0 }}</span></a></li>
        <li class="nav-item"><a class="nav-link {{ $currentStatus === 'processing' ? 'active' : '' }}" href="/musedock/cross-publisher/queue?status=processing">Procesando <span class="badge bg-info">{{ $counts['processing'] ?? 0 }}</span></a></li>
        <li class="nav-item"><a class="nav-link {{ $currentStatus === 'completed' ? 'active' : '' }}" href="/musedock/cross-publisher/queue?status=completed">Completados <span class="badge bg-success">{{ $counts['completed'] ?? 0 }}</span></a></li>
        <li class="nav-item"><a class="nav-link {{ $currentStatus === 'failed' ? 'active' : '' }}" href="/musedock/cross-publisher/queue?status=failed">Fallidos <span class="badge bg-danger">{{ $counts['failed'] ?? 0 }}</span></a></li>
    </ul>

    <div class="card">
        <div class="card-body p-0">
            @if(empty($items))
                <div class="text-center py-5"><p class="text-muted">La cola está vacía.</p></div>
            @else
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Post</th>
                            <th>Origen</th>
                            <th>Destino</th>
                            <th>Traducir</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                        <tr>
                            <td>{{ $item->id }}</td>
                            <td class="text-truncate" style="max-width: 200px;">
                                <strong>{{ $item->post_title ?? 'Post #' . $item->source_post_id }}</strong>
                            </td>
                            <td><small>{{ $item->source_tenant_name ?? '' }}<br><span class="text-muted">{{ $item->source_domain ?? '' }}</span></small></td>
                            <td><small>{{ $item->target_tenant_name ?? '' }}<br><span class="text-muted">{{ $item->target_domain ?? '' }}</span></small></td>
                            <td>
                                @if($item->translate)
                                    <span class="badge bg-info">{{ strtoupper($item->target_language ?? '?') }}</span>
                                @else
                                    <span class="text-muted">No</span>
                                @endif
                            </td>
                            <td>
                                @switch($item->status)
                                    @case('pending')<span class="badge bg-warning text-dark">Pendiente</span>@break
                                    @case('processing')<span class="badge bg-info">Procesando</span>@break
                                    @case('completed')<span class="badge bg-success">Completado</span>@break
                                    @case('failed')
                                        <span class="badge bg-danger" title="{{ $item->error_message }}">Fallido</span>
                                        @if($item->error_message)
                                            <br><small class="text-danger">{{ mb_substr($item->error_message, 0, 60) }}...</small>
                                        @endif
                                    @break
                                    @default<span class="badge bg-secondary">{{ $item->status }}</span>
                                @endswitch
                            </td>
                            <td><small>{{ date('d/m/y H:i', strtotime($item->created_at)) }}</small></td>
                            <td>
                                @if($item->status === 'pending')
                                <form method="POST" action="/musedock/cross-publisher/queue/{{ $item->id }}/process" class="d-inline">
                                    {!! csrf_field() !!}
                                    <button type="submit" class="btn btn-sm btn-success" title="Procesar"><i class="bi bi-play-fill"></i></button>
                                </form>
                                @endif
                                @if($item->status === 'failed')
                                <form method="POST" action="/musedock/cross-publisher/queue/{{ $item->id }}/retry" class="d-inline">
                                    {!! csrf_field() !!}
                                    <button type="submit" class="btn btn-sm btn-warning" title="Reintentar"><i class="bi bi-arrow-clockwise"></i></button>
                                </form>
                                @endif
                                <form method="POST" action="/musedock/cross-publisher/queue/{{ $item->id }}/delete" class="d-inline" onsubmit="return confirm('¿Eliminar?')">
                                    {!! csrf_field() !!}
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                </form>
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
@endsection
