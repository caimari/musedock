@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="row g-4">
  <div class="col-12 col-xl-9">

    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h3 class="card-title mb-0"><i class="bi bi-cloud me-2"></i>Cuentas DNS</h3>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-info btn-sm" id="dnsa-help" title="Cómo funciona"><i class="bi bi-info-circle me-1"></i> Cómo funciona</button>
          <form method="POST" action="/musedock/settings/dns-accounts/import" class="d-inline">{!! csrf_field() !!}
            <button class="btn btn-outline-secondary btn-sm" type="submit" title="Copia lo que hubiera en Domain Manager y en el .env"><i class="bi bi-box-arrow-in-down me-1"></i> Importar cuentas existentes</button>
          </form>
          <button class="btn btn-primary btn-sm" type="button" id="dnsa-new"><i class="bi bi-plus-circle me-1"></i> Añadir cuenta</button>
        </div>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light">
              <tr><th>Proveedor</th><th>Nombre</th><th>Referencia</th><th>Credencial</th><th>Última prueba</th><th>Estado</th><th class="text-end">Acciones</th></tr>
            </thead>
            <tbody>
            @forelse($accounts as $a)
              <tr>
                <td><strong>{{ $a['provider_label'] }}</strong>@if($a['provider'] === 'cloudflare' && !empty($a['metadata']['role']))<div><span class="badge bg-secondary">{{ $a['metadata']['role'] }}</span></div>@endif</td>
                <td>{{ $a['name'] }}@if($a['is_default']) <i class="bi bi-star-fill text-warning" title="Predeterminada de su proveedor"></i>@endif</td>
                <td class="text-muted" style="font-size:.8rem;word-break:break-all;">{{ $a['account_ref'] ?: '—' }}</td>
                <td style="font-size:.8rem;">
                  @if(!$a['readable'])<span class="text-danger">Ilegible (¿cambió APP_KEY?)</span>
                  @else @foreach($a['credentials_masked'] as $k => $v)<div><span class="text-muted">{{ $k }}:</span> <code>{{ $v }}</code></div>@endforeach @endif
                </td>
                <td style="font-size:.8rem;">
                  @if($a['last_test_status'])
                    <span class="badge bg-{{ $a['last_test_status'] === 'ok' ? 'success' : ($a['last_test_status'] === 'error' ? 'danger' : 'secondary') }}">{{ $a['last_test_status'] === 'ok' ? 'Funciona' : ($a['last_test_status'] === 'error' ? 'Falla' : 'Sin prueba') }}</span>
                    <div class="text-muted">{{ $a['last_test_message'] }}</div>
                  @else <span class="text-muted">Sin probar</span> @endif
                </td>
                <td>@if($a['enabled'])<span class="badge bg-success">Habilitada</span>@else<span class="badge bg-secondary">Deshabilitada</span>@endif</td>
                <td class="text-end text-nowrap">
                  <form method="POST" action="/musedock/settings/dns-accounts/{{ $a['id'] }}/test" class="d-inline">{!! csrf_field() !!}<button class="btn btn-outline-primary btn-sm" title="Probar la conexión"><i class="bi bi-wifi"></i></button></form>
                  <button type="button" class="btn btn-outline-secondary btn-sm dnsa-edit" data-id="{{ $a['id'] }}" title="Editar / sobrescribir clave"><i class="bi bi-pencil"></i></button>
                  @if(!$a['is_default'])<form method="POST" action="/musedock/settings/dns-accounts/{{ $a['id'] }}/default" class="d-inline">{!! csrf_field() !!}<button class="btn btn-outline-warning btn-sm" title="Marcar como predeterminada"><i class="bi bi-star"></i></button></form>@endif
                  <form method="POST" action="/musedock/settings/dns-accounts/{{ $a['id'] }}/toggle" class="d-inline">{!! csrf_field() !!}<button class="btn btn-outline-secondary btn-sm" title="{{ $a['enabled'] ? 'Deshabilitar' : 'Habilitar' }}"><i class="bi bi-{{ $a['enabled'] ? 'pause-circle' : 'play-circle' }}"></i></button></form>
                  <form method="POST" action="/musedock/settings/dns-accounts/{{ $a['id'] }}/delete" class="d-inline dnsa-delete" data-name="{{ $a['name'] }}">{!! csrf_field() !!}<input type="hidden" name="confirm_password" value=""><button class="btn btn-outline-danger btn-sm" title="Borrar"><i class="bi bi-trash"></i></button></form>
                </td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center text-muted p-4">No hay cuentas todavía. Pulsa «Importar cuentas existentes» o «Añadir cuenta».</td></tr>
            @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- Formulario añadir / editar --}}
    <div class="card" id="dnsa-form-card" style="display:none;">
      <div class="card-header"><h5 class="mb-0" id="dnsa-form-title">Añadir cuenta</h5></div>
      <div class="card-body">
        <form method="POST" action="/musedock/settings/dns-accounts" autocomplete="off" id="dnsa-form">
          {!! csrf_field() !!}
          <input type="hidden" name="id" value="">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Proveedor</label>
              <select class="form-select" name="provider" id="dnsa-provider">
                @foreach($providers as $key => $p)<option value="{{ $key }}">{{ $p['label'] }}</option>@endforeach
              </select>
            </div>
            <div class="col-md-4"><label class="form-label">Nombre</label><input type="text" class="form-control" name="name" required maxlength="120" placeholder="Ej.: Screen Art Films"></div>
            <div class="col-md-4"><label class="form-label" id="dnsa-ref-label">Referencia de la cuenta</label><input type="text" class="form-control" name="account_ref" maxlength="120"></div>
            <div id="dnsa-fields" class="row g-3 mx-0 px-0"></div>
            <div class="col-md-4 dnsa-cf"><label class="form-label">Rol</label>
              <select class="form-select" name="role"><option value="primary">Primary (zonas propias)</option><option value="domains">Domains (dominios de clientes)</option><option value="readonly">Read-only (solo consultar)</option></select></div>
            <div class="col-md-4 dnsa-cf"><label class="form-label">SSL para dominios nuevos</label>
              <select class="form-select" name="ssl_mode"><option value="full">Full</option><option value="strict">Strict</option><option value="flexible">Flexible</option></select></div>
            <div class="col-12 d-flex gap-4">
              <div class="form-check"><input class="form-check-input" type="checkbox" name="enabled" value="1" id="dnsa-enabled" checked><label class="form-check-label" for="dnsa-enabled">Habilitada</label></div>
              <div class="form-check"><input class="form-check-input" type="checkbox" name="is_default" value="1" id="dnsa-default"><label class="form-check-label" for="dnsa-default">Predeterminada de su proveedor</label></div>
            </div>
          </div>
          <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i> Guardar y probar</button>
            <button class="btn btn-outline-secondary" type="button" id="dnsa-cancel">Cancelar</button>
          </div>
          <small class="text-muted d-block mt-2">Al editar, deja un campo secreto vacío para conservar el guardado; escribe uno nuevo para sobrescribirlo.</small>
        </form>
      </div>
    </div>

  </div>
  <div class="col-xl-3 d-none d-xl-block">
    @include('settings.partials.sidebar', ['active' => 'dns-accounts'])
  </div>
