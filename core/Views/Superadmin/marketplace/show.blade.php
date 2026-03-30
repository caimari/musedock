@extends('layouts.app')

@section('title', $title ?? 'Detalles del Item')

@section('content')
@include('partials.alerts-sweetalert2')

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
    $typeNames = [
        'module' => 'Módulo',
        'plugin' => 'Plugin',
        'theme' => 'Tema',
    ];
    $icon = $typeIcons[$type] ?? 'bi-box';
    $color = $typeColors[$type] ?? 'secondary';
    $typeName = $typeNames[$type] ?? 'Item';
@endphp

<div class="container-fluid py-4">
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('superadmin.marketplace.index') }}">Marketplace</a></li>
            <li class="breadcrumb-item"><a href="{{ route('superadmin.marketplace.search') }}?type={{ $type }}">{{ $typeName }}s</a></li>
            <li class="breadcrumb-item active">{{ $item['name'] }}</li>
        </ol>
    </nav>

    <div class="row">
        {{-- Info Principal --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                {{-- Header con imagen --}}
                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 250px;">
                    @if(!empty($item['thumbnail']) && file_exists(APP_ROOT . '/public' . $item['thumbnail']))
                        <img src="{{ $item['thumbnail'] }}" alt="{{ $item['name'] }}" class="img-fluid" style="max-height: 250px; object-fit: contain;">
                    @else
                        <i class="bi {{ $icon }} display-1 text-{{ $color }} opacity-50"></i>
                    @endif
                </div>

                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <span class="badge bg-{{ $color }} mb-2">
                                <i class="bi {{ $icon }} me-1"></i> {{ $typeName }}
                            </span>
                            <h2 class="mb-1">{{ $item['name'] }}</h2>
                            <p class="text-muted">
                                <i class="bi bi-person me-1"></i> {{ $item['author'] ?? 'MuseDock' }}
                            </p>
                        </div>
                        @if(($item['price'] ?? 0) == 0)
                            <span class="badge bg-success fs-5 py-2 px-3">Gratis</span>
                        @else
                            <span class="badge bg-warning text-dark fs-5 py-2 px-3">${{ number_format($item['price'], 2) }}</span>
                        @endif
                    </div>

                    {{-- Descripción --}}
                    <div class="mb-4">
                        <h5>Descripción</h5>
                        <p class="text-muted">{{ $item['description'] ?? 'Sin descripción disponible.' }}</p>
                    </div>

                    {{-- Detalles Largos --}}
                    @if(!empty($item['long_description']))
                    <div class="mb-4">
                        <h5>Características</h5>
                        <div class="text-muted">
                            {!! nl2br(htmlspecialchars($item['long_description'])) !!}
                        </div>
                    </div>
                    @endif

                    {{-- Screenshots --}}
                    @if(!empty($item['screenshots']))
                    <div class="mb-4">
                        <h5>Capturas de Pantalla</h5>
                        <div class="row g-2">
                            @foreach($item['screenshots'] as $screenshot)
                            <div class="col-4">
                                <a href="{{ $screenshot }}" target="_blank">
                                    <img src="{{ $screenshot }}" alt="Screenshot" class="img-fluid rounded border">
                                </a>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Changelog --}}
                    @if(!empty($item['changelog']))
                    <div>
                        <h5>Historial de Cambios</h5>
                        <div class="bg-light p-3 rounded">
                            <pre class="mb-0 small">{{ $item['changelog'] }}</pre>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4">
            {{-- Acciones --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    @if($is_installed)
                        <div class="alert alert-success mb-3">
                            <i class="bi bi-check-circle me-2"></i>
                            <strong>Instalado</strong> - v{{ $installed_version }}
                        </div>

                        @if(!empty($item['version']) && version_compare($item['version'], $installed_version, '>'))
                            <button type="button" class="btn btn-warning w-100 mb-2 btn-update-item" data-slug="{{ $item['slug'] }}" data-type="{{ $type }}">
                                <i class="bi bi-arrow-repeat me-1"></i> Actualizar a v{{ $item['version'] }}
                            </button>
                        @endif

                        <form action="{{ route('superadmin.marketplace.uninstall') }}" method="POST" onsubmit="return confirm('¿Desinstalar este {{ strtolower($typeName) }}?');">
                            @csrf
                            <input type="hidden" name="_csrf" value="{{ csrf_token() }}">
                            <input type="hidden" name="slug" value="{{ $item['slug'] }}">
                            <input type="hidden" name="type" value="{{ $type }}">
                            <button type="submit" class="btn btn-outline-danger w-100">
                                <i class="bi bi-trash me-1"></i> Desinstalar
                            </button>
                        </form>
                    @else
                        <button type="button" class="btn btn-{{ $color }} btn-lg w-100 mb-2 btn-install-item" data-slug="{{ $item['slug'] }}" data-type="{{ $type }}" data-name="{{ $item['name'] }}">
                            <i class="bi bi-download me-1"></i> Instalar {{ $typeName }}
                        </button>
                        <small class="text-muted d-block text-center">
                            Instalación automática desde el marketplace
                        </small>
                    @endif
                </div>
            </div>

            {{-- Información --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i> Información</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted">Versión</td>
                            <td class="fw-bold">{{ $item['version'] ?? '1.0.0' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Autor</td>
                            <td>{{ $item['author'] ?? 'MuseDock' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Descargas</td>
                            <td>{{ number_format($item['downloads'] ?? 0) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Valoración</td>
                            <td>
                                @for($i = 1; $i <= 5; $i++)
                                    <i class="bi bi-star{{ $i <= ($item['rating'] ?? 0) ? '-fill text-warning' : '' }}"></i>
                                @endfor
                                <span class="small">({{ number_format($item['rating'] ?? 0, 1) }})</span>
                            </td>
                        </tr>
                        @if(!empty($item['min_version']))
                        <tr>
                            <td class="text-muted">Requiere</td>
                            <td>MuseDock v{{ $item['min_version'] }}+</td>
                        </tr>
                        @endif
                        @if(!empty($item['last_updated']))
                        <tr>
                            <td class="text-muted">Actualizado</td>
                            <td>{{ $item['last_updated'] }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>

            {{-- Soporte --}}
            @if(!empty($item['support_url']) || !empty($item['docs_url']))
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="bi bi-life-preserver me-2"></i> Soporte</h6>
                </div>
                <div class="card-body">
                    @if(!empty($item['docs_url']))
                        <a href="{{ $item['docs_url'] }}" target="_blank" class="btn btn-outline-primary w-100 mb-2">
                            <i class="bi bi-book me-1"></i> Documentación
                        </a>
                    @endif
                    @if(!empty($item['support_url']))
                        <a href="{{ $item['support_url'] }}" target="_blank" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-question-circle me-1"></i> Soporte
                        </a>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Instalar item
    document.querySelectorAll('.btn-install-item').forEach(btn => {
        btn.addEventListener('click', function() {
            const slug = this.dataset.slug;
            const type = this.dataset.type;
            const name = this.dataset.name;

            Swal.fire({
                title: '<i class="bi bi-download text-primary"></i>',
                html: `
                    <h4 class="mb-3">Instalar ${name}</h4>
                    <p class="text-muted">¿Deseas instalar este item desde el marketplace?</p>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-download me-1"></i> Instalar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#0d6efd',
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostrar loading
                    Swal.fire({
                        title: '<i class="bi bi-hourglass-split"></i>',
                        html: `
                            <h5 class="mb-3">Instalando...</h5>
                            <p class="text-muted mb-0">Descargando e instalando ${name}</p>
                            <div class="progress mt-3" style="height: 6px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
                            </div>
                        `,
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false
                    });

                    // Hacer petición AJAX
                    fetch('{{ route("superadmin.marketplace.install") }}', {
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
                                title: '¡Instalado!',
                                text: data.message,
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'No se pudo instalar',
                            });
                        }
                    })
                    .catch(error => {
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
                confirmButtonText: 'Actualizar',
                cancelButtonText: 'Cancelar',
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Actualizando...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
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
                            Swal.fire('¡Actualizado!', data.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    });
                }
            });
        });
    });
});
</script>
@endpush
