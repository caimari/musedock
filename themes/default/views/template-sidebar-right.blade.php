@extends('layouts.app')

{{-- SEO --}}
@section('title')
    {{ ($translation->seo_title ?: $translation->title ?: 'Página') . ' | ' . setting('site_name', 'MuseDock CMS') }}
@endsection

@section('keywords')
    {{ $translation->seo_keywords ?? setting('site_keywords', '') }}
@endsection

@section('description')
    {{ $translation->seo_description ?? setting('site_description', '') }}
@endsection

@section('og_title')
    {{ $translation->seo_title ?: $translation->title ?: 'Página' }}
@endsection

@section('og_description')
    {{ $translation->seo_description ?? setting('site_description', '') }}
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

<div class="container py-4 page-with-sidebar">
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
