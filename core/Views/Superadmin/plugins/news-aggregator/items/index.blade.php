@extends('layouts.app')

@section('title', 'Noticias - News Aggregator')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        @include('plugins.news-aggregator._nav', ['activeTab' => 'items', 'tenantId' => $tenantId ?? 0, 'tenants' => $tenants ?? []])

        @include('partials.alerts-sweetalert2')

        {{-- Filters --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="tenant" value="{{ $tenantId }}">
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
            <form id="bulkForm" action="/musedock/news-aggregator/items/bulk" method="POST">
                @csrf
                <input type="hidden" name="tenant_id" value="{{ $tenantId }}">
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
                                    @php
                                        $isGroup = $item->_is_group ?? false;
                                        $clusterItems = $item->_cluster_items ?? [];
                                        $sourceCount = $item->_source_count ?? 1;
                                        $bestItem = $item->_best_item ?? $item;
                                        $statusClass = match($bestItem->status) {
                                            'pending' => 'secondary',
                                            'processing' => 'info',
                                            'ready' => 'warning',
                                            'approved' => 'success',
                                            'rejected' => 'danger',
                                            'published' => 'primary',
                                            default => 'secondary'
                                        };
                                        $statusLabel = match($bestItem->status) {
                                            'pending' => 'Pendiente',
                                            'processing' => 'Procesando',
                                            'ready' => 'Listo',
                                            'approved' => 'Aprobado',
                                            'rejected' => 'Rechazado',
                                            'published' => 'Publicado',
                                            default => $bestItem->status
                                        };
                                    @endphp
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="ids[]" value="{{ $bestItem->id }}" class="form-check-input item-checkbox">
                                        </td>
                                        <td>
                                            <a href="/musedock/news-aggregator/items/{{ $bestItem->id }}?tenant={{ $tenantId }}">
                                                <strong>{{ mb_strimwidth($bestItem->original_title ?? '', 0, 80, '...') }}</strong>
                                            </a>
                                            @if(!empty($bestItem->rewritten_title))
                                                <br><small class="text-success">
                                                    <i class="bi bi-check-circle"></i>
                                                    {{ mb_strimwidth($bestItem->rewritten_title ?? '', 0, 60, '...') }}
                                                </small>
                                            @endif
                                            @if(!empty($bestItem->media_keywords))
                                                <br>
                                                @foreach(array_slice(explode(',', $bestItem->media_keywords), 0, 4) as $kw)
                                                    <span class="badge bg-light text-muted border" style="font-size:0.65em;">{{ trim($kw) }}</span>
                                                @endforeach
                                            @endif
                                            {{-- Fuentes verificadas expandibles --}}
                                            @if($isGroup && $sourceCount > 1)
                                                <div class="mt-2">
                                                    <a href="javascript:void(0)" class="text-decoration-none small" onclick="document.getElementById('cluster-{{ $bestItem->id }}').classList.toggle('d-none')">
                                                        <span class="badge bg-info"><i class="bi bi-shield-check"></i> {{ $sourceCount }} fuentes verificadas</span>
                                                        <i class="bi bi-chevron-down ms-1" style="font-size:0.7em;"></i>
                                                    </a>
                                                    <div id="cluster-{{ $bestItem->id }}" class="d-none mt-2 border rounded p-2" style="background:#f8f9fa; font-size:0.8em;">
                                                        @foreach($clusterItems as $ci)
                                                            <div class="d-flex align-items-start mb-2 {{ !$loop->last ? 'pb-2 border-bottom' : '' }}">
                                                                <span class="badge bg-{{ $ci->id === $bestItem->id ? 'primary' : 'light text-dark border' }} me-2" style="font-size:0.7em; min-width: 22px;">{{ $loop->iteration }}</span>
                                                                <div class="flex-grow-1">
                                                                    <a href="/musedock/news-aggregator/items/{{ $ci->id }}?tenant={{ $tenantId }}" class="fw-semibold text-decoration-none">
                                                                        {{ mb_strimwidth($ci->original_title ?? '', 0, 90, '...') }}
                                                                    </a>
                                                                    <div class="text-muted" style="font-size:0.85em;">
                                                                        @if(!empty($ci->feed_name))
                                                                            <span><i class="bi bi-rss"></i> {{ $ci->feed_name }}</span>
                                                                        @endif
                                                                        @if(!empty($ci->original_url))
                                                                            <a href="{{ $ci->original_url }}" target="_blank" rel="noopener noreferrer" class="ms-2 text-muted">
                                                                                <i class="bi bi-box-arrow-up-right"></i> URL original
                                                                            </a>
                                                                        @endif
                                                                        @if(!empty($ci->original_author))
                                                                            <span class="ms-2"><i class="bi bi-person"></i> {{ $ci->original_author }}</span>
                                                                        @endif
                                                                        <span class="ms-2"><i class="bi bi-calendar"></i> {{ date('d/m H:i', strtotime($ci->created_at)) }}</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <small>{{ $bestItem->source_name ?? '-' }}</small>
                                            @if($isGroup && $sourceCount > 1)
                                                <br><span class="badge {{ $sourceCount >= 3 ? 'bg-success' : 'bg-info' }}" style="font-size:0.6em;">
                                                    <i class="bi bi-shield-check"></i> {{ $sourceCount }} fuentes
                                                </span>
                                            @elseif(($item->processing_type ?? 'direct') === 'verified')
                                                <br><span class="badge bg-info" style="font-size:0.6em;"><i class="bi bi-shield-check"></i> Verificada</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $statusClass }}">{{ $statusLabel }}</span>
                                            @if($bestItem->tokens_used > 0)
                                                <br><small class="text-muted">{{ number_format($bestItem->tokens_used) }} tokens</small>
                                            @endif
                                        </td>
                                        <td><small>{{ date('d/m/Y H:i', strtotime($bestItem->created_at)) }}</small></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="/musedock/news-aggregator/items/{{ $bestItem->id }}?tenant={{ $tenantId }}"
                                                   class="btn btn-outline-primary" title="Ver">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                @if($bestItem->status === 'ready' || $bestItem->status === 'approved')
                                                    <a href="/musedock/news-aggregator/items/{{ $bestItem->id }}/approve?tenant={{ $tenantId }}"
                                                       class="btn btn-outline-success" title="Aprobar">
                                                        <i class="bi bi-check"></i>
                                                    </a>
                                                @endif
                                                @if($bestItem->status === 'approved')
                                                    <a href="/musedock/news-aggregator/items/{{ $bestItem->id }}/publish?tenant={{ $tenantId }}"
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
