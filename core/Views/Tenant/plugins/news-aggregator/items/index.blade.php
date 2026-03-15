@extends('layouts.app')

@section('title', 'Noticias - News Aggregator')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        @include('plugins.news-aggregator._nav', ['activeTab' => 'items'])

        {{-- Flash Messages --}}
        @if(session('flash_success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('flash_success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            @php unset($_SESSION['flash_success']); @endphp
        @endif
        @if(session('flash_error'))
            <div class="alert alert-danger alert-dismissible fade show">
                {{ session('flash_error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            @php unset($_SESSION['flash_error']); @endphp
        @endif

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
                            <option value="ready" {{ ($filters['status'] ?? '') === 'ready' ? 'selected' : '' }}>Listo para revisión</option>
                            <option value="approved" {{ ($filters['status'] ?? '') === 'approved' ? 'selected' : '' }}>Aprobado</option>
                            <option value="rejected" {{ ($filters['status'] ?? '') === 'rejected' ? 'selected' : '' }}>Rechazado</option>
                            <option value="published" {{ ($filters['status'] ?? '') === 'published' ? 'selected' : '' }}>Publicado</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Fuente</label>
                        <select name="source_id" class="form-select" onchange="this.form.submit()">
                            <option value="">Todas</option>
                            @foreach($sources as $source)
                                <option value="{{ $source->id }}" {{ ($filters['source_id'] ?? '') == $source->id ? 'selected' : '' }}>
                                    {{ $source->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>
        </div>

        @if(empty($items))
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-inbox fs-1 text-muted"></i>
                    <p class="text-muted mt-3">No hay noticias capturadas.</p>
                </div>
            </div>
        @else
            <form id="bulkForm" action="{{ admin_url('/plugins/news-aggregator/items/bulk') }}" method="POST">
                @csrf
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <div>
                            <input type="checkbox" id="selectAll" class="form-check-input me-2">
                            <label for="selectAll" class="form-check-label">Seleccionar todos</label>
                        </div>
                        <div class="btn-group btn-group-sm">
                            <button type="submit" name="action" value="approve" class="btn btn-outline-success">
                                <i class="bi bi-check-lg"></i> Aprobar
                            </button>
                            <button type="submit" name="action" value="reject" class="btn btn-outline-warning">
                                <i class="bi bi-x-lg"></i> Rechazar
                            </button>
                            <button type="submit" name="action" value="delete" class="btn btn-outline-danger"
                                    onclick="return confirm('¿Estás seguro de eliminar los seleccionados?')">
                                <i class="bi bi-trash"></i> Eliminar
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="40"></th>
                                    <th>Original</th>
                                    <th>Fuente</th>
                                    <th>Estado</th>
                                    <th>Fecha</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($items as $item)
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="ids[]" value="{{ $item->id }}" class="form-check-input item-checkbox">
                                        </td>
                                        <td>
                                            <a href="{{ admin_url('/plugins/news-aggregator/items/' . $item->id) }}">
                                                <strong>{{ mb_strimwidth($item->original_title ?? '', 0, 80, '...') }}</strong>
                                            </a>
                                            @if(!empty($item->rewritten_title))
                                                <br><small class="text-success">
                                                    <i class="bi bi-check-circle"></i>
                                                    {{ mb_strimwidth($item->rewritten_title ?? '', 0, 60, '...') }}
                                                </small>
                                            @endif
                                            @if(!empty($item->media_keywords))
                                                <br>
                                                @foreach(array_slice(explode(',', $item->media_keywords), 0, 4) as $kw)
                                                    <span class="badge bg-light text-muted border" style="font-size:0.65em;">{{ trim($kw) }}</span>
                                                @endforeach
                                            @endif
                                        </td>
                                        <td>
                                            <small>{{ $item->source_name ?? '-' }}</small>
                                            @if(($item->processing_type ?? 'direct') === 'verified')
                                                @php
                                                    $csList = !empty($item->cluster_sources) ? json_decode($item->cluster_sources, true) : [];
                                                    $csCount = count($csList);
                                                @endphp
                                                @if($csCount > 1)
                                                    <br><span class="badge bg-info" style="font-size:0.6em;">
                                                        <i class="bi bi-shield-check"></i> {{ $csCount }} fuentes
                                                    </span>
                                                @else
                                                    <br><span class="badge bg-info" style="font-size:0.6em;"><i class="bi bi-shield-check"></i> Verificada</span>
                                                @endif
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $statusClass = match($item->status) {
                                                    'pending' => 'secondary',
                                                    'processing' => 'info',
                                                    'ready' => 'warning',
                                                    'approved' => 'success',
                                                    'rejected' => 'danger',
                                                    'published' => 'primary',
                                                    default => 'secondary'
                                                };
                                                $statusLabel = match($item->status) {
                                                    'pending' => 'Pendiente',
                                                    'processing' => 'Procesando',
                                                    'ready' => 'Listo',
                                                    'approved' => 'Aprobado',
                                                    'rejected' => 'Rechazado',
                                                    'published' => 'Publicado',
                                                    default => $item->status
                                                };
                                            @endphp
                                            <span class="badge bg-{{ $statusClass }}">{{ $statusLabel }}</span>
                                            @if($item->tokens_used > 0)
                                                <br><small class="text-muted">{{ number_format($item->tokens_used) }} tokens</small>
                                            @endif
                                        </td>
                                        <td><small>{{ date('d/m/Y H:i', strtotime($item->created_at)) }}</small></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="{{ admin_url('/plugins/news-aggregator/items/' . $item->id) }}"
                                                   class="btn btn-outline-primary" title="Ver">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                @if($item->status === 'ready' || $item->status === 'approved')
                                                    <a href="{{ admin_url('/plugins/news-aggregator/items/' . $item->id . '/approve') }}"
                                                       class="btn btn-outline-success" title="Aprobar">
                                                        <i class="bi bi-check"></i>
                                                    </a>
                                                @endif
                                                @if($item->status === 'approved')
                                                    <a href="{{ admin_url('/plugins/news-aggregator/items/' . $item->id . '/publish') }}"
                                                       class="btn btn-outline-primary" title="Crear Post">
                                                        <i class="bi bi-send"></i>
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
            </form>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    document.getElementById('selectAll').addEventListener('change', function() {
                        document.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = this.checked);
                    });
                });
            </script>
        @endif
    </div>
</div>
@endsection
