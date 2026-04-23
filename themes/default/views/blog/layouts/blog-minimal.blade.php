{{-- Blog Layout: Minimal (text-only, no images) --}}
<div style="max-width: 800px; margin: 0 auto;">
@if(!empty($posts) && count($posts) > 0)
    @include('blog.layouts._blog-header-ticker', ['tickerPosition' => 'top'])

    @foreach($posts as $post)
    {!! render_ad_slot('in-feed', ['index' => $loop->index]) !!}
    <article class="mb-4 pb-4 {{ !$loop->last ? 'border-bottom' : '' }}">
        <div class="text-muted small mb-1">
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
        <h2 class="h5 mb-2" style="line-height: 1.4;">
            <a href="{{ blog_url($post->slug) }}" class="text-decoration-none text-dark" style="font-weight: 600;">{{ $post->title }}</a>
        </h2>
        @include('blog.layouts._taxonomy-chips', ['post' => $post])
        @php
            $__excerpt = $post->excerpt ?: strip_tags($post->content ?? '');
            $__excerpt = trim(preg_replace('/\s+/', ' ', $__excerpt));
            $__excerpt = mb_strlen($__excerpt) > 250 ? mb_substr($__excerpt, 0, 250) . '...' : $__excerpt;
        @endphp
        <p class="text-muted mb-2" style="line-height: 1.6;">{{ $__excerpt }}</p>
        <a href="{{ blog_url($post->slug) }}" class="btn-read-more-minimal">{{ __('blog.read_more') }}</a>
    </article>
    @endforeach

    @include('blog.layouts._blog-pagination')
    @include('blog.layouts._blog-header-ticker', ['tickerPosition' => 'bottom'])
@else
    <p class="text-muted text-center">{{ __('blog.no_posts') }}</p>
@endif
</div>

<style>
.btn-read-more-minimal {
    font-size: .8rem;
    font-weight: 500;
    color: #6366f1;
    text-decoration: none;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: color .2s ease;
}
.btn-read-more-minimal:hover {
    color: #4338ca;
    text-decoration: none;
}
.btn-read-more-minimal:visited {
    color: #6366f1;
    text-decoration: none;
}
</style>
