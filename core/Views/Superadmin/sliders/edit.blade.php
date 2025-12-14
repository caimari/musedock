@extends('layouts.app')

@section('title', $title ?? 'Editar Slider')

@push('styles')
{{-- Swiper local (sin CDN) para evitar problemas CSP --}}
<link rel="stylesheet" href="/assets/css/swiper-bundle.min.css?v={{ file_exists(public_path('assets/css/swiper-bundle.min.css')) ? filemtime(public_path('assets/css/swiper-bundle.min.css')) : time() }}" />
<link rel="stylesheet" href="{{ asset('themes/default/css/slider-themes.css') }}?v={{ file_exists(public_path('assets/themes/default/css/slider-themes.css')) ? filemtime(public_path('assets/themes/default/css/slider-themes.css')) : time() }}" />
{{-- Google Fonts (para selects de tipografías en captions) --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;800&family=Montserrat:wght@400;600;700;800&family=Roboto:wght@400;500;700;900&family=Open+Sans:wght@400;600;700;800&family=Lato:wght@400;700;900&family=Poppins:wght@400;500;600;700;800&family=Oswald:wght@400;500;600;700&family=Raleway:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<style>
	    /* Asegurar que los contenedores permitan sticky */
	    .app-content,
	    .container-fluid,
	    .sliders-edit-row,
	    .sliders-edit-left {
	        overflow: visible !important;
	    }

	    /* IMPORTANTE: para que position:sticky funcione durante TODO el scroll del sidebar,
	       la columna izquierda debe estirarse a la altura de la columna derecha.
	       Evitar align-items:flex-start (corta el sticky). */
	    .sliders-edit-row {
	        align-items: stretch !important;
	    }

		    /* Preview sticky - controlado por JS (más robusto que sticky por overflow AdminLTE) */
		    .preview-sticky-wrapper {
		        z-index: 1000;
		        align-self: flex-start;
		        max-height: calc(100vh - 40px);
		    }

		    .preview-sticky-wrapper.is-fixed {
		        position: fixed !important;
		    }

		    /* Placeholder para evitar "saltos" cuando el preview pasa a fixed */
		    .preview-sticky-placeholder {
		        display: none;
		    }

		    .preview-sticky-placeholder.is-active {
		        display: block;
		    }

		    /* Botón anclar/desanclar */
		    .btn-preview-pin.active {
		        background: rgba(0, 0, 0, 0.08);
		    }

	    /* Asegurar que el contenedor tenga altura para scroll */
	    .sliders-edit-left {
	        min-height: 100%;
	        position: relative;
	    }

    /* Importante: NO recortar el slider aquí, para que temas con sombra (p.ej. rounded-shadow)
       se vean correctamente en el preview. El recorte lo hace #preview-swiper. */
    #preview-container {
        border-radius: 0;
        overflow: visible;
        position: relative;
    }
    #preview-swiper {
        height: 350px;
        border-radius: 8px;
        overflow: hidden;
    }

    .theme-badge {
        font-size: 12px;
        padding: 4px 10px;
    }
    #preview-swiper .swiper-slide img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .preview-controls {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 100;
        display: flex;
        gap: 5px;
    }
    .theme-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        z-index: 100;
        background: rgba(0,0,0,0.7);
        color: white;
        padding: 4px 10px;
    }

    /* ===== ESTILOS PARA GALLERY MODE (MINIATURAS) ===== */
    #preview-container.gallery-mode #preview-swiper {
        height: 280px;
    }

    #thumbs-swiper {
        height: 70px;
        box-sizing: border-box;
        padding: 5px 0;
        background: rgba(0,0,0,0.8);
        transition: background 0.3s ease;
    }

    /* Gallery Light - fondo claro para thumbs */
    .theme-gallery-light #thumbs-swiper,
    #preview-container.theme-gallery-light #thumbs-swiper,
    .gallery-light #thumbs-swiper {
        background: linear-gradient(180deg, #f5f5f5 0%, #e8e8e8 100%) !important;
        border-top: 1px solid #ddd !important;
    }

    #thumbs-swiper .swiper-slide {
        width: 80px;
        height: 60px;
        opacity: 0.5;
        cursor: pointer;
        border: 2px solid transparent;
        border-radius: 4px;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    #thumbs-swiper .swiper-slide-thumb-active {
        opacity: 1;
        border-color: #fff;
    }

    /* Gallery Light - borde activo naranja */
    .theme-gallery-light #thumbs-swiper .swiper-slide-thumb-active,
    #preview-container.theme-gallery-light #thumbs-swiper .swiper-slide-thumb-active {
        border-color: #FF6600 !important;
    }

    #thumbs-swiper .swiper-slide img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .thumbs-container {
        display: none;
    }

    #preview-container.gallery-mode .thumbs-container {
        display: block;
    }

    /* ===== ESTILOS PARA PREVIEW SWIPER ===== */
    #preview-swiper .swiper-button-prev:hover,
    #preview-swiper .swiper-button-next:hover {
        transform: translateY(-50%) scale(1.1) !important;
    }

    /* ========== FORMATOS DE FLECHAS - SOLO FORMA Y TAMAÑO ========== */
    /* Los colores se aplican desde los pickers (updateArrowColor, updateArrowBackgroundColor) */

    /* --- CÍRCULOS --- */
    #preview-swiper .swiper-button-prev.arrow-circle-shadow,
    #preview-swiper .swiper-button-next.arrow-circle-shadow {
        border-radius: 50% !important;
        width: 44px !important;
        height: 44px !important;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2) !important;
    }
    #preview-swiper .swiper-button-prev.arrow-circle-medium,
    #preview-swiper .swiper-button-next.arrow-circle-medium {
        border-radius: 50% !important;
        width: 48px !important;
        height: 48px !important;
        box-shadow: 0 2px 12px rgba(0,0,0,0.2) !important;
    }
    #preview-swiper .swiper-button-prev.arrow-circle-large,
    #preview-swiper .swiper-button-next.arrow-circle-large {
        border-radius: 50% !important;
        width: 52px !important;
        height: 52px !important;
        box-shadow: 0 2px 15px rgba(0,0,0,0.2) !important;
    }
    #preview-swiper .swiper-button-prev.arrow-circle-blur,
    #preview-swiper .swiper-button-next.arrow-circle-blur {
        border-radius: 50% !important;
        width: 52px !important;
        height: 52px !important;
        backdrop-filter: blur(10px) !important;
        -webkit-backdrop-filter: blur(10px) !important;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15) !important;
    }
    #preview-swiper .swiper-button-prev.arrow-circle-border,
    #preview-swiper .swiper-button-next.arrow-circle-border {
        border-radius: 50% !important;
        width: 52px !important;
        height: 52px !important;
        border: 2px solid currentColor !important;
        box-shadow: 0 4px 20px rgba(0,0,0,0.2) !important;
    }

    /* --- CUADRADOS --- */
    #preview-swiper .swiper-button-prev.arrow-square-basic,
    #preview-swiper .swiper-button-next.arrow-square-basic {
        border-radius: 0 !important;
        width: 44px !important;
        height: 44px !important;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2) !important;
    }
    #preview-swiper .swiper-button-prev.arrow-square-medium,
    #preview-swiper .swiper-button-next.arrow-square-medium {
        border-radius: 0 !important;
        width: 48px !important;
        height: 48px !important;
        box-shadow: 0 2px 12px rgba(0,0,0,0.2) !important;
    }
    #preview-swiper .swiper-button-prev.arrow-square-large,
    #preview-swiper .swiper-button-next.arrow-square-large {
        border-radius: 0 !important;
        width: 56px !important;
        height: 56px !important;
        box-shadow: 0 2px 15px rgba(0,0,0,0.2) !important;
    }
    #preview-swiper .swiper-button-prev.arrow-square-blur,
    #preview-swiper .swiper-button-next.arrow-square-blur {
        border-radius: 0 !important;
        width: 56px !important;
        height: 56px !important;
        backdrop-filter: blur(8px) !important;
        -webkit-backdrop-filter: blur(8px) !important;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15) !important;
    }
    #preview-swiper .swiper-button-prev.arrow-square-border,
    #preview-swiper .swiper-button-next.arrow-square-border {
        border-radius: 0 !important;
        width: 48px !important;
        height: 48px !important;
        border: 2px solid currentColor !important;
        box-shadow: 0 2px 15px rgba(0,0,0,0.15) !important;
    }

    /* --- REDONDEADOS --- */
    #preview-swiper .swiper-button-prev.arrow-rounded-small,
    #preview-swiper .swiper-button-next.arrow-rounded-small {
        border-radius: 8px !important;
        width: 40px !important;
        height: 40px !important;
        box-shadow: 0 2px 12px rgba(0,0,0,0.1) !important;
    }
    #preview-swiper .swiper-button-prev.arrow-rounded-medium,
    #preview-swiper .swiper-button-next.arrow-rounded-medium {
        border-radius: 8px !important;
        width: 44px !important;
        height: 44px !important;
        box-shadow: 0 2px 12px rgba(0,0,0,0.15) !important;
    }
    #preview-swiper .swiper-button-prev.arrow-rounded-large,
    #preview-swiper .swiper-button-next.arrow-rounded-large {
        border-radius: 12px !important;
        width: 46px !important;
        height: 46px !important;
        box-shadow: 0 4px 20px rgba(0,0,0,0.2) !important;
    }

    /* --- MINIMALISTAS --- */
    #preview-swiper .swiper-button-prev.arrow-minimal,
    #preview-swiper .swiper-button-next.arrow-minimal {
        background: transparent !important;
        width: 40px !important;
        height: 40px !important;
        box-shadow: none !important;
    }
    #preview-swiper .swiper-button-prev.arrow-minimal::after,
    #preview-swiper .swiper-button-next.arrow-minimal::after {
        width: 12px !important;
        height: 12px !important;
        border-width: 3px !important;
        filter: drop-shadow(0 0 8px rgba(0,0,0,0.4)) !important;
    }
    #preview-swiper .swiper-button-prev.arrow-minimal-large,
    #preview-swiper .swiper-button-next.arrow-minimal-large {
        background: transparent !important;
        width: 56px !important;
        height: 56px !important;
        box-shadow: none !important;
    }
    #preview-swiper .swiper-button-prev.arrow-minimal-large::after,
    #preview-swiper .swiper-button-next.arrow-minimal-large::after {
        width: 18px !important;
        height: 18px !important;
        border-width: 4px !important;
        filter: drop-shadow(0 0 10px rgba(0,0,0,0.5)) !important;
    }

    /* Paginación */
    #preview-swiper .swiper-pagination-bullet {
        width: 10px;
        height: 10px;
        background: rgba(255,255,255,0.7);
        opacity: 1;
    }
    #preview-swiper .swiper-pagination-bullet-active {
        background: #fff;
        transform: scale(1.2);
    }

    /* Navegación oculta */
    #preview-swiper .swiper-button-prev.nav-hidden,
    #preview-swiper .swiper-button-next.nav-hidden {
        display: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
        pointer-events: none !important;
    }
    #preview-swiper .swiper-pagination.pag-hidden {
        display: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
        pointer-events: none !important;
    }

    /* Navegación visible (forzar) */
    #preview-swiper .swiper-button-prev:not(.nav-hidden),
    #preview-swiper .swiper-button-next:not(.nav-hidden) {
        display: flex !important;
        visibility: visible !important;
        opacity: 1 !important;
    }
    #preview-swiper .swiper-pagination:not(.pag-hidden) {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
    }

    /* Caption en preview */
    #preview-swiper .caption {
        position: absolute;
        z-index: 10;
        padding: 15px 20px;
        border-radius: 4px;
    }

    /* ===== ESTILOS PARA EFECTOS DE TRANSICIÓN ===== */
    /* Fade effect - slides deben estar apilados */
    #preview-swiper.swiper-fade .swiper-slide {
        opacity: 0 !important;
        transition: opacity 0.6s ease;
    }
    #preview-swiper.swiper-fade .swiper-slide-active {
        opacity: 1 !important;
    }

    /* Cube effect */
    #preview-swiper.swiper-cube {
        overflow: visible;
    }
    #preview-swiper.swiper-cube .swiper-slide {
        backface-visibility: hidden;
    }

    /* Coverflow effect */
    #preview-swiper.swiper-coverflow .swiper-slide {
        width: 70%;
    }

    /* Flip effect */
    #preview-swiper.swiper-flip {
        overflow: visible;
    }

    /* ===== ESTILOS PARA DRAG & DROP ===== */
    .drag-handle {
        cursor: grab;
        color: #6c757d;
        user-select: none;
    }

    .drag-handle:hover {
        color: #0d6efd;
    }

    .drag-handle:active {
        cursor: grabbing;
    }

    .sortable-ghost {
        opacity: 0.4;
        background-color: #e3f2fd !important;
    }

    .sortable-chosen {
        background-color: #fff3cd !important;
    }

    .sortable-drag {
        background-color: #fff !important;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
    }

    /* Transición suave para filas */
    #slides-list-{{ $slider->id ?? '' }} tr {
        transition: transform 0.15s ease, background-color 0.2s ease;
    }

    /* ===== ESTILOS PERSONALIZADOS DE FLECHAS (desde PHP) ===== */
    /* Se aplican al cargar la página para evitar parpadeo */
    #preview-swiper .swiper-button-prev,
    #preview-swiper .swiper-button-next,
    #preview-swiper.swiper .swiper-button-prev,
    #preview-swiper.swiper .swiper-button-next {
        background-color: {{ $settings['arrows_bg_color'] ?? 'rgba(255,255,255,0.9)' }} !important;
        color: {{ $settings['arrows_color'] ?? '#000000' }} !important;
    }
    #preview-swiper .swiper-button-prev::after,
    #preview-swiper .swiper-button-next::after,
    #preview-swiper.swiper .swiper-button-prev::after,
    #preview-swiper.swiper .swiper-button-next::after {
        color: {{ $settings['arrows_color'] ?? '#000000' }} !important;
    }
