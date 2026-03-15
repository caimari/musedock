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
        <h1 class="mb-2" style="font-size: 1.75rem; margin-top: 0;">{{ __('blog.categories') }}</h1>
    </div>

    @if(!empty($categories) && count($categories) > 0)
        <div class="row">
        @foreach($categories as $category)
        <div class="col-lg-4 col-md-6 mb-4">
            <a href="{{ blog_url($category->slug, 'category') }}" class="text-decoration-none">
                <div class="card h-100 shadow-sm border-0 hover-shadow">
                    @if($category->image)
                    <img src="{{ $category->image }}" alt="{{ $category->name }}" class="card-img-top" style="height: 180px; object-fit: cover;">
                    @endif
                    <div class="card-body text-center">
                        <h2 class="card-title h5 mb-2 text-dark">{{ $category->name }}</h2>
                        @if($category->description)
                        <p class="card-text text-muted small mb-2">{{ mb_strlen($category->description) > 100 ? mb_substr($category->description, 0, 100) . '...' : $category->description }}</p>
                        @endif
                        <span class="badge bg-primary rounded-pill">{{ $category->post_count ?? 0 }} {{ __('blog.posts') }}</span>
                    </div>
                </div>
            </a>
        </div>
        @endforeach
        </div>
    @else
        <div class="text-center py-5">
            <i class="bi bi-folder" style="font-size: 4rem; color: #ccc;"></i>
            <p class="text-muted mt-3">{{ __('blog.frontend.no_posts') }}</p>
            <a href="{{ blog_url() }}" class="btn btn-outline-primary mt-2">{{ __('blog.view_all') }}</a>
        </div>
    @endif
</div>

@endsection
