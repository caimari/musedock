@extends('layouts.app')

@section('title', 'Configuracion AI Image')

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <h1 class="h3 mb-4">
            <i class="bi bi-stars"></i> AI Image - Proveedores de Imagen
        </h1>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="card mb-4">
            <div class="card-header">
                <strong>Proveedores disponibles</strong>
            </div>
            <div class="card-body">
                @if(empty($providers))
                    <div class="text-center py-4">
                        <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">No hay proveedores de imagen IA configurados.</p>
                        <p class="text-muted">Contacta con el administrador del sistema para activar proveedores de imagen.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nombre</th>
                                    <th>Tipo</th>
                                    <th>Modelo</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($providers as $p)
                                <tr>
                                    <td><strong>{{ $p['name'] }}</strong></td>
                                    <td><span class="badge bg-info">{{ strtoupper($p['provider_type']) }}</span></td>
                                    <td>{{ $p['model'] ?? '-' }}</td>
                                    <td><span class="badge bg-success">Disponible</span></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="bi bi-info-circle"></i>
                        Los proveedores de imagen IA estan disponibles en el editor de posts.
                        Busca el boton <i class="bi bi-stars"></i> junto al campo de imagen destacada.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
