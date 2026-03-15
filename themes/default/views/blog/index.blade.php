@extends('layouts.app')

{{-- SEO --}}
@section('title')
    @if(!empty($is_home))
        @php $__subtitle = site_setting('site_subtitle', ''); @endphp
        {{ site_setting('site_name', '') . ($__subtitle ? ' | ' . $__subtitle : '') }}
    @else
        {{ __('blog.title') . ' | ' . site_setting('site_name', '') }}
    @endif
@endsection

@section('description')
    {{ site_setting('site_description', '') }}
@endsection

@section('content')
@php
    $blogLayout = themeOption('blog.blog_layout', 'grid');
    $allowedLayouts = ['grid', 'list', 'magazine', 'minimal', 'newspaper', 'fashion'];
    if (!in_array($blogLayout, $allowedLayouts)) {
        $blogLayout = 'grid';
    }

    // Check if layout partial exists (same pattern as header_layout in layouts/app.blade.php)
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
    @include($blogLayoutPartial, [
        'posts' => $posts ?? [],
        'pagination' => $pagination ?? [],
        'categories' => $categories ?? [],
        'is_home' => $is_home ?? false,
    ])
</div>
@endsection
