@extends('layouts.app')

{{-- SEO --}}
@section('title')
    {{ __('blog.frontend.tag') . ' | ' . __('blog.title') . ' | ' . site_setting('site_name', '') }}
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
                    <h1>{{ __('blog.tags') }}</h1>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- ====== Banner End ====== -->

<!-- ====== Tags Start ====== -->
<section class="py-5">
    <div class="container">
        @if(!empty($tags) && count($tags) > 0)
        <div class="d-flex flex-wrap gap-3 justify-content-center py-4">
            @foreach($tags as $tag)
                <a href="{{ blog_url($tag->slug, 'tag') }}" class="btn btn-outline-primary btn-lg rounded-pill px-4">
                    {{ $tag->name }}
                    <span class="badge bg-primary ms-2">{{ $tag->post_count ?? 0 }}</span>
                </a>
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
<!-- ====== Tags End ====== -->

@endsection
