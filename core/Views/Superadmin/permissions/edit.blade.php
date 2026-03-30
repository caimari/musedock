@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-4">{{ $title }}</h2>
        <a href="/musedock/permissions" class="btn btn-secondary">Volver a permisos</a>
    </div>

    @php
        // Verificar si multitenencia está activada
        $multiTenantEnabled = \Screenart\Musedock\Env::get('MULTI_TENANT_ENABLED', null);
        if ($multiTenantEnabled === null) {
            $multiTenantEnabled = setting('multi_tenant_enabled', false);
        }
    @endphp

    @include('partials.alerts')

    <div class="alert alert-info mb-4">
        <h6 class="alert-heading"><i class="bi bi-info-circle me-2"></i>¿Qué es un permiso?</h6>
        <p class="mb-2">Los permisos definen qué acciones pueden realizar los usuarios. Se asignan a <strong>Roles</strong>, y los roles se asignan a usuarios.</p>
        <hr>
        <p class="mb-0 small">
            <strong>Slug:</strong> Identificador técnico usado por el sistema (ej: <code>blog.create</code>)<br>
            <strong>Nombre:</strong> Descripción legible para humanos (ej: <code>Crear posts</code>)
        </p>
    </div>

    <div class="card">
        <div class="card-header bg-primary text-white">
            <strong>Editar permiso: {{ $permission['slug'] ?? $permission['name'] }}</strong>
        </div>
        <div class="card-body">
            <form method="POST" action="/musedock/permissions/{{ $permission['id'] }}/update">
                {!! csrf_field() !!}
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Slug del permiso <span class="text-danger">*</span></label>
                        <input type="text" name="slug" id="slug" class="form-control"
                               value="{{ $permission['slug'] ?? '' }}"
                               placeholder="Ej: blog.create" required
                               pattern="^[a-z0-9]+(\.[a-z0-9]+)*$" title="Formato: recurso.acción (solo minúsculas, números y puntos)">
                        <small class="text-muted">Formato: <code>recurso.acción</code> (ej: users.create, blog.edit, pages.delete)</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombre legible <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control"
                               value="{{ $permission['name'] }}"
                               placeholder="Ej: Crear posts del blog" required>
                        <small class="text-muted">Nombre descriptivo que se mostrará en la interfaz</small>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Descripción</label>
                        <input type="text" name="description" class="form-control"
                               value="{{ $permission['description'] }}"
                               placeholder="Ej: Permite crear nuevos posts en el blog">
                        <small class="text-muted">Explicación detallada de qué permite hacer este permiso</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Categoría</label>
                        <input type="text" name="category" class="form-control"
                               value="{{ $permission['category'] }}"
                               placeholder="Ej: Blog, Usuarios, Contenido" list="categories">
                        <datalist id="categories">
                            <option value="Usuarios">
                            <option value="Contenido">
                            <option value="Blog">
                            <option value="Media">
                            <option value="Configuración">
                            <option value="Apariencia">
                            <option value="Sistema">
                        </datalist>
                        <small class="text-muted">Agrupa permisos por sección para facilitar su asignación a roles</small>
                    </div>
                </div>
                @if($multiTenantEnabled)
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tenant</label>
                        <select name="tenant_id" class="form-control">
                            <option value="" {{ $permission['tenant_id'] === null ? 'selected' : '' }}>Global (disponible para todos)</option>
                            @foreach ($tenants as $tenant)
                                <option value="{{ $tenant['id'] }}" {{ $permission['tenant_id'] == $tenant['id'] ? 'selected' : '' }}>
                                    {{ $tenant['name'] }}
                                    @if(!empty($tenant['domain']))
                                        ({{ $tenant['domain'] }})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Los permisos globales están disponibles para asignar en cualquier tenant</small>
                    </div>
                </div>
                @else
                <input type="hidden" name="tenant_id" value="">
                @endif
                <div class="mt-4 d-flex justify-content-end">
                    <a href="/musedock/permissions" class="btn btn-light me-2">Cancelar</a>
                    <button type="submit" class="btn btn-success">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
