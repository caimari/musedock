@extends('layouts.app')

@section('title')
    404 - {{ __('errors.page_not_found') }} | {{ site_setting('site_name', '') }}
@endsection

@section('description')
    {{ __('errors.page_not_found_desc') }}
@endsection

@section('robots')
    noindex, nofollow
@endsection

@section('content')

<!-- ====== Banner Start ====== -->
<section class="ud-page-banner">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="ud-banner-content">
                    <h1>404</h1>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- ====== Banner End ====== -->

<!-- ====== Error 404 Start ====== -->
<section class="ud-404 py-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="ud-404-wrapper text-center">
                    <div class="ud-404-content">
                        <h2 class="ud-404-title">
                            {{ __('errors.page_not_found_title') }}
                        </h2>
                        <h5 class="ud-404-subtitle mt-3">
                            {{ __('errors.maybe_find_here') }}
                        </h5>
                        <ul class="ud-404-links mt-4">
                            <li>
                                <a href="{{ url('/') }}">{{ __('navigation.home') }}</a>
                            </li>
                            @php
                                $blogPublic = site_setting('blog_public', '1');
                            @endphp
                            @if($blogPublic === '1')
                            <li>
                                <a href="{{ url('/blog') }}">{{ __('navigation.blog') }}</a>
                            </li>
                            @endif
                            <li>
                                <a href="{{ url('/contact') }}">{{ __('navigation.contact') }}</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- ====== Error 404 End ====== -->

@endsection
