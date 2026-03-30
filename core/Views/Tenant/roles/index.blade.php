@extends('layouts.app')
@section('title', __('roles_title'))
@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Gestión de Roles</h1>

    @if(flash('success'))
        <div class="alert alert-success">{{ flash('success') }}</div>
    @endif

    @if(flash('error'))
        <div class="alert alert-danger">{{ flash('error') }}</div>
    @endif

    <a href="{{ admin_url('roles/create') }}" class="btn btn-primary mb-3">
        <i class="fas fa-plus"></i> Crear Nuevo Rol
    </a>

    <div class="card shadow mb-4">
        <div class="card-body">
            @if(count($roles) > 0)
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($roles as $role)
                                <tr>
                                    <td>{{ $role['name'] }}</td>
                                    <td>{{ $role['description'] }}</td>
                                    <td>
                                        <a href="{{ admin_url('roles/' . $role['id'] . '/edit') }}" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                        <a href="{{ admin_url('roles/' . $role['id'] . '/permissions') }}" class="btn btn-sm btn-info">
                                            <i class="fas fa-key"></i> Permisos
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p>No hay roles disponibles.</p>
            @endif
        </div>
    </div>
</div>
@endsection
