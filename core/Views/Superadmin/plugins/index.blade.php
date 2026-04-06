@extends('layouts.app')

@section('title', 'Plugins del Sistema')

@push('styles')
<style>
.plugins-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}
.plugins-stats {
    display: flex;
    gap: 1rem;
}
.stat-badge {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.85rem;
    padding: 0.4rem 0.75rem;
    border-radius: 6px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
}
.stat-badge i { font-size: 0.9rem; }
.plugin-list { display: flex; flex-direction: column; gap: 0; }
.plugin-item {
    display: flex;
    align-items: center;
    padding: 1rem 1.25rem;
    background: #fff;
    border: 1px solid #e9ecef;
    border-bottom: none;
    transition: all 0.15s ease;
}
.plugin-item:first-child { border-radius: 0.5rem 0.5rem 0 0; }
.plugin-item:last-child { border-bottom: 1px solid #e9ecef; border-radius: 0 0 0.5rem 0.5rem; }
.plugin-item:only-child { border-radius: 0.5rem; border-bottom: 1px solid #e9ecef; }
.plugin-item:hover { background: #f8f9fa; }
.plugin-item.enabled { border-left: 3px solid #198754; }
.plugin-item.disabled { border-left: 3px solid #dee2e6; opacity: 0.85; }
.plugin-item.new { border-left: 3px solid #0dcaf0; }
.plugin-icon {
    width: 48px; height: 48px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; flex-shrink: 0; margin-right: 1rem;
}
.plugin-icon.enabled { background: linear-gradient(135deg, #198754 0%, #20c997 100%); color: white; }
.plugin-icon.disabled { background: #e9ecef; color: #6c757d; }
.plugin-icon.new { background: linear-gradient(135deg, #0dcaf0, #6edff6); color: white; }
.plugin-info { flex-grow: 1; min-width: 0; }
.plugin-name { font-weight: 600; font-size: 1rem; color: #212529; margin-bottom: 0.15rem; }
.plugin-description { font-size: 0.85rem; color: #6c757d; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.plugin-meta { display: flex; align-items: center; gap: 1rem; flex-shrink: 0; }
.plugin-version {
    font-size: 0.75rem; color: #6c757d; background: #f1f3f4;
    padding: 0.2rem 0.5rem; border-radius: 4px; font-family: monospace;
    min-width: 52px; text-align: center;
}
.plugin-status {
    display: flex; align-items: center; gap: 0.35rem;
    font-size: 0.8rem; font-weight: 500; padding: 0.35rem 0.75rem;
    border-radius: 20px; min-width: 90px; justify-content: center;
}
.plugin-status.active { background: rgba(25, 135, 84, 0.1); color: #198754; }
.plugin-status.inactive { background: rgba(108, 117, 125, 0.1); color: #6c757d; }
.plugin-actions { display: flex; align-items: center; flex-shrink: 0; }
/* Toggle switch */
.toggle-switch {
    position: relative; width: 50px; height: 28px; cursor: pointer; flex-shrink: 0;
}
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-track {
    position: absolute; inset: 0; background: #dee2e6;
    border-radius: 999px; transition: background 0.25s ease;
}
.toggle-track::after {
    content: ''; position: absolute; top: 3px; left: 3px;
    width: 22px; height: 22px; background: #fff; border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0,0,0,0.15); transition: transform 0.25s ease;
}
.toggle-switch.active .toggle-track { background: #198754; }
.toggle-switch.active .toggle-track::after { transform: translateX(22px); }
.toggle-switch.dash-toggle .toggle-track { background: #dee2e6; }
.toggle-switch.dash-toggle.active .toggle-track { background: #0d6efd; }
.toggle-label { font-size: 0.7rem; color: #6c757d; text-align: center; margin-top: 2px; white-space: nowrap; }
@media (max-width: 768px) {
    .plugins-header { flex-direction: column; gap: 1rem; align-items: flex-start; }
    .plugin-item { flex-wrap: wrap; gap: 0.75rem; }
    .plugin-meta { width: 100%; }
}
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="plugins-header">
        <div class="d-flex align-items-center gap-3">
            <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#6f42c1,#a370db);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-plug" style="font-size:1.35rem;color:#fff;"></i>
            </div>
            <div>
                <h3 class="mb-0" style="font-size:1.25rem;font-weight:700;">Plugins del Sistema</h3>
                <p class="text-muted mb-0" style="font-size:0.85rem;">Gestiona plugins exclusivos del panel de administración</p>
            </div>
        </div>
        <div class="plugins-stats">
            <div class="stat-badge" style="background:rgba(25,135,84,0.1);border-color:rgba(25,135,84,0.2);color:#198754;">
                <i class="bi bi-check-circle-fill"></i>
                <span><?= $stats['active'] ?> activos</span>
            </div>
            <div class="stat-badge">
                <i class="bi bi-plug"></i>
                <span><?= $stats['total'] ?> total</span>
            </div>
            <a href="/musedock/plugin-store" class="stat-badge" style="text-decoration:none;color:#6366f1;border-color:rgba(99,102,241,0.2);background:rgba(99,102,241,0.08);">
                <i class="bi bi-shop"></i>
                <span>Plugin Store</span>
            </a>
            <button type="button" class="stat-badge" id="btnUploadPlugin" style="cursor:pointer;color:#0d6efd;border-color:rgba(13,110,253,0.2);background:rgba(13,110,253,0.08);">
                <i class="bi bi-upload"></i>
                <span>Subir ZIP</span>
            </button>
        </div>
    </div>

    <?php
        // Map de URLs de admin para cada plugin
        $pluginUrlMap = [
            'caddy-domain-manager' => '/musedock/domain-manager',
            'cross-publisher' => '/musedock/cross-publisher',
            'news-aggregator' => '/musedock/news-aggregator',
            'ai-skin-generator' => '/musedock/ai-skin-generator',
            'theme-extractor' => '/musedock/theme-extractor',
        ];
        $pluginIconMap = [
            'caddy-domain-manager' => 'bi-globe',
            'cross-publisher' => 'bi-share',
            'news-aggregator' => 'bi-newspaper',
            'ai-skin-generator' => 'bi-palette',
            'theme-extractor' => 'bi-brush',
        ];
    ?>

    <!-- Plugins Instalados -->
    <?php if (!empty($installedPlugins)): ?>
        <div class="plugin-list">
            <?php foreach ($installedPlugins as $plugin): ?>
                <?php
                    $isActive = (bool) $plugin->is_active;
                    $showDash = (bool) ($plugin->show_in_dashboard ?? true);
                    $icon = $pluginIconMap[$plugin->slug] ?? 'bi-plug';
                    $settingsUrl = $pluginUrlMap[$plugin->slug] ?? null;
                ?>
                <div class="plugin-item <?= $isActive ? 'enabled' : 'disabled' ?>">
                    <div class="plugin-icon <?= $isActive ? 'enabled' : 'disabled' ?>">
                        <i class="bi <?= $icon ?>"></i>
                    </div>

                    <div class="plugin-info">
                        <div class="plugin-name"><?= htmlspecialchars($plugin->name) ?></div>
                        <p class="plugin-description"><?= htmlspecialchars($plugin->description ?? 'Sin descripción') ?></p>
                    </div>

                    <div class="plugin-meta">
                        <span class="plugin-version">v<?= htmlspecialchars($plugin->version) ?></span>
                        <div class="plugin-status <?= $isActive ? 'active' : 'inactive' ?>">
                            <i class="bi <?= $isActive ? 'bi-check-circle-fill' : 'bi-dash-circle' ?>"></i>
                            <?= $isActive ? 'Activo' : 'Inactivo' ?>
                        </div>
                    </div>

                    <div class="plugin-actions">
                        {{-- Settings --}}
                        <div style="width:36px;text-align:center;margin-right:12px;">
                            <?php if ($settingsUrl && $isActive): ?>
                            <a href="<?= $settingsUrl ?>" title="Configuración"
                               style="color:#6c757d;font-size:1.1rem;transition:color 0.15s;"
                               onmouseover="this.style.color='#212529'" onmouseout="this.style.color='#6c757d'">
                                <i class="bi bi-gear"></i>
                            </a>
                            <?php endif; ?>
                        </div>

                        {{-- Toggle Dashboard --}}
                        <div style="width:60px;text-align:center;margin-right:12px;">
                            <form method="POST" action="/musedock/plugins/<?= $plugin->id ?>/toggle-dashboard" class="d-inline">
                                <?= csrf_field() ?>
                                <label class="toggle-switch dash-toggle <?= $showDash ? 'active' : '' ?> toggle-dash-btn"
                                       title="<?= $showDash ? 'Ocultar del dashboard' : 'Mostrar en dashboard' ?>"
                                       style="cursor:pointer;">
                                    <input type="checkbox" <?= $showDash ? 'checked' : '' ?>>
                                    <span class="toggle-track"></span>
                                </label>
                            </form>
                            <div class="toggle-label">Dashboard</div>
                        </div>

                        {{-- Toggle Activar/Desactivar --}}
                        <div style="width:60px;text-align:center;">
                            <?php if ($isActive): ?>
                                <label class="toggle-switch active toggle-plugin-btn"
                                       data-plugin-id="<?= $plugin->id ?>"
                                       data-plugin-name="<?= htmlspecialchars($plugin->name) ?>"
                                       data-action="deactivate"
                                       title="Desactivar">
                                    <input type="checkbox" checked>
                                    <span class="toggle-track"></span>
                                </label>
                            <?php else: ?>
                                <form method="POST" action="/musedock/plugins/<?= $plugin->id ?>/activate" class="d-inline">
                                    <?= csrf_field() ?>
                                    <label class="toggle-switch toggle-activate-btn" title="Activar" style="cursor:pointer;">
                                        <input type="checkbox">
                                        <span class="toggle-track"></span>
                                    </label>
                                </form>
                            <?php endif; ?>
                            <div class="toggle-label">Activo</div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <div style="width:64px;height:64px;border-radius:16px;background:linear-gradient(135deg,#e8e0f3,#d4c5ed);display:inline-flex;align-items:center;justify-content:center;margin-bottom:1rem;">
                    <i class="bi bi-plug" style="font-size:1.75rem;color:#6f42c1;"></i>
                </div>
                <h5 class="mb-2">No hay plugins instalados</h5>
                <p class="text-muted mb-3" style="max-width:400px;margin:0 auto;">Sube un plugin ZIP o visita el Plugin Store para empezar.</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Plugins Disponibles (No Instalados) -->
    <?php if (!empty($newPlugins)): ?>
        <h6 class="mt-4 mb-3 text-muted" style="font-size:0.85rem;text-transform:uppercase;letter-spacing:0.05em;">
            <i class="bi bi-box-seam me-1"></i> Disponibles para instalar
        </h6>
        <div class="plugin-list">
            <?php foreach ($newPlugins as $np): ?>
                <?php $npIcon = $pluginIconMap[$np['slug']] ?? 'bi-box-seam'; ?>
                <div class="plugin-item new">
                    <div class="plugin-icon new">
                        <i class="bi <?= $npIcon ?>"></i>
                    </div>
                    <div class="plugin-info">
                        <div class="plugin-name"><?= htmlspecialchars($np['name']) ?></div>
                        <p class="plugin-description"><?= htmlspecialchars($np['description'] ?? 'Sin descripción') ?></p>
                    </div>
                    <div class="plugin-meta">
                        <span class="plugin-version">v<?= htmlspecialchars($np['version'] ?? '1.0.0') ?></span>
                    </div>
                    <div class="plugin-actions">
                        <form method="POST" action="/musedock/plugins/install" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="slug" value="<?= htmlspecialchars($np['slug']) ?>">
                            <button type="submit" class="btn btn-sm btn-success">
                                <i class="bi bi-download me-1"></i> Instalar
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Formulario oculto para subir plugin -->
<form id="uploadPluginForm" method="POST" action="/musedock/plugins/upload" enctype="multipart/form-data" style="display: none;">
    <?= csrf_field() ?>
    <input type="file" id="pluginZipInput" name="plugin_file" accept=".zip">
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = '<?= csrf_token() ?>';

    // ========== TOGGLE DESACTIVAR (con contraseña) ==========
    document.querySelectorAll('.toggle-plugin-btn').forEach(toggle => {
        const checkbox = toggle.querySelector('input[type="checkbox"]');
        if (checkbox) checkbox.addEventListener('click', e => e.preventDefault());

        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const pluginId = this.dataset.pluginId;
            const pluginName = this.dataset.pluginName;

            Swal.fire({
                title: `¿Desactivar "${pluginName}"?`,
                html: `<div class="text-start">
                    <p class="text-muted mb-3">El plugin dejará de funcionar pero permanecerá instalado.</p>
                    <label class="form-label fw-bold">Contraseña para confirmar:</label>
                    <input type="password" id="deactivatePassword" class="form-control" placeholder="Contraseña del superadmin" autocomplete="current-password">
                </div>`,
                showCancelButton: true,
                confirmButtonText: 'Desactivar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
                focusConfirm: false,
                didOpen: () => document.getElementById('deactivatePassword').focus(),
                preConfirm: () => {
                    const pw = document.getElementById('deactivatePassword').value;
                    if (!pw) { Swal.showValidationMessage('La contraseña es requerida'); return false; }
                    return pw;
                }
            }).then(result => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'Desactivando...', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });
                    fetch(`/musedock/plugins/${pluginId}/deactivate-secure`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({ _csrf: csrfToken, password: result.value })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) { Swal.fire({ icon: 'success', title: 'Desactivado', text: data.message, timer: 2000, showConfirmButton: false }).then(() => location.reload()); }
                        else { Swal.fire({ icon: 'error', title: 'Error', text: data.message }); }
                    })
                    .catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión' }));
                }
            });
        });
    });

    // ========== TOGGLE ACTIVAR (submit form) ==========
    document.querySelectorAll('.toggle-activate-btn').forEach(toggle => {
        const checkbox = toggle.querySelector('input[type="checkbox"]');
        if (checkbox) checkbox.addEventListener('click', e => e.preventDefault());
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            this.closest('form').submit();
        });
    });

    // ========== TOGGLE DASHBOARD (submit form) ==========
    document.querySelectorAll('.toggle-dash-btn').forEach(toggle => {
        const checkbox = toggle.querySelector('input[type="checkbox"]');
        if (checkbox) checkbox.addEventListener('click', e => e.preventDefault());
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            this.closest('form').submit();
        });
    });

    // ========== SUBIR PLUGIN ZIP ==========
    const btnUpload = document.getElementById('btnUploadPlugin');
    const uploadForm = document.getElementById('uploadPluginForm');
    const fileInput = document.getElementById('pluginZipInput');

    if (btnUpload) {
        btnUpload.addEventListener('click', function() {
            Swal.fire({
                title: '<i class="bi bi-cloud-upload text-primary"></i> Subir Plugin',
                html: `<div class="text-start">
                    <p class="text-muted mb-3">Selecciona un archivo ZIP con el plugin a instalar.</p>
                    <div class="upload-zone border border-2 border-dashed rounded-3 p-4 text-center" id="dropZone" style="cursor:pointer;">
                        <i class="bi bi-file-earmark-zip display-4 text-muted"></i>
                        <p class="mb-1 mt-2"><strong>Arrastra el archivo aquí</strong></p>
                        <p class="text-muted small mb-2">o haz clic para seleccionar</p>
                        <span class="badge bg-secondary">Máximo 50MB</span>
                    </div>
                    <div id="selectedFile" class="mt-3 d-none">
                        <div class="alert alert-success py-2 mb-0">
                            <i class="bi bi-file-earmark-check me-2"></i><span id="fileName"></span>
                            <button type="button" class="btn-close btn-sm float-end" id="clearFile"></button>
                        </div>
                    </div>
                </div>`,
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-upload me-1"></i> Subir e Instalar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#0d6efd',
                width: '500px',
                didOpen: () => {
                    const dropZone = document.getElementById('dropZone');
                    const selectedFileDiv = document.getElementById('selectedFile');
                    const fileNameSpan = document.getElementById('fileName');
                    const clearFileBtn = document.getElementById('clearFile');
                    dropZone.addEventListener('click', () => fileInput.click());
                    dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('border-primary','bg-light'); });
                    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('border-primary','bg-light'));
                    dropZone.addEventListener('drop', e => {
                        e.preventDefault(); dropZone.classList.remove('border-primary','bg-light');
                        const file = e.dataTransfer.files[0];
                        if (file && file.name.endsWith('.zip')) handleFileSelect(file);
                        else Swal.showValidationMessage('Solo archivos .zip');
                    });
                    fileInput.addEventListener('change', function() { if (this.files[0]) handleFileSelect(this.files[0]); });
                    clearFileBtn.addEventListener('click', () => { fileInput.value=''; selectedFileDiv.classList.add('d-none'); dropZone.classList.remove('d-none'); });
                    function handleFileSelect(file) {
                        if (file.size > 50*1024*1024) { Swal.showValidationMessage('Máximo 50MB'); return; }
                        fileNameSpan.textContent = file.name+' ('+(file.size/1024/1024).toFixed(2)+' MB)';
                        selectedFileDiv.classList.remove('d-none'); dropZone.classList.add('d-none');
                        const dt = new DataTransfer(); dt.items.add(file); fileInput.files = dt.files;
                    }
                },
                preConfirm: () => { if (!fileInput.files[0]) { Swal.showValidationMessage('Selecciona un archivo ZIP'); return false; } return true; }
            }).then(result => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'Subiendo plugin...', html: '<div class="progress"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%"></div></div>', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });
                    uploadForm.submit();
                }
            });
        });
    }
});
</script>
@endpush