</style>
@endpush

@section('content')
<div class="app-content">
    <div class="container-fluid">

        {{-- Título y Navegación --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="breadcrumb">
                <a href="{{ route('sliders.index') }}">Sliders</a>
                <span class="mx-2">/</span>
                <span>Editando: {{ e($slider->name) }}</span>
            </div>
            <a href="{{ route('sliders.index') }}" class="btn btn-sm btn-outline-secondary">Volver al Listado</a>
        </div>

        {{-- Alertas --}}
        @include('partials.alerts-sweetalert2')

        <form method="POST" action="{{ route('sliders.update', ['id' => $slider->id]) }}">
	            @csrf
	
		            <div class="row sliders-edit-row">
		
		                {{-- Panel izquierdo (Datos principales y Diapositivas) --}}
		                <div class="col-lg-8 sliders-edit-left">

                    {{-- Información del Slider --}}
                    <div class="card mb-4">
                        <div class="card-header">Información del Slider</div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Nombre del Slider</label>
                                <input type="text" name="name" value="{{ old('name', $slider->name) }}" class="form-control" required>
                            </div>
                        </div>
	                    </div>

	                    {{-- Vista Previa en Tiempo Real (STICKY) --}}
	                    <div class="preview-sticky-placeholder"></div>
	                    <div class="preview-sticky-wrapper">
	                        <div class="card mb-4">
	                            <div class="card-header d-flex justify-content-between align-items-center">
	                                <span><i class="fas fa-eye me-2"></i>Vista Previa en Tiempo Real</span>
	                                <div class="btn-group btn-group-sm">
	                                    <button type="button"
	                                            id="toggle-preview-pin"
	                                            class="btn btn-outline-secondary btn-preview-pin"
	                                            title="Anclar / desanclar preview"
	                                            aria-pressed="true">
	                                        <i class="fas fa-thumbtack"></i>
	                                    </button>
	                                    <button type="button" class="btn btn-outline-secondary" onclick="previewSwiper && previewSwiper.slidePrev()" title="Anterior">
	                                        <i class="fas fa-chevron-left"></i>
	                                    </button>
	                                    <button type="button" class="btn btn-outline-secondary" onclick="previewSwiper && previewSwiper.slideNext()" title="Siguiente">
	                                        <i class="fas fa-chevron-right"></i>
	                                    </button>
	                                </div>
	                            </div>
                            <div class="card-body p-0">
                                <div id="preview-container" class="{{ ($settings['engine'] ?? 'swiper') === 'gallery' ? 'gallery-mode' : '' }}">
                                    <div class="theme-badge" id="theme-badge" style="{{ ($settings['engine'] ?? 'swiper') === 'gallery' ? 'background: rgba(102,126,234,0.9);' : '' }}">
                                        @if(($settings['engine'] ?? 'swiper') === 'gallery')
                                            <i class="fas fa-th me-1"></i>Gallery Mode
                                        @elseif(!empty($settings['theme']))
                                            {{ $settings['theme'] }}
                                        @else
                                            Sin tema
                                        @endif
                                    </div>

                                    {{-- Swiper Preview --}}
                                    <div id="preview-swiper"
                                         class="swiper {{ !empty($settings['theme']) ? 'theme-' . e($settings['theme']) : '' }}"
                                         style="height: {{ (int)($settings['height'] ?? 400) }}px;">
                                        <div class="swiper-wrapper">
                                            @if($slides && count($slides) > 0)
	                                                @foreach($slides as $slide)
		                                                <div class="swiper-slide">
		                                                    <img src="{{ $slide->image_url }}" alt="{{ $slide->title ?? 'Slide' }}">
		                                                    <div class="caption" id="caption-{{ $loop->index }}"
		                                                         data-title-font="{{ !empty($slide->title_font) ? e(preg_replace('/[;\r\n]/', '', $slide->title_font)) : '' }}"
		                                                         data-desc-font="{{ !empty($slide->description_font) ? e(preg_replace('/[;\r\n]/', '', $slide->description_font)) : '' }}"
		                                                         data-title-bold="{{ (!isset($slide->title_bold) || (int)$slide->title_bold === 1) ? '1' : '0' }}"
		                                                         data-title-color="{{ !empty($slide->title_color) ? e($slide->title_color) : '' }}"
		                                                         data-desc-color="{{ !empty($slide->description_color) ? e($slide->description_color) : '' }}"
		                                                         data-btn-custom="{{ (!empty($slide->button_custom) && (int)$slide->button_custom === 1) ? '1' : '0' }}"
		                                                         data-btn-bg="{{ !empty($slide->button_bg_color) ? e($slide->button_bg_color) : '' }}"
		                                                         data-btn-text="{{ !empty($slide->button_text_color) ? e($slide->button_text_color) : '' }}"
		                                                         data-btn-border="{{ !empty($slide->button_border_color) ? e($slide->button_border_color) : '' }}"
		                                                         data-btn2-custom="{{ (!empty($slide->button2_custom) && (int)$slide->button2_custom === 1) ? '1' : '0' }}"
		                                                         data-btn2-bg="{{ !empty($slide->button2_bg_color) ? e($slide->button2_bg_color) : '' }}"
		                                                         data-btn2-text="{{ !empty($slide->button2_text_color) ? e($slide->button2_text_color) : '' }}"
		                                                         data-btn2-border="{{ !empty($slide->button2_border_color) ? e($slide->button2_border_color) : '' }}"
		                                                         data-btn-shape="{{ !empty($slide->button_shape) ? e($slide->button_shape) : 'rounded' }}">
		                                                        <h4 class="caption-title">{{ $slide->title ?? 'Título' }}</h4>
		                                                        <p class="caption-description mb-0">{{ $slide->description ?? '' }}</p>
		                                                        @if((!empty($slide->link_url) && !empty($slide->link_text)) || (!empty($slide->link2_url) && !empty($slide->link2_text)))
		                                                            <div class="mt-3 slider-cta-buttons" data-shape="{{ !empty($slide->button_shape) ? e($slide->button_shape) : 'rounded' }}">
		                                                                @if(!empty($slide->link_url) && !empty($slide->link_text))
		                                                                    <a class="slider-cta-button" data-btn="1" href="{{ $slide->link_url }}" target="{{ $slide->link_target ?? '_self' }}" @if(($slide->link_target ?? '_self') === '_blank') rel="noopener noreferrer" @endif>{{ $slide->link_text }}</a>
		                                                                @endif
		                                                                @if(!empty($slide->link2_url) && !empty($slide->link2_text))
		                                                                    <a class="slider-cta-button secondary" data-btn="2" href="{{ $slide->link2_url }}" target="{{ $slide->link2_target ?? '_self' }}" @if(($slide->link2_target ?? '_self') === '_blank') rel="noopener noreferrer" @endif>{{ $slide->link2_text }}</a>
		                                                                @endif
		                                                            </div>
		                                                        @endif
		                                                    </div>
		                                                </div>
		                                                @endforeach
                                            @else
                                                <div class="swiper-slide">
                                                    <div style="width:100%;height:100%;background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);display:flex;align-items:center;justify-content:center;">
                                                        <div class="text-white text-center">
                                                            <i class="fas fa-images fa-3x mb-3"></i>
                                                            <p>Añade diapositivas para ver el preview</p>
                                                        </div>
                                                    </div>
                                                    <div class="caption">
                                                        <h4 class="caption-title">Título de ejemplo</h4>
                                                        <p class="caption-description mb-0">Descripción de ejemplo</p>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Navegación --}}
                                        <div class="swiper-button-prev"></div>
                                        <div class="swiper-button-next"></div>

                                        {{-- Paginación --}}
                                        <div class="swiper-pagination"></div>
                                    </div>

                                    {{-- Thumbnails para modo Gallery --}}
                                    <div class="thumbs-container">
                                        <div id="thumbs-swiper" class="swiper">
                                            <div class="swiper-wrapper">
                                                @if($slides && count($slides) > 0)
                                                    @foreach($slides as $slide)
                                                    <div class="swiper-slide">
                                                        <img src="{{ $slide->image_url }}" alt="{{ $slide->title ?? 'Thumb' }}">
                                                    </div>
                                                    @endforeach
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-2 bg-light border-top">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Preview actualizado en tiempo real</small>
                                        @if(($settings['autoplay'] ?? '') == 1)
                                            <small id="autoplay-status" class="badge bg-success">Autoplay: ON ({{ $settings['autoplay_delay'] ?? 3000 }}ms)</small>
                                        @else
                                            <small id="autoplay-status" class="badge bg-secondary">Autoplay: OFF</small>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Diapositivas --}}
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>Diapositivas</span>
                            <a href="{{ route('slides.create', ['sliderId' => $slider->id]) }}" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus me-1"></i> Añadir Diapositiva
                            </a>
                        </div>
                        <div class="card-body table-responsive p-0">
                            @if($slides && count($slides) > 0)
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px;">Orden</th>
                                            <th style="width: 100px;">Imagen</th>
                                            <th>Título</th>
                                            <th>Activo</th>
                                            <th class="text-end">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="slides-list-{{ $slider->id }}">
                                        @foreach($slides as $slide)
                                            <tr data-slide-id="{{ $slide->id }}">
                                                <td class="drag-handle" title="Arrastrar para reordenar">
                                                    <i class="fas fa-grip-vertical"></i> {{ $slide->sort_order }}
                                                </td>
                                                <td>
                                                    @if($slide->image_url)
                                                        <img src="{{ $slide->image_url }}" alt="{{ e($slide->title ?? 'Slide') }}" style="max-width: 80px; border-radius: 4px;">
                                                    @else
                                                        <span class="text-muted">Sin imagen</span>
                                                    @endif
                                                </td>
                                                <td>{{ e($slide->title ?? '-') }}</td>
                                                <td>
                                                    @if($slide->is_active)
                                                        <span class="badge bg-success">Sí</span>
                                                    @else
                                                        <span class="badge bg-secondary">No</span>
                                                    @endif
                                                </td>
                                                <td class="text-end">
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="{{ route('slides.edit', ['slideId' => $slide->id]) }}" class="btn btn-outline-primary" title="Editar Diapositiva">
                                                            <i class="fas fa-pencil-alt"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-outline-danger" title="Eliminar Diapositiva"
                                                            onclick="confirmDeleteSlide({{ $slide->id }})">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <div class="p-3 text-center text-muted">
                                    Este slider aún no tiene diapositivas. <a href="{{ route('slides.create', ['sliderId' => $slider->id]) }}">Añade la primera</a>.
                                </div>
                            @endif
                        </div>
                    </div>

                </div>

	                {{-- Panel derecho (Settings) --}}
	                <div class="col-lg-4">

                    {{-- MOTOR --}}
            <div class="card mb-3">
                <div class="card-header">Motor del Slider</div>
                <div class="card-body">
                    <label class="form-label">Selecciona el motor:</label>
                    <select name="settings[engine]" class="form-select">
                        <option value="swiper" @if(($settings['engine'] ?? $slider->engine ?? 'swiper') == 'swiper') selected @endif>Swiper (Estándar)</option>
                        <option value="gallery" @if(($settings['engine'] ?? $slider->engine ?? 'swiper') == 'gallery') selected @endif>Gallery (Miniaturas)</option>
                    </select>
                </div>
            </div>

           {{-- TEMA VISUAL --}}
