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

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
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
                            <?php foreach ($items as $item):
                                $isGroup = $item->_is_group ?? false;
                                $clusterItems = $item->_cluster_items ?? [];
                                $sourceCount = $item->_source_count ?? 1;
                                $bestItem = $item->_best_item ?? $item;
                                $statusClass = match($bestItem->status) {
                                    'pending' => 'secondary',
                                    'processing' => 'info',
                                    'ready' => 'warning',
                                    'approved' => 'success',
                                    'rejected' => 'danger',
                                    'published' => 'primary',
                                    default => 'secondary'
                                };
                            ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="ids[]" value="<?= $bestItem->id ?>" class="form-check-input item-checkbox">
                                    </td>
                                    <td>
                                        <a href="/admin/plugins/news-aggregator/items/<?= $bestItem->id ?>">
                                            <strong><?= htmlspecialchars(mb_substr($bestItem->original_title, 0, 80)) ?></strong>
                                        </a>
                                        <?php if (!empty($bestItem->rewritten_title)): ?>
                                            <br><small class="text-success">
                                                <i class="bi bi-check-circle"></i>
                                                <?= htmlspecialchars(mb_substr($bestItem->rewritten_title, 0, 60)) ?>...
                                            </small>
                                        <?php endif; ?>
                                        <?php if ($isGroup && $sourceCount > 1): ?>
                                            <div class="mt-2">
                                                <a href="javascript:void(0)" class="text-decoration-none small" onclick="document.getElementById('cluster-<?= $bestItem->id ?>').classList.toggle('d-none')">
                                                    <span class="badge bg-info"><i class="bi bi-shield-check"></i> <?= $sourceCount ?> fuentes verificadas</span>
                                                    <i class="bi bi-chevron-down ms-1" style="font-size:0.7em;"></i>
                                                </a>
                                                <div id="cluster-<?= $bestItem->id ?>" class="d-none mt-2 border rounded p-2" style="background:#f8f9fa; font-size:0.8em;">
                                                    <?php $ciNum = 0; foreach ($clusterItems as $ci): $ciNum++; ?>
                                                        <div class="d-flex align-items-start mb-2 <?= $ciNum < count($clusterItems) ? 'pb-2 border-bottom' : '' ?>">
                                                            <span class="badge bg-<?= $ci->id === $bestItem->id ? 'primary' : 'light text-dark border' ?> me-2" style="font-size:0.7em; min-width: 22px;"><?= $ciNum ?></span>
                                                            <div class="flex-grow-1">
                                                                <a href="/admin/plugins/news-aggregator/items/<?= $ci->id ?>" class="fw-semibold text-decoration-none">
                                                                    <?= htmlspecialchars(mb_substr($ci->original_title ?? '', 0, 90)) ?>
                                                                </a>
                                                                <div class="text-muted" style="font-size:0.85em;">
                                                                    <?php if (!empty($ci->feed_name)): ?>
                                                                        <span><i class="bi bi-rss"></i> <?= htmlspecialchars($ci->feed_name) ?></span>
                                                                    <?php endif; ?>
                                                                    <?php if (!empty($ci->original_url)): ?>
                                                                        <a href="<?= htmlspecialchars($ci->original_url) ?>" target="_blank" rel="noopener noreferrer" class="ms-2 text-muted">
                                                                            <i class="bi bi-box-arrow-up-right"></i> URL original
                                                                        </a>
                                                                    <?php endif; ?>
                                                                    <?php if (!empty($ci->original_author)): ?>
                                                                        <span class="ms-2"><i class="bi bi-person"></i> <?= htmlspecialchars($ci->original_author) ?></span>
                                                                    <?php endif; ?>
                                                                    <span class="ms-2"><i class="bi bi-calendar"></i> <?= date('d/m H:i', strtotime($ci->created_at)) ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($bestItem->source_name ?? '-') ?></small>
                                        <?php if ($isGroup && $sourceCount > 1): ?>
                                            <br><span class="badge <?= $sourceCount >= 3 ? 'bg-success' : 'bg-info' ?>" style="font-size:0.6em;">
                                                <i class="bi bi-shield-check"></i> <?= $sourceCount ?> fuentes
                                            </span>
                                        <?php elseif (($item->processing_type ?? 'direct') === 'verified'): ?>
                                            <br><span class="badge bg-info" style="font-size:0.6em;"><i class="bi bi-shield-check"></i> Verificada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $statusClass ?>">
                                            <?= $lang['items_status_' . $bestItem->status] ?? $bestItem->status ?>
                                        </span>
                                        <?php if ($bestItem->tokens_used > 0): ?>
                                            <br><small class="text-muted"><?= number_format($bestItem->tokens_used) ?> tokens</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= date('d/m/Y H:i', strtotime($bestItem->created_at)) ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="/admin/plugins/news-aggregator/items/<?= $bestItem->id ?>"
                                               class="btn btn-outline-primary"
                                               title="<?= $lang['items_view'] ?? 'View' ?>">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($bestItem->status === 'ready' || $bestItem->status === 'approved'): ?>
                                                <a href="/admin/plugins/news-aggregator/items/<?= $bestItem->id ?>/approve"
                                                   class="btn btn-outline-success"
                                                   title="<?= $lang['items_approve'] ?? 'Approve' ?>">
                                                    <i class="bi bi-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($bestItem->status === 'approved'): ?>
                                                <a href="/admin/plugins/news-aggregator/items/<?= $bestItem->id ?>/publish"
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
