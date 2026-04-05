@extends('layouts.app')

@section('title', 'API Keys')

@push('styles')
<style>
.api-info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
.api-info-card { background: #f8f9fa; border-radius: 8px; padding: 1rem; border: 1px solid #e9ecef; transition: border-color 0.2s; }
.api-info-card:hover { border-color: #adb5bd; }
.api-info-card .icon { font-size: 1.5rem; margin-bottom: 0.5rem; display: block; }
.api-info-card h6 { font-size: 0.85rem; font-weight: 600; margin-bottom: 0.25rem; }
.api-info-card p { font-size: 0.78rem; color: #6c757d; margin: 0; line-height: 1.4; }
.key-hash { font-family: monospace; font-size: 0.75rem; color: #6c757d; }
.perm-group-title { font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #495057; margin-bottom: 0.5rem; padding-bottom: 0.25rem; border-bottom: 2px solid #e9ecef; }
</style>
@endpush

@section('content')
@php
  $allPerms = \Screenart\Musedock\Controllers\Api\V1\ApiKeyController::availablePermissions();
  $permGroups = \Screenart\Musedock\Controllers\Api\V1\ApiKeyController::permissionGroups();
  unset($permGroups['Sistema']); // Tenants don't see system perms
  $newKey = $_SESSION['_new_api_key'] ?? null;
  if ($newKey) { unset($_SESSION['_new_api_key']); }
  $adminPath = '/' . trim($GLOBALS['tenant']['admin_path'] ?? 'admin', '/');
@endphp

@php
  $tenantDomain = $GLOBALS['tenant']['domain'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
  $apiBaseUrl = 'https://' . $tenantDomain;
@endphp

<!-- Info cards -->
<div class="api-info-grid mb-4">
  <div class="api-info-card">
    <span class="icon">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fd7e14" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
    </span>
    <h6>Zapier / Make / n8n</h6>
    <p>URL base para REST:</p>
    <code style="font-size:0.72rem;word-break:break-all;">{{ $apiBaseUrl }}/api/v1/</code>
  </div>
  <div class="api-info-card">
    <span class="icon">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#198754" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
    </span>
    <h6>ChatGPT (Custom GPTs)</h6>
    <p>Schema para Actions:</p>
    <code style="font-size:0.72rem;word-break:break-all;">{{ $apiBaseUrl }}/api/v1/openapi.yaml</code>
  </div>
  <div class="api-info-card">
    <span class="icon">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#6f42c1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
    </span>
    <h6>Claude Code / Desktop</h6>
    <p>URL para MCP Server:</p>
    <code style="font-size:0.72rem;word-break:break-all;">{{ $apiBaseUrl }}</code>
  </div>
  <div class="api-info-card">
    <span class="icon">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#0d6efd" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
    </span>
    <h6>Scripts / curl / API</h6>
    <p>Ejemplo:</p>
    <code style="font-size:0.72rem;word-break:break-all;">curl -H "Authorization: Bearer mdk_..." {{ $apiBaseUrl }}/api/v1/posts</code>
  </div>
</div>

<!-- Main card -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h3 class="card-title mb-0">API Keys</h3>
    <button class="btn btn-primary btn-sm" id="btnCreateKey">
      <i class="bi bi-plus-lg"></i> Crear API Key
    </button>
  </div>

  <div class="card-body">
    @if(!empty($flash))
      <div class="alert alert-info alert-dismissible fade show">
        {{ $flash }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    <p class="text-muted small mb-3">Cada key que crees aqui solo tiene acceso a <strong>tu propio sitio web</strong>. No puede acceder a otros sitios.</p>

    <div class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Permisos</th>
            <th>Rate Limit</th>
            <th>Ultimo uso</th>
            <th>Expira</th>
            <th>Estado</th>
            <th width="100">Acciones</th>
          </tr>
        </thead>
        <tbody>
          @if(empty($keys))
            <tr><td colspan="7" class="text-center text-muted py-4">No hay API keys creadas.</td></tr>
          @endif
          @foreach($keys as $k)
            <tr>
              <td>
                <strong>{{ $k['name'] }}</strong><br>
                <span class="key-hash">{{ substr($k['api_key_hash'], 0, 12) }}...</span>
              </td>
              <td>
                @foreach($k['permissions_list'] as $p)
                  <span class="badge bg-secondary" style="font-size:0.7rem;">{{ $p }}</span>
                @endforeach
              </td>
              <td>{{ $k['rate_limit'] }}/min</td>
              <td><small>{{ $k['last_used_at'] ?? 'Nunca' }}</small></td>
              <td><small>{{ $k['expires_at'] ?? 'Nunca' }}</small></td>
              <td>
                @if($k['is_active'])
                  <span class="badge bg-success">Activa</span>
                @else
                  <span class="badge bg-secondary">Inactiva</span>
                @endif
              </td>
              <td class="text-nowrap">
                <form method="POST" action="{{ $adminPath }}/settings/api-keys/{{ $k['id'] }}/toggle" style="display:inline;">
                  {!! csrf_field() !!}
                  <button type="submit" class="btn btn-sm {{ $k['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' }}" title="{{ $k['is_active'] ? 'Desactivar' : 'Activar' }}">
                    <i class="bi {{ $k['is_active'] ? 'bi-pause-fill' : 'bi-play-fill' }}"></i>
                  </button>
                </form>
                <button type="button" class="btn btn-sm btn-outline-danger" title="Eliminar" onclick="deleteKey({{ $k['id'] }}, '{{ addslashes($k['name']) }}')">
                  <i class="bi bi-trash"></i>
                </button>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Hidden form for delete -->
<form id="deleteKeyForm" method="POST" style="display:none;">
  {!! csrf_field() !!}
</form>

@push('scripts')
<script>
const csrf = '{{ csrf_token() }}';
const adminPath = '{{ $adminPath }}';
const permGroups = {!! json_encode($permGroups) !!};
const allPerms = {!! json_encode($allPerms) !!};

@if($newKey)
Swal.fire({
  icon: 'success',
  title: 'API Key creada',
  html: `
    <p class="mb-2">Copia la key ahora. <strong>No se mostrara de nuevo.</strong></p>
    <div style="background:#fff3cd;padding:12px;border-radius:8px;border:1px solid #ffc107;">
      <code id="swalNewKey" style="font-size:1rem;word-break:break-all;user-select:all;">{{ $newKey }}</code>
    </div>
    <button class="btn btn-sm btn-outline-dark mt-3" onclick="navigator.clipboard.writeText(document.getElementById('swalNewKey').textContent).then(()=>this.innerHTML='<i class=\\'bi bi-check\\'></i> Copiado!')">
      <i class="bi bi-clipboard"></i> Copiar al portapapeles
    </button>
  `,
  width: 550,
  showConfirmButton: true,
  confirmButtonText: 'Entendido',
  allowOutsideClick: false,
});
@endif

function deleteKey(id, name) {
  Swal.fire({
    title: 'Eliminar API Key',
    html: `Vas a eliminar <strong>${name}</strong>.<br>Esta accion no se puede deshacer.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    confirmButtonText: 'Eliminar',
    cancelButtonText: 'Cancelar',
  }).then((result) => {
    if (result.isConfirmed) {
      const form = document.getElementById('deleteKeyForm');
      form.action = `${adminPath}/settings/api-keys/${id}/delete`;
      form.submit();
    }
  });
}

document.getElementById('btnCreateKey').addEventListener('click', function() {
  // Build permissions HTML
  let permsHtml = '<div class="row text-start" style="max-height:280px;overflow-y:auto;">';
  for (const [groupName, groupPermKeys] of Object.entries(permGroups)) {
    permsHtml += `<div class="col-6 mb-3"><div class="perm-group-title">${groupName}</div>`;
    groupPermKeys.forEach(pk => {
      const label = allPerms[pk] || pk;
      const safeId = pk.replace(/[\.\-\*]/g, '_');
      permsHtml += `
        <div class="form-check" style="font-size:0.82rem;">
          <input class="form-check-input perm-cb" type="checkbox" value="${pk}" id="p_${safeId}">
          <label class="form-check-label" for="p_${safeId}"><code style="font-size:0.75rem;">${pk}</code></label>
        </div>`;
    });
    permsHtml += '</div>';
  }
  permsHtml += '</div>';
  permsHtml += `<div class="form-check text-start mt-1"><input class="form-check-input" type="checkbox" id="swalSelectAll" onchange="document.querySelectorAll('.perm-cb').forEach(c=>c.checked=this.checked)"><label class="form-check-label" for="swalSelectAll"><strong>Seleccionar todos</strong></label></div>`;

  Swal.fire({
    title: 'Crear nueva API Key',
    width: 600,
    html: `
      <div class="text-start">
        <div class="mb-3">
          <label class="form-label fw-bold">Nombre <span class="text-danger">*</span></label>
          <input type="text" id="swalName" class="form-control" placeholder="ej: ChatGPT Publisher, Zapier Integration">
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold">Permisos</label>
          ${permsHtml}
        </div>

        <div class="row">
          <div class="col-6">
            <label class="form-label fw-bold">Rate Limit (req/min)</label>
            <input type="number" id="swalRateLimit" class="form-control" value="60" min="1" max="1000">
          </div>
          <div class="col-6">
            <label class="form-label fw-bold">Expiracion</label>
            <input type="datetime-local" id="swalExpires" class="form-control">
          </div>
        </div>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Crear API Key',
    cancelButtonText: 'Cancelar',
    focusConfirm: false,
    preConfirm: () => {
      const name = document.getElementById('swalName').value.trim();
      if (!name) {
        Swal.showValidationMessage('El nombre es obligatorio');
        return false;
      }
      const perms = [...document.querySelectorAll('.perm-cb:checked')].map(c => c.value);
      if (perms.length === 0) {
        Swal.showValidationMessage('Selecciona al menos un permiso');
        return false;
      }
      return { name, permissions: perms, rate_limit: document.getElementById('swalRateLimit').value || '60', expires_at: document.getElementById('swalExpires').value || '' };
    }
  }).then((result) => {
    if (result.isConfirmed && result.value) {
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = `${adminPath}/settings/api-keys`;
      form.style.display = 'none';

      const addField = (name, value) => {
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = name; input.value = value;
        form.appendChild(input);
      };

      addField('_csrf', csrf);
      addField('name', result.value.name);
      addField('rate_limit', result.value.rate_limit);
      addField('expires_at', result.value.expires_at);
      result.value.permissions.forEach(p => addField('permissions[]', p));

      document.body.appendChild(form);
      form.submit();
    }
  });
});
</script>
@endpush
@endsection
