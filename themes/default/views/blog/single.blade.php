@extends('layouts.app')

{{-- SEO --}}
@php
    $seoTitle = $post->seo_title ?: $post->title;
    $seoDesc = $post->seo_description ?: ($post->excerpt ?? mb_substr(strip_tags($post->content), 0, 160));
    $twTitle = $post->twitter_title ?: $seoTitle;
    $twDesc = $post->twitter_description ?: $seoDesc;
    $postImage = $post->featured_image
        ? (str_starts_with($post->featured_image, 'http') ? $post->featured_image : url($post->featured_image))
        : '';
    $twImage = $post->twitter_image
        ? (str_starts_with($post->twitter_image, 'http') ? $post->twitter_image : url($post->twitter_image))
        : $postImage;
@endphp

@section('title', $seoTitle . ' | ' . site_setting('site_name', ''))
@section('description', $seoDesc)
@section('keywords', $post->seo_keywords ?? '')
@section('og_title', $seoTitle)
@section('og_description', $seoDesc)
@section('og_type', 'article')
@php \Screenart\Musedock\View::startSection('og_type', 'article'); @endphp
@if($postImage)
@section('og_image', $postImage)
@endif
@section('twitter_title', $twTitle)
@section('twitter_description', $twDesc)
@if($twImage)
@section('twitter_image', $twImage)
@endif
@if($post->canonical_url)
@section('canonical_url', $post->canonical_url)
@endif
@if($post->robots_directive)
@section('robots', $post->robots_directive)
@endif

