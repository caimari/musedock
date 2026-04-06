@extends('layouts::app')

@section('title', __instagram('settings.settings') . ' - Instagram Gallery')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endpush

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
            <div class="d-flex align-items-center gap-3">
                <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi bi-gear" style="font-size:1.35rem;color:#fff;"></i>
                </div>
                <div>
                    <h3 class="mb-0" style="font-size:1.25rem;font-weight:700;"><?php echo __instagram('settings.settings'); ?></h3>
                    <p class="text-muted mb-0" style="font-size:0.85rem;">Configuración global del módulo Instagram Gallery</p>
                </div>
            </div>
            <a href="/musedock/instagram" style="display:flex;align-items:center;gap:0.35rem;font-size:0.85rem;padding:0.4rem 0.75rem;border-radius:6px;background:#f8f9fa;border:1px solid #e9ecef;color:#6c757d;text-decoration:none;">
                <i class="bi bi-arrow-left"></i>
                <span><?php echo __instagram('common.back'); ?></span>
            </a>
        </div>

        <form method="POST" action="/musedock/instagram/settings">
            <div class="row">
                <div class="col-lg-8">
                    <!-- Modo de funcionamiento -->
                    @php $currentMode = $settings['instagram_mode'] ?? 'both'; @endphp
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-toggles"></i> Modo de funcionamiento</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="instagram_mode" id="mode_both" value="both" {{ $currentMode === 'both' ? 'checked' : '' }}>
                                <label class="form-check-label" for="mode_both">
                                    <strong>Ambos (recomendado)</strong>
                                    <small class="d-block text-muted">Feed completo con Graph API + insertar posts individuales con oEmbed</small>
                                </label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="instagram_mode" id="mode_graph" value="graph" {{ $currentMode === 'graph' ? 'checked' : '' }}>
                                <label class="form-check-label" for="mode_graph">
                                    <strong>Solo Graph API</strong>
                                    <small class="d-block text-muted">Feed de Instagram con diseño personalizado. Requiere credenciales de API.</small>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="instagram_mode" id="mode_oembed" value="oembed" {{ $currentMode === 'oembed' ? 'checked' : '' }}>
                                <label class="form-check-label" for="mode_oembed">
                                    <strong>Solo oEmbed</strong>
                                    <small class="d-block text-muted">Insertar posts públicos de Instagram sin configurar API. Diseño nativo de Instagram.</small>
                                </label>
                            </div>

                            <div class="alert alert-light border mt-3 mb-0" style="font-size:0.85rem;">
                                <strong>Shortcodes disponibles:</strong>
                                <div class="mt-2">
                                    <code>[instagram connection=1 layout="grid" columns=3 limit=12]</code>
                                    <small class="text-muted ms-2">— Feed con Graph API</small>
                                </div>
                                <div class="mt-1">
                                    <code>[instagram-post url="https://instagram.com/p/ABC123"]</code>
                                    <small class="text-muted ms-2">— Post individual con oEmbed</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- API Credentials -->
                    @php $hasCredentials = !empty($settings['instagram_app_id'] ?? ''); @endphp
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-key"></i> <?php echo __instagram('settings.api_credentials'); ?></h5>
                            @if($hasCredentials)
                            <button type="button" class="btn btn-sm btn-outline-warning" id="btnUnlockApi">
                                <i class="bi bi-unlock me-1"></i> Desbloquear para editar
                            </button>
                            @endif
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
                                <input type="text" class="form-control api-credential" name="instagram_app_id"
                                       value="<?php echo htmlspecialchars($settings['instagram_app_id'] ?? ''); ?>"
                                       {{ $hasCredentials ? 'disabled' : '' }}>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><?php echo __instagram('settings.instagram_app_secret'); ?></label>
                                <input type="password" class="form-control api-credential" name="instagram_app_secret"
                                       value="<?php echo htmlspecialchars($settings['instagram_app_secret'] ?? ''); ?>"
                                       {{ $hasCredentials ? 'disabled' : '' }}>
                            </div>

                            @php
                                $defaultRedirectUri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'musedock.com') . '/musedock/instagram/callback';
                                $redirectUriValue = !empty($settings['instagram_redirect_uri']) ? $settings['instagram_redirect_uri'] : $defaultRedirectUri;
                            @endphp
                            <div class="mb-3">
                                <label class="form-label"><?php echo __instagram('settings.instagram_redirect_uri'); ?></label>
                                <div class="input-group">
                                    <input type="url" class="form-control api-credential" name="instagram_redirect_uri" id="redirectUriInput"
                                           value="{{ htmlspecialchars($redirectUriValue) }}"
                                           {{ $hasCredentials ? 'disabled' : '' }}>
                                    <button type="button" class="btn btn-outline-secondary" id="btnCopyUri" title="Copiar URI">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                                <small class="form-text text-muted">Copia esta URL e insértala en "Valid OAuth Redirect URIs" en Facebook Developers</small>
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
</div>
@endsection

@push('scripts')
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

    // Copiar Redirect URI
    const copyBtn = document.getElementById('btnCopyUri');
    if (copyBtn) {
        copyBtn.addEventListener('click', function() {
            const input = document.getElementById('redirectUriInput');
            const val = input.value;
            navigator.clipboard.writeText(val).then(() => {
                this.innerHTML = '<i class="bi bi-check-lg text-success"></i>';
                setTimeout(() => { this.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 2000);
            });
        });
    }

    // Desbloquear campos de API
    const unlockBtn = document.getElementById('btnUnlockApi');
    if (unlockBtn) {
        unlockBtn.addEventListener('click', function() {
            document.querySelectorAll('.api-credential').forEach(input => {
                input.disabled = false;
            });
            this.innerHTML = '<i class="bi bi-lock-fill me-1"></i> Desbloqueado';
            this.classList.remove('btn-outline-warning');
            this.classList.add('btn-outline-success');
            this.disabled = true;
        });
    }
</script>
@endpush
