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
                <button type="button" class="btn btn-outline-secondary" id="btn-sync-plugins">
                    <i class="bi bi-arrow-repeat"></i> Sincronizar
                </button>
                <button class="btn btn-primary" id="btn-install-plugin">
                    <i class="bi bi-upload"></i> Instalar Plugin
                </button>
            </div>
        </div>

        <!-- Formulario oculto para sincronizar -->
        <form id="form-sync" action="{{ admin_url('/plugins/sync') }}" method="POST" style="display:none;">
            @csrf
        </form>

        <!-- Formulario oculto para subir plugin -->
        <form id="form-upload" action="{{ admin_url('/plugins/upload') }}" method="POST" enctype="multipart/form-data" style="display:none;">
            @csrf
            <input type="file" id="plugin_zip_hidden" name="plugin_zip" accept=".zip">
        </form>

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
                            <form action="{{ admin_url('/plugins/' . $plugin['slug'] . '/toggle') }}" method="POST" class="flex-fill plugin-toggle-form" data-plugin-name="{{ $plugin['name'] }}" data-plugin-active="{{ $plugin['active'] ? '1' : '0' }}">
                                @csrf
                                <button type="button" class="btn btn-sm {{ $plugin['active'] ? 'btn-warning' : 'btn-success' }} w-100 btn-toggle-plugin">
                                    @if($plugin['active'])
                                        <i class="bi bi-pause-circle"></i> Desactivar
                                    @else
                                        <i class="bi bi-play-circle"></i> Activar
                                    @endif
                                </button>
                            </form>

                            <form action="{{ admin_url('/plugins/' . $plugin['slug'] . '/uninstall') }}" method="POST" class="plugin-uninstall-form" data-plugin-name="{{ $plugin['name'] }}">
                                @csrf
                                @method('DELETE')
                                <button type="button" class="btn btn-sm btn-danger btn-uninstall-plugin">
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
            <div class="card-header" style="background-color: #d4edda; color: #155724;">
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
            }
        });
    </script>
@endif

<script>
document.addEventListener('DOMContentLoaded', function() {

    // ========== INSTALAR PLUGIN ==========
    document.getElementById('btn-install-plugin').addEventListener('click', function() {
        Swal.fire({
            title: '📦 Instalar Plugin',
            html: `
                <p class="mb-3">Selecciona un archivo ZIP con el plugin a instalar.</p>
                <input type="file" id="swal-plugin-file" accept=".zip" class="form-control">
                <small class="text-muted d-block mt-2">Máximo 10MB. Solo archivos ZIP.</small>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-upload"></i> Instalar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#198754',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                const fileInput = document.getElementById('swal-plugin-file');
                if (!fileInput.files.length) {
                    Swal.showValidationMessage('Debes seleccionar un archivo ZIP');
                    return false;
                }
                const file = fileInput.files[0];
                if (!file.name.toLowerCase().endsWith('.zip')) {
                    Swal.showValidationMessage('Solo se permiten archivos ZIP');
                    return false;
                }
                if (file.size > 10 * 1024 * 1024) {
                    Swal.showValidationMessage('El archivo no puede superar los 10MB');
                    return false;
                }
                return file;
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                // Copiar archivo al formulario oculto y enviar
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(result.value);
                document.getElementById('plugin_zip_hidden').files = dataTransfer.files;

                Swal.fire({
                    title: 'Instalando...',
                    text: 'Por favor espera mientras se instala el plugin.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                        document.getElementById('form-upload').submit();
                    }
                });
            }
        });
    });

    // ========== SINCRONIZAR PLUGINS ==========
    document.getElementById('btn-sync-plugins').addEventListener('click', function() {
        Swal.fire({
            title: '🔄 Sincronizar Plugins',
            text: '¿Deseas sincronizar los plugins desde el disco? Esto registrará nuevos plugins y actualizará los existentes.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sincronizar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#6c757d'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Sincronizando...',
                    text: 'Por favor espera.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                        document.getElementById('form-sync').submit();
                    }
                });
            }
        });
    });

    // ========== TOGGLE PLUGIN (Activar/Desactivar) ==========
    document.querySelectorAll('.btn-toggle-plugin').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const form = this.closest('.plugin-toggle-form');
            const pluginName = form.dataset.pluginName;
            const isActive = form.dataset.pluginActive === '1';
            const action = isActive ? 'desactivar' : 'activar';
            const actionVerb = isActive ? 'Desactivar' : 'Activar';

            Swal.fire({
                title: `${isActive ? '⏸️' : '▶️'} ${actionVerb} Plugin`,
                html: `<p>¿Estás seguro de ${action} el plugin <strong>${pluginName}</strong>?</p>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: actionVerb,
                cancelButtonText: 'Cancelar',
                confirmButtonColor: isActive ? '#ffc107' : '#198754'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });

    // ========== DESINSTALAR PLUGIN ==========
    document.querySelectorAll('.btn-uninstall-plugin').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const form = this.closest('.plugin-uninstall-form');
            const pluginName = form.dataset.pluginName;

            Swal.fire({
                title: '🗑️ Desinstalar Plugin',
                html: `
                    <p class="text-danger"><strong>¡Atención!</strong></p>
                    <p>Estás a punto de desinstalar el plugin <strong>${pluginName}</strong>.</p>
                    <p class="text-muted small">Esta acción eliminará todos los archivos del plugin y no se puede deshacer.</p>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, desinstalar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
                focusCancel: true
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Desinstalando...',
                        text: 'Por favor espera.',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                            form.submit();
                        }
                    });
                }
            });
        });
    });

});
</script>
@endsection
