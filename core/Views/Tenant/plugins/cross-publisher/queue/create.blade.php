@extends('layouts.app')

@section('title', 'Publicar en la red - Cross-Publisher')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        @include('plugins.cross-publisher._nav', ['activeTab' => 'queue'])

        {{-- Flash Messages --}}
        @if(session('flash_error'))
            <div class="alert alert-danger alert-dismissible fade show">
                {{ session('flash_error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Publicar en la red</h4>
            <a href="{{ admin_url('/plugins/cross-publisher/queue') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>

        @if(empty($targetTenants))
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                No hay tenants disponibles en tu red editorial.
                <a href="{{ admin_url('/plugins/cross-publisher/network') }}" class="alert-link">
                    Configurar red
                </a>
            </div>
        @else
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form action="{{ admin_url('/plugins/cross-publisher/queue') }}" method="POST">
                        @csrf
                        {{-- Select Post --}}
                        <div class="mb-4">
                            <label for="post_id" class="form-label">Seleccionar post *</label>
                            <select class="form-select" id="post_id" name="post_id" required>
                                <option value="">-- Seleccionar --</option>
                                @foreach($posts as $post)
                                    <option value="{{ $post->id }}">
                                        {{ $post->title }}
                                        ({{ date('d/m/Y', strtotime($post->published_at)) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Select Targets --}}
                        <div class="mb-4">
                            <label class="form-label">Seleccionar destinos *</label>
                            <div class="row">
                                @foreach($targetTenants as $tenant)
                                    <div class="col-md-6 mb-2">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input"
                                                   id="target_{{ $tenant->tenant_id }}"
                                                   name="target_tenant_ids[]"
                                                   value="{{ $tenant->tenant_id }}">
                                            <label class="form-check-label" for="target_{{ $tenant->tenant_id }}">
                                                <strong>{{ $tenant->tenant_name }}</strong>
                                                <br><small class="text-muted">
                                                    {{ $tenant->domain }}
                                                    <span class="badge bg-secondary">{{ strtoupper($tenant->default_language) }}</span>
                                                </small>
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Translation Options --}}
                        <div class="mb-4">
                            <div class="card bg-light border-0">
                                <div class="card-body">
                                    <h6 class="card-title">Opciones de traducción</h6>

                                    <div class="form-check mb-2">
                                        <input type="checkbox" class="form-check-input" id="translate" name="translate">
                                        <label class="form-check-label" for="translate">
                                            Traducir contenido
                                        </label>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <label for="target_language" class="form-label">Idioma destino</label>
                                            <select class="form-select" id="target_language" name="target_language">
                                                <option value="es">Español</option>
                                                <option value="en">English</option>
                                                <option value="ca">Català</option>
                                                <option value="fr">Français</option>
                                                <option value="de">Deutsch</option>
                                                <option value="it">Italiano</option>
                                                <option value="pt">Português</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send"></i> Añadir a la cola
                            </button>
                            <a href="{{ admin_url('/plugins/cross-publisher/queue') }}" class="btn btn-outline-secondary">
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
