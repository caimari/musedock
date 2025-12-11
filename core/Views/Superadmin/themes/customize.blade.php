@extends('layouts.app')

@section('content')
<div class="container">
    {{-- Breadcrumb --}}
    <div class="breadcrumb mb-3">
        <a href="{{ url('/musedock') }}">Panel</a>
        <span class="mx-2">/</span>
        <a href="{{ url('/musedock/themes') }}">Temas</a>
        <span class="mx-2">/</span>
        <span>{{ ucfirst($theme['name'] ?? $theme['slug']) }}</span>
    </div>

    <h1 class="mb-4">Editor de Tema: {{ ucfirst($theme['name'] ?? $theme['slug']) }}</h1>

    <p class="text-muted">Puedes editar los siguientes archivos del tema:</p>

    <ul class="list-group mb-4">
        @forelse ($editableFiles as $file)
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span>{{ $file['label'] }}</span>
                <a href="{{ url('/musedock/theme-editor/' . $theme['slug'] . '/edit?file=' . urlencode($file['relative'])) }}" class="btn btn-sm btn-outline-secondary">Editor de código</a>
            </li>
        @empty
            <li class="list-group-item text-muted">No se encontraron archivos editables en este tema.</li>
        @endforelse
    </ul>

    <a href="{{ url('/musedock/themes') }}" class="btn btn-secondary">← Volver</a>
</div>
@endsection
