@extends('layouts.app')

@section('title', $title ?? 'Plugin Store')

@push('styles')
<style>
.store-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}
.store-stats {
    display: flex;
    gap: 1rem;
}
.stat-badge {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.85rem;
    padding: 0.4rem 0.75rem;
    border-radius: 6px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
}
.stat-badge i { font-size: 0.9rem; }
</style>
@endpush

@section('content')
<div class="container-fluid">
    @php
        $installedCount = 0;
        foreach ($catalog as $p) { if (!empty($p['is_installed'])) $installedCount++; }
        $totalCount = count($catalog);
    @endphp

    <div class="store-header">
        <div class="d-flex align-items-center gap-3">
            <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-shop" style="font-size:1.35rem;color:#fff;"></i>
            </div>
            <div>
                <h3 class="mb-0" style="font-size:1.25rem;font-weight:700;">Plugin Store</h3>
                <p class="text-muted mb-0" style="font-size:0.85rem;">Descubre plugins y módulos para ampliar tu instalación</p>
            </div>
        </div>
        <div class="store-stats">
            <div class="stat-badge" style="background:rgba(25,135,84,0.1);border-color:rgba(25,135,84,0.2);color:#198754;">
                <i class="bi bi-check-circle-fill"></i>
                <span>{{ $installedCount }} instalados</span>
            </div>
            <div class="stat-badge">
                <i class="bi bi-box-seam"></i>
                <span>{{ $totalCount }} disponibles</span>
            </div>
            <a href="/musedock/modules" class="stat-badge" style="text-decoration:none;color:#6366f1;border-color:rgba(99,102,241,0.2);background:rgba(99,102,241,0.08);">
                <i class="bi bi-arrow-left"></i>
                <span>Módulos</span>
            </a>
        </div>
    </div>

    @if(empty($catalog))
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <div style="width:64px;height:64px;border-radius:16px;background:linear-gradient(135deg,#e0e7ff,#c7d2fe);display:inline-flex;align-items:center;justify-content:center;margin-bottom:1rem;">
                <i class="bi bi-shop" style="font-size:1.75rem;color:#6366f1;"></i>
            </div>
            <h5 class="mb-2">No hay productos disponibles</h5>
            <p class="text-muted mb-3" style="max-width:400px;margin:0 auto;">Comprueba la conexión con el servidor de licencias. El catálogo se actualizará cuando esté disponible.</p>
            <a href="/musedock/modules" style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.5rem 1.25rem;border-radius:8px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;text-decoration:none;font-size:0.85rem;font-weight:500;transition:opacity 0.15s ease;"
               onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">
                <i class="bi bi-puzzle"></i>
                Gestionar Módulos
            </a>
        </div>
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
                    @elseif(!empty($product['update_available']))
                        @if(!empty($product['has_license']))
                        <button class="btn btn-warning btn-sm w-100 btn-install" data-slug="{{ $product['slug'] }}">
                            <i class="bi bi-arrow-up-circle me-1"></i>Actualizar a v{{ $product['current_version'] }}
                        </button>
                        @else
                        <button class="btn btn-warning btn-sm w-100 btn-verify" data-slug="{{ $product['slug'] }}" data-name="{{ $product['name'] ?? $product['slug'] }}">
                            <i class="bi bi-key me-1"></i>Verificar licencia para actualizar
                        </button>
                        @endif
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
                    return fetch('/musedock/plugin-store/verify', {
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
                        Swal.showValidationMessage('Error de conexion con el servidor de licencias');
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
            const originalHtml = button.innerHTML;

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
                    return fetch('/musedock/plugin-store/install', {
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
