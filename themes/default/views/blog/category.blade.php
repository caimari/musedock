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
    <div class="row">
        {{-- Contenido principal --}}
        <div class="col-lg-8">
            <h1 class="mb-2">{{ $category->name }}</h1>
            @if($category->description)
            <p class="text-muted mb-4">{{ $category->description }}</p>
            @endif

            @if(!empty($posts) && count($posts) > 0)
                @foreach($posts as $post)
                <article class="blog-post-card mb-4 pb-4 border-bottom">
                    {{-- Imagen destacada --}}
                    @if($post->featured_image)
                    <div class="post-thumbnail mb-3">
                        <a href="/blog/{{ $post->slug }}">
                            <img src="{{ asset($post->featured_image) }}" alt="{{ $post->title }}" class="img-fluid rounded">
                        </a>
                    </div>
                    @endif

                    {{-- Título --}}
                    <h2 class="post-title h4">
                        <a href="/blog/{{ $post->slug }}" class="text-decoration-none text-dark">{{ $post->title }}</a>
                    </h2>

                    {{-- Meta información --}}
                    <div class="post-meta mb-3 text-muted small">
                        <span><i class="far fa-calendar"></i> {{ date('d/m/Y', strtotime($post->published_at)) }}</span>
                    </div>

                    {{-- Excerpt --}}
                    @if($post->excerpt)
                    <div class="post-excerpt mb-3">
                        <p>{{ $post->excerpt }}</p>
                    </div>
                    @endif

                    {{-- Leer más --}}
                    <a href="/blog/{{ $post->slug }}" class="btn btn-sm btn-primary">{{ __('blog.read_more') }}</a>
                </article>
                @endforeach
            @else
                <p class="text-muted">{{ __('blog.no_posts_category') }}</p>
            @endif

            <div class="mt-4">
                <a href="/blog" class="btn btn-outline-secondary">← {{ __('blog.back_to_all') }}</a>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4">
            @if(!empty($categories) && count($categories) > 0)
            <div class="widget widget-categories mb-4">
                <h4 class="widget-title">{{ __('blog.categories') }}</h4>
                <ul class="list-unstyled">
                    @foreach($categories as $cat)
                    <li class="{{ $cat->id == $category->id ? 'fw-bold' : '' }}">
                        <a href="/blog/category/{{ $cat->slug }}">{{ $cat->name }}</a>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif

            <div class="widget widget-all-posts mb-4">
                <a href="/blog" class="btn btn-sm btn-outline-primary">{{ __('blog.view_all') }}</a>
            </div>
        </div>
    </div>
</div>

@endsection
