@extends('layouts.app')

@section('content')



        <!-- slider Area Start-->
        <div class="slider-area ">
        <!-- Mobile Menu -->
        <div class="single-slider slider-height2 d-flex align-items-center" data-background="{{ asset('themes/default/img/hero/contact_hero.jpg') }}">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="hero-cap text-center">
                            <h2>List Pages</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- slider Area End-->
    <div class="page-content container mt-5">
    <div class="content">
    @if(count($pages) > 0)
        <div class="row">
            @foreach($pages as $page)
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">{{ $page->title }}</h5>
                            <p class="card-text text-muted">
                                Actualizado: {{ date('d/m/Y', strtotime($page->updated_at)) }}
                            </p>
                            <a href="/p/{{ $page->slug }}" class="btn btn-primary">Ver página</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <p>No hay páginas disponibles.</p>
    @endif
</div>
</div>
@endsection