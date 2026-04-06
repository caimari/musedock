@extends('layouts.app')

@section('title', 'Proveedores de Imagen IA')

@section('content')
<div class="container-fluid">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
        <div class="d-flex align-items-center gap-3">
            <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#d63384,#e685b5);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-stars" style="font-size:1.35rem;color:#fff;"></i>
            </div>
            <div>
                <h3 class="mb-0" style="font-size:1.25rem;font-weight:700;">Proveedores de Imagen IA</h3>
                <p class="text-muted mb-0" style="font-size:0.85rem;">Gestiona los proveedores de generación de imágenes con IA</p>
            </div>
        </div>
        <div style="display:flex;gap:1rem;">
            <button type="button" id="btnNewProvider" style="display:flex;align-items:center;gap:0.35rem;font-size:0.85rem;padding:0.4rem 0.75rem;border-radius:6px;background:#f8f9fa;border:1px solid #e9ecef;color:#6c757d;cursor:pointer;transition:all 0.15s;"
                    onmouseover="this.style.background='#e9ecef'" onmouseout="this.style.background='#f8f9fa'">
                <i class="bi bi-plus-lg"></i>
                <span>Nuevo proveedor</span>
            </button>
            <a href="/musedock/modules" style="display:flex;align-items:center;gap:0.35rem;font-size:0.85rem;padding:0.4rem 0.75rem;border-radius:6px;background:#f8f9fa;border:1px solid #e9ecef;color:#6c757d;text-decoration:none;">
                <i class="bi bi-arrow-left"></i>
                <span>Módulos</span>
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if(empty($providers))
                <div class="text-center py-5">
                    <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3 mb-1">No hay proveedores de imagen configurados.</p>
                    <p class="text-muted">Haz clic en <strong>"Nuevo proveedor"</strong> para empezar.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Nombre</th>
                                <th>Tipo</th>
                                <th>Modelo</th>
                                <th>Estado</th>
                                <th>Global</th>
                                <th class="text-end pe-4" width="150">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($providers as $p)
                            <tr>
                                <td class="ps-4"><strong>{{ $p['name'] }}</strong></td>
                                <td><span class="badge bg-info">{{ strtoupper($p['provider_type']) }}</span></td>
                                <td><code>{{ $p['model'] ?? '-' }}</code></td>
                                <td>
                                    @if($p['active'])
                                        <span class="badge bg-success">Activo</span>
                                    @else
                                        <span class="badge bg-secondary">Inactivo</span>
                                    @endif
                                </td>
                                <td>
                                    @if($p['system_wide'] ?? false)
                                        <span class="badge bg-primary">Si</span>
                                    @else
                                        <span class="badge bg-light text-dark">No</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-outline-primary btn-edit-provider"
                                        data-provider="{{ json_encode($p) }}">
                                        <i class="bi bi-pencil"></i> Editar
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger btn-delete-provider"
                                        data-id="{{ $p['id'] }}"
                                        data-name="{{ $p['name'] }}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Hidden forms for create/edit/delete --}}
<form id="formCreateProvider" method="POST" action="/musedock/ai-image/settings" style="display:none;">
    @csrf
    <input type="hidden" name="action" value="create_provider">
</form>
<form id="formEditProvider" method="POST" action="/musedock/ai-image/settings" style="display:none;">
    @csrf
    <input type="hidden" name="action" value="update_provider">
