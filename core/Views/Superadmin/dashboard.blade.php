@extends('layouts.app')

@section('title', __('dashboard.title'))

@section('content')
<div class="app-content">
    <div class="container-fluid">

        {{-- Header del dashboard --}}
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
            <div class="d-flex align-items-center gap-3">
                <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#3b7ddd,#6ea8fe);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi bi-speedometer2" style="font-size:1.35rem;color:#fff;"></i>
                </div>
                <div>
                    <h3 class="mb-0" style="font-size:1.25rem;font-weight:700;">{{ __('dashboard.welcome', ['name' => $name ?? 'Admin']) }}</h3>
                    <p class="text-muted mb-0" style="font-size:0.85rem;">Panel de administración de {{ cms_version('name') }} v{{ cms_version('version') }}</p>
                </div>
            </div>
            <div style="display:flex;gap:1rem;">
                <a href="/" target="_blank" style="display:flex;align-items:center;gap:0.35rem;font-size:0.85rem;padding:0.4rem 0.75rem;border-radius:6px;background:#f8f9fa;border:1px solid #e9ecef;color:#6c757d;text-decoration:none;">
                    <i class="bi bi-box-arrow-up-right"></i>
                    <span>Visitar sitio</span>
                </a>
                <a href="/musedock/logout" style="display:flex;align-items:center;gap:0.35rem;font-size:0.85rem;padding:0.4rem 0.75rem;border-radius:6px;background:rgba(220,53,69,0.08);border:1px solid rgba(220,53,69,0.2);color:#dc3545;text-decoration:none;">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Cerrar sesión</span>
                </a>
            </div>
        </div>

        {{-- Accesos directos (plugins + módulos con show_in_dashboard) --}}
        @if (!empty($activePlugins) || !empty($dashboardModules))
        @php
            $pluginUrlMap = [
                'caddy-domain-manager' => '/musedock/domain-manager',
                'cross-publisher' => '/musedock/cross-publisher',
                'news-aggregator' => '/musedock/news-aggregator',
                'ai-skin-generator' => '/musedock/ai-skin-generator',
                'theme-extractor' => '/musedock/theme-extractor',
            ];
            $pluginIconMap = [
                'caddy-domain-manager' => 'bi-globe',
                'cross-publisher' => 'bi-share',
                'news-aggregator' => 'bi-newspaper',
                'ai-skin-generator' => 'bi-palette',
                'theme-extractor' => 'bi-brush',
            ];
            $pluginColorMap = [
                'caddy-domain-manager' => ['#0d6efd', '#6ea8fe'],
                'cross-publisher' => ['#198754', '#20c997'],
                'news-aggregator' => ['#fd7e14', '#ffb74d'],
                'ai-skin-generator' => ['#d63384', '#e685b5'],
                'theme-extractor' => ['#6f42c1', '#a370db'],
            ];
        @endphp
        <div class="row mb-4">
            <div class="col-12">
                <h6 class="text-muted mb-3" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;">
                    <i class="bi bi-lightning-charge me-1"></i> Accesos directos
                </h6>
            </div>
            @foreach ($activePlugins as $ap)
            @php
                $apUrl = $pluginUrlMap[$ap['slug']] ?? '/musedock/plugins';
                $apIcon = $pluginIconMap[$ap['slug']] ?? 'bi-plug';
                $apColors = $pluginColorMap[$ap['slug']] ?? ['#6c757d', '#adb5bd'];
            @endphp
            <div class="col-md-4 col-lg-3 mb-3">
                <a href="{{ $apUrl }}" class="card border-0 shadow-sm text-decoration-none h-100" style="transition:transform 0.15s,box-shadow 0.15s;"
                   onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'"
                   onmouseout="this.style.transform='';this.style.boxShadow=''">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <div style="width:42px;height:42px;border-radius:10px;background:linear-gradient(135deg,{{ $apColors[0] }},{{ $apColors[1] }});display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi {{ $apIcon }}" style="font-size:1.2rem;color:#fff;"></i>
                        </div>
                        <div>
                            <div style="font-weight:600;font-size:0.9rem;color:#212529;">{{ $ap['name'] }}</div>
                            <div style="font-size:0.75rem;color:#6c757d;">{{ mb_strimwidth($ap['description'] ?? '', 0, 45, '...') }}</div>
                        </div>
                    </div>
                </a>
            </div>
            @endforeach

            @php
                $moduleIconMap = [
                    'blog' => 'bi-journal-richtext',
                    'custom-forms' => 'bi-ui-checks',
                    'image-gallery' => 'bi-card-image',
                    'react-sliders' => 'bi-sliders2',
                    'instagram-gallery' => 'bi-instagram',
                    'media-manager' => 'bi-images',
                    'wp-importer' => 'bi-wordpress',
                    'ai-writer' => 'bi-pencil-square',
                    'ai-image' => 'bi-stars',
                    'elements' => 'bi-bricks',
                ];
                $moduleUrlMap = [
                    'blog' => '/musedock/blog/posts',
                    'custom-forms' => '/musedock/custom-forms',
                    'image-gallery' => '/musedock/image-gallery',
                    'instagram-gallery' => '/musedock/social-publisher',
                    'media-manager' => '/musedock/media',
                    'wp-importer' => '/musedock/wp-importer',
                    'ai-writer' => '/musedock/aiwriter/settings',
                    'ai-image' => '/musedock/ai-image/settings',
                    'elements' => '/musedock/elements',
                    'react-sliders' => '/musedock/sliders',
                ];
            @endphp
            @foreach ($dashboardModules ?? [] as $dm)
            @php
                $dmUrl = $moduleUrlMap[$dm['slug']] ?? '/musedock/modules';
                $dmIcon = $moduleIconMap[$dm['slug']] ?? 'bi-puzzle';
            @endphp
            <div class="col-md-4 col-lg-3 mb-3">
                <a href="{{ $dmUrl }}" class="card border-0 shadow-sm text-decoration-none h-100" style="transition:transform 0.15s,box-shadow 0.15s;"
                   onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'"
                   onmouseout="this.style.transform='';this.style.boxShadow=''">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <div style="width:42px;height:42px;border-radius:10px;background:linear-gradient(135deg,#198754,#20c997);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi {{ $dmIcon }}" style="font-size:1.2rem;color:#fff;"></i>
                        </div>
                        <div>
                            <div style="font-weight:600;font-size:0.9rem;color:#212529;">{{ $dm['name'] }}</div>
                            <div style="font-size:0.75rem;color:#6c757d;">{{ mb_strimwidth($dm['description'] ?? '', 0, 45, '...') }}</div>
                        </div>
                    </div>
                </a>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Cards de gestión --}}
        <h6 class="text-muted mb-3" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;">
            <i class="bi bi-grid-3x3-gap me-1"></i> Gestión
        </h6>
        <div class="row mb-4">
            @php
                $managementCards = [
                    ['url' => '/musedock/tenants', 'icon' => 'bi-people', 'colors' => ['#0d6efd','#6ea8fe'], 'title' => __('dashboard.active_tenants'), 'desc' => __('tenants.view_all')],
                    ['url' => '/musedock/modules', 'icon' => 'bi-puzzle', 'colors' => ['#198754','#20c997'], 'title' => __('dashboard.available_modules'), 'desc' => __('modules.manage')],
                    ['url' => '/musedock/plugins', 'icon' => 'bi-plug', 'colors' => ['#6f42c1','#a370db'], 'title' => 'Plugins', 'desc' => 'Gestionar plugins del sistema'],
                    ['url' => '/musedock/settings', 'icon' => 'bi-gear', 'colors' => ['#6c757d','#adb5bd'], 'title' => 'Ajustes', 'desc' => 'Configuración del sitio'],
                    ['url' => '/musedock/tickets', 'icon' => 'bi-ticket-detailed', 'colors' => ['#fd7e14','#ffb74d'], 'title' => 'Tickets', 'desc' => 'Soporte técnico'],
                    ['url' => '/musedock/logs', 'icon' => 'bi-terminal', 'colors' => ['#495057','#6c757d'], 'title' => 'Logs', 'desc' => 'Registros del sistema'],
                ];
            @endphp
            @foreach($managementCards as $mc)
            <div class="col-md-4 col-lg-3 mb-3">
                <a href="{{ $mc['url'] }}" class="card border-0 shadow-sm text-decoration-none h-100" style="transition:transform 0.15s,box-shadow 0.15s;"
                   onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'"
                   onmouseout="this.style.transform='';this.style.boxShadow=''">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <div style="width:42px;height:42px;border-radius:10px;background:linear-gradient(135deg,{{ $mc['colors'][0] }},{{ $mc['colors'][1] }});display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi {{ $mc['icon'] }}" style="font-size:1.2rem;color:#fff;"></i>
                        </div>
                        <div>
                            <div style="font-weight:600;font-size:0.9rem;color:#212529;">{{ $mc['title'] }}</div>
                            <div style="font-size:0.75rem;color:#6c757d;">{{ $mc['desc'] }}</div>
                        </div>
                    </div>
                </a>
            </div>
            @endforeach
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
                                        <th>{{ __('common.description') }}</th>
                                        <th class="text-center" style="width: 120px;">{{ __('common.actions') }}</th>
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
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: '_token=' + encodeURIComponent(csrfToken) + '&seeder=' + encodeURIComponent(seederKey)
        })
        .then(response => {
            // Guardar el texto de la respuesta para debug
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    // Si no es JSON, lanzar error con el contenido
                    throw new Error('Respuesta no válida del servidor:\n\n' + text.substring(0, 500));
                }
            });
        })
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

                // Construir mensaje de error detallado
                let errorMsg = data.error || 'Error desconocido';
                if (data.file) errorMsg += '\n\nArchivo: ' + data.file;
                if (data.line) errorMsg += '\nLínea: ' + data.line;
                if (data.output) errorMsg += '\n\nOutput: ' + data.output;

                // Mostrar error con SweetAlert si está disponible, sino alert normal
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: '{{ __('dashboard.seeders_error') }}',
                        html: '<pre style="text-align:left;font-size:12px;max-height:300px;overflow:auto;">' + errorMsg.replace(/</g, '&lt;') + '</pre>',
                        width: 600
                    });
                } else {
                    alert('Error: ' + errorMsg);
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
                    html: '<pre style="text-align:left;font-size:12px;max-height:300px;overflow:auto;">' + error.message.replace(/</g, '&lt;') + '</pre>',
                    width: 600
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
