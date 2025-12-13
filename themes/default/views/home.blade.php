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

        <!-- slider Area Start-->
        <div class="slider-area ">
            <!-- Mobile Menu -->
            <div class="slider-active">
                <div class="single-slider slider-height d-flex align-items-center" data-background="{{ asset('themes/default/img/hero/h1_hero.jpg') }}">
                    <div class="container">
                        <div class="row">
                            <div class="col-xl-6 col-lg-6 col-md-8">
                                <div class="hero__caption">
                                    <p data-animation="fadeInLeft" data-delay=".4s">Welcome to Muse Dock</p>
                                    <h1 data-animation="fadeInLeft" data-delay=".6s" >We help you to grow your business</h1>
                                    <!-- Hero-btn -->
                                    <div class="hero__btn" data-animation="fadeInLeft" data-delay=".8s">
                                        <a href="industries.html" class="btn hero-btn">Learn More</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="single-slider slider-height d-flex align-items-center" data-background="{{ asset('themes/default/img/hero/h1_hero.jpg') }}">
                    <div class="container">
                        <div class="row">
                            <div class="col-xl-6 col-lg-6 col-md-8">
                                <div class="hero__caption">
                                    @php
                                        $homeContent = apply_filters('the_content', $translation->content ?? $page->content ?? '<p>' . __('home_intro') . '</p>');
                                        // Eliminar el primer h1, h2 o h3 del contenido para evitar duplicados
                                        $homeContent = preg_replace('/<h[123][^>]*>.*?<\/h[123]>/', '', $homeContent, 1);
                                    @endphp
                                    <p data-animation="fadeInLeft" data-delay=".4s">{!! $homeContent !!}</p>
                                    <h1 data-animation="fadeInLeft" data-delay=".6s" >{{ $translation->title ?? $page->title ?? __('home_title') }}</h1>
                                    <!-- Hero-btn -->
                                    <div class="hero__btn" data-animation="fadeInLeft" data-delay=".8s">
                                        <a href="industries.html" class="btn hero-btn">{{ __('home_learn_more') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- slider Area End-->

@endsection

