@extends('layouts.app')

@section('title', __('modules_title'))

@push('styles')
<style>
.module-list {
    display: flex;
    flex-direction: column;
    gap: 0;
}
.module-item {
    display: flex;
    align-items: center;
    padding: 1rem 1.25rem;
    background: #fff;
    border: 1px solid #e9ecef;
    border-bottom: none;
    transition: all 0.15s ease;
}
.module-item:first-child {
    border-radius: 0.5rem 0.5rem 0 0;
}
.module-item:last-child {
    border-bottom: 1px solid #e9ecef;
    border-radius: 0 0 0.5rem 0.5rem;
}
.module-item:hover {
    background: #f8f9fa;
}
.module-item.enabled {
    border-left: 3px solid #198754;
}
.module-item.disabled {
    border-left: 3px solid #dee2e6;
    opacity: 0.85;
}
.module-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
    margin-right: 1rem;
}
.module-icon.enabled {
    background: linear-gradient(135deg, #198754 0%, #20c997 100%);
    color: white;
}
.module-icon.disabled {
    background: #e9ecef;
    color: #6c757d;
}
.module-info {
    flex-grow: 1;
    min-width: 0;
}
.module-name {
    font-weight: 600;
    font-size: 1rem;
    color: #212529;
    margin-bottom: 0.15rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.module-description {
    font-size: 0.85rem;
    color: #6c757d;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.module-meta {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-right: 1rem;
    flex-shrink: 0;
}
.module-version {
    font-size: 0.75rem;
    color: #6c757d;
    background: #f1f3f4;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-family: monospace;
}
.module-status {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.8rem;
    font-weight: 500;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
}
.module-status.active {
    background: rgba(25, 135, 84, 0.1);
    color: #198754;
}
.module-status.inactive {
    background: rgba(108, 117, 125, 0.1);
    color: #6c757d;
}
.module-actions {
    flex-shrink: 0;
}
.btn-toggle {
    min-width: 110px;
    font-size: 0.85rem;
    padding: 0.4rem 0.75rem;
}
.modules-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}
.modules-stats {
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
.stat-badge.active {
    background: rgba(25, 135, 84, 0.1);
    border-color: rgba(25, 135, 84, 0.2);
    color: #198754;
}
.stat-badge i {
    font-size: 0.9rem;
}
@media (max-width: 768px) {
    .module-item {
        flex-wrap: wrap;
        gap: 0.75rem;
    }
    .module-meta {
        width: 100%;
        margin-right: 0;
        justify-content: flex-start;
        order: 3;
    }
    .module-actions {
        order: 2;
        margin-left: auto;
    }
}
</style>
@endpush

@section('content')
<div class="container-fluid">
    @php
        $activeCount = 0;
        foreach ($modules as $m) {
            if (!empty($m['tenant_enabled'])) {
                $activeCount++;
            }
        }
        $totalCount = count($modules);
    @endphp

    <div class="modules-header">
        <div>
            <h3 class="mb-1">{{ __('modules_title') }}</h3>
            <p class="text-muted mb-0">{{ __('modules_subtitle') ?? 'Gestiona los módulos activos en tu sitio' }}</p>
        </div>
        <div class="modules-stats">
            <div class="stat-badge active">
                <i class="bi bi-check-circle-fill"></i>
                <span>{{ $activeCount }} {{ __('active') ?? 'activos' }}</span>
            </div>
            <div class="stat-badge">
                <i class="bi bi-grid-3x3-gap"></i>
                <span>{{ $totalCount }} {{ __('total') ?? 'total' }}</span>
            </div>
        </div>
    </div>

    @if (empty($modules))
        <div class="alert alert-info" role="alert">
            <i class="bi bi-info-circle me-2"></i>
            {{ __('no_modules_available') ?? 'No hay módulos disponibles en este momento.' }}
        </div>
    @else
        <div class="module-list">
            @foreach ($modules as $module)
                @php
                    $isEnabled = !empty($module['tenant_enabled']);
                    $iconMap = [
                        'blog' => 'bi-journal-richtext',
                        'pages' => 'bi-file-earmark-text',
                        'media' => 'bi-images',
                        'custom-forms' => 'bi-ui-checks',
                        'image-gallery' => 'bi-card-image',
                        'react-sliders' => 'bi-sliders2',
                        'instagram-gallery' => 'bi-instagram',
                        'tickets' => 'bi-ticket-detailed',
                    ];
                    $icon = $iconMap[$module['slug'] ?? ''] ?? 'bi-puzzle';
                @endphp
                <div class="module-item {{ $isEnabled ? 'enabled' : 'disabled' }}">
                    <div class="module-icon {{ $isEnabled ? 'enabled' : 'disabled' }}">
                        <i class="bi {{ $icon }}"></i>
                    </div>

                    <div class="module-info">
                        <div class="module-name">
                            {{ $module['name'] }}
                        </div>
                        <p class="module-description">{{ $module['description'] ?? __('no_description') ?? 'Sin descripción disponible' }}</p>
                    </div>

                    <div class="module-meta">
                        @if(!empty($module['version']))
                            <span class="module-version">v{{ $module['version'] }}</span>
                        @endif
                        <div class="module-status {{ $isEnabled ? 'active' : 'inactive' }}">
                            <i class="bi {{ $isEnabled ? 'bi-check-circle-fill' : 'bi-dash-circle' }}"></i>
                            {{ $isEnabled ? (__('active') ?? 'Activo') : (__('inactive') ?? 'Inactivo') }}
                        </div>
                    </div>

                    <div class="module-actions">
                        <form method="POST" action="{{ admin_url('/modules/' . $module['id'] . '/toggle') }}"
                              class="module-toggle-form"
                              data-module-name="{{ $module['name'] }}"
                              data-module-action="{{ $isEnabled ? 'desactivar' : 'activar' }}">
                            {!! csrf_field() !!}
                            <input type="hidden" name="password" class="password-input">
                            <button type="button" class="btn btn-sm btn-toggle {{ $isEnabled ? 'btn-outline-danger' : 'btn-success' }} toggle-module-btn">
                                <i class="bi {{ $isEnabled ? 'bi-x-lg' : 'bi-check-lg' }} me-1"></i>
                                {{ $isEnabled ? (__('deactivate') ?? 'Desactivar') : (__('activate') ?? 'Activar') }}
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection

{{-- SweetAlert2 para mensajes flash --}}
@php
    $successMessage = consume_flash('success');
    $errorMessage = consume_flash('error');
    $warningMessage = consume_flash('warning');
@endphp

@if ($successMessage)
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: '{{ __("success") ?? "Éxito" }}',
            text: {!! json_encode($successMessage) !!},
            confirmButtonText: '{{ __("accept") ?? "Aceptar" }}',
            timer: 3000,
            timerProgressBar: true
        });
    }
});
</script>
@endif

