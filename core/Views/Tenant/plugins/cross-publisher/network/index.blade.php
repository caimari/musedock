@extends('layouts.app')

@section('title', 'Red - Cross-Publisher')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        @include('plugins.cross-publisher._nav', ['activeTab' => 'network'])

        {{-- Flash Messages --}}
        @if(session('flash_success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('flash_success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('flash_error'))
            <div class="alert alert-danger alert-dismissible fade show">
                {{ session('flash_error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(!$networkKey)
            {{-- Registration Form --}}
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Registrar en una red</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Introduce la clave de tu red editorial para conectar con otros tenants.
                    </p>

                    <form action="{{ admin_url('/plugins/cross-publisher/network/register') }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="network_key" class="form-label">Clave de red *</label>
                                    <input type="text" class="form-control" id="network_key" name="network_key"
                                           placeholder="mi-red-editorial" pattern="[a-z0-9-]+" required>
                                    <div class="form-text">Solo letras minúsculas, números y guiones</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="default_language" class="form-label">Idioma por defecto</label>
                                    <select class="form-select" id="default_language" name="default_language">
                                        <option value="es">Español</option>
                                        <option value="en">English</option>
                                        <option value="ca">Català</option>
                                        <option value="fr">Français</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="can_publish" name="can_publish" checked>
                                    <label class="form-check-label" for="can_publish">
                                        Puede publicar en otros tenants
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="can_receive" name="can_receive" checked>
                                    <label class="form-check-label" for="can_receive">
                                        Puede recibir de otros tenants
                                    </label>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-diagram-3"></i> Registrar
                        </button>
                    </form>
                </div>
            </div>
        @else
            {{-- Current Configuration --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Configuración actual</h5>
                </div>
                <div class="card-body">
                    <form action="{{ admin_url('/plugins/cross-publisher/network/update') }}" method="POST">
                        @csrf
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Clave de red</label>
                                <input type="text" class="form-control" value="{{ $networkKey }}" disabled>
                            </div>
                            <div class="col-md-6">
                                <label for="default_language" class="form-label">Idioma por defecto</label>
                                <select class="form-select" id="default_language" name="default_language">
                                    <option value="es" {{ ($config->default_language ?? 'es') === 'es' ? 'selected' : '' }}>Español</option>
                                    <option value="en" {{ ($config->default_language ?? '') === 'en' ? 'selected' : '' }}>English</option>
                                    <option value="ca" {{ ($config->default_language ?? '') === 'ca' ? 'selected' : '' }}>Català</option>
                                    <option value="fr" {{ ($config->default_language ?? '') === 'fr' ? 'selected' : '' }}>Français</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="can_publish" name="can_publish"
                                           {{ ($config->can_publish ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="can_publish">Puede publicar</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="can_receive" name="can_receive"
                                           {{ ($config->can_receive ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="can_receive">Puede recibir</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active"
                                           {{ ($config->is_active ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">Activo</label>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Guardar cambios
                        </button>
                    </form>
                </div>
            </div>

            {{-- Network Members --}}
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Miembros de la red ({{ count($networkTenants) }})</h5>
                </div>
                @if(empty($networkTenants))
                    <div class="card-body">
                        <p class="text-muted">No hay otros miembros en esta red.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Tenant</th>
                                    <th>Dominio</th>
                                    <th>Idioma</th>
                                    <th>Permisos</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($networkTenants as $tenant)
                                    <tr>
                                        <td><strong>{{ $tenant->tenant_name }}</strong></td>
                                        <td>
                                            <a href="https://{{ $tenant->domain }}" target="_blank">
                                                {{ $tenant->domain }}
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">{{ strtoupper($tenant->default_language) }}</span>
                                        </td>
                                        <td>
                                            @if($tenant->can_publish)
                                                <span class="badge bg-success">Publica</span>
                                            @endif
                                            @if($tenant->can_receive)
                                                <span class="badge bg-info">Recibe</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
