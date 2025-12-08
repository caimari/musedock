@extends('layouts.app')

@section('title', $title ?? 'Buscar en Marketplace')

@section('content')
@include('partials.alerts-sweetalert2')

<div class="container-fluid py-4">
    {{-- Header --}}
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.marketplace.index') }}">Marketplace</a></li>
                    <li class="breadcrumb-item active">Búsqueda</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0">
                <i class="bi bi-search me-2"></i>
                @if($query)
                    Resultados para "{{ htmlspecialchars($query) }}"
                @else
                    Explorar Marketplace
                @endif
            </h1>
        </div>
    </div>

    <div class="row">
        {{-- Sidebar de filtros --}}
        <div class="col-lg-3 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="bi bi-funnel me-2"></i> Filtros</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('superadmin.marketplace.search') }}" method="GET">
                        {{-- Búsqueda --}}
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Buscar</label>
                            <input type="text" name="q" class="form-control" value="{{ $query }}" placeholder="Palabras clave...">
                        </div>

                        {{-- Tipo --}}
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Tipo</label>
                            <select name="type" class="form-select">
                                <option value="">Todos</option>
                                <option value="module" {{ $current_type === 'module' ? 'selected' : '' }}>Módulos</option>
                                <option value="plugin" {{ $current_type === 'plugin' ? 'selected' : '' }}>Plugins</option>
                                <option value="theme" {{ $current_type === 'theme' ? 'selected' : '' }}>Temas</option>
                            </select>
                        </div>

                        {{-- Categoría --}}
                        @if(!empty($categories))
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Categoría</label>
                            <select name="category" class="form-select">
                                <option value="">Todas</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat['slug'] }}" {{ $current_category === $cat['slug'] ? 'selected' : '' }}>
                                        {{ $cat['name'] }} ({{ $cat['count'] }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @endif

                        {{-- Ordenar --}}
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Ordenar por</label>
                            <select name="sort" class="form-select">
                                <option value="popular" {{ $current_sort === 'popular' ? 'selected' : '' }}>Más populares</option>
                                <option value="newest" {{ $current_sort === 'newest' ? 'selected' : '' }}>Más recientes</option>
                                <option value="updated" {{ $current_sort === 'updated' ? 'selected' : '' }}>Actualizados recientemente</option>
                                <option value="rating" {{ $current_sort === 'rating' ? 'selected' : '' }}>Mejor valorados</option>
                            </select>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-1"></i> Buscar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Resultados --}}
        <div class="col-lg-9">
            @php
                $items = $results['items'] ?? $results ?? [];
                $total = $results['total'] ?? count($items);
                $totalPages = $results['total_pages'] ?? 1;
            @endphp

            @if(!empty($items))
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted">{{ $total }} resultado(s) encontrados</span>
                </div>

                <div class="row g-4">
                    @foreach($items as $item)
                    <div class="col-md-6 col-xl-4">
                        @include('marketplace._item_card', ['item' => $item])
                    </div>
                    @endforeach
                </div>

                {{-- Paginación --}}
                @if($totalPages > 1)
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        @if($current_page > 1)
                            <li class="page-item">
                                <a class="page-link" href="?{{ http_build_query(array_merge($_GET, ['page' => $current_page - 1])) }}">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                        @endif

                        @for($i = 1; $i <= $totalPages; $i++)
                            <li class="page-item {{ $i === $current_page ? 'active' : '' }}">
                                <a class="page-link" href="?{{ http_build_query(array_merge($_GET, ['page' => $i])) }}">{{ $i }}</a>
                            </li>
                        @endfor

                        @if($current_page < $totalPages)
                            <li class="page-item">
                                <a class="page-link" href="?{{ http_build_query(array_merge($_GET, ['page' => $current_page + 1])) }}">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        @endif
                    </ul>
                </nav>
                @endif
            @else
                <div class="text-center py-5">
                    <i class="bi bi-search display-1 text-muted opacity-50"></i>
                    <h4 class="mt-3">No se encontraron resultados</h4>
                    <p class="text-muted">Intenta con otros términos de búsqueda o filtros diferentes.</p>
                    <a href="{{ route('superadmin.marketplace.index') }}" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left me-1"></i> Volver al Marketplace
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
