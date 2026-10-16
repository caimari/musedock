@extends('layouts::app')

@section('title', 'Search Console')

@section('content')
@php
    $fmt = fn($n) => number_format((int) $n, 0, ',', '.');
    $linkedDomains = array_column($properties, 'domain');
    $ownPropertyMissing = $credential && $tenantDomain !== '' && !in_array($tenantDomain, $linkedDomains, true);
@endphp
<style>
.gsc-page .card { border:1px solid #e5eaf1;border-radius:12px;box-shadow:0 3px 14px rgba(30,50,80,.035); }
.gsc-page .stat-badge { display:flex;align-items:center;gap:.35rem;font-size:.85rem;padding:.4rem .75rem;border-radius:6px;background:#f8f9fa;border:1px solid #e9ecef; }
.gsc-page .table td,.gsc-page .table th { padding:12px 14px;vertical-align:middle; }
.gsc-page .num { text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap; }
.gsc-up { color:#1e7e34; } .gsc-down { color:#c62828; }
.gsc-steps li { margin-bottom:.35rem; }
</style>

<div class="app-content gsc-page">
<div class="container-fluid">

    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
        <div class="d-flex align-items-center gap-3">
            <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#1a73e8,#34a853);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-graph-up-arrow" style="font-size:1.35rem;color:#fff;"></i>
            </div>
            <div>
                <h3 class="mb-0" style="font-size:1.25rem;font-weight:700;">Cómo te encuentran en Google</h3>
                <p class="text-muted mb-0" style="font-size:0.85rem;">Clics, impresiones y posición media de tu sitio (últimos 28 días)</p>
            </div>
        </div>
        @if($isClient)
        <div class="stat-badge" style="background:rgba(95,104,115,.08);border-color:rgba(95,104,115,.2);color:#3f4650;"><i class="bi bi-shield-lock"></i><span>Datos privados</span></div>
        @endif
    </div>

    @if($isClient)
    <div class="alert alert-light border d-flex gap-2 align-items-start" style="font-size:.88rem;">
        <i class="bi bi-shield-lock" style="font-size:1.1rem;color:#5f6873;"></i>
        <div>Tus datos de Search Console son <strong>privados</strong>: se leen con tu propia cuenta de Google y solo los ve tu equipo. El administrador de la plataforma no tiene acceso a ellos.</div>
    </div>
    @endif

    {{-- ===== Cuenta propia (obligatoria para clientes, opcional para el resto) ===== --}}
    @if($canManage && ($isClient || $credential))
        @if(!$credential)
        <div class="card mb-4"><div class="card-body p-4">
            <h5 style="font-weight:700;">Conecta tu cuenta de Google</h5>
            <ol class="gsc-steps" style="font-size:.9rem;">
                <li>En <a href="https://console.cloud.google.com/projectcreate" target="_blank" rel="noopener">Google Cloud Console</a> crea un proyecto.</li>
                <li>Activa <a href="https://console.cloud.google.com/apis/library/searchconsole.googleapis.com" target="_blank" rel="noopener">Google Search Console API</a> y <a href="https://console.cloud.google.com/apis/library/siteverification.googleapis.com" target="_blank" rel="noopener">Site Verification API</a>.</li>
                <li>Crea una <em>cuenta de servicio</em> (sin roles) y descarga su clave <em>JSON</em>.</li>
                <li>Súbela aquí. Se guarda cifrada y no se vuelve a mostrar.</li>
            </ol>
            <form method="post" action="{{ $baseUrl }}/credential" enctype="multipart/form-data" class="d-flex gap-2 flex-wrap align-items-center">
                {!! csrf_field() !!}
                <input type="file" name="key_file" accept=".json,application/json" class="form-control" style="max-width:420px;" required aria-label="Fichero JSON de la cuenta de servicio">
                <button class="btn btn-primary" type="submit"><i class="bi bi-plug"></i> Conectar</button>
            </form>
        </div></div>
        @else
        <div class="card mb-4"><div class="card-body p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div style="font-size:.9rem;">
                <i class="bi bi-person-badge"></i> <strong>{{ $credential['client_email'] }}</strong>
                @if($credential['last_test_ok'] === false)
                    <span class="badge bg-danger ms-1">Error: {{ $credential['last_error'] }}</span>
                @else
                    <span class="badge bg-success ms-1">Conectada</span>
                @endif
                <div class="text-muted" style="font-size:.8rem;">Si tu dominio ya está en Search Console, añade este email en Configuración → Usuarios y permisos.</div>
            </div>
            <div class="d-flex gap-2">
                <form method="post" action="{{ $baseUrl }}/refresh">{!! csrf_field() !!}<button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-arrow-repeat"></i> Releer propiedades</button></form>
                <form method="post" action="{{ $baseUrl }}/credential/delete" id="gsc-delete-form">{!! csrf_field() !!}<input type="hidden" name="confirm_password" value=""><button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-x-circle"></i> Desconectar</button></form>
            </div>
        </div></div>

        @if($ownPropertyMissing)
        <div class="card mb-4"><div class="card-body p-3 d-flex justify-content-between align-items-center flex-wrap gap-2" id="gsc-own-domain" data-domain="{{ $tenantDomain }}">
            <div style="font-size:.9rem;"><strong>{{ $tenantDomain }}</strong> aún no está accesible con tu cuenta.
                <div class="gsc-verify-msg text-muted" style="font-size:.8rem;">Puedes darlo de alta automáticamente si su DNS lo gestiona la plataforma.</div></div>
            <button type="button" class="btn btn-primary btn-sm gsc-verify"><i class="bi bi-lightning"></i> Dar de alta</button>
        </div></div>
        @endif
        @endif
    @elseif($isClient)
        <div class="alert alert-info">Pide a un administrador de tu sitio con el permiso «Gestionar Search Console» que conecte la cuenta de Google.</div>
    @endif

    {{-- ===== Datos ===== --}}
    @if(!empty($properties))
    <div class="card mb-4"><div class="card-body p-0"><div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Propiedad</th><th class="num">Clics</th><th class="num">Impresiones</th><th class="num">CTR</th><th class="num">Posición</th><th class="num">vs. 28 d antes</th><th>Última sync.</th></tr></thead>
            <tbody>
            @foreach($properties as $p)
                @php
                    $t = $totals[(int) $p['id']] ?? null;
                    $delta = ($t && $t['prev_clicks'] > 0) ? ($t['clicks'] - $t['prev_clicks']) / $t['prev_clicks'] : null;
                @endphp
                <tr>
                    <td><a href="{{ $baseUrl }}/domain/{{ $p['id'] }}" title="Ver el informe de SEO"><strong>{{ $p['domain'] }}</strong></a>
                        <div class="text-muted" style="font-size:.75rem;">{{ $p['site_url'] }} · {{ $p['owner_type'] === 'tenant' ? 'tu cuenta' : 'cuenta de la plataforma' }}@if($p['status'] !== 'active') · <span class="text-warning">sin acceso</span>@endif</div></td>
                    <td class="num">{{ $t ? $fmt($t['clicks']) : '—' }}</td>
                    <td class="num">{{ $t ? $fmt($t['impressions']) : '—' }}</td>
                    <td class="num">{{ $t ? number_format($t['ctr'] * 100, 1, ',', '.') . ' %' : '—' }}</td>
                    <td class="num">{{ ($t && $t['position'] !== null) ? number_format($t['position'], 1, ',', '.') : '—' }}</td>
                    <td class="num">@if($delta !== null)<span class="{{ $delta >= 0 ? 'gsc-up' : 'gsc-down' }}">{{ ($delta >= 0 ? '+' : '') . number_format($delta * 100, 0, ',', '.') }} %</span>@else — @endif</td>
                    <td style="font-size:.8rem;">{{ $p['last_sync_at'] ? date('d/m H:i', strtotime($p['last_sync_at'])) : 'Pendiente (cada noche)' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div></div></div>
    @elseif(!$isClient)
        <div class="card"><div class="card-body p-4 text-center text-muted">Tu sitio aún no está conectado a Search Console. El administrador de la plataforma lo activará desde su panel.</div></div>
    @endif

</div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var box = document.getElementById('gsc-own-domain');
    if (!box) return;
    var token = @json(csrf_token());
    box.querySelector('.gsc-verify').addEventListener('click', function () {
        var btn = this, msg = box.querySelector('.gsc-verify-msg');
        btn.disabled = true; msg.className = 'gsc-verify-msg text-muted'; msg.textContent = 'Verificando…';
        fetch(@json($baseUrl . '/verify'), { method: 'POST', headers: { 'X-CSRF-TOKEN': token }, body: new URLSearchParams({ domain: box.dataset.domain, _csrf: token }) })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var cls = { verified: 'text-success', pending: 'text-warning', manual: 'text-secondary', error: 'text-danger' }[res.status] || 'text-danger';
                msg.className = 'gsc-verify-msg ' + cls; msg.textContent = res.message;
                if (res.status === 'verified') { setTimeout(function () { location.reload(); }, 1200); } else { btn.disabled = false; }
            })
            .catch(function () { msg.className = 'gsc-verify-msg text-danger'; msg.textContent = 'Error de red'; btn.disabled = false; });
    });
})();
</script>
<script>
(function () {
    document.querySelectorAll('form[action$="/refresh"]').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            var b = e.submitter || f.querySelector('button');
            if (b) { b.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>'); setTimeout(function () { b.disabled = true; }, 0); }
            if (window.Swal) { Swal.fire({ title: 'Leyendo las propiedades de Search Console…', text: 'Puede tardar unos segundos.', allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, didOpen: function () { Swal.showLoading(); } }); }
        });
    });
    window.addEventListener('pageshow', function (e) { if (e.persisted) { location.reload(); } });

    var del = document.getElementById('gsc-delete-form');
    if (!del || !window.Swal) return;
    del.addEventListener('submit', function (e) {
        e.preventDefault();
        Swal.fire({
            title: 'Desconectar Search Console',
            html: 'Se desconectará tu cuenta de servicio y se <strong>borrarán los datos</strong> guardados. Confirma con tu contraseña de administrador.',
            icon: 'warning', input: 'password', inputPlaceholder: 'Contraseña de administrador',
            inputAttributes: { autocomplete: 'current-password' },
            showCancelButton: true, confirmButtonText: 'Desconectar y borrar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545',
            inputValidator: function (v) { return v ? undefined : 'Escribe tu contraseña'; }
        }).then(function (r) {
            if (r.isConfirmed) { del.querySelector('[name=confirm_password]').value = r.value; del.submit(); }
        });
    });
})();
</script>
@endpush
