@extends('layouts.app')

{{-- SEO --}}
@section('title')
    {{ __('blog.frontend.category') . ' | ' . __('blog.title') . ' | ' . site_setting('site_name', '') }}
@endsection

@section('description')
    {{ site_setting('site_description', '') }}
@endsection

@section('content')

<div class="container py-5">
    {{-- Cabecera --}}
    <div class="mb-4 pb-3 border-bottom">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2" style="background: transparent; padding-left: 0;">
                <li class="breadcrumb-item"><a href="/" style="color: #333;">{{ __('common.home') }}</a></li>
                @if(blog_prefix() !== '')
                <li class="breadcrumb-item"><a href="{{ blog_url() }}" style="color: #333;">{{ __('blog.title') }}</a></li>
                @endif
                <li class="breadcrumb-item active" aria-current="page" style="color: #666;">{{ __('blog.categories') }}</li>
            </ol>
        </nav>
    </div>

    @if(!empty($categories) && count($categories) > 0)
        @php $__usedImages = []; @endphp
        <div class="row">
        @foreach($categories as $category)
        @php
            $__catImg = null;
            if ($category->image) {
                $__catImg = $category->image;
            } else {
                try {
                    $__pdo = $__pdo ?? \Screenart\Musedock\Database::connect();
                    $__tid = tenant_id();
                    $__baseSql = "SELECT bp.featured_image FROM blog_posts bp
                        INNER JOIN blog_post_categories pc ON bp.id = pc.post_id
                        WHERE pc.category_id = ? AND bp.status = 'published'
                        " . ($__tid ? " AND bp.tenant_id = {$__tid}" : " AND bp.tenant_id IS NULL") . "
                        AND bp.featured_image IS NOT NULL AND bp.featured_image != ''";

                    // First try: exclude already used images
                    if (!empty($__usedImages)) {
                        $__placeholders = implode(',', array_fill(0, count($__usedImages), '?'));
                        $__imgStmt = $__pdo->prepare($__baseSql . " AND bp.featured_image NOT IN ({$__placeholders}) ORDER BY bp.published_at DESC LIMIT 1");
                        $__imgStmt->execute(array_merge([$category->id], $__usedImages));
                        $__catImg = $__imgStmt->fetchColumn() ?: null;
                    }

                    // Fallback: allow any image if no unique one found
                    if (!$__catImg) {
                        $__imgStmt = $__pdo->prepare($__baseSql . " ORDER BY bp.published_at DESC LIMIT 1");
                        $__imgStmt->execute([$category->id]);
                        $__catImg = $__imgStmt->fetchColumn() ?: null;
                    }
                } catch (\Exception $e) {}
            }
            if ($__catImg) {
                $__usedImages[] = $__catImg;
            }
            if ($__catImg && !str_starts_with($__catImg, '/') && !str_starts_with($__catImg, 'http')) {
                $__catImg = asset($__catImg);
            }
        @endphp
        <div class="col-lg-4 col-md-6 mb-4">
            <a href="{{ blog_url($category->slug, 'category') }}" class="cat-card-link">
                <div class="cat-card">
                    <div class="cat-card-img" @if($__catImg) style="background-image:url('{{ $__catImg }}')" @endif>
                        @if(!$__catImg)
                        <div class="cat-card-img-placeholder">
                            <i class="fas fa-folder-open"></i>
                        </div>
                        @endif
                        <div class="cat-card-overlay">
                            <span class="cat-card-count">{{ $category->post_count ?? 0 }}</span>
                        </div>
                    </div>
                    <div class="cat-card-body">
                        <h2 class="cat-card-title">{{ $category->name }}</h2>
                        @if($category->description)
                        <p class="cat-card-desc">{{ mb_strlen($category->description) > 100 ? mb_substr($category->description, 0, 100) . '...' : $category->description }}</p>
                        @endif
                        <span class="cat-card-arrow"><i class="fas fa-arrow-right"></i></span>
                    </div>
                </div>
            </a>
        </div>
        @endforeach
        </div>
    @else
        <div class="text-center py-5">
            <i class="fas fa-folder-open" style="font-size: 3rem; color: #ddd;"></i>
            <p class="text-muted mt-3">{{ __('blog.frontend.no_posts') }}</p>
            <a href="{{ blog_url() }}" class="btn btn-outline-secondary mt-2">{{ __('blog.view_all') }}</a>
        </div>
    @endif
</div>

<style>
.cat-card-link { text-decoration: none !important; color: inherit !important; }
.cat-card {
    border-radius: 10px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 2px 12px rgba(0,0,0,0.07);
    transition: transform 0.25s, box-shadow 0.25s;
    height: 100%;
    display: flex;
    flex-direction: column;
}
.cat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 28px rgba(0,0,0,0.12);
}
.cat-card-img {
    height: 180px;
    background-size: cover;
    background-position: center;
    background-color: #f0f2f5;
    position: relative;
}
.cat-card-img-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #ccc;
    font-size: 2.5rem;
}
.cat-card-overlay {
    position: absolute;
    top: 12px;
    right: 12px;
}
.cat-card-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    background: rgba(0,0,0,0.6);
    color: #fff;
    font-size: 0.85rem;
    font-weight: 700;
    border-radius: 50%;
    backdrop-filter: blur(4px);
}
.cat-card-body {
    padding: 18px 20px;
    flex: 1;
    display: flex;
    flex-direction: column;
    position: relative;
}
.cat-card-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #1a1a1a;
    margin: 0 0 6px;
    line-height: 1.3;
}
.cat-card:hover .cat-card-title {
    color: var(--header-link-hover-color, #ff5e15);
}
.cat-card-desc {
    font-size: 0.82rem;
    color: #888;
    line-height: 1.5;
    margin: 0;
    flex: 1;
}
.cat-card-arrow {
    position: absolute;
    bottom: 18px;
    right: 20px;
    color: #ccc;
    font-size: 0.85rem;
    transition: color 0.2s, transform 0.2s;
}
.cat-card:hover .cat-card-arrow {
    color: var(--header-link-hover-color, #ff5e15);
    transform: translateX(3px);
}
@media (max-width: 575px) {
    .cat-card-img { height: 140px; }
}
</style>

@endsection
