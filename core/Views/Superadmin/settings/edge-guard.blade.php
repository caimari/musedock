@extends('layouts.app')
@section('title', $title)
@section('content')
@php
  $modeLabels = ['off' => 'Apagado', 'simulate' => 'Simulación', 'auto' => 'Automático'];
  $stateBadge = ['blocked' => ['danger', 'Bloqueada'], 'clear' => ['success', 'Responde'], 'glitch' => ['warning text-dark', 'Fallo puntual'], 'recovering' => ['info text-dark', 'Volviendo (espera ' . $restoreMinutes . ' min estable)'], 'unknown' => ['secondary', 'Sin datos']];
  $statusBadge = ['active' => ['danger', 'Sin proxy ahora'], 'restored' => ['success', 'Restaurado'], 'kept_manual' => ['warning', 'Cambiado a mano: respetado'], 'failed' => ['dark', 'Falló'], 'sim_closed' => ['secondary', 'Simulación cerrada'], 'cancelled_user' => ['secondary', 'Anulado a mano: queda sin proxy']];
  // Horas en la zona del país vigilado y, entre paréntesis, en UTC (la base de datos guarda UTC)
  $utc = fn ($t) => $guard->fmt($t);
  $proxiedCount = count(array_filter($records, fn ($r) => (int) $r['proxied']));
@endphp
<div class="row g-4">
  <div class="col-12 col-xl-10">

    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h3 class="card-title mb-0"><i class="bi bi-shield-exclamation me-2"></i>Bloqueos Cloudflare</h3>
        <span class="badge bg-{{ $mode === 'auto' ? 'success' : ($mode === 'simulate' ? 'info text-dark' : 'secondary') }} fs-6">{{ $modeLabels[$mode] }}</span>
      </div>
      <div class="card-body">
        <p class="text-muted mb-3" style="font-size:.9rem;">
          Unas sondas en conexiones reales comprueban cada minuto si llegan a las IPs de Cloudflare de tus dominios.
          Si una IP deja de responder desde alguna operadora (bloqueos de LaLiga en España, caídas de Cloudflare…) y tu servidor
          responde directo con un certificado válido, se quita la nube naranja de los dominios afectados; cuando la IP vuelve a responder
          de forma estable, se pone otra vez. Solo se toca lo que tenía el proxy puesto, y si alguien cambia un registro mientras tanto,
          se respeta su cambio. Las sondas no tienen las claves de Cloudflare: solo informan.
        </p>
        <form method="POST" action="/musedock/settings/edge-guard">{!! csrf_field() !!}
          <div class="row g-3">
            <div class="col-md-5">
              <label class="form-label fw-semibold">Modo</label>
              @foreach(['off' => 'No hace nada (solo deshace lo que ya cambió)', 'simulate' => 'Detecta y avisa de lo que haría, sin tocar Cloudflare', 'auto' => 'Quita y vuelve a poner el proxy solo'] as $m => $desc)
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="mode" id="eg-mode-{{ $m }}" value="{{ $m }}" @checked($mode === $m)>
                  <label class="form-check-label" for="eg-mode-{{ $m }}"><strong>{{ $modeLabels[$m] }}</strong> <span class="text-muted" style="font-size:.85rem;">— {{ $desc }}</span></label>
                </div>
              @endforeach
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold" for="eg-restore">Volver a poner el proxy tras</label>
              <div class="input-group"><input type="number" min="10" max="240" class="form-control" id="eg-restore" name="restore_minutes" value="{{ $restoreMinutes }}"><span class="input-group-text">min</span></div>
              <div class="form-text">Minutos seguidos respondiendo bien desde todas las sondas.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold" for="eg-excluded">Nunca tocar estos dominios</label>
              <textarea class="form-control" id="eg-excluded" name="excluded" rows="3" placeholder="uno por línea (incluye sus subdominios)">{{ $excluded }}</textarea>
            </div>
          </div>
          <button class="btn btn-primary btn-sm mt-3" type="submit"><i class="bi bi-check2 me-1"></i> Guardar</button>
        </form>
      </div>
    </div>

    @php
      $realActive = array_values(array_filter($active, fn ($c) => !(int) $c['simulated']));
      $simActive = array_values(array_filter($active, fn ($c) => (int) $c['simulated']));
      $blockedIps = array_keys(array_filter($states, fn ($s) => $s['state'] === 'blocked'));
    @endphp
    @if($realActive)
      <div class="alert alert-danger d-flex gap-3 align-items-start">
        <i class="bi bi-shield-fill-exclamation fs-3"></i>
        <div>
          <strong>Ahora mismo hay {{ count($realActive) }} dominio(s) sin la nube naranja</strong> porque sus IPs de Cloudflare están bloqueadas
          ({{ implode(', ', $blockedIps) ?: 'comprobando si ya responden' }}). Van directos a tu servidor y se ven desde España.
          <div class="mt-1" style="font-size:.85rem;">
            Se volverá a poner el proxy solo cuando esas IPs lleven {{ $restoreMinutes }} minutos seguidos respondiendo desde todas las sondas.
            Desde {{ $utc($realActive[0]['changed_at']) }}: {{ implode(', ', array_slice(array_column($realActive, 'name'), 0, 8)) }}{{ count($realActive) > 8 ? '…' : '' }}
          </div>
        </div>
      </div>
    @elseif($simActive)
      <div class="alert alert-info"><i class="bi bi-eye me-1"></i> <strong>Simulación:</strong> en automático ahora se habría quitado el proxy de {{ count($simActive) }} dominio(s). Cloudflare no se ha tocado.</div>
    @elseif($blockedIps)
      <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i> Hay IPs bloqueadas ({{ implode(', ', $blockedIps) }}) pero no se ha cambiado nada{{ $mode === 'off' ? ': el modo está apagado' : ' todavía (comprobando el servidor de origen)' }}.</div>
    @elseif($livePaths)
      <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i> Todo normal: ninguna IP de Cloudflare de tus dominios está bloqueada desde las sondas.</div>
    @else
      <div class="alert alert-secondary"><i class="bi bi-broadcast me-1"></i> Ninguna sonda ha informado en los últimos minutos: sin datos no se cambia nada.</div>
    @endif

    @if($newToken)
    <div class="alert alert-warning">
      <h5 class="alert-heading"><i class="bi bi-key me-1"></i> Token de la sonda «{{ $newToken['name'] }}»</h5>
      <p class="mb-2">Cópialo ahora: <strong>no se volverá a mostrar</strong> (solo se guarda su huella).</p>
      <code class="d-block p-2 bg-white border rounded user-select-all">{{ $newToken['token'] }}</code>
      <hr>
      <p class="mb-1"><strong>Instalar en la máquina de la sonda</strong> (como root; necesita python3 y curl):</p>
