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

@php
    $__blogDesc = trim(site_setting('site_description', ''));
    if (empty($__blogDesc)) {
        $__sub = site_setting('site_subtitle', '');
        $__blogDesc = $__sub ? site_setting('site_name', '') . ' — ' . $__sub : site_setting('site_name', '');
    }
    \Screenart\Musedock\View::startSection('description', $__blogDesc);
@endphp

@section('content')
@php
    $blogLayout = themeOption('blog.blog_layout', 'grid');
    $allowedLayouts = ['grid', 'list', 'magazine', 'minimal', 'newspaper', 'fashion', 'mosaic'];
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

    $hasBriefs = !empty($showBriefs) && !empty($briefs) && count($briefs) > 0;
@endphp

<div class="container pt-3 pb-5">
    @if($hasBriefs)
    <div class="row">
        {{-- Main blog content --}}
        <div class="col-lg-9">
            @include($blogLayoutPartial, [
                'posts' => $posts ?? [],
                'pagination' => $pagination ?? [],
                'categories' => $categories ?? [],
                'is_home' => $is_home ?? false,
            ])
        </div>

        {{-- Briefs sidebar --}}
        <div class="col-lg-3">
            <div class="briefs-sidebar" style="position: sticky; top: 80px;">
                <div class="briefs-header d-flex align-items-center mb-3" style="border-bottom: 2px solid #dc3545; padding-bottom: 8px;">
                    <i class="bi bi-lightning-fill text-danger me-2"></i>
                    <h5 class="mb-0" style="font-size: 1rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">{{ __('blog.briefs_title') }}</h5>
                </div>
                <div class="briefs-list">
                    @foreach($briefs as $brief)
                    <article class="brief-item mb-3 pb-3{{ !$loop->last ? ' border-bottom' : '' }}">
                        <a href="{{ blog_url($brief->slug) }}" class="text-decoration-none">
                            <h6 class="brief-title mb-1" style="font-size: 0.85rem; font-weight: 600; color: #212529; line-height: 1.3;">{{ $brief->title }}</h6>
                        </a>
                        @php
                            $briefDate = $brief->published_at ?? $brief->created_at;
                            $briefDateStr = $briefDate instanceof \DateTime ? $briefDate->format('d/m/Y') : date('d/m/Y', strtotime($briefDate));
                        @endphp
                        <span class="text-muted" style="font-size: 0.75rem;"><i class="far fa-clock me-1"></i>{{ $briefDateStr }}</span>
                    </article>
                    @endforeach
                </div>
                <div class="text-center mt-3">
                    <a href="{{ blog_url('brief', 'category') }}" class="text-decoration-none d-inline-block" style="font-size: 0.8rem; color: #6c757d; border: 1px solid #dee2e6; padding: 6px 16px; border-radius: 20px; transition: all 0.2s;">
                        {{ __('blog.briefs_view_all') }} <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    @else
    @include($blogLayoutPartial, [
        'posts' => $posts ?? [],
        'pagination' => $pagination ?? [],
        'categories' => $categories ?? [],
        'is_home' => $is_home ?? false,
    ])
    @endif
</div>

{{-- Plugin hook: after blog posts, before footer --}}
@php if (function_exists('do_action')) { do_action('home_after_posts'); } @endphp

@endsection
