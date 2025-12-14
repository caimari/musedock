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
    @php
        $homeContent = apply_filters('the_content', $translation->content ?? $page->content ?? '<p>' . __('home_intro') . '</p>');
        $homeContent = preg_replace('/<h[123][^>]*>.*?<\/h[123]>/', '', $homeContent, 1);
    @endphp

    <div class="container py-4 page-container">
        <div class="page-content-wrapper">
            <h1 class="page-title">{{ $translation->title ?? $page->title ?? __('home_title') }}</h1>
            <div class="page-body">
                {!! $homeContent !!}
            </div>
        </div>
    </div>

@endsection
