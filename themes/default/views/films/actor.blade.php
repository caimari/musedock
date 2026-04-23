@extends('layouts.app')

@section('title', $actorName . ' — Filmografía | ' . site_setting('site_name', 'IberoFilms'))

@section('content')

@if(isset($__jsonld_person))
<script type="application/ld+json">{!! $__jsonld_person !!}</script>
@endif

<style>
  .actor-page, .actor-page a:not(.badge):not(.btn), .actor-page p, .actor-page small,
  .actor-page div, .actor-page td, .actor-page th, .actor-page h1, .actor-page h2,
  .actor-page h3, .actor-page h4, .actor-page h5, .actor-page h6, .actor-page li,
  .actor-page strong { color: #333 !important; }
  .actor-page .text-muted { color: #777 !important; }
  .actor-page .breadcrumb-item a { color: #0d6efd !important; }
  .actor-photo-hero {
    width: 180px !important;
    height: 180px !important;
    min-width: 180px !important;
    min-height: 180px !important;
    max-width: 180px !important;
    max-height: 180px !important;
    border-radius: 50% !important;
    object-fit: cover !important;
    aspect-ratio: 1 / 1 !important;
    display: block !important;
    border: 4px solid #fff;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
  }
  .actor-photo-placeholder {
    width: 180px; height: 180px; border-radius: 50%; background: #e9ecef;
    display: flex; align-items: center; justify-content: center;
  }
  .film-card { transition: transform 0.2s; overflow: hidden; border-radius: 8px; }
  .film-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important; }
  .filmography-row { border-bottom: 1px solid #eee; padding: 8px 0; cursor: pointer; transition: background 0.15s; }
  .filmography-row:hover { background: #e8f4fd !important; }
  .filmography-poster { width: 35px; height: 52px; object-fit: cover; border-radius: 4px; }
  .filmography-year { width: 50px; font-weight: bold; color: #555 !important; }
  .in-catalog-badge { font-size: 0.65rem; }
</style>

<div class="container py-4 actor-page">

  {{-- Breadcrumbs --}}
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="/">Inicio</a></li>
      <li class="breadcrumb-item"><a href="/films">Películas</a></li>
      <li class="breadcrumb-item active">{{ $actorName }}</li>
    </ol>
  </nav>

  <div class="row">
    {{-- Sidebar --}}
    <div class="col-lg-3 col-md-4 mb-4 text-center">
      @if($actorPhoto)
        <img src="https://image.tmdb.org/t/p/w500{{ $actorPhoto }}" alt="{{ $actorName }}" class="actor-photo-hero mb-3">
      @else
        <div class="actor-photo-placeholder mb-3 mx-auto">
          <i class="bi bi-person" style="font-size:4rem;color:#aaa;"></i>
        </div>
      @endif

      <h1 class="h3 fw-bold">{{ $actorName }}</h1>

      <div class="card mt-3 text-start">
        <div class="card-body small">
          @if($knownFor)
          <div class="mb-2"><strong>Conocido por</strong><br>{{ $knownFor === 'Acting' ? 'Interpretación' : $knownFor }}</div>
          @endif

          @if($birthday)
          <div class="mb-2">
            <strong>Fecha de nacimiento</strong><br>
            {{ date('j \d\e F \d\e Y', strtotime($birthday)) }}
            @if($age && !$deathday) ({{ $age }} años)@endif
          </div>
          @endif

          @if($deathday)
          <div class="mb-2">
            <strong>Fallecimiento</strong><br>
            {{ date('j \d\e F \d\e Y', strtotime($deathday)) }} ({{ $age }} años)
          </div>
          @endif

          @if($birthplace)
          <div class="mb-2"><strong>Lugar de nacimiento</strong><br>{{ $birthplace }}</div>
          @endif

          @if($gender)
          <div class="mb-2"><strong>Género</strong><br>{{ $gender == 1 ? 'Femenino' : ($gender == 2 ? 'Masculino' : 'Otro') }}</div>
          @endif

          <div class="mb-2">
            <strong>Créditos</strong><br>
            {{ count($tmdbFilmography ?? []) }} películas
          </div>
        </div>
      </div>

      {{-- External links --}}
      <div class="mt-3 d-flex flex-column gap-1">
        <a href="https://www.themoviedb.org/person/{{ $actorTmdbId }}" target="_blank" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-box-arrow-up-right me-1"></i> TMDb
        </a>
        @if($imdbId)
        <a href="https://www.imdb.com/name/{{ $imdbId }}" target="_blank" class="btn btn-sm btn-outline-warning">
          <i class="bi bi-box-arrow-up-right me-1"></i> IMDb
        </a>
        @endif
      </div>
    </div>

    {{-- Main content --}}
    <div class="col-lg-9 col-md-8">

      {{-- Biography --}}
      @if($biography)
      <div class="card mb-4">
        <div class="card-body">
          <h5 class="card-title">Biografía</h5>
          <div class="biography-text">{!! nl2br(e($biography)) !!}</div>
        </div>
      </div>
      @endif

      {{-- Films in our catalog --}}
      @if(!empty($localFilms))
      <h5 class="mb-3"><i class="bi bi-camera-reels me-1"></i> En nuestro catálogo ({{ $localCount }})</h5>
      <div class="row g-3 mb-5">
        @foreach($localFilms as $film)
        <div class="col-6 col-md-4 col-lg-3">
          <a href="/films/{{ $film->slug }}" class="text-decoration-none">
            <div class="card h-100 border-0 shadow-sm film-card">
              @if($film->poster_path)
                <img src="{{ film_poster_url($film->poster_path) }}" alt="{{ $film->title }}" class="card-img-top" style="aspect-ratio:2/3;object-fit:cover;">
              @else
                <div class="card-img-top bg-dark d-flex align-items-center justify-content-center" style="aspect-ratio:2/3;color:#888;">
                  <i class="bi bi-camera-reels" style="font-size:2rem;"></i>
                </div>
              @endif
              @if($film->tmdb_rating)
                <span class="position-absolute top-0 end-0 m-2 badge bg-warning text-dark fw-bold" style="font-size:0.75rem;">{{ number_format($film->tmdb_rating, 1) }}</span>
              @endif
              <div class="card-body p-2">
                <h6 class="card-title mb-1" style="font-size:0.85rem;">{{ $film->title }}</h6>
                <small>{{ $film->year ?? '' }}</small>
                @php
                  $character = '';
                  foreach($film->getCast(0) as $a) {
                    if (($a['tmdb_id'] ?? 0) == $actorTmdbId) { $character = $a['character'] ?? ''; break; }
                  }
                @endphp
                @if($character)
                  <br><small style="color:#0d6efd !important;font-size:0.75rem;">como {{ $character }}</small>
                @endif
              </div>
            </div>
          </a>
        </div>
        @endforeach
      </div>
      @endif

      {{-- Full TMDb filmography --}}
      @if(!empty($tmdbFilmography))
      <h5 class="mb-3"><i class="bi bi-list-ul me-1"></i> Filmografía completa ({{ count($tmdbFilmography) }})</h5>
      <div class="card">
        <div class="card-body p-0">
          <div style="max-height:600px;overflow-y:auto;">
            @foreach($tmdbFilmography as $credit)
            @php
              $clickable = $credit['in_catalog'] || ($apiEnabled ?? false);
              $tag = $clickable ? 'a' : 'div';
              $href = $clickable ? '/films/ver/' . $credit['tmdb_id'] : '';
            @endphp
            <{{ $tag }} {!! $clickable ? 'href="' . $href . '"' : '' !!} class="filmography-row d-flex align-items-center px-3 text-decoration-none" style="display:flex !important;{{ $clickable ? '' : 'cursor:default;opacity:0.6;' }}">
              <span class="filmography-year">{{ $credit['year'] ?: '—' }}</span>
              @if($credit['poster_path'])
                <img src="https://image.tmdb.org/t/p/w92{{ $credit['poster_path'] }}" alt="" class="filmography-poster me-2">
              @else
                <div class="filmography-poster me-2 bg-light d-flex align-items-center justify-content-center">
                  <i class="bi bi-film text-muted" style="font-size:0.7rem;"></i>
                </div>
              @endif
              <div class="flex-grow-1">
                <span class="fw-semibold" style="font-size:0.9rem;">{{ $credit['title'] }}</span>
                @if($credit['character'])
                  <br><small style="color:#888 !important;">como {{ $credit['character'] }}</small>
                @endif
              </div>
              @if($credit['vote_average'] > 0)
                <span class="badge bg-warning text-dark" style="font-size:0.7rem;">{{ number_format($credit['vote_average'], 1) }}</span>
              @endif
            </{{ $tag }}>
            @endforeach
          </div>
        </div>
      </div>
      @endif

    </div>
  </div>
</div>
@include('films/partials/_image_fallback')
@endsection
