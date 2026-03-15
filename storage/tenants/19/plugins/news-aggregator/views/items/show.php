<?php
/**
 * News Aggregator - Item Detail View
 */
$pageTitle = $lang['items_review_title'] ?? 'Review News';
?>

<div class="news-aggregator-item-detail">
    <div class="page-header mb-4 d-flex justify-content-between align-items-center">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
        <a href="/admin/plugins/news-aggregator/items" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
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

    <!-- Status and Actions -->
    <div class="card mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <?php
                $statusClass = match($item->status) {
                    'pending' => 'secondary',
                    'processing' => 'info',
                    'ready' => 'warning',
                    'approved' => 'success',
                    'rejected' => 'danger',
                    'published' => 'primary',
                    default => 'secondary'
                };
                ?>
                <span class="badge bg-<?= $statusClass ?> fs-6">
                    <?= $lang['items_status_' . $item->status] ?? $item->status ?>
                </span>
                <span class="ms-3 text-muted">
                    <?= $lang['items_source'] ?? 'Source' ?>: <strong><?= htmlspecialchars($item->source_name ?? '-') ?></strong>
                </span>
                <?php if ($item->tokens_used > 0): ?>
                    <span class="ms-3 text-muted">
                        Tokens: <strong><?= number_format($item->tokens_used) ?></strong>
                    </span>
                <?php endif; ?>
            </div>
            <div class="btn-group">
                <?php if ($item->status === 'pending'): ?>
                    <a href="/admin/plugins/news-aggregator/items/<?= $item->id ?>/rewrite" class="btn btn-info">
                        <i class="bi bi-robot"></i> <?= $lang['items_rewrite'] ?? 'Rewrite with AI' ?>
                    </a>
                <?php endif; ?>
                <?php if ($item->status === 'ready'): ?>
                    <a href="/admin/plugins/news-aggregator/items/<?= $item->id ?>/approve?next=1" class="btn btn-success">
                        <i class="bi bi-check-lg"></i> <?= $lang['items_approve'] ?? 'Approve' ?>
                    </a>
                    <a href="/admin/plugins/news-aggregator/items/<?= $item->id ?>/reject" class="btn btn-warning">
                        <i class="bi bi-x-lg"></i> <?= $lang['items_reject'] ?? 'Reject' ?>
                    </a>
                    <a href="/admin/plugins/news-aggregator/items/<?= $item->id ?>/rewrite" class="btn btn-outline-info">
                        <i class="bi bi-arrow-clockwise"></i> Reescribir de nuevo
                    </a>
                <?php endif; ?>
                <?php if ($item->status === 'approved'): ?>
                    <a href="/admin/plugins/news-aggregator/items/<?= $item->id ?>/publish" class="btn btn-primary">
                        <i class="bi bi-send"></i> <?= $lang['items_publish'] ?? 'Create Post' ?>
                    </a>
                <?php endif; ?>
                <?php if ($item->status === 'published' && $item->created_post_id): ?>
                    <a href="/admin/blog/posts/<?= $item->created_post_id ?>/edit" class="btn btn-outline-primary">
                        <i class="bi bi-pencil"></i> Editar Post
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Original Content -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><?= $lang['items_original'] ?? 'Original' ?></h5>
                </div>
                <div class="card-body">
                    <h4><?= htmlspecialchars($item->original_title) ?></h4>

                    <?php if (!empty($item->original_author)): ?>
                        <p class="text-muted mb-2">
                            <i class="bi bi-person"></i> <?= htmlspecialchars($item->original_author) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($item->original_published_at)): ?>
                        <p class="text-muted mb-2">
                            <i class="bi bi-calendar"></i> <?= date('d/m/Y H:i', strtotime($item->original_published_at)) ?>
                        </p>
                    <?php endif; ?>

                    <p class="mb-3">
                        <a href="<?= htmlspecialchars($item->original_url) ?>" target="_blank" class="text-decoration-none">
                            <i class="bi bi-link-45deg"></i> Ver original
                        </a>
                    </p>

                    <?php if (!empty($item->original_image_url)): ?>
                        <div class="mb-3">
                            <img src="<?= htmlspecialchars($item->original_image_url) ?>"
                                 alt="Original image"
                                 class="img-fluid rounded"
                                 style="max-height: 200px;"
                                 onerror="this.style.display='none'">
                        </div>
                    <?php endif; ?>

                    <div class="original-content" style="max-height: 400px; overflow-y: auto;">
                        <?= $item->original_content ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rewritten Content -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><?= $lang['items_rewritten'] ?? 'Rewritten' ?></h5>
                    <?php if (!empty($item->rewritten_title)): ?>
                        <button type="button" class="btn btn-sm btn-outline-light" data-bs-toggle="collapse" data-bs-target="#editForm">
                            <i class="bi bi-pencil"></i> <?= $lang['items_edit_rewrite'] ?? 'Edit' ?>
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($item->rewritten_title)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-robot fs-1"></i>
                            <p class="mt-3">No hay contenido reescrito todavía.</p>
                            <?php if ($item->status === 'pending'): ?>
                                <a href="/admin/plugins/news-aggregator/items/<?= $item->id ?>/rewrite" class="btn btn-info">
                                    <i class="bi bi-robot"></i> <?= $lang['items_rewrite'] ?? 'Rewrite with AI' ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- View Mode -->
                        <div id="viewContent">
                            <h4><?= htmlspecialchars($item->rewritten_title) ?></h4>

                            <?php if (!empty($item->rewritten_excerpt)): ?>
                                <p class="lead text-muted">
                                    <?= htmlspecialchars($item->rewritten_excerpt) ?>
                                </p>
                            <?php endif; ?>

                            <div class="rewritten-content" style="max-height: 400px; overflow-y: auto;">
                                <?= $item->rewritten_content ?>
                            </div>
                        </div>

                        <!-- Edit Mode -->
                        <div id="editForm" class="collapse">
                            <form action="/admin/plugins/news-aggregator/items/<?= $item->id ?>/update" method="POST">
                                <div class="mb-3">
                                    <label class="form-label"><?= $lang['items_rewritten_title'] ?? 'Title' ?></label>
                                    <input type="text" name="rewritten_title" class="form-control"
                                           value="<?= htmlspecialchars($item->rewritten_title) ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><?= $lang['items_rewritten_excerpt'] ?? 'Excerpt' ?></label>
                                    <textarea name="rewritten_excerpt" class="form-control" rows="2"><?= htmlspecialchars($item->rewritten_excerpt ?? '') ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><?= $lang['items_rewritten_content'] ?? 'Content' ?></label>
                                    <textarea name="rewritten_content" class="form-control" rows="10"><?= htmlspecialchars($item->rewritten_content) ?></textarea>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-success">
                                        <?= $lang['items_save'] ?? 'Save Changes' ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#editForm">
                                        Cancelar
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Metadata -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">Metadatos</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>ID:</strong> <?= $item->id ?>
                </div>
                <div class="col-md-3">
                    <strong>Capturado:</strong> <?= date('d/m/Y H:i', strtotime($item->created_at)) ?>
                </div>
                <div class="col-md-3">
                    <strong>Procesado:</strong> <?= $item->processed_at ? date('d/m/Y H:i', strtotime($item->processed_at)) : '-' ?>
                </div>
                <div class="col-md-3">
                    <strong>Revisado:</strong> <?= $item->reviewed_at ? date('d/m/Y H:i', strtotime($item->reviewed_at)) : '-' ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.original-content img,
.rewritten-content img {
    max-width: 100%;
    height: auto;
}
</style>
