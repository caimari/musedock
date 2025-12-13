@extends('layouts.app')

@section('title')
    {{ ($translation->seo_title ?? $page->seo_title ?? $translation->title ?? $page->title ?? __('page_title')) . ' | ' . setting('site_name', config('app_name', 'MuseDock CMS')) }}
@endsection

@section('keywords')
    {{ $translation->seo_keywords ?? $page->seo_keywords ?? setting('site_keywords', '') }}
@endsection

@section('og_title')
    {{ $translation->seo_title ?? $page->seo_title ?? $translation->title ?? $page->title ?? __('page_title') }}
@endsection

@section('og_description')
    {{ $translation->seo_description ?? $page->seo_description ?? setting('site_description', '') }}
@endsection

@section('content')

<!-- Page Header -->
<section style="background-color: var(--color-AliceBlue); padding: 3rem 0; margin-bottom: 3rem;">
    <div class="container">
        <h1 style="font-size: clamp(2rem, 4vw, 2.5rem); font-weight: 700; color: var(--color-secondary); margin-bottom: 0.5rem;">
            {{ $translation->title ?? $page->title ?? 'Page Title' }}
        </h1>
        @if(isset($page->excerpt) && $page->excerpt)
        <p style="font-size: 1.125rem; color: var(--color-SlateBlue);">
            {{ $page->excerpt }}
        </p>
        @endif
    </div>
</section>

<!-- Page Content -->
<section style="padding-bottom: 5rem;">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div style="color: var(--color-secondary); line-height: 1.8; font-size: 1rem;">
                    {!! apply_filters('the_content', $translation->content ?? $page->content ?? '') !!}
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
