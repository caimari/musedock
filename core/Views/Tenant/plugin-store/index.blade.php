@extends('layouts.app')

@section('title', $title ?? 'Plugin Store')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="bi bi-shop me-2"></i>
                        Plugin Store
                    </h1>
                    <p class="text-muted mb-0">Plugins y modulos premium disponibles</p>
                </div>
                <a href="{{ admin_url('plugins') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Volver a Plugins
                </a>
            </div>
        </div>
    </div>

    @if(empty($catalog))
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        No hay productos disponibles en este momento.
    </div>
    @else
    <div class="row g-4">
        @foreach($catalog as $product)
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border" id="product-{{ $product['slug'] }}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="card-title mb-0">{{ $product['name'] ?? $product['slug'] }}</h5>
                        @php
                            $typeBadge = match($product['type'] ?? '') {
                                'cms-plugin' => 'bg-primary',
                                'cms-module' => 'bg-success',
                                default      => 'bg-secondary',
                            };
                        @endphp
                        <span class="badge {{ $typeBadge }}">{{ $product['type'] ?? '' }}</span>
                    </div>
                    <p class="card-text text-muted small">{{ $product['description'] ?? '' }}</p>

                    <div class="d-flex gap-3 mb-3 small">
                        @if(!empty($product['price_monthly']))
                        <span class="text-muted">{{ number_format($product['price_monthly'], 2) }}&euro;/mes</span>
                        @endif
                        @if(!empty($product['price_yearly']))
                        <span class="text-muted">{{ number_format($product['price_yearly'], 2) }}&euro;/ano</span>
                        @endif
                        @if(!empty($product['price_lifetime']))
                        <span class="text-muted">{{ number_format($product['price_lifetime'], 2) }}&euro; lifetime</span>
                        @endif
                    </div>

                    @if(!empty($product['current_version']))
                    <div class="mb-3">
                        <span class="badge bg-light text-dark">v{{ $product['current_version'] }}</span>
                        @if(!empty($product['installed_version']))
                        <span class="badge bg-light text-dark">Instalado: v{{ $product['installed_version'] }}</span>
                        @endif
                    </div>
                    @endif
                </div>
                <div class="card-footer bg-transparent border-top">
                    @if(!empty($product['is_installed']) && !($product['update_available'] ?? false))
                        <span class="btn btn-success btn-sm disabled w-100">
                            <i class="bi bi-check-circle me-1"></i>Instalado
                        </span>
                    @elseif(!empty($product['update_available']) && !empty($product['has_license']))
                        <button class="btn btn-warning btn-sm w-100 btn-install" data-slug="{{ $product['slug'] }}">
                            <i class="bi bi-arrow-up-circle me-1"></i>Actualizar
                        </button>
                    @elseif(!empty($product['has_license']))
                        <button class="btn btn-primary btn-sm w-100 btn-install" data-slug="{{ $product['slug'] }}">
                            <i class="bi bi-download me-1"></i>Instalar
                        </button>
                    @else
                        <button class="btn btn-outline-primary btn-sm w-100 btn-verify" data-slug="{{ $product['slug'] }}" data-name="{{ $product['name'] ?? $product['slug'] }}">
                            <i class="bi bi-key me-1"></i>Introducir licencia
                        </button>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const adminPath = '{{ admin_url("") }}'.replace(/\/$/, '');

    // Verify license via SweetAlert2
    document.querySelectorAll('.btn-verify').forEach(btn => {
        btn.addEventListener('click', function() {
            const slug = this.dataset.slug;
            const name = this.dataset.name;

            Swal.fire({
                title: 'Verificar licencia',
                html: `<p class="text-muted small mb-3">Introduce tu clave de licencia para <strong>${name}</strong></p>
                       <input type="text" id="swal-license-key" class="swal2-input" placeholder="MDCK-XXXX-XXXX-XXXX" maxlength="19" style="text-transform:uppercase;letter-spacing:2px;font-family:monospace;font-size:0.9rem;">`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-shield-check me-1"></i>Verificar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#6366f1',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    const key = document.getElementById('swal-license-key').value.trim();
                    if (!key) {
                        Swal.showValidationMessage('Introduce una clave de licencia');
                        return false;
                    }
                    return fetch(adminPath + '/plugin-store/verify', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `key=${encodeURIComponent(key)}&product_slug=${encodeURIComponent(slug)}&_token=${encodeURIComponent(csrfToken)}`
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) {
                            Swal.showValidationMessage(data.error || 'Licencia no valida');
                            return false;
                        }
                        return data;
                    })
                    .catch(() => {
                        Swal.showValidationMessage('Error de conexion');
                        return false;
                    });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Licencia verificada',
                        text: 'Expira: ' + (result.value.expires || 'Lifetime'),
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => location.reload());
                }
            });
        });
    });

    // Install product via SweetAlert2
    document.querySelectorAll('.btn-install').forEach(btn => {
        btn.addEventListener('click', function() {
            const slug = this.dataset.slug;
            const button = this;

            Swal.fire({
                title: 'Instalar producto',
                text: `Se descargara e instalara "${slug}". Continuar?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-download me-1"></i>Instalar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#6366f1',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch(adminPath + '/plugin-store/install', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `product_slug=${encodeURIComponent(slug)}&_token=${encodeURIComponent(csrfToken)}`
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) {
                            Swal.showValidationMessage(data.error || 'Error al instalar');
                            return false;
                        }
                        return data;
                    })
                    .catch(() => {
                        Swal.showValidationMessage('Error de conexion');
                        return false;
                    });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    button.className = 'btn btn-success btn-sm w-100 disabled';
                    button.innerHTML = '<i class="bi bi-check-circle me-1"></i>Instalado';
                    Swal.fire({
                        icon: 'success',
                        title: 'Instalado',
                        text: result.value.message || 'Producto instalado correctamente',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            });
        });
    });
});
</script>
@endpush
