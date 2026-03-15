{{-- Blog Layout: Newspaper (1 large left + 2 stacked right, rest in list below) --}}
@if(!empty($posts) && count($posts) > 0)
    @php
        $postsArray = is_array($posts) ? $posts : (method_exists($posts, 'toArray') ? $posts->toArray() : (array)$posts);
        $featured = $postsArray[0] ?? null;
        $sidePost1 = $postsArray[1] ?? null;
        $sidePost2 = $postsArray[2] ?? null;
        $rest = array_slice($postsArray, 3);
    @endphp

    @include('blog.layouts._blog-header-ticker', ['tickerPosition' => 'top'])

    {{-- Top section: 1 large + 2 small stacked --}}
    <div class="row mb-4">
        {{-- Featured large post (left) --}}
        @if($featured)
        @php $featured = is_object($featured) ? $featured : (object)$featured; @endphp
        <div class="col-md-8 mb-3 mb-md-0">
            @php
                if ($featured->featured_image && !($featured->hide_featured_image ?? false)) {
                    $featuredImg = (str_starts_with($featured->featured_image, '/') || str_starts_with($featured->featured_image, 'http'))
                        ? $featured->featured_image
                        : asset($featured->featured_image);
                } else {
                    $featuredImg = '/assets/themes/default/img/blog-default.svg';
                }
                $dateVal = $featured->published_at ?? $featured->created_at;
                $dateStr = $dateVal instanceof \DateTime ? $dateVal->format('d/m/Y') : date('d/m/Y', strtotime($dateVal));
                $postAuthorName = null;
                if (!empty($featured->user_id)) {
                    if (($featured->user_type ?? '') === 'superadmin' && !empty($featured->tenant_id)) {
                        $__pdo = \Screenart\Musedock\Database::connect();
                        $__stmt = $__pdo->prepare("SELECT name FROM admins WHERE tenant_id = ? AND is_root_admin = 1 LIMIT 1");
                        $__stmt->execute([$featured->tenant_id]);
                        $__ra = $__stmt->fetch(\PDO::FETCH_OBJ);
                        $postAuthorName = $__ra ? $__ra->name : null;
                    }
                    if (!$postAuthorName) {
                        $__author = match($featured->user_type ?? 'admin') {
                            'superadmin' => \Screenart\Musedock\Models\SuperAdmin::find($featured->user_id),
                            'admin' => \Screenart\Musedock\Models\Admin::find($featured->user_id),
                            'user' => \Screenart\Musedock\Models\User::find($featured->user_id),
                            default => null,
                        };
                        $postAuthorName = $__author ? $__author->name : null;
                    }
                }
                $postAuthorUrl = null;
                if ($postAuthorName && (($featured->user_type ?? '') === 'admin')) {
                    $__pdo = $__pdo ?? \Screenart\Musedock\Database::connect();
                    $__aStmt = $__pdo->prepare("SELECT author_slug, author_page_enabled FROM admins WHERE id = ? LIMIT 1");
                    $__aStmt->execute([$featured->user_id]);
                    $__aData = $__aStmt->fetch(\PDO::FETCH_OBJ);
                    if ($__aData && $__aData->author_page_enabled && $__aData->author_slug) {
                        $postAuthorUrl = blog_url($__aData->author_slug, 'author');
                    }
                }
                $__excerpt = $featured->excerpt ?: strip_tags($featured->content ?? '');
                $__excerpt = trim(preg_replace('/\s+/', ' ', $__excerpt));
                $__excerpt = mb_strlen($__excerpt) > 250 ? mb_substr($__excerpt, 0, 250) . '...' : $__excerpt;
            @endphp
            <article class="newspaper-featured">
                <a href="{{ blog_url($featured->slug) }}" class="d-block overflow-hidden rounded" style="height: 400px; background-color: #f0f2f5;">
                    <img src="{{ $featuredImg }}" alt="{{ $featured->title }}" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;">
                </a>
                <div style="padding-top: 10px;">
                    <div class="post-meta mb-1 text-muted small">
                        <span><i class="far fa-calendar"></i> {{ $dateStr }}</span>
                        @if($postAuthorName)
                        <span class="ms-2"><i class="far fa-user"></i> @if($postAuthorUrl)<a href="{{ $postAuthorUrl }}" class="text-muted">{{ $postAuthorName }}</a>@else{{ $postAuthorName }}@endif</span>
                        @endif
                    </div>
                    <h2 class="mb-1" style="line-height: 1.25; margin-top: 0; font-size: 1.5rem;">
                        <a href="{{ blog_url($featured->slug) }}" class="text-decoration-none text-dark">{{ $featured->title }}</a>
                    </h2>
                    <p class="text-muted mb-0" style="font-size: 0.9rem; line-height: 1.5;">{{ $__excerpt }}</p>
                </div>
            </article>
        </div>
        @endif

        {{-- Two stacked posts (right) --}}
        <div class="col-md-4 d-flex flex-column">
            @foreach([$sidePost1, $sidePost2] as $sidePost)
                @if($sidePost)
                @php
                    $sidePost = is_object($sidePost) ? $sidePost : (object)$sidePost;
                    if ($sidePost->featured_image && !($sidePost->hide_featured_image ?? false)) {
                        $sideImg = (str_starts_with($sidePost->featured_image, '/') || str_starts_with($sidePost->featured_image, 'http'))
                            ? $sidePost->featured_image
                            : asset($sidePost->featured_image);
                    } else {
                        $sideImg = '/assets/themes/default/img/blog-default.svg';
                    }
                    $sideImg = media_thumb_url($sideImg, 'medium');
                    $dateVal = $sidePost->published_at ?? $sidePost->created_at;
                    $sideDateStr = $dateVal instanceof \DateTime ? $dateVal->format('d/m/Y') : date('d/m/Y', strtotime($dateVal));
                    $sideAuthorName = null;
                    if (!empty($sidePost->user_id)) {
                        if (($sidePost->user_type ?? '') === 'superadmin' && !empty($sidePost->tenant_id)) {
                            $__pdo = \Screenart\Musedock\Database::connect();
                            $__stmt = $__pdo->prepare("SELECT name FROM admins WHERE tenant_id = ? AND is_root_admin = 1 LIMIT 1");
                            $__stmt->execute([$sidePost->tenant_id]);
                            $__ra = $__stmt->fetch(\PDO::FETCH_OBJ);
                            $sideAuthorName = $__ra ? $__ra->name : null;
                        }
                        if (!$sideAuthorName) {
                            $__author = match($sidePost->user_type ?? 'admin') {
                                'superadmin' => \Screenart\Musedock\Models\SuperAdmin::find($sidePost->user_id),
                                'admin' => \Screenart\Musedock\Models\Admin::find($sidePost->user_id),
                                'user' => \Screenart\Musedock\Models\User::find($sidePost->user_id),
                                default => null,
                            };
                            $sideAuthorName = $__author ? $__author->name : null;
                        }
                    }
                    $sideAuthorUrl = null;
                    if ($sideAuthorName && (($sidePost->user_type ?? '') === 'admin')) {
                        $__pdo = $__pdo ?? \Screenart\Musedock\Database::connect();
                        $__aStmt = $__pdo->prepare("SELECT author_slug, author_page_enabled FROM admins WHERE id = ? LIMIT 1");
                        $__aStmt->execute([$sidePost->user_id]);
                        $__aData = $__aStmt->fetch(\PDO::FETCH_OBJ);
                        if ($__aData && $__aData->author_page_enabled && $__aData->author_slug) {
                            $sideAuthorUrl = blog_url($__aData->author_slug, 'author');
                        }
                    }
                @endphp
                <article class="newspaper-side mb-3 flex-fill">
                    <a href="{{ blog_url($sidePost->slug) }}" class="d-block overflow-hidden rounded" style="height: 190px; background-color: #f0f2f5;">
                        <img src="{{ $sideImg }}" alt="{{ $sidePost->title }}" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;">
                    </a>
                    <div style="padding-top: 6px;">
                        <div class="post-meta mb-1 text-muted small">
                            <span><i class="far fa-calendar"></i> {{ $sideDateStr }}</span>
                            @if($sideAuthorName)
                            <span class="ms-2"><i class="far fa-user"></i> @if($sideAuthorUrl)<a href="{{ $sideAuthorUrl }}" class="text-muted">{{ $sideAuthorName }}</a>@else{{ $sideAuthorName }}@endif</span>
                            @endif
                        </div>
                        <h3 class="mb-0" style="line-height: 1.25; margin-top: 0; font-size: 1rem;">
                            <a href="{{ blog_url($sidePost->slug) }}" class="text-decoration-none text-dark">{{ $sidePost->title }}</a>
                        </h3>
                    </div>
                </article>
                @endif
            @endforeach
        </div>
    </div>

    {{-- Rest of posts in horizontal list --}}
    @if(count($rest) > 0)
    <hr style="border-top: 1px solid #ddd; margin: 1.5rem 0;">
    @foreach($rest as $post)
    @php $post = is_object($post) ? $post : (object)$post; @endphp
    <article class="row mb-4 pb-4 {{ !$loop->last ? 'border-bottom' : '' }}">
        <div class="col-md-4 mb-3 mb-md-0">
            <a href="{{ blog_url($post->slug) }}" class="d-block overflow-hidden rounded" style="height: 200px; background-color: #f0f2f5;">
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
                <img src="{{ $imageUrl }}" alt="{{ $post->title }}" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;">
            </a>
        </div>
        <div class="col-md-8 d-flex flex-column">
            <div class="post-meta mb-1 text-muted small">
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

            <h2 class="h5 mb-1" style="line-height: 1.3; margin-top: 0;">
                <a href="{{ blog_url($post->slug) }}" class="text-decoration-none text-dark">{{ $post->title }}</a>
            </h2>

            @include('blog.layouts._taxonomy-chips', ['post' => $post])
            @php
                $__excerpt = $post->excerpt ?: strip_tags($post->content ?? '');
                $__excerpt = trim(preg_replace('/\s+/', ' ', $__excerpt));
                $__excerpt = mb_strlen($__excerpt) > 280 ? mb_substr($__excerpt, 0, 280) . '...' : $__excerpt;
            @endphp
            <p class="text-muted mb-0 flex-grow-1">{{ $__excerpt }}</p>

            <div class="mt-2">
                <a href="{{ blog_url($post->slug) }}" class="btn-read-more">{{ __('blog.read_more') }}</a>
            </div>
        </div>
    </article>
    @endforeach
    @endif

    @include('blog.layouts._blog-pagination')
    @include('blog.layouts._blog-header-ticker', ['tickerPosition' => 'bottom'])
@else
    <p class="text-muted text-center">{{ __('blog.no_posts') }}</p>
@endif

@include('blog.layouts._blog-shared-styles')
<style>
.newspaper-featured a:hover, .newspaper-side a:hover { text-decoration: none; }
@media (max-width: 767.98px) {
    .newspaper-side { margin-bottom: 1.5rem !important; }
}
</style>
