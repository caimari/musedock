@extends('layouts.app')

@section('title', 'Grupos Editoriales - Cross-Publisher')

@section('content')
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-collection me-2"></i>Grupos Editoriales</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="/musedock/cross-publisher">Cross-Publisher</a></li>
                    <li class="breadcrumb-item active">Grupos</li>
                </ol>
            </nav>
        </div>
        <a href="/musedock/cross-publisher/groups/create" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Nuevo Grupo
        </a>
    </div>

    @include('partials.alerts-sweetalert2')

    <div class="card">
        <div class="card-body">
            @if(empty($groups))
                <div class="text-center py-5">
                    <i class="bi bi-collection text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">No hay grupos editoriales creados.</p>
                    <a href="/musedock/cross-publisher/groups/create" class="btn btn-outline-primary">
                        <i class="bi bi-plus-lg me-1"></i>Crear primer grupo
                    </a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Idioma</th>
                                <th>Miembros</th>
                                <th>Auto-Sync</th>
                                <th width="150">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($groups as $group)
                            <tr>
                                <td>
                                    <strong>{{ $group->name }}</strong>
                                </td>
                                <td class="text-muted">{{ $group->description ? mb_substr($group->description, 0, 80) . (mb_strlen($group->description) > 80 ? '...' : '') : '-' }}</td>
                                <td><span class="badge bg-secondary">{{ strtoupper($group->default_language ?? 'es') }}</span></td>
                                <td><span class="badge bg-primary">{{ $group->member_count ?? 0 }}</span> tenants</td>
                                <td>
                                    @if($group->auto_sync_enabled)
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Activo</span>
                                    @else
                                        <span class="badge bg-light text-muted">Inactivo</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="/musedock/cross-publisher/groups/{{ $group->id }}/edit" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="/musedock/cross-publisher/groups/{{ $group->id }}/delete" class="d-inline" onsubmit="return confirm('¿Eliminar este grupo? Los tenants NO se eliminarán.')">
                                        {!! csrf_field() !!}
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
