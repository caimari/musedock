@extends('layouts.app')

@section('title', __('modules_title'))

@section('content')
<div class="container-fluid">
    <h3 class="mb-3">{{ __('modules_title') }}</h3>

    <div class="row">
        @if (empty($modules))
            <div class="col-12">
                <div class="alert alert-info" role="alert">
                    <i class="bi bi-info-circle me-2"></i>
                    {{ __('no_modules_available') ?? 'No hay módulos disponibles en este momento.' }}
                </div>
            </div>
        @endif

        @foreach ($modules as $module)
            <div class="col-md-4 mb-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">{{ $module['name'] }}</h5>
                        <p class="card-text flex-grow-1">{{ $module['description'] ?? __('no_description') ?? 'Sin descripción' }}</p>

                        <div class="mt-auto">
                            <form method="POST" action="{{ admin_url('/modules/' . $module['id'] . '/toggle') }}" class="module-toggle-form" data-module-name="{{ $module['name'] }}" data-module-action="{{ $module['tenant_enabled'] ? 'desactivar' : 'activar' }}">
                                {!! csrf_field() !!}
                                <input type="hidden" name="password" class="password-input">
                                <button type="button" class="btn btn-sm {{ $module['tenant_enabled'] ? 'btn-danger' : 'btn-success' }} toggle-module-btn">
                                    <i class="bi {{ $module['tenant_enabled'] ? 'bi-x-circle' : 'bi-check-circle' }} me-1"></i>
                                    {{ $module['tenant_enabled'] ? (__('deactivate') ?? 'Desactivar') : (__('activate') ?? 'Activar') }}
                                </button>
                            </form>

                            @if ($module['tenant_enabled'])
                                <span class="badge bg-success ms-2">
                                    <i class="bi bi-check-circle-fill"></i> {{ __('active') ?? 'Activo' }}
                                </span>
                            @else
                                <span class="badge bg-secondary ms-2">
                                    <i class="bi bi-dash-circle-fill"></i> {{ __('inactive') ?? 'Inactivo' }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
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
                    title: '¡Éxito!',
                    text: '{{ $successMessage }}',
                    confirmButtonText: 'Aceptar',
                    timer: 3000,
                    timerProgressBar: true
                });
            } else {
                alert('{{ $successMessage }}');
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
                    text: '{{ $errorMessage }}',
                    confirmButtonText: 'Aceptar'
                });
            } else {
                alert('Error: {{ $errorMessage }}');
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
                    title: 'Atención',
                    text: '{{ $warningMessage }}',
                    confirmButtonText: 'Aceptar'
                });
            } else {
                alert('Atención: {{ $warningMessage }}');
            }
        });
    </script>
@endif

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Manejar clics en botones de toggle
    document.querySelectorAll('.toggle-module-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const form = this.closest('.module-toggle-form');
            const moduleName = form.dataset.moduleName;
            const action = form.dataset.moduleAction;

            // Mostrar SweetAlert2 pidiendo contraseña
            Swal.fire({
                title: '🔐 Confirmación requerida',
                html: `
                    <p class="mb-3">Estás a punto de <strong>${action}</strong> el módulo <strong>${moduleName}</strong>.</p>
                    <p class="mb-2">Por favor, introduce tu contraseña para confirmar:</p>
                `,
                input: 'password',
                inputPlaceholder: 'Introduce tu contraseña',
                inputAttributes: {
                    autocapitalize: 'off',
                    autocorrect: 'off',
                    autocomplete: 'current-password'
                },
                showCancelButton: true,
                confirmButtonText: 'Confirmar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: action === 'activar' ? '#198754' : '#dc3545',
                showLoaderOnConfirm: true,
                preConfirm: (password) => {
                    if (!password) {
                        Swal.showValidationMessage('Debes introducir tu contraseña');
                        return false;
                    }
                    return password;
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed) {
                    // Asignar contraseña al campo hidden y enviar formulario
                    form.querySelector('.password-input').value = result.value;
                    form.submit();
                }
            });
        });
    });
});
</script>
