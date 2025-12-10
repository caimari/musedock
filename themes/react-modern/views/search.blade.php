@extends('layouts.app')

@section('title', 'Resultados de búsqueda: ' . ($query ?? '') . ' | ' . setting('site_name'))

@section('content')
<!-- Hero Section -->
<section class="relative bg-gradient-to-br from-primary-600 via-secondary-600 to-accent-600 text-white py-16 md:py-20">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 left-0 w-64 h-64 bg-white rounded-full -translate-x-1/2 -translate-y-1/2"></div>
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-white rounded-full translate-x-1/2 translate-y-1/2"></div>
    </div>

    <div class="container-custom relative z-10">
        <div class="max-w-3xl mx-auto text-center">
            <h1 class="text-3xl md:text-5xl font-bold mb-6 text-shadow">
                <i class="fas fa-search mr-3"></i>
                Buscar en el sitio
            </h1>

            <!-- Formulario de búsqueda -->
            <form action="{{ url('/search') }}" method="GET">
                <div class="flex gap-2">
                    <input
                        type="text"
                        name="q"
                        class="flex-1 px-6 py-4 rounded-lg text-gray-900 text-lg focus:outline-none focus:ring-4 focus:ring-white/30"
                        placeholder="¿Qué estás buscando?"
                        value="{{ $query ?? '' }}"
                        required
                        minlength="2"
                    >
                    <button
                        type="submit"
                        class="px-8 py-4 bg-white text-primary-600 font-semibold rounded-lg hover:bg-gray-100 transition-all duration-300 shadow-lg"
                    >
                        <i class="fas fa-search"></i>
                        <span class="hidden md:inline ml-2">Buscar</span>
                    </button>
                </div>
            </form>

            @if(!empty($query))
                <p class="mt-6 text-white/90 text-lg">
                    @if($results['total'] > 0)
                        Se encontraron <strong>{{ $results['total'] }}</strong> resultado(s) para "<strong>{{ $query }}</strong>"
                    @else
                        No se encontraron resultados para "<strong>{{ $query }}</strong>"
                    @endif
                </p>
            @endif
        </div>
    </div>
</section>

