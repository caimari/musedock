@extends('layouts.app')

{{-- SEO --}}
@section('title')
    {{ __('blog.posts_by') . ' ' . $author->name . ' | ' . __('blog.title') . ' | ' . site_setting('site_name', '') }}
@endsection

@section('description')
    {{ $author->bio ? mb_substr(strip_tags($author->bio), 0, 160) : site_setting('site_description', '') }}
@endsection

@section('content')
@php
    $blogLayout = themeOption('blog.blog_layout', 'grid');
    $allowedLayouts = ['grid', 'list', 'magazine', 'minimal', 'newspaper', 'fashion'];
    if (!in_array($blogLayout, $allowedLayouts)) {
        $blogLayout = 'grid';
    }
    $blogLayoutPartial = 'blog.layouts.blog-' . $blogLayout;
    $themeSlug = get_active_theme_slug();
    $tenantId = tenant_id();
    $themeBase = APP_ROOT . "/themes/{$themeSlug}";
    if ($tenantId && is_dir(APP_ROOT . "/themes/tenant_{$tenantId}/{$themeSlug}/views")) {
        $themeBase = APP_ROOT . "/themes/tenant_{$tenantId}/{$themeSlug}";
    }
    $blogLayoutPath = $themeBase . '/views/blog/layouts/blog-' . $blogLayout . '.blade.php';
    if (!file_exists($blogLayoutPath)) {
        $defaultPath = APP_ROOT . '/themes/default/views/blog/layouts/blog-' . $blogLayout . '.blade.php';
        if (!file_exists($defaultPath)) {
            $blogLayoutPartial = 'blog.layouts.blog-grid';
        }
    }

    // Avatar
    $hasAvatar = $author->avatar && file_exists(APP_ROOT . '/storage/avatars/' . $author->avatar);
    $authorInitial = strtoupper(mb_substr($author->name, 0, 1));
@endphp

<div class="container pt-3 pb-5">
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-3" style="background: transparent; padding-left: 0;">
            <li class="breadcrumb-item"><a href="/" style="color: #333;">{{ __('common.home') }}</a></li>
            @if(blog_prefix() !== '')
            <li class="breadcrumb-item"><a href="{{ blog_url() }}" style="color: #333;">{{ __('blog.title') }}</a></li>
            @endif
            <li class="breadcrumb-item active" aria-current="page" style="color: #666;">{{ $author->name }}</li>
        </ol>
    </nav>

    {{-- Author Header --}}
    <div class="author-header text-center mb-5 pb-4 border-bottom">
        {{-- Avatar --}}
        <div class="mb-3">
            @if($hasAvatar)
                <img src="/author-avatar/{{ $author->avatar }}" alt="{{ $author->name }}" class="rounded-circle" style="width: 120px; height: 120px; object-fit: cover; border: 3px solid #f0f0f0;">
            @else
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 120px; height: 120px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: 600; font-size: 48px; border: 3px solid #f0f0f0;">
                    {{ $authorInitial }}
                </div>
            @endif
        </div>

        {{-- Name --}}
        <h1 class="mb-2" style="font-size: 1.75rem;">{{ $author->name }}</h1>

        {{-- Bio --}}
        @if($author->bio)
        <p class="text-muted mx-auto" style="max-width: 600px; font-size: 0.95rem; line-height: 1.6;">{{ $author->bio }}</p>
        @endif

        {{-- Social Links --}}
        @if($author->social_twitter || $author->social_linkedin || $author->social_github || $author->social_website)
        <div class="author-social mt-3">
            @if($author->social_twitter)
            <a href="{{ $author->social_twitter }}" target="_blank" rel="noopener noreferrer" class="author-social-link" title="Twitter / X">
                <i class="fab fa-x-twitter"></i>
            </a>
            @endif
            @if($author->social_linkedin)
            <a href="{{ $author->social_linkedin }}" target="_blank" rel="noopener noreferrer" class="author-social-link" title="LinkedIn">
                <i class="fab fa-linkedin-in"></i>
            </a>
            @endif
            @if($author->social_github)
            <a href="{{ $author->social_github }}" target="_blank" rel="noopener noreferrer" class="author-social-link" title="GitHub">
                <i class="fab fa-github"></i>
            </a>
            @endif
            @if($author->social_website)
            <a href="{{ $author->social_website }}" target="_blank" rel="noopener noreferrer" class="author-social-link" title="Website">
                <i class="fas fa-globe"></i>
            </a>
            @endif
        </div>
        @endif

        {{-- Post count --}}
        <p class="text-muted mt-3 mb-0" style="font-size: 0.85rem;">
            {{ $pagination['total_posts'] ?? 0 }} {{ __('blog.posts') }}
        </p>
    </div>

    {{-- Posts Grid --}}
    @include($blogLayoutPartial, [
        'posts' => $posts ?? [],
        'pagination' => $pagination ?? [],
        'categories' => $categories ?? [],
        'is_home' => false,
    ])
</div>

<style>
.author-social {
    display: flex;
    justify-content: center;
    gap: 12px;
}
.author-social-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #f0f2f5;
    color: #555;
    font-size: 1rem;
    text-decoration: none;
    transition: all .2s ease;
}
.author-social-link:hover {
    background: #333;
    color: #fff;
}
</style>
@endsection
