@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Editar Rol: {{ $role['name'] }}</h1>

    @if(flash('success'))
        <div class="alert alert-success">{{ flash('success') }}</div>
    @endif

    @if(flash('error'))
        <div class="alert alert-danger">{{ flash('error') }}</div>
    @endif

    <form action="{{ admin_url('roles/' . $role['id'] . '/edit') }}" method="POST">
        <div class="form-group col-md-4 mb-4">
            <label for="name">Nombre del Rol</label>
            <input type="text" name="name" id="name" class="form-control" value="{{ $role['name'] }}" readonly>
        </div>

        <div class="form-group col-md-4 mb-4">
            <label for="description">Descripción</label>
            <textarea name="description" id="description" class="form-control" rows="4">{{ $role['description'] }}</textarea>
        </div>

        <button type="submit" class="btn btn-primary mt-4">
            <i class="fas fa-save"></i> Guardar Cambios
        </button>
    </form>
</div>
@endsection
