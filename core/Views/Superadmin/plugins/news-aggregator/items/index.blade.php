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
                <form id="filterForm" method="GET" class="row g-3 align-items-end">
                    <input type="hidden" name="tenant" value="{{ $tenantId }}">
                    <div class="col-md-3">
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
                    <div class="col-md-3">
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
                    <div class="col-md-2">
                        <label class="form-label">Tipo</label>
                        <select name="verified" class="form-select" onchange="this.form.submit()">
                            <option value="">Todas</option>
                            <option value="1" {{ !empty($filters['verified']) ? 'selected' : '' }}>Verificadas</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Por página</label>
                        <select name="per_page" class="form-select" onchange="this.form.submit()">
                            @foreach([50, 100, 500, 1000] as $pp)
                                <option value="{{ $pp }}" {{ $perPage == $pp ? 'selected' : '' }}>{{ $pp }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" id="infiniteScrollToggle" {{ !empty($_GET['scroll']) ? 'checked' : '' }}>
                            <label class="form-check-label small" for="infiniteScrollToggle">Scroll infinito</label>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Total count --}}
        <div class="d-flex justify-content-between align-items-center mb-2">
            <small class="text-muted">
                {{ $totalItems }} items en total
                @if($totalPages > 1)
                    — Página {{ $page }} de {{ $totalPages }}
                @endif
            </small>
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
                        <div class="d-flex gap-2">
                            <button type="submit" name="action" value="approve" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-check-lg"></i> Aprobar
                            </button>
                            <button type="submit" name="action" value="reject" class="btn btn-sm btn-outline-warning">
                                <i class="bi bi-x-lg"></i> Rechazar
                            </button>
                            <button type="submit" name="action" value="delete" class="btn btn-sm btn-outline-danger"
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
                                            @if(($item->processing_type ?? 'direct') === 'verified' && $sourceCount <= 1)
                                                <span class="badge text-dark ms-1" style="font-size:0.6em; vertical-align: middle; background-color:#d0e8ff;">
                                                    <i class="bi bi-shield-check"></i> Verificada
                                                </span>
                                            @elseif($isGroup && $sourceCount > 1)
                                                <span class="badge {{ $sourceCount >= 3 ? 'bg-success' : 'text-dark' }} ms-1" style="font-size:0.6em; vertical-align: middle;{{ $sourceCount < 3 ? ' background-color:#d0e8ff;' : '' }}">
                                                    <i class="bi bi-shield-fill-check"></i> {{ $sourceCount }} fuentes
                                                </span>
                                            @endif
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
                                                        <span class="badge text-dark" style="background-color:#d0e8ff;"><i class="bi bi-shield-check"></i> {{ $sourceCount }} fuentes verificadas</span>
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
                                                <br><span class="badge {{ $sourceCount >= 3 ? 'bg-success' : 'text-dark' }}" style="font-size:0.6em;{{ $sourceCount < 3 ? ' background-color:#d0e8ff;' : '' }}">
                                                    <i class="bi bi-shield-check"></i> {{ $sourceCount }} fuentes
                                                </span>
                                            @elseif(($item->processing_type ?? 'direct') === 'verified')
                                                <br><span class="badge text-dark" style="font-size:0.6em; background-color:#d0e8ff;"><i class="bi bi-shield-check"></i> Verificada</span>
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

            {{-- Pagination (hidden in infinite scroll mode) --}}
            @if($totalPages > 1)
                @php
                    $queryParams = ['tenant' => $tenantId];
                    if (!empty($filters['status'])) $queryParams['status'] = $filters['status'];
                    if (!empty($filters['source_id'])) $queryParams['source_id'] = $filters['source_id'];
                    if (!empty($filters['verified'])) $queryParams['verified'] = 1;
                    if ($perPage != 50) $queryParams['per_page'] = $perPage;
                @endphp
                <nav class="mt-3" id="paginationNav">
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <li class="page-item {{ $page <= 1 ? 'disabled' : '' }}">
                            <a class="page-link" href="?{{ http_build_query(array_merge($queryParams, ['page' => $page - 1])) }}">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>

                        @php
                            $start = max(1, $page - 3);
                            $end = min($totalPages, $page + 3);
                        @endphp

                        @if($start > 1)
                            <li class="page-item">
                                <a class="page-link" href="?{{ http_build_query(array_merge($queryParams, ['page' => 1])) }}">1</a>
                            </li>
                            @if($start > 2)
                                <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                            @endif
                        @endif

                        @for($p = $start; $p <= $end; $p++)
                            <li class="page-item {{ $p === $page ? 'active' : '' }}">
                                <a class="page-link" href="?{{ http_build_query(array_merge($queryParams, ['page' => $p])) }}">{{ $p }}</a>
                            </li>
                        @endfor

                        @if($end < $totalPages)
                            @if($end < $totalPages - 1)
                                <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                            @endif
                            <li class="page-item">
                                <a class="page-link" href="?{{ http_build_query(array_merge($queryParams, ['page' => $totalPages])) }}">{{ $totalPages }}</a>
                            </li>
                        @endif

                        <li class="page-item {{ $page >= $totalPages ? 'disabled' : '' }}">
                            <a class="page-link" href="?{{ http_build_query(array_merge($queryParams, ['page' => $page + 1])) }}">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            @endif

            {{-- Infinite scroll loader --}}
            <div id="scrollLoader" class="text-center py-4 d-none">
                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                <span class="ms-2 text-muted">Cargando más noticias...</span>
            </div>
            <div id="scrollEnd" class="text-center py-3 d-none">
                <small class="text-muted">No hay más noticias.</small>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Select all checkbox
                    document.getElementById('selectAll').addEventListener('change', function() {
                        document.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = this.checked);
                    });

                    // Infinite scroll
                    const toggle = document.getElementById('infiniteScrollToggle');
                    const paginationNav = document.getElementById('paginationNav');
                    const scrollLoader = document.getElementById('scrollLoader');
                    const scrollEnd = document.getElementById('scrollEnd');
                    const tbody = document.querySelector('#bulkForm table tbody');
                    let currentPage = {{ $page }};
                    const totalPages = {{ $totalPages }};
                    let loading = false;
                    let scrollActive = toggle.checked;

                    function buildUrl(page) {
                        const params = new URLSearchParams(window.location.search);
                        params.set('page', page);
                        params.set('scroll', '1');
                        return window.location.pathname + '?' + params.toString();
                    }

                    function updateUI() {
                        if (scrollActive) {
                            if (paginationNav) paginationNav.classList.add('d-none');
                            if (currentPage >= totalPages) {
                                scrollEnd.classList.remove('d-none');
                            }
                        } else {
                            if (paginationNav) paginationNav.classList.remove('d-none');
                            scrollEnd.classList.add('d-none');
                            scrollLoader.classList.add('d-none');
                        }
                    }

                    async function loadMore() {
                        if (loading || currentPage >= totalPages || !scrollActive) return;
                        loading = true;
                        scrollLoader.classList.remove('d-none');

                        try {
                            const resp = await fetch(buildUrl(currentPage + 1));
                            const html = await resp.text();
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            const rows = doc.querySelectorAll('#bulkForm table tbody tr');
                            if (rows.length > 0) {
                                rows.forEach(row => tbody.appendChild(row));
                                currentPage++;
                            }
                            if (currentPage >= totalPages) {
                                scrollEnd.classList.remove('d-none');
                            }
                        } catch (e) {
                            console.error('Error loading more items:', e);
                        } finally {
                            loading = false;
                            scrollLoader.classList.add('d-none');
                        }
                    }

                    toggle.addEventListener('change', function() {
                        scrollActive = this.checked;
                        updateUI();
                    });

                    window.addEventListener('scroll', function() {
                        if (!scrollActive) return;
                        const scrollBottom = window.innerHeight + window.scrollY;
                        const docHeight = document.documentElement.scrollHeight;
                        if (scrollBottom >= docHeight - 400) {
                            loadMore();
                        }
                    });

                    updateUI();
                });
            </script>
        @endif
    </div>
</div>
@endsection