@if ($errorMessage)
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: {!! json_encode($errorMessage) !!},
            confirmButtonText: '{{ __("accept") ?? "Aceptar" }}'
        });
    }
});
</script>
@endif

@if ($warningMessage)
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'warning',
            title: '{{ __("attention") ?? "Atención" }}',
            text: {!! json_encode($warningMessage) !!},
            confirmButtonText: '{{ __("accept") ?? "Aceptar" }}'
        });
    }
});
</script>
@endif

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.toggle-module-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const form = this.closest('.module-toggle-form');
            const moduleName = form.dataset.moduleName;
            const action = form.dataset.moduleAction;
            const isActivating = action === 'activar';

            Swal.fire({
                title: '<i class="bi bi-shield-lock text-primary"></i> {{ __("confirmation_required") ?? "Confirmación requerida" }}',
                html: `
                    <div class="text-start">
                        <p class="mb-3">Estás a punto de <strong>${action}</strong> el módulo <strong>${moduleName}</strong>.</p>
                        <p class="mb-2 text-muted small">Por seguridad, introduce tu contraseña para confirmar:</p>
                    </div>
                `,
                input: 'password',
                inputPlaceholder: '{{ __("enter_password") ?? "Introduce tu contraseña" }}',
                inputAttributes: {
                    autocapitalize: 'off',
                    autocorrect: 'off',
                    autocomplete: 'current-password'
                },
                showCancelButton: true,
                confirmButtonText: `<i class="bi ${isActivating ? 'bi-check-lg' : 'bi-x-lg'} me-1"></i> ${isActivating ? '{{ __("activate") ?? "Activar" }}' : '{{ __("deactivate") ?? "Desactivar" }}'}`,
                cancelButtonText: '{{ __("cancel") ?? "Cancelar" }}',
                confirmButtonColor: isActivating ? '#198754' : '#dc3545',
                cancelButtonColor: '#6c757d',
                showLoaderOnConfirm: true,
                preConfirm: (password) => {
                    if (!password) {
                        Swal.showValidationMessage('{{ __("password_required") ?? "Debes introducir tu contraseña" }}');
                        return false;
                    }
                    return password;
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed) {
                    form.querySelector('.password-input').value = result.value;
                    form.submit();
                }
            });
        });
    });
});
</script>
