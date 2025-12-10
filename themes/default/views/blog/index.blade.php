@extends('layouts.app')

{{-- SEO --}}
@section('title')
    {{ __('blog.title') . ' | ' . setting('site_name', 'MuseDock CMS') }}
@endsection

@section('description')
    {{ setting('site_description', '') }}
@endsection

@section('content')

<div class="container py-5">
    <div class="row">
        {{-- Contenido principal --}}
        <div class="col-lg-8">
            <h1 class="mb-4">{{ __('blog.title') }}</h1>

            @if(!empty($posts) && count($posts) > 0)
                <div class="row">
                @foreach($posts as $post)
                <div class="col-md-6 mb-4">
                    <article class="card h-100 shadow-sm border-0">
                        {{-- Imagen destacada o imagen por defecto --}}
                        <a href="/blog/{{ $post->slug }}">
                            @php
                                // Determinar la imagen a mostrar
                                if ($post->featured_image && !$post->hide_featured_image) {
                                    // Tiene imagen y no está oculta - usar la imagen del post
                                    $imageUrl = (str_starts_with($post->featured_image, '/media/') || str_starts_with($post->featured_image, 'http'))
                                        ? $post->featured_image
                                        : asset($post->featured_image);
                                } else {
                                    // No tiene imagen o está oculta - usar imagen por defecto
                                    $imageUrl = '/assets/themes/default/img/blog-default.svg';
                                }
                            @endphp
                            <img src="{{ $imageUrl }}" alt="{{ $post->title }}" class="card-img-top" style="height: 250px; object-fit: cover;">
                        </a>

                        {{-- Contenido --}}
                        <div class="card-body d-flex flex-column">
                            {{-- Título --}}
                            <h2 class="card-title h5 mb-2">
                                <a href="/blog/{{ $post->slug }}" class="text-decoration-none text-dark">{{ $post->title }}</a>
                            </h2>

                            {{-- Meta información --}}
                            <div class="post-meta mb-3 text-muted small">
                                <span><i class="far fa-calendar"></i> {{ date('d/m/Y', strtotime($post->published_at)) }}</span>
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
            @else
                <p class="text-muted">{{ __('blog.no_posts') }}</p>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4">
            @if(!empty($categories) && count($categories) > 0)
            <div class="widget widget-categories mb-4">
                <h4 class="widget-title">{{ __('blog.categories') }}</h4>
                <ul class="list-unstyled">
                    @foreach($categories as $cat)
                    <li><a href="/blog/category/{{ $cat->slug }}">{{ $cat->name }}</a></li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>
    </div>
</div>

@endsection
