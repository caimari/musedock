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
<!-- START Simple Slider (pegado al header) -->
<div id="simple-slider" class="simple-slider-container">
  <!-- Slide 1 -->
	  <div class="simple-slide active" style="background-image: url('{{ asset('themes/musedock/images/home-one-slider-one.jpg') }}');">
	    <div class="simple-slide-content">
	      <div class="slide-text">
	        <h1>Crea tu sitio web gratis en segundos</h1>
	        <ul class="slide-features">
	          <li><i class="fa fa-check"></i> Panel propio para crear y publicar</li>
	          <li><i class="fa fa-check"></i> 1GB gratis para empezar</li>
	          <li><i class="fa fa-check"></i> SSL gratuito incluido</li>
	        </ul>
	        <div class="slide-button">
	          <a href="{{ url('/register') }}">Crear mi sitio</a>
	        </div>
	      </div>
	      <div class="slide-image">
	        <img src="{{ asset('themes/musedock/images/object-one.png') }}" alt="MuseDock" class="animated-obj-1">
	      </div>
	    </div>
	  </div>

  <!-- Slide 2 -->
	  <div class="simple-slide" style="background-image: url('{{ asset('themes/musedock/images/home-one-slider-two.jpg') }}');">
	    <div class="simple-slide-content">
	      <div class="slide-text">
	        <h1><strong>Subdominio</strong> incluido<br>en .musedock.com</h1>
	        <ul class="slide-features">
	          <li><i class="fa fa-check"></i> Tu web en minutos, sin instalar nada</li>
	          <li><i class="fa fa-check"></i> URL: <strong>tu-subdominio.musedock.com</strong></li>
	          <li><i class="fa fa-check"></i> Escala cuando tu proyecto crece</li>
	        </ul>
	        <div class="slide-button">
	          <a href="{{ url('/register') }}">Empezar gratis</a>
	        </div>
	      </div>
	      <div class="slide-image">
	        <img src="{{ asset('themes/musedock/images/object-two.png') }}" alt="Infraestructura" class="animated-obj-2">
	      </div>
	    </div>
	  </div>

  <!-- Slide 3 -->
	  <div class="simple-slide" style="background-image: url('{{ asset('themes/musedock/images/home-one-slider-three.jpg') }}');">
	    <div class="simple-slide-content">
	      <h1><strong>Seguridad</strong> y rendimiento<br>con Cloudflare</h1>
	      <ul class="slide-features">
	        <li><i class="fa fa-check"></i> Proxy naranja + mitigación de ataques</li>
	        <li><i class="fa fa-check"></i> SSL gratis y HTTPS siempre</li>
	        <li><i class="fa fa-check"></i> Caché/CDN para más velocidad</li>
	      </ul>

	    </div>
	  </div>
</div>
<!-- END Simple Slider -->

<!-- Domain Search (hardcoded) -->
<div class="ziph-page_content ziph-dhav-dotted" style="background-color:#267ae9;">
  <div class="container">
    <div class="ziph-page_warp">
      <section class="wpb-content-wrapper">
        <div class="row">
          <div class="col-sm-12">
            <div class="ziph-domainsearch_area">
              <div class="ziph-domainsearch">
                <div class="ziph-fix ziph-domainsrch_warp">
                  <h3 class="ziph-flt_left ziph-domainsrch_title">{{ __('home.find_perfect_domain') }}</h3>
                  <div class="ziph-flt_right ziph-domainsrch_form">
                    <form method="get" action="{{ url('/domain') }}">
                      <input type="search" id="whmcs_domain_search" name="query" placeholder="{{ __('home.enter_domain') }}">
                      <input id="domsrch_btn" value="{{ __('home.search') }}" type="submit">
                    </form>
                    <div class="text-right ziph-domainsrch_links">
                      <a href="{{ url('/domain') }}">{{ __('home.view_domain_price_list') }}</a>
                      <a href="{{ url('/domain') }}">{{ __('home.bulk_domain_search') }}</a>
                      <a href="{{ url('/domain') }}">{{ __('home.transfer_domain') }}</a>
                    </div>
                  </div>
                </div>

                <div class="text-right ziph-domainsrch_price">
                  <span class="ziph-dsp_col"><strong>.com</strong> $5.75</span>
                  <span class="ziph-dsp_col"><strong>.net</strong> $9.75</span>
                  <span class="ziph-dsp_col"><strong>.org</strong> $7.75</span>
                  <span class="ziph-dsp_col"><strong>.us</strong> $5.75</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</div>

@php
  $homeContent = $translation->content ?? ($page->content ?? '');
@endphp
@if(!empty($homeContent))
<div class="padding-none ziph-page_content">
  <div class="ziph-page_warp">
    <div class="container">
      <div class="home-content-wrapper">
        {!! apply_filters('the_content', $homeContent) !!}
      </div>
    </div>
  </div>
</div>
@endif
@endsection

{{-- Additional styles for slider --}}
@push('styles')
<style>
/* Contenedor del contenido de la home - evitar desbordamiento */
.home-content-wrapper {
    width: 100%;
    max-width: 100%;
    overflow: hidden;
    box-sizing: border-box;
}

.home-content-wrapper p {
    max-width: 100%;
    overflow: hidden;
}

.home-content-wrapper img {
    max-width: 100%;
    height: auto;
    display: inline-block;
    vertical-align: middle;
}

/* Asegurar que el contenedor padre no se desborde */
.ziph-page_warp {
    overflow: hidden;
    max-width: 100%;
}

.ziph-header_navigation { margin-bottom: 0; }
.simple-slider-container { margin-top: 0; }

.simple-slider-container {
    position: relative;
    height: 490px;
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
    align-self: flex-end;
    margin-bottom: -1px; /* pega la imagen al borde inferior del slider */
}

.slide-image img {
    max-width: 470px;
    height: auto;
    max-height: 560px;
    display: block;
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
