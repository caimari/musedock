{{-- themes/default/page.blade.php --}}
@extends('layouts.app') 

{{-- === SECCIONES SEO (Usando $translation que ya tiene el fallback) === --}}
@section('title')
    {{-- Usar el título SEO si existe, si no el título normal. Añadir nombre del sitio. --}}
    {{ ($translation->seo_title ?: $translation->title ?: 'Página') . ' | ' . setting('site_name', 'MuseDock CMS') }}
@endsection

@section('keywords')
    {{ $translation->seo_keywords ?? setting('site_keywords', '') }}
@endsection

@section('description') {{-- Añadir meta description --}}
    {{ $translation->seo_description ?? setting('site_description', '') }}
@endsection

@section('og_title')
    {{-- Usar título SEO o normal para Open Graph --}}
    {{ $translation->seo_title ?: $translation->title ?: 'Página' }}
@endsection

@section('og_description')
    {{ $translation->seo_description ?? setting('site_description', '') }}
@endsection

{{-- Añadir más metas SEO si es necesario (imagen, canónica, robots, twitter) --}}
@section('extra_head')
    @if(!empty($translation->canonical_url))
        <link rel="canonical" href="{{ $translation->canonical_url }}" />
    @endif
    @if(!empty($translation->robots_directive) && $translation->robots_directive !== 'index,follow') {{-- Solo si no es el default --}}
        <meta name="robots" content="{{ $translation->robots_directive }}" />
    @endif
    @if(!empty($translation->seo_image))
        <meta property="og:image" content="{{ $translation->seo_image }}" />
    @endif
    {{-- Twitter Cards (Ejemplo básico) --}}
    <meta name="twitter:card" content="summary_large_image"> {{-- O 'summary' --}}
    @if(!empty($translation->twitter_title))
        <meta name="twitter:title" content="{{ $translation->twitter_title }}">
    @elseif(!empty($translation->seo_title ?: $translation->title))
         <meta name="twitter:title" content="{{ $translation->seo_title ?: $translation->title }}">
    @endif
    @if(!empty($translation->twitter_description))
        <meta name="twitter:description" content="{{ $translation->twitter_description }}">
     @elseif(!empty($translation->seo_description))
         <meta name="twitter:description" content="{{ $translation->seo_description }}">
    @endif
     @if(!empty($translation->twitter_image))
        <meta name="twitter:image" content="{{ $translation->twitter_image }}">
    @elseif(!empty($translation->seo_image))
        <meta name="twitter:image" content="{{ $translation->seo_image }}">
    @endif
@endsection


@section('content')

{{-- Depuración: Valores de las variables --}}
<!-- 
DEBUG: 
show_slider: {{ var_export($customizations->show_slider, true) }}
hide_title: {{ var_export($customizations->hide_title, true) }}
is_homepage: {{ var_export($page->is_homepage, true) }}
-->

{{-- Verificar si se debe mostrar la cabecera para esta página --}}
@if($customizations->show_slider === true || $customizations->show_slider === 1 || $customizations->show_slider === "1")
<!-- Cabecera Area Start-->
<div class="slider-area">
    <div class="single-slider slider-height2 d-flex align-items-center" 
         data-background="{{ asset($customizations->slider_image ? $customizations->slider_image : 'themes/default/img/hero/contact_hero.jpg') }}">
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
<!-- Cabecera Area End-->
@else
<!-- La cabecera NO se mostrará porque show_slider es: {{ var_export($customizations->show_slider, true) }} -->
@endif

{{-- Contenido principal de la página --}}
<div class="{{ $customizations->container_class ?? 'container py-4 page-container' }}">
    <article class="{{ $customizations->content_class ?? 'page-content-wrapper' }}">
        {{-- Mostrar el título solo si NO está oculto Y no es la página de inicio --}}
        @if($customizations->hide_title !== true && !$page->is_homepage)
            <h1 class="page-title">{{ $translation->title ?? '' }}</h1>
        @endif

        {{-- Renderizar el contenido HTML con filtros aplicados (shortcodes, etc.) --}}
        <div class="page-body">
             {!! apply_filters('the_content', $translation->content ?? '<p class="text-muted">Contenido no disponible.</p>') !!}
        </div>
    </article>
</div>
@endsection