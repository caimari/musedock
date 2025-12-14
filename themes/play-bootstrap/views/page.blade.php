@extends('layouts.app')

{{-- === SECCIONES SEO === --}}
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

@section('content')

{{-- Banner de página (si no está oculto) --}}
@if(!isset($post) && isset($customizations))
    @if($customizations->show_slider === true || $customizations->show_slider === 1 || $customizations->show_slider === "1")
    <!-- ====== Banner Start ====== -->
    <section class="ud-page-banner">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="ud-banner-content">
                        <h1>{{ $customizations->slider_title ?? $translation->title }}</h1>
                        @if(!empty($customizations->slider_content))
                        <div class="ud-banner-subtitle mt-3">
                            {!! $customizations->slider_content !!}
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- ====== Banner End ====== -->
    @endif
@endif

{{-- Contenido principal de la página --}}
<section class="ud-page-content py-5{{ isset($post) ? ' ud-page-content-post' : '' }}">
    <div class="{{ isset($post) ? 'container' : ((isset($customizations) ? $customizations->container_class : null) ?? 'container') }}">
        <article class="{{ isset($post) ? 'blog-post-single' : ((isset($customizations) ? $customizations->content_class : null) ?? 'page-content-wrapper') }}">
            @if(isset($post))
                {{-- Es un post de blog - mostrar imagen destacada si no está oculta --}}
                @if($translation->featured_image && !$post->hide_featured_image)
                <div class="featured-image mb-4">
                    @php
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
                    <span><i class="lni lni-calendar"></i> {{ $dateStr }}</span>
                </div>

                {{-- Contenido del post --}}
                <div class="post-content">
                    {!! $translation->content ?? '<p class="text-muted">Contenido no disponible.</p>' !!}
                </div>
            @else
                {{-- Es una página --}}
                {{-- Mostrar el título solo si NO está oculto Y no es la página de inicio --}}
                @if(isset($customizations) && $customizations->hide_title !== true && isset($page) && !$page->is_homepage)
                    <h1 class="page-title mb-4">{{ $translation->title ?? '' }}</h1>
                @endif

                {{-- Renderizar el contenido HTML con filtros aplicados (shortcodes, etc.) --}}
                <div class="page-body">
                    @php
                        $content = apply_filters('the_content', $translation->content ?? '<p class="text-muted">Contenido no disponible.</p>');
                        // Si hay slider activo, eliminar el primer h1, h2 o h3 del contenido para evitar duplicados
                        if (isset($customizations) && ($customizations->show_slider === true || $customizations->show_slider === 1 || $customizations->show_slider === "1")) {
                            $content = preg_replace('/<h[123][^>]*>.*?<\/h[123]>/', '', $content, 1);
                        }
                    @endphp
                    {!! $content !!}
                </div>
            @endif
        </article>
    </div>
</section>

@endsection
