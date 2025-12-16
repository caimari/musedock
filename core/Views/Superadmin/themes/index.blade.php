@extends('layouts.app')

@section('content')
@include('partials.alerts-sweetalert2')
<div class="container">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="mb-0">Gestión de Temas</h1>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-secondary" id="btnUploadTheme">
                <i class="bi bi-upload me-1"></i> Subir tema
            </button>
            <a href="{{ route('themes.create') }}" class="btn btn-primary text-decoration-none">
                <i class="bi bi-plus-lg me-1"></i> Crear nuevo tema
            </a>
        </div>
    </div>

    <table class="table table-bordered table-hover align-middle">
        <thead>
            <tr>
                <th>Nombre del tema</th>
                <th>Estado</th>
                <th>Disponible para Tenants</th>
                <th>Personalizar</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($themes as $theme)
                @php
                    $isCustomizable = false;

                    // Ruta absoluta a theme.json
                    $configPath = APP_ROOT . "/themes/" . $theme['slug'] . "/theme.json";

                    if (file_exists($configPath)) {
                        $themeConfigJson = @file_get_contents($configPath);

                        if ($themeConfigJson !== false) {
                            $themeConfig = json_decode($themeConfigJson, true);

                            if (json_last_error() !== JSON_ERROR_NONE) {
                                error_log("Error en JSON para el tema {$theme['slug']}: " . json_last_error_msg());
                            }

                            if (json_last_error() === JSON_ERROR_NONE && !empty($themeConfig['customizable'])) {
                                $isCustomizable = true;
                            }
                        } else {
                            error_log("No se pudo leer el archivo theme.json de {$theme['slug']}");
                        }
                    }

                    // Ruta para detectar si tiene home.blade.php
                    $homeViewPath = APP_ROOT . "/themes/" . $theme['slug'] . "/views/home.blade.php";
                @endphp

                <tr>
                    {{-- Nombre --}}
                    <td>
                        {{ ucfirst($theme['name']) }}
                        @if ($theme['status'] !== 'ok' && $theme['status'] !== 'activo')
                            <span class="badge bg-warning text-dark">{{ $theme['status'] }}</span>
                        @endif
                    </td>

                    {{-- Estado --}}
                    <td>
                        @if ($theme['slug'] === $currentTheme)
                            <span class="badge bg-success">Activo</span>
                        @else
                            <button type="button"
                                    class="btn btn-sm btn-outline-primary btn-activate-theme"
                                    data-theme-slug="{{ $theme['slug'] }}"
                                    data-theme-name="{{ ucfirst($theme['name']) }}"
                                    data-activate-url="{{ route('themes.activate') }}">
                                Activar
                            </button>
                        @endif
                    </td>

                    {{-- Disponible para Tenants --}}
                    <td class="text-center">
                        @php
                            $isAvailableForTenants = !empty($theme['available_for_tenants']);
                        @endphp
                        <form method="POST" action="{{ route('themes.toggle-tenant') }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="theme" value="{{ $theme['slug'] }}">
                            <button type="submit" class="btn btn-sm {{ $isAvailableForTenants ? 'btn-success' : 'btn-outline-secondary' }}" title="{{ $isAvailableForTenants ? 'Disponible para tenants - Click para deshabilitar' : 'No disponible - Click para habilitar' }}">
                                @if ($isAvailableForTenants)
                                    <i class="bi bi-check-circle-fill me-1"></i> Sí
                                @else
                                    <i class="bi bi-x-circle me-1"></i> No
                                @endif
                            </button>
                        </form>
                    </td>

                    {{-- Personalizar --}}
                    <td>
                        @if ($isCustomizable)
                            <a href="{{ route('themes.appearance.global', ['slug' => $theme['slug']]) }}" class="btn btn-sm btn-info text-white text-decoration-none" title="Personalizar Apariencia Global">
                                <i class="bi bi-palette me-1 text-white"></i> Apariencia
                            </a>
                        @else
                            <span class="text-muted small fst-italic">N/A</span>
                        @endif
                    </td>

                    {{-- Acciones --}}
                    <td>
                        <div class="d-flex flex-wrap gap-2">
                            @if (file_exists($homeViewPath))
                                <a href="{{ route('themes.editor.customize', ['slug' => $theme['slug']]) }}" class="btn btn-sm btn-outline-secondary text-decoration-none">
                                    <i class="bi bi-pencil-square me-1"></i> Editor
                                </a>
                            @else
                                <a href="{{ route('themes.editor.builder', ['slug' => $theme['slug'], 'file' => 'home.blade.php']) }}" class="btn btn-sm btn-danger text-white text-decoration-none">
                                    <i class="bi bi-hammer me-1 text-white"></i> Construir
                                </a>
                            @endif

                            <a href="{{ route('themes.download', ['slug' => $theme['slug']]) }}" class="btn btn-sm btn-outline-primary text-decoration-none">
                                <i class="bi bi-download me-1"></i> Descargar
                            </a>

                            <button type="button"
                                    class="btn btn-sm btn-outline-danger btn-delete-theme"
                                    data-theme-slug="{{ $theme['slug'] }}"
                                    data-theme-name="{{ ucfirst($theme['name']) }}"
                                    data-delete-url="{{ route('themes.destroy', ['slug' => $theme['slug']]) }}"
                                    {{ $theme['slug'] === $currentTheme ? 'disabled' : '' }}>
                                <i class="bi bi-trash me-1"></i> Eliminar
                            </button>
                        </div>
                    </td>
                </tr>

            @endforeach
        </tbody>
    </table>

    {{-- Formulario oculto para subir tema --}}
    <form id="uploadThemeForm" method="POST" action="{{ route('themes.upload') }}" enctype="multipart/form-data" style="display: none;">
        @csrf
        <input type="file" id="themeZipInput" name="theme_zip" accept=".zip">
    </form>

    {{-- Formulario oculto para eliminar tema --}}
    <form id="deleteThemeForm" method="POST" action="" style="display: none;">
        @csrf
        @method('DELETE')
        <input type="hidden" name="password" id="deleteThemePassword">
    </form>

    {{-- Formulario oculto para activar tema --}}
    <form id="activateThemeForm" method="POST" action="" style="display: none;">
        @csrf
        <input type="hidden" name="theme" id="activateThemeSlug">
    </form>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ========== SUBIR TEMA ZIP con SweetAlert2 ==========
    const btnUpload = document.getElementById('btnUploadTheme');
    const uploadForm = document.getElementById('uploadThemeForm');
    const fileInput = document.getElementById('themeZipInput');

    if (btnUpload) {
        btnUpload.addEventListener('click', function() {
            Swal.fire({
                title: '<i class="bi bi-cloud-upload text-primary"></i> Subir Tema',
                html: `
                    <div class="text-start">
                        <p class="text-muted mb-3">Selecciona un archivo ZIP con el tema a instalar.</p>
                        <div class="upload-zone border border-2 border-dashed rounded-3 p-4 text-center" id="dropZone" style="cursor: pointer; transition: all 0.3s;">
                            <i class="bi bi-file-earmark-zip display-4 text-muted"></i>
                            <p class="mb-1 mt-2"><strong>Arrastra el archivo aquí</strong></p>
                            <p class="text-muted small mb-2">o haz clic para seleccionar</p>
                            <span class="badge bg-secondary">Máximo 50MB</span>
                        </div>
                        <div id="selectedFile" class="mt-3 d-none">
                            <div class="alert alert-success py-2 mb-0">
                                <i class="bi bi-file-earmark-check me-2"></i>
                                <span id="fileName"></span>
                                <button type="button" class="btn-close btn-sm float-end" id="clearFile"></button>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            El nombre de la carpeta dentro del ZIP se utilizará como slug del tema.
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-upload me-1"></i> Subir Tema',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#6c757d',
                width: '500px',
                didOpen: () => {
                    const dropZone = document.getElementById('dropZone');
                    const selectedFileDiv = document.getElementById('selectedFile');
                    const fileNameSpan = document.getElementById('fileName');
                    const clearFileBtn = document.getElementById('clearFile');
                    let selectedFile = null;

                    // Click para seleccionar archivo
                    dropZone.addEventListener('click', () => fileInput.click());

                    // Drag & Drop
                    dropZone.addEventListener('dragover', (e) => {
                        e.preventDefault();
                        dropZone.classList.add('border-primary', 'bg-light');
                    });
                    dropZone.addEventListener('dragleave', () => {
                        dropZone.classList.remove('border-primary', 'bg-light');
                    });
                    dropZone.addEventListener('drop', (e) => {
                        e.preventDefault();
                        dropZone.classList.remove('border-primary', 'bg-light');
                        const file = e.dataTransfer.files[0];
                        if (file && file.name.endsWith('.zip')) {
                            handleFileSelect(file);
                        } else {
                            Swal.showValidationMessage('Por favor selecciona un archivo .zip');
                        }
                    });

                    // Cuando se selecciona archivo
                    fileInput.addEventListener('change', function() {
                        if (this.files[0]) {
                            handleFileSelect(this.files[0]);
                        }
                    });

                    // Limpiar archivo
                    clearFileBtn.addEventListener('click', () => {
                        fileInput.value = '';
                        selectedFileDiv.classList.add('d-none');
                        dropZone.classList.remove('d-none');
                        selectedFile = null;
                    });

                    function handleFileSelect(file) {
                        if (file.size > 50 * 1024 * 1024) {
                            Swal.showValidationMessage('El archivo excede el límite de 50MB');
                            return;
                        }
                        selectedFile = file;
                        fileNameSpan.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
                        selectedFileDiv.classList.remove('d-none');
                        dropZone.classList.add('d-none');

                        // Crear nuevo FileList para el input
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        fileInput.files = dt.files;
                    }
                },
                preConfirm: () => {
                    if (!fileInput.files[0]) {
                        Swal.showValidationMessage('Por favor selecciona un archivo ZIP');
                        return false;
                    }
                    return true;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostrar loading
                    Swal.fire({
                        title: 'Subiendo tema...',
                        html: '<p class="mb-2">Por favor espera mientras se sube e instala el tema.</p><div class="progress"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div></div>',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    uploadForm.submit();
                }
            });
        });
    }

    // ========== ELIMINAR TEMA con SweetAlert2 ==========
    document.querySelectorAll('.btn-delete-theme').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();

            const slug = this.dataset.themeSlug;
            const name = this.dataset.themeName || slug;
            const deleteUrl = this.dataset.deleteUrl;

            Swal.fire({
                title: '<i class="bi bi-exclamation-triangle-fill text-danger"></i>',
                html: `
                    <div class="text-center mb-3">
                        <h4 class="text-danger mb-2">Eliminar Tema</h4>
                        <p class="text-muted mb-0">Estás a punto de eliminar el tema:</p>
                        <h5 class="mt-2 mb-3"><span class="badge bg-dark fs-6">${name}</span></h5>
                    </div>
                    <div class="alert alert-danger py-2 mb-3">
                        <i class="bi bi-exclamation-circle me-2"></i>
                        <strong>¡Atención!</strong> Esta acción eliminará permanentemente todos los archivos del tema del servidor.
                    </div>
                    <div class="text-start">
                        <label for="swal-password" class="form-label fw-semibold">
                            <i class="bi bi-shield-lock me-1"></i> Confirma con tu contraseña
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-key"></i></span>
                            <input type="password" id="swal-password" class="form-control" placeholder="Ingresa tu contraseña" autocomplete="current-password">
                        </div>
                        <small class="text-muted mt-1 d-block">Por seguridad, necesitamos verificar tu identidad.</small>
                    </div>
                `,
                icon: null,
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-trash me-1"></i> Eliminar Tema',
                cancelButtonText: '<i class="bi bi-x-lg me-1"></i> Cancelar',
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                width: '480px',
                customClass: {
                    popup: 'swal-delete-theme',
                    confirmButton: 'btn btn-danger px-4',
                    cancelButton: 'btn btn-secondary px-4'
                },
                focusConfirm: false,
                didOpen: () => {
                    const passwordInput = document.getElementById('swal-password');
                    if (passwordInput) {
                        passwordInput.focus();
                        // Permitir enviar con Enter
                        passwordInput.addEventListener('keypress', function(e) {
                            if (e.key === 'Enter') {
                                Swal.clickConfirm();
                            }
                        });
                    }
                },
                preConfirm: () => {
                    const password = document.getElementById('swal-password').value;
                    if (!password || password.trim() === '') {
                        Swal.showValidationMessage('<i class="bi bi-exclamation-circle me-1"></i> Debes ingresar tu contraseña');
                        return false;
                    }
                    return password;
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    // Mostrar loading mientras se procesa
                    Swal.fire({
                        title: '<i class="bi bi-hourglass-split text-danger"></i>',
                        html: `
                            <h5 class="mb-3">Eliminando tema...</h5>
                            <p class="text-muted mb-0">Por favor espera mientras se eliminan los archivos.</p>
                            <div class="progress mt-3" style="height: 6px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-danger" style="width: 100%"></div>
                            </div>
                        `,
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false
                    });

                    // Configurar y enviar el formulario
                    const form = document.getElementById('deleteThemeForm');
                    const passwordField = document.getElementById('deleteThemePassword');

                    form.setAttribute('action', deleteUrl);
                    passwordField.value = result.value;
                    form.submit();
                }
            });
        });
    });

    // ========== ACTIVAR TEMA con SweetAlert2 ==========
    document.querySelectorAll('.btn-activate-theme').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();

            const slug = this.dataset.themeSlug;
            const name = this.dataset.themeName || slug;
            const activateUrl = this.dataset.activateUrl;

            Swal.fire({
                title: '<i class="bi bi-palette-fill text-primary"></i>',
                html: `
                    <div class="text-center mb-3">
                        <h4 class="text-primary mb-2">Activar Tema</h4>
                        <p class="text-muted mb-0">Estás a punto de activar el tema:</p>
                        <h5 class="mt-2 mb-3"><span class="badge bg-primary fs-6">${name}</span></h5>
                    </div>
                    <div class="alert alert-info py-2 mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        Este tema se aplicará a todo el sitio web inmediatamente.
                    </div>
                `,
                icon: null,
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-check-circle me-1"></i> Activar Tema',
                cancelButtonText: '<i class="bi bi-x-lg me-1"></i> Cancelar',
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#6c757d',
                width: '450px',
                customClass: {
                    popup: 'swal-activate-theme',
                    confirmButton: 'btn btn-primary px-4',
                    cancelButton: 'btn btn-secondary px-4'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostrar loading mientras se procesa
                    Swal.fire({
                        title: '<i class="bi bi-hourglass-split text-primary"></i>',
                        html: `
                            <h5 class="mb-3">Activando tema...</h5>
                            <p class="text-muted mb-0">Por favor espera mientras se aplica el nuevo tema.</p>
                            <div class="progress mt-3" style="height: 6px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
                            </div>
                        `,
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false
                    });

                    // Configurar y enviar el formulario
                    const form = document.getElementById('activateThemeForm');
                    const themeSlugField = document.getElementById('activateThemeSlug');

                    form.setAttribute('action', activateUrl);
                    themeSlugField.value = slug;
                    form.submit();
                }
            });
        });
    });
});
</script>
@endpush
