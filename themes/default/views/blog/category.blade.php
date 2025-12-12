@extends('layouts.app')

{{-- SEO --}}
@section('title')
    {{ $category->name . ' | ' . __('blog.title') . ' | ' . setting('site_name', 'MuseDock CMS') }}
@endsection

@section('description')
    {{ $category->description ?? setting('site_description', '') }}
@endsection

@section('content')

<div class="container py-5">
    {{-- Cabecera de categoría --}}
    <div class="category-header mb-4 pb-3 border-bottom">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2" style="background: transparent; padding-left: 0;">
                <li class="breadcrumb-item"><a href="/" style="color: #333;">{{ __('common.home') }}</a></li>
                <li class="breadcrumb-item"><a href="/blog" style="color: #333;">{{ __('blog.title') }}</a></li>
                <li class="breadcrumb-item active" aria-current="page" style="color: #666;">{{ $category->name }}</li>
            </ol>
        </nav>
        <h1 class="mb-2" style="font-size: 1.75rem; margin-top: 0;">{{ $category->name }}</h1>
        @if($category->description)
        <p class="text-muted mb-0">{{ $category->description }}</p>
        @endif
    </div>

    @if(!empty($posts) && count($posts) > 0)
        <div class="row">
        @foreach($posts as $post)
        <div class="col-lg-4 col-md-6 mb-4">
            <article class="card h-100 shadow-sm border-0">
                {{-- Imagen destacada --}}
                <a href="/blog/{{ $post->slug }}">
                    @php
                        if ($post->featured_image && !($post->hide_featured_image ?? false)) {
                            $imageUrl = (str_starts_with($post->featured_image, '/media/') || str_starts_with($post->featured_image, 'http'))
                                ? $post->featured_image
                                : asset($post->featured_image);
                        } else {
                            $imageUrl = '/assets/themes/default/img/blog-default.svg';
                        }
                    @endphp
                    <img src="{{ $imageUrl }}" alt="{{ $post->title }}" class="card-img-top" style="height: 250px; object-fit: cover;">
                </a>

                <div class="card-body d-flex flex-column">
                    {{-- Título --}}
                    <h2 class="card-title h5 mb-2">
                        <a href="/blog/{{ $post->slug }}" class="text-decoration-none text-dark">{{ $post->title }}</a>
                    </h2>

                    {{-- Meta información --}}
                    <div class="post-meta mb-3 text-muted small">
                        @php
                            $dateVal = $post->published_at ?? $post->created_at;
                            $dateStr = $dateVal instanceof \DateTime ? $dateVal->format('d/m/Y') : date('d/m/Y', strtotime($dateVal));
                        @endphp
                        <span><i class="far fa-calendar"></i> {{ $dateStr }}</span>
                    </div>

                    {{-- Excerpt --}}
                    @if($post->excerpt)
                    <p class="card-text text-muted mb-3 flex-grow-1">{{ mb_strlen($post->excerpt) > 120 ? mb_substr($post->excerpt, 0, 120) . '...' : $post->excerpt }}</p>
                    @endif

                    {{-- Leer más --}}
                    <div class="mt-auto">
                        <a href="/blog/{{ $post->slug }}" class="btn btn-primary px-4 py-2" style="font-size: 0.875rem;">{{ __('blog.read_more') }}</a>
                    </div>
                </div>
            </article>
        </div>
        @endforeach
        </div>

        {{-- Paginación --}}
        @if(!empty($pagination) && $pagination['total_pages'] > 1)
        <div class="row mt-4">
            <div class="col-12">
                <nav aria-label="Navegación de páginas">
                    <ul class="pagination justify-content-center">
                        @if($pagination['current_page'] > 1)
                        <li class="page-item">
                            <a class="page-link" href="?page={{ $pagination['current_page'] - 1 }}" aria-label="Anterior">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        @else
                        <li class="page-item disabled">
                            <span class="page-link">&laquo;</span>
                        </li>
                        @endif

                        @for($i = 1; $i <= $pagination['total_pages']; $i++)
                            @if($i == $pagination['current_page'])
                            <li class="page-item active" aria-current="page">
                                <span class="page-link">{{ $i }}</span>
                            </li>
                            @else
                            <li class="page-item">
                                <a class="page-link" href="?page={{ $i }}">{{ $i }}</a>
                            </li>
                            @endif
                        @endfor

                        @if($pagination['current_page'] < $pagination['total_pages'])
                        <li class="page-item">
                            <a class="page-link" href="?page={{ $pagination['current_page'] + 1 }}" aria-label="Siguiente">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                        @else
                        <li class="page-item disabled">
                            <span class="page-link">&raquo;</span>
                        </li>
                        @endif
                    </ul>
                </nav>
            </div>
        </div>
        @endif
    @else
        <div class="text-center py-5">
            <i class="bi bi-inbox" style="font-size: 4rem; color: #ccc;"></i>
            @php
                $noPostsCatText = __('blog.no_posts_category');
                if ($noPostsCatText === 'blog.no_posts_category') {
                    $noPostsCatText = detectLanguage() === 'es' ? 'No hay posts en esta categoria.' : 'No posts in this category.';
                }
            @endphp
            <p class="text-muted mt-3">{{ $noPostsCatText }}</p>
            <a href="/blog" class="btn btn-outline-primary mt-2">{{ __('blog.view_all') }}</a>
        </div>
    @endif
</div>

@endsection
