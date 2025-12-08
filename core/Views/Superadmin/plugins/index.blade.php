@extends('layouts.app')

@section('title', 'Plugins del Sistema')

@section('content')
<div class="container-fluid p-0">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-3">
                <i class="bi bi-plugin me-2"></i>
                Plugins del Sistema
            </h1>
            <p class="text-muted">Gestiona plugins exclusivos para el dominio base (superadmin)</p>
        </div>
        <div class="col-md-4 text-end">
            <button type="button" class="btn btn-primary" id="btnUploadPlugin">
                <i class="bi bi-upload me-2"></i>Subir Plugin
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Total</h6>
                            <h3 class="mb-0"><?= $stats['total'] ?></h3>
                        </div>
                        <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                            <i class="bi bi-plugin text-primary fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Activos</h6>
                            <h3 class="mb-0 text-success"><?= $stats['active'] ?></h3>
                        </div>
                        <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                            <i class="bi bi-check-circle text-success fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Inactivos</h6>
                            <h3 class="mb-0 text-warning"><?= $stats['inactive'] ?></h3>
                        </div>
                        <div class="rounded-circle bg-warning bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                            <i class="bi bi-pause-circle text-warning fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Disponibles</h6>
                            <h3 class="mb-0 text-info"><?= $stats['available'] ?></h3>
                        </div>
                        <div class="rounded-circle bg-info bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                            <i class="bi bi-box-seam text-info fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Plugins Instalados -->
    <?php if (!empty($installedPlugins)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Plugins Instalados</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Plugin</th>
                                <th>Versión</th>
                                <th>Autor</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($installedPlugins as $plugin): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                <i class="bi bi-plugin text-primary"></i>
                                            </div>
                                            <div>
                                                <strong><?= htmlspecialchars($plugin->name) ?></strong>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($plugin->description ?? 'Sin descripción') ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($plugin->version) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($plugin->author_url): ?>
                                            <a href="<?= htmlspecialchars($plugin->author_url) ?>" target="_blank" rel="noopener">
                                                <?= htmlspecialchars($plugin->author ?? 'Desconocido') ?>
                                            </a>
                                        <?php else: ?>
                                            <?= htmlspecialchars($plugin->author ?? 'Desconocido') ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($plugin->is_active): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle me-1"></i>Activo
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="bi bi-pause-circle me-1"></i>Inactivo
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($plugin->is_active): ?>
                                                <form method="POST" action="/musedock/plugins/<?= $plugin->id ?>/deactivate" style="display:inline;">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-outline-warning btn-sm" onclick="return confirm('¿Desactivar este plugin?')">
                                                        <i class="bi bi-pause-circle"></i> Desactivar
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" action="/musedock/plugins/<?= $plugin->id ?>/activate" style="display:inline;">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-outline-success btn-sm">
                                                        <i class="bi bi-play-circle"></i> Activar
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <a href="/musedock/plugins/<?= $plugin->id ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-eye"></i> Ver
                                            </a>

                                            <?php if (!$plugin->is_active): ?>
                                                <form method="POST" action="/musedock/plugins/<?= $plugin->id ?>/uninstall" style="display:inline;">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Desinstalar este plugin? Esta acción no se puede deshacer.')">
                                                        <i class="bi bi-trash"></i> Desinstalar
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            No hay plugins instalados. Puedes subir un plugin o explorar el directorio.
        </div>
    <?php endif; ?>

    <!-- Plugins Disponibles (No Instalados) -->
    <?php if (!empty($newPlugins)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Plugins Disponibles para Instalar</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Plugin</th>
                                <th>Versión</th>
                                <th>Autor</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($newPlugins as $plugin): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-info bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                <i class="bi bi-box-seam text-info"></i>
                                            </div>
                                            <div>
                                                <strong><?= htmlspecialchars($plugin['name']) ?></strong>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($plugin['description'] ?? 'Sin descripción') ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($plugin['version'] ?? '1.0.0') ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($plugin['author'] ?? 'Desconocido') ?></td>
                                    <td class="text-end">
                                        <form method="POST" action="/musedock/plugins/install" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="slug" value="<?= htmlspecialchars($plugin['slug']) ?>">
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                <i class="bi bi-download"></i> Instalar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Formulario oculto para subir plugin -->
<form id="uploadPluginForm" method="POST" action="/musedock/plugins/upload" enctype="multipart/form-data" style="display: none;">
    <?= csrf_field() ?>
    <input type="file" id="pluginZipInput" name="plugin_file" accept=".zip">
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ========== SUBIR PLUGIN ZIP con SweetAlert2 ==========
    const btnUpload = document.getElementById('btnUploadPlugin');
    const uploadForm = document.getElementById('uploadPluginForm');
    const fileInput = document.getElementById('pluginZipInput');

    if (btnUpload) {
        btnUpload.addEventListener('click', function() {
            Swal.fire({
                title: '<i class="bi bi-cloud-upload text-primary"></i> Subir Plugin',
                html: `
                    <div class="text-start">
                        <p class="text-muted mb-3">Selecciona un archivo ZIP con el plugin a instalar.</p>
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
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Importante:</strong> Asegúrate de que el plugin sea compatible con MuseDock y provenga de una fuente confiable.
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
                        title: 'Subiendo plugin...',
                        html: '<p class="mb-2">Por favor espera mientras se sube e instala el plugin.</p><div class="progress"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div></div>',
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
});
</script>
@endpush
