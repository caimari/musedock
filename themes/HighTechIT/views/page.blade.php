@extends('layouts.app')

{{-- SEO Sections --}}
@section('title')
    {{ ($translation->seo_title ?: $translation->title ?: 'Page') . ' | ' . site_setting('site_name', '') }}
@endsection

@section('keywords')
    {{ $translation->seo_keywords ?? site_setting('site_keywords', '') }}
@endsection

@section('description')
    {{ $translation->seo_description ?? site_setting('site_description', '') }}
@endsection

@section('og_title')
    {{ $translation->seo_title ?: $translation->title ?: 'Page' }}
@endsection

@section('og_description')
    {{ $translation->seo_description ?? site_setting('site_description', '') }}
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
            $ogImageUrl = (str_starts_with($translation->featured_image, '/media/') || str_starts_with($translation->featured_image, 'http'))
                ? $translation->featured_image
                : asset($translation->featured_image);
        @endphp
        <meta property="og:image" content="{{ $ogImageUrl }}" />
    @endif
    <meta name="twitter:card" content="summary_large_image">
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

{{-- Page Header Start --}}
@if(!isset($post) && isset($customizations))
    @if($customizations->show_slider === true || $customizations->show_slider === 1 || $customizations->show_slider === "1")
    <div class="container-fluid page-header py-5">
        <div class="container text-center py-5">
            <h1 class="display-2 text-white mb-4 animated slideInDown">{{ $customizations->slider_title ?? $translation->title }}</h1>
            @if(!empty($customizations->slider_content))
                <div class="text-white fs-5 mb-4 animated slideInDown">
                    {!! $customizations->slider_content !!}
                </div>
            @endif
        </div>
    </div>
    @endif
@endif
{{-- Page Header End --}}

{{-- Main Content --}}
<div class="{{ isset($post) ? 'container py-5' : ((isset($customizations) ? $customizations->container_class : null) ?? 'container py-5 page-container') }}">
    <article class="{{ isset($post) ? 'blog-post-single' : ((isset($customizations) ? $customizations->content_class : null) ?? 'page-content-wrapper') }}">
        @if(isset($post))
            {{-- Blog Post --}}
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

            <h1 class="post-title mb-3">{{ $translation->title }}</h1>

            <div class="post-meta mb-4 text-muted">
                @php
                    $dateVal = $translation->published_at;
                    $dateStr = $dateVal instanceof \DateTime ? $dateVal->format('d/m/Y') : date('d/m/Y', strtotime($dateVal));
                @endphp
                <span><i class="far fa-calendar"></i> {{ $dateStr }}</span>
            </div>

            <div class="post-content">
                {!! $translation->content ?? '<p class="text-muted">Content not available.</p>' !!}
            </div>
        @else
            {{-- Regular Page --}}
            @if(isset($customizations) && $customizations->hide_title !== true && isset($page) && !$page->is_homepage)
                <h1 class="page-title mb-4">{{ $translation->title ?? '' }}</h1>
            @endif

            <div class="page-body">
                @php
                    $content = apply_filters('the_content', $translation->content ?? '<p class="text-muted">Content not available.</p>');
                    // Solo eliminar el primer h1 si hay slider, no h2 ni h3
                    if (isset($customizations) && ($customizations->show_slider === true || $customizations->show_slider === 1 || $customizations->show_slider === "1")) {
                        $content = preg_replace('/<h1[^>]*>.*?<\/h1>/', '', $content, 1);
                    }
                @endphp
                {!! $content !!}
            </div>
        @endif
    </article>
</div>
@endsection
