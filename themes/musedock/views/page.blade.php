@extends('layouts.app')

@php
  // Evitar warnings si este template se renderiza sin $page (ej: mal mapeo o reuse)
  $page = $page ?? null;
@endphp

@section('title') 
{{ ($translation->seo_title ?: $translation->title ?: 'Página') . ' | ' . site_setting('site_name', '') }}
@endsection

@section('keywords') 
{{ $translation->seo_keywords ?? site_setting('site_keywords', '') }}
@endsection

@section('description') 
{{ $translation->seo_description ?? site_setting('site_description', '') }}
@endsection

@section('og_title') 
{{ $translation->seo_title ?: $translation->title ?: 'Página' }}
@endsection

@section('og_description') 
{{ $translation->seo_description ?? site_setting('site_description', '') }}
@endsection

@section('twitter_title') 
{{ $translation->seo_title ?: $translation->title ?: 'Página' }}
@endsection

@section('twitter_description') 
{{ $translation->seo_description ?? site_setting('site_description', '') }}
@endsection

@section('content')
<div class="padding-none ziph-page_content">
  <div class="container">
    <div class="ziph-page_warp">

      @if(!empty($page->banner_image ?? null))
      <!-- Page Banner -->
      <div class="ziph-page_banner" style="background-image: url('{{ asset($page->banner_image) }}');">
        <div class="ziph-banner_overlay">
          <div class="container">
            <div class="row">
              <div class="col-md-12">
                <h1 class="ziph-page_title">{{ $translation->title }}</h1>

                {{-- Breadcrumbs --}}
                @if(function_exists('get_breadcrumb'))
                <nav class="ziph-page_breadcrumb">
                  <ol class="breadcrumb">
                    {!! get_breadcrumb() !!}
                  </ol>
                </nav>
                @endif
              </div>
            </div>
          </div>
        </div>
      </div>
      @endif

      <!-- Page Content -->
      <div class="ziph-page_content_main @if(!empty($page->banner_image ?? null)) ziph-with-banner @endif">
        <div class="row">
          @if(($page->show_sidebar ?? false) && ($page->sidebar_position ?? '') === 'left')
          <div class="col-md-3">
            @include('partials.sidebar-left')
          </div>
          <div class="col-md-9">
            <div class="ziph-page_content_inner">
              {{-- Título dentro del contenido desactivado (evita el "HOME" gigante); usar banner si se necesita --}}
              
              @if(!empty($translation->excerpt ?? null))
              <div class="ziph-page_excerpt">
                <p>{{ $translation->excerpt ?? '' }}</p>
              </div>
              @endif
              
              <div class="ziph-page_content_body">
                {!! apply_filters('the_content', $translation->content) !!}
              </div>
              
              {{-- Custom Fields --}}
              @if(isset($page->custom_fields) && $page->custom_fields)
              <div class="ziph-custom_fields">
                @foreach($page->custom_fields as $field => $value)
                @if($value)
                <div class="ziph-custom_field ziph-field-{{ $field }}">
                  <strong>{{ __($field) }}:</strong> {!! $value !!}
                </div>
                @endif
                @endforeach
              </div>
              @endif
              
              {{-- Page Meta Information --}}
              @if($page->show_meta)
              <div class="ziph-page_meta">
                @if($page->author)
                <div class="ziph-meta_author">
                  <i class="fa fa-user"></i> 
                  {{ __('Author') }}: {{ $page->author }}
                </div>
                @endif
                
                @if($translation->created_at)
                <div class="ziph-meta_date">
                  <i class="fa fa-calendar"></i> 
                  {{ __('Published') }}: {{ $translation->created_at->format('d/m/Y') }}
                </div>
                @endif
                
                @if($translation->updated_at && $translation->updated_at > $translation->created_at)
                <div class="ziph-meta_updated">
                  <i class="fa fa-clock-o"></i> 
                  {{ __('Updated') }}: {{ $translation->updated_at->format('d/m/Y') }}
                </div>
                @endif
                
                @if($page->category)
                <div class="ziph-meta_category">
                  <i class="fa fa-folder"></i> 
                  {{ __('Category') }}: {{ $page->category }}
                </div>
                @endif
              </div>
              @endif
              
              {{-- Tags --}}
              @if(isset($page->tags) && $page->tags)
              <div class="ziph-page_tags">
                <i class="fa fa-tags"></i>
                @foreach(explode(',', $page->tags) as $tag)
                <span class="ziph-tag">{{ trim($tag) }}</span>
                @endforeach
              </div>
              @endif
            </div>
          </div>
          @else
          <div class="col-md-12">
            <div class="ziph-page_content_inner">
              {{-- Título dentro del contenido desactivado (evita el "HOME" gigante); usar banner si se necesita --}}
              
              @if(!empty($translation->excerpt ?? null))
              <div class="ziph-page_excerpt">
                <p>{{ $translation->excerpt ?? '' }}</p>
              </div>
              @endif
              
              <div class="ziph-page_content_body">
                {!! apply_filters('the_content', $translation->content) !!}
              </div>
              
              {{-- Custom Fields --}}
              @if(isset($page->custom_fields) && $page->custom_fields)
              <div class="ziph-custom_fields">
                @foreach($page->custom_fields as $field => $value)
                @if($value)
                <div class="ziph-custom_field ziph-field-{{ $field }}">
                  <strong>{{ __($field) }}:</strong> {!! $value !!}
                </div>
                @endif
                @endforeach
              </div>
              @endif
              
              {{-- Page Meta Information --}}
              @if($page->show_meta)
              <div class="ziph-page_meta">
                @if($page->author)
                <div class="ziph-meta_author">
                  <i class="fa fa-user"></i> 
                  {{ __('Author') }}: {{ $page->author }}
                </div>
                @endif
                
                @if($translation->created_at)
                <div class="ziph-meta_date">
                  <i class="fa fa-calendar"></i> 
                  {{ __('Published') }}: {{ $translation->created_at->format('d/m/Y') }}
                </div>
                @endif
                
                @if($translation->updated_at && $translation->updated_at > $translation->created_at)
                <div class="ziph-meta_updated">
                  <i class="fa fa-clock-o"></i> 
                  {{ __('Updated') }}: {{ $translation->updated_at->format('d/m/Y') }}
                </div>
                @endif
                
                @if($page->category)
                <div class="ziph-meta_category">
                  <i class="fa fa-folder"></i> 
                  {{ __('Category') }}: {{ $page->category }}
                </div>
                @endif
              </div>
              @endif
              
              {{-- Tags --}}
              @if(isset($page->tags) && $page->tags)
              <div class="ziph-page_tags">
                <i class="fa fa-tags"></i>
                @foreach(explode(',', $page->tags) as $tag)
                <span class="ziph-tag">{{ trim($tag) }}</span>
                @endforeach
              </div>
              @endif
            </div>
          </div>
          @endif
          
          @if(($page->show_sidebar ?? false) && ($page->sidebar_position ?? '') === 'right')
          <div class="col-md-3">
            @include('partials.sidebar-right')
          </div>
          @endif
        </div>
      </div>
      
      {{-- Related Pages --}}
      @if(isset($related_pages) && $related_pages->count() > 0)
      <div class="ziph-related_pages">
        <h3>{{ __('Related Pages') }}</h3>
        <div class="row">
          @foreach($related_pages as $related_page)
          <div class="col-md-4">
            <div class="ziph-related_page_item">
              @if($related_page->featured_image)
              <div class="ziph-related_page_image">
                <a href="{{ url('/page/' . $related_page->slug) }}">
                  <img src="{{ asset($related_page->featured_image) }}" alt="{{ $related_page->title }}">
                </a>
              </div>
              @endif
              <div class="ziph-related_page_content">
                <h4>
                  <a href="{{ url('/page/' . $related_page->slug) }}">
                    {{ $related_page->title }}
                  </a>
                </h4>
                @if(!empty($related_page->excerpt ?? null))
                <p>{{ Str::limit(strip_tags($related_page->excerpt ?? ''), 150) }}</p>
                @endif
              </div>
            </div>
          </div>
          @endforeach
        </div>
      </div>
      @endif
      
    </div>
  </div>
