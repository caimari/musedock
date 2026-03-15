<?php
/**
 * News Aggregator - Logs View
 */
$pageTitle = $lang['logs_title'] ?? 'Activity Logs';
?>

<div class="news-aggregator-logs">
    <div class="page-header mb-4">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label"><?= $lang['logs_action'] ?? 'Action' ?></label>
                    <select name="action" class="form-select" onchange="this.form.submit()">
                        <option value="">Todas</option>
                        <option value="fetch" <?= ($filters['action'] ?? '') === 'fetch' ? 'selected' : '' ?>>
                            <?= $lang['logs_action_fetch'] ?? 'Fetch' ?>
                        </option>
                        <option value="rewrite" <?= ($filters['action'] ?? '') === 'rewrite' ? 'selected' : '' ?>>
                            <?= $lang['logs_action_rewrite'] ?? 'Rewrite' ?>
                        </option>
                        <option value="approve" <?= ($filters['action'] ?? '') === 'approve' ? 'selected' : '' ?>>
                            <?= $lang['logs_action_approve'] ?? 'Approve' ?>
                        </option>
                        <option value="reject" <?= ($filters['action'] ?? '') === 'reject' ? 'selected' : '' ?>>
                            <?= $lang['logs_action_reject'] ?? 'Reject' ?>
                        </option>
                        <option value="publish" <?= ($filters['action'] ?? '') === 'publish' ? 'selected' : '' ?>>
                            <?= $lang['logs_action_publish'] ?? 'Publish' ?>
                        </option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= $lang['logs_status'] ?? 'Status' ?></label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">Todos</option>
                        <option value="success" <?= ($filters['status'] ?? '') === 'success' ? 'selected' : '' ?>>Success</option>
                        <option value="failed" <?= ($filters['status'] ?? '') === 'failed' ? 'selected' : '' ?>>Failed</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($logs)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <p class="text-muted"><?= $lang['logs_empty'] ?? 'No logs.' ?></p>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th><?= $lang['logs_date'] ?? 'Date' ?></th>
                            <th><?= $lang['logs_action'] ?? 'Action' ?></th>
                            <th><?= $lang['logs_status'] ?? 'Status' ?></th>
                            <th><?= $lang['logs_items'] ?? 'Items' ?></th>
                            <th><?= $lang['logs_tokens'] ?? 'Tokens' ?></th>
                            <th><?= $lang['logs_error'] ?? 'Error' ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <small><?= date('d/m/Y H:i:s', strtotime($log->created_at)) ?></small>
                                </td>
                                <td>
                                    <?php
                                    $actionClass = match($log->action) {
                                        'fetch' => 'info',
                                        'rewrite' => 'primary',
                                        'approve' => 'success',
                                        'reject' => 'warning',
                                        'publish' => 'success',
                                        default => 'secondary'
                                    };
                                    ?>
                                    <span class="badge bg-<?= $actionClass ?>">
                                        <?= $lang['logs_action_' . $log->action] ?? $log->action ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($log->status === 'success'): ?>
                                        <span class="badge bg-success">OK</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Error</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $log->items_count ?? '-' ?></td>
                                <td><?= $log->tokens_used ? number_format($log->tokens_used) : '-' ?></td>
                                <td>
                                    <?php if (!empty($log->error_message)): ?>
                                        <small class="text-danger" title="<?= htmlspecialchars($log->error_message) ?>">
                                            <?= htmlspecialchars(mb_substr($log->error_message, 0, 50)) ?>...
                                        </small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
