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
                        <label for="post_id" class="form-label fw-semibold">
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

                    <!-- Seleccionar Destinos con modo por tenant -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">
                            <?= $lang['queue_select_targets'] ?? 'Seleccionar destinos y modo de publicacion' ?> *
                        </label>
                        <small class="text-muted d-block mb-3">Para cada destino, elige como publicar el contenido.</small>

                        <?php foreach ($targetTenants as $tenant): ?>
                            <div class="card mb-3 border">
                                <div class="card-body py-3">
                                    <div class="row align-items-center">
                                        <!-- Checkbox + Tenant info -->
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input type="checkbox"
                                                       class="form-check-input target-check"
                                                       id="target_<?= $tenant->tenant_id ?>"
                                                       name="target_tenant_ids[]"
                                                       value="<?= $tenant->tenant_id ?>"
                                                       data-tenant-id="<?= $tenant->tenant_id ?>">
                                                <label class="form-check-label" for="target_<?= $tenant->tenant_id ?>">
                                                    <strong><?= htmlspecialchars($tenant->tenant_name) ?></strong>
                                                    <br><small class="text-muted">
                                                        <?= htmlspecialchars($tenant->domain) ?>
                                                        <span class="badge bg-secondary"><?= strtoupper($tenant->default_language) ?></span>
                                                    </small>
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Publish Mode -->
                                        <div class="col-md-4 target-options" id="options_<?= $tenant->tenant_id ?>" style="opacity: 0.4; pointer-events: none;">
                                            <label class="form-label small mb-1">Modo de publicacion</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio"
                                                       name="publish_mode[<?= $tenant->tenant_id ?>]"
                                                       id="mode_clone_<?= $tenant->tenant_id ?>"
                                                       value="clone" checked>
                                                <label class="form-check-label" for="mode_clone_<?= $tenant->tenant_id ?>">
                                                    <strong>A) Clonar</strong>
                                                    <small class="text-muted d-block">Copia identica + canonical. Rapido, sin IA.</small>
                                                </label>
                                            </div>
                                            <div class="form-check mt-1">
                                                <input class="form-check-input" type="radio"
                                                       name="publish_mode[<?= $tenant->tenant_id ?>]"
                                                       id="mode_adapt_<?= $tenant->tenant_id ?>"
                                                       value="adapt">
                                                <label class="form-check-label" for="mode_adapt_<?= $tenant->tenant_id ?>">
                                                    <strong>B) Adaptar con IA</strong>
                                                    <small class="text-muted d-block">Reescribe para SEO. Sin canonical, doble indexacion.</small>
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Language -->
                                        <div class="col-md-4 target-options" id="lang_<?= $tenant->tenant_id ?>" style="opacity: 0.4; pointer-events: none;">
                                            <label class="form-label small mb-1">Idioma destino</label>
                                            <select class="form-select form-select-sm"
                                                    name="target_language[<?= $tenant->tenant_id ?>]">
                                                <option value="es" <?= ($tenant->default_language ?? 'es') === 'es' ? 'selected' : '' ?>>Espanol</option>
                                                <option value="en" <?= ($tenant->default_language ?? '') === 'en' ? 'selected' : '' ?>>English</option>
                                                <option value="ca" <?= ($tenant->default_language ?? '') === 'ca' ? 'selected' : '' ?>>Catala</option>
                                                <option value="fr" <?= ($tenant->default_language ?? '') === 'fr' ? 'selected' : '' ?>>Francais</option>
                                                <option value="de" <?= ($tenant->default_language ?? '') === 'de' ? 'selected' : '' ?>>Deutsch</option>
                                                <option value="it" <?= ($tenant->default_language ?? '') === 'it' ? 'selected' : '' ?>>Italiano</option>
                                                <option value="pt" <?= ($tenant->default_language ?? '') === 'pt' ? 'selected' : '' ?>>Portugues</option>
                                            </select>
                                            <small class="text-muted">Si difiere del original, se traduce automaticamente</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Info cards -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="alert alert-info mb-0 small">
                                <i class="bi bi-files"></i> <strong>Opcion A - Clonar:</strong>
                                Copia identica con <code>canonical</code> al original. Google solo indexa el original.
                                Util para llenar medios rapidamente.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-success mb-0 small">
                                <i class="bi bi-stars"></i> <strong>Opcion B - Adaptar:</strong>
                                La IA reescribe titulo y parrafos clave. Ambos medios indexan por separado.
                                Mejor para SEO a largo plazo.
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i> <?= $lang['queue_submit'] ?? 'Anadir a la cola' ?>
                        </button>
                        <a href="/admin/plugins/cross-publisher/queue" class="btn btn-outline-secondary">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <script>
        document.querySelectorAll('.target-check').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                var tenantId = this.dataset.tenantId;
                var options = document.getElementById('options_' + tenantId);
                var lang = document.getElementById('lang_' + tenantId);

                if (this.checked) {
                    if (options) { options.style.opacity = '1'; options.style.pointerEvents = 'auto'; }
                    if (lang) { lang.style.opacity = '1'; lang.style.pointerEvents = 'auto'; }
                } else {
                    if (options) { options.style.opacity = '0.4'; options.style.pointerEvents = 'none'; }
                    if (lang) { lang.style.opacity = '0.4'; lang.style.pointerEvents = 'none'; }
                }
            });
        });
        </script>
    <?php endif; ?>
</div>
