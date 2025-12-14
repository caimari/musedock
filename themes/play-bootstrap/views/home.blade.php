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

@push('styles')
<style>
/* ===== ESPACIADO ENTRE COMPONENTES ===== */
.page-content-wrapper > * {
    margin-bottom: 3rem !important; /* 48px entre componentes */
}

.page-content-wrapper > *:last-child {
    margin-bottom: 0 !important;
}

/* ===== SLIDER FULL-WIDTH COMO PRIMER ELEMENTO ===== */
body.has-fullwidth-slider-first .ud-hero {
    display: none !important;
}

body.has-fullwidth-slider-first main {
    padding-top: 0 !important;
    margin-top: 0 !important;
}

body.has-fullwidth-slider-first .ud-page-content {
    padding: 0 !important;
    margin: 0 !important;
}

body.has-fullwidth-slider-first .page-content-wrapper {
    padding-top: 0 !important;
    margin-top: 0 !important;
}

body.has-fullwidth-slider-first .page-content-wrapper > .slider-full-width-wrapper:first-child {
    margin-top: 0 !important;
    margin-bottom: 3rem !important;
}

/* ===== SLIDER NORMAL (SIN FULL-WIDTH) COMO PRIMER ELEMENTO ===== */
body.has-slider-first:not(.has-fullwidth-slider-first) .ud-hero {
    padding-top: 120px !important; /* Reducir el padding del hero */
}

body.has-slider-first:not(.has-fullwidth-slider-first) .ud-page-content {
    padding-top: 1rem !important;
}
</style>
@endpush

@push('scripts')
<script>
// Detectar tipo de slider y ajustar layout
document.addEventListener('DOMContentLoaded', function() {
    const wrapper = document.querySelector('.page-content-wrapper');
    if (wrapper) {
        const firstChild = wrapper.firstElementChild;

        if (firstChild) {
            // Si es slider full-width
            if (firstChild.classList.contains('slider-full-width-wrapper')) {
                document.body.classList.add('has-fullwidth-slider-first');
                console.log('Slider full-width detectado como primer elemento');
            }
            // Si es slider normal (swiper o gallery sin wrapper full-width)
            else if (firstChild.classList.contains('swiper') || firstChild.classList.contains('gallery-container')) {
                document.body.classList.add('has-slider-first');
                console.log('Slider normal detectado como primer elemento');
            }
        }
    }
});
</script>
@endpush

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
