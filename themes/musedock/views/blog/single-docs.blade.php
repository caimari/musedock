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
    $__tf = $tenantId ? "p.tenant_id = $tenantId" : "p.tenant_id IS NULL";
    $__sf = $tenantId ? "s.tenant_id = $tenantId" : "s.tenant_id IS NULL";

    // Build navigation: all published docs posts grouped by product > section
    // Structure: docsNav[product_slug] = { name, sections: { section_slug: { name, posts: [...] } } }
    $docsNav = [];
    $navStmt = $pdo->query("
        SELECT p.id, p.title, p.slug,
               c.id as cat_id, c.name as cat_name, c.slug as cat_slug, c.\"order\" as cat_order,
               c.parent_id as cat_parent_id,
               pc2.id as parent_cat_id, pc2.name as parent_cat_name, pc2.slug as parent_cat_slug, pc2.\"order\" as parent_cat_order,
               COALESCE(s.prefix, 'docs') as url_prefix
        FROM blog_posts p
        LEFT JOIN blog_post_categories bpc ON bpc.post_id = p.id
        LEFT JOIN blog_categories c ON c.id = bpc.category_id
        LEFT JOIN blog_categories pc2 ON pc2.id = c.parent_id
        LEFT JOIN slugs s ON s.reference_id = p.id AND s.module = 'blog' AND $__sf
        WHERE p.post_type = 'docs'
        AND p.status = 'published'
        AND $__tf
        ORDER BY pc2.\"order\" ASC, c.\"order\" ASC, p.title ASC
    ");
    $navRows = $navStmt->fetchAll(\PDO::FETCH_OBJ);

    $__seenPostIds = [];
    foreach ($navRows as $row) {
        if (isset($__seenPostIds[$row->id])) continue;
        $__seenPostIds[$row->id] = true;

        // Determine product and section
        // 3-level: Docs > Product > Section — post is in Section
        // 2-level: Docs > Section — post is in Section (no product level)
        // 1-level: post has no category or directly in Docs
        $productKey = $row->parent_cat_slug ?: ($row->cat_slug ?: '_general');
        $productName = $row->parent_cat_name ?: ($row->cat_name ?: 'General');
        $productOrder = $row->parent_cat_order ?? ($row->cat_order ?? 99);
        $sectionKey = $row->parent_cat_slug ? $row->cat_slug : '_root';
        $sectionName = $row->parent_cat_slug ? $row->cat_name : '';
        $sectionOrder = $row->parent_cat_slug ? ($row->cat_order ?? 99) : 0;

        if (!isset($docsNav[$productKey])) {
            $docsNav[$productKey] = [
                'name' => $productName,
                'slug' => $productKey,
                'order' => $productOrder,
                'sections' => []
            ];
        }
        if (!isset($docsNav[$productKey]['sections'][$sectionKey])) {
            $docsNav[$productKey]['sections'][$sectionKey] = [
                'name' => $sectionName,
                'order' => $sectionOrder,
                'posts' => []
            ];
        }

        $postUrl = '/' . $row->url_prefix . '/' . $row->slug;
        $docsNav[$productKey]['sections'][$sectionKey]['posts'][] = (object)[
            'id' => $row->id,
            'title' => $row->title,
            'slug' => $row->slug,
            'url' => $postUrl,
            'active' => ($row->id == $post->id)
        ];
    }

    // Sort products and sections by order
    uasort($docsNav, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
    foreach ($docsNav as &$product) {
        uasort($product['sections'], fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
    }
    unset($product);

    // Detect which product the current post belongs to
    $currentProduct = null;
    $currentSection = null;
    foreach ($docsNav as $pk => $pv) {
        foreach ($pv['sections'] as $sk => $sv) {
            foreach ($sv['posts'] as $p) {
                if ($p->active) { $currentProduct = $pk; $currentSection = $sk; break 3; }
            }
        }
    }

    // If multiple products exist, only show current product's nav (keep sidebar focused)
    $multiProduct = count($docsNav) > 1;
    $sidebarNav = $multiProduct && $currentProduct ? [$currentProduct => $docsNav[$currentProduct]] : $docsNav;

    // Build product switcher URLs (first post of each product)
    $productSwitcherUrls = [];
    foreach ($docsNav as $pk => $pv) {
        foreach ($pv['sections'] as $sv) {
            if (!empty($sv['posts'])) {
                $productSwitcherUrls[$pk] = $sv['posts'][0]->url;
                break;
            }
        }
        if (!isset($productSwitcherUrls[$pk])) $productSwitcherUrls[$pk] = '/docs/';
    }

    // Build breadcrumb — use first category of the post
    $currentCat = null;
    foreach ($post->categories ?? [] as $cat) {
        if ($cat->slug !== 'docs') { // Skip the root "docs" category, use the subcategory
            $currentCat = $cat;
            break;
        }
    }
    if (!$currentCat && !empty($post->categories)) {
        $currentCat = $post->categories[0] ?? null;
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
                        <a href="/docs/" class="docs-sidebar-title">
                            <i class="bi bi-book"></i> Documentación
                        </a>
                    </div>

                    <div class="docs-search">
                        <input type="text" id="docs-search-input" placeholder="Buscar en docs..." class="docs-search-input">
                    </div>

                    <div class="docs-nav-sections" id="docs-nav-sections">
                        @if($multiProduct)
                        <div class="docs-product-switcher" style="margin-bottom:0.75rem;">
                            <select id="docs-product-select" class="docs-search-input" style="font-weight:600;font-size:0.8rem;" onchange="var urls=@json($productSwitcherUrls); if(urls[this.value]) window.location.href=urls[this.value];">
                                @foreach($docsNav as $pk => $pv)
                                <option value="{{ $pk }}" {{ $pk === $currentProduct ? 'selected' : '' }}>{{ $pv['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif

                        @foreach($sidebarNav as $productSlug => $product)
                            @foreach($product['sections'] as $sectionSlug => $section)
                                @if($sectionSlug === '_root' || empty($section['name']))
                                    {{-- Posts directly under product (no section) --}}
                                    @foreach($section['posts'] as $navPost)
                                    <div class="docs-nav-root-link">
                                        <a href="{{ $navPost->url }}" class="{{ $navPost->active ? 'active' : '' }}">
                                            {{ $navPost->title }}
                                        </a>
                                    </div>
                                    @endforeach
                                @else
                                <div class="docs-nav-section" data-section="{{ $sectionSlug }}">
                                    @php $__hasActive = !empty(array_filter($section['posts'], fn($p) => $p->active)); @endphp
                                    <button class="docs-nav-section-title {{ $__hasActive ? 'active' : '' }}" type="button">
                                        {{ $section['name'] }}
                                        <svg class="docs-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                    </button>
                                    <ul class="docs-nav-links {{ $__hasActive ? 'show' : '' }}">
                                        @foreach($section['posts'] as $navPost)
                                        <li>
                                            <a href="{{ $navPost->url }}" class="{{ $navPost->active ? 'active' : '' }}">
                                                {{ $navPost->title }}
                                            </a>
                                        </li>
                                        @endforeach
                                    </ul>
                                </div>
                                @endif
                            @endforeach
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
                    <a href="/docs/">Docs</a>
                    @if($currentProduct && isset($docsNav[$currentProduct]))
                    <span class="docs-breadcrumb-sep">/</span>
                    <span>{{ $docsNav[$currentProduct]['name'] }}</span>
                    @endif
                    @if($currentSection && $currentSection !== '_root' && isset($docsNav[$currentProduct]['sections'][$currentSection]) && !empty($docsNav[$currentProduct]['sections'][$currentSection]['name']))
                    <span class="docs-breadcrumb-sep">/</span>
                    <span>{{ $docsNav[$currentProduct]['sections'][$currentSection]['name'] }}</span>
                    @endif
                    <span class="docs-breadcrumb-sep">/</span>
                    <span class="docs-breadcrumb-current">{{ $post->title }}</span>
                </nav>

                {{-- Article --}}
                <article class="docs-article">
                    @if(!($post->hide_title ?? 0))
                    <h1 class="docs-title">{{ $post->title }}</h1>
                    @endif

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
