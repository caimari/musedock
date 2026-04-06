{{-- Blog Single: Documentation Template --}}
{{-- Used when a post belongs to a category with slug "docs" or child of "docs" --}}
@extends('layouts.app')

@php
    $seoTitle = $post->seo_title ?: $post->title;
    $seoDesc = $post->seo_description ?: ($post->excerpt ?? mb_substr(strip_tags($post->content), 0, 160));
@endphp

@section('title', $seoTitle . ' | Docs | ' . site_setting('site_name', ''))
@section('description', $seoDesc)
@section('keywords', $post->seo_keywords ?? '')
@section('og_title', $seoTitle)
@section('og_description', $seoDesc)

@section('content')
@php
    $pdo = \Screenart\Musedock\Database::connect();
    $tenantId = tenant_id();

    // Get all docs categories (slug = 'docs' and its children)
    $__tenantFilter = $tenantId ? "tenant_id = $tenantId" : "tenant_id IS NULL";
    $docsRootStmt = $pdo->query("SELECT id FROM blog_categories WHERE slug = 'docs' AND $__tenantFilter LIMIT 1");
    $docsRootId = $docsRootStmt->fetchColumn();

    // Get all subcategories of docs
    $docsCatIds = [$docsRootId];
    if ($docsRootId) {
        $subStmt = $pdo->prepare("SELECT id FROM blog_categories WHERE parent_id = ? AND $__tenantFilter");
        $subStmt->execute([$docsRootId]);
        while ($row = $subStmt->fetch(\PDO::FETCH_OBJ)) {
            $docsCatIds[] = $row->id;
        }
    }

    // Build navigation: get all published docs posts grouped by category
    $docsNav = [];
    if (!empty($docsCatIds)) {
        $placeholders = implode(',', array_fill(0, count($docsCatIds), '?'));
        $__slugTenantFilter = $tenantId ? "s.tenant_id = $tenantId" : "s.tenant_id IS NULL";
        $__postTenantFilter = $tenantId ? "p.tenant_id = $tenantId" : "p.tenant_id IS NULL";
        $navStmt = $pdo->prepare("
            SELECT p.id, p.title, p.slug, c.id as cat_id, c.name as cat_name, c.slug as cat_slug, c.\"order\" as cat_order,
                   COALESCE(s.prefix, '') as url_prefix
            FROM blog_posts p
            INNER JOIN blog_post_categories pc ON pc.post_id = p.id
            INNER JOIN blog_categories c ON c.id = pc.category_id
            LEFT JOIN slugs s ON s.reference_id = p.id AND s.module = 'blog' AND $__slugTenantFilter
            WHERE pc.category_id IN ($placeholders)
            AND p.status = 'published'
            AND $__postTenantFilter
            ORDER BY c.\"order\" ASC, c.name ASC, p.title ASC
        ");
        $navStmt->execute($docsCatIds);
        $navRows = $navStmt->fetchAll(\PDO::FETCH_OBJ);

        foreach ($navRows as $row) {
            $catKey = $row->cat_slug;
            if (!isset($docsNav[$catKey])) {
                $docsNav[$catKey] = [
                    'name' => $row->cat_name,
                    'slug' => $row->cat_slug,
                    'order' => $row->cat_order,
                    'posts' => []
                ];
            }
            $postUrl = $row->url_prefix ? '/' . $row->url_prefix . '/' . $row->slug : '/' . $row->slug;
            $docsNav[$catKey]['posts'][] = (object)[
                'id' => $row->id,
                'title' => $row->title,
                'slug' => $row->slug,
                'url' => $postUrl,
                'active' => ($row->id == $post->id)
            ];
        }

        // Sort categories by order
        uasort($docsNav, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
    }

    // Build breadcrumb
    $currentCat = null;
    foreach ($post->categories ?? [] as $cat) {
        if (in_array($cat->id ?? $cat->slug, $docsCatIds) || $cat->slug === 'docs') {
            $currentCat = $cat;
        }
    }

    $processedContent = apply_filters('the_content', $post->content ?? '');
@endphp

<div class="docs-layout">
    <div class="container pb-5" style="padding-top:0">
        <div class="row">
            {{-- Sidebar Navigation --}}
            <aside class="col-lg-3 docs-sidebar-col">
                <nav class="docs-sidebar" id="docs-sidebar">
                    <div class="docs-sidebar-header">
                        <a href="{{ blog_url('', 'category') }}/docs" class="docs-sidebar-title">
                            <i class="bi bi-book"></i> Documentación
                        </a>
                    </div>

                    <div class="docs-search">
                        <input type="text" id="docs-search-input" placeholder="Buscar en docs..." class="docs-search-input">
                    </div>

                    <div class="docs-nav-sections" id="docs-nav-sections">
                        @foreach($docsNav as $catSlug => $category)
                            @if($catSlug !== 'docs')
                            <div class="docs-nav-section" data-section="{{ $catSlug }}">
                                @php $__hasActive = !empty(array_filter($category['posts'], fn($p) => $p->active)); @endphp
                                <button class="docs-nav-section-title {{ $__hasActive ? 'active' : '' }}" type="button">
                                    {{ $category['name'] }}
                                    <svg class="docs-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                </button>
                                <ul class="docs-nav-links {{ $__hasActive ? 'show' : '' }}">
                                    @foreach($category['posts'] as $navPost)
                                    <li>
                                        <a href="{{ $navPost->url }}" class="{{ $navPost->active ? 'active' : '' }}">
                                            {{ $navPost->title }}
                                        </a>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                            @else
                                {{-- Root "docs" category posts (no subcategory) --}}
                                @foreach($category['posts'] as $navPost)
                                <div class="docs-nav-root-link">
                                    <a href="{{ $navPost->url }}" class="{{ $navPost->active ? 'active' : '' }}">
                                        {{ $navPost->title }}
                                    </a>
                                </div>
                                @endforeach
                            @endif
                        @endforeach
                    </div>
                </nav>
            </aside>

            {{-- Main Content --}}
            <div class="col-lg-7 docs-content-col">
                {{-- Breadcrumbs --}}
                <nav class="docs-breadcrumb" aria-label="Breadcrumb">
                    <a href="/">{{ site_setting('site_name', 'Home') }}</a>
                    <span class="docs-breadcrumb-sep">/</span>
                    <a href="{{ blog_url('', 'category') }}/docs">Docs</a>
                    @if($currentCat && $currentCat->slug !== 'docs')
                    <span class="docs-breadcrumb-sep">/</span>
                    <a href="{{ blog_url($currentCat->slug, 'category') }}">{{ $currentCat->name }}</a>
                    @endif
                    <span class="docs-breadcrumb-sep">/</span>
                    <span class="docs-breadcrumb-current">{{ $post->title }}</span>
                </nav>

                {{-- Article --}}
                <article class="docs-article">
                    <h1 class="docs-title">{{ $post->title }}</h1>

                    <div class="docs-body page-body">
                        {!! $processedContent !!}
                    </div>

                    {{-- Prev/Next navigation --}}
                    @php
                        // Find prev/next within docs nav
                        $allDocsPosts = [];
                        foreach ($docsNav as $cat) {
                            foreach ($cat['posts'] as $p) {
                                $allDocsPosts[] = $p;
                            }
                        }
                        $currentIdx = null;
                        foreach ($allDocsPosts as $idx => $p) {
                            if ($p->active) { $currentIdx = $idx; break; }
                        }
                        $prevDoc = $currentIdx !== null && $currentIdx > 0 ? $allDocsPosts[$currentIdx - 1] : null;
                        $nextDoc = $currentIdx !== null && $currentIdx < count($allDocsPosts) - 1 ? $allDocsPosts[$currentIdx + 1] : null;
                    @endphp
                    @if($prevDoc || $nextDoc)
                    <nav class="docs-pagination">
                        @if($prevDoc)
                        <a href="{{ $prevDoc->url }}" class="docs-pagination-prev">
                            <span class="docs-pagination-label">&larr; Anterior</span>
                            <span class="docs-pagination-title">{{ $prevDoc->title }}</span>
                        </a>
                        @else
                        <span></span>
                        @endif
                        @if($nextDoc)
                        <a href="{{ $nextDoc->url }}" class="docs-pagination-next">
                            <span class="docs-pagination-label">Siguiente &rarr;</span>
                            <span class="docs-pagination-title">{{ $nextDoc->title }}</span>
                        </a>
                        @endif
                    </nav>
                    @endif
                </article>
            </div>

            {{-- Table of Contents (right sidebar) --}}
            <aside class="col-lg-2 docs-toc-col">
                <div class="docs-toc" id="docs-toc">
                    <div class="docs-toc-title">En esta página</div>
                    <nav id="docs-toc-nav">
                        {{-- Populated by JS from H2/H3 headings --}}
                    </nav>
                </div>
            </aside>
        </div>
    </div>
</div>

@include('blog.layouts._docs-styles')
@endsection
