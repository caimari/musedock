@extends('layouts.app')

{{-- SEO --}}
@section('title')
    {{ $post->title . ' | ' . site_setting('site_name', '') }}
@endsection

@section('description')
    {{ $post->excerpt ?? mb_substr(strip_tags($post->content), 0, 160) }}
@endsection

@section('content')

@php
    $showHero = !empty($post->show_hero);
@endphp

{{-- Hero (a lo ancho, pegado al header) --}}
@if($showHero)
    @php
        // Seleccionar imagen hero con rotación automática si no hay imagen personalizada
        if (!empty($post->hero_image)) {
            $heroPath = $post->hero_image;
        } else {
            // Array de imágenes hero disponibles para rotar aleatoriamente
            $heroImages = [
                'themes/default/img/hero/contact_hero.jpg',
                'themes/default/img/hero/about_hero.jpg',
                'themes/default/img/hero/services_hero.jpg',
                'themes/default/img/hero/gallery_hero.jpg',
                'themes/default/img/hero/Industries_hero.jpg',
                'themes/default/img/hero/h1_hero.jpg',
            ];
            // Seleccionar imagen aleatoria en cada carga de página
            $heroPath = $heroImages[array_rand($heroImages)];
        }

        $heroUrl = (str_starts_with($heroPath, '/media/') || str_starts_with($heroPath, 'http')) ? $heroPath : asset($heroPath);
        $heroTitle = $post->hero_title ?: $post->title;
    @endphp
    <div class="slider-area">
        <div class="single-slider slider-height2 d-flex align-items-center" data-background="{{ $heroUrl }}">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="hero-cap text-center">
                            <h2>{{ $heroTitle }}</h2>
                            @if(!empty($post->hero_content))
                                <div class="hero-subtitle mt-3">
                                    {!! $post->hero_content !!}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

@php
    $containerPaddingClass = $showHero ? 'pt-0 pb-5' : 'py-5';
@endphp

<div class="container {{ $containerPaddingClass }}">
    <div class="row">
        {{-- Contenido principal --}}
        <div class="col-lg-8">
            <article class="blog-post-single page-content-wrapper">

                {{-- Imagen destacada --}}
                @if($post->featured_image && !$post->hide_featured_image)
                    @php
                        $imageUrl = (str_starts_with($post->featured_image, '/media/') || str_starts_with($post->featured_image, 'http'))
                            ? $post->featured_image
                            : asset($post->featured_image);
                    @endphp
                    <img src="{{ $imageUrl }}" alt="{{ $post->title }}" class="img-fluid rounded mb-4" style="width: 100%; max-height: 500px; object-fit: cover;">
                @endif

                {{-- Título - Ocultar solo si hide_title está activado --}}
                @if(empty($post->hide_title) || $post->hide_title !== 1)
                    <h1 class="mb-3">{{ $post->title }}</h1>
                @endif

                {{-- Meta información --}}
                <div class="post-meta mb-4 text-muted">
                    @php
                        $dateVal = $post->published_at ?? $post->created_at;
                        $dateStr = $dateVal instanceof \DateTime ? $dateVal->format('d/m/Y') : date('d/m/Y', strtotime($dateVal));
                    @endphp
                    <span><i class="far fa-calendar"></i> {{ $dateStr }}</span>
                    @if($post->view_count > 0)
                        <span class="ms-3"><i class="far fa-eye"></i> {{ $post->view_count }} {{ __('blog.views') }}</span>
                    @endif
                </div>

                {{-- Contenido --}}
                <div class="post-content page-body">
                    {!! $post->content !!}
                </div>

                {{-- Categorías y etiquetas --}}
                @if(!empty($post->categories) || !empty($post->tags))
                <div class="post-taxonomies mt-5 pt-4 border-top">
                    @if(!empty($post->categories))
                    <div class="mb-3">
                        <strong>{{ __('blog.categories') }}:</strong>
                        @foreach($post->categories as $category)
                            <a href="/blog/category/{{ $category->slug }}" class="badge bg-primary text-decoration-none">{{ $category->name }}</a>
                        @endforeach
                    </div>
                    @endif

                    @if(!empty($post->tags))
                    <div>
                        <strong>{{ __('blog.tags') }}:</strong>
                        @foreach($post->tags as $tag)
                            <a href="/blog/tag/{{ $tag->slug }}" class="badge bg-secondary text-decoration-none">{{ $tag->name }}</a>
                        @endforeach
                    </div>
                    @endif
                </div>
                @endif

                {{-- Navegación prev/next --}}
                @if(!empty($prevPost) || !empty($nextPost))
                <nav class="blog-post-nav mt-5 pt-4 border-top">
                    <div class="row">
                        @if(!empty($prevPost))
                        <div class="col-md-6 mb-3 mb-md-0">
                            <a href="/blog/{{ $prevPost->slug }}" class="text-decoration-none">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    <div>
                                        <small class="text-muted d-block">{{ __('blog.previous_post') }}</small>
                                        <strong>{{ $prevPost->title }}</strong>
                                    </div>
                                </div>
                            </a>
                        </div>
                        @endif

                        @if(!empty($nextPost))
                        <div class="col-md-6 text-md-end">
                            <a href="/blog/{{ $nextPost->slug }}" class="text-decoration-none">
                                <div class="d-flex align-items-center justify-content-md-end">
                                    <div>
                                        <small class="text-muted d-block">{{ __('blog.next_post') }}</small>
                                        <strong>{{ $nextPost->title }}</strong>
                                    </div>
                                    <i class="fas fa-arrow-right ms-2"></i>
                                </div>
                            </a>
                        </div>
                        @endif
                    </div>
                </nav>
                @endif
            </article>
        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4">
            @include('partials.sidebar')
        </div>
    </div>
</div>

@endsection