<!-- Resultados -->
<section class="py-12 md:py-20 bg-gray-50">
    <div class="container-custom max-w-5xl">
        @if(!empty($query))
            @if($results['total'] > 0)
                <!-- Páginas -->
                @if(count($results['pages']) > 0)
                    <div class="mb-12">
                        <h2 class="text-2xl md:text-3xl font-bold mb-6 text-gradient">
                            <i class="fas fa-file-alt mr-2"></i>
                            Páginas ({{ count($results['pages']) }})
                        </h2>

                        <div class="space-y-6">
                            @foreach($results['pages'] as $page)
                                <article class="card p-6">
                                    <h3 class="text-xl md:text-2xl font-bold mb-3">
                                        <a href="{{ $page['url'] }}" class="text-gray-900 hover:text-primary-600 transition-colors">
                                            {!! $page['title'] !!}
                                        </a>
                                    </h3>
                                    <p class="text-gray-600 mb-3 flex items-center">
                                        <i class="far fa-calendar mr-2"></i>
                                        {{ date('d \\d\\e F \\d\\e Y', strtotime($page['published_at'])) }}
                                    </p>
                                    <p class="text-gray-700 mb-4 leading-relaxed">{!! $page['excerpt'] !!}</p>
                                    <a href="{{ $page['url'] }}" class="inline-flex items-center text-primary-600 hover:text-secondary-600 font-semibold">
                                        Leer más
                                        <i class="fas fa-arrow-right ml-2"></i>
                                    </a>
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Posts de blog -->
                @if(count($results['posts']) > 0)
                    <div class="mb-12">
                        <h2 class="text-2xl md:text-3xl font-bold mb-6 text-gradient">
                            <i class="fas fa-blog mr-2"></i>
                            Blog ({{ count($results['posts']) }})
                        </h2>

                        <div class="space-y-6">
                            @foreach($results['posts'] as $post)
                                <article class="card overflow-hidden">
                                    @if(!empty($post['featured_image']))
                                        <div class="grid md:grid-cols-3 gap-6">
                                            <div class="md:col-span-1">
                                                <img
                                                    src="{{ asset($post['featured_image']) }}"
                                                    alt="{{ $post['title'] }}"
                                                    class="w-full h-48 md:h-full object-cover"
                                                >
                                            </div>
                                            <div class="md:col-span-2 p-6">
                                                <h3 class="text-xl md:text-2xl font-bold mb-3">
                                                    <a href="{{ $post['url'] }}" class="text-gray-900 hover:text-primary-600 transition-colors">
                                                        {!! $post['title'] !!}
                                                    </a>
                                                </h3>
                                                <p class="text-gray-600 mb-3 flex items-center">
                                                    <i class="far fa-calendar mr-2"></i>
                                                    {{ date('d \\d\\e F \\d\\e Y', strtotime($post['published_at'])) }}
                                                </p>
                                                <p class="text-gray-700 mb-4 leading-relaxed">{!! $post['excerpt'] !!}</p>
                                                <a href="{{ $post['url'] }}" class="inline-flex items-center text-primary-600 hover:text-secondary-600 font-semibold">
                                                    Leer más
                                                    <i class="fas fa-arrow-right ml-2"></i>
                                                </a>
                                            </div>
                                        </div>
                                    @else
                                        <div class="p-6">
                                            <h3 class="text-xl md:text-2xl font-bold mb-3">
                                                <a href="{{ $post['url'] }}" class="text-gray-900 hover:text-primary-600 transition-colors">
                                                    {!! $post['title'] !!}
                                                </a>
                                            </h3>
                                            <p class="text-gray-600 mb-3 flex items-center">
                                                <i class="far fa-calendar mr-2"></i>
                                                {{ date('d \\d\\e F \\d\\e Y', strtotime($post['published_at'])) }}
                                            </p>
                                            <p class="text-gray-700 mb-4 leading-relaxed">{!! $post['excerpt'] !!}</p>
                                            <a href="{{ $post['url'] }}" class="inline-flex items-center text-primary-600 hover:text-secondary-600 font-semibold">
                                                Leer más
                                                <i class="fas fa-arrow-right ml-2"></i>
                                            </a>
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endif
            @else
                <!-- Sin resultados -->
                <div class="text-center py-12">
                    <div class="inline-flex items-center justify-center w-24 h-24 bg-gradient-to-br from-primary-500 to-secondary-500 rounded-full mb-6">
                        <i class="fas fa-search-minus text-white text-4xl"></i>
                    </div>
                    <h2 class="text-2xl md:text-3xl font-bold mb-4 text-gray-900">
                        No se encontraron resultados
                    </h2>
                    <p class="text-lg text-gray-600 mb-6 max-w-md mx-auto">
                        Intenta buscar con otros términos o visita nuestra página principal.
                    </p>
                    <a href="{{ url('/') }}" class="btn-primary">
                        <i class="fas fa-home mr-2"></i>
                        Volver al inicio
                    </a>
                </div>
            @endif
        @else
            <!-- Mensaje inicial -->
            <div class="text-center py-12">
                <div class="inline-flex items-center justify-center w-24 h-24 bg-gradient-to-br from-primary-500 to-secondary-500 rounded-full mb-6">
                    <i class="fas fa-search text-white text-4xl"></i>
                </div>
                <h2 class="text-2xl md:text-3xl font-bold mb-4 text-gray-900">
                    Comienza tu búsqueda
                </h2>
                <p class="text-lg text-gray-600 max-w-md mx-auto">
                    Introduce un término de búsqueda para encontrar páginas y publicaciones.
                </p>
            </div>
        @endif
    </div>
</section>

@push('styles')
<style>
    mark {
        background: linear-gradient(120deg, rgba(255, 243, 205, 0.8) 0%, rgba(255, 237, 160, 0.8) 100%);
        padding: 2px 6px;
        border-radius: 4px;
        font-weight: 600;
        color: #92400e;
    }
</style>
@endpush
@endsection
