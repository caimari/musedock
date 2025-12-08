@extends('layouts.app')

@section('title', 'Logs de Uso de IA')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Logs de Uso de IA</h1>

    <!-- Filtros -->
    <div class="card shadow mb-4">
        <div class="card-header">Filtros</div>
        <div class="card-body">
            <form method="GET" action="/musedock/ai/logs">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="provider_id" class="form-label">Proveedor</label>
                        <select name="provider_id" id="provider_id" class="form-select">
                            <option value="">Todos</option>
                            @foreach($providers as $provider)
                                <option value="{{ $provider['id'] }}" {{ ($filters['provider_id'] ?? '') == $provider['id'] ? 'selected' : '' }}>
                                    {{ $provider['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="module" class="form-label">Módulo</label>
                        <input type="text" name="module" id="module" class="form-control" value="{{ $filters['module'] ?? '' }}">
                    </div>
                     <div class="col-md-2">
                        <label for="date_from" class="form-label">Desde</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
                    </div>
                     <div class="col-md-2">
                        <label for="date_to" class="form-label">Hasta</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de Logs -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Proveedor</th>
                            <th>Módulo</th>
                            <th>Acción</th>
                            <th>Usuario</th>
                            <th>Estado</th>
                            <th>Tokens</th>
                            <th>Prompt (Inicio)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                        <tr>
                            <td>{{ $log['created_at'] }}</td>
                            <td>{{ $log['provider_name'] ?? 'N/A' }} ({{ $log['provider_type'] ?? 'N/A' }})</td>
                            <td>{{ $log['module'] ?: 'N/A' }}</td>
                            <td>{{ $log['action'] ?: 'N/A' }}</td>
                            <td>{{ $log['user_type'] ? $log['user_type'] . ':' . $log['user_id'] : 'Sistema' }}</td>
                            <td>
                                @if(strpos($log['status'], 'error') === 0)
                                    <span class="badge bg-danger" title="{{ $log['status'] }}">Error</span>
                                @else
                                     <span class="badge bg-success">{{ $log['status'] }}</span>
                                @endif
                            </td>
                            <td>{{ $log['tokens_used'] }}</td>
                            <td title="{{ $log['prompt'] }}">{{ Str::limit($log['prompt'], 50) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center">No se encontraron logs con los filtros aplicados.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Aquí podrías añadir la paginación si la implementas --}}
            {{-- Ejemplo: {{ $logs->links() }} --}}

        </div>
    </div>
</div>
{{-- Importar Str si no está disponible globalmente --}}
@php
use Illuminate\Support\Str;
@endphp
@endsection