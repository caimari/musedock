@extends('layouts.app')

@php
    $__sl = function_exists('detectLanguage') ? detectLanguage() : ($currentLang ?? 'es');
@endphp

@section('title', ($__sl === 'en' ? 'Search: ' : 'Buscar: ') . ($query ?? '') . ' | ' . site_setting('site_name', ''))

@section('content')
<div class="container py-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">

            {{-- Search Form --}}
            <div class="md-search-page-header mb-5">
                <h1 class="md-search-page-title">{{ $__sl === 'en' ? 'Search' : 'Buscar' }}</h1>
                <form action="{{ url('/search') }}" method="GET" class="md-search-page-form">
                    <div class="md-search-page-input-wrap">
                        <svg class="md-search-page-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" name="q" class="md-search-page-input" placeholder="{{ $__sl === 'en' ? 'What are you looking for?' : '¿Qué estás buscando?' }}" value="{{ $query ?? '' }}" required minlength="2" autofocus>
                        <button type="submit" class="md-search-page-btn">{{ $__sl === 'en' ? 'Search' : 'Buscar' }}</button>
                    </div>
                </form>

                @if(!empty($query))
                    <p class="md-search-page-summary">
                        @if($results['total'] > 0)
                            {{ $results['total'] }} {{ $__sl === 'en' ? 'result(s) for' : 'resultado(s) para' }} "<strong>{{ $query }}</strong>"
                        @else
                            {{ $__sl === 'en' ? 'No results found for' : 'Sin resultados para' }} "<strong>{{ $query }}</strong>"
                        @endif
                    </p>
                @endif
            </div>

            {{-- Results --}}
            @if(!empty($query))
                @if($results['total'] > 0)

                    {{-- Blog Posts --}}
                    @if(count($results['posts']) > 0)
                    <div class="mb-5">
                        <h2 class="md-search-section-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                            {{ $__sl === 'en' ? 'Posts' : 'Artículos' }} ({{ count($results['posts']) }})
                        </h2>

                        @foreach($results['posts'] as $post)
                        <a href="{{ $post['url'] }}" class="md-search-result-card">
                            @if(!empty($post['featured_image']))
                            @php
                                $__img = (str_starts_with($post['featured_image'], '/') || str_starts_with($post['featured_image'], 'http'))
                                    ? $post['featured_image'] : asset($post['featured_image']);
                                $__img = function_exists('media_thumb_url') ? media_thumb_url($__img) : $__img;
                            @endphp
                            <div class="md-search-result-img">
                                <img src="{{ $__img }}" alt="{{ strip_tags($post['title']) }}" loading="lazy">
                            </div>
                            @endif
                            <div class="md-search-result-body">
                                <h3 class="md-search-result-title">{!! $post['title'] !!}</h3>
                                <span class="md-search-result-date">{{ date('d/m/Y', strtotime($post['published_at'])) }}</span>
                                <p class="md-search-result-excerpt">{!! $post['excerpt'] !!}</p>
                            </div>
                        </a>
                        @endforeach
                    </div>
                    @endif

                    {{-- Pages --}}
                    @if(count($results['pages']) > 0)
                    <div class="mb-5">
                        <h2 class="md-search-section-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                            {{ $__sl === 'en' ? 'Pages' : 'Páginas' }} ({{ count($results['pages']) }})
                        </h2>

                        @foreach($results['pages'] as $page)
                        <a href="{{ $page['url'] }}" class="md-search-result-card">
                            <div class="md-search-result-body">
                                <h3 class="md-search-result-title">{!! $page['title'] !!}</h3>
                                <p class="md-search-result-excerpt">{!! $page['excerpt'] !!}</p>
                            </div>
                        </a>
                        @endforeach
                    </div>
                    @endif

                @else
                    {{-- No results --}}
                    <div class="md-search-empty">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color: #ccc;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="8" y1="8" x2="14" y2="14"></line><line x1="14" y1="8" x2="8" y2="14"></line></svg>
                        <p>{{ $__sl === 'en' ? 'No results found. Try different keywords.' : 'No se encontraron resultados. Prueba con otros términos.' }}</p>
                        <a href="{{ url('/') }}" class="md-search-home-link">{{ $__sl === 'en' ? 'Back to home' : 'Volver al inicio' }}</a>
                    </div>
                @endif
            @else
                <div class="md-search-empty">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color: #ccc;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <p>{{ $__sl === 'en' ? 'Type something to search pages and posts.' : 'Escribe algo para buscar en páginas y artículos.' }}</p>
                </div>
            @endif
        </div>
    </div>
