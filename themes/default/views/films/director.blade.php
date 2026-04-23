@extends('layouts.app')

@section('title', $title . ' | ' . site_setting('site_name', 'IberoFilms'))

@section('content')
<div class="container py-5">
  <div class="text-center mb-5">
    <h1 class="display-5 fw-bold">{{ $title }}</h1>
    <p class="lead text-muted">{{ $pagination['total_posts'] ?? 0 }} películas</p>
  </div>

  @if(!empty($films) && count($films) > 0)
  <div class="row g-4">
    @foreach($films as $film)
    <div class="col-6 col-md-4 col-lg-3">
      <a href="/films/{{ $film->slug }}" class="text-decoration-none">
        <div class="card h-100 border-0 shadow-sm film-card">
          @if($film->poster_path)
            <img src="{{ film_poster_url($film->poster_path) }}" alt="{{ $film->title }}" class="card-img-top" style="aspect-ratio:2/3;object-fit:cover;">
          @else
            <div class="card-img-top bg-dark d-flex align-items-center justify-content-center text-muted" style="aspect-ratio:2/3;"><i class="bi bi-camera-reels" style="font-size:2rem;"></i></div>
          @endif
          <div class="card-body p-2">
            <h6 class="card-title mb-1 text-dark" style="font-size:0.9rem;">{{ $film->title }}</h6>
            <small class="text-muted">{{ $film->year ?? '' }}</small>
          </div>
        </div>
      </a>
    </div>
    @endforeach
  </div>

  @if(($pagination['total_pages'] ?? 1) > 1)
  <nav class="mt-5 d-flex justify-content-center">
    <ul class="pagination">
      @for($p = 1; $p <= $pagination['total_pages']; $p++)
        <li class="page-item {{ $p == $pagination['current_page'] ? 'active' : '' }}">
          <a class="page-link" href="/films/director/{{ $directorSlug }}?page={{ $p }}">{{ $p }}</a>
        </li>
      @endfor
    </ul>
  </nav>
  @endif

  @else
  <div class="text-center py-5 text-muted">
    <p>No se encontraron películas de este director.</p>
    <a href="/films" class="btn btn-outline-primary">Ver todas las películas</a>
  </div>
  @endif
</div>

<style>
.film-card { transition: transform 0.2s; overflow: hidden; border-radius: 8px; }
.film-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important; }
</style>
@include('films/partials/_image_fallback')
@endsection