<pre class="bg-white border rounded p-2 mb-0" style="font-size:.8rem;white-space:pre-wrap;">curl -fsS {{ $apiBase }}/api/v1/edge-guard/probe.py -o /usr/local/bin/musedock-edge-probe.py
install -m 600 /dev/null /etc/musedock-edge-probe.token   # y pega el token dentro
cat &gt; /etc/musedock-edge-probe.conf &lt;&lt;'EOF'
URL={{ $apiBase }}
TOKEN_FILE=/etc/musedock-edge-probe.token
# PATHS=ONO:eth0,Orange:eth1   (nombre:interfaz de cada salida; vacío = la salida por defecto)
# RESOLVE=10.0.0.1             (opcional: IP por la que llegar al CMS, p. ej. por VPN)
EOF
echo '* * * * * root flock -n /run/musedock-edge-probe.lock python3 -I /usr/local/bin/musedock-edge-probe.py' &gt; /etc/cron.d/musedock-edge-probe</pre>
    </div>
    @endif

    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-broadcast me-2"></i>Sondas</h5>
        <div class="d-flex gap-2 flex-wrap">
        <a href="/musedock/settings/edge-guard/probe.py" class="btn btn-outline-secondary btn-sm text-nowrap" title="Script de la sonda (Python 3 + curl), para la máquina que vigilará"><i class="bi bi-download me-1"></i> Descargar sonda</a>
        <a href="/docs/escudo-bloqueos-cloudflare" target="_blank" rel="noopener" class="btn btn-outline-info btn-sm text-nowrap"><i class="bi bi-book me-1"></i> Guía</a>
        <form method="POST" action="/musedock/settings/edge-guard/probe" class="d-flex gap-2">{!! csrf_field() !!}
          <input type="text" name="name" class="form-control form-control-sm" placeholder="nombre, p. ej. b250" style="width:180px;" required>
          <button class="btn btn-outline-primary btn-sm text-nowrap" type="submit"><i class="bi bi-plus-circle me-1"></i> Crear sonda</button>
        </form>
        </div>
      </div>
      <div class="card-body p-0">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light"><tr><th>Sonda</th><th>Salidas (último informe)</th><th class="text-end"></th></tr></thead>
          <tbody>
          @forelse($probes as $p)
            <tr>
              <td><strong>{{ $p['name'] }}</strong><div class="text-muted" style="font-size:.75rem;">creada {{ $utc($p['created_at']) }}</div></td>
              <td style="font-size:.85rem;">
                @php $mine = array_filter($probePaths, fn ($pp) => $pp['probe'] === $p['name']); @endphp
                @forelse($mine as $pp)
                  @php $isLive = isset($livePaths[$pp['probe'] . '/' . $pp['path']]); @endphp
                  <div><span class="badge bg-{{ $isLive ? 'success' : 'secondary' }}">{{ $isLive ? 'viva' : 'muda' }}</span> <strong>{{ $pp['path'] }}</strong> <span class="text-muted">{{ $pp['public_ip'] }} · {{ $utc($pp['last']) }}</span></div>
                @empty
                  <span class="text-muted">Aún no ha informado.</span>
                @endforelse
              </td>
              <td class="text-end"><form method="POST" action="/musedock/settings/edge-guard/probe/revoke" onsubmit="return confirm('¿Revocar la sonda {{ $p['name'] }}?');">{!! csrf_field() !!}<input type="hidden" name="name" value="{{ $p['name'] }}"><button class="btn btn-outline-danger btn-sm" title="Revocar"><i class="bi bi-x-circle"></i></button></form></td>
            </tr>
          @empty
            <tr><td colspan="3" class="text-center text-muted p-3">No hay sondas. Crea una para la máquina que vigilará desde España.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-hdd-network me-2"></i>IPs de Cloudflare de tus dominios
          <span class="badge bg-light text-muted border fw-normal ms-2" style="font-size:.75rem;" id="eg-live" title="Las sondas informan cada minuto; la página se actualiza sola">
            <span class="spinner-grow spinner-grow-sm text-success" role="status" style="width:.6rem;height:.6rem;"></span>
            en directo · actualiza en <span id="eg-countdown">60</span> s
          </span>
        </h5>
        <form method="POST" action="/musedock/settings/edge-guard/inventory">{!! csrf_field() !!}
          <button class="btn btn-outline-secondary btn-sm" type="submit" title="Volver a leer los registros de Cloudflare"><i class="bi bi-arrow-repeat me-1"></i> Leer Cloudflare</button>
        </form>
      </div>
      <div class="card-body p-0">
        <div class="px-3 pt-2 text-muted" style="font-size:.8rem;">
          {{ count($records) }} registros de dominios del CMS, {{ $proxiedCount }} con proxy · última lectura: {{ $inventoryAt ? date('d/m H:i', $inventoryAt) : 'nunca' }}
          @if($inventoryErrors)<div class="text-danger">{{ $inventoryErrors }}</div>@endif
        </div>
        <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light"><tr><th>IP</th><th>Estado</th>@foreach($livePaths as $k => $lp)<th class="text-nowrap">{{ $lp['probe'] }} · {{ $lp['path'] }}</th>@endforeach<th>Dominios</th></tr></thead>
          <tbody>
          @forelse($states as $ip => $s)
            @php
              // Etiqueta para personas: «clear» (apta para restaurar) exige 30 min de historial; una IP que nunca
              // ha fallado ya «Responde». Solo la que falló y ahora va bien está «Volviendo».
              $view = match (true) {
                $s['state'] === 'blocked', $s['state'] === 'unknown' => $s['state'],
                in_array(false, $s['paths'], true) => 'glitch',
                $s['state'] === 'recovering' && !empty($s['had_fail']) => 'recovering',
                default => 'clear',
              };
              [$bc, $bl] = $stateBadge[$view] ?? ['secondary', $view];
              $names = $byIp[$ip] ?? [];
            @endphp
            <tr>
              <td><code>{{ $ip }}</code></td>
              <td><span class="badge bg-{{ $bc }}">{{ $bl }}</span></td>
              @foreach($livePaths as $k => $lp)
                @php $v = $s['paths'][$k] ?? null; @endphp
                <td>@if($v === null)<span class="text-muted">—</span>@elseif($v)<i class="bi bi-check-circle-fill text-success"></i>@else<i class="bi bi-x-circle-fill text-danger"></i>@endif</td>
              @endforeach
              <td style="font-size:.8rem;">{{ count($names) }} @if($names)<span class="text-muted">· {{ implode(', ', array_slice($names, 0, 4)) }}{{ count($names) > 4 ? '…' : '' }}</span>@endif</td>
            </tr>
          @empty
            <tr><td colspan="{{ 3 + count($livePaths) }}" class="text-center text-muted p-3">Sin datos todavía: pulsa «Leer Cloudflare».</td></tr>
          @endforelse
          </tbody>
        </table>
        </div>
      </div>
    </div>

    @php
      $dWith = count(array_filter($domains, fn ($d) => $d['original']));
      $dOff = count(array_filter($domains, fn ($d) => $d['original'] && !$d['now']));
    @endphp
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-globe2 me-2"></i>Tus dominios en Cloudflare</h5>
        <div class="text-muted mt-1" style="font-size:.82rem;">
          {{ count($domains) }} nombres: <strong>{{ $dWith }}</strong> configurados con nube naranja y <strong>{{ count($domains) - $dWith }}</strong> sin ella (directos; el escudo nunca les pone el proxy).
          @if($dOff) Ahora mismo <strong class="text-danger">{{ $dOff }}</strong> están sin proxy por un bloqueo. @endif
        </div>
      </div>
      <div class="card-body p-0">
        <details @if($dOff) open @endif>
          <summary class="px-3 py-2 text-primary" style="cursor:pointer;font-size:.88rem;">Ver la lista completa</summary>
          <div class="table-responsive">
          <table class="table table-sm align-middle mb-0" style="font-size:.85rem;">
            <thead class="table-light"><tr><th>Nombre</th><th>Apunta a</th><th>Configuración original</th><th>Ahora</th></tr></thead>
            <tbody>
            @foreach($domains as $d)
              <tr>
                <td><a href="https://{{ $d['name'] }}/" target="_blank" rel="noopener noreferrer" class="fw-bold text-reset text-decoration-none" title="Abrir en una pestaña nueva">{{ $d['name'] }} <i class="bi bi-box-arrow-up-right" style="font-size:.7em;opacity:.6;"></i></a>@if($d['excluded']) <span class="badge bg-secondary">nunca tocar</span>@endif</td>
                <td class="text-muted">{{ $d['type'] }} {{ $d['content'] }}</td>
                <td>
                  @php
                    // Qué pasará si se cambia, para explicarlo en la ventana de confirmación
                    if ($d['original']) {
                      $what = !$d['now']
                        ? 'Ahora ya va directo por un bloqueo, así que no se toca Cloudflare: solo se anula el regreso. Cuando acabe el bloqueo no se le volverá a poner el proxy.'
                        : 'Se quitará la nube naranja en Cloudflare ahora mismo: irá directo a tu servidor.';
                    } else {
                      $what = ($d['zone_blocked'] && !$d['excluded'])
                        ? 'Su zona tiene IPs de Cloudflare bloqueadas ahora (' . implode(', ', $d['zone_blocked']) . '): seguirá directo de momento y se le pondrá el proxy él solo cuando acabe el bloqueo.'
                        : 'Se pondrá la nube naranja en Cloudflare ahora mismo.';
                    }
                  @endphp
                  <button type="button" class="badge border-0 eg-original {{ $d['original'] ? '' : 'bg-light text-dark border' }}" style="cursor:pointer;{{ $d['original'] ? 'background:#f6821f;' : '' }}"
                          title="Pulsa para cambiar la configuración original"
                          data-record="{{ $d['record_id'] }}" data-name="{{ $d['name'] }}" data-to="{{ $d['original'] ? '0' : '1' }}" data-what="{{ $what }}">
                    @if($d['original'])<i class="bi bi-cloud-fill"></i> Con proxy @else<i class="bi bi-cloud"></i> Sin proxy (directo) @endif
                    <i class="bi bi-pencil ms-1" style="font-size:.7em;opacity:.7;"></i>
                  </button>
                </td>
                <td>
                  @if($d['original'] && !$d['now'] && $d['manual'])<span class="badge bg-warning text-dark">Pendiente de poner proxy</span> <span class="text-muted">cuando acabe el bloqueo (cambio manual)</span>
                  @elseif($d['original'] && !$d['now'])<span class="badge bg-danger">Sin proxy por bloqueo</span> <span class="text-muted">desde {{ $utc($d['changed_at']) }}</span>
                  @elseif($d['now'])<span class="text-muted">Igual que el original</span>
                  @else<span class="text-muted">Igual que el original</span>@endif
                </td>
              </tr>
            @endforeach
            </tbody>
          </table>
          </div>
        </details>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Cambios ({{ count($active) }} activos)</h5></div>
      <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light"><tr><th>Dominio</th><th>Estado</th><th>Motivo</th><th>Quitado</th><th>Restaurado</th></tr></thead>
          <tbody>
          @forelse($history as $c)
            @php [$sc, $sl] = $statusBadge[$c['status']] ?? ['secondary', $c['status']]; @endphp
            <tr>
              <td><strong>{{ $c['name'] }}</strong> @if((int) $c['simulated'])<span class="badge bg-info text-dark">simulado</span>@endif</td>
              <td><span class="badge bg-{{ $sc }}">{{ (int) $c['simulated'] && $c['status'] === 'active' ? 'Lo habría quitado' : $sl }}</span>@if($c['note'])<div class="text-muted" style="font-size:.75rem;">{{ $c['note'] }}</div>@endif</td>
              <td style="font-size:.8rem;">{{ ['bloqueo' => 'Bloqueo de operadoras (desde fuera respondía)', 'cloudflare' => 'Cloudflare no respondía', 'manual' => 'Cambio manual a «con proxy» durante un bloqueo'][$c['cause']] ?? $c['cause'] }}<div class="text-muted">{{ str_replace(',', ', ', $c['edge_ips']) }}</div></td>
              <td class="text-nowrap" style="font-size:.85rem;">{{ $utc($c['changed_at']) }}</td>
              <td class="text-nowrap" style="font-size:.85rem;">{{ $utc($c['restored_at']) }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted p-3">Ningún cambio todavía.</td></tr>
          @endforelse
          </tbody>
        </table>
        </div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-journal-text me-2"></i>Registro</h5>
        <form method="POST" action="/musedock/settings/edge-guard/clear-log" onsubmit="return confirm('¿Vaciar el registro y el historial cerrado? Los cambios activos se conservan.');">{!! csrf_field() !!}
          <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash me-1"></i> Vaciar registro</button>
        </form>
      </div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0" style="font-size:.85rem;">
          <tbody>
          @forelse($events as $ev)
            <tr><td class="text-nowrap text-muted" style="width:170px;">{{ $utc($ev['created_at']) }}</td><td><span class="badge bg-{{ $ev['level'] === 'error' ? 'danger' : ($ev['level'] === 'warn' ? 'warning text-dark' : 'light text-dark') }}">{{ $ev['level'] }}</span> {{ $ev['message'] }}</td></tr>
          @empty
            <tr><td class="text-center text-muted p-3">Sin entradas.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>
<form method="POST" action="/musedock/settings/edge-guard/original" id="eg-original-form" style="display:none;">{!! csrf_field() !!}
  <input type="hidden" name="record_id"><input type="hidden" name="proxied"><input type="hidden" name="confirm_password">
</form>
<script>
// Cambio manual de la configuración original: ventana con explicación y contraseña
document.querySelectorAll('.eg-original').forEach(function (b) {
  b.addEventListener('click', function () {
    window.egModalOpen = true;
    var esc = function (s) { return String(s).replace(/[&<>"]/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); };
    var to = b.dataset.to === '1' ? '<strong style="color:#f6821f;">Con proxy</strong>' : '<strong>Sin proxy (directo)</strong>';
    Swal.fire({
      title: 'Cambiar la configuración original', icon: 'question', input: 'password', inputPlaceholder: 'Contraseña de administrador',
      html: '<div style="text-align:left;font-size:.95rem;"><p><strong>' + esc(b.dataset.name) + '</strong> pasará a: ' + to + '</p><p class="text-muted">' + esc(b.dataset.what) + '</p><p class="mb-0">El escudo respetará esta configuración a partir de ahora. Confirma con tu contraseña.</p></div>',
      inputAttributes: { autocomplete: 'current-password' }, showCancelButton: true,
      confirmButtonText: 'Cambiar', cancelButtonText: 'Cancelar',
      inputValidator: function (v) { return v ? undefined : 'Escribe tu contraseña'; }
    }).then(function (r) {
      window.egModalOpen = false;
      if (!r.isConfirmed) return;
      var f = document.getElementById('eg-original-form');
      f.record_id.value = b.dataset.record; f.proxied.value = b.dataset.to; f.confirm_password.value = r.value;
      f.submit();
    });
  });
});

// Refresco automático cada minuto, salvo si se está escribiendo en un formulario
(function () {
  var left = 60, el = document.getElementById('eg-countdown'), typing = false;
  document.addEventListener('focusin', function (e) { if (e.target.matches('input, textarea, select')) typing = true; });
  document.addEventListener('focusout', function () { typing = false; });
  setInterval(function () {
    if (typing || window.egModalOpen) { el.textContent = '—'; return; }
    left--;
    el.textContent = left;
    if (left <= 0) { location.reload(); }
  }, 1000);
})();
</script>
@endsection
