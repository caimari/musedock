{{-- Blog Layout: Grid (3-column card grid) --}}
@if(!empty($posts) && count($posts) > 0)
    @include('blog.layouts._blog-header-ticker', ['tickerPosition' => 'top'])

    <div class="row mt-4">
    @foreach($posts as $post)
    <div class="col-lg-4 col-md-6 mb-4">
        <article class="card h-100 shadow-sm border-0">
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

    @include('blog.layouts._blog-pagination')
    @include('blog.layouts._blog-header-ticker', ['tickerPosition' => 'bottom'])
@else
    <p class="text-muted text-center">{{ __('blog.no_posts') }}</p>
@endif

@include('blog.layouts._blog-shared-styles')
