@extends('layouts.app')

@section('title', __('dashboard.title'))

@section('content')


<div class="app-content">
    <div class="container-fluid">

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

        {{-- Alerta de seeders faltantes - Debajo de los cards --}}
        @if (!empty($missingSeeders))
        <div class="row mt-4">
            <div class="col-12">
                <div class="card border-warning" id="missing-seeders-card">
                    <div class="card-header bg-warning text-dark d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>{{ __('dashboard.missing_seeders_title') }}</strong>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">{{ __('dashboard.missing_seeders_description') }}</p>

                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Seeder</th>
                                        <th>{{ __('common.description') ?? 'Descripción' }}</th>
                                        <th class="text-center" style="width: 120px;">{{ __('common.actions') ?? 'Acción' }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($missingSeeders as $seeder)
                                    <tr>
                                        <td><code>{{ $seeder['name'] }}</code></td>
                                        <td>{{ $seeder['description'] }}</td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-warning run-seeder-btn" data-seeder="{{ $seeder['key'] }}">
                                                <i class="bi bi-play-fill"></i> Ejecutar
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-warning" id="run-all-seeders">
                                <i class="bi bi-play-circle-fill me-1"></i> {{ __('dashboard.run_all_seeders') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

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
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        // Deshabilitar todos los botones mientras se ejecuta
        document.querySelectorAll('.run-seeder-btn, #run-all-seeders').forEach(btn => btn.disabled = true);

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
                const card = document.getElementById('missing-seeders-card');
                card.className = 'card border-success';
                card.querySelector('.card-header').className = 'card-header bg-success text-white d-flex align-items-center';
                card.querySelector('.card-header').innerHTML = `
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <strong>{{ __('dashboard.seeders_success') }}</strong>
                `;
                card.querySelector('.card-body').innerHTML = `
                    <div class="text-center py-3">
                        <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                        <p class="mt-2 mb-0">${data.message}</p>
                        <small class="text-muted">{{ __('dashboard.seeders_reloading') }}</small>
                    </div>
                `;
                // Recargar página después de 2 segundos
                setTimeout(() => window.location.reload(), 2000);
            } else {
                // Rehabilitar botones en caso de error
                document.querySelectorAll('.run-seeder-btn, #run-all-seeders').forEach(btn => btn.disabled = false);
                button.innerHTML = originalText;

                // Mostrar error con SweetAlert si está disponible, sino alert normal
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: '{{ __('dashboard.seeders_error') }}',
                        text: data.error || 'Error desconocido'
                    });
                } else {
                    alert('Error: ' + (data.error || 'Error desconocido'));
                }
            }
        })
        .catch(error => {
            document.querySelectorAll('.run-seeder-btn, #run-all-seeders').forEach(btn => btn.disabled = false);
            button.innerHTML = originalText;

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    text: error.message
                });
            } else {
                alert('Error de conexión: ' + error.message);
            }
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
