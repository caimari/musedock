@extends('layouts.app')

{{-- SEO --}}
@section('title')
    {{ ($translation->seo_title ?: $translation->title ?: 'Página') . ' | ' . site_setting('site_name', '') }}
@endsection

@section('keywords')
    {{ $translation->seo_keywords ?? site_setting('site_keywords', '') }}
@endsection

@section('description')
    {{ $translation->seo_description ?? site_setting('site_description', '') }}
@endsection

@section('og_title')
    {{ $translation->seo_title ?: $translation->title ?: 'Página' }}
@endsection

@section('og_description')
    {{ $translation->seo_description ?? site_setting('site_description', '') }}
@endsection

@section('extra_head')
    @if(!empty($translation->canonical_url))
        <link rel="canonical" href="{{ $translation->canonical_url }}" />
    @endif
    @if(!empty($translation->robots_directive) && $translation->robots_directive !== 'index,follow')
        <meta name="robots" content="{{ $translation->robots_directive }}" />
    @endif
    @if(!empty($translation->seo_image))
        <meta property="og:image" content="{{ $translation->seo_image }}" />
    @endif
    @if(isset($post) && !empty($translation->featured_image))
        @php
            // Si la URL ya empieza con /media/ o es absoluta (http), usarla directamente
            $ogImageUrl = (str_starts_with($translation->featured_image, '/media/') || str_starts_with($translation->featured_image, 'http'))
                ? $translation->featured_image
                : asset($translation->featured_image);
        @endphp
        <meta property="og:image" content="{{ $ogImageUrl }}" />
    @endif
@endsection

@section('content')

{{-- Cabecera/slider para páginas (igual que template full) --}}
@if(!isset($post) && isset($customizations))
    @if($customizations->show_slider === true || $customizations->show_slider === 1 || $customizations->show_slider === "1")
        @php
            $sliderPath = !empty($customizations->slider_image) ? $customizations->slider_image : 'themes/default/img/hero/contact_hero.jpg';
            $sliderUrl = (str_starts_with($sliderPath, '/media/') || str_starts_with($sliderPath, 'http')) ? $sliderPath : asset($sliderPath);
        @endphp
        <div class="slider-area">
            <div class="single-slider slider-height2 d-flex align-items-center" data-background="{{ $sliderUrl }}">
                <div class="container">
                    <div class="row">
                        <div class="col-xl-12">
                            <div class="hero-cap text-center">
                                <h2>{{ $customizations->slider_title ?? $translation->title }}</h2>
                                @if(!empty($customizations->slider_content))
                                    <div class="hero-subtitle mt-3">
                                        {!! $customizations->slider_content !!}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endif

{{-- Hero para posts de blog (a lo ancho, igual que pages/page.blade.php) --}}
@if(isset($post) && ($post->show_hero === true || $post->show_hero === 1 || $post->show_hero === "1"))
    @php
        $heroPath = !empty($post->hero_image) ? $post->hero_image : 'themes/default/img/hero/contact_hero.jpg';
        $heroUrl = (str_starts_with($heroPath, '/media/') || str_starts_with($heroPath, 'http')) ? $heroPath : asset($heroPath);
        $heroTitle = $post->hero_title ?: ($translation->title ?? $post->title);
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
    $showSlider = isset($customizations) && ($customizations->show_slider === true || $customizations->show_slider === 1 || $customizations->show_slider === "1");
    $showHero = isset($post) && ($post->show_hero === true || $post->show_hero === 1 || $post->show_hero === "1");
    $containerPaddingClass = ($showSlider || $showHero) ? 'pt-0 pb-4' : 'py-4';
@endphp

<div class="container {{ $containerPaddingClass }} page-with-sidebar">
    <div class="row">
        {{-- Contenido principal --}}
        <div class="col-md-8 col-lg-9">
            <article class="@if(isset($post)) blog-post-single @else page-content-wrapper @endif">
                @if(isset($post))
                    {{-- Es un post de blog - mostrar imagen destacada si no está oculta --}}
                    @if($translation->featured_image && !$post->hide_featured_image)
                    <div class="featured-image mb-4">
                        @php
                            // Si la URL ya empieza con /media/ o es absoluta (http), usarla directamente
                            $imageUrl = (str_starts_with($translation->featured_image, '/media/') || str_starts_with($translation->featured_image, 'http'))
                                ? $translation->featured_image
                                : asset($translation->featured_image);
                        @endphp
                        <img src="{{ $imageUrl }}" alt="{{ $translation->title }}" class="img-fluid rounded">
                    </div>
                    @endif

                    {{-- Título del post --}}
                    <h1 class="post-title mb-3">{{ $translation->title }}</h1>

                    {{-- Meta información del post --}}
                    <div class="post-meta mb-4 text-muted">
                        @php
                            $dateVal = $translation->published_at;
                            $dateStr = $dateVal instanceof \DateTime ? $dateVal->format('d/m/Y') : date('d/m/Y', strtotime($dateVal));
                        @endphp
                        <span><i class="far fa-calendar"></i> {{ $dateStr }}</span>
                    </div>
                @endif

                <div class="@if(isset($post)) post-content @else page-body @endif">
                    {!! $translation->content ?? '<p class="text-muted">Contenido no disponible.</p>' !!}
                </div>
            </article>
        </div>

        {{-- Sidebar derecha --}}
        <div class="col-md-4 col-lg-3">
            {{-- Sidebar parcial (si existe) --}}
            @include('partials.sidebar')
        </div>
    </div>
</div>

@endsection
