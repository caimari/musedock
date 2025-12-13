@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="themes-manager">
    <div class="page-header mb-4">
        <h2>Gestión de Temas</h2>
        <p class="text-muted">Personaliza el aspecto de tu sitio web con temas oficiales o personalizados</p>
    </div>

    <div class="actions-bar mb-4">
        <button class="btn btn-primary" onclick="document.getElementById('upload-form').style.display='block'">
            <i class="fas fa-upload"></i> Instalar Tema Personalizado
        </button>
    </div>

    <!-- Formulario de subida -->
    <div id="upload-form" style="display:none;" class="card mb-4">
        <div class="card-body">
            <h4 class="card-title">Subir Tema Personalizado</h4>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Importante:</strong> Todos los temas se validan automáticamente por seguridad.
                Solo temas validados pueden activarse.
            </div>

            <form action="{{ admin_url('themes/upload') }}" method="POST" enctype="multipart/form-data">
                {!! csrf_field() !!}
                <div class="mb-3">
                    <label for="theme_zip" class="form-label">Archivo ZIP del tema:</label>
                    <input type="file" class="form-control" id="theme_zip" name="theme_zip" accept=".zip" required>
                    <small class="form-text text-muted">Máximo 20MB. Solo archivos ZIP.</small>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Instalar
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('upload-form').style.display='none'">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Temas Globales -->
    <div class="themes-section mb-5">
        <h3 class="mb-3">
            <i class="fas fa-globe"></i> Temas Oficiales
            <span class="badge bg-success">Seguros</span>
        </h3>
        <p class="text-muted mb-4">Temas desarrollados por MuseDock, totalmente seguros y optimizados</p>

        <div class="row g-4">
            @forelse($globalThemes as $theme)
            <div class="col-md-4">
                <div class="card h-100 @if($tenant['theme_type'] === 'global' && $tenant['theme'] === $theme['slug']) border-primary @endif">
                    @if($theme['screenshot'])
                    <img src="/themes/{{ $theme['slug'] }}/{{ $theme['screenshot'] }}"
                         class="card-img-top"
                         alt="{{ $theme['name'] }}"
                         style="height: 200px; object-fit: cover;">
                    @else
                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                        <i class="fas fa-image fa-3x text-muted"></i>
                    </div>
                    @endif

                    <div class="card-body">
                        <h5 class="card-title">{{ $theme['name'] }}</h5>
                        <p class="card-text text-muted small">{{ $theme['description'] }}</p>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted">Por: {{ $theme['author'] }}</small>
                            <small class="text-muted">v{{ $theme['version'] }}</small>
                        </div>

                        <div class="mb-2">
                            <span class="badge bg-success"><i class="fas fa-check-circle"></i> Oficial</span>
                        </div>
                    </div>

                    <div class="card-footer bg-white">
                        @if($tenant['theme_type'] === 'global' && $tenant['theme'] === $theme['slug'])
                            <div class="d-flex gap-2">
                                <span class="badge bg-primary flex-grow-1 text-center py-2">
                                    <i class="fas fa-check"></i> ACTIVO
                                </span>
                                <a href="/{{ admin_path() }}/widgets/{{ $theme['slug'] }}" class="btn btn-info btn-sm" title="Gestionar Widgets">
                                    <i class="fas fa-th-large"></i>
                                </a>
                            </div>
                        @else
                            <form action="{{ admin_url('themes/activate-global/' . $theme['slug']) }}" method="POST">
                                {!! csrf_field() !!}
                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                    <i class="fas fa-power-off"></i> Activar
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No hay temas globales disponibles.
                </div>
            </div>
            @endforelse
        </div>
    </div>

    <!-- Temas Personalizados -->
    <div class="themes-section">
        <h3 class="mb-3">
            <i class="fas fa-palette"></i> Mis Temas Personalizados
        </h3>
        <p class="text-muted mb-4">Temas exclusivos de tu sitio, aislados y validados por seguridad</p>

        @if(empty($customThemes))
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-paint-brush fa-3x text-muted mb-3"></i>
                    <h5>No tienes temas personalizados instalados</h5>
                    <p class="text-muted">Los temas personalizados te permiten diseños únicos para tu sitio.</p>
                    <button class="btn btn-primary mt-3" onclick="document.getElementById('upload-form').style.display='block'">
                        <i class="fas fa-upload"></i> Instalar Primer Tema
                    </button>
                </div>
            </div>
        @else
            <div class="row g-4">
                @foreach($customThemes as $theme)
                <div class="col-md-4">
                    <div class="card h-100 @if($tenant['theme_type'] === 'custom' && $tenant['custom_theme_slug'] === $theme['slug']) border-primary @endif">
                        @if($theme['screenshot'])
                        <img src="/storage/tenants/{{ $tenantId }}/themes/{{ $theme['slug'] }}/{{ $theme['screenshot'] }}"
                             class="card-img-top"
                             alt="{{ $theme['name'] }}"
                             style="height: 200px; object-fit: cover;">
                        @else
                        <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                            <i class="fas fa-image fa-3x text-muted"></i>
                        </div>
                        @endif

                        <div class="card-body">
                            <h5 class="card-title">{{ $theme['name'] }}</h5>
                            <p class="card-text text-muted small">{{ $theme['description'] ?? 'Sin descripción' }}</p>

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="text-muted">Por: {{ $theme['author'] ?? 'Desconocido' }}</small>
                                <small class="text-muted">v{{ $theme['version'] }}</small>
                            </div>

                            <div class="mb-2">
                                @if($theme['validated'])
                                    <span class="badge bg-success" title="Tema validado y seguro">
                                        <i class="fas fa-shield-alt"></i> Validado ({{ $theme['security_score'] }}/100)
                                    </span>
                                @else
                                    <span class="badge bg-danger" title="Tema no pasó validación de seguridad">
                                        <i class="fas fa-exclamation-triangle"></i> No Validado
                                    </span>
                                @endif
                                <span class="badge bg-info">
                                    <i class="fas fa-lock"></i> Privado
                                </span>
                            </div>

                            @if(!empty($theme['validation_errors']) && $theme['validation_errors'] !== '[]')
                            <details class="mt-2">
                                <summary class="text-danger small" style="cursor: pointer;">
                                    <i class="fas fa-exclamation-circle"></i> Ver errores de validación
                                </summary>
                                <ul class="small mt-2 text-danger">
                                    @foreach(json_decode($theme['validation_errors'], true) as $error)
                                    <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </details>
                            @endif
                        </div>

                        <div class="card-footer bg-white">
                            <div class="d-flex gap-2 flex-wrap">
                                @if($tenant['theme_type'] === 'custom' && $tenant['custom_theme_slug'] === $theme['slug'])
                                    <span class="badge bg-primary flex-grow-1 text-center py-2">
                                        <i class="fas fa-check"></i> ACTIVO
                                    </span>
                                    <a href="/{{ admin_path() }}/widgets/{{ $theme['slug'] }}" class="btn btn-info btn-sm" title="Gestionar Widgets">
                                        <i class="fas fa-th-large"></i>
                                    </a>
                                @elseif($theme['validated'])
                                    <form action="{{ admin_url('themes/activate-custom/' . $theme['slug']) }}" method="POST" class="flex-grow-1">
                                        {!! csrf_field() !!}
                                        <button type="submit" class="btn btn-success btn-sm w-100">
                                            <i class="fas fa-power-off"></i> Activar
                                        </button>
                                    </form>
                                @else
                                    <button class="btn btn-secondary btn-sm flex-grow-1" disabled title="Tema no validado">
                                        <i class="fas fa-lock"></i> No Seguro
                                    </button>
                                @endif

                                <form action="{{ admin_url('themes/revalidate/' . $theme['slug']) }}" method="POST">
                                    {!! csrf_field() !!}
                                    <button type="submit" class="btn btn-info btn-sm" title="Revalidar tema">
                                        <i class="fas fa-sync"></i>
                                    </button>
                                </form>

                                @if(!$theme['active'])
                                <form action="{{ admin_url('themes/uninstall/' . $theme['slug']) }}" method="POST"
                                      onsubmit="return confirm('¿Estás seguro de que deseas eliminar este tema?')">
                                    {!! csrf_field() !!}
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" title="Desinstalar tema">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Información de seguridad -->
    <div class="card mt-5 bg-light">
        <div class="card-body">
            <h4 class="card-title">
                <i class="fas fa-shield-alt text-primary"></i> Seguridad de Temas
            </h4>
            <div class="row mt-3">
                <div class="col-md-6">
                    <h6><i class="fas fa-check-circle text-success"></i> Temas Oficiales</h6>
                    <ul class="small">
                        <li>Desarrollados por MuseDock</li>
                        <li>100% seguros y optimizados</li>
                        <li>Actualizaciones automáticas</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6><i class="fas fa-user-shield text-info"></i> Temas Personalizados</h6>
                    <ul class="small">
                        <li>Validación automática de seguridad</li>
                        <li>Detección de código malicioso</li>
                        <li>Aislamiento por tenant (no afectan a otros sitios)</li>
                        <li>Score de seguridad 0-100</li>
                    </ul>
                </div>
            </div>
            <div class="alert alert-info mt-3 mb-0">
                <strong><i class="fas fa-info-circle"></i> Importante:</strong>
                Los temas personalizados se validan automáticamente buscando funciones peligrosas,
                XSS, SQL injection y otros riesgos. Solo temas validados pueden activarse.
            </div>
        </div>
    </div>
</div>

<style>
.themes-manager .card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.themes-manager .card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important;
}

.themes-manager .badge {
    font-size: 0.75rem;
}

.themes-manager details summary {
    list-style: none;
}

.themes-manager details summary::-webkit-details-marker {
    display: none;
}
</style>

<script>
// Cerrar el formulario de subida con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('upload-form').style.display = 'none';
    }
});
</script>
@endsection
