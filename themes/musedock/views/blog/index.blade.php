@extends('layouts.app')

{{-- SEO --}}
@section('title')
    @if(!empty($is_home))
        @php $__subtitle = site_setting('site_subtitle', ''); @endphp
        {{ site_setting('site_name', '') . ($__subtitle ? ' | ' . $__subtitle : '') }}
    @elseif(tenant_id() === null && blog_language_lists_enabled())
        {{ seo_title_with_suffix(__('blog.meta_title'), [site_setting('site_name', '')]) }}
    @else
        {{ __('blog.title') . ' | ' . site_setting('site_name', '') }}
    @endif
@endsection

@section('description')
    @if(empty($is_home) && tenant_id() === null && blog_language_lists_enabled())
        {{ __('blog.meta_description') }}
    @else
        {{ site_setting('site_description', '') }}
    @endif
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
    @if(empty($is_home) && tenant_id() === null && blog_language_lists_enabled())
        {{-- Sitio principal: el listado lleva su H1 (antes no tenía ninguno) --}}
        <header class="mb-4 pt-3">
            <h1 class="h2 mb-2">{{ __('blog.heading') }}</h1>
            <p class="text-muted mb-0">{{ __('blog.meta_description') }}</p>
        </header>
    @endif
    @include($blogLayoutPartial, [
        'posts' => $posts ?? [],
        'pagination' => $pagination ?? [],
        'categories' => $categories ?? [],
        'is_home' => $is_home ?? false,
    ])
</div>
@endsection
