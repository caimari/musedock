{{-- Blog Sidebar Extras: Search, Related Posts, Popular Tags, Popular Categories (orderable) --}}
@php
    $showSidebarSearch = themeOption('blog.blog_sidebar_search', false);
    $showRelatedPosts = themeOption('blog.blog_sidebar_related_posts', true);
    $relatedPostsCount = (int) themeOption('blog.blog_sidebar_related_posts_count', 4);
    $showSidebarTags = themeOption('blog.blog_sidebar_tags', true);
    $showSidebarCategories = themeOption('blog.blog_sidebar_categories', true);

    // Orden configurable
    $__sidebarBlocks = [];
    if ($showSidebarSearch) $__sidebarBlocks[] = ['type' => 'search', 'order' => (int) themeOption('blog.blog_sidebar_search_order', 1)];
    if ($showRelatedPosts) $__sidebarBlocks[] = ['type' => 'related', 'order' => (int) themeOption('blog.blog_sidebar_related_posts_order', 2)];
    if ($showSidebarTags) $__sidebarBlocks[] = ['type' => 'tags', 'order' => (int) themeOption('blog.blog_sidebar_tags_order', 3)];
    if ($showSidebarCategories) $__sidebarBlocks[] = ['type' => 'categories', 'order' => (int) themeOption('blog.blog_sidebar_categories_order', 4)];
    usort($__sidebarBlocks, fn($a, $b) => $a['order'] - $b['order']);
@endphp

