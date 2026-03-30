@extends('layouts.app')

@section('title', 'Nuevo Grupo Editorial - Cross-Publisher')

@section('content')
<div class="container-fluid p-4">
    <div class="mb-4">
        <h1 class="h3 mb-1"><i class="bi bi-plus-circle me-2"></i>Nuevo Grupo Editorial</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/musedock/cross-publisher">Cross-Publisher</a></li>
                <li class="breadcrumb-item"><a href="/musedock/cross-publisher/groups">Grupos</a></li>
                <li class="breadcrumb-item active">Nuevo</li>
            </ol>
        </nav>
    </div>

    @include('partials.alerts-sweetalert2')

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="/musedock/cross-publisher/groups">
                        {!! csrf_field() !!}
                        <div class="mb-3">
                            <label for="name" class="form-label">Nombre del grupo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required placeholder="Ej: Red CulturaPost, Grupo Editorial X...">
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Descripción</label>
                            <textarea class="form-control" id="description" name="description" rows="3" placeholder="Descripción opcional del grupo editorial"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="default_language" class="form-label">Idioma principal</label>
                            <select class="form-select" id="default_language" name="default_language">
                                <option value="es">Español</option>
                                <option value="en">English</option>
                                <option value="ca">Català</option>
                                <option value="fr">Français</option>
                                <option value="de">Deutsch</option>
                                <option value="it">Italiano</option>
                                <option value="pt">Português</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="auto_sync_enabled" name="auto_sync_enabled" value="1">
                                <label class="form-check-label" for="auto_sync_enabled">
                                    Auto-sync activado para este grupo
                                </label>
                            </div>
                            <small class="text-muted">Si está activo, los cambios en posts fuente se sincronizarán automáticamente a los destinos.</small>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Crear Grupo
                            </button>
                            <a href="/musedock/cross-publisher/groups" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
