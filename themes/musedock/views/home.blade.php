@extends('layouts.app')

@section('title') 
{{ __('Home') . ' | ' . site_setting('site_name', '') }}
@endsection

@section('keywords') 
{{ site_setting('site_keywords', '') }}
@endsection

@section('description') 
{{ site_setting('site_description', '') }}
@endsection

@section('og_title') 
{{ site_setting('site_name', '') . ' - ' . __('Web Hosting Solutions') }}
@endsection

@section('og_description') 
{{ site_setting('site_description', '') }}
@endsection

@section('twitter_title') 
{{ site_setting('site_name', '') . ' - ' . __('Web Hosting Solutions') }}
@endsection

@section('twitter_description') 
{{ site_setting('site_description', '') }}
@endsection

@section('content')
<div class="padding-none ziph-page_content">
  <div class="ziph-page_warp">
    
    <!-- START Simple Slider -->
    <div id="simple-slider" class="simple-slider-container">
      <!-- Slide 1 -->
      <div class="simple-slide active" style="background-image: url('{{ asset('themes/musedock/images/home-one-slider-one.jpg') }}');">
        <div class="simple-slide-content">
          <div class="slide-text">
            <h1><strong>{{ __('Amazing') }}</strong> {{ __('Hosting Plans') }}<br>{{ __('with FREE DOMAIN') }}</h1>
            <ul class="slide-features">
              <li><i class="fa fa-check"></i> {{ __('Unlimited Disk Space, Bandwidth') }}</li>
              <li><i class="fa fa-check"></i> {{ __('Free domain registration') }}</li>
              <li><i class="fa fa-check"></i> {{ __('1,000s of free templates') }}</li>
              <li><i class="fa fa-check"></i> {{ __('24/7 Support') }}</li>
            </ul>
            <div class="slide-button">
              <a href="{{ url('/shared-hosting') }}">{{ __('Get Started Now') }}</a>
            </div>
          </div>
          <div class="slide-image">
            <img src="{{ asset('themes/musedock/images/object-one.png') }}" alt="{{ __('Hosting Guy') }}" class="animated-obj-1">
          </div>
        </div>
      </div>

      <!-- Slide 2 -->
      <div class="simple-slide" style="background-image: url('{{ asset('themes/musedock/images/home-one-slider-two.jpg') }}');">
        <div class="simple-slide-content">
          <div class="slide-text">
            <h1><strong>{{ __('Premium') }}</strong> {{ __('Web Hosting') }}<br>{{ __('Starting at $2.99/mo') }}</h1>
            <ul class="slide-features">
              <li><i class="fa fa-check"></i> {{ __('99.9% Uptime Guarantee') }}</li>
              <li><i class="fa fa-check"></i> {{ __('Free SSL Certificate') }}</li>
              <li><i class="fa fa-check"></i> {{ __('One-Click WordPress Install') }}</li>
              <li><i class="fa fa-check"></i> {{ __('Money Back Guarantee') }}</li>
            </ul>
            <div class="slide-button">
              <a href="{{ url('/shared-hosting') }}">{{ __('View Plans') }}</a>
            </div>
          </div>
          <div class="slide-image">
            <img src="{{ asset('themes/musedock/images/object-two.png') }}" alt="{{ __('Servers') }}" class="animated-obj-2">
          </div>
        </div>
      </div>

      <!-- Slide 3 -->
      <div class="simple-slide" style="background-image: url('{{ asset('themes/musedock/images/home-one-slider-three.jpg') }}');">
        <div class="simple-slide-content">
          <h1><strong>{{ __('Powerful') }}</strong> {{ __('Cloud Hosting') }}<br>{{ __('for Your Business') }}</h1>
          <ul class="slide-features">
            <li><i class="fa fa-check"></i> {{ __('High Performance SSD Storage') }}</li>
            <li><i class="fa fa-check"></i> {{ __('Scalable Resources') }}</li>
            <li><i class="fa fa-check"></i> {{ __('Advanced Security Features') }}</li>
            <li><i class="fa fa-check"></i> {{ __('Expert Support Team') }}</li>
          </ul>
          <div class="slide-button">
            <a href="{{ url('/cloud-hosting') }}">{{ __('Learn More') }}</a>
          </div>
        </div>
      </div>
    </div>
    <!-- END Simple Slider -->

    <!-- Dynamic Content from CMS -->
    @if(isset($page) && $page)
    <div class="container">
      <div class="ziph-page_content">
        {!! apply_filters('the_content', $page->content ?? '') !!}
      </div>
    </div>
    @endif

  </div>
