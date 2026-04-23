{{-- Film Library: Home Carousel / Cartelera --}}
@if(!empty($homeFilms))
<div class="film-home-carousel py-5" style="background:#0d0d0d;">
  <div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="fw-bold mb-0" style="color:#fff;">{{ $homeCarouselTitle ?? 'Cartelera' }}</h2>
      <a href="/films" class="btn btn-sm btn-outline-light">Ver todo</a>
    </div>
    <div class="film-carousel-scroll">
      @foreach($homeFilms as $film)
      <a href="/films/{{ $film->slug }}" class="film-carousel-item text-decoration-none">
        <div class="film-carousel-card">
          @if($film->poster_path)
            <img src="{{ film_poster_url($film->poster_path, 'w342') }}" alt="{{ $film->title }}" class="film-carousel-poster">
          @else
            <div class="film-carousel-poster film-carousel-no-poster d-flex align-items-center justify-content-center">
              <i class="bi bi-camera-reels" style="font-size:2rem;color:#555;"></i>
            </div>
          @endif
          @if($film->tmdb_rating)
            <span class="film-carousel-rating">{{ number_format($film->tmdb_rating, 1) }}</span>
          @endif
          <div class="film-carousel-info">
            <p class="film-carousel-title">{{ $film->title }}</p>
            <span class="film-carousel-meta">{{ $film->year ?? '' }}{{ $film->director ? ' · ' . $film->director : '' }}</span>
          </div>
        </div>
      </a>
      @endforeach
    </div>
  </div>
</div>

<style>
.film-carousel-scroll {
  display: flex;
  gap: 12px;
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: thin;
  scrollbar-color: #444 transparent;
  padding-bottom: 8px;
}
.film-carousel-scroll::-webkit-scrollbar { height: 6px; }
.film-carousel-scroll::-webkit-scrollbar-track { background: transparent; }
.film-carousel-scroll::-webkit-scrollbar-thumb { background: #444; border-radius: 3px; }
.film-carousel-item {
  flex: 0 0 auto;
  scroll-snap-align: start;
}
.film-carousel-card {
  width: 160px;
  border-radius: 8px;
  overflow: hidden;
  position: relative;
  transition: transform 0.2s;
}
.film-carousel-card:hover {
  transform: translateY(-4px);
}
.film-carousel-poster {
  width: 160px;
  height: 240px;
  object-fit: cover;
  display: block;
}
.film-carousel-no-poster {
  background: #1a1a1a;
}
.film-carousel-rating {
  position: absolute;
  top: 6px;
  right: 6px;
  background: #f5c518;
  color: #000;
  font-weight: 700;
  font-size: 0.7rem;
  padding: 2px 6px;
  border-radius: 4px;
}
.film-carousel-info {
  padding: 6px 4px;
}
.film-carousel-title {
  color: #fff;
  font-size: 0.8rem;
  font-weight: 600;
  line-height: 1.2;
  margin: 0 0 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.film-carousel-meta {
  color: #888;
  font-size: 0.7rem;
}
@media (max-width: 768px) {
  .film-carousel-card { width: 130px; }
  .film-carousel-poster { width: 130px; height: 195px; }
}
</style>
@endif
