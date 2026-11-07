@php
    // Textos en el idioma del sitio (inglés si es «en»; español en cualquier otro caso, como hasta ahora)
    $__en = function_exists('detectLanguage') && detectLanguage() === 'en';
    $__t = $__en
        ? ['title' => '404 - Page not found', 'h' => 'Page not found', 'p' => 'Sorry, the page you are looking for does not exist or has been moved.', 'home' => 'Back to home', 'back' => 'Go back']
        : ['title' => '404 - Página no encontrada', 'h' => 'Página no encontrada', 'p' => 'Lo sentimos, la página que buscas no existe o ha sido movida.', 'home' => 'Volver al inicio', 'back' => 'Volver atrás'];
@endphp
@extends('layouts.app')

@section('title')
    {{ $__t['title'] }} | {{ site_setting('site_name', '') }}
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
                <h2 class="mb-3">{{ $__t['h'] }}</h2>
                <p class="text-muted mb-4">{{ $__t['p'] }}</p>
                <div class="d-flex justify-content-center gap-3">
                    <a href="{{ url('/') }}" class="btn btn-primary">{{ $__t['home'] }}</a>
                    <a href="javascript:history.back()" class="btn btn-outline-secondary">{{ $__t['back'] }}</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
