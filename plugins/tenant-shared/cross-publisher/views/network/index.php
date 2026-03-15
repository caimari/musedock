<?php
/**
 * Cross-Publisher - Network Configuration View
 */
$pageTitle = $lang['network_title'] ?? 'Configuración de Red';
?>

<div class="cross-publisher-network">
    <div class="page-header mb-4">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
    </div>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['flash_success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['flash_error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if (!$networkKey): ?>
        <!-- Formulario de registro -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?= $lang['network_register'] ?? 'Registrar en una red' ?></h5>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    <?= $lang['network_register_help'] ?? 'Introduce la clave de tu red editorial para conectar con otros tenants.' ?>
                </p>

                <form action="/admin/plugins/cross-publisher/network/register" method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="network_key" class="form-label">
                                    <?= $lang['network_key'] ?? 'Clave de red' ?> *
                                </label>
                                <input type="text"
                                       class="form-control"
                                       id="network_key"
                                       name="network_key"
                                       placeholder="mi-red-editorial"
                                       pattern="[a-z0-9-]+"
                                       required>
                                <div class="form-text">Solo letras minúsculas, números y guiones</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="default_language" class="form-label">
                                    <?= $lang['network_language'] ?? 'Idioma por defecto' ?>
                                </label>
                                <select class="form-select" id="default_language" name="default_language">
                                    <option value="es">Español</option>
                                    <option value="en">English</option>
                                    <option value="ca">Català</option>
                                    <option value="fr">Français</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="can_publish" name="can_publish" checked>
                                <label class="form-check-label" for="can_publish">
                                    <?= $lang['network_can_publish'] ?? 'Puede publicar en otros tenants' ?>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="can_receive" name="can_receive" checked>
                                <label class="form-check-label" for="can_receive">
                                    <?= $lang['network_can_receive'] ?? 'Puede recibir de otros tenants' ?>
                                </label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <?= $lang['network_register_button'] ?? 'Registrar' ?>
                    </button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <!-- Configuración actual -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><?= $lang['network_current'] ?? 'Configuración actual' ?></h5>
            </div>
            <div class="card-body">
                <form action="/admin/plugins/cross-publisher/network/update" method="POST">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><?= $lang['network_key'] ?? 'Clave de red' ?></label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($networkKey) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label for="default_language" class="form-label">
                                <?= $lang['network_language'] ?? 'Idioma por defecto' ?>
                            </label>
                            <select class="form-select" id="default_language" name="default_language">
                                <option value="es" <?= ($config->default_language ?? 'es') === 'es' ? 'selected' : '' ?>>Español</option>
                                <option value="en" <?= ($config->default_language ?? '') === 'en' ? 'selected' : '' ?>>English</option>
                                <option value="ca" <?= ($config->default_language ?? '') === 'ca' ? 'selected' : '' ?>>Català</option>
                                <option value="fr" <?= ($config->default_language ?? '') === 'fr' ? 'selected' : '' ?>>Français</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="can_publish" name="can_publish"
                                       <?= ($config->can_publish ?? true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="can_publish">
                                    <?= $lang['network_can_publish'] ?? 'Puede publicar' ?>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="can_receive" name="can_receive"
                                       <?= ($config->can_receive ?? true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="can_receive">
                                    <?= $lang['network_can_receive'] ?? 'Puede recibir' ?>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active"
                                       <?= ($config->is_active ?? true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">
                                    <?= $lang['network_active'] ?? 'Activo' ?>
                                </label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <?= $lang['network_update'] ?? 'Guardar cambios' ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Miembros de la red -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?= $lang['network_members'] ?? 'Miembros de la red' ?> (<?= count($networkTenants) ?>)</h5>
            </div>
            <?php if (empty($networkTenants)): ?>
                <div class="card-body">
                    <p class="text-muted"><?= $lang['network_no_members'] ?? 'No hay otros miembros en esta red.' ?></p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th><?= $lang['network_tenant'] ?? 'Tenant' ?></th>
                                <th><?= $lang['network_domain'] ?? 'Dominio' ?></th>
                                <th><?= $lang['network_language'] ?? 'Idioma' ?></th>
                                <th><?= $lang['network_permissions'] ?? 'Permisos' ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($networkTenants as $tenant): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($tenant->tenant_name) ?></strong></td>
                                    <td>
                                        <a href="https://<?= htmlspecialchars($tenant->domain) ?>" target="_blank">
                                            <?= htmlspecialchars($tenant->domain) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?= strtoupper($tenant->default_language) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($tenant->can_publish): ?>
                                            <span class="badge bg-success">Publica</span>
                                        <?php endif; ?>
                                        <?php if ($tenant->can_receive): ?>
                                            <span class="badge bg-info">Recibe</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