</div>

<script>
(function () {
    var providers = @json($providers), accounts = @json($accounts);
    var card = document.getElementById('dnsa-form-card'), form = document.getElementById('dnsa-form'),
        sel = document.getElementById('dnsa-provider'), box = document.getElementById('dnsa-fields');

    function render(provider, acc) {
        box.innerHTML = '';
        var fields = (providers[provider] || {}).fields || {};
        Object.keys(fields).forEach(function (f) {
            var def = fields[f], saved = acc && acc.credentials_masked && acc.credentials_masked[f];
            var col = document.createElement('div'); col.className = 'col-md-6';
            var lab = document.createElement('label'); lab.className = 'form-label'; lab.textContent = def[0] + (def[2] ? '' : ' (opcional)');
            var inp = document.createElement('input'); inp.className = 'form-control'; inp.name = 'credentials[' + f + ']';
            inp.type = def[1] ? 'password' : 'text'; inp.autocomplete = 'new-password';
            if (acc) { inp.placeholder = saved ? (def[1] ? saved + ' (guardada; vacío = no cambiar)' : saved) : ''; if (!def[1] && saved) inp.value = saved; }
            else if (def[2]) { inp.required = true; }
            col.appendChild(lab); col.appendChild(inp); box.appendChild(col);
        });
        document.querySelectorAll('.dnsa-cf').forEach(function (e) { e.style.display = provider === 'cloudflare' ? '' : 'none'; });
        document.getElementById('dnsa-ref-label').textContent = provider === 'cloudflare' ? 'Account ID de Cloudflare' : 'Referencia de la cuenta (opcional)';
    }
    function open(acc) {
        card.style.display = '';
        var f = function (n) { return form.querySelector('[name="' + n + '"]'); };
        f('id').value = acc ? acc.id : '';
        sel.value = acc ? acc.provider : 'cloudflare'; sel.disabled = !!acc;
        if (acc) { var h = document.createElement('input'); h.type = 'hidden'; h.name = 'provider'; h.value = acc.provider; h.id = 'dnsa-provider-h'; form.appendChild(h); }
        else { var old = document.getElementById('dnsa-provider-h'); if (old) old.remove(); }
        f('name').value = acc ? acc.name : ''; f('account_ref').value = acc ? (acc.account_ref || '') : '';
        f('role').value = acc && acc.metadata.role ? acc.metadata.role : 'primary';
        f('ssl_mode').value = acc && acc.metadata.ssl_mode ? acc.metadata.ssl_mode : 'full';
        document.getElementById('dnsa-enabled').checked = acc ? !!acc.enabled : true;
        document.getElementById('dnsa-default').checked = acc ? !!acc.is_default : false;
        document.getElementById('dnsa-form-title').textContent = acc ? 'Editar cuenta: ' + acc.name : 'Añadir cuenta';
        render(sel.value, acc);
        card.scrollIntoView({ behavior: 'smooth' });
    }
    document.getElementById('dnsa-help').addEventListener('click', function () {
        Swal.fire({
            title: 'Cuentas DNS: un solo sitio para las claves',
            icon: 'info', width: 640, confirmButtonText: 'Entendido',
            html: '<div style="text-align:left;font-size:.95rem">'
                + '<p>Lo que pongas aquí lo usan <strong>todos los plugins y módulos</strong> (Domain Manager, Cloud, Search Console…). Si cambias o añades una clave, funciona en todos a la vez.</p>'
                + '<p>Se guardan <strong>siempre cifradas</strong> (AES-256-GCM) y nunca se vuelven a mostrar.</p>'
                + '<p>Puedes tener varias cuentas del mismo proveedor y de proveedores distintos. Al editar, deja un campo secreto vacío para conservar el guardado.</p>'
                + '<p class="mb-0"><strong>Caddy</strong> (certificados) lee su token de <code>/etc/default/caddy</code>; se actualiza desde «Guardar token de Cloudflare» en Domain Manager.</p></div>'
        });
    });
    document.getElementById('dnsa-new').addEventListener('click', function () { open(null); });
    document.getElementById('dnsa-cancel').addEventListener('click', function () { card.style.display = 'none'; });
    sel.addEventListener('change', function () { render(sel.value, null); });
    document.querySelectorAll('.dnsa-edit').forEach(function (b) {
        b.addEventListener('click', function () { open(accounts.filter(function (a) { return a.id == b.dataset.id; })[0]); });
    });
    document.querySelectorAll('.dnsa-delete').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                title: 'Borrar cuenta DNS', icon: 'warning', input: 'password', inputPlaceholder: 'Contraseña de administrador',
                html: 'Se borrará <strong>' + f.dataset.name.replace(/</g, '&lt;') + '</strong> y dejará de estar disponible para todos los plugins y módulos que la usen. Confirma con tu contraseña.',
                inputAttributes: { autocomplete: 'current-password' }, showCancelButton: true,
                confirmButtonText: 'Borrar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545',
                inputValidator: function (v) { return v ? undefined : 'Escribe tu contraseña'; }
            }).then(function (r) { if (r.isConfirmed) { f.querySelector('[name=confirm_password]').value = r.value; f.submit(); } });
        });
    });
})();
</script>
@endsection
