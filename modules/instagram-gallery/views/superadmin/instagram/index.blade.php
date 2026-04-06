@extends('layouts::app')

@section('title', __instagram('connection.connections') . ' - Instagram Gallery')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endpush

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
            <div class="d-flex align-items-center gap-3">
                <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi bi-instagram" style="font-size:1.35rem;color:#fff;"></i>
                </div>
                <div>
                    <h3 class="mb-0" style="font-size:1.25rem;font-weight:700;"><?php echo __instagram('connection.connections'); ?></h3>
                    <p class="text-muted mb-0" style="font-size:0.85rem;"><?php echo __instagram('module.description'); ?></p>
                </div>
            </div>
            <div style="display:flex;gap:1rem;">
                <a href="/musedock/instagram/settings" style="display:flex;align-items:center;gap:0.35rem;font-size:0.85rem;padding:0.4rem 0.75rem;border-radius:6px;background:#f8f9fa;border:1px solid #e9ecef;color:#6c757d;text-decoration:none;">
                    <i class="bi bi-gear"></i>
                    <span><?php echo __instagram('settings.settings'); ?></span>
                </a>
                <?php if ($apiConfigured): ?>
                    <a href="/musedock/instagram/connect" style="display:flex;align-items:center;gap:0.35rem;font-size:0.85rem;padding:0.4rem 0.75rem;border-radius:6px;background:linear-gradient(135deg,#f09433,#dc2743,#bc1888);border:none;color:#fff;text-decoration:none;">
                        <i class="bi bi-instagram"></i>
                        <span><?php echo __instagram('connection.connect_new'); ?></span>
                    </a>
                <?php else: ?>
                    <button onclick="showApiWarning()" style="display:flex;align-items:center;gap:0.35rem;font-size:0.85rem;padding:0.4rem 0.75rem;border-radius:6px;background:linear-gradient(135deg,#f09433,#dc2743,#bc1888);border:none;color:#fff;cursor:pointer;">
                        <i class="bi bi-instagram"></i>
                        <span><?php echo __instagram('connection.connect_new'); ?></span>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($connections)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <div style="width:64px;height:64px;border-radius:16px;background:linear-gradient(135deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888);display:inline-flex;align-items:center;justify-content:center;margin-bottom:1rem;">
                        <i class="bi bi-instagram" style="font-size:1.75rem;color:#fff;"></i>
                    </div>
                    <?php if (!$apiConfigured): ?>
                        <h5 class="mb-2">Configura las credenciales de API</h5>
                        <p class="text-muted mb-3" style="max-width:440px;margin:0 auto;">Para conectar tu cuenta de Instagram necesitas configurar las credenciales de la API de Facebook Developers. También puedes usar el modo oEmbed para insertar posts sin API.</p>
                        <div class="d-flex justify-content-center gap-2">
                            <a href="/musedock/instagram/settings" style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.5rem 1.25rem;border-radius:8px;background:linear-gradient(135deg,#f09433,#dc2743,#bc1888);color:#fff;text-decoration:none;font-size:0.85rem;font-weight:500;">
                                <i class="bi bi-gear"></i> Configurar API
                            </a>
                        </div>
                        <div class="mt-3 pt-3 border-top" style="max-width:440px;margin:0 auto;">
                            <p class="text-muted small mb-1"><strong>Sin API:</strong> usa este shortcode para insertar posts públicos:</p>
                            <code style="font-size:0.8rem;">[instagram-post url="https://instagram.com/p/ABC123"]</code>
                        </div>
                    <?php else: ?>
                        <h5 class="mb-2"><?php echo __instagram('connection.no_connections'); ?></h5>
                        <p class="text-muted mb-3" style="max-width:400px;margin:0 auto;"><?php echo __instagram('connection.connect_first'); ?></p>
                        <a href="/musedock/instagram/connect" style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.5rem 1.25rem;border-radius:8px;background:linear-gradient(135deg,#f09433,#dc2743,#bc1888);color:#fff;text-decoration:none;font-size:0.85rem;font-weight:500;">
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
        {{-- Generador de Shortcodes --}}
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white d-flex align-items-center gap-2">
                <i class="bi bi-code-slash"></i>
                <h5 class="mb-0">Generador de Shortcodes</h5>
            </div>
            <div class="card-body p-0">
                {{-- oEmbed --}}
                <div class="p-3">
                    <h6 class="mb-1"><i class="bi bi-link-45deg me-1"></i> Post individual (oEmbed)</h6>
                    <p class="text-muted small mb-2">Pega la URL de cualquier post público. No necesita API.</p>
                    <div class="input-group input-group-sm" style="max-width:600px;">
                        <input type="url" class="form-control" id="oembedUrlInput" placeholder="https://www.instagram.com/p/ABC123/">
                        <button class="btn btn-outline-secondary" type="button" id="btnGenerateOembed">
                            <i class="bi bi-lightning me-1"></i> Generar
                        </button>
                    </div>
                    <div id="oembedResult" class="mt-2" style="display:none;max-width:600px;">
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control font-monospace" id="oembedShortcode" readonly style="font-size:0.8rem;background:#f8f9fa;">
                            <button class="btn btn-outline-primary" type="button" id="btnCopyOembed" title="Copiar">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Separador --}}
                <hr class="m-0">

                {{-- Graph API --}}
                <div class="p-3">
                    <h6 class="mb-1"><i class="bi bi-grid-3x3 me-1"></i> Feed completo (Graph API)</h6>
                    <?php if (!empty($connections)): ?>
                        <p class="text-muted small mb-2">Configura el feed y copia el shortcode.</p>
                        <div class="d-flex flex-wrap gap-2 align-items-end" style="max-width:600px;">
                            <div>
                                <label class="form-label small text-muted mb-0">Cuenta</label>
                                <select class="form-select form-select-sm" id="feedConnection" style="min-width:140px;">
                                    <?php foreach ($connections as $c): ?>
                                        <option value="<?= $c->id ?>">@<?= htmlspecialchars($c->username) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="form-label small text-muted mb-0">Layout</label>
                                <select class="form-select form-select-sm" id="feedLayout" style="min-width:100px;">
                                    <option value="grid">Grid</option>
                                    <option value="masonry">Masonry</option>
                                    <option value="carousel">Carousel</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label small text-muted mb-0">Cols</label>
                                <input type="number" class="form-control form-control-sm" id="feedColumns" value="3" min="1" max="6" style="width:60px;">
                            </div>
                            <div>
                                <label class="form-label small text-muted mb-0">Posts</label>
                                <input type="number" class="form-control form-control-sm" id="feedLimit" value="12" min="1" max="50" style="width:65px;">
                            </div>
                        </div>
                        <div class="mt-2" style="max-width:600px;">
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control font-monospace" id="feedShortcode" readonly style="font-size:0.8rem;background:#f8f9fa;">
                                <button class="btn btn-outline-primary" type="button" id="btnCopyFeed" title="Copiar">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-muted small mb-0">Conecta una cuenta de Instagram para generar shortcodes de feed.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
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

    // ========== SHORTCODE GENERATOR ==========
    // oEmbed generator
    const oembedInput = document.getElementById('oembedUrlInput');
    const oembedResult = document.getElementById('oembedResult');
    const oembedShortcode = document.getElementById('oembedShortcode');

    document.getElementById('btnGenerateOembed')?.addEventListener('click', function() {
        const url = oembedInput.value.trim();
        if (!url || !url.match(/instagram\.com\/(p|reel|tv)\//i)) {
            Swal.fire({ icon: 'warning', title: 'URL no válida', text: 'Introduce una URL de post de Instagram (instagram.com/p/... o instagram.com/reel/...)', timer: 3000, showConfirmButton: false });
            return;
        }
        oembedShortcode.value = `[instagram-post url="${url}"]`;
        oembedResult.style.display = '';
    });

    // Also generate on Enter key
    oembedInput?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); document.getElementById('btnGenerateOembed').click(); }
    });

    // Copy oEmbed shortcode
    document.getElementById('btnCopyOembed')?.addEventListener('click', function() {
        navigator.clipboard.writeText(oembedShortcode.value).then(() => {
            this.innerHTML = '<i class="bi bi-check-lg text-success"></i>';
            setTimeout(() => { this.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 2000);
        });
    });

    // Feed shortcode generator
    function updateFeedShortcode() {
        const conn = document.getElementById('feedConnection')?.value;
        if (!conn) return;
        const layout = document.getElementById('feedLayout')?.value || 'grid';
        const cols = document.getElementById('feedColumns')?.value || 3;
        const limit = document.getElementById('feedLimit')?.value || 12;
        const sc = document.getElementById('feedShortcode');
        if (sc) sc.value = `[instagram connection=${conn} layout="${layout}" columns=${cols} limit=${limit}]`;
    }

    ['feedConnection', 'feedLayout', 'feedColumns', 'feedLimit'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', updateFeedShortcode);
        document.getElementById(id)?.addEventListener('input', updateFeedShortcode);
    });
    updateFeedShortcode(); // Initial generation

    // Copy feed shortcode
    document.getElementById('btnCopyFeed')?.addEventListener('click', function() {
        const sc = document.getElementById('feedShortcode');
        if (sc) {
            navigator.clipboard.writeText(sc.value).then(() => {
                this.innerHTML = '<i class="bi bi-check-lg text-success"></i>';
                setTimeout(() => { this.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 2000);
            });
        }
    });
</script>
@endpush
