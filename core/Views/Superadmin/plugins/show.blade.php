@extends('layouts.app')

@section('title', 'Detalle del Plugin - ' . $plugin->name)

@section('content')
<div class="container-fluid p-0">
    <!-- Breadcrumb estilo sliders -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="breadcrumb mb-0">
            <a href="/musedock/plugins">Plugins</a>
            <span class="mx-2">/</span>
            <span><?= htmlspecialchars($plugin->name) ?></span>
        </div>
        <a href="/musedock/plugins" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Volver al Listado
        </a>
    </div>

    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-1">
                <i class="bi bi-plugin me-2"></i>
                <?= htmlspecialchars($plugin->name) ?>
            </h1>
            <p class="text-muted"><?= htmlspecialchars($plugin->description ?? 'Sin descripción') ?></p>
        </div>
        <div class="col-md-4 text-end">
            <?php if ($plugin->is_active): ?>
                <span class="badge bg-success fs-6">
                    <i class="bi bi-check-circle me-1"></i>Activo
                </span>
            <?php else: ?>
                <span class="badge bg-secondary fs-6">
                    <i class="bi bi-pause-circle me-1"></i>Inactivo
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <!-- Información Principal -->
        <div class="col-lg-8">
            <!-- Detalles del Plugin -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Información del Plugin</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Versión:</strong>
                        </div>
                        <div class="col-md-8">
                            <span class="badge bg-secondary"><?= htmlspecialchars($plugin->version) ?></span>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Autor:</strong>
                        </div>
                        <div class="col-md-8">
                            <?php if ($plugin->author_url): ?>
                                <a href="<?= htmlspecialchars($plugin->author_url) ?>" target="_blank" rel="noopener">
                                    <?= htmlspecialchars($plugin->author ?? 'Desconocido') ?>
                                    <i class="bi bi-box-arrow-up-right ms-1 small"></i>
                                </a>
                            <?php else: ?>
                                <?= htmlspecialchars($plugin->author ?? 'Desconocido') ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($plugin->plugin_url): ?>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <strong>Sitio web:</strong>
                            </div>
                            <div class="col-md-8">
                                <a href="<?= htmlspecialchars($plugin->plugin_url) ?>" target="_blank" rel="noopener">
                                    <?= htmlspecialchars($plugin->plugin_url) ?>
                                    <i class="bi bi-box-arrow-up-right ms-1 small"></i>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Slug:</strong>
                        </div>
                        <div class="col-md-8">
                            <code><?= htmlspecialchars($plugin->slug) ?></code>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Namespace:</strong>
                        </div>
                        <div class="col-md-8">
                            <code><?= htmlspecialchars($plugin->namespace ?? 'N/A') ?></code>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Ruta:</strong>
                        </div>
                        <div class="col-md-8">
                            <code><?= htmlspecialchars($plugin->path) ?></code>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Archivo principal:</strong>
                        </div>
                        <div class="col-md-8">
                            <code><?= htmlspecialchars($plugin->main_file) ?></code>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Instalado:</strong>
                        </div>
                        <div class="col-md-8">
                            <?= $plugin->installed_at ? date('d/m/Y H:i', strtotime($plugin->installed_at)) : 'N/A' ?>
                        </div>
                    </div>

                    <?php if ($plugin->activated_at): ?>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <strong>Última activación:</strong>
                            </div>
                            <div class="col-md-8">
                                <?= date('d/m/Y H:i', strtotime($plugin->activated_at)) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Requisitos -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Requisitos</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($requirements)): ?>
                        <div class="alert alert-success mb-0">
                            <i class="bi bi-check-circle me-2"></i>
                            El plugin cumple con todos los requisitos del sistema.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Advertencias:</strong>
                            <ul class="mb-0 mt-2">
                                <?php foreach ($requirements as $req): ?>
                                    <li><?= htmlspecialchars($req) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <strong>PHP requerido:</strong>
                            <?php if ($plugin->requires_php): ?>
                                <code><?= htmlspecialchars($plugin->requires_php) ?>+</code>
                                <small class="text-muted">(Actual: <?= PHP_VERSION ?>)</small>
                            <?php else: ?>
                                <span class="text-muted">No especificado</span>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <strong>MuseDock requerido:</strong>
                            <?php if ($plugin->requires_musedock): ?>
                                <code><?= htmlspecialchars($plugin->requires_musedock) ?>+</code>
                            <?php else: ?>
                                <span class="text-muted">No especificado</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($plugin->dependencies): ?>
                        <div class="mt-3">
                            <strong>Dependencias:</strong>
                            <ul class="mb-0">
                                <?php foreach ($plugin->dependencies as $dep => $version): ?>
                                    <li>
                                        <code><?= htmlspecialchars($dep) ?></code>
                                        <?php if ($version): ?>
                                            (Versión <?= htmlspecialchars($version) ?>+)
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar de Acciones -->
        <div class="col-lg-4">
            <!-- Acciones Principales -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Acciones</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <?php if ($plugin->is_active): ?>
                            <button type="button" class="btn btn-warning w-100" id="btnDeactivatePlugin"
                                    data-plugin-id="<?= $plugin->id ?>"
                                    data-plugin-name="<?= htmlspecialchars($plugin->name) ?>">
                                <i class="bi bi-pause-circle me-2"></i>Desactivar Plugin
                            </button>
                        <?php else: ?>
                            <form method="POST" action="/musedock/plugins/<?= $plugin->id ?>/activate">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-play-circle me-2"></i>Activar Plugin
                                </button>
                            </form>
                        <?php endif; ?>

                        <a href="/musedock/plugins" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Volver a Plugins
                        </a>
                    </div>
                </div>
            </div>

            <!-- Zona de Peligro -->
            <?php if (!$plugin->is_active): ?>
                <div class="card border-danger">
                    <div class="card-body">
                        <h6 class="text-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Zona de Peligro
                        </h6>
                        <p class="small text-muted mb-3">
                            Desinstalar este plugin eliminará todos sus datos y configuraciones.
                        </p>
                        <button type="button" class="btn btn-outline-danger btn-sm w-100" id="btnUninstallPlugin"
                                data-plugin-id="<?= $plugin->id ?>"
                                data-plugin-name="<?= htmlspecialchars($plugin->name) ?>">
                            <i class="bi bi-trash me-2"></i>Desinstalar Plugin
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = '<?= csrf_token() ?>';

    // ========== DESACTIVAR PLUGIN con SweetAlert2 ==========
    const btnDeactivate = document.getElementById('btnDeactivatePlugin');
    if (btnDeactivate) {
        btnDeactivate.addEventListener('click', function() {
            const pluginId = this.dataset.pluginId;
            const pluginName = this.dataset.pluginName;

            Swal.fire({
                title: '<i class="bi bi-shield-lock text-warning"></i> Confirmar Desactivación',
                html: `
                    <div class="text-start">
                        <p class="mb-3">Estás a punto de desactivar el plugin <strong>${pluginName}</strong>.</p>
                        <div class="alert alert-warning py-2 mb-3">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <small>El plugin dejará de funcionar pero permanecerá instalado.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Introduce tu contraseña para confirmar:</label>
                            <input type="password" id="deactivatePassword" class="form-control" placeholder="Contraseña del superadmin" autocomplete="current-password">
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-pause-circle me-1"></i> Desactivar Plugin',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                width: '450px',
                focusConfirm: false,
                didOpen: () => {
                    document.getElementById('deactivatePassword').focus();
                },
                preConfirm: () => {
                    const password = document.getElementById('deactivatePassword').value;
                    if (!password) {
                        Swal.showValidationMessage('La contraseña es requerida');
                        return false;
                    }
                    return password;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Desactivando plugin...',
                        html: '<p class="mb-0">Por favor espera...</p>',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => Swal.showLoading()
                    });

                    fetch(`/musedock/plugins/${pluginId}/deactivate-secure`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            _csrf: csrfToken,
                            password: result.value
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Plugin Desactivado',
                                text: data.message,
                                confirmButtonColor: '#0d6efd'
                            }).then(() => location.reload());
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message,
                                confirmButtonColor: '#0d6efd'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Error de conexión. Intenta de nuevo.',
                            confirmButtonColor: '#0d6efd'
                        });
                    });
                }
            });
        });
    }

    // ========== DESINSTALAR PLUGIN con SweetAlert2 ==========
    const btnUninstall = document.getElementById('btnUninstallPlugin');
    if (btnUninstall) {
        btnUninstall.addEventListener('click', function() {
            const pluginId = this.dataset.pluginId;
            const pluginName = this.dataset.pluginName;

            Swal.fire({
                title: '<i class="bi bi-exclamation-triangle text-danger"></i> Confirmar Desinstalación',
                html: `
                    <div class="text-start">
                        <p class="mb-3">Estás a punto de desinstalar el plugin <strong>${pluginName}</strong>.</p>
                        <div class="alert alert-danger py-2 mb-3">
                            <i class="bi bi-trash me-2"></i>
                            <small><strong>Esta acción no se puede deshacer.</strong> Se eliminarán todos los datos y configuraciones del plugin.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Introduce tu contraseña para confirmar:</label>
                            <input type="password" id="uninstallPassword" class="form-control" placeholder="Contraseña del superadmin" autocomplete="current-password">
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-trash me-1"></i> Desinstalar Plugin',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                width: '450px',
                focusConfirm: false,
                didOpen: () => {
                    document.getElementById('uninstallPassword').focus();
                },
                preConfirm: () => {
                    const password = document.getElementById('uninstallPassword').value;
                    if (!password) {
                        Swal.showValidationMessage('La contraseña es requerida');
                        return false;
                    }
                    return password;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Desinstalando plugin...',
                        html: '<p class="mb-0">Por favor espera...</p>',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => Swal.showLoading()
                    });

                    fetch(`/musedock/plugins/${pluginId}/uninstall-secure`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            _csrf: csrfToken,
                            password: result.value
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Plugin Desinstalado',
                                text: data.message,
                                confirmButtonColor: '#0d6efd'
                            }).then(() => window.location.href = '/musedock/plugins');
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message,
                                confirmButtonColor: '#0d6efd'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Error de conexión. Intenta de nuevo.',
                            confirmButtonColor: '#0d6efd'
                        });
                    });
                }
            });
        });
    }
});
</script>
@endpush
