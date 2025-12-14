<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __instagram('post.posts'); ?> - @<?php echo htmlspecialchars($connection->username); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-md-8">
                <a href="/musedock/instagram" class="btn btn-sm btn-outline-secondary mb-2">
                    <i class="bi bi-arrow-left"></i> <?php echo __instagram('common.back'); ?>
                </a>
                <h1 class="h3 mb-1">
                    <i class="bi bi-grid-3x3 text-danger"></i>
                    <?php echo __instagram('post.posts'); ?> - @<?php echo htmlspecialchars($connection->username); ?>
                </h1>
                <p class="text-muted"><?php echo __instagram('post.cached_posts'); ?>: <?php echo count($posts); ?></p>
            </div>
        </div>

        <?php if (empty($posts)): ?>
            <div class="text-center py-5">
                <i class="bi bi-image" style="font-size: 4rem; color: #ddd;"></i>
                <h4 class="mt-3"><?php echo __instagram('post.no_posts'); ?></h4>
                <p class="text-muted"><?php echo __instagram('post.sync_to_fetch'); ?></p>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($posts as $post): ?>
                    <div class="col-md-3 col-sm-6">
                        <div class="card h-100">
                            <a href="<?php echo htmlspecialchars($post->permalink); ?>" target="_blank" rel="noopener">
                                <img src="<?php echo htmlspecialchars($post->getDisplayUrl()); ?>"
                                     class="card-img-top"
                                     alt="<?php echo htmlspecialchars($post->caption ?? 'Instagram post'); ?>"
                                     style="aspect-ratio: 1; object-fit: cover;">
                                <?php if ($post->isVideo()): ?>
                                    <div class="position-absolute top-0 end-0 m-2">
                                        <span class="badge bg-dark"><i class="bi bi-play-circle-fill"></i></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($post->isCarousel()): ?>
                                    <div class="position-absolute top-0 end-0 m-2">
                                        <span class="badge bg-dark"><i class="bi bi-collection-fill"></i></span>
                                    </div>
                                <?php endif; ?>
                            </a>
                            <div class="card-body p-2">
                                <small class="text-muted d-block">
                                    <?php echo $post->getFormattedCaption(80); ?>
                                </small>
                                <div class="d-flex justify-content-between mt-2">
                                    <small class="text-muted">
                                        <i class="bi bi-calendar"></i> <?php echo $post->getTimeAgo(); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
