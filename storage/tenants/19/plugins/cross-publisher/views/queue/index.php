<?php
/**
 * Cross-Publisher - Queue List View
 */
$pageTitle = $lang['queue_title'] ?? 'Cola de publicación';
?>

<div class="cross-publisher-queue">
    <div class="page-header mb-4 d-flex justify-content-between align-items-center">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
        <div>
            <a href="/admin/plugins/cross-publisher/queue/process-all" class="btn btn-success me-2">
                <i class="bi bi-play-circle"></i> <?= $lang['queue_process_all'] ?? 'Procesar todo' ?>
            </a>
            <a href="/admin/plugins/cross-publisher/queue/create" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> <?= $lang['queue_add'] ?? 'Nuevo' ?>
            </a>
        </div>
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

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label"><?= $lang['queue_filter_status'] ?? 'Estado' ?></label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">Todos</option>
                        <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>
                            <?= $lang['status_pending'] ?? 'Pendiente' ?>
                        </option>
                        <option value="processing" <?= ($filters['status'] ?? '') === 'processing' ? 'selected' : '' ?>>
                            <?= $lang['status_processing'] ?? 'Procesando' ?>
                        </option>
                        <option value="completed" <?= ($filters['status'] ?? '') === 'completed' ? 'selected' : '' ?>>
                            <?= $lang['status_completed'] ?? 'Completado' ?>
                        </option>
                        <option value="failed" <?= ($filters['status'] ?? '') === 'failed' ? 'selected' : '' ?>>
                            <?= $lang['status_failed'] ?? 'Fallido' ?>
                        </option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($queue)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <p class="text-muted"><?= $lang['queue_empty'] ?? 'No hay items en la cola.' ?></p>
                <a href="/admin/plugins/cross-publisher/queue/create" class="btn btn-primary">
                    <?= $lang['queue_add'] ?? 'Añadir' ?>
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th><?= $lang['queue_post'] ?? 'Post' ?></th>
                            <th><?= $lang['queue_target'] ?? 'Destino' ?></th>
                            <th><?= $lang['queue_status'] ?? 'Estado' ?></th>
                            <th><?= $lang['queue_date'] ?? 'Fecha' ?></th>
                            <th><?= $lang['queue_actions'] ?? 'Acciones' ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($queue as $item): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars(mb_substr($item->post_title ?? '', 0, 50)) ?></strong>
                                    <?php if ($item->translate): ?>
                                        <br><small class="text-info">
                                            <i class="bi bi-translate"></i> Traducir a <?= strtoupper($item->target_language) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($item->target_tenant_name ?? '') ?></strong>
                                    <br><small class="text-muted"><?= htmlspecialchars($item->target_domain ?? '') ?></small>
                                </td>
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
                                    <?php if ($item->status === 'failed' && !empty($item->error_message)): ?>
                                        <br><small class="text-danger" title="<?= htmlspecialchars($item->error_message) ?>">
                                            <?= htmlspecialchars(mb_substr($item->error_message, 0, 30)) ?>...
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?= date('d/m/Y H:i', strtotime($item->created_at)) ?></small>
                                    <?php if ($item->completed_at): ?>
                                        <br><small class="text-success"><?= date('d/m H:i', strtotime($item->completed_at)) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($item->status === 'pending'): ?>
                                            <a href="/admin/plugins/cross-publisher/queue/<?= $item->id ?>/process"
                                               class="btn btn-outline-success"
                                               title="<?= $lang['queue_process'] ?? 'Procesar' ?>">
                                                <i class="bi bi-play"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($item->status === 'completed' && $item->target_post_id): ?>
                                            <a href="https://<?= htmlspecialchars($item->target_domain) ?>/admin/blog/posts/<?= $item->target_post_id ?>/edit"
                                               class="btn btn-outline-primary"
                                               target="_blank"
                                               title="<?= $lang['queue_view_post'] ?? 'Ver post' ?>">
                                                <i class="bi bi-box-arrow-up-right"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($item->status !== 'processing'): ?>
                                            <a href="/admin/plugins/cross-publisher/queue/<?= $item->id ?>/delete"
                                               class="btn btn-outline-danger"
                                               onclick="return confirm('¿Estás seguro?')"
                                               title="<?= $lang['queue_delete'] ?? 'Eliminar' ?>">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
