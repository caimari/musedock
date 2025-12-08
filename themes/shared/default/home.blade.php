@extends('layouts.base')

@section('title', $title ?? 'Inicio')

@section('content')
    <div class="container mt-5 text-center">
        <h1>Bienvenido a {{ $title ?? 'MuseDock CMS' }}</h1>
        <p>Este es el tema del tenant 1.</p>
    </div>
@endsection
