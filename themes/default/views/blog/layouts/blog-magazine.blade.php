{{-- Blog Layout: Magazine (featured hero carousel + 2-column grid) --}}
@if(!empty($posts) && count($posts) > 0)
    @php
        $postsArray = is_array($posts) ? $posts : (method_exists($posts, 'toArray') ? $posts->toArray() : (array)$posts);

        // Si hay posts destacados, usarlos para el carousel; si no, top 5 por views
        $__featuredArr = !empty($featuredPosts) ? (is_array($featuredPosts) ? $featuredPosts : (method_exists($featuredPosts, 'toArray') ? $featuredPosts->toArray() : (array)$featuredPosts)) : [];
        if (!empty($__featuredArr)) {
            shuffle($__featuredArr);
            $carouselPosts = array_slice($__featuredArr, 0, 5);
        } else {
            $carouselCount = min(5, count($postsArray));
            $sortedByViews = $postsArray;
            usort($sortedByViews, function($a, $b) {
                $aViews = is_object($a) ? ($a->view_count ?? 0) : ($a['view_count'] ?? 0);
                $bViews = is_object($b) ? ($b->view_count ?? 0) : ($b['view_count'] ?? 0);
                return $bViews - $aViews;
            });
            $carouselPosts = array_slice($sortedByViews, 0, $carouselCount);
        }

        // Grid = ALL posts in original date order
        $rest = $postsArray;
    @endphp

    @include('blog.layouts._blog-header-ticker', ['tickerPosition' => 'top'])

    {{-- Featured Carousel --}}
    @if(count($carouselPosts) > 0)
    <div id="magazineCarousel" class="carousel slide mb-5 pb-4 border-bottom" data-ride="carousel" data-interval="4000">
        <div class="carousel-inner" style="overflow: hidden;">
            @foreach($carouselPosts as $idx => $cPost)
            @php
                $cPost = is_object($cPost) ? $cPost : (object)$cPost;
                if ($cPost->featured_image && !($cPost->hide_featured_image ?? false)) {
                    $cImg = (str_starts_with($cPost->featured_image, '/') || str_starts_with($cPost->featured_image, 'http'))
                        ? $cPost->featured_image
                        : asset($cPost->featured_image);
                } else {
                    $cImg = '/assets/themes/default/img/blog-default.svg';
                }
                $cImg = media_thumb_url($cImg, 'medium');
                $cDateVal = $cPost->published_at ?? $cPost->created_at;
                $cDateStr = $cDateVal instanceof \DateTime ? $cDateVal->format('d/m/Y') : date('d/m/Y', strtotime($cDateVal));
                $cAuthorName = null;
                if (!empty($cPost->user_id)) {
                    if (($cPost->user_type ?? '') === 'superadmin' && !empty($cPost->tenant_id)) {
                        $__pdo = \Screenart\Musedock\Database::connect();
                        $__stmt = $__pdo->prepare("SELECT name FROM admins WHERE tenant_id = ? AND is_root_admin = 1 LIMIT 1");
                        $__stmt->execute([$cPost->tenant_id]);
                        $__ra = $__stmt->fetch(\PDO::FETCH_OBJ);
                        $cAuthorName = $__ra ? $__ra->name : null;
                    }
                    if (!$cAuthorName) {
                        $__author = match($cPost->user_type ?? 'admin') {
                            'superadmin' => \Screenart\Musedock\Models\SuperAdmin::find($cPost->user_id),
                            'admin' => \Screenart\Musedock\Models\Admin::find($cPost->user_id),
                            'user' => \Screenart\Musedock\Models\User::find($cPost->user_id),
                            default => null,
                        };
                        $cAuthorName = $__author ? $__author->name : null;
                    }
                }
                $cAuthorUrl = null;
                if ($cAuthorName && (($cPost->user_type ?? '') === 'admin')) {
                    $__pdo = $__pdo ?? \Screenart\Musedock\Database::connect();
                    $__aStmt = $__pdo->prepare("SELECT author_slug, author_page_enabled FROM admins WHERE id = ? LIMIT 1");
                    $__aStmt->execute([$cPost->user_id]);
                    $__aData = $__aStmt->fetch(\PDO::FETCH_OBJ);
                    if ($__aData && $__aData->author_page_enabled && $__aData->author_slug) {
                        $cAuthorUrl = blog_url($__aData->author_slug, 'author');
                    }
                }
                $cExcerpt = $cPost->excerpt ?: strip_tags($cPost->content ?? '');
                $cExcerpt = trim(preg_replace('/\s+/', ' ', $cExcerpt));
                $cExcerpt = mb_strlen($cExcerpt) > 300 ? mb_substr($cExcerpt, 0, 300) . '...' : $cExcerpt;
            @endphp
            <div class="carousel-item @if($idx === 0) active @endif">
                <div class="row">
                    <div class="col-md-7 mb-3 mb-md-0">
                        <a href="{{ blog_url($cPost->slug) }}" class="d-block overflow-hidden rounded" style="height: 360px; background-color: #f0f2f5;">
                            <img src="{{ $cImg }}" alt="{{ $cPost->title }}" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;">
                        </a>
                    </div>
                    <div class="col-md-5 d-flex flex-column">
                        <h2 style="line-height: 1.25; margin: 0 0 4px 0; font-size: 1.4rem;">
                            <a href="{{ blog_url($cPost->slug) }}" class="text-decoration-none text-dark">{{ $cPost->title }}</a>
                        </h2>
                        <div class="post-meta text-muted small" style="margin-bottom: 8px;">
                            <span><i class="far fa-calendar"></i> {{ $cDateStr }}</span>
                            @if($cAuthorName)
                            <span class="ms-2"><i class="far fa-user"></i> @if($cAuthorUrl)<a href="{{ $cAuthorUrl }}" class="text-muted">{{ $cAuthorName }}</a>@else{{ $cAuthorName }}@endif</span>
                            @endif
                        </div>
                        <p class="text-muted" style="font-size: 0.9rem; line-height: 1.5; margin: 0;">{{ $cExcerpt }}</p>
                        <div style="padding-top: 12px;">
                            <a href="{{ blog_url($cPost->slug) }}" class="btn-read-more">{{ __('blog.read_more') }}</a>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Indicators --}}
        @if(count($carouselPosts) > 1)
        <ol class="carousel-indicators magazine-carousel-indicators">
            @foreach($carouselPosts as $idx => $cp)
            <li data-target="#magazineCarousel" data-slide-to="{{ $idx }}" @if($idx === 0) class="active" @endif></li>
            @endforeach
        </ol>

        <a class="carousel-control-prev magazine-carousel-arrow" href="#magazineCarousel" role="button" data-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="sr-only">Previous</span>
        </a>
        <a class="carousel-control-next magazine-carousel-arrow" href="#magazineCarousel" role="button" data-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="sr-only">Next</span>
        </a>
        @endif
    </div>
    @endif

    {{-- Rest of posts in 2-column grid --}}
    @if(count($rest) > 0)
    <div class="row">
        @foreach($rest as $post)
        @php $post = is_object($post) ? $post : (object)$post; @endphp
        <div class="col-md-6 mb-4">
            <article class="card h-100 shadow-sm border-0">
                @if(($post->post_type ?? 'post') !== 'brief')
                <a href="{{ blog_url($post->slug) }}" class="d-block card-img-wrapper">
                    @php
                        if ($post->featured_image && !($post->hide_featured_image ?? false)) {
                            $imageUrl = (str_starts_with($post->featured_image, '/') || str_starts_with($post->featured_image, 'http'))
                                ? $post->featured_image
                                : asset($post->featured_image);
                        } else {
                            $imageUrl = '/assets/themes/default/img/blog-default.svg';
                        }
                        $imageUrl = media_thumb_url($imageUrl, 'medium');
                    @endphp
                    <img src="{{ $imageUrl }}" alt="{{ $post->title }}" class="card-img-top" loading="lazy" style="width: 100%; height: 220px; object-fit: cover;">
                </a>
                @endif

                <div class="card-body d-flex flex-column" style="padding: 8px 16px 14px !important;">
                    <h2 class="card-title h5 mb-2 card-title-clamp" style="margin-top: 0;">
                        <a href="{{ blog_url($post->slug) }}" class="text-decoration-none text-dark">{{ $post->title }}</a>
                    </h2>

                    <div class="post-meta mb-3 text-muted small">
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
                            if ($postAuthorName && (($post->user_type ?? '') === 'admin')) {
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
                        <span class="ms-2"><i class="far fa-user"></i> @if($postAuthorUrl)<a href="{{ $postAuthorUrl }}" class="text-muted">{{ $postAuthorName }}</a>@else{{ $postAuthorName }}@endif</span>
                        @endif
                    </div>

                    @include('blog.layouts._taxonomy-chips', ['post' => $post])
                    @php
                        $__excerpt = $post->excerpt ?: strip_tags($post->content ?? '');
                        $__excerpt = trim(preg_replace('/\s+/', ' ', $__excerpt));
                        $__excerpt = mb_strlen($__excerpt) > 200 ? mb_substr($__excerpt, 0, 200) . '...' : $__excerpt;
                    @endphp
                    <p class="card-text text-muted mb-0 card-excerpt-clamp">{{ $__excerpt }}</p>

                    <div class="mt-auto pt-3">
                        <a href="{{ blog_url($post->slug) }}" class="btn-read-more">{{ __('blog.read_more') }}</a>
                    </div>
                </div>
            </article>
        </div>
        @endforeach
    </div>
    @endif

    @include('blog.layouts._blog-pagination')
    @include('blog.layouts._blog-header-ticker', ['tickerPosition' => 'bottom'])
@else
    <p class="text-muted text-center">{{ __('blog.no_posts') }}</p>
@endif

@include('blog.layouts._blog-shared-styles')

<style>
/* Magazine Carousel */
#magazineCarousel {
    position: relative;
}

