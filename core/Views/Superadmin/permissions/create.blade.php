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

        // Filtrar slugs sugeridos que no existen aún
        $availableSlugs = array_diff($suggestedSlugs ?? [], $existingSlugs ?? []);
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

    {{-- Mostrar sugerencias si hay slugs del código disponibles --}}
    @if(!empty($availableSlugs))
    <div class="alert alert-success mb-4">
        <h6 class="alert-heading"><i class="bi bi-lightbulb me-2"></i>Slugs detectados en el código</h6>
        <p class="mb-2">Estos permisos están usados en controladores pero aún no existen. Haz clic para seleccionar:</p>
        <div class="d-flex flex-wrap gap-2">
            @foreach($availableSlugs as $slug)
                <button type="button" class="btn btn-sm btn-outline-success slug-suggestion" data-slug="{{ $slug }}">
                    {{ $slug }}
                </button>
            @endforeach
        </div>
    </div>
    @endif

    <div class="card">
        <div class="card-header bg-primary text-white">
            <strong>Crear nuevo permiso</strong>
        </div>
        <div class="card-body">
            <form method="POST" action="/musedock/permissions/store">
                {!! csrf_field() !!}
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Slug del permiso <span class="text-danger">*</span></label>
                        <input type="text" name="slug" id="slug" class="form-control" placeholder="Ej: blog.create" required
                               pattern="^[a-z0-9]+(\.[a-z0-9]+)*$" title="Formato: recurso.acción (solo minúsculas, números y puntos)"
                               list="slugSuggestions">
                        <datalist id="slugSuggestions">
                            @foreach($suggestedSlugs ?? [] as $slug)
                                @if(!in_array($slug, $existingSlugs ?? []))
                                    <option value="{{ $slug }}">
                                @endif
                            @endforeach
                        </datalist>
                        <small class="text-muted">Formato: <code>recurso.acción</code> (ej: users.create, blog.edit, pages.delete)</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombre legible <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" placeholder="Ej: Crear posts del blog" required>
                        <small class="text-muted">Nombre descriptivo que se mostrará en la interfaz</small>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Descripción</label>
                        <input type="text" name="description" class="form-control" placeholder="Ej: Permite crear nuevos posts en el blog">
                        <small class="text-muted">Explicación detallada de qué permite hacer este permiso</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Categoría</label>
                        <input type="text" name="category" class="form-control" placeholder="Ej: Blog, Usuarios, Contenido" list="categories">
                        <datalist id="categories">
                            <option value="Usuarios">
                            <option value="Contenido">
                            <option value="Blog">
                            <option value="Media">
                            <option value="Configuración">
                            <option value="Apariencia">
                            <option value="Sistema">
                            <option value="Soporte">
                        </datalist>
                        <small class="text-muted">Agrupa permisos por sección para facilitar su asignación a roles</small>
                    </div>
                </div>
                @if($multiTenantEnabled)
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tenant</label>
                        <select name="tenant_id" class="form-control">
                            <option value="">Global (disponible para todos)</option>
                            @foreach ($tenants as $tenant)
                                <option value="{{ $tenant['id'] }}">
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
                    <button type="submit" class="btn btn-success">Guardar permiso</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Auto-rellenar slug y generar nombre legible al hacer clic en sugerencias
document.querySelectorAll('.slug-suggestion').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var slug = this.dataset.slug;
        document.getElementById('slug').value = slug;

        // Generar nombre legible desde el slug
        var parts = slug.split('.');
        var actions = {
            'view': 'Ver', 'create': 'Crear', 'edit': 'Editar',
            'delete': 'Eliminar', 'manage': 'Gestionar', 'reply': 'Responder',
            'update': 'Actualizar'
        };
        var resources = {
            'users': 'usuarios', 'pages': 'páginas', 'posts': 'posts',
            'media': 'archivos', 'settings': 'configuración', 'tickets': 'tickets',
            'logs': 'logs', 'modules': 'módulos', 'languages': 'idiomas',
            'themes': 'temas', 'menus': 'menús', 'appearance': 'apariencia',
            'advanced': 'avanzado', 'cron': 'tareas programadas', 'ai': 'IA'
        };

        if (parts.length === 2) {
            var action = actions[parts[1]] || parts[1].charAt(0).toUpperCase() + parts[1].slice(1);
            var resource = resources[parts[0]] || parts[0];
            document.getElementById('name').value = action + ' ' + resource;
        } else {
            document.getElementById('name').value = slug.replace(/\./g, ' ');
        }

        // Auto-seleccionar categoría
        var categoryMap = {
            'users': 'Usuarios', 'pages': 'Contenido', 'posts': 'Blog',
            'media': 'Media', 'settings': 'Configuración', 'appearance': 'Apariencia',
            'themes': 'Apariencia', 'menus': 'Apariencia', 'modules': 'Sistema',
            'logs': 'Sistema', 'advanced': 'Sistema', 'languages': 'Configuración',
            'tickets': 'Soporte'
        };
        var category = categoryMap[parts[0]] || 'General';
        document.querySelector('input[name="category"]').value = category;
    });
});
</script>
@endsection
