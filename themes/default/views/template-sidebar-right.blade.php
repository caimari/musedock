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
@endsection

@section('content')

<div class="container py-4 page-with-sidebar">
    <div class="row">
        {{-- Contenido principal --}}
        <div class="col-md-8 col-lg-9">
            <article class="page-content-wrapper">
                {{-- <h1 class="page-title">{{ $translation->title ?? '' }}</h1> --}}
                <div class="page-body">
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
