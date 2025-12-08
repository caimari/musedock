@extends('layouts.app')

@section('title', $title ?? 'Gestión de Módulos')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="bi bi-puzzle me-2"></i>
                        Gestión de Módulos
                    </h1>
                    <p class="text-muted mb-0">Instala, activa y gestiona módulos del CMS</p>
                </div>
                <div>
                    <button type="button" class="btn btn-primary" id="btnUploadModule">
                        <i class="bi bi-upload me-2"></i>
                        Subir Módulo ZIP
                    </button>
                </div>
            </div>
        </div>
    </div>

    @if(!empty($autoRegistered))
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Módulos detectados automáticamente:</strong>
        {{ implode(', ', $autoRegistered) }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <!-- Listado de Módulos -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 py-3">
            <h5 class="mb-0">Módulos Disponibles</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Módulo</th>
                            <th>Versión</th>
                            <th>Estado</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($modules as $module)
                        <tr>
                            <td class="ps-4">
                                <div>
                                    <h6 class="mb-0">{{ $module['name'] ?? $module['slug'] }}</h6>
                                    <small class="text-muted">{{ $module['description'] ?? 'Sin descripción' }}</small>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-secondary">v{{ $module['version'] ?? '1.0.0' }}</span>
                            </td>
                            <td>
                                @if($module['installed'])
                                    @if($module['active'] ?? false)
                                    <span class="badge bg-success">Activo</span>
                                    @else
                                    <span class="badge bg-warning">Inactivo</span>
                                    @endif
                                @else
                                <span class="badge bg-secondary">No instalado</span>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group btn-group-sm">
                                    @if(!$module['installed'])
                                    <form method="POST" action="/musedock/modules/activate" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="slug" value="{{ $module['slug'] }}">
                                        <button type="submit" class="btn btn-success" title="Instalar y Activar">
                                            <i class="bi bi-download"></i> Instalar
                                        </button>
                                    </form>
                                    @else
                                        @if($module['active'] ?? false)
                                        <form method="POST" action="/musedock/modules/deactivate" class="d-inline module-action-form">
                                            @csrf
                                            <input type="hidden" name="slug" value="{{ $module['slug'] }}">
                                            <button type="button" class="btn btn-warning confirm-module-action"
                                                    data-action="deactivate"
                                                    data-module="{{ $module['name'] ?? $module['slug'] }}"
                                                    title="Desactivar">
                                                <i class="bi bi-pause"></i>
                                            </button>
                                        </form>
                                        @else
                                        <form method="POST" action="/musedock/modules/activate" class="d-inline module-action-form">
                                            @csrf
                                            <input type="hidden" name="slug" value="{{ $module['slug'] }}">
                                            <button type="button" class="btn btn-success confirm-module-action"
                                                    data-action="activate"
                                                    data-module="{{ $module['name'] ?? $module['slug'] }}"
                                                    title="Activar">
                                                <i class="bi bi-play"></i>
                                            </button>
                                        </form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center py-4">
                                <p class="text-muted mt-2">No hay módulos disponibles</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Formulario oculto para subir módulo -->
<form id="uploadModuleForm" method="POST" action="/musedock/modules/upload" enctype="multipart/form-data" style="display: none;">
    @csrf
    <input type="file" id="moduleZipInput" name="module_zip" accept=".zip">
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ========== SUBIR MÓDULO ZIP con SweetAlert2 ==========
    const btnUpload = document.getElementById('btnUploadModule');
    const uploadForm = document.getElementById('uploadModuleForm');
    const fileInput = document.getElementById('moduleZipInput');

    if (btnUpload) {
        btnUpload.addEventListener('click', function() {
            Swal.fire({
                title: '<i class="bi bi-cloud-upload text-primary"></i> Subir Módulo',
                html: `
                    <div class="text-start">
                        <p class="text-muted mb-3">Selecciona un archivo ZIP con el módulo a instalar.</p>
                        <div class="upload-zone border border-2 border-dashed rounded-3 p-4 text-center" id="dropZone" style="cursor: pointer; transition: all 0.3s;">
                            <i class="bi bi-file-earmark-zip display-4 text-muted"></i>
                            <p class="mb-1 mt-2"><strong>Arrastra el archivo aquí</strong></p>
                            <p class="text-muted small mb-2">o haz clic para seleccionar</p>
                            <span class="badge bg-secondary">Máximo 50MB</span>
                        </div>
                        <div id="selectedFile" class="mt-3 d-none">
                            <div class="alert alert-success py-2 mb-0">
                                <i class="bi bi-file-earmark-check me-2"></i>
                                <span id="fileName"></span>
                                <button type="button" class="btn-close btn-sm float-end" id="clearFile"></button>
                            </div>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-upload me-1"></i> Subir e Instalar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#6c757d',
                width: '500px',
                didOpen: () => {
                    const dropZone = document.getElementById('dropZone');
                    const selectedFileDiv = document.getElementById('selectedFile');
                    const fileNameSpan = document.getElementById('fileName');
                    const clearFileBtn = document.getElementById('clearFile');
                    let selectedFile = null;

                    // Click para seleccionar archivo
                    dropZone.addEventListener('click', () => fileInput.click());

                    // Drag & Drop
                    dropZone.addEventListener('dragover', (e) => {
                        e.preventDefault();
                        dropZone.classList.add('border-primary', 'bg-light');
                    });
                    dropZone.addEventListener('dragleave', () => {
                        dropZone.classList.remove('border-primary', 'bg-light');
                    });
                    dropZone.addEventListener('drop', (e) => {
                        e.preventDefault();
                        dropZone.classList.remove('border-primary', 'bg-light');
                        const file = e.dataTransfer.files[0];
                        if (file && file.name.endsWith('.zip')) {
                            handleFileSelect(file);
                        } else {
                            Swal.showValidationMessage('Por favor selecciona un archivo .zip');
                        }
                    });

                    // Cuando se selecciona archivo
                    fileInput.addEventListener('change', function() {
                        if (this.files[0]) {
                            handleFileSelect(this.files[0]);
                        }
                    });

                    // Limpiar archivo
                    clearFileBtn.addEventListener('click', () => {
                        fileInput.value = '';
                        selectedFileDiv.classList.add('d-none');
                        dropZone.classList.remove('d-none');
                        selectedFile = null;
                    });

                    function handleFileSelect(file) {
                        if (file.size > 50 * 1024 * 1024) {
                            Swal.showValidationMessage('El archivo excede el límite de 50MB');
                            return;
                        }
                        selectedFile = file;
                        fileNameSpan.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
                        selectedFileDiv.classList.remove('d-none');
                        dropZone.classList.add('d-none');

                        // Crear nuevo FileList para el input
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        fileInput.files = dt.files;
                    }
                },
                preConfirm: () => {
                    if (!fileInput.files[0]) {
                        Swal.showValidationMessage('Por favor selecciona un archivo ZIP');
                        return false;
                    }
                    return true;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostrar loading
                    Swal.fire({
                        title: 'Subiendo módulo...',
                        html: '<p class="mb-2">Por favor espera mientras se sube e instala el módulo.</p><div class="progress"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div></div>',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    uploadForm.submit();
                }
            });
        });
    }

    // ========== ACTIVAR/DESACTIVAR MÓDULOS ==========
    document.querySelectorAll('.confirm-module-action').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();

            const action = this.dataset.action;
            const moduleName = this.dataset.module;
            const form = this.closest('form');

            const isActivating = action === 'activate';
            const actionText = isActivating ? 'activar' : 'desactivar';
            const actionTextCap = isActivating ? 'Activar' : 'Desactivar';

            Swal.fire({
                title: `¿${actionTextCap} módulo "${moduleName}"?`,
                html: `
                    <div class="text-start">
                        <p class="mb-3">
                            <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
                            <strong>¡Atención! Esta acción afectará a TODOS los tenants/dominios del sistema.</strong>
                        </p>
                        <div class="alert alert-warning mb-0">
                            <h6 class="alert-heading mb-2">
                                <i class="bi bi-info-circle me-2"></i>
                                Impacto de esta acción:
                            </h6>
                            <ul class="mb-0 ps-3">
                                ${isActivating ? `
                                    <li>El módulo estará <strong>disponible</strong> para todos los tenants</li>
                                    <li>Los tenants podrán activarlo en su panel individual</li>
                                    <li>El menú del módulo aparecerá en los sidebars</li>
                                ` : `
                                    <li>El módulo se <strong>ocultará</strong> de todos los tenants</li>
                                    <li>Los menús del módulo desaparecerán de los sidebars</li>
                                    <li>Las funcionalidades del módulo quedarán inaccesibles</li>
                                    <li>Esto ocurrirá incluso si los tenants lo tenían activado</li>
                                `}
                            </ul>
                        </div>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: isActivating ? '#28a745' : '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: `Sí, ${actionText}`,
                cancelButtonText: 'Cancelar',
                width: '600px',
                customClass: {
                    htmlContainer: 'text-start'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostrar loading
                    Swal.fire({
                        title: `${isActivating ? 'Activando' : 'Desactivando'} módulo...`,
                        html: 'Por favor espera mientras se procesa la acción.',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Enviar formulario
                    form.submit();
                }
            });
        });
    });
});
</script>
@endpush
