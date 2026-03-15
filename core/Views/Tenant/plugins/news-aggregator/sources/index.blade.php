@extends('layouts.app')

@section('title', 'Fuentes - News Aggregator')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        @include('plugins.news-aggregator._nav', ['activeTab' => 'sources'])

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

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Fuentes de noticias</h4>
            <a href="{{ admin_url('/plugins/news-aggregator/sources/create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Añadir fuente
            </a>
        </div>

        @if(empty($sources))
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-rss fs-1 text-muted"></i>
                    <p class="text-muted mt-3 mb-3">No hay fuentes configuradas.</p>
                    <a href="{{ admin_url('/plugins/news-aggregator/sources/create') }}" class="btn btn-primary">
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
                                            <span class="badge bg-info ms-1" title="Fuente verificada: compara múltiples medios"><i class="bi bi-shield-check"></i> Verificada</span>
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
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ admin_url('/plugins/news-aggregator/sources/' . $source->id . '/fetch') }}"
                                               class="btn btn-outline-success" title="Fetch ahora">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </a>
                                            <a href="{{ admin_url('/plugins/news-aggregator/sources/' . $source->id . '/edit') }}"
                                               class="btn btn-outline-primary" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form action="{{ admin_url('/plugins/news-aggregator/sources/' . $source->id . '/delete') }}"
                                                  method="POST" class="d-inline"
                                                  onsubmit="return confirm('¿Estás seguro de eliminar esta fuente?')">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-danger" title="Eliminar">
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
