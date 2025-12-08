@extends('layouts.app')

@section('title', __('dashboard.title'))

@section('content')


<div class="app-content">
    <div class="container-fluid">

        {{-- Alerta de seeders faltantes --}}
        @if (!empty($missingSeeders))
        <div class="alert alert-warning alert-dismissible fade show" role="alert" id="missing-seeders-alert">
            <div class="d-flex align-items-start">
                <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
                <div class="flex-grow-1">
                    <h5 class="alert-heading mb-2">{{ __('dashboard.missing_seeders_title', [], 'Configuración incompleta') }}</h5>
                    <p class="mb-2">{{ __('dashboard.missing_seeders_description', [], 'Se detectaron datos faltantes que pueden afectar el funcionamiento del sistema:') }}</p>
                    <ul class="mb-3">
                        @foreach ($missingSeeders as $seeder)
                        <li><strong>{{ $seeder['name'] }}</strong>: {{ $seeder['description'] }}</li>
                        @endforeach
                    </ul>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-warning" id="run-all-seeders">
                            <i class="bi bi-play-fill me-1"></i> {{ __('dashboard.run_all_seeders', [], 'Ejecutar todos los seeders') }}
                        </button>
                        @foreach ($missingSeeders as $seeder)
                        <button type="button" class="btn btn-outline-warning btn-sm run-seeder-btn" data-seeder="{{ $seeder['key'] }}">
                            <i class="bi bi-play me-1"></i> {{ $seeder['name'] }}
                        </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="card">
            <div class="card-body">
                <h5>{{ __('dashboard.welcome', ['name' => $_SESSION['super_admin']['email'] ?? 'Admin']) }}</h5>
                <p>{{ __('dashboard.welcome_message') }}</p>
                <a href="/musedock/logout" class="btn btn-danger mt-2">{{ __('auth.logout') }}</a>
            </div>
        </div>

        {{-- Aquí podrías cargar estadísticas, resumen de uso, widgets, etc. --}}
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card text-bg-light">
                    <div class="card-header">{{ __('dashboard.active_tenants') }}</div>
                    <div class="card-body">
                        <p class="card-text">{{ __('dashboard.add_stats') }}</p>
                        <a href="/musedock/tenants" class="btn btn-outline-primary">{{ __('tenants.view_all') }}</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card text-bg-light">
                    <div class="card-header">{{ __('dashboard.available_modules') }}</div>
                    <div class="card-body">
                        <p class="card-text">{{ __('dashboard.manage_modules_desc') }}</p>
                        <a href="/musedock/modules" class="btn btn-outline-secondary">{{ __('modules.manage') }}</a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
@if (!empty($missingSeeders))
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // Función para ejecutar seeder
    function runSeeder(seederKey, button) {
        const originalText = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Ejecutando...';

        fetch('/musedock/run-seeders', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrfToken
            },
            body: 'seeder=' + encodeURIComponent(seederKey)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mostrar mensaje de éxito
                const alert = document.getElementById('missing-seeders-alert');
                alert.className = 'alert alert-success';
                alert.innerHTML = `
                    <div class="d-flex align-items-center">
                        <i class="bi bi-check-circle-fill me-3 fs-4"></i>
                        <div>
                            <strong>¡Completado!</strong> ${data.message}
                            <br><small>Recargando página...</small>
                        </div>
                    </div>
                `;
                // Recargar página después de 2 segundos
                setTimeout(() => window.location.reload(), 2000);
            } else {
                button.disabled = false;
                button.innerHTML = originalText;
                alert('Error: ' + (data.error || 'Error desconocido'));
            }
        })
        .catch(error => {
            button.disabled = false;
            button.innerHTML = originalText;
            alert('Error de conexión: ' + error.message);
        });
    }

    // Ejecutar todos los seeders
    document.getElementById('run-all-seeders')?.addEventListener('click', function() {
        runSeeder('all', this);
    });

    // Ejecutar seeder individual
    document.querySelectorAll('.run-seeder-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            runSeeder(this.dataset.seeder, this);
        });
    });
});
</script>
@endif
@endpush
