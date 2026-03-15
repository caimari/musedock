<?php
/**
 * News Aggregator - Dashboard View
 */
$pageTitle = $lang['dashboard_title'] ?? 'News Aggregator';
?>

<div class="news-aggregator-dashboard">
    <div class="page-header mb-4">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title"><?= $lang['dashboard_stats_sources'] ?? 'Active Sources' ?></h5>
                    <h2 class="mb-0"><?= $stats['sources_count'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title"><?= $lang['dashboard_stats_items_today'] ?? 'Captured Today' ?></h5>
                    <h2 class="mb-0"><?= $stats['items_today'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h5 class="card-title"><?= $lang['dashboard_stats_pending'] ?? 'Pending Review' ?></h5>
                    <h2 class="mb-0"><?= $stats['pending_count'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title"><?= $lang['dashboard_stats_tokens'] ?? 'Tokens Today' ?></h5>
                    <h2 class="mb-0"><?= number_format($stats['tokens_today'] ?? 0) ?></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Items -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><?= $lang['dashboard_recent'] ?? 'Latest Captured News' ?></h5>
                    <a href="/admin/plugins/news-aggregator/items" class="btn btn-sm btn-outline-primary">
                        <?= $lang['items_title'] ?? 'View All' ?>
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentItems)): ?>
                        <p class="text-muted"><?= $lang['dashboard_no_data'] ?? 'No data to display' ?></p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><?= $lang['items_original'] ?? 'Original' ?></th>
                                        <th><?= $lang['items_source'] ?? 'Source' ?></th>
                                        <th><?= $lang['items_status'] ?? 'Status' ?></th>
                                        <th><?= $lang['items_date'] ?? 'Date' ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentItems as $item): ?>
                                        <tr>
                                            <td>
                                                <a href="/admin/plugins/news-aggregator/items/<?= $item->id ?>">
                                                    <?= htmlspecialchars(mb_substr($item->original_title, 0, 60)) ?>...
                                                </a>
                                            </td>
                                            <td><?= htmlspecialchars($item->source_name ?? '-') ?></td>
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
                                            </td>
                                            <td><?= date('d/m H:i', strtotime($item->created_at)) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Errors -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><?= $lang['dashboard_errors'] ?? 'Recent Errors' ?></h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recentErrors)): ?>
                        <p class="text-muted"><?= $lang['dashboard_no_data'] ?? 'No errors' ?></p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($recentErrors as $error): ?>
                                <li class="list-group-item px-0">
                                    <small class="text-danger">
                                        <strong><?= htmlspecialchars($error->source_name ?? $error->action) ?></strong>
                                        <br>
                                        <?= htmlspecialchars(mb_substr($error->error_message ?? '', 0, 100)) ?>
                                        <br>
                                        <span class="text-muted"><?= date('d/m H:i', strtotime($error->created_at)) ?></span>
                                    </small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">Acciones Rápidas</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="/admin/plugins/news-aggregator/sources/create" class="btn btn-outline-primary">
                            <i class="bi bi-plus-circle"></i> <?= $lang['sources_add'] ?? 'Add Source' ?>
                        </a>
                        <a href="/admin/plugins/news-aggregator/items?status=ready" class="btn btn-outline-warning">
                            <i class="bi bi-eye"></i> <?= $lang['items_status_ready'] ?? 'Review Pending' ?>
                        </a>
                        <a href="/admin/plugins/news-aggregator/settings" class="btn btn-outline-secondary">
                            <i class="bi bi-gear"></i> <?= $lang['menu_settings'] ?? 'Settings' ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
