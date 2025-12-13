@extends('layouts.app')

@section('title', ($translation->seo_title ?? $page->title ?? setting('site_name')) . ' | ' . setting('site_name'))
@section('description', $translation->seo_description ?? $page->seo_description ?? setting('site_description'))

@section('content')

{{-- Cabecera con imagen de fondo (si está habilitada) --}}
@if(isset($customizations) && ($customizations->show_slider === true || $customizations->show_slider === 1 || $customizations->show_slider === "1"))
<section class="relative bg-gradient-to-br from-primary-600 via-secondary-600 to-accent-600 text-white py-20 md:py-32 overflow-hidden"
         style="background-image: url('{{ asset($customizations->slider_image ?? 'themes/react-modern/img/hero/default-hero.jpg') }}'); background-size: cover; background-position: center; background-blend-mode: overlay;">
    <!-- Overlay oscuro para mejorar legibilidad -->
    <div class="absolute inset-0 bg-black/40"></div>

    <div class="container-custom relative z-10">
        <div class="max-w-4xl mx-auto text-center">
            <h1 class="text-4xl md:text-6xl font-bold mb-6 text-shadow">
                {{ $customizations->slider_title ?? $translation->title ?? $page->title ?? 'Página' }}
            </h1>

            @if(!empty($customizations->slider_content))
                <div class="text-xl md:text-2xl text-white/90 mb-8">
                    {!! $customizations->slider_content !!}
                </div>
            @endif

            <div class="divider-gradient mx-auto"></div>
        </div>
    </div>

    <!-- Animated floating elements -->
    <div class="absolute bottom-8 left-1/2 -translate-x-1/2 animate-bounce">
        <i class="fas fa-chevron-down text-white/50 text-2xl"></i>
    </div>
</section>
@endif

{{-- Contenido principal --}}
<article class="{{ isset($customizations) && $customizations->show_slider ? 'py-12 md:py-20' : 'py-12 md:py-20' }}">
    <div class="container-custom max-w-4xl">
        {{-- Mostrar título solo si NO está oculto Y NO se mostró en la cabecera --}}
        @if(!isset($customizations) || ($customizations->hide_title !== true && $customizations->hide_title !== 1 && $customizations->hide_title !== "1"))
            @if(!isset($customizations) || !$customizations->show_slider)
                <!-- Page Header (solo si no hay slider) -->
                <header class="mb-12">
                    <h1 class="text-4xl md:text-6xl font-bold mb-6 text-gradient">
                        {{ $translation->title ?? $page->title ?? 'Página' }}
                    </h1>

                    <div class="divider-gradient mb-6"></div>

                    @if(isset($page->featured_image) && $page->featured_image)
                        <img
                            src="{{ asset($page->featured_image) }}"
                            alt="{{ $translation->title ?? $page->title }}"
                            class="w-full h-auto rounded-xl shadow-lg mb-8"
                        >
                    @endif

                    @if(isset($page->published_at))
                        <div class="text-gray-600 flex items-center">
                            <i class="far fa-calendar mr-2"></i>
                            <time datetime="{{ $page->published_at }}">
                                {{ date('d \d\e F \d\e Y', strtotime($page->published_at)) }}
                            </time>
                        </div>
                    @endif
                </header>
            @endif
        @endif

        <!-- Page Content -->
        <div class="musedock-content">
            @php
                $content = apply_filters('the_content', $translation->content ?? $page->content ?? '<p>Contenido no disponible.</p>');
                // Si hay slider activo, eliminar el primer h1, h2 o h3 del contenido para evitar duplicados
                if (isset($customizations) && ($customizations->show_slider === true || $customizations->show_slider === 1 || $customizations->show_slider === "1")) {
                    $content = preg_replace('/<h[123][^>]*>.*?<\/h[123]>/', '', $content, 1);
                }
            @endphp
            {!! $content !!}
        </div>
    </div>
</article>
@endsection