</div>
@endsection

{{-- Additional styles for slider --}}
@push('styles')
<style>
.simple-slider-container {
    position: relative;
    height: 730px;
    overflow: hidden;
}

.simple-slide {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    display: none;
}

.simple-slide.active {
    display: block;
}

.simple-slide-content {
    display: flex;
    align-items: center;
    height: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 15px;
}

.slide-text {
    flex: 1;
    color: #fff;
    max-width: 600px;
}

.slide-text h1 {
    font-size: 48px;
    font-weight: 700;
    line-height: 1.2;
    margin-bottom: 30px;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
}

.slide-features {
    list-style: none;
    padding: 0;
    margin: 0 0 40px 0;
}

.slide-features li {
    margin-bottom: 15px;
    font-size: 18px;
    display: flex;
    align-items: center;
}

.slide-features i {
    margin-right: 15px;
    color: #00ff88;
    font-size: 16px;
}

.slide-button a {
    display: inline-block;
    background-color: #ff5e15;
    color: #fff;
    padding: 15px 30px;
    text-decoration: none;
    border-radius: 5px;
    font-weight: 600;
    font-size: 16px;
    transition: all 0.3s ease;
}

.slide-button a:hover {
    background-color: #e54c08;
    transform: translateY(-2px);
}

.slide-image {
    flex: 0 0 auto;
    margin-left: 50px;
}

.slide-image img {
    max-width: 400px;
    height: auto;
}

@media (max-width: 992px) {
    .simple-slide-content {
        flex-direction: column;
        text-align: center;
        padding: 60px 15px;
    }
    
    .slide-text {
        order: 2;
        max-width: 100%;
        margin-top: 30px;
    }
    
    .slide-image {
        order: 1;
        margin-left: 0;
        margin-bottom: 30px;
    }
    
    .slide-text h1 {
        font-size: 36px;
    }
    
    .slide-features li {
        font-size: 16px;
    }
}

@media (max-width: 768px) {
    .simple-slider-container {
        height: 600px;
    }
    
    .slide-text h1 {
        font-size: 28px;
        margin-bottom: 20px;
    }
    
    .slide-features li {
        font-size: 14px;
        margin-bottom: 10px;
    }
    
    .slide-image img {
        max-width: 250px;
    }
    
    .slide-button a {
        padding: 12px 25px;
        font-size: 14px;
    }
}
</style>
@endpush

{{-- Slider initialization script --}}
@push('scripts')
<script>
$(document).ready(function() {
    // Simple slider functionality
    let currentSlide = 0;
    const slides = $('.simple-slide');
    const totalSlides = slides.length;
    
    function showSlide(index) {
        slides.removeClass('active');
        slides.eq(index).addClass('active');
    }
    
    function nextSlide() {
        currentSlide = (currentSlide + 1) % totalSlides;
        showSlide(currentSlide);
    }
    
    function prevSlide() {
        currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
        showSlide(currentSlide);
    }
    
    // Auto-play slider
    setInterval(nextSlide, 5000);
    
    // Keyboard navigation
    $(document).keydown(function(e) {
        if (e.key === 'ArrowLeft') {
            prevSlide();
        } else if (e.key === 'ArrowRight') {
            nextSlide();
        }
    });
    
    // Touch/swipe support
    let touchStartX = 0;
    let touchEndX = 0;
    
    $('#simple-slider').on('touchstart', function(e) {
        touchStartX = e.originalEvent.touches[0].clientX;
    });
    
    $('#simple-slider').on('touchend', function(e) {
        touchEndX = e.originalEvent.changedTouches[0].clientX;
        handleSwipe();
    });
    
    function handleSwipe() {
        if (touchEndX < touchStartX - 50) {
            nextSlide(); // Swipe left
        } else if (touchEndX > touchStartX + 50) {
            prevSlide(); // Swipe right
        }
    }
});
</script>
@endpush
