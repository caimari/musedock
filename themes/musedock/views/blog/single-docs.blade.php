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

    // Build navigation: all published docs posts grouped by docs tree
    // Structure: docsNav[product_slug] = { name, sections: { section_slug: { name, posts: [...] } } }
    $docsNav = [];
    $catTf = $tenantId ? "tenant_id = $tenantId" : "tenant_id IS NULL";

    $docsRootStmt = $pdo->query("SELECT id FROM blog_categories WHERE slug = 'docs' AND $catTf LIMIT 1");
    $docsRootId = (int)($docsRootStmt->fetchColumn() ?: 0);

    $allCategories = [];
    $catRowsStmt = $pdo->query("SELECT id, parent_id, name, slug, description, \"order\" FROM blog_categories WHERE $catTf");
    foreach ($catRowsStmt->fetchAll(\PDO::FETCH_OBJ) as $catRow) {
        $allCategories[(int)$catRow->id] = $catRow;
    }

    $navStmt = $pdo->query("
        SELECT p.id, p.title, p.slug,
               c.id as cat_id, c.slug as cat_slug,
               COALESCE(s.prefix, 'docs') as url_prefix
        FROM blog_posts p
        LEFT JOIN blog_post_categories bpc ON bpc.post_id = p.id
        LEFT JOIN blog_categories c ON c.id = bpc.category_id
        LEFT JOIN slugs s ON s.reference_id = p.id AND s.module = 'blog' AND $__sf
        WHERE p.post_type = 'docs'
        AND p.status = 'published'
        AND $__tf
        ORDER BY p.title ASC
    ");
    $navRows = $navStmt->fetchAll(\PDO::FETCH_OBJ);

    $ensureProduct = function (string $key, string $name = '', int $order = 99) use (&$docsNav): void {
        if (!isset($docsNav[$key])) {
            $docsNav[$key] = [
                'name' => $name !== '' ? $name : ucfirst($key),
                'slug' => $key,
                'order' => $order,
                'sections' => []
            ];
        }
    };

    $resolveDocsPath = function (int $catId) use ($docsRootId, $allCategories): ?array {
        if ($docsRootId <= 0 || $catId <= 0 || !isset($allCategories[$catId])) return null;

        $chain = [];
        $cursor = $catId;
        $guard = 0;
        while ($cursor > 0 && isset($allCategories[$cursor]) && $guard++ < 50) {
            $node = $allCategories[$cursor];
            $chain[] = $node;
            if ((int)$node->id === $docsRootId) break;
            $cursor = (int)($node->parent_id ?? 0);
        }

        if (empty($chain) || (int)end($chain)->id !== $docsRootId) return null;
        $chain = array_reverse($chain); // docs -> product -> section -> ...
        $productNode = $chain[1] ?? null;
        if (!$productNode) return null;
        return [
            'product' => $productNode,
            'section' => $chain[2] ?? null,
        ];
    };

    $guessProduct = function ($row): ?array {
        $catSlug = strtolower((string)($row->cat_slug ?? ''));
        $postSlug = strtolower((string)($row->slug ?? ''));
        $title = strtolower((string)($row->title ?? ''));

        if ($catSlug === 'cms' || strpos($postSlug, 'cms') !== false || strpos($title, 'cms') !== false) {
            return ['key' => 'cms', 'name' => 'MuseDock CMS', 'order' => 1];
        }
        if ($catSlug === 'panel' || strpos($postSlug, 'panel') !== false || strpos($title, 'panel') !== false) {
            return ['key' => 'panel', 'name' => 'MuseDock Panel', 'order' => 2];
        }
        if ($catSlug === 'portal' || strpos($postSlug, 'portal') !== false || strpos($title, 'portal') !== false) {
            return ['key' => 'portal', 'name' => 'MuseDock Portal', 'order' => 3];
        }

        return null;
    };

    $rowsByPost = [];
    foreach ($navRows as $row) {
        $rowsByPost[(int)$row->id][] = $row;
    }

    foreach ($rowsByPost as $__postId => $__rows) {
        $__primary = $__rows[0];
        $__chosenPath = null;
        foreach ($__rows as $__row) {
            $__path = $resolveDocsPath((int)($__row->cat_id ?? 0));
            if (!$__path) continue;
            $__chosenPath = $__path;
            if ($__path['section']) break;
        }

        $assigned = false;
        if ($__chosenPath) {
            $__productNode = $__chosenPath['product'];
            $__sectionNode = $__chosenPath['section'];

            $__productKey = (string)($__productNode->slug ?: ('product-' . (int)$__productNode->id));
            $ensureProduct($__productKey, (string)$__productNode->name, (int)($__productNode->order ?? 99));

            $__sectionKey = '_root';
            $__sectionName = '';
            $__sectionOrder = 0;
            if ($__sectionNode) {
                $__sectionKey = (string)($__sectionNode->slug ?: ('section-' . (int)$__sectionNode->id));
                $__sectionName = (string)$__sectionNode->name;
                $__sectionOrder = (int)($__sectionNode->order ?? 99);
            }

            if (!isset($docsNav[$__productKey]['sections'][$__sectionKey])) {
                $docsNav[$__productKey]['sections'][$__sectionKey] = [
                    'name' => $__sectionName,
                    'order' => $__sectionOrder,
                    'posts' => []
                ];
            }

            $docsNav[$__productKey]['sections'][$__sectionKey]['posts'][] = (object)[
                'id' => $__primary->id,
                'title' => $__primary->title,
                'slug' => $__primary->slug,
                'url' => '/' . $__primary->url_prefix . '/' . $__primary->slug,
                'active' => ($__primary->id == $post->id)
            ];
            $assigned = true;
        }

        if (!$assigned) {
            $__guessed = $guessProduct($__primary);
            if ($__guessed) {
                $ensureProduct($__guessed['key'], $__guessed['name'], $__guessed['order']);
                if (!isset($docsNav[$__guessed['key']]['sections']['_root'])) {
                    $docsNav[$__guessed['key']]['sections']['_root'] = ['name' => '', 'order' => 0, 'posts' => []];
                }
                $docsNav[$__guessed['key']]['sections']['_root']['posts'][] = (object)[
                    'id' => $__primary->id,
                    'title' => $__primary->title,
                    'slug' => $__primary->slug,
                    'url' => '/' . $__primary->url_prefix . '/' . $__primary->slug,
                    'active' => ($__primary->id == $post->id)
                ];
                $assigned = true;
            }
        }

        if (!$assigned) {
            $ensureProduct('_general', 'General', 999);
            if (!isset($docsNav['_general']['sections']['_root'])) {
                $docsNav['_general']['sections']['_root'] = ['name' => '', 'order' => 0, 'posts' => []];
            }
            $docsNav['_general']['sections']['_root']['posts'][] = (object)[
                'id' => $__primary->id,
                'title' => $__primary->title,
                'slug' => $__primary->slug,
                'url' => '/' . $__primary->url_prefix . '/' . $__primary->slug,
                'active' => ($__primary->id == $post->id)
            ];
        }
    }

    // Sort products and sections by order
    uasort($docsNav, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
    foreach ($docsNav as &$product) {
        uasort($product['sections'], fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
        foreach ($product['sections'] as &$section) {
            if (empty($section['posts']) || !is_array($section['posts'])) {
                continue;
            }
            usort($section['posts'], function ($a, $b) {
                $aTitle = mb_strtolower(trim((string)($a->title ?? '')));
                $bTitle = mb_strtolower(trim((string)($b->title ?? '')));
                $aIsLicense = str_starts_with($aTitle, 'licencia');
                $bIsLicense = str_starts_with($bTitle, 'licencia');
                if ($aIsLicense !== $bIsLicense) {
                    return $aIsLicense ? 1 : -1; // licencia siempre al final
                }
                return $aTitle <=> $bTitle;
            });
        }
        unset($section);
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

    // Resolve sidebar context: requested product from query or current post product.
    $multiProduct = count($docsNav) > 1;
    $requestedProduct = isset($_GET['product']) ? (string)$_GET['product'] : '';
    if ($requestedProduct !== '' && !isset($docsNav[$requestedProduct])) {
        $requestedProduct = '';
    }
    $selectedProduct = $requestedProduct !== '' ? $requestedProduct : $currentProduct;
    $sidebarNav = ($multiProduct && $selectedProduct && isset($docsNav[$selectedProduct]))
        ? [$selectedProduct => $docsNav[$selectedProduct]]
        : $docsNav;

    // Build product switcher URLs (first post of each product), preserving context.
    $productSwitcherUrls = [];
    foreach ($docsNav as $pk => $pv) {
        foreach ($pv['sections'] as $sv) {
            if (!empty($sv['posts'])) {
                $productSwitcherUrls[$pk] = $sv['posts'][0]->url . '?product=' . urlencode($pk);
                break;
            }
        }
        if (!isset($productSwitcherUrls[$pk])) $productSwitcherUrls[$pk] = '/docs/?product=' . urlencode($pk);
    }

    // Breadcrumb links (all clickable)
    $docsIndexUrl = '/docs/' . (!empty($selectedProduct) ? '?product=' . urlencode($selectedProduct) : '');
    $productCrumbUrl = null;
    $sectionCrumbUrl = null;
    $currentDocUrl = null;

    if (!empty($currentProduct) && isset($docsNav[$currentProduct])) {
        $productCrumbUrl = $productSwitcherUrls[$currentProduct] ?? ('/docs/?product=' . urlencode($currentProduct));

        if (!empty($currentSection) && $currentSection !== '_root' && isset($docsNav[$currentProduct]['sections'][$currentSection])) {
            $sectionPosts = $docsNav[$currentProduct]['sections'][$currentSection]['posts'] ?? [];
            if (!empty($sectionPosts)) {
                $sectionCrumbUrl = $sectionPosts[0]->url . '?product=' . urlencode($currentProduct);
            }
        }

        foreach (($docsNav[$currentProduct]['sections'] ?? []) as $__sec) {
            foreach (($__sec['posts'] ?? []) as $__p) {
                if (!empty($__p->active)) {
                    $currentDocUrl = $__p->url . '?product=' . urlencode($currentProduct);
                    break 2;
                }
            }
        }
    }

    if ($currentDocUrl === null) {
        $currentDocUrl = '/docs/' . ($post->slug ?? '');
        if (!empty($selectedProduct)) {
            $currentDocUrl .= '?product=' . urlencode($selectedProduct);
        }
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
                                <option value="{{ $pk }}" {{ $pk === $selectedProduct ? 'selected' : '' }}>{{ $pv['name'] }}</option>
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
                                        <a href="{{ $navPost->url }}?product={{ urlencode($productSlug) }}" class="{{ $navPost->active ? 'active' : '' }}">
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
                                            <a href="{{ $navPost->url }}?product={{ urlencode($productSlug) }}" class="{{ $navPost->active ? 'active' : '' }}">
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
                    <a href="{{ $docsIndexUrl }}">Docs</a>
                    @if($currentProduct && isset($docsNav[$currentProduct]))
                    <span class="docs-breadcrumb-sep">/</span>
                    <a href="{{ $productCrumbUrl }}" class="docs-breadcrumb-product" style="font-weight:700;">{{ $docsNav[$currentProduct]['name'] }}</a>
                    @endif
                    @if($currentSection && $currentSection !== '_root' && isset($docsNav[$currentProduct]['sections'][$currentSection]) && !empty($docsNav[$currentProduct]['sections'][$currentSection]['name']))
                    <span class="docs-breadcrumb-sep">/</span>
                    @if($sectionCrumbUrl)
                    <a href="{{ $sectionCrumbUrl }}">{{ $docsNav[$currentProduct]['sections'][$currentSection]['name'] }}</a>
                    @else
                    <span>{{ $docsNav[$currentProduct]['sections'][$currentSection]['name'] }}</span>
                    @endif
                    @endif
                    <span class="docs-breadcrumb-sep">/</span>
                    <a href="{{ $currentDocUrl }}" class="docs-breadcrumb-current">{{ $post->title }}</a>
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
                        foreach ($sidebarNav as $cat) {
                            foreach (($cat['sections'] ?? []) as $sec) {
                                foreach (($sec['posts'] ?? []) as $p) {
                                    $allDocsPosts[] = $p;
                                }
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
                        <a href="{{ $prevDoc->url }}{{ !empty($selectedProduct) ? '?product=' . urlencode($selectedProduct) : '' }}" class="docs-pagination-prev">
                            <span class="docs-pagination-label">&larr; Anterior</span>
                            <span class="docs-pagination-title">{{ $prevDoc->title }}</span>
                        </a>
                        @else
                        <span></span>
                        @endif
                        @if($nextDoc)
                        <a href="{{ $nextDoc->url }}{{ !empty($selectedProduct) ? '?product=' . urlencode($selectedProduct) : '' }}" class="docs-pagination-next">
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
