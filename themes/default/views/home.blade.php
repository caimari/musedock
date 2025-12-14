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
        @endif
    @endif

    <div class="{{ (isset($customizations) ? $customizations->container_class : null) ?? 'container py-4 page-container' }}">
        <article class="{{ (isset($customizations) ? $customizations->content_class : null) ?? 'page-content-wrapper' }}">
            @if(isset($customizations) && $customizations->hide_title !== true && isset($page) && !$page->is_homepage)
                <h1 class="page-title">{{ $translation->title ?? '' }}</h1>
            @endif

            <div class="page-body">
                @php
                    $content = apply_filters('the_content', $translation->content ?? $page->content ?? '<p>' . __('home_intro') . '</p>');
                    if (isset($customizations) && ($customizations->show_slider === true || $customizations->show_slider === 1 || $customizations->show_slider === "1")) {
                        $content = preg_replace('/<h[123][^>]*>.*?<\/h[123]>/', '', $content, 1);
                    }
                @endphp
                {!! $content !!}
            </div>
        </article>
    </div>

@endsection
