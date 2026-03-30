@extends('layouts.app')

{{-- SEO --}}
@section('title')
    {{ __('blog.frontend.category') . ' | ' . __('blog.title') . ' | ' . site_setting('site_name', '') }}
@endsection

@section('description')
    {{ site_setting('site_description', '') }}
@endsection

@section('content')

<!-- ====== Banner Start ====== -->
<section class="ud-page-banner" style="background-image: linear-gradient(rgba(48, 86, 211, 0.55), rgba(48, 86, 211, 0.55)), url('{{ asset('img/background.jpg') }}'); background-size: cover; background-position: center; background-repeat: no-repeat;">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="ud-banner-content">
                    <h1>{{ __('blog.categories') }}</h1>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- ====== Banner End ====== -->

<!-- ====== Categories Start ====== -->
<section class="py-5">
    <div class="container">
        @if(!empty($categories) && count($categories) > 0)
        <div class="row">
            @foreach($categories as $category)
            <div class="col-lg-4 col-md-6 mb-4">
                <a href="{{ blog_url($category->slug, 'category') }}" class="text-decoration-none">
                    <div class="ud-single-blog">
                        @if($category->image)
                        <div class="ud-blog-image">
                            <img src="{{ $category->image }}" alt="{{ $category->name }}" loading="lazy" />
                        </div>
                        @endif
                        <div class="ud-blog-content text-center">
                            <h3 class="ud-blog-title">{{ $category->name }}</h3>
                            @if($category->description)
                            <p class="ud-blog-desc">{{ mb_strlen($category->description) > 100 ? mb_substr($category->description, 0, 100) . '...' : $category->description }}</p>
                            @endif
                            <span class="badge bg-primary">{{ $category->post_count ?? 0 }} {{ __('blog.posts') }}</span>
                        </div>
                    </div>
                </a>
            </div>
            @endforeach
        </div>
        @else
        <div class="row">
            <div class="col-12">
                <p class="text-muted text-center">{{ __('blog.frontend.no_posts') }}</p>
            </div>
        </div>
        @endif
    </div>
</section>
<!-- ====== Categories End ====== -->

@endsection
