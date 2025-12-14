<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __instagram('gallery.title'); ?> - @<?php echo htmlspecialchars($connection->username); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-md-8">
                <a href="/admin/instagram" class="btn btn-sm btn-outline-secondary mb-2">
                    <i class="bi bi-arrow-left"></i> <?php echo __instagram('common.back'); ?>
                </a>
                <h1 class="h3 mb-1">
                    <i class="bi bi-instagram text-danger"></i>
                    <?php echo __instagram('gallery.title'); ?> - @<?php echo htmlspecialchars($connection->username); ?>
                </h1>
                <p class="text-muted">Configura y obtén el shortcode para insertar en tu contenido</p>
            </div>
        </div>

        <div class="row">
            <!-- Configuración -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="bi bi-sliders"></i> Configuración</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Layout</label>
                            <select class="form-select" id="layoutSelect">
                                <?php foreach ($layouts as $key => $layout): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $defaultLayout === $key ? 'selected' : ''; ?>>
                                        <?php echo $layout['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted" id="layoutDescription"></small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Columnas</label>
                            <input type="number" class="form-control" id="columnsInput" min="1" max="6" value="<?php echo $defaultColumns; ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Espacio (px)</label>
                            <input type="number" class="form-control" id="gapInput" min="0" max="50" value="<?php echo $defaultGap; ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Límite de Posts</label>
                            <input type="number" class="form-control" id="limitInput" min="1" max="50" value="12">
                        </div>

                        <hr>

                        <div class="mb-3">
                            <label class="form-label"><strong><?php echo __instagram('gallery.shortcode'); ?></strong></label>
                            <div class="input-group">
                                <input type="text" class="form-control font-monospace" id="shortcodeText" readonly>
                                <button class="btn btn-outline-secondary" type="button" onclick="copyShortcode()">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                            <small class="form-text text-muted"><?php echo __instagram('gallery.usage_info'); ?></small>
                        </div>

                        <div class="alert alert-info small mb-0">
                            <i class="bi bi-info-circle"></i>
                            <strong>Ejemplos de uso:</strong>
                            <ul class="mb-0 mt-2">
                                <li><code>[instagram connection=<?php echo $connection->id; ?>]</code></li>
                                <li><code>[instagram connection=<?php echo $connection->id; ?> layout="masonry"]</code></li>
                                <li><code>[instagram username="<?php echo $connection->username; ?>"]</code></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vista Previa -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-eye"></i> <?php echo __instagram('gallery.preview'); ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($posts)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-image" style="font-size: 4rem; color: #ddd;"></i>
                                <h4 class="mt-3"><?php echo __instagram('gallery.no_preview'); ?></h4>
                            </div>
                        <?php else: ?>
                            <div id="galleryPreview">
                                <?php
                                    // Generate preview using helper
                                    $previewHtml = render_instagram_gallery_html(
                                        $connection,
                                        array_slice($posts, 0, 12),
                                        $defaultLayout,
                                        $defaultColumns,
                                        $defaultGap,
                                        [
                                            'show_caption' => true,
                                            'caption_length' => 100,
                                            'lazy_load' => true,
                                            'hover_effect' => 'zoom'
                                        ]
                                    );
                                    echo $previewHtml;
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const layoutDescriptions = <?php echo json_encode($layouts); ?>;
        const connectionId = <?php echo $connection->id; ?>;

        function updateShortcode() {
            const layout = document.getElementById('layoutSelect').value;
            const columns = document.getElementById('columnsInput').value;
            const gap = document.getElementById('gapInput').value;
            const limit = document.getElementById('limitInput').value;

            let shortcode = `[instagram connection=${connectionId}`;

            if (layout !== '<?php echo $defaultLayout; ?>') {
                shortcode += ` layout="${layout}"`;
            }
            if (columns !== '<?php echo $defaultColumns; ?>') {
                shortcode += ` columns=${columns}`;
            }
            if (gap !== '<?php echo $defaultGap; ?>') {
                shortcode += ` gap=${gap}`;
            }
            if (limit !== '12') {
                shortcode += ` limit=${limit}`;
            }

            shortcode += ']';

            document.getElementById('shortcodeText').value = shortcode;

            // Update layout description
            const layoutDesc = layoutDescriptions[layout];
            if (layoutDesc) {
                document.getElementById('layoutDescription').textContent = layoutDesc.description;
            }
        }

        function copyShortcode() {
            const shortcodeInput = document.getElementById('shortcodeText');
            shortcodeInput.select();
            navigator.clipboard.writeText(shortcodeInput.value).then(() => {
                Swal.fire({
                    icon: 'success',
                    title: '<?php echo __instagram('gallery.shortcode_copied'); ?>',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000,
                    timerProgressBar: true
                });
            });
        }

        // Event listeners
        document.getElementById('layoutSelect').addEventListener('change', updateShortcode);
        document.getElementById('columnsInput').addEventListener('input', updateShortcode);
        document.getElementById('gapInput').addEventListener('input', updateShortcode);
        document.getElementById('limitInput').addEventListener('input', updateShortcode);

        // Initial update
        updateShortcode();
    </script>
</body>
</html>