<div class="card mb-3">
    <div class="card-header">Tema Visual</div>
    <div class="card-body">
        <label class="form-label">Tema</label>
	        <select name="settings[theme]" class="form-select">
	            <option value="">- Sin tema (personalizado) -</option>
	            <optgroup label="Temas Swiper & Gallery">
	                <option value="dark-rounded" @if(($settings['theme'] ?? '') == 'dark-rounded') selected @endif>🌙 Dark Rounded (Glassmorphism)</option>
	                <option value="moon-crescent" @if(($settings['theme'] ?? '') == 'moon-crescent') selected @endif>🌙 Media Luna (Wave)</option>
	                <option value="light-minimal" @if(($settings['theme'] ?? '') == 'light-minimal') selected @endif>☀️ Light Minimal (Limpio)</option>
	                <option value="hero-full" @if(($settings['theme'] ?? '') == 'hero-full') selected @endif>🎬 Hero Full (Cinematic)</option>
	                <option value="neon-glow" @if(($settings['theme'] ?? '') == 'neon-glow') selected @endif>⚡ Neon Glow (Cyberpunk)</option>
	                <option value="elegant-gold" @if(($settings['theme'] ?? '') == 'elegant-gold') selected @endif>👑 Elegant Gold (Luxury)</option>
	                <option value="modern-gradient" @if(($settings['theme'] ?? '') == 'modern-gradient') selected @endif>🌈 Modern Gradient (Vibrante)</option>
	                <option value="rounded-shadow" @if(($settings['theme'] ?? '') == 'rounded-shadow') selected @endif>🧾 Rounded Shadow (Tarjeta)</option>
	            </optgroup>
            <optgroup label="Temas Gallery">
                <option value="gallery-light" @if(($settings['theme'] ?? '') == 'gallery-light') selected @endif>📸 Gallery Light (Claro)</option>
                <option value="gallery-dark" @if(($settings['theme'] ?? '') == 'gallery-dark') selected @endif>🎞️ Gallery Dark (Oscuro)</option>
            </optgroup>
        </select>
    </div>
</div>

            {{-- TAMAÑO --}}
            <div class="card mb-3">
                <div class="card-header">Tamaño</div>
                <div class="card-body">
                    <label class="form-label">Altura del slider (px)</label>
                    <input type="number"
                           class="form-control"
                           name="settings[height]"
                           min="120"
                           max="1200"
                           step="10"
                           value="{{ (int)($settings['height'] ?? 400) }}">
                    <small class="form-text text-muted d-block">Ejemplos: 400 (actual), 500, 600, 700… Aplica al render del shortcode.</small>
                </div>
            </div>

            {{-- REPRODUCCIÓN --}}
            <div class="card mb-3">
                <div class="card-header">Reproducción</div>
                <div class="card-body">

                    {{-- Autoplay --}}
                    <div class="form-check mb-2">
                        <input type="hidden" name="settings[autoplay]" value="0">
                        <input type="checkbox" class="form-check-input" name="settings[autoplay]" value="1" @if(($settings['autoplay'] ?? '') == 1) checked @endif>
                        <label class="form-check-label">Activar autoplay</label>
                    </div>

                    {{-- Autoplay Delay --}}
                    <div class="mb-2">
                        <label class="form-label">Autoplay Delay (ms)</label>
                        <input type="number" name="settings[autoplay_delay]" value="{{ $settings['autoplay_delay'] ?? 3000 }}" class="form-control">
                    </div>

                    {{-- Loop --}}
                    <div class="form-check mb-2">
                        <input type="hidden" name="settings[loop]" value="0">
                        <input type="checkbox" class="form-check-input" name="settings[loop]" value="1" @if(($settings['loop'] ?? '') == 1) checked @endif>
                        <label class="form-check-label">Activar bucle infinito</label>
                    </div>

                </div>
            </div>

            {{-- NAVEGACIÓN Y FLECHAS --}}
            <div class="card mb-3">
                <div class="card-header">Navegación y Flechas</div>
                <div class="card-body">

                    {{-- Pagination --}}
                    <div class="form-check mb-2">
                        <input type="hidden" name="settings[pagination]" value="0">
                        <input type="checkbox" class="form-check-input" name="settings[pagination]" value="1" @if(($settings['pagination'] ?? '') == 1) checked @endif>
                        <label class="form-check-label">Mostrar paginación</label>
                    </div>

                    {{-- Navigation (Flechas) --}}
                    <div class="form-check mb-2">
                        <input type="hidden" name="settings[navigation]" value="0">
                        <input type="checkbox" class="form-check-input" name="settings[navigation]" value="1" @if(($settings['navigation'] ?? '') == 1) checked @endif>
                        <label class="form-check-label">Mostrar flechas de navegación</label>
                    </div>

                    {{-- Full Width (a sangre) --}}
                    <div class="form-check mb-2">
                        <input type="hidden" name="settings[full_width]" value="0">
                        <input type="checkbox" class="form-check-input" name="settings[full_width]" value="1" @if(($settings['full_width'] ?? '') == 1) checked @endif>
                        <label class="form-check-label">Ancho completo (a sangre)</label>
                        <small class="form-text text-muted d-block">El slider ocupará todo el ancho de la pantalla sin márgenes del container</small>
                    </div>

                  {{-- Estilo de Flechas (actualizado con más opciones) --}}
<div class="mb-2">
    <label class="form-label">Formato de Flechas (solo forma y tamaño)</label>
    <select name="settings[arrows_style]" class="form-select">
        {{-- CÍRCULOS --}}
        <optgroup label="Círculos">
            <option value="">Círculo básico (44px)</option>
            <option value="arrow-circle-shadow" @if(($settings['arrows_style'] ?? '') == 'arrow-circle-shadow') selected @endif>Círculo con sombra (44px)</option>
            <option value="arrow-circle-medium" @if(($settings['arrows_style'] ?? '') == 'arrow-circle-medium') selected @endif>Círculo mediano (48px)</option>
            <option value="arrow-circle-large" @if(($settings['arrows_style'] ?? '') == 'arrow-circle-large') selected @endif>Círculo grande (52px)</option>
            <option value="arrow-circle-blur" @if(($settings['arrows_style'] ?? '') == 'arrow-circle-blur') selected @endif>Círculo con blur (52px glassmorphism)</option>
            <option value="arrow-circle-border" @if(($settings['arrows_style'] ?? '') == 'arrow-circle-border') selected @endif>Círculo con borde (52px)</option>
        </optgroup>
        {{-- CUADRADOS --}}
        <optgroup label="Cuadrados">
            <option value="arrow-square-basic" @if(($settings['arrows_style'] ?? '') == 'arrow-square-basic') selected @endif>Cuadrado (44px)</option>
            <option value="arrow-square-medium" @if(($settings['arrows_style'] ?? '') == 'arrow-square-medium') selected @endif>Cuadrado mediano (48px)</option>
            <option value="arrow-square-large" @if(($settings['arrows_style'] ?? '') == 'arrow-square-large') selected @endif>Cuadrado grande (56px)</option>
            <option value="arrow-square-blur" @if(($settings['arrows_style'] ?? '') == 'arrow-square-blur') selected @endif>Cuadrado con blur (56px glassmorphism)</option>
            <option value="arrow-square-border" @if(($settings['arrows_style'] ?? '') == 'arrow-square-border') selected @endif>Cuadrado con borde (48px)</option>
        </optgroup>
        {{-- REDONDEADOS --}}
        <optgroup label="Redondeados">
            <option value="arrow-rounded-small" @if(($settings['arrows_style'] ?? '') == 'arrow-rounded-small') selected @endif>Redondeado pequeño (40px, radio 8px)</option>
            <option value="arrow-rounded-medium" @if(($settings['arrows_style'] ?? '') == 'arrow-rounded-medium') selected @endif>Redondeado mediano (44px, radio 8px)</option>
            <option value="arrow-rounded-large" @if(($settings['arrows_style'] ?? '') == 'arrow-rounded-large') selected @endif>Redondeado grande (46px, radio 12px)</option>
        </optgroup>
        {{-- MINIMALISTAS --}}
        <optgroup label="Minimalistas">
            <option value="arrow-minimal" @if(($settings['arrows_style'] ?? '') == 'arrow-minimal') selected @endif>Sin fondo pequeño (40px)</option>
            <option value="arrow-minimal-large" @if(($settings['arrows_style'] ?? '') == 'arrow-minimal-large') selected @endif>Sin fondo grande (56px)</option>
        </optgroup>
    </select>
