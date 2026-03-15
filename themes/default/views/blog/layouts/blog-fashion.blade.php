{{-- Blog Layout: Fashion (circular images, elegant typography, carousel) --}}
@if(!empty($posts) && count($posts) > 0)
    @php
        $postsArray = is_array($posts) ? $posts : (method_exists($posts, 'toArray') ? $posts->toArray() : (array)$posts);

        // Pick top 5 by view_count for carousel
        $carouselCount = min(5, count($postsArray));
        $sortedByViews = $postsArray;
        usort($sortedByViews, function($a, $b) {
            $aViews = is_object($a) ? ($a->view_count ?? 0) : ($a['view_count'] ?? 0);
            $bViews = is_object($b) ? ($b->view_count ?? 0) : ($b['view_count'] ?? 0);
            return $bViews - $aViews;
        });
        $carouselPosts = array_slice($sortedByViews, 0, $carouselCount);
        $carouselPostIds = array_map(function($p) {
            return is_object($p) ? $p->id : ($p['id'] ?? 0);
        }, $carouselPosts);

        // Grid = ALL posts in original date order (independent of carousel)
        $rest = $postsArray;
    @endphp

    @include('blog.layouts._blog-header-ticker', ['tickerPosition' => 'top'])

    {{-- Featured Carousel with large circles --}}
    @if(count($carouselPosts) > 0)
    <div id="fashionCarousel" class="carousel slide fashion-featured text-center mt-4 mb-5 pb-4 border-bottom">

        <div class="carousel-inner">
            @foreach($carouselPosts as $idx => $carouselPost)
            @php
                $carouselPost = is_object($carouselPost) ? $carouselPost : (object)$carouselPost;
                if ($carouselPost->featured_image && !($carouselPost->hide_featured_image ?? false)) {
                    $featuredImg = (str_starts_with($carouselPost->featured_image, '/') || str_starts_with($carouselPost->featured_image, 'http'))
                        ? $carouselPost->featured_image
                        : asset($carouselPost->featured_image);
                } else {
                    $featuredImg = '/assets/themes/default/img/blog-default.svg';
                }
                $featuredImg = media_thumb_url($featuredImg, 'medium');
                $dateVal = $carouselPost->published_at ?? $carouselPost->created_at;
                $dateStr = $dateVal instanceof \DateTime ? $dateVal->format('d/m/Y') : date('d/m/Y', strtotime($dateVal));
                $postAuthorName = null;
                if (!empty($carouselPost->user_id)) {
                    if (($carouselPost->user_type ?? '') === 'superadmin' && !empty($carouselPost->tenant_id)) {
                        $__pdo = \Screenart\Musedock\Database::connect();
                        $__stmt = $__pdo->prepare("SELECT name FROM admins WHERE tenant_id = ? AND is_root_admin = 1 LIMIT 1");
                        $__stmt->execute([$carouselPost->tenant_id]);
                        $__ra = $__stmt->fetch(\PDO::FETCH_OBJ);
                        $postAuthorName = $__ra ? $__ra->name : null;
                    }
                    if (!$postAuthorName) {
                        $__author = match($carouselPost->user_type ?? 'admin') {
                            'superadmin' => \Screenart\Musedock\Models\SuperAdmin::find($carouselPost->user_id),
                            'admin' => \Screenart\Musedock\Models\Admin::find($carouselPost->user_id),
                            'user' => \Screenart\Musedock\Models\User::find($carouselPost->user_id),
                            default => null,
                        };
                        $postAuthorName = $__author ? $__author->name : null;
                    }
                }
                $postAuthorUrl = null;
                if ($postAuthorName && (($carouselPost->user_type ?? '') === 'admin')) {
                    $__pdo = $__pdo ?? \Screenart\Musedock\Database::connect();
                    $__aStmt = $__pdo->prepare("SELECT author_slug, author_page_enabled FROM admins WHERE id = ? LIMIT 1");
                    $__aStmt->execute([$carouselPost->user_id]);
                    $__aData = $__aStmt->fetch(\PDO::FETCH_OBJ);
                    if ($__aData && $__aData->author_page_enabled && $__aData->author_slug) {
                        $postAuthorUrl = blog_url($__aData->author_slug, 'author');
                    }
                }
                $__excerpt = $carouselPost->excerpt ?: strip_tags($carouselPost->content ?? '');
                $__excerpt = trim(preg_replace('/\s+/', ' ', $__excerpt));
                $__excerpt = mb_strlen($__excerpt) > 250 ? mb_substr($__excerpt, 0, 250) . '...' : $__excerpt;
            @endphp
            <div class="carousel-item @if($idx === 0) active @endif">
                <a href="{{ blog_url($carouselPost->slug) }}" class="fashion-circle-link mb-4">
                    <div class="fashion-circle fashion-circle-lg">
                        <img src="{{ $featuredImg }}" alt="{{ $carouselPost->title }}" loading="lazy">
                    </div>
                </a>
                <div class="text-muted small" style="margin-bottom: 0; line-height: 1;">
                    <span><i class="far fa-calendar"></i> {{ $dateStr }}</span>
                    @if($postAuthorName)
                    <span class="ms-3"><i class="far fa-user"></i> @if($postAuthorUrl)<a href="{{ $postAuthorUrl }}" class="text-muted">{{ $postAuthorName }}</a>@else{{ $postAuthorName }}@endif</span>
                    @endif
                </div>
                <h2 class="fashion-featured-title">
                    <a href="{{ blog_url($carouselPost->slug) }}" class="text-decoration-none">{{ $carouselPost->title }}</a>
                </h2>
                <p class="fashion-featured-excerpt text-muted mx-auto" style="max-width: 600px; margin-bottom: 10px;">{{ $__excerpt }}</p>
                <div style="margin-top: 10px;">
                    <a href="{{ blog_url($carouselPost->slug) }}" class="btn-read-more">{{ __('blog.read_more') }}</a>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Indicators --}}
        @if(count($carouselPosts) > 1)
        <ol class="carousel-indicators fashion-carousel-indicators">
            @foreach($carouselPosts as $idx => $cp)
            <li data-target="#fashionCarousel" data-slide-to="{{ $idx }}" @if($idx === 0) class="active" @endif></li>
            @endforeach
        </ol>

        <a class="carousel-control-prev fashion-carousel-arrow" href="#fashionCarousel" role="button" data-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="sr-only">Previous</span>
        </a>
        <a class="carousel-control-next fashion-carousel-arrow" href="#fashionCarousel" role="button" data-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="sr-only">Next</span>
        </a>
        @endif
    </div>
    @endif

    {{-- Rest of posts in grid with circular images --}}
    @if(count($rest) > 0)
    <div class="row">
        @foreach($rest as $post)
        @php $post = is_object($post) ? $post : (object)$post; @endphp
        <div class="col-md-6 col-lg-4 mb-5">
            <article class="fashion-card text-center">
                <a href="{{ blog_url($post->slug) }}" class="fashion-circle-link mb-3">
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
                    <div class="fashion-circle fashion-circle-sm">
                        <img src="{{ $imageUrl }}" alt="{{ $post->title }}" loading="lazy">
                    </div>
                </a>

                <div class="post-meta mb-0 text-muted small">
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

                <h3 class="fashion-card-title mb-2">
                    <a href="{{ blog_url($post->slug) }}" class="text-decoration-none">{{ $post->title }}</a>
                </h3>

                @include('blog.layouts._taxonomy-chips', ['post' => $post])
                @php
                    $__excerpt = $post->excerpt ?: strip_tags($post->content ?? '');
                    $__excerpt = trim(preg_replace('/\s+/', ' ', $__excerpt));
                    $__excerpt = mb_strlen($__excerpt) > 150 ? mb_substr($__excerpt, 0, 150) . '...' : $__excerpt;
                @endphp
                <p class="fashion-card-excerpt text-muted">{{ $__excerpt }}</p>

                <a href="{{ blog_url($post->slug) }}" class="btn-read-more">{{ __('blog.read_more') }}</a>
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
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&display=swap');

