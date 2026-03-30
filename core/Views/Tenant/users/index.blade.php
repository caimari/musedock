@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Gestión de Usuarios</h1>
        
        @if($canCreate)
        <a href="{{ admin_url('users/create') }}" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-user-plus fa-sm text-white-50"></i> Crear Usuario
        </a>
        @endif
    </div>

    @if(flash('success'))
    <div class="alert alert-success">{{ flash('success') }}</div>
    @endif

    @if(flash('error'))
    <div class="alert alert-danger">{{ flash('error') }}</div>
    @endif

    {{-- Tabla de usuarios --}}
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Usuarios Registrados</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="usersTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Roles</th>
                            <th>Fecha Registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                        <tr>
                            <td>{{ $user['id'] }}</td>
                            <td>{{ $user['name'] }}</td>
                            <td>{{ $user['email'] }}</td>
                            <td>
                                @if(!empty($user['roles']))
                                    @foreach(explode(',', $user['roles']) as $role)
                                        <span class="badge badge-info">{{ trim($role) }}</span>
                                    @endforeach
                                @else
                                    <span class="badge badge-secondary">Sin rol</span>
                                @endif
                            </td>
                            <td>{{ $user['created_at'] }}</td>
                            <td>
                                <div class="btn-group" role="group">
                                    @if($canEdit)
                                    <a href="{{ admin_url('users/edit/' . $user['id']) }}" class="btn btn-sm btn-info">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    @endif
                                    
                                    @if($canDelete)
                                    <form action="{{ admin_url('users/delete/' . $user['id']) }}" method="POST" style="display:inline;" onsubmit="return confirm('¿Estás seguro de eliminar este usuario?');">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i> Eliminar
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Tabla de roles --}}
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Roles Disponibles y Permisos</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Permisos Asignados</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($roles as $role)
                        <tr>
                            <td>{{ $role['id'] }}</td>
                            <td>{{ $role['name'] }}</td>
                            <td>{{ $role['description'] }}</td>
                            <td>
                                @if(!empty($role['permissions']))
                                    @foreach($role['permissions'] as $perm)
                                        <span class="badge badge-light">{{ $perm }}</span>
                                    @endforeach
                                @else
                                    <span class="text-muted">Sin permisos</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        $('#usersTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json'
            }
        });
    });
</script>
@endsection
