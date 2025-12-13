@extends('layouts.app')

{{-- SEO --}}
@section('title')
    {{ $post->title . ' | ' . site_setting('site_name', '') }}
@endsection

@section('description')
    {{ $post->excerpt ?? mb_substr(strip_tags($post->content), 0, 160) }}
@endsection

@section('content')

<div class="container py-5">
    <div class="row">
        {{-- Contenido principal --}}
        <div class="col-lg-8">
            <article class="blog-post">
                {{-- Imagen destacada --}}
                @if($post->featured_image && !$post->hide_featured_image)
                    @php
                        $imageUrl = (str_starts_with($post->featured_image, '/media/') || str_starts_with($post->featured_image, 'http'))
                            ? $post->featured_image
                            : asset($post->featured_image);
                    @endphp
                    <img src="{{ $imageUrl }}" alt="{{ $post->title }}" class="img-fluid rounded mb-4" style="width: 100%; max-height: 500px; object-fit: cover;">
                @endif

                {{-- Título --}}
                <h1 class="mb-3">{{ $post->title }}</h1>

                {{-- Meta información --}}
                <div class="post-meta mb-4 text-muted">
                    @php
                        $dateVal = $post->published_at ?? $post->created_at;
                        $dateStr = $dateVal instanceof \DateTime ? $dateVal->format('d/m/Y') : date('d/m/Y', strtotime($dateVal));
                    @endphp
                    <span><i class="far fa-calendar"></i> {{ $dateStr }}</span>
                    @if($post->view_count > 0)
                        <span class="ms-3"><i class="far fa-eye"></i> {{ $post->view_count }} {{ __('blog.views') }}</span>
                    @endif
                </div>

                {{-- Contenido --}}
                <div class="post-content">
                    {!! $post->content !!}
                </div>

                {{-- Categorías y etiquetas --}}
                @if(!empty($post->categories) || !empty($post->tags))
                <div class="post-taxonomies mt-5 pt-4 border-top">
                    @if(!empty($post->categories))
                    <div class="mb-3">
                        <strong>{{ __('blog.categories') }}:</strong>
                        @foreach($post->categories as $category)
                            <a href="/blog/categoria/{{ $category->slug }}" class="badge bg-primary text-decoration-none">{{ $category->name }}</a>
                        @endforeach
                    </div>
                    @endif

                    @if(!empty($post->tags))
                    <div>
                        <strong>{{ __('blog.tags') }}:</strong>
                        @foreach($post->tags as $tag)
                            <a href="/blog/etiqueta/{{ $tag->slug }}" class="badge bg-secondary text-decoration-none">{{ $tag->name }}</a>
                        @endforeach
                    </div>
                    @endif
                </div>
                @endif

                {{-- Navegación prev/next --}}
                @if(!empty($prevPost) || !empty($nextPost))
                <nav class="blog-post-nav mt-5 pt-4 border-top">
                    <div class="row">
                        @if(!empty($prevPost))
                        <div class="col-md-6 mb-3 mb-md-0">
                            <a href="/blog/{{ $prevPost->slug }}" class="text-decoration-none">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    <div>
                                        <small class="text-muted d-block">{{ __('blog.previous_post') }}</small>
                                        <strong>{{ $prevPost->title }}</strong>
                                    </div>
                                </div>
                            </a>
                        </div>
                        @endif

                        @if(!empty($nextPost))
                        <div class="col-md-6 text-md-end">
                            <a href="/blog/{{ $nextPost->slug }}" class="text-decoration-none">
                                <div class="d-flex align-items-center justify-content-md-end">
                                    <div>
                                        <small class="text-muted d-block">{{ __('blog.next_post') }}</small>
                                        <strong>{{ $nextPost->title }}</strong>
                                    </div>
                                    <i class="fas fa-arrow-right ms-2"></i>
                                </div>
                            </a>
                        </div>
                        @endif
                    </div>
                </nav>
                @endif
            </article>
        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4">
            @include('partials.sidebar')
        </div>
    </div>
</div>

@endsection