</div>

                    {{-- Color de Flechas --}}
                    <div class="mb-2">
                        <label class="form-label">Color de Flechas</label>
                        <input type="color" name="settings[arrows_color]" value="{{ $settings['arrows_color'] ?? '#000000' }}" class="form-control form-control-color">
                    </div>

                    {{-- Fondo de Flechas (RGBA) --}}
                    <div class="mb-2">
                        <label class="form-label">Fondo de Flechas (RGBA o HEX)</label>

                        {{-- Input oculto para mantener compatibilidad del formulario --}}
                        <input type="hidden" name="settings[arrows_bg_color]" id="arrows_bg_color_input"
                               value="{{ $settings['arrows_bg_color'] ?? 'rgba(255,255,255,0.9)' }}">

                        {{-- Elemento para el color picker --}}
                        <div id="arrows-bg-picker-element" style="
                            height: 38px;
                            border: 1px solid #ced4da;
                            border-radius: 0.25rem;
                            padding: 6px 12px;
                            background-color: {{ $settings['arrows_bg_color'] ?? 'rgba(255,255,255,0.9)' }};
                            color: #000;
                            cursor: pointer;
                            display: flex;
                            align-items: center;
                        ">{{ $settings['arrows_bg_color'] ?? 'rgba(255,255,255,0.9)' }}</div>

                        {{-- Indicador visual del color --}}
                        <div id="arrows-bg-preview" style="
                            margin-top: 8px;
                            padding: 8px;
                            border: 1px solid #ddd;
                            border-radius: 4px;
                            font-size: 12px;
                        ">
                            <div>Color actual: <strong id="arrows-bg-value">{{ $settings['arrows_bg_color'] ?? 'rgba(255,255,255,0.9)' }}</strong></div>
                            <div style="
                                margin-top: 6px;
                                height: 24px;
                                background-color: {{ $settings['arrows_bg_color'] ?? 'rgba(255,255,255,0.9)' }};
                                border-radius: 3px;
                                border: 1px solid #ddd;
                            "></div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- CAPTIONS Y TEXTOS --}}
	            <div class="card mb-3">
	                <div class="card-header">Textos y Descripciones</div>
	                <div class="card-body">
	                    @php
	                        $fontOptions = [
	                            '' => '— Tipografía del tema (default) —',
	                            "'Playfair Display', serif" => 'Playfair Display',
	                            "'Montserrat', sans-serif" => 'Montserrat',
	                            "'Roboto', sans-serif" => 'Roboto',
	                            "'Open Sans', sans-serif" => 'Open Sans',
	                            "'Lato', sans-serif" => 'Lato',
	                            "'Poppins', sans-serif" => 'Poppins',
	                            "'Oswald', sans-serif" => 'Oswald',
	                            "'Raleway', sans-serif" => 'Raleway',
	                        ];
	                    @endphp
	
	                    {{-- Mostrar descripción --}}
	                    <div class="form-check mb-2">
	                        <input type="hidden" name="settings[show_caption]" value="0">
                        <input type="checkbox" class="form-check-input" name="settings[show_caption]" value="1" @if(($settings['show_caption'] ?? '') == 1) checked @endif>
                        <label class="form-check-label">Mostrar descripción</label>
                    </div>

{{-- Fondo caption --}}
<div class="mb-2">
    <label class="form-label">Fondo del texto (RGBA o HEX)</label>
    
    {{-- Input original (oculto para mantener compatibilidad del formulario) --}}
    <input type="hidden" name="settings[caption_bg]" id="caption_bg_input" 
           value="{{ $settings['caption_bg'] ?? 'rgba(0,0,0,0.6)' }}">
           
    {{-- Elemento para el color picker --}}
    <div id="color-picker-element" style="
        height: 38px;
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
        padding: 6px 12px;
        background-color: {{ $settings['caption_bg'] ?? 'rgba(0,0,0,0.6)' }};
        color: #fff;
        cursor: pointer;
        display: flex;
        align-items: center;
    ">{{ $settings['caption_bg'] ?? 'rgba(0,0,0,0.6)' }}</div>
    
    {{-- Indicador visual del color --}}
    <div id="color-preview" style="
        margin-top: 8px;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 12px;
    ">
        <div>Color actual: <strong id="color-value">{{ $settings['caption_bg'] ?? 'rgba(0,0,0,0.6)' }}</strong></div>
        <div style="
            margin-top: 6px;
            height: 24px;
            background-color: {{ $settings['caption_bg'] ?? 'rgba(0,0,0,0.6)' }};
            border-radius: 3px;
        "></div>
    </div>
</div>


	                    {{-- Color caption --}}
	                    <div class="mb-2">
	                        <label class="form-label">Color del texto</label>
	                        <input type="color" name="settings[caption_color]" value="{{ $settings['caption_color'] ?? '#ffffff' }}" class="form-control form-control-color">
	                    </div>

	                    <div class="mb-2">
	                        <label class="form-label">Color global del título</label>
	                        <input type="color" name="settings[caption_title_color]" value="{{ $settings['caption_title_color'] ?? ($settings['caption_color'] ?? '#ffffff') }}" class="form-control form-control-color">
	                    </div>

	                    <div class="mb-2">
	                        <label class="form-label">Color global del subtítulo</label>
	                        <input type="color" name="settings[caption_description_color]" value="{{ $settings['caption_description_color'] ?? ($settings['caption_color'] ?? '#ffffff') }}" class="form-control form-control-color">
	                    </div>

	                    <div class="mb-2">
	                        <label class="form-label">Tipografía global del título</label>
	                        <select name="settings[caption_title_font]" class="form-select">
	                            @php $selectedTitleFont = $settings['caption_title_font'] ?? ''; @endphp
	                            @foreach($fontOptions as $value => $label)
	                                <option value="{{ $value }}" @if((string)$selectedTitleFont === (string)$value) selected @endif>{{ $label }}</option>
	                            @endforeach
	                        </select>
	                        <small class="form-text text-muted d-block">Las tipografías definidas en una diapositiva tienen prioridad.</small>
	                    </div>

	                    <div class="mb-2">
	                        <label class="form-label">Tipografía global del subtítulo</label>
	                        <select name="settings[caption_description_font]" class="form-select">
	                            @php $selectedDescFont = $settings['caption_description_font'] ?? ''; @endphp
	                            @foreach($fontOptions as $value => $label)
	                                <option value="{{ $value }}" @if((string)$selectedDescFont === (string)$value) selected @endif>{{ $label }}</option>
	                            @endforeach
	                        </select>
	                        <small class="form-text text-muted d-block">Las tipografías definidas en una diapositiva tienen prioridad.</small>
	                    </div>

	                    <div class="mt-3 mb-2">
	                        <label class="form-label">Botón (CTA) global: fondo</label>
	                        <input type="color" name="settings[cta_bg_color]" value="{{ $settings['cta_bg_color'] ?? '#1d4ed8' }}" class="form-control form-control-color">
	                    </div>
	                    <div class="mb-2">
	                        <label class="form-label">Botón (CTA) global: texto</label>
	                        <input type="color" name="settings[cta_text_color]" value="{{ $settings['cta_text_color'] ?? '#ffffff' }}" class="form-control form-control-color">
	                    </div>
	                    <div class="mb-2">
	                        <label class="form-label">Botón (CTA) global: borde</label>
	                        <input type="color" name="settings[cta_border_color]" value="{{ $settings['cta_border_color'] ?? '#ffffff' }}" class="form-control form-control-color">
	                    </div>

                    {{-- Tamaño título --}}
                    <div class="mb-2">
                        <label class="form-label">Tamaño del título</label>
                        <input type="text" name="settings[caption_title_size]" value="{{ $settings['caption_title_size'] ?? '28px' }}" class="form-control">
                    </div>

                    {{-- Tamaño descripción --}}
                    <div class="mb-2">
                        <label class="form-label">Tamaño de la descripción</label>
                        <input type="text" name="settings[caption_description_size]" value="{{ $settings['caption_description_size'] ?? '18px' }}" class="form-control">
                    </div>

   {{-- Posición --}}
<div class="mb-2">
    <label class="form-label">Posición del texto</label>
    <select name="settings[caption_position]" class="form-select">
        <option value="bottom-left" @if(($settings['caption_position'] ?? '') == 'bottom-left') selected @endif>Abajo Izquierda</option>
        <option value="bottom-right" @if(($settings['caption_position'] ?? '') == 'bottom-right') selected @endif>Abajo Derecha</option>
        <option value="top-left" @if(($settings['caption_position'] ?? '') == 'top-left') selected @endif>Arriba Izquierda</option>
        <option value="top-right" @if(($settings['caption_position'] ?? '') == 'top-right') selected @endif>Arriba Derecha</option>
        <option value="center" @if(($settings['caption_position'] ?? '') == 'center') selected @endif>Centro Centrado</option>
    </select>
</div>


                    {{-- Efecto de transición --}}
                    <div class="mb-2">
                        <label class="form-label">Efecto de transición</label>
                        <select name="settings[transition_effect]" class="form-select">
                            <option value="slide" @if(($settings['transition_effect'] ?? '') == 'slide') selected @endif>Deslizar</option>
                            <option value="fade" @if(($settings['transition_effect'] ?? '') == 'fade') selected @endif>Desvanecer (Fade)</option>
                            <option value="cube" @if(($settings['transition_effect'] ?? '') == 'cube') selected @endif>Cubo 3D</option>
                            <option value="coverflow" @if(($settings['transition_effect'] ?? '') == 'coverflow') selected @endif>Coverflow</option>
                            <option value="flip" @if(($settings['transition_effect'] ?? '') == 'flip') selected @endif>Voltear (Flip)</option>
                        </select>
                    </div>

                </div>
            </div>

            {{-- BOTÓN GUARDAR --}}
            <div class="mt-4 text-end">
                <button type="submit" class="btn btn-success">Guardar Slider</button>
            </div>

        </div>
    </div>

</div>
</form>

{{-- Formularios de eliminación de slides (fuera del formulario principal) --}}
@if(isset($slides) && count($slides) > 0)
    @foreach($slides as $slide)
        <form id="delete-slide-form-{{ $slide->id }}" action="{{ route('slides.destroy', ['slideId' => $slide->id]) }}" method="POST" style="display: none;">
            @csrf
            <input type="hidden" name="_method" value="DELETE">
        </form>
    @endforeach
@endif

    </div>
{{-- Scripts externos (LOCAL - sin CDN) --}}
<script src="/assets/js/swiper-bundle.min.js"></script>
<script src="/assets/vendor/pickr/pickr.min.js"></script>
<script src="/assets/vendor/sortablejs/Sortable.min.js"></script>
<link rel="stylesheet" href="/assets/vendor/pickr/classic.min.css" />

<script>
// Variables globales
let previewSwiper = null;
let thumbsSwiper = null;
let pickrInstance = null;
let arrowsBgPickrInstance = null;
let captionBgColor = '{{ $settings["caption_bg"] ?? "rgba(0,0,0,0.6)" }}';
let arrowsBgColor = '{{ $settings["arrows_bg_color"] ?? "rgba(255,255,255,0.9)" }}';
let currentEngine = '{{ $settings["engine"] ?? "swiper" }}';
let lastThemeValue = null;
let isApplyingThemePreset = false;

// Nombres de temas para el badge
const themeNames = {
    '': 'Sin tema',
    'dark-rounded': '🌙 Dark Rounded',
    'moon-crescent': '🌙 Media Luna',
    'light-minimal': '☀️ Light Minimal',
    'hero-full': '🎬 Hero Full',
    'neon-glow': '⚡ Neon Glow',
    'elegant-gold': '👑 Elegant Gold',
    'modern-gradient': '🌈 Modern Gradient',
    'rounded-shadow': '🧾 Rounded Shadow',
    'gallery-light': '📸 Gallery Light',
    'gallery-dark': '🎞️ Gallery Dark'
};

