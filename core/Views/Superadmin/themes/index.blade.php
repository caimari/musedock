@extends('layouts.app')

@section('content')
@include('partials.alerts-sweetalert2')

@push('styles')
<style>
.themes-container { max-width: 1200px; margin: 0 auto; }
.themes-header h2 { font-weight: 600; color: #1a1a2e; margin-bottom: 0.25rem; }
.themes-header p { color: #6c757d; margin-bottom: 0; }

/* Active theme banner */
.active-theme-card {
    background: #fff; border: 1px solid #e5e7eb; border-left: 4px solid #10b981;
    border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 2rem;
}
.active-theme-card .icon-circle {
    width: 44px; height: 44px; background: rgba(16,185,129,0.1); border-radius: 50%;
    display: flex; align-items: center; justify-content: center; font-size: 1.25rem; color: #10b981;
}
.active-theme-card .theme-label { font-size: 0.8rem; color: #64748b; }
.active-theme-card .theme-name { font-size: 1.1rem; font-weight: 600; margin: 0; color: #1e293b; }
.active-theme-card .badge { background: rgba(16,185,129,0.1); color: #059669; font-weight: 500; }

/* Section header */
.section-header {
    display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;
    padding-bottom: 1rem; border-bottom: 1px solid #e9ecef;
}
.section-header .icon-box {
    width: 44px; height: 44px; border-radius: 12px; display: flex;
    align-items: center; justify-content: center; font-size: 1.25rem; color: white;
}
.section-header .icon-box.official { background: linear-gradient(135deg, #10b981, #059669); }
.section-header h4 { font-weight: 600; margin: 0; color: #1a1a2e; }
.section-header small { color: #6c757d; }

/* Theme grid */
.theme-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;
}
.theme-card {
    background: white; border-radius: 16px; border: 1px solid #e5e7eb;
    overflow: hidden; transition: all 0.2s ease; position: relative;
}
.theme-card:hover { border-color: #c7d2fe; box-shadow: 0 8px 25px rgba(99,102,241,0.1); transform: translateY(-2px); }
.theme-card.is-active { border-color: #93c5fd; box-shadow: 0 0 0 1px rgba(59,130,246,0.15); }
.theme-card .active-badge {
    position: absolute; top: 12px; right: -32px;
    background: linear-gradient(135deg, #10b981, #059669); color: white;
    font-size: 0.65rem; font-weight: 700; padding: 4px 40px; transform: rotate(45deg);
    letter-spacing: 0.5px; z-index: 5;
}
.theme-preview {
    height: 160px; background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    position: relative; overflow: hidden;
}
.theme-preview img { width: 100%; height: 100%; object-fit: cover; }
.theme-preview .placeholder-icon { font-size: 3rem; color: #94a3b8; margin-bottom: 0.5rem; }
.theme-preview .placeholder-text { font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; }
.theme-info { padding: 1.25rem; }
.theme-info .theme-title { font-size: 1rem; font-weight: 600; color: #1e293b; margin-bottom: 0.25rem; }
.theme-info .theme-desc { font-size: 0.85rem; color: #64748b; margin-bottom: 0.75rem; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.theme-info .theme-meta { display: flex; justify-content: space-between; font-size: 0.75rem; color: #94a3b8; }
.theme-actions { padding: 0 1.25rem 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap; }
.theme-actions .btn { font-size: 0.8rem; padding: 0.4rem 0.75rem; border-radius: 8px; }
.theme-actions .btn-active { background: #10b981; border: none; color: white; }
.theme-actions .btn-activate { background: white; border: 1px solid #6366f1; color: #6366f1; }
.theme-actions .btn-activate:hover { background: #6366f1; color: white; }
.theme-actions .btn-icon { padding: 0.4rem 0.55rem; background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b; }
.theme-actions .btn-icon:hover { background: #e2e8f0; color: #475569; }
.badge-tenant-yes { background: rgba(16,185,129,0.1); color: #059669; font-size: 0.65rem; padding: 0.2rem 0.5rem; border-radius: 4px; }
.badge-tenant-no { background: rgba(239,68,68,0.1); color: #ef4444; font-size: 0.65rem; padding: 0.2rem 0.5rem; border-radius: 4px; }

/* Skins collapsible inside theme card */
.theme-skins-toggle {
    display: flex; align-items: center; gap: 6px; padding: 8px 1.25rem;
    background: #f8fafc; border-top: 1px solid #f0f0f0; cursor: pointer;
    font-size: 0.78rem; color: #7c3aed; font-weight: 500; transition: background 0.15s;
    text-decoration: none;
}
.theme-skins-toggle:hover { background: #f0f0ff; color: #6d28d9; text-decoration: none; }
.theme-skins-toggle .bi-chevron-down { transition: transform 0.2s; }
.theme-skins-toggle.collapsed .bi-chevron-down { transform: rotate(-90deg); }
.theme-skins-panel { padding: 1rem 1.25rem; background: #faf9ff; border-top: 1px solid #f0f0f0; }
.skin-mini-card {
    display: flex; align-items: center; gap: 10px; padding: 8px 10px;
    border-radius: 8px; border: 1px solid #e5e7eb; background: #fff;
    margin-bottom: 6px; transition: all 0.15s;
}
.skin-mini-card:hover { border-color: #c4b5fd; }
.skin-mini-card.skin-active { border-color: #22c55e; background: #f0fdf4; }
.skin-color-dots { display: flex; gap: 3px; }
.skin-color-dots span { width: 12px; height: 12px; border-radius: 50%; border: 1px solid rgba(0,0,0,0.08); }
.skin-mini-name { font-size: 0.8rem; font-weight: 500; color: #1e293b; flex: 1; }
.skin-mini-badge { font-size: 0.6rem; padding: 2px 6px; border-radius: 4px; }
.skin-mini-actions { display: flex; gap: 4px; opacity: 0; transition: opacity 0.15s; }
.skin-mini-card:hover .skin-mini-actions { opacity: 1; }
.skin-mini-card.skin-disabled { opacity: 0.5; border-style: dashed; }

@media (max-width: 576px) {
    .theme-grid { grid-template-columns: 1fr; }
}
</style>
@endpush

<div class="themes-container">
    {{-- Header --}}
    <div class="themes-header d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h2><i class="bi bi-palette2 me-2"></i>Gestión de Temas</h2>
            <p>Administra los temas del CMS y su disponibilidad para tenants</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="/musedock/theme-extractor" class="btn btn-outline-info btn-sm text-decoration-none">
                <i class="bi bi-cloud-download me-1"></i> Theme Extractor
            </a>
            <button class="btn btn-outline-secondary btn-sm" id="btnUploadTheme">
                <i class="bi bi-upload me-1"></i> Subir tema
            </button>
            <a href="{{ route('themes.create') }}" class="btn btn-primary btn-sm text-decoration-none">
                <i class="bi bi-plus-lg me-1"></i> Crear tema
            </a>
        </div>
    </div>

    {{-- Active theme banner --}}
    <div class="active-theme-card">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="icon-circle"><i class="bi bi-check-circle-fill"></i></div>
                <div>
                    <div class="theme-label">Tema activo del CMS principal</div>
                    <h5 class="theme-name">{{ ucfirst($currentTheme) }} <span class="badge ms-2">Activo</span></h5>
                </div>
            </div>
            @php
                $__activeConfigPath = APP_ROOT . "/themes/{$currentTheme}/theme.json";
                $__activeCustomizable = file_exists($__activeConfigPath) && !empty(json_decode(file_get_contents($__activeConfigPath), true)['customizable']);
            @endphp
            @if($__activeCustomizable)
            <a href="{{ route('themes.appearance.global', ['slug' => $currentTheme]) }}" class="btn btn-light btn-sm text-decoration-none">
                <i class="bi bi-brush me-1"></i> Personalizar
            </a>
            @endif
        </div>
    </div>

    {{-- Themes Grid --}}
    <div class="themes-section mb-5">
        <div class="section-header">
            <div class="icon-box official"><i class="bi bi-layers"></i></div>
            <div class="flex-grow-1">
                <h4>Temas instalados</h4>
                <small>{{ count($themes) }} tema(s) en el sistema</small>
            </div>
        </div>

        <div class="theme-grid">
            @foreach($themes as $theme)
                @php
                    $isActive = $theme['slug'] === $currentTheme;
                    $configPath = APP_ROOT . "/themes/{$theme['slug']}/theme.json";
                    $themeConfig = file_exists($configPath) ? json_decode(file_get_contents($configPath), true) : [];
                    $isCustomizable = !empty($themeConfig['customizable']);
                    $screenshotFile = !empty($themeConfig['screenshot']) ? APP_ROOT . "/themes/{$theme['slug']}/{$themeConfig['screenshot']}" : null;
                    $screenshotPath = ($screenshotFile && file_exists($screenshotFile)) ? "/themes/{$theme['slug']}/{$themeConfig['screenshot']}" : null;
                    $isAvailable = !empty($theme['available_for_tenants']);
                    $homeViewPath = APP_ROOT . "/themes/{$theme['slug']}/views/home.blade.php";
                    $description = $themeConfig['description'] ?? '';
                    $author = $themeConfig['author'] ?? 'MuseDock';
                    $version = $themeConfig['version'] ?? '1.0';

                    // Load skins for this theme
                    $__pdo = \Screenart\Musedock\Database::connect();
                    $__skStmt = $__pdo->prepare("SELECT slug, name, options, screenshot, description, author, is_active FROM theme_skins WHERE theme_slug = ? AND tenant_id IS NULL ORDER BY name");
                    $__skStmt->execute([$theme['slug']]);
                    $__themeSkins = $__skStmt->fetchAll(\PDO::FETCH_ASSOC);

                    // Get active skin
                    $__activeSkin = '';
                    $__asStmt = $__pdo->prepare("SELECT value FROM settings WHERE key = 'active_skin_slug' LIMIT 1");
                    $__asStmt->execute();
                    $__asRow = $__asStmt->fetch(\PDO::FETCH_ASSOC);
                    if ($__asRow) $__activeSkin = $__asRow['value'];
                @endphp
                <div class="theme-card {{ $isActive ? 'is-active' : '' }}">
                    @if($isActive)
                        <div class="active-badge">ACTIVO</div>
                    @endif

                    <div class="theme-preview">
                        @if($screenshotPath)
                            <img src="{{ $screenshotPath }}" alt="{{ $theme['name'] }}" loading="lazy">
                        @else
                            <i class="bi bi-palette placeholder-icon"></i>
                            <span class="placeholder-text">{{ $theme['slug'] }}</span>
                        @endif
                    </div>

                    <div class="theme-info">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <span class="theme-title">{{ ucfirst($theme['name']) }}</span>
                            @if($isAvailable)
                                <span class="badge-tenant-yes"><i class="bi bi-people-fill me-1"></i>Tenants</span>
                            @else
                                <span class="badge-tenant-no"><i class="bi bi-lock me-1"></i>Solo CMS</span>
                            @endif
                        </div>
                        @if($description)
                            <p class="theme-desc">{{ $description }}</p>
                        @endif
                        <div class="theme-meta">
                            <span><i class="bi bi-person me-1"></i>{{ $author }}</span>
                            <span><i class="bi bi-tag me-1"></i>v{{ $version }}</span>
                        </div>
                    </div>

                    <div class="theme-actions">
                        @if($isActive)
                            <button class="btn btn-active flex-grow-1" disabled>
                                <i class="bi bi-check2 me-1"></i>Activo
                            </button>
                        @else
                            <button type="button" class="btn btn-activate flex-grow-1 btn-activate-theme"
                                    data-theme-slug="{{ $theme['slug'] }}"
                                    data-theme-name="{{ ucfirst($theme['name']) }}"
                                    data-activate-url="{{ route('themes.activate') }}">
                                <i class="bi bi-power me-1"></i>Activar
                            </button>
                        @endif

                        @if($isCustomizable)
                            <a href="{{ route('themes.appearance.global', ['slug' => $theme['slug']]) }}" class="btn btn-icon text-decoration-none" title="Personalizar">
                                <i class="bi bi-brush"></i>
                            </a>
                        @endif

                        @if(file_exists($homeViewPath))
                            <a href="{{ route('themes.editor.customize', ['slug' => $theme['slug']]) }}" class="btn btn-icon text-decoration-none" title="Editor">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                        @endif

                        <form method="POST" action="{{ route('themes.toggle-tenant') }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="theme" value="{{ $theme['slug'] }}">
                            <button type="submit" class="btn btn-icon" title="{{ $isAvailable ? 'Deshabilitar para tenants' : 'Habilitar para tenants' }}">
                                <i class="bi {{ $isAvailable ? 'bi-people-fill text-success' : 'bi-people' }}"></i>
                            </button>
                        </form>

                        <a href="{{ route('themes.download', ['slug' => $theme['slug']]) }}" class="btn btn-icon text-decoration-none" title="Descargar">
                            <i class="bi bi-download"></i>
                        </a>

                        @if(!$isActive)
                            <button type="button" class="btn btn-icon btn-delete-theme"
                                    data-theme-slug="{{ $theme['slug'] }}"
                                    data-theme-name="{{ ucfirst($theme['name']) }}"
                                    data-delete-url="{{ route('themes.destroy', ['slug' => $theme['slug']]) }}"
                                    title="Eliminar" style="color: #ef4444;">
                                <i class="bi bi-trash3"></i>
                            </button>
                        @endif
                    </div>

                    {{-- Skins collapsible --}}
                    @if(!empty($__themeSkins))
                    <a class="theme-skins-toggle collapsed" data-bs-toggle="collapse" href="#skins-{{ $theme['slug'] }}" role="button">
                        <i class="bi bi-stars"></i>
                        {{ count($__themeSkins) }} skin(s)
                        <i class="bi bi-chevron-down ms-auto"></i>
                    </a>
                    <div class="collapse" id="skins-{{ $theme['slug'] }}">
                        <div class="theme-skins-panel">
                            @foreach($__themeSkins as $skin)
                            @php
                                $skinOpts = is_string($skin['options']) ? json_decode($skin['options'], true) : ($skin['options'] ?? []);
                                $skinColors = [
                                    $skinOpts['header']['header_bg_color'] ?? '#f8f9fa',
                                    $skinOpts['topbar']['topbar_bg_color'] ?? '#1a2a40',
                                    $skinOpts['header']['header_cta_bg_color'] ?? '#ff5e15',
                                    $skinOpts['footer']['footer_bg_color'] ?? '#1a1a2e',
                                ];
                            @endphp
                            <div class="skin-mini-card {{ $__activeSkin === $skin['slug'] ? 'skin-active' : '' }} {{ !($skin['is_active'] ?? true) ? 'skin-disabled' : '' }}" id="skin-card-{{ $skin['slug'] }}">
                                <div class="skin-color-dots">
                                    @foreach($skinColors as $c)
                                        <span style="background:{{ $c }};"></span>
                                    @endforeach
                                </div>
                                <span class="skin-mini-name">{{ $skin['name'] }}</span>
                                @if($__activeSkin === $skin['slug'])
                                    <span class="skin-mini-badge" style="background:#dcfce7;color:#16a34a;">En uso</span>
                                @endif
                                @if(!($skin['is_active'] ?? true))
                                    <span class="skin-mini-badge" style="background:#fee2e2;color:#dc2626;">Oculto</span>
                                @endif
                                <div class="skin-mini-actions ms-1">
                                    <button type="button" class="btn btn-sm p-0 border-0" style="font-size:0.7rem;color:{{ ($skin['is_active'] ?? true) ? '#f59e0b' : '#22c55e' }};" onclick="toggleSkin('{{ $skin['slug'] }}')" title="{{ ($skin['is_active'] ?? true) ? 'Ocultar para tenants' : 'Activar para tenants' }}">
                                        <i class="bi {{ ($skin['is_active'] ?? true) ? 'bi-eye-slash' : 'bi-eye' }}"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm p-0 border-0" style="font-size:0.7rem;color:#ef4444;" onclick="deleteSkin('{{ $skin['slug'] }}', '{{ addslashes($skin['name']) }}')" title="Eliminar skin">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Layouts collapsible --}}
                    @if($isCustomizable && !empty($themeConfig['customizable_options']['header']['options']['header_layout']['options']))
                    @php
                        $__allLayouts = $themeConfig['customizable_options']['header']['options']['header_layout']['options'] ?? [];
                        // Load global layout defaults from settings
                        $__pdo = $__pdo ?? \Screenart\Musedock\Database::connect();
                        $__dlStmt = $__pdo->prepare("SELECT layout_value, is_allowed FROM tenant_layout_restrictions WHERE tenant_id IS NULL AND layout_type = 'header_layout'");
                        $__dlStmt->execute();
                        $__defaultRestrictions = [];
                        foreach ($__dlStmt->fetchAll(\PDO::FETCH_ASSOC) as $__dr) {
                            $__defaultRestrictions[$__dr['layout_value']] = (bool)$__dr['is_allowed'];
                        }
                        $__hasDefaults = !empty($__defaultRestrictions);
                    @endphp
                    <a class="theme-skins-toggle collapsed" data-bs-toggle="collapse" href="#layouts-{{ $theme['slug'] }}" role="button" style="border-top: 1px solid #f0f0f0;">
                        <i class="bi bi-layout-split"></i>
                        {{ count($__allLayouts) }} layout(s)
                        <i class="bi bi-chevron-down ms-auto"></i>
                    </a>
                    <div class="collapse" id="layouts-{{ $theme['slug'] }}">
                        <div class="theme-skins-panel">
                            <p class="text-muted small mb-2">Layouts disponibles por defecto para todos los tenants:</p>
                            @foreach($__allLayouts as $__lKey => $__lLabel)
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input layout-default-toggle" type="checkbox"
                                           data-theme="{{ $theme['slug'] }}" data-layout="{{ $__lKey }}"
                                           id="dl-{{ $theme['slug'] }}-{{ $__lKey }}"
                                           {{ !$__hasDefaults || ($__defaultRestrictions[$__lKey] ?? true) ? 'checked' : '' }}>
                                </div>
                                <label for="dl-{{ $theme['slug'] }}-{{ $__lKey }}" class="small mb-0" style="cursor:pointer;">{{ $__lLabel }}</label>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Hidden forms --}}
<form id="uploadThemeForm" method="POST" action="{{ route('themes.upload') }}" enctype="multipart/form-data" style="display:none;">
    @csrf
    <input type="file" id="themeZipInput" name="theme_zip" accept=".zip">
</form>
<form id="deleteThemeForm" method="POST" action="" style="display:none;">
    @csrf
    @method('DELETE')
    <input type="hidden" name="password" id="deleteThemePassword">
</form>
<form id="activateThemeForm" method="POST" action="" style="display:none;">
    @csrf
    <input type="hidden" name="theme" id="activateThemeSlug">
</form>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Toggle collapsed class for chevron rotation
    document.querySelectorAll('.theme-skins-toggle').forEach(function(el) {
        el.addEventListener('click', function() {
            this.classList.toggle('collapsed');
        });
    });

    // Layout default toggles
    document.querySelectorAll('.layout-default-toggle').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            const layout = this.dataset.layout;
            const isAllowed = this.checked ? 1 : 0;
            fetch('/musedock/themes/toggle-layout-default', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: '_csrf={{ csrf_token() }}&layout=' + encodeURIComponent(layout) + '&is_allowed=' + isAllowed
            }).then(r => r.json()).then(d => {
                if (!d.success) { this.checked = !this.checked; }
            }).catch(() => { this.checked = !this.checked; });
        });
    });

    // Upload theme
    const btnUpload = document.getElementById('btnUploadTheme');
    const uploadForm = document.getElementById('uploadThemeForm');
    const fileInput = document.getElementById('themeZipInput');
    if (btnUpload) {
        btnUpload.addEventListener('click', function() {
            Swal.fire({
                title: '<i class="bi bi-cloud-upload text-primary"></i> Subir Tema',
                html: '<div class="text-start"><p class="text-muted mb-3">Selecciona un archivo ZIP con el tema.</p><div class="upload-zone border border-2 border-dashed rounded-3 p-4 text-center" id="dropZone" style="cursor:pointer;"><i class="bi bi-file-earmark-zip display-4 text-muted"></i><p class="mb-1 mt-2"><strong>Arrastra aquí o haz clic</strong></p><span class="badge bg-secondary">Máx 50MB</span></div><div id="selectedFile" class="mt-3 d-none"><div class="alert alert-success py-2 mb-0"><i class="bi bi-file-earmark-check me-2"></i><span id="fileName"></span><button type="button" class="btn-close btn-sm float-end" id="clearFile"></button></div></div></div>',
                showCancelButton: true, confirmButtonText: '<i class="bi bi-upload me-1"></i> Subir', cancelButtonText: 'Cancelar',
                confirmButtonColor: '#0d6efd', width: '480px',
                didOpen: () => {
                    const dz = document.getElementById('dropZone'), sf = document.getElementById('selectedFile'), fn = document.getElementById('fileName');
                    dz.addEventListener('click', () => fileInput.click());
                    dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('border-primary','bg-light'); });
                    dz.addEventListener('dragleave', () => dz.classList.remove('border-primary','bg-light'));
                    dz.addEventListener('drop', e => { e.preventDefault(); dz.classList.remove('border-primary','bg-light'); if(e.dataTransfer.files[0]?.name.endsWith('.zip')) sel(e.dataTransfer.files[0]); });
                    fileInput.addEventListener('change', function(){ if(this.files[0]) sel(this.files[0]); });
                    document.getElementById('clearFile').addEventListener('click', () => { fileInput.value=''; sf.classList.add('d-none'); dz.classList.remove('d-none'); });
                    function sel(f){ if(f.size>50*1024*1024){Swal.showValidationMessage('Máx 50MB');return;} fn.textContent=f.name+' ('+(f.size/1024/1024).toFixed(2)+' MB)'; sf.classList.remove('d-none'); dz.classList.add('d-none'); const dt=new DataTransfer(); dt.items.add(f); fileInput.files=dt.files; }
                },
                preConfirm: () => { if(!fileInput.files[0]){Swal.showValidationMessage('Selecciona un ZIP');return false;} return true; }
            }).then(r => { if(r.isConfirmed){ Swal.fire({title:'Subiendo...',allowOutsideClick:false,showConfirmButton:false,didOpen:()=>Swal.showLoading()}); uploadForm.submit(); }});
        });
    }

    // Delete theme
    document.querySelectorAll('.btn-delete-theme').forEach(btn => {
        btn.addEventListener('click', function() {
            const slug = this.dataset.themeSlug, name = this.dataset.themeName, url = this.dataset.deleteUrl;
            Swal.fire({
                title: '<i class="bi bi-exclamation-triangle-fill text-danger"></i>',
                html: '<h5 class="text-danger mb-2">Eliminar tema</h5><p>¿Eliminar <strong>'+name+'</strong> permanentemente?</p><div class="text-start mt-3"><label class="form-label fw-semibold"><i class="bi bi-shield-lock me-1"></i>Contraseña</label><input type="password" id="swal-password" class="form-control" placeholder="Tu contraseña"></div>',
                showCancelButton: true, confirmButtonColor: '#dc3545', confirmButtonText: '<i class="bi bi-trash me-1"></i> Eliminar', cancelButtonText: 'Cancelar',
                focusConfirm: false,
                didOpen: () => { const p=document.getElementById('swal-password'); p.focus(); p.addEventListener('keypress',e=>{if(e.key==='Enter')Swal.clickConfirm();}); },
                preConfirm: () => { const p=document.getElementById('swal-password').value; if(!p){Swal.showValidationMessage('Contraseña requerida');return false;} return p; }
            }).then(r => { if(r.isConfirmed){ Swal.fire({title:'Eliminando...',allowOutsideClick:false,showConfirmButton:false,didOpen:()=>Swal.showLoading()}); const f=document.getElementById('deleteThemeForm'); f.action=url; document.getElementById('deleteThemePassword').value=r.value; f.submit(); }});
        });
    });

    // Activate theme
    document.querySelectorAll('.btn-activate-theme').forEach(btn => {
        btn.addEventListener('click', function() {
            const slug = this.dataset.themeSlug, name = this.dataset.themeName, url = this.dataset.activateUrl;
            Swal.fire({
                title: '<i class="bi bi-palette-fill text-primary"></i>',
                html: '<h5 class="mb-2">Activar tema</h5><p>¿Activar <strong>'+name+'</strong> en el CMS principal?</p>',
                showCancelButton: true, confirmButtonColor: '#6366f1', confirmButtonText: '<i class="bi bi-check-lg me-1"></i> Activar', cancelButtonText: 'Cancelar'
            }).then(r => { if(r.isConfirmed){ Swal.fire({title:'Activando...',allowOutsideClick:false,showConfirmButton:false,didOpen:()=>Swal.showLoading()}); const f=document.getElementById('activateThemeForm'); f.action=url; document.getElementById('activateThemeSlug').value=slug; f.submit(); }});
        });
    });
    // ==================== SKIN MANAGEMENT ====================

    // Toggle skin visibility
    window.toggleSkin = function(slug) {
        fetch('/musedock/themes/skins/' + slug + '/toggle', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: '_token={{ csrf_token() }}'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                Swal.fire('Error', data.error || 'Error al cambiar estado', 'error');
            }
        });
    };

    // Delete skin
    window.deleteSkin = function(slug, name) {
        Swal.fire({
            title: '<i class="bi bi-trash text-danger"></i>',
            html: '<p>Eliminar el skin <strong>' + name + '</strong>?</p><small class="text-muted">Esta accion no se puede deshacer.</small>',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: '<i class="bi bi-trash me-1"></i> Eliminar',
            cancelButtonText: 'Cancelar'
        }).then(r => {
            if (r.isConfirmed) {
                fetch('/musedock/themes/skins/' + slug + '/delete', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: '_token={{ csrf_token() }}'
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const card = document.getElementById('skin-card-' + slug);
                        if (card) card.remove();
                        Swal.fire({icon:'success', title:'Eliminado', text: data.message, timer: 1500, showConfirmButton: false});
                    } else {
                        Swal.fire('Error', data.error || 'Error al eliminar', 'error');
                    }
                });
            }
        });
    };
});
</script>
@endpush