/* Indicators */
.magazine-carousel-indicators {
    position: relative !important;
    bottom: auto !important;
    margin: 18px 0 0 !important;
    display: flex;
    justify-content: center;
    gap: 8px;
}
.magazine-carousel-indicators li {
    width: 10px !important;
    height: 10px !important;
    border-radius: 50% !important;
    background-color: #ccc !important;
    border: none !important;
    padding: 0;
    opacity: 0.5;
    transition: all .3s;
    cursor: pointer;
    text-indent: 0;
    margin: 0 !important;
}
.magazine-carousel-indicators li.active {
    background-color: #6c63ff !important;
    opacity: 1;
    transform: scale(1.2);
}

/* Carousel arrows */
.magazine-carousel-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 36px;
    height: 36px;
    background: rgba(0,0,0,0.08);
    border-radius: 50%;
    opacity: 0;
    transition: opacity .3s;
}
.magazine-carousel-arrow.carousel-control-prev {
    left: -18px;
}
.magazine-carousel-arrow.carousel-control-next {
    right: -18px;
}
#magazineCarousel:hover .magazine-carousel-arrow {
    opacity: 0.6;
}
.magazine-carousel-arrow:hover {
    opacity: 1 !important;
    background: rgba(0,0,0,0.15);
}
.magazine-carousel-arrow .carousel-control-prev-icon,
.magazine-carousel-arrow .carousel-control-next-icon {
    width: 16px;
    height: 16px;
    filter: invert(0.4);
}

@media (max-width: 767.98px) {
    .magazine-carousel-arrow {
        display: none;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var el = document.getElementById('magazineCarousel');
    if (!el) return;
    // Bootstrap 5
    if (typeof bootstrap !== 'undefined' && bootstrap.Carousel) {
        new bootstrap.Carousel(el, { interval: 4000, ride: 'carousel', wrap: true });
    }
    // Bootstrap 4 (jQuery)
    else if (typeof jQuery !== 'undefined') {
        jQuery('#magazineCarousel').carousel({ interval: 4000, ride: 'carousel', wrap: true });
        jQuery('#magazineCarousel').on('slide.bs.carousel', function(e) {
            jQuery('.magazine-carousel-indicators li').removeClass('active');
            jQuery('.magazine-carousel-indicators li').eq(e.to).addClass('active');
        });
    }
});
</script>
