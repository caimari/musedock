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
                            <form method="POST" action="{{ admin_url('/modules/' . $module['id'] . '/toggle') }}" class="d-inline">
                                {!! csrf_field() !!}
                                <button type="submit" class="btn btn-sm {{ $module['tenant_enabled'] ? 'btn-danger' : 'btn-success' }}">
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
        Swal.fire({
            icon: 'success',
            title: '¡Éxito!',
            text: '{{ $successMessage }}',
            confirmButtonText: 'Aceptar',
            timer: 3000,
            timerProgressBar: true
        });
    </script>
@endif

@if ($errorMessage)
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '{{ $errorMessage }}',
            confirmButtonText: 'Aceptar'
        });
    </script>
@endif

@if ($warningMessage)
    <script>
        Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: '{{ $warningMessage }}',
            confirmButtonText: 'Aceptar'
        });
    </script>
@endif
