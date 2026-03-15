@extends('layouts.app')

{{-- SEO --}}
@php
    $postTitle = $translation->title ?? $post->title;
    $postExcerpt = $translation->excerpt ?? $post->excerpt ?? mb_substr(strip_tags($translation->content ?? $post->content), 0, 160);
    $seoTitle = $post->seo_title ?: $postTitle;
    $seoDesc = $post->seo_description ?: $postExcerpt;
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

@section('content')

<div class="container py-5">
    <div class="row">
        {{-- Contenido principal --}}
        <div class="col-lg-8">
            <article class="blog-post">
                {{-- Hero --}}
                @if(!empty($post->show_hero))
                    @php
                        $heroPath = !empty($post->hero_image) ? $post->hero_image : 'themes/default/img/hero/contact_hero.jpg';
                        $heroUrl = (str_starts_with($heroPath, '/') || str_starts_with($heroPath, 'http')) ? $heroPath : asset($heroPath);
                        $heroTitle = $post->hero_title ?: ($translation->title ?? $post->title);
                    @endphp
                    <div class="slider-area mb-4">
                        <div class="single-slider slider-height2 d-flex align-items-center" data-background="{{ $heroUrl }}">
                            <div class="container">
                                <div class="row">
                                    <div class="col-xl-12">
                                        <div class="hero-cap text-center">
                                            <h1>{{ $heroTitle }}</h1>
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

                {{-- Título --}}
                @if(empty($post->hide_title) && empty($post->show_hero))
                    <h1 class="mb-3">{{ $translation->title ?? $post->title }}</h1>
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
                    <span>📅 {{ $dateStr }}</span>
                    @if($postAuthorName)
                        <span class="ms-3"><i class="far fa-user"></i> @if($postAuthorUrl)<a href="{{ $postAuthorUrl }}" class="text-muted">{{ $postAuthorName }}</a>@else{{ $postAuthorName }}@endif</span>
                    @endif
                    @if($post->view_count > 0 && site_setting('blog_show_views', '1') === '1')
                        <span class="ms-3">👁️ {{ $post->view_count }} {{ __('blog.views') }}</span>
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
                <div class="post-content">
                    {!! $translation->content ?? $post->content !!}
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
                <nav class="blog-post-nav mt-5 pt-4 border-top">
                    <div class="row">
                        @if(!empty($prevPost))
                        <div class="col-md-6 mb-3 mb-md-0">
                            <a href="{{ blog_url($prevPost->slug) }}" class="text-decoration-none">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    <div>
                                        <small class="text-muted d-block">{{ __('blog.previous_post') }}</small>
                                        <strong>{{ $prevPost->title }}</strong>
                                    </div>
                                </div>
                            </a>
                        </div>
                        @endif

                        @if(!empty($nextPost))
                        <div class="col-md-6 text-md-end">
                            <a href="{{ blog_url($nextPost->slug) }}" class="text-decoration-none">
                                <div class="d-flex align-items-center justify-content-md-end">
                                    <div>
                                        <small class="text-muted d-block">{{ __('blog.next_post') }}</small>
                                        <strong>{{ $nextPost->title }}</strong>
                                    </div>
                                    <i class="fas fa-arrow-right ms-2"></i>
                                </div>
                            </a>
                        </div>
                        @endif
                    </div>
                </nav>
                @endif
            </article>
        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4">
            @include('blog.layouts._blog-sidebar-extras', ['post' => $post])
            @include('partials.sidebar')
        </div>
    </div>
</div>

@endsection
