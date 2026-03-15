<?php
/**
 * News Aggregator - Items List View
 */
$pageTitle = $lang['items_title'] ?? 'Captured News';
?>

<div class="news-aggregator-items">
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

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label"><?= $lang['items_filter_status'] ?? 'Filter by status' ?></label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value=""><?= $lang['items_filter_all'] ?? 'All' ?></option>
                        <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>
                            <?= $lang['items_status_pending'] ?? 'Pending' ?>
                        </option>
                        <option value="processing" <?= ($filters['status'] ?? '') === 'processing' ? 'selected' : '' ?>>
                            <?= $lang['items_status_processing'] ?? 'Processing' ?>
                        </option>
                        <option value="ready" <?= ($filters['status'] ?? '') === 'ready' ? 'selected' : '' ?>>
                            <?= $lang['items_status_ready'] ?? 'Ready for review' ?>
                        </option>
                        <option value="approved" <?= ($filters['status'] ?? '') === 'approved' ? 'selected' : '' ?>>
                            <?= $lang['items_status_approved'] ?? 'Approved' ?>
                        </option>
                        <option value="rejected" <?= ($filters['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>
                            <?= $lang['items_status_rejected'] ?? 'Rejected' ?>
                        </option>
                        <option value="published" <?= ($filters['status'] ?? '') === 'published' ? 'selected' : '' ?>>
                            <?= $lang['items_status_published'] ?? 'Published' ?>
                        </option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= $lang['items_filter_source'] ?? 'Filter by source' ?></label>
                    <select name="source_id" class="form-select" onchange="this.form.submit()">
                        <option value=""><?= $lang['items_filter_all'] ?? 'All' ?></option>
                        <?php foreach ($sources as $source): ?>
                            <option value="<?= $source->id ?>" <?= ($filters['source_id'] ?? '') == $source->id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($source->name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($items)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <p class="text-muted"><?= $lang['items_empty'] ?? 'No news captured.' ?></p>
            </div>
        </div>
    <?php else: ?>
        <form id="bulkForm" action="/admin/plugins/news-aggregator/items/bulk" method="POST">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <input type="checkbox" id="selectAll" class="form-check-input me-2">
                        <label for="selectAll" class="form-check-label">Seleccionar todos</label>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="submit" name="action" value="approve" class="btn btn-outline-success">
                            <?= $lang['items_bulk_approve'] ?? 'Approve selected' ?>
                        </button>
                        <button type="submit" name="action" value="reject" class="btn btn-outline-warning">
                            <?= $lang['items_bulk_reject'] ?? 'Reject selected' ?>
                        </button>
                        <button type="submit" name="action" value="delete" class="btn btn-outline-danger"
                                onclick="return confirm('¿Estás seguro?')">
                            <?= $lang['items_bulk_delete'] ?? 'Delete selected' ?>
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th width="40"></th>
                                <th><?= $lang['items_original'] ?? 'Original' ?></th>
                                <th><?= $lang['items_source'] ?? 'Source' ?></th>
                                <th><?= $lang['items_status'] ?? 'Status' ?></th>
                                <th><?= $lang['items_date'] ?? 'Date' ?></th>
                                <th><?= $lang['items_actions'] ?? 'Actions' ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="ids[]" value="<?= $item->id ?>" class="form-check-input item-checkbox">
                                    </td>
                                    <td>
                                        <a href="/admin/plugins/news-aggregator/items/<?= $item->id ?>">
                                            <strong><?= htmlspecialchars(mb_substr($item->original_title, 0, 80)) ?></strong>
                                        </a>
                                        <?php if (!empty($item->rewritten_title)): ?>
                                            <br><small class="text-success">
                                                <i class="bi bi-check-circle"></i>
                                                <?= htmlspecialchars(mb_substr($item->rewritten_title, 0, 60)) ?>...
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($item->source_name ?? '-') ?></small>
                                    </td>
                                    <td>
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
                                        <span class="badge bg-<?= $statusClass ?>">
                                            <?= $lang['items_status_' . $item->status] ?? $item->status ?>
                                        </span>
                                        <?php if ($item->tokens_used > 0): ?>
                                            <br><small class="text-muted"><?= number_format($item->tokens_used) ?> tokens</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= date('d/m/Y H:i', strtotime($item->created_at)) ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="/admin/plugins/news-aggregator/items/<?= $item->id ?>"
                                               class="btn btn-outline-primary"
                                               title="<?= $lang['items_view'] ?? 'View' ?>">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($item->status === 'ready' || $item->status === 'approved'): ?>
                                                <a href="/admin/plugins/news-aggregator/items/<?= $item->id ?>/approve"
                                                   class="btn btn-outline-success"
                                                   title="<?= $lang['items_approve'] ?? 'Approve' ?>">
                                                    <i class="bi bi-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($item->status === 'approved'): ?>
                                                <a href="/admin/plugins/news-aggregator/items/<?= $item->id ?>/publish"
                                                   class="btn btn-outline-primary"
                                                   title="<?= $lang['items_publish'] ?? 'Create Post' ?>">
                                                    <i class="bi bi-send"></i>
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
        </form>

        <script>
            document.getElementById('selectAll').addEventListener('change', function() {
                document.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = this.checked);
            });
        </script>
    <?php endif; ?>
</div>
