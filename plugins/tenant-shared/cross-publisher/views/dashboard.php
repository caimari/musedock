<?php
/**
 * Cross-Publisher - Dashboard View
 */
$pageTitle = $lang['dashboard_title'] ?? 'Cross-Publisher';
?>

<div class="cross-publisher-dashboard">
    <div class="page-header mb-4">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
    </div>

    <!-- Network Status -->
    <?php if (!$networkKey): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            <?= $lang['network_not_configured'] ?? 'Este tenant no está registrado en ninguna red editorial.' ?>
            <a href="/admin/plugins/cross-publisher/network" class="alert-link">
                <?= $lang['network_configure'] ?? 'Configurar ahora' ?>
            </a>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-diagram-3"></i>
            <?= $lang['network_active'] ?? 'Red activa' ?>: <strong><?= htmlspecialchars($networkKey) ?></strong>
            (<?= count($networkTenants) ?> <?= $lang['network_members'] ?? 'miembros' ?>)
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h5 class="card-title"><?= $lang['stats_pending'] ?? 'Pendientes' ?></h5>
                    <h2 class="mb-0"><?= $stats['pending_count'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title"><?= $lang['stats_processing'] ?? 'Procesando' ?></h5>
                    <h2 class="mb-0"><?= $stats['processing_count'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title"><?= $lang['stats_completed'] ?? 'Completados' ?></h5>
                    <h2 class="mb-0"><?= $stats['completed_count'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title"><?= $lang['stats_tokens'] ?? 'Tokens hoy' ?></h5>
                    <h2 class="mb-0"><?= number_format($stats['tokens_today'] ?? 0) ?></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Queue -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><?= $lang['queue_recent'] ?? 'Cola reciente' ?></h5>
                    <a href="/admin/plugins/cross-publisher/queue" class="btn btn-sm btn-outline-primary">
                        <?= $lang['queue_view_all'] ?? 'Ver todo' ?>
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentQueue)): ?>
                        <p class="text-muted"><?= $lang['queue_empty'] ?? 'No hay items en la cola' ?></p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><?= $lang['queue_post'] ?? 'Post' ?></th>
                                        <th><?= $lang['queue_target'] ?? 'Destino' ?></th>
                                        <th><?= $lang['queue_status'] ?? 'Estado' ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentQueue as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars(mb_substr($item->post_title ?? '', 0, 40)) ?>...</td>
                                            <td><?= htmlspecialchars($item->target_tenant_name ?? '') ?></td>
                                            <td>
                                                <?php
                                                $statusClass = match($item->status) {
                                                    'pending' => 'warning',
                                                    'processing' => 'info',
                                                    'completed' => 'success',
                                                    'failed' => 'danger',
                                                    default => 'secondary'
                                                };
                                                ?>
                                                <span class="badge bg-<?= $statusClass ?>">
                                                    <?= $lang['status_' . $item->status] ?? $item->status ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions & Network -->
        <div class="col-md-4">
            <!-- Quick Actions -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><?= $lang['quick_actions'] ?? 'Acciones rápidas' ?></h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="/admin/plugins/cross-publisher/queue/create" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> <?= $lang['queue_add'] ?? 'Publicar en red' ?>
                        </a>
                        <?php if ($stats['pending_count'] > 0): ?>
                            <a href="/admin/plugins/cross-publisher/queue/process-all" class="btn btn-success">
                                <i class="bi bi-play-circle"></i> <?= $lang['queue_process_all'] ?? 'Procesar cola' ?>
                            </a>
                        <?php endif; ?>
                        <a href="/admin/plugins/cross-publisher/settings" class="btn btn-outline-secondary">
                            <i class="bi bi-gear"></i> <?= $lang['menu_settings'] ?? 'Configuración' ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Network Members -->
            <?php if ($networkKey && !empty($networkTenants)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><?= $lang['network_members'] ?? 'Miembros de la red' ?></h5>
                    </div>
                    <ul class="list-group list-group-flush">
                        <?php foreach (array_slice($networkTenants, 0, 5) as $tenant): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= htmlspecialchars($tenant->tenant_name) ?></strong>
                                    <br><small class="text-muted"><?= htmlspecialchars($tenant->domain) ?></small>
                                </div>
                                <span class="badge bg-<?= $tenant->default_language === 'es' ? 'primary' : 'secondary' ?>">
                                    <?= strtoupper($tenant->default_language) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (count($networkTenants) > 5): ?>
                        <div class="card-footer text-center">
                            <a href="/admin/plugins/cross-publisher/network">
                                +<?= count($networkTenants) - 5 ?> más
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
