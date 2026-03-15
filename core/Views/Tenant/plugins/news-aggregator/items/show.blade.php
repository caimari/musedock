@extends('layouts.app')

@section('title', 'Revisar noticia - News Aggregator')

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
                        <a href="{{ admin_url('/plugins/news-aggregator/items/' . $item->id . '/rewrite') }}" class="btn btn-info">
                            <i class="bi bi-robot"></i> Reescribir con IA
                        </a>
                    @endif
                    @if($item->status === 'ready')
                        <a href="{{ admin_url('/plugins/news-aggregator/items/' . $item->id . '/approve?next=1') }}" class="btn btn-success">
                            <i class="bi bi-check-lg"></i> Aprobar
                        </a>
                        <a href="{{ admin_url('/plugins/news-aggregator/items/' . $item->id . '/reject') }}" class="btn btn-warning">
                            <i class="bi bi-x-lg"></i> Rechazar
                        </a>
                        <a href="{{ admin_url('/plugins/news-aggregator/items/' . $item->id . '/rewrite') }}" class="btn btn-outline-info">
                            <i class="bi bi-arrow-clockwise"></i> Reescribir
                        </a>
                    @endif
                    @if($item->status === 'approved')
                        <a href="{{ admin_url('/plugins/news-aggregator/items/' . $item->id . '/publish') }}" class="btn btn-primary">
                            <i class="bi bi-send"></i> Crear Post
                        </a>
                    @endif
                    @if($item->status === 'published' && $item->created_post_id)
                        <a href="{{ admin_url('/blog/posts/' . $item->created_post_id . '/edit') }}" class="btn btn-outline-primary">
                            <i class="bi bi-pencil"></i> Editar Post
                        </a>
                    @endif
                    <a href="{{ admin_url('/plugins/news-aggregator/items') }}" class="btn btn-outline-secondary">
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
                <div class="card border-0 shadow-sm h-100">
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
            </div>

            {{-- Rewritten Content (derecha) --}}
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
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
                                    <a href="{{ admin_url('/plugins/news-aggregator/items/' . $item->id . '/rewrite') }}" class="btn btn-info">
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
                                <form action="{{ admin_url('/plugins/news-aggregator/items/' . $item->id . '/update') }}" method="POST">
                                    @csrf
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
@endsection
