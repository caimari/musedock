@extends('layouts.app')

@section('title', 'Resultados de búsqueda: ' . ($query ?? '') . ' | ' . site_setting('site_name', ''))

@section('content')
<div class="container py-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <!-- Buscador -->
            <div class="mb-5">
                <h1 class="mb-4">Buscar en el sitio</h1>
                <form action="{{ url('/search') }}" method="GET" class="mb-4">
                    <div class="input-group input-group-lg">
                        <input
                            type="text"
                            name="q"
                            class="form-control"
                            placeholder="¿Qué estás buscando?"
                            value="{{ $query ?? '' }}"
                            required
                            minlength="2"
                        >
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                    </div>
                </form>

                @if(!empty($query))
                    <p class="text-muted">
                        @if($results['total'] > 0)
                            Se encontraron <strong>{{ $results['total'] }}</strong> resultado(s) para "<strong>{{ $query }}</strong>"
                        @else
                            No se encontraron resultados para "<strong>{{ $query }}</strong>"
                        @endif
                    </p>
                @endif
            </div>

            <!-- Resultados -->
            @if(!empty($query))
                @if($results['total'] > 0)
                    <!-- Páginas -->
                    @if(count($results['pages']) > 0)
                        <div class="mb-5">
                            <h2 class="h4 mb-3">
                                <i class="fas fa-file-alt text-primary"></i>
                                Páginas ({{ count($results['pages']) }})
                            </h2>

                            @foreach($results['pages'] as $page)
                                <div class="card mb-3 shadow-sm">
                                    <div class="card-body">
                                        <h3 class="h5 card-title">
                                            <a href="{{ $page['url'] }}" class="text-decoration-none">
                                                {!! $page['title'] !!}
                                            </a>
                                        </h3>
                                        <p class="card-text text-muted small mb-2">
                                            <i class="far fa-calendar"></i>
                                            {{ date('d/m/Y', strtotime($page['published_at'])) }}
                                        </p>
                                        <p class="card-text">{!! $page['excerpt'] !!}</p>
                                        <a href="{{ $page['url'] }}" class="btn btn-sm btn-outline-primary">
                                            Leer más <i class="fas fa-arrow-right"></i>
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <!-- Posts de blog -->
                    @if(count($results['posts']) > 0)
                        <div class="mb-5">
                            <h2 class="h4 mb-3">
                                <i class="fas fa-blog text-primary"></i>
                                Blog ({{ count($results['posts']) }})
                            </h2>

                            @foreach($results['posts'] as $post)
                                <div class="card mb-3 shadow-sm">
                                    <div class="card-body">
                                        @if(!empty($post['featured_image']))
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <img
                                                        src="{{ asset($post['featured_image']) }}"
                                                        alt="{{ $post['title'] }}"
                                                        class="img-fluid rounded"
                                                    >
                                                </div>
                                                <div class="col-md-9">
                                                    <h3 class="h5 card-title">
                                                        <a href="{{ $post['url'] }}" class="text-decoration-none">
                                                            {!! $post['title'] !!}
                                                        </a>
                                                    </h3>
                                                    <p class="card-text text-muted small mb-2">
                                                        <i class="far fa-calendar"></i>
                                                        {{ date('d/m/Y', strtotime($post['published_at'])) }}
                                                    </p>
                                                    <p class="card-text">{!! $post['excerpt'] !!}</p>
                                                    <a href="{{ $post['url'] }}" class="btn btn-sm btn-outline-primary">
                                                        Leer más <i class="fas fa-arrow-right"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        @else
                                            <h3 class="h5 card-title">
                                                <a href="{{ $post['url'] }}" class="text-decoration-none">
                                                    {!! $post['title'] !!}
                                                </a>
                                            </h3>
                                            <p class="card-text text-muted small mb-2">
                                                <i class="far fa-calendar"></i>
                                                {{ date('d/m/Y', strtotime($post['published_at'])) }}
                                            </p>
                                            <p class="card-text">{!! $post['excerpt'] !!}</p>
                                            <a href="{{ $post['url'] }}" class="btn btn-sm btn-outline-primary">
                                                Leer más <i class="fas fa-arrow-right"></i>
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @else
                    <!-- Sin resultados -->
                    <div class="alert alert-info">
                        <h4 class="alert-heading">
                            <i class="fas fa-info-circle"></i>
                            No se encontraron resultados
                        </h4>
                        <p class="mb-0">
                            Intenta buscar con otros términos o visita nuestra
                            <a href="{{ url('/') }}" class="alert-link">página principal</a>.
                        </p>
                    </div>
                @endif
            @else
                <!-- Mensaje inicial -->
                <div class="text-center text-muted">
                    <i class="fas fa-search fa-3x mb-3"></i>
                    <p>Introduce un término de búsqueda para encontrar páginas y posts.</p>
                </div>
            @endif
        </div>
    </div>
</div>

@push('styles')
<style>
    mark {
        background-color: #fff3cd;
        padding: 2px 4px;
        border-radius: 2px;
        font-weight: 500;
    }

    .card:hover {
        transform: translateY(-2px);
        transition: all 0.3s ease;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }
</style>
@endpush
@endsection
