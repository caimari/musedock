@extends('layouts.app')

@section('title', 'Detalle del Plugin - ' . $plugin->name)

@section('content')
<div class="container-fluid p-0">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/musedock/dashboard">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="/musedock/plugins">Plugins</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($plugin->name) ?></li>
        </ol>
    </nav>

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
                            <form method="POST" action="/musedock/plugins/<?= $plugin->id ?>/deactivate">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-warning w-100" onclick="return confirm('¿Desactivar este plugin?')">
                                    <i class="bi bi-pause-circle me-2"></i>Desactivar Plugin
                                </button>
                            </form>
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
                        <form method="POST" action="/musedock/plugins/<?= $plugin->id ?>/uninstall">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline-danger btn-sm w-100" onclick="return confirm('¿Estás seguro de desinstalar este plugin? Esta acción no se puede deshacer.')">
                                <i class="bi bi-trash me-2"></i>Desinstalar Plugin
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
@endsection
