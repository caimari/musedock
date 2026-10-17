@extends('layouts.app')

{{-- SEO --}}
@section('title')
    {{ $tag->name . ' | ' . __('blog.title') . ' | ' . site_setting('site_name', '') }}
@endsection

@section('description')
    {{ $tag->description ?? site_setting('site_description', '') }}
@endsection

@section('content')
@php
    $blogLayout = themeOption('blog.blog_layout', 'grid');
    $allowedLayouts = ['grid', 'list', 'magazine', 'minimal', 'newspaper', 'fashion', 'mosaic'];
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
@endphp

<div class="container pt-3 pb-5">
    {{-- Cabecera de etiqueta --}}
    <div class="tag-header mb-4 pb-3 border-bottom">
        @include('blog._docs-listing-prep', ['posts' => $posts ?? [], 'crumbCategory' => null, 'crumbType' => 'tag', 'crumbName' => $tag->name])
        <h1 class="mb-2" style="font-size: 1.75rem; margin-top: 0;">{{ $tag->name }}</h1>
        @if($tag->description)
        <p class="text-muted mb-0">{{ $tag->description }}</p>
        @endif
    </div>

    @include($blogLayoutPartial, [
        'posts' => $posts ?? [],
        'pagination' => $pagination ?? [],
        'categories' => $categories ?? [],
        'is_home' => false,
    ])
</div>
@endsection
