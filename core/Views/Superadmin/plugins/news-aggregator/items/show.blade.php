@extends('layouts.app')

@section('title', 'Revisar noticia - News Aggregator')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        @include('plugins.news-aggregator._nav', ['activeTab' => 'items', 'tenantId' => $tenantId ?? 0, 'tenants' => $tenants ?? []])

        @include('partials.alerts-sweetalert2')

        {{-- Status and Actions Bar --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center flex-wrap gap-2">
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
                    <span class="badge bg-{{ $statusClass }} fs-6">{{ $statusLabel }}</span>

                    {{-- Indicador de fuentes --}}
                    @php
                        $csData = !empty($item->cluster_sources) ? json_decode($item->cluster_sources, true) : [];
                        $sourceCount = count($csData) > 1 ? count($csData) : ($cluster->source_count ?? 1);
                        $clusterBadge = $sourceCount >= 3 ? 'bg-success' : ($sourceCount >= 2 ? 'bg-info' : 'bg-warning text-dark');
                        $clusterLabel = $sourceCount >= 3 ? 'Confirmado' : ($sourceCount >= 2 ? $sourceCount . ' fuentes' : 'Fuente única');
                    @endphp
                    @if($sourceCount >= 2)
                        <span class="badge {{ $clusterBadge }}">
                            <i class="bi bi-shield-check"></i> {{ $sourceCount }} fuentes — {{ $clusterLabel }}
                        </span>
                    @else
                        <span class="badge bg-warning text-dark">
                            <i class="bi bi-exclamation-triangle"></i> Fuente única
                        </span>
                    @endif

                    <span class="text-muted">
                        Fuente: <strong>{{ $item->source_name ?? '-' }}</strong>
                    </span>
                    @if($item->tokens_used > 0)
                        <span class="text-muted">
                            Tokens: <strong>{{ number_format($item->tokens_used) }}</strong>
                        </span>
                    @endif
                </div>
                <div class="btn-group">
                    @if($item->status === 'pending')
                        <a href="/musedock/news-aggregator/items/{{ $item->id }}/rewrite?tenant={{ $tenantId }}" class="btn btn-info">
                            <i class="bi bi-robot"></i> Reescribir con IA
                        </a>
                    @endif
                    @if($item->status === 'ready')
                        <a href="/musedock/news-aggregator/items/{{ $item->id }}/approve?tenant={{ $tenantId }}&next=1" class="btn btn-success">
                            <i class="bi bi-check-lg"></i> Aprobar
                        </a>
                        <a href="/musedock/news-aggregator/items/{{ $item->id }}/reject?tenant={{ $tenantId }}" class="btn btn-warning">
                            <i class="bi bi-x-lg"></i> Rechazar
                        </a>
                        <a href="/musedock/news-aggregator/items/{{ $item->id }}/rewrite?tenant={{ $tenantId }}" class="btn btn-outline-info">
                            <i class="bi bi-arrow-clockwise"></i> Reescribir
                        </a>
                    @endif
                    @if($item->status === 'approved')
                        <a href="/musedock/news-aggregator/items/{{ $item->id }}/publish?tenant={{ $tenantId }}" class="btn btn-primary">
                            <i class="bi bi-send"></i> Crear Post
                        </a>
                    @endif
                    @if($item->status === 'published' && $item->created_post_id)
                        <a href="/musedock/blog/posts/{{ $item->created_post_id }}/edit" class="btn btn-outline-primary">
                            <i class="bi bi-pencil"></i> Editar Post
                        </a>
                    @endif
                    <a href="/musedock/news-aggregator/items?tenant={{ $tenantId }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Volver
                    </a>
                </div>
            </div>
        </div>

        {{-- Detector de similitud --}}
        @if(!empty($item->rewritten_content) && !empty($item->original_content))
            @php
                $originalText = strip_tags($item->original_content);
                $rewrittenText = strip_tags($item->rewritten_content);
                similar_text($originalText, $rewrittenText, $similarityPercent);
                $similarityPercent = round($similarityPercent);
                $similarityClass = $similarityPercent > 30 ? 'danger' : ($similarityPercent > 20 ? 'warning' : 'success');
            @endphp
            <div class="alert alert-{{ $similarityClass }} mb-4 d-flex align-items-center">
                <i class="bi bi-{{ $similarityPercent > 30 ? 'exclamation-triangle' : 'check-circle' }} me-2 fs-5"></i>
                <div>
                    <strong>Similitud texto:</strong> {{ $similarityPercent }}%
                    @if($similarityPercent > 30)
                        — <span class="text-danger">El texto reescrito es demasiado similar al original. Recomendable reescribir de nuevo.</span>
                    @elseif($similarityPercent > 20)
                        — Similitud moderada. Revise el contenido antes de aprobar.
                    @else
                        — Texto suficientemente diferenciado del original.
                    @endif
                </div>
            </div>
        @endif

        <div class="row">
            {{-- Original Content (izquierda) --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="bi bi-file-text"></i> Fuente original</h5>
                    </div>
                    <div class="card-body">
                        <h4>{{ $item->original_title }}</h4>

                        @if(!empty($item->original_author))
                            <p class="text-muted mb-2">
                                <i class="bi bi-person"></i> {{ $item->original_author }}
                            </p>
                        @endif

                        @if(!empty($item->original_published_at))
                            <p class="text-muted mb-2">
                                <i class="bi bi-calendar"></i> {{ date('d/m/Y H:i', strtotime($item->original_published_at)) }}
                            </p>
                        @endif

                        <p class="mb-3">
                            <a href="{{ $item->original_url }}" target="_blank" class="text-decoration-none">
                                <i class="bi bi-link-45deg"></i> Ver original en {{ $item->source_name ?? 'fuente' }}
                            </a>
                        </p>

                        @if(!empty($item->original_image_url))
                            <div class="mb-3">
                                <img src="{{ $item->original_image_url }}" alt="Original image"
                                     class="img-fluid rounded" style="max-height: 200px;"
                                     onerror="this.style.display='none'">
                            </div>
                        @endif

                        <div class="original-content" style="max-height: 400px; overflow-y: auto;">
                            {!! $item->original_content !!}
                        </div>
                    </div>
                </div>

                {{-- Fuentes verificadas del evento (desde cluster_sources JSON) --}}
                @php
                    $clusterSourcesList = !empty($item->cluster_sources) ? json_decode($item->cluster_sources, true) : [];
                @endphp
                @if(count($clusterSourcesList) > 1)
                    <div class="card border-0 shadow-sm mt-3">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">
                                <i class="bi bi-newspaper"></i>
                                Fuentes que cubren este evento ({{ count($clusterSourcesList) }})
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                @foreach($clusterSourcesList as $cs)
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <strong>{{ $cs['feed_name'] ?? 'Fuente' }}</strong>
                                                <br>
                                                <small>{{ $cs['title'] ?? '' }}</small>
                                            </div>
                                            @if(!empty($cs['url']))
                                                <a href="{{ $cs['url'] }}" target="_blank" class="btn btn-sm btn-outline-secondary align-self-center">
                                                    <i class="bi bi-box-arrow-up-right"></i>
                                                </a>
                                            @endif
                                        </div>
                                        @if(!empty($cs['content']))
                                            <div class="mt-2 small text-muted" style="max-height: 100px; overflow-y: auto;">
                                                {!! $cs['content'] !!}
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @elseif(!empty($cluster) && !empty($cluster->items) && count($cluster->items) > 1)
                    {{-- Fallback: cluster items de BD (para items anteriores sin cluster_sources) --}}
                    <div class="card border-0 shadow-sm mt-3">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">
                                <i class="bi bi-newspaper"></i>
                                Otras fuentes del mismo evento ({{ count($cluster->items) - 1 }} más)
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                @foreach($cluster->items as $clusterItem)
                                    @if($clusterItem->id !== $item->id)
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <strong>{{ $clusterItem->source_name ?? 'Fuente' }}</strong>
                                                    <br>
                                                    <small>{{ $clusterItem->original_title }}</small>
                                                </div>
                                                <a href="{{ $clusterItem->original_url }}" target="_blank" class="btn btn-sm btn-outline-secondary align-self-center">
                                                    <i class="bi bi-box-arrow-up-right"></i>
                                                </a>
                                            </div>
                                            @if(!empty($clusterItem->original_content))
                                                <div class="mt-2 small text-muted" style="max-height: 100px; overflow-y: auto;">
                                                    {!! $clusterItem->original_content !!}
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Contexto de la fuente original --}}
                <div class="card border-0 shadow-sm mt-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center"
                         data-bs-toggle="collapse" data-bs-target="#sourceContextSection" role="button">
                        <h6 class="mb-0">
                            <i class="bi bi-file-earmark-text"></i> Contexto de la fuente original
                            @if(!empty($item->source_context))
                                <span class="badge bg-success ms-1">Extraído</span>
                            @endif
                            @if(!empty($item->source_context_included) && $item->source_context_included)
                                <span class="badge bg-primary ms-1">Incluido en IA</span>
                            @endif
                        </h6>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div id="sourceContextSection" class="collapse {{ !empty($item->source_context) ? 'show' : '' }}">
                        <div class="card-body">
                            <p class="text-muted small mb-2">
                                Extrae el texto completo del artículo original para dar más contexto a la IA al reescribir.
                            </p>

                            <div id="sourceContextContent">
                                @if(!empty($item->source_context))
                                    <div class="border rounded p-3 bg-light mb-3" style="max-height: 300px; overflow-y: auto; white-space: pre-wrap; font-size: 0.85rem;">{{ $item->source_context }}</div>
                                @else
                                    <div class="text-center py-3 text-muted" id="sourceContextEmpty">
                                        <i class="bi bi-file-earmark-text fs-3"></i>
                                        <p class="mt-2 mb-0">No se ha extraído contexto todavía.</p>
                                    </div>
                                @endif
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btnExtractContext" onclick="extractContext({{ $item->id }})">
                                    <i class="bi bi-download"></i>
                                    {{ !empty($item->source_context) ? 'Re-extraer contexto' : 'Extraer contexto' }}
                                </button>

                                @if(!empty($item->source_context))
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="sourceContextIncluded"
                                           {{ !empty($item->source_context_included) && $item->source_context_included ? 'checked' : '' }}
                                           onchange="toggleSourceContext({{ $item->id }}, this.checked)">
                                    <label class="form-check-label" for="sourceContextIncluded">
                                        <strong>Incluir en reescritura IA</strong>
                                    </label>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Investigación externa --}}
                <div class="card border-0 shadow-sm mt-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center"
                         data-bs-toggle="collapse" data-bs-target="#researchSection" role="button">
                        <h6 class="mb-0">
                            <i class="bi bi-search"></i> Investigación externa
                            @php
                                $researchResults = !empty($item->research_context) ? json_decode($item->research_context, true) : [];
                                $researchIncluded = !empty($item->research_context_included) ? json_decode($item->research_context_included, true) : [];
                            @endphp
                            @if(!empty($researchResults))
                                <span class="badge bg-success ms-1">{{ count($researchResults) }} resultados</span>
                            @endif
                            @if(!empty($researchIncluded))
                                <span class="badge bg-primary ms-1">{{ count($researchIncluded) }} incluidos</span>
                            @endif
                        </h6>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div id="researchSection" class="collapse {{ !empty($researchResults) ? 'show' : '' }}">
                        <div class="card-body">
                            <p class="text-muted small mb-2">
                                Busca noticias relacionadas en múltiples servicios de noticias para enriquecer la reescritura.
                            </p>

                            <div id="researchResults">
                                @if(!empty($researchResults))
                                    <div class="list-group mb-3">
                                        @foreach($researchResults as $rr)
                                            <div class="list-group-item">
                                                <div class="d-flex align-items-start">
                                                    <div class="form-check me-2 mt-1">
                                                        <input class="form-check-input research-check" type="checkbox"
                                                               value="{{ $rr['id'] }}"
                                                               {{ in_array($rr['id'], $researchIncluded) ? 'checked' : '' }}
                                                               onchange="updateResearchInclusion({{ $item->id }})">
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex justify-content-between">
                                                            <strong class="small">{{ $rr['title'] }}</strong>
                                                            @if(!empty($rr['url']))
                                                                <a href="{{ $rr['url'] }}" target="_blank" class="btn btn-sm btn-link p-0">
                                                                    <i class="bi bi-box-arrow-up-right"></i>
                                                                </a>
                                                            @endif
                                                        </div>
                                                        <small class="text-muted d-block">{{ $rr['source'] ?? '' }}{{ !empty($rr['published_at']) ? ' — ' . date('d/m/Y', strtotime($rr['published_at'])) : '' }}</small>
                                                        @if(!empty($rr['excerpt']))
                                                            <small class="text-muted d-block mt-1" style="max-height: 60px; overflow: hidden;">{{ $rr['excerpt'] }}</small>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="text-center py-3 text-muted" id="researchEmpty">
                                        <i class="bi bi-search fs-3"></i>
                                        <p class="mt-2 mb-0">No se ha investigado todavía.</p>
                                    </div>
                                @endif
                            </div>

                            <button type="button" class="btn btn-sm btn-outline-info" id="btnResearch" onclick="doResearch({{ $item->id }})">
                                <i class="bi bi-search"></i>
                                {{ !empty($researchResults) ? 'Re-investigar' : 'Investigar' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Rewritten Content (derecha) --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-robot"></i> Texto generado</h5>
                        @if(!empty($item->rewritten_title))
                            <button type="button" class="btn btn-sm btn-outline-light" data-bs-toggle="collapse" data-bs-target="#editForm">
                                <i class="bi bi-pencil"></i> Editar
                            </button>
                        @endif
                    </div>
                    <div class="card-body">
                        @if(empty($item->rewritten_title))
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-robot fs-1"></i>
                                <p class="mt-3">No hay contenido reescrito todavía.</p>
                                @if($item->status === 'pending')
                                    <a href="/musedock/news-aggregator/items/{{ $item->id }}/rewrite?tenant={{ $tenantId }}" class="btn btn-info">
                                        <i class="bi bi-robot"></i> Reescribir con IA
                                    </a>
                                @endif
                            </div>
                        @else
                            {{-- View Mode --}}
                            <div id="viewContent">
                                <h4>{{ $item->rewritten_title }}</h4>

                                @if(!empty($item->rewritten_excerpt))
                                    <p class="lead text-muted">{{ $item->rewritten_excerpt }}</p>
                                @endif

                                <div class="rewritten-content" style="max-height: 400px; overflow-y: auto;">
                                    {!! $item->rewritten_content !!}
                                </div>
                            </div>

                            {{-- Edit Mode --}}
                            <div id="editForm" class="collapse">
                                <form action="/musedock/news-aggregator/items/{{ $item->id }}/update" method="POST">
                                    @csrf
                                    <input type="hidden" name="tenant_id" value="{{ $tenantId }}">
                                    <div class="mb-3">
                                        <label class="form-label">Título</label>
                                        <input type="text" name="rewritten_title" class="form-control"
                                               value="{{ $item->rewritten_title }}">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Extracto</label>
                                        <textarea name="rewritten_excerpt" class="form-control" rows="2">{{ $item->rewritten_excerpt ?? '' }}</textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Contenido</label>
                                        <textarea name="rewritten_content" class="form-control" rows="10">{{ $item->rewritten_content }}</textarea>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-success">
                                            <i class="bi bi-check-lg"></i> Guardar cambios
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#editForm">
                                            Cancelar
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Metadata --}}
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Metadatos</h5>
            </div>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-md-3">
                        <strong>ID:</strong> {{ $item->id }}
                    </div>
                    <div class="col-md-3">
                        <strong>Capturado:</strong> {{ date('d/m/Y H:i', strtotime($item->created_at)) }}
                    </div>
                    <div class="col-md-3">
                        <strong>Procesado:</strong> {{ $item->processed_at ? date('d/m/Y H:i', strtotime($item->processed_at)) : '-' }}
                    </div>
                    <div class="col-md-3">
                        <strong>Revisado:</strong> {{ $item->reviewed_at ? date('d/m/Y H:i', strtotime($item->reviewed_at)) : '-' }}
                    </div>
                </div>
                @php
                    $tags = !empty($item->source_tags) ? json_decode($item->source_tags, true) : [];
                    if (empty($tags) && !empty($item->media_keywords)) {
                        $tags = array_map('trim', explode(',', $item->media_keywords));
                    }
                @endphp
                @if(!empty($tags))
                    <div class="mt-2">
                        <strong>Tags del medio:</strong>
                        @foreach($tags as $tag)
                            <span class="badge bg-light text-dark border me-1">{{ $tag }}</span>
                        @endforeach
                    </div>
                @endif
                @if(!empty($item->ai_categories))
                    <div class="mt-2">
                        <strong>Categorías IA:</strong>
                        @foreach(json_decode($item->ai_categories, true) ?? [] as $cat)
                            <span class="badge bg-primary me-1">{{ $cat }}</span>
                        @endforeach
                    </div>
                @endif
                @if(!empty($item->ai_tags))
                    <div class="mt-2">
                        <strong>Tags IA:</strong>
                        @foreach(json_decode($item->ai_tags, true) ?? [] as $tag)
                            <span class="badge bg-info me-1">{{ $tag }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
.original-content img,
.rewritten-content img {
    max-width: 100%;
    height: auto;
}
</style>

<script>
/**
 * Extraer contexto de la fuente original
 */
function extractContext(itemId) {
    const btn = document.getElementById('btnExtractContext');
    const container = document.getElementById('sourceContextContent');
    const origHtml = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Extrayendo...';

    fetch('/musedock/news-aggregator/items/' + itemId + '/extract-context', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: '_token={{ csrf_token() }}&tenant_id={{ $tenantId }}'
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        if (data.success) {
            container.innerHTML = '<div class="border rounded p-3 bg-light mb-3" style="max-height: 300px; overflow-y: auto; white-space: pre-wrap; font-size: 0.85rem;">'
                + escapeHtml(data.context) + '</div>';
            btn.innerHTML = '<i class="bi bi-download"></i> Re-extraer contexto';

            // Mostrar el switch de inclusión si no existía
            if (!document.getElementById('sourceContextIncluded')) {
                const switchHtml = '<div class="form-check form-switch">'
                    + '<input class="form-check-input" type="checkbox" id="sourceContextIncluded" onchange="toggleSourceContext(' + itemId + ', this.checked)">'
                    + '<label class="form-check-label" for="sourceContextIncluded"><strong>Incluir en reescritura IA</strong></label>'
                    + '</div>';
                btn.parentElement.insertAdjacentHTML('beforeend', switchHtml);
            }

            if (data.cached) {
                showToast('Contexto cargado desde caché', 'info');
            } else {
                showToast('Contexto extraído correctamente', 'success');
            }
        } else {
            btn.innerHTML = origHtml;
            showToast(data.error || 'Error al extraer contexto', 'danger');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        showToast('Error de conexión', 'danger');
    });
}

/**
 * Toggle inclusión de contexto de fuente
 */
function toggleSourceContext(itemId, included) {
    fetch('/musedock/news-aggregator/items/' + itemId + '/toggle-source-context', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: '_token={{ csrf_token() }}&tenant_id={{ $tenantId }}&included=' + (included ? 'true' : 'false')
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(included ? 'Contexto incluido en reescritura IA' : 'Contexto excluido de reescritura IA', 'success');
        } else {
            showToast(data.error || 'Error', 'danger');
        }
    })
    .catch(() => showToast('Error de conexión', 'danger'));
}

