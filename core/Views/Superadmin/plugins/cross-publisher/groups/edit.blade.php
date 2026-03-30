@extends('layouts.app')

@section('title', 'Editar Grupo: ' . $group->name . ' - Cross-Publisher')

@section('content')
<div class="container-fluid p-4">
    <div class="mb-4">
        <h1 class="h3 mb-1"><i class="bi bi-pencil-square me-2"></i>Editar Grupo: {{ $group->name }}</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/musedock/cross-publisher">Cross-Publisher</a></li>
                <li class="breadcrumb-item"><a href="/musedock/cross-publisher/groups">Grupos</a></li>
                <li class="breadcrumb-item active">{{ $group->name }}</li>
            </ol>
        </nav>
    </div>

    @include('partials.alerts-sweetalert2')

    <form method="POST" action="/musedock/cross-publisher/groups/{{ $group->id }}/update">
        {!! csrf_field() !!}
        <div class="row">
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header"><h5 class="card-title mb-0">Datos del Grupo</h5></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ $group->name }}" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Descripción</label>
                            <textarea class="form-control" id="description" name="description" rows="3">{{ $group->description }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label for="default_language" class="form-label">Idioma principal</label>
                            <select class="form-select" id="default_language" name="default_language">
                                @foreach(['es' => 'Español', 'en' => 'English', 'ca' => 'Català', 'fr' => 'Français', 'de' => 'Deutsch', 'it' => 'Italiano', 'pt' => 'Português'] as $code => $label)
                                <option value="{{ $code }}" {{ ($group->default_language ?? 'es') === $code ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="auto_sync_enabled" name="auto_sync_enabled" value="1" {{ $group->auto_sync_enabled ? 'checked' : '' }}>
                            <label class="form-check-label" for="auto_sync_enabled">Auto-sync activado</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Miembros del Grupo</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">Selecciona los tenants que pertenecen a este grupo editorial.</p>
                        <div style="max-height: 400px; overflow-y: auto;">
                            @foreach($availableTenants as $tenant)
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="members[]" value="{{ $tenant->id }}" id="tenant_{{ $tenant->id }}"
                                    {{ in_array($tenant->id, array_column((array)$members, 'id')) ? 'checked' : '' }}>
                                <label class="form-check-label" for="tenant_{{ $tenant->id }}">
                                    <strong>{{ $tenant->name }}</strong>
                                    <small class="text-muted d-block">{{ $tenant->domain }}</small>
                                </label>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i>Guardar Cambios
            </button>
            <a href="/musedock/cross-publisher/groups" class="btn btn-outline-secondary">Volver</a>
        </div>
    </form>
</div>
@endsection
