@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="mb-4">Crear nuevo tema</h1>

    @if (consume_flash('error'))
        <div class="alert alert-danger">{{ consume_flash('error') }}</div>
    @endif

    @if (consume_flash('success'))
        <div class="alert alert-success">{{ consume_flash('success') }}</div>
    @endif

    <form method="POST" action="/musedock/themes/store">
        {!! csrf_field() !!}

        <div class="mb-3">
            <label for="name" class="form-label">Nombre del tema</label>
            <input type="text" class="form-control" name="name" id="name" required placeholder="Ej: Tema oscuro moderno">
        </div>

        <div class="mb-3">
            <label for="slug" class="form-label">Slug (identificador único)</label>
            <input type="text" class="form-control" name="slug" id="slug" required placeholder="Ej: tema-oscuro">
            <small class="form-text text-muted">Sin espacios ni caracteres especiales. Se usará como nombre de carpeta.</small>
        </div>

        <button type="submit" class="btn btn-success">Crear tema</button>
        <a href="/musedock/themes" class="btn btn-secondary ms-2">Cancelar</a>
    </form>
</div>
@endsection
