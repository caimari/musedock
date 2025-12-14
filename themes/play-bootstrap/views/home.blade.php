@extends('layouts.app')

@section('title')
    {{ ($translation->seo_title ?? $page->seo_title ?? $translation->title ?? $page->title ?? __('home_title')) . ' | ' . site_setting('site_name', '') }}
@endsection

@section('keywords')
    {{ $translation->seo_keywords ?? $page->seo_keywords ?? site_setting('site_keywords', '') }}
@endsection

@section('description')
    {{ $translation->seo_description ?? $page->seo_description ?? site_setting('site_description', '') }}
@endsection

@section('og_title')
    {{ $translation->seo_title ?? $page->seo_title ?? $translation->title ?? $page->title ?? __('home_title') }}
@endsection

@section('og_description')
    {{ $translation->seo_description ?? $page->seo_description ?? site_setting('site_description', '') }}
@endsection

@section('content')
<!-- ====== Hero Start ====== -->
<section class="ud-hero" id="home">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="ud-hero-content wow fadeInUp" data-wow-delay=".2s">
                    @php
                        $pageTitle = $translation->title ?? $page->title ?? site_setting('site_name', '');
                        $homeContent = apply_filters('the_content', $translation->content ?? $page->content ?? '');
                        // Extraer el primer párrafo para usar como descripción
                        preg_match('/<p[^>]*>(.*?)<\/p>/s', $homeContent, $matches);
                        $heroDesc = $matches[1] ?? site_setting('site_description', '');
                        // Limpiar tags HTML del desc
                        $heroDesc = strip_tags($heroDesc);
                    @endphp
                    <h1 class="ud-hero-title">
                        {{ $pageTitle }}
                    </h1>
                    @if($heroDesc)
                    <p class="ud-hero-desc">
                        {{ $heroDesc }}
                    </p>
                    @endif
                </div>
                <div class="ud-hero-brands-wrapper wow fadeInUp" data-wow-delay=".3s">
                    {{-- Aquí puedes agregar logos de marcas si es necesario --}}
                </div>
                <div class="ud-hero-image wow fadeInUp" data-wow-delay=".25s">
                    <img src="{{ asset('themes/play-bootstrap/img/hero/hero-image.svg') }}" alt="hero-image" />
                    <img
                        src="{{ asset('themes/play-bootstrap/img/hero/dotted-shape.svg') }}"
                        alt="shape"
                        class="shape shape-1"
                    />
                    <img
                        src="{{ asset('themes/play-bootstrap/img/hero/dotted-shape.svg') }}"
                        alt="shape"
                        class="shape shape-2"
                    />
                </div>
            </div>
        </div>
    </div>
</section>
<!-- ====== Hero End ====== -->

<!-- ====== Contenido dinámico ====== -->
@if(!empty($homeContent))
<section class="ud-page-content">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="page-content-wrapper">
                    @php
                        // Eliminar el primer h1 y primer párrafo del contenido (ya los mostramos en el hero)
                        $contentFiltered = preg_replace('/<h1[^>]*>.*?<\/h1>/', '', $homeContent, 1);
                        $contentFiltered = preg_replace('/<p[^>]*>.*?<\/p>/s', '', $contentFiltered, 1);
                    @endphp
                    {!! $contentFiltered !!}
                </div>
            </div>
        </div>
    </div>
</section>
@endif
<!-- ====== Contenido End ====== -->

@endsection