/* Fashion/Circle Layout */
.fashion-circle-link {
    display: block;
    text-decoration: none;
}
.fashion-circle {
    border-radius: 50%;
    overflow: hidden;
    margin: 0 auto;
    border: 3px solid #f0f0f0;
    transition: border-color .3s ease, transform .3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}
.fashion-circle-lg {
    width: 320px;
    height: 320px;
}
.fashion-circle-sm {
    width: 200px;
    height: 200px;
}
.fashion-circle img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center center;
    display: block;
    min-width: 100%;
    min-height: 100%;
    transition: transform .4s ease;
}
.fashion-circle-link:hover .fashion-circle {
    border-color: #ccc;
    transform: scale(1.03);
}
.fashion-circle-link:hover .fashion-circle img {
    transform: scale(1.08);
}

/* Featured post / Carousel */
#fashionCarousel {
    position: relative;
}
#fashionCarousel .carousel-inner {
    overflow: hidden;
}
.fashion-featured-title {
    font-family: 'Playfair Display', 'Georgia', serif;
    font-size: 1.8rem;
    font-weight: 700;
    line-height: 1.3;
    color: #1a1a1a;
    margin-top: 10px !important;
    margin-bottom: 10px !important;
}
.fashion-featured-title a {
    color: #1a1a1a;
    transition: color .2s;
}
.fashion-featured-title a:hover {
    color: #d4a574;
}
.fashion-featured-excerpt {
    font-size: .95rem;
    line-height: 1.7;
    color: #666;
}

