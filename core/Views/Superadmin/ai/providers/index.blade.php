@extends('layouts.app')

@section('title', 'Proveedores de IA')

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Proveedores de IA</h1>
        <a href="/musedock/ai/providers/create" class="btn btn-primary btn-sm shadow-sm">
            <i class="bi bi-plus-lg me-1"></i> Crear Nuevo Proveedor
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Modelo Pred.</th>
                            <th>Activo</th>
                            <th>Sistema Global</th>
                            <th>Tenant ID</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($providers as $provider)
                        <tr>
                            <td>{{ $provider['name'] }}</td>
                            <td><span class="badge bg-secondary">{{ $provider['provider_type'] }}</span></td>
                            <td>{{ $provider['model'] ?: 'N/A' }}</td>
                            <td>
                                @if($provider['active'])
                                    <span class="badge bg-success">Sí</span>
                                @else
                                    <span class="badge bg-danger">No</span>
                                @endif
                            </td>
                            <td>
                                @if($provider['system_wide'])
                                    <span class="badge bg-info">Sí</span>
                                @else
                                    <span class="badge bg-secondary">No</span>
                                @endif
                            </td>
                             <td>{{ $provider['tenant_id'] ?: 'Global' }}</td>
                            <td>
                                <a href="/musedock/ai/providers/{{ $provider['id'] }}/edit" class="btn btn-sm btn-warning me-1" title="Editar">
                                    <i class="bi bi-pencil-fill"></i>
                                </a>

                                <form action="/musedock/ai/providers/{{ $provider['id'] }}/toggle" method="POST" class="d-inline-block me-1" onsubmit="return confirm('¿Cambiar estado de activación?')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $provider['active'] ? 'btn-secondary' : 'btn-success' }}" title="{{ $provider['active'] ? 'Desactivar' : 'Activar' }}">
                                        <i class="bi {{ $provider['active'] ? 'bi-toggle-off' : 'bi-toggle-on' }}"></i>
                                    </button>
                                </form>

                                <form action="/musedock/ai/providers/{{ $provider['id'] }}/delete" method="POST" class="d-inline-block" onsubmit="return confirm('¿Estás seguro de eliminar este proveedor? Esta acción no se puede deshacer.')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center">No se encontraron proveedores.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection