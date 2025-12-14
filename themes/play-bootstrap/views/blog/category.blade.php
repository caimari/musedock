@extends('layouts.app')

{{-- SEO --}}
@section('title')
    {{ $category->name . ' | ' . __('blog.title') . ' | ' . site_setting('site_name', '') }}
@endsection

@section('description')
    {{ $category->description ?? __('blog.frontend.category_desc') . ' ' . $category->name }}
@endsection

@section('content')

<!-- ====== Banner Start ====== -->
<section class="ud-page-banner" style="background-image: linear-gradient(rgba(48, 86, 211, 0.55), rgba(48, 86, 211, 0.55)), url('{{ asset('img/background.jpg') }}'); background-size: cover; background-position: center; background-repeat: no-repeat;">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="ud-banner-content">
                    <h1>{{ __('blog.frontend.category') }}: {{ $category->name }}</h1>
                    @if($category->description)
                    <p class="mt-3">{{ $category->description }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
<!-- ====== Banner End ====== -->

<!-- ====== Blog Start ====== -->
<section class="ud-blog-grids py-5">
    <div class="container">
        @if(!empty($posts) && count($posts) > 0)
        <div class="row">
            @foreach($posts as $post)
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="ud-single-blog">
                    <div class="ud-blog-image">
                        <a href="/blog/{{ $post->slug }}">
                            @php
                                if ($post->featured_image && !$post->hide_featured_image) {
                                    $imageUrl = (str_starts_with($post->featured_image, '/media/') || str_starts_with($post->featured_image, 'http'))
                                        ? $post->featured_image
                                        : asset($post->featured_image);
                                } else {
                                    $imageUrl = asset('img/no-image.png');
                                }
                            @endphp
                            <img src="{{ $imageUrl }}" alt="{{ $post->title }}" />
                        </a>
                    </div>
                    <div class="ud-blog-content">
                        @php
                            $dateVal = $post->published_at ?? $post->created_at;
                            $dateStr = $dateVal instanceof \DateTime ? $dateVal->format('M d, Y') : date('M d, Y', strtotime($dateVal));
                        @endphp
                        <span class="ud-blog-date">{{ $dateStr }}</span>
                        <h3 class="ud-blog-title">
                            <a href="/blog/{{ $post->slug }}">
                                {{ $post->title }}
                            </a>
                        </h3>
                        @if($post->excerpt)
                        <p class="ud-blog-desc">
                            {{ mb_strlen($post->excerpt) > 120 ? mb_substr($post->excerpt, 0, 120) . '...' : $post->excerpt }}
                        </p>
                        @endif
                    </div>
                </div>
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
        <div class="row">
            <div class="col-12">
                <p class="text-muted text-center">{{ __('blog.frontend.no_posts_in_category') }}</p>
            </div>
        </div>
        @endif
    </div>
</section>
<!-- ====== Blog End ====== -->

@endsection
