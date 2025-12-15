@extends('layouts.app')

{{-- SEO --}}
@section('title')
    {{ $post->title . ' | ' . site_setting('site_name', '') }}
@endsection

@section('description')
    {{ $post->excerpt ?? mb_substr(strip_tags($post->content), 0, 160) }}
@endsection

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
                        $imageUrl = (str_starts_with($post->featured_image, '/media/') || str_starts_with($post->featured_image, 'http'))
                            ? $post->featured_image
                            : asset($post->featured_image);
                    @endphp
                    <img src="{{ $imageUrl }}" alt="{{ $post->title }}" class="img-fluid rounded" />
                    <div class="ud-blog-overlay">
                        <div class="ud-blog-overlay-content">
                            <div class="ud-blog-meta">
                                @php
                                    $dateVal = $post->published_at ?? $post->created_at;
                                    $dateStr = $dateVal instanceof \DateTime ? $dateVal->format('d M Y') : date('d M Y', strtotime($dateVal));
                                @endphp
                                <p class="date">
                                    <i class="lni lni-calendar"></i> <span>{{ $dateStr }}</span>
                                </p>
                                @if($post->view_count > 0)
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
                        @if(!empty($post->tags))
                        <ul class="ud-blog-tags">
                            @foreach($post->tags as $tag)
                            <li>
                                <a href="/blog/tag/{{ $tag->slug }}">{{ $tag->name }}</a>
                            </li>
                            @endforeach
                        </ul>
                        @endif

                        <div class="ud-blog-share">
                            <h6>{{ __('blog.frontend.share_post') }}</h6>
                            <ul class="ud-blog-share-links">
                                <li>
                                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ url('/blog/' . $post->slug) }}" target="_blank" class="facebook">
                                        <i class="lni lni-facebook-filled"></i>
                                    </a>
                                </li>
                                <li>
                                    <a href="https://twitter.com/intent/tweet?url={{ url('/blog/' . $post->slug) }}&text={{ urlencode($post->title) }}" target="_blank" class="twitter">
                                        <i class="lni lni-twitter-filled"></i>
                                    </a>
                                </li>
                                <li>
                                    <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ url('/blog/' . $post->slug) }}" target="_blank" class="linkedin">
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
                                <a href="/blog/{{ $prevPost->slug }}" class="text-decoration-none">
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
                                <a href="/blog/{{ $nextPost->slug }}" class="text-decoration-none">
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
                                        <a href="/blog/{{ $recentPost['slug'] }}">
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
                                <a href="/blog/category/{{ $category->slug }}">
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
