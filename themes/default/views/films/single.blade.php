@extends('layouts.app')

@section('title', $seoTitle . ' | ' . site_setting('site_name', 'IberoFilms'))
@section('description', $seoDesc)
@section('og_title', $seoTitle)
@section('og_description', $seoDesc)
@if($seoImage)
@section('og_image', $seoImage)
@endif

@section('content')

{{-- Schema.org Movie JSON-LD --}}
@if(isset($__jsonld_movie))
<script type="application/ld+json">{!! $__jsonld_movie !!}</script>
@endif
@if(isset($__jsonld_breadcrumb))
<script type="application/ld+json">{!! $__jsonld_breadcrumb !!}</script>
@endif

<style>
  .film-page, .film-page a:not(.badge), .film-page .breadcrumb-item a,
  .film-page .card, .film-page .card-body, .film-page .card-title,
  .film-page strong, .film-page small, .film-page p, .film-page div {
    color: #333 !important;
  }
  .film-page .breadcrumb-item.active { color: #666 !important; }
  .film-page .breadcrumb-item a { color: #0d6efd !important; }
  .film-page .text-muted { color: #777 !important; }
  .film-page .badge { color: #fff !important; }
  .film-page .badge.bg-light { color: #333 !important; }
  /* Cast photos — perfect circle with fixed square container */
  .film-cast-photo {
    width: 52px !important;
    height: 52px !important;
    min-width: 52px !important;
    max-width: 52px !important;
    min-height: 52px !important;
    max-height: 52px !important;
    border-radius: 50% !important;
    object-fit: cover !important;
    display: block !important;
    aspect-ratio: 1 / 1 !important;
  }
  .film-cast-placeholder {
    width: 52px !important;
    height: 52px !important;
    min-width: 52px !important;
    min-height: 52px !important;
    border-radius: 50% !important;
    display: flex !important;
    align-items: center;
    justify-content: center;
  }
  .film-cast-item a { text-decoration: none; }
  .film-cast-item a:hover small.fw-semibold { text-decoration: underline; }
  /* Trailer full width */
  .film-trailer-wrap {
    position: relative;
    width: 100%;
    padding-bottom: 56.25%; /* 16:9 */
    height: 0;
    overflow: hidden;
    border-radius: 8px;
  }
  .film-trailer-wrap iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 100% !important;
    height: 100% !important;
    border: 0;
  }
  .film-card { transition: transform 0.2s; overflow: hidden; border-radius: 8px; }
  .film-card:hover { transform: translateY(-3px); }
</style>

<div class="container py-4 film-page">

  {{-- Breadcrumbs --}}
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="/">Inicio</a></li>
      <li class="breadcrumb-item"><a href="/films">Películas</a></li>
      <li class="breadcrumb-item active">{{ $film->title }}</li>
    </ol>
  </nav>

  {{-- Backdrop --}}
  @if($film->backdrop_path)
  <div class="mb-4 rounded overflow-hidden position-relative" style="max-height:350px;">
    <img src="{{ film_poster_url($film->backdrop_path, 'w1280') }}" alt="{{ $film->title }}" class="w-100" style="object-fit:cover;max-height:350px;">
    <div class="position-absolute bottom-0 start-0 w-100 p-3" style="background:linear-gradient(transparent,rgba(0,0,0,0.7));">
      <h1 class="mb-0" style="color:#fff !important;">{{ $film->title }} @if($film->year)<small>({{ $film->year }})</small>@endif</h1>
      @if($film->tagline)
        <p class="mb-0 fst-italic" style="color:#ddd !important;">{{ $film->tagline }}</p>
      @endif
    </div>
  </div>
  @endif

  <div class="row">
    {{-- Sidebar: Poster --}}
    <div class="col-lg-3 col-md-4 mb-4">
      @if($film->poster_path)
        <img src="{{ film_poster_url($film->poster_path, 'w500') }}" alt="{{ $film->title }}" class="img-fluid rounded shadow">
      @endif

      <div class="card mt-3">
        <div class="card-body small">
          @if($film->director)
          <div class="mb-2">
            <strong>Director</strong><br>
            <a href="/films/director/{{ $film->director_slug }}">{{ $film->director }}</a>
          </div>
          @endif
          @if($film->year)
          <div class="mb-2">
            <strong>Año</strong><br>
            <a href="/films/year/{{ $film->year }}">{{ $film->year }}</a>
          </div>
          @endif
          @if($film->runtime)
          <div class="mb-2"><strong>Duración</strong><br>{{ $film->runtime }} min</div>
          @endif
          @if($film->original_language)
          <div class="mb-2"><strong>Idioma original</strong><br>{{ strtoupper($film->original_language) }}</div>
          @endif
          @if($film->production_countries)
          <div class="mb-2"><strong>Países</strong><br>{{ $film->production_countries }}</div>
          @endif
          @if($film->tmdb_rating)
          <div class="mb-2">
            <strong>Rating TMDb</strong><br>
            <span class="badge bg-warning text-dark">{{ number_format($film->tmdb_rating, 1) }}/10</span>
            <small>({{ number_format($film->tmdb_vote_count ?? 0) }} votos)</small>
          </div>
          @endif
          @if($film->editorial_rating)
          <div class="mb-2">
            <strong>Puntuación editorial</strong><br>
            <span class="badge bg-primary">{{ number_format($film->editorial_rating, 1) }}/10</span>
          </div>
          @endif
        </div>
      </div>

      @if(!empty($genres))
      <div class="mt-3">
        @foreach($genres as $genre)
          <a href="/films/genero/{{ $genre->slug }}" class="badge rounded-pill text-decoration-none mb-1" style="background:{{ $genre->color ?? '#6c757d' }}">{{ $genre->name }}</a>
        @endforeach
      </div>
      @endif
    </div>

    {{-- Main Content --}}
    <div class="col-lg-9 col-md-8">

      @if(!$film->backdrop_path)
      <h1 class="h2 mb-1">{{ $film->title }} @if($film->year)<small>({{ $film->year }})</small>@endif</h1>
      @if($film->tagline)
        <p class="fst-italic mb-3" style="color:#888 !important;">{{ $film->tagline }}</p>
      @endif
      @endif

      {{-- Synopsis --}}
      @if($film->synopsis_editorial)
      <div class="card mb-4">
        <div class="card-body">
          <h5 class="card-title">Sinopsis</h5>
          <div>{!! nl2br(e($film->synopsis_editorial)) !!}</div>
        </div>
      </div>
      @elseif($film->synopsis_tmdb)
      <div class="card mb-4">
        <div class="card-body">
          <h5 class="card-title">Sinopsis</h5>
          <div>{{ $film->synopsis_tmdb }}</div>
        </div>
      </div>
      @endif

      @if($film->editorial_content)
      <div class="card mb-4">
        <div class="card-body">
          <h5 class="card-title">Análisis editorial</h5>
          <div>{!! nl2br(e($film->editorial_content)) !!}</div>
        </div>
      </div>
      @endif

      @if($film->editorial_context)
      <div class="card mb-4">
        <div class="card-body">
          <h5 class="card-title">Contexto</h5>
          <div>{!! nl2br(e($film->editorial_context)) !!}</div>
        </div>
      </div>
      @endif

      {{-- Trailer --}}
      @if($film->trailer_url)
      <div class="card mb-4">
        <div class="card-body">
          <h5 class="card-title">Tráiler</h5>
          @php
            $videoId = '';
            if (preg_match('/(?:v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $film->trailer_url, $m)) {
                $videoId = $m[1];
            }
          @endphp
          @if($videoId)
            <div class="film-trailer-wrap">
              <iframe src="https://www.youtube.com/embed/{{ $videoId }}" allowfullscreen loading="lazy"></iframe>
            </div>
          @else
            <a href="{{ $film->trailer_url }}" target="_blank" class="btn btn-outline-danger">
              <i class="bi bi-play-circle me-1"></i> Ver tráiler
            </a>
          @endif
        </div>
      </div>
      @endif

      {{-- Cast --}}
      @php $cast = $film->getCast(15); @endphp
      @if(!empty($cast))
      <div class="card mb-4">
        <div class="card-body">
          <h5 class="card-title">Reparto</h5>
          <div class="row g-2">
            @foreach($cast as $actor)
            @php
              $actorSlug = function_exists('film_slugify') ? film_slugify($actor['name']) : strtolower(preg_replace('/[^a-z0-9]+/', '-', $actor['name']));
              $actorUrl = '/films/actor/' . ($actor['tmdb_id'] ?? 0) . '-' . $actorSlug;
            @endphp
            <div class="col-6 col-md-4 col-lg-3 film-cast-item">
              <a href="{{ $actorUrl }}" class="d-flex align-items-center">
                @if(!empty($actor['photo']))
                  <img src="https://image.tmdb.org/t/p/w185{{ $actor['photo'] }}" alt="{{ $actor['name'] }}" class="film-cast-photo me-2">
                @else
                  <div class="film-cast-placeholder bg-light me-2">
                    <i class="bi bi-person text-muted"></i>
                  </div>
                @endif
                <div>
                  <small class="fw-semibold d-block">{{ $actor['name'] }}</small>
                  @if(!empty($actor['character']))
                    <small style="color:#888 !important;">{{ $actor['character'] }}</small>
                  @endif
                </div>
              </a>
            </div>
            @endforeach
          </div>
        </div>
      </div>
      @endif

      {{-- Watch Providers --}}
      @php $providers = $film->getWatchProviders(); @endphp
      @if(!empty($providers))
      <div class="card mb-4">
        <div class="card-body">
          <h5 class="card-title">Dónde verla</h5>
          <div class="d-flex flex-wrap gap-2">
            @foreach($providers as $p)
              @php
                $searchUrl = 'https://www.google.com/search?q=' . urlencode('ver "' . $film->title . '" ' . ($film->year ?? '') . ' en ' . $p['name']);
              @endphp
              <a href="{{ $searchUrl }}" target="_blank" rel="noopener" class="badge bg-light border text-decoration-none" style="color:#333 !important;cursor:pointer;">
                @if(!empty($p['logo_path']))
                  <img src="https://image.tmdb.org/t/p/w45{{ $p['logo_path'] }}" alt="{{ $p['name'] }}" style="height:20px;border-radius:3px;" class="me-1">
                @endif
                {{ $p['name'] }} <small style="color:#888 !important;">({{ $p['type'] }})</small>
              </a>
            @endforeach
          </div>
          <small class="mt-2 d-block" style="color:#999 !important;">Datos de disponibilidad proporcionados por JustWatch/TMDb.</small>
        </div>
      </div>
      @endif

      {{-- Related Films --}}
      @if(!empty($relatedFilms))
      <h5 class="mt-5 mb-3">Películas relacionadas</h5>
      <div class="row g-3">
        @foreach($relatedFilms as $related)
        <div class="col-6 col-md-4 col-lg-3">
          <a href="/films/{{ $related->slug }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm film-card">
              @if($related->poster_path)
                <img src="{{ film_poster_url($related->poster_path, 'w342') }}" alt="{{ $related->title }}" class="card-img-top" style="aspect-ratio:2/3;object-fit:cover;">
              @else
                <div class="card-img-top bg-dark d-flex align-items-center justify-content-center" style="aspect-ratio:2/3;color:#888 !important;">
                  <i class="bi bi-camera-reels"></i>
                </div>
              @endif
              <div class="card-body p-2">
                <small class="fw-semibold" style="color:#333 !important;">{{ $related->title }}</small><br>
                <small style="color:#888 !important;">{{ $related->year ?? '' }}</small>
              </div>
            </div>
          </a>
        </div>
        @endforeach
      </div>
      @endif

    </div>
  </div>
</div>

@include('films/partials/_image_fallback')
@endsection
