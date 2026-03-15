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

<!-- ====== Banner Start ====== -->
<section class="ud-page-banner" style="background-image: linear-gradient(rgba(48, 86, 211, 0.55), rgba(48, 86, 211, 0.55)), url('{{ asset('img/background.jpg') }}'); background-size: cover; background-position: center; background-repeat: no-repeat;">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="ud-banner-content">
                    <h1>{{ $post->title }}</h1>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- ====== Banner End ====== -->

<!-- ====== Blog Details Start ====== -->
<section class="ud-blog-details py-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                {{-- Imagen destacada --}}
                @if($post->featured_image && !$post->hide_featured_image)
                <div class="ud-blog-details-image mb-4">
                    @php
                        $imageUrl = (str_starts_with($post->featured_image, '/') || str_starts_with($post->featured_image, 'http'))
                            ? $post->featured_image
                            : asset($post->featured_image);
                    @endphp
                    <img src="{{ $imageUrl }}" alt="{{ $post->title }}" class="img-fluid rounded" loading="lazy" />
                    <div class="ud-blog-overlay">
                        <div class="ud-blog-overlay-content">
                            <div class="ud-blog-meta">
                                @php
                                    $dateVal = $post->published_at ?? $post->created_at;
                                    $dateStr = $dateVal instanceof \DateTime ? $dateVal->format('d M Y') : date('d M Y', strtotime($dateVal));
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
                                <p class="date">
                                    <i class="lni lni-calendar"></i> <span>{{ $dateStr }}</span>
                                </p>
                                @if($postAuthorName)
                                <p class="date">
                                    <i class="lni lni-user"></i> <span>@if($postAuthorUrl)<a href="{{ $postAuthorUrl }}">{{ $postAuthorName }}</a>@else{{ $postAuthorName }}@endif</span>
                                </p>
                                @endif
                                @if($post->view_count > 0 && site_setting('blog_show_views', '1') === '1')
                                <p class="view">
                                    <i class="lni lni-eye"></i> <span>{{ $post->view_count }}</span>
                                </p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            <div class="col-lg-8">
                <div class="ud-blog-details-content">
                    {{-- Contenido --}}
                    <div class="post-content">
                        {!! $post->content !!}
                    </div>

                    {{-- Categorías y Tags --}}
                    @if(!empty($post->categories) || !empty($post->tags))
                    <div class="ud-blog-details-action mt-5 pt-4 border-top">
                        @if(!empty($post->categories))
                        <div class="post-taxonomy-list mb-3">
                            @foreach($post->categories as $category)
                                <a href="{{ blog_url($category->slug, 'category') }}" class="taxonomy-badge">{{ $category->name }}</a>
                            @endforeach
                        </div>
                        @endif

                        @if(!empty($post->tags))
                        <div class="post-taxonomy-list">
                            @foreach($post->tags as $tag)
                                <a href="{{ blog_url($tag->slug, 'tag') }}" class="taxonomy-badge">{{ $tag->name }}</a>
                            @endforeach
                        </div>
                        @endif

                        <style>
                        .post-taxonomy-list {
                            display: flex;
                            flex-wrap: wrap;
                            gap: 10px;
                        }
                        .post-taxonomy-list .taxonomy-badge {
                            display: inline-block;
                            padding: 8px 20px;
                            font-size: .85rem;
                            font-weight: 500;
                            color: #4a5568;
                            background-color: transparent;
                            border: 1px solid #d1d5db;
                            border-radius: 4px;
                            text-decoration: none;
                            text-transform: uppercase;
                            letter-spacing: 0.5px;
                            transition: all .2s ease;
                        }
                        .post-taxonomy-list .taxonomy-badge:hover {
                            color: #1a202c;
                            border-color: #4a5568;
                            background-color: #f7fafc;
                        }
                        </style>

                        <div class="ud-blog-share mt-4">
                            <h6>{{ __('blog.frontend.share_post') }}</h6>
                            <ul class="ud-blog-share-links">
                                <li>
                                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ url(blog_url($post->slug)) }}" target="_blank" class="facebook">
                                        <i class="lni lni-facebook-filled"></i>
                                    </a>
                                </li>
                                <li>
                                    <a href="https://twitter.com/intent/tweet?url={{ url(blog_url($post->slug)) }}&text={{ urlencode($post->title) }}" target="_blank" class="twitter">
                                        <i class="lni lni-twitter-filled"></i>
                                    </a>
                                </li>
                                <li>
                                    <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ url(blog_url($post->slug)) }}" target="_blank" class="linkedin">
                                        <i class="lni lni-linkedin-original"></i>
                                    </a>
                                </li>
                            </ul>
                        </div>
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
                                        <i class="lni lni-arrow-left me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">{{ __('blog.frontend.previous_post') }}</small>
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
                                            <small class="text-muted d-block">{{ __('blog.frontend.next_post') }}</small>
                                            <strong>{{ $nextPost->title }}</strong>
                                        </div>
                                        <i class="lni lni-arrow-right ms-2"></i>
                                    </div>
                                </a>
                            </div>
                            @endif
                        </div>
                    </nav>
                    @endif
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="col-lg-4">
                <div class="ud-blog-sidebar">
                    {{-- Últimas publicaciones --}}
                    @php
                        $recentPosts = [];
                        try {
                            $pdo = \Screenart\Musedock\Database::connect();
                            $tenantId = tenant_id();
                            $currentLang = detectLanguage();

                            if ($tenantId) {
                                $stmt = $pdo->prepare("
                                    SELECT p.id, p.slug, pt.title
                                    FROM posts p
                                    INNER JOIN post_translations pt ON p.id = pt.post_id
                                    WHERE p.tenant_id = ? AND p.status = 'published' AND pt.language_code = ? AND p.id != ?
                                    ORDER BY p.published_at DESC
                                    LIMIT 5
                                ");
                                $stmt->execute([$tenantId, $currentLang, $post->id]);
                            } else {
                                $stmt = $pdo->prepare("
                                    SELECT p.id, p.slug, pt.title
                                    FROM posts p
                                    INNER JOIN post_translations pt ON p.id = pt.post_id
                                    WHERE p.tenant_id IS NULL AND p.status = 'published' AND pt.language_code = ? AND p.id != ?
                                    ORDER BY p.published_at DESC
                                    LIMIT 5
                                ");
                                $stmt->execute([$currentLang, $post->id]);
                            }
                            $recentPosts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                        } catch (\PDOException $e) {
                            $recentPosts = [];
                        }
                    @endphp

                    @if(count($recentPosts) > 0)
                    <div class="ud-articles-box mb-4">
                        <h3 class="ud-articles-box-title">{{ __('blog.frontend.recent_posts') }}</h3>
                        <ul class="ud-articles-list">
                            @foreach($recentPosts as $recentPost)
                            <li>
                                <div class="ud-article-content">
                                    <h5>
                                        <a href="{{ blog_url($recentPost['slug']) }}">
                                            {{ $recentPost['title'] }}
                                        </a>
                                    </h5>
                                </div>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    {{-- Categorías --}}
                    @if(!empty($post->categories))
                    <div class="ud-categories-box">
                        <h3 class="ud-articles-box-title">{{ __('blog.categories') }}</h3>
                        <ul class="ud-categories-list">
                            @foreach($post->categories as $category)
                            <li>
                                <a href="{{ blog_url($category->slug, 'category') }}">
                                    {{ $category->name }}
                                </a>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
<!-- ====== Blog Details End ====== -->

@endsection
