@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Permisos para el rol: {{ $role['name'] }}</h1>

    @if(flash('success'))
        <div class="alert alert-success">{{ flash('success') }}</div>
    @endif

    @if(flash('error'))
        <div class="alert alert-danger">{{ flash('error') }}</div>
    @endif

    <form action="{{ admin_url('roles/' . $role['id'] . '/permissions') }}" method="POST">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Listado de Permisos</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    {{-- Agrupar permisos por categoría --}}
                    @php
                        $grouped = [];
                        foreach ($permissions as $perm) {
                            $grouped[$perm['category']][] = $perm;
                        }
                    @endphp

                    {{-- Mostrar cada grupo --}}
                    @foreach($grouped as $category => $perms)
                        <div class="col-md-4 mb-4">
                            <h5 class="text-uppercase text-muted">{{ ucfirst($category) }}</h5>
                            @foreach($perms as $perm)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" 
                                           name="permissions[]" 
                                           value="{{ $perm['id'] }}"
                                           id="perm_{{ $perm['id'] }}"
                                           {{ in_array($perm['id'], $assigned) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="perm_{{ $perm['id'] }}">
                                        {{ $perm['name'] }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>

                <button type="submit" class="btn btn-primary mt-4">
                    <i class="fas fa-save"></i> Guardar Permisos
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
