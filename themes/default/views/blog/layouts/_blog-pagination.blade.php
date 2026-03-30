{{-- Blog Pagination --}}
@if(!empty($pagination) && $pagination['total_pages'] > 1)
@php
    $current = $pagination['current_page'];
    $total = $pagination['total_pages'];
    $window = 2;
    $paginationStyle = themeOption('blog.blog_pagination_style', 'minimal');
    $accentColor = themeOption('header.header_link_hover_color', '#ff5e15');

    $pages = [];
    $pages[] = 1;
    if ($total > 1) $pages[] = $total;
    for ($i = max(2, $current - $window); $i <= min($total - 1, $current + $window); $i++) {
        $pages[] = $i;
    }
    $pages = array_unique($pages);
    sort($pages);
@endphp
<div class="row mt-4">
    <div class="col-12">
        <nav aria-label="Navegación de páginas" class="blog-pagination blog-pagination--{{ $paginationStyle }}">
            <div class="bp-wrapper">
                {{-- Previous --}}
                @if($current > 1)
                <a href="?page={{ $current - 1 }}" class="bp-item bp-prev" aria-label="Anterior">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                </a>
                @else
                <span class="bp-item bp-prev bp-disabled" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                </span>
                @endif

                {{-- Page numbers --}}
                @php $prev = 0; @endphp
                @foreach($pages as $p)
                    @if($p - $prev > 1)
                    <span class="bp-item bp-ellipsis">&hellip;</span>
                    @endif

                    @if($p == $current)
                    <span class="bp-item bp-active" aria-current="page">{{ $p }}</span>
                    @else
                    <a href="?page={{ $p }}" class="bp-item">{{ $p }}</a>
                    @endif

                    @php $prev = $p; @endphp
                @endforeach

                {{-- Next --}}
                @if($current < $total)
                <a href="?page={{ $current + 1 }}" class="bp-item bp-next" aria-label="Siguiente">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </a>
                @else
                <span class="bp-item bp-next bp-disabled" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </span>
                @endif
            </div>

            <div class="bp-info">Página {{ $current }} de {{ $total }}</div>
        </nav>
    </div>
</div>

<style>
/* ============================================
   BLOG PAGINATION - Base
   ============================================ */
.blog-pagination {
    --bp-accent: {{ $accentColor }};
    --bp-text: #555;
    --bp-bg: transparent;
    --bp-size: 40px;
    --bp-font: 14px;
    --bp-gap: 6px;
    --bp-radius: 6px;
}
.bp-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--bp-gap);
    flex-wrap: wrap;
}
.bp-item {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: var(--bp-size);
    height: var(--bp-size);
    padding: 0 4px;
    font-size: var(--bp-font);
    font-weight: 500;
    color: var(--bp-text);
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    background: none;
    line-height: 1;
}
.bp-item:hover:not(.bp-active):not(.bp-disabled):not(.bp-ellipsis) {
    color: var(--bp-accent);
}
.bp-disabled {
    opacity: 0.3;
    cursor: default;
    pointer-events: none;
}
.bp-ellipsis {
    cursor: default;
    opacity: 0.5;
    min-width: 24px;
}
.bp-info {
    text-align: center;
    margin-top: 10px;
    font-size: 13px;
    color: #999;
    letter-spacing: 0.3px;
}

/* ============================================
   STYLE: Minimal (default)
   ============================================ */
.blog-pagination--minimal .bp-active {
    color: var(--bp-accent);
    font-weight: 700;
    font-size: 16px;
}
.blog-pagination--minimal .bp-prev,
.blog-pagination--minimal .bp-next {
    color: var(--bp-text);
}
.blog-pagination--minimal .bp-prev:hover,
.blog-pagination--minimal .bp-next:hover {
    color: var(--bp-accent);
}

/* ============================================
   STYLE: Rounded (circular buttons)
   ============================================ */