/* Carousel indicators */
.fashion-carousel-indicators {
    position: relative !important;
    bottom: auto !important;
    margin: 18px 0 0 !important;
    display: flex;
    justify-content: center;
    gap: 8px;
}
.fashion-carousel-indicators li {
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
.fashion-carousel-indicators li.active {
    background-color: #d4a574 !important;
    opacity: 1;
    transform: scale(1.2);
}

/* Carousel arrows */
.fashion-carousel-arrow {
    position: absolute;
    top: 160px;
    width: 36px;
    height: 36px;
    background: rgba(0,0,0,0.08);
    border-radius: 50%;
    opacity: 0;
    transition: opacity .3s;
}
#fashionCarousel:hover .fashion-carousel-arrow {
    opacity: 0.6;
}
.fashion-carousel-arrow:hover {
    opacity: 1 !important;
    background: rgba(0,0,0,0.15);
}
.fashion-carousel-arrow .carousel-control-prev-icon,
.fashion-carousel-arrow .carousel-control-next-icon {
    width: 16px;
    height: 16px;
    filter: invert(0.4);
}

/* Grid cards */
.fashion-card {
    padding: 0 10px;
}
.fashion-card-title {
    font-family: 'Playfair Display', 'Georgia', serif;
    font-size: 1.15rem;
    font-weight: 600;
    line-height: 1.35;
    color: #1a1a1a;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 2.6em;
    margin-top: 4px !important;
}
.fashion-card-title a {
    color: #1a1a1a;
    transition: color .2s;
}
.fashion-card-title a:hover {
    color: #d4a574;
}
.fashion-card-excerpt {
    font-size: .85rem;
    line-height: 1.6;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Responsive */
@media (max-width: 767.98px) {
    .fashion-circle-lg {
        width: 220px;
        height: 220px;
    }
    .fashion-circle-sm {
        width: 160px;
        height: 160px;
    }
    .fashion-featured-title {
        font-size: 1.4rem;
    }
    .fashion-carousel-arrow {
        top: 110px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var el = document.getElementById('fashionCarousel');
    if (!el) return;
    // Bootstrap 5
    if (typeof bootstrap !== 'undefined' && bootstrap.Carousel) {
        new bootstrap.Carousel(el, { interval: 5000, ride: 'carousel', wrap: true, pause: 'hover' });
    }
    // Bootstrap 4 (jQuery)
    else if (typeof jQuery !== 'undefined') {
        jQuery('#fashionCarousel').carousel({ interval: 5000, ride: 'carousel', wrap: true, pause: 'hover' });
        jQuery('#fashionCarousel').on('slide.bs.carousel', function(e) {
            jQuery('.fashion-carousel-indicators li').removeClass('active');
            jQuery('.fashion-carousel-indicators li').eq(e.to).addClass('active');
        });
    }
});
</script>
