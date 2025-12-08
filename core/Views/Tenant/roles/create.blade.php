@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Crear Nuevo Rol</h1>

    @if(flash('error'))
        <div class="alert alert-danger">{{ flash('error') }}</div>
    @endif

    <form action="{{ admin_url('roles/create') }}" method="POST">
        <div class="form-group">
            <label for="name">Nombre del Rol</label>
            <input type="text" name="name" id="name" class="form-control" required>
        </div>

        <div class="form-group">
            <label for="description">Descripción</label>
            <textarea name="description" id="description" class="form-control"></textarea>
        </div>

        <button type="submit" class="btn btn-success mt-3">
            <i class="fas fa-save"></i> Guardar Rol
        </button>
    </form>
</div>
@endsection
