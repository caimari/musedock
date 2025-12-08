@extends('layouts.app')

@section('title', 'Dashboard de Inteligencia Artificial')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Dashboard de IA</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <!-- Estadísticas Resumen -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Peticiones Totales</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['summary']['total_requests'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-arrow-repeat fs-2 text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Tokens Totales Usados</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($stats['summary']['total_tokens'] ?? 0) }}</div>
                        </div>
                        <div class="col-auto">
                             <i class="bi bi-coin fs-2 text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
         <div class="col-md-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Proveedores Activos</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['summary']['providers_count'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                             <i class="bi bi-cpu fs-2 text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
         <div class="col-md-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Módulos Usando IA</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['summary']['modules_count'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                             <i class="bi bi-box-seam fs-2 text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Uso por Proveedor y Módulo -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Uso por Proveedor</h6>
                </div>
                <div class="card-body">
                    @if(!empty($stats['by_provider']))
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Proveedor</th>
                                        <th>Peticiones</th>
                                        <th>Tokens</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($stats['by_provider'] as $providerStat)
                                    <tr>
                                        <td>{{ $providerStat['provider_name'] }} ({{ $providerStat['provider_type'] }})</td>
                                        <td>{{ $providerStat['requests'] }}</td>
                                        <td>{{ number_format($providerStat['tokens']) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p>No hay datos de uso por proveedor.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Uso por Módulo</h6>
                </div>
                <div class="card-body">
                     @if(!empty($stats['by_module']))
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Módulo</th>
                                        <th>Peticiones</th>
                                        <th>Tokens</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($stats['by_module'] as $moduleStat)
                                    <tr>
                                        <td>{{ $moduleStat['module'] ?: 'N/A' }}</td>
                                        <td>{{ $moduleStat['requests'] }}</td>
                                        <td>{{ number_format($moduleStat['tokens']) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p>No hay datos de uso por módulo.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>
@endsection