@php
    // Article JSON-LD structured data
    $__authorName = '';
    if (!empty($post->user_id)) {
        try {
            $__authorRow = \Screenart\Musedock\Database::query("SELECT name FROM admins WHERE id = ? LIMIT 1", [$post->user_id])->fetch(\PDO::FETCH_OBJ);
            $__authorName = $__authorRow->name ?? '';
        } catch (\Exception $e) {}
    }
    $__articleLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $seoTitle,
        'description' => $seoDesc,
        'url' => url($_SERVER['REQUEST_URI']),
        'datePublished' => $post->published_at ?? $post->created_at ?? date('c'),
        'dateModified' => $post->updated_at ?? $post->published_at ?? date('c'),
        'author' => ['@type' => 'Person', 'name' => $__authorName ?: site_setting('site_name', '')],
        'publisher' => ['@type' => 'Organization', 'name' => site_setting('site_name', ''), 'url' => url('/')],
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => url($_SERVER['REQUEST_URI'])],
    ];
    if (!empty($postImage)) {
        $__articleLd['image'] = $postImage;
    }
    $__siteLogo = site_setting('site_logo', '');
    if (!empty($__siteLogo)) {
        $__articleLd['publisher']['logo'] = ['@type' => 'ImageObject', 'url' => url(public_file_url($__siteLogo))];
    }
    \Screenart\Musedock\View::startSection('jsonld', '<script type="application/ld+json">' . json_encode($__articleLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>');
@endphp

@section('content')

@php
    $showHero = !empty($post->show_hero);
@endphp

{{-- Hero (a lo ancho, pegado al header) --}}
@if($showHero)
    @php
        // Seleccionar imagen hero con rotación automática si no hay imagen personalizada
        $defaultHeroImages = [
            'themes/default/img/hero/contact_hero.jpg',
            'themes/default/img/hero/about_hero.jpg',
            'themes/default/img/hero/services_hero.jpg',
            'themes/default/img/hero/gallery_hero.jpg',
            'themes/default/img/hero/Industries_hero.jpg',
            'themes/default/img/hero/h1_hero.jpg',
        ];

        $hasCustomImage = !empty($post->hero_image) &&
                          !in_array($post->hero_image, $defaultHeroImages) &&
                          !str_ends_with($post->hero_image, '/contact_hero.jpg') &&
                          !str_ends_with($post->hero_image, '/about_hero.jpg') &&
                          !str_ends_with($post->hero_image, '/services_hero.jpg') &&
                          !str_ends_with($post->hero_image, '/gallery_hero.jpg') &&
                          !str_ends_with($post->hero_image, '/Industries_hero.jpg') &&
                          !str_ends_with($post->hero_image, '/h1_hero.jpg');

        if ($hasCustomImage) {
            $heroPath = $post->hero_image;
        } else {
            // Seleccionar imagen aleatoria en cada carga de página
            $heroPath = $defaultHeroImages[array_rand($defaultHeroImages)];
        }

        $heroUrl = (str_starts_with($heroPath, '/') || str_starts_with($heroPath, 'http')) ? $heroPath : asset($heroPath);
        $heroTitle = $post->hero_title ?: $post->title;
    @endphp
    <div class="slider-area">
        <div class="single-slider slider-height2 d-flex align-items-center" data-background="{{ $heroUrl }}">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="hero-cap text-center">
                            <h2>{{ $heroTitle }}</h2>
                            @if(!empty($post->hero_content))
                                <div class="hero-subtitle mt-3">
                                    {!! $post->hero_content !!}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

@php
    $containerPaddingClass = $showHero ? 'pt-0 pb-5' : 'py-5';
    // Determine post template
    $postTemplate = $post->template ?: 'template-sidebar-right';
    // Normalize old values: 'single', null, empty → sidebar-right (retrocompatibility)
    if (in_array($postTemplate, ['single', ''])) {
        $postTemplate = 'template-sidebar-right';
    }
    // Sidebar page structure → force full width (the sidebar nav IS the sidebar)
    $__pageStructure = themeOption('structure.page_structure', 'classic');
    $__isSidebarStructure = ($__pageStructure === 'sidebar' || themeOption('header.header_layout', '') === 'sidebar');
    if ($__isSidebarStructure) {
        $postTemplate = 'page';
    }
    $hasSidebar = !in_array($postTemplate, ['page', 'full-width']);
    $sidebarLeft = ($postTemplate === 'template-sidebar-left');
    $contentCol = $hasSidebar ? 'col-lg-8' : 'col-lg-12';
@endphp

<div class="container {{ $containerPaddingClass }}">
    <div class="row">
        {{-- Sidebar left --}}
        @if($hasSidebar && $sidebarLeft)
        <div class="col-lg-4">
            @include('blog.layouts._blog-sidebar-extras', ['post' => $post])
            @include('partials.sidebar')
        </div>
        @endif

        {{-- Contenido principal --}}
        <div class="{{ $contentCol }}">
            <article class="blog-post-single page-content-wrapper">

                {{-- Título - Ocultar solo si hide_title está activado --}}
                @if(!$post->hide_title || $post->hide_title != 1)
                    <h1 class="mb-3">{{ $post->title }}</h1>
                @endif

                {{-- Meta información --}}
                <div class="post-meta mb-4 text-muted">
                    @php
                        $dateVal = $post->published_at ?? $post->created_at;
                        $dateStr = $dateVal instanceof \DateTime ? $dateVal->format('d/m/Y') : date('d/m/Y', strtotime($dateVal));
                        $postAuthorName = null;
                        if (!empty($post->user_id)) {
                            if (($post->user_type ?? '') === 'superadmin' && !empty($post->tenant_id)) {
                                $__pdo = \Screenart\Musedock\Database::connect();
                                $__stmt = $__pdo->prepare("SELECT name FROM admins WHERE tenant_id = ? AND is_root_admin = 1 LIMIT 1");
                                $__stmt->execute([$post->tenant_id]);
                                $__ra = $__stmt->fetch(\PDO::FETCH_OBJ);
                                $postAuthorName = $__ra ? $__ra->name : null;
                            }
                            if (!$postAuthorName) {
                                $__author = match($post->user_type ?? 'admin') {
                                    'superadmin' => \Screenart\Musedock\Models\SuperAdmin::find($post->user_id),
                                    'admin' => \Screenart\Musedock\Models\Admin::find($post->user_id),
                                    'user' => \Screenart\Musedock\Models\User::find($post->user_id),
                                    default => null,
                                };
                                $postAuthorName = $__author ? $__author->name : null;
                            }
                        }
                        $postAuthorUrl = null;
                        if ($postAuthorName && ($post->user_type ?? '') === 'admin') {
                            $__pdo = $__pdo ?? \Screenart\Musedock\Database::connect();
                            $__aStmt = $__pdo->prepare("SELECT author_slug, author_page_enabled FROM admins WHERE id = ? LIMIT 1");
                            $__aStmt->execute([$post->user_id]);
                            $__aData = $__aStmt->fetch(\PDO::FETCH_OBJ);
                            if ($__aData && $__aData->author_page_enabled && $__aData->author_slug) {
                                $postAuthorUrl = blog_url($__aData->author_slug, 'author');
                            }
                        }
                    @endphp
                    <span><i class="far fa-calendar"></i> {{ $dateStr }}</span>
                    @if($postAuthorName)
                        <span class="ms-3"><i class="far fa-user"></i> @if($postAuthorUrl)<a href="{{ $postAuthorUrl }}" class="text-muted">{{ $postAuthorName }}</a>@else{{ $postAuthorName }}@endif</span>
                    @endif
                    @if($post->view_count > 0 && site_setting('blog_show_views', '1') === '1')
                        <span class="ms-3"><i class="far fa-eye"></i> {{ $post->view_count }} {{ __('blog.views') }}</span>
                    @endif
                </div>

                {{-- Imagen destacada --}}
                @if($post->featured_image && !$post->hide_featured_image)
                    @php
                        $imageUrl = (str_starts_with($post->featured_image, '/') || str_starts_with($post->featured_image, 'http'))
                            ? $post->featured_image
                            : asset($post->featured_image);
                    @endphp
                    <img src="{{ $imageUrl }}" alt="{{ $post->title }}" class="img-fluid rounded mb-4" loading="lazy" style="width: 100%; max-height: 500px; object-fit: cover;">
                @endif

                {{-- Contenido --}}
                <div class="post-content page-body">
                    {!! $post->content !!}
                </div>

                {{-- Categorías y etiquetas --}}
                @if(!empty($post->categories) || !empty($post->tags))
                <div class="mt-5 pt-4 border-top">
                    @if(!empty($post->categories))
                    <div class="mb-2">
                        <span class="tx-section-label">{{ __('blog.categories') }}</span>
                        @foreach($post->categories as $__cat)
                        @php
                            $__cc = !empty($__cat->color) ? trim($__cat->color) : null;
                            if ($__cc) {
                                $__ch = ltrim($__cc, '#');
                                if (strlen($__ch) === 3) { $__ch = $__ch[0].$__ch[0].$__ch[1].$__ch[1].$__ch[2].$__ch[2]; }
                                $__cr = hexdec(substr($__ch,0,2)); $__cg = hexdec(substr($__ch,2,2)); $__cb = hexdec(substr($__ch,4,2));
                                $__cs = "background:rgba({$__cr},{$__cg},{$__cb},0.12);color:{$__cc};border-color:rgba({$__cr},{$__cg},{$__cb},0.35);";
                            } else {
                                $__cs = 'background:#fff8e6;color:#c47a00;border-color:rgba(240,208,128,0.8);';
                            }
                        @endphp
                        <a href="{{ blog_url($__cat->slug, 'category') }}" class="tx-chip tx-chip-cat" style="{{ $__cs }}">{{ $__cat->name }}</a>
                        @endforeach
                    </div>
                    @endif
                    @if(!empty($post->tags))
                    <div>
                        <span class="tx-section-label">{{ __('blog.tags') }}</span>
                        @foreach($post->tags as $__tag)
                        @php
                            $__tc = !empty($__tag->color) ? trim($__tag->color) : null;
                            if ($__tc) {
                                $__th = ltrim($__tc, '#');
                                if (strlen($__th) === 3) { $__th = $__th[0].$__th[0].$__th[1].$__th[1].$__th[2].$__th[2]; }
                                $__tr = hexdec(substr($__th,0,2)); $__tg = hexdec(substr($__th,2,2)); $__tb = hexdec(substr($__th,4,2));
                                $__ts = "background:rgba({$__tr},{$__tg},{$__tb},0.10);color:{$__tc};border-color:rgba({$__tr},{$__tg},{$__tb},0.32);";
                            } else {
                                $__ts = 'background:#eaf0fb;color:#1a4fa0;border-color:rgba(154,184,232,0.8);';
                            }
                        @endphp
                        <a href="{{ blog_url($__tag->slug, 'tag') }}" class="tx-chip tx-chip-tag" style="{{ $__ts }}">{{ $__tag->name }}</a>
                        @endforeach
                    </div>
                    @endif
                    <style>
                    .tx-section-label {
                        display: inline-block;
                        font-size: 10px;
                        font-weight: 600;
                        text-transform: uppercase;
                        letter-spacing: 0.1em;
                        color: #9ca3af;
                        margin-right: 6px;
                        vertical-align: middle;
                    }
                    .tx-chip {
                        display: inline-block;
                        padding: 2px 8px;
                        font-family: 'JetBrains Mono', 'Fira Mono', 'Courier New', monospace;
                        font-size: 10.5px;
                        font-weight: 500;
                        letter-spacing: 0.07em;
                        text-transform: uppercase;
                        border-radius: 3px;
                        border: 1px solid;
                        text-decoration: none;
                        white-space: nowrap;
                        transition: filter 0.15s ease, opacity 0.15s ease;
                        line-height: 1.7;
                    }
                    .tx-chip:hover { filter: brightness(0.88); opacity: 0.9; text-decoration: none; }
                    .tx-chip:visited { color: inherit; }
                    @media (max-width: 575px) {
                        .tx-chip { font-size: 9.5px; padding: 2px 6px; }
                    }
                    </style>
                </div>
                @endif

                {{-- Navegación prev/next --}}
                @if(!empty($prevPost) || !empty($nextPost))
                @php
                    $isBriefNav = ($post->post_type ?? 'post') === 'brief';
                    $navLabel = $isBriefNav ? 'brief' : '';
                @endphp
                <nav class="post-nav mt-5">
                    <div class="post-nav-inner">
                        @if(!empty($prevPost))
                        <a href="{{ blog_url($prevPost->slug) }}" class="post-nav-link post-nav-prev">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                            <div class="post-nav-text">
                                <span class="post-nav-label">{{ __('blog.previous_post') }}</span>
                                <span class="post-nav-title">{{ $prevPost->title }}</span>
                            </div>
                        </a>
                        @else
                        <span class="post-nav-link post-nav-placeholder"></span>
                        @endif

                        @if(!empty($nextPost))
                        <a href="{{ blog_url($nextPost->slug) }}" class="post-nav-link post-nav-next">
                            <div class="post-nav-text">
                                <span class="post-nav-label">{{ __('blog.next_post') }}</span>
                                <span class="post-nav-title">{{ $nextPost->title }}</span>
                            </div>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </a>
                        @else
                        <span class="post-nav-link post-nav-placeholder"></span>
                        @endif
                    </div>
                </nav>
                <style>
                .post-nav { border-top: 1px solid #eee; padding-top: 1.5rem; }
                .post-nav-inner { display: flex; justify-content: space-between; gap: 1rem; }
                .post-nav-link { display: flex; align-items: center; gap: 12px; text-decoration: none; color: #333; padding: 12px 16px; border-radius: 8px; transition: all 0.2s; flex: 1; min-width: 0; }
                .post-nav-link:hover { background: #f8f8f8; color: #333; }
                .post-nav-link:hover svg { color: var(--header-link-hover-color, #ff5e15); }
                .post-nav-link svg { flex-shrink: 0; color: #aaa; transition: color 0.2s; }
                .post-nav-next { justify-content: flex-end; text-align: right; }
                .post-nav-text { display: flex; flex-direction: column; min-width: 0; }
                .post-nav-label { font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
                .post-nav-title { font-weight: 600; font-size: 14px; line-height: 1.3; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
                .post-nav-placeholder { flex: 1; }
                @media (max-width: 575px) {
                    .post-nav-inner { flex-direction: column; gap: 0; }
                    .post-nav-next { text-align: left; justify-content: flex-start; flex-direction: row-reverse; }
                    .post-nav-link { border-radius: 0; border-bottom: 1px solid #f0f0f0; }
                    .post-nav-link:last-child { border-bottom: none; }
                }
                </style>
                @endif
            </article>

            {{-- Code blocks styling --}}
            <style>
            /* Inline code */
            .post-content code:not(pre code) {
                background: #f3f4f6;
                color: #e11d48;
                padding: 2px 6px;
                border-radius: 4px;
                font-size: 0.88em;
                font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
                word-break: break-word;
            }
            /* Code block container */
            .code-block {
                background: #1e1e2e;
                border-radius: 8px;
                margin: 1.5rem 0;
                overflow: hidden;
                font-size: 0.88rem;
            }
            .code-block-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 16px;
                background: #313244;
                color: #a6adc8;
                font-size: 0.78rem;
                font-family: inherit;
            }
            .code-block-lang {
                font-weight: 600;
                text-transform: capitalize;
            }
            .code-block-copy {
                display: flex;
                align-items: center;
                gap: 4px;
                background: none;
                border: none;
                color: #a6adc8;
                cursor: pointer;
                padding: 2px 8px;
                border-radius: 4px;
                font-size: 0.78rem;
                transition: all 0.15s;
            }
            .code-block-copy:hover {
                background: rgba(255,255,255,0.1);
                color: #cdd6f4;
            }
            .code-block pre {
                margin: 0;
                padding: 16px;
                overflow-x: auto;
                color: #cdd6f4;
                line-height: 1.6;
            }
            .code-block pre code {
                background: none !important;
                color: inherit !important;
                padding: 0 !important;
                border-radius: 0 !important;
                font-size: inherit !important;
                font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
            }
            </style>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var container = document.querySelector('.post-content');
                if (!container) return;

                // 0. Clean ChatGPT pasted code blocks (CodeMirror divs with token classes)
                container.querySelectorAll('.cm-content, [id="code-block-viewer"]').forEach(function(cm) {
                    var wrapper = cm.closest('.relative.w-full') || cm.closest('[class*="border-radius-3xl"]')?.closest('.relative');
                    if (!wrapper) wrapper = cm.closest('div.relative');
                    var text = cm.textContent.trim();
                    if (text && wrapper) {
                        var target = wrapper;
                        // Walk up to find the outermost ChatGPT wrapper
                        while (target.parentElement && target.parentElement !== container &&
                               !target.parentElement.matches('p, h1, h2, h3, h4, h5, h6, ul, ol, blockquote, article')) {
                            if (target.parentElement.classList.contains('relative') && target.parentElement.classList.contains('w-full')) {
                                target = target.parentElement;
                                break;
                            }
                            target = target.parentElement;
                        }
                        makeCodeBlock(target, text);
                    }
                });

                // 1. Wrap existing <pre><code> blocks
                container.querySelectorAll('pre').forEach(function(pre) {
                    if (pre.closest('.code-block')) return;
                    makeCodeBlock(pre, pre.textContent);
                });

                // 2. Detect standalone <code> that look like code blocks
                container.querySelectorAll('code').forEach(function(code) {
                    if (code.closest('pre') || code.closest('.code-block') || code.closest('table') || code.closest('li')) return;
                    var text = code.textContent;
                    var isBlock = (text.includes('\n') && text.length > 40) || text.length > 120;
                    if (isBlock) {
                        makeCodeBlock(code, text);
                    }
                });

                function makeCodeBlock(el, text) {
                    var lang = detectLang(text);
                    var wrapper = document.createElement('div');
                    wrapper.className = 'code-block';
                    wrapper.innerHTML =
                        '<div class="code-block-header">' +
                            '<span class="code-block-lang">' + lang + '</span>' +
                            '<button class="code-block-copy" onclick="copyCode(this)" title="Copiar">' +
                            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg> Copiar</button>' +
                        '</div>' +
                        '<pre><code></code></pre>';
                    wrapper.querySelector('code').textContent = text.trim();
                    el.replaceWith(wrapper);
                }

                function detectLang(text) {
                    if (/<\?php|namespace |use |function .*\(.*\$/.test(text)) return 'PHP';
                    if (/tinymce\.init|toolbar_mode|selector:|addEventListener|querySelector|document\.|function\s*\(|const |let |=>|\.then\(/.test(text)) return 'JavaScript';
                    if (/[\{][\s\S]*?[a-z-]+\s*:\s*[^;]+;/.test(text) && !/function/.test(text)) return 'CSS';
                    if (/<[a-z][\s\S]*>/i.test(text) && /<\/[a-z]+>/i.test(text)) return 'HTML';
                    if (/^(SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER)\b/i.test(text.trim())) return 'SQL';
                    if (/^\s*\$\s|apt |npm |composer |php |git |curl /m.test(text)) return 'Bash';
                    if (/def |import |class .*:$|print\(/m.test(text)) return 'Python';
                    return 'Codigo';
                }

                // 3. Clean leftover empty ChatGPT divs
                container.querySelectorAll('[class*="token-"], [class*="corner-superellipse"], [class*="clipPathFallback"]').forEach(function(el) {
                    var parent = el.closest('.relative.w-full');
                    if (parent && !parent.querySelector('.code-block') && parent.textContent.trim() === '') {
                        parent.remove();
                    }
                });
            });

            function copyCode(btn) {
                var code = btn.closest('.code-block').querySelector('code');
                navigator.clipboard.writeText(code.textContent).then(function() {
                    var orig = btn.innerHTML;
                    btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#a6e3a1" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Copiado';
                    setTimeout(function() { btn.innerHTML = orig; }, 1500);
                });
            }
            </script>
        </div>

        {{-- Sidebar right --}}
        @if($hasSidebar && !$sidebarLeft)
        <div class="col-lg-4">
            @include('blog.layouts._blog-sidebar-extras', ['post' => $post])
            @include('partials.sidebar')
        </div>
        @endif
    </div>
</div>

@endsection
