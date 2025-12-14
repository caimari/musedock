<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __instagram('connection.connections'); ?> - Instagram Gallery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-md-8">
                <h1 class="h3 mb-1">
                    <i class="bi bi-instagram text-danger"></i>
                    <?php echo __instagram('connection.connections'); ?>
                </h1>
                <p class="text-muted"><?php echo __instagram('module.description'); ?></p>
            </div>
            <div class="col-md-4 text-end">
                <a href="/musedock/instagram/settings" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-gear"></i> <?php echo __instagram('settings.settings'); ?>
                </a>
                <?php if ($apiConfigured): ?>
                    <a href="/musedock/instagram/connect" class="btn btn-danger">
                        <i class="bi bi-instagram"></i> <?php echo __instagram('connection.connect_new'); ?>
                    </a>
                <?php else: ?>
                    <button class="btn btn-danger" onclick="showApiWarning()">
                        <i class="bi bi-instagram"></i> <?php echo __instagram('connection.connect_new'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$apiConfigured): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                <?php echo __instagram('connection.api_not_configured'); ?>
                <a href="/musedock/instagram/settings" class="alert-link"><?php echo __instagram('connection.configure_api'); ?></a>
            </div>
        <?php endif; ?>

        <?php if (empty($connections)): ?>
            <div class="text-center py-5">
                <i class="bi bi-instagram" style="font-size: 4rem; color: #ddd;"></i>
                <h4 class="mt-3"><?php echo __instagram('connection.no_connections'); ?></h4>
                <p class="text-muted"><?php echo __instagram('connection.connect_first'); ?></p>
                <?php if ($apiConfigured): ?>
                    <a href="/musedock/instagram/connect" class="btn btn-danger mt-3">
                        <i class="bi bi-instagram"></i> <?php echo __instagram('connection.connect'); ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($connections as $connection): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <?php if ($connection->profile_picture): ?>
                                        <img src="<?php echo htmlspecialchars($connection->profile_picture); ?>"
                                             alt="<?php echo htmlspecialchars($connection->username); ?>"
                                             class="rounded-circle me-3"
                                             style="width: 50px; height: 50px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center me-3"
                                             style="width: 50px; height: 50px;">
                                            <i class="bi bi-instagram"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="flex-grow-1">
                                        <h5 class="card-title mb-0">@<?php echo htmlspecialchars($connection->username); ?></h5>
                                        <small class="text-muted">ID: <?php echo $connection->id; ?></small>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <?php if ($connection->is_active && !$connection->isTokenExpired()): ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle"></i> <?php echo __instagram('connection.active'); ?>
                                        </span>
                                    <?php elseif ($connection->isTokenExpired()): ?>
                                        <span class="badge bg-danger">
                                            <i class="bi bi-x-circle"></i> <?php echo __instagram('connection.expired'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">
                                            <i class="bi bi-dash-circle"></i> <?php echo __instagram('connection.inactive'); ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($connection->isTokenExpiringSoon()): ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="bi bi-exclamation-triangle"></i> <?php echo __instagram('connection.expires_soon'); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <ul class="list-unstyled small mb-3">
                                    <li class="mb-1">
                                        <i class="bi bi-calendar-event text-muted"></i>
                                        <strong><?php echo __instagram('connection.token_expires_at'); ?>:</strong>
                                        <?php echo date('d/m/Y H:i', strtotime($connection->token_expires_at)); ?>
                                        <span class="text-muted">(<?php echo $connection->getDaysUntilExpiration(); ?> días)</span>
                                    </li>
                                    <?php if ($connection->last_synced_at): ?>
                                        <li class="mb-1">
                                            <i class="bi bi-arrow-repeat text-muted"></i>
                                            <strong><?php echo __instagram('connection.last_synced_at'); ?>:</strong>
                                            <?php echo date('d/m/Y H:i', strtotime($connection->last_synced_at)); ?>
                                        </li>
                                    <?php endif; ?>
                                    <?php if ($connection->last_error): ?>
                                        <li class="text-danger">
                                            <i class="bi bi-exclamation-circle"></i>
                                            <strong><?php echo __instagram('connection.last_error'); ?>:</strong>
                                            <small><?php echo htmlspecialchars($connection->last_error); ?></small>
                                        </li>
                                    <?php endif; ?>
                                </ul>

                                <div class="btn-group w-100" role="group">
                                    <button type="button" class="btn btn-sm btn-primary" onclick="syncConnection(<?php echo $connection->id; ?>)">
                                        <i class="bi bi-arrow-repeat"></i> <?php echo __instagram('connection.sync_now'); ?>
                                    </button>
                                    <a href="/musedock/instagram/<?php echo $connection->id; ?>/posts" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-grid-3x3"></i> <?php echo __instagram('post.posts'); ?>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDisconnect(<?php echo $connection->id; ?>, '<?php echo addslashes($connection->username); ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Show success/error messages
        <?php if (isset($_SESSION['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: '<?php echo __instagram('common.success'); ?>',
                text: '<?php echo addslashes($_SESSION['success']); ?>',
                confirmButtonColor: '#198754',
                timer: 3000,
                timerProgressBar: true
            });
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            Swal.fire({
                icon: 'error',
                title: '<?php echo __instagram('common.error'); ?>',
                html: '<?php echo addslashes($_SESSION['error']); ?>',
                confirmButtonColor: '#dc3545'
            });
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        function showApiWarning() {
            Swal.fire({
                icon: 'warning',
                title: '<?php echo __instagram('connection.api_not_configured'); ?>',
                text: 'Configura las credenciales de la API de Instagram primero.',
                confirmButtonText: '<?php echo __instagram('connection.configure_api'); ?>',
                confirmButtonColor: '#0d6efd',
                showCancelButton: true,
                cancelButtonText: '<?php echo __instagram('common.cancel'); ?>'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '/musedock/instagram/settings';
                }
            });
        }

        function syncConnection(id) {
            Swal.fire({
                title: '<?php echo __instagram('connection.syncing'); ?>',
                text: 'Obteniendo posts de Instagram...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch(`/musedock/instagram/${id}/sync`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '<?php echo __instagram('common.success'); ?>',
                        text: data.message,
                        confirmButtonColor: '#198754'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '<?php echo __instagram('common.error'); ?>',
                        text: data.message,
                        confirmButtonColor: '#dc3545'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: '<?php echo __instagram('common.error'); ?>',
                    text: error.message,
                    confirmButtonColor: '#dc3545'
                });
            });
        }

        function confirmDisconnect(id, username) {
            Swal.fire({
                icon: 'warning',
                title: '<?php echo __instagram('connection.confirm_disconnect'); ?>',
                html: `<p><?php echo __instagram('connection.disconnect_warning'); ?></p><p><strong>@${username}</strong></p>`,
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-trash me-1"></i> <?php echo __instagram('common.delete'); ?>',
                cancelButtonText: '<?php echo __instagram('common.cancel'); ?>'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = `/musedock/instagram/${id}/disconnect`;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
    </script>
</body>
</html>
