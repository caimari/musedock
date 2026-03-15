<?php
/**
 * News Aggregator - Sources List View
 */
$pageTitle = $lang['sources_title'] ?? 'News Sources';
?>

<div class="news-aggregator-sources">
    <div class="page-header mb-4 d-flex justify-content-between align-items-center">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
        <a href="/admin/plugins/news-aggregator/sources/create" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> <?= $lang['sources_add'] ?? 'Add Source' ?>
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

    <?php if (empty($sources)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <p class="text-muted mb-3"><?= $lang['sources_empty'] ?? 'No sources configured.' ?></p>
                <a href="/admin/plugins/news-aggregator/sources/create" class="btn btn-primary">
                    <?= $lang['sources_add'] ?? 'Add Source' ?>
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th><?= $lang['sources_name'] ?? 'Name' ?></th>
                            <th><?= $lang['sources_type'] ?? 'Type' ?></th>
                            <th><?= $lang['sources_status'] ?? 'Status' ?></th>
                            <th><?= $lang['sources_last_fetch'] ?? 'Last Fetch' ?></th>
                            <th><?= $lang['sources_last_count'] ?? 'Articles' ?></th>
                            <th><?= $lang['sources_actions'] ?? 'Actions' ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sources as $source): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($source->name) ?></strong>
                                    <?php if (!empty($source->keywords)): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($source->keywords) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $typeLabel = match($source->source_type) {
                                        'rss' => $lang['sources_type_rss'] ?? 'RSS/Atom',
                                        'newsapi' => $lang['sources_type_newsapi'] ?? 'NewsAPI',
                                        'gnews' => $lang['sources_type_gnews'] ?? 'GNews',
                                        'mediastack' => $lang['sources_type_mediastack'] ?? 'MediaStack',
                                        default => $source->source_type
                                    };
                                    ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($typeLabel) ?></span>
                                </td>
                                <td>
                                    <?php if ($source->enabled): ?>
                                        <span class="badge bg-success"><?= $lang['sources_status_active'] ?? 'Active' ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= $lang['sources_status_inactive'] ?? 'Inactive' ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($source->fetch_error)): ?>
                                        <br><small class="text-danger" title="<?= htmlspecialchars($source->fetch_error) ?>">
                                            <?= $lang['sources_error'] ?? 'Error' ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $source->last_fetch_at ? date('d/m/Y H:i', strtotime($source->last_fetch_at)) : '-' ?>
                                </td>
                                <td>
                                    <?= $source->last_fetch_count ?? 0 ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="/admin/plugins/news-aggregator/sources/<?= $source->id ?>/fetch"
                                           class="btn btn-outline-success"
                                           title="<?= $lang['sources_fetch_now'] ?? 'Fetch Now' ?>">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </a>
                                        <a href="/admin/plugins/news-aggregator/sources/<?= $source->id ?>/edit"
                                           class="btn btn-outline-primary"
                                           title="<?= $lang['sources_edit'] ?? 'Edit' ?>">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="/admin/plugins/news-aggregator/sources/<?= $source->id ?>/delete"
                                              method="POST"
                                              class="d-inline"
                                              onsubmit="return confirm('¿Estás seguro?')">
                                            <button type="submit" class="btn btn-outline-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
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
