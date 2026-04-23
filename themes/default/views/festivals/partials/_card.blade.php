<div class="card h-100 shadow-sm festival-card">
  <a href="{{ festival_url($festival->slug) }}" class="text-decoration-none">
    @if($festival->featured_image || $festival->cover_image)
      <img src="{{ $festival->featured_image ?: $festival->cover_image }}" class="card-img-top" alt="{{ $festival->name }}"
           style="height:200px;object-fit:cover;" loading="lazy">
    @else
      <div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height:200px;">
        <i class="bi bi-film" style="font-size:3rem;color:#dee2e6"></i>
      </div>
    @endif
  </a>
  <div class="card-body d-flex flex-column">
    <div class="mb-2">
      @if($festival->status === 'verified' || $festival->status === 'claimed')
        <span class="badge bg-primary"><i class="bi bi-patch-check-fill me-1"></i>Verificado</span>
      @endif
      @if($festival->submission_status === 'open')
        <span class="badge bg-success">Submissions abiertas</span>
      @endif
      @if($festival->featured)
        <span class="badge bg-warning text-dark"><i class="bi bi-star-fill me-1"></i></span>
      @endif
    </div>

    <h5 class="card-title mb-1">
      <a href="{{ festival_url($festival->slug) }}" class="text-decoration-none text-dark">{{ $festival->name }}</a>
    </h5>

    <p class="text-muted small mb-2">
      <i class="bi bi-geo-alt me-1"></i>{{ $festival->city ? $festival->city . ', ' : '' }}{{ $festival->country }}
    </p>

    @if(isset($festivalCategories[$festival->id]))
      <div class="mb-2">
        @foreach($festivalCategories[$festival->id] as $cat)
          <a href="{{ festival_url($cat['slug'], 'category') }}" class="badge rounded-pill text-decoration-none" style="background:{{ $cat['color'] ?? '#6c757d' }};font-size:0.7rem">{{ $cat['name'] }}</a>
        @endforeach
      </div>
    @endif

    @if($festival->short_description)
      <p class="card-text small text-muted flex-grow-1">{{ mb_substr($festival->short_description, 0, 120) }}{{ mb_strlen($festival->short_description) > 120 ? '...' : '' }}</p>
    @endif

    @if($festival->start_date)
      <p class="small text-muted mb-2">
        <i class="bi bi-calendar-event me-1"></i>{{ date('d M Y', strtotime($festival->start_date)) }}
        @if($festival->end_date) — {{ date('d M Y', strtotime($festival->end_date)) }}@endif
      </p>
    @endif

    <div class="mt-auto">
      <a href="{{ festival_url($festival->slug) }}" class="btn btn-outline-primary btn-sm w-100">Ver perfil</a>
    </div>
  </div>
</div>