/**
 * Investigar noticias relacionadas
 */
function doResearch(itemId) {
    const btn = document.getElementById('btnResearch');
    const container = document.getElementById('researchResults');
    const origHtml = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Investigando...';

    fetch('/musedock/news-aggregator/items/' + itemId + '/research', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: '_token={{ csrf_token() }}&tenant_id={{ $tenantId }}'
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        if (data.success && data.results && data.results.length > 0) {
            let html = '<div class="list-group mb-3">';
            data.results.forEach(function(rr) {
                const pubDate = rr.published_at ? ' — ' + new Date(rr.published_at).toLocaleDateString('es-ES') : '';
                html += '<div class="list-group-item">'
                    + '<div class="d-flex align-items-start">'
                    + '<div class="form-check me-2 mt-1">'
                    + '<input class="form-check-input research-check" type="checkbox" value="' + escapeHtml(rr.id) + '" onchange="updateResearchInclusion(' + itemId + ')">'
                    + '</div>'
                    + '<div class="flex-grow-1">'
                    + '<div class="d-flex justify-content-between">'
                    + '<strong class="small">' + escapeHtml(rr.title) + '</strong>';
                if (rr.url) {
                    html += '<a href="' + escapeHtml(rr.url) + '" target="_blank" class="btn btn-sm btn-link p-0"><i class="bi bi-box-arrow-up-right"></i></a>';
                }
                html += '</div>'
                    + '<small class="text-muted d-block">' + escapeHtml(rr.source || '') + pubDate + '</small>';
                if (rr.excerpt) {
                    html += '<small class="text-muted d-block mt-1" style="max-height: 60px; overflow: hidden;">' + escapeHtml(rr.excerpt) + '</small>';
                }
                html += '</div></div></div>';
            });
            html += '</div>';
            container.innerHTML = html;
            btn.innerHTML = '<i class="bi bi-search"></i> Re-investigar';

            const provMsg = data.cached ? 'Resultados cargados desde caché' : 'Encontrados ' + data.results.length + ' resultados vía ' + (data.provider || 'API');
            showToast(provMsg, 'success');
        } else {
            btn.innerHTML = origHtml;
            showToast(data.error || 'No se encontraron resultados', 'warning');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        showToast('Error de conexión', 'danger');
    });
}

/**
 * Actualizar IDs de resultados de investigación incluidos
 */
function updateResearchInclusion(itemId) {
    const checks = document.querySelectorAll('.research-check:checked');
    const ids = Array.from(checks).map(c => c.value);

    fetch('/musedock/news-aggregator/items/' + itemId + '/toggle-research-context', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: '_token={{ csrf_token() }}&tenant_id={{ $tenantId }}&included_ids=' + encodeURIComponent(JSON.stringify(ids))
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(ids.length + ' fragmento(s) seleccionado(s) para incluir en IA', 'success');
        }
    })
    .catch(() => {});
}

/**
 * Escapar HTML
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

/**
 * Toast de notificaciones con SweetAlert2
 */
function showToast(message, type) {
    const iconMap = { success: 'success', danger: 'error', warning: 'warning', info: 'info' };
    const timerMap = { success: 3000, danger: 5000, warning: 4000, info: 3500 };
    const icon = iconMap[type] || type;
    const timer = timerMap[type] || 3000;

    Swal.fire({
        icon: icon,
        title: message,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: timer,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });
}
</script>
@endsection
