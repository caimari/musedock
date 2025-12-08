@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="mb-4">Editor de Tema: {{ ucfirst($theme['name'] ?? $theme['slug']) }}</h1>

    <p class="text-muted">Puedes editar los siguientes archivos del tema:</p>

    <ul class="list-group mb-4">
        @forelse ($editableFiles as $file)
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span>{{ $file['label'] }}</span>
                <div class="d-flex gap-2">
                    <a href="{{ url('/musedock/theme-editor/' . $theme['slug'] . '/edit?file=' . urlencode($file['relative'])) }}" class="btn btn-sm btn-outline-secondary">Editor de código</a>
                    <a href="{{ url('/musedock/theme-editor/' . $theme['slug'] . '/builder?file=' . urlencode($file['relative'])) }}" class="btn btn-sm btn-outline-primary">Editor visual</a>
                </div>
            </li>
        @empty
            <li class="list-group-item text-muted">No se encontraron archivos editables en este tema.</li>
        @endforelse
    </ul>

    <a href="{{ url('/musedock/themes') }}" class="btn btn-secondary">← Volver</a>
</div>
@endsection
