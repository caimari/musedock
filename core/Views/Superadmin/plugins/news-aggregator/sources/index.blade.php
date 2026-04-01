@extends('layouts.app')

@section('title', 'Fuentes - News Aggregator')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        @include('plugins.news-aggregator._nav', ['activeTab' => 'sources', 'tenantId' => $tenantId ?? 0, 'tenants' => $tenants ?? []])

        @include('partials.alerts-sweetalert2')

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Fuentes de noticias</h4>
            <a href="/musedock/news-aggregator/sources/create?tenant={{ $tenantId }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Añadir fuente
            </a>
        </div>

        @if(empty($sources))
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-rss fs-1 text-muted"></i>
                    <p class="text-muted mt-3 mb-3">No hay fuentes configuradas.</p>
                    <a href="/musedock/news-aggregator/sources/create?tenant={{ $tenantId }}" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Añadir fuente
                    </a>
                </div>
            </div>
        @else
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Estado</th>
                                <th>Último fetch</th>
                                <th>Artículos</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sources as $source)
                                <tr>
                                    <td>
                                        <strong>{{ $source->name }}</strong>
                                        @if(($source->processing_type ?? 'direct') === 'verified')
                                            <span class="badge text-dark ms-1" style="background-color:#d0e8ff;" title="Fuente verificada: compara múltiples medios"><i class="bi bi-shield-check"></i> Verificada</span>
                                        @endif
                                        @if(!empty($source->keywords))
                                            <br><small class="text-muted">{{ $source->keywords }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $typeLabel = match($source->source_type) {
                                                'rss' => 'RSS/Atom',
                                                'newsapi' => 'NewsAPI',
                                                'gnews' => 'GNews',
                                                'mediastack' => 'MediaStack',
                                                default => $source->source_type
                                            };
                                        @endphp
                                        <span class="badge bg-secondary">{{ $typeLabel }}</span>
                                    </td>
                                    <td>
                                        @if($source->enabled)
                                            <span class="badge bg-success">Activa</span>
                                        @else
                                            <span class="badge bg-secondary">Inactiva</span>
                                        @endif
                                        @if(!empty($source->fetch_error))
                                            <br><small class="text-danger" title="{{ $source->fetch_error }}">Error</small>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $source->last_fetch_at ? date('d/m/Y H:i', strtotime($source->last_fetch_at)) : '-' }}
                                    </td>
                                    <td>{{ $source->last_fetch_count ?? 0 }}</td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="/musedock/news-aggregator/sources/{{ $source->id }}/fetch?tenant={{ $tenantId }}"
                                               class="btn btn-sm btn-outline-success" title="Fetch ahora">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </a>
                                            <a href="/musedock/news-aggregator/sources/{{ $source->id }}/edit?tenant={{ $tenantId }}"
                                               class="btn btn-sm btn-outline-primary" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form action="/musedock/news-aggregator/sources/{{ $source->id }}/delete"
                                                  method="POST" class="d-inline"
                                                  onsubmit="return confirm('¿Estás seguro de eliminar esta fuente?')">
                                                @csrf
                                                <input type="hidden" name="tenant_id" value="{{ $tenantId }}">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
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
