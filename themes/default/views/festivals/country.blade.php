@extends('layouts.app')

@section('title', $title . ' | ' . site_setting('site_name', 'Festivales'))
@section('description', fd_t('Descubre los festivales en ') . $countryName . fd_t('. Directorio completo y actualizado.'))

@section('content')
@include('festivals/partials/_assets')
<div class="container py-5 fd-scope">

  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="{{ fd_path('/') }}">{{ fd_t('Inicio') }}</a></li>
      <li class="breadcrumb-item"><a href="{{ fd_path('/festivals') }}">{{ fd_t('Festivales') }}</a></li>
      <li class="breadcrumb-item active">{{ $countryName }}</li>
    </ol>
  </nav>

  <div class="mb-4">
    <h1 class="h3"><i class="bi bi-geo-alt me-2"></i>{{ $title }}</h1>
    <p class="text-muted small">{{ $pagination['total_posts'] ?? 0 }} {{ fd_t('festivales') }}</p>
  </div>

  @if(!empty($festivals) && count($festivals) > 0)
  <div class="row g-4">
    @foreach($festivals as $festival)
    <div class="col-md-6 col-lg-4">
      @include('festivals/partials/_card', ['festival' => $festival, 'festivalCategories' => $festivalCategories])
    </div>
    @endforeach
  </div>

  @if(($pagination['total_pages'] ?? 1) > 1)
  <nav class="mt-5 d-flex justify-content-center">
    <ul class="pagination">
      @if($pagination['current_page'] > 1)
        <li class="page-item"><a class="page-link" href="?page={{ $pagination['current_page'] - 1 }}">&laquo;</a></li>
      @endif
      @for($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++)
        <li class="page-item {{ $i === $pagination['current_page'] ? 'active' : '' }}">
          <a class="page-link" href="?page={{ $i }}">{{ $i }}</a>
        </li>
      @endfor
      @if($pagination['current_page'] < $pagination['total_pages'])
        <li class="page-item"><a class="page-link" href="?page={{ $pagination['current_page'] + 1 }}">&raquo;</a></li>
      @endif
    </ul>
  </nav>
  @endif
  @else
  <div class="text-center py-5">
    <p class="text-muted">{{ fd_t('No se encontraron festivales en {c}.', ['c' => $countryName]) }}</p>
    <a href="{{ fd_path('/festivals') }}" class="btn btn-outline-primary">{{ fd_t('Ver todos los festivales') }}</a>
  </div>
  @endif

</div>
@endsection