</form>
<form id="formDeleteProvider" method="POST" action="/musedock/ai-image/settings" style="display:none;">
    @csrf
    <input type="hidden" name="action" value="delete_provider">
    <input type="hidden" name="provider_id" id="deleteProviderId">
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {

    function getProviderFormHtml(data) {
        data = data || {};
        var isEdit = !!data.id;
        var apiKeyPlaceholder = isEdit ? 'Dejar vacio para no cambiar' : 'sk-...';

        return '<div class="text-start">'
            + '<div class="row">'
            + '<div class="col-md-5 mb-3">'
            + '<label class="form-label fw-bold">Nombre</label>'
            + '<input type="text" id="swalProvName" class="form-control" value="' + (data.name || '') + '" placeholder="Ej: OpenAI DALL-E 3" required>'
            + '</div>'
            + '<div class="col-md-4 mb-3">'
            + '<label class="form-label fw-bold">Tipo</label>'
            + '<select id="swalProvType" class="form-select">'
            + '<option value="openai"' + (data.provider_type === 'openai' ? ' selected' : '') + '>OpenAI (DALL-E)</option>'
            + '<option value="minimax"' + (data.provider_type === 'minimax' ? ' selected' : '') + '>MiniMax</option>'
            + '<option value="picalias"' + (data.provider_type === 'picalias' ? ' selected' : '') + '>Picalias</option>'
            + '<option value="fal"' + (data.provider_type === 'fal' ? ' selected' : '') + '>FAL</option>'
            + '</select>'
            + '</div>'
            + '<div class="col-md-3 mb-3">'
            + '<label class="form-label fw-bold">Modelo</label>'
            + '<input type="text" id="swalProvModel" class="form-control" value="' + (data.model || '') + '" placeholder="dall-e-3">'
            + '</div>'
            + '</div>'
            + '<div class="row">'
            + '<div class="col-md-6 mb-3">'
            + '<label class="form-label fw-bold">API Key</label>'
            + '<input type="password" id="swalProvApiKey" class="form-control" value="' + (isEdit ? '' : (data.api_key || '')) + '" placeholder="' + apiKeyPlaceholder + '">'
            + '</div>'
            + '<div class="col-md-6 mb-3">'
            + '<label class="form-label fw-bold">Endpoint <small class="text-muted">(opcional)</small></label>'
            + '<input type="text" id="swalProvEndpoint" class="form-control" value="' + (data.endpoint || '') + '" placeholder="Dejar vacio para usar el predeterminado">'
            + '</div>'
            + '</div>'
            + '<div class="row">'
            + '<div class="col-md-4">'
            + '<div class="form-check form-switch">'
            + '<input class="form-check-input" type="checkbox" id="swalProvActive"' + (data.active === undefined || data.active ? ' checked' : '') + '>'
            + '<label class="form-check-label" for="swalProvActive">Activo</label>'
            + '</div>'
            + '</div>'
            + '<div class="col-md-8">'
            + '<div class="form-check form-switch">'
            + '<input class="form-check-input" type="checkbox" id="swalProvSystemWide"' + (data.system_wide === undefined || data.system_wide ? ' checked' : '') + '>'
            + '<label class="form-check-label" for="swalProvSystemWide">Disponible para todos los tenants (Global)</label>'
            + '</div>'
            + '</div>'
            + '</div>'
            + '</div>';
    }

    function submitProviderForm(formId, extraFields) {
        var form = document.getElementById(formId);
        // Remove old dynamic inputs
        form.querySelectorAll('.dynamic-field').forEach(function(el) { el.remove(); });
        // Add new fields
        Object.keys(extraFields).forEach(function(key) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = extraFields[key];
            input.className = 'dynamic-field';
            form.appendChild(input);
        });
        form.submit();
    }

    // ========== NEW PROVIDER ==========
    document.getElementById('btnNewProvider').addEventListener('click', function() {
        Swal.fire({
            title: '<i class="bi bi-plus-circle text-primary"></i> Nuevo proveedor de imagen',
            html: getProviderFormHtml({}),
            width: '750px',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-check-lg me-1"></i> Crear proveedor',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            customClass: { htmlContainer: 'text-start' },
            preConfirm: function() {
                var name = document.getElementById('swalProvName').value.trim();
                if (!name) {
                    Swal.showValidationMessage('El nombre es obligatorio');
                    return false;
                }
                return {
                    provider_name: name,
                    provider_type: document.getElementById('swalProvType').value,
                    model: document.getElementById('swalProvModel').value.trim(),
                    api_key: document.getElementById('swalProvApiKey').value.trim(),
                    endpoint: document.getElementById('swalProvEndpoint').value.trim(),
                    active: document.getElementById('swalProvActive').checked ? 'on' : '',
                    system_wide: document.getElementById('swalProvSystemWide').checked ? 'on' : ''
                };
            }
        }).then(function(result) {
            if (result.isConfirmed && result.value) {
                Swal.fire({
                    title: 'Creando proveedor...',
                    allowOutsideClick: false,
                    didOpen: function() { Swal.showLoading(); }
                });
                submitProviderForm('formCreateProvider', result.value);
            }
        });
    });

    // ========== EDIT PROVIDER ==========
    document.querySelectorAll('.btn-edit-provider').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var p = JSON.parse(this.getAttribute('data-provider'));
            Swal.fire({
                title: '<i class="bi bi-pencil text-primary"></i> Editar proveedor',
                html: getProviderFormHtml(p),
                width: '750px',
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-check-lg me-1"></i> Guardar cambios',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#6c757d',
                customClass: { htmlContainer: 'text-start' },
                preConfirm: function() {
                    var name = document.getElementById('swalProvName').value.trim();
                    if (!name) {
                        Swal.showValidationMessage('El nombre es obligatorio');
                        return false;
                    }
                    return {
                        provider_id: p.id,
                        provider_name: name,
                        provider_type: document.getElementById('swalProvType').value,
                        model: document.getElementById('swalProvModel').value.trim(),
                        api_key: document.getElementById('swalProvApiKey').value.trim(),
                        endpoint: document.getElementById('swalProvEndpoint').value.trim(),
                        active: document.getElementById('swalProvActive').checked ? 'on' : '',
                        system_wide: document.getElementById('swalProvSystemWide').checked ? 'on' : ''
                    };
                }
            }).then(function(result) {
                if (result.isConfirmed && result.value) {
                    Swal.fire({
                        title: 'Guardando cambios...',
                        allowOutsideClick: false,
                        didOpen: function() { Swal.showLoading(); }
                    });
                    submitProviderForm('formEditProvider', result.value);
                }
            });
        });
    });

    // ========== DELETE PROVIDER ==========
    document.querySelectorAll('.btn-delete-provider').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var name = this.getAttribute('data-name');
            Swal.fire({
                title: 'Eliminar proveedor',
                html: '<p>Vas a eliminar el proveedor <strong>"' + name + '"</strong>.</p>'
                    + '<p class="text-danger mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Esta accion no se puede deshacer.</p>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-trash me-1"></i> Eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d'
            }).then(function(result) {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Eliminando...',
                        allowOutsideClick: false,
                        didOpen: function() { Swal.showLoading(); }
                    });
                    document.getElementById('deleteProviderId').value = id;
                    document.getElementById('formDeleteProvider').submit();
                }
            });
        });
    });

});
</script>
@endpush
