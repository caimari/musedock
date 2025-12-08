@extends('layouts.app')

@section('title', 'Mis Plugins Privados')

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>🔌 Mis Plugins Privados</h2>
                <p class="text-muted">Plugins personalizados instalados exclusivamente para tu sitio</p>
            </div>
            <div class="d-flex gap-2">
                <form action="/admin/plugins/sync" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-repeat"></i> Sincronizar
                    </button>
                </form>
                <button class="btn btn-primary" onclick="toggleUploadForm()">
                    <i class="bi bi-upload"></i> Instalar Plugin
                </button>
            </div>
        </div>

        <!-- Formulario de subida (oculto por defecto) -->
        <div id="upload-form" class="card mb-4" style="display:none;">
            <div class="card-header bg-primary text-white">
                <h4 class="card-title mb-0">📦 Instalar Nuevo Plugin</h4>
            </div>
            <div class="card-body">
                <form action="/admin/plugins/upload" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label for="plugin_zip" class="form-label">Archivo ZIP del plugin:</label>
                        <input
                            type="file"
                            id="plugin_zip"
                            name="plugin_zip"
                            accept=".zip"
                            required
                            class="form-control"
                        >
                        <small class="form-text text-muted">Máximo 10MB. Solo archivos ZIP.</small>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-lg"></i> Instalar Plugin
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="toggleUploadForm()">
                            <i class="bi bi-x-lg"></i> Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de plugins -->
        @if(empty($plugins))
            <div class="card">
                <div class="card-body text-center py-5">
                    <div style="font-size: 4rem; opacity: 0.3;">🔌</div>
                    <h3 class="mt-3">No tienes plugins personalizados instalados</h3>
                    <p class="text-muted">Los plugins te permiten agregar funcionalidad exclusiva para tu sitio.</p>
                    <p class="text-muted">Instala un plugin usando el botón "Instalar Plugin" arriba.</p>
                </div>
            </div>
        @else
            <div class="row">
                @foreach($plugins as $plugin)
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card h-100 {{ $plugin['active'] ? 'border-success' : 'border-secondary' }}">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title mb-1">{{ $plugin['name'] }}</h5>
                                <span class="badge bg-secondary">v{{ $plugin['version'] }}</span>
                            </div>
                            @if($plugin['active'])
                                <span class="badge bg-success">Activo</span>
                            @else
                                <span class="badge bg-secondary">Inactivo</span>
                            @endif
                        </div>
                        <div class="card-body">
                            <p class="card-text">{{ $plugin['description'] ?: 'Sin descripción disponible' }}</p>

                            <div class="mt-3">
                                <small class="d-block text-muted"><strong>Autor:</strong> {{ $plugin['author'] ?: 'Desconocido' }}</small>
                                <small class="d-block text-muted"><strong>Instalado:</strong> {{ date('d/m/Y', strtotime($plugin['installed_at'])) }}</small>
                                @if($plugin['updated_at'])
                                <small class="d-block text-muted"><strong>Actualizado:</strong> {{ date('d/m/Y', strtotime($plugin['updated_at'])) }}</small>
                                @endif
                            </div>
                        </div>
                        <div class="card-footer bg-transparent d-flex gap-2">
                            <form action="/admin/plugins/{{ $plugin['slug'] }}/toggle" method="POST" class="flex-fill">
                                @csrf
                                <button type="submit" class="btn btn-sm {{ $plugin['active'] ? 'btn-warning' : 'btn-success' }} w-100">
                                    @if($plugin['active'])
                                        <i class="bi bi-pause-circle"></i> Desactivar
                                    @else
                                        <i class="bi bi-play-circle"></i> Activar
                                    @endif
                                </button>
                            </form>

                            <form action="/admin/plugins/{{ $plugin['slug'] }}/uninstall" method="POST"
                                  onsubmit="return confirm('¿Estás seguro de desinstalar este plugin?\n\nEsta acción eliminará todos los archivos del plugin y no se puede deshacer.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif

        <!-- Información sobre plugins -->
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h4 class="card-title mb-0">ℹ️ Información sobre Plugins</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>¿Qué son los Plugins Privados?</h5>
                        <p>Los plugins privados son extensiones de funcionalidad exclusivas de tu sitio. A diferencia de los módulos globales del sistema, estos plugins:</p>
                        <ul>
                            <li>Solo están disponibles para tu sitio</li>
                            <li>No son visibles ni accesibles por otros sitios</li>
                            <li>Puedes instalarlos, activarlos y desinstalarlos libremente</li>
                            <li>Se almacenan de forma aislada en tu espacio de almacenamiento</li>
                        </ul>
                    </div>

                    <div class="col-md-6">
                        <h5>Diferencia entre Módulos y Plugins</h5>
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Característica</th>
                                    <th>Módulos Globales</th>
                                    <th>Plugins Privados</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Instalación</td>
                                    <td>Por el administrador del sistema</td>
                                    <td>Por ti mismo</td>
                                </tr>
                                <tr>
                                    <td>Disponibilidad</td>
                                    <td>Para todos los sitios (si se activan)</td>
                                    <td>Solo para tu sitio</td>
                                </tr>
                                <tr>
                                    <td>Ubicación</td>
                                    <td>/modules/</td>
                                    <td>/storage/tenants/{id}/plugins/</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <h5>Seguridad</h5>
                        <p>Todos los plugins se validan automáticamente antes de instalarse para detectar:</p>
                        <ul>
                            <li>Funciones peligrosas (eval, exec, system, etc.)</li>
                            <li>Acceso a archivos sensibles del sistema</li>
                            <li>Permisos no autorizados</li>
                            <li>Estructura incorrecta</li>
                        </ul>
                    </div>

                    <div class="col-md-6">
                        <h5>Rendimiento</h5>
                        <p>Para optimizar el rendimiento:</p>
                        <ul>
                            <li>Solo se cargan los plugins activos</li>
                            <li>Los plugins inactivos no afectan el rendimiento</li>
                            <li>Cada plugin se carga solo para tu sitio</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleUploadForm() {
    const form = document.getElementById('upload-form');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}
</script>
@endsection
