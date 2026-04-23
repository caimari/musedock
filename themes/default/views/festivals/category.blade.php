@extends('layouts.app')

@section('title', ($category->seo_title ?: $category->name . ' — Festivales') . ' | ' . site_setting('site_name', 'FestivalNews'))
@section('description', $category->seo_description ?: 'Festivales en la categoría ' . $category->name)

@section('content')
<div class="container py-5">

  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="/">Inicio</a></li>
      <li class="breadcrumb-item"><a href="/festivals">Festivales</a></li>
      <li class="breadcrumb-item active">{{ $category->name }}</li>
    </ol>
  </nav>

  <div class="mb-4">
    <h1 class="h3">{{ $title }}</h1>
    @if($category->description)
      <p class="text-muted">{{ $category->description }}</p>
    @endif
    <p class="text-muted small">{{ $pagination['total_posts'] ?? 0 }} festivales</p>
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
    <p class="text-muted">No se encontraron festivales en esta categoría.</p>
    <a href="/festivals" class="btn btn-outline-primary">Ver todos los festivales</a>
  </div>
  @endif

</div>
@endsection
