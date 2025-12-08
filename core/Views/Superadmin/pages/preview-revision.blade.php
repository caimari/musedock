@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="app-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>{{ $title }}</h2>
            <div>
                <a href="/musedock/pages/{{ $page->id }}/revisions" class="btn btn-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Volver a revisiones
                </a>
                <form method="POST" action="/musedock/pages/{{ $page->id }}/revisions/{{ $revision->id }}/restore" style="display:inline;" onsubmit="return confirm('¿Restaurar a esta versión?');">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-arrow-counterclockwise"></i> Restaurar esta versión
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h3 class="mb-3">{{ e($revision->title) }}</h3>
                <hr>
                <div class="revision-content">
                    {!! $revision->content !!}
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <h6 class="text-muted">Metadatos de la revisión</h6>
                <ul class="list-unstyled small text-muted mb-0">
                    <li><strong>Tipo:</strong> {{ ucfirst($revision->revision_type) }}</li>
                    <li><strong>Resumen:</strong> {{ $revision->summary }}</li>
                    <li><strong>Fecha:</strong> {{ date('d/m/Y H:i:s', strtotime($revision->created_at)) }}</li>
                    @if($revision->user_agent)
                    <li><strong>Navegador:</strong> {{ $revision->user_agent }}</li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