@if(!empty($__sidebarBlocks))
@php
    $sidebarRelatedPosts = [];
    $sidebarPopularTags = [];
    $sidebarPopularCategories = [];

    try {
        $pdo = \Screenart\Musedock\Database::connect();
        $tenantId = tenant_id();
        $currentPostId = $post->id ?? 0;

        // --- Related Posts ---
        if ($showRelatedPosts && $currentPostId) {
            $limit = max(1, min(6, $relatedPostsCount));

            $catIds = [];
            $tagIds = [];
            if (!empty($post->categories)) {
                foreach ($post->categories as $c) {
                    $catIds[] = $c->id;
                }
            }
            if (!empty($post->tags)) {
                foreach ($post->tags as $t) {
                    $tagIds[] = $t->id;
                }
            }

            $relatedIds = [];

            // First: posts sharing categories or tags
            if (!empty($catIds) || !empty($tagIds)) {
                $unionParts = [];
                $unionParams = [];

                if (!empty($catIds)) {
                    $catPlaceholders = implode(',', array_fill(0, count($catIds), '?'));
                    $unionParts[] = "SELECT DISTINCT pc.post_id FROM blog_post_categories pc WHERE pc.category_id IN ({$catPlaceholders})";
                    $unionParams = array_merge($unionParams, $catIds);
                }
                if (!empty($tagIds)) {
                    $tagPlaceholders = implode(',', array_fill(0, count($tagIds), '?'));
                    $unionParts[] = "SELECT DISTINCT pt.post_id FROM blog_post_tags pt WHERE pt.tag_id IN ({$tagPlaceholders})";
                    $unionParams = array_merge($unionParams, $tagIds);
                }

                $unionSql = implode(' UNION ', $unionParts);

                if ($tenantId) {
                    $sql = "SELECT bp.id, bp.title, bp.slug, bp.featured_image, bp.hide_featured_image, bp.published_at, bp.created_at
                            FROM blog_posts bp
                            WHERE bp.id IN ({$unionSql})
                              AND bp.id != ?
                              AND bp.tenant_id = ?
                              AND bp.status = 'published'
                            ORDER BY RANDOM()
                            LIMIT {$limit}";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(array_merge($unionParams, [$currentPostId, $tenantId]));
                } else {
                    $sql = "SELECT bp.id, bp.title, bp.slug, bp.featured_image, bp.hide_featured_image, bp.published_at, bp.created_at
                            FROM blog_posts bp
                            WHERE bp.id IN ({$unionSql})
                              AND bp.id != ?
                              AND bp.tenant_id IS NULL
                              AND bp.status = 'published'
                            ORDER BY RANDOM()
                            LIMIT {$limit}";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(array_merge($unionParams, [$currentPostId]));
                }
                $sidebarRelatedPosts = $stmt->fetchAll(\PDO::FETCH_OBJ);
                foreach ($sidebarRelatedPosts as $rp) {
                    $relatedIds[] = $rp->id;
                }
            }

            // Fill remaining slots with recent posts
            $remaining = $limit - count($sidebarRelatedPosts);
            if ($remaining > 0) {
                $excludeIds = array_merge([$currentPostId], $relatedIds);
                $excludePlaceholders = implode(',', array_fill(0, count($excludeIds), '?'));
                if ($tenantId) {
                    $stmt = $pdo->prepare("
                        SELECT id, title, slug, featured_image, hide_featured_image, published_at, created_at
                        FROM blog_posts
                        WHERE id NOT IN ({$excludePlaceholders})
                          AND tenant_id = ?
                          AND status = 'published'
                        ORDER BY RANDOM()
                        LIMIT {$remaining}
                    ");
                    $stmt->execute(array_merge($excludeIds, [$tenantId]));
                } else {
                    $stmt = $pdo->prepare("
                        SELECT id, title, slug, featured_image, hide_featured_image, published_at, created_at
                        FROM blog_posts
                        WHERE id NOT IN ({$excludePlaceholders})
                          AND tenant_id IS NULL
                          AND status = 'published'
                        ORDER BY RANDOM()
                        LIMIT {$remaining}
                    ");
                    $stmt->execute($excludeIds);
                }
                $fillPosts = $stmt->fetchAll(\PDO::FETCH_OBJ);
                $sidebarRelatedPosts = array_merge($sidebarRelatedPosts, $fillPosts);
            }
        }

        // --- Popular Tags ---
        if ($showSidebarTags) {
            if ($tenantId) {
                $stmt = $pdo->prepare("
                    SELECT t.id, t.name, t.slug, t.color, COUNT(pt.post_id) as post_count
                    FROM blog_tags t
                    INNER JOIN blog_post_tags pt ON t.id = pt.tag_id
                    INNER JOIN blog_posts bp ON bp.id = pt.post_id
                    WHERE bp.tenant_id = ? AND bp.status = 'published'
                    GROUP BY t.id, t.name, t.slug, t.color
                    ORDER BY post_count DESC
                    LIMIT 10
                ");
                $stmt->execute([$tenantId]);
            } else {
                $stmt = $pdo->query("
                    SELECT t.id, t.name, t.slug, t.color, COUNT(pt.post_id) as post_count
                    FROM blog_tags t
                    INNER JOIN blog_post_tags pt ON t.id = pt.tag_id
                    INNER JOIN blog_posts bp ON bp.id = pt.post_id
                    WHERE bp.tenant_id IS NULL AND bp.status = 'published'
                    GROUP BY t.id, t.name, t.slug, t.color
                    ORDER BY post_count DESC
                    LIMIT 10
                ");
            }
            $sidebarPopularTags = $stmt->fetchAll(\PDO::FETCH_OBJ);
        }

        // --- Popular Categories ---
        if ($showSidebarCategories) {
            if ($tenantId) {
                $stmt = $pdo->prepare("
                    SELECT c.id, c.name, c.slug, COUNT(pc.post_id) as post_count
                    FROM blog_categories c
                    INNER JOIN blog_post_categories pc ON c.id = pc.category_id
                    INNER JOIN blog_posts bp ON bp.id = pc.post_id
                    WHERE bp.tenant_id = ? AND bp.status = 'published'
                    GROUP BY c.id, c.name, c.slug
                    ORDER BY post_count DESC
                    LIMIT 10
                ");
                $stmt->execute([$tenantId]);
            } else {
                $stmt = $pdo->query("
                    SELECT c.id, c.name, c.slug, COUNT(pc.post_id) as post_count
                    FROM blog_categories c
                    INNER JOIN blog_post_categories pc ON c.id = pc.category_id
                    INNER JOIN blog_posts bp ON bp.id = pc.post_id
                    WHERE bp.tenant_id IS NULL AND bp.status = 'published'
                    GROUP BY c.id, c.name, c.slug
                    ORDER BY post_count DESC
                    LIMIT 10
                ");
            }
            $sidebarPopularCategories = $stmt->fetchAll(\PDO::FETCH_OBJ);
        }

    } catch (\Exception $e) {
        // Silent fallback
    }
@endphp

{{-- Render blocks in configured order --}}
@foreach($__sidebarBlocks as $__block)

    @if($__block['type'] === 'search')
    <div class="sidebar-extras-section mb-4">
        <h5 class="sidebar-extras-title">{{ __('search.search') }}</h5>
        <form action="{{ url('/search') }}" method="GET">
            <div style="display:flex; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;">
                <input type="text" name="q" placeholder="{{ __('search.search') }}..." required minlength="2" style="flex:1; border:none; padding: 10px 14px; font-size: 14px; outline:none;">
                <button type="submit" style="border:none; background: var(--header-link-hover-color, #ff5e15); color: #fff; padding: 10px 16px; cursor:pointer;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                </button>
            </div>
        </form>
    </div>
    @endif

    @if($__block['type'] === 'related' && !empty($sidebarRelatedPosts))
    <div class="sidebar-extras-section mb-4">
        <h5 class="sidebar-extras-title">{{ __('blog.related_posts') }}</h5>
        @foreach($sidebarRelatedPosts as $relatedPost)
        <div class="sidebar-related-card mb-3">
            <a href="{{ blog_url($relatedPost->slug) }}" class="d-flex text-decoration-none">
                @php
                    if ($relatedPost->featured_image && !($relatedPost->hide_featured_image ?? false)) {
                        $relImgOrig = (str_starts_with($relatedPost->featured_image, '/') || str_starts_with($relatedPost->featured_image, 'http'))
                            ? $relatedPost->featured_image
                            : asset($relatedPost->featured_image);
                        $relImg = media_thumb_url($relImgOrig);
                    } else {
                        $relImg = '/assets/themes/default/img/blog-default.svg';
                    }
                    $relDate = $relatedPost->published_at ?? $relatedPost->created_at;
                    $relDateStr = $relDate instanceof \DateTime ? $relDate->format('d/m/Y') : date('d/m/Y', strtotime($relDate));
                @endphp
                <div class="sidebar-related-img flex-shrink-0" style="width: 80px; height: 80px; overflow: hidden; border-radius: 6px; background: #f0f2f5; display: flex; align-items: center; justify-content: center;">
                    <img src="{{ $relImg }}" alt="{{ $relatedPost->title }}" loading="lazy" style="width: 100%; height: 100%; object-fit: contain;">
                </div>
                <div class="ms-3 d-flex flex-column justify-content-center">
                    <h6 class="mb-1 sidebar-related-title">{{ $relatedPost->title }}</h6>
                    <small class="text-muted" style="font-size: .75rem;"><i class="far fa-calendar"></i> {{ $relDateStr }}</small>
                </div>
            </a>
        </div>
        @endforeach
    </div>
    @endif

    @if($__block['type'] === 'tags' && !empty($sidebarPopularTags))
    <div class="sidebar-extras-section mb-4">
        <h5 class="sidebar-extras-title">{{ __('blog.popular_tags') }}</h5>
        <div class="d-flex flex-wrap gap-2">
            @foreach($sidebarPopularTags as $sTag)
            @php
                $__sc = !empty($sTag->color) ? trim($sTag->color) : null;
                if ($__sc) {
                    $__sh = ltrim($__sc, '#');
                    if (strlen($__sh) === 3) { $__sh = $__sh[0].$__sh[0].$__sh[1].$__sh[1].$__sh[2].$__sh[2]; }
                    $__sr = hexdec(substr($__sh,0,2)); $__sg = hexdec(substr($__sh,2,2)); $__sb = hexdec(substr($__sh,4,2));
                    $__ss = "background:rgba({$__sr},{$__sg},{$__sb},0.10);color:{$__sc};border-color:rgba({$__sr},{$__sg},{$__sb},0.32);";
                } else {
                    $__ss = 'background:#eaf0fb;color:#1a4fa0;border-color:rgba(154,184,232,0.8);';
                }
            @endphp
            <a href="{{ blog_url($sTag->slug, 'tag') }}" class="tx-chip tx-chip-tag" style="{{ $__ss }}">{{ $sTag->name }} <span style="font-size:9px;opacity:0.65;margin-left:3px;">{{ $sTag->post_count }}</span></a>
            @endforeach
        </div>
    </div>
    @endif

    @if($__block['type'] === 'categories' && !empty($sidebarPopularCategories))
    <div class="sidebar-extras-section mb-4">
        <h5 class="sidebar-extras-title">{{ __('blog.popular_categories') }}</h5>
        <ul class="sidebar-categories-list list-unstyled mb-0">
            @foreach($sidebarPopularCategories as $sCat)
            <li class="sidebar-category-item">
                <a href="{{ blog_url($sCat->slug, 'category') }}" class="d-flex justify-content-between align-items-center">
                    <span>{{ $sCat->name }}</span>
                    <span class="sidebar-cat-count">{{ $sCat->post_count }}</span>
                </a>
            </li>
            @endforeach
        </ul>
    </div>
    @endif

@endforeach

<style>
.sidebar-extras-section {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.sidebar-extras-title {
    font-size: 1rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f0f0;
}
.sidebar-related-title {
    font-size: .85rem;
    line-height: 1.3;
    font-weight: 600;
    color: #333;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.sidebar-related-card a:hover .sidebar-related-title {
    color: #e74c3c !important;
}
.sidebar-tags-cloud {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.sidebar-tag-pill {
    display: inline-block;
    padding: 4px 12px;
    font-size: .8rem;
    font-weight: 500;
    color: #4a5568;
    background: #f7fafc;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    text-decoration: none;
    transition: all .2s;
}
.sidebar-tag-pill:hover {
    color: #fff;
    background: #e74c3c;
    border-color: #e74c3c;
    text-decoration: none;
}
.sidebar-tag-pill:visited {
    color: #4a5568;
}
.sidebar-tag-pill:hover:visited {
    color: #fff;
}
.sidebar-tag-count {
    font-size: .7rem;
    color: #a0aec0;
    margin-left: 4px;
}
.sidebar-tag-pill:hover .sidebar-tag-count {
    color: rgba(255,255,255,0.8);
}
.sidebar-categories-list .sidebar-category-item {
    border-bottom: 1px solid #f0f0f0;
}
.sidebar-categories-list .sidebar-category-item:last-child {
    border-bottom: none;
}
.sidebar-categories-list .sidebar-category-item a {
    display: flex;
    padding: 10px 0;
    color: #4a5568;
    text-decoration: none;
    font-size: .9rem;
    transition: color .2s;
}
.sidebar-categories-list .sidebar-category-item a:hover {
    color: #e74c3c;
}
.sidebar-categories-list .sidebar-category-item a:visited {
    color: #4a5568;
}
.sidebar-categories-list .sidebar-category-item a:hover:visited {
    color: #e74c3c;
}
.sidebar-cat-count {
    background: #f0f2f5;
    color: #666;
    font-size: .75rem;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 12px;
    min-width: 28px;
    text-align: center;
}
</style>
@endif