</div>

<style>
.md-search-page-title {
    font-size: 1.6rem;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 20px;
}
.md-search-page-form {
    margin-bottom: 12px;
}
.md-search-page-input-wrap {
    display: flex;
    align-items: center;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    overflow: hidden;
    background: #fff;
    transition: border-color 0.2s;
    position: relative;
}
.md-search-page-input-wrap:focus-within {
    border-color: var(--header-link-hover-color, #ff5e15);
    box-shadow: 0 0 0 3px rgba(255,94,21,0.08);
}
.md-search-page-icon {
    position: absolute;
    left: 16px;
    color: #aaa;
    pointer-events: none;
}
.md-search-page-input {
    flex: 1;
    border: none;
    padding: 14px 16px 14px 48px;
    font-size: 16px;
    outline: none;
    background: transparent;
    font-family: inherit;
    color: #333;
}
.md-search-page-input::placeholder { color: #bbb; }
.md-search-page-btn {
    border: none;
    background: var(--header-link-hover-color, #ff5e15);
    color: #fff;
    padding: 14px 24px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: opacity 0.2s;
    font-family: inherit;
}
.md-search-page-btn:hover { opacity: 0.9; }
.md-search-page-summary {
    font-size: 14px;
    color: #888;
}
.md-search-section-title {
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #999;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.md-search-result-card {
    display: flex;
    gap: 16px;
    padding: 16px;
    border-radius: 10px;
    margin-bottom: 8px;
    text-decoration: none;
    color: inherit;
    transition: background 0.15s;
    border: 1px solid transparent;
}
.md-search-result-card:hover {
    background: #f8f9fa;
    border-color: #eee;
    text-decoration: none;
    color: inherit;
}
.md-search-result-img {
    flex-shrink: 0;
    width: 90px;
    height: 68px;
    border-radius: 8px;
    overflow: hidden;
    background: #f0f2f5;
    display: flex;
    align-items: center;
    justify-content: center;
}
.md-search-result-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.md-search-result-body {
    flex: 1;
    min-width: 0;
}
.md-search-result-title {
    font-size: 15px;
    font-weight: 600;
    color: #1a1a1a;
    margin: 0 0 4px;
    line-height: 1.3;
}
.md-search-result-card:hover .md-search-result-title {
    color: var(--header-link-hover-color, #ff5e15);
}
.md-search-result-date {
    font-size: 12px;
    color: #aaa;
}
.md-search-result-excerpt {
    font-size: 13px;
    color: #777;
    margin: 6px 0 0;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.md-search-result-excerpt mark {
    background: #fff3cd;
    padding: 1px 3px;
    border-radius: 2px;
    color: inherit;
}
.md-search-empty {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}
.md-search-empty p {
    margin: 16px 0 0;
    font-size: 15px;
}
.md-search-home-link {
    display: inline-block;
    margin-top: 12px;
    font-size: 14px;
    color: var(--header-link-hover-color, #ff5e15);
    text-decoration: none;
}
.md-search-home-link:hover { text-decoration: underline; }
@media (max-width: 575px) {
    .md-search-result-img { width: 70px; height: 54px; }
    .md-search-page-input { font-size: 15px; padding: 12px 14px 12px 42px; }
    .md-search-page-btn { padding: 12px 16px; }
}
</style>
@endsection