// ============================================
// APLICAR PRESET "MEDIA LUNA" (THEME: moon-crescent)
// ============================================
function applyMoonCrescentPreset() {
    if (isApplyingThemePreset) return;
    isApplyingThemePreset = true;

    try {
        // Helpers
        const setCheckbox = (name, checked) => {
            const el = document.querySelector(`input[type="checkbox"][name="${name}"]`);
            if (el) el.checked = !!checked;
        };
        const setValue = (name, value) => {
            const el = document.querySelector(`[name="${name}"]`);
            if (el) el.value = value;
        };
        const setSelect = (name, value) => {
            const el = document.querySelector(`select[name="${name}"]`);
            if (el) el.value = value;
        };

        // Look & layout similar a la referencia
        setValue('settings[height]', '600');
        setCheckbox('settings[full_width]', true);

        // Navegación
        setCheckbox('settings[navigation]', true);
        setCheckbox('settings[pagination]', true);
        setSelect('settings[arrows_style]', 'arrow-minimal-large');
        setValue('settings[arrows_color]', '#ffffff');

        // Fondo de flechas (aunque en minimal se forza transparente)
        const arrowsBg = 'rgba(255,255,255,0)';
        const arrowsBgHidden = document.getElementById('arrows_bg_color_input');
        if (arrowsBgHidden) arrowsBgHidden.value = arrowsBg;
        arrowsBgColor = arrowsBg;
        const arrowsPickerEl = document.getElementById('arrows-bg-picker-element');
        if (arrowsPickerEl) {
            arrowsPickerEl.style.backgroundColor = arrowsBg;
            arrowsPickerEl.textContent = arrowsBg;
        }
        const arrowsBgValueEl = document.getElementById('arrows-bg-value');
        if (arrowsBgValueEl) arrowsBgValueEl.textContent = arrowsBg;
        const arrowsBgPreview = document.getElementById('arrows-bg-preview');
        if (arrowsBgPreview) {
            const colorBox = arrowsBgPreview.querySelector('div:last-child');
            if (colorBox) colorBox.style.backgroundColor = arrowsBg;
        }

        // Texto centrado y limpio (sin caja de fondo)
        setCheckbox('settings[show_caption]', true);
        setSelect('settings[caption_position]', 'center');
        setValue('settings[caption_color]', '#ffffff');
        setValue('settings[caption_title_size]', '56px');
        setValue('settings[caption_description_size]', '18px');

        const captionBg = 'rgba(0,0,0,0)';
        const captionHidden = document.getElementById('caption_bg_input');
        if (captionHidden) captionHidden.value = captionBg;
        captionBgColor = captionBg;
        const captionPickerEl = document.getElementById('color-picker-element');
        if (captionPickerEl) {
            captionPickerEl.style.backgroundColor = captionBg;
            captionPickerEl.textContent = captionBg;
        }
        const captionValueEl = document.getElementById('color-value');
        if (captionValueEl) captionValueEl.textContent = captionBg;
        const captionPreview = document.getElementById('color-preview');
        if (captionPreview) {
            const colorBox = captionPreview.querySelector('div:last-child');
            if (colorBox) colorBox.style.backgroundColor = captionBg;
        }

        // Movimiento suave
        setCheckbox('settings[autoplay]', true);
        setValue('settings[autoplay_delay]', '5000');
        setCheckbox('settings[loop]', true);
        setSelect('settings[transition_effect]', 'slide');
    } finally {
        isApplyingThemePreset = false;
    }

    // Re-aplicar preview con los nuevos valores
    updateHeight();
    updateNavigation();
    updatePagination();
    updateArrowStyle();
    updateArrowColor();
    updateArrowBackgroundColor();
    updateAutoplay();
    updateLoop();
    updateCaptionStyles();
}

// Nombres de estilos de flechas
const arrowStyleNames = {
    '': 'Por defecto',
    'arrow-rounded-white': 'Redondas Blancas',
    'arrow-orange-box': 'Naranja Caja',
    'arrow-simple-blue': 'Azules Minimal',
    'arrow-rounded-black': 'Redondas Negras',
    'arrow-square-blue': 'Cuadradas Azules',
    'arrow-transparent-white': 'Transparentes Blancas'
};

// ============================================
// APLICAR CLASES DE FLECHAS INMEDIATAMENTE (ANTES DE SWIPER)
// ============================================
function applyArrowStylesImmediately() {
    const styleSelect = document.querySelector('select[name="settings[arrows_style]"]');
    const arrowStyleClass = styleSelect?.value || '';

    // Obtener las flechas del preview
    const prevBtn = document.querySelector('#preview-swiper .swiper-button-prev');
    const nextBtn = document.querySelector('#preview-swiper .swiper-button-next');

    if (!prevBtn || !nextBtn) {
        return; // Esperar a que Swiper cree las flechas
    }

    // Lista de todas las clases de estilo de formato (ACTUALIZADA)
    const allStyles = [
        // Círculos
        'arrow-circle-shadow', 'arrow-circle-medium', 'arrow-circle-large',
        'arrow-circle-blur', 'arrow-circle-border',
        // Cuadrados
        'arrow-square-basic', 'arrow-square-medium', 'arrow-square-large',
        'arrow-square-blur', 'arrow-square-border',
        // Redondeados
        'arrow-rounded-small', 'arrow-rounded-medium', 'arrow-rounded-large',
        // Minimalistas
        'arrow-minimal', 'arrow-minimal-large',
        // Clases antiguas (por compatibilidad)
        'arrow-rounded-white', 'arrow-orange-box', 'arrow-simple-blue',
        'arrow-rounded-black', 'arrow-square-blue', 'arrow-transparent-white'
    ];

    // Remover todas las clases de estilo anteriores
    allStyles.forEach(s => {
        prevBtn.classList.remove(s);
        nextBtn.classList.remove(s);
    });

    // Añadir la clase del nuevo formato (si existe)
    if (arrowStyleClass) {
        prevBtn.classList.add(arrowStyleClass);
        nextBtn.classList.add(arrowStyleClass);
    }

    console.log('Formato de flechas aplicado:', arrowStyleClass || 'default');

    // IMPORTANTE: Aplicar colores DESPUÉS del formato
    // Los colores se manejan por separado en updateArrowColor() y updateArrowBackgroundColor()
}

// ============================================
// CONFIRMAR ELIMINACIÓN DE SLIDE
// ============================================
function confirmDeleteSlide(slideId) {
    Swal.fire({
        title: '¿Eliminar diapositiva?',
        text: 'Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.getElementById('delete-slide-form-' + slideId);
            if (form) {
                form.submit();
            } else {
                console.error('Formulario de eliminación no encontrado para slide:', slideId);
                Swal.fire('Error', 'No se pudo encontrar el formulario de eliminación.', 'error');
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Aplicar estilos de flechas ANTES de inicializar Swiper para evitar parpadeo
    applyArrowStylesImmediately();

    // Inicializar Swiper Preview según el motor seleccionado
    initPreviewSwiper();

    // Inicializar Color Pickers
    setTimeout(initColorPicker, 100);
    setTimeout(initArrowsBgColorPicker, 150);

    // Configurar listeners para actualización en tiempo real
    setupRealTimeListeners();

    // Configurar sticky con JavaScript como fallback
    setupStickyPreview();

    // Inicializar drag & drop para reordenar slides
    initSortableSlides();

    // Aplicar configuración inicial inmediatamente (sin delay para evitar parpadeo)
    setTimeout(function() {
        updateTheme(); // IMPORTANTE: Aplicar tema al contenedor para Gallery Light/Dark
        updateNavigation();
        updatePagination();
        updateArrowStyle();
        updateArrowColor();
        updateArrowBackgroundColor(); // IMPORTANTE: Aplicar fondo de flechas también
        updateCaptionStyles();
    }, 0);
});

// ============================================
// INICIALIZAR SORTABLE (DRAG & DROP)
// ============================================
function initSortableSlides() {
    const slidesListId = 'slides-list-{{ $slider->id }}';
    const slidesList = document.getElementById(slidesListId);

    if (!slidesList || typeof Sortable === 'undefined') {
        console.log('SortableJS no disponible o lista no encontrada');
        return;
    }

    new Sortable(slidesList, {
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        dragClass: 'sortable-drag',
        onEnd: function(evt) {
            updateSlideOrder();
        }
    });

    console.log('Sortable inicializado para slides');
}

// ============================================
// ACTUALIZAR ORDEN DE SLIDES VIA AJAX
// ============================================
function updateSlideOrder() {
    const slidesListId = 'slides-list-{{ $slider->id }}';
    const slidesList = document.getElementById(slidesListId);

    if (!slidesList) return;

    const rows = slidesList.querySelectorAll('tr[data-slide-id]');
    const orderData = [];

    rows.forEach((row, index) => {
        const slideId = row.getAttribute('data-slide-id');
        orderData.push({
            id: parseInt(slideId),
            order: index
        });

        // Actualizar el número visible en la celda
        const orderCell = row.querySelector('.drag-handle');
        if (orderCell) {
            orderCell.innerHTML = '<i class="fas fa-grip-vertical"></i> ' + index;
        }
    });

    // Obtener token CSRF
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
                   || document.querySelector('input[name="_csrf"]')?.value
                   || '{{ csrf_token() }}';

    // Enviar al servidor
    fetch('/musedock/sliders/{{ $slider->id }}/slides/order', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ order: orderData, _csrf: csrfToken })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Actualizar también el preview
            updatePreviewSlideOrder(orderData);
            console.log('Orden de slides actualizado');

            // Mostrar notificación sutil
            showToast('Orden actualizado', 'success');
        } else {
            console.error('Error al actualizar orden:', data.message);
            showToast('Error al actualizar orden', 'error');
        }
    })
    .catch(error => {
        console.error('Error de red:', error);
        showToast('Error de conexión', 'error');
    });
}

// Actualizar el orden de slides en el preview
function updatePreviewSlideOrder(orderData) {
    const previewWrapper = document.querySelector('#preview-swiper .swiper-wrapper');
    const thumbsWrapper = document.querySelector('#thumbs-swiper .swiper-wrapper');

    if (!previewWrapper) return;

    // Reordenar slides en el preview según el nuevo orden
    const slides = Array.from(previewWrapper.children);
    const slideMap = {};

    // Crear mapa de slides por índice original
    slides.forEach((slide, index) => {
        slideMap[index] = slide;
    });

    // Limpiar y reordenar (simplificado - recargar para mantener sincronizado)
    if (previewSwiper) {
        previewSwiper.update();
    }
}