</div>
@endsection

{{-- Page Styles --}}
@push('styles')
<style>
.ziph-page_banner {
    height: 300px;
    background-size: cover;
    background-position: center;
    position: relative;
    margin-bottom: 40px;
}

.ziph-banner_overlay {
    background: rgba(0,0,0,0.5);
    height: 100%;
    display: flex;
    align-items: center;
}

.ziph-page_title {
    color: #fff;
    font-size: 42px;
    font-weight: 700;
    margin: 0;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
}

.ziph-page_breadcrumb {
    margin-top: 15px;
}

.ziph-page_breadcrumb .breadcrumb {
    background: rgba(255,255,255,0.1);
    border-radius: 20px;
    padding: 10px 20px;
    margin: 0;
}

.ziph-page_breadcrumb .breadcrumb li,
.ziph-page_breadcrumb .breadcrumb li a {
    color: #fff;
    text-decoration: none;
}

.ziph-page_content_main {
    padding: 40px 0;
}

.ziph-page_content_inner {
    background: #fff;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.ziph-page_content_title {
    color: var(--header-logo-text-color, #1a2a40);
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 20px;
}

.ziph-page_excerpt {
    font-size: 18px;
    color: #666;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.ziph-page_content_body {
    line-height: 1.8;
    margin-bottom: 40px;
}

.ziph-page_content_body h1,
.ziph-page_content_body h2,
.ziph-page_content_body h3,
.ziph-page_content_body h4,
.ziph-page_content_body h5,
.ziph-page_content_body h6 {
    color: var(--header-logo-text-color, #1a2a40);
    margin-top: 30px;
    margin-bottom: 15px;
}

.ziph-page_content_body img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    margin: 20px 0;
}

.ziph-custom_fields {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin: 30px 0;
}

.ziph-custom_field {
    margin-bottom: 10px;
}

.ziph-page_meta {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin: 30px 0;
    font-size: 14px;
}

.ziph-page_meta div {
    margin-bottom: 8px;
}

.ziph-page_meta i {
    width: 20px;
    color: var(--footer-link-hover-color, #ff5e15);
}

.ziph-page_tags {
    margin: 20px 0;
}

.ziph-tag {
    display: inline-block;
    background: var(--footer-link-hover-color, #ff5e15);
    color: #fff;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    margin-right: 8px;
    margin-bottom: 8px;
}

.ziph-related_pages {
    margin-top: 60px;
    padding-top: 40px;
    border-top: 1px solid #eee;
}

.ziph-related_pages h3 {
    color: var(--header-logo-text-color, #1a2a40);
    margin-bottom: 30px;
}

.ziph-related_page_item {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 20px;
    margin-bottom: 20px;
    transition: transform 0.3s ease;
}

.ziph-related_page_item:hover {
    transform: translateY(-5px);
}

.ziph-related_page_image {
    margin-bottom: 15px;
}

.ziph-related_page_image img {
    width: 100%;
    border-radius: 8px;
    height: 150px;
    object-fit: cover;
}

.ziph-related_page_content h4 {
    margin: 0 0 10px 0;
    font-size: 18px;
}

.ziph-related_page_content h4 a {
    color: var(--header-logo-text-color, #1a2a40);
    text-decoration: none;
}

.ziph-related_page_content h4 a:hover {
    color: var(--footer-link-hover-color, #ff5e15);
}

.ziph-related_page_content p {
    color: #666;
    margin: 0;
    font-size: 14px;
}

@media (max-width: 768px) {
    .ziph-page_banner {
        height: 200px;
    }
    
    .ziph-page_title {
        font-size: 28px;
    }
    
    .ziph-page_content_inner {
        padding: 20px;
    }
    
    .ziph-page_content_title {
        font-size: 24px;
    }
    
    .ziph-related_pages .row {
        margin: 0;
    }
    
    .ziph-related_pages .col-md-4 {
        padding: 0;
        margin-bottom: 20px;
    }
}
</style>
@endpush
