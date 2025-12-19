@extends('layouts.app')

@section('title')
    {{ ($translation->seo_title ?? $page->seo_title ?? $translation->title ?? $page->title ?? __('home_title')) . ' | ' . site_setting('site_name', '') }}
@endsection

@section('keywords')
    {{ $translation->seo_keywords ?? $page->seo_keywords ?? site_setting('site_keywords', '') }}
@endsection

@section('og_title')
    {{ $translation->seo_title ?? $page->seo_title ?? $translation->title ?? $page->title ?? __('home_title') }}
@endsection

@section('og_description')
    {{ $translation->seo_description ?? $page->seo_description ?? site_setting('site_description', '') }}
@endsection

@section('content')
    @if(isset($customizations))
        {{-- Verificar si se debe mostrar la cabecera para esta página --}}
        @if($customizations->show_slider === true || $customizations->show_slider === 1 || $customizations->show_slider === "1")
            @php
                // Seleccionar imagen hero con rotación automática si no hay imagen personalizada
                $defaultHeroImages = [
                    'themes/default/img/hero/contact_hero.jpg',
                    'themes/default/img/hero/about_hero.jpg',
                    'themes/default/img/hero/services_hero.jpg',
                    'themes/default/img/hero/gallery_hero.jpg',
                    'themes/default/img/hero/Industries_hero.jpg',
                    'themes/default/img/hero/h1_hero.jpg',
                ];

                $hasCustomImage = !empty($customizations->slider_image) &&
                                  !in_array($customizations->slider_image, $defaultHeroImages) &&
                                  !str_ends_with($customizations->slider_image, '/contact_hero.jpg') &&
                                  !str_ends_with($customizations->slider_image, '/about_hero.jpg') &&
                                  !str_ends_with($customizations->slider_image, '/services_hero.jpg') &&
                                  !str_ends_with($customizations->slider_image, '/gallery_hero.jpg') &&
                                  !str_ends_with($customizations->slider_image, '/Industries_hero.jpg') &&
                                  !str_ends_with($customizations->slider_image, '/h1_hero.jpg');

                if ($hasCustomImage) {
                    $sliderPath = $customizations->slider_image;
                } else {
                    // Seleccionar imagen aleatoria en cada carga de página
                    $sliderPath = $defaultHeroImages[array_rand($defaultHeroImages)];
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
                                    <h2>{{ !empty($customizations->slider_title) ? $customizations->slider_title : ($translation->title ?? '') }}</h2>
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

    @php
	        $content = apply_filters('the_content', $translation->content ?? $page->content ?? '<p>' . __('home_intro') . '</p>');
	        // Solo eliminar el primer h1 si hay slider, no h2 ni h3
	        if (isset($customizations) && ($customizations->show_slider === true || $customizations->show_slider === 1 || $customizations->show_slider === "1")) {
	            // No eliminar títulos de Elements (p.ej. <h1 class="hero-title">)
	            $content = preg_replace('/<h1(?![^>]*\bhero-title\b)[^>]*>.*?<\/h1>/s', '', $content, 1);
	        }

        // Detectar y extraer slider a sangre (full-width) del contenido
        $fullWidthSliderHtml = '';
        $hasFullWidthSlider = false;
        if (preg_match('/<div class="slider-full-width-wrapper"[^>]*>.*?<\/div>\s*<\/div>/s', $content, $matches)) {
            // Extraer todo el bloque del slider (incluyendo scripts y estilos)
            if (preg_match('/(<link[^>]*swiper[^>]*>.*?<div class="slider-full-width-wrapper"[^>]*>.*?<script>.*?<\/script>\s*<\/div>)/s', $content, $fullMatch)) {
                $fullWidthSliderHtml = $fullMatch[1];
                $content = str_replace($fullMatch[1], '', $content);
                $hasFullWidthSlider = true;
            }
        }

        // Limpiar párrafos vacíos que puedan quedar al inicio
        $content = preg_replace('/^(\s*<p>\s*<\/p>\s*)+/', '', $content);
    @endphp

    {{-- Slider a sangre FUERA del contenedor --}}
    @if($hasFullWidthSlider)
        {!! $fullWidthSliderHtml !!}
    @endif

    <div class="{{ (isset($customizations) ? $customizations->container_class : null) ?? 'container py-4 page-container' }} has-slider-content">
        <article class="{{ (isset($customizations) ? $customizations->content_class : null) ?? 'page-content-wrapper' }}">
            @if(isset($customizations) && $customizations->hide_title !== true && isset($page) && !$page->is_homepage)
                <h1 class="page-title">{{ $translation->title ?? '' }}</h1>
            @endif

            <div class="page-body">
                {!! $content !!}
            </div>
        </article>
    </div>

@endsection
