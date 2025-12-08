@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Gestión de Permisos por Rol</h1>

    @if(flash('success'))
        <div class="alert alert-success">{{ flash('success') }}</div>
    @endif

    @if(flash('error'))
        <div class="alert alert-danger">{{ flash('error') }}</div>
    @endif

    <form action="{{ admin_url('roles/permissions') }}" method="POST">
        <div class="row">
            @php
                // Agrupamos permisos por categoría
                $groupedPermissions = [];
                foreach ($permissions as $perm) {
                    $groupedPermissions[$perm['category']][] = $perm;
                }
            @endphp

            @foreach($roles as $role)
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <strong>Rol: {{ $role['name'] }}</strong>
                        <span class="small d-block">{{ $role['description'] }}</span>
                    </div>
                    <div class="card-body">
                        @foreach($groupedPermissions as $category => $perms)
                            <h6 class="text-uppercase text-muted mt-3">{{ ucfirst($category) }}</h6>
                            @foreach($perms as $perm)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           name="permissions[{{ $role['id'] }}][]"
                                           value="{{ $perm['id'] }}"
                                           id="role{{ $role['id'] }}_perm{{ $perm['id'] }}"
                                           {{ in_array($perm['id'], $rolePermissions[$role['id']] ?? []) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="role{{ $role['id'] }}_perm{{ $perm['id'] }}">
                                        {{ $perm['name'] }}
                                    </label>
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="text-center">
            <button type="submit" class="btn btn-success btn-lg px-4">
                <i class="fas fa-save"></i> Guardar todos los cambios
            </button>
        </div>
    </form>
</div>
@endsection
