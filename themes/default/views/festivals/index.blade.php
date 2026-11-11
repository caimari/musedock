@extends('layouts.app')

@section('title', $title . ' | ' . site_setting('site_name', 'Festivales'))
@section('description', fd_t('Descubre festivales de cine, música, artes y más. Directorio completo con información actualizada.'))

@section('content')
@include('festivals/partials/_assets')
<div class="container py-5 fd-scope">

  {{-- Header --}}
  <div class="text-center mb-5">
    <h1 class="display-5 fw-bold">{{ fd_t('Directorio de Festivales') }}</h1>
    <p class="lead text-muted">{{ fd_t('Descubre festivales de todo el mundo') }}</p>
  </div>

  {{-- Filters --}}
  <div class="card mb-4">
    <div class="card-body py-3">
      <form method="GET" action="{{ fd_path('/festivals') }}" class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label small text-muted">{{ fd_t('Tipo') }}</label>
          <select name="type" class="form-select form-select-sm">
            <option value="">{{ fd_t('Todos los tipos') }}</option>
            @foreach($types as $key => $label)
              <option value="{{ $key }}" {{ $typeFilter === $key ? 'selected' : '' }}>{{ fd_t($label) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small text-muted">{{ fd_t('País') }}</label>
          <select name="country" class="form-select form-select-sm">
            <option value="">{{ fd_t('Todos los países') }}</option>
            @foreach($countries as $c)
              <option value="{{ $c }}" {{ $countryFilter === $c ? 'selected' : '' }}>{{ $c }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small text-muted">{{ fd_t('Submissions') }}</label>
          <select name="submissions" class="form-select form-select-sm">
            <option value="">{{ fd_t('Todos') }}</option>
            <option value="open" {{ $submissionFilter === 'open' ? 'selected' : '' }}>{{ fd_t('Abiertos') }}</option>
            <option value="upcoming" {{ $submissionFilter === 'upcoming' ? 'selected' : '' }}>{{ fd_t('Próximamente') }}</option>
            <option value="closed" {{ $submissionFilter === 'closed' ? 'selected' : '' }}>{{ fd_t('Cerrados') }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-primary btn-sm w-100">{{ fd_t('Filtrar') }}</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Results count --}}
  <p class="text-muted mb-3">{{ $pagination['total_posts'] ?? 0 }} {{ fd_t('festivales encontrados') }}</p>

  {{-- Festival Grid --}}
  @if(!empty($festivals) && count($festivals) > 0)
  <div class="row g-4">
    @foreach($festivals as $festival)
    <div class="col-md-6 col-lg-4">
      <div class="card h-100 shadow-sm festival-card">
        {{-- Image --}}
        <a href="{{ festival_url($festival->slug) }}" class="text-decoration-none">
          {!! \FestivalDirectory\Services\Shortcode::media([
              'name' => $festival->name,
              'logo' => $festival->logo,
              'logo_bg' => $festival->logo_bg ?? null,
              'featured_image' => $festival->featured_image,
              'cover_image' => $festival->cover_image,
          ]) !!}
        </a>

        <div class="card-body d-flex flex-column">
          {{-- Status badges --}}
          <div class="mb-2">
            @if($festival->status === 'verified' || $festival->status === 'claimed')
              <span class="badge bg-primary"><i class="bi bi-patch-check-fill me-1"></i>{{ fd_t('Verificado') }}</span>
            @endif
            @if($festival->submission_status === 'open')
              <span class="badge bg-success">{{ fd_t('Submissions abiertas') }}</span>
            @elseif($festival->submission_status === 'upcoming')
              <span class="badge bg-warning text-dark">{{ fd_t('Próximamente') }}</span>
            @endif
            @if($festival->featured)
              <span class="badge bg-warning text-dark"><i class="bi bi-star-fill me-1"></i>{{ fd_t('Destacado') }}</span>
            @endif
          </div>

          {{-- Title --}}
          <h5 class="card-title mb-1">
            <a href="{{ festival_url($festival->slug) }}" class="text-decoration-none text-dark">{{ $festival->name }}</a>
          </h5>

          {{-- Location --}}
          <p class="text-muted small mb-2">
            <i class="bi bi-geo-alt me-1"></i>{{ $festival->city ? $festival->city . ', ' : '' }}{{ $festival->country }}
          </p>

          {{-- Categories --}}
          @if(isset($festivalCategories[$festival->id]))
            <div class="mb-2">
              @foreach($festivalCategories[$festival->id] as $cat)
                <a href="{{ festival_url($cat['slug'], 'category') }}" class="badge rounded-pill text-decoration-none" style="background:{{ $cat['color'] ?? '#6c757d' }};font-size:0.7rem">{{ fd_t($cat['name']) }}</a>
              @endforeach
            </div>
          @endif

          {{-- Description --}}
          @php $__short = fd_tr($festival, 'short_description'); @endphp
          @if($__short)
            <p class="card-text small text-muted flex-grow-1">{{ mb_substr($__short, 0, 150) }}{{ mb_strlen($__short) > 150 ? '...' : '' }}</p>
          @endif

          {{-- Dates --}}
          @if($festival->start_date)
            <p class="small text-muted mb-2">
              <i class="bi bi-calendar-event me-1"></i>
              {{ date('d M Y', strtotime($festival->start_date)) }}
              @if($festival->end_date)
                — {{ date('d M Y', strtotime($festival->end_date)) }}
              @endif
            </p>
          @endif

          {{-- CTA --}}
          <div class="mt-auto">
            <a href="{{ festival_url($festival->slug) }}" class="btn btn-outline-primary btn-sm w-100">{{ fd_t('Ver perfil') }}</a>
          </div>
        </div>
      </div>
    </div>
    @endforeach
  </div>

  {{-- Pagination --}}
  @if(($pagination['total_pages'] ?? 1) > 1)
  <nav class="mt-5 d-flex justify-content-center">
    <ul class="pagination">
      @if($pagination['current_page'] > 1)
        <li class="page-item"><a class="page-link" href="?page={{ $pagination['current_page'] - 1 }}{{ !empty($typeFilter) ? '&type='.$typeFilter : '' }}{{ !empty($countryFilter) ? '&country='.$countryFilter : '' }}{{ !empty($submissionFilter) ? '&submissions='.$submissionFilter : '' }}">&laquo;</a></li>
      @endif
      @for($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++)
        <li class="page-item {{ $i === $pagination['current_page'] ? 'active' : '' }}">
          <a class="page-link" href="?page={{ $i }}{{ !empty($typeFilter) ? '&type='.$typeFilter : '' }}{{ !empty($countryFilter) ? '&country='.$countryFilter : '' }}{{ !empty($submissionFilter) ? '&submissions='.$submissionFilter : '' }}">{{ $i }}</a>
        </li>
      @endfor
      @if($pagination['current_page'] < $pagination['total_pages'])
        <li class="page-item"><a class="page-link" href="?page={{ $pagination['current_page'] + 1 }}{{ !empty($typeFilter) ? '&type='.$typeFilter : '' }}{{ !empty($countryFilter) ? '&country='.$countryFilter : '' }}{{ !empty($submissionFilter) ? '&submissions='.$submissionFilter : '' }}">&raquo;</a></li>
      @endif
    </ul>
  </nav>
  @endif

  @else
  <div class="text-center py-5">
    <i class="bi bi-film" style="font-size:4rem;color:#dee2e6"></i>
    <p class="text-muted mt-3">{{ fd_t('No se encontraron festivales con los filtros seleccionados.') }}</p>
    <a href="{{ fd_path('/festivals') }}" class="btn btn-outline-primary">{{ fd_t('Ver todos') }}</a>
  </div>
  @endif

</div>
@endsection
