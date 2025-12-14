<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __instagram('settings.settings'); ?> - Instagram Gallery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-md-8">
                <a href="/musedock/instagram" class="btn btn-sm btn-outline-secondary mb-2">
                    <i class="bi bi-arrow-left"></i> <?php echo __instagram('common.back'); ?>
                </a>
                <h1 class="h3 mb-1">
                    <i class="bi bi-gear text-danger"></i>
                    <?php echo __instagram('settings.settings'); ?>
                </h1>
                <p class="text-muted">Configuración global del módulo Instagram Gallery</p>
            </div>
        </div>

        <form method="POST" action="/musedock/instagram/settings">
            <div class="row">
                <div class="col-lg-8">
                    <!-- API Credentials -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-key"></i> <?php echo __instagram('settings.api_credentials'); ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <strong>Configuración de Instagram Basic Display API:</strong>
                                <ol class="mb-0 mt-2">
                                    <li>Ve a <a href="https://developers.facebook.com/" target="_blank">Facebook Developers</a></li>
                                    <li>Crea una app y configura Instagram Basic Display</li>
                                    <li>Copia el App ID y App Secret aquí</li>
                                </ol>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><?php echo __instagram('settings.instagram_app_id'); ?></label>
                                <input type="text" class="form-control" name="instagram_app_id"
                                       value="<?php echo htmlspecialchars($settings['instagram_app_id'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><?php echo __instagram('settings.instagram_app_secret'); ?></label>
                                <input type="password" class="form-control" name="instagram_app_secret"
                                       value="<?php echo htmlspecialchars($settings['instagram_app_secret'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><?php echo __instagram('settings.instagram_redirect_uri'); ?></label>
                                <input type="url" class="form-control" name="instagram_redirect_uri"
                                       value="<?php echo htmlspecialchars($settings['instagram_redirect_uri'] ?? ''); ?>"
                                       placeholder="https://tusitio.com/musedock/instagram/callback">
                                <small class="form-text text-muted">Debe coincidir con la configurada en Facebook Developers</small>
                            </div>
                        </div>
                    </div>

                    <!-- Display Settings -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-palette"></i> <?php echo __instagram('settings.display_settings'); ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><?php echo __instagram('settings.default_layout'); ?></label>
                                    <select class="form-select" name="default_layout">
                                        <?php foreach ($layouts as $key => $layout): ?>
                                            <option value="<?php echo $key; ?>"
                                                <?php echo ($settings['default_layout'] ?? 'grid') === $key ? 'selected' : ''; ?>>
                                                <?php echo $layout['name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><?php echo __instagram('settings.default_columns'); ?></label>
                                    <input type="number" class="form-control" name="default_columns" min="1" max="6"
                                           value="<?php echo $settings['default_columns'] ?? 3; ?>">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><?php echo __instagram('settings.default_gap'); ?></label>
                                    <input type="number" class="form-control" name="default_gap" min="0" max="50"
                                           value="<?php echo $settings['default_gap'] ?? 10; ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?php echo __instagram('settings.max_posts_per_gallery'); ?></label>
                                    <input type="number" class="form-control" name="max_posts_per_gallery" min="1" max="100"
                                           value="<?php echo $settings['max_posts_per_gallery'] ?? 50; ?>">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?php echo __instagram('settings.caption_max_length'); ?></label>
                                    <input type="number" class="form-control" name="caption_max_length" min="50" max="500"
                                           value="<?php echo $settings['caption_max_length'] ?? 150; ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="show_captions"
                                               <?php echo ($settings['show_captions'] ?? true) ? 'checked' : ''; ?>>
                                        <label class="form-check-label"><?php echo __instagram('settings.show_captions'); ?></label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="enable_lightbox"
                                               <?php echo ($settings['enable_lightbox'] ?? true) ? 'checked' : ''; ?>>
                                        <label class="form-check-label"><?php echo __instagram('settings.enable_lightbox'); ?></label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="enable_lazy_loading"
                                               <?php echo ($settings['enable_lazy_loading'] ?? true) ? 'checked' : ''; ?>>
                                        <label class="form-check-label"><?php echo __instagram('settings.enable_lazy_loading'); ?></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cache Settings -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-hdd"></i> <?php echo __instagram('settings.cache_settings'); ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?php echo __instagram('settings.cache_duration_hours'); ?></label>
                                    <input type="number" class="form-control" name="cache_duration_hours" min="1" max="168"
                                           value="<?php echo $settings['cache_duration_hours'] ?? 6; ?>">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?php echo __instagram('settings.token_refresh_threshold_days'); ?></label>
                                    <input type="number" class="form-control" name="token_refresh_threshold_days" min="1" max="30"
                                           value="<?php echo $settings['token_refresh_threshold_days'] ?? 7; ?>">
                                </div>
                            </div>

                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="auto_refresh_tokens"
                                       <?php echo ($settings['auto_refresh_tokens'] ?? true) ? 'checked' : ''; ?>>
                                <label class="form-check-label"><?php echo __instagram('settings.auto_refresh_tokens'); ?></label>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mb-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-circle"></i> <?php echo __instagram('common.save'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
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
    </script>
</body>
</html>
