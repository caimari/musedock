@extends('layouts.app')

@section('title')
    {{ ($translation->seo_title ?? $page->seo_title ?? $translation->title ?? $page->title ?? __('home_title')) . ' | ' . setting('site_name', config('app_name', 'MuseDock CMS')) }}
@endsection

@section('keywords')
    {{ $translation->seo_keywords ?? $page->seo_keywords ?? setting('site_keywords', 'Palabras clave predeterminadas') }}
@endsection

@section('og_title')
    {{ $translation->seo_title ?? $page->seo_title ?? $translation->title ?? $page->title ?? __('home_title') }}
@endsection

@section('og_description')
    {{ $translation->seo_description ?? $page->seo_description ?? setting('site_description', 'Descripción predeterminada') }}
@endsection

@section('content')

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="hero-content">
                    <h1 class="hero-title">
                        {{ $translation->title ?? $page->title ?? __('home_title') }}
                    </h1>
                    <div class="hero-description">
                        {!! apply_filters('the_content', $translation->content ?? $page->content ?? '<p>' . __('home_intro') . '</p>') !!}
                    </div>
                    <div class="hero-actions">
                        <a href="#" class="btn btn-primary">{{ __('home_learn_more') }}</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="hero-image">
                    <img src="{{ asset('themes/sustainable-nextjs-1.0.0/img/hero.svg') }}" alt="{{ $translation->title ?? $page->title ?? __('home_title') }}" class="img-fluid">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features-section py-5">
    <div class="container">
        <div class="row text-center mb-5">
            <div class="col-12">
                <h2>{{ __('features_title') }}</h2>
                <p>{{ __('features_subtitle') }}</p>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="feature-box">
                    <div class="feature-icon">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <h3>{{ __('feature_1_title') }}</h3>
                    <p>{{ __('feature_1_desc') }}</p>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="feature-box">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>{{ __('feature_2_title') }}</h3>
                    <p>{{ __('feature_2_desc') }}</p>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="feature-box">
                    <div class="feature-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                    <h3>{{ __('feature_3_title') }}</h3>
                    <p>{{ __('feature_3_desc') }}</p>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
