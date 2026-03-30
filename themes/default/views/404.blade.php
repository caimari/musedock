@extends('layouts.app')

@section('title')
    404 - Página no encontrada | {{ site_setting('site_name', '') }}
@endsection

@section('robots')
    noindex, nofollow
@endsection

@section('content')
<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center py-5">
                <h1 class="display-1 fw-bold text-primary mb-3">404</h1>
                <h2 class="mb-3">Página no encontrada</h2>
                <p class="text-muted mb-4">Lo sentimos, la página que buscas no existe o ha sido movida.</p>
                <div class="d-flex justify-content-center gap-3">
                    <a href="{{ url('/') }}" class="btn btn-primary">Volver al inicio</a>
                    <a href="javascript:history.back()" class="btn btn-outline-secondary">Volver atrás</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
