{{-- themes/default/page.blade.php --}}
@extends('layouts.app') 

{{-- === SECCIONES SEO (Usando $translation que ya tiene el fallback) === --}}
@section('title')
    {{-- Usar el título SEO si existe, si no el título normal. Añadir nombre del sitio. --}}
    {{ ($translation->seo_title ?: $translation->title ?: 'Página') . ' | ' . site_setting('site_name', '') }}
@endsection

@section('keywords')
    {{ $translation->seo_keywords ?? site_setting('site_keywords', '') }}
@endsection

@section('description') {{-- Añadir meta description --}}
    {{ $translation->seo_description ?? site_setting('site_description', '') }}
@endsection

@section('og_title')
    {{-- Usar título SEO o normal para Open Graph --}}
    {{ $translation->seo_title ?: $translation->title ?: 'Página' }}
@endsection

@section('og_description')
    {{ $translation->seo_description ?? site_setting('site_description', '') }}
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
    @if(isset($post) && !empty($translation->featured_image))
        @php
            // Si la URL ya empieza con /media/ o es absoluta (http), usarla directamente
            $ogImageUrl = (str_starts_with($translation->featured_image, '/media/') || str_starts_with($translation->featured_image, 'http'))
                ? $translation->featured_image
                : asset($translation->featured_image);
        @endphp
        <meta property="og:image" content="{{ $ogImageUrl }}" />
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

{{-- El slider/cabecera solo se muestra para páginas, no para posts de blog --}}
@if(!isset($post) && isset($customizations))
    {{-- Verificar si se debe mostrar la cabecera para esta página --}}
    @if($customizations->show_slider === true || $customizations->show_slider === 1 || $customizations->show_slider === "1")
    @php
        // Seleccionar imagen hero con rotación automática si no hay imagen personalizada
        if (!empty($customizations->slider_image)) {
            $sliderPath = $customizations->slider_image;
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
            $sliderPath = $heroImages[array_rand($heroImages)];
        }

        $sliderUrl = (str_starts_with($sliderPath, '/media/') || str_starts_with($sliderPath, 'http')) ? $sliderPath : asset($sliderPath);
    @endphp
    <!-- Cabecera Area Start-->
    <div class="slider-area">
        <div class="single-slider slider-height2 d-flex align-items-center"
             data-background="{{ $sliderUrl }}">
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
    @endif
@endif

{{-- Hero para posts de blog (usa show_hero/hero_*) --}}
@if(isset($post) && ($post->show_hero === true || $post->show_hero === 1 || $post->show_hero === "1"))
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

{{-- Contenido principal de la página --}}
@php
    // Pre-procesar contenido para detectar y extraer slider a sangre
    $rawContent = $translation->content ?? '';
    $processedContent = apply_filters('the_content', $rawContent);

    // Detectar y extraer slider a sangre (full-width) del contenido
    $fullWidthSliderHtml = '';
    $pageHasFullWidthSlider = false;

    if (strpos($processedContent, 'slider-full-width-wrapper') !== false) {
        // Intentar extraer el bloque completo del slider (link CSS + wrapper + script)
        if (preg_match('/(<link[^>]*swiper[^>]*>.*?<div class="slider-full-width-wrapper"[^>]*>.*?<script>.*?<\/script>\s*<\/div>)/s', $processedContent, $fullMatch)) {
            $fullWidthSliderHtml = $fullMatch[1];
            $processedContent = str_replace($fullMatch[1], '', $processedContent);
            $pageHasFullWidthSlider = true;
        } elseif (preg_match('/(<div class="slider-full-width-wrapper"[^>]*>.*?<\/div>\s*<\/div>\s*<\/div>)/s', $processedContent, $simpleMatch)) {
            // Fallback: extraer solo el wrapper sin link/script
            $fullWidthSliderHtml = $simpleMatch[1];
            $processedContent = str_replace($simpleMatch[1], '', $processedContent);
            $pageHasFullWidthSlider = true;
        }

        // Limpiar párrafos vacíos que puedan quedar al inicio
        $processedContent = preg_replace('/^(\s*<p>\s*<\/p>\s*)+/', '', $processedContent);
    }
@endphp

{{-- Slider a sangre FUERA del contenedor --}}
@if($pageHasFullWidthSlider)
    {!! $fullWidthSliderHtml !!}
@endif

<div class="{{ isset($post) ? 'container py-4' : ((isset($customizations) ? $customizations->container_class : null) ?? 'container py-4 page-container') }} has-slider-content">
    <article class="{{ isset($post) ? 'blog-post-single' : ((isset($customizations) ? $customizations->content_class : null) ?? 'page-content-wrapper') }}">
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

            {{-- Título del post - Ocultar solo si hide_title está activado --}}
            @if(empty($post->hide_title) || $post->hide_title !== 1)
                <h1 class="post-title mb-3">{{ $translation->title }}</h1>
            @endif

            {{-- Meta información del post --}}
            <div class="post-meta mb-4 text-muted">
                @php
                    $dateVal = $translation->published_at;
                    $dateStr = $dateVal instanceof \DateTime ? $dateVal->format('d/m/Y') : date('d/m/Y', strtotime($dateVal));
                @endphp
                <span><i class="far fa-calendar"></i> {{ $dateStr }}</span>
            </div>

            {{-- Contenido del post --}}
            <div class="post-content">
                {!! $translation->content ?? '<p class="text-muted">Contenido no disponible.</p>' !!}
            </div>
        @else
            {{-- Es una página --}}
            {{-- Mostrar el título H1 solo si NO está oculto mediante hide_title Y no es la página de inicio --}}
            @if(isset($customizations) && $customizations->hide_title !== true && $customizations->hide_title !== 1 && isset($page) && !$page->is_homepage)
                <h1 class="page-title">{{ $translation->title ?? '' }}</h1>
            @endif

            {{-- Renderizar el contenido HTML con filtros aplicados (shortcodes, etc.) --}}
            <div class="page-body">
                @php
                    // Usar el contenido ya procesado (sin el slider a sangre si lo había)
                    $content = $processedContent;
                    // Si hide_title está activado, eliminar el primer H1 del contenido
                    // Esto permite ocultar títulos que el usuario haya puesto manualmente en el editor
                    if (isset($customizations) && ($customizations->hide_title === true || $customizations->hide_title === 1 || $customizations->hide_title === "1")) {
                        $content = preg_replace('/<h1[^>]*>.*?<\/h1>/', '', $content, 1);
                    }
                @endphp
                {!! $content !!}
            </div>
        @endif
    </article>
</div>

@endsection
