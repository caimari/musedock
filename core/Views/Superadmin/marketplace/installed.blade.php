@extends('layouts.app')

@section('title', $title ?? 'Items Instalados')

@section('content')
@include('partials.alerts-sweetalert2')

<div class="container-fluid py-4">
    {{-- Header --}}
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.marketplace.index') }}">Marketplace</a></li>
                    <li class="breadcrumb-item active">Mis Instalaciones</li>
                </ol>
            </nav>
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="bi bi-box-seam me-2"></i>
                        Items Instalados desde Marketplace
                    </h1>
                    <p class="text-muted mb-0">Gestiona los módulos, plugins y temas instalados desde el marketplace</p>
                </div>
                <a href="{{ route('superadmin.marketplace.index') }}" class="btn btn-primary">
                    <i class="bi bi-shop me-1"></i> Ir al Marketplace
                </a>
            </div>
        </div>
    </div>

    {{-- Actualizaciones disponibles --}}
    @if(!empty($updates))
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-warning d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
                <div>
                    <strong>{{ count($updates) }} actualización(es) disponible(s)</strong>
                    <p class="mb-0 small">Hay nuevas versiones disponibles para algunos de tus items instalados.</p>
                </div>
            </div>

            <div class="card border-warning mb-4">
                <div class="card-header bg-warning bg-opacity-10">
                    <h6 class="mb-0"><i class="bi bi-arrow-repeat me-2"></i> Actualizaciones Disponibles</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Tipo</th>
                                    <th>Versión Actual</th>
                                    <th>Nueva Versión</th>
                                    <th class="text-end">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($updates as $update)
                                <tr>
                                    <td><strong>{{ $update['name'] }}</strong></td>
                                    <td>
                                        <span class="badge bg-{{ $update['type'] === 'module' ? 'primary' : ($update['type'] === 'plugin' ? 'success' : 'info') }}">
                                            {{ ucfirst($update['type']) }}
                                        </span>
                                    </td>
                                    <td>v{{ $update['current_version'] }}</td>
                                    <td><span class="text-success fw-bold">v{{ $update['latest_version'] }}</span></td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-warning btn-update-item" data-slug="{{ $update['slug'] }}" data-type="{{ $update['type'] }}">
                                            <i class="bi bi-arrow-repeat"></i> Actualizar
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Filtros --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="btn-group" role="group">
                <a href="{{ route('superadmin.marketplace.installed') }}" class="btn {{ !$current_type ? 'btn-primary' : 'btn-outline-primary' }}">
                    <i class="bi bi-grid-3x3-gap me-1"></i> Todos
                </a>
                <a href="{{ route('superadmin.marketplace.installed') }}?type=module" class="btn {{ $current_type === 'module' ? 'btn-primary' : 'btn-outline-primary' }}">
                    <i class="bi bi-puzzle me-1"></i> Módulos
                </a>
                <a href="{{ route('superadmin.marketplace.installed') }}?type=plugin" class="btn {{ $current_type === 'plugin' ? 'btn-primary' : 'btn-outline-primary' }}">
                    <i class="bi bi-plug me-1"></i> Plugins
                </a>
                <a href="{{ route('superadmin.marketplace.installed') }}?type=theme" class="btn {{ $current_type === 'theme' ? 'btn-primary' : 'btn-outline-primary' }}">
                    <i class="bi bi-palette me-1"></i> Temas
                </a>
            </div>
        </div>
    </div>

    {{-- Lista de items instalados --}}
    @if(!empty($items))
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Item</th>
                            <th>Tipo</th>
                            <th>Versión</th>
                            <th>Instalado</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                        @php
                            $typeIcons = [
                                'module' => 'bi-puzzle',
                                'plugin' => 'bi-plug',
                                'theme' => 'bi-palette',
                            ];
                            $typeColors = [
                                'module' => 'primary',
                                'plugin' => 'success',
                                'theme' => 'info',
                            ];
                            $icon = $typeIcons[$item['type']] ?? 'bi-box';
                            $color = $typeColors[$item['type']] ?? 'secondary';
                        @endphp
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-{{ $color }} bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                        <i class="bi {{ $icon }} text-{{ $color }}"></i>
                                    </div>
                                    <div>
                                        <strong>{{ $item['name'] ?? $item['slug'] }}</strong>
                                        <br>
                                        <small class="text-muted">{{ $item['slug'] }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-{{ $color }}">{{ ucfirst($item['type']) }}</span>
                            </td>
                            <td>
                                <span class="badge bg-secondary">v{{ $item['version'] ?? '1.0.0' }}</span>
                            </td>
                            <td>
                                <small class="text-muted">
                                    {{ date('d/m/Y H:i', strtotime($item['installed_at'])) }}
                                </small>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('superadmin.marketplace.show', ['type' => $item['type'], 'slug' => $item['slug']]) }}" class="btn btn-outline-{{ $color }}" title="Ver detalles">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <button type="button" class="btn btn-outline-danger btn-uninstall-item" data-slug="{{ $item['slug'] }}" data-type="{{ $item['type'] }}" data-name="{{ $item['name'] ?? $item['slug'] }}" title="Desinstalar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @else
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-box-seam display-1 text-muted opacity-50"></i>
            <h4 class="mt-3">No hay items instalados desde el marketplace</h4>
            <p class="text-muted">Explora el marketplace para encontrar módulos, plugins y temas útiles.</p>
            <a href="{{ route('superadmin.marketplace.index') }}" class="btn btn-primary">
                <i class="bi bi-shop me-1"></i> Explorar Marketplace
            </a>
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Desinstalar item
    document.querySelectorAll('.btn-uninstall-item').forEach(btn => {
        btn.addEventListener('click', function() {
            const slug = this.dataset.slug;
            const type = this.dataset.type;
            const name = this.dataset.name;

            Swal.fire({
                title: '<i class="bi bi-exclamation-triangle-fill text-danger"></i>',
                html: `
                    <h4 class="text-danger mb-3">Desinstalar ${name}</h4>
                    <p class="text-muted">¿Estás seguro de que deseas desinstalar este item?</p>
                    <div class="alert alert-warning py-2">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        Esta acción eliminará los archivos del servidor.
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-trash me-1"></i> Desinstalar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
            }).then((result) => {
                if (result.isConfirmed) {
                    // Crear y enviar formulario
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '{{ route("superadmin.marketplace.uninstall") }}';
                    form.innerHTML = `
                        <input type="hidden" name="_csrf" value="{{ csrf_token() }}">
                        <input type="hidden" name="slug" value="${slug}">
                        <input type="hidden" name="type" value="${type}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
    });

    // Actualizar item
    document.querySelectorAll('.btn-update-item').forEach(btn => {
        btn.addEventListener('click', function() {
            const slug = this.dataset.slug;
            const type = this.dataset.type;

            Swal.fire({
                title: '¿Actualizar?',
                text: 'Se descargará e instalará la última versión.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-arrow-repeat me-1"></i> Actualizar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#ffc107',
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Actualizando...',
                        html: '<div class="progress mt-2"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div></div>',
                        allowOutsideClick: false,
                        showConfirmButton: false
                    });

                    fetch('{{ route("superadmin.marketplace.update") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `_csrf={{ csrf_token() }}&slug=${slug}&type=${type}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Actualizado!',
                                text: data.message,
                            }).then(() => location.reload());
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'No se pudo actualizar',
                            });
                        }
                    })
                    .catch(() => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Error de conexión',
                        });
                    });
                }
            });
        });
    });
});
</script>
@endpush