// Mostrar notificación toast
function showToast(message, type = 'info') {
    // Si SweetAlert2 está disponible, usar toast
    if (typeof Swal !== 'undefined') {
        const Toast = Swal.mixin({
            toast: true,
            position: 'bottom-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });

        Toast.fire({
            icon: type === 'error' ? 'error' : 'success',
            title: message
        });
    } else {
        console.log(message);
    }
}

// ============================================
// CONFIGURAR STICKY PREVIEW CON JAVASCRIPT
// ============================================
function setupStickyPreview() {
    const stickyWrapper = document.querySelector('.preview-sticky-wrapper');
    const placeholder = document.querySelector('.preview-sticky-placeholder');
    const leftCol = stickyWrapper?.closest('.sliders-edit-left');
    const pinBtn = document.getElementById('toggle-preview-pin');

    if (!stickyWrapper || !placeholder || !leftCol) return;

    const topOffset = 16;

    // Si un ancestro tiene scroll interno (AdminLTE: main.content), window.scrollY se queda en 0.
    // Detectamos el "scroll container" real de forma robusta.
    const getScrollContainer = (startEl) => {
        let node = startEl.parentElement;
        while (node) {
            const style = window.getComputedStyle(node);
            const overflowY = style.overflowY;
            if ((overflowY === 'auto' || overflowY === 'scroll' || overflowY === 'overlay') && node.scrollHeight > node.clientHeight) {
                return node;
            }
            node = node.parentElement;
        }
        return document.querySelector('main.content') || null;
    };

    const scrollContainer = getScrollContainer(stickyWrapper);
    const usesWindow = !scrollContainer || scrollContainer === document.body || scrollContainer === document.documentElement;

    const getScrollTop = () => {
        if (usesWindow) return window.pageYOffset || document.documentElement.scrollTop || 0;
        return scrollContainer.scrollTop || 0;
    };

    const getScrollerTopInViewport = () => {
        if (usesWindow) return 0;
        return scrollContainer.getBoundingClientRect().top;
    };

    // Guardar estilo original por si existe
    const originalInlineStyle = stickyWrapper.getAttribute('style') || '';

    let isFixed = false;
    let rafId = null;
    const storageKey = 'musedock.sliderPreviewPinned';
    let pinEnabled = true;

    const readPinned = () => {
        try {
            const v = window.localStorage.getItem(storageKey);
            // default: true (pinned)
            if (v === null) return true;
            return v === '1';
        } catch (_) {
            return true;
        }
    };

    const writePinned = (value) => {
        try {
            window.localStorage.setItem(storageKey, value ? '1' : '0');
        } catch (_) {
            // ignore
        }
    };

    const syncPinBtn = () => {
        if (!pinBtn) return;
        pinBtn.classList.toggle('active', pinEnabled);
        pinBtn.setAttribute('aria-pressed', pinEnabled ? 'true' : 'false');
        pinBtn.title = pinEnabled ? 'Preview anclado (clic para desanclar)' : 'Preview desanclado (clic para anclar)';
    };

    const applyPosition = () => {
        const leftRect = leftCol.getBoundingClientRect();
        const scrollerTop = Math.max(getScrollerTopInViewport(), 0);

        stickyWrapper.style.position = 'fixed';
        stickyWrapper.style.top = (scrollerTop + topOffset) + 'px';
        stickyWrapper.style.left = leftRect.left + 'px';
        stickyWrapper.style.width = leftRect.width + 'px';
        stickyWrapper.style.zIndex = '1100';
    };

    // Solución extra-robusta: cuando está fixed, movemos el nodo al body para evitar
    // conflictos de "containing blocks" por overflow/transform en ancestros.
    const fix = () => {
        if (isFixed) return;
        isFixed = true;

        placeholder.style.height = stickyWrapper.offsetHeight + 'px';
        placeholder.classList.add('is-active');
        stickyWrapper.classList.add('is-fixed');

        document.body.appendChild(stickyWrapper);
        applyPosition();
    };

    const unfix = () => {
        if (!isFixed) return;
        isFixed = false;

        stickyWrapper.classList.remove('is-fixed');
        stickyWrapper.setAttribute('style', originalInlineStyle);

        placeholder.classList.remove('is-active');
        placeholder.style.height = '';

        // Volver a insertar el preview justo después del placeholder
        placeholder.insertAdjacentElement('afterend', stickyWrapper);
    };

    const onScroll = () => {
        if (rafId) return;
        rafId = window.requestAnimationFrame(() => {
            rafId = null;

            // En móvil/tablet mejor no fijar para evitar problemas de espacio
            if (window.innerWidth < 992) {
                unfix();
                return;
            }

            // Si está desanclado, siempre volver al flujo normal
            if (!pinEnabled) {
                unfix();
                return;
            }

            const scrollTop = getScrollTop();
            const scrollerTop = getScrollerTopInViewport();
            const phRect = placeholder.getBoundingClientRect();
            const phTopInScroll = scrollTop + (phRect.top - scrollerTop);

            if (scrollTop >= phTopInScroll - topOffset) {
                fix();
                applyPosition();
            } else {
                unfix();
            }
        });
    };

    const addScrollListener = (target) => {
        if (!target) return;
        target.addEventListener('scroll', onScroll, { passive: true });
    };

    addScrollListener(usesWindow ? window : scrollContainer);
    // Fallback: por si el layout cambia y el scroll cae en window (o viceversa)
    addScrollListener(window);

    // Toggle anclar/desanclar
    pinEnabled = readPinned();
    syncPinBtn();
    if (pinBtn) {
        pinBtn.addEventListener('click', () => {
            pinEnabled = !pinEnabled;
            writePinned(pinEnabled);
            syncPinBtn();
            onScroll();
        });
    }

    window.addEventListener('resize', () => {
        if (isFixed) applyPosition();
        onScroll();
    });

    // Inicial
    setTimeout(onScroll, 250);
}

// ============================================
// INICIALIZAR SWIPER PREVIEW
// ============================================
function initPreviewSwiper() {
    const swiperEl = document.getElementById('preview-swiper');
    if (!swiperEl) return;

    // Destruir instancias anteriores si existen
    if (previewSwiper) {
        previewSwiper.destroy(true, true);
        previewSwiper = null;
    }
    if (thumbsSwiper) {
        thumbsSwiper.destroy(true, true);
        thumbsSwiper = null;
    }

    // Obtener valores iniciales
    const autoplayCheckbox = document.querySelector('input[type="checkbox"][name="settings[autoplay]"]');
    const loopCheckbox = document.querySelector('input[type="checkbox"][name="settings[loop]"]');
    const effectSelect = document.querySelector('select[name="settings[transition_effect]"]');
    const delayInput = document.querySelector('input[name="settings[autoplay_delay]"]');
    const engineSelect = document.querySelector('select[name="settings[engine]"]');

    const isAutoplay = autoplayCheckbox?.checked || false;
    const isLoop = loopCheckbox?.checked || false;
    const effect = effectSelect?.value || 'slide';
    const delay = parseInt(delayInput?.value) || 3000;
    currentEngine = engineSelect?.value || 'swiper';

    // Mapear efectos
    let swiperEffect = effect;
    if (effect === 'parallax') swiperEffect = 'slide';

    // Configuración base del Swiper
    const swiperConfig = {
        loop: isLoop,
        effect: swiperEffect,
        autoplay: isAutoplay ? { delay: delay, disableOnInteraction: false } : false,
        speed: 600,
        pagination: {
            el: '#preview-swiper .swiper-pagination',
            clickable: true
        },
        navigation: {
            nextEl: '#preview-swiper .swiper-button-next',
            prevEl: '#preview-swiper .swiper-button-prev'
        },
        on: {
            slideChange: function() {
                updateCaptionStyles();
            }
        }
    };

    // Configuración específica para efectos
    if (swiperEffect === 'fade') {
        swiperConfig.fadeEffect = { crossFade: true };
    } else if (swiperEffect === 'cube') {
        swiperConfig.cubeEffect = {
            shadow: true,
            slideShadows: true,
            shadowOffset: 20,
            shadowScale: 0.94
        };
    } else if (swiperEffect === 'coverflow') {
        swiperConfig.coverflowEffect = {
            rotate: 50,
            stretch: 0,
            depth: 100,
            modifier: 1,
            slideShadows: true
        };
    } else if (swiperEffect === 'flip') {
        swiperConfig.flipEffect = {
            slideShadows: true
        };
    }

    // Si es modo Gallery, primero inicializar thumbnails
    if (currentEngine === 'gallery') {
        const thumbsEl = document.getElementById('thumbs-swiper');
        if (thumbsEl) {
            thumbsSwiper = new Swiper('#thumbs-swiper', {
                spaceBetween: 10,
                slidesPerView: 'auto',
                freeMode: true,
                watchSlidesProgress: true,
                centerInsufficientSlides: true
            });

            // Añadir thumbs al config principal
            swiperConfig.thumbs = {
                swiper: thumbsSwiper
            };
        }
    }

    // Inicializar Swiper principal
    previewSwiper = new Swiper('#preview-swiper', swiperConfig);

    console.log('Swiper Preview inicializado en modo:', currentEngine);
}

// ============================================
// INICIALIZAR COLOR PICKER RGBA
// ============================================
function initColorPicker() {
    const pickerElement = document.getElementById('color-picker-element');
    const hiddenInput = document.getElementById('caption_bg_input');
    const colorPreview = document.getElementById('color-preview');
    const colorValueEl = document.getElementById('color-value');

    if (!pickerElement || !hiddenInput) {
        console.log('Color picker elements not found');
        return;
    }

    // Usar el color inicial guardado en la variable global
    const initialColor = captionBgColor || hiddenInput.value || 'rgba(0,0,0,0.6)';
    console.log('Inicializando color picker con:', initialColor);

    // Actualizar el hidden input con el valor inicial
    hiddenInput.value = initialColor;

    pickrInstance = Pickr.create({
        el: pickerElement,
        theme: 'classic',
        default: initialColor,
        swatches: [
            'rgba(0, 0, 0, 0.6)',
            'rgba(0, 0, 0, 0.8)',
            'rgba(255, 255, 255, 0.8)',
            'rgba(255, 102, 0, 0.8)',
            'rgba(51, 153, 255, 0.8)',
            'rgba(212, 175, 55, 0.9)'
        ],
        components: {
            preview: true,
            opacity: true,
            hue: true,
            interaction: { hex: true, rgba: true, input: true, save: true }
        }
    });

    function updateColorDisplay(color) {
        try {
            const rgbaArray = color.toRGBA();
            const rgbaColor = `rgba(${Math.round(rgbaArray[0])}, ${Math.round(rgbaArray[1])}, ${Math.round(rgbaArray[2])}, ${rgbaArray[3].toFixed(2)})`;

            // Actualizar variable global
            captionBgColor = rgbaColor;

            // Actualizar hidden input
            hiddenInput.value = rgbaColor;

            // Actualizar displays visuales
            if (colorValueEl) colorValueEl.textContent = rgbaColor;
            if (colorPreview) {
                const colorBox = colorPreview.querySelector('div:last-child');
                if (colorBox) colorBox.style.backgroundColor = rgbaColor;
            }

            // Actualizar preview
            updateCaptionStyles();
            console.log('Color de caption actualizado:', rgbaColor);
        } catch (error) {
            console.error("Error al actualizar color:", error);
        }
    }

    pickrInstance.on('change', updateColorDisplay);
    pickrInstance.on('save', (color, instance) => {
        updateColorDisplay(color);
        instance.hide();
    });

    // Aplicar el color inicial al preview inmediatamente
    setTimeout(() => {
        updateCaptionStyles();
    }, 200);
}

// ============================================
// INICIALIZAR COLOR PICKER PARA FONDO DE FLECHAS
// ============================================
function initArrowsBgColorPicker() {
    const pickerElement = document.getElementById('arrows-bg-picker-element');
    const hiddenInput = document.getElementById('arrows_bg_color_input');
    const colorPreview = document.getElementById('arrows-bg-preview');
    const colorValueEl = document.getElementById('arrows-bg-value');

    if (!pickerElement || !hiddenInput) {
        console.log('Arrows BG color picker elements not found');
        return;
    }

    // Usar el color inicial guardado en la variable global
    const initialColor = arrowsBgColor || hiddenInput.value || 'rgba(255,255,255,0.9)';
    console.log('Inicializando arrows bg color picker con:', initialColor);

    // Actualizar el hidden input con el valor inicial
    hiddenInput.value = initialColor;

    arrowsBgPickrInstance = Pickr.create({
        el: pickerElement,
        theme: 'classic',
        default: initialColor,
        swatches: [
            'rgba(255, 255, 255, 0.9)',
            'rgba(255, 255, 255, 0.7)',
            'rgba(0, 0, 0, 0.7)',
            'rgba(0, 0, 0, 0.5)',
            'rgba(51, 153, 255, 0.8)',
            'rgba(255, 102, 0, 0.8)'
        ],
        components: {
            preview: true,
            opacity: true,
            hue: true,
            interaction: { hex: true, rgba: true, input: true, save: true }
        }
    });

    function updateArrowsBgColorDisplay(color) {
        try {
            const rgbaArray = color.toRGBA();
            const rgbaColor = `rgba(${Math.round(rgbaArray[0])}, ${Math.round(rgbaArray[1])}, ${Math.round(rgbaArray[2])}, ${rgbaArray[3].toFixed(2)})`;

            // Actualizar variable global
            arrowsBgColor = rgbaColor;

            // Actualizar hidden input
            hiddenInput.value = rgbaColor;

            // Actualizar displays visuales
            if (colorValueEl) colorValueEl.textContent = rgbaColor;
            if (colorPreview) {
                const colorBox = colorPreview.querySelector('div:last-child');
                if (colorBox) colorBox.style.backgroundColor = rgbaColor;
            }

            // Actualizar preview de las flechas
            updateArrowBackgroundColor();
            console.log('Color de fondo de flechas actualizado:', rgbaColor);
        } catch (error) {
            console.error("Error al actualizar color de flechas:", error);
        }
    }

    arrowsBgPickrInstance.on('change', updateArrowsBgColorDisplay);
    arrowsBgPickrInstance.on('save', (color, instance) => {
        updateArrowsBgColorDisplay(color);
        instance.hide();
    });

    // Aplicar el color inicial al preview inmediatamente
    setTimeout(() => {
        updateArrowBackgroundColor();
    }, 200);
}

// ============================================
// ACTUALIZAR COLOR DE FONDO DE FLECHAS EN PREVIEW
// ============================================
function updateArrowBackgroundColor() {
    const bgColor = arrowsBgColor || 'rgba(255,255,255,0.9)';

    // Verificar si el estilo actual es minimalista (sin fondo)
    const styleSelect = document.querySelector('select[name="settings[arrows_style]"]');
    const currentStyle = styleSelect?.value || '';
    const isMinimal = currentStyle.includes('arrow-minimal');

    // Crear o actualizar el estilo CSS para el fondo de las flechas
    let arrowBgStyle = document.getElementById('custom-arrow-bg-color');
    if (!arrowBgStyle) {
        arrowBgStyle = document.createElement('style');
        arrowBgStyle.id = 'custom-arrow-bg-color';
        document.head.appendChild(arrowBgStyle);
    }

    // Si es minimalista, forzar fondo transparente; si no, aplicar color de fondo
    if (isMinimal) {
        arrowBgStyle.textContent = `
            #preview-swiper .swiper-button-prev,
            #preview-swiper .swiper-button-next,
            #preview-swiper.swiper .swiper-button-prev,
            #preview-swiper.swiper .swiper-button-next {
                background-color: transparent !important;
                background: transparent !important;
                box-shadow: none !important;
            }
        `;
        console.log('Fondo de flechas: TRANSPARENTE (estilo minimalista)');
    } else {
        arrowBgStyle.textContent = `
            #preview-swiper .swiper-button-prev,
            #preview-swiper .swiper-button-next,
            #preview-swiper.swiper .swiper-button-prev,
            #preview-swiper.swiper .swiper-button-next {
                background-color: ${bgColor} !important;
            }
        `;
        console.log('Fondo de flechas aplicado:', bgColor);
    }
}

// ============================================
// CONFIGURAR LISTENERS EN TIEMPO REAL
// ============================================
function setupRealTimeListeners() {
    // Checkboxes - actualizar inmediatamente
    document.querySelectorAll('input[type="checkbox"]').forEach(input => {
        input.addEventListener('change', handleSettingChange);
    });

    // Selects - actualizar inmediatamente
    document.querySelectorAll('select').forEach(select => {
        select.addEventListener('change', handleSettingChange);
    });

    // Inputs de texto y número - con debounce
    document.querySelectorAll('input[type="text"], input[type="number"]').forEach(input => {
        input.addEventListener('input', debounce(handleSettingChange, 200));
    });

    // Inputs de color - actualizar inmediatamente
    document.querySelectorAll('input[type="color"]').forEach(input => {
        input.addEventListener('input', handleSettingChange);
    });
}

// ============================================
// MANEJAR CAMBIO DE CONFIGURACIÓN
// ============================================
function handleSettingChange(e) {
    const name = e.target.name;
    console.log('Setting changed:', name, e.target.type === 'checkbox' ? e.target.checked : e.target.value);

    // Actualizar según el tipo de configuración
    if (name.includes('engine')) {
        updateEngine();
    } else if (name.includes('theme')) {
        updateTheme();
    } else if (name.includes('height')) {
        updateHeight();
    } else if (name.includes('arrows_style')) {
        updateArrowStyle();
        // Después de cambiar estilo, aplicar colores (fondo transparente si es minimalista)
        setTimeout(() => {
            updateArrowColor();
            updateArrowBackgroundColor();
        }, 50);
    } else if (name.includes('navigation')) {
        updateNavigation();
    } else if (name.includes('pagination')) {
        updatePagination();
    } else if (name.includes('autoplay')) {
        updateAutoplay();
    } else if (name.includes('loop')) {
        updateLoop();
    } else if (name.includes('transition_effect')) {
        updateEffect();
    } else if (name.includes('caption') || name.includes('show_caption')) {
        updateCaptionStyles();
    } else if (name.includes('arrows_color')) {
        updateArrowColor();
    }
}

// ============================================
// ACTUALIZAR MOTOR (Swiper/Gallery)
// ============================================
function updateEngine() {
    const engineSelect = document.querySelector('select[name="settings[engine]"]');
    const engine = engineSelect?.value || 'swiper';
    const container = document.getElementById('preview-container');
    const badge = document.getElementById('theme-badge');

    // Solo reinicializar si cambió el motor
    if (engine !== currentEngine) {
        currentEngine = engine;

        if (engine === 'gallery') {
            // Mostrar indicador de Gallery
            if (badge) {
                badge.innerHTML = '<i class="fas fa-th me-1"></i>Gallery Mode';
                badge.style.background = 'rgba(102,126,234,0.9)';
            }
            // Añadir clase para estilos de gallery
            if (container) container.classList.add('gallery-mode');

            console.log('Motor Gallery seleccionado - Vista con miniaturas');
        } else {
            // Modo Swiper normal
            if (container) container.classList.remove('gallery-mode');
            if (badge) {
                badge.style.background = 'rgba(0,0,0,0.7)';
            }
            updateTheme(); // Restaurar el badge del tema
        }

        // Reinicializar el Swiper con el nuevo motor
        initPreviewSwiper();

        // Re-aplicar estilos después de reinicializar
        setTimeout(() => {
            updateNavigation();
            updatePagination();
            updateArrowStyle();
            updateArrowColor();
            updateCaptionStyles();
        }, 100);
    }

    console.log('Motor actualizado:', engine);
}

// ============================================
// ACTUALIZAR TEMA VISUAL
// ============================================
function updateTheme() {
    const themeSelect = document.querySelector('select[name="settings[theme]"]');
    const theme = themeSelect?.value || '';
    const swiperEl = document.getElementById('preview-swiper');
    const previewContainer = document.getElementById('preview-container');
    const thumbsContainer = document.querySelector('.thumbs-container');
    const badge = document.getElementById('theme-badge');

    if (!swiperEl) return;

    // Remover clases de tema anteriores del swiper
    swiperEl.className = swiperEl.className.replace(/theme-[\w-]+/g, '').trim();

    // Remover clases de tema anteriores del contenedor (para afectar thumbs)
    if (previewContainer) {
        previewContainer.className = previewContainer.className.replace(/theme-[\w-]+/g, '').trim();
    }

    // Añadir nuevo tema al swiper y al contenedor
    if (theme) {
        swiperEl.classList.add('theme-' + theme);
        if (previewContainer) {
            previewContainer.classList.add('theme-' + theme);
        }
    }

    // Actualizar badge
    if (badge) {
        badge.textContent = themeNames[theme] || 'Sin tema';
    }

    // Aplicar preset solo cuando el usuario CAMBIA el tema (no en el primer render)
    if (lastThemeValue !== null && lastThemeValue !== theme && theme === 'moon-crescent') {
        applyMoonCrescentPreset();
    }
    lastThemeValue = theme;

    console.log('Tema actualizado:', theme);
}

// ============================================
// ACTUALIZAR ALTURA DEL PREVIEW
// ============================================
function updateHeight() {
    const heightInput = document.querySelector('input[name="settings[height]"]');
    const height = Math.max(120, Math.min(1200, parseInt(heightInput?.value || '400', 10)));
    const swiperEl = document.getElementById('preview-swiper');
    if (swiperEl) {
        swiperEl.style.height = height + 'px';
    }

    // Swiper necesita update() si cambia tamaño
    if (window.previewSwiper && typeof window.previewSwiper.update === 'function') {
        window.previewSwiper.update();
    }
}

// ============================================
// ACTUALIZAR ESTILO DE FLECHAS
// ============================================
function updateArrowStyle() {
    const styleSelect = document.querySelector('select[name="settings[arrows_style]"]');
    const style = styleSelect?.value || '';

    const prevBtn = document.querySelector('#preview-swiper .swiper-button-prev');
    const nextBtn = document.querySelector('#preview-swiper .swiper-button-next');

    if (!prevBtn || !nextBtn) return;

    // Lista de todas las clases de estilo de flechas (ACTUALIZADA)
    const allStyles = [
        // Círculos
        'arrow-circle-shadow', 'arrow-circle-medium', 'arrow-circle-large',
        'arrow-circle-blur', 'arrow-circle-border',
        // Cuadrados
        'arrow-square-basic', 'arrow-square-medium', 'arrow-square-large',
        'arrow-square-blur', 'arrow-square-border',
        // Redondeados
        'arrow-rounded-small', 'arrow-rounded-medium', 'arrow-rounded-large',
        // Minimalistas
        'arrow-minimal', 'arrow-minimal-large',
        // Clases antiguas (por compatibilidad)
        'arrow-rounded-white', 'arrow-orange-box', 'arrow-simple-blue',
        'arrow-rounded-black', 'arrow-square-blue', 'arrow-transparent-white'
    ];

    // Remover todas las clases de estilo anteriores
    allStyles.forEach(s => {
        prevBtn.classList.remove(s);
        nextBtn.classList.remove(s);
    });

    // Limpiar cualquier estilo inline previo
    [prevBtn, nextBtn].forEach(btn => {
        btn.style.removeProperty('background');
        btn.style.removeProperty('color');
        btn.style.removeProperty('border-radius');
        btn.style.removeProperty('text-shadow');
    });

    // Limpiar hojas de estilo dinámicas previas
    const oldDynamicStyle = document.getElementById('dynamic-arrow-style');
    if (oldDynamicStyle) oldDynamicStyle.remove();

    // Añadir la clase del nuevo estilo (si existe)
    // Los estilos se aplicarán desde slider-themes.css
    if (style) {
        prevBtn.classList.add(style);
        nextBtn.classList.add(style);
    }

    // Ya NO aplicamos estilos inline - confiamos 100% en slider-themes.css
    console.log('Estilo de flechas actualizado:', style || 'default');
}

// ============================================
// ACTUALIZAR COLOR DE FLECHAS
// ============================================
function updateArrowColor() {
    const colorInput = document.querySelector('input[name="settings[arrows_color]"]');
    const color = colorInput?.value || '#000000';

    // Obtener o crear la hoja de estilos para color de flechas
    let colorStyle = document.getElementById('custom-arrow-color');
    if (!colorStyle) {
        colorStyle = document.createElement('style');
        colorStyle.id = 'custom-arrow-color';
        document.head.appendChild(colorStyle);
    }

    // SIEMPRE aplicar el color seleccionado con máxima especificidad
    // Aplicar DESPUÉS de #preview-swiper para ganar especificidad
    colorStyle.textContent = `
        #preview-swiper.swiper .swiper-button-prev,
        #preview-swiper.swiper .swiper-button-next,
        #preview-swiper .swiper-button-prev,
        #preview-swiper .swiper-button-next,
        #preview-swiper.swiper .swiper-button-prev::after,
        #preview-swiper.swiper .swiper-button-next::after,
        #preview-swiper .swiper-button-prev::after,
        #preview-swiper .swiper-button-next::after {
            color: ${color} !important;
        }
    `;

    console.log('Color de flechas aplicado:', color);
}

// ============================================
// ACTUALIZAR NAVEGACIÓN (FLECHAS)
// ============================================
function updateNavigation() {
    const checkbox = document.querySelector('input[type="checkbox"][name="settings[navigation]"]');
    const isVisible = checkbox?.checked || false;

    const prevBtn = document.querySelector('#preview-swiper .swiper-button-prev');
    const nextBtn = document.querySelector('#preview-swiper .swiper-button-next');

    if (prevBtn) {
        prevBtn.classList.toggle('nav-hidden', !isVisible);
        prevBtn.style.setProperty('display', isVisible ? 'flex' : 'none', 'important');
    }
    if (nextBtn) {
        nextBtn.classList.toggle('nav-hidden', !isVisible);
        nextBtn.style.setProperty('display', isVisible ? 'flex' : 'none', 'important');
    }

    console.log('Navegación:', isVisible ? 'visible' : 'oculta');
}

// ============================================
// ACTUALIZAR PAGINACIÓN
// ============================================
function updatePagination() {
    const checkbox = document.querySelector('input[type="checkbox"][name="settings[pagination]"]');
    const isVisible = checkbox?.checked || false;

    const pagination = document.querySelector('#preview-swiper .swiper-pagination');
    if (pagination) {
        pagination.classList.toggle('pag-hidden', !isVisible);
        pagination.style.setProperty('display', isVisible ? 'block' : 'none', 'important');
    }

    console.log('Paginación:', isVisible ? 'visible' : 'oculta');
}

// ============================================
// ACTUALIZAR AUTOPLAY
// ============================================
function updateAutoplay() {
    if (!previewSwiper) return;

    const checkbox = document.querySelector('input[type="checkbox"][name="settings[autoplay]"]');
    const delayInput = document.querySelector('input[name="settings[autoplay_delay]"]');
    const isEnabled = checkbox?.checked || false;
    const delay = parseInt(delayInput?.value) || 3000;

    const statusBadge = document.getElementById('autoplay-status');

    if (isEnabled) {
        previewSwiper.params.autoplay = { delay: delay, disableOnInteraction: false };
        previewSwiper.autoplay.start();
        if (statusBadge) {
            statusBadge.textContent = 'Autoplay: ON (' + delay + 'ms)';
            statusBadge.className = 'badge bg-success';
        }
    } else {
        previewSwiper.autoplay.stop();
        if (statusBadge) {
            statusBadge.textContent = 'Autoplay: OFF';
            statusBadge.className = 'badge bg-secondary';
        }
    }

    console.log('Autoplay:', isEnabled ? 'activado' : 'desactivado');
}

// ============================================
// ACTUALIZAR LOOP
// ============================================
function updateLoop() {
    if (!previewSwiper) return;

    const checkbox = document.querySelector('input[type="checkbox"][name="settings[loop]"]');
    const isLoop = checkbox?.checked || false;

    // Swiper requiere reinicialización para cambiar loop
    previewSwiper.params.loop = isLoop;
    previewSwiper.update();

    console.log('Loop:', isLoop ? 'activado' : 'desactivado');
}

// ============================================
// ACTUALIZAR EFECTO DE TRANSICIÓN
// ============================================
function updateEffect() {
    const select = document.querySelector('select[name="settings[transition_effect]"]');
    const effect = select?.value || 'slide';

    // Para cambiar el efecto, necesitamos reinicializar Swiper
    // porque fade/cube/coverflow requieren reinicialización completa
    initPreviewSwiper();

    // Re-aplicar estilos después de reinicializar
    setTimeout(() => {
        updateNavigation();
        updatePagination();
        updateArrowStyle();
        updateArrowColor();
        updateCaptionStyles();
    }, 100);

    console.log('Efecto actualizado:', effect);
}

// ============================================
// ACTUALIZAR ESTILOS DE CAPTION
// ============================================
function updateCaptionStyles() {
    const showCaptionCheckbox = document.querySelector('input[type="checkbox"][name="settings[show_caption]"]');
    // Usar la variable global captionBgColor que se actualiza con Pickr
    const captionBg = captionBgColor || document.getElementById('caption_bg_input')?.value || 'rgba(0,0,0,0.6)';
    const captionColor = document.querySelector('input[name="settings[caption_color]"]')?.value || '#ffffff';
    const globalTitleColor = document.querySelector('input[name="settings[caption_title_color]"]')?.value || captionColor;
    const globalDescColor = document.querySelector('input[name="settings[caption_description_color]"]')?.value || captionColor;
    const titleSize = document.querySelector('input[name="settings[caption_title_size]"]')?.value || '28px';
    const descSize = document.querySelector('input[name="settings[caption_description_size]"]')?.value || '18px';
    const position = document.querySelector('select[name="settings[caption_position]"]')?.value || 'bottom-left';
    const globalTitleFont = document.querySelector('select[name="settings[caption_title_font]"]')?.value || '';
    const globalDescFont = document.querySelector('select[name="settings[caption_description_font]"]')?.value || '';
    const globalBtnBg = document.querySelector('input[name="settings[cta_bg_color]"]')?.value || '#1d4ed8';
    const globalBtnText = document.querySelector('input[name="settings[cta_text_color]"]')?.value || '#ffffff';
    const globalBtnBorder = document.querySelector('input[name="settings[cta_border_color]"]')?.value || '#ffffff';

    const isVisible = showCaptionCheckbox?.checked || false;

    console.log('Aplicando caption background:', captionBg);

    // Aplicar a todos los captions
    document.querySelectorAll('#preview-swiper .caption').forEach(caption => {
        // Visibilidad
        caption.style.display = isVisible ? 'block' : 'none';

        // Estilos de fondo y color
        caption.style.setProperty('background', captionBg, 'important');
        caption.style.color = captionColor;
        caption.style.padding = '15px 20px';
        caption.style.maxWidth = '60%';

        // Resetear posición
        caption.style.top = '';
        caption.style.bottom = '';
        caption.style.left = '';
        caption.style.right = '';
        caption.style.transform = '';
        caption.style.textAlign = '';

        // Remover clases de posición anteriores
        caption.className = caption.className.replace(/position-[\w-]+/g, 'caption').trim();

        // Aplicar posición
        switch(position) {
            case 'bottom-left':
                caption.style.bottom = '60px';
                caption.style.left = '20px';
                break;
            case 'bottom-right':
                caption.style.bottom = '60px';
                caption.style.right = '20px';
                break;
            case 'top-left':
                caption.style.top = '20px';
                caption.style.left = '20px';
                break;
            case 'top-right':
                caption.style.top = '20px';
                caption.style.right = '20px';
                break;
            case 'center':
                caption.style.top = '50%';
                caption.style.left = '50%';
                caption.style.transform = 'translate(-50%, -50%)';
                caption.style.textAlign = 'center';
                caption.style.maxWidth = '80%';
                break;
        }

        // Aplicar tamaños de texto
        const title = caption.querySelector('.caption-title');
        const desc = caption.querySelector('.caption-description');
        if (title) {
            title.style.fontSize = titleSize;
            const slideTitleColor = caption.dataset.titleColor || '';
            title.style.color = slideTitleColor || globalTitleColor || captionColor;
            title.style.margin = '0';
            const slideTitleFont = caption.dataset.titleFont || '';
            const slideTitleBold = caption.dataset.titleBold;
            const finalTitleFont = slideTitleFont || globalTitleFont;
            if (finalTitleFont) title.style.fontFamily = finalTitleFont;
            if (slideTitleBold === '0') title.style.fontWeight = '400';
            else if (slideTitleBold === '1') title.style.fontWeight = '800';
        }
        if (desc) {
            desc.style.fontSize = descSize;
            const slideDescColor = caption.dataset.descColor || '';
            desc.style.color = slideDescColor || globalDescColor || captionColor;
            desc.style.marginTop = '8px';
            const slideDescFont = caption.dataset.descFont || '';
            const finalDescFont = slideDescFont || globalDescFont;
            if (finalDescFont) desc.style.fontFamily = finalDescFont;
        }

        // Botón CTA (si existe)
        const btn1 = caption.querySelector('.slider-cta-button[data-btn="1"]');
        if (btn1) {
            const useCustom = caption.dataset.btnCustom === '1';
            const btnBg = (useCustom && caption.dataset.btnBg) ? caption.dataset.btnBg : globalBtnBg;
            const btnText = (useCustom && caption.dataset.btnText) ? caption.dataset.btnText : globalBtnText;
            const btnBorder = (useCustom && caption.dataset.btnBorder) ? caption.dataset.btnBorder : globalBtnBorder;
            btn1.style.backgroundColor = btnBg;
            btn1.style.color = btnText;
            btn1.style.borderColor = btnBorder;
        }

        const btn2 = caption.querySelector('.slider-cta-button[data-btn="2"]');
        if (btn2) {
            const useCustom2 = caption.dataset.btn2Custom === '1';
            const btn2Bg = (useCustom2 && caption.dataset.btn2Bg) ? caption.dataset.btn2Bg : 'transparent';
            const btn2Text = (useCustom2 && caption.dataset.btn2Text) ? caption.dataset.btn2Text : globalBtnText;
            const btn2Border = (useCustom2 && caption.dataset.btn2Border) ? caption.dataset.btn2Border : globalBtnBorder;
            btn2.style.backgroundColor = btn2Bg;
            btn2.style.color = btn2Text;
            btn2.style.borderColor = btn2Border;
        }

        const btnRow = caption.querySelector('.slider-cta-buttons');
        if (btnRow) {
            const shape = (btnRow.dataset.shape || caption.dataset.btnShape || 'rounded');
            btnRow.classList.toggle('shape-square', shape === 'square');
        }
    });
}

// ============================================
// ACTUALIZAR TODO EL PREVIEW
// ============================================
function updateAllPreview() {
    updateEngine();
    updateTheme();
    updateHeight();
    updateArrowStyle();
    // Aplicar color de flechas después del estilo para que tenga prioridad
    setTimeout(updateArrowColor, 100);
    updateNavigation();
    updatePagination();
    updateAutoplay();
    updateCaptionStyles();
    console.log('Preview completo actualizado');
}

// ============================================
// FUNCIÓN DEBOUNCE
// ============================================
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func.apply(this, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

</script>
@endsection

@push('scripts')
@endpush
