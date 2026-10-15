@extends('layouts::app')

@section('title', 'Search Console')

@section('content')
@php
    $sumClicks = array_sum(array_column($totals, 'clicks'));
    $sumImpr = array_sum(array_column($totals, 'impressions'));
    $fmt = fn($n) => number_format((int) $n, 0, ',', '.');
    $statusLabels = ['active' => ['Activa', 'success'], 'no_access' => ['Sin acceso', 'warning'], 'pending' => ['Pendiente', 'secondary'], 'error' => ['Error', 'danger']];
@endphp
<style>
.gsc-page .card { border:1px solid #e5eaf1;border-radius:12px;box-shadow:0 3px 14px rgba(30,50,80,.035); }
.gsc-page .stat-badge { display:flex;align-items:center;gap:.35rem;font-size:.85rem;padding:.4rem .75rem;border-radius:6px;background:#f8f9fa;border:1px solid #e9ecef; }
.gsc-page .table td,.gsc-page .table th { padding:12px 14px;vertical-align:middle; }
.gsc-page .num { text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap; }
.gsc-dot { width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:6px;background:#ccd3dd; }
.gsc-dot-blue{background:#397de0}.gsc-dot-green{background:#279c65}.gsc-dot-orange{background:#e49a29}.gsc-dot-purple{background:#8a56c5}.gsc-dot-red{background:#d84c53}
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
                <h3 class="mb-0" style="font-size:1.25rem;font-weight:700;">Search Console de tus dominios</h3>
                <p class="text-muted mb-0" style="font-size:0.85rem;">Clics, impresiones y posición de todos tus sitios en un solo panel (últimos 28 días)</p>
            </div>
        </div>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
            <div class="stat-badge"><i class="bi bi-globe2"></i><span>{{ count($properties) }} dominios</span></div>
            <div class="stat-badge" style="background:rgba(26,115,232,.08);border-color:rgba(26,115,232,.2);color:#1a5fc4;"><i class="bi bi-cursor"></i><span>{{ $fmt($sumClicks) }} clics</span></div>
            <div class="stat-badge" style="background:rgba(52,168,83,.08);border-color:rgba(52,168,83,.2);color:#1e7e34;"><i class="bi bi-eye"></i><span>{{ $fmt($sumImpr) }} impresiones</span></div>
            <a href="/musedock/tenants" class="stat-badge" style="text-decoration:none;color:#495057;"><i class="bi bi-building"></i><span>Tenants</span></a>
        </div>
    </div>


    @if($clientCount > 0)
    <div class="alert alert-light border d-flex gap-2 align-items-start" style="font-size:.88rem;">
        <i class="bi bi-shield-lock" style="font-size:1.1rem;color:#5f6873;"></i>
        <div><strong>{{ $clientCount }} {{ $clientCount === 1 ? 'dominio de cliente excluido' : 'dominios de clientes excluidos' }}.</strong>
        Su SEO es privado: cada cliente conecta su propia cuenta de Google desde su panel y solo él ve sus datos. La cuenta de la plataforma nunca los lee.</div>
    </div>
    @endif

    {{-- ===== Cuenta de servicio de la plataforma ===== --}}
    @if(!$credential)
    <div class="card mb-4"><div class="card-body p-4">
        <h5 style="font-weight:700;">Conecta la cuenta de servicio de Google</h5>
        <ol class="gsc-steps" style="font-size:.9rem;">
            <li>En <a href="https://console.cloud.google.com/projectcreate" target="_blank" rel="noopener">Google Cloud Console</a> crea un proyecto (p. ej. «musedock-gsc»).</li>
            <li>Activa <a href="https://console.cloud.google.com/apis/library/searchconsole.googleapis.com" target="_blank" rel="noopener">Google Search Console API</a> y <a href="https://console.cloud.google.com/apis/library/siteverification.googleapis.com" target="_blank" rel="noopener">Site Verification API</a>.</li>
            <li>En <em>IAM y administración → Cuentas de servicio</em> crea una cuenta (sin roles) y en <em>Claves → Añadir clave → JSON</em> descarga el fichero.</li>
            <li>Súbelo aquí. Se guarda cifrado y no se vuelve a mostrar.</li>
        </ol>
        <form method="post" action="{{ $baseUrl }}/credential" enctype="multipart/form-data" class="d-flex gap-2 flex-wrap align-items-center">
            {!! csrf_field() !!}
            <input type="file" name="key_file" accept=".json,application/json" class="form-control" style="max-width:420px;" required>
            <button class="btn btn-primary" type="submit"><i class="bi bi-plug"></i> Conectar</button>
        </form>
    </div></div>
    @else
    <div class="card mb-4"><div class="card-body p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div style="font-size:.9rem;">
            <i class="bi bi-person-badge"></i> <strong>{{ $credential['client_email'] }}</strong>
            <span class="text-muted">· proyecto {{ $credential['project_id'] ?: '—' }}</span>
            @if($credential['last_test_ok'] === false)
                <span class="badge bg-danger ms-1">Error: {{ $credential['last_error'] }}</span>
            @else
                <span class="badge bg-success ms-1">Conectada</span>
            @endif
            <div class="text-muted" style="font-size:.8rem;">Para dar acceso a una propiedad a mano: Search Console → Configuración → Usuarios y permisos → añade este email.</div>
        </div>
        <div class="d-flex gap-2">
            <form method="post" action="{{ $baseUrl }}/refresh">{!! csrf_field() !!}<button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-arrow-repeat"></i> Releer propiedades</button></form>
            <form method="post" action="{{ $baseUrl }}/credential/delete" onsubmit="return confirm('¿Desconectar la cuenta y borrar todos los datos de Search Console guardados?');">{!! csrf_field() !!}<button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-x-circle"></i> Desconectar</button></form>
        </div>
    </div></div>

    {{-- ===== Dominios ===== --}}
    <div class="card mb-4"><div class="card-body p-0">
        <div class="d-flex gap-2 flex-wrap align-items-center p-3 border-bottom">
            <input type="search" id="gsc-filter-text" class="form-control form-control-sm" placeholder="Filtrar por dominio o tenant…" style="max-width:260px;" aria-label="Filtrar por dominio o tenant">
            <select id="gsc-filter-color" class="form-select form-select-sm" style="max-width:180px;" aria-label="Filtrar por color">
                <option value="">Todos los colores</option>
                @foreach(['blue' => 'Azul', 'green' => 'Verde', 'orange' => 'Naranja', 'purple' => 'Morado', 'red' => 'Rojo', 'none' => 'Sin color'] as $v => $l)
                    <option value="{{ $v }}">{{ $l }}</option>
                @endforeach
            </select>
            <select id="gsc-filter-status" class="form-select form-select-sm" style="max-width:180px;" aria-label="Filtrar por estado">
                <option value="">Todos los estados</option>
                <option value="active">Activas</option>
                <option value="no_access">Sin acceso</option>
            </select>
            <div class="ms-auto text-muted" style="font-size:.85rem;" id="gsc-filter-sum"></div>
        </div>
        <div class="table-responsive">
        <table class="table table-hover mb-0" id="gsc-table">
            <thead><tr>
                <th>Dominio</th><th>Tenant</th><th>Estado</th>
                <th class="num">Clics</th><th class="num">Impresiones</th><th class="num">CTR</th><th class="num">Posición</th><th class="num">vs. 28 d antes</th><th>Última sync.</th>
            </tr></thead>
            <tbody>
            @forelse($properties as $p)
                @php
                    $t = $totals[(int) $p['id']] ?? null;
                    $delta = ($t && $t['prev_clicks'] > 0) ? ($t['clicks'] - $t['prev_clicks']) / $t['prev_clicks'] : null;
                    [$sLabel, $sClass] = $statusLabels[$p['status']] ?? [$p['status'], 'secondary'];
                    $color = $p['label_color'] ?: 'none';
                @endphp
                <tr data-text="{{ strtolower($p['domain'] . ' ' . ($p['tenant_name'] ?? 'sitio principal')) }}" data-color="{{ $color }}" data-status="{{ $p['status'] }}" data-clicks="{{ $t['clicks'] ?? 0 }}" data-impr="{{ $t['impressions'] ?? 0 }}" @if($focusTenant !== null && (int) $p['tenant_id'] === $focusTenant) class="table-primary" @endif>
                    <td><strong>{{ $p['domain'] }}</strong><div class="text-muted" style="font-size:.75rem;">{{ $p['site_url'] }}</div></td>
                    <td><span class="gsc-dot gsc-dot-{{ $color }}"></span>{{ $p['tenant_name'] ?? 'Sitio principal' }}</td>
                    <td><span class="badge bg-{{ $sClass }}">{{ $sLabel }}</span></td>
                    <td class="num">{{ $t ? $fmt($t['clicks']) : '—' }}</td>
                    <td class="num">{{ $t ? $fmt($t['impressions']) : '—' }}</td>
                    <td class="num">{{ $t ? number_format($t['ctr'] * 100, 1, ',', '.') . ' %' : '—' }}</td>
                    <td class="num">{{ ($t && $t['position'] !== null) ? number_format($t['position'], 1, ',', '.') : '—' }}</td>
                    <td class="num">
                        @if($delta !== null)
                            <span class="{{ $delta >= 0 ? 'gsc-up' : 'gsc-down' }}">{{ ($delta >= 0 ? '+' : '') . number_format($delta * 100, 0, ',', '.') }} %</span>
                        @else — @endif
                    </td>
                    <td style="font-size:.8rem;">{{ $p['last_sync_at'] ? date('d/m H:i', strtotime($p['last_sync_at'])) : 'Pendiente' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted p-4">La cuenta aún no ve ninguna propiedad de tus dominios. Dalas de alta abajo o añade su email en Search Console.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div></div>

    @if(!empty($missing))
    <div class="card mb-4"><div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <div>
                <h6 class="mb-0" style="font-weight:700;">Dominios propios sin acceso todavía ({{ count($missing) }})</h6>
                <div class="text-muted" style="font-size:.82rem;">«Dar de alta» verifica el dominio con un TXT en Cloudflare y lo añade a Search Console. Tus propietarios actuales no cambian.</div>
            </div>
            <button type="button" class="btn btn-primary btn-sm" id="gsc-verify-all"><i class="bi bi-lightning"></i> Dar de alta todos</button>
        </div>
        <ul class="list-group list-group-flush">
            @foreach($missing as $m)
            <li class="list-group-item d-flex justify-content-between align-items-center gap-2 flex-wrap px-0" data-domain="{{ $m['domain'] }}">
                <span><strong>{{ $m['domain'] }}</strong> <span class="text-muted" style="font-size:.82rem;">· {{ $m['name'] }}</span></span>
                <span class="d-flex align-items-center gap-2">
                    <span class="gsc-verify-msg text-muted" style="font-size:.8rem;max-width:520px;"></span>
                    <button type="button" class="btn btn-outline-primary btn-sm gsc-verify">Dar de alta</button>
                </span>
            </li>
            @endforeach
        </ul>
    </div></div>
    @endif
    @endif

</div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var table = document.getElementById('gsc-table');
    if (table) {
        var text = document.getElementById('gsc-filter-text'), color = document.getElementById('gsc-filter-color'),
            status = document.getElementById('gsc-filter-status'), sum = document.getElementById('gsc-filter-sum');
        var fmt = new Intl.NumberFormat('es-ES');
        function apply() {
            var q = text.value.trim().toLowerCase(), c = color.value, s = status.value, n = 0, clicks = 0, impr = 0;
            table.querySelectorAll('tbody tr[data-text]').forEach(function (tr) {
                var show = (!q || tr.dataset.text.indexOf(q) !== -1) && (!c || tr.dataset.color === c) && (!s || tr.dataset.status === s);
                tr.style.display = show ? '' : 'none';
                if (show) { n++; clicks += +tr.dataset.clicks; impr += +tr.dataset.impr; }
            });
            sum.textContent = n + ' dominios · ' + fmt.format(clicks) + ' clics · ' + fmt.format(impr) + ' impresiones';
        }
        [text, color, status].forEach(function (el) { el.addEventListener('input', apply); });
        apply();
    }

    var token = @json(csrf_token());
    function verify(li) {
        var btn = li.querySelector('.gsc-verify'), msg = li.querySelector('.gsc-verify-msg');
        btn.disabled = true; msg.className = 'gsc-verify-msg text-muted'; msg.textContent = 'Verificando…';
        var body = new URLSearchParams({ domain: li.dataset.domain, _csrf: token });
        return fetch(@json($baseUrl . '/verify'), { method: 'POST', headers: { 'X-CSRF-TOKEN': token }, body: body })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var cls = { verified: 'text-success', pending: 'text-warning', manual: 'text-secondary', error: 'text-danger' }[res.status] || 'text-danger';
                msg.className = 'gsc-verify-msg ' + cls; msg.textContent = res.message;
                btn.disabled = res.status === 'verified';
                if (res.status === 'verified') btn.textContent = 'Hecho';
            })
            .catch(function () { msg.className = 'gsc-verify-msg text-danger'; msg.textContent = 'Error de red'; btn.disabled = false; });
    }
    document.querySelectorAll('.gsc-verify').forEach(function (b) { b.addEventListener('click', function () { verify(b.closest('li')); }); });
    var all = document.getElementById('gsc-verify-all');
    if (all) all.addEventListener('click', function () {
        all.disabled = true;
        var items = Array.prototype.slice.call(document.querySelectorAll('li[data-domain]'));
        items.reduce(function (p, li) { return p.then(function () { return li.querySelector('.gsc-verify').disabled ? null : verify(li); }); }, Promise.resolve())
            .then(function () { all.disabled = false; });
    });
})();
</script>
@endpush