.blog-pagination--rounded {
    --bp-gap: 8px;
}
.blog-pagination--rounded .bp-item:not(.bp-ellipsis) {
    width: var(--bp-size);
    height: var(--bp-size);
    border-radius: 50%;
    background: #f5f5f5;
    color: var(--bp-text);
}
.blog-pagination--rounded .bp-item:hover:not(.bp-active):not(.bp-disabled):not(.bp-ellipsis) {
    background: #e8e8e8;
    color: var(--bp-accent);
}
.blog-pagination--rounded .bp-active {
    background: var(--bp-accent) !important;
    color: #fff !important;
    font-weight: 600;
}
.blog-pagination--rounded .bp-disabled {
    background: #f9f9f9;
}

/* ============================================
   STYLE: Pill (rounded rectangles)
   ============================================ */
.blog-pagination--pill {
    --bp-gap: 6px;
    --bp-radius: 20px;
}
.blog-pagination--pill .bp-item:not(.bp-ellipsis) {
    border-radius: var(--bp-radius);
    padding: 0 14px;
    background: transparent;
    border: 1.5px solid #e0e0e0;
    color: var(--bp-text);
}
.blog-pagination--pill .bp-item:hover:not(.bp-active):not(.bp-disabled):not(.bp-ellipsis) {
    border-color: var(--bp-accent);
    color: var(--bp-accent);
}
.blog-pagination--pill .bp-active {
    background: var(--bp-accent) !important;
    border-color: var(--bp-accent) !important;
    color: #fff !important;
    font-weight: 600;
}
.blog-pagination--pill .bp-disabled {
    border-color: #eee;
}
.blog-pagination--pill .bp-ellipsis {
    border: none;
}

/* ============================================
   STYLE: Underline
   ============================================ */
.blog-pagination--underline {
    --bp-gap: 0;
}
.blog-pagination--underline .bp-wrapper {
    border-bottom: 1px solid #e5e5e5;
    padding-bottom: 0;
}
.blog-pagination--underline .bp-item:not(.bp-ellipsis) {
    padding: 8px 16px;
    margin-bottom: -1px;
    border-bottom: 2px solid transparent;
    border-radius: 0;
    font-size: 14px;
}
.blog-pagination--underline .bp-item:hover:not(.bp-active):not(.bp-disabled):not(.bp-ellipsis) {
    border-bottom-color: #ddd;
    color: var(--bp-accent);
}
.blog-pagination--underline .bp-active {
    border-bottom-color: var(--bp-accent) !important;
    color: var(--bp-accent) !important;
    font-weight: 700;
}
.blog-pagination--underline .bp-ellipsis {
    border-bottom: none;
    padding: 8px 6px;
}

/* ============================================
   STYLE: Dots (compact indicators)
   ============================================ */
.blog-pagination--dots {
    --bp-gap: 10px;
}
.blog-pagination--dots .bp-item:not(.bp-prev):not(.bp-next):not(.bp-ellipsis) {
    width: 12px;
    height: 12px;
    min-width: 12px;
    border-radius: 50%;
    background: #ddd;
    font-size: 0;
    color: transparent;
    padding: 0;
    transition: all 0.3s ease;
}
.blog-pagination--dots .bp-item:hover:not(.bp-active):not(.bp-disabled):not(.bp-prev):not(.bp-next):not(.bp-ellipsis) {
    background: #bbb;
    transform: scale(1.2);
}
.blog-pagination--dots .bp-active {
    background: var(--bp-accent) !important;
    width: 28px !important;
    border-radius: 6px !important;
}
.blog-pagination--dots .bp-ellipsis {
    width: auto;
    min-width: auto;
    font-size: 14px;
    color: #bbb;
    background: none;
}
.blog-pagination--dots .bp-prev,
.blog-pagination--dots .bp-next {
    width: auto;
    min-width: auto;
    font-size: inherit;
    color: var(--bp-text);
    background: none;
}

/* ============================================
   Mobile responsive
   ============================================ */
@media (max-width: 575px) {
    .blog-pagination {
        --bp-size: 36px;
        --bp-font: 13px;
    }
    .blog-pagination--pill .bp-item:not(.bp-ellipsis) {
        padding: 0 10px;
    }
    .blog-pagination--underline .bp-item:not(.bp-ellipsis) {
        padding: 6px 10px;
    }
}
</style>
@endif
