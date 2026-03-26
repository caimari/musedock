@extends('layouts::app')

@section('title', __instagram('connection.connections') . ' - Instagram Gallery')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endpush

@php
    $adminBase = '/' . admin_path();
@endphp

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-md-8">
                <h1 class="h3 mb-1">
                    <i class="bi bi-instagram text-danger"></i>
                    <?php echo __instagram('connection.connections'); ?>
                </h1>
                <p class="text-muted"><?php echo __instagram('module.description'); ?></p>
            </div>
            <div class="col-md-4 text-end">
                <a href="{{ $adminBase }}/instagram/settings" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-gear"></i> <?php echo __instagram('settings.settings'); ?>
                </a>
                <?php if ($apiConfigured): ?>
                    <a href="{{ $adminBase }}/instagram/connect" class="btn btn-danger">
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
                <a href="{{ $adminBase }}/instagram/settings" class="alert-link"><?php echo __instagram('connection.configure_api'); ?></a>
            </div>
        <?php endif; ?>

        <?php if (empty($connections)): ?>
            <div class="card mb-4">
                <div class="card-body text-center py-5">
                    <i class="bi bi-instagram" style="font-size: 4rem; color: #E1306C;"></i>
                    <h4 class="mt-3"><?php echo __instagram('connection.no_connections'); ?></h4>
                    <p class="text-muted"><?php echo __instagram('connection.connect_first'); ?></p>
                    <?php if ($apiConfigured): ?>
                        <a href="{{ $adminBase }}/instagram/connect" class="btn btn-danger mt-2">
                            <i class="bi bi-instagram"></i> <?php echo __instagram('connection.connect'); ?>
                        </a>
                    <?php endif; ?>
                </div>
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
                                        <div class="rounded-circle d-flex align-items-center justify-content-center me-3"
                                             style="width: 50px; height: 50px; background: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888);">
                                            <i class="bi bi-instagram text-white" style="font-size: 1.4rem;"></i>
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
                                        <span class="text-muted">(<?php echo $connection->getDaysUntilExpiration(); ?> dias)</span>
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
                                    <a href="{{ $adminBase }}/instagram/<?php echo $connection->id; ?>/posts" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-grid-3x3"></i> <?php echo __instagram('post.posts'); ?>
                                    </a>
                                    <a href="{{ $adminBase }}/instagram/<?php echo $connection->id; ?>/gallery" class="btn btn-sm btn-outline-success" title="Shortcode">
                                        <i class="bi bi-code-square"></i>
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

        <!-- Instrucciones -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-book me-2"></i>Como usar Instagram Gallery</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary"><i class="bi bi-1-circle me-1"></i> Configurar la API</h6>
                        <p class="small text-muted mb-3">
                            Ve a <a href="{{ $adminBase }}/instagram/settings"><i class="bi bi-gear"></i> Configuracion</a> e introduce las credenciales de tu app de Instagram (App ID y App Secret). Necesitas crear una app en <a href="https://developers.facebook.com/" target="_blank">Facebook Developers</a> con el producto "Instagram Basic Display".
                        </p>

                        <h6 class="text-primary"><i class="bi bi-2-circle me-1"></i> Conectar tu cuenta</h6>
                        <p class="small text-muted mb-3">
                            Pulsa <strong>"Conectar Nueva Cuenta"</strong> y autoriza la app en Instagram. Se obtendra un token de acceso valido por 60 dias que se renueva automaticamente.
                        </p>

                        <h6 class="text-primary"><i class="bi bi-3-circle me-1"></i> Sincronizar posts</h6>
                        <p class="small text-muted mb-3">
                            Pulsa <strong>"Sincronizar Ahora"</strong> en la tarjeta de tu cuenta para descargar los posts mas recientes. Los posts se cachean en la base de datos para cargar rapido.
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary"><i class="bi bi-4-circle me-1"></i> Insertar en tu sitio</h6>
                        <p class="small text-muted mb-2">
                            Usa el shortcode en cualquier pagina o post para mostrar la galeria:
                        </p>
                        <div class="bg-light rounded p-3 mb-3">
                            <code class="d-block mb-1">[instagram connection=1]</code>
                            <code class="d-block mb-1">[instagram connection=1 layout="grid" columns=4]</code>
                            <code class="d-block mb-1">[instagram connection=1 layout="masonry" limit=12]</code>
                            <code class="d-block">[instagram connection=1 layout="carousel"]</code>
                        </div>

                        <h6>Parametros disponibles</h6>
                        <table class="table table-sm small mb-0">
                            <tbody>
                                <tr><td><code>connection</code></td><td>ID de la conexion (obligatorio)</td></tr>
                                <tr><td><code>layout</code></td><td>grid, masonry, carousel, lightbox, justified</td></tr>
                                <tr><td><code>columns</code></td><td>1-6 (por defecto: 3)</td></tr>
                                <tr><td><code>limit</code></td><td>1-50 posts a mostrar</td></tr>
                                <tr><td><code>gap</code></td><td>Espacio entre imagenes en px</td></tr>
                                <tr><td><code>show_caption</code></td><td>true/false</td></tr>
                                <tr><td><code>hover_effect</code></td><td>zoom, fade, none</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const adminBase = '{{ $adminBase }}';

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
                window.location.href = adminBase + '/instagram/settings';
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

        fetch(`${adminBase}/instagram/${id}/sync`, {
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
                form.action = `${adminBase}/instagram/${id}/disconnect`;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
</script>
@endpush
