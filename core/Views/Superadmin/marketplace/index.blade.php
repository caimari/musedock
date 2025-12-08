@extends('layouts.app')

@section('title', $title ?? 'Marketplace')

@section('content')
@include('partials.alerts-sweetalert2')

<div class="container-fluid py-4">
    {{-- Header --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="bi bi-shop me-2"></i>
                        Marketplace
                    </h1>
                    <p class="text-muted mb-0">Descubre e instala módulos, plugins y temas para tu CMS</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('superadmin.marketplace.installed') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-box-seam me-1"></i> Mis Instalaciones
                    </a>
                    <a href="{{ route('superadmin.marketplace.developer') }}" class="btn btn-outline-primary">
                        <i class="bi bi-code-slash me-1"></i> Desarrolladores
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Barra de búsqueda --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body py-5">
                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            <h2 class="text-white text-center mb-4">
                                <i class="bi bi-search me-2"></i>
                                ¿Qué estás buscando?
                            </h2>
                            <form action="{{ route('superadmin.marketplace.search') }}" method="GET">
                                <div class="input-group input-group-lg shadow">
                                    <input type="text" name="q" class="form-control border-0" placeholder="Buscar módulos, plugins, temas..." autocomplete="off">
                                    <select name="type" class="form-select border-0" style="max-width: 160px;">
                                        <option value="">Todo</option>
                                        <option value="module" {{ $current_type === 'module' ? 'selected' : '' }}>Módulos</option>
                                        <option value="plugin" {{ $current_type === 'plugin' ? 'selected' : '' }}>Plugins</option>
                                        <option value="theme" {{ $current_type === 'theme' ? 'selected' : '' }}>Temas</option>
                                    </select>
                                    <button type="submit" class="btn btn-warning px-4">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros por tipo --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="btn-group" role="group">
                <a href="{{ route('superadmin.marketplace.index') }}" class="btn {{ !$current_type ? 'btn-primary' : 'btn-outline-primary' }}">
                    <i class="bi bi-grid-3x3-gap me-1"></i> Todos
                </a>
                <a href="{{ route('superadmin.marketplace.index') }}?type=module" class="btn {{ $current_type === 'module' ? 'btn-primary' : 'btn-outline-primary' }}">
                    <i class="bi bi-puzzle me-1"></i> Módulos
                </a>
                <a href="{{ route('superadmin.marketplace.index') }}?type=plugin" class="btn {{ $current_type === 'plugin' ? 'btn-primary' : 'btn-outline-primary' }}">
                    <i class="bi bi-plug me-1"></i> Plugins
                </a>
                <a href="{{ route('superadmin.marketplace.index') }}?type=theme" class="btn {{ $current_type === 'theme' ? 'btn-primary' : 'btn-outline-primary' }}">
                    <i class="bi bi-palette me-1"></i> Temas
                </a>
            </div>
        </div>
    </div>

    {{-- Items Destacados --}}
    @if(!empty($featured))
    <div class="row mb-5">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">
                    <i class="bi bi-star-fill text-warning me-2"></i>
                    Destacados
                </h4>
            </div>
            <div class="row g-4">
                @foreach($featured as $item)
                <div class="col-md-6 col-lg-4">
                    @include('marketplace._item_card', ['item' => $item])
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Items Populares --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">
                    <i class="bi bi-fire text-danger me-2"></i>
                    Populares
                </h4>
                <a href="{{ route('superadmin.marketplace.search') }}?sort=popular" class="btn btn-sm btn-outline-secondary">
                    Ver todos <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>

            @if(!empty($popular) && isset($popular['items']))
                <div class="row g-4">
                    @foreach($popular['items'] as $item)
                    <div class="col-md-6 col-lg-4 col-xl-3">
                        @include('marketplace._item_card', ['item' => $item])
                    </div>
                    @endforeach
                </div>
            @elseif(!empty($popular))
                <div class="row g-4">
                    @foreach($popular as $item)
                    <div class="col-md-6 col-lg-4 col-xl-3">
                        @include('marketplace._item_card', ['item' => $item])
                    </div>
                    @endforeach
                </div>
            @else
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    El marketplace estará disponible próximamente. Mientras tanto, puedes subir módulos, plugins y temas manualmente.
                </div>
            @endif
        </div>
    </div>

    {{-- Categorías --}}
    @if(!empty($categories))
    <div class="row">
        <div class="col-12">
            <h4 class="mb-3">
                <i class="bi bi-bookmark me-2"></i>
                Categorías
            </h4>
            <div class="row g-3">
                @foreach($categories as $category)
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="{{ route('superadmin.marketplace.search') }}?category={{ $category['slug'] }}" class="card border-0 shadow-sm h-100 text-decoration-none category-card">
                        <div class="card-body text-center py-4">
                            <h6 class="mb-1">{{ $category['name'] }}</h6>
                            <small class="text-muted">{{ $category['count'] }} items</small>
                        </div>
                    </a>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
</div>

<style>
.category-card {
    transition: all 0.3s ease;
}
.category-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.15) !important;
}
</style>
@endsection
