@extends('layouts.app')

@section('title')
    {{ site_setting('site_name', '') }}
@endsection

@section('description')
    {{ site_setting('site_description', '') }}
@endsection

@section('keywords')
    {{ site_setting('site_keywords', '') }}
@endsection

@section('content')

{{-- Carousel Start --}}
<div class="container-fluid px-0">
    <div id="carouselId" class="carousel slide" data-bs-ride="carousel">
        @php
            $slides = themeOption('home_carousel_slides', [
                [
                    'image' => 'themes/HighTechIT/img/carousel-1.jpg',
                    'subtitle' => 'Best IT Solutions',
                    'title' => 'An Innovative IT Solutions Agency',
                    'description' => 'Lorem ipsum dolor sit amet elit. Sed efficitur quis purus ut interdum. Pellentesque aliquam dolor eget urna ultricies tincidunt.',
                    'btn1_text' => 'Read More',
                    'btn1_url' => '#',
                    'btn2_text' => 'Contact Us',
                    'btn2_url' => '/contact'
                ],
                [
                    'image' => 'themes/HighTechIT/img/carousel-2.jpg',
                    'subtitle' => 'Best IT Solutions',
                    'title' => 'Quality Digital Services You Really Need!',
                    'description' => 'Lorem ipsum dolor sit amet elit. Sed efficitur quis purus ut interdum. Pellentesque aliquam dolor eget urna ultricies tincidunt.',
                    'btn1_text' => 'Read More',
                    'btn1_url' => '#',
                    'btn2_text' => 'Contact Us',
                    'btn2_url' => '/contact'
                ]
            ]);
            $slideCount = is_array($slides) ? count($slides) : 0;
        @endphp

        @if($slideCount > 1)
        <ol class="carousel-indicators">
            @foreach($slides as $index => $slide)
                <li data-bs-target="#carouselId" data-bs-slide-to="{{ $index }}" class="{{ $index === 0 ? 'active' : '' }}" aria-current="{{ $index === 0 ? 'true' : 'false' }}" aria-label="Slide {{ $index + 1 }}"></li>
            @endforeach
        </ol>
        @endif

        <div class="carousel-inner" role="listbox">
            @foreach($slides as $index => $slide)
                <div class="carousel-item {{ $index === 0 ? 'active' : '' }}">
                    <img src="{{ asset($slide['image']) }}" class="img-fluid" alt="Slide {{ $index + 1 }}">
                    <div class="carousel-caption">
                        <div class="container carousel-content">
                            <h6 class="text-secondary h4 animated fadeInUp">{{ $slide['subtitle'] }}</h6>
                            <h1 class="text-white display-1 mb-4 animated {{ $index === 0 ? 'fadeInRight' : 'fadeInLeft' }}">{{ $slide['title'] }}</h1>
                            <p class="mb-4 text-white fs-5 animated fadeInDown">{{ $slide['description'] }}</p>
                            @if(!empty($slide['btn1_text']))
                                <a href="{{ url($slide['btn1_url']) }}" class="me-2"><button type="button" class="px-4 py-sm-3 px-sm-5 btn btn-primary rounded-pill carousel-content-btn1 animated fadeInLeft">{{ $slide['btn1_text'] }}</button></a>
                            @endif
                            @if(!empty($slide['btn2_text']))
                                <a href="{{ url($slide['btn2_url']) }}" class="ms-2"><button type="button" class="px-4 py-sm-3 px-sm-5 btn btn-primary rounded-pill carousel-content-btn2 animated fadeInRight">{{ $slide['btn2_text'] }}</button></a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if($slideCount > 1)
        <button class="carousel-control-prev" type="button" data-bs-target="#carouselId" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#carouselId" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
        @endif
    </div>
</div>
{{-- Carousel End --}}

{{-- Content Area - Can be managed via page content or shortcodes --}}
<div class="container-fluid py-5">
    <div class="container">
        @if(isset($page) && !empty($page->content))
            {!! apply_filters('the_content', $page->content) !!}
        @else
            {{-- Default homepage content --}}
            <div class="text-center mx-auto pb-5 wow fadeIn" data-wow-delay=".3s" style="max-width: 800px;">
                <h5 class="text-primary">{{ __('Welcome') }}</h5>
                <h1 class="mb-4">{{ site_setting('site_name', 'HighTech') }}</h1>
                <p class="lead">{{ site_setting('site_description', '') }}</p>
            </div>
        @endif
    </div>
</div>

@endsection
