@extends('layouts.app')

@section('title')
    {{ ($page->seo_title ?? $page->title) . ' | ' . setting('site_name', config('app_name', 'MuseDock CMS')) }}
@endsection

@section('keywords')
    {{ $page->seo_keywords ?? setting('site_keywords', '') }}
@endsection

@section('og_title')
    {{ $page->seo_title ?? $page->title }}
@endsection

@section('og_description')
    {{ $page->seo_description ?? setting('site_description', '') }}
@endsection

@section('og_url')
    {{ url($page->slug) }}
@endsection

@section('og_image')
    @if(!empty($page->seo_image))
        {{ asset($page->seo_image) }}
    @endif
@endsection

@section('twitter_title')
    {{ $page->twitter_title ?? $page->seo_title ?? $page->title }}
@endsection

@section('twitter_description')
    {{ $page->twitter_description ?? $page->seo_description ?? setting('site_description', '') }}
@endsection

@section('twitter_image')
    @if(!empty($page->twitter_image))
        {{ asset($page->twitter_image) }}
    @endif
@endsection

@section('canonical')
    <link rel="canonical" href="{{ url($page->slug) }}">
@endsection

@section('robots')
    {{ $page->robots_directive ?? 'index,follow' }}
@endsection

@section('content')

SLIDEr PUBLIC PAGE

    <!-- slider Area Start-->
    <div class="slider-area ">
        <!-- Mobile Menu -->
        <div class="single-slider slider-height2 d-flex align-items-center" data-background="{{ asset('themes/default/img/hero/contact_hero.jpg') }}">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="hero-cap text-center">
                            <h2>{{ $page->title }}</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- slider Area End-->



<div class="page-content container mt-5">
      <!--  <h1 class="display-4">{{ $page->title }}</h1> -->
    <div class="content">
        {!! $page->content !!}
    </div>
</div>
@endsection
