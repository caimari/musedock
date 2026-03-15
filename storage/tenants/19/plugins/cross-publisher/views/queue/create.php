<?php
/**
 * Cross-Publisher - Create Queue Item View
 */
$pageTitle = $lang['queue_create_title'] ?? 'Publicar en la red';
?>

<div class="cross-publisher-queue-create">
    <div class="page-header mb-4">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
    </div>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['flash_error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if (empty($targetTenants)): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            <?= $lang['no_target_tenants'] ?? 'No hay tenants disponibles en tu red editorial.' ?>
            <a href="/admin/plugins/cross-publisher/network" class="alert-link">
                <?= $lang['network_configure'] ?? 'Configurar red' ?>
            </a>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <form action="/admin/plugins/cross-publisher/queue" method="POST">
                    <!-- Seleccionar Post -->
                    <div class="mb-4">
                        <label for="post_id" class="form-label">
                            <?= $lang['queue_select_post'] ?? 'Seleccionar post' ?> *
                        </label>
                        <select class="form-select" id="post_id" name="post_id" required>
                            <option value="">-- Seleccionar --</option>
                            <?php foreach ($posts as $post): ?>
                                <option value="<?= $post->id ?>">
                                    <?= htmlspecialchars($post->title) ?>
                                    (<?= date('d/m/Y', strtotime($post->published_at)) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Seleccionar Destinos -->
                    <div class="mb-4">
                        <label class="form-label">
                            <?= $lang['queue_select_targets'] ?? 'Seleccionar destinos' ?> *
                        </label>
                        <div class="row">
                            <?php foreach ($targetTenants as $tenant): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input type="checkbox"
                                               class="form-check-input"
                                               id="target_<?= $tenant->tenant_id ?>"
                                               name="target_tenant_ids[]"
                                               value="<?= $tenant->tenant_id ?>">
                                        <label class="form-check-label" for="target_<?= $tenant->tenant_id ?>">
                                            <strong><?= htmlspecialchars($tenant->tenant_name) ?></strong>
                                            <br><small class="text-muted">
                                                <?= htmlspecialchars($tenant->domain) ?>
                                                <span class="badge bg-secondary"><?= strtoupper($tenant->default_language) ?></span>
                                            </small>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Opciones de traducción -->
                    <div class="mb-4">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title"><?= $lang['queue_translation_options'] ?? 'Opciones de traducción' ?></h6>

                                <div class="form-check mb-2">
                                    <input type="checkbox" class="form-check-input" id="translate" name="translate">
                                    <label class="form-check-label" for="translate">
                                        <?= $lang['queue_translate'] ?? 'Traducir contenido' ?>
                                    </label>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="target_language" class="form-label">
                                            <?= $lang['queue_target_language'] ?? 'Idioma destino' ?>
                                        </label>
                                        <select class="form-select" id="target_language" name="target_language">
                                            <option value="es">Español</option>
                                            <option value="en">English</option>
                                            <option value="ca">Català</option>
                                            <option value="fr">Français</option>
                                            <option value="de">Deutsch</option>
                                            <option value="it">Italiano</option>
                                            <option value="pt">Português</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i> <?= $lang['queue_submit'] ?? 'Añadir a la cola' ?>
                        </button>
                        <a href="/admin/plugins/cross-publisher/queue" class="btn btn-outline-secondary">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